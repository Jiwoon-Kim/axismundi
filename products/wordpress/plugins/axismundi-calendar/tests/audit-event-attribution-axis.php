<?php
/** Stored Event attribution never derives from a User or current selection. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
global $wpdb;
$original_user = get_current_user_id();
$user = 0;
$actors = $posts = array();
$checks = 0;
$scope = null;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $label ); }
};
try {
	$assert( axismundi_cal_ready(), 'Existing Calendar schema is ready; audit does not install it' );
	$created = wp_insert_user( array( 'user_login' => 'axeventaxis' . strtolower( wp_generate_password( 10, false, false ) ),
		'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$assert( ! is_wp_error( $created ), 'Temporary author created' );
	$user = (int) $created;
	wp_set_current_user( $user );
	foreach ( array( 'Person', 'Organization' ) as $type ) {
		$actor = axismundi_actors_create_local( array( 'actor_type' => $type, 'actor_scope' => 'Person' === $type ? 'user' : 'managed',
			'local_user_id' => 'Person' === $type ? $user : null,
			'personal_owner_user_id' => 'Person' === $type ? $user : null, 'initial_owner_user_id' => $user,
			'preferred_username' => 'axeventaxis' . strtolower( wp_generate_password( 10, false, false ) ) ) );
		$assert( $actor instanceof Axismundi_Actor, 'Account fixture created' );
		$actors[] = $actor->get_identity_id();
	}
	$organization = axismundi_actors_get_by_identity( $actors[1] );
	$now = current_time( 'mysql', true );
	// Only the envelope/schedule read contract is needed here, not Calendar authoring or publication.
	foreach ( array( $actors[1], 0 ) as $stored ) {
		$post = wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_author' => $user,
			'post_status' => 'draft', 'post_title' => 'Stored Event attribution fixture' ), true );
		$assert( ! is_wp_error( $post ), 'Draft Event source created' );
		$posts[] = (int) $post;
		$assert( false !== $wpdb->insert( axismundi_cal_events_table(), array( 'post_id' => $post,
			'acting_actor_identity_id' => $stored, 'external_participation_url' => '', 'created_at' => $now, 'updated_at' => $now ) ), 'Envelope fixture inserted' );
		$assert( false !== $wpdb->insert( axismundi_cal_schedules_table(), array( 'event_post_id' => $post,
			'timezone' => 'UTC', 'end_timezone' => 'UTC', 'duration' => 'PT1H', 'recurrence_json' => '', 'location_text' => '',
			'dtstart_local' => '2030-01-01 10:00:00', 'dtend_local' => '2030-01-01 11:00:00',
			'ical_uid' => wp_generate_uuid4(), 'created_at' => $now, 'updated_at' => $now ) ), 'Schedule read fixture inserted' );
	}
	$assert( $organization->get_uri() === axismundi_cal_event_acting_actor_uri( $posts[0] ), 'Stored Organization wins over WordPress author Person' );
	axismundi_actors_set_acting_actor( $user, $actors[0] );
	$assert( $organization->get_uri() === axismundi_cal_event_acting_actor_uri( $posts[0] ), 'Changing current choice cannot reattribute an existing Event' );
	$assert( '' === axismundi_cal_event_acting_actor_uri( $posts[1] ), 'Missing stored attribution never falls back to post_author' );
	$assert( '' === axismundi_cal_authoring_actor_uri( $posts[1] ), 'Existing unattributed Event is not filed under the current writer' );
	$assert( 0 === axismundi_cal_resolve_event_acting_actor( $posts[1], array(), axismundi_cal_event_get( $posts[1] ) ), 'Ordinary edit preserves unknown attribution' );

	// Restrict the migration to these fixtures; do not backfill unrelated development data.
	$prefix = 'SELECT post_id FROM ' . axismundi_cal_events_table() . ' WHERE acting_actor_identity_id = 0';
	$scope = static function ( string $sql ) use ( $prefix, $posts, $wpdb ) : string {
		return $sql === $prefix ? $sql . $wpdb->prepare( ' AND post_id IN (%d,%d)', $posts[0], $posts[1] ) : $sql;
	};
	add_filter( 'query', $scope );
	$assert( 0 === axismundi_cal_backfill_event_acting_actors()
		&& 0 === axismundi_cal_event_acting_actor_identity( $posts[1] ), 'Backfill cannot invent an Actor from a User without a recorded Create' );
	$uri = axismundi_cal_event_object_uri( get_post( $posts[1] ) );
	$activity = axismundi_act_record_source_activity( array( 'type' => 'Create', 'actor' => $organization->get_uri(), 'object' => $uri ),
		'outbound', 'event-axis:' . wp_generate_uuid4() );
	$assert( $activity instanceof Axismundi_Activity, 'Historical Organization Create recorded' );
	$assert( 1 === axismundi_cal_backfill_event_acting_actors()
		&& $actors[1] === axismundi_cal_event_acting_actor_identity( $posts[1] ), 'Backfill recovers Organization from ledger, not author Person' );
	$assert( 0 === axismundi_cal_backfill_event_acting_actors(), 'Backfill is replay safe and leaves stored attribution alone' );
} finally {
	if ( null !== $scope ) { remove_filter( 'query', $scope ); }
	foreach ( $posts as $id ) {
		$post = get_post( $id );
		if ( $post instanceof WP_Post ) {
			$uri = axismundi_cal_event_object_uri( $post );
			$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) );
		}
		$wpdb->delete( axismundi_cal_schedules_table(), array( 'event_post_id' => $id ) );
		$wpdb->delete( axismundi_cal_events_table(), array( 'post_id' => $id ) );
		wp_delete_post( $id, true );
		$wpdb->delete( axismundi_op_objects_table(), array( 'source_owner' => 'wordpress-core', 'source_kind' => 'post', 'source_ref' => $id ) );
	}
	foreach ( array_reverse( $actors ) as $id ) {
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_addresses_table(), axismundi_actors_signing_keys_table(), axismundi_actors_actors_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $id ) );
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	wp_set_current_user( $original_user );
	if ( $user > 0 ) { wp_delete_user( $user ); }
}
printf( "PASS: %d Event attribution axis checks\n", $checks );
