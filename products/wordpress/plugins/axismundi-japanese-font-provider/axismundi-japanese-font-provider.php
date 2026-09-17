<?php
/**
 * Plugin Name:       Axismundi Japanese Font Provider
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/wordpress/plugins/axismundi-japanese-font-provider
 * Description:       Optional Japanese web-font provider for the Axismundi theme — supplies Noto Sans JP and Noto Serif JP, fills the theme's CJK fallback slot for Japanese documents, and registers them as a Font Library collection.
 * Version:           0.1.1
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-japanese-font-provider
 *
 * @package AxismundiJapaneseFontProvider
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'AXISMUNDI_JAPANESE_FONT_PROVIDER_VERSION' ) ) {
	define( 'AXISMUNDI_JAPANESE_FONT_PROVIDER_VERSION', '0.1.1' );
}

/**
 * The Japanese range the bundled faces cover, as declared in assets/styles/fonts.css.
 *
 * The Font Library collection declares the same range, so a family installed from it
 * takes only Japanese text, as the automatic fallback does. The files contain other glyphs
 * too; this is the site's choice of what the face is used for, not the file's coverage.
 * tests/audit-collection.php checks that the two stay identical.
 */
const AXISMUNDI_JAPANESE_FONT_PROVIDER_UNICODE_RANGE = 'U+3000-303F, U+3040-309F, U+30A0-30FF, U+3190-319F, U+31F0-31FF, U+3200-32FF, U+3300-33FF, U+4E00-9FFF, U+F900-FAFF, U+FF00-FFEF';

/**
 * Enqueue the @font-face provider on the front end and in the block editor.
 *
 * enqueue_block_assets fires for both the front end and the editor (including
 * the editor canvas). CSS activates the provider only when the document root is
 * Japanese; the logged-in user's admin-interface language is not a content-font
 * signal.
 */
function axismundi_japanese_font_provider_enqueue() : void {
	wp_enqueue_style(
		'axismundi-japanese-font-provider',
		plugins_url( 'assets/styles/fonts.css', __FILE__ ),
		array(),
		AXISMUNDI_JAPANESE_FONT_PROVIDER_VERSION
	);
}
add_action( 'enqueue_block_assets', 'axismundi_japanese_font_provider_enqueue' );

/**
 * Register the Noto CJK Japanese family as a Font Library collection.
 *
 * Discovery / management surface in Site Editor > Styles > Typography > Manage
 * fonts. The @font-face provider above is what actually renders the theme's
 * stacks, without installing anything. Installing a family from this collection
 * adds a separate font limited to the same Japanese range; it does not recreate the
 * theme's fallback stack. Available since WordPress 6.5.
 */
function axismundi_japanese_font_provider_collection() : void {
	if ( ! function_exists( 'wp_register_font_collection' ) ) {
		return;
	}

	$sans_src = plugins_url( 'assets/fonts/noto-sans-jp/axismundi-noto-sans-jp.woff2', __FILE__ );
	$serif_src = plugins_url( 'assets/fonts/noto-serif-jp/axismundi-noto-serif-jp.woff2', __FILE__ );

	wp_register_font_collection(
		'axismundi-japanese-font-provider',
		array(
			'name'          => __( 'Axismundi Japanese Font Provider', 'axismundi-japanese-font-provider' ),
			'description'   => __( 'Noto Sans JP and Noto Serif JP for the Axismundi theme. Automatic Japanese fallback works without installing anything; installing adds a separate font limited to the same Japanese range and does not recreate the fallback stack of the theme.', 'axismundi-japanese-font-provider' ),
			'categories'    => array(
				array(
					'name' => __( 'Japanese', 'axismundi-japanese-font-provider' ),
					'slug' => 'japanese',
				),
			),
			'font_families' => array(
				array(
					'categories'           => array( 'japanese' ),
					'font_family_settings' => array(
						'name'       => 'Noto Sans JP',
						'slug'       => 'noto-sans-jp',
						'fontFamily' => '"Noto Sans JP", sans-serif',
						'fontFace'   => array(
							array(
								'fontFamily'   => 'Noto Sans JP',
								'fontStyle'    => 'normal',
								'fontWeight'   => '100 900',
								'fontDisplay'  => 'swap',
								'src'          => $sans_src,
								'unicodeRange' => AXISMUNDI_JAPANESE_FONT_PROVIDER_UNICODE_RANGE,
							),
						),
					),
				),
				array(
					'categories'           => array( 'japanese' ),
					'font_family_settings' => array(
						'name'       => 'Noto Serif JP',
						'slug'       => 'noto-serif-jp',
						'fontFamily' => '"Noto Serif JP", serif',
						'fontFace'   => array(
							array(
								'fontFamily'   => 'Noto Serif JP',
								'fontStyle'    => 'normal',
								'fontWeight'   => '100 900',
								'fontDisplay'  => 'swap',
								'src'          => $serif_src,
								'unicodeRange' => AXISMUNDI_JAPANESE_FONT_PROVIDER_UNICODE_RANGE,
							),
						),
					),
				),
			),
		)
	);
}
add_action( 'init', 'axismundi_japanese_font_provider_collection' );
