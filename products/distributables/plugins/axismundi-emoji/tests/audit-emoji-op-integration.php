<?php
/**
 * Object Projections integration (dev-only; dist-excluded).
 *
 * The Object body is the one surface where all three emoji layers meet, so this is
 * where their order has to be proved rather than asserted: a declared shortcode becomes
 * a custom emoji, an undeclared one is still Core's smiley, and neither reaches into
 * `<code>`. Everything else in the plugin can be right while this order is wrong.
 *
 * No network. A remote Object is stored directly and removed afterwards.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';

global $wpdb;
$ax_op_results = array();
$ax_op_ids     = array();
$ax_op_uri     = 'https://emoji-op.test/notes/1';

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_op_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

try {
	if ( ! function_exists( 'axismundi_op_remote_object_store' ) ) {
		ax_op_assert( $ax_op_results, 'Object Projections is available to integrate with', false );
		throw new RuntimeException( 'Object Projections not active' );
	}
	axismundi_emoji_install();

	// Two emoji this Object will declare, plus one it will not.
	foreach ( array( 'partyparrot', 'cool' ) as $ax_op_key ) {
		$hash = hash( 'sha256', 'emoji-op.test/' . $ax_op_key );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
			axismundi_emoji_table(),
			array(
				'emoji_authority' => 'emoji-op.test',
				'shortcode_key'   => $ax_op_key,
				'shortcode'       => ':' . $ax_op_key . ':',
				'source_url'      => 'https://cdn.emoji-op.test/' . $ax_op_key . '.png',
				'scope'           => 'remote',
				'source_kind'     => 'remote',
				'review_status'   => 'approved',
				'content_hash'    => $hash,
				'cached_path'     => axismundi_emoji_cache_relative_path( $hash, 'png' ),
				'byte_size'       => 100,
				'first_seen_at'   => current_time( 'mysql', true ),
				'last_seen_at'    => current_time( 'mysql', true ),
			)
		);
		$ax_op_ids[] = (int) $wpdb->insert_id;
	}

	$ax_op_payload = array(
		'id'           => $ax_op_uri,
		'type'         => 'Note',
		'attributedTo' => 'https://emoji-op.test/users/someone',
		'content'      => '<p>a :partyparrot: b :cool: c :mrgreen: d</p><pre>:partyparrot:</pre>',
		'published'    => '2026-07-27T00:00:00Z',
		'tag'          => array(
			array(
				'type' => 'Emoji',
				'name' => ':partyparrot:',
				'id'   => 'https://emoji-op.test/emojis/partyparrot',
				'icon' => array( 'url' => 'https://cdn.emoji-op.test/partyparrot.png' ),
			),
		),
	);
	$ax_op_stored = axismundi_op_remote_object_store( $ax_op_payload );
	ax_op_assert( $ax_op_results, 'the fixture Object is stored', ! is_wp_error( $ax_op_stored ) );

	$ax_op_model = array( 'object_uri' => $ax_op_uri, 'content_html' => $ax_op_payload['content'] );
	$ax_op_map   = axismundi_emoji_object_declarations( $ax_op_model );
	ax_op_assert( $ax_op_results, 'declarations are read from the Object\'s own stored payload', array( 'partyparrot' ) === array_keys( $ax_op_map ) );

	$ax_op_body = apply_filters( 'axismundi_op_object_content_html', wp_kses_post( $ax_op_payload['content'] ), $ax_op_model );

	ax_op_assert( $ax_op_results, 'a declared shortcode in the body becomes its custom emoji', str_contains( $ax_op_body, 'alt=":partyparrot:"' ) );
	ax_op_assert( $ax_op_results, 'an undeclared shortcode is left alone at this stage, whatever the registry holds', str_contains( $ax_op_body, ':cool:' ) && ! str_contains( $ax_op_body, 'alt=":cool:"' ) );
	ax_op_assert( $ax_op_results, 'a declared shortcode inside <pre> stays text, because it is an example rather than a glyph', str_contains( $ax_op_body, '<pre>:partyparrot:</pre>' ) );

	/*
	 * The layer boundary: Core still owns everything we did not claim.
	 *
	 * Checked by what Core produced, not by the shortcode disappearing — a converted
	 * `:mrgreen:` becomes an `<img … alt=":mrgreen:">`, so the text is still present and
	 * an absence test would report failure for a conversion that worked. `:cool:` has a
	 * Unicode replacement and leaves no trace, which is why both forms are asserted.
	 */
	$ax_op_final = convert_smilies( $ax_op_body );
	ax_op_assert( $ax_op_results, 'Core smilies then convert the shortcodes we left, so the two layers cooperate', ! str_contains( $ax_op_final, ':cool:' ) && str_contains( $ax_op_final, 'wp-smiley' ) );
	ax_op_assert( $ax_op_results, 'and the custom emoji survives that pass untouched', str_contains( $ax_op_final, 'alt=":partyparrot:"' ) && str_contains( $ax_op_final, 'ax-emoji' ) );

	$ax_op_css = file_get_contents( WP_PLUGIN_DIR . '/axismundi-object-projections/assets/object-view.css' );
	ax_op_assert(
		$ax_op_results,
		'Object bodies give inline emoji a legible one-and-a-half-em size without changing profile-name sizing',
		is_string( $ax_op_css ) && str_contains( $ax_op_css, '.axismundi-object__content img.ax-emoji' ) && str_contains( $ax_op_css, 'inline-size: 1.5em' )
	);
	ax_op_assert(
		$ax_op_results,
		'the media dialog uses the same emoji scale as the Object body it repeats',
		is_string( $ax_op_css ) && str_contains( $ax_op_css, '.axismundi-object__media-panel-body img.ax-emoji' )
	);

	// Observation runs off the same events that cache the Object.
	$wpdb->delete( axismundi_emoji_table(), array( 'emoji_authority' => 'emoji-op.test' ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	// The handler directly, not the action. Firing a real hook here would also invoke
	// every other plugin listening on it with a fixture argument it never expects, which
	// tests their type signatures rather than this integration.
	axismundi_emoji_observe_remote_object( is_array( $ax_op_stored ) ? $ax_op_stored : array( 'object_uri' => $ax_op_uri, 'payload' => $ax_op_payload ) );
	$ax_op_observed = axismundi_emoji_get( 'emoji-op.test', 'partyparrot' );
	if ( is_array( $ax_op_observed ) ) {
		$ax_op_ids[] = (int) $ax_op_observed['id'];
	}
	ax_op_assert( $ax_op_results, 'storing a remote Object records the emoji it declares', is_array( $ax_op_observed ) );
	ax_op_assert( $ax_op_results, 'and records them as pending, so nothing is fetched or shown without review', is_array( $ax_op_observed ) && 'pending' === $ax_op_observed['review_status'] );

	$ax_op_after = apply_filters( 'axismundi_op_object_content_html', wp_kses_post( $ax_op_payload['content'] ), $ax_op_model );
	ax_op_assert( $ax_op_results, 'an unreviewed emoji renders as its shortcode in the body', str_contains( $ax_op_after, ':partyparrot:' ) && ! str_contains( $ax_op_after, 'alt=":partyparrot:"' ) );
} catch ( Throwable $ax_op_error ) {
	ax_op_assert( $ax_op_results, 'the Object Projections suite ran to completion: ' . $ax_op_error->getMessage(), false );
} finally {
	foreach ( array_unique( array_filter( $ax_op_ids ) ) as $ax_op_id ) {
		$wpdb->delete( axismundi_emoji_table(), array( 'id' => (int) $ax_op_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	$wpdb->delete( axismundi_emoji_table(), array( 'emoji_authority' => 'emoji-op.test' ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->delete( $wpdb->prefix . 'ax_remote_objects', array( 'object_uri_hash' => hash( 'sha256', $ax_op_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
}

$ax_op_failures = count( array_filter( $ax_op_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_op_results ), $ax_op_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_op_failures > 0 ? 1 : 0 );
}
exit( $ax_op_failures > 0 ? 1 : 0 );
