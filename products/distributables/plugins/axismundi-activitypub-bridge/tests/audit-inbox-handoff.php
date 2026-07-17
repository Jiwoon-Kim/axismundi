<?php
/**
 * Existing Inbox action composition regression (dev-only; dist-excluded).
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_bridge_inbox_results   = array();
$ax_bridge_inbox_user      = 0;
$ax_bridge_inbox_actor_ids = array();
$ax_bridge_inbox_host      = 'example.com';
$ax_bridge_instance_before = axismundi_actors_get_instance( $ax_bridge_inbox_host );
$ax_bridge_schedule_before = wp_next_scheduled( 'axismundi_actors_cache_remote_instance', array( $ax_bridge_inbox_host ) );
$ax_bridge_inbox_activity  = 'https://example.com/activities/' . wp_generate_uuid4();
$ax_bridge_actor_activity  = 'https://example.com/activities/' . wp_generate_uuid4();
$ax_bridge_mention_activity = 'https://example.com/activities/' . wp_generate_uuid4();
$ax_bridge_fallback_activity = '';
$ax_bridge_accept_activity = '';
$ax_bridge_update_activity = 'https://example.com/activities/' . wp_generate_uuid4();
$ax_bridge_diagnostics_before = get_option( 'ax_activitypub_bridge_inbox_diagnostics', null );

/** @param bool[] $results Results. */
function ax_bridge_inbox_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	// The bridge assertion needs a missing instance row. Preserve shared fixture state and
	// restore it in `finally`; the remote Actor repository intentionally accepts only hosts
	// that pass WordPress's public-network URL validation, so an invented hostname is not a
	// valid isolation mechanism here.
	wp_clear_scheduled_hook( 'axismundi_actors_cache_remote_instance', array( $ax_bridge_inbox_host ) );
	$wpdb->delete( axismundi_actors_instances_table(), array( 'host_hash' => hash( 'sha256', $ax_bridge_inbox_host ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture setup; exact row is restored in finally.

	$login = 'ax_bridge_' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_bridge_inbox_user = (int) wp_insert_user(
		array(
			'user_login' => $login,
			'user_pass'  => wp_generate_password(),
			'role'       => 'author',
		)
	);
	$local = axismundi_actors_ensure_for_user( $ax_bridge_inbox_user );
	if ( $local instanceof Axismundi_Actor ) {
		$ax_bridge_inbox_actor_ids[] = $local->get_identity_id();
		axismundi_actors_register_handle( $local->get_identity_id(), $login );
		axismundi_actors_set_status( $local->get_identity_id(), 'public' );
		$local = axismundi_actors_get_for_user( $ax_bridge_inbox_user );
	}

	$remote_uri = 'https://' . $ax_bridge_inbox_host . '/users/' . wp_generate_uuid4();
	$remote     = axismundi_actors_upsert_remote(
		array(
			'uri'                => $remote_uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'bridge_remote',
			'display_name'       => 'Bridge Remote',
			'profile_url'        => $remote_uri,
			'endpoints'          => array(
				'inbox'  => $remote_uri . '/inbox',
				'outbox' => $remote_uri . '/outbox',
			),
			'payload'            => array( 'id' => $remote_uri, 'type' => 'Person', 'preferredUsername' => 'bridge_remote' ),
		)
	);
	if ( $remote instanceof Axismundi_Actor ) {
		$ax_bridge_inbox_actor_ids[] = $remote->get_identity_id();
	}
	// Remote discovery has its own first-fill hook. Remove that event so this fixture proves
	// the verified Follow handoff independently schedules a missing host rather than merely
	// observing work queued by fixture setup.
	wp_clear_scheduled_hook( 'axismundi_actors_cache_remote_instance', array( $ax_bridge_inbox_host ) );
	$wpdb->delete( axismundi_actors_instances_table(), array( 'host_hash' => hash( 'sha256', $ax_bridge_inbox_host ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- restore shared state in finally after exercising the missing-host path.

	$payload = array(
		'id'     => $ax_bridge_inbox_activity,
		'type'   => 'Follow',
		'actor'  => $remote_uri,
		'object' => $local instanceof Axismundi_Actor ? $local->get_uri() : '',
		'to'     => $local instanceof Axismundi_Actor ? array( $local->get_uri() ) : array(),
	);
	$request = new WP_REST_Request( 'POST', '/activitypub/1.0/inbox' );
	$request->set_header( 'Content-Type', 'application/activity+json' );
	$request->set_body( wp_json_encode( $payload ) );
	$response = ( new Activitypub\Rest\Inbox_Controller() )->create_item( $request );
	$stored   = axismundi_act_get( $ax_bridge_inbox_activity );
	$relation = $local instanceof Axismundi_Actor ? axismundi_act_get_relation( 'follow', $remote_uri, $local->get_uri() ) : null;
	$ax_bridge_accept_activity = is_array( $relation ) ? (string) ( $relation['state_activity_uri'] ?? '' ) : '';
	$accept = '' !== $ax_bridge_accept_activity ? axismundi_act_get( $ax_bridge_accept_activity ) : null;
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'the shared Inbox action composition returns 202', $response instanceof WP_REST_Response && 202 === $response->get_status() );
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'the shared Inbox records the full Activity once in the Axismundi ledger', $stored instanceof Axismundi_Activity && 'inbound' === $stored->get_direction() );
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'the inbound Follow auto-accepts through an outbound Activity when approval is not required', is_array( $relation ) && 'accepted' === (string) $relation['state'] && 'inbound' === (string) $relation['direction'] && $accept instanceof Axismundi_Activity && 'outbound' === $accept->get_direction() );
	$instance_fill = wp_next_scheduled( 'axismundi_actors_cache_remote_instance', array( $ax_bridge_inbox_host ) );
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'verified Follow traffic queues one missing instance-cache fill without refreshing the Actor snapshot', false !== $instance_fill && null === axismundi_actors_get_instance( $ax_bridge_inbox_host ) );

	$update_payload = array(
		'id'     => $ax_bridge_update_activity,
		'type'   => 'Update',
		'actor'  => $remote_uri,
		'to'     => array( $local->get_uri() ),
		'object' => array(
			'id'                => $remote_uri,
			'type'              => 'Person',
			'preferredUsername' => 'bridge_remote',
			'name'              => 'Bridge Remote Updated',
			'url'               => $remote_uri,
			'inbox'             => $remote_uri . '/inbox',
			'outbox'            => $remote_uri . '/outbox',
		),
	);
	$update_request = new WP_REST_Request( 'POST', '/activitypub/1.0/inbox' );
	$update_request->set_header( 'Content-Type', 'application/activity+json' );
	$update_request->set_body( wp_json_encode( $update_payload ) );
	$update_response = ( new Activitypub\Rest\Inbox_Controller() )->create_item( $update_request );
	$updated_remote  = axismundi_actors_get_by_uri( $remote_uri );
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'a verified complete Update(Actor) refreshes the existing cache row and records the Activity', $update_response instanceof WP_REST_Response && 202 === $update_response->get_status() && $updated_remote instanceof Axismundi_Actor && 'Bridge Remote Updated' === $updated_remote->get_display_name() && axismundi_act_get( $ax_bridge_update_activity ) instanceof Axismundi_Activity );

	$actor_payload       = $payload;
	$actor_payload['id'] = $ax_bridge_actor_activity;
	$actor_request       = new WP_REST_Request( 'POST', '/activitypub/1.0/actors/' . $ax_bridge_inbox_user . '/inbox' );
	$actor_request->set_param( 'user_id', $ax_bridge_inbox_user );
	$actor_request->set_param( 'type', 'Follow' );
	$actor_request->set_header( 'Content-Type', 'application/activity+json' );
	$actor_request->set_body( wp_json_encode( $actor_payload ) );
	$actor_response = ( new Activitypub\Rest\Actors_Inbox_Controller() )->create_item( $actor_request );
	$actor_stored   = axismundi_act_get( $ax_bridge_actor_activity );
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'the per-Actor Inbox action composition returns 202', $actor_response instanceof WP_REST_Response && 202 === $actor_response->get_status() );
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'the per-Actor Inbox records the Activity through the same URI-keyed ledger', $actor_stored instanceof Axismundi_Activity && 'inbound' === $actor_stored->get_direction() );
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'official Inbox persistence remains dormant for both controller paths', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND (post_content LIKE %s OR post_content LIKE %s)", 'ap_inbox', '%' . $wpdb->esc_like( $ax_bridge_inbox_activity ) . '%', '%' . $wpdb->esc_like( $ax_bridge_actor_activity ) . '%' ) ) ); // phpcs:ignore WordPress.DB

	$replay = ( new Activitypub\Rest\Inbox_Controller() )->create_item( $request );
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'an identical delivery replay is idempotently acknowledged', $replay instanceof WP_REST_Response && 202 === $replay->get_status() && $stored->get_id() === axismundi_act_get( $ax_bridge_inbox_activity )->get_id() );

	$mention_payload = array(
		'id'     => $ax_bridge_mention_activity,
		'type'   => 'Create',
		'actor'  => $remote_uri,
		'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		'object' => array(
			'id'      => 'https://example.com/notes/' . wp_generate_uuid4(),
			'type'    => 'Note',
			'to'      => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'tag'     => array( array( 'type' => 'Mention', 'href' => $local->get_uri(), 'name' => '@fixture@example.test' ) ),
			'content' => '<p>Private content is not copied into diagnostics.</p>',
		),
	);
	$mention_request = new WP_REST_Request( 'POST', '/activitypub/1.0/inbox' );
	$mention_request->set_header( 'Content-Type', 'application/activity+json' );
	$mention_request->set_body( wp_json_encode( $mention_payload ) );
	$mention_response = ( new Activitypub\Rest\Inbox_Controller() )->create_item( $mention_request );
	$mention_stored   = axismundi_act_get( $ax_bridge_mention_activity );
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'a verified shared-Inbox Mention href can supplement an otherwise absent local audience target', $mention_response instanceof WP_REST_Response && 202 === $mention_response->get_status() && $mention_stored instanceof Axismundi_Activity );

	$untargeted                  = $payload;
	$ax_bridge_fallback_activity = 'https://example.com/activities/' . wp_generate_uuid4();
	$untargeted['id']            = $ax_bridge_fallback_activity;
	$untargeted['actor']         = $local->get_uri();
	$untargeted['to']            = array( $local->get_uri() );
	$untargeted_request = new WP_REST_Request( 'POST', '/activitypub/1.0/actors/' . $ax_bridge_inbox_user . '/inbox' );
	$untargeted_request->set_param( 'user_id', $ax_bridge_inbox_user );
	$untargeted_request->set_param( 'type', 'Follow' );
	$untargeted_request->set_header( 'Content-Type', 'application/activity+json' );
	$untargeted_request->set_body( wp_json_encode( $untargeted ) );
	$untargeted_response = ( new Activitypub\Rest\Actors_Inbox_Controller() )->create_item( $untargeted_request );
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'an Activity the bridge cannot claim falls back to official Inbox storage instead of being lost', $untargeted_response instanceof WP_REST_Response && 202 === $untargeted_response->get_status() && null === axismundi_act_get( $untargeted['id'] ) && 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_content LIKE %s", 'ap_inbox', '%' . $wpdb->esc_like( $untargeted['id'] ) . '%' ) ) ); // phpcs:ignore WordPress.DB
	$diagnostics = axismundi_activitypub_bridge_inbox_diagnostics();
	$diagnostic_json = wp_json_encode( $diagnostics );
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'bounded diagnostics distinguish recorded and unclaimed Inbox outcomes without copying payload content', count( $diagnostics ) <= 50 && str_contains( $diagnostic_json, 'recorded' ) && str_contains( $diagnostic_json, 'unclaimed' ) && str_contains( $diagnostic_json, 'ax_bridge_inbox_actor' ) && ! str_contains( $diagnostic_json, 'Private content' ) );
} finally {
	$wpdb->delete( axismundi_act_relations_table(), array( 'initiating_activity_uri' => $ax_bridge_inbox_activity ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_act_relations_table(), array( 'initiating_activity_uri' => $ax_bridge_actor_activity ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $ax_bridge_inbox_activity ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $ax_bridge_actor_activity ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $ax_bridge_mention_activity ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $ax_bridge_update_activity ) ); // phpcs:ignore WordPress.DB
	wp_clear_scheduled_hook( 'axismundi_actors_cache_remote_instance', array( $ax_bridge_inbox_host ) );
	$wpdb->delete( axismundi_actors_instances_table(), array( 'host_hash' => hash( 'sha256', $ax_bridge_inbox_host ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- remove fixture state before restoring the saved row.
	if ( is_array( $ax_bridge_instance_before ) ) {
		$wpdb->replace( axismundi_actors_instances_table(), $ax_bridge_instance_before ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- restore the exact row, including identity and timestamps, captured before fixture setup.
	}
	if ( false !== $ax_bridge_schedule_before ) {
		wp_schedule_single_event( max( time() + 1, (int) $ax_bridge_schedule_before ), 'axismundi_actors_cache_remote_instance', array( $ax_bridge_inbox_host ) );
	}
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'the fixture restores the pre-existing instance cache row after exercising the missing-host path', $ax_bridge_instance_before === axismundi_actors_get_instance( $ax_bridge_inbox_host ) );
	if ( '' !== $ax_bridge_accept_activity ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $ax_bridge_accept_activity ) ); // phpcs:ignore WordPress.DB
		$spools = get_posts( array( 'post_type' => 'ap_outbox', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_activitypub_external_activity_uri', 'meta_value' => $ax_bridge_accept_activity ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		foreach ( $spools as $spool_id ) {
			wp_delete_post( (int) $spool_id, true );
		}
	}
	if ( '' !== $ax_bridge_fallback_activity ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->posts} WHERE post_type = %s AND post_content LIKE %s", 'ap_inbox', '%' . $wpdb->esc_like( $ax_bridge_fallback_activity ) . '%' ) ); // phpcs:ignore WordPress.DB
	}
	foreach ( array_unique( $ax_bridge_inbox_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB
	}
	if ( $ax_bridge_inbox_user > 0 && get_userdata( $ax_bridge_inbox_user ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $ax_bridge_inbox_user );
	}
	if ( null === $ax_bridge_diagnostics_before ) {
		delete_option( 'ax_activitypub_bridge_inbox_diagnostics' );
	} else {
		update_option( 'ax_activitypub_bridge_inbox_diagnostics', $ax_bridge_diagnostics_before, false );
	}
}

$ax_bridge_inbox_failures = count( array_filter( $ax_bridge_inbox_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_bridge_inbox_results ), $ax_bridge_inbox_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_bridge_inbox_failures > 0 ? 1 : 0 );
}
exit( $ax_bridge_inbox_failures > 0 ? 1 : 0 );
