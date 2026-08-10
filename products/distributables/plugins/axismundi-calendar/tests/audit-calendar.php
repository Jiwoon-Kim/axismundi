<?php
/**
 * Calendar collections and their membership (dev-only; dist-excluded).
 *
 * The property that matters most is that a filter which matches nothing returns nothing. A filtered
 * view that quietly falls back to the whole site is the failure that looks like a working page: an
 * empty or mistyped Calendar would show every Event on the site, on a page built to show a few.
 *
 * The second is that membership is by series. A weekly meeting is one member however often it meets
 * and an annual birthday is one member forever, so a Calendar's size follows how many things are in
 * it rather than how long they run.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_cl_results   = array();
$ax_cl_posts     = array();
$ax_cl_calendars = array();

/** @param bool[] $results Results. */
function ax_cl_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Publish an Event through the real writers. */
function ax_cl_event( array &$posts, string $title, array $fields ) : int {
	$id      = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_author' => 1, 'post_title' => $title ) );
	$posts[] = $id;
	axismundi_cal_event_save( $id, $fields );
	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;
	return $id;
}

/** Titles in a range, for one Calendar or the whole site. */
function ax_cl_titles( string $from, string $to, int $calendar_id ) : array {
	return array_values( array_unique( array_map(
		static fn( array $o ) : string => (string) $o['title'],
		axismundi_cal_occurrences_in_range( $from, $to, AXISMUNDI_CAL_RANGE_MAX, $calendar_id )
	) ) );
}

