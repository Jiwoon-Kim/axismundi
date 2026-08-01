<?php
/**
 * Member-distributed Topic access regression (dev-only; dist-excluded).
 *
 * A community whose distribution is `members` withheld its Topics from the ActivityStreams route
 * but answered 200 on the HTML permalink, so the protection was true only of the representation
 * nobody reads by hand. This locks both routes to one rule, and locks the refusal as a 404: a
 * notice saying "this is for members" still discloses that the post exists, who wrote it, and
 * what it is about.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/topics.php';
require_once __DIR__ . '/../includes/memberships.php';
require_once __DIR__ . '/../includes/distribution.php';

axismundi_forum_install();
axismundi_forum_register_topic_post_type();

global $wpdb;
$ax_ta_results = array();
$ax_ta_users   = array();
$ax_ta_ids     = array();
$ax_ta_posts   = array();

/** @param bool[] $results Results. */
function ax_ta_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$owner    = (int) wp_insert_user( array( 'user_login' => 'axta_' . strtolower( wp_generate_password( 9, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$member   = (int) wp_insert_user( array( 'user_login' => 'axtam_' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	$outsider = (int) wp_insert_user( array( 'user_login' => 'axtao_' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	$author   = (int) wp_insert_user( array( 'user_login' => 'axtaa_' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'contributor' ) );
	$ax_ta_users = array( $owner, $member, $outsider, $author );
	wp_set_current_user( $owner );

	foreach ( array( $owner, $member, $author ) as $uid ) {
		$actor = axismundi_actors_ensure_for_user( $uid );
		if ( $actor instanceof Axismundi_Actor ) {
			$ax_ta_ids[] = $actor->get_identity_id();
			axismundi_actors_register_handle( $actor->get_identity_id(), 'axta' . strtolower( wp_generate_password( 8, false, false ) ) );
			axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
		}
	}
	$member_actor = axismundi_actors_get_for_user( $member );

	$group       = axismundi_actors_create_managed_group( array( 'owner_user_id' => $owner, 'preferred_username' => 'axtag' . strtolower( wp_generate_password( 7, false, false ) ), 'status' => 'public' ) );
	$ax_ta_ids[] = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	$gid         = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	axismundi_forum_set_distribution_scope( $gid, $owner, 'members' );
	axismundi_forum_write_membership( $gid, $member_actor->get_identity_id(), 'accepted', home_url( '/activities/' . wp_generate_uuid4() . '/' ) );

	/*
	 * Authored by someone who is neither a member nor a manager, so "author" and "member" are
	 * told apart. Submission itself is done by the manager: a contributor cannot submit to a
	 * community, and admitting as them leaves no entry at all — which would make every
	 * assertion below pass for the wrong reason, since a Topic with no community is unrestricted.
	 */
	$topic         = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_FORUM_TOPIC_POST_TYPE, 'post_status' => 'publish', 'post_author' => $author, 'post_title' => 'Members Only Topic', 'post_content' => 'canary' ) );
	$ax_ta_posts[] = $topic;
	$admitted      = axismundi_forum_admit_local_topic( $gid, $topic, $owner );
	$post          = get_post( $topic );

	ax_ta_assert(
		$ax_ta_results,
		'the fixture Topic really is bound to the member-distributed community',
		! is_wp_error( $admitted ) && is_array( axismundi_forum_get_topic_entry( $topic ) )
	);

	ax_ta_assert(
		$ax_ta_results,
		'a member-distributed Topic is refused to anonymous visitors and to logged-in non-members alike',
		! is_wp_error( $admitted )
			&& false === axismundi_forum_can_read_topic( $post, 0 )
			&& false === axismundi_forum_can_read_topic( $post, $outsider )
	);

	ax_ta_assert(
		$ax_ta_results,
		'an accepted member, a manager, and the author can all read it',
		true === axismundi_forum_can_read_topic( $post, $member )
			&& true === axismundi_forum_can_read_topic( $post, $owner )
			// Membership can be revoked and a community can change scope afterwards; neither
			// should take someone's own post away from them.
			&& true === axismundi_forum_can_read_topic( $post, $author )
	);

	ax_ta_assert(
		$ax_ta_results,
		'the same Topic stays out of public listings for those refused, and stays in for those allowed',
		in_array( $topic, axismundi_forum_restricted_topic_ids( 0 ), true )
			&& in_array( $topic, axismundi_forum_restricted_topic_ids( $outsider ), true )
			&& ! in_array( $topic, axismundi_forum_restricted_topic_ids( $member ), true )
			&& ! in_array( $topic, axismundi_forum_restricted_topic_ids( $author ), true )
	);

	// The federated representation was already withheld; this keeps the two routes agreeing.
	ax_ta_assert(
		$ax_ta_results,
		'the ActivityStreams projection is withheld for the same Topic, so neither route is the loose one',
		false === axismundi_forum_topic_article_visible( $post )
	);

	// A public community must not be caught by any of this.
	$open_group  = axismundi_actors_create_managed_group( array( 'owner_user_id' => $owner, 'preferred_username' => 'axtao' . strtolower( wp_generate_password( 7, false, false ) ), 'status' => 'public' ) );
	$ax_ta_ids[] = $open_group instanceof Axismundi_Actor ? $open_group->get_identity_id() : 0;
	$open_topic  = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_FORUM_TOPIC_POST_TYPE, 'post_status' => 'publish', 'post_author' => $author, 'post_title' => 'Open Topic', 'post_content' => 'body' ) );
	$ax_ta_posts[] = $open_topic;
	axismundi_forum_admit_local_topic( $open_group->get_identity_id(), $open_topic, $owner );

	ax_ta_assert(
		$ax_ta_results,
		'a public community Topic is readable by anyone and never enters the restricted list',
		'public' === axismundi_forum_get_distribution_scope( $open_group->get_identity_id() )
			&& true === axismundi_forum_can_read_topic( get_post( $open_topic ), 0 )
			&& ! in_array( $open_topic, axismundi_forum_restricted_topic_ids( 0 ), true )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( array_filter( array_unique( $ax_ta_posts ) ) as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
	$table = axismundi_act_activities_table();
	foreach ( array_filter( array_unique( $ax_ta_ids ) ) as $identity_id ) {
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
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( array_filter( array_unique( $ax_ta_users ) ) as $user_id ) {
		if ( get_userdata( (int) $user_id ) ) {
			wp_delete_user( (int) $user_id );
		}
	}
}

$ax_ta_failed = count( array_filter( $ax_ta_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_ta_results ) - $ax_ta_failed, count( $ax_ta_results ) );
exit( $ax_ta_failed > 0 ? 1 : 0 );
