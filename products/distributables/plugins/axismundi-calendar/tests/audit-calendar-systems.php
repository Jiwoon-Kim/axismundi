<?php
/**
 * Audit: AbsoluteDay arithmetic, the calendar-system registry, and the lunar month store.
 *
 * No network. Every month here is a fixture, and that is the point of this slice: the arithmetic
 * over a materialised store is provable without an authority, so when KASI arrives the only new
 * question is whether the fetch was parsed correctly.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

/**
 * Record one assertion.
 *
 * @param array<int,array{0:bool,1:string}> $results Results.
 * @param string                            $label   What is claimed.
 * @param bool                              $ok      Whether it holds.
 * @return void
 */
function ax_cs_assert( array &$results, string $label, bool $ok ) : void {
	$results[] = array( $ok, $label );
	if ( ! $ok ) {
		echo "[FAIL] {$label}\n";
	}
}

$ax_cs_results = array();
$ax_cs_system  = 'audit-lunisolar';

// -- AbsoluteDay ---------------------------------------------------------------------------------

ax_cs_assert( $ax_cs_results, 'the epoch is day one', 1 === axismundi_cal_absolute_day( 1, 1, 1 ) );
ax_cs_assert( $ax_cs_results, 'and the day after it is day two', 2 === axismundi_cal_absolute_day( 1, 1, 2 ) );

/*
 * Against PHP's own calendar rather than against a table of answers I wrote. A hand-written
 * expectation would only prove the two agree with each other, and both would be mine.
 */
$ax_cs_walk_ok = true;
$ax_cs_probe   = new DateTimeImmutable( '1899-12-31', new DateTimeZone( 'UTC' ) );
for ( $ax_cs_i = 0; $ax_cs_i < 60000; $ax_cs_i++ ) {
	$ax_cs_expected = axismundi_cal_absolute_day( 1899, 12, 31 ) + $ax_cs_i;
	$ax_cs_date     = $ax_cs_probe->modify( '+' . $ax_cs_i . ' days' );
	$ax_cs_actual   = axismundi_cal_absolute_day( (int) $ax_cs_date->format( 'Y' ), (int) $ax_cs_date->format( 'n' ), (int) $ax_cs_date->format( 'j' ) );
	if ( $ax_cs_actual !== $ax_cs_expected ) {
		$ax_cs_walk_ok = false;
		break;
	}
}
ax_cs_assert( $ax_cs_results, 'one day of the civil calendar is one AbsoluteDay, for 164 years without a gap', $ax_cs_walk_ok );

$ax_cs_round_ok = true;
foreach ( array( '-0059-02-13', '0001-01-01', '1582-10-15', '1900-03-01', '2000-02-29', '2026-08-12', '2050-12-31' ) as $ax_cs_iso ) {
	$ax_cs_abs = axismundi_cal_iso_to_absolute_day( $ax_cs_iso );
	if ( null === $ax_cs_abs || axismundi_cal_absolute_day_to_iso( $ax_cs_abs ) !== $ax_cs_iso ) {
		$ax_cs_round_ok = false;
	}
}
ax_cs_assert( $ax_cs_results, 'and every date round-trips, including 59 BC and the leap day of a leap century', $ax_cs_round_ok );

ax_cs_assert(
	$ax_cs_results,
	'the year before the epoch is the year before, not the year after',
	axismundi_cal_absolute_day_to_iso( 0 ) === '0000-12-31'
);
ax_cs_assert(
	$ax_cs_results,
	'1900 was not a leap year and 2000 was',
	! axismundi_cal_is_leap_year( 1900 ) && axismundi_cal_is_leap_year( 2000 )
		&& 1 === axismundi_cal_absolute_day( 1900, 3, 1 ) - axismundi_cal_absolute_day( 1900, 2, 28 )
		&& 2 === axismundi_cal_absolute_day( 2000, 3, 1 ) - axismundi_cal_absolute_day( 2000, 2, 28 )
);
ax_cs_assert( $ax_cs_results, 'a day that does not exist is not a date', null === axismundi_cal_iso_to_absolute_day( '2026-02-30' ) );
ax_cs_assert( $ax_cs_results, 'nor is a month that does not exist', null === axismundi_cal_iso_to_absolute_day( '2026-13-01' ) );
ax_cs_assert( $ax_cs_results, 'nor is something that is not a date at all', null === axismundi_cal_iso_to_absolute_day( 'tomorrow' ) );

