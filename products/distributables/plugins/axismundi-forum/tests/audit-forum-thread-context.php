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
$ax_tc_objects = array();

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

	$reply = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $owner, 'post_content' => '<p>Thread reply.</p>' ) );
	$ax_tc_posts[] = $reply;
	$reply_saved = $reply > 0 && function_exists( 'axismundi_note_save' )
		? axismundi_note_save( $reply, array( 'in_reply_to_uri' => axismundi_forum_topic_object_uri( get_post( $topic ) ), 'visibility' => 'public' ) )
		: new WP_Error( 'fixture' );
	if ( ! is_wp_error( $reply_saved ) ) { wp_update_post( array( 'ID' => $reply, 'post_status' => 'publish' ) ); }
	$reply_envelope = function_exists( 'axismundi_note_get' ) ? axismundi_note_get( $reply ) : null;
	$reply_uri = is_array( $reply_envelope ) ? axismundi_note_object_uri( (string) $reply_envelope['local_uuid'] ) : '';
	$reply_create = '' !== $reply_uri ? axismundi_act_get_object_lifecycle( $reply_uri ) : null;
	$reply_announces = $reply_create instanceof Axismundi_Activity ? axismundi_act_get_by_object( $reply_create->get_uri(), 10 ) : array();
	$reply_announce = empty( $reply_announces ) ? null : reset( $reply_announces );
	$public_uri = function_exists( 'axismundi_act_public_audience_uri' ) ? axismundi_act_public_audience_uri() : 'https://www.w3.org/ns/activitystreams#Public';
	ax_tc_assert(
		$ax_tc_results,
		'a public local Note reply directly addresses its Topic Group, carries public routing in cc, and is redistributed as Group Announce(Create(Note))',
		! is_wp_error( $reply_saved ) && $reply_create instanceof Axismundi_Activity && 'Create' === $reply_create->get_type()
			&& $group instanceof Axismundi_Actor && in_array( $group->get_uri(), (array) ( $reply_create->get_audience()['to'] ?? array() ), true )
			&& in_array( $public_uri, (array) ( $reply_create->get_audience()['cc'] ?? array() ), true )
			&& $group->get_uri() === (string) ( $reply_create->get_payload()['object']['audience'] ?? '' )
			&& $reply_announce instanceof Axismundi_Activity && 'Announce' === $reply_announce->get_type()
			&& 'Create' === (string) ( $reply_announce->get_payload()['object']['type'] ?? '' )
			&& $group->get_uri() === $reply_announce->get_actor_uri()
	);

	// A reply to a remote community is its own direct submission. The remote Group, not this
	// site's Group, decides whether to Announce it to that community's followers.
	$remote_group_uri = 'https://example.com/c/axtc-' . wp_generate_uuid4();
	$remote_group = axismundi_actors_upsert_remote(
		array(
			'uri' => $remote_group_uri, 'actor_type' => 'Group', 'preferred_username' => 'axtcremote', 'display_name' => 'Remote Community', 'profile_url' => $remote_group_uri,
			'endpoints' => array( 'inbox' => $remote_group_uri . '/inbox', 'outbox' => $remote_group_uri . '/outbox' ),
			'payload' => array( 'id' => $remote_group_uri, 'type' => 'Group', 'preferredUsername' => 'axtcremote', 'inbox' => $remote_group_uri . '/inbox', 'outbox' => $remote_group_uri . '/outbox' ),
		)
	);
	$ax_tc_ids[] = $remote_group instanceof Axismundi_Actor ? $remote_group->get_identity_id() : 0;
	$remote_topic_uri = 'https://example.com/post/' . wp_generate_uuid4();
	$ax_tc_objects[] = $remote_topic_uri;
	$remote_parent = function_exists( 'axismundi_op_remote_object_store' ) ? axismundi_op_remote_object_store(
		// Lemmy comments make their parent author the audience and place the community in cc.
		array( 'id' => $remote_topic_uri, 'type' => 'Note', 'attributedTo' => 'https://example.com/u/axtc-parent', 'inReplyTo' => 'https://example.com/post/' . wp_generate_uuid4(), 'audience' => 'https://example.com/u/axtc-parent', 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ), 'cc' => array( $remote_group_uri, 'https://example.com/u/axtc-parent' ), 'content' => '<p>Remote Comment</p>' )
	) : new WP_Error( 'fixture' );
	$remote_reply = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $owner, 'post_content' => '<p>Reply to remote community.</p>' ) );
	$ax_tc_posts[] = $remote_reply;
	$remote_saved = $remote_reply > 0 && function_exists( 'axismundi_note_save' )
		? axismundi_note_save( $remote_reply, array( 'in_reply_to_uri' => $remote_topic_uri, 'visibility' => 'public' ) )
		: new WP_Error( 'fixture' );
	if ( ! is_wp_error( $remote_saved ) ) { wp_update_post( array( 'ID' => $remote_reply, 'post_status' => 'publish' ) ); }
	$remote_envelope = function_exists( 'axismundi_note_get' ) ? axismundi_note_get( $remote_reply ) : null;
	$remote_reply_uri = is_array( $remote_envelope ) ? axismundi_note_object_uri( (string) $remote_envelope['local_uuid'] ) : '';
	$remote_create = '' !== $remote_reply_uri ? axismundi_act_get_object_lifecycle( $remote_reply_uri ) : null;
	$remote_reply_count = function_exists( 'axismundi_op_get_display_reply_tree_count' )
		? axismundi_op_get_display_reply_tree_count( $remote_topic_uri )
		: array( 'count' => 0 );
	$remote_inboxes = $remote_create instanceof Axismundi_Activity && function_exists( 'axismundi_activitypub_bridge_activity_inboxes' )
		? axismundi_activitypub_bridge_activity_inboxes( $remote_create )
		: array();
	ax_tc_assert(
		$ax_tc_results,
		'a local Note reply directly addresses the remote Topic Group while carrying public routing and its parent author in cc',
		! is_wp_error( $remote_parent ) && ! is_wp_error( $remote_saved ) && $remote_group instanceof Axismundi_Actor && $remote_create instanceof Axismundi_Activity
			&& in_array( $remote_group->get_uri(), (array) ( $remote_create->get_audience()['to'] ?? array() ), true )
			&& in_array( $public_uri, (array) ( $remote_create->get_audience()['cc'] ?? array() ), true )
			&& in_array( 'https://example.com/u/axtc-parent', (array) ( $remote_create->get_audience()['cc'] ?? array() ), true )
			&& $remote_group->get_uri() === (string) ( $remote_create->get_payload()['object']['audience'] ?? '' )
			&& false === axismundi_forum_group_reply_actor_feed_visible( true, $remote_create )
			&& null === axismundi_forum_group_reply_public_outbox_payload( $remote_create->get_payload(), $remote_create )
	);
	ax_tc_assert(
		$ax_tc_results,
		'a public direct reply to a remote Lemmy comment creates the visible local thread edge used by the Reply count',
		'' !== $remote_reply_uri && 1 === (int) ( $remote_reply_count['count'] ?? 0 )
	);
	ax_tc_assert(
		$ax_tc_results,
		'a direct reply to a remote Lemmy comment queues only its community inbox for transport',
		$remote_group instanceof Axismundi_Actor && in_array( $remote_group->get_uri() . '/inbox', $remote_inboxes, true ) && 1 === count( $remote_inboxes )
	);
} finally {
	foreach ( array_unique( $ax_tc_objects ) as $object_uri ) {
		$wpdb->delete( $wpdb->prefix . 'ax_remote_objects', array( 'object_uri_hash' => hash( 'sha256', $object_uri ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
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
