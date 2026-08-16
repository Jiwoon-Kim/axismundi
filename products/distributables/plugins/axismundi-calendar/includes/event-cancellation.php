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
 * Tell the ledger, and through it everybody holding the Event.
 *
 * `Update`, not `Delete`. A cancelled Event has not gone away -- it is still there, still at the time
 * it was going to be, and now saying it is off. `Delete` would tell every peer to tombstone it, and a
 * guest whose calendar it disappeared from would have no way to learn why. That is a different act
 * with a different meaning, and this one is not it.
 *
 * @param int $post_id Event post ID.
 * @return void
 */
function axismundi_cal_record_event_cancellation( int $post_id ) : void {
	$host = axismundi_cal_event_owner_actor_uri( $post_id );
	if ( '' === $host ) {
		// Nothing to say it in the name of. The status is still stored; the Event simply has no Actor
		// to publish the change as, which is the same silence every other participation act keeps.
		return;
	}
	$event_uri = axismundi_cal_event_uri( $post_id );
	axismundi_cal_participation_activity(
		array( 'type' => 'Update', 'actor' => $host, 'object' => $event_uri ),
		// One act per cancellation of this Event. Cancelling, reinstating and cancelling again is a
		// question nothing here answers yet, and a key that collided would answer it by accident.
		'ax-cal-cancel:' . $event_uri
	);
}
add_action( 'axismundi_cal_event_cancelled', 'axismundi_cal_record_event_cancellation' );

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
