<?php
/** Explicit Todo PoC provisioning and JMAP-owned Principal discovery. */
namespace Axismundi\Jmap\TodoPoc;

defined( 'ABSPATH' ) || exit;

use Axismundi\Jmap\{AccountCapability, Context, MethodError, Registry};
use function Axismundi\Jmap\{account_actor, account_actors, account_policy, copy_json, runtime_registry, server, set_account_policy};

/** Capability availability is policy AND resource access, not an Actor manager grant. */
function grant( string $account, int $user ) : ?AccountCapability {
	if ( '1' !== get_option( 'ax_jmap_todo_poc_version' ) || ! live_account( $account ) || ! user_can( $user, 'read' ) ) { return null; }
	global $wpdb;
	$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT account_id FROM %i WHERE account_id=%s', table( 'accounts' ), $account ) );
	if ( null === $exists ) { return null; }
	if ( operator( $account, $user ) ) { return new AccountCapability( new \stdClass(), true ); }
	$lists = visible_lists( $account, $user );
	if ( ! $lists ) { return null; }
	$writable = false;
	foreach ( $lists as $row ) { $r = rights( $row, $user ); $writable = $writable || $r->mayWrite || $r->mayAdmin; }
	// isSubscribed is a per-user preference even on read-only content.
	return new AccountCapability( new \stdClass(), $writable );
}

function available( string $account, int $user ) : bool {
	$actor = account_actor( $account );
	$policy = $actor ? account_policy( $actor->get_identity_id(), CAPABILITY ) : null;
	return $policy && $policy->enabled && null !== grant( $account, $user );
}

function shared_accounts( int $user, bool $subscribed_only ) : array {
	if ( '1' !== get_option( 'ax_jmap_todo_poc_version' ) ) { return array(); }
	global $wpdb;
	$accounts = $wpdb->get_col( 'SELECT account_id FROM ' . table( 'accounts' ) . ' ORDER BY account_id' );
	$result = array();
	foreach ( $accounts as $account ) {
		if ( ! available( $account, $user ) ) { continue; }
		if ( operator( $account, $user ) ) {
			// The system Account's capability-based owner need not have an Actors grant row.
			if ( null === \Axismundi\Jmap\resolve_account_actor( $account, $user ) ) { $result[] = $account; }
			continue;
		}
		foreach ( visible_lists( $account, $user ) as $list ) {
			if ( ! $subscribed_only || subscribed( $list['id'], $user, $account ) ) { $result[] = $account; break; }
		}
	}
	return $result;
}

/** This is an explicit administrator operation, not a read-side entitlement repair. */
function enable_account( string $account ) {
	if ( ! current_user_can( 'manage_options' ) || ! live_account( $account ) ) { return new \WP_Error( 'ax_jmap_poc_forbidden', 'Site administration and a live Account are required.' ); }
	try {
		install();
		provision_todo_account( $account );
		$id = account_actor( $account )->get_identity_id();
		foreach ( array( CAPABILITY, PRINCIPALS ) as $uri ) {
			$result = set_account_policy( $id, $uri, true, new \stdClass(), runtime_registry() );
			if ( true !== $result ) { return $result; }
		}
		return true;
	} catch ( \Throwable $error ) { return new \WP_Error( 'ax_jmap_poc_storage', 'Todo provisioning failed.' ); }
}

function account_options() : array {
	$result = array();
	$actors = account_actors( get_current_user_id() );
	foreach ( shared_accounts( get_current_user_id(), false ) as $account ) { $actors[ $account ] = account_actor( $account ); }
	// Site administrators can enable the system Account without gaining an Actor grant.
	if ( current_user_can( 'manage_options' ) && function_exists( 'axismundi_actors_get_system_actor' ) ) {
		$system = axismundi_actors_get_system_actor();
		if ( $system ) { $actors[ 'a' . $system->get_identity_id() ] = $system; }
	}
	foreach ( $actors as $id => $actor ) {
		if ( ! live_account( $id ) ) { continue; }
		$result[] = array( 'id' => $id, 'name' => $actor->get_display_name(), 'canEnable' => current_user_can( 'manage_options' ), 'canCreate' => operator( $id, get_current_user_id() ), 'enabled' => available( $id, get_current_user_id() ) );
	}
	return $result;
}

