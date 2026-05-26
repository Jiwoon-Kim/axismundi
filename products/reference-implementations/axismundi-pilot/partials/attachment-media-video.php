<?php
/**
 * Dynamic attachment media partial: video.
 *
 * @package Axismundi_Pilot
 *
 * @var int $attachment_id Current attachment ID.
 */

defined( 'ABSPATH' ) || exit;

$url        = wp_get_attachment_url( $attachment_id );
$metadata   = wp_get_attachment_metadata( $attachment_id );
$tracks     = axismundi_pilot_get_video_tracks( $attachment_id );
$track_html = axismundi_pilot_render_video_tracks( $tracks );
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

$meta_items = array_merge( axismundi_pilot_get_attachment_common_meta( $attachment_id ), array(
	__( 'Length', 'axismundi-pilot' )  => $metadata['length_formatted'] ?? '',
	__( 'Dimensions', 'axismundi-pilot' ) => ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) )
		? sprintf(
			/* translators: 1: width in pixels, 2: height in pixels. */
			__( '%1$d x %2$d px', 'axismundi-pilot' ),
			(int) $metadata['width'],
			(int) $metadata['height']
		)
		: '',
	__( 'Format', 'axismundi-pilot' )  => $metadata['fileformat'] ?? '',
	__( 'Data format', 'axismundi-pilot' ) => $metadata['dataformat'] ?? '',
	__( 'Bitrate', 'axismundi-pilot' ) => ! empty( $metadata['bitrate'] ) ? size_format( (int) ( $metadata['bitrate'] / 8 ) ) . '/s' : '',
	__( 'Text tracks', 'axismundi-pilot' ) => $tracks ? count( $tracks ) : '',
) );

$raw_meta = array(
	__( 'WP attachment metadata', 'axismundi-pilot' ) => $metadata,
	__( 'Attachment custom fields', 'axismundi-pilot' ) => get_post_meta( $attachment_id ),
);

?>
<div class="ax-attachment-media ax-attachment-media--video">
	<?php echo $video_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php axismundi_pilot_render_attachment_meta( $meta_items, $raw_meta ); ?>
</div>
