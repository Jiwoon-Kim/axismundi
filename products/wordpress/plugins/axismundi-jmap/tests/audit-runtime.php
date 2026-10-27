<?php
/** Real Account policy and shared runtime; synthetic capabilities, no HTTP. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
use Axismundi\Jmap\AccountCapability;
use Axismundi\Jmap\MethodError;
use const Axismundi\Jmap\CORE;
use function Axismundi\Jmap\{accounts_table,policies_table,account_policy,set_account_policy,install_account_store,runtime_registry,server,check_account_limit};
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void { ++$checks; if ( ! $ok ) { throw new RuntimeException( $label ); } };
$snapshot = static function () use ( $wpdb ) : array {
	$rows = array();
	foreach ( array( axismundi_actors_actors_table() => 'id', axismundi_actors_managers_table() => 'identity_id,principal_kind,principal_id', \Axismundi\Jmap\accounts_table() => 'actor_id', \Axismundi\Jmap\policies_table() => 'account_id,capability_uri', axismundi_actors_meta_table() => 'meta_id' ) as $table => $order ) { $rows[ $table ] = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY {$order}", ARRAY_A ); }
	return $rows;
};
$before = $snapshot();
$initial_user = get_current_user_id();
$cookies = $_COOKIE;
$users = $actors = array();
$a = 'https://example.test/jmap/runtime';
$b = 'https://example.test/jmap/Runtime';
$used = 0;
$admin = 0;
$register = static function ( $registry ) use ( $a, $b, &$used, &$admin ) : void {
	$registry->register_capability( $a );
	$registry->register_capability( $b );
	$registry->register_method( 'Widget/get', $a, static fn() => (object) array( 'used' => $used ), true );
	$registry->register_method( 'Widget/set', $a, static function ( $args ) use ( $a, &$used ) {
		$result = check_account_limit( $args->accountId, $a, 'maxItems', $used );
		if ( is_wp_error( $result ) ) { throw new MethodError( 'forbidden', 'Resource limit.' ); }
		return (object) array( 'used' => ++$used );
	}, true, true );
	$registry->register_method( 'Widget/revoke', $a, static function ( $args ) use ( $a, &$admin ) {
		$user = get_current_user_id();
		wp_set_current_user( $admin );
		try { $result = set_account_policy( (int) substr( $args->accountId, 1 ), $a, false, (object) array( 'maxItems' => 2 ), runtime_registry() ); }
		finally { wp_set_current_user( $user ); }
		if ( true !== $result ) { throw new RuntimeException( 'Fixture policy revocation failed.' ); }
		return new stdClass();
	}, true );
	$registry->register_method( 'ReadOnly/get', $b, static fn() => new stdClass(), true );
	$registry->register_method( 'ReadOnly/set', $b, static fn() => new stdClass(), true, true );
	$registry->register_account_provider( $a, new stdClass(), static fn( $actor, $user, $limits ) => new AccountCapability( (object) array( 'maxItems' => $limits->maxItems ), 'owner' === axismundi_actors_account_role( $actor->get_identity_id(), $user ) ), 'runtime', static fn( $limits ) => count( get_object_vars( $limits ) ) === 1 && is_int( $limits->maxItems ?? null ) && $limits->maxItems >= 0 );
	$registry->register_account_provider( $b, new stdClass(), static fn() => new AccountCapability( new stdClass(), false ), 'runtime_read' );
};
add_action( 'axismundi_jmap_register', $register );
$network = static fn() => new WP_Error( 'runtime_no_http', 'No HTTP during runtime audit.' );
add_filter( 'pre_http_request', $network );
$transport = (object) array( 'apiUrl' => 'https://example.test/jmap', 'uploadUrl' => 'https://example.test/upload/{accountId}', 'downloadUrl' => 'https://example.test/download/{accountId}/{blobId}/{name}?type={type}', 'eventSourceUrl' => 'https://example.test/events?types={types}&closeafter={closeafter}&ping={ping}' );
$wire = static fn( $calls ) => json_encode( (object) array( 'using' => array( CORE, $a, $b ), 'methodCalls' => $calls ), JSON_THROW_ON_ERROR );
try {
	$assert( install_account_store(), 'Account store install is replay-safe' );
	foreach ( array( 'author', 'administrator' ) as $role ) {
		$user = wp_insert_user( array( 'user_login' => 'axruntime' . wp_generate_password( 12, false, false ), 'user_pass' => wp_generate_password(), 'role' => $role ) );
		$assert( ! is_wp_error( $user ), 'Fixture User created' );
		$users[] = (int) $user;
	}
	[ $owner, $admin ] = $users;
	$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'initial_owner_user_id' => $owner, 'personal_owner_user_id' => $owner, 'display_name' => 'Runtime Person' ) );
	$assert( $actor instanceof Axismundi_Actor, 'Local Actor created' );
	$id = $actor->get_identity_id();
	$actors[] = $id;
	$account = 'a' . $id;
	$assert( $id === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT actor_id FROM %i WHERE actor_id=%d', accounts_table(), $id ) ), 'Account configuration uses the Actor ID immediately after creation' );
	$columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . accounts_table() );
	$assert( array( 'actor_id','revision','updated_at' ) === $columns, 'No independent Account UUID, status or identity is stored' );
	$registry = runtime_registry();
	$assert( $registry === runtime_registry() && $registry->supports( CORE ), 'One registered Core runtime is reused' );
	wp_set_current_user( $owner );
	$session = server()->session( $transport );
	$assert( isset( $session->accounts->{$account} ) && '{}' === json_encode( $session->accounts->{$account}->accountCapabilities ), 'Provider registration alone does not grant a capability' );
	$assert( is_wp_error( set_account_policy( $id, $a, true, (object) array( 'maxItems' => 2 ), $registry ) ), 'Actor owner cannot self-assign administrative entitlements' );
	wp_set_current_user( $admin );
	$assert( is_wp_error( set_account_policy( $id, $a, true, (object) array( 'maxItems' => -1 ), $registry ) ), 'Invalid limits are rejected' );
	$assert( is_wp_error( set_account_policy( $id, CORE, true, new stdClass(), $registry ) ), 'Core is not an Account entitlement' );
	$stale = set_account_policy( $id, $a, true, (object) array( 'maxItems' => 2 ), $registry, 0 );
	$assert( is_wp_error( $stale ) && 'ax_jmap_policy_stale' === $stale->get_error_code() && null === account_policy( $id, $a ), 'Stale policy update does not create a grant' );
	$assert( true === set_account_policy( $id, $a, true, (object) array( 'maxItems' => 2 ), $registry, 1 ), 'Administrator assigns validated Account policy' );
	$assert( true === set_account_policy( $id, $b, true, new stdClass(), $registry ), 'Case-distinct capability has its own policy row' );
	$assert( 2 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE account_id=%d', policies_table(), $id ) ), 'Capability URI keys are case-sensitive' );
	$assert( '{}' === json_encode( server()->accounts()->accounts ), 'Entitlement administration grants no Actor operating access' );
	wp_set_current_user( $owner );
	$full = server()->session( $transport );
	$assert( isset( $full->capabilities->{CORE}, $full->accounts->{$account}->accountCapabilities->{$a} ) && ! isset( $full->accounts->{$account}->accountCapabilities->{CORE} ), 'Core and Account capabilities remain separate' );
	$assert( $session->state !== $full->state && ! $full->accounts->{$account}->isReadOnly, 'Effective capability change updates Session state' );
	$_COOKIE[ axismundi_actors_selection_cookie_name() ] = 'invalid-cookie';
	$assert( isset( server()->authorize( $account, $a )->accountCapabilities->{$a} ), 'Explicit Account authorization never uses the browser selection cookie' );
	$result = server()->execute( $wire( array( array( 'ReadOnly/set', (object) array( 'accountId' => $account ), 'deny' ) ) ), $transport );
	$assert( 'accountReadOnly' === ( $result->methodResponses[0][1]->type ?? null ), 'Writable capability A does not authorize writes through read-only capability B' );
	$result = server()->execute( $wire( array( array( 'Widget/set', new stdClass(), 'missing' ) ) ), $transport );
	$assert( 'invalidArguments' === $result->methodResponses[0][1]->type && 0 === $used, 'Missing accountId does not fall back to a cookie or primary Account' );
	foreach ( array( 1, 2 ) as $count ) {
		$result = server()->execute( $wire( array( array( 'Widget/set', (object) array( 'accountId' => $account ), 'write' ) ) ), $transport );
		$assert( $count === $result->methodResponses[0][1]->used, 'JMAP handler uses the shared resource-limit service' );
	}
	$assert( is_wp_error( check_account_limit( $account, $a, 'maxItems', $used ) ), 'WordPress PHP callers encounter the same limit' );
	$result = server()->execute( $wire( array( array( 'Widget/set', (object) array( 'accountId' => $account ), 'over' ) ) ), $transport );
	$assert( 'forbidden' === $result->methodResponses[0][1]->type && 2 === $used, 'Exceeding the resource limit performs no write' );
	$result = server()->execute( $wire( array( array( 'Widget/revoke', (object) array( 'accountId' => $account ), 'revoke' ), array( 'Widget/set', (object) array( 'accountId' => $account ), 'after' ) ) ), $transport );
	$assert( 'accountNotSupportedByMethod' === ( $result->methodResponses[1][1]->type ?? null ) && 2 === $used, 'Policy revocation is rechecked between methods in one batch' );
	$assert( $result->sessionState === server()->session( $transport )->state && $result->sessionState !== $full->state, 'Response carries current post-execution Session state' );
	$grant = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE identity_id=%d AND principal_kind=%s AND principal_id=%d', axismundi_actors_managers_table(), $id, 'wp_user', $owner ), ARRAY_A );
	$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $id, 'principal_kind' => 'wp_user', 'principal_id' => $owner ) );
	$assert( ! isset( server()->accounts()->accounts->{$account} ), 'Principal revocation removes Account access without changing its identity' );
	$wpdb->insert( axismundi_actors_managers_table(), $grant );
	$assert( axismundi_actors_set_status( $id, 'disabled' ) && ! isset( server()->accounts()->accounts->{$account} ), 'Actor disable immediately disables Account access' );
	axismundi_actors_set_status( $id, 'public' );
	$assert( isset( server()->accounts()->accounts->{$account} ), 'Actor recovery restores access through the existing principal' );
	$remote_uri = 'https://example.com/actors/runtime-' . wp_generate_uuid4();
	$remote = axismundi_actors_upsert_remote( array( 'uri' => $remote_uri, 'actor_type' => 'Person', 'payload' => array( 'id' => $remote_uri, 'type' => 'Person' ), 'endpoints' => array( 'inbox' => $remote_uri . '/inbox', 'outbox' => $remote_uri . '/outbox' ) ) );
	$assert( $remote instanceof Axismundi_Actor, 'Remote fixture exists' );
	$actors[] = $remote->get_identity_id();
	wp_set_current_user( $admin );
	$assert( is_wp_error( set_account_policy( $remote->get_identity_id(), $a, true, (object) array( 'maxItems' => 2 ), $registry ) ) && null === $wpdb->get_var( $wpdb->prepare( 'SELECT actor_id FROM %i WHERE actor_id=%d', accounts_table(), $remote->get_identity_id() ) ), 'Remote Actor cannot acquire a local Account configuration' );
	wp_set_current_user( $owner );
	$assert( axismundi_actors_set_status( $id, 'tombstone' ) && ! isset( server()->accounts()->accounts->{$account} ) && $id === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT actor_id FROM %i WHERE actor_id=%d', accounts_table(), $id ) ), 'Tombstone denies access while retaining configuration with the retired Actor' );
} finally {
	$_COOKIE = $cookies;
	wp_set_current_user( $initial_user );
	foreach ( $actors as $id ) {
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_signing_keys_table(), axismundi_actors_keys_table(), axismundi_actors_endpoints_table(), axismundi_actors_addresses_table(), axismundi_actors_texts_table(), axismundi_actors_fetch_state_table(), axismundi_actors_asset_cache_table(), axismundi_actors_identity_relations_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_actors_table(), array( 'id' => $id ) );
	}
	foreach ( $users as $user ) { wp_delete_user( $user ); }
	remove_action( 'axismundi_jmap_register', $register );
	remove_filter( 'pre_http_request', $network );
}
$assert( $before === $snapshot(), 'Actors, principals, Account configuration, policies and detail rows are preserved exactly after fixture cleanup' );
printf( "PASS: %d runtime checks\n", $checks );
