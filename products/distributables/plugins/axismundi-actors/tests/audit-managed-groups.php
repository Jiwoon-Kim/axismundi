<?php
/**
 * Managed-actor authority kernel regression (dev-only; dist-excluded).
 *
 * Self-contained; `finally` cleanup of every row and user it creates; exit 0/1.
 * Locks (DATA-MODEL §9.6.1): a managed Group is `scope=managed`, `type=Group`,
 * `local_user_id=NULL`, and resolves at `/@handle` and `/actors/{uuid}` like any
 * actor; creation seeds exactly one `owner`; `can_manage` is a role-ranked relation
 * predicate that excludes strangers and never auto-grants a site admin; the last
 * owner can be neither demoted nor removed; managers attach only to a managed actor;
 * the manager relation is authority ONLY, carrying no follow/membership state.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/managed-groups.php';
require_once dirname( __DIR__ ) . '/includes/routing.php';
require_once dirname( __DIR__ ) . '/includes/admin.php';

global $wpdb;
$ax_mg_results     = array();
$ax_mg_identity_ids = array();
$ax_mg_user_ids     = array();

/** @param bool[] $results Results. */
function ax_mg_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Create a throwaway WP user and remember it for cleanup. */
function ax_mg_user( array &$user_ids ) : int {
	$uid = (int) wp_insert_user(
		array(
			'user_login' => 'ax_mg_' . strtolower( wp_generate_password( 10, false, false ) ),
			'user_pass'  => wp_generate_password(),
			'role'       => 'editor',
		)
	);
	if ( $uid > 0 ) {
		$user_ids[] = $uid;
	}
	return $uid;
}

