<?php
/** RFC 8620 method patterns on the RFC 9670 sharing example, not a task standard. */
namespace Axismundi\Jmap\TodoPoc;

defined( 'ABSPATH' ) || exit;

use Axismundi\Jmap\{Context, MethodError};
use function Axismundi\Jmap\{copy_json, valid_id};

const CAPABILITY = 'urn:com.example:jmap:todo';
const PRINCIPALS = 'urn:ietf:params:jmap:principals';
const PRINCIPAL_OWNER = 'urn:ietf:params:jmap:principals:owner';

function state( string $account, string $type, int $user, ?int $revision = null ) : string {
	$body = ( null === $revision ? read_clock( $account ) : $revision ) . '.' . read_acl_revision( $account );
	$scope = $account . ':' . $type . ':' . $user . ':' . (int) operator( $account, $user );
	return $body . '.' . substr( hash_hmac( 'sha256', $scope . ':' . $body, wp_salt( 'auth' ) ), 0, 32 );
}

function since_revision( mixed $token, string $account, string $type, int $user ) : int {
	if ( ! is_string( $token ) ) { throw new MethodError( 'invalidArguments' ); }
	if ( ! preg_match( '/\A(0|[1-9][0-9]*)\.[0-9]+\.[a-f0-9]{32}\z/D', $token, $m )
		|| (int) $m[1] > read_clock( $account ) || ! hash_equals( state( $account, $type, $user, (int) $m[1] ), $token ) ) {
		throw new MethodError( 'cannotCalculateChanges' );
	}
	return (int) $m[1];
}

function select_properties( \stdClass $object, mixed $properties ) : \stdClass {
	if ( null === $properties ) { return $object; }
	if ( ! is_array( $properties ) || ! array_is_list( $properties ) ) { throw new MethodError( 'invalidArguments' ); }
	$result = (object) array( 'id' => $object->id );
	foreach ( $properties as $key ) {
		if ( ! is_string( $key ) || ! property_exists( $object, $key ) ) { throw new MethodError( 'invalidArguments' ); }
		$result->{$key} = $object->{$key};
	}
	return $result;
}

function subscribed( string $id, int $user, string $account ) : bool {
	global $wpdb;
	$value = $wpdb->get_var( $wpdb->prepare( 'SELECT subscribed FROM %i WHERE list_id=%s AND user_id=%d', table( 'subscriptions' ), $id, $user ) );
	check_db();
	return null === $value ? operator( $account, $user ) : (bool) $value;
}

function list_object( array $row, int $user ) : \stdClass {
	$rights = rights( $row, $user );
	return (object) array( 'id' => $row['id'], 'name' => $row['name'],
		'isSubscribed' => subscribed( $row['id'], $user, $row['account_id'] ), 'myRights' => $rights,
		'shareWith' => $rights->mayAdmin ? json_decode( $row['shares_json'], false, 32, JSON_THROW_ON_ERROR ) : null );
}

function todo_object( array $row ) : \stdClass {
	return (object) array( 'id' => $row['id'], 'todoListId' => $row['list_id'], 'title' => $row['title'],
		'keywords' => json_decode( $row['keywords_json'], false, 32, JSON_THROW_ON_ERROR ) );
}

function visible_objects( string $account, string $type, int $user ) : array {
	global $wpdb;
	$lists = visible_lists( $account, $user );
	if ( 'TodoList' === $type ) { return array_map( static fn( $r ) => list_object( $r, $user ), $lists ); }
	$allowed = array_column( $lists, 'id' );
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE account_id=%s ORDER BY id', table( 'todos' ), $account ), ARRAY_A );
	check_db();
	return array_map( __NAMESPACE__ . '\\todo_object', array_values( array_filter( $rows, static fn( $r ) => in_array( $r['list_id'], $allowed, true ) ) ) );
}

