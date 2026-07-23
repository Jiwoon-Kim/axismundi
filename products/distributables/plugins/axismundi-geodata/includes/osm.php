<?php
/**
 * OpenStreetMap / Nominatim provider boundary.
 *
 * A second place-lookup provider behind the same registry as Google. It is
 * disabled by default: the operator must choose the public Nominatim service (an
 * explicit low-volume admin opt-in) or a custom endpoint in Settings > Geodata.
 * Nominatim requires an identifying User-Agent, which we build from the site URL
 * and an optional contact. Source ids are osm:node/…, osm:way/…, osm:relation/….
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * The resolved Nominatim base endpoint, or '' when disabled.
 *
 * @return string
 */
function axismundi_geodata_osm_endpoint() : string {
	$cfg  = axismundi_geodata_get_settings();
	$mode = $cfg['nominatim_mode'] ?? 'none';

	if ( 'public' === $mode ) {
		return 'https://nominatim.openstreetmap.org';
	}
	if ( 'custom' === $mode ) {
		$url = (string) ( $cfg['nominatim_endpoint'] ?? '' );
		return preg_match( '#^https?://#i', $url ) ? rtrim( $url, '/' ) : '';
	}

	return '';
}

/**
 * Whether OSM lookup is configured.
 *
 * @return bool
 */
function axismundi_geodata_osm_enabled() : bool {
	return '' !== axismundi_geodata_osm_endpoint();
}

/**
 * Identifying User-Agent for Nominatim (required by its usage policy).
 *
 * @return string
 */
function axismundi_geodata_osm_user_agent() : string {
	$contact = trim( (string) ( axismundi_geodata_get_settings()['nominatim_contact'] ?? '' ) );
	$suffix  = '' !== $contact ? '; ' . $contact : '';

	return 'AxismundiGeodata/' . AXISMUNDI_GEODATA_VERSION . ' (' . home_url( '/' ) . $suffix . ')';
}

/**
 * Map an OSM class/type tag to an Axismundi place type, or '' when unknown.
 *
 * @param string $class OSM class (amenity / tourism / leisure / place …).
 * @param string $type  OSM type tag.
 * @return string
 */
function axismundi_geodata_osm_type_to_place_type( string $class, string $type ) : string {
	$map = array(
		// Tourism / culture.
		'museum'           => 'museum',
		'gallery'          => 'art_gallery',
		'artwork'          => 'art_gallery',
		'hotel'            => 'hotel',
		'hostel'           => 'hostel',
		'guest_house'      => 'guest_house',
		'motel'            => 'motel',
		'attraction'       => '',
		'theme_park'       => 'amusement_park',
		'zoo'              => 'zoo',
		'aquarium'         => 'aquarium',
		// Food & drink.
		'restaurant'       => 'restaurant',
		'fast_food'        => 'restaurant',
		'cafe'             => 'cafe',
		'bakery'           => 'bakery',
		'pub'              => 'local_pub',
		'bar'              => 'local_pub',
		// Shopping.
		'department_store' => 'department_store',
		'supermarket'      => 'supermarket',
		'convenience'      => 'convenience_store',
		'marketplace'      => 'traditional_market',
		// Services & public.
		'bank'             => 'bank',
		'atm'              => 'atm',
		'pharmacy'         => 'pharmacy',
		'hospital'         => 'hospital',
		'clinic'           => 'clinic',
		'library'          => 'library',
		'university'       => 'university',
		'college'          => 'university',
		'school'           => 'school',
		'parking'          => 'parking',
		'cinema'           => 'movie_theater',
		'theatre'          => 'performance_venue',
		'place_of_worship' => 'temple',
		// Parks & nature.
		'park'             => 'city_park',
		'garden'           => 'garden',
		'nature_reserve'   => 'natural_park',
		'marina'           => 'marina',
		'beach'            => 'beach',
		'beach_resort'     => 'beach',
		// Transport.
		'station'          => 'station',
		'aerodrome'        => 'airport',
		'ferry_terminal'   => 'ferry_terminal',
		// Historic.
		'monument'         => 'historical_landmark',
		'memorial'         => 'historical_landmark',
		'castle'           => 'historical_landmark',
		'archaeological_site' => 'historical_landmark',
		// Administrative (place class) for geo_area.
		'country'          => 'country',
		'state'            => 'province',
		'province'         => 'province',
		'region'           => 'province',
		'county'           => 'county',
		'city'             => 'city',
		'town'             => 'town',
		'village'          => 'village',
		'suburb'           => 'sublocality',
		'neighbourhood'    => 'neighborhood',
		'administrative'   => '',
	);

	return $map[ $type ] ?? '';
}

