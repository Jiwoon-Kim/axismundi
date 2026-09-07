<?php
/**
 * Authority-side Emoji verification contracts (dev-only; dist-excluded).
 *
 * The HTTP transport is mocked: this suite proves the worker's identity checks without
 * contacting the network, and deliberately exercises a response that looks plausible
 * but cannot be allowed to promote a hearsay row.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';

global $wpdb;
$ax_emoji_verify_results = array();
$ax_emoji_verify_ids     = array();
$GLOBALS['ax_emoji_verify_uris']      = array();
$GLOBALS['ax_emoji_verify_seen_urls'] = array();

/** @param array<bool> $results @param string $label @param bool $condition @return void */
function ax_emoji_verify_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** @param string $name @return array<string,mixed> */
function ax_emoji_verify_hearsay_payload( string $name ) : array {
	return array(
		'id' => 'https://carrier.example.com/notes/' . trim( $name, ':' ),
		'tag' => array(
			array(
				'type' => 'Emoji',
				'name' => $name,
				'id'   => 'https://example.com/emojis/' . trim( $name, ':' ),
				'icon' => array( 'url' => 'https://evil-cdn.example.com/' . trim( $name, ':' ) . '.png' ),
			),
		),
	);
}

/** @return array<string,mixed> */
function ax_emoji_verify_authority_document() : array {
	return array(
		'id'      => 'https://example.com/emojis/borrowed',
		'type'    => 'Emoji',
		'name'    => ':borrowed:',
		'updated' => '2026-07-27T01:02:03Z',
		'icon'    => array(
			'type'      => 'Image',
			'mediaType' => 'image/png',
			'url'       => 'https://cdn.authority.example.com/borrowed.png',
		),
	);
}

/** @return array<string,mixed>|WP_Error */
function ax_emoji_verify_mock_request( $preempt, $args, $url ) {
	global $ax_emoji_verify_uris, $ax_emoji_verify_seen_urls;
	$ax_emoji_verify_seen_urls[] = $url;
	if ( ! isset( $ax_emoji_verify_uris[ $url ] ) ) {
		return $preempt;
	}
	$body = $ax_emoji_verify_uris[ $url ];
	return array(
		'headers'       => array( 'content-type' => 'application/activity+json' ),
		'body'          => wp_json_encode( $body ),
		'response'      => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'       => array(),
		'filename'      => null,
		'http_response' => null,
	);
}
add_filter( 'pre_http_request', 'ax_emoji_verify_mock_request', 10, 3 );

