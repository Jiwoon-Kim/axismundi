<?php
/** Run with wp eval-file in a disposable development site. @package AxismundiJmap */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-jmap.php';

use function Axismundi\Jmap\account_actors;
use function Axismundi\Jmap\resolve_account_actor;

if ( ! function_exists( 'axismundi_actors_create_managed_actor' ) ) {
	throw new RuntimeException( 'Actors must be active.' );
}

$users = array();
$managed = null;
$checks = 0;
$assert = static function ( bool $condition, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
};

try {
	foreach ( array( 'author', 'administrator' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'axjmap' . strtolower( wp_generate_password( 12, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => $role ) );
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}
		$users[] = (int) $id;
	}
	$person = axismundi_actors_ensure_for_user( $users[0] );
	if ( ! $person instanceof Axismundi_Actor ) {
		throw new RuntimeException( 'Person fixture failed.' );
	}
	$personal_id = 'a' . $person->get_identity_id();
	$assert( null !== resolve_account_actor( $personal_id, $users[0] ), 'Own Person is an Account' );
	$assert( null === resolve_account_actor( $personal_id, $users[1] ), 'Admin cannot read another personal Account' );
	$managed = axismundi_actors_create_managed_actor( array( 'owner_user_id' => $users[0], 'actor_type' => 'Organization', 'preferred_username' => 'axjmap' . strtolower( wp_generate_password( 10, false, false ) ), 'status' => 'internal' ) );
	if ( is_wp_error( $managed ) ) {
		throw new RuntimeException( $managed->get_error_message() );
	}
	$account_id = 'a' . $managed->get_identity_id();
	$assert( null !== resolve_account_actor( $account_id, $users[0] ), 'Owner has managed Account access' );
	$assert( null === resolve_account_actor( $account_id, $users[1] ), 'No implicit admin override' );
	$assert( isset( account_actors( $users[0] )[ $personal_id ], account_actors( $users[0] )[ $account_id ] ), 'Lists personal and managed Accounts' );
	$result = axismundi_actors_add_manager( $managed->get_identity_id(), $users[1], 'editor' );
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_message() );
	}
	$assert( null !== resolve_account_actor( $account_id, $users[1] ), 'Delegated editor can resolve Account identity' );
	$result = axismundi_actors_remove_manager( $managed->get_identity_id(), $users[1] );
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_message() );
	}
	$assert( null === resolve_account_actor( $account_id, $users[1] ), 'Revocation takes effect immediately' );
	foreach ( array( 'a0', 'a01', 'a-1', $account_id . 'x', 'a99999999999999999999999999999999' ) as $invalid ) {
		$assert( null === resolve_account_actor( $invalid, $users[0] ), 'Malformed Account ID rejected' );
	}
	$assert( null === resolve_account_actor( $account_id, 0 ) && array() === account_actors( 0 ), 'Anonymous caller rejected' );
	printf( "PASS: %d Account checks\n", $checks );
} finally {
	// Remove only identities created by this fixture, then the temporary WP users.
	if ( $managed instanceof Axismundi_Actor ) {
		global $wpdb;
		$id = $managed->get_identity_id();
		$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $id ), array( '%d' ) );
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $id ), array( '%d' ) );
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ), array( '%d' ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) {
		wp_delete_user( $id );
	}
}
