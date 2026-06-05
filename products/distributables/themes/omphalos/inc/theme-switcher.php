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
 * Enqueue the editor canvas colour-scheme bridge.
 *
 * The editor iframe does not execute wp_head, so the front-end head script never
 * runs inside the canvas. The bridge copies the same omphalos_theme cookie onto
 * the iframe document's <html data-theme>, keeping front/editor previews aligned
 * without introducing a second persistence mechanism.
 *
 * @return void
 */
function omphalos_theme_scheme_editor_assets() : void {
	$uri = omphalos_asset_uri( 'assets/scripts/editor-theme-scheme.js' );
	if ( null === $uri ) {
		return;
	}

	$path    = get_stylesheet_directory() . '/assets/scripts/editor-theme-scheme.js';
	$version = file_exists( $path ) ? (string) filemtime( $path ) : OMPHALOS_VERSION;

	wp_enqueue_script(
		'omphalos-editor-theme-scheme',
		$uri,
		array(),
		$version,
		true
	);
}
add_action( 'enqueue_block_editor_assets', 'omphalos_theme_scheme_editor_assets' );

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
