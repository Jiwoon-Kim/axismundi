<?php
/** ActivityPub read routes and legacy GET aliases; application APIs keep their own namespace. */
defined( 'ABSPATH' ) || exit;

/** Retain validated GET/HEAD aliases without ever redirecting a signed POST. */
function axismundi_op_register_activitypub_read_route( string $route, array $args, ?string $legacy = null ) : void {
	register_rest_route( 'axismundi/activitypub', $route, $args );
	$args['callback'] = 'axismundi_op_redirect_activitypub_read';
	register_rest_route( 'axismundi/v1', $legacy ?? ( '/inbox' === $route ? '/activitypub/inbox' : $route ), $args );
}

/** Only registered legacy read routes reach this callback; forward paging/filter query parameters. */
function axismundi_op_redirect_activitypub_read( WP_REST_Request $request ) {
	$path = substr( $request->get_route(), strlen( '/axismundi/v1' ) );
	if ( '/activitypub/inbox' === $path ) { $path = '/inbox'; }
	$query = $request->get_query_params();
	unset( $query['rest_route'] );
	$url = rest_url( 'axismundi/activitypub' . $path );
	if ( $query ) { $url = add_query_arg( $query, $url ); }
	return new WP_REST_Response( null, 302, array( 'Location' => $url, 'Cache-Control' => 'no-store' ) );
}
