<?php
/**
 * The community archive on a Group Actor's profile.
 *
 * A community has two collections, not one, and every forum in the fediverse already presents
 * them that way: what was posted here, and what was said in reply here. Lemmy addresses them as
 * `dataType=Post` and `dataType=Comment` on the same community URL, and a reader arriving from
 * there expects the same shape.
 *
 * This is not the Person `community` surface wearing different labels. That one asks "which
 * communities has this person contributed to"; this one asks "what has this community accepted".
 * The ledger predicate and the card renderer are shared, the question is not, and the labels
 * follow the question rather than being forced into agreement.
 *
 * `Comments` is the surface where the acceptance rule earns its keep. Listing every Note that
 * names this Group as its `audience` would republish replies the community never accepted and
 * replies a moderator has already withdrawn, because an author's `Create` is immutable and
 * survives both. So membership here is the Group's own still-effective `Announce`, exactly as it
 * is in the thread context collection — one rule, two surfaces, no way for them to disagree.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/** Items listed per archive page. */
const AXISMUNDI_FORUM_ARCHIVE_PAGE_SIZE = 20;

/**
 * Announce rows read before the comment archive stops looking.
 *
 * An archive is a reading surface rather than a synchronisation surface, so it is allowed to end:
 * a reader paging back through a community is not trying to mirror it. The thread context
 * collection is where completeness matters, and that one pages by cursor with no ceiling.
 */
const AXISMUNDI_FORUM_ARCHIVE_SCAN_LIMIT = 500;

/**
 * The collections a community archive offers.
 *
 * @return array<string,string>
 */
function axismundi_forum_group_archive_filters() : array {
	return array(
		'posts'    => __( 'Posts', 'axismundi-forum' ),
		'comments' => __( 'Comments', 'axismundi-forum' ),
	);
}

/**
 * Which collection the reader asked for.
 *
 * @return string
 */
function axismundi_forum_group_archive_filter() : string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read navigation.
	$requested = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : '';
	return array_key_exists( $requested, axismundi_forum_group_archive_filters() ) ? $requested : 'posts';
}

/**
 * Which page the reader asked for.
 *
 * `topic_page` is still read because links to it exist; `page` is what this surface says now, so
 * both collections are addressed the same way rather than one keeping a name that only ever made
 * sense when Topics were the only thing here.
 *
 * @return int
 */
function axismundi_forum_group_archive_page_number() : int {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read pagination.
	$page = isset( $_GET['page'] ) ? absint( wp_unslash( $_GET['page'] ) ) : 0;
	if ( $page < 1 ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- legacy public read pagination.
		$page = isset( $_GET['topic_page'] ) ? absint( wp_unslash( $_GET['topic_page'] ) ) : 0;
	}
	return max( 1, $page );
}

/**
 * Every reply this community has accepted, newest first.
 *
 * Read from the Group's own `Announce` rows rather than from the replies themselves. An Announce
 * is the community saying yes, it stops standing the moment it is undone, and there is exactly
 * one place to look for it — whereas a reply's own `audience` is a claim by its author and proves
 * only what the author wanted.
 *
 * A Note announced more than once — a `Create` and later an `Update` — is one comment, so the
 * newest Announce wins and the rest are dropped. Only the selection is cached, never rendered
 * output, so a cached archive cannot outlive a change in who may see the objects in it.
 *
 * @param Axismundi_Actor $group Community Group.
 * @return array{uris:string[],truncated:bool}
 */
