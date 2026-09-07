<?php
/**
 * Per-topic thread context (FEP-7888, FEP-11dd, FEP-f228, FEP-1985, FEP-9f9f).
 *
 * A Forum root post and its replies belong to one conversation, and that conversation needs a
 * name of its own. Naming the Group instead — which this once did — is permitted by FEP-7888,
 * which lists a forum or channel as a legitimate `context`, but it collapses every thread in the
 * Forum into one: a reply could say which community it belonged to and never which discussion.
 *
 * So each Topic gets a `context` URI that dereferences to an `OrderedCollection` of the root post
 * followed by its replies. The Group remains `audience` — who it is addressed to and who
 * redistributes it — which is the question FEP-1b12 answers. Two different questions, two
 * different properties.
 *
 * Four contracts decide the shape here, and each one replaced a shortcut:
 *
 * - FEP-f228 wants the whole conversation, so a peer can backfill a thread from one collection
 *   instead of chasing `inReplyTo` upward one fetch at a time. This listed direct replies only
 *   and said so. It no longer does: an edge records the root it descends from, so the whole tree
 *   is one indexed range scan and the old objection — an unbounded resolve per node on a public
 *   route — does not apply to a query that never traverses.
 * - FEP-f228 also allows a conversation owner to include only the replies it accepted, which is
 *   exactly what a community is for. Membership is therefore the Group's admission ledger, not
 *   the reply's own claim: `inReplyTo` decides the shape of the tree and nothing else, because
 *   anyone may point at anything.
 * - FEP-9f9f asks that a collection identify itself without query parameters, and this named
 *   itself with a percent-encoded URI inside one. The id is now a path, keyed by the same stable
 *   post identifier the Topic's own Object URI already uses, leaving the query string to paging
 *   where it belongs.
 * - FEP-1985 lets a collection say which way it runs. A conversation is read oldest first, and
 *   saying so beats letting each peer guess.
 *
 * The collection is derived, never stored. Its members come from the same thread graph that backs
 * the replies collection, so a context and a replies collection can never disagree about who is
 * in a conversation. `attributedTo` names the Group because FEP-11dd asks who owns a context, and
 * the owner is the community the thread was admitted to — not the author, who owns only their own
 * post. When that Group is remote we still host the collection: FEP-11dd asks who owns a context,
 * not who serves it, and a remote community publishes no such collection for us to point at. What
 * we may not do there is claim completeness, because our copy is only what reached us.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/** Members listed per collection page. */
const AXISMUNDI_FORUM_THREAD_PAGE_SIZE = 20;

/** Default number of thread edges one context page may examine. */
const AXISMUNDI_FORUM_THREAD_SCAN_LIMIT = 300;

/**
 * The stable context URI for one Topic's thread.
 *
 * Path-based, per FEP-9f9f, and keyed by the same post identifier the Topic's Object URI is built
 * from — so the two stay stable together or not at all, and a reader can see they belong to each
 * other.
 *
 * @param WP_Post $topic Topic post.
 * @return string
 */
function axismundi_forum_topic_context_uri( WP_Post $topic ) : string {
	return '' === axismundi_forum_topic_object_uri( $topic )
		? ''
		: rest_url( 'axismundi/v1/forum/thread/' . $topic->ID );
}

/**
 * The URI of one page of a Topic's thread collection.
 *
 * Paging state lives in the query string precisely so the collection's own id does not have to
 * carry any, and it is a cursor rather than a page number because pages here are not fixed
 * windows into a stored list — see the page builder for why.
 *
 * @param WP_Post $topic    Topic post.
 * @param int     $after_id Edge cursor; 0 is the first page.
 * @return string
 */
function axismundi_forum_thread_page_uri( WP_Post $topic, int $after_id ) : string {
	$context = axismundi_forum_topic_context_uri( $topic );
	return '' === $context ? '' : add_query_arg( array( 'after' => max( 0, $after_id ) ), $context );
}

/**
 * The community a Topic belongs to, whether it was admitted locally or addressed to a remote one.
 *
 * @param WP_Post $topic Topic post.
 * @return Axismundi_Actor|null
 */
function axismundi_forum_topic_context_group( WP_Post $topic ) : ?Axismundi_Actor {
	$entry = axismundi_forum_get_topic_entry( $topic->ID );
	if ( is_array( $entry ) && function_exists( 'axismundi_actors_get_by_identity' ) ) {
		$group = axismundi_actors_get_by_identity( (int) $entry['group_identity_id'] );
		if ( $group instanceof Axismundi_Actor ) {
			return $group;
		}
	}
	return axismundi_forum_get_remote_topic_group( $topic );
}

