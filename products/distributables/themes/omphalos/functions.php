<?php
/**
 * Omphalos — functions.php
 *
 * Twenty Twenty-Five child theme that grounds the Axismundi design language on
 * WordPress. Behaviour is ported from the axismundi-pilot proof theme with an
 * `omphalos_` prefix; it registers no custom blocks.
 *
 * @package Omphalos
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'OMPHALOS_VERSION' ) ) {
	define( 'OMPHALOS_VERSION', '0.1.0' );
}

/**
 * Resolve a child-theme-relative asset path only when the file is present.
 *
 * @param string $relative_path Theme-relative path.
 * @return string|null Theme URI or null when the asset is not present.
 */
function omphalos_asset_uri( string $relative_path ) : ?string {
	$relative_path = ltrim( $relative_path, '/' );
	$absolute_path = get_stylesheet_directory() . '/' . $relative_path;

	if ( ! file_exists( $absolute_path ) ) {
		return null;
	}

	return get_stylesheet_directory_uri() . '/' . $relative_path;
}

/**
 * Child theme setup.
 */
function omphalos_setup() : void {
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );

	load_theme_textdomain( 'omphalos', get_stylesheet_directory() . '/languages' );

	// Editor canvas mirrors the front-end token + style cascade when present.
	$editor_styles = array_values(
		array_filter(
			array(
				file_exists( get_stylesheet_directory() . '/assets/styles/tokens.ref.css' ) ? 'assets/styles/tokens.ref.css' : null,
				file_exists( get_stylesheet_directory() . '/assets/styles/tokens.sys.light.css' ) ? 'assets/styles/tokens.sys.light.css' : null,
				file_exists( get_stylesheet_directory() . '/assets/styles/tokens.sys.core.css' ) ? 'assets/styles/tokens.sys.core.css' : null,
				file_exists( get_stylesheet_directory() . '/assets/styles/tokens.comp.css' ) ? 'assets/styles/tokens.comp.css' : null,
				file_exists( get_stylesheet_directory() . '/assets/styles/tokens.sys.dark.css' ) ? 'assets/styles/tokens.sys.dark.css' : null,
			)
		)
	);

	if ( ! empty( $editor_styles ) ) {
		add_editor_style( $editor_styles );
	}
}
add_action( 'after_setup_theme', 'omphalos_setup' );

/**
 * Enqueue Omphalos runtime styles, ordered, only when the files exist.
 *
 * The parent (Twenty Twenty-Five) stylesheet loads automatically for block
 * themes; this layers the Axismundi token + style cascade on top.
 */
function omphalos_enqueue_assets() : void {
	// Canonical Axismundi token load order:
	// ref -> sys.light -> sys.core -> comp -> sys.dark.
	$styles = array(
		'omphalos-tokens-ref'       => array( 'assets/styles/tokens.ref.css', array() ),
		'omphalos-tokens-sys-light' => array( 'assets/styles/tokens.sys.light.css', array( 'omphalos-tokens-ref' ) ),
		'omphalos-tokens-sys-core'  => array( 'assets/styles/tokens.sys.core.css', array( 'omphalos-tokens-sys-light' ) ),
		'omphalos-tokens-comp'      => array( 'assets/styles/tokens.comp.css', array( 'omphalos-tokens-sys-core' ) ),
		'omphalos-tokens-sys-dark'  => array( 'assets/styles/tokens.sys.dark.css', array( 'omphalos-tokens-comp' ) ),
	);

	$previous = array();
	foreach ( $styles as $handle => $style ) {
		$uri = omphalos_asset_uri( $style[0] );
		if ( null === $uri ) {
			continue;
		}
		wp_enqueue_style( $handle, $uri, $style[1], OMPHALOS_VERSION );
		$previous[] = $handle;
	}
}
add_action( 'wp_enqueue_scripts', 'omphalos_enqueue_assets' );

/**
 * Register Omphalos block pattern categories.
 */
function omphalos_register_pattern_categories() : void {
	register_block_pattern_category(
		'omphalos',
		array( 'label' => __( 'Omphalos', 'omphalos' ) )
	);
}
add_action( 'init', 'omphalos_register_pattern_categories' );

// Attachment media object templates (ported from axismundi-pilot).
$omphalos_attachment = get_stylesheet_directory() . '/inc/attachment.php';
if ( file_exists( $omphalos_attachment ) ) {
	require_once $omphalos_attachment;
}
