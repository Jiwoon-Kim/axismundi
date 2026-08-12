<?php
/**
 * The lunar month store: the compressed form of a lunisolar calendar.
 *
 * A month of converted dates is 28-31 rows carrying three facts. This keeps the three -- which day
 * the month starts on, which month it is, and how long it runs -- and derives every date in it by
 * subtraction. Storing the dates themselves would be storing the same fact thirty times and giving
 * a later writer thirty places to disagree with itself.
 *
 * Boundaries are the irreducible input. Two anchors 94 days apart cannot say whether the months
 * between them ran 29+30+29 or 30+29+30, so a month is never inferred from its neighbours: it is
 * either materialised from an authority or absent, and absent is an answer.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** The lunisolar calendar Korea keeps, and the first system this store holds. */
const AXISMUNDI_CAL_KOREAN_LUNISOLAR = 'korean-lunisolar';

/**
 * KASI's stated range for 음양력변환. Outside it the Gregorian grid is unchanged and the annotation
 * is absent -- never an error, and never a blank cell where a number was.
 */
const AXISMUNDI_CAL_KOREAN_LUNISOLAR_FROM = '-0059-02-13';
const AXISMUNDI_CAL_KOREAN_LUNISOLAR_TO   = '2050-12-31';

/**
 * Store one lunar month, replacing what is there for the same month.
 *
 * @param string              $system System id.
 * @param array<string,mixed> $month  start_date (ISO) or start_absolute_day, lunar_year,
 *                                    lunar_month, leap_month, days.
 * @return int|WP_Error Row id.
 */
function axismundi_cal_lunar_month_save( string $system, array $month ) {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_cal_not_ready', __( 'The calendar tables are not installed.', 'axismundi-calendar' ) );
	}
	$system = strtolower( trim( $system ) );
	if ( '' === $system ) {
		return new WP_Error( 'ax_cal_system_required', __( 'A calendar system is required.', 'axismundi-calendar' ) );
	}

	$start = isset( $month['start_absolute_day'] )
		? (int) $month['start_absolute_day']
		: axismundi_cal_iso_to_absolute_day( (string) ( $month['start_date'] ?? '' ) );
	if ( null === $start ) {
		return new WP_Error( 'ax_cal_start_required', __( 'A lunar month needs the day it starts on.', 'axismundi-calendar' ) );
	}

	$days = (int) ( $month['days'] ?? 0 );
	// 29 or 30, and nothing else. A lunisolar month is one lunation; a row saying 31 is a parse that
	// went wrong upstream, and accepting it would put the error into every date after it.
	if ( 29 !== $days && 30 !== $days ) {
		return new WP_Error( 'ax_cal_days_invalid', __( 'A lunar month runs 29 or 30 days.', 'axismundi-calendar' ) );
	}
	$lunar_month = (int) ( $month['lunar_month'] ?? 0 );
	if ( $lunar_month < 1 || $lunar_month > 12 ) {
		return new WP_Error( 'ax_cal_month_invalid', __( 'A lunar month is 1 to 12.', 'axismundi-calendar' ) );
	}

	$row = array(
		'system'             => $system,
		'start_absolute_day' => $start,
		'lunar_year'         => (int) ( $month['lunar_year'] ?? 0 ),
		'lunar_month'        => $lunar_month,
		'leap_month'         => empty( $month['leap_month'] ) ? 0 : 1,
		'days'               => $days,
		'updated_at'         => current_time( 'mysql', true ),
	);

	$table    = axismundi_cal_lunar_months_table();
	$existing = axismundi_cal_lunar_month_get( $system, (int) $row['lunar_year'], $lunar_month, 1 === $row['leap_month'] );
	if ( is_array( $existing ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $table, $row, array( 'id' => (int) $existing['id'] ) );
		return (int) $existing['id'];
	}
	$row['created_at'] = $row['updated_at'];
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->insert( $table, $row );
	return (int) $wpdb->insert_id;
}

/**
 * One stored month, by what it is rather than by where it falls.
 *
 * @param string $system      System id.
 * @param int    $lunar_year  Lunar year.
 * @param int    $lunar_month Lunar month.
 * @param bool   $leap        Whether this is the leap month of that number.
 * @return array<string,mixed>|null
 */
function axismundi_cal_lunar_month_get( string $system, int $lunar_year, int $lunar_month, bool $leap = false ) : ?array {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_lunar_months_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE system = %s AND lunar_year = %d AND lunar_month = %d AND leap_month = %d",
			strtolower( trim( $system ) ),
			$lunar_year,
			$lunar_month,
			$leap ? 1 : 0
		),
		ARRAY_A
	);
	return is_array( $row ) ? $row : null;
}

