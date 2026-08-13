<?php
/**
 * Moon phase computation and materialization (dev-only; dist-excluded).
 *
 * An astronomical routine fails quietly. Every number it returns looks like a plausible date, so a
 * transposed coefficient or a missing term produces a calendar that is confidently wrong and that
 * nothing downstream can notice. The only useful check is against instants established elsewhere.
 *
 * Two independent kinds of anchor, deliberately. Meeus's own worked example fixes the algorithm in
 * Dynamical Time, with no timezone or ΔT between the answer and the arithmetic. Published phase
 * times fix the whole path -- the corrections, the ΔT estimate and the Julian Day conversion -- as a
 * reader's clock would see it. Either alone would pass while the other half was broken.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_mp_results   = array();
$ax_mp_calendars = array();

/** @param bool[] $results Results. */
function ax_mp_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Seconds between two UTC timestamp strings. */
function ax_mp_gap( string $a, string $b ) : int {
	return (int) abs( strtotime( $a . ' UTC' ) - strtotime( $b . ' UTC' ) );
}

/** The computed instant of one phase in one year, or '' when it is not there. */
function ax_mp_find( int $year, string $phase, string $date ) : string {
	foreach ( axismundi_cal_moon_phases_in_year( $year ) as $found ) {
		if ( $found['phase'] === $phase && str_starts_with( $found['start_utc'], $date ) ) {
			return (string) $found['start_utc'];
		}
	}
	return '';
}

