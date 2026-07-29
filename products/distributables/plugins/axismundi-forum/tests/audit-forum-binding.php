<?php
/**
 * Forum F0 binding-contract regression (dev-only; dist-excluded).
 *
 * Locks docs/AXISMUNDI-FORUM-ARCHITECTURE.md §6-F0 acceptance: a Group Actor exists
 * without a Forum; a Forum cannot bind a Person, a remote Actor, a missing identity,
 * or an already-bound Group; binding requires Actors manager authority (not a WP
 * role); the relation is 1:1 both ways; and unbinding or deleting a Forum removes only
 * the binding row while the Group Actor survives (never tombstoned).
 *
 * Self-contained; `finally` cleans every row/post/user it creates; exit 0/1.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once __DIR__ . '/../includes/repository.php';

axismundi_forum_install();

global $wpdb;
$ax_f_results     = array();
$ax_f_identity_ids = array();
$ax_f_user_ids     = array();
$ax_f_post_ids     = array();

/** @param bool[] $results Results. */
function ax_f_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** True when $r is a WP_Error carrying $code. */
function ax_f_err( $r, string $code ) : bool {
	return is_wp_error( $r ) && $code === $r->get_error_code();
}

/** Create a throwaway WP user (editor) and remember it. */
function ax_f_user( array &$user_ids ) : int {
	$uid = (int) wp_insert_user(
		array(
			'user_login' => 'ax_f_' . strtolower( wp_generate_password( 10, false, false ) ),
			'user_pass'  => wp_generate_password(),
			'role'       => 'editor',
		)
	);
	if ( $uid > 0 ) {
		$user_ids[] = $uid;
	}
	return $uid;
}

/** Create an ax_forum post and remember it. */
function ax_f_forum( array &$post_ids ) : int {
	$pid = (int) wp_insert_post(
		array(
			'post_type'   => 'ax_forum',
			'post_status' => 'publish',
			'post_title'  => 'Audit Forum ' . wp_generate_password( 6, false, false ),
		)
	);
	if ( $pid > 0 ) {
		$post_ids[] = $pid;
	}
	return $pid;
}

/** Insert a minimal remote Group actor fixture; return its identity id. */
function ax_f_remote_group( array &$identity_ids ) : int {
	global $wpdb;
	$now  = current_time( 'mysql', true );
	$uri  = 'https://remote.example/actors/' . wp_generate_password( 8, false, false );
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test fixture.
		axismundi_actors_identities_table(),
		array(
			'uuid'               => wp_generate_uuid4(),
			'canonical_uri'      => $uri,
			'canonical_uri_hash' => hash( 'sha256', $uri ),
			'object_kind'        => 'actor',
			'origin'             => 'remote',
			'status'             => 'public',
			'created_at'         => $now,
			'updated_at'         => $now,
		)
	);
	$rid = (int) $wpdb->insert_id;
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test fixture.
		axismundi_actors_actors_table(),
		array(
			'identity_id' => $rid,
			'actor_type'  => 'Group',
			'created_at'  => $now,
			'updated_at'  => $now,
		)
	);
	if ( $rid > 0 ) {
		$identity_ids[] = $rid;
	}
	return $rid;
}