/**
 * The stored month containing a day, or null when nobody has materialised it.
 *
 * The candidate is the latest month starting on or before the day, and it still has to contain it.
 * Without that second test a gap in the store would answer with the month before it and report day
 * 400 of a 29-day month, which is worse than saying nothing.
 *
 * @param string $system       System id.
 * @param int    $absolute_day Absolute day.
 * @return array<string,mixed>|null
 */
function axismundi_cal_lunar_month_containing( string $system, int $absolute_day ) : ?array {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_lunar_months_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE system = %s AND start_absolute_day <= %d ORDER BY start_absolute_day DESC LIMIT 1",
			strtolower( trim( $system ) ),
			$absolute_day
		),
		ARRAY_A
	);
	if ( ! is_array( $row ) ) {
		return null;
	}
	return $absolute_day < (int) $row['start_absolute_day'] + (int) $row['days'] ? $row : null;
}

/**
 * What a lunisolar system calls a day, from the store alone.
 *
 * This is subtraction, not interpolation: the month row says which day it began on, so the day of
 * the month is the difference. Nothing here guesses at a month it does not have.
 *
 * @param string $system       System id.
 * @param int    $absolute_day Absolute day.
 * @return array{year:int,month:int,day:int,leapMonth:bool}|null
 */
function axismundi_cal_lunar_date( string $system, int $absolute_day ) : ?array {
	$month = axismundi_cal_lunar_month_containing( $system, $absolute_day );
	if ( null === $month ) {
		return null;
	}
	return array(
		'year'      => (int) $month['lunar_year'],
		'month'     => (int) $month['lunar_month'],
		'day'       => $absolute_day - (int) $month['start_absolute_day'] + 1,
		'leapMonth' => 1 === (int) $month['leap_month'],
	);
}

/**
 * What a lunisolar system calls each day of a range, keyed by ISO date.
 *
 * Keyed on the Gregorian date because that is what the grid already knows: a cell asks the map once
 * with the date it is drawing rather than searching for itself.
 *
 * @param string $system System id.
 * @param string $from   ISO date, inclusive.
 * @param string $to     ISO date, inclusive.
 * @return array<string,array{year:int,month:int,day:int,leapMonth:bool}>
 */
function axismundi_cal_lunar_dates( string $system, string $from, string $to ) : array {
	$start = axismundi_cal_iso_to_absolute_day( $from );
	$end   = axismundi_cal_iso_to_absolute_day( $to );
	if ( null === $start || null === $end || $end < $start ) {
		return array();
	}
	// Bounded, because this is reached from a request argument and a two-century range asked for by
	// hand should return nothing rather than walk two centuries.
	if ( $end - $start > 400 ) {
		return array();
	}

	$out   = array();
	$month = null;
	for ( $day = $start; $day <= $end; $day++ ) {
		// One query per lunar month rather than per day: within a month the answer is arithmetic.
		if ( null === $month || $day >= (int) $month['start_absolute_day'] + (int) $month['days'] || $day < (int) $month['start_absolute_day'] ) {
			$month = axismundi_cal_lunar_month_containing( $system, $day );
		}
		if ( null === $month ) {
			continue;
		}
		$out[ axismundi_cal_absolute_day_to_iso( $day ) ] = array(
			'year'      => (int) $month['lunar_year'],
			'month'     => (int) $month['lunar_month'],
			'day'       => $day - (int) $month['start_absolute_day'] + 1,
			'leapMonth' => 1 === (int) $month['leap_month'],
		);
	}
	return $out;
}

/**
 * Register the Korean lunisolar system.
 *
 * Registered whether or not a single month has been materialised. The system existing and the store
 * being filled are different facts, and a screen has to be able to say "this provider covers 2026
 * and has not been fetched yet" rather than "there is no such calendar".
 *
 * @return void
 */
function axismundi_cal_register_korean_lunisolar() : void {
	axismundi_cal_register_calendar_system(
		AXISMUNDI_CAL_KOREAN_LUNISOLAR,
		array(
			'label'         => __( 'Korean lunar calendar', 'axismundi-calendar' ),
			'coverage_from' => AXISMUNDI_CAL_KOREAN_LUNISOLAR_FROM,
			'coverage_to'   => AXISMUNDI_CAL_KOREAN_LUNISOLAR_TO,
			'resolve'       => static fn( int $absolute_day ) : ?array => axismundi_cal_lunar_date( AXISMUNDI_CAL_KOREAN_LUNISOLAR, $absolute_day ),
		)
	);
}
add_action( 'axismundi_cal_register_calendar_systems', 'axismundi_cal_register_korean_lunisolar' );
