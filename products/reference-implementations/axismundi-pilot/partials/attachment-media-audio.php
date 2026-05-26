<?php
/**
 * Dynamic attachment media partial: audio.
 *
 * @package Axismundi_Pilot
 *
 * @var int $attachment_id Current attachment ID.
 */

defined( 'ABSPATH' ) || exit;

$url             = wp_get_attachment_url( $attachment_id );
$metadata        = wp_get_attachment_metadata( $attachment_id );
$cover_image_id  = axismundi_pilot_get_audio_cover_attachment_id( $attachment_id );
$cover_image     = $cover_image_id ? wp_get_attachment_image(
	$cover_image_id,
	'large',
	false,
	array(
		'class' => 'ax-attachment-media__cover-image',
	)
) : '';
$audio_block     = $url ? do_blocks(
	sprintf(
		'<!-- wp:audio {"id":%1$d,"src":"%2$s"} --><figure class="wp-block-audio"><audio controls src="%2$s"></audio></figure><!-- /wp:audio -->',
		(int) $attachment_id,
		esc_url( $url )
	)
) : '';

$meta_items = array_merge( axismundi_pilot_get_attachment_common_meta( $attachment_id ), array(
	__( 'Length', 'axismundi-pilot' )      => $metadata['length_formatted'] ?? '',
	__( 'Format', 'axismundi-pilot' )      => $metadata['dataformat'] ?? '',
	__( 'File format', 'axismundi-pilot' ) => $metadata['fileformat'] ?? '',
	__( 'Channel mode', 'axismundi-pilot' ) => $metadata['channelmode'] ?? '',
	__( 'Channels', 'axismundi-pilot' )    => $metadata['channels'] ?? '',
	__( 'Sample rate', 'axismundi-pilot' ) => ! empty( $metadata['sample_rate'] ) ? number_format_i18n( (int) $metadata['sample_rate'] ) . ' Hz' : '',
	__( 'Input sample rate', 'axismundi-pilot' ) => ! empty( $metadata['sample_rate_input'] ) ? number_format_i18n( (int) $metadata['sample_rate_input'] ) . ' Hz' : '',
	__( 'Bitrate', 'axismundi-pilot' )     => ! empty( $metadata['bitrate'] ) ? size_format( (int) ( $metadata['bitrate'] / 8 ) ) . '/s' : '',
	__( 'Bitrate mode', 'axismundi-pilot' ) => $metadata['bitrate_mode'] ?? '',
	__( 'Lossless', 'axismundi-pilot' )    => $metadata['lossless'] ?? null,
	__( 'Compression ratio', 'axismundi-pilot' ) => $metadata['compression_ratio'] ?? '',
	__( 'Encoder', 'axismundi-pilot' )     => $metadata['encoder'] ?? '',
	__( 'Encoder settings', 'axismundi-pilot' ) => $metadata['encoder_settings'] ?? '',
	__( 'Title', 'axismundi-pilot' )       => $metadata['title'] ?? '',
	__( 'Artist', 'axismundi-pilot' )      => $metadata['artist'] ?? '',
	__( 'Album', 'axismundi-pilot' )       => $metadata['album'] ?? '',
	__( 'Description', 'axismundi-pilot' ) => $metadata['description'] ?? '',
	__( 'Text', 'axismundi-pilot' )        => $metadata['text'] ?? '',
) );

if ( ! empty( $metadata['tags'] ) && is_array( $metadata['tags'] ) ) {
	foreach ( array( 'title', 'artist', 'album', 'genre', 'year' ) as $tag ) {
		if ( ! empty( $metadata['tags'][ $tag ] ) ) {
			$meta_items[ ucwords( str_replace( '_', ' ', $tag ) ) ] = is_array( $metadata['tags'][ $tag ] )
				? implode( ', ', array_filter( $metadata['tags'][ $tag ] ) )
				: $metadata['tags'][ $tag ];
		}
	}
}

$raw_meta = array(
	__( 'WP attachment metadata', 'axismundi-pilot' ) => $metadata,
	__( 'Attachment custom fields', 'axismundi-pilot' ) => get_post_meta( $attachment_id ),
);

?>
<div class="ax-attachment-media ax-attachment-media--audio">
	<?php if ( $cover_image ) : ?>
		<figure class="wp-block-image size-large ax-attachment-media__cover">
			<?php echo $cover_image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</figure>
	<?php endif; ?>
	<?php echo $audio_block; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php axismundi_pilot_render_attachment_meta( $meta_items, $raw_meta ); ?>
</div>