/**
 * Normalise a Nominatim place into a term-bindable candidate.
 *
 * @param array $place Raw Nominatim result.
 * @return array<string,mixed>|null
 */
function axismundi_geodata_osm_normalize_place( array $place ) : ?array {
	$osm_type = isset( $place['osm_type'] ) ? sanitize_key( (string) $place['osm_type'] ) : '';
	$osm_id   = isset( $place['osm_id'] ) ? (string) (int) $place['osm_id'] : '';
	if ( '' === $osm_type || '' === $osm_id ) {
		return null;
	}

	$class   = isset( $place['class'] ) ? sanitize_key( (string) $place['class'] ) : '';
	$type    = isset( $place['type'] ) ? sanitize_key( (string) $place['type'] ) : '';
	$address = isset( $place['display_name'] ) ? sanitize_text_field( (string) $place['display_name'] ) : '';

	$name = isset( $place['name'] ) && '' !== $place['name'] ? sanitize_text_field( (string) $place['name'] ) : '';
	if ( '' === $name && '' !== $address ) {
		$parts = explode( ',', $address );
		$name  = sanitize_text_field( trim( $parts[0] ) );
	}

	$lat = isset( $place['lat'] ) ? axismundi_geodata_sanitize_latitude( $place['lat'] ) : null;
	$lon = isset( $place['lon'] ) ? axismundi_geodata_sanitize_longitude( $place['lon'] ) : null;
	$bounds = '';
	if ( isset( $place['boundingbox'] ) && is_array( $place['boundingbox'] ) && 4 === count( $place['boundingbox'] ) ) {
		// Nominatim returns south,north,west,east; the durable term value is W,S,E,N.
		$bounds = axismundi_geodata_sanitize_bounds(
			implode(
				',',
				array(
					$place['boundingbox'][2],
					$place['boundingbox'][0],
					$place['boundingbox'][3],
					$place['boundingbox'][1],
				)
			)
		);
	}

	return array(
		'place_id'      => $osm_type . '/' . $osm_id,
		'name'          => $name,
		'address'       => $address,
		'latitude'      => $lat,
		'longitude'     => $lon,
		'bounds'        => $bounds,
		'place_type'    => axismundi_geodata_osm_type_to_place_type( $class, $type ),
		'provider_type' => trim( $class . ':' . $type, ':' ),
	);
}

/**
 * Search Nominatim for term candidates.
 *
 * @param int $term_id Term id.
 * @return array<int,array<string,mixed>>|WP_Error
 */
