<?php
/**
 * Taking your own answer back (dev-only; dist-excluded).
 *
 * The third act, and the one whose vocabulary had been left open. Read as a handshake it settles
 * itself: the guest undoes what the guest wrote. On the Join path that is their `Join` -- never the
 * host's `Accept(Join)`, which is not theirs to retract and would make the ledger say the host
 * changed their mind. On the Invite path it is their own `Accept`, `Reject` or `TentativeAccept`,
 * which is Follow's shape exactly. `Leave` turns out to say nothing `Undo(Join)` does not.
 *
 * The rest is about changing an answer, which is two events in the record and one command here -- and
 * about the direction where the order matters: a refusal must not be quietly turned into "no answer"
 * by an acceptance the Event has no room for.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_un_results = array();
$ax_un_users   = array();
$ax_un_posts   = array();

/** @param bool[] $results Results. */
function ax_un_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated, published Person Actor. */
function ax_un_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axun' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axun' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

/** One published Event. */
function ax_un_event( array &$posts, int $author, int $calendar_id, array $extra = array() ) : int {
	$post_id = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_title' => 'Workshop', 'post_status' => 'draft', 'post_author' => $author )
	);
	$posts[] = $post_id;
	$saved   = axismundi_cal_event_save(
		$post_id,
		array_merge(
			array(
				'calendar_id' => $calendar_id,
				'starts_at'   => gmdate( 'Y-m-d H:i:s', strtotime( '+25 days' ) ),
				'ends_at'     => gmdate( 'Y-m-d H:i:s', strtotime( '+25 days +3 hours' ) ),
				'timezone'    => 'Asia/Seoul',
				'join_mode'   => 'free',
			),
			$extra
		)
	);
	if ( is_wp_error( $saved ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "  (fixture refused: %s)\n", $saved->get_error_message() );
		return 0;
	}
	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;
	return $post_id;
}

/** The Activities one Actor wrote about one Event's participation, newest first. */
function ax_un_acts( string $actor_uri, string $type ) : array {
	return array_values(
		array_filter(
			axismundi_act_get_by_actor( $actor_uri, 50 ),
			static fn( Axismundi_Activity $activity ) : bool => $type === $activity->get_type()
		)
	);
}

