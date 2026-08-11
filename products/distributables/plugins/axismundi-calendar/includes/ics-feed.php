<?php
/**
 * The subscription endpoints.
 *
 * `/events.ics` is a rolling subscription window, not the site's whole history. A subscription feed
 * is re-fetched forever, so its size is a running cost paid by every subscriber on every poll;
 * WordCamp Central's own feed is upcoming-events-only for the same reason. Past Events are not
 * deleted by this -- they remain on the site and in the calendar UI. They simply stop being carried
 * in a document whose purpose is "what is coming up".
 *
 * `/event/{slug}.ics` has no window. A single Event is fetched deliberately, usually to add one
 * thing to a personal calendar, and refusing to serve last year's is a different question from
 * whether to carry it in a rolling feed.
 *
 * Conditional GET is answered because subscription clients poll on their own schedule and cannot be
 * pushed to. Without `ETag` the server re-serializes the whole calendar for every poll from every
 * subscriber, forever.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * How far back the rolling subscription feed reaches.
 *
 * Three months, so a subscriber who adds the feed today still sees the season just gone rather than
 * a calendar that begins abruptly at today's date.
 */
const AXISMUNDI_CAL_FEED_PAST_MONTHS = 3;

/**
 * Register the feed routes.
 *
 * On `init`, with the post type, because rewrite rules belong to that lifecycle. A REST route would
 * be the wrong shape: subscription clients are handed a URL by a human and expect a file at it.
 *
 * @return void
 */
function axismundi_cal_register_ics_routes() : void {
	add_rewrite_rule( '^events\.ics$', 'index.php?ax_cal_ics=site', 'top' );
	add_rewrite_rule(
		'^event/([^/]+)\.ics$',
		'index.php?ax_cal_ics=event&ax_cal_event=$matches[1]',
		'top'
	);
	add_rewrite_rule(
		'^calendar/([^/]+)\.ics$',
		'index.php?ax_cal_ics=calendar&ax_cal_slug=$matches[1]',
		'top'
	);
	add_rewrite_rule(
		'^calendar/([^/]+)/?$',
		'index.php?ax_cal_page=1&ax_cal_slug=$matches[1]',
		'top'
	);
}
add_action( 'init', 'axismundi_cal_register_ics_routes', 9 );

/**
 * Register the query vars the routes set.
 *
 * @param string[] $vars Query vars.
 * @return string[]
 */
function axismundi_cal_ics_query_vars( array $vars ) : array {
	$vars[] = 'ax_cal_ics';
	$vars[] = 'ax_cal_event';
	$vars[] = 'ax_cal_slug';
	$vars[] = 'ax_cal_page';
	return $vars;
}
add_filter( 'query_vars', 'axismundi_cal_ics_query_vars' );

/**
 * Keep the canonical redirect away from the feed URLs.
 *
 * WordPress adds a trailing slash to anything that looks like a directory, turning `/events.ics`
 * into `/events.ics/`. Most subscription clients follow the redirect, so this looks harmless until
 * one does not -- and the URL is handed around by people who reasonably expect a file to be a file.
 *
 * @param string|false $redirect Proposed redirect.
 * @return string|false
 */
function axismundi_cal_ics_no_canonical( $redirect ) {
	$which = get_query_var( 'ax_cal_ics' );
	return ( '' === $which || null === $which ) ? $redirect : false;
}
add_filter( 'redirect_canonical', 'axismundi_cal_ics_no_canonical' );

/**
 * The schedules a rolling feed carries.
 *
 * A recurring series is included while it is still running, judged by its rule rather than by its
 * start: a weekly meeting that began in 2019 is current, and a series that ended last year is not.
 * A single Event is included when it has not yet finished, plus the trailing window.
 *
 * @param string $cutoff_utc Earliest instant of interest, UTC.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_feed_schedules( string $cutoff_utc, int $calendar_id = 0 ) : array {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_schedules_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- feed query over this plugin's own table.
	$rows = (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY dtstart_local ASC", ARRAY_A );

	$horizon = gmdate( 'Y-m-d H:i:s', strtotime( $cutoff_utc . ' +5 years' ) );
	$out     = array();
	foreach ( $rows as $schedule ) {
		if ( $calendar_id > 0 && $calendar_id !== (int) $schedule['calendar_id'] ) {
			continue;
		}
		$post = get_post( (int) $schedule['event_post_id'] );
		if ( ! $post instanceof WP_Post || ! axismundi_cal_event_listable( $post ) ) {
			continue;
		}
		// Asking the expander whether anything is left is what makes a bounded rule -- COUNT or
		// UNTIL -- drop out of the feed once it has finished, without duplicating that logic here.
		if ( empty( axismundi_cal_range( $schedule, $cutoff_utc, $horizon ) ) ) {
			continue;
		}
		$out[] = array( 'schedule' => $schedule, 'post' => $post );
	}
	return $out;
}

/**
 * Build the feed body for the whole site.
 *
 * @return array{body:string,modified:int}
 */
