<?php
/**
 * Dynamic attachment media partial: video.
 *
 * @package Omphalos
 *
 * @var int $attachment_id Current attachment ID.
 */

defined( 'ABSPATH' ) || exit;

$url        = wp_get_attachment_url( $attachment_id );
$metadata   = wp_get_attachment_metadata( $attachment_id );
$tracks     = omphalos_get_video_tracks( $attachment_id );
$track_html = omphalos_render_video_tracks( $tracks );
$block_attrs = array(
	'id'  => (int) $attachment_id,
	'src' => esc_url_raw( (string) $url ),
);
if ( $tracks ) {
	$block_attrs['tracks'] = $tracks;
}
$video_html = $url ? do_blocks(
	sprintf(
		'<!-- wp:video %1$s --><figure class="wp-block-video"><video controls src="%2$s">%3$s</video></figure><!-- /wp:video -->',
		wp_json_encode( $block_attrs ),
		esc_url( $url ),
		$track_html
	)
) : '';

$meta_items = array_merge( omphalos_get_attachment_common_meta( $attachment_id ), array(
	__( 'Length', 'omphalos' )  => $metadata['length_formatted'] ?? '',
	__( 'Dimensions', 'omphalos' ) => ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) )
		? sprintf(
			/* translators: 1: width in pixels, 2: height in pixels. */
			__( '%1$d x %2$d px', 'omphalos' ),
			(int) $metadata['width'],
			(int) $metadata['height']
		)
		: '',
	__( 'Format', 'omphalos' )  => $metadata['fileformat'] ?? '',
	__( 'Data format', 'omphalos' ) => $metadata['dataformat'] ?? '',
	__( 'Bitrate', 'omphalos' ) => ! empty( $metadata['bitrate'] ) ? size_format( (int) ( $metadata['bitrate'] / 8 ) ) . '/s' : '',
	__( 'Text tracks', 'omphalos' ) => $tracks ? count( $tracks ) : '',
) );

$raw_meta = array(
	__( 'WP attachment metadata', 'omphalos' ) => $metadata,
	__( 'Attachment custom fields', 'omphalos' ) => get_post_meta( $attachment_id ),
);

?>
<div class="ax-attachment-media ax-attachment-media--video">
	<?php echo $video_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php omphalos_render_attachment_meta( $meta_items, $raw_meta ); ?>
</div>
