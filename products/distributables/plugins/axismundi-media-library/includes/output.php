<?php
/**
 * Phase 4c — output integration.
 *
 * Sensitive media gets a front-end click-to-reveal blur overlay on the visual
 * core blocks that render an attachment: core/image, core/video, and
 * core/post-featured-image. Scope is deliberate:
 * - Audio has no visual surface — skipped.
 * - This is a viewer content warning, NOT access control: the file is never
 *   altered or withheld, and (per product decision) the blur applies to everyone
 *   including the owner — a content warning is a viewer choice, not a permission.
 * - Open Graph / oEmbed preview exclusion and post-level flagging are out of
 *   scope (embed-template / not-a-media-plugin territory).
 * Gated to Independent mode so Core mode leaves WordPress output untouched.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/**
 * The content-warning text for a sensitive attachment: the authored warning, else
 * the sensitivity reason, else a generic default.
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function axismundi_media_content_warning_text( int $attachment_id ) : string {
	$warning = (string) get_post_meta( $attachment_id, '_ax_media_content_warning', true );
	if ( '' === $warning ) {
		$warning = (string) get_post_meta( $attachment_id, '_ax_media_sensitivity_reason', true );
	}
	if ( '' === trim( $warning ) ) {
		$warning = __( 'Sensitive content', 'axismundi-media-library' );
	}
	return $warning;
}

/**
 * Wrap already-rendered block HTML in the sensitive reveal overlay.
 *
 * @param string $inner_html    Rendered block HTML (already escaped by core).
 * @param int    $attachment_id Attachment ID.
 * @return string
 */
function axismundi_media_sensitive_overlay( string $inner_html, int $attachment_id ) : string {
	return axismundi_media_sensitive_overlay_with_warning( $inner_html, axismundi_media_content_warning_text( $attachment_id ) );
}

/**
 * The same reveal overlay for media whose warning is not the attachment's own.
 *
 * An Object's lead image inherits the Object's sensitivity rather than carrying a
 * second, separately-answerable flag, so its warning text comes from the Object.
 * The markup, stylesheet, and reveal script stay shared: a viewer should not meet
 * two different content warnings on one page.
 *
 * @param string $inner_html Rendered media HTML (already escaped by its renderer).
 * @param string $warning    Content-warning text; empty falls back to the generic one.
 * @return string
 */
function axismundi_media_sensitive_overlay_with_warning( string $inner_html, string $warning ) : string {
	if ( '' === trim( $warning ) ) {
		$warning = __( 'Sensitive content', 'axismundi-media-library' );
	}
	// The reveal control is a button, not a link: it performs an action and navigates
	// nowhere, and an `href="#"` scrolls the reader to the top of the page on click.
	// Federated content drops it entirely (the FEP-b2b8 allowlist admits no `button`),
	// which is correct — a remote client cannot drive our reveal script and renders its
	// own content-warning affordance. The warning text and blur classes still survive.
	$overlay = '<div class="ax-media-sensitive__overlay"><p class="ax-media-sensitive__warning">' . esc_html( $warning ) . '</p>'
		. '<button type="button" class="ax-media-sensitive__reveal">' . esc_html__( 'Show', 'axismundi-media-library' ) . '</button></div>';

	// Core Image renders a figure. Decorate that existing gallery item rather than
	// wrapping it: Core Gallery owns direct-child geometry and a wrapper makes a
	// sensitive item fall out of its grid. The fallback keeps video/third-party
	// block output covered when it does not have a figure root.
	if ( preg_match( '/^(\s*<figure\b)([^>]*)(>.*)(<\/figure>\s*)$/si', $inner_html, $matches ) ) {
		$attributes = (string) $matches[2];
		if ( preg_match( '/\bclass=("|\')(.*?)\1/i', $attributes, $class_match ) ) {
			$replacement = 'class=' . $class_match[1] . trim( $class_match[2] . ' ax-media-sensitive is-hidden' ) . $class_match[1];
			$attributes   = str_replace( $class_match[0], $replacement, $attributes );
		} else {
			$attributes .= ' class="ax-media-sensitive is-hidden"';
		}
		return $matches[1] . $attributes . $matches[3] . $overlay . $matches[4];
	}

	return '<div class="ax-media-sensitive is-hidden"><div class="ax-media-sensitive__content">' . $inner_html . '</div>' . $overlay . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered block HTML is already escaped by Core.
}

/**
 * The attachment a visual block renders, or 0 when it is not one we blur.
 *
 * @param array         $block    Parsed block.
 * @param WP_Block|null $instance Block instance (for post context).
 * @return int
 */
