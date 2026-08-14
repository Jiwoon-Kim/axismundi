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
	list( $ax_mp_from, $ax_mp_to ) = axismundi_cal_moon_phase_window();
	ax_mp_assert(
		$ax_mp_results,
		'the stored window reaches exactly as far ahead as the subscription feed serves',
		$ax_mp_to === gmdate( 'Y-m-d', (int) strtotime( '+' . AXISMUNDI_CAL_DATASET_FEED_YEARS . ' years' ) )
	);
	ax_mp_assert(
		$ax_mp_results,
		'and exactly as far back, so neither surface carries a month the other does not',
		$ax_mp_from === gmdate( 'Y-m-d', (int) strtotime( '-' . AXISMUNDI_CAL_FEED_PAST_MONTHS . ' months' ) )
	);

	// -- The window rolls ------------------------------------------------------------------------------

	/*
	 * Maintenance is the operation that actually runs, over and over, unattended. Both halves have to
	 * work at once: creating without pruning grows the table forever, and pruning without creating
	 * empties the far edge of every subscriber's feed as the window advances past what was written at
	 * installation. Neither failure is visible from the site, which shows whatever is there.
	 */
	$ax_mp_kept = axismundi_cal_maintain_moon_phases( $ax_mp_calendar );
	ax_mp_assert( $ax_mp_results, 'maintenance runs and reports what it did', is_array( $ax_mp_kept ) );

	$ax_mp_in_window = static function () use ( $ax_mp_calendar, $ax_mp_from, $ax_mp_to ) : array {
		return axismundi_cal_system_items_in_range( $ax_mp_calendar, $ax_mp_from, gmdate( 'Y-m-d', (int) strtotime( $ax_mp_to . ' +1 day' ) ) );
	};
	ax_mp_assert( $ax_mp_results, 'and the window is populated afterwards', count( $ax_mp_in_window() ) > 100 );

	/*
	 * The 2025 rows this file materialized earlier are the fixture: a year that has since fallen out of
	 * the window is exactly what a real installation accumulates as time passes.
	 */
	global $wpdb;
	$ax_mp_outside = static function () use ( $wpdb, $ax_mp_calendar, $ax_mp_from, $ax_mp_to ) : int {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture inspection.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . axismundi_cal_system_items_table() . ' WHERE calendar_id = %d AND ( start_utc < %s OR start_utc >= %s )',
				$ax_mp_calendar,
				$ax_mp_from . ' 00:00:00',
				gmdate( 'Y-m-d 00:00:00', (int) strtotime( $ax_mp_to . ' +1 day' ) )
			)
		);
	};
	ax_mp_assert( $ax_mp_results, 'nothing is left outside the window once maintenance has run', 0 === $ax_mp_outside() );

	/*
	 * Scoped by `source_uid`, not by the calendar. Equinoxes and lunar eclipses are going to share this
	 * calendar and carry their own windows, so a prune that took everything out of range would start
	 * deleting another generator's rows the day one is added -- and it would look like the phases
	 * working correctly.
	 */
	$ax_mp_neighbour = axismundi_cal_system_item_save(
		$ax_mp_calendar,
		array( 'title' => 'Some other astronomy row', 'temporal_kind' => 'instant', 'start_utc' => '2019-03-20T21:58:00Z', 'source_uid' => 'equinox-2019-03', 'status' => 'published' )
	);
	axismundi_cal_maintain_moon_phases( $ax_mp_calendar );
	ax_mp_assert(
		$ax_mp_results,
		'and a row another generator owns survives the prune, though it is far outside this window',
		! is_wp_error( $ax_mp_neighbour ) && is_array( axismundi_cal_system_item_get( (int) $ax_mp_neighbour ) )
	);
	axismundi_cal_system_item_delete( (int) $ax_mp_neighbour );

	/*
	 * Idempotence, which is what makes a daily job safe. A second run on an unchanged day must not
	 * write a second copy of anything, and `source_uid` is what carries that.
	 */
	$ax_mp_before = count( $ax_mp_in_window() );
	axismundi_cal_maintain_moon_phases( $ax_mp_calendar );
	ax_mp_assert( $ax_mp_results, 'running it twice in a day changes nothing, since it upserts on the phase identity', $ax_mp_before === count( $ax_mp_in_window() ) );

	/*
	 * Outside the window there are no rows and nothing recomputes them on read. That is the deliberate
	 * shape: these are a materialized view for the current grid and the current subscription, not an
	 * archive. The arithmetic still answers for any year, which is what a future browse feature would
	 * use -- and it would say so rather than pretending to read stored data.
	 */
	ax_mp_assert(
		$ax_mp_results,
		'a year outside the window holds no rows, and reading it does not quietly materialize any',
		array() === axismundi_cal_system_items_in_range( $ax_mp_calendar, '2031-01-01', '2032-01-01', array(), true )
			&& array() === axismundi_cal_system_items_in_range( $ax_mp_calendar, '2031-01-01', '2032-01-01', array(), true )
	);
	ax_mp_assert(
		$ax_mp_results,
		'while the arithmetic still answers for it, so a browse feature has something to build on',
		count( axismundi_cal_moon_phases_in_year( 2031 ) ) >= 48
	);

	// -- When it next needs doing ----------------------------------------------------------------------

	/*
	 * The whole reason this is not a daily job. Two moments can make the stored set wrong and both are
	 * computable: a phase past the far edge becomes due as the window advances onto it, and the oldest
	 * stored row becomes stale as the trailing edge passes it. A wrong answer here is invisible -- the
	 * calendar simply stops being maintained, and looks exactly like one that is.
	 */
	$ax_mp_due = axismundi_cal_moon_phase_next_maintenance( $ax_mp_calendar );
	ax_mp_assert( $ax_mp_results, 'the next due moment is in the future, since the window is current', $ax_mp_due > time() );
	/*
	 * Bounded by the phase interval rather than merely "some time later". A due date months away would
	 * mean an edge had been missed; one seconds away would mean it is really a daily job wearing a
	 * different hat.
	 */
	ax_mp_assert(
		$ax_mp_results,
		'and is within one lunation, which is as long as either edge can stay quiet',
		( $ax_mp_due - time() ) < (int) ceil( AXISMUNDI_CAL_SYNODIC_MONTH ) * DAY_IN_SECONDS
	);

	global $wpdb;
	$ax_mp_oldest = (string) $wpdb->get_var(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture inspection.
		$wpdb->prepare( 'SELECT MIN(start_utc) FROM ' . axismundi_cal_system_items_table() . ' WHERE calendar_id = %d', $ax_mp_calendar )
	);
	/*
	 * The trailing edge is a date comparison, so the oldest row survives all of its own day. Asserting
	 * the exact moment rather than a range is what catches an off-by-one that would prune it a day
	 * early -- which nothing downstream would report, because a missing phase looks like a phase that
	 * was never there.
	 */
	ax_mp_assert(
		$ax_mp_results,
		'the trailing edge falls exactly when the oldest stored phase stops being inside the window',
		$ax_mp_due <= (int) strtotime( substr( $ax_mp_oldest, 0, 10 ) . ' +1 day +' . AXISMUNDI_CAL_FEED_PAST_MONTHS . ' months UTC' )
	);
	ax_mp_assert(
		$ax_mp_results,
		'and nothing is due before then except a phase arriving at the far edge',
		$ax_mp_due === min(
			(int) strtotime( substr( $ax_mp_oldest, 0, 10 ) . ' +1 day +' . AXISMUNDI_CAL_FEED_PAST_MONTHS . ' months UTC' ),
			$ax_mp_due
		)
	);

	/*
	 * An empty calendar has no oldest row and no far edge to reason from, and the answer is to fill it
	 * rather than to wait. Returned as 0 rather than as a timestamp, so a caller cannot mistake "now"
	 * for a moment in 1970.
	 */
	$ax_mp_empty = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Empty astronomy', 'slug' => 'ax-mp-empty-' . $ax_mp_suffix, 'timezone' => 'UTC', 'kind' => 'system', 'system_provider' => 'astronomy' )
	);
	$ax_mp_calendars[] = $ax_mp_empty;
	ax_mp_assert( $ax_mp_results, 'a calendar with nothing stored is due immediately rather than at some computed moment', 0 === axismundi_cal_moon_phase_next_maintenance( $ax_mp_empty ) );

	// -- How a computed row reads on the maintenance screen ---------------------------------------------

	/*
	 * Both of these were wrong on screen while every stored value was right, which is the failure mode
	 * an admin table has: it reads fields, and a field that is NULL by design looks like missing data.
	 */
	$ax_mp_admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( ! empty( $ax_mp_admin ) ) {
		wp_set_current_user( (int) $ax_mp_admin[0] );
		ob_start();
		axismundi_cal_render_system_item_editor( (array) axismundi_cal_calendar_get( $ax_mp_calendar ), 'https://example.test/admin' );
		$ax_mp_html = (string) ob_get_clean();
		wp_set_current_user( 0 );

		/*
		 * The date column read `start_date`, which an instant does not have, so every computed row showed
		 * an empty cell. Shown as the UTC moment instead -- and labelled, because rendering it in the
		 * site's zone would print one reading of a moment as though it were the fact.
		 */
		$ax_mp_sample = axismundi_cal_system_items_in_range( $ax_mp_calendar, $ax_mp_from, gmdate( 'Y-m-d', (int) strtotime( $ax_mp_to . ' +1 day' ) ) );
		// The screen renders one year at a time, so any row of the window appearing is the evidence --
		// which row it happens to be is the year selector's business, not this rule's.
		$ax_mp_shown = 0;
		foreach ( $ax_mp_sample as $ax_mp_row ) {
			if ( str_contains( $ax_mp_html, substr( (string) $ax_mp_row['start_utc'], 0, 16 ) ) ) {
				++$ax_mp_shown;
			}
		}
		ax_mp_assert(
			$ax_mp_results,
			'a moment shows its instant on the maintenance screen rather than an empty date cell',
			$ax_mp_shown > 0
		);

		/*
		 * A generator writes `source_uid` for the same reason an import does -- it is what makes a second
		 * pass update rather than duplicate -- so a screen reading only that called every moon phase
		 * "Imported", naming a feed that does not exist.
		 */
		ax_mp_assert( $ax_mp_results, 'and is described as computed rather than imported, since there is no feed behind it', str_contains( $ax_mp_html, 'Computed' ) );
		ax_mp_assert( $ax_mp_results, 'with nothing on the screen claiming it came from somewhere', ! str_contains( $ax_mp_html, 'Imported' ) );
	}

	// -- Equinoxes and solstices -------------------------------------------------------------------------

	/*
	 * Meeus chapter 27, worked example 27.b: the June solstice of 1962. Asserted in JDE, so nothing
	 * about timezones, ΔT or the calendar conversion sits between the book's number and this one.
	 */
	ax_mp_assert(
		$ax_mp_results,
		'the June solstice of 1962 lands where Meeus computes it, to under a second',
		abs( axismundi_cal_season_jde( 1962, 1 ) - 2437837.39245 ) < 0.00002
	);
	/*
	 * The mean instant is not the instant. The periodic series is worth about twenty minutes, and a run
	 * that skipped it would still return a plausible date in the right week -- which nothing downstream
	 * could tell apart from the right one.
	 */
	$ax_mp_season_mean = 2451716.56767 + ( 365241.62603 * ( ( 1962 - 2000 ) / 1000 ) )
		+ ( 0.00325 * pow( ( 1962 - 2000 ) / 1000, 2 ) )
		+ ( 0.00888 * pow( ( 1962 - 2000 ) / 1000, 3 ) )
		- ( 0.00030 * pow( ( 1962 - 2000 ) / 1000, 4 ) );
	ax_mp_assert(
		$ax_mp_results,
		'and is far enough from the mean point to show the periodic terms were applied',
		abs( axismundi_cal_season_jde( 1962, 1 ) - $ax_mp_season_mean ) > 0.005
	);

	/*
	 * Published instants, which exercise ΔT and the Julian Day conversion as well as the series. Meeus
	 * gives these to about 51 seconds for 1951-2050, so two minutes is the honest tolerance.
	 */
	$ax_mp_season_anchors = array(
		array( 'NORTHWARD-EQUINOX', '2025-03-20 09:01:00' ),
		array( 'NORTHERN-SOLSTICE', '2025-06-21 02:42:00' ),
		array( 'SOUTHWARD-EQUINOX', '2025-09-22 18:19:00' ),
		array( 'SOUTHERN-SOLSTICE', '2025-12-21 15:03:00' ),
	);
	$ax_mp_seasons_2025 = axismundi_cal_seasons_in_year( 2025 );
	foreach ( $ax_mp_season_anchors as $ax_mp_index => $ax_mp_anchor ) {
		ax_mp_assert(
			$ax_mp_results,
			sprintf( 'the %s of 2025 is computed within two minutes of its published time', strtolower( str_replace( '-', ' ', $ax_mp_anchor[0] ) ) ),
			$ax_mp_anchor[0] === (string) $ax_mp_seasons_2025[ $ax_mp_index ]['phase']
				&& ax_mp_gap( (string) $ax_mp_seasons_2025[ $ax_mp_index ]['start_utc'], $ax_mp_anchor[1] ) < 120
		);
	}
	ax_mp_assert( $ax_mp_results, 'a year holds exactly four of them, unlike the lunations', 4 === count( $ax_mp_seasons_2025 ) );

	/*
	 * Whole calendar years, unlike the phases, because these are asked for by year: somebody looks up
	 * the equinox of 2027 the way nobody looks up the full moons of 2027. A window beginning in May
	 * would leave the maintenance screen showing a partial year with no way to reach the rest.
	 */
	list( $ax_mp_season_from, $ax_mp_season_to ) = axismundi_cal_season_window();
	ax_mp_assert(
		$ax_mp_results,
		'their window is whole calendar years rather than a rolling span',
		str_ends_with( $ax_mp_season_from, '-01-01' ) && str_ends_with( $ax_mp_season_to, '-12-31' )
	);
	/*
	 * The invariant that decides how short a materialization policy may be, and the one failure neither
	 * end can see: the feed serves two years ahead, so a window stopping sooner leaves the far edge of
	 * every subscriber's calendar empty while the site itself looks complete. Asserted per generator
	 * rather than once, because each now chooses its own span.
	 */
	list( $ax_mp_feed_from, $ax_mp_feed_to ) = axismundi_cal_moon_phase_window();
	ax_mp_assert(
		$ax_mp_results,
		'and it still covers everything the subscription feed reaches, at both ends',
		$ax_mp_season_to >= $ax_mp_feed_to && $ax_mp_season_from <= $ax_mp_feed_from
	);
	/*
	 * Which is why "this year and next" was not enough. Stated as arithmetic rather than as a comment,
	 * so a later tightening of the span fails here instead of in somebody's calendar client.
	 */
	ax_mp_assert(
		$ax_mp_results,
		'two calendar years would not have covered it, which is what rules that policy out',
		sprintf( '%04d-12-31', (int) gmdate( 'Y' ) + 1 ) < $ax_mp_feed_to
	);
	/*
	 * A window of whole years moves one day a year, so there is nothing in the data to compute a due
	 * moment from -- unlike the phases, whose edges are wherever the next lunation happens to fall.
	 */
	ax_mp_assert(
		$ax_mp_results,
		'so maintenance is due at the turn of the year and not before',
		( static function () use ( $ax_mp_calendar ) : bool {
			axismundi_cal_maintain_seasons( $ax_mp_calendar );
			return gmdate( 'm-d', axismundi_cal_season_next_maintenance( $ax_mp_calendar ) ) === '01-01';
		} )()
	);
	ax_mp_assert(
		$ax_mp_results,
		'each carrying its parent key as well, since one of those cannot name an entry on its own',
		count( array_filter( $ax_mp_seasons_2025, static fn( array $p ) : bool => '' === axismundi_cal_item_generated_name( array( $p['parent'], $p['phase'] ) ) ) ) === 0
	);

	/*
	 * Two generators on one calendar, which is the arrangement the `source_uid` scoping exists for. A
	 * prune that took everything outside its own window would delete the other one's rows, and the
	 * calendar would look perfectly correct for whichever generator ran last.
	 */
	axismundi_cal_maintain_seasons( $ax_mp_calendar );
	$ax_mp_mixed = axismundi_cal_maintain_moon_phases( $ax_mp_calendar );
	ax_mp_assert( $ax_mp_results, 'the phases and the seasons can share a calendar', is_array( $ax_mp_mixed ) );
	global $wpdb;
	$ax_mp_season_rows = (int) $wpdb->get_var(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture inspection.
		$wpdb->prepare( "SELECT COUNT(*) FROM " . axismundi_cal_system_items_table() . " WHERE calendar_id = %d AND source_uid LIKE %s", $ax_mp_calendar, $wpdb->esc_like( 'season-' ) . '%' )
	);
	ax_mp_assert( $ax_mp_results, 'and maintaining the phases does not prune the seasons out from under them', $ax_mp_season_rows > 0 );

	/*
	 * The symmetric case, and the one that actually exercises the scoping. Both generators use the same
	 * window today, so with every row inside it neither prune would reach the other's whether it were
	 * scoped or not -- the check would pass on a version that deletes everything out of range. A row
	 * deliberately placed outside is what tells those two implementations apart.
	 */
	$ax_mp_stray_phase = axismundi_cal_system_item_save(
		$ax_mp_calendar,
		array( 'temporal_kind' => 'instant', 'start_utc' => '2019-01-06T01:28:00Z', 'categories' => array( 'MOON-PHASE', 'NEW-MOON' ), 'source_uid' => 'moon-phase-999999', 'status' => 'published' )
	);
	axismundi_cal_maintain_seasons( $ax_mp_calendar );
	ax_mp_assert(
		$ax_mp_results,
		'while maintaining the seasons leaves a phase far outside their window alone',
		! is_wp_error( $ax_mp_stray_phase ) && is_array( axismundi_cal_system_item_get( (int) $ax_mp_stray_phase ) )
	);
	/*
	 * And the phases' own prune does reach it, so the row really was out of range rather than the check
	 * having been satisfied by a row nothing would have deleted anyway.
	 */
	axismundi_cal_maintain_moon_phases( $ax_mp_calendar );
	ax_mp_assert(
		$ax_mp_results,
		'and its own generator does prune it, which is what makes that a real test rather than a safe one',
		null === axismundi_cal_system_item_get( (int) $ax_mp_stray_phase )
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
