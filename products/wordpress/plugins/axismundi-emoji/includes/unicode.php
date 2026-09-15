<?php
/**
 * Unicode emoji font fallback: profiles, the font manifest, and the front-end adapter.
 *
 * Design: docs/AXISMUNDI-EMOJI-UNICODE.md. The short version: a grapheme the browser
 * cannot draw natively, and that a registered emoji font covers, is wrapped so the font
 * draws it and Core's image fallback leaves it alone. Everything else stays exactly as
 * Core handles it today, and Core's Twemoji fallback remains the last resort.
 *
 * This is deliberately separate from the custom emoji code in this plugin. A Unicode
 * grapheme has no registry row, no review state and no ActivityPub declaration; the only
 * question here is how it is drawn.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_EMOJI_UNICODE_SCRIPT_HANDLE    = 'axismundi-emoji-unicode';
const AXISMUNDI_EMOJI_UNICODE_STYLE_HANDLE     = 'axismundi-emoji-unicode';
const AXISMUNDI_EMOJI_UNICODE_CONFIG_ID        = 'axismundi-emoji-unicode-config';
const AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION = 'axismundi_emoji_unicode_rendering';
const AXISMUNDI_EMOJI_UNICODE_FAMILY           = 'Noto Color Emoji';
const AXISMUNDI_EMOJI_UNICODE_MANIFEST         = 'assets/fonts/noto-color-emoji/manifest.json';
const AXISMUNDI_EMOJI_UNICODE_COLLECTION       = 'axismundi-emoji';

/**
 * Capability profiles: which graphemes belong together for detection and wrapping.
 *
 * Sequences are code point lists rather than literal strings, so neither PHP nor the
 * JSON handed to the browser carries escape notation that a tool could decode in transit.
 *
 * `flags` is split in two because the two halves fail independently: on Windows Chromium
 * (measured 2026-09-15) country flags are missing while the England tag sequence draws a
 * plain black flag, and Core's replacement pattern covers only the former (Trac candidate C3).
 *
 * A profile is judged as a whole, so its probe must include every member that can fail on
 * its own. A browser drawing England but not Scotland would otherwise be declared native
 * for all three.
 *
 * @return array<string,array{match:string,probe:array<int,int[]>,sequences?:array<int,int[]>}>
 */
function axismundi_emoji_unicode_profiles() : array {
	$england  = array( 0x1F3F4, 0xE0067, 0xE0062, 0xE0065, 0xE006E, 0xE0067, 0xE007F );
	$scotland = array( 0x1F3F4, 0xE0067, 0xE0062, 0xE0073, 0xE0063, 0xE0074, 0xE007F );
	$wales    = array( 0x1F3F4, 0xE0067, 0xE0062, 0xE0077, 0xE006C, 0xE0073, 0xE007F );

	return array(
		'flags-country'     => array(
			'match' => 'regional-indicator-pair',
			'modes' => array( 'auto' ),
			// Sark, the country flag platforms add last; Core probes the same one.
			'probe' => array( array( 0x1F1E8, 0x1F1F6 ) ),
		),
		'flags-subdivision' => array(
			'match'     => 'tag-sequence',
			'modes'     => array( 'auto' ),
			'probe'     => array( $england, $scotland, $wales ),
			// Only the three RGI subdivision flags. Any other tag sequence is not a flag a
			// font is expected to have, and wrapping it would block Core for nothing.
			'sequences' => array( $england, $scotland, $wales ),
		),
		/*
		 * Every RGI emoji, for `font` mode: the site draws them all with its own font, whatever
		 * the browser could draw itself, so there is no native probe. The probe asks only
		 * whether the font works in this browser, and uses sequences, because a single code
		 * point would pass on a system emoji font even when ours failed to load.
		 */
		'emoji'             => array(
			'match' => 'rgi-emoji',
			'modes' => array( 'font' ),
			'probe' => array( array( 0x1F1E8, 0x1F1F6 ), array( 0x1F9D1, 0x200D, 0x1FA70 ) ),
		),
	);
}

