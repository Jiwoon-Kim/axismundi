<?php
/**
 * Omphalos — theme switcher infrastructure.
 *
 * Phase 1: the authoritative, cache-safe application of the persisted colour
 * scheme. The omphalos/theme-switcher block (Phase 2+) is the control that writes
 * the cookie; this file makes the choice take effect on every page before paint.
 *
 * @package Omphalos
 */

defined( 'ABSPATH' ) || exit;

/**
 * Print the blocking inline <head> script that applies the persisted theme.
 *
 * Why inline + early instead of SSR via language_attributes: a distributable
 * theme must assume full-page caching, under which a PHP-rendered data-theme is
 * baked into the cached HTML (the first visitor's mode for everyone). This script
 * runs per-visitor in the browser, reading the cookie and setting
 * <html data-theme="auto|light|dark"> before first paint — cache-safe and
 * FOUC-free. tokens.sys.dark.css + foundation.css already key off data-theme, so
 * setting it is all that's required. No-JS / no-cookie falls back to "auto"
 * (follows the OS via prefers-color-scheme), matching the default behaviour.
 *
 * Hooked at priority 0 so it prints ahead of the enqueued token CSS.
 *
 * @return void
 */
function omphalos_theme_scheme_head_script() : void {
	// Whitelisted modes only; anything else (or missing) resolves to auto.
	?>
<script id="omphalos-theme-scheme">
(function(){try{var m=document.cookie.match(/(?:^|;\s*)omphalos_theme=(auto|light|dark)/);document.documentElement.dataset.theme=m?m[1]:"auto";}catch(e){document.documentElement.dataset.theme="auto";}})();
</script>
	<?php
}
add_action( 'wp_head', 'omphalos_theme_scheme_head_script', 0 );

/**
 * Register the omphalos/theme-switcher block.
 *
 * Phase 2: shell only — server render (render.php) + static editor preview
 * (edit.js) + block-scoped style. No view module yet, so the front-end buttons
 * have no behaviour; the Interactivity toggle lands in Phase 3.
 *
 * @return void
 */
function omphalos_register_theme_switcher_block() : void {
	$dir = get_stylesheet_directory() . '/blocks/theme-switcher';
	if ( file_exists( $dir . '/block.json' ) ) {
		register_block_type( $dir );
	}
}
add_action( 'init', 'omphalos_register_theme_switcher_block' );
