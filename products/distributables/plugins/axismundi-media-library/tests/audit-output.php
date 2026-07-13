<?php
/**
 * Phase 4c — sensitive output integration (dev-only; dist-excluded).
 *
 * Self-contained; `finally` cleanup; exit 0/1. Locks: content-warning resolution
 * (authored -> reason -> default), attachment resolution per visual block
 * (image/video via attrs.id, featured via thumbnail, unknown -> 0), that only a
 * sensitive attachment is wrapped, empty/unknown blocks pass through, and the
 * warning text is escaped.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_results = array();

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_out_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

$ax_created = array( 'atts' => array(), 'posts' => array() );
$ax_prev_mode = get_option( 'ax_media_relationship_mode' );

try {
	update_option( 'ax_media_relationship_mode', 'independent' );
	ax_out_assert( $ax_results, 'independent mode active for the render path', axismundi_media_is_independent() );

	$att = (int) wp_insert_attachment( array( 'post_title' => 'Pic', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg' ) );
	$ax_created['atts'][] = $att;

	// Warning resolution.
	update_post_meta( $att, '_ax_media_content_warning', 'Graphic injury' );
	ax_out_assert( $ax_results, 'authored content warning wins', 'Graphic injury' === axismundi_media_content_warning_text( $att ) );
	delete_post_meta( $att, '_ax_media_content_warning' );
	update_post_meta( $att, '_ax_media_sensitivity_reason', 'violence' );
	ax_out_assert( $ax_results, 'sensitivity reason is the fallback', 'violence' === axismundi_media_content_warning_text( $att ) );
	delete_post_meta( $att, '_ax_media_sensitivity_reason' );
	ax_out_assert( $ax_results, 'generic default when nothing authored', 'Sensitive content' === axismundi_media_content_warning_text( $att ) );

	// Attachment resolution per block.
	ax_out_assert( $ax_results, 'core/image resolves attrs.id', $att === axismundi_media_block_attachment_id( array( 'blockName' => 'core/image', 'attrs' => array( 'id' => $att ) ), null ) );
	ax_out_assert( $ax_results, 'core/video resolves attrs.id', $att === axismundi_media_block_attachment_id( array( 'blockName' => 'core/video', 'attrs' => array( 'id' => $att ) ), null ) );
	ax_out_assert( $ax_results, 'unrelated block resolves to 0', 0 === axismundi_media_block_attachment_id( array( 'blockName' => 'core/paragraph', 'attrs' => array() ), null ) );

	// Featured image via the current post's thumbnail.
	$post_id = (int) wp_insert_post( array( 'post_title' => 'Host', 'post_status' => 'publish', 'post_type' => 'post' ) );
	$ax_created['posts'][] = $post_id;
	update_post_meta( $post_id, '_thumbnail_id', $att );
	$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- CLI fixture context for get_the_ID().
	setup_postdata( $GLOBALS['post'] );
	ax_out_assert( $ax_results, 'core/post-featured-image resolves the thumbnail', $att === axismundi_media_block_attachment_id( array( 'blockName' => 'core/post-featured-image', 'attrs' => array() ), null ) );
	wp_reset_postdata();

	// Render gating: non-sensitive passes through; sensitive is wrapped.
	$img_block = array( 'blockName' => 'core/image', 'attrs' => array( 'id' => $att ) );
	$html      = '<figure class="wp-block-image"><img src="x.jpg" alt=""/></figure>';
	ax_out_assert( $ax_results, 'non-sensitive image is left untouched', $html === axismundi_media_render_sensitive_block( $html, $img_block, null ) );

	update_post_meta( $att, '_ax_media_sensitive', '1' );
	$wrapped = axismundi_media_render_sensitive_block( $html, $img_block, null );
	ax_out_assert( $ax_results, 'sensitive image is wrapped, original preserved', false !== strpos( $wrapped, 'ax-media-sensitive is-hidden' ) && false !== strpos( $wrapped, $html ) && false !== strpos( $wrapped, 'ax-media-sensitive__reveal' ) );

	ax_out_assert( $ax_results, 'empty content is never wrapped', '' === axismundi_media_render_sensitive_block( '', $img_block, null ) );
	ax_out_assert( $ax_results, 'unknown block is never wrapped', 'x' === axismundi_media_render_sensitive_block( 'x', array( 'blockName' => 'core/paragraph', 'attrs' => array() ), null ) );

	// Warning text is escaped in the overlay.
	update_post_meta( $att, '_ax_media_content_warning', '<script>alert(1)</script>' );
	$xss = axismundi_media_render_sensitive_block( $html, $img_block, null );
	ax_out_assert( $ax_results, 'warning text is escaped (no raw script tag)', false === strpos( $xss, '<script>alert(1)</script>' ) && false !== strpos( $xss, '&lt;script&gt;' ) );

} finally {
	wp_reset_postdata();
	foreach ( $ax_created['posts'] as $ax_p ) {
		if ( $ax_p ) {
			wp_delete_post( (int) $ax_p, true );
		}
	}
	foreach ( $ax_created['atts'] as $ax_a ) {
		if ( $ax_a ) {
			wp_delete_attachment( (int) $ax_a, true );
		}
	}
	if ( false === $ax_prev_mode ) {
		delete_option( 'ax_media_relationship_mode' );
	} else {
		update_option( 'ax_media_relationship_mode', $ax_prev_mode );
	}
}

$ax_fail = 0;
foreach ( $ax_results as $ax_r ) {
	if ( ! $ax_r ) {
		++$ax_fail;
	}
}
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
printf( "\n== %d checks, %d failed ==\n", count( $ax_results ), $ax_fail );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_fail > 0 ? 1 : 0 );
}
exit( $ax_fail > 0 ? 1 : 0 );
