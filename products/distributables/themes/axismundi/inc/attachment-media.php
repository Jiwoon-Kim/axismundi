<?php
/**
 * Axismundi — attachment media rendering.
 *
 * WordPress core has no block that outputs "the current attachment" —
 * core/post-content renders only the description — so an attachment page would
 * otherwise show metadata with no media. This module prepends the attachment
 * file to the Post Content block on attachment pages, switching on the
 * attachment's MIME type to emit a native, responsive element: an image, an
 * audio player, a video player (with caption tracks paired by a non-standard,
 * theme-specific filename convention — see axismundi_attachment_caption_tracks),
 * or a download link as a fallback. It renders only the file itself and invents
 * no associations beyond that caption pairing.
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
			$media = wp_get_attachment_image(
				$post_id,
				'full',
				false,
				array(
					'decoding' => 'async',
					'loading'  => 'eager',
				)
			);
			break;

		case 'audio':
			$cover_id   = axismundi_audio_cover_attachment_id( $post_id );
			$cover_html = $cover_id ? wp_get_attachment_image(
				$cover_id,
				'large',
				false,
				array(
					'class' => 'axismundi-attachment-audio-cover',
				)
			) : '';
			$caption    = axismundi_audio_metadata_caption( $post_id );
			$media = sprintf(
				'%1$s<audio controls preload="metadata" src="%2$s"></audio>%3$s',
				$cover_html,
				esc_url( $url ),
				$caption
			);
			break;

		case 'video':
			// Caption tracks via a non-standard, theme-specific filename
			// convention (see axismundi_attachment_caption_tracks); WordPress
			// has no native video-attachment -> caption association.
			$tracks = '';
			foreach ( axismundi_attachment_caption_tracks( $post_id ) as $track ) {
				$tracks .= sprintf(
					'<track kind="captions" src="%s" srclang="%s" label="%s"%s>',
					esc_url( $track['src'] ),
					esc_attr( $track['srclang'] ),
					esc_attr( $track['label'] ),
					$track['default'] ? ' default' : ''
				);
			}
			$media = sprintf(
				'<video controls playsinline preload="metadata" src="%s">%s</video>',
				esc_url( $url ),
				$tracks
			);
			break;

		default:
			// PDFs, plain text, WebVTT, archives, etc. — offer the file itself.
			$media = sprintf(
				'<a href="%s" download>%s</a>',
				esc_url( $url ),
				esc_html( wp_basename( (string) get_attached_file( $post_id ) ) )
			);
	}

	if ( '' === $media ) {
		return '';
	}

	return sprintf(
		'<figure class="axismundi-attachment-media">%1$s</figure>%2$s',
		$media,
		axismundi_attachment_metadata_html( $post_id )
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
 * Find cover art associated with an audio attachment.
 *
 * WordPress extracts embedded audio artwork as an attachment and often links it
 * via `_thumbnail_id`. When that explicit link is absent, try the attachment
 * metadata image id and finally the sibling filename pattern used for extracted
 * cover art.
 *
 * @param int $attachment_id Audio attachment ID.
 * @return int Cover attachment ID, or 0 when none is available.
 */
function axismundi_audio_cover_attachment_id( int $attachment_id ) : int {
	$thumbnail_id = (int) get_post_meta( $attachment_id, '_thumbnail_id', true );
	if ( $thumbnail_id && 'attachment' === get_post_type( $thumbnail_id ) ) {
		return $thumbnail_id;
	}

	$metadata = wp_get_attachment_metadata( $attachment_id );
	if ( ! empty( $metadata['image'] ) && is_array( $metadata['image'] ) ) {
		foreach ( array( 'attachment_id', 'id' ) as $key ) {
			if ( ! empty( $metadata['image'][ $key ] ) ) {
				$image_id = (int) $metadata['image'][ $key ];
				if ( $image_id && 'attachment' === get_post_type( $image_id ) ) {
					return $image_id;
				}
			}
		}
	}

	if ( empty( $metadata['image'] ) || ! is_array( $metadata['image'] ) ) {
		return 0;
	}

	$attached_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
	if ( '' === $attached_file ) {
		return 0;
	}

	$directory = trailingslashit( dirname( $attached_file ) );
	$basename  = pathinfo( $attached_file, PATHINFO_FILENAME );
	$extension = pathinfo( $attached_file, PATHINFO_EXTENSION );

	$cover_posts = get_posts(
		array(
			'fields'         => 'ids',
			'meta_key'       => '_wp_attached_file',
			'meta_value'     => array(
				$directory . $basename . '-' . $extension . '-image.jpg',
				$directory . $basename . '-image.jpg',
			),
			'meta_compare'   => 'IN',
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
			'post_type'      => 'attachment',
			'posts_per_page' => 1,
		)
	);

	return $cover_posts ? (int) $cover_posts[0] : 0;
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

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'axismundi_filter_opus_attachment_metadata', 11, 2 );

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
