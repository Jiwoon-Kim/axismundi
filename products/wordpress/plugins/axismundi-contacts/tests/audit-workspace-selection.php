<?php
/** S10-C1 reviewer audit. Requires Contacts, Actors and JMAP. @package AxismundiContacts */
defined( 'ABSPATH' ) || exit( 1 );
require_once dirname( __DIR__ ) . '/includes/admin.php';
require_once dirname( __DIR__, 2 ) . '/axismundi-jmap/axismundi-jmap.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$original_user = get_current_user_id();
$cookies = $_COOKIE;
$user = 0;
$fixtures = array();
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $label ); }
};
add_filter( 'axismundi_actors_account_selection_enabled', '__return_true' );
try {
	global $wpdb;
	$assert( axismundi_contacts_ready(), 'Contacts dependencies and storage are ready' );
	$created = wp_insert_user( array( 'user_login' => 'axworkspace' . strtolower( wp_generate_password( 12, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$assert( ! is_wp_error( $created ), 'Temporary User created' );
	$user = (int) $created;
	wp_set_current_user( $user );
	foreach ( array( 'A', 'B' ) as $name ) {
		$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'personal_owner_user_id' => $user, 'initial_owner_user_id' => $user, 'display_name' => 'Workspace ' . $name ) );
		$assert( $actor instanceof Axismundi_Actor, 'Account fixture created' );
		$fixtures[] = $actor->get_identity_id();
	}
	$expiration = time() + 600;
	$token = WP_Session_Tokens::get_instance( $user )->create( $expiration );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user, $expiration, 'logged_in', $token );
	unset( $_COOKIE[ axismundi_actors_selection_cookie_name() ] );
	update_user_meta( $user, AXISMUNDI_ACTORS_ACTING_META, $fixtures[0] );
	update_user_meta( $user, axismundi_actors_account_default_key( 'workspace' ), $fixtures[0] );
	$metadata = get_user_meta( $user );
	$books_before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_contacts_books_table() );
	$check_closed = static function ( string $label ) use ( $assert, $wpdb, $books_before ) : void {
		$result = axismundi_contacts_current_account_selection();
		$screen = axismundi_contacts_current_book();
		$assert( is_wp_error( $result ) && null === axismundi_contacts_current_account(), $label . ': selection fails closed' );
		$assert( null === $screen['actor'] && array() === $screen['book'] && '' !== $screen['error'], $label . ': screen retains error' );
		$assert( $books_before === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_contacts_books_table() ), $label . ': no default book created' );
	};
	$check_closed( 'Missing cookie despite legacy A' );
	$_COOKIE[ axismundi_actors_selection_cookie_name() ] = 'invalid';
	$check_closed( 'Malformed cookie' );
	$assert( true === axismundi_actors_set_workspace( $user, $fixtures[1] ), 'Select handleless Account B' );
	$assert( $fixtures[1] === axismundi_contacts_current_account()->get_identity_id(), 'Contacts reads B, not legacy A' );
	$assert( 'a' . $fixtures[1] === \Axismundi\Jmap\account_selection_view()['current'], 'Contacts and JMAP UI agree' );
	$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $fixtures[1], 'principal_kind' => 'wp_user', 'principal_id' => $user ) );
	$check_closed( 'Revoked B despite legacy A' );
	$assert( true === axismundi_actors_set_workspace( $user, 0 ), 'Clear workspace' );
	$check_closed( 'Cleared selection' );
	$assert( $metadata === get_user_meta( $user ), 'Session switching does not rewrite preferences' );
	printf( "PASS: %d Contacts workspace selection checks\n", $checks );
} finally {
	remove_filter( 'axismundi_actors_account_selection_enabled', '__return_true' );
	$_COOKIE = $cookies;
	wp_set_current_user( $original_user );
	foreach ( array_reverse( $fixtures ) as $id ) {
		foreach ( array( axismundi_actors_addresses_table(), axismundi_actors_managers_table(), axismundi_actors_signing_keys_table(), axismundi_actors_actors_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $id ) );
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	if ( $user > 0 ) { wp_delete_user( $user ); }
}
