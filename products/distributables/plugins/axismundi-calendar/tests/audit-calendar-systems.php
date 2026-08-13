<?php
/**
 * Audit: AbsoluteDay arithmetic, the calendar-system registry, and the lunar month store.
 *
 * No network, and none needed: every calendar system here is computed on this server.
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
ax_cs_assert( $ax_cs_results, 'and who its dates come from', false !== strpos( (string) ( $ax_cs_korean['authority'] ?? '' ), 'ICU' ) );
ax_cs_assert(
	$ax_cs_results,
	'while the Unicode calendar is recorded beside it and is not the identifier',
	'dangi' === ( $ax_cs_korean['icu_calendar'] ?? '' ) && 'korean-lunisolar' === ( $ax_cs_korean['id'] ?? '' )
);
/*
 * Nothing registered today needs configuring, and the seam that lets one say so is asserted where it
 * can be asserted honestly -- on a fixture. Checking the Korean system for a settings callback would
 * only record that it currently has none.
 */
ax_cs_assert(
	$ax_cs_results,
	'a system may render its own settings, for the day one needs a key',
	( static function () : bool {
		axismundi_cal_register_calendar_system( 'audit-configurable', array( 'label' => 'x', 'settings' => '__return_true' ) );
		return is_callable( axismundi_cal_calendar_system( 'audit-configurable' )['settings'] ?? null );
	} )()
);
ax_cs_assert(
	$ax_cs_results,
	'while a system that needs nothing configured says so by having none',
	// `??` treats a stored null as absent, so the key is asked for by name.
	null === axismundi_cal_calendar_system( 'korean-lunisolar' )['settings']
);
ax_cs_assert(
	$ax_cs_results,
	'the Korean calendar answers with nothing fetched, stored or configured',
	( static function () : bool {
		$date = axismundi_cal_system_date( 'korean-lunisolar', (int) axismundi_cal_iso_to_absolute_day( '2026-08-13' ) );
		return is_array( $date ) && 7 === $date['month'] && 1 === $date['day'];
	} )()
);

/*
 * A system with no stated bounds covers every date. That is a claim about the provider -- ICU will
 * answer for any day it is handed -- and not an absence of one, so it is asserted rather than left
 * to be inferred from a missing check.
 */
ax_cs_assert(
	$ax_cs_results,
	'a system that states no bounds covers any day, including ones nobody has a calendar for',
	axismundi_cal_system_covers( 'korean-lunisolar', (int) axismundi_cal_iso_to_absolute_day( '2026-08-12' ) )
		&& axismundi_cal_system_covers( 'korean-lunisolar', (int) axismundi_cal_iso_to_absolute_day( '2051-01-01' ) )
		&& axismundi_cal_system_covers( 'korean-lunisolar', (int) axismundi_cal_iso_to_absolute_day( '-0060-01-01' ) )
);

// -- The calendars ICU answers for -----------------------------------------------------------------

/*
 * No key, no store, no network. These prove the registry was worth building: one provider needs a
 * service key and a materialised store and these need nothing, and neither the screen nor the grid
 * has to know which is which.
 */
