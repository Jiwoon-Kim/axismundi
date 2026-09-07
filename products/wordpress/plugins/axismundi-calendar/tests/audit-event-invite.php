<?php
/**
 * Being asked to come (dev-only; dist-excluded).
 *
 * Join and Invite are the same relation from opposite ends, and the property that has to survive is
 * who answers: a request is the host's to decide, an invitation is the guest's. The two paths meet on
 * one pending row, so the checks here are mostly about that row not being answered by the wrong
 * person -- an attendance list nobody agreed to is the failure this exists to prevent.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_iv_results = array();
$ax_iv_users   = array();
$ax_iv_posts   = array();

/** @param bool[] $results Results. */
function ax_iv_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated, published Person Actor. */
function ax_iv_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axiv' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axiv' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

/** One published Event taking invitations only. */
function ax_iv_event( array &$posts, int $author, int $calendar_id, string $title, array $extra = array() ) : int {
	$post_id = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_title' => $title, 'post_status' => 'draft', 'post_author' => $author )
	);
	$posts[] = $post_id;
	$saved   = axismundi_cal_event_save(
		$post_id,
		array_merge(
			array(
				'calendar_id' => $calendar_id,
				'starts_at'   => gmdate( 'Y-m-d H:i:s', strtotime( '+14 days' ) ),
				'ends_at'     => gmdate( 'Y-m-d H:i:s', strtotime( '+14 days +2 hours' ) ),
				'timezone'    => 'Asia/Seoul',
				'join_mode'   => 'restricted',
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

try {
	$ax_iv_host_user  = ax_iv_user( $ax_iv_users );
	$ax_iv_guest_user = ax_iv_user( $ax_iv_users );
	wp_set_current_user( $ax_iv_host_user );
	$ax_iv_host  = axismundi_actors_get_for_user( $ax_iv_host_user );
	$ax_iv_guest = axismundi_actors_get_for_user( $ax_iv_guest_user );
	$ax_iv_cal   = (int) axismundi_cal_primary_calendar( (string) $ax_iv_host->get_uri() )['id'];
	$ax_iv_guest_cal = (int) axismundi_cal_primary_calendar( (string) $ax_iv_guest->get_uri() )['id'];

	$ax_iv_party = ax_iv_event( $ax_iv_posts, $ax_iv_host_user, $ax_iv_cal, 'Dinner' );

	// -- asking somebody to come ---------------------------------------------------------------------

	ax_iv_assert(
		$ax_iv_results,
		'a host can invite somebody, and the invitation waits rather than assuming an answer',
		'pending' === axismundi_cal_event_invite( $ax_iv_party, (string) $ax_iv_guest->get_uri() )
			&& 'invite' === (string) axismundi_cal_event_participation( $ax_iv_party, (string) $ax_iv_guest->get_uri() )['source']
	);
	/*
	 * The placement contract from the calendar slice: something you have not replied to is exactly what
	 * you need to see on your own calendar.
	 */
	ax_iv_assert(
		$ax_iv_results,
		'an unanswered invitation is already on the invited Actor\'s own calendar',
		in_array( $ax_iv_party, axismundi_cal_placed_event_ids( $ax_iv_guest_cal ), true )
			&& 'invited' === axismundi_cal_event_placement_reason( $ax_iv_guest_cal, $ax_iv_party )
	);
	// An offer is not a seat. A capacity filled by people who never replied would be the alternative.
	ax_iv_assert(
		$ax_iv_results,
		'and inviting somebody does not seat them',
		0 === count( axismundi_cal_event_attendees( $ax_iv_party ) )
	);

	// -- who answers ----------------------------------------------------------------------------------

	/*
	 * The point of the slice. Both paths meet on one pending row, and the host's reply belongs to
	 * requests only -- otherwise a host could accept on somebody's behalf and record an attendance
	 * nobody agreed to.
	 */
	$ax_iv_stolen = axismundi_cal_event_respond_to_join( $ax_iv_party, (string) $ax_iv_guest->get_uri(), 'accept' );
	ax_iv_assert(
		$ax_iv_results,
		'the host cannot answer an invitation on the invited Actor\'s behalf',
		is_wp_error( $ax_iv_stolen ) && 'ax_event_respond_invite' === $ax_iv_stolen->get_error_code()
			&& 'pending' === (string) axismundi_cal_event_participation( $ax_iv_party, (string) $ax_iv_guest->get_uri() )['state']
	);
	wp_set_current_user( $ax_iv_guest_user );
	ax_iv_assert(
		$ax_iv_results,
		'the invited Actor answers for themselves, and the seat is taken then',
		'accepted' === axismundi_cal_event_respond_to_invite( $ax_iv_party, (string) $ax_iv_guest->get_uri(), 'accept' )
			&& 1 === count( axismundi_cal_event_attendees( $ax_iv_party ) )
	);
	/*
	 * And may change it. A guest's answer is theirs to revise, which is the opposite of a host's reply
	 * to a request -- that one is a decision and stays made.
	 */
	ax_iv_assert(
		$ax_iv_results,
		'a guest may change their answer later, unlike a host answering a request',
		'rejected' === axismundi_cal_event_respond_to_invite( $ax_iv_party, (string) $ax_iv_guest->get_uri(), 'reject' )
			&& 0 === count( axismundi_cal_event_attendees( $ax_iv_party ) )
	);
	ax_iv_assert(
		$ax_iv_results,
		'and may answer tentatively, which is an answer rather than the absence of one',
		'tentative' === axismundi_cal_event_respond_to_invite( $ax_iv_party, (string) $ax_iv_guest->get_uri(), 'tentative' )
	);
	// Somebody nobody invited has nothing to answer.
	$ax_iv_stranger = ax_iv_user( $ax_iv_users );
	$ax_iv_stranger_actor = axismundi_actors_get_for_user( $ax_iv_stranger );
	ax_iv_assert(
		$ax_iv_results,
		'somebody who was not invited has no invitation to accept',
		is_wp_error( axismundi_cal_event_respond_to_invite( $ax_iv_party, (string) $ax_iv_stranger_actor->get_uri(), 'accept' ) )
	);

	// -- an invitation is the permission -----------------------------------------------------------------

	/*
	 * `join_eligibility` decides who may ask, and must not decide who may answer: inviting somebody the
	 * Event would have turned away would otherwise produce an invitation that cannot be accepted.
	 */
	wp_set_current_user( $ax_iv_host_user );
	$ax_iv_closed = ax_iv_event( $ax_iv_posts, $ax_iv_host_user, $ax_iv_cal, 'Followers only', array( 'join_eligibility' => 'followers' ) );
	axismundi_cal_event_invite( $ax_iv_closed, (string) $ax_iv_stranger_actor->get_uri() );
	wp_set_current_user( $ax_iv_stranger );
	ax_iv_assert(
		$ax_iv_results,
		'being invited is the permission, so eligibility does not veto the answer',
		! axismundi_cal_event_join_eligible( $ax_iv_closed, (string) $ax_iv_stranger_actor->get_uri() )
			&& 'accepted' === axismundi_cal_event_respond_to_invite( $ax_iv_closed, (string) $ax_iv_stranger_actor->get_uri(), 'accept' )
	);

	// -- taking it back ------------------------------------------------------------------------------------

	wp_set_current_user( $ax_iv_host_user );
	$ax_iv_second = ax_iv_user( $ax_iv_users );
	$ax_iv_second_actor = axismundi_actors_get_for_user( $ax_iv_second );
	axismundi_cal_event_invite( $ax_iv_party, (string) $ax_iv_second_actor->get_uri() );
	$ax_iv_second_cal = (int) axismundi_cal_primary_calendar( (string) $ax_iv_second_actor->get_uri() )['id'];
	ax_iv_assert(
		$ax_iv_results,
		'withdrawing an unanswered invitation removes it rather than leaving a reply nobody made',
		true === axismundi_cal_event_withdraw_invite( $ax_iv_party, (string) $ax_iv_second_actor->get_uri() )
			&& null === axismundi_cal_event_participation( $ax_iv_party, (string) $ax_iv_second_actor->get_uri() )
			&& ! in_array( $ax_iv_party, axismundi_cal_placed_event_ids( $ax_iv_second_cal ), true )
	);
	/*
	 * An answered invitation is a guest, and removing one reads as a different act to everybody
	 * involved. It should say so rather than being spelled `Undo`.
	 */
	$ax_iv_answered = axismundi_cal_event_withdraw_invite( $ax_iv_closed, (string) $ax_iv_stranger_actor->get_uri() );
	ax_iv_assert(
		$ax_iv_results,
		'but an invitation somebody accepted is not withdrawn behind their back',
		is_wp_error( $ax_iv_answered ) && 'ax_event_invite_answered' === $ax_iv_answered->get_error_code()
	);
	// Re-inviting somebody who replied would erase what they said and ask again as if they had not.
	ax_iv_assert(
		$ax_iv_results,
		'and somebody who already replied is not quietly asked again into a pending state',
		is_wp_error( axismundi_cal_event_invite( $ax_iv_party, (string) $ax_iv_guest->get_uri() ) )
			&& 'tentative' === (string) axismundi_cal_event_participation( $ax_iv_party, (string) $ax_iv_guest->get_uri() )['state']
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_iv_posts ) as $ax_iv_post_id ) {
		if ( $ax_iv_post_id > 0 ) {
			wp_delete_post( (int) $ax_iv_post_id, true );
		}
	}
	foreach ( array_unique( $ax_iv_users ) as $ax_iv_user_id ) {
		wp_delete_user( (int) $ax_iv_user_id );
	}
}

$ax_iv_failures = count( array_filter( $ax_iv_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_iv_results ), $ax_iv_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_iv_failures > 0 ? 1 : 0 );
}
exit( $ax_iv_failures > 0 ? 1 : 0 );
