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
		'page'           => 'axismundi_forum_profile_surface_page',
	);
	return $surfaces;
}
add_filter( 'axismundi_act_actor_profile_surfaces', 'axismundi_forum_register_profile_surface', 10, 2 );
