<?php
/**
 * First-party Axismundi Actor projection.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Read-only source for one Actor's public Outbox projection. */
final class Axismundi_OP_Actor_Outbox {
	public function __construct( private Axismundi_Actor $actor ) {}
	public function get_actor() : Axismundi_Actor { return $this->actor; }
}

/** Stable public Outbox URI owned by the representation layer. */
function axismundi_op_actor_outbox_url( Axismundi_Actor $actor ) : string {
	return rest_url( 'axismundi/v1/actors/' . rawurlencode( $actor->get_uuid() ) . '/outbox' );
}

/** Public representation gate for one local Actor. */
function axismundi_op_actor_visible( Axismundi_Actor $actor ) : bool {
	return function_exists( 'axismundi_actors_is_public_profile' )
		&& axismundi_actors_is_public_profile( $actor );
}

/** Project one Axismundi Actor; transport fields are supplied by a bridge filter. */
function axismundi_op_actor_transform( Axismundi_Actor $actor ) : array {
	$profile = function_exists( 'axismundi_actors_profile_data' )
		? axismundi_actors_profile_data( $actor )
		: array();
	$object  = array(
		'id'                      => $actor->get_uri(),
		'type'                    => $actor->get_type(),
		'url'                     => $actor->get_profile_url(),
		'preferredUsername'       => $actor->get_preferred_username(),
		'name'                    => (string) ( $profile['name'] ?? $actor->get_display_name() ),
		'summary'                 => (string) ( $profile['summary'] ?? '' ),
		'manuallyApprovesFollowers' => true === $actor->get_policy_flag( 'manually_approves_followers' ),
	);
	if ( null !== $actor->get_policy_flag( 'discoverable' ) ) {
		$object['discoverable'] = $actor->get_policy_flag( 'discoverable' );
	}
	if ( null !== $actor->get_policy_flag( 'indexable' ) ) {
		$object['indexable'] = $actor->get_policy_flag( 'indexable' );
	}
	if ( '' !== $actor->get_published_at() ) {
		$object['published'] = mysql2date( DATE_RFC3339, $actor->get_published_at(), false );
	}
	$avatar_id = $actor->get_avatar_attachment_id();
	$header_id = $actor->get_header_attachment_id();
	$avatar    = $avatar_id > 0 ? wp_get_attachment_image_url( $avatar_id, 'medium' ) : (string) ( $profile['avatar'] ?? '' );
	$header    = $header_id > 0 ? wp_get_attachment_image_url( $header_id, 'large' ) : '';
	if ( '' !== $avatar ) {
		$object['icon'] = array( 'type' => 'Image', 'url' => esc_url_raw( $avatar ) );
	}
	if ( '' !== $header ) {
		$object['image'] = array( 'type' => 'Image', 'url' => esc_url_raw( $header ) );
	}
	if ( function_exists( 'axismundi_act_get_public_outbox' ) ) {
		$object['outbox'] = axismundi_op_actor_outbox_url( $actor );
	}

	/**
	 * Supply protocol transport properties without transferring representation ownership.
	 *
	 * @param array<string,mixed> $fields inbox/publicKey/endpoints/etc.
	 * @param Axismundi_Actor     $actor  Local Actor.
	 */
	$fields  = (array) apply_filters( 'axismundi_op_actor_transport_fields', array(), $actor );
	$allowed = array_intersect_key( $fields, array_flip( array( 'inbox', 'followers', 'following', 'featured', 'endpoints', 'publicKey' ) ) );
	return array_merge( $object, $allowed );
}

/** Public visibility gate for an Actor Outbox. */
function axismundi_op_actor_outbox_visible( Axismundi_OP_Actor_Outbox $source ) : bool {
	return axismundi_op_actor_visible( $source->get_actor() )
		&& function_exists( 'axismundi_act_get_public_outbox' );
}

/** Project one Actor's public Activity ledger into an OrderedCollection. */
function axismundi_op_actor_outbox_transform( Axismundi_OP_Actor_Outbox $source ) : array {
	$actor = $source->get_actor();
	return array(
		'id'           => axismundi_op_actor_outbox_url( $actor ),
		'type'         => 'OrderedCollection',
		'attributedTo' => $actor->get_uri(),
		'url'          => $actor->get_profile_url(),
		'orderedItems' => axismundi_act_get_public_outbox( $actor->get_uri(), 200 ),
	);
}

/** Register the Actor transformer when the Actors plugin is available. */
function axismundi_op_register_actor_transformers() : void {
	if ( ! class_exists( 'Axismundi_Actor' ) ) {
		return;
	}
	axismundi_op_register_object_transformer(
		'axismundi-actor',
		array(
			'supports'   => static fn( $source ) : bool => $source instanceof Axismundi_Actor,
			'object_uri' => static fn( Axismundi_Actor $actor ) : string => $actor->get_uri(),
			'transform'  => 'axismundi_op_actor_transform',
			'visible'    => 'axismundi_op_actor_visible',
			'priority'   => 5,
		)
	);
	axismundi_op_register_collection_transformer(
		'axismundi-actor-outbox',
		array(
			'supports'       => static fn( $source ) : bool => $source instanceof Axismundi_OP_Actor_Outbox,
			'collection_uri' => static fn( Axismundi_OP_Actor_Outbox $source ) : string => axismundi_op_actor_outbox_url( $source->get_actor() ),
			'transform'      => 'axismundi_op_actor_outbox_transform',
			'visible'        => 'axismundi_op_actor_outbox_visible',
			'priority'       => 5,
		)
	);
}
add_action( 'axismundi_op_register_transformers', 'axismundi_op_register_actor_transformers' );

/** Register the representation-owned public Outbox route. */
function axismundi_op_register_actor_outbox_route() : void {
	if ( ! class_exists( 'Axismundi_Actor' ) || ! function_exists( 'axismundi_actors_get_by_uuid' ) || ! function_exists( 'axismundi_act_get_public_outbox' ) ) {
		return;
	}
	register_rest_route(
		'axismundi/v1',
		'/actors/(?P<uuid>[0-9a-f-]{36})/outbox',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_op_get_actor_outbox',
			'permission_callback' => '__return_true',
			'args'                => array(
				'uuid' => array( 'required' => true, 'type' => 'string', 'pattern' => '^[0-9a-f-]{36}$' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_op_register_actor_outbox_route' );

/** Serve one public local Actor Outbox without transport-plugin involvement. */
function axismundi_op_get_actor_outbox( WP_REST_Request $request ) {
	$actor = axismundi_actors_get_by_uuid( strtolower( (string) $request['uuid'] ) );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || ! axismundi_op_actor_visible( $actor ) ) {
		return new WP_Error( 'ax_op_outbox_not_found', __( 'The Actor outbox was not found.', 'axismundi-object-projections' ), array( 'status' => 404 ) );
	}
	$collection = axismundi_op_transform_collection( new Axismundi_OP_Actor_Outbox( $actor ) );
	if ( is_wp_error( $collection ) ) {
		return $collection;
	}
	$response = rest_ensure_response( $collection );
	$response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );
	$response->header( 'Cache-Control', 'public, max-age=60' );
	return $response;
}
