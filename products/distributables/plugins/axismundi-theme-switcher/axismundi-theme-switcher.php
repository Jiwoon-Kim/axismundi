<?php
/**
 * Plugin Name:       Axismundi Theme Switcher
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-theme-switcher
 * Description:       Light / dark / auto theme switcher block and color-scheme bridge for Axismundi.
 * Version:           0.1.7
 * Requires at least: 7.1
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-theme-switcher
 *
 * @package AxismundiThemeSwitcher
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the axismundi/theme-switcher block.
 *
 * Unlike the Omphalos pilot, the Axismundi companion plugin owns both the block
 * UI and the cache-safe scheme bridge. The theme only exposes the data-theme
 * token selectors that this plugin toggles.
 *
 * @return void
 */
function axismundi_theme_switcher_register_block() : void {
	$dir = __DIR__ . '/blocks/theme-switcher';
	if ( file_exists( $dir . '/block.json' ) ) {
		register_block_type( $dir );
	}
}
add_action( 'init', 'axismundi_theme_switcher_register_block' );

/**
 * Print the breakpoint the `mobile` compression switches at.
 *
 * `cycleButtonVisibility: "mobile"` renders both surfaces and hides one with a
 * media query. A media query cannot read a custom property, so the breakpoint
 * has to be a literal -- but it does not have to be *our* literal. A theme
 * declares `settings.viewport`, and the same helper core/navigation uses turns
 * it into the query string, so the switcher compresses exactly where that
 * theme's own responsive values do. With no declaration the helper still
 * answers, with WordPress's own default.
 *
 * A theme can declare `tablet` alone, in which case there is no mobile
 * breakpoint to switch at and nothing is printed: the group then shows at every
 * width, which is what `off` does. Inventing a width here would be worse.
 *
 * @return void
 */
function axismundi_theme_switcher_enqueue_breakpoint_style() : void {
	if ( ! wp_style_is( 'axismundi-theme-switcher-style', 'registered' ) ) {
		return;
	}

	$axismundi_theme_switcher_settings = wp_get_global_settings();
	$axismundi_theme_switcher_viewport = $axismundi_theme_switcher_settings['viewport'] ?? null;

	// Gutenberg first, as core/navigation does: the plugin can carry a newer
	// implementation than the WordPress release it runs on.
	$axismundi_theme_switcher_queries = method_exists( 'WP_Theme_JSON_Gutenberg', 'get_viewport_media_queries' )
		? WP_Theme_JSON_Gutenberg::get_viewport_media_queries( $axismundi_theme_switcher_viewport )
		: WP_Theme_JSON::get_viewport_media_queries( $axismundi_theme_switcher_viewport );

	if ( empty( $axismundi_theme_switcher_queries['@mobile'] ) ) {
		return;
	}

	$axismundi_theme_switcher_css = $axismundi_theme_switcher_queries['@mobile'] . '{'
		. '.wp-block-axismundi-theme-switcher[data-cycle-visibility="mobile"] .axismundi-theme-switcher__cycle{display:inline-flex;}'
		. '.wp-block-axismundi-theme-switcher[data-cycle-visibility="mobile"] .axismundi-theme-switcher__group{display:none;}'
		. '}';

	wp_add_inline_style( 'axismundi-theme-switcher-style', $axismundi_theme_switcher_css );
}
add_action( 'wp_enqueue_scripts', 'axismundi_theme_switcher_enqueue_breakpoint_style', 20 );
add_action( 'enqueue_block_assets', 'axismundi_theme_switcher_enqueue_breakpoint_style', 20 );

/**
 * Hook the switcher after the header Navigation block.
 *
 * This keeps the Axismundi theme distributable standalone: the theme contains no
 * plugin block markup, and the switcher appears only when the companion plugin
 * is active.
 *
 * @param array<int,string>       $hooked_block_types Hooked block names.
 * @param string                  $relative_position  before|after|first_child|last_child.
 * @param string|null             $anchor_block_type  Anchor block name, or null for template-level passes.
 * @param WP_Block_Template|mixed $context            Template, part, pattern, or navigation context.
 * @return array<int,string>
 */
