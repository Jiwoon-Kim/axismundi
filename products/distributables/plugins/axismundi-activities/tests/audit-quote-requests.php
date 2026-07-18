<?php
/** FEP-044f QuoteRequest policy state-machine regression (dev-only). */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_qr_results      = array();
$ax_qr_users        = array();
$ax_qr_identities   = array();
$ax_qr_actor_uris   = array();
$ax_qr_posts        = array();
$ax_qr_old_user     = get_current_user_id();
$ax_qr_suffix       = strtolower( wp_generate_password( 8, false, false ) );
$ax_qr_bridge_hook  = has_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound' );

function ax_qr_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Create one cached remote Person. */
function ax_qr_remote( string $name, array &$identities, array &$actor_uris ) : ?Axismundi_Actor {
	$uri   = 'https://example.com/users/' . $name;
	$actor = axismundi_actors_upsert_remote(
		array(
			'uri'                => $uri,
			'actor_type'         => 'Person',
			'preferred_username' => $name,
			'display_name'       => $name,
			'profile_url'        => $uri,
			'endpoints'          => array( 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
			'payload'            => array( 'id' => $uri, 'type' => 'Person', 'preferredUsername' => $name, 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
		)
	);
	if ( is_wp_error( $actor ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture diagnostic.
		printf( "[DEBUG] remote Actor: %s - %s\n", $actor->get_error_code(), $actor->get_error_message() );
	}
	if ( $actor instanceof Axismundi_Actor ) {
		$identities[] = $actor->get_identity_id();
		$actor_uris[] = $actor->get_uri();
	}
	return $actor instanceof Axismundi_Actor ? $actor : null;
}

/** Record one remote QuoteRequest whose inline instrument is internally consistent. */
function ax_qr_request( Axismundi_Actor $requester, string $quoted_uri, string $suffix, array &$actor_uris ) {
	$quoting_uri = $requester->get_uri() . '/statuses/' . $suffix;
	$request_uri = $quoting_uri . '/quote-request';
	$actor_uris[] = $requester->get_uri();
	return axismundi_act_record_activity(
		array(
			'id'         => $request_uri,
			'type'       => 'QuoteRequest',
			'actor'      => $requester->get_uri(),
			'object'     => $quoted_uri,
			'instrument' => array( 'type' => 'Note', 'id' => $quoting_uri, 'attributedTo' => $requester->get_uri(), 'quote' => $quoted_uri ),
			'to'         => array( $quoted_uri ),
		),
		'inbound'
	);
}

try {
	remove_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', $ax_qr_bridge_hook ?: 10 );
	add_filter( 'axismundi_op_post_lifecycle_owner', static fn() : string => 'fixture' );
	axismundi_act_install();
	axismundi_op_install();

	$user_id = wp_create_user( 'axquote_' . $ax_qr_suffix, wp_generate_password( 24 ), 'axquote_' . $ax_qr_suffix . '@example.test' );
	if ( ! is_wp_error( $user_id ) ) {
		$ax_qr_users[] = (int) $user_id;
		$user = new WP_User( (int) $user_id );
		$user->set_role( 'contributor' );
	}
	$author = ! is_wp_error( $user_id ) ? axismundi_actors_ensure_for_user( (int) $user_id ) : null;
	if ( $author instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $author->get_identity_id(), 'axquote_' . $ax_qr_suffix );
		axismundi_actors_set_status( $author->get_identity_id(), 'public' );
		$author = axismundi_actors_get_for_user( (int) $user_id );
		$ax_qr_identities[] = $author->get_identity_id();
		$ax_qr_actor_uris[] = $author->get_uri();
	}
	$requester = ax_qr_remote( 'quoter_' . $ax_qr_suffix, $ax_qr_identities, $ax_qr_actor_uris );
	$post_id   = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => (int) $user_id, 'post_title' => 'Quoted Article', 'post_content' => '<p>Quoted.</p>' ) );
	if ( is_int( $post_id ) ) {
		$ax_qr_posts[] = $post_id;
	}
	$quoted_uri = is_int( $post_id ) ? add_query_arg( 'p', $post_id, home_url( '/' ) ) : '';
	ax_qr_assert( $ax_qr_results, 'fixture creates one projectable local Article and one cached remote requester', $author instanceof Axismundi_Actor && $requester instanceof Axismundi_Actor && '' !== $quoted_uri );
	if ( ! $author instanceof Axismundi_Actor || ! $requester instanceof Axismundi_Actor || '' === $quoted_uri ) {
		throw new RuntimeException( 'QuoteRequest fixture prerequisites failed.' );
	}

	update_post_meta( $post_id, AXISMUNDI_OP_POST_QUOTE_POLICY_META, 'anyone' );
	$anyone  = ax_qr_request( $requester, $quoted_uri, 'anyone-' . $ax_qr_suffix, $ax_qr_actor_uris );
	$accept  = $anyone instanceof Axismundi_Activity ? axismundi_act_get_quote_request_decision( $anyone->get_uri() ) : null;
	$accept_payload = $accept instanceof Axismundi_Activity ? $accept->get_payload() : array();
	$authorization  = isset( $accept_payload['result'] ) ? axismundi_act_get_quote_authorization( (string) $accept_payload['result'] ) : null;
	ax_qr_assert(
		$ax_qr_results,
		'anyone issues one outbound Accept with an active QuoteAuthorization addressed to the requester',
		$anyone instanceof Axismundi_Activity
			&& $accept instanceof Axismundi_Activity
			&& 'Accept' === $accept->get_type()
			&& $anyone->get_uri() === $accept->get_object_uri()
			&& in_array( $requester->get_uri(), (array) ( $accept->get_audience()['to'] ?? array() ), true )
			&& is_array( $authorization )
			&& 'active' === $authorization['status']
			&& $anyone->get_instrument_uri() === $authorization['quoting_object_uri']
	);

	update_post_meta( $post_id, AXISMUNDI_OP_POST_QUOTE_POLICY_META, 'me' );
	$replay        = $anyone instanceof Axismundi_Activity ? axismundi_act_process_quote_request( $anyone ) : null;
	$decision_rows = $anyone instanceof Axismundi_Activity ? array_filter( axismundi_act_get_by_object( $anyone->get_uri(), 50 ), static fn( Axismundi_Activity $item ) : bool => in_array( $item->get_type(), array( 'Accept', 'Reject' ), true ) ) : array();
	ax_qr_assert( $ax_qr_results, 'reprocessing after a policy change returns the original decision without minting another Activity', $replay instanceof Axismundi_Activity && $accept instanceof Axismundi_Activity && $replay->get_uri() === $accept->get_uri() && 1 === count( $decision_rows ) );

	// Simulate interruption after consent is issued but before its Accept is committed.
	remove_action( 'axismundi_act_activity_recorded', 'axismundi_act_maybe_process_quote_request', 15 );
	$recovery_request = ax_qr_request( $requester, $quoted_uri, 'recovery-' . $ax_qr_suffix, $ax_qr_actor_uris );
	add_action( 'axismundi_act_activity_recorded', 'axismundi_act_maybe_process_quote_request', 15 );
	$recovery_auth = null;
	if ( $recovery_request instanceof Axismundi_Activity ) {
		$recovery_auth = axismundi_act_issue_quote_authorization(
			array(
				'request_activity_uri' => $recovery_request->get_uri(),
				'quoting_object_uri'   => (string) $recovery_request->get_instrument_uri(),
				'quoted_object_uri'    => $quoted_uri,
				'requester_actor_uri'  => $requester->get_uri(),
				'author_actor_uri'     => $author->get_uri(),
			)
		);
	}
	$recovered = $recovery_request instanceof Axismundi_Activity ? axismundi_act_process_quote_request( $recovery_request ) : null;
	$recovered_payload = $recovered instanceof Axismundi_Activity ? $recovered->get_payload() : array();
	ax_qr_assert( $ax_qr_results, 'an issued authorization survives interruption and completes its Accept despite a later restrictive policy', is_array( $recovery_auth ) && $recovered instanceof Axismundi_Activity && 'Accept' === $recovered->get_type() && ( $recovery_auth['authorization_uri'] ?? '' ) === ( $recovered_payload['result'] ?? '' ) );

	$me_request = ax_qr_request( $requester, $quoted_uri, 'me-' . $ax_qr_suffix, $ax_qr_actor_uris );
	$me_reject  = $me_request instanceof Axismundi_Activity ? axismundi_act_get_quote_request_decision( $me_request->get_uri() ) : null;
	ax_qr_assert( $ax_qr_results, 'me rejects a remote requester and issues no authorization', $me_reject instanceof Axismundi_Activity && 'Reject' === $me_reject->get_type() && null === axismundi_act_get_quote_authorization_for_request( $me_request->get_uri() ) );

	delete_post_meta( $post_id, AXISMUNDI_OP_POST_QUOTE_POLICY_META );
	$unset_request = ax_qr_request( $requester, $quoted_uri, 'unset-' . $ax_qr_suffix, $ax_qr_actor_uris );
	$unset_reject  = $unset_request instanceof Axismundi_Activity ? axismundi_act_get_quote_request_decision( $unset_request->get_uri() ) : null;
	ax_qr_assert( $ax_qr_results, 'an unset policy invents no consent and converges on Reject', $unset_reject instanceof Axismundi_Activity && 'Reject' === $unset_reject->get_type() );

	update_post_meta( $post_id, AXISMUNDI_OP_POST_QUOTE_POLICY_META, 'followers' );
	$nonfollower_request = ax_qr_request( $requester, $quoted_uri, 'nonfollower-' . $ax_qr_suffix, $ax_qr_actor_uris );
	$nonfollower_reject  = $nonfollower_request instanceof Axismundi_Activity ? axismundi_act_get_quote_request_decision( $nonfollower_request->get_uri() ) : null;
	ax_qr_assert( $ax_qr_results, 'followers rejects a requester without an accepted Follow edge', $nonfollower_reject instanceof Axismundi_Activity && 'Reject' === $nonfollower_reject->get_type() );

	$follow = axismundi_act_import_follow_snapshot( $requester->get_uri(), $author->get_uri(), 'accepted', 'quote-fixture:' . $ax_qr_suffix );
	$follower_request = ax_qr_request( $requester, $quoted_uri, 'follower-' . $ax_qr_suffix, $ax_qr_actor_uris );
	$follower_accept  = $follower_request instanceof Axismundi_Activity ? axismundi_act_get_quote_request_decision( $follower_request->get_uri() ) : null;
	ax_qr_assert( $ax_qr_results, 'followers reads the accepted relation directly and approves without fetching the public Collection', is_array( $follow ) && 'accepted' === $follow['state'] && $follower_accept instanceof Axismundi_Activity && 'Accept' === $follower_accept->get_type() );

	$bad_uri = $requester->get_uri() . '/statuses/bad-' . $ax_qr_suffix;
	$bad = axismundi_act_record_activity(
		array(
			'id' => $bad_uri . '/quote-request', 'type' => 'QuoteRequest', 'actor' => $requester->get_uri(), 'object' => $quoted_uri,
			'instrument' => array( 'id' => $bad_uri, 'type' => 'Note', 'attributedTo' => 'https://example.com/users/someone-else', 'quote' => $quoted_uri ),
		),
		'inbound'
	);
	$bad_result = $bad instanceof Axismundi_Activity ? axismundi_act_process_quote_request( $bad ) : null;
	ax_qr_assert( $ax_qr_results, 'a contradictory inlined instrument is retained as input but never receives a decision', $bad instanceof Axismundi_Activity && is_wp_error( $bad_result ) && 'ax_act_quote_instrument_actor' === $bad_result->get_error_code() && null === axismundi_act_get_quote_request_decision( $bad->get_uri() ) );
} finally {
	if ( false === has_action( 'axismundi_act_activity_recorded', 'axismundi_act_maybe_process_quote_request' ) ) {
		add_action( 'axismundi_act_activity_recorded', 'axismundi_act_maybe_process_quote_request', 15 );
	}
	if ( false !== $ax_qr_bridge_hook ) {
		add_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', (int) $ax_qr_bridge_hook );
	}
	wp_set_current_user( $ax_qr_old_user );
	$GLOBALS['axismundi_actors_current_actor'] = null;
	foreach ( $ax_qr_posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( array_unique( $ax_qr_actor_uris ) as $actor_uri ) {
		$wpdb->delete( axismundi_act_quote_authorizations_table(), array( 'requester_actor_uri_hash' => hash( 'sha256', $actor_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned cleanup.
		$wpdb->delete( axismundi_act_relations_table(), array( 'subject_actor_uri_hash' => hash( 'sha256', $actor_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned cleanup.
		$wpdb->delete( axismundi_act_relations_table(), array( 'object_actor_uri_hash' => hash( 'sha256', $actor_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned cleanup.
		$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri_hash' => hash( 'sha256', $actor_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned cleanup.
	}
	foreach ( array_unique( $ax_qr_identities ) as $identity_id ) {
		foreach ( array( axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table(), axismundi_actors_identity_relations_table(), axismundi_actors_asset_cache_table(), axismundi_actors_addresses_table(), axismundi_actors_texts_table() ) as $child_table ) {
			$wpdb->delete( $child_table, array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned cleanup.
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned cleanup.
	}
	foreach ( $ax_qr_users as $user_id ) {
		wp_delete_user( $user_id );
	}
}

$ax_qr_failures = count( array_filter( $ax_qr_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_qr_results ), $ax_qr_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_qr_failures > 0 ? 1 : 0 );
}
exit( $ax_qr_failures > 0 ? 1 : 0 );
