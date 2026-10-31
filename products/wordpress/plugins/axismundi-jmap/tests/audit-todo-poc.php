<?php
/** Real Todo PoC envelope and isolated sharing fixtures. Run with wp eval-file. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
use const Axismundi\Jmap\CORE;
use const Axismundi\Jmap\TodoPoc\{CAPABILITY, PRINCIPALS};
use function Axismundi\Jmap\TodoPoc\{execute, enable_account, install, table, principal_for_user};
use function Axismundi\Jmap\server;

global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $label ); }
};
$initial_user = get_current_user_id();
$cookies = $_COOKIE;
$users = $actors = array();
$network = static fn() => new WP_Error( 'audit_no_http', 'No HTTP during Todo audit.' );
add_filter( 'pre_http_request', $network );
// Explicit additive installation is permitted; retain its schema version marker.
install();
$snapshot = static function () use ( $wpdb ) : array {
	$result = array();
	$tables = array( axismundi_actors_actors_table(), axismundi_actors_managers_table(), axismundi_actors_meta_table(), axismundi_actors_addresses_table(), axismundi_actors_signing_keys_table(), axismundi_actors_keys_table(), axismundi_actors_endpoints_table(), axismundi_actors_texts_table(), axismundi_actors_fetch_state_table(), axismundi_actors_asset_cache_table(), axismundi_actors_identity_relations_table(), \Axismundi\Jmap\accounts_table(), \Axismundi\Jmap\policies_table() );
	foreach ( array( 'principals', 'accounts', 'lists', 'todos', 'subscriptions', 'changes' ) as $suffix ) { $tables[] = table( $suffix ); }
	foreach ( $tables as $name ) {
		$rows = $wpdb->get_results( "SELECT * FROM {$name}", ARRAY_A );
		if ( $wpdb->last_error ) { throw new RuntimeException( 'Snapshot failed: ' . $name ); }
		$result[ $name ] = array_map( static fn( $row ) => json_encode( $row, JSON_THROW_ON_ERROR ), $rows );
		sort( $result[ $name ], SORT_STRING );
	}
	return $result;
};
$before = $snapshot();
$principal_ids = $wpdb->get_col( 'SELECT id FROM ' . table( 'principals' ) );
$batch = static function ( array $calls ) : stdClass {
	return execute( json_encode( (object) array( 'using' => array( CORE, CAPABILITY, PRINCIPALS ), 'methodCalls' => $calls ), JSON_THROW_ON_ERROR ) );
};
$call = static function ( string $method, array $args ) use ( $batch, $assert ) : stdClass {
	$response = $batch( array( array( $method, (object) $args, 'audit' ) ) );
	$assert( isset( $response->methodResponses[0] ) && 'audit' === $response->methodResponses[0][2], 'Real envelope preserves call ID' );
	return $response->methodResponses[0][1];
};
try {
	foreach ( array( 'subscriber', 'subscriber', 'subscriber', 'administrator' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'axtodo' . wp_generate_password( 12, false, false ), 'user_pass' => wp_generate_password(), 'role' => $role ) );
		$assert( ! is_wp_error( $id ), 'Fixture user created' );
		$users[] = (int) $id;
	}
	[ $a, $b, $c, $admin ] = $users;
	foreach ( array( $a, $b, $c ) as $user ) {
		$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'initial_owner_user_id' => $user, 'personal_owner_user_id' => $user, 'display_name' => 'Todo audit' ) );
		$assert( $actor instanceof Axismundi_Actor, 'Fixture Actor created' );
		$actors[] = $actor->get_identity_id();
	}
	[ $aa, $ab, $ac ] = array_map( static fn( $id ) => 'a' . $id, $actors );
	wp_set_current_user( $a );
	$assert( is_wp_error( enable_account( $aa ) ), 'Subscriber cannot enable entitlement' );
	wp_set_current_user( $admin );
	$assert( true === enable_account( $aa ) && true === enable_account( $ab ), 'Administrator explicitly enables A and B' );
	$pb = principal_for_user( $b );
	$assert( is_string( $pb ) && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_id FROM %i WHERE id=%s', table( 'principals' ), $pb ) ) === $b, 'B has opaque user Principal mapping' );
	wp_set_current_user( $a );
	$directory = $call( 'Principal/get', array( 'accountId' => $aa, 'ids' => array( $pb ) ) );
	$assert( $pb === ( $directory->list[0]->id ?? null ), 'Principal is discoverable through envelope' );
	$initial = $call( 'Todo/get', array( 'accountId' => $aa ) )->state;
	$response = $batch( array(
		array( 'TodoList/set', (object) array( 'accountId' => $aa, 'create' => (object) array( 'list' => (object) array( 'name' => 'Audit list' ) ) ), 'list' ),
		array( 'Todo/set', (object) array( 'accountId' => $aa, 'create' => (object) array( 'todo' => (object) array( 'todoListId' => '#list', 'title' => 'Audit todo' ) ) ), 'todo' ),
		array( 'Todo/get', (object) array( 'accountId' => $aa ), 'all' ),
		array( 'Todo/get', (object) array( 'accountId' => $aa, '#ids' => (object) array( 'resultOf' => 'all', 'name' => 'Todo/get', 'path' => '/list/*/id' ) ), 'read' ),
	) );
	$list = $response->methodResponses[0][1]->created->list->id ?? '';
	$todo = $response->methodResponses[1][1]->created->todo->id ?? '';
	$assert( '' !== $list && '' !== $todo && $list === ( $response->methodResponses[3][1]->list[0]->todoListId ?? null ), 'Creation IDs and result backreferences resolve across real calls: ' . wp_json_encode( $response ) );
	$created = $call( 'Todo/changes', array( 'accountId' => $aa, 'sinceState' => $initial ) );
	$assert( array( $todo ) === $created->created && $initial !== $created->newState, 'Create advances state and changes' );
	$updated = $call( 'Todo/set', array( 'accountId' => $aa, 'ifInState' => $created->newState, 'update' => (object) array( $todo => (object) array( 'title' => 'Updated' ) ) ) );
	$assert( property_exists( $updated->updated ?? new stdClass(), $todo ) && $updated->oldState !== $updated->newState, 'ifInState update advances state' );
	$assert( 'stateMismatch' === ( $call( 'Todo/set', array( 'accountId' => $aa, 'ifInState' => $created->newState, 'destroy' => array( $todo ) ) )->type ?? null ), 'Stale state rejects destruction' );
	$delta = $call( 'Todo/changes', array( 'accountId' => $aa, 'sinceState' => $created->newState ) );
	$assert( array( $todo ) === $delta->updated && array() === $delta->destroyed, 'Update changes exclude rejected destroy' );
	foreach ( array( 'Todo' => $todo, 'TodoList' => $list ) as $type => $object_id ) {
		$whole = $call( $type . '/get', array( 'accountId' => $aa, 'ids' => array( $object_id ) ) )->list[0];
		$echo = $call( $type . '/set', array( 'accountId' => $aa, 'update' => (object) array( $object_id => $whole ) ) );
		$assert( property_exists( $echo->updated ?? new stdClass(), $object_id ), $type . ' whole-object round-trip' );
		$bad = clone $whole;
		$bad->id = 'different';
		$rejected = $call( $type . '/set', array( 'accountId' => $aa, 'update' => (object) array( $object_id => $bad ) ) );
		$error = $rejected->notUpdated->{$object_id} ?? new stdClass();
		$assert( 'invalidProperties' === ( $error->type ?? null ) && array( 'id' ) === ( $error->properties ?? null ), $type . ' differing server-set value rejected' );
		if ( 'TodoList' === $type ) {
			$bad = \Axismundi\Jmap\copy_json( $whole );
			$bad->myRights->mayAdmin = false;
			$rejected = $call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $object_id => $bad ) ) );
			$assert( array( 'myRights' ) === ( $rejected->notUpdated->{$object_id}->properties ?? null ), 'Changed computed rights rejected' );
		}
	}
	$seed = (object) array( 'keywords' => (object) array( 'mozart' => true ) );
	$call( 'Todo/set', array( 'accountId' => $aa, 'update' => (object) array( $todo => $seed ) ) );
	$whole = $call( 'Todo/get', array( 'accountId' => $aa, 'ids' => array( $todo ) ) )->list[0];
	$call( 'Todo/set', array( 'accountId' => $aa, 'update' => (object) array( $todo => (object) array( 'keywords/chopin' => true, 'keywords/mozart' => null ) ) ) );
	$minimal = $call( 'Todo/get', array( 'accountId' => $aa, 'ids' => array( $todo ) ) )->list[0];
	$whole->keywords = (object) array( 'chopin' => true );
	$call( 'Todo/set', array( 'accountId' => $aa, 'update' => (object) array( $todo => $seed ) ) );
	$call( 'Todo/set', array( 'accountId' => $aa, 'update' => (object) array( $todo => $whole ) ) );
	$full = $call( 'Todo/get', array( 'accountId' => $aa, 'ids' => array( $todo ) ) )->list[0];
	$assert( \Axismundi\Jmap\TodoPoc\patch_values_equal( $minimal, $full ), 'Minimal and whole-object patches produce identical objects' );
	$share = (object) array( $pb => (object) array( 'mayRead' => true, 'mayWrite' => false, 'mayAdmin' => false ) );
	$shared = $call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'shareWith' => $share ) ) ) );
	$assert( property_exists( $shared->updated, $list ), 'A shares read-only with B' );
	wp_set_current_user( $b );
	$assert( ! isset( server()->accounts()->accounts->{$aa} ), 'Unsubscribed share is not in Session accounts' );
	$read = $call( 'Todo/get', array( 'accountId' => $aa, 'ids' => array( $todo ) ) );
	$assert( 'Updated' === ( $read->list[0]->title ?? null ), 'B reads A shared Todo' );
	$rights = $call( 'TodoList/get', array( 'accountId' => $aa, 'ids' => array( $list ) ) )->list[0];
	$assert( $rights->myRights->mayRead && ! $rights->myRights->mayWrite && ! $rights->myRights->mayAdmin && null === $rights->shareWith, 'B receives read-only rights without share map' );
	$denied = $call( 'Todo/set', array( 'accountId' => $aa, 'create' => (object) array( 'bad' => (object) array( 'title' => 'Denied', 'todoListId' => $list ) ), 'update' => (object) array( $todo => (object) array( 'title' => 'Denied' ) ), 'destroy' => array( $todo ) ) );
	$assert( 'forbidden' === ( $denied->notCreated->bad->type ?? null ) && 'forbidden' === ( $denied->notUpdated->{$todo}->type ?? null ) && 'forbidden' === ( $denied->notDestroyed->{$todo}->type ?? null ), 'Read-only B cannot create update or destroy Todo' );
	$elevate = $call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'shareWith' => (object) array( $pb => (object) array( 'mayRead' => true, 'mayWrite' => true, 'mayAdmin' => true ) ) ) ) ) );
	$assert( 'forbidden' === ( $elevate->notUpdated->{$list}->type ?? null ), 'B cannot elevate shares' );
	wp_set_current_user( $a );
	$writer_share = (object) array( $pb => (object) array( 'mayRead' => true, 'mayWrite' => true, 'mayAdmin' => false ) );
	$call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'shareWith' => $writer_share ) ) ) );
	$stored_shares = \Axismundi\Jmap\TodoPoc\list_row( $list, $aa )['shares_json'];
	wp_set_current_user( $b );
	$writer = $call( 'TodoList/get', array( 'accountId' => $aa, 'ids' => array( $list ) ) )->list[0];
	$assert( $writer->myRights->mayWrite && ! $writer->myRights->mayAdmin && null === $writer->shareWith, 'Writer fixture has no sharing administration' );
	$echo = $call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => $writer ) ) );
	$assert( property_exists( $echo->updated ?? new stdClass(), $list ), 'Writer without mayAdmin whole-object round-trip' );
	$assert( $stored_shares === \Axismundi\Jmap\TodoPoc\list_row( $list, $aa )['shares_json'], 'Writer echo preserves stored sharing map' );
	$writer->shareWith = $writer_share;
	$denied_share = $call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => $writer ) ) );
	$assert( 'forbidden' === ( $denied_share->notUpdated->{$list}->type ?? null ), 'Writer cannot change projected shareWith' );
	wp_set_current_user( $a );
	$call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'shareWith' => $share ) ) ) );
	wp_set_current_user( $b );
	$subscription = $call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'isSubscribed' => true ) ) ) );
	$session = server()->accounts();
	$assert( property_exists( $subscription->updated, $list ) && isset( $session->accounts->{$aa}, $session->accounts->{$ab} ) && $session->accounts->{$aa}->isReadOnly, 'Subscription exposes A alongside own B Account' );
	$assert( ! axismundi_actors_account_role( $actors[0], $b ), 'Resource sharing adds no Actors manager grant' );
	// Operating grades must not manufacture ownership of private resources.
	foreach ( array( 'manager', 'editor' ) as $grade ) {
		$assert( true === axismundi_actors_set_principal_role( $actors[0], 'wp_user', $c, $grade ), 'Fixture operating grade assigned' );
		$assert( $grade === axismundi_actors_account_role( $actors[0], $c ), 'Fixture operating grade resolves' );
		wp_set_current_user( $c );
		$assert( ! \Axismundi\Jmap\TodoPoc\operator( $aa, $c ), 'Manager/editor is not a resource owner' );
		$assert( 'accountNotSupportedByMethod' === ( $call( 'TodoList/get', array( 'accountId' => $aa ) )->type ?? null ), 'Operating grade alone grants no Todo resource access' );
		wp_set_current_user( $b );
	}
	$assert( true === axismundi_actors_set_principal_role( $actors[0], 'wp_user', $c, null ), 'Fixture operating grade revoked' );
	// B keeps L1 access, but must refetch when an item disappears into private L2.
	$reader_state = $call( 'Todo/get', array( 'accountId' => $aa ) )->state;
	wp_set_current_user( $a );
	$private = $call( 'TodoList/set', array( 'accountId' => $aa, 'create' => (object) array( 'private' => (object) array( 'name' => 'Private L2' ) ) ) );
	$private_id = $private->created->private->id ?? '';
	$assert( '' !== $private_id, 'Private destination created' );
	$move = $call( 'Todo/set', array( 'accountId' => $aa, 'update' => (object) array( $todo => (object) array( 'todoListId' => $private_id ) ) ) );
	$assert( property_exists( $move->updated, $todo ), 'A moves shared Todo into private L2' );
	wp_set_current_user( $b );
	$assert( array( $todo ) === $call( 'Todo/get', array( 'accountId' => $aa, 'ids' => array( $todo ) ) )->notFound, 'Moved private Todo is no longer visible to B' );
	$assert( $list === ( $call( 'TodoList/get', array( 'accountId' => $aa, 'ids' => array( $list ) ) )->list[0]->id ?? null ), 'B retains shared L1 access after move' );
	$assert( 'cannotCalculateChanges' === ( $call( 'Todo/changes', array( 'accountId' => $aa, 'sinceState' => $reader_state ) )->type ?? null ), 'Move invalidates B old changes token through ACL epoch' );
	foreach ( array( 'Todo' => $todo, 'TodoList' => $list ) as $type => $id ) {
		$foreign = $call( $type . '/get', array( 'accountId' => $ab, 'ids' => array( $id ) ) );
		$assert( array( $id ) === $foreign->notFound && array() === $foreign->list, 'Cross-account get is notFound' );
		$foreign = $call( $type . '/set', array( 'accountId' => $ab, 'update' => (object) array( $id => new stdClass() ), 'destroy' => array( $id ) ) );
		$assert( 'notFound' === ( $foreign->notUpdated->{$id}->type ?? null ) && 'notFound' === ( $foreign->notDestroyed->{$id}->type ?? null ), 'Cross-account mutations are notFound' );
	}
	$assert( 'invalidArguments' === ( $call( 'Todo/get', array() )->type ?? null ), 'Missing accountId cannot use own Account' );
	wp_set_current_user( $c );
	$assert( 'accountNotFound' === ( $call( 'Todo/get', array( 'accountId' => $aa ) )->type ?? null ), 'Unauthorized C cannot read A' );
	wp_set_current_user( $a );
	$pc = principal_for_user( $c );
	$assert( is_string( $pc ), 'Deletion target has a Principal' );
	$share->{$pc} = (object) array( 'mayRead' => true, 'mayWrite' => false, 'mayAdmin' => false );
	$target = $call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'shareWith' => $share ) ) ) );
	$assert( property_exists( $target->updated, $list ), 'C is explicitly shared before deletion' );
	$assert( wp_delete_user( $c ), 'Shared target C deleted' );
	$assert( null === principal_for_user( $c ), 'Deleted user Principal mapping removed' );
	$after_delete = $call( 'TodoList/get', array( 'accountId' => $aa, 'ids' => array( $list ) ) )->list[0];
	$assert( ! isset( $after_delete->shareWith->{$pc} ) && isset( $after_delete->shareWith->{$pb} ), 'Deleted C share removed while B share survives' );
	$renamed = $call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'name' => 'Renamed after deletion' ) ) ) );
	$assert( property_exists( $renamed->updated, $list ), 'Unrelated rename remains valid after shared user deletion' );
	$call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'shareWith' => null ) ) ) );
	wp_set_current_user( $b );
	$assert( ! isset( server()->accounts()->accounts->{$aa} ) && 'accountNotFound' === ( $call( 'Todo/get', array( 'accountId' => $aa ) )->type ?? null ), 'Revocation denies A immediately in the same process and session' );
	$assert( isset( $call( 'Todo/get', array( 'accountId' => $ab ) )->list ), 'Own B Account remains usable after A revocation' );
	$own = $call( 'TodoList/set', array( 'accountId' => $ab, 'create' => (object) array( 'own' => (object) array( 'name' => 'B own list' ) ) ) );
	$assert( isset( $own->created->own->id ), 'B can still write its separate own Account' );
	wp_set_current_user( $a );
	$state = $call( 'Todo/get', array( 'accountId' => $aa ) )->state;
	$destroy = $call( 'Todo/set', array( 'accountId' => $aa, 'ifInState' => $state, 'destroy' => array( $todo ) ) );
	$delta = $call( 'Todo/changes', array( 'accountId' => $aa, 'sinceState' => $state ) );
	$assert( array( $todo ) === $destroy->destroyed && $state !== $destroy->newState && array( $todo ) === $delta->destroyed && array() === $delta->created && array() === $delta->updated, 'Destroy advances state and reports exact changes' );
} finally {
	$_COOKIE = $cookies;
	wp_set_current_user( $initial_user );
	foreach ( $actors as $id ) {
		$wpdb->delete( table( 'accounts' ), array( 'account_id' => 'a' . $id ) );
		$wpdb->delete( table( 'principals' ), array( 'actor_id' => $id ) );
		$wpdb->delete( \Axismundi\Jmap\policies_table(), array( 'account_id' => $id ) );
		$wpdb->delete( \Axismundi\Jmap\accounts_table(), array( 'actor_id' => $id ) );
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_signing_keys_table(), axismundi_actors_keys_table(), axismundi_actors_endpoints_table(), axismundi_actors_addresses_table(), axismundi_actors_texts_table(), axismundi_actors_fetch_state_table(), axismundi_actors_asset_cache_table(), axismundi_actors_identity_relations_table() ) as $name ) { $wpdb->delete( $name, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_actors_table(), array( 'id' => $id ) );
	}
	foreach ( $users as $user ) { wp_delete_user( $user ); }
	// Explicit enable provisions all users: remove only IDs absent at entry.
	foreach ( $wpdb->get_col( 'SELECT id FROM ' . table( 'principals' ) ) as $id ) {
		if ( ! in_array( $id, $principal_ids, true ) ) { $wpdb->delete( table( 'principals' ), array( 'id' => $id ) ); }
	}
	remove_filter( 'pre_http_request', $network );
	$after = $snapshot();
	$changed = array_keys( array_filter( $before, static fn( $rows, $name ) => $rows !== $after[ $name ], ARRAY_FILTER_USE_BOTH ) );
	$assert( $before === $after, 'All baseline Actors, policies and PoC rows preserved after cleanup: ' . implode( ', ', $changed ) );
}
printf( "PASS: %d Todo PoC integration checks\n", $checks );