/**
 * Whether one reply was submitted to a community.
 *
 * The reply's own `audience` is not evidence — any Actor can name any Group in an object it
 * authored. What counts is the immutable Create or Update that introduced it: it must name this
 * Group and have been addressed to it.
 *
 * This is where a reply asks to join a conversation. It is not where it is let in.
 *
 * @param string          $uri   Candidate reply Object URI.
 * @param Axismundi_Actor $group Community the conversation belongs to.
 * @return bool
 */
function axismundi_forum_thread_member_submitted( string $uri, Axismundi_Actor $group ) : bool {
	if ( ! function_exists( 'axismundi_forum_submitted_object_community_group' ) ) {
		return false;
	}
	$submitted = axismundi_forum_submitted_object_community_group( $uri, $group->is_local() );
	return $submitted instanceof Axismundi_Actor && hash_equals( $group->get_uri(), $submitted->get_uri() );
}

/**
 * Whether a community accepted one reply into its conversation.
 *
 * Submitting is not being accepted, and the difference is the whole reason a community exists.
 * A reply can be addressed to a Group by someone the Group will not publish, and a reply the
 * Group once published can have that decision withdrawn — `Undo` of an `Announce` is exactly how
 * a moderator takes a post back out, and the `Create` behind it is immutable and stays. Reading
 * only the submission would keep publishing what a moderator had already removed.
 *
 * So acceptance is the Group's own `Announce`, and it is read as still effective rather than as
 * ever having existed. FEP-1b12 has that Announce wrap the submission Activity rather than the
 * Object, so it is found under the Activity's URI.
 *
 * The same question is asked of a remote community, against the same local ledger, which means a
 * remote thread reports what actually reached us and never more. That is a real limit and the
 * honest one: we cannot vouch for an acceptance we never saw.
 *
 * @param string          $uri   Candidate reply Object URI.
 * @param Axismundi_Actor $group Community that owns the conversation.
 * @return bool
 */
function axismundi_forum_thread_member_accepted( string $uri, Axismundi_Actor $group ) : bool {
	if ( ! axismundi_forum_thread_member_submitted( $uri, $group ) || ! function_exists( 'axismundi_act_get_by_object' ) ) {
		return false;
	}
	foreach ( axismundi_act_get_by_object( $uri, 50 ) as $submission ) {
		if ( ! $submission instanceof Axismundi_Activity || ! $submission->is_effective() || ! in_array( $submission->get_type(), array( 'Create', 'Update' ), true ) ) {
			continue;
		}
		foreach ( axismundi_act_get_by_object( $submission->get_uri(), 20 ) as $announce ) {
			if ( $announce instanceof Axismundi_Activity && 'Announce' === $announce->get_type() && $announce->is_effective()
				&& hash_equals( $group->get_uri(), $announce->get_actor_uri() ) ) {
				return true;
			}
		}
	}
	return false;
}

/** Bump the generation every derived thread collection is cached under. */
function axismundi_forum_thread_cache_generation() : int {
	return (int) get_option( 'ax_forum_thread_generation', 1 );
}

/**
 * Invalidate every cached thread selection when the ledger they are derived from moves.
 *
 * One counter for all threads rather than one per thread: a reply changes the conversation it
 * joined and, through admission, potentially the one it was refused by, and paying for a stale
 * page is worse than paying to rebuild a bounded list.
 *
 * @param Axismundi_Activity $activity Recorded activity.
 */