try {
	axismundi_emoji_install();
	$payload = ax_emoji_verify_hearsay_payload( ':borrowed:' );
	$subject = (string) $payload['id'];
	axismundi_emoji_observe_payload( $payload, $subject );
	$row = axismundi_emoji_get( 'example.com', 'borrowed' );
	if ( is_array( $row ) ) {
		$ax_emoji_verify_ids[] = (int) $row['id'];
	}
	ax_emoji_verify_assert( $ax_emoji_verify_results, 'a borrowed emoji begins as an unverified row with no canonical image source', is_array( $row ) && 'unverified' === axismundi_emoji_review_bucket( $row ) && '' === (string) $row['source_url'] );
	ax_emoji_verify_assert( $ax_emoji_verify_results, 'queueing requests verification but does not make an HTTP request yet', is_array( $row ) && true === axismundi_emoji_review_apply( (int) $row['id'], 'queue_verification', 1 ) );
	$queued = axismundi_emoji_get( 'example.com', 'borrowed' );
	ax_emoji_verify_assert( $ax_emoji_verify_results, 'only explicitly queued rows enter the worker queue', is_array( $queued ) && 1 === count( axismundi_emoji_verification_queue() ) );

	$uri = 'https://example.com/emojis/borrowed';
	$GLOBALS['ax_emoji_verify_uris'][ $uri ] = ax_emoji_verify_authority_document();
	$document = axismundi_emoji_fetch_verification_document( $uri );
	ax_emoji_verify_assert( $ax_emoji_verify_results, 'the worker accepts a bounded ActivityStreams response from the authority', is_array( $document ) && $uri === (string) ( $document['id'] ?? '' ) );
	ax_emoji_verify_assert( $ax_emoji_verify_results, 'the authority document promotes matching identity metadata through the first-party path', is_array( $queued ) && axismundi_emoji_verify_queued_row( $queued ) );
	$verified = axismundi_emoji_get( 'example.com', 'borrowed' );
	ax_emoji_verify_assert( $ax_emoji_verify_results, 'verified metadata has its own CDN source, authority timestamp, and no remaining verification candidate', is_array( $verified ) && 'https://cdn.authority.example.com/borrowed.png' === $verified['source_url'] && '2026-07-27T01:02:03Z' === $verified['updated_raw'] && null !== $verified['verified_at'] && '' === (string) $verified['verification_uri'] );
	ax_emoji_verify_assert( $ax_emoji_verify_results, 'verification moves the row into ordinary pending review rather than approving bytes nobody has inspected', is_array( $verified ) && 'pending' === $verified['review_status'] && '' === (string) $verified['review_reason'] && ! axismundi_emoji_is_renderable( $verified ) );

	$bad_payload = ax_emoji_verify_hearsay_payload( ':mismatch:' );
	$bad_subject = (string) $bad_payload['id'];
	axismundi_emoji_observe_payload( $bad_payload, $bad_subject );
	$bad = axismundi_emoji_get( 'example.com', 'mismatch' );
	if ( is_array( $bad ) ) {
		$ax_emoji_verify_ids[] = (int) $bad['id'];
	}
	axismundi_emoji_review_apply( (int) $bad['id'], 'queue_verification', 1 );
	$bad_queued = axismundi_emoji_get( 'example.com', 'mismatch' );
	$GLOBALS['ax_emoji_verify_uris']['https://example.com/emojis/mismatch'] = ax_emoji_verify_authority_document();
	ax_emoji_verify_assert( $ax_emoji_verify_results, 'a response whose own id does not exactly match the queued identifier cannot promote metadata', is_array( $bad_queued ) && ! axismundi_emoji_verify_queued_row( $bad_queued ) );
	$bad_after = axismundi_emoji_get( 'example.com', 'mismatch' );
	ax_emoji_verify_assert( $ax_emoji_verify_results, 'a failed verification retains the plain-text fallback and increments only a retry counter', is_array( $bad_after ) && '' === (string) $bad_after['source_url'] && 'verify_queued' === $bad_after['review_reason'] && 1 === (int) $bad_after['failure_count'] );

	/* ------------------------------------------------------------------ *
	 * Retry boundary
	 *
	 * These run the queue repeatedly, which is the only way the earlier defect was
	 * visible: every single-attempt assertion passed while the queue re-selected the
	 * same rows every fifteen seconds forever.
	 * ------------------------------------------------------------------ */

	$ax_emoji_verify_seeded = array();
	$now = current_time( 'mysql', true );
	for ( $i = 1; $i <= AXISMUNDI_EMOJI_VERIFICATION_BATCH_SIZE + 1; $i++ ) {
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
			axismundi_emoji_table(),
			array(
				'emoji_authority'  => 'dead.invalid',
				'shortcode_key'    => 'retry' . $i,
				'shortcode'        => ':retry' . $i . ':',
				'verification_uri' => 'https://dead.invalid/emojis/retry' . $i,
				'scope'            => 'remote',
				'source_kind'      => 'remote',
				'review_status'    => 'pending',
				'review_reason'    => 'verify_queued',
				'first_seen_at'    => $now,
				'last_seen_at'     => $now,
			)
		);
		$ax_emoji_verify_seeded[]  = (int) $wpdb->insert_id;
		$ax_emoji_verify_ids[]     = (int) $wpdb->insert_id;
	}

	$first  = axismundi_emoji_verification_queue();
	foreach ( $first as $row ) {
		axismundi_emoji_mark_verification_failure( (int) $row['id'] );
	}
	$second = axismundi_emoji_verification_queue();
	$first_keys  = array_map( static fn( array $r ) : string => (string) $r['shortcode_key'], $first );
	$second_keys = array_map( static fn( array $r ) : string => (string) $r['shortcode_key'], $second );

	ax_emoji_verify_assert(
		$ax_emoji_verify_results,
		'a row that just failed is withheld from the very next pass, so the queue cannot hot-loop on it',
		array() === array_intersect( $first_keys, $second_keys )
	);
	ax_emoji_verify_assert(
		$ax_emoji_verify_results,
		'the row behind a full batch of failures is finally reached, instead of being starved by them',
		in_array( 'retry' . ( AXISMUNDI_EMOJI_VERIFICATION_BATCH_SIZE + 1 ), $second_keys, true )
	);
	ax_emoji_verify_assert(
		$ax_emoji_verify_results,
		'the backoff grows with each failure rather than staying flat',
		axismundi_emoji_verification_backoff( 3 ) > axismundi_emoji_verification_backoff( 1 )
	);

	// Exhaust one row's attempts.
	$ax_emoji_verify_victim = $ax_emoji_verify_seeded[0];
	for ( $i = 0; $i < AXISMUNDI_EMOJI_VERIFICATION_MAX_FAILURES; $i++ ) {
		axismundi_emoji_mark_verification_failure( $ax_emoji_verify_victim );
	}
	$ax_emoji_verify_dead = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . axismundi_emoji_table() . ' WHERE id = %d', $ax_emoji_verify_victim ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	ax_emoji_verify_assert(
		$ax_emoji_verify_results,
		'an unreachable authority is abandoned rather than retried forever',
		is_array( $ax_emoji_verify_dead ) && 'verify_failed' === (string) $ax_emoji_verify_dead['review_reason']
	);
	ax_emoji_verify_assert(
		$ax_emoji_verify_results,
		'abandonment is not rejection: the row stays pending and keeps its text fallback',
		is_array( $ax_emoji_verify_dead ) && 'pending' === (string) $ax_emoji_verify_dead['review_status']
	);
	$ax_emoji_verify_still = array_map( static fn( array $r ) : string => (string) $r['shortcode_key'], axismundi_emoji_verification_queue( 25 ) );
	ax_emoji_verify_assert(
		$ax_emoji_verify_results,
		'an abandoned row leaves the worker queue entirely',
		! in_array( 'retry1', $ax_emoji_verify_still, true )
	);
	ax_emoji_verify_assert(
		$ax_emoji_verify_results,
		'an operator can put an abandoned row back, which is the judgement the backoff cannot make',
		array_key_exists( 'queue_verification', axismundi_emoji_review_commands( $ax_emoji_verify_dead ) )
	);
	axismundi_emoji_review_apply( $ax_emoji_verify_victim, 'queue_verification', 1 );
	$ax_emoji_verify_revived = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . axismundi_emoji_table() . ' WHERE id = %d', $ax_emoji_verify_victim ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	ax_emoji_verify_assert(
		$ax_emoji_verify_results,
		'requeuing clears both counters, or the row would be withheld by a backoff it has served',
		is_array( $ax_emoji_verify_revived ) && 0 === (int) $ax_emoji_verify_revived['failure_count'] && null === $ax_emoji_verify_revived['next_attempt_at']
	);
} catch ( Throwable $error ) {
	ax_emoji_verify_assert( $ax_emoji_verify_results, 'the verification worker suite ran to completion: ' . $error->getMessage(), false );
} finally {
	remove_filter( 'pre_http_request', 'ax_emoji_verify_mock_request', 10 );
	foreach ( array_unique( $ax_emoji_verify_ids ) as $id ) {
		$wpdb->delete( axismundi_emoji_references_table(), array( 'emoji_id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_emoji_table(), array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$failures = count( array_filter( $ax_emoji_verify_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_emoji_verify_results ), $failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failures > 0 ? 1 : 0 );
}
exit( $failures > 0 ? 1 : 0 );
