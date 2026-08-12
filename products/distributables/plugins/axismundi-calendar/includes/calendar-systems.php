<?php
/**
 * Calendar systems: other ways of naming a day the Gregorian calendar has already drawn.
 *
 * Nothing here stores a date. Gregorian/ISO stays the canonical storage for every event, and a
 * calendar system is a representation over it -- 2026-08-12 is not two days, it is one day with two
 * names. A system that could be stored in would be a second source of truth for the same fact.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The day 0001-01-01 (proleptic Gregorian) is given, so an `AbsoluteDay` is a positive integer for
 * every date anyone will type and a negative one for the BC end of KASI's range.
 */
const AXISMUNDI_CAL_ABSOLUTE_DAY_EPOCH = '0001-01-01';

/**
 * The integer naming one civil day, counted from 0001-01-01 (proleptic Gregorian) = 1.
 *
 * Deliberately not a Julian Day Number. JDN rolls at noon UT, and a type that carries that
 * convention by name invites somebody to hand an astronomical instant to something that wanted a
 * civil day. A provider that genuinely needs the instant should take a separate UTC value; these two
 * differ by half a day and must not share a type.
 *
 * @param int $year  Proleptic Gregorian year.
 * @param int $month Month, 1-12.
 * @param int $day   Day of month.
 * @return int
 */
function axismundi_cal_absolute_day( int $year, int $month, int $day ) : int {
	$prior = $year - 1;
	// Days before this month, less the correction for a February that has not happened yet or was
	// short. The `367 * m - 362` term is the standard integer form of the month-length prefix sum.
	$correction = 2;
	if ( $month <= 2 ) {
		$correction = 0;
	} elseif ( axismundi_cal_is_leap_year( $year ) ) {
		$correction = 1;
	}
	// Floored, not truncated. `intdiv` rounds towards zero, which is the same thing for the years
	// anybody types and one day out for every year before the epoch -- the half of KASI's range that
	// reaches 59 BC.
	return ( 365 * $prior )
		+ axismundi_cal_floor_div( $prior, 4 )
		- axismundi_cal_floor_div( $prior, 100 )
		+ axismundi_cal_floor_div( $prior, 400 )
		+ intdiv( ( 367 * $month ) - 362, 12 )
		- $correction
		+ $day;
}

/**
 * Whether a proleptic Gregorian year is a leap year.
 *
 * @param int $year Year.
 * @return bool
 */
function axismundi_cal_is_leap_year( int $year ) : bool {
	if ( 0 !== $year % 4 ) {
		return false;
	}
	if ( 0 !== $year % 100 ) {
		return true;
	}
	return 0 === $year % 400;
}

/**
 * The civil date an `AbsoluteDay` names.
 *
 * @param int $absolute_day Absolute day.
 * @return array{year:int,month:int,day:int}
 */
function axismundi_cal_absolute_day_to_date( int $absolute_day ) : array {
	// Which year, found by counting whole Gregorian cycles rather than by searching. `intdiv` floors
	// towards zero in PHP, so the negative side is taken to a floor explicitly -- without it every
	// date before 0001-01-01 lands a year out, which is the half of KASI's range nobody tests.
	$d400 = axismundi_cal_floor_div( $absolute_day - 1, 146097 );
	$rem  = $absolute_day - 1 - ( $d400 * 146097 );
	$d100 = min( intdiv( $rem, 36524 ), 3 );
	$rem -= $d100 * 36524;
	$d4   = intdiv( $rem, 1461 );
	$rem -= $d4 * 1461;
	$d1   = min( intdiv( $rem, 365 ), 3 );

	$year      = ( $d400 * 400 ) + ( $d100 * 100 ) + ( $d4 * 4 ) + $d1 + 1;
	$remaining = $absolute_day - axismundi_cal_absolute_day( $year, 1, 1 );

	$correction = 2;
	if ( $absolute_day < axismundi_cal_absolute_day( $year, 3, 1 ) ) {
		$correction = 0;
	} elseif ( axismundi_cal_is_leap_year( $year ) ) {
		$correction = 1;
	}
	$month = intdiv( ( 12 * ( $remaining + $correction ) ) + 373, 367 );
	$day   = $absolute_day - axismundi_cal_absolute_day( $year, $month, 1 ) + 1;

	return array( 'year' => $year, 'month' => $month, 'day' => $day );
}

