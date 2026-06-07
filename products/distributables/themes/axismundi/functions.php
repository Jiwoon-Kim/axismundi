<?php
/**
 * Axismundi — functions.php
 *
 * Standalone Material Design 3 block theme. Phase 0 keeps this file deliberately
 * small: the submission-scanner theme supports and a minimal asset-enqueue
 * skeleton. The token / style cascade and font registration land in Phase 2.
 *
 * @package Axismundi
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'AXISMUNDI_VERSION' ) ) {
	define( 'AXISMUNDI_VERSION', '0.1.0' );
}

/**
 * Resolve a theme-relative asset path only when the file is present.
 *
 * @param string $relative_path Theme-relative path.
 * @return string|null Theme URI, or null when the asset is not present yet.
 */
function axismundi_asset_uri( string $relative_path ) : ?string {
	$relative_path = ltrim( $relative_path, '/' );
	$absolute_path = get_theme_file_path( $relative_path );

	if ( ! file_exists( $absolute_path ) ) {
		return null;
	}

	return get_theme_file_uri( $relative_path );
}

/**
 * Theme setup.
 *
 * A standalone block theme is recognized via templates/index.html, but the
 * WordPress.org upload scanner inspects the ZIP in isolation, so the common
 * theme supports are declared explicitly. wp-block-styles is intentionally NOT
 * enabled — Axismundi owns its core block appearance via the M3 contract.
 */
function axismundi_setup() : void {
	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'align-wide' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	load_theme_textdomain( 'axismundi', get_template_directory() . '/languages' );

	// Mirror the runtime token + utility CSS into the editor canvas so Global Styles
	// previews (e.g. the Shadows section) resolve the same --md-sys-* custom
	// properties as the front end. Fonts auto-load from theme.json in both contexts.
	add_editor_style(
		array_values(
			array_filter(
				array(
					file_exists( get_template_directory() . '/assets/styles/tokens.ref.css' ) ? 'assets/styles/tokens.ref.css' : null,
					file_exists( get_template_directory() . '/assets/styles/tokens.sys.color.light.css' ) ? 'assets/styles/tokens.sys.color.light.css' : null,
					file_exists( get_template_directory() . '/assets/styles/tokens.sys.color.dark.css' ) ? 'assets/styles/tokens.sys.color.dark.css' : null,
					file_exists( get_template_directory() . '/assets/styles/tokens.sys.elevation.css' ) ? 'assets/styles/tokens.sys.elevation.css' : null,
					file_exists( get_template_directory() . '/assets/styles/icons.css' ) ? 'assets/styles/icons.css' : null,
				)
			)
		)
	);
}
add_action( 'after_setup_theme', 'axismundi_setup' );

/**
 * Enqueue Axismundi runtime styles, ordered, only when the files exist.
 *
 * Skeleton only in Phase 0 — the M3 token + style cascade (tokens.*, foundation,
 * blocks, icons, theme-switcher) is populated in Phase 2. Handles are enqueued in
 * dependency order so the cascade stays explicit.
 */
function axismundi_enqueue_assets() : void {
	$styles = array(
		// M3 token layers (literals in ref; downstream var() in sys), in dependency
		// order so the cascade stays explicit: ref palette -> color roles
		// (light, then dark override) -> elevation -> utilities.
		'axismundi-tokens-ref'         => array( 'assets/styles/tokens.ref.css', array() ),
		'axismundi-tokens-color-light' => array( 'assets/styles/tokens.sys.color.light.css', array( 'axismundi-tokens-ref' ) ),
		'axismundi-tokens-color-dark'  => array( 'assets/styles/tokens.sys.color.dark.css', array( 'axismundi-tokens-color-light' ) ),
		'axismundi-tokens-elevation'   => array( 'assets/styles/tokens.sys.elevation.css', array( 'axismundi-tokens-color-dark' ) ),
		// Material Symbols icon utility (the font auto-loads from theme.json).
		'axismundi-icons'              => array( 'assets/styles/icons.css', array( 'axismundi-tokens-elevation' ) ),
	);

	foreach ( $styles as $handle => $style ) {
		$uri = axismundi_asset_uri( $style[0] );
		if ( null === $uri ) {
			continue;
		}
		wp_enqueue_style( $handle, $uri, $style[1], AXISMUNDI_VERSION );
	}
}
add_action( 'wp_enqueue_scripts', 'axismundi_enqueue_assets' );

/**
 * Load the color/elevation token vars into the block-editor UI document.
 *
 * add_editor_style() reaches the editor CANVAS iframe, but the Global Styles
 * sidebar (e.g. the colour-palette swatches) renders in the main editor document,
 * which would otherwise have no --md-sys-color-* custom properties — so the
 * var()-based palette swatches would resolve to nothing. Enqueue the token layers
 * here so the swatches and other UI previews render the real M3 colours.
 */
function axismundi_enqueue_editor_ui_assets() : void {
	$prev = '';
	foreach ( array(
		'axismundi-editor-tokens-ref'         => 'assets/styles/tokens.ref.css',
		'axismundi-editor-tokens-color-light' => 'assets/styles/tokens.sys.color.light.css',
		'axismundi-editor-tokens-color-dark'  => 'assets/styles/tokens.sys.color.dark.css',
		'axismundi-editor-tokens-elevation'   => 'assets/styles/tokens.sys.elevation.css',
	) as $handle => $rel ) {
		$uri = axismundi_asset_uri( $rel );
		if ( null === $uri ) {
			continue;
		}
		wp_enqueue_style( $handle, $uri, '' === $prev ? array() : array( $prev ), AXISMUNDI_VERSION );
		$prev = $handle;
	}
}
add_action( 'enqueue_block_editor_assets', 'axismundi_enqueue_editor_ui_assets' );
