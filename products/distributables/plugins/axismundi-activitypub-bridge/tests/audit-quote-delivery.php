<?php
/**
 * Outbound Quote Activities through the Bridge transport spool (dev-only).
 *
 * The generic `axismundi_act_activity_recorded` -> `axismundi_activitypub_bridge_queue_outbound`
 * hook requires no Quote-specific code: it derives recipient Inboxes from any outbound
 * Activity's audience. This fixture proves that generic mechanism actually delivers the three
 * Quote-lifecycle Activities -- an outbound QuoteRequest, the Accept it receives back, and the
 * Delete that withdraws a revoked QuoteAuthorization -- end to end through signed (mocked) HTTP,
 * since every existing Quote regression exercises the Activities ledger directly and never
 * proves Bridge picks any of it up.
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_qd_results     = array();
$ax_qd_user_ids    = array();
$ax_qd_actor_ids   = array();
$ax_qd_post_ids    = array();
$ax_qd_remote_uris = array();
$ax_qd_delivery_ids = array();
$GLOBALS['ax_qd_http_args'] = array();

/** @param bool[] $results Results. */
function ax_qd_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Capture the signed HTTP request without network delivery. */
function ax_qd_http_mock( $response, array $args, string $url ) {
	unset( $response );
	$GLOBALS['ax_qd_http_args'][] = array( 'url' => $url, 'args' => $args );
	return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 202, 'message' => 'Accepted' ), 'cookies' => array(), 'filename' => null );
}

/** Create one public local author. */
function ax_qd_local_author( array &$user_ids, array &$actor_ids ) : ?WP_User {
	$login = 'ax_qd_' . strtolower( wp_generate_password( 8, false, false ) );
	$uid   = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	if ( $uid <= 0 ) {
		return null;
	}
	$user_ids[] = $uid;
	$actor = axismundi_actors_ensure_for_user( $uid );
	if ( $actor instanceof Axismundi_Actor ) {
		$actor_ids[] = $actor->get_identity_id();
		axismundi_actors_register_handle( $actor->get_identity_id(), $login );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	}
	return get_userdata( $uid ) ?: null;
}

/** Create one cached, deliverable remote Person. */
function ax_qd_remote_actor( array &$actor_ids, string $slug ) : ?Axismundi_Actor {
	$uri   = 'https://example.com/actors/' . $slug;
	$actor = axismundi_actors_upsert_remote(
		array(
			'uri'                => $uri,
			'actor_type'         => 'Person',
			'preferred_username' => $slug,
			'display_name'       => $slug,
			'profile_url'        => $uri,
			'endpoints'          => array( 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
			'payload'            => array( 'id' => $uri, 'type' => 'Person', 'preferredUsername' => $slug, 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
		)
	);
	if ( $actor instanceof Axismundi_Actor ) {
		$actor_ids[] = $actor->get_identity_id();
		return $actor;
	}
	return null;
}

/** Create and publish one fixture Note. */
function ax_qd_note( array &$post_ids, int $author_id, string $content, string $quote_target = '' ) : array {
	$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $author_id, 'post_content' => $content ) );
	$post_ids[] = $post_id;
	if ( '' !== $quote_target ) {
		axismundi_note_save( $post_id, array( 'quote_target_uri' => $quote_target ) );
	}
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$envelope = axismundi_note_get( $post_id );
	return array( 'post_id' => $post_id, 'uri' => is_array( $envelope ) ? axismundi_note_object_uri( (string) $envelope['local_uuid'] ) : '' );
}