function get_objects( \stdClass $args, Context $context, string $type ) : \stdClass {
	$objects = visible_objects( $args->accountId, $type, $context->user_id );
	$indexed = array();
	foreach ( $objects as $object ) { $indexed[ $object->id ] = $object; }
	$ids = $args->ids ?? array_keys( $indexed );
	if ( ! is_array( $ids ) || ! array_is_list( $ids ) ) { throw new MethodError( 'invalidArguments' ); }
	$list = $missing = array();
	foreach ( $ids as $id ) {
		if ( ! valid_id( $id ) ) { throw new MethodError( 'invalidArguments' ); }
		if ( isset( $indexed[ $id ] ) ) { $list[] = select_properties( $indexed[ $id ], $args->properties ?? null ); }
		else { $missing[] = $id; }
	}
	return (object) array( 'accountId' => $args->accountId, 'state' => state( $args->accountId, $type, $context->user_id ), 'list' => $list, 'notFound' => $missing );
}

/** The returned snapshot and its token must describe the same committed data. */
function read_method( \stdClass $args, Context $context, string $type, bool $delta = false ) : \stdClass {
	begin( $args->accountId );
	try {
		\Axismundi\Jmap\server()->authorize( $args->accountId, CAPABILITY );
		$result = $delta ? changes( $args, $context, $type ) : get_objects( $args, $context, $type );
		commit();
		return $result;
	} catch ( \Throwable $error ) { rollback(); throw $error; }
}

/** Return only complete change sets; clients can refetch when ACLs changed. */
function changes( \stdClass $args, Context $context, string $type ) : \stdClass {
	global $wpdb;
	$account = $args->accountId;
	$since = since_revision( $args->sinceState ?? null, $account, $type, $context->user_id );
	$now = read_clock( $account );
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE account_id=%s AND type=%s AND revision>%d AND revision<=%d ORDER BY revision', table( 'changes' ), $account, $type, $since, $now ), ARRAY_A );
	check_db();
	$aggregate = array();
	foreach ( $rows as $row ) {
		$id = $row['object_id'];
		if ( ! isset( $aggregate[ $id ] ) ) { $aggregate[ $id ] = array( 'first' => $row['operation'], 'last' => $row['operation'] ); }
		$aggregate[ $id ]['last'] = $row['operation'];
	}
	$visible = array_column( visible_objects( $account, $type, $context->user_id ), 'id' );
	$result = (object) array( 'accountId' => $account, 'oldState' => $args->sinceState, 'newState' => state( $account, $type, $context->user_id, $now ), 'hasMoreChanges' => false, 'created' => array(), 'updated' => array(), 'destroyed' => array() );
	foreach ( $aggregate as $id => $event ) {
		if ( 'create' === $event['first'] && 'destroy' === $event['last'] ) { continue; }
		// Destruction IDs are safe only for operators: a reader may never have seen the object.
		if ( 'destroy' === $event['last'] ) {
			if ( ! operator( $account, $context->user_id ) ) { throw new MethodError( 'cannotCalculateChanges' ); }
			$result->destroyed[] = $id;
		} elseif ( in_array( $id, $visible, true ) ) {
			$key = 'create' === $event['first'] ? 'created' : 'updated';
			$result->{$key}[] = $id;
		}
	}
	if ( isset( $args->maxChanges ) && ( ! is_int( $args->maxChanges ) || $args->maxChanges < 1 ) ) { throw new MethodError( 'invalidArguments' ); }
	if ( isset( $args->maxChanges ) && count( $result->created ) + count( $result->updated ) + count( $result->destroyed ) > $args->maxChanges ) { throw new MethodError( 'tooManyChanges' ); }
	return $result;
}

/** Compare JSON values without depending on object member order or scalar coercion. */
function patch_values_equal( mixed $left, mixed $right ) : bool {
	if ( $left instanceof \stdClass && $right instanceof \stdClass ) {
		$a = get_object_vars( $left );
		$b = get_object_vars( $right );
		if ( count( $a ) !== count( $b ) ) { return false; }
		foreach ( $a as $key => $value ) {
			if ( ! array_key_exists( $key, $b ) || ! patch_values_equal( $value, $b[ $key ] ) ) { return false; }
		}
		return true;
	}
	if ( is_array( $left ) && is_array( $right ) ) {
		if ( array_keys( $left ) !== array_keys( $right ) ) { return false; }
		foreach ( $left as $key => $value ) { if ( ! patch_values_equal( $value, $right[ $key ] ) ) { return false; } }
		return true;
	}
	return $left === $right;
}

