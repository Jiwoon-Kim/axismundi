<?php
/**
 * Axismundi Pilot — functions.php
 *
 * v3.6.0 Phase 2A scaffold. This theme consumes the Axismundi public surface
 * as a WordPress block theme proof without registering custom blocks.
 *
 * @package Axismundi_Pilot
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'AXISMUNDI_PILOT_VERSION' ) ) {
	define( 'AXISMUNDI_PILOT_VERSION', '0.2.2-pilot' );
}

/**
 * Resolve a theme-relative asset path only when Phase 2B has copied it.
 *
 * @param string $relative_path Theme-relative path.
 * @return string|null Theme URI or null when the asset is not present yet.
 */
function axismundi_pilot_asset_uri( string $relative_path ) : ?string {
	$relative_path = ltrim( $relative_path, '/' );
	$absolute_path = get_template_directory() . '/' . $relative_path;

	if ( ! file_exists( $absolute_path ) ) {
		return null;
	}

	return get_template_directory_uri() . '/' . $relative_path;
}

/**
 * Theme setup.
 */
function axismundi_pilot_setup() : void {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support(
		'html5',
		array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script' )
	);

	load_theme_textdomain( 'axismundi-pilot', get_template_directory() . '/languages' );

	$editor_styles = array_filter(
		array(
			file_exists( get_template_directory() . '/assets/styles/fonts.css' ) ? 'assets/styles/fonts.css' : null,
			file_exists( get_template_directory() . '/assets/styles/tokens.ref.css' ) ? 'assets/styles/tokens.ref.css' : null,
			file_exists( get_template_directory() . '/assets/styles/tokens.sys.light.css' ) ? 'assets/styles/tokens.sys.light.css' : null,
			file_exists( get_template_directory() . '/assets/styles/tokens.sys.core.css' ) ? 'assets/styles/tokens.sys.core.css' : null,
			file_exists( get_template_directory() . '/assets/styles/tokens.comp.css' ) ? 'assets/styles/tokens.comp.css' : null,
			file_exists( get_template_directory() . '/assets/styles/tokens.sys.dark.css' ) ? 'assets/styles/tokens.sys.dark.css' : null,
			file_exists( get_template_directory() . '/assets/styles/wp-preset.bridge.css' ) ? 'assets/styles/wp-preset.bridge.css' : null,
			file_exists( get_template_directory() . '/assets/styles/wp-custom.bridge.css' ) ? 'assets/styles/wp-custom.bridge.css' : null,
			file_exists( get_template_directory() . '/assets/styles/tokens.css' ) ? 'assets/styles/tokens.css' : null,
			file_exists( get_template_directory() . '/assets/styles/base.css' ) ? 'assets/styles/base.css' : null,
			file_exists( get_template_directory() . '/assets/styles/icons.css' ) ? 'assets/styles/icons.css' : null,
			file_exists( get_template_directory() . '/assets/styles/components.css' ) ? 'assets/styles/components.css' : null,
			file_exists( get_template_directory() . '/assets/styles/blocks.css' ) ? 'assets/styles/blocks.css' : null,
			file_exists( get_template_directory() . '/assets/styles/prose.css' ) ? 'assets/styles/prose.css' : null,
			file_exists( get_template_directory() . '/assets/styles/pilot-block-bridge.css' ) ? 'assets/styles/pilot-block-bridge.css' : null,
		)
	);

	if ( ! empty( $editor_styles ) ) {
		add_editor_style( array_values( $editor_styles ) );
	}
}
add_action( 'after_setup_theme', 'axismundi_pilot_setup' );

// ---------------------------------------------------------------------------
// Theme colour-scheme — cookie / user-meta helpers
// ---------------------------------------------------------------------------

/**
 * Cookie and user-meta key for the stored colour scheme.
 * Must match the JS constant `cookieName` passed via wp_interactivity_state().
 */
define( 'AXISMUNDI_PILOT_SCHEME_KEY', 'axismundi-pilot-theme' );

/**
 * Register the colour-scheme user meta so logged-in users can persist their
 * preference server-side and it is accessible through the REST API.
 */
