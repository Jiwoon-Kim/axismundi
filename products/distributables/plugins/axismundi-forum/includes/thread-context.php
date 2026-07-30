<?php
/**
 * Per-topic thread context (FEP-7888, FEP-11dd).
 *
 * A Forum root post and its replies belong to one conversation, and that conversation needs a
 * name of its own. Naming the Group instead — which this did — is permitted by FEP-7888, which
 * lists a forum or channel as a legitimate `context`, but it collapses every thread in the
 * Forum into one: a reply could say which community it belonged to and never which discussion.
 *
 * So each Topic gets a `context` URI that dereferences to an `OrderedCollection` of the root
 * post followed by its replies. The Group remains `audience` — who it is addressed to and who
 * redistributes it — which is the question FEP-1b12 answers. Two different questions, two
 * different properties.
 *
 * The collection is derived, never stored. Its members come from the same thread graph that
 * already backs the replies collection, so a context and a replies collection can never
 * disagree about who is in a conversation. `attributedTo` names the Group because FEP-11dd
 * asks who owns a context, and the owner is the community the thread was admitted to — not the
 * author, who owns only their own post.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/**
 * The stable context URI for one Topic's thread.
 *
 * Derived from the root object URI rather than stored, which keeps it stable for exactly as
 * long as the root post's identity is stable and makes it impossible for the two to drift.
 *
 * @param WP_Post $topic Topic post.
 * @return string
 */
function axismundi_forum_topic_context_uri( WP_Post $topic ) : string {
	$object_uri = axismundi_forum_topic_object_uri( $topic );
	return '' === $object_uri
		? ''
		: add_query_arg( array( 'object' => $object_uri ), rest_url( 'axismundi/v1/forum/thread' ) );
}

/**
 * Build the thread collection for one Topic.
 *
 * @param WP_Post $topic Topic post.
 * @param int     $limit Bounded reply count.
 * @return array<string,mixed>
 */
function axismundi_forum_thread_collection( WP_Post $topic, int $limit = 50 ) : array {
	$entry = axismundi_forum_get_topic_entry( $topic->ID );
	$group = is_array( $entry ) && function_exists( 'axismundi_actors_get_by_identity' )
		? axismundi_actors_get_by_identity( (int) $entry['group_identity_id'] )
		: axismundi_forum_get_remote_topic_group( $topic );
	$object_uri = axismundi_forum_topic_object_uri( $topic );
	$items      = array( $object_uri );
	$truncated  = false;
	if ( function_exists( 'axismundi_op_get_public_reply_collection_page' ) ) {
		/*
		 * Direct replies only, for now. A conversation is the whole reply tree, so this is an
		 * under-report rather than a wrong report: every URI listed is genuinely in the thread,
		 * and deeper replies are reachable from their own parent's replies collection. Walking
		 * the tree here would mean an unbounded resolve per node on a public route, which is
		 * the shape of request a hostile thread is built to exploit; nesting comes with the
		 * paging contract, not before it.
		 */
		$page      = axismundi_op_get_public_reply_collection_page( $object_uri, 1, max( 1, min( 100, $limit ) ) );
		$truncated = ! empty( $page['truncated'] ) || ! empty( $page['has_next'] );
		foreach ( (array) ( $page['uris'] ?? array() ) as $reply_uri ) {
			if ( '' !== (string) $reply_uri && ! in_array( (string) $reply_uri, $items, true ) ) {
				$items[] = (string) $reply_uri;
			}
		}
	}
	$collection = array(
		'@context'     => 'https://www.w3.org/ns/activitystreams',
		'id'           => axismundi_forum_topic_context_uri( $topic ),
		'type'         => 'OrderedCollection',
		'name'         => get_the_title( $topic ),
		'orderedItems' => $items,
	);
	// totalItems is omitted rather than guessed once the bounded scan stops short: a count that
	// silently means "at least this many" is worse than no count.
	if ( ! $truncated ) {
		$collection['totalItems'] = count( $items );
	}
	if ( $group instanceof Axismundi_Actor ) {
		// FEP-11dd: the context has an owner, and it is the community the thread lives in.
		$collection['attributedTo'] = $group->get_uri();
		$collection['audience']     = $group->get_uri();
	}
	return $collection;
}

/** Register the read-only thread-context route. */
function axismundi_forum_register_thread_route() : void {
	register_rest_route(
		'axismundi/v1',
		'/forum/thread',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_forum_get_thread',
			'permission_callback' => '__return_true',
			'args'                => array(
				'object' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_forum_register_thread_route' );

/**
 * Serve one Topic's thread context.
 *
 * Only a visible Topic answers. A context that resolved for a locked-away or unpublished
 * Topic would disclose, by existing, that the thread exists.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_forum_get_thread( WP_REST_Request $request ) {
	$object_uri = (string) $request->get_param( 'object' );
	$topic      = axismundi_forum_resolve_topic_source( null, $object_uri );
	if ( ! $topic instanceof WP_Post || ! axismundi_forum_topic_article_visible( $topic ) ) {
		return new WP_Error( 'ax_forum_thread_not_found', __( 'No public Forum thread matches that object.', 'axismundi-forum' ), array( 'status' => 404 ) );
	}
	$response = rest_ensure_response( axismundi_forum_thread_collection( $topic ) );
	$response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );
	return $response;
}
