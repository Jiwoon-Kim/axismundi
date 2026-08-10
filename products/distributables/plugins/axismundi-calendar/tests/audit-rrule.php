<?php
/**
 * The recurrence rule validator (dev-only; dist-excluded).
 *
 * Two properties matter here and neither is visible from a rule that merely saved:
 *
 * 1. Everything stored can actually be expanded. A rule accepted but not evaluable produces a
 *    series whose dates are invented, which looks like a working calendar.
 * 2. Rules that mean the same thing store the same. Normalization is what lets change detection
 *    compare meaning instead of spelling; without it every re-import looks like an edit, `SEQUENCE`
 *    climbs on its own, and every subscriber re-syncs a calendar that never moved.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_rr_results = array();

/** @param bool[] $results Results. */
function ax_rr_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Accepted, and stored as the given normalized form. */
function ax_rr_ok( array &$results, string $label, string $input, string $expected, bool $floating = false ) : void {
	$actual = axismundi_cal_rrule_validate( $input, $floating );
	ax_rr_assert( $results, $label, ! is_wp_error( $actual ) && $expected === $actual );
	if ( is_wp_error( $actual ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "        refused: %s\n", $actual->get_error_message() );
	} elseif ( $expected !== $actual ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "        expected %s, got %s\n", $expected, $actual );
	}
}

/** Refused, with the expected reason. */
function ax_rr_no( array &$results, string $label, string $input, string $code, bool $floating = false ) : void {
	$actual = axismundi_cal_rrule_validate( $input, $floating );
	ax_rr_assert( $results, $label, is_wp_error( $actual ) && $code === $actual->get_error_code() );
}

// -- The rules this calendar promises to expand -------------------------------------------

