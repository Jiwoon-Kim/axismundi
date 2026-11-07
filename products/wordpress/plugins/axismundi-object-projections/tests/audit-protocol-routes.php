<?php
/** Protocol namespace migration: real dispatch, read aliases and application separation. */
defined( 'ABSPATH' ) || exit( 1 );
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	echo "PASS {$label}\n";
};
$user = get_current_user_id();
$uri = $_SERVER['REQUEST_URI'] ?? null;
$uuid = '00000000-0000-4000-8000-000000000001';
try {
	wp_set_current_user( 0 );
	$routes = rest_get_server()->get_routes();
	foreach ( array( 'inbox', 'outbox', 'followers', 'following', 'liked', 'featured', 'sent', 'moderators' ) as $collection ) {
		$path = '/actors/' . $uuid . '/' . $collection;
		$query = array( 'page' => 1 );
		if ( 'sent' === $collection ) { $query['audience'] = 'none'; $query['in_reply_to'] = 'unknown'; }
		$request = new WP_REST_Request( 'GET', '/axismundi/v1' . $path );
		$request->set_query_params( $query );
		$response = rest_do_request( $request );
		$expected = add_query_arg( $query, rest_url( 'axismundi/activitypub' . $path ) );
		$assert( 302 === $response->get_status() && $expected === ( $response->get_headers()['Location'] ?? '' ), 'Legacy read preserves collection and filters: ' . $collection );
	}
	foreach ( array( 'replies', 'likes', 'shares', 'emoji-reactions' ) as $collection ) {
		$request = new WP_REST_Request( 'GET', '/axismundi/v1/objects/' . $collection );
		$query = array( 'object' => 'https://example.test/notes/a?x=1&y=2' );
		$request->set_query_params( $query );
		$response = rest_do_request( $request );
		$assert( 302 === $response->get_status() && add_query_arg( $query, rest_url( 'axismundi/activitypub/objects/' . $collection ) ) === ( $response->get_headers()['Location'] ?? '' ), 'Legacy Object collection preserves canonical URI argument: ' . $collection );
	}
	$request = new WP_REST_Request( 'GET', '/axismundi/v1/activitypub/inbox' );
	$request->set_query_params( array( 'page' => 1, 'cursor' => 'r123' ) );
	$response = rest_do_request( $request );
	$assert( 302 === $response->get_status() && add_query_arg( array( 'page' => 1, 'cursor' => 'r123' ), axismundi_op_shared_inbox_url() ) === $response->get_headers()['Location'], 'Legacy shared Inbox points to /activitypub/inbox, not a doubled prefix' );
	foreach ( array( '/axismundi/v1/objects/(?P<id>[1-9][0-9]*)', '/axismundi/v1/objects/read', '/axismundi/v1/hashtags/(?P<term_id>[1-9][0-9]*)/members', '/axismundi/v1/actors/(?P<uuid>[a-f0-9-]{36})/moderators/initialize' ) as $route ) {
		$assert( isset( $routes[$route] ), 'Application read remains in v1: ' . $route );
	}
	$assert( ! isset( $routes['/axismundi/activitypub/objects/read'] ) && ! isset( $routes['/axismundi/activitypub/hashtags/(?P<term_id>[1-9][0-9]*)/members'] ) && ! isset( $routes['/axismundi/activitypub/actors/(?P<uuid>[a-f0-9-]{36})/moderators/initialize'] ), 'Application queries and commands are not duplicated into the protocol namespace' );
	$nodeinfo = rest_do_request( new WP_REST_Request( 'GET', '/axismundi/nodeinfo/2.1' ) );
	$assert( 200 === $nodeinfo->get_status() && '2.1' === ( $nodeinfo->get_data()['version'] ?? '' ), 'Actors serves NodeInfo at its protocol namespace' );
	$links = axismundi_actors_nodeinfo_discovery()['links'];
	$assert( 1 === count( $links ) && rest_url( 'axismundi/nodeinfo/2.1' ) === $links[0]['href'], 'Discovery advertises one canonical NodeInfo link' );
	$_SERVER['REQUEST_URI'] = wp_parse_url( home_url( '/.well-known/nodeinfo' ), PHP_URL_PATH );
	$wp = new WP();
	$wp->query_vars = array( 'rest_route' => '/activitypub/1.0/nodeinfo' );
	axismundi_actors_route_nodeinfo_discovery( $wp );
	$assert( 'discovery' === $wp->query_vars['ax_nodeinfo'] && ! isset( $wp->query_vars['rest_route'] ), 'Exact discovery path overrides a stale upstream rewrite before REST dispatch' );
	$_SERVER['REQUEST_URI'] .= '/unrelated';
	$wp->query_vars = array( 'rest_route' => '/activitypub/1.0/nodeinfo' );
	axismundi_actors_route_nodeinfo_discovery( $wp );
	$assert( '/activitypub/1.0/nodeinfo' === $wp->query_vars['rest_route'], 'Discovery override does not capture neighboring paths' );
} finally {
	wp_set_current_user( $user );
	if ( null === $uri ) { unset( $_SERVER['REQUEST_URI'] ); } else { $_SERVER['REQUEST_URI'] = $uri; }
}
echo "== {$checks} protocol route checks, 0 failed ==\n";