/**
 * Integer division that floors in both directions.
 *
 * @param int $numerator   Numerator.
 * @param int $denominator Denominator.
 * @return int
 */
function axismundi_cal_floor_div( int $numerator, int $denominator ) : int {
	$quotient = intdiv( $numerator, $denominator );
	if ( 0 !== $numerator % $denominator && ( $numerator < 0 ) !== ( $denominator < 0 ) ) {
		--$quotient;
	}
	return $quotient;
}

/**
 * The `AbsoluteDay` an ISO civil date names, or null when the string is not one.
 *
 * Civil, so no timezone is applied and none is wanted: 2026-08-12 is the same day everywhere it is
 * written, and converting it through an offset is how an all-day row moves a day for somebody.
 *
 * @param string $date ISO date, `YYYY-MM-DD`, optionally with a leading `-` for BC.
 * @return int|null
 */
function axismundi_cal_iso_to_absolute_day( string $date ) : ?int {
	if ( 1 !== preg_match( '/^(-?\d{1,6})-(\d{2})-(\d{2})$/', trim( $date ), $parts ) ) {
		return null;
	}
	$year  = (int) $parts[1];
	$month = (int) $parts[2];
	$day   = (int) $parts[3];
	if ( $month < 1 || $month > 12 || $day < 1 || $day > 31 ) {
		return null;
	}
	$absolute = axismundi_cal_absolute_day( $year, $month, $day );
	// Round-tripped, because the arithmetic above accepts 2026-02-30 and quietly returns March.
	$back = axismundi_cal_absolute_day_to_date( $absolute );
	if ( $back['year'] !== $year || $back['month'] !== $month || $back['day'] !== $day ) {
		return null;
	}
	return $absolute;
}

/**
 * The ISO civil date an `AbsoluteDay` names.
 *
 * @param int $absolute_day Absolute day.
 * @return string
 */
function axismundi_cal_absolute_day_to_iso( int $absolute_day ) : string {
	$date = axismundi_cal_absolute_day_to_date( $absolute_day );
	$sign = $date['year'] < 0 ? '-' : '';
	return sprintf( '%s%04d-%02d-%02d', $sign, abs( $date['year'] ), $date['month'], $date['day'] );
}

/**
 * The registered calendar systems, by id.
 *
 * @return array<string,array<string,mixed>>
 */
function axismundi_cal_calendar_systems() : array {
	static $asked = false;
	if ( ! $asked ) {
		// Once, and not at load: a provider registering itself may need the store, and the store is
		// not answerable while the plugin is still being required.
		$asked = true;
		/** Register calendar systems by calling `axismundi_cal_register_calendar_system()`. */
		do_action( 'axismundi_cal_register_calendar_systems' );
	}
	return axismundi_cal_calendar_system_registry();
}

/**
 * The registry itself, which registration writes to and lookup reads from.
 *
 * @param array<string,mixed>|null $write Entry to add, keyed by id, or null to read.
 * @return array<string,array<string,mixed>>
 */
function axismundi_cal_calendar_system_registry( ?array $write = null ) : array {
	static $registry = array();
	if ( null !== $write ) {
		$registry = array_merge( $registry, $write );
	}
	return $registry;
}

/**
 * Add a calendar system.
 *
 * `coverage` is a property of the system, not an error condition. A grid outside it draws exactly as
 * it did before and the annotation is simply absent: 2051 is not a failure, it is a year this
 * provider has nothing to say about, and a provider replaced later may cover it.
 *
 * @param string              $id   System id, e.g. `korean-lunisolar`.
 * @param array<string,mixed> $args label, type, authority, icu_calendar, resolve (callable|null),
 *                                  coverage_from, coverage_to (ISO), settings (callable|null).
 * @return void
 */
