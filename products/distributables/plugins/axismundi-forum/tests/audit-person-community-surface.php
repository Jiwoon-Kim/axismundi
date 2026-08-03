<?php
/**
 * Person profile community-surface regression (dev-only; dist-excluded).
 *
 * The two surfaces of a Person profile are one ledger read from two sides: `activity` takes the
 * entries with no Group context, `community` takes the ones that have it. Neither is a forum
 * feature any more — the fixtures here are forum content precisely because the point is that a
 * Topic and a Group reply still land where they used to after the forum stopped deciding it.
 *
 * Kept in the forum suite for its fixtures, which is also what makes it the replacement test: if
 * this passes with no forum rule left in the selection path, the addressing is carrying it.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/actor-feed.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/topics.php';
require_once __DIR__ . '/../includes/outbound-topics.php';
require_once __DIR__ . '/../includes/distribution.php';

axismundi_forum_install();
axismundi_forum_register_topic_post_type();

global $wpdb;
$ax_ps_results = array();
$ax_ps_users   = array();
$ax_ps_ids     = array();
$ax_ps_posts   = array();

/** @param bool[] $results Results. */
function ax_ps_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$owner         = (int) wp_insert_user( array( 'user_login' => 'axps_' . strtolower( wp_generate_password( 9, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_ps_users[] = $owner;
	wp_set_current_user( $owner );
	$author      = axismundi_actors_ensure_for_user( $owner );
	$ax_ps_ids[] = $author instanceof Axismundi_Actor ? $author->get_identity_id() : 0;
	if ( $author instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $author->get_identity_id(), 'axps' . strtolower( wp_generate_password( 8, false, false ) ) );
		axismundi_actors_set_status( $author->get_identity_id(), 'public' );
		$author = axismundi_actors_get_by_identity( $author->get_identity_id() );
	}

	$group       = axismundi_actors_create_managed_group( array( 'owner_user_id' => $owner, 'preferred_username' => 'axpsg' . strtolower( wp_generate_password( 7, false, false ) ), 'status' => 'public' ) );
	$ax_ps_ids[] = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	$community   = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;

	// A community Topic, a community reply, and one ordinary personal Note for contrast.
	$topic         = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_FORUM_TOPIC_POST_TYPE, 'post_status' => 'publish', 'post_author' => $owner, 'post_title' => 'Surface Topic', 'post_content' => 'body' ) );
	$ax_ps_posts[] = $topic;
	$admitted      = axismundi_forum_admit_local_topic( $community, $topic, $owner );
	$topic_uri     = function_exists( 'axismundi_op_post_object_uri' ) ? axismundi_op_post_object_uri( get_post( $topic ) ) : '';

	/*
	 * The Notes are drafted, given their envelope, and only then published — which is the order
	 * the editor uses. Publishing first would record a `Create` that predates the reply metadata,
	 * and since the feed selects `Create` rows, the reply would be judged by a payload that did
	 * not yet name the community it was addressed to.
	 */
	$reply_post    = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $owner, 'post_title' => 'surface reply', 'post_content' => 'a community reply' ) );
	$ax_ps_posts[] = $reply_post;
	axismundi_note_save( $reply_post, array( 'visibility' => 'public', 'in_reply_to_uri' => $topic_uri ) );
	wp_update_post( array( 'ID' => $reply_post, 'post_status' => 'publish' ) );
	$reply_commit = axismundi_note_record_commit( get_post( $reply_post ) );

	$personal_post = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $owner, 'post_title' => 'personal note', 'post_content' => 'just a note' ) );
	$ax_ps_posts[] = $personal_post;
	axismundi_note_save( $personal_post, array( 'visibility' => 'public' ) );
	wp_update_post( array( 'ID' => $personal_post, 'post_status' => 'publish' ) );
	$personal_commit = axismundi_note_record_commit( get_post( $personal_post ) );
	ax_ps_assert(
		$ax_ps_results,
		'both fixture Notes reached the ledger, so the surface assertions below are testing selection and not an empty store',
		$reply_commit instanceof Axismundi_Activity && $personal_commit instanceof Axismundi_Activity
	);

	$surfaces = $author instanceof Axismundi_Actor ? axismundi_act_actor_profile_surfaces( $author ) : array();
	ax_ps_assert(
		$ax_ps_results,
		'a Person profile has both sides of its own ledger, and each names the side of Group context it takes',
		isset( $surfaces['community'], $surfaces['activity'] )
			&& is_callable( $surfaces['community']['page'] )
			&& 'in' === (string) $surfaces['community']['group_context']
			&& 'out' === (string) $surfaces['activity']['group_context']
			// The same four filters as the timeline, because they are the same ledger.
			&& array_keys( axismundi_act_actor_feed_filters() ) === array_keys( (array) $surfaces['community']['filters'] )
			&& 'all' === (string) $surfaces['community']['default_filter']
	);

	ax_ps_assert(
		$ax_ps_results,
		'no forum rule is left in the profile-feed selection path, so the assertions below measure addressing',
		! has_filter( 'axismundi_act_actor_feed_activity_visible', 'axismundi_forum_topic_submission_actor_feed_visible' )
			&& ! has_filter( 'axismundi_act_actor_feed_activity_visible', 'axismundi_forum_group_reply_actor_feed_visible' )
			&& ! function_exists( 'axismundi_forum_profile_surface_visible' )
			&& ! function_exists( 'axismundi_forum_render_profile_surface' )
	);

	$group_surfaces = $group instanceof Axismundi_Actor ? axismundi_act_actor_profile_surfaces( $group ) : array();
	/*
	 * A Group profile has one Activities-owned Activity surface. Forum contributes the
	 * Posts/Comments vocabulary and its declarative mapping, not a second selection or page
	 * renderer of its own.
	 */
	ax_ps_assert(
		$ax_ps_results,
		'a Group profile reads its accepted Announce ledger through Activities while Forum supplies only its collection vocabulary',
		isset( $group_surfaces['activity'] )
			&& 'axismundi_act_actor_community_surface_page' === (string) $group_surfaces['activity']['page']
			&& array( 'posts', 'comments' ) === array_keys( (array) $group_surfaces['activity']['filters'] )
			&& array( 'filter' => 'all', 'object_is_reply' => false ) === (array) $group_surfaces['activity']['filter_selection']['posts']
			&& array( 'filter' => 'all', 'object_is_reply' => true ) === (array) $group_surfaces['activity']['filter_selection']['comments']
			&& ! function_exists( 'axismundi_forum_community_surface_page' )
			&& ! isset( $group_surfaces['community'] )
	);

	$object_uris = static function ( string $filter ) use ( $author ) : array {
		return array_column( axismundi_act_actor_community_surface_page( $author, 50, '', $filter )['items'], 'object_uri' );
	};
	$all          = $object_uris( 'all' );
	$no_replies   = $object_uris( 'posts-and-boosts' );
	$reply_uri    = axismundi_note_object_uri( (string) axismundi_note_get( $reply_post )['local_uuid'] );
	$personal_uri = axismundi_note_object_uri( (string) axismundi_note_get( $personal_post )['local_uuid'] );

	ax_ps_assert(
		$ax_ps_results,
		'the community surface shows the Topic and the community reply that the personal timeline hides',
		! is_wp_error( $admitted ) && '' !== $topic_uri
			&& in_array( $topic_uri, $all, true )
			&& in_array( $reply_uri, $all, true )
	);

	// The timeline's own switches still work here, rather than the surface answering `all` to
	// everything because its selection happens somewhere the filter never reaches.
	ax_ps_assert(
		$ax_ps_results,
		'turning replies off drops the community reply and keeps the Topic',
		! in_array( $reply_uri, $no_replies, true ) && in_array( $topic_uri, $no_replies, true )
	);

	ax_ps_assert(
		$ax_ps_results,
		'an ordinary personal Note never leaks onto the community surface',
		'' !== $personal_uri && ! in_array( $personal_uri, $all, true )
			&& ! in_array( $personal_uri, $no_replies, true )
	);

	/*
	 * Whose identity each row names, which is the difference between the two surfaces that a
	 * reader actually sees. The page is already this Person's profile, so the community rows name
	 * the Group instead — and the timeline rows must keep naming the Person, or the surfaces would
	 * be telling the same reader two different things about the same entry.
	 */
	$community_item = axismundi_act_actor_community_surface_page( $author, 5, '', 'all' )['items'][0] ?? array();
	$activity_item  = axismundi_act_actor_activity_surface_page( $author, 5, '', 'all' )['items'][0] ?? array();
	ax_ps_assert(
		$ax_ps_results,
		'a community row names the Group it went to, while a timeline row names nobody but the author',
		$group instanceof Axismundi_Actor
			&& $group->get_uri() === (string) ( $community_item['header_actor'] ?? '' )
			&& '' === (string) ( $activity_item['header_actor'] ?? 'unset' )
	);

	// The inverse half: what the community surface shows must still be absent from the timeline.
	$activity_uris = array();
	foreach ( array_keys( axismundi_act_actor_feed_filters() ) as $filter ) {
		$activity_uris = array_merge( $activity_uris, array_column( axismundi_act_actor_feed_page( $author, 50, '', $filter, 'activity' )['items'], 'object_uri' ) );
	}
	ax_ps_assert(
		$ax_ps_results,
		'community contributions stay out of every personal-timeline filter, and the personal Note stays in',
		! in_array( $topic_uri, $activity_uris, true )
			&& ! in_array( $reply_uri, $activity_uris, true )
			&& in_array( $personal_uri, $activity_uris, true )
	);

	// A surface is a way of reading one Actor, never a second identity.
	ax_ps_assert(
		$ax_ps_results,
		'the community surface reads the same Actor URI and mints no second identity',
		$author instanceof Axismundi_Actor
			&& $author->get_uri() === (string) ( axismundi_act_actor_community_surface_page( $author, 5, '', 'all' )['items'][0]['actor_uri'] ?? '' )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( array_filter( array_unique( $ax_ps_posts ) ) as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
	$table = axismundi_act_activities_table();
	foreach ( array_filter( array_unique( $ax_ps_ids ) ) as $identity_id ) {
		$fixture = axismundi_actors_get_by_identity( (int) $identity_id );
		if ( $fixture instanceof Axismundi_Actor ) {
			$wpdb->delete( $table, array( 'actor_uri_hash' => hash( 'sha256', $fixture->get_uri() ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		foreach ( array( axismundi_forum_entries_table(), axismundi_forum_settings_table(), axismundi_forum_memberships_table() ) as $forum_table ) {
			$wpdb->delete( $forum_table, array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		foreach ( array( axismundi_actors_endpoints_table(), axismundi_actors_actors_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	if ( ! empty( $ax_ps_users ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_ps_users ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_ps_failed = count( array_filter( $ax_ps_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_ps_results ) - $ax_ps_failed, count( $ax_ps_results ) );
exit( $ax_ps_failed > 0 ? 1 : 0 );
