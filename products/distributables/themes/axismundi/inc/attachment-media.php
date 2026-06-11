<?php
/**
 * Axismundi — attachment media rendering.
 *
 * WordPress core has no block that outputs "the current attachment" —
 * core/post-content renders only the description — so an attachment page would
 * otherwise show metadata with no media. This module prepends the attachment
 * file to the Post Content block on attachment pages, switching on the
 * attachment's MIME type to emit a responsive media object: raster images use
 * the real core/image block path so theme.json lightbox/radius settings apply,
 * audio/video use native players (with caption tracks paired by a non-standard,
 * theme-specific filename convention — see axismundi_attachment_caption_tracks),
 * and other files fall back to a download link. It renders only the file itself
 * and invents no associations beyond that caption pairing.
 *
 * Wiring note: this filters render_block_core/post-content rather than
 * registering a block. register_block_type() is plugin territory and is
 * disallowed in themes by the WordPress.org Theme Check, so a server-side
 * filter is the theme-legal way to inject the media. The elements carry no
 * presentational CSS — they ride the theme's global responsive media baseline
 * in style.css (max-width:100%).
 *
 * @package Axismundi
 */

defined( 'ABSPATH' ) || exit;

// Axismundi renders attachment media itself; drop core's the_content prepend so
// the file is never injected twice on an attachment page.
remove_filter( 'the_content', 'prepend_attachment' );

/**
 * Prepend the attachment file to the Post Content block on attachment pages.
 *
 * @param string   $block_content Rendered Post Content block markup.
 * @param array    $block         Parsed block.
 * @param WP_Block $instance      Block instance, carrying post context.
 * @return string Filtered block markup.
 */
function axismundi_prepend_attachment_media( $block_content, $block, $instance ) {
	$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : 0;

	if ( ! $post_id || ! is_attachment( $post_id ) ) {
		return $block_content;
	}

	return axismundi_attachment_media_html( $post_id ) . $block_content;
}
add_filter( 'render_block_core/post-content', 'axismundi_prepend_attachment_media', 10, 3 );

/**
 * Build the media element for an attachment, by MIME type.
 *
 * @param int $post_id Attachment ID.
 * @return string Media HTML, or '' when there is nothing to render.
 */
function axismundi_attachment_media_html( int $post_id ) : string {
	$url = wp_get_attachment_url( $post_id );
	if ( ! $url ) {
		return '';
	}

	$type  = strtok( (string) get_post_mime_type( $post_id ), '/' );
	$media = '';

	switch ( $type ) {
		case 'image':
			$media = axismundi_attachment_image_block_html(
				$post_id,
				'full',
				'axismundi-attachment-media__image',
				array( 'loading' => 'eager' )
			);
			break;

		case 'audio':
			// Cover art, when present, is the attachment's featured image
			// (_thumbnail_id) and is rendered by the post-featured-image block in
			// templates/attachment.html. So the audio branch owns only the player
			// plus any artist/album caption — one MIME, one responsibility.
			$media = axismundi_attachment_audio_block_html( $post_id, $url )
				. axismundi_audio_metadata_caption( $post_id );
			break;

		case 'video':
			// Caption tracks via a non-standard, theme-specific filename
			// convention (see axismundi_attachment_caption_tracks); WordPress
			// has no native video-attachment -> caption association.
			$tracks = axismundi_attachment_caption_tracks( $post_id );
			$media = axismundi_attachment_video_block_html( $post_id, $url, $tracks );
			break;

		default:
			// PDFs, plain text, WebVTT, archives, etc. — render through core/file
			// so the WP file block styling and Download button contract apply.
			$media = axismundi_attachment_file_block_html( $post_id, $url );
	}

	if ( '' === $media ) {
		return '';
	}

	return sprintf(
		'<div class="axismundi-attachment-media axismundi-attachment-media--%3$s">%1$s</div>%2$s',
		$media,
		axismundi_attachment_metadata_html( $post_id ),
		esc_attr( $type ?: 'file' )
	);
}

/**
 * Render an attachment image through core/image so block settings apply.
 *
 * @param int                 $attachment_id Attachment ID.
 * @param string              $size          Image size slug.
 * @param string              $figure_class  Extra class on the core/image figure.
 * @param array<string,mixed> $attr          Extra image attributes.
 * @return string Rendered core/image block HTML, or raw SVG fallback.
 */
