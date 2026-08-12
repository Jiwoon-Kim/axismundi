<?php
/**
 * One-off: is ICU's `dangi` the same calendar as KASI, across the range anybody alive will use?
 *
 * Fetches every month of 1900-2050 from KASI, stores it, and compares each day against ICU. Prints
 * only the days that differ, so silence is the result worth having.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$from = 1900;
$to   = 2050;

$cal = IntlCalendar::createInstance( 'UTC', 'ko_KR@calendar=dangi' );
$greg = IntlCalendar::createInstance( 'UTC', 'en_US@calendar=gregorian' );
$total = 0;
$diff  = 0;
$bad_years = array();

printf( "ICU %s, comparing %d-%d\n", INTL_ICU_VERSION, $from, $to );
foreach ( range( $from, $to ) as $year ) {
	$result = axismundi_cal_kasi_materialise_years( $year, $year );
	if ( '' !== $result['error'] ) {
		printf( "%d: FETCH FAILED %s\n", $year, $result['error'] );
		break;
	}

	global $wpdb;
	$table = axismundi_cal_lunar_months_table();
	$start = axismundi_cal_absolute_day( $year, 1, 1 );
	$end   = axismundi_cal_absolute_day( $year, 12, 31 );
	$year_diff = 0;
	for ( $abs = $start; $abs <= $end; $abs++ ) {
		$ours = axismundi_cal_lunar_date( AXISMUNDI_CAL_KOREAN_LUNISOLAR, $abs );
		if ( null === $ours ) {
			continue;
		}
		$iso = axismundi_cal_absolute_day_to_iso( $abs );
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $iso ) );
		$greg->clear();
		$greg->set( $y, $m - 1, $d, 12, 0, 0 );
		$cal->setTime( $greg->getTime() );
		$icu = array(
			'month'     => $cal->get( IntlCalendar::FIELD_MONTH ) + 1,
			'day'       => $cal->get( IntlCalendar::FIELD_DAY_OF_MONTH ),
			'leapMonth' => (bool) $cal->get( IntlCalendar::FIELD_IS_LEAP_MONTH ),
		);
		++$total;
		if ( $icu['month'] !== $ours['month'] || $icu['day'] !== $ours['day'] || $icu['leapMonth'] !== $ours['leapMonth'] ) {
			++$diff;
			++$year_diff;
			if ( $year_diff <= 3 ) {
				printf(
					"  %s KASI=%s%d.%d ICU=%s%d.%d\n",
					$iso,
					$ours['leapMonth'] ? 'L' : '',
					$ours['month'],
					$ours['day'],
					$icu['leapMonth'] ? 'L' : '',
					$icu['month'],
					$icu['day']
				);
			}
		}
	}
	if ( $year_diff > 0 ) {
		$bad_years[ $year ] = $year_diff;
		printf( "%d: %d days differ\n", $year, $year_diff );
	}
	if ( 0 === $year % 10 ) {
		printf( "... %d done, %d days compared, %d differ\n", $year, $total, $diff );
	}
}

printf( "\n== %d days compared, %d differ ==\n", $total, $diff );
if ( array() === $bad_years ) {
	echo "identical across the whole range\n";
} else {
	foreach ( $bad_years as $year => $count ) {
		printf( "%d: %d\n", $year, $count );
	}
}
