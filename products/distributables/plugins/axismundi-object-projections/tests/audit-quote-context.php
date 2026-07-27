<?php
/**
 * Consent-aware quote-context projection and dynamic block regression.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_qc_results = array();
$ax_qc_uris    = array();
$ax_qc_public  = 'https://www.w3.org/ns/activitystreams#Public';
$ax_qc_suffix  = strtolower( wp_generate_password( 8, false, false ) );
$ax_qc_actor   = 'https://example.com/users/quote-context-' . $ax_qc_suffix;

/** @param bool[] $results Test results. */
function ax_qc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** @return array<string,mixed> */
function ax_qc_enriched_model( string $uri ) : array {
	$source = axismundi_op_resolve_source_by_uri( $uri );
	$model  = null === $source ? null : axismundi_op_object_view_model( $source );
	if ( ! is_array( $model ) ) {
		return array();
	}
	axismundi_op_set_current_object_view_model( $model );
	$enriched = (array) axismundi_op_current_object_view_model();
	axismundi_op_set_current_object_view_model( null );
	return $enriched;
}

/** Render the dynamic block with one enriched model as current context. */
function ax_qc_render( array $model ) : string {
	axismundi_op_set_current_object_view_model( $model );
	$html = do_blocks( '<!-- wp:axismundi/quote-context /-->' );
	axismundi_op_set_current_object_view_model( null );
	return $html;
}

