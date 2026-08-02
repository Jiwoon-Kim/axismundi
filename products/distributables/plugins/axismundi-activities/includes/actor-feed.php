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

	// The payload is already at hand here, so no lookup is needed — unlike an observed Object,
	// which is why acquiring the addressing is per-kind and judging it is not.
	return axismundi_act_feed_item_with_group_context(
		array(
			'id'         => $activity->get_uri(),
			'kind'       => 'activity',
			'type'       => $type,
			'actor_uri'  => $activity->get_actor_uri(),
			'object_uri' => $object_uri,
			'published'  => is_string( $published ) ? $published : '',
			// An Announce is a boost, never a reply, so the thread graph is not consulted for one.
			'is_reply'   => 'Create' === $type && axismundi_act_actor_feed_item_is_reply( $activity, $object_uri ),
		),
		$payload
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
	// No payload here — an observed Object is known by URI alone, so its addressing is fetched
	// from whoever cached it. The stamp is the same one a ledger row gets.
	return axismundi_act_feed_item_with_group_context(
		array(
			'id'         => 'observed:' . hash( 'sha256', $object_uri ),
			'kind'       => 'observed_object',
			'type'       => 'Object',
			'actor_uri'  => $actor->get_uri(),
			'object_uri' => $object_uri,
			'published'  => false === strtotime( $published ) ? '' : $published,
		)
	);
}

/**
 * Stamp one feed descriptor with the Groups its Object was posted into.
 *
 * Getting the addressing and judging it are separate problems, and only the first differs by kind.
 * A ledger row carries its payload; an observed Object is a cache-miss fallback that carries a URI
 * and nothing else, so its addressing has to be fetched from whoever cached it. Judging is then one
 * function over one descriptor, which is the point — a Person's two surfaces are complements, and
 * if the two kinds were classified separately they would disagree at exactly the row that decides
 * which tab it belongs to.
 *
 * Remote Objects reach a profile mostly through the observed path, so classifying only ledger rows
 * would leave a Lemmy Person's community posts either missing from Community or leaking into
 * Activity — and only on the first page, since that is the only place observed Objects join.
 *
 * @param array<string,mixed> $item    Feed item descriptor.
 * @param array<string,mixed> $payload Payload already at hand, if any.
 * @return array<string,mixed>
 */
function axismundi_act_feed_item_with_group_context( array $item, array $payload = array() ) : array {
	$object_uri = (string) ( $item['object_uri'] ?? '' );
	if ( empty( $payload ) && '' !== $object_uri ) {
		/**
		 * Supply the stored payload for an Object this feed knows only by URI.
		 *
		 * Activities does not own an Object cache and must not read one directly; the product that
		 * stored the Object answers, and an unanswered URI simply has no Group context rather than
		 * being guessed at.
		 *
		 * @param array<string,mixed> $payload    Empty by default.
		 * @param string              $object_uri Canonical Object URI.
		 */
		$payload = (array) apply_filters( 'axismundi_act_feed_object_payload', array(), $object_uri );
	}
	$item['group_context'] = function_exists( 'axismundi_act_group_context' )
		? axismundi_act_group_context( $payload, $object_uri )
		: array( 'has_group_context' => false, 'group_uris' => array(), 'primary_group_uri' => '' );
	return $item;
}

/**
 * Whether one stamped descriptor belongs in a feed selecting by Group context.
 *
 * @param array<string,mixed> $item Feed item descriptor.
 * @param string              $mode `out`, `in`, or `both`.
 * @return bool
 */
/**
 * The Actor whose identity the card header should name, when it is not the author.
 *
 * Empty for every surface that shows a chronology of one Actor's own acts, which is the ordinary
 * case: the header names whoever wrote the Object, and nothing needs to say so.
 *
 * `audience` uses the classifier's `primary_group_uri` and nothing else. When an entry is
 * addressed to several communities and no single one is primary, this returns empty and the
 * header falls back to the author — picking the first Group would name a community the entry may
 * only incidentally touch, and showing the wrong Group is worse than showing the author, who is
 * at least always correct.
 *
 * @param array<string,mixed> $item   Feed item descriptor.
 * @param string              $source `object` or `audience`, from the surface.
 * @return string Actor URI, or empty for the Object's own author.
 */
function axismundi_act_feed_item_header_actor( array $item, string $source = 'object' ) : string {
	if ( 'audience' !== $source ) {
		return '';
	}
	return (string) ( $item['group_context']['primary_group_uri'] ?? '' );
}

/**
 * Why this entry is in this list, when there is anything to say.
 *
 * Null far more often than not, and that is the point: a Create on a personal timeline is the
 * ordinary case and has no story, so it gets no descriptor rather than an empty one. A block
 * reading "is there a status" would otherwise have to distinguish absent from blank, and every
 * card would carry a row that renders nothing.
 *
 * Only Activities can answer this — it owns the verb and the surface policy — and only Object
 * Projections should draw it, which is why this returns a descriptor rather than markup. The
 * "Boosted" line used to be built here as HTML, which put an Activities decision at a fixed
 * position inside a card whose layout belongs to the template.
 *
 * @param array<string,mixed> $item           Feed item descriptor.
 * @param string              $announce_frame `show` or `hide`, from the surface.
 * @return array<string,mixed>|null
 */
function axismundi_act_feed_item_status( array $item, string $announce_frame = 'show' ) : ?array {
	if ( 'Announce' !== (string) ( $item['type'] ?? '' ) || 'show' !== $announce_frame ) {
		return null;
	}
	return array(
		'kind'      => 'announce',
		'actor_uri' => (string) ( $item['actor_uri'] ?? '' ),
	);
}