function axismundi_attachment_image_block_html( int $attachment_id, string $size = 'large', string $figure_class = '', array $attr = array() ) : string {
	$url = wp_get_attachment_url( $attachment_id );
	if ( ! $url ) {
		return '';
	}

	$mime_type = get_post_mime_type( $attachment_id );
	if ( 'image/svg+xml' === $mime_type ) {
		return sprintf(
			'<figure class="wp-block-image size-%1$s %2$s"><img src="%3$s" alt="%4$s" loading="lazy" decoding="async" /></figure>',
			esc_attr( $size ),
			esc_attr( $figure_class ),
			esc_url( $url ),
			esc_attr( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) )
		);
	}

	$image_attr = array_merge(
		array(
			'class'    => 'wp-image-' . (int) $attachment_id,
			'decoding' => 'async',
			'loading'  => 'lazy',
		),
		$attr
	);
	$image      = wp_get_attachment_image( $attachment_id, $size, false, $image_attr );
	if ( ! $image ) {
		return '';
	}

	$figure_class = trim( 'wp-block-image size-' . $size . ' ' . $figure_class );
	$block_attrs  = array(
		'id'              => (int) $attachment_id,
		'sizeSlug'        => $size,
		'linkDestination' => 'none',
	);

	return do_blocks(
		sprintf(
			'<!-- wp:image %1$s --><figure class="%2$s">%3$s</figure><!-- /wp:image -->',
			wp_json_encode( $block_attrs ),
			esc_attr( $figure_class ),
			$image
		)
	);
}

/**
 * Render an attachment audio file through core/audio so block settings apply.
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $url           Attachment URL.
 * @return string Rendered core/audio block HTML.
 */
function axismundi_attachment_audio_block_html( int $attachment_id, string $url ) : string {
	if ( '' === $url ) {
		return '';
	}

	$block_attrs = array(
		'id'  => (int) $attachment_id,
		'src' => esc_url_raw( $url ),
	);

	return do_blocks(
		sprintf(
			'<!-- wp:audio %1$s --><figure class="wp-block-audio"><audio controls preload="metadata" src="%2$s"></audio></figure><!-- /wp:audio -->',
			wp_json_encode( $block_attrs ),
			esc_url( $url )
		)
	);
}

/**
 * Render an attachment video file through core/video so block settings apply.
 *
 * @param int                                                             $attachment_id Attachment ID.
 * @param string                                                          $url           Attachment URL.
 * @param array<int,array{src:string,srclang:string,label:string,default:bool}> $tracks Caption tracks.
 * @return string Rendered core/video block HTML.
 */
function axismundi_attachment_video_block_html( int $attachment_id, string $url, array $tracks = array() ) : string {
	if ( '' === $url ) {
		return '';
	}

	$block_tracks = array();
	$track_html   = '';
	foreach ( $tracks as $track ) {
		$block_tracks[] = array(
			'src'     => $track['src'],
			'label'   => $track['label'],
			'srcLang' => $track['srclang'],
			'kind'    => 'subtitles',
			'default' => (bool) $track['default'],
		);
		$track_html .= sprintf(
			'<track kind="subtitles" src="%s" srclang="%s" label="%s"%s>',
			esc_url( $track['src'] ),
			esc_attr( $track['srclang'] ),
			esc_attr( $track['label'] ),
			$track['default'] ? ' default' : ''
		);
	}

	$block_attrs = array(
		'id'  => (int) $attachment_id,
		'src' => esc_url_raw( $url ),
	);
	if ( $block_tracks ) {
		$block_attrs['tracks'] = $block_tracks;
	}

	return do_blocks(
		sprintf(
			'<!-- wp:video %1$s --><figure class="wp-block-video"><video controls playsinline preload="metadata" src="%2$s">%3$s</video></figure><!-- /wp:video -->',
			wp_json_encode( $block_attrs ),
			esc_url( $url ),
			$track_html
		)
	);
}

/**
 * Render a non-previewable attachment through core/file so the WP file block
 * styling and Download button contract apply (PDF, WebVTT, plain text, archives,
 * and any other MIME without a media player).
 *
 * @param int    $attachment_id Attachment ID.
 * @param string $url           Attachment URL.
 * @return string Rendered core/file block HTML.
 */
function axismundi_attachment_file_block_html( int $attachment_id, string $url ) : string {
	if ( '' === $url ) {
		return '';
	}

	$label = get_the_title( $attachment_id );
	if ( '' === trim( (string) $label ) ) {
		$label = wp_basename( (string) get_attached_file( $attachment_id ) );
	}

	$block_attrs = array(
		'id'             => (int) $attachment_id,
		'href'           => esc_url_raw( $url ),
		'displayPreview' => false,
	);

	return do_blocks(
		sprintf(
			'<!-- wp:file %1$s --><div class="wp-block-file"><a id="wp-block-file--media-%2$d" href="%3$s">%4$s</a><a href="%3$s" class="wp-block-file__button wp-element-button" download aria-describedby="wp-block-file--media-%2$d">%5$s</a></div><!-- /wp:file -->',
			wp_json_encode( $block_attrs ),
			(int) $attachment_id,
			esc_url( $url ),
			esc_html( $label ),
			esc_html__( 'Download', 'axismundi' )
		)
	);
}

