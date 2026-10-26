<?php
/** Gate-on immediate publication through the REST dispatcher, not browser/HTTP auth. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
global $wpdb;
// Keep wp_after_insert_post from publishing before the REST controller commits metadata.
if ( ! defined( 'REST_REQUEST' ) ) { define( 'REST_REQUEST', true ); }
if ( ! REST_REQUEST ) { throw new RuntimeException( 'Run this audit in a dedicated REST-mode process.' ); }
$original_user = get_current_user_id();
$cookies = $_COOKIE;
$post_data = $_POST;
$header = $_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] ?? null;
$initial_gate = axismundi_actors_account_selection_enabled();
$actors = $posts = $errors = array();
$user = 0;
$checks = $http = 0;
$enable = static fn() => true;
$adapter = static fn() => true;
$owner = static fn() => 'axismundi';
$network = static function () use ( &$http ) { ++$http; return new WP_Error( 'audit_http', 'No network in this audit.' ); };
$failed = static function ( WP_Error $error, WP_Post $post ) use ( &$errors ) : void { $errors[ $post->ID ] = $error; };
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $label ); }
};
$save = static function ( string $title, string $visibility = 'public' ) use ( &$posts ) : array {
	$request = new WP_REST_Request( 'POST', '/wp/v2/posts' );
	$request->set_body_params( array( 'title' => $title, 'content' => '<p>Immediate publication fixture.</p>',
		'status' => 'publish', 'meta' => array( AXISMUNDI_OP_POST_VISIBILITY_META => $visibility ) ) );
	// Production reads PHP's request header; internal REST dispatch does not populate $_SERVER.
	if ( isset( $_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] ) ) {
		$request->set_header( 'X-Axismundi-Account', $_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] );
	}
	$response = rest_do_request( $request );
	$data = $response->get_data();
	$id = (int) ( $data['id'] ?? 0 );
	if ( $id > 0 ) { $posts[] = $id; }
	return array( $response, $id, $data );
};
add_filter( 'axismundi_actors_account_selection_enabled', $enable, PHP_INT_MAX );
add_filter( 'axismundi_actors_account_selection_adapter_available', $adapter, PHP_INT_MAX );
add_filter( 'axismundi_op_post_lifecycle_owner', $owner, PHP_INT_MAX );
add_filter( 'pre_http_request', $network );
add_action( 'axismundi_act_post_create_failed', $failed, 10, 2 );
try {
	$assert( PHP_INT_MAX === has_filter( 'axismundi_actors_account_selection_enabled', $enable )
		&& PHP_INT_MAX === has_filter( 'axismundi_actors_account_selection_adapter_available', $adapter ), 'Audit owns both gate filters independently of opt-in plugins' );
	$assert( axismundi_actors_account_selection_enabled(), 'Selection gate is enabled by this audit' );
	$created = wp_insert_user( array( 'user_login' => 'aximmediate' . strtolower( wp_generate_password( 12, false, false ) ),
		'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$assert( ! is_wp_error( $created ), 'Temporary publisher created' );
	$user = (int) $created;
	wp_set_current_user( $user );
	foreach ( array( 'Person', 'Organization' ) as $type ) {
		$actor = axismundi_actors_create_local( array( 'actor_type' => $type, 'actor_scope' => 'Person' === $type ? 'user' : 'managed',
			'personal_owner_user_id' => 'Person' === $type ? $user : null, 'initial_owner_user_id' => $user,
			'preferred_username' => 'aximmediate' . strtolower( wp_generate_password( 12, false, false ) ), 'display_name' => $type ) );
		$assert( $actor instanceof Axismundi_Actor, 'Account fixture created' );
		$actors[] = $actor->get_identity_id();
		$assert( ! is_wp_error( axismundi_actors_set_status( $actor->get_identity_id(), 'public' ) ), 'Account activated' );
	}
	$expires = time() + 600;
	$token = WP_Session_Tokens::get_instance( $user )->create( $expires );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user, $expires, 'logged_in', $token );
	$_POST = array();
	$assert( true === axismundi_actors_set_account_selection( $actors[1] ), 'Select Organization A' );
	$_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] = (string) $actors[1];
	list( $response, $id, $data ) = $save( 'Selected Account immediate publication', 'followers' );
	$assert( 201 === $response->get_status() && 'publish' === get_post_status( $id ), 'REST commits the source immediately' );
	$uri = axismundi_op_post_object_uri( get_post( $id ) );
	$activities = axismundi_act_get_by_object( $uri );
	$assert( 1 === count( $activities ) && 'Create' === $activities[0]->get_type(), 'Immediate save records exactly one Create' );
	$actor = axismundi_actors_get_by_identity( $actors[1] );
	$assert( $actor->get_uri() === $activities[0]->get_actor_uri(), 'Create uses selected Organization A, not personal Account B' );
	$assert( 'outbound' === $activities[0]->get_direction() && $uri === $activities[0]->get_object_uri(), 'Create records the saved Object in outbound ledger' );
	$assert( $actor->get_uri() === axismundi_act_get_object_attribution( $uri ), 'Committed attribution matches selected Account' );
	$payload = $activities[0]->get_payload();
	$assert( array( axismundi_op_actor_followers_url( $actor ) ) === $payload['to'] && array() === $payload['cc'], 'Create observes REST metadata, not premature public defaults' );
	$assert( ! isset( $errors[ $id ] )
		&& ! isset( $data['axismundi_publication_error'] ), 'Successful publication has no federation error' );

	// The editor remains pinned to A while another tab changes the session to B.
	$assert( true === axismundi_actors_set_account_selection( $actors[0] ), 'Another tab selects Person B' );
	list( $response, $id, $data ) = $save( 'Cross-tab mismatch source' );
	$assert( 201 === $response->get_status() && 'publish' === get_post_status( $id ), 'Cross-tab mismatch still commits the WordPress source' );
	$assert( isset( $errors[ $id ] ) && 'ax_act_selection_changed' === $errors[ $id ]->get_error_code(), 'Cross-tab mismatch reports ax_act_selection_changed from lifecycle' );
	$assert( isset( $data['axismundi_publication_error'] ) && $errors[ $id ]->get_error_message() === $data['axismundi_publication_error'], 'REST response exposes the federation warning' );
	$uri = axismundi_op_post_object_uri( get_post( $id ) );
	$assert( array() === axismundi_act_get_by_object( $uri ) && '' === axismundi_act_get_object_attribution( $uri ), 'Mismatch creates no Activity or attribution under either Account' );

	$assert( true === axismundi_actors_set_default_account( $user, 'publish', $actors[0] ), 'Stored publish default exists to expose accidental fallback' );
	axismundi_actors_clear_account_selection();
	list( $response, $id, $data ) = $save( 'Missing selection source' );
	$assert( 201 === $response->get_status() && 'publish' === get_post_status( $id ), 'Missing selection still commits the WordPress source' );
	$assert( isset( $errors[ $id ] ) && 'ax_selection_missing' === $errors[ $id ]->get_error_code(), 'Missing browser selection fails closed despite saved default' );
	$assert( isset( $data['axismundi_publication_error'] ) && $errors[ $id ]->get_error_message() === $data['axismundi_publication_error'], 'Missing selection warning reaches REST response' );
	$uri = axismundi_op_post_object_uri( get_post( $id ) );
	$assert( array() === axismundi_act_get_by_object( $uri ) && '' === axismundi_act_get_object_attribution( $uri ), 'Missing selection never falls back to personal or default Account' );
	$assert( 0 === $http, 'Immediate publication performs no network requests' );
} finally {
	remove_action( 'axismundi_act_post_create_failed', $failed, 10 );
	foreach ( $posts as $id ) {
		$post = get_post( $id );
		if ( $post instanceof WP_Post ) {
			$uri = axismundi_op_post_object_uri( $post );
			$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) );
			wp_delete_post( $id, true );
			$wpdb->delete( axismundi_op_objects_table(), array( 'source_owner' => 'wordpress-core', 'source_kind' => 'post', 'source_ref' => $id ) );
		}
		delete_transient( 'ax_act_publish_error_' . $user . '_' . $id );
	}
	foreach ( array_reverse( $actors ) as $id ) {
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_addresses_table(), axismundi_actors_signing_keys_table(), axismundi_actors_endpoints_table(), axismundi_actors_actors_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $id ) );
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	wp_set_current_user( $original_user );
	if ( $user > 0 ) { wp_delete_user( $user ); }
	$_COOKIE = $cookies;
	$_POST = $post_data;
	if ( null === $header ) { unset( $_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] ); } else { $_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] = $header; }
	remove_filter( 'pre_http_request', $network );
	remove_filter( 'axismundi_op_post_lifecycle_owner', $owner, PHP_INT_MAX );
	remove_filter( 'axismundi_actors_account_selection_enabled', $enable, PHP_INT_MAX );
	remove_filter( 'axismundi_actors_account_selection_adapter_available', $adapter, PHP_INT_MAX );
}
$assert( $initial_gate === axismundi_actors_account_selection_enabled(), 'Original gate restored after cleanup' );
printf( "PASS: %d immediate publication checks (initial gate: %s, restored)\n", $checks, $initial_gate ? 'on' : 'off' );
