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

/** @return string The profile feed as the Actor profile template renders it. */
function ax_gp_profile_feed( Axismundi_Actor $actor ) : string {
	$GLOBALS['axismundi_actors_current_actor'] = $actor;
	$html = axismundi_act_render_actor_activity_feed();
	unset( $GLOBALS['axismundi_actors_current_actor'] );
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

	$group_feed = $group instanceof Axismundi_Actor ? ax_gp_profile_feed( $group ) : '';
	ax_gp_assert(
		$ax_gp_results,
		'a public managed Group profile shows Forum Topics in place of an Activity timeline',
		$bound
			&& ! is_wp_error( $admitted )
			&& false !== strpos( $group_feed, 'axismundi-forum-topic-list' )
			&& false !== strpos( $group_feed, 'Group Profile Topic Alpha' )
			&& false === strpos( $group_feed, 'axismundi-activity-feed' )
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
	$ax_gp_old_get    = $_GET;
	$_GET['topic_page'] = '2';
	$GLOBALS['axismundi_actors_current_actor'] = $group;
	$page_two_html = axismundi_forum_render_topic_list_block( array( 'perPage' => 1 ) );
	unset( $GLOBALS['axismundi_actors_current_actor'] );
	$_GET = $ax_gp_old_get;
	ax_gp_assert(
		$ax_gp_results,
		'a Group community feed uses reproducible topic_page URLs without repeating the first entry',
		3 === axismundi_forum_visible_topic_entry_count( $community )
			&& 1 === count( $page_one_entries ) && 1 === count( $page_two_entries )
			&& (int) $page_one_entries[0]['id'] !== (int) $page_two_entries[0]['id']
			&& false !== strpos( $page_two_html, 'Page 2 of 3' )
			&& false !== strpos( $page_two_html, 'topic_page=1' )
			&& false !== strpos( $page_two_html, 'topic_page=3' )
	);

	$solo      = ax_gp_group( $owner, $ax_gp_ids );
	$solo_feed = $solo instanceof Axismundi_Actor ? ax_gp_profile_feed( $solo ) : 'x';
	ax_gp_assert(
		$ax_gp_results,
		'every public managed Group shows an empty Forum Topic feed before its first Topic',
		$solo instanceof Axismundi_Actor && false !== strpos( $solo_feed, 'axismundi-forum-topic-list' )
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
		'a members-scope Group hides Topics from anonymous profile visitors and shows them to an accepted signed-in member',
		true === $members_scope && false === strpos( $anonymous_member_scope_feed, 'Group Profile Topic Alpha') && false !== strpos( $member_scope_feed, 'Group Profile Topic Alpha' )
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