// -- The registry --------------------------------------------------------------------------------

ax_cs_assert(
	$ax_cs_results,
	'the Korean lunisolar system is registered whether or not one month has been fetched',
	is_array( axismundi_cal_calendar_system( 'korean-lunisolar' ) )
);
ax_cs_assert( $ax_cs_results, 'and a system nobody registered is not invented', null === axismundi_cal_calendar_system( 'martian' ) );

/*
 * What a system is, kept apart from what formats it. Islamic is lunar and Korean is lunisolar, and a
 * screen grouping them under one word would have to unlearn the difference when the second arrives.
 */
$ax_cs_korean = (array) axismundi_cal_calendar_system( 'korean-lunisolar' );
ax_cs_assert( $ax_cs_results, 'a system says which kind of calendar it is', 'lunisolar' === ( $ax_cs_korean['type'] ?? '' ) );
ax_cs_assert( $ax_cs_results, 'and who its dates come from', false !== strpos( (string) ( $ax_cs_korean['authority'] ?? '' ), 'KASI' ) );
ax_cs_assert(
	$ax_cs_results,
	'while the Unicode calendar is recorded beside it and is not the identifier',
	'dangi' === ( $ax_cs_korean['icu_calendar'] ?? '' ) && 'korean-lunisolar' === ( $ax_cs_korean['id'] ?? '' )
);
ax_cs_assert(
	$ax_cs_results,
	'and a system may render its own settings, because what one provider needs configured is its own business',
	is_callable( $ax_cs_korean['settings'] ?? null )
);

$ax_cs_in  = (int) axismundi_cal_iso_to_absolute_day( '2026-08-12' );
$ax_cs_out = (int) axismundi_cal_iso_to_absolute_day( '2051-01-01' );
$ax_cs_bc  = (int) axismundi_cal_iso_to_absolute_day( '-0060-01-01' );
ax_cs_assert( $ax_cs_results, 'a day inside the provider range is covered', axismundi_cal_system_covers( 'korean-lunisolar', $ax_cs_in ) );
ax_cs_assert( $ax_cs_results, 'a day after it is not, which is a fact about the provider and not an error', ! axismundi_cal_system_covers( 'korean-lunisolar', $ax_cs_out ) );
ax_cs_assert( $ax_cs_results, 'and neither is a day before it begins', ! axismundi_cal_system_covers( 'korean-lunisolar', $ax_cs_bc ) );

// -- The store -----------------------------------------------------------------------------------

global $wpdb;
$ax_cs_table = axismundi_cal_lunar_months_table();
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$ax_cs_table} WHERE system = %s", $ax_cs_system ) );

/*
 * Three consecutive months, one of them a leap month, with lengths that differ. Invented rather than
 * fetched: what is being proved is that a month row plus subtraction reproduces every date in it,
 * and a real month would prove exactly the same thing while also needing an API key.
 */
$ax_cs_months = array(
	array( 'start_date' => '2026-06-15', 'lunar_year' => 2026, 'lunar_month' => 4, 'leap_month' => false, 'days' => 30 ),
	array( 'start_date' => '2026-07-15', 'lunar_year' => 2026, 'lunar_month' => 4, 'leap_month' => true, 'days' => 29 ),
	array( 'start_date' => '2026-08-13', 'lunar_year' => 2026, 'lunar_month' => 5, 'leap_month' => false, 'days' => 30 ),
);
$ax_cs_saved = true;
foreach ( $ax_cs_months as $ax_cs_month ) {
	if ( ! is_int( axismundi_cal_lunar_month_save( $ax_cs_system, $ax_cs_month ) ) ) {
		$ax_cs_saved = false;
	}
}
ax_cs_assert( $ax_cs_results, 'three lunar months store', $ax_cs_saved );

