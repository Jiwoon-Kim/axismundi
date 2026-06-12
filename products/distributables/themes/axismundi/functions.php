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
	define( 'AXISMUNDI_VERSION', '0.1.13' );
}

// Theme-internal attachment page renderer. WordPress core/post-content only
// outputs the attachment description, so this filter prepends the actual file
// on attachment templates (image / audio / video + captions / download fallback).
require_once get_template_directory() . '/inc/attachment-media.php';

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
	// previews resolve the same --md-sys-* custom properties as the front end.
	// Fonts auto-load from theme.json in both contexts.
	add_editor_style(
		array_values(
			array_filter(
				array(
					'style.css',
					file_exists( get_template_directory() . '/assets/styles/tokens.ref.css' ) ? 'assets/styles/tokens.ref.css' : null,
					file_exists( get_template_directory() . '/assets/styles/tokens.sys.color.light.css' ) ? 'assets/styles/tokens.sys.color.light.css' : null,
					file_exists( get_template_directory() . '/assets/styles/tokens.sys.color.dark.css' ) ? 'assets/styles/tokens.sys.color.dark.css' : null,
					file_exists( get_template_directory() . '/assets/styles/tokens.sys.elevation.css' ) ? 'assets/styles/tokens.sys.elevation.css' : null,
					file_exists( get_template_directory() . '/assets/styles/tokens.sys.state.css' ) ? 'assets/styles/tokens.sys.state.css' : null,
					file_exists( get_template_directory() . '/assets/styles/tokens.sys.motion.css' ) ? 'assets/styles/tokens.sys.motion.css' : null,
					file_exists( get_template_directory() . '/assets/styles/icons.css' ) ? 'assets/styles/icons.css' : null,
					file_exists( get_template_directory() . '/assets/styles/components.button.css' ) ? 'assets/styles/components.button.css' : null,
					file_exists( get_template_directory() . '/assets/styles/blocks.text.css' ) ? 'assets/styles/blocks.text.css' : null,
					file_exists( get_template_directory() . '/assets/styles/blocks.table.css' ) ? 'assets/styles/blocks.table.css' : null,
					file_exists( get_template_directory() . '/assets/styles/blocks.accordion.css' ) ? 'assets/styles/blocks.accordion.css' : null,
				)
			)
		)
	);
}
add_action( 'after_setup_theme', 'axismundi_setup' );

/**
 * Enable attachment pages.
 *
 * WordPress 6.4+ ships attachment pages disabled by default
 * (`wp_attachment_pages_enabled` = 0), so `redirect_canonical()` 301-redirects
 * every attachment URL straight to the raw file. Axismundi treats the
 * attachment page as a first-class media surface (templates/attachment.html),
 * so force the flag on while the theme is active.
 *
 * This filters the option at read time rather than writing it to the database:
 * the behavior is theme-scoped and reverts the moment the theme is switched
 * away, without mutating the site's stored setting. Both the stored-value
 * (`option_*`) and missing-value (`default_option_*`) reads are covered, and
 * the string '1' satisfies core's strict `'1' === ` comparisons (admin "View
 * attachment page" labels) as well as the boolean redirect guard.
 *
 * @return string Always '1'.
 */
function axismundi_enable_attachment_pages() : string {
	return '1';
}
add_filter( 'option_wp_attachment_pages_enabled', 'axismundi_enable_attachment_pages' );
add_filter( 'default_option_wp_attachment_pages_enabled', 'axismundi_enable_attachment_pages' );

/**
 * Enqueue Axismundi runtime styles, ordered, only when the files exist.
 *
 * Skeleton only in Phase 0 — the M3 token + style cascade (tokens.*, foundation,
 * blocks, icons, theme-switcher) is populated in Phase 2. Handles are enqueued in
 * dependency order so the cascade stays explicit.
 */
