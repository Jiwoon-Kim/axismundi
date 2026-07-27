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

/** Read-only source for one Actor's follower-count projection. */
final class Axismundi_OP_Actor_Followers {
	public function __construct( private Axismundi_Actor $actor, private string $collection = 'followers', private int $page = 0 ) {}
	public function get_actor() : Axismundi_Actor { return $this->actor; }
	public function get_collection() : string { return 'following' === $this->collection ? 'following' : 'followers'; }
	public function get_page() : int { return $this->page; }
}

/** Stable public Outbox URI owned by the representation layer. */
function axismundi_op_actor_outbox_url( Axismundi_Actor $actor ) : string {
	return rest_url( 'axismundi/v1/actors/' . rawurlencode( $actor->get_uuid() ) . '/outbox' );
}

/** Stable Followers URI owned by the representation layer. */
function axismundi_op_actor_followers_url( Axismundi_Actor $actor ) : string {
	return axismundi_op_actor_follow_collection_url( $actor, 'followers' );
}

/** Stable Followers/Following URI owned by the representation layer. */
function axismundi_op_actor_follow_collection_url( Axismundi_Actor $actor, string $collection, int $page = 0 ) : string {
	$collection = 'following' === $collection ? 'following' : 'followers';
	$url = rest_url( 'axismundi/v1/actors/' . rawurlencode( $actor->get_uuid() ) . '/' . $collection );
	return $page > 0 ? add_query_arg( 'page', $page, $url ) : $url;
}

/** Supply Activities with the representation-owned Followers address. */
function axismundi_op_supply_actor_followers_url( string $uri, Axismundi_Actor $actor ) : string {
	return axismundi_op_actor_followers_url( $actor );
}
add_filter( 'axismundi_act_actor_followers_uri', 'axismundi_op_supply_actor_followers_url', 10, 2 );

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
	if ( function_exists( 'axismundi_actors_profile_field_attachments' ) ) {
		$attachments = axismundi_actors_profile_field_attachments( $actor );
		if ( ! empty( $attachments ) ) {
			$object['attachment'] = $attachments;
		}
	}
	if ( function_exists( 'axismundi_act_get_public_outbox' ) ) {
		$object['outbox'] = axismundi_op_actor_outbox_url( $actor );
	}
	if ( function_exists( 'axismundi_act_get_follower_count' ) ) {
		$object['followers'] = axismundi_op_actor_followers_url( $actor );
	}
	if ( function_exists( 'axismundi_act_get_following_count' ) ) {
		$object['following'] = axismundi_op_actor_follow_collection_url( $actor, 'following' );
	}

	/**
	 * Supply protocol transport properties without transferring representation ownership.
	 *
	 * @param array<string,mixed> $fields inbox/publicKey/endpoints/etc.
	 * @param Axismundi_Actor     $actor  Local Actor.
	 */
	$fields  = (array) apply_filters( 'axismundi_op_actor_transport_fields', array(), $actor );
	$allowed = array_intersect_key( $fields, array_flip( array( 'inbox', 'endpoints', 'publicKey' ) ) );
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

/** Public disclosure gate for an Actor Followers collection. */
function axismundi_op_actor_followers_visible( Axismundi_OP_Actor_Followers $source ) : bool {
	$actor = $source->get_actor();
	return axismundi_op_actor_visible( $actor ) && function_exists( 'axismundi_act_get_follow_collection_page' );
}

/**
 * Project Follow collections as OrderedCollection roots and pages.
 *
 * The root is always served, whatever the policy, because the Actor document advertises
 * its address and an advertised URI that 404s is a broken document. What varies is how
 * much of it is filled in:
 *
 *   public     - `totalItems` and `first`.
 *   count-only - `totalItems`, no `first`. The absence of `first` is the machine-readable
 *                way to say "do not enumerate this", and it is the same signal we honour
 *                when reading somebody else's collection.
 *   private    - neither. `totalItems` is omitted rather than sent as 0: zero is a claim
 *                that the account has no followers, which is not what was asked for.
 *
 * @param Axismundi_OP_Actor_Followers $source Collection source.
 * @return array<string,mixed>
 */
