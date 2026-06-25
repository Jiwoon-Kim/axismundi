<?php
/**
 * Place identity model — binding a term to an external place.
 *
 * A geo_area / geotag term stores its provider binding in two term meta keys:
 *   ax_geo_source   — which provider the identity comes from (manual / google /
 *                     osm / wikidata / geonames)
 *   ax_geo_place_id — that provider's RAW id (ChIJ… / node/123456 / Q12345 /
 *                     1838524 / a manual slug)
 *
 * They are stored separately so the source can be queried / filtered, and the
 * canonical "source:id" form (google:ChIJ…) is composed on demand by
 * axismundi_geodata_canonical_place_id(). A future Google / OSM lookup fills both;
 * for now the term editor shows them read-only.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Allowed place-identity providers, source slug => label.
 *
 * @return array<string,string>
 */
function axismundi_geodata_place_sources() : array {
	return array(
		'manual'   => __( 'Manual', 'axismundi-geodata' ),
		'google'   => __( 'Google', 'axismundi-geodata' ),
		'osm'      => __( 'OpenStreetMap', 'axismundi-geodata' ),
		'wikidata' => __( 'Wikidata', 'axismundi-geodata' ),
		'geonames' => __( 'GeoNames', 'axismundi-geodata' ),
	);
}

/**
 * Display label for a source slug, falling back to the slug.
 *
 * @param string $source Source slug.
 * @return string
 */
function axismundi_geodata_place_source_label( string $source ) : string {
	$sources = axismundi_geodata_place_sources();

	return $sources[ $source ] ?? $source;
}

/**
 * The canonical "source:id" place identifier for a term, or '' when either part
 * is missing. Raw provider ids never contain a leading "provider:" prefix, so the
 * first colon always separates source from id.
 *
 * @param int $term_id Term id.
 * @return string e.g. "google:ChIJ…", or '' if unbound.
 */
function axismundi_geodata_canonical_place_id( int $term_id ) : string {
	$source = (string) get_term_meta( $term_id, 'ax_geo_source', true );
	$id     = (string) get_term_meta( $term_id, 'ax_geo_place_id', true );

	if ( '' === $source || '' === $id ) {
		return '';
	}

	return $source . ':' . $id;
}

/**
 * Split a canonical "source:id" string back into its parts. A string with no
 * colon is treated as a bare id with an unknown source.
 *
 * @param string $canonical Canonical id.
 * @return array{source:string,id:string}
 */
function axismundi_geodata_parse_place_id( string $canonical ) : array {
	$pos = strpos( $canonical, ':' );
	if ( false === $pos ) {
		return array(
			'source' => '',
			'id'     => $canonical,
		);
	}

	return array(
		'source' => substr( $canonical, 0, $pos ),
		'id'     => substr( $canonical, $pos + 1 ),
	);
}

/**
 * Whether a source slug is an allowed place-identity provider.
 *
 * @param string $source Source slug.
 * @return bool
 */
function axismundi_geodata_is_place_source( string $source ) : bool {
	return array_key_exists( $source, axismundi_geodata_place_sources() );
}

/**
 * Bind a term to an external place — the single write path every lookup / import
 * (Google, OSM, CSV) goes through. Validates the source, stores the identity, and
 * stores any facts the lookup returned (which pass through their registered
 * sanitizers via update_term_meta).
 *
 * @param int    $term_id  Term id.
 * @param string $source   Provider slug (must be an allowed source).
 * @param string $place_id Provider raw id.
 * @param array  $facts    Optional facts: geo_latitude, geo_longitude, geo_address,
 *                         ax_geo_place_type, ax_geo_radius.
 * @return string Canonical "source:id", or '' when the source is invalid or the id empty.
 */
function axismundi_geodata_bind_place_identity( int $term_id, string $source, string $place_id, array $facts = array() ) : string {
	if ( ! axismundi_geodata_is_place_source( $source ) || '' === $place_id ) {
		return '';
	}

	update_term_meta( $term_id, 'ax_geo_source', $source );
	update_term_meta( $term_id, 'ax_geo_place_id', $place_id );

	foreach ( array( 'geo_latitude', 'geo_longitude', 'geo_address', 'ax_geo_place_type', 'ax_geo_radius' ) as $key ) {
		if ( array_key_exists( $key, $facts ) && '' !== $facts[ $key ] && null !== $facts[ $key ] ) {
			update_term_meta( $term_id, $key, $facts[ $key ] );
		}
	}

	return axismundi_geodata_canonical_place_id( $term_id );
}