function axismundi_enqueue_assets() : void {
	// Global HTML semantic glue (mark / abbr, etc.) — the theme's root style.css,
	// enqueued explicitly (TT5-style) so standard inline elements have a baseline
	// regardless of source. Block-scoped fixes stay in assets/styles/*.css below.
	wp_enqueue_style( 'axismundi-style', get_stylesheet_uri(), array(), AXISMUNDI_VERSION );

	$styles = array(
		// M3 token layers (literals in ref; downstream var() in sys), in dependency
		// order so the cascade stays explicit: ref palette -> color roles
		// (light, then dark override) -> elevation -> state -> utilities.
		'axismundi-tokens-ref'         => array( 'assets/styles/tokens.ref.css', array() ),
		'axismundi-tokens-color-light' => array( 'assets/styles/tokens.sys.color.light.css', array( 'axismundi-tokens-ref' ) ),
		'axismundi-tokens-color-dark'  => array( 'assets/styles/tokens.sys.color.dark.css', array( 'axismundi-tokens-color-light' ) ),
		'axismundi-tokens-elevation'   => array( 'assets/styles/tokens.sys.elevation.css', array( 'axismundi-tokens-color-dark' ) ),
		'axismundi-tokens-state'       => array( 'assets/styles/tokens.sys.state.css', array( 'axismundi-tokens-elevation' ) ),
		'axismundi-tokens-motion'      => array( 'assets/styles/tokens.sys.motion.css', array( 'axismundi-tokens-state' ) ),
		// Material Symbols icon utility (the font auto-loads from theme.json).
		'axismundi-icons'              => array( 'assets/styles/icons.css', array( 'axismundi-tokens-motion' ) ),
		// Component layer — only what theme.json cannot express (e.g. motion).
		'axismundi-button'             => array( 'assets/styles/components.button.css', array( 'axismundi-tokens-motion', 'axismundi-icons' ) ),
		// Core text block refinements that need pseudo-elements / specificity.
		'axismundi-blocks-text'        => array( 'assets/styles/blocks.text.css', array( 'axismundi-tokens-motion' ) ),
		// Core/raw table refinements that need cell-level selectors.
		'axismundi-blocks-table'       => array( 'assets/styles/blocks.table.css', array( 'axismundi-blocks-text' ) ),
		// core/accordion family — M3 contained list; cross-block state/divider CSS.
		'axismundi-blocks-accordion'   => array( 'assets/styles/blocks.accordion.css', array( 'axismundi-blocks-table' ) ),
	);

	if ( is_attachment() ) {
		$styles['axismundi-attachment'] = array( 'assets/styles/attachment.css', array( 'axismundi-blocks-table' ) );
	}

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
 * Load token vars into the block-editor UI document.
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
		'axismundi-editor-tokens-state'       => 'assets/styles/tokens.sys.state.css',
		'axismundi-editor-tokens-motion'      => 'assets/styles/tokens.sys.motion.css',
	) as $handle => $rel ) {
		$uri = axismundi_asset_uri( $rel );
		if ( null === $uri ) {
			continue;
		}
		wp_enqueue_style( $handle, $uri, '' === $prev ? array() : array( $prev ), AXISMUNDI_VERSION );
		$prev = $handle;
	}

	// The token CSS sets `color-scheme` on :root so the front and editor CANVAS
	// have native controls (scrollbars/form fields) that follow the theme. In
	// THIS document, though, :root is the WordPress admin chrome — color-scheme
	// must not leak here or the editor's panels get dark native scrollbars on a
	// light WP UI. It leaks two ways: data-theme="dark" (handled by the switcher
	// plugin keeping data-theme off the admin root) AND the OS-dark fallback
	// (`:root:not([data-theme])`), which matches the admin root whenever the OS
	// is dark. The swatches only need the colour tokens, so reset the chrome's
	// scheme to normal. enqueue_block_editor_assets is editor-only, so the rest
	// of wp-admin is untouched.
	if ( '' !== $prev ) {
		wp_add_inline_style( $prev, ':root{color-scheme:normal !important;}' );
	}
}
add_action( 'enqueue_block_editor_assets', 'axismundi_enqueue_editor_ui_assets' );
