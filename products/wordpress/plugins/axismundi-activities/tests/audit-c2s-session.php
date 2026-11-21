<?php
/** Real AP Session HTTP, with an isolated acting grant and no successful submissions. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$origin = rtrim( getenv( 'AX_C2S_HTTP_ORIGIN' ) ?: home_url(), '/' );
$host = wp_parse_url( home_url(), PHP_URL_HOST );
$port = wp_parse_url( home_url(), PHP_URL_PORT );
$host .= $port ? ':' . $port : '';
$base = $origin . '/wp-json/axismundi/';
$session_path = 'v1/users/me';
$session_type = 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
};
$request = static function ( string $path, array $auth = array(), ?array $payload = null ) use ( $base, $host ) : array {
	$response = wp_remote_request( $base . $path, array(
		'method' => null === $payload ? 'GET' : 'POST', 'timeout' => 30, 'redirection' => 0,
		'headers' => array_merge( array( 'Host' => $host, 'Content-Type' => 'application/json' ), $auth ),
		'body' => null === $payload ? null : wp_json_encode( $payload ),
	) );
	if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
	return array( wp_remote_retrieve_response_code( $response ), json_decode( wp_remote_retrieve_body( $response ), true ), wp_remote_retrieve_header( $response, 'cache-control' ), wp_remote_retrieve_header( $response, 'content-type' ) );
};
$users = array();
$ids = array();
$original_user = get_current_user_id();
try {
	foreach ( array( 'caller', 'owner' ) as $kind ) {
		$login = 'axaps_' . $kind . '_' . strtolower( wp_generate_password( 8, false, false ) );
		$user = wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
		$assert( is_int( $user ), 'Fixture User created' );
		$users[] = $user;
	}
	[ $caller, $owner ] = $users;
	$bare = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'initial_owner_user_id' => $owner ) );
	$assert( $bare instanceof Axismundi_Actor, 'Handle-less Actor fixture created' );
	$ids[] = $bare->get_identity_id();
	$bare_url = $origin . wp_parse_url( $bare->get_uri(), PHP_URL_PATH );
	$bare_get = static function () use ( $bare_url, $host ) {
		$response = wp_remote_get( $bare_url, array(
			'redirection' => 0, 'timeout' => 30,
			'headers' => array( 'Host' => $host, 'Accept' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ),
		) );
		if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
		return $response;
	};
	$bare_response = $bare_get();
	$bare_document = json_decode( wp_remote_retrieve_body( $bare_response ), true );
	$assert( 200 === wp_remote_retrieve_response_code( $bare_response )
		&& $bare->get_uri() === ( $bare_document['id'] ?? null ) && 'Person' === ( $bare_document['type'] ?? null )
		&& ! empty( $bare_document['inbox'] ) && ! empty( $bare_document['outbox'] ) && ! empty( $bare_document['publicKey'] ),
		'Anonymous canonical GET reads a handle-less Actor with inbox, outbox and key' );
	$assert( ! array_key_exists( 'preferredUsername', $bare_document ) && ! array_key_exists( 'url', $bare_document ),
		'Absent handle and profile URL are omitted, not fabricated' );
	$assert( axismundi_actors_set_status( $bare->get_identity_id(), 'disabled' ), 'Disable handle-less Actor' );
	$assert( 404 === wp_remote_retrieve_response_code( $bare_get() ), 'Disabled Actor remains hidden on canonical GET' );
	$actor = axismundi_actors_ensure_for_user( $owner );
	$assert( $actor instanceof Axismundi_Actor, 'Fixture Actor created' );
	$id = $actor->get_identity_id();
	$ids[] = $id;
	axismundi_actors_register_handle( $id, get_userdata( $owner )->user_login );
	axismundi_actors_set_status( $id, 'public' );
	$actor = axismundi_actors_get_by_identity( $id );
	$uri = $actor->get_uri();
	$path = 'activitypub/actors/' . $actor->get_uuid() . '/outbox';
	$secret = WP_Application_Passwords::create_new_application_password( $caller, array( 'name' => 'AP Session audit' ) );
	$assert( ! is_wp_error( $secret ), 'Temporary Application Password created' );
	$auth = array( 'Authorization' => 'Basic ' . base64_encode( get_userdata( $caller )->user_login . ':' . $secret[0] ) );
	$assert( 401 === $request( $session_path )[0], 'Anonymous Session denied' );
	$assert( 401 === $request( $session_path, array( 'Authorization' => 'Basic invalid' ) )[0], 'An unverified header does not authenticate Session' );
	[ $status, $empty ] = $request( $session_path . '?userId=' . $owner, $auth );
	$assert( 200 === $status && array() === ( $empty['actors'] ?? null ), 'Valid actorless login is empty; userId query cannot impersonate the owner' );
	$assert( true === axismundi_actors_set_principal_role( $id, 'wp_user', $caller, 'editor' ), 'Caller granted acting access' );
	wp_set_current_user( $caller );
	$projected = axismundi_act_c2s_session();
	[ $status, $session, $cache, $media ] = $request( $session_path, $auth + array( 'Accept' => $session_type ) );
	$assert( 200 === $status && $projected['actors'] === ( $session['actors'] ?? null ) && str_contains( $cache, 'private' ) && str_contains( $cache, 'no-store' ), 'REST serves the authenticated read-only projection without caching' );
	$assert( $session_type === $media, 'Session is served as profiled JSON-LD' );
	$assert( 'c2s:Session' === ( $session['type'] ?? null ) && rest_url( 'axismundi/' . $session_path ) === ( $session['url'] ?? null )
		&& ! array_key_exists( 'id', $session ) && 'https://www.w3.org/ns/activitystreams' === ( $session['@context'][0] ?? null ),
		'Session has an AS2 context and an endpoint URL, not a snapshot identifier' );
	$entry = $session['actors'][0] ?? array();
	$assert( 1 === count( $session['actors'] ) && array( 'id' => $uri ) === $entry,
		'Session advertises only the canonical Actor reference, not identity or endpoint copies' );
	if ( function_exists( 'Axismundi\Jmap\server' ) ) {
		$account = \Axismundi\Jmap\server()->accounts()->accounts->{ 'a' . $id } ?? null;
		// JMAP and AP are separate sessions that share only authentication: the
		// Account exists for the same login but carries no link to the Actor.
		$assert( null !== $account && ! property_exists( $account, 'actorUri' )
			&& ! isset( $account->accountCapabilities->{'urn:com.example:jmap:todo'} ),
			'JMAP Account for the same login exists without an Actor link or a Todo entitlement' );
	}
	$assert( 422 === $request( $path, $auth, array( 'type' => 'Unsupported' ) )[0], 'Advertised outbox accepts the caller up to payload validation' );
	$assert( true === axismundi_actors_set_principal_role( $id, 'wp_user', $caller, null ), 'Acting access revoked' );
	[ $status, $empty ] = $request( $session_path, $auth );
	$assert( 200 === $status && array() === ( $empty['actors'] ?? null ), 'Revoked Actor disappears from the next Session' );
	$assert( 403 === $request( $path, $auth, array( 'type' => 'Unsupported' ) )[0], 'An old Session URL does not retain permission to submit' );
	$assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE actor_uri=%s', axismundi_act_activities_table(), $uri ) ), 'Discovery and refusal create no Activity rows' );
	printf( "PASS: %d AP Session checks\n", $checks );
} finally {
	wp_set_current_user( $original_user );
	foreach ( $ids as $id ) {
		$wpdb->delete( axismundi_actors_meta_table(), array( 'actor_id' => $id ) );
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_endpoints_table(), axismundi_actors_addresses_table(), axismundi_actors_texts_table(), axismundi_actors_signing_keys_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $id ) );
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	foreach ( $users as $user ) { wp_delete_user( $user ); }
}