if ( class_exists( 'IntlCalendar' ) ) {
	$ax_cs_newyear = (int) axismundi_cal_iso_to_absolute_day( '2026-02-17' );
	$ax_cs_chinese = axismundi_cal_system_date( 'chinese', $ax_cs_newyear );
	ax_cs_assert(
		$ax_cs_results,
		'the Chinese calendar answers without anything having been fetched, and puts 춘절 on the first of its first month',
		is_array( $ax_cs_chinese ) && 1 === $ax_cs_chinese['month'] && 1 === $ax_cs_chinese['day']
	);
	ax_cs_assert(
		$ax_cs_results,
		'and reports a year that counts straight through rather than a place in the 60-year cycle',
		is_array( $ax_cs_chinese ) && $ax_cs_chinese['year'] > 1000
	);
	ax_cs_assert(
		$ax_cs_results,
		'the Hebrew calendar answers too, in its own era',
		( axismundi_cal_system_date( 'hebrew', $ax_cs_newyear )['year'] ?? 0 ) > 5000
	);
	ax_cs_assert(
		$ax_cs_results,
		'the Islamic calendar is registered as lunar, not lunisolar, because it intercalates nothing',
		'lunar' === ( axismundi_cal_calendar_system( 'islamic-umalqura' )['type'] ?? '' )
	);
	ax_cs_assert(
		$ax_cs_results,
		'and its year advances faster than the Gregorian one, which is the whole difference',
		( static function () : bool {
			$a = axismundi_cal_system_date( 'islamic-umalqura', (int) axismundi_cal_iso_to_absolute_day( '1990-01-01' ) );
			$b = axismundi_cal_system_date( 'islamic-umalqura', (int) axismundi_cal_iso_to_absolute_day( '2023-01-01' ) );
			// 33 Gregorian years are about 34 Islamic ones.
			return is_array( $a ) && is_array( $b ) && ( $b['year'] - $a['year'] ) === 34;
		} )()
	);
	ax_cs_assert(
		$ax_cs_results,
		'a calendar ICU does not have is not registered as one that quietly answers in Gregorian',
		null === axismundi_cal_calendar_system( 'vietnamese' ) && null === axismundi_cal_icu_date( 'nonesuch', 739000 )
	);
}

// -- A system that answers for part of a range ----------------------------------------------------

/*
 * A registered system whose resolver declines is the ordinary case, not a broken one: a provider can
 * cover a span and still have nothing to say about a day in it. Asserted with a closure rather than
 * a real provider, so the claim is about the seam and not about anybody's data.
 */
axismundi_cal_register_calendar_system(
	$ax_cs_system,
	array(
		'label'         => 'Audit calendar',
		'type'          => 'lunisolar',
		'coverage_from' => '2026-06-15',
		'coverage_to'   => '2026-12-31',
		'resolve'       => static function ( int $day ) : ?array {
			return $day === (int) axismundi_cal_iso_to_absolute_day( '2026-07-15' )
				? array( 'year' => 2026, 'month' => 4, 'day' => 1, 'leapMonth' => true )
				: null;
		},
	)
);
ax_cs_assert(
	$ax_cs_results,
	'a system resolves a day inside its coverage',
	array( 'year' => 2026, 'month' => 4, 'day' => 1, 'leapMonth' => true )
		=== axismundi_cal_system_date( $ax_cs_system, (int) axismundi_cal_iso_to_absolute_day( '2026-07-15' ) )
);
ax_cs_assert(
	$ax_cs_results,
	'a system can cover a day it has no answer for, and says nothing rather than not existing',
	axismundi_cal_system_covers( $ax_cs_system, (int) axismundi_cal_iso_to_absolute_day( '2026-11-01' ) )
		&& null === axismundi_cal_system_date( $ax_cs_system, (int) axismundi_cal_iso_to_absolute_day( '2026-11-01' ) )
);
ax_cs_assert(
	$ax_cs_results,
	'and outside its coverage the resolver is not asked at all',
	null === axismundi_cal_system_date( $ax_cs_system, (int) axismundi_cal_iso_to_absolute_day( '2026-06-14' ) )
);

// -- Showing a second date -------------------------------------------------------------------------

/*
 * A display preference, kept out of the membership model. Nobody grants it and nobody revokes it,
 * which is the whole reason it is not a CalendarList entry.
 */
$ax_cs_user = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
$ax_cs_kept = get_user_meta( $ax_cs_user, AXISMUNDI_CAL_SECONDARY_META, true );
wp_set_current_user( $ax_cs_user );

ax_cs_assert(
	$ax_cs_results,
	'a system nobody registered cannot be stored as a preference',
	array( 'chinese' ) === axismundi_cal_secondary_systems_set( array( 'chinese', 'martian', 'chinese' ) )
);
/*
 * The sidebar offers one at a time -- two second dates under a number is three numbers in a cell --
 * but the preference is still a list. A control deciding what the model may say would mean widening
 * storage again the first time a year view has room for two.
 */
