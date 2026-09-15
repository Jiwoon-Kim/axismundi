<?php
/**
 * Unicode emoji font fallback: server contract (dev-only; dist-excluded).
 *
 * What the server decides: which profiles exist, which font covers them, when the adapter
 * loads at all, where it loads, what it is told, and what Font Library is offered. Wrapping
 * and its interplay with Core happen in a browser and are measured by tests/unicode-harness.
 *
 * Run: npx wp-env run cli wp eval-file wp-content/plugins/axismundi-emoji/tests/audit-emoji-unicode.php
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';

$ax_uni_results = array();

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_uni_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/** Forget what earlier checks enqueued, so each case starts from a clean queue. */
function ax_uni_reset_assets() : void {
	wp_dequeue_script( AXISMUNDI_EMOJI_UNICODE_SCRIPT_HANDLE );
	wp_deregister_script( AXISMUNDI_EMOJI_UNICODE_SCRIPT_HANDLE );
	wp_dequeue_style( AXISMUNDI_EMOJI_UNICODE_STYLE_HANDLE );
	wp_deregister_style( AXISMUNDI_EMOJI_UNICODE_STYLE_HANDLE );
}

/** Capture what a printing function echoes. */
function ax_uni_capture( callable $fn ) : string {
	ob_start();
	$fn();
	return (string) ob_get_clean();
}

$ax_uni_no_font = static fn( array $manifest ) : array => array( 'family' => $manifest['family'], 'profiles' => array() );

