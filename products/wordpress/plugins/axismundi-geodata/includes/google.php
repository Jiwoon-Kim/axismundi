<?php
/**
 * Google provider boundary.
 *
 * The thin layer that maps between Axismundi place types and Google Places types,
 * and calls the Places API from admin-only server-side actions. Kept separate so
 * OSM / other providers can sit alongside it without leaking Google specifics
 * into the core model.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * ax_geo_place_type => Google Places type where the tokens differ. geo_area
 * administrative types map to address-component types; generated custom geotag
 * extensions map to their reviewed Google fallback.
 *
 * @return array<string,string>
 */
function axismundi_geodata_google_type_map() : array {
	$map = array(
		// geo_area administrative types -> Google address component types.
		'country'             => 'country',
		'province'            => 'administrative_area_level_1',
		'state'               => 'administrative_area_level_1',
		'city'                => 'locality',
		'county'              => 'administrative_area_level_2',
		'district'            => 'administrative_area_level_2',
		'town'                => 'locality',
		'township'            => 'administrative_area_level_3',
		'village'             => 'locality',
		'sublocality'         => 'sublocality',
		'neighborhood'        => 'neighborhood',
	);

	foreach ( axismundi_geodata_geotag_place_type_records() as $record ) {
		if ( 'custom' === $record['source'] ) {
			$map[ $record['slug'] ] = $record['google_fallback'];
		}
	}

	return $map;
}

/**
 * Map an Axismundi place type to a Google type (e.g. for a search includedType).
 * Google-aligned slugs pass through; local extensions use their reviewed
 * best-effort fallback.
 *
 * @param string $ax_type Axismundi place-type slug.
 * @return string Google type, or '' for an empty input.
 */
function axismundi_geodata_to_google_type( string $ax_type ) : string {
	if ( '' === $ax_type ) {
		return '';
	}
	$map = axismundi_geodata_google_type_map();

	return $map[ $ax_type ] ?? $ax_type;
}

/**
 * Map a Google Places type back to an Axismundi place type, for suggesting the
 * place type from a lookup result. The geo_area forward map is many-to-one
 * (several admin types share one Google type), so it is not auto-reversed. Google
 * geotag tokens already match the controlled vocabulary and pass through.
 *
 * @param string $google_type Google Places type.
 * @return string Axismundi place-type slug, or '' for an empty input.
 */
function axismundi_geodata_from_google_type( string $google_type ) : string {
	if ( '' === $google_type ) {
		return '';
	}
	$reverse = array(
		'country'                 => 'country',
		'administrative_area_level_1' => 'province',
		'administrative_area_level_2' => 'district',
		'administrative_area_level_3' => 'district',
		'sublocality'             => 'sublocality',
		'sublocality_level_1'     => 'sublocality',
		'sublocality_level_2'     => 'sublocality',
		'sublocality_level_3'     => 'sublocality',
		'sublocality_level_4'     => 'sublocality',
		'sublocality_level_5'     => 'sublocality',
		'neighborhood'            => 'neighborhood',
	);

	return $reverse[ $google_type ] ?? $google_type;
}

/**
 * Reverse geocode a map point through Google Geocoding API v4.
 *
 * The clicked coordinate remains the term centroid. Returned locations may be
 * address or political-entity centroids and must not silently move the marker.
 *
 * @param float  $lat        Clicked latitude.
 * @param float  $lng        Clicked longitude.
 * @param string $taxonomy   geo_area or geotag.
 * @param string $place_type Selected Axismundi place type.
 * @return array<int,array<string,mixed>>|WP_Error
 */
