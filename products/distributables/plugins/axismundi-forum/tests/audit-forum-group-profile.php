<?php
/**
 * Forum Group-profile surface regression (dev-only; dist-excluded).
 *
 * A public managed Group Actor's profile is its community page, so it shows Forum Topics where
 * an ordinary Actor shows an Activity timeline. Locks that claim to public managed Groups and
 * locks Forum out of every other Actor's profile.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/actor-feed.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/topics.php';
require_once __DIR__ . '/../includes/memberships.php';
require_once __DIR__ . '/../includes/distribution.php';

axismundi_forum_install();
axismundi_forum_register_topic_post_type();

global $wpdb;
$ax_gp_results = array();
$ax_gp_users   = array();
$ax_gp_ids     = array();
$ax_gp_posts   = array();

/** @param bool[] $results Results. */
function ax_gp_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/**
 * The feed as one loop attribute would render it.
 *
 * One entry per page, so there is a pager to look at. At the default page size this fixture fits
 * on a single page, the numbered links never render, and an assertion about them would pass by
 * finding nothing to disagree with.
 *
 * @return string
 */
function ax_gp_navigation_html( Axismundi_Actor $actor, string $navigation ) : string {
	$one = static fn() : int => 1;
	add_filter( 'axismundi_act_actor_feed_per_page', $one, 99 );
	$GLOBALS['axismundi_actors_current_actor'] = $actor;
	$html = axismundi_act_render_actor_activity_feed( array( 'navigation' => $navigation ) );
	unset( $GLOBALS['axismundi_actors_current_actor'] );
	remove_filter( 'axismundi_act_actor_feed_per_page', $one, 99 );
	return $html;
}

/** @return string The profile feed as the Actor profile template renders it. */
function ax_gp_profile_feed( Axismundi_Actor $actor ) : string {
	$GLOBALS['axismundi_actors_current_actor'] = $actor;
	$html = axismundi_act_render_actor_activity_feed();
	unset( $GLOBALS['axismundi_actors_current_actor'] );
	return $html;
}

/** @param array<string,string> $args Query string the reader arrived with. */
function ax_gp_profile_feed_with_args( Axismundi_Actor $actor, array $args ) : string {
	$restore = $_GET;
	$_GET    = $args;
	$html    = ax_gp_profile_feed( $actor );
	$_GET    = $restore;
	return $html;
}

/** @return Axismundi_Actor|WP_Error Throwaway public managed Group. */
function ax_gp_group( int $owner, array &$identity_ids ) {
	$group = axismundi_actors_create_managed_group(
		array(
			'owner_user_id'      => $owner,
			'preferred_username' => 'axgp' . strtolower( wp_generate_password( 7, false, false ) ),
			'status'             => 'internal',
		)
	);
	if ( $group instanceof Axismundi_Actor ) {
		$identity_ids[] = $group->get_identity_id();
		axismundi_actors_set_status( $group->get_identity_id(), 'public' );
		$group = axismundi_actors_get_by_identity( $group->get_identity_id() );
	}
	return $group;
}

