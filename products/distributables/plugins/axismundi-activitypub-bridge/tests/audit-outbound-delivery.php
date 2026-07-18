<?php
/**
 * Actor transport and Bridge-owned delivery-table regression (dev-only).
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_bridge_delivery_results     = array();
$ax_bridge_delivery_user        = 0;
$ax_bridge_delivery_actor_ids   = array();
$ax_bridge_delivery_ids         = array();
$ax_bridge_delivery_sources     = array();
$ax_bridge_delivery_social_uris = array();
$ax_bridge_migration_before     = get_option( 'ax_activitypub_bridge_delivery_migration', null );
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
	$installed = axismundi_activitypub_bridge_install_delivery_table();
	$table     = axismundi_activitypub_bridge_delivery_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted fixture table identifier.
	$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted fixture table identifier.
	$unique = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'activity_uri_hash'", ARRAY_A );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the verified delivery schema has one status machine and a unique Activity URI identity', $installed && in_array( 'status', $columns, true ) && ! in_array( 'post_status', $columns, true ) && ! empty( $unique ) && 0 === (int) $unique[0]['Non_unique'] );

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

	add_filter( 'axismundi_activitypub_bridge_actor_public_key', '__return_empty_string', 99 );
	$keyless_federatable = $local instanceof Axismundi_Actor && axismundi_activitypub_bridge_actor_federatable( $local );
	$keyless_fields      = $local instanceof Axismundi_Actor ? axismundi_activitypub_bridge_actor_transport_fields( array( 'existing' => true ), $local ) : array();
	$keyless_links       = $local instanceof Axismundi_Actor ? axismundi_activitypub_bridge_webfinger_links( array(), $local ) : array( array( 'rel' => 'self' ) );
	remove_filter( 'axismundi_activitypub_bridge_actor_public_key', '__return_empty_string', 99 );
	$keyless_self = array_values( array_filter( $keyless_links, static fn( array $link ) : bool => 'self' === ( $link['rel'] ?? '' ) ) );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'a keyless Actor advertises no Inbox, endpoints, publicKey, or WebFinger self link while preserving existing fields (atomic fail-closed)', ! $keyless_federatable && true === ( $keyless_fields['existing'] ?? null ) && ! isset( $keyless_fields['inbox'], $keyless_fields['endpoints'], $keyless_fields['publicKey'] ) && array() === $keyless_self );
	$key_reads = 0;
	$one_shot_key = static function ( string $key ) use ( &$key_reads ) : string {
		++$key_reads;
		return 1 === $key_reads ? $key : '';
	};
	add_filter( 'axismundi_activitypub_bridge_actor_public_key', $one_shot_key, 99 );
	$atomic_fields = $local instanceof Axismundi_Actor ? axismundi_activitypub_bridge_actor_transport_fields( array(), $local ) : array();
	remove_filter( 'axismundi_activitypub_bridge_actor_public_key', $one_shot_key, 99 );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the atomic transport bundle resolves one stable public-key snapshot', 1 === $key_reads && ! empty( $atomic_fields['publicKey']['publicKeyPem'] ) && isset( $atomic_fields['inbox'], $atomic_fields['endpoints'] ) );

	$request = new WP_REST_Request( 'GET', '/axismundi/v1/actors/' . $local->get_uuid() . '/outbox' );
	$request->set_param( 'uuid', $local->get_uuid() );
	$outbox = $local instanceof Axismundi_Actor ? axismundi_op_get_actor_outbox( $request ) : null;
	$data   = $outbox instanceof WP_REST_Response ? $outbox->get_data() : array();
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'Object Projections serves the Activities-backed Actor OrderedCollection independently of Bridge transport', isset( $data['type'], $data['attributedTo'] ) && 'OrderedCollection' === $data['type'] && $local->get_uri() === $data['attributedTo'] );

	$remote_uri = 'https://example.com/users/' . wp_generate_uuid4();
	$remote     = axismundi_actors_upsert_remote(
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
	$follow_uri = is_array( $remote_follow ) ? (string) ( $remote_follow['initiating_activity_uri'] ?? '' ) : '';
	$follow_id  = axismundi_activitypub_bridge_find_delivery( $follow_uri );
	$follow_job = axismundi_activitypub_bridge_get_delivery( $follow_id );
	$follow_payload = is_object( $follow_job ) ? json_decode( (string) $follow_job->payload_json, true ) : array();
	if ( $follow_id > 0 ) {
		$ax_bridge_delivery_ids[] = $follow_id;
	}
	$ax_bridge_delivery_social_uris[] = $follow_uri;
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'a remote Follow queues exactly one JSON-LD row addressed to the cached Actor inbox', $follow_id > 0 && 'https://www.w3.org/ns/activitystreams' === ( $follow_payload['@context'] ?? '' ) && 'Follow' === ( $follow_payload['type'] ?? '' ) && in_array( $remote_uri, (array) ( $follow_payload['to'] ?? array() ), true ) );

	$inbound_follow_uri = 'https://example.com/activities/' . wp_generate_uuid4();
	$inbound_follow = $local instanceof Axismundi_Actor
		? axismundi_act_record_activity( array( 'id' => $inbound_follow_uri, 'type' => 'Follow', 'actor' => $remote_uri, 'object' => $local->get_uri() ), 'inbound' )
		: new WP_Error( 'fixture_actor_missing' );
	$remote_accept = $local instanceof Axismundi_Actor && $remote instanceof Axismundi_Actor
		? axismundi_act_get_relation( 'follow', $remote->get_uri(), $local->get_uri() )
		: null;
	$accept_uri = is_array( $remote_accept ) ? (string) ( $remote_accept['state_activity_uri'] ?? '' ) : '';
	$accept_id  = axismundi_activitypub_bridge_find_delivery( $accept_uri );
	$accept_job = axismundi_activitypub_bridge_get_delivery( $accept_id );
	$accept_payload = is_object( $accept_job ) ? json_decode( (string) $accept_job->payload_json, true ) : array();
	if ( $accept_id > 0 ) {
		$ax_bridge_delivery_ids[] = $accept_id;
	}
	$ax_bridge_delivery_social_uris = array_merge( $ax_bridge_delivery_social_uris, array( $inbound_follow_uri, $accept_uri ) );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'auto-accepting an inbound Follow queues one JSON-LD Accept back to that Actor', $inbound_follow instanceof Axismundi_Activity && $accept_id > 0 && 'Accept' === ( $accept_payload['type'] ?? '' ) && $inbound_follow_uri === ( $accept_payload['object'] ?? '' ) && in_array( $remote_uri, (array) ( $accept_payload['to'] ?? array() ), true ) );
	$followers_address = $local instanceof Axismundi_Actor ? axismundi_op_actor_followers_url( $local ) : '';
	$followers_activity = $local instanceof Axismundi_Actor ? axismundi_act_record_activity( array( 'type' => 'Announce', 'actor' => $local->get_uri(), 'object' => 'https://example.com/objects/followers-only', 'to' => array( $followers_address ) ), 'outbound' ) : null;
	$followers_inboxes = $followers_activity instanceof Axismundi_Activity ? axismundi_activitypub_bridge_activity_inboxes( $followers_activity ) : array();
	if ( $followers_activity instanceof Axismundi_Activity ) {
		$ax_bridge_delivery_social_uris[] = $followers_activity->get_uri();
		$followers_delivery_id = axismundi_activitypub_bridge_find_delivery( $followers_activity->get_uri() );
		if ( $followers_delivery_id > 0 ) {
			$ax_bridge_delivery_ids[] = $followers_delivery_id;
		}
	}
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the sender Followers collection address expands to accepted follower inboxes only at the transport boundary', in_array( 'https://example.com/inbox', $followers_inboxes, true ) );

	$activity_uri = home_url( '/activities/' . wp_generate_uuid4() . '/' );
	$payload      = array( 'id' => $activity_uri, 'type' => 'Like', 'actor' => $local->get_uri(), 'object' => 'https://example.com/objects/liked', 'to' => array( $remote_uri ) );
	$sender       = axismundi_activitypub_bridge_sender( $local );
	$rejected     = axismundi_activitypub_bridge_enqueue_delivery( $payload, array_merge( $sender, array( 'private_key' => 'secret' ) ), array( 'https://example.com/inbox' ) );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the Bridge spool rejects caller-supplied private key material', is_wp_error( $rejected ) && 'ax_bridge_delivery_private_key' === $rejected->get_error_code() );

	$recorded = axismundi_act_record_activity( $payload, 'outbound' );
	$job_id   = axismundi_activitypub_bridge_find_delivery( $activity_uri );
	$job      = axismundi_activitypub_bridge_get_delivery( $job_id );
	if ( $job_id > 0 ) {
		$ax_bridge_delivery_ids[] = $job_id;
	}
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'a committed outbound Activity automatically queues one transport-only table row', $recorded instanceof Axismundi_Activity && is_object( $job ) && 'queued' === $job->status );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'a non-public outbound Activity is excluded by the Activities public projection contract', $recorded instanceof Axismundi_Activity && null === axismundi_act_public_payload( $recorded ) );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the table stores the complete payload and no private key material', is_object( $job ) && false !== strpos( (string) $job->payload_json, $activity_uri ) && false === strpos( wp_json_encode( $job ), 'PRIVATE KEY' ) && false === strpos( wp_json_encode( $job ), 'private_key' ) );
	$stock_rows = get_posts( array( 'post_type' => 'ap_outbox', 'post_status' => array( 'pending', 'publish' ), 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_activitypub_external_activity_uri', 'meta_value' => $activity_uri ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the stock Outbox scheduler cannot discover a Bridge transport job', empty( $stock_rows ) );
	$duplicate = axismundi_activitypub_bridge_enqueue_delivery( $payload, $sender, array( 'https://example.com/inbox' ) );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the Activity URI unique key idempotently identifies one transport row', $job_id === $duplicate );

	$claim_one = wp_generate_uuid4();
	$claim_two = wp_generate_uuid4();
	$first_claim  = axismundi_activitypub_bridge_claim_delivery( $job_id, $claim_one );
	$second_claim = axismundi_activitypub_bridge_claim_delivery( $job_id, $claim_two );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the conditional UPDATE grants exactly one worker claim', $first_claim && ! $second_claim );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'only the owning worker can renew its delivery claim', axismundi_activitypub_bridge_touch_delivery_claim( $job_id, $claim_one ) && ! axismundi_activitypub_bridge_touch_delivery_claim( $job_id, $claim_two ) );
	axismundi_activitypub_bridge_release_delivery( $job_id, $claim_one );

	add_filter( 'pre_http_request', 'ax_bridge_delivery_http_mock', 99, 3 );
	axismundi_activitypub_bridge_process_delivery( $job_id, 1 );
	remove_filter( 'pre_http_request', 'ax_bridge_delivery_http_mock', 99 );
	$args    = (array) ( $GLOBALS['ax_bridge_delivery_http_args']['args'] ?? array() );
	$headers = array_change_key_case( (array) ( $args['headers'] ?? array() ), CASE_LOWER );
	$job     = axismundi_activitypub_bridge_get_delivery( $job_id );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the worker resolves signing material only in memory and signs the HTTP request', isset( $headers['signature'] ) || isset( $headers['signature-input'] ) );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the worker releases its claim and reaches one terminal state', is_object( $job ) && null === $job->lock_token && null === $job->locked_at && 'delivered' === $job->status );
	$ledger = axismundi_act_get( $activity_uri );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'delivery completion does not replace the Axismundi ledger', $recorded instanceof Axismundi_Activity && $ledger instanceof Axismundi_Activity && $recorded->get_id() === $ledger->get_id() );
	$peer_error = axismundi_activitypub_bridge_delivery_error( array( 'body' => '{"error":"Signature rejected by peer"}' ), 401 );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'a failed peer response retains one bounded sanitized diagnostic message', 'HTTP 401: Signature rejected by peer' === $peer_error );
	$key_missing_response = array( 'body' => '{"error":"Public key not found for key https://local.example/actor#main-key"}' );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'only the peer public-key discovery 401 joins the bounded retry policy', axismundi_activitypub_bridge_delivery_should_retry( $key_missing_response, 401 ) && ! axismundi_activitypub_bridge_delivery_should_retry( array( 'body' => '{"error":"Signature rejected"}' ), 401 ) );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the peer key-recovery backoff crosses a one-day cache window then dead-letters', array( 300, 3600, 86400, 172800 ) === axismundi_activitypub_bridge_delivery_key_recovery_schedule() && 300 === axismundi_activitypub_bridge_delivery_next_delay( 1, true ) && 3600 === axismundi_activitypub_bridge_delivery_next_delay( 2, true ) && 86400 === axismundi_activitypub_bridge_delivery_next_delay( 3, true ) && 172800 === axismundi_activitypub_bridge_delivery_next_delay( 4, true ) && -1 === axismundi_activitypub_bridge_delivery_next_delay( 5, true ) );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the ordinary transient backoff keeps its bounded quadratic schedule and dead-letters at the attempt ceiling', axismundi_activitypub_bridge_delivery_retry_delay( 2 ) === axismundi_activitypub_bridge_delivery_next_delay( 1, false ) && -1 === axismundi_activitypub_bridge_delivery_next_delay( axismundi_activitypub_bridge_delivery_max_attempts(), false ) );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'only the peer public-key discovery 401 is classified as key recovery', axismundi_activitypub_bridge_delivery_is_key_recovery_error( $key_missing_response, 401 ) && ! axismundi_activitypub_bridge_delivery_is_key_recovery_error( array( 'body' => '{"error":"Signature rejected"}' ), 401 ) && ! axismundi_activitypub_bridge_delivery_is_key_recovery_error( array(), 500 ) );
	wp_clear_scheduled_hook( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $job_id, 1 ) );
	$wpdb->update(
		$table,
		array( 'status' => 'failed', 'attempt' => 1, 'pending_inboxes_json' => '[]', 'last_error' => 'HTTP 401: Public key not found for key fixture' ),
		array( 'id' => $job_id ),
		array( '%s', '%d', '%s', '%s' ),
		array( '%d' )
	);
	$manual_retry = axismundi_activitypub_bridge_retry_failed_delivery( $job_id );
	$retried_job  = axismundi_activitypub_bridge_get_delivery( $job_id );
	$retried_inboxes = is_object( $retried_job ) ? json_decode( (string) $retried_job->pending_inboxes_json, true ) : array();
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'an administrator can atomically restore the immutable recipient set of one failed delivery', true === $manual_retry && is_object( $retried_job ) && 'retrying' === $retried_job->status && 0 === (int) $retried_job->attempt && array( 'https://example.com/inbox' ) === $retried_inboxes );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'a second manual retry loses the failed-state compare-and-swap', is_wp_error( axismundi_activitypub_bridge_retry_failed_delivery( $job_id ) ) );
	wp_clear_scheduled_hook( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $job_id, 1 ) );
	add_filter( 'pre_http_request', 'ax_bridge_delivery_http_mock', 99, 3 );
	axismundi_activitypub_bridge_process_delivery( $job_id, 1 );
	remove_filter( 'pre_http_request', 'ax_bridge_delivery_http_mock', 99 );
	$retried_job = axismundi_activitypub_bridge_get_delivery( $job_id );
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'the manually retried immutable Activity can reach the delivered terminal state', is_object( $retried_job ) && 'delivered' === $retried_job->status );

	$provisional_uri = home_url( '/activities/provisional-' . wp_generate_uuid4() . '/' );
	$provisional_payload = array( 'id' => $provisional_uri, 'type' => 'Like', 'actor' => $local->get_uri(), 'object' => 'https://example.com/objects/provisional', 'to' => array( $remote_uri ) );
	$provisional_source = wp_insert_post(
		array(
			'post_type'    => AXISMUNDI_ACTIVITYPUB_BRIDGE_LEGACY_POST_TYPE,
			'post_status'  => 'pending',
			'post_content' => wp_slash( wp_json_encode( $provisional_payload ) ),
			'meta_input'   => array(
				'_ax_ap_actor_uri'       => $local->get_uri(),
				'_ax_ap_key_id'          => $sender['key_id'],
				'_ax_ap_inboxes'         => wp_json_encode( array( 'https://example.com/inbox' ) ),
				'_ax_ap_pending_inboxes' => wp_json_encode( array( 'https://example.com/inbox' ) ),
				'_ax_ap_attempt'         => 0,
				'_ax_ap_status'          => 'queued',
			),
		),
		true
	);
	$ax_bridge_delivery_sources[] = (int) $provisional_source;
	$cpt_migrated = ! is_wp_error( $provisional_source ) && axismundi_activitypub_bridge_migrate_delivery_cpt();
	$provisional_id = axismundi_activitypub_bridge_find_delivery( $provisional_uri );
	if ( $provisional_id > 0 ) {
		$ax_bridge_delivery_ids[] = $provisional_id;
	}
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'provisional CPT jobs migrate without deleting their source rows', $cpt_migrated && $provisional_id > 0 && AXISMUNDI_ACTIVITYPUB_BRIDGE_LEGACY_POST_TYPE === get_post_type( $provisional_source ) && $provisional_id === (int) get_post_meta( $provisional_source, '_ax_ap_migrated_to_table', true ) );

	$legacy_uri = home_url( '/activities/legacy-' . wp_generate_uuid4() . '/' );
	$legacy_payload = array( 'id' => $legacy_uri, 'type' => 'Like', 'actor' => $local->get_uri(), 'object' => 'https://example.com/objects/legacy', 'to' => array( $remote_uri ) );
	$legacy_source = wp_insert_post(
		array(
			'post_type'    => 'ap_outbox',
			'post_status'  => 'pending',
			'post_content' => wp_slash( wp_json_encode( $legacy_payload ) ),
			'meta_input'   => array(
				'_activitypub_external_delivery'        => 1,
				'_activitypub_external_actor_uri'       => $local->get_uri(),
				'_activitypub_external_key_id'          => $sender['key_id'],
				'_activitypub_external_inboxes'         => wp_json_encode( array( 'https://example.com/inbox' ) ),
				'_activitypub_external_pending_inboxes' => wp_json_encode( array( 'https://example.com/inbox' ) ),
				'_activitypub_external_attempt'         => 0,
				'_activitypub_external_status'          => 'queued',
			),
		),
		true
	);
	$ax_bridge_delivery_sources[] = (int) $legacy_source;
	$legacy_migrated = ! is_wp_error( $legacy_source ) && axismundi_activitypub_bridge_migrate_external_outbox_rows();
	$legacy_id = axismundi_activitypub_bridge_find_delivery( $legacy_uri );
	if ( $legacy_id > 0 ) {
		$ax_bridge_delivery_ids[] = $legacy_id;
	}
	ax_bridge_delivery_assert( $ax_bridge_delivery_results, 'legacy external Outbox jobs migrate without deletion and leave the stock pending scan', $legacy_migrated && $legacy_id > 0 && 'publish' === get_post_status( $legacy_source ) && $legacy_id === (int) get_post_meta( $legacy_source, '_ax_ap_migrated_to_table', true ) );
} finally {
	remove_filter( 'pre_http_request', 'ax_bridge_delivery_http_mock', 99 );
	foreach ( array_unique( $ax_bridge_delivery_ids ) as $delivery_id ) {
		for ( $attempt = 1; $attempt <= 10; ++$attempt ) {
			wp_clear_scheduled_hook( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $delivery_id, $attempt ) );
		}
		$wpdb->delete( axismundi_activitypub_bridge_delivery_table(), array( 'id' => $delivery_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}
	foreach ( array_unique( $ax_bridge_delivery_sources ) as $source_id ) {
		if ( $source_id > 0 ) {
			wp_delete_post( $source_id, true );
		}
	}
	if ( null === $ax_bridge_migration_before ) {
		delete_option( 'ax_activitypub_bridge_delivery_migration' );
	} else {
		update_option( 'ax_activitypub_bridge_delivery_migration', $ax_bridge_migration_before, false );
	}
	foreach ( array_filter( array_unique( $ax_bridge_delivery_social_uris ) ) as $social_uri ) {
		$wpdb->delete( axismundi_act_relations_table(), array( 'initiating_activity_uri' => $social_uri ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_act_relations_table(), array( 'state_activity_uri' => $social_uri ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $social_uri ) ); // phpcs:ignore WordPress.DB
	}
	$wpdb->delete( axismundi_act_relations_table(), array( 'initiating_activity_uri' => $activity_uri ?? '' ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $activity_uri ?? '' ) ); // phpcs:ignore WordPress.DB
	foreach ( array_unique( $ax_bridge_delivery_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB
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
