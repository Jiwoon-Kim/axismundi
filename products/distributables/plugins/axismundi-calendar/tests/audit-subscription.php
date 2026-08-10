<?php
/**
 * Subscribed calendars: parsing, identity, absence and the URL guard (dev-only; dist-excluded).
 *
 * No network. The fixture is a captured document, because a test that fetches a real feed depends on
 * somebody else's server and somebody else's data, and starts failing on a day nothing here changed.
 * The live subscription is a manual check, not a gate.
 *
 * What is pinned:
 *
 * - Absence is not deletion. A feed with a retention window drops finished events, and reading that
 *   as cancellation tells a reader an event was called off when it merely happened.
 * - A rule this engine cannot expand is kept, not dropped. A subscribed feed is not ours to refuse,
 *   and refusing would silently remove somebody's events from a calendar they chose to watch.
 * - Subscribed entries are not this site's to publish: they stay out of the iCalendar export.
 * - The URL is attacker-influenced by nature, so the guard is asserted rather than assumed.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_sb_results   = array();
$ax_sb_calendars = array();
$ax_sb_posts     = array();

/** @param bool[] $results Results. */
function ax_sb_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A captured feed, in the shape a national holiday calendar actually takes. */
function ax_sb_feed( bool $second_pass = false ) : string {
	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//Example//Holidays//EN',
		'CALSCALE:GREGORIAN',
		'BEGIN:VEVENT',
		'UID:holiday-liberation@example.org',
		'DTSTAMP:20260101T000000Z',
		'DTSTART;VALUE=DATE:20260815',
		'DTEND;VALUE=DATE:20260816',
		'SUMMARY:Liberation Day\, observed',
		'END:VEVENT',
		'BEGIN:VEVENT',
		'UID:weekly-meetup@example.org',
		'DTSTAMP:20260101T000000Z',
		'DTSTART;TZID=Asia/Seoul:20260901T190000',
		'DTEND;TZID=Asia/Seoul:20260901T210000',
		'SUMMARY:Weekly meetup',
		'LOCATION:Somewhere long enough that the line has to be folded by the publisher to fit',
		'RRULE:FREQ=WEEKLY;BYDAY=TU',
		'END:VEVENT',
		'BEGIN:VEVENT',
		'UID:complex-rule@example.org',
		'DTSTAMP:20260101T000000Z',
		'DTSTART;TZID=Asia/Seoul:20260902T100000',
		'DTEND;TZID=Asia/Seoul:20260902T110000',
		'SUMMARY:Last weekday of the month',
		'RRULE:FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1',
		'END:VEVENT',
	);
	if ( ! $second_pass ) {
		// Present on the first fetch and gone on the second, as a rolling feed does once an event
		// has finished.
		$lines = array_merge(
			$lines,
			array(
				'BEGIN:VEVENT',
				'UID:finished-event@example.org',
				'DTSTAMP:20260101T000000Z',
				'DTSTART;TZID=Asia/Seoul:20260101T100000',
				'DTEND;TZID=Asia/Seoul:20260101T120000',
				'SUMMARY:Already finished',
				'END:VEVENT',
			)
		);
	}
	$lines[] = 'END:VCALENDAR';
	return implode( "\r\n", $lines ) . "\r\n";
}

