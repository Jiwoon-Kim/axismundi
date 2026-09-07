<?php
/**
 * Experimental Material Symbol replacements for registered Core icons.
 *
 * Navigation and Search still render their own SVGs in the current Core, so
 * this deliberately affects only consumers of the WordPress Icon API.
 *
 * @package Axismundi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Replace the small, verified Core Icon API subset with theme-owned SVGs.
 *
 * @return void
 */
function axismundi_replace_core_icons() : void {
	if (
		! function_exists( 'wp_get_icon' )
		|| ! function_exists( 'wp_unregister_icon' )
		|| ! function_exists( 'wp_register_icon' )
	) {
		return;
	}

	$directory = get_theme_file_path( 'assets/icons/material-symbols/' );
	$icons     = array(
		'core/menu'   => 'menu.svg',
		'core/search' => 'search.svg',
	);

	foreach ( $icons as $name => $file ) {
		$file_path = $directory . $file;
		if ( ! is_readable( $file_path ) || '' === wp_get_icon( $name ) ) {
			continue;
		}

		wp_unregister_icon( $name );
		wp_register_icon(
			$name,
			array(
				'label'     => $name,
				'file_path' => $file_path,
			)
		);
	}
}
add_action( 'init', 'axismundi_replace_core_icons', 20 );
