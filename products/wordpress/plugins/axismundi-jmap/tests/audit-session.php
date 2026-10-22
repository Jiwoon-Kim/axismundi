<?php
/** Development-only Session audit: wp eval-file. @package AxismundiJmap */
defined( 'ABSPATH' ) || exit( 1 );
require_once dirname( __DIR__ ) . '/axismundi-jmap.php';

use Axismundi\Jmap\AccountCapability;
use Axismundi\Jmap\SessionProjector;
use Axismundi\Jmap\Engine;
use Axismundi\Jmap\Context;
use Axismundi\Jmap\MethodError;
use function Axismundi\Jmap\core_registry;
use const Axismundi\Jmap\CORE;

$users = array();
$fixtures = array();
$original_user = get_current_user_id();
$checks = 0;
$assert = static function ( bool $condition, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
};
$reject = static function ( callable $action, string $label ) use ( $assert ) : void {
	$rejected = false;
	try {
		$action();
	} catch ( InvalidArgumentException | LogicException $error ) {
		$rejected = true;
	}
	$assert( $rejected, $label );
};
// Synthetic transport contracts and a synthetic provider, never production advertisements.
$transport = (object) array(
	'apiUrl' => 'https://example.test/jmap/api',
	'downloadUrl' => 'https://example.test/jmap/{accountId}/{blobId}/{name}?type={type}',
	'uploadUrl' => 'https://example.test/jmap/upload/{accountId}',
	'eventSourceUrl' => 'https://example.test/jmap/events?types={types}&closeafter={closeafter}&ping={ping}',
);
$core = (object) array( 'maxSizeUpload' => 0, 'maxConcurrentUpload' => 0, 'maxSizeRequest' => 1000000, 'maxConcurrentRequests' => 1, 'maxCallsInRequest' => 16, 'maxObjectsInGet' => 0, 'maxObjectsInSet' => 0, 'collationAlgorithms' => array() );
$uri = 'https://example.test/jmap/test';
try {
	global $wpdb;
	foreach ( array( 'author', 'administrator' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'axsession' . strtolower( wp_generate_password( 12, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => $role ) );
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}
		$users[] = (int) $id;
	}
	wp_set_current_user( $users[0] );
	foreach ( array( 'Person', 'Person', 'Organization' ) as $index => $type ) {
		$actor = axismundi_actors_create_local( array( 'actor_type' => $type, 'actor_scope' => 'Person' === $type ? 'user' : 'managed', 'personal_owner_user_id' => 'Person' === $type ? $users[0] : null, 'initial_owner_user_id' => $users[0], 'display_name' => 'Session ' . $index ) );
		if ( ! $actor instanceof Axismundi_Actor ) {
			throw new RuntimeException( 'Fixture failed' );
		}
		$fixtures[] = $actor->get_identity_id();
	}
	$ids = array_map( static fn( $id ) => 'a' . $id, $fixtures );
	$plain = new SessionProjector( core_registry() );
	$reject( fn() => new SessionProjector( new \Axismundi\Jmap\Registry() ), 'Cannot advertise Core from an empty registry' );
	$base = $plain->build( $users[0], $transport, $core );
	$metadata = get_user_meta( $users[0] );
	$plain->build( $users[0], $transport, $core );
	$assert( $metadata === get_user_meta( $users[0] ), 'Session GET projection does not mutate preferences' );
	$assert( ! str_contains( json_encode( $base ), 'personal_owner_user_id' ) && ! str_contains( json_encode( $base ), 'user_pass' ), 'Internal ownership columns and password fields not serialized' );
	$not_ready = static fn() => 'incomplete';
	add_filter( 'pre_option_ax_actors_account_access_version', $not_ready );
	try {
		$reject( fn() => $plain->accounts( $users[0] ), 'Incomplete migration rejects Session instead of granting legacy access' );
	} finally {
		remove_filter( 'pre_option_ax_actors_account_access_version', $not_ready );
	}
	$assert( 3 === count( get_object_vars( $base->accounts ) ), 'All accessible Accounts exposed without handle requirement' );
	$assert( $base->accounts->{$ids[0]}->isPersonal && $base->accounts->{$ids[1]}->isPersonal && ! $base->accounts->{$ids[2]}->isPersonal, 'Multiple personal Accounts, organization operational owner not personal' );
	$assert( '{}' === json_encode( $base->accounts->{$ids[0]}->accountCapabilities ) && $base->accounts->{$ids[0]}->isReadOnly, 'No implicit capabilities or writes from ownership' );
	$assert( '{}' === json_encode( $base->primaryAccounts ) && 1 === count( get_object_vars( $base->capabilities ) ), 'Core only, no fake Contacts capability or primary' );
	$assert( get_userdata( $users[0] )->user_login === $base->username && 'Session 0' === $base->accounts->{$ids[0]}->name, 'Credential username and Account display name independent' );
	$assert( $base->state === $plain->build( $users[0], $transport, $core )->state, 'Repeated Session has stable state' );
	axismundi_actors_set_workspace( $users[0], $fixtures[2] );
	$assert( $base->state === $plain->build( $users[0], $transport, $core )->state, 'Active Actor is not Session primary or state' );
	$reordered = (object) array_reverse( get_object_vars( $core ), true );
	$assert( $base->state === $plain->build( $users[0], $transport, $reordered )->state, 'Object member ordering does not change state' );
	$reject( fn() => $plain->accounts( $users[1] ), 'Another User Session rejected even for administrators' );
	$reject( fn() => $plain->accounts( 0 ), 'Anonymous Session rejected' );
	$bad = clone $transport;
	$bad->apiUrl = 'http://example.test/api';
	$reject( fn() => $plain->build( $users[0], $bad, $core ), 'HTTP URL rejected' );
	$bad->apiUrl = 'https://user@example.test/api';
	$reject( fn() => $plain->build( $users[0], $bad, $core ), 'URL credentials rejected' );
	$bad = clone $transport;
	$bad->uploadUrl = 'https://example.test/upload';
	$reject( fn() => $plain->build( $users[0], $bad, $core ), 'Missing template variable rejected' );
	$bad->uploadUrl = 'https://example.test/upload/{+accountId}';
	$reject( fn() => $plain->build( $users[0], $bad, $core ), 'Unsupported template operator rejected' );
	$reject( fn() => $plain->build( $users[0], new stdClass(), $core ), 'No invented endpoint defaults' );
	$bad_core = clone $core;
	unset( $bad_core->maxConcurrentRequests );
	$reject( fn() => $plain->build( $users[0], $transport, $bad_core ), 'Missing normative Core limit rejected' );
	$bad_core = clone $core;
	$bad_core->maxCallsInRequest = -1;
	$reject( fn() => $plain->build( $users[0], $transport, $bad_core ), 'Negative Core limit rejected' );
	$bad_core->maxCallsInRequest = 9007199254740992;
	$reject( fn() => $plain->build( $users[0], $transport, $bad_core ), 'Unsafe JSON integer rejected' );
	$bad_core = clone $core;
	$bad_core->collationAlgorithms = array( 42 );
	$reject( fn() => $plain->build( $users[0], $transport, $bad_core ), 'Non-string collation rejected' );
	$registry = core_registry();
	$registry->register_capability( $uri );
	$reject( fn() => $registry->register_account_provider( $uri, new stdClass(), static fn() => null, 'test' ), 'No provider advertisement without registered Account method' );
	$registry->register_method( 'Test/get', $uri, static fn() => new stdClass(), true );
	$registry->register_method( 'Test/set', $uri, static fn() => new stdClass(), true, true );
	$enabled = true;
	$registry->register_account_provider( $uri, (object) array( 'test' => true ), static function ( $actor, $user ) use ( &$enabled, $fixtures, $users ) {
		return $enabled && $actor->get_identity_id() === $fixtures[2] ? new AccountCapability( (object) array( 'maxItems' => 10 ), $user === $users[0] ) : null;
	}, 'test' );
	$projector = new SessionProjector( $registry );
	$reject( fn() => $registry->register_capability( 'https://example.test/late' ), 'Session freezes registration' );
	$full = $projector->build( $users[0], $transport, $core );
	$changed_transport = clone $transport;
	$changed_transport->apiUrl = 'https://example.test/jmap/v2';
	$assert( $full->state !== $projector->build( $users[0], $changed_transport, $core )->state, 'Transport URL change affects Session state' );
	$changed_core = clone $core;
	$changed_core->maxCallsInRequest = 8;
	$assert( $full->state !== $projector->build( $users[0], $transport, $changed_core )->state, 'Server limit change affects Session state' );
	$entries = $registry->account_providers();
	$entries[$uri]['server']->test = false;
	$assert( $projector->build( $users[0], $transport, $core )->capabilities->{$uri}->test, 'Registry metadata isolated from caller mutation' );
	$assert( isset( $full->capabilities->{$uri}, $full->accounts->{$ids[2]}->accountCapabilities->{$uri} ) && ! $full->accounts->{$ids[2]}->isReadOnly, 'Explicit provider supports writable Account' );
	$assert( ! isset( $full->accounts->{$ids[0]}->accountCapabilities->{$uri} ) && $full->accounts->{$ids[0]}->isReadOnly, 'Per-Account feature grant not inferred from installation' );
	$assert( '{}' === json_encode( $full->primaryAccounts ), 'No arbitrary primary Account' );
	axismundi_actors_set_default_account( $users[0], 'test', $fixtures[2] );
	$defaulted = $projector->build( $users[0], $transport, $core );
	$assert( $ids[2] === $defaulted->primaryAccounts->{$uri} && $full->state !== $defaulted->state, 'Eligible explicit default changes Session state' );
	axismundi_actors_set_default_account( $users[0], 'test', $fixtures[0] );
	$assert( ! isset( $projector->build( $users[0], $transport, $core )->primaryAccounts->{$uri} ), 'Unsupported default omitted, not silently replaced' );
	$enabled = false;
	$off = $projector->build( $users[0], $transport, $core );
	$assert( $off->accounts->{$ids[2]}->isReadOnly && ! isset( $off->accounts->{$ids[2]}->accountCapabilities->{$uri} ) && $off->state !== $full->state, 'Feature disable removes grant and changes state' );
	$enabled = true;
	axismundi_actors_write_manager_row( $fixtures[2], $users[1], 'editor' );
	wp_set_current_user( $users[1] );
	$delegated = $projector->build( $users[1], $transport, $core );
	$assert( 1 === count( get_object_vars( $delegated->accounts ) ) && ! $delegated->accounts->{$ids[2]}->isPersonal && $delegated->accounts->{$ids[2]}->isReadOnly, 'Delegated User sees only granted, read-only Account' );
	$resolver = array( $projector, 'account' );
	$assert( null === $projector->account( 'a01', $users[1] ), 'Single Account resolver rejects malformed IDs' );
	$assert( ! isset( $projector->account( $ids[2], $users[1] )->accountCapabilities->{CORE} ), 'Core is not an Account capability' );
	$context = new Context( $users[1], new stdClass(), $resolver );
	$assert( $context->authorize( $ids[2], $uri, false )->isReadOnly, 'Engine resolver consumes same Account projection' );
	try {
		$context->authorize( $ids[2], $uri, true );
		$assert( false, 'Read-only write must fail' );
	} catch ( MethodError $error ) {
		$assert( 'accountReadOnly' === $error->type, 'Read-only write rejected by engine' );
	}
	$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $fixtures[2], 'user_id' => $users[1] ) );
	$revoked = $projector->build( $users[1], $transport, $core );
	$assert( '{}' === json_encode( $revoked->accounts ) && $delegated->state !== $revoked->state, 'Revocation removes Account and changes state immediately' );
	try {
		$context->authorize( $ids[2], $uri, false );
		$assert( false, 'Revoked read must fail' );
	} catch ( MethodError $error ) {
		$assert( 'accountNotFound' === $error->type, 'Existing resolver rechecks revocation rather than holding Session snapshot' );
	}
	wp_set_current_user( $users[0] );
	$before = $projector->build( $users[0], $transport, $core );
	$wpdb->update( axismundi_actors_actors_table(), array( 'display_name' => 'Renamed Account' ), array( 'identity_id' => $fixtures[0] ) );
	$renamed = $projector->build( $users[0], $transport, $core );
	$assert( 'Renamed Account' === $renamed->accounts->{$ids[0]}->name && $before->state !== $renamed->state, 'Display name changes Session state' );
	axismundi_actors_set_status( $fixtures[2], 'disabled' );
	$assert( ! isset( $projector->build( $users[0], $transport, $core )->accounts->{$ids[2]} ), 'Disabled Account excluded' );
	$engine = new Engine( $registry );
	$latest = $projector->build( $users[0], $transport, $core );
	$response = $engine->execute( json_encode( array( 'using' => array( CORE ), 'methodCalls' => array( array( 'Core/echo', new stdClass(), 'c' ) ) ) ), $users[0], $latest->state, $resolver );
	$assert( $latest->state === $response->sessionState, 'Response sessionState uses current projected state' );
	$assert( '{}' === json_encode( $projector->accounts( $users[0] )->primaryAccounts ), 'No primary remains for disabled Account' );
	printf( "PASS: %d Session checks\n", $checks );
} finally {
	wp_set_current_user( $original_user );
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
