<?php
/**
 * Geo taxonomies.
 *
 * Two taxonomies with deliberately different shapes, so "where it is" and "what
 * place it's about" never collapse into one ambiguous tree:
 *
 *   geo_area  hierarchical  — address / administrative containment (is-in).
 *                             대한민국 > 부산광역시 > 수영구 > 광안동
 *   geotag    flat          — place / content geo tags (topical).
 *                             광안리해수욕장, 광안대교, 야경명소
 *
 * Both attach to the term-bearing object types (post / page; ax_note later via
 * the axismundi_geodata_object_types filter). Attachments are NOT included —
 * they carry coordinate meta only.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register geo_area (hierarchical) and geotag (flat).
 *
 * @return void
 */
function axismundi_geodata_register_taxonomies() : void {
	$object_types = axismundi_geodata_object_types();

	register_taxonomy(
		'geo_area',
		$object_types,
		array(
			'hierarchical'      => true,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => false,
			'rewrite'           => array( 'slug' => 'geo-area' ),
			'labels'            => array(
				'name'          => _x( 'Geo Areas', 'Taxonomy general name', 'axismundi-geodata' ),
				'singular_name' => _x( 'Geo Area', 'Taxonomy singular name', 'axismundi-geodata' ),
				'search_items'  => __( 'Search Geo Areas', 'axismundi-geodata' ),
				'all_items'     => __( 'All Geo Areas', 'axismundi-geodata' ),
				'parent_item'   => __( 'Parent Geo Area', 'axismundi-geodata' ),
				'edit_item'     => __( 'Edit Geo Area', 'axismundi-geodata' ),
				'add_new_item'  => __( 'Add New Geo Area', 'axismundi-geodata' ),
				'menu_name'     => __( 'Geo Areas', 'axismundi-geodata' ),
			),
		)
	);

	register_taxonomy(
		'geotag',
		$object_types,
		array(
			'hierarchical'      => false,
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => false,
			'rewrite'           => array( 'slug' => 'geotag' ),
			'labels'            => array(
				'name'          => _x( 'Geotags', 'Taxonomy general name', 'axismundi-geodata' ),
				'singular_name' => _x( 'Geotag', 'Taxonomy singular name', 'axismundi-geodata' ),
				'search_items'  => __( 'Search Geotags', 'axismundi-geodata' ),
				'all_items'     => __( 'All Geotags', 'axismundi-geodata' ),
				'edit_item'     => __( 'Edit Geotag', 'axismundi-geodata' ),
				'add_new_item'  => __( 'Add New Geotag', 'axismundi-geodata' ),
				'menu_name'     => __( 'Geotags', 'axismundi-geodata' ),
			),
		)
	);
}
add_action( 'init', 'axismundi_geodata_register_taxonomies' );
