<?php
/**
 * Human-facing timeline for an Actor profile.
 *
 * The immutable Activity ledger remains authoritative. This is deliberately a
 * presentation adapter over its public-safe payloads, not a second object feed
 * or an archive of WordPress posts.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/**
 * The views a profile timeline can be read through.
 *
 * These are views of one Actor, not separate Actors: a Person is a single identity URI and a
 * single outbox, and splitting the profile across addresses would mean claiming otherwise. The
 * view is a way of reading the same feed, so it lives in a query argument, and the federated
 * representation is untouched by it.
 *
 * @return array<string,array{replies:bool,boosts:bool,label:string}>
 */
function axismundi_act_actor_feed_filters() : array {
	return array(
		'posts'            => array( 'replies' => false, 'boosts' => false, 'label' => __( 'Posts', 'axismundi-activities' ) ),
		'posts-and-boosts' => array( 'replies' => false, 'boosts' => true, 'label' => __( 'Posts and boosts', 'axismundi-activities' ) ),
		'posts-and-replies' => array( 'replies' => true, 'boosts' => false, 'label' => __( 'Posts and replies', 'axismundi-activities' ) ),
		'all'              => array( 'replies' => true, 'boosts' => true, 'label' => __( 'All activity', 'axismundi-activities' ) ),
	);
}

/**
 * The default timeline filter, and the one an unrecognised request falls back to.
 *
 * Replies off, boosts on. A reply is half of a conversation and reads as a fragment out of the
 * thread it belongs to, whereas a boost is something the Actor chose to put on their own page —
 * which is why Mastodon opens on the same combination.
 */
function axismundi_act_actor_feed_default_filter() : string {
	/** @param string $filter Default filter key. */
	$filter  = (string) apply_filters( 'axismundi_act_actor_feed_default_filter', 'posts-and-boosts' );
	$filters = axismundi_act_actor_feed_filters();
	return isset( $filters[ $filter ] ) ? $filter : 'posts-and-boosts';
}

/** Resolve a requested filter key to a supported one. */
function axismundi_act_actor_feed_filter( string $filter ) : string {
	$filters = axismundi_act_actor_feed_filters();
	return isset( $filters[ $filter ] ) ? $filter : axismundi_act_actor_feed_default_filter();
}

/**
 * The filter key for one combination of the two independent switches.
 *
 * The four named filters are the 2x2 product of "show replies" and "show boosts", which is how
 * Mastodon models the same control: it presents the two switches and derives the label. Naming
 * the four combinations keeps them addressable in a URL — a link has to say which list it points
 * at — while the control the reader touches stays the two questions they actually have.
 *
 * @param bool $replies Whether replies are shown.
 * @param bool $boosts  Whether boosts are shown.
 * @return string
 */
function axismundi_act_actor_feed_filter_key( bool $replies, bool $boosts ) : string {
	foreach ( axismundi_act_actor_feed_filters() as $key => $rules ) {
		if ( (bool) $rules['replies'] === $replies && (bool) $rules['boosts'] === $boosts ) {
			return (string) $key;
		}
	}
	return axismundi_act_actor_feed_default_filter();
}

/**
 * Whether one feed entry is a reply.
 *
 * An embedded object answers for itself. A Create that carries only a URI does not, and the
 * thread graph is owned elsewhere, so the question is asked through a filter rather than by
 * reaching into another product's tables from here.
 *
 * @param Axismundi_Activity $activity Ledger row.
 * @param string             $object_uri Canonical object URI.
 * @return bool
 */
function axismundi_act_actor_feed_item_is_reply( Axismundi_Activity $activity, string $object_uri ) : bool {
	$object = $activity->get_payload()['object'] ?? null;
	if ( is_array( $object ) && ! empty( $object['inReplyTo'] ) ) {
		return true;
	}
	$known = is_array( $object ) && array_key_exists( 'inReplyTo', $object );
	/**
	 * Let the product that owns the thread graph answer for an object the Activity only names.
	 *
	 * @param bool               $is_reply   Whether the entry is a reply.
	 * @param string             $object_uri Canonical object URI.
	 * @param Axismundi_Activity $activity   Ledger row.
	 */
	return $known ? false : (bool) apply_filters( 'axismundi_act_actor_feed_item_is_reply', false, $object_uri, $activity );
}

/**
 * Build one viewer-safe feed item descriptor from a Create or Announce row.
 *
 * The card content is not read here: Activities owns selection and verb framing,
 * and hands the object URI to whichever product renders it. Public entries are
 * always eligible; a product may additionally admit an addressed private entry
 * for the current local viewer without changing the public outbox contract.
 */
function axismundi_act_actor_feed_item( Axismundi_Activity $activity, string $surface = 'activity' ) : ?array {
	$visible = axismundi_act_is_publicly_renderable( $activity );
	/**
	 * Viewer-specific Actor-feed admission. This is presentation-only: Activity
	 * collections and public outboxes continue to use their public audience gate.
	 *
	 * The surface is passed because the same entry can belong on one profile surface and not
	 * another. A Topic submitted to a community is not personal activity, but it is exactly what
	 * the community surface exists to show — one predicate, read in two directions, rather than
	 * two rules that can drift apart.
	 *
	 * @param bool                $visible  Whether the activity is publicly renderable.
	 * @param Axismundi_Activity  $activity Candidate ledger row.
	 * @param string              $surface  Profile surface being rendered.
	 */
	$visible = (bool) apply_filters( 'axismundi_act_actor_feed_activity_visible', $visible, $activity, $surface );
	if ( ! $visible ) {
		return null;
	}
	$payload = $activity->get_payload();
	unset( $payload['bto'], $payload['bcc'] );
	$type = $activity->get_type();
	if ( 'Create' !== $type && 'Announce' !== $type ) {
		return null;
	}
	$object_uri = axismundi_act_member_uri( $payload['object'] ?? null );
	if ( '' === $object_uri ) {
		$object_uri = (string) ( $activity->get_object_uri() ?? '' );
	}
	if ( '' === $object_uri ) {
		return null;
	}
	$published = $activity->get_published_at();

	return array(
		'id'         => $activity->get_uri(),
		'kind'       => 'activity',
		'type'       => $type,
		'actor_uri'  => $activity->get_actor_uri(),
		'object_uri' => $object_uri,
		'published'  => is_string( $published ) ? $published : '',
		// An Announce is a boost, never a reply, so the thread graph is not consulted for one.
		'is_reply'   => 'Create' === $type && axismundi_act_actor_feed_item_is_reply( $activity, $object_uri ),
	);
}

/**
 * Whether one feed item belongs in one view.
 *
 * @param array<string,mixed> $item Feed item descriptor.
 * @param string              $filter Resolved filter key.
 * @return bool
 */
function axismundi_act_actor_feed_item_in_filter( array $item, string $filter ) : bool {
	$filters = axismundi_act_actor_feed_filters();
	$rules   = $filters[ $filter ] ?? $filters[ axismundi_act_actor_feed_default_filter() ];
	if ( 'Announce' === (string) ( $item['type'] ?? '' ) ) {
		return (bool) $rules['boosts'];
	}
	// An observed Object has no Activity framing it: it is something this Actor was seen to
	// have posted, so it belongs wherever plain posts do.
	return empty( $item['is_reply'] ) || (bool) $rules['replies'];
}

/** Normalize one third-party observed Object fallback row. */
function axismundi_act_actor_feed_observed_item( $item, Axismundi_Actor $actor ) : ?array {
	if ( ! is_array( $item ) || 'observed_object' !== (string) ( $item['kind'] ?? '' ) || ! hash_equals( $actor->get_uri(), (string) ( $item['actor_uri'] ?? '' ) ) ) {
		return null;
	}
	$object_uri = axismundi_act_uri( $item['object_uri'] ?? '' );
	if ( '' === $object_uri ) {
		return null;
	}
	$published = is_scalar( $item['published'] ?? null ) ? (string) $item['published'] : '';
	return array(
		'id'         => 'observed:' . hash( 'sha256', $object_uri ),
		'kind'       => 'observed_object',
		'type'       => 'Object',
		'actor_uri'  => $actor->get_uri(),
		'object_uri' => $object_uri,
		'published'  => false === strtotime( $published ) ? '' : $published,
	);
}

/**
 * Descending feed chronology with a deterministic identity tie-breaker.
 *
 * Two ledger rows are compared by their cursors, because that is the ordering the query itself
 * used. Sorting them by anything else — an activity URI, say — silently reorders rows that share
 * a timestamp, and then which of the tied rows appears depends on where the page boundary
 * happened to fall. In a cursor-paged feed that would make a row repeat or disappear at the
 * page boundary.
 *
 * An observed Object has no cursor, so it can only be placed by its published time.
 */
