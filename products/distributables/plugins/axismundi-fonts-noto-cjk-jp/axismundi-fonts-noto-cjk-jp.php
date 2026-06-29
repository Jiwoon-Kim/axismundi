<?php
/**
 * Plugin Name:       Axismundi Fonts: Noto CJK Japanese
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-fonts-noto-cjk-jp
 * Description:       Optional Japanese web-font provider for the Axismundi theme — supplies Noto Sans JP and Noto Serif JP, fills the theme's CJK fallback slot for Japanese documents, and registers them as a Font Library collection.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-fonts-noto-cjk-jp
 *
 * @package AxismundiFontsNotoCjkJp
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'AXISMUNDI_FONTS_NOTO_CJK_JP_VERSION' ) ) {
	define( 'AXISMUNDI_FONTS_NOTO_CJK_JP_VERSION', '0.1.0' );
}

/**
 * Enqueue the @font-face provider on the front end and in the block editor.
 *
 * enqueue_block_assets fires for both the front end and the editor (including
 * the editor canvas). CSS activates the provider only when the document root is
 * Japanese; the logged-in user's admin-interface language is not a content-font
 * signal.
 */
function axismundi_fonts_noto_cjk_jp_enqueue() : void {
	wp_enqueue_style(
		'axismundi-fonts-noto-cjk-jp',
		plugins_url( 'assets/styles/fonts.css', __FILE__ ),
		array(),
		AXISMUNDI_FONTS_NOTO_CJK_JP_VERSION
	);
}
add_action( 'enqueue_block_assets', 'axismundi_fonts_noto_cjk_jp_enqueue' );

/**
 * Register the Noto CJK Japanese family as a Font Library collection.
 *
 * Discovery / management surface in Site Editor > Styles > Typography > Manage
 * fonts. The @font-face provider above is what actually renders the theme's
 * stacks; this collection lets users browse and explicitly install/select the
 * families. Available since WordPress 6.5.
 */
function axismundi_fonts_noto_cjk_jp_collection() : void {
	if ( ! function_exists( 'wp_register_font_collection' ) ) {
		return;
	}

	$sans_src = plugins_url( 'assets/fonts/noto-sans-jp/axismundi-noto-sans-jp.woff2', __FILE__ );
	$serif_src = plugins_url( 'assets/fonts/noto-serif-jp/axismundi-noto-serif-jp.woff2', __FILE__ );

	wp_register_font_collection(
		'axismundi-fonts-noto-cjk-jp',
		array(
			'name'          => __( 'Axismundi Fonts: Noto CJK Japanese', 'axismundi-fonts-noto-cjk-jp' ),
			'description'   => __( 'Noto Sans JP and Noto Serif JP for the Axismundi theme.', 'axismundi-fonts-noto-cjk-jp' ),
			'categories'    => array(
				array(
					'name' => __( 'Japanese', 'axismundi-fonts-noto-cjk-jp' ),
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
								'fontFamily'  => 'Noto Sans JP',
								'fontStyle'   => 'normal',
								'fontWeight'  => '100 900',
								'fontDisplay' => 'swap',
								'src'         => $sans_src,
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
								'fontFamily'  => 'Noto Serif JP',
								'fontStyle'   => 'normal',
								'fontWeight'  => '100 900',
								'fontDisplay' => 'swap',
								'src'         => $serif_src,
							),
						),
					),
				),
			),
		)
	);
}
add_action( 'init', 'axismundi_fonts_noto_cjk_jp_collection' );