try {
	// -- Parsing ------------------------------------------------------------------------------

	$ax_sb_entries = axismundi_cal_ics_parse( ax_sb_feed() );
	ax_sb_assert( $ax_sb_results, 'every component in the feed is read', 4 === count( $ax_sb_entries ) );

	$ax_sb_by_uid = array();
	foreach ( $ax_sb_entries as $entry ) {
		$ax_sb_by_uid[ $entry['ical_uid'] ] = $entry;
	}

	$ax_sb_holiday = $ax_sb_by_uid['holiday-liberation@example.org'] ?? array();
	ax_sb_assert(
		$ax_sb_results,
		'an all-day entry is a date, not midnight in some timezone, so it lands on its own day everywhere',
		1 === (int) $ax_sb_holiday['all_day'] && '2026-08-15 00:00:00' === $ax_sb_holiday['start_utc'] && '' === $ax_sb_holiday['timezone']
	);
	ax_sb_assert( $ax_sb_results, 'and its escaped comma is read back as a comma', 'Liberation Day, observed' === $ax_sb_holiday['summary'] );

	$ax_sb_weekly = $ax_sb_by_uid['weekly-meetup@example.org'] ?? array();
	ax_sb_assert(
		$ax_sb_results,
		'a zoned time is converted from the zone the publisher named',
		'2026-09-01 10:00:00' === $ax_sb_weekly['start_utc'] && '2026-09-01 19:00:00' === $ax_sb_weekly['start_local'] && 'Asia/Seoul' === $ax_sb_weekly['timezone']
	);
	ax_sb_assert( $ax_sb_results, 'a rule this engine can expand is marked as such', 1 === (int) $ax_sb_weekly['expansion_supported'] );

	$ax_sb_complex = $ax_sb_by_uid['complex-rule@example.org'] ?? array();
	ax_sb_assert(
		$ax_sb_results,
		'a rule this engine cannot expand keeps its text rather than being dropped, since a subscribed feed is not ours to refuse',
		'' !== $ax_sb_complex['rrule'] && 0 === (int) $ax_sb_complex['expansion_supported']
	);

	// Folding is the publisher's choice and has to survive the round trip.
	$ax_sb_folded = str_replace(
		'LOCATION:Somewhere long enough that the line has to be folded by the publisher to fit',
		"LOCATION:Somewhere long enough that the line has to be folded by the pu\r\n blisher to fit",
		ax_sb_feed()
	);
	$ax_sb_unfolded = axismundi_cal_ics_parse( $ax_sb_folded );
	$ax_sb_location = '';
	foreach ( $ax_sb_unfolded as $entry ) {
		if ( 'weekly-meetup@example.org' === $entry['ical_uid'] ) {
			$ax_sb_location = (string) $entry['location'];
		}
	}
	ax_sb_assert( $ax_sb_results, 'a folded line is rejoined, which is the difference between a long value and a broken one', 'Somewhere long enough that the line has to be folded by the publisher to fit' === $ax_sb_location );

	$ax_sb_nouid = axismundi_cal_ics_parse( "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nDTSTART:20260101T000000Z\r\nSUMMARY:No identity\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n" );
	ax_sb_assert( $ax_sb_results, 'a component with no UID is skipped, because it could never be recognised again', array() === $ax_sb_nouid );

	$ax_sb_partial = axismundi_cal_ics_parse( "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:broken@example.org\r\nDTSTART:not-a-date\r\nEND:VEVENT\r\nBEGIN:VEVENT\r\nUID:fine@example.org\r\nDTSTART:20260101T000000Z\r\nSUMMARY:Fine\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n" );
	ax_sb_assert( $ax_sb_results, 'one unreadable component costs that component and not the rest of the feed', 1 === count( $ax_sb_partial ) && 'fine@example.org' === $ax_sb_partial[0]['ical_uid'] );

	// -- The URL guard -----------------------------------------------------------------------------

	$ax_sb_cal = axismundi_cal_calendar_save( array( 'name' => 'Subscribed', 'slug' => 'ax-sb-cal' ) );
	$ax_sb_calendars[] = (int) $ax_sb_cal;

	foreach ( array( 'http://127.0.0.1/cal.ics', 'http://localhost/cal.ics', 'http://169.254.169.254/latest/meta-data/', 'http://10.0.0.5/cal.ics' ) as $ax_sb_bad ) {
		ax_sb_assert(
			$ax_sb_results,
			sprintf( 'a subscription to %s is refused, since this server would be the one fetching it', $ax_sb_bad ),
			is_wp_error( axismundi_cal_add_source( (int) $ax_sb_cal, $ax_sb_bad ) )
		);
	}
	ax_sb_assert( $ax_sb_results, 'and so is a non-HTTP scheme', is_wp_error( axismundi_cal_add_source( (int) $ax_sb_cal, 'file:///etc/passwd' ) ) );
	ax_sb_assert(
		$ax_sb_results,
		'a name that does not resolve is refused rather than allowed through unchecked',
		is_wp_error( axismundi_cal_validate_source_url( 'https://no-such-host.invalid/cal.ics' ) )
	);
	/*
	 * A literal public address, so the accept path is deterministic. Using a hostname would make the
	 * assertion depend on this machine having DNS, and a test that passes or fails on network weather
	 * is not evidence about the code.
	 */
	$ax_sb_public = 'https://93.184.216.34/calendar.ics';
	ax_sb_assert( $ax_sb_results, 'while an ordinary public address is accepted', is_int( axismundi_cal_add_source( (int) $ax_sb_cal, $ax_sb_public ) ) );

	$ax_sb_source = (int) axismundi_cal_add_source( (int) $ax_sb_cal, $ax_sb_public );
	ax_sb_assert( $ax_sb_results, 'adding the same address twice is one subscription, not two', 1 === count( axismundi_cal_sources_for_calendar( (int) $ax_sb_cal ) ) );
	ax_sb_assert( $ax_sb_results, 'a subscription records that this site is not its authority', 'remote' === (string) axismundi_cal_source_get( $ax_sb_source )['authority'] );

	// -- Storing a snapshot, and what happens when an entry leaves it ---------------------------------

	$ax_sb_now   = current_time( 'mysql', true );
	$ax_sb_table = axismundi_cal_entries_table();
	foreach ( axismundi_cal_ics_parse( ax_sb_feed() ) as $entry ) {
		$hash = hash( 'sha256', $entry['ical_uid'] . "\n" . $entry['recurrence_id'] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture standing in for a fetch.
		$wpdb->replace( $ax_sb_table, array_merge( $entry, array( 'source_id' => $ax_sb_source, 'entry_hash' => $hash, 'presence' => 'present', 'last_seen_at' => $ax_sb_now, 'created_at' => $ax_sb_now, 'updated_at' => $ax_sb_now ) ) );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture.
	$ax_sb_stored = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ax_sb_table} WHERE source_id = %d", $ax_sb_source ) );
	ax_sb_assert( $ax_sb_results, 'the snapshot is cached', 4 === $ax_sb_stored );

	// The second fetch, which no longer carries the finished event.
	$ax_sb_seen = array();
	foreach ( axismundi_cal_ics_parse( ax_sb_feed( true ) ) as $entry ) {
		$ax_sb_seen[] = hash( 'sha256', $entry['ical_uid'] . "\n" . $entry['recurrence_id'] );
	}
	$ax_sb_ph = implode( ',', array_fill( 0, count( $ax_sb_seen ), '%s' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture standing in for a fetch.
	$wpdb->query( $wpdb->prepare( "UPDATE {$ax_sb_table} SET presence = 'missing' WHERE source_id = %d AND presence = 'present' AND entry_hash NOT IN ({$ax_sb_ph})", array_merge( array( $ax_sb_source ), $ax_sb_seen ) ) );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture.
	$ax_sb_gone = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$ax_sb_table} WHERE source_id = %d AND ical_uid = %s", $ax_sb_source, 'finished-event@example.org' ), ARRAY_A );
	ax_sb_assert( $ax_sb_results, 'an entry that left the feed is kept rather than deleted', is_array( $ax_sb_gone ) );
	ax_sb_assert(
		$ax_sb_results,
		'and recorded as missing rather than cancelled, because a retention window is not a cancellation',
		'missing' === (string) $ax_sb_gone['presence'] && 'confirmed' === (string) $ax_sb_gone['status']
	);
	ax_sb_assert( $ax_sb_results, 'while the entries still in the feed stay present', 3 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ax_sb_table} WHERE source_id = %d AND presence = 'present'", $ax_sb_source ) ) );

	$ax_sb_visible = axismundi_cal_subscribed_entries( (int) $ax_sb_cal, '2026-08-01 00:00:00', '2026-09-30 00:00:00' );
	$ax_sb_uids    = array_map( static fn( array $r ) : string => (string) $r['ical_uid'], $ax_sb_visible );
	ax_sb_assert( $ax_sb_results, 'a missing entry is not shown', ! in_array( 'finished-event@example.org', $ax_sb_uids, true ) );
	ax_sb_assert( $ax_sb_results, 'while a present one is', in_array( 'holiday-liberation@example.org', $ax_sb_uids, true ) );

	// -- Subscribed entries are not ours to publish ------------------------------------------------

	$ax_sb_feed_body = axismundi_cal_site_feed( (int) $ax_sb_cal, 'Subscribed' )['body'];
	ax_sb_assert(
		$ax_sb_results,
		'a subscribed entry is absent from this site\'s own iCalendar export, since this site is not its authority',
		! str_contains( $ax_sb_feed_body, 'Liberation Day' ) && ! str_contains( $ax_sb_feed_body, 'holiday-liberation@example.org' )
	);
	ax_sb_assert(
		$ax_sb_results,
		'and creates no local Event, so nothing exists for ActivityPub to re-publish',
		0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s", AXISMUNDI_CAL_EVENT_POST_TYPE, 'Liberation Day, observed' ) )
	);

	// -- Removing a subscription --------------------------------------------------------------------

	axismundi_cal_remove_source( $ax_sb_source );
	ax_sb_assert( $ax_sb_results, 'removing a subscription drops its cache', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ax_sb_table} WHERE source_id = %d", $ax_sb_source ) ) );
	ax_sb_assert( $ax_sb_results, 'and the calendar itself survives', null !== axismundi_cal_calendar_get( (int) $ax_sb_cal ) );
} finally {
	foreach ( array_unique( $ax_sb_calendars ) as $ax_sb_calendar_id ) {
		foreach ( axismundi_cal_sources_for_calendar( (int) $ax_sb_calendar_id ) as $ax_sb_row ) {
			axismundi_cal_remove_source( (int) $ax_sb_row['id'] );
		}
		axismundi_cal_calendar_delete( (int) $ax_sb_calendar_id );
	}
	foreach ( array_unique( $ax_sb_posts ) as $ax_sb_post_id ) {
		wp_delete_post( (int) $ax_sb_post_id, true );
	}
}

$ax_sb_failures = count( array_filter( $ax_sb_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_sb_results ), $ax_sb_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_sb_failures > 0 ? 1 : 0 );
}
exit( $ax_sb_failures > 0 ? 1 : 0 );