function axismundi_cal_site_feed( int $calendar_id = 0, string $name = '', string $display_tz = '' ) : array {
	$cutoff_ts = (int) strtotime( '-' . AXISMUNDI_CAL_FEED_PAST_MONTHS . ' months' );
	$cutoff    = gmdate( 'Y-m-d H:i:s', $cutoff_ts );
	$rows      = axismundi_cal_feed_schedules( $cutoff, $calendar_id );

	$components = array();
	$tzids      = array();
	$modified   = 0;
	foreach ( $rows as $row ) {
		$components = array_merge( $components, axismundi_cal_ics_vevent( $row['schedule'], $row['post'] ) );
		$tzids[]    = (string) $row['schedule']['timezone'];
		$modified   = max( $modified, (int) strtotime( (string) $row['schedule']['updated_at'] . ' UTC' ) );
	}

	/*
	 * The window moves on its own, so the body can change with no row edited: the day an Event
	 * falls past the cutoff, this document loses a component while every `updated_at` stays where
	 * it was. Reporting only row timestamps would leave a client that sends `If-Modified-Since`
	 * holding a calendar that still lists something the feed no longer carries -- and, having been
	 * told nothing changed, with no reason to ask again.
	 */
	$modified = max( $modified, axismundi_cal_feed_last_expiry( $cutoff_ts ) );

	return array(
		'body'     => axismundi_cal_ics_document(
			$components,
			$tzids,
			$cutoff_ts,
			(int) strtotime( '+2 years' ),
			'' !== $name ? $name : (string) get_bloginfo( 'name' ),
			$display_tz
		),
		'modified' => $modified > 0 ? $modified : time(),
	);
}

/**
 * When the feed most recently lost an Event to the moving cutoff.
 *
 * An Event leaves the window `AXISMUNDI_CAL_FEED_PAST_MONTHS` after its last occurrence ends, so
 * that instant is a moment the document changed. Only the most recent one matters, since earlier
 * departures are already reflected in it.
 *
 * Under-reporting here is safe and over-reporting is not: too old an answer costs a subscriber one
 * unnecessary full fetch, while too new an answer hands them a `304` for a document they do not
 * have. Schedules whose departure is long past are therefore skipped rather than examined, and an
 * unbounded rule never departs at all.
 *
 * @param int $cutoff_ts Current window cutoff, timestamp.
 * @return int Timestamp, or 0.
 */
function axismundi_cal_feed_last_expiry( int $cutoff_ts ) : int {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return 0;
	}
	$window  = AXISMUNDI_CAL_FEED_PAST_MONTHS;
	$table   = axismundi_cal_schedules_table();
	$horizon = gmdate( 'Y-m-d H:i:s', $cutoff_ts + ( 5 * YEAR_IN_SECONDS ) );
	// Only recent departures can be the most recent one, and only finite series depart at all.
	$earliest = gmdate( 'Y-m-d H:i:s', $cutoff_ts - YEAR_IN_SECONDS );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- feed query over this plugin's own table.
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE dtend_local >= %s ORDER BY dtend_local DESC", $earliest ),
		ARRAY_A
	);

	$latest = 0;
	$cutoff = gmdate( 'Y-m-d H:i:s', $cutoff_ts );
	foreach ( $rows as $schedule ) {
		$post = get_post( (int) $schedule['event_post_id'] );
		if ( ! $post instanceof WP_Post || ! axismundi_cal_event_listable( $post ) ) {
			continue;
		}
		if ( ! empty( axismundi_cal_range( $schedule, $cutoff, $horizon ) ) ) {
			// Still running, so it has not departed.
			continue;
		}
		$past = axismundi_cal_range( $schedule, $earliest, $cutoff );
		if ( empty( $past ) ) {
			continue;
		}
		$last = 0;
		foreach ( $past as $occurrence ) {
			$last = max( $last, (int) strtotime( (string) $occurrence['end_utc'] . ' UTC' ) );
		}
		$latest = max( $latest, (int) strtotime( '+' . $window . ' months', $last ) );
	}
	return $latest;
}

