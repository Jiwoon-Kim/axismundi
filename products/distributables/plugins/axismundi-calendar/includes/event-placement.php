<?php
/**
 * Which Events appear on a Calendar that is not the one they were filed on.
 *
 * An Event has one home -- `schedules.calendar_id` -- and that has not changed. What this file adds
 * is the other way an Event reaches a calendar: somebody was invited to it, or asked to come, and it
 * shows up on their own calendar without being moved, copied, or re-filed. Google does the same, and
 * the consequence is visible there: if you are also subscribed to the calendar the Event lives on,
 * you see it twice. That is not a bug to be deduplicated away. Two true things are being said -- this
 * is on a calendar you follow, and this is something you were invited to -- and collapsing them would
 * lose whichever one somebody was looking for.
 *
 * Derived rather than stored. A row saying "(this calendar, this event, because of that
 * participation)" would be a second copy of facts already in the participation table, and the
 * calendar in it is never free: it is always the participant's own, which is immutable. A copy that
 * can only ever agree with its source is a copy that will eventually disagree with it, and it would
 * need a backfill for every participation ever recorded. When FEP-400e `Add` arrives -- a remote
 * object placed in an arbitrary collection by an act that is not a participation -- there is a real
 * fact with nowhere to live, and that is when a table earns its place.
 *
 * Declining does not remove anything. The Event stays on your calendar with your answer attached,
 * which is what makes "show declined events" a setting rather than a way to undo a refusal.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** Participation states that mean somebody said no. */
const AXISMUNDI_CAL_DECLINED_STATES = array( 'rejected', 'declined', 'withdrawn' );

/**
 * Whether one Actor wants Events they declined shown on their calendar.
 *
 * Kept on that Actor's own list entry for that calendar, because that is where every other "how this
 * calendar looks to me" answer already lives -- colour, name, whether it is shown at all. A person
 * hiding their declined invitations must not hide anybody else's.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $actor_uri   Viewing Actor.
 * @return bool
 */
function axismundi_cal_shows_declined_events( int $calendar_id, string $actor_uri ) : bool {
	$entry = axismundi_cal_list_entry( $calendar_id, $actor_uri );
	// Absent means nobody has said otherwise, and an invitation you have not dealt with should not
	// vanish because you never opened a settings screen.
	return ! is_array( $entry ) || 1 === (int) ( $entry['show_declined_events'] ?? 1 );
}

/**
 * Say whether declined Events are shown on one Actor's view of one Calendar.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $actor_uri   Viewing Actor.
 * @param bool   $show        Whether to show them.
 * @return int|WP_Error Entry id.
 */
function axismundi_cal_set_shows_declined_events( int $calendar_id, string $actor_uri, bool $show ) {
	return axismundi_cal_list_set( $calendar_id, $actor_uri, 'reader', array( 'show_declined_events' => $show ) );
}

/**
 * The Actor whose own calendar this is, or '' when it is not anybody's own.
 *
 * Participation puts an Event on the participant's *own* calendar and nowhere else. Asking which
 * Actor a calendar belongs to is therefore the whole of the placement rule, and a calendar somebody
 * merely owns among several does not collect their invitations.
 *
 * @param int $calendar_id Calendar id.
 * @return string
 */
function axismundi_cal_calendar_owner_of_record( int $calendar_id ) : string {
	$calendar = axismundi_cal_calendar_get( $calendar_id );
	if ( ! is_array( $calendar ) || 1 !== (int) ( $calendar['is_primary'] ?? 0 ) ) {
		return '';
	}
	return (string) $calendar['authority_actor_uri'];
}

/**
 * Events that appear on one Calendar because its Actor was invited to them or asked to come.
 *
 * Excludes Events already filed on this Calendar. Somebody who joins an Event on their own calendar
 * has one Event on one calendar, and the participation is a second reason for it to be there rather
 * than a second entry -- the duplicate that *is* meaningful is across two different calendars.
 *
 * @param int $calendar_id Calendar id.
 * @return int[] Event post ids.
 */
function axismundi_cal_placed_event_ids( int $calendar_id ) : array {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$actor_uri = axismundi_cal_calendar_owner_of_record( $calendar_id );
	if ( '' === $actor_uri ) {
		return array();
	}
	$participation = axismundi_cal_participation_table();
	$schedules     = axismundi_cal_schedules_table();
	$declined      = axismundi_cal_shows_declined_events( $calendar_id, $actor_uri )
		? array()
		: AXISMUNDI_CAL_DECLINED_STATES;

	$sql    = "SELECT p.event_post_id FROM {$participation} p
		 LEFT JOIN {$schedules} s ON s.event_post_id = p.event_post_id
		 WHERE p.actor_uri_hash = %s AND p.event_post_id IS NOT NULL
		   AND ( s.calendar_id IS NULL OR s.calendar_id <> %d )";
	$params = array( hash( 'sha256', $actor_uri ), $calendar_id );
	if ( array() !== $declined ) {
		$sql   .= ' AND p.state NOT IN ( ' . implode( ', ', array_fill( 0, count( $declined ), '%s' ) ) . ' )';
		$params = array_merge( $params, $declined );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed lookup across this plugin's own tables.
	return array_values( array_unique( array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $params ) ) ) ) );
}

/**
 * Why participation put one Event on one Calendar.
 *
 * Only the participation answer. Being filed on a calendar is an independent fact -- an Event with no
 * participation at all has it, and it comes from the schedule -- so returning it from here would make
 * one function the authority on two unrelated things and invite callers to ask this before checking
 * the home calendar. The two are joined in the query and nowhere else.
 *
 * @param int $calendar_id Calendar id.
 * @param int $post_id     Event post ID.
 * @return string `invited` | `joined` | '' when participation is not why it is here.
 */
function axismundi_cal_event_placement_reason( int $calendar_id, int $post_id ) : string {
	$actor_uri = axismundi_cal_calendar_owner_of_record( $calendar_id );
	if ( '' === $actor_uri ) {
		return '';
	}
	$participation = axismundi_cal_event_participation( $post_id, $actor_uri );
	if ( ! is_array( $participation ) ) {
		return '';
	}
	return 'invite' === (string) ( $participation['source'] ?? '' ) ? 'invited' : 'joined';
}
