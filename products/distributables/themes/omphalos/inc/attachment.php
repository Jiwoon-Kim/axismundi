<?php
/**
 * Omphalos — attachment media object templates.
 *
 * Ported from axismundi-pilot (functions renamed to the omphalos_ prefix).
 * Renders type-specific media for attachment pages and surfaces WEBP EXIF and
 * OPUS tag metadata that WordPress core does not expose by default.
 *
 * @package Omphalos
 */

defined( 'ABSPATH' ) || exit;
/**
 * Enable front-end attachment pages in the Pilot dev environment.
 *
 * WordPress disables attachment pages for new installs. The Pilot keeps them
 * enabled so media object templates can be tested. Do not copy this override
 * into a distributable theme without an explicit product decision.
 *
 * @return bool
 */
function omphalos_enable_attachment_pages() : bool {
	return true;
}
add_filter( 'pre_option_wp_attachment_pages_enabled', 'omphalos_enable_attachment_pages' );

// Attachment media is rendered by type-specific partials below.
remove_filter( 'the_content', 'prepend_attachment' );

/**
 * Enqueue the attachment media stylesheet — attachment pages only.
 *
 * The attachment template renders media OUTSIDE `.wp-block-post-content` and as
 * raw markup (e.g. wp_get_attachment_image), not parsed core blocks. So neither
 * prose.css (post-content scoped) nor the core block stylesheets (enqueued only
 * for real blocks) reach it — a full-size <img> would otherwise render at its
 * native width and overflow the content column. This sheet gives the
 * `.ax-attachment-media` surface its baseline (chiefly max-width on the media).
 *
 * @return void
 */
function omphalos_enqueue_attachment_styles() : void {
	if ( ! is_attachment() ) {
		return;
	}
	$uri = function_exists( 'omphalos_asset_uri' ) ? omphalos_asset_uri( 'assets/styles/attachment.css' ) : null;
	if ( null === $uri ) {
		return;
	}
	wp_enqueue_style(
		'omphalos-attachment',
		$uri,
		array(),
		defined( 'OMPHALOS_VERSION' ) ? OMPHALOS_VERSION : false
	);
}
add_action( 'wp_enqueue_scripts', 'omphalos_enqueue_attachment_styles', 20 );

/**
 * Register Pilot attachment metadata fields.
 *
 * `omphalos_video_tracks` stores a JSON array of WebVTT track definitions for
 * video attachment pages. Editing UI is intentionally deferred; during the
 * Pilot phase the field can be managed through WP-CLI or Custom Fields.
 *
 * @return void
 */
function omphalos_register_attachment_meta() : void {
	register_post_meta( 'attachment', 'omphalos_video_tracks', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'omphalos_sanitize_video_tracks_meta',
		'auth_callback'     => static function() : bool {
			return current_user_can( 'edit_posts' );
		},
	) );
}
add_action( 'init', 'omphalos_register_attachment_meta' );

/**
 * Render the media portion of attachment pages through a PHP partial hierarchy.
 *
 * @param string   $block_content Existing Post Content block output.
 * @param array    $block         Parsed block data.
 * @param WP_Block $instance      Block instance containing context.
 * @return string Filtered block output.
 */
function omphalos_render_attachment_block( string $block_content, array $block, WP_Block $instance ) : string {
	$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : 0;

	if ( ! $post_id || ! is_attachment( $post_id ) ) {
		return $block_content;
	}

	$partials = array();
	foreach ( array( 'image', 'video', 'audio' ) as $type ) {
		if ( wp_attachment_is( $type, $post_id ) ) {
			$partials[] = "partials/attachment-media-{$type}.php";
			break;
		}
	}
	$partials[] = 'partials/attachment-media.php';

	$partial = locate_template( $partials, false, false );
	if ( ! $partial ) {
		return $block_content;
	}

	$attachment_id = $post_id;

	ob_start();
	include $partial;
	$media_html = ob_get_clean();

	return $media_html . $block_content;
}
add_filter( 'render_block_core/post-content', 'omphalos_render_attachment_block', 10, 3 );

