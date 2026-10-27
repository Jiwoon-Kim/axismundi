<?php
/** Real WP schedule lifecycle, frozen Account and atomic consumption. Dev-only. @package AxismundiActivities */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
$original_user = get_current_user_id();
$cookies = $_COOKIE;
$header = $_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] ?? null;
$users = $actors = $posts = array();
$checks = 0;
$http = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $label ); }
};
$owner = static fn() => 'axismundi';
$network = static function () use ( &$http ) { ++$http; return new WP_Error( 'audit_http', 'No network in this audit.' ); };
$fail_consume = static function ( string $sql ) : string {
	return str_contains( $sql, axismundi_act_submissions_table() ) && str_contains( $sql, "'completed'" ) && str_starts_with( $sql, 'UPDATE' ) ? 'INVALID SQL AUDIT_PENDING_CONSUME' : $sql;
};
add_filter( 'axismundi_actors_account_selection_enabled', '__return_true' );
add_filter( 'axismundi_actors_account_selection_adapter_available', '__return_true' );
add_filter( 'axismundi_op_post_lifecycle_owner', $owner, 99 );
add_filter( 'pre_http_request', $network );
try {
	global $wpdb;
	$assert( axismundi_act_install(), 'Pending schema installed and verified' );
	$user = wp_insert_user( array( 'user_login' => 'axschedule' . strtolower( wp_generate_password( 12, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$assert( ! is_wp_error( $user ), 'Temporary publisher created' );
	$users[] = (int) $user;
	wp_set_current_user( $user );
	foreach ( array( 'Person', 'Organization' ) as $type ) {
		$actor = axismundi_actors_create_local( array( 'actor_type' => $type, 'actor_scope' => 'Person' === $type ? 'user' : 'managed',
			'initial_owner_user_id' => $user, 'personal_owner_user_id' => 'Person' === $type ? $user : null,
			'preferred_username' => 'scheduled' . strtolower( wp_generate_password( 12, false, false ) ), 'display_name' => $type ) );
		$assert( $actor instanceof Axismundi_Actor, 'Account fixture created' );
		$actors[] = $actor->get_identity_id();
		$assert( ! is_wp_error( axismundi_actors_set_status( $actor->get_identity_id(), 'public' ) ), 'Account activated' );
	}
	$expiration = time() + 600;
	$token = WP_Session_Tokens::get_instance( $user )->create( $expiration );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user, $expiration, 'logged_in', $token );
	$assert( true === axismundi_actors_set_account_selection( $actors[1] ), 'Select Organization for scheduling' );
	$_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] = (string) $actors[1];
	$due = time() + 70;
	foreach ( array( 'deliver', 'revoked', 'legacy', 'atomic', 'cancel' ) as $name ) {
		$id = wp_insert_post( array( 'post_author' => $user, 'post_type' => 'post', 'post_status' => 'future', 'post_title' => 'Scheduled ' . $name,
			'post_date' => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $due ) ), 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $due ) ), true );
		$assert( ! is_wp_error( $id ) && 'future' === get_post_status( $id ), 'Core owns future schedule' );
		$posts[ $name ] = $id;
		$intent = axismundi_act_scheduled_post_submission( $id );
		$assert( $intent && 'pending' === $intent['state'] && $actors[1] === (int) $intent['actor_identity_id'], 'Frozen Organization intent stored' );
		$assert( 'wordpress-core' === $intent['source_owner'] && 'post' === $intent['source_kind'] && (int) $id === (int) $intent['source_ref'] && 'Create' === $intent['activity_type'], 'Source-neutral submission records explicit Create type' );
		$assert( array() === axismundi_act_get_by_object( $intent['object_uri'] ) && '' === axismundi_act_get_object_attribution( $intent['object_uri'] ), 'Pending has no Activity or attribution' );
	}
	$old = axismundi_act_scheduled_post_submission( $posts['deliver'] );
	wp_update_post( array( 'ID' => $posts['deliver'], 'post_title' => 'Scheduled replacement one' ) );
	wp_update_post( array( 'ID' => $posts['deliver'], 'post_title' => 'Scheduled replacement two' ) );
	$new = axismundi_act_scheduled_post_submission( $posts['deliver'] );
	$assert( $old['intent_uuid'] !== $new['intent_uuid'] && 'pending' === $new['state'], 'Editing replaces intent generation' );
	$assert( 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE source_owner = 'wordpress-core' AND source_kind = 'post' AND source_ref = %d", axismundi_act_submissions_table(), $posts['deliver'] ) ), 'Only one intent row per source triple' );
	wp_update_post( array( 'ID' => $posts['cancel'], 'post_status' => 'draft' ) );
	$assert( 'cancelled' === axismundi_act_scheduled_post_submission( $posts['cancel'] )['state'], 'Unscheduling cancels pending intent' );
	$wpdb->delete( axismundi_act_submissions_table(), axismundi_act_post_submission_source( $posts['legacy'] ) );
	$assert( true === axismundi_actors_set_account_selection( $actors[0] ), 'Switch to Person after scheduling' );
	$_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] = (string) $actors[0];
	wp_set_current_user( 0 );
	// sleep() can be interrupted by a child-process signal; the clock is the assertion.
	$due = strtotime( get_post( $posts['atomic'] )->post_date_gmt . ' UTC' );
	while ( time() <= $due ) { sleep( min( 5, $due - time() + 1 ) ); }
	$assert( time() >= $due, 'Real WordPress schedule is due before firing' );
	$errors = $wpdb->suppress_errors();
	add_filter( 'query', $fail_consume );
	try { check_and_publish_future_post( $posts['atomic'] ); }
	finally { remove_filter( 'query', $fail_consume ); $wpdb->suppress_errors( $errors ); }
	$atomic = axismundi_act_scheduled_post_submission( $posts['atomic'] );
	$assert( 'failed' === $atomic['state'] && array() === axismundi_act_get_by_object( $atomic['object_uri'] ), 'Intent consumption failure rolls back Create atomically: ' . wp_json_encode( array( $atomic['state'], $atomic['error_code'], get_post_status( $posts['atomic'] ), count( axismundi_act_get_by_object( $atomic['object_uri'] ) ) ) ) );
	check_and_publish_future_post( $posts['deliver'] );
	$done = axismundi_act_scheduled_post_submission( $posts['deliver'] );
	$activities = axismundi_act_get_by_object( $done['object_uri'] );
	$assert( 'publish' === get_post_status( $posts['deliver'] ) && 'completed' === $done['state'], 'WP fires schedule and consumes intent' );
	$assert( 1 === count( $activities ) && axismundi_actors_get_by_identity( $actors[1] )->get_uri() === $activities[0]->get_actor_uri(), 'Create uses frozen Organization, never current Person' );
	$payload = $activities[0]->get_payload();
	$audience = json_decode( $done['audience_json'], true );
	$assert( $payload['to'] === $audience['to'] && $payload['cc'] === $audience['cc'], 'Create uses frozen audience' );
	axismundi_act_on_object_publish_candidate( get_post( $posts['deliver'] ), $done['object_uri'], '' );
	$assert( 1 === count( axismundi_act_get_by_object( $done['object_uri'] ) ), 'Duplicate candidate cannot create twice' );
	$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $actors[1], 'principal_kind' => 'wp_user', 'principal_id' => $user ) );
	check_and_publish_future_post( $posts['revoked'] );
	$revoked = axismundi_act_scheduled_post_submission( $posts['revoked'] );
	$assert( 'publish' === get_post_status( $posts['revoked'] ) && 'failed' === $revoked['state'] && 'ax_act_pending_permission' === $revoked['error_code'], 'Revocation preserves WP publication but fails federation' );
	$assert( array() === axismundi_act_get_by_object( $revoked['object_uri'] ), 'Revocation cannot fallback to Person' );
	check_and_publish_future_post( $posts['legacy'] );
	$assert( array() === axismundi_act_get_by_object( axismundi_op_post_object_uri( get_post( $posts['legacy'] ) ) ), 'Legacy schedule without intent is not attributed retroactively' );
	$assert( 0 === $http, 'Scheduling and execution make no network calls' );
	printf( "PASS: %d scheduled publication checks\n", $checks );
} finally {
	remove_filter( 'query', $fail_consume );
	remove_filter( 'pre_http_request', $network );
	remove_filter( 'axismundi_op_post_lifecycle_owner', $owner, 99 );
	remove_filter( 'axismundi_actors_account_selection_enabled', '__return_true' );
	remove_filter( 'axismundi_actors_account_selection_adapter_available', '__return_true' );
	$_COOKIE = $cookies;
	if ( null === $header ) { unset( $_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] ); } else { $_SERVER['HTTP_X_AXISMUNDI_ACCOUNT'] = $header; }
	wp_set_current_user( $original_user );
	foreach ( $posts as $id ) {
		$uri = axismundi_op_post_object_uri( get_post( $id ) );
		$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) );
		$wpdb->delete( axismundi_act_submissions_table(), axismundi_act_post_submission_source( $id ) );
		wp_delete_post( $id, true );
		$wpdb->delete( axismundi_op_objects_table(), array( 'source_owner' => 'wordpress-core', 'source_kind' => 'post', 'source_ref' => $id ) );
	}
	foreach ( array_reverse( $actors ) as $id ) {
		foreach ( array( axismundi_actors_addresses_table(), axismundi_actors_managers_table(), axismundi_actors_signing_keys_table(), axismundi_actors_actors_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	foreach ( $users as $id ) { wp_delete_user( $id ); }
}
