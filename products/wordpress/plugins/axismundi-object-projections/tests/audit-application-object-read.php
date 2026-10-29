<?php
/** Application single-object reads, with rollback-only bodies and no network. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; };
$old_user = get_current_user_id(); $cookies = $_COOKIE; $network = 0;
$deny_network = static function () use ( &$network ) { ++$network; return new WP_Error( 'audit_network', 'Unexpected network' ); };
$operator = null;
foreach ( $wpdb->get_results( 'SELECT identity_id,principal_id FROM ' . axismundi_actors_managers_table() . " WHERE principal_kind='wp_user'", ARRAY_A ) as $grant ) {
	$candidate = axismundi_actors_get_by_identity( (int) $grant['identity_id'] );
	if ( $candidate && get_userdata( $grant['principal_id'] ) && ! is_wp_error( axismundi_actors_resolve_account_selection( (int) $grant['principal_id'], (int) $grant['identity_id'] ) ) ) {
		$operator = $candidate; $user = (int) $grant['principal_id']; break;
	}
}
if ( ! $operator ) { throw new RuntimeException( 'Existing authorized operator required' ); }
$sessions = WP_Session_Tokens::get_instance( $user );
$expiration = time() + 600; $token = $sessions->create( $expiration );
add_filter( 'pre_http_request', $deny_network, PHP_INT_MAX );
$wpdb->query( 'START TRANSACTION' );
try {
	$make = static function ( array $addressing ) use ( $operator ) {
		$uuid = wp_generate_uuid4(); $uri = axismundi_op_local_object_uri( $uuid );
		$row = axismundi_op_write_local_object( array_merge( array( 'id' => $uri, 'url' => $uri, 'type' => 'Note', 'attributedTo' => $operator->get_uri(), 'content' => '<p>Read fixture</p>' ), $addressing ), $uuid );
		if ( is_wp_error( $row ) ) { throw new RuntimeException( $row->get_error_message() ); }
		return axismundi_op_get_object_by_uri( $uri );
	};
	$public = $make( array( 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) ) );
	$private = $make( array( 'to' => array( 'https://recipient.example/actor' ) ) );
	wp_set_current_user( 0 );
	$assert( is_array( axismundi_op_read_application_object( $public['id'] ) ), 'Anonymous public read' );
	$assert( is_wp_error( axismundi_op_read_application_object( $private['id'] ) ), 'Anonymous private read denied' );
	$assert( is_wp_error( axismundi_op_read_application_object( PHP_INT_MAX ) ), 'Unknown ID does not fetch' );
	wp_set_current_user( $user );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user, $expiration, 'logged_in', $token );
	unset( $_COOKIE[ axismundi_actors_selection_cookie_name() ] );
	$assert( is_wp_error( axismundi_op_read_application_object( $private['id'] ) ), 'Login without Actor selection is insufficient' );
	$session = axismundi_actors_browser_session( $user );
	$unsigned = 'v1|' . $operator->get_identity_id() . '|' . $user . '|' . $session['expiration'];
	$_COOKIE[ axismundi_actors_selection_cookie_name() ] = $unsigned . '|' . hash_hmac( 'sha256', $unsigned . '|' . $session['binding'], wp_salt( 'auth' ) );
	$assert( is_array( axismundi_op_read_application_object( $private['id'] ) ), 'Validated selected author can read private body' );
	$assert( is_wp_error( axismundi_op_read_object_jsonld( $private['id'] ) ), 'Public reader remains private even for logged-in operator' );
	$assert( axismundi_op_application_actor_can_read( array( 'to' => array( array( 'id' => $operator->get_uri() ) ) ), 'https://other.example/actor', $operator ), 'Exact addressed recipient allowed' );
	$assert( ! axismundi_op_application_actor_can_read( array( 'tag' => array( array( 'type' => 'Mention', 'href' => $operator->get_uri() ) ) ), 'https://other.example/actor', $operator ), 'Mention alone grants nothing' );
	$assert( ! axismundi_op_application_actor_can_read( array( 'to' => array( 'https://unrelated.example/actor' ) ), 'https://other.example/actor', $operator ), 'Unaddressed Actor denied' );
	$remote_uri = 'https://application-read.example/' . wp_generate_uuid4();
	$remote = axismundi_op_store_remote_object( array( 'id' => $remote_uri, 'url' => $remote_uri, 'type' => 'Note', 'attributedTo' => 'https://application-read.example/author', 'to' => array( $operator->get_uri() ), 'content' => '<p>Cached<script>bad()</script></p>' ) );
	$assert( ! is_wp_error( $remote ), 'Private remote cache fixture stored' );
	$remote_index = axismundi_op_get_object_by_uri( $remote_uri );
	$read = axismundi_op_read_application_object( $remote_index['id'] );
	$assert( is_array( $read ) && ! str_contains( $read['content'], '<script' ), 'Addressed remote cache read sanitized' );
	$assert( is_wp_error( axismundi_op_read_object_jsonld( $remote_index['id'] ) ), 'Private remote remains absent from public reader' );
	$followers = axismundi_actors_get_endpoint( $operator, 'followers' );
	if ( '' === $followers ) {
		$followers = $operator->get_uri() . '/followers';
		$endpoints = axismundi_actors_get_endpoints( $operator ); $endpoints['followers'] = $followers;
		$assert( axismundi_actors_write_endpoints( $operator->get_identity_id(), $endpoints ), 'Rollback-only follower endpoint supplied' );
	}
	$peer = null;
	foreach ( $wpdb->get_col( 'SELECT id FROM ' . axismundi_actors_actors_table() . ' WHERE id <> ' . (int) $operator->get_identity_id() . ' LIMIT 30' ) as $peer_id ) {
		$peer = axismundi_actors_get_by_identity( (int) $peer_id );
		if ( $peer ) { break; }
	}
	$assert( '' !== $followers && $peer instanceof Axismundi_Actor, 'Follower authorization fixtures available' );
	$follow_document = array( 'to' => array( $followers ) );
	axismundi_act_write_relation( 'follow', $peer->get_uri(), $operator->get_uri(), 'outbound', 'pending', null, null );
	$assert( ! axismundi_op_application_actor_can_read( $follow_document, $operator->get_uri(), $peer ), 'Pending follower denied' );
	axismundi_act_write_relation( 'follow', $peer->get_uri(), $operator->get_uri(), 'outbound', 'accepted', null, null );
	$assert( axismundi_op_application_actor_can_read( $follow_document, $operator->get_uri(), $peer ), 'Accepted addressed follower allowed' );
	$assert( ! axismundi_op_application_actor_can_read( array( 'to' => array( 'https://other.example/followers' ) ), $operator->get_uri(), $peer ), 'Unrelated collection grants nothing' );
	$_COOKIE[ axismundi_actors_selection_cookie_name() ] .= 'tampered';
	$assert( is_wp_error( axismundi_op_read_application_object( $private['id'] ) ), 'Tampered selection denied' );
	wp_set_current_user( 0 );
	$request = new WP_REST_Request( 'GET', '/axismundi/v1/objects/' . $public['id'] );
	$response = rest_do_request( $request );
	$assert( 200 === $response->get_status() && $response->get_data()['id'] === $public['canonical_uri'], 'GET resolves index ID to canonical URI' );
	$assert( str_contains( $response->get_headers()['Cache-Control'], 'no-store' ), 'Application response not shared-cacheable' );
	$tombstoned = axismundi_op_tombstone_local_object( axismundi_op_get_local_object( $public['canonical_uri'] ), $operator->get_uri() );
	$assert( true === $tombstoned, 'Public Tombstone fixture created' );
	$response = rest_do_request( $request );
	$assert( 410 === $response->get_status() && 'Tombstone' === $response->get_data()['type'] && ! isset( $response->get_data()['content'] ), 'Application Tombstone is minimal and 410' );
	$assert( 0 === $network, 'No outbound requests during any read' );
} finally {
	$wpdb->query( 'ROLLBACK' );
	remove_filter( 'pre_http_request', $deny_network, PHP_INT_MAX );
	$sessions->destroy( $token ); $_COOKIE = $cookies; wp_set_current_user( $old_user );
}
echo "PASS application-object-read {$checks}\n";