try {
	$owner    = ax_f_user( $ax_f_user_ids );
	$stranger = ax_f_user( $ax_f_user_ids );
	$person_u = ax_f_user( $ax_f_user_ids );

	$group1 = axismundi_actors_create_managed_group( array( 'owner_user_id' => $owner, 'preferred_username' => 'axf' . strtolower( wp_generate_password( 6, false, false ) ) ) );
	$group2 = axismundi_actors_create_managed_group( array( 'owner_user_id' => $owner, 'preferred_username' => 'axf' . strtolower( wp_generate_password( 6, false, false ) ) ) );
	$gid1   = $group1 instanceof Axismundi_Actor ? $group1->get_identity_id() : 0;
	$gid2   = $group2 instanceof Axismundi_Actor ? $group2->get_identity_id() : 0;
	if ( $gid1 > 0 ) {
		$ax_f_identity_ids[] = $gid1;
	}
	if ( $gid2 > 0 ) {
		$ax_f_identity_ids[] = $gid2;
	}

	$person = axismundi_actors_ensure_for_user( $person_u );
	$pid_id = $person instanceof Axismundi_Actor ? $person->get_identity_id() : 0;
	if ( $pid_id > 0 ) {
		$ax_f_identity_ids[] = $pid_id;
	}
	$remote_gid = ax_f_remote_group( $ax_f_identity_ids );

	$forum_a = ax_f_forum( $ax_f_post_ids );
	$forum_b = ax_f_forum( $ax_f_post_ids );

	// A managed Group exists on its own, with no Forum binding.
	ax_f_assert(
		$ax_f_results,
		'a managed Group Actor exists without a Forum and starts unbound',
		$group1 instanceof Axismundi_Actor && null === axismundi_forum_get_binding( $forum_a )
	);

	// A local Person Actor is not a Forum-eligible target.
	ax_f_assert(
		$ax_f_results,
		'binding a local Person Actor is rejected',
		ax_f_err( axismundi_forum_bind_group( $forum_a, $pid_id, $owner ), 'ax_forum_group_type' )
	);

	// A remote Group is rejected -- F0 binds local Groups only.
	ax_f_assert(
		$ax_f_results,
		'binding a remote Group Actor is rejected',
		ax_f_err( axismundi_forum_bind_group( $forum_a, $remote_gid, $owner ), 'ax_forum_group_remote' )
	);

	// A missing identity is rejected before any row is written.
	ax_f_assert(
		$ax_f_results,
		'binding a non-existent identity is rejected',
		ax_f_err( axismundi_forum_bind_group( $forum_a, 99999999, $owner ), 'ax_forum_group_missing' )
	);

	// Authority is the Actors manager relation, not a WP role: a stranger cannot bind.
	ax_f_assert(
		$ax_f_results,
		'a non-manager cannot bind a Group (authority via Actors, not post meta)',
		ax_f_err( axismundi_forum_bind_group( $forum_a, $gid1, $stranger ), 'ax_forum_forbidden' )
	);

	// The Group's manager binds it successfully, and the binding resolves back.
	$bind_ok = axismundi_forum_bind_group( $forum_a, $gid1, $owner );
	$bound   = axismundi_forum_get_bound_group( $forum_a );
	ax_f_assert(
		$ax_f_results,
		'a manager binds an eligible Group and it resolves back to the Actor',
		true === $bind_ok
			&& $bound instanceof Axismundi_Actor
			&& $bound->get_identity_id() === $gid1
			&& $forum_a === axismundi_forum_get_forum_for_group( $gid1 )
	);

	// 1:1 -- the same Group cannot be bound to a second Forum.
	ax_f_assert(
		$ax_f_results,
		'an already-bound Group cannot be bound to another Forum',
		ax_f_err( axismundi_forum_bind_group( $forum_b, $gid1, $owner ), 'ax_forum_group_bound' )
	);

	// 1:1 -- a Forum already bound cannot take a different Group.
	ax_f_assert(
		$ax_f_results,
		'a Forum already bound cannot bind a different Group',
		ax_f_err( axismundi_forum_bind_group( $forum_a, $gid2, $owner ), 'ax_forum_forum_bound' )
	);

	// Re-binding the same pair is idempotent, not an error.
	ax_f_assert(
		$ax_f_results,
		'rebinding the same Group to the same Forum is idempotent',
		true === axismundi_forum_bind_group( $forum_a, $gid1, $owner )
	);

	// Unbinding removes only the binding row; the Group Actor survives.
	$forbidden_unbind = axismundi_forum_unbind_group( $forum_a, $stranger );
	ax_f_assert(
		$ax_f_results,
		'a non-manager cannot remove a Forum binding',
		ax_f_err( $forbidden_unbind, 'ax_forum_forbidden' )
			&& null !== axismundi_forum_get_binding( $forum_a )
	);

	$unbound      = axismundi_forum_unbind_group( $forum_a, $owner );
	$group_after  = axismundi_actors_get_by_identity( $gid1 );
	ax_f_assert(
		$ax_f_results,
		'unbinding removes the row only and never tombstones the Group Actor',
		true === $unbound
			&& null === axismundi_forum_get_binding( $forum_a )
			&& $group_after instanceof Axismundi_Actor
			&& 'managed' === $group_after->get_scope()
	);

	// Deleting the Forum post drops the binding but leaves the Group Actor intact.
	axismundi_forum_bind_group( $forum_a, $gid1, $owner );
	ax_f_assert(
		$ax_f_results,
		'a non-manager cannot delete a bound Forum through WordPress capabilities',
		in_array( 'do_not_allow', map_meta_cap( 'delete_post', $stranger, $forum_a ), true )
			&& ! in_array( 'do_not_allow', map_meta_cap( 'delete_post', $owner, $forum_a ), true )
	);
	wp_delete_post( $forum_a, true );
	$group_final = axismundi_actors_get_by_identity( $gid1 );
	ax_f_assert(
		$ax_f_results,
		'deleting a Forum removes its binding but the Group Actor remains',
		null === axismundi_forum_get_binding( $forum_a )
			&& 0 === axismundi_forum_get_forum_for_group( $gid1 )
			&& $group_final instanceof Axismundi_Actor
	);
} finally {
	$ax_identities = axismundi_actors_identities_table();
	$ax_actors     = axismundi_actors_actors_table();
	$ax_managers   = axismundi_actors_managers_table();
	$ax_bindings   = axismundi_forum_bindings_table();
	foreach ( array_unique( $ax_f_post_ids ) as $pid ) {
		$wpdb->delete( $ax_bindings, array( 'forum_post_id' => (int) $pid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		if ( get_post( (int) $pid ) ) {
			wp_delete_post( (int) $pid, true );
		}
	}
	foreach ( array_unique( $ax_f_identity_ids ) as $iid ) {
		$wpdb->delete( $ax_managers, array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $ax_actors, array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $ax_identities, array( 'id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	if ( ! empty( $ax_f_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_f_user_ids ) as $uid ) {
			if ( get_userdata( (int) $uid ) ) {
				wp_delete_user( (int) $uid );
			}
		}
	}
}

$ax_f_failed = count( array_filter( $ax_f_results, static fn( $r ) => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_f_results ) - $ax_f_failed, count( $ax_f_results ) );
exit( $ax_f_failed > 0 ? 1 : 0 );