function axismundi_cal_register_calendar_system( string $id, array $args ) : void {
	$id = strtolower( trim( $id ) );
	if ( '' === $id ) {
		return;
	}
	$from = isset( $args['coverage_from'] ) ? axismundi_cal_iso_to_absolute_day( (string) $args['coverage_from'] ) : null;
	$to   = isset( $args['coverage_to'] ) ? axismundi_cal_iso_to_absolute_day( (string) $args['coverage_to'] ) : null;
	axismundi_cal_calendar_system_registry(
		array(
			$id => array(
				'id'            => $id,
				'label'         => (string) ( $args['label'] ?? $id ),
				// `fn( int $absolute_day ) : ?array{year:int,month:int,day:int,leapMonth:bool}`.
				'resolve'       => isset( $args['resolve'] ) && is_callable( $args['resolve'] ) ? $args['resolve'] : null,
				'coverage_from' => $from,
				'coverage_to'   => $to,
				/*
				 * Lunisolar, lunar or solar. Not decoration: Islamic is lunar and does not intercalate,
				 * which is why Ramadan walks through the seasons, and a taxonomy that called it the same
				 * thing as the Korean calendar would have to be unlearned by whoever added it.
				 */
				'type'          => (string) ( $args['type'] ?? 'other' ),
				// Who decides what a date is. Not the same question as which identifier formats it.
				'authority'     => (string) ( $args['authority'] ?? '' ),
				/*
				 * The Unicode/CLDR calendar this corresponds to, recorded for `Intl` formatting and
				 * BCP 47 interoperability and for nothing else. It is deliberately not the id and never
				 * the source: `dangi` is ICU's own implementation with its own astronomical rules, and
				 * assuming it agrees with the authority above -- year by year, leap month by leap month
				 * -- is exactly the assumption that has never been checked here.
				 */
				'icu_calendar'  => (string) ( $args['icu_calendar'] ?? '' ),
				// `fn() : void`, rendering this provider's own section of the settings screen.
				'settings'      => isset( $args['settings'] ) && is_callable( $args['settings'] ) ? $args['settings'] : null,
			),
		)
	);
}

/**
 * One registered system, or null.
 *
 * @param string $id System id.
 * @return array<string,mixed>|null
 */
function axismundi_cal_calendar_system( string $id ) : ?array {
	$systems = axismundi_cal_calendar_systems();
	return $systems[ strtolower( trim( $id ) ) ] ?? null;
}

/**
 * Whether a system has anything to say about a day.
 *
 * @param string $id           System id.
 * @param int    $absolute_day Absolute day.
 * @return bool
 */
function axismundi_cal_system_covers( string $id, int $absolute_day ) : bool {
	$system = axismundi_cal_calendar_system( $id );
	if ( null === $system ) {
		return false;
	}
	if ( null !== $system['coverage_from'] && $absolute_day < $system['coverage_from'] ) {
		return false;
	}
	if ( null !== $system['coverage_to'] && $absolute_day > $system['coverage_to'] ) {
		return false;
	}
	return true;
}

/**
 * What a system calls a day, or null when it has nothing to say about it.
 *
 * Null is the ordinary answer, not a failure: outside coverage, and inside coverage for a month
 * nobody has materialised yet, the honest reply is that this system has no name for the day.
 *
 * @param string $id           System id.
 * @param int    $absolute_day Absolute day.
 * @return array{year:int,month:int,day:int,leapMonth:bool}|null
 */
function axismundi_cal_system_date( string $id, int $absolute_day ) : ?array {
	$system = axismundi_cal_calendar_system( $id );
	if ( null === $system || null === $system['resolve'] || ! axismundi_cal_system_covers( $id, $absolute_day ) ) {
		return null;
	}
	$resolved = call_user_func( $system['resolve'], $absolute_day );
	return is_array( $resolved ) ? $resolved : null;
}
