<?php
/** Person featured representation. Membership and authorization belong to Actors. */
defined( 'ABSPATH' ) || exit;

final class Axismundi_OP_Actor_Featured {
	public function __construct( public readonly Axismundi_Actor $actor ) {}
}

function axismundi_op_actor_featured_url( Axismundi_Actor $actor ) : string {
	return rest_url( 'axismundi/activitypub/actors/' . rawurlencode( $actor->get_uuid() ) . '/featured' );
}
add_filter( 'axismundi_actors_featured_uri', static fn( string $uri, Axismundi_Actor $actor ) : string => axismundi_op_actor_featured_url( $actor ), 10, 2 );

add_action( 'axismundi_op_register_vocabulary', static function () : void {
	axismundi_op_register_vocabulary_group( array( 'featured' => 'http://joinmastodon.org/ns#featured' ), array(
		'kind' => 'property', 'provenance' => 'vendor:toot', 'source' => 'https://docs.joinmastodon.org/spec/activitypub/#featured',
		'contributor' => 'axismundi-object-projections', 'group' => 'actor-featured',
		'context' => array( 'featured' => array( '@id' => 'http://joinmastodon.org/ns#featured', '@type' => '@id' ) ),
		'when' => array( array( 'kind' => 'key', 'names' => array( 'featured' ), 'path' => '~^$~' ) ),
	) );
} );

function axismundi_op_register_featured_contract() : void {
	if ( ! function_exists( 'axismundi_actors_read_featured' ) ) { return; }
	axismundi_op_register_collection_contract( 'ap-featured', array(
		'owner' => 'axismundi-actors', 'member_models' => array( 'Object' ),
		'membership_source' => 'explicit:Add/Remove', 'membership_authority' => 'membership-row', 'append_policy' => 'owner-only',
		'presentation' => 'bounded-members', 'ordering' => 'source-defined', 'collection_type' => 'OrderedCollection',
		'total_capability' => 'exact', 'total_disclosure' => array( 'public' => 'always', 'owner' => 'always' ),
		'reader' => 'axismundi_actors_read_featured',
	) );
}
add_action( 'axismundi_op_register_collection_contracts', 'axismundi_op_register_featured_contract' );

add_filter( 'axismundi_op_actor_projection_fields', static function ( array $document, Axismundi_Actor $actor ) : array {
	if ( $actor->is_local() && 'Person' === $actor->get_type() && function_exists( 'axismundi_actors_read_featured' ) ) { $document['featured'] = axismundi_op_actor_featured_url( $actor ); }
	return $document;
}, 10, 2 );

function axismundi_op_actor_featured_transform( Axismundi_OP_Actor_Featured $source ) {
	$actor = $source->actor;
	$members = axismundi_op_read_collection_contract( 'ap-featured', $actor );
	return is_wp_error( $members ) ? $members : axismundi_op_assemble_collection( 'ap-featured', array( 'id' => axismundi_op_actor_featured_url( $actor ), 'type' => 'OrderedCollection', 'attributedTo' => $actor->get_uri() ), $members );
}
add_action( 'axismundi_op_register_transformers', static function () : void {
	axismundi_op_register_collection_transformer( 'axismundi-person-featured', array(
		'supports' => static fn( $source ) : bool => $source instanceof Axismundi_OP_Actor_Featured,
		'collection_uri' => static fn( Axismundi_OP_Actor_Featured $source ) : string => axismundi_op_actor_featured_url( $source->actor ),
		'transform' => 'axismundi_op_actor_featured_transform',
		'visible' => static fn( Axismundi_OP_Actor_Featured $source ) : bool => $source->actor->is_local() && 'Person' === $source->actor->get_type() && axismundi_op_actor_visible( $source->actor ),
	) );
} );

function axismundi_op_get_actor_featured( WP_REST_Request $request ) {
	$actor = axismundi_actors_get_by_uuid( (string) ( $request->get_url_params()['uuid'] ?? '' ) );
	if ( ! $actor || ! $actor->is_local() || 'Person' !== $actor->get_type() || ! axismundi_op_actor_visible( $actor ) ) {
		return new WP_Error( 'ax_op_featured_missing', 'The featured collection was not found.', array( 'status' => 404 ) );
	}
	$document = axismundi_op_transform_collection( new Axismundi_OP_Actor_Featured( $actor ) );
	return is_wp_error( $document ) ? $document : new WP_REST_Response( $document, 200, array( 'Content-Type' => 'application/activity+json', 'Cache-Control' => 'no-store', 'Access-Control-Allow-Origin' => '*' ) );
}
add_action( 'rest_api_init', static function () : void {
	if ( ! function_exists( 'axismundi_actors_read_featured' ) ) { return; }
	axismundi_op_register_activitypub_read_route( '/actors/(?P<uuid>[0-9a-f-]{36})/featured', array(
		'methods' => WP_REST_Server::READABLE, 'callback' => 'axismundi_op_get_actor_featured', 'permission_callback' => '__return_true',
	) );
} );
