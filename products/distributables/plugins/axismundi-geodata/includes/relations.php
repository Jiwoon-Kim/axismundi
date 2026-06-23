<?php
/**
 * Geotag -> geo_area containment relationship.
 *
 * A geotag stores a single leaf geo_area term id (ax_geo_area). The full
 * containment chain is derived by walking the geo_area hierarchy upward, so the
 * tree is the one source of truth — re-parenting an area updates every geotag's
 * chain for free. "geotags in area X" expands X to its descendant areas.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * The leaf geo_area term id a geotag is contained in (0 if unset).
 *
 * @param int $geotag_id Geotag term id.
 * @return int
 */
function axismundi_geodata_get_geotag_area_id( int $geotag_id ) : int {
	return (int) get_term_meta( $geotag_id, 'ax_geo_area', true );
}

/**
 * The containment chain for a geotag, leaf first: [ leaf, parent, ..., root ].
 * Empty when no area is assigned.
 *
 * @param int $geotag_id Geotag term id.
 * @return int[] geo_area term ids.
 */
function axismundi_geodata_get_geotag_area_chain( int $geotag_id ) : array {
	$area = axismundi_geodata_get_geotag_area_id( $geotag_id );
	if ( ! $area ) {
		return array();
	}

	return array_merge( array( $area ), get_ancestors( $area, 'geo_area', 'taxonomy' ) );
}

/**
 * Geotag terms contained in a geo_area, optionally including its descendant
 * areas (so "geotags in 부산광역시" also returns those in 수영구, 광안동, ...).
 *
 * @param int  $area_id         geo_area term id.
 * @param bool $with_descendants Include geotags in descendant areas.
 * @return WP_Term[] Geotag terms.
 */
function axismundi_geodata_get_geotags_in_area( int $area_id, bool $with_descendants = true ) : array {
	$area_ids = array( $area_id );

	if ( $with_descendants ) {
		$area_ids = array_merge( $area_ids, get_term_children( $area_id, 'geo_area' ) );
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'geotag',
			'hide_empty' => false,
			// Querying the small geotag term set by its single area pointer; not a
			// hot path over post rows.
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'ax_geo_area',
					'value'   => array_map( 'intval', $area_ids ),
					'compare' => 'IN',
				),
			),
		)
	);

	return is_wp_error( $terms ) ? array() : $terms;
}
