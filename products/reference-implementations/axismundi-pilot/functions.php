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
			file_exists( get_template_directory() . '/assets/styles/tokens.css' ) ? 'assets/styles/tokens.css' : null,
			file_exists( get_template_directory() . '/assets/styles/base.css' ) ? 'assets/styles/base.css' : null,
			file_exists( get_template_directory() . '/assets/styles/icons.css' ) ? 'assets/styles/icons.css' : null,
			file_exists( get_template_directory() . '/assets/styles/components.css' ) ? 'assets/styles/components.css' : null,
			file_exists( get_template_directory() . '/assets/styles/blocks.css' ) ? 'assets/styles/blocks.css' : null,
			file_exists( get_template_directory() . '/assets/styles/prose.css' ) ? 'assets/styles/prose.css' : null,
		)
	);

	if ( ! empty( $editor_styles ) ) {
		add_editor_style( array_values( $editor_styles ) );
	}
}
add_action( 'after_setup_theme', 'axismundi_pilot_setup' );

/**
 * Enqueue copied Pilot assets when Phase 2B has produced them.
 */
function axismundi_pilot_enqueue_assets() : void {
	$styles = array(
		'axismundi-pilot-fonts'      => array( 'assets/styles/fonts.css', array() ),
		'axismundi-pilot-tokens'     => array( 'assets/styles/tokens.css', array( 'axismundi-pilot-fonts' ) ),
		'axismundi-pilot-base'       => array( 'assets/styles/base.css', array( 'axismundi-pilot-tokens' ) ),
		'axismundi-pilot-icons'      => array( 'assets/styles/icons.css', array( 'axismundi-pilot-fonts', 'axismundi-pilot-tokens' ) ),
		'axismundi-pilot-components' => array( 'assets/styles/components.css', array( 'axismundi-pilot-base', 'axismundi-pilot-icons' ) ),
		'axismundi-pilot-blocks'     => array( 'assets/styles/blocks.css', array( 'axismundi-pilot-components' ) ),
		'axismundi-pilot-prose'      => array( 'assets/styles/prose.css', array( 'axismundi-pilot-blocks' ) ),
	);

	foreach ( $styles as $handle => $style ) {
		$uri = axismundi_pilot_asset_uri( $style[0] );

		if ( null === $uri ) {
			continue;
		}

		wp_enqueue_style( $handle, $uri, $style[1], AXISMUNDI_PILOT_VERSION );
	}
}
add_action( 'wp_enqueue_scripts', 'axismundi_pilot_enqueue_assets' );

/**
 * Register block styles in Phase 2D.
 *
 * Intentionally empty in Phase 2A. No custom blocks are registered by this
 * theme.
 */
function axismundi_pilot_register_block_styles() : void {
}
add_action( 'init', 'axismundi_pilot_register_block_styles' );
