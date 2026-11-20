<?php
/** Run in a fresh WP-CLI eval-file process; real REST dispatch, no Todo registry. */
defined( 'ABSPATH' ) || exit( 1 );

$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $label ); }
};
$initial_user = get_current_user_id();
$registration = $GLOBALS['wp_filter']['axismundi_jmap_register'] ?? null;
remove_all_actions( 'axismundi_jmap_register' );
$dispatch = static function ( string $body, ?string $nonce, string $route = '/axismundi/jmap' ) {
	$request = new WP_REST_Request( 'POST', $route );
	$request->set_header( 'Content-Type', 'application/json' );
	if ( null !== $nonce ) { $request->set_header( 'X-WP-Nonce', $nonce ); }
	$request->set_body( $body );
	$response = rest_do_request( $request );
	// WP_REST_Server::serve_request applies this after dispatch, not rest_do_request.
	return apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
};
$wire = static fn( array $calls, array $using = array( 'urn:ietf:params:jmap:core' ) ) => wp_json_encode( array( 'using' => $using, 'methodCalls' => $calls ) );
try {
	$users = get_users( array( 'capability' => 'read', 'number' => 1, 'fields' => 'ID' ) );
	$assert( ! empty( $users ), 'An existing read-capable fixture User is required' );
	wp_set_current_user( (int) $users[0] );
	$nonce = wp_create_nonce( 'wp_rest' );
	$assert( null === \Axismundi\Jmap\runtime_registry()->method( 'Todo/get' ), 'Fresh generic registry excludes Todo' );
	$body = $wire( array( array( 'Core/echo', (object) array( 'hello' => 'world' ), 'e' ) ) );
		// The retired alias paths must stop accepting the batch, not merely stop being advertised.
	foreach ( array( '/axismundi/jmap/api', '/axismundi-jmap/v1/api', '/axismundi-jmap/v1/todo' ) as $gone ) {
		$assert( 404 === $dispatch( $body, $nonce, $gone )->get_status(), 'Retired API path no longer dispatches: ' . $gone );
	}
	foreach ( array( '/axismundi/jmap', '/axismundi/jmap/todo' ) as $route ) {
		$response = $dispatch( $body, $nonce, $route );
		$data = $response->get_data();
		$assert( 200 === $response->get_status() && 'world' === $data->methodResponses[0][1]->hello, 'Core/echo dispatch: ' . $route );
		$assert( 'private, no-store' === ( $response->get_headers()['Cache-Control'] ?? null ), 'Success is not cacheable' );
	}
	$response = $dispatch( $wire( array( array( 'Missing/get', new stdClass(), 'm' ) ) ), $nonce );
	$assert( 'unknownMethod' === $response->get_data()->methodResponses[0][1]->type, 'Unknown method is a method error' );
	foreach ( array( '{' => 'notJSON', $wire( array(), array( 'urn:ietf:params:jmap:core', 'urn:example:missing' ) ) => 'unknownCapability' ) as $body => $type ) {
		$response = $dispatch( $body, $nonce );
		$assert( 400 === $response->get_status() && 'urn:ietf:params:jmap:error:' . $type === $response->get_data()->type, 'Correct RequestError problem' );
		$assert( 'application/problem+json' === ( $response->get_headers()['Content-Type'] ?? null ), 'Problem media type' );
		$assert( 'private, no-store' === ( $response->get_headers()['Cache-Control'] ?? null ), 'Request errors are not cacheable' );
	}
	foreach ( array( null, 'invalid' ) as $bad_nonce ) {
		$response = $dispatch( $body, $bad_nonce );
		$assert( 403 === $response->get_status(), 'Missing or invalid nonce denied' );
		$assert( 'private, no-store' === ( $response->get_headers()['Cache-Control'] ?? null ), 'Permission errors are not cacheable' );
	}
	wp_set_current_user( 0 );
	// No principal is a prompt to authenticate, not a refusal: a client that discovered this
	// endpoint must be able to tell "sign in" (401) from "credentials would not help" (403).
	$anonymous = $dispatch( $body, $nonce );
	$assert( 401 === $anonymous->get_status(), 'Anonymous access asks for authentication even with another User nonce' );
	$assert( 'ax_jmap_unauthenticated' === ( $anonymous->get_data()['code'] ?? null ), 'Anonymous denial names the missing authentication' );
	$assert( 'private, no-store' === ( $anonymous->get_headers()['Cache-Control'] ?? null ), 'Authentication errors are not cacheable' );
	echo 'PASS: ' . $checks . " HTTP transport checks\n";
} finally {
	wp_set_current_user( $initial_user );
	if ( null !== $registration ) { $GLOBALS['wp_filter']['axismundi_jmap_register'] = $registration; }
}
