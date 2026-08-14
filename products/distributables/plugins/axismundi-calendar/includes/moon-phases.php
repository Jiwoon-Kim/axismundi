<?php
/**
 * When the moon changes phase.
 *
 * Computed rather than fetched. A phase is a solved problem in celestial mechanics and has been
 * since 1991, so an API key, a rate limit and a network dependency would buy nothing except a way
 * for the calendar to be wrong when somebody else's server is down. This is Meeus, *Astronomical
 * Algorithms*, chapter 49, which gives the instant of each phase to within a few seconds -- far
 * finer than anything a calendar grid can show.
 *
 * A phase is an instant and is stored as one. It is not a date: the full moon of 2026-08-28T00:30Z
 * is the 28th in Seoul and the 27th in Los Angeles, and there is no date that is true for both.
 * Google's own moon-phase feed flattens each phase to an all-day entry and writes the time into the
 * `SUMMARY` string -- "Full moon 10:27pm" -- which fixes the day to one timezone and puts the only
 * accurate part of the answer somewhere no program can read it.
 *
 * The time scale, the ΔT estimate and the Julian Day conversion live in `astronomy.php`, because
 * none of them is about the moon. What crosses the boundary out of here is a UTC timestamp string.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The four phases, in the order one lunation visits them.
 *
 * Keyed by the quarter of `k` each corresponds to in Meeus, which is what makes the index below an
 * integer and therefore a usable identity.
 */
const AXISMUNDI_CAL_MOON_PHASES = array(
	0 => 'NEW-MOON',
	1 => 'FIRST-QUARTER',
	2 => 'FULL-MOON',
	3 => 'LAST-QUARTER',
);

/** Mean length of a lunation in days, which is what sets how many phases a year holds. */
const AXISMUNDI_CAL_SYNODIC_MONTH = 29.530588861;

/**
 * The Julian Ephemeris Day of one phase, in Dynamical Time.
 *
 * `$k` is Meeus's lunation index: whole numbers are new moons counted from the one of 2000 January
 * 6, and the quarters land on .25, .5 and .75. Internal, and deliberately so -- see the file header.
 *
 * @param float $k Lunation index, a multiple of 0.25.
 * @return float
 */