/**
 * Whether a profile is used by a rendering mode: flags in `auto`, every emoji in `font`.
 *
 * @param string      $id   Profile id.
 * @param string|null $mode Rendering mode; the site's current one when null.
 * @return bool
 */
function axismundi_emoji_unicode_profile_in_mode( string $id, ?string $mode = null ) : bool {
	$profiles = axismundi_emoji_unicode_profiles();
	$mode     = $mode ?? axismundi_emoji_unicode_rendering_mode();
	return isset( $profiles[ $id ] ) && in_array( $mode, (array) ( $profiles[ $id ]['modes'] ?? array() ), true );
}

/**
 * Which profiles a font file on this site can draw, and how the browser should load it.
 *
 * Read from the manifest scripts/build-unicode-font-manifest.py writes next to the files.
 * Only entries whose file is actually present count. The filter lets another font source
 * (a self-hosted Twemoji COLR build, say) supply the same shape without this plugin
 * knowing about it.
 *
 * Each file loads under its own CSS alias (`fontFamily`), because the complete font and a
 * flags subset overlap: under one shared family name the browser could not be told which
 * face a flag should come from, and might fetch both. The font's internal name and the
 * Font Library family stay `Noto Color Emoji`.
 *
 * The URL carries the file's SHA-256 prefix, so a replaced file under the same name is
 * never served from a stale browser cache.
 *
 * @return array{family:string,profiles:array<string,array{url:string,unicodeRange:string,fontFamily:string,sha256:string}>}
 */
function axismundi_emoji_unicode_font_manifest() : array {
	static $shipped = null;
	$root = dirname( __DIR__ );
	if ( null === $shipped ) {
		$json    = is_readable( $root . '/' . AXISMUNDI_EMOJI_UNICODE_MANIFEST ) ? file_get_contents( $root . '/' . AXISMUNDI_EMOJI_UNICODE_MANIFEST ) : false;
		$data    = is_string( $json ) ? json_decode( $json, true ) : null;
		$family  = is_array( $data ) && is_string( $data['family'] ?? null ) ? $data['family'] : AXISMUNDI_EMOJI_UNICODE_FAMILY;
		$shipped = array(
			'family'   => $family,
			'profiles' => array(),
		);
		$files = is_array( $data ) && is_array( $data['files'] ?? null ) ? $data['files'] : array();
		if ( is_array( $data ) && 1 === (int) ( $data['schema'] ?? 0 ) && is_array( $data['profiles'] ?? null ) ) {
			foreach ( $data['profiles'] as $id => $profile ) {
				$file = is_array( $profile ) ? (string) ( $profile['file'] ?? '' ) : '';
				if ( '' === $file || str_contains( $file, '..' ) || ! is_readable( $root . '/' . $file ) ) {
					continue;
				}
				$meta = is_array( $files[ $file ] ?? null ) ? $files[ $file ] : array();
				$sha  = is_string( $meta['sha256'] ?? null ) ? $meta['sha256'] : '';
				$url  = plugins_url( $file, $root . '/axismundi-emoji.php' );
				$shipped['profiles'][ (string) $id ] = array(
					'url'          => '' !== $sha ? add_query_arg( 'ver', substr( $sha, 0, 12 ), $url ) : $url,
					'unicodeRange' => (string) ( $profile['unicodeRange'] ?? '' ),
					'fontFamily'   => is_string( $meta['alias'] ?? null ) && '' !== $meta['alias'] ? $meta['alias'] : $family,
					'sha256'       => $sha,
				);
			}
		}
	}

	/**
	 * Filters the emoji font coverage available to the Unicode adapter.
	 *
	 * @param array{family:string,profiles:array<string,array{url:string,unicodeRange?:string,fontFamily?:string,sha256?:string}>} $manifest
	 */
	$manifest = apply_filters( 'axismundi_emoji_unicode_font_manifest', $shipped );

	$family   = is_string( $manifest['family'] ?? null ) && '' !== $manifest['family'] ? $manifest['family'] : AXISMUNDI_EMOJI_UNICODE_FAMILY;
	$known    = axismundi_emoji_unicode_profiles();
	$profiles = array();
	foreach ( is_array( $manifest['profiles'] ?? null ) ? $manifest['profiles'] : array() as $id => $profile ) {
		if ( isset( $known[ $id ] ) && is_array( $profile ) && is_string( $profile['url'] ?? null ) && '' !== $profile['url'] ) {
			$profiles[ $id ] = array(
				'url'          => $profile['url'],
				'unicodeRange' => is_string( $profile['unicodeRange'] ?? null ) ? $profile['unicodeRange'] : '',
				'fontFamily'   => axismundi_emoji_unicode_css_name( is_string( $profile['fontFamily'] ?? null ) && '' !== $profile['fontFamily'] ? $profile['fontFamily'] : $family ),
				'sha256'       => is_string( $profile['sha256'] ?? null ) ? $profile['sha256'] : '',
			);
		}
	}
	return array(
		'family'   => axismundi_emoji_unicode_css_name( $family ),
		'profiles' => $profiles,
	);
}

