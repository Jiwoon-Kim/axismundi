<?php
/**
 * Japanese provider: the Font Library collection declares the same range as the CSS (dev-only; dist-excluded).
 *
 * Run: npx wp-env run cli wp eval-file wp-content/plugins/axismundi-japanese-font-provider/tests/audit-collection.php
 *
 * @package AxismundiFontProviders
 */

defined( 'ABSPATH' ) || exit( 1 );

if ( ! defined( 'AXISMUNDI_JAPANESE_FONT_PROVIDER_UNICODE_RANGE' ) ) {
	require_once dirname( __DIR__ ) . '/axismundi-japanese-font-provider.php';
}

$ax_fp_results = array();

/**
 * @param bool[] $results Results.
 * @param string $label   Check label.
 * @param bool   $ok      Outcome.
 */
function ax_fp_assert( array &$results, string $label, bool $ok ) : void {
	$results[] = $ok;
	printf( "[%s] %s\n", $ok ? 'PASS' : 'FAIL', $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
}

/**
 * The unicode-range the stylesheet declares for one family.
 */
function ax_fp_css_range( string $css, string $family ) : string {
	$pattern = '/font-family:\s*"' . preg_quote( $family, '/' ) . '";[^}]*?unicode-range:\s*([^;]+);/s';
	return preg_match( $pattern, $css, $m ) ? trim( $m[1] ) : '';
}

$ax_fp_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/styles/fonts.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- plugin-owned file.

if ( WP_Font_Library::get_instance()->get_font_collection( 'axismundi-japanese-font-provider' ) ) {
	wp_unregister_font_collection( 'axismundi-japanese-font-provider' );
}
axismundi_japanese_font_provider_collection();
$ax_fp_collection = WP_Font_Library::get_instance()->get_font_collection( 'axismundi-japanese-font-provider' );
$ax_fp_data       = $ax_fp_collection ? $ax_fp_collection->get_data() : null;
ax_fp_assert( $ax_fp_results, 'the collection is registered', is_array( $ax_fp_data ) );

$ax_fp_faces = array();
foreach ( (array) ( $ax_fp_data['font_families'] ?? array() ) as $ax_fp_family ) {
	foreach ( (array) ( $ax_fp_family['font_family_settings']['fontFace'] ?? array() ) as $ax_fp_face ) {
		$ax_fp_faces[ $ax_fp_face['fontFamily'] ] = $ax_fp_face;
	}
}

foreach ( array( 'Noto Sans JP', 'Noto Serif JP' ) as $ax_fp_name ) {
	$ax_fp_css_range = ax_fp_css_range( $ax_fp_css, $ax_fp_name );
	ax_fp_assert( $ax_fp_results, "{$ax_fp_name}: the stylesheet declares a range", '' !== $ax_fp_css_range );
	ax_fp_assert( $ax_fp_results, "{$ax_fp_name}: the constant matches the stylesheet", AXISMUNDI_JAPANESE_FONT_PROVIDER_UNICODE_RANGE === $ax_fp_css_range );
	ax_fp_assert( $ax_fp_results, "{$ax_fp_name}: the collection face keeps the same range after sanitizing", ( $ax_fp_faces[ $ax_fp_name ]['unicodeRange'] ?? '' ) === $ax_fp_css_range );
}

ax_fp_assert( $ax_fp_results, 'the description says installing is optional', str_contains( (string) ( $ax_fp_data['description'] ?? '' ), 'without installing anything' ) );

$ax_fp_failures = count( array_filter( $ax_fp_results, static fn( bool $r ) : bool => ! $r ) );
printf( "\n== %d checks, %d failed ==\n", count( $ax_fp_results ), $ax_fp_failures ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output.
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_fp_failures > 0 ? 1 : 0 );
}
exit( $ax_fp_failures > 0 ? 1 : 0 );
