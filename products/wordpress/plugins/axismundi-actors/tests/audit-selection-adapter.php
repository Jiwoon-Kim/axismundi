<?php
/** S10-B adapter audit for reviewer execution; not a browser transport test. @package AxismundiJmap */
defined( 'ABSPATH' ) || exit( 1 );
require_once dirname( __DIR__ ) . '/axismundi-actors.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

use function Axismundi\Actors\Selection\account_selection_view;
use function Axismundi\Actors\Selection\apply_account_selection;
use function Axismundi\Actors\Selection\handle_account_selection_post;
use function Axismundi\Actors\Selection\register_account_selection_ui;
use function Axismundi\Actors\Selection\render_account_selection_page;

$users = array();
$fixtures = array();
$original_user = get_current_user_id();
$cookies = $_COOKIE;
$post = $_POST;
$method = $_SERVER['REQUEST_METHOD'] ?? null;
$enabled = false;
$gate = static function () use ( &$enabled ) : bool { return $enabled; };
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $label ); }
};
$error = static function ( $value, string $code, string $label ) use ( $assert ) : void {
	$assert( is_wp_error( $value ) && $code === $value->get_error_code(), $label );
};
$make = static function ( array $args ) use ( &$fixtures ) : Axismundi_Actor {
	$actor = axismundi_actors_create_local( $args );
	if ( ! $actor instanceof Axismundi_Actor ) { throw new RuntimeException( 'Account selection fixture failed.' ); }
	$fixtures[] = $actor->get_identity_id();
	return $actor;
};
$die_handler = static function () : callable {
	return static function ( $message, $title, $args ) : void {
		throw new RuntimeException( 'http:' . (int) ( $args['response'] ?? 500 ) );
	};
};
add_filter( 'axismundi_actors_account_selection_enabled', $gate );

