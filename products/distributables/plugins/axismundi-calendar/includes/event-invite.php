<?php
/**
 * Being asked to come, as opposed to asking.
 *
 * Join and Invite are the same relation reached from opposite ends, and the ledger already knew that:
 * one row per Actor per Event, with `source` saying who started it. What was missing was the second
 * end. A host could answer a request and nobody could extend one.
 *
 * The distinction that has to survive is who answers:
 *
 *   Join    the guest asks, the host answers.   `respond_to_join()` is the host's decision.
 *   Invite  the host asks, the guest answers.   `respond_to_invite()` is the guest's.
 *
 * Collapsing them would let a host accept an invitation on somebody's behalf, which is the one thing
 * an RSVP must not permit -- an attendance list nobody agreed to.
 *
 * And an invitation is itself the permission. `join_eligibility` decides who may ask; it must not
 * decide who may answer, or inviting somebody the Event would have turned away would produce an
 * invitation that cannot be accepted.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** What a guest may answer with. `tentative` is an answer, not an absence of one. */
const AXISMUNDI_CAL_INVITE_ANSWERS = array(
	'accept'    => 'accepted',
	'reject'    => 'rejected',
	'tentative' => 'tentative',
);

/**
 * Ask somebody to come.
 *
 * @param int    $post_id   Event post ID.
 * @param string $actor_uri Actor being invited.
 * @return string|WP_Error Resulting state.
 */
