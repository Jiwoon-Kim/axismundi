<?php
/** Explicit local-only Chrome fixtures. Run setup, verify the UI, then cleanup. */
defined( 'WP_CLI' ) && WP_CLI || exit( 1 );
if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) { throw new RuntimeException( 'Local wp-env only' ); }
global $wpdb;
$key = 'ax_community_moderation_ui_fixture';
$mode = getenv( 'AX_MOD_UI_MODE' );
if ( 'cleanup' === $mode ) {
	$state = get_option( $key );
	if ( ! is_array( $state ) ) { throw new RuntimeException( 'No fixture to clean' ); }
	foreach ( $state['objects'] as $uri ) {
		axismundi_op_delete_remote_object( $uri );
		foreach ( array( axismundi_op_objects_table() => 'canonical_uri', axismundi_op_object_relations_table() => 'source_object_uri', axismundi_op_thread_edges_table() => 'child_uri' ) as $table => $column ) { $wpdb->delete( $table, array( $column => $uri ) ); }
		$wpdb->delete( axismundi_op_object_index_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) );
	}
	foreach ( $state['actors'] as $id ) {
		$actor = axismundi_actors_get_by_identity( $id );
		if ( ! $actor ) { continue; }
		$state['activities'] = array_merge( $state['activities'], $wpdb->get_col( $wpdb->prepare( 'SELECT activity_uri FROM %i WHERE actor_uri=%s', axismundi_act_activities_table(), $actor->get_uri() ) ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE subject_actor_uri=%s OR object_actor_uri=%s', axismundi_act_relations_table(), $actor->get_uri(), $actor->get_uri() ) );
		foreach ( array( axismundi_actors_community_posts_table(), axismundi_actors_moderators_table(), axismundi_actors_moderators_state_table(), axismundi_actors_moderators_log_table() ) as $table ) { $wpdb->delete( $table, array( 'group_id' => $id ) ); }
		// Exact fixture IDs only; do not deactivate Actors or invalidate the operator's sessions.
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_signing_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_meta_table(), array( 'actor_id' => $id ) );
		$wpdb->delete( axismundi_actors_actors_table(), array( 'id' => $id ) );
	}
	foreach ( array_unique( $state['activities'] ) as $uri ) {
		$activity = axismundi_act_get( $uri );
		if ( ! $activity ) { continue; }
		$wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $activity->get_id() ) );
		$wpdb->delete( axismundi_act_activities_table(), array( 'id' => $activity->get_id() ) );
	}
	delete_option( $key );
	echo "Fixture removed.\n"; return;
}
if ( 'setup' !== $mode || get_option( $key ) ) { throw new RuntimeException( 'Choose setup/cleanup; an existing fixture must be cleaned first' ); }
$user = (int) getenv( 'AX_MOD_UI_USER' );
$person = axismundi_actors_get_by_uri( (string) getenv( 'AX_MOD_UI_PERSON' ) );
if ( ! $person || 'Person' !== $person->get_type() || true !== axismundi_act_outbox_authorize( $user, $person ) ) { throw new RuntimeException( 'Explicit authorized Person and User required' ); }
wp_set_current_user( $user );
$state = array( 'actors' => array(), 'objects' => array(), 'activities' => array() );
update_option( $key, $state, false );
remove_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', 10 );
add_action( 'axismundi_act_activity_recorded', static function ( $activity ) use ( &$state, $key ) { $state['activities'][] = $activity->get_uri(); update_option( $key, $state, false ); }, 999 );
$group = axismundi_actors_create_managed_actor( array( 'owner_user_id' => $user, 'actor_type' => 'Group', 'preferred_username' => 'axmodui' . strtolower( wp_generate_password( 8, false, false ) ), 'federation_profile' => 'community' ) );
if ( ! $group instanceof Axismundi_Actor ) { throw new RuntimeException( 'Group fixture creation failed' ); }
$state['actors'][] = $group->get_identity_id(); update_option( $key, $state, false );
$initialized = axismundi_act_initialize_moderators( $group, $person, $user );
if ( is_wp_error( $initialized ) ) { throw new RuntimeException( $initialized->get_error_message() ); }
$base = 'https://example.com/moderation-ui-' . wp_generate_uuid4();
$person_uri = $base . '/author';
$record = axismundi_actors_normalize_remote_actor_payload( array( 'id' => $person_uri, 'type' => 'Person', 'preferredUsername' => 'fixture', 'inbox' => $person_uri . '/inbox', 'outbox' => $person_uri . '/outbox' ), $person_uri );
$author = is_wp_error( $record ) ? $record : axismundi_actors_upsert_remote( $record );
if ( ! $author instanceof Axismundi_Actor ) { throw new RuntimeException( is_wp_error( $author ) ? $author->get_error_message() : 'Remote fixture failed' ); }
$state['actors'][] = $author->get_identity_id(); update_option( $key, $state, false );
// Leave this fixture without delivery endpoints: browser approval must not send externally.
$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => $author->get_identity_id() ) );
axismundi_act_write_relation( 'follow', $person_uri, $group->get_uri(), 'inbound', 'accepted', null, null );
foreach ( array( 'Approve fixture', 'Reject fixture' ) as $index => $name ) {
	$uri = $base . '/post-' . $index;
	$state['objects'][] = $uri; update_option( $key, $state, false );
	$object = array( 'id' => $uri, 'type' => 'Page', 'name' => $name, 'content' => 'Disposable local moderation screen fixture.', 'attributedTo' => $person_uri, 'audience' => $group->get_uri(), 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ), 'cc' => array( $group->get_uri() ) );
	$payload = array( 'id' => $uri . '/create', 'type' => 'Create', 'actor' => $person_uri, 'object' => $object, 'to' => array( $group->get_uri() ), 'cc' => array( 'https://www.w3.org/ns/activitystreams#Public' ) );
	$result = axismundi_activitypub_bridge_record_inbox( $payload, array(), false, $group );
	if ( is_wp_error( $result ) || ! axismundi_actors_get_community_post( $group->get_identity_id(), $uri ) ) { throw new RuntimeException( 'Pending fixture admission failed' ); }
}
echo wp_json_encode( array( 'group_id' => $group->get_identity_id(), 'moderator_uri' => $person->get_uri() ) ) . "\n";
