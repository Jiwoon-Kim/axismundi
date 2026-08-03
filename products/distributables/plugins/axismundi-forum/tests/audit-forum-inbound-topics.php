<?php
/**
 * Forum F2 remote Page-admission regression (dev-only; dist-excluded).
 *
 * A verified public inbound Create is cached by Object Projections first. Forum
 * may then add only an accepted remote member's Page addressed to its bound Group.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-object-projections/includes/remote-objects.php';
require_once WP_PLUGIN_DIR . '/axismundi-object-projections/includes/inbox-observations.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/topics.php';
require_once __DIR__ . '/../includes/memberships.php';
require_once __DIR__ . '/../includes/inbound-topics.php';
require_once __DIR__ . '/../includes/distribution.php';
require_once __DIR__ . '/../includes/group-archive.php';

axismundi_forum_install();

global $wpdb;
$ax_fit_results       = array();
$ax_fit_user_ids      = array();
$ax_fit_identity_ids  = array();
$ax_fit_post_ids      = array();
$ax_fit_activity_uris = array();
$ax_fit_object_uris   = array();

/** @param bool[] $results Results. */
function ax_fit_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

function ax_fit_user( array &$user_ids ) : int {
	$user_id = (int) wp_insert_user( array( 'user_login' => 'ax_fit_' . strtolower( wp_generate_password( 10, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
	if ( $user_id > 0 ) {
		$user_ids[] = $user_id;
	}
	return $user_id;
}


function ax_fit_remote_person( array &$identity_ids, string $suffix ) {
	$uri = 'https://example.com/users/' . $suffix;
	$actor = axismundi_actors_upsert_remote(
		array(
			'uri' => $uri, 'actor_type' => 'Person', 'preferred_username' => $suffix, 'display_name' => $suffix, 'profile_url' => $uri,
			'endpoints' => array( 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
			'payload' => array( 'id' => $uri, 'type' => 'Person', 'preferredUsername' => $suffix, 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
		)
	);
	if ( $actor instanceof Axismundi_Actor ) {
		$identity_ids[] = $actor->get_identity_id();
	}
	return $actor;
}

/** Record a public remote Page Create. */
function ax_fit_create( Axismundi_Actor $author, string $object_uri, array $object_extra = array() ) {
	$object = array_merge(
		array(
			'id' => $object_uri, 'type' => 'Page', 'attributedTo' => $author->get_uri(), 'name' => 'Remote topic',
			'content' => '<p>Remote Forum topic.</p>', 'mediaType' => 'text/html',
			'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		),
		$object_extra
	);
	return axismundi_act_record_activity(
		array(
			'id' => $object_uri . '/activity', 'type' => 'Create', 'actor' => $author->get_uri(), 'object' => $object,
			'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		),
		'inbound'
	);
}

try {
	$owner = ax_fit_user( $ax_fit_user_ids );
	$group = axismundi_actors_create_managed_group( array( 'owner_user_id' => $owner, 'preferred_username' => 'axfit' . strtolower( wp_generate_password( 7, false, false ) ), 'status' => 'public' ) );
	if ( $group instanceof Axismundi_Actor ) {
		$ax_fit_identity_ids[] = $group->get_identity_id();
	}
	$community = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	$bound = $community > 0 && axismundi_forum_is_community( $community );
	$member = ax_fit_remote_person( $ax_fit_identity_ids, 'member_' . strtolower( wp_generate_password( 7, false, false ) ) );
	$outsider = ax_fit_remote_person( $ax_fit_identity_ids, 'outsider_' . strtolower( wp_generate_password( 7, false, false ) ) );
	$membership = $member instanceof Axismundi_Actor ? axismundi_forum_write_membership( $community, $member->get_identity_id(), 'accepted', 'https://example.com/activities/follow-' . wp_generate_uuid4() ) : new WP_Error( 'fixture' );

	$accepted_uri = 'https://example.com/pages/accepted-' . wp_generate_uuid4();
	$accepted = $member instanceof Axismundi_Actor && $group instanceof Axismundi_Actor
		? ax_fit_create( $member, $accepted_uri, array( 'audience' => $group->get_uri(), 'context' => $group->get_uri() ) )
		: new WP_Error( 'fixture' );
	$ax_fit_activity_uris[] = $accepted_uri . '/activity';
	$ax_fit_object_uris[] = $accepted_uri;
	$accepted_entry = axismundi_forum_get_remote_entry( $community, $accepted_uri );
	$accepted_announce = is_array( $accepted_entry ) ? axismundi_act_get( (string) ( $accepted_entry['announced_activity_uri'] ?? '' ) ) : null;
	ax_fit_assert(
		$ax_fit_results,
		'an accepted remote Group follower is admitted only through a local Group Announce preserving the inbound Create',
		true === $bound && true === $membership && $accepted instanceof Axismundi_Activity
			&& is_array( axismundi_op_get_remote_object( $accepted_uri ) ) && is_array( $accepted_entry )
			&& 'topic' === (string) $accepted_entry['entry_type'] && null === $accepted_entry['source_post_id']
			&& $member instanceof Axismundi_Actor && $member->get_identity_id() === (int) $accepted_entry['submission_actor_identity_id']
			&& $accepted_uri === (string) $accepted_entry['object_uri']
			&& 'visible' === (string) $accepted_entry['admission_state'] && $accepted_announce instanceof Axismundi_Activity
			&& $group instanceof Axismundi_Actor && $group->get_uri() === $accepted_announce->get_actor_uri()
			&& $accepted->get_payload() === (array) ( $accepted_announce->get_payload()['object'] ?? array() )
	);
	ax_fit_assert(
		$ax_fit_results,
		'the Forum mixed Topic query includes the admitted remote Page without inventing a local Topic post',
		1 === count( axismundi_forum_visible_topic_entries( $community ) ) && $accepted_uri === (string) axismundi_forum_visible_topic_entries( $community )[0]['object_uri']
	);
	$card = function_exists( 'axismundi_op_render_object_by_uri' ) && $member instanceof Axismundi_Actor
		? axismundi_op_render_object_by_uri( $accepted_uri, array( 'headingTag' => 'h3', 'interactions' => false, 'expected_author' => $member->get_uri() ) )
		: '';
	ax_fit_assert(
		$ax_fit_results,
		'the admitted remote Page resolves through the shared public Object Card renderer used by the Forum list',
		'' !== $card && str_contains( wp_strip_all_tags( $card ), 'Remote topic' )
	);

	$reply_uri = 'https://example.com/comments/accepted-' . wp_generate_uuid4();
	$reply = $member instanceof Axismundi_Actor && $group instanceof Axismundi_Actor
		? ax_fit_create( $member, $reply_uri, array( 'type' => 'Note', 'name' => '', 'inReplyTo' => $accepted_uri, 'audience' => $group->get_uri(), 'context' => $group->get_uri() ) )
		: new WP_Error( 'fixture' );
	$ax_fit_activity_uris[] = $reply_uri . '/activity';
	$ax_fit_object_uris[] = $reply_uri;
	$reply_announces = $reply instanceof Axismundi_Activity ? axismundi_act_get_by_object( $reply->get_uri(), 10 ) : array();
	$reply_announce = null;
	foreach ( $reply_announces as $candidate ) {
		if ( $candidate instanceof Axismundi_Activity && 'Announce' === $candidate->get_type() && $candidate->is_effective() && $group instanceof Axismundi_Actor && hash_equals( $group->get_uri(), $candidate->get_actor_uri() ) ) {
			$reply_announce = $candidate;
			$ax_fit_activity_uris[] = $candidate->get_uri();
			break;
		}
	}
	$reply_comments = $group instanceof Axismundi_Actor ? axismundi_forum_group_comment_uris( $group ) : array( 'uris' => array() );
	$reply_entry = axismundi_forum_get_remote_entry( $community, $reply_uri );
	ax_fit_assert(
		$ax_fit_results,
		'an accepted remote member Note reply is preserved in the Group Announce ledger and appears in the community Comments collection',
		$reply instanceof Axismundi_Activity && is_array( $reply_entry ) && 'reply' === (string) $reply_entry['entry_type'] && 'visible' === (string) $reply_entry['admission_state'] && $reply_announce instanceof Axismundi_Activity
			&& $reply->get_payload() === (array) ( $reply_announce->get_payload()['object'] ?? array() )
			&& in_array( $reply_uri, (array) $reply_comments['uris'], true )
	);

	$comment_approval = axismundi_forum_set_comment_approval_policy( $community, $owner, 'approval' );
	$pending_reply_uri = 'https://example.com/comments/pending-' . wp_generate_uuid4();
	$pending_reply = $member instanceof Axismundi_Actor && $group instanceof Axismundi_Actor && true === $comment_approval
		? ax_fit_create( $member, $pending_reply_uri, array( 'type' => 'Note', 'name' => '', 'inReplyTo' => $accepted_uri, 'audience' => $group->get_uri(), 'context' => $group->get_uri() ) )
		: new WP_Error( 'fixture' );
	$ax_fit_activity_uris[] = $pending_reply_uri . '/activity';
	$ax_fit_object_uris[] = $pending_reply_uri;
	$pending_reply_entry = axismundi_forum_get_remote_entry( $community, $pending_reply_uri );
	$pending_comment_count = count( axismundi_forum_pending_comment_entries( $community ) );
	$approved_reply = is_array( $pending_reply_entry ) ? axismundi_forum_approve_pending_entry( (int) $pending_reply_entry['id'], $owner ) : new WP_Error( 'fixture' );
	$approved_reply_entry = axismundi_forum_get_remote_entry( $community, $pending_reply_uri );
	ax_fit_assert(
		$ax_fit_results,
		'a Comment approval policy queues a reply separately, then moderator approval records its Group Announce',
		$pending_reply instanceof Axismundi_Activity && is_array( $pending_reply_entry ) && 'reply' === (string) $pending_reply_entry['entry_type'] && 'pending' === (string) $pending_reply_entry['admission_state']
			&& 1 === $pending_comment_count && true === $approved_reply && is_array( $approved_reply_entry ) && 'visible' === (string) $approved_reply_entry['admission_state'] && ! empty( $approved_reply_entry['announced_activity_uri'] )
	);
	axismundi_forum_set_comment_approval_policy( $community, $owner, 'open' );

	remove_action( 'axismundi_op_remote_object_observed', 'axismundi_forum_observe_remote_root', 20 );
	$cached_reply_uri = 'https://example.com/comments/cached-' . wp_generate_uuid4();
	$cached_reply = $member instanceof Axismundi_Actor && $group instanceof Axismundi_Actor
		? ax_fit_create( $member, $cached_reply_uri, array( 'type' => 'Note', 'name' => '', 'inReplyTo' => $accepted_uri, 'audience' => $group->get_uri(), 'context' => $group->get_uri() ) )
		: new WP_Error( 'fixture' );
	add_action( 'axismundi_op_remote_object_observed', 'axismundi_forum_observe_remote_root', 20, 2 );
	$ax_fit_activity_uris[] = $cached_reply_uri . '/activity';
	$ax_fit_object_uris[] = $cached_reply_uri;
	$cached_row = $cached_reply instanceof Axismundi_Activity ? axismundi_op_get_remote_object( $cached_reply_uri ) : null;
	$cached_before = $cached_reply instanceof Axismundi_Activity ? axismundi_act_get_by_object( $cached_reply->get_uri(), 10 ) : array();
	if ( is_array( $cached_row ) ) {
		axismundi_forum_observe_fetched_remote_reply( $cached_row );
	}
	$cached_after = $cached_reply instanceof Axismundi_Activity ? axismundi_act_get_by_object( $cached_reply->get_uri(), 10 ) : array();
	$cached_announce = null;
	foreach ( $cached_after as $candidate ) {
		if ( $candidate instanceof Axismundi_Activity && 'Announce' === $candidate->get_type() && $candidate->is_effective() && $group instanceof Axismundi_Actor && hash_equals( $group->get_uri(), $candidate->get_actor_uri() ) ) {
			$cached_announce = $candidate;
			$ax_fit_activity_uris[] = $candidate->get_uri();
			break;
		}
	}
	ax_fit_assert(
		$ax_fit_results,
		'a refetch replays an already-cached inbound Note through its original verified Create without inventing another submission',
		$cached_reply instanceof Axismundi_Activity && is_array( $cached_row ) && empty( $cached_before ) && $cached_announce instanceof Axismundi_Activity
	);

	$outsider_uri = 'https://example.com/pages/outsider-' . wp_generate_uuid4();
	$outsider_create = $outsider instanceof Axismundi_Actor && $group instanceof Axismundi_Actor
		? ax_fit_create( $outsider, $outsider_uri, array( 'audience' => $group->get_uri() ) )
		: new WP_Error( 'fixture' );
	$ax_fit_activity_uris[] = $outsider_uri . '/activity';
	$ax_fit_object_uris[] = $outsider_uri;
	ax_fit_assert(
		$ax_fit_results,
		'a public Page from a non-member is cached but never becomes a Forum entry',
		$outsider_create instanceof Axismundi_Activity && is_array( axismundi_op_get_remote_object( $outsider_uri ) ) && null === axismundi_forum_get_remote_entry( $community, $outsider_uri )
	);

	$outsider_reply_uri = 'https://example.com/comments/outsider-' . wp_generate_uuid4();
	$members_only_comments = axismundi_forum_set_comment_posting_policy( $community, $owner, 'members' );
	$outsider_reply = $outsider instanceof Axismundi_Actor && $group instanceof Axismundi_Actor
		? ax_fit_create( $outsider, $outsider_reply_uri, array( 'type' => 'Note', 'name' => '', 'inReplyTo' => $accepted_uri, 'audience' => $group->get_uri(), 'context' => $group->get_uri() ) )
		: new WP_Error( 'fixture' );
	$ax_fit_activity_uris[] = $outsider_reply_uri . '/activity';
	$ax_fit_object_uris[] = $outsider_reply_uri;
	$outsider_reply_announces = $outsider_reply instanceof Axismundi_Activity ? axismundi_act_get_by_object( $outsider_reply->get_uri(), 10 ) : array();
	ax_fit_assert(
		$ax_fit_results,
		'a non-member remote Note reply is cached but never gains a local Group Announce',
		true === $members_only_comments && $outsider_reply instanceof Axismundi_Activity && empty( $outsider_reply_announces ) && null === axismundi_forum_get_remote_entry( $community, $outsider_reply_uri )
	);

	$elsewhere_uri = 'https://example.com/pages/elsewhere-' . wp_generate_uuid4();
	$elsewhere_create = $member instanceof Axismundi_Actor ? ax_fit_create( $member, $elsewhere_uri, array( 'audience' => 'https://example.com/groups/elsewhere' ) ) : new WP_Error( 'fixture' );
	$ax_fit_activity_uris[] = $elsewhere_uri . '/activity';
	$ax_fit_object_uris[] = $elsewhere_uri;
	ax_fit_assert(
		$ax_fit_results,
		'an accepted member cannot inject a Page that omits this Group from its audience or context',
		$elsewhere_create instanceof Axismundi_Activity && null === axismundi_forum_get_remote_entry( $community, $elsewhere_uri )
	);

	$approval_policy = axismundi_forum_set_topic_approval_policy( $community, $owner, 'approval' );
	$pending_uri = 'https://example.com/pages/pending-' . wp_generate_uuid4();
	$pending = $member instanceof Axismundi_Actor && $group instanceof Axismundi_Actor && true === $approval_policy
		? ax_fit_create( $member, $pending_uri, array( 'audience' => $group->get_uri(), 'context' => $group->get_uri() ) )
		: new WP_Error( 'fixture' );
	$ax_fit_activity_uris[] = $pending_uri . '/activity';
	$ax_fit_object_uris[] = $pending_uri;
	$pending_entry = axismundi_forum_get_remote_entry( $community, $pending_uri );
	$missing_reason = is_array( $pending_entry ) && function_exists( 'axismundi_forum_reject_pending_entry' )
		? axismundi_forum_reject_pending_entry( (int) $pending_entry['id'], $owner )
		: new WP_Error( 'fixture' );
	$rejected = is_array( $pending_entry ) && function_exists( 'axismundi_forum_reject_pending_entry' )
		? axismundi_forum_reject_pending_entry( (int) $pending_entry['id'], $owner, 'Off-topic for this community.' )
		: new WP_Error( 'fixture' );
	$rejected_entry = axismundi_forum_get_remote_entry( $community, $pending_uri );
	$rejects = $pending instanceof Axismundi_Activity ? axismundi_act_get_by_object( $pending->get_uri(), 10 ) : array();
	$reject = null;
	foreach ( $rejects as $candidate ) {
		if ( $candidate instanceof Axismundi_Activity && 'Reject' === $candidate->get_type() && $candidate->is_effective() ) {
			$reject = $candidate;
			$ax_fit_activity_uris[] = $candidate->get_uri();
			break;
		}
	}
	ax_fit_assert(
		$ax_fit_results,
		'a moderator must state a reason, then rejects a pending remote Topic by answering its original Create, removes it from review, and never announces it to followers',
		$pending instanceof Axismundi_Activity && is_array( $pending_entry ) && 'pending' === (string) $pending_entry['admission_state']
			&& is_wp_error( $missing_reason ) && 'ax_forum_reject_reason' === $missing_reason->get_error_code()
			&& true === $rejected && is_array( $rejected_entry ) && 'rejected' === (string) $rejected_entry['admission_state']
			&& $reject instanceof Axismundi_Activity && $group instanceof Axismundi_Actor && $member instanceof Axismundi_Actor
			&& $group->get_uri() === $reject->get_actor_uri() && $pending->get_uri() === $reject->get_object_uri()
			&& in_array( $member->get_uri(), (array) ( $reject->get_audience()['to'] ?? array() ), true )
			&& 'Off-topic for this community.' === (string) ( $reject->get_payload()['summary'] ?? '' )
			&& empty( $rejected_entry['announced_activity_uri'] ) && empty( axismundi_forum_pending_topic_entries( $community ) )
	);
} finally {
	$entries = axismundi_forum_entries_table();
	$memberships = axismundi_forum_memberships_table();
	$settings = axismundi_forum_settings_table();
	$activities = axismundi_act_activities_table();
	$remote_objects = axismundi_op_remote_objects_table();
	$identities = axismundi_actors_identities_table();
	$actors = axismundi_actors_actors_table();
	$endpoints = axismundi_actors_endpoints_table();
	$managers = axismundi_actors_managers_table();
	foreach ( array_unique( $ax_fit_post_ids ) as $post_id ) {
		if ( get_post( (int) $post_id ) ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_unique( $ax_fit_activity_uris ) as $activity_uri ) {
		$wpdb->delete( $activities, array( 'activity_uri_hash' => hash( 'sha256', $activity_uri ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( array_unique( $ax_fit_object_uris ) as $object_uri ) {
		$wpdb->delete( $remote_objects, array( 'object_uri_hash' => hash( 'sha256', $object_uri ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( array_unique( $ax_fit_identity_ids ) as $identity_id ) {
		// Forum projections are keyed by the Group identity, so they belong in this loop.
		$wpdb->delete( $entries, array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $memberships, array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $settings, array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $endpoints, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $managers, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $actors, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $identities, array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	if ( ! empty( $ax_fit_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_fit_user_ids ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_fit_failed = count( array_filter( $ax_fit_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_fit_results ) - $ax_fit_failed, count( $ax_fit_results ) );
exit( $ax_fit_failed > 0 ? 1 : 0 );
