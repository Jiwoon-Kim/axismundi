<?php
/**
 * The range API across the cache horizon, and the recurring federation guard (dev-only).
 *
 * One property: the answer must not depend on how much of the series happens to be materialized.
 * A cache that answers "nothing" past its own far edge loses next year, and one that answers
 * "nothing" before its near edge loses the archive -- and both look like an empty month rather than
 * like a bug. So every case is checked against a live expansion of the same window, which is the
 * only answer that cannot be wrong for cache reasons.
 *
 * Second property: a recurring Event does not federate. FEP-8a8e carries one `startTime`, so a
 * weekly series published as a single Event tells peers it happens once -- and looks entirely
 * correct on the receiving end, which is what makes it worse than publishing nothing.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_rg_results = array();
$ax_rg_posts   = array();
$ax_rg_calendars = array();

/** @param bool[] $results Results. */
function ax_rg_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Start instants of a set of occurrences. */
function ax_rg_starts( array $occurrences ) : array {
	return array_map( static fn( array $o ) : string => (string) $o['start_utc'], $occurrences );
}

try {
	$ax_rg_calendar = axismundi_cal_calendar_save( array( 'name' => 'Range calendar', 'slug' => 'audit-range', 'timezone' => 'Asia/Seoul' ) );
	// Published on purpose. Every surface these fixtures exercise is a public one, and a Calendar is
	// private until somebody says otherwise, so the fixture has to say so.
	axismundi_cal_acl_grant( (int) $ax_rg_calendar, '', 'reader', 'public' );
	$ax_rg_calendars[] = (int) $ax_rg_calendar;
	$ax_rg_post = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_author' => 1, 'post_title' => 'Range fixture' )
	);
	$ax_rg_posts[] = $ax_rg_post;

	/*
	 * Written through the Event writer rather than straight to the Schedule, so the fixture has the
	 * envelope row a published Event really has. Reaching past the writer would leave an Event that
	 * reads as having no times at all, and the assertions below would then be measuring the fixture.
	 */
	$ax_rg_saved = axismundi_cal_event_save(
		$ax_rg_post,
		array( 'calendar_id' => (int) $ax_rg_calendar, 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-08-01 19:00:00', 'ends_at' => '2026-08-01 21:00:00', 'rrule' => 'FREQ=WEEKLY;BYDAY=SA' )
	);
	$ax_rg_id = (int) ( axismundi_cal_schedule_for_event( $ax_rg_post )['id'] ?? 0 );
	ax_rg_assert( $ax_rg_results, 'an unbounded weekly Schedule saves', true === $ax_rg_saved && $ax_rg_id > 0 );

	$ax_rg_schedule = axismundi_cal_schedule_for_event( $ax_rg_post );
	ax_rg_assert(
		$ax_rg_results,
		'materializing records both edges of the window, not only the far one',
		is_array( $ax_rg_schedule ) && '' !== (string) $ax_rg_schedule['materialized_from_utc'] && '' !== (string) $ax_rg_schedule['materialized_until_utc']
	);

	// -- Wholly inside the window ---------------------------------------------------------

	$ax_rg_inside_from = '2026-09-01 00:00:00';
	$ax_rg_inside_to   = '2026-10-01 00:00:00';
	ax_rg_assert(
		$ax_rg_results,
		'a range inside the cache matches a live expansion of the same range',
		ax_rg_starts( axismundi_cal_range( $ax_rg_schedule, $ax_rg_inside_from, $ax_rg_inside_to ) )
			=== ax_rg_starts( axismundi_cal_expand( $ax_rg_schedule, $ax_rg_inside_from, $ax_rg_inside_to ) )
	);

	// -- Beyond the far edge ---------------------------------------------------------------

	$ax_rg_beyond_from = gmdate( 'Y-m-d H:i:s', strtotime( (string) $ax_rg_schedule['materialized_until_utc'] . ' +30 days' ) );
	$ax_rg_beyond_to   = gmdate( 'Y-m-d H:i:s', strtotime( (string) $ax_rg_schedule['materialized_until_utc'] . ' +60 days' ) );
	$ax_rg_beyond      = axismundi_cal_range( $ax_rg_schedule, $ax_rg_beyond_from, $ax_rg_beyond_to );
	ax_rg_assert(
		$ax_rg_results,
		'a range wholly past the horizon is answered, rather than being reported as an empty month',
		count( $ax_rg_beyond ) > 0
	);
	ax_rg_assert(
		$ax_rg_results,
		'and it matches a live expansion exactly',
		ax_rg_starts( $ax_rg_beyond ) === ax_rg_starts( axismundi_cal_expand( $ax_rg_schedule, $ax_rg_beyond_from, $ax_rg_beyond_to ) )
	);
	ax_rg_assert(
		$ax_rg_results,
		'reading the cache alone would have returned nothing there, so the guard is what produced the answer',
		array() === axismundi_cal_cached_range( (int) $ax_rg_id, $ax_rg_beyond_from, $ax_rg_beyond_to )
	);

	// -- Straddling the far edge, which is the case that returns too few rows rather than none --

	$ax_rg_straddle_from = gmdate( 'Y-m-d H:i:s', strtotime( (string) $ax_rg_schedule['materialized_until_utc'] . ' -30 days' ) );
	$ax_rg_straddle_to   = gmdate( 'Y-m-d H:i:s', strtotime( (string) $ax_rg_schedule['materialized_until_utc'] . ' +30 days' ) );
	$ax_rg_straddle      = axismundi_cal_range( $ax_rg_schedule, $ax_rg_straddle_from, $ax_rg_straddle_to );
	$ax_rg_straddle_live = axismundi_cal_expand( $ax_rg_schedule, $ax_rg_straddle_from, $ax_rg_straddle_to );
	ax_rg_assert(
		$ax_rg_results,
		'a range straddling the horizon returns the cached part and the computed part as one set',
		ax_rg_starts( $ax_rg_straddle ) === ax_rg_starts( $ax_rg_straddle_live )
	);
	ax_rg_assert(
		$ax_rg_results,
		'and nothing is duplicated where the two meet, because recurrence ids are the merge key',
		count( $ax_rg_straddle ) === count( array_unique( ax_rg_starts( $ax_rg_straddle ) ) )
	);
	ax_rg_assert(
		$ax_rg_results,
		'which is strictly more than the cache alone would have given',
		count( $ax_rg_straddle ) > count( axismundi_cal_cached_range( (int) $ax_rg_id, $ax_rg_straddle_from, $ax_rg_straddle_to ) )
	);

	// -- Before the near edge ---------------------------------------------------------------

	$ax_rg_before_from = '2026-06-01 00:00:00';
	$ax_rg_before_to   = '2026-08-15 00:00:00';
	ax_rg_assert(
		$ax_rg_results,
		'a range reaching back before the cache begins is answered from computation, not truncated',
		ax_rg_starts( axismundi_cal_range( $ax_rg_schedule, $ax_rg_before_from, $ax_rg_before_to ) )
			=== ax_rg_starts( axismundi_cal_expand( $ax_rg_schedule, $ax_rg_before_from, $ax_rg_before_to ) )
	);

	// -- An override is still honoured when the answer comes from the cache ------------------

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture standing in for the editor.
	$wpdb->delete( axismundi_cal_occurrences_table(), array( 'schedule_id' => (int) $ax_rg_id, 'recurrence_id' => '20260905T190000' ) );
	$ax_rg_now = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture standing in for the editor.
	$wpdb->insert(
		axismundi_cal_occurrences_table(),
		array(
			'schedule_id' => (int) $ax_rg_id, 'recurrence_id' => '20260905T190000',
			'start_utc' => '2026-09-05 10:00:00', 'end_utc' => '2026-09-05 12:00:00',
			'start_local' => '2026-09-05 19:00:00', 'end_local' => '2026-09-05 21:00:00',
			'status' => 'cancelled', 'origin' => 'override', 'location_place_id' => null,
			'location_text' => '', 'override_json' => '', 'created_at' => $ax_rg_now, 'updated_at' => $ax_rg_now,
		)
	);
	$ax_rg_with_override = axismundi_cal_range( $ax_rg_schedule, '2026-09-05 00:00:00', '2026-09-06 00:00:00' );
	ax_rg_assert(
		$ax_rg_results,
		'a cancelled instance read through the range API is still cancelled',
		1 === count( $ax_rg_with_override ) && 'cancelled' === (string) $ax_rg_with_override[0]['status']
	);

	// -- The federation guard ----------------------------------------------------------------

	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $ax_rg_post, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;
	ax_rg_assert(
		$ax_rg_results,
		'a recurring Event is withheld from federation, since one Event carries one startTime',
		false === axismundi_cal_event_visible( get_post( $ax_rg_post ) )
	);
	ax_rg_assert(
		$ax_rg_results,
		'and the REST envelope says so, so the panel and the guard cannot disagree',
		true === axismundi_cal_rest_envelope( $ax_rg_post )['recurring']
	);

	// Created as a draft and published afterwards: an Event cannot be published before it has an
	// envelope, which is the publish guard doing its job rather than something to work around.
	$ax_rg_single = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_author' => 1, 'post_title' => 'Single fixture' )
	);
	$ax_rg_posts[] = $ax_rg_single;
	axismundi_cal_event_save(
		$ax_rg_single,
		array( 'calendar_id' => (int) $ax_rg_calendar, 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-08-01 19:00:00', 'ends_at' => '2026-08-01 21:00:00' )
	);
	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $ax_rg_single, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;
	ax_rg_assert(
		$ax_rg_results,
		'while a single Event still federates, so the guard is narrow rather than a blanket stop',
		true === axismundi_cal_event_visible( get_post( $ax_rg_single ) )
	);
	ax_rg_assert(
		$ax_rg_results,
		'and adding a rule to it withdraws it',
		true === axismundi_cal_event_save( $ax_rg_single, array( 'rrule' => 'FREQ=WEEKLY;BYDAY=SA' ) )
			&& false === axismundi_cal_event_visible( get_post( $ax_rg_single ) )
	);
} finally {
	foreach ( array_unique( $ax_rg_posts ) as $ax_rg_post_id ) {
		$ax_rg_row = axismundi_cal_schedule_for_event( (int) $ax_rg_post_id );
		if ( is_array( $ax_rg_row ) ) {
			$wpdb->delete( axismundi_cal_occurrences_table(), array( 'schedule_id' => (int) $ax_rg_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( axismundi_cal_schedules_table(), array( 'id' => (int) $ax_rg_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_cal_events_table(), array( 'post_id' => (int) $ax_rg_post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_delete_post( (int) $ax_rg_post_id, true );
	}
	foreach ( array_unique( $ax_rg_calendars ) as $ax_rg_calendar_id ) {
		axismundi_cal_calendar_delete( (int) $ax_rg_calendar_id );
	}
}

$ax_rg_failures = count( array_filter( $ax_rg_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_rg_results ), $ax_rg_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_rg_failures > 0 ? 1 : 0 );
}
exit( $ax_rg_failures > 0 ? 1 : 0 );
