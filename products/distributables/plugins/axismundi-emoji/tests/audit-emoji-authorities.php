<?php
/**
 * Per-authority standing decisions (dev-only; dist-excluded).
 *
 * The claim under test is a narrow one and easy to get wrong in the generous direction:
 * trusting an instance must remove friction for what that instance itself declares, and
 * nothing else. So the interesting assertions here are all about what the setting does
 * NOT do — it does not vouch for hearsay, does not reach backwards on its own, does not
 * override a licence, and does not spill onto a neighbouring host.
 *
 * No network. Fixtures use `.test` hosts so they cannot collide with live rows.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';

global $wpdb;
$ax_auth_results = array();
$ax_auth_trusted = 'trusted-authority.test';
$ax_auth_other   = 'other-authority.test';

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_auth_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * Insert a pending remote emoji directly, bypassing observation.
 *
 * @param string      $authority Declaring host.
 * @param string      $key       Shortcode key.
 * @param string      $license   Licence state.
 * @param string|null $reason    Review reason.
 * @return int
 */
function ax_auth_seed( string $authority, string $key, string $license = 'unknown', ?string $reason = null ) : int {
	global $wpdb;
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
		axismundi_emoji_table(),
		array(
			'emoji_authority' => $authority,
			'shortcode_key'   => $key,
			'shortcode'       => ':' . $key . ':',
			'source_url'      => 'https://cdn.' . $authority . '/' . $key . '.png',
			'scope'           => 'remote',
			'source_kind'     => 'remote',
			'review_status'   => 'pending',
			'review_reason'   => $reason,
			'license_state'   => $license,
			'first_seen_at'   => current_time( 'mysql', true ),
			'last_seen_at'    => current_time( 'mysql', true ),
		)
	);
	return (int) $wpdb->insert_id;
}

