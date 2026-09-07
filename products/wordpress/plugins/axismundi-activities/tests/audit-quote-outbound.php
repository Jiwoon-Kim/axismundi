<?php
/**
 * Outbound QuoteRequest emission + immutable decision reconciliation regression.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_oq_results   = array();
$ax_oq_uris      = array();
$ax_oq_actor_ids = array();
$GLOBALS['ax_oq_events'] = array();

/** @param bool[] $results Results. */
function ax_oq_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Capture the decision signal. */
function ax_oq_capture( Axismundi_Activity $request, string $decision, string $authorization ) : void {
	$GLOBALS['ax_oq_events'][] = array( 'uri' => $request->get_uri(), 'decision' => $decision, 'auth' => $authorization );
}

/** Create one public local Person actor and return its URI. */
function ax_oq_actor( array &$actor_ids ) : string {
	$actor = axismundi_actors_create_local(
		array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'preferred_username' => 'oq' . strtolower( wp_generate_password( 8, false, false ) ) )
	);
	if ( ! $actor instanceof Axismundi_Actor ) {
		return '';
	}
	$actor_ids[] = $actor->get_identity_id();
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$fresh = axismundi_actors_get_by_identity( $actor->get_identity_id() );
	return $fresh instanceof Axismundi_Actor ? $fresh->get_uri() : '';
}

/** Record a decision Activity from a target Actor about one request. */
function ax_oq_decide( array &$uris, string $type, string $actor, string $request_uri, array $embedded, string $result = '', array $to = array() ) : string {
	$object = array_merge( array( 'id' => $request_uri, 'type' => 'QuoteRequest' ), $embedded );
	$payload = array( 'type' => $type, 'actor' => $actor, 'object' => $object, 'to' => $to );
	if ( '' !== $result ) {
		$payload['result'] = $result;
	}
	$activity = axismundi_act_record_activity( $payload, 'local' );
	if ( $activity instanceof Axismundi_Activity ) {
		$uris[] = $activity->get_uri();
	}
	return $activity instanceof Axismundi_Activity ? $activity->get_uri() : '';
}

