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
 * The owning Calendar is asked too. Post status alone says whether the Event was published, not
 * whether the collection it lives in is public -- and without this check a Calendar shared with
 * three people would have every one of its Events on the site's public grid and in its public feed.
 *
 * @param WP_Post $post Event post.
 * @return bool
 */
function axismundi_cal_event_listable( WP_Post $post ) : bool {
	if ( ! axismundi_cal_event_post_viewable( $post ) ) {
		return false;
	}
	$schedule = axismundi_cal_schedule_for_event( (int) $post->ID );
	if ( ! is_array( $schedule ) ) {
		return false;
	}
	/*
	 * The Event's own half of the two-axis rule. A private Event inside a public Calendar is withheld
	 * here, which is the one place that answers for the feed, the range query the block and the
	 * readable page both read, and the permalink guard -- so the five surfaces cannot disagree by one
	 * of them forgetting to ask.
	 *
	 * The more restrictive of the two always wins, and this is only ever the restrictive direction: an
	 * Event cannot open a Calendar that is closed, which is why there is no `public` value for it.
	 */
	$envelope = axismundi_cal_event_get( (int) $post->ID );
	if ( is_array( $envelope ) && 'private' === (string) ( $envelope['visibility'] ?? 'default' ) ) {
		return false;
	}
	return '' !== axismundi_cal_calendar_authority( (int) $schedule['calendar_id'] )
		&& axismundi_cal_calendar_is_listable( (int) $schedule['calendar_id'] );
}

/**
 * Whether the Event post itself is showable, leaving the Calendar out of it.
 *
 * The half of the question that is about this post: published, not password-protected, not on a
 * privately-viewable status. Separated from the Calendar half so an authorized reader of a private
 * Calendar can be served without anything having to bypass the public gate to do it -- a caller that
 * has already established permission asks this, and a caller that has not asks `event_listable()`.
 *
 * @param WP_Post $post Event post.
 * @return bool
 */
function axismundi_cal_event_post_viewable( WP_Post $post ) : bool {
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
	return axismundi_cal_collect_occurrences( $from_utc, $to_utc, $limit, $calendar_id, 'axismundi_cal_event_listable' );
}

/**
 * Every occurrence on one Calendar, with no permission check of its own.
 *
 * A serializer, in the same sense as `axismundi_cal_site_feed()`: it answers what is on a Calendar,
 * not who may ask. Only for callers that have already established a `reader` role -- which is the
 * whole reason it exists, since an authorized reader of a private Calendar must be served without
 * anything having to defeat the public gate to do it.
 *
 * Never reachable from an unauthenticated route. The REST API decides access first and calls this
 * second; anything that calls it without deciding is a bug in that caller, not here.
 *
 * @param int    $calendar_id Calendar id. Required -- there is no site-wide form, because a site-wide
 *                            question has no single Calendar to have authorized.
 * @param string $from_utc    Range start, `Y-m-d H:i:s` UTC.
 * @param string $to_utc      Range end, `Y-m-d H:i:s` UTC.
 * @param int    $limit       Maximum occurrences.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_calendar_occurrences( int $calendar_id, string $from_utc, string $to_utc, int $limit = AXISMUNDI_CAL_RANGE_MAX ) : array {
	if ( $calendar_id <= 0 ) {
		return array();
	}
	return axismundi_cal_collect_occurrences( $from_utc, $to_utc, $limit, $calendar_id, 'axismundi_cal_event_post_viewable' );
}

/**
 * The shared range walk, told which Events it may include.
 *
 * The gate is a parameter rather than a flag, so the two callers above read as two different
 * questions instead of one question with a bypass. A boolean `$include_private` here would sooner or
 * later be passed `true` by something that had not checked anything.
 *
 * @param string   $from_utc    Range start, `Y-m-d H:i:s` UTC.
 * @param string   $to_utc      Range end, `Y-m-d H:i:s` UTC.
 * @param int      $limit       Maximum occurrences.
 * @param int      $calendar_id Restrict to one owning Calendar, or 0 for the whole site.
 * @param callable $gate        Predicate over the Event post.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_collect_occurrences( string $from_utc, string $to_utc, int $limit, int $calendar_id, callable $gate ) : array {
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
		/*
		 * Filed here, or reaching this calendar because its Actor was invited or asked to come. The
		 * second set is not filed anywhere near here -- it stays on the calendar it was written on, and
		 * appears on this one as well, which is why this is an OR and not a moved row.
		 */
		$placed = axismundi_cal_placed_event_ids( $calendar_id );
		if ( array() === $placed ) {
			$sql     .= ' AND calendar_id = %d';
			$params[] = $calendar_id;
		} else {
			$sql     .= ' AND ( calendar_id = %d OR event_post_id IN ( ' . implode( ', ', array_fill( 0, count( $placed ), '%d' ) ) . ' ) )';
			$params   = array_merge( array( $to_utc, $calendar_id ), $placed );
		}
	}
	$sql .= ' ORDER BY dtstart_local ASC';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- range query over this plugin's own table.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

	$out = array();
	foreach ( $rows as $schedule ) {
		$post = get_post( (int) $schedule['event_post_id'] );
		if ( ! $post instanceof WP_Post || ! $gate( $post ) ) {
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
