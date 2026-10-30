<?php
/** Real admin reads using a temporary session, no new User/Actor. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
$origin = getenv( 'AX_ADMIN_HTTP_ORIGIN' );
if ( ! $origin ) { throw new RuntimeException( 'Set AX_ADMIN_HTTP_ORIGIN to the reachable site origin (wp-env: http://wordpress).' ); }
$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) )[0] ?? 0 );
if ( ! $admin ) { throw new RuntimeException( 'Existing administrator required' ); }
$sessions = WP_Session_Tokens::get_instance( $admin );
$expires = time() + 600; $token = $sessions->create( $expires );
$cookies = AUTH_COOKIE . '=' . wp_generate_auth_cookie( $admin, $expires, 'auth', $token ) . '; ' . LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie( $admin, $expires, 'logged_in', $token );
$host = wp_parse_url( admin_url(), PHP_URL_HOST ); $port = wp_parse_url( admin_url(), PHP_URL_PORT );
$headers = array( 'Host' => $host . ( $port ? ':' . $port : '' ), 'Cookie' => $cookies );
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; };
$read = static function ( string $path ) use ( $origin, $headers ) {
	$r = wp_remote_get( untrailingslashit( $origin ) . wp_parse_url( admin_url(), PHP_URL_PATH ) . $path, array( 'headers' => $headers, 'timeout' => 90, 'redirection' => 0 ) );
	if ( is_wp_error( $r ) ) { throw new RuntimeException( $r->get_error_message() ); }
	return $r;
};
try {
	foreach ( array( 'axismundi-all-accounts' => 'All Accounts', 'axismundi-local-actors' => 'Local Actors', 'axismundi-add-local-actor' => 'Add Local Actor', 'axismundi-remote-actors' => 'Remote Actors', 'axismundi-managed-actors' => 'Managed actors' ) as $slug => $label ) {
		$r = $read( 'admin.php?page=' . $slug );
		$assert( 200 === wp_remote_retrieve_response_code( $r ) && str_contains( wp_remote_retrieve_body( $r ), '<h1>' . $label . '</h1>' ), $label . ': HTTP 200 and page heading' );
	}
	$r = $read( 'admin.php?page=axismundi-actor-profile' ); $body = wp_remote_retrieve_body( $r );
	$assert( 200 === wp_remote_retrieve_response_code( $r ), 'Profile HTTP 200' );
	$assert( str_contains( $body, 'toplevel_page_axismundi-accounts' ), 'Accounts top-level menu rendered' );
	$assert( str_contains( $body, 'axismundi-actors-media-js' ), 'Profile media picker loads after parent move' );
	$r = $read( 'admin.php?page=axismundi-contacts' ); $body = wp_remote_retrieve_body( $r );
	$assert( 200 === wp_remote_retrieve_response_code( $r ), 'Contacts HTTP 200' );
	$assert( str_contains( $body, 'axismundi-contacts-card-editor-js' ), 'Contacts editor assets load after parent move' );
	$r = $read( 'users.php?page=axismundi-contacts&action=lookup&search=fixture' );
	$location = wp_remote_retrieve_header( $r, 'location' );
	$assert( 302 === wp_remote_retrieve_response_code( $r ) && str_contains( $location, '/admin.php?' ), 'Legacy Users URL redirects to admin parent: ' . wp_remote_retrieve_response_code( $r ) . ' ' . $location );
	$assert( str_contains( $location, 'action=lookup' ) && str_contains( $location, 'search=fixture' ), 'Legacy redirect preserves navigation context' );
} finally { $sessions->destroy( $token ); }
echo "PASS accounts-navigation-http {$checks}\n";