function axismundi_forum_group_comment_uris( Axismundi_Actor $group ) : array {
	$key    = 'ax_forum_group_comments_' . axismundi_forum_thread_cache_generation() . '_' . $group->get_identity_id();
	$cached = get_transient( $key );
	if ( is_array( $cached ) && isset( $cached['uris'], $cached['truncated'] ) ) {
		return array( 'uris' => (array) $cached['uris'], 'truncated' => (bool) $cached['truncated'] );
	}
	$uris   = array();
	$seen   = array();
	$offset = 0;
	if ( function_exists( 'axismundi_act_get_effective_by_actor_and_type' ) ) {
		while ( $offset < AXISMUNDI_FORUM_ARCHIVE_SCAN_LIMIT ) {
			$batch = axismundi_act_get_effective_by_actor_and_type( $group->get_uri(), array( 'Announce' ), min( 100, AXISMUNDI_FORUM_ARCHIVE_SCAN_LIMIT - $offset ), $offset );
			if ( empty( $batch ) ) {
				break;
			}
			$offset += count( $batch );
			foreach ( $batch as $announce ) {
				$uri = axismundi_forum_announced_reply_uri( $announce );
				if ( '' === $uri || isset( $seen[ $uri ] ) ) {
					continue;
				}
				$seen[ $uri ] = true;
				$source = function_exists( 'axismundi_op_resolve_source_by_uri' ) ? axismundi_op_resolve_source_by_uri( $uri ) : null;
				if ( null === $source || ! axismundi_op_reply_collection_child_visible( $source ) ) {
					continue;
				}
				$uris[] = $uri;
			}
			if ( count( $batch ) < 100 ) {
				break;
			}
		}
	}
	$selection = array( 'uris' => $uris, 'truncated' => $offset >= AXISMUNDI_FORUM_ARCHIVE_SCAN_LIMIT );
	set_transient( $key, $selection, HOUR_IN_SECONDS );
	return $selection;
}

/**
 * The Note reply URI one Group Announce distributed, or '' when it distributed something else.
 *
 * FEP-1b12 has the Announce wrap the submission Activity as it was received, so the Object is two
 * levels down. A Topic Article and a Note reply both arrive here; only the reply belongs in a
 * comment list, and `inReplyTo` is what tells them apart.
 *
 * @param Axismundi_Activity $announce Group Announce.
 * @return string
 */
function axismundi_forum_announced_reply_uri( Axismundi_Activity $announce ) : string {
	if ( 'Announce' !== $announce->get_type() ) {
		return '';
	}
	$wrapped = $announce->get_payload()['object'] ?? null;
	if ( ! is_array( $wrapped ) || ! in_array( (string) ( $wrapped['type'] ?? '' ), array( 'Create', 'Update' ), true ) ) {
		return '';
	}
	$object = $wrapped['object'] ?? null;
	if ( ! is_array( $object ) || 'Note' !== (string) ( $object['type'] ?? '' ) || empty( $object['inReplyTo'] ) ) {
		return '';
	}
	return (string) ( $object['id'] ?? '' );
}

/**
 * Render one page of a community's accepted replies.
 *
 * @param Axismundi_Actor $group Community Group.
 * @param int             $page  1-based page number.
 * @return array{html:string,pages:int,page:int}
 */
function axismundi_forum_render_group_comments( Axismundi_Actor $group, int $page ) : array {
	$selection = axismundi_forum_group_comment_uris( $group );
	$total     = count( $selection['uris'] );
	$pages     = max( 1, (int) ceil( $total / AXISMUNDI_FORUM_ARCHIVE_PAGE_SIZE ) );
	$page      = max( 1, min( $pages, $page ) );
	$items     = array();
	foreach ( array_slice( $selection['uris'], ( $page - 1 ) * AXISMUNDI_FORUM_ARCHIVE_PAGE_SIZE, AXISMUNDI_FORUM_ARCHIVE_PAGE_SIZE ) as $uri ) {
		$card = function_exists( 'axismundi_op_render_object_by_uri' )
			? axismundi_op_render_object_by_uri( $uri, array( 'headingTag' => 'h3', 'interactions' => false ) )
			: '';
		if ( '' !== $card ) {
			$items[] = '<li class="axismundi-forum-archive__item axismundi-forum-archive__item--comment">' . $card . '</li>';
		}
	}
	$html = empty( $items )
		? '<p class="axismundi-forum-archive__empty">' . esc_html__( 'No comments yet.', 'axismundi-forum' ) . '</p>'
		: '<ul class="axismundi-forum-archive__items">' . implode( '', $items ) . '</ul>';
	return array( 'html' => $html, 'pages' => $pages, 'page' => $page );
}

/**
 * The tab strip naming a community's two collections.
 *
 * @param string $current Active filter.
 * @return string
 */
