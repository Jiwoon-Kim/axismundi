<?php
/**
 * What happened, and the row that projects it (dev-only; dist-excluded).
 *
 * A `Join` is an Activity before it is a row. The row exists so a screen does not have to replay the
 * ledger to draw a list, and every state below therefore has an Activity behind it -- a state that
 * moved without one would be a fact with no author, no time, and nothing for a later `Undo` to
 * address.
 *
 * The pair this file exists for is the seat and the answer. Capacity is read inside the same locked
 * section that writes the row, because deciding there is room and then writing are two steps with a
 * gap between them, and two people arriving at an Event with one place left would both read the
 * count before either wrote. The `Accept` is recorded after that section rather than before it: an
 * acceptance published for a place that turned out not to exist is the one order of events with
 * nothing to retract it.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_pl_results   = array();
$ax_pl_posts     = array();
$ax_pl_calendars = array();
$ax_pl_users     = array();

/** @param bool[] $results Results. */
function ax_pl_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A user with an activated public Person Actor. */
function ax_pl_user( array &$users ) : array {
	$handle  = 'axpl' . strtolower( wp_generate_password( 8, false, false ) );
	$id      = (int) wp_insert_user( array( 'user_login' => $handle, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$users[] = $id;
	$uri     = '';
	if ( function_exists( 'axismundi_actors_ensure_for_user' ) ) {
		$actor = axismundi_actors_ensure_for_user( $id );
		if ( $actor instanceof Axismundi_Actor ) {
			axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
			axismundi_actors_register_handle( $actor->get_identity_id(), $handle );
			$uri = (string) $actor->get_uri();
		}
	}
	return array( 'user_id' => $id, 'actor_uri' => $uri );
}

/** The type of the Activity a participation row is currently reading, or ''. */
function ax_pl_response_type( int $post_id, string $actor_uri ) : string {
	$row = axismundi_cal_event_participation( $post_id, $actor_uri );
	if ( ! is_array( $row ) || empty( $row['current_response_activity_uri'] ) ) {
		return '';
	}
	$activity = axismundi_act_get( (string) $row['current_response_activity_uri'] );
	return $activity instanceof Axismundi_Activity ? (string) $activity->get_type() : '';
}

/** The Activity URI a participation row was started by. */
function ax_pl_asked_with( int $post_id, string $actor_uri ) : string {
	$row = axismundi_cal_event_participation( $post_id, $actor_uri );
	return is_array( $row ) ? (string) $row['initiating_activity_uri'] : '';
}

/** One participation state, or ''. */
function ax_pl_state( int $post_id, string $actor_uri ) : string {
	$row = axismundi_cal_event_participation( $post_id, $actor_uri );
	return is_array( $row ) ? (string) $row['state'] : '';
}

try {
	$ax_pl_host  = ax_pl_user( $ax_pl_users );
	$ax_pl_alice = ax_pl_user( $ax_pl_users );
	$ax_pl_bob   = ax_pl_user( $ax_pl_users );
	wp_set_current_user( $ax_pl_host['user_id'] );

	$ax_pl_suffix   = strtolower( wp_generate_password( 6, false, false ) );
	$ax_pl_calendar = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Ledger fixture', 'slug' => 'ax-pl-' . $ax_pl_suffix, 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_pl_host['actor_uri'] )
	);
	$ax_pl_calendars[] = $ax_pl_calendar;
	axismundi_cal_acl_grant( $ax_pl_calendar, '', 'reader', 'public' );

	$ax_pl_make = static function ( array &$posts, int $calendar, string $title, array $fields = array() ) : int {
		$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_title' => $title, 'post_author' => get_current_user_id() ) );
		$posts[] = $post_id;
		axismundi_cal_event_save(
			$post_id,
			array_merge( array( 'calendar_id' => $calendar, 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-12-05 19:00:00', 'ends_at' => '2026-12-05 21:00:00' ), $fields )
		);
		$GLOBALS['axismundi_cal_rest_write'] = true;
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		$GLOBALS['axismundi_cal_rest_write'] = false;
		return $post_id;
	};

	// -- free: asked and answered in one breath -------------------------------------------------------

	$ax_pl_open = $ax_pl_make( $ax_pl_posts, $ax_pl_calendar, 'Open house', array( 'join_mode' => 'free' ) );
	axismundi_cal_event_join( $ax_pl_open, $ax_pl_alice['actor_uri'] );
	$ax_pl_join = axismundi_act_get( ax_pl_asked_with( $ax_pl_open, $ax_pl_alice['actor_uri'] ) );
	ax_pl_assert(
		$ax_pl_results,
		'joining records a Join by the person coming, addressed at the event',
		$ax_pl_join instanceof Axismundi_Activity
			&& 'Join' === (string) $ax_pl_join->get_type()
			&& $ax_pl_alice['actor_uri'] === (string) $ax_pl_join->get_actor_uri()
			&& axismundi_cal_event_uri( $ax_pl_open ) === (string) $ax_pl_join->get_object_uri()
	);
	/*
	 * FEP-8a8e defines `attendees` as the Joins that were answered with an `Accept`, so an open Event
	 * records one rather than treating the Join as sufficient by itself. What separates it from a
	 * moderated Event is that a server made the answer immediately, not that the answer is missing --
	 * which is why a screen reads this as "accepted automatically" rather than naming somebody.
	 */
	$ax_pl_row    = axismundi_cal_event_participation( $ax_pl_open, $ax_pl_alice['actor_uri'] );
	$ax_pl_accept = axismundi_act_get( (string) $ax_pl_row['current_response_activity_uri'] );
	ax_pl_assert(
		$ax_pl_results,
		'an open event answers at once, with an Accept by the host addressed at that Join',
		'accepted' === (string) $ax_pl_row['state']
			&& $ax_pl_accept instanceof Axismundi_Activity
			&& 'Accept' === (string) $ax_pl_accept->get_type()
			&& $ax_pl_host['actor_uri'] === (string) $ax_pl_accept->get_actor_uri()
			&& (string) $ax_pl_join->get_uri() === (string) $ax_pl_accept->get_object_uri()
	);
	/*
	 * One intention, one request. Local Activity URIs are freshly minted per call, so without a stable
	 * source key a double-submitted form would be two Joins in the ledger for one person arriving.
	 */
	axismundi_cal_event_join( $ax_pl_open, $ax_pl_alice['actor_uri'] );
	ax_pl_assert(
		$ax_pl_results,
		'asking twice in a row does not mint a second Join',
		(string) $ax_pl_join->get_uri() === ax_pl_asked_with( $ax_pl_open, $ax_pl_alice['actor_uri'] )
	);

	// -- restricted: the answer waits for somebody ----------------------------------------------------

	$ax_pl_mod = $ax_pl_make( $ax_pl_posts, $ax_pl_calendar, 'Moderated', array( 'join_mode' => 'restricted' ) );
	axismundi_cal_event_join( $ax_pl_mod, $ax_pl_alice['actor_uri'] );
	$ax_pl_pending = axismundi_cal_event_participation( $ax_pl_mod, $ax_pl_alice['actor_uri'] );
	ax_pl_assert(
		$ax_pl_results,
		'a moderated event records the Join and nothing answering it yet',
		'pending' === (string) $ax_pl_pending['state']
			&& '' !== (string) $ax_pl_pending['initiating_activity_uri']
			&& null === $ax_pl_pending['current_response_activity_uri']
	);
	ax_pl_assert(
		$ax_pl_results,
		'accepting it records an Accept by the host and seats them',
		'accepted' === axismundi_cal_event_respond_to_join( $ax_pl_mod, $ax_pl_alice['actor_uri'], 'accept' )
			&& 'Accept' === ax_pl_response_type( $ax_pl_mod, $ax_pl_alice['actor_uri'] )
	);
	ax_pl_assert(
		$ax_pl_results,
		'and a request already answered is not answered a second time',
		is_wp_error( axismundi_cal_event_respond_to_join( $ax_pl_mod, $ax_pl_alice['actor_uri'], 'reject' ) )
	);

	axismundi_cal_event_join( $ax_pl_mod, $ax_pl_bob['actor_uri'] );
	ax_pl_assert(
		$ax_pl_results,
		'rejecting records a Reject, which is the same shape of answer',
		'rejected' === axismundi_cal_event_respond_to_join( $ax_pl_mod, $ax_pl_bob['actor_uri'], 'reject' )
			&& 'Reject' === ax_pl_response_type( $ax_pl_mod, $ax_pl_bob['actor_uri'] )
	);
	ax_pl_assert(
		$ax_pl_results,
		'somebody turned down cannot simply ask again',
		is_wp_error( axismundi_cal_event_join( $ax_pl_mod, $ax_pl_bob['actor_uri'] ) )
	);

	// -- taking a request back ------------------------------------------------------------------------

	$ax_pl_undo_ev = $ax_pl_make( $ax_pl_posts, $ax_pl_calendar, 'Withdrawable', array( 'join_mode' => 'restricted' ) );
	axismundi_cal_event_join( $ax_pl_undo_ev, $ax_pl_alice['actor_uri'] );
	$ax_pl_asked = ax_pl_asked_with( $ax_pl_undo_ev, $ax_pl_alice['actor_uri'] );
	ax_pl_assert(
		$ax_pl_results,
		'a pending request is taken back with an Undo addressed at the Join it retracts',
		true === axismundi_cal_event_withdraw_join( $ax_pl_undo_ev, $ax_pl_alice['actor_uri'] )
			&& 'withdrawn' === ax_pl_state( $ax_pl_undo_ev, $ax_pl_alice['actor_uri'] )
			&& 'Undo' === ax_pl_response_type( $ax_pl_undo_ev, $ax_pl_alice['actor_uri'] )
	);
	/*
	 * Coming back is a new request. The first Join has an `Undo` addressed to it, so reviving it would
	 * contradict the ledger -- and the organizer would be approving something already retracted.
	 */
	axismundi_cal_event_join( $ax_pl_undo_ev, $ax_pl_alice['actor_uri'] );
	ax_pl_assert(
		$ax_pl_results,
		'changing their mind afterwards is a fresh Join rather than the undone one revived',
		$ax_pl_asked !== ax_pl_asked_with( $ax_pl_undo_ev, $ax_pl_alice['actor_uri'] )
			&& 'pending' === ax_pl_state( $ax_pl_undo_ev, $ax_pl_alice['actor_uri'] )
	);
	/*
	 * Cancelling an acceptance is a different act. ActivityStreams has `Leave` for stopping attending
	 * something you were admitted to, and whether an accepted `Join` is undone or left is not settled
	 * -- so one button for both would decide it by accident, and the wrong choice federates.
	 */
	ax_pl_assert(
		$ax_pl_results,
		'an accepted reply is not withdrawn, that question being one nobody has answered yet',
		is_wp_error( axismundi_cal_event_withdraw_join( $ax_pl_open, $ax_pl_alice['actor_uri'] ) )
	);

	// -- a place is taken by an acceptance, not by asking ---------------------------------------------

	$ax_pl_one = $ax_pl_make( $ax_pl_posts, $ax_pl_calendar, 'One seat, moderated', array( 'join_mode' => 'restricted', 'maximum_attendee_capacity' => 1 ) );
	axismundi_cal_event_join( $ax_pl_one, $ax_pl_alice['actor_uri'] );
	axismundi_cal_event_join( $ax_pl_one, $ax_pl_bob['actor_uri'] );
	ax_pl_assert(
		$ax_pl_results,
		'requests waiting for an answer hold no places, so a queue cannot close the door behind it',
		1 === axismundi_cal_event_remaining_capacity( $ax_pl_one )
	);
	ax_pl_assert(
		$ax_pl_results,
		'the first acceptance takes the place',
		'accepted' === axismundi_cal_event_respond_to_join( $ax_pl_one, $ax_pl_alice['actor_uri'], 'accept' )
			&& 0 === axismundi_cal_event_remaining_capacity( $ax_pl_one )
	);
	ax_pl_assert(
		$ax_pl_results,
		'and the second is refused rather than seated past the limit',
		is_wp_error( axismundi_cal_event_respond_to_join( $ax_pl_one, $ax_pl_bob['actor_uri'], 'accept' ) )
			&& 'pending' === ax_pl_state( $ax_pl_one, $ax_pl_bob['actor_uri'] )
	);
	/*
	 * The order that refusal has to happen in. An `Accept` recorded before the place was secured would
	 * be a published acceptance for a seat that does not exist, with nothing to retract it.
	 */
	ax_pl_assert(
		$ax_pl_results,
		'leaving no acceptance in the ledger for a place that was never given',
		'' === ax_pl_response_type( $ax_pl_one, $ax_pl_bob['actor_uri'] )
	);
	ax_pl_assert(
		$ax_pl_results,
		'though the request itself survives, somebody having genuinely asked',
		'' !== ax_pl_asked_with( $ax_pl_one, $ax_pl_bob['actor_uri'] )
	);
	/*
	 * The same limit on the immediate path. An open Event seats people as they arrive, so it is the
	 * one where the count and the write have no human step between them to slow them down.
	 */
	$ax_pl_open_one = $ax_pl_make( $ax_pl_posts, $ax_pl_calendar, 'One seat, open', array( 'join_mode' => 'free', 'maximum_attendee_capacity' => 1 ) );
	ax_pl_assert(
		$ax_pl_results,
		'an open event with one place seats the first and refuses the second',
		'accepted' === axismundi_cal_event_join( $ax_pl_open_one, $ax_pl_alice['actor_uri'] )
			&& is_wp_error( axismundi_cal_event_join( $ax_pl_open_one, $ax_pl_bob['actor_uri'] ) )
			&& 1 === count( axismundi_cal_event_attendees( $ax_pl_open_one ) )
	);
	ax_pl_assert(
		$ax_pl_results,
		'and records no acceptance for the one it turned away',
		'' === ax_pl_response_type( $ax_pl_open_one, $ax_pl_bob['actor_uri'] )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_pl_posts as $ax_pl_post ) {
		wp_delete_post( (int) $ax_pl_post, true );
	}
	foreach ( $ax_pl_calendars as $ax_pl_cal ) {
		axismundi_cal_calendar_delete( (int) $ax_pl_cal );
	}
	foreach ( $ax_pl_users as $ax_pl_user_id ) {
		wp_delete_user( (int) $ax_pl_user_id );
	}
}

$ax_pl_failures = count( array_filter( $ax_pl_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_pl_results ), $ax_pl_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_pl_failures > 0 ? 1 : 0 );
}
exit( $ax_pl_failures > 0 ? 1 : 0 );
