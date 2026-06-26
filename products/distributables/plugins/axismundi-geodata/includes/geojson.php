<?php
/**
 * GeoJSON export — the read contract a map renderer (the future Map plugin,
 * Leaflet, MapLibre, Google Data layer) consumes.
 *
 * GeoJSON is not a storage format here: data stays WordPress-native (terms, meta,
 * attachments) and is projected to GeoJSON FeatureCollections on request, with
 * coordinates in [longitude, latitude] order. geotag / geo_area centroids are
 * public named-place facts; post exact coordinates (privacy-rounded) and a bbox
 * query for archive/search map views come later.
 *
 *   GET /wp-json/axismundi-geodata/v1/geotags?area={id}&geotag={id}&bbox=w,s,e,n
 *   GET /wp-json/axismundi-geodata/v1/media?ids=1,2,3
 *   GET /wp-json/axismundi-geodata/v1/track/{attachment_id}
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the GeoJSON REST routes.
 *
 * @return void
 */
function axismundi_geodata_register_geojson_routes() : void {
	register_rest_route(
		'axismundi-geodata/v1',
		'/geotags',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_geodata_rest_geotags_geojson',
			'permission_callback' => '__return_true',
			'args'                => array(
				'area' => array( 'type' => 'integer', 'required' => false ),
				'geotag' => array( 'type' => 'integer', 'required' => false ),
				'bbox' => array( 'type' => 'string', 'required' => false ),
			),
		)
	);

	register_rest_route(
		'axismundi-geodata/v1',
		'/track/(?P<id>\d+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_geodata_rest_track_geojson',
			'permission_callback' => '__return_true',
			'args'                => array( 'id' => array( 'type' => 'integer' ) ),
		)
	);

	register_rest_route(
		'axismundi-geodata/v1',
		'/media',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_geodata_rest_media_geojson',
			'permission_callback' => '__return_true',
			'args'                => array(
				'ids' => array( 'type' => 'string', 'required' => true ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_geodata_register_geojson_routes' );

/**
 * Parse a "W,S,E,N" bbox string, or null.
 *
 * @param string $bbox Bbox param.
 * @return array{0:float,1:float,2:float,3:float}|null
 */
function axismundi_geodata_parse_bbox( string $bbox ) : ?array {
	if ( '' === $bbox ) {
		return null;
	}
	$parts = array_map( 'floatval', explode( ',', $bbox ) );

	return 4 === count( $parts ) ? $parts : null;
}

/**
 * Whether a [lon, lat] coordinate is inside a [W, S, E, N] bbox.
 *
 * @param array $coords [lon, lat].
 * @param array $bbox   [W, S, E, N].
 * @return bool
 */
function axismundi_geodata_coord_in_bbox( array $coords, array $bbox ) : bool {
	return $coords[0] >= $bbox[0] && $coords[0] <= $bbox[2]
		&& $coords[1] >= $bbox[1] && $coords[1] <= $bbox[3];
}

/**
 * A geotag term as a GeoJSON Point Feature, or null when it has no coordinate.
 *
 * @param WP_Term $term Geotag term.
 * @return array<string,mixed>|null
 */
function axismundi_geodata_geotag_feature( WP_Term $term ) : ?array {
	$lat = get_term_meta( $term->term_id, 'geo_latitude', true );
	$lon = get_term_meta( $term->term_id, 'geo_longitude', true );
	if ( '' === (string) $lat || '' === (string) $lon ) {
		return null;
	}

	$link = get_term_link( $term );

	return array(
		'type'       => 'Feature',
		'geometry'   => array(
			'type'        => 'Point',
			'coordinates' => array( (float) $lon, (float) $lat ),
		),
		'properties' => array(
			'id'         => $term->term_id,
			'type'       => 'geotag',
			'title'      => $term->name,
			'place_type' => (string) get_term_meta( $term->term_id, 'ax_geo_place_type', true ),
			'place_id'   => axismundi_geodata_canonical_place_id( $term->term_id ),
			'url'        => is_wp_error( $link ) ? '' : $link,
		),
	);
}

/**
 * GET geotags as a GeoJSON FeatureCollection (optionally within an area / bbox).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function axismundi_geodata_rest_geotags_geojson( WP_REST_Request $request ) : WP_REST_Response {
	$single = (int) $request->get_param( 'geotag' );
	$area   = (int) $request->get_param( 'area' );
	if ( $single > 0 ) {
		$term  = get_term( $single, 'geotag' );
		$terms = $term instanceof WP_Term ? array( $term ) : array();
	} elseif ( $area > 0 ) {
		$terms = axismundi_geodata_get_geotags_in_area( $area );
	} else {
		$terms = get_terms( array( 'taxonomy' => 'geotag', 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}
	}

	$bbox     = axismundi_geodata_parse_bbox( (string) $request->get_param( 'bbox' ) );
	$features = array();
	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		$feature = axismundi_geodata_geotag_feature( $term );
		if ( null === $feature ) {
			continue;
		}
		if ( $bbox && ! axismundi_geodata_coord_in_bbox( $feature['geometry']['coordinates'], $bbox ) ) {
			continue;
		}
		$features[] = $feature;
	}

	return rest_ensure_response(
		array(
			'type'     => 'FeatureCollection',
			'features' => $features,
		)
	);
}

/**
 * GET a track attachment as a GeoJSON FeatureCollection (Line/MultiLineString).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_geodata_rest_track_geojson( WP_REST_Request $request ) {
	$id = (int) $request['id'];
	if ( ! axismundi_geodata_is_track_attachment( $id ) || ! in_array( get_post_status( $id ), array( 'inherit', 'publish' ), true ) ) {
		return new WP_Error( 'axismundi_not_track', __( 'Not a track attachment.', 'axismundi-geodata' ), array( 'status' => 404 ) );
	}

	$segments = axismundi_geodata_track_segments( $id );
	if ( empty( $segments ) ) {
		return new WP_Error( 'axismundi_empty_track', __( 'Track has no coordinates.', 'axismundi-geodata' ), array( 'status' => 404 ) );
	}

	$geometry = 1 === count( $segments )
		? array( 'type' => 'LineString', 'coordinates' => $segments[0] )
		: array( 'type' => 'MultiLineString', 'coordinates' => $segments );

	$feature = array(
		'type'       => 'Feature',
		'geometry'   => $geometry,
		'properties' => array(
			'id'         => $id,
			'type'       => 'track',
			'name'       => (string) get_post_meta( $id, 'ax_track_name', true ),
			'format'     => (string) get_post_meta( $id, 'ax_track_format', true ),
			'distance_m' => (int) get_post_meta( $id, 'ax_track_distance_m', true ),
		),
	);

	return rest_ensure_response(
		array(
			'type'     => 'FeatureCollection',
			'features' => array( $feature ),
		)
	);
}

/**
 * A public GPS attachment as a GeoJSON Point Feature, or null when private/unset.
 *
 * @param int $attachment_id Attachment ID.
 * @return array<string,mixed>|null
 */
function axismundi_geodata_attachment_point_feature( int $attachment_id ) : ?array {
	if ( 'attachment' !== get_post_type( $attachment_id ) || ! axismundi_geodata_is_public( $attachment_id ) ) {
		return null;
	}

	$lat = get_post_meta( $attachment_id, 'geo_latitude', true );
	$lon = get_post_meta( $attachment_id, 'geo_longitude', true );
	if ( '' === (string) $lat || '' === (string) $lon ) {
		return null;
	}

	$thumb = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
	$full  = wp_get_attachment_url( $attachment_id );

	return array(
		'type'       => 'Feature',
		'geometry'   => array(
			'type'        => 'Point',
			'coordinates' => array( (float) $lon, (float) $lat ),
		),
		'properties' => array(
			'id'        => $attachment_id,
			'type'      => 'attachment',
			'title'     => get_the_title( $attachment_id ),
			'url'       => get_attachment_link( $attachment_id ),
			'media_url' => $full ? $full : '',
			'thumbnail' => is_array( $thumb ) ? (string) $thumb[0] : '',
		),
	);
}

/**
 * A track attachment as a GeoJSON Feature, or null when unavailable.
 *
 * @param int $attachment_id Attachment ID.
 * @return array<string,mixed>|null
 */
function axismundi_geodata_track_feature( int $attachment_id ) : ?array {
	if ( ! axismundi_geodata_is_track_attachment( $attachment_id ) || ! in_array( get_post_status( $attachment_id ), array( 'inherit', 'publish' ), true ) ) {
		return null;
	}

	$segments = axismundi_geodata_track_segments( $attachment_id );
	if ( empty( $segments ) ) {
		return null;
	}

	$geometry = 1 === count( $segments )
		? array( 'type' => 'LineString', 'coordinates' => $segments[0] )
		: array( 'type' => 'MultiLineString', 'coordinates' => $segments );

	return array(
		'type'       => 'Feature',
		'geometry'   => $geometry,
		'properties' => array(
			'id'         => $attachment_id,
			'type'       => 'track',
			'name'       => (string) get_post_meta( $attachment_id, 'ax_track_name', true ),
			'title'      => get_the_title( $attachment_id ),
			'format'     => (string) get_post_meta( $attachment_id, 'ax_track_format', true ),
			'distance_m' => (int) get_post_meta( $attachment_id, 'ax_track_distance_m', true ),
			'url'        => get_attachment_link( $attachment_id ),
		),
	);
}

/**
 * GET selected media attachments (public GPS points + GPX/KML tracks) as GeoJSON.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function axismundi_geodata_rest_media_geojson( WP_REST_Request $request ) : WP_REST_Response {
	$ids = array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'ids' ) ) ) );
	$ids = array_slice( array_values( array_unique( $ids ) ), 0, 100 );

	$features = array();
	foreach ( $ids as $id ) {
		if ( 'attachment' !== get_post_type( $id ) ) {
			continue;
		}

		$feature = axismundi_geodata_track_feature( $id );
		if ( null === $feature ) {
			$feature = axismundi_geodata_attachment_point_feature( $id );
		}
		if ( null !== $feature ) {
			$features[] = $feature;
		}
	}

	return rest_ensure_response(
		array(
			'type'     => 'FeatureCollection',
			'features' => $features,
		)
	);
}
