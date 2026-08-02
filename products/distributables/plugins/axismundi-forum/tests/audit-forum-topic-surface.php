<?php
/**
 * Forum Topic page surface regression (dev-only; dist-excluded).
 *
 * A Topic page has to be Forum's own template, show the community it belongs to, and show
 * replies that arrived from other servers. Each of those was separately absent at some point:
 * the template file outlived its loader, no block rendered the replies collection, and nothing
 * named the community on a Topic. This locks all three together.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/object-lifecycle.php';
require_once WP_PLUGIN_DIR . '/axismundi-object-projections/includes/remote-objects.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/topics.php';
require_once __DIR__ . '/../includes/outbound-topics.php';
require_once __DIR__ . '/../includes/distribution.php';
require_once __DIR__ . '/../includes/templates.php';
require_once __DIR__ . '/../includes/community-card.php';
require_once __DIR__ . '/../includes/votes.php';

axismundi_forum_install();
axismundi_forum_register_topic_post_type();

global $wpdb;
$ax_ts_results = array();
$ax_ts_users   = array();
$ax_ts_ids     = array();
$ax_ts_posts   = array();
$ax_ts_objects = array();

/** @param bool[] $results Results. */
function ax_ts_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$owner         = (int) wp_insert_user( array( 'user_login' => 'axts_' . strtolower( wp_generate_password( 9, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_ts_users[] = $owner;
	wp_set_current_user( $owner );
	$person        = axismundi_actors_ensure_for_user( $owner );
	$ax_ts_ids[]   = $person instanceof Axismundi_Actor ? $person->get_identity_id() : 0;
	if ( $person instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $person->get_identity_id(), 'axts' . strtolower( wp_generate_password( 8, false, false ) ) );
		axismundi_actors_set_status( $person->get_identity_id(), 'public' );
		$person = axismundi_actors_get_by_identity( $person->get_identity_id() );
	}
	$group       = axismundi_actors_create_managed_group( array( 'owner_user_id' => $owner, 'preferred_username' => 'axtsg' . strtolower( wp_generate_password( 7, false, false ) ), 'status' => 'public' ) );
	$ax_ts_ids[] = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	$community   = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;

	$topic         = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_FORUM_TOPIC_POST_TYPE, 'post_status' => 'publish', 'post_author' => $owner, 'post_title' => 'Topic Surface Audit', 'post_content' => 'body' ) );
	$ax_ts_posts[] = $topic;
	$admitted      = axismundi_forum_admit_local_topic( $community, $topic, $owner );
	$topic_uri     = axismundi_forum_topic_object_uri( get_post( $topic ) );

	// The template must be Forum's, not the theme's generic single post template.
	axismundi_forum_register_templates();
	$registered = get_block_templates( array( 'slug__in' => array( 'single-ax_topic' ) ) );
	$plugin_template = null;
	foreach ( $registered as $candidate ) {
		if ( 'plugin' === (string) $candidate->source ) {
			$plugin_template = $candidate;
		}
	}
	ax_ts_assert(
		$ax_ts_results,
		'Forum registers its own single-Topic template instead of leaving Topics on the theme post template',
		null !== $plugin_template
			&& false !== strpos( (string) $plugin_template->content, 'axismundi/replies' )
			&& false !== strpos( (string) $plugin_template->content, 'axismundi/object-replies' )
			&& false !== strpos( (string) $plugin_template->content, 'axismundi/community-card' )
	);

	// A Topic page names the community it belongs to, reading Actors rather than a copy.
	$GLOBALS['wp_query']     = new WP_Query( array( 'p' => $topic, 'post_type' => AXISMUNDI_FORUM_TOPIC_POST_TYPE ) );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	$card = axismundi_forum_render_community_card_block( array( 'showMemberCount' => true ) );
	ax_ts_assert(
		$ax_ts_results,
		'the Topic page shows its community card with the Group identity resolved live from Actors',
		$group instanceof Axismundi_Actor
			&& false !== strpos( $card, 'axismundi-forum-community-card' )
			&& false !== strpos( $card, esc_html( $group->get_preferred_username() ) )
			&& false !== strpos( $card, esc_url( $group->get_profile_url() ) )
	);

	// A reply received from another server appears on the Topic it answers.
	$author_uri = 'https://example.com/users/axts' . strtolower( wp_generate_password( 7, false, false ) );
	$remote = axismundi_actors_upsert_remote(
		array(
			'uri' => $author_uri, 'actor_type' => 'Person', 'preferred_username' => 'axtsremote', 'display_name' => 'Remote Replier', 'profile_url' => $author_uri,
			'endpoints' => array( 'inbox' => $author_uri . '/inbox', 'outbox' => $author_uri . '/outbox' ),
			'payload' => array( 'id' => $author_uri, 'type' => 'Person', 'preferredUsername' => 'axtsremote', 'inbox' => $author_uri . '/inbox', 'outbox' => $author_uri . '/outbox' ),
		)
	);
	if ( $remote instanceof Axismundi_Actor ) {
		$ax_ts_ids[] = $remote->get_identity_id();
	}
	/*
	 * A remote vote is distributed by the local Group only after the ledger proves the target
	 * was submitted directly to that Group. The incoming Activity's `audience` is advisory,
	 * not sufficient evidence on its own.
	 */
	$submission = $topic > 0 ? axismundi_forum_record_topic_commit( get_post( $topic ) ) : null;
	$vote_uri   = 'https://example.com/activities/dislike/' . wp_generate_uuid4();
	$vote       = $remote instanceof Axismundi_Actor
		? axismundi_act_record_activity( array( 'id' => $vote_uri, 'type' => 'Dislike', 'actor' => $remote->get_uri(), 'object' => $topic_uri, 'audience' => $group instanceof Axismundi_Actor ? $group->get_uri() : '' ), 'inbound' )
		: new WP_Error( 'fixture' );
	$vote_announces = $group instanceof Axismundi_Actor
		? array_filter(
			axismundi_act_get_by_actor( $group->get_uri(), 50 ),
			static fn( $candidate ) => $candidate instanceof Axismundi_Activity
				&& 'Announce' === $candidate->get_type()
				&& $vote_uri === (string) ( $candidate->get_payload()['object']['id'] ?? '' )
		)
		: array();
	ax_ts_assert(
		$ax_ts_results,
		'a local community redistributes an inbound Dislike only when its target has recorded direct Group-submission evidence',
		$submission instanceof Axismundi_Activity
			&& $vote instanceof Axismundi_Activity
			&& 1 === count( $vote_announces )
			&& $group instanceof Axismundi_Actor
			&& $group->get_uri() === (string) ( reset( $vote_announces )->get_actor_uri() ?? '' )
	);
	$undo_uri = 'https://example.com/activities/undo/' . wp_generate_uuid4();
	$undo     = $remote instanceof Axismundi_Actor
		? axismundi_act_record_activity( array( 'id' => $undo_uri, 'type' => 'Undo', 'actor' => $remote->get_uri(), 'object' => $vote_uri ), 'inbound' )
		: new WP_Error( 'fixture' );
	$undo_announces = $group instanceof Axismundi_Actor
		? array_filter(
			axismundi_act_get_by_actor( $group->get_uri(), 50 ),
			static fn( $candidate ) => $candidate instanceof Axismundi_Activity
				&& 'Announce' === $candidate->get_type()
				&& $undo_uri === (string) ( $candidate->get_payload()['object']['id'] ?? '' )
		)
		: array();
	$undone_vote = axismundi_act_get( $vote_uri );
	ax_ts_assert(
		$ax_ts_results,
		'a local community redistributes Undo(Dislike), preserving the vote withdrawal instead of undoing its own Announce',
		$undo instanceof Axismundi_Activity
			&& $undone_vote instanceof Axismundi_Activity
			&& ! $undone_vote->is_effective()
			&& 1 === count( $undo_announces )
			&& $vote_uri === (string) ( reset( $undo_announces )->get_payload()['object']['object'] ?? '' )
	);
	$local_cast = $person instanceof Axismundi_Actor ? axismundi_forum_cast_vote( $person, $topic_uri, 'down' ) : new WP_Error( 'fixture' );
	$local_vote = $person instanceof Axismundi_Actor ? axismundi_act_get_actor_vote( 'Dislike', $person->get_uri(), $topic_uri, true ) : null;
	$local_announces = $group instanceof Axismundi_Actor && $local_vote instanceof Axismundi_Activity
		? array_filter(
			axismundi_act_get_by_actor( $group->get_uri(), 50 ),
			static fn( $candidate ) => $candidate instanceof Axismundi_Activity
				&& 'Announce' === $candidate->get_type()
				&& $local_vote->get_uri() === (string) ( $candidate->get_payload()['object']['id'] ?? '' )
		)
		: array();
	ax_ts_assert(
		$ax_ts_results,
		'a local community vote is also redistributed by its Group instead of remaining a local-only score change',
		is_array( $local_cast ) && $local_vote instanceof Axismundi_Activity && 1 === count( $local_announces )
	);
	$before_unrelated = count( $vote_announces );
	$unrelated = $remote instanceof Axismundi_Actor
		? axismundi_act_record_activity( array( 'id' => 'https://example.com/activities/dislike/' . wp_generate_uuid4(), 'type' => 'Dislike', 'actor' => $remote->get_uri(), 'object' => 'https://example.com/objects/unrelated-' . wp_generate_uuid4(), 'audience' => $group instanceof Axismundi_Actor ? $group->get_uri() : '' ), 'inbound' )
		: new WP_Error( 'fixture' );
	$after_unrelated = $group instanceof Axismundi_Actor
		? array_filter(
			axismundi_act_get_by_actor( $group->get_uri(), 50 ),
			static fn( $candidate ) => $candidate instanceof Axismundi_Activity
				&& 'Announce' === $candidate->get_type()
				&& $vote_uri === (string) ( $candidate->get_payload()['object']['id'] ?? '' )
		)
		: array();
	ax_ts_assert(
		$ax_ts_results,
		'a remote Dislike cannot make a Group announce an arbitrary object by merely claiming that Group as audience',
		$unrelated instanceof Axismundi_Activity && $before_unrelated === count( $after_unrelated )
	);
	$reply_uri       = 'https://example.com/comment/' . wp_generate_uuid4();
	$ax_ts_objects[] = $reply_uri;
	$stored = axismundi_op_store_remote_object(
		array(
			'id' => $reply_uri, 'type' => 'Note', 'attributedTo' => $author_uri, 'inReplyTo' => $topic_uri,
			'content' => '<p>Remote reply body.</p>', 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		)
	);
	$tree_replies_html = axismundi_op_render_replies_block();
	$replies_html      = axismundi_op_render_object_replies_block( array( 'perPage' => 20 ) );
	ax_ts_assert(
		$ax_ts_results,
		'a reply received from another server is rendered by both the nested-tree and collection-list blocks on the Topic it answers',
		! is_wp_error( $stored )
			&& false !== strpos( $tree_replies_html, 'axismundi-thread--replies' )
			&& false !== strpos( $tree_replies_html, 'Remote reply body.' )
			&& false !== strpos( $replies_html, 'axismundi-object-replies' )
			&& false !== strpos( $replies_html, 'Remote reply body.' )
	);

	// Off any object, the block stays silent rather than guessing a thread.
	$GLOBALS['wp_query']     = new WP_Query( array( 'post_type' => 'post' ) );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	ax_ts_assert(
		$ax_ts_results,
		'both Topic-page blocks render nothing when the request is about no object or community',
		'' === axismundi_op_render_object_replies_block( array() ) && '' === axismundi_forum_render_community_card_block( array() )
	);
} finally {
	foreach ( array_unique( $ax_ts_objects ) as $object_uri ) {
		$wpdb->delete( $wpdb->prefix . 'ax_remote_objects', array( 'object_uri_hash' => hash( 'sha256', $object_uri ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( array_unique( $ax_ts_posts ) as $post_id ) {
		if ( get_post( (int) $post_id ) ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_filter( array_unique( $ax_ts_ids ) ) as $identity_id ) {
		$actor = axismundi_actors_get_by_identity( (int) $identity_id );
		if ( $actor instanceof Axismundi_Actor && function_exists( 'axismundi_act_activities_table' ) ) {
			$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri_hash' => hash( 'sha256', $actor->get_uri() ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		foreach ( array( axismundi_forum_settings_table(), axismundi_forum_entries_table(), axismundi_forum_memberships_table() ) as $table ) {
			$wpdb->delete( $table, array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		foreach ( array( axismundi_actors_endpoints_table(), axismundi_actors_managers_table(), axismundi_actors_actors_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	wp_set_current_user( 0 );
	if ( ! empty( $ax_ts_users ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_ts_users ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_ts_failed = count( array_filter( $ax_ts_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_ts_results ) - $ax_ts_failed, count( $ax_ts_results ) );
exit( $ax_ts_failed > 0 ? 1 : 0 );
