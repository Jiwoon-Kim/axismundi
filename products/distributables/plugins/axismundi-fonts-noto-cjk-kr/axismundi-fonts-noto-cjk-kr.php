<?php
/**
 * Plugin Name:       Axismundi Fonts: Noto CJK Korean
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-fonts-noto-cjk-kr
 * Description:       Optional Korean web-font provider for the Axismundi theme — supplies Noto Sans KR and Noto Serif KR, fills the theme's CJK fallback slot for Korean documents, and registers them as a Font Library collection.
 * Version:           0.1.3
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-fonts-noto-cjk-kr
 *
 * @package AxismundiFontsNotoCjkKr
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'AXISMUNDI_FONTS_NOTO_CJK_KR_VERSION' ) ) {
	define( 'AXISMUNDI_FONTS_NOTO_CJK_KR_VERSION', '0.1.3' );
}

/**
 * Enqueue the @font-face provider on the front end and in the block editor.
 *
 * enqueue_block_assets fires for both the front end and the editor (including
 * the editor canvas). CSS activates the provider only when the document root is
 * Korean; the logged-in user's admin-interface language is not a content-font
 * signal.
 */
function axismundi_fonts_noto_cjk_kr_enqueue() : void {
	wp_enqueue_style(
		'axismundi-fonts-noto-cjk-kr',
		plugins_url( 'assets/styles/fonts.css', __FILE__ ),
		array(),
		AXISMUNDI_FONTS_NOTO_CJK_KR_VERSION
	);
}
add_action( 'enqueue_block_assets', 'axismundi_fonts_noto_cjk_kr_enqueue' );

/**
 * Register the Noto CJK Korean family as a Font Library collection.
 *
 * Discovery / management surface in Site Editor > Styles > Typography > Manage
 * fonts. The @font-face provider above is what actually renders the theme's
 * stacks; this collection lets users browse and explicitly install/select the
 * families. Available since WordPress 6.5.
 */
function axismundi_fonts_noto_cjk_kr_collection() : void {
	if ( ! function_exists( 'wp_register_font_collection' ) ) {
		return;
	}

	$sans_src = plugins_url( 'assets/fonts/noto-sans-kr/axismundi-noto-sans-kr.woff2', __FILE__ );
	$serif_src = plugins_url( 'assets/fonts/noto-serif-kr/axismundi-noto-serif-kr.woff2', __FILE__ );

	wp_register_font_collection(
		'axismundi-fonts-noto-cjk-kr',
		array(
			'name'          => __( 'Axismundi Fonts: Noto CJK Korean', 'axismundi-fonts-noto-cjk-kr' ),
			'description'   => __( 'Noto Sans KR and Noto Serif KR for the Axismundi theme.', 'axismundi-fonts-noto-cjk-kr' ),
			'categories'    => array(
				array(
					'name' => __( 'Korean', 'axismundi-fonts-noto-cjk-kr' ),
					'slug' => 'korean',
				),
			),
			'font_families' => array(
				array(
					'categories'           => array( 'korean' ),
					'font_family_settings' => array(
						'name'       => 'Noto Sans KR',
						'slug'       => 'noto-sans-kr',
						'fontFamily' => '"Noto Sans KR", sans-serif',
						'fontFace'   => array(
							array(
								'fontFamily'  => 'Noto Sans KR',
								'fontStyle'   => 'normal',
								'fontWeight'  => '100 900',
								'fontDisplay' => 'swap',
								'src'         => $sans_src,
							),
						),
					),
				),
				array(
					'categories'           => array( 'korean' ),
					'font_family_settings' => array(
						'name'       => 'Noto Serif KR',
						'slug'       => 'noto-serif-kr',
						'fontFamily' => '"Noto Serif KR", serif',
						'fontFace'   => array(
							array(
								'fontFamily'  => 'Noto Serif KR',
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
add_action( 'init', 'axismundi_fonts_noto_cjk_kr_collection' );
