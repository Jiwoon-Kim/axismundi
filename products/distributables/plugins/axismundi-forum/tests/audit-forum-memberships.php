<?php
/**
 * Forum F2 membership-projection regression (dev-only; dist-excluded).
 *
 * Locks the boundary that Activities owns Follow state while Forum projects it only
 * for an inbound remote Actor following one bound local managed Group.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/managed-groups.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/relations.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/local-social.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/topics.php';
require_once __DIR__ . '/../includes/memberships.php';

axismundi_forum_install();

global $wpdb;
$ax_fm_results       = array();
$ax_fm_user_ids      = array();
$ax_fm_identity_ids  = array();
$ax_fm_post_ids      = array();
$ax_fm_activity_uris = array();

/** @param bool[] $results Results. */
function ax_fm_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** @return int Throwaway editor user id. */
function ax_fm_user( array &$user_ids ) : int {
	$user_id = (int) wp_insert_user(
		array(
			'user_login' => 'ax_fm_' . strtolower( wp_generate_password( 10, false, false ) ),
			'user_pass'  => wp_generate_password(),
			'role'       => 'editor',
		)
	);
	if ( $user_id > 0 ) {
		$user_ids[] = $user_id;
	}
	return $user_id;
}

/** @return Axismundi_Actor|null Activated public local Person fixture for one throwaway user. */
function ax_fm_local_person( array &$user_ids, array &$identity_ids ) : ?Axismundi_Actor {
	$login   = 'axfml' . strtolower( wp_generate_password( 9, false, false ) );
	$user_id = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'contributor' ) );
	if ( $user_id <= 0 ) {
		return null;
	}
	$user_ids[] = $user_id;
	$actor      = axismundi_actors_ensure_for_user( $user_id );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return null;
	}
	$identity_ids[] = $actor->get_identity_id();
	if ( is_wp_error( axismundi_actors_register_handle( $actor->get_identity_id(), $login ) )
		|| ! axismundi_actors_set_status( $actor->get_identity_id(), 'public' ) ) {
		return null;
	}
	return axismundi_actors_get_for_user( $user_id );
}

/** @return int Activities rows recording an Accept by one Actor, for federation-silence checks. */
function ax_fm_accept_count( string $actor_uri ) : int {
	global $wpdb;
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-scoped audit count.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE activity_type = 'Accept' AND actor_uri_hash = %s", hash( 'sha256', $actor_uri ) ) );
}

