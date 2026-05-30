<?php
/**
 * Dynamic attachment media partial: fallback file.
 *
 * @package Omphalos
 *
 * @var int $attachment_id Current attachment ID.
 */

defined( 'ABSPATH' ) || exit;

$url        = wp_get_attachment_url( $attachment_id );
$file_path  = get_attached_file( $attachment_id );
$mime_type  = get_post_mime_type( $attachment_id );
$file_title = get_the_title( $attachment_id );
$file_html  = $url ? do_blocks(
	sprintf(
		'<!-- wp:file {"id":%1$d,"href":"%2$s"} --><div class="wp-block-file"><a href="%2$s">%3$s</a><a href="%2$s" class="wp-block-file__button wp-element-button" download>%4$s</a></div><!-- /wp:file -->',
		(int) $attachment_id,
		esc_url( $url ),
		esc_html( $file_title ),
		esc_html__( 'Download', 'omphalos' )
	)
) : '';

$meta_items = omphalos_get_attachment_common_meta( $attachment_id );
$raw_meta   = array(
	__( 'WP attachment metadata', 'omphalos' ) => wp_get_attachment_metadata( $attachment_id ),
	__( 'Attachment custom fields', 'omphalos' ) => get_post_meta( $attachment_id ),
);

?>
<div class="ax-attachment-media ax-attachment-media--file">
	<?php echo $file_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php omphalos_render_attachment_meta( $meta_items, $raw_meta ); ?>
</div>
