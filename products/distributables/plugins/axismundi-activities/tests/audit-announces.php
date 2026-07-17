<?php
/** Announce, Undo, visibility, shares, block, and lease regression (dev-only). */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$results       = array();
$users         = array();
$identities    = array();
$activity_uris = array();
$posts         = array();
$old_user      = get_current_user_id();
$suffix        = strtolower( wp_generate_password( 8, false, false ) );
$public_uri    = 'https://example.com/objects/public-' . $suffix;
$private_uri   = 'https://example.com/objects/private-' . $suffix;
$bridge_hook   = has_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound' );

function ax_announce_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	remove_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', $bridge_hook ?: 10 );
	add_filter( 'axismundi_op_post_lifecycle_owner', static fn() : string => 'fixture' );
	axismundi_op_install();
	axismundi_act_install();

	$user_id = wp_create_user( 'axboost_' . $suffix, wp_generate_password( 24 ), 'axboost_' . $suffix . '@example.test' );
	if ( ! is_wp_error( $user_id ) ) {
		$users[] = (int) $user_id;
		$user = new WP_User( (int) $user_id );
		$user->set_role( 'contributor' );
	}
	$local = ! is_wp_error( $user_id ) ? axismundi_actors_ensure_for_user( (int) $user_id ) : null;
	if ( $local instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $local->get_identity_id(), 'axboost_' . $suffix );
		axismundi_actors_set_status( $local->get_identity_id(), 'public' );
		$local        = axismundi_actors_get_for_user( (int) $user_id );
		$identities[] = $local->get_identity_id();
	}
	$remote_uri = 'https://example.com/users/owner-' . $suffix;
	$remote     = axismundi_actors_upsert_remote(
		array(
			'uri'                => $remote_uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'owner_' . $suffix,
			'display_name'       => 'Remote owner',
			'profile_url'        => $remote_uri,
			'endpoints'          => array( 'inbox' => $remote_uri . '/inbox', 'outbox' => $remote_uri . '/outbox' ),
			'payload'            => array( 'id' => $remote_uri, 'type' => 'Person', 'preferredUsername' => 'owner_' . $suffix, 'inbox' => $remote_uri . '/inbox', 'outbox' => $remote_uri . '/outbox' ),
		)
	);
	if ( $remote instanceof Axismundi_Actor ) {
		$identities[] = $remote->get_identity_id();
	}
	$public = axismundi_op_remote_object_store( array( 'id' => $public_uri, 'type' => 'Note', 'attributedTo' => $remote_uri, 'content' => 'Public Note.', 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) ) );
	$private = axismundi_op_remote_object_store( array( 'id' => $private_uri, 'type' => 'Note', 'attributedTo' => $remote_uri, 'content' => 'Private Note.', 'to' => array( $remote_uri . '/followers' ) ) );
	ax_announce_assert( $results, 'fixture creates one local Actor and public/private cached targets', $local instanceof Axismundi_Actor && $remote instanceof Axismundi_Actor && is_array( $public ) && is_array( $private ) );

	$denied = $local instanceof Axismundi_Actor ? axismundi_act_announce_object( $local, $private_uri, $remote_uri ) : null;
	ax_announce_assert( $results, 'followers-only and unknown-visibility objects fail closed', is_wp_error( $denied ) && 'ax_act_announce_visibility' === $denied->get_error_code() );

	$announce = $local instanceof Axismundi_Actor ? axismundi_act_announce_object( $local, $public_uri, $remote_uri ) : null;
	if ( $announce instanceof Axismundi_Activity ) {
		$activity_uris[] = $announce->get_uri();
	}
	$replay = $local instanceof Axismundi_Actor ? axismundi_act_announce_object( $local, $public_uri, $remote_uri ) : null;
	$audience = $announce instanceof Axismundi_Activity ? $announce->get_audience() : array();
	ax_announce_assert( $results, 'personal Announce is idempotent, public, addresses the origin Actor, and references the object by URI', $announce instanceof Axismundi_Activity && $replay instanceof Axismundi_Activity && $announce->get_uri() === $replay->get_uri() && $public_uri === $announce->get_object_uri() && in_array( 'https://www.w3.org/ns/activitystreams#Public', (array) ( $audience['to'] ?? array() ), true ) && in_array( $remote_uri, (array) ( $audience['cc'] ?? array() ), true ) && 1 === axismundi_op_active_lease_count( $public_uri ) );

	$undo = $local instanceof Axismundi_Actor ? axismundi_act_unannounce_object( $local, $public_uri ) : null;
	if ( $undo instanceof Axismundi_Activity ) {
		$activity_uris[] = $undo->get_uri();
	}
	ax_announce_assert( $results, 'Undo targets the Announce Activity URI and preserves its public and origin-server audience', $undo instanceof Axismundi_Activity && $announce instanceof Axismundi_Activity && $announce->get_uri() === $undo->get_object_uri() && ! axismundi_act_get_announce_state( $local->get_uri(), $public_uri ) && axismundi_act_has_public_audience( $undo ) && in_array( $remote_uri, (array) ( $undo->get_audience()['cc'] ?? array() ), true ) && 0 === axismundi_op_active_lease_count( $public_uri ) );

	$again = $local instanceof Axismundi_Actor ? axismundi_act_announce_object( $local, $public_uri, $remote_uri ) : null;
	if ( $again instanceof Axismundi_Activity ) {
		$activity_uris[] = $again->get_uri();
	}
	ax_announce_assert( $results, 're-Announce after Undo starts a new immutable cycle', $again instanceof Axismundi_Activity && $announce instanceof Axismundi_Activity && $again->get_uri() !== $announce->get_uri() && 2 === axismundi_act_announce_cycle_count( $local->get_uri(), $public_uri ) );

	wp_set_current_user( (int) $local->get_local_user_id() );
	axismundi_act_register_boost_button_block();
	$markup = do_blocks( '<!-- wp:axismundi/boost-button {"objectUri":"' . esc_url_raw( $public_uri ) . '"} /-->' );
	ax_announce_assert( $results, 'Boost block exposes canonical state through the Interactivity API', str_contains( $markup, 'data-wp-interactive="axismundi/boost-button"' ) && str_contains( $markup, 'actions.toggleAnnounce' ) && str_contains( $markup, 'aria-pressed' ) );

	$post_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => (int) $local->get_local_user_id(), 'post_title' => 'Shares fixture', 'post_content' => 'Public Article.' ) );
	if ( is_int( $post_id ) && $post_id > 0 ) {
		$posts[] = $post_id;
	}
	$GLOBALS['axismundi_op_loaded']                  = false;
	$GLOBALS['axismundi_op_object_transformers']     = array();
	$GLOBALS['axismundi_op_collection_transformers'] = array();
	$GLOBALS['axismundi_op_sequence']                = 0;
	$article     = is_int( $post_id ) ? axismundi_op_transform_object( get_post( $post_id ) ) : null;
	$article_uri = is_array( $article ) ? (string) ( $article['id'] ?? '' ) : '';
	$inbound = '' !== $article_uri ? axismundi_act_record_activity( array( 'id' => $remote_uri . '/announces/' . $suffix, 'type' => 'Announce', 'actor' => $remote_uri, 'object' => $article_uri, 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ), 'cc' => array( $local->get_uri() ) ), 'inbound' ) : null;
	if ( $inbound instanceof Axismundi_Activity ) {
		$activity_uris[] = $inbound->get_uri();
	}
	$request = new WP_REST_Request( 'GET', '/axismundi/v1/objects/shares' );
	$request->set_param( 'object', $article_uri );
	$response = '' !== $article_uri ? axismundi_op_get_object_shares( $request ) : null;
	$data     = $response instanceof WP_REST_Response ? $response->get_data() : array();
	ax_announce_assert( $results, 'public objects advertise a count-only shares OrderedCollection without Actor or Activity enumeration', is_array( $article ) && isset( $article['shares'] ) && axismundi_op_object_shares_url( $article_uri ) === $article['shares'] && 'OrderedCollection' === ( $data['type'] ?? '' ) && 1 === (int) ( $data['totalItems'] ?? 0 ) && ! isset( $data['items'] ) && ! isset( $data['orderedItems'] ) );
} finally {
	wp_set_current_user( $old_user );
	remove_all_filters( 'axismundi_op_post_lifecycle_owner' );
	if ( $bridge_hook ) {
		add_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', $bridge_hook );
	}
	foreach ( $activity_uris as $uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB
	}
	foreach ( array( $public_uri, $private_uri ) as $uri ) {
		$wpdb->delete( axismundi_op_object_leases_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_op_remote_objects_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB
	}
	foreach ( $posts as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
	foreach ( array_unique( $identities ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB
	}
	foreach ( $users as $user_id ) {
		wp_delete_user( (int) $user_id );
	}
}

$failures = count( array_filter( $results, static fn( bool $passed ) : bool => ! $passed ) );
printf( "\n== %d checks, %d failed ==\n", count( $results ), $failures ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
exit( $failures > 0 ? 1 : 0 );
