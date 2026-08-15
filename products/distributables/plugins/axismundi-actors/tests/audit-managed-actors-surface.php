<?php
/**
 * Creating and staffing a managed actor (dev-only; dist-excluded).
 *
 * The screen used to say Group everywhere while the model already accepted three kinds, so the one
 * thing it could not do was the thing somebody actually wanted: publish as an Organization. What is
 * checked here is that the kind now travels from the form to the row, and that the manager relation
 * -- the whole reason nobody shares a password -- is editable by the people entitled to edit it and
 * nobody else.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

// The admin surface loads only for admin requests, and WP-CLI is not one. Required directly so the
// screen under test is the screen that ships, rather than a copy of its logic living here.
require_once dirname( __DIR__ ) . '/includes/admin.php';

$ax_ms_results = array();
$ax_ms_users   = array();

/** @param bool[] $results Results. */
function ax_ms_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A plain local account. */
function ax_ms_user( array &$users, string $role = 'author' ) : int {
	$id      = (int) wp_insert_user(
		array( 'user_login' => 'axms' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => $role )
	);
	$users[] = $id;
	return $id;
}

/** The managers section as one viewer sees it. */
function ax_ms_render_managers( int $viewer, Axismundi_Actor $actor ) : string {
	wp_set_current_user( $viewer );
	ob_start();
	axismundi_actors_managers_form( $actor );
	return (string) ob_get_clean();
}

try {
	$ax_ms_owner  = ax_ms_user( $ax_ms_users, 'administrator' );
	$ax_ms_helper = ax_ms_user( $ax_ms_users );
	$ax_ms_other  = ax_ms_user( $ax_ms_users );
	wp_set_current_user( $ax_ms_owner );

	// -- the kind travels ------------------------------------------------------------------------------

	$ax_ms_org = axismundi_actors_create_managed_group(
		array( 'owner_user_id' => $ax_ms_owner, 'preferred_username' => 'axms' . strtolower( wp_generate_password( 8, false, false ) ), 'actor_type' => 'Organization', 'status' => 'internal' )
	);
	ax_ms_assert(
		$ax_ms_results,
		'an organization is created as one, and is managed rather than tied to an account',
		$ax_ms_org instanceof Axismundi_Actor && 'Organization' === $ax_ms_org->get_type() && $ax_ms_org->is_managed()
	);
	/*
	 * The default survives. Anything still posting the older form has no kind to send, and a missing
	 * field must not quietly start producing a different sort of actor than it always did.
	 */
	$ax_ms_group = axismundi_actors_create_managed_group(
		array( 'owner_user_id' => $ax_ms_owner, 'preferred_username' => 'axms' . strtolower( wp_generate_password( 8, false, false ) ), 'status' => 'internal' )
	);
	ax_ms_assert(
		$ax_ms_results,
		'and a request that names no kind still makes the group it always made',
		$ax_ms_group instanceof Axismundi_Actor && 'Group' === $ax_ms_group->get_type()
	);
	ax_ms_assert(
		$ax_ms_results,
		'a kind nothing defines is refused rather than stored',
		is_wp_error( axismundi_actors_create_managed_group( array( 'owner_user_id' => $ax_ms_owner, 'preferred_username' => 'axmsbad', 'actor_type' => 'Person' ) ) )
	);

	$ax_ms_id = $ax_ms_org->get_identity_id();

	// -- who runs it ------------------------------------------------------------------------------------

	$ax_ms_seeded = axismundi_actors_group_managers( $ax_ms_id );
	ax_ms_assert(
		$ax_ms_results,
		'whoever created it is its first owner, so it is never born with nobody able to run it',
		1 === count( $ax_ms_seeded ) && 'owner' === (string) $ax_ms_seeded[0]['role'] && $ax_ms_owner === (int) $ax_ms_seeded[0]['user_id']
	);
	axismundi_actors_add_manager( $ax_ms_id, $ax_ms_helper, 'editor' );
	/*
	 * Publishing as an actor and deciding who else may are different powers. The authority to add
	 * people is what turns a role into a way of keeping it, so an editor does not have it.
	 */
	ax_ms_assert(
		$ax_ms_results,
		'an editor may act as the actor but may not appoint anybody',
		axismundi_actors_managed_actor_can_manage( $ax_ms_id, $ax_ms_helper )
			&& ! axismundi_actors_managed_actor_can_manage( $ax_ms_id, $ax_ms_helper, 'manager' )
	);
	ax_ms_assert(
		$ax_ms_results,
		'and somebody with no relation at all can do neither',
		! axismundi_actors_managed_actor_can_manage( $ax_ms_id, $ax_ms_other )
	);
	/*
	 * The row nothing may edit. An actor with no owner has nobody left who could appoint one, so both
	 * ways out of that state are closed rather than left to be discovered.
	 */
	ax_ms_assert(
		$ax_ms_results,
		'the last owner can be neither demoted nor removed',
		is_wp_error( axismundi_actors_add_manager( $ax_ms_id, $ax_ms_owner, 'editor' ) )
			&& is_wp_error( axismundi_actors_remove_manager( $ax_ms_id, $ax_ms_owner ) )
	);

	// -- what the screen offers -------------------------------------------------------------------------

	$ax_ms_owner_html  = ax_ms_render_managers( $ax_ms_owner, $ax_ms_org );
	$ax_ms_editor_html = ax_ms_render_managers( $ax_ms_helper, $ax_ms_org );
	ax_ms_assert(
		$ax_ms_results,
		'an owner is offered the controls that change who runs it',
		false !== strpos( $ax_ms_owner_html, 'axismundi_actors_save_manager' )
			&& false !== strpos( $ax_ms_owner_html, 'Add a manager' )
	);
	ax_ms_assert(
		$ax_ms_results,
		'an editor sees who else is here and is offered none of them',
		false !== strpos( $ax_ms_editor_html, 'Managers' )
			&& false === strpos( $ax_ms_editor_html, 'axismundi_actors_save_manager' )
			&& false === strpos( $ax_ms_editor_html, 'axismundi_actors_remove_manager' )
	);
	ax_ms_assert(
		$ax_ms_results,
		'and the last owner is shown as staying rather than offered a change that would be refused',
		false !== strpos( $ax_ms_owner_html, 'The last owner stays' )
	);
	/*
	 * A Person actor has no managers, because the account is the relation. Rendering an empty table on
	 * one would invite somebody to add a manager to a person.
	 */
	$ax_ms_person = axismundi_actors_ensure_for_user( $ax_ms_other );
	ax_ms_assert(
		$ax_ms_results,
		'a personal actor has no manager list at all',
		$ax_ms_person instanceof Axismundi_Actor && '' === ax_ms_render_managers( $ax_ms_owner, $ax_ms_person )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_ms_users as $ax_ms_user_id ) {
		wp_delete_user( (int) $ax_ms_user_id );
	}
}

$ax_ms_failures = count( array_filter( $ax_ms_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ms_results ), $ax_ms_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ms_failures > 0 ? 1 : 0 );
}
exit( $ax_ms_failures > 0 ? 1 : 0 );
