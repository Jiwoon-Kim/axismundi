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

	$settings = axismundi_geodata_get_settings();
	$deps     = array( 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' );

	// Bundle Leaflet only when a raster tile provider is configured — "none"
	// makes no external map requests and needs no map library.
	$map_enabled = 'custom_raster' === $settings['provider'] && '' !== $settings['tile_url'];
	if ( $map_enabled ) {
		wp_enqueue_style(
			'axismundi-leaflet',
			plugins_url( 'assets/vendor/leaflet/leaflet.css', AXISMUNDI_GEODATA_FILE ),
			array(),
			'1.9.4'
		);
		wp_enqueue_script(
			'axismundi-leaflet',
			plugins_url( 'assets/vendor/leaflet/leaflet.js', AXISMUNDI_GEODATA_FILE ),
			array(),
			'1.9.4',
			true
		);
		$deps[] = 'axismundi-leaflet';
	}

	wp_enqueue_script(
		'axismundi-geodata-editor',
		plugins_url( $relative, AXISMUNDI_GEODATA_FILE ),
		$deps,
		(string) filemtime( $path ),
		true
	);

	wp_add_inline_script(
		'axismundi-geodata-editor',
		'window.axismundiGeodataMap = ' . wp_json_encode(
			array(
				'mapEnabled'  => $map_enabled,
				'tileUrl'     => $settings['tile_url'],
				'attribution' => $settings['attribution'],
				'minZoom'     => (int) $settings['min_zoom'],
				'maxZoom'     => (int) $settings['max_zoom'],
				'imagePath'   => plugins_url( 'assets/vendor/leaflet/images/', AXISMUNDI_GEODATA_FILE ),
			)
		) . ';',
		'before'
	);

	wp_set_script_translations( 'axismundi-geodata-editor', 'axismundi-geodata' );
}
add_action( 'enqueue_block_editor_assets', 'axismundi_geodata_enqueue_editor' );
