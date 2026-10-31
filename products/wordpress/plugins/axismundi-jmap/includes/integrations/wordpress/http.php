<?php
/** Authenticated WordPress JMAP API; not a public Session/blob/SSE service. */
namespace Axismundi\Jmap\Http;
defined( 'ABSPATH' ) || exit;

function permission( \WP_REST_Request $request ) {
	if ( ! current_user_can( 'read' ) || ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
		return new \WP_Error( 'ax_jmap_forbidden', 'Authentication required.', array( 'status' => 403 ) );
	}
	return true;
}

function dispatch( \WP_REST_Request $request ) {
	try { return rest_ensure_response( \Axismundi\Jmap\execute_json( $request->get_body() ) ); }
	catch ( \Axismundi\Jmap\RequestError $error ) {
		return new \WP_REST_Response( $error->problem(), $error->status, array( 'Content-Type' => 'application/problem+json' ) );
	}
	catch ( \Throwable $error ) {
		error_log( 'JMAP REST server failure: ' . get_class( $error ) );
		return new \WP_Error( 'ax_jmap_server_failed', 'Server failed.', array( 'status' => 500 ) );
	}
}

// WordPress validates JSON parameters before the route callback can reject the envelope.
add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
	if ( null !== $result || 'POST' !== $request->get_method()
		|| ! in_array( $request->get_route(), array( '/axismundi-jmap/v1/api', '/axismundi-jmap/v1/todo' ), true ) ) {
		return $result;
	}
	try { json_decode( $request->get_body(), false, 512, JSON_THROW_ON_ERROR ); }
	catch ( \JsonException $error ) {
		$allowed = permission( $request );
		if ( true !== $allowed ) { return $allowed; }
		$problem = new \Axismundi\Jmap\RequestError( 'notJSON', 400, 'Request is not valid JSON.' );
		return new \WP_REST_Response( $problem->problem(), $problem->status, array( 'Content-Type' => 'application/problem+json' ) );
	}
	return $result;
}, 10, 3 );

add_filter( 'rest_post_dispatch', function ( $response, $server, $request ) {
	if ( '/axismundi-jmap/v1/api' === $request->get_route() || '/axismundi-jmap/v1/todo' === $request->get_route() ) {
		$response = rest_ensure_response( $response );
		$response->header( 'Cache-Control', 'private, no-store' );
	}
	return $response;
}, 10, 3 );

add_action( 'rest_api_init', function () {
	register_rest_route( 'axismundi-jmap/v1', '/api', array(
		'methods' => 'POST',
		'permission_callback' => __NAMESPACE__ . '\\permission',
		'callback' => __NAMESPACE__ . '\\dispatch',
	) );
} );