try {
	axismundi_act_install();
	add_action( 'axismundi_act_outbound_quote_decided', 'ax_oq_capture', 10, 3 );

	$author = ax_oq_actor( $ax_oq_actor_ids );
	$target = ax_oq_actor( $ax_oq_actor_ids );
	$other  = ax_oq_actor( $ax_oq_actor_ids );
	ax_oq_assert( $ax_oq_results, 'the fixture created three public local actors', '' !== $author && '' !== $target && '' !== $other );

	$s        = strtolower( wp_generate_password( 8, false, false ) );
	$quoting  = home_url( '/?ax_note=' . $s );
	$quoted   = 'https://example.com/notes/' . $s;
	$auth_uri = 'https://example.com/authorizations/' . $s;
	$inst     = array( 'id' => $quoting, 'type' => 'Note', 'content' => '<p>quoting</p>' );
	$common   = array( 'author_actor_uri' => $author, 'target_author_uri' => $target, 'direction' => 'local' );

	// P1-2: the finalized quoting Note is inlined as the instrument, idempotent per generation.
	$qr    = axismundi_act_record_outbound_quote_request( array_merge( $common, array( 'instrument' => $inst, 'quoted_object_uri' => $quoted, 'generation' => 1 ) ) );
	$again = axismundi_act_record_outbound_quote_request( array_merge( $common, array( 'instrument' => $inst, 'quoted_object_uri' => $quoted, 'generation' => 1 ) ) );
	$ax_oq_uris[] = $qr instanceof Axismundi_Activity ? $qr->get_uri() : '';
	$req_payload = $qr instanceof Axismundi_Activity ? $qr->get_payload() : array();
	ax_oq_assert( $ax_oq_results, 'the QuoteRequest inlines the finalized quoting Note as its instrument, idempotent per generation', $qr instanceof Axismundi_Activity && $qr->get_id() === $again->get_id() && $quoted === $qr->get_object_uri() && $quoting === $qr->get_instrument_uri() && is_array( $req_payload['instrument'] ?? null ) && $quoting === ( $req_payload['instrument']['id'] ?? '' ) );

	$request_uri = $qr->get_uri();

	// P1-1/P1-2: a changed target Actor, quoted Object, or generation each mint a distinct
	// request even when every other field is identical.
	$quoted_b    = 'https://example.com/notes/' . $s . '-b';
	$diff_target = axismundi_act_record_outbound_quote_request( array( 'instrument' => $inst, 'quoted_object_uri' => $quoted, 'author_actor_uri' => $author, 'target_author_uri' => $other, 'direction' => 'local', 'generation' => 1 ) );
	$diff_quoted = axismundi_act_record_outbound_quote_request( array_merge( $common, array( 'instrument' => $inst, 'quoted_object_uri' => $quoted_b, 'generation' => 1 ) ) );
	$gen2        = axismundi_act_record_outbound_quote_request( array_merge( $common, array( 'instrument' => $inst, 'quoted_object_uri' => $quoted, 'generation' => 2 ) ) );
	foreach ( array( $diff_target, $diff_quoted, $gen2 ) as $variant ) {
		$ax_oq_uris[] = $variant instanceof Axismundi_Activity ? $variant->get_uri() : '';
	}
	$ids = array_map( static fn( $a ) : int => $a instanceof Axismundi_Activity ? $a->get_id() : 0, array( $qr, $diff_target, $diff_quoted, $gen2 ) );
	ax_oq_assert( $ax_oq_results, 'a changed target Actor, quoted Object, or generation each record a distinct request without a source conflict', $diff_target instanceof Axismundi_Activity && $diff_quoted instanceof Axismundi_Activity && $gen2 instanceof Axismundi_Activity && 4 === count( array_unique( $ids ) ) && ! in_array( 0, $ids, true ) );

	// Pending before any decision.
	ax_oq_assert( $ax_oq_results, 'an undecided outbound QuoteRequest reconciles to no decision', null === axismundi_act_outbound_quote_decision( $request_uri ) );

	// A non-target Accept is ignored.
	ax_oq_decide( $ax_oq_uris, 'Accept', $other, $request_uri, array( 'actor' => $author, 'object' => $quoted, 'instrument' => $quoting ), $auth_uri, array( $author ) );
	ax_oq_assert( $ax_oq_results, 'an Accept from a non-target Actor does not decide the request', null === axismundi_act_outbound_quote_decision( $request_uri ) && empty( $GLOBALS['ax_oq_events'] ) );

	// P2-4: a target Accept whose inlined object contradicts the request is rejected.
	ax_oq_decide( $ax_oq_uris, 'Accept', $target, $request_uri, array( 'actor' => $author, 'object' => 'https://example.com/wrong', 'instrument' => $quoting ), $auth_uri, array( $author ) );
	ax_oq_assert( $ax_oq_results, 'a target Accept with a contradicting inlined QuoteRequest is ignored', null === axismundi_act_outbound_quote_decision( $request_uri ) && empty( $GLOBALS['ax_oq_events'] ) );

	// A valid target Accept decides the request and fires the signal exactly once.
	$GLOBALS['ax_oq_events'] = array();
	$accept_uri = ax_oq_decide( $ax_oq_uris, 'Accept', $target, $request_uri, array( 'actor' => $author, 'object' => $quoted, 'instrument' => $quoting ), $auth_uri, array( $author ) );
	$decision   = axismundi_act_outbound_quote_decision( $request_uri );
	ax_oq_assert( $ax_oq_results, 'a valid target Accept reconciles to accepted with its authorization and deciding activity, firing once', is_array( $decision ) && 'accepted' === $decision['decision'] && $auth_uri === $decision['authorization_uri'] && $accept_uri === $decision['deciding_activity_uri'] && 1 === count( $GLOBALS['ax_oq_events'] ) && 'accepted' === $GLOBALS['ax_oq_events'][0]['decision'] );

	// P1-3 + P2-5: a later Reject cannot flip the first decision and does not re-fire.
	ax_oq_decide( $ax_oq_uris, 'Reject', $target, $request_uri, array( 'actor' => $author, 'object' => $quoted, 'instrument' => $quoting ), '', array( $author ) );
	$after = axismundi_act_outbound_quote_decision( $request_uri );
	ax_oq_assert( $ax_oq_results, 'a later Reject cannot flip the immutable first Accept and does not re-fire the signal', is_array( $after ) && 'accepted' === $after['decision'] && $accept_uri === $after['deciding_activity_uri'] && 1 === count( $GLOBALS['ax_oq_events'] ) );

	// A fresh request whose first decision is a Reject reconciles to rejected.
	$s2       = strtolower( wp_generate_password( 8, false, false ) );
	$quoting2 = home_url( '/?ax_note=' . $s2 );
	$quoted2  = 'https://example.com/notes/' . $s2;
	$qr2      = axismundi_act_record_outbound_quote_request( array_merge( $common, array( 'instrument' => array( 'id' => $quoting2, 'type' => 'Note' ), 'quoted_object_uri' => $quoted2, 'generation' => 1 ) ) );
	$ax_oq_uris[] = $qr2 instanceof Axismundi_Activity ? $qr2->get_uri() : '';
	$request_uri2 = $qr2 instanceof Axismundi_Activity ? $qr2->get_uri() : '';
	ax_oq_decide( $ax_oq_uris, 'Reject', $target, $request_uri2, array( 'actor' => $author, 'object' => $quoted2, 'instrument' => $quoting2 ), '', array( $author ) );
	$decision2 = axismundi_act_outbound_quote_decision( $request_uri2 );
	ax_oq_assert( $ax_oq_results, 'a request whose first decision is a target Reject reconciles to rejected with no authorization', is_array( $decision2 ) && 'rejected' === $decision2['decision'] && '' === $decision2['authorization_uri'] );

	// P2: a bare-URI instrument and a mismatched attribution are refused at the API.
	$uri_only = axismundi_act_record_outbound_quote_request( array_merge( $common, array( 'instrument' => $quoting, 'quoted_object_uri' => $quoted, 'generation' => 9 ) ) );
	$bad_attr = axismundi_act_record_outbound_quote_request( array_merge( $common, array( 'instrument' => array( 'id' => home_url( '/?ax_note=' . $s . '-c' ), 'attributedTo' => $other ), 'quoted_object_uri' => $quoted, 'generation' => 9 ) ) );
	ax_oq_assert( $ax_oq_results, 'a bare-URI instrument and a mismatched attribution are refused', is_wp_error( $uri_only ) && 'ax_act_outbound_quote_instrument' === $uri_only->get_error_code() && is_wp_error( $bad_attr ) && 'ax_act_outbound_quote_attribution' === $bad_attr->get_error_code() );

	// P1: the first valid decision is found past a full page of earlier invalid responses.
	$sp        = strtolower( wp_generate_password( 8, false, false ) );
	$quoting_p = home_url( '/?ax_note=' . $sp );
	$quoted_p  = 'https://example.com/notes/' . $sp;
	$auth_p    = 'https://example.com/authorizations/' . $sp;
	$qr_p      = axismundi_act_record_outbound_quote_request( array_merge( $common, array( 'instrument' => array( 'id' => $quoting_p, 'type' => 'Note' ), 'quoted_object_uri' => $quoted_p, 'generation' => 1 ) ) );
	$ax_oq_uris[] = $qr_p instanceof Axismundi_Activity ? $qr_p->get_uri() : '';
	$request_p = $qr_p instanceof Axismundi_Activity ? $qr_p->get_uri() : '';
	$embed_p   = array( 'actor' => $author, 'object' => $quoted_p, 'instrument' => $quoting_p );
	for ( $i = 0; $i < 201; $i++ ) {
		// Valid target Accept structure but no QuoteAuthorization result, so not a decision.
		ax_oq_decide( $ax_oq_uris, 'Accept', $target, $request_p, $embed_p, '', array( $author ) );
	}
	ax_oq_decide( $ax_oq_uris, 'Accept', $target, $request_p, $embed_p, $auth_p, array( $author ) );
	$decision_p = axismundi_act_outbound_quote_decision( $request_p );
	ax_oq_assert( $ax_oq_results, 'the first valid decision is found past a full page of earlier invalid responses', is_array( $decision_p ) && 'accepted' === $decision_p['decision'] && $auth_p === $decision_p['authorization_uri'] );

	// Distinct quoting and quoted objects are required.
	$bad = axismundi_act_record_outbound_quote_request( array_merge( $common, array( 'instrument' => array( 'id' => $quoting ), 'quoted_object_uri' => $quoting ) ) );
	ax_oq_assert( $ax_oq_results, 'a self-referential QuoteRequest is rejected', is_wp_error( $bad ) && 'ax_act_outbound_quote_args' === $bad->get_error_code() );
} finally {
	remove_action( 'axismundi_act_outbound_quote_decided', 'ax_oq_capture', 10 );
	foreach ( array_filter( array_unique( $ax_oq_uris ) ) as $uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_unique( $ax_oq_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

$ax_oq_failures = count( array_filter( $ax_oq_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_oq_results ), $ax_oq_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_oq_failures > 0 ? 1 : 0 );
}
exit( $ax_oq_failures > 0 ? 1 : 0 );
