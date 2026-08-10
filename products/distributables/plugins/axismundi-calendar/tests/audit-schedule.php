<?php
/**
 * The envelope-to-Schedule cutover and the occurrence cache (dev-only; dist-excluded).
 *
 * The property under test is single authority. `wp_ax_events` and `wp_ax_cal_schedules` must not
 * both accept writes for when an Event happens: two writers for one fact drift, and the drift shows
 * up much later as a calendar page disagreeing with the Object the site already federated. So the
 * legacy time columns are not merely unused here -- they are proved dead, by corrupting them and
 * showing nothing reads them.
 *
 * The other property is that the cache is disposable. Rule-derived rows may be thrown away and
 * rebuilt; a cancellation, a moved instance or a changed venue may not, because those exist in no
 * other place. A rebuild that loses them is indistinguishable from a rebuild that worked.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_sc_results = array();
$ax_sc_posts   = array();

/** @param bool[] $results Results. */
function ax_sc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Cached rows for a schedule, as comparable tuples. */
function ax_sc_rows( int $schedule_id ) : array {
	global $wpdb;
	$table = axismundi_cal_occurrences_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT recurrence_id, start_utc, end_utc, status, origin, location_text FROM {$table} WHERE schedule_id = %d ORDER BY start_utc ASC", $schedule_id ), ARRAY_A );
	return $rows;
}

