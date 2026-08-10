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
function axismundi_cal_feed_schedules( string $cutoff_utc ) : array {
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
function axismundi_cal_site_feed() : array {
	$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-' . AXISMUNDI_CAL_FEED_PAST_MONTHS . ' months' ) );
	$rows   = axismundi_cal_feed_schedules( $cutoff );

	$components = array();
	$tzids      = array();
	$modified   = 0;
	foreach ( $rows as $row ) {
		$components = array_merge( $components, axismundi_cal_ics_vevent( $row['schedule'], $row['post'] ) );
		$tzids[]    = (string) $row['schedule']['timezone'];
		$modified   = max( $modified, (int) strtotime( (string) $row['schedule']['updated_at'] . ' UTC' ) );
	}

	return array(
		'body'     => axismundi_cal_ics_document(
			$components,
			$tzids,
			(int) strtotime( $cutoff . ' UTC' ),
			(int) strtotime( '+2 years' ),
			(string) get_bloginfo( 'name' )
		),
		'modified' => $modified > 0 ? $modified : time(),
	);
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
	if ( $sent_etag === $etag || ( false !== $sent_time && $sent_time >= $feed['modified'] ) ) {
		status_header( 304 );
		exit;
	}

	status_header( 200 );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCalendar document, not HTML.
	echo $feed['body'];
	exit;
}
add_action( 'template_redirect', 'axismundi_cal_serve_ics' );