/**
 * Build a compact attachment metadata summary plus raw WordPress metadata.
 *
 * The summary is meant for humans; the raw details are deliberately collapsed
 * and mirror WordPress' own attachment metadata/custom-field storage for VQA
 * and media-object debugging.
 *
 * @param int $attachment_id Attachment ID.
 * @return string Attachment metadata HTML, or '' when there is nothing to show.
 */
function axismundi_attachment_metadata_html( int $attachment_id ) : string {
	$metadata = wp_get_attachment_metadata( $attachment_id );
	$metadata = is_array( $metadata ) ? $metadata : array();
	$file     = get_attached_file( $attachment_id );
	$mime     = get_post_mime_type( $attachment_id );

	$items = array(
		__( 'Attachment ID', 'axismundi' ) => $attachment_id,
		__( 'MIME type', 'axismundi' )     => $mime,
		__( 'File name', 'axismundi' )     => $file ? wp_basename( $file ) : '',
		__( 'File size', 'axismundi' )     => ( $file && file_exists( $file ) ) ? size_format( filesize( $file ) ) : '',
	);

	if ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
		$items[ __( 'Dimensions', 'axismundi' ) ] = sprintf(
			/* translators: 1: width in pixels, 2: height in pixels. */
			__( '%1$d x %2$d px', 'axismundi' ),
			(int) $metadata['width'],
			(int) $metadata['height']
		);
	}

	if ( wp_attachment_is( 'image', $attachment_id ) ) {
		$alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( '' !== trim( (string) $alt_text ) ) {
			$items[ __( 'Alternative text', 'axismundi' ) ] = $alt_text;
		}
	}

	if ( ! empty( $metadata['length_formatted'] ) ) {
		$items[ __( 'Length', 'axismundi' ) ] = $metadata['length_formatted'];
	}

	foreach ( array(
		'dataformat'        => __( 'Format', 'axismundi' ),
		'fileformat'        => __( 'File format', 'axismundi' ),
		'channelmode'       => __( 'Channel mode', 'axismundi' ),
		'channels'          => __( 'Channels', 'axismundi' ),
		'bitrate_mode'      => __( 'Bitrate mode', 'axismundi' ),
		'encoder'           => __( 'Encoder', 'axismundi' ),
		'encoder_settings'  => __( 'Encoder settings', 'axismundi' ),
		'title'             => __( 'Title', 'axismundi' ),
		'artist'            => __( 'Artist', 'axismundi' ),
		'album'             => __( 'Album', 'axismundi' ),
		'genre'             => __( 'Genre', 'axismundi' ),
		'year'              => __( 'Year', 'axismundi' ),
		'description'       => __( 'Description', 'axismundi' ),
		'compression_ratio' => __( 'Compression ratio', 'axismundi' ),
	) as $key => $label ) {
		if ( isset( $metadata[ $key ] ) && '' !== $metadata[ $key ] && array() !== $metadata[ $key ] ) {
			$items[ $label ] = $metadata[ $key ];
		}
	}

	if ( ! empty( $metadata['sample_rate'] ) ) {
		$items[ __( 'Sample rate', 'axismundi' ) ] = number_format_i18n( (int) $metadata['sample_rate'] ) . ' Hz';
	}

	if ( ! empty( $metadata['sample_rate_input'] ) ) {
		$items[ __( 'Input sample rate', 'axismundi' ) ] = number_format_i18n( (int) $metadata['sample_rate_input'] ) . ' Hz';
	}

	if ( ! empty( $metadata['bitrate'] ) ) {
		$items[ __( 'Bitrate', 'axismundi' ) ] = size_format( (int) ( (float) $metadata['bitrate'] / 8 ) ) . '/s';
	}

	if ( ! empty( $metadata['lossless'] ) || ( isset( $metadata['lossless'] ) && false === $metadata['lossless'] ) ) {
		$items[ __( 'Lossless', 'axismundi' ) ] = (bool) $metadata['lossless'];
	}

	if ( ! empty( $metadata['image_meta'] ) && is_array( $metadata['image_meta'] ) ) {
		$image_meta = $metadata['image_meta'];
		$has_visible_image_meta = false;
		$image_items = array(
			'camera'        => __( 'Camera', 'axismundi' ),
			'copyright'     => __( 'Copyright', 'axismundi' ),
			'iso'           => __( 'ISO', 'axismundi' ),
			'shutter_speed' => __( 'Shutter speed', 'axismundi' ),
		);

		foreach ( $image_items as $key => $label ) {
			if ( ! empty( $image_meta[ $key ] ) ) {
				$items[ $label ] = $image_meta[ $key ];
				$has_visible_image_meta = true;
			}
		}

		if ( ! empty( $image_meta['aperture'] ) ) {
			$items[ __( 'Aperture', 'axismundi' ) ] = 'f/' . $image_meta['aperture'];
			$has_visible_image_meta = true;
		}

		if ( ! empty( $image_meta['focal_length'] ) ) {
			$items[ __( 'Focal length', 'axismundi' ) ] = $image_meta['focal_length'] . ' mm';
			$has_visible_image_meta = true;
		}

		if ( ! empty( $image_meta['created_timestamp'] ) ) {
			$items[ __( 'Created', 'axismundi' ) ] = wp_date( get_option( 'date_format' ), (int) $image_meta['created_timestamp'] );
			$has_visible_image_meta = true;
		}

		if ( ! $has_visible_image_meta ) {
			$items[ __( 'Image metadata', 'axismundi' ) ] = __( 'Present; no camera/EXIF values', 'axismundi' );
		}
	}

	$raw = array(
		__( 'WP attachment metadata', 'axismundi' )    => $metadata,
		__( 'Attachment custom fields', 'axismundi' ) => get_post_meta( $attachment_id ),
	);

	return axismundi_render_attachment_metadata( $items, $raw );
}

