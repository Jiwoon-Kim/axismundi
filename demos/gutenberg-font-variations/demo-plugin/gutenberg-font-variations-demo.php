<?php
/**
 * Plugin Name: Gutenberg Font Variations Demo
 * Description: Fixture for the Gutenberg font-variation-settings prototype. Adds Roboto Flex with its full fvar table as face `axes`, turns the Font variations panel on for the site and off for Heading, and adds a demo post.
 * Version: 0.2.0
 * Requires at least: 7.0
 * License: GPL-2.0-or-later
 *
 * Roboto Flex is licensed under the SIL Open Font License 1.1: see assets/OFL.txt.
 *
 * The panel shows the axes a face declares, minus the ones with a CSS property
 * of their own, so there is no per-axis list here: `settings.typography
 * .fontVariations` is a boolean, and a block turns it off by setting it false.
 *
 * @package gutenberg-font-variations-demo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Roboto Flex fvar table, read from assets/RobotoFlex.woff2 with fontTools:
 * tag, min, default, max.
 *
 * @return array Axes in the `fontFace` shape.
 */
function gutenberg_font_variations_demo_axes() {
	$axes = array();
	foreach ( array(
		array( 'opsz', 8, 14, 144 ),
		array( 'wght', 100, 400, 1000 ),
		array( 'GRAD', -200, 0, 150 ),
		array( 'wdth', 25, 100, 151 ),
		array( 'slnt', -10, 0, 0 ),
		array( 'XOPQ', 27, 96, 175 ),
		array( 'YOPQ', 25, 79, 135 ),
		array( 'XTRA', 323, 468, 603 ),
		array( 'YTUC', 528, 712, 760 ),
		array( 'YTLC', 416, 514, 570 ),
		array( 'YTAS', 649, 750, 854 ),
		array( 'YTDE', -305, -203, -98 ),
		array( 'YTFI', 560, 738, 788 ),
	) as $axis ) {
		$axes[] = array(
			'tag'     => $axis[0],
			'min'     => $axis[1],
			'default' => $axis[2],
			'max'     => $axis[3],
		);
	}
	return $axes;
}

add_filter(
	'wp_theme_json_data_theme',
	static function ( $theme_json ) {
		$data     = $theme_json->get_data();
		$families = $data['settings']['typography']['fontFamilies']['theme'] ?? array();

		$families[] = array(
			'name'       => 'Roboto Flex',
			'slug'       => 'roboto-flex',
			'fontFamily' => '"Roboto Flex", sans-serif',
			/*
			 * One binary, two faces: normal keeps upright matching, and the
			 * oblique descriptor maps a CSS oblique request onto `slnt`. The
			 * shared URL is fetched once. The range reads 0deg to 10deg, not
			 * -10deg to 0deg: `font-style: oblique <angle>` takes the angle with
			 * the sign flipped, and declared the other way round no oblique
			 * request slants the text at all.
			 */
			'fontFace'   => array(
				array(
					'fontFamily'  => 'Roboto Flex',
					'fontStyle'   => 'normal',
					'fontWeight'  => '100 1000',
					'fontStretch' => '25% 151%',
					'src'         => array( plugins_url( 'assets/RobotoFlex.woff2', __FILE__ ) ),
					'axes'        => gutenberg_font_variations_demo_axes(),
				),
				array(
					'fontFamily'  => 'Roboto Flex',
					'fontStyle'   => 'oblique 0deg 10deg',
					'fontWeight'  => '100 1000',
					'fontStretch' => '25% 151%',
					'src'         => array( plugins_url( 'assets/RobotoFlex.woff2', __FILE__ ) ),
					'axes'        => gutenberg_font_variations_demo_axes(),
				),
			),
		);

		return $theme_json->update_with(
			array(
				'version'  => 3,
				'settings' => array(
					'typography' => array(
						'fontFamilies'   => $families,
						// Whether the panel is offered at all. Not a list of axes:
						// the faces already declare those.
						'fontVariations' => true,
					),
					'blocks'     => array(
						// The same font, with the panel turned off for this block.
						'core/heading' => array(
							'typography' => array(
								'fontVariations' => false,
							),
						),
					),
				),
				'styles'   => array(
					'typography' => array(
						'fontFamily' => 'var:preset|font-family|roboto-flex',
					),
				),
			)
		);
	}
);

/**
 * Creates the demo post, or restores its content.
 *
 * @return int Post ID.
 */
function gutenberg_font_variations_demo_post() {
	$content  = '<!-- wp:heading --><h2 class="wp-block-heading">Heading: the panel is turned off for this block.</h2><!-- /wp:heading -->';
	$content .= '<!-- wp:paragraph --><p>Paragraph in Roboto Flex: Grade and the other custom axes. Weight, width, slant and optical size are registered axes and stay out of this panel.</p><!-- /wp:paragraph -->';
	$content .= '<!-- wp:paragraph --><p>Paragraph to switch to another font: the axis values on this block are cleared with it.</p><!-- /wp:paragraph -->';
	$content .= '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>List: no font variation settings support, so no panel.</li><!-- /wp:list-item --></ul><!-- /wp:list -->';

	$existing = get_page_by_path( 'font-variations-demo', OBJECT, 'post' );
	if ( $existing ) {
		wp_update_post(
			array(
				'ID'           => $existing->ID,
				'post_content' => $content,
			)
		);
		return $existing->ID;
	}
	return (int) wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_title'   => 'Font variations demo',
			'post_name'    => 'font-variations-demo',
			'post_content' => $content,
		)
	);
}

register_activation_hook( __FILE__, 'gutenberg_font_variations_demo_post' );

// `/wp-admin/?font-variations-demo` opens the demo post in the editor.
add_action(
	'admin_init',
	static function () {
		if ( ! isset( $_GET['font-variations-demo'] ) || ! current_user_can( 'edit_posts' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$post = get_page_by_path( 'font-variations-demo', OBJECT, 'post' );
		$id   = $post ? $post->ID : gutenberg_font_variations_demo_post();
		wp_safe_redirect( admin_url( 'post.php?post=' . $id . '&action=edit' ) );
		exit;
	}
);
