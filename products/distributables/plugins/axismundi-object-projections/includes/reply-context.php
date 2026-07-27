<?php
/**
 * `reply_context` — the direct conversational parent of one Object.
 *
 * A deliberately shallow projection: exactly one ancestor (the `inReplyTo` parent),
 * never the thread. Surfaces that carry it — the media dialog's side panel above all —
 * exist to show media in context, and a full ancestor chain would crowd out the thing
 * the reader opened. The whole conversation stays one click away on the parent Object's
 * own page, which is what the `url` here is for.
 *
 * This reuses the quote-context primitives rather than growing a second set: the same
 * resolver, the same public-renderability gate, and the same compact projection, whose
 * excerpt is already blanked for a sensitive parent so a content warning is never
 * leaked past. Only the relation differs — `inReplyTo` instead of `quote`.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build the `reply_context` projection for one Object View Model.
 *
 * Returns null when the Object is not a reply at all. When it is a reply whose parent
 * cannot be shown — unresolved, non-public, or deleted — the projection still resolves
 * with `available => false` and keeps the parent URI, because "this is a reply and here
 * is where it points" remains true and useful even when the body may not be displayed.
 *
 * @param array<string,mixed> $model View model of the replying Object.
 * @return array<string,mixed>|null
 */
function axismundi_op_build_reply_context( array $model ) : ?array {
	$parent_uri = trim( (string) ( $model['in_reply_to'] ?? '' ) );
	if ( '' === $parent_uri ) {
		return null;
	}
	$self_uri = (string) ( $model['object_uri'] ?? $model['id'] ?? '' );
	if ( '' !== $self_uri && $parent_uri === $self_uri ) {
		// A self-referencing inReplyTo would render an Object as its own ancestor.
		return null;
	}

	$unavailable = array(
		'uri'       => $parent_uri,
		// A canonical Object URI is dereferenceable, so it is a usable destination even
		// with nothing cached locally. A non-HTTP identifier gets no link at all.
		'url'       => in_array( strtolower( (string) wp_parse_url( $parent_uri, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ? $parent_uri : '',
		'available' => false,
	);

	if ( ! function_exists( 'axismundi_op_resolve_source_by_uri' ) ) {
		return $unavailable;
	}
	$source = axismundi_op_resolve_source_by_uri( $parent_uri );
	if ( null === $source
		|| ( function_exists( 'axismundi_op_object_card_publicly_renderable' ) && ! axismundi_op_object_card_publicly_renderable( $source ) )
	) {
		return $unavailable;
	}

	// The parent is projected without enrichment: this surface needs its identity and a
	// short excerpt, not its own hashtags, mentions, quote target, or reply parent.
	$parent = axismundi_op_object_view_model( $source );
	if ( ! is_array( $parent ) || 'tombstone' === (string) ( $parent['status'] ?? '' ) ) {
		return $unavailable;
	}

	$projection              = axismundi_op_quote_context_target_projection( $parent );
	$projection['available'] = true;
	if ( '' === (string) $projection['uri'] ) {
		$projection['uri'] = $parent_uri;
	}
	if ( '' === (string) $projection['url'] ) {
		$projection['url'] = $unavailable['url'];
	}
	return $projection;
}
