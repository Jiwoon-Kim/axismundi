<?php
/**
 * Group community archive regression (dev-only; dist-excluded).
 *
 * A community has two collections — what was posted here and what was said in reply here — and
 * the second is the one that can go wrong quietly. Listing every Note that names the Group as its
 * `audience` would look right on a healthy site and republish, on a real one, replies the
 * community never accepted and replies a moderator has already withdrawn: an author's `Create` is
 * immutable and outlives both decisions.
 *
 * So this locks that `Comments` is the Group's own still-effective `Announce` and nothing else,
 * and that it is genuinely a different list from `Posts` rather than the same rows relabelled.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/group-archive.php';
require_once __DIR__ . '/../includes/topics.php';

axismundi_forum_install();
axismundi_forum_register_topic_post_type();

global $wpdb;
$ax_ga_results = array();
$ax_ga_users   = array();
$ax_ga_ids     = array();
$ax_ga_posts   = array();

/** @param bool[] $results Results. */
function ax_ga_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Rebuild the archive selection through the same seam the ledger uses. */
function ax_ga_refresh() : void {
	update_option( 'ax_forum_thread_generation', axismundi_forum_thread_cache_generation() + 1, false );
}

try {
	$owner         = (int) wp_insert_user( array( 'user_login' => 'axga_' . strtolower( wp_generate_password( 9, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_ga_users[] = $owner;
	wp_set_current_user( $owner );
	$author      = axismundi_actors_ensure_for_user( $owner );
	$ax_ga_ids[] = $author instanceof Axismundi_Actor ? $author->get_identity_id() : 0;
	axismundi_actors_register_handle( $author->get_identity_id(), 'axga' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $author->get_identity_id(), 'public' );

	$group       = axismundi_actors_create_managed_actor( array( 'owner_user_id' => $owner, 'preferred_username' => 'axgag' . strtolower( wp_generate_password( 7, false, false ) ), 'status' => 'public' ) );
	$ax_ga_ids[] = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	$community   = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;

	$topic         = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_FORUM_TOPIC_POST_TYPE, 'post_status' => 'publish', 'post_author' => $owner, 'post_title' => 'Archive Topic', 'post_content' => 'body' ) );
	$ax_ga_posts[] = $topic;
	$admitted      = axismundi_forum_admit_local_topic( $community, $topic, $owner );
	$topic_uri     = axismundi_forum_topic_object_uri( get_post( $topic ) );

	$reply         = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $owner, 'post_content' => '<p>Archived reply.</p>' ) );
	$ax_ga_posts[] = $reply;
	$reply_saved   = axismundi_note_save( $reply, array( 'in_reply_to_uri' => $topic_uri, 'visibility' => 'public' ) );
	if ( ! is_wp_error( $reply_saved ) ) {
		wp_update_post( array( 'ID' => $reply, 'post_status' => 'publish' ) );
	}
	$envelope  = axismundi_note_get( $reply );
	$reply_uri = is_array( $envelope ) ? axismundi_note_object_uri( (string) $envelope['local_uuid'] ) : '';

	/*
	 * A remote subscriber, so the delivery contract can be checked end to end rather than inferred.
	 *
	 * Nothing here computes an intersection of Person and Group audiences. A Group Announce is
	 * addressed to the Group's followers collection and the bridge expands that to the inbox of
	 * every accepted follower; a peer then files it under whichever of its own feeds the receiving
	 * account subscribes to. Which is why following one of our people does not deliver a
	 * community's posts, and why subscribing to the community does.
	 */
	$remote_person_uri = 'https://example.com/u/axga-' . wp_generate_uuid4();
	$remote_person     = axismundi_actors_upsert_remote(
		array(
			'uri' => $remote_person_uri, 'actor_type' => 'Person', 'preferred_username' => 'axgaperson', 'display_name' => 'Remote Subscriber', 'profile_url' => $remote_person_uri,
			'endpoints' => array( 'inbox' => $remote_person_uri . '/inbox', 'outbox' => $remote_person_uri . '/outbox' ),
			'payload' => array( 'id' => $remote_person_uri, 'type' => 'Person', 'preferredUsername' => 'axgaperson', 'inbox' => $remote_person_uri . '/inbox', 'outbox' => $remote_person_uri . '/outbox' ),
		)
	);
	$ax_ga_ids[]   = $remote_person instanceof Axismundi_Actor ? $remote_person->get_identity_id() : 0;
	$subscription  = $remote_person instanceof Axismundi_Actor
		? axismundi_act_write_relation( 'follow', $remote_person_uri, $group->get_uri(), 'inbound', 'accepted', $remote_person_uri . '/follows/1', $remote_person_uri . '/follows/1' )
		: new WP_Error( 'fixture' );

	ax_ga_refresh();
	$comments = axismundi_forum_group_comment_uris( $group );

	// Before asserting what the list contains, prove the community really did accept the reply --
	// otherwise an empty list would pass every check below by having nothing in it.
	$reply_create   = '' !== $reply_uri ? axismundi_act_get_object_lifecycle( $reply_uri ) : null;
	$announces      = $reply_create instanceof Axismundi_Activity ? axismundi_act_get_by_object( $reply_create->get_uri(), 10 ) : array();
	$reply_announce = null;
	foreach ( $announces as $candidate ) {
		if ( $candidate instanceof Axismundi_Activity && 'Announce' === $candidate->get_type() && $candidate->is_effective() && hash_equals( $group->get_uri(), $candidate->get_actor_uri() ) ) {
			$reply_announce = $candidate;
		}
	}
	ax_ga_assert(
		$ax_ga_results,
		'the community really did announce the reply, so an empty archive below would be a failure and not a coincidence',
		! is_wp_error( $admitted ) && ! is_wp_error( $reply_saved ) && '' !== $reply_uri && $reply_announce instanceof Axismundi_Activity
	);

	ax_ga_assert(
		$ax_ga_results,
		'an accepted reply appears in the community Comments collection',
		in_array( $reply_uri, $comments['uris'], true )
	);

	// A Topic is a Post. The same Announce ledger carries both, so the collection has to tell an
	// Article from a Note rather than listing whatever the Group ever distributed.
	ax_ga_assert(
		$ax_ga_results,
		'the Topic itself stays out of Comments even though the Group announced it too',
		'' !== $topic_uri && ! in_array( $topic_uri, $comments['uris'], true )
	);

	/*
	 * The delivery half of the same contract, which nothing had checked end to end. A reader on
	 * another server does not see a community's posts because we worked out that they follow
	 * somebody who posted here — they see them because they subscribed to the community and the
	 * Announce is addressed to its followers.
	 */
	$announce_inboxes = $reply_announce instanceof Axismundi_Activity && function_exists( 'axismundi_activitypub_bridge_activity_inboxes' )
		? axismundi_activitypub_bridge_activity_inboxes( $reply_announce )
		: array();
	$author_only_inboxes = $reply_create instanceof Axismundi_Activity && function_exists( 'axismundi_activitypub_bridge_activity_inboxes' )
		? axismundi_activitypub_bridge_activity_inboxes( $reply_create )
		: array();
	ax_ga_assert(
		$ax_ga_results,
		'a community Announce reaches a remote subscriber inbox, and the author own Create does not carry it there',
		! is_wp_error( $subscription )
			&& in_array( $remote_person_uri . '/inbox', $announce_inboxes, true )
			&& ! in_array( $remote_person_uri . '/inbox', $author_only_inboxes, true )
	);

	/*
	 * The case the acceptance rule exists for. After the Group withdraws its Announce the author's
	 * `Create` is untouched and the reply still names the Group as its audience, so anything
	 * reading the reply rather than the ledger would go on listing it.
	 */
	$undone = axismundi_act_record_source_activity(
		array( 'type' => 'Undo', 'actor' => $group->get_uri(), 'object' => $reply_announce instanceof Axismundi_Activity ? $reply_announce->get_uri() : '' ),
		'outbound',
		'axga-unannounce:' . ( $reply_announce instanceof Axismundi_Activity ? $reply_announce->get_uri() : '' )
	);
	ax_ga_refresh();
	$after_undo   = axismundi_forum_group_comment_uris( $group );
	$still_claims = is_array( axismundi_note_get( $reply ) );

	ax_ga_assert(
		$ax_ga_results,
		'a withdrawn reply leaves Comments while its own record still names the community',
		! is_wp_error( $undone ) && $still_claims && ! in_array( $reply_uri, $after_undo['uris'], true )
	);

	/*
	 * A Topic is projected as an Article, so the archive shows it as one — the same card a remote
	 * community's Topics already used, rather than a title on a line for ours and a card for
	 * theirs.
	 */
	$card_opts   = array( 'headingTag' => 'h3', 'interactions' => false );
	$public_card = axismundi_op_render_object_by_uri( $topic_uri, $card_opts + array( 'communityViewer' => $community ) );
	ax_ga_assert(
		$ax_ga_results,
		'a local Topic in a public community renders the same Article card a remote one does',
		'' !== $public_card && false !== strpos( $public_card, 'axismundi-object' )
	);

	/*
	 * The opt-in is a claim the filter checks rather than takes. A caller naming a community the
	 * Topic is not in gets the public answer back, so the argument cannot be used to open
	 * something by asserting the wrong thing about it.
	 */
	axismundi_forum_set_distribution_scope( $community, $owner, 'members' );
	$member_card    = axismundi_op_render_object_by_uri( $topic_uri, $card_opts + array( 'communityViewer' => $community ) );
	$member_can_read = axismundi_forum_can_read_topic( get_post( $topic ) );
	$wrong_community = axismundi_forum_open_community_topic_card( false, get_post( $topic ), array( 'communityViewer' => 99999901 ) );
	$card_gate_opens = axismundi_forum_open_community_topic_card( false, get_post( $topic ), array( 'communityViewer' => $community ) );
	wp_set_current_user( 0 );
	$stranger_card = axismundi_op_render_object_by_uri( $topic_uri, $card_opts + array( 'communityViewer' => $community ) );
	$stranger_gate = axismundi_forum_open_community_topic_card( false, get_post( $topic ), array( 'communityViewer' => $community ) );
	wp_set_current_user( $owner );
	axismundi_forum_set_distribution_scope( $community, $owner, 'public' );

	ax_ga_assert(
		$ax_ga_results,
		'a members-only Topic stays shut to a stranger, and naming the wrong community does not open it',
		false === $stranger_gate && '' === $stranger_card && false === $wrong_community
	);

	/*
	 * The limitation, asserted rather than left implicit. The card gate opens for an entitled
	 * member, but the view model underneath asks only whether the source is publicly visible and
	 * takes no argument from its caller — so the card is still empty and the archive falls back to
	 * the Topic title for that reader. When Object Projections grows a viewer-scoped view model
	 * this assertion is what will fail and say so.
	 */
	ax_ga_assert(
		$ax_ga_results,
		'an entitled member is allowed the card but still gets none, because the view model has no viewer-scoped seam yet',
		true === $member_can_read && true === $card_gate_opens && '' === $member_card
	);

	// The reader-facing surface: two collections, addressed separately, paged the same way.
	$filters = axismundi_forum_group_archive_filters();
	$tabs    = axismundi_forum_render_archive_tabs( 'comments' );
	ax_ga_assert(
		$ax_ga_results,
		'the archive offers Posts and Comments as two addressable collections with the active one marked',
		array( 'posts', 'comments' ) === array_keys( $filters )
			&& false !== strpos( $tabs, 'filter=comments' )
			&& false !== strpos( $tabs, 'aria-current="page"' )
	);

	ax_ga_assert(
		$ax_ga_results,
		'pagination links stay inside the collection the reader is in',
		false !== strpos( axismundi_forum_render_archive_pagination( 1, 3, 'comments' ), 'filter=comments' )
			&& false !== strpos( axismundi_forum_render_archive_pagination( 1, 3, 'comments' ), 'page=2' )
			// Posts is the default collection, so it says so by leaving the argument off.
			&& false === strpos( axismundi_forum_render_archive_pagination( 1, 3, 'posts' ), 'filter=' )
	);

	// Links to `topic_page` exist and must keep working, but `page` is what this surface says now.
	$ax_ga_defaulted = axismundi_forum_group_archive_page_number();
	$_GET['topic_page'] = '3';
	$ax_ga_legacy = axismundi_forum_group_archive_page_number();
	$_GET['page'] = '2';
	$ax_ga_current = axismundi_forum_group_archive_page_number();
	unset( $_GET['page'], $_GET['topic_page'] );
	ax_ga_assert(
		$ax_ga_results,
		'a legacy topic_page link still resolves, and the current page argument wins when both are present',
		1 === $ax_ga_defaulted && 3 === $ax_ga_legacy && 2 === $ax_ga_current
	);
} finally {
	foreach ( array_unique( $ax_ga_posts ) as $post_id ) {
		if ( get_post( (int) $post_id ) ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_filter( array_unique( $ax_ga_ids ) ) as $identity_id ) {
		$wpdb->delete( axismundi_forum_entries_table(), array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_forum_settings_table(), array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		foreach ( array( axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_managers_table(), axismundi_actors_actors_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	wp_set_current_user( 0 );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( array_filter( array_unique( $ax_ga_users ) ) as $user_id ) {
		if ( get_userdata( (int) $user_id ) ) {
			wp_delete_user( (int) $user_id );
		}
	}
}

$ax_ga_failed = count( array_filter( $ax_ga_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_ga_results ) - $ax_ga_failed, count( $ax_ga_results ) );
exit( $ax_ga_failed > 0 ? 1 : 0 );
