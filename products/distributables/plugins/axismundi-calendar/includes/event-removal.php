<?php
/**
 * Taking somebody off the list.
 *
 * The third of the acts that follow an answer, and the reason they are three rather than one `Undo`:
 * each ends something different, and the difference is visible to everybody involved.
 *
 *   organizer removes attendee  ends one person's attendance      the Event goes on without them
 *   event cancellation          ends the Event's own lifecycle    nobody is removed; nothing happens
 *   guest Undo                  ends the guest's own answer       their choice, and theirs to remake
 *
 * So this is not `Undo(Accept)`. An `Undo` is authored by whoever authored the Activity it addresses,
 * and an organizer undoing a guest's acceptance would be the host retracting the guest's own words --
 * the ledger would read as though the guest had changed their mind. ActivityStreams already has the
 * verb for this: `Remove`, whose subject is the one doing the removing.
 *
 * The removed row stays, in a state that is nobody's answer. Writing it as `rejected` or `withdrawn`
 * would say the guest declined an Event they were thrown out of, and deleting it would leave the
 * organizer with no record of an act they took and the guest with no way to see why the Event left
 * their calendar.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** States somebody can be removed from: they are on the list, in one way or another. */
const AXISMUNDI_CAL_REMOVABLE_STATES = array( 'accepted', 'tentative', 'tentative_rejected', 'rejected' );

/**
 * Take somebody off an Event's list.
 *
 * @param int    $post_id   Event post ID.
 * @param string $actor_uri The Actor being removed.
 * @return true|WP_Error
 */
function axismundi_cal_event_remove_attendee( int $post_id, string $actor_uri ) {
	$actor_uri = trim( $actor_uri );
	if ( '' === $actor_uri ) {
		return new WP_Error( 'ax_event_remove_actor', __( 'A removal needs somebody to remove.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( ! axismundi_cal_can_manage_participation( $post_id ) ) {
		return new WP_Error( 'ax_event_remove_denied', __( 'You cannot manage who is coming to this event.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}
	/*
	 * A called-off Event has no list to take anybody off. Its replies are the record of what people
	 * said while it was on, and editing them afterwards would rewrite that.
	 */
	$cancelled = axismundi_cal_event_cancellation_block( $post_id );
	if ( null !== $cancelled ) {
		return $cancelled;
	}
	$host = axismundi_cal_event_owner_actor_uri( $post_id );
	if ( '' === $host ) {
		return new WP_Error( 'ax_event_remove_no_host', __( 'This event has no host Actor to remove anybody on behalf of.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}
	if ( $host === $actor_uri ) {
		/*
		 * The host counted themselves in with their own `Join`, so taking themselves out is their own
		 * `Undo` -- and a co-manager removing the Actor the Event is published as would leave it running
		 * in the name of somebody it had thrown out.
		 */
		return new WP_Error( 'ax_event_remove_host', __( 'The host is not removed from their own event.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	$participation = axismundi_cal_event_participation( $post_id, $actor_uri );
	if ( ! is_array( $participation ) ) {
		return new WP_Error( 'ax_event_remove_missing', __( 'That person is not on this event\'s list.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	$state = (string) $participation['state'];
	if ( 'pending' === $state ) {
		/*
		 * Nothing has been agreed yet, and the two ends already have their own act for that: a request
		 * is refused, an invitation is withdrawn. Removing somebody who is still waiting would answer
		 * them with a third word that means neither.
		 */
		return 'invite' === (string) $participation['source']
			? new WP_Error( 'ax_event_remove_pending_invite', __( 'That invitation has not been answered; withdraw it instead.', 'axismundi-calendar' ), array( 'status' => 409 ) )
			: new WP_Error( 'ax_event_remove_pending_join', __( 'That request has not been answered; reject it instead.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	if ( ! in_array( $state, AXISMUNDI_CAL_REMOVABLE_STATES, true ) ) {
		return new WP_Error( 'ax_event_remove_state', __( 'That person is already off this event\'s list.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}

	/*
	 * `Remove`, addressed to the Event rather than to an attendees collection URI. FEP-8a8e's
	 * `attendees` is projected inline and has no id of its own, and naming a target nothing can fetch
	 * would be worse than naming the Event everybody involved already holds.
	 *
	 * Keyed on the participation that is ending, so removing the same person twice is one act -- and
	 * so that inviting them back and removing them again is a second one rather than a collision.
	 */
	$removal = axismundi_cal_participation_activity(
		array(
			'type'   => 'Remove',
			'actor'  => $host,
			'object' => $actor_uri,
			'target' => axismundi_cal_event_uri( $post_id ),
		),
		'ax-cal-remove:' . (string) $participation['initiating_activity_uri'] . ':' . $state
	);
	if ( is_wp_error( $removal ) ) {
		return $removal;
	}
	/*
	 * The Activity first, unlike an acceptance. A seat published before it exists is the failure there;
	 * here the risk runs the other way -- a row saying somebody was removed with nothing anywhere
	 * saying who removed them, or when, leaves the guest an Event that vanished for no stated reason.
	 */
	$set = axismundi_cal_event_participation_set( $post_id, $actor_uri, 'removed', $removal );
	if ( is_wp_error( $set ) ) {
		return $set;
	}
	/*
	 * The person it happened to, and the reason this act needs a notice more than any other here: the
	 * Event leaves their calendar. Without being told, what they experience is an evening they had
	 * planned around silently disappearing.
	 */
	axismundi_cal_notify( 'event_removed', $post_id, array( $actor_uri ), $host, $removal );
	return true;
}
