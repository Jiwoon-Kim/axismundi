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
	// Nobody is asked to something that is off.
	$cancelled = axismundi_cal_event_cancellation_block( $post_id );
	if ( null !== $cancelled ) {
		return $cancelled;
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
	/*
	 * Somebody the organizer removed may be asked back. The removal already ended what they had said,
	 * so there is no reply here to erase -- and without this an organizer who removed the wrong person
	 * would have no way to correct it, while the person themselves is barred from asking.
	 */
	$readmitting = is_array( $existing ) && 'removed' === (string) $existing['state'];
	if ( is_array( $existing ) && ! $readmitting && 'pending' !== (string) $existing['state'] ) {
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
		// A second invitation after a removal is a second act, keyed on the removal it follows so it
		// does not collide with the invitation that came before it.
		$readmitting
			? 'ax-cal-reinvite:' . (string) $existing['current_response_activity_uri']
			: 'ax-cal-invite:' . $event_uri . ':' . $actor_uri
	);
	if ( is_wp_error( $invite ) ) {
		return $invite;
	}
	if ( $readmitting && 'invite' !== (string) $existing['source'] ) {
		global $wpdb;
		/*
		 * The one place a relation changes direction, and only because the previous one is over. The
		 * rule that `source` is written once protects the answer: it is what stops a host replying on a
		 * guest's behalf. Nobody is waiting on an answer in a removed row, and what follows genuinely
		 * did start from the other end -- the guest asked once, was removed, and is now being asked.
		 * Leaving it as `join` would leave them an invitation they have no way to answer.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( axismundi_cal_participation_table(), array( 'source' => 'invite' ), array( 'id' => (int) $existing['id'] ), array( '%s' ), array( '%d' ) );
	}
	/*
	 * Pending, and no seat taken. An invitation is an offer; the place is claimed when it is accepted,
	 * or an Event could be full of people who never replied.
	 */
	$seated = axismundi_cal_participation_seat( $post_id, $actor_uri, 'pending', $invite, 'invite' );
	if ( is_wp_error( $seated ) ) {
		return $seated;
	}
	// The row is written; what the `Invite` meant can be resolved now and not before.
	axismundi_cal_notify_flush();
	return 'pending';
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
	/*
	 * Answering an invitation to something that has been called off changes nothing about whether it
	 * happens, and would record an arrangement for an evening that is not going ahead. The reply they
	 * already gave stays as it was: it is what they said while it was still on.
	 */
	$cancelled = axismundi_cal_event_cancellation_block( $post_id );
	if ( null !== $cancelled ) {
		return $cancelled;
	}
	$participation = axismundi_cal_event_participation( $post_id, $actor_uri );
	if ( ! is_array( $participation ) || 'invite' !== (string) $participation['source'] ) {
		return new WP_Error( 'ax_event_invite_missing', __( 'You have not been invited to this event.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	$invite_uri = (string) $participation['initiating_activity_uri'];
	if ( '' === $invite_uri ) {
		return new WP_Error( 'ax_event_invite_unaddressable', __( 'That invitation predates the record of it and cannot be answered.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}

	$state = AXISMUNDI_CAL_INVITE_ANSWERS[ $decision ];
	if ( $state === (string) $participation['state'] ) {
		// Saying the same thing again is not a second answer, and a ledger with one act per click would
		// be a record of a page being reloaded.
		return $state;
	}

	/*
	 * Changing an answer is two events in the ledger and one command here. RSVP answers are mutually
	 * exclusive the way a Like and a Dislike are: what happened is that the previous answer was undone
	 * and another was given, and both belong in the record -- but a screen that made somebody undo
	 * first and answer second would leave them stranded in `pending` whenever the second half failed.
	 *
	 * Order matters, and it is not the same in both directions. Going towards acceptance the capacity
	 * is read before anything is retracted: if the Event is full the previous answer has to stand,
	 * because a refusal quietly turned into "no answer" is a state nobody asked for. Going the other
	 * way there is nothing to run out of.
	 */
	$previous = (string) $participation['current_response_activity_uri'];
	if ( 'pending' !== (string) $participation['state'] ) {
		if ( 'accepted' === $state ) {
			$remaining = axismundi_cal_event_remaining_capacity( $post_id );
			if ( null !== $remaining && $remaining < 1 ) {
				return new WP_Error( 'ax_event_join_full', __( 'This event is full.', 'axismundi-calendar' ), array( 'status' => 409 ) );
			}
		}
		if ( '' !== $previous ) {
			$undone = axismundi_cal_participation_activity(
				array( 'type' => 'Undo', 'actor' => $actor_uri, 'object' => $previous ),
				'ax-cal-undo-response:' . $previous
			);
			if ( is_wp_error( $undone ) ) {
				return $undone;
			}
			/*
			 * Resolved here rather than at the end, which is the reason a command recording two
			 * Activities flushes between them. The retraction is a fact about the answer being taken
			 * back; leaving it in the queue until the new answer had been written would resolve it
			 * against the answer that replaced it, and the audience snapshots of two acts would be
			 * the state of one.
			 */
			axismundi_cal_notify_flush();
		}
	}

	/*
	 * The seat, then the answer, in the order an acceptance already uses: publishing an `Accept` for a
	 * place that turned out not to exist leaves a promise with nothing to retract it. Eligibility is
	 * deliberately not consulted -- being invited is the permission, and an invitation that cannot be
	 * accepted would be a message with no meaning.
	 *
	 * The capacity read above is a courtesy and this is the decision: two people taking the last place
	 * at once are separated here, under the lock, and the loser is left in `pending` -- which their own
	 * `Undo` has already made true.
	 */
	$seated = axismundi_cal_participation_seat( $post_id, $actor_uri, $state, null, 'invite' );
	if ( is_wp_error( $seated ) ) {
		axismundi_cal_event_participation_set( $post_id, $actor_uri, 'pending' );
		return $seated;
	}
	$types    = array( 'accept' => 'Accept', 'reject' => 'Reject', 'tentative' => 'TentativeAccept' );
	$response = axismundi_cal_participation_activity(
		array( 'type' => $types[ $decision ], 'actor' => $actor_uri, 'object' => $invite_uri ),
		/*
		 * Keyed by how many answers this invitation has already had. The answer alone was enough while a
		 * reply was given once and never taken back; now somebody can accept, undo, and accept again --
		 * and a key naming only the answer would hand back the first `Accept`, an Activity their own
		 * `Undo` has already retracted. Counted from the ledger rather than kept in a column, because
		 * the ledger is where the answers are and a second tally would be a second thing to keep true.
		 */
		'ax-cal-invite-' . $decision . ':' . $invite_uri . ':' . $state . ':' . axismundi_cal_invite_answer_count( $invite_uri, $actor_uri )
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	axismundi_cal_participation_note_response( $post_id, $actor_uri, $response );
	// The answer is recorded and the row says it, so the second half of a changed answer resolves
	// here -- the retraction having already been resolved against the answer it retracted.
	axismundi_cal_notify_flush();
	return $state;
}

/**
 * How many times one Actor has answered one invitation.
 *
 * Read from the ledger, which is the record of the answers themselves. It is what makes a second
 * `Accept` after an `Undo` a second act rather than the first one handed back.
 *
 * @param string $invite_uri Invitation Activity URI.
 * @param string $actor_uri  The invited Actor.
 * @return int
 */
function axismundi_cal_invite_answer_count( string $invite_uri, string $actor_uri ) : int {
	if ( ! function_exists( 'axismundi_act_get_by_object' ) ) {
		return 0;
	}
	$answers = array( 'Accept', 'Reject', 'TentativeAccept' );
	$count   = 0;
	foreach ( axismundi_act_get_by_object( $invite_uri ) as $activity ) {
		if ( $actor_uri === $activity->get_actor_uri() && in_array( $activity->get_type(), $answers, true ) ) {
			++$count;
		}
	}
	return $count;
}

/**
 * Take back your own answer to an invitation.
 *
 * `Undo` of the response the guest wrote, which is exactly Follow's shape: you undo your own
 * `Accept`, `Reject` or `TentativeAccept`, and what is left is the invitation, unanswered again. Not
 * `Undo(Invite)` -- the invitation is the host's and still stands -- and not `Leave`, which would be
 * a second word for the same thing.
 *
 * The invitation stays on the guest's calendar. Being asked is still true, and it is a thing they
 * have to deal with; it is only their answer that has gone.
 *
 * @param int    $post_id   Event post ID.
 * @param string $actor_uri The invited Actor, taking back their own answer.
 * @return string|WP_Error Resulting state.
 */
function axismundi_cal_event_undo_invite_response( int $post_id, string $actor_uri ) {
	$actor_uri     = trim( $actor_uri );
	$participation = axismundi_cal_event_participation( $post_id, $actor_uri );
	if ( ! is_array( $participation ) || 'invite' !== (string) $participation['source'] ) {
		return new WP_Error( 'ax_event_invite_missing', __( 'You have not been invited to this event.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	if ( 'pending' === (string) $participation['state'] ) {
		return new WP_Error( 'ax_event_invite_unanswered', __( 'You have not answered that invitation yet.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	if ( ! in_array( (string) $participation['state'], AXISMUNDI_CAL_INVITE_ANSWERS, true ) ) {
		// `removed` is the host's act and not an answer, so there is nothing here of the guest's to undo.
		return new WP_Error( 'ax_event_invite_unanswerable', __( 'There is no answer of yours to take back.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	$previous = (string) $participation['current_response_activity_uri'];
	if ( '' === $previous ) {
		return new WP_Error( 'ax_event_invite_unaddressable', __( 'That answer predates the record of it and cannot be taken back.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	$undone = axismundi_cal_participation_activity(
		array( 'type' => 'Undo', 'actor' => $actor_uri, 'object' => $previous ),
		'ax-cal-undo-response:' . $previous
	);
	if ( is_wp_error( $undone ) ) {
		return $undone;
	}
	/*
	 * Back to pending, with no response recorded. `pending` is the state of having been asked and not
	 * replied, so pointing it at the `Undo` would leave the row explaining itself with the act that
	 * emptied it -- the ledger holds that history, and this column holds the current answer.
	 */
	$set = axismundi_cal_event_participation_set( $post_id, $actor_uri, 'pending' );
	if ( is_wp_error( $set ) ) {
		return $set;
	}
	// An organizer who had counted them in needs to know they no longer have an answer at all, which
	// is a different thing from being told they now say no.
	axismundi_cal_notify_flush();
	return 'pending';
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
		$undone = axismundi_cal_participation_activity(
			array( 'type' => 'Undo', 'actor' => $host, 'object' => (string) $participation['initiating_activity_uri'] ),
			'ax-cal-invite-undo:' . (string) $participation['initiating_activity_uri']
		);
		/*
		 * Resolved before the row goes, because the resolver reads the invitation being withdrawn to
		 * know who was invited -- and afterwards there is nothing left to read.
		 */
		axismundi_cal_notify_flush();
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