/**
 * Render attachment metadata as a definition list plus collapsed raw JSON.
 *
 * @param array<string,mixed> $items Metadata label/value pairs.
 * @param array<string,mixed> $raw   Raw metadata groups.
 * @return string Metadata HTML.
 */
function axismundi_render_attachment_metadata( array $items, array $raw ) : string {
	$items = array_filter(
		$items,
		static fn( $value ) : bool => ! ( null === $value || '' === $value || '0' === $value || array() === $value )
	);
	$raw   = array_filter(
		$raw,
		static fn( $value ) : bool => ! ( null === $value || '' === $value || array() === $value )
	);

	if ( ! $items && ! $raw ) {
		return '';
	}

	$html = '<section class="axismundi-attachment-meta" aria-label="' . esc_attr__( 'Attachment metadata', 'axismundi' ) . '">';

	if ( $items ) {
		$html .= '<dl class="axismundi-attachment-meta__summary">';
		foreach ( $items as $label => $value ) {
			$html .= '<dt>' . esc_html( (string) $label ) . '</dt>';
			$html .= '<dd>' . esc_html( axismundi_format_attachment_metadata_value( $value ) ) . '</dd>';
		}
		$html .= '</dl>';
	}

	foreach ( $raw as $label => $value ) {
		$html .= '<details class="axismundi-attachment-meta__raw">';
		$html .= '<summary>' . esc_html( (string) $label ) . '</summary>';
		$html .= '<pre><code>' . esc_html( wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) . '</code></pre>';
		$html .= '</details>';
	}

	$html .= '</section>';

	return $html;
}

/**
 * Format metadata values for summary display.
 *
 * @param mixed $value Metadata value.
 * @return string Formatted value.
 */
function axismundi_format_attachment_metadata_value( $value ) : string {
	if ( is_bool( $value ) ) {
		return $value ? __( 'true', 'axismundi' ) : __( 'false', 'axismundi' );
	}

	if ( is_array( $value ) ) {
		return implode( ', ', array_map( 'strval', array_filter( $value ) ) );
	}

	return (string) $value;
}

/**
 * Build a compact visible caption from audio metadata.
 *
 * @param int $attachment_id Audio attachment ID.
 * @return string Figcaption HTML, or '' when no artist/album is known.
 */
function axismundi_audio_metadata_caption( int $attachment_id ) : string {
	$metadata = wp_get_attachment_metadata( $attachment_id );
	if ( ! is_array( $metadata ) ) {
		return '';
	}

	$items = array_filter(
		array(
			$metadata['artist'] ?? '',
			$metadata['album'] ?? '',
		),
		static fn( $item ) : bool => '' !== trim( (string) $item )
	);

	if ( ! $items ) {
		return '';
	}

	return sprintf(
		'<figcaption class="wp-element-caption">%s</figcaption>',
		esc_html( implode( ' · ', $items ) )
	);
}

/**
 * Supplement WebP attachment metadata with EXIF values from the RIFF EXIF chunk.
 *
 * WordPress core's normal EXIF path is JPEG/TIFF-oriented; PHP's exif_read_data()
 * does not expose the EXIF chunk in these WebP fixtures. Parse the embedded TIFF
 * payload directly so the attachment page can surface camera metadata.
 *
 * @param array<string,mixed> $metadata      Generated attachment metadata.
 * @param int                 $attachment_id Attachment ID.
 * @return array<string,mixed>
 */
