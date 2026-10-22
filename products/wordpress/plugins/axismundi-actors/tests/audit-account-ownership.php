<?php
/** Development-only migration and ownership audit: wp eval-file. @package AxismundiActors */

defined( 'ABSPATH' ) || exit( 1 );

$users = array();
$fixtures = array();
$checks = 0;
$fail_migration = null;
$assert = static function ( bool $condition, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
};

try {
	global $wpdb;
	$assert( '33.0' === get_option( 'ax_actors_db_version' ) && axismundi_actors_account_access_ready(), 'Verified schema and migration complete' );
	foreach ( array( 'author', 'author' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'axown' . strtolower( wp_generate_password( 12, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => $role ) );
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}
		$users[] = (int) $id;
	}
	$legacy = axismundi_actors_ensure_for_user( $users[0] );
	if ( ! $legacy instanceof Axismundi_Actor ) {
		throw new RuntimeException( 'Legacy fixture failed.' );
	}
	$fixtures[] = $legacy->get_identity_id();
	$legacy_id = $legacy->get_identity_id();
	$legacy_uri = $legacy->get_uri();
	$legacy_uuid = $legacy->get_uuid();
	$assert( $users[0] === $legacy->get_local_user_id() && $users[0] === $legacy->get_personal_owner_user_id(), 'Legacy compatibility and personal attribution both present' );
	$assert( 'owner' === axismundi_actors_account_role( $legacy_id, $users[0] ), 'Creation seeds owner atomically' );
	$assert( axismundi_actors_account_is_personal( $legacy, $users[0] ), 'Personal ownership recognized' );
	$assert( is_wp_error( axismundi_actors_remove_manager( $legacy_id, $users[0] ) ), 'Legacy removal cannot detach personal ownership' );

	// Low-level storage fixture only: additional-account UI and lifecycle are the next slice.
	$second = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'personal_owner_user_id' => $users[0], 'status' => 'internal' ) );
	if ( ! $second instanceof Axismundi_Actor ) {
		throw new RuntimeException( 'Second personal fixture failed.' );
	}
	$fixtures[] = $second->get_identity_id();
	$assert( null === $second->get_local_user_id(), 'No duplicate upstream WP-user binding' );
	$assert( 2 === count( axismundi_actors_personal_accounts_for_user( $users[0] ) ), 'One User has two personal Accounts' );
	$assert( 2 === count( axismundi_actors_workspace_options( $users[0] ) ), 'Both personal Accounts are discoverable' );
	$assert( $legacy_id === axismundi_actors_get_for_user( $users[0] )->get_identity_id(), 'Legacy getter remains pinned, not first accessible Account' );
	$assert( ! axismundi_actors_managed_actor_can_manage( $legacy_id, $users[0] ) && array() === axismundi_actors_list_manageable_actors( $users[0] ), 'Legacy managed APIs do not widen to personal ACL rows' );
	$assert( ! axismundi_actors_can_act_as( $second, $users[0] ), 'Storage support does not bypass handle activation' );

	$organization = axismundi_actors_create_managed_actor( array( 'owner_user_id' => $users[0], 'actor_type' => 'Organization', 'status' => 'internal' ) );
	if ( ! $organization instanceof Axismundi_Actor ) {
		throw new RuntimeException( 'Organization fixture failed.' );
	}
	$fixtures[] = $organization->get_identity_id();
	$assert( ! axismundi_actors_account_is_personal( $organization, $users[0] ), 'Organization owner role is not personal ownership' );
	$assert( 3 === count( axismundi_actors_workspace_options( $users[0] ) ), 'Personal and organization Accounts share one access model' );
	$added = axismundi_actors_write_manager_row( $second->get_identity_id(), $users[1], 'editor' );
	$assert( ! is_wp_error( $added ) && null !== axismundi_actors_resolve_workspace( $second->get_identity_id(), $users[1] ), 'Explicit personal delegation is readable by common kernel' );
	$assert( ! axismundi_actors_account_is_personal( $second, $users[1] ), 'Delegation does not transfer personal ownership' );
	$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $second->get_identity_id(), 'user_id' => $users[1] ) );
	$assert( null === axismundi_actors_resolve_workspace( $second->get_identity_id(), $users[1] ), 'Personal delegation revocation takes effect' );

	// Simulate a pre-upgrade personal identity while keeping its canonical identifiers.
	$wpdb->update( axismundi_actors_actors_table(), array( 'personal_owner_user_id' => null ), array( 'identity_id' => $legacy_id ) );
	$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $legacy_id, 'user_id' => $users[0] ) );
	delete_option( 'ax_actors_account_access_version' );
	$assert( array() === axismundi_actors_workspace_options( $users[0] ), 'Incomplete migration fails closed' );
	$fail_migration = static function ( string $sql ) : string {
		return str_contains( $sql, 'INSERT INTO' ) && str_contains( $sql, "'owner', UTC_TIMESTAMP()" )
			? 'SELECT * FROM axismundi_test_missing_ownership_table' : $sql;
	};
	add_filter( 'query', $fail_migration );
	$prior_errors = $wpdb->suppress_errors( true );
	try {
		$failed = axismundi_actors_migrate_account_access();
	} finally {
		remove_filter( 'query', $fail_migration );
		$fail_migration = null;
		$wpdb->suppress_errors( $prior_errors );
	}
	$assert( ! $failed && ! axismundi_actors_account_access_ready(), 'SQL failure does not mark migration complete' );
	$assert( null === axismundi_actors_get_by_identity( $legacy_id )->get_personal_owner_user_id(), 'Backfill rolls back when access insert fails' );
	$assert( axismundi_actors_migrate_account_access(), 'Migration resumes successfully' );
	$after = axismundi_actors_get_by_identity( $legacy_id );
	$assert( $legacy_uri === $after->get_uri() && $legacy_uuid === $after->get_uuid() && $legacy_id === $after->get_identity_id(), 'Identity, UUID and URI preserved' );
	$assert( 'owner' === axismundi_actors_account_role( $legacy_id, $users[0] ), 'Legacy ownership backfilled' );
	$assert( axismundi_actors_migrate_account_access() && 3 === count( axismundi_actors_workspace_options( $users[0] ) ), 'Repeated migration is stable' );
	$assert( null === $organization->get_personal_owner_user_id(), 'Managed roles are never inferred as personal ownership' );
	$wpdb->update( axismundi_actors_managers_table(), array( 'role' => 'editor' ), array( 'identity_id' => $legacy_id, 'user_id' => $users[0] ) );
	delete_option( 'ax_actors_account_access_version' );
	$assert( ! axismundi_actors_migrate_account_access(), 'Conflicting personal-owner role blocks completion' );
	$stored_role = $wpdb->get_var( $wpdb->prepare( 'SELECT role FROM %i WHERE identity_id = %d AND user_id = %d', axismundi_actors_managers_table(), $legacy_id, $users[0] ) );
	$assert( 'editor' === $stored_role, 'Migration never silently promotes an existing role' );
	$wpdb->update( axismundi_actors_managers_table(), array( 'role' => 'owner' ), array( 'identity_id' => $legacy_id, 'user_id' => $users[0] ) );
	$assert( axismundi_actors_migrate_account_access(), 'Explicit conflict repair permits completion' );

	$before_count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_identities_table() );
	$fail_migration = static function ( string $sql ) : string {
		return str_contains( $sql, 'INSERT INTO' ) && str_contains( $sql, 'ax_actor_managers' )
			? 'SELECT * FROM axismundi_test_missing_access_table' : $sql;
	};
	add_filter( 'query', $fail_migration );
	$prior_errors = $wpdb->suppress_errors( true );
	try {
		$failed_account = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'personal_owner_user_id' => $users[0] ) );
	} finally {
		remove_filter( 'query', $fail_migration );
		$fail_migration = null;
		$wpdb->suppress_errors( $prior_errors );
	}
	$assert( is_wp_error( $failed_account ) && 'ax_actors_account_access' === $failed_account->get_error_code(), 'Owner write failure rejects creation' );
	$assert( $before_count === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_identities_table() ), 'Failed owner write leaves no identity behind' );
	printf( "PASS: %d ownership checks\n", $checks );
} finally {
	if ( null !== $fail_migration ) {
		remove_filter( 'query', $fail_migration );
	}
	if ( ! axismundi_actors_account_access_ready() ) {
		axismundi_actors_migrate_account_access();
	}
	// Delete only newly-created fixtures before deleting temporary users.
	foreach ( array_reverse( $fixtures ) as $id ) {
		$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $id ) );
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $id ) );
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) {
		wp_delete_user( $id );
	}
}
