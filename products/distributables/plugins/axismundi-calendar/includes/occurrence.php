<?php
/**
 * Expanding a Schedule into the instances it actually produces.
 *
 * Expansion happens in the event's own timezone, on wall time, and converts to UTC only at the end.
 * That order is the whole point: a weekly 19:00 is 19:00 on both sides of a DST change even though
 * the instant beneath it moves by an hour. Expanding in UTC and converting back would keep the
 * instant and move the wall time, which is the opposite of what a calendar promises -- and it would
 * look correct for the two-thirds of the year with no transition in it.
 *
 * The rule is the source of truth for rule-derived instances; the occurrence table is a cache of
 * them, plus a permanent home for the facts that exist nowhere else -- a cancelled instance, a moved
 * one, an extra date. So a rebuild may discard `origin = 'rule'` rows freely and must never touch
 * the others.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * How far past the requested range expansion will run before giving up.
 *
 * An unbounded rule intersected with a narrow far-future window can otherwise walk millions of
 * candidates. The bound is on iterations rather than time so that a pathological rule fails as an
 * empty answer instead of a timeout.
 */
const AXISMUNDI_CAL_EXPAND_MAX_STEPS = 10000;

/**
 * The instances a schedule produces within a range.
 *
 * Computed rather than read, so the answer does not depend on how much of the series happens to
 * have been materialized. Overrides stored against the schedule are applied on top, which is what
 * makes a cancelled or moved instance survive a rebuild.
 *
 * @param array<string,mixed> $schedule Schedule row.
 * @param string              $from_utc Range start, `Y-m-d H:i:s` UTC, inclusive.
 * @param string              $to_utc   Range end, `Y-m-d H:i:s` UTC, exclusive.
 * @return array<int,array<string,mixed>> Occurrences ordered by start.
 */
function axismundi_cal_expand( array $schedule, string $from_utc, string $to_utc ) : array {
	$zone = axismundi_cal_schedule_zone( $schedule );
	if ( ! $zone instanceof DateTimeZone ) {
		return array();
	}
	$all_day  = ! empty( $schedule['all_day'] );
	$utc      = new DateTimeZone( 'UTC' );

	try {
		$from = new DateTimeImmutable( $from_utc, $utc );
		$to   = new DateTimeImmutable( $to_utc, $utc );
		$dtstart = new DateTimeImmutable( (string) $schedule['dtstart_local'], $zone );
		$dtend   = new DateTimeImmutable( (string) $schedule['dtend_local'], $zone );
	} catch ( Exception $error ) {
		return array();
	}
	if ( $to <= $from ) {
		return array();
	}

	/*
	 * The duration is carried rather than the end time. An event that runs 19:00-21:00 runs two
	 * hours on every occurrence, including the one where the clocks change; recomputing the end from
	 * a stored offset would make that occurrence an hour longer or shorter than it is.
	 *
	 * Measured between the civil values rather than between the two instants they resolve to. On a
	 * night the clocks go back, 01:00 and 03:00 are three real hours apart, and `diff()` on the zoned
	 * pair says so -- but adding three hours back onto a zoned start applies the same offset change a
	 * second time, and the event ends at 04:00. The stored end says 03:00, so the interval carried has
	 * to be the two civil hours between them; where that lands in real time is the zone's business,
	 * and it answers once.
	 */
	$duration = axismundi_cal_civil_interval( (string) $schedule['dtstart_local'], (string) $schedule['dtend_local'] );

	$rrule = trim( (string) ( $schedule['rrule'] ?? '' ) );
	if ( '' === $rrule ) {
		$starts = array( $dtstart );
	} else {
		$parts = axismundi_cal_rrule_parse( $rrule );
		if ( is_wp_error( $parts ) ) {
			return array();
		}
		$checked = axismundi_cal_rrule_check( $parts, false );
		if ( is_wp_error( $checked ) ) {
			return array();
		}
		$starts = axismundi_cal_expand_starts( $checked, $dtstart, $zone, $from, $to, $duration );
	}

	$overrides  = axismundi_cal_overrides( (int) ( $schedule['id'] ?? 0 ) );
	$occurrences = array();

	foreach ( $starts as $start ) {
		$recurrence_id = axismundi_cal_recurrence_id( $start, $all_day );
		$occurrence    = axismundi_cal_build_occurrence( $schedule, $start, $duration, $all_day );
		if ( isset( $overrides[ $recurrence_id ] ) ) {
			$occurrence = axismundi_cal_apply_override( $occurrence, $overrides[ $recurrence_id ], $zone, $all_day );
		}
		// Applied after the override, since moving an instance can move it out of the window and
		// an instance moved into the window has to appear.
		if ( axismundi_cal_in_range( $occurrence, $from, $to ) ) {
			$occurrences[ $recurrence_id ] = $occurrence;
		}
	}

	// RDATE instances are authored, not derived, so they are added rather than matched.
	foreach ( $overrides as $recurrence_id => $override ) {
		if ( 'rdate' !== (string) $override['origin'] || isset( $occurrences[ $recurrence_id ] ) ) {
			continue;
		}
		$occurrence = axismundi_cal_override_occurrence( $schedule, $override, $all_day );
		if ( null !== $occurrence && axismundi_cal_in_range( $occurrence, $from, $to ) ) {
			$occurrences[ $recurrence_id ] = $occurrence;
		}
	}

	usort(
		$occurrences,
		static fn( array $a, array $b ) : int => strcmp( (string) $a['start_utc'], (string) $b['start_utc'] )
	);
	return array_values( $occurrences );
}

