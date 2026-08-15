<?php
/**
 * A Calendar as a JSCalendar `Group` (RFC 8984 §2.3).
 *
 * The third representation of one Calendar, beside the ActivityStreams collection and the iCalendar
 * feed, and the one an address book links to. JSCalendar already has the envelope: a `Group` holds
 * calendar objects in `entries` and names where it came from in `source`, so nothing here invents a
 * wrapper for a job the standard already does.
 *
 * What it answers with is an agenda, not an archive. A public calendar accumulates years of finished
 * events, and sending all of them to anybody who opens the link is a bill nobody agreed to pay --
 * so the default is what is happening now and what is coming. That is a deliberate narrowing and it
 * has a consequence worth stating: this document cannot be somebody's only synchronisation source,
 * because a client that already holds a past event will never be told it changed. The ICS feed
 * remains the subscription format, and a real sync API is a later, different thing.
 *
 * The gate is `event_listable()`, as everywhere else, and the items are the Events *filed* here --
 * the same narrowing the ActivityStreams collection makes, for the same reason.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** The media type, saying which JSCalendar object is inside it. */
const AXISMUNDI_CAL_JSCALENDAR_GROUP_MEDIA_TYPE = 'application/jscalendar+json;type=group';

/**
 * How far ahead a recurring series is looked at when deciding whether it is still running.
 *
 * A rule with no end never stops producing occurrences, so the question "does this series have a
 * future" has to be asked over a window rather than over eternity. A year and a bit answers it for
 * anything a person would recognise as current.
 */
const AXISMUNDI_CAL_AGENDA_HORIZON_DAYS = 400;

/**
 * Whether an Event is happening now or still to come.
 *
 * In progress counts: something that started an hour ago and runs until tonight is on today's
 * agenda, and dropping it the moment it begins would be the one time a calendar is most looked at.
 *
 * @param array<string,mixed> $schedule Schedule row.
 * @param string              $now_utc  Reference instant, `Y-m-d H:i:s` UTC.
 * @return bool
 */
