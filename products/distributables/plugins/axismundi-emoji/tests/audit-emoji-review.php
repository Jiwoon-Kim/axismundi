<?php
/**
 * Review-state contracts (dev-only; dist-excluded).
 *
 * No network. This locks the distinction between a verification request and a human
 * decision, because conflating them makes the audit trail lie.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';

global $wpdb;
$ax_emoji_review_results = array();
$ax_emoji_review_ids     = array();
$ax_emoji_review_host    = 'review.fixture.invalid';

/** @param array<bool> $results @param string $label @param bool $condition @return void */
function ax_emoji_review_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** @return array<string,mixed> */
function ax_emoji_review_tag( string $name, string $host, string $icon_host ) : array {
	return array(
		'type' => 'Emoji',
		'name' => $name,
		'id'   => 'https://' . $host . '/emojis/' . trim( $name, ':' ),
		'icon' => array( 'url' => 'https://' . $icon_host . '/' . trim( $name, ':' ) . '.png' ),
	);
}

try {
	axismundi_emoji_install();
	$subject = 'https://' . $ax_emoji_review_host . '/notes/1';
	$payload = array( 'id' => $subject, 'tag' => array( ax_emoji_review_tag( ':ready:', $ax_emoji_review_host, 'cdn.review.fixture.invalid' ) ) );
	axismundi_emoji_observe_payload( $payload, $subject );
	$ready = axismundi_emoji_get( $ax_emoji_review_host, 'ready' );
	if ( is_array( $ready ) ) {
		$ax_emoji_review_ids[] = (int) $ready['id'];
	}
	ax_emoji_review_assert( $ax_emoji_review_results, 'a first-party pending row offers approve and reject', is_array( $ready ) && array_keys( axismundi_emoji_review_commands( $ready ) ) === array( 'approve', 'reject' ) );
	ax_emoji_review_assert( $ax_emoji_review_results, 'approval records the human decision but does not pretend bytes already exist', is_array( $ready ) && true === axismundi_emoji_review_apply( (int) $ready['id'], 'approve', 7 ) );
	$approved = is_array( $ready ) ? axismundi_emoji_get( $ax_emoji_review_host, 'ready' ) : null;
	ax_emoji_review_assert( $ax_emoji_review_results, 'approved metadata without a cache remains non-renderable', is_array( $approved ) && 'approved' === $approved['review_status'] && ! axismundi_emoji_is_renderable( $approved ) );
	$metadata = axismundi_emoji_review_metadata( array( 'declared_media_type' => '', 'media_type' => 'image/png', 'license_state' => 'unknown' ) );
	ax_emoji_review_assert(
		$ax_emoji_review_results,
		'the review screen shows the detected type when a valid remote Emoji omitted icon.mediaType',
		in_array( 'image/png', $metadata, true )
	);
	$apng_metadata = axismundi_emoji_review_metadata( array( 'declared_media_type' => 'image/apng', 'media_type' => 'image/png', 'license_state' => 'unknown' ) );
	ax_emoji_review_assert(
		$ax_emoji_review_results,
		'the more specific declared APNG type wins over the PNG container detected from its bytes',
	in_array( 'image/apng', $apng_metadata, true ) && ! in_array( 'image/png', $apng_metadata, true )
	);

	$hearsay_subject = 'https://carrier.fixture.invalid/notes/1';
	$hearsay_payload = array( 'id' => $hearsay_subject, 'tag' => array( ax_emoji_review_tag( ':borrowed:', 'origin.fixture.invalid', 'cdn.carrier.fixture.invalid' ) ) );
	axismundi_emoji_observe_payload( $hearsay_payload, $hearsay_subject );
	$hearsay = axismundi_emoji_get( 'origin.fixture.invalid', 'borrowed' );
	if ( is_array( $hearsay ) ) {
		$ax_emoji_review_ids[] = (int) $hearsay['id'];
	}
	ax_emoji_review_assert( $ax_emoji_review_results, 'a hearsay row is unverified but retains only an authority-hosted verification candidate', is_array( $hearsay ) && 'unverified' === axismundi_emoji_review_bucket( $hearsay ) && 'https://origin.fixture.invalid/emojis/borrowed' === $hearsay['verification_uri'] );
	ax_emoji_review_assert( $ax_emoji_review_results, 'an unverified candidate can be queued but cannot be approved', is_array( $hearsay ) && array_keys( axismundi_emoji_review_commands( $hearsay ) ) === array( 'queue_verification', 'reject' ) && is_wp_error( axismundi_emoji_review_apply( (int) $hearsay['id'], 'approve', 7 ) ) );
	ax_emoji_review_assert( $ax_emoji_review_results, 'queueing verification does not falsely record a human review', is_array( $hearsay ) && true === axismundi_emoji_review_apply( (int) $hearsay['id'], 'queue_verification', 7 ) );
	$queued = is_array( $hearsay ) ? axismundi_emoji_get( 'origin.fixture.invalid', 'borrowed' ) : null;
	ax_emoji_review_assert( $ax_emoji_review_results, 'the queued row has no reviewer audit fields and remains unverified', is_array( $queued ) && 'verify_queued' === $queued['review_reason'] && null === $queued['reviewed_at'] && null === $queued['reviewed_by'] );

	$unverified = axismundi_emoji_review_queue( 'unverified', 1 );
	ax_emoji_review_assert( $ax_emoji_review_results, 'queue filtering happens before the result limit', 1 === count( $unverified ) && 'borrowed' === (string) ( $unverified[0]['shortcode_key'] ?? '' ) );
} catch ( Throwable $error ) {
	ax_emoji_review_assert( $ax_emoji_review_results, 'the review suite ran to completion: ' . $error->getMessage(), false );
} finally {
	foreach ( array_unique( $ax_emoji_review_ids ) as $id ) {
		$wpdb->delete( axismundi_emoji_references_table(), array( 'emoji_id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_emoji_table(), array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$failures = count( array_filter( $ax_emoji_review_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_emoji_review_results ), $failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failures > 0 ? 1 : 0 );
}
exit( $failures > 0 ? 1 : 0 );
