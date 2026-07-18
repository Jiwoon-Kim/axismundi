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
$ax_bridge_mention_object = 'https://example.com/notes/' . wp_generate_uuid4();
$ax_bridge_fallback_activity = '';
$ax_bridge_accept_activity = '';
$ax_bridge_update_activity = 'https://example.com/activities/' . wp_generate_uuid4();
$ax_bridge_quote_activity  = 'https://example.com/activities/' . wp_generate_uuid4();
$ax_bridge_quote_decision  = '';
$ax_bridge_quote_delete    = '';
$ax_bridge_quote_auth      = '';
$ax_bridge_quote_post      = 0;
$ax_bridge_diagnostics_before = get_option( 'ax_activitypub_bridge_inbox_diagnostics', null );

/** @param bool[] $results Results. */
function ax_bridge_inbox_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Keep fixture Post publication from producing an unrelated Create Activity. */
function ax_bridge_inbox_fixture_lifecycle_owner() : string {
	return 'fixture';
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

	add_filter( 'axismundi_op_post_lifecycle_owner', 'ax_bridge_inbox_fixture_lifecycle_owner', 100 );
	$ax_bridge_quote_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $ax_bridge_inbox_user, 'post_title' => 'Quote target', 'post_content' => '<p>Quoted.</p>' ) );
	remove_filter( 'axismundi_op_post_lifecycle_owner', 'ax_bridge_inbox_fixture_lifecycle_owner', 100 );
	$quoted_uri = $ax_bridge_quote_post > 0 ? add_query_arg( 'p', $ax_bridge_quote_post, home_url( '/' ) ) : '';
	update_post_meta( $ax_bridge_quote_post, AXISMUNDI_OP_POST_QUOTE_POLICY_META, 'anyone' );
	$quote_payload = array(
		'id'         => $ax_bridge_quote_activity,
		'type'       => 'QuoteRequest',
		'actor'      => $remote_uri,
		'object'     => $quoted_uri,
		'instrument' => array( 'id' => $remote_uri . '/statuses/quote', 'type' => 'Note', 'attributedTo' => $remote_uri, 'quote' => $quoted_uri ),
		'to'         => array( $local->get_uri() ),
	);
	$quote_request = new WP_REST_Request( 'POST', '/activitypub/1.0/inbox' );
	$quote_request->set_header( 'Content-Type', 'application/activity+json' );
	$quote_request->set_body( wp_json_encode( $quote_payload ) );
	$quote_response = ( new Activitypub\Rest\Inbox_Controller() )->create_item( $quote_request );
	$quote_stored   = axismundi_act_get( $ax_bridge_quote_activity );
	$quote_decision = $quote_stored instanceof Axismundi_Activity ? axismundi_act_get_quote_request_decision( $quote_stored->get_uri() ) : null;
	$quote_decision_payload = $quote_decision instanceof Axismundi_Activity ? $quote_decision->get_payload() : array();
	$ax_bridge_quote_decision = $quote_decision instanceof Axismundi_Activity ? $quote_decision->get_uri() : '';
	$ax_bridge_quote_auth = (string) ( $quote_decision_payload['result'] ?? '' );
	$quote_authorization = '' !== $ax_bridge_quote_auth ? axismundi_act_get_quote_authorization( $ax_bridge_quote_auth ) : null;
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'a verified QuoteRequest composes through the generic Inbox into one Activities-owned Accept and authorization', $quote_response instanceof WP_REST_Response && 202 === $quote_response->get_status() && $quote_stored instanceof Axismundi_Activity && $quote_decision instanceof Axismundi_Activity && 'Accept' === $quote_decision->get_type() && 'outbound' === $quote_decision->get_direction() && is_array( $quote_authorization ) && 'active' === $quote_authorization['status'] );
	$quote_revoked = '' !== $ax_bridge_quote_auth ? axismundi_act_revoke_quote_authorization( $ax_bridge_quote_auth, 'fixture' ) : null;
	$quote_deletes = '' !== $ax_bridge_quote_auth ? array_values( array_filter( axismundi_act_get_by_object( $ax_bridge_quote_auth, 50 ), static fn( Axismundi_Activity $item ) : bool => 'Delete' === $item->get_type() && 'outbound' === $item->get_direction() ) ) : array();
	$ax_bridge_quote_delete = isset( $quote_deletes[0] ) ? $quote_deletes[0]->get_uri() : '';
	$quote_delete_delivery  = '' !== $ax_bridge_quote_delete ? axismundi_activitypub_bridge_find_delivery( $ax_bridge_quote_delete ) : 0;
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'revoking that stamp records one privacy-minimal Delete and composes it into the Bridge delivery queue', is_array( $quote_revoked ) && 'revoked' === $quote_revoked['status'] && 1 === count( $quote_deletes ) && $quote_delete_delivery > 0 );

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
		'id'      => $ax_bridge_mention_object,
			'type'    => 'Note',
			'attributedTo' => $remote_uri,
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
	$mention_object = axismundi_op_remote_object_get( $ax_bridge_mention_object );
	ax_bridge_inbox_assert( $ax_bridge_inbox_results, 'a verified inbound Create caches its complete self-consistent embedded Object without a second network fetch', is_array( $mention_object ) && $remote_uri === $mention_object['attributed_to_uri'] && false !== strpos( (string) $mention_object['content'], 'Private content' ) );

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
	remove_filter( 'axismundi_op_post_lifecycle_owner', 'ax_bridge_inbox_fixture_lifecycle_owner', 100 );
	$wpdb->delete( axismundi_act_relations_table(), array( 'initiating_activity_uri' => $ax_bridge_inbox_activity ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_act_relations_table(), array( 'initiating_activity_uri' => $ax_bridge_actor_activity ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $ax_bridge_inbox_activity ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $ax_bridge_actor_activity ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $ax_bridge_mention_activity ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_op_remote_objects_table(), array( 'object_uri' => $ax_bridge_mention_object ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $ax_bridge_update_activity ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $ax_bridge_quote_activity ) ); // phpcs:ignore WordPress.DB
	if ( '' !== $ax_bridge_quote_decision ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $ax_bridge_quote_decision ) ); // phpcs:ignore WordPress.DB
		$delivery_id = axismundi_activitypub_bridge_find_delivery( $ax_bridge_quote_decision );
		if ( $delivery_id > 0 ) {
			for ( $attempt = 1; $attempt <= 10; ++$attempt ) {
				wp_clear_scheduled_hook( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $delivery_id, $attempt ) );
			}
			$wpdb->delete( axismundi_activitypub_bridge_delivery_table(), array( 'id' => $delivery_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		}
	}
	if ( '' !== $ax_bridge_quote_delete ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $ax_bridge_quote_delete ) ); // phpcs:ignore WordPress.DB
		$delivery_id = axismundi_activitypub_bridge_find_delivery( $ax_bridge_quote_delete );
		if ( $delivery_id > 0 ) {
			for ( $attempt = 1; $attempt <= 10; ++$attempt ) {
				wp_clear_scheduled_hook( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $delivery_id, $attempt ) );
			}
			$wpdb->delete( axismundi_activitypub_bridge_delivery_table(), array( 'id' => $delivery_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		}
	}
	if ( '' !== $ax_bridge_quote_auth ) {
		$wpdb->delete( axismundi_act_quote_authorizations_table(), array( 'authorization_uri_hash' => hash( 'sha256', $ax_bridge_quote_auth ) ) ); // phpcs:ignore WordPress.DB
	}
	if ( $ax_bridge_quote_post > 0 ) {
		wp_delete_post( $ax_bridge_quote_post, true );
	}
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
		$delivery_id = axismundi_activitypub_bridge_find_delivery( $ax_bridge_accept_activity );
		if ( $delivery_id > 0 ) {
			for ( $attempt = 1; $attempt <= 10; ++$attempt ) {
				wp_clear_scheduled_hook( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $delivery_id, $attempt ) );
			}
			$wpdb->delete( axismundi_activitypub_bridge_delivery_table(), array( 'id' => $delivery_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		}
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