/**
 * Render attachment metadata as a compact definition list plus raw details.
 *
 * @param array<string,mixed> $items Metadata label/value pairs.
 * @param array<string,mixed> $raw   Raw metadata groups to display.
 */
function omphalos_render_attachment_meta( array $items, array $raw = array() ) : void {
	$items = array_filter(
		$items,
		static fn( $value ) : bool => ! ( null === $value || '' === $value || 0 === $value || '0' === $value || array() === $value )
	);

	$raw = array_filter(
		$raw,
		static fn( $value ) : bool => ! ( null === $value || '' === $value || array() === $value )
	);

	if ( empty( $items ) && empty( $raw ) ) {
		return;
	}

	echo '<section class="ax-attachment-meta" aria-label="' . esc_attr__( 'Attachment metadata', 'omphalos' ) . '">';

	if ( ! empty( $items ) ) {
		echo '<dl class="ax-attachment-meta__summary">';
		foreach ( $items as $label => $value ) {
			echo '<dt>' . esc_html( (string) $label ) . '</dt>';
			echo '<dd>' . esc_html( omphalos_format_attachment_meta_value( $value ) ) . '</dd>';
		}
		echo '</dl>';
	}

	foreach ( $raw as $label => $value ) {
		echo '<details class="ax-attachment-meta__raw">';
		echo '<summary>' . esc_html( (string) $label ) . '</summary>';
		echo '<pre><code>' . esc_html( wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) . '</code></pre>';
		echo '</details>';
	}

	echo '</section>';
}

/**
 * Format metadata values for summary display.
 *
 * @param mixed $value Metadata value.
 * @return string
 */
function omphalos_format_attachment_meta_value( $value ) : string {
	if ( is_bool( $value ) ) {
		return $value ? __( 'true', 'omphalos' ) : __( 'false', 'omphalos' );
	}

	if ( is_array( $value ) ) {
		return implode( ', ', array_map( 'strval', array_filter( $value ) ) );
	}

	return (string) $value;
}

/**
 * Build common attachment metadata fields.
 *
 * @param int $attachment_id Attachment ID.
 * @return array<string,mixed>
 */
function omphalos_get_attachment_common_meta( int $attachment_id ) : array {
	$file_path = get_attached_file( $attachment_id );
	$mime_type = get_post_mime_type( $attachment_id );

	return array(
		__( 'Attachment ID', 'omphalos' ) => $attachment_id,
		__( 'MIME type', 'omphalos' )     => $mime_type,
		__( 'File name', 'omphalos' )     => $file_path ? wp_basename( $file_path ) : '',
		__( 'File size', 'omphalos' )     => ( $file_path && file_exists( $file_path ) ) ? size_format( filesize( $file_path ) ) : '',
	);
}

/**
 * Sanitize the JSON video track metadata field.
 *
 * @param mixed $value Raw meta value.
 * @return string JSON-encoded track list.
 */
function omphalos_sanitize_video_tracks_meta( $value ) : string {
	$tracks = is_string( $value ) ? json_decode( $value, true ) : $value;

	if ( ! is_array( $tracks ) ) {
		return '';
	}

	$sanitized = array();
	foreach ( $tracks as $track ) {
		if ( ! is_array( $track ) ) {
			continue;
		}

		$src = isset( $track['src'] ) ? esc_url_raw( (string) $track['src'] ) : '';
		if ( ! $src ) {
			continue;
		}

		$kind = isset( $track['kind'] ) ? sanitize_key( (string) $track['kind'] ) : 'captions';
		if ( ! in_array( $kind, array( 'subtitles', 'captions', 'descriptions', 'chapters', 'metadata' ), true ) ) {
			$kind = 'captions';
		}

		$sanitized[] = array(
			'src'     => $src,
			'kind'    => $kind,
			'srclang' => isset( $track['srclang'] ) ? sanitize_key( (string) $track['srclang'] ) : '',
			'label'   => isset( $track['label'] ) ? sanitize_text_field( (string) $track['label'] ) : '',
			'default' => ! empty( $track['default'] ),
		);
	}

	return $sanitized ? wp_json_encode( $sanitized ) : '';
}