/** Server-set echoes are compared against the immutable pre-patch object. */
function patch( \stdClass $old, \stdClass $updates, array $allowed, array $server_set = array() ) : \stdClass {
	$before = copy_json( $old );
	$old = copy_json( $old );
	$keys = array_keys( get_object_vars( $updates ) );
	foreach ( $keys as $path ) {
		foreach ( $keys as $other ) { if ( $other !== $path && str_starts_with( $other, $path . '/' ) ) { throw new MethodError( 'invalidPatch' ); } }
		if ( preg_match( '/~(?![01])/', $path ) ) { throw new MethodError( 'invalidPatch' ); }
		$parts = array_map( static fn( $s ) => str_replace( array( '~1', '~0' ), array( '/', '~' ), $s ), explode( '/', $path ) );
		if ( ! in_array( $parts[0], $allowed, true ) ) {
			$current = $before;
			$exists = in_array( $parts[0], $server_set, true );
			foreach ( $parts as $part ) {
				if ( ! $current instanceof \stdClass || ! property_exists( $current, $part ) ) { $exists = false; break; }
				$current = $current->{$part};
			}
			if ( $exists && patch_values_equal( $current, $updates->{$path} ) ) { continue; }
			throw new MethodError( 'invalidProperties', $path, array( $path ) );
		}
		$target = $old;
		$last = array_pop( $parts );
		foreach ( $parts as $part ) {
			if ( ! ( $target->{$part} ?? null ) instanceof \stdClass ) { throw new MethodError( 'invalidPatch' ); }
			$target = $target->{$part};
		}
		if ( null === $updates->{$path} && count( explode( '/', $path ) ) > 1 ) { unset( $target->{$last} ); }
		else { $target->{$last} = copy_json( $updates->{$path} ); }
	}
	return $old;
}

function validate_shares( mixed $shares, string $account ) : void {
	if ( null === $shares ) { return; }
	if ( ! $shares instanceof \stdClass ) { throw new MethodError( 'invalidProperties', 'shareWith' ); }
	foreach ( $shares as $id => $grant ) {
		$row = get_principal_row( (string) $id );
		if ( ! $row || empty( $row['user_id'] ) || ! get_userdata( (int) $row['user_id'] ) || (string) $id === principal_for_account( $account )
			|| ! $grant instanceof \stdClass || array_diff( array_keys( get_object_vars( $grant ) ), array( 'mayRead', 'mayWrite', 'mayAdmin' ) ) ) { throw new MethodError( 'invalidProperties', 'shareWith' ); }
		foreach ( array( 'mayRead', 'mayWrite', 'mayAdmin' ) as $key ) { if ( ! is_bool( $grant->{$key} ?? null ) ) { throw new MethodError( 'invalidProperties', 'shareWith' ); } }
	}
}

