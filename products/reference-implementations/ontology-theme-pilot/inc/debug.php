<?php
/**
 * Axismundi Pilot — Debug helper
 *
 * Exposes theme.json processing diagnostics. Reachable at:
 *   /wp-admin/?axismundi_debug=1   (must be admin)
 *
 * Shows:
 *   - WP's resolved theme.json data (post-merge of theme.json + style variation)
 *   - Generated --wp--preset--color--* CSS custom properties
 *   - Block style registry contents
 */

defined( 'ABSPATH' ) || exit;

function axismundi_pilot_debug_page() : void {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( empty( $_GET['axismundi_debug'] ) ) {
		return;
	}

	header( 'Content-Type: text/plain; charset=utf-8' );

	echo "=== Axismundi Pilot — theme.json Diagnostic ===\n\n";

	// 1. Resolved theme.json data
	$theme_data = WP_Theme_JSON_Resolver::get_merged_data();
	$raw = $theme_data->get_raw_data();
	echo "1) Active palette (post-merge of theme.json + variation):\n";
	$palette = $raw['settings']['color']['palette']['theme'] ?? array();
	foreach ( $palette as $entry ) {
		echo sprintf( "   %-30s %s\n", $entry['slug'], $entry['color'] );
	}
	echo "\n";

	// 2. Available style variations
	echo "2) Style variations available:\n";
	$variations = WP_Theme_JSON_Resolver::get_style_variations();
	foreach ( $variations as $v ) {
		echo "   - " . ( $v['title'] ?? '(untitled)' ) . "\n";
	}
	echo "\n";

	// 3. Block styles registered
	echo "3) Block styles registered for M3 binding targets:\n";
	$registry = WP_Block_Styles_Registry::get_instance();
	$all      = $registry->get_all_registered();
	foreach ( array( 'core/button', 'core/group', 'core/list', 'core/separator', 'core/search' ) as $block ) {
		$styles = $all[ $block ] ?? array();
		echo "   $block:\n";
		foreach ( $styles as $name => $def ) {
			echo "     - $name (" . ( $def['label'] ?? '?' ) . ")\n";
		}
	}
	echo "\n";

	// 4. Generated CSS custom properties from theme.json
	echo "4) Generated CSS preset variables (from theme.json):\n";
	$css = $theme_data->get_stylesheet( array( 'variables' ) );
	$lines = preg_split( '/\R/', $css );
	$shown = 0;
	foreach ( $lines as $line ) {
		if ( preg_match( '/--wp--preset--color--/', $line ) && $shown < 20 ) {
			echo "   " . trim( $line ) . "\n";
			$shown++;
		}
	}
	if ( $shown === 0 ) {
		echo "   (no --wp--preset--color--* found — theme.json palette not ingested)\n";
	}
	echo "\n";

	// 5. file presence
	echo "5) Pilot theme files:\n";
	$theme_dir = get_template_directory();
	foreach ( array( 'theme.json', 'styles/m3-dark.json', 'assets/css/tokens.css', 'assets/css/base.css', 'assets/css/block-styles.css' ) as $f ) {
		$path = $theme_dir . '/' . $f;
		echo "   " . ( file_exists( $path ) ? '✓' : '✗' ) . " $f" . ( file_exists( $path ) ? ' (' . filesize( $path ) . ' bytes)' : '' ) . "\n";
	}

	exit;
}
add_action( 'admin_init', 'axismundi_pilot_debug_page' );
