<?php
/**
 * GeoRSS Simple extensions for WordPress RSS 2.0 feeds.
 *
 * Existing WordPress feed queries remain authoritative. This file only adds
 * WGS 84 geometry: taxonomy feeds describe their channel, while each post item
 * is located from its public geotag terms. Post and attachment coordinate meta
 * are intentionally excluded from this serializer.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the GeoRSS namespace to non-comment RSS 2.0 feeds.
 *
 * @return void
 */
function axismundi_geodata_georss_namespace() : void {
	if ( is_comment_feed() ) {
		return;
	}

	echo ' xmlns:georss="http://www.georss.org/georss"';
}
add_action( 'rss2_ns', 'axismundi_geodata_georss_namespace' );

/**
 * Coordinate pairs carried by a post's geotag terms.
 *
 * @param int $post_id Post ID.
 * @return array<int,array{lat:float,lng:float}>
 */
function axismundi_geodata_post_georss_points( int $post_id ) : array {
	$terms = get_the_terms( $post_id, 'geotag' );
	if ( ! is_array( $terms ) ) {
		return array();
	}

	$points = array();
	foreach ( $terms as $term ) {
		$lat = get_term_meta( $term->term_id, 'geo_latitude', true );
		$lng = get_term_meta( $term->term_id, 'geo_longitude', true );
		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			continue;
		}
		$points[] = array( 'lat' => (float) $lat, 'lng' => (float) $lng );
	}

	return $points;
}

/**
 * Print one escaped GeoRSS Simple geometry element.
 *
 * @param string $geometry point or box.
 * @param string $value    GeoRSS coordinate text.
 * @return void
 */
function axismundi_geodata_print_georss_geometry( string $geometry, string $value ) : void {
	if ( '' === $value || ! in_array( $geometry, array( 'point', 'box' ), true ) ) {
		return;
	}

	printf( "\t<georss:%1\$s>%2\$s</georss:%1\$s>\n", esc_html( $geometry ), esc_xml( $value ) );
}

/**
 * Add a point or aggregate box to each located RSS item.
 *
 * @return void
 */
function axismundi_geodata_georss_item() : void {
	if ( is_comment_feed() ) {
		return;
	}

	$points = axismundi_geodata_post_georss_points( get_the_ID() );
	if ( empty( $points ) ) {
		return;
	}

	if ( 1 === count( $points ) ) {
		axismundi_geodata_print_georss_geometry(
			'point',
			axismundi_geodata_coords_to_georss_point( $points[0]['lat'], $points[0]['lng'] )
		);
		return;
	}

	$lats = array_column( $points, 'lat' );
	$lngs = array_column( $points, 'lng' );
	$south = min( $lats );
	$north = max( $lats );
	$west  = min( $lngs );
	$east  = max( $lngs );

	if ( $south === $north && $west === $east ) {
		axismundi_geodata_print_georss_geometry( 'point', axismundi_geodata_coords_to_georss_point( $south, $west ) );
		return;
	}

	axismundi_geodata_print_georss_geometry(
		'box',
		axismundi_geodata_bounds_to_georss_box( $west, $south, $east, $north )
	);
}
add_action( 'rss2_item', 'axismundi_geodata_georss_item' );

/**
 * Describe geo taxonomy RSS channels with their term geometry.
 *
 * A geo_area uses captured map bounds when available and falls back to its
 * centre point. A geotag always uses its place point.
 *
 * @return void
 */
function axismundi_geodata_georss_channel() : void {
	if ( is_comment_feed() ) {
		return;
	}

	$term = get_queried_object();
	if ( ! $term instanceof WP_Term || ! in_array( $term->taxonomy, array( 'geo_area', 'geotag' ), true ) ) {
		return;
	}

	if ( 'geo_area' === $term->taxonomy ) {
		$bounds = axismundi_geodata_parse_bounds( (string) get_term_meta( $term->term_id, 'ax_geo_bounds', true ) );
		if ( null !== $bounds ) {
			axismundi_geodata_print_georss_geometry(
				'box',
				axismundi_geodata_bounds_to_georss_box( $bounds['west'], $bounds['south'], $bounds['east'], $bounds['north'] )
			);
			return;
		}
	}

	$lat = get_term_meta( $term->term_id, 'geo_latitude', true );
	$lng = get_term_meta( $term->term_id, 'geo_longitude', true );
	axismundi_geodata_print_georss_geometry( 'point', axismundi_geodata_coords_to_georss_point( $lat, $lng ) );
}
add_action( 'rss2_head', 'axismundi_geodata_georss_channel' );
