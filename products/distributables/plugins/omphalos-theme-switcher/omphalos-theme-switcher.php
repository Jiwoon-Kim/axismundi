<?php
/**
 * Plugin Name:       Omphalos Theme Switcher
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi
 * Description:       Companion block for the Omphalos light / dark / auto theme switcher.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       omphalos-theme-switcher
 *
 * @package OmphalosThemeSwitcher
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load plugin translations when present.
 *
 * @return void
 */
function omphalos_theme_switcher_load_textdomain() : void {
	load_plugin_textdomain( 'omphalos-theme-switcher', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'omphalos_theme_switcher_load_textdomain' );

/**
 * Register the omphalos/theme-switcher block.
 *
 * The Omphalos theme owns the early head script that applies the persisted
 * cookie before paint. This companion plugin only owns the inserter/editor
 * block UI and its front-end button behaviour.
 *
 * @return void
 */
function omphalos_theme_switcher_register_block() : void {
	$dir = __DIR__ . '/blocks/theme-switcher';
	if ( file_exists( $dir . '/block.json' ) ) {
		register_block_type( $dir );
	}
}
add_action( 'init', 'omphalos_theme_switcher_register_block' );
