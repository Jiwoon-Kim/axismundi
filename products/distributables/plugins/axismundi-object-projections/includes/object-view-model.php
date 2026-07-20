<?php
/**
 * Neutral single-object view-model registry and the axismundi/object-view block.
 *
 * A product (Note now; remote cache later) registers an adapter that normalizes
 * its own source into one view model. The dynamic `axismundi/object-view` block
 * renders the request's current view model inside a block template, so the human
 * HTML page never depends on a Core `post-content` block or a faked singular
 * query — a Tombstone has no `WP_Post` and must still render.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** @var array<string,array<string,mixed>> View-model adapters, by stable id. */
$GLOBALS['axismundi_op_view_model_adapters'] = array();

/**
 * Register one source-to-view-model adapter.
 *
 * OP never reads product storage; the adapter's supports and transform callbacks
 * do. Definitions follow the transformer registry's deterministic id/priority
 * contract so plugin load order never decides which product owns a source.
 *
 * @param string              $id   Stable lowercase slug.
 * @param array<string,mixed> $args supports, transform, [priority].
 * @return true|WP_Error
 */
function axismundi_op_register_object_view_model( string $id, array $args ) {
	$key = sanitize_key( $id );
	if ( '' === $key || $key !== $id ) {
		return new WP_Error( 'ax_op_view_model_id', __( 'View-model adapter IDs must be non-empty lowercase slugs.', 'axismundi-object-projections' ) );
	}
	if ( ! is_callable( $args['supports'] ?? null ) || ! is_callable( $args['transform'] ?? null ) ) {
		return new WP_Error( 'ax_op_view_model_args', __( 'A view-model adapter requires callable supports and transform callbacks.', 'axismundi-object-projections' ) );
	}
	return axismundi_op_store_transformer(
		'axismundi_op_view_model_adapters',
		array(
			'id'        => $key,
			'supports'  => $args['supports'],
			'transform' => $args['transform'],
			'priority'  => isset( $args['priority'] ) ? (int) $args['priority'] : 10,
		)
	);
}

/**
 * Resolve one source into its normalized view model, or null.
 *
 * @return array<string,mixed>|null
 */
function axismundi_op_object_view_model( $source ) : ?array {
	foreach ( axismundi_op_sort_registry( (array) $GLOBALS['axismundi_op_view_model_adapters'] ) as $adapter ) {
		try {
			if ( true !== call_user_func( $adapter['supports'], $source ) ) {
				continue;
			}
			$model = call_user_func( $adapter['transform'], $source );
			if ( is_array( $model ) ) {
				return $model;
			}
		} catch ( Throwable $error ) {
			continue;
		}
	}
	return null;
}

/** Set the view model the object-view block renders for this request. */
function axismundi_op_set_current_object_view_model( ?array $model ) : void {
	$GLOBALS['axismundi_op_current_view_model'] = $model;
}

/** The view model bound to this request, or null. */
function axismundi_op_current_object_view_model() : ?array {
	$model = $GLOBALS['axismundi_op_current_view_model'] ?? null;
	return is_array( $model ) ? $model : null;
}

/** Render one author byline from a view model. */
function axismundi_op_object_view_author( array $model ) : string {
	$author = isset( $model['author'] ) && is_array( $model['author'] ) ? $model['author'] : array();
	$name   = (string) ( $author['name'] ?? '' );
	if ( '' === $name ) {
		return '';
	}
	$handle = (string) ( $author['handle'] ?? '' );
	$url    = (string) ( $author['url'] ?? '' );
	$label  = '' !== $handle
		? esc_html( $name ) . ' <span class="axismundi-object__handle">' . esc_html( $handle ) . '</span>'
		: esc_html( $name );
	$inner  = '' !== $url ? '<a href="' . esc_url( $url ) . '" rel="author">' . $label . '</a>' : $label;
	return '<p class="axismundi-object__author">' . $inner . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above.
}

/** Render one already-sanitized local media descriptor without guessing storage. */
function axismundi_op_object_view_attachment( array $descriptor ) : string {
	$urls = $descriptor['url'] ?? array();
	$urls = is_array( $urls ) && array_is_list( $urls ) ? $urls : array( $urls );
	$link = null;
	foreach ( $urls as $candidate ) {
		if ( is_array( $candidate ) && ! empty( $candidate['href'] ) ) {
			$link = $candidate;
			break;
		}
	}
	if ( ! is_array( $link ) ) {
		return '';
	}
	$href = esc_url( (string) $link['href'] );
	if ( '' === $href ) {
		return '';
	}
	$name       = trim( (string) ( $descriptor['name'] ?? '' ) );
	$media_type = strtolower( (string) ( $link['mediaType'] ?? $descriptor['mediaType'] ?? '' ) );
	$type       = strtolower( (string) ( $descriptor['type'] ?? 'document' ) );
	if ( 'image' === $type && str_starts_with( $media_type, 'image/' ) ) {
		$body = '<figure class="axismundi-object__attachment axismundi-object__attachment--image"><img src="' . $href . '" alt="' . esc_attr( $name ) . '" loading="lazy" />';
		if ( '' !== $name ) {
			$body .= '<figcaption>' . esc_html( $name ) . '</figcaption>';
		}
		$body .= '</figure>';
	} else {
		$label = '' !== $name ? $name : __( 'Open attachment', 'axismundi-object-projections' );
		$body  = '<p class="axismundi-object__attachment axismundi-object__attachment--file"><a href="' . $href . '">' . esc_html( $label ) . '</a></p>';
	}
	if ( ! empty( $descriptor['sensitive'] ) ) {
		$warning = trim( (string) ( $descriptor['summary'] ?? '' ) );
		$summary = '' !== $warning ? esc_html( $warning ) : esc_html__( 'Sensitive media', 'axismundi-object-projections' );
		return '<details class="axismundi-object__attachment-warning"><summary>' . $summary . '</summary>' . $body . '</details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Body escaped above.
	}
	return $body;
}

