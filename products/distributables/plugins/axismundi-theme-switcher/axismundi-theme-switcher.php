<?php
/**
 * Plugin Name:       Axismundi Theme Switcher
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-theme-switcher
 * Description:       Light / dark / auto theme switcher block and color-scheme bridge for Axismundi.
 * Version:           0.1.0
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
