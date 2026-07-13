<?php
/**
 * Phase 1 — actor repository regression (dev-only; dist-excluded).
 *
 * Self-contained; installs the schema; `finally` cleanup of every row it creates;
 * exit 0/1. Locks: UUID/URI stable across a handle change; one local Person per
 * user; local handle collisions blocked while duplicate REMOTE handles are allowed;
 * CLI activation seeds the site actor but no Person; user delete tombstones (not
 * deletes); no orphan actor row.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$ax_results = array();
$ax_ids     = array(); // identity_ids to clean up.
$ax_users   = array();

/**
 * @param array $results Accumulator.
 * @param string $label  Contract.
 * @param bool  $cond    Holds.
 * @return void
 */
function ax_rep_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_actors_install();
	$idents = axismundi_actors_identities_table();
	$actors = axismundi_actors_actors_table();

	// --- Person via ensure_for_user ---
	$uid = (int) wp_insert_user( array( 'user_login' => 'ax_rep_alice', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_users[] = $uid;
	$alice = axismundi_actors_ensure_for_user( $uid );
	$ok = ( $alice instanceof Axismundi_Actor );
	if ( $ok ) {
		$ax_ids[] = $alice->get_identity_id();
	}
	ax_rep_assert( $ax_results, 'ensure_for_user creates an internal local Person', $ok && 'Person' === $alice->get_type() && 'user' === $alice->get_scope() && 'internal' === $alice->get_status() && $uid === $alice->get_local_user_id() );

	$uuid0 = $alice->get_uuid();
	$uri0  = $alice->get_uri();
	ax_rep_assert( $ax_results, 'actor_uri is the path /actors/{uuid}', home_url( '/actors/' . $uuid0 ) === $uri0 );

	// --- ensure_for_user idempotent (no duplicate Person) ---
	$alice2 = axismundi_actors_ensure_for_user( $uid );
	ax_rep_assert( $ax_results, 'ensure_for_user is idempotent (same identity)', $alice2 instanceof Axismundi_Actor && $alice2->get_identity_id() === $alice->get_identity_id() );

	// --- duplicate Person for same user is rejected at the DB ---
	$dup = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'preferred_username' => 'alice-again', 'local_user_id' => $uid ) );
	ax_rep_assert( $ax_results, 'a second actor for the same user is rejected', is_wp_error( $dup ) );

	// --- a freshly ensured Person is HANDLE-LESS (handle registered later) ---
	ax_rep_assert( $ax_results, 'a freshly ensured Person has no handle yet', '' === $alice->get_preferred_username() && ! $alice->is_handle_locked() );

	// --- register_handle: first registration locks; UUID/URI stay stable ---
	$reg   = axismundi_actors_register_handle( $alice->get_identity_id(), 'alice_handle' );
	$after = axismundi_actors_get_by_uuid( $uuid0 );
	ax_rep_assert( $ax_results, 'register_handle sets a locked handle without touching UUID/URI', true === $reg && $after instanceof Axismundi_Actor && $after->get_uuid() === $uuid0 && $after->get_uri() === $uri0 && 'alice_handle' === $after->get_preferred_username() && $after->is_handle_locked() );

	// --- the handle is immutable: a second registration is refused ---
	$again = axismundi_actors_register_handle( $alice->get_identity_id(), 'alice_two' );
	ax_rep_assert( $ax_results, 'a locked handle cannot be changed', is_wp_error( $again ) && 'ax_actors_handle_locked' === $again->get_error_code() && null !== axismundi_actors_get_by_handle( 'alice_handle' ) && null === axismundi_actors_get_by_handle( 'alice_two' ) );

	// --- lookups round-trip ---
	ax_rep_assert( $ax_results, 'get_by_uri and get_for_user resolve the same actor', axismundi_actors_get_by_uri( $uri0 ) instanceof Axismundi_Actor && axismundi_actors_get_for_user( $uid )->get_identity_id() === $alice->get_identity_id() );

	// --- create_local with a handle (seed style) auto-resolves collisions and locks ---
	$c1 = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'preferred_username' => 'dupe' ) );
	$c2 = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'preferred_username' => 'dupe' ) );
	if ( $c1 instanceof Axismundi_Actor ) {
		$ax_ids[] = $c1->get_identity_id();
	}
	if ( $c2 instanceof Axismundi_Actor ) {
		$ax_ids[] = $c2->get_identity_id();
	}
	$k1 = (string) $wpdb->get_var( $wpdb->prepare( "SELECT local_handle_key FROM {$actors} WHERE identity_id = %d", $c1->get_identity_id() ) ); // phpcs:ignore WordPress.DB
	$k2 = (string) $wpdb->get_var( $wpdb->prepare( "SELECT local_handle_key FROM {$actors} WHERE identity_id = %d", $c2->get_identity_id() ) ); // phpcs:ignore WordPress.DB
	ax_rep_assert( $ax_results, 'create_local handle collision auto-resolves to distinct locked handles', '' !== $k1 && '' !== $k2 && $k1 !== $k2 && $c1->get_preferred_username() === $k1 && $c2->get_preferred_username() === $k2 && $c1->is_handle_locked() && axismundi_actors_get_by_handle( $k2 )->get_identity_id() === $c2->get_identity_id() );

	// --- registering a taken handle on a handle-less actor is blocked ---
	$fresh = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user' ) );
	if ( $fresh instanceof Axismundi_Actor ) {
		$ax_ids[] = $fresh->get_identity_id();
	}
	$clash = axismundi_actors_register_handle( $fresh->get_identity_id(), 'dupe' );
	ax_rep_assert( $ax_results, 'registering a taken handle is blocked', is_wp_error( $clash ) && 'ax_actors_handle_taken' === $clash->get_error_code() );

	// --- handle mention-interop rule: only [a-z0-9_], no hyphen/dot/edge-underscore ---
	$bad_hyphen = axismundi_actors_register_handle( $fresh->get_identity_id(), 'kim-jiwoon' );
	$bad_edge   = axismundi_actors_register_handle( $fresh->get_identity_id(), '_kim' );
	$fresh_now  = axismundi_actors_get_by_uuid( $fresh->get_uuid() );
	ax_rep_assert( $ax_results, 'hyphen and edge-underscore handles are rejected, leaving the actor handle-less', is_wp_error( $bad_hyphen ) && 'ax_actors_handle' === $bad_hyphen->get_error_code() && is_wp_error( $bad_edge ) && '' === $fresh_now->get_preferred_username() && ! $fresh_now->is_handle_locked() );
	ax_rep_assert( $ax_results, 'suggest folds hyphens/spaces to underscores; validator rejects hyphens', 'kim_jiwoon_kim' === axismundi_actors_suggest_handle( 'Kim-Jiwoon Kim' ) && axismundi_actors_is_valid_handle( 'kim_jiwoon_kim' ) && ! axismundi_actors_is_valid_handle( 'kim-jiwoon' ) && ! axismundi_actors_is_valid_handle( '_kim' ) );

	// --- same REMOTE handle allowed multiple times (local_handle_key NULL) ---
	$now = current_time( 'mysql', true );
	$remote_ok = true;
	foreach ( array( 'https://a.example/users/alice', 'https://b.example/users/alice' ) as $r_uri ) {
		$u = wp_generate_uuid4();
		$wpdb->insert( $idents, array( 'uuid' => $u, 'canonical_uri' => $r_uri, 'canonical_uri_hash' => hash( 'sha256', $r_uri ), 'object_kind' => 'actor', 'origin' => 'remote', 'status' => 'public', 'created_at' => $now, 'updated_at' => $now ) ); // phpcs:ignore WordPress.DB
		$rid = (int) $wpdb->insert_id;
		$ax_ids[] = $rid;
		$ins = $wpdb->insert( $actors, array( 'identity_id' => $rid, 'actor_type' => 'Person', 'actor_scope' => null, 'preferred_username' => 'alice', 'local_handle_key' => null, 'created_at' => $now, 'updated_at' => $now ) ); // phpcs:ignore WordPress.DB
		$remote_ok = $remote_ok && false !== $ins;
	}
	ax_rep_assert( $ax_results, 'the same remote handle is allowed on multiple remote actors', $remote_ok );

	// --- CLI activation: site actor seeded, no Person for a phantom user ---
	wp_set_current_user( 0 );
	$person_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$actors} WHERE actor_scope = 'user'" ); // phpcs:ignore WordPress.DB
	$had_site = (bool) axismundi_actors_get_site_actor();
	axismundi_actors_seed();
	$site = axismundi_actors_get_site_actor();
	if ( $site && ! $had_site ) {
		$ax_ids[] = $site->get_identity_id();
	}
	$person_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$actors} WHERE actor_scope = 'user'" ); // phpcs:ignore WordPress.DB
	ax_rep_assert( $ax_results, 'CLI seed creates the site actor and no Person', $site instanceof Axismundi_Actor && 'site' === $site->get_scope() && $person_before === $person_after );

	// seed is idempotent (no duplicate site actor)
	axismundi_actors_seed();
	$site_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$actors} WHERE actor_scope = 'site'" ); // phpcs:ignore WordPress.DB
	ax_rep_assert( $ax_results, 'reactivation seed does not duplicate the site actor', 1 === $site_count );

	// --- user delete tombstones the identity (rows retained) ---
	$bob = (int) wp_insert_user( array( 'user_login' => 'ax_rep_bob', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$bob_actor = axismundi_actors_ensure_for_user( $bob );
	$bob_id    = $bob_actor->get_identity_id();
	$bob_uuid  = $bob_actor->get_uuid();
	$ax_ids[]  = $bob_id;
	wp_delete_user( $bob );
	$bob_after = axismundi_actors_get_by_uuid( $bob_uuid );
	ax_rep_assert( $ax_results, 'user delete tombstones the identity, keeps the row', $bob_after instanceof Axismundi_Actor && 'tombstone' === $bob_after->get_status() );

	// --- no orphan actor row ---
	$orphans = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$actors} a LEFT JOIN {$idents} i ON i.id = a.identity_id WHERE i.id IS NULL" ); // phpcs:ignore WordPress.DB
	ax_rep_assert( $ax_results, 'no actor row without its identity', 0 === $orphans );

} finally {
	foreach ( array_unique( $ax_ids ) as $iid ) {
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}
	foreach ( $ax_users as $u ) {
		if ( get_userdata( $u ) ) {
			wp_delete_user( $u );
		}
	}
}

$ax_fail = 0;
foreach ( $ax_results as $r ) {
	if ( ! $r ) {
		++$ax_fail;
	}
}
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_results ), $ax_fail );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_fail > 0 ? 1 : 0 );
}
exit( $ax_fail > 0 ? 1 : 0 );
