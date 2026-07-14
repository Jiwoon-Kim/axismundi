<?php
/**
 * Local Follow service and UI regression fixture (dev-only).
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/relations.php';
require_once dirname( __DIR__ ) . '/includes/local-social.php';
require_once dirname( __DIR__ ) . '/includes/local-social-ui.php';

$ax_local_results       = array();
$ax_local_user_ids      = array();
$ax_local_identity_ids  = array();
$ax_local_actor_uris    = array();
$ax_local_old_user      = get_current_user_id();
$ax_local_old_get       = $_GET;
$ax_local_old_auto      = get_option( 'axismundi_activities_auto_accept_local_follows', null );
$ax_local_suffix        = strtolower( wp_generate_password( 7, false, false ) );
$GLOBALS['ax_local_http_count'] = 0;

/** Record one fixture result. */
function ax_local_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Prove local social workflows perform no HTTP. */
function ax_local_observe_http( $preempt ) {
	++$GLOBALS['ax_local_http_count'];
	return $preempt;
}

/** Create one activated public fixture Person Actor. */
function ax_local_create_person( string $login, array &$user_ids, array &$identity_ids, array &$actor_uris ) : ?Axismundi_Actor {
	$user_id = wp_create_user( $login, wp_generate_password( 24 ), $login . '@example.test' );
	if ( is_wp_error( $user_id ) ) {
		return null;
	}
	$user_ids[] = (int) $user_id;
	$user = new WP_User( (int) $user_id );
	$user->set_role( 'contributor' );
	$actor      = axismundi_actors_ensure_for_user( (int) $user_id );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return null;
	}
	$registered = axismundi_actors_register_handle( $actor->get_identity_id(), $login );
	if ( is_wp_error( $registered ) || ! axismundi_actors_set_status( $actor->get_identity_id(), 'public' ) ) {
		return null;
	}
	$actor = axismundi_actors_get_for_user( (int) $user_id );
	if ( $actor instanceof Axismundi_Actor ) {
		$identity_ids[] = $actor->get_identity_id();
		$actor_uris[]   = $actor->get_uri();
	}
	return $actor;
}

