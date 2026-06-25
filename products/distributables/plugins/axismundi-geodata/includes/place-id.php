<?php
/**
 * Place identity model — binding a term to an external place.
 *
 * A geo_area / geotag term stores its provider binding in one term meta key:
 *   ax_geo_place_id — a canonical namespaced id, e.g. google:ChIJ…,
 *                     osm:node/123456, wikidata:Q12345, geonames:1838524,
 *                     manual:gwangalli-beach.
 *
 * Source is parsed from that string when needed. This keeps the durable model
 * aligned with external-id practice and prevents every provider adapter from
 * having to maintain split source/id columns.
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
 * Normalise a source + raw id into a canonical namespaced place id.
 *
 * @param string $source   Provider source.
 * @param string $place_id Provider raw id, or already-canonical id.
 * @return string Canonical id, or '' when invalid.
 */
function axismundi_geodata_normalize_place_id( string $source, string $place_id ) : string {
	$source   = sanitize_key( $source );
	$place_id = trim( sanitize_text_field( $place_id ) );

	if ( '' === $source || '' === $place_id || ! axismundi_geodata_is_place_source( $source ) ) {
		return '';
	}

	$parsed = axismundi_geodata_parse_place_id( $place_id );
	if ( '' !== $parsed['source'] ) {
		return $parsed['source'] === $source && axismundi_geodata_is_place_source( $parsed['source'] ) && '' !== $parsed['id']
			? $parsed['source'] . ':' . $parsed['id']
			: '';
	}

	return $source . ':' . $parsed['id'];
}

/**
 * The canonical namespaced place identifier for a term, or '' when missing.
 *
 * Legacy split storage (`ax_geo_source` + raw `ax_geo_place_id`) is read as a
 * fallback so local/dev terms from early iterations don't break. New writes store
 * only canonical `ax_geo_place_id`.
 *
 * @param int $term_id Term id.
 * @return string e.g. "google:ChIJ…", or '' if unbound.
 */
function axismundi_geodata_canonical_place_id( int $term_id ) : string {
	$id     = trim( (string) get_term_meta( $term_id, 'ax_geo_place_id', true ) );
	$parsed = axismundi_geodata_parse_place_id( $id );

	if ( '' !== $parsed['source'] && axismundi_geodata_is_place_source( $parsed['source'] ) && '' !== $parsed['id'] ) {
		return $parsed['source'] . ':' . $parsed['id'];
	}

	$legacy_source = (string) get_term_meta( $term_id, 'ax_geo_source', true );
	return axismundi_geodata_normalize_place_id( $legacy_source, $id );
}

/**
 * Split a canonical "source:id" string back into its parts. A string with no
 * colon is treated as a bare id with an unknown source.
 *
 * @param string $canonical Canonical id.
 * @return array{source:string,id:string}
 */
function axismundi_geodata_parse_place_id( string $canonical ) : array {
	$canonical = trim( sanitize_text_field( $canonical ) );
	$pos = strpos( $canonical, ':' );
	if ( false === $pos ) {
		return array(
			'source' => '',
			'id'     => $canonical,
		);
	}

	return array(
		'source' => sanitize_key( substr( $canonical, 0, $pos ) ),
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
 * @param string $place_id Provider raw id, or already-canonical id.
 * @param array  $facts    Optional facts: geo_latitude, geo_longitude, geo_address,
 *                         ax_geo_place_type, ax_geo_radius.
 * @return string Canonical "source:id", or '' when the source is invalid or the id empty.
 */
function axismundi_geodata_bind_place_identity( int $term_id, string $source, string $place_id, array $facts = array() ) : string {
	$canonical = axismundi_geodata_normalize_place_id( $source, $place_id );
	if ( '' === $canonical ) {
		return '';
	}

	update_term_meta( $term_id, 'ax_geo_place_id', $canonical );
	delete_term_meta( $term_id, 'ax_geo_source' ); // Legacy split key.

	foreach ( array( 'geo_latitude', 'geo_longitude', 'geo_address', 'ax_geo_place_type', 'ax_geo_radius' ) as $key ) {
		if ( array_key_exists( $key, $facts ) && '' !== $facts[ $key ] && null !== $facts[ $key ] ) {
			update_term_meta( $term_id, $key, $facts[ $key ] );
		}
	}

	return $canonical;
}