try {
	axismundi_op_install();
	$nested_uri = 'https://example.com/objects/nested-' . $ax_qc_suffix;
	$target_uri = 'https://example.com/objects/article-' . $ax_qc_suffix;
	$source_uri = 'https://example.com/objects/source-' . $ax_qc_suffix;
	$tombstone_source_uri = 'https://example.com/objects/tombstone-source-' . $ax_qc_suffix;
	$cycle_uri  = 'https://example.com/objects/cycle-' . $ax_qc_suffix;
	$ax_qc_uris = array( $nested_uri, $target_uri, $source_uri, $tombstone_source_uri, $cycle_uri );

	foreach (
		array(
			array( 'id' => $nested_uri, 'type' => 'Article', 'attributedTo' => $ax_qc_actor, 'name' => 'Earlier article', 'content' => '<p>NESTED-SECRET-CONTENT</p>', 'to' => array( $ax_qc_public ) ),
			array( 'id' => $target_uri, 'type' => 'Article', 'attributedTo' => $ax_qc_actor, 'name' => 'Quoted article', 'summary' => 'Quoted article summary.', 'content' => '<p>Quoted article body.</p>', 'quote' => $nested_uri, 'to' => array( $ax_qc_public ) ),
			array( 'id' => $source_uri, 'type' => 'Note', 'attributedTo' => $ax_qc_actor, 'content' => '<p>Source commentary.</p>', 'quote' => $target_uri, 'to' => array( $ax_qc_public ) ),
			array( 'id' => $tombstone_source_uri, 'type' => 'Note', 'attributedTo' => $ax_qc_actor, 'content' => '<p>Tombstone commentary.</p>', 'quote' => $target_uri, 'to' => array( $ax_qc_public ) ),
			array( 'id' => $cycle_uri, 'type' => 'Note', 'attributedTo' => $ax_qc_actor, 'content' => '<p>Cycle commentary.</p>', 'quote' => $cycle_uri, 'to' => array( $ax_qc_public ) ),
		) as $payload
	) {
		axismundi_op_remote_object_store( $payload );
	}

	$embed_model = ax_qc_enriched_model( $source_uri );
	$embed       = (array) ( $embed_model['quote_context'] ?? array() );
	$embed_html  = ax_qc_render( $embed_model );
	ax_qc_assert(
		$ax_qc_results,
		'a direct Article quote embeds compact Article metadata and limits its nested quote to a reference',
		'embed' === (string) ( $embed['display_state'] ?? '' )
			&& 'Quoted article' === (string) ( $embed['target']['title'] ?? '' )
			&& 'Earlier article' === (string) ( $embed['nested']['title'] ?? '' )
			&& false !== strpos( $embed_html, 'Quoted article summary.' )
			&& false !== strpos( $embed_html, 'Read article' )
			&& false !== strpos( $embed_html, 'Earlier article' )
			&& false === strpos( $embed_html, 'NESTED-SECRET-CONTENT' )
	);

	$pending_filter = static function ( $state, array $model ) use ( $source_uri ) {
		return $source_uri === (string) ( $model['object_uri'] ?? '' ) ? 'pending' : $state;
	};
	add_filter( 'axismundi_op_quote_state_for_object', $pending_filter, 1, 2 );
	$pending_model = ax_qc_enriched_model( $source_uri );
	$pending       = (array) ( $pending_model['quote_context'] ?? array() );
	$pending_html  = ax_qc_render( $pending_model );
	remove_filter( 'axismundi_op_quote_state_for_object', $pending_filter, 1 );
	ax_qc_assert(
		$ax_qc_results,
		'a pending quote retains the quoting Object but resolves neither target metadata nor body',
		'pending' === (string) ( $pending['display_state'] ?? '' )
			&& null === ( $pending['target'] ?? null )
			&& false !== strpos( $pending_html, 'Quote approval pending' )
			&& false === strpos( $pending_html, 'Quoted article body.' )
			&& false === strpos( $pending_html, 'Quoted article summary.' )
	);

	axismundi_op_verify_quote_consent( $source_uri, $target_uri, 'https://example.com/authorizations/' . $ax_qc_suffix, 'rejected' );
	$rejected_model = ax_qc_enriched_model( $source_uri );
	$rejected       = (array) ( $rejected_model['quote_context'] ?? array() );
	$rejected_html  = ax_qc_render( $rejected_model );
	ax_qc_assert(
		$ax_qc_results,
		'a rejected quote renders a placeholder and never resolves or leaks the quoted Article body',
		'unavailable' === (string) ( $rejected['display_state'] ?? '' )
			&& 'rejected' === (string) ( $rejected['reason'] ?? '' )
			&& null === ( $rejected['target'] ?? null )
			&& false !== strpos( $rejected_html, 'Quote request rejected' )
			&& false === strpos( $rejected_html, 'Quoted article body.' )
	);

	axismundi_op_remote_object_store(
		array(
			'id'           => $target_uri,
			'type'         => 'Tombstone',
			'attributedTo' => $ax_qc_actor,
			'to'           => array( $ax_qc_public ),
		)
	);
	axismundi_op_verify_quote_consent( $tombstone_source_uri, $target_uri, 'https://example.com/authorizations/tombstone-' . $ax_qc_suffix, 'accepted' );
	$tombstone_model = ax_qc_enriched_model( $tombstone_source_uri );
	$tombstone       = (array) ( $tombstone_model['quote_context'] ?? array() );
	$tombstone_html  = ax_qc_render( $tombstone_model );
	ax_qc_assert(
		$ax_qc_results,
		'a deleted quoted Object becomes a tombstone placeholder instead of an embed',
		'unavailable' === (string) ( $tombstone['display_state'] ?? '' )
			&& 'tombstone' === (string) ( $tombstone['reason'] ?? '' )
			&& null === ( $tombstone['target'] ?? null )
			&& false !== strpos( $tombstone_html, 'Quoted object deleted' )
	);

	$cycle_model = ax_qc_enriched_model( $cycle_uri );
	$cycle       = (array) ( $cycle_model['quote_context'] ?? array() );
	ax_qc_assert(
		$ax_qc_results,
		'a self-referential quote becomes a cycle placeholder without recursive resolution',
		'unavailable' === (string) ( $cycle['display_state'] ?? '' ) && 'cycle' === (string) ( $cycle['reason'] ?? '' ) && null === ( $cycle['target'] ?? null )
	);
} finally {
	axismundi_op_set_current_object_view_model( null );
	foreach ( $ax_qc_uris as $uri ) {
		axismundi_op_remote_object_delete( $uri );
	}
}

$ax_qc_failures = count( array_filter( $ax_qc_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_qc_results ), $ax_qc_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_qc_failures > 0 ? 1 : 0 );
}
exit( $ax_qc_failures > 0 ? 1 : 0 );