/**
 * Read WebVTT track definitions for a video attachment.
 *
 * @param int $attachment_id Attachment ID.
 * @return array<int,array<string,mixed>>
 */
function omphalos_get_video_tracks( int $attachment_id ) : array {
	$raw = get_post_meta( $attachment_id, 'omphalos_video_tracks', true );
	if ( ! $raw ) {
		return array();
	}

	$tracks = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
	if ( ! is_array( $tracks ) ) {
		return array();
	}

	$sanitized = omphalos_sanitize_video_tracks_meta( $tracks );
	$decoded   = $sanitized ? json_decode( $sanitized, true ) : array();

	return is_array( $decoded ) ? $decoded : array();
}

/**
 * Render WebVTT track tags for a video attachment.
 *
 * @param array<int,array<string,mixed>> $tracks Track definitions.
 * @return string HTML track tags.
 */
function omphalos_render_video_tracks( array $tracks ) : string {
	$html = '';

	foreach ( $tracks as $track ) {
		if ( empty( $track['src'] ) ) {
			continue;
		}

		$html .= sprintf(
			'<track kind="%1$s" src="%2$s"%3$s%4$s%5$s>',
			esc_attr( $track['kind'] ?? 'captions' ),
			esc_url( $track['src'] ),
			! empty( $track['srclang'] ) ? ' srclang="' . esc_attr( $track['srclang'] ) . '"' : '',
			! empty( $track['label'] ) ? ' label="' . esc_attr( $track['label'] ) . '"' : '',
			! empty( $track['default'] ) ? ' default' : ''
		);
	}

	return $html;
}

/**
 * Supplement WEBP attachment metadata with EXIF values from the RIFF EXIF chunk.
 *
 * WordPress core reads EXIF through PHP's `exif_read_data()`, and its default
 * metadata type list is JPEG/TIFF-oriented. PHP 8.3 returns false for WEBP
 * files even when they contain a valid EXIF chunk, so Pilot parses the small
 * TIFF payload directly for media-object evidence.
 *
 * @param array<string,mixed> $metadata      Generated attachment metadata.
 * @param int                 $attachment_id Attachment ID.
 * @return array<string,mixed>
 */
function omphalos_filter_webp_attachment_metadata( array $metadata, int $attachment_id ) : array {
	if ( 'image/webp' !== get_post_mime_type( $attachment_id ) ) {
		return $metadata;
	}

	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! is_readable( $file ) ) {
		return $metadata;
	}

	$exif = omphalos_read_webp_exif( $file );
	if ( empty( $exif ) ) {
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

	$metadata['omphalos_webp_exif'] = $exif;

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'omphalos_filter_webp_attachment_metadata', 10, 2 );

/**
 * Supplement Opus-in-Ogg attachment metadata with Vorbis comments.
 *
 * getID3 exposes the stream data and embedded image, but this fixture's Opus
 * `artist` and `album` fields are empty after WordPress import despite being
 * present in the `OpusTags` packet. Pilot reads that packet directly so the
 * attachment object can surface the original media tags.
 *
 * @param array<string,mixed> $metadata      Generated attachment metadata.
 * @param int                 $attachment_id Attachment ID.
 * @return array<string,mixed>
 */