try {
	$owner    = ax_mg_user( $ax_mg_user_ids );
	$manager  = ax_mg_user( $ax_mg_user_ids );
	$editor   = ax_mg_user( $ax_mg_user_ids );
	$stranger = ax_mg_user( $ax_mg_user_ids );

	// A managed Group is a first-class actor: managed scope, Group type, no WP user,
	// and it resolves through the same handle/uuid routes a Person does.
	$group = axismundi_actors_create_managed_actor(
		array( 'owner_user_id' => $owner, 'preferred_username' => 'axmg' . strtolower( wp_generate_password( 6, false, false ) ) )
	);
	if ( $group instanceof Axismundi_Actor ) {
		$ax_mg_identity_ids[] = $group->get_identity_id();
	}
	$identity_id = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	ax_mg_assert(
		$ax_mg_results,
		'a managed Group is scope=managed, type=Group, has no local WP user, and resolves by handle and uuid',
		$group instanceof Axismundi_Actor
			&& 'managed' === $group->get_scope()
			&& 'Group' === $group->get_type()
			&& $group->is_managed()
			&& null === $group->get_local_user_id()
			&& axismundi_actors_get_by_handle( $group->get_preferred_username() ) instanceof Axismundi_Actor
			&& axismundi_actors_get_by_uuid( $group->get_uuid() ) instanceof Axismundi_Actor
	);

	$public_group = axismundi_actors_create_managed_actor(
		array( 'owner_user_id' => $owner, 'preferred_username' => 'axmgpub' . strtolower( wp_generate_password( 6, false, false ) ), 'status' => 'public' )
	);
	if ( $public_group instanceof Axismundi_Actor ) {
		$ax_mg_identity_ids[] = $public_group->get_identity_id();
	}
	ax_mg_assert(
		$ax_mg_results,
		'the public Group directory includes published managed Groups and excludes internal ones',
		$public_group instanceof Axismundi_Actor
			&& in_array( $public_group->get_identity_id(), array_map( static fn( Axismundi_Actor $actor ) : int => $actor->get_identity_id(), axismundi_actors_get_public_managed_actors( 100 ) ), true )
			&& ! in_array( $identity_id, array_map( static fn( Axismundi_Actor $actor ) : int => $actor->get_identity_id(), axismundi_actors_get_public_managed_actors( 100 ) ), true )
	);

	// Creation seeds exactly one owner -- there is never an observable ownerless
	// managed actor.
	ax_mg_assert(
		$ax_mg_results,
		'creating a managed Group seeds exactly one owner',
		1 === axismundi_actors_managed_owner_count( $identity_id )
			&& axismundi_actors_managed_actor_can_manage( $identity_id, $owner, 'owner' )
	);

	// A missing/invalid owner is refused before any actor is created.
	$no_owner = axismundi_actors_create_managed_actor( array( 'preferred_username' => 'axmgno' ) );
	ax_mg_assert(
		$ax_mg_results,
		'creating a managed Group without a valid owner fails closed',
		is_wp_error( $no_owner ) && 'ax_actors_managed_owner' === $no_owner->get_error_code()
	);

	// can_manage is a role-ranked relation predicate: a manager clears "any" and
	// "editor" but not "owner"; a stranger clears nothing; a site admin is NOT
	// auto-granted by the relation itself.
	axismundi_actors_add_manager( $identity_id, $manager, 'manager' );
	axismundi_actors_add_manager( $identity_id, $editor, 'editor' );
	$admin = ax_mg_user( $ax_mg_user_ids );
	$admin_user = get_user_by( 'id', $admin );
	if ( $admin_user instanceof WP_User ) {
		$admin_user->set_role( 'administrator' );
	}
	ax_mg_assert(
		$ax_mg_results,
		'can_manage ranks roles, excludes strangers, and does not auto-grant a site admin',
		axismundi_actors_managed_actor_can_manage( $identity_id, $manager )
			&& axismundi_actors_managed_actor_can_manage( $identity_id, $manager, 'editor' )
			&& ! axismundi_actors_managed_actor_can_manage( $identity_id, $manager, 'owner' )
			&& axismundi_actors_managed_actor_can_manage( $identity_id, $editor, 'editor' )
			&& ! axismundi_actors_managed_actor_can_manage( $identity_id, $editor, 'manager' )
			&& ! axismundi_actors_managed_actor_can_manage( $identity_id, $stranger )
			&& ! axismundi_actors_managed_actor_can_manage( $identity_id, $admin )
	);

	// The admin surface delegates managed-Group editing to the same authority
	// relation; it must not accidentally treat a Group like a user-owned Person.
	ax_mg_assert(
		$ax_mg_results,
		'the shared profile-management gate grants Group managers and excludes a site admin without a manager relation',
		axismundi_actors_can_manage( $group, $manager )
			&& ! axismundi_actors_can_manage( $group, $stranger )
			&& ! axismundi_actors_can_manage( $group, $admin )
	);

	$claimed = axismundi_actors_claim_managed_group( $identity_id, $admin );
	$all_groups = axismundi_actors_list_all_managed_actors();
	ax_mg_assert(
		$ax_mg_results,
		'a site administrator explicitly claims any local managed Group as a manager without changing ordinary can_manage rules',
		true === $claimed
			&& axismundi_actors_managed_actor_can_manage( $identity_id, $admin, 'manager' )
			&& in_array( $identity_id, array_map( static fn( Axismundi_Actor $actor ) : int => $actor->get_identity_id(), $all_groups ), true )
	);

	// The last owner is protected from both demotion and removal.
	$demote_last = axismundi_actors_add_manager( $identity_id, $owner, 'editor' );
	$remove_last = axismundi_actors_remove_manager( $identity_id, $owner );
	ax_mg_assert(
		$ax_mg_results,
		'the last owner can be neither demoted nor removed',
		is_wp_error( $demote_last ) && 'ax_actors_last_owner' === $demote_last->get_error_code()
			&& is_wp_error( $remove_last ) && 'ax_actors_last_owner' === $remove_last->get_error_code()
			&& axismundi_actors_managed_actor_can_manage( $identity_id, $owner, 'owner' )
	);

	// Once a second owner exists, removing the first is allowed -- the invariant is
	// "at least one", not "never remove".
	axismundi_actors_add_manager( $identity_id, $manager, 'owner' );
	$remove_ok = axismundi_actors_remove_manager( $identity_id, $owner );
	ax_mg_assert(
		$ax_mg_results,
		'an owner can be removed once another owner remains, keeping at least one owner',
		true === $remove_ok
			&& 1 === axismundi_actors_managed_owner_count( $identity_id )
			&& ! axismundi_actors_managed_actor_can_manage( $identity_id, $owner )
	);

	// Routing preview authority follows the manager relation for a managed actor,
	// not WP-user identity: the remaining owner and the editor may preview, a
	// stranger may not.
	ax_mg_assert(
		$ax_mg_results,
		'preview authority for a managed Group is the manager relation, and excludes non-managers',
		axismundi_actors_can_preview( $group, $manager )
			&& axismundi_actors_can_preview( $group, $editor )
			&& ! axismundi_actors_can_preview( $group, $stranger )
	);

	// list_manageable_groups is the join a Forum uses to offer bindable Groups; a
	// minimum role narrows it.
	ax_mg_assert(
		$ax_mg_results,
		'list_manageable_groups returns the Group for its managers and honors a minimum role',
		1 === count( axismundi_actors_list_manageable_groups( $editor ) )
			&& 1 === count( axismundi_actors_list_manageable_groups( $manager, 'owner' ) )
			&& 0 === count( axismundi_actors_list_manageable_groups( $editor, 'owner' ) )
			&& 0 === count( axismundi_actors_list_manageable_groups( $stranger ) )
	);

	// Managers attach ONLY to a managed actor: the site actor (or a user Person)
	// cannot take a manager row.
	$site = axismundi_actors_get_site_actor();
	$bad_target = $site instanceof Axismundi_Actor
		? axismundi_actors_add_manager( $site->get_identity_id(), $manager, 'editor' )
		: new WP_Error( 'ax_actors_manager_target', 'no site actor' );
	ax_mg_assert(
		$ax_mg_results,
		'a manager cannot be attached to a non-managed actor',
		is_wp_error( $bad_target ) && 'ax_actors_manager_target' === $bad_target->get_error_code()
	);
} finally {
	$ax_identities = axismundi_actors_identities_table();
	$ax_actors     = axismundi_actors_actors_table();
	$ax_managers   = axismundi_actors_managers_table();
	foreach ( array_unique( $ax_mg_identity_ids ) as $ax_id ) {
		$wpdb->delete( $ax_managers, array( 'identity_id' => (int) $ax_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $ax_actors, array( 'identity_id' => (int) $ax_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $ax_identities, array( 'id' => (int) $ax_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	if ( ! empty( $ax_mg_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_mg_user_ids ) as $ax_uid ) {
			if ( get_userdata( (int) $ax_uid ) ) {
				wp_delete_user( (int) $ax_uid );
			}
		}
	}
}

$ax_mg_failed = count( array_filter( $ax_mg_results, static fn( $r ) => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_mg_results ) - $ax_mg_failed, count( $ax_mg_results ) );
exit( $ax_mg_failed > 0 ? 1 : 0 );
