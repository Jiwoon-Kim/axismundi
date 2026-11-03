<?php
/** Explicit legacy adoption, transactional failure injection and hard wire cut. */
defined( 'ABSPATH' ) || exit( 1 );
$migrate = $community_migrate ?? 'axismundi_act_migrate_community_group';
$migrate_request = $community_migrate_request ?? 'axismundi_act_community_migration_request';
$project = $community_migration_projection ?? 'axismundi_op_project_community_moderators';
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { ++$checks; if ( ! $ok ) { throw new RuntimeException( $label ); } };
$users = $actors = array(); $previous_user = get_current_user_id();
$events = array();
$observe = static function ( $activity ) use ( &$events ) { $events[$activity->get_uri()] = array( 'actor' => $activity->get_actor_uri() ); };
add_action( 'axismundi_act_activity_recorded', $observe, 999 );
remove_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', 10 );
$no_forum = static function ( $sql ) use ( $wpdb ) { if ( str_contains( $sql, $wpdb->prefix . 'ax_forum_' ) ) { throw new RuntimeException( 'Migration must not access Forum tables' ); } return $sql; };
add_filter( 'query', $no_forum );
try {
	foreach ( array( 'administrator', 'editor' ) as $role ) {
		$user = wp_insert_user( array( 'user_login' => 'axmigration' . strtolower( wp_generate_password( 10, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => $role ) );
		if ( is_wp_error( $user ) ) { throw new RuntimeException( 'User fixture failed' ); }
		$users[] = $user;
		$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'initial_owner_user_id' => $user, 'preferred_username' => 'axmigration' . strtolower( wp_generate_password( 10, false, false ) ), 'status' => 'public' ) );
		if ( ! $actor instanceof Axismundi_Actor ) { throw new RuntimeException( 'Person fixture failed' ); }
		$people[] = $actor; $actors[] = $actor;
	}
	wp_set_current_user( $users[0] );
	$make = static function ( int $owner, ?string $profile = null ) use ( &$actors ) {
		$args = array( 'owner_user_id' => $owner, 'actor_type' => 'Group', 'preferred_username' => 'axmigration' . strtolower( wp_generate_password( 10, false, false ) ) );
		if ( null !== $profile ) { $args['federation_profile'] = $profile; }
		$group = axismundi_actors_create_managed_actor( $args );
		if ( ! $group instanceof Axismundi_Actor ) { throw new RuntimeException( 'Group fixture failed' ); }
		$actors[] = $group; return $group;
	};
	$group = $make( $users[0] ); $foreign = $make( $users[1] ); $configured = $make( $users[0], 'community' );
	$channel = $make( $users[0] );
	$wpdb->insert( axismundi_actors_meta_table(), array( 'actor_id' => $channel->get_identity_id(), 'meta_key' => 'policy:federation_profile', 'meta_value' => 'channel' ) );
	$assert( null === axismundi_actors_group_profile( $group ), 'Ordinary bootstrap does not assign a profile' );
	$assert( ! isset( $project( array(), $group )['attributedTo'] ), 'Legacy Group advertises no replacement before migration' );
	$assert( axismundi_actors_moderators_uri( $configured ) === ( $project( array(), $configured )['attributedTo'] ?? '' ), 'Empty Community still advertises its collection' );
	$assert( ! isset( $project( array(), $channel )['attributedTo'] ) && ! isset( $project( array(), $people[0] )['attributedTo'] ), 'Channel and Person do not inherit Community discovery' );
	$assert( is_wp_error( $migrate( $users[1], $foreign, $people[1] ) ), 'An ordinary operator cannot migrate profiles' );
	$assert( is_wp_error( $migrate( $users[0], $foreign, $people[0] ) ), 'Administrator status cannot replace Group operating permission' );
	$assert( is_wp_error( $migrate( $users[0], $group, $people[1] ) ), 'Person operating permission is required' );
	$assert( is_wp_error( $migrate( $users[0], $channel, $people[0] ) ), 'Channel profile cannot be overwritten' );
	$assert( is_wp_error( $migrate( $users[0], $configured, $people[0] ) ), 'Configured Community cannot be silently reinitialized' );
	foreach ( array( 'profile' => axismundi_actors_meta_table(), 'member' => axismundi_actors_moderators_table(), 'log' => axismundi_actors_moderators_log_table(), 'activity' => axismundi_act_activities_table() ) as $name => $table ) {
		$fixture = $make( $users[0] ); $hit = false; $event_count = count( $events );
		$fault = static function ( $sql ) use ( $table, &$hit ) { if ( ! $hit && preg_match( '/^INSERT\s+INTO\s+`?' . preg_quote( $table, '/' ) . '`?\s/i', $sql ) ) { $hit = true; return 'INSERT INTO ax_migration_missing_table VALUES (1)'; } return $sql; };
		$old_errors = $wpdb->suppress_errors(); add_filter( 'query', $fault, 999 );
		try { $failed = $migrate( $users[0], $fixture, $people[0] ); }
		finally { remove_filter( 'query', $fault, 999 ); $wpdb->suppress_errors( $old_errors ); }
		$assert( $hit && is_wp_error( $failed ), $name . ' failure is exercised' );
		$assert( null === axismundi_actors_group_profile( $fixture ), $name . ' failure rolls back the profile' );
		$assert( null === axismundi_act_community_migration_control( $fixture ) && count( $events ) === $event_count, $name . ' failure leaves no Activity or public event' );
		$assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE group_id=%d', axismundi_actors_moderators_table(), $fixture->get_identity_id() ) ), $name . ' failure leaves no moderator' );
	}
	$body_before = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', axismundi_actors_actors_table(), $group->get_identity_id() ), ARRAY_A );
	$keys_before = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE identity_id=%d', axismundi_actors_signing_keys_table(), $group->get_identity_id() ), ARRAY_A );
	$principals = $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A );
	$relations = $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_relations_table() . ' ORDER BY id', ARRAY_A );
	$input = array( 'group_uri' => $group->get_uri(), 'person_uri' => $people[0]->get_uri(), 'confirm_migration' => '1', '_wpnonce' => wp_create_nonce( axismundi_act_community_migration_nonce( $group->get_uri(), $people[0]->get_uri() ) ) );
	$bad = $input; $bad['_wpnonce'] = 'invalid';
	$assert( is_wp_error( $migrate_request( $bad ) ), 'Admin migration rejects invalid nonce' );
	$bad = $input; unset( $bad['confirm_migration'] );
	$assert( is_wp_error( $migrate_request( $bad ) ), 'Admin migration requires explicit confirmation' );
	$control = $migrate_request( $input );
	$assert( $control instanceof Axismundi_Activity, 'Explicit admin form commits the migration' );
	$assert( 'community' === axismundi_actors_group_profile( $group ), 'Migration assigns the Community profile' );
	$assert( array( $people[0]->get_uri() ) === axismundi_actors_read_moderators( $group )['items'], 'Migration appoints only the explicitly supplied Person' );
	$assert( axismundi_act_community_migration_announced( $group, $control ), 'Group independently announces the moderator Add' );
	$assert( $control->get_actor_uri() === $people[0]->get_uri() && 'Add' === $control->get_type(), 'Bootstrap control is authored by the Person, not Group' );
	$assert( $body_before === $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', axismundi_actors_actors_table(), $group->get_identity_id() ), ARRAY_A ) && $keys_before === $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE identity_id=%d', axismundi_actors_signing_keys_table(), $group->get_identity_id() ), ARRAY_A ), 'Actor URI handle status and key material are untouched' );
	$assert( $principals === $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A ) && $relations === $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_relations_table() . ' ORDER BY id', ARRAY_A ), 'Migration creates no principals or Follow relations' );
	$uri = $control->get_uri(); $before_retry = count( $events );
	$assert( $uri === $migrate_request( $input )->get_uri() && count( $events ) === $before_retry, 'Same migration retry creates no duplicate Activity' );
	$assert( false === axismundi_actors_store_meta( $group->get_identity_id(), 'policy:federation_profile', 'channel' ) && is_wp_error( axismundi_actors_check_group_profile_update( array( 'federation_profile' => 'channel' ) ) ), 'Normal profile edit guards remain immutable' );
	$wire = axismundi_op_actor_transform( $group );
	$assert( $wire['id'] === $group->get_uri() && $wire['attributedTo'] === axismundi_actors_moderators_uri( $group ), 'Actor identity is stable and discovery points to the new collection' );
	$request = new WP_REST_Request( 'GET', '/axismundi/v1/actors/' . $group->get_uuid() . '/moderators' );
	$response = rest_do_request( $request );
	$assert( 200 === $response->get_status() && array( $people[0]->get_uri() ) === $response->get_data()['orderedItems'], 'Advertised collection resolves with the explicit Person' );
	$assert( 404 === rest_do_request( new WP_REST_Request( 'GET', '/axismundi/v1/forum/groups/' . $group->get_uuid() . '/moderators' ) )->get_status(), 'Old Forum URL is not an alias' );
	$repair = $make( $users[0] ); $hit = false;
	$fault = static function ( $sql ) use ( &$hit ) { if ( str_starts_with( $sql, 'INSERT INTO `' . axismundi_act_activities_table() . '`' ) && str_contains( $sql, "'Announce'" ) ) { $hit = true; return 'INSERT INTO ax_migration_missing_table VALUES (1)'; } return $sql; };
	$old_errors = $wpdb->suppress_errors(); add_filter( 'query', $fault, 999 );
	try { $failed = $migrate( $users[0], $repair, $people[0] ); }
	finally { remove_filter( 'query', $fault, 999 ); $wpdb->suppress_errors( $old_errors ); }
	$assert( $hit && is_wp_error( $failed ) && 'ax_migration_announce' === $failed->get_error_code(), 'Announcement failure is reported after the atomic migration' );
	$assert( 'community' === axismundi_actors_group_profile( $repair ) && array( $people[0]->get_uri() ) === axismundi_actors_read_moderators( $repair )['items'], 'Announcement failure retains a complete profile and moderator' );
	$assert( in_array( $repair->get_identity_id(), array_map( static fn( $candidate ) => $candidate['group']->get_identity_id(), axismundi_act_community_migration_candidates( $people[0] ) ), true ), 'Admin form exposes unfinished announcements for retry' );
	$repaired = $migrate( $users[0], $repair, $people[0] );
	$assert( $repaired instanceof Axismundi_Activity && axismundi_act_community_migration_announced( $repair, $repaired ), 'Retry completes the Group announcement without reinitializing roles' );
} finally {
	remove_action( 'axismundi_act_activity_recorded', $observe, 999 ); remove_filter( 'query', $no_forum );
	add_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', 10 );
	foreach ( $actors as $actor ) {
		$id = $actor->get_identity_id();
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE actor_uri=%s', axismundi_act_activities_table(), $actor->get_uri() ) ) as $activity_id ) { $wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $activity_id ) ); $wpdb->delete( axismundi_act_activities_table(), array( 'id' => $activity_id ) ); }
		foreach ( array( axismundi_actors_moderators_table(), axismundi_actors_moderators_state_table(), axismundi_actors_moderators_log_table() ) as $table ) { $wpdb->delete( $table, array( 'group_id' => $id ) ); }
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_addresses_table(), axismundi_actors_texts_table(), axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_signing_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_meta_table(), array( 'actor_id' => $id ) ); $wpdb->delete( axismundi_actors_actors_table(), array( 'id' => $id ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php'; foreach ( $users as $user ) { wp_delete_user( $user ); }
	wp_set_current_user( $previous_user );
}
printf( "== %d checks, 0 failed ==\n", $checks );
