<?php
/**
 * Audit: the KASI client's two jobs -- keeping the key, and reading the answer.
 *
 * The fetch itself is not exercised here. What can be proved without spending somebody's quota is
 * everything that decides whether a fetch was understood: the key survives storage and never appears
 * in the clear, a response becomes lunar months, and a response that is not one is refused rather
 * than half-read.
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
function ax_kasi_assert( array &$results, string $label, bool $ok ) : void {
	$results[] = array( $ok, $label );
	if ( ! $ok ) {
		echo "[FAIL] {$label}\n";
	}
}

$ax_kasi_results = array();
$ax_kasi_before  = get_option( AXISMUNDI_CAL_KASI_KEY_OPTION, null );

// -- The key -------------------------------------------------------------------------------------

// Shaped like a portal key: base64 with the `+` and `=` percent-encoded, which is the form that
// breaks if anything encodes it a second time.
$ax_kasi_key = 'HcApL3Snff4qtU1p9CyJZQEP5xZrJfqWZuo8ChPSIcn%2BX1eD6Gieej%2BbNkKWZUZq%3D%3D';

ax_kasi_assert( $ax_kasi_results, 'a key stores', true === axismundi_cal_kasi_key_set( $ax_kasi_key ) );
ax_kasi_assert( $ax_kasi_results, 'and reads back exactly as it was typed', axismundi_cal_kasi_key() === $ax_kasi_key );
ax_kasi_assert(
	$ax_kasi_results,
	'while what sits in the database is not the key',
	false === strpos( (string) get_option( AXISMUNDI_CAL_KASI_KEY_OPTION, '' ), 'HcApL3' )
);
ax_kasi_assert(
	$ax_kasi_results,
	'and is not a reversible rearrangement of it either',
	base64_decode( (string) get_option( AXISMUNDI_CAL_KASI_KEY_OPTION, '' ), true ) !== $ax_kasi_key
);
ax_kasi_assert(
	$ax_kasi_results,
	'ciphertext differs between two writes of the same key, so equal keys are not visibly equal',
	( static function () use ( $ax_kasi_key ) : bool {
		axismundi_cal_kasi_key_set( $ax_kasi_key );
		$first = (string) get_option( AXISMUNDI_CAL_KASI_KEY_OPTION, '' );
		axismundi_cal_kasi_key_set( $ax_kasi_key );
		return $first !== (string) get_option( AXISMUNDI_CAL_KASI_KEY_OPTION, '' );
	} )()
);
ax_kasi_assert(
	$ax_kasi_results,
	'a key that has been tampered with reads as no key rather than as a corrupt one',
	( static function () : bool {
		update_option( AXISMUNDI_CAL_KASI_KEY_OPTION, base64_encode( 'not a box' ), false );
		return '' === axismundi_cal_kasi_key();
	} )()
);
axismundi_cal_kasi_key_set( '' );
ax_kasi_assert( $ax_kasi_results, 'and clearing it removes it', '' === axismundi_cal_kasi_key() && false === get_option( AXISMUNDI_CAL_KASI_KEY_OPTION, false ) );
ax_kasi_assert(
	$ax_kasi_results,
	'a month cannot be fetched without one',
	is_wp_error( axismundi_cal_kasi_fetch_month( 2026, 8 ) ) && 'ax_cal_kasi_no_key' === axismundi_cal_kasi_fetch_month( 2026, 8 )->get_error_code()
);

axismundi_cal_kasi_key_set( $ax_kasi_key );
ax_kasi_assert(
	$ax_kasi_results,
	'a year past what the provider covers is refused here rather than spent on a request',
	is_wp_error( axismundi_cal_kasi_fetch_month( 2051, 1 ) ) && 'ax_cal_kasi_coverage' === axismundi_cal_kasi_fetch_month( 2051, 1 )->get_error_code()
);
ax_kasi_assert(
	$ax_kasi_results,
	'and so is a month that is not a month',
	is_wp_error( axismundi_cal_kasi_fetch_month( 2026, 13 ) )
);

/*
 * A real pair, taken from a live response: KASI says 2026-08-02 is Julian day 2461255. The offset
 * between that and this plugin's AbsoluteDay has to be the constant relating the two epochs, and
 * checking it here means the day numbering is confirmed against an outside authority rather than
 * against the inverse of itself.
 */