function save_object( string $account, string $type, ?string $id, \stdClass $values, Context $context ) : \stdClass {
	global $wpdb;
	$user = $context->user_id;
	$create = null === $id;
	$id ??= ( 'TodoList' === $type ? 'L' : 'T' ) . str_replace( '-', '', wp_generate_uuid4() );
	if ( 'TodoList' === $type ) {
		$existing = $create ? null : list_row( $id, $account );
		if ( ! $create && ( ! $existing || ! rights( $existing, $user )->mayRead ) ) { throw new MethodError( 'notFound' ); }
		if ( $create && ! operator( $account, $user ) ) { throw new MethodError( 'forbidden' ); }
		$current_rights = $existing ? rights( $existing, $user ) : (object) array( 'mayRead' => true, 'mayWrite' => true, 'mayAdmin' => true );
		$old = $existing ? list_object( $existing, $user ) : (object) array( 'name' => '', 'shareWith' => null, 'isSubscribed' => true );
		$new = patch( copy_json( $old ), $values, array( 'name', 'shareWith', 'isSubscribed' ), $create ? array() : array( 'id', 'myRights' ) );
		// Echoing the caller's projection is not a request to change its hidden fields.
		if ( ( ! $current_rights->mayWrite && ! patch_values_equal( $old->name, $new->name ) ) || ( ! $current_rights->mayAdmin && ! patch_values_equal( $old->shareWith, $new->shareWith ) ) ) { throw new MethodError( 'forbidden' ); }
		if ( ! is_string( $new->name ) || '' === trim( $new->name ) || strlen( $new->name ) > 255 || ! is_bool( $new->isSubscribed ) ) { throw new MethodError( 'invalidProperties' ); }
		if ( $create || wp_json_encode( $new->shareWith ) !== wp_json_encode( $old->shareWith ) ) { validate_shares( $new->shareWith, $account ); }
		$shares = wp_json_encode( $new->shareWith );
		// A non-admin sees null, but cannot erase the stored sharing map by renaming.
		if ( ! $current_rights->mayAdmin && $existing ) { $shares = $existing['shares_json']; }
		$changed_shares = $existing && $shares !== $existing['shares_json'];
		$data = array( 'account_id' => $account, 'name' => $new->name, 'shares_json' => $shares, 'revision' => read_clock( $account ) + 1 );
		$ok = $create ? $wpdb->insert( table( 'lists' ), array_merge( array( 'id' => $id ), $data ) ) : $wpdb->update( table( 'lists' ), $data, array( 'id' => $id, 'account_id' => $account ) );
		if ( false === $ok ) { throw new \RuntimeException( 'TodoList write failed.' ); }
		if ( $create || $old->isSubscribed !== $new->isSubscribed ) {
			if ( false === $wpdb->replace( table( 'subscriptions' ), array( 'list_id' => $id, 'user_id' => $user, 'subscribed' => (int) $new->isSubscribed ) ) ) { throw new \RuntimeException( 'Subscription write failed.' ); }
			if ( ! $create ) { bump_acl_revision( $account ); }
		}
		if ( $changed_shares ) { bump_acl_revision( $account ); }
	} else {
		$row = $create ? null : $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE account_id=%s AND id=%s', table( 'todos' ), $account, $id ), ARRAY_A );
		$source = $row ? list_row( $row['list_id'], $account ) : null;
		if ( ! $create && ( ! $row || ! $source || ! rights( $source, $user )->mayRead ) ) { throw new MethodError( 'notFound' ); }
		if ( $source && ! rights( $source, $user )->mayWrite ) { throw new MethodError( 'forbidden' ); }
		$new = patch( $row ? todo_object( $row ) : (object) array( 'title' => '', 'keywords' => new \stdClass(), 'todoListId' => '' ), $values, array( 'title', 'keywords', 'todoListId' ), $create ? array() : array( 'id' ) );
		if ( ! is_string( $new->todoListId ) || ! is_string( $new->title ) || '' === trim( $new->title ) || strlen( $new->title ) > 10000 || ! $new->keywords instanceof \stdClass ) { throw new MethodError( 'invalidProperties' ); }
		$destination_id = $context->resolve_id( $new->todoListId );
		$destination = $destination_id ? list_row( $destination_id, $account ) : null;
		if ( ! $destination || ! rights( $destination, $user )->mayRead || ! rights( $destination, $user )->mayWrite ) { throw new MethodError( 'forbidden' ); }
		foreach ( $new->keywords as $key => $value ) { if ( true !== $value || '' === (string) $key ) { throw new MethodError( 'invalidProperties', 'keywords' ); } }
		$data = array( 'account_id' => $account, 'list_id' => $destination_id, 'title' => $new->title, 'keywords_json' => wp_json_encode( $new->keywords ), 'revision' => read_clock( $account ) + 1 );
		$ok = $create ? $wpdb->insert( table( 'todos' ), array_merge( array( 'id' => $id ), $data ) ) : $wpdb->update( table( 'todos' ), $data, array( 'account_id' => $account, 'id' => $id ) );
		if ( false === $ok ) { throw new \RuntimeException( 'Todo write failed.' ); }
		if ( $row && $row['list_id'] !== $destination_id ) { bump_acl_revision( $account ); }
	}
	touch( $account, $type, $id, $create ? 'create' : 'update' );
	return $create ? (object) array( 'id' => $id ) : new \stdClass();
}

