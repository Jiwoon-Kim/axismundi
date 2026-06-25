<?php
/**
 * Google provider boundary.
 *
 * The thin layer that maps between Axismundi place types and Google Places types,
 * and (later) calls the Places API. Kept separate so OSM / other providers can sit
 * alongside it without leaking Google specifics into the core model. This file is
 * pure mapping — no network calls; the lookup AJAX is added on top.
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