function directory_account() : ?string {
	foreach ( account_options() as $option ) { if ( $option['enabled'] ) { return $option['id']; } }
	return null;
}

function decorate_account( \stdClass $object, \Axismundi_Actor $actor, int $user ) : \stdClass {
	if ( ! isset( $object->accountCapabilities->{CAPABILITY} ) ) { return $object; }
	$id = principal_for_account( 'a' . $actor->get_identity_id() );
	if ( null !== $id ) {
		$object->accountCapabilities->{PRINCIPAL_OWNER} = (object) array( 'accountIdForPrincipal' => directory_account() ?? 'a' . $actor->get_identity_id(), 'principalId' => $id );
	}
	return $object;
}

function principal_objects( int $user ) : array {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT * FROM ' . table( 'principals' ) . ' ORDER BY id', ARRAY_A );
	$result = array();
	foreach ( $rows as $row ) {
		$person = $row['user_id'] ? get_userdata( (int) $row['user_id'] ) : null;
		$actor = $row['actor_id'] ? account_actor( 'a' . $row['actor_id'] ) : null;
		if ( ! $person && ( ! $actor || ! live_account( 'a' . $row['actor_id'] ) ) ) { continue; }
		$accounts = new \stdClass();
		$associated = $person ? account_actors( (int) $person->ID ) : array( 'a' . $row['actor_id'] => $actor );
		foreach ( $associated as $id => $candidate ) {
			if ( $person && ! axismundi_actors_account_is_personal( $candidate, (int) $person->ID ) ) { continue; }
			if ( available( $id, $user ) ) { $accounts->{$id} = server()->authorize( $id, CAPABILITY ); }
		}
		$ids = array_keys( get_object_vars( $accounts ) );
		$result[] = (object) array( 'id' => $row['id'], 'type' => $person ? 'individual' : 'other',
			'name' => $person ? $person->display_name : $actor->get_display_name(), 'description' => null,
			'email' => $person && (int) $person->ID === $user ? $person->user_email : null, 'timeZone' => null,
			'capabilities' => (object) array( CAPABILITY => (object) array( 'accountId' => $ids[0] ?? null, 'mayShareWith' => (bool) $person ) ),
			'accounts' => $ids ? $accounts : null );
	}
	return $result;
}

function principal_state( string $account, int $user, ?array $objects = null ) : string {
	return hash_hmac( 'sha256', $account . ':' . $user . ':' . wp_json_encode( $objects ?? principal_objects( $user ) ), wp_salt( 'auth' ) );
}

function principal_get( \stdClass $args, Context $context ) : \stdClass {
	$objects = principal_objects( $context->user_id );
	$indexed = array();
	foreach ( $objects as $o ) { $indexed[ $o->id ] = $o; }
	$ids = $args->ids ?? array_keys( $indexed );
	if ( ! is_array( $ids ) || ! array_is_list( $ids ) ) { throw new MethodError( 'invalidArguments' ); }
	$list = $missing = array();
	foreach ( $ids as $id ) {
		if ( ! \Axismundi\Jmap\valid_id( $id ) ) { throw new MethodError( 'invalidArguments' ); }
		if ( isset( $indexed[ $id ] ) ) { $list[] = select_properties( $indexed[ $id ], $args->properties ?? null ); } else { $missing[] = $id; }
	}
	return (object) array( 'accountId' => $args->accountId, 'state' => principal_state( $args->accountId, $context->user_id, $objects ), 'list' => $list, 'notFound' => $missing );
}