try {
	$ax_un_host_user  = ax_un_user( $ax_un_users );
	$ax_un_guest_user = ax_un_user( $ax_un_users );
	wp_set_current_user( $ax_un_host_user );
	$ax_un_host      = axismundi_actors_get_for_user( $ax_un_host_user );
	$ax_un_guest     = axismundi_actors_get_for_user( $ax_un_guest_user );
	$ax_un_cal       = (int) axismundi_cal_primary_calendar( (string) $ax_un_host->get_uri() )['id'];
	$ax_un_guest_cal = (int) axismundi_cal_primary_calendar( (string) $ax_un_guest->get_uri() )['id'];
	axismundi_cal_acl_grant( $ax_un_cal, '', 'reader', 'public' );

	// -- leaving something you asked to come to ------------------------------------------------------------

	$ax_un_open = ax_un_event( $ax_un_posts, $ax_un_host_user, $ax_un_cal );
	axismundi_cal_event_join( $ax_un_open, (string) $ax_un_guest->get_uri() );
	$ax_un_join_uri = (string) axismundi_cal_event_participation( $ax_un_open, (string) $ax_un_guest->get_uri() )['initiating_activity_uri'];
	ax_un_assert(
		$ax_un_results,
		'somebody admitted to an event can leave it, which is taking their own request back',
		true === axismundi_cal_event_withdraw_join( $ax_un_open, (string) $ax_un_guest->get_uri() )
			&& 'withdrawn' === (string) axismundi_cal_event_participation( $ax_un_open, (string) $ax_un_guest->get_uri() )['state']
			&& 0 === count( axismundi_cal_event_attendees( $ax_un_open ) )
	);
	/*
	 * The whole reason `Leave` was not needed, and the thing that would have been wrong if it had been
	 * spelled `Undo(Accept)`: the guest undoes the `Join` they wrote, not the `Accept` the host wrote.
	 */
	$ax_un_undo = ax_un_acts( (string) $ax_un_guest->get_uri(), 'Undo' );
	ax_un_assert(
		$ax_un_results,
		'and the ledger says they undid their own request, not the host\'s acceptance of it',
		1 === count( $ax_un_undo ) && $ax_un_join_uri === (string) $ax_un_undo[0]->get_object_uri()
	);
	ax_un_assert(
		$ax_un_results,
		'the event leaves their calendar, since they are no longer part of it',
		! in_array( $ax_un_open, axismundi_cal_placed_event_ids( $ax_un_guest_cal ), true )
	);
	// The place is free, and the door is not bolted: an open Event takes them back if they return.
	ax_un_assert(
		$ax_un_results,
		'and they may ask again, because leaving is not being turned away',
		'accepted' === axismundi_cal_event_join( $ax_un_open, (string) $ax_un_guest->get_uri() )
	);

	// -- taking back an answer to an invitation --------------------------------------------------------------

	$ax_un_invited = ax_un_event( $ax_un_posts, $ax_un_host_user, $ax_un_cal, array( 'join_mode' => 'restricted' ) );
	axismundi_cal_event_invite( $ax_un_invited, (string) $ax_un_guest->get_uri() );
	axismundi_cal_event_respond_to_invite( $ax_un_invited, (string) $ax_un_guest->get_uri(), 'accept' );
	$ax_un_accept_uri = (string) axismundi_cal_event_participation( $ax_un_invited, (string) $ax_un_guest->get_uri() )['current_response_activity_uri'];
	ax_un_assert(
		$ax_un_results,
		'a guest can take back their acceptance, leaving the invitation unanswered again',
		'pending' === axismundi_cal_event_undo_invite_response( $ax_un_invited, (string) $ax_un_guest->get_uri() )
			&& 0 === count( axismundi_cal_event_attendees( $ax_un_invited ) )
	);
	ax_un_assert(
		$ax_un_results,
		'undoing what they wrote themselves, which is the same shape as undoing a Follow',
		$ax_un_accept_uri === (string) ax_un_acts( (string) $ax_un_guest->get_uri(), 'Undo' )[0]->get_object_uri()
	);
	/*
	 * The invitation is the host's and still stands, so it stays where an unanswered invitation goes.
	 * Only the answer has gone.
	 */
	ax_un_assert(
		$ax_un_results,
		'the invitation stays on their calendar, because being asked is still true',
		in_array( $ax_un_invited, axismundi_cal_placed_event_ids( $ax_un_guest_cal ), true )
			&& 'invited' === axismundi_cal_event_placement_reason( $ax_un_guest_cal, $ax_un_invited )
	);
	// A refusal is theirs too, and taking one back returns them to the same unanswered state.
	axismundi_cal_event_respond_to_invite( $ax_un_invited, (string) $ax_un_guest->get_uri(), 'reject' );
	ax_un_assert(
		$ax_un_results,
		'and a refusal can be taken back the same way, since it was equally their own answer',
		'pending' === axismundi_cal_event_undo_invite_response( $ax_un_invited, (string) $ax_un_guest->get_uri() )
	);
	ax_un_assert(
		$ax_un_results,
		'while an invitation nobody has answered has nothing of theirs to take back',
		'ax_event_invite_unanswered' === axismundi_cal_event_undo_invite_response( $ax_un_invited, (string) $ax_un_guest->get_uri() )->get_error_code()
	);

	// -- changing an answer is one command and two events ------------------------------------------------------

	axismundi_cal_event_respond_to_invite( $ax_un_invited, (string) $ax_un_guest->get_uri(), 'accept' );
	$ax_un_before = count( ax_un_acts( (string) $ax_un_guest->get_uri(), 'Undo' ) );
	ax_un_assert(
		$ax_un_results,
		'changing an answer records the retraction as well as the new answer',
		'rejected' === axismundi_cal_event_respond_to_invite( $ax_un_invited, (string) $ax_un_guest->get_uri(), 'reject' )
			&& count( ax_un_acts( (string) $ax_un_guest->get_uri(), 'Undo' ) ) === $ax_un_before + 1
	);
	// Saying the same thing twice is not a second answer; it is a reloaded page.
	$ax_un_steady = count( ax_un_acts( (string) $ax_un_guest->get_uri(), 'Reject' ) );
	ax_un_assert(
		$ax_un_results,
		'and repeating an answer is not a second one',
		'rejected' === axismundi_cal_event_respond_to_invite( $ax_un_invited, (string) $ax_un_guest->get_uri(), 'reject' )
			&& count( ax_un_acts( (string) $ax_un_guest->get_uri(), 'Reject' ) ) === $ax_un_steady
	);

	// -- the direction where order matters ------------------------------------------------------------------------

	/*
	 * Turning a refusal into an acceptance can fail, and it must fail leaving the refusal standing. If
	 * the retraction went first, somebody who tried to change their mind about a full Event would be
	 * left with no answer at all -- a state they never chose and the organizer never saw them choose.
	 */
	$ax_un_full  = ax_un_event( $ax_un_posts, $ax_un_host_user, $ax_un_cal, array( 'join_mode' => 'restricted', 'maximum_attendee_capacity' => 1 ) );
	$ax_un_taker = axismundi_actors_get_for_user( ax_un_user( $ax_un_users ) );
	axismundi_cal_event_invite( $ax_un_full, (string) $ax_un_guest->get_uri() );
	axismundi_cal_event_invite( $ax_un_full, (string) $ax_un_taker->get_uri() );
	axismundi_cal_event_respond_to_invite( $ax_un_full, (string) $ax_un_guest->get_uri(), 'reject' );
	axismundi_cal_event_respond_to_invite( $ax_un_full, (string) $ax_un_taker->get_uri(), 'accept' );
	$ax_un_undos_before = count( ax_un_acts( (string) $ax_un_guest->get_uri(), 'Undo' ) );
	$ax_un_late         = axismundi_cal_event_respond_to_invite( $ax_un_full, (string) $ax_un_guest->get_uri(), 'accept' );
	ax_un_assert(
		$ax_un_results,
		'changing a refusal to an acceptance on a full event is refused',
		is_wp_error( $ax_un_late ) && 'ax_event_join_full' === $ax_un_late->get_error_code()
	);
	ax_un_assert(
		$ax_un_results,
		'and their refusal is left standing rather than becoming no answer at all',
		'rejected' === (string) axismundi_cal_event_participation( $ax_un_full, (string) $ax_un_guest->get_uri() )['state']
			&& count( ax_un_acts( (string) $ax_un_guest->get_uri(), 'Undo' ) ) === $ax_un_undos_before
	);
	// The other direction never runs out of anything, so it is always available.
	ax_un_assert(
		$ax_un_results,
		'while giving up a place on a full event always works, and frees it',
		'rejected' === axismundi_cal_event_respond_to_invite( $ax_un_full, (string) $ax_un_taker->get_uri(), 'reject' )
			&& 1 === (int) axismundi_cal_event_remaining_capacity( $ax_un_full )
	);
	ax_un_assert(
		$ax_un_results,
		'and the place it freed can then be taken by the person who had said no',
		'accepted' === axismundi_cal_event_respond_to_invite( $ax_un_full, (string) $ax_un_guest->get_uri(), 'accept' )
	);

	// -- what is not yours to undo --------------------------------------------------------------------------------

	// A request the host turned down is spent: what stands between them and the Event is the refusal.
	$ax_un_closed = ax_un_event( $ax_un_posts, $ax_un_host_user, $ax_un_cal, array( 'join_mode' => 'restricted' ) );
	$ax_un_turned = axismundi_actors_get_for_user( ax_un_user( $ax_un_users ) );
	axismundi_cal_event_join( $ax_un_closed, (string) $ax_un_turned->get_uri() );
	axismundi_cal_event_respond_to_join( $ax_un_closed, (string) $ax_un_turned->get_uri(), 'reject' );
	$ax_un_spent = axismundi_cal_event_withdraw_join( $ax_un_closed, (string) $ax_un_turned->get_uri() );
	ax_un_assert(
		$ax_un_results,
		'a request that was turned down cannot be taken back, having already been answered',
		is_wp_error( $ax_un_spent ) && 'ax_event_withdraw_answered' === $ax_un_spent->get_error_code()
	);
	// And the two paths stay apart: an invitation is not a request you withdraw.
	$ax_un_wrong = axismundi_cal_event_withdraw_join( $ax_un_invited, (string) $ax_un_guest->get_uri() );
	ax_un_assert(
		$ax_un_results,
		'and an invitation is answered rather than withdrawn, which is the other end of the same rule',
		is_wp_error( $ax_un_wrong ) && 'ax_event_withdraw_source' === $ax_un_wrong->get_error_code()
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_un_posts ) as $ax_un_post_id ) {
		if ( $ax_un_post_id > 0 ) {
			wp_delete_post( (int) $ax_un_post_id, true );
		}
	}
	foreach ( array_unique( $ax_un_users ) as $ax_un_user_id ) {
		wp_delete_user( (int) $ax_un_user_id );
	}
}

$ax_un_failures = count( array_filter( $ax_un_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_un_results ), $ax_un_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_un_failures > 0 ? 1 : 0 );
}
exit( $ax_un_failures > 0 ? 1 : 0 );