try {
	$ax_cl_a = ax_cl_event( $ax_cl_posts, 'Belongs to A', array( 'timezone' => 'UTC', 'starts_at' => '2026-09-05 10:00:00', 'ends_at' => '2026-09-05 12:00:00' ) );
	$ax_cl_b = ax_cl_event( $ax_cl_posts, 'Belongs to B', array( 'timezone' => 'UTC', 'starts_at' => '2026-09-06 10:00:00', 'ends_at' => '2026-09-06 12:00:00' ) );
	$ax_cl_both = ax_cl_event( $ax_cl_posts, 'Belongs to both', array( 'timezone' => 'UTC', 'starts_at' => '2026-09-07 10:00:00', 'ends_at' => '2026-09-07 12:00:00' ) );

	// -- Creating -----------------------------------------------------------------------------

	$ax_cl_first = axismundi_cal_calendar_save( array( 'name' => 'Calendar A', 'slug' => 'cal-a', 'timezone' => 'Asia/Seoul' ) );
	ax_cl_assert( $ax_cl_results, 'a calendar is created', is_int( $ax_cl_first ) && $ax_cl_first > 0 );
	$ax_cl_calendars[] = (int) $ax_cl_first;

	$ax_cl_second = axismundi_cal_calendar_save( array( 'name' => 'Calendar B', 'slug' => 'cal-b' ) );
	$ax_cl_calendars[] = (int) $ax_cl_second;

	$ax_cl_dupe = axismundi_cal_calendar_save( array( 'name' => 'Another', 'slug' => 'cal-a' ) );
	ax_cl_assert(
		$ax_cl_results,
		'a duplicate slug is refused rather than silently suffixed, because the slug is a subscription URL people already hold',
		is_wp_error( $ax_cl_dupe ) && 'ax_cal_slug_taken' === $ax_cl_dupe->get_error_code()
	);
	ax_cl_assert( $ax_cl_results, 'a calendar with no name is refused', is_wp_error( axismundi_cal_calendar_save( array( 'name' => '  ' ) ) ) );
	ax_cl_assert( $ax_cl_results, 'and an invented timezone is refused', is_wp_error( axismundi_cal_calendar_save( array( 'name' => 'Bad zone', 'slug' => 'bad-zone', 'timezone' => 'Not/AZone' ) ) ) );

	/*
	 * A site set to a manual UTC offset reports `+09:00` from `wp_timezone_string()`, which is not an
	 * IANA identifier. Taking it as the default made creating any calendar impossible on such a site,
	 * with an error naming a timezone the author never typed. An unusable default is dropped; an
	 * unusable value the author actually supplied is still refused, which the assertion above pins.
	 */
	$ax_cl_default = axismundi_cal_calendar_save( array( 'name' => 'Default zone', 'slug' => 'cal-default-zone' ) );
	ax_cl_assert( $ax_cl_results, 'a calendar can be created without naming a timezone, whatever the site is set to', is_int( $ax_cl_default ) && $ax_cl_default > 0 );
	if ( is_int( $ax_cl_default ) ) {
		$ax_cl_calendars[] = $ax_cl_default;
		$ax_cl_zone        = (string) axismundi_cal_calendar_get( $ax_cl_default )['timezone'];
		ax_cl_assert(
			$ax_cl_results,
			'and it stores a real zone or none, never a bare offset that later reads as invalid',
			'' === $ax_cl_zone || in_array( $ax_cl_zone, timezone_identifiers_list(), true )
		);
	}

	// -- Membership ----------------------------------------------------------------------------

	ax_cl_assert( $ax_cl_results, 'an Event joins a calendar', true === axismundi_cal_add_event( (int) $ax_cl_first, $ax_cl_a ) );
	axismundi_cal_add_event( (int) $ax_cl_first, $ax_cl_both );
	axismundi_cal_add_event( (int) $ax_cl_second, $ax_cl_b );
	axismundi_cal_add_event( (int) $ax_cl_second, $ax_cl_both );

	ax_cl_assert(
		$ax_cl_results,
		'one Event belongs to several calendars at once, which is why membership is not a category',
		2 === count( axismundi_cal_event_calendars( $ax_cl_both ) )
	);

	$ax_cl_before = count( axismundi_cal_calendar_event_ids( (int) $ax_cl_first ) );
	axismundi_cal_add_event( (int) $ax_cl_first, $ax_cl_a );
	ax_cl_assert( $ax_cl_results, 'adding the same Event twice leaves one membership, so a retry cannot double it', $ax_cl_before === count( axismundi_cal_calendar_event_ids( (int) $ax_cl_first ) ) );

	ax_cl_assert( $ax_cl_results, 'a non-Event cannot be added', is_wp_error( axismundi_cal_add_event( (int) $ax_cl_first, 1 ) ) );
	ax_cl_assert( $ax_cl_results, 'and a calendar that does not exist cannot be added to', is_wp_error( axismundi_cal_add_event( 999999, $ax_cl_a ) ) );

	// -- Filtering ------------------------------------------------------------------------------

	$ax_cl_from = '2026-09-01 00:00:00';
	$ax_cl_to   = '2026-09-30 00:00:00';

	$ax_cl_in_a = ax_cl_titles( $ax_cl_from, $ax_cl_to, (int) $ax_cl_first );
	ax_cl_assert( $ax_cl_results, 'a calendar shows its own members', in_array( 'Belongs to A', $ax_cl_in_a, true ) && in_array( 'Belongs to both', $ax_cl_in_a, true ) );
	ax_cl_assert( $ax_cl_results, 'and not another calendar\'s', ! in_array( 'Belongs to B', $ax_cl_in_a, true ) );

	$ax_cl_site = ax_cl_titles( $ax_cl_from, $ax_cl_to, 0 );
	ax_cl_assert( $ax_cl_results, 'the unfiltered site view still shows everything', in_array( 'Belongs to A', $ax_cl_site, true ) && in_array( 'Belongs to B', $ax_cl_site, true ) );

	$ax_cl_empty = axismundi_cal_calendar_save( array( 'name' => 'Empty', 'slug' => 'cal-empty' ) );
	$ax_cl_calendars[] = (int) $ax_cl_empty;
	ax_cl_assert(
		$ax_cl_results,
		'an empty calendar shows nothing rather than falling back to the whole site',
		array() === ax_cl_titles( $ax_cl_from, $ax_cl_to, (int) $ax_cl_empty )
	);

	// -- Removal and lifecycle --------------------------------------------------------------------

	$ax_cl_rev = (int) axismundi_cal_calendar_get( (int) $ax_cl_first )['revision'];
	axismundi_cal_remove_event( (int) $ax_cl_first, $ax_cl_a );
	ax_cl_assert( $ax_cl_results, 'removing a member takes it out of the calendar', ! in_array( 'Belongs to A', ax_cl_titles( $ax_cl_from, $ax_cl_to, (int) $ax_cl_first ), true ) );
	ax_cl_assert( $ax_cl_results, 'and the Event itself survives, because a collection is not its contents', 'publish' === get_post_status( $ax_cl_a ) );
	ax_cl_assert( $ax_cl_results, 'the revision moves when membership changes', (int) axismundi_cal_calendar_get( (int) $ax_cl_first )['revision'] > $ax_cl_rev );

	wp_delete_post( $ax_cl_b, true );
	ax_cl_assert(
		$ax_cl_results,
		'deleting an Event drops its memberships, so a calendar cannot count something that no longer exists',
		! in_array( $ax_cl_b, axismundi_cal_calendar_event_ids( (int) $ax_cl_second ), true )
	);

	// -- Series membership ------------------------------------------------------------------------

	$ax_cl_series = ax_cl_event(
		$ax_cl_posts,
		'Weekly member',
		array( 'timezone' => 'UTC', 'starts_at' => '2026-09-05 10:00:00', 'ends_at' => '2026-09-05 12:00:00', 'rrule' => 'FREQ=WEEKLY;BYDAY=SA' )
	);
	axismundi_cal_add_event( (int) $ax_cl_second, $ax_cl_series );
	ax_cl_assert(
		$ax_cl_results,
		'a recurring Event is one member however many times it meets, so a calendar does not grow with time',
		1 === count( array_filter( axismundi_cal_calendar_event_ids( (int) $ax_cl_second ), static fn( int $id ) : bool => $id === $ax_cl_series ) )
	);
	$ax_cl_series_rows = array_filter(
		axismundi_cal_occurrences_in_range( $ax_cl_from, $ax_cl_to, AXISMUNDI_CAL_RANGE_MAX, (int) $ax_cl_second ),
		static fn( array $o ) : bool => 'Weekly member' === $o['title']
	);
	ax_cl_assert( $ax_cl_results, 'while the range view still shows each of its occurrences', count( $ax_cl_series_rows ) >= 3 );

	// -- The subscription feed ---------------------------------------------------------------------

	$ax_cl_feed = axismundi_cal_site_feed( (int) $ax_cl_second, 'Calendar B' );
	ax_cl_assert( $ax_cl_results, 'a calendar feed carries its own members', str_contains( $ax_cl_feed['body'], 'Weekly member' ) );
	ax_cl_assert( $ax_cl_results, 'and not another calendar\'s', ! str_contains( $ax_cl_feed['body'], 'Belongs to A' ) );
	ax_cl_assert( $ax_cl_results, 'and names itself rather than the site', str_contains( $ax_cl_feed['body'], 'X-WR-CALNAME:Calendar B' ) );
	ax_cl_assert(
		$ax_cl_results,
		'a recurring member is exported as a rule, not as one component per occurrence',
		1 === substr_count( $ax_cl_feed['body'], 'RRULE:' )
	);

	// -- Block rendering ----------------------------------------------------------------------------

	$ax_cl_html = do_blocks( '<!-- wp:axismundi-calendar/calendar {"calendar":"cal-a","view":"month"} /-->' );
	ax_cl_assert( $ax_cl_results, 'the block accepts a calendar slug', str_contains( $ax_cl_html, 'ax-cal__grid' ) );
	$ax_cl_unknown = do_blocks( '<!-- wp:axismundi-calendar/calendar {"calendar":"no-such-calendar"} /-->' );
	ax_cl_assert(
		$ax_cl_results,
		'and an unknown slug renders an empty calendar rather than the whole site, so a typo does not publish everything',
		str_contains( $ax_cl_unknown, 'ax-cal__empty' ) && ! str_contains( $ax_cl_unknown, 'Belongs to both' )
	);

	// -- Deleting a calendar --------------------------------------------------------------------------

	axismundi_cal_calendar_delete( (int) $ax_cl_empty );
	ax_cl_assert( $ax_cl_results, 'a deleted calendar is gone', null === axismundi_cal_calendar_by_slug( 'cal-empty' ) );
	ax_cl_assert( $ax_cl_results, 'and its slug is free again', ! is_wp_error( axismundi_cal_calendar_save( array( 'name' => 'Reused', 'slug' => 'cal-empty' ) ) ) );
	$ax_cl_calendars[] = (int) axismundi_cal_calendar_by_slug( 'cal-empty' )['id'];
} finally {
	foreach ( array_unique( $ax_cl_calendars ) as $ax_cl_calendar_id ) {
		axismundi_cal_calendar_delete( (int) $ax_cl_calendar_id );
	}
	foreach ( array_unique( $ax_cl_posts ) as $ax_cl_post_id ) {
		$ax_cl_row = axismundi_cal_schedule_for_event( (int) $ax_cl_post_id );
		if ( is_array( $ax_cl_row ) ) {
			$wpdb->delete( axismundi_cal_occurrences_table(), array( 'schedule_id' => (int) $ax_cl_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( axismundi_cal_schedules_table(), array( 'id' => (int) $ax_cl_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_cal_events_table(), array( 'post_id' => (int) $ax_cl_post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_delete_post( (int) $ax_cl_post_id, true );
	}
}

$ax_cl_failures = count( array_filter( $ax_cl_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_cl_results ), $ax_cl_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_cl_failures > 0 ? 1 : 0 );
}
exit( $ax_cl_failures > 0 ? 1 : 0 );
