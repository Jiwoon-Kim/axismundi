<?php
/**
 * Activity-backed Object likes collection projection.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Read-only source for one publicly projectable object's likes collection. */
final class Axismundi_OP_Object_Likes {
	/** @param mixed $source Local object source. */
	public function __construct( private string $object_uri, private $source ) {}
	public function get_object_uri() : string { return $this->object_uri; }
	/** @return mixed */
	public function get_source() { return $this->source; }
}

/** Read-only source for one publicly projectable object's FEP-c0e0 reactions. */
final class Axismundi_OP_Object_Emoji_Reactions {
	/** @param mixed $source Local object source. */
	public function __construct( private string $object_uri, private $source ) {}
	public function get_object_uri() : string { return $this->object_uri; }
	/** @return mixed */
	public function get_source() { return $this->source; }
}

/** Read-only source for one publicly projectable object's shares collection. */
final class Axismundi_OP_Object_Shares {
	/** @param mixed $source Local object source. */
	public function __construct( private string $object_uri, private $source ) {}
	public function get_object_uri() : string { return $this->object_uri; }
	/** @return mixed */
	public function get_source() { return $this->source; }
}

/** Stable representation URI for an Object's likes collection. */
function axismundi_op_object_likes_url( string $object_uri ) : string {
	return add_query_arg( 'object', $object_uri, rest_url( 'axismundi/v1/objects/likes' ) );
}

/** Stable representation URI for an Object's FEP-c0e0 emoji reaction collection. */
function axismundi_op_object_emoji_reactions_url( string $object_uri ) : string {
	return add_query_arg( 'object', $object_uri, rest_url( 'axismundi/v1/objects/emoji-reactions' ) );
}

/** Stable representation URI for an Object's shares collection. */
function axismundi_op_object_shares_url( string $object_uri ) : string {
	return add_query_arg( 'object', $object_uri, rest_url( 'axismundi/v1/objects/shares' ) );
}

/** Attach the likes collection URI to a projected local object. */
function axismundi_op_add_likes_property( array $object ) : array {
	if ( function_exists( 'axismundi_act_get_like_count' ) && ! empty( $object['id'] ) ) {
		$object['likes'] = axismundi_op_object_likes_url( (string) $object['id'] );
	}
	if ( function_exists( 'axismundi_act_get_effective_reactions' ) && ! empty( $object['id'] ) ) {
		// FEP-c0e0 assigns this property the Fedibird IRI. Keep the full key so this
		// document remains valid even for peers that do not compact that context term.
		//
		// `http`, not `https`, because in JSON-LD the IRI *is* the property name and the two
		// spell different terms. The FEP writes it this way, as do the sibling namespaces it
		// uses -- `http://litepub.social/ns#`, `http://joinmastodon.org/ns#` -- so `https`
		// here would publish a property no peer is looking for.
		$object['http://fedibird.com/ns#emojiReactions'] = axismundi_op_object_emoji_reactions_url( (string) $object['id'] );
	}
	if ( function_exists( 'axismundi_act_get_announce_count' ) && ! empty( $object['id'] ) ) {
		$object['shares'] = axismundi_op_object_shares_url( (string) $object['id'] );
	}
	return $object;
}
add_filter( 'axismundi_op_post_article', 'axismundi_op_add_likes_property' );
add_filter( 'axismundi_op_media_attachment', 'axismundi_op_add_likes_property' );

/** Resolve an exact public local projection source from its canonical object URI. */
function axismundi_op_local_source_from_object_uri( string $object_uri ) {
	$parts = wp_parse_url( $object_uri );
	if ( ! is_array( $parts ) ) {
		return null;
	}
	$home = wp_parse_url( home_url( '/' ) );
	if ( ! is_array( $home ) || strtolower( (string) ( $parts['host'] ?? '' ) ) !== strtolower( (string) ( $home['host'] ?? '' ) ) ) {
		return null;
	}
	$query = array();
	if ( isset( $parts['query'] ) ) {
		parse_str( (string) $parts['query'], $query );
	}
	$post_id = isset( $query['p'] ) ? absint( $query['p'] ) : ( isset( $query['attachment_id'] ) ? absint( $query['attachment_id'] ) : url_to_postid( $object_uri ) );
	$source  = $post_id > 0 ? get_post( $post_id ) : null;
	if ( ! $source instanceof WP_Post ) {
		return null;
	}
	$projected = axismundi_op_transform_object( $source );
	return is_array( $projected ) && isset( $projected['id'] ) && hash_equals( (string) $projected['id'], $object_uri ) ? $source : null;
}

