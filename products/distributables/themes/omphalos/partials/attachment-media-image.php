<?php
/**
 * Dynamic attachment media partial: image.
 *
 * @package Omphalos
 *
 * @var int $attachment_id Current attachment ID.
 */

defined( 'ABSPATH' ) || exit;

$mime_type = get_post_mime_type( $attachment_id );
$url       = wp_get_attachment_url( $attachment_id );
$alt       = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
$metadata  = wp_get_attachment_metadata( $attachment_id );

if ( 'image/svg+xml' === $mime_type && $url ) {
	$media_html = sprintf(
		'<figure class="wp-block-image size-large ax-attachment-media__figure"><img src="%1$s" alt="%2$s" loading="lazy" decoding="async" /></figure>',
		esc_url( $url ),
		esc_attr( $alt )
	);
} else {
	$image = wp_get_attachment_image(
		$attachment_id,
		'large',
		false,
		array(
			'class' => 'ax-attachment-media__image',
		)
	);

	$media_html = $image ? sprintf(
		'<figure class="wp-block-image size-large ax-attachment-media__figure">%s</figure>',
		$image
	) : '';
}

$meta_items = omphalos_get_attachment_common_meta( $attachment_id );

if ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
	$meta_items[ __( 'Dimensions', 'omphalos' ) ] = sprintf(
		/* translators: 1: width in pixels, 2: height in pixels. */
		__( '%1$d x %2$d px', 'omphalos' ),
		(int) $metadata['width'],
		(int) $metadata['height']
	);
}

if ( ! empty( $metadata['image_meta'] ) && is_array( $metadata['image_meta'] ) ) {
	$image_meta = $metadata['image_meta'];
	$meta_items[ __( 'Camera', 'omphalos' ) ]         = $image_meta['camera'] ?? '';
	$meta_items[ __( 'Aperture', 'omphalos' ) ]       = ! empty( $image_meta['aperture'] ) ? 'f/' . $image_meta['aperture'] : '';
	$meta_items[ __( 'Focal length', 'omphalos' ) ]   = ! empty( $image_meta['focal_length'] ) ? $image_meta['focal_length'] . ' mm' : '';
	$meta_items[ __( 'ISO', 'omphalos' ) ]            = $image_meta['iso'] ?? '';
	$meta_items[ __( 'Shutter speed', 'omphalos' ) ]  = $image_meta['shutter_speed'] ?? '';
	$meta_items[ __( 'Copyright', 'omphalos' ) ]      = $image_meta['copyright'] ?? '';

	if ( ! empty( $image_meta['created_timestamp'] ) ) {
		$meta_items[ __( 'Created', 'omphalos' ) ] = wp_date(
			get_option( 'date_format' ),
			(int) $image_meta['created_timestamp']
		);
	}
}

$raw_meta = array(
	__( 'WP attachment metadata', 'omphalos' ) => $metadata,
	__( 'Attachment custom fields', 'omphalos' ) => get_post_meta( $attachment_id ),
);

?>
<div class="ax-attachment-media ax-attachment-media--image">
	<?php echo $media_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php omphalos_render_attachment_meta( $meta_items, $raw_meta ); ?>
</div>
