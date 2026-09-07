<?php
/**
 * One-time migration for taxonomy identifiers used before the plugin review.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Move pre-review term-taxonomy rows to the plugin-prefixed identifiers.
 *
 * Terms, term meta, and object relationships are all keyed by term or
 * term-taxonomy IDs, so renaming the taxonomy field preserves the existing
 * hierarchy and assignments without copying content.
 *
 * @return void
 */
function axismundi_geodata_migrate_taxonomy_identifiers() : void {
	$option = 'axismundi_geodata_taxonomy_migration_version';
	if ( (int) get_option( $option, 0 ) >= AXISMUNDI_GEODATA_TAXONOMY_MIGRATION_VERSION ) {
		return;
	}

	global $wpdb;
	$renames = array(
		'geo_area' => AXISMUNDI_GEODATA_TAXONOMY_AREA,
		'geotag'   => AXISMUNDI_GEODATA_TAXONOMY_TAG,
	);
	foreach ( $renames as $legacy => $current ) {
		$updated = $wpdb->update(
			$wpdb->term_taxonomy,
			array( 'taxonomy' => $current ),
			array( 'taxonomy' => $legacy ),
			array( '%s' ),
			array( '%s' )
		);
		if ( false === $updated ) {
			return;
		}
	}

	update_option( $option, AXISMUNDI_GEODATA_TAXONOMY_MIGRATION_VERSION, false );
	flush_rewrite_rules();
}
add_action( 'init', 'axismundi_geodata_migrate_taxonomy_identifiers', 21 );
