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

/** @return Axismundi_Activity|WP_Error The original Create that a Group may wrap unchanged. */
function axismundi_forum_entry_create_activity( array $entry ) {
	$accepted_uri = (string) ( $entry['accepted_activity_uri'] ?? '' );
	if ( '' !== $accepted_uri && function_exists( 'axismundi_act_get' ) ) {
		$accepted = axismundi_act_get( $accepted_uri );
		if ( $accepted instanceof Axismundi_Activity && 'Create' === $accepted->get_type() && $accepted->is_effective() && is_array( $accepted->get_payload()['object'] ?? null ) ) {
			return $accepted;
		}
	}
	if ( ! function_exists( 'axismundi_act_get_by_object' ) ) {
		return new WP_Error( 'ax_forum_announce_create', __( 'The original Topic Create is unavailable.', 'axismundi-forum' ) );
	}
	foreach ( axismundi_act_get_by_object( (string) ( $entry['object_uri'] ?? '' ), 50 ) as $activity ) {
		if ( $activity instanceof Axismundi_Activity && 'Create' === $activity->get_type() && $activity->is_effective() && is_array( $activity->get_payload()['object'] ?? null ) ) {
			return $activity;
		}
	}
	return new WP_Error( 'ax_forum_announce_create', __( 'The original Topic Create is unavailable.', 'axismundi-forum' ) );
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
	$create = axismundi_forum_entry_create_activity( $entry );
	if ( is_wp_error( $create ) ) {
		return $create;
	}
	$followers = axismundi_op_actor_followers_url( $group );
	if ( '' === $followers ) {
		return new WP_Error( 'ax_forum_announce_followers', __( 'The community followers collection is unavailable.', 'axismundi-forum' ) );
	}
	/*
	 * An undone Announce must be replaceable by a fresh Announce of the same Create. The source
	 * key therefore names a distribution cycle, not just the entry/Create pair. Reusing the old
	 * key would return the ineffective historical row and falsely make a re-approved entry look
	 * distributed again.
	 */
	$cycle = function_exists( 'axismundi_act_announce_cycle_count' )
		? 1 + axismundi_act_announce_cycle_count( $group->get_uri(), $create->get_uri() )
		: 1;
	return axismundi_act_record_source_activity(
		array(
			'type'   => 'Announce',
			'actor'  => $group->get_uri(),
			// FEP-1b12 requires the original incoming or local Create as received, not its Object URI.
			'object' => $create->get_payload(),
			'to'     => array( $followers ),
		),
		'outbound',
		'forum-group-announce:' . (int) $entry['id'] . ':create:' . $create->get_uri() . ':cycle:' . $cycle
	);
}

/**
 * Withdraw an approved Topic from this community without deleting the author's Object.
 *
 * The Group itself owns the prior Announce, so it sends Undo(Announce) directly to the same
 * followers collection. The entry returns to the same pending queue used before its first
 * approval, so a later moderator decision can publish a new Announce cycle of the original Create.
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
		array( 'id' => $entry_id ),
		array( '%s', '%s' ),
		array( '%d' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ledger-first Group distribution withdrawal.
	return false === $updated ? new WP_Error( 'ax_forum_withdraw_write', __( 'The withdrawn Topic could not return to pending review.', 'axismundi-forum' ) ) : true;
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
	$announce = axismundi_forum_record_group_announce( $entry );
	if ( is_wp_error( $announce ) ) {
		return $announce;
	}
	global $wpdb;
	$updated = $wpdb->update(
		axismundi_forum_entries_table(),
		array( 'admission_state' => 'visible', 'announced_activity_uri' => $announce->get_uri(), 'updated_at' => current_time( 'mysql', true ) ),
		array( 'id' => $entry_id ),
		array( '%s', '%s', '%s' ),
		array( '%d' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ledger-first transition of one pending Group entry.
	return false === $updated ? new WP_Error( 'ax_forum_announce_write', __( 'The approved Topic could not be made visible.', 'axismundi-forum' ) ) : true;
}