/**
 * Walk the rule, producing local start times.
 *
 * `COUNT` counts the instances the rule produces from the beginning, not the ones inside the
 * requested window, so the walk always starts at DTSTART. `UNTIL` and `COUNT` therefore both mean
 * the same thing whichever month is being looked at.
 *
 * @param array<string,mixed> $rule     Checked rule parts.
 * @param DateTimeImmutable   $dtstart  Series start, local.
 * @param DateTimeZone        $zone     Event zone.
 * @param DateTimeImmutable   $from     Window start, UTC.
 * @param DateTimeImmutable   $to       Window end, UTC.
 * @param DateInterval        $duration Instance duration.
 * @return DateTimeImmutable[]
 */
function axismundi_cal_expand_starts( array $rule, DateTimeImmutable $dtstart, DateTimeZone $zone, DateTimeImmutable $from, DateTimeImmutable $to, DateInterval $duration ) : array {
	$freq     = (string) $rule['FREQ'];
	$interval = (int) ( $rule['INTERVAL'] ?? 1 );
	$count    = isset( $rule['COUNT'] ) ? (int) $rule['COUNT'] : null;
	$until    = axismundi_cal_rule_until( $rule, $zone );

	$starts    = array();
	$produced  = 0;
	$steps     = 0;
	$cursor    = $dtstart;
	$utc       = new DateTimeZone( 'UTC' );

	while ( $steps < AXISMUNDI_CAL_EXPAND_MAX_STEPS ) {
		++$steps;
		$candidates = axismundi_cal_period_candidates( $rule, $freq, $cursor, $dtstart, $zone );

		foreach ( $candidates as $candidate ) {
			if ( $candidate < $dtstart ) {
				continue;
			}
			if ( null !== $until && $candidate > $until ) {
				return $starts;
			}
			++$produced;
			if ( null !== $count && $produced > $count ) {
				return $starts;
			}
			// Collected even when it precedes the window, because COUNT is measured from the start
			// of the series; only the returned set is filtered by range.
			$candidate_end = $candidate->add( $duration );
			if ( $candidate_end->setTimezone( $utc ) > $from ) {
				$starts[] = $candidate;
			}
		}

		$cursor = axismundi_cal_advance( $cursor, $freq, $interval );
		// The window is closed once the period being generated starts after it, but only when the
		// series is not otherwise bounded -- a COUNT rule has to keep counting to know where it ends.
		if ( $cursor->setTimezone( $utc ) >= $to && null === $count ) {
			break;
		}
		if ( null !== $until && $cursor > $until ) {
			break;
		}
		if ( null === $count && $cursor->setTimezone( $utc ) >= $to ) {
			break;
		}
	}

	return $starts;
}

/**
 * The local start times one period of the rule produces.
 *
 * @param array<string,mixed> $rule    Checked rule parts.
 * @param string              $freq    Frequency.
 * @param DateTimeImmutable   $cursor  Period anchor, local.
 * @param DateTimeImmutable   $dtstart Series start, local.
 * @param DateTimeZone        $zone    Event zone.
 * @return DateTimeImmutable[]
 */