/**
 * Render the request's current object view model.
 *
 * A Tombstone renders a minimal deleted notice with no author, content, or
 * interaction affordances. An active object renders its content with an optional
 * content-warning wrapper and an interactions slot products may fill.
 */
function axismundi_op_render_object_view_block( array $attributes = array(), string $content = '' ) : string {
	unset( $content );
	$model = axismundi_op_current_object_view_model();
	if ( null === $model ) {
		return '';
	}
	$heading_tag          = isset( $attributes['headingTag'] ) && in_array( $attributes['headingTag'], array( 'h1', 'h2', 'h3', 'h4' ), true ) ? $attributes['headingTag'] : 'h1';
	$interactions_enabled = ! isset( $attributes['interactions'] ) || (bool) $attributes['interactions'];

	if ( 'tombstone' === (string) ( $model['status'] ?? '' ) ) {
		return '<div class="axismundi-object axismundi-object--tombstone">'
			. '<p class="axismundi-object__deleted">' . esc_html__( 'This object has been deleted.', 'axismundi-object-projections' ) . '</p>'
			. '</div>';
	}

	$parts   = array();
	$title   = trim( (string) ( $model['title'] ?? '' ) );
	if ( '' !== $title ) {
		$parts[] = '<' . $heading_tag . ' class="axismundi-object__title">' . esc_html( $title ) . '</' . $heading_tag . '>';
	}
	$parts[] = axismundi_op_object_view_author( $model );

	$published = (string) ( $model['published'] ?? '' );
	if ( '' !== $published ) {
		$timestamp = strtotime( $published );
		if ( false !== $timestamp ) {
			$parts[] = '<p class="axismundi-object__published"><time datetime="' . esc_attr( gmdate( 'c', $timestamp ) ) . '">'
				. esc_html( wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $timestamp ) )
				. '</time></p>';
		}
	}

	$body = wp_kses_post( (string) ( $model['content_html'] ?? '' ) );
	if ( ! empty( $model['sensitive'] ) ) {
		$warning = (string) ( $model['content_warning'] ?? '' );
		$summary = '' !== trim( $warning ) ? esc_html( $warning ) : esc_html__( 'Sensitive content', 'axismundi-object-projections' );
		$parts[] = '<details class="axismundi-object__sensitive"><summary>' . $summary . '</summary>'
			. '<div class="axismundi-object__content">' . $body . '</div></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Body is wp_kses_post rendered by the adapter.
	} else {
		$parts[] = '<div class="axismundi-object__content">' . $body . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Body is wp_kses_post rendered by the adapter.
	}

	$attachment_html = array();
	foreach ( (array) ( $model['attachments'] ?? array() ) as $descriptor ) {
		if ( is_array( $descriptor ) ) {
			$rendered = axismundi_op_object_view_attachment( $descriptor );
			if ( '' !== $rendered ) {
				$attachment_html[] = $rendered;
			}
		}
	}
	if ( $attachment_html ) {
		$parts[] = '<div class="axismundi-object__attachments">' . implode( '', $attachment_html ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Children escaped by renderer.
	}

	/**
	 * Filter interaction affordances (Like / Announce) for one active object view.
	 *
	 * Interactions are never rendered for a Tombstone. The canonical object URI is
	 * passed so a consumer can bind actions without a personalized shared cache.
	 *
	 * @param string               $html  Interaction markup (empty by default).
	 * @param array<string,mixed>  $model The active object view model.
	 */
	$interactions = $interactions_enabled ? (string) apply_filters( 'axismundi_op_object_view_interactions', '', $model ) : '';
	if ( '' !== $interactions ) {
		$parts[] = '<div class="axismundi-object__interactions">' . $interactions . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Interaction consumer owns escaping.
	}

	$type_class = sanitize_html_class( strtolower( (string) ( $model['type'] ?? 'object' ) ), 'object' );
	return '<article class="axismundi-object axismundi-object--' . esc_attr( $type_class ) . '">'
		. implode( '', array_filter( $parts ) )
		. '</article>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above.
}

/** Register the server-rendered object-view block (no editor script). */
function axismundi_op_register_object_view_block() : void {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	register_block_type(
		'axismundi/object-view',
		array(
			'api_version'     => 3,
			'title'           => __( 'Axismundi Object', 'axismundi-object-projections' ),
			'category'        => 'theme',
			'render_callback' => 'axismundi_op_render_object_view_block',
			'supports'        => array( 'html' => false, 'inserter' => false ),
		)
	);
}
add_action( 'init', 'axismundi_op_register_object_view_block' );
