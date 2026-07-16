<?php
/**
 * Actor transport fields and external delivery regression (dev-only).
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_bridge_delivery_results   = array();
$ax_bridge_delivery_user      = 0;
$ax_bridge_delivery_actor_ids = array();
$ax_bridge_delivery_spool     = 0;
$ax_bridge_social_spools      = array();
$ax_bridge_social_uris        = array();
$GLOBALS['ax_bridge_delivery_http_args'] = array();

/** @param bool[] $results Results. */
function ax_bridge_delivery_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Capture the signed HTTP request without network delivery. */
function ax_bridge_delivery_http_mock( $response, array $args, string $url ) {
	unset( $response );
	$GLOBALS['ax_bridge_delivery_http_args'] = array( 'url' => $url, 'args' => $args );
	return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 202, 'message' => 'Accepted' ), 'cookies' => array(), 'filename' => null );
}

try {
	$login = 'ax_delivery_' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_bridge_delivery_user = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$local = axismundi_actors_ensure_for_user( $ax_bridge_delivery_user );
	if ( $local instanceof Axismundi_Actor ) {
		$ax_bridge_delivery_actor_ids[] = $local->get_identity_id();
		axismundi_actors_register_handle( $local->get_identity_id(), $login );
		axismundi_actors_set_status( $local->get_identity_id(), 'public' );
		$local = axismundi_actors_get_for_user( $ax_bridge_delivery_user );
	}

	$fields = $local instanceof Axismundi_Actor ? axismundi_activitypub_bridge_actor_transport_fields( array(), $local ) : array();
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'Bridge supplies Inbox, sharedInbox, and publicKey but does not own Outbox representation', isset( $fields['inbox'], $fields['endpoints']['sharedInbox'], $fields['publicKey']['publicKeyPem'] ) && ! isset( $fields['outbox'] ) );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'publicKey owner and id use the Axismundi Actor identity', $local instanceof Axismundi_Actor && $local->get_uri() === $fields['publicKey']['owner'] && $local->get_uri() . '#main-key' === $fields['publicKey']['id'] );

	$resource   = 'acct:' . $local->get_preferred_username() . '@' . axismundi_actors_webfinger_authority();
	$webfinger  = axismundi_activitypub_bridge_webfinger_data( new WP_Error( 'official_not_found' ), $resource );
	$self_links = is_array( $webfinger )
		? array_values( array_filter( (array) ( $webfinger['links'] ?? array() ), static fn( array $link ) : bool => 'self' === ( $link['rel'] ?? '' ) ) )
		: array();
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'official WebFinger resolves a public Axismundi handle to its Actor identity', is_array( $webfinger ) && 'acct:' . substr( $resource, 5 ) === $webfinger['subject'] && in_array( $local->get_uri(), $webfinger['aliases'], true ) );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'WebFinger advertises the canonical ActivityStreams Actor document', isset( $self_links[0]['type'], $self_links[0]['href'] ) && 'application/activity+json' === $self_links[0]['type'] && $local->get_uri() === $self_links[0]['href'] );
	$sentinel = new WP_Error( 'official_owner' );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'non-Axismundi WebFinger resources retain their existing provider result', $sentinel === axismundi_activitypub_bridge_webfinger_data( $sentinel, 'acct:remote@example.com' ) );

	$request = new WP_REST_Request( 'GET', '/axismundi/v1/actors/' . $local->get_uuid() . '/outbox' );
	$request->set_param( 'uuid', $local->get_uuid() );
	$outbox = $local instanceof Axismundi_Actor ? axismundi_op_get_actor_outbox( $request ) : null;
	$data = $outbox instanceof WP_REST_Response ? $outbox->get_data() : array();
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'Object Projections serves the Activities-backed Actor OrderedCollection independently of Bridge transport', isset( $data['type'], $data['attributedTo'] ) && 'OrderedCollection' === $data['type'] && $local->get_uri() === $data['attributedTo'] );

	$activity_uri = home_url( '/activities/' . wp_generate_uuid4() . '/' );
	$remote_uri   = 'https://example.com/users/' . wp_generate_uuid4();
	$remote       = axismundi_actors_upsert_remote(
		array(
			'uri'                => $remote_uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'delivery_remote',
			'display_name'       => 'Delivery Remote',
			'profile_url'        => $remote_uri,
			'endpoints'          => array( 'inbox' => 'https://example.com/inbox', 'outbox' => 'https://example.com/outbox' ),
			'payload'            => array( 'id' => $remote_uri, 'type' => 'Person', 'preferredUsername' => 'delivery_remote' ),
		)
	);
	if ( $remote instanceof Axismundi_Actor ) {
		$ax_bridge_delivery_actor_ids[] = $remote->get_identity_id();
	}
	$remote_follow = $local instanceof Axismundi_Actor && $remote instanceof Axismundi_Actor
		? axismundi_act_follow_remote_actor( $local, $remote )
		: new WP_Error( 'fixture_actor_missing' );
	$follow_uri    = is_array( $remote_follow ) ? (string) ( $remote_follow['initiating_activity_uri'] ?? '' ) : '';
	$follow_spools = '' !== $follow_uri
		? get_posts(
			array(
				'post_type'      => 'ap_outbox',
				'post_status'    => array( 'pending', 'publish' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_activitypub_external_activity_uri', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $follow_uri, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		)
		: array();
	$ax_bridge_social_spools = array_merge( $ax_bridge_social_spools, array_map( 'intval', $follow_spools ) );
	$ax_bridge_social_uris[] = $follow_uri;
	$follow_payload = '' !== $follow_uri && isset( $follow_spools[0] ) ? json_decode( (string) get_post_field( 'post_content', (int) $follow_spools[0] ), true ) : array();
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'a remote Follow queues exactly one spool addressed to the cached Actor inbox', 1 === count( $follow_spools ) && 'Follow' === ( $follow_payload['type'] ?? '' ) && in_array( $remote_uri, (array) ( $follow_payload['to'] ?? array() ), true ) );

	$inbound_follow_uri = 'https://example.com/activities/' . wp_generate_uuid4();
	$inbound_follow     = $local instanceof Axismundi_Actor
		? axismundi_act_record_activity( array( 'id' => $inbound_follow_uri, 'type' => 'Follow', 'actor' => $remote_uri, 'object' => $local->get_uri() ), 'inbound' )
		: new WP_Error( 'fixture_actor_missing' );
	$remote_accept      = $local instanceof Axismundi_Actor && $remote instanceof Axismundi_Actor
		? axismundi_act_get_relation( 'follow', $remote->get_uri(), $local->get_uri() )
		: null;
	$accept_uri         = is_array( $remote_accept ) ? (string) ( $remote_accept['state_activity_uri'] ?? '' ) : '';
	$accept_spools      = '' !== $accept_uri
		? get_posts(
			array(
				'post_type'      => 'ap_outbox',
				'post_status'    => array( 'pending', 'publish' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_activitypub_external_activity_uri', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $accept_uri, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		)
		: array();
	$ax_bridge_social_spools = array_merge( $ax_bridge_social_spools, array_map( 'intval', $accept_spools ) );
	$ax_bridge_social_uris[] = $inbound_follow_uri;
	$ax_bridge_social_uris[] = $accept_uri;
	$accept_payload = '' !== $accept_uri && isset( $accept_spools[0] ) ? json_decode( (string) get_post_field( 'post_content', (int) $accept_spools[0] ), true ) : array();
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'auto-accepting an inbound remote Follow queues one Accept addressed back to that Actor', $inbound_follow instanceof Axismundi_Activity && 1 === count( $accept_spools ) && 'Accept' === ( $accept_payload['type'] ?? '' ) && $inbound_follow_uri === ( $accept_payload['object'] ?? '' ) && in_array( $remote_uri, (array) ( $accept_payload['to'] ?? array() ), true ) );

	$payload = array( 'id' => $activity_uri, 'type' => 'Like', 'actor' => $local->get_uri(), 'object' => 'https://example.com/objects/liked', 'to' => array( $remote_uri ) );
	$sender       = axismundi_activitypub_bridge_sender( $local );
	$rejected     = Activitypub\deliver_activity( $payload, array_merge( $sender, array( 'private_key' => 'secret' ) ), array( 'https://example.com/inbox' ) );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the transport API rejects caller-supplied private key material', is_wp_error( $rejected ) && 'activitypub_external_delivery_private_key' === $rejected->get_error_code() );

	$recorded = axismundi_act_record_activity( $payload, 'outbound' );
	if ( is_wp_error( $recorded ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI diagnostic.
		printf( "[DEBUG] outbound record error: %s — %s\n", $recorded->get_error_code(), $recorded->get_error_message() );
	}
	$queued = get_posts(
		array(
			'post_type'      => 'ap_outbox',
			'post_status'    => array( 'pending', 'publish' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_activitypub_external_activity_uri', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $activity_uri, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	$ax_bridge_delivery_spool = isset( $queued[0] ) ? (int) $queued[0] : 0;
	$stored = $ax_bridge_delivery_spool > 0 ? get_post( $ax_bridge_delivery_spool ) : null;
	$all_meta = $stored ? get_post_meta( $stored->ID ) : array();
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'a committed outbound Activity automatically queues one transport-only spool row', $recorded instanceof Axismundi_Activity && $stored instanceof WP_Post );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'a non-public outbound Activity is excluded by the Activities public projection contract', $recorded instanceof Axismundi_Activity && null === axismundi_act_public_payload( $recorded ) );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the official spool stores the complete payload and only a non-secret key reference', $stored instanceof WP_Post && false !== strpos( $stored->post_content, $activity_uri ) && false === strpos( wp_json_encode( $all_meta ), 'PRIVATE KEY' ) && $sender['private_key_ref'] === get_post_meta( $stored->ID, '_activitypub_external_private_key_ref', true ) );
	$duplicate = Activitypub\deliver_activity( $payload, $sender, array( 'https://example.com/inbox' ) );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the Activity URI idempotently identifies one transport spool row', $ax_bridge_delivery_spool === $duplicate );

	add_filter( 'pre_http_request', 'ax_bridge_delivery_http_mock', 99, 3 );
	Activitypub\External_Delivery::process( $ax_bridge_delivery_spool, 1 );
	remove_filter( 'pre_http_request', 'ax_bridge_delivery_http_mock', 99 );
	$args = (array) ( $GLOBALS['ax_bridge_delivery_http_args']['args'] ?? array() );
	$headers = array_change_key_case( (array) ( $args['headers'] ?? array() ), CASE_LOWER );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the worker resolves signing material only in memory and signs the HTTP request', isset( $headers['signature'] ) || isset( $headers['signature-input'] ) );
	$ledger = axismundi_act_get( $activity_uri );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'a successful transport job is published as delivered without replacing the Axismundi ledger', 'delivered' === get_post_meta( $ax_bridge_delivery_spool, '_activitypub_external_status', true ) && 'publish' === get_post_status( $ax_bridge_delivery_spool ) && $recorded instanceof Axismundi_Activity && $ledger instanceof Axismundi_Activity && $recorded->get_id() === $ledger->get_id() );
} finally {
	remove_filter( 'pre_http_request', 'ax_bridge_delivery_http_mock', 99 );
	if ( $ax_bridge_delivery_spool > 0 ) {
		wp_clear_scheduled_hook( Activitypub\External_Delivery::PROCESS_HOOK, array( $ax_bridge_delivery_spool, 1 ) );
		wp_delete_post( $ax_bridge_delivery_spool, true );
	}
	foreach ( array_unique( $ax_bridge_social_spools ) as $social_spool ) {
		wp_clear_scheduled_hook( Activitypub\External_Delivery::PROCESS_HOOK, array( $social_spool, 1 ) );
		wp_delete_post( $social_spool, true );
	}
	foreach ( array_filter( array_unique( $ax_bridge_social_uris ) ) as $social_uri ) {
		$wpdb->delete( axismundi_act_relations_table(), array( 'initiating_activity_uri' => $social_uri ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_act_relations_table(), array( 'state_activity_uri' => $social_uri ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $social_uri ) ); // phpcs:ignore WordPress.DB
	}
	$wpdb->delete( axismundi_act_relations_table(), array( 'initiating_activity_uri' => $activity_uri ?? '' ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $activity_uri ?? '' ) ); // phpcs:ignore WordPress.DB
	foreach ( array_unique( $ax_bridge_delivery_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB
	}
	if ( $ax_bridge_delivery_user > 0 && get_userdata( $ax_bridge_delivery_user ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $ax_bridge_delivery_user );
	}
}

$failures = count( array_filter( $ax_bridge_delivery_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_bridge_delivery_results ), $failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failures > 0 ? 1 : 0 );
}
exit( $failures > 0 ? 1 : 0 );