function axismundi_pilot_register_scheme_meta() : void {
	$sanitize = static fn( string $value ) : string =>
		in_array( $value, array( 'light', 'dark', 'auto' ), true ) ? $value : '';

	register_meta( 'user', AXISMUNDI_PILOT_SCHEME_KEY, array(
		'label'             => __( 'Colour Scheme', 'axismundi-pilot' ),
		'description'       => __( 'Stores the preferred colour scheme for the Axismundi Pilot site.', 'axismundi-pilot' ),
		'default'           => '',
		'sanitize_callback' => $sanitize,
		'show_in_rest'      => true,
		'single'            => true,
		'type'              => 'string',
	) );
}
add_action( 'init', 'axismundi_pilot_register_scheme_meta' );

/**
 * Return the user's current colour scheme preference.
 *
 * Priority: cookie → user meta (logged-in) → 'auto'.
 *
 * @return 'light'|'dark'|'auto'
 */
function axismundi_pilot_get_color_scheme() : string {
	$key   = AXISMUNDI_PILOT_SCHEME_KEY;
	$valid = array( 'light', 'dark', 'auto' );

	// Cookie is checked first: JS writes the cookie synchronously on every
	// click, whereas the user-meta REST write is async and may not have
	// completed before the next page load.  Giving the cookie higher priority
	// means SSR always reflects the most-recent user choice, with user meta
	// serving as a cross-device / cross-browser fallback when no cookie exists.
	if ( isset( $_COOKIE[ $key ] ) ) {
		$stored = sanitize_key( wp_unslash( $_COOKIE[ $key ] ) );
		if ( $stored && in_array( $stored, $valid, true ) ) {
			return $stored;
		}
	}

	if ( is_user_logged_in() ) {
		$stored = get_user_meta( get_current_user_id(), $key, true );
		if ( $stored && in_array( $stored, $valid, true ) ) {
			return $stored;
		}
	}

	return 'auto';
}

/**
 * Add the explicit theme state to the Pilot front-end root element.
 *
 * Reads the user's stored preference from cookie / user meta so the correct
 * data-theme is rendered server-side — eliminating the flash of unstyled
 * content that occurred when the previous implementation always wrote "auto"
 * and relied on JavaScript to correct it.
 *
 * Pilot-only BACKLOG #22 evidence. Do not copy this filter into distributable
 * themes without an explicit distributable skeleton bootstrap decision.
 *
 * @param string $output Existing language attributes.
 * @return string Language attributes with the resolved data-theme value.
 */
function axismundi_pilot_language_attributes( string $output ) : string {
	if ( is_admin() || false !== strpos( $output, 'data-theme=' ) ) {
		return $output;
	}

	$scheme = axismundi_pilot_get_color_scheme();
	return trim( $output . ' data-theme="' . esc_attr( $scheme ) . '"' );
}
add_filter( 'language_attributes', 'axismundi_pilot_language_attributes', 20 );

/**
 * Enqueue the Interactivity API colour-scheme script module and set the
 * initial server-side state for the theme switcher.
 *
 * The script module is only registered when the compiled asset file is
 * present (i.e. after `npm run build` has been executed).
 */
function axismundi_pilot_enqueue_color_scheme() : void {
	$asset_file = get_template_directory() . '/public/js/color-scheme.asset.php';

	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = include $asset_file;

	wp_enqueue_script_module(
		'axismundi-pilot-color-scheme',
		get_template_directory_uri() . '/public/js/color-scheme.js',
		$asset['dependencies'],
		$asset['version']
	);

	// Enqueue wp-api-fetch only when the user is logged in so the meta
	// endpoint call in the JS module has its transport available.
	if ( is_user_logged_in() ) {
		wp_enqueue_script( 'wp-api-fetch' );
	}

	wp_interactivity_state( 'axismundi-pilot/color-scheme', array(
		'colorScheme'  => axismundi_pilot_get_color_scheme(),
		'userId'       => get_current_user_id(),
		'cookieName'   => AXISMUNDI_PILOT_SCHEME_KEY,
		'cookiePath'   => COOKIEPATH,
		'cookieDomain' => (string) COOKIE_DOMAIN,
	) );
}
add_action( 'wp_enqueue_scripts', 'axismundi_pilot_enqueue_color_scheme' );

// ---------------------------------------------------------------------------
// Attachment templates — dynamic media partials
// ---------------------------------------------------------------------------

/**
 * Enable front-end attachment pages in the Pilot dev environment.
 *
 * WordPress disables attachment pages for new installs. The Pilot keeps them
 * enabled so media object templates can be tested. Do not copy this override
 * into a distributable theme without an explicit product decision.
 *
 * @return bool
 */
