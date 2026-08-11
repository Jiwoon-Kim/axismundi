<?php
/**
 * iCalendar serialization (dev-only; dist-excluded).
 *
 * The failures pinned here are the ones that only appear for particular content or particular
 * subscribers: a title containing a comma, a calendar read in a zone that observes DST, a feed
 * fetched by a client that sends `If-None-Match`, an event whose title is long or non-Latin.
 * Everything else about the document looks correct while any of them is broken.
 *
 * The privacy rule is asserted rather than assumed: `ATTENDEE` and `ORGANIZER` carry addresses, and
 * a subscription feed is a broadcast to anyone with the URL.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_ics_results = array();
$ax_ics_posts   = array();
$ax_ics_calendars = array();

/** @param bool[] $results Results. */
function ax_ics_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Publish an Event through the real writers. */
function ax_ics_event( array &$posts, string $title, array $fields ) : int {
	$id      = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_author' => 1, 'post_title' => $title ) );
	$posts[] = $id;
	axismundi_cal_event_save( $id, array_merge( array( 'calendar_id' => (int) $GLOBALS['ax_ics_calendar'] ), $fields ) );
	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;
	return $id;
}

try {
	$ax_ics_site      = axismundi_actors_get_site_actor();
	$ax_ics_authority = $ax_ics_site instanceof Axismundi_Actor ? $ax_ics_site->get_uri() : '';
	$ax_ics_calendar = axismundi_cal_calendar_save( array( 'name' => 'ICS calendar', 'slug' => 'audit-ics', 'timezone' => 'America/New_York', 'owner_actor_uri' => $ax_ics_authority ) );
	$GLOBALS['ax_ics_calendar'] = (int) $ax_ics_calendar;
	$ax_ics_calendars[] = (int) $ax_ics_calendar;
	// -- Escaping and folding, which fail only for particular text ---------------------------

	ax_ics_assert( $ax_ics_results, 'commas and semicolons are escaped, since both separate values in iCalendar', 'a\\, b\\; c' === axismundi_cal_ics_escape( 'a, b; c' ) );
	ax_ics_assert( $ax_ics_results, 'a backslash is escaped first, so it does not escape the escapes', 'a\\\\b' === axismundi_cal_ics_escape( 'a\\b' ) );
	ax_ics_assert( $ax_ics_results, 'newlines become the literal escape rather than breaking the line structure', 'a\\nb' === axismundi_cal_ics_escape( "a\nb" ) );

	$ax_ics_long   = 'SUMMARY:' . str_repeat( 'abcdefghij', 20 );
	$ax_ics_folded = axismundi_cal_ics_fold( $ax_ics_long );
	$ax_ics_lines  = explode( "\r\n", $ax_ics_folded );
	$ax_ics_over   = array_filter( $ax_ics_lines, static fn( string $l ) : bool => strlen( $l ) > 75 );
	ax_ics_assert( $ax_ics_results, 'a long line is folded to 75 octets', count( $ax_ics_lines ) > 1 && array() === $ax_ics_over );
	ax_ics_assert( $ax_ics_results, 'and every continuation begins with a space, which is what marks it as one', count( array_filter( array_slice( $ax_ics_lines, 1 ), static fn( string $l ) : bool => str_starts_with( $l, ' ' ) ) ) === count( $ax_ics_lines ) - 1 );
	ax_ics_assert( $ax_ics_results, 'unfolding a folded line returns the original, so nothing was lost or gained', $ax_ics_long === str_replace( "\r\n ", '', $ax_ics_folded ) );

	// Multi-byte is the case a naive octet split corrupts: the limit is on octets, but a character
	// cut in half is a character no parser can put back together.
	$ax_ics_korean = 'SUMMARY:' . str_repeat( '한글제목', 30 );
	$ax_ics_kfold  = axismundi_cal_ics_fold( $ax_ics_korean );
	ax_ics_assert( $ax_ics_results, 'a multi-byte line folds without splitting a character', $ax_ics_korean === str_replace( "\r\n ", '', $ax_ics_kfold ) );
	ax_ics_assert( $ax_ics_results, 'and its folded lines still respect the octet limit', array() === array_filter( explode( "\r\n", $ax_ics_kfold ), static fn( string $l ) : bool => strlen( $l ) > 75 ) );

	// -- A recurring Event is a rule, not an expansion ------------------------------------------

	$ax_ics_series = ax_ics_event(
		$ax_ics_posts,
		'Weekly, with a comma',
		array( 'timezone' => 'America/New_York', 'starts_at' => '2026-10-24 19:00:00', 'ends_at' => '2026-10-24 21:00:00', 'rrule' => 'FREQ=WEEKLY;BYDAY=SA' )
	);
	$ax_ics_schedule = axismundi_cal_schedule_for_event( $ax_ics_series );
	$ax_ics_now      = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture standing in for the editor.
	$wpdb->delete( axismundi_cal_occurrences_table(), array( 'schedule_id' => (int) $ax_ics_schedule['id'], 'recurrence_id' => '20261031T190000' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture standing in for the editor.
	$wpdb->insert(
		axismundi_cal_occurrences_table(),
		array(
			'schedule_id' => (int) $ax_ics_schedule['id'], 'recurrence_id' => '20261031T190000',
			'start_utc' => '2026-10-31 23:00:00', 'end_utc' => '2026-11-01 01:00:00',
			'start_local' => '2026-10-31 19:00:00', 'end_local' => '2026-10-31 21:00:00',
			'status' => 'cancelled', 'origin' => 'override', 'location_place_id' => null,
			'location_text' => '', 'override_json' => '', 'created_at' => $ax_ics_now, 'updated_at' => $ax_ics_now,
		)
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture standing in for the editor.
	$wpdb->delete( axismundi_cal_occurrences_table(), array( 'schedule_id' => (int) $ax_ics_schedule['id'], 'recurrence_id' => '20261107T190000' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture standing in for the editor.
	$wpdb->insert(
		axismundi_cal_occurrences_table(),
		array(
			'schedule_id' => (int) $ax_ics_schedule['id'], 'recurrence_id' => '20261107T190000',
			'start_utc' => '2026-11-07 19:00:00', 'end_utc' => '2026-11-07 21:00:00',
			'start_local' => '2026-11-07 14:00:00', 'end_local' => '2026-11-07 16:00:00',
			'status' => 'scheduled', 'origin' => 'override', 'location_place_id' => null,
			'location_text' => 'The annexe', 'override_json' => '', 'created_at' => $ax_ics_now, 'updated_at' => $ax_ics_now,
		)
	);

	$ax_ics_schedule = axismundi_cal_schedule_for_event( $ax_ics_series );
	$ax_ics_lines    = axismundi_cal_ics_vevent( $ax_ics_schedule, get_post( $ax_ics_series ) );
	$ax_ics_body     = implode( "\n", $ax_ics_lines );

	ax_ics_assert( $ax_ics_results, 'the series is one component carrying its rule, not one per occurrence', 1 === substr_count( $ax_ics_body, 'RRULE:' ) );
	ax_ics_assert( $ax_ics_results, 'and the rule is the normalized one that was stored', str_contains( $ax_ics_body, 'RRULE:FREQ=WEEKLY;BYDAY=SA' ) );
	ax_ics_assert( $ax_ics_results, 'a cancelled instance becomes EXDATE, generated from the cancellation rather than stored twice', str_contains( $ax_ics_body, 'EXDATE;TZID=America/New_York:20261031T190000' ) );
	ax_ics_assert( $ax_ics_results, 'a moved instance is its own component identified by RECURRENCE-ID', str_contains( $ax_ics_body, 'RECURRENCE-ID;TZID=America/New_York:20261107T190000' ) );
	ax_ics_assert( $ax_ics_results, 'and that component keeps the series UID, so clients update the instance instead of adding an event', 2 === substr_count( $ax_ics_body, 'UID:' . $ax_ics_schedule['ical_uid'] ) );
	ax_ics_assert( $ax_ics_results, 'the moved instance carries its own venue', str_contains( $ax_ics_body, 'LOCATION:The annexe' ) );
	ax_ics_assert( $ax_ics_results, 'the title is escaped in the output, not only by the escaper in isolation', str_contains( $ax_ics_body, 'SUMMARY:Weekly\\, with a comma' ) );

	// -- Privacy ------------------------------------------------------------------------------

	ax_ics_assert(
		$ax_ics_results,
		'no attendee, organizer or mail address appears, because a subscription feed is a broadcast',
		! str_contains( $ax_ics_body, 'ATTENDEE' ) && ! str_contains( $ax_ics_body, 'ORGANIZER' ) && ! str_contains( strtolower( $ax_ics_body ), 'mailto:' )
	);

	// -- Stable identity ------------------------------------------------------------------------

	$ax_ics_uid = (string) $ax_ics_schedule['ical_uid'];
	wp_update_post( array( 'ID' => $ax_ics_series, 'post_title' => 'Renamed entirely', 'post_name' => 'renamed-entirely' ) );
	$ax_ics_after = axismundi_cal_schedule_for_event( $ax_ics_series );
	ax_ics_assert( $ax_ics_results, 'the UID survives a retitle and a new slug, so subscribers update rather than duplicate', $ax_ics_uid === (string) $ax_ics_after['ical_uid'] );

	$ax_ics_seq = (int) $ax_ics_after['sequence'];
	axismundi_cal_event_save( $ax_ics_series, array( 'starts_at' => '2026-10-24 20:00:00', 'ends_at' => '2026-10-24 22:00:00' ) );
	$ax_ics_moved = axismundi_cal_schedule_for_event( $ax_ics_series );
	ax_ics_assert( $ax_ics_results, 'and SEQUENCE advances when the time moves, which is what tells a client to re-sync', (int) $ax_ics_moved['sequence'] > $ax_ics_seq );

	// -- VTIMEZONE from real transitions ---------------------------------------------------------

	$ax_ics_tz = axismundi_cal_ics_vtimezone( 'America/New_York', strtotime( '2026-01-01' ), strtotime( '2027-01-01' ) );
	$ax_ics_tzbody = implode( "\n", $ax_ics_tz );
	ax_ics_assert( $ax_ics_results, 'a DST zone produces both a standard and a daylight observance', str_contains( $ax_ics_tzbody, 'BEGIN:STANDARD' ) && str_contains( $ax_ics_tzbody, 'BEGIN:DAYLIGHT' ) );
	ax_ics_assert( $ax_ics_results, 'with the offsets the IANA database actually gives, not a guess', str_contains( $ax_ics_tzbody, 'TZOFFSETTO:-0400' ) && str_contains( $ax_ics_tzbody, 'TZOFFSETTO:-0500' ) );
	$ax_ics_utc_tz = axismundi_cal_ics_vtimezone( 'UTC', strtotime( '2026-01-01' ), strtotime( '2027-01-01' ) );
	ax_ics_assert( $ax_ics_results, 'a zone with no transitions in the window produces no daylight observance', ! str_contains( implode( "\n", $ax_ics_utc_tz ), 'BEGIN:DAYLIGHT' ) );

	// -- The document -----------------------------------------------------------------------------

	$ax_ics_doc = axismundi_cal_ics_document( $ax_ics_lines, array( 'America/New_York' ), strtotime( '2026-01-01' ), strtotime( '2027-01-01' ), 'Test' );
	ax_ics_assert( $ax_ics_results, 'the document is wrapped and declares its version', str_starts_with( $ax_ics_doc, 'BEGIN:VCALENDAR' ) && str_contains( $ax_ics_doc, 'VERSION:2.0' ) );
	ax_ics_assert( $ax_ics_results, 'every line ends CRLF, which RFC 5545 requires and some clients enforce', ! preg_match( '/(?<!\r)\n/', $ax_ics_doc ) );
	ax_ics_assert( $ax_ics_results, 'the zone the events reference is defined in the document', str_contains( $ax_ics_doc, 'TZID:America/New_York' ) );
	ax_ics_assert( $ax_ics_results, 'and it ends where it should', str_ends_with( $ax_ics_doc, 'END:VCALENDAR' . "\r\n" ) );

	// -- The rolling window ------------------------------------------------------------------------

	$ax_ics_old = ax_ics_event(
		$ax_ics_posts,
		'Long finished',
		array( 'timezone' => 'UTC', 'starts_at' => '2019-01-05 10:00:00', 'ends_at' => '2019-01-05 12:00:00' )
	);
	$ax_ics_feed = axismundi_cal_site_feed();
	ax_ics_assert( $ax_ics_results, 'an Event that finished years ago is not carried in the rolling subscription feed', ! str_contains( $ax_ics_feed['body'], 'Long finished' ) );

	$ax_ics_single = axismundi_cal_event_feed( get_post( $ax_ics_old ) );
	ax_ics_assert(
		$ax_ics_results,
		'but its own .ics still serves it, because fetching one Event is a deliberate act rather than a subscription',
		is_array( $ax_ics_single ) && str_contains( $ax_ics_single['body'], 'Long finished' )
	);

	// -- Conditional GET ---------------------------------------------------------------------------

	$ax_ics_etag = '"abc"';
	ax_ics_assert( $ax_ics_results, 'a matching entity tag is not modified', true === axismundi_cal_ics_not_modified( $ax_ics_etag, 1000, '"abc"', 2000 ) );
	/*
	 * The case that was wrong. The two validators answer different questions: the entity tag
	 * describes this document, `Last-Modified` describes the rows it was built from, and the rolling
	 * window moves them apart without any edit. Satisfying either one meant a client sending both
	 * could be told nothing had changed on the strength of the weaker answer -- exactly when it had.
	 */
	ax_ics_assert(
		$ax_ics_results,
		'a stale entity tag is modified even when If-Modified-Since would have been satisfied',
		false === axismundi_cal_ics_not_modified( $ax_ics_etag, 1000, '"stale"', 2000 )
	);
	ax_ics_assert( $ax_ics_results, 'with no entity tag, If-Modified-Since decides', true === axismundi_cal_ics_not_modified( $ax_ics_etag, 1000, '', 2000 ) );
	ax_ics_assert( $ax_ics_results, 'and an older If-Modified-Since is modified', false === axismundi_cal_ics_not_modified( $ax_ics_etag, 1000, '', 500 ) );
	ax_ics_assert( $ax_ics_results, 'a request with neither validator is always modified', false === axismundi_cal_ics_not_modified( $ax_ics_etag, 1000, '', false ) );

	// The window moving is itself a change, so a client with only If-Modified-Since has to see it.
	$ax_ics_dropped = ax_ics_event(
		$ax_ics_posts,
		'Just fell out of the window',
		array(
			'timezone'  => 'UTC',
			'starts_at' => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( AXISMUNDI_CAL_FEED_PAST_MONTHS * 31 + 2 ) . ' days' ) ),
			'ends_at'   => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( AXISMUNDI_CAL_FEED_PAST_MONTHS * 31 + 2 ) . ' days +2 hours' ) ),
		)
	);
	$ax_ics_after_drop = axismundi_cal_site_feed();
	ax_ics_assert( $ax_ics_results, 'an Event past the cutoff is out of the feed', ! str_contains( $ax_ics_after_drop['body'], 'Just fell out of the window' ) );
	ax_ics_assert(
		$ax_ics_results,
		'and the feed reports a modification time that accounts for the window moving, not only for row edits',
		$ax_ics_after_drop['modified'] >= strtotime( '-40 days' )
	);
	unset( $ax_ics_dropped );
} finally {
	foreach ( array_unique( $ax_ics_posts ) as $ax_ics_post_id ) {
		$ax_ics_row = axismundi_cal_schedule_for_event( (int) $ax_ics_post_id );
		if ( is_array( $ax_ics_row ) ) {
			$wpdb->delete( axismundi_cal_occurrences_table(), array( 'schedule_id' => (int) $ax_ics_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( axismundi_cal_schedules_table(), array( 'id' => (int) $ax_ics_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_cal_events_table(), array( 'post_id' => (int) $ax_ics_post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_delete_post( (int) $ax_ics_post_id, true );
	}
	foreach ( array_unique( $ax_ics_calendars ) as $ax_ics_calendar_id ) {
		axismundi_cal_calendar_delete( (int) $ax_ics_calendar_id );
	}
}

$ax_ics_failures = count( array_filter( $ax_ics_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ics_results ), $ax_ics_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ics_failures > 0 ? 1 : 0 );
}
exit( $ax_ics_failures > 0 ? 1 : 0 );
