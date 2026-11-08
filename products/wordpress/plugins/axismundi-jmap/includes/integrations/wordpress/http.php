<?php
/** Authenticated JMAP Session and API; SSE remains an explicit stub. */
namespace Axismundi\Jmap\Http;
defined( 'ABSPATH' ) || exit;

function permission( \WP_REST_Request $request ) {
	$nonce = sanitize_text_field( (string) $request->get_header( 'X-WP-Nonce' ) );
	$query = $request->get_query_params();
	// Core accepts a query nonce for ordinary authenticated browser download links.
	if ( '' === $nonce && isset( $query['_wpnonce'] ) && is_string( $query['_wpnonce'] ) ) {
		$nonce = sanitize_text_field( $query['_wpnonce'] );
	}
	if ( ! current_user_can( 'read' ) || ( ! rest_get_authenticated_app_password()
		&& ! wp_verify_nonce( $nonce, 'wp_rest' ) ) ) {
		return new \WP_Error( 'ax_jmap_forbidden', 'Authentication required.', array( 'status' => 403 ) );
	}
	return true;
}

function dispatch( \WP_REST_Request $request ) {
	try { return rest_ensure_response( \Axismundi\Jmap\server()->execute( $request->get_body(), session_transport() ) ); }
	catch ( \Axismundi\Jmap\RequestError $error ) {
		return new \WP_REST_Response( $error->problem(), $error->status, array( 'Content-Type' => 'application/problem+json' ) );
	}
	catch ( \Throwable $error ) {
		error_log( 'JMAP REST server failure: ' . get_class( $error ) );
		return new \WP_Error( 'ax_jmap_server_failed', 'Server failed.', array( 'status' => 500 ) );
	}
}

function session_transport() : \stdClass {
	$base = rest_url( 'axismundi/jmap/' );
	return (object) array(
		'apiUrl' => $base . 'api',
		'uploadUrl' => $base . 'upload/{accountId}',
		'downloadUrl' => $base . 'download/{accountId}/{blobId}/{name}?type={type}',
		'eventSourceUrl' => $base . 'eventsource?types={types}&closeafter={closeafter}&ping={ping}',
	);
}

function session( \WP_REST_Request $request ) {
	try { return new \WP_REST_Response( \Axismundi\Jmap\server()->session( session_transport() ) ); }
	catch ( \Throwable $error ) {
		error_log( 'JMAP Session unavailable: ' . get_class( $error ) );
		return new \WP_Error( 'ax_jmap_session_unavailable', 'Session configuration is unavailable; HTTPS is required outside local development.', array( 'status' => 503 ) );
	}
}

function unavailable() : \WP_REST_Response {
	return new \WP_REST_Response( array( 'type' => 'about:blank', 'title' => 'Not Implemented', 'status' => 501, 'detail' => 'EventSource is not implemented by this authoring slice.' ), 501, array( 'Content-Type' => 'application/problem+json' ) );
}

// WordPress validates JSON parameters before the route callback can reject the envelope.
add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
	if ( null !== $result || 'POST' !== $request->get_method()
		|| ! in_array( $request->get_route(), array( '/axismundi/jmap/api', '/axismundi/jmap/todo', '/axismundi-jmap/v1/api', '/axismundi-jmap/v1/todo' ), true ) ) {
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
	if ( preg_match( '#^/(?:axismundi/jmap|axismundi-jmap/v1)(?:/|$)#', $request->get_route() ) ) {
		$response = rest_ensure_response( $response );
		$response->header( 'Cache-Control', 'private, no-store' );
		$response->header( 'Vary', 'Authorization, Cookie' );
	}
	return $response;
}, 10, 3 );

add_action( 'rest_api_init', function () {
	foreach ( array( 'axismundi/jmap', 'axismundi-jmap/v1' ) as $namespace ) {
		// POST compatibility aliases execute in place, never redirect authenticated requests.
		register_rest_route( $namespace, '/api', array( 'methods' => 'POST', 'permission_callback' => __NAMESPACE__ . '\\permission', 'callback' => __NAMESPACE__ . '\\dispatch' ) );
	}
	register_rest_route( 'axismundi/jmap', '/session', array( 'methods' => 'GET', 'permission_callback' => __NAMESPACE__ . '\\permission', 'callback' => __NAMESPACE__ . '\\session' ) );
	register_rest_route( 'axismundi/jmap', '/eventsource', array( 'methods' => 'GET', 'permission_callback' => __NAMESPACE__ . '\\permission', 'callback' => __NAMESPACE__ . '\\unavailable' ) );
} );

/** Exact discovery-path interception works without a rewrite flush or protocol URL relocation. */
add_action( 'parse_request', function ( \WP $wp ) : void {
	$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	$expected = wp_parse_url( home_url( '/.well-known/jmap' ), PHP_URL_PATH );
	if ( ! is_string( $path ) || untrailingslashit( $path ) !== $expected ) { return; }
	$method = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
	nocache_headers();
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		status_header( 405 ); header( 'Allow: GET, HEAD' ); exit;
	}
	wp_safe_redirect( rest_url( 'axismundi/jmap/session' ), 302 );
	exit;

}, 0 );