function axismundi_pilot_enable_attachment_pages() : bool {
	return true;
}
add_filter( 'pre_option_wp_attachment_pages_enabled', 'axismundi_pilot_enable_attachment_pages' );

// Attachment media is rendered by type-specific partials below.
remove_filter( 'the_content', 'prepend_attachment' );

/**
 * Register Pilot attachment metadata fields.
 *
 * `axismundi_video_tracks` stores a JSON array of WebVTT track definitions for
 * video attachment pages. Editing UI is intentionally deferred; during the
 * Pilot phase the field can be managed through WP-CLI or Custom Fields.
 *
 * @return void
 */
function axismundi_pilot_register_attachment_meta() : void {
	register_post_meta( 'attachment', 'axismundi_video_tracks', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'axismundi_pilot_sanitize_video_tracks_meta',
		'auth_callback'     => static function() : bool {
			return current_user_can( 'edit_posts' );
		},
	) );
}
add_action( 'init', 'axismundi_pilot_register_attachment_meta' );

/**
 * Render the media portion of attachment pages through a PHP partial hierarchy.
 *
 * @param string   $block_content Existing Post Content block output.
 * @param array    $block         Parsed block data.
 * @param WP_Block $instance      Block instance containing context.
 * @return string Filtered block output.
 */
function axismundi_pilot_render_attachment_block( string $block_content, array $block, WP_Block $instance ) : string {
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
add_filter( 'render_block_core/post-content', 'axismundi_pilot_render_attachment_block', 10, 3 );

/**
 * Render attachment metadata as a compact definition list plus raw details.
 *
 * @param array<string,mixed> $items Metadata label/value pairs.
 * @param array<string,mixed> $raw   Raw metadata groups to display.
 */
function axismundi_pilot_render_attachment_meta( array $items, array $raw = array() ) : void {
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

	echo '<section class="ax-attachment-meta" aria-label="' . esc_attr__( 'Attachment metadata', 'axismundi-pilot' ) . '">';

	if ( ! empty( $items ) ) {
		echo '<dl class="ax-attachment-meta__summary">';
		foreach ( $items as $label => $value ) {
			echo '<dt>' . esc_html( (string) $label ) . '</dt>';
			echo '<dd>' . esc_html( axismundi_pilot_format_attachment_meta_value( $value ) ) . '</dd>';
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
function axismundi_pilot_format_attachment_meta_value( $value ) : string {
	if ( is_bool( $value ) ) {
		return $value ? __( 'true', 'axismundi-pilot' ) : __( 'false', 'axismundi-pilot' );
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
function axismundi_pilot_get_attachment_common_meta( int $attachment_id ) : array {
	$file_path = get_attached_file( $attachment_id );
	$mime_type = get_post_mime_type( $attachment_id );

	return array(
		__( 'Attachment ID', 'axismundi-pilot' ) => $attachment_id,
		__( 'MIME type', 'axismundi-pilot' )     => $mime_type,
		__( 'File name', 'axismundi-pilot' )     => $file_path ? wp_basename( $file_path ) : '',
		__( 'File size', 'axismundi-pilot' )     => ( $file_path && file_exists( $file_path ) ) ? size_format( filesize( $file_path ) ) : '',
	);
}

/**
 * Sanitize the JSON video track metadata field.
 *
 * @param mixed $value Raw meta value.
 * @return string JSON-encoded track list.
 */
function axismundi_pilot_sanitize_video_tracks_meta( $value ) : string {
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
function axismundi_pilot_get_video_tracks( int $attachment_id ) : array {
	$raw = get_post_meta( $attachment_id, 'axismundi_video_tracks', true );
	if ( ! $raw ) {
		return array();
	}

	$tracks = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
	if ( ! is_array( $tracks ) ) {
		return array();
	}

	$sanitized = axismundi_pilot_sanitize_video_tracks_meta( $tracks );
	$decoded   = $sanitized ? json_decode( $sanitized, true ) : array();

	return is_array( $decoded ) ? $decoded : array();
}

/**
 * Render WebVTT track tags for a video attachment.
 *
 * @param array<int,array<string,mixed>> $tracks Track definitions.
 * @return string HTML track tags.
 */
function axismundi_pilot_render_video_tracks( array $tracks ) : string {
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
function axismundi_pilot_filter_webp_attachment_metadata( array $metadata, int $attachment_id ) : array {
	if ( 'image/webp' !== get_post_mime_type( $attachment_id ) ) {
		return $metadata;
	}

	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! is_readable( $file ) ) {
		return $metadata;
	}

	$exif = axismundi_pilot_read_webp_exif( $file );
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

	$metadata['axismundi_webp_exif'] = $exif;

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'axismundi_pilot_filter_webp_attachment_metadata', 10, 2 );

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
function axismundi_pilot_filter_opus_attachment_metadata( array $metadata, int $attachment_id ) : array {
	$mime_type = get_post_mime_type( $attachment_id );
	if ( 'audio/ogg' !== $mime_type && 'opus' !== ( $metadata['dataformat'] ?? '' ) ) {
		return $metadata;
	}

	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! is_readable( $file ) ) {
		return $metadata;
	}

	$comments = axismundi_pilot_read_opus_tags( $file );
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

	$metadata['axismundi_opus_tags'] = $comments;

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'axismundi_pilot_filter_opus_attachment_metadata', 11, 2 );

/**
 * Read Vorbis comments from the first OPUS `OpusTags` packet in an Ogg file.
 *
 * @param string $file OPUS/Ogg file path.
 * @return array<string,string>
 */
function axismundi_pilot_read_opus_tags( string $file ) : array {
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

	$vendor_length = axismundi_pilot_le_uint32( $data, $cursor );
	$cursor       += 4 + $vendor_length;
	if ( $cursor + 4 > $length ) {
		return array();
	}

	$comment_count = axismundi_pilot_le_uint32( $data, $cursor );
	$cursor       += 4;
	$comments      = array();

	for ( $i = 0; $i < $comment_count; $i++ ) {
		if ( $cursor + 4 > $length ) {
			break;
		}

		$comment_length = axismundi_pilot_le_uint32( $data, $cursor );
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

function axismundi_pilot_le_uint32( string $data, int $offset ) : int {
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
function axismundi_pilot_read_webp_exif( string $file ) : array {
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
			return axismundi_pilot_parse_tiff_exif( $chunk_data );
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
function axismundi_pilot_parse_tiff_exif( string $data ) : array {
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

	$magic = axismundi_pilot_tiff_uint16( $data, 2, $endian );
	if ( 42 !== $magic ) {
		return array();
	}

	$ifd_offset = axismundi_pilot_tiff_uint32( $data, 4, $endian );
	$ifd0       = axismundi_pilot_parse_tiff_ifd( $data, $ifd_offset, $endian );
	$exif_ifd   = array();
	if ( ! empty( $ifd0['ExifIFDPointer'] ) ) {
		$exif_ifd = axismundi_pilot_parse_tiff_ifd( $data, (int) $ifd0['ExifIFDPointer'], $endian );
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
function axismundi_pilot_parse_tiff_ifd( string $data, int $offset, string $endian ) : array {
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

	$count = axismundi_pilot_tiff_uint16( $data, $offset, $endian );
	$out   = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$entry_offset = $offset + 2 + ( 12 * $i );
		if ( $entry_offset + 12 > strlen( $data ) ) {
			break;
		}

		$tag = axismundi_pilot_tiff_uint16( $data, $entry_offset, $endian );
		if ( ! isset( $tag_names[ $tag ] ) ) {
			continue;
		}

		$type  = axismundi_pilot_tiff_uint16( $data, $entry_offset + 2, $endian );
		$num   = axismundi_pilot_tiff_uint32( $data, $entry_offset + 4, $endian );
		$value = axismundi_pilot_tiff_value( $data, $type, $num, substr( $data, $entry_offset + 8, 4 ), $endian );
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
function axismundi_pilot_tiff_value( string $data, int $type, int $count, string $value_bytes, string $endian ) {
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
		$offset = axismundi_pilot_tiff_uint32( $value_bytes, 0, $endian );
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
				$values[] = axismundi_pilot_tiff_uint16( $chunk, 0, $endian );
				break;
			case 4:
				$values[] = axismundi_pilot_tiff_uint32( $chunk, 0, $endian );
				break;
			case 5:
				$values[] = axismundi_pilot_tiff_rational( $chunk, $endian, false );
				break;
			case 9:
				$values[] = axismundi_pilot_tiff_int32( $chunk, 0, $endian );
				break;
			case 10:
				$values[] = axismundi_pilot_tiff_rational( $chunk, $endian, true );
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
function axismundi_pilot_tiff_rational( string $chunk, string $endian, bool $signed ) : string {
	$numerator   = $signed ? axismundi_pilot_tiff_int32( $chunk, 0, $endian ) : axismundi_pilot_tiff_uint32( $chunk, 0, $endian );
	$denominator = $signed ? axismundi_pilot_tiff_int32( $chunk, 4, $endian ) : axismundi_pilot_tiff_uint32( $chunk, 4, $endian );

	if ( 0 === $denominator ) {
		return (string) $numerator;
	}

	return rtrim( rtrim( sprintf( '%.12F', $numerator / $denominator ), '0' ), '.' );
}

function axismundi_pilot_tiff_uint16( string $data, int $offset, string $endian ) : int {
	$bytes = substr( $data, $offset, 2 );
	if ( 2 > strlen( $bytes ) ) {
		return 0;
	}
	return unpack( 'little' === $endian ? 'v' : 'n', $bytes )[1];
}

function axismundi_pilot_tiff_uint32( string $data, int $offset, string $endian ) : int {
	$bytes = substr( $data, $offset, 4 );
	if ( 4 > strlen( $bytes ) ) {
		return 0;
	}
	return unpack( 'little' === $endian ? 'V' : 'N', $bytes )[1];
}

function axismundi_pilot_tiff_int32( string $data, int $offset, string $endian ) : int {
	$value = axismundi_pilot_tiff_uint32( $data, $offset, $endian );
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
function axismundi_pilot_get_audio_cover_attachment_id( int $attachment_id ) : int {
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

/**
 * Enqueue copied Pilot assets when Phase 2B has produced them.
 */
function axismundi_pilot_enqueue_assets() : void {
	$styles = array(
		'axismundi-pilot-fonts'            => array( 'assets/styles/fonts.css', array() ),
		'axismundi-pilot-tokens-ref'       => array( 'assets/styles/tokens.ref.css', array( 'axismundi-pilot-fonts' ) ),
		'axismundi-pilot-tokens-sys-light' => array( 'assets/styles/tokens.sys.light.css', array( 'axismundi-pilot-tokens-ref' ) ),
		'axismundi-pilot-tokens-sys-core'  => array( 'assets/styles/tokens.sys.core.css', array( 'axismundi-pilot-tokens-sys-light' ) ),
		'axismundi-pilot-tokens-comp'      => array( 'assets/styles/tokens.comp.css', array( 'axismundi-pilot-tokens-sys-core' ) ),
		'axismundi-pilot-tokens-sys-dark'  => array( 'assets/styles/tokens.sys.dark.css', array( 'axismundi-pilot-tokens-comp' ) ),
		'axismundi-pilot-wp-preset'        => array( 'assets/styles/wp-preset.bridge.css', array( 'axismundi-pilot-tokens-sys-dark' ) ),
		'axismundi-pilot-wp-custom'        => array( 'assets/styles/wp-custom.bridge.css', array( 'axismundi-pilot-wp-preset' ) ),
		'axismundi-pilot-tokens'           => array( 'assets/styles/tokens.css', array( 'axismundi-pilot-wp-custom' ) ),
		'axismundi-pilot-base'             => array( 'assets/styles/base.css', array( 'axismundi-pilot-tokens' ) ),
		'axismundi-pilot-icons'            => array( 'assets/styles/icons.css', array( 'axismundi-pilot-fonts', 'axismundi-pilot-tokens' ) ),
		'axismundi-pilot-components'       => array( 'assets/styles/components.css', array( 'axismundi-pilot-base', 'axismundi-pilot-icons' ) ),
		'axismundi-pilot-blocks'           => array( 'assets/styles/blocks.css', array( 'axismundi-pilot-components' ) ),
		'axismundi-pilot-prose'            => array( 'assets/styles/prose.css', array( 'axismundi-pilot-blocks' ) ),
		'axismundi-pilot-bridge'           => array( 'assets/styles/pilot-block-bridge.css', array( 'axismundi-pilot-prose' ) ),
	);

	foreach ( $styles as $handle => $style ) {
		$uri = axismundi_pilot_asset_uri( $style[0] );

		if ( null === $uri ) {
			continue;
		}

		wp_enqueue_style( $handle, $uri, $style[1], AXISMUNDI_PILOT_VERSION );
	}

	$bridge_script = 'assets/scripts/pilot-block-bridge.js';
	if ( file_exists( get_template_directory() . '/' . $bridge_script ) ) {
		wp_enqueue_script(
			'axismundi-pilot-block-bridge',
			axismundi_pilot_asset_uri( $bridge_script ),
			array(),
			AXISMUNDI_PILOT_VERSION,
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'axismundi_pilot_enqueue_assets' );

/**
 * Enqueue icon font assets for editor-side previews.
 *
 * `add_editor_style()` covers the iframe canvas. The Custom HTML block can also
 * render previews in editor chrome, so the icon font itself must be available
 * through block editor assets as well. Keep this intentionally narrow to avoid
 * leaking the full Pilot component stylesheet into WordPress admin UI.
 */
function axismundi_pilot_enqueue_editor_icon_assets() : void {
	$fonts = axismundi_pilot_asset_uri( 'assets/styles/fonts.css' );
	if ( null !== $fonts ) {
		wp_enqueue_style( 'axismundi-pilot-editor-fonts', $fonts, array(), AXISMUNDI_PILOT_VERSION );
	}

	$icons = axismundi_pilot_asset_uri( 'assets/styles/icons.css' );
	if ( null !== $icons ) {
		wp_enqueue_style( 'axismundi-pilot-editor-icons', $icons, array( 'axismundi-pilot-editor-fonts' ), AXISMUNDI_PILOT_VERSION );
	}
}
add_action( 'enqueue_block_editor_assets', 'axismundi_pilot_enqueue_editor_icon_assets' );

/**
 * Register Axismundi's bundled text fonts as a WordPress Font Library collection.
 *
 * The theme also registers these families in theme.json with `fontFace.src`.
 * This collection gives the Font Library the same font-name-level definitions
 * through the WP 6.5+ `font_family_settings` contract.
 */
function axismundi_pilot_register_font_collection() : void {
	if ( ! function_exists( 'wp_register_font_collection' ) ) {
		return;
	}

	$font_families = array(
		array(
			'font_family_settings' => array(
				'fontFamily' => '"Roboto Flex", "Noto Sans KR", system-ui, sans-serif',
				'slug'       => 'roboto-flex',
				'name'       => 'Roboto Flex',
				'fontFace'   => array(
					array(
						'fontFamily'  => 'Roboto Flex',
						'fontStyle'   => 'oblique -10deg 0deg',
						'fontWeight'  => '100 1000',
						'fontStretch' => '25% 151%',
						'src'         => get_theme_file_uri( 'assets/fonts/roboto-flex/axismundi-roboto-flex.woff2' ),
					),
				),
			),
			'categories'           => array( 'sans-serif' ),
		),
		array(
			'font_family_settings' => array(
				'fontFamily' => '"Noto Sans KR", sans-serif',
				'slug'       => 'noto-sans-kr',
				'name'       => 'Noto Sans KR',
				'fontFace'   => array(
					array(
						'fontFamily' => 'Noto Sans KR',
						'fontStyle'  => 'normal',
						'fontWeight' => '100 900',
						'src'        => get_theme_file_uri( 'assets/fonts/noto-sans-kr/axismundi-noto-sans-kr.woff2' ),
					),
				),
			),
			'categories'           => array( 'sans-serif' ),
		),
		array(
			'font_family_settings' => array(
				'fontFamily' => '"Roboto Serif", "Noto Serif KR", Georgia, serif',
				'slug'       => 'roboto-serif',
				'name'       => 'Roboto Serif',
				'fontFace'   => array(
					array(
						'fontFamily'  => 'Roboto Serif',
						'fontStyle'   => 'normal',
						'fontWeight'  => '100 900',
						'fontStretch' => '50% 150%',
						'src'         => get_theme_file_uri( 'assets/fonts/roboto-serif/axismundi-roboto-serif.woff2' ),
					),
				),
			),
			'categories'           => array( 'serif' ),
		),
		array(
			'font_family_settings' => array(
				'fontFamily' => '"Noto Serif KR", serif',
				'slug'       => 'noto-serif-kr',
				'name'       => 'Noto Serif KR',
				'fontFace'   => array(
					array(
						'fontFamily' => 'Noto Serif KR',
						'fontStyle'  => 'normal',
						'fontWeight' => '100 900',
						'src'        => get_theme_file_uri( 'assets/fonts/noto-serif-kr/axismundi-noto-serif-kr.woff2' ),
					),
				),
			),
			'categories'           => array( 'serif' ),
		),
		array(
			'font_family_settings' => array(
				'fontFamily' => '"Roboto Mono", monospace',
				'slug'       => 'roboto-mono',
				'name'       => 'Roboto Mono',
				'fontFace'   => array(
					array(
						'fontFamily' => 'Roboto Mono',
						'fontStyle'  => 'normal',
						'fontWeight' => '100 700',
						'src'        => get_theme_file_uri( 'assets/fonts/roboto-mono/axismundi-roboto-mono.woff2' ),
					),
					array(
						'fontFamily' => 'Roboto Mono',
						'fontStyle'  => 'italic',
						'fontWeight' => '100 700',
						'src'        => get_theme_file_uri( 'assets/fonts/roboto-mono/axismundi-roboto-mono-italic.woff2' ),
					),
				),
			),
			'categories'           => array( 'monospace' ),
		),
	);

	wp_register_font_collection(
		'axismundi-pilot-fonts',
		array(
			'name'          => _x( 'Axismundi Pilot Fonts', 'Font collection name', 'axismundi-pilot' ),
			'description'   => _x( 'Bundled Roboto and Noto Korean font families for the Axismundi Pilot theme.', 'Font collection description', 'axismundi-pilot' ),
			'font_families' => $font_families,
			'categories'    => array(
				array(
					'name' => _x( 'Sans Serif', 'Font category name', 'axismundi-pilot' ),
					'slug' => 'sans-serif',
				),
				array(
					'name' => _x( 'Serif', 'Font category name', 'axismundi-pilot' ),
					'slug' => 'serif',
				),
				array(
					'name' => _x( 'Monospace', 'Font category name', 'axismundi-pilot' ),
					'slug' => 'monospace',
				),
			),
		)
	);
}
add_action( 'init', 'axismundi_pilot_register_font_collection' );

/**
 * Register block style variants used by the Pilot patterns.
 *
 * These are style registrations for WordPress core blocks only. The Pilot
 * theme intentionally does not register custom blocks.
 */
function axismundi_pilot_register_block_styles() : void {
	$styles = array(
		'core/button'    => array(
			'tonal'    => __( 'Tonal', 'axismundi-pilot' ),
			'elevated' => __( 'Elevated', 'axismundi-pilot' ),
			'text'     => __( 'Text', 'axismundi-pilot' ),
		),
		'core/group'     => array(
			'card-filled'   => __( 'Card filled', 'axismundi-pilot' ),
			'card-elevated' => __( 'Card elevated', 'axismundi-pilot' ),
			'card-outlined' => __( 'Card outlined', 'axismundi-pilot' ),
		),
		'core/list'      => array(
			'list-segmented' => __( 'Segmented list', 'axismundi-pilot' ),
		),
		'core/separator' => array(
			'divider-inset'        => __( 'Inset divider', 'axismundi-pilot' ),
			'divider-middle-inset' => __( 'Middle inset divider', 'axismundi-pilot' ),
		),
		'core/search'    => array(
			'filled-search' => __( 'Filled search', 'axismundi-pilot' ),
		),
	);

	foreach ( $styles as $block_name => $block_styles ) {
		foreach ( $block_styles as $style_name => $label ) {
			register_block_style(
				$block_name,
				array(
					'name'  => $style_name,
					'label' => $label,
				)
			);
		}
	}
}
add_action( 'init', 'axismundi_pilot_register_block_styles' );

/**
 * Register Pilot pattern categories.
 */
function axismundi_pilot_register_pattern_categories() : void {
	register_block_pattern_category(
		'axismundi-showcase',
		array( 'label' => __( 'Axismundi Showcase', 'axismundi-pilot' ) )
	);
	register_block_pattern_category(
		'axismundi-composition',
		array( 'label' => __( 'Axismundi Composition', 'axismundi-pilot' ) )
	);
	register_block_pattern_category(
		'axismundi-prose',
		array( 'label' => __( 'Axismundi Prose', 'axismundi-pilot' ) )
	);
	register_block_pattern_category(
		'axismundi-dev',
		array( 'label' => __( 'Axismundi Dev', 'axismundi-pilot' ) )
	);
}
add_action( 'init', 'axismundi_pilot_register_pattern_categories' );