function axismundi_cal_schedule_is_current( array $schedule, string $now_utc ) : bool {
	$until = gmdate( 'Y-m-d H:i:s', strtotime( $now_utc . ' +' . AXISMUNDI_CAL_AGENDA_HORIZON_DAYS . ' days' ) );
	foreach ( axismundi_cal_expand( $schedule, $now_utc, $until ) as $occurrence ) {
		// `expand()` already drops what ended before the window opened, so anything it returns either
		// has not finished or has not started.
		if ( 'cancelled' !== (string) ( $occurrence['status'] ?? '' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * One Calendar as a JSCalendar Group.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_cal_jscalendar_group( array $calendar ) {
	global $wpdb;
	$id = (int) ( $calendar['id'] ?? 0 );
	if ( $id <= 0 || ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_cal_group_missing', __( 'That calendar does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	$now       = current_time( 'mysql', true );
	$schedules = axismundi_cal_schedules_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed lookup in this plugin's own table.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$schedules} WHERE calendar_id = %d ORDER BY dtstart_local ASC", $id ), ARRAY_A );

	$entries = array();
	foreach ( $rows as $schedule ) {
		$post = get_post( (int) $schedule['event_post_id'] );
		if ( ! $post instanceof WP_Post || ! axismundi_cal_event_listable( $post ) ) {
			continue;
		}
		if ( ! axismundi_cal_schedule_is_current( $schedule, $now ) ) {
			continue;
		}
		$event = axismundi_cal_jscalendar_event( $post );
		if ( ! is_wp_error( $event ) ) {
			$entries[] = $event;
		}
	}

	$group = array(
		'@type'   => 'Group',
		'uid'     => 'urn:uuid:' . (string) ( $calendar['uuid'] ?? '' ),
		'updated' => gmdate( 'Y-m-d\TH:i:s\Z' ),
		'title'   => axismundi_cal_calendar_display_name( $calendar ),
		'entries' => $entries,
	);
	// Where this came from, which is the Calendar itself rather than this rendering of it.
	$source = axismundi_cal_collection_uri_for( $calendar );
	if ( '' !== $source ) {
		$group['source'] = $source;
	}
	if ( '' !== trim( (string) ( $calendar['description'] ?? '' ) ) ) {
		$group['description'] = wp_strip_all_tags( (string) $calendar['description'] );
	}
	return $group;
}

/** @return array<string,string> */
function axismundi_cal_jscalendar_group_rewrite_rules() : array {
	return array(
		'^calendar/c/([0-9a-fA-F-]{36})\.json$' => 'index.php?ax_cal_group_uuid=$matches[1]',
		'^calendar/([^/]+)\.json$'              => 'index.php?ax_cal_group_slug=$matches[1]',
	);
}

/** @return void */
function axismundi_cal_register_jscalendar_group_routes() : void {
	foreach ( axismundi_cal_jscalendar_group_rewrite_rules() as $regex => $query ) {
		add_rewrite_rule( $regex, $query, 'top' );
	}
}
add_action( 'init', 'axismundi_cal_register_jscalendar_group_routes', 7 );

/**
 * @param string[] $vars Query vars.
 * @return string[]
 */
function axismundi_cal_jscalendar_group_query_vars( array $vars ) : array {
	$vars[] = 'ax_cal_group_slug';
	$vars[] = 'ax_cal_group_uuid';
	return $vars;
}
add_filter( 'query_vars', 'axismundi_cal_jscalendar_group_query_vars' );

/** @return string The JSCalendar address of one Calendar, or '' when it has none. */
function axismundi_cal_jscalendar_group_url( array $calendar ) : string {
	$canonical = axismundi_cal_collection_uri_for( $calendar );
	return '' === $canonical ? '' : $canonical . '.json';
}

/**
 * Serve one Calendar as a JSCalendar Group.
 *
 * Public calendars only, by the same rule the collection follows: this is a document a stranger
 * fetches, and holding shared access is not the calendar being published.
 *
 * @return void
 */
function axismundi_cal_serve_jscalendar_group() : void {
	$slug = (string) get_query_var( 'ax_cal_group_slug' );
	$uuid = (string) get_query_var( 'ax_cal_group_uuid' );
	if ( '' === $slug && '' === $uuid ) {
		return;
	}
	$calendar = '' !== $uuid ? axismundi_cal_calendar_by_uuid( $uuid ) : axismundi_cal_calendar_by_slug( $slug );
	$group    = is_array( $calendar ) && axismundi_cal_collection_visible( new Axismundi_Cal_Collection( $calendar ) )
		? axismundi_cal_jscalendar_group( $calendar )
		: new WP_Error( 'ax_cal_group_missing', 'not_found' );
	if ( is_wp_error( $group ) ) {
		status_header( 404 );
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo wp_json_encode( array( 'error' => 'not_found' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response.
		exit;
	}
	status_header( 200 );
	header( 'Content-Type: ' . AXISMUNDI_CAL_JSCALENDAR_GROUP_MEDIA_TYPE . '; charset=' . get_option( 'blog_charset' ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Access-Control-Allow-Origin: *' );
	echo wp_json_encode( $group ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response.
	exit;
}
add_action( 'template_redirect', 'axismundi_cal_serve_jscalendar_group', 4 );

/**
 * Put an Actor's own public calendar on their contact card.
 *
 * Contributed from here rather than assembled in Actors: the calendar is this plugin's fact, and an
 * identity registry that knew how to address one would be holding a second copy of that knowledge.
 *
 * Only a published calendar. A card is a public document, and naming a private address on one turns
 * a contact card into directions around the sharing rules.
 *
 * @param array<string,mixed> $card  Card so far.
 * @param Axismundi_Actor     $actor Actor.
 * @return array<string,mixed>
 */
function axismundi_cal_jscontact_calendars( array $card, Axismundi_Actor $actor ) : array {
	if ( ! $actor->is_local() ) {
		return $card;
	}
	$calendar = axismundi_cal_primary_calendar( (string) $actor->get_uri() );
	if ( ! is_array( $calendar ) || ! axismundi_cal_collection_visible( new Axismundi_Cal_Collection( $calendar ) ) ) {
		return $card;
	}
	$uri = axismundi_cal_jscalendar_group_url( $calendar );
	if ( '' === $uri ) {
		return $card;
	}
	$card['calendars'] = array(
		'primary' => array(
			'@type'     => 'Calendar',
			'kind'      => 'calendar',
			'uri'       => $uri,
			'mediaType' => AXISMUNDI_CAL_JSCALENDAR_GROUP_MEDIA_TYPE,
			'pref'      => 1,
		),
	);
	return $card;
}
add_filter( 'axismundi_actors_jscontact_card', 'axismundi_cal_jscontact_calendars', 10, 2 );