function axismundi_cal_period_candidates( array $rule, string $freq, DateTimeImmutable $cursor, DateTimeImmutable $dtstart, DateTimeZone $zone ) : array {
	$time   = $dtstart->format( 'H:i:s' );
	$byday  = $rule['BYDAY'] ?? array();
	$bymday = $rule['BYMONTHDAY'] ?? array();
	$bymon  = $rule['BYMONTH'] ?? array();

	if ( 'DAILY' === $freq ) {
		/*
		 * Rebuilt at the series' own time of day rather than taken from the cursor. On the morning the
		 * clocks go forward, an occurrence at 02:30 has no such moment and the zone places it at 03:30 --
		 * correct for that day, and carried by the cursor into every day after it, so a 02:30 series
		 * would quietly become a 03:30 series for good. The date advances; the clock does not.
		 */
		$candidates = array( axismundi_cal_at_civil_time( $cursor, $time, $zone ) );
	} elseif ( 'WEEKLY' === $freq ) {
		$candidates = axismundi_cal_week_candidates( $cursor, $byday, $rule, $time, $zone );
	} else {
		// MONTHLY and YEARLY differ only in which months they consider.
		$months = array();
		if ( 'YEARLY' === $freq ) {
			$months = empty( $bymon ) ? array( (int) $dtstart->format( 'n' ) ) : $bymon;
		} else {
			$months = array( (int) $cursor->format( 'n' ) );
			if ( ! empty( $bymon ) && ! in_array( (int) $cursor->format( 'n' ), $bymon, true ) ) {
				return array();
			}
		}
		$candidates = array();
		foreach ( $months as $month ) {
			$candidates = array_merge(
				$candidates,
				axismundi_cal_month_candidates( (int) $cursor->format( 'Y' ), (int) $month, $byday, $bymday, $time, $zone, $dtstart )
			);
		}
	}

	usort( $candidates, static fn( DateTimeImmutable $a, DateTimeImmutable $b ) : int => $a <=> $b );
	return $candidates;
}

/**
 * Start times within one week.
 *
 * @param DateTimeImmutable                            $cursor Week anchor.
 * @param array<int,array{ordinal:int|null,day:string}> $byday  Weekday selection.
 * @param array<string,mixed>                          $rule   Checked rule.
 * @param string                                       $time   Wall time to apply.
 * @param DateTimeZone                                 $zone   Event zone.
 * @return DateTimeImmutable[]
 */
function axismundi_cal_week_candidates( DateTimeImmutable $cursor, array $byday, array $rule, string $time, DateTimeZone $zone ) : array {
	if ( empty( $byday ) ) {
		return array( $cursor );
	}
	$wkst  = (string) ( $rule['WKST'] ?? 'MO' );
	$order = AXISMUNDI_CAL_RRULE_WEEKDAYS;
	$pivot = array_search( $wkst, $order, true );
	if ( false !== $pivot && $pivot > 0 ) {
		$order = array_merge( array_slice( $order, (int) $pivot ), array_slice( $order, 0, (int) $pivot ) );
	}
	// The week containing the cursor, measured from the configured week start. This is why WKST
	// matters: with INTERVAL >= 2 it decides which days fall in the same week as the anchor.
	$cursor_index = (int) array_search( strtoupper( $cursor->format( 'D' ) ) === 'THU' ? 'TH' : substr( strtoupper( $cursor->format( 'D' ) ), 0, 2 ), $order, true );
	$week_start   = $cursor->modify( '-' . $cursor_index . ' days' );

	$out = array();
	foreach ( $byday as $entry ) {
		$index = array_search( $entry['day'], $order, true );
		if ( false === $index ) {
			continue;
		}
		$day = $week_start->modify( '+' . (int) $index . ' days' );
		$out[] = axismundi_cal_at_time( $day, $time, $zone );
	}
	return $out;
}

/**
 * Start times within one month.
 *
 * @param int                                          $year    Year.
 * @param int                                          $month   Month.
 * @param array<int,array{ordinal:int|null,day:string}> $byday   Weekday selection.
 * @param int[]                                        $bymday  Month-day selection.
 * @param string                                       $time    Wall time to apply.
 * @param DateTimeZone                                 $zone    Event zone.
 * @param DateTimeImmutable                            $dtstart Series start.
 * @return DateTimeImmutable[]
 */
function axismundi_cal_month_candidates( int $year, int $month, array $byday, array $bymday, string $time, DateTimeZone $zone, DateTimeImmutable $dtstart ) : array {
	$days_in_month = (int) gmdate( 't', (int) gmmktime( 0, 0, 0, $month, 1, $year ) );
	$selected      = array();

	foreach ( $bymday as $day ) {
		$resolved = $day > 0 ? $day : $days_in_month + $day + 1;
		if ( $resolved >= 1 && $resolved <= $days_in_month ) {
			$selected[] = $resolved;
		}
	}

	foreach ( $byday as $entry ) {
		$matching = array();
		for ( $day = 1; $day <= $days_in_month; $day++ ) {
			$probe = axismundi_cal_local_date( $year, $month, $day, $time, $zone );
			if ( null === $probe ) {
				continue;
			}
			$token = strtoupper( substr( $probe->format( 'D' ), 0, 2 ) );
			$token = 'TH' === $token && 'Thu' !== $probe->format( 'D' ) ? $token : $token;
			if ( axismundi_cal_weekday_token( $probe ) === $entry['day'] ) {
				$matching[] = $day;
			}
		}
		if ( null === $entry['ordinal'] ) {
			$selected = array_merge( $selected, $matching );
			continue;
		}
		// `1SA` is the first Saturday, `-1FR` the last Friday. An ordinal past the end of the month
		// selects nothing rather than clamping, since "the fifth Monday" of a month without one did
		// not happen.
		$index = $entry['ordinal'] > 0 ? $entry['ordinal'] - 1 : count( $matching ) + $entry['ordinal'];
		if ( isset( $matching[ $index ] ) ) {
			$selected[] = $matching[ $index ];
		}
	}

	if ( empty( $byday ) && empty( $bymday ) ) {
		$selected[] = (int) $dtstart->format( 'j' );
	}

	$out = array();
	foreach ( array_unique( $selected ) as $day ) {
		$date = axismundi_cal_local_date( $year, $month, (int) $day, $time, $zone );
		if ( null !== $date ) {
			$out[] = $date;
		}
	}
	return $out;
}