$ax_cs_at = static fn( string $iso ) : ?array => axismundi_cal_lunar_date( $ax_cs_system, (int) axismundi_cal_iso_to_absolute_day( $iso ) );

ax_cs_assert(
	$ax_cs_results,
	'the first day of a stored month is day one of it',
	array( 'year' => 2026, 'month' => 4, 'day' => 1, 'leapMonth' => false ) === $ax_cs_at( '2026-06-15' )
);
ax_cs_assert(
	$ax_cs_results,
	'and the last day of a 30-day month is its 30th, not the 1st of the next',
	array( 'year' => 2026, 'month' => 4, 'day' => 30, 'leapMonth' => false ) === $ax_cs_at( '2026-07-14' )
);
ax_cs_assert(
	$ax_cs_results,
	'the day after it belongs to the next month, which is the leap 4th and not the 5th',
	array( 'year' => 2026, 'month' => 4, 'day' => 1, 'leapMonth' => true ) === $ax_cs_at( '2026-07-15' )
);
ax_cs_assert(
	$ax_cs_results,
	'a 29-day month ends on its 29th',
	array( 'year' => 2026, 'month' => 4, 'day' => 29, 'leapMonth' => true ) === $ax_cs_at( '2026-08-12' )
);
ax_cs_assert(
	$ax_cs_results,
	'and the month after a leap month is the next number',
	array( 'year' => 2026, 'month' => 5, 'day' => 1, 'leapMonth' => false ) === $ax_cs_at( '2026-08-13' )
);
ax_cs_assert(
	$ax_cs_results,
	'the leap month is a month of its own, not a variant of the one it repeats',
	is_array( axismundi_cal_lunar_month_get( $ax_cs_system, 2026, 4, false ) )
		&& is_array( axismundi_cal_lunar_month_get( $ax_cs_system, 2026, 4, true ) )
		&& axismundi_cal_lunar_month_get( $ax_cs_system, 2026, 4, false )['start_absolute_day']
			!== axismundi_cal_lunar_month_get( $ax_cs_system, 2026, 4, true )['start_absolute_day']
);

/*
 * The gap is the assertion that matters most. Nothing is stored before 2026-06-15, and the honest
 * answer for a day there is that this store has no name for it -- not the last month it does have,
 * counted forward until the number is absurd.
 */
ax_cs_assert( $ax_cs_results, 'a day before anything stored has no lunar date', null === $ax_cs_at( '2026-06-14' ) );
ax_cs_assert( $ax_cs_results, 'and a day after the last stored month has none either', null === $ax_cs_at( '2026-09-12' ) );

$ax_cs_range = axismundi_cal_lunar_dates( $ax_cs_system, '2026-07-10', '2026-07-20' );
ax_cs_assert( $ax_cs_results, 'a range answers one entry per day', 11 === count( $ax_cs_range ) );
ax_cs_assert(
	$ax_cs_results,
	'keyed by the Gregorian date the grid already knows',
	isset( $ax_cs_range['2026-07-15'] ) && 1 === $ax_cs_range['2026-07-15']['day'] && true === $ax_cs_range['2026-07-15']['leapMonth']
);
ax_cs_assert(
	$ax_cs_results,
	'and a range crossing a month boundary crosses it in the right place',
	30 === $ax_cs_range['2026-07-14']['day'] && 4 === $ax_cs_range['2026-07-14']['month'] && false === $ax_cs_range['2026-07-14']['leapMonth']
);
ax_cs_assert(
	$ax_cs_results,
	'a range reaching past the store returns the days it has and no others',
	array_keys( axismundi_cal_lunar_dates( $ax_cs_system, '2026-06-13', '2026-06-16' ) ) === array( '2026-06-15', '2026-06-16' )
);
ax_cs_assert( $ax_cs_results, 'and a range asked for backwards is empty rather than reversed', array() === axismundi_cal_lunar_dates( $ax_cs_system, '2026-07-20', '2026-07-10' ) );
ax_cs_assert( $ax_cs_results, 'a range nobody would draw is refused rather than walked', array() === axismundi_cal_lunar_dates( $ax_cs_system, '2026-01-01', '2030-01-01' ) );

