<?php
/**
 * Block-editor Location panel enqueue.
 *
 * Hand-written script (no build step, like the other Axismundi companion
 * plugins). The panel reads/writes the post's geo_* / ax_geo_public_precision
 * meta, which is already registered with show_in_rest.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue the Location document-setting panel in the block editor.
 *
 * @return void
 */
function axismundi_geodata_enqueue_editor() : void {
	$relative = 'assets/editor.js';
	$path     = plugin_dir_path( AXISMUNDI_GEODATA_FILE ) . $relative;

	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'axismundi-geodata-editor',
		plugins_url( $relative, AXISMUNDI_GEODATA_FILE ),
		array( 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
		(string) filemtime( $path ),
		true
	);

	wp_set_script_translations( 'axismundi-geodata-editor', 'axismundi-geodata' );
}
add_action( 'enqueue_block_editor_assets', 'axismundi_geodata_enqueue_editor' );
