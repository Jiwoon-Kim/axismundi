<?php
/** Forum-independent Community moderation, ordered Actor snapshots and authority isolation. */
defined( 'ABSPATH' ) || exit( 1 );
$moderator_write = $moderator_write ?? 'axismundi_actors_write_moderators';
$moderator_read = $moderator_read ?? 'axismundi_actors_read_moderators';
$moderator_sync = $moderator_sync ?? 'axismundi_actors_sync_moderators';
$moderator_reference = $moderator_reference ?? 'axismundi_actors_remote_moderators_reference';
$moderator_receive = $moderator_receive ?? 'axismundi_activitypub_bridge_receive_moderators';
$target_block_apply = $target_block_apply ?? 'axismundi_act_apply_relation_activity';
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { ++$checks; if ( ! $ok ) { throw new RuntimeException( $label ); } echo "PASS $label\n"; };
$users = array(); $actors = array(); $documents = array(); $previous_user = get_current_user_id();
$previous_diagnostics = get_option( 'ax_activitypub_bridge_inbox_diagnostics', null );
$prefix = 'https://example.com/moderators-' . wp_generate_uuid4();
$race = false; $remote_group = null;
$http = static function ( $pre, $args, $url ) use ( &$documents, &$race, &$remote_group ) {
	if ( ! array_key_exists( $url, $documents ) ) { return $pre; }
	if ( $race && $remote_group && $url === axismundi_actors_moderators_uri( $remote_group ) ) { $race = false; axismundi_actors_request_moderators_sync( $remote_group ); }
	$value = $documents[$url];
	return is_wp_error( $value ) ? $value : array( 'headers' => array( 'content-type' => 'application/activity+json' ), 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( $value ) );
};
add_filter( 'pre_http_request', $http, 999, 3 );
remove_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', 10 );
try {
	$assert( axismundi_actors_install_moderators(), 'Moderator tables install with transactional storage' );
	for ( $i = 0; $i < 3; ++$i ) {
		$user = wp_insert_user( array( 'user_login' => 'axmod' . strtolower( wp_generate_password( 10, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 0 === $i ? 'administrator' : 'author' ) );
		if ( is_wp_error( $user ) ) { throw new RuntimeException( $user->get_error_message() ); }
		$users[] = (int) $user;
		$actor = axismundi_actors_ensure_for_user( $user );
		$actors[] = $actor;
		axismundi_actors_register_handle( $actor->get_identity_id(), 'axmod' . strtolower( wp_generate_password( 9, false, false ) ) );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
		$people[$i] = axismundi_actors_get_by_identity( $actor->get_identity_id() );
	}
	wp_set_current_user( $users[0] );
	$people[3] = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'initial_owner_user_id' => $users[0], 'preferred_username' => 'axmod' . strtolower( wp_generate_password( 9, false, false ) ) ) );
	if ( ! $people[3] instanceof Axismundi_Actor ) { throw new RuntimeException( 'Alternate owned Person fixture failed' ); }
	$actors[] = $people[3];
	$group = axismundi_actors_create_managed_actor( array( 'owner_user_id' => $users[0], 'actor_type' => 'Group', 'preferred_username' => 'axmod' . strtolower( wp_generate_password( 9, false, false ) ), 'federation_profile' => 'community' ) );
	$channel = axismundi_actors_create_managed_actor( array( 'owner_user_id' => $users[0], 'actor_type' => 'Group', 'preferred_username' => 'axmod' . strtolower( wp_generate_password( 9, false, false ) ) ) );
	if ( ! $group instanceof Axismundi_Actor || ! $channel instanceof Axismundi_Actor ) { throw new RuntimeException( 'Group fixture creation failed' ); }
	$actors[] = $group; $actors[] = $channel;
	// Model a future/imported Channel; its public creation remains disabled.
	$wpdb->insert( axismundi_actors_meta_table(), array( 'actor_id' => $channel->get_identity_id(), 'meta_key' => 'policy:federation_profile', 'meta_value' => 'channel' ) );
	$assert( is_wp_error( $moderator_read( $channel ) ), 'Channel cannot acquire moderators' );
	$assert( is_wp_error( $moderator_read( $people[0] ) ), 'Person cannot acquire moderators' );
	foreach ( array( 'a', 'b', 'c', 'remote-group', 'foreign-group', 'untyped-group' ) as $name ) {
		$uri = $prefix . '/' . $name; $type = str_contains( $name, 'group' ) ? 'Group' : 'Person';
		$document = array( 'id' => $uri, 'type' => $type, 'preferredUsername' => $name . substr( md5( $prefix ), 0, 8 ), 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' );
		if ( 'Group' === $type ) { $document['attributedTo'] = $uri . '/moderators'; }
		if ( 'foreign-group' === $name ) { $document['attributedTo'] = 'https://example.org/moderators'; }
		if ( 'untyped-group' === $name ) { unset( $document['attributedTo'] ); }
		$record = axismundi_actors_normalize_remote_actor_payload( $document, $uri );
		$actor = is_wp_error( $record ) ? $record : axismundi_actors_upsert_remote( $record );
		if ( ! $actor instanceof Axismundi_Actor ) { throw new RuntimeException( 'Remote actor fixture failed: ' . ( is_wp_error( $actor ) ? $actor->get_error_message() : '' ) ); }
		$remote[$name] = $actor; $actors[] = $actor; $documents[$uri] = $document;
	}
	$remote_group = $remote['remote-group']; $collection = $remote_group->get_uri() . '/moderators';
	// upsert_remote is a storage primitive; exercise the production discovery notification.
	do_action( 'axismundi_actors_remote_actor_discovered', $remote_group );
	$principals = $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A );
	$relations = $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_relations_table() . ' ORDER BY id', ARRAY_A );
	$assert( array() === axismundi_actors_read_moderators( $group )['items'], 'Group ownership does not infer a moderator' );
	$assert( is_wp_error( axismundi_act_initialize_moderators( $group, $people[1], $users[0] ) ), 'Bootstrap requires explicit Person operating authority too' );
	$initialize = new WP_REST_Request( 'POST', '/axismundi/v1/actors/' . $group->get_uuid() . '/moderators/initialize' );
	$initialize->set_header( 'Content-Type', 'application/json' ); $initialize->set_body( wp_json_encode( array( 'person' => $people[0]->get_uri() ) ) );
	$assert( 403 === rest_do_request( $initialize )->get_status(), 'Bootstrap REST requires authenticated request proof' );
	$initialize->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	$initialized = rest_do_request( $initialize );
	$assert( 201 === $initialized->get_status(), 'Explicit bootstrap REST commits successfully' );
	$first = axismundi_act_get( $initialized->get_data()['id'] );
	$assert( $first instanceof Axismundi_Activity && $people[0]->get_uri() === $first->get_actor_uri(), 'Explicit bootstrap authors a Person Add' );
	$assert( array( $people[0]->get_uri() ) === $moderator_read( $group )['items'], 'First explicit moderator is stored' );
	$command = static fn( string $type, string $member ) : array => array( 'type' => $type, 'actor' => $people[0]->get_uri(), 'object' => $member, 'target' => axismundi_actors_moderators_uri( $group ) );
	$added = axismundi_act_submit_outbox( $users[0], $people[0], $command( 'Add', $people[1]->get_uri() ), 'moderator-add' );
	$assert( $added instanceof Axismundi_Activity, 'C2S Add reaches the Community writer' );
	$assert( array( $people[0]->get_uri(), $people[1]->get_uri() ) === $moderator_read( $group )['items'], 'Local appointment order is oldest first' );
	$assert( $added->get_uri() === axismundi_act_submit_outbox( $users[0], $people[0], $command( 'Add', $people[1]->get_uri() ), 'moderator-add' )->get_uri(), 'Same Idempotency-Key reuses the Add' );
	$assert( is_wp_error( axismundi_act_submit_outbox( $users[0], $people[0], $command( 'Add', $people[2]->get_uri() ), 'moderator-add' ) ), 'Changed retry intent is refused' );
	$wpdb->query( 'START TRANSACTION' );
	try {
		$denied = $moderator_write( $group, $people[3], $people[3], true, hash( 'sha256', $prefix . '/second-bootstrap' ), $prefix . '/second-bootstrap', true, $users[0] );
		$assert( is_wp_error( $denied ), 'Bootstrap cannot appoint a new Person after initialization' );
	} finally { $wpdb->query( 'ROLLBACK' ); }
	$wpdb->query( 'START TRANSACTION' );
	try {
		$denied = $moderator_write( $group, $people[2], $remote['a'], true, hash( 'sha256', $prefix . '/unauthorized' ), $prefix . '/unauthorized' );
		$assert( is_wp_error( $denied ), 'Non-moderator change is refused' );
	} finally { $wpdb->query( 'ROLLBACK' ); }
	$lower = $command( 'Remove', $people[0]->get_uri() ); $lower['actor'] = $people[1]->get_uri();
	$assert( is_wp_error( axismundi_act_submit_outbox( $users[1], $people[1], $lower ) ), 'Lower moderator cannot remove a higher moderator' );
	$assert( is_wp_error( axismundi_act_initialize_moderators( $group, $people[0], $users[1] ) ), 'Bootstrap never bypasses Account authority' );
	$uri = axismundi_actors_moderators_uri( $group );
	$request = new WP_REST_Request( 'GET', '/axismundi/v1/actors/' . $group->get_uuid() . '/moderators' );
	$response = rest_do_request( $request ); $wire = $response->get_data();
	if ( 200 !== $response->get_status() ) { throw new RuntimeException( 'Moderator route: ' . wp_json_encode( $wire ) ); }
	$assert( 200 === $response->get_status() && 'OrderedCollection' === $wire['type'] && array( $people[0]->get_uri(), $people[1]->get_uri() ) === $wire['orderedItems'] && ! isset( $wire['first'], $wire['next'], $wire['totalItems'] ) && ! array_key_exists( 'totalItems', $wire ), 'Serving preserves Person URI order without pages or totals' );
	$assert( axismundi_actors_moderators_uri( $group ) === ( axismundi_op_actor_transform( $group )['attributedTo'] ?? '' ), 'Community Actor advertises the Actors moderator collection' );
	$assert( array( 'Actor' ) === axismundi_op_collection_contract( 'ap-moderators' )['member_models'], 'Collection registry distinguishes Actor members' );
	$promoted = axismundi_act_submit_outbox( $users[0], $people[0], $command( 'Add', $remote['a']->get_uri() ) );
	$assert( $promoted instanceof Axismundi_Activity, 'A remote Person can be explicitly appointed without principal access' );
	$control = array( 'id' => $prefix . '/remote-add', 'type' => 'Add', 'actor' => $remote['a']->get_uri(), 'object' => $remote['b']->get_uri(), 'target' => $uri, 'audience' => $group->get_uri(), 'to' => array( $group->get_uri() ) );
	$received = axismundi_activitypub_bridge_record_inbox( $control, array(), false, $group );
	$assert( is_array( $received ) && 'handled' === $received['outcome'] && in_array( $remote['b']->get_uri(), axismundi_actors_read_moderators( $group )['items'], true ), 'Verified-ingress handoff applies an existing remote moderator Add' );
	$remove = $control; $remove['id'] = $prefix . '/remote-remove'; $remove['type'] = 'Remove';
	$received = axismundi_activitypub_bridge_record_inbox( $remove, array(), false, $group );
	$assert( is_array( $received ) && ! in_array( $remote['b']->get_uri(), axismundi_actors_read_moderators( $group )['items'], true ), 'Verified-ingress handoff applies Remove independently' );
	$announce = axismundi_act_announce_moderator_control( $group, $promoted );
	$assert( $announce instanceof Axismundi_Activity && 'Announce' === $announce->get_type() && $group->get_uri() === $announce->get_actor_uri() && $people[0]->get_uri() === $announce->get_payload()['object']['actor'], 'Group Announce keeps the Person author distinct' );
	$block = array( 'id' => $prefix . '/direct-scoped-block', 'type' => 'Block', 'actor' => $remote['a']->get_uri(), 'object' => $people[1]->get_uri(), 'target' => $group->get_uri() );
	$assert( axismundi_act_record_activity( $block, 'inbound', array( $people[1]->get_identity_id() ) ) instanceof Axismundi_Activity, 'A scoped Block can be retained as received evidence' );
	$wpdb->query( 'START TRANSACTION' );
	try {
		$effects = $target_block_apply( axismundi_act_normalize( $block, 'inbound' ) );
		$assert( array() === $effects && null === axismundi_act_get_relation( 'block', $remote['a']->get_uri(), $people[1]->get_uri() ), 'Target-scoped Block never becomes a personal Block relation' );
	} finally { $wpdb->query( 'ROLLBACK' ); }
	$fail_log = static function ( string $query ) : string { return str_contains( $query, 'INSERT INTO' ) && str_contains( $query, 'ax_actor_moderator_log' ) ? 'INSERT INTO ax_missing_moderator_log VALUES (1)' : $query; };
	add_filter( 'query', $fail_log ); $suppress = $wpdb->suppress_errors( true );
	try { $failed = axismundi_act_submit_outbox( $users[0], $people[0], $command( 'Add', $people[2]->get_uri() ), 'failed-log' ); }
	finally { remove_filter( 'query', $fail_log ); $wpdb->suppress_errors( $suppress ); }
	$assert( is_wp_error( $failed ) && ! in_array( $people[2]->get_uri(), axismundi_actors_read_moderators( $group )['items'], true ), 'Log failure rolls back the moderator addition' );
	$assert( axismundi_act_submit_outbox( $users[0], $people[0], $command( 'Remove', $people[1]->get_uri() ) ) instanceof Axismundi_Activity
		&& axismundi_act_submit_outbox( $users[0], $people[0], $command( 'Remove', $remote['a']->get_uri() ) ) instanceof Axismundi_Activity, 'Moderator removals retain only the top moderator' );
	$assert( is_wp_error( axismundi_act_submit_outbox( $users[0], $people[0], $command( 'Remove', $people[0]->get_uri() ) ) ), 'Last moderator cannot remove itself' );
	$retry = axismundi_act_submit_outbox( $users[0], $people[0], $command( 'Add', $people[1]->get_uri() ), 'moderator-add' );
	$assert( $retry instanceof Axismundi_Activity && array( $people[0]->get_uri() ) === axismundi_actors_read_moderators( $group )['items'], 'Old Add retry cannot revive a removed local moderator' );
	$ban = array( 'id' => $prefix . '/ban-announcement', 'type' => 'Announce', 'actor' => $remote['untyped-group']->get_uri(), 'object' => array( 'id' => $prefix . '/reported-ban', 'type' => 'Block', 'actor' => $remote['a']->get_uri(), 'object' => $people[0]->get_uri(), 'target' => $remote['untyped-group']->get_uri() ), 'to' => array( $people[0]->get_uri() ) );
	$observed_ban = axismundi_activitypub_bridge_record_inbox( $ban, array(), false, $people[0] );
	$assert( $observed_ban instanceof Axismundi_Activity && $remote['untyped-group']->get_uri() === $observed_ban->get_actor_uri() && null === axismundi_act_get( $ban['object']['id'] ), 'Group sanction observation needs no moderator interpretation and creates no Person Block' );
	$assert( is_wp_error( axismundi_actors_read_moderators( $remote_group ) ), 'Remote roles require a verified complete collection snapshot' );
	$assert( null !== $wpdb->get_var( $wpdb->prepare( 'SELECT group_id FROM %i WHERE group_id=%d', axismundi_actors_moderators_state_table(), $remote_group->get_identity_id() ) ), 'Remote self-description automatically requests synchronization' );
	$assert( false === $moderator_reference( $remote['foreign-group'], 'https://example.org/moderators' ), 'A foreign-origin collection reference is refused' );
	$a = $remote['a']->get_uri(); $b = $remote['b']->get_uri(); $c = $remote['c']->get_uri();
	$documents[$collection] = array( 'id' => $collection, 'type' => 'OrderedCollection', 'orderedItems' => array( $a, $b ) );
	$assert( true === $moderator_sync( $remote_group->get_identity_id() ) && array( $a, $b ) === $moderator_read( $remote_group )['items'], 'Complete remote membership is reconciled' );
	$documents[$collection]['orderedItems'] = array( $b, $a );
	$assert( true === $moderator_sync( $remote_group->get_identity_id() ) && array( $b, $a ) === $moderator_read( $remote_group )['items'], 'Remote order changes even when membership is unchanged' );
	$assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE group_id=%d AND appointed_at IS NOT NULL', axismundi_actors_moderators_table(), $remote_group->get_identity_id() ) ), 'Remote appointment timestamps are not invented' );
	$late = array( 'id' => $prefix . '/late-announce', 'type' => 'Announce', 'actor' => $remote_group->get_uri(), 'object' => array( 'id' => $prefix . '/late-add', 'type' => 'Add', 'actor' => $a, 'object' => $c, 'target' => $collection ), 'to' => array( $people[0]->get_uri() ) );
	$hint_handler = static function ( $handler, $target, $candidate ) use ( $moderator_receive, $remote_group, $collection ) { return $candidate->get_identity_id() === $remote_group->get_identity_id() && $target === $collection ? $moderator_receive : $handler; };
	add_filter( 'axismundi_activitypub_bridge_collection_control_handler', $hint_handler, 999, 3 );
	try { $late_result = axismundi_activitypub_bridge_record_inbox( $late, array(), false, $people[0] ); }
	finally { remove_filter( 'axismundi_activitypub_bridge_collection_control_handler', $hint_handler, 999 ); }
	$assert( is_array( $late_result ) && 'handled' === $late_result['outcome'] && array( $b, $a ) === axismundi_actors_read_moderators( $remote_group )['items'], 'Late Add cannot optimistically resurrect a remote moderator' );
	$documents[$collection]['orderedItems'] = array( $b, $a, $c ); $race = true;
	$stale = $moderator_sync( $remote_group->get_identity_id() );
	$assert( is_wp_error( $stale ) && array( $b, $a ) === axismundi_actors_read_moderators( $remote_group )['items'], 'In-flight snapshot cannot resurrect after a newer signal' );
	$documents[$a] = new WP_Error( 'ax_op_remote_signed_fetch_required', 'Authentication required' );
	$documents[$collection]['orderedItems'][] = $remote_group->get_uri();
	$assert( true === $moderator_sync( $remote_group->get_identity_id() ) && array( $b, $c ) === $moderator_read( $remote_group )['items'], 'Unresolved member allows progress but never leaks cached authority' );
	$stored = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE group_id=%d AND person_uri=%s', axismundi_actors_moderators_table(), $remote_group->get_identity_id(), $a ), ARRAY_A );
	$assert( 'unresolved' === $stored['resolution'] && '1' === $stored['position'], 'Unresolved URI retains authoritative position' );
	$assert( 'excluded' === $wpdb->get_var( $wpdb->prepare( 'SELECT resolution FROM %i WHERE group_id=%d AND person_uri=%s', axismundi_actors_moderators_table(), $remote_group->get_identity_id(), $remote_group->get_uri() ) ), 'Confirmed non-Person members are excluded independently' );
	$before = $moderator_read( $remote_group )['items'];
	$documents[$collection] = new WP_Error( 'timeout', 'Unavailable' );
	$assert( is_wp_error( $moderator_sync( $remote_group->get_identity_id() ) ) && $before === $moderator_read( $remote_group )['items'], 'Collection failure preserves the complete previous snapshot' );
	$documents[$collection] = array( 'id' => $collection, 'type' => 'OrderedCollection', 'orderedItems' => array(), 'totalItems' => 3 );
	$assert( is_wp_error( $moderator_sync( $remote_group->get_identity_id() ) ) && $before === $moderator_read( $remote_group )['items'], 'Incomplete collection cannot erase moderators' );
	$documents[$collection] = array( 'id' => $collection, 'type' => 'OrderedCollection', 'orderedItems' => array() );
	$assert( true === $moderator_sync( $remote_group->get_identity_id() ) && array() === $moderator_read( $remote_group )['items'], 'An explicit complete empty collection clears remote membership' );
	$assert( $principals === $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A ), 'No principal grants are created' );
	$assert( $relations === $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_relations_table() . ' ORDER BY id', ARRAY_A ), 'No relation membership changes' );
	$assert( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE group_id=%d', axismundi_actors_moderators_log_table(), $group->get_identity_id() ) ) >= 5, 'Moderator decisions retain an independent actor and Activity log' );
} finally {
	remove_filter( 'pre_http_request', $http, 999 );
	add_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', 10 );
	if ( null === $previous_diagnostics ) { delete_option( 'ax_activitypub_bridge_inbox_diagnostics' ); } else { update_option( 'ax_activitypub_bridge_inbox_diagnostics', $previous_diagnostics, false ); }
	foreach ( $actors as $actor ) {
		if ( ! $actor instanceof Axismundi_Actor ) { continue; }
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE actor_uri=%s', axismundi_act_activities_table(), $actor->get_uri() ) );
		foreach ( $rows as $id ) { $wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $id ) ); $wpdb->delete( axismundi_act_activities_table(), array( 'id' => $id ) ); }
		$wpdb->delete( axismundi_activitypub_bridge_collection_controls_table(), array( 'group_uri' => $actor->get_uri() ) );
		wp_clear_scheduled_hook( 'axismundi_actors_sync_moderators', array( $actor->get_identity_id() ) );
		foreach ( array( axismundi_actors_moderators_table(), axismundi_actors_moderators_state_table(), axismundi_actors_moderators_log_table() ) as $table ) { $wpdb->delete( $table, array( 'group_id' => $actor->get_identity_id() ) ); }
	}
	require_once ABSPATH . 'wp-admin/includes/user.php'; foreach ( $users as $id ) { wp_delete_user( $id ); }
	foreach ( $actors as $actor ) {
		if ( ! $actor instanceof Axismundi_Actor ) { continue; } $id = $actor->get_identity_id();
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_signing_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_meta_table(), array( 'actor_id' => $id ) ); $wpdb->delete( axismundi_actors_actors_table(), array( 'id' => $id ) );
	}
	wp_set_current_user( $previous_user );
}
printf( "== %d checks, 0 failed ==\n", $checks );
