<?php
/** Inbox read representations; Activities owns receipts and Bridge owns POST transport. */
defined( 'ABSPATH' ) || exit;

function axismundi_op_actor_inbox_url( Axismundi_Actor $actor ) : string {
	return rest_url( 'axismundi/activitypub/actors/' . rawurlencode( $actor->get_uuid() ) . '/inbox' );
}

function axismundi_op_shared_inbox_url() : string {
	return rest_url( 'axismundi/activitypub/inbox' );
}

/** Assemble receipt pages through the same contract and response helpers as other Actor collections. */
function axismundi_op_inbox_collection_response( string $contract, string $url, WP_REST_Request $request, array $page, bool $shared ) {
	$data = array( 'id' => $url, 'type' => 'OrderedCollection' );
	if ( ! $shared ) { $data['url'] = $url; }
	$is_page = $request['page'] || ( $shared && $request['cursor'] );
	if ( $is_page ) {
		$data['id'] = add_query_arg( array_filter( array( 'page' => 1, 'cursor' => (string) $request['cursor'] ) ), $url );
		$data['type'] = 'OrderedCollectionPage';
		$data['partOf'] = $url;
		if ( $page['has_more'] ) {
			$data['next'] = add_query_arg( array( 'page' => 1, 'cursor' => $page['next_cursor'] ), $url );
		}
	} else {
		$data['first'] = add_query_arg( 'page', 1, $url );
	}
	$data = axismundi_op_assemble_collection( $contract, $data, $is_page ? array( 'items' => $page['items'] ) : array(), $shared ? 'public' : 'owner' );
	if ( is_wp_error( $data ) ) { return $data; }
	$data = array_merge( array( '@context' => axismundi_op_jsonld_context( $data ) ), $data );
	$response = axismundi_op_actor_collection_response( $data );
	if ( is_wp_error( $response ) ) { return $response; }
	$response->header( 'Cache-Control', $shared ? 'no-store' : 'private, no-store' );
	return $response;
}

/** Activities rechecks the current Account's access before returning any membership. */
function axismundi_op_get_actor_inbox( WP_REST_Request $request ) {
	$actor = axismundi_actors_get_by_uuid( strtolower( (string) ( $request->get_url_params()['uuid'] ?? '' ) ) );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || 'public' !== $actor->get_status() ) {
		return new WP_Error( 'ax_bridge_inbox_missing', 'The Actor Inbox is unavailable.', array( 'status' => 404 ) );
	}
	$page = axismundi_op_read_collection_contract( 'ap-inbox', $actor->get_uri(), 20, (string) $request['cursor'] );
	return is_wp_error( $page ) ? $page : axismundi_op_inbox_collection_response( 'ap-inbox', axismundi_op_actor_inbox_url( $actor ), $request, $page, false );
}

/** The existing site disclosure setting controls GET only, never POST reception. */
function axismundi_op_get_shared_inbox( WP_REST_Request $request ) {
	if ( ! (bool) get_option( 'ax_bridge_shared_inbox_public', true ) ) {
		return new WP_Error( 'ax_bridge_shared_private', 'The shared Inbox collection is not public.', array( 'status' => 404 ) );
	}
	$page = axismundi_op_read_collection_contract( 'ap-shared-inbox', 20, (string) $request['cursor'] );
	return is_wp_error( $page ) ? $page : axismundi_op_inbox_collection_response( 'ap-shared-inbox', axismundi_op_shared_inbox_url(), $request, $page, true );
}

/** Register only reads; verifier availability must not determine receipt readability. */
function axismundi_op_register_inbox_routes() : void {
	if ( ! function_exists( 'axismundi_actors_get_by_uuid' ) || ! function_exists( 'axismundi_act_get_actor_inbox_page' ) || ! function_exists( 'axismundi_act_get_shared_inbox_page' ) ) { return; }
	$args = array( 'page' => array( 'type' => 'integer', 'minimum' => 1 ), 'cursor' => array( 'type' => 'string', 'default' => '' ) );
	axismundi_op_register_activitypub_read_route( '/inbox', array(
		'methods' => WP_REST_Server::READABLE, 'callback' => 'axismundi_op_get_shared_inbox',
		'permission_callback' => '__return_true', 'args' => $args,
	) );
	$args['uuid'] = array( 'required' => true, 'type' => 'string', 'pattern' => '^[0-9a-f-]{36}$' );
	axismundi_op_register_activitypub_read_route( '/actors/(?P<uuid>[0-9a-f-]{36})/inbox', array(
		'methods' => WP_REST_Server::READABLE, 'callback' => 'axismundi_op_get_actor_inbox',
		'permission_callback' => '__return_true', 'args' => $args,
	) );
}
add_action( 'rest_api_init', 'axismundi_op_register_inbox_routes' );
