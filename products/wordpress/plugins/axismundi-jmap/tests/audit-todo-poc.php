<?php
/** Real Todo PoC envelope and isolated sharing fixtures. Run with wp eval-file. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
use const Axismundi\Jmap\CORE;
use const Axismundi\Jmap\TodoPoc\{CAPABILITY, PRINCIPALS};
use function Axismundi\Jmap\TodoPoc\{execute, enable_account, install, table, principal_for_account};
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
	$assert( true === enable_account( $aa ) && true === enable_account( $ab ) && true === enable_account( $ac ), 'Administrator explicitly enables the personal Accounts' );
	$group = axismundi_actors_create_local( array( 'actor_type' => 'Group', 'initial_owner_user_id' => $b, 'display_name' => 'Todo shared recipient' ) );
	$assert( $group instanceof Axismundi_Actor, 'Non-personal sharing fixture created' );
	$actors[] = $group->get_identity_id();
	$ag = 'a' . $group->get_identity_id();
	$assert( true === enable_account( $ag ), 'Administrator explicitly provisions the non-personal Account' );
	$pg = principal_for_account( $ag );
	$pa = principal_for_account( $aa );
	$pb = principal_for_account( $ab );
	$assert( is_string( $pb ) && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT actor_id FROM %i WHERE id=%s', table( 'principals' ), $pb ) ) === $actors[1], 'B has an opaque Account Principal mapping' );
	$assert( null === \Axismundi\Jmap\TodoPoc\current_user_principal( $admin ), 'A user with no provisioned personal Account has no current Principal' );
	$assert( true === axismundi_actors_set_principal_role( $group->get_identity_id(), 'wp_user', $admin, 'owner' ), 'Actorless user can operate a non-personal Account' );
	$actorless_caps = server()->accounts()->accounts->{$ag}->accountCapabilities->{PRINCIPALS};
	$assert( property_exists( $actorless_caps, 'currentUserPrincipalId' ) && null === $actorless_caps->currentUserPrincipalId,
		'Session emits null rather than omitting or substituting a Principal for the Actorless user' );
	wp_set_current_user( $b );
	$group_caps = server()->accounts()->accounts->{$ag}->accountCapabilities;
	$assert( $pb === $group_caps->{PRINCIPALS}->currentUserPrincipalId
		&& $pg === $group_caps->{\Axismundi\Jmap\TodoPoc\PRINCIPAL_OWNER}->principalId && $pg !== $pb,
		'A managed Account does not make its owner Principal the request user' );
	$assert( true === axismundi_actors_set_principal_role( $actors[2], 'wp_user', $b, 'owner' ),
		'Grant access to a second writable Todo Account for default selection' );
	$session = server()->accounts();
	$assert( isset( $session->accounts->{$ab}->accountCapabilities->{CAPABILITY}, $session->accounts->{$ac}->accountCapabilities->{CAPABILITY} )
		&& ! property_exists( $session->primaryAccounts, CAPABILITY ), 'Multiple Todo Accounts do not imply a default' );
	$assert( true === axismundi_actors_set_default_account( $b, 'todo_poc', $actors[2] ), 'Store a non-first Todo Account as the default' );
	$session = server()->accounts();
	$assert( array( CAPABILITY => $ac ) === get_object_vars( $session->primaryAccounts )
		&& isset( $session->accounts->{$ac}->accountCapabilities->{\Axismundi\Jmap\TodoPoc\PRINCIPAL_OWNER} ),
		'Only the saved Todo primary is projected, not Core or the Principal owner marker' );

	wp_set_current_user( $admin );
	$registry = \Axismundi\Jmap\runtime_registry();
	$assert( true === \Axismundi\Jmap\set_account_policy( $actors[2], CAPABILITY, false, new stdClass(), $registry ), 'Disable Todo for the saved primary only' );
	wp_set_current_user( $b );
	$session = server()->accounts();
	$assert( isset( $session->accounts->{$ac}, $session->accounts->{$ab}->accountCapabilities->{CAPABILITY} )
		&& ! isset( $session->accounts->{$ac}->accountCapabilities->{CAPABILITY} )
		&& ! property_exists( $session->primaryAccounts, CAPABILITY ),
		'Saved Account remains visible but loses its primary when it stops advertising Todo' );

	wp_set_current_user( $admin );
	$assert( true === \Axismundi\Jmap\set_account_policy( $actors[2], CAPABILITY, true, new stdClass(), $registry ), 'Restore Todo for the saved primary' );
	wp_set_current_user( $b );
	$assert( $ac === ( server()->accounts()->primaryAccounts->{CAPABILITY} ?? null ), 'Restoring Todo restores the same stored primary' );
	$assert( true === axismundi_actors_set_principal_role( $actors[2], 'wp_user', $b, null ), 'Revoke access to the saved Todo Account' );
	$session = server()->accounts();
	$assert( ! property_exists( $session->accounts, $ac ) && isset( $session->accounts->{$ab}->accountCapabilities->{CAPABILITY} )
		&& ! property_exists( $session->primaryAccounts, CAPABILITY ), 'Revoked primary is omitted without falling back to another Todo Account' );
	wp_set_current_user( $a );
	$cap = server()->accounts()->accounts->{$aa}->accountCapabilities->{PRINCIPALS};
	$assert( property_exists( $cap, 'currentUserPrincipalId' ) && $pa === $cap->currentUserPrincipalId, 'Account capability advertises the requesting user Principal' );
	$self = $call( 'Principal/get', array( 'accountId' => $aa, 'ids' => array( $cap->currentUserPrincipalId ) ) );
	$assert( $pa === ( $self->list[0]->id ?? null ), 'Session currentUserPrincipalId resolves through Principal/get' );
	$owner_grant = (object) array( $pa => (object) array( 'mayRead' => true, 'mayWrite' => false, 'mayAdmin' => false ) );
	$refused = $call( 'TodoList/set', array( 'accountId' => $aa, 'create' => (object) array(
		'self' => (object) array( 'name' => 'Not a share', 'shareWith' => $owner_grant ),
	) ) );
	$assert( 'invalidProperties' === ( $refused->notCreated->self->type ?? null ), 'The Account owner Principal cannot be added to shareWith' );
	$shared_group = $call( 'TodoList/set', array( 'accountId' => $aa, 'create' => (object) array(
		'group' => (object) array( 'name' => 'Group share', 'shareWith' => (object) array( $pg => (object) array( 'mayRead' => true, 'mayWrite' => false, 'mayAdmin' => false ) ) ),
	) ) )->created->group->id ?? null;
	wp_set_current_user( $b );
	$assert( $shared_group && $shared_group === ( $call( 'TodoList/get', array( 'accountId' => $aa, 'ids' => array( $shared_group ) ) )->list[0]->id ?? null ),
		'A user authorized for the recipient Account can read a list shared to that Account Principal' );
	wp_set_current_user( $a );
	$call( 'TodoList/set', array( 'accountId' => $aa, 'destroy' => array( $shared_group ) ) );
	$directory = $call( 'Principal/get', array( 'accountId' => $aa, 'ids' => array( $pb ) ) );
	$assert( $pb === ( $directory->list[0]->id ?? null ), 'Principal is discoverable through envelope' );
	$recipient = $directory->list[0]->id;
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
	$share = (object) array( $recipient => (object) array( 'mayRead' => true, 'mayWrite' => false, 'mayAdmin' => false ) );
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
	$subscription_batch = $batch( array(
		array( 'TodoList/set', (object) array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'isSubscribed' => true ) ) ), 'subscribe' ),
		array( 'TodoList/set', (object) array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'name' => 'Forbidden rename' ) ) ), 'rename' ),
		array( 'TodoList/get', (object) array( 'accountId' => $aa, 'ids' => array( $list ) ), 'verify' ),
	) )->methodResponses;
	$subscription = $subscription_batch[0][1];
	$assert( 'TodoList/set' === $subscription_batch[0][0] && 'subscribe' === $subscription_batch[0][2]
		&& 'TodoList/set' === $subscription_batch[1][0] && 'rename' === $subscription_batch[1][2]
		&& 'forbidden' === ( $subscription_batch[1][1]->notUpdated->{$list}->type ?? null )
		&& 'Audit list' === ( $subscription_batch[2][1]->list[0]->name ?? null ),
		'Read-only subscriber can subscribe but cannot rename the same list in the same batch' );
	$session = server()->accounts();
	$assert( property_exists( $subscription->updated, $list ) && isset( $session->accounts->{$aa}, $session->accounts->{$ab} ) && $session->accounts->{$aa}->isReadOnly, 'Subscription exposes A alongside own B Account' );
	$assert( ! axismundi_actors_account_role( $actors[0], $b ), 'Resource sharing adds no Actors manager grant' );
	if ( function_exists( 'axismundi_act_c2s_session' ) ) {
		wp_set_current_user( $a );
		axismundi_actors_register_handle( $actors[0], get_userdata( $a )->user_login );
		axismundi_actors_set_status( $actors[0], 'public' );
		$shared_actor = axismundi_actors_get_by_identity( $actors[0] );
		$assert( true === axismundi_act_outbox_authorize( $a, $shared_actor ), 'Shared Todo Account has an activated outbox for its owner' );
		wp_set_current_user( $b );
		$ap_session = axismundi_act_c2s_session();
		$assert( ! is_wp_error( $ap_session )
			&& isset( server()->accounts()->accounts->{$aa} )
			&& ! in_array( $shared_actor->get_uri(), array_column( $ap_session['actors'], 'id' ), true ),
			'Subscribed JMAP reader sees the shared Account but cannot discover its Actor in the AP submission session' );
	}
	$list_state = $call( 'TodoList/get', array( 'accountId' => $aa ) )->state;
	wp_set_current_user( $a );
	$rename_list = $call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'name' => 'Renamed shared list' ) ) ) );
	$assert( property_exists( $rename_list->updated, $list ), 'Owner renamed the shared list' );
	wp_set_current_user( $b );
	$list_delta = $call( 'TodoList/changes', array( 'accountId' => $aa, 'sinceState' => $list_state ) );
	$assert( $list_state === $list_delta->oldState && $list_delta->newState !== $list_state
		&& array( $list ) === $list_delta->updated && array() === $list_delta->created && array() === $list_delta->destroyed
		&& false === $list_delta->hasMoreChanges, 'Shared TodoList rename is exactly one update' );
	$wp_list = $call( 'TodoList/get', array( 'accountId' => $aa ) );
	$assert( $wp_list->state === $list_delta->newState && 'Renamed shared list' === $wp_list->list[0]->name, 'TodoList delta and refetched snapshot agree' );
	$unsubscribed = $call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'isSubscribed' => false ) ) ) );
	$assert( property_exists( $unsubscribed->updated, $list ) && ! isset( server()->accounts()->accounts->{$aa} ), 'Unsubscribing removes the last subscription from Session discovery' );
	$assert( 'cannotCalculateChanges' === ( $call( 'TodoList/changes', array( 'accountId' => $aa, 'sinceState' => $wp_list->state ) )->type ?? null ),
		'An unsubscribed reader with explicit access must refetch rather than reuse its old list state' );
	$call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'isSubscribed' => true ) ) ) );
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
	$keyword = (object) array( 'audit-query' => true );
	$call( 'Todo/set', array( 'accountId' => $aa, 'update' => (object) array( $todo => (object) array( 'keywords' => $keyword ) ) ) );
	$hidden = $call( 'Todo/set', array( 'accountId' => $aa, 'create' => (object) array( 'hidden' => (object) array(
		'todoListId' => $private_id, 'title' => 'Zulu private', 'keywords' => $keyword,
	) ) ) )->created->hidden->id ?? '';
	$query = array( 'accountId' => $aa, 'filter' => (object) array( 'operator' => 'OR', 'conditions' => array(
		(object) array( 'hasKeyword' => 'not-present' ), (object) array( 'hasKeyword' => 'audit-query' ),
	) ), 'sort' => array( (object) array( 'property' => 'title', 'isAscending' => false ) ), 'calculateTotal' => true );
	$owned_query = $call( 'Todo/query', $query );
	$assert( '' !== $hidden && array( $hidden, $todo ) === $owned_query->ids && 2 === $owned_query->total, 'Owner sees both keyword matches in descending title order' );
	wp_set_current_user( $b );
	$shared_query = $call( 'Todo/query', $query );
	$assert( in_array( $todo, $shared_query->ids, true ), 'Shared query includes the matching readable Todo' );
	$assert( ! in_array( $hidden, $shared_query->ids, true ), 'Shared query excludes the matching private Todo ID' );
	$assert( 1 === $shared_query->total && false === $shared_query->canCalculateChanges, 'Shared total excludes the private match and does not promise deltas' );
	$page = $call( 'Todo/query', array( 'calculateTotal' => false, 'position' => -1, 'limit' => 0 ) + $query );
	$assert( array() === $page->ids && 0 === $page->position && ! property_exists( $page, 'total' )
		&& $page->queryState === $shared_query->queryState, 'Zero limit omits IDs and total without changing the query state' );
	$assert( 'anchorNotFound' === $call( 'Todo/query', array( 'anchor' => $hidden ) + $query )->type, 'Private IDs cannot be used as anchors' );
	$assert( 'unsupportedFilter' === $call( 'Todo/query', array( 'filter' => (object) array( 'unknown' => true ) ) + $query )->type, 'Unsupported conditions are not silently ignored' );
	$assert( 'unsupportedSort' === $call( 'Todo/query', array( 'sort' => array( (object) array( 'property' => 'keywords' ) ) ) + $query )->type, 'Unsupported comparators are rejected' );
	wp_set_current_user( $a );
	$call( 'Todo/set', array( 'accountId' => $aa, 'update' => (object) array( $hidden => (object) array( 'title' => 'Private edit' ) ) ) );
	wp_set_current_user( $b );
	$assert( $shared_query->queryState === $call( 'Todo/query', $query )->queryState, 'Private writes do not change a reader query state' );
	$destination = $call( 'TodoList/set', array( 'accountId' => $ab, 'create' => (object) array( 'copyList' => (object) array( 'name' => 'Copy destination' ) ) ) )->created->copyList->id;
	$source_state = $call( 'Todo/get', array( 'accountId' => $aa ) )->state;
	$destination_state = $call( 'Todo/get', array( 'accountId' => $ab ) )->state;
	$copy_args = array( 'fromAccountId' => $aa, 'accountId' => $ab, 'ifFromInState' => $source_state, 'ifInState' => $destination_state,
		'create' => (object) array( 'copied' => (object) array( 'id' => $todo, 'todoListId' => $destination, 'title' => 'Copied Todo' ),
			'hidden' => (object) array( 'id' => $hidden, 'todoListId' => $destination ) ), 'onSuccessDestroyOriginal' => true );
	foreach ( array( 'ifFromInState', 'ifInState' ) as $guard ) {
		$assert( 'stateMismatch' === $call( 'Todo/copy', array( $guard => 'stale' ) + $copy_args )->type, 'Copy aborts on a stale ' . $guard );
	}
	$assert( $destination_state === $call( 'Todo/get', array( 'accountId' => $ab ) )->state, 'State mismatch leaves destination untouched' );
	$copied = $batch( array(
		array( 'Todo/copy', (object) $copy_args, 'copy' ),
		array( 'Todo/get', (object) array( 'accountId' => $ab ), 'after' ),
	) )->methodResponses;
	$copy_id = $copied[0][1]->created->copied->id ?? '';
	$assert( array( 'Todo/copy', 'Todo/set', 'Todo/get' ) === array_column( $copied, 0 )
		&& 'copy' === $copied[1][2], 'Implicit destroy follows the copy and precedes the next call under the same call ID' );
	$assert( '' !== $copy_id && $copy_id !== $todo && 'notFound' === ( $copied[0][1]->notCreated->hidden->type ?? null ),
		'Copy creates a fresh ID and does not disclose a private source' );
	$assert( 'forbidden' === ( $copied[1][1]->notDestroyed->{$todo}->type ?? null )
		&& $todo === $call( 'Todo/get', array( 'accountId' => $aa, 'ids' => array( $todo ) ) )->list[0]->id,
		'Read-only source survives implicit destroy while its copy remains committed' );
	$copy_doc = $copied[2][1]->list[0];
	$assert( $copy_id === $copy_doc->id && $destination === $copy_doc->todoListId && 'Copied Todo' === $copy_doc->title
		&& true === $copy_doc->keywords->{'audit-query'}, 'Destination document applies overrides and inherits omitted fields' );
	$read_only_destination = $call( 'Todo/copy', array( 'fromAccountId' => $ab, 'accountId' => $aa,
		'create' => (object) array( 'refused' => (object) array( 'id' => $copy_id, 'todoListId' => $list ) ),
	) );
	$assert( 'accountReadOnly' === ( $read_only_destination->type ?? null ), 'Copy cannot write into a read-only destination account' );
	$stale_destroy = $batch( array( array( 'Todo/copy', (object) array( 'fromAccountId' => $ab, 'accountId' => $ab,
		'create' => (object) array( 'staleDestroy' => (object) array( 'id' => $copy_id ) ),
		'onSuccessDestroyOriginal' => true, 'destroyFromIfInState' => 'stale',
	), 'stale' ) ) )->methodResponses;
	$assert( isset( $stale_destroy[0][1]->created->staleDestroy->id ) && 'error' === $stale_destroy[1][0]
		&& 'stateMismatch' === $stale_destroy[1][1]->type
		&& $copy_id === $call( 'Todo/get', array( 'accountId' => $ab, 'ids' => array( $copy_id ) ) )->list[0]->id,
		'Stale destroy state preserves the source and does not roll back the copy' );
	$move_copy = $batch( array( array( 'Todo/copy', (object) array( 'fromAccountId' => $ab, 'accountId' => $ab,
		'create' => (object) array( 'move' => (object) array( 'id' => $copy_id ) ), 'onSuccessDestroyOriginal' => true,
	), 'move' ) ) )->methodResponses;
	$assert( isset( $move_copy[0][1]->created->move->id ) && array( $copy_id ) === ( $move_copy[1][1]->destroyed ?? null ),
		'An authorized implicit destroy removes only the successfully copied source' );
	wp_set_current_user( $a );
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
	$pc = principal_for_account( $ac );
	$assert( is_string( $pc ), 'Deletion target has a Principal' );
	$share->{$pc} = (object) array( 'mayRead' => true, 'mayWrite' => false, 'mayAdmin' => false );
	$target = $call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'shareWith' => $share ) ) ) );
	$assert( property_exists( $target->updated, $list ), 'C is explicitly shared before deletion' );
	$assert( axismundi_actors_set_status( $actors[2], 'tombstone' ), 'Shared target Account identity retired' );
	$after_delete = $call( 'TodoList/get', array( 'accountId' => $aa, 'ids' => array( $list ) ) )->list[0];
	$assert( ! isset( $after_delete->shareWith->{$pc} ) && isset( $after_delete->shareWith->{$pb} ), 'Deleted C share removed while B share survives' );
	$renamed = $call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'name' => 'Renamed after deletion' ) ) ) );
	$assert( property_exists( $renamed->updated, $list ), 'Unrelated rename remains valid after shared user deletion' );
	$second_share = (object) array( $pb => (object) array( 'mayRead' => true, 'mayWrite' => false, 'mayAdmin' => false ) );
	$second = $call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $private_id => (object) array( 'shareWith' => $second_share ) ) ) );
	$assert( property_exists( $second->updated, $private_id ), 'Second list keeps the account accessible after partial revocation' );
	wp_set_current_user( $b );
	$before_revoke = $call( 'TodoList/get', array( 'accountId' => $aa ) )->state;
	wp_set_current_user( $a );
	$call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $list => (object) array( 'shareWith' => null ) ) ) );
	wp_set_current_user( $b );
	$assert( 'cannotCalculateChanges' === ( $call( 'TodoList/changes', array( 'accountId' => $aa, 'sinceState' => $before_revoke ) )->type ?? null ),
		'Partial share revocation invalidates the old list state instead of guessing destroyed IDs' );
	$remaining = $call( 'TodoList/get', array( 'accountId' => $aa ) );
	$assert( array( $private_id ) === array_column( $remaining->list, 'id' ), 'Refetch drops the revoked list and keeps the still-readable list' );
	wp_set_current_user( $a );
	$call( 'TodoList/set', array( 'accountId' => $aa, 'update' => (object) array( $private_id => (object) array( 'shareWith' => null ) ) ) );
	wp_set_current_user( $b );
	$assert( ! isset( server()->accounts()->accounts->{$aa} ) && 'accountNotFound' === ( $call( 'Todo/get', array( 'accountId' => $aa ) )->type ?? null ), 'Revocation denies A immediately in the same process and session' );
	$assert( 'accountNotFound' === ( $call( 'TodoList/changes', array( 'accountId' => $aa, 'sinceState' => $remaining->state ) )->type ?? null ),
		'Revoking the final readable list denies changes without leaking any IDs' );
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