// -- What a month may be -------------------------------------------------------------------------

ax_cs_assert(
	$ax_cs_results,
	'a month of 31 days is not a lunation and is refused',
	is_wp_error( axismundi_cal_lunar_month_save( $ax_cs_system, array( 'start_date' => '2026-09-12', 'lunar_year' => 2026, 'lunar_month' => 6, 'days' => 31 ) ) )
);
ax_cs_assert(
	$ax_cs_results,
	'a month with no day to start on is refused',
	is_wp_error( axismundi_cal_lunar_month_save( $ax_cs_system, array( 'lunar_year' => 2026, 'lunar_month' => 6, 'days' => 30 ) ) )
);
ax_cs_assert(
	$ax_cs_results,
	'and a 13th month is refused, because a leap month is the 4th again rather than a number past 12',
	is_wp_error( axismundi_cal_lunar_month_save( $ax_cs_system, array( 'start_date' => '2026-09-12', 'lunar_year' => 2026, 'lunar_month' => 13, 'days' => 30 ) ) )
);

$ax_cs_first = axismundi_cal_lunar_month_save( $ax_cs_system, array( 'start_date' => '2026-06-15', 'lunar_year' => 2026, 'lunar_month' => 4, 'days' => 29 ) );
ax_cs_assert(
	$ax_cs_results,
	're-materialising a month it already has corrects that month instead of storing it twice',
	is_int( $ax_cs_first ) && 29 === (int) axismundi_cal_lunar_month_get( $ax_cs_system, 2026, 4, false )['days']
);

// -- Through the system seam ---------------------------------------------------------------------

axismundi_cal_register_calendar_system(
	$ax_cs_system,
	array(
		'label'         => 'Audit lunisolar',
		'coverage_from' => '2026-06-15',
		'coverage_to'   => '2026-09-11',
		'resolve'       => static fn( int $day ) : ?array => axismundi_cal_lunar_date( $ax_cs_system, $day ),
	)
);
ax_cs_assert(
	$ax_cs_results,
	'a system resolves a day inside its coverage',
	is_array( axismundi_cal_system_date( $ax_cs_system, (int) axismundi_cal_iso_to_absolute_day( '2026-07-15' ) ) )
);
ax_cs_assert(
	$ax_cs_results,
	'and says nothing outside it without asking the store',
	null === axismundi_cal_system_date( $ax_cs_system, (int) axismundi_cal_iso_to_absolute_day( '2026-06-14' ) )
);
ax_cs_assert(
	$ax_cs_results,
	'while the Korean system covering a day it has not fetched still says nothing, rather than not existing',
	null !== axismundi_cal_calendar_system( 'korean-lunisolar' )
		&& axismundi_cal_system_covers( 'korean-lunisolar', $ax_cs_in )
		&& null === axismundi_cal_system_date( 'korean-lunisolar', $ax_cs_in )
);

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$ax_cs_table} WHERE system = %s", $ax_cs_system ) );

$ax_cs_failed = count( array_filter( $ax_cs_results, static fn( array $r ) : bool => ! $r[0] ) );
printf( "== %d checks, %d failed ==\n", count( $ax_cs_results ), $ax_cs_failed );
