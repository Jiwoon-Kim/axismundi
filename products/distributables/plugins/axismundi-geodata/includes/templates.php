<?php
/**
 * Plugin-owned templates for the public geo taxonomy archives.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/** Read one bundled block-template file. */
function axismundi_geodata_template_content( string $slug ) : string {
	if ( ! in_array( $slug, array( 'taxonomy-geo_area', 'taxonomy-geotag' ), true ) ) {
		return '';
	}
	$path = dirname( __DIR__ ) . '/templates/' . $slug . '.html';
	return is_readable( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bundled local template.
}

/** Render the bundled PHP pattern so its strings use the Geodata text domain. */
function axismundi_geodata_archive_pattern_content() : string {
	$path = dirname( __DIR__ ) . '/patterns/geo-archive-results.php';
	if ( ! is_readable( $path ) ) {
		return '';
	}
	ob_start();
	include $path;
	return (string) ob_get_clean();
}

/** Register the existing rich archive pattern under its domain owner. */
function axismundi_geodata_register_archive_pattern() : void {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	$content = axismundi_geodata_archive_pattern_content();
	if ( '' === trim( $content ) ) {
		return;
	}
	register_block_pattern(
		'axismundi-geodata/geo-archive-results',
		array(
			'title'       => __( 'Geo archive results', 'axismundi-geodata' ),
			'description' => __( 'A geographic archive with a map, inherited post feed, and place navigation.', 'axismundi-geodata' ),
			'categories'  => array( 'query' ),
			'inserter'    => false,
			'content'     => $content,
		)
	);
}
add_action( 'init', 'axismundi_geodata_register_archive_pattern', 19 );

/** Register both standard taxonomy hierarchy templates. */
function axismundi_geodata_register_archive_templates() : void {
	if ( ! function_exists( 'register_block_template' ) ) {
		return;
	}

	foreach (
		array(
			'taxonomy-geo_area' => array(
				'title'       => __( 'Geo Area Archive', 'axismundi-geodata' ),
				'description' => __( 'Displays posts assigned to one geographic area.', 'axismundi-geodata' ),
			),
			'taxonomy-geotag'   => array(
				'title'       => __( 'Geotag Archive', 'axismundi-geodata' ),
				'description' => __( 'Displays posts assigned to one named place.', 'axismundi-geodata' ),
			),
		) as $slug => $args
	) {
		$content = axismundi_geodata_template_content( $slug );
		if ( '' === trim( $content ) ) {
			continue;
		}
		register_block_template(
			'axismundi-geodata//' . $slug,
			array(
				'title'       => $args['title'],
				'description' => $args['description'],
				'content'     => $content,
			)
		);
	}
}
add_action( 'init', 'axismundi_geodata_register_archive_templates', 20 );