function axismundi_cal_moon_phase_jde( float $k ) : float {
	$t  = $k / 1236.85;
	$t2 = $t * $t;
	$t3 = $t2 * $t;
	$t4 = $t3 * $t;

	$jde = 2451550.09766 + ( AXISMUNDI_CAL_SYNODIC_MONTH * $k )
		+ ( 0.00015437 * $t2 )
		- ( 0.000000150 * $t3 )
		+ ( 0.00000000073 * $t4 );

	// The eccentricity of the Earth's orbit, which slowly changes and scales every solar term below.
	$e = 1 - ( 0.002516 * $t ) - ( 0.0000074 * $t2 );

	$m       = 2.5534 + ( 29.10535670 * $k ) - ( 0.0000014 * $t2 ) - ( 0.00000011 * $t3 );
	$m_prime = 201.5643 + ( 385.81693528 * $k ) + ( 0.0107582 * $t2 ) + ( 0.00001238 * $t3 ) - ( 0.000000058 * $t4 );
	$f       = 160.7108 + ( 390.67050284 * $k ) - ( 0.0016118 * $t2 ) - ( 0.00000227 * $t3 ) + ( 0.000000011 * $t4 );
	$omega   = 124.7746 - ( 1.56375588 * $k ) + ( 0.0020672 * $t2 ) + ( 0.00000215 * $t3 );

	$quarter = (int) round( fmod( fmod( $k, 1.0 ) + 1.0, 1.0 ) * 4 ) % 4;

	if ( 0 === $quarter || 2 === $quarter ) {
		// New and full differ only in the first two coefficients; everything after is shared.
		$lead   = 0 === $quarter ? -0.40720 : -0.40614;
		$solar  = 0 === $quarter ? 0.17241 : 0.17302;
		$two_mp = 0 === $quarter ? 0.01608 : 0.01614;
		$two_f  = 0 === $quarter ? 0.01039 : 0.01043;
		$e_2m   = 0 === $quarter ? 0.00208 : 0.00209;
		$mp_m   = 0 === $quarter ? 0.00739 : 0.00734;
		$mp_pm  = 0 === $quarter ? -0.00514 : -0.00515;

		$jde += $lead * axismundi_cal_sin_deg( $m_prime );
		$jde += $solar * $e * axismundi_cal_sin_deg( $m );
		$jde += $two_mp * axismundi_cal_sin_deg( 2 * $m_prime );
		$jde += $two_f * axismundi_cal_sin_deg( 2 * $f );
		$jde += $mp_m * $e * axismundi_cal_sin_deg( $m_prime - $m );
		$jde += $mp_pm * $e * axismundi_cal_sin_deg( $m_prime + $m );
		$jde += $e_2m * $e * $e * axismundi_cal_sin_deg( 2 * $m );
		$jde += -0.00111 * axismundi_cal_sin_deg( $m_prime - ( 2 * $f ) );
		$jde += -0.00057 * axismundi_cal_sin_deg( $m_prime + ( 2 * $f ) );
		$jde += 0.00056 * $e * axismundi_cal_sin_deg( ( 2 * $m_prime ) + $m );
		$jde += -0.00042 * axismundi_cal_sin_deg( 3 * $m_prime );
		$jde += 0.00042 * $e * axismundi_cal_sin_deg( $m + ( 2 * $f ) );
		$jde += 0.00038 * $e * axismundi_cal_sin_deg( $m - ( 2 * $f ) );
		$jde += -0.00024 * $e * axismundi_cal_sin_deg( ( 2 * $m_prime ) - $m );
		$jde += -0.00017 * axismundi_cal_sin_deg( $omega );
		$jde += -0.00007 * axismundi_cal_sin_deg( $m_prime + ( 2 * $m ) );
		$jde += 0.00004 * axismundi_cal_sin_deg( ( 2 * $m_prime ) - ( 2 * $f ) );
		$jde += 0.00004 * axismundi_cal_sin_deg( 3 * $m );
		$jde += 0.00003 * axismundi_cal_sin_deg( $m_prime + $m - ( 2 * $f ) );
		$jde += 0.00003 * axismundi_cal_sin_deg( ( 2 * $m_prime ) + ( 2 * $f ) );
		$jde += -0.00003 * axismundi_cal_sin_deg( $m_prime + $m + ( 2 * $f ) );
		$jde += 0.00003 * axismundi_cal_sin_deg( $m_prime - $m + ( 2 * $f ) );
		$jde += -0.00002 * axismundi_cal_sin_deg( $m_prime - $m - ( 2 * $f ) );
		$jde += -0.00002 * axismundi_cal_sin_deg( ( 3 * $m_prime ) + $m );
		$jde += 0.00002 * axismundi_cal_sin_deg( 4 * $m_prime );
	} else {
		$jde += -0.62801 * axismundi_cal_sin_deg( $m_prime );
		$jde += 0.17172 * $e * axismundi_cal_sin_deg( $m );
		$jde += -0.01183 * $e * axismundi_cal_sin_deg( $m_prime + $m );
		$jde += 0.00862 * axismundi_cal_sin_deg( 2 * $m_prime );
		$jde += 0.00804 * axismundi_cal_sin_deg( 2 * $f );
		$jde += 0.00454 * $e * axismundi_cal_sin_deg( $m_prime - $m );
		$jde += 0.00204 * $e * $e * axismundi_cal_sin_deg( 2 * $m );
		$jde += -0.00180 * axismundi_cal_sin_deg( $m_prime - ( 2 * $f ) );
		$jde += -0.00070 * axismundi_cal_sin_deg( $m_prime + ( 2 * $f ) );
		$jde += -0.00040 * axismundi_cal_sin_deg( 3 * $m_prime );
		$jde += -0.00034 * $e * axismundi_cal_sin_deg( ( 2 * $m_prime ) - $m );
		$jde += 0.00032 * $e * axismundi_cal_sin_deg( $m + ( 2 * $f ) );
		$jde += 0.00032 * $e * axismundi_cal_sin_deg( $m - ( 2 * $f ) );
		$jde += -0.00028 * $e * $e * axismundi_cal_sin_deg( $m_prime + ( 2 * $m ) );
		$jde += 0.00027 * $e * axismundi_cal_sin_deg( ( 2 * $m_prime ) + $m );
		$jde += -0.00017 * axismundi_cal_sin_deg( $omega );
		$jde += -0.00005 * axismundi_cal_sin_deg( $m_prime - $m - ( 2 * $f ) );
		$jde += 0.00004 * axismundi_cal_sin_deg( ( 2 * $m_prime ) + ( 2 * $f ) );
		$jde += -0.00004 * axismundi_cal_sin_deg( $m_prime + $m + ( 2 * $f ) );
		$jde += 0.00004 * axismundi_cal_sin_deg( $m_prime - ( 2 * $m ) );
		$jde += 0.00003 * axismundi_cal_sin_deg( $m_prime + $m - ( 2 * $f ) );
		$jde += 0.00003 * axismundi_cal_sin_deg( 3 * $m );
		$jde += 0.00002 * axismundi_cal_sin_deg( ( 2 * $m_prime ) - ( 2 * $f ) );
		$jde += 0.00002 * axismundi_cal_sin_deg( $m_prime - $m + ( 2 * $f ) );
		$jde += -0.00002 * axismundi_cal_sin_deg( ( 3 * $m_prime ) + $m );

		/*
		 * The quarters are not symmetric about the syzygies, so one further term is added for the first
		 * quarter and subtracted for the last. Dropping it puts both quarters out by about seven
		 * minutes, in opposite directions -- which looks like a rounding problem rather than a missing
		 * term, and is the reason it is written out here rather than folded into the list above.
		 */
		$w = 0.00306
			- ( 0.00038 * $e * axismundi_cal_cos_deg( $m ) )
			+ ( 0.00026 * axismundi_cal_cos_deg( $m_prime ) )
			- ( 0.00002 * axismundi_cal_cos_deg( $m_prime - $m ) )
			+ ( 0.00002 * axismundi_cal_cos_deg( $m_prime + $m ) )
			+ ( 0.00002 * axismundi_cal_cos_deg( 2 * $f ) );
		$jde += 1 === $quarter ? $w : -$w;
	}

	/*
	 * The planetary arguments. Small, and none of them optional: together they reach about a minute,
	 * which is more than the precision this is otherwise being trusted to.
	 */
	$additional = array(
		array( 0.000325, 299.77 + ( 0.107408 * $k ) - ( 0.009173 * $t2 ) ),
		array( 0.000165, 251.88 + ( 0.016321 * $k ) ),
		array( 0.000164, 251.83 + ( 26.651886 * $k ) ),
		array( 0.000126, 349.42 + ( 36.412478 * $k ) ),
		array( 0.000110, 84.66 + ( 18.206239 * $k ) ),
		array( 0.000062, 141.74 + ( 53.303771 * $k ) ),
		array( 0.000060, 207.14 + ( 2.453732 * $k ) ),
		array( 0.000056, 154.84 + ( 7.306860 * $k ) ),
		array( 0.000047, 34.52 + ( 27.261239 * $k ) ),
		array( 0.000042, 207.19 + ( 0.121824 * $k ) ),
		array( 0.000040, 291.34 + ( 1.844379 * $k ) ),
		array( 0.000037, 161.72 + ( 24.198154 * $k ) ),
		array( 0.000035, 239.56 + ( 25.513099 * $k ) ),
		array( 0.000023, 331.55 + ( 3.592518 * $k ) ),
	);
	foreach ( $additional as $term ) {
		$jde += $term[0] * axismundi_cal_sin_deg( $term[1] );
	}

	return $jde;
}

