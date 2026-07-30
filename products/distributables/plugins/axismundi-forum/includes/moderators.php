<?php
/**
 * Actor-scoped community moderation roles.
 *
 * A managed-Group manager is a local WordPress login delegation. A moderator is an Actor
 * relationship that can be published through the Group's attributedTo collection. The
 * effective set intentionally includes both without storing the former as a duplicate row.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/** @return bool Whether one membership role is valid. */
function axismundi_forum_valid_membership_role( string $role ) : bool {
	return in_array( $role, array( 'member', 'moderator' ), true );
}

/** @return bool Whether this Actor is a published local Person usable on the wire. */
function axismundi_forum_public_local_person( $actor ) : bool {
	return $actor instanceof Axismundi_Actor
		&& $actor->is_local()
		&& 'Person' === $actor->get_type()
		&& 'public' === $actor->get_status()
		&& $actor->is_handle_locked();
}

/** @return bool Whether a local user is a manager or an explicitly assigned Actor moderator. */
function axismundi_forum_user_can_moderate( int $group_identity_id, int $user_id ) : bool {
	if ( $group_identity_id <= 0 || $user_id <= 0 || ! axismundi_forum_is_community( $group_identity_id ) ) {
		return false;
	}
	if ( axismundi_forum_user_can_manage( $group_identity_id, $user_id ) ) {
		return true;
	}
	$actor = function_exists( 'axismundi_actors_get_for_user' ) ? axismundi_actors_get_for_user( $user_id ) : null;
	return $actor instanceof Axismundi_Actor && axismundi_forum_actor_is_moderator( $group_identity_id, $actor );
}

/** @return bool Whether this Actor has explicit or derived Group moderation authority. */
function axismundi_forum_actor_is_moderator( int $group_identity_id, Axismundi_Actor $actor ) : bool {
	if ( $group_identity_id <= 0 || ! axismundi_forum_is_community( $group_identity_id ) ) {
		return false;
	}
	$membership = axismundi_forum_get_membership( $group_identity_id, $actor->get_identity_id() );
	if ( is_array( $membership ) && 'accepted' === (string) $membership['membership_state'] && 'moderator' === (string) $membership['membership_role'] ) {
		return true;
	}
	$user_id = $actor->get_local_user_id();
	return null !== $user_id && axismundi_forum_user_can_manage( $group_identity_id, $user_id );
}

/**
 * Grant or revoke an explicit Actor moderator role.
 *
 * The caller is a local Group manager. The subject must already be an accepted member: Add
 * does not silently admit someone, and the membership projection stays the membership source.
 */
function axismundi_forum_set_actor_moderator( int $group_identity_id, int $actor_identity_id, int $user_id, bool $moderator ) {
	if ( ! axismundi_forum_user_can_manage( $group_identity_id, $user_id ) ) {
		return new WP_Error( 'ax_forum_forbidden', __( 'You do not manage this Forum Group.', 'axismundi-forum' ) );
	}
	$membership = axismundi_forum_get_membership( $group_identity_id, $actor_identity_id );
	if ( ! is_array( $membership ) || 'accepted' !== (string) $membership['membership_state'] ) {
		return new WP_Error( 'ax_forum_moderator_member', __( 'Only an accepted community member may become a moderator.', 'axismundi-forum' ) );
	}
	$role = $moderator ? 'moderator' : 'member';
	if ( $role === (string) $membership['membership_role'] ) {
		return true;
	}
	global $wpdb;
	$updated = $wpdb->update(
		axismundi_forum_memberships_table(),
		array( 'membership_role' => $role, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'group_identity_id' => $group_identity_id, 'actor_identity_id' => $actor_identity_id ),
		array( '%s', '%s' ),
		array( '%d', '%d' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- manager-authorized Group role transition.
	return false === $updated ? new WP_Error( 'ax_forum_moderator_write', __( 'The moderator role could not be saved.', 'axismundi-forum' ) ) : true;
}

/**
 * Actor collection for FEP-1b12 attributedTo.
 *
 * Explicit moderator memberships and local manager delegation are merged here. WordPress users
 * without a public Person Actor never leak into federation metadata.
 *
 * @return array<int,Axismundi_Actor> Unique public Person Actors keyed by identity id.
 */
function axismundi_forum_effective_moderators( int $group_identity_id ) : array {
	if ( ! axismundi_forum_is_community( $group_identity_id ) ) {
		return array();
	}
	$moderators = array();
	global $wpdb;
	$table = axismundi_forum_memberships_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed explicit moderator lookup.
	$ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT actor_identity_id FROM {$table} WHERE group_identity_id = %d AND membership_state = 'accepted' AND membership_role = 'moderator'", $group_identity_id ) );
	foreach ( $ids as $identity_id ) {
		$actor = function_exists( 'axismundi_actors_get_by_identity' ) ? axismundi_actors_get_by_identity( (int) $identity_id ) : null;
		if ( axismundi_forum_public_local_person( $actor ) || ( $actor instanceof Axismundi_Actor && ! $actor->is_local() && 'Person' === $actor->get_type() ) ) {
			$moderators[ $actor->get_identity_id() ] = $actor;
		}
	}
	foreach ( function_exists( 'axismundi_actors_group_managers' ) ? (array) axismundi_actors_group_managers( $group_identity_id ) : array() as $manager ) {
		$actor = function_exists( 'axismundi_actors_get_for_user' ) ? axismundi_actors_get_for_user( (int) ( $manager['user_id'] ?? 0 ) ) : null;
		if ( axismundi_forum_public_local_person( $actor ) ) {
			$moderators[ $actor->get_identity_id() ] = $actor;
		}
	}
	return $moderators;
}