try {
	$ax_sc_events = axismundi_cal_events_table();

	// -- 1. A legacy envelope converts to exactly one Schedule ---------------------------------

	$ax_sc_legacy = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_author' => 1, 'post_title' => 'Legacy conversion fixture' )
	);
	$ax_sc_posts[] = $ax_sc_legacy;
	$ax_sc_now     = current_time( 'mysql', true );
	// Written straight to the old table, as a site upgrading from before the Schedule existed.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture.
	$wpdb->replace(
		$ax_sc_events,
		array(
			'post_id' => $ax_sc_legacy, 'starts_at' => '2026-08-01 19:00:00', 'starts_at_gmt' => '2026-08-01 10:00:00',
			'ends_at' => '2026-08-01 21:00:00', 'ends_at_gmt' => '2026-08-01 12:00:00', 'timezone' => 'Asia/Seoul',
			'display_end_time' => 1, 'previous_starts_at_gmt' => null, 'event_status' => 'EventScheduled',
			'join_mode' => 'free', 'external_participation_url' => '', 'maximum_attendee_capacity' => null,
			'created_at' => $ax_sc_now, 'updated_at' => $ax_sc_now,
		)
	);

	$ax_sc_created = axismundi_cal_convert_legacy_envelopes();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture.
	$ax_sc_count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . axismundi_cal_schedules_table() . ' WHERE event_post_id = %d', $ax_sc_legacy ) );
	ax_sc_assert( $ax_sc_results, 'a legacy envelope converts to exactly one Schedule', $ax_sc_created >= 1 && 1 === $ax_sc_count );

	$ax_sc_schedule = axismundi_cal_schedule_for_event( $ax_sc_legacy );
	ax_sc_assert(
		$ax_sc_results,
		'and it carries the times and zone it had, not the site zone',
		is_array( $ax_sc_schedule ) && '2026-08-01 19:00:00' === $ax_sc_schedule['dtstart_local'] && 'Asia/Seoul' === $ax_sc_schedule['timezone']
	);
	ax_sc_assert( $ax_sc_results, 'a legacy single Event converts to a schedule with no rule, because recurrence could not be authored before', '' === (string) $ax_sc_schedule['rrule'] );

	axismundi_cal_convert_legacy_envelopes();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture.
	$ax_sc_again = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . axismundi_cal_schedules_table() . ' WHERE event_post_id = %d', $ax_sc_legacy ) );
	ax_sc_assert( $ax_sc_results, 'converting twice does not produce a second Schedule, so a rerun upgrade is safe', 1 === $ax_sc_again );

	// -- 2. One writer: the legacy time columns are dead ---------------------------------------

	$ax_sc_edit = axismundi_cal_event_save( $ax_sc_legacy, array( 'starts_at' => '2026-08-02 18:00:00', 'ends_at' => '2026-08-02 20:00:00' ) );
	ax_sc_assert( $ax_sc_results, 'editing an Event succeeds through the Schedule writer', true === $ax_sc_edit );

	$ax_sc_envelope = axismundi_cal_event_get( $ax_sc_legacy );
	$ax_sc_schedule = axismundi_cal_schedule_for_event( $ax_sc_legacy );
	ax_sc_assert(
		$ax_sc_results,
		'the Event as read agrees with the Schedule, which is the only thing that was written',
		'2026-08-02 18:00:00' === $ax_sc_envelope['starts_at'] && $ax_sc_envelope['starts_at'] === $ax_sc_schedule['dtstart_local']
	);
	ax_sc_assert( $ax_sc_results, 'and its UTC is recomputed from the event zone rather than stored twice', '2026-08-02 09:00:00' === $ax_sc_envelope['starts_at_gmt'] );

	// Corrupted on purpose: if anything still read these, the assertion below would return them.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture.
	$wpdb->update( $ax_sc_events, array( 'starts_at' => '1999-01-01 00:00:00', 'starts_at_gmt' => '1999-01-01 00:00:00', 'timezone' => 'Europe/Paris' ), array( 'post_id' => $ax_sc_legacy ) );
	$ax_sc_after = axismundi_cal_event_get( $ax_sc_legacy );
	ax_sc_assert(
		$ax_sc_results,
		'the legacy time columns are dead: corrupting them changes nothing the Event reports',
		'2026-08-02 18:00:00' === $ax_sc_after['starts_at'] && 'Asia/Seoul' === $ax_sc_after['timezone']
	);

	ax_sc_assert( $ax_sc_results, 'a move is remembered as the previous start, so a peer can tell a reschedule from a new Event', '2026-08-01 10:00:00' === (string) $ax_sc_schedule['previous_start_utc'] );
	ax_sc_assert( $ax_sc_results, 'and SEQUENCE moved, because subscribers must re-sync a changed time', (int) $ax_sc_schedule['sequence'] > 0 );

	$ax_sc_seq = (int) $ax_sc_schedule['sequence'];
	axismundi_cal_event_save( $ax_sc_legacy, array( 'maximum_attendee_capacity' => 40 ) );
	$ax_sc_schedule = axismundi_cal_schedule_for_event( $ax_sc_legacy );
	ax_sc_assert( $ax_sc_results, 'while a change that is not about time or place leaves SEQUENCE alone, so an edit is not an update storm', $ax_sc_seq === (int) $ax_sc_schedule['sequence'] );

	// -- 3. Materializing twice is the same as materializing once -------------------------------

	$ax_sc_rec = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_author' => 1, 'post_title' => 'Recurring fixture' )
	);
	$ax_sc_posts[] = $ax_sc_rec;
	$ax_sc_id = axismundi_cal_schedule_save(
		$ax_sc_rec,
		array( 'timezone' => 'Asia/Seoul', 'dtstart_local' => '2026-08-01 19:00:00', 'dtend_local' => '2026-08-01 21:00:00', 'rrule' => 'FREQ=WEEKLY;BYDAY=SA;COUNT=6' )
	);
	ax_sc_assert( $ax_sc_results, 'a recurring Schedule saves and materializes', is_int( $ax_sc_id ) && $ax_sc_id > 0 );

	$ax_sc_first = ax_sc_rows( (int) $ax_sc_id );
	ax_sc_assert( $ax_sc_results, 'and the cache holds one row per occurrence the rule produces', 6 === count( $ax_sc_first ) );
	axismundi_cal_materialize( (int) $ax_sc_id );
	ax_sc_assert( $ax_sc_results, 'materializing again produces exactly the same rows, so a rebuild is not a change', $ax_sc_first === ax_sc_rows( (int) $ax_sc_id ) );

	$ax_sc_cached = axismundi_cal_cached_range( (int) $ax_sc_id, '2026-08-01 00:00:00', '2026-08-16 00:00:00' );
	$ax_sc_live   = axismundi_cal_expand( (array) axismundi_cal_schedule_for_event( $ax_sc_rec ), '2026-08-01 00:00:00', '2026-08-16 00:00:00' );
	ax_sc_assert(
		$ax_sc_results,
		'the cached range and a live expansion agree, so reading the cache is not a different answer',
		count( $ax_sc_cached ) === count( $ax_sc_live )
			&& array_map( static fn( array $r ) : string => (string) $r['start_utc'], $ax_sc_cached )
				=== array_map( static fn( array $r ) : string => (string) $r['start_utc'], $ax_sc_live )
	);

	// -- 4. Authored exceptions survive a rebuild -----------------------------------------------

	$ax_sc_occ = axismundi_cal_occurrences_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture standing in for the editor.
	$wpdb->delete( $ax_sc_occ, array( 'schedule_id' => (int) $ax_sc_id, 'recurrence_id' => '20260808T190000' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture standing in for the editor.
	$wpdb->insert(
		$ax_sc_occ,
		array(
			'schedule_id' => (int) $ax_sc_id, 'recurrence_id' => '20260808T190000',
			'start_utc' => '2026-08-08 10:00:00', 'end_utc' => '2026-08-08 12:00:00',
			'start_local' => '2026-08-08 19:00:00', 'end_local' => '2026-08-08 21:00:00',
			'status' => 'cancelled', 'origin' => 'override', 'location_place_id' => null,
			'location_text' => '', 'override_json' => '', 'created_at' => $ax_sc_now, 'updated_at' => $ax_sc_now,
		)
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture standing in for the editor.
	$wpdb->delete( $ax_sc_occ, array( 'schedule_id' => (int) $ax_sc_id, 'recurrence_id' => '20260815T190000' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture standing in for the editor.
	$wpdb->insert(
		$ax_sc_occ,
		array(
			'schedule_id' => (int) $ax_sc_id, 'recurrence_id' => '20260815T190000',
			'start_utc' => '2026-08-15 05:00:00', 'end_utc' => '2026-08-15 07:00:00',
			'start_local' => '2026-08-15 14:00:00', 'end_local' => '2026-08-15 16:00:00',
			'status' => 'scheduled', 'origin' => 'override', 'location_place_id' => null,
			'location_text' => 'Moved to the annexe', 'override_json' => '', 'created_at' => $ax_sc_now, 'updated_at' => $ax_sc_now,
		)
	);

	axismundi_cal_materialize( (int) $ax_sc_id );
	$ax_sc_rebuilt = ax_sc_rows( (int) $ax_sc_id );
	$ax_sc_by_id   = array();
	foreach ( $ax_sc_rebuilt as $row ) {
		$ax_sc_by_id[ (string) $row['recurrence_id'] ] = $row;
	}

	ax_sc_assert(
		$ax_sc_results,
		'a cancelled occurrence survives a rebuild, because a cancellation exists nowhere else',
		isset( $ax_sc_by_id['20260808T190000'] ) && 'cancelled' === $ax_sc_by_id['20260808T190000']['status'] && 'override' === $ax_sc_by_id['20260808T190000']['origin']
	);
	ax_sc_assert(
		$ax_sc_results,
		'a moved occurrence keeps its moved time and its venue',
		isset( $ax_sc_by_id['20260815T190000'] ) && '2026-08-15 05:00:00' === $ax_sc_by_id['20260815T190000']['start_utc'] && 'Moved to the annexe' === $ax_sc_by_id['20260815T190000']['location_text']
	);
	ax_sc_assert(
		$ax_sc_results,
		'and it keeps the recurrence id of the instance it replaces, not of the time it moved to',
		isset( $ax_sc_by_id['20260815T190000'] ) && ! isset( $ax_sc_by_id['20260815T140000'] )
	);
	ax_sc_assert( $ax_sc_results, 'the rebuild still produces the full series, so exceptions did not displace rule rows', 6 === count( $ax_sc_rebuilt ) );

	$ax_sc_expanded = axismundi_cal_expand( (array) axismundi_cal_schedule_for_event( $ax_sc_rec ), '2026-08-08 00:00:00', '2026-08-09 00:00:00' );
	ax_sc_assert(
		$ax_sc_results,
		'and a live expansion applies the cancellation too, so cache and computation do not disagree',
		1 === count( $ax_sc_expanded ) && 'cancelled' === $ax_sc_expanded[0]['status']
	);
} finally {
	foreach ( array_unique( $ax_sc_posts ) as $ax_sc_post_id ) {
		$ax_sc_schedule_row = axismundi_cal_schedule_for_event( (int) $ax_sc_post_id );
		if ( is_array( $ax_sc_schedule_row ) ) {
			$wpdb->delete( axismundi_cal_occurrences_table(), array( 'schedule_id' => (int) $ax_sc_schedule_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( axismundi_cal_schedules_table(), array( 'id' => (int) $ax_sc_schedule_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_cal_events_table(), array( 'post_id' => (int) $ax_sc_post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_delete_post( (int) $ax_sc_post_id, true );
	}
}

$ax_sc_failures = count( array_filter( $ax_sc_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_sc_results ), $ax_sc_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_sc_failures > 0 ? 1 : 0 );
}
exit( $ax_sc_failures > 0 ? 1 : 0 );
