<?php
/**
 * The community-contributions surface of a Person profile.
 *
 * Forum already hides a Person's Group submissions from their personal timeline, because a Topic
 * posted to a community is the community's surface and not that person's chronology. Hiding
 * alone, though, loses the contribution history entirely: a reader could not find what someone
 * had written in a community from that person's profile at all.
 *
 * So the same two predicates that hide those entries from `activity` are read in the opposite
 * direction here, and become the contents of `?view=community`. One rule, two directions —
 * rather than a second definition of "community contribution" that can drift away from the first.
 *
 * This is a collection over the same Actor, not a second Actor: the identity URI and the outbox
 * are unchanged, exactly as Lemmy's `?view=Posts` is still one user.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/** The slices of the community surface. */
function axismundi_forum_profile_filters() : array {
	return array(
		'overview' => __( 'Overview', 'axismundi-forum' ),
		'topics'   => __( 'Topics', 'axismundi-forum' ),
		'replies'  => __( 'Replies', 'axismundi-forum' ),
	);
}

/**
 * Whether one ledger row is this Person's contribution to a community.
 *
 * @param Axismundi_Activity $activity Ledger row.
 * @param string             $filter   `overview`, `topics`, or `replies`.
 * @return bool
 */
function axismundi_forum_is_profile_contribution( Axismundi_Activity $activity, string $filter ) : bool {
	$is_topic = function_exists( 'axismundi_forum_is_direct_topic_submission_activity' ) && axismundi_forum_is_direct_topic_submission_activity( $activity );
	$is_reply = function_exists( 'axismundi_forum_is_direct_group_reply_activity' ) && axismundi_forum_is_direct_group_reply_activity( $activity );
	if ( 'topics' === $filter ) {
		return $is_topic;
	}
	if ( 'replies' === $filter ) {
		return $is_reply;
	}
	return $is_topic || $is_reply;
}

/**
 * Admit a community contribution on the community surface, and only there.
 *
 * The two existing filters run at priority 30 and hide these entries. This runs after them and
 * decides the question outright for this surface: on `community` a contribution is admitted and
 * everything else is refused, so the surface cannot quietly fill up with ordinary posts.
 *
 * @param bool               $visible  Whether the activity is publicly renderable.
 * @param Axismundi_Activity $activity Candidate ledger row.
 * @param string             $surface  Profile surface being rendered.
 * @return bool
 */
function axismundi_forum_profile_surface_visible( bool $visible, Axismundi_Activity $activity, string $surface = 'activity' ) : bool {
	if ( 0 !== strpos( $surface, 'community' ) ) {
		return $visible;
	}
	$filter = substr( $surface, strlen( 'community' ) );
	$filter = '' === $filter ? 'overview' : ltrim( $filter, ':' );
	/*
	 * The public-audience test still governs. A private submission stays private: this decides
	 * what *kind* of entry belongs here, never whether a reader is allowed to see it.
	 */
	return $visible && axismundi_forum_is_profile_contribution( $activity, $filter );
}
add_filter( 'axismundi_act_actor_feed_activity_visible', 'axismundi_forum_profile_surface_visible', 40, 3 );

/**
 * One page of a Person's community contributions.
 *
 * Selection, cursor paging, and card rendering all belong to Activities; this only states which
 * entries count. The filter travels inside the surface key because that is what the admission
 * hook receives — a separate channel for it could disagree with the page being built.
 *
 * @param Axismundi_Actor $actor  Person whose contributions are read.
 * @param int             $limit  Entries per page.
 * @param string          $cursor Continuation cursor.
 * @param string          $filter `overview`, `topics`, or `replies`.
 * @param bool            $inclusive Whether the cursor anchors an inclusive window.
 * @return array<string,mixed>
 */
function axismundi_forum_profile_surface_page( Axismundi_Actor $actor, int $limit, string $cursor, string $filter, bool $inclusive = false, bool $head_window = false ) : array {
	$filters = axismundi_forum_profile_filters();
	$filter  = isset( $filters[ $filter ] ) ? $filter : 'overview';
	// `all` keeps the Activities-side filter from dropping replies before this surface sees
	// them: the community surface decides its own membership, and a reply is most of what it
	// exists to show.
	return axismundi_act_actor_feed_page( $actor, $limit, $cursor, 'all', 'community:' . $filter, $inclusive, $head_window );
}

/**
 * How long one page of selected contributions may be reused.
 *
 * Short on purpose. The point is to absorb the repeated ledger scan a crawler or a refresh
 * causes, not to serve stale history: a contribution that appears a minute late in an archive
 * costs nothing, one that appears a day late looks broken.
 */
function axismundi_forum_profile_cache_ttl() : int {
	/** @param int $ttl Seconds. */
	return max( 0, (int) apply_filters( 'axismundi_forum_profile_cache_ttl', 60 ) );
}

/**
 * The cache generation for one Person's contributions.
 *
 * Bumping a number invalidates every page at once without deleting anything, which matters
 * because transients have no wildcard delete and the alternative -- remembering every key ever
 * written -- is a second bookkeeping problem.
 */