/**
 * Whether a conditional request may be answered `304`.
 *
 * `ETag` wins whenever the client sent one. The two validators do not answer the same question: the
 * entity tag describes this document, while `Last-Modified` describes the rows it was built from,
 * and those diverge every time the rolling window moves without an edit. Treating them as
 * interchangeable -- satisfying either one -- means a client that sends both can be told nothing
 * changed on the strength of the weaker answer, which is precisely the case where it has changed.
 *
 * @param string   $etag      Current entity tag.
 * @param int      $modified  Current modification time.
 * @param string   $sent_etag `If-None-Match`, or ''.
 * @param int|false $sent_time `If-Modified-Since` as a timestamp, or false.
 * @return bool
 */
function axismundi_cal_ics_not_modified( string $etag, int $modified, string $sent_etag, $sent_time ) : bool {
	if ( '' !== $sent_etag ) {
		return $sent_etag === $etag;
	}
	return false !== $sent_time && $sent_time >= $modified;
}

/**
 * Build the feed body for one Event.
 *
 * @param WP_Post $post Event post.
 * @return array{body:string,modified:int}|null
 */
function axismundi_cal_event_feed( WP_Post $post ) : ?array {
	$schedule = axismundi_cal_schedule_for_event( (int) $post->ID );
	if ( ! is_array( $schedule ) ) {
		return null;
	}
	$start = (int) strtotime( (string) $schedule['dtstart_local'] );
	return array(
		'body'     => axismundi_cal_ics_document(
			axismundi_cal_ics_vevent( $schedule, $post ),
			array( (string) $schedule['timezone'] ),
			$start,
			(int) strtotime( '+2 years', $start ),
			get_the_title( $post )
		),
		'modified' => (int) strtotime( (string) $schedule['updated_at'] . ' UTC' ),
	);
}

/**
 * Serve a feed, answering conditional requests.
 *
 * The `ETag` is a hash of the body rather than a revision counter, so it changes when and only when
 * the document does. A counter would have to be bumped by every path that can affect the output --
 * a title edit, a cancellation, a venue change -- and the one that gets forgotten is the one that
 * serves subscribers a stale calendar indefinitely.
 *
 * @return void
 */
function axismundi_cal_serve_ics() : void {
	$which = get_query_var( 'ax_cal_ics' );
	if ( '' === $which || null === $which ) {
		return;
	}

	$feed = null;
	if ( 'site' === $which ) {
		$feed = axismundi_cal_site_feed();
	} elseif ( 'calendar' === $which ) {
		$calendar = axismundi_cal_calendar_by_slug( (string) get_query_var( 'ax_cal_slug' ) );
		if ( is_array( $calendar ) && 'local' === (string) $calendar['kind'] && axismundi_cal_is_publicly_readable( (int) $calendar['id'] ) ) {
			$feed = axismundi_cal_site_feed(
				(int) $calendar['id'],
				(string) $calendar['name'],
				axismundi_cal_calendar_timezone( $calendar )
			);
		}
	} elseif ( 'event' === $which ) {
		$slug = sanitize_title( (string) get_query_var( 'ax_cal_event' ) );
		$post = $slug ? get_page_by_path( $slug, OBJECT, AXISMUNDI_CAL_EVENT_POST_TYPE ) : null;
		if ( $post instanceof WP_Post && axismundi_cal_event_listable( $post ) ) {
			$feed = axismundi_cal_event_feed( $post );
		}
	}

	if ( null === $feed ) {
		status_header( 404 );
		nocache_headers();
		exit;
	}

	$etag     = '"' . md5( $feed['body'] ) . '"';
	$modified = gmdate( 'D, d M Y H:i:s', $feed['modified'] ) . ' GMT';

	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: inline; filename="calendar.ics"' );
	header( 'ETag: ' . $etag );
	header( 'Last-Modified: ' . $modified );
	header( 'Cache-Control: public, max-age=3600' );

	$sent_etag = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_IF_NONE_MATCH'] ) ) ) : '';
	$sent_time = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ? strtotime( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) ) : false;
	if ( axismundi_cal_ics_not_modified( $etag, (int) $feed['modified'], $sent_etag, $sent_time ) ) {
		status_header( 304 );
		exit;
	}

	status_header( 200 );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCalendar document, not HTML.
	echo $feed['body'];
	exit;
}
add_action( 'template_redirect', 'axismundi_cal_serve_ics' );