function axismundi_geodata_google_reverse( float $lat, float $lng, string $taxonomy = '', string $place_type = '' ) {
	$key = axismundi_geodata_google_api_key();
	if ( '' === $key ) {
		return new WP_Error( 'axismundi_google_key_missing', __( 'Google API key is not configured.', 'axismundi-geodata' ) );
	}

	$args = array(
		'location.latitude'  => $lat,
		'location.longitude' => $lng,
		'languageCode'       => axismundi_geodata_lookup_language_code(),
	);
	$response = wp_remote_get(
		'https://geocode.googleapis.com/v4/geocode/location?' . http_build_query( $args ),
		array(
			'timeout' => 15,
			'headers' => array(
				'X-Goog-Api-Key'   => $key,
				'X-Goog-FieldMask' => 'results.placeId,results.formattedAddress,results.types,results.location',
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code < 200 || $code >= 300 ) {
		$message = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : __( 'Google reverse geocoding failed.', 'axismundi-geodata' );
		return new WP_Error( 'axismundi_google_reverse_failed', $message, array( 'status' => $code ) );
	}

	$candidates = array();
	foreach ( (array) ( $data['results'] ?? array() ) as $result ) {
		$id = isset( $result['placeId'] ) ? sanitize_text_field( (string) $result['placeId'] ) : '';
		if ( '' === $id ) {
			continue;
		}
		$types = array_map( 'sanitize_key', (array) ( $result['types'] ?? array() ) );
		$type  = '';
		foreach ( $types as $candidate_type ) {
			if ( ! in_array( $candidate_type, array( 'political', 'geocode' ), true ) ) {
				$type = $candidate_type;
				break;
			}
		}
		if ( 'axismundi_geo_area' === $taxonomy ) {
			$is_area = 'country' === $type
				|| str_starts_with( $type, 'administrative_area_level_' )
				|| 'locality' === $type
				|| 'sublocality' === $type
				|| str_starts_with( $type, 'sublocality_level_' )
				|| 'neighborhood' === $type;
			if ( ! $is_area ) {
				continue;
			}
		}
		$candidates[] = array(
			'place_id'      => $id,
			'name'          => '',
			'address'       => isset( $result['formattedAddress'] ) ? sanitize_text_field( (string) $result['formattedAddress'] ) : '',
			// The resolved place location (so picking a candidate corrects the point),
			// falling back to the clicked point when the API omits it.
			'latitude'      => isset( $result['location']['latitude'] ) ? axismundi_geodata_sanitize_latitude( $result['location']['latitude'] ) : $lat,
			'longitude'     => isset( $result['location']['longitude'] ) ? axismundi_geodata_sanitize_longitude( $result['location']['longitude'] ) : $lng,
			'place_type'    => axismundi_geodata_from_google_type( $type ),
			'provider_type' => $type,
		);
	}

	return array_slice( $candidates, 0, 8 );
}

/**
 * A compact Text Search field mask for candidate selection.
 *
 * Google Places API (New) requires an explicit field mask. Keep this narrow so a
 * lookup returns only the facts we can bind to a term.
 */
const AXISMUNDI_GEODATA_GOOGLE_TEXT_SEARCH_FIELDS = 'places.id,places.displayName,places.formattedAddress,places.location,places.viewport,places.primaryType,places.types';

/**
 * Normalise a Google Places result into a term-bindable candidate.
 *
 * @param array $place Raw place object.
 * @return array<string,mixed>|null
 */
function axismundi_geodata_google_normalize_place( array $place ) : ?array {
	$id = isset( $place['id'] ) ? sanitize_text_field( (string) $place['id'] ) : '';
	if ( '' === $id ) {
		return null;
	}

	$name = '';
	if ( isset( $place['displayName']['text'] ) ) {
		$name = sanitize_text_field( (string) $place['displayName']['text'] );
	}

	$address     = isset( $place['formattedAddress'] ) ? sanitize_text_field( (string) $place['formattedAddress'] ) : '';
	$google_type = isset( $place['primaryType'] ) ? sanitize_key( (string) $place['primaryType'] ) : '';
	$latitude    = isset( $place['location']['latitude'] ) ? axismundi_geodata_sanitize_latitude( $place['location']['latitude'] ) : null;
	$longitude   = isset( $place['location']['longitude'] ) ? axismundi_geodata_sanitize_longitude( $place['location']['longitude'] ) : null;
	$bounds      = '';
	if ( isset( $place['viewport']['low'], $place['viewport']['high'] ) ) {
		$low    = (array) $place['viewport']['low'];
		$high   = (array) $place['viewport']['high'];
		$bounds = axismundi_geodata_sanitize_bounds(
			implode(
				',',
				array(
					$low['longitude'] ?? '',
					$low['latitude'] ?? '',
					$high['longitude'] ?? '',
					$high['latitude'] ?? '',
				)
			)
		);
	}

	return array(
		'place_id'      => $id,
		'name'          => $name,
		'address'       => $address,
		'latitude'      => $latitude,
		'longitude'     => $longitude,
		'bounds'        => $bounds,
		'place_type'    => axismundi_geodata_from_google_type( $google_type ),
		'provider_type' => $google_type,
	);
}

/**
 * Search Google Places Text Search (New) for term candidates.
 *
 * @param int $term_id Term id.
 * @return array<int,array<string,mixed>>|WP_Error
 */
function axismundi_geodata_google_search_query( string $query, string $region = '', $lat = null, $lng = null, float $radius = 0 ) {
	$key = axismundi_geodata_google_api_key();
	if ( '' === $key ) {
		return new WP_Error( 'axismundi_google_key_missing', __( 'Google API key is not configured.', 'axismundi-geodata' ) );
	}

	$body = array(
		'textQuery'    => $query,
		'languageCode' => axismundi_geodata_lookup_language_code(),
	);

	if ( '' !== $region ) {
		$body['regionCode'] = $region;
	}

	if ( null !== $lat && null !== $lng ) {
		$radius = $radius > 0 ? $radius : 5000;
		$body['locationBias'] = array(
			'circle' => array(
				'center' => array(
					'latitude'  => axismundi_geodata_sanitize_latitude( $lat ),
					'longitude' => axismundi_geodata_sanitize_longitude( $lng ),
				),
				'radius' => max( 100, min( 50000, $radius ) ),
			),
		);
	}

	$response = wp_remote_post(
		'https://places.googleapis.com/v1/places:searchText',
		array(
			'timeout' => 15,
			'headers' => array(
				'Content-Type'      => 'application/json',
				'X-Goog-Api-Key'    => $key,
				'X-Goog-FieldMask'  => AXISMUNDI_GEODATA_GOOGLE_TEXT_SEARCH_FIELDS,
			),
			'body'    => wp_json_encode( $body ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( $code < 200 || $code >= 300 ) {
		$message = isset( $data['error']['message'] ) ? (string) $data['error']['message'] : __( 'Google Places lookup failed.', 'axismundi-geodata' );
		return new WP_Error( 'axismundi_google_lookup_failed', $message, array( 'status' => $code ) );
	}

	$candidates = array();
	foreach ( (array) ( $data['places'] ?? array() ) as $place ) {
		if ( is_array( $place ) ) {
			$candidate = axismundi_geodata_google_normalize_place( $place );
			if ( null !== $candidate ) {
				$candidates[] = $candidate;
			}
		}
	}

	return array_slice( $candidates, 0, 8 );
}

/**
 * Search Google for an existing term, including its stored geographic context.
 *
 * @param int $term_id Term id.
 * @return array<int,array<string,mixed>>|WP_Error
 */
function axismundi_geodata_google_lookup_term( int $term_id ) {
	$term = axismundi_geodata_lookup_get_term( $term_id );
	if ( is_wp_error( $term ) ) {
		return $term;
	}

	$lat = get_term_meta( $term_id, 'geo_latitude', true );
	$lng = get_term_meta( $term_id, 'geo_longitude', true );

	return axismundi_geodata_google_search_query(
		axismundi_geodata_lookup_term_query( $term ),
		axismundi_geodata_lookup_country_code( $term ),
		'' !== $lat ? $lat : null,
		'' !== $lng ? $lng : null
	);
}

/**
 * Whether Google lookup is configured (a server API key is set).
 *
 * @return bool
 */
function axismundi_geodata_google_enabled() : bool {
	return '' !== axismundi_geodata_google_api_key();
}

/**
 * Register Google as a lookup provider.
 *
 * @param array $providers Provider registry.
 * @return array
 */
function axismundi_geodata_register_google_provider( array $providers ) : array {
	$providers['google'] = array(
		'label'        => __( 'Google', 'axismundi-geodata' ),
		'enabled'      => 'axismundi_geodata_google_enabled',
		'search'       => 'axismundi_geodata_google_lookup_term',
		'search_query' => 'axismundi_geodata_google_search_query',
		'reverse'      => 'axismundi_geodata_google_reverse',
	);

	return $providers;
}
add_filter( 'axismundi_geodata_lookup_providers', 'axismundi_geodata_register_google_provider' );