ax_kasi_assert(
	$ax_kasi_results,
	'AbsoluteDay agrees with the Julian day the service reports for the same date',
	2461255 - (int) axismundi_cal_iso_to_absolute_day( '2026-08-02' ) === 1721425
);
ax_kasi_assert(
	$ax_kasi_results,
	'and the same offset holds a century away, so the two calendars are not merely aligned at one point',
	2425461 - (int) axismundi_cal_iso_to_absolute_day( '1928-08-02' ) === 1721425
);

// -- Reading an answer ---------------------------------------------------------------------------

/**
 * One day row in the shape the service returns.
 *
 * @param string $sol  Gregorian date.
 * @param int    $ly   Lunar year.
 * @param int    $lm   Lunar month.
 * @param int    $ld   Lunar day.
 * @param bool   $leap Leap month.
 * @param int    $len  Days in the lunar month.
 * @return string
 */
function ax_kasi_item( string $sol, int $ly, int $lm, int $ld, bool $leap, int $len ) : string {
	list( $y, $m, $d ) = explode( '-', $sol );
	return "<item><lunYear>{$ly}</lunYear><lunMonth>" . sprintf( '%02d', $lm ) . "</lunMonth><lunDay>" . sprintf( '%02d', $ld ) . '</lunDay>'
		. '<lunLeapmonth>' . ( $leap ? '윤' : '평' ) . "</lunLeapmonth><lunNday>{$len}</lunNday>"
		. "<solYear>{$y}</solYear><solMonth>{$m}</solMonth><solDay>{$d}</solDay></item>";
}

/**
 * A whole response.
 *
 * @param string $items Item XML.
 * @param string $code  Result code.
 * @return string
 */
function ax_kasi_body( string $items, string $code = '00' ) : string {
	return '<?xml version="1.0" encoding="UTF-8"?><response><header><resultCode>' . $code . '</resultCode>'
		. '<resultMsg>' . ( '00' === $code ? 'NORMAL SERVICE.' : 'SERVICE KEY IS NOT REGISTERED ERROR.' ) . '</resultMsg></header>'
		. '<body><items>' . $items . '</items></body></response>';
}

/*
 * A Gregorian month straddling two lunar months, one of them a leap month. Every day carries its own
 * month's identity and length, so both are fully described by any day belonging to them -- which is
 * the whole reason a month is one request rather than thirty.
 */
$ax_kasi_items = ax_kasi_item( '2026-07-13', 2026, 4, 28, false, 30 )
	. ax_kasi_item( '2026-07-14', 2026, 4, 29, false, 30 )
	. ax_kasi_item( '2026-07-15', 2026, 4, 1, true, 29 )
	. ax_kasi_item( '2026-07-16', 2026, 4, 2, true, 29 );

$ax_kasi_parsed = axismundi_cal_kasi_parse( ax_kasi_body( $ax_kasi_items ) );
ax_kasi_assert( $ax_kasi_results, 'a response reads as its day rows', is_array( $ax_kasi_parsed ) && 4 === count( $ax_kasi_parsed ) );

$ax_kasi_months = axismundi_cal_kasi_months( is_array( $ax_kasi_parsed ) ? $ax_kasi_parsed : array() );
ax_kasi_assert( $ax_kasi_results, 'four days describe the two lunar months they belong to, and not four', 2 === count( $ax_kasi_months ) );

$ax_kasi_plain = $ax_kasi_months['2026:04:0'] ?? array();
$ax_kasi_leap  = $ax_kasi_months['2026:04:1'] ?? array();
ax_kasi_assert(
	$ax_kasi_results,
	'a month is dated from any of its days, not only from its first',
	( $ax_kasi_plain['start_absolute_day'] ?? 0 ) === axismundi_cal_iso_to_absolute_day( '2026-06-16' )
);
ax_kasi_assert(
	$ax_kasi_results,
	'and the leap month is kept apart from the month it repeats',
	true === ( $ax_kasi_leap['leap_month'] ?? null ) && false === ( $ax_kasi_plain['leap_month'] ?? null )
		&& ( $ax_kasi_leap['start_absolute_day'] ?? 0 ) === axismundi_cal_iso_to_absolute_day( '2026-07-15' )
);
ax_kasi_assert( $ax_kasi_results, 'with the length the service gave', 30 === ( $ax_kasi_plain['days'] ?? 0 ) && 29 === ( $ax_kasi_leap['days'] ?? 0 ) );