/**
 * A family name safe to place inside double quotes in CSS and a canvas font string.
 *
 * @param string $name Family name.
 * @return string
 */
function axismundi_emoji_unicode_css_name( string $name ) : string {
	return trim( str_replace( array( '"', "'", '\\', '<', '>', ';', '{', '}' ), '', $name ) );
}

/**
 * The rendering policy (docs §1 D8).
 *
 * - `auto` (default): the browser draws what it can; the flags subset covers the profiles it
 *   cannot; WordPress's image fallback, if on, handles the rest.
 * - `font`: the site owns its emoji. Every RGI emoji is drawn with the complete bundled font,
 *   whatever the browser could draw itself; a browser that cannot use the font falls back
 *   to WordPress's images, if on.
 * - `core`: this layer is off, so an operator who sees a problem can return to WordPress's
 *   own behaviour in one step without deactivating the custom emoji half of the plugin.
 *
 * @return string
 */
function axismundi_emoji_unicode_rendering_mode() : string {
	$mode = get_option( AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION, 'auto' );
	return in_array( $mode, array( 'auto', 'font', 'core' ), true ) ? $mode : 'auto';
}

/**
 * The adapter's configuration, as printed for the browser.
 *
 * Coverage only: the browser learns which profiles have a font and the alias to probe it
 * under, never a file URL. Files are loaded by the @font-face rules, so there is one
 * source for them.
 *
 * @return array{version:string,family:string,profiles:array<string,array<string,mixed>>}
 */
function axismundi_emoji_unicode_config() : array {
	$fonts    = axismundi_emoji_unicode_font_manifest();
	$profiles = array();
	foreach ( axismundi_emoji_unicode_profiles() as $id => $profile ) {
		$profile['font'] = isset( $fonts['profiles'][ $id ] );
		if ( $profile['font'] ) {
			$profile['fontFamily'] = $fonts['profiles'][ $id ]['fontFamily'];
		}
		$profiles[ $id ] = $profile;
	}
	$config = array(
		'mode'     => axismundi_emoji_unicode_rendering_mode(),
		'family'   => $fonts['family'],
		'profiles' => $profiles,
	);
	/*
	 * Probe results cached in the browser are valid only for these profiles and these exact
	 * files. The file hashes are part of the key, so shipping a different font under the same
	 * name discards a cached "font probe passed".
	 */
	$config['version'] = substr( md5( (string) wp_json_encode( array( $config, array_column( $fonts['profiles'], 'sha256' ), array_column( $fonts['profiles'], 'url' ) ) ) ), 0, 12 );
	return $config;
}