function omphalos_filter_opus_attachment_metadata( array $metadata, int $attachment_id ) : array {
	$mime_type = get_post_mime_type( $attachment_id );
	if ( 'audio/ogg' !== $mime_type && 'opus' !== ( $metadata['dataformat'] ?? '' ) ) {
		return $metadata;
	}

	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! is_readable( $file ) ) {
		return $metadata;
	}

	$comments = omphalos_read_opus_tags( $file );
	if ( empty( $comments ) ) {
		return $metadata;
	}

	$map = array(
		'album'       => 'album',
		'artist'      => 'artist',
		'title'       => 'title',
		'genre'       => 'genre',
		'date'        => 'year',
		'lyrics-eng'  => 'text',
		'description' => 'description',
	);

	foreach ( $map as $comment_key => $metadata_key ) {
		if ( ! empty( $comments[ $comment_key ] ) ) {
			$metadata[ $metadata_key ] = $comments[ $comment_key ];
		}
	}

	$metadata['omphalos_opus_tags'] = $comments;

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'omphalos_filter_opus_attachment_metadata', 11, 2 );

/**
 * Read Vorbis comments from the first OPUS `OpusTags` packet in an Ogg file.
 *
 * @param string $file OPUS/Ogg file path.
 * @return array<string,string>
 */
function omphalos_read_opus_tags( string $file ) : array {
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

	$vendor_length = omphalos_le_uint32( $data, $cursor );
	$cursor       += 4 + $vendor_length;
	if ( $cursor + 4 > $length ) {
		return array();
	}

	$comment_count = omphalos_le_uint32( $data, $cursor );
	$cursor       += 4;
	$comments      = array();

	for ( $i = 0; $i < $comment_count; $i++ ) {
		if ( $cursor + 4 > $length ) {
			break;
		}

		$comment_length = omphalos_le_uint32( $data, $cursor );
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

function omphalos_le_uint32( string $data, int $offset ) : int {
	$bytes = substr( $data, $offset, 4 );
	if ( 4 > strlen( $bytes ) ) {
		return 0;
	}
	return unpack( 'V', $bytes )[1];
}

/**
 * Read selected EXIF tags from a WEBP RIFF EXIF chunk.
 *
 * @param string $file WEBP file path.
 * @return array<string,mixed>
 */
function omphalos_read_webp_exif( string $file ) : array {
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
			return omphalos_parse_tiff_exif( $chunk_data );
		}

		$offset += 8 + $chunk_size + ( $chunk_size % 2 );
	}

	return array();
}

/**
 * Parse the TIFF payload contained in a WEBP EXIF chunk.
 *
 * @param string $data TIFF bytes.
 * @return array<string,mixed>
 */
