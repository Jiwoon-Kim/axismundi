<?php
/**
 * The subdivision codes that countries assign.
 *
 * ISO 3166-2 says which subdivision code belongs under which ISO 3166-1 country; it does not give
 * this plugin a global administrative hierarchy or a translated label to show a person. GeoData
 * terms retain both jobs. The registry is for validation, imports, and relating a term's optional
 * `ax_geo_iso_3166_2` value to the country it must live under.
 *
 * The source snapshot is Unicode CLDR 48.2's public subdivision containment data, under
 * Unicode-3.0. See `data/iso-3166-2.tsv` and its licence beside it. Keeping this as a versioned
 * input makes updates deliberate, without pretending a WordPress request can keep an ISO register
 * current on its own.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/** The CLDR snapshot from which this ISO 3166-2 registry was generated. */
const AXISMUNDI_GEODATA_ISO_3166_2_EDITION = 'CLDR-48.2';

/**
 * Every known subdivision code, mapped to its ISO 3166-1 alpha-2 parent.
 *
 * @return array<string,string>
 */
function axismundi_geodata_subdivisions() : array {
	static $subdivisions = null;
	if ( null === $subdivisions ) {
		$subdivisions = (array) require __DIR__ . '/iso-3166-2.generated.php';
	}
	return $subdivisions;
}

/**
 * Whether the registry knows this fully qualified ISO 3166-2 code.
 *
 * Unknown imported codes are not rejected or rewritten merely because this release is older than
 * the import. This is a question the GeoData editor can use, not a limit on what a Card may say.
 *
 * @param string $code ISO 3166-2 code.
 * @return bool
 */
function axismundi_geodata_is_subdivision_code( string $code ) : bool {
	return isset( axismundi_geodata_subdivisions()[ strtoupper( trim( $code ) ) ] );
}

/**
 * The country that assigns a subdivision code.
 *
 * @param string $code ISO 3166-2 code.
 * @return string ISO 3166-1 alpha-2 code, or empty when unknown.
 */
function axismundi_geodata_subdivision_country_code( string $code ) : string {
	return (string) ( axismundi_geodata_subdivisions()[ strtoupper( trim( $code ) ) ] ?? '' );
}

/**
 * Whether this known subdivision is assigned by this country.
 *
 * @param string $code    ISO 3166-2 code.
 * @param string $country ISO 3166-1 alpha-2 code.
 * @return bool
 */
function axismundi_geodata_subdivision_belongs_to_country( string $code, string $country ) : bool {
	return strtoupper( trim( $country ) ) === axismundi_geodata_subdivision_country_code( $code );
}

/**
 * The codes a country assigns, keyed by their full ISO 3166-2 spelling.
 *
 * @param string $country ISO 3166-1 alpha-2 code.
 * @return array<string,string>
 */
function axismundi_geodata_subdivisions_for_country( string $country ) : array {
	$country = strtoupper( trim( $country ) );
	return array_filter(
		axismundi_geodata_subdivisions(),
		static function ( string $parent ) use ( $country ) : bool {
			return $parent === $country;
		}
	);
}