/**
 * Whether the adapter has anything to do on this site.
 *
 * Without a font there is nothing to wrap a grapheme for, so nothing is loaded at all:
 * the page is byte-for-byte what Core would have sent.
 *
 * @return bool
 */
function axismundi_emoji_unicode_is_active() : bool {
	$mode = axismundi_emoji_unicode_rendering_mode();
	if ( 'core' === $mode ) {
		return false;
	}
	// A font for a profile this mode does not use is not a reason to load anything.
	foreach ( array_keys( axismundi_emoji_unicode_font_manifest()['profiles'] ) as $id ) {
		if ( axismundi_emoji_unicode_profile_in_mode( (string) $id, $mode ) ) {
			return true;
		}
	}
	return false;
}

/**
 * The stylesheet: one @font-face per file under its alias, and one wrapper rule per profile.
 *
 * WORKAROUND (Trac candidate C5): this is printed here rather than through
 * wp_print_font_faces(). WP_Font_Face::order_src() takes the format from
 * pathinfo( $url, PATHINFO_EXTENSION ), which for `font.woff2?ver=…` is `woff2?ver=…`, an
 * unknown format, so the src is dropped and a src-less @font-face is printed (measured
 * 2026-09-16). A cache-busted font URL cannot go through Core's printer today.
 *
 * Each face's unicode-range is the union of the profiles that file serves, so the file is
 * not downloaded on a page with no covered code point.
 *
 * @return string
 */
