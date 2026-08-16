<?php
/**
 * Calling it off.
 *
 * The second of the three acts that follow an answer, and the one that ends the Event itself rather
 * than anybody's place in it. Nobody is removed. Everybody who said they were coming still said it,
 * and the record of that is what an organizer, a guest, an audit and a dispute all need afterwards.
 *
 * So a cancellation deletes nothing -- not the Event, not the participation, not the placement on
 * anybody's calendar. There is no expiry that eventually sweeps them: cancelled is not "this never
 * happened", it is "this was scheduled and then called off", and the two are different facts. The
 * iCalendar `CANCEL`, the ActivityPub delivery and anything asking later what became of that evening
 * all read the same record.
 *
 * It stays on the calendars of the people who were coming, deliberately. Somebody who kept an evening
 * free needs to be told it is off, and an Event that vanished would leave them holding an empty
 * evening for a reason they cannot see. The default agenda stops showing it when the time it was
 * scheduled for has passed -- not because it was cancelled, but because that window is what "current
 * and upcoming" means, and browsing the date it was on still finds it.
 *
 * What does close is the Event's participation. A cancelled Event takes no more replies, its capacity
 * has nothing left to limit, and the guest list stops being published. That gate runs ahead of the
 * participant visibility policy rather than through it: whether a stranger may see who was coming is
 * a question about an Event that is still happening.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether one Actor wants Events that were called off shown on one of their calendars.
 *
 * The sibling of "show declined events", and kept for the same reason in the same place: it is one
 * person's view of one calendar, so hiding cancellations must not hide anybody else's.
 *
 * It shows them by default, and that default is the important half. Somebody who kept an evening
 * free is exactly who needs to see that it is off; an Event that quietly vanished would leave them
 * holding the evening and no reason. Turning it off is for afterwards -- a calendar somebody would
 * rather see clean -- and even then the Event is only hidden, never removed.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $actor_uri   Viewing Actor.
 * @return bool
 */
function axismundi_cal_shows_cancelled_events( int $calendar_id, string $actor_uri ) : bool {
	$entry = axismundi_cal_list_entry( $calendar_id, $actor_uri );
	return ! is_array( $entry ) || 1 === (int) ( $entry['show_cancelled_events'] ?? 1 );
}

/**
 * Say whether called-off Events are shown on one Actor's view of one Calendar.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $actor_uri   Viewing Actor.
 * @param bool   $show        Whether to show them.
 * @return int|WP_Error Entry id.
 */
function axismundi_cal_set_shows_cancelled_events( int $calendar_id, string $actor_uri, bool $show ) {
	return axismundi_cal_list_set( $calendar_id, $actor_uri, 'reader', array( 'show_cancelled_events' => $show ) );
}

/**
 * Whether an Event has been called off.
 *
 * @param int $post_id Event post ID.
 * @return bool
 */
function axismundi_cal_event_is_cancelled( int $post_id ) : bool {
	$envelope = axismundi_cal_event_get( $post_id );
	return is_array( $envelope ) && 'EventCancelled' === (string) $envelope['event_status'];
}

/**
 * Call an Event off.
 *
 * @param int $post_id Event post ID.
 * @return true|WP_Error
 */