function axismundi_filter_webp_attachment_metadata( array $metadata, int $attachment_id ) : array {
	if ( 'image/webp' !== get_post_mime_type( $attachment_id ) ) {
		return $metadata;
	}

	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! is_readable( $file ) ) {
		return $metadata;
	}

	$exif = axismundi_read_webp_exif( $file );
	if ( ! $exif ) {
		return $metadata;
	}

	$metadata['image_meta'] = array_merge(
		isset( $metadata['image_meta'] ) && is_array( $metadata['image_meta'] ) ? $metadata['image_meta'] : array(),
		array_filter(
			array(
				'aperture'          => $exif['FNumber'] ?? null,
				'camera'            => $exif['Model'] ?? null,
				'created_timestamp' => ! empty( $exif['DateTimeDigitized'] ) ? wp_exif_date2ts( $exif['DateTimeDigitized'] ) : null,
				'focal_length'      => $exif['FocalLength'] ?? null,
				'iso'               => $exif['ISOSpeedRatings'] ?? null,
				'shutter_speed'     => $exif['ExposureTime'] ?? null,
				'title'             => $exif['ImageDescription'] ?? null,
				'copyright'         => $exif['Copyright'] ?? null,
				'orientation'       => $exif['Orientation'] ?? null,
			),
			static fn( $value ) : bool => ! ( null === $value || '' === $value || 0 === $value || '0' === $value )
		)
	);

	$metadata['axismundi_webp_exif'] = $exif;

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'axismundi_filter_webp_attachment_metadata', 10, 2 );

/**
 * Supplement Opus-in-Ogg metadata with Vorbis comments.
 *
 * Some Opus fixtures expose `artist` and `album` inside the OpusTags packet,
 * while WordPress' normal metadata import leaves those fields empty. Read the
 * packet directly so attachment pages can surface the original media tags.
 *
 * @param array<string,mixed> $metadata      Generated attachment metadata.
 * @param int                 $attachment_id Attachment ID.
 * @return array<string,mixed>
 */
function axismundi_filter_opus_attachment_metadata( array $metadata, int $attachment_id ) : array {
	$mime_type = get_post_mime_type( $attachment_id );
	if ( 'audio/ogg' !== $mime_type && 'opus' !== ( $metadata['dataformat'] ?? '' ) ) {
		return $metadata;
	}

	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! is_readable( $file ) ) {
		return $metadata;
	}

	$comments = axismundi_read_opus_tags( $file );
	if ( ! $comments ) {
		return $metadata;
	}

	$map = array(
		'album'       => 'album',
		'artist'      => 'artist',
		'title'       => 'title',
		'genre'       => 'genre',
		'date'        => 'year',
		'description' => 'description',
	);

	foreach ( $map as $comment_key => $metadata_key ) {
		if ( ! empty( $comments[ $comment_key ] ) ) {
			$metadata[ $metadata_key ] = $comments[ $comment_key ];
		}
	}

	$metadata['axismundi_opus_tags'] = $comments;
	axismundi_normalize_embedded_cover_attachment( $attachment_id, $comments );

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'axismundi_filter_opus_attachment_metadata', 11, 2 );

/**
 * Fill the title/slug/alt text on WordPress core's generated audio cover art.
 *
 * Core extracts embedded Ogg/Opus cover art into a real image attachment and
 * links it via _thumbnail_id, but currently leaves the generated image's title
 * empty and the slug as the numeric ID. That makes Media Library grids show the
 * temporary "uploading..." label even after the upload has completed. This is a
 * small theme-side shim until the behavior is fixed upstream in core.
 *
 * @param int                  $audio_attachment_id Audio attachment ID.
 * @param array<string,string> $comments            Parsed Opus comments.
 * @return void
 */
function axismundi_normalize_embedded_cover_attachment( int $audio_attachment_id, array $comments = array() ) : void {
	$cover_id = (int) get_post_meta( $audio_attachment_id, '_thumbnail_id', true );
	if ( ! $cover_id || ! wp_attachment_is( 'image', $cover_id ) ) {
		return;
	}

	$cover_file = (string) get_attached_file( $cover_id );
	if ( ! get_post_meta( $cover_id, '_cover_hash', true ) && false === strpos( wp_basename( $cover_file ), '-ogg-image' ) ) {
		return;
	}

	$audio_title = isset( $comments['title'] ) ? trim( (string) $comments['title'] ) : '';
	if ( '' === $audio_title ) {
		$audio_title = trim( get_the_title( $audio_attachment_id ) );
	}
	if ( '' === $audio_title ) {
		$audio_title = preg_replace( '/\.[^.]+$/', '', wp_basename( (string) get_attached_file( $audio_attachment_id ) ) );
	}
	if ( '' === trim( (string) $audio_title ) ) {
		return;
	}

	$cover_title = sprintf(
		/* translators: %s: audio attachment title. */
		__( '%s cover art', 'axismundi' ),
		$audio_title
	);
	$cover_post  = get_post( $cover_id );

	$needs_update = ! $cover_post
		|| '' === trim( (string) $cover_post->post_title )
		|| (string) $cover_id === (string) $cover_post->post_name
		|| 'uploading' === strtolower( trim( (string) $cover_post->post_title, ". \t\n\r\0\x0B" ) );

	if ( $needs_update ) {
		wp_update_post(
			array(
				'ID'         => $cover_id,
				'post_title' => $cover_title,
				'post_name'  => sanitize_title( $cover_title ),
			)
		);
	}

	if ( '' === trim( (string) get_post_meta( $cover_id, '_wp_attachment_image_alt', true ) ) ) {
		update_post_meta(
			$cover_id,
			'_wp_attachment_image_alt',
			sprintf(
				/* translators: %s: audio attachment title. */
				__( 'Album artwork for %s', 'axismundi' ),
				$audio_title
			)
		);
	}
}