try {
	update_option( 'axismundi_activities_auto_accept_local_follows', true, false );
	add_filter( 'pre_http_request', 'ax_local_observe_http' );

	$follower = ax_local_create_person( 'axfollow_' . $ax_local_suffix, $ax_local_user_ids, $ax_local_identity_ids, $ax_local_actor_uris );
	$target   = ax_local_create_person( 'axtarget_' . $ax_local_suffix, $ax_local_user_ids, $ax_local_identity_ids, $ax_local_actor_uris );
	ax_local_assert( $ax_local_results, 'fixture creates two activated public local Person actors', $follower instanceof Axismundi_Actor && $target instanceof Axismundi_Actor );

	wp_set_current_user( (int) $follower->get_local_user_id() );
	$subscriber = new WP_User( (int) $follower->get_local_user_id() );
	$subscriber->set_role( 'subscriber' );
	wp_set_current_user( 0 );
	wp_set_current_user( (int) $follower->get_local_user_id() );
	ax_local_assert( $ax_local_results, 'Subscriber cannot use local social actions even with an existing Actor', null === axismundi_act_current_local_actor() );
	$subscriber->set_role( 'contributor' );
	wp_set_current_user( 0 );
	wp_set_current_user( (int) $follower->get_local_user_id() );
	$auto = axismundi_act_follow_local_actor( $follower, $target );
	ax_local_assert( $ax_local_results, 'NULL follower policy uses the site auto-accept default and records an accepted local edge', is_array( $auto ) && 'accepted' === $auto['state'] && 'local' === $auto['direction'] );
	ax_local_assert( $ax_local_results, 'accepted local edges derive following and followers symmetrically', in_array( $target->get_uri(), axismundi_act_get_following( $follower->get_uri() ), true ) && in_array( $follower->get_uri(), axismundi_act_get_followers( $target->get_uri() ), true ) );

	$undone = axismundi_act_unfollow_local_actor( $follower, $target );
	ax_local_assert( $ax_local_results, 'local Unfollow retains history and marks the relation undone', is_array( $undone ) && 'undone' === $undone['state'] );

	$policy = axismundi_actors_set_local_policy( $target, 'manually_approves_followers', true, (int) $target->get_local_user_id() );
	$target = axismundi_actors_get_by_uri( $target->get_uri() );
	ax_local_assert( $ax_local_results, 'Actors-owned policy API stores explicit manual approval without Activities table writes', true === $policy && $target instanceof Axismundi_Actor && true === $target->get_policy_flag( 'manually_approves_followers' ) );

	$pending  = axismundi_act_follow_local_actor( $follower, $target );
	$requests = axismundi_act_get_pending_follow_requests( $target->get_uri() );
	ax_local_assert( $ax_local_results, 'manual approval leaves one pending request visible to the target', is_array( $pending ) && 'pending' === $pending['state'] && 1 === count( $requests ) && $follower->get_uri() === $requests[0]['subject_actor_uri'] );
	$sent_requests = axismundi_act_get_pending_following_requests( $follower->get_uri() );
	ax_local_assert( $ax_local_results, 'manual approval leaves one pending sent request visible to the sender', 1 === count( $sent_requests ) && $target->get_uri() === $sent_requests[0]['object_actor_uri'] );
	wp_set_current_user( (int) $follower->get_local_user_id() );
	ob_start();
	axismundi_act_render_follows_page();
	$sent_html = (string) ob_get_clean();
	ax_local_assert( $ax_local_results, 'Follows renders pending sent requests with a nonce-protected cancel action', str_contains( $sent_html, 'Sent requests' ) && str_contains( $sent_html, 'Cancel request' ) && str_contains( $sent_html, 'return_to' ) && str_contains( $sent_html, '_wpnonce' ) );

	$wrong = axismundi_act_respond_to_local_follow( $follower, (string) $pending['initiating_activity_uri'], 'accept' );
	ax_local_assert( $ax_local_results, 'a non-target Actor cannot accept another Actor\'s request', is_wp_error( $wrong ) && 'ax_act_follow_request' === $wrong->get_error_code() );

	wp_set_current_user( (int) $target->get_local_user_id() );
	$accepted = axismundi_act_respond_to_local_follow( $target, (string) $pending['initiating_activity_uri'], 'accept' );
	ax_local_assert( $ax_local_results, 'the target Actor can accept a pending request', is_array( $accepted ) && 'accepted' === $accepted['state'] && empty( axismundi_act_get_pending_follow_requests( $target->get_uri() ) ) );

	ob_start();
	axismundi_act_render_follows_page();
	$admin_html = (string) ob_get_clean();
	ax_local_assert( $ax_local_results, 'Follows renders policy, requests, followers, and following for the current Actor only', str_contains( $admin_html, 'Require approval' ) && str_contains( $admin_html, 'Follow requests' ) && str_contains( $admin_html, 'Followers' ) && str_contains( $admin_html, $follower->get_preferred_username() ) );

	wp_set_current_user( (int) $follower->get_local_user_id() );
	$follows_url = axismundi_act_follows_admin_url();
	ax_local_assert( $ax_local_results, 'Contributor Follows uses the transport-neutral Profile submenu slug', str_contains( $follows_url, 'profile.php?page=axismundi-follows' ) );
	$GLOBALS['axismundi_actors_current_actor'] = $target;
	$profile_html = axismundi_act_render_profile_follow_control( '<article>profile</article>' );
	ax_local_assert( $ax_local_results, 'the public Actor profile control reflects accepted state as Unfollow', str_contains( $profile_html, 'Unfollow' ) && str_contains( $profile_html, 'target_uri' ) );
	$columns = axismundi_act_users_follow_column( array( 'username' => 'Username' ) );
	$cell    = axismundi_act_users_follow_column_content( '', 'ax_local_follow', (int) $target->get_local_user_id() );
	ax_local_assert( $ax_local_results, 'administrative Users rows expose nonce-protected local Follow state and actions', isset( $columns['ax_local_follow'] ) && str_contains( $cell, '_wpnonce' ) && str_contains( $cell, 'Unfollow' ) );

	$remote = axismundi_actors_upsert_remote(
		array(
			'uri'                => 'https://example.com/users/remote_' . $ax_local_suffix,
			'actor_type'         => 'Person',
			'preferred_username' => 'remote_' . $ax_local_suffix,
			'display_name'       => 'Remote fixture',
			'profile_url'        => 'https://example.com/@remote_' . $ax_local_suffix,
			'payload'            => array( 'id' => 'https://example.com/users/remote_' . $ax_local_suffix, 'type' => 'Person' ),
			'endpoints'          => array( 'inbox' => 'https://example.com/inbox/' . $ax_local_suffix, 'outbox' => 'https://example.com/outbox/' . $ax_local_suffix ),
		)
	);
	if ( $remote instanceof Axismundi_Actor ) {
		$ax_local_identity_ids[] = $remote->get_identity_id();
		$ax_local_actor_uris[]   = $remote->get_uri();
	}
	$remote_attempt = $remote instanceof Axismundi_Actor ? axismundi_act_follow_local_actor( $follower, $remote ) : null;
	ax_local_assert( $ax_local_results, 'local Follow service rejects remote Actors and performs no HTTP', is_wp_error( $remote_attempt ) && 'ax_act_local_only' === $remote_attempt->get_error_code() && 0 === $GLOBALS['ax_local_http_count'] );
} finally {
	remove_filter( 'pre_http_request', 'ax_local_observe_http' );
	wp_set_current_user( $ax_local_old_user );
	$_GET = $ax_local_old_get;
	$GLOBALS['axismundi_actors_current_actor'] = null;
	if ( null === $ax_local_old_auto ) {
		delete_option( 'axismundi_activities_auto_accept_local_follows' );
	} else {
		update_option( 'axismundi_activities_auto_accept_local_follows', $ax_local_old_auto, false );
	}
	global $wpdb;
	foreach ( array_unique( $ax_local_actor_uris ) as $actor_uri ) {
		$wpdb->delete( axismundi_act_relations_table(), array( 'subject_actor_uri_hash' => hash( 'sha256', $actor_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned cleanup.
		$wpdb->delete( axismundi_act_relations_table(), array( 'object_actor_uri_hash' => hash( 'sha256', $actor_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned cleanup.
		$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri_hash' => hash( 'sha256', $actor_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned cleanup.
	}
	foreach ( array_unique( $ax_local_identity_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table(), axismundi_actors_identity_relations_table(), axismundi_actors_asset_cache_table(), axismundi_actors_addresses_table(), axismundi_actors_texts_table() ) as $child_table ) {
			$wpdb->delete( $child_table, array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned Actor cleanup.
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned Actor cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned Actor cleanup.
	}
	foreach ( array_unique( $ax_local_user_ids ) as $user_id ) {
		wp_delete_user( $user_id );
	}
}

$ax_local_failures = count( array_filter( $ax_local_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_local_results ), $ax_local_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_local_failures > 0 ? 1 : 0 );
}
exit( $ax_local_failures > 0 ? 1 : 0 );
