<?php
/**
 * Expanding a Schedule into Occurrences (dev-only; dist-excluded).
 *
 * The failures this pins are all of one kind: an expander that is right for most of the year. A
 * weekly event keeps its wall time across a DST change while the instant beneath it moves, so an
 * implementation that expands in UTC is correct for roughly ten months and silently an hour wrong
 * for the other two. Nothing reports that; the calendar renders either way.
 *
 * No database: expansion is a pure function of a schedule row, so the fixtures are arrays. The
 * override cases that do need storage are marked and skipped when the tables are absent.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_oc_results = array();

/** @param bool[] $results Results. */
function ax_oc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A schedule fixture. */
function ax_oc_schedule( array $overrides = array() ) : array {
	return array_merge(
		array(
			'id'                => 0,
			'timezone'          => 'Asia/Seoul',
			'all_day'           => 0,
			'dtstart_local'     => '2026-08-01 19:00:00',
			'dtend_local'       => '2026-08-01 21:00:00',
			'rrule'             => '',
			'location_place_id' => null,
			'location_text'     => '',
		),
		$overrides
	);
}

/** Local start times produced in a window. */
function ax_oc_starts( array $schedule, string $from, string $to ) : array {
	return array_map(
		static fn( array $o ) : string => (string) $o['start_local'],
		axismundi_cal_expand( $schedule, $from, $to )
	);
}

/** UTC start times produced in a window. */
function ax_oc_utc( array $schedule, string $from, string $to ) : array {
	return array_map(
		static fn( array $o ) : string => (string) $o['start_utc'],
		axismundi_cal_expand( $schedule, $from, $to )
	);
}

// -- A single non-recurring instance, which is what every Event has today --------------------

$ax_oc_single = ax_oc_schedule();
ax_oc_assert(
	$ax_oc_results,
	'a schedule with no rule produces exactly one occurrence',
	array( '2026-08-01 19:00:00' ) === ax_oc_starts( $ax_oc_single, '2026-07-01 00:00:00', '2026-09-01 00:00:00' )
);
ax_oc_assert(
	$ax_oc_results,
	'and its UTC is derived from the event zone, not the site zone',
	array( '2026-08-01 10:00:00' ) === ax_oc_utc( $ax_oc_single, '2026-07-01 00:00:00', '2026-09-01 00:00:00' )
);
ax_oc_assert(
	$ax_oc_results,
	'a window that ends before it starts produces nothing rather than everything',
	array() === ax_oc_starts( $ax_oc_single, '2026-09-01 00:00:00', '2026-08-01 00:00:00' )
);

// -- Weekly, and the DST change that makes wall time the only correct basis ------------------

$ax_oc_dst = ax_oc_schedule(
	array(
		'timezone'      => 'America/New_York',
		'dtstart_local' => '2026-10-24 19:00:00',
		'dtend_local'   => '2026-10-24 21:00:00',
		'rrule'         => 'FREQ=WEEKLY;BYDAY=SA',
	)
);
$ax_oc_dst_local = ax_oc_starts( $ax_oc_dst, '2026-10-20 00:00:00', '2026-11-20 00:00:00' );
ax_oc_assert(
	$ax_oc_results,
	'a weekly event keeps its wall time across a DST change',
	array( '2026-10-24 19:00:00', '2026-10-31 19:00:00', '2026-11-07 19:00:00', '2026-11-14 19:00:00' ) === $ax_oc_dst_local
);
$ax_oc_dst_utc = ax_oc_utc( $ax_oc_dst, '2026-10-20 00:00:00', '2026-11-20 00:00:00' );
ax_oc_assert(
	$ax_oc_results,
	'and its UTC instant moves by an hour when the clocks do, which is what a stored UTC pair would have got wrong',
	'2026-10-24 23:00:00' === $ax_oc_dst_utc[0] && '2026-11-08 00:00:00' === $ax_oc_dst_utc[2]
);

$ax_oc_seoul = ax_oc_schedule( array( 'rrule' => 'FREQ=WEEKLY;BYDAY=SA' ) );
ax_oc_assert(
	$ax_oc_results,
	'a zone without DST is stable, so the two cases are distinguished rather than both merely passing',
	array( '2026-08-01 10:00:00', '2026-08-08 10:00:00' ) === array_slice( ax_oc_utc( $ax_oc_seoul, '2026-08-01 00:00:00', '2026-08-15 00:00:00' ), 0, 2 )
);