function axismundi_act_feed_item_in_group_context( array $item, string $mode ) : bool {
	if ( ! function_exists( 'axismundi_act_group_context_admits' ) ) {
		return true;
	}
	return axismundi_act_group_context_admits( $mode, ! empty( $item['group_context']['has_group_context'] ) );
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
	/*
	 * Which side of Group context this surface takes, read once and applied to both item kinds.
	 *
	 * Ledger rows and observed Objects arrive by different routes and join in one list, so the
	 * question has to be asked of them in the same place with the same answer. Asking it only of
	 * the ledger would sort a Lemmy Person's community posts wrongly — and only on the first page,
	 * because that is the only page observed Objects join, which is the worst way for it to be
	 * wrong: the profile would disagree with itself after "Load more".
	 */
	$surfaces            = axismundi_act_actor_profile_surfaces( $actor );
	$group_context_mode  = (string) ( $surfaces[ $surface ]['group_context'] ?? 'both' );
	/*
	 * Whether this surface explains an Announce, resolved here for the same reason.
	 *
	 * The policy belongs to the surface and the descriptor belongs to the entry, so the surface is
	 * asked once and every entry on the page — ledger row or observed Object — carries its own
	 * answer from then on. The alternative is asking again at render time, where the REST
	 * continuation would have to be told the surface a second time and could be told a different
	 * one than the page it is continuing.
	 */
	$announce_frame = (string) ( $surfaces[ $surface ]['announce_frame'] ?? 'show' );
	$header_source = (string) ( $surfaces[ $surface ]['header_actor_source'] ?? 'object' );
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
			if ( is_array( $item ) && axismundi_act_actor_feed_item_in_filter( $item, $filter ) && axismundi_act_feed_item_in_group_context( $item, $group_context_mode ) ) {
				$item['cursor'] = $last;
				$item['status'] = axismundi_act_feed_item_status( $item, $announce_frame );
				$item['header_actor'] = axismundi_act_feed_item_header_actor( $item, $header_source );
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
			if ( is_array( $normalized ) && axismundi_act_actor_feed_item_in_filter( $normalized, $filter ) && axismundi_act_feed_item_in_group_context( $normalized, $group_context_mode ) ) {
				$normalized['cursor'] = '';
				$normalized['status'] = axismundi_act_feed_item_status( $normalized, $announce_frame );
				$normalized['header_actor'] = axismundi_act_feed_item_header_actor( $normalized, $header_source );
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
 * @param string            $surface       Surface being rendered, so the card comes from that surface's layout.
 * @return array{cards:array<int,string>,head_cursor:string,next_cursor:string,has_more:bool}
 */
function axismundi_act_actor_feed_display_page( callable $page_callback, Axismundi_Actor $actor, int $limit, string $cursor, string $filter, bool $inclusive = false, bool $head_window = false, string $mode = 'infinite', string $density = 'card', string $surface = '' ) : array {
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
		? axismundi_act_actor_feed_template_source( $actor, $density, $surface )
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
		'card'    => array( 'label' => __( 'Card view', 'axismundi-activities' ), 'icon' => 'view_stream' ),
		'compact' => array( 'label' => __( 'List view', 'axismundi-activities' ), 'icon' => 'view_list' ),
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
 * What the author declared on the tab for the surface being rendered.
 *
 * Two of the feed's decisions are per-surface rather than per-profile: how the collection is
 * walked, and which shape the filter control takes. A timeline is continued by cursor and a
 * community archive is browsed by number — one value held by the feed could only ever be right for
 * one of them.
 *
 * So they live on `feed-tab`, which already owns one surface's layout, and the server reads them
 * back out of the tab it is rendering rather than off the feed block that contains every tab.
 *
 * @param Axismundi_Actor $actor   Profile Actor.
 * @param string          $surface Surface being rendered.
 * @return array<string,mixed>
 */
function axismundi_act_feed_tab_attributes( Axismundi_Actor $actor, string $surface ) : array {
	$tabs = axismundi_act_find_block_by_name( axismundi_act_actor_feed_template_blocks( $actor ), 'axismundi/feed-tabs' );
	if ( null === $tabs ) {
		return array();
	}
	foreach ( (array) ( $tabs['innerBlocks'] ?? array() ) as $tab ) {
		if ( 'axismundi/feed-tab' === (string) ( $tab['blockName'] ?? '' )
			&& $surface === (string) ( $tab['attrs']['surface'] ?? 'activity' )
		) {
			return (array) ( $tab['attrs'] ?? array() );
		}
	}
	return array();
}

/**
 * How this surface is walked: what the tab asked for, when the surface can serve it.
 *
 * A sibling of the filter-style policy and typed the same way on purpose. Both consume the same
 * tab declaration, so both should fail the same way when it is not passed — an inline
 * `$tab_attrs['navigation'] ?? ''` swallows an unassigned variable and silently reports that the
 * author declared nothing, which is how the declaration reached the editor and not the page.
 *
 * A free choice would let a template ask a numbered archive to be read with a cursor it does not
 * have, so the surface's declared modes are the bound and its own default is the answer to
 * anything outside them.
 *
 * @param array<string,mixed> $surface        Surface descriptor.
 * @param array<string,mixed> $tab_attributes Attributes of the tab being rendered.
 * @return string
 */
function axismundi_act_feed_navigation_mode( array $surface, array $tab_attributes ) : string {
	$supported = (array) ( $surface['modes'] ?? array() );
	$requested = (string) ( $tab_attributes['navigation'] ?? '' );
	return in_array( $requested, $supported, true ) ? $requested : (string) ( $surface['mode'] ?? 'infinite' );
}

/** Whether this surface's filters are reader-owned switches instead of addressable tabs. */
function axismundi_act_feed_filters_are_client_owned( array $surface, array $tab_attributes ) : bool {
	$filter_style = (string) ( $tab_attributes['filterStyle'] ?? '' );
	if ( 'tabs' === $filter_style ) {
		return false;
	}
	if ( 'switches' === $filter_style ) {
		return true;
	}
	return ! empty( $surface['toggles'] );
}

/** Render the current Actor's public Activity feed. */
function axismundi_act_render_actor_activity_feed( array $attributes = array() ) : string {
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
		wp_enqueue_script_module( 'axismundi-feed-view-script-module' );
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
	$tab_attrs = axismundi_act_feed_tab_attributes( $actor, $surface );
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
	/*
	 * Which shape the filter control takes: declared by the author, defaulted from the surface.
	 *
	 * It used to be derived here and nowhere else, which meant the editor could not know it —
	 * whether a surface offers switches depends on the Actor being viewed, and an author placing
	 * the block was shown a guess that was wrong for a Person's community tab. A declaration is
	 * something both ends read, so the preview and the page agree by construction instead of by
	 * two predicates staying in step.
	 *
	 * Unset keeps the old behaviour exactly, so every template saved before this reads the same.
	 */
	$client_owned = axismundi_act_feed_filters_are_client_owned( $current, $tab_attrs );
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
	 * The template's choice when the source can serve it, and the source's answer when it cannot.
	 *
	 * The loop owns this because it decides how the list is queried and continued, not how a
	 * button looks. But a free choice would let a template ask a numbered archive to be read with
	 * a cursor it does not have, so the surface's declared modes are the bound.
	 */
	$mode = axismundi_act_feed_navigation_mode( $current, $tab_attrs );
	$densities_available = axismundi_act_actor_feed_densities_available( $actor, $surface );
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
	$page  = axismundi_act_actor_feed_display_page( $current['page'], $actor, $per_page, $cursor, $filter, false, false, $mode, $density, $surface );
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
	$filter_state = $client_owned ? (array) ( axismundi_act_actor_feed_filters()[ $filter ] ?? array() ) : array();

	/*
	 * How far along this page is, as a model rather than as a control.
	 *
	 * The feed knows the position and how the collection is walked; what a reader presses to
	 * walk it is the pager's, and the two shapes it can take are genuinely different controls.
	 */
	$page_model = array(
		'page'       => (int) ( $page['page'] ?? 1 ),
		'pages'      => (int) ( $page['pages'] ?? 1 ),
		'hasMore'    => ! empty( $page['has_more'] ),
		'nextCursor' => (string) ( $page['next_cursor'] ?? '' ),
		'cursor'     => $cursor,
	);

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
		'density'       => $density,
		'cursor'        => ! empty( $page_model['hasMore'] ) && '' !== $page_model['nextCursor'] ? $page_model['nextCursor'] : '',
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
	 * The saved layout renders itself, in its own order.
	 *
	 * The parts the feed builds — its filters, its density switch, its pager — are handed to the
	 * blocks that mark where they go, and the loop builds the list. There is no assembly step
	 * left, because the arrangement is the block tree rather than a slot list derived from it.
	 */
	$body = axismundi_act_render_feed_body(
		axismundi_act_feed_surface_blocks( axismundi_act_actor_feed_template_blocks( $actor ), $surface ),
		array(
			/*
			 * Child blocks receive the feed model, not finished chrome. `density` is the reader's
			 * current choice and rides on links; `filters` and `toggles` describe the choices that
			 * the filters block renders where the template placed it.
			 */
			'filters'        => (array) $current['filters'],
			'toggles'        => (array) ( $current['toggles'] ?? array() ),
			'filterState'    => $filter_state,
			'clientOwned'    => $client_owned,
			'densities'      => $densities_available,
			'baseUrl'        => $base_url,
			'surface'        => $surface,
			'filter'         => $filter,
			'defaultFilter'  => (string) $current['default_filter'],
			'density'        => $density,
			'navigation'     => $mode,
			'page'           => $page_model,
			'cards'        => $cards,
			'announceHost' => $announce_host,
			'reactionHost' => $reaction_host,
		)
	);

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
 * One address for a feed, built from the feed's own state rather than from a closure.
 *
 * A pure function over the context so the blocks can address the feed without being handed a
 * callable: context stays data, which is what survives being serialized, inspected, or read by a
 * block rendering on a request the feed did not build.
 *
 * The surface and the filter ride on every link, so following a pager does not quietly reset
 * which entries are shown, and each is left out when it already is the default so an ordinary
 * address stays short. The density is not added here: the base address already carries it under
 * the same rule, and stating one rule in two places is how the two copies start disagreeing.
 *
 * @param array<string,mixed>  $context Feed context.
 * @param array<string,string> $query   Extra arguments, such as a page or a cursor.
 * @return string
 */
function axismundi_act_feed_url( array $context, array $query = array(), array $drop = array() ) : string {
	$url     = (string) ( $context['baseUrl'] ?? '' );
	/*
	 * A caller may drop an argument the base address carries.
	 *
	 * Only the density switch needs this, and only for its first segment: the default density is
	 * addressed by leaving the argument out entirely, so a link to it has to be able to remove
	 * what the reader's current address is carrying.
	 */
	if ( array() !== $drop ) {
		$url = remove_query_arg( $drop, $url );
	}
	$surface = (string) ( $context['surface'] ?? 'activity' );
	if ( 'activity' !== $surface ) {
		$url = add_query_arg( 'view', $surface, $url );
	}
	if ( (string) ( $context['filter'] ?? '' ) !== (string) ( $context['defaultFilter'] ?? '' ) ) {
		$url = add_query_arg( 'filter', (string) $context['filter'], $url );
	}
	foreach ( $query as $key => $value ) {
		$url = add_query_arg( $key, rawurlencode( (string) $value ), $url );
	}
	return $url;
}

/**
 * The density switch: two links that read as one control.
 *
 * A connected pair of buttons, in the classes the theme's button group already uses, because that
 * is what this is — two segments of one choice rather than two separate calls to action. The
 * markup follows the core button convention while the block draws it itself: this is a dynamic
 * block, so no `core/button` is ever parsed on the page and a style that waited for one would
 * never load.
 *
 * Both segments are outlined; the current one is filled. That is the group's own contract rather
 * than a call-to-action reading of the outline style: in a segmented control, outlined is the
 * resting state of every segment and selection is shown by the secondary-container fill, whatever
 * the base variant. Styling the unselected half as something quieter than outlined would make the
 * pair read as one button and one hint.
 *
 * `aria-current="page"` and not `aria-pressed`: these are links, and following one navigates to a
 * different address rather than toggling a state on this one. An icon with the name behind it,
 * not instead of it — the glyph makes the two choices readable at a glance, and the name stays
 * available to a screen reader and to a hover.
 *
 * @param array<string,mixed> $attributes Block attributes.
 * @param string              $content    Inner blocks, of which there are none.
 * @param WP_Block|null       $block      Block instance carrying the feed context.
 * @return string
 */
function axismundi_act_render_feed_density_switch_block( array $attributes = array(), string $content = '', $block = null ) : string {
	unset( $attributes, $content );
	$context   = is_object( $block ) && isset( $block->context['axismundi/feed'] ) ? (array) $block->context['axismundi/feed'] : array();
	$available = array_values( (array) ( $context['densities'] ?? array() ) );
	/*
	 * No switch when there is nothing to switch between. A template holding one card is a template
	 * whose author decided how this feed reads, and offering a control with a single option would
	 * present that decision as a question.
	 */
	if ( count( $available ) < 2 ) {
		return '';
	}
	$current  = (string) ( $context['density'] ?? '' );
	$labels   = axismundi_act_feed_densities();
	$segments = array();
	foreach ( $available as $index => $key ) {
		$is_current = (string) $key === $current;
		/*
		 * The default is addressed by leaving `density` out, and the default is whichever card was
		 * saved first. So re-ordering the templates moves the plain address onto the new default,
		 * while an old `?density=card` link keeps opening card explicitly — which is what it said.
		 */
		$href  = 0 === (int) $index
			? axismundi_act_feed_url( $context, array(), array( 'density' ) )
			: axismundi_act_feed_url( $context, array( 'density' => (string) $key ), array( 'density' ) );
		$label = (string) ( $labels[ $key ]['label'] ?? $key );
		$segments[] = '<div class="wp-block-button is-style-outline' . ( $is_current ? ' is-current' : '' ) . '">'
			. '<a class="wp-block-button__link wp-element-button axismundi-feed-density-switch__link"'
			. ' href="' . esc_url( $href ) . '"' . ( $is_current ? ' aria-current="page"' : '' )
			. ' title="' . esc_attr( $label ) . '">'
			. '<span class="material-symbols-outlined" aria-hidden="true">' . esc_html( (string) ( $labels[ $key ]['icon'] ?? 'view_stream' ) ) . '</span>'
			. '<span class="screen-reader-text">' . esc_html( $label ) . '</span>'
			. '</a></div>';
	}
	return '<nav class="axismundi-feed-density-switch" aria-label="'
		. esc_attr__( 'Entry density', 'axismundi-activities' ) . '">'
		. '<div class="wp-block-buttons is-style-connected">' . implode( '', $segments ) . '</div>'
		. '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Links and labels are escaped above.
}

/**
 * The pager, in whichever shape this surface is walked by.
 *
 * Both shapes live here rather than being chosen by the feed and handed over as finished markup.
 * How a collection is walked is the feed's decision and is already in the context; what a reader
 * presses in order to walk it is this block's, and the two are genuinely different controls — one
 * navigates, one appends.
 *
 * @param array<string,mixed> $attributes Block attributes.
 * @param string              $content    Inner blocks, of which there are none.
 * @param WP_Block|null       $block      Block instance carrying the feed context.
 * @return string
 */
function axismundi_act_render_feed_pagination_block( array $attributes = array(), string $content = '', $block = null ) : string {
	unset( $attributes, $content );
	$context = is_object( $block ) && isset( $block->context['axismundi/feed'] ) ? (array) $block->context['axismundi/feed'] : array();
	if ( array() === $context ) {
		return '';
	}
	$page = (array) ( $context['page'] ?? array() );
	return 'pagination' === (string) ( $context['navigation'] ?? 'infinite' )
		? axismundi_act_feed_numbered_pager( $context, $page )
		: axismundi_act_feed_cursor_pager( $context, $page );
}

/**
 * A numbered pager, in the markup the theme already styles.
 *
 * `core/query-pagination`'s class contract, because this is the same control answering the same
 * question and a reader should not be able to tell which block drew it. Plain links with no
 * runtime behaviour: this surface changes page by navigating, which is the whole reason its
 * position is a number and not a cursor. It works with no script, and the address reproduces
 * exactly what the reader is looking at.
 *
 * Its own spacing is not set here. Where the pager sits, and what it is wrapped in, belongs to the
 * template that placed it.
 *
 * @param array<string,mixed> $context Feed context.
 * @param array<string,mixed> $page    Page model.
 * @return string
 */
function axismundi_act_feed_numbered_pager( array $context, array $page ) : string {
	$current = max( 1, (int) ( $page['page'] ?? 1 ) );
	$total   = max( 1, (int) ( $page['pages'] ?? 1 ) );
	if ( $total < 2 ) {
		return '';
	}
	$link = static function ( int $target, string $class, string $label ) use ( $context ) : string {
		return '<a class="wp-block-query-pagination-' . esc_attr( $class ) . ' axismundi-feed-pagination__' . esc_attr( $class ) . '"'
			. ' href="' . esc_url( axismundi_act_feed_url( $context, array( 'page' => (string) $target ) ) ) . '">'
			. esc_html( $label ) . '</a>';
	};
	return '<nav class="wp-block-query-pagination axismundi-feed-pagination is-navigation-pagination" aria-label="'
		. esc_attr__( 'Archive pages', 'axismundi-activities' ) . '">'
		. ( $current > 1 ? $link( $current - 1, 'previous', __( 'Newer', 'axismundi-activities' ) ) : '' )
		/* translators: 1: current page number, 2: total page count. */
		. '<span class="wp-block-query-pagination-numbers axismundi-feed-pagination__numbers">' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'axismundi-activities' ), $current, $total ) ) . '</span>'
		. ( $current < $total ? $link( $current + 1, 'next', __( 'Older', 'axismundi-activities' ) ) : '' )
		. '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Links and labels are escaped above.
}

/**
 * A cursor pager: one control that continues the feed in place.
 *
 * Load more is a real link before it is anything else. With no script it navigates to the next
 * cursor page, so the whole feed stays reachable by a reader without script and by a crawler; with
 * script the same element fetches that page and appends it once.
 *
 * The control is always present and hidden when there is nothing further, rather than removed and
 * rebuilt: changing the filters replaces the list from page one, and a link that had been deleted
 * would have to be recreated at exactly the right moment, while one that is only hidden reappears.
 *
 * @param array<string,mixed> $context Feed context.
 * @param array<string,mixed> $page    Page model.
 * @return string
 */
function axismundi_act_feed_cursor_pager( array $context, array $page ) : string {
	$has_more = ! empty( $page['hasMore'] ) && '' !== (string) ( $page['nextCursor'] ?? '' );
	$newer    = '';
	if ( '' !== (string) ( $page['cursor'] ?? '' ) ) {
		// A cursor names where the next page starts, not where the reader came from, so there is no
		// honest "previous". The top is the one position always known to be real.
		$newer = '<a class="axismundi-feed-pagination__newer axismundi-activity-feed__newer-link" href="'
			. esc_url( axismundi_act_feed_url( $context ) ) . '">'
			. esc_html__( 'Back to the newest activity', 'axismundi-activities' ) . '</a>';
	}
	/*
	 * The busy state is drawn rather than borrowed from an icon font.
	 *
	 * A circular indeterminate indicator is a shape and a motion, and `progress_activity` is a
	 * static glyph that would need both put back on top of it. Indeterminate and not a value: the
	 * feed does not know how much of the next page has arrived, and an indicator that filled at a
	 * rate nobody measured would be claiming it did.
	 */
	$busy = '<span class="axismundi-feed-pagination__loading" aria-hidden="true">'
		. '<svg viewBox="0 0 48 48" focusable="false" aria-hidden="true">'
		. '<circle cx="24" cy="24" r="20" fill="none" stroke-width="4" pathLength="100" />'
		. '</svg></span>';
	$more = '<a class="axismundi-feed-pagination__more axismundi-activity-feed__more-link"'
		. ' data-wp-on--click="actions.loadMore"'
		/*
		 * Busy and unavailable are two different statements and the control needs both.
		 *
		 * A second press while a page is in flight is already refused by the runtime, which returns
		 * early on `isPending` — so nothing is appended twice. But that refusal is silent: without
		 * `aria-disabled` the control still presents itself as pressable, and a reader who cannot see
		 * the indicator has no way to know why nothing happened. `disabled` is not an option here
		 * because this is an anchor, where the attribute means nothing at all.
		 */
		. ' data-wp-bind--aria-busy="context.isPending"'
		. ' data-wp-bind--aria-disabled="context.isPending"'
		. ( $has_more ? '' : ' hidden' )
		. ' href="' . esc_url( axismundi_act_feed_url( $context, array( 'feed_after' => (string) ( $page['nextCursor'] ?? '' ) ) ) ) . '">'
		. $busy
		. '<span class="axismundi-feed-pagination__more-label">' . esc_html__( 'Load more', 'axismundi-activities' ) . '</span>'
		. '</a>';
	return '<nav class="axismundi-feed-pagination axismundi-activity-feed__pagination is-navigation-infinite" aria-label="'
		. esc_attr__( 'Timeline pages', 'axismundi-activities' ) . '">' . $newer . $more . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Links and labels are escaped above.
}

/** The filter control, where the template placed it. */
function axismundi_act_render_feed_filters_block( array $attributes = array(), string $content = '', $block = null ) : string {
	unset( $attributes, $content );
	$context = is_object( $block ) && isset( $block->context['axismundi/feed'] ) ? (array) $block->context['axismundi/feed'] : array();
	$filters = (array) ( $context['filters'] ?? array() );
	if ( count( $filters ) < 2 ) {
		return '';
	}
	$current = (string) ( $context['filter'] ?? '' );
	if ( ! empty( $context['clientOwned'] ) ) {
		$switches = array();
		$state    = (array) ( $context['filterState'] ?? array() );
		foreach ( (array) ( $context['toggles'] ?? array() ) as $bit => $label ) {
			$switches[] = '<label class="axismundi-switch axismundi-activity-feed__switch">'
				. '<input class="axismundi-switch__input" type="checkbox" role="switch"'
				. ' name="' . esc_attr( (string) $bit ) . '"'
				. checked( ! empty( $state[ $bit ] ), true, false )
				. ' data-wp-on--change="actions.setFilter">'
				. '<span class="axismundi-switch__track" aria-hidden="true"></span>'
				. '<span class="axismundi-switch__label">' . esc_html( (string) $label ) . '</span>'
				. '</label>';
		}
		return '<div class="axismundi-activity-feed__filters" hidden'
			. ' data-wp-init="callbacks.watchFilters" data-wp-watch="callbacks.filtersLifecycle">'
			. '<button type="button" class="axismundi-activity-feed__filters-trigger"'
			. ' data-wp-on--click="actions.toggleFilters"'
			. ' data-wp-bind--aria-expanded="context.isFiltersOpen"'
			. ' aria-haspopup="dialog">'
			. '<span data-wp-text="context.filterLabel">' . esc_html( (string) ( $filters[ $current ] ?? '' ) ) . '</span>'
			. '<span class="material-symbols-outlined" aria-hidden="true" data-wp-bind--hidden="context.isFiltersOpen">arrow_drop_down</span>'
			. '<span class="material-symbols-outlined" aria-hidden="true" hidden data-wp-bind--hidden="!context.isFiltersOpen">arrow_drop_up</span>'
			. '</button>'
			. '<div class="axismundi-activity-feed__filters-panel" role="dialog"'
			. ' aria-label="' . esc_attr__( 'Timeline filters', 'axismundi-activities' ) . '"'
			. ' hidden data-wp-bind--hidden="!context.isFiltersOpen">'
			. implode( '', $switches )
			. '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Labels are escaped above.
	}
	$tabs = array();
	foreach ( $filters as $key => $label ) {
		$target            = $context;
		$target['filter']  = (string) $key;
		$is_current         = (string) $key === $current;
		$tabs[] = '<a class="axismundi-feed-filters__view' . ( $is_current ? ' is-current' : '' ) . '" href="'
			. esc_url( axismundi_act_feed_url( $target ) ) . '"' . ( $is_current ? ' aria-current="page"' : '' ) . '>'
			. esc_html( (string) $label ) . '</a>';
	}
	return '<nav class="axismundi-feed-filters__views" aria-label="'
		. esc_attr__( 'Timeline views', 'axismundi-activities' ) . '">' . implode( '', $tabs ) . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Links and labels are escaped above.
}

/**
 * Render the current surface's saved layout, in the order it was saved in.
 *
 * The arrangement is the block tree now. It used to be read out of the tree into a list of slot
 * keys which the feed then assembled, so moving a control meant the same fact was recorded twice —
 * once by the author moving a block and once by a helper deriving the order again.
 *
 * A template with no loop still gets one appended: the runtime finds the container it appends to
 * by looking for it, so a template that omitted the list would be a feed that cannot be paged.
 *
 * @param array<int,array<string,mixed>> $blocks  Blocks of the surface being rendered.
 * @param array<string,mixed>            $context Feed context for the children.
 * @return string
 */
function axismundi_act_render_feed_body( array $blocks, array $context ) : string {
	$children = axismundi_act_feed_surface_children( $blocks );
	$has_loop = null !== axismundi_act_find_block_by_name( $children, 'axismundi/feed-loop' );
	if ( ! $has_loop ) {
		$children[] = array( 'blockName' => 'axismundi/feed-loop', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
	}
	$body = '';
	foreach ( $children as $child ) {
		if ( '' === (string) ( $child['blockName'] ?? '' ) ) {
			continue;
		}
		$body .= ( new WP_Block( $child, array( 'axismundi/feed' => $context ) ) )->render();
	}
	return $body;
}

/**
 * The blocks that make up one surface's layout.
 *
 * The surface's own children when the template has tabs, and the feed's children when it does not —
 * a template written before tabs existed keeps its single arrangement, and one written before the
 * chrome blocks existed has only a loop, which still renders.
 *
 * @param array<int,array<string,mixed>> $blocks Blocks of the surface being rendered.
 * @return array<int,array<string,mixed>>
 */
function axismundi_act_feed_surface_children( array $blocks ) : array {
	$feed = axismundi_act_find_block_by_name( $blocks, 'axismundi/feed' );
	return null === $feed ? $blocks : (array) ( $feed['innerBlocks'] ?? array() );
}

/**
 * Repeat the feed's entries — the block that actually loops.
 *
 * The list markup lived in the feed's own renderer, which is why the block holding the cards was
 * called `feed-item-templates` and did nothing at render time. It renders now, and the feed hands
 * it what it needs through block context rather than assembling the list and dropping it into a
 * slot the loop merely marked the position of.
 *
 * The list is always emitted, even when this page is empty: appended pages need something to
 * attach to, so an empty feed says so in its own row rather than by omitting the container the
 * runtime is going to look for.
 *
 * What is deliberately *not* here is rendering each Object into a card. That happens where the
 * page is scanned, because the scan decides how many ledger rows to consume — a row whose Object
 * has vanished renders nothing and must not count against the page size. Moving the card render
 * here would separate that decision from its evidence and quietly shorten pages.
 *
 * @param array<string,mixed> $attributes Block attributes.
 * @param string              $content    Inner blocks, which are the item templates and render nothing.
 * @param WP_Block|null       $block      Block instance carrying the feed context.
 * @return string
 */
function axismundi_act_render_feed_loop_block( array $attributes = array(), string $content = '', $block = null ) : string {
	unset( $attributes, $content );
	$context = is_object( $block ) && isset( $block->context['axismundi/feed'] ) ? (array) $block->context['axismundi/feed'] : array();
	$cards   = (string) ( $context['cards'] ?? '' );
	return '<ol class="axismundi-activity-feed__list" data-wp-init="callbacks.watchFeed">'
		. ( '' === $cards ? '<li class="axismundi-activity-feed__empty">' . esc_html__( 'Nothing to show in this view.', 'axismundi-activities' ) . '</li>' : $cards )
		. '</ol>'
		. (string) ( $context['announceHost'] ?? '' )
		. (string) ( $context['reactionHost'] ?? '' )
		. '<p class="axismundi-activity-feed__status" data-wp-text="context.error" role="status"></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Cards and hosts are escaped by their own renderers.
}

/**
 * Render the loop the current surface saved, through the block rather than around it.
 *
 * The parsed node is found in the surface's own subtree and rendered as a `WP_Block` with the
 * feed's context attached. A `do_blocks()` on serialized markup would lose that context and the
 * loop would render an empty list — which is exactly how a responsibility move gets reverted by
 * accident, since an empty feed and a feed with nothing to show look the same.
 *
 * @param array<int,array<string,mixed>> $blocks  Blocks of the surface being rendered.
 * @param array<string,mixed>            $context Feed context for the loop.
 * @return string
 */
function axismundi_act_render_feed_loop( array $blocks, array $context ) : string {
	$node = axismundi_act_find_block_by_name( $blocks, 'axismundi/feed-loop' );
	if ( null === $node ) {
		// A template with no loop still gets one. The runtime finds the continuation container by
		// looking for it, so a missing list is a feed that cannot be paged rather than a feed
		// without cards.
		$node = array( 'blockName' => 'axismundi/feed-loop', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() );
	}
	return ( new WP_Block( $node, array( 'axismundi/feed' => $context ) ) )->render();
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
		// Ledger-selected: this surface is everything the Actor did *outside* a community.
		'group_context'  => 'out',
		// A boost is an act this Actor performed, so their timeline says so; the header keeps naming
		// whoever wrote the Object.
		'header_actor_source' => 'object',
		'announce_frame'      => 'show',
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
	$own = array( 'activity' => $activity );
	/*
	 * The other side of the same ledger.
	 *
	 * A Person's community contributions are not a second collection held somewhere else: they are
	 * the entries of this same outbox that were addressed to a community, which is why both
	 * surfaces read the same page function and differ only in which side of `group_context` they
	 * take. It used to be registered by the forum, and that made "did this person write in a
	 * community" a question only a forum could answer — untrue the moment a remote Person's Object
	 * arrives carrying `audience`, which is how Lemmy states the same fact and how any threadiverse
	 * peer will.
	 *
	 * A Group gets none. Its profile already is the community, so a "community contributions" tab
	 * there would point at itself.
	 */
	if ( 'Group' !== $actor->get_type() ) {
		$own['community'] = array(
			'label'          => __( 'Community', 'axismundi-activities' ),
			'heading'        => __( 'Community contributions', 'axismundi-activities' ),
			'filters'        => $filters,
			'toggles'        => $activity['toggles'],
			/*
			 * Replies included by default, unlike the timeline. A reply reads as a fragment on a
			 * personal chronology, which is why it is hidden there; in a community it is most of
			 * what contributing means, and hiding it would leave this surface mostly empty for
			 * exactly the people who use communities most.
			 */
			'default_filter' => 'all',
			'group_context'  => 'in',
			/*
			 * The Group, not the Person, on every row of this surface.
			 *
			 * The page is already a Person's profile, so repeating their avatar and handle on each
			 * entry says nothing a reader did not know; which community it went to is the thing
			 * they came here to see. And nothing here needs the boost line: this surface is defined
			 * by where an entry was addressed, not by what its author did with it.
			 */
			'header_actor_source' => 'audience',
			'announce_frame'      => 'hide',
			'page'           => 'axismundi_act_actor_community_surface_page',
			'mode'           => 'infinite',
		);
	}
	$surfaces = (array) apply_filters( 'axismundi_act_actor_profile_surfaces', $own, $actor );
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
			array( 'modes' => array(), 'mode' => 'infinite', 'filters' => array(), 'default_filter' => '', 'heading' => '', 'label' => (string) $key, 'header_actor_source' => 'object', 'announce_frame' => 'show' ),
			$definition
		);
		/*
		 * What a surface *can* be walked as, which is not the same as what a template asked for.
		 *
		 * A source declares the modes it can honour: a ledger with no total cannot answer "page 7
		 * of 12", and a numbered archive has no cursor to continue from. The template then picks
		 * among those, and picking something absent from the list is not a preference to respect —
		 * it is a request the source cannot serve, so the first supported mode stands.
		 *
		 * A surface that declares nothing is taken to support only the single mode it named, which
		 * is what every surface written before this meant.
		 */
		$declared = array_values( array_intersect( array( 'infinite', 'pagination' ), (array) $surfaces[ $key ]['modes'] ) );
		if ( empty( $declared ) ) {
			$declared = array( 'pagination' === ( $definition['mode'] ?? '' ) ? 'pagination' : 'infinite' );
		}
		$surfaces[ $key ]['modes'] = $declared;
		$surfaces[ $key ]['mode']  = in_array( (string) $surfaces[ $key ]['mode'], $declared, true )
			? (string) $surfaces[ $key ]['mode']
			: $declared[0];
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
 * The same ledger, read for what this Person addressed to a community.
 *
 * Identical to the timeline apart from the surface key, because the difference between them is a
 * property of the entries and not of how they are fetched: the surface descriptor names the side
 * of `group_context` to take, and one selection applies it to both item kinds.
 */
function axismundi_act_actor_community_surface_page( Axismundi_Actor $actor, int $limit, string $cursor, string $filter, bool $inclusive = false, bool $head_window = false ) : array {
	return axismundi_act_actor_feed_page( $actor, $limit, $cursor, $filter, 'community', $inclusive, $head_window );
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
	/*
	 * No frame here any more. The boost line is a block inside the card now, placed wherever the
	 * template puts `object-status`, and fed by the descriptor this item carries.
	 */
	return '<li class="axismundi-activity-feed__item axismundi-activity-feed__item--object axismundi-activity-feed__item--' . esc_attr( strtolower( (string) ( $item['type'] ?? '' ) ) ) . '">'
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
	$page = axismundi_act_actor_feed_display_page( $current['page'], $actor, (int) $request['per_page'], (string) $request['after'], $filter, false, false, $mode, $density, $surface );
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
	register_block_type( dirname( __DIR__ ) . '/blocks/feed', array( 'render_callback' => 'axismundi_act_render_actor_activity_feed' ) );
	register_block_type( dirname( __DIR__ ) . '/blocks/feed-tabs' );
	register_block_type( dirname( __DIR__ ) . '/blocks/feed-tab' );
	register_block_type( dirname( __DIR__ ) . '/blocks/feed-loop', array( 'render_callback' => 'axismundi_act_render_feed_loop_block' ) );
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
	register_block_type( dirname( __DIR__ ) . '/blocks/feed-filters', array( 'render_callback' => 'axismundi_act_render_feed_filters_block' ) );
	register_block_type( dirname( __DIR__ ) . '/blocks/feed-pagination', array( 'render_callback' => 'axismundi_act_render_feed_pagination_block' ) );
	register_block_type( dirname( __DIR__ ) . '/blocks/feed-density-switch', array( 'render_callback' => 'axismundi_act_render_feed_density_switch_block' ) );
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
function axismundi_act_actor_feed_template_source( Axismundi_Actor $actor, string $density = 'card', string $surface = '' ) : string {
	return axismundi_act_extract_feed_item_template( axismundi_act_feed_surface_blocks( axismundi_act_actor_feed_template_blocks( $actor ), $surface ), $density );
}

/**
 * Narrow a parsed template to the one surface being rendered.
 *
 * A profile used to have one arrangement, so every question about the template — which cards, what
 * chrome, in what order — was asked of the whole document and answered by the first match found
 * anywhere in it. With a layout per surface there are several of each, and "the first one" becomes
 * whichever surface an author happened to put at the top.
 *
 * A template with no tabs is returned untouched. That is not a legacy path being tolerated: it is
 * what a profile with a single arrangement looks like, and what every template saved before this
 * block existed looks like, including the ones already in the database.
 *
 * A template that has tabs but none for this surface returns nothing rather than falling back to
 * the whole document. Falling back would silently render the Activity layout under the Community
 * heading, which reads as a working feed showing the wrong thing — the failure that is hardest to
 * notice and hardest to explain.
 *
 * @param array<int,array<string,mixed>> $blocks  Parsed template blocks.
 * @param string                         $surface Surface being rendered.
 * @return array<int,array<string,mixed>>
 */
function axismundi_act_feed_surface_blocks( array $blocks, string $surface ) : array {
	$tabs = axismundi_act_find_block_by_name( $blocks, 'axismundi/feed-tabs' );
	if ( null === $tabs || '' === $surface ) {
		return $blocks;
	}
	foreach ( (array) ( $tabs['innerBlocks'] ?? array() ) as $tab ) {
		if ( 'axismundi/feed-tab' !== (string) ( $tab['blockName'] ?? '' ) ) {
			continue;
		}
		if ( $surface === (string) ( $tab['attrs']['surface'] ?? 'activity' ) ) {
			return (array) ( $tab['innerBlocks'] ?? array() );
		}
	}
	return array();
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
 * Find the feed loop anywhere in a parsed template.
 *
 * @param array<int,array<string,mixed>> $blocks Parsed blocks.
 * @return array<string,mixed>|null
 */
function axismundi_act_find_feed_loop_block( array $blocks ) : ?array {
	foreach ( $blocks as $block ) {
		if ( 'axismundi/feed' === ( $block['blockName'] ?? '' ) ) {
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
	$wrapper = axismundi_act_find_block_by_name( $blocks, 'axismundi/feed-loop' );
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
function axismundi_act_actor_feed_densities_available( Axismundi_Actor $actor, string $surface = '' ) : array {
	return axismundi_act_feed_item_templates( axismundi_act_feed_surface_blocks( axismundi_act_actor_feed_template_blocks( $actor ), $surface ) )['order'];
}
