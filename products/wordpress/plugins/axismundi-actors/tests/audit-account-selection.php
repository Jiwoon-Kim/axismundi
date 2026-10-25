<?php
/** S10 selection kernel and real WordPress session-token contracts. @package AxismundiActors */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';

$users = array();
$fixtures = array();
$original_user = get_current_user_id();
$cookies = $_COOKIE;
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
	if ( ! $actor instanceof Axismundi_Actor ) { throw new RuntimeException( 'Selection fixture failed.' ); }
	$fixtures[] = $actor->get_identity_id();
	return $actor;
};
$login = static function ( int $user, int $expiration ) : string {
	wp_set_current_user( $user );
	$token = WP_Session_Tokens::get_instance( $user )->create( $expiration );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user, $expiration, 'logged_in', $token );
	return $token;
};
try {
	global $wpdb;
	foreach ( array( 'author', 'author', 'administrator' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'axsel' . strtolower( wp_generate_password( 12, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => $role ) );
		$assert( ! is_wp_error( $id ), 'Temporary User created' );
		$users[] = (int) $id;
	}
	$name = 's10_' . strtolower( wp_generate_password( 10, false, false ) );
	$personal = $make( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'local_user_id' => $users[0], 'preferred_username' => $name, 'status' => 'public' ) );
	$draft = $make( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'personal_owner_user_id' => $users[0] ) );
	$organization = $make( array( 'actor_type' => 'Organization', 'actor_scope' => 'managed', 'initial_owner_user_id' => $users[1], 'preferred_username' => $name . '_org', 'status' => 'public' ) );
	wp_set_current_user( $users[0] );
	$cookie_name = axismundi_actors_selection_cookie_name();
	unset( $_COOKIE[ $cookie_name ], $_COOKIE[ LOGGED_IN_COOKIE ] );
	$error( axismundi_actors_resolve_account_selection( 0 ), 'ax_selection_user', 'Anonymous principal rejected' );
	$error( axismundi_actors_resolve_account_selection( $users[0] ), 'ax_selection_missing', 'No first-Account or legacy Person default' );
	update_user_meta( $users[0], AXISMUNDI_ACTORS_ACTING_META, $personal->get_identity_id() );
	$error( axismundi_actors_resolve_account_selection( $users[0] ), 'ax_selection_missing', 'Legacy acting preference is not a new selection' );
	$selected = axismundi_actors_resolve_account_selection( $users[0], $personal->get_identity_id(), '', true );
	$assert( is_array( $selected ) && 'explicit' === $selected['source'] && $personal->get_identity_id() === $selected['actor']->get_identity_id(), 'Explicit publication selection resolved' );
	$assert( is_array( axismundi_actors_resolve_account_selection( $users[0], $draft->get_identity_id() ) ), 'Handle-less Account data access remains available' );
	$error( axismundi_actors_resolve_account_selection( $users[0], $draft->get_identity_id(), '', true ), 'ax_selection_publication', 'Unpublishable Account does not change sender' );
	$error( axismundi_actors_resolve_account_selection( $users[0], $organization->get_identity_id() ), 'ax_selection_unavailable', 'Other User Account rejected' );
	$error( axismundi_actors_resolve_account_selection( $users[2], $personal->get_identity_id() ), 'ax_selection_unavailable', 'Administrator has no implicit Account authority' );
	$assert( true === axismundi_actors_set_default_account( $users[0], 'publishing', $personal->get_identity_id() ), 'Explicit purpose default stored' );
	$selected = axismundi_actors_resolve_account_selection( $users[0], null, 'publishing', true );
	$assert( is_array( $selected ) && 'default' === $selected['source'], 'Only allowed purpose uses explicit default' );
	$error( axismundi_actors_resolve_account_selection( $users[0], 0, 'publishing', true ), 'ax_selection_unavailable', 'Explicit zero does not use valid default' );
	$error( axismundi_actors_resolve_account_selection( $users[0], $organization->get_identity_id(), 'publishing', true ), 'ax_selection_unavailable', 'Invalid explicit Account does not use default' );
	$error( axismundi_actors_resolve_account_selection( $users[0], null, 'bad/purpose' ), 'ax_selection_purpose', 'Invalid purpose rejected' );
	$error( axismundi_actors_set_account_selection( $personal->get_identity_id() ), 'ax_selection_session', 'CLI/App Password identity is not a browser session' );
	$expiration = time() + 600;
	$token = $login( $users[0], $expiration );
	$assert( true === axismundi_actors_set_account_selection( $personal->get_identity_id() ), 'Live browser session may select Account' );
	$value = $_COOKIE[ $cookie_name ];
	$assert( ! str_contains( $value, $token ) && (int) explode( '|', $value )[3] === $expiration, 'Cookie omits raw login token and uses existing expiry' );
	$selected = axismundi_actors_resolve_account_selection( $users[0], null, '', true, true );
	$assert( is_array( $selected ) && 'session' === $selected['source'], 'Browser selection resolved' );
	$error( axismundi_actors_resolve_account_selection( $users[0] ), 'ax_selection_missing', 'Non-browser adapter ignores browser cookie' );
	$assert( true === axismundi_actors_set_account_selection( $draft->get_identity_id() ), 'Data workspace selection need not publish' );
	$error( axismundi_actors_resolve_account_selection( $users[0], null, 'publishing', true, true ), 'ax_selection_publication', 'Unpublishable session selection does not fall back to default' );
	$_COOKIE[ $cookie_name ] = $value;
	$assert( is_array( axismundi_actors_resolve_account_selection( $users[0], $draft->get_identity_id() ) ), 'Explicit selection wins over different valid cookie' );
	$_COOKIE[ $cookie_name ] = substr( $value, 0, -1 ) . ( str_ends_with( $value, '0' ) ? '1' : '0' );
	$error( axismundi_actors_resolve_account_selection( $users[0], null, 'publishing', false, true ), 'ax_selection_cookie', 'Tampered cookie blocks default fallback' );
	$assert( is_array( axismundi_actors_resolve_account_selection( $users[0], $personal->get_identity_id(), '', false, true ) ), 'Explicit API choice does not depend on unrelated cookie' );
	$_COOKIE[ $cookie_name ] = array( 'invalid' );
	$error( axismundi_actors_selection_cookie( $users[0] ), 'ax_selection_cookie', 'Array cookie rejected without type error' );
	$_COOKIE[ $cookie_name ] = $value;
	$cookie_a = $_COOKIE[ LOGGED_IN_COOKIE ];
	$login( $users[0], $expiration );
	$error( axismundi_actors_selection_cookie( $users[0] ), 'ax_selection_cookie', 'Selection cannot cross login sessions of same User' );
	$login( $users[1], $expiration );
	$error( axismundi_actors_selection_cookie( $users[1] ), 'ax_selection_cookie', 'Selection cannot cross Users' );
	wp_set_current_user( $users[0] );
	$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie_a;
	$assert( $personal->get_identity_id() === axismundi_actors_selection_cookie( $users[0] ), 'Original live session still resolves its selection' );
	$session = axismundi_actors_browser_session( $users[0] );
	foreach ( array( time() - 1, $expiration + 1 ) as $wrong_expiry ) {
		$unsigned = 'v1|' . $personal->get_identity_id() . '|' . $users[0] . '|' . $wrong_expiry;
		$_COOKIE[ $cookie_name ] = $unsigned . '|' . hash_hmac( 'sha256', $unsigned . '|' . $session['binding'], wp_salt( 'auth' ) );
		$error( axismundi_actors_selection_cookie( $users[0] ), 'ax_selection_cookie', 'Expired or extended selection expiry rejected' );
	}
	$_COOKIE[ $cookie_name ] = $value;
	$assert( ! is_wp_error( axismundi_actors_write_manager_row( $organization->get_identity_id(), $users[0], 'manager' ) ), 'Explicit Organization delegation created' );
	$assert( true === axismundi_actors_set_account_selection( $organization->get_identity_id() ), 'Delegated Account selectable' );
	$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $organization->get_identity_id(), 'principal_kind' => 'wp_user', 'principal_id' => $users[0] ) );
	$error( axismundi_actors_resolve_account_selection( $users[0], null, 'publishing', true, true ), 'ax_selection_unavailable', 'Revoked session selection stops publication without Person fallback' );
	$_COOKIE[ $cookie_name ] = $value;
	WP_Session_Tokens::get_instance( $users[0] )->destroy( $token );
	$error( axismundi_actors_selection_cookie( $users[0] ), 'ax_selection_session', 'Destroyed login session invalidates selection' );
	$login( $users[0], $expiration );
	$assert( true === axismundi_actors_set_account_selection( $personal->get_identity_id() ), 'New session can explicitly select again' );
	axismundi_actors_set_status( $personal->get_identity_id(), 'disabled' );
	$error( axismundi_actors_resolve_account_selection( $users[0], null, 'publishing', true, true ), 'ax_selection_unavailable', 'Disabled selection blocked' );
	axismundi_actors_set_status( $personal->get_identity_id(), 'tombstone' );
	$error( axismundi_actors_resolve_account_selection( $users[0], $personal->get_identity_id() ), 'ax_selection_unavailable', 'Tombstone explicit selection blocked' );
	axismundi_actors_clear_account_selection();
	$assert( null === axismundi_actors_selection_cookie( $users[0] ), 'Clear removes current request selection' );
	$error( axismundi_actors_resolve_account_selection( $users[0], null, 'publishing' ), 'ax_selection_unavailable', 'Stale explicit default is not silently replaced' );
	$options = axismundi_actors_selection_cookie_options( $expiration );
	$assert( $options['httponly'] && 'Lax' === $options['samesite'] && $expiration === $options['expires'], 'Cookie flags and expiry are explicit' );
	$assert( false !== has_action( 'clear_auth_cookie', 'axismundi_actors_clear_account_selection' ), 'WordPress logout clears selection cookie' );
	$assert( false !== has_action( 'wp_login', 'axismundi_actors_clear_account_selection' ), 'A fresh login clears prior selection cookie' );
	printf( "PASS: %d Account selection checks\n", $checks );
} finally {
	$_COOKIE = $cookies;
	wp_set_current_user( $original_user );
	foreach ( array_reverse( $fixtures ) as $id ) {
		foreach ( array( axismundi_actors_addresses_table(), axismundi_actors_managers_table(), axismundi_actors_signing_keys_table(), axismundi_actors_actors_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $id ) );
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	foreach ( $users as $user ) { wp_delete_user( $user ); }
}