// -- Bounds --------------------------------------------------------------------------------

$ax_oc_count = ax_oc_schedule( array( 'rrule' => 'FREQ=DAILY;COUNT=3' ) );
ax_oc_assert(
	$ax_oc_results,
	'COUNT bounds the series',
	3 === count( ax_oc_starts( $ax_oc_count, '2026-07-01 00:00:00', '2027-01-01 00:00:00' ) )
);
ax_oc_assert(
	$ax_oc_results,
	'and counts from the start of the series, not from the window, so a later month does not restart it',
	array() === ax_oc_starts( $ax_oc_count, '2026-09-01 00:00:00', '2026-10-01 00:00:00' )
);

$ax_oc_until = ax_oc_schedule( array( 'rrule' => 'FREQ=DAILY;UNTIL=20260803T235959Z' ) );
ax_oc_assert(
	$ax_oc_results,
	'UNTIL bounds the series',
	3 === count( ax_oc_starts( $ax_oc_until, '2026-07-01 00:00:00', '2027-01-01 00:00:00' ) )
);

// -- Monthly, including the ordinal weekday the panel has to support -------------------------

$ax_oc_first_sat = ax_oc_schedule(
	array(
		'dtstart_local' => '2026-08-01 14:00:00',
		'dtend_local'   => '2026-08-01 16:00:00',
		'rrule'         => 'FREQ=MONTHLY;BYDAY=1SA',
	)
);
ax_oc_assert(
	$ax_oc_results,
	'the first Saturday of each month',
	array( '2026-08-01 14:00:00', '2026-09-05 14:00:00', '2026-10-03 14:00:00' ) === ax_oc_starts( $ax_oc_first_sat, '2026-08-01 00:00:00', '2026-10-31 00:00:00' )
);

$ax_oc_last_fri = ax_oc_schedule(
	array(
		'dtstart_local' => '2026-08-28 14:00:00',
		'dtend_local'   => '2026-08-28 16:00:00',
		'rrule'         => 'FREQ=MONTHLY;BYDAY=-1FR',
	)
);
ax_oc_assert(
	$ax_oc_results,
	'the last Friday of each month, counted from the end',
	array( '2026-08-28 14:00:00', '2026-09-25 14:00:00' ) === ax_oc_starts( $ax_oc_last_fri, '2026-08-01 00:00:00', '2026-10-01 00:00:00' )
);

$ax_oc_31 = ax_oc_schedule(
	array(
		'dtstart_local' => '2026-01-31 09:00:00',
		'dtend_local'   => '2026-01-31 10:00:00',
		'rrule'         => 'FREQ=MONTHLY;BYMONTHDAY=31',
	)
);
$ax_oc_31_starts = ax_oc_starts( $ax_oc_31, '2026-01-01 00:00:00', '2026-05-01 00:00:00' );
ax_oc_assert(
	$ax_oc_results,
	'a monthly 31st skips the months that have none instead of rolling into the next month',
	array( '2026-01-31 09:00:00', '2026-03-31 09:00:00' ) === $ax_oc_31_starts
);

$ax_oc_last_day = ax_oc_schedule(
	array(
		'dtstart_local' => '2026-01-31 09:00:00',
		'dtend_local'   => '2026-01-31 10:00:00',
		'rrule'         => 'FREQ=MONTHLY;BYMONTHDAY=-1',
	)
);
ax_oc_assert(
	$ax_oc_results,
	'BYMONTHDAY=-1 is the last day, whatever length the month is',
	array( '2026-01-31 09:00:00', '2026-02-28 09:00:00', '2026-03-31 09:00:00' ) === ax_oc_starts( $ax_oc_last_day, '2026-01-01 00:00:00', '2026-04-01 00:00:00' )
);