function omphalos_parse_tiff_exif( string $data ) : array {
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

	$magic = omphalos_tiff_uint16( $data, 2, $endian );
	if ( 42 !== $magic ) {
		return array();
	}

	$ifd_offset = omphalos_tiff_uint32( $data, 4, $endian );
	$ifd0       = omphalos_parse_tiff_ifd( $data, $ifd_offset, $endian );
	$exif_ifd   = array();
	if ( ! empty( $ifd0['ExifIFDPointer'] ) ) {
		$exif_ifd = omphalos_parse_tiff_ifd( $data, (int) $ifd0['ExifIFDPointer'], $endian );
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
 * Parse a TIFF IFD.
 *
 * @param string $data   TIFF bytes.
 * @param int    $offset IFD offset.
 * @param string $endian Byte order.
 * @return array<string,mixed>
 */
function omphalos_parse_tiff_ifd( string $data, int $offset, string $endian ) : array {
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

	$count = omphalos_tiff_uint16( $data, $offset, $endian );
	$out   = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$entry_offset = $offset + 2 + ( 12 * $i );
		if ( $entry_offset + 12 > strlen( $data ) ) {
			break;
		}

		$tag = omphalos_tiff_uint16( $data, $entry_offset, $endian );
		if ( ! isset( $tag_names[ $tag ] ) ) {
			continue;
		}

		$type  = omphalos_tiff_uint16( $data, $entry_offset + 2, $endian );
		$num   = omphalos_tiff_uint32( $data, $entry_offset + 4, $endian );
		$value = omphalos_tiff_value( $data, $type, $num, substr( $data, $entry_offset + 8, 4 ), $endian );
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
function omphalos_tiff_value( string $data, int $type, int $count, string $value_bytes, string $endian ) {
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
		$offset = omphalos_tiff_uint32( $value_bytes, 0, $endian );
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
				$values[] = omphalos_tiff_uint16( $chunk, 0, $endian );
				break;
			case 4:
				$values[] = omphalos_tiff_uint32( $chunk, 0, $endian );
				break;
			case 5:
				$values[] = omphalos_tiff_rational( $chunk, $endian, false );
				break;
			case 9:
				$values[] = omphalos_tiff_int32( $chunk, 0, $endian );
				break;
			case 10:
				$values[] = omphalos_tiff_rational( $chunk, $endian, true );
				break;
			default:
				$values[] = bin2hex( $chunk );
		}
	}

	return 1 === count( $values ) ? $values[0] : $values;
}

/**
 * Convert TIFF rational bytes to decimal string.
 *
 * @param string $chunk 8-byte numerator/denominator pair.
 * @param string $endian Byte order.
 * @param bool   $signed Signed numerator/denominator.
 * @return string
 */
function omphalos_tiff_rational( string $chunk, string $endian, bool $signed ) : string {
	$numerator   = $signed ? omphalos_tiff_int32( $chunk, 0, $endian ) : omphalos_tiff_uint32( $chunk, 0, $endian );
	$denominator = $signed ? omphalos_tiff_int32( $chunk, 4, $endian ) : omphalos_tiff_uint32( $chunk, 4, $endian );

	if ( 0 === $denominator ) {
		return (string) $numerator;
	}

	return rtrim( rtrim( sprintf( '%.12F', $numerator / $denominator ), '0' ), '.' );
}

function omphalos_tiff_uint16( string $data, int $offset, string $endian ) : int {
	$bytes = substr( $data, $offset, 2 );
	if ( 2 > strlen( $bytes ) ) {
		return 0;
	}
	return unpack( 'little' === $endian ? 'v' : 'n', $bytes )[1];
}

function omphalos_tiff_uint32( string $data, int $offset, string $endian ) : int {
	$bytes = substr( $data, $offset, 4 );
	if ( 4 > strlen( $bytes ) ) {
		return 0;
	}
	return unpack( 'little' === $endian ? 'V' : 'N', $bytes )[1];
}

function omphalos_tiff_int32( string $data, int $offset, string $endian ) : int {
	$value = omphalos_tiff_uint32( $data, $offset, $endian );
	return $value > 0x7fffffff ? $value - 0x100000000 : $value;
}

/**
 * Find the cover-image attachment generated from embedded audio artwork.
 *
 * Prefer explicit WordPress links first (`_thumbnail_id` and metadata image
 * attachment IDs), then fall back to the filename pattern WordPress uses when
 * it extracts embedded OPUS/MP3 cover art as a sibling image attachment.
 *
 * @param int $attachment_id Audio attachment ID.
 * @return int Cover-image attachment ID or 0.
 */
function omphalos_get_audio_cover_attachment_id( int $attachment_id ) : int {
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

	$candidates = array_filter(
		array_unique(
			array(
				$directory . $basename . '-' . $extension . '-image.jpg',
				$directory . $basename . '-image.jpg',
			)
		)
	);

	$cover_posts = get_posts(
		array(
			'fields'         => 'ids',
			'meta_key'       => '_wp_attached_file',
			'meta_value'     => $candidates,
			'meta_compare'   => 'IN',
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
			'post_type'      => 'attachment',
			'posts_per_page' => 1,
		)
	);

	return $cover_posts ? (int) $cover_posts[0] : 0;
}


