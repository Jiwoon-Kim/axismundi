<?php
/**
 * Latitude / Longitude columns on the geo term list tables.
 *
 * Registering custom columns also gives WordPress the Screen Options checkboxes
 * to show or hide them per user, so the coordinates are visible at a glance but
 * never forced into the table.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add Latitude / Longitude columns to a geo term list table.
 *
 * @param array<string,string> $columns Existing columns.
 * @return array<string,string>
 */
function axismundi_geodata_term_columns( array $columns ) : array {
	$columns['ax_geo_latitude']  = __( 'Latitude', 'axismundi-geodata' );
	$columns['ax_geo_longitude'] = __( 'Longitude', 'axismundi-geodata' );

	return $columns;
}

/**
 * Fill the coordinate columns from term meta.
 *
 * @param string $content Existing column content.
 * @param string $column  Column key.
 * @param int    $term_id Term id.
 * @return string
 */
function axismundi_geodata_term_column_content( string $content, string $column, int $term_id ) : string {
	if ( 'ax_geo_latitude' !== $column && 'ax_geo_longitude' !== $column ) {
		return $content;
	}

	$value = get_term_meta( $term_id, $column, true );

	return '' === $value ? '&mdash;' : esc_html( $value );
}

/**
 * Wire the column filters for both geo taxonomies.
 *
 * @return void
 */
function axismundi_geodata_admin_columns_init() : void {
	foreach ( array( 'geo_area', 'geotag' ) as $taxonomy ) {
		add_filter( "manage_edit-{$taxonomy}_columns", 'axismundi_geodata_term_columns' );
		add_filter( "manage_{$taxonomy}_custom_column", 'axismundi_geodata_term_column_content', 10, 3 );
	}
}
add_action( 'admin_init', 'axismundi_geodata_admin_columns_init' );