/** @return Axismundi_Actor|WP_Error Cached remote Person fixture with valid endpoints. */
function ax_fm_remote_person( array &$identity_ids, string $suffix ) {
	$uri = 'https://example.com/users/' . $suffix;
	$actor = axismundi_actors_upsert_remote(
		array(
			'uri'                => $uri,
			'actor_type'         => 'Person',
			'preferred_username' => $suffix,
			'display_name'       => $suffix,
			'profile_url'        => $uri,
			'endpoints'          => array( 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
			'payload'            => array( 'id' => $uri, 'type' => 'Person', 'preferredUsername' => $suffix, 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
		)
	);
	if ( $actor instanceof Axismundi_Actor ) {
		$identity_ids[] = $actor->get_identity_id();
	}
	return $actor;
}

try {
	$owner    = ax_fm_user( $ax_fm_user_ids );
	$stranger = ax_fm_user( $ax_fm_user_ids );
	$group = axismundi_actors_create_managed_group(
		array(
			'owner_user_id'      => $owner,
			'preferred_username' => 'axfm' . strtolower( wp_generate_password( 7, false, false ) ),
			'status'             => 'public',
		)
	);
	if ( $group instanceof Axismundi_Actor ) {
		$ax_fm_identity_ids[] = $group->get_identity_id();
	}
	// The Group identity *is* the community; there is no second record to create and bind.
	$community = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	$bound     = $community > 0 && axismundi_forum_is_community( $community );
	$remote_open = ax_fm_remote_person( $ax_fm_identity_ids, 'open_' . strtolower( wp_generate_password( 7, false, false ) ) );
	$follow_open_uri = 'https://example.com/activities/follow-' . wp_generate_uuid4();
	$follow_open = $group instanceof Axismundi_Actor && $remote_open instanceof Axismundi_Actor
		? axismundi_act_record_activity( array( 'id' => $follow_open_uri, 'type' => 'Follow', 'actor' => $remote_open->get_uri(), 'object' => $group->get_uri(), 'to' => array( $group->get_uri() ) ), 'inbound' )
		: new WP_Error( 'fixture' );
	$ax_fm_activity_uris[] = $follow_open_uri;
	$open_membership = $remote_open instanceof Axismundi_Actor ? axismundi_forum_get_membership( $community, $remote_open->get_identity_id() ) : null;
	$open_relation = $group instanceof Axismundi_Actor && $remote_open instanceof Axismundi_Actor ? axismundi_act_get_relation( 'follow', $remote_open->get_uri(), $group->get_uri() ) : null;
	ax_fm_assert(
		$ax_fm_results,
		'an open Forum turns an inbound remote Follow into an accepted membership through Activities Accept',
		true === $bound
			&& $follow_open instanceof Axismundi_Activity
			&& is_array( $open_relation)
			&& 'accepted' === (string) $open_relation['state']
			&& is_array( $open_membership)
			&& 'accepted' === (string) $open_membership['membership_state']
			&& $follow_open_uri === (string) $open_membership['membership_evidence_activity_uri']
	);

	$policy = axismundi_forum_set_membership_policy( $community, $owner, 'approval' );
	$remote_pending = ax_fm_remote_person( $ax_fm_identity_ids, 'pending_' . strtolower( wp_generate_password( 7, false, false ) ) );
	$follow_pending_uri = 'https://example.com/activities/follow-' . wp_generate_uuid4();
	$follow_pending = $group instanceof Axismundi_Actor && $remote_pending instanceof Axismundi_Actor
		? axismundi_act_record_activity( array( 'id' => $follow_pending_uri, 'type' => 'Follow', 'actor' => $remote_pending->get_uri(), 'object' => $group->get_uri(), 'to' => array( $group->get_uri() ) ), 'inbound' )
		: new WP_Error( 'fixture' );
	$ax_fm_activity_uris[] = $follow_pending_uri;
	$pending_membership = $remote_pending instanceof Axismundi_Actor ? axismundi_forum_get_membership( $community, $remote_pending->get_identity_id() ) : null;
	$pending_relation = $group instanceof Axismundi_Actor && $remote_pending instanceof Axismundi_Actor ? axismundi_act_get_relation( 'follow', $remote_pending->get_uri(), $group->get_uri() ) : null;
	ax_fm_assert(
		$ax_fm_results,
		'an approval Forum retains an inbound remote Follow as a pending membership request',
		true === $policy
			&& $follow_pending instanceof Axismundi_Activity
			&& is_array( $pending_relation)
			&& 'pending' === (string) $pending_relation['state']
			&& is_array( $pending_membership)
			&& 'pending' === (string) $pending_membership['membership_state']
			&& 1 === count( axismundi_forum_pending_memberships( $community ) )
	);

	$forbidden = $remote_pending instanceof Axismundi_Actor ? axismundi_forum_respond_to_membership_request( $community, $remote_pending->get_identity_id(), $stranger, 'accept' ) : new WP_Error( 'fixture' );
	$accepted = $remote_pending instanceof Axismundi_Actor ? axismundi_forum_respond_to_membership_request( $community, $remote_pending->get_identity_id(), $owner, 'accept' ) : new WP_Error( 'fixture' );
	$accepted_membership = $remote_pending instanceof Axismundi_Actor ? axismundi_forum_get_membership( $community, $remote_pending->get_identity_id() ) : null;
	ax_fm_assert(
		$ax_fm_results,
		'only the bound Group manager may accept a pending membership request',
		is_wp_error( $forbidden)
			&& 'ax_forum_forbidden' === $forbidden->get_error_code()
			&& is_array( $accepted)
			&& 'accepted' === (string) $accepted['state']
			&& is_array( $accepted_membership)
			&& 'accepted' === (string) $accepted_membership['membership_state']
	);

	$undo = $group instanceof Axismundi_Actor && $remote_pending instanceof Axismundi_Actor
		? axismundi_act_record_activity( array( 'id' => 'https://example.com/activities/undo-' . wp_generate_uuid4(), 'type' => 'Undo', 'actor' => $remote_pending->get_uri(), 'object' => $follow_pending_uri, 'to' => array( $group->get_uri() ) ), 'inbound' )
		: new WP_Error( 'fixture' );
	$undone_membership = $remote_pending instanceof Axismundi_Actor ? axismundi_forum_get_membership( $community, $remote_pending->get_identity_id() ) : null;
	ax_fm_assert(
		$ax_fm_results,
		'an inbound Undo(Follow) revokes the Forum membership projection without deleting either Actor',
		$undo instanceof Axismundi_Activity
			&& is_array( $undone_membership)
			&& 'undone' === (string) $undone_membership['membership_state']
			&& $group instanceof Axismundi_Actor
			&& $remote_pending instanceof Axismundi_Actor
	);

	// F1 seal 1 + 6: a local member of a local Group is a member, and approval holds them.
	$local_member   = ax_fm_local_person( $ax_fm_user_ids, $ax_fm_identity_ids );
	$local_relation = $group instanceof Axismundi_Actor && $local_member instanceof Axismundi_Actor
		? axismundi_act_follow_actor( $local_member, $group )
		: new WP_Error( 'fixture' );
	$local_membership = $local_member instanceof Axismundi_Actor ? axismundi_forum_get_membership( $community, $local_member->get_identity_id() ) : null;
	ax_fm_assert(
		$ax_fm_results,
		'a local Person may follow its own site managed Group and an approval Forum holds them pending',
		is_array( $local_relation )
			&& 'local' === (string) $local_relation['direction']
			&& 'pending' === (string) $local_relation['state']
			&& is_array( $local_membership )
			&& 'pending' === (string) $local_membership['membership_state']
	);

	// F1 seal 3 + 4: rebuild restores the projection from the ledger and sends nothing.
	$accepts_before = $group instanceof Axismundi_Actor ? ax_fm_accept_count( $group->get_uri() ) : -1;
	$wpdb->delete( axismundi_forum_memberships_table(), array( 'group_identity_id' => $community ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit wipes the projection to prove it is derivable.
	$emptied  = axismundi_forum_count_memberships( $community );
	$rebuilt  = axismundi_forum_rebuild_memberships( $community );
	$rebuilt_local  = $local_member instanceof Axismundi_Actor ? axismundi_forum_get_membership( $community, $local_member->get_identity_id() ) : null;
	$rebuilt_undone = $remote_pending instanceof Axismundi_Actor ? axismundi_forum_get_membership( $community, $remote_pending->get_identity_id() ) : null;
	$accepts_after  = $group instanceof Axismundi_Actor ? ax_fm_accept_count( $group->get_uri() ) : -2;
	ax_fm_assert(
		$ax_fm_results,
		'membership is rebuildable from the Activities relation ledger without federating anything',
		0 === $emptied
			&& is_array( $rebuilt )
			&& 3 === $rebuilt['members']
			&& 3 === $rebuilt['relations']
			&& $accepts_before === $accepts_after
			&& is_array( $rebuilt_local )
			&& 'pending' === (string) $rebuilt_local['membership_state']
			&& is_array( $rebuilt_undone )
			&& 'undone' === (string) $rebuilt_undone['membership_state']
	);

	// Rebuild replaces: a row the ledger no longer yields must not survive it.
	$orphan_ok = false !== $wpdb->insert(
		axismundi_forum_memberships_table(),
		array(
			'group_identity_id'                => $community,
			'actor_identity_id'                => 99999901,
			'membership_evidence_activity_uri' => 'https://example.com/activities/vanished',
			'membership_state'                 => 'accepted',
			'created_at'                       => current_time( 'mysql', true ),
			'updated_at'                       => current_time( 'mysql', true ),
		),
		array( '%d', '%d', '%s', '%s', '%s', '%s' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture for a stale projection row.
	$with_orphan = axismundi_forum_count_memberships( $community );
	axismundi_forum_rebuild_memberships( $community );
	ax_fm_assert(
		$ax_fm_results,
		'rebuild replaces the projection and drops a membership the ledger no longer yields',
		$orphan_ok
			&& 4 === $with_orphan
			&& 3 === axismundi_forum_count_memberships( $community )
			&& null === axismundi_forum_get_membership( $community, 99999901 )
	);

	// A Block aimed at the Group is a relation, but never evidence of membership.
	$blocker    = ax_fm_remote_person( $ax_fm_identity_ids, 'blocker_' . strtolower( wp_generate_password( 7, false, false ) ) );
	$block_uri  = 'https://example.com/activities/block-' . wp_generate_uuid4();
	$ax_fm_activity_uris[] = $block_uri;
	$block = $group instanceof Axismundi_Actor && $blocker instanceof Axismundi_Actor
		? axismundi_act_record_activity( array( 'id' => $block_uri, 'type' => 'Block', 'actor' => $blocker->get_uri(), 'object' => $group->get_uri() ), 'inbound' )
		: new WP_Error( 'fixture' );
	$after_block = axismundi_forum_rebuild_memberships( $community );
	ax_fm_assert(
		$ax_fm_results,
		'a Block relation aimed at the Group is excluded from the ledger read and never becomes membership',
		$block instanceof Axismundi_Activity
			&& is_array( $after_block )
			&& 3 === $after_block['relations']
			&& 3 === $after_block['members']
			&& $blocker instanceof Axismundi_Actor
			&& null === axismundi_forum_get_membership( $community, $blocker->get_identity_id() )
	);

	// The ledger read must page rather than truncate at its default page size.
	$ax_fm_bulk_uris = array();
	if ( $group instanceof Axismundi_Actor ) {
		for ( $i = 0; $i < 205; $i++ ) {
			$bulk_uri = 'https://example.com/users/bulk' . $i . '_' . strtolower( wp_generate_password( 6, false, false ) );
			$bulk_follow = 'https://example.com/activities/bulk-' . wp_generate_uuid4();
			$ax_fm_bulk_uris[] = $bulk_uri;
			$ax_fm_activity_uris[] = $bulk_follow;
			$bulk_actor = axismundi_actors_upsert_remote(
				array(
					'uri'                => $bulk_uri,
					'actor_type'         => 'Person',
					'preferred_username' => 'bulk' . $i,
					'display_name'       => 'bulk' . $i,
					'profile_url'        => $bulk_uri,
					'endpoints'          => array( 'inbox' => $bulk_uri . '/inbox', 'outbox' => $bulk_uri . '/outbox' ),
					'payload'            => array( 'id' => $bulk_uri, 'type' => 'Person', 'preferredUsername' => 'bulk' . $i, 'inbox' => $bulk_uri . '/inbox', 'outbox' => $bulk_uri . '/outbox' ),
				)
			);
			if ( $bulk_actor instanceof Axismundi_Actor ) {
				$ax_fm_identity_ids[] = $bulk_actor->get_identity_id();
			}
			axismundi_act_record_activity( array( 'id' => $bulk_follow, 'type' => 'Follow', 'actor' => $bulk_uri, 'object' => $group->get_uri(), 'to' => array( $group->get_uri() ) ), 'inbound' );
		}
	}
	$bulk_rebuild = axismundi_forum_rebuild_memberships( $community );
	ax_fm_assert(
		$ax_fm_results,
		'rebuild pages past the ledger read size instead of truncating at it',
		is_array( $bulk_rebuild )
			&& 208 === $bulk_rebuild['relations']
			&& 208 === $bulk_rebuild['members']
			&& 208 === axismundi_forum_count_memberships( $community )
	);

	// F1 seal 5: opening the Forum admits the whole queue, past any one page of it.
	$queued          = count( axismundi_forum_pending_memberships( $community, 200 ) );
	$opened          = axismundi_forum_set_membership_policy( $community, $owner, 'open' );
	$opened_local    = $local_member instanceof Axismundi_Actor ? axismundi_forum_get_membership( $community, $local_member->get_identity_id() ) : null;
	$opened_relation = $group instanceof Axismundi_Actor && $local_member instanceof Axismundi_Actor
		? axismundi_act_get_relation( 'follow', $local_member->get_uri(), $group->get_uri() )
		: null;
	ax_fm_assert(
		$ax_fm_results,
		'switching a Forum to open admits everyone already waiting, past one page of the queue',
		true === $opened
			&& 200 === $queued // The reporting page caps at 200; the real queue is 206.
			&& is_array( $opened_local )
			&& 'accepted' === (string) $opened_local['membership_state']
			&& is_array( $opened_relation )
			&& 'accepted' === (string) $opened_relation['state']
			&& 0 === count( axismundi_forum_pending_memberships( $community ) )
	);

	/*
	 * An Accept that cannot be recorded must leave the member queued rather than admitted here
	 * and pending on their own server. The failure is produced honestly: the evidence Activity
	 * is removed from the ledger, which is exactly what respond_to_local_follow() refuses to
	 * act on.
	 */
	$stuck   = ax_fm_remote_person( $ax_fm_identity_ids, 'stuck_' . strtolower( wp_generate_password( 7, false, false ) ) );
	$blocked = axismundi_forum_set_membership_policy( $community, $owner, 'approval' );
	$stuck_follow_uri = 'https://example.com/activities/follow-' . wp_generate_uuid4();
	$ax_fm_activity_uris[] = $stuck_follow_uri;
	if ( $group instanceof Axismundi_Actor && $stuck instanceof Axismundi_Actor ) {
		axismundi_act_record_activity( array( 'id' => $stuck_follow_uri, 'type' => 'Follow', 'actor' => $stuck->get_uri(), 'object' => $group->get_uri(), 'to' => array( $group->get_uri() ) ), 'inbound' );
	}
	$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri_hash' => hash( 'sha256', $stuck_follow_uri ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit forces the Accept to fail.
	$refused = axismundi_forum_set_membership_policy( $community, $owner, 'open' );
	$stuck_membership = $stuck instanceof Axismundi_Actor ? axismundi_forum_get_membership( $community, $stuck->get_identity_id() ) : null;
	$stuck_relation   = $group instanceof Axismundi_Actor && $stuck instanceof Axismundi_Actor ? axismundi_act_get_relation( 'follow', $stuck->get_uri(), $group->get_uri() ) : null;
	ax_fm_assert(
		$ax_fm_results,
		'a member whose Accept cannot be recorded stays pending in both the ledger and the projection',
		true === $blocked
			&& true === $refused
			&& is_array( $stuck_relation )
			&& 'pending' === (string) $stuck_relation['state']
			&& is_array( $stuck_membership )
			&& 'pending' === (string) $stuck_membership['membership_state']
	);
} finally {
	$memberships = axismundi_forum_memberships_table();
	$settings    = axismundi_forum_settings_table();
	$activities  = axismundi_act_activities_table();
	$relations   = axismundi_act_relations_table();
	$identities  = axismundi_actors_identities_table();
	$actors      = axismundi_actors_actors_table();
	$endpoints   = axismundi_actors_endpoints_table();
	$managers    = axismundi_actors_managers_table();
	foreach ( array_unique( $ax_fm_post_ids ) as $post_id ) {
		if ( get_post( (int) $post_id ) ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_unique( $ax_fm_activity_uris ) as $activity_uri ) {
		$wpdb->delete( $activities, array( 'activity_uri_hash' => hash( 'sha256', $activity_uri ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( array_unique( $ax_fm_identity_ids ) as $identity_id ) {
		// Forum rows are keyed by the Group identity now, so they are cleaned up here.
		$wpdb->delete( $memberships, array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $settings, array( 'group_identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$actor = axismundi_actors_get_by_identity( (int) $identity_id );
		if ( $actor instanceof Axismundi_Actor ) {
			$wpdb->delete( $relations, array( 'subject_actor_uri_hash' => hash( 'sha256', $actor->get_uri() ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
			$wpdb->delete( $relations, array( 'object_actor_uri_hash' => hash( 'sha256', $actor->get_uri() ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		$wpdb->delete( $endpoints, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $managers, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $actors, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( $identities, array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	if ( ! empty( $ax_fm_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_fm_user_ids ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_fm_failed = count( array_filter( $ax_fm_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_fm_results ) - $ax_fm_failed, count( $ax_fm_results ) );
exit( $ax_fm_failed > 0 ? 1 : 0 );