$ax_oc_yearly = ax_oc_schedule(
	array(
		'dtstart_local' => '2026-10-03 09:00:00',
		'dtend_local'   => '2026-10-03 10:00:00',
		'rrule'         => 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=3',
	)
);
ax_oc_assert(
	$ax_oc_results,
	'an annual date',
	array( '2026-10-03 09:00:00', '2027-10-03 09:00:00' ) === ax_oc_starts( $ax_oc_yearly, '2026-01-01 00:00:00', '2028-01-01 00:00:00' )
);

// -- Windows --------------------------------------------------------------------------------

/*
 * The window is UTC while the schedule is local, so this fixture is in UTC deliberately: in any
 * other zone "crosses midnight" and "crosses the window boundary" are different moments, and the
 * assertion would be testing the offset rather than the overlap rule.
 */
$ax_oc_midnight = ax_oc_schedule(
	array(
		'timezone'      => 'UTC',
		'dtstart_local' => '2026-08-01 22:00:00',
		'dtend_local'   => '2026-08-02 02:00:00',
	)
);
ax_oc_assert(
	$ax_oc_results,
	'an event already under way when the window opens is part of it, rather than being missed for starting earlier',
	array( '2026-08-01 22:00:00' ) === ax_oc_starts( $ax_oc_midnight, '2026-08-02 00:00:00', '2026-08-03 00:00:00' )
);
ax_oc_assert(
	$ax_oc_results,
	'and it is equally part of the window it starts in',
	array( '2026-08-01 22:00:00' ) === ax_oc_starts( $ax_oc_midnight, '2026-08-01 00:00:00', '2026-08-02 00:00:00' )
);
ax_oc_assert(
	$ax_oc_results,
	'while a window it does not touch at all excludes it',
	array() === ax_oc_starts( $ax_oc_midnight, '2026-08-02 03:00:00', '2026-08-03 00:00:00' )
);

$ax_oc_all_day = ax_oc_schedule(
	array(
		'all_day'       => 1,
		'dtstart_local' => '2026-08-01 00:00:00',
		'dtend_local'   => '2026-08-02 00:00:00',
	)
);
$ax_oc_all_day_rows = axismundi_cal_expand( $ax_oc_all_day, '2026-07-01 00:00:00', '2026-09-01 00:00:00' );
ax_oc_assert(
	$ax_oc_results,
	'an all-day instance is identified by its date alone, since it is the same day everywhere',
	1 === count( $ax_oc_all_day_rows ) && '20260801' === $ax_oc_all_day_rows[0]['recurrence_id']
);

$ax_oc_timed = axismundi_cal_expand( $ax_oc_single, '2026-07-01 00:00:00', '2026-09-01 00:00:00' );
ax_oc_assert(
	$ax_oc_results,
	'a timed instance is identified by local wall time, which is what survives a move or a DST shift',
	'20260801T190000' === $ax_oc_timed[0]['recurrence_id']
);

// -- Refusals and safety ---------------------------------------------------------------------

ax_oc_assert(
	$ax_oc_results,
	'an unusable timezone produces nothing rather than falling back to the site zone',
	array() === ax_oc_starts( ax_oc_schedule( array( 'timezone' => 'Not/AZone' ) ), '2026-07-01 00:00:00', '2026-09-01 00:00:00' )
);
ax_oc_assert(
	$ax_oc_results,
	'a rule outside the supported set expands to nothing rather than to invented dates',
	array() === ax_oc_starts( ax_oc_schedule( array( 'rrule' => 'FREQ=MONTHLY;BYSETPOS=-1;BYDAY=MO' ) ), '2026-07-01 00:00:00', '2026-12-01 00:00:00' )
);
$ax_oc_unbounded = ax_oc_schedule( array( 'rrule' => 'FREQ=DAILY' ) );
$ax_oc_far = ax_oc_starts( $ax_oc_unbounded, '2026-08-10 00:00:00', '2026-08-13 00:00:00' );
ax_oc_assert(
	$ax_oc_results,
	'an unbounded rule answers a narrow window with just that window',
	array( '2026-08-10 19:00:00', '2026-08-11 19:00:00', '2026-08-12 19:00:00' ) === $ax_oc_far
);

$ax_oc_failures = count( array_filter( $ax_oc_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_oc_results ), $ax_oc_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_oc_failures > 0 ? 1 : 0 );
}
exit( $ax_oc_failures > 0 ? 1 : 0 );