function axismundi_emoji_unicode_css() : string {
	$fonts   = axismundi_emoji_unicode_font_manifest();
	$formats = array(
		'woff2' => 'woff2',
		'woff'  => 'woff',
		'ttf'   => 'truetype',
		'otf'   => 'opentype',
	);
	$faces   = array();
	// Faces for the current mode's profiles only: `auto` must never reference, let alone
	// download, the complete font, and `font` has no use for the flags subset.
	foreach ( $fonts['profiles'] as $id => $profile ) {
		if ( ! axismundi_emoji_unicode_profile_in_mode( (string) $id ) ) {
			continue;
		}
		$url = $profile['url'];
		if ( ! isset( $faces[ $url ] ) ) {
			$faces[ $url ] = array(
				'family' => $profile['fontFamily'],
				'ranges' => array(),
			);
		}
		foreach ( array_filter( array_map( 'trim', explode( ',', $profile['unicodeRange'] ) ) ) as $range ) {
			if ( preg_match( '/^U\+[0-9A-Fa-f?]{1,6}(?:-[0-9A-Fa-f]{1,6})?$/', $range ) ) {
				$faces[ $url ]['ranges'][ $range ] = true;
			}
		}
	}

	$css = '';
	foreach ( $faces as $url => $face ) {
		$extension = strtolower( (string) pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		if ( ! isset( $formats[ $extension ] ) ) {
			continue;
		}
		$css .= sprintf(
			'@font-face{font-family:"%1$s";font-style:normal;font-weight:400;font-display:swap;src:url("%2$s") format("%3$s");%4$s}',
			$face['family'],
			str_replace( array( '"', ')', '(' ), '', esc_url_raw( $url ) ),
			$formats[ $extension ],
			array() !== $face['ranges'] ? 'unicode-range:' . implode( ', ', array_keys( $face['ranges'] ) ) . ';' : ''
		);
	}

	/*
	 * The wrapper names its family itself, so the font is used no matter what the theme's
	 * stack says and no theme has to cooperate. `normal` style and weight keep a bold or
	 * italic paragraph from asking for a face the emoji font does not have.
	 */
	$css .= '.ax-unicode-emoji{font-style:normal;font-weight:400;}';
	foreach ( $fonts['profiles'] as $id => $profile ) {
		if ( ! axismundi_emoji_unicode_profile_in_mode( (string) $id ) ) {
			continue;
		}
		$css .= sprintf( '.ax-unicode-emoji[data-ax-emoji-profile="%1$s"]{font-family:"%2$s";}', $id, $profile['fontFamily'] );
	}
	return $css;
}

/**
 * Load the adapter in the document head, deferred, with its stylesheet.
 *
 * The head matters. Core's detection module is printed in the footer and its image
 * replacement arrives later still, after an asynchronous probe and a script load. A
 * deferred head script runs before both, so the first wrap happens before Core's first
 * parse, and the adapter's MutationObserver is registered before Core's, so it sees each
 * mutation batch first. (Measured by tests/unicode-harness and in wp-env, not assumed.)
 *
 * @return void
 */
function axismundi_emoji_unicode_enqueue() : void {
	if ( ! axismundi_emoji_unicode_is_active() ) {
		return;
	}
	$script = dirname( __DIR__ ) . '/assets/unicode/adapter.js';
	if ( ! is_readable( $script ) ) {
		return;
	}
	wp_enqueue_script(
		AXISMUNDI_EMOJI_UNICODE_SCRIPT_HANDLE,
		plugins_url( 'assets/unicode/adapter.js', dirname( __DIR__ ) . '/axismundi-emoji.php' ),
		array(),
		AXISMUNDI_EMOJI_VERSION . '-' . (string) filemtime( $script ),
		array(
			'in_footer' => false,
			'strategy'  => 'defer',
		)
	);
	wp_register_style( AXISMUNDI_EMOJI_UNICODE_STYLE_HANDLE, false, array(), AXISMUNDI_EMOJI_VERSION );
	wp_add_inline_style( AXISMUNDI_EMOJI_UNICODE_STYLE_HANDLE, axismundi_emoji_unicode_css() );
	wp_enqueue_style( AXISMUNDI_EMOJI_UNICODE_STYLE_HANDLE );
}
add_action( 'wp_enqueue_scripts', 'axismundi_emoji_unicode_enqueue' );

/**
 * Load the adapter for an editing screen: passive, with the same stylesheet.
 *
 * Called by the picker's enqueue helper, so it lands only on screens that asked for the
 * picker. Passive means probe and classify, never observe: the block editor's DOM is React's,
 * and the picker applies the wrapper class to its own tiles. Core's emoji detection is
 * removed on the block editor screen (edit-form-blocks.php), so there is no image fallback
 * to cooperate with there.
 *
 * @return bool Whether it was enqueued.
 */
function axismundi_emoji_unicode_enqueue_passive() : bool {
	static $done = false;
	if ( $done ) {
		return true;
	}
	$script = dirname( __DIR__ ) . '/assets/unicode/adapter.js';
	if ( ! axismundi_emoji_unicode_is_active() || ! is_readable( $script ) ) {
		return false;
	}
	$handle = AXISMUNDI_EMOJI_UNICODE_SCRIPT_HANDLE . '-passive';
	wp_register_script(
		$handle,
		plugins_url( 'assets/unicode/adapter.js', dirname( __DIR__ ) . '/axismundi-emoji.php' ),
		array(),
		AXISMUNDI_EMOJI_VERSION . '-' . (string) filemtime( $script ),
		true
	);
	$config            = axismundi_emoji_unicode_config();
	$config['observe'] = false;
	wp_add_inline_script( $handle, 'window.axismundiEmojiUnicodeConfig = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';', 'before' );
	wp_enqueue_script( $handle );

	if ( ! wp_style_is( AXISMUNDI_EMOJI_UNICODE_STYLE_HANDLE, 'registered' ) ) {
		wp_register_style( AXISMUNDI_EMOJI_UNICODE_STYLE_HANDLE, false, array(), AXISMUNDI_EMOJI_VERSION );
		wp_add_inline_style( AXISMUNDI_EMOJI_UNICODE_STYLE_HANDLE, axismundi_emoji_unicode_css() );
	}
	wp_enqueue_style( AXISMUNDI_EMOJI_UNICODE_STYLE_HANDLE );
	$done = true;
	return true;
}

/**
 * Print the configuration as inert JSON, the way Core prints `wp-emoji-settings`.
 *
 * @return void
 */
function axismundi_emoji_unicode_print_head() : void {
	if ( ! wp_script_is( AXISMUNDI_EMOJI_UNICODE_SCRIPT_HANDLE, 'enqueued' ) ) {
		return;
	}
	wp_print_inline_script_tag(
		(string) wp_json_encode( axismundi_emoji_unicode_config(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ),
		array(
			'id'   => AXISMUNDI_EMOJI_UNICODE_CONFIG_ID,
			'type' => 'application/json',
		)
	);
}
add_action( 'wp_head', 'axismundi_emoji_unicode_print_head', 1 );

/**
 * The complete font Font Library offers, from the manifest's `collectionFile`.
 *
 * Deliberately not derived from the runtime profiles. In `auto` the flags profiles load a
 * subset, but a user installing "Noto Color Emoji" should get the whole font; the manifest
 * names that file separately so the two choices cannot drift into each other.
 *
 * @return string Plugin URL, or '' when the manifest names no shipped, listed file.
 */
function axismundi_emoji_unicode_collection_font_url() : string {
	$root = dirname( __DIR__ );
	$json = is_readable( $root . '/' . AXISMUNDI_EMOJI_UNICODE_MANIFEST ) ? file_get_contents( $root . '/' . AXISMUNDI_EMOJI_UNICODE_MANIFEST ) : false;
	$data = is_string( $json ) ? json_decode( $json, true ) : null;
	$file = is_array( $data ) && is_string( $data['collectionFile'] ?? null ) ? $data['collectionFile'] : '';
	if ( '' === $file || str_contains( $file, '..' ) || ! isset( $data['files'][ $file ] ) || ! is_readable( $root . '/' . $file ) ) {
		return '';
	}
	return plugins_url( $file, $root . '/axismundi-emoji.php' );
}

/**
 * Offer the emoji font in Font Library, as the regional font providers do.
 *
 * Discovery and management only. Installing it from the collection copies the file into
 * uploads and makes it selectable in Typography; rendering never depends on that, because
 * the adapter's own @font-face above does the work on every page.
 *
 * Listed under the real family name, without a unicode-range and without the cache query:
 * the collection offers the complete font, while runtime faces may use narrower profile
 * subsets under their aliases. What Font Library cannot yet record is that this
 * family is for emoji at all; that lives in the manifest until Gutenberg #82848 gives
 * families a place to say so.
 *
 * @return void
 */
function axismundi_emoji_unicode_register_font_collection() : void {
	if ( ! function_exists( 'wp_register_font_collection' ) ) {
		return;
	}
	$fonts = axismundi_emoji_unicode_font_manifest();
	$url   = axismundi_emoji_unicode_collection_font_url();
	if ( '' === $url ) {
		return;
	}
	$faces = array(
		array(
			'fontFamily'  => $fonts['family'],
			'fontStyle'   => 'normal',
			'fontWeight'  => '400',
			'fontDisplay' => 'swap',
			'src'         => $url,
		),
	);
	wp_register_font_collection(
		AXISMUNDI_EMOJI_UNICODE_COLLECTION,
		array(
			'name'          => __( 'Axismundi Emoji', 'axismundi-emoji' ),
			'description'   => __( 'Noto Color Emoji (COLRv1), the emoji font Axismundi Emoji uses where a browser cannot draw an emoji itself.', 'axismundi-emoji' ),
			'categories'    => array(
				array(
					'name' => __( 'Emoji', 'axismundi-emoji' ),
					'slug' => 'emoji',
				),
			),
			'font_families' => array(
				array(
					'categories'           => array( 'emoji' ),
					'font_family_settings' => array(
						'name'       => $fonts['family'],
						'slug'       => sanitize_title( $fonts['family'] ),
						'fontFamily' => '"' . $fonts['family'] . '"',
						'fontFace'   => $faces,
					),
				),
			),
		)
	);
}
add_action( 'init', 'axismundi_emoji_unicode_register_font_collection' );
