<?php
/**
 * Being given access, and being asked (dev-only; dist-excluded).
 *
 * The two are separate acts and the separation is the point: access is granted at once and is the
 * only source of truth about what somebody may do, while the calendar appears on their screen only
 * if they say yes -- and disappears again if they remove it, without giving up the access. What is
 * checked here is that neither act can be mistaken for the other, and that a remote Actor is not told
 * a story this site cannot yet make true.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_si_results = array();
$ax_si_users   = array();
$ax_si_cals    = array();

/** @param bool[] $results Results. */
function ax_si_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated Person Actor. */
function ax_si_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axsi' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	if ( $actor instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $actor->get_identity_id(), 'axsi' . strtolower( wp_generate_password( 8, false, false ) ) );
	}
	return $id;
}

/** Whether one calendar is in one Actor's own list. */
function ax_si_in_list( int $calendar_id, string $actor_uri ) : bool {
	return is_array( axismundi_cal_list_entry( $calendar_id, $actor_uri ) );
}

try {
	$ax_si_owner_user = ax_si_user( $ax_si_users );
	$ax_si_guest_user = ax_si_user( $ax_si_users );
	wp_set_current_user( $ax_si_owner_user );
	$ax_si_owner = axismundi_actors_get_for_user( $ax_si_owner_user );
	$ax_si_guest = axismundi_actors_get_for_user( $ax_si_guest_user );

	$ax_si_cal = axismundi_cal_calendar_save(
		array( 'name' => 'Team calendar', 'slug' => 'axsi-' . strtolower( wp_generate_password( 8, false, false ) ), 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => (string) $ax_si_owner->get_uri() )
	);
	$ax_si_cals[] = (int) $ax_si_cal;

	// -- access now, the calendar on their screen only if they want it ------------------------------------

	axismundi_cal_acl_grant( (int) $ax_si_cal, (string) $ax_si_guest->get_uri(), 'writer' );
	axismundi_cal_share_invite( (int) $ax_si_cal, (string) $ax_si_guest->get_uri(), 'writer', (string) $ax_si_owner->get_uri() );
	ax_si_assert(
		$ax_si_results,
		'sharing gives access immediately and puts nothing on the recipient\'s screen',
		axismundi_cal_can_write( (int) $ax_si_cal, (string) $ax_si_guest->get_uri() )
			&& ! ax_si_in_list( (int) $ax_si_cal, (string) $ax_si_guest->get_uri() )
			&& 1 === count( axismundi_cal_pending_share_invitations( (string) $ax_si_guest->get_uri() ) )
	);

	// -- declining is an answer, not a refusal of the access ------------------------------------------------

	axismundi_cal_answer_share_invitation( (int) $ax_si_cal, (string) $ax_si_guest->get_uri(), 'decline' );
	ax_si_assert(
		$ax_si_results,
		'declining leaves the access alone, because who may read a calendar is the owner\'s decision',
		axismundi_cal_can_write( (int) $ax_si_cal, (string) $ax_si_guest->get_uri() )
			&& ! ax_si_in_list( (int) $ax_si_cal, (string) $ax_si_guest->get_uri() )
			&& 'declined' === (string) axismundi_cal_share_invitation( (int) $ax_si_cal, (string) $ax_si_guest->get_uri() )['state']
	);

	// -- accepting is what adds it, and removing it is not giving up access ----------------------------------

	axismundi_cal_share_invite( (int) $ax_si_cal, (string) $ax_si_guest->get_uri(), 'writer', (string) $ax_si_owner->get_uri() );
	axismundi_cal_answer_share_invitation( (int) $ax_si_cal, (string) $ax_si_guest->get_uri(), 'accept' );
	ax_si_assert(
		$ax_si_results,
		'accepting is what puts the calendar in their list',
		ax_si_in_list( (int) $ax_si_cal, (string) $ax_si_guest->get_uri() )
			&& 'accepted' === (string) axismundi_cal_share_invitation( (int) $ax_si_cal, (string) $ax_si_guest->get_uri() )['state']
	);
	/*
	 * The reason the two are separate at all. Google's own mail says you may hide or remove the
	 * calendar at any time -- which is only true if removing it is not the same act as giving up the
	 * permission somebody granted you.
	 */
	axismundi_cal_list_remove( (int) $ax_si_cal, (string) $ax_si_guest->get_uri() );
	ax_si_assert(
		$ax_si_results,
		'removing it from your list does not give up the access',
		! ax_si_in_list( (int) $ax_si_cal, (string) $ax_si_guest->get_uri() )
			&& axismundi_cal_can_write( (int) $ax_si_cal, (string) $ax_si_guest->get_uri() )
	);
	// Withdrawing access is the owner's act, and it is the ACL that answers.
	axismundi_cal_acl_revoke( (int) $ax_si_cal, (string) $ax_si_guest->get_uri() );
	ax_si_assert(
		$ax_si_results,
		'and an invitation cannot be accepted once the access behind it is gone',
		is_wp_error( axismundi_cal_answer_share_invitation( (int) $ax_si_cal, (string) $ax_si_guest->get_uri(), 'accept' ) )
	);

	// -- an address is not a way past a private calendar --------------------------------------------------

	/*
	 * Knowing where a calendar lives must not be the same as being allowed to read it, or sharing
	 * would be decorative. The invitation is the way in.
	 */
	$ax_si_private = axismundi_cal_calendar_get( (int) $ax_si_cal );
	wp_set_current_user( $ax_si_guest_user );
	$ax_si_denied = axismundi_cal_subscribe_url( axismundi_cal_calendar_ics_url( $ax_si_private ) ?: home_url( '/calendar/' . $ax_si_private['slug'] . '.ics' ) );
	ax_si_assert(
		$ax_si_results,
		'a private calendar cannot be added by knowing its address',
		is_wp_error( $ax_si_denied ) && 'ax_cal_subscribe_private' === $ax_si_denied->get_error_code()
	);
	/*
	 * Public is different, and even then it is not mirrored: a copy of our own calendar would go stale
	 * under an id nobody publishes. The address resolves to the calendar it names.
	 */
	wp_set_current_user( $ax_si_owner_user );
	axismundi_cal_acl_grant( (int) $ax_si_cal, '', 'reader', 'public' );
	wp_set_current_user( $ax_si_guest_user );
	$ax_si_added = axismundi_cal_subscribe_url( home_url( '/calendar/' . $ax_si_private['slug'] . '.ics' ) );
	ax_si_assert(
		$ax_si_results,
		'a public one resolves to the calendar itself rather than being mirrored as a foreign feed',
		(int) $ax_si_cal === (int) $ax_si_added && ax_si_in_list( (int) $ax_si_cal, (string) $ax_si_guest->get_uri() )
	);

	// -- which section it belongs in --------------------------------------------------------------------------

	/*
	 * Not "how much access" but "whose calendar is this to me". A writer on somebody else's calendar
	 * is a recipient; a calendar found by its public address is a subscription however much of it can
	 * be read.
	 */
	ax_si_assert(
		$ax_si_results,
		'a calendar you were given access to directly is yours; one you added by its address is a subscription',
		'mine' === axismundi_cal_calendar_list_section( (int) $ax_si_cal, (string) $ax_si_owner->get_uri() )
			&& 'subscribed' === axismundi_cal_calendar_list_section( (int) $ax_si_cal, (string) $ax_si_guest->get_uri() )
	);
	axismundi_cal_acl_grant( (int) $ax_si_cal, (string) $ax_si_guest->get_uri(), 'reader' );
	ax_si_assert(
		$ax_si_results,
		'and being shared with directly moves it out of subscriptions, whatever the role',
		'mine' === axismundi_cal_calendar_list_section( (int) $ax_si_cal, (string) $ax_si_guest->get_uri() )
	);

	// -- a remote Actor is not told a story this site cannot make true -------------------------------------------

	/*
	 * The honest half of "remote handles work". A rule can be stored for one, and it will mean
	 * something the day delivery exists; what must not happen is the screen reporting that a calendar
	 * was shared when the other side has no way to hear about it or read it.
	 */
	ax_si_assert(
		$ax_si_results,
		'a local Actor can be delivered an invitation, and a remote one cannot yet',
		axismundi_cal_invitation_deliverable( (string) $ax_si_guest->get_uri() )
			&& ! axismundi_cal_invitation_deliverable( 'https://example.org/actors/019' )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_si_cals ) as $ax_si_cal_id ) {
		axismundi_cal_calendar_delete( (int) $ax_si_cal_id );
	}
	foreach ( array_unique( $ax_si_users ) as $ax_si_user_id ) {
		wp_delete_user( (int) $ax_si_user_id );
	}
}

$ax_si_failures = count( array_filter( $ax_si_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_si_results ), $ax_si_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_si_failures > 0 ? 1 : 0 );
}
exit( $ax_si_failures > 0 ? 1 : 0 );