function axismundi_cal_event_cancel( int $post_id ) {
	if ( ! axismundi_cal_can_manage_participation( $post_id ) ) {
		return new WP_Error( 'ax_event_cancel_denied', __( 'This event is not yours to call off.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}
	if ( ! is_array( axismundi_cal_event_get( $post_id ) ) ) {
		return new WP_Error( 'ax_event_cancel_missing', __( 'That event does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	if ( axismundi_cal_event_is_cancelled( $post_id ) ) {
		return new WP_Error( 'ax_event_cancel_already', __( 'That event has already been called off.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	// Through the envelope writer, which is what notices the transition -- so cancelling from here and
	// cancelling from the editor are the same act rather than two with different consequences.
	return axismundi_cal_event_save( $post_id, array( 'event_status' => 'EventCancelled' ) );
}

/**
 * Put a called-off Event back on.
 *
 * The status it returns to is stated rather than remembered. Nothing stores what an Event was before
 * it was cancelled, and a column that did would exist to answer one question on one path; naming the
 * status here is the same answer without the second copy -- and an Event that was tentative before
 * and comes back tentative is something its organizer knows and can say.
 *
 * The replies are exactly as they were. Everybody who had said they were coming still has, because
 * their answer was never touched -- and resetting them to `pending` would erase what people said in
 * the name of asking them again. Whether reinstating should re-ask is a product question about
 * notifications, not a reason to destroy answers.
 *
 * @param int    $post_id Event post ID.
 * @param string $status  Status to return to; anything but cancelled.
 * @return true|WP_Error
 */
function axismundi_cal_event_reinstate( int $post_id, string $status = 'EventScheduled' ) {
	if ( ! axismundi_cal_can_manage_participation( $post_id ) ) {
		return new WP_Error( 'ax_event_reinstate_denied', __( 'This event is not yours to put back on.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}
	if ( ! is_array( axismundi_cal_event_get( $post_id ) ) ) {
		return new WP_Error( 'ax_event_reinstate_missing', __( 'That event does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	if ( ! axismundi_cal_event_is_cancelled( $post_id ) ) {
		return new WP_Error( 'ax_event_reinstate_not_cancelled', __( 'That event has not been called off.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	if ( 'EventCancelled' === $status || ! in_array( $status, axismundi_cal_event_statuses(), true ) ) {
		return new WP_Error( 'ax_event_reinstate_status', __( 'An event is put back on as something other than cancelled.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	return axismundi_cal_event_save( $post_id, array( 'event_status' => $status ) );
}

/**
 * Tell the ledger, and through it everybody holding the Event.
 *
 * `Update` in both directions, and `Delete` in neither. A cancelled Event has not gone away -- it is
 * still there, still at the time it was going to be, and now saying it is off. `Delete` would tell
 * every peer to tombstone it, and a guest whose calendar it disappeared from would have no way to
 * learn why.
 *
 * Putting it back on is an `Update` too, rather than an `Undo` of the cancellation. The two are not
 * the same shape: an `Undo` retracts a relationship its author established -- a `Follow`, a `Join`, an
 * `Accept` -- and says it never stood. The cancellation is not a relationship and it did stand:
 * people were told, and some of them made other plans on the strength of it. What happened is that
 * the Event changed again, and that is what `Update` says.
 *
 * @param int $post_id Event post ID.
 * @return void
 */
function axismundi_cal_record_event_status_change( int $post_id ) : void {
	$host = axismundi_cal_event_owner_actor_uri( $post_id );
	if ( '' === $host ) {
		// Nothing to say it in the name of. The status is still stored; the Event simply has no Actor
		// to publish the change as, which is the same silence every other participation act keeps.
		return;
	}
	$schedule = axismundi_cal_schedule_for_event( $post_id );
	$envelope = axismundi_cal_event_get( $post_id );
	if ( ! is_array( $schedule ) || ! is_array( $envelope ) ) {
		return;
	}
	$event_uri = axismundi_cal_event_uri( $post_id );
	$update    = axismundi_cal_participation_activity(
		array( 'type' => 'Update', 'actor' => $host, 'object' => $event_uri ),
		/*
		 * One act per version of the Event. Keyed on the sequence rather than on the word `cancel`,
		 * because an Event can be called off, put back on and called off again -- and a key naming only
		 * the act would hand back the first cancellation, announcing a version of the Event that two
		 * changes have since replaced. The sequence is already the answer to "which version is this",
		 * so nothing here counts anything a second time.
		 */
		'ax-cal-status:' . $event_uri . ':' . (string) $envelope['event_status'] . ':' . (int) $schedule['sequence']
	);
	/*
	 * Everybody still holding it, which is the one notice here that is not addressed to a single
	 * person. An Event being called off is the piece of news somebody would otherwise get by turning
	 * up, and putting it back on is the one they would otherwise get by not turning up.
	 */
	axismundi_cal_notify(
		axismundi_cal_event_is_cancelled( $post_id ) ? 'event_cancelled' : 'event_reinstated',
		$post_id,
		axismundi_cal_event_lifecycle_audience( $post_id ),
		$host,
		is_wp_error( $update ) ? '' : $update
	);
}
add_action( 'axismundi_cal_event_cancelled', 'axismundi_cal_record_event_status_change' );
add_action( 'axismundi_cal_event_reinstated', 'axismundi_cal_record_event_status_change' );

/**
 * Refuse what a called-off Event can no longer take.
 *
 * One predicate for every participation path, so the answer cannot differ depending on which door
 * somebody came through: asking to come, being asked, answering either, and being taken off the list
 * are all about arrangements for an Event that is going ahead.
 *
 * The existing replies are left exactly as they are. They are what somebody said at the time, and
 * rewriting them to `removed` or `rejected` would put a cancellation into the mouths of the people
 * who had agreed to come.
 *
 * @param int $post_id Event post ID.
 * @return WP_Error|null Error when the Event is off, null when it is going ahead.
 */
function axismundi_cal_event_cancellation_block( int $post_id ) : ?WP_Error {
	if ( ! axismundi_cal_event_is_cancelled( $post_id ) ) {
		return null;
	}
	return new WP_Error( 'ax_event_cancelled', __( 'That event has been called off.', 'axismundi-calendar' ), array( 'status' => 409 ) );
}
