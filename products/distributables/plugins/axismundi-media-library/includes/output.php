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
	$warning = axismundi_media_content_warning_text( $attachment_id );
	return sprintf(
		'<div class="ax-media-sensitive is-hidden"><div class="ax-media-sensitive__content">%1$s</div>'
			. '<div class="ax-media-sensitive__overlay"><p class="ax-media-sensitive__warning">%2$s</p>'
			. '<button type="button" class="ax-media-sensitive__reveal">%3$s</button></div></div>',
		$inner_html,
		esc_html( $warning ),
		esc_html__( 'Show', 'axismundi-media-library' )
	);
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
	$attachment_id = axismundi_media_block_attachment_id( $block, $instance );
	if ( $attachment_id <= 0 || ! axismundi_media_is_sensitive( $attachment_id ) ) {
		return $block_content;
	}
	return axismundi_media_sensitive_overlay( $block_content, $attachment_id );
}
add_filter( 'render_block', 'axismundi_media_render_sensitive_block', 15, 3 );

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