/**
 * Read Vorbis comments from the first OpusTags packet in an Ogg file.
 *
 * @param string $file OPUS/Ogg file path.
 * @return array<string,string>
 */
function axismundi_read_opus_tags( string $file ) : array {
	$data = file_get_contents( $file );
	if ( false === $data ) {
		return array();
	}

	$offset = strpos( $data, 'OpusTags' );
	if ( false === $offset ) {
		return array();
	}

	$cursor = $offset + 8;
	$length = strlen( $data );
	if ( $cursor + 8 > $length ) {
		return array();
	}

	$vendor_length = axismundi_le_uint32( $data, $cursor );
	$cursor       += 4 + $vendor_length;
	if ( $cursor + 4 > $length ) {
		return array();
	}

	$comment_count = axismundi_le_uint32( $data, $cursor );
	$cursor       += 4;
	$comments      = array();

	for ( $i = 0; $i < $comment_count; $i++ ) {
		if ( $cursor + 4 > $length ) {
			break;
		}

		$comment_length = axismundi_le_uint32( $data, $cursor );
		$cursor        += 4;
		if ( $cursor + $comment_length > $length ) {
			break;
		}

		$comment = substr( $data, $cursor, $comment_length );
		$cursor += $comment_length;

		$separator = strpos( $comment, '=' );
		if ( false === $separator ) {
			continue;
		}

		$key = strtolower( substr( $comment, 0, $separator ) );
		if ( 'metadata_block_picture' === $key ) {
			continue;
		}

		$comments[ $key ] = substr( $comment, $separator + 1 );
	}

	return $comments;
}

/**
 * Read a little-endian unsigned 32-bit integer.
 *
 * @param string $data Binary data.
 * @param int    $offset Byte offset.
 * @return int Parsed value.
 */
function axismundi_le_uint32( string $data, int $offset ) : int {
	$bytes = substr( $data, $offset, 4 );
	if ( 4 > strlen( $bytes ) ) {
		return 0;
	}

	return unpack( 'V', $bytes )[1];
}

/**
 * Read selected EXIF tags from a WebP RIFF EXIF chunk.
 *
 * @param string $file WebP file path.
 * @return array<string,mixed>
 */
function axismundi_read_webp_exif( string $file ) : array {
	$data = file_get_contents( $file );
	if ( false === $data || 12 > strlen( $data ) || 'RIFF' !== substr( $data, 0, 4 ) || 'WEBP' !== substr( $data, 8, 4 ) ) {
		return array();
	}

	$offset = 12;
	$length = strlen( $data );
	while ( $offset + 8 <= $length ) {
		$chunk_type = substr( $data, $offset, 4 );
		$chunk_size = unpack( 'V', substr( $data, $offset + 4, 4 ) )[1];
		$chunk_data = substr( $data, $offset + 8, $chunk_size );

		if ( 'EXIF' === $chunk_type ) {
			return axismundi_parse_tiff_exif( $chunk_data );
		}

		$offset += 8 + $chunk_size + ( $chunk_size % 2 );
	}

	return array();
}

/**
 * Parse the TIFF payload contained in a WebP EXIF chunk.
 *
 * @param string $data TIFF bytes.
 * @return array<string,mixed>
 */
function axismundi_parse_tiff_exif( string $data ) : array {
	if ( 8 > strlen( $data ) ) {
		return array();
	}

	$byte_order = substr( $data, 0, 2 );
	if ( 'II' === $byte_order ) {
		$endian = 'little';
	} elseif ( 'MM' === $byte_order ) {
		$endian = 'big';
	} else {
		return array();
	}

	$magic = axismundi_tiff_uint16( $data, 2, $endian );
	if ( 42 !== $magic ) {
		return array();
	}

	$ifd_offset = axismundi_tiff_uint32( $data, 4, $endian );
	$ifd0       = axismundi_parse_tiff_ifd( $data, $ifd_offset, $endian );
	$exif_ifd   = array();
	if ( ! empty( $ifd0['ExifIFDPointer'] ) ) {
		$exif_ifd = axismundi_parse_tiff_ifd( $data, (int) $ifd0['ExifIFDPointer'], $endian );
	}

	$flat = array_merge( $ifd0, $exif_ifd );
	unset( $flat['ExifIFDPointer'] );

	if ( ! empty( $flat['FNumber'] ) ) {
		$flat['FNumber'] = round( (float) $flat['FNumber'], 2 );
	}
	if ( ! empty( $flat['ExposureTime'] ) ) {
		$flat['ExposureTime'] = (string) $flat['ExposureTime'];
	}
	if ( ! empty( $flat['FocalLength'] ) ) {
		$flat['FocalLength'] = (string) $flat['FocalLength'];
	}

	return $flat;
}

