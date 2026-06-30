<?php
/**
 * Plugin Name:       Axismundi Traditional Chinese Font Provider
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-traditional-chinese-font-provider
 * Description:       Optional Traditional Chinese web-font provider for the Axismundi theme — supplies Noto Sans TC, fills the theme's sans-serif CJK fallback slot for Traditional Chinese documents, and registers it as a Font Library collection.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-traditional-chinese-font-provider
 *
 * @package AxismundiTraditionalChineseFontProvider
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'AXISMUNDI_TRADITIONAL_CHINESE_FONT_PROVIDER_VERSION' ) ) {
	define( 'AXISMUNDI_TRADITIONAL_CHINESE_FONT_PROVIDER_VERSION', '0.1.0' );
}

/**
 * Enqueue the @font-face provider on the front end and in the block editor.
 *
 * enqueue_block_assets fires for both the front end and the editor (including
 * the editor canvas). CSS activates the provider only when the document is
 * Traditional Chinese; the logged-in user's admin-interface language is not a
 * content-font signal.
 */
function axismundi_traditional_chinese_font_provider_enqueue() : void {
	wp_enqueue_style(
		'axismundi-traditional-chinese-font-provider',
		plugins_url( 'assets/styles/fonts.css', __FILE__ ),
		array(),
		AXISMUNDI_TRADITIONAL_CHINESE_FONT_PROVIDER_VERSION
	);
}
add_action( 'enqueue_block_assets', 'axismundi_traditional_chinese_font_provider_enqueue' );

/**
 * Register the Noto CJK Traditional Chinese family as a Font Library collection.
 *
 * Discovery / management surface in Site Editor > Styles > Typography > Manage
 * fonts. The @font-face provider above is what actually renders the theme's
 * stacks; this collection lets users browse and explicitly install/select the
 * families. Available since WordPress 6.5.
 */
function axismundi_traditional_chinese_font_provider_collection() : void {
	if ( ! function_exists( 'wp_register_font_collection' ) ) {
		return;
	}

	$sans_src = plugins_url( 'assets/fonts/noto-sans-tc/axismundi-noto-sans-tc.woff2', __FILE__ );
	wp_register_font_collection(
		'axismundi-traditional-chinese-font-provider',
		array(
			'name'          => __( 'Axismundi Traditional Chinese Font Provider', 'axismundi-traditional-chinese-font-provider' ),
			'description'   => __( 'Noto Sans TC for the Axismundi theme.', 'axismundi-traditional-chinese-font-provider' ),
			'categories'    => array(
				array(
					'name' => __( 'Traditional Chinese', 'axismundi-traditional-chinese-font-provider' ),
					'slug' => 'traditional-chinese',
				),
			),
			'font_families' => array(
				array(
					'categories'           => array( 'traditional-chinese' ),
					'font_family_settings' => array(
						'name'       => 'Noto Sans TC',
						'slug'       => 'noto-sans-tc',
						'fontFamily' => '"Noto Sans TC", sans-serif',
						'fontFace'   => array(
							array(
								'fontFamily'  => 'Noto Sans TC',
								'fontStyle'   => 'normal',
								'fontWeight'  => '100 900',
								'fontDisplay' => 'swap',
								'src'         => $sans_src,
							),
						),
					),
				),
			),
		)
	);
}
add_action( 'init', 'axismundi_traditional_chinese_font_provider_collection' );
