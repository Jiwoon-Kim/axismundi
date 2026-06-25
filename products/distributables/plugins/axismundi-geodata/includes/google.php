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
 * ax_geo_place_type => Google Places type, only where they differ from a
 * 1:1-aligned identity. geo_area administrative types map to Google address
 * component types; the ambiguous geotag types pick a Google primary type. Every
 * other geotag slug was already aligned to its Google token, so it passes through.
 *
 * @return array<string,string>
 */
function axismundi_geodata_google_type_map() : array {
	return array(
		// geo_area administrative types -> Google address component types.
		'country'             => 'country',
		'province'            => 'administrative_area_level_1',
		'state'               => 'administrative_area_level_1',
		'region'              => 'administrative_area_level_1',
		'metropolitan_city'   => 'administrative_area_level_1',
		'city'                => 'locality',
		'county'              => 'administrative_area_level_2',
		'district'            => 'administrative_area_level_2',
		'municipality'        => 'locality',
		'town'                => 'locality',
		'township'            => 'administrative_area_level_3',
		'village'             => 'locality',
		'borough'             => 'sublocality',
		'ward'                => 'sublocality',
		'locality'            => 'locality',
		'sublocality'         => 'sublocality',
		'neighborhood'        => 'neighborhood',
		'administrative_area' => 'administrative_area_level_2',
		// geotag types we kept local because Google splits them differently.
		'station'             => 'transit_station',
		'temple'              => 'place_of_worship',
		'performance_venue'   => 'performing_arts_theater',
		'local_pub'           => 'pub',
		'pension'             => 'bed_and_breakfast',
	);
}

/**
 * Map an Axismundi place type to a Google type (e.g. for a search includedType).
 * Aligned slugs pass through unchanged; Korea-specific types with no Google
 * equivalent also pass through and should be treated as a best-effort hint.
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
 * (several admin types share one Google type), so it is not auto-reversed; only
 * the unambiguous geotag splits are reversed, and any other token (mostly aligned
 * identities) passes through.
 *
 * @param string $google_type Google Places type.
 * @return string Axismundi place-type slug, or '' for an empty input.
 */
function axismundi_geodata_from_google_type( string $google_type ) : string {
	if ( '' === $google_type ) {
		return '';
	}
	$reverse = array(
		'transit_station'         => 'station',
		'train_station'           => 'station',
		'place_of_worship'        => 'temple',
		'performing_arts_theater' => 'performance_venue',
		'pub'                     => 'local_pub',
		'bed_and_breakfast'       => 'pension',
	);

	return $reverse[ $google_type ] ?? $google_type;
}

/**
 * A compact Text Search field mask for candidate selection.
 *
 * Google Places API (New) requires an explicit field mask. Keep this narrow so a
 * lookup returns only the facts we can bind to a term.
 */
const AXISMUNDI_GEODATA_GOOGLE_TEXT_SEARCH_FIELDS = 'places.id,places.displayName,places.formattedAddress,places.location,places.primaryType,places.types';

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

	return array(
		'place_id'      => $id,
		'name'          => $name,
		'address'       => $address,
		'latitude'      => $latitude,
		'longitude'     => $longitude,
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
function axismundi_geodata_google_lookup_term( int $term_id ) {
	$key = axismundi_geodata_google_api_key();
	if ( '' === $key ) {
		return new WP_Error( 'axismundi_google_key_missing', __( 'Google API key is not configured.', 'axismundi-geodata' ) );
	}

	$term = axismundi_geodata_lookup_get_term( $term_id );
	if ( is_wp_error( $term ) ) {
		return $term;
	}

	$body = array(
		'textQuery'    => axismundi_geodata_lookup_term_query( $term ),
		'languageCode' => axismundi_geodata_lookup_language_code(),
	);

	$region = axismundi_geodata_lookup_country_code( $term );
	if ( '' !== $region ) {
		$body['regionCode'] = $region;
	}

	$lat = get_term_meta( $term_id, 'geo_latitude', true );
	$lng = get_term_meta( $term_id, 'geo_longitude', true );
	if ( '' !== $lat && '' !== $lng ) {
		$radius = (float) get_term_meta( $term_id, 'ax_geo_radius', true );
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
		'label'   => __( 'Google', 'axismundi-geodata' ),
		'enabled' => 'axismundi_geodata_google_enabled',
		'search'  => 'axismundi_geodata_google_lookup_term',
	);

	return $providers;
}
add_filter( 'axismundi_geodata_lookup_providers', 'axismundi_geodata_register_google_provider' );
