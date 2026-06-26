<?php
/**
 * Plugin Name:       Axismundi Map
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-map
 * Description:       Front-end map block that draws Axismundi Geo Data (geotags, GPS tracks) over a self-hosted basemap, reusing the Geo Data plugin's map assets.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-map
 *
 * @package AxismundiMap
 */

defined( 'ABSPATH' ) || exit;

define( 'AXISMUNDI_MAP_VERSION', '0.1.0' );
define( 'AXISMUNDI_MAP_FILE', __FILE__ );

/**
 * Whether the Geo Data plugin (the data + map-asset provider) is available.
 *
 * @return bool
 */
function axismundi_map_geodata_active() : bool {
	return function_exists( 'axismundi_geodata_resolve_tiles' );
}

/**
 * Register the editor script and the axismundi/map block.
 *
 * @return void
 */
function axismundi_map_register_block() : void {
	wp_register_script(
		'axismundi-map-edit',
		plugins_url( 'blocks/map/edit.js', __FILE__ ),
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
		AXISMUNDI_MAP_VERSION,
		true
	);

	register_block_type( __DIR__ . '/blocks/map' );
}
add_action( 'init', 'axismundi_map_register_block' );

/**
 * Render the axismundi/map block: resolve the front-end basemap from Geo Data,
 * enqueue the matching renderer (Leaflet raster / MapLibre PMTiles) using Geo
 * Data's shared handles, and emit a canvas carrying a JSON config for view.js.
 *
 * @param array $attributes Block attributes.
 * @return void
 */
function axismundi_map_render_block( array $attributes ) : void {
	$height      = isset( $attributes['height'] ) ? max( 120, (int) $attributes['height'] ) : 360;
	$source      = isset( $attributes['source'] ) ? (string) $attributes['source'] : 'none';
	$zoom        = isset( $attributes['zoom'] ) ? (int) $attributes['zoom'] : 0;
	$show_popups = ! empty( $attributes['showPopups'] );

	if ( ! axismundi_map_geodata_active() ) {
		printf( '<div %s><p>%s</p></div>', wp_kses_post( get_block_wrapper_attributes() ), esc_html__( 'Axismundi Geo Data plugin is required for this map.', 'axismundi-map' ) );
		return;
	}

	$tiles = axismundi_geodata_resolve_tiles( 'front' );
	if ( empty( $tiles['enabled'] ) ) {
		printf( '<div %s><p>%s</p></div>', wp_kses_post( get_block_wrapper_attributes() ), esc_html__( 'Configure a front-end map provider (custom raster tiles or a PMTiles map pack) under Settings → Geodata.', 'axismundi-map' ) );
		return;
	}

	$geojson = '';
	if ( 'geotags' === $source ) {
		$args = array();
		if ( ! empty( $attributes['areaId'] ) ) {
			$args['area'] = (int) $attributes['areaId'];
		}
		if ( ! empty( $attributes['bbox'] ) ) {
			$args['bbox'] = sanitize_text_field( (string) $attributes['bbox'] );
		}
		$geojson = add_query_arg( $args, rest_url( 'axismundi-geodata/v1/geotags' ) );
	} elseif ( 'track' === $source && ! empty( $attributes['trackId'] ) ) {
		$geojson = rest_url( 'axismundi-geodata/v1/track/' . (int) $attributes['trackId'] );
	}

	if ( 'pmtiles' === $tiles['kind'] ) {
		wp_enqueue_style( 'axismundi-maplibre' );
		$deps = array( 'axismundi-maplibre', 'axismundi-pmtiles', 'axismundi-protomaps-basemaps' );
	} else {
		wp_enqueue_style( 'axismundi-leaflet' );
		$deps = array( 'axismundi-leaflet' );
	}
	wp_enqueue_script( 'axismundi-map-view', plugins_url( 'blocks/map/view.js', AXISMUNDI_MAP_FILE ), $deps, AXISMUNDI_MAP_VERSION, true );

	$lang = strtolower( substr( determine_locale(), 0, 2 ) );
	if ( ! preg_match( '/^[a-z]{2}$/', $lang ) ) {
		$lang = 'en';
	}

	$config = array(
		'kind'        => $tiles['kind'],
		'tileUrl'     => $tiles['tile_url'],
		'packUrl'     => $tiles['pack_url'] ?? '',
		'attribution' => $tiles['attribution'],
		'minZoom'     => (int) $tiles['min_zoom'],
		'maxZoom'     => (int) $tiles['max_zoom'],
		'center'      => $tiles['center'] ?? array(),
		'bounds'      => $tiles['bounds'] ?? array(),
		'glyphs'      => 'https://protomaps.github.io/basemaps-assets/fonts/{fontstack}/{range}.pbf',
		'sprite'      => 'https://protomaps.github.io/basemaps-assets/sprites/v4/light',
		'lang'        => $lang,
		'geojson'     => $geojson,
		'zoom'        => $zoom,
		'showPopups'  => $show_popups,
	);

	printf(
		'<div %1$s><div class="axismundi-map__canvas" style="height:%2$dpx" data-config="%3$s"></div></div>',
		wp_kses_post( get_block_wrapper_attributes( array( 'class' => 'axismundi-map' ) ) ),
		(int) $height,
		esc_attr( wp_json_encode( $config ) )
	);
}