function axismundi_forum_invalidate_thread_cache( Axismundi_Activity $activity ) : void {
	if ( in_array( $activity->get_type(), array( 'Create', 'Update', 'Delete', 'Announce', 'Undo' ), true ) ) {
		update_option( 'ax_forum_thread_generation', axismundi_forum_thread_cache_generation() + 1, false );
	}
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_forum_invalidate_thread_cache', 40 );

/**
 * How many thread edges one request may examine.
 *
 * Each candidate costs an acceptance proof and a source resolve, so this is what keeps a
 * conversation's length from deciding how much work a public request may demand. It is a bound on
 * one request and never on the conversation: whatever the scan did not reach stays reachable
 * through the cursor the page hands back.
 *
 * @return int
 */
function axismundi_forum_thread_scan_limit() : int {
	/**
	 * Filter how many thread edges one context page may examine.
	 *
	 * @param int $limit Edge rows per request.
	 */
	return max( 1, (int) apply_filters( 'axismundi_forum_thread_scan_limit', AXISMUNDI_FORUM_THREAD_SCAN_LIMIT ) );
}

/**
 * One page of a Topic's conversation: accepted replies, in the order they became known.
 *
 * Paging is a cursor over thread edges rather than an offset into a filtered list, because most
 * of what the scan reads it then discards — replies the community did not accept, replies the
 * reader may not see. With a page number, a run of discarded rows longer than the scan bound
 * would end the collection early and hide everything after it; a reader would be told the
 * conversation was over. The cursor names the last edge actually examined, so the next request
 * resumes there whether or not this page managed to fill.
 *
 * That is why a page can come back short and still offer `next`. A short page means the scan
 * ran out of budget, not that the conversation ran out of replies.
 *
 * Only the selection is cached, never rendered output, so a cached page cannot outlive a change
 * in who may see the objects in it.
 *
 * @param WP_Post $topic    Topic post.
 * @param int     $after_id Resume strictly after this edge id; 0 starts at the root.
 * @return array{uris:string[],next:int}
 */
function axismundi_forum_thread_page_members( WP_Post $topic, int $after_id = 0 ) : array {
	$root = axismundi_forum_topic_object_uri( $topic );
	if ( '' === $root ) {
		return array( 'uris' => array(), 'next' => 0 );
	}
	$after_id = max( 0, $after_id );
	$key      = 'ax_forum_thread_' . axismundi_forum_thread_cache_generation() . '_' . $topic->ID . '_' . $after_id;
	$cached   = get_transient( $key );
	if ( is_array( $cached ) && isset( $cached['uris'], $cached['next'] ) ) {
		return array( 'uris' => (array) $cached['uris'], 'next' => (int) $cached['next'] );
	}

	/*
	 * FEP-f228 requires the top-level post to be a member, and it is the one member that needs no
	 * acceptance proof: a Topic is in its own conversation by being what the conversation is about.
	 * It leads the first page only, so resuming from a cursor never repeats it.
	 */
	$uris      = 0 === $after_id ? array( $root ) : array();
	$group     = axismundi_forum_topic_context_group( $topic );
	$limit     = axismundi_forum_thread_scan_limit();
	$cursor    = $after_id;
	$examined  = 0;
	$exhausted = false;
	$filled    = false;
	while ( $group instanceof Axismundi_Actor && $examined < $limit && ! $filled ) {
		$want = min( 100, $limit - $examined );
		$rows = axismundi_op_get_thread_descendant_rows( $root, $want, $cursor );
		if ( empty( $rows ) ) {
			$exhausted = true;
			break;
		}
		foreach ( $rows as $row ) {
			$cursor = (int) $row['id'];
			++$examined;
			$uri = (string) $row['uri'];
			if ( in_array( $uri, $uris, true ) || ! axismundi_forum_thread_member_accepted( $uri, $group ) ) {
				continue;
			}
			$source = function_exists( 'axismundi_op_resolve_source_by_uri' ) ? axismundi_op_resolve_source_by_uri( $uri ) : null;
			if ( null === $source || ! axismundi_op_reply_collection_child_visible( $source ) ) {
				continue;
			}
			$uris[] = $uri;
			if ( count( $uris ) >= AXISMUNDI_FORUM_THREAD_PAGE_SIZE ) {
				$filled = true;
				break;
			}
		}
		if ( ! $filled && count( $rows ) < $want ) {
			$exhausted = true;
			break;
		}
	}
	// The conversation ends only when a scan reached its actual end. Running out of budget is not
	// an ending, and saying so would strand every reply the scan never got to.
	$page = array( 'uris' => $uris, 'next' => $exhausted ? 0 : $cursor );
	set_transient( $key, $page, HOUR_IN_SECONDS );
	return $page;
}

/**
 * The head of one Topic's thread collection.
 *
 * Neither `totalItems` nor `last` appears here, and both are omitted for the same reason: knowing
 * either one means scanning the whole conversation and proving acceptance for every reply in it,
 * which is the work the cursor exists to avoid. A total that silently meant "at least this many",
 * or a `last` that pointed at the end of one bounded scan, would each be worse than saying
 * nothing — a reader would stop early and believe it had everything.
 *
 * @param WP_Post $topic Topic post.
 * @return array<string,mixed>
 */
function axismundi_forum_thread_collection( WP_Post $topic ) : array {
	$group      = axismundi_forum_topic_context_group( $topic );
	$collection = array(
		'@context'  => array( 'https://www.w3.org/ns/activitystreams', 'https://w3id.org/fep/1985' ),
		'id'        => axismundi_forum_topic_context_uri( $topic ),
		'type'      => 'OrderedCollection',
		'name'      => get_the_title( $topic ),
		/*
		 * FEP-1985. Forward, and chronological by when each reply became known here rather than by
		 * when its author published it: the order is the edge index, and a reply that reaches this
		 * site late joins the end even if it was written early. For backfill — what this collection
		 * is for — that is sufficient and stable, because a reader wants every member, not a
		 * particular sequence. A consumer that needs authored order must sort by each object's own
		 * `published`, and this deliberately does not promise to have done that for it.
		 */
		'orderType' => 'ForwardChronological',
		'first'     => axismundi_forum_thread_page_uri( $topic, 0 ),
	);
	if ( $group instanceof Axismundi_Actor ) {
		// FEP-11dd: the context has an owner, and it is the community the thread lives in.
		$collection['attributedTo'] = $group->get_uri();
		$collection['audience']     = $group->get_uri();
	}
	return $collection;
}

/**
 * One page of a Topic's thread collection.
 *
 * @param WP_Post $topic    Topic post.
 * @param int     $after_id Resume strictly after this edge id; 0 starts at the root.
 * @return array<string,mixed>
 */
function axismundi_forum_thread_collection_page( WP_Post $topic, int $after_id = 0 ) : array {
	$page       = axismundi_forum_thread_page_members( $topic, $after_id );
	$collection = array(
		'@context'     => array( 'https://www.w3.org/ns/activitystreams', 'https://w3id.org/fep/1985' ),
		'id'           => axismundi_forum_thread_page_uri( $topic, $after_id ),
		'type'         => 'OrderedCollectionPage',
		'orderType'    => 'ForwardChronological',
		'partOf'       => axismundi_forum_topic_context_uri( $topic ),
		'orderedItems' => array_values( $page['uris'] ),
	);
	if ( $page['next'] > 0 ) {
		$collection['next'] = axismundi_forum_thread_page_uri( $topic, $page['next'] );
	}
	return $collection;
}

/** Register the read-only thread-context route. */
function axismundi_forum_register_thread_route() : void {
	register_rest_route(
		'axismundi/v1',
		'/forum/thread/(?P<topic>\d+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_forum_get_thread',
			'permission_callback' => '__return_true',
			'args'                => array(
				'topic' => array( 'required' => true, 'type' => 'integer' ),
				'after' => array( 'required' => false, 'type' => 'integer', 'minimum' => 0 ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_forum_register_thread_route' );

/**
 * Serve one Topic's thread context, or one page of it.
 *
 * Only a publicly visible Topic answers, and that is the whole of this route's scope. A context
 * that resolved for a locked-away or unpublished Topic would disclose, by existing, that the
 * thread exists, so the check fails closed and stays closed even for a reader who is signed in
 * and entitled: a members-only community's thread is absent here for everyone.
 *
 * That is a deliberate limit rather than an oversight, and it is narrower than the HTML surface,
 * where an author, an accepted member, or a moderator can read a members-only Topic. Serving the
 * same thread as AS2 to a signed-in member would need a viewer-aware gate and a cache partitioned
 * per reader, and doing it over federation would need more still — FEP-8c13 asks for
 * actor-bound fetches and forwarding proofs before anyone may claim a private thread works
 * between servers. Until those exist, a local authenticated read and a federated private thread
 * are two different problems, and claiming the second by loosening this check would be the
 * dishonest way to appear to have solved it.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_forum_get_thread( WP_REST_Request $request ) {
	$topic = get_post( (int) $request->get_param( 'topic' ) );
	if ( ! $topic instanceof WP_Post || ! axismundi_forum_topic_article_supports( $topic ) || ! axismundi_forum_topic_article_visible( $topic ) ) {
		return new WP_Error( 'ax_forum_thread_not_found', __( 'No public Forum thread matches that object.', 'axismundi-forum' ), array( 'status' => 404 ) );
	}
	$after    = $request->get_param( 'after' );
	$body     = null === $after
		? axismundi_forum_thread_collection( $topic )
		: axismundi_forum_thread_collection_page( $topic, (int) $after );
	$response = rest_ensure_response( $body );
	$response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );
	return $response;
}