function register( Registry $registry ) : void {
	foreach ( array( CAPABILITY, PRINCIPALS, PRINCIPAL_OWNER ) as $uri ) { $registry->register_capability( $uri ); }
	foreach ( array( 'Todo', 'TodoList' ) as $type ) {
		$registry->register_type( $type, CAPABILITY, 'https://jmap.io/spec/rfc9670/#section-4.1' );
		$registry->register_method( $type . '/get', CAPABILITY, static fn( $a, $c ) => read_method( $a, $c, $type ), true );
		$registry->register_method( $type . '/set', CAPABILITY, static fn( $a, $c ) => set_objects( $a, $c, $type ), true );
		$registry->register_method( $type . '/changes', CAPABILITY, static fn( $a, $c ) => read_method( $a, $c, $type, true ), true );
	}
	$registry->register_type( 'Principal', PRINCIPALS, 'https://jmap.io/spec/rfc9670/#section-2' );
	$registry->register_method( 'Principal/get', PRINCIPALS, __NAMESPACE__ . '\\principal_get', true );
	$registry->register_method( 'Principal/changes', PRINCIPALS, static function ( $a, $c ) {
		$current = principal_state( $a->accountId, $c->user_id );
		if ( ! is_string( $a->sinceState ?? null ) ) { throw new MethodError( 'invalidArguments' ); }
		if ( ! hash_equals( $current, $a->sinceState ) ) { throw new MethodError( 'cannotCalculateChanges' ); }
		return (object) array( 'accountId' => $a->accountId, 'oldState' => $current, 'newState' => $current, 'hasMoreChanges' => false, 'created' => array(), 'updated' => array(), 'destroyed' => array() );
	}, true );
	$registry->register_account_provider( CAPABILITY, new \stdClass(), static fn( $actor, $user ) => grant( 'a' . $actor->get_identity_id(), $user ), 'todo_poc', null, __NAMESPACE__ . '\\shared_accounts' );
	$registry->register_account_provider( PRINCIPALS, new \stdClass(), static fn( $actor, $user ) => available( 'a' . $actor->get_identity_id(), $user ) ? new AccountCapability( new \stdClass(), false ) : null, 'principals_poc' );
}

/** Admin transport exercises the same registry, engine and live Account resolver. */
function execute( string $json ) : \stdClass {
	return \Axismundi\Jmap\execute_json( $json );
}

function register_user( int $user ) : void {
	if ( '1' === get_option( 'ax_jmap_todo_poc_version' ) ) { principal_for_user( $user, true ); }
}

function delete_user( int $user ) : void {
	if ( '1' !== get_option( 'ax_jmap_todo_poc_version' ) ) { return; }
	global $wpdb;
	$principal = principal_for_user( $user );
	if ( null !== $principal ) {
		foreach ( $wpdb->get_col( 'SELECT account_id FROM ' . table( 'accounts' ) ) as $account ) {
			begin( $account );
			try {
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE account_id=%s', table( 'lists' ), $account ), ARRAY_A );
				foreach ( $rows as $row ) {
					$shares = json_decode( $row['shares_json'], false, 32, JSON_THROW_ON_ERROR );
					if ( ! $shares instanceof \stdClass || ! property_exists( $shares, $principal ) ) { continue; }
					unset( $shares->{$principal} );
					if ( false === $wpdb->update( table( 'lists' ), array( 'shares_json' => wp_json_encode( get_object_vars( $shares ) ? $shares : null ) ), array( 'id' => $row['id'] ) ) ) { throw new \RuntimeException( 'Share cleanup failed.' ); }
					touch( $account, 'TodoList', $row['id'], 'update' );
					bump_acl_revision( $account );
				}
				commit();
			} catch ( \Throwable $error ) { rollback(); throw $error; }
		}
	}
	$wpdb->delete( table( 'principals' ), array( 'user_id' => $user ) );
	$wpdb->delete( table( 'subscriptions' ), array( 'user_id' => $user ) );
}

add_action( 'axismundi_jmap_register', __NAMESPACE__ . '\\register' );
add_filter( 'axismundi_jmap_account_projection', __NAMESPACE__ . '\\decorate_account', 10, 3 );
add_action( 'user_register', __NAMESPACE__ . '\\register_user' );
add_action( 'deleted_user', __NAMESPACE__ . '\\delete_user' );
