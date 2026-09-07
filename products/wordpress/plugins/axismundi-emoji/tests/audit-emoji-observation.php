<?php
/**
 * Observation and registry upsert (dev-only; dist-excluded).
 *
 * Writes to the real registry tables and cleans up after itself. The contracts under
 * test are the ones that only show up on the *second* sighting of an emoji: identity
 * survives a re-upload, so the same key can come back meaning a different picture, and
 * how that is handled decides whether an approved emoji can silently become something
 * nobody reviewed.
 *
 * No network.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';

global $wpdb;
$ax_emo_results  = array();
$ax_emo_created  = array();
$ax_emo_authority = 'fixture.invalid';

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_emo_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * A minimal Emoji tag for the fixture authority.
 *
 * @param string $name    Shortcode.
 * @param string $updated RFC-3339 timestamp, or ''.
 * @param string $license Licence free text.
 * @return array<string,mixed>
 */
function ax_emo_tag( string $name, string $updated = '', string $license = '' ) : array {
	$tag = array(
		'type' => 'Emoji',
		'name' => $name,
		'id'   => 'https://fixture.invalid/emojis/' . trim( $name, ':' ),
		'icon' => array( 'type' => 'Image', 'url' => 'https://cdn.fixture.invalid/' . trim( $name, ':' ) . '.png' ),
	);
	if ( '' !== $updated ) {
		$tag['updated'] = $updated;
	}
	if ( '' !== $license ) {
		$tag['_misskey_license'] = array( 'freeText' => $license );
	}
	return $tag;
}