/**
 * The lunation index of the phase nearest a moment.
 *
 * @param float $year Decimal year.
 * @return float Multiple of 0.25.
 */
function axismundi_cal_moon_phase_k( float $year ) : float {
	return round( ( $year - 2000.0 ) * 12.3685 * 4 ) / 4;
}

/**
 * Every phase falling in one calendar year, in UTC.
 *
 * Bounded by walking rather than by counting. A year holds twelve or thirteen lunations depending on
 * where its boundaries fall, so a loop of a fixed length would drop a phase in some years and invent
 * one in others; the walk starts before the year and stops after it.
 *
 * @param int $year Calendar year, UTC.
 * @return array<int,array{phase:string,start_utc:string,index:int}>
 */
function axismundi_cal_moon_phases_in_year( int $year ) : array {
	$k    = axismundi_cal_moon_phase_k( $year - 0.1 );
	$out  = array();
	$stop = sprintf( '%04d-01-01 00:00:00', $year + 1 );
	$from = sprintf( '%04d-01-01 00:00:00', $year );

	// A generous ceiling rather than an exact count: 13 lunations is 52 phases, and the walk stops on
	// the date rather than on the counter.
	for ( $step = 0; $step < 64; $step++ ) {
		$utc = axismundi_cal_jde_to_utc( axismundi_cal_moon_phase_jde( $k ) );
		if ( $utc >= $stop ) {
			break;
		}
		if ( $utc >= $from ) {
			$quarter = (int) round( fmod( fmod( $k, 1.0 ) + 1.0, 1.0 ) * 4 ) % 4;
			$out[]   = array(
				'phase'     => AXISMUNDI_CAL_MOON_PHASES[ $quarter ],
				'start_utc' => $utc,
				/*
				 * Four times `k`, which is an integer for every phase and unique across all of them. It is
				 * what makes a regeneration update its own rows instead of writing a second copy of the
				 * year -- the same job `source_uid` does for an imported feed.
				 */
				'index'     => (int) round( $k * 4 ),
			);
		}
		$k += 0.25;
	}
	return $out;
}

