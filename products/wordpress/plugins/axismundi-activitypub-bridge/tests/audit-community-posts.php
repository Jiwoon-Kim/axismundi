<?php
/** Forum-independent root admission and review, plus deferred parent acquisition. */
defined( 'ABSPATH' ) || exit( 1 );
$admit = $community_admit ?? 'axismundi_act_admit_community_root';
$review = $community_review ?? 'axismundi_act_review_community_post';
$decide = $community_decide ?? 'axismundi_actors_decide_community_post';
$store = $community_store ?? 'axismundi_actors_admit_community_post';
global $wpdb;
$checks = 0;
$assert = static function ( $ok, $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; };
$users = array(); $actors = array(); $objects = array(); $activity_uris = array();
$old_user = get_current_user_id();
$old_acquisition = get_option( AXISMUNDI_OP_BACKGROUND_ACQUISITION_OPTION, null );
$prefix = 'https://example.com/community-post-' . wp_generate_uuid4();
$parent = $prefix . '/unknown-parent'; $parent_calls = 0; $parent_error = true;
$http = static function ( $pre, $args, $url ) use ( $parent, &$parent_calls, &$parent_error ) {
	if ( $url === $parent ) {
		++$parent_calls;
		if ( $parent_error ) { return new WP_Error( 'ax_op_remote_signed_fetch_required', 'Signed fetch required' ); }
		return array( 'headers' => array( 'content-type' => 'application/activity+json' ), 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'id' => $parent, 'type' => 'Page', 'attributedTo' => $parent . '/author', 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ), 'name' => 'Parent', 'content' => 'Parent body' ) ) );
	}
	return new WP_Error( 'audit_no_network', 'No live network in this fixture' );
};
$no_forum = static function ( $query ) use ( $wpdb ) {
	if ( str_contains( $query, $wpdb->prefix . 'ax_forum_' ) ) { throw new RuntimeException( 'Forum table access is forbidden' ); }
	return $query;
};
add_filter( 'query', $no_forum );
add_filter( 'pre_http_request', $http, PHP_INT_MAX, 3 );
remove_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', 10 );
update_option( AXISMUNDI_OP_BACKGROUND_ACQUISITION_OPTION, '0' );
try {
	$assert( axismundi_actors_install_community_posts(), 'Community storage installs transactionally' );
	for ( $i = 0; $i < 2; ++$i ) {
		$user = wp_insert_user( array( 'user_login' => 'axcp' . strtolower( wp_generate_password( 10, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
		if ( is_wp_error( $user ) ) { throw new RuntimeException( $user->get_error_message() ); }
		$users[] = $user;
		$actor = axismundi_actors_ensure_for_user( $user ); $actors[] = $actor;
		axismundi_actors_register_handle( $actor->get_identity_id(), 'axcp' . strtolower( wp_generate_password( 10, false, false ) ) );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
		$people[$i] = axismundi_actors_get_by_identity( $actor->get_identity_id() );
	}
	wp_set_current_user( $users[0] );
	$group = axismundi_actors_create_managed_actor( array( 'owner_user_id' => $users[0], 'actor_type' => 'Group', 'preferred_username' => 'axcp' . strtolower( wp_generate_password( 10, false, false ) ), 'federation_profile' => 'community' ) );
	$channel = axismundi_actors_create_managed_actor( array( 'owner_user_id' => $users[0], 'actor_type' => 'Group', 'preferred_username' => 'axcp' . strtolower( wp_generate_password( 10, false, false ) ) ) );
	if ( ! $group instanceof Axismundi_Actor || ! $channel instanceof Axismundi_Actor ) { throw new RuntimeException( 'Group fixtures failed' ); }
	$actors[] = $group; $actors[] = $channel;
	$wpdb->insert( axismundi_actors_meta_table(), array( 'actor_id' => $channel->get_identity_id(), 'meta_key' => 'policy:federation_profile', 'meta_value' => 'channel' ) );
	$author_uri = $prefix . '/person';
	$record = axismundi_actors_normalize_remote_actor_payload( array( 'id' => $author_uri, 'type' => 'Person', 'preferredUsername' => 'axcpremote', 'inbox' => $author_uri . '/inbox', 'outbox' => $author_uri . '/outbox' ), $author_uri );
	$author = axismundi_actors_upsert_remote( $record );
	if ( ! $author instanceof Axismundi_Actor ) { throw new RuntimeException( 'Remote Person fixture failed' ); }
	$actors[] = $author;
	$initialized = axismundi_act_initialize_moderators( $group, $people[0], $users[0] );
	$assert( $initialized instanceof Axismundi_Activity, 'Existing moderator bootstrap is reused' );
	axismundi_act_write_relation( 'follow', $author_uri, $group->get_uri(), 'inbound', 'accepted', null, null );
	$principals = $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A );
	$relations = $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_relations_table() . ' ORDER BY id', ARRAY_A );
	$make = static function ( string $name, string $type = 'Page', string $reply = '' ) use ( $prefix, $author_uri, $group, &$objects, &$activity_uris ) {
		$uri = $prefix . '/' . $name; $objects[] = $uri; $activity_uris[] = $uri . '/create';
		$object = array( 'id' => $uri, 'type' => $type, 'name' => $name, 'content' => 'Source body', 'attributedTo' => $author_uri, 'audience' => $group->get_uri(), 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ), 'cc' => array( $group->get_uri() ) );
		if ( '' !== $reply ) { $object['inReplyTo'] = $reply; }
		return array( '@context' => 'https://www.w3.org/ns/activitystreams', 'id' => $uri . '/create', 'type' => 'Create', 'actor' => $author_uri, 'object' => $object, 'to' => array( $group->get_uri() ), 'cc' => array( 'https://www.w3.org/ns/activitystreams#Public' ) );
	};
	$payload = $make( 'page' );
	$received = axismundi_activitypub_bridge_record_inbox( $payload, array(), false, $group );
	$assert( $received instanceof Axismundi_Activity, 'Verified Bridge handoff records the Person Create' );
	$assert( $payload === $received->get_payload(), 'Original Create payload is preserved' );
	$row = axismundi_actors_get_community_post( $group->get_identity_id(), $payload['object']['id'] );
	$assert( $row && 'pending' === $row['admission_state'], 'Root enters pending review' );
	$assert( array() === axismundi_act_get_by_object( $received->get_uri() ), 'No Announce exists before review' );
	$assert( $admit( $received, $group )['id'] === $row['id'], 'Repeated admission is idempotent' );
	$assert( is_wp_error( $admit( $received, $channel ) ), 'Channel admission is refused' );
	$assert( is_wp_error( $admit( $received, $people[0] ) ), 'Person admission is refused' );
	$assert( is_wp_error( $store( $channel, $payload['object']['id'], $received->get_uri(), $author_uri ) ), 'Storage rejects Channel profile too' );
	$unreceived_payload = $make( 'unreceived' );
	$unreceived = axismundi_act_record_activity( $unreceived_payload, 'inbound' );
	$assert( $unreceived instanceof Axismundi_Activity && is_wp_error( $admit( $unreceived, $group ) ), 'An address without a Group receipt grants no admission' );
	$article = $make( 'article', 'Article' );
	$article_create = axismundi_activitypub_bridge_record_inbox( $article, array(), false, $group );
	$assert( $article_create instanceof Axismundi_Activity && is_array( $admit( $article_create, $group ) ), 'Article roots are accepted too' );
	$note = $make( 'bare-note', 'Note' );
	$bare = axismundi_act_record_activity( $note, 'inbound', array( $group->get_identity_id() ) );
	$assert( $bare instanceof Axismundi_Activity && is_wp_error( $admit( $bare, $group ) ), 'Bare Note is not a root submission' );
	$cache_before = axismundi_op_get_remote_object( $payload['object']['id'], false );
	$wpdb->query( 'START TRANSACTION' );
	try { $denied = $decide( $group, $people[1], $payload['object']['id'], true ); }
	finally { $wpdb->query( 'ROLLBACK' ); }
	$assert( is_wp_error( $denied ), 'Non-moderator review is refused' );
	$assert( is_wp_error( $review( $users[1], $people[0], $group, $payload['object']['id'], true ) ), 'Moderator Person operating permission is required' );
	$approved = $review( $users[0], $people[0], $group, $payload['object']['id'], true );
	$assert( $approved instanceof Axismundi_Activity, 'Moderator approval produces an Announce' );
	$assert( $approved->get_actor_uri() === $group->get_uri() && $approved->get_payload()['object'] === $payload, 'Group announcement preserves the Person authored Create' );
	$assert( 'approved' === axismundi_actors_get_community_post( $group->get_identity_id(), $payload['object']['id'] )['admission_state'], 'Approval and publication commit together' );
	$assert( $approved->get_uri() === $review( $users[0], $people[0], $group, $payload['object']['id'], true )->get_uri(), 'Approval retries reuse one Announce' );
	$assert( $cache_before === axismundi_op_get_remote_object( $payload['object']['id'], false ), 'Approval never changes the source cache' );
	$fail_log = static function ( $query ) {
		return str_starts_with( $query, 'INSERT INTO `' . axismundi_actors_moderators_log_table() . '`' ) ? str_replace( axismundi_actors_moderators_log_table(), axismundi_actors_moderators_log_table() . '_audit_missing', $query ) : $query;
	};
	$old_suppress = $wpdb->suppress_errors( true );
	add_filter( 'query', $fail_log, 999 );
	try { $failed = $review( $users[0], $people[0], $group, $article['object']['id'], true ); }
	finally { remove_filter( 'query', $fail_log, 999 ); $wpdb->suppress_errors( $old_suppress ); }
	$assert( is_wp_error( $failed ) && 'pending' === axismundi_actors_get_community_post( $group->get_identity_id(), $article['object']['id'] )['admission_state'] && array() === axismundi_act_get_by_object( $article_create->get_uri() ), 'Log failure rolls back both decision and Announce' );
	$assert( true === $review( $users[0], $people[0], $group, $article['object']['id'], false ), 'Moderator can reject a pending root' );
	$assert( is_wp_error( $review( $users[0], $people[0], $group, $article['object']['id'], true ) ), 'Rejected submissions cannot silently become approved' );
	$assert( array() === axismundi_act_get_by_object( $article_create->get_uri() ), 'Rejection emits no Announce' );
	$assert( 2 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE group_id=%d AND verb IN ('ApprovePost','RejectPost')", axismundi_actors_moderators_log_table(), $group->get_identity_id() ) ), 'Approval and rejection use the existing moderation log' );
	update_option( AXISMUNDI_OP_BACKGROUND_ACQUISITION_OPTION, '1' );
	$reply_payload = $make( 'reply', 'Note', $parent );
	$reply = axismundi_activitypub_bridge_record_inbox( $reply_payload, array(), false, $people[0] );
	$assert( $reply instanceof Axismundi_Activity && 0 === $parent_calls, 'Reply ingress never fetches its parent synchronously' );
	$assert( false !== wp_next_scheduled( 'axismundi_op_fetch_reply_parent', array( $reply->get_uri(), 0 ) ), 'Unknown parent is queued asynchronously' );
	update_option( AXISMUNDI_OP_BACKGROUND_ACQUISITION_OPTION, '0' );
	axismundi_op_fetch_reply_parent( $reply->get_uri() );
	$assert( 0 === $parent_calls, 'Queued parent jobs honor disabled acquisition at execution' );
	update_option( AXISMUNDI_OP_BACKGROUND_ACQUISITION_OPTION, '1' );
	$edge_before = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE child_uri=%s', axismundi_op_thread_edges_table(), $reply_payload['object']['id'] ), ARRAY_A );
	$assert( count( $edge_before ) === 1, 'Reply edge exists by Object URI before parent fetch' );
	axismundi_op_fetch_reply_parent( $reply->get_uri() );
	$assert( 1 === $parent_calls && null === axismundi_op_get_remote_object( $parent, false ), 'Authentication failure leaves parent unresolved' );
	$assert( $edge_before === $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE child_uri=%s', axismundi_op_thread_edges_table(), $reply_payload['object']['id'] ), ARRAY_A ), 'Failed parent fetch preserves thread edges' );
	$parent_error = false; $objects[] = $parent;
	axismundi_op_fetch_reply_parent( $reply->get_uri(), 1 );
	$assert( is_array( axismundi_op_get_remote_object( $parent, false ) ), 'Deferred parent fetch caches the Object without an Activity' );
	$assert( array() === axismundi_act_get_by_object( $parent ), 'Parent resolution fabricates no Person Create' );
	$before_calls = $parent_calls;
	axismundi_op_fetch_reply_parent( $reply->get_uri(), 2 );
	$assert( $parent_calls === $before_calls, 'Cached parents are not fetched again' );
	$assert( $principals === $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A ), 'No principal grants are created' );
	$assert( $relations === $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_relations_table() . ' ORDER BY id', ARRAY_A ), 'Submission and review do not change Follow relations' );
} finally {
	remove_filter( 'query', $no_forum );
	remove_filter( 'pre_http_request', $http, PHP_INT_MAX );
	add_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', 10 );
	if ( null === $old_acquisition ) { delete_option( AXISMUNDI_OP_BACKGROUND_ACQUISITION_OPTION ); } else { update_option( AXISMUNDI_OP_BACKGROUND_ACQUISITION_OPTION, $old_acquisition ); }
	foreach ( $activity_uris as $uri ) { for ( $i = 0; $i <= 2; ++$i ) { wp_clear_scheduled_hook( 'axismundi_op_fetch_reply_parent', array( $uri, $i ) ); } }
	wp_clear_scheduled_hook( 'axismundi_op_discover_remote_actor', array( $parent . '/author' ) );
	foreach ( $objects as $uri ) {
		axismundi_op_delete_remote_object( $uri );
		$index = axismundi_op_get_object_by_uri( $uri );
		if ( $index ) { $wpdb->delete( axismundi_op_objects_table(), array( 'id' => $index['id'] ) ); }
		$wpdb->delete( axismundi_op_thread_edges_table(), array( 'child_uri' => $uri ) );
	}
	foreach ( $actors as $actor ) {
		if ( ! $actor instanceof Axismundi_Actor ) { continue; }
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE actor_uri=%s', axismundi_act_activities_table(), $actor->get_uri() ) ) as $id ) { $wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $id ) ); $wpdb->delete( axismundi_act_activities_table(), array( 'id' => $id ) ); }
		foreach ( array( axismundi_actors_community_posts_table(), axismundi_actors_moderators_table(), axismundi_actors_moderators_state_table(), axismundi_actors_moderators_log_table() ) as $table ) { $wpdb->delete( $table, array( 'group_id' => $actor->get_identity_id() ) ); }
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE subject_actor_uri=%s OR object_actor_uri=%s', axismundi_act_relations_table(), $actor->get_uri(), $actor->get_uri() ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) { wp_delete_user( $id ); }
	foreach ( $actors as $actor ) {
		if ( ! $actor instanceof Axismundi_Actor ) { continue; } $id = $actor->get_identity_id();
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_signing_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_meta_table(), array( 'actor_id' => $id ) ); $wpdb->delete( axismundi_actors_actors_table(), array( 'id' => $id ) );
	}
	wp_set_current_user( $old_user );
}
printf( "== %d checks, 0 failed ==\n", $checks );