/**
 * The RFC 5545 weekday token for a date.
 *
 * @param DateTimeImmutable $date Date.
 * @return string
 */
function axismundi_cal_weekday_token( DateTimeImmutable $date ) : string {
	return AXISMUNDI_CAL_RRULE_WEEKDAYS[ (int) $date->format( 'N' ) - 1 ];
}

/**
 * Build a local date at a wall time, or null when the date does not exist.
 *
 * @param int          $year  Year.
 * @param int          $month Month.
 * @param int          $day   Day.
 * @param string       $time  Wall time.
 * @param DateTimeZone $zone  Zone.
 * @return DateTimeImmutable|null
 */
function axismundi_cal_local_date( int $year, int $month, int $day, string $time, DateTimeZone $zone ) : ?DateTimeImmutable {
	try {
		$date = new DateTimeImmutable( sprintf( '%04d-%02d-%02d %s', $year, $month, $day, $time ), $zone );
	} catch ( Exception $error ) {
		return null;
	}
	// PHP rolls 31 February forward into March rather than refusing it, so a rolled date is
	// discarded: a rule asking for a day the month does not have selects nothing that month.
	if ( (int) $date->format( 'j' ) !== $day || (int) $date->format( 'n' ) !== $month ) {
		return null;
	}
	return $date;
}

/**
 * Apply a wall time to a date in a zone.
 *
 * @param DateTimeImmutable $date Date.
 * @param string            $time Wall time.
 * @param DateTimeZone      $zone Zone.
 * @return DateTimeImmutable
 */
function axismundi_cal_at_time( DateTimeImmutable $date, string $time, DateTimeZone $zone ) : DateTimeImmutable {
	$built = axismundi_cal_local_date( (int) $date->format( 'Y' ), (int) $date->format( 'n' ), (int) $date->format( 'j' ), $time, $zone );
	return $built ?? $date;
}

/**
 * Move the period anchor on by one interval.
 *
 * @param DateTimeImmutable $cursor   Anchor.
 * @param string            $freq     Frequency.
 * @param int               $interval Interval.
 * @return DateTimeImmutable
 */
function axismundi_cal_advance( DateTimeImmutable $cursor, string $freq, int $interval ) : DateTimeImmutable {
	switch ( $freq ) {
		case 'DAILY':
			return $cursor->modify( '+' . $interval . ' days' );
		case 'WEEKLY':
			return $cursor->modify( '+' . ( $interval * 7 ) . ' days' );
		case 'YEARLY':
			return $cursor->modify( '+' . $interval . ' years' );
		default:
			// Anchored to the first of the month so that stepping from the 31st does not skip the
			// months that have no 31st.
			return $cursor->modify( 'first day of this month' )->modify( '+' . $interval . ' months' )
				->setDate(
					(int) $cursor->modify( 'first day of this month' )->modify( '+' . $interval . ' months' )->format( 'Y' ),
					(int) $cursor->modify( 'first day of this month' )->modify( '+' . $interval . ' months' )->format( 'n' ),
					1
				);
	}
}

/**
 * The rule's UNTIL as a local instant, or null.
 *
 * @param array<string,mixed> $rule Checked rule.
 * @param DateTimeZone        $zone Event zone.
 * @return DateTimeImmutable|null
 */
function axismundi_cal_rule_until( array $rule, DateTimeZone $zone ) : ?DateTimeImmutable {
	if ( ! isset( $rule['UNTIL'] ) ) {
		return null;
	}
	$value = (string) $rule['UNTIL'];
	try {
		if ( preg_match( '/^[0-9]{8}$/', $value ) ) {
			return new DateTimeImmutable( substr( $value, 0, 4 ) . '-' . substr( $value, 4, 2 ) . '-' . substr( $value, 6, 2 ) . ' 23:59:59', $zone );
		}
		$utc = new DateTimeImmutable(
			substr( $value, 0, 4 ) . '-' . substr( $value, 4, 2 ) . '-' . substr( $value, 6, 2 ) . ' ' .
			substr( $value, 9, 2 ) . ':' . substr( $value, 11, 2 ) . ':' . substr( $value, 13, 2 ),
			new DateTimeZone( str_ends_with( $value, 'Z' ) ? 'UTC' : $zone->getName() )
		);
		return $utc->setTimezone( $zone );
	} catch ( Exception $error ) {
		return null;
	}
}

