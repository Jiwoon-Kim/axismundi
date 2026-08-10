<?php
/**
 * Occurrences across every Event, for a range.
 *
 * The calendar grid asks a different question from the projection: not "may this be sent to other
 * servers?" but "may this person see it on this site?". Those answers differ deliberately for
 * recurring Events -- withheld from federation because FEP-8a8e describes a single occurrence, and
 * shown here because the local calendar has no such limitation. Keeping the two rules apart is the
 * point; collapsing them would either hide series from their own site or publish them wrongly.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The most occurrences one range query will return.
 *
 * A month of a busy site is tens of rows; a bound is here so that a wide range or a pathological
 * rule degrades into a truncated view rather than an exhausted request.
 */
const AXISMUNDI_CAL_RANGE_MAX = 500;

/**
 * Whether an Event may appear on this site's calendar.
 *
 * Deliberately not `axismundi_cal_event_visible()`, which answers the federation question and
 * withholds recurring Events. Password-protected and privately-viewable Events are excluded because
 * the grid is a public surface and a title is enough to disclose one.
 *
 * @param WP_Post $post Event post.
 * @return bool
 */
function axismundi_cal_event_listable( WP_Post $post ) : bool {
	return AXISMUNDI_CAL_EVENT_POST_TYPE === $post->post_type
		&& 'publish' === $post->post_status
		&& '' === (string) $post->post_password
		&& is_post_publicly_viewable( $post );
}

/**
 * Every listable occurrence between two UTC instants.
 *
 * Each schedule is resolved through the range API rather than by reading the occurrence table
 * directly, so a month beyond the materialized horizon is answered by computation instead of coming
 * back empty. That is the whole reason the range API judges coverage rather than trusting the cache.
 *
 * @param string $from_utc    Range start, `Y-m-d H:i:s` UTC.
 * @param string $to_utc      Range end, `Y-m-d H:i:s` UTC.
 * @param int    $limit       Maximum occurrences.
 * @param int    $calendar_id Restrict to one owning Calendar, or 0 for the whole site.
 * @return array<int,array<string,mixed>> Occurrences with their Event, ordered by start.
 */
function axismundi_cal_occurrences_in_range( string $from_utc, string $to_utc, int $limit = AXISMUNDI_CAL_RANGE_MAX, int $calendar_id = 0 ) : array {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return array();
	}
	$schedules = axismundi_cal_schedules_table();
	/*
	 * Schedules whose series could still be running in the window: those that start before it ends.
	 * The end is not filtered on, because a rule bounded by COUNT or UNTIL carries no end date in
	 * this table -- only the expansion knows where it stops, and it is cheap for it to say so.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- range query over this plugin's own table.
	$sql    = "SELECT * FROM {$schedules} WHERE dtstart_local <= %s";
	$params = array( $to_utc );
	if ( $calendar_id > 0 ) {
		$sql     .= ' AND calendar_id = %d';
		$params[] = $calendar_id;
	}
	$sql .= ' ORDER BY dtstart_local ASC';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- range query over this plugin's own table.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

	$out = array();
	foreach ( $rows as $schedule ) {
		$post = get_post( (int) $schedule['event_post_id'] );
		if ( ! $post instanceof WP_Post || ! axismundi_cal_event_listable( $post ) ) {
			continue;
		}
		foreach ( axismundi_cal_range( $schedule, $from_utc, $to_utc ) as $occurrence ) {
			$out[] = array_merge(
				$occurrence,
				array(
					'post_id'   => (int) $post->ID,
					'title'     => get_the_title( $post ),
					'permalink' => (string) get_permalink( $post ),
					'all_day'   => (int) $schedule['all_day'],
					'recurring' => axismundi_cal_schedule_is_recurring( $schedule ),
				)
			);
			if ( count( $out ) >= $limit ) {
				break 2;
			}
		}
	}

	usort( $out, static fn( array $a, array $b ) : int => strcmp( (string) $a['start_utc'], (string) $b['start_utc'] ) );
	return $out;
}

/**
 * Group occurrences by the local date they fall on.
 *
 * The grid is laid out in the reader's timezone while each Event stores the one it happens in, so an
 * event at 08:00 in Seoul belongs to the previous day for a reader in Europe. Grouping therefore
 * happens on the display zone rather than on the event's, which is what makes a grid agree with the
 * clock of the person reading it.
 *
 * All-day entries are the exception and are not converted at all -- see below.
 *
 * @param array<int,array<string,mixed>> $occurrences Occurrences.
 * @param DateTimeZone                   $display     Display timezone.
 * @return array<string,array<int,array<string,mixed>>> Keyed by `Y-m-d`.
 */
function axismundi_cal_group_by_day( array $occurrences, DateTimeZone $display ) : array {
	$utc  = new DateTimeZone( 'UTC' );
	$days = array();
	foreach ( $occurrences as $occurrence ) {
		/*
		 * An all-day entry is a civil date, not an instant, so it is never converted. Liberation Day
		 * is the fifteenth of August in Seoul and in New York alike; converting it put a national
		 * holiday on the previous day for every reader west of UTC. Its end is exclusive, as DTEND
		 * is for dates, so a one-day event covers one day rather than two.
		 */
		if ( ! empty( $occurrence['all_day'] ) ) {
			try {
				$cursor = new DateTimeImmutable( substr( (string) $occurrence['start_local'], 0, 10 ), $utc );
				$stop   = new DateTimeImmutable( substr( (string) $occurrence['end_local'], 0, 10 ), $utc );
			} catch ( Exception $error ) {
				continue;
			}
			if ( $stop <= $cursor ) {
				$stop = $cursor->modify( '+1 day' );
			}
			while ( $cursor < $stop ) {
				$days[ $cursor->format( 'Y-m-d' ) ][] = $occurrence;
				$cursor = $cursor->modify( '+1 day' );
			}
			continue;
		}
		try {
			$start = new DateTimeImmutable( (string) $occurrence['start_utc'], $utc );
			$end   = new DateTimeImmutable( (string) $occurrence['end_utc'], $utc );
		} catch ( Exception $error ) {
			continue;
		}
		// An event spanning midnight appears on each day it touches, because a reader looking at
		// Tuesday should see the thing that is still going on at breakfast.
		$cursor = $start->setTimezone( $display )->setTime( 0, 0 );
		$last   = $end->setTimezone( $display );
		while ( $cursor <= $last ) {
			$days[ $cursor->format( 'Y-m-d' ) ][] = $occurrence;
			$cursor = $cursor->modify( '+1 day' );
			if ( $cursor->format( 'Y-m-d' ) === $last->format( 'Y-m-d' ) && $last->format( 'H:i:s' ) === '00:00:00' ) {
				// An end exactly at midnight belongs to the previous day, not to the one it touches
				// for zero seconds.
				break;
			}
		}
	}
	return $days;
}