function axismundi_cal_event_invite( int $post_id, string $actor_uri ) {
	$actor_uri = trim( $actor_uri );
	if ( '' === $actor_uri ) {
		return new WP_Error( 'ax_event_invite_actor', __( 'An invitation needs somebody to invite.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( ! axismundi_cal_can_manage_participation( $post_id ) ) {
		return new WP_Error( 'ax_event_invite_denied', __( 'You cannot invite people to this event.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}
	$host = axismundi_cal_event_owner_actor_uri( $post_id );
	if ( '' === $host ) {
		return new WP_Error( 'ax_event_invite_no_host', __( 'This event has no host Actor to invite on behalf of.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}
	if ( $host === $actor_uri ) {
		// The host is already coming to their own Event; an invitation would be a reply to themselves.
		return new WP_Error( 'ax_event_invite_host', __( 'The host does not need an invitation.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	$existing = axismundi_cal_event_participation( $post_id, $actor_uri );
	if ( is_array( $existing ) && 'pending' !== (string) $existing['state'] ) {
		/*
		 * Somebody who already answered -- or already asked and was answered -- is not re-invited into a
		 * pending state. That would erase their reply and ask them again as though they had never said
		 * anything, which is what a reminder is for and this is not one.
		 */
		return new WP_Error( 'ax_event_invite_answered', __( 'That person has already replied about this event.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}

	$event_uri = axismundi_cal_event_uri( $post_id );
	$invite    = axismundi_cal_participation_activity(
		array( 'type' => 'Invite', 'actor' => $host, 'object' => $event_uri, 'target' => $actor_uri ),
		'ax-cal-invite:' . $event_uri . ':' . $actor_uri
	);
	if ( is_wp_error( $invite ) ) {
		return $invite;
	}
	/*
	 * Pending, and no seat taken. An invitation is an offer; the place is claimed when it is accepted,
	 * or an Event could be full of people who never replied.
	 */
	$seated = axismundi_cal_participation_seat( $post_id, $actor_uri, 'pending', $invite, 'invite' );
	return is_wp_error( $seated ) ? $seated : 'pending';
}

/**
 * Answer an invitation.
 *
 * The guest's own decision, and theirs to change: an accepted invitation may become a refusal later,
 * which is ordinary and is why this does not refuse an already-answered row the way a host's reply to
 * a request does.
 *
 * @param int    $post_id   Event post ID.
 * @param string $actor_uri The invited Actor, answering for themselves.
 * @param string $decision  accept|reject|tentative.
 * @return string|WP_Error Resulting state.
 */
function axismundi_cal_event_respond_to_invite( int $post_id, string $actor_uri, string $decision ) {
	$actor_uri = trim( $actor_uri );
	if ( ! isset( AXISMUNDI_CAL_INVITE_ANSWERS[ $decision ] ) ) {
		return new WP_Error( 'ax_event_invite_answer', __( 'An invitation is accepted, declined, or answered tentatively.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$participation = axismundi_cal_event_participation( $post_id, $actor_uri );
	if ( ! is_array( $participation ) || 'invite' !== (string) $participation['source'] ) {
		return new WP_Error( 'ax_event_invite_missing', __( 'You have not been invited to this event.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	$invite_uri = (string) $participation['initiating_activity_uri'];
	if ( '' === $invite_uri ) {
		return new WP_Error( 'ax_event_invite_unaddressable', __( 'That invitation predates the record of it and cannot be answered.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}

	/*
	 * The seat first, then the answer, in the order an acceptance already uses: publishing an `Accept`
	 * for a place that turned out not to exist leaves a promise with nothing to retract it. Eligibility
	 * is deliberately not consulted -- being invited is the permission, and an invitation that cannot
	 * be accepted would be a message with no meaning.
	 */
	$state  = AXISMUNDI_CAL_INVITE_ANSWERS[ $decision ];
	$seated = axismundi_cal_participation_seat( $post_id, $actor_uri, $state, null, 'invite' );
	if ( is_wp_error( $seated ) ) {
		return $seated;
	}
	$types    = array( 'accept' => 'Accept', 'reject' => 'Reject', 'tentative' => 'TentativeAccept' );
	$response = axismundi_cal_participation_activity(
		array( 'type' => $types[ $decision ], 'actor' => $actor_uri, 'object' => $invite_uri ),
		// Keyed by the answer as well as the invitation, so changing your mind records a new act rather
		// than colliding with the one you are replacing.
		'ax-cal-invite-' . $decision . ':' . $invite_uri . ':' . $state
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	axismundi_cal_participation_note_response( $post_id, $actor_uri, $response );
	return $state;
}

/**
 * Take an invitation back.
 *
 * Only one nobody has answered. Withdrawing an invitation somebody accepted is removing a guest, and
 * that reads as a different act to everyone involved -- it should say so rather than being spelled
 * `Undo`.
 *
 * @param int    $post_id   Event post ID.
 * @param string $actor_uri Invited Actor.
 * @return true|WP_Error
 */
function axismundi_cal_event_withdraw_invite( int $post_id, string $actor_uri ) {
	global $wpdb;
	$actor_uri = trim( $actor_uri );
	if ( ! axismundi_cal_can_manage_participation( $post_id ) ) {
		return new WP_Error( 'ax_event_invite_denied', __( 'You cannot manage invitations for this event.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}
	$participation = axismundi_cal_event_participation( $post_id, $actor_uri );
	if ( ! is_array( $participation ) || 'invite' !== (string) $participation['source'] ) {
		return new WP_Error( 'ax_event_invite_missing', __( 'There is no invitation to withdraw.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	if ( 'pending' !== (string) $participation['state'] ) {
		return new WP_Error( 'ax_event_invite_answered', __( 'That invitation has been answered; remove the guest instead.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	$host = axismundi_cal_event_owner_actor_uri( $post_id );
	if ( '' !== $host ) {
		axismundi_cal_participation_activity(
			array( 'type' => 'Undo', 'actor' => $host, 'object' => (string) $participation['initiating_activity_uri'] ),
			'ax-cal-invite-undo:' . (string) $participation['initiating_activity_uri']
		);
	}
	/*
	 * The row goes rather than becoming `withdrawn`. Nobody replied, so there is no answer to preserve
	 * -- and a lingering row would keep the Event on their calendar for an invitation that no longer
	 * exists.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_cal_participation_table(), array( 'id' => (int) $participation['id'] ), array( '%d' ) );
	return true;
}
