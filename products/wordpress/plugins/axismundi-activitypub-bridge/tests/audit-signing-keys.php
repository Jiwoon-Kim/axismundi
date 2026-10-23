<?php
/** Local key isolation, rotation and delivery regression. Development WP CLI only. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$users = array(); $actors = array(); $checks = 0; $fail = null;
$original_user = get_current_user_id();
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $label ); }
	++$checks;
};
try {
	foreach ( array( 'author', 'administrator' ) as $role ) {
		$user = wp_insert_user( array( 'user_login' => 'axkey' . strtolower( wp_generate_password( 12, false ) ), 'user_pass' => wp_generate_password(), 'role' => $role ) );
		$assert( ! is_wp_error( $user ), 'User fixture created' );
		$users[] = (int) $user;
	}
	wp_set_current_user( $users[0] );
	foreach ( array( 'Group', 'Service', 'Person' ) as $type ) {
		$actor = axismundi_actors_create_local( array( 'actor_type' => $type, 'actor_scope' => 'Person' === $type ? 'user' : 'managed', 'personal_owner_user_id' => 'Person' === $type ? $users[0] : null, 'initial_owner_user_id' => $users[0], 'status' => 'public', 'preferred_username' => 'axkey' . strtolower( wp_generate_password( 12, false ) ) ) );
		$assert( $actor instanceof Axismundi_Actor, $type . ' created with key' );
		$actors[] = $actor;
		$assert( 'actor' === axismundi_actors_transport_binding( $actor )['kind'], 'New Actor uses independent provider' );
	}
	$group = $actors[0]; $id = $group->get_identity_id();
	$public = axismundi_activitypub_bridge_public_key( $group );
	$private = axismundi_activitypub_bridge_private_key( $group );
	$fp = hash( 'sha256', trim( $public ) );
	$assert( '' !== $private && '' !== $public, 'Local key pair resolves' );
	$assert( $public !== axismundi_activitypub_bridge_public_key( $actors[1] ) && $public !== axismundi_activitypub_bridge_public_key( $actors[2] ), 'Group Service and additional Person have distinct keys' );
	$assert( $public !== Activitypub\Application::get_public_key(), 'New Actor does not use Application key' );
	$signature = '';
	$assert( openssl_sign( 'actor-key-audit', $signature, $private, OPENSSL_ALGO_SHA256 ) && 1 === openssl_verify( 'actor-key-audit', $signature, $public, OPENSSL_ALGO_SHA256 ), 'Actor pair creates verifiable RSA signature' );
	$assert( 0 === openssl_verify( 'actor-key-audit', $signature, axismundi_activitypub_bridge_public_key( $actors[1] ), OPENSSL_ALGO_SHA256 ), 'Other Actor cannot verify this signature' );
	$table = axismundi_actors_signing_keys_table();
	$old = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE identity_id = %d', $table, $id ), ARRAY_A );
	$assert( str_starts_with( $old['secret_box'], 'v1:' ) && ! str_contains( $old['secret_box'], 'PRIVATE KEY' ) && ! str_contains( $old['secret_box'], $private ), 'Stored secret is encrypted, not PEM' );
	$assert( ! str_contains( wp_json_encode( axismundi_actors_local_public_key( $group ) ), 'PRIVATE KEY' ), 'Public projection has no private material' );
	$wpdb->update( $table, array( 'secret_box' => 'v1:' . base64_encode( random_bytes( 80 ) ) ), array( 'id' => $old['id'] ) );
	try {
		$assert( '' === axismundi_activitypub_bridge_public_key( $group ) && '' === axismundi_activitypub_bridge_private_key( $group ), 'Corrupt encrypted secret withholds transport instead of Application fallback' );
	} finally { $wpdb->update( $table, array( 'secret_box' => $old['secret_box'] ), array( 'id' => $old['id'] ) ); }
	$assert( axismundi_actors_migrate_transport_bindings() && $public === axismundi_activitypub_bridge_public_key( $group ), 'Migration never rotates local Actor key' );
	wp_set_current_user( $users[1] );
	$assert( is_wp_error( axismundi_actors_rotate_signing_key( $id, $fp ) ), 'Unrelated administrator cannot rotate key' );
	axismundi_actors_write_manager_row( $id, $users[1], 'editor' );
	$assert( axismundi_actors_can_act_as( $group, $users[1] ) && is_wp_error( axismundi_actors_rotate_signing_key( $id, $fp ) ), 'Delegate who can publish still cannot rotate owner key' );
	$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $id, 'principal_kind' => 'wp_user', 'principal_id' => $users[1] ) );
	wp_set_current_user( $users[0] );
	$assert( is_wp_error( axismundi_actors_rotate_signing_key( $id, str_repeat( '0', 64 ) ) ), 'Stale fingerprint fails closed' );
	foreach ( array( 'insert', 'pointer', 'commit' ) as $stage ) {
		$fail = static function ( string $sql ) use ( $stage, $table ) : string {
			$match = ( 'commit' === $stage && 'COMMIT' === $sql )
				|| ( 'insert' === $stage && str_starts_with( $sql, 'INSERT INTO' ) && str_contains( $sql, $table ) )
				|| ( 'pointer' === $stage && str_starts_with( $sql, 'UPDATE' ) && str_contains( $sql, 'local_signing_key_id' ) );
			return $match ? 'SELECT * FROM ax_missing_key_audit' : $sql;
		};
		add_filter( 'query', $fail ); $suppress = $wpdb->suppress_errors();
		try { $result = axismundi_actors_rotate_signing_key( $id, $fp ); }
		finally { remove_filter( 'query', $fail ); $fail = null; $wpdb->suppress_errors( $suppress ); }
		$assert( is_wp_error( $result ) && $public === axismundi_activitypub_bridge_public_key( $group ), $stage . ' failure preserves old key' );
		$assert( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE identity_id = %d', $table, $id ) ), 'Failed rotation leaves no history event' );
	}
	$new_fp = axismundi_actors_rotate_signing_key( $id, $fp );
	$new_public = axismundi_activitypub_bridge_public_key( $group );
	$assert( is_string( $new_fp ) && $fp !== $new_fp && $public !== $new_public, 'Explicit owner rotation changes key' );
	$assert( $group->get_uri() . '#main-key' === axismundi_actors_local_public_key( $group )['id'], 'Rotation preserves published key ID' );
	$update = axismundi_act_get_object_lifecycle( $group->get_uri() );
	$assert( $update instanceof Axismundi_Activity && 'Update' === $update->get_type()
		&& $new_public === $update->get_payload()['object']['publicKey']['publicKeyPem']
		&& ! str_contains( wp_json_encode( $update->get_payload() ), 'PRIVATE KEY' ), 'Rotation publishes public Actor Update without secrets' );
	$assert( is_wp_error( axismundi_actors_rotate_signing_key( $id, $fp ) ), 'Second stale caller cannot rotate again' );
	$retired = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $old['id'] ), ARRAY_A );
	$assert( null === $retired['secret_box'] && null !== $retired['retired_at'] && $public === $retired['public_key_pem'], 'Rotation wipes retired secret but keeps public history' );
	$event = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE identity_id = %d ORDER BY id DESC LIMIT 1', $table, $id ), ARRAY_A );
	$assert( $fp === $event['previous_fingerprint'] && $users[0] === (int) $event['initiated_by'], 'Rotation event records predecessor and owner' );
	$job = (object) array( 'actor_uri' => $group->get_uri(), 'key_id' => $group->get_uri() . '#main-key' );
	$identity = axismundi_activitypub_bridge_delivery_identity( $job );
	$assert( is_array( $identity ) && openssl_sign( 'queued-after-rotation', $signature, $identity['private_key'], OPENSSL_ALGO_SHA256 ) && 1 === openssl_verify( 'queued-after-rotation', $signature, $new_public, OPENSSL_ALGO_SHA256 ), 'Existing delivery key reference resolves rotated pair' );
	// Model an already published pre-S3b Group without replacing its upstream key.
	$legacy = $actors[1];
	$wpdb->update( axismundi_actors_actors_table(), array( 'transport_kind' => 'upstream_application', 'local_signing_key_id' => null ), array( 'identity_id' => $legacy->get_identity_id() ) );
	$legacy_pem = axismundi_activitypub_bridge_public_key( $legacy );
	$assert( axismundi_actors_migrate_transport_bindings() && $legacy_pem === axismundi_activitypub_bridge_public_key( $legacy ), 'Existing upstream Actor keeps key during migration' );
	$assert( is_string( axismundi_actors_rotate_signing_key( $legacy->get_identity_id(), hash( 'sha256', trim( $legacy_pem ) ) ) ) && 'actor' === axismundi_actors_transport_binding( $legacy )['kind'], 'Existing upstream Actor adopts own key only by explicit rotation' );
	$person = axismundi_actors_ensure_for_user( $users[0] );
	$assert( $person instanceof Axismundi_Actor, 'Legacy per-user Actor fixture exists' );
	$actors[] = $person;
	axismundi_actors_register_handle( $person->get_identity_id(), 'axkey' . strtolower( wp_generate_password( 12, false ) ) );
	axismundi_actors_set_status( $person->get_identity_id(), 'public' );
	$person = axismundi_actors_get_by_identity( $person->get_identity_id() );
	$wpdb->update( axismundi_actors_actors_table(), array( 'transport_kind' => 'upstream_user', 'transport_user_id' => $users[0], 'local_signing_key_id' => null ), array( 'identity_id' => $person->get_identity_id() ) );
	$person_fp = hash( 'sha256', trim( axismundi_activitypub_bridge_public_key( $person ) ) );
	$assert( is_string( axismundi_actors_rotate_signing_key( $person->get_identity_id(), $person_fp ) ), 'Legacy per-user Actor rotates explicitly' );
	$assert( $person->get_identity_id() === axismundi_actors_get_transport_actor_for_user( $users[0] )->get_identity_id(), 'Old per-user Inbox routing survives key rotation' );
	$assert( $users[0] === axismundi_actors_transport_binding( $person )['user_id'], 'Legacy user moderation context survives rotation' );
	$wrong_public = axismundi_activitypub_bridge_public_key( $actors[2] );
	$public_filter = static fn( $pem ) => $wrong_public;
	add_filter( 'axismundi_activitypub_bridge_actor_public_key', $public_filter );
	try { $assert( is_wp_error( axismundi_activitypub_bridge_delivery_identity( $job ) ), 'Delivery refuses mismatched projected and private keys' ); }
	finally { remove_filter( 'axismundi_activitypub_bridge_actor_public_key', $public_filter ); }
	foreach ( array( 'disabled', 'tombstone' ) as $status ) {
		axismundi_actors_set_status( $id, $status );
		$assert( null === axismundi_actors_signing_key_snapshot( $group ), 'Stale Actor object cannot bypass current ' . $status . ' state' );
	}
	foreach ( array( 'insert', 'commit' ) as $stage ) {
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE origin = 'local'", axismundi_actors_identities_table() ) );
		$fail = static function ( string $sql ) use ( $stage, $table ) : string {
			return ( ( 'commit' === $stage && 'COMMIT' === $sql ) || ( 'insert' === $stage && str_starts_with( $sql, 'INSERT INTO' ) && str_contains( $sql, $table ) ) ) ? 'SELECT * FROM ax_missing_create_key_audit' : $sql;
		};
		add_filter( 'query', $fail ); $suppress = $wpdb->suppress_errors();
		try { $result = axismundi_actors_create_local( array( 'actor_type' => 'Service', 'actor_scope' => 'managed', 'initial_owner_user_id' => $users[0] ) ); }
		finally { remove_filter( 'query', $fail ); $fail = null; $wpdb->suppress_errors( $suppress ); }
		$assert( is_wp_error( $result ) && $count === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE origin = 'local'", axismundi_actors_identities_table() ) ), 'New Actor ' . $stage . ' failure rolls back identity and key' );
	}
	echo "PASS: {$checks} signing key checks\n";
} finally {
	if ( $fail ) { remove_filter( 'query', $fail ); }
	wp_set_current_user( $original_user );
	foreach ( $actors as $actor ) {
		$id = $actor->get_identity_id();
		$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri_hash' => hash( 'sha256', $actor->get_uri() ) ) );
		foreach ( array( axismundi_actors_signing_keys_table(), axismundi_actors_managers_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_actors_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $user ) { wp_delete_user( $user ); }
}