try {
	axismundi_emoji_install();

	// A clean slate, in case an earlier interrupted run left rows behind.
	foreach ( array( $ax_auth_trusted, $ax_auth_other ) as $ax_auth_host ) {
		$wpdb->delete( axismundi_emoji_table(), array( 'emoji_authority' => $ax_auth_host ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_emoji_authorities_table(), array( 'emoji_authority' => $ax_auth_host ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}

	// -- Storing the decision --------------------------------------------------------

	ax_auth_assert( $ax_auth_results, 'an authority default is stored', axismundi_emoji_set_authority_default( $ax_auth_trusted, 'approved', 1, 20 ) );
	ax_auth_assert( $ax_auth_results, 'and reads back as the standing decision', 'approved' === axismundi_emoji_authority_default( $ax_auth_trusted ) );
	$ax_auth_priority = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT fallback_priority FROM ' . axismundi_emoji_authorities_table() . ' WHERE emoji_authority = %s', $ax_auth_trusted ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	ax_auth_assert( $ax_auth_results, 'a fallback source priority is stored separately from the auto-review decision', 20 === $ax_auth_priority );
	ax_auth_assert( $ax_auth_results, 'an authority with no decision is still reviewed one by one', 'pending' === axismundi_emoji_authority_default( $ax_auth_other ) );
	ax_auth_assert( $ax_auth_results, 'a state outside the vocabulary is refused rather than stored', ! axismundi_emoji_set_authority_default( $ax_auth_trusted, 'whatever', 1 ) );
	ax_auth_assert( $ax_auth_results, 'and refusing it leaves the previous decision intact', 'approved' === axismundi_emoji_authority_default( $ax_auth_trusted ) );

	/*
	 * The streak is evidence accumulated about an authority across many decisions. A
	 * REPLACE-based upsert would discard it every time the dropdown was touched, so the
	 * fact that it survives is the thing worth asserting.
	 */
	$wpdb->update( axismundi_emoji_authorities_table(), array( 'rejected_streak' => 7 ), array( 'emoji_authority' => $ax_auth_trusted ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	axismundi_emoji_set_authority_default( $ax_auth_trusted, 'approved', 1, 7 );
	$ax_auth_streak = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT rejected_streak FROM ' . axismundi_emoji_authorities_table() . ' WHERE emoji_authority = %s', $ax_auth_trusted ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	ax_auth_assert( $ax_auth_results, 'changing the decision preserves what this authority has already earned', 7 === $ax_auth_streak );
	$ax_auth_priority = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT fallback_priority FROM ' . axismundi_emoji_authorities_table() . ' WHERE emoji_authority = %s', $ax_auth_trusted ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	ax_auth_assert( $ax_auth_results, 'changing a standing decision updates only the requested fallback priority', 7 === $ax_auth_priority );

	// -- What the decision governs ---------------------------------------------------

	$ax_auth_tag = array(
		'type' => 'Emoji',
		'name' => ':firstparty:',
		'id'   => 'https://' . $ax_auth_trusted . '/emojis/firstparty',
		'icon' => array( 'type' => 'Image', 'url' => 'https://cdn.' . $ax_auth_trusted . '/firstparty.png' ),
	);
	axismundi_emoji_observe_payload(
		array( 'id' => 'https://' . $ax_auth_trusted . '/notes/1', 'type' => 'Note', 'tag' => array( $ax_auth_tag ) ),
		'https://' . $ax_auth_trusted . '/notes/1',
		'object'
	);
	$ax_auth_first = axismundi_emoji_get( $ax_auth_trusted, 'firstparty' );
	ax_auth_assert( $ax_auth_results, 'an emoji declared by the trusted authority itself arrives approved', is_array( $ax_auth_first ) && 'approved' === $ax_auth_first['review_status'] );

	/*
	 * Hearsay: the same emoji name, declared by a post on a different server. Trusting
	 * `trusted-authority.test` is a statement about what that server says, and this is not
	 * that server saying it — so the standing decision must not apply.
	 */
	axismundi_emoji_observe_payload(
		array(
			'id'   => 'https://' . $ax_auth_other . '/notes/9',
			'type' => 'Note',
			'tag'  => array(
				array(
					'type' => 'Emoji',
					'name' => ':hearsay@' . $ax_auth_trusted . ':',
					'icon' => array( 'type' => 'Image', 'url' => 'https://cdn.' . $ax_auth_trusted . '/hearsay.png' ),
				),
			),
		),
		'https://' . $ax_auth_other . '/notes/9',
		'object'
	);
	$ax_auth_hearsay = axismundi_emoji_get( $ax_auth_trusted, 'hearsay' );
	ax_auth_assert( $ax_auth_results, 'the same authority reported second-hand does not inherit its trust', is_array( $ax_auth_hearsay ) && 'pending' === $ax_auth_hearsay['review_status'] );
	ax_auth_assert( $ax_auth_results, 'and says why it is waiting', is_array( $ax_auth_hearsay ) && 'unverified' === $ax_auth_hearsay['review_reason'] );

	// -- The retroactive sweep, as a separate act ------------------------------------

	$ax_auth_plain      = ax_auth_seed( $ax_auth_trusted, 'plain' );
	$ax_auth_restricted = ax_auth_seed( $ax_auth_trusted, 'norights', 'restricted' );
	$ax_auth_unverified = ax_auth_seed( $ax_auth_trusted, 'nosource', 'unknown', 'unverified' );
	$ax_auth_neighbour  = ax_auth_seed( $ax_auth_other, 'neighbour' );

	ax_auth_assert(
		$ax_auth_results,
		'storing a decision does not retroactively approve the queue behind it',
		axismundi_emoji_set_authority_default( $ax_auth_trusted, 'approved', 1 )
			&& 'pending' === (string) axismundi_emoji_get( $ax_auth_trusted, 'plain' )['review_status']
	);

	$ax_auth_swept = axismundi_emoji_approve_pending_for_authority( $ax_auth_trusted, 1 );
	ax_auth_assert( $ax_auth_results, 'the sweep approves the ordinary waiting emoji', 'approved' === (string) axismundi_emoji_get( $ax_auth_trusted, 'plain' )['review_status'] );
	ax_auth_assert( $ax_auth_results, 'a licence that forbids reuse is not swept past — that judgement stays a person\'s', 'pending' === (string) axismundi_emoji_get( $ax_auth_trusted, 'norights' )['review_status'] );
	ax_auth_assert( $ax_auth_results, 'an unverified row is not swept either, because there is nothing yet to judge', 'pending' === (string) axismundi_emoji_get( $ax_auth_trusted, 'nosource' )['review_status'] );
	ax_auth_assert( $ax_auth_results, 'the neighbouring authority is untouched by it', 'pending' === (string) axismundi_emoji_get( $ax_auth_other, 'neighbour' )['review_status'] );
	ax_auth_assert( $ax_auth_results, 'and the sweep reports only what it actually changed', 1 === $ax_auth_swept );
	ax_auth_assert( $ax_auth_results, 'running it again changes nothing, so the button is safe to press twice', 0 === axismundi_emoji_approve_pending_for_authority( $ax_auth_trusted, 1 ) );

	/* The undo boundary is the recorded bulk batch, not every approval on this authority. */
	$ax_auth_hash = hash( 'sha256', 'authority-batch-cache' );
	$ax_auth_path = axismundi_emoji_cache_relative_path( $ax_auth_hash, 'webp' );
	$ax_auth_file = trailingslashit( axismundi_emoji_cache_dir() ) . $ax_auth_path;
	wp_mkdir_p( dirname( $ax_auth_file ) );
	copy( axismundi_emoji_bundled_path(), $ax_auth_file );
	$wpdb->update( axismundi_emoji_table(), array( 'content_hash' => $ax_auth_hash, 'cached_path' => $ax_auth_path ), array( 'id' => $ax_auth_plain ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture setup.
	$ax_auth_batch = axismundi_emoji_latest_approval_batch( $ax_auth_trusted );
	ax_auth_assert( $ax_auth_results, 'the bulk approval records a reversible batch boundary', is_array( $ax_auth_batch ) && 1 === (int) $ax_auth_batch['count'] );
	ax_auth_assert( $ax_auth_results, 'the batch can hold cached bytes before its undo', file_exists( $ax_auth_file ) );
	$ax_auth_undone = is_array( $ax_auth_batch ) ? axismundi_emoji_undo_approval_batch( $ax_auth_trusted, (string) $ax_auth_batch['batch'], 1 ) : 0;
	$ax_auth_plain_after = axismundi_emoji_get( $ax_auth_trusted, 'plain' );
	ax_auth_assert( $ax_auth_results, 'undo moves only that batch back to ordinary review', 1 === $ax_auth_undone && is_array( $ax_auth_plain_after ) && 'pending' === $ax_auth_plain_after['review_status'] && '' === (string) $ax_auth_plain_after['cached_path'] );
	ax_auth_assert( $ax_auth_results, 'undo releases an unshared cached blob instead of leaving it charged on disk', ! file_exists( $ax_auth_file ) );
	ax_auth_assert( $ax_auth_results, 'a consumed batch cannot undo a second time', 0 === ( is_array( $ax_auth_batch ) ? axismundi_emoji_undo_approval_batch( $ax_auth_trusted, (string) $ax_auth_batch['batch'], 1 ) : -1 ) );

	// -- What the screen shows -------------------------------------------------------

	$ax_auth_summary = array();
	foreach ( axismundi_emoji_authority_summary() as $ax_auth_row ) {
		$ax_auth_summary[ (string) $ax_auth_row['emoji_authority'] ] = $ax_auth_row;
	}
	ax_auth_assert( $ax_auth_results, 'the summary lists every authority that has declared an emoji', isset( $ax_auth_summary[ $ax_auth_trusted ], $ax_auth_summary[ $ax_auth_other ] ) );
	ax_auth_assert( $ax_auth_results, 'it carries each authority\'s standing decision', 'approved' === (string) $ax_auth_summary[ $ax_auth_trusted ]['review_default'] );
	ax_auth_assert( $ax_auth_results, 'and defaults an undecided authority to per-emoji review', 'pending' === (string) $ax_auth_summary[ $ax_auth_other ]['review_default'] );
	// Waiting: the hearsay row, the restricted one, the unverified one. Decided: the
	// first-party arrival the standing decision approved; the bulk-approved row was undone.
	ax_auth_assert( $ax_auth_results, 'the counts separate what is still waiting from what was decided', 4 === (int) $ax_auth_summary[ $ax_auth_trusted ]['pending'] && 1 === (int) $ax_auth_summary[ $ax_auth_trusted ]['approved'] );
	ax_auth_assert( $ax_auth_results, 'and surface the licence-restricted rows the sweep will refuse', 1 === (int) $ax_auth_summary[ $ax_auth_trusted ]['restricted'] );

	unset( $ax_auth_plain, $ax_auth_restricted, $ax_auth_unverified, $ax_auth_neighbour );
} catch ( Throwable $ax_auth_error ) {
	ax_auth_assert( $ax_auth_results, 'the authorities suite ran to completion: ' . $ax_auth_error->getMessage(), false );
} finally {
	foreach ( array( $ax_auth_trusted, $ax_auth_other ) as $ax_auth_host ) {
		$wpdb->delete( axismundi_emoji_table(), array( 'emoji_authority' => $ax_auth_host ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_emoji_authorities_table(), array( 'emoji_authority' => $ax_auth_host ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$ax_auth_failures = count( array_filter( $ax_auth_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_auth_results ), $ax_auth_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_auth_failures > 0 ? 1 : 0 );
}
exit( $ax_auth_failures > 0 ? 1 : 0 );
