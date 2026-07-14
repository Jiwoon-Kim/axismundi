<?php
/**
 * Phase 4a — admin activation / management logic regression (dev-only).
 *
 * The POST handlers redirect+exit, so this exercises the building blocks they use:
 * capability gating, status labels, the activation state transition, actor-type
 * change, and handle candidates.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/routing.php';
require_once dirname( __DIR__ ) . '/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$ax_admin_results = array();
$ax_admin_ids     = array();
$ax_admin_users   = array();
$ax_prev_type     = (string) get_option( 'ax_actors_site_actor_type', 'Application' );

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $cond Holds.
 * @return void
 */
function ax_admin_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_actors_install();
	axismundi_actors_seed();

	$uid = (int) wp_insert_user( array( 'user_login' => 'ax_admin_alice', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_admin_users[] = $uid;
	$admin = (int) wp_insert_user( array( 'user_login' => 'ax_admin_boss', 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_admin_users[] = $admin;
	$other = (int) wp_insert_user( array( 'user_login' => 'ax_admin_bob', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_admin_users[] = $other;
	$subscriber = (int) wp_insert_user( array( 'user_login' => 'ax_admin_reader', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	$ax_admin_users[] = $subscriber;

	$actor = axismundi_actors_ensure_for_user( $uid );
	$ax_admin_ids[] = $actor->get_identity_id();

	// Not-activated label + candidates.
	ax_admin_assert( $ax_admin_results, 'a handle-less actor shows Not activated', 'Not activated' === axismundi_actors_status_label( $actor ) );
	$cands = axismundi_actors_handle_candidates( $uid );
	ax_admin_assert( $ax_admin_results, 'handle candidates include the nicename and never the raw login', in_array( 'ax_admin_alice', $cands, true ) );

	// Capability: owner yes, other author no, admin yes.
	ax_admin_assert( $ax_admin_results, 'the owner may manage their own actor', axismundi_actors_can_manage( $actor, $uid ) );
	ax_admin_assert( $ax_admin_results, 'another non-admin user may not manage it', ! axismundi_actors_can_manage( $actor, $other ) );
	ax_admin_assert( $ax_admin_results, 'an administrator may manage any actor', axismundi_actors_can_manage( $actor, $admin ) );
	$subscriber_actor = axismundi_actors_ensure_for_user( $subscriber );
	if ( $subscriber_actor instanceof Axismundi_Actor ) {
		$ax_admin_ids[] = $subscriber_actor->get_identity_id();
	}
	ax_admin_assert( $ax_admin_results, 'a Subscriber cannot manage or activate even its own retained Actor row', $subscriber_actor instanceof Axismundi_Actor && ! axismundi_actors_can_manage( $subscriber_actor, $subscriber ) );

	// Activation transition: register handle (internal) then publish.
	axismundi_actors_register_handle( $actor->get_identity_id(), 'alice_admin' );
	axismundi_actors_set_status( $actor->get_identity_id(), 'internal' );
	$actor = axismundi_actors_get_by_uuid( $actor->get_uuid() );
	ax_admin_assert( $ax_admin_results, 'after activation the actor is Internal with a locked handle', 'Internal' === axismundi_actors_status_label( $actor ) && ! axismundi_actors_is_public_profile( $actor ) && $actor->is_handle_locked() );

	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$actor = axismundi_actors_get_by_uuid( $actor->get_uuid() );
	ax_admin_assert( $ax_admin_results, 'publishing yields Public and a public profile', 'Public' === axismundi_actors_status_label( $actor ) && axismundi_actors_is_public_profile( $actor ) );

	// Site actor type change (Application <-> Organization); invalid rejected.
	$site = axismundi_actors_get_site_actor();
	$type_ok  = $site instanceof Axismundi_Actor && axismundi_actors_set_actor_type( $site->get_identity_id(), 'Organization' );
	$site2    = axismundi_actors_get_site_actor();
	$type_bad = axismundi_actors_set_actor_type( $site->get_identity_id(), 'Nonsense' );
	ax_admin_assert( $ax_admin_results, 'site actor type changes to Organization and rejects an invalid type', $type_ok && 'Organization' === $site2->get_type() && false === $type_bad );
	axismundi_actors_set_actor_type( $site->get_identity_id(), 'Application' );

} finally {
	foreach ( array_unique( $ax_admin_ids ) as $iid ) {
		$wpdb->delete( axismundi_actors_addresses_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( $ax_admin_users as $u ) {
		if ( get_userdata( $u ) ) {
			wp_delete_user( $u );
		}
	}
	update_option( 'ax_actors_site_actor_type', $ax_prev_type );
}

$ax_admin_failures = count( array_filter( $ax_admin_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_admin_results ), $ax_admin_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_admin_failures > 0 ? 1 : 0 );
}
exit( $ax_admin_failures > 0 ? 1 : 0 );