/**
 * The window these rows are kept for.
 *
 * The same window the subscription feed serves, and deliberately the same expression of it rather
 * than a second one that happens to agree today. A stored span shorter than the feed's would empty a
 * subscriber's calendar at its far edge, and a span longer than it would accumulate rows nothing
 * reads -- both are invisible from the site itself, which shows whatever is there.
 *
 * Rolling, in days from today, rather than a set of calendar years. A year-based span only moves on
 * the 1st of January, so through December it would be reaching two months past what the feed serves
 * at one end while carrying fifteen months nobody asks for at the other.
 *
 * @return array{0:string,1:string} First and last civil date, as `Y-m-d`.
 */
function axismundi_cal_moon_phase_window() : array {
	return array(
		gmdate( 'Y-m-d', (int) strtotime( '-' . AXISMUNDI_CAL_FEED_PAST_MONTHS . ' months' ) ),
		gmdate( 'Y-m-d', (int) strtotime( '+' . AXISMUNDI_CAL_DATASET_FEED_YEARS . ' years' ) ),
	);
}

/**
 * Bring one calendar's phases into line with the window.
 *
 * The rows are a materialized view of an arithmetic result, not an archive of it. Phases outside the
 * window are not kept and are not recomputed on demand: nothing asks for them. A reader who navigates
 * to 2031 sees no phases, and that is the honest state of a calendar whose purpose is the current
 * grid and the current subscription -- not a claim that the moon did nothing that year. If browsing
 * arbitrary years ever becomes a feature it wants its own surface, where the answer is computed for
 * the range asked about rather than mistaken for stored data.
 *
 * Both halves have to run together. Creating without pruning grows the table forever; pruning without
 * creating empties the far edge of every subscriber's calendar as the window advances past what was
 * materialized at installation.
 *
 * @param int $calendar_id Calendar id.
 * @return array{created:int,deleted:int}|WP_Error
 */
