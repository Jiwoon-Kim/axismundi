<?php
/**
 * Plugin-review regression: unique taxonomy identifiers and legacy migration.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_geo_taxonomy_results = array();

/** @param bool[] $results Results. */
function ax_geo_taxonomy_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

ax_geo_taxonomy_assert(
	$ax_geo_taxonomy_results,
	'geo taxonomies use unique Axismundi-prefixed identifiers while retaining public slugs',
	taxonomy_exists( AXISMUNDI_GEODATA_TAXONOMY_AREA )
		&& taxonomy_exists( AXISMUNDI_GEODATA_TAXONOMY_TAG )
		&& ! taxonomy_exists( 'geo_area' )
		&& ! taxonomy_exists( 'geotag' )
		&& 'geo-area' === ( $GLOBALS['wp_taxonomies'][ AXISMUNDI_GEODATA_TAXONOMY_AREA ]->rewrite['slug'] ?? '' )
		&& 'geotag' === ( $GLOBALS['wp_taxonomies'][ AXISMUNDI_GEODATA_TAXONOMY_TAG ]->rewrite['slug'] ?? '' )
);

$post_id = wp_insert_post(
	array(
		'post_title'  => 'Geodata taxonomy migration audit',
		'post_status' => 'draft',
	)
);
$term    = is_int( $post_id ) && $post_id > 0
	? wp_insert_term( 'Geodata taxonomy migration audit', AXISMUNDI_GEODATA_TAXONOMY_TAG )
	: new WP_Error( 'axismundi_geodata_audit_post', 'Could not create audit post.' );

if ( ! is_wp_error( $term ) && $post_id > 0 ) {
	wp_set_object_terms( $post_id, (int) $term['term_id'], AXISMUNDI_GEODATA_TAXONOMY_TAG );
	$term_object = get_term( (int) $term['term_id'], AXISMUNDI_GEODATA_TAXONOMY_TAG );

	if ( $term_object instanceof WP_Term ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->term_taxonomy,
			array( 'taxonomy' => 'geotag' ),
			array( 'term_taxonomy_id' => $term_object->term_taxonomy_id ),
			array( '%s' ),
			array( '%d' )
		);
		clean_term_cache( (int) $term['term_id'], 'geotag' );
		delete_option( 'axismundi_geodata_taxonomy_migration_version' );
		axismundi_geodata_migrate_taxonomy_identifiers();
	}

	$assigned = get_the_terms( $post_id, AXISMUNDI_GEODATA_TAXONOMY_TAG );
	ax_geo_taxonomy_assert(
		$ax_geo_taxonomy_results,
		'identifier migration preserves legacy term relationships',
		is_array( $assigned )
			&& 1 === count( $assigned )
			&& (int) $term['term_id'] === (int) $assigned[0]->term_id
			&& AXISMUNDI_GEODATA_TAXONOMY_TAG === $assigned[0]->taxonomy
	);
} else {
	ax_geo_taxonomy_assert( $ax_geo_taxonomy_results, 'identifier migration preserves legacy term relationships', false );
}

if ( ! is_wp_error( $term ) ) {
	wp_delete_term( (int) $term['term_id'], AXISMUNDI_GEODATA_TAXONOMY_TAG );
}
if ( $post_id > 0 ) {
	wp_delete_post( $post_id, true );
}

$ax_geo_taxonomy_failures = count( array_filter( $ax_geo_taxonomy_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_geo_taxonomy_results ), $ax_geo_taxonomy_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_geo_taxonomy_failures > 0 ? 1 : 0 );
}
exit( $ax_geo_taxonomy_failures > 0 ? 1 : 0 );