function axismundi_geodata_osm_search_query( string $query, string $country_code = '' ) {
	$endpoint = axismundi_geodata_osm_endpoint();
	if ( '' === $endpoint ) {
		return new WP_Error( 'axismundi_osm_disabled', __( 'OpenStreetMap lookup is not configured.', 'axismundi-geodata' ) );
	}

	$args = array(
		'q'               => $query,
		'format'          => 'jsonv2',
		'addressdetails'  => 1,
		'limit'           => 8,
		'accept-language' => axismundi_geodata_lookup_language_code(),
	);
	if ( '' !== $country_code ) {
		$args['countrycodes'] = strtolower( $country_code );
	}

	$response = wp_remote_get(
		$endpoint . '/search?' . http_build_query( $args ),
		array(
			'timeout' => 15,
			'headers' => array(
				'User-Agent' => axismundi_geodata_osm_user_agent(),
				'Accept'     => 'application/json',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'axismundi_osm_lookup_failed', __( 'OpenStreetMap lookup failed.', 'axismundi-geodata' ), array( 'status' => (int) $code ) );
	}

	$candidates = array();
	foreach ( (array) $data as $place ) {
		if ( is_array( $place ) ) {
			$candidate = axismundi_geodata_osm_normalize_place( $place );
			if ( null !== $candidate ) {
				$candidates[] = $candidate;
			}
		}
	}

	return array_slice( $candidates, 0, 8 );
}

/**
 * Search Nominatim for an existing term and its area context.
 *
 * @param int $term_id Term id.
 * @return array<int,array<string,mixed>>|WP_Error
 */
function axismundi_geodata_osm_lookup_term( int $term_id ) {
	$term = axismundi_geodata_lookup_get_term( $term_id );
	if ( is_wp_error( $term ) ) {
		return $term;
	}

	return axismundi_geodata_osm_search_query(
		axismundi_geodata_lookup_term_query( $term ),
		axismundi_geodata_lookup_country_code( $term )
	);
}

/**
 * Nominatim detail zoom for an Axismundi administrative type.
 *
 * @param string $place_type Selected place type.
 * @return int
 */
function axismundi_geodata_osm_reverse_zoom( string $place_type ) : int {
	$zoom = array(
		'country'      => 3,
		'province'     => 5,
		'state'        => 5,
		'county'       => 8,
		'district'     => 8,
		'city'         => 10,
		'town'         => 12,
		'township'     => 12,
		'village'      => 14,
		'sublocality'  => 16,
		'neighborhood' => 16,
	);

	return $zoom[ $place_type ] ?? 18;
}

/**
 * Reverse geocode a clicked map point through Nominatim.
 *
 * @param float  $lat        Clicked latitude.
 * @param float  $lng        Clicked longitude.
 * @param string $taxonomy   geo_area or geotag.
 * @param string $place_type Selected Axismundi place type.
 * @return array<int,array<string,mixed>>|WP_Error
 */
function axismundi_geodata_osm_reverse( float $lat, float $lng, string $taxonomy = '', string $place_type = '' ) {
	$endpoint = axismundi_geodata_osm_endpoint();
	if ( '' === $endpoint ) {
		return new WP_Error( 'axismundi_osm_disabled', __( 'OpenStreetMap lookup is not configured.', 'axismundi-geodata' ) );
	}

	$response = wp_remote_get(
		$endpoint . '/reverse?' . http_build_query(
			array(
				'format'          => 'jsonv2',
				'lat'             => $lat,
				'lon'             => $lng,
				'zoom'            => 'axismundi_geo_area' === $taxonomy ? axismundi_geodata_osm_reverse_zoom( $place_type ) : 18,
				'addressdetails'  => 1,
				'accept-language' => axismundi_geodata_lookup_language_code(),
			)
		),
		array(
			'timeout' => 15,
			'headers' => array(
				'User-Agent' => axismundi_geodata_osm_user_agent(),
				'Accept'     => 'application/json',
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code < 200 || $code >= 300 || ! is_array( $data ) ) {
		return new WP_Error( 'axismundi_osm_reverse_failed', __( 'OpenStreetMap reverse lookup failed.', 'axismundi-geodata' ), array( 'status' => (int) $code ) );
	}

	$candidate = axismundi_geodata_osm_normalize_place( $data );
	if ( null === $candidate ) {
		return array();
	}
	// Keep the resolved place coordinates from normalize_place so picking the
	// candidate corrects the marker, rather than pinning it to the clicked point.

	return array( $candidate );
}

/**
 * Register OSM as a lookup provider.
 *
 * @param array $providers Provider registry.
 * @return array
 */
function axismundi_geodata_register_osm_provider( array $providers ) : array {
	$providers['osm'] = array(
		'label'        => __( 'OpenStreetMap', 'axismundi-geodata' ),
		'enabled'      => 'axismundi_geodata_osm_enabled',
		'search'       => 'axismundi_geodata_osm_lookup_term',
		'search_query' => 'axismundi_geodata_osm_search_query',
		'reverse'      => 'axismundi_geodata_osm_reverse',
	);

	return $providers;
}
add_filter( 'axismundi_geodata_lookup_providers', 'axismundi_geodata_register_osm_provider' );