function axismundi_forum_render_archive_tabs( string $current ) : string {
	$base  = remove_query_arg( array( 'filter', 'page', 'topic_page' ) );
	$items = array();
	foreach ( axismundi_forum_group_archive_filters() as $key => $label ) {
		$url     = 'posts' === $key ? $base : add_query_arg( 'filter', $key, $base );
		$active  = $key === $current;
		$items[] = '<li class="axismundi-forum-archive__tab' . ( $active ? ' is-active' : '' ) . '">'
			// The active tab is still a link so it can be shared and so a reader can return to the
			// top of a collection they have paged into, but it stops being a navigation target.
			. '<a href="' . esc_url( $url ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
	}
	return '<nav class="axismundi-forum-archive__tabs" aria-label="' . esc_attr__( 'Community collections', 'axismundi-forum' ) . '"><ul>' . implode( '', $items ) . '</ul></nav>';
}

/**
 * Numbered pagination shared by both collections.
 *
 * @param int    $page   Current page.
 * @param int    $pages  Total pages.
 * @param string $filter Active filter.
 * @return string
 */
function axismundi_forum_render_archive_pagination( int $page, int $pages, string $filter ) : string {
	if ( $pages < 2 ) {
		return '';
	}
	$base = remove_query_arg( array( 'page', 'topic_page' ) );
	$base = 'posts' === $filter ? remove_query_arg( 'filter', $base ) : add_query_arg( 'filter', $filter, $base );
	$link = static function ( int $target, string $class, string $label ) use ( $base ) : string {
		return '<a class="axismundi-forum-archive__' . esc_attr( $class ) . '" href="' . esc_url( add_query_arg( 'page', $target, $base ) ) . '">' . esc_html( $label ) . '</a>';
	};
	return '<nav class="axismundi-forum-archive__pagination" aria-label="' . esc_attr__( 'Archive pages', 'axismundi-forum' ) . '">'
		. ( $page > 1 ? $link( $page - 1, 'previous', __( 'Newer', 'axismundi-forum' ) ) : '<span class="axismundi-forum-archive__previous" aria-hidden="true"></span>' )
		/* translators: 1: current page number, 2: total page count. */
		. '<span class="axismundi-forum-archive__page">' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'axismundi-forum' ), $page, $pages ) ) . '</span>'
		. ( $page < $pages ? $link( $page + 1, 'next', __( 'Older', 'axismundi-forum' ) ) : '<span class="axismundi-forum-archive__next" aria-hidden="true"></span>' )
		. '</nav>';
}

/**
 * Render the whole community archive for one Group profile.
 *
 * @param Axismundi_Actor $group Community Group.
 * @return string
 */
function axismundi_forum_render_group_archive( Axismundi_Actor $group ) : string {
	if ( ! axismundi_forum_can_view_community_topics( $group->get_identity_id() ) ) {
		// Claim the Group feed without falling back to its Activity timeline.
		return '<section class="axismundi-forum-archive" hidden aria-hidden="true"></section>';
	}
	$filter = axismundi_forum_group_archive_filter();
	$page   = axismundi_forum_group_archive_page_number();
	if ( 'comments' === $filter ) {
		$rendered   = axismundi_forum_render_group_comments( $group, $page );
		$body       = $rendered['html'];
		$pagination = axismundi_forum_render_archive_pagination( $rendered['page'], $rendered['pages'], $filter );
	} else {
		// Posts keep their existing renderer: it already knows how to tell a local Topic from a
		// cached remote one, and re-deriving that here would give the two collections two answers.
		$body       = axismundi_forum_render_topic_list_block( array( 'perPage' => AXISMUNDI_FORUM_ARCHIVE_PAGE_SIZE ), '', false );
		$pagination = '';
	}
	$labels = axismundi_forum_group_archive_filters();
	return '<section class="axismundi-forum-archive">'
		. '<h2 class="axismundi-forum-archive__heading">' . esc_html( (string) $labels[ $filter ] ) . '</h2>'
		. axismundi_forum_render_archive_tabs( $filter )
		. $body
		. $pagination
		. '</section>';
}
