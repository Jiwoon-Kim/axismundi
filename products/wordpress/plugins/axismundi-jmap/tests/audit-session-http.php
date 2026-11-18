<?php
/** Real HTTP discovery, Session, cookie/App Password authentication and compatibility. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
$checks = 0;
$assert = static function ( $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
	echo "PASS $label\n";
};
$initial_user = get_current_user_id();
$user = 0;
$actor = null;
$origin = rtrim( getenv( 'AX_JMAP_HTTP_ORIGIN' ) ?: home_url(), '/' );
$host = wp_parse_url( home_url(), PHP_URL_HOST );
$port = wp_parse_url( home_url(), PHP_URL_PORT );
$host .= $port ? ':' . $port : '';
$request = static function ( string $method, string $path, array $headers = array(), string $body = '' ) use ( $origin, $host ) : array {
	$r = wp_remote_request( $origin . $path, array( 'method' => $method, 'redirection' => 0, 'timeout' => 15, 'headers' => array_merge( array( 'Host' => $host, 'Content-Type' => 'application/json' ), $headers ), 'body' => $body ) );
	if ( is_wp_error( $r ) ) { throw new RuntimeException( $r->get_error_message() ); }
	return array( wp_remote_retrieve_response_code( $r ), json_decode( wp_remote_retrieve_body( $r ), true ), wp_remote_retrieve_headers( $r ) );
};
try {
	$user = wp_insert_user( array( 'user_login' => 'jmaphttp' . strtolower( wp_generate_password( 10, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	$assert( is_int( $user ), 'Temporary principal created' );
	$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'personal_owner_user_id' => $user, 'initial_owner_user_id' => $user, 'display_name' => 'JMAP HTTP fixture' ) );
	$assert( $actor instanceof Axismundi_Actor, 'Actor Account fixture created' );
	wp_set_current_user( $user );
	$expires = time() + 600;
	$token = WP_Session_Tokens::get_instance( $user )->create( $expires );
	$cookie = wp_generate_auth_cookie( $user, $expires, 'logged_in', $token );
	$_COOKIE[LOGGED_IN_COOKIE] = $cookie;
	$auth = array( 'Cookie' => LOGGED_IN_COOKIE . '=' . $cookie, 'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ) );
	$base = '/wp-json/axismundi/jmap';
	$session_path = '/wp-json/axismundi/v1/users/me/session';
	$discovery = $request( 'GET', '/.well-known/jmap' );
	$assert( 302 === $discovery[0] && rest_url( 'axismundi/v1/users/me/session' ) === (string) $discovery[2]['location'], 'Discovery redirects to the canonical Session' );
	$assert( 405 === $request( 'POST', '/.well-known/jmap' )[0], 'Discovery does not redirect POST' );
	foreach ( array( array(), array( 'Cookie' => $auth['Cookie'] ), array_merge( $auth, array( 'X-WP-Nonce' => 'invalid' ) ) ) as $bad ) {
		$r = $request( 'GET', $session_path, $bad );
		$assert( in_array( $r[0], array( 401, 403 ), true ), 'Unauthenticated or invalid cookie request denied' );
		$assert( str_contains( (string) $r[2]['cache-control'], 'no-store' ), 'Permission error is not cacheable' );
	}
	$session = $request( 'GET', $session_path, $auth );
	$assert( 200 === $session[0] && isset( $session[1]['capabilities']['urn:ietf:params:jmap:core'], $session[1]['accounts'], $session[1]['state'] ), 'Authenticated browser gets a complete Session' );
	$assert( isset( $session[1]['accounts']['a' . $actor->get_identity_id()] ), 'Session contains the accessible Actor Account' );
	$assert( str_contains( (string) $session[2]['cache-control'], 'no-store' ), 'Canonical Session is not cacheable' );
	$legacy = $request( 'GET', $base . '/session', $auth );
	$assert( 200 === $legacy[0] && $session[1] === $legacy[1] && empty( $legacy[2]['location'] ), 'Legacy Session returns the same document without redirecting' );
	foreach ( array( 'apiUrl', 'downloadUrl', 'uploadUrl', 'eventSourceUrl' ) as $field ) {
		$url = (string) ( $session[1][ $field ] ?? '' );
		// apiUrl is the namespace path itself, so requiring a trailing slash there would pass only
		// while it was wrong, and a substring test would also accept the retired /api suffix.
		$ok = 'apiUrl' === $field ? str_ends_with( $url, '/axismundi/jmap' ) : str_contains( $url, '/axismundi/jmap/' );
		$assert( '' !== $url && $ok, 'Canonical Session URL: ' . $field );
	}
	$body = wp_json_encode( array( 'using' => array( 'urn:ietf:params:jmap:core' ), 'methodCalls' => array( array( 'Core/echo', (object) array( 'hello' => 'world' ), 'e' ) ) ) );
	foreach ( array( '/wp-json/axismundi/jmap/api', '/wp-json/axismundi-jmap/v1/api', '/wp-json/axismundi-jmap/v1/todo' ) as $gone ) {
		$assert( 404 === $request( 'POST', $gone, $auth, $body )[0], 'Retired API path is gone over real HTTP: ' . $gone );
	}
	foreach ( array( $base, $base . '/todo' ) as $path ) {
		$r = $request( 'POST', $path, $auth, $body );
		$assert( 200 === $r[0] && 'world' === ( $r[1]['methodResponses'][0][1]['hello'] ?? null ) && empty( $r[2]['location'] ), 'API executes without redirect: ' . $path );
		$assert( $r[1]['sessionState'] === $session[1]['state'], 'API sessionState agrees with Session GET' );
		$assert( 400 === $request( 'POST', $path, $auth, '{' )[0], 'Malformed JSON remains a request-level error' );
	}
	foreach ( array( array( 'POST', '/upload/a0', 404 ), array( 'GET', '/download/a1/b1/name?type=text%2Fplain', 404 ), array( 'GET', '/eventsource?types=*&closeafter=state&ping=30', 501 ) ) as [$method, $path, $expected] ) {
		$r = $request( $method, $base . $path, $auth );
		$assert( $expected === $r[0], 'Invalid blob route or unimplemented EventSource is rejected' );
	}
	$app = WP_Application_Passwords::create_new_application_password( $user, array( 'name' => 'Disposable JMAP HTTP fixture' ) );
	$assert( is_array( $app ), 'Temporary Application Password created' );
	$basic = array( 'Authorization' => 'Basic ' . base64_encode( get_userdata( $user )->user_login . ':' . $app[0] ) );
	$assert( 200 === $request( 'GET', $session_path, $basic )[0], 'Real Application Password reads Session without nonce' );
	$assert( 200 === $request( 'POST', $base, $basic, $body )[0], 'Real Application Password invokes API without nonce' );
	WP_Application_Passwords::delete_application_password( $user, $app[1]['uuid'] );
	$assert( in_array( $request( 'GET', $session_path, $basic )[0], array( 401, 403 ), true ), 'Revoked Application Password is denied' );
	echo "PASS $checks real HTTP Session checks\n";
} finally {
	global $wpdb;
	if ( $actor instanceof Axismundi_Actor ) {
		$id = $actor->get_identity_id();
		foreach ( array( axismundi_actors_addresses_table(), axismundi_actors_managers_table(), axismundi_actors_signing_keys_table(), axismundi_actors_actors_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	if ( is_int( $user ) && $user > 0 ) { WP_Application_Passwords::delete_all_application_passwords( $user ); wp_delete_user( $user ); }
	unset( $_COOKIE[LOGGED_IN_COOKIE] );
	wp_set_current_user( $initial_user );
}
