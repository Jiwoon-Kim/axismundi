<?php
/**
 * Choosing which Actor you publish as (dev-only; dist-excluded).
 *
 * The property under test is not "the menu works" but "the stored choice is never
 * authority": a manager role can be taken away between choosing and posting, and the
 * selection must lose its force at that moment rather than at the next login. The other
 * half is separation -- nothing here may resolve the acting Actor from the Actor a page
 * happens to be about, or the act of reading an Organization's profile becomes the act
 * of speaking for it.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_aa_results = array();
$ax_aa_users   = array();

/** @param bool[] $results Results. */
function ax_aa_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A plain local account with an activated Person Actor. */
function ax_aa_user( array &$users, string $role = 'author' ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axaa' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => $role )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	if ( $actor instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $actor->get_identity_id(), 'axaa' . strtolower( wp_generate_password( 8, false, false ) ) );
	}
	return $id;
}

try {
	$ax_aa_owner   = ax_aa_user( $ax_aa_users );
	$ax_aa_helper  = ax_aa_user( $ax_aa_users );
	$ax_aa_admin   = ax_aa_user( $ax_aa_users, 'administrator' );
	$ax_aa_org     = axismundi_actors_create_managed_actor(
		array( 'owner_user_id' => $ax_aa_owner, 'preferred_username' => 'axaa' . strtolower( wp_generate_password( 8, false, false ) ), 'actor_type' => 'Organization', 'status' => 'public' )
	);
	$ax_aa_org_id  = $ax_aa_org->get_identity_id();
	$ax_aa_person  = axismundi_actors_get_for_user( $ax_aa_owner );

	// -- what is on offer ------------------------------------------------------------------------------

	$ax_aa_options = axismundi_actors_acting_actor_options( $ax_aa_owner );
	ax_aa_assert(
		$ax_aa_results,
		'a user is offered their own Person first, then what they run',
		2 === count( $ax_aa_options )
			&& $ax_aa_person->get_identity_id() === $ax_aa_options[0]->get_identity_id()
			&& $ax_aa_org_id === $ax_aa_options[1]->get_identity_id()
	);
	ax_aa_assert(
		$ax_aa_results,
		'and somebody with no relation to it is offered only themselves',
		1 === count( axismundi_actors_acting_actor_options( $ax_aa_helper ) )
	);
	/*
	 * The relation is the whole truth about who runs a managed actor. An administrator may claim it
	 * first, visibly; what must not happen is publishing as it because of a capability nobody granted
	 * for that purpose.
	 */
	ax_aa_assert(
		$ax_aa_results,
		'a site administrator is not silently able to speak for an actor nobody appointed them to',
		! axismundi_actors_can_act_as( $ax_aa_org, $ax_aa_admin )
	);

	// -- choosing --------------------------------------------------------------------------------------

	ax_aa_assert(
		$ax_aa_results,
		'with no choice made, a user publishes as themselves',
		axismundi_actors_acting_actor( $ax_aa_owner )->get_identity_id() === $ax_aa_person->get_identity_id()
	);
	ax_aa_assert(
		$ax_aa_results,
		'choosing an actor they run is stored and takes effect',
		true === axismundi_actors_set_acting_actor( $ax_aa_owner, $ax_aa_org_id )
			&& axismundi_actors_acting_actor( $ax_aa_owner )->get_identity_id() === $ax_aa_org_id
	);
	ax_aa_assert(
		$ax_aa_results,
		'choosing one they do not run is refused rather than stored',
		is_wp_error( axismundi_actors_set_acting_actor( $ax_aa_helper, $ax_aa_org_id ) )
			&& axismundi_actors_acting_actor( $ax_aa_helper )->get_identity_id() !== $ax_aa_org_id
	);

	// -- the choice is a preference, not a capability ---------------------------------------------------

	/*
	 * The point of the slice. The selection was made while the role existed and is still sitting in
	 * user meta; what decides is the relation as it stands now.
	 */
	axismundi_actors_add_manager( $ax_aa_org_id, $ax_aa_helper, 'editor' );
	axismundi_actors_set_acting_actor( $ax_aa_helper, $ax_aa_org_id );
	axismundi_actors_remove_manager( $ax_aa_org_id, $ax_aa_helper );
	ax_aa_assert(
		$ax_aa_results,
		'a revoked manager stops publishing as the actor on the next read, not the next login',
		(int) get_user_meta( $ax_aa_helper, AXISMUNDI_ACTORS_ACTING_META, true ) === $ax_aa_org_id
			&& ! axismundi_actors_can_act_as( $ax_aa_org, $ax_aa_helper )
			&& axismundi_actors_acting_actor( $ax_aa_helper )->get_local_user_id() === $ax_aa_helper
	);
	/*
	 * Falling back rather than failing: an actor being disabled is a reason to stop speaking for it,
	 * never a reason to stop the person writing as themselves.
	 */
	axismundi_actors_set_status( $ax_aa_org_id, 'disabled' );
	ax_aa_assert(
		$ax_aa_results,
		'a disabled selection falls back to the user rather than erroring',
		axismundi_actors_acting_actor( $ax_aa_owner ) instanceof Axismundi_Actor
			&& axismundi_actors_acting_actor( $ax_aa_owner )->get_local_user_id() === $ax_aa_owner
	);
	axismundi_actors_set_status( $ax_aa_org_id, 'public' );
	ax_aa_assert(
		$ax_aa_results,
		'and it comes back by itself once the actor is usable again, because the choice was never lost',
		axismundi_actors_acting_actor( $ax_aa_owner )->get_identity_id() === $ax_aa_org_id
	);
	ax_aa_assert(
		$ax_aa_results,
		'clearing the choice returns the user to their own Person',
		true === axismundi_actors_set_acting_actor( $ax_aa_owner, 0 )
			&& axismundi_actors_acting_actor( $ax_aa_owner )->get_identity_id() === $ax_aa_person->get_identity_id()
	);

	// -- the two Actors stay apart ----------------------------------------------------------------------

	/*
	 * Reading is not speaking. Standing on the Organization's profile page must leave the acting Actor
	 * exactly where the user put it, or the rename that separated these two names bought nothing.
	 */
	wp_set_current_user( $ax_aa_helper );
	$GLOBALS['axismundi_actors_profile_actor'] = $ax_aa_org;
	ax_aa_assert(
		$ax_aa_results,
		'being on an actor profile does not make the reader publish as it',
		axismundi_actors_profile_actor() instanceof Axismundi_Actor
			&& axismundi_actors_profile_actor()->get_identity_id() === $ax_aa_org_id
			&& axismundi_actors_acting_actor( $ax_aa_helper )->get_local_user_id() === $ax_aa_helper
	);
	unset( $GLOBALS['axismundi_actors_profile_actor'] );

	// -- what the menu offers ---------------------------------------------------------------------------

	require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
	axismundi_actors_set_acting_actor( $ax_aa_owner, 0 );
	$ax_aa_bar = new WP_Admin_Bar();
	wp_set_current_user( $ax_aa_owner );
	axismundi_actors_acting_actor_admin_bar( $ax_aa_bar );
	$ax_aa_switch  = $ax_aa_bar->get_node( 'ax-acting-actor-' . $ax_aa_org_id );
	$ax_aa_group   = $ax_aa_bar->get_node( 'ax-acting-actor' );
	$ax_aa_current = $ax_aa_bar->get_node( 'ax-acting-actor-current' );
	/*
	 * A POST with a nonce, not a link. Publishing under a different name is a state change, and a
	 * change a crafted URL can make on your behalf is one somebody else can make for you.
	 */
	ax_aa_assert(
		$ax_aa_results,
		'switching is a nonce-protected POST rather than something a link can do to you',
		null !== $ax_aa_switch
			&& false !== strpos( (string) $ax_aa_switch->title, 'method="post"' )
			&& false !== strpos( (string) $ax_aa_switch->title, '_wpnonce' )
	);
	/*
	 * Under the account menu, because "who am I publishing as" is the question that menu already
	 * answers -- and in a group of its own, so it is never read as part of Edit Profile or Log Out.
	 */
	ax_aa_assert(
		$ax_aa_results,
		'the switcher lives in its own group inside the account menu, not as a bar of its own',
		null !== $ax_aa_group && 'my-account' === $ax_aa_group->parent
			&& null !== $ax_aa_current && 'ax-acting-actor' === $ax_aa_current->parent
			&& 'ax-acting-actor' === $ax_aa_switch->parent
	);
	// Looking and speaking are separate commands, and the one already in force is not offered again.
	ax_aa_assert(
		$ax_aa_results,
		'the actor in force is shown and linked, never offered as a switch to itself',
		false === strpos( (string) $ax_aa_current->title, 'method="post"' )
			&& null === $ax_aa_bar->get_node( 'ax-acting-actor-' . $ax_aa_person->get_identity_id() )
	);
	// A user with one identity has no choice to make; a menu offering it would only be furniture.
	$ax_aa_lone = new WP_Admin_Bar();
	wp_set_current_user( $ax_aa_helper );
	axismundi_actors_acting_actor_admin_bar( $ax_aa_lone );
	ax_aa_assert(
		$ax_aa_results,
		'a user with nothing to switch between is shown no switcher',
		null === $ax_aa_lone->get_node( 'ax-acting-actor' )
	);

	// -- an account with nothing to publish as ----------------------------------------------------------

	$ax_aa_bare = (int) wp_insert_user(
		array( 'user_login' => 'axaa' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'author' )
	);
	$ax_aa_users[] = $ax_aa_bare;
	ax_aa_assert(
		$ax_aa_results,
		'an account that never activated an Actor has none, and is told so rather than given one',
		null === axismundi_actors_acting_actor( $ax_aa_bare )
			&& array() === axismundi_actors_acting_actor_options( $ax_aa_bare )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_aa_users as $ax_aa_user_id ) {
		wp_delete_user( (int) $ax_aa_user_id );
	}
}

$ax_aa_failures = count( array_filter( $ax_aa_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_aa_results ), $ax_aa_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_aa_failures > 0 ? 1 : 0 );
}
exit( $ax_aa_failures > 0 ? 1 : 0 );
