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
	define( 'AXISMUNDI_PILOT_VERSION', '0.1.0-pilot' );
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

/**
 * Add the explicit theme auto state to the Pilot front-end root element.
 *
 * Pilot-only BACKLOG #22 evidence. Do not copy this filter into distributable
 * themes without an explicit distributable skeleton bootstrap decision.
 *
 * @param string $output Existing language attributes.
 * @return string Language attributes with a default data-theme when absent.
 */
function axismundi_pilot_language_attributes( string $output ) : string {
	if ( is_admin() || false !== strpos( $output, 'data-theme=' ) ) {
		return $output;
	}

	return trim( $output . ' data-theme="auto"' );
}
add_filter( 'language_attributes', 'axismundi_pilot_language_attributes', 20 );

/**
 * Enqueue copied Pilot assets when Phase 2B has produced them.
 */
function axismundi_pilot_enqueue_assets() : void {
	$styles = array(
		'axismundi-pilot-fonts'            => array( 'assets/styles/fonts.css', array() ),
		'axismundi-pilot-tokens-ref'       => array( 'assets/styles/tokens.ref.css', array( 'axismundi-pilot-fonts' ) ),
		'axismundi-pilot-tokens-sys-light' => array( 'assets/styles/tokens.sys.light.css', array( 'axismundi-pilot-tokens-ref' ) ),
		'axismundi-pilot-tokens-comp'      => array( 'assets/styles/tokens.comp.css', array( 'axismundi-pilot-tokens-sys-light' ) ),
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
}
add_action( 'init', 'axismundi_pilot_register_pattern_categories' );
