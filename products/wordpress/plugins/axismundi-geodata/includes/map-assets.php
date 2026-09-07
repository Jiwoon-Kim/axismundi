<?php
/**
 * Map asset provider — register the bundled map libraries as stable, shared script
 * handles on the front end and admin alike.
 *
 * Axismundi Geo Data owns the vendored map libraries; a dependent plugin
 * (axismundi-map) reuses them by handle instead of re-bundling. The admin preview
 * (enqueue_map_field) enqueues these same handles. Registering on `init` makes
 * them available wherever a consumer enqueues, front or back.
 *
 * Stable handles (the map asset provider contract):
 *   axismundi-leaflet, axismundi-maplibre, axismundi-pmtiles,
 *   axismundi-protomaps-basemaps
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the shared map vendor scripts / styles.
 *
 * @return void
 */
function axismundi_geodata_register_map_assets() : void {
	$vendor = plugins_url( 'assets/vendor/', AXISMUNDI_GEODATA_FILE );

	wp_register_style( 'axismundi-leaflet', $vendor . 'leaflet/leaflet.css', array(), '1.9.4' );
	wp_register_script( 'axismundi-leaflet', $vendor . 'leaflet/leaflet.js', array(), '1.9.4', true );

	wp_register_style( 'axismundi-maplibre', $vendor . 'maplibre/maplibre-gl.css', array(), '5.24.0' );
	wp_register_script( 'axismundi-maplibre', $vendor . 'maplibre/maplibre-gl.js', array(), '5.24.0', true );

	wp_register_script( 'axismundi-pmtiles', $vendor . 'pmtiles/pmtiles.js', array(), '4.4.1', true );
	wp_register_script( 'axismundi-protomaps-basemaps', $vendor . 'protomaps/basemaps.js', array(), '5.7.2', true );
}
add_action( 'init', 'axismundi_geodata_register_map_assets' );
