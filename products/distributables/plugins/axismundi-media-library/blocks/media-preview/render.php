<?php
/**
 * Media Preview server render.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

$axismundi_media_preview_id = (int) ( $block->context['postId'] ?? 0 );
if ( ! $axismundi_media_preview_id && isset( $_GET['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor preview context.
	$axismundi_media_preview_id = absint( $_GET['post_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

if ( ! $axismundi_media_preview_id || 'attachment' !== get_post_type( $axismundi_media_preview_id ) ) {
	return;
}

$axismundi_media_preview_size = sanitize_key( (string) ( $attributes['sizeSlug'] ?? 'medium_large' ) );
$axismundi_media_preview_html = wp_get_attachment_image(
	$axismundi_media_preview_id,
	$axismundi_media_preview_size,
	true,
	array( 'loading' => 'lazy' )
);

if ( ! $axismundi_media_preview_html ) {
	return;
}

$axismundi_media_preview_wrapper = get_block_wrapper_attributes(
	array( 'class' => 'ax-media-preview' )
);
$axismundi_media_preview_link = ! isset( $attributes['linkToAttachment'] ) || (bool) $attributes['linkToAttachment'];
$axismundi_media_preview_sensitive = '1' === (string) get_post_meta( $axismundi_media_preview_id, '_ax_media_sensitive', true );
$axismundi_media_preview_warning = trim( (string) get_post_meta( $axismundi_media_preview_id, '_ax_media_content_warning', true ) );
?>
<figure <?php echo $axismundi_media_preview_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $axismundi_media_preview_link ) : ?>
		<a href="<?php echo esc_url( get_attachment_link( $axismundi_media_preview_id ) ); ?>">
	<?php endif; ?>
	<?php echo $axismundi_media_preview_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated attachment image markup. ?>
	<?php if ( $axismundi_media_preview_sensitive ) : ?>
		<span class="ax-media-preview__sensitive"><span aria-hidden="true">!</span><span><?php echo esc_html( $axismundi_media_preview_warning ?: __( 'Sensitive media', 'axismundi-media-library' ) ); ?></span></span>
	<?php endif; ?>
	<?php if ( $axismundi_media_preview_link ) : ?>
		</a>
	<?php endif; ?>
</figure>
