<?php
/**
 * Plugin Name:       Axismundi Map
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-map
 * Description:       Front-end map block that draws Axismundi Geo Data (geotags, GPS tracks) over a self-hosted basemap, reusing the Geo Data plugin's map assets.
 * Version:           0.2.1
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Requires Plugins:  axismundi-geodata
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-map
 *
 * @package AxismundiMap
 */

defined( 'ABSPATH' ) || exit;

define( 'AXISMUNDI_MAP_VERSION', '0.2.1' );
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
 * Build geotag features from the posts on the current main-query page.
 *
 * Terms are deduplicated because several posts can reference the same place.
 * This deliberately follows the native post taxonomy query instead of the
 * geotag-to-area metadata relation.
 *
 * @return array{type:string,features:array<int,array<string,mixed>>}
 */
function axismundi_map_current_query_geotags() : array {
	global $wp_query;

	$features = array();
	$seen     = array();
	$posts    = $wp_query instanceof WP_Query ? $wp_query->posts : array();

	foreach ( $posts as $post ) {
		$post_id = $post instanceof WP_Post ? $post->ID : (int) $post;
		$terms   = get_the_terms( $post_id, 'axismundi_geotag' );
		if ( ! is_array( $terms ) ) {
			continue;
		}

		foreach ( $terms as $term ) {
			if ( isset( $seen[ $term->term_id ] ) || ! function_exists( 'axismundi_geodata_geotag_feature' ) ) {
				continue;
			}
			$seen[ $term->term_id ] = true;
			$feature                = axismundi_geodata_geotag_feature( $term );
			if ( null !== $feature ) {
				$features[] = $feature;
			}
		}
	}

	return array(
		'type'     => 'FeatureCollection',
		'features' => $features,
	);
}

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
	$show_visitor_location = ! empty( $attributes['showVisitorLocation'] );

	if ( ! axismundi_map_geodata_active() ) {
		printf( '<div %s><p>%s</p></div>', wp_kses_post( get_block_wrapper_attributes() ), esc_html__( 'Axismundi Geo Data plugin is required for this map.', 'axismundi-map' ) );
		return;
	}

	$tiles = axismundi_geodata_resolve_tiles( 'front' );
	if ( empty( $tiles['enabled'] ) ) {
		printf( '<div %s><p>%s</p></div>', wp_kses_post( get_block_wrapper_attributes() ), esc_html__( 'Configure a front-end map provider (custom raster tiles or a PMTiles map pack) under Settings → Geodata.', 'axismundi-map' ) );
		return;
	}

	$geojson      = '';
	$geojson_data = null;
	$sync_key     = '';
	$fit_inline   = false;
	if ( 'geotags' === $source ) {
		$args = array();
		if ( ! empty( $attributes['bbox'] ) ) {
			$args['bbox'] = sanitize_text_field( (string) $attributes['bbox'] );
		}
		$geojson = add_query_arg( $args, rest_url( 'axismundi-geodata/v1/geotags' ) );
	} elseif ( 'track' === $source && ! empty( $attributes['trackId'] ) ) {
		$geojson = rest_url( 'axismundi-geodata/v1/track/' . (int) $attributes['trackId'] );
	} elseif ( 'media' === $source && ! empty( $attributes['mediaIds'] ) && is_array( $attributes['mediaIds'] ) ) {
		$ids = array_filter( array_map( 'absint', $attributes['mediaIds'] ) );
		if ( ! empty( $ids ) ) {
			$geojson = add_query_arg( 'ids', implode( ',', $ids ), rest_url( 'axismundi-geodata/v1/media' ) );
		}
	} elseif ( 'current' === $source ) {
		// Query Map View: a geo-area archive follows the posts on the current query
		// page; a geotag archive remains focused on the single queried place.
		$queried = get_queried_object();
		if ( $queried instanceof WP_Term ) {
			if ( 'axismundi_geo_area' === $queried->taxonomy ) {
				$geojson_data = axismundi_map_current_query_geotags();
				$sync_key     = 'geo-area-' . (int) $queried->term_id;
			} elseif ( 'axismundi_geotag' === $queried->taxonomy ) {
				$geojson = add_query_arg( 'geotag', (int) $queried->term_id, rest_url( 'axismundi-geodata/v1/geotags' ) );
			}
		}
	} elseif ( 'georss' === $source && ! empty( $attributes['feedUrl'] ) && function_exists( 'axismundi_geodata_georss_geojson' ) ) {
		// External GeoRSS feed: Geodata fetches/caches and converts server-side,
		// inlined here so there is no public URL-proxy endpoint to abuse.
		$geojson_data = axismundi_geodata_georss_geojson( esc_url_raw( (string) $attributes['feedUrl'] ) );

		$items_to_show = isset( $attributes['itemsToShow'] ) ? (int) $attributes['itemsToShow'] : 0;
		if ( $items_to_show > 0 && ! empty( $geojson_data['features'] ) ) {
			$geojson_data['features'] = array_slice( $geojson_data['features'], 0, $items_to_show );
		}
		$fit_inline = ! empty( $geojson_data['features'] );
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
		'kind'                => $tiles['kind'],
		'tileUrl'             => $tiles['tile_url'],
		'packUrl'             => $tiles['pack_url'] ?? '',
		'attribution'         => $tiles['attribution'],
		'rasterTileUrl'       => $tiles['raster_tile_url'] ?? '',
		'rasterAttribution'   => $tiles['raster_attribution'] ?? '',
		'minZoom'             => (int) $tiles['min_zoom'],
		'maxZoom'             => (int) $tiles['max_zoom'],
		'center'              => $tiles['center'] ?? array(),
		'bounds'              => $tiles['bounds'] ?? array(),
		'glyphs'              => 'https://protomaps.github.io/basemaps-assets/fonts/{fontstack}/{range}.pbf',
		'sprite'              => 'https://protomaps.github.io/basemaps-assets/sprites/v4/light',
		'lang'                => $lang,
		'geojson'             => $geojson,
		'geojsonData'         => $geojson_data,
		'fitGeojson'          => '' !== $sync_key || $fit_inline,
		'fallbackBounds'      => array(),
		'singlePointZoom'     => 0,
		'zoom'                => $zoom,
		'showPopups'          => $show_popups,
		'showVisitorLocation' => $show_visitor_location,
		'displayAuthor'       => ! isset( $attributes['displayAuthor'] ) || ! empty( $attributes['displayAuthor'] ),
		'displayDate'         => ! empty( $attributes['displayDate'] ),
		'displayExcerpt'      => ! isset( $attributes['displayExcerpt'] ) || ! empty( $attributes['displayExcerpt'] ),
		'excerptLength'       => isset( $attributes['excerptLength'] ) ? max( 1, (int) $attributes['excerptLength'] ) : 55,
		'openInNewTab'        => ! empty( $attributes['openInNewTab'] ),
	);

	if ( '' !== $sync_key && isset( $queried ) && $queried instanceof WP_Term ) {
		$lat = get_term_meta( $queried->term_id, 'geo_latitude', true );
		$lon = get_term_meta( $queried->term_id, 'geo_longitude', true );
		if ( '' !== (string) $lat && '' !== (string) $lon ) {
			$config['center'] = array( (float) $lon, (float) $lat );
		}

		$term_zoom = (float) get_term_meta( $queried->term_id, 'ax_geo_zoom', true );
		if ( $zoom <= 0 ) {
			$config['zoom'] = $term_zoom > 0 ? $term_zoom : 10;
		}
		$config['singlePointZoom'] = $zoom > 0 ? $zoom : ( $term_zoom > 0 ? $term_zoom : 14 );

		// A manually selected zoom wins. Otherwise a provider viewport is the empty-
		// archive fallback; marker-bearing pages always fit their marker coordinates.
		if ( $zoom <= 0 && $term_zoom <= 0 ) {
			$bounds = axismundi_geodata_parse_bounds( (string) get_term_meta( $queried->term_id, 'ax_geo_bounds', true ) );
			if ( null !== $bounds ) {
				$config['fallbackBounds'] = array( $bounds['west'], $bounds['south'], $bounds['east'], $bounds['north'] );
			}
		}

		wp_interactivity_state(
			'axismundi/map',
			array(
				'datasets' => array( $sync_key => $geojson_data ),
			)
		);
	}

	$wrapper_attributes = array( 'class' => 'axismundi-map' );
	$context_attribute  = '';
	if ( '' !== $sync_key ) {
		$wrapper_attributes['data-wp-interactive'] = 'axismundi/map';
		$wrapper_attributes['data-wp-watch']       = 'callbacks.syncDataset';
		$context_attribute = wp_interactivity_data_wp_context( array( 'mapKey' => $sync_key ) );
	}

	printf(
		'<div %1$s %4$s><div class="axismundi-map__canvas" style="height:%2$dpx" data-config="%3$s"></div></div>',
		wp_kses_post( get_block_wrapper_attributes( $wrapper_attributes ) ),
		(int) $height,
		esc_attr( wp_json_encode( $config ) ),
		$context_attribute // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by the Interactivity API.
	);
}
