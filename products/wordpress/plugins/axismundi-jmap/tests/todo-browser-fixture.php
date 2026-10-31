<?php
/** Disposable browser identities; wp eval-file ... create|cleanup [prefix]. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
use function Axismundi\Jmap\TodoPoc\{table, install, enable_account};
global $wpdb;
$mode = $args[0] ?? '';
$prefix = $args[1] ?? 'ax_todo_ui_' . strtolower( wp_generate_password( 8, false, false ) );
if ( ! preg_match( '/\Aax_todo_ui_[a-z0-9]{8}\z/D', $prefix ) ) { throw new RuntimeException( 'Invalid fixture prefix.' ); }
if ( 'create' === $mode ) {
	install();
	$before = $wpdb->get_col( 'SELECT id FROM ' . table( 'principals' ) );
	update_option( $prefix, $before, false );
	$result = array( 'prefix' => $prefix, 'users' => array() );
	foreach ( array( 'administrator', 'subscriber' ) as $role ) {
		$login = $prefix . '_' . $role;
		$password = wp_generate_password( 24, false, false );
		$user = wp_insert_user( array( 'user_login' => $login, 'user_pass' => $password, 'display_name' => $login, 'role' => $role ) );
		if ( is_wp_error( $user ) ) { throw new RuntimeException( 'Fixture user failed.' ); }
		$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'personal_owner_user_id' => $user, 'initial_owner_user_id' => $user, 'display_name' => $login ) );
		if ( ! $actor instanceof Axismundi_Actor ) { throw new RuntimeException( 'Fixture Actor failed.' ); }
		$result['users'][] = array( 'login' => $login, 'password' => $password, 'id' => $user, 'actor' => $actor->get_identity_id(), 'account' => 'a' . $actor->get_identity_id() );
	}
	wp_set_current_user( $result['users'][0]['id'] );
	foreach ( $result['users'] as $user ) { if ( true !== enable_account( $user['account'] ) ) { throw new RuntimeException( 'Enable failed.' ); } }
	update_option( $prefix, array_diff( $wpdb->get_col( 'SELECT id FROM ' . table( 'principals' ) ), $before ), false );
	echo wp_json_encode( $result );
} elseif ( 'cleanup' === $mode ) {
	$users = get_users( array( 'search' => $prefix . '_*', 'search_columns' => array( 'user_login' ) ) );
	foreach ( $users as $user ) {
		if ( ! str_starts_with( $user->user_login, $prefix . '_' ) ) { continue; }
		foreach ( axismundi_actors_personal_accounts_for_user( $user->ID ) as $actor ) {
			$id = $actor->get_identity_id();
			$wpdb->delete( table( 'accounts' ), array( 'account_id' => 'a' . $id ) );
			$wpdb->delete( table( 'principals' ), array( 'actor_id' => $id ) );
			foreach ( array( 'managers', 'signing_keys', 'keys', 'endpoints', 'addresses', 'texts', 'fetch_state', 'asset_cache', 'identity_relations' ) as $kind ) {
				$fn = 'axismundi_actors_' . $kind . '_table';
				$wpdb->delete( $fn(), array( 'identity_id' => $id ) );
			}
			$wpdb->delete( axismundi_actors_actors_table(), array( 'id' => $id ) );
		}
		wp_delete_user( $user->ID );
	}
	$created = get_option( $prefix, null );
	if ( ! is_array( $created ) && $users ) { throw new RuntimeException( 'Fixture Principal allocation record missing.' ); }
	foreach ( $created ?? array() as $id ) { $wpdb->delete( table( 'principals' ), array( 'id' => $id ) ); }
	delete_option( $prefix );
	echo 'Fixture cleaned';
} else { throw new RuntimeException( 'Use create or cleanup.' ); }