/**
 * Parse a TIFF Image File Directory.
 *
 * @param string $data   TIFF bytes.
 * @param int    $offset IFD offset.
 * @param string $endian Byte order.
 * @return array<string,mixed>
 */
function axismundi_parse_tiff_ifd( string $data, int $offset, string $endian ) : array {
	$tag_names = array(
		0x010f => 'Make',
		0x0110 => 'Model',
		0x0112 => 'Orientation',
		0x0131 => 'Software',
		0x0132 => 'DateTime',
		0x829a => 'ExposureTime',
		0x829d => 'FNumber',
		0x8769 => 'ExifIFDPointer',
		0x8827 => 'ISOSpeedRatings',
		0x9003 => 'DateTimeOriginal',
		0x9004 => 'DateTimeDigitized',
		0x9201 => 'ShutterSpeedValue',
		0x9202 => 'ApertureValue',
		0x9204 => 'ExposureBiasValue',
		0x9205 => 'MaxApertureValue',
		0x920a => 'FocalLength',
		0xa002 => 'ExifImageWidth',
		0xa003 => 'ExifImageHeight',
		0xa405 => 'FocalLengthIn35mmFilm',
	);

	if ( 0 > $offset || $offset + 2 > strlen( $data ) ) {
		return array();
	}

	$count = axismundi_tiff_uint16( $data, $offset, $endian );
	$out   = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$entry_offset = $offset + 2 + ( 12 * $i );
		if ( $entry_offset + 12 > strlen( $data ) ) {
			break;
		}

		$tag = axismundi_tiff_uint16( $data, $entry_offset, $endian );
		if ( ! isset( $tag_names[ $tag ] ) ) {
			continue;
		}

		$type  = axismundi_tiff_uint16( $data, $entry_offset + 2, $endian );
		$num   = axismundi_tiff_uint32( $data, $entry_offset + 4, $endian );
		$value = axismundi_tiff_value( $data, $type, $num, substr( $data, $entry_offset + 8, 4 ), $endian );
		if ( null !== $value && '' !== $value ) {
			$out[ $tag_names[ $tag ] ] = $value;
		}
	}

	return $out;
}

/**
 * Read a TIFF entry value.
 *
 * @param string $data        TIFF bytes.
 * @param int    $type        TIFF field type.
 * @param int    $count       Value count.
 * @param string $value_bytes Inline value or value offset bytes.
 * @param string $endian      Byte order.
 * @return mixed
 */
function axismundi_tiff_value( string $data, int $type, int $count, string $value_bytes, string $endian ) {
	$type_sizes = array(
		1  => 1,
		2  => 1,
		3  => 2,
		4  => 4,
		5  => 8,
		7  => 1,
		9  => 4,
		10 => 8,
	);
	if ( ! isset( $type_sizes[ $type ] ) ) {
		return null;
	}

	$size = $type_sizes[ $type ] * $count;
	if ( 4 >= $size ) {
		$raw = substr( $value_bytes, 0, $size );
	} else {
		$offset = axismundi_tiff_uint32( $value_bytes, 0, $endian );
		if ( 0 > $offset || $offset + $size > strlen( $data ) ) {
			return null;
		}
		$raw = substr( $data, $offset, $size );
	}

	if ( 2 === $type ) {
		return trim( rtrim( $raw, "\0" ) );
	}

	$values = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$chunk = substr( $raw, $i * $type_sizes[ $type ], $type_sizes[ $type ] );
		switch ( $type ) {
			case 3:
				$values[] = axismundi_tiff_uint16( $chunk, 0, $endian );
				break;
			case 4:
				$values[] = axismundi_tiff_uint32( $chunk, 0, $endian );
				break;
			case 5:
				$values[] = axismundi_tiff_rational( $chunk, $endian, false );
				break;
			case 9:
				$values[] = axismundi_tiff_int32( $chunk, 0, $endian );
				break;
			case 10:
				$values[] = axismundi_tiff_rational( $chunk, $endian, true );
				break;
			default:
				$values[] = bin2hex( $chunk );
		}
	}

	return 1 === count( $values ) ? $values[0] : $values;
}

/**
 * Convert TIFF rational bytes to a decimal string.
 *
 * @param string $chunk  8-byte numerator/denominator pair.
 * @param string $endian Byte order.
 * @param bool   $signed Whether the rational is signed.
 * @return string
 */