function destroy_object( string $account, string $type, string $id, int $user ) : void {
	global $wpdb;
	if ( 'TodoList' === $type ) {
		$row = list_row( $id, $account );
		if ( ! $row || ! rights( $row, $user )->mayRead ) { throw new MethodError( 'notFound' ); }
		if ( ! rights( $row, $user )->mayAdmin ) { throw new MethodError( 'forbidden' ); }
		$children = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE account_id=%s AND list_id=%s', table( 'todos' ), $account, $id ) );
		foreach ( $children as $child ) { touch( $account, 'Todo', $child, 'destroy' ); }
		$target = table( 'lists' );
	} else {
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE account_id=%s AND id=%s', table( 'todos' ), $account, $id ), ARRAY_A );
		$list = $row ? list_row( $row['list_id'], $account ) : null;
		if ( ! $list || ! rights( $list, $user )->mayRead ) { throw new MethodError( 'notFound' ); }
		if ( ! rights( $list, $user )->mayWrite ) { throw new MethodError( 'forbidden' ); }
		$target = table( 'todos' );
	}
	if ( false === $wpdb->delete( $target, array( 'id' => $id, 'account_id' => $account ) ) ) { throw new \RuntimeException( 'Destroy failed.' ); }
	touch( $account, $type, $id, 'destroy' );
}

function set_objects( \stdClass $args, Context $context, string $type ) : \stdClass {
	$account = $args->accountId;
	$result = (object) array( 'accountId' => $account, 'oldState' => null, 'newState' => null,
		'created' => new \stdClass(), 'updated' => new \stdClass(), 'destroyed' => array(),
		'notCreated' => new \stdClass(), 'notUpdated' => new \stdClass(), 'notDestroyed' => new \stdClass() );
	$created = array();
	begin( $account );
	try {
		// Recheck live capability and ACLs after acquiring the Account write lock.
		\Axismundi\Jmap\server()->authorize( $account, CAPABILITY );
		$result->oldState = state( $account, $type, $context->user_id );
		if ( isset( $args->ifInState ) && $args->ifInState !== $result->oldState ) { throw new MethodError( 'stateMismatch' ); }
		foreach ( array( 'create', 'update', 'destroy' ) as $operation ) {
			$entries = $args->{$operation} ?? ( 'destroy' === $operation ? array() : new \stdClass() );
			foreach ( $entries as $key => $value ) {
				$id = 'destroy' === $operation ? $value : (string) $key;
				$bucket = array( 'create' => 'notCreated', 'update' => 'notUpdated', 'destroy' => 'notDestroyed' )[ $operation ];
				global $wpdb;
				$wpdb->query( 'SAVEPOINT todo_object' );
				try {
					if ( ! valid_id( $id ) || ( 'destroy' !== $operation && ! $value instanceof \stdClass ) ) { throw new MethodError( 'invalidProperties' ); }
					if ( 'destroy' === $operation ) { destroy_object( $account, $type, $id, $context->user_id ); $result->destroyed[] = $id; }
					else {
						$saved = save_object( $account, $type, 'create' === $operation ? null : $id, $value, $context );
						$success = 'create' === $operation ? 'created' : 'updated';
						$result->{$success}->{$id} = 'create' === $operation ? $saved : null;
						if ( 'create' === $operation ) { $created[ $id ] = $saved->id; }
					}
				} catch ( MethodError $error ) {
					$wpdb->query( 'ROLLBACK TO SAVEPOINT todo_object' );
					$result->{$bucket}->{is_string( $id ) ? $id : 'invalid'} = $error->arguments();
				}
			}
		}
		$result->newState = state( $account, $type, $context->user_id );
		commit();
	} catch ( \Throwable $error ) { rollback(); throw $error; }
	foreach ( $created as $creation => $id ) { $context->record_created_id( $creation, $id ); }
	// Optional transports consume committed state; no Notifications/PWA dependency.
	try {
		do_action( 'axismundi_jmap_todo_poc_committed', $account, (object) array( 'TodoList' => state( $account, 'TodoList', $context->user_id ), 'Todo' => state( $account, 'Todo', $context->user_id ) ), $context->user_id );
	} catch ( \Throwable $observer_error ) { error_log( 'JMAP Todo observer failed: ' . get_class( $observer_error ) ); }
	return $result;
}