function axismundi_theme_switcher_hook_after_header_navigation( array $hooked_block_types, string $relative_position, ?string $anchor_block_type, $context ) : array {
	if ( 'after' !== $relative_position || 'core/navigation' !== $anchor_block_type ) {
		return $hooked_block_types;
	}

	$is_header_context = false;
	if ( $context instanceof WP_Block_Template ) {
		$is_header_context = ( property_exists( $context, 'area' ) && 'header' === $context->area )
			|| ( property_exists( $context, 'slug' ) && 'header' === $context->slug );
	} elseif ( is_array( $context ) && isset( $context['blockTypes'] ) && is_array( $context['blockTypes'] ) ) {
		$is_header_context = in_array( 'core/template-part/header', $context['blockTypes'], true );
	}

	if ( $is_header_context ) {
		$hooked_block_types[] = 'axismundi/theme-switcher';
	}

	return $hooked_block_types;
}
add_filter( 'hooked_block_types', 'axismundi_theme_switcher_hook_after_header_navigation', 10, 4 );

/**
 * Make automatically hooked switchers use the cycle style variation.
 *
 * Manually inserted switcher blocks keep their own attributes; only the hooked
 * header instance receives the compact cycle variation.
 *
 * @param array<string,mixed>|null $parsed_hooked_block Hooked block array.
 * @param string                   $hooked_block_type   Hooked block name.
 * @param string                   $relative_position   Relative hook position.
 * @param array<string,mixed>      $parsed_anchor_block Anchor block array.
 * @return array<string,mixed>|null
 */
function axismundi_theme_switcher_cycle_hooked_block( ?array $parsed_hooked_block, string $hooked_block_type, string $relative_position, array $parsed_anchor_block ) : ?array {
	if (
		null === $parsed_hooked_block
		|| 'axismundi/theme-switcher' !== $hooked_block_type
		|| 'after' !== $relative_position
		|| 'core/navigation' !== ( $parsed_anchor_block['blockName'] ?? '' )
	) {
		return $parsed_hooked_block;
	}

	$parsed_hooked_block['attrs']['className'] = trim( ( $parsed_hooked_block['attrs']['className'] ?? '' ) . ' is-style-theme-cycle' );

	return $parsed_hooked_block;
}
add_filter( 'hooked_block_axismundi/theme-switcher', 'axismundi_theme_switcher_cycle_hooked_block', 10, 4 );

/**
 * Print the blocking inline script that applies the persisted scheme early.
 *
 * The script is intentionally client-side instead of PHP-rendering data-theme:
 * full-page caches must not bake one visitor's color mode into the cached HTML.
 *
 * @return void
 */
function axismundi_theme_switcher_head_script() : void {
	?>
<script id="axismundi-theme-scheme">
(function(){try{var m=document.cookie.match(/(?:^|;\s*)axismundi_theme=(auto|light|dark)/);document.documentElement.dataset.theme=m?m[1]:"auto";}catch(e){document.documentElement.dataset.theme="auto";}})();
</script>
	<?php
}
add_action( 'wp_head', 'axismundi_theme_switcher_head_script', 0 );

/**
 * Enqueue the editor color-scheme bridge.
 *
 * Block-editor and Style Book preview documents are separate iframes. The bridge
 * mirrors the axismundi_theme cookie onto the editor document and same-origin preview
 * documents, so token selectors respond to the switcher while editing. The editor
 * document is included because Global Styles draws the colour-palette swatches there
 * and the theme's palette is var()-based; without it the swatches follow the operating
 * system while the canvas beside them follows the switcher.
 *
 * @return void
 */
function axismundi_theme_switcher_enqueue_editor_bridge() : void {
	/*
	 * The tooltip runtime, which the block registers as a viewScript for the
	 * front end. The editor needs it too, and needs it here rather than in the
	 * canvas: the bridge below runs in this document and reaches into each
	 * preview iframe to attach it. Loading it first is what lets the bridge
	 * find it.
	 */
	$axismundi_theme_switcher_tooltip = __DIR__ . '/blocks/theme-switcher/tooltip.js';
	$axismundi_theme_switcher_deps    = array();

	if ( file_exists( $axismundi_theme_switcher_tooltip ) ) {
		wp_enqueue_script(
			'axismundi-theme-switcher-tooltip',
			plugins_url( 'blocks/theme-switcher/tooltip.js', __FILE__ ),
			array(),
			(string) filemtime( $axismundi_theme_switcher_tooltip ),
			true
		);
		$axismundi_theme_switcher_deps[] = 'axismundi-theme-switcher-tooltip';
	}

	$path = __DIR__ . '/assets/editor-theme-scheme.js';
	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'axismundi-theme-switcher-editor-scheme',
		plugins_url( 'assets/editor-theme-scheme.js', __FILE__ ),
		$axismundi_theme_switcher_deps,
		(string) filemtime( $path ),
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'axismundi_theme_switcher_enqueue_editor_bridge' );
