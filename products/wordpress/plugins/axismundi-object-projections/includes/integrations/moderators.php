<?php
/** Community moderator representation. Does not add attributedTo to Actor documents. */
defined( 'ABSPATH' ) || exit;

final class Axismundi_OP_Community_Moderators {
	public function __construct( public readonly Axismundi_Actor $actor ) {}
}

function axismundi_op_register_moderators_contract() : void {
	if ( ! function_exists( 'axismundi_actors_read_moderators' ) ) { return; }
	axismundi_op_register_collection_contract( 'ap-moderators', array(
		'owner' => 'axismundi-actors', 'member_models' => array( 'Actor' ), 'member_types' => array( 'Person' ),
		'membership_source' => 'explicit:Add/Remove', 'membership_authority' => 'membership-row', 'append_policy' => 'owner-only',
		'presentation' => 'bounded-members', 'ordering' => 'source-defined', 'collection_type' => 'OrderedCollection',
		'total_capability' => 'unavailable', 'total_reason' => 'member-level-visibility',
		'total_disclosure' => array( 'public' => 'never', 'owner' => 'never' ), 'reader' => 'axismundi_actors_read_moderators',
	) );
}
add_action( 'axismundi_op_register_collection_contracts', 'axismundi_op_register_moderators_contract' );

function axismundi_op_moderators_transform( Axismundi_OP_Community_Moderators $source ) {
	$members = axismundi_op_read_collection_contract( 'ap-moderators', $source->actor );
	return is_wp_error( $members ) ? $members : axismundi_op_assemble_collection( 'ap-moderators', array( 'id' => axismundi_actors_moderators_uri( $source->actor ), 'type' => 'OrderedCollection' ), $members );
}
add_action( 'axismundi_op_register_transformers', static function () : void {
	axismundi_op_register_collection_transformer( 'community-moderators', array(
		'supports' => static fn( $source ) : bool => $source instanceof Axismundi_OP_Community_Moderators,
		'collection_uri' => static fn( Axismundi_OP_Community_Moderators $source ) : string => axismundi_actors_moderators_uri( $source->actor ),
		'transform' => 'axismundi_op_moderators_transform',
		'visible' => static fn( Axismundi_OP_Community_Moderators $source ) : bool => axismundi_actors_is_community( $source->actor ) && axismundi_op_actor_visible( $source->actor ),
	) );
} );

function axismundi_op_get_actor_moderators( WP_REST_Request $request ) {
	$group = axismundi_actors_get_by_uuid( (string) ( $request->get_url_params()['uuid'] ?? '' ) );
	if ( ! $group || ! axismundi_actors_is_community( $group ) || ! axismundi_op_actor_visible( $group ) ) { return new WP_Error( 'ax_op_moderators_missing', 'The Community moderator collection was not found.', array( 'status' => 404 ) ); }
	$document = axismundi_op_transform_collection( new Axismundi_OP_Community_Moderators( $group ) );
	return is_wp_error( $document ) ? $document : new WP_REST_Response( $document, 200, array( 'Content-Type' => 'application/activity+json', 'Cache-Control' => 'no-store', 'Access-Control-Allow-Origin' => '*' ) );
}
add_action( 'rest_api_init', static function () : void {
	if ( ! function_exists( 'axismundi_actors_read_moderators' ) ) { return; }
	register_rest_route( 'axismundi/v1', '/actors/(?P<uuid>[a-f0-9-]{36})/moderators', array( 'methods' => 'GET', 'callback' => 'axismundi_op_get_actor_moderators', 'permission_callback' => '__return_true' ) );
} );