function axismundi_op_actor_followers_transform( Axismundi_OP_Actor_Followers $source ) : array {
	$actor  = $source->get_actor();
	$kind   = $source->get_collection();
	$page   = $source->get_page();
	$policy = function_exists( 'axismundi_actors_follow_collections_policy' )
		? axismundi_actors_follow_collections_policy( $actor )
		: 'private';
	$public = 'public' === $policy;
	$url    = axismundi_op_actor_follow_collection_url( $actor, $kind );
	$data   = $public || 'count-only' === $policy
		? axismundi_act_get_follow_collection_page( 'followers' === $kind ? 'object' : 'subject', $actor->get_uri(), max( 1, $page ), 20 )
		: array( 'items' => array(), 'total' => 0, 'has_more' => false );

	if ( $page > 0 ) {
		// Pages are route-gated to `public` already; this stays defensive rather than
		// trusting that the only caller will always be the one that exists today.
		$out = array(
			'id'           => axismundi_op_actor_follow_collection_url( $actor, $kind, $page ),
			'type'         => 'OrderedCollectionPage',
			'attributedTo' => $actor->get_uri(),
			'url'          => $actor->get_profile_url(),
			'partOf'       => $url,
			'totalItems'   => $data['total'],
			'orderedItems' => $public ? $data['items'] : array(),
		);
		if ( $public && ! empty( $data['has_more'] ) ) {
			$out['next'] = axismundi_op_actor_follow_collection_url( $actor, $kind, $page + 1 );
		}
		return $out;
	}

	$out = array(
		'id'           => $url,
		'type'         => 'OrderedCollection',
		'attributedTo' => $actor->get_uri(),
		'url'          => $actor->get_profile_url(),
	);
	if ( 'private' !== $policy ) {
		$out['totalItems'] = $data['total'];
	}
	if ( $public && $data['total'] > 0 ) {
		$out['first'] = axismundi_op_actor_follow_collection_url( $actor, $kind, 1 );
	}
	return $out;
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
	axismundi_op_register_collection_transformer(
		'axismundi-actor-followers',
		array(
			'supports'       => static fn( $source ) : bool => $source instanceof Axismundi_OP_Actor_Followers,
			'collection_uri' => static fn( Axismundi_OP_Actor_Followers $source ) : string => axismundi_op_actor_follow_collection_url( $source->get_actor(), $source->get_collection(), $source->get_page() ),
			'transform'      => 'axismundi_op_actor_followers_transform',
			'visible'        => 'axismundi_op_actor_followers_visible',
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

/** Register the representation-owned Followers route. */
function axismundi_op_register_actor_followers_route() : void {
	if ( ! class_exists( 'Axismundi_Actor' ) || ! function_exists( 'axismundi_actors_get_by_uuid' ) || ! function_exists( 'axismundi_act_get_follow_collection_page' ) ) {
		return;
	}
	register_rest_route(
		'axismundi/v1',
		'/actors/(?P<uuid>[0-9a-f-]{36})/(?P<collection>followers|following)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_op_get_actor_followers',
			'permission_callback' => '__return_true',
			'args'                => array(
				'uuid' => array( 'required' => true, 'type' => 'string', 'pattern' => '^[0-9a-f-]{36}$' ),
				'collection' => array( 'required' => true, 'type' => 'string', 'enum' => array( 'followers', 'following' ) ),
				'page' => array( 'required' => false, 'type' => 'integer', 'minimum' => 1 ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_op_register_actor_followers_route' );

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

/** Serve one public count-only Followers collection. */
function axismundi_op_get_actor_followers( WP_REST_Request $request ) {
	$actor  = axismundi_actors_get_by_uuid( strtolower( (string) $request['uuid'] ) );
	$kind = 'following' === (string) $request['collection'] ? 'following' : 'followers';
	$page = max( 0, absint( $request['page'] ) );
	$source = $actor instanceof Axismundi_Actor ? new Axismundi_OP_Actor_Followers( $actor, $kind, $page ) : null;
	// `function_exists` matters here and not only in the transform: this endpoint is
	// public, so an Actors plugin older than this policy would turn a version skew into a
	// fatal error on an anonymous request rather than a missing feature.
	$pages_allowed = $actor instanceof Axismundi_Actor
		&& function_exists( 'axismundi_actors_follow_collections_are_public' )
		&& axismundi_actors_follow_collections_are_public( $actor );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || ! $source instanceof Axismundi_OP_Actor_Followers || ! axismundi_op_actor_followers_visible( $source ) || ( $page > 0 && ! $pages_allowed ) ) {
		return new WP_Error( 'ax_op_followers_not_found', __( 'The Actor followers collection was not found.', 'axismundi-object-projections' ), array( 'status' => 404 ) );
	}
	$collection = axismundi_op_transform_collection( $source );
	if ( is_wp_error( $collection ) ) {
		return $collection;
	}
	$response = rest_ensure_response( $collection );
	$response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );
	$response->header( 'Cache-Control', 'public, max-age=60' );
	return $response;
}
