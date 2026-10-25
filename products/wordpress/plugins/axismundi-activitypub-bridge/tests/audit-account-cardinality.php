<?php
/** S4 cardinality migration and multi-Person transport audit. Development WP CLI only. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0; $users = array(); $actors = array(); $uris = array(); $fail = null;
$before_user = get_current_user_id();
$table = $wpdb->prefix . 'ax_cardinality_audit_' . strtolower( wp_generate_password( 8, false ) );
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $label ); }
	++$checks;
};
try {
	$assert( AXISMUNDI_ACTORS_DB_VERSION === get_option( 'ax_actors_db_version' ), 'Schema migration finished' );
	foreach ( array( 'ADD UNIQUE KEY', 'DROP INDEX local_user_id', 'ADD KEY local_user_lookup', 'DROP INDEX transport_user_id' ) as $stage ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		$wpdb->query( $wpdb->prepare( 'CREATE TABLE %i (identity_id bigint unsigned NOT NULL PRIMARY KEY, local_user_id bigint unsigned NULL, transport_user_id bigint unsigned NULL, UNIQUE KEY local_user_id(local_user_id), KEY transport_user_id(transport_user_id)) ENGINE=InnoDB', $table ) );
		$wpdb->query( $wpdb->prepare( 'INSERT INTO %i VALUES (1,11,11),(2,22,22)', $table ) );
		$hit = false;
		$fail = static function ( string $sql ) use ( $table, $stage, &$hit ) : string {
			if ( str_contains( $sql, $table ) && str_contains( $sql, $stage ) ) { $hit = true; return 'SELECT * FROM ax_missing_cardinality_audit'; }
			return $sql;
		};
		add_filter( 'query', $fail ); $suppress = $wpdb->suppress_errors();
		try { $result = axismundi_actors_upgrade_account_cardinality( $table ); }
		finally { remove_filter( 'query', $fail ); $fail = null; $wpdb->suppress_errors( $suppress ); }
		$assert( $hit && ! $result, 'DDL failure is reported: ' . $stage );
		if ( 'ADD UNIQUE KEY' === $stage ) {
			$assert( '0' === (string) $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name='local_user_id'", $table ), 1 ), 'Old constraint is not dropped before transport uniqueness is verified' );
		}
		$assert( axismundi_actors_upgrade_account_cardinality( $table ) && axismundi_actors_upgrade_account_cardinality( $table ), 'Partial DDL retry and replay succeed' );
		$assert( false !== $wpdb->query( $wpdb->prepare( 'INSERT INTO %i VALUES (3,11,NULL),(4,11,NULL)', $table ) ), 'Multiple local links and unbound Accounts are allowed' );
		$suppress = $wpdb->suppress_errors();
		try { $duplicate = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET transport_user_id=11 WHERE identity_id=3', $table ) ); }
		finally { $wpdb->suppress_errors( $suppress ); }
		$assert( false === $duplicate, 'Database rejects duplicate legacy route' );
		$assert( array( '11', '22' ) === array_map( 'strval', $wpdb->get_col( $wpdb->prepare( 'SELECT transport_user_id FROM %i WHERE identity_id IN (1,2) ORDER BY identity_id', $table ) ) ), 'Existing bindings survive DDL' );
	}
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	$wpdb->query( $wpdb->prepare( 'CREATE TABLE %i (identity_id bigint unsigned NOT NULL PRIMARY KEY, local_user_id bigint unsigned NULL, transport_user_id bigint unsigned NULL, UNIQUE KEY local_user_id(local_user_id)) ENGINE=InnoDB', $table ) );
	$wpdb->query( $wpdb->prepare( 'INSERT INTO %i VALUES (1,11,11),(2,22,11)', $table ) );
	$assert( ! axismundi_actors_upgrade_account_cardinality( $table ), 'Ambiguous legacy routes stop migration without picking a winner' );
	$assert( '0' === (string) $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name='local_user_id'", $table ), 1 ), 'Old ownership uniqueness survives conflicting route data' );
	$wpdb->query( $wpdb->prepare( 'UPDATE %i SET transport_user_id=22 WHERE identity_id=2', $table ) );
	$assert( axismundi_actors_upgrade_account_cardinality( $table ), 'Repaired route data permits retry' );
	foreach ( array( 'author', 'author' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'axs4' . strtolower( wp_generate_password( 12, false ) ), 'user_pass' => wp_generate_password(), 'role' => $role ) );
		$assert( ! is_wp_error( $id ), 'User fixture created' ); $users[] = (int) $id;
	}
	wp_set_current_user( $users[0] );
	$first = axismundi_actors_ensure_for_user( $users[0] );
	$assert( $first instanceof Axismundi_Actor, 'Canonical Person created' ); $actors[] = $first;
	$second = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'local_user_id' => $users[0], 'personal_owner_user_id' => $users[0] ) );
	$assert( $second instanceof Axismundi_Actor, 'Second Person with same legacy user link can be stored' ); $actors[] = $second;
	foreach ( array( $first, $second ) as $actor ) {
		axismundi_actors_register_handle( $actor->get_identity_id(), 'axs4' . strtolower( wp_generate_password( 12, false ) ) );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	}
	$first = axismundi_actors_get_by_identity( $first->get_identity_id() );
	$second = axismundi_actors_get_by_identity( $second->get_identity_id() );
	$assert( $users[0] === $second->get_personal_owner_user_id() && 'owner' === axismundi_actors_account_role( $second->get_identity_id(), $users[0] ), 'Personal attribution and access relation are both seeded' );
	$assert( axismundi_actors_account_is_personal( $first, $users[0] ) && axismundi_actors_account_is_personal( $second, $users[0] ), 'Both Accounts are personal to same owner' );
	$assert( 'actor' === axismundi_actors_transport_binding( $second )['kind'] && null === axismundi_actors_transport_binding( $second )['user_id'], 'Additional Person has own provider and no duplicate legacy route' );
	$canonical = axismundi_actors_get_for_user( $users[0] );
	$assert( $canonical instanceof Axismundi_Actor && $first->get_identity_id() === $canonical->get_identity_id(), 'Legacy User getter is pinned to canonical transport binding' );
	$assert( ! is_wp_error( axismundi_actors_set_workspace( $users[0], $second->get_identity_id() ) ) && ! is_wp_error( axismundi_actors_set_default_account( $users[0], 's4', $second->get_identity_id() ) ), 'Workspace and default preference may select second Account' );
	$assert( $first->get_identity_id() === axismundi_actors_get_for_user( $users[0] )->get_identity_id(), 'Preferences do not rebind old User routes' );
	$first_key = axismundi_activitypub_bridge_public_key( $first );
	$assert( axismundi_actors_migrate_transport_bindings() && axismundi_actors_upgrade_account_cardinality( axismundi_actors_actors_table() ), 'Replay accepts multi-Person data without rebinding' );
	$assert( $first_key === axismundi_activitypub_bridge_public_key( $first ), 'Cardinality replay does not change existing signing material' );
	$public = axismundi_activitypub_bridge_public_key( $second ); $signature = '';
	$assert( '' !== $public && $public !== axismundi_activitypub_bridge_public_key( $first ) && $public !== Activitypub\Application::get_public_key(), 'Additional Person key is independent of first Person and Application' );
	$assert( openssl_sign( 's4-person', $signature, axismundi_activitypub_bridge_private_key( $second ), OPENSSL_ALGO_SHA256 ) && 1 === openssl_verify( 's4-person', $signature, $public, OPENSSL_ALGO_SHA256 ), 'Additional Person signs using advertised key' );
	$assert( axismundi_activitypub_bridge_inbox_url( $second ) !== axismundi_activitypub_bridge_inbox_url( $first ) && axismundi_activitypub_bridge_inbox_url( $second ) !== axismundi_activitypub_bridge_shared_inbox_url(), 'Additional Person has distinct Inbox' );
	$uri = 'https://example.com/s4-' . wp_generate_uuid4();
	$remote = axismundi_actors_upsert_remote( array( 'uri' => $uri, 'actor_type' => 'Person', 'payload' => array( 'id' => $uri, 'type' => 'Person' ), 'endpoints' => array( 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ) ) );
	$assert( $remote instanceof Axismundi_Actor, 'Remote sender fixture cached' ); $actors[] = $remote;
	$payload = array( 'id' => $uri . '/like', 'type' => 'Like', 'actor' => $uri, 'object' => $uri . '/object', 'to' => array( $second->get_uri() ) ); $uris[] = $payload['id'];
	$assert( axismundi_activitypub_bridge_record_inbox( $payload, array(), false, $second ) instanceof Axismundi_Activity, 'Additional Person receives addressed Activity' );
	$assert( 1 === count( axismundi_act_get_actor_inbox_page( $second->get_uri() )['items'] ) && array() === axismundi_act_get_actor_inbox_page( $first->get_uri() )['items'], 'Owner can read both Accounts but their Inbox memberships stay isolated' );
	wp_set_current_user( $users[1] );
	$assert( null === axismundi_actors_resolve_workspace( $second->get_identity_id(), $users[1] ), 'Other User gains no Account access' );
	wp_set_current_user( $users[0] );
	$assert( ! is_wp_error( axismundi_act_write_relation( 'block', $second->get_uri(), $uri, 'outbound', 'active', null, null ) ), 'Additional Person can own Block relation' );
	$payload['id'] = $uri . '/mixed'; $uris[] = $payload['id']; $payload['to'][] = $first->get_uri();
	$row = axismundi_activitypub_bridge_record_inbox( $payload );
	$assert( $row instanceof Axismundi_Activity, 'Unblocked first Person can still receive shared delivery' );
	$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT actor_identity_id FROM %i WHERE activity_id=%d', axismundi_act_inbox_table(), $row->get_id() ) );
	$assert( array( $first->get_identity_id() ) === array_map( 'intval', $ids ), 'Additional Person Block filters only its own receipt' );
	$suppress = $wpdb->suppress_errors();
	try { $duplicate = $wpdb->update( axismundi_actors_actors_table(), array( 'transport_user_id' => $users[0] ), array( 'identity_id' => $second->get_identity_id() ) ); }
	finally { $wpdb->suppress_errors( $suppress ); }
	$assert( false === $duplicate, 'Live database unique constraint protects old Inbox route' );
	$canonical = axismundi_actors_get_transport_actor_for_user( $users[0] );
	$assert( $canonical instanceof Axismundi_Actor && $first->get_identity_id() === $canonical->get_identity_id(), 'Old Inbox still resolves exactly one Actor' );
	$assert( is_wp_error( axismundi_actors_create_account( array( 'display_name' => 'Still gated' ) ) ), 'Public additional-account creation gate remains closed' );
	echo "PASS: {$checks} cardinality checks\n";
} finally {
	if ( $fail ) { remove_filter( 'query', $fail ); }
	wp_set_current_user( $before_user );
	foreach ( $uris as $uri ) { $activity = axismundi_act_get( $uri ); if ( $activity ) { $wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $activity->get_id() ) ); $wpdb->delete( axismundi_act_activities_table(), array( 'id' => $activity->get_id() ) ); } }
	foreach ( $actors as $actor ) {
		$id = $actor->get_identity_id();
		$wpdb->delete( axismundi_act_relations_table(), array( 'subject_actor_uri_hash' => hash( 'sha256', $actor->get_uri() ) ) );
		foreach ( array( axismundi_actors_signing_keys_table(), axismundi_actors_managers_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_actors_table() ) as $t ) { $wpdb->delete( $t, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	require_once ABSPATH . 'wp-admin/includes/user.php'; foreach ( $users as $user ) { wp_delete_user( $user ); }
}
