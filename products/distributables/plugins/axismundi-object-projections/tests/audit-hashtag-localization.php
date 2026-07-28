<?php
/**
 * Received hashtag links point at this site's archive (dev-only; dist-excluded).
 *
 * The fixture is the real wire shape, fetched from a live Misskey note federated to
 * Mastodon: the body carries `<a href="https://misskey.io/tags/hashtag" rel="tag">` and
 * `tag[]` carries the matching `Hashtag`. Mastodon shows that note with a
 * `mastodon.social/tags/hashtag` link, which is the behaviour reproduced here — the
 * anchor href is presentation, `tag[]` is the authority.
 *
 * No network.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_hl_results = array();
$ax_hl_uri     = 'https://hashtag-localize.test/notes/1';

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_hl_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

try {
	$ax_hl_payload = array(
		'id'           => $ax_hl_uri,
		'type'         => 'Note',
		'attributedTo' => 'https://hashtag-localize.test/users/someone',
		'content'      => '<p>before <a href="https://hashtag-localize.test/tags/HashTag" rel="tag">#HashTag</a> and <a href="https://hashtag-localize.test/tags/undeclared" rel="tag">#undeclared</a> and <a href="https://example.org/page">a plain link</a><span data-origin="https://hashtag-localize.test/tags/HashTag">metadata</span></p>',
		'published'    => '2026-07-29T00:00:00Z',
		'tag'          => array(
			array( 'type' => 'Hashtag', 'href' => 'https://hashtag-localize.test/tags/HashTag', 'name' => '#HashTag' ),
		),
	);
	$ax_hl_stored = axismundi_op_remote_object_store( $ax_hl_payload );
	ax_hl_assert( $ax_hl_results, 'the fixture Object is cached', ! is_wp_error( $ax_hl_stored ) );

	$ax_hl_model = array( 'object_uri' => $ax_hl_uri, 'content_html' => $ax_hl_payload['content'] );
	$ax_hl_html  = apply_filters( 'axismundi_op_object_content_html', wp_kses_post( $ax_hl_payload['content'] ), $ax_hl_model );

	$ax_hl_term = axismundi_op_find_hashtag_term( '#hashtag' );
	ax_hl_assert( $ax_hl_results, 'observing the Object indexed its declared hashtag', $ax_hl_term instanceof WP_Term );
	$ax_hl_local = $ax_hl_term instanceof WP_Term ? axismundi_op_hashtag_archive_uri( $ax_hl_term ) : '';

	ax_hl_assert( $ax_hl_results, 'a declared hashtag now links to this site\'s archive', '' !== $ax_hl_local && str_contains( $ax_hl_html, 'href="' . esc_url( $ax_hl_local ) . '"' ) );
	ax_hl_assert( $ax_hl_results, 'and no longer to the origin instance\'s tag page', ! str_contains( $ax_hl_html, 'href="https://hashtag-localize.test/tags/HashTag"' ) );

	/*
	 * The author's spelling is theirs. `#HashTag` and `#hashtag` are one tag for matching
	 * and two readings for a person, which is why normalization keeps a display `name`
	 * beside the lowercased `key` — the same split the emoji registry uses.
	 */
	ax_hl_assert( $ax_hl_results, 'the visible text keeps the author\'s capitalisation', str_contains( $ax_hl_html, '>#HashTag</a>' ) );
	ax_hl_assert( $ax_hl_results, 'even though matching found it under a lowercased key', $ax_hl_term instanceof WP_Term && 'hashtag' === strtolower( $ax_hl_term->name ) );

	/*
	 * Declared, not guessed. An anchor that merely looks like a hashtag was not vouched
	 * for by the Object, so rewriting it would be this site inventing a claim about
	 * somebody else's message.
	 */
	ax_hl_assert( $ax_hl_results, 'an undeclared tag-looking anchor is left exactly as written', str_contains( $ax_hl_html, 'href="https://hashtag-localize.test/tags/undeclared"' ) );
	ax_hl_assert( $ax_hl_results, 'and an ordinary link is untouched', str_contains( $ax_hl_html, 'href="https://example.org/page"' ) );
	ax_hl_assert( $ax_hl_results, 'only an anchor href is localized, not another element attribute containing the same origin URL', str_contains( $ax_hl_html, 'data-origin="https://hashtag-localize.test/tags/HashTag"' ) );

	// Rendering must not write. A tag this site never indexed leaves the markup alone
	// rather than minting a taxonomy row during a page view.
	$ax_hl_before = (int) wp_count_terms( array( 'taxonomy' => AXISMUNDI_OP_HASHTAG_TAXONOMY, 'hide_empty' => false ) );
	apply_filters( 'axismundi_op_object_content_html', wp_kses_post( $ax_hl_payload['content'] ), $ax_hl_model );
	ax_hl_assert( $ax_hl_results, 'rendering creates no terms, because a page view is not an observation', (int) wp_count_terms( array( 'taxonomy' => AXISMUNDI_OP_HASHTAG_TAXONOMY, 'hide_empty' => false ) ) === $ax_hl_before );

	// A local Object has no cached payload to consult, so the filter must pass it through.
	$ax_hl_local_model = array( 'object_uri' => home_url( '/?p=999999' ), 'content_html' => '<p>a <a href="https://example.org/x">link</a></p>' );
	ax_hl_assert(
		$ax_hl_results,
		'a body with no cached declaration behind it passes through unchanged',
		'<p>a <a href="https://example.org/x">link</a></p>' === apply_filters( 'axismundi_op_object_content_html', '<p>a <a href="https://example.org/x">link</a></p>', $ax_hl_local_model )
	);
} catch ( Throwable $ax_hl_error ) {
	ax_hl_assert( $ax_hl_results, 'the hashtag localization suite ran to completion: ' . $ax_hl_error->getMessage(), false );
} finally {
	$wpdb->delete( axismundi_op_remote_objects_table(), array( 'object_uri_hash' => hash( 'sha256', $ax_hl_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$ax_hl_term = axismundi_op_find_hashtag_term( '#hashtag' );
	if ( $ax_hl_term instanceof WP_Term && 0 === (int) $ax_hl_term->count ) {
		wp_delete_term( $ax_hl_term->term_id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	}
}

$ax_hl_failures = count( array_filter( $ax_hl_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_hl_results ), $ax_hl_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_hl_failures > 0 ? 1 : 0 );
}
exit( $ax_hl_failures > 0 ? 1 : 0 );
