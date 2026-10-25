<?php
/** S10-C2 selection checks for reviewer execution. @package AxismundiActivities */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
$original_user = get_current_user_id();
$cookies = $_COOKIE;
$post_data = $_POST;
$header = $_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] ?? null;
$users = array();
$fixtures = array();
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $label ); }
};
$error = static function ( $result, string $code ) use ( $assert ) : void {
	$assert( is_wp_error( $result ) && $code === $result->get_error_code(), $code );
};
add_filter( 'axismundi_actors_account_selection_enabled', '__return_true' );
add_filter( 'axismundi_actors_account_selection_adapter_available', '__return_true' );
try {
	global $wpdb;
	$user = wp_insert_user( array( 'user_login' => 'axpubselection' . strtolower( wp_generate_password( 10, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$assert( ! is_wp_error( $user ), 'Temporary User created' );
	$users[] = (int) $user;
	wp_set_current_user( $user );
	foreach ( array( 'A', 'B', 'Private' ) as $name ) {
		$args = array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'personal_owner_user_id' => $user, 'initial_owner_user_id' => $user, 'display_name' => $name );
		if ( 'Private' !== $name ) { $args['preferred_username'] = 'axpub' . strtolower( wp_generate_password( 12, false, false ) ); }
		$actor = axismundi_actors_create_local( $args );
		$assert( $actor instanceof Axismundi_Actor, 'Account fixture created' );
		$fixtures[] = $actor->get_identity_id();
		if ( 'Private' !== $name ) {
			$assert( ! is_wp_error( axismundi_actors_set_status( $actor->get_identity_id(), 'public' ) ), 'Publishing Account activated' );
			$assert( axismundi_actors_can_act_as( axismundi_actors_get_by_identity( $actor->get_identity_id() ), $user ), 'Publishing fixture has live authority' );
		}
	}
	$expiration = time() + 600;
	$token = WP_Session_Tokens::get_instance( $user )->create( $expiration );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user, $expiration, 'logged_in', $token );
	unset( $_COOKIE[ axismundi_actors_selection_cookie_name() ], $_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] );
	$_POST = array();
	$post = new WP_Post( (object) array( 'ID' => 0, 'post_author' => $user, 'post_type' => 'post', 'post_status' => 'draft' ) );
	$error( axismundi_act_c2s_resolve_actor( $user, 0 ), 'ax_selection_unavailable' );
	$error( axismundi_act_request_publish_actor( $post, $user ), 'ax_selection_missing' );
	$assert( true === axismundi_actors_set_account_selection( $fixtures[0] ), 'Select A' );
	$error( axismundi_act_request_publish_actor( $post, $user ), 'ax_act_selection_expected' );
	$_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] = (string) $fixtures[0];
	$assert( $fixtures[0] === axismundi_act_request_publish_actor( $post, $user )->get_identity_id(), 'Expected A matches selected A' );
	$assert( true === axismundi_actors_set_account_selection( $fixtures[1] ), 'Select B in another tab' );
	$error( axismundi_act_request_publish_actor( $post, $user ), 'ax_act_selection_changed' );
	$_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] = (string) $fixtures[1];
	$assert( $fixtures[1] === axismundi_act_request_publish_actor( $post, $user )->get_identity_id(), 'Reloaded editor may publish as B' );
	unset( $_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] );
	$_POST['ax_publish_account'] = (string) $fixtures[1];
	$error( axismundi_act_request_publish_actor( $post, $user ), 'ax_act_selection_nonce' );
	$_POST['ax_publish_account_nonce'] = wp_create_nonce( 'ax_publish_account_0_' . $fixtures[1] );
	$assert( $fixtures[1] === axismundi_act_request_publish_actor( $post, $user )->get_identity_id(), 'Classic editor nonce pins B' );
	$assert( true === axismundi_actors_set_account_selection( $fixtures[2] ), 'Private Account selectable for data' );
	$error( axismundi_act_request_publish_actor( $post, $user ), 'ax_selection_publication' );
	unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	$error( axismundi_act_request_publish_actor( $post, $user ), 'ax_selection_missing' );
	$assert( true === axismundi_actors_set_default_account( $user, 'publish', $fixtures[0] ), 'Explicit publish default saved' );
	$assert( $fixtures[0] === axismundi_act_request_publish_actor( $post, $user )->get_identity_id(), 'Non-browser uses explicit publish default, not cookie' );
	$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $fixtures[0], 'principal_kind' => 'wp_user', 'principal_id' => $user ) );
	$error( axismundi_act_request_publish_actor( $post, $user ), 'ax_selection_unavailable' );
	printf( "PASS: %d publication selection checks\n", $checks );
} finally {
	remove_filter( 'axismundi_actors_account_selection_enabled', '__return_true' );
	remove_filter( 'axismundi_actors_account_selection_adapter_available', '__return_true' );
	$_COOKIE = $cookies;
	$_POST = $post_data;
	if ( null === $header ) { unset( $_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] ); }
	else { $_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] = $header; }
	wp_set_current_user( $original_user );
	foreach ( array_reverse( $fixtures ) as $id ) {
		foreach ( array( axismundi_actors_addresses_table(), axismundi_actors_managers_table(), axismundi_actors_signing_keys_table(), axismundi_actors_actors_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $id ) );
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	foreach ( $users as $user ) { wp_delete_user( $user ); }
}
