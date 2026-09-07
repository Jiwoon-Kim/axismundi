<?php
/**
 * Quote relation resolution: the `quote_context` projection added to a full
 * Object View Model at the enrichment boundary.
 *
 * A quote is displayed by *availability*, not by protocol detail: whichever
 * product owns the source Object (a local Note's own authored intent, or OP's
 * generic indexed relation for anything else, local or remote) answers one raw
 * state string through the `axismundi_op_quote_state_for_object` filter, and
 * this file is the only place that normalizes every product's vocabulary into
 * the three states a renderer actually branches on: `embed`, `pending`,
 * `unavailable`. A protocol-conformant quote with no consent layer at all
 * (Misskey, or any quote authored before FEP-044f) is `embed` -- consent is an
 * optional richer layer on top of an already-legitimate quote, never a
 * prerequisite for showing one.
 *
 * Nesting is bounded at the data layer, not just in rendering: the direct quote
 * target gets a full compact projection (`target`), and that target's own quote
 * target -- if any -- gets only a `nested` reference (its identity and canonical
 * URL, never its content), so a chain of quotes cannot pull three, four, or an
 * unbounded number of remote objects into one request. Resolution never performs
 * a synchronous remote fetch; a cache miss is `unavailable`, exactly like a
 * rejected or revoked quote, and backfill is an observation/reconciliation
 * concern, not a render-time one.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/**
 * OP's own default quote-state answer: the object_relations projection's
 * indexed consent state, for any source -- local or remote -- that owns no
 * richer product-level state (or hasn't been claimed by one).
 *
 * Registered at a low priority so a product's own filter (e.g. a local Note's
 * authored lifecycle, which knows about `pending`/`rejected` states this table
 * cannot see) is asked first and this is only the fallback.
 *
 * @param string|null          $state Existing answer; returned unchanged if already set.
 * @param array<string,mixed>  $model View model of the quoting (source) Object.
 * @return string|null
 */
function axismundi_op_default_quote_state( $state, array $model ) {
	if ( null !== $state ) {
		return $state;
	}
	$source_uri = (string) ( $model['object_uri'] ?? $model['id'] ?? '' );
	if ( '' === $source_uri ) {
		return null;
	}
	$relation = axismundi_op_quote_relation_for_source( $source_uri );
	return null !== $relation ? $relation['consent_status'] : null;
}
add_filter( 'axismundi_op_quote_state_for_object', 'axismundi_op_default_quote_state', 20, 2 );

/**
 * Normalize any product's raw quote state into the three states a renderer
 * branches on, plus the specific reason an unavailable/pending state carries
 * for diagnostics (never shown verbatim to a reader).
 *
 * `null` (no state answered at all, e.g. legacy content with a bare `quote_uri`
 * and no relation ever indexed) still displays: the object is present, so the
 * quote is exactly as legitimate as it always was pre-consent-layer.
 *
 * @param string|null $raw_state One adapter's own vocabulary.
 * @return array{display_state:string,reason:string}
 */
function axismundi_op_normalize_quote_display_state( ?string $raw_state ) : array {
	switch ( $raw_state ) {
		case 'self':
		case 'accepted':
		case 'approved':
		case 'legacy_unverified':
		case null:
			return array(
				'display_state' => 'embed',
				'reason'         => '',
			);
		case 'pending':
		case 'not-requested':
			return array(
				'display_state' => 'pending',
				'reason'         => 'pending',
			);
		case 'rejected':
			return array(
				'display_state' => 'unavailable',
				'reason'         => 'rejected',
			);
		case 'revoked':
			return array(
				'display_state' => 'unavailable',
				'reason'         => 'revoked',
			);
		case 'invalid':
			return array(
				'display_state' => 'unavailable',
				'reason'         => 'unverified',
			);
		default:
			return array(
				'display_state' => 'unavailable',
				'reason'         => 'unavailable',
			);
	}
}

/**
 * Compact identity for whoever a resolved source is attributed to.
 *
 * @param array<string,mixed> $model Normalized view model of that source.
 * @return array{name:string,handle:string,avatar_url:string,url:string}
 */
function axismundi_op_quote_context_author( array $model ) : array {
	$author = (array) ( $model['author'] ?? array() );
	return array(
		'name'       => (string) ( $author['name'] ?? '' ),
		'handle'     => (string) ( $author['handle'] ?? '' ),
		'avatar_url' => (string) ( $author['avatar_url'] ?? '' ),
		'url'        => (string) ( $author['url'] ?? '' ),
	);
}

/**
 * Compact projection for a full-embed quote target (depth 1): identity, a short
 * excerpt, and enough to pick a presentation, but never the full reply-tree,
 * hashtag, attachment gallery, or actions data a full card carries.
 *
 * @param array<string,mixed> $model Normalized (not enriched) view model.
 * @return array<string,mixed>
 */
