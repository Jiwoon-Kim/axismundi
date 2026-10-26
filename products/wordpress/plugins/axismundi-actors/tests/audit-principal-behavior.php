<?php
/** Development-only principal behavior audit: wp eval-file. @package AxismundiActors */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$actors = array();
$user_id = 0;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $label ); }
	++$checks;
};
try {
	$subject = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'managed', 'status' => 'public' ) );
	$assert( $subject instanceof Axismundi_Actor, 'Local subject created' );
	$actors[] = $subject->get_identity_id();
	$n = $subject->get_identity_id();
	$assert( ! get_userdata( $n ), 'Numeric collision fixture does not overwrite a User' );
	$assert( false !== $wpdb->insert( $wpdb->users, array( 'ID' => $n, 'user_login' => 'axprincipal' . $n, 'user_pass' => wp_hash_password( wp_generate_password() ), 'user_nicename' => 'axprincipal' . $n, 'user_email' => '', 'user_registered' => current_time( 'mysql', true ), 'display_name' => 'Principal fixture' ) ), 'Independent User sequence fixture inserted' );
	$user_id = $n;
	clean_user_cache( $n );
	$target = axismundi_actors_create_local( array( 'actor_type' => 'Group', 'actor_scope' => 'managed', 'status' => 'public' ) );
	$assert( $target instanceof Axismundi_Actor, 'Target Account created' );
	$actors[] = $target->get_identity_id();
	$id = $target->get_identity_id();
	$uri = 'https://example.com/principal-audit-' . wp_generate_uuid4();
	$remote = axismundi_actors_upsert_remote( array( 'uri' => $uri, 'actor_type' => 'Person', 'display_name' => 'Remote fixture', 'endpoints' => array( 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ), 'payload' => array( 'id' => $uri, 'type' => 'Person' ) ) );
	$assert( $remote instanceof Axismundi_Actor, 'Remote subject created without network' );
	$rid = $remote->get_identity_id();
	$actors[] = $rid;
	$assert( null !== axismundi_actors_resolve_principal( 'remote_actor', $rid ), 'Remote subject resolves' );
	$assert( '' === axismundi_actors_principal_account_role( $id, 'remote_actor', $rid ), 'Resolving a remote subject grants no role' );
	$assert( null === axismundi_actors_resolve_principal( 'local_account', $rid ), 'Remote cannot masquerade as local' );
	$assert( null === axismundi_actors_resolve_principal( 'remote_actor', $n ), 'Local cannot masquerade as remote' );
	$now = current_time( 'mysql', true );
	// Insert the non-User subject first so an unscoped role query cannot pass by row order.
	$assert( true === axismundi_actors_set_principal_role( $id, 'local_account', $n, 'manager' ), 'Local principal grant API writes the relation' );
	$assert( '' === axismundi_actors_account_role( $id, $n ), 'Actor grant does not grant same-numbered User' );
	if ( function_exists( 'axismundi_ntf_can_read_inbox' ) ) {
		$assert( ! axismundi_ntf_can_read_inbox( $id, $n ), 'Actor grant does not authenticate an inbox reader' );
		$assert( array() === axismundi_ntf_inbox_readers( $id ), 'Actor grant does not recursively fan out to User readers' );
	}
	$assert( ! is_wp_error( axismundi_actors_write_manager_row( $id, $n, 'editor' ) ), 'User relation inserted' );
	$assert( 'editor' === axismundi_actors_account_role( $id, $n ), 'User gets only User role' );
	$assert( 'manager' === axismundi_actors_principal_account_role( $id, 'local_account', $n ), 'Local Account gets only local role' );
	if ( function_exists( 'axismundi_ntf_inbox_readers' ) ) {
		$assert( array( $n ) === axismundi_ntf_inbox_readers( $id ), 'Direct User grant supplies exactly one inbox reader' );
	}
	if ( function_exists( 'axismundi_forum_actor_is_moderator' ) ) {
		$assert( axismundi_forum_actor_is_moderator( $id, $subject ), 'Explicit local Account grant supplies Actor moderation authority' );
	}
	$assert( ! axismundi_actors_principal_can_manage( $id, 'wp_user', $n, 'manager' ), 'Local manager does not elevate same-numbered User editor' );
	$assert( ! axismundi_actors_principal_can_manage( $id, 'local_account', $n, 'unknown' ), 'Unknown minimum role fails closed' );
	$assert( isset( axismundi_actors_accounts_for_principal( 'local_account', $n )[ $id ] ), 'Exact local subject lists its Account' );
	$assert( 2 === count( axismundi_actors_account_principals( $id ) ), 'Typed roster retains colliding namespaces' );
	$assert( is_wp_error( axismundi_actors_set_principal_role( $id, 'remote_actor', $rid, 'owner' ) ), 'Remote grant API remains closed' );
	$assert( true === axismundi_actors_set_principal_role( $id, 'wp_user', $n, 'owner' ), 'Direct WP owner established' );
	$deleted_owner_id = 1 + (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->users}" );
	$assert( false !== $wpdb->insert( axismundi_actors_managers_table(), array( 'identity_id' => $id, 'principal_kind' => 'wp_user', 'principal_id' => $deleted_owner_id, 'user_id' => $deleted_owner_id, 'role' => 'owner', 'created_at' => $now, 'updated_at' => $now ) ), 'Deleted User owner fixture relation inserted without creating a User' );
	$assert( '' === axismundi_actors_account_role( $id, $deleted_owner_id ), 'Deleted owner relation retains denied read verdict' );
	$assert( array() === array_values( array_filter( axismundi_actors_account_principals( $id ), static fn( array $row ) : bool => 'wp_user' === $row['principal_kind'] && $deleted_owner_id === $row['principal_id'] ) ), 'Active principal roster intentionally omits deleted User sources without deleting their rows' );
	$assert( 1 === axismundi_actors_principal_owner_count( $id, 'wp_user' ), 'Deleted User owner is not a live recovery anchor' );
	$assert( is_wp_error( axismundi_actors_set_principal_role( $id, 'wp_user', $n, 'editor' ) ), 'Deleted User owner cannot permit demoting last live owner' );
	$assert( is_wp_error( axismundi_actors_set_principal_role( $id, 'wp_user', $n, null ) ), 'Deleted User owner cannot permit removing last live owner' );
	$assert( 'owner' === $wpdb->get_var( $wpdb->prepare( 'SELECT role FROM %i WHERE identity_id = %d AND principal_kind = %s AND principal_id = %d', axismundi_actors_managers_table(), $id, 'wp_user', $deleted_owner_id ) ), 'Owner protection leaves orphan row untouched' );
	$assert( true === axismundi_actors_set_principal_role( $id, 'local_account', $n, 'owner' ), 'Local Account owner coexists' );
	$assert( is_wp_error( axismundi_actors_set_principal_role( $id, 'wp_user', $n, null ) ), 'Local owner cannot replace last WP owner anchor' );
	$assert( is_wp_error( axismundi_actors_add_manager( $id, $n, 'editor' ) ), 'Legacy adapter also protects last WP owner' );
	$assert( is_wp_error( axismundi_actors_promote_user_principal( $id, $n, $rid ) ), 'Promotion rejects unavailable selected Account' );
	$assert( true === axismundi_actors_set_principal_role( $n, 'wp_user', $n, 'owner' ), 'Selected Account access explicitly granted' );
	$assert( true === axismundi_actors_set_principal_role( $id, 'local_account', $n, null ), 'Explicit local grant can be revoked' );
	$assert( '' === axismundi_actors_principal_account_role( $id, 'local_account', $n ), 'WP access does not recursively recreate Actor access' );
	if ( function_exists( 'axismundi_forum_actor_is_moderator' ) ) {
		$assert( ! axismundi_forum_actor_is_moderator( $id, $subject ), 'Remaining User owner does not implicitly make the Actor a moderator' );
	}
	$assert( true === axismundi_actors_promote_user_principal( $id, $n, $n ), 'Promotion uses explicitly supplied accessible Account' );
	$assert( 'owner' === axismundi_actors_account_role( $id, $n ), 'Promotion preserves direct WP grant' );
	$assert( true === axismundi_actors_set_principal_role( $id, 'local_account', $n, 'editor' ), 'Explicit local role changes independently' );
	$assert( is_wp_error( axismundi_actors_promote_user_principal( $id, $n, $n ) ), 'Promotion does not overwrite a conflicting explicit Actor role' );
	$assert( false !== $wpdb->insert( axismundi_actors_managers_table(), array( 'identity_id' => $id, 'principal_kind' => 'remote_actor', 'principal_id' => $rid, 'user_id' => null, 'role' => 'owner', 'created_at' => $now, 'updated_at' => $now ) ), 'Remote stale grant fixture inserted without opening grant API' );
	$assert( is_wp_error( axismundi_actors_set_principal_role( $id, 'wp_user', $n, null ) ), 'Remote owner cannot replace the last live WP User anchor' );
	$assert( is_wp_error( axismundi_actors_set_principal_role( $id, 'remote_actor', $rid, 'editor' ) ), 'Existing remote grant cannot be updated through local writer' );
	foreach ( array( 'disabled', 'tombstone' ) as $status ) {
		axismundi_actors_set_status( $n, $status );
		$assert( null === axismundi_actors_resolve_principal( 'local_account', $n ), $status . ' local subject rejected' );
		$assert( '' === axismundi_actors_principal_account_role( $id, 'local_account', $n ), $status . ' subject cannot receive role' );
		axismundi_actors_set_status( $rid, $status );
		$assert( null === axismundi_actors_resolve_principal( 'remote_actor', $rid ), $status . ' remote subject rejected' );
	}
	$assert( true === axismundi_actors_set_principal_role( $id, 'remote_actor', $rid, null ), 'Trusted exact revocation accepts a tombstoned remote subject' );
	$assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE identity_id = %d AND principal_kind = %s AND principal_id = %d', axismundi_actors_managers_table(), $id, 'remote_actor', $rid ) ), 'Remote revocation deletes only the explicit remote grant' );
	$assert( 'owner' === axismundi_actors_account_role( $id, $n ), 'Remote revocation preserves direct User grant' );
	axismundi_actors_set_status( $id, 'disabled' );
	$assert( ! isset( axismundi_actors_accounts_for_principal( 'wp_user', $n )[ $id ] ), 'Typed Account list intentionally rejects disabled targets' );
	$assert( array() === axismundi_actors_account_principals( $id ), 'Active roster intentionally rejects disabled targets' );
	axismundi_actors_set_status( $id, 'tombstone' );
	$assert( '' === axismundi_actors_account_role( $id, $n ), 'Tombstone target denies existing User grant' );
	echo "PASS: {$checks} principal behavior checks\n";
} finally {
	foreach ( $actors as $id ) {
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_actors_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $id ) );
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	if ( $user_id ) {
		$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $user_id ) );
		$wpdb->delete( $wpdb->users, array( 'ID' => $user_id ) );
		clean_user_cache( $user_id );
	}
}