/**
 * The stable local identity of one instance.
 *
 * @param DateTimeImmutable $start   Local start.
 * @param bool              $all_day Whether the schedule is all-day.
 * @return string
 */
function axismundi_cal_recurrence_id( DateTimeImmutable $start, bool $all_day ) : string {
	return $all_day ? $start->format( 'Ymd' ) : $start->format( 'Ymd\THis' );
}

/**
 * The schedule's timezone.
 *
 * @param array<string,mixed> $schedule Schedule row.
 * @return DateTimeZone|null
 */
function axismundi_cal_schedule_zone( array $schedule ) : ?DateTimeZone {
	try {
		return new DateTimeZone( (string) ( $schedule['timezone'] ?? '' ) );
	} catch ( Exception $error ) {
		return null;
	}
}

/**
 * Build one occurrence from a local start.
 *
 * @param array<string,mixed> $schedule Schedule row.
 * @param DateTimeImmutable   $start    Local start.
 * @param DateInterval        $duration Duration.
 * @param bool                $all_day  All-day flag.
 * @return array<string,mixed>
 */
function axismundi_cal_build_occurrence( array $schedule, DateTimeImmutable $start, DateInterval $duration, bool $all_day ) : array {
	$utc = new DateTimeZone( 'UTC' );
	$end = axismundi_cal_add_civil( $start, $duration );
	/*
	 * An Event that ends somewhere else. The stored civil end belongs to the arrival zone, so it is
	 * placed there rather than carried forward from the departure zone -- a flight landing at 11:00 in
	 * New York lands at 11:00 in New York, and adding its length to a Seoul clock says otherwise.
	 *
	 * Only a single occurrence can reach this: the writer refuses a second zone on a repeating Event
	 * rather than deciding what a recurrence carries across two sets of clock changes.
	 */
	$end_zone = trim( (string) ( $schedule['end_timezone'] ?? '' ) );
	if ( '' !== $end_zone && ! $all_day ) {
		try {
			$end = new DateTimeImmutable( (string) $schedule['dtend_local'], new DateTimeZone( $end_zone ) );
		} catch ( Exception $error ) {
			$end = axismundi_cal_add_civil( $start, $duration );
		}
	}
	return array(
		'schedule_id'       => (int) ( $schedule['id'] ?? 0 ),
		'recurrence_id'     => axismundi_cal_recurrence_id( $start, $all_day ),
		'start_local'       => $start->format( 'Y-m-d H:i:s' ),
		'end_local'         => $end->format( 'Y-m-d H:i:s' ),
		'start_utc'         => $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
		'end_utc'           => $end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
		'status'            => 'scheduled',
		'origin'            => 'rule',
		'location_place_id' => $schedule['location_place_id'] ?? null,
		'location_text'     => (string) ( $schedule['location_text'] ?? '' ),
	);
}

/**
 * One date at a stated time of day, in a stated zone.
 *
 * Where the zone has no such moment, it places it -- and that placement stays local to the day it
 * happened on, because the next candidate is built from the date again rather than from this answer.
 *
 * @param DateTimeImmutable $day  Any moment on the wanted date.
 * @param string            $time `H:i:s`.
 * @param DateTimeZone      $zone Zone.
 * @return DateTimeImmutable
 */
function axismundi_cal_at_civil_time( DateTimeImmutable $day, string $time, DateTimeZone $zone ) : DateTimeImmutable {
	try {
		return new DateTimeImmutable( $day->format( 'Y-m-d' ) . ' ' . $time, $zone );
	} catch ( Exception $error ) {
		return $day;
	}
}

/**
 * How far apart two civil times are, measured on a clock that never changes.
 *
 * UTC is used as a calendar with no transitions in it, not as a time. An event written as
 * 01:00-03:00 is two hours on the wall whatever the zone does that night, and that is the quantity a
 * recurrence carries from one occurrence to the next.
 *
 * @param string $start_local Civil start, `Y-m-d H:i:s`.
 * @param string $end_local   Civil end, `Y-m-d H:i:s`.
 * @return DateInterval
 */
function axismundi_cal_civil_interval( string $start_local, string $end_local ) : DateInterval {
	$fixed = new DateTimeZone( 'UTC' );
	try {
		$from = new DateTimeImmutable( $start_local, $fixed );
		$to   = new DateTimeImmutable( $end_local, $fixed );
	} catch ( Exception $error ) {
		return new DateInterval( 'PT0S' );
	}
	return $from->diff( $to );
}