/** Public visibility gate for an Object likes collection. */
function axismundi_op_object_likes_visible( Axismundi_OP_Object_Likes $source ) : bool {
	return function_exists( 'axismundi_act_get_like_count' )
		&& ! is_wp_error( axismundi_op_transform_object( $source->get_source() ) );
}

/** Public visibility gate for an Object emoji-reaction collection. */
function axismundi_op_object_emoji_reactions_visible( Axismundi_OP_Object_Emoji_Reactions $source ) : bool {
	return function_exists( 'axismundi_act_get_effective_reactions' )
		&& ! is_wp_error( axismundi_op_transform_object( $source->get_source() ) );
}

/** Project a count-only collection without disclosing liker identities. */
function axismundi_op_object_likes_transform( Axismundi_OP_Object_Likes $source ) : array {
	$object = axismundi_op_transform_object( $source->get_source() );
	if ( is_wp_error( $object ) ) {
		return $object;
	}
	return array(
		'id'           => axismundi_op_object_likes_url( $source->get_object_uri() ),
		'type'         => 'OrderedCollection',
		'attributedTo' => (string) $object['attributedTo'],
		'url'          => axismundi_op_object_html_url( $object ),
		'totalItems'   => axismundi_act_get_like_count( $source->get_object_uri() ),
	);
}

/** Project FEP-c0e0's public collection without fabricating a count-only substitute. */
function axismundi_op_object_emoji_reactions_transform( Axismundi_OP_Object_Emoji_Reactions $source ) : array {
	$object = axismundi_op_transform_object( $source->get_source() );
	if ( is_wp_error( $object ) ) {
		return $object;
	}
	/*
	 * No `as:Public` gate here, deliberately. FEP-c0e0's own examples address a reaction to
	 * the sender's followers and the object's author and nothing else, so requiring public
	 * addressing would empty this collection for exactly the implementations it exists to
	 * interoperate with. Publishing it is the point: the FEP says this URL "MUST resolve to
	 * a collection containing `Like` (with `content`) and `EmojiReact` activities".
	 *
	 * `bto`/`bcc` are the exception and are removed. A blind-copy list is addressed to
	 * people the other recipients are not meant to know about; forwarding it verbatim would
	 * disclose them to everyone who reads the collection.
	 */
	$items = array_map(
		static function ( Axismundi_Activity $activity ) : array {
			$payload = $activity->get_payload();
			unset( $payload['bto'], $payload['bcc'] );
			return $payload;
		},
		axismundi_act_get_effective_reactions( $source->get_object_uri() )
	);
	return array(
		'id'           => axismundi_op_object_emoji_reactions_url( $source->get_object_uri() ),
		'type'         => 'OrderedCollection',
		'attributedTo' => (string) $object['attributedTo'],
		'url'          => axismundi_op_object_html_url( $object ),
		// The ledger's true count, not the size of this bounded page.
		'totalItems'   => axismundi_act_get_reaction_activity_count( $source->get_object_uri() ),
		'orderedItems' => $items,
	);
}

/** Project a count-only shares collection without disclosing announcing Actors. */
function axismundi_op_object_shares_transform( Axismundi_OP_Object_Shares $source ) : array {
	$object = axismundi_op_transform_object( $source->get_source() );
	if ( is_wp_error( $object ) ) {
		return $object;
	}
	return array(
		'id'           => axismundi_op_object_shares_url( $source->get_object_uri() ),
		// Kept aligned with Axismundi's existing likes collection; Mastodon uses Collection.
		'type'         => 'OrderedCollection',
		'attributedTo' => (string) $object['attributedTo'],
		'url'          => axismundi_op_object_html_url( $object ),
		'totalItems'   => axismundi_act_get_announce_count( $source->get_object_uri() ),
	);
}

