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
$dispatch = static function ( string $body, ?string $nonce, string $route = '/axismundi-jmap/v1/api' ) {
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
	foreach ( array( '/axismundi-jmap/v1/api', '/axismundi-jmap/v1/todo' ) as $route ) {
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
	$assert( 403 === $dispatch( $body, $nonce )->get_status(), 'Anonymous access denied even with another User nonce' );
	echo 'PASS: ' . $checks . " HTTP transport checks\n";
} finally {
	wp_set_current_user( $initial_user );
	if ( null !== $registration ) { $GLOBALS['wp_filter']['axismundi_jmap_register'] = $registration; }
}