try {
	global $wpdb;
	foreach ( array( 'author', 'author' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'axswitch' . strtolower( wp_generate_password( 10, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => $role ) );
		$assert( ! is_wp_error( $id ), 'Temporary User created' );
		$users[] = (int) $id;
	}
	$first = $make( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'personal_owner_user_id' => $users[0], 'display_name' => 'Account A' ) );
	$second = $make( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'personal_owner_user_id' => $users[0], 'display_name' => 'Account B' ) );
	$foreign = $make( array( 'actor_type' => 'Organization', 'actor_scope' => 'managed', 'initial_owner_user_id' => $users[1], 'display_name' => 'Foreign Account' ) );
	wp_set_current_user( $users[0] );
	$expiration = time() + 600;
	$token = WP_Session_Tokens::get_instance( $users[0] )->create( $expiration );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $users[0], $expiration, 'logged_in', $token );
	unset( $_COOKIE[ axismundi_actors_selection_cookie_name() ] );
	$nonce = wp_create_nonce( 'ax_jmap_account_selection' );
	$error( apply_account_selection( 'select', 'a' . $first->get_identity_id(), $nonce ), 'ax_jmap_selection_disabled', 'UI remains review-gated' );
	update_user_meta( $users[0], AXISMUNDI_ACTORS_ACTING_META, $first->get_identity_id() );
	update_user_meta( $users[0], axismundi_actors_account_default_key( 'workspace' ), $first->get_identity_id() );
	$assert( $first->get_identity_id() === axismundi_actors_current_workspace()->get_identity_id(), 'Disabled rollout retains legacy workspace' );
	$enabled = true;
	add_filter( 'axismundi_actors_account_selection_adapter_available', '__return_false', 100 );
	$assert( ! axismundi_actors_account_selection_enabled() && ! \Axismundi\Actors\Selection\account_selection_ui_enabled(), 'Absent adapter prevents split rollout' );
	remove_filter( 'axismundi_actors_account_selection_adapter_available', '__return_false', 100 );
	$error( axismundi_actors_current_workspace_result(), 'ax_selection_missing', 'No cookie does not select legacy preference or default' );
	$_COOKIE[ axismundi_actors_selection_cookie_name() ] = 'invalid';
	$error( axismundi_actors_current_workspace_result(), 'ax_selection_cookie', 'Malformed cookie remains a workspace error' );
	$assert( null === axismundi_actors_current_workspace(), 'Typed workspace cannot fallback after cookie error' );
	unset( $_COOKIE[ axismundi_actors_selection_cookie_name() ] );
	$view = account_selection_view();
	$assert( is_array( $view ) && 2 === count( get_object_vars( $view['accounts'] ) ) && '' === $view['current'], 'JMAP projects both Accounts without selecting the first' );
	$assert( ! isset( $view['accounts']->{'a' . $first->get_identity_id()}->accountCapabilities ), 'Actor selector does not invent protocol capabilities' );
	$error( apply_account_selection( 'select', 'a' . $first->get_identity_id(), '' ), 'ax_jmap_selection_nonce', 'Missing nonce rejected' );
	$error( apply_account_selection( 'select', 'a' . $foreign->get_identity_id(), $nonce ), 'ax_jmap_selection_account', 'Other User Account rejected' );
	$error( apply_account_selection( 'select', 'a0' . $first->get_identity_id(), $nonce ), 'ax_jmap_selection_account', 'Malformed Account ID rejected without coercion' );
	$error( apply_account_selection( 'unknown', '', $nonce ), 'ax_jmap_selection_operation', 'Unknown operation rejected' );
	$assert( true === apply_account_selection( 'login_default', 'a' . $first->get_identity_id(), $nonce ), 'Explicit login default saved' );
	$assert( null === axismundi_actors_current_workspace(), 'Saving login default does not select current workspace' );
	$auth_cookie = $_COOKIE[ LOGGED_IN_COOKIE ];
	axismundi_actors_remember_login_cookie( $auth_cookie );
	wp_set_current_user( 0 );
	axismundi_actors_initialize_login_selection( '', get_userdata( $users[0] ) );
	wp_set_current_user( $users[0] );
	$assert( $first->get_identity_id() === axismundi_actors_current_workspace()->get_identity_id(), 'New login restores explicit default without ambient current User' );
	$assert( true === apply_account_selection( 'clear_login_default', '', $nonce ), 'Login preference can be removed separately' );
	$assert( $first->get_identity_id() === axismundi_actors_current_workspace()->get_identity_id(), 'Removing login default does not change active session' );
	$assert( true === apply_account_selection( 'login_default', 'a' . $first->get_identity_id(), $nonce ), 'Login preference restored explicitly' );
	$assert( true === apply_account_selection( 'clear', '', $nonce ), 'Selection may still be cleared internally' );
	$assert( null === axismundi_actors_current_workspace(), 'Login default does not repair a missing mid-session choice' );
	$metadata = get_user_meta( $users[0] );
	$assert( true === apply_account_selection( 'select', 'a' . $first->get_identity_id(), $nonce ), 'Select Account A' );
	$assert( 'a' . $first->get_identity_id() === account_selection_view()['current'], 'View reflects selection cookie' );
	$assert( true === apply_account_selection( 'select', 'a' . $second->get_identity_id(), $nonce ), 'Switch to Account B' );
	$assert( 'a' . $second->get_identity_id() === account_selection_view()['current'], 'View reflects Account B' );
	$assert( $second->get_identity_id() === axismundi_actors_current_workspace()->get_identity_id(), 'Workspace and selection UI agree on B, not legacy A' );
	$assert( true === axismundi_actors_set_workspace( $users[0], $first->get_identity_id() ), 'Workspace setter writes session selection' );
	$assert( 'a' . $first->get_identity_id() === account_selection_view()['current'], 'Workspace setter and UI share one selection' );
	$assert( true === axismundi_actors_set_workspace( $users[0], $second->get_identity_id() ), 'Workspace setter restores B' );
	$assert( $metadata === get_user_meta( $users[0] ), 'Switching writes no active usermeta or default preference' );
	ob_start();
	try { render_account_selection_page(); }
	finally { $html = ob_get_clean(); }
	$assert( str_contains( $html, 'axismundi_jmap_select_account' ) && str_contains( $html, '_wpnonce' ), 'Form uses new action and nonce' );
	$assert( ! str_contains( $html, 'axismundi_actors_set_acting_actor' ) && ! str_contains( $html, 'Foreign Account' ), 'UI contains neither legacy action nor foreign Account' );
	$assert( ! str_contains( $html, 'value="clear"' ) && str_contains( $html, 'type="radio"' ), 'UI separates single login preference from selection without a clear-selection command' );
	$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $second->get_identity_id(), 'principal_kind' => 'wp_user', 'principal_id' => $users[0] ) );
	$view = account_selection_view();
	$assert( '' === $view['current'] && is_wp_error( $view['error'] ) && ! isset( $view['accounts']->{'a' . $second->get_identity_id()} ), 'Revocation removes stale choice without selecting A' );
	$assert( is_wp_error( axismundi_actors_current_workspace_result() ) && null === axismundi_actors_current_workspace(), 'Revoked workspace cannot fallback to legacy A' );
	$error( apply_account_selection( 'select', 'a' . $second->get_identity_id(), $nonce ), 'ax_jmap_selection_account', 'Stale form cannot select revoked Account' );
	$assert( true === apply_account_selection( 'clear', '', $nonce ) && '' === account_selection_view()['current'], 'Clear selection recovers from stale cookie' );
	$assert( null === axismundi_actors_current_workspace(), 'Clearing selection leaves workspace unselected despite stored default' );
	$assert( true === apply_account_selection( 'login_default', 'a' . $first->get_identity_id(), $nonce ), 'Login default remains explicit' );
	$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $first->get_identity_id(), 'principal_kind' => 'wp_user', 'principal_id' => $users[0] ) );
	axismundi_actors_remember_login_cookie( $auth_cookie );
	axismundi_actors_initialize_login_selection( '', get_userdata( $users[0] ) );
	$assert( null === axismundi_actors_current_workspace(), 'Revoked login default cannot initialize another Account' );
	register_account_selection_ui();
	$assert( false === has_action( 'admin_bar_menu', 'axismundi_actors_acting_actor_admin_bar' ), 'Old switcher menu is detached when new UI is enabled' );
	$assert( false === has_action( 'admin_post_axismundi_actors_set_acting_actor', 'axismundi_actors_handle_set_acting_actor' ), 'Old switcher POST is detached' );
	add_filter( 'wp_die_handler', $die_handler );
	try {
		foreach ( array( 'GET' => 405, 'POST' => 400 ) as $request_method => $expected ) {
			$_SERVER['REQUEST_METHOD'] = $request_method;
			$_POST = array( 'account_id' => array( 'invalid' ) );
			$status = '';
			try { handle_account_selection_post(); }
			catch ( RuntimeException $error_response ) { $status = $error_response->getMessage(); }
			$assert( 'http:' . $expected === $status, 'HTTP adapter rejects invalid method or array arguments' );
		}
	} finally { remove_filter( 'wp_die_handler', $die_handler ); }
	WP_Session_Tokens::get_instance( $users[0] )->destroy( $token );
	$error( apply_account_selection( 'clear', '', $nonce ), 'ax_selection_session', 'Expired login cannot mutate selection' );
	$error( axismundi_actors_set_workspace( $users[0], 0 ), 'ax_selection_session', 'Workspace clear requires a live browser session' );
	printf( "PASS: %d Account selection adapter checks\n", $checks );
} finally {
	remove_filter( 'axismundi_actors_account_selection_enabled', $gate );
	remove_filter( 'axismundi_actors_account_selection_adapter_available', '__return_false', 100 );
	remove_filter( 'wp_die_handler', $die_handler );
	$_COOKIE = $cookies;
	$_POST = $post;
	if ( null === $method ) { unset( $_SERVER['REQUEST_METHOD'] ); }
	else { $_SERVER['REQUEST_METHOD'] = $method; }
	wp_set_current_user( $original_user );
	foreach ( array_reverse( $fixtures ) as $id ) {
		foreach ( array( axismundi_actors_addresses_table(), axismundi_actors_managers_table(), axismundi_actors_signing_keys_table(), axismundi_actors_actors_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $id ) );
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	foreach ( $users as $user ) { wp_delete_user( $user ); }
}