ax_rr_ok( $ax_rr_results, 'a weekly rule on one weekday', 'FREQ=WEEKLY;BYDAY=SA', 'FREQ=WEEKLY;BYDAY=SA' );
ax_rr_ok( $ax_rr_results, 'the first Saturday of the month, which needs an ordinal BYDAY', 'FREQ=MONTHLY;BYDAY=1SA', 'FREQ=MONTHLY;BYDAY=1SA' );
ax_rr_ok( $ax_rr_results, 'the last Friday of the month', 'FREQ=MONTHLY;BYDAY=-1FR', 'FREQ=MONTHLY;BYDAY=-1FR' );
ax_rr_ok( $ax_rr_results, 'a day of the month', 'FREQ=MONTHLY;BYMONTHDAY=15', 'FREQ=MONTHLY;BYMONTHDAY=15' );
ax_rr_ok( $ax_rr_results, 'an annual date', 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=3', 'FREQ=YEARLY;BYMONTH=10;BYMONTHDAY=3' );
ax_rr_ok( $ax_rr_results, 'a fortnightly rule', 'FREQ=WEEKLY;INTERVAL=2;BYDAY=TU', 'FREQ=WEEKLY;INTERVAL=2;BYDAY=TU' );
ax_rr_ok( $ax_rr_results, 'a bounded run by count', 'FREQ=DAILY;COUNT=10', 'FREQ=DAILY;COUNT=10' );
ax_rr_ok( $ax_rr_results, 'a bounded run by date', 'FREQ=DAILY;UNTIL=20261231T235959Z', 'FREQ=DAILY;UNTIL=20261231T235959Z' );
ax_rr_ok( $ax_rr_results, 'a non-default week start is kept, because it changes which dates an INTERVAL rule produces', 'FREQ=WEEKLY;INTERVAL=2;BYDAY=SU;WKST=SU', 'FREQ=WEEKLY;INTERVAL=2;BYDAY=SU;WKST=SU' );

// -- Normalization: same meaning, same stored form ------------------------------------------

ax_rr_ok( $ax_rr_results, 'part order does not change what is stored', 'BYDAY=SA;FREQ=WEEKLY', 'FREQ=WEEKLY;BYDAY=SA' );
ax_rr_ok( $ax_rr_results, 'INTERVAL=1 is dropped, since that is what its absence means', 'FREQ=WEEKLY;INTERVAL=1;BYDAY=SA', 'FREQ=WEEKLY;BYDAY=SA' );
ax_rr_ok( $ax_rr_results, 'WKST=MO is dropped for the same reason', 'FREQ=WEEKLY;BYDAY=SA;WKST=MO', 'FREQ=WEEKLY;BYDAY=SA' );
ax_rr_ok( $ax_rr_results, 'lower case is a spelling, not a difference', 'freq=weekly;byday=sa', 'FREQ=WEEKLY;BYDAY=SA' );
ax_rr_ok( $ax_rr_results, 'the RRULE: prefix is accepted and not stored', 'RRULE:FREQ=WEEKLY;BYDAY=SA', 'FREQ=WEEKLY;BYDAY=SA' );
ax_rr_ok( $ax_rr_results, 'weekdays are ordered by the week, not by the order typed', 'FREQ=WEEKLY;BYDAY=SA,MO,WE', 'FREQ=WEEKLY;BYDAY=MO,WE,SA' );
ax_rr_ok( $ax_rr_results, 'a repeated weekday is stored once', 'FREQ=WEEKLY;BYDAY=SA,SA', 'FREQ=WEEKLY;BYDAY=SA' );
ax_rr_ok( $ax_rr_results, 'days from the end of the month sort after days from the start', 'FREQ=MONTHLY;BYMONTHDAY=-1,15', 'FREQ=MONTHLY;BYMONTHDAY=15,-1' );

$ax_rr_a = axismundi_cal_rrule_validate( 'FREQ=WEEKLY;BYDAY=SA;INTERVAL=1' );
$ax_rr_b = axismundi_cal_rrule_validate( 'INTERVAL=1;BYDAY=SA;FREQ=WEEKLY' );
ax_rr_assert( $ax_rr_results, 'two spellings of one recurrence compare equal, so a re-import is not read as an edit', $ax_rr_a === $ax_rr_b && ! is_wp_error( $ax_rr_a ) );

// -- Refusals: named, so an author can act on them -------------------------------------------

ax_rr_no( $ax_rr_results, 'BYSETPOS is refused rather than stored and mis-expanded', 'FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1', 'ax_cal_rrule_unsupported' );
ax_rr_no( $ax_rr_results, 'BYWEEKNO is refused', 'FREQ=YEARLY;BYWEEKNO=20', 'ax_cal_rrule_unsupported' );
ax_rr_no( $ax_rr_results, 'BYYEARDAY is refused', 'FREQ=YEARLY;BYYEARDAY=100', 'ax_cal_rrule_unsupported' );
ax_rr_no( $ax_rr_results, 'sub-day parts are refused', 'FREQ=DAILY;BYHOUR=9', 'ax_cal_rrule_unsupported' );
ax_rr_no( $ax_rr_results, 'COUNT and UNTIL together are refused, because the two readings disagree', 'FREQ=DAILY;COUNT=5;UNTIL=20261231T000000Z', 'ax_cal_rrule_bounds' );
ax_rr_no( $ax_rr_results, 'a missing FREQ is refused', 'BYDAY=SA', 'ax_cal_rrule_freq' );
ax_rr_no( $ax_rr_results, 'an unknown frequency is refused', 'FREQ=FORTNIGHTLY', 'ax_cal_rrule_freq' );
ax_rr_no( $ax_rr_results, 'an ordinal weekday in a weekly rule is refused instead of guessed', 'FREQ=WEEKLY;BYDAY=1SA', 'ax_cal_rrule_byday_ordinal' );
ax_rr_no( $ax_rr_results, 'INTERVAL=0 is refused', 'FREQ=DAILY;INTERVAL=0', 'ax_cal_rrule_positive' );
ax_rr_no( $ax_rr_results, 'BYMONTHDAY=0 is refused, since there is no zeroth day', 'FREQ=MONTHLY;BYMONTHDAY=0', 'ax_cal_rrule_range' );
ax_rr_no( $ax_rr_results, 'BYMONTH=13 is refused', 'FREQ=YEARLY;BYMONTH=13', 'ax_cal_rrule_range' );
ax_rr_no( $ax_rr_results, 'a nonsense weekday is refused', 'FREQ=WEEKLY;BYDAY=XX', 'ax_cal_rrule_byday' );
ax_rr_no( $ax_rr_results, 'a repeated part is refused rather than one silently winning', 'FREQ=DAILY;COUNT=2;COUNT=3', 'ax_cal_rrule_duplicate' );
ax_rr_no( $ax_rr_results, 'a fragment that is not NAME=VALUE is refused', 'FREQ=DAILY;JUNK', 'ax_cal_rrule_malformed' );
ax_rr_no( $ax_rr_results, 'an empty rule is refused', '', 'ax_cal_rrule_empty' );

// -- UNTIL and the zone -----------------------------------------------------------------------

ax_rr_no( $ax_rr_results, 'a local UNTIL against a zoned start is refused, since it would end the series at a different instant for every reader', 'FREQ=DAILY;UNTIL=20261231T235959', 'ax_cal_rrule_until_utc' );
ax_rr_ok( $ax_rr_results, 'and is accepted for a floating start, where local time is the whole point', 'FREQ=DAILY;UNTIL=20261231T235959', 'FREQ=DAILY;UNTIL=20261231T235959', true );
ax_rr_ok( $ax_rr_results, 'a date-only UNTIL needs no zone either way', 'FREQ=DAILY;UNTIL=20261231', 'FREQ=DAILY;UNTIL=20261231' );

$ax_rr_failures = count( array_filter( $ax_rr_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_rr_results ), $ax_rr_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_rr_failures > 0 ? 1 : 0 );
}
exit( $ax_rr_failures > 0 ? 1 : 0 );