function axismundi_cal_maintain_moon_phases( int $calendar_id ) {
	global $wpdb;
	$calendar = axismundi_cal_calendar_get( $calendar_id );
	if ( ! is_array( $calendar ) || 'astronomy' !== axismundi_cal_system_provider( $calendar ) ) {
		return new WP_Error( 'ax_cal_phase_provider', __( 'Moon phases belong to an astronomy calendar.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	list( $from, $to ) = axismundi_cal_moon_phase_window();

	$created = 0;
	for ( $year = (int) substr( $from, 0, 4 ); $year <= (int) substr( $to, 0, 4 ); $year++ ) {
		foreach ( axismundi_cal_moon_phases_in_year( $year ) as $phase ) {
			$date = substr( (string) $phase['start_utc'], 0, 10 );
			if ( $date < $from || $date > $to ) {
				// The edge years are only partly inside, which is what makes this a window rather than
				// a set of years.
				continue;
			}
			$saved = axismundi_cal_system_item_save(
				$calendar_id,
				array(
					'temporal_kind' => AXISMUNDI_CAL_TEMPORAL_INSTANT,
					'start_utc'     => $phase['start_utc'],
					// No `ASTRONOMY`: the Calendar carries it, and repeating it here would store one fact per row.
					'categories'    => array( 'MOON-PHASE', $phase['phase'] ),
					'batch_year'    => $year,
					'source_uid'    => 'moon-phase-' . $phase['index'],
					'status'        => 'published',
				)
			);
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			++$created;
		}
	}

	/*
	 * Scoped by `source_uid` rather than by the calendar alone. This calendar is where the equinoxes
	 * and the lunar eclipses are going to live too, and each of those will have its own window and its
	 * own reason to keep a row -- so a prune that deleted "everything outside the window on this
	 * calendar" would start throwing away another generator's rows the moment one is added.
	 */
	$table = axismundi_cal_system_items_table();
	$like  = $wpdb->esc_like( 'moon-phase-' ) . '%';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- window maintenance over this plugin's own table.
	$deleted = (int) $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$table}
			 WHERE calendar_id = %d AND source_uid LIKE %s
			   AND ( start_utc < %s OR start_utc >= %s )",
			$calendar_id,
			$like,
			$from . ' 00:00:00',
			gmdate( 'Y-m-d 00:00:00', (int) strtotime( $to . ' +1 day' ) )
		)
	);

	return array( 'created' => $created, 'deleted' => $deleted );
}

/**
 * When this calendar next needs attention.
 *
 * Nothing about a moon phase changes daily, so nothing about this needs doing daily. Two events can
 * make the stored set wrong and they are both predictable: a phase beyond the far edge becomes due as
 * the window advances onto it, and the oldest stored phase becomes stale as the trailing edge passes
 * it. Everything between those two moments is a job that would recompute a few hundred rows to
 * discover that nothing had changed.
 *
 * The earlier of the two, because either one alone leaves the set wrong. In practice that is roughly
 * once a week -- the interval between phases -- rather than once a day.
 *
 * Returns 0 when there is nothing stored to reason about, which means "do it now": an empty calendar
 * has no oldest row and no far edge, and the answer is to fill it rather than to wait.
 *
 * @param int $calendar_id Calendar id.
 * @return int Timestamp, or 0 for immediately.
 */
