<?php
/** Canonical local Object route, independent of WordPress post routing. */
defined( 'ABSPATH' ) || exit;

function axismundi_op_local_object_route() : void {
	$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	$base = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) ) . '/objects/';
	if ( ! is_string( $path ) || ! str_starts_with( $path, $base ) ) { return; }
	$method = strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) { status_header( 405 ); header( 'Allow: GET, HEAD' ); exit; }
	$uuid = substr( $path, strlen( $base ) );
	$row = preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $uuid ) ? axismundi_op_get_local_object( axismundi_op_local_object_uri( $uuid ) ) : null;
	$visible = is_array( $row ) && ! empty( $row['publicly_readable'] );
	$status = ! $visible ? 404 : ( 'tombstone' === $row['object_status'] ? 410 : 200 );
	status_header( $status );
	nocache_headers();
	header( 'Vary: Accept', false );
	header( 'X-Content-Type-Options: nosniff' );
	$json = axismundi_op_accepts_activitystreams( (string) ( $_SERVER['HTTP_ACCEPT'] ?? '' ) );
	if ( $json ) {
		header( 'Content-Type: application/activity+json; charset=UTF-8' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, HEAD' );
		if ( 'HEAD' !== $method ) {
			$document = $visible ? axismundi_op_transform_object( new Axismundi_OP_Local_Source( $row ) ) : array( 'error' => 'Not found' );
			if ( is_wp_error( $document ) ) { status_header( 500 ); $document = array( 'error' => 'Object rendering failed' ); }
			echo wp_json_encode( $document, JSON_HEX_TAG | JSON_HEX_AMP );
		}
	} else {
		header( 'Content-Type: text/html; charset=UTF-8' );
		if ( 'HEAD' !== $method ) {
			echo '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
			wp_head();
			echo '</head><body><main>';
			if ( 200 === $status ) { echo axismundi_op_render_object_by_uri( $row['object_uri'] ); }
			else { echo '<h1>' . esc_html( 410 === $status ? __( 'Deleted object', 'axismundi-object-projections' ) : __( 'Not found', 'axismundi-object-projections' ) ) . '</h1>'; }
			echo '</main>'; wp_footer(); echo '</body></html>';
		}
	}
	exit;
}
add_action( 'template_redirect', 'axismundi_op_local_object_route', -10 );
