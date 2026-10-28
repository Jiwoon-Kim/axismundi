<?php
/** Account-policy-gated liked representation; membership belongs to Activities. */
defined( 'ABSPATH' ) || exit;

function axismundi_op_actor_liked_url( Axismundi_Actor $actor ) : string {
	return rest_url( 'axismundi/v1/actors/' . rawurlencode( $actor->get_uuid() ) . '/liked' );
}

function axismundi_op_get_actor_liked( WP_REST_Request $request ) {
	$actor = axismundi_actors_get_by_uuid( (string) $request->get_url_params()['uuid'] );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || ! axismundi_op_actor_visible( $actor )
		|| ! function_exists( 'axismundi_act_get_liked_collection_page' ) ) {
		return new WP_Error( 'ax_op_liked_not_found', 'The liked collection was not found.', array( 'status' => 404 ) );
	}
	$url = axismundi_op_actor_liked_url( $actor );
	$access = axismundi_act_get_liked_collection_page( $actor->get_uri(), PHP_INT_MAX, 1 );
	if ( is_wp_error( $access ) ) { return $access; }
	$page = 1 === (int) $request['page'];
	$metadata = array( 'id' => $url, 'type' => 'OrderedCollection', 'attributedTo' => $actor->get_uri() );
	$membership = array();
	if ( $page ) {
		$before = isset( $request['before'] ) ? (int) $request['before'] : PHP_INT_MAX;
		$membership = axismundi_act_get_liked_collection_page( $actor->get_uri(), $before );
		if ( is_wp_error( $membership ) ) { return $membership; }
		$metadata['id'] = add_query_arg( 'page', 1, $url );
		if ( $before < PHP_INT_MAX ) { $metadata['id'] = add_query_arg( 'before', $before, $metadata['id'] ); }
		$metadata['type'] = 'OrderedCollectionPage';
		$metadata['partOf'] = $url;
		if ( null !== $membership['next'] ) {
			$metadata['next'] = add_query_arg( array( 'page' => 1, 'before' => $membership['next'] ), $url );
		}
	} else {
		$metadata['first'] = add_query_arg( 'page', 1, $url );
	}
	$document = axismundi_op_assemble_collection( 'ap-liked', $metadata, $membership, 'public', false );
	if ( is_wp_error( $document ) ) { return $document; }
	$document = array_merge( array( '@context' => axismundi_op_jsonld_context( $document ) ), $document );
	return new WP_REST_Response( $document, 200, array( 'Content-Type' => 'application/activity+json', 'Cache-Control' => 'no-store' ) );
}

function axismundi_op_register_actor_liked_route() : void {
	if ( ! function_exists( 'axismundi_act_get_liked_collection_page' ) ) { return; }
	register_rest_route( 'axismundi/v1', '/actors/(?P<uuid>[0-9a-f-]{36})/liked', array(
		'methods' => WP_REST_Server::READABLE, 'callback' => 'axismundi_op_get_actor_liked', 'permission_callback' => '__return_true',
		'args' => array( 'page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 1 ), 'before' => array( 'type' => 'integer', 'minimum' => 1 ) ),
	) );
}
add_action( 'rest_api_init', 'axismundi_op_register_actor_liked_route' );