try {
	delete_option( AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION );

	// -- Profiles ------------------------------------------------------------------------------

	$ax_uni_profiles = axismundi_emoji_unicode_profiles();
	ax_uni_assert( $ax_uni_results, 'flags are two profiles, because the two halves fail independently, plus one for every emoji in font mode', array( 'flags-country', 'flags-subdivision', 'emoji' ) === array_keys( $ax_uni_profiles ) );
	ax_uni_assert( $ax_uni_results, 'flags belong to auto and the whole set to font, so the modes never compete for a grapheme', array( 'auto' ) === $ax_uni_profiles['flags-country']['modes'] && array( 'auto' ) === $ax_uni_profiles['flags-subdivision']['modes'] && array( 'font' ) === $ax_uni_profiles['emoji']['modes'] );
	ax_uni_assert( $ax_uni_results, 'the font mode probe uses sequences only, since a single code point passes on a system emoji font', array() === array_filter( $ax_uni_profiles['emoji']['probe'], static fn( array $seq ) : bool => count( $seq ) < 2 ) );

	$ax_uni_all_ints = true;
	foreach ( $ax_uni_profiles as $ax_uni_profile ) {
		foreach ( array_merge( $ax_uni_profile['probe'], $ax_uni_profile['sequences'] ?? array() ) as $ax_uni_list ) {
			$ax_uni_all_ints = $ax_uni_all_ints && array() !== $ax_uni_list && array_filter( $ax_uni_list, 'is_int' ) === $ax_uni_list;
		}
	}
	ax_uni_assert( $ax_uni_results, 'every sequence is a code point list, so no escape notation travels anywhere', $ax_uni_all_ints );
	ax_uni_assert( $ax_uni_results, 'the country probe is Sark, the flag Core probes too', array( array( 0x1F1E8, 0x1F1F6 ) ) === $ax_uni_profiles['flags-country']['probe'] );

	$ax_uni_tag_shape = true;
	foreach ( $ax_uni_profiles['flags-subdivision']['sequences'] as $ax_uni_list ) {
		$ax_uni_middle    = array_slice( $ax_uni_list, 1, -1 );
		$ax_uni_tag_shape = $ax_uni_tag_shape && 0x1F3F4 === $ax_uni_list[0] && 0xE007F === end( $ax_uni_list )
			&& array() === array_filter( $ax_uni_middle, static fn( int $cp ) : bool => $cp < 0xE0020 || $cp > 0xE007E );
	}
	ax_uni_assert( $ax_uni_results, 'subdivision flags are exactly England, Scotland and Wales, each a well-formed tag sequence', 3 === count( $ax_uni_profiles['flags-subdivision']['sequences'] ) && $ax_uni_tag_shape );
	ax_uni_assert( $ax_uni_results, 'the subdivision probe covers all three, so one native flag cannot vouch for the other two', $ax_uni_profiles['flags-subdivision']['sequences'] === $ax_uni_profiles['flags-subdivision']['probe'] );

	// -- The shipped font ----------------------------------------------------------------------

	$ax_uni_root     = dirname( __DIR__ );
	$ax_uni_manifest = json_decode( (string) file_get_contents( $ax_uni_root . '/' . AXISMUNDI_EMOJI_UNICODE_MANIFEST ), true );
	ax_uni_assert( $ax_uni_results, 'a generated manifest ships beside the font', is_array( $ax_uni_manifest ) && 'scripts/build-unicode-font-manifest.py' === ( $ax_uni_manifest['generatedBy'] ?? '' ) );

	$ax_uni_hashes_match = is_array( $ax_uni_manifest ) && array() !== ( $ax_uni_manifest['files'] ?? array() );
	foreach ( is_array( $ax_uni_manifest ) ? ( $ax_uni_manifest['files'] ?? array() ) : array() as $ax_uni_rel => $ax_uni_file ) {
		$ax_uni_path         = $ax_uni_root . '/' . $ax_uni_rel;
		$ax_uni_hashes_match = $ax_uni_hashes_match && is_readable( $ax_uni_path )
			&& hash_file( 'sha256', $ax_uni_path ) === $ax_uni_file['sha256'] && filesize( $ax_uni_path ) === $ax_uni_file['bytes'];
	}
	ax_uni_assert( $ax_uni_results, 'every file the manifest lists is present with the recorded size and SHA-256', $ax_uni_hashes_match );
	ax_uni_assert( $ax_uni_results, 'the font directory keeps its licence and provenance', is_readable( $ax_uni_root . '/assets/fonts/noto-color-emoji/OFL.txt' ) && is_readable( $ax_uni_root . '/assets/fonts/noto-color-emoji/source.txt' ) );

	$ax_uni_fonts = axismundi_emoji_unicode_font_manifest();
	ax_uni_assert( $ax_uni_results, 'every profile is covered by a shipped font', array( 'flags-country', 'flags-subdivision', 'emoji' ) === array_keys( $ax_uni_fonts['profiles'] ) );
	ax_uni_assert( $ax_uni_results, 'under the upstream family name, unchanged', 'Noto Color Emoji' === $ax_uni_fonts['family'] );
	// Runtime expectations come from the manifest's profiles and files, so a different subset needs no edit here.
	$ax_uni_rel   = (string) ( $ax_uni_manifest['profiles']['flags-country']['file'] ?? '' );
	$ax_uni_name  = basename( $ax_uni_rel );
	$ax_uni_sha   = (string) ( $ax_uni_manifest['files'][ $ax_uni_rel ]['sha256'] ?? '' );
	$ax_uni_alias = (string) ( $ax_uni_manifest['files'][ $ax_uni_rel ]['alias'] ?? '' );
	ax_uni_assert( $ax_uni_results, 'both flag profiles are served by one listed file with a declared alias', '' !== $ax_uni_alias && '' !== $ax_uni_sha && $ax_uni_rel === ( $ax_uni_manifest['profiles']['flags-subdivision']['file'] ?? null ) );
	ax_uni_assert( $ax_uni_results, 'the font URL points into the plugin and carries the file hash, so a replaced file is never served stale', str_ends_with( $ax_uni_fonts['profiles']['flags-country']['url'], '/axismundi-emoji/' . $ax_uni_rel . '?ver=' . substr( $ax_uni_sha, 0, 12 ) ) && '' !== $ax_uni_sha );
	ax_uni_assert( $ax_uni_results, 'the runtime file loads under its own alias, so it cannot collide with the full font', $ax_uni_alias === $ax_uni_fonts['profiles']['flags-country']['fontFamily'] && $ax_uni_alias === $ax_uni_fonts['profiles']['flags-subdivision']['fontFamily'] );

	// -- When nothing loads --------------------------------------------------------------------

	add_filter( 'axismundi_emoji_unicode_font_manifest', $ax_uni_no_font );
	ax_uni_reset_assets();
	do_action( 'wp_enqueue_scripts' );
	ax_uni_assert( $ax_uni_results, 'with no font covering anything, the adapter is not loaded, so the page is what Core sends', ! wp_script_is( AXISMUNDI_EMOJI_UNICODE_SCRIPT_HANDLE, 'enqueued' ) && ! wp_style_is( AXISMUNDI_EMOJI_UNICODE_STYLE_HANDLE, 'enqueued' ) );
	ax_uni_assert( $ax_uni_results, 'nor is anything printed in the head', '' === ax_uni_capture( 'axismundi_emoji_unicode_print_head' ) );
	$ax_uni_version_without = axismundi_emoji_unicode_config()['version'];
	remove_filter( 'axismundi_emoji_unicode_font_manifest', $ax_uni_no_font );
	ax_uni_assert( $ax_uni_results, 'the cache version changes with coverage, so stale probe results are discarded', axismundi_emoji_unicode_config()['version'] !== $ax_uni_version_without );

	$ax_uni_version_shipped = axismundi_emoji_unicode_config()['version'];
	$ax_uni_other_file      = static function ( array $manifest ) : array {
		foreach ( $manifest['profiles'] as $id => $profile ) {
			$manifest['profiles'][ $id ]['sha256'] = str_repeat( '0', 64 );
		}
		return $manifest;
	};
	add_filter( 'axismundi_emoji_unicode_font_manifest', $ax_uni_other_file );
	ax_uni_assert( $ax_uni_results, 'and with the file hash, so a different font under the same name discards a cached font probe', axismundi_emoji_unicode_config()['version'] !== $ax_uni_version_shipped );
	remove_filter( 'axismundi_emoji_unicode_font_manifest', $ax_uni_other_file );

	$ax_uni_unknown = static function ( array $manifest ) : array {
		$manifest['profiles']['not-a-profile'] = array( 'url' => 'https://example.org/x.woff2', 'unicodeRange' => 'U+0-10FFFF' );
		return $manifest;
	};
	add_filter( 'axismundi_emoji_unicode_font_manifest', $ax_uni_unknown );
	ax_uni_assert( $ax_uni_results, 'a filtered manifest cannot add a profile the adapter does not define', ! isset( axismundi_emoji_unicode_font_manifest()['profiles']['not-a-profile'] ) );
	remove_filter( 'axismundi_emoji_unicode_font_manifest', $ax_uni_unknown );

	// -- When it loads -------------------------------------------------------------------------

	ax_uni_reset_assets();
	do_action( 'wp_enqueue_scripts' );
	ax_uni_assert( $ax_uni_results, 'the adapter loads with the shipped font', wp_script_is( AXISMUNDI_EMOJI_UNICODE_SCRIPT_HANDLE, 'enqueued' ) );
	ax_uni_assert( $ax_uni_results, 'in the head, so it runs before Core\'s footer detection module', 1 !== (int) wp_scripts()->get_data( AXISMUNDI_EMOJI_UNICODE_SCRIPT_HANDLE, 'group' ) );
	ax_uni_assert( $ax_uni_results, 'deferred, so the document is parsed when it wraps', 'defer' === wp_scripts()->get_data( AXISMUNDI_EMOJI_UNICODE_SCRIPT_HANDLE, 'strategy' ) );
	ax_uni_assert( $ax_uni_results, 'with no dependencies, so it cannot be pushed behind anything', array() === wp_scripts()->registered[ AXISMUNDI_EMOJI_UNICODE_SCRIPT_HANDLE ]->deps );

	$ax_uni_inline = implode( '', (array) wp_styles()->get_data( AXISMUNDI_EMOJI_UNICODE_STYLE_HANDLE, 'after' ) );
	ax_uni_assert( $ax_uni_results, 'the stylesheet is enqueued with the adapter', wp_style_is( AXISMUNDI_EMOJI_UNICODE_STYLE_HANDLE, 'enqueued' ) );
	ax_uni_assert( $ax_uni_results, 'each wrapper names its profile\'s family itself, so no theme stack has to include it', str_contains( $ax_uni_inline, '.ax-unicode-emoji[data-ax-emoji-profile="flags-country"]{font-family:"' . $ax_uni_alias . '";}' ) && str_contains( $ax_uni_inline, '.ax-unicode-emoji[data-ax-emoji-profile="flags-subdivision"]{font-family:"' . $ax_uni_alias . '";}' ) );
	ax_uni_assert( $ax_uni_results, 'and keeps bold or italic text from asking for a face the emoji font lacks', str_contains( $ax_uni_inline, '.ax-unicode-emoji{font-style:normal;font-weight:400;}' ) );
	ax_uni_assert( $ax_uni_results, 'one @font-face for the runtime file, under its alias', 1 === substr_count( $ax_uni_inline, '@font-face' ) && str_contains( $ax_uni_inline, '@font-face{font-family:"' . $ax_uni_alias . '";' ) );
	ax_uni_assert( $ax_uni_results, 'its src keeps the hashed URL and the woff2 format (C5: Core\'s printer would drop both)', str_contains( $ax_uni_inline, '/' . $ax_uni_name . '?ver=' . substr( $ax_uni_sha, 0, 12 ) . '") format("woff2")' ) );
	ax_uni_assert( $ax_uni_results, 'its unicode-range is the union of both flag profiles, so other text never picks it', str_contains( $ax_uni_inline, 'unicode-range:U+1F1E6-1F1FF, U+1F3F4, U+E0020-E007F;' ) );

	/*
	 * The reason for printing faces ourselves, pinned: if Core's printer starts keeping a src
	 * with a query string, this fails and the workaround can go.
	 */
	$ax_uni_core_face = ax_uni_capture( static fn() => wp_print_font_faces( array( array( array( 'font-family' => 'C5 probe', 'src' => array( 'https://example.org/f/a.woff2?ver=abc' ) ) ) ) ) );
	ax_uni_assert( $ax_uni_results, 'C5 still holds: wp_print_font_faces() drops a src that has a query string', str_contains( $ax_uni_core_face, '@font-face' ) && ! str_contains( $ax_uni_core_face, 'src:' ) );

	$ax_uni_head = ax_uni_capture( 'axismundi_emoji_unicode_print_head' );
	ax_uni_assert( $ax_uni_results, 'the configuration is inert JSON under the id the adapter reads', str_contains( $ax_uni_head, 'id="' . AXISMUNDI_EMOJI_UNICODE_CONFIG_ID . '"' ) && str_contains( $ax_uni_head, 'type="application/json"' ) );
	$ax_uni_json = preg_match( '#<script[^>]*id="' . AXISMUNDI_EMOJI_UNICODE_CONFIG_ID . '"[^>]*>(.*?)</script>#s', $ax_uni_head, $ax_uni_m ) ? json_decode( $ax_uni_m[1], true ) : null;
	ax_uni_assert( $ax_uni_results, 'it parses, and marks both flag profiles as having a font', is_array( $ax_uni_json ) && true === $ax_uni_json['profiles']['flags-country']['font'] && true === $ax_uni_json['profiles']['flags-subdivision']['font'] );
	ax_uni_assert( $ax_uni_results, 'with the alias the font probe must ask for', is_array( $ax_uni_json ) && $ax_uni_alias === ( $ax_uni_json['profiles']['flags-country']['fontFamily'] ?? '' ) );
	ax_uni_assert( $ax_uni_results, 'it never carries the harness-only diagnostics switch', is_array( $ax_uni_json ) && ! isset( $ax_uni_json['diagnostics'] ) );
	ax_uni_assert( $ax_uni_results, 'font URLs stay out of the config; the browser learns coverage, not file locations', is_array( $ax_uni_json ) && ! str_contains( (string) wp_json_encode( $ax_uni_json ), '.woff2' ) );


	// -- Font Library ----------------------------------------------------------------------------

	$ax_uni_collection = class_exists( 'WP_Font_Library' ) ? WP_Font_Library::get_instance()->get_font_collection( AXISMUNDI_EMOJI_UNICODE_COLLECTION ) : null;
	$ax_uni_data       = $ax_uni_collection instanceof WP_Font_Collection ? $ax_uni_collection->get_data() : null;
	ax_uni_assert( $ax_uni_results, 'the font is offered as a Font Library collection', is_array( $ax_uni_data ) );
	$ax_uni_family = is_array( $ax_uni_data ) ? ( $ax_uni_data['font_families'][0]['font_family_settings'] ?? array() ) : array();
	ax_uni_assert( $ax_uni_results, 'as the family Noto Color Emoji, slug noto-color-emoji', 'Noto Color Emoji' === ( $ax_uni_family['name'] ?? '' ) && 'noto-color-emoji' === ( $ax_uni_family['slug'] ?? '' ) );
	/*
	 * Pinned by name, not read from the manifest: what a user installs is a product decision
	 * (the complete Noto Color Emoji), and a manifest that quietly pointed the collection at a
	 * subset should fail here rather than pass by agreeing with itself.
	 */
	$ax_uni_collection_rel = 'assets/fonts/noto-color-emoji/axismundi-noto-colrv1.woff2';
	ax_uni_assert( $ax_uni_results, 'the manifest names the complete font as the collection file, and lists it with its hash', $ax_uni_collection_rel === ( $ax_uni_manifest['collectionFile'] ?? '' ) && isset( $ax_uni_manifest['files'][ $ax_uni_collection_rel ]['sha256'] ) );
	ax_uni_assert( $ax_uni_results, 'in auto the stylesheet never references the complete font, so auto never downloads it', 'auto' === axismundi_emoji_unicode_rendering_mode() && ! str_contains( $ax_uni_inline, '/' . basename( $ax_uni_collection_rel ) . '?' ) );
	ax_uni_assert( $ax_uni_results, 'only the font-mode profile uses the complete font at runtime', array( 'emoji' ) === array_keys( array_filter( $ax_uni_manifest['profiles'] ?? array(), static fn( array $p ) : bool => $ax_uni_collection_rel === ( $p['file'] ?? '' ) ) ) );

	update_option( AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION, 'font' );
	$ax_uni_font_css = axismundi_emoji_unicode_css();
	ax_uni_assert( $ax_uni_results, 'in font mode the stylesheet names only the complete font, under its own alias and with no range', 1 === substr_count( $ax_uni_font_css, '@font-face' ) && str_contains( $ax_uni_font_css, '/' . basename( $ax_uni_collection_rel ) . '?ver=' ) && ! str_contains( $ax_uni_font_css, 'unicode-range' ) && str_contains( $ax_uni_font_css, '.ax-unicode-emoji[data-ax-emoji-profile="emoji"]{font-family:"Axismundi Emoji Full";}' ) );
	ax_uni_assert( $ax_uni_results, 'and no flags wrapper rule, since font mode never classifies a grapheme as a flag profile', ! str_contains( $ax_uni_font_css, 'flags-country' ) );
	ax_uni_assert( $ax_uni_results, 'the config tells the adapter which mode it is in', 'font' === axismundi_emoji_unicode_config()['mode'] && axismundi_emoji_unicode_is_active() );
	delete_option( AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION );
	ax_uni_assert( $ax_uni_results, 'the collection offers exactly that file as its one face, without the runtime cache query', 1 === count( $ax_uni_family['fontFace'] ?? array() ) && str_ends_with( (string) ( $ax_uni_family['fontFace'][0]['src'] ?? '' ), '/axismundi-emoji/' . $ax_uni_collection_rel ) );
	add_filter( 'axismundi_emoji_unicode_font_manifest', $ax_uni_no_font );
	ax_uni_assert( $ax_uni_results, 'and it stays offered when runtime coverage is filtered away, because the two are separate', str_ends_with( axismundi_emoji_unicode_collection_font_url(), '/axismundi-emoji/' . $ax_uni_collection_rel ) );
	remove_filter( 'axismundi_emoji_unicode_font_manifest', $ax_uni_no_font );

	// -- Editing screens: passive adapter -------------------------------------------------------

	ax_uni_reset_assets();
	ax_uni_assert( $ax_uni_results, 'the picker helper loads the adapter for editing screens', axismundi_emoji_enqueue_picker() && wp_script_is( AXISMUNDI_EMOJI_UNICODE_SCRIPT_HANDLE . '-passive', 'enqueued' ) );
	$ax_uni_passive = implode( '', (array) wp_scripts()->get_data( AXISMUNDI_EMOJI_UNICODE_SCRIPT_HANDLE . '-passive', 'before' ) );
	ax_uni_assert( $ax_uni_results, 'in passive mode: it probes and classifies but never observes React\'s DOM', str_contains( $ax_uni_passive, 'window.axismundiEmojiUnicodeConfig = ' ) && str_contains( $ax_uni_passive, '"observe":false' ) );
	ax_uni_assert( $ax_uni_results, 'with the same stylesheet, so picker tiles get the same font faces', wp_style_is( AXISMUNDI_EMOJI_UNICODE_STYLE_HANDLE, 'enqueued' ) );
	$ax_uni_picker_before = implode( '', (array) wp_scripts()->get_data( AXISMUNDI_EMOJI_PICKER_HANDLE, 'before' ) );
	ax_uni_assert( $ax_uni_results, 'the picker receives group file URLs, not the catalogue itself', str_contains( $ax_uni_picker_before, 'window.axismundiEmojiUnicodeSource = ' ) && str_contains( $ax_uni_picker_before, 'rgi-17.0\/flags.json' ) === false && str_contains( $ax_uni_picker_before, 'rgi-17.0/flags.json' ) && strlen( $ax_uni_picker_before ) < 4000 );
	axismundi_emoji_enqueue_picker();
	ax_uni_assert( $ax_uni_results, 'asking twice adds the source once', 1 === substr_count( implode( '', (array) wp_scripts()->get_data( AXISMUNDI_EMOJI_PICKER_HANDLE, 'before' ) ), 'axismundiEmojiUnicodeSource' ) );
	$ax_uni_js_passive = (string) file_get_contents( $ax_uni_root . '/assets/unicode/adapter.js' );
	ax_uni_assert( $ax_uni_results, 'the adapter stops before observing when told not to', str_contains( $ax_uni_js_passive, 'config.observe === false' ) );

	// -- The operator's switch -----------------------------------------------------------------

	update_option( AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION, 'core' );
	ax_uni_reset_assets();
	do_action( 'wp_enqueue_scripts' );
	ax_uni_assert( $ax_uni_results, 'policy "core" turns the rendering layer off even with the font present', ! wp_script_is( AXISMUNDI_EMOJI_UNICODE_SCRIPT_HANDLE, 'enqueued' ) && '' === ax_uni_capture( 'axismundi_emoji_unicode_print_head' ) );
	update_option( AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION, 'something-else' );
	ax_uni_assert( $ax_uni_results, 'an unknown policy value reads as auto', 'auto' === axismundi_emoji_unicode_rendering_mode() );
	delete_option( AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION );

	// -- The adapter's side of the contract ----------------------------------------------------

	$ax_uni_js = (string) file_get_contents( $ax_uni_root . '/assets/unicode/adapter.js' );
	ax_uni_assert( $ax_uni_results, 'the adapter reads the same config id PHP prints', str_contains( $ax_uni_js, "'" . AXISMUNDI_EMOJI_UNICODE_CONFIG_ID . "'" ) );
	ax_uni_assert( $ax_uni_results, 'its wrapper class is new, not the custom emoji img class', str_contains( $ax_uni_js, "WRAPPER_CLASS = 'ax-unicode-emoji'" ) );
	ax_uni_assert( $ax_uni_results, 'it nests the Core exclusion span inside its own (C2 workaround)', str_contains( $ax_uni_js, 'outer.appendChild( inner )' ) && str_contains( $ax_uni_js, "CORE_EXCLUDE_CLASS = 'wp-exclude-emoji'" ) );
	ax_uni_assert( $ax_uni_results, 'it never touches Core\'s support cache', ! str_contains( $ax_uni_js, 'wpEmojiSettingsSupports' ) && ! str_contains( $ax_uni_js, '_wpemojiSettings' ) );
	ax_uni_assert( $ax_uni_results, 'its own cache expires in milliseconds, not Core\'s mixed units', str_contains( $ax_uni_js, 'CACHE_TTL = 7 * 24 * 60 * 60 * 1000' ) );
	ax_uni_assert( $ax_uni_results, 'it refuses to run without grapheme segmentation', str_contains( $ax_uni_js, "typeof Intl.Segmenter !== 'function'" ) );
	ax_uni_assert( $ax_uni_results, 'it matches both profile rules PHP declares', str_contains( $ax_uni_js, "case 'regional-indicator-pair':" ) && str_contains( $ax_uni_js, "case 'tag-sequence':" ) );
	ax_uni_assert( $ax_uni_results, 'the source carries no backslash-u escapes a file tool could decode', ! str_contains( $ax_uni_js, chr( 92 ) . 'u' ) );
} catch ( Throwable $ax_uni_error ) {
	ax_uni_assert( $ax_uni_results, 'the unicode suite ran to completion: ' . $ax_uni_error->getMessage(), false );
}

$ax_uni_failures = count( array_filter( $ax_uni_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_uni_results ), $ax_uni_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_uni_failures > 0 ? 1 : 0 );
}
exit( $ax_uni_failures > 0 ? 1 : 0 );
