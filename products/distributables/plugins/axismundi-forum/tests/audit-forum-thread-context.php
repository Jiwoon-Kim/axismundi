<?php
/**
 * Forum root-type and thread-context regression (dev-only; dist-excluded).
 *
 * Locks Constitution Article 13: a Forum root post is an `Article`, the Group is `audience`,
 * and `context` is a per-topic resolvable thread rather than the Group. Inbound stays lenient.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/topics.php';
require_once __DIR__ . '/../includes/thread-context.php';
require_once __DIR__ . '/../includes/inbound-topics.php';

axismundi_forum_install();
axismundi_forum_register_topic_post_type();

global $wpdb;
$ax_tc_results = array();
$ax_tc_users   = array();
$ax_tc_ids     = array();
$ax_tc_posts   = array();

/** @param bool[] $results Results. */
function ax_tc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$owner         = (int) wp_insert_user( array( 'user_login' => 'axtc_' . strtolower( wp_generate_password( 9, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_tc_users[] = $owner;
	wp_set_current_user( $owner );
	$author      = axismundi_actors_ensure_for_user( $owner );
	$ax_tc_ids[] = $author instanceof Axismundi_Actor ? $author->get_identity_id() : 0;
	if ( $author instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $author->get_identity_id(), 'axtc' . strtolower( wp_generate_password( 8, false, false ) ) );
		axismundi_actors_set_status( $author->get_identity_id(), 'public' );
	}

	$group       = axismundi_actors_create_managed_group( array( 'owner_user_id' => $owner, 'preferred_username' => 'axtcg' . strtolower( wp_generate_password( 7, false, false ) ), 'status' => 'public' ) );
	$ax_tc_ids[] = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	$community = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	$bound = $community > 0 && axismundi_forum_is_community( $community );

	$topic         = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_FORUM_TOPIC_POST_TYPE, 'post_status' => 'publish', 'post_author' => $owner, 'post_title' => 'Thread Context Topic', 'post_content' => 'body' ) );
	$ax_tc_posts[] = $topic;
	$admitted      = axismundi_forum_admit_local_topic( $community, $topic, $owner );
	$object        = axismundi_forum_topic_to_article( get_post( $topic ) );

	ax_tc_assert(
		$ax_tc_results,
		'a Forum root post is published as an Article, never as a Document-family Page',
		true === $bound && ! is_wp_error( $admitted ) && is_array( $object ) && 'Article' === (string) $object['type']
	);

	ax_tc_assert(
		$ax_tc_results,
		'the Group is the audience while context names the thread, and the two are not the same URI',
		is_array( $object )
			&& $group instanceof Axismundi_Actor
			&& $group->get_uri() === (string) $object['audience']
			&& '' !== (string) $object['context']
			&& $group->get_uri() !== (string) $object['context']
			&& (string) $object['context'] === axismundi_forum_topic_context_uri( get_post( $topic ) )
	);

	$collection = axismundi_forum_thread_collection( get_post( $topic ) );
	ax_tc_assert(
		$ax_tc_results,
		'the context URI dereferences to an OrderedCollection owned by the Group and led by the root post',
		'OrderedCollection' === (string) $collection['type']
			&& 'Thread Context Topic' === (string) $collection['name']
			&& $group instanceof Axismundi_Actor
			&& $group->get_uri() === (string) ( $collection['attributedTo'] ?? '' )
			&& is_array( $collection['orderedItems'] )
			&& axismundi_forum_topic_object_uri( get_post( $topic ) ) === (string) $collection['orderedItems'][0]
			&& (string) $collection['id'] === axismundi_forum_topic_context_uri( get_post( $topic ) )
	);

	$request  = new WP_REST_Request( 'GET', '/axismundi/v1/forum/thread' );
	$request->set_param( 'object', axismundi_forum_topic_object_uri( get_post( $topic ) ) );
	$served   = axismundi_forum_get_thread( $request );
	$missing  = new WP_REST_Request( 'GET', '/axismundi/v1/forum/thread' );
	$missing->set_param( 'object', home_url( '/?p=99999901' ) );
	$refused  = axismundi_forum_get_thread( $missing );
	ax_tc_assert(
		$ax_tc_results,
		'the thread route serves activity+json for a visible Topic and 404s for anything else',
		$served instanceof WP_REST_Response
			&& 'OrderedCollection' === (string) ( $served->get_data()['type'] ?? '' )
			&& false !== strpos( (string) $served->get_headers()['Content-Type'], 'application/activity+json' )
			&& is_wp_error( $refused )
			&& 404 === (int) ( $refused->get_error_data()['status'] ?? 0 )
	);

	// Lenient on receive: a peer's own root type is accepted, an ambiguous one is not.
	ax_tc_assert(
		$ax_tc_results,
		'inbound admits both Article and the Page Lemmy publishes, and still refuses a bare Note',
		axismundi_forum_is_root_object_type( 'Article' )
			&& axismundi_forum_is_root_object_type( 'Page' )
			&& ! axismundi_forum_is_root_object_type( 'Note' )
			&& ! axismundi_forum_is_root_object_type( 'Image' )
	);

	// A reply inherits the thread it is replying into rather than being told about it.
	$inherited = function_exists( 'axismundi_note_inherited_context_uri' )
		? axismundi_note_inherited_context_uri( axismundi_forum_topic_object_uri( get_post( $topic ) ) )
		: 'note-plugin-inactive';
	ax_tc_assert(
		$ax_tc_results,
		'a reply to a Topic inherits that Topic thread context from its parent',
		$inherited === axismundi_forum_topic_context_uri( get_post( $topic ) )
	);
} finally {
	foreach ( array_unique( $ax_tc_posts ) as $post_id ) {
		if ( get_post( (int) $post_id ) ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_filter( array_unique( $ax_tc_ids ) ) as $identity_id ) {
		// Forum projections are keyed by the Group identity, so they belong in this loop.
		$wpdb->delete( axismundi_forum_entries_table(), array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_forum_settings_table(), array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	wp_set_current_user( 0 );
	if ( ! empty( $ax_tc_users ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_tc_users ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_tc_failed = count( array_filter( $ax_tc_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_tc_results ) - $ax_tc_failed, count( $ax_tc_results ) );
exit( $ax_tc_failed > 0 ? 1 : 0 );