try {
	axismundi_emoji_install();
	ax_emo_assert( $ax_emo_results, 'the registry schema is installed and queryable', axismundi_emoji_ready() );

	$ax_emo_subject = 'https://fixture.invalid/notes/1';

	/* ------------------------------------------------------------------ *
	 * First sighting
	 * ------------------------------------------------------------------ */

	$ax_emo_payload = array( 'id' => $ax_emo_subject, 'tag' => array( ax_emo_tag( ':fixture_one:', '2024-01-01T00:00:00Z' ) ) );
	$ax_emo_count   = axismundi_emoji_observe_payload( $ax_emo_payload, $ax_emo_subject );
	$ax_emo_row     = axismundi_emoji_get( $ax_emo_authority, 'fixture_one' );
	if ( is_array( $ax_emo_row ) ) {
		$ax_emo_created[] = (int) $ax_emo_row['id'];
	}
	ax_emo_assert( $ax_emo_results, 'observing a payload records the emoji it declares', 1 === $ax_emo_count && is_array( $ax_emo_row ) );
	ax_emo_assert( $ax_emo_results, 'a newly observed emoji is pending, so nothing becomes visible without review', is_array( $ax_emo_row ) && 'pending' === $ax_emo_row['review_status'] );
	ax_emo_assert( $ax_emo_results, 'observation downloads nothing: there is no cached path yet', is_array( $ax_emo_row ) && '' === (string) $ax_emo_row['cached_path'] && null === $ax_emo_row['fetched_at'] );
	ax_emo_assert( $ax_emo_results, 'a pending emoji is not renderable, so it falls back to its shortcode', is_array( $ax_emo_row ) && ! axismundi_emoji_is_renderable( $ax_emo_row ) );
	ax_emo_assert( $ax_emo_results, 'the original shortcode is stored verbatim for alt, title, and plain-text surfaces', is_array( $ax_emo_row ) && ':fixture_one:' === $ax_emo_row['shortcode'] );

	// The reference is what later makes garbage collection possible.
	$ax_emo_refs = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test fixture.
		$wpdb->prepare( 'SELECT COUNT(*) FROM ' . axismundi_emoji_references_table() . ' WHERE emoji_id = %d', (int) $ax_emo_row['id'] )
	);
	ax_emo_assert( $ax_emo_results, 'the declaring subject is recorded as a reference', 1 === $ax_emo_refs );

	/* ------------------------------------------------------------------ *
	 * Re-observation
	 * ------------------------------------------------------------------ */

	$wpdb->update( axismundi_emoji_table(), array( 'review_status' => 'approved', 'cached_path' => 'x/y.png', 'fetched_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $ax_emo_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test fixture.
	$ax_emo_approved = axismundi_emoji_get( $ax_emo_authority, 'fixture_one' );
	ax_emo_assert( $ax_emo_results, 'an approved emoji with bytes is renderable', is_array( $ax_emo_approved ) && axismundi_emoji_is_renderable( $ax_emo_approved ) );

	// Same `updated` — a routine re-sighting must not disturb a decision already made.
	axismundi_emoji_observe_payload( $ax_emo_payload, 'https://fixture.invalid/notes/2' );
	$ax_emo_same = axismundi_emoji_get( $ax_emo_authority, 'fixture_one' );
	ax_emo_assert( $ax_emo_results, 'seeing the same emoji again leaves an approval alone', is_array( $ax_emo_same ) && 'approved' === $ax_emo_same['review_status'] );

	$ax_emo_refs2 = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test fixture.
		$wpdb->prepare( 'SELECT COUNT(*) FROM ' . axismundi_emoji_references_table() . ' WHERE emoji_id = %d', (int) $ax_emo_row['id'] )
	);
	ax_emo_assert( $ax_emo_results, 'a second declaring subject adds a reference rather than duplicating the emoji', 2 === $ax_emo_refs2 );

	// Newer `updated` — the image behind a stable key has changed.
	$ax_emo_changed = array( 'id' => $ax_emo_subject, 'tag' => array( ax_emo_tag( ':fixture_one:', '2025-06-01T00:00:00Z' ) ) );
	axismundi_emoji_observe_payload( $ax_emo_changed, $ax_emo_subject );
	$ax_emo_requeued = axismundi_emoji_get( $ax_emo_authority, 'fixture_one' );
	ax_emo_assert( $ax_emo_results, 'a newer `updated` returns an approved emoji to review, since the picture may now differ', is_array( $ax_emo_requeued ) && 'pending' === $ax_emo_requeued['review_status'] );
	ax_emo_assert( $ax_emo_results, 'it is flagged `changed`, distinguishable from a first sighting an operator has never seen', is_array( $ax_emo_requeued ) && 'changed' === $ax_emo_requeued['review_reason'] );
	ax_emo_assert( $ax_emo_results, 'the stale cache is dropped rather than served under a name that may now mean something else', is_array( $ax_emo_requeued ) && '' === (string) $ax_emo_requeued['cached_path'] );
	ax_emo_assert( $ax_emo_results, 'and it stops being renderable, falling back to the shortcode', is_array( $ax_emo_requeued ) && ! axismundi_emoji_is_renderable( $ax_emo_requeued ) );

	// An older or missing `updated` is not evidence of anything.
	$wpdb->update( axismundi_emoji_table(), array( 'review_status' => 'approved', 'cached_path' => 'x/y.png' ), array( 'id' => (int) $ax_emo_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test fixture.
	axismundi_emoji_observe_payload( array( 'id' => $ax_emo_subject, 'tag' => array( ax_emo_tag( ':fixture_one:' ) ) ), $ax_emo_subject );
	$ax_emo_absent = axismundi_emoji_get( $ax_emo_authority, 'fixture_one' );
	ax_emo_assert( $ax_emo_results, 'an absent `updated` does not re-queue, or every server that omits it would churn approvals', is_array( $ax_emo_absent ) && 'approved' === $ax_emo_absent['review_status'] );
	ax_emo_assert( $ax_emo_results, 'an older `updated` is not newer', ! axismundi_emoji_updated_is_newer( '2020-01-01T00:00:00Z', '2024-01-01T00:00:00Z' ) );

	/* ------------------------------------------------------------------ *
	 * Authority-scoped default
	 * ------------------------------------------------------------------ */

	ax_emo_assert( $ax_emo_results, 'an authority with no ruling defaults its emoji to pending', 'pending' === axismundi_emoji_authority_default( 'never.seen.invalid' ) );

	$wpdb->replace( axismundi_emoji_authorities_table(), array( 'emoji_authority' => $ax_emo_authority, 'review_default' => 'rejected' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test fixture.
	axismundi_emoji_observe_payload( array( 'id' => $ax_emo_subject, 'tag' => array( ax_emo_tag( ':fixture_two:' ) ) ), $ax_emo_subject );
	$ax_emo_two = axismundi_emoji_get( $ax_emo_authority, 'fixture_two' );
	if ( is_array( $ax_emo_two ) ) {
		$ax_emo_created[] = (int) $ax_emo_two['id'];
	}
	ax_emo_assert( $ax_emo_results, 'a ruling on the authority decides new emoji from it without a second review', is_array( $ax_emo_two ) && 'rejected' === $ax_emo_two['review_status'] );

	/* ------------------------------------------------------------------ *
	 * Trust boundary: only the authority defines its own emoji
	 * ------------------------------------------------------------------ */

	// Establish a first-party canonical row.
	axismundi_emoji_observe_payload(
		array( 'id' => 'https://victim.invalid/notes/1', 'tag' => array(
			array(
				'type'    => 'Emoji',
				'name'    => ':target:',
				'id'      => 'https://victim.invalid/emojis/target',
				'updated' => '2024-01-01T00:00:00Z',
				'icon'    => array( 'url' => 'https://cdn.victim.invalid/target.png' ),
			),
		) ),
		'https://victim.invalid/notes/1'
	);
	$ax_emo_target = axismundi_emoji_get( 'victim.invalid', 'target' );
	if ( is_array( $ax_emo_target ) ) {
		$ax_emo_created[] = (int) $ax_emo_target['id'];
	}
	$wpdb->update( axismundi_emoji_table(), array( 'review_status' => 'approved', 'cached_path' => 'a/b.png' ), array( 'id' => (int) $ax_emo_target['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test fixture.
	ax_emo_assert( $ax_emo_results, 'the authority itself may define its emoji, and the row is marked verified', is_array( $ax_emo_target ) && null !== $ax_emo_target['verified_at'] );

	/*
	 * The attack: a note from somewhere else claims the victim's emoji, points the
	 * image at a host it controls, and dates it in the future so the re-queue path
	 * fires. Nothing in `id` or `updated` is authenticated, so only provenance can
	 * stop this.
	 */
	axismundi_emoji_observe_payload(
		array( 'id' => 'https://evil.invalid/notes/9', 'tag' => array(
			array(
				'type'    => 'Emoji',
				'name'    => ':target:',
				'id'      => 'https://victim.invalid/emojis/target',
				'updated' => '2099-01-01T00:00:00Z',
				'icon'    => array( 'url' => 'https://evil.invalid/nasty.png' ),
			),
		) ),
		'https://evil.invalid/notes/9'
	);
	$ax_emo_after = axismundi_emoji_get( 'victim.invalid', 'target' );
	ax_emo_assert( $ax_emo_results, 'a third party cannot repoint the image source of an emoji it does not own', is_array( $ax_emo_after ) && 'https://cdn.victim.invalid/target.png' === $ax_emo_after['source_url'] );
	ax_emo_assert( $ax_emo_results, 'a third party cannot plant a future `updated` to force an approved emoji back into review', is_array( $ax_emo_after ) && 'approved' === $ax_emo_after['review_status'] && '2024-01-01T00:00:00Z' === $ax_emo_after['updated_raw'] );
	ax_emo_assert( $ax_emo_results, 'the hearsay sighting is still recorded, since it is real evidence of use', is_array( $ax_emo_after ) && $ax_emo_after['last_seen_at'] >= $ax_emo_target['last_seen_at'] );

	// An emoji first seen only through hearsay exists, but asserts nothing.
	axismundi_emoji_observe_payload(
		array( 'id' => 'https://evil.invalid/notes/10', 'tag' => array(
			array(
				'type' => 'Emoji',
				'name' => ':unseen:',
				'id'   => 'https://stranger.invalid/emojis/unseen',
				'icon' => array( 'url' => 'https://evil.invalid/also-nasty.png' ),
			),
		) ),
		'https://evil.invalid/notes/10'
	);
	$ax_emo_hearsay = axismundi_emoji_get( 'stranger.invalid', 'unseen' );
	if ( is_array( $ax_emo_hearsay ) ) {
		$ax_emo_created[] = (int) $ax_emo_hearsay['id'];
	}
	ax_emo_assert( $ax_emo_results, 'an emoji known only by hearsay is recorded as unverified with no source URL', is_array( $ax_emo_hearsay ) && 'unverified' === $ax_emo_hearsay['review_reason'] && '' === (string) $ax_emo_hearsay['source_url'] );

	// A qualified name is hearsay by construction: the carrier is not the owner.
	$ax_emo_qualified = axismundi_emoji_descriptor_from_tag(
		array( 'type' => 'Emoji', 'name' => ':bird@third.invalid:', 'icon' => array( 'url' => 'https://cdn.x/e.png' ) ),
		'misskey.invalid'
	);
	ax_emo_assert( $ax_emo_results, 'a borrowed, authority-qualified emoji is never first-party to the server carrying it', is_array( $ax_emo_qualified ) && empty( $ax_emo_qualified['first_party'] ) );

	/* ------------------------------------------------------------------ *
	 * Licence carried through
	 * ------------------------------------------------------------------ */

	axismundi_emoji_observe_payload(
		array( 'id' => $ax_emo_subject, 'tag' => array( ax_emo_tag( ':fixture_three:', '', 'Public Domain' ) ) ),
		$ax_emo_subject
	);
	$ax_emo_three = axismundi_emoji_get( $ax_emo_authority, 'fixture_three' );
	if ( is_array( $ax_emo_three ) ) {
		$ax_emo_created[] = (int) $ax_emo_three['id'];
	}
	ax_emo_assert( $ax_emo_results, 'the licence text and its classification are stored at observation time', is_array( $ax_emo_three ) && 'allowed' === $ax_emo_three['license_state'] && 'Public Domain' === $ax_emo_three['license_text'] );
	ax_emo_assert( $ax_emo_results, 'an allowed licence still does not make an emoji visible on its own', is_array( $ax_emo_three ) && ! axismundi_emoji_is_renderable( $ax_emo_three ) );
} catch ( Throwable $ax_emo_error ) {
	ax_emo_assert( $ax_emo_results, 'the observation suite ran to completion: ' . $ax_emo_error->getMessage(), false );
} finally {
	foreach ( array_unique( $ax_emo_created ) as $ax_emo_id ) {
		$wpdb->delete( axismundi_emoji_references_table(), array( 'emoji_id' => (int) $ax_emo_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_emoji_table(), array( 'id' => (int) $ax_emo_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	$wpdb->delete( axismundi_emoji_authorities_table(), array( 'emoji_authority' => $ax_emo_authority ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
}

$ax_emo_failures = count( array_filter( $ax_emo_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_emo_results ), $ax_emo_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_emo_failures > 0 ? 1 : 0 );
}
exit( $ax_emo_failures > 0 ? 1 : 0 );