/** Register the collection transformer. */
function axismundi_op_register_reaction_transformer() : void {
	if ( ! function_exists( 'axismundi_act_get_like_count' ) && ! function_exists( 'axismundi_act_get_announce_count' ) ) {
		return;
	}
	if ( function_exists( 'axismundi_act_get_like_count' ) ) {
		axismundi_op_register_collection_transformer(
			'axismundi-object-likes',
			array(
				'supports'       => static fn( $source ) : bool => $source instanceof Axismundi_OP_Object_Likes,
				'collection_uri' => static fn( Axismundi_OP_Object_Likes $source ) : string => axismundi_op_object_likes_url( $source->get_object_uri() ),
				'transform'      => 'axismundi_op_object_likes_transform',
				'visible'        => 'axismundi_op_object_likes_visible',
				'priority'       => 5,
			)
		);
	}
	if ( function_exists( 'axismundi_act_get_effective_reactions' ) ) {
		axismundi_op_register_collection_transformer(
			'axismundi-object-emoji-reactions',
			array(
				'supports'       => static fn( $source ) : bool => $source instanceof Axismundi_OP_Object_Emoji_Reactions,
				'collection_uri' => static fn( Axismundi_OP_Object_Emoji_Reactions $source ) : string => axismundi_op_object_emoji_reactions_url( $source->get_object_uri() ),
				'transform'      => 'axismundi_op_object_emoji_reactions_transform',
				'visible'        => 'axismundi_op_object_emoji_reactions_visible',
				'priority'       => 5,
			)
		);
	}
	if ( function_exists( 'axismundi_act_get_announce_count' ) ) {
		axismundi_op_register_collection_transformer(
			'axismundi-object-shares',
			array(
				'supports'       => static fn( $source ) : bool => $source instanceof Axismundi_OP_Object_Shares,
				'collection_uri' => static fn( Axismundi_OP_Object_Shares $source ) : string => axismundi_op_object_shares_url( $source->get_object_uri() ),
				'transform'      => 'axismundi_op_object_shares_transform',
				'visible'        => static fn( Axismundi_OP_Object_Shares $source ) : bool => function_exists( 'axismundi_act_get_announce_count' ) && ! is_wp_error( axismundi_op_transform_object( $source->get_source() ) ),
				'priority'       => 5,
			)
		);
	}
	if ( function_exists( 'axismundi_act_get_effective_reactions' ) ) {
		register_rest_route(
			'axismundi/v1',
			'/objects/emoji-reactions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'axismundi_op_get_object_emoji_reactions',
				'permission_callback' => '__return_true',
				'args'                => array( 'object' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ) ),
			)
		);
	}
}
add_action( 'axismundi_op_register_transformers', 'axismundi_op_register_reaction_transformer' );

/** Register the public Object likes route. */
function axismundi_op_register_object_likes_route() : void {
	if ( function_exists( 'axismundi_act_get_like_count' ) ) {
		register_rest_route(
			'axismundi/v1',
			'/objects/likes',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'axismundi_op_get_object_likes',
				'permission_callback' => '__return_true',
				'args'                => array( 'object' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ) ),
			)
		);
	}
	if ( function_exists( 'axismundi_act_get_announce_count' ) ) {
		register_rest_route(
			'axismundi/v1',
			'/objects/shares',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'axismundi_op_get_object_shares',
				'permission_callback' => '__return_true',
				'args'                => array( 'object' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ) ),
			)
		);
	}
}
add_action( 'rest_api_init', 'axismundi_op_register_object_likes_route' );