try {
	// -- The algorithm, in Dynamical Time --------------------------------------------------------------

	/*
	 * Meeus, chapter 49, example 49.b: the Last Quarter of 2044 January, k = 544.75. Chosen over the
	 * New Moon example because it exercises the parts the other one cannot -- the quarter correction
	 * table and the W term that is added for a first quarter and subtracted for a last. Dropping W
	 * puts both quarters out by about seven minutes in opposite directions, which reads like rounding
	 * rather than a missing term.
	 *
	 * Asserted in JDE, so nothing about timezones, ΔT or the calendar conversion is between the book's
	 * number and this one.
	 */
	$ax_mp_jde = axismundi_cal_moon_phase_jde( 544.75 );
	ax_mp_assert(
		$ax_mp_results,
		'the last quarter of 2044 January lands where Meeus computes it, to under a second',
		abs( $ax_mp_jde - 2467636.49186 ) < 0.00002
	);

	/*
	 * The mean phase is not the phase. Meeus's series begins at the uncorrected value and the
	 * corrections reach a third of a day, so a run that silently skipped the correction table would
	 * still return a date near the right one -- which is why the distance from the mean is asserted to
	 * be large rather than merely nonzero.
	 */
	$ax_mp_mean = 2451550.09766 + ( AXISMUNDI_CAL_SYNODIC_MONTH * 544.75 );
	ax_mp_assert(
		$ax_mp_results,
		'and is far from the mean phase it starts from, so the corrections are demonstrably applied',
		abs( $ax_mp_jde - $ax_mp_mean ) > 0.05
	);

	// -- The whole path, as a clock shows it -----------------------------------------------------------

	/*
	 * Published instants, which exercise ΔT and the Julian Day conversion as well as the series. Two
	 * minutes of tolerance because ΔT going forward is a prediction rather than a measurement -- the
	 * 2005-2050 polynomial is some five seconds long for the present decade, and that error grows.
	 */
	$ax_mp_anchors = array(
		array( 2025, 'FULL-MOON', '2025-01-13', '2025-01-13 22:27:00' ),
		array( 2025, 'NEW-MOON', '2025-01-29', '2025-01-29 12:36:00' ),
		array( 2025, 'FULL-MOON', '2025-02-12', '2025-02-12 13:53:00' ),
		array( 2025, 'FIRST-QUARTER', '2025-01-06', '2025-01-06 23:56:00' ),
	);
	foreach ( $ax_mp_anchors as $ax_mp_anchor ) {
		list( $ax_mp_year, $ax_mp_phase, $ax_mp_date, $ax_mp_expected ) = $ax_mp_anchor;
		$ax_mp_got = ax_mp_find( $ax_mp_year, $ax_mp_phase, $ax_mp_date );
		ax_mp_assert(
			$ax_mp_results,
			sprintf( 'the %s of %s is computed within two minutes of its published time', strtolower( str_replace( '-', ' ', $ax_mp_phase ) ), $ax_mp_date ),
			'' !== $ax_mp_got && ax_mp_gap( $ax_mp_got, $ax_mp_expected ) < 120
		);
	}

	// -- The shape of a year ---------------------------------------------------------------------------

	$ax_mp_year_2025 = axismundi_cal_moon_phases_in_year( 2025 );
	ax_mp_assert(
		$ax_mp_results,
		'a year holds twelve or thirteen lunations rather than a fixed count, and the walk finds all of them',
		count( $ax_mp_year_2025 ) >= 48 && count( $ax_mp_year_2025 ) <= 53
	);
	ax_mp_assert(
		$ax_mp_results,
		'every one of them falls inside the year it was asked for',
		count( array_filter( $ax_mp_year_2025, static fn( array $p ) : bool => ! str_starts_with( (string) $p['start_utc'], '2025-' ) ) ) === 0
	);
	ax_mp_assert(
		$ax_mp_results,
		'and they come out in order, since the grid and the feed both present them as given',
		( static function () use ( $ax_mp_year_2025 ) : bool {
			$previous = '';
			foreach ( $ax_mp_year_2025 as $phase ) {
				if ( '' !== $previous && (string) $phase['start_utc'] <= $previous ) {
					return false;
				}
				$previous = (string) $phase['start_utc'];
			}
			return true;
		} )()
	);
	/*
	 * The cycle, which is what catches a phase labelled with the wrong quarter. A table that named
	 * every instant correctly but mapped k to the wrong member would still be monotonic and still be
	 * the right length.
	 */
	ax_mp_assert(
		$ax_mp_results,
		'the four phases follow one another in the order a lunation visits them',
		( static function () use ( $ax_mp_year_2025 ) : bool {
			$order = array_values( AXISMUNDI_CAL_MOON_PHASES );
			$at    = array_search( (string) $ax_mp_year_2025[0]['phase'], $order, true );
			foreach ( $ax_mp_year_2025 as $phase ) {
				if ( $order[ $at % 4 ] !== (string) $phase['phase'] ) {
					return false;
				}
				++$at;
			}
			return true;
		} )()
	);
	/*
	 * No Julian Day crosses this boundary. It counts from noon and it is a float, and both are ways
	 * for a date to move half a day inside a function that never knew it was handling one.
	 */
	ax_mp_assert(
		$ax_mp_results,
		'nothing but a UTC string leaves the computation',
		count( array_filter( $ax_mp_year_2025, static fn( array $p ) : bool => ! is_string( $p['start_utc'] ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $p['start_utc'] ) ) ) === 0
	);

	// -- Materializing ---------------------------------------------------------------------------------

	$ax_mp_suffix   = (string) wp_rand( 1000, 9999 );
	$ax_mp_calendar = (int) axismundi_cal_calendar_save(
		array(
			'name'            => 'Moon phases',
			'slug'            => 'ax-mp-' . $ax_mp_suffix,
			'timezone'        => 'UTC',
			'kind'            => 'system',
			'system_provider' => 'astronomy',
		)
	);
	$ax_mp_calendars[] = $ax_mp_calendar;
	ax_mp_assert( $ax_mp_results, 'an astronomy calendar exists to write onto', $ax_mp_calendar > 0 );

	$ax_mp_written = axismundi_cal_generate_moon_phases( $ax_mp_calendar, 2025, 2025 );
	ax_mp_assert( $ax_mp_results, 'a year of phases materializes', is_int( $ax_mp_written ) && $ax_mp_written === count( $ax_mp_year_2025 ) );

	$ax_mp_rows = axismundi_cal_system_items_in_range( $ax_mp_calendar, '2025-01-01', '2026-01-01' );
	ax_mp_assert(
		$ax_mp_results,
		'as instants, with no civil date invented for a moment that is two dates at once',
		count( $ax_mp_rows ) > 0
			&& count( array_filter( $ax_mp_rows, static fn( array $r ) : bool => 'instant' !== (string) $r['temporal_kind'] || null !== $r['start_date'] ) ) === 0
	);
	/*
	 * Written with no title at all, which is the whole reason step 3 came before this one. A generator
	 * producing hundreds of rows a year should not be writing a word it would have to translate.
	 */
	ax_mp_assert(
		$ax_mp_results,
		'and with no stored name, because the phase key names them in whatever language they are read',
		count( array_filter( $ax_mp_rows, static fn( array $r ) : bool => null !== $r['title'] ) ) === 0
	);
	ax_mp_assert(
		$ax_mp_results,
		'while every one of them still reads as a name',
		count( array_filter( $ax_mp_rows, static fn( array $r ) : bool => '' === axismundi_cal_item_display_name( $r ) ) ) === 0
	);
	ax_mp_assert(
		$ax_mp_results,
		'each carrying exactly one phase, which the exclusive group is what enforces',
		count(
			array_filter(
				$ax_mp_rows,
				static fn( array $r ) : bool => 1 !== count( array_intersect( axismundi_cal_normalize_categories( (string) $r['categories'] ), AXISMUNDI_CAL_CATEGORY_EXCLUSIVE_GROUPS['moon_phase']['members'] ) )
			)
		) === 0
	);

	/*
	 * Regeneration is the operation this will actually be used for: a cron adding next year, or
	 * somebody re-running a span after a fix. Without a stable identity per phase it would append a
	 * second copy of every row, and a subscriber would be handed a calendar of duplicates.
	 */
	$ax_mp_again = axismundi_cal_generate_moon_phases( $ax_mp_calendar, 2025, 2025 );
	ax_mp_assert(
		$ax_mp_results,
		'regenerating the same year updates its entries rather than adding a second copy of it',
		is_int( $ax_mp_again ) && count( axismundi_cal_system_items_in_range( $ax_mp_calendar, '2025-01-01', '2026-01-01' ) ) === count( $ax_mp_rows )
	);

	/*
	 * Storage is a cache of a computation, never the extent of what is knowable. A year nobody has
	 * materialized still has phases, and nothing may read an empty year as "no phases then".
	 */
	ax_mp_assert(
		$ax_mp_results,
		'a year nobody materialized still computes, since storage is a cache rather than the coverage',
		count( axismundi_cal_moon_phases_in_year( 2087 ) ) >= 48
			&& array() === axismundi_cal_system_items_in_range( $ax_mp_calendar, '2087-01-01', '2088-01-01', array(), true )
	);

	/*
	 * The default span is sized to the subscription window, not to an idea of reach. Asserted against
	 * the feed's own constants so the two cannot drift apart: a materialized span shorter than the
	 * window would empty a subscriber's calendar at its far edge, which is the failure that is invisible
	 * from the site itself.
	 */
	list( $ax_mp_first, $ax_mp_last ) = axismundi_cal_moon_phase_default_span( 2026 );
	ax_mp_assert(
		$ax_mp_results,
		'the default span reaches at least as far ahead as the subscription feed does',
		$ax_mp_last >= 2026 + AXISMUNDI_CAL_DATASET_FEED_YEARS
	);
	ax_mp_assert(
		$ax_mp_results,
		'and far enough back to cover the months the feed carries behind today',
		$ax_mp_first <= 2026 && ( 2026 - $ax_mp_first ) * 12 >= AXISMUNDI_CAL_FEED_PAST_MONTHS
	);

	// -- Where phases do not belong --------------------------------------------------------------------

	$ax_mp_holidays = (int) axismundi_cal_calendar_save(
		array(
			'name'            => 'Not astronomy',
			'slug'            => 'ax-mp-h-' . $ax_mp_suffix,
			'timezone'        => 'Asia/Seoul',
			'kind'            => 'system',
			'system_provider' => 'holiday',
			'provider_config' => array( 'region' => 'KR', 'source_locale' => 'ko-KR' ),
		)
	);
	$ax_mp_calendars[] = $ax_mp_holidays;
	ax_mp_assert(
		$ax_mp_results,
		'a holiday calendar refuses to be filled with moon phases, which are not a thing anyone reviews',
		is_wp_error( axismundi_cal_generate_moon_phases( $ax_mp_holidays, 2025, 2025 ) )
	);
	ax_mp_assert(
		$ax_mp_results,
		'and a span that ends before it begins is refused rather than quietly writing nothing',
		is_wp_error( axismundi_cal_generate_moon_phases( $ax_mp_calendar, 2027, 2025 ) )
	);
} finally {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$ax_mp_strays = (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . axismundi_cal_calendars_table() . " WHERE slug LIKE %s", 'ax-mp-%' ) );
	foreach ( array_unique( array_merge( $ax_mp_calendars, array_map( 'intval', $ax_mp_strays ) ) ) as $ax_mp_calendar_id ) {
		axismundi_cal_system_items_forget_calendar( (int) $ax_mp_calendar_id );
		axismundi_cal_list_forget_calendar( (int) $ax_mp_calendar_id );
		axismundi_cal_acl_forget_calendar( (int) $ax_mp_calendar_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => (int) $ax_mp_calendar_id ) );
	}
}

$ax_mp_failures = count( array_filter( $ax_mp_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_mp_results ), $ax_mp_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_mp_failures > 0 ? 1 : 0 );
}
exit( $ax_mp_failures > 0 ? 1 : 0 );