function axismundi_tiff_rational( string $chunk, string $endian, bool $signed ) : string {
	$numerator   = $signed ? axismundi_tiff_int32( $chunk, 0, $endian ) : axismundi_tiff_uint32( $chunk, 0, $endian );
	$denominator = $signed ? axismundi_tiff_int32( $chunk, 4, $endian ) : axismundi_tiff_uint32( $chunk, 4, $endian );

	if ( 0 === $denominator ) {
		return (string) $numerator;
	}

	return rtrim( rtrim( sprintf( '%.12F', $numerator / $denominator ), '0' ), '.' );
}

function axismundi_tiff_uint16( string $data, int $offset, string $endian ) : int {
	$bytes = substr( $data, $offset, 2 );
	if ( 2 > strlen( $bytes ) ) {
		return 0;
	}

	return unpack( 'little' === $endian ? 'v' : 'n', $bytes )[1];
}

function axismundi_tiff_uint32( string $data, int $offset, string $endian ) : int {
	$bytes = substr( $data, $offset, 4 );
	if ( 4 > strlen( $bytes ) ) {
		return 0;
	}

	return unpack( 'little' === $endian ? 'V' : 'N', $bytes )[1];
}

function axismundi_tiff_int32( string $data, int $offset, string $endian ) : int {
	$value = axismundi_tiff_uint32( $data, $offset, $endian );

	return $value > 0x7fffffff ? $value - 0x100000000 : $value;
}

/**
 * Find sibling WebVTT caption tracks for a video attachment.
 *
 * NON-STANDARD, Axismundi-specific convention. WordPress has no native
 * association between a raw video attachment and caption files — captions
 * normally live on the core/video block (its track UI stores them in post
 * content), and nothing in the standard upload flow attaches a track to an
 * attachment *page*. This is a theme convenience for attachment pages only: a
 * video stored as "<base>.<ext>" is paired with any caption uploaded as
 * "<base>.<lang>.vtt" in the same uploads directory (e.g. gwangan-720p.webm <-
 * gwangan-720p.en.vtt / gwangan-720p.ko.vtt). The track matching the site
 * locale (else the first found) is marked default. Because the pairing is by
 * filename, an unrelated VTT that happens to share the base name will match.
 *
 * @param int $video_id Video attachment ID.
 * @return array<int,array{src:string,srclang:string,label:string,default:bool}>
 */
function axismundi_attachment_caption_tracks( int $video_id ) : array {
	$video_file = get_attached_file( $video_id );
	if ( ! $video_file ) {
		return array();
	}

	$dir  = trailingslashit( dirname( $video_file ) );
	$base = pathinfo( $video_file, PATHINFO_FILENAME );

	$vtt_ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'text/vtt',
			'post_status'    => 'inherit',
			'numberposts'    => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);

	$tracks = array();
	foreach ( $vtt_ids as $vtt_id ) {
		$vtt_file = get_attached_file( $vtt_id );
		if ( ! $vtt_file || trailingslashit( dirname( $vtt_file ) ) !== $dir ) {
			continue;
		}

		$name = wp_basename( $vtt_file );
		if ( 0 !== strpos( $name, $base . '.' ) ) {
			continue;
		}

		// Language code = the segment between "<base>." and ".vtt". WordPress
		// sanitises a double extension like "clip.en.vtt" to "clip.en_.vtt", so
		// trim the trailing underscore the sanitiser leaves behind.
		$lang = preg_replace( '/^' . preg_quote( $base . '.', '/' ) . '(.+)\.vtt$/i', '$1', $name );
		$lang = ( $lang === $name ) ? '' : trim( $lang, '_-. ' );

		$tracks[] = array(
			'src'     => wp_get_attachment_url( $vtt_id ),
			'srclang' => $lang,
			'label'   => axismundi_language_label( $lang ),
			'default' => false,
		);
	}

	// Default to the track matching the site language, else the first found.
	if ( $tracks ) {
		$site_lang   = strtolower( (string) strtok( get_locale(), '_-' ) );
		$default_idx = 0;
		foreach ( $tracks as $i => $track ) {
			if ( strtolower( $track['srclang'] ) === $site_lang ) {
				$default_idx = $i;
				break;
			}
		}
		$tracks[ $default_idx ]['default'] = true;
	}

	return $tracks;
}

/**
 * Human-readable label for a caption language code.
 *
 * @param string $lang Language code (e.g. "en", "ko").
 * @return string Display label.
 */
function axismundi_language_label( string $lang ) : string {
	if ( '' === $lang ) {
		return __( 'Captions', 'axismundi' );
	}

	$map = array(
		'en' => 'English',
		'ko' => '한국어',
		'ja' => '日本語',
	);

	return $map[ strtolower( $lang ) ] ?? strtoupper( $lang );
}