/**
 * Move a zoned time forward by a civil interval, keeping the answer on the wall clock.
 *
 * Adding to a zoned instant moves real time, so an interval spanning a transition lands an hour out.
 * The arithmetic is done on the civil value and the result is handed back to the zone to place --
 * which is the one thing a zone is for, and it should do it once.
 *
 * @param DateTimeImmutable $start    Zoned start.
 * @param DateInterval      $duration Civil interval.
 * @return DateTimeImmutable
 */
function axismundi_cal_add_civil( DateTimeImmutable $start, DateInterval $duration ) : DateTimeImmutable {
	$fixed = new DateTimeZone( 'UTC' );
	$civil = ( new DateTimeImmutable( $start->format( 'Y-m-d H:i:s' ), $fixed ) )->add( $duration );
	try {
		return new DateTimeImmutable( $civil->format( 'Y-m-d H:i:s' ), $start->getTimezone() );
	} catch ( Exception $error ) {
		return $start->add( $duration );
	}
}

/**
 * Overrides stored against a schedule, keyed by recurrence id.
 *
 * @param int $schedule_id Schedule id.
 * @return array<string,array<string,mixed>>
 */
function axismundi_cal_overrides( int $schedule_id ) : array {
	global $wpdb;
	if ( $schedule_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_occurrences_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- authored exceptions for one schedule.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE schedule_id = %d AND origin <> 'rule'", $schedule_id ), ARRAY_A );
	$out  = array();
	foreach ( $rows as $row ) {
		$out[ (string) $row['recurrence_id'] ] = $row;
	}
	return $out;
}

/**
 * Apply an authored exception to a rule-derived occurrence.
 *
 * @param array<string,mixed> $occurrence Occurrence.
 * @param array<string,mixed> $override   Stored exception.
 * @param DateTimeZone        $zone       Event zone.
 * @param bool                $all_day    All-day flag.
 * @return array<string,mixed>
 */
function axismundi_cal_apply_override( array $occurrence, array $override, DateTimeZone $zone, bool $all_day ) : array {
	$occurrence['status'] = (string) $override['status'];
	$occurrence['origin'] = (string) $override['origin'];
	if ( '0000-00-00 00:00:00' !== (string) $override['start_local'] && '' !== (string) $override['start_local'] ) {
		$moved = axismundi_cal_override_times( $override, $zone );
		if ( null !== $moved ) {
			$occurrence = array_merge( $occurrence, $moved );
		}
	}
	if ( null !== $override['location_place_id'] || '' !== (string) $override['location_text'] ) {
		$occurrence['location_place_id'] = $override['location_place_id'];
		$occurrence['location_text']     = (string) $override['location_text'];
	}
	// The identity stays the rule's, not the moved time's: this is still the answer to "which
	// Saturday?", which is what RECURRENCE-ID means and what a reply to an invitation refers to.
	return $occurrence;
}

/**
 * Recompute UTC from an override's local times.
 *
 * @param array<string,mixed> $override Stored exception.
 * @param DateTimeZone        $zone     Event zone.
 * @return array<string,string>|null
 */
function axismundi_cal_override_times( array $override, DateTimeZone $zone ) : ?array {
	try {
		$utc   = new DateTimeZone( 'UTC' );
		$start = new DateTimeImmutable( (string) $override['start_local'], $zone );
		$end   = new DateTimeImmutable( (string) $override['end_local'], $zone );
	} catch ( Exception $error ) {
		return null;
	}
	return array(
		'start_local' => $start->format( 'Y-m-d H:i:s' ),
		'end_local'   => $end->format( 'Y-m-d H:i:s' ),
		'start_utc'   => $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
		'end_utc'     => $end->setTimezone( $utc )->format( 'Y-m-d H:i:s' ),
	);
}

/**
 * Build an occurrence that exists only as an authored date.
 *
 * @param array<string,mixed> $schedule Schedule row.
 * @param array<string,mixed> $override Stored exception.
 * @param bool                $all_day  All-day flag.
 * @return array<string,mixed>|null
 */
function axismundi_cal_override_occurrence( array $schedule, array $override, bool $all_day ) : ?array {
	$zone = axismundi_cal_schedule_zone( $schedule );
	if ( ! $zone instanceof DateTimeZone ) {
		return null;
	}
	$times = axismundi_cal_override_times( $override, $zone );
	if ( null === $times ) {
		return null;
	}
	return array_merge(
		array(
			'schedule_id'       => (int) $override['schedule_id'],
			'recurrence_id'     => (string) $override['recurrence_id'],
			'status'            => (string) $override['status'],
			'origin'            => (string) $override['origin'],
			'location_place_id' => $override['location_place_id'] ?? ( $schedule['location_place_id'] ?? null ),
			'location_text'     => '' !== (string) $override['location_text'] ? (string) $override['location_text'] : (string) ( $schedule['location_text'] ?? '' ),
		),
		$times
	);
}

/**
 * Whether an occurrence overlaps the requested window.
 *
 * Overlap rather than containment, so an event already under way when the window opens is still
 * part of that day or month.
 *
 * @param array<string,mixed> $occurrence Occurrence.
 * @param DateTimeImmutable   $from       Window start, UTC.
 * @param DateTimeImmutable   $to         Window end, UTC.
 * @return bool
 */
function axismundi_cal_in_range( array $occurrence, DateTimeImmutable $from, DateTimeImmutable $to ) : bool {
	try {
		$utc   = new DateTimeZone( 'UTC' );
		$start = new DateTimeImmutable( (string) $occurrence['start_utc'], $utc );
		$end   = new DateTimeImmutable( (string) $occurrence['end_utc'], $utc );
	} catch ( Exception $error ) {
		return false;
	}
	return $end > $from && $start < $to;
}

/**
 * How far ahead rule-derived instances are kept in the table.
 *
 * The table is a cache for the range people actually look at. Materializing an unbounded weekly rule
 * to the end of time would write hundreds of thousands of rows to answer questions nobody asked, and
 * the correct answer for any range is computable anyway.
 */
const AXISMUNDI_CAL_MATERIALIZE_MONTHS = 18;

/**
 * Write the rule-derived instances for a schedule into the cache.
 *
 * Rebuild rather than merge, but only of `origin = 'rule'` rows. Those are a materialization of
 * something recomputable, so discarding them loses nothing; `rdate`, `override` and cancelled rows
 * are authored facts that exist in no other place and are never touched here. That distinction is
 * what makes the cache safe to throw away, and it is the difference between a rebuild and losing
 * every cancellation an editor ever made.
 *
 * Idempotent: the same schedule materialized twice produces the same rows, because each is keyed by
 * `(schedule_id, recurrence_id)` and the recurrence id is derived from the rule rather than from
 * when the rebuild ran.
 *
 * @param int $schedule_id Schedule id.
 * @return int Number of rule-derived rows written.
 */
function axismundi_cal_materialize( int $schedule_id ) : int {
	global $wpdb;
	if ( $schedule_id <= 0 || ! axismundi_cal_ready() ) {
		return 0;
	}
	$schedules = axismundi_cal_schedules_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- primary-key lookup in this plugin's own table.
	$schedule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$schedules} WHERE id = %d", $schedule_id ), ARRAY_A );
	if ( ! is_array( $schedule ) ) {
		return 0;
	}

	$table = axismundi_cal_occurrences_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- discarding only the recomputable rows.
	$wpdb->delete( $table, array( 'schedule_id' => $schedule_id, 'origin' => 'rule' ) );

	// From the series start rather than from today, so a range query about the past is answered from
	// the same rows as one about next week.
	$from    = (string) ( axismundi_cal_to_utc( (string) $schedule['dtstart_local'], (string) $schedule['timezone'] ) ?? gmdate( 'Y-m-d H:i:s' ) );
	$through = gmdate( 'Y-m-d H:i:s', strtotime( '+' . AXISMUNDI_CAL_MATERIALIZE_MONTHS . ' months' ) );
	if ( strtotime( $through ) <= strtotime( $from ) ) {
		$through = gmdate( 'Y-m-d H:i:s', strtotime( $from . ' +1 day' ) );
	}

	$written = 0;
	$now     = current_time( 'mysql', true );
	foreach ( axismundi_cal_expand( $schedule, $from, $through ) as $occurrence ) {
		// An instance an editor has already acted on is represented by its authored row, which is
		// still present; writing a rule row beside it would collide on the unique key and, worse,
		// would be a second opinion about the same instance.
		if ( 'rule' !== (string) $occurrence['origin'] ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$inserted = $wpdb->insert(
			$table,
			array(
				'schedule_id'       => $schedule_id,
				'recurrence_id'     => (string) $occurrence['recurrence_id'],
				'start_utc'         => (string) $occurrence['start_utc'],
				'end_utc'           => (string) $occurrence['end_utc'],
				'start_local'       => (string) $occurrence['start_local'],
				'end_local'         => (string) $occurrence['end_local'],
				'status'            => (string) $occurrence['status'],
				'origin'            => 'rule',
				'location_place_id' => $occurrence['location_place_id'],
				'location_text'     => (string) $occurrence['location_text'],
				'override_json'     => '',
				'created_at'        => $now,
				'updated_at'        => $now,
			)
		);
		if ( false !== $inserted ) {
			++$written;
		}
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	// Both edges are recorded, not just the far one. A window is only usable if the request lies
	// inside it at both ends, and a range that reaches back before the cache begins is the case
	// that silently returns too few rows rather than none.
	$wpdb->update( $schedules, array( 'materialized_from_utc' => $from, 'materialized_until_utc' => $through ), array( 'id' => $schedule_id ) );
	return $written;
}

/**
 * Read cached occurrences for a schedule within a range.
 *
 * The cached counterpart of `axismundi_cal_expand()`, and required to agree with it inside the
 * materialized horizon. Callers that may ask beyond the horizon should expand instead; a cache that
 * silently answers "nothing" past its own edge is how a calendar loses next year.
 *
 * @param int    $schedule_id Schedule id.
 * @param string $from_utc    Range start, UTC.
 * @param string $to_utc      Range end, UTC.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_cached_range( int $schedule_id, string $from_utc, string $to_utc ) : array {
	global $wpdb;
	if ( $schedule_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_occurrences_table();
	// Overlap, not containment, so an instance already under way when the window opens is included,
	// matching what the expander does.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- range query over this plugin's own table.
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE schedule_id = %d AND end_utc > %s AND start_utc < %s ORDER BY start_utc ASC",
			$schedule_id,
			$from_utc,
			$to_utc
		),
		ARRAY_A
	);
}

/**
 * The occurrences of a schedule in a range, from the cache where it reaches and computed where it
 * does not.
 *
 * The contract is that the answer does not depend on how much of the series happens to be
 * materialized. A cache that silently answers "nothing" past its own edge is how a calendar loses
 * next year, and one that answers "nothing" before its edge loses the archive -- so coverage is
 * judged at both ends, and any part of the request the cache does not reach is expanded live.
 *
 * Merged by `recurrence_id`, which is why that identity has to be stable: it is what lets a cached
 * row and a computed row be recognised as the same instance rather than appearing twice.
 *
 * @param array<string,mixed> $schedule Schedule row.
 * @param string              $from_utc Range start, UTC.
 * @param string              $to_utc   Range end, UTC.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_range( array $schedule, string $from_utc, string $to_utc ) : array {
	$schedule_id = (int) ( $schedule['id'] ?? 0 );
	$covered_from = (string) ( $schedule['materialized_from_utc'] ?? '' );
	$covered_to   = (string) ( $schedule['materialized_until_utc'] ?? '' );

	// Nothing materialized, or no identity to look rows up by: compute the whole answer.
	if ( $schedule_id <= 0 || '' === $covered_from || '' === $covered_to ) {
		return axismundi_cal_expand( $schedule, $from_utc, $to_utc );
	}

	$from = strtotime( $from_utc );
	$to   = strtotime( $to_utc );
	if ( false === $from || false === $to || $to <= $from ) {
		return array();
	}
	$cov_from = strtotime( $covered_from );
	$cov_to   = strtotime( $covered_to );

	$merged = array();

	// The part the cache reaches.
	$overlap_from = max( $from, $cov_from );
	$overlap_to   = min( $to, $cov_to );
	if ( $overlap_to > $overlap_from ) {
		foreach ( axismundi_cal_cached_range( $schedule_id, gmdate( 'Y-m-d H:i:s', $overlap_from ), gmdate( 'Y-m-d H:i:s', $overlap_to ) ) as $row ) {
			$merged[ (string) $row['recurrence_id'] ] = $row;
		}
	}

	// The parts it does not, at either end.
	$gaps = array();
	if ( $from < $cov_from ) {
		$gaps[] = array( $from, min( $to, $cov_from ) );
	}
	if ( $to > $cov_to ) {
		$gaps[] = array( max( $from, $cov_to ), $to );
	}
	foreach ( $gaps as $gap ) {
		if ( $gap[1] <= $gap[0] ) {
			continue;
		}
		foreach ( axismundi_cal_expand( $schedule, gmdate( 'Y-m-d H:i:s', $gap[0] ), gmdate( 'Y-m-d H:i:s', $gap[1] ) ) as $occurrence ) {
			// The cached row wins where both exist: it is the one carrying any stored override, and
			// keeping one of the two makes the merge order irrelevant.
			if ( ! isset( $merged[ (string) $occurrence['recurrence_id'] ] ) ) {
				$merged[ (string) $occurrence['recurrence_id'] ] = $occurrence;
			}
		}
	}

	$out = array_values( $merged );
	usort( $out, static fn( array $a, array $b ) : int => strcmp( (string) $a['start_utc'], (string) $b['start_utc'] ) );
	return $out;
}

/**
 * Whether a schedule repeats.
 *
 * @param array<string,mixed>|null $schedule Schedule row.
 * @return bool
 */
function axismundi_cal_schedule_is_recurring( ?array $schedule ) : bool {
	return is_array( $schedule ) && '' !== trim( (string) ( $schedule['rrule'] ?? '' ) );
}
