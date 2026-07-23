<?php
/** Follow button state, REST, and render contract regression (dev-only). */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_follow_button_results     = array();
$ax_follow_button_users       = array();
$ax_follow_button_identities  = array();
$ax_follow_button_activity_uris = array();
$ax_follow_button_old_user    = get_current_user_id();
$ax_follow_button_suffix      = strtolower( wp_generate_password( 8, false, false ) );
$ax_follow_button_remote_ip  = static fn() : string => '203.0.113.91';
$ax_follow_button_remote_http = static function ( $preempt, array $args, string $url ) {
	if ( str_starts_with( $url, 'https://example.com/.well-known/webfinger' ) ) {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		if ( 'acct:visitor@example.com' === (string) ( $query['resource'] ?? '' ) ) {
			return array(
				'headers'  => array( 'content-type' => 'application/jrd+json' ),
				'body'     => wp_json_encode( array( 'subject' => 'acct:visitor@example.com', 'links' => array( array( 'rel' => 'http://ostatus.org/schema/1.0/subscribe', 'template' => 'https://example.com/authorize_interaction?uri={uri}' ) ) ) ),
				'response' => array( 'code' => 200, 'message' => 'Fixture' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}
	}
	return $preempt;
};

function ax_follow_button_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

function ax_follow_button_person( string $login, array &$users, array &$identities ) : ?Axismundi_Actor {
	$user_id = wp_create_user( $login, wp_generate_password( 24 ), $login . '@example.test' );
	if ( is_wp_error( $user_id ) ) {
		return null;
	}
	$users[] = (int) $user_id;
	$user = new WP_User( (int) $user_id );
	$user->set_role( 'contributor' );
	$actor = axismundi_actors_ensure_for_user( (int) $user_id );
	if ( ! $actor instanceof Axismundi_Actor || is_wp_error( axismundi_actors_register_handle( $actor->get_identity_id(), $login ) ) || ! axismundi_actors_set_status( $actor->get_identity_id(), 'public' ) ) {
		return null;
	}
	$actor = axismundi_actors_get_for_user( (int) $user_id );
	if ( $actor instanceof Axismundi_Actor ) {
		$identities[] = $actor->get_identity_id();
	}
	return $actor;
}

try {
	axismundi_act_install();
	update_option( 'axismundi_activities_auto_accept_local_follows', true, false );
	$follower = ax_follow_button_person( 'axbutton_' . $ax_follow_button_suffix, $ax_follow_button_users, $ax_follow_button_identities );
	$target   = ax_follow_button_person( 'axtarget_' . $ax_follow_button_suffix, $ax_follow_button_users, $ax_follow_button_identities );
	ax_follow_button_assert( $ax_follow_button_results, 'fixture creates two activated public local Person Actors', $follower instanceof Axismundi_Actor && $target instanceof Axismundi_Actor );

	if ( ! $follower instanceof Axismundi_Actor || ! $target instanceof Axismundi_Actor ) {
		throw new RuntimeException( 'Fixture Actors could not be created.' );
	}
	wp_set_current_user( (int) $follower->get_local_user_id() );
	$initial = axismundi_act_follow_button_state( $follower, $target );
	ax_follow_button_assert( $ax_follow_button_results, 'an unrelated Actor receives the Follow state', 'none' === $initial['state'] && false === $initial['follows_you'] && 'Follow' === $initial['label'] );

	$inbound = axismundi_act_follow_local_actor( $target, $follower );
	if ( is_array( $inbound ) && ! empty( $inbound['initiating_activity_uri'] ) ) {
		$ax_follow_button_activity_uris[] = (string) $inbound['initiating_activity_uri'];
	}
	$follow_back = axismundi_act_follow_button_state( $follower, $target );
	ax_follow_button_assert( $ax_follow_button_results, 'an accepted inverse edge derives Follow back without another state store', is_array( $inbound ) && 'accepted' === $inbound['state'] && 'none' === $follow_back['state'] && true === $follow_back['follows_you'] && 'Follow back' === $follow_back['label'] );

	$post_request = new WP_REST_Request( 'POST', '/axismundi/v1/follows' );
	$post_request->set_param( 'target_uri', $target->get_uri() );
	$post_response = axismundi_act_rest_follow_actor( $post_request );
	$post_data     = $post_response instanceof WP_REST_Response ? $post_response->get_data() : array();
	$following     = axismundi_act_get_relation( 'follow', $follower->get_uri(), $target->get_uri() );
	if ( is_array( $following ) && ! empty( $following['initiating_activity_uri'] ) ) {
		$ax_follow_button_activity_uris[] = (string) $following['initiating_activity_uri'];
	}
	ax_follow_button_assert( $ax_follow_button_results, 'REST Follow returns the authoritative Mutual state after local auto-accept', $post_response instanceof WP_REST_Response && 'accepted' === $post_data['state'] && true === $post_data['follows_you'] && 'Mutual' === $post_data['label'] && is_array( $following ) && 'accepted' === $following['state'] );

	$delete_request = new WP_REST_Request( 'DELETE', '/axismundi/v1/follows' );
	$delete_request->set_param( 'target_uri', $target->get_uri() );
	$delete_response = axismundi_act_rest_unfollow_actor( $delete_request );
	$delete_data     = $delete_response instanceof WP_REST_Response ? $delete_response->get_data() : array();
	ax_follow_button_assert( $ax_follow_button_results, 'REST Unfollow restores Follow back while retaining the inverse edge', $delete_response instanceof WP_REST_Response && 'none' === $delete_data['state'] && true === $delete_data['follows_you'] && 'Follow back' === $delete_data['label'] );

	axismundi_actors_set_local_policy( $target, 'manually_approves_followers', true, (int) $target->get_local_user_id() );
	$target = axismundi_actors_get_by_uri( $target->get_uri() );
	$pending_request = new WP_REST_Request( 'POST', '/axismundi/v1/follows' );
	$pending_request->set_param( 'target_uri', $target->get_uri() );
	$pending_response = axismundi_act_rest_follow_actor( $pending_request );
	$pending_data     = $pending_response instanceof WP_REST_Response ? $pending_response->get_data() : array();
	$pending_relation = axismundi_act_get_relation( 'follow', $follower->get_uri(), $target->get_uri() );
	if ( is_array( $pending_relation ) && ! empty( $pending_relation['initiating_activity_uri'] ) ) {
		$ax_follow_button_activity_uris[] = (string) $pending_relation['initiating_activity_uri'];
	}
	ax_follow_button_assert( $ax_follow_button_results, 'manual approval derives Requested and keeps Follow back information', $pending_response instanceof WP_REST_Response && 'pending' === $pending_data['state'] && true === $pending_data['follows_you'] && 'Requested' === $pending_data['label'] );

	$markup = do_blocks( '<!-- wp:axismundi/follow-button {"actorUri":"' . esc_url_raw( $target->get_uri() ) . '"} /-->' );
	ax_follow_button_assert( $ax_follow_button_results, 'the active control emits Interactivity directives, a REST nonce, and a cache bypass', str_contains( $markup, 'data-wp-interactive="axismundi/follow-button"' ) && str_contains( $markup, 'data-wp-on--click="actions.toggleFollow"' ) && str_contains( str_replace( '\\/', '/', $markup ), 'axismundi/v1/follows' ) && str_contains( $markup, 'Requested' ) && defined( 'DONOTCACHEPAGE' ) && true === DONOTCACHEPAGE );

	$self_markup = do_blocks( '<!-- wp:axismundi/follow-button {"actorUri":"' . esc_url_raw( $follower->get_uri() ) . '"} /-->' );
	ax_follow_button_assert( $ax_follow_button_results, 'a viewer never receives a Follow control for their own Actor', '' === $self_markup );

	wp_set_current_user( 0 );
	$anonymous_markup = do_blocks( '<!-- wp:axismundi/follow-button {"actorUri":"' . esc_url_raw( $target->get_uri() ) . '"} /-->' );
	ax_follow_button_assert( $ax_follow_button_results, 'anonymous viewers receive the Dialogs-native remote-follow modal plus a local login path without a mutation nonce', str_contains( $anonymous_markup, 'data-wp-interactive="axismundi/follow-button"' ) && str_contains( $anonymous_markup, '<dialog ' ) && str_contains( str_replace( '\\/', '/', $anonymous_markup ), 'axismundi/v1/remote-follow' ) && str_contains( $anonymous_markup, 'Log in or create an account on this site instead' ) && ! str_contains( $anonymous_markup, 'wp_rest' ) );

	add_filter( 'axismundi_act_remote_follow_client_ip', $ax_follow_button_remote_ip );
	add_filter( 'pre_http_request', $ax_follow_button_remote_http, 10, 3 );
	$follow_count_before = count( array_filter( axismundi_act_get_by_object( $target->get_uri() ), static fn( Axismundi_Activity $activity ) : bool => 'Follow' === $activity->get_type() ) );
	$remote_request = new WP_REST_Request( 'POST', '/axismundi/v1/remote-follow' );
	$remote_request->set_param( 'target_uri', $target->get_uri() );
	$remote_request->set_param( 'resource', '@visitor@example.com' );
	$remote_response = axismundi_act_rest_remote_follow( $remote_request );
	$remote_data     = $remote_response instanceof WP_REST_Response ? $remote_response->get_data() : array();
	$target_resource = axismundi_act_remote_follow_target_resource( $target );
	$follow_count_after = count( array_filter( axismundi_act_get_by_object( $target->get_uri() ), static fn( Axismundi_Activity $activity ) : bool => 'Follow' === $activity->get_type() ) );
	ax_follow_button_assert( $ax_follow_button_results, 'anonymous remote follow resolves the visitor server subscribe template without creating a Follow activity', $remote_response instanceof WP_REST_Response && is_string( $target_resource ) && str_contains( (string) ( $remote_data['url'] ?? '' ), rawurlencode( $target_resource ) ) && $follow_count_before === $follow_count_after );
	remove_filter( 'pre_http_request', $ax_follow_button_remote_http, 10 );
	remove_filter( 'axismundi_act_remote_follow_client_ip', $ax_follow_button_remote_ip );
} finally {
	remove_filter( 'pre_http_request', $ax_follow_button_remote_http, 10 );
	remove_filter( 'axismundi_act_remote_follow_client_ip', $ax_follow_button_remote_ip );
	delete_transient( 'ax_remote_follow_' . md5( '203.0.113.91' ) );
	wp_set_current_user( $ax_follow_button_old_user );
	foreach ( array_unique( $ax_follow_button_activity_uris ) as $uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( $ax_follow_button_identities as $identity_id ) {
		$actor = axismundi_actors_get_by_identity( (int) $identity_id );
		if ( $actor instanceof Axismundi_Actor ) {
			$wpdb->delete( axismundi_act_relations_table(), array( 'subject_actor_uri_hash' => hash( 'sha256', $actor->get_uri() ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
			$wpdb->delete( axismundi_act_relations_table(), array( 'object_actor_uri_hash' => hash( 'sha256', $actor->get_uri() ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
			$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri_hash' => hash( 'sha256', $actor->get_uri() ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( $ax_follow_button_users as $user_id ) {
		wp_delete_user( (int) $user_id );
	}
}

$ax_follow_button_failures = count( array_filter( $ax_follow_button_results, static fn( bool $passed ) : bool => ! $passed ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_follow_button_results ), $ax_follow_button_failures );
exit( $ax_follow_button_failures > 0 ? 1 : 0 );
