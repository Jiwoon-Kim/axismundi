<?php
/**
 * Public ActivityStreams replies collections derived from the URI thread graph.
 *
 * A replies collection belongs to the authoritative local parent Object, not to
 * an Activity ledger and not to a cached remote Object.  The edge index supplies
 * direct children from either store; collection pages expose only their canonical
 * IDs, so serving a local collection never republishes a remote cache snapshot.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Read-only source for an authoritative local Object's replies collection. */
final class Axismundi_OP_Object_Replies {
	/** @param mixed $source Local Object source. */
	public function __construct( private string $object_uri, private $source, private int $page = 0 ) {}
	public function get_object_uri() : string { return $this->object_uri; }
	/** @return mixed */
	public function get_source() { return $this->source; }
	public function get_page() : int { return $this->page; }
}

/** Stable root representation URI for one Object's replies collection. */
function axismundi_op_object_replies_url( string $object_uri, int $page = 0 ) : string {
	$args = array( 'object' => $object_uri );
	if ( $page > 0 ) {
		$args['page'] = $page;
	}
	return add_query_arg( $args, rest_url( 'axismundi/v1/objects/replies' ) );
}

/** Attach a replies collection only to an active locally authoritative Object. */
function axismundi_op_add_replies_property( array $object ) : array {
	if ( ! empty( $object['id'] ) && 'Tombstone' !== (string) ( $object['type'] ?? '' ) ) {
		$object['replies'] = axismundi_op_object_replies_url( (string) $object['id'] );
	}
	return $object;
}
add_filter( 'axismundi_op_post_article', 'axismundi_op_add_replies_property', 20 );

/**
 * Resolve an exact locally authoritative source from its canonical Object URI.
 *
 * Products with non-WordPress identities (such as Note) participate through the
 * URI resolver. Core posts retain the established URL-to-post fallback. A remote
 * cache wrapper is never an authority, even when it has the same URI.
 *
 * @return mixed|null
 */
function axismundi_op_authoritative_source_from_object_uri( string $object_uri ) {
	$uri = axismundi_op_relation_uri( $object_uri );
	if ( '' === $uri ) {
		return null;
	}
	$source = function_exists( 'axismundi_op_resolve_source_by_uri' ) ? axismundi_op_resolve_source_by_uri( $uri ) : null;
	if ( null !== $source && ! $source instanceof Axismundi_Op_Remote_Source ) {
		$transformer = axismundi_op_resolve_object_transformer( $source );
		try {
			if ( null !== $transformer && hash_equals( $uri, (string) call_user_func( $transformer['uri'], $source ) ) ) {
				return $source;
			}
		} catch ( \Throwable $error ) {
			return null;
		}
	}
	$source = function_exists( 'axismundi_op_local_source_from_object_uri' ) ? axismundi_op_local_source_from_object_uri( $uri ) : null;
	return $source instanceof Axismundi_Op_Remote_Source ? null : $source;
}

/** Whether a child may be named in an anonymous replies collection. */
function axismundi_op_reply_collection_child_visible( $source ) : bool {
	if ( $source instanceof Axismundi_Op_Remote_Source ) {
		return function_exists( 'axismundi_op_cached_object_publicly_viewable' )
			&& axismundi_op_cached_object_publicly_viewable( $source->get_row() );
	}
	return axismundi_op_source_publicly_visible( $source );
}

/**
 * One page of publicly nameable direct replies, oldest first.
 *
 * The page offset is in accepted collection members, not raw edges. That matters
 * when poll votes, private replies, stale cache entries, or unresolved parents are
 * interleaved with text replies. We deliberately omit totalItems: with a bounded
 * hostile-thread scan, claiming a partial total would be false.
 *
 * @return array{uris:string[],has_next:bool,truncated:bool}
 */
function axismundi_op_get_public_reply_collection_page( string $parent_uri, int $page = 1, int $limit = 50 ) : array {
	$page       = max( 1, $page );
	$limit      = max( 1, min( 100, $limit ) );
	$wanted     = ( $page - 1 ) * $limit;
	$accepted   = 0;
	$offset     = 0;
	$batch_size = 100;
	$scan_limit = 1000;
	$uris       = array();
	while ( $offset < $scan_limit && count( $uris ) <= $limit ) {
		$batch = axismundi_op_get_thread_reply_uris( $parent_uri, min( $batch_size, $scan_limit - $offset ), $offset );
		if ( empty( $batch ) ) {
			break;
		}
		foreach ( $batch as $child_uri ) {
			if ( ! apply_filters( 'axismundi_op_thread_include_reply', true, $child_uri, $parent_uri ) ) {
				continue;
			}
			$source = axismundi_op_resolve_source_by_uri( $child_uri );
			if ( null === $source || ! axismundi_op_reply_collection_child_visible( $source ) ) {
				continue;
			}
			if ( $accepted++ < $wanted ) {
				continue;
			}
			$uris[] = $child_uri;
			if ( count( $uris ) > $limit ) {
				break 2;
			}
		}
		$offset += count( $batch );
		if ( count( $batch ) < $batch_size ) {
			break;
		}
	}
	$has_next = count( $uris ) > $limit || $offset >= $scan_limit;
	return array(
		'uris'      => array_slice( $uris, 0, $limit ),
		'has_next'  => $has_next,
		'truncated' => $offset >= $scan_limit,
	);
}