$ax_kasi_error = axismundi_cal_kasi_parse( ax_kasi_body( '', '30' ) );
ax_kasi_assert(
	$ax_kasi_results,
	'a service refusal is the answer, not an empty month',
	is_wp_error( $ax_kasi_error ) && 'ax_cal_kasi_service' === $ax_kasi_error->get_error_code()
);
ax_kasi_assert( $ax_kasi_results, 'and a body that is not XML is refused', is_wp_error( axismundi_cal_kasi_parse( 'Service Key is not registered' ) ) );
ax_kasi_assert( $ax_kasi_results, 'as is an empty one', is_wp_error( axismundi_cal_kasi_parse( '' ) ) );
ax_kasi_assert( $ax_kasi_results, 'and a well-formed response with no days in it', is_wp_error( axismundi_cal_kasi_parse( ax_kasi_body( '' ) ) ) );

/*
 * A row missing the length cannot say how long its month ran, and a month of unknown length would
 * make every date after it wrong rather than absent. It is dropped; its neighbours are not.
 */
$ax_kasi_partial = axismundi_cal_kasi_months(
	(array) axismundi_cal_kasi_parse(
		ax_kasi_body(
			'<item><lunYear>2026</lunYear><lunMonth>06</lunMonth><lunDay>01</lunDay><lunLeapmonth>평</lunLeapmonth><solYear>2026</solYear><solMonth>09</solMonth><solDay>11</solDay></item>'
			. ax_kasi_item( '2026-09-12', 2026, 7, 1, false, 30 )
		)
	)
);
ax_kasi_assert( $ax_kasi_results, 'a day that cannot say how long its month is drops, and the rest of the response survives', 1 === count( $ax_kasi_partial ) );

// -- All the way into the store ------------------------------------------------------------------

global $wpdb;
$ax_kasi_table = axismundi_cal_lunar_months_table();
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$ax_kasi_table} WHERE system = %s", AXISMUNDI_CAL_KOREAN_LUNISOLAR ) );

foreach ( $ax_kasi_months as $ax_kasi_month ) {
	axismundi_cal_lunar_month_save( AXISMUNDI_CAL_KOREAN_LUNISOLAR, $ax_kasi_month );
}
/*
 * Asserted against the store rather than through the registered system. The workspace annotation now
 * resolves `korean-lunisolar` through ICU, so asking the system would answer whether ICU works and
 * say nothing about whether the response was parsed -- which is the only thing this file is for.
 */
ax_kasi_assert(
	$ax_kasi_results,
	'a parsed month becomes a day the store can name',
	array( 'year' => 2026, 'month' => 4, 'day' => 1, 'leapMonth' => true )
		=== axismundi_cal_lunar_date( AXISMUNDI_CAL_KOREAN_LUNISOLAR, (int) axismundi_cal_iso_to_absolute_day( '2026-07-15' ) )
);
ax_kasi_assert(
	$ax_kasi_results,
	'while a day nothing was fetched for is absent from it',
	null === axismundi_cal_lunar_date( AXISMUNDI_CAL_KOREAN_LUNISOLAR, (int) axismundi_cal_iso_to_absolute_day( '2026-12-25' ) )
);

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$ax_kasi_table} WHERE system = %s", AXISMUNDI_CAL_KOREAN_LUNISOLAR ) );
if ( null === $ax_kasi_before ) {
	delete_option( AXISMUNDI_CAL_KASI_KEY_OPTION );
} else {
	update_option( AXISMUNDI_CAL_KASI_KEY_OPTION, $ax_kasi_before, false );
}

$ax_kasi_failed = count( array_filter( $ax_kasi_results, static fn( array $r ) : bool => ! $r[0] ) );
printf( "== %d checks, %d failed ==\n", count( $ax_kasi_results ), $ax_kasi_failed );
