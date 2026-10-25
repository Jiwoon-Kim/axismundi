<?php
/** S2 deletion, key disposal and handle reuse contracts. Run with wp eval-file. @package AxismundiActors */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';

$users = array();
$ids = array();
$orphan_key = 0;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $ok ) {
		throw new RuntimeException( $label );
	}
};
$make = static function ( array $args ) use ( &$ids ) : Axismundi_Actor {
	$actor = axismundi_actors_create_local( $args );
	if ( ! $actor instanceof Axismundi_Actor ) {
		throw new RuntimeException( 'Actor fixture creation failed.' );
	}
	$ids[] = $actor->get_identity_id();
	return $actor;
};

try {
	global $wpdb;
	$assert( AXISMUNDI_ACTORS_DB_VERSION === get_option( 'ax_actors_db_version' ), 'Deletion migration completed' );
	$handle = 's2_' . strtolower( wp_generate_password( 12, false, false ) );
	foreach ( array( 'owner', 'survivor' ) as $suffix ) {
		$user = wp_insert_user( array( 'user_login' => $handle . $suffix, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
		$assert( ! is_wp_error( $user ), 'Temporary User created' );
		$users[] = (int) $user;
	}
	$first = $make( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'local_user_id' => $users[0], 'preferred_username' => $handle, 'status' => 'public' ) );
	$second = $make( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'personal_owner_user_id' => $users[0], 'preferred_username' => $handle . '_two', 'status' => 'public' ) );
	$unscoped = $make( array( 'actor_type' => 'Person', 'personal_owner_user_id' => $users[0], 'status' => 'public' ) );
	$legacy = $make( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'local_user_id' => $users[0], 'status' => 'public' ) );
	$wpdb->update( axismundi_actors_actors_table(), array( 'personal_owner_user_id' => null ), array( 'identity_id' => $legacy->get_identity_id() ) );
	$organization = $make( array( 'actor_type' => 'Organization', 'actor_scope' => 'managed', 'initial_owner_user_id' => $users[0], 'status' => 'public' ) );
	$assert( null === $second->get_local_user_id(), 'Personal Account without legacy binding is covered' );
	$assert( 'owner' === axismundi_actors_account_role( $organization->get_identity_id(), $users[0] ) && ! axismundi_actors_account_is_personal( $organization, $users[0] ), 'Operating owner is not personal attribution' );
	$assert( null !== axismundi_actors_signing_key_snapshot( $first ), 'Live Actor can sign before deletion' );
	axismundi_actors_record_handle_address( $first->get_identity_id(), $handle );
	$assert( axismundi_actors_record_local_acct_address( $first->get_identity_id(), $handle . '@example.test' ), 'Local acct claim recorded' );
	$assert( true === axismundi_actors_reserve_former_handle( $first->get_identity_id(), $handle . '_old' ), 'Former handle claim recorded' );
	$assert( $handle !== axismundi_actors_unique_local_handle( $handle ), 'Live handle remains unique' );
	$public = $wpdb->get_var( $wpdb->prepare( 'SELECT public_key_pem FROM %i WHERE identity_id = %d', axismundi_actors_signing_keys_table(), $first->get_identity_id() ) );

	// A failure in either cleanup stage must not publish a partial state transition.
	foreach ( array( 'secret_box = NULL', 'DELETE FROM', 'COMMIT' ) as $needle ) {
		$fail = static function ( string $sql ) use ( $needle ) : string {
			return ( 'COMMIT' === $needle && 'COMMIT' === $sql ) || ( str_contains( $sql, $needle ) && ( str_contains( $sql, 'ax_actor_signing_keys' ) || str_contains( $sql, 'ax_actor_addresses' ) ) )
				? 'SELECT * FROM ax_s2_missing_table' : $sql;
		};
		$prior = $wpdb->suppress_errors( true );
		add_filter( 'query', $fail );
		try {
			$done = axismundi_actors_set_status( $first->get_identity_id(), 'tombstone' );
		} finally {
			remove_filter( 'query', $fail );
			$wpdb->suppress_errors( $prior );
		}
		$assert( ! $done && 'public' === axismundi_actors_get_by_identity( $first->get_identity_id() )->get_status(), 'Cleanup failure rolls back status: ' . $needle );
		$assert( null !== axismundi_actors_signing_key_snapshot( $first ), 'Cleanup failure preserves signing key' );
		$assert( $first->get_identity_id() === axismundi_actors_handle_owner( $handle ), 'Cleanup failure preserves handle claims' );
	}

	$assert( wp_delete_user( $users[0] ), 'WordPress User deleted' );
	foreach ( array( $first, $second, $unscoped, $legacy ) as $actor ) {
		$after = axismundi_actors_get_by_identity( $actor->get_identity_id() );
		$assert( $after && 'tombstone' === $after->get_status() && $actor->get_uri() === $after->get_uri(), 'Every personal or legacy Person keeps its deleted identity' );
		$assert( null === axismundi_actors_signing_key_snapshot( $after ), 'Deleted Actor cannot sign' );
		$key = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE identity_id = %d', axismundi_actors_signing_keys_table(), $actor->get_identity_id() ), ARRAY_A );
		$assert( is_array( $key ) && null === $key['secret_box'] && ! empty( $key['retired_at'] ) && ! empty( $key['public_key_pem'] ), 'Secret destroyed; public history retained' );
		$assert( array() === axismundi_actors_get_addresses( $actor->get_identity_id() ), 'Deleted Actor releases local address claims' );
		$assert( null === $wpdb->get_var( $wpdb->prepare( 'SELECT local_handle_key FROM %i WHERE identity_id = %d', axismundi_actors_actors_table(), $actor->get_identity_id() ) ), 'Deleted Actor releases UNIQUE handle value' );
	}
	$assert( 'public' === axismundi_actors_get_by_identity( $organization->get_identity_id() )->get_status() && null !== axismundi_actors_signing_key_snapshot( $organization ), 'Managed Organization is not deleted with an operator' );
	$assert( $public === $wpdb->get_var( $wpdb->prepare( 'SELECT public_key_pem FROM %i WHERE identity_id = %d', axismundi_actors_signing_keys_table(), $first->get_identity_id() ) ), 'Original public key is unchanged' );
	$assert( null === axismundi_actors_get_by_handle( $handle ) && 0 === axismundi_actors_handle_owner( $handle ) && $handle === axismundi_actors_unique_local_handle( $handle ), 'Dead handle is free without a suffix' );
	$assert( 0 === axismundi_actors_handle_owner( $handle . '_old' ), 'Former handle reservation released too' );
	$assert( ! axismundi_actors_set_status( $first->get_identity_id(), 'public' ), 'Deleted identity cannot be resurrected' );
	$assert( axismundi_actors_set_status( $first->get_identity_id(), 'tombstone' ), 'Repeated tombstone is idempotent' );
	$replacement = $make( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'personal_owner_user_id' => $users[1], 'preferred_username' => $handle, 'status' => 'public' ) );
	axismundi_actors_record_handle_address( $replacement->get_identity_id(), $handle );
	$assert( $replacement->get_uuid() !== $first->get_uuid() && $replacement->get_uri() !== $first->get_uri(), 'Reused handle never reuses identity' );
	$assert( $replacement->get_identity_id() === axismundi_actors_get_by_handle( $handle )->get_identity_id(), 'Handle resolves only to replacement Actor' );
	$assert( $replacement->get_identity_id() === axismundi_actors_handle_owner( $handle ), 'Replacement owns address ledger claim' );
	$assert( axismundi_actors_record_local_acct_address( $replacement->get_identity_id(), $handle . '@example.test' ), 'Replacement can claim the released acct address' );
	$assert( is_wp_error( axismundi_actors_register_handle( $legacy->get_identity_id(), $handle . '_dead' ) ), 'Handle-less tombstone cannot reclaim a handle' );
	$assert( ! axismundi_actors_record_local_acct_address( $first->get_identity_id(), $handle . '@example.test' ), 'Deleted Actor cannot reclaim an acct address' );
	$assert( axismundi_actors_is_reserved_handle( 'admin' ), 'System reserved handles are unchanged' );

	// Emulate pre-S2 data without bypassing the production migration under test.
	$old = $make( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'preferred_username' => $handle . '_prior' ) );
	axismundi_actors_record_handle_address( $old->get_identity_id(), $handle . '_prior' );
	$wpdb->update( axismundi_actors_identities_table(), array( 'status' => 'tombstone' ), array( 'id' => $old->get_identity_id() ) );
	$assert( 1 === $wpdb->query( $wpdb->prepare(
		'INSERT INTO %i (identity_id, public_key_pem, secret_box, fingerprint, created_at) SELECT identity_id, public_key_pem, secret_box, fingerprint, created_at FROM %i WHERE identity_id = %d LIMIT 1',
		axismundi_actors_signing_keys_table(), axismundi_actors_signing_keys_table(), $replacement->get_identity_id()
	) ), 'Orphan key fixture copied' );
	$orphan_key = (int) $wpdb->insert_id;
	$wpdb->update( axismundi_actors_signing_keys_table(), array( 'identity_id' => 0 ), array( 'id' => $orphan_key ) );
	$fail = static function ( string $sql ) : string {
		return str_starts_with( $sql, 'DELETE ad FROM' ) && str_contains( $sql, 'ax_actor_addresses' ) ? 'SELECT * FROM ax_s2_missing_table' : $sql;
	};
	$prior = $wpdb->suppress_errors( true );
	add_filter( 'query', $fail );
	try {
		$done = axismundi_actors_migrate_deletion_cleanup();
	} finally {
		remove_filter( 'query', $fail );
		$wpdb->suppress_errors( $prior );
	}
	$assert( ! $done && null !== $wpdb->get_var( $wpdb->prepare( 'SELECT secret_box FROM %i WHERE id = %d', axismundi_actors_signing_keys_table(), $orphan_key ) ), 'Migration failure rolls back orphan secret disposal' );
	$assert( $handle . '_prior' === $wpdb->get_var( $wpdb->prepare( 'SELECT local_handle_key FROM %i WHERE identity_id = %d', axismundi_actors_actors_table(), $old->get_identity_id() ) ), 'Migration failure rolls back handle release' );
	$assert( axismundi_actors_migrate_deletion_cleanup() && axismundi_actors_migrate_deletion_cleanup(), 'Deletion migration replays safely' );
	$key = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', axismundi_actors_signing_keys_table(), $orphan_key ), ARRAY_A );
	$assert( null === $key['secret_box'] && ! empty( $key['retired_at'] ) && ! empty( $key['public_key_pem'] ), 'Orphan secret disposed without deleting history' );
	$assert( null === axismundi_actors_get_by_handle( $handle . '_prior' ) && 0 === axismundi_actors_handle_owner( $handle . '_prior' ), 'Migration releases historical tombstone handles' );
	$assert( null !== axismundi_actors_signing_key_snapshot( $replacement ), 'Migration preserves live signing key' );
	printf( "PASS: %d user-deletion checks\n", $checks );
} finally {
	foreach ( array_reverse( $ids ) as $id ) {
		foreach ( array( axismundi_actors_addresses_table(), axismundi_actors_managers_table(), axismundi_actors_signing_keys_table(), axismundi_actors_actors_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $id ) );
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	if ( $orphan_key ) {
		$wpdb->delete( axismundi_actors_signing_keys_table(), array( 'id' => $orphan_key ) );
	}
	foreach ( $users as $user ) {
		if ( get_userdata( $user ) ) {
			wp_delete_user( $user );
		}
	}
}