function axismundi_op_quote_context_target_projection( array $model ) : array {
	$sensitive = ! empty( $model['sensitive'] );
	$warning   = trim( (string) ( $model['content_warning'] ?? '' ) );
	$excerpt   = $sensitive
		? ''
		: trim( (string) ( $model['summary'] ?? '' ) );
	if ( ! $sensitive && '' === $excerpt ) {
		$excerpt = wp_trim_words( wp_strip_all_tags( (string) ( $model['content_html'] ?? '' ) ), 40 );
	}
	return array(
		'uri'              => (string) ( $model['object_uri'] ?? $model['id'] ?? '' ),
		'url'              => (string) ( $model['human_url'] ?? '' ),
		'type'             => (string) ( $model['type'] ?? 'Note' ),
		'author'           => axismundi_op_quote_context_author( $model ),
		'title'            => trim( (string) ( $model['title'] ?? $model['name'] ?? '' ) ),
		'excerpt'          => $excerpt,
		'sensitive'        => $sensitive,
		'content_warning'  => $warning,
		'thumbnail'        => is_array( $model['media']['featured'] ?? null ) ? $model['media']['featured'] : null,
		'published'        => (string) ( $model['published'] ?? '' ),
	);
}

/**
 * Minimal depth-2 reference: identity and a canonical link only, never content.
 * No consent state is evaluated at this depth -- a reference never displays the
 * referenced Object's own body, so there is nothing to gate.
 *
 * @param string        $uri     Depth-2 target URI.
 * @param array<string> $visited URIs already on this resolution path (cycle guard).
 * @return array<string,mixed>|null
 */
function axismundi_op_quote_context_reference( string $uri, array $visited ) : ?array {
	if ( '' === $uri || in_array( $uri, $visited, true ) ) {
		return null;
	}
	$source = axismundi_op_resolve_source_by_uri( $uri );
	if ( null === $source || ! axismundi_op_object_card_publicly_renderable( $source ) ) {
		return null;
	}
	$model = axismundi_op_object_view_model( $source );
	if ( ! is_array( $model ) || 'tombstone' === (string) ( $model['status'] ?? '' ) ) {
		return null;
	}
	return array(
		'uri'    => (string) ( $model['object_uri'] ?? $model['id'] ?? $uri ),
		'url'    => (string) ( $model['human_url'] ?? '' ),
		'type'   => (string) ( $model['type'] ?? 'Note' ),
		'title'  => trim( (string) ( $model['title'] ?? $model['name'] ?? '' ) ),
		'author' => axismundi_op_quote_context_author( $model ),
	);
}

/**
 * Build the `quote_context` projection for one full Object View Model, or null
 * when the Object carries no quote relation at all.
 *
 * @param array<string,mixed> $model View model of the quoting (source) Object.
 * @return array<string,mixed>|null
 */
function axismundi_op_build_quote_context( array $model ) : ?array {
	$target_uri = (string) ( $model['quote_uri'] ?? '' );
	if ( '' === $target_uri ) {
		return null;
	}
	$source_uri = (string) ( $model['object_uri'] ?? $model['id'] ?? '' );
	$visited    = array_filter( array( $source_uri ) );

	// A quote pointing back at its own quoting Object (A quotes A) is a cycle at
	// depth 1 already, so it never even reaches state normalization.
	if ( in_array( $target_uri, $visited, true ) ) {
		return array(
			'target_uri'    => $target_uri,
			'display_state' => 'unavailable',
			'reason'        => 'cycle',
			'target'        => null,
			'nested'        => null,
		);
	}

	/** @param string|null $state @param array<string,mixed> $model */
	$raw_state = apply_filters( 'axismundi_op_quote_state_for_object', null, $model );
	$state     = axismundi_op_normalize_quote_display_state( is_string( $raw_state ) ? $raw_state : null );

	$context = array(
		'target_uri'    => $target_uri,
		'display_state' => $state['display_state'],
		'reason'        => $state['reason'],
		'target'        => null,
		'nested'        => null,
	);

	// Pending and unavailable quotes show a status placeholder, never the target's
	// own content -- so the target is never even resolved for those states.
	if ( 'embed' !== $context['display_state'] ) {
		return $context;
	}

	$target_source = axismundi_op_resolve_source_by_uri( $target_uri );
	if ( null === $target_source || ! axismundi_op_object_card_publicly_renderable( $target_source ) ) {
		$context['display_state'] = 'unavailable';
		$context['reason']        = 'unavailable';
		return $context;
	}
	$target_model = axismundi_op_object_view_model( $target_source );
	if ( ! is_array( $target_model ) || 'tombstone' === (string) ( $target_model['status'] ?? '' ) ) {
		$context['display_state'] = 'unavailable';
		$context['reason']        = 'tombstone' === (string) ( $target_model['status'] ?? '' ) ? 'tombstone' : 'unavailable';
		return $context;
	}

	$context['target'] = axismundi_op_quote_context_target_projection( $target_model );

	$nested_uri = (string) ( $target_model['quote_uri'] ?? '' );
	if ( '' !== $nested_uri ) {
		$visited[]          = $target_uri;
		$context['nested']  = in_array( $nested_uri, $visited, true )
			? array( 'uri' => $nested_uri, 'cycle' => true )
			: axismundi_op_quote_context_reference( $nested_uri, $visited );
	}

	return $context;
}