ax_cs_assert(
	$ax_cs_results,
	'choosing none stores none, rather than leaving the last choice behind',
	array() === axismundi_cal_secondary_systems_set( array() ) && array() === axismundi_cal_secondary_systems()
);
ax_cs_assert(
	$ax_cs_results,
	'and the store still holds more than one, because the limit is the cell and not the model',
	array( 'chinese', 'hebrew' ) === axismundi_cal_secondary_systems_set( array( 'chinese', 'hebrew' ) )
);
ax_cs_assert(
	$ax_cs_results,
	'and a preference naming a system that has gone away reads as off rather than as an error',
	( static function () use ( $ax_cs_user ) : bool {
		update_user_meta( $ax_cs_user, AXISMUNDI_CAL_SECONDARY_META, array( 'chinese', 'was-registered-once' ) );
		return array( 'chinese' ) === axismundi_cal_secondary_systems();
	} )()
);

// The month on the first of the month, and only there. Every other day is its number alone.
ax_cs_assert(
	$ax_cs_results,
	'the first of a month says which month it is',
	'7.1' === axismundi_cal_secondary_label( array( 'year' => 2026, 'month' => 7, 'day' => 1, 'leapMonth' => false ) )
);
ax_cs_assert(
	$ax_cs_results,
	'a leap month says so, because otherwise two different months read identically',
	axismundi_cal_secondary_label( array( 'year' => 2026, 'month' => 7, 'day' => 1, 'leapMonth' => true ) )
		!== axismundi_cal_secondary_label( array( 'year' => 2026, 'month' => 7, 'day' => 1, 'leapMonth' => false ) )
);
ax_cs_assert(
	$ax_cs_results,
	'and every other day is just a number, so the month stands out on the day it changes',
	'2' === axismundi_cal_secondary_label( array( 'year' => 2026, 'month' => 7, 'day' => 2, 'leapMonth' => false ) )
);

$ax_cs_req = new WP_REST_Request( 'PUT', '/axismundi/v1/actors/me/secondaryCalendars' );
$ax_cs_req->set_param( 'systems', array( 'chinese' ) );
$ax_cs_req->set_param( 'start', '2026-08-01' );
$ax_cs_req->set_param( 'end', '2026-08-05' );
$ax_cs_body = (array) rest_do_request( $ax_cs_req )->get_data();
ax_cs_assert(
	$ax_cs_results,
	'setting the preference answers with the dates for the month in the same round trip',
	isset( $ax_cs_body['dates']['chinese']['2026-08-01'] ) && 5 === count( $ax_cs_body['dates']['chinese'] )
);
ax_cs_assert(
	$ax_cs_results,
	'and offers every registered system, not only the ones turned on',
	count( (array) $ax_cs_body['available'] ) >= 3
);
ax_cs_assert(
	$ax_cs_results,
	'a system with nothing to say about a range is absent rather than present and empty',
	! isset( $ax_cs_body['dates']['hebrew'] )
);

wp_set_current_user( 0 );
ax_cs_assert(
	$ax_cs_results,
	'and there is no anonymous answer, because a preference belongs to somebody',
	401 === rest_do_request( new WP_REST_Request( 'GET', '/axismundi/v1/actors/me/secondaryCalendars' ) )->get_status()
);
if ( is_array( $ax_cs_kept ) ) {
	update_user_meta( $ax_cs_user, AXISMUNDI_CAL_SECONDARY_META, $ax_cs_kept );
} else {
	delete_user_meta( $ax_cs_user, AXISMUNDI_CAL_SECONDARY_META );
}

$ax_cs_failed = count( array_filter( $ax_cs_results, static fn( array $r ) : bool => ! $r[0] ) );
printf( "== %d checks, %d failed ==\n", count( $ax_cs_results ), $ax_cs_failed );
