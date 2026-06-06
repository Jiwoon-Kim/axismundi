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
				file_exists( get_stylesheet_directory() . '/assets/styles/foundation.css' ) ? 'assets/styles/foundation.css' : null,
				file_exists( get_stylesheet_directory() . '/assets/styles/prose.css' ) ? 'assets/styles/prose.css' : null,
				file_exists( get_stylesheet_directory() . '/assets/styles/blocks.css' ) ? 'assets/styles/blocks.css' : null,
				file_exists( get_stylesheet_directory() . '/assets/styles/icons.css' ) ? 'assets/styles/icons.css' : null,
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
	// Token layers, then the scoped style layers. In an FSE child theme the
	// global element baseline (html/body/headings/links) is owned by WordPress
	// core + theme.json + the parent theme, so the lab's global base.css reset
	// is intentionally NOT loaded. prose.css re-contracts the prose layer for
	// FSE, scoped to the long-form surface (.wp-block-post-content); blocks.css
	// layers core-block chrome (drop cap, pullquote, verse, table variations)
	// on top, also scoped to post content so nothing leaks into UI chrome.
	// components.css lands later in Phase 8.
	$styles = array(
		'omphalos-tokens-ref'       => array( 'assets/styles/tokens.ref.css', array() ),
		'omphalos-tokens-sys-light' => array( 'assets/styles/tokens.sys.light.css', array( 'omphalos-tokens-ref' ) ),
		'omphalos-tokens-sys-core'  => array( 'assets/styles/tokens.sys.core.css', array( 'omphalos-tokens-sys-light' ) ),
		'omphalos-tokens-comp'      => array( 'assets/styles/tokens.comp.css', array( 'omphalos-tokens-sys-core' ) ),
		'omphalos-tokens-sys-dark'  => array( 'assets/styles/tokens.sys.dark.css', array( 'omphalos-tokens-comp' ) ),
		'omphalos-foundation'       => array( 'assets/styles/foundation.css', array( 'omphalos-tokens-sys-dark' ) ),
		'omphalos-prose'            => array( 'assets/styles/prose.css', array( 'omphalos-foundation' ) ),
		'omphalos-blocks'           => array( 'assets/styles/blocks.css', array( 'omphalos-prose' ) ),
		// Global Material Symbols utility — front + editor (added to add_editor_style
		// in omphalos_setup too) so theme-control icons render the same in both.
		'omphalos-icons'            => array( 'assets/styles/icons.css', array( 'omphalos-blocks' ) ),
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
 * Comments chrome runtime.
 *
 * WordPress core's `comment-reply` script moves the single `#respond` form under the
 * target comment. Omphalos keeps the root composer at the bottom for now; inline
 * per-comment composers are a future form/plugin lane. The tiny runtime here only
 * draws comment-thread connector paths from measured avatar positions.
 */
function omphalos_enqueue_comment_assets() : void {
	if ( is_admin() ) {
		return;
	}

	wp_dequeue_script( 'comment-reply' );

	$uri = omphalos_asset_uri( 'assets/scripts/comment-thread-connectors.js' );
	if ( null !== $uri ) {
		$path    = get_stylesheet_directory() . '/assets/scripts/comment-thread-connectors.js';
		$version = file_exists( $path ) ? (string) filemtime( $path ) : OMPHALOS_VERSION;
		wp_enqueue_script( 'omphalos-comment-connectors', $uri, array(), $version, true );
	}
}
add_action( 'wp_enqueue_scripts', 'omphalos_enqueue_comment_assets', 100 );

/**
 * Load the /embed/ Object Card stylesheet INSIDE the embed iframe document only
 * (the card our posts render as when embedded elsewhere). Self-contained and
 * token-aware-with-fallbacks; deliberately NO token CSS and NO @font-face are
 * enqueued into the iframe (font NAMES fall through). See
 * docs/EMBED-TEMPLATE-ROUTE.md §10. Article/post variant v1.
 */
function omphalos_enqueue_embed_styles() : void {
	$uri = omphalos_asset_uri( 'assets/styles/embed.css' );
	if ( null !== $uri ) {
		// Depend on core's `wp-embed-template` so our card styles print AFTER its
		// inline CSS and win on equal specificity (no !important needed).
		wp_enqueue_style( 'omphalos-embed', $uri, array( 'wp-embed-template' ), OMPHALOS_VERSION );
	}
}
add_action( 'enqueue_embed_scripts', 'omphalos_enqueue_embed_styles' );

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

/**
 * Register Omphalos block style variations.
 *
 * Only theme-owned variations are registered here. WordPress core already
 * ships core/quote "Plain" (is-style-plain) and core/table "Stripes"
 * (is-style-stripes); those need CSS (blocks.css) but no registration.
 */
function omphalos_register_block_styles() : void {
	register_block_style(
		'core/list',
		array(
			'name'  => 'list-segmented',
			'label' => __( 'Segmented list', 'omphalos' ),
		)
	);
	// core/image ships Default + Rounded itself. Add a "No rounding" option so
	// the M3 corner scale's `none` is selectable without killing the default
	// medium radius. CSS for all three is in blocks.css §1.
	register_block_style(
		'core/image',
		array(
			'name'  => 'no-rounding',
			'label' => __( 'No rounding', 'omphalos' ),
		)
	);
	// core/group as M3 Card. Bare group stays a plain layout container; only these
	// opt-in variations turn a group into a Card surface (filled / elevated /
	// outlined), so layout groups in patterns never accidentally read as cards.
	// CSS for all three is in blocks.css §8.
	foreach (
		array(
			'card-filled'   => __( 'Card (filled)', 'omphalos' ),
			'card-elevated' => __( 'Card (elevated)', 'omphalos' ),
			'card-outlined' => __( 'Card (outlined)', 'omphalos' ),
		) as $name => $label
	) {
		register_block_style(
			'core/group',
			array(
				'name'  => $name,
				'label' => $label,
			)
		);
	}
	// core/button as M3 Button. Core already ships `fill` (default) and `outline`
	// block styles, mapped to M3 Filled / Outlined in blocks.css §9 — only the
	// three missing M3 variants are registered here. The cross-surface button
	// base (.wp-element-button) lives in theme.json styles.elements.button +
	// blocks.css §9; these are the per-variant color treatments for core/button.
	//
	// Variation contract (three NON-redundant layers; same shape as core/group
	// card-* and core/image no-rounding):
	//   1. register_block_style() here    → the selectable name + label, and the
	//      `is-style-<slug>` class the front end keys on. Required for toolbar
	//      selection + blocks.css rendering.
	//   2. styles/blocks/<block>-<slug>.json partial → the Global Styles / Stylebook
	//      DISCOVERABILITY layer. Verified empirically: register_block_style() alone
	//      does NOT surface a variation in the Global Styles "Style variations"
	//      panel; removing the partial makes Tonal/Elevated/Connected vanish from
	//      the editor UI (front end still renders via blocks.css). So the partial is
	//      not a duplicate — it is what makes the variation editable/visible in FSE.
	//   3. blocks.css §9 → the actual visual treatment + hover/focus/active state
	//      layers (color-mix) + connected geometry. The partials carry base colour
	//      only; the state layers live here exclusively.
	// theme.json styles.blocks.*.variations inline is intentionally NOT used — it
	// merely duplicated the partials' base colour and was removed during cleanup.
	foreach (
		array(
			'tonal'    => __( 'Tonal', 'omphalos' ),
			'elevated' => __( 'Elevated', 'omphalos' ),
			'text'     => __( 'Text', 'omphalos' ),
		) as $name => $label
	) {
		register_block_style(
			'core/button',
			array(
				'name'  => $name,
				'label' => $label,
			)
		);
	}
	// core/buttons as a visual Button group approximation. This owns connected
	// geometry for a row of core/button children only; selected/toggle semantics
	// stay plugin/custom-markup territory.
	register_block_style(
		'core/buttons',
		array(
			'name'  => 'connected',
			'label' => __( 'Connected', 'omphalos' ),
		)
	);
	// core/separator — M3 inset dividers. Core ships default / wide / dots; the
	// theme adds the two inset variants. CSS in blocks.css §11.
	foreach (
		array(
			'divider-inset'        => __( 'Inset divider', 'omphalos' ),
			'divider-middle-inset' => __( 'Middle-inset divider', 'omphalos' ),
		) as $name => $label
	) {
		register_block_style(
			'core/separator',
			array(
				'name'  => $name,
				'label' => $label,
			)
		);
	}
	// core/query as M3 cards — SURFACE-only opt-in. The Query Loop post item is a flat
	// article by default (blocks.css §18b); this variation turns each item into a filled
	// card surface (reusing the §16 Latest Posts/RSS card ontology). It deliberately does
	// NOT own layout: list / grid / columns stay core-owned (the Query/Post-Template
	// List|Grid view + core's responsive collapse). CSS in blocks.css §18c.
	register_block_style(
		'core/query',
		array(
			'name'  => 'cards',
			'label' => __( 'Cards', 'omphalos' ),
		)
	);
}
add_action( 'init', 'omphalos_register_block_styles' );

/**
 * Surface this theme's name where the parent prints its own as the footer credit.
 *
 * Twenty Twenty-Five's footer patterns (footer, footer-columns, footer-newsletter,
 * page-portfolio-home) print a literal `Twenty Twenty-Five` credit via
 * esc_html_e( 'Twenty Twenty-Five', 'twentytwentyfive' ). That string is a
 * theme-chosen credit, not site-owner data, so an Omphalos child theme should
 * show "Omphalos" there. Filtering the parent textdomain's exact string (rather
 * than overriding/cloning the whole footer pattern) keeps the change to one line
 * and tracks parent footer markup updates. Returns the active theme's Name header
 * so it stays a single source of truth (style.css) and covers every parent footer
 * variant at once. Scoped to the gettext_twentytwentyfive hook, so only the
 * parent textdomain is affected.
 *
 * @param string $translation Translated text.
 * @param string $text        Original (untranslated) text.
 * @return string
 */
function omphalos_theme_name_credit( $translation, $text ) {
	if ( 'Twenty Twenty-Five' === $text ) {
		return wp_get_theme()->get( 'Name' );
	}
	return $translation;
}
add_filter( 'gettext_twentytwentyfive', 'omphalos_theme_name_credit', 10, 2 );

// Attachment media object templates (ported from axismundi-pilot).
$omphalos_attachment = get_stylesheet_directory() . '/inc/attachment.php';
if ( file_exists( $omphalos_attachment ) ) {
	require_once $omphalos_attachment;
}

// Theme switcher infrastructure (cache-safe data-theme head script; block later).
$omphalos_theme_switcher = get_stylesheet_directory() . '/inc/theme-switcher.php';
if ( file_exists( $omphalos_theme_switcher ) ) {
	require_once $omphalos_theme_switcher;
}
