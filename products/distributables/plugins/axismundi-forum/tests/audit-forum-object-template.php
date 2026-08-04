<?php
/**
 * Object template routing regression (dev-only; dist-excluded).
 *
 * A reply submitted to a community used to render through the plain `single-object` template:
 * no community card, no vote, no sign of where it was posted. This locks the three-way split —
 * root, reply, community — and locks it at the one place both Object routes now ask.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/topics.php';
require_once __DIR__ . '/../includes/distribution.php';
require_once __DIR__ . '/../includes/votes.php';
require_once __DIR__ . '/../includes/community-card.php';
require_once __DIR__ . '/../includes/templates.php';

axismundi_forum_install();
axismundi_forum_register_topic_post_type();

global $wpdb;
$ax_ot_results = array();
$ax_ot_users   = array();
$ax_ot_ids     = array();
$ax_ot_posts   = array();

/** @param bool[] $results Results. */
function ax_ot_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$owner         = (int) wp_insert_user( array( 'user_login' => 'axot_' . strtolower( wp_generate_password( 9, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_ot_users[] = $owner;
	wp_set_current_user( $owner );
	$author      = axismundi_actors_ensure_for_user( $owner );
	$ax_ot_ids[] = $author instanceof Axismundi_Actor ? $author->get_identity_id() : 0;
	if ( $author instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $author->get_identity_id(), 'axot' . strtolower( wp_generate_password( 8, false, false ) ) );
		axismundi_actors_set_status( $author->get_identity_id(), 'public' );
	}

	$group       = axismundi_actors_create_managed_group( array( 'owner_user_id' => $owner, 'preferred_username' => 'axotg' . strtolower( wp_generate_password( 7, false, false ) ), 'status' => 'public' ) );
	$ax_ot_ids[] = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	$community   = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;

	$topic         = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_FORUM_TOPIC_POST_TYPE, 'post_status' => 'publish', 'post_author' => $owner, 'post_title' => 'Object Template Topic', 'post_content' => 'body' ) );
	$ax_ot_posts[] = $topic;
	$admitted      = axismundi_forum_admit_local_topic( $community, $topic, $owner );
	$topic_uri     = function_exists( 'axismundi_op_post_object_uri' ) ? axismundi_op_post_object_uri( get_post( $topic ) ) : '';

	// A reply into the community, which is exactly the case that rendered bare before.
	$reply_post    = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $owner, 'post_title' => 'community reply', 'post_content' => 'a reply' ) );
	$ax_ot_posts[] = $reply_post;
	$reply         = axismundi_note_save( $reply_post, array( 'visibility' => 'public', 'in_reply_to_uri' => $topic_uri ) );
	$reply_uri     = is_array( $reply ) ? axismundi_note_object_uri( (string) $reply['local_uuid'] ) : '';
	if ( ! is_wp_error( $reply ) ) { wp_update_post( array( 'ID' => $reply_post, 'post_status' => 'publish' ) ); }

	// A reply to something with no community, for contrast.
	$loose_post    = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $owner, 'post_title' => 'loose reply', 'post_content' => 'a reply' ) );
	$ax_ot_posts[] = $loose_post;
	$loose         = axismundi_note_save( $loose_post, array( 'visibility' => 'public', 'in_reply_to_uri' => 'https://example.com/notes/unaffiliated' ) );
	$loose_uri     = is_array( $loose ) ? axismundi_note_object_uri( (string) $loose['local_uuid'] ) : '';
	if ( ! is_wp_error( $loose ) ) { wp_update_post( array( 'ID' => $loose_post, 'post_status' => 'publish' ) ); }

	ax_ot_assert(
		$ax_ot_results,
		/*
		 * Being a reply does not fork the template; being in a community still does.
		 *
		 * These are different kinds of fact. A reply is something the Object's own model already
		 * states, so the page reads it and says so through reply-context. A community is a fact
		 * another product supplies about the same Object, and it changes the frame the document is
		 * read in. Asserting both here keeps the distinction from eroding in either direction --
		 * re-forking on reply, or letting the community frame stop applying to one.
		 */
		'a reply routes to the same Object template as a root post, while a community still routes to its own frame',
		'single-object' === axismundi_op_object_template_slug( array( 'id' => $loose_uri, 'type' => 'Note', 'in_reply_to' => '' ), 200 )
			&& 'single-object' === axismundi_op_object_template_slug( array( 'id' => $loose_uri, 'type' => 'Note', 'in_reply_to' => 'https://example.com/notes/unaffiliated' ), 200 )
			&& 'single-object-article' === axismundi_op_object_template_slug( array( 'id' => $loose_uri, 'type' => 'Article', 'in_reply_to' => '' ), 200 )
	);

	ax_ot_assert(
		$ax_ot_results,
		'a reply submitted to a community routes to the community template',
		! is_wp_error( $admitted ) && '' !== $reply_uri
			&& axismundi_forum_object_community_group( $reply_uri ) instanceof Axismundi_Actor
			&& 'single-object-community' === axismundi_op_object_template_slug( array( 'id' => $reply_uri, 'type' => 'Note', 'in_reply_to' => $topic_uri ), 200 )
	);

	$ax_ot_previous_route = $GLOBALS['axismundi_op_object_html_route'] ?? null;
	$GLOBALS['axismundi_op_object_html_route'] = array(
		'status' => 200,
		'model'  => array( 'id' => $reply_uri ),
	);
	$ax_ot_card_group = axismundi_forum_card_group();
	$GLOBALS['axismundi_op_object_html_route'] = $ax_ot_previous_route;
	ax_ot_assert(
		$ax_ot_results,
		'a community Object route gives its sidebar the Group even without an Actor profile context',
		$group instanceof Axismundi_Actor
			&& $ax_ot_card_group instanceof Axismundi_Actor
			&& $group->get_uri() === $ax_ot_card_group->get_uri()
	);

	$ax_ot_previous_model = axismundi_op_current_object_view_model();
	$GLOBALS['axismundi_op_object_html_route'] = $ax_ot_previous_route;
	axismundi_op_set_current_object_view_model( array( 'id' => $reply_uri, 'status' => 'active' ) );
	$ax_ot_note_card_group = axismundi_forum_card_group();
	axismundi_op_set_current_object_view_model( $ax_ot_previous_model );
	ax_ot_assert(
		$ax_ot_results,
		'a local Note Object route gives its sidebar the Group through the shared current Object model',
		$group instanceof Axismundi_Actor
			&& $ax_ot_note_card_group instanceof Axismundi_Actor
			&& $group->get_uri() === $ax_ot_note_card_group->get_uri()
	);

	ax_ot_assert(
		$ax_ot_results,
		'a reply with no community keeps the ordinary Object template rather than borrowing a community frame',
		'' !== $loose_uri
			&& null === axismundi_forum_object_community_group( $loose_uri )
			&& 'single-object' === axismundi_op_object_template_slug( array( 'id' => $loose_uri, 'type' => 'Note', 'in_reply_to' => 'https://example.com/notes/unaffiliated' ), 200 )
	);

	// A deleted post must not gain a community sidebar it never had while alive.
	ax_ot_assert(
		$ax_ot_results,
		'a Tombstone stays minimal and is never routed to the community template',
		'object-tombstone' === axismundi_op_object_template_slug( array( 'id' => $reply_uri, 'type' => 'Note', 'in_reply_to' => $topic_uri ), 410 )
	);

	$community_template = get_block_template( 'axismundi-forum//single-object-community', 'wp_template' );
	$reply_template     = get_block_template( 'axismundi-object-projections//single-object-reply', 'wp_template' );
	$object_template    = get_block_template( 'axismundi-object-projections//single-object', 'wp_template' );
	ax_ot_assert(
		$ax_ot_results,
		'the community template is registered with its community blocks, and the retired reply template is gone rather than left registered and unreachable',
		$community_template instanceof WP_Block_Template
			&& $object_template instanceof WP_Block_Template
			/*
			 * Unregistered, not merely unrouted.
			 *
			 * Dropping the reply branch from the slug decision alone would have left this template
			 * registered and offered in the Site Editor, where an author could still customize a
			 * document nothing routes to and reasonably conclude their edits were being ignored.
			 */
			&& ! $reply_template instanceof WP_Block_Template
			&& false !== strpos( $community_template->content, 'wp:axismundi/community-card' )
			/*
			 * The vote is no longer placed here, and that is the point.
			 *
			 * A community Object gets its vote because the shared card's Like resolves to one in
			 * community context, not because this template hard-codes a second control beside the
			 * card. Keeping the old block would now render both. What this template still owes the
			 * page is the community frame — the card and the sidebar — so that is what is asserted.
			 */
			&& false === strpos( $community_template->content, 'wp:axismundi/interaction {"type":"vote"}' )
			&& false !== strpos( $community_template->content, 'axismundi-object-thread-item' )
			/*
			 * One human-facing thread renderer, not two.
			 *
			 * `axismundi/replies` draws the bounded nested tree this page shows. The direct-reply
			 * collection stays an ActivityPub contract and keeps its renderer, but placing it here
			 * too printed the same thread a second time in a flatter shape. Both were carried
			 * together on purpose while they were being compared; this asserts the comparison ended.
			 */
			&& false !== strpos( $community_template->content, 'wp:axismundi/replies' )
			&& false === strpos( $community_template->content, 'wp:axismundi/object-replies' )
			// The Object card, not post blocks: an Object document has no post behind it.
			&& false === strpos( $community_template->content, 'wp:post-content' )
			// The Object document a reply now renders through still carries the shared thread item.
			&& false !== strpos( $object_template->content, 'axismundi-object-thread-item' )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( array_filter( array_unique( $ax_ot_posts ) ) as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
	$table = axismundi_act_activities_table();
	foreach ( array_filter( array_unique( $ax_ot_ids ) ) as $identity_id ) {
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
	if ( ! empty( $ax_ot_users ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_ot_users ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_ot_failed = count( array_filter( $ax_ot_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_ot_results ) - $ax_ot_failed, count( $ax_ot_results ) );
exit( $ax_ot_failed > 0 ? 1 : 0 );