try {
	$owner         = (int) wp_insert_user( array( 'user_login' => 'axgp_' . strtolower( wp_generate_password( 9, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_gp_users[] = $owner;
	wp_set_current_user( $owner );
	$person = axismundi_actors_ensure_for_user( $owner );
	if ( $person instanceof Axismundi_Actor ) {
		$ax_gp_ids[] = $person->get_identity_id();
		axismundi_actors_register_handle( $person->get_identity_id(), 'axgp' . strtolower( wp_generate_password( 8, false, false ) ) );
		axismundi_actors_set_status( $person->get_identity_id(), 'public' );
		$person = axismundi_actors_get_for_user( $owner );
	}

	$group = ax_gp_group( $owner, $ax_gp_ids );
	$community = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	$bound = $community > 0 && axismundi_forum_is_community( $community );

	$topic = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_FORUM_TOPIC_POST_TYPE, 'post_status' => 'publish', 'post_author' => $owner, 'post_title' => 'Group Profile Topic Alpha', 'post_content' => 'body' ) );
	$ax_gp_posts[] = $topic;
	$admitted = axismundi_forum_admit_local_topic( $community, $topic, $owner );
	$topic_uri = axismundi_forum_topic_object_uri( get_post( $topic ) );

	/*
	 * The Group admits a reply by announcing its Create, rather than the Note Object directly.
	 * This makes the profile assertion below cover the one-hop unwrapping that lets Activities
	 * apply Forum's Posts/Comments vocabulary to the Group Actor's common ledger.
	 */
	$reply = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $owner, 'post_content' => '<p>Group Profile Reply Delta.</p>' ) );
	$ax_gp_posts[] = $reply;
	$reply_saved = axismundi_note_save( $reply, array( 'in_reply_to_uri' => $topic_uri, 'visibility' => 'public' ) );
	if ( ! is_wp_error( $reply_saved ) ) {
		wp_update_post( array( 'ID' => $reply, 'post_status' => 'publish' ) );
	}
	$reply_envelope = axismundi_note_get( $reply );
	$reply_uri      = is_array( $reply_envelope ) ? axismundi_note_object_uri( (string) $reply_envelope['local_uuid'] ) : '';

	$group_feed = $group instanceof Axismundi_Actor ? ax_gp_profile_feed( $group ) : '';
	ax_gp_assert(
		$ax_gp_results,
		'a public managed Group profile shows its Activity through the same feed a Person profile uses',
		$bound
			&& ! is_wp_error( $admitted )
			// The shared loop, not a list Forum drew itself.
			&& 1 === preg_match( '#<ol class="axismundi-activity-feed__list#', $group_feed )
			&& false !== strpos( $group_feed, 'Group Profile Topic Alpha' )
			/*
			 * A card, asserted as a card. The old form of this checked only that the title
			 * appeared somewhere, which the archive's bare-title fallback satisfied even when the
			 * card had rendered nothing at all — so it could not tell a working community from a
			 * broken one.
			 */
			&& false !== strpos( $group_feed, 'axismundi-object-card__header' )
			&& false !== strpos( $group_feed, 'is-type-like' )
	);
	$group_posts_html    = $group instanceof Axismundi_Actor ? ax_gp_profile_feed_with_args( $group, array( 'filter' => 'posts' ) ) : '';
	$group_comments_html = $group instanceof Axismundi_Actor ? ax_gp_profile_feed_with_args( $group, array( 'filter' => 'comments' ) ) : '';
	ax_gp_assert(
		$ax_gp_results,
		'Forum Posts and Comments select different admitted Objects through the Activities-owned Group Activity page',
		! is_wp_error( $reply_saved )
			&& '' !== $reply_uri
			&& false !== strpos( $group_posts_html, 'Group Profile Topic Alpha' )
			&& false === strpos( $group_posts_html, 'Group Profile Reply Delta.' )
			&& false === strpos( $group_comments_html, 'Group Profile Topic Alpha' )
			&& false !== strpos( $group_comments_html, 'Group Profile Reply Delta.' )
	);
	ax_gp_assert(
		$ax_gp_results,
		'a Group activity is one surface, so the Person profile\'s surface switch never appears on it',
		false === strpos( $group_feed, 'axismundi-activity-feed__surfaces' )
			&& $group instanceof Axismundi_Actor
			&& array( 'activity' ) === array_keys( axismundi_act_actor_profile_surfaces( $group ) )
			&& 'pagination' === (string) axismundi_act_actor_profile_surfaces( $group )['activity']['mode']
			&& 'axismundi_act_actor_community_surface_page' === (string) axismundi_act_actor_profile_surfaces( $group )['activity']['page']
			&& ! function_exists( 'axismundi_forum_community_surface_page' )
	);
	ax_gp_assert(
		$ax_gp_results,
		'a Group route selects the dedicated community profile template',
		$group instanceof Axismundi_Actor && 'actor-group-profile' === axismundi_actors_profile_template_slug( $group )
	);
	$activities_before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_act_activities_table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit baseline.
	$relations_before  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_act_relations_table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit baseline.
	if ( $group instanceof Axismundi_Actor ) {
		$wpdb->delete( axismundi_forum_settings_table(), array( 'group_identity_id' => $group->get_identity_id() ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- force the unconfigured first-observation case.
	}
	$read_only_feed    = $group instanceof Axismundi_Actor ? ax_gp_profile_feed( $group ) : '';
	$activities_after  = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_act_activities_table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit comparison.
	$relations_after   = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_act_relations_table() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit comparison.
	$group_settings_after = $group instanceof Axismundi_Actor ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . axismundi_forum_settings_table() . ' WHERE group_identity_id = %d', $group->get_identity_id() ) ) : -1; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact audit fixture lookup.
	ax_gp_assert(
		$ax_gp_results,
		'an unconfigured public Group renders as a community without writing settings, Activities, or Follows',
		'' !== $read_only_feed && $activities_before === $activities_after && $relations_before === $relations_after && 0 === $group_settings_after
	);
	$pagination_topics = array();
	foreach ( array( 'Beta', 'Gamma' ) as $suffix ) {
		$pagination_topic = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_FORUM_TOPIC_POST_TYPE, 'post_status' => 'publish', 'post_author' => $owner, 'post_title' => 'Group Profile Topic ' . $suffix, 'post_content' => 'body' ) );
		$ax_gp_posts[] = $pagination_topic;
		$pagination_topics[] = $pagination_topic;
		axismundi_forum_admit_local_topic( $community, $pagination_topic, $owner );
	}
	$page_one_entries = axismundi_forum_visible_topic_entries( $community, 1, 1 );
	$page_two_entries = axismundi_forum_visible_topic_entries( $community, 1, 2 );
	$ax_gp_old_get = $_GET;
	/** @param array<string,string> $args Query string the reader arrived with. */
	$ax_gp_paged_html = static function ( array $args ) use ( $group ) : string {
		$restore = $_GET;
		$_GET    = $args;
		$GLOBALS['axismundi_actors_current_actor'] = $group;
		$html = axismundi_forum_render_topic_list_block( array( 'perPage' => 1 ) );
		unset( $GLOBALS['axismundi_actors_current_actor'] );
		$_GET = $restore;
		return $html;
	};
	$page_two_html        = $ax_gp_paged_html( array( 'page' => '2' ) );
	$page_two_legacy_html = $ax_gp_paged_html( array( 'topic_page' => '2' ) );
	$_GET                 = $ax_gp_old_get;
	ax_gp_assert(
		$ax_gp_results,
		'a Group Activity pages without repeating the first entry, and addresses pages by the name both collections share',
		3 === axismundi_forum_visible_topic_entry_count( $community )
			&& 1 === count( $page_one_entries ) && 1 === count( $page_two_entries )
			&& (int) $page_one_entries[0]['id'] !== (int) $page_two_entries[0]['id']
			&& false !== strpos( $page_two_html, 'Page 2 of 3' )
			&& 1 === preg_match( '#href="[^"]*[?&]page=1"#', $page_two_html )
			&& 1 === preg_match( '#href="[^"]*[?&]page=3"#', $page_two_html )
	);
	/*
	 * The old name is still read, and it is read *only* — the links this surface emits say
	 * `page`, so a reader who follows one leaves the legacy name behind on the first click.
	 * That asymmetry is the contract, and both halves need holding: drop the read and every
	 * `topic_page` link anyone already published lands on page one, keep emitting the old
	 * name and the two collections never share an address after all.
	 */
	/*
	 * A community draws the same card a Person does, from the same saved template.
	 *
	 * Forum chooses which entries a community shows; it does not decide what a card contains. It
	 * used to do both, by calling the Object renderer with no template and interactions switched
	 * off — so a Group's cards were a second, poorer card kept in Forum, and the `interactions`
	 * and `reaction-bar` blocks saved in the Group profile template never reached the page. The
	 * failure was invisible from the editor, where both profiles are the same PHP template.
	 *
	 * Asserted against Activities' resolver rather than against a copy of its answer: the point is
	 * that there is one card definition, so agreeing with a duplicate would miss the whole thing.
	 */
	$ax_gp_card_template = $group instanceof Axismundi_Actor ? axismundi_forum_archive_card_template( $group->get_identity_id() ) : '';
	$ax_gp_person_source = $group instanceof Axismundi_Actor && function_exists( 'axismundi_act_actor_feed_template_source' )
		? axismundi_act_actor_feed_template_source( $group )
		: 'x';
	ax_gp_assert(
		$ax_gp_results,
		'the community archive repeats the Group profile\'s own saved card, not a second one kept in Forum',
		'' !== $ax_gp_card_template
			&& $ax_gp_card_template === $ax_gp_person_source
			&& false !== strpos( $ax_gp_card_template, 'axismundi/interactions' )
	);
	$ax_gp_archive_html = $group instanceof Axismundi_Actor ? ax_gp_profile_feed( $group ) : '';
	/*
	 * The two surfaces must not disagree about what kind of list they are.
	 *
	 * The Activity timeline emitted `ol` and both Forum collections emitted `ul`, so the same kind
	 * of list claimed its order was meaningful on one profile and meaningless on the next — a
	 * difference a reader using a screen reader is told about and a sighted reader is not. Both are
	 * newest-first, and here position is also what the page numbers count, so `ol` is the true one
	 * and it is the timeline that was already right.
	 */
	$ax_gp_person_feed = $person instanceof Axismundi_Actor ? ax_gp_profile_feed( $person ) : '';
	$ax_gp_topics_html = $group instanceof Axismundi_Actor ? ax_gp_profile_feed( $group ) : '';
	ax_gp_assert(
		$ax_gp_results,
		'both profiles list their entries in the same kind of list, and it is the ordered one',
		/*
		 * They are now the same list because they are the same list — one loop renders both, so
		 * this can no longer drift. It is kept as the thing that would notice if a product started
		 * drawing its own container again.
		 */
		1 === preg_match( '#<ol class="axismundi-activity-feed__list#', $ax_gp_person_feed )
			&& 1 === preg_match( '#<ol class="axismundi-activity-feed__list#', $ax_gp_topics_html )
			&& 0 === preg_match( '#<ul class="axismundi-forum-(topic-list|archive)__items#', $ax_gp_topics_html )
	);
	ax_gp_assert(
		$ax_gp_results,
		'the card the archive renders carries the controls that template defines',
		'' !== $ax_gp_archive_html
			&& false !== strpos( $ax_gp_archive_html, 'is-type-like' )
			&& false !== strpos( $ax_gp_archive_html, 'is-type-reply' )
			&& false !== strpos( $ax_gp_archive_html, 'is-type-announce' )
	);

	/*
	 * The loop chooses how the list is walked, but only among what the source can serve.
	 *
	 * A community archive is counted and jumped around in and has no cursor to continue from, so a
	 * template asking this loop for an infinite feed is not a preference to respect — it is a
	 * request that cannot be answered, and the surface's declared modes settle it. Without the
	 * bound, a saved template could quietly turn a working archive into one with no way forward.
	 */
	$ax_gp_forced_infinite = $group instanceof Axismundi_Actor ? ax_gp_navigation_html( $group, 'infinite' ) : '';
	ax_gp_assert(
		$ax_gp_results,
		'a Group activity refuses an infinite feed it cannot serve and stays on numbered pages',
		$group instanceof Axismundi_Actor
			&& array( 'pagination' ) === (array) axismundi_act_actor_profile_surfaces( $group )['activity']['modes']
			&& 1 === preg_match( '#axismundi-feed-pagination__numbers\b#', $ax_gp_forced_infinite )
			&& 0 === preg_match( '#axismundi-activity-feed__more-link#', $ax_gp_forced_infinite )
	);

	/*
	 * Density, asserted on the profile the reader actually gets.
	 *
	 * The previous form of this called the archive's own tab, pager and switch builders directly.
	 * Those functions stopped being on the render path the moment a community became a feed
	 * surface, and the assertions kept passing while the control vanished from every page — a test
	 * that names a function proves the function, not the feature.
	 *
	 * `density`, not `view`: `view` already addresses a profile surface, so `?view=compact` began
	 * resolving to a surface that does not exist. The old spelling is still read and never emitted.
	 */
	/*
	 * One entry per page, so there is a pager to inspect at all. This fixture holds three Topics
	 * and the default page size is twenty — at which the numbered links never render and the
	 * clause asserting they carry the density would pass by finding nothing to disagree with.
	 */
	$ax_gp_one_per_page = static fn() : int => 1;
	$ax_gp_density = static function ( array $args ) use ( $group, $ax_gp_one_per_page ) : string {
		$restore = $_GET;
		$_GET    = $args;
		add_filter( 'axismundi_act_actor_feed_per_page', $ax_gp_one_per_page, 99 );
		$html = $group instanceof Axismundi_Actor ? ax_gp_profile_feed( $group ) : '';
		remove_filter( 'axismundi_act_actor_feed_per_page', $ax_gp_one_per_page, 99 );
		$_GET = $restore;
		return $html;
	};
	$ax_gp_card_html    = $ax_gp_density( array() );
	$ax_gp_compact_html = $ax_gp_density( array( 'density' => 'compact' ) );
	$ax_gp_legacy_html  = $ax_gp_density( array( 'view' => 'compact' ) );
	$ax_gp_surface_html = $ax_gp_density( array( 'view' => 'activity' ) );
	ax_gp_assert(
		$ax_gp_results,
		'the rendered profile carries the reader\'s density, and a surface name is never mistaken for one',
		1 === preg_match( '#axismundi-activity-feed is-density-card#', $ax_gp_card_html )
			&& 1 === preg_match( '#axismundi-activity-feed is-density-compact#', $ax_gp_compact_html )
			// The address a community archive once published still lands on the same density.
			&& 1 === preg_match( '#axismundi-activity-feed is-density-compact#', $ax_gp_legacy_html )
			&& 1 === preg_match( '#axismundi-activity-feed is-density-card#', $ax_gp_surface_html )
	);
	ax_gp_assert(
		$ax_gp_results,
		'density is a presentation, so it changes how entries are drawn and not which ones',
		/*
		 * Counted by feed rows, not by any part of a card.
		 *
		 * The two densities are separate saved compositions now, so they deliberately contain
		 * different blocks — an earlier form of this counted card headers and broke the moment
		 * compact stopped having one, which was the feature working rather than a regression. The
		 * row is the entry; what is inside it is the thing density is allowed to change.
		 */
		substr_count( $ax_gp_card_html, '<li class="axismundi-activity-feed__item' ) === substr_count( $ax_gp_compact_html, '<li class="axismundi-activity-feed__item' )
			&& 0 < substr_count( $ax_gp_compact_html, '<li class="axismundi-activity-feed__item' )
			// And they really are different compositions, or this would be asserting nothing.
			&& $ax_gp_card_html !== $ax_gp_compact_html
	);
	ax_gp_assert(
		$ax_gp_results,
		'every link the feed builds carries the density, and carries the default by leaving it out',
		// Collection tabs and the numbered pager both, since either would silently return the
		// reader to cards on the first click and nothing about the page would look wrong after.
		0 < preg_match_all( '#axismundi-feed-filters__view[^>]*href="[^"]*density=compact#', $ax_gp_compact_html )
			&& 0 < preg_match_all( '#axismundi-feed-pagination__(next|previous)" href="[^"]*density=compact#', $ax_gp_compact_html )
			/*
			 * Scoped to navigation, because the density switch itself must of course link to the
			 * other density — including from card view, where it is the only way to reach compact.
			 * The claim is about links that go somewhere else and carry the reader's density along.
			 */
			&& 0 === preg_match_all( '#(axismundi-feed-filters__view|axismundi-feed-pagination__(next|previous))[^>]*href="[^"]*density=#', $ax_gp_card_html )
			&& false === strpos( $ax_gp_compact_html, 'view=compact' )
	);

	/*
	 * Compared after normalising the per-control instance ids.
	 *
	 * Those ids are minted per rendered control and are *required* to differ — one Object shown
	 * twice must not have two controls claiming one id, which is held in the interaction audit.
	 * So they are not evidence about the address, and comparing them here would fail on the one
	 * property another audit exists to guarantee. Everything else is compared byte for byte,
	 * which is what "the same page" means.
	 */
	$ax_gp_normalise_ids = static function ( string $html ) : string {
		return (string) preg_replace( '#(ax-(?:rx|announce-menu|announce-trigger))-\d+#', '$1-N', $html );
	};
	ax_gp_assert(
		$ax_gp_results,
		'the legacy topic_page address still reaches the same page but is never emitted again',
		$ax_gp_normalise_ids( $page_two_legacy_html ) === $ax_gp_normalise_ids( $page_two_html )
			&& $page_two_legacy_html !== $page_two_html
			&& false !== strpos( $page_two_html, 'Page 2 of 3' )
			&& false === strpos( $page_two_html, 'topic_page=' )
	);

	$solo      = ax_gp_group( $owner, $ax_gp_ids );
	$solo_feed = $solo instanceof Axismundi_Actor ? ax_gp_profile_feed( $solo ) : 'x';
	ax_gp_assert(
		$ax_gp_results,
		'every public managed Group shows an empty Forum Topic feed before its first Topic',
		$solo instanceof Axismundi_Actor && false !== strpos( $solo_feed, 'axismundi-activity-feed__list' )
	);
	$member_user = (int) wp_insert_user( array( 'user_login' => 'axgp_member_' . strtolower( wp_generate_password( 7, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	$ax_gp_users[] = $member_user;
	$member = $member_user > 0 ? axismundi_actors_ensure_for_user( $member_user ) : null;
	if ( $member instanceof Axismundi_Actor ) {
		$ax_gp_ids[] = $member->get_identity_id();
		axismundi_actors_register_handle( $member->get_identity_id(), 'axgpm' . strtolower( wp_generate_password( 7, false, false ) ) );
		axismundi_actors_set_status( $member->get_identity_id(), 'public' );
		$member = axismundi_actors_get_for_user( $member_user );
	}
	$members_scope = axismundi_forum_set_distribution_scope( $community, $owner, 'members' );
	wp_set_current_user( 0 );
	$anonymous_member_scope_feed = $group instanceof Axismundi_Actor ? ax_gp_profile_feed( $group ) : '';
	if ( $member instanceof Axismundi_Actor ) {
		axismundi_forum_write_membership( $community, $member->get_identity_id(), 'accepted', 'https://example.invalid/follows/' . wp_generate_uuid4() );
	}
	wp_set_current_user( $member_user );
	$member_scope_feed = $group instanceof Axismundi_Actor ? ax_gp_profile_feed( $group ) : '';
	ax_gp_assert(
		$ax_gp_results,
		'a members-scope community publishes nothing to anyone yet, including its own accepted members',
		/*
		 * Scoped out on purpose, and asserted in both directions so it stays a decision.
		 *
		 * Reading a members-only Topic needs a viewer-aware card renderer we have not built: the
		 * Object view model closes such a Topic through the transformer's federation visibility,
		 * which correctly answers "may this be republished" and is the wrong gate to be asked "may
		 * this member read it". Carrying an entitlement that far is a piece of work, not a flag.
		 *
		 * The member half is the part worth being explicit about, because it is a reduction. The
		 * old archive fell back to a bare title link whenever a card came back empty, so an
		 * accepted member was seeing a list of titles — not by design, but because the fallback
		 * concealed that the cards had never rendered at all. This asserts the new state rather
		 * than pretending the old one was a feature; when the viewer-aware renderer exists, this
		 * assertion is what has to be rewritten to demand cards.
		 *
		 * Anonymous stays hidden, which it always was and must remain.
		 */
		true === $members_scope
			&& false === strpos( $anonymous_member_scope_feed, 'Group Profile Topic Alpha' )
			&& false === strpos( $member_scope_feed, 'Group Profile Topic Alpha' )
			&& false === strpos( $member_scope_feed, 'axismundi-object-card__header' )
	);
	ax_gp_assert(
	$ax_gp_results,
	'members-scope Group profiles disable shared caching while public-scope Group profiles remain cacheable',
	$group instanceof Axismundi_Actor
		&& axismundi_actors_profile_requires_nocache( $group )
		&& true === axismundi_forum_set_distribution_scope( $community, $owner, 'public' )
		&& ! axismundi_actors_profile_requires_nocache( $group )
);
	wp_set_current_user( 0 );

	ax_gp_assert(
		$ax_gp_results,
		'Forum does not claim a Person profile feed',
		$person instanceof Axismundi_Actor && '' === axismundi_forum_actor_feed_html( '', $person )
	);

	ax_gp_assert(
		$ax_gp_results,
		'Forum does not displace a feed another product already rendered',
		$group instanceof Axismundi_Actor && '<p>claimed</p>' === axismundi_forum_actor_feed_html( '<p>claimed</p>', $group )
	);

	// Off any community surface there is no community to resolve, and the Topic list must
	// stay empty rather than fall back to some other Group's Topics.
	ax_gp_assert(
		$ax_gp_results,
		'community context resolves to nothing when the page is about no community',
		0 === axismundi_forum_context_group_id()
	);
} finally {
	foreach ( array_unique( $ax_gp_posts ) as $post_id ) {
		if ( get_post( (int) $post_id ) ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_unique( $ax_gp_ids ) as $identity_id ) {
		// Forum projections are keyed by the Group identity, so they belong in this loop.
		$wpdb->delete( axismundi_forum_entries_table(), array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_forum_memberships_table(), array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_forum_settings_table(), array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$actor = axismundi_actors_get_by_identity( (int) $identity_id );
		if ( $actor instanceof Axismundi_Actor && function_exists( 'axismundi_act_activities_table' ) ) {
			$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri_hash' => hash( 'sha256', $actor->get_uri() ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	wp_set_current_user( 0 );
	if ( ! empty( $ax_gp_users ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_gp_users ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_gp_failed = count( array_filter( $ax_gp_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_gp_results ) - $ax_gp_failed, count( $ax_gp_results ) );
exit( $ax_gp_failed > 0 ? 1 : 0 );