/**
 * Count publicly nameable direct replies for a human Object view.
 *
 * This intentionally remains separate from the ActivityStreams collection,
 * whose root omits totalItems rather than claim a partial hostile-thread scan.
 * The UI may display a `+` suffix when the bounded scan is exhausted.
 *
 * @return array{count:int,truncated:bool}
 */
function axismundi_op_get_public_reply_collection_count( string $parent_uri, int $scan_limit = 1000 ) : array {
	$scan_limit = max( 1, min( 1000, $scan_limit ) );
	$offset     = 0;
	$count      = 0;
	$batch_size = 100;
	while ( $offset < $scan_limit ) {
		$batch = axismundi_op_get_thread_reply_uris( $parent_uri, min( $batch_size, $scan_limit - $offset ), $offset );
		if ( empty( $batch ) ) {
			return array( 'count' => $count, 'truncated' => false );
		}
		foreach ( $batch as $child_uri ) {
			if ( ! apply_filters( 'axismundi_op_thread_include_reply', true, $child_uri, $parent_uri ) ) {
				continue;
			}
			$source = axismundi_op_resolve_source_by_uri( $child_uri );
			if ( null !== $source && axismundi_op_reply_collection_child_visible( $source ) ) {
				++$count;
			}
		}
		$offset += count( $batch );
		if ( count( $batch ) < $batch_size ) {
			return array( 'count' => $count, 'truncated' => false );
		}
	}
	return array( 'count' => $count, 'truncated' => true );
}

/** Public visibility gate for a replies collection's local parent. */
function axismundi_op_object_replies_visible( Axismundi_OP_Object_Replies $source ) : bool {
	$object = axismundi_op_transform_object( $source->get_source() );
	return is_array( $object ) && 'Tombstone' !== (string) ( $object['type'] ?? '' );
}

/** Project a root OrderedCollection or one OrderedCollectionPage. */
function axismundi_op_object_replies_transform( Axismundi_OP_Object_Replies $source ) : array {
	$object = axismundi_op_transform_object( $source->get_source() );
	if ( is_wp_error( $object ) ) {
		return $object;
	}
	$root = axismundi_op_object_replies_url( $source->get_object_uri() );
	if ( 0 === $source->get_page() ) {
		return array(
			'id'           => $root,
			'type'         => 'OrderedCollection',
			'attributedTo' => (string) $object['attributedTo'],
			'url'          => axismundi_op_object_html_url( $object ),
			'first'        => axismundi_op_object_replies_url( $source->get_object_uri(), 1 ),
		);
	}
	$page    = axismundi_op_get_public_reply_collection_page( $source->get_object_uri(), $source->get_page() );
	$payload = array(
		'id'           => axismundi_op_object_replies_url( $source->get_object_uri(), $source->get_page() ),
		'type'         => 'OrderedCollectionPage',
		'attributedTo' => (string) $object['attributedTo'],
		'url'          => axismundi_op_object_html_url( $object ),
		'partOf'       => $root,
		'orderedItems' => $page['uris'],
	);
	if ( $page['has_next'] ) {
		$payload['next'] = axismundi_op_object_replies_url( $source->get_object_uri(), $source->get_page() + 1 );
	}
	return $payload;
}

/** Register the replies collection transformer. */
function axismundi_op_register_replies_collection_transformer() : void {
	axismundi_op_register_collection_transformer(
		'axismundi-object-replies',
		array(
			'supports'       => static fn( $source ) : bool => $source instanceof Axismundi_OP_Object_Replies,
			'collection_uri' => static fn( Axismundi_OP_Object_Replies $source ) : string => axismundi_op_object_replies_url( $source->get_object_uri(), $source->get_page() ),
			'transform'      => 'axismundi_op_object_replies_transform',
			'visible'        => 'axismundi_op_object_replies_visible',
			'priority'       => 5,
		)
	);
}
add_action( 'axismundi_op_register_transformers', 'axismundi_op_register_replies_collection_transformer' );

/** Register the public replies collection endpoint. */
function axismundi_op_register_object_replies_route() : void {
	register_rest_route(
		'axismundi/v1',
		'/objects/replies',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_op_get_object_replies',
			'permission_callback' => '__return_true',
			'args'                => array(
				'object' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ),
				'page'   => array( 'required' => false, 'type' => 'integer', 'minimum' => 1 ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_op_register_object_replies_route' );

/** Serve one public replies collection or page for a local authoritative Object. */
function axismundi_op_get_object_replies( WP_REST_Request $request ) {
	$uri    = trim( (string) $request['object'] );
	$source = axismundi_op_authoritative_source_from_object_uri( $uri );
	if ( null === $source ) {
		return new WP_Error( 'ax_op_replies_not_found', __( 'The Object replies collection was not found.', 'axismundi-object-projections' ), array( 'status' => 404 ) );
	}
	$page       = max( 0, absint( $request['page'] ) );
	$collection = axismundi_op_transform_collection( new Axismundi_OP_Object_Replies( $uri, $source, $page ) );
	if ( is_wp_error( $collection ) ) {
		if ( 'ax_op_not_public' === $collection->get_error_code() ) {
			return new WP_Error( 'ax_op_replies_not_found', __( 'The Object replies collection was not found.', 'axismundi-object-projections' ), array( 'status' => 404 ) );
		}
		return $collection;
	}
	$response = rest_ensure_response( $collection );
	$response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );
	$response->header( 'Cache-Control', 'public, max-age=60' );
	return $response;
}
