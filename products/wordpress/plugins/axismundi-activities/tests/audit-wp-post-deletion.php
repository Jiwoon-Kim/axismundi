<?php
/** Dev-only WP status versus permanent deletion; AX_C2S_HTTP_ORIGIN supports wp-env. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; };
$before = $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_activities_table() . ' ORDER BY id', ARRAY_A );
$principal_before = $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A );
$user_before = get_current_user_id();
$user = 0; $actor = null; $post_id = 0; $uri = ''; $recorded = array();
foreach ( $wpdb->get_results( 'SELECT identity_id,principal_id FROM ' . axismundi_actors_managers_table() . " WHERE principal_kind='wp_user'", ARRAY_A ) as $grant ) {
	$candidate = axismundi_actors_get_by_identity( (int) $grant['identity_id'] );
	if ( $candidate instanceof Axismundi_Actor && user_can( (int) $grant['principal_id'], 'delete_posts' ) && axismundi_actors_can_act_as( $candidate, (int) $grant['principal_id'] ) ) { $user = (int) $grant['principal_id']; $actor = $candidate; break; }
}
$assert( $actor instanceof Axismundi_Actor, 'Existing authorized WP author available' );
$owner = static fn() => 'axismundi';
$observe = static function ( $activity ) use ( &$recorded, &$uri ) { if ( $uri === $activity->get_object_uri() ) { $recorded[] = $activity->get_uri(); } };
add_filter( 'axismundi_op_post_lifecycle_owner', $owner, PHP_INT_MAX );
add_action( 'axismundi_act_activity_recorded', $observe, 999 );
$origin = rtrim( getenv( 'AX_C2S_HTTP_ORIGIN' ) ?: home_url(), '/' );
$host = wp_parse_url( home_url(), PHP_URL_HOST );
$port = wp_parse_url( home_url(), PHP_URL_PORT );
$host .= $port ? ':' . $port : '';
$read = static function ( string $accept ) use ( $origin, $host, &$uri ) {
	$path = wp_parse_url( $uri, PHP_URL_PATH ); $query = wp_parse_url( $uri, PHP_URL_QUERY );
	$response = wp_remote_get( $origin . $path . ( $query ? '?' . $query : '' ), array( 'headers' => array( 'Host' => $host, 'Accept' => $accept ), 'redirection' => 0, 'timeout' => 30 ) );
	if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
	return $response;
};
try {
	// No ambient authoring session: the fixture records its explicit Create below.
	wp_set_current_user( 0 );
	$post_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $user, 'post_title' => 'WP deletion policy fixture' ) );
	$assert( is_int( $post_id ) && $post_id > 0, 'WP source inserted' );
	$uri = axismundi_op_post_object_uri( get_post( $post_id ) );
	$created = axismundi_act_record_activity( array( 'type' => 'Create', 'actor' => $actor->get_uri(), 'object' => $uri, 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) ), 'outbound' );
	$assert( $created instanceof Axismundi_Activity, 'Explicit publication recorded' );
	$assert( 200 === wp_remote_retrieve_response_code( $read( 'application/activity+json' ) ), 'Published WP Object is retrievable before status changes' );
	foreach ( array( 'draft', 'private', 'trash' ) as $status ) {
		if ( 'trash' === $status ) { wp_trash_post( $post_id ); }
		else { wp_update_post( array( 'ID' => $post_id, 'post_status' => $status ) ); }
		$assert( $status === get_post_status( $post_id ), 'Real WP transition exercised: ' . $status );
		$events = axismundi_act_get_by_object( $uri );
		$assert( 1 === count( $events ) && 'Create' === $events[0]->get_type(), 'No Update/Delete for reversible status: ' . $status );
		foreach ( array( 'application/activity+json', 'text/html' ) as $accept ) {
			$response = $read( $accept );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$assert( 404 === wp_remote_retrieve_response_code( $response ) && 'Tombstone' !== ( $body['type'] ?? '' ), 'Unavailable WP source is 404 without Tombstone: ' . $status . ' ' . $accept );
		}
		if ( 'trash' !== $status ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
			$assert( $actor->get_uri() === axismundi_act_get_object_attribution( $uri ), 'Restored publication retains recorded attribution: ' . $status );
		}
	}
	$removed = wp_delete_post( $post_id, true );
	$assert( $removed instanceof WP_Post && null === get_post( $post_id ), 'Permanent deletion removes the source' );
	$events = axismundi_act_get_by_object( $uri );
	$deletes = array_filter( $events, static fn( $a ) => 'Delete' === $a->get_type() );
	$assert( 2 === count( $events ) && 1 === count( $deletes ), 'Permanent deletion records exactly one Delete alongside Create' );
	$index = axismundi_op_get_object_by_uri( $uri );
	$assert( null === axismundi_op_get_local_object( $uri ) && ( ! is_array( $index ) || 'Tombstone' !== ( $index['as2_type'] ?? '' ) ), 'WP deletion creates no OP body or registry Tombstone' );
	foreach ( array( 'application/activity+json', 'text/html' ) as $accept ) {
		$response = $read( $accept ); $body = json_decode( wp_remote_retrieve_body( $response ), true );
		$assert( 404 === wp_remote_retrieve_response_code( $response ) && 'Tombstone' !== ( $body['type'] ?? '' ), 'Permanent WP deletion stays 404 without a Tombstone: ' . $accept );
	}
} finally {
	remove_action( 'axismundi_act_activity_recorded', $observe, 999 );
	remove_filter( 'axismundi_op_post_lifecycle_owner', $owner, PHP_INT_MAX );
	$disable = static fn() => 'fixture';
	add_filter( 'axismundi_op_post_lifecycle_owner', $disable, PHP_INT_MAX );
	if ( $post_id && get_post( $post_id ) ) { wp_delete_post( $post_id, true ); }
	remove_filter( 'axismundi_op_post_lifecycle_owner', $disable, PHP_INT_MAX );
	foreach ( $recorded as $id ) { $a = axismundi_act_get( $id ); if ( $a ) { $wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $a->get_id() ) ); } $wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $id ) ); }
	if ( $uri ) {
		$wpdb->delete( axismundi_op_objects_table(), array( 'canonical_uri' => $uri ) );
		$wpdb->delete( axismundi_op_object_index_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) );
	}
	wp_set_current_user( $user_before );
}
$assert( $before === $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_activities_table() . ' ORDER BY id', ARRAY_A ), 'Existing ledger contents preserved' );
$assert( $principal_before === $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A ), 'Principal grants preserved' );
printf( "PASS: %d WP deletion policy checks\n", $checks );
