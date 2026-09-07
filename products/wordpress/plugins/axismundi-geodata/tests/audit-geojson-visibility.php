<?php
/** GeoJSON anonymous attachment visibility regression. */

defined( 'ABSPATH' ) || exit( 1 );

$results = array();
$posts   = array();

function ax_geo_vis_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function ax_geo_vis_attachment( int $parent, string $title, string $mime_type = 'image/jpeg' ) : int {
	$id = wp_insert_attachment( array( 'post_mime_type' => $mime_type, 'post_title' => $title, 'post_status' => 'inherit' ), '', 0, true );
	if ( is_wp_error( $id ) ) {
		return 0;
	}

	$id = (int) $id;
	if ( $parent > 0 ) {
		wp_update_post( array( 'ID' => $id, 'post_parent' => $parent ) );
	}

	return $id;
}

try {
	$public = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 1, 'post_title' => 'GeoJSON public parent' ) );
	$private = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'private', 'post_author' => 1, 'post_title' => 'GeoJSON private parent' ) );
	$posts = array( $public, $private );
	$public_attachment  = ax_geo_vis_attachment( $public, 'Public coordinate' );
	$private_attachment = ax_geo_vis_attachment( $private, 'Private coordinate' );
	$orphan_attachment  = ax_geo_vis_attachment( 0, 'Orphan coordinate' );
	$private_track      = ax_geo_vis_attachment( $private, 'Private track', AXISMUNDI_GEODATA_GPX_MIME );
	$posts = array_merge( $posts, array( $public_attachment, $private_attachment, $orphan_attachment, $private_track ) );
	foreach ( array( $public_attachment, $private_attachment, $orphan_attachment ) as $id ) {
		update_post_meta( $id, 'geo_public', '1' );
		update_post_meta( $id, 'geo_latitude', '35.1' );
		update_post_meta( $id, 'geo_longitude', '129.1' );
	}
	ax_geo_vis_assert( $results, 'only an opted-in attachment with a published public parent is anonymously GeoJSON-viewable', axismundi_geodata_attachment_geojson_publicly_viewable( $public_attachment ) && ! axismundi_geodata_attachment_geojson_publicly_viewable( $private_attachment ) && ! axismundi_geodata_attachment_geojson_publicly_viewable( $orphan_attachment ) );
	$request = new WP_REST_Request( 'GET', '/axismundi-geodata/v1/media' );
	$request->set_param( 'ids', implode( ',', array( $public_attachment, $private_attachment, $orphan_attachment ) ) );
	$data = axismundi_geodata_rest_media_geojson( $request )->get_data();
	ax_geo_vis_assert( $results, 'the public media endpoint omits private-parent and orphan attachment coordinates', 1 === count( $data['features'] ?? array() ) && $public_attachment === (int) ( $data['features'][0]['properties']['id'] ?? 0 ) );
	$track_request = new WP_REST_Request( 'GET', '/axismundi-geodata/v1/track/' . $private_track );
	$track_request->set_param( 'id', $private_track );
	$track = axismundi_geodata_rest_track_geojson( $track_request );
	ax_geo_vis_assert( $results, 'the public track endpoint returns 404 before reading a private-parent track', $track instanceof WP_Error && 'axismundi_not_track' === $track->get_error_code() && 404 === (int) $track->get_error_data()['status'] );
} finally {
	foreach ( array_unique( $posts ) as $id ) { if ( $id > 0 && get_post( $id ) instanceof WP_Post ) { wp_delete_post( $id, true ); } }
}

$failures = count( array_filter( $results, static fn( bool $result ) : bool => ! $result ) );
printf( "\n== %d checks, %d failed ==\n", count( $results ), $failures ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
if ( class_exists( 'WP_CLI' ) ) { WP_CLI::halt( $failures ); }
exit( $failures > 0 ? 1 : 0 );
