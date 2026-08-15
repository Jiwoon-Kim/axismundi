<?php
/**
 * How long an Event runs, as the stored fact.
 *
 * The model has meant start-plus-length since the timezone work: an Event that runs 19:00-21:00 runs
 * two hours on every occurrence, including the night the clocks change, and every reader was
 * recovering that length from a stored end time. Recovering it is exactly where the two possible
 * answers drifted apart -- the civil length between two clock faces, and the real elapsed time
 * between two instants -- and each reader that did the arithmetic was a place the drift could recur.
 *
 * So the length is written down and the end is derived. JSCalendar 2.0 says the same thing, which is
 * why this is the shape being migrated to rather than a local preference.
 *
 * Which length depends on whether the Event stays in one zone:
 *
 *   same zone      the civil length. 19:00-21:00 is two hours whatever the clocks do that night.
 *   another zone   the elapsed time. Seoul 10:00 to New York 11:00 has no civil length at all --
 *                  one hour between two clock faces, fifteen in the air -- and only the second
 *                  reconstructs the arrival instant from the departure.
 *
 * Legacy rows with no stored duration fall back to computing it, so nothing breaks before the
 * backfill runs. That fallback is the transition and is expected to go.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * An ISO 8601 duration for one schedule's civil or elapsed length.
 *
 * @param string $start_local Civil start.
 * @param string $end_local   Civil end.
 * @param string $timezone    Start zone.
 * @param string $end_zone    End zone, or '' when the Event ends where it started.
 * @return string
 */
function axismundi_cal_compute_duration( string $start_local, string $end_local, string $timezone, string $end_zone ) : string {
	if ( '' === trim( $end_zone ) ) {
		return axismundi_cal_interval_to_iso( axismundi_cal_civil_interval( $start_local, $end_local ) );
	}
	try {
		$start = new DateTimeImmutable( $start_local, new DateTimeZone( $timezone ) );
		$end   = new DateTimeImmutable( $end_local, new DateTimeZone( $end_zone ) );
	} catch ( Exception $error ) {
		return 'PT0S';
	}
	$seconds = max( 0, $end->getTimestamp() - $start->getTimestamp() );
	return sprintf( 'PT%dH%dM%dS', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 );
}

/**
 * A DateInterval as ISO 8601.
 *
 * Days stay days rather than becoming 24 hours: `P1D` is one calendar day, which is not 24 hours on
 * the night the clocks change, and that difference is the whole reason a civil length is carried.
 *
 * @param DateInterval $interval Interval.
 * @return string
 */
function axismundi_cal_interval_to_iso( DateInterval $interval ) : string {
	$days = (int) $interval->days > 0 ? (int) $interval->days : (int) $interval->d;
	$time = sprintf( '%dH%dM%dS', (int) $interval->h, (int) $interval->i, (int) $interval->s );
	return 'P' . ( $days > 0 ? $days . 'D' : '' ) . 'T' . $time;
}

/**
 * The length one schedule runs for.
 *
 * @param array<string,mixed> $schedule Schedule row.
 * @return DateInterval
 */
function axismundi_cal_schedule_duration( array $schedule ) : DateInterval {
	$stored = trim( (string) ( $schedule['duration'] ?? '' ) );
	if ( '' !== $stored ) {
		try {
			return new DateInterval( $stored );
		} catch ( Exception $error ) {
			// A value nothing can read is worse than none: fall through and compute, rather than
			// answering with zero and quietly ending every occurrence when it starts.
			$stored = '';
		}
	}
	$computed = axismundi_cal_compute_duration(
		(string) ( $schedule['dtstart_local'] ?? '' ),
		(string) ( $schedule['dtend_local'] ?? '' ),
		(string) ( $schedule['timezone'] ?? 'UTC' ),
		(string) ( $schedule['end_timezone'] ?? '' )
	);
	try {
		return new DateInterval( $computed );
	} catch ( Exception $error ) {
		return new DateInterval( 'PT0S' );
	}
}

/**
 * Where one occurrence ends, derived from where it starts and how long it runs.
 *
 * The civil answer, in whichever zone the Event ends in. A same-zone Event moves along its own wall
 * clock; one that lands elsewhere has its arrival placed on the arrival clock.
 *
 * @param array<string,mixed> $schedule Schedule row.
 * @param DateTimeImmutable   $start    Zoned occurrence start.
 * @return DateTimeImmutable
 */
function axismundi_cal_occurrence_end( array $schedule, DateTimeImmutable $start ) : DateTimeImmutable {
	$duration = axismundi_cal_schedule_duration( $schedule );
	$end_zone = trim( (string) ( $schedule['end_timezone'] ?? '' ) );
	if ( '' === $end_zone ) {
		return axismundi_cal_add_civil( $start, $duration );
	}
	try {
		// Elapsed time from the departure instant, then read on the arrival clock.
		return $start->add( $duration )->setTimezone( new DateTimeZone( $end_zone ) );
	} catch ( Exception $error ) {
		return axismundi_cal_add_civil( $start, $duration );
	}
}

/**
 * Write down the length of every schedule that has none.
 *
 * Records the answer the readers were already computing, so nothing observable changes and the
 * derivation stops being the authority.
 *
 * @return int Rows filled.
 */
function axismundi_cal_backfill_durations() : int {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return 0;
	}
	$table = axismundi_cal_schedules_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own table.
	$rows = (array) $wpdb->get_results( "SELECT id, dtstart_local, dtend_local, timezone, end_timezone FROM {$table} WHERE duration = ''", ARRAY_A );
	$filled = 0;
	foreach ( $rows as $row ) {
		$duration = axismundi_cal_compute_duration(
			(string) $row['dtstart_local'],
			(string) $row['dtend_local'],
			(string) $row['timezone'],
			(string) $row['end_timezone']
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $table, array( 'duration' => $duration ), array( 'id' => (int) $row['id'] ), array( '%s' ), array( '%d' ) );
		++$filled;
	}
	return $filled;
}