function axismundi_act_actor_feed_compare( array $left, array $right ) : int {
	$left_at  = axismundi_act_parse_feed_cursor( (string) ( $left['cursor'] ?? '' ) );
	$right_at = axismundi_act_parse_feed_cursor( (string) ( $right['cursor'] ?? '' ) );
	if ( null !== $left_at && null !== $right_at ) {
		return $left_at['time'] === $right_at['time']
			? $right_at['id'] <=> $left_at['id']
			: strcmp( $right_at['time'], $left_at['time'] );
	}
	$left_time  = '' !== (string) ( $left['published'] ?? '' ) ? (int) strtotime( (string) $left['published'] ) : 0;
	$right_time = '' !== (string) ( $right['published'] ?? '' ) ? (int) strtotime( (string) $right['published'] ) : 0;
	if ( $left_time !== $right_time ) {
		return $right_time <=> $left_time;
	}
	return strcmp( (string) ( $right['id'] ?? '' ), (string) ( $left['id'] ?? '' ) );
}

/**
 * Public Activity feed items for one local or cached remote public Actor.
 *
 * This is the newest page and nothing else. It delegates rather than selecting rows of its own,
 * so a caller that does not paginate still sees exactly what the first page of the paginated
 * feed shows — two selection paths for one feed is how they quietly stop agreeing.
 */
function axismundi_act_actor_feed_items( Axismundi_Actor $actor, int $limit = 20 ) : array {
	return axismundi_act_actor_feed_page( $actor, $limit )['items'];
}

/**
 * One cursor-positioned page of an Actor's public feed.
 *
 * The visibility filter runs after the query, so how many rows a page yields is not known until
 * they have been examined. `has_more` therefore reports whether the *ledger* has anything left
 * beyond this page, not whether that remainder will render — a page that filters down to nothing
 * still hands back a cursor, because stopping there would hide everything older than one private
 * run of activities.
 *
 * @param Axismundi_Actor $actor  Actor whose feed is read.
 * @param int             $limit  Items per page.
 * @param string          $cursor Continuation cursor, or '' for the newest page.
 * @param string          $filter Timeline filter key; entries outside it are skipped.
 * @param string          $surface Profile surface being read; products admit entries per surface.
 * @param bool            $inclusive Whether the cursor includes its anchor row. This is reserved
 *                                   for a caller that needs a stable snapshot; profile navigation
 *                                   always uses an exclusive continuation cursor.
 * @param bool            $head_window Whether the caller is rendering from the feed head.
 * @return array{items:array<int,array<string,mixed>>,next_cursor:string,has_more:bool,filter:string}
 */
function axismundi_act_actor_feed_page( Axismundi_Actor $actor, int $limit = 20, string $cursor = '', string $filter = 'all', string $surface = 'activity', bool $inclusive = false, bool $head_window = false ) : array {
	$filter = axismundi_act_actor_feed_filter( $filter );
	$empty = array( 'items' => array(), 'next_cursor' => '', 'has_more' => false, 'filter' => $filter );
	if ( function_exists( 'axismundi_actors_get_by_uri' ) ) {
		// Re-resolve before applying the public boundary: a status change must not be able to
		// leave a stale Actor object advertising a public feed.
		$current = axismundi_actors_get_by_uri( $actor->get_uri() );
		if ( ! $current instanceof Axismundi_Actor ) {
			return $empty;
		}
		$actor = $current;
	}
	if ( ! function_exists( 'axismundi_actors_is_public_profile' ) || ! axismundi_actors_is_public_profile( $actor ) ) {
		return $empty;
	}
	$limit = max( 1, min( 50, $limit ) );
	$items = array();
	$last  = $cursor;
	/*
	 * A visibility filter can exclude a long run of otherwise valid ledger rows, so one query of
	 * `$limit` rows can yield almost nothing. Walk forward with the cursor until the page is
	 * full rather than letting a private run make the profile look like it ends there. The scan
	 * is bounded: an Actor whose entire recent history is private returns a short page and an
	 * honest cursor, instead of holding the request open reading the whole ledger.
	 */
	$scanned    = 0;
	$scan_limit = (int) apply_filters( 'axismundi_act_actor_feed_scan_limit', max( 50, min( 200, $limit * 10 ) ), $actor, $limit );
	$scan_limit = max( $limit, min( 200, $scan_limit ) );
	$has_more   = false;
	while ( count( $items ) < $limit && $scanned < $scan_limit ) {
		$batch      = min( $limit, $scan_limit - $scanned );
		// Only the first batch may include the anchor row; continuing the scan is always
		// exclusive, or the last row of one batch would repeat as the first of the next.
		$activities = axismundi_act_get_actor_feed_after( $actor->get_uri(), $batch, $last, $inclusive && 0 === $scanned );
		if ( empty( $activities ) ) {
			break;
		}
		$examined = 0;
		$filled   = false;
		foreach ( $activities as $activity ) {
			++$examined;
			if ( ! $activity instanceof Axismundi_Activity ) {
				continue;
			}
			$last = axismundi_act_feed_cursor( $activity );
			$item = axismundi_act_actor_feed_item( $activity, $surface );
			if ( is_array( $item ) && axismundi_act_actor_feed_item_in_filter( $item, $filter ) ) {
				$item['cursor'] = $last;
				$items[]        = $item;
				if ( count( $items ) >= $limit ) {
					// Stop on the row that fills the page, so the page is exactly the size it
					// was asked for and the cursor names the last row actually shown. Draining
					// the rest of the batch would overshoot and leave the cursor past rows the
					// reader never saw.
					$filled = true;
					break;
				}
			}
		}
		$scanned += $examined;
		if ( $filled ) {
			$has_more = true;
			break;
		}
		// A short batch means the ledger is exhausted, which is the only way to know there is
		// nothing further; a full batch leaves the question open and the cursor stands. This is
		// reassigned each pass, so an earlier full batch cannot leave a stale claim of more.
		$has_more = count( $activities ) >= $batch;
		if ( ! $has_more ) {
			break;
		}
	}
	$has_more = $has_more && '' !== $last && ( $inclusive || $last !== $cursor );

	/*
	 * Observed Objects have no position in the ledger — they are a cache-miss fallback anchored
	 * to nothing — so they belong to a render that starts at the top of the feed. Re-offering
	 * them further down would repeat the same rows on every page, since there is no cursor that
	 * could exclude them.
	 */
	if ( '' === $cursor || $head_window ) {
		$activity_object_uris = array_values( array_unique( array_filter( array_map( static fn( array $item ) : string => (string) ( $item['object_uri'] ?? '' ), $items ) ) ) );
		$observed             = (array) apply_filters( 'axismundi_act_actor_feed_observed_items', array(), $actor, $activity_object_uris, $limit );
		foreach ( $observed as $item ) {
			$normalized = axismundi_act_actor_feed_observed_item( $item, $actor );
			if ( is_array( $normalized ) && axismundi_act_actor_feed_item_in_filter( $normalized, $filter ) ) {
				$normalized['cursor'] = '';
				$items[]              = $normalized;
			}
		}
		usort( $items, 'axismundi_act_actor_feed_compare' );
	}
	return array(
		'items'       => $items,
		'next_cursor' => $has_more ? $last : '',
		'has_more'    => $has_more,
		'filter'      => $filter,
	);
}

/**
 * Render one display-sized feed page, skipping immutable ledger rows whose Object vanished.
 *
 * @param callable          $page_callback Surface page callback.
 * @param Axismundi_Actor   $actor         Profile Actor.
 * @param int               $limit         Requested visible-card count.
 * @param string            $cursor        Start cursor.
 * @param string            $filter        Surface filter.
 * @param bool              $inclusive     Whether to include the anchor.
 * @param bool              $head_window   Whether this is the first display page in the window.
 * @return array{cards:array<int,string>,head_cursor:string,next_cursor:string,has_more:bool}
 */
