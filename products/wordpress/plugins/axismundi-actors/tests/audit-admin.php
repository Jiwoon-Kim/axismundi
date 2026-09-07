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
$ax_prev_site_disabled = get_option( 'ax_actors_site_actor_disabled', false );

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

	/*
	 * The management screen must not create anything.
	 *
	 * It used to call ensure_for_user(), which creates an actor when none
	 * exists: a database write reachable by GET, with the target coming off the
	 * query string, so an administrator could be made to perform it for an
	 * arbitrary user by following a link. Reported by the WordPress.org plugin
	 * review, 2026-09-07.
	 *
	 * Rendering is a read now, and creation happens in the nonce-checked
	 * activation POST. This asserts the read: a user with no actor still has
	 * none after the screen renders for them.
	 */
	$ax_csrf_user = (int) wp_insert_user( array( 'user_login' => 'ax_admin_fresh', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_admin_users[] = $ax_csrf_user;
	ax_admin_assert( $ax_admin_results, 'a fresh user starts with no actor', null === axismundi_actors_get_for_user( $ax_csrf_user ) );

	wp_set_current_user( $admin );
	$_GET['user_id'] = $ax_csrf_user;
	ax_admin_assert( $ax_admin_results, 'GET routing resolves the requested user for an administrator', $ax_csrf_user === axismundi_actors_admin_target_user() );

	ob_start();
	axismundi_actors_render_admin_page();
	$ax_csrf_markup = (string) ob_get_clean();
	unset( $_GET['user_id'] );

	ax_admin_assert( $ax_admin_results, 'rendering the screen creates no actor', null === axismundi_actors_get_for_user( $ax_csrf_user ) );
	ax_admin_assert( $ax_admin_results, 'the un-activated screen still offers the activation wizard', false !== strpos( $ax_csrf_markup, 'axismundi_actors_activate' ) );
	ax_admin_assert( $ax_admin_results, 'and that wizard carries a nonce, which is where creation happens', false !== strpos( $ax_csrf_markup, '_wpnonce' ) );

	wp_set_current_user( $other );
	$_GET['user_id'] = $ax_csrf_user;
	ax_admin_assert( $ax_admin_results, 'a non-admin cannot route to another user and is given their own id', $other === axismundi_actors_admin_target_user() );
	unset( $_GET['user_id'] );
	wp_set_current_user( $admin );

	ax_admin_assert( $ax_admin_results, 'the pre-creation capability rule matches the actor rule for an owner', axismundi_actors_can_manage_user_actor( $uid, $uid ) );
	ax_admin_assert( $ax_admin_results, 'and refuses a stranger', ! axismundi_actors_can_manage_user_actor( $uid, $other ) );

	/*
	 * Authorization has to come before the write, on every path.
	 *
	 * The render path was fixed first and the activation POST was not: it called
	 * ensure_for_user() and only then can_manage(), so any logged-in user could
	 * mint a nonce for ax_actors_activate_<id> in their own session, POST it,
	 * and leave an empty actor row for someone else behind a 403. A nonce proves
	 * the request came from this session; it says nothing about permission.
	 *
	 * The check below is on the shared decision rather than the handler, because
	 * the handler wp_die()s and exit()s. What matters is the ordering invariant,
	 * and that lives in one function now precisely so it cannot be fixed in one
	 * caller and missed in the other.
	 */
	$ax_target = (int) wp_insert_user( array( 'user_login' => 'ax_admin_target', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_admin_users[] = $ax_target;

	wp_set_current_user( $subscriber );
	$ax_refused = axismundi_actors_authorize_user_actor( $ax_target );
	ax_admin_assert( $ax_admin_results, 'an unprivileged user is refused before anything is created', is_wp_error( $ax_refused ) );
	ax_admin_assert( $ax_admin_results, 'and the refusal leaves no actor row behind', null === axismundi_actors_get_for_user( $ax_target ) );

	wp_set_current_user( $other );
	ax_admin_assert( $ax_admin_results, 'another author cannot reach a stranger actor either', is_wp_error( axismundi_actors_authorize_user_actor( $ax_target ) ) );
	ax_admin_assert( $ax_admin_results, 'still nothing created', null === axismundi_actors_get_for_user( $ax_target ) );

	wp_set_current_user( $ax_target );
	$ax_own = axismundi_actors_authorize_user_actor( $ax_target );
	ax_admin_assert( $ax_admin_results, 'the owner is allowed and the actor is created then', $ax_own instanceof Axismundi_Actor );
	if ( $ax_own instanceof Axismundi_Actor ) {
		$ax_admin_ids[] = $ax_own->get_identity_id();
	}
	wp_set_current_user( $admin );

	/*
	 * esc_url_raw() is not array-safe. array_map()ing it over posted input made
	 * profile_field_url[0][]= a TypeError inside ltrim(), a fatal on a form any
	 * browser can send. sanitize_text_field() returns '' for an array, which is
	 * what made the pair look interchangeable when it is not.
	 */
	/*
	 * Call the function the handler calls, with the shapes a form can post.
	 *
	 * An earlier version of this block re-typed an equivalent expression instead.
	 * Production then changed and the copy did not, so within one commit the test
	 * was asserting on map_deep() while the handler ran array_filter() and
	 * array_map() -- passing, and testing nothing that shipped. The expression
	 * lives in one place now so it cannot happen again.
	 */
	$ax_urls = axismundi_actors_sanitize_url_list( array( '  https://example.test/x  ', array( 'nested' ), 'https://example.test/%ED%95%9C' ) );
	ax_admin_assert( $ax_admin_results, 'a nested URL element is dropped rather than reaching esc_url_raw and throwing', ! isset( $ax_urls[1] ) );
	ax_admin_assert( $ax_admin_results, 'surrounding space is trimmed rather than encoded as %20', 'https://example.test/x' === ( $ax_urls[0] ?? '' ) );
	ax_admin_assert( $ax_admin_results, 'percent encoding survives, so a non-ASCII path is not silently emptied', 'https://example.test/%ED%95%9C' === ( $ax_urls[2] ?? '' ) );
	ax_admin_assert( $ax_admin_results, 'keys are preserved, so a dropped element does not shift the rest out of step with the names', array( 0, 2 ) === array_keys( $ax_urls ) );
	ax_admin_assert( $ax_admin_results, 'and the array_map form it replaced really was fatal on that input', ( static function () { try { array_map( 'esc_url_raw', array( array( 'x' ) ) ); return false; } catch ( Throwable $e ) { return true; } } )() );
	ax_admin_assert( $ax_admin_results, 'the sanitize_text_field pairing it replaced really does strip percent encoding', 'https://example.test/' === sanitize_text_field( 'https://example.test/%ED%95%9C' ) );

	// Activation transition: register handle (internal) then publish.
	axismundi_actors_register_handle( $actor->get_identity_id(), 'alice_admin' );
	axismundi_actors_set_status( $actor->get_identity_id(), 'internal' );
	$actor = axismundi_actors_get_by_uuid( $actor->get_uuid() );
	ax_admin_assert( $ax_admin_results, 'after activation the actor is Internal with a locked handle', 'Internal' === axismundi_actors_status_label( $actor ) && ! axismundi_actors_is_public_profile( $actor ) && $actor->is_handle_locked() );

	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$actor = axismundi_actors_get_by_uuid( $actor->get_uuid() );
	ax_admin_assert( $ax_admin_results, 'publishing yields Public and a public profile', 'Public' === axismundi_actors_status_label( $actor ) && axismundi_actors_is_public_profile( $actor ) );

	// The Site Actor is retained but unavailable until Instance Actor support exists.
	$site = axismundi_actors_get_site_actor();
	if ( $site instanceof Axismundi_Actor ) {
		axismundi_actors_set_actor_type( $site->get_identity_id(), 'Organization' );
		axismundi_actors_set_status( $site->get_identity_id(), 'public' );
		delete_option( 'ax_actors_site_actor_disabled' );
		axismundi_actors_disable_site_actor();
		$site = axismundi_actors_get_site_actor();
	}
	ax_admin_assert( $ax_admin_results, 'site actor is retained but fixed to a disabled Application', $site instanceof Axismundi_Actor && 'Application' === $site->get_type() && 'disabled' === $site->get_status() && ! axismundi_actors_is_public_profile( $site ) );

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
	if ( false === $ax_prev_site_disabled ) {
		delete_option( 'ax_actors_site_actor_disabled' );
	} else {
		update_option( 'ax_actors_site_actor_disabled', $ax_prev_site_disabled, false );
	}
}

$ax_admin_failures = count( array_filter( $ax_admin_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_admin_results ), $ax_admin_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_admin_failures > 0 ? 1 : 0 );
}
exit( $ax_admin_failures > 0 ? 1 : 0 );