/** Serve one public local Object likes collection. */
function axismundi_op_get_object_likes( WP_REST_Request $request ) {
	$uri    = trim( (string) $request['object'] );
	$source = axismundi_op_authoritative_source_from_object_uri( $uri );
	if ( null === $source ) {
		return new WP_Error( 'ax_op_likes_not_found', __( 'The Object likes collection was not found.', 'axismundi-object-projections' ), array( 'status' => 404 ) );
	}
	$collection = axismundi_op_transform_collection( new Axismundi_OP_Object_Likes( $uri, $source ) );
	if ( is_wp_error( $collection ) ) {
		return $collection;
	}
	$response = rest_ensure_response( $collection );
	$response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );
	$response->header( 'Cache-Control', 'public, max-age=60' );
	return $response;
}

/** Serve one public local Object's FEP-c0e0 emoji reaction collection. */
function axismundi_op_get_object_emoji_reactions( WP_REST_Request $request ) {
	$uri    = trim( (string) $request['object'] );
	$source = axismundi_op_authoritative_source_from_object_uri( $uri );
	if ( null === $source ) {
		return new WP_Error( 'ax_op_emoji_reactions_not_found', __( 'The Object emoji reaction collection was not found.', 'axismundi-object-projections' ), array( 'status' => 404 ) );
	}
	$collection = axismundi_op_transform_collection( new Axismundi_OP_Object_Emoji_Reactions( $uri, $source ) );
	if ( is_wp_error( $collection ) ) {
		return $collection;
	}
	$response = rest_ensure_response( $collection );
	$response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );
	$response->header( 'Cache-Control', 'public, max-age=60' );
	return $response;
}

/** Serve one public local Object shares collection. */
function axismundi_op_get_object_shares( WP_REST_Request $request ) {
	$uri    = trim( (string) $request['object'] );
	$source = axismundi_op_authoritative_source_from_object_uri( $uri );
	if ( null === $source ) {
		return new WP_Error( 'ax_op_shares_not_found', __( 'The Object shares collection was not found.', 'axismundi-object-projections' ), array( 'status' => 404 ) );
	}
	$collection = axismundi_op_transform_collection( new Axismundi_OP_Object_Shares( $uri, $source ) );
	if ( is_wp_error( $collection ) ) {
		return $collection;
	}
	$response = rest_ensure_response( $collection );
	$response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );
	$response->header( 'Cache-Control', 'public, max-age=60' );
	return $response;
}

/** Back-compat name for the remote cache's public-listing predicate. */
function axismundi_op_remote_object_is_announceable( array $row ) : bool {
	return axismundi_op_remote_object_is_publicly_listable( $row );
}

/** Supply the fail-closed Announce visibility decision owned by the object layer. */
function axismundi_op_can_announce_object( $allowed, Axismundi_Actor $actor, string $object_uri ) {
	$source = axismundi_op_local_source_from_object_uri( $object_uri );
	if ( null !== $source ) {
		return true;
	}
	$row = function_exists( 'axismundi_op_remote_object_get' ) ? axismundi_op_remote_object_get( $object_uri, false ) : null;
	return is_array( $row ) && axismundi_op_remote_object_is_announceable( $row )
		? true
		: $allowed;
}
add_filter( 'axismundi_act_can_announce_object', 'axismundi_op_can_announce_object', 10, 3 );

/** Resolve an announceable object's canonical URI and author without network access. */
function axismundi_op_resolve_announce_target( $target, string $object_uri ) {
	$source = axismundi_op_local_source_from_object_uri( $object_uri );
	if ( null !== $source ) {
		$object = axismundi_op_transform_object( $source );
		if ( is_array( $object ) && ! empty( $object['attributedTo'] ) ) {
			return array( 'object_uri' => $object_uri, 'recipient_uri' => axismundi_act_member_uri( $object['attributedTo'] ), 'source' => $source );
		}
	}
	$row = function_exists( 'axismundi_op_remote_object_get' ) ? axismundi_op_remote_object_get( $object_uri, false ) : null;
	if ( is_array( $row ) && axismundi_op_remote_object_is_announceable( $row ) ) {
		return array( 'object_uri' => $object_uri, 'recipient_uri' => (string) $row['attributed_to_uri'], 'source' => $row );
	}
	return $target;
}
add_filter( 'axismundi_act_resolve_announce_target', 'axismundi_op_resolve_announce_target', 10, 2 );
