<?php
/**
 * FEP-1b12 Group distribution of approved Topic submissions.
 *
 * This is intentionally separate from personal boosts. A Group Announce wraps the original
 * Activity and addresses the Group followers collection. Its withdrawal is the Group's direct
 * Undo of that Announce, not a Delete of the author's Object and not a moderator-collection
 * Remove.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/** @return array<string,string> Group Announce delivery scopes. */
function axismundi_forum_distribution_scopes() : array {
	return array(
		'public'  => __( 'Public', 'axismundi-forum' ),
		'members' => __( 'Community members', 'axismundi-forum' ),
	);
}

/** Read one Group's distribution scope, defaulting all newly public Groups to public. */
function axismundi_forum_get_distribution_scope( int $group_identity_id ) : string {
	$community = axismundi_forum_get_community( $group_identity_id );
	$scope = is_array( $community ) ? (string) ( $community['distribution_scope'] ?? '' ) : '';
	return array_key_exists( $scope, axismundi_forum_distribution_scopes() ) ? $scope : 'public';
}

/** Update one Group's distribution scope after its manager authorizes the policy change. */
function axismundi_forum_set_distribution_scope( int $group_identity_id, int $user_id, string $scope ) {
	if ( ! array_key_exists( $scope, axismundi_forum_distribution_scopes() ) ) {
		return new WP_Error( 'ax_forum_distribution_scope', __( 'The community distribution scope is invalid.', 'axismundi-forum' ) );
	}
	if ( $scope === axismundi_forum_get_distribution_scope( $group_identity_id ) ) {
		return true;
	}
	return axismundi_forum_update_community_policy( $group_identity_id, $user_id, 'distribution_scope', $scope );
}

/** Return the exact Group Announce audience for one configured community. */
function axismundi_forum_distribution_audience( Axismundi_Actor $group ) {
	if ( ! function_exists( 'axismundi_op_actor_followers_url' ) ) {
		return new WP_Error( 'ax_forum_announce_followers', __( 'The community followers collection is unavailable.', 'axismundi-forum' ) );
	}
	$followers = axismundi_op_actor_followers_url( $group );
	if ( '' === $followers ) {
		return new WP_Error( 'ax_forum_announce_followers', __( 'The community followers collection is unavailable.', 'axismundi-forum' ) );
	}
	if ( 'members' === axismundi_forum_get_distribution_scope( $group->get_identity_id() ) ) {
		return array( 'to' => array( $followers ), 'cc' => array() );
	}
	$public = function_exists( 'axismundi_act_public_audience_uri' )
		? axismundi_act_public_audience_uri()
		: 'https://www.w3.org/ns/activitystreams#Public';
	return array( 'to' => array( $public ), 'cc' => array( $followers ) );
}

/** @return Axismundi_Activity|WP_Error The immutable Create or Update the Group may wrap unchanged. */
function axismundi_forum_entry_submission_activity( array $entry ) {
	$submission_uri = (string) ( $entry['accepted_activity_uri'] ?? '' );
	if ( '' !== $submission_uri && function_exists( 'axismundi_act_get' ) ) {
		$submission = axismundi_act_get( $submission_uri );
		if ( $submission instanceof Axismundi_Activity && in_array( $submission->get_type(), array( 'Create', 'Update' ), true ) && $submission->is_effective() && is_array( $submission->get_payload()['object'] ?? null ) ) {
			return $submission;
		}
	}
	if ( ! function_exists( 'axismundi_act_get_by_object' ) ) {
		return new WP_Error( 'ax_forum_announce_submission', __( 'The original Topic submission is unavailable.', 'axismundi-forum' ) );
	}
	foreach ( axismundi_act_get_by_object( (string) ( $entry['object_uri'] ?? '' ), 50 ) as $activity ) {
		if ( $activity instanceof Axismundi_Activity && in_array( $activity->get_type(), array( 'Create', 'Update' ), true ) && $activity->is_effective() && is_array( $activity->get_payload()['object'] ?? null ) ) {
			return $activity;
		}
	}
	return new WP_Error( 'ax_forum_announce_submission', __( 'The original Topic submission is unavailable.', 'axismundi-forum' ) );
}