function axismundi_act_actor_feed_display_page( callable $page_callback, Axismundi_Actor $actor, int $limit, string $cursor, string $filter, bool $inclusive = false, bool $head_window = false, string $mode = 'infinite', string $density = 'card' ) : array {
	$cards       = array();
	$head_cursor = $cursor;
	$scan_pages  = 0;
	/*
	 * Resolved here because this is the one function both callers go through.
	 *
	 * The first page is built while a template is being rendered; every page after it comes from a
	 * REST request with no template in sight. Resolving the card in each place separately would
	 * work until somebody changed one of them, and the difference would only ever appear after
	 * "Load more" — so the answer is worked out once, on the way in.
	 */
	$card_template = function_exists( 'axismundi_act_actor_feed_template_source' )
		? axismundi_act_actor_feed_template_source( $actor, $density )
		: '';
	$scan_limit  = max( 1, (int) apply_filters( 'axismundi_act_actor_feed_card_scan_pages', 10, $actor, $limit ) );
	$page        = array( 'items' => array(), 'next_cursor' => '', 'has_more' => false );
	while ( true ) {
		$page = (array) call_user_func( $page_callback, $actor, $limit, $cursor, $filter, $inclusive, $head_window && 0 === $scan_pages );
		foreach ( (array) ( $page['items'] ?? array() ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( '' === $head_cursor && ! empty( $item['cursor'] ) ) {
				$head_cursor = (string) $item['cursor'];
			}
			$card = axismundi_act_render_actor_feed_card( $item, $card_template, $mode );
			if ( '' !== $card ) {
				$cards[] = $card;
			}
		}
		if ( count( $cards ) >= $limit || empty( $page['has_more'] ) || ++$scan_pages >= $scan_limit ) {
			break;
		}
		$cursor = (string) ( $page['next_cursor'] ?? '' );
		if ( '' === $cursor ) {
			break;
		}
		// Only the first query includes its anchor. Every scan after it must advance past the
		// previous batch, or the last ledger row would render twice.
		$inclusive = false;
	}
	return array(
		'cards'       => $cards,
		'head_cursor' => $head_cursor,
		'next_cursor' => (string) ( $page['next_cursor'] ?? '' ),
		'has_more'    => ! empty( $page['has_more'] ),
		/*
		 * Carried through for a paged surface, which answers where it is by number rather than by
		 * cursor. Absent for a cursor feed — a ledger has no page count, and inventing 1 of 1 for
		 * one would be answering a question it was never asked.
		 */
		'page'        => isset( $page['page'] ) ? max( 1, (int) $page['page'] ) : 0,
		'pages'       => isset( $page['pages'] ) ? max( 1, (int) $page['pages'] ) : 0,
	);
}

/**
 * The link that addresses one surface and filter on a profile.
 *
 * The default filter is left out of the URL so the plain profile address stays the plain one, and
 * the timeline's own switches never appear here at all — those are a reading preference kept in
 * the browser, not part of the address.
 *
 * @param array<string,array<string,mixed>> $surfaces Registered surfaces.
 * @param string                            $surface  Surface key.
 * @param string                            $filter   Filter key.
 * @param array<string,scalar>              $query    Extra query arguments.
 * @return string
 */
function axismundi_act_actor_profile_url( array $surfaces, string $surface, string $filter, array $query = array() ) : string {
	$base = remove_query_arg( array( 'feed_after', 'feed_from', 'feed_pages', 'feed_head', 'view', 'filter', 'page' ) );
	$url  = 'activity' === $surface ? $base : add_query_arg( 'view', $surface, $base );
	if ( $filter !== (string) ( $surfaces[ $surface ]['default_filter'] ?? '' ) ) {
		$url = add_query_arg( 'filter', $filter, $url );
	}
	foreach ( $query as $key => $value ) {
		$url = add_query_arg( $key, rawurlencode( (string) $value ), $url );
	}
	return $url;
}

/**
 * How densely the feed draws each entry.
 *
 * A presentation, not a selection: both densities list the same entries in the same order, and
 * only the amount of each one drawn differs. It belongs to the feed rather than to whichever
 * product supplied the entries, because it is a property of reading a list — which is also why a
 * Person timeline can have it without anything new being invented for it.
 *
 * @return array<string,string>
 */
function axismundi_act_feed_densities() : array {
	return array(
		'card'    => __( 'Card', 'axismundi-activities' ),
		'compact' => __( 'Compact', 'axismundi-activities' ),
	);
}

/**
 * The density the reader asked for.
 *
 * `density`, not `view`. `view` is already the address of a profile *surface* — the thing
 * `?view=community` selects — and a community archive briefly published `?view=compact` for this
 * instead. Two meanings for one name is not a collision that can be lived with: once a community
 * became a surface, `?view=compact` started resolving to a surface that does not exist and quietly
 * fell back to the default.
 *
 * The old spelling is still read, and only when it names a density, so `?view=community` is never
 * mistaken for one. It is never emitted; every link this feed builds carries `density`.
 *
 * @return string
 */
function axismundi_act_feed_density( array $available = array() ) : string {
	/*
	 * What the template offers, not what this file can name. A density nobody saved a card for is
	 * not a density this feed has, so asking for it is the same as asking for nothing.
	 */
	$available = array_values( array_filter( $available, static fn( $key ) : bool => isset( axismundi_act_feed_densities()[ $key ] ) ) );
	if ( empty( $available ) ) {
		return 'card';
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read presentation.
	$requested = isset( $_GET['density'] ) ? sanitize_key( wp_unslash( $_GET['density'] ) ) : '';
	if ( ! in_array( $requested, $available, true ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- legacy public read presentation.
		$legacy    = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		$requested = in_array( $legacy, $available, true ) ? $legacy : '';
	}
	// The first saved card is the default, so re-ordering the templates re-homes the plain address.
	return '' !== $requested ? $requested : $available[0];
}

/** One navigation tab. */
function axismundi_act_actor_profile_tab( string $href, string $label, bool $is_current, string $class ) : string {
	return '<a class="' . esc_attr( $class ) . ( $is_current ? ' is-current' : '' ) . '" href="' . esc_url( $href ) . '"'
		. ( $is_current ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
}

/**
 * Chrome for a surface that renders its own body.
 *
 * The tabs are the same tabs the timeline gets, built once here so a surface that is not a feed
 * does not grow a second, drifting copy of the profile's navigation.
 *
 * @param Axismundi_Actor                   $actor    Profile Actor.
 * @param array<string,array<string,mixed>> $surfaces Registered surfaces.
 * @param string                            $surface  Current surface key.
 * @param string                            $filter   Current filter key.
 * @param array<string,mixed>               $current  Current surface definition.
 * @param string                            $body     Surface-rendered body HTML.
 * @return string
 */
function axismundi_act_render_actor_feed_shell( Axismundi_Actor $actor, array $surfaces, string $surface, string $filter, array $current, string $body ) : string {
	unset( $actor );
	$surface_nav = '';
	if ( count( $surfaces ) > 1 ) {
		$tabs = array();
		foreach ( $surfaces as $key => $definition ) {
			$tabs[] = axismundi_act_actor_profile_tab(
				axismundi_act_actor_profile_url( $surfaces, (string) $key, (string) $definition['default_filter'] ),
				(string) $definition['label'],
				(string) $key === $surface,
				'axismundi-activity-feed__surface'
			);
		}
		$surface_nav = '<nav class="axismundi-activity-feed__surfaces" aria-label="' . esc_attr__( 'Profile surfaces', 'axismundi-activities' ) . '">'
			. implode( '', $tabs ) . '</nav>';
	}
	$filter_tabs = array();
	foreach ( (array) $current['filters'] as $key => $label ) {
		$filter_tabs[] = axismundi_act_actor_profile_tab(
			axismundi_act_actor_profile_url( $surfaces, $surface, (string) $key ),
			(string) $label,
			(string) $key === $filter,
			'axismundi-activity-feed__view'
		);
	}
	$filter_nav = count( $filter_tabs ) > 1
		? '<nav class="axismundi-activity-feed__views" aria-label="' . esc_attr__( 'Timeline views', 'axismundi-activities' ) . '">' . implode( '', $filter_tabs ) . '</nav>'
		: '';
	return '<section class="axismundi-activity-feed" aria-labelledby="axismundi-activity-feed-heading">'
		. '<h2 id="axismundi-activity-feed-heading" class="axismundi-activity-feed__heading">' . esc_html( (string) $current['heading'] ) . '</h2>'
		. $surface_nav
		. $filter_nav
		. $body
		. '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Tabs are escaped above; the body is escaped by the surface that produced it.
}

/** Render the current Actor's public Activity feed. */
function axismundi_act_render_actor_activity_feed() : string {
	if ( ! function_exists( 'axismundi_actors_current_actor' ) ) {
		return '';
	}
	$actor = axismundi_actors_current_actor();
	if ( ! $actor instanceof Axismundi_Actor ) {
		return '';
	}
	/**
	 * Let a product that owns this Actor's community replace the timeline entirely.
	 *
	 * Some Actors are not people posting: a Group's profile *is* its community, the way a
	 * Lemmy community page is, and what belongs under it is that community's threads rather
	 * than a chronology of the Group's own Activities. The Actor profile stays the one public
	 * surface — there is no second page to send anyone to — so the product holding those
	 * threads answers here and Activities steps aside.
	 *
	 * Activities does not know what a Forum is, and must not: it offers the slot and takes
	 * whatever comes back, exactly as it does for the object cards below.
	 *
	 * @param string          $html  Empty by default, which keeps the Activity timeline.
	 * @param Axismundi_Actor $actor Actor whose profile is being rendered.
	 */
	$claimed = (string) apply_filters( 'axismundi_act_actor_feed_html', '', $actor );
	if ( '' !== $claimed ) {
		return $claimed;
	}
	/*
	 * The Actor profile template is PHP that embeds this block as markup. Core's metadata-driven
	 * view-module enqueue is skipped on that composition path, so the feed shell must enqueue its
	 * own delegated-controller module before it emits `data-wp-interactive="axismundi/actor-feed"`.
	 */
	if ( function_exists( 'wp_enqueue_script_module' ) ) {
		wp_enqueue_script_module( 'axismundi-actor-feed-loop-view-script-module' );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read pagination.
	$cursor = isset( $_GET['feed_after'] ) ? sanitize_text_field( wp_unslash( $_GET['feed_after'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read surface selection.
	$surface = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read filter selection.
	$filter   = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : '';
	$surfaces = axismundi_act_actor_profile_surfaces( $actor );
	$surface  = isset( $surfaces[ $surface ] ) ? $surface : axismundi_act_actor_default_surface( $surfaces );
	$current  = $surfaces[ $surface ];
	/*
	 * A surface's slices divide differently, so they are addressed differently.
	 *
	 * The community surface's slices are collections — Topics is a different list of things than
	 * Replies — so they stay in the URL where they can be linked to and crawled. The timeline's
	 * switches are a reading preference, closer to a display setting than to a destination: two
	 * readers looking at the same profile are looking at the same thing whether or not one of
	 * them has boosts hidden. A preference does not belong in a URL it would then be baked into
	 * and shared with, so those live in the reader's browser and the server always renders the
	 * default.
	 */
	$client_owned = ! empty( $current['toggles'] );
	$filter       = $client_owned || ! isset( $current['filters'][ $filter ] )
		? (string) $current['default_filter']
		: $filter;
	/**
	 * How many timeline entries one page shows.
	 *
	 * @param int             $per_page Entries per page.
	 * @param Axismundi_Actor $actor    Actor whose profile is being rendered.
	 */
	$per_page = (int) apply_filters( 'axismundi_act_actor_feed_per_page', 20, $actor );
	/*
	 * The ledger can outlive the object it once announced. `actor_feed_page()` intentionally keeps
	 * those rows: it owns an immutable record, not the mutable local object store. The card
	 * renderer rightly drops an unresolved Create, though, so a page selected only by ledger rows
	 * can otherwise be blank while its "older" link points at the first useful card.
	 *
	 * Fill each display page by following a bounded number of cursor pages until it has actual
	 * cards.
	 *
	 * The server always renders exactly one page. Loading more is the browser's job: it asks the
	 * feed endpoint for the next page and appends it once, which is what Jetpack's infinite
	 * scroll does and what keeps the cost of each step constant. Re-rendering everything already
	 * on screen in order to grow a window makes the tenth step ten times the work of the first.
	 */
	/*
	 * A surface may render its own body.
	 *
	 * The timeline is a feed: cards, newest first, continued by cursor. A contributions archive
	 * is not — it is browsed, returned to, and linked into ("the thing I wrote on page 3"), which
	 * wants numbered pages and a list rather than an endless column of full posts. Rather than
	 * teach the feed chrome to be both, a surface that is not a feed supplies its own body and
	 * keeps only the tabs above it.
	 */
	if ( isset( $current['render'] ) && is_callable( $current['render'] ) ) {
		$body = (string) call_user_func( $current['render'], $actor, $filter, $per_page );
		return axismundi_act_render_actor_feed_shell( $actor, $surfaces, $surface, $filter, $current, $body );
	}
	$mode    = (string) ( $current['mode'] ?? 'infinite' );
	$densities_available = axismundi_act_actor_feed_densities_available( $actor );
	$density             = axismundi_act_feed_density( $densities_available );
	/*
	 * A paged surface is addressed by number, so the position it is asked for is a page rather
	 * than a cursor. `page` is the same name the community archive already published, so links
	 * already in the world keep working.
	 */
	if ( 'pagination' === $mode ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read pagination.
		$requested_page = isset( $_GET['page'] ) ? max( 1, absint( wp_unslash( $_GET['page'] ) ) ) : 1;
		$cursor         = (string) $requested_page;
	}
	$page  = axismundi_act_actor_feed_display_page( $current['page'], $actor, $per_page, $cursor, $filter, false, false, $mode, $density );
	$cards = implode( '', $page['cards'] );
	/*
	 * An empty *narrowed* surface still renders: the reader chose that tab and needs the
	 * navigation to get back out of it. Only an Actor with nothing at all on the default surface,
	 * and nowhere else to go, yields nothing — so a profile that has never posted does not grow
	 * an empty timeline chrome.
	 */
	if ( '' === $cards && empty( $page['has_more'] ) && '' === $cursor && 'activity' === $surface
		&& $filter === (string) $current['default_filter'] && count( $surfaces ) <= 1 ) {
		return '';
	}

	/*
	 * Surface and filter are both part of the address of what the reader is looking at, so they
	 * ride along on every link built here. Dropping either from the continuation would silently
	 * return them to the default one page in — the very list they had just chosen to narrow.
	 *
	 * The cursor is deliberately not carried across a surface or filter change: a cursor names a
	 * position in one ordering, and reusing it in a differently filtered list would land the
	 * reader somewhere they were never at.
	 */
	$base_url = remove_query_arg( array( 'feed_after', 'feed_from', 'feed_pages', 'feed_head', 'view', 'filter', 'density' ) );
	/*
	 * Density rides along on everything, because it is the reader's, not the list's. A tab or a
	 * page number that dropped it would return them to cards on the first click — and nothing
	 * about the page would look wrong afterwards, which is how the previous attempt at this went
	 * unnoticed. The default is carried by omission so a card address stays the short one.
	 */
	// Carried only when it is not the default, and the default is the first saved card.
	if ( isset( $densities_available[0] ) && $density !== $densities_available[0] ) {
		$base_url = add_query_arg( 'density', $density, $base_url );
	}
	$link_url = static function ( string $surface_key, string $filter_key, array $query = array() ) use ( $base_url, $surfaces ) : string {
		$url = 'activity' === $surface_key ? $base_url : add_query_arg( 'view', $surface_key, $base_url );
		if ( $filter_key !== (string) $surfaces[ $surface_key ]['default_filter'] ) {
			$url = add_query_arg( 'filter', $filter_key, $url );
		}
		foreach ( $query as $key => $value ) {
			$url = add_query_arg( $key, rawurlencode( (string) $value ), $url );
		}
		return $url;
	};
	$tab = static function ( string $href, string $label, bool $is_current, string $class ) : string {
		return '<a class="' . esc_attr( $class ) . ( $is_current ? ' is-current' : '' ) . '" href="' . esc_url( $href ) . '"'
			. ( $is_current ? ' aria-current="page"' : '' ) . '>'
			. esc_html( $label ) . '</a>';
	};

	// One surface is not a choice, so the surface navigation appears only once a product has
	// contributed a second one.
	$surface_nav = '';
	if ( count( $surfaces ) > 1 ) {
		$surface_tabs = array();
		foreach ( $surfaces as $key => $definition ) {
			$surface_tabs[] = $tab( $link_url( (string) $key, (string) $definition['default_filter'] ), (string) $definition['label'], (string) $key === $surface, 'axismundi-activity-feed__surface' );
		}
		$surface_nav = '<nav class="axismundi-activity-feed__surfaces" aria-label="' . esc_attr__( 'Profile surfaces', 'axismundi-activities' ) . '">'
			. implode( '', $surface_tabs ) . '</nav>';
	}
	/*
	 * Two shapes of control, because the two surfaces ask different kinds of question.
	 *
	 * The timeline's filters are the product of two independent switches — show replies, show
	 * boosts — so they are presented as two switches under a disclosure whose label states the
	 * combination, which is what Mastodon does. The community surface's slices are mutually
	 * exclusive collections, so they stay tabs, which is what Lemmy does. Forcing either into
	 * the other's control would misrepresent how the choices relate.
	 *
	 * The switches are links, not checkboxes, and the disclosure is a `details` element. Both
	 * work with no script at all, and the chosen combination stays in the URL rather than in
	 * browser storage — Mastodon can keep it in `localStorage` because nothing on that page is
	 * server-rendered, but ours is, so a stored preference the server cannot see would be
	 * contradicted by the very first paint and would not survive being shared as a link.
	 */
	$filter_nav = '';
	if ( $client_owned ) {
		$state    = axismundi_act_actor_feed_filters()[ $filter ];
		$switches = array();
		/*
		 * A native checkbox with `role="switch"`, wrapped in its own label. Native means the
		 * browser keeps Space-to-toggle, focus, and the checked state for free; the track is a
		 * sibling the theme draws, and the handle is that track's pseudo-element rather than
		 * another node to keep in sync. The plugin emits the semantics; the switch's appearance
		 * is a theme component, the same way buttons and selects are.
		 */
		foreach ( (array) $current['toggles'] as $bit => $label ) {
			$switches[] = '<label class="axismundi-switch axismundi-activity-feed__switch">'
				. '<input class="axismundi-switch__input" type="checkbox" role="switch"'
				. ' name="' . esc_attr( (string) $bit ) . '"'
				. checked( (bool) $state[ $bit ], true, false )
				. ' data-wp-on--change="actions.setFilter">'
				. '<span class="axismundi-switch__track" aria-hidden="true"></span>'
				. '<span class="axismundi-switch__label">' . esc_html( (string) $label ) . '</span>'
				. '</label>';
		}
		/*
		 * A trigger and a popover, which is the shape the Add reaction picker already
		 * established: a button that states what is open, and a `role="dialog"` panel holding
		 * controls. A `details` element would have been less code but the wrong promise — this
		 * holds form controls, floats over the page, and closes on Escape and on a click
		 * outside, none of which a disclosure does.
		 *
		 * The whole control is hidden until the runtime reveals it. These are real checkboxes in
		 * no form, so without script they would sit there doing nothing — and a control that
		 * visibly does nothing is worse than one never offered. A reader without script gets the
		 * default timeline, which is the timeline everyone else starts on.
		 */
		$filter_nav = '<div class="axismundi-activity-feed__filters" hidden'
			. ' data-wp-init="callbacks.watchFilters" data-wp-watch="callbacks.filtersLifecycle">'
			. '<button type="button" class="axismundi-activity-feed__filters-trigger"'
			. ' data-wp-on--click="actions.toggleFilters"'
			. ' data-wp-bind--aria-expanded="context.isFiltersOpen"'
			. ' aria-haspopup="dialog">'
			. '<span data-wp-text="context.filterLabel">' . esc_html( (string) $current['filters'][ $filter ] ) . '</span>'
			. '<span class="material-symbols-outlined" aria-hidden="true">unfold_more</span>'
			. '</button>'
			. '<div class="axismundi-activity-feed__filters-panel" role="dialog"'
			. ' aria-label="' . esc_attr__( 'Timeline filters', 'axismundi-activities' ) . '"'
			. ' hidden data-wp-bind--hidden="!context.isFiltersOpen">'
			. implode( '', $switches )
			. '</div>'
			. '</div>';
	} else {
		$filter_tabs = array();
		foreach ( (array) $current['filters'] as $key => $label ) {
			$filter_tabs[] = $tab( $link_url( $surface, (string) $key ), (string) $label, (string) $key === $filter, 'axismundi-activity-feed__view' );
		}
		$filter_nav = count( $filter_tabs ) > 1
			? '<nav class="axismundi-activity-feed__views" aria-label="' . esc_attr__( 'Timeline views', 'axismundi-activities' ) . '">' . implode( '', $filter_tabs ) . '</nav>'
			: '';
	}

	/*
	 * Load more is a real link before it is anything else. Without JavaScript it navigates to the
	 * next cursor page, so the whole feed stays reachable by a reader with no script and by a
	 * crawler. With JavaScript the same element fetches that page from the feed endpoint and
	 * appends it once — constant work per step, which is the whole reason for doing it this way
	 * rather than re-rendering a growing window.
	 */
	/*
	 * The control is always present and hidden when there is nothing further, rather than being
	 * removed and rebuilt. Changing the switches replaces the list from page one, and a link that
	 * had been deleted would have to be recreated at exactly the right moment; one that is only
	 * hidden simply reappears.
	 */
	$has_more = ! empty( $page['has_more'] ) && '' !== (string) $page['next_cursor'];
	$more     = '<a class="axismundi-activity-feed__more-link" data-wp-on--click="actions.loadMore"'
		. ( $has_more ? '' : ' hidden' )
		. ' href="' . esc_url( $link_url( $surface, $filter, array( 'feed_after' => (string) $page['next_cursor'] ) ) ) . '">'
		. esc_html__( 'Load more', 'axismundi-activities' ) . '</a>';
	$newer = '';
	if ( '' !== $cursor ) {
		// A cursor names where the next page starts, not where the reader came from, so there is
		// no honest "previous". The top is the one position always known to be real.
		$newer = '<a class="axismundi-activity-feed__newer-link" href="' . esc_url( $link_url( $surface, $filter ) ) . '">'
			. esc_html__( 'Back to the newest activity', 'axismundi-activities' ) . '</a>';
	}
	$navigation = '<nav class="axismundi-activity-feed__pagination" aria-label="' . esc_attr__( 'Timeline pages', 'axismundi-activities' ) . '">' . $newer . $more . '</nav>';
	/*
	 * A numbered pager instead, when the surface is walked that way.
	 *
	 * Plain links with no runtime behaviour: this surface changes page by navigating, which is the
	 * whole reason its position is a number and not a cursor. That also means it works with no
	 * script and that the address reproduces exactly what the reader is looking at.
	 */
	if ( 'pagination' === $mode ) {
		$current_page = max( 1, (int) ( $page['page'] ?? 1 ) );
		$total_pages  = max( 1, (int) ( $page['pages'] ?? 1 ) );
		$page_link    = static function ( int $target, string $class, string $label ) use ( $link_url, $surface, $filter ) : string {
			return '<a class="axismundi-activity-feed__' . esc_attr( $class ) . '" href="'
				. esc_url( $link_url( $surface, $filter, array( 'page' => (string) $target ) ) ) . '">' . esc_html( $label ) . '</a>';
		};
		$navigation = $total_pages < 2 ? '' : '<nav class="axismundi-activity-feed__pagination" aria-label="'
			. esc_attr__( 'Archive pages', 'axismundi-activities' ) . '">'
			. ( $current_page > 1 ? $page_link( $current_page - 1, 'previous-link', __( 'Newer', 'axismundi-activities' ) ) : '' )
			/* translators: 1: current page number, 2: total page count. */
			. '<span class="axismundi-activity-feed__page">' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'axismundi-activities' ), $current_page, $total_pages ) ) . '</span>'
			. ( $current_page < $total_pages ? $page_link( $current_page + 1, 'next-link', __( 'Older', 'axismundi-activities' ) ) : '' )
			. '</nav>';
	}
	$reaction_host = function_exists( 'axismundi_act_render_feed_reaction_picker_host' )
		? axismundi_act_render_feed_reaction_picker_host()
		: '';
	$announce_host = function_exists( 'axismundi_act_render_feed_announce_menu_host' )
		? axismundi_act_render_feed_announce_menu_host()
		: '';

	/*
	 * The feed is a client-rendered island inside a server-rendered page: the profile header,
	 * the tabs, and the first page are ordinary HTML, and only the continuation is fetched. The
	 * context carries everything the runtime needs to ask for the next page, so it never has to
	 * parse it back out of a URL.
	 */
	$context = array(
		'endpoint'      => rest_url( 'axismundi/v1/actor-feed' ),
		/*
		 * Cookie authentication on a REST request counts only alongside a nonce. Without one the
		 * next page is fetched as a stranger, and every control on it comes back telling the
		 * reader to log in — on a page they are already logged into.
		 */
		'nonce'         => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
		'actorUri'      => $actor->get_uri(),
		'surface'       => $surface,
		'filter'        => $filter,
		'cursor'        => $has_more ? (string) $page['next_cursor'] : '',
		'perPage'       => $per_page,
		'defaultFilter' => (string) $current['default_filter'],
		'filterLabel'   => (string) $current['filters'][ $filter ],
		// Labels for every combination, so the runtime can name the reader's choice without
		// asking the server again for a string it already had.
		'filterLabels'  => array_map( 'strval', (array) $current['filters'] ),
		'clientOwned'   => $client_owned,
		'isFiltersOpen' => false,
		'isPending'     => false,
		'error'         => '',
		'errorFallback' => __( 'More activity could not be loaded.', 'axismundi-activities' ),
	);
	/*
	 * Plain links, so the density survives with no script and can be shared. It changes nothing
	 * about which entries are listed, so it must not look like it selects anything — and the
	 * reader's page and collection are kept, because density does not move anybody.
	 */
	$density_switch = array();
	$density_labels = axismundi_act_feed_densities();
	foreach ( $densities_available as $index => $key ) {
		$is_current   = (string) $key === $density;
		/*
		 * The default is addressed by leaving `density` out, and the default is whichever card was
		 * saved first. So re-ordering the templates moves the plain address onto the new default
		 * while an old `?density=card` link keeps opening card explicitly, which is what it said.
		 */
		$density_href = 0 === (int) $index
			? remove_query_arg( 'density' )
			: add_query_arg( 'density', (string) $key, remove_query_arg( 'density' ) );
		$density_switch[] = '<a class="axismundi-activity-feed__density' . ( $is_current ? ' is-current' : '' ) . '"'
			. ' href="' . esc_url( $density_href ) . '"' . ( $is_current ? ' aria-current="true"' : '' ) . '>'
			. esc_html( (string) ( $density_labels[ $key ] ?? $key ) ) . '</a>';
	}
	/*
	 * No switch when there is nothing to switch between. A template holding one card is a template
	 * whose author decided how this feed reads, and offering a control with a single option would
	 * be presenting that decision as a question.
	 */
	$density_nav = count( $density_switch ) < 2 ? '' : '<div class="axismundi-activity-feed__densities" role="group" aria-label="'
		. esc_attr__( 'Entry density', 'axismundi-activities' ) . '">' . implode( '', $density_switch ) . '</div>';

	$parts = array(
		'filters'    => $filter_nav,
		'density'    => $density_nav,
		// The list is always emitted, even when this page is empty, because appended pages need
		// something to attach to. An empty feed says so in its own row rather than by omitting
		// the container the runtime is going to look for.
		'list'       => '<ol class="axismundi-activity-feed__list" data-wp-init="callbacks.watchFeed">'
			. ( '' === $cards ? '<li class="axismundi-activity-feed__empty">' . esc_html__( 'Nothing to show in this view.', 'axismundi-activities' ) . '</li>' : $cards )
			. '</ol>'
			. $announce_host
			. $reaction_host
			. '<p class="axismundi-activity-feed__status" data-wp-text="context.error" role="status"></p>',
		'pagination' => $navigation,
	);
	/*
	 * The template's arrangement when it has one, and the arrangement it used to be born with
	 * when it does not. The list is forced back in either way: it holds the cards, the runtime
	 * finds the continuation container by looking for it, and a template that omitted it would
	 * be asking for a feed with no feed in it.
	 */
	$slots = axismundi_act_actor_feed_slots( $actor );
	// A template written before the density block existed still gets the control, in the place it
	// was emitted from before it could be placed at all.
	$slots = array() === $slots ? array( 'filters', 'density', 'list', 'pagination' ) : $slots;
	if ( ! in_array( 'list', $slots, true ) ) {
		$slots[] = 'list';
	}
	$body = '';
	foreach ( $slots as $slot ) {
		$body .= $parts[ $slot ] ?? '';
	}

	return '<section class="axismundi-activity-feed is-density-' . esc_attr( $density ) . '"'
		. ' data-density="' . esc_attr( $density ) . '" data-wp-interactive="axismundi/actor-feed" '
		. wp_interactivity_data_wp_context( $context )
		. ' aria-labelledby="axismundi-activity-feed-heading">'
		. '<h2 id="axismundi-activity-feed-heading" class="axismundi-activity-feed__heading">' . esc_html( (string) $current['heading'] ) . '</h2>'
		. $surface_nav
		. $body
		. '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Cards and controls are escaped above.
}

/**
 * The surfaces a Person profile can be read through.
 *
 * A Person is one Actor with one identity URI and one outbox, so these are not separate Actors
 * and not separate profiles — they are collections over the same Actor's objects, which is the
 * model Lemmy and WordPress.org both use. The federated representation is untouched by which one
 * a reader happens to be looking at.
 *
 * Activities owns exactly one surface: what this Person published themselves. A product that
 * owns some other context of the same Actor — a forum's community contributions, an archive of
 * their long-form work — registers its own here, and Activities does not know those exist.
 *
 * @param Axismundi_Actor $actor Actor whose profile is being rendered.
 * @return array<string,array{label:string,heading:string,filters:array<string,string>,default_filter:string,page:callable}>
 */
function axismundi_act_actor_profile_surfaces( Axismundi_Actor $actor ) : array {
	$filters = array();
	foreach ( axismundi_act_actor_feed_filters() as $key => $definition ) {
		$filters[ $key ] = (string) $definition['label'];
	}
	$activity = array(
		'label'          => __( 'Activity', 'axismundi-activities' ),
		'heading'        => __( 'Timeline', 'axismundi-activities' ),
		'filters'        => $filters,
		// The two independent switches the four filter keys are the product of.
		'toggles'        => array(
			'replies' => __( 'Show replies', 'axismundi-activities' ),
			'boosts'  => __( 'Show boosts', 'axismundi-activities' ),
		),
		'default_filter' => axismundi_act_actor_feed_default_filter(),
		'page'           => 'axismundi_act_actor_activity_surface_page',
		/*
		 * How this surface is walked, which is a property of the collection and not of the theme.
		 *
		 * A ledger of Activities has no length anybody asks for and one direction, so it is walked
		 * with a cursor and continued in place. A community's archive is a list that can be
		 * counted and jumped around in, so it is walked by number. The surface says which, and the
		 * pagination block obeys — rather than each product drawing its own control and its own
		 * list markup, which is how one profile came to be an `ol` and the other a `ul`.
		 */
		'mode'           => 'infinite',
	);
	/**
	 * Contribute a profile surface for an Actor.
	 *
	 * Each surface supplies a `page` callable with the signature
	 * `( Axismundi_Actor $actor, int $limit, string $cursor, string $filter, bool $inclusive,
	 * bool $head_window )` returning the same
	 * shape as `axismundi_act_actor_feed_page()`, so chrome, pagination, and card rendering are
	 * shared rather than reimplemented per product.
	 *
	 * @param array<string,array<string,mixed>> $surfaces Registered surfaces, keyed by slug.
	 * @param Axismundi_Actor                   $actor    Actor whose profile is being rendered.
	 */
	$surfaces = (array) apply_filters( 'axismundi_act_actor_profile_surfaces', array( 'activity' => $activity ), $actor );
	$surfaces = array_filter( $surfaces, 'is_array' );
	/*
	 * A product may remove the Activity surface, but only by leaving another in its place.
	 *
	 * It used to be unremovable, on the grounds that a reader must not lose the timeline out from
	 * under them. That is right for a Person and wrong for a Group whose profile *is* its
	 * community: there, a chronology of the Group Actor's own Activities is not a second way to
	 * read the profile, it is a different and emptier thing, and offering it as a tab invites a
	 * reader to leave the only content there is.
	 *
	 * Leaving nothing is still refused, because a profile with no surface has no feed at all and
	 * would fail as a blank region rather than as an error.
	 */
	if ( empty( $surfaces ) ) {
		$surfaces = array( 'activity' => $activity );
	}
	foreach ( $surfaces as $key => $definition ) {
		$surfaces[ $key ] = array_merge(
			array( 'mode' => 'infinite', 'filters' => array(), 'default_filter' => '', 'heading' => '', 'label' => (string) $key ),
			$definition
		);
		$surfaces[ $key ]['mode'] = 'pagination' === ( $definition['mode'] ?? '' ) ? 'pagination' : 'infinite';
	}
	return $surfaces;
}

/**
 * The surface a profile opens on, and the one an unrecognised address falls back to.
 *
 * `activity` when it is present, which is every Person. When a product has replaced the set — a
 * community Group, whose profile is its archive — the fallback is the first surface registered
 * rather than a name that is no longer there. Falling back to a missing key is how a profile
 * renders as an empty region with nothing to say about why.
 *
 * @param array<string,array<string,mixed>> $surfaces Registered surfaces.
 * @return string
 */
function axismundi_act_actor_default_surface( array $surfaces ) : string {
	if ( isset( $surfaces['activity'] ) ) {
		return 'activity';
	}
	$keys = array_keys( $surfaces );
	return '' !== (string) ( $keys[0] ?? '' ) ? (string) $keys[0] : 'activity';
}

/** The Activities-owned surface: what this Person published themselves. */
function axismundi_act_actor_activity_surface_page( Axismundi_Actor $actor, int $limit, string $cursor, string $filter, bool $inclusive = false, bool $head_window = false ) : array {
	return axismundi_act_actor_feed_page( $actor, $limit, $cursor, $filter, 'activity', $inclusive, $head_window );
}

/**
 * Render feed item descriptors into list items.
 *
 * Extracted so the REST continuation returns markup built by exactly this code. A second
 * renderer for appended pages is how the tenth page stops looking like the first.
 *
 * @param array<int,array<string,mixed>> $items Feed item descriptors.
 * @return string
 */
function axismundi_act_render_actor_feed_cards( array $items, string $card_template = '', string $mode = 'infinite' ) : string {
	$cards = array();
	foreach ( $items as $item ) {
		if ( is_array( $item ) ) {
			$card = axismundi_act_render_actor_feed_card( $item, $card_template, $mode );
			if ( '' !== $card ) {
				$cards[] = $card;
			}
		}
	}
	return implode( '', $cards );
}

/** Render one public-safe feed descriptor into a card, or nothing when its object no longer exists. */
function axismundi_act_render_actor_feed_card( array $item, string $card_template = '', string $mode = 'infinite' ) : string {
	/**
	 * Let an object-owning product render a public activity's object through its own view model.
	 * Activities deliberately owns only ledger selection and verb framing, so it never reaches
	 * into Note or Object Projections directly. Object Projections registers the default handler.
	 *
	 * @param string              $html Empty by default.
	 * @param array<string,mixed> $item Public-safe Activity feed item.
	 */
	$object_html = (string) apply_filters( 'axismundi_act_actor_feed_object_html', '', $item, $card_template, $mode );
	if ( '' === $object_html ) {
		/**
		 * A public Activity can reference a remote Object which was not embedded in a Create and
		 * has not been explicitly cached. Products may render a safe external reference in that
		 * narrow cache-miss case. A known tombstone or non-public source remains hidden.
		 *
		 * @param string              $html Empty by default.
		 * @param array<string,mixed> $item Public-safe Activity feed item.
		 */
		$object_html = (string) apply_filters( 'axismundi_act_actor_feed_missing_object_html', '', $item );
		if ( '' === $object_html ) {
			return '';
		}
	}
	$frame = '';
	if ( 'Announce' === (string) ( $item['type'] ?? '' ) ) {
		$frame = '<p class="axismundi-activity-feed__boost"><span class="material-symbols-outlined" aria-hidden="true">sync</span> '
			. esc_html__( 'Boosted', 'axismundi-activities' ) . '</p>';
	}
	return '<li class="axismundi-activity-feed__item axismundi-activity-feed__item--object axismundi-activity-feed__item--' . esc_attr( strtolower( (string) ( $item['type'] ?? '' ) ) ) . '">'
		. $frame
		. $object_html
		. '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Frame is escaped above; the owning product owns and escapes its renderer output.
}

/**
 * Register the feed continuation endpoint.
 *
 * Reading a public profile's timeline needs no authentication, and requiring it would make every
 * response uncacheable for the anonymous readers who are most of the traffic. The endpoint serves
 * what the profile page itself would show the same visitor, applying the same public boundary.
 */
function axismundi_act_register_actor_feed_route() : void {
	register_rest_route(
		'axismundi/v1',
		'/actor-feed',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_act_rest_actor_feed',
			'permission_callback' => '__return_true',
			'args'                => array(
				'actor_uri' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ),
				'surface'   => array( 'required' => false, 'type' => 'string', 'default' => 'activity' ),
				'filter'    => array( 'required' => false, 'type' => 'string', 'default' => '' ),
				'after'     => array( 'required' => false, 'type' => 'string', 'default' => '' ),
				'density'   => array( 'required' => false, 'type' => 'string', 'default' => 'card' ),
				'per_page'  => array( 'required' => false, 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 50 ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_act_register_actor_feed_route' );

/** Serve exactly one continuation page of a profile feed. */
function axismundi_act_rest_actor_feed( WP_REST_Request $request ) {
	$actor = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( axismundi_act_uri( (string) $request['actor_uri'] ) ) : null;
	if ( ! $actor instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_act_feed_actor', __( 'That Actor is not available here.', 'axismundi-activities' ), array( 'status' => 404 ) );
	}
	$surfaces = axismundi_act_actor_profile_surfaces( $actor );
	$surface  = (string) $request['surface'];
	$surface  = isset( $surfaces[ $surface ] ) ? $surface : axismundi_act_actor_default_surface( $surfaces );
	$current  = $surfaces[ $surface ];
	$filter   = (string) $request['filter'];
	$filter   = isset( $current['filters'][ $filter ] ) ? $filter : (string) $current['default_filter'];

	// The continuation has to repeat the card the first page drew, so the density travels with the
	// request. Reading it off the server would find whatever the last visitor asked for.
	$density  = 'compact' === (string) $request['density'] ? 'compact' : 'card';
	$mode     = (string) ( $current['mode'] ?? 'infinite' );
	$page = axismundi_act_actor_feed_display_page( $current['page'], $actor, (int) $request['per_page'], (string) $request['after'], $filter, false, false, $mode, $density );
	$response = new WP_REST_Response(
		array(
			'html'        => implode( '', $page['cards'] ),
			'next_cursor' => (string) $page['next_cursor'],
			'has_more'    => (bool) $page['has_more'],
		),
		200
	);
	/*
	 * A logged-in reader's cards carry their own Like state and REST nonces, so that response is
	 * theirs alone and must not be stored by a shared cache. An anonymous response is the same
	 * for everyone and may be cached briefly — the profile shell around it stays cacheable either
	 * way, which is the point of splitting the feed out of it.
	 */
	if ( is_user_logged_in() ) {
		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
	} else {
		$response->header( 'Cache-Control', 'public, max-age=30' );
	}
	return $response;
}

/** Register the server-rendered Actor Activity feed block. */
function axismundi_act_register_actor_activity_feed_block() : void {
	register_block_type( dirname( __DIR__ ) . '/blocks/actor-feed-loop', array( 'render_callback' => 'axismundi_act_render_actor_activity_feed' ) );
	register_block_type( dirname( __DIR__ ) . '/blocks/feed-item-templates' );
	register_block_type( dirname( __DIR__ ) . '/blocks/feed-item-template' );
	/*
	 * The chrome around the cards, as blocks an author can move or leave out.
	 *
	 * These belong to Activities and only to Activities. A Group's Community archive paginates by
	 * number over a list that can be counted and jumped around in; this feed walks a cursor down a
	 * ledger whose length nobody asks for. They are not one control with two settings, and putting
	 * a "Load more" block on the Community would be offering a reader a direction that surface does
	 * not have.
	 */
	register_block_type( dirname( __DIR__ ) . '/blocks/feed-filters' );
	register_block_type( dirname( __DIR__ ) . '/blocks/feed-pagination' );
	register_block_type( dirname( __DIR__ ) . '/blocks/feed-density-switch' );
}
add_action( 'init', 'axismundi_act_register_actor_activity_feed_block' );

/**
 * The saved card template this feed repeats, resolved the same way for both callers.
 *
 * A feed renders its first page while a template is being rendered and its later pages from a REST
 * request that has no template, no block instance, and no inner blocks. If each side worked out
 * which markup to repeat on its own, the two would agree until somebody edited one of them — and
 * the failure would show up only after "Load more", which is close to the worst place to look for
 * it. So both call this, and there is one answer.
 *
 * A saved template wins over the bundled file. The Site Editor writes its own `wp_template` post
 * and that post is what the page renders from, so reading the file would quietly serve continuation
 * cards that ignore every edit the author made.
 *
 * Which template is a property of the Actor, not of the request. The endpoint is given an Actor
 * URI and derives the rest, so nothing about the choice is taken on the client's word.
 *
 * @param Axismundi_Actor $actor Actor whose profile is being read.
 * @return string Serialized inner blocks of the feed item template, or '' when there is none.
 */
function axismundi_act_actor_feed_template_source( Axismundi_Actor $actor, string $density = 'card' ) : string {
	return axismundi_act_extract_feed_item_template( axismundi_act_actor_feed_template_blocks( $actor ), $density );
}

/**
 * The parsed profile template this Actor's feed is being read out of.
 *
 * Parsed once per Actor kind per request. Two things now read this template — which card to
 * repeat, and where the chrome around the cards goes — and parsing it twice to answer two
 * questions about the same document would be work done for nothing.
 *
 * @param Axismundi_Actor $actor Actor whose profile is being read.
 * @return array<int,array<string,mixed>>
 */
function axismundi_act_actor_feed_template_blocks( Axismundi_Actor $actor ) : array {
	static $parsed = array();
	$slug = 'Group' === $actor->get_type() ? 'actor-group-profile' : 'actor-person-profile';
	if ( isset( $parsed[ $slug ] ) ) {
		return $parsed[ $slug ];
	}

	$content = '';
	if ( function_exists( 'get_block_template' ) ) {
		$template = get_block_template( 'axismundi-actors//' . $slug, 'wp_template' );
		if ( $template instanceof WP_Block_Template && '' !== (string) $template->content ) {
			$content = (string) $template->content;
		}
	}
	if ( '' === $content && function_exists( 'axismundi_actors_profile_template_content' ) ) {
		$content = axismundi_actors_profile_template_content( $slug );
	}
	$parsed[ $slug ] = '' === $content ? array() : parse_blocks( $content );
	return $parsed[ $slug ];
}

/**
 * The order the feed's own parts appear in, as the template arranges them.
 *
 * The filters and the pagination are blocks so that an author can move them — put the filters
 * under the list, drop them entirely on a profile that only ever shows one view. What they are
 * not is self-rendering: both describe the same query the loop just ran, so the loop renders
 * them and these blocks say where.
 *
 * An empty result means the template says nothing about the arrangement, and the caller keeps
 * the fixed order the feed had before it could be arranged at all. That is what a template
 * written before these blocks existed looks like — including one already saved to the database
 * by the Site Editor — and it has to keep working, because losing this reading is losing
 * "Load more".
 *
 * @param Axismundi_Actor $actor Actor whose profile is being read.
 * @return array<int,string> Slot keys in template order, or an empty array for no opinion.
 */
function axismundi_act_actor_feed_slots( Axismundi_Actor $actor ) : array {
	return axismundi_act_feed_slots_from_blocks( axismundi_act_actor_feed_template_blocks( $actor ) );
}

/**
 * The arrangement a parsed template asks for, separate from where that template came from.
 *
 * Split out because the two halves fail differently and are worth being able to ask about
 * separately: resolving the template is about the Site Editor and the filesystem, while this is
 * about what the author arranged.
 *
 * @param array<int,array<string,mixed>> $blocks Parsed template blocks.
 * @return array<int,string> Slot keys in template order, or an empty array for no opinion.
 */
function axismundi_act_feed_slots_from_blocks( array $blocks ) : array {
	$loop = axismundi_act_find_feed_loop_block( $blocks );
	if ( null === $loop ) {
		return array();
	}
	$known = array(
		'axismundi/feed-filters'       => 'filters',
		'axismundi/feed-density-switch' => 'density',
		'axismundi/feed-item-templates' => 'list',
		'axismundi/feed-item-template'  => 'list',
		'axismundi/feed-pagination'    => 'pagination',
	);
	$slots = array();
	foreach ( (array) ( $loop['innerBlocks'] ?? array() ) as $block ) {
		$slot = $known[ (string) ( $block['blockName'] ?? '' ) ] ?? '';
		if ( '' !== $slot && ! in_array( $slot, $slots, true ) ) {
			$slots[] = $slot;
		}
	}
	// A template that names only the card is the old arrangement written out, not a request to
	// drop the chrome. It takes a chrome block to move one, and a chrome block to omit the other.
	return array( 'list' ) === $slots ? array() : $slots;
}

/**
 * Find the feed loop anywhere in a parsed template.
 *
 * @param array<int,array<string,mixed>> $blocks Parsed blocks.
 * @return array<string,mixed>|null
 */
function axismundi_act_find_feed_loop_block( array $blocks ) : ?array {
	foreach ( $blocks as $block ) {
		if ( 'axismundi/actor-feed-loop' === ( $block['blockName'] ?? '' ) ) {
			return $block;
		}
		$found = axismundi_act_find_feed_loop_block( (array) ( $block['innerBlocks'] ?? array() ) );
		if ( null !== $found ) {
			return $found;
		}
	}
	return null;
}

/**
 * Find the feed item template anywhere in a parsed template and give back its children.
 *
 * Its children rather than the block itself: what repeats is the card, and the wrapper exists only
 * so an author has something to edit. Searching recursively rather than at a fixed depth keeps the
 * lookup working when the feed is moved inside a group, a column, or whatever else the Site Editor
 * lets someone wrap it in.
 *
 * @param array<int,array<string,mixed>> $blocks Parsed blocks.
 * @return string
 */
function axismundi_act_extract_feed_item_template( array $blocks, string $density = '' ) : string {
	$found = axismundi_act_feed_item_templates( $blocks );
	if ( empty( $found['order'] ) ) {
		return '';
	}
	/*
	 * The requested density when the template has it, and otherwise the first one saved — which is
	 * also what an address with no density at all means. Falling back to the first rather than to
	 * `card` is what makes a compact-only template open compact instead of blank.
	 */
	return isset( $found['templates'][ $density ] )
		? $found['templates'][ $density ]
		: $found['templates'][ $found['order'][0] ];
}

/**
 * Every card this template holds, in the order they were saved.
 *
 * Order is the contract. The first entry is the default density, so an author changes what a
 * reader opens on by moving blocks rather than by finding a setting — and a template holding only
 * a compact card simply opens compact, with no switch offered, because there is nothing to switch
 * between.
 *
 * A duplicate density is refused rather than resolved. "First wins" is the rule for choosing among
 * *different* densities; letting it also silently decide between two cards claiming the same one
 * would turn an ordering convenience into a data model, and the second card would be unreachable
 * with nothing saying so. The audit fails on it; the renderer keeps the first so a hand-edited
 * template still draws something.
 *
 * @param array<int,array<string,mixed>> $blocks Parsed template blocks.
 * @return array{templates:array<string,string>,order:array<int,string>,duplicates:array<int,string>}
 */
function axismundi_act_feed_item_templates( array $blocks ) : array {
	$found = array( 'templates' => array(), 'order' => array(), 'duplicates' => array() );
	/*
	 * The set, and only the set. A card is read from the direct children of `feed-item-templates`
	 * and nowhere else — not from the loop's own children, which is where they used to live.
	 *
	 * That is a clean break rather than an oversight. Reading both shapes would mean a template
	 * could be half-migrated indefinitely and nothing would say which one was in force; the saved
	 * templates are being overwritten on deploy, so there is no install to carry. A template with
	 * no set renders no cards, which is loud, instead of quietly finding one somewhere else.
	 */
	$wrapper = axismundi_act_find_block_by_name( $blocks, 'axismundi/feed-item-templates' );
	if ( null === $wrapper ) {
		return $found;
	}
	foreach ( (array) ( $wrapper['innerBlocks'] ?? array() ) as $node ) {
		if ( 'axismundi/feed-item-template' !== (string) ( $node['blockName'] ?? '' ) ) {
			continue;
		}
		$density = (string) ( $node['attrs']['density'] ?? 'card' );
		$density = 'compact' === $density ? 'compact' : 'card';
		if ( isset( $found['templates'][ $density ] ) ) {
			$found['duplicates'][] = $density;
			continue;
		}
		$found['templates'][ $density ] = serialize_blocks( (array) ( $node['innerBlocks'] ?? array() ) );
		$found['order'][]               = $density;
	}
	return $found;
}

/**
 * Find the first block of one name anywhere in a parsed tree.
 *
 * @param array<int,array<string,mixed>> $blocks Parsed blocks.
 * @param string                         $name   Block name.
 * @return array<string,mixed>|null
 */
function axismundi_act_find_block_by_name( array $blocks, string $name ) : ?array {
	foreach ( $blocks as $block ) {
		if ( $name === (string) ( $block['blockName'] ?? '' ) ) {
			return $block;
		}
		$found = axismundi_act_find_block_by_name( (array) ( $block['innerBlocks'] ?? array() ), $name );
		if ( null !== $found ) {
			return $found;
		}
	}
	return null;
}

/**
 * The densities this Actor's template actually offers, in saved order.
 *
 * @param Axismundi_Actor $actor Actor whose profile is being read.
 * @return array<int,string>
 */
function axismundi_act_actor_feed_densities_available( Axismundi_Actor $actor ) : array {
	return axismundi_act_feed_item_templates( axismundi_act_actor_feed_template_blocks( $actor ) )['order'];
}

/**
 * The saved card for one density, or '' when the template does not carry that one.
 *
 * Density is an attribute on the item template rather than a second block type, because both are
 * the same thing — the card this feed repeats — differing only in how much of an entry an author
 * chose to draw. `card` is the value a template with no attribute means, so the two spellings
 * cannot disagree.
 *
 * @param array<int,array<string,mixed>> $blocks  Parsed blocks.
 * @param string                         $density Density being rendered.
 * @return string
 */
function axismundi_act_find_feed_item_template( array $blocks, string $density ) : string {
	foreach ( $blocks as $block ) {
		if ( 'axismundi/feed-item-template' === ( $block['blockName'] ?? '' ) ) {
			$saved = (string) ( $block['attrs']['density'] ?? 'card' );
			$saved = 'compact' === $saved ? 'compact' : 'card';
			if ( $saved === $density ) {
				return serialize_blocks( (array) ( $block['innerBlocks'] ?? array() ) );
			}
			continue;
		}
		$found = axismundi_act_find_feed_item_template( (array) ( $block['innerBlocks'] ?? array() ), $density );
		if ( '' !== $found ) {
			return $found;
		}
	}
	return '';
}
