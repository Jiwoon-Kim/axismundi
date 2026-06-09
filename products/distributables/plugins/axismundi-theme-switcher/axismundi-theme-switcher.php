<?php
/**
 * Plugin Name:       Axismundi Theme Switcher
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-theme-switcher
 * Description:       Light / dark / auto theme switcher block and color-scheme bridge for Axismundi.
 * Version:           0.1.1
 * Requires at least: 6.7
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
 * Load plugin translations when present.
 *
 * @return void
 */
function axismundi_theme_switcher_load_textdomain() : void {
	load_plugin_textdomain( 'axismundi-theme-switcher', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'axismundi_theme_switcher_load_textdomain' );

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
add_action( 'admin_head', 'axismundi_theme_switcher_head_script', 0 );

/**
 * Enqueue the editor color-scheme bridge.
 *
 * Block-editor and Style Book preview documents are separate iframes. The bridge
 * mirrors the axismundi_theme cookie into same-origin preview documents so token
 * selectors respond to the switcher while editing.
 *
 * @return void
 */
function axismundi_theme_switcher_enqueue_editor_bridge() : void {
	$path = __DIR__ . '/assets/editor-theme-scheme.js';
	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'axismundi-theme-switcher-editor-scheme',
		plugins_url( 'assets/editor-theme-scheme.js', __FILE__ ),
		array(),
		(string) filemtime( $path ),
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'axismundi_theme_switcher_enqueue_editor_bridge' );