/** Refresh a local pending entry to its current immutable Create or Update before approval. */
function axismundi_forum_refresh_local_entry_submission( array $entry ) {
	$source_post_id = (int) ( $entry['source_post_id'] ?? 0 );
	if ( $source_post_id <= 0 ) {
		return $entry;
	}
	$topic = get_post( $source_post_id );
	if ( ! $topic instanceof WP_Post || AXISMUNDI_FORUM_TOPIC_POST_TYPE !== $topic->post_type || ! function_exists( 'axismundi_forum_record_topic_commit' ) ) {
		return new WP_Error( 'ax_forum_announce_submission', __( 'The local Topic submission is unavailable.', 'axismundi-forum' ) );
	}
	$submission = axismundi_forum_record_topic_commit( $topic );
	if ( is_wp_error( $submission ) ) {
		return $submission;
	}
	if ( ! $submission instanceof Axismundi_Activity ) {
		return new WP_Error( 'ax_forum_announce_submission', __( 'The local Topic submission could not be recorded.', 'axismundi-forum' ) );
	}
	if ( $submission->get_uri() === (string) ( $entry['accepted_activity_uri'] ?? '' ) ) {
		return $entry;
	}
	global $wpdb;
	$updated = $wpdb->update(
		axismundi_forum_entries_table(),
		array( 'accepted_activity_uri' => $submission->get_uri(), 'updated_at' => current_time( 'mysql', true ) ),
		array( 'id' => (int) $entry['id'] ),
		array( '%s', '%s' ),
		array( '%d' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- approval must point at the exact lifecycle Activity the Group will distribute.
	if ( false === $updated ) {
		return new WP_Error( 'ax_forum_announce_submission', __( 'The Topic submission could not be linked to its current Activity.', 'axismundi-forum' ) );
	}
	$entry['accepted_activity_uri'] = $submission->get_uri();
	return $entry;
}

/** Record the Group Announce for one validated entry, returning an existing effective row. */
function axismundi_forum_record_group_announce( array $entry ) {
	$group = axismundi_forum_get_community_group( (int) ( $entry['group_identity_id'] ?? 0 ) );
	if ( ! $group instanceof Axismundi_Actor || ! $group->is_local() || 'public' !== $group->get_status()
		|| ! function_exists( 'axismundi_act_record_source_activity' ) || ! function_exists( 'axismundi_op_actor_followers_url' ) ) {
		return new WP_Error( 'ax_forum_announce_group', __( 'The local community Group cannot distribute this Topic.', 'axismundi-forum' ) );
	}
	$existing_uri = (string) ( $entry['announced_activity_uri'] ?? '' );
	$existing = '' !== $existing_uri && function_exists( 'axismundi_act_get' ) ? axismundi_act_get( $existing_uri ) : null;
	if ( $existing instanceof Axismundi_Activity && 'Announce' === $existing->get_type() && $existing->is_effective() && hash_equals( $group->get_uri(), $existing->get_actor_uri() ) ) {
		return $existing;
	}
	$submission = axismundi_forum_entry_submission_activity( $entry );
	if ( is_wp_error( $submission ) ) {
		return $submission;
	}
	$audience = axismundi_forum_distribution_audience( $group );
	if ( is_wp_error( $audience ) ) {
		return $audience;
	}
	/*
	 * An undone Announce must be replaceable by a fresh Announce of the same submission. The source
	 * key therefore names a distribution cycle, not just the entry/Create pair. Reusing the old
	 * key would return the ineffective historical row and falsely make a re-approved entry look
	 * distributed again.
	 */
	$cycle = function_exists( 'axismundi_act_announce_cycle_count' )
		? 1 + axismundi_act_announce_cycle_count( $group->get_uri(), $submission->get_uri() )
		: 1;
	return axismundi_act_record_source_activity(
		array(
			'type'   => 'Announce',
			'actor'  => $group->get_uri(),
			// FEP-1b12 requires the original incoming or local Activity as received, not its Object URI.
			'object' => $submission->get_payload(),
			'to'     => $audience['to'],
			'cc'     => $audience['cc'],
		),
		'outbound',
		'forum-group-announce:' . (int) $entry['id'] . ':submission:' . $submission->get_uri() . ':cycle:' . $cycle
	);
}

/**
 * Withdraw an approved Topic from this community without deleting the author's Object.
 *
 * The Group itself owns the prior Announce, so it sends Undo(Announce) directly to the same
 * followers collection. The entry returns to the same pending queue used before its first
 * approval, so a later moderator decision can publish a new Announce cycle of the current
 * Create or Update submission.
 */
function axismundi_forum_withdraw_announced_entry( int $entry_id, int $user_id ) {
	$entry = axismundi_forum_get_entry( $entry_id );
	if ( ! is_array( $entry ) || 'visible' !== (string) $entry['admission_state'] ) {
		return new WP_Error( 'ax_forum_announced_entry', __( 'The Topic is not currently published by this community.', 'axismundi-forum' ) );
	}
	if ( ! function_exists( 'axismundi_forum_user_can_moderate' ) || ! axismundi_forum_user_can_moderate( (int) $entry['group_identity_id'], $user_id ) ) {
		return new WP_Error( 'ax_forum_forbidden', __( 'You may not withdraw this community Topic.', 'axismundi-forum' ) );
	}
	$group = axismundi_forum_get_community_group( (int) $entry['group_identity_id'] );
	$announce = function_exists( 'axismundi_act_get' ) ? axismundi_act_get( (string) ( $entry['announced_activity_uri'] ?? '' ) ) : null;
	if ( ! $group instanceof Axismundi_Actor || ! $announce instanceof Axismundi_Activity || 'Announce' !== $announce->get_type() || ! $announce->is_effective() || ! hash_equals( $group->get_uri(), $announce->get_actor_uri() )
		|| ! function_exists( 'axismundi_act_record_source_activity' ) ) {
		return new WP_Error( 'ax_forum_announce_missing', __( 'The active community Announce is unavailable.', 'axismundi-forum' ) );
	}
	$undo = axismundi_act_record_source_activity(
		array( 'type' => 'Undo', 'actor' => $group->get_uri(), 'object' => $announce->get_uri(), 'to' => (array) ( $announce->get_audience()['to'] ?? array() ), 'cc' => (array) ( $announce->get_audience()['cc'] ?? array() ) ),
		'outbound',
		'forum-group-withdraw-undo:' . $entry_id . ':announce:' . $announce->get_uri()
	);
	if ( is_wp_error( $undo ) ) {
		return $undo;
	}
	global $wpdb;
	$updated = $wpdb->update(
		axismundi_forum_entries_table(),
		array( 'admission_state' => 'pending', 'updated_at' => current_time( 'mysql', true ) ),
		array( 'id' => (int) $entry['id'] ),
		array( '%s', '%s' ),
		array( '%d' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ledger-first Group distribution withdrawal.
	return false === $updated ? new WP_Error( 'ax_forum_withdraw_write', __( 'The withdrawn Topic could not return to pending review.', 'axismundi-forum' ) ) : true;
}

/**
 * Publish one already-validated pending entry through its Group Announce.
 *
 * Callers own validation and authorization. This function owns the atomic semantic transition:
 * a Topic becomes visible only after its Group has an effective distribution Activity.
 */
function axismundi_forum_publish_validated_pending_entry( array $entry ) {
	if ( 'pending' !== (string) ( $entry['admission_state'] ?? '' ) ) {
		return new WP_Error( 'ax_forum_pending_entry', __( 'The Topic is not awaiting community publication.', 'axismundi-forum' ) );
	}
	$announce = axismundi_forum_record_group_announce( $entry );
	if ( is_wp_error( $announce ) ) {
		return $announce;
	}
	global $wpdb;
	$updated = $wpdb->update(
		axismundi_forum_entries_table(),
		array( 'admission_state' => 'visible', 'announced_activity_uri' => $announce->get_uri(), 'updated_at' => current_time( 'mysql', true ) ),
		array( 'id' => (int) $entry['id'] ),
		array( '%s', '%s', '%s' ),
		array( '%d' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ledger-first transition of one pending Group entry.
	return false === $updated ? new WP_Error( 'ax_forum_announce_write', __( 'The approved Topic could not be made visible.', 'axismundi-forum' ) ) : true;
}

/**
 * Approve a pending Topic for Group distribution.
 *
 * The Group Announce is committed first. If it cannot be recorded, the entry remains pending
 * and no local list claims the Group approved it.
 */
function axismundi_forum_approve_pending_entry( int $entry_id, int $user_id ) {
	$entry = axismundi_forum_get_entry( $entry_id );
	if ( ! is_array( $entry ) || 'pending' !== (string) $entry['admission_state'] ) {
		return new WP_Error( 'ax_forum_pending_entry', __( 'The Topic is not awaiting community approval.', 'axismundi-forum' ) );
	}
	if ( ! function_exists( 'axismundi_forum_user_can_moderate' ) || ! axismundi_forum_user_can_moderate( (int) $entry['group_identity_id'], $user_id ) ) {
		return new WP_Error( 'ax_forum_forbidden', __( 'You may not approve this community Topic.', 'axismundi-forum' ) );
	}
	$entry = axismundi_forum_refresh_local_entry_submission( $entry );
	return is_wp_error( $entry ) ? $entry : axismundi_forum_publish_validated_pending_entry( $entry );
}

/**
 * Reject one pending Topic submission back to its original Person Actor.
 *
 * A rejection answers the submitted Create or Update, never the Topic Object. The Group's
 * ledger record is committed first; if the projection write fails, a later retry reuses that
 * immutable Reject and can finish moving the entry out of the review queue.
 */
function axismundi_forum_reject_pending_entry( int $entry_id, int $user_id, string $reason = '' ) {
	$reason = wp_kses_post( $reason );
	if ( '' === trim( wp_strip_all_tags( $reason ) ) ) {
		return new WP_Error( 'ax_forum_reject_reason', __( 'A rejection reason is required.', 'axismundi-forum' ) );
	}
	$entry = axismundi_forum_get_entry( $entry_id );
	if ( ! is_array( $entry ) || 'pending' !== (string) $entry['admission_state'] ) {
		return new WP_Error( 'ax_forum_pending_entry', __( 'The Topic is not awaiting community approval.', 'axismundi-forum' ) );
	}
	if ( ! function_exists( 'axismundi_forum_user_can_moderate' ) || ! axismundi_forum_user_can_moderate( (int) $entry['group_identity_id'], $user_id ) ) {
		return new WP_Error( 'ax_forum_forbidden', __( 'You may not reject this community Topic.', 'axismundi-forum' ) );
	}
	$group = axismundi_forum_get_community_group( (int) $entry['group_identity_id'] );
	$submission = axismundi_forum_entry_submission_activity( $entry );
	if ( ! $group instanceof Axismundi_Actor || ! function_exists( 'axismundi_act_record_source_activity' ) || is_wp_error( $submission ) ) {
		return is_wp_error( $submission ) ? $submission : new WP_Error( 'ax_forum_reject_group', __( 'The community Group cannot reject this Topic.', 'axismundi-forum' ) );
	}
	$author_uri = $submission->get_actor_uri();
	if ( '' === $author_uri ) {
		return new WP_Error( 'ax_forum_reject_author', __( 'The submitting Actor is unavailable.', 'axismundi-forum' ) );
	}
	$rejection = axismundi_act_record_source_activity(
		array(
			'type'     => 'Reject',
			'actor'    => $group->get_uri(),
			'object'   => $submission->get_uri(),
			// The Activity Vocabulary's Reject example uses summary for the human-readable
			// explanation; `object` remains the precise Create or Update the Group rejected.
			'summary'  => $reason,
			'to'       => array( $author_uri ),
			'audience' => $group->get_uri(),
		),
		'outbound',
		'forum-group-reject:entry:' . $entry_id . ':submission:' . $submission->get_uri()
	);
	if ( is_wp_error( $rejection ) ) {
		return $rejection;
	}
	global $wpdb;
	$updated = $wpdb->update(
		axismundi_forum_entries_table(),
		array( 'admission_state' => 'rejected', 'updated_at' => current_time( 'mysql', true ) ),
		array( 'id' => $entry_id ),
		array( '%s', '%s' ),
		array( '%d' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ledger-first decision projection.
	return false === $updated ? new WP_Error( 'ax_forum_reject_write', __( 'The rejected Topic could not leave the review queue.', 'axismundi-forum' ) ) : true;
}
