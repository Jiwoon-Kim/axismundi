<?php
/**
 * Optional Note lifecycle -> Bridge delivery composition regression (dev-only).
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_bnd_results      = array();
$ax_bnd_user_ids     = array();
$ax_bnd_identity_ids = array();
$ax_bnd_post_ids     = array();
$ax_bnd_object_uris  = array();
$ax_bnd_activity_uris = array();
$ax_bnd_previous_user = get_current_user_id();
$GLOBALS['ax_bnd_http'] = 0;

/** @param bool[] $results Results. */
function ax_bnd_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Prove enqueue composition performs no network request. */
function ax_bnd_http( $preempt ) {
	++$GLOBALS['ax_bnd_http'];
	return $preempt;
}

try {
	add_filter( 'pre_http_request', 'ax_bnd_http' );
	$login = 'ax_bnd_' . strtolower( wp_generate_password( 8, false, false ) );
	$uid   = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_bnd_user_ids[] = $uid;
	$local = axismundi_actors_ensure_for_user( $uid );
	if ( $local instanceof Axismundi_Actor ) {
		$ax_bnd_identity_ids[] = $local->get_identity_id();
		axismundi_actors_register_handle( $local->get_identity_id(), $login );
		axismundi_actors_set_status( $local->get_identity_id(), 'public' );
		axismundi_actors_set_default_language( $local->get_identity_id(), 'en' );
		$local = axismundi_actors_get_by_uri( $local->get_uri() );
	}
	wp_set_current_user( $uid );

	$remote_uri = 'https://example.com/users/' . wp_generate_uuid4();
	$remote     = axismundi_actors_upsert_remote(
		array(
			'uri'                => $remote_uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'note_peer',
			'display_name'       => 'Note Peer',
			'profile_url'        => $remote_uri,
			'endpoints'          => array( 'inbox' => 'https://example.com/note-inbox', 'outbox' => 'https://example.com/note-outbox' ),
			'payload'            => array( 'id' => $remote_uri, 'type' => 'Person', 'preferredUsername' => 'note_peer' ),
		)
	);
	if ( $remote instanceof Axismundi_Actor ) {
		$ax_bnd_identity_ids[] = $remote->get_identity_id();
	}

	$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $uid, 'post_content' => '<p>Direct Note one.</p>' ) );
	$ax_bnd_post_ids[] = $post_id;
	axismundi_note_save_envelope(
		$post_id,
		array(
			'visibility' => 'mentioned',
			'language'   => 'en',
			'mentions'   => array( $remote_uri ),
		)
	);
	$envelope = axismundi_note_get( $post_id );
	$object_uri = is_array( $envelope ) ? axismundi_note_object_uri( (string) $envelope['local_uuid'] ) : '';
	$ax_bnd_object_uris[] = $object_uri;
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );

	$create = axismundi_act_get_object_lifecycle( $object_uri );
	if ( $create instanceof Axismundi_Activity ) {
		$ax_bnd_activity_uris[] = $create->get_uri();
	}
	$create_job = $create instanceof Axismundi_Activity ? axismundi_activitypub_bridge_get_delivery( axismundi_activitypub_bridge_find_delivery( $create->get_uri() ) ) : null;
	$create_payload = is_object( $create_job ) ? json_decode( (string) $create_job->payload_json, true ) : array();
	$create_inboxes = is_object( $create_job ) ? json_decode( (string) $create_job->inboxes_json, true ) : array();
	ax_bnd_assert(
		$ax_bnd_results,
		'a mentioned-only Note Create queues one embedded Object to the exact remote inbox',
		$create instanceof Axismundi_Activity
		&& 'Create' === $create->get_type()
		&& is_object( $create_job )
		&& array( 'https://example.com/note-inbox' ) === $create_inboxes
		&& $remote_uri === ( $create_payload['to'][0] ?? '' )
		&& $object_uri === ( $create_payload['object']['id'] ?? '' )
	);

	wp_update_post( array( 'ID' => $post_id, 'post_content' => '<p>Direct Note two.</p>' ) );
	$update = axismundi_act_get_object_lifecycle( $object_uri );
	if ( $update instanceof Axismundi_Activity ) {
		$ax_bnd_activity_uris[] = $update->get_uri();
	}
	$update_job = $update instanceof Axismundi_Activity ? axismundi_activitypub_bridge_get_delivery( axismundi_activitypub_bridge_find_delivery( $update->get_uri() ) ) : null;
	$update_payload = is_object( $update_job ) ? json_decode( (string) $update_job->payload_json, true ) : array();
	ax_bnd_assert( $ax_bnd_results, 'a later Note snapshot queues one Update with the edited embedded representation', $update instanceof Axismundi_Activity && 'Update' === $update->get_type() && is_object( $update_job ) && false !== strpos( (string) ( $update_payload['object']['content'] ?? '' ), 'Direct Note two.' ) );

	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
	$delete = axismundi_act_get_object_lifecycle( $object_uri );
	if ( $delete instanceof Axismundi_Activity ) {
		$ax_bnd_activity_uris[] = $delete->get_uri();
	}
	$delete_job = $delete instanceof Axismundi_Activity ? axismundi_activitypub_bridge_get_delivery( axismundi_activitypub_bridge_find_delivery( $delete->get_uri() ) ) : null;
	$delete_payload = is_object( $delete_job ) ? json_decode( (string) $delete_job->payload_json, true ) : array();
	ax_bnd_assert( $ax_bnd_results, 'withdrawal queues one addressed privacy-minimal Delete without embedding the Note', $delete instanceof Axismundi_Activity && 'Delete' === $delete->get_type() && is_object( $delete_job ) && $object_uri === ( $delete_payload['object'] ?? '' ) && ! is_array( $delete_payload['object'] ?? null ) && $remote_uri === ( $delete_payload['to'][0] ?? '' ) );
	ax_bnd_assert( $ax_bnd_results, 'Note lifecycle composition only enqueues and performs no HTTP request', 0 === $GLOBALS['ax_bnd_http'] );
} finally {
	remove_filter( 'pre_http_request', 'ax_bnd_http' );
	foreach ( array_unique( $ax_bnd_activity_uris ) as $activity_uri ) {
		$wpdb->delete( axismundi_activitypub_bridge_delivery_table(), array( 'activity_uri_hash' => hash( 'sha256', $activity_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_unique( $ax_bnd_object_uris ) as $object_uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $object_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_unique( $ax_bnd_post_ids ) as $post_id ) {
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( get_post( (int) $post_id ) instanceof WP_Post ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_unique( $ax_bnd_identity_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( $ax_bnd_user_ids ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_bnd_user_ids ) as $uid ) {
			if ( get_userdata( (int) $uid ) ) {
				wp_delete_user( (int) $uid );
			}
	}
	}
	wp_set_current_user( $ax_bnd_previous_user );
}

$ax_bnd_failures = count( array_filter( $ax_bnd_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_bnd_results ), $ax_bnd_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_bnd_failures > 0 ? 1 : 0 );
}
exit( $ax_bnd_failures > 0 ? 1 : 0 );