try {
	add_action( 'axismundi_note_lifecycle_failed', static function ( WP_Error $error ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture diagnostic.
		printf( "[INFO] Note lifecycle: %s\n", $error->get_error_code() );
	} );

	$author = ax_qd_local_author( $ax_qd_user_ids, $ax_qd_actor_ids );
	$actor  = $author instanceof WP_User ? axismundi_actors_get_for_user( $author->ID ) : null;
	$actor_uri = $actor instanceof Axismundi_Actor ? $actor->get_uri() : '';
	ax_qd_assert( $ax_qd_results, 'the fixture creates one federatable public local author', '' !== $actor_uri && $author instanceof WP_User );

	// Scenario A: an outbound QuoteRequest, addressed to a remote Actor's cached Inbox.
	$remote_target_actor = ax_qd_remote_actor( $ax_qd_actor_ids, 'quoted-' . strtolower( wp_generate_password( 6, false, false ) ) );
	$remote_target_uri    = 'https://remote.example/notes/' . strtolower( wp_generate_password( 8, false, false ) );
	$remote_target_stored = $remote_target_actor instanceof Axismundi_Actor
		? axismundi_op_remote_object_store( array( 'id' => $remote_target_uri, 'type' => 'Note', 'attributedTo' => $remote_target_actor->get_uri(), 'content' => 'Remote quoted note.' ) )
		: null;
	$ax_qd_remote_uris[] = $remote_target_uri;

	$quoting = ax_qd_note( $ax_qd_post_ids, (int) $author->ID, 'Quoting a remote object.', $remote_target_uri );
	$outbound_request = $remote_target_actor instanceof Axismundi_Actor
		? axismundi_act_get_outbound_quote_request( $quoting['uri'], $remote_target_uri, $actor_uri, $remote_target_actor->get_uri(), 1 )
		: null;
	$outbound_job_id = $outbound_request instanceof Axismundi_Activity ? axismundi_activitypub_bridge_find_delivery( $outbound_request->get_uri() ) : 0;
	$outbound_job     = $outbound_job_id > 0 ? axismundi_activitypub_bridge_get_delivery( $outbound_job_id ) : null;
	$outbound_payload = is_object( $outbound_job ) ? json_decode( (string) $outbound_job->payload_json, true ) : array();
	if ( $outbound_job_id > 0 ) {
		$ax_qd_delivery_ids[] = $outbound_job_id;
	}
	ax_qd_assert(
		$ax_qd_results,
		'an outbound QuoteRequest queues one Bridge delivery addressed to the quoted Actor\'s Inbox with the finalized instrument',
		is_array( $remote_target_stored ) && $outbound_request instanceof Axismundi_Activity && 'outbound' === $outbound_request->get_direction()
			&& $outbound_job_id > 0 && 'queued' === ( $outbound_job->status ?? '' )
			&& 'QuoteRequest' === ( $outbound_payload['type'] ?? '' )
			&& in_array( $remote_target_actor->get_uri() . '/inbox', json_decode( (string) $outbound_job->inboxes_json, true ), true )
			&& is_array( $outbound_payload['instrument'] ?? null ) && $remote_target_uri === ( $outbound_payload['instrument']['quote'] ?? '' )
	);

	add_filter( 'pre_http_request', 'ax_qd_http_mock', 99, 3 );
	if ( $outbound_job_id > 0 ) {
		axismundi_activitypub_bridge_process_delivery( $outbound_job_id, 1 );
	}
	remove_filter( 'pre_http_request', 'ax_qd_http_mock', 99 );
	$outbound_sent  = end( $GLOBALS['ax_qd_http_args'] );
	$outbound_headers = array_change_key_case( (array) ( $outbound_sent['args']['headers'] ?? array() ), CASE_LOWER );
	$outbound_final = $outbound_job_id > 0 ? axismundi_activitypub_bridge_get_delivery( $outbound_job_id ) : null;
	ax_qd_assert(
		$ax_qd_results,
		'the QuoteRequest is signed and delivered to the quoted Actor\'s Inbox without altering the Activities ledger',
		is_array( $outbound_sent ) && $remote_target_actor->get_uri() . '/inbox' === ( $outbound_sent['url'] ?? '' )
			&& ( isset( $outbound_headers['signature'] ) || isset( $outbound_headers['signature-input'] ) )
			&& is_object( $outbound_final ) && 'delivered' === $outbound_final->status
			&& $outbound_request->get_id() === axismundi_act_get( $outbound_request->get_uri() )->get_id()
	);

	// Scenario B: a remote Actor's inbound QuoteRequest against our own published Note, whose
	// local automatic Accept must itself queue back to the requester's Inbox.
	$requester = ax_qd_remote_actor( $ax_qd_actor_ids, 'requester-' . strtolower( wp_generate_password( 6, false, false ) ) );
	$quoted    = ax_qd_note( $ax_qd_post_ids, (int) $author->ID, 'Our object, open to anyone.' );
	axismundi_note_save( $quoted['post_id'], array( 'quote_policy' => 'anyone' ) );

	$inbound_quoting_uri = $requester instanceof Axismundi_Actor ? $requester->get_uri() . '/statuses/' . strtolower( wp_generate_password( 6, false, false ) ) : '';
	$inbound_request_uri = $inbound_quoting_uri . '/quote-request';
	$inbound_request = $requester instanceof Axismundi_Actor
		? axismundi_act_record_activity(
			array(
				'id'         => $inbound_request_uri,
				'type'       => 'QuoteRequest',
				'actor'      => $requester->get_uri(),
				'object'     => $quoted['uri'],
				'instrument' => array( 'type' => 'Note', 'id' => $inbound_quoting_uri, 'attributedTo' => $requester->get_uri(), 'quote' => $quoted['uri'] ),
				'to'         => array( $quoted['uri'] ),
			),
			'inbound'
		)
		: null;
	$ax_qd_remote_uris[] = $inbound_request_uri;
	$decision = $inbound_request instanceof Axismundi_Activity ? axismundi_act_get_quote_request_decision( $inbound_request->get_uri() ) : null;
	$accept_job_id = $decision instanceof Axismundi_Activity ? axismundi_activitypub_bridge_find_delivery( $decision->get_uri() ) : 0;
	$accept_job    = $accept_job_id > 0 ? axismundi_activitypub_bridge_get_delivery( $accept_job_id ) : null;
	$accept_payload = is_object( $accept_job ) ? json_decode( (string) $accept_job->payload_json, true ) : array();
	if ( $accept_job_id > 0 ) {
		$ax_qd_delivery_ids[] = $accept_job_id;
	}
	ax_qd_assert(
		$ax_qd_results,
		'an inbound QuoteRequest\'s automatic Accept queues one Bridge delivery back to the requester\'s Inbox with the issued QuoteAuthorization',
		$inbound_request instanceof Axismundi_Activity && $decision instanceof Axismundi_Activity && 'Accept' === $decision->get_type()
			&& $accept_job_id > 0 && 'Accept' === ( $accept_payload['type'] ?? '' )
			&& in_array( $requester->get_uri() . '/inbox', json_decode( (string) $accept_job->inboxes_json, true ), true )
			&& '' !== ( $accept_payload['result'] ?? '' )
	);

	add_filter( 'pre_http_request', 'ax_qd_http_mock', 99, 3 );
	if ( $accept_job_id > 0 ) {
		axismundi_activitypub_bridge_process_delivery( $accept_job_id, 1 );
	}
	remove_filter( 'pre_http_request', 'ax_qd_http_mock', 99 );
	$accept_sent  = end( $GLOBALS['ax_qd_http_args'] );
	$accept_final = $accept_job_id > 0 ? axismundi_activitypub_bridge_get_delivery( $accept_job_id ) : null;
	ax_qd_assert(
		$ax_qd_results,
		'the Accept is delivered to the requester\'s Inbox and reaches the terminal delivered state',
		is_array( $accept_sent ) && $requester->get_uri() . '/inbox' === ( $accept_sent['url'] ?? '' )
			&& is_object( $accept_final ) && 'delivered' === $accept_final->status
	);

	// Scenario C: revoking that authorization queues its addressed, minimal Delete.
	$authorization_uri = (string) ( $accept_payload['result'] ?? '' );
	$authorization      = '' !== $authorization_uri ? axismundi_act_get_quote_authorization( $authorization_uri ) : null;
	$revoked = is_array( $authorization ) ? axismundi_act_revoke_quote_authorization( $authorization_uri, 'fixture' ) : null;
	$delete_activity = is_array( $revoked )
		? current( array_filter( axismundi_act_get_by_object( $authorization_uri, 50 ), static fn( Axismundi_Activity $item ) : bool => 'Delete' === $item->get_type() && 'outbound' === $item->get_direction() ) )
		: null;
	$delete_job_id = $delete_activity instanceof Axismundi_Activity ? axismundi_activitypub_bridge_find_delivery( $delete_activity->get_uri() ) : 0;
	$delete_job    = $delete_job_id > 0 ? axismundi_activitypub_bridge_get_delivery( $delete_job_id ) : null;
	$delete_payload = is_object( $delete_job ) ? json_decode( (string) $delete_job->payload_json, true ) : array();
	if ( $delete_job_id > 0 ) {
		$ax_qd_delivery_ids[] = $delete_job_id;
	}
	ax_qd_assert(
		$ax_qd_results,
		'revoking a QuoteAuthorization queues its addressed Delete without embedding either protected Object',
		is_array( $revoked ) && 'revoked' === $revoked['status'] && $delete_activity instanceof Axismundi_Activity
			&& $delete_job_id > 0 && 'Delete' === ( $delete_payload['type'] ?? '' ) && $authorization_uri === ( $delete_payload['object'] ?? '' )
			&& in_array( $requester->get_uri() . '/inbox', json_decode( (string) $delete_job->inboxes_json, true ), true )
			&& ! isset( $delete_payload['instrument'], $delete_payload['target'] )
	);

	// A local-direction QuoteRequest (self/local-other) must never reach the transport spool.
	$self_target = ax_qd_note( $ax_qd_post_ids, (int) $author->ID, 'Self target for local-direction check.' );
	$self_quote  = ax_qd_note( $ax_qd_post_ids, (int) $author->ID, 'Self quote.', $self_target['uri'] );
	$self_request = axismundi_act_get_outbound_quote_request( $self_quote['uri'], $self_target['uri'], $actor_uri, $actor_uri, 1 );
	ax_qd_assert( $ax_qd_results, 'a self-quote never records an outbound QuoteRequest or a Bridge delivery row', null === $self_request );
} finally {
	remove_filter( 'pre_http_request', 'ax_qd_http_mock', 99 );
	foreach ( array_unique( $ax_qd_delivery_ids ) as $delivery_id ) {
		for ( $attempt = 1; $attempt <= 10; ++$attempt ) {
			wp_clear_scheduled_hook( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $delivery_id, $attempt ) );
		}
		$wpdb->delete( axismundi_activitypub_bridge_delivery_table(), array( 'id' => $delivery_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_unique( $ax_qd_remote_uris ) as $uri ) {
		axismundi_op_remote_object_delete( $uri );
	}
	foreach ( array_unique( $ax_qd_post_ids ) as $post_id ) {
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( get_post( (int) $post_id ) instanceof WP_Post ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	$actor_hashes = array();
	foreach ( array_unique( $ax_qd_actor_ids ) as $identity_id ) {
		$identity = $wpdb->get_row( $wpdb->prepare( 'SELECT canonical_uri FROM ' . axismundi_actors_identities_table() . ' WHERE id = %d', $identity_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		if ( is_array( $identity ) ) {
			$actor_hashes[] = hash( 'sha256', (string) $identity['canonical_uri'] );
		}
	}
	foreach ( $actor_hashes as $hash ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri_hash' => $hash ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_act_quote_authorizations_table(), array( 'requester_actor_uri_hash' => $hash ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_act_quote_authorizations_table(), array( 'author_actor_uri_hash' => $hash ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_unique( $ax_qd_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( ! empty( $ax_qd_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_qd_user_ids ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_qd_failures = count( array_filter( $ax_qd_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_qd_results ), $ax_qd_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_qd_failures > 0 ? 1 : 0 );
}
exit( $ax_qd_failures > 0 ? 1 : 0 );