function axismundi_media_block_attachment_id( array $block, $instance ) : int {
	$name = (string) ( $block['blockName'] ?? '' );
	if ( 'core/image' === $name || 'core/video' === $name ) {
		return (int) ( $block['attrs']['id'] ?? 0 );
	}
	if ( 'core/post-featured-image' === $name ) {
		$post_id = ( $instance instanceof WP_Block && isset( $instance->context['postId'] ) )
			? (int) $instance->context['postId']
			: (int) get_the_ID();
		return $post_id > 0 ? (int) get_post_thumbnail_id( $post_id ) : 0;
	}
	return 0;
}

/** Whether a core Image carries an additional, post-local content warning. */
function axismundi_media_block_has_sensitive_override( array $block ) : bool {
	return 'core/image' === (string) ( $block['blockName'] ?? '' )
		&& ! empty( $block['attrs']['axismundiSensitive'] );
}

/**
 * Return the effective warning for one rendered visual block.
 *
 * Attachment sensitivity is authoritative. A post-local image override has no
 * attachment-owned warning text, so it deliberately uses the neutral default.
 */
function axismundi_media_block_content_warning( array $block, int $attachment_id ) : string {
	if ( $attachment_id > 0 && axismundi_media_is_sensitive( $attachment_id ) ) {
		return axismundi_media_content_warning_text( $attachment_id );
	}
	return __( 'Sensitive content', 'axismundi-media-library' );
}

/**
 * Blur sensitive media on the front end. Editor / REST block-renderer previews are
 * left untouched so authors always see the real media while editing.
 *
 * @param string        $block_content Rendered HTML.
 * @param array         $block         Parsed block.
 * @param WP_Block|null $instance      Block instance.
 * @return string
 */
function axismundi_media_render_sensitive_block( string $block_content, array $block, $instance = null ) : string {
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $block_content;
	}
	if ( '' === trim( $block_content ) || ! axismundi_media_is_independent() ) {
		return $block_content;
	}
	$attachment_id        = axismundi_media_block_attachment_id( $block, $instance );
	$attachment_sensitive = $attachment_id > 0 && axismundi_media_is_sensitive( $attachment_id );
	if ( ! $attachment_sensitive && ! axismundi_media_block_has_sensitive_override( $block ) ) {
		return $block_content;
	}
	return axismundi_media_sensitive_overlay_with_warning(
		$block_content,
		axismundi_media_block_content_warning( $block, $attachment_id )
	);
}
add_filter( 'render_block', 'axismundi_media_render_sensitive_block', 15, 3 );

/** Expose the effective attachment sensitivity to the Core editor media store. */
function axismundi_media_register_sensitivity_rest_field() : void {
	register_rest_field(
		'attachment',
		'axismundiSensitive',
		array(
			'get_callback' => static function ( array $attachment ) : bool {
				return axismundi_media_is_sensitive( (int) ( $attachment['id'] ?? 0 ) );
			},
			'schema'       => array(
				'description' => __( 'Whether this attachment is marked sensitive by Media Library.', 'axismundi-media-library' ),
				'type'        => 'boolean',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_media_register_sensitivity_rest_field' );

/** Add the post-local sensitivity control to Core Image. */
function axismundi_media_enqueue_sensitive_image_editor() : void {
	if ( ! axismundi_media_is_independent() ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! $screen->is_block_editor() ) {
		return;
	}
	$base = dirname( __DIR__ ) . '/axismundi-media-library.php';
	$js   = dirname( __DIR__ ) . '/assets/sensitive-image-editor.js';
	wp_enqueue_script(
		'axismundi-media-sensitive-image-editor',
		plugins_url( 'assets/sensitive-image-editor.js', $base ),
		array( 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-data', 'wp-element', 'wp-hooks', 'wp-i18n' ),
		file_exists( $js ) ? (string) filemtime( $js ) : false,
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'axismundi_media_enqueue_sensitive_image_editor' );

/**
 * Enqueue the reveal overlay assets on the front end in Independent mode.
 *
 * @return void
 */
function axismundi_media_sensitive_assets() : void {
	if ( is_admin() || ! axismundi_media_is_independent() ) {
		return;
	}
	$base = dirname( __DIR__ ) . '/axismundi-media-library.php';
	$css  = dirname( __DIR__ ) . '/assets/sensitive.css';
	$js   = dirname( __DIR__ ) . '/assets/sensitive.js';
	wp_enqueue_style(
		'axismundi-media-sensitive',
		plugins_url( 'assets/sensitive.css', $base ),
		array(),
		file_exists( $css ) ? (string) filemtime( $css ) : false
	);
	wp_enqueue_script(
		'axismundi-media-sensitive',
		plugins_url( 'assets/sensitive.js', $base ),
		array(),
		file_exists( $js ) ? (string) filemtime( $js ) : false,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'axismundi_media_sensitive_assets' );
