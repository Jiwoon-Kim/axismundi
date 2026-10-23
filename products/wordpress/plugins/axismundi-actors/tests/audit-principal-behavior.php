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
	$assert( false !== $wpdb->insert( axismundi_actors_managers_table(), array( 'identity_id' => $id, 'principal_kind' => 'local_account', 'principal_id' => $n, 'user_id' => null, 'role' => 'manager', 'created_at' => $now, 'updated_at' => $now ) ), 'Local principal relation inserted' );
	$assert( '' === axismundi_actors_account_role( $id, $n ), 'Actor grant does not grant same-numbered User' );
	$assert( ! is_wp_error( axismundi_actors_write_manager_row( $id, $n, 'editor' ) ), 'User relation inserted' );
	$assert( 'editor' === axismundi_actors_account_role( $id, $n ), 'User gets only User role' );
	$assert( 'manager' === axismundi_actors_principal_account_role( $id, 'local_account', $n ), 'Local Account gets only local role' );
	foreach ( array( 'disabled', 'tombstone' ) as $status ) {
		axismundi_actors_set_status( $n, $status );
		$assert( null === axismundi_actors_resolve_principal( 'local_account', $n ), $status . ' local subject rejected' );
		$assert( '' === axismundi_actors_principal_account_role( $id, 'local_account', $n ), $status . ' subject cannot receive role' );
		axismundi_actors_set_status( $rid, $status );
		$assert( null === axismundi_actors_resolve_principal( 'remote_actor', $rid ), $status . ' remote subject rejected' );
	}
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
