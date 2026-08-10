<?php
/**
 * Calendar ownership and timezone inheritance (dev-only; dist-excluded).
 *
 * An Event has one Calendar. Its Schedule records that ownership, and the Calendar's IANA zone is
 * the default used to turn the author's local start/end values into UTC. The reader's timezone is
 * deliberately a separate display concern.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_cl_results   = array();
$ax_cl_posts     = array();
$ax_cl_calendars = array();

function ax_cl_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI audit output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

function ax_cl_event( array &$posts, int $calendar_id, string $title, array $fields ) : int {
	$id      = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_author' => 1, 'post_title' => $title ) );
	$posts[] = $id;
	axismundi_cal_event_save( $id, array_merge( $fields, array( 'calendar_id' => $calendar_id ) ) );
	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;
	return $id;
}

function ax_cl_titles( string $from, string $to, int $calendar_id ) : array {
	return array_values( array_unique( array_map(
		static fn( array $o ) : string => (string) $o['title'],
		axismundi_cal_occurrences_in_range( $from, $to, AXISMUNDI_CAL_RANGE_MAX, $calendar_id )
	) ) );
}

try {
	$ax_cl_first = axismundi_cal_calendar_save( array( 'name' => 'Calendar A', 'slug' => 'ownership-a', 'timezone' => 'Asia/Seoul' ) );
	$ax_cl_second = axismundi_cal_calendar_save( array( 'name' => 'Calendar B', 'slug' => 'ownership-b', 'timezone' => 'Europe/London' ) );
	$ax_cl_empty = axismundi_cal_calendar_save( array( 'name' => 'Empty', 'slug' => 'ownership-empty', 'timezone' => 'UTC' ) );
	$ax_cl_calendars = array( (int) $ax_cl_first, (int) $ax_cl_second, (int) $ax_cl_empty );

	ax_cl_assert( $ax_cl_results, 'a local Calendar requires a named IANA timezone', is_wp_error( axismundi_cal_calendar_save( array( 'name' => 'No zone', 'slug' => 'ownership-no-zone' ) ) ) );
	ax_cl_assert( $ax_cl_results, 'a fixed UTC offset is refused because a Calendar needs DST rules, not a snapshot', is_wp_error( axismundi_cal_calendar_save( array( 'name' => 'Offset', 'slug' => 'ownership-offset', 'timezone' => '+09:00' ) ) ) );

	$ax_cl_unfiled = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_author' => 1, 'post_title' => 'No calendar' ) );
	$ax_cl_posts[] = $ax_cl_unfiled;
	$ax_cl_missing = axismundi_cal_event_save( $ax_cl_unfiled, array( 'starts_at' => '2026-09-01 19:00:00', 'ends_at' => '2026-09-01 20:00:00' ) );
	ax_cl_assert( $ax_cl_results, 'an Event without a Calendar is refused instead of becoming unowned', is_wp_error( $ax_cl_missing ) && 'ax_event_calendar' === $ax_cl_missing->get_error_code() );

	$ax_cl_a = ax_cl_event( $ax_cl_posts, (int) $ax_cl_first, 'Seoul event', array( 'starts_at' => '2026-09-05 19:00:00', 'ends_at' => '2026-09-05 21:00:00' ) );
	$ax_cl_b = ax_cl_event( $ax_cl_posts, (int) $ax_cl_second, 'London event', array( 'starts_at' => '2026-09-06 09:00:00', 'ends_at' => '2026-09-06 10:00:00' ) );
	$ax_cl_a_schedule = axismundi_cal_schedule_for_event( $ax_cl_a );
	ax_cl_assert( $ax_cl_results, 'a new Event inherits its Calendar timezone when no override is supplied', is_array( $ax_cl_a_schedule ) && 'Asia/Seoul' === (string) $ax_cl_a_schedule['timezone'] && (int) $ax_cl_first === (int) $ax_cl_a_schedule['calendar_id'] );
	ax_cl_assert( $ax_cl_results, 'the inherited wall time becomes the correct UTC instant', is_array( $ax_cl_a_schedule ) && '2026-09-05 10:00:00' === axismundi_cal_to_utc( (string) $ax_cl_a_schedule['dtstart_local'], (string) $ax_cl_a_schedule['timezone'] ) );
	ax_cl_assert( $ax_cl_results, 'an Event resolves to one Calendar, never an array of memberships', (int) $ax_cl_first === (int) axismundi_cal_calendar_for_event( $ax_cl_a )['id'] );

	$ax_cl_from = '2026-09-01 00:00:00';
	$ax_cl_to   = '2026-09-30 00:00:00';
	ax_cl_assert( $ax_cl_results, 'a Calendar range contains its own Event', in_array( 'Seoul event', ax_cl_titles( $ax_cl_from, $ax_cl_to, (int) $ax_cl_first ), true ) );
	ax_cl_assert( $ax_cl_results, 'and never another Calendar event', ! in_array( 'London event', ax_cl_titles( $ax_cl_from, $ax_cl_to, (int) $ax_cl_first ), true ) );
	ax_cl_assert( $ax_cl_results, 'an empty Calendar returns nothing rather than the whole site', array() === ax_cl_titles( $ax_cl_from, $ax_cl_to, (int) $ax_cl_empty ) );

	$ax_cl_move = axismundi_cal_event_save( $ax_cl_a, array( 'calendar_id' => (int) $ax_cl_second ) );
	$ax_cl_moved = axismundi_cal_schedule_for_event( $ax_cl_a );
	ax_cl_assert( $ax_cl_results, 'moving an Event replaces its owner rather than adding a second Calendar', true === $ax_cl_move && is_array( $ax_cl_moved ) && (int) $ax_cl_second === (int) $ax_cl_moved['calendar_id'] && 1 === count( array_filter( axismundi_cal_calendar_event_ids( (int) $ax_cl_second ), static fn( int $id ) : bool => $id === $ax_cl_a ) ) );
	ax_cl_assert( $ax_cl_results, 'the former Calendar no longer lists the moved Event', ! in_array( $ax_cl_a, axismundi_cal_calendar_event_ids( (int) $ax_cl_first ), true ) );
	$ax_cl_feed = axismundi_cal_site_feed( (int) $ax_cl_second, 'Calendar B' );
	ax_cl_assert( $ax_cl_results, 'the Calendar ICS feed filters by Schedule ownership', str_contains( $ax_cl_feed['body'], 'Seoul event' ) && str_contains( $ax_cl_feed['body'], 'London event' ) );

	$ax_cl_london_rows = array_values( array_filter( axismundi_cal_occurrences_in_range( '2026-09-05 00:00:00', '2026-09-08 00:00:00', AXISMUNDI_CAL_RANGE_MAX, (int) $ax_cl_second ), static fn( array $o ) : bool => 'London event' === $o['title'] ) );
	$ax_cl_view = axismundi_cal_group_by_day( $ax_cl_london_rows, new DateTimeZone( 'Asia/Seoul' ) );
	ax_cl_assert( $ax_cl_results, 'the reader timezone remains independent of the Calendar timezone', isset( $ax_cl_view['2026-09-06'] ) );

	ax_cl_assert( $ax_cl_results, 'deleting a Calendar deletes the Events it owns rather than leaving unfiled schedules behind', true === axismundi_cal_calendar_delete( (int) $ax_cl_second ) && null === get_post( $ax_cl_a ) && null === get_post( $ax_cl_b ) && null === axismundi_cal_calendar_get( (int) $ax_cl_second ) );
} finally {
	foreach ( array_unique( $ax_cl_posts ) as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
	foreach ( array_unique( $ax_cl_calendars ) as $calendar_id ) {
		axismundi_cal_calendar_delete( (int) $calendar_id );
	}
}

$ax_cl_failures = count( array_filter( $ax_cl_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI audit output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_cl_results ), $ax_cl_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_cl_failures > 0 ? 1 : 0 );
}
exit( $ax_cl_failures > 0 ? 1 : 0 );