function axismundi_forum_profile_cache_version( Axismundi_Actor $actor ) : int {
	return (int) get_option( 'ax_forum_profile_gen_' . md5( $actor->get_uri() ), 1 );
}

/** Invalidate every cached contributions page for one Actor. */
function axismundi_forum_invalidate_profile_cache( string $actor_uri ) : void {
	if ( '' === $actor_uri ) {
		return;
	}
	$key = 'ax_forum_profile_gen_' . md5( $actor_uri );
	update_option( $key, (int) get_option( $key, 1 ) + 1, false );
}

/**
 * Bump the generation whenever this Actor's record changes.
 *
 * Keyed on the acting Actor rather than the Group: it is that person's archive that changed, and
 * keying on the Group would make a busy community invalidate every member's pages on every post.
 *
 * @param Axismundi_Activity $activity Recorded ledger row.
 * @return void
 */
function axismundi_forum_invalidate_profile_cache_for_activity( Axismundi_Activity $activity ) : void {
	if ( in_array( $activity->get_type(), array( 'Create', 'Update', 'Delete', 'Undo' ), true ) ) {
		axismundi_forum_invalidate_profile_cache( $activity->get_actor_uri() );
	}
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_forum_invalidate_profile_cache_for_activity' );

/**
 * One numbered page of a Person's community contributions.
 *
 * Numbered rather than cursor-paged, because this is an archive and not a feed: a reader browses
 * it, goes into a thread, comes back, and links to what they found. A cursor cannot express
 * "page 3", and the drift a cursor protects against -- new rows arriving at the head -- is
 * exactly what a contributions archive tolerates, the same way every forum's page 2 does.
 *
 * Only the selection is cached, never the rendered rows: what a reader may see, and any control
 * on a row, is theirs alone and must not be handed to the next visitor.
 *
 * @param Axismundi_Actor $actor    Person whose contributions are read.
 * @param string          $filter   `overview`, `topics`, or `replies`.
 * @param int             $page     1-based page number.
 * @param int             $per_page Rows per page.
 * @return array{uris:string[],has_more:bool,page:int}
 */
function axismundi_forum_profile_contribution_page( Axismundi_Actor $actor, string $filter, int $page, int $per_page ) : array {
	$filters  = axismundi_forum_profile_filters();
	$filter   = isset( $filters[ $filter ] ) ? $filter : 'overview';
	$page     = max( 1, $page );
	$per_page = max( 1, min( 100, $per_page ) );
	$key      = sprintf(
		'ax_forum_contrib_%s_%d_%s_%d_%d',
		substr( md5( $actor->get_uri() ), 0, 12 ),
		axismundi_forum_profile_cache_version( $actor ),
		$filter,
		$page,
		$per_page
	);
	$cached = get_transient( $key );
	if ( is_array( $cached ) && isset( $cached['uris'], $cached['has_more'] ) ) {
		return array( 'uris' => (array) $cached['uris'], 'has_more' => (bool) $cached['has_more'], 'page' => $page );
	}
	/*
	 * Walk the cursor pages the selection already knows how to produce and stop at the requested
	 * offset. The archive is addressed by page number; the ledger is still ordered by cursor, and
	 * this is where the two meet.
	 */
	$skip   = ( $page - 1 ) * $per_page;
	$cursor = '';
	$uris   = array();
	$more   = false;
	$guard  = 0;
	while ( $guard++ < 200 ) {
		$batch = axismundi_act_actor_feed_page( $actor, $per_page, $cursor, 'all', 'community:' . $filter );
		foreach ( (array) $batch['items'] as $item ) {
			if ( $skip > 0 ) {
				--$skip;
				continue;
			}
			if ( count( $uris ) >= $per_page ) {
				$more = true;
				break 2;
			}
			$uris[] = (string) ( $item['object_uri'] ?? '' );
		}
		if ( empty( $batch['has_more'] ) || '' === (string) $batch['next_cursor'] ) {
			break;
		}
		$cursor = (string) $batch['next_cursor'];
	}
	$result = array( 'uris' => array_values( array_filter( $uris ) ), 'has_more' => $more );
	$ttl    = axismundi_forum_profile_cache_ttl();
	if ( $ttl > 0 ) {
		set_transient( $key, $result, $ttl );
	}
	return array( 'uris' => $result['uris'], 'has_more' => $result['has_more'], 'page' => $page );
}

/**
 * Render the community contributions archive.
 *
 * @param Axismundi_Actor $actor    Person whose contributions are listed.
 * @param string          $filter   Active filter.
 * @param int             $per_page Rows per page.
 * @return string
 */
function axismundi_forum_render_profile_surface( Axismundi_Actor $actor, string $filter, int $per_page ) : string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read pagination.
	$page   = isset( $_GET['page'] ) ? max( 1, absint( wp_unslash( $_GET['page'] ) ) ) : 1;
	$result = axismundi_forum_profile_contribution_page( $actor, $filter, $page, $per_page );
	$rows   = array();
	foreach ( $result['uris'] as $uri ) {
		$preview = function_exists( 'axismundi_op_list_preview_by_uri' ) ? axismundi_op_list_preview_by_uri( $uri ) : null;
		if ( null === $preview ) {
			// The ledger outlives the object it recorded, so a row with nothing to show is
			// dropped rather than rendered as an empty line.
			continue;
		}
		$title = '' !== $preview['title'] ? $preview['title'] : __( 'Untitled', 'axismundi-forum' );
		$link  = '' !== $preview['url'] ? $preview['url'] : $uri;
		// A sensitive Object shows its warning, never a cut of the thing the warning is about.
		$body = $preview['sensitive']
			? '<p class="axismundi-forum-contrib__warning">' . esc_html( '' !== $preview['content_warning'] ? $preview['content_warning'] : __( 'Sensitive content', 'axismundi-forum' ) ) . '</p>'
			: ( '' !== $preview['excerpt'] ? '<p class="axismundi-forum-contrib__excerpt">' . esc_html( $preview['excerpt'] ) . '</p>' : '' );
		$rows[] = '<li class="axismundi-forum-contrib__item axismundi-forum-contrib__item--' . esc_attr( strtolower( $preview['type'] ) ) . '">'
			. '<a class="axismundi-forum-contrib__title" href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>'
			. $body
			. ( '' !== $preview['published'] ? '<p class="axismundi-forum-contrib__date"><time datetime="' . esc_attr( $preview['published'] ) . '">' . esc_html( mysql2date( get_option( 'date_format' ), $preview['published'] ) ) . '</time></p>' : '' )
			. '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above.
	}
	if ( empty( $rows ) && 1 === $page ) {
		return '<p class="axismundi-forum-contrib__empty">' . esc_html__( 'No community contributions yet.', 'axismundi-forum' ) . '</p>';
	}
	$surfaces = function_exists( 'axismundi_act_actor_profile_surfaces' ) ? axismundi_act_actor_profile_surfaces( $actor ) : array();
	$link_for = static function ( int $target ) use ( $surfaces, $filter ) : string {
		return function_exists( 'axismundi_act_actor_profile_url' )
			? axismundi_act_actor_profile_url( $surfaces, 'community', $filter, $target > 1 ? array( 'page' => $target ) : array() )
			: '';
	};
	$nav = array();
	if ( $page > 1 ) {
		$nav[] = '<a class="axismundi-forum-contrib__prev" href="' . esc_url( $link_for( $page - 1 ) ) . '" rel="prev">' . esc_html__( 'Newer', 'axismundi-forum' ) . '</a>';
	}
	$nav[] = '<span class="axismundi-forum-contrib__page">' . esc_html( sprintf( /* translators: %d: page number. */ __( 'Page %d', 'axismundi-forum' ), $page ) ) . '</span>';
	if ( ! empty( $result['has_more'] ) ) {
		$nav[] = '<a class="axismundi-forum-contrib__next" href="' . esc_url( $link_for( $page + 1 ) ) . '" rel="next">' . esc_html__( 'Older', 'axismundi-forum' ) . '</a>';
	}
	return '<ul class="axismundi-forum-contrib">' . implode( '', $rows ) . '</ul>'
		. ( count( $nav ) > 1 ? '<nav class="axismundi-forum-contrib__pagination" aria-label="' . esc_attr__( 'Contribution pages', 'axismundi-forum' ) . '">' . implode( '', $nav ) . '</nav>' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rows and controls are escaped above.
}

/**
 * Register the community surface on a Person profile.
 *
 * A Group profile is not given one: a Group's profile already *is* its community feed, so a
 * "community contributions" tab there would point at itself.
 *
 * @param array<string,array<string,mixed>> $surfaces Registered surfaces.
 * @param Axismundi_Actor                   $actor    Actor whose profile is being rendered.
 * @return array<string,array<string,mixed>>
 */
function axismundi_forum_register_profile_surface( array $surfaces, Axismundi_Actor $actor ) : array {
	if ( 'Group' === $actor->get_type() || ! $actor->is_local() ) {
		return $surfaces;
	}
	$surfaces['community'] = array(
		'label'          => __( 'Community', 'axismundi-forum' ),
		'heading'        => __( 'Community contributions', 'axismundi-forum' ),
		'filters'        => axismundi_forum_profile_filters(),
		'default_filter' => 'overview',
		// Ledger-selected, so it must say which side it takes. See axismundi_act_group_context_admits().
		'group_context'  => 'in',
		'page'           => 'axismundi_forum_profile_surface_page',
		// An archive rather than a feed, so it renders its own list and numbers its own pages.
		'render'         => 'axismundi_forum_render_profile_surface',
	);
	return $surfaces;
}
add_filter( 'axismundi_act_actor_profile_surfaces', 'axismundi_forum_register_profile_surface', 10, 2 );
