<?php
/**
 * FEP-1b12 Group distribution of approved Topic submissions.
 *
 * This is intentionally separate from personal boosts. A Group Announce wraps the original
 * Activity, addresses the Group followers collection, and has no shares or interaction lease
 * semantics. Its withdrawal is an Undo of this outer Announce, not a Delete of the author's
 * Object and not a moderator-collection Remove.
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
	return axismundi_act_record_source_activity(
		array(
			'type'   => 'Announce',
			'actor'  => $group->get_uri(),
			// FEP-1b12 requires the original incoming or local Create as received, not its Object URI.
			'object' => $create->get_payload(),
			'to'     => array( $followers ),
		),
		'outbound',
		'forum-group-announce:' . (int) $entry['id'] . ':create:' . $create->get_uri()
	);
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