function axismundi_cal_moon_phase_next_maintenance( int $calendar_id ) : int {
	global $wpdb;
	list( $from, $to ) = axismundi_cal_moon_phase_window();

	$table = axismundi_cal_system_items_table();
	$like  = $wpdb->esc_like( 'moon-phase-' ) . '%';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- scheduling lookup over this plugin's own table.
	$oldest = (string) $wpdb->get_var(
		$wpdb->prepare( "SELECT MIN(start_utc) FROM {$table} WHERE calendar_id = %d AND source_uid LIKE %s", $calendar_id, $like )
	);
	if ( '' === $oldest ) {
		return 0;
	}

	/*
	 * The trailing edge is a date comparison, so the oldest row survives all of its own day and leaves
	 * at the first instant the window starts after it. Computed from the row rather than from a fixed
	 * interval, because the gap between the oldest two rows is not constant.
	 */
	$leaves = (int) strtotime(
		substr( $oldest, 0, 10 ) . ' +1 day +' . AXISMUNDI_CAL_FEED_PAST_MONTHS . ' months UTC'
	);

	/*
	 * The first phase past the far edge, which is the next one that will need writing. Found by walking
	 * the edge year and the one after it, since a window ending in late December has its next phase in
	 * the following year.
	 */
	$arrives = 0;
	for ( $year = (int) substr( $to, 0, 4 ); $year <= (int) substr( $to, 0, 4 ) + 1; $year++ ) {
		foreach ( axismundi_cal_moon_phases_in_year( $year ) as $phase ) {
			$date = substr( (string) $phase['start_utc'], 0, 10 );
			if ( $date <= $to ) {
				continue;
			}
			$arrives = (int) strtotime( $date . ' -' . AXISMUNDI_CAL_DATASET_FEED_YEARS . ' years UTC' );
			break 2;
		}
	}

	if ( 0 === $arrives ) {
		return $leaves;
	}
	return min( $leaves, $arrives );
}

/**
 * Write the phases of a span of years onto one Calendar.
 *
 * Storage here is a cache of a computation, not the extent of what is knowable. Any year can be
 * computed the moment somebody asks for it, so materializing a span is about making review, export
 * and the subscription feed stable -- not about deciding which years exist. Nothing should ever read
 * an empty year as "no phases then".
 *
 * @param int $calendar_id Calendar id.
 * @param int $from_year   First year, inclusive.
 * @param int $to_year     Last year, inclusive.
 * @return int|WP_Error Number of entries written.
 */
function axismundi_cal_generate_moon_phases( int $calendar_id, int $from_year, int $to_year ) {
	$calendar = axismundi_cal_calendar_get( $calendar_id );
	if ( ! is_array( $calendar ) || 'astronomy' !== axismundi_cal_system_provider( $calendar ) ) {
		return new WP_Error( 'ax_cal_phase_provider', __( 'Moon phases belong to an astronomy calendar.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( $to_year < $from_year ) {
		return new WP_Error( 'ax_cal_phase_range', __( 'That span ends before it begins.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$written = 0;
	for ( $year = $from_year; $year <= $to_year; $year++ ) {
		foreach ( axismundi_cal_moon_phases_in_year( $year ) as $phase ) {
			$saved = axismundi_cal_system_item_save(
				$calendar_id,
				array(
					/*
					 * No title. The phase key names the row in whichever language it is read, which is why
					 * a generator writing hundreds of these is not made to write a word for each one.
					 */
					'temporal_kind' => AXISMUNDI_CAL_TEMPORAL_INSTANT,
					'start_utc'     => $phase['start_utc'],
					// No `ASTRONOMY`: the Calendar carries it, and repeating it here would store one fact per row.
					'categories'    => array( 'MOON-PHASE', $phase['phase'] ),
					'batch_year'    => $year,
					'source_uid'    => 'moon-phase-' . $phase['index'],
					'status'        => 'published',
				)
			);
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			++$written;
		}
	}
	return $written;
}
