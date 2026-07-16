<?php
/** Like, Undo, REST, block, and interaction-lease regression (dev-only). */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_react_results      = array();
$ax_react_users        = array();
$ax_react_identities   = array();
$ax_react_activity_uris = array();
$ax_react_posts        = array();
$ax_react_old_user     = get_current_user_id();
$ax_react_suffix       = strtolower( wp_generate_password( 8, false, false ) );
$ax_react_object_uri   = 'https://example.com/objects/' . $ax_react_suffix;
$ax_react_bridge_hook  = has_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound' );

function ax_react_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

function ax_react_person( string $login, array &$users, array &$identities ) : ?Axismundi_Actor {
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
	remove_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', $ax_react_bridge_hook ?: 10 );
	add_filter( 'axismundi_op_post_lifecycle_owner', static fn() : string => 'fixture' );
	axismundi_op_install();
	axismundi_act_install();
	$local = ax_react_person( 'axlike_' . $ax_react_suffix, $ax_react_users, $ax_react_identities );
	$remote_uri = 'https://example.com/users/owner-' . $ax_react_suffix;
	$remote = axismundi_actors_upsert_remote(
		array(
			'uri' => $remote_uri,
			'actor_type' => 'Person',
			'preferred_username' => 'owner_' . $ax_react_suffix,
			'display_name' => 'Remote owner',
			'profile_url' => $remote_uri,
			'endpoints' => array( 'inbox' => $remote_uri . '/inbox', 'outbox' => $remote_uri . '/outbox' ),
			'payload' => array( 'id' => $remote_uri, 'type' => 'Person', 'preferredUsername' => 'owner_' . $ax_react_suffix, 'inbox' => $remote_uri . '/inbox', 'outbox' => $remote_uri . '/outbox' ),
		)
	);
	if ( $remote instanceof Axismundi_Actor ) {
		$ax_react_identities[] = $remote->get_identity_id();
	}
	$stored = axismundi_op_remote_object_store( array( 'id' => $ax_react_object_uri, 'type' => 'Note', 'attributedTo' => $remote_uri, 'content' => 'Remote Note.' ) );
	ax_react_assert( $ax_react_results, 'fixture creates one local reaction Actor and one cached remote target', $local instanceof Axismundi_Actor && $remote instanceof Axismundi_Actor && is_array( $stored ) );

	$like = axismundi_act_like_object( $local, $ax_react_object_uri, $remote_uri );
	if ( $like instanceof Axismundi_Activity ) {
		$ax_react_activity_uris[] = $like->get_uri();
	}
	$replay = axismundi_act_like_object( $local, $ax_react_object_uri, $remote_uri );
	ax_react_assert( $ax_react_results, 'outbound Like is idempotent, addressed to the owner, and creates one interaction lease', $like instanceof Axismundi_Activity && $replay instanceof Axismundi_Activity && $like->get_uri() === $replay->get_uri() && 'outbound' === $like->get_direction() && in_array( $remote_uri, (array) $like->get_audience()['to'], true ) && 1 === axismundi_act_get_like_count( $ax_react_object_uri ) && 1 === axismundi_op_active_lease_count( $ax_react_object_uri ) );

	$undo = axismundi_act_unlike_object( $local, $ax_react_object_uri );
	if ( $undo instanceof Axismundi_Activity ) {
		$ax_react_activity_uris[] = $undo->get_uri();
	}
	ax_react_assert( $ax_react_results, 'Undo targets the Like Activity URI, not the liked object, and releases its lease', $undo instanceof Axismundi_Activity && $like instanceof Axismundi_Activity && $like->get_uri() === $undo->get_object_uri() && $local->get_uri() === $undo->get_actor_uri() && ! axismundi_act_get_like_state( $local->get_uri(), $ax_react_object_uri ) && 0 === axismundi_act_get_like_count( $ax_react_object_uri ) && 0 === axismundi_op_active_lease_count( $ax_react_object_uri ) );
	$undo_replay = axismundi_act_unlike_object( $local, $ax_react_object_uri );
	ax_react_assert( $ax_react_results, 'replayed Unlike returns the existing effective Undo without minting another transition', $undo instanceof Axismundi_Activity && $undo_replay instanceof Axismundi_Activity && $undo->get_uri() === $undo_replay->get_uri() );

	$relike = axismundi_act_like_object( $local, $ax_react_object_uri, $remote_uri );
	if ( $relike instanceof Axismundi_Activity ) {
		$ax_react_activity_uris[] = $relike->get_uri();
	}
	ax_react_assert( $ax_react_results, 'a later Like starts a distinct Activity cycle and restores state and lease', $relike instanceof Axismundi_Activity && $like instanceof Axismundi_Activity && $relike->get_uri() !== $like->get_uri() && axismundi_act_get_like_state( $local->get_uri(), $ax_react_object_uri ) && 1 === axismundi_op_active_lease_count( $ax_react_object_uri ) );

	$inbound_one = axismundi_act_record_activity( array( 'id' => $remote_uri . '/likes/1-' . $ax_react_suffix, 'type' => 'Like', 'actor' => $remote_uri, 'object' => $ax_react_object_uri ), 'inbound' );
	$inbound_two = axismundi_act_record_activity( array( 'id' => $remote_uri . '/likes/2-' . $ax_react_suffix, 'type' => 'Like', 'actor' => $remote_uri, 'object' => $ax_react_object_uri ), 'inbound' );
	foreach ( array( $inbound_one, $inbound_two ) as $inbound ) {
		if ( $inbound instanceof Axismundi_Activity ) {
			$ax_react_activity_uris[] = $inbound->get_uri();
		}
	}
	ax_react_assert( $ax_react_results, 'aggregate count and collection members are distinct by Actor even if that Actor sends multiple active Like IDs', 2 === axismundi_act_get_like_count( $ax_react_object_uri ) && 2 === count( axismundi_act_get_effective_likes( $ax_react_object_uri ) ) && in_array( $ax_react_object_uri, axismundi_act_get_liked_object_uris( $local->get_uri() ), true ) );

	wp_set_current_user( (int) $local->get_local_user_id() );
	$request = new WP_REST_Request( 'DELETE', '/axismundi/v1/likes' );
	$request->set_param( 'object_uri', $ax_react_object_uri );
	$response = axismundi_act_rest_unlike_object( $request );
	$data = $response instanceof WP_REST_Response ? $response->get_data() : array();
	if ( ! empty( $data['activity_uri'] ) ) {
		$ax_react_activity_uris[] = (string) $data['activity_uri'];
	}
	ax_react_assert( $ax_react_results, 'REST mutation returns authoritative server state and distinct count', $response instanceof WP_REST_Response && false === $data['is_liked'] && 1 === (int) $data['like_count'] );

	axismundi_act_register_like_button_block();
	$markup = do_blocks( '<!-- wp:axismundi/like-button {"objectUri":"' . esc_url_raw( $ax_react_object_uri ) . '"} /-->' );
	ax_react_assert( $ax_react_results, 'dynamic block emits Interactivity directives, canonical URI context, accessible state, and a logged-in cache bypass', str_contains( $markup, 'data-wp-interactive="axismundi/like-button"' ) && str_contains( $markup, 'data-wp-on--click="actions.toggleLike"' ) && str_contains( $markup, 'aria-pressed' ) && str_contains( str_replace( '\\/', '/', $markup ), esc_url_raw( $ax_react_object_uri ) ) && defined( 'DONOTCACHEPAGE' ) && true === DONOTCACHEPAGE );

	$post_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => (int) $local->get_local_user_id(), 'post_title' => 'Likes collection fixture', 'post_content' => 'Public Article.' ) );
	if ( is_int( $post_id ) && $post_id > 0 ) {
		$ax_react_posts[] = $post_id;
	}
	$GLOBALS['axismundi_op_loaded']                  = false;
	$GLOBALS['axismundi_op_object_transformers']     = array();
	$GLOBALS['axismundi_op_collection_transformers'] = array();
	$GLOBALS['axismundi_op_sequence']                = 0;
	$article = is_int( $post_id ) ? axismundi_op_transform_object( get_post( $post_id ) ) : null;
	$article_uri = is_array( $article ) ? (string) ( $article['id'] ?? '' ) : '';
	$private_collection_like = '' !== $article_uri ? axismundi_act_record_activity( array( 'id' => $remote_uri . '/likes/private-' . $ax_react_suffix, 'type' => 'Like', 'actor' => $remote_uri, 'object' => $article_uri, 'to' => array( $local->get_uri() ) ), 'inbound' ) : null;
	$collection_like = '' !== $article_uri ? axismundi_act_record_activity( array( 'id' => $remote_uri . '/likes/collection-' . $ax_react_suffix, 'type' => 'Like', 'actor' => $remote_uri, 'object' => $article_uri, 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) ), 'inbound' ) : null;
	foreach ( array( $private_collection_like, $collection_like ) as $collection_activity ) {
		if ( $collection_activity instanceof Axismundi_Activity ) {
			$ax_react_activity_uris[] = $collection_activity->get_uri();
		}
	}
	$collection_request = new WP_REST_Request( 'GET', '/axismundi/v1/objects/likes' );
	$collection_request->set_param( 'object', $article_uri );
	$collection_response = '' !== $article_uri ? axismundi_op_get_object_likes( $collection_request ) : null;
	$collection_data = $collection_response instanceof WP_REST_Response ? $collection_response->get_data() : array();
	$collection_contract = is_array( $article ) && isset( $article['likes'] ) && axismundi_op_object_likes_url( $article_uri ) === $article['likes'] && $collection_response instanceof WP_REST_Response && 'OrderedCollection' === ( $collection_data['type'] ?? '' ) && 1 === (int) ( $collection_data['totalItems'] ?? 0 ) && ! array_key_exists( 'orderedItems', $collection_data ) && $private_collection_like instanceof Axismundi_Activity && $collection_like instanceof Axismundi_Activity;
	if ( ! $collection_contract ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI diagnostic.
		printf( "[DEBUG] article=%s response=%s error=%s data=%s\n", wp_json_encode( $article ), is_object( $collection_response ) ? get_class( $collection_response ) : gettype( $collection_response ), is_wp_error( $collection_response ) ? $collection_response->get_error_code() . ':' . $collection_response->get_error_message() : '', wp_json_encode( $collection_data ) );
	}
	ax_react_assert( $ax_react_results, 'a public Article advertises a count-only likes collection without disclosing liker identities', $collection_contract );
} finally {
	wp_set_current_user( $ax_react_old_user );
	remove_all_filters( 'axismundi_op_post_lifecycle_owner' );
	if ( $ax_react_bridge_hook ) {
		add_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', $ax_react_bridge_hook );
	}
	foreach ( $ax_react_activity_uris as $uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB
	}
	$wpdb->delete( axismundi_op_object_leases_table(), array( 'object_uri_hash' => hash( 'sha256', $ax_react_object_uri ) ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_op_remote_objects_table(), array( 'object_uri_hash' => hash( 'sha256', $ax_react_object_uri ) ) ); // phpcs:ignore WordPress.DB
	foreach ( $ax_react_posts as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
	foreach ( array_unique( $ax_react_identities ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB
	}
	foreach ( $ax_react_users as $user_id ) {
		wp_delete_user( (int) $user_id );
	}
}

$ax_react_failures = count( array_filter( $ax_react_results, static fn( bool $passed ) : bool => ! $passed ) );
printf( "\n== %d checks, %d failed ==\n", count( $ax_react_results ), $ax_react_failures ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
exit( $ax_react_failures > 0 ? 1 : 0 );
