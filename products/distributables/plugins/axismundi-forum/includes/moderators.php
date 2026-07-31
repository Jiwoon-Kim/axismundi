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

/** Record the actor-authored moderation activity and Group wrapper for one role transition. */
function axismundi_forum_record_moderator_change( Axismundi_Actor $group, Axismundi_Actor $subject, int $user_id, bool $moderator ) {
	$actor = function_exists( 'axismundi_actors_get_for_user' ) ? axismundi_actors_get_for_user( $user_id ) : null;
	if ( ! axismundi_forum_public_local_person( $actor ) || ! axismundi_forum_actor_is_moderator( $group->get_identity_id(), $actor )
		|| ! function_exists( 'axismundi_act_record_source_activity' ) || ! function_exists( 'axismundi_op_actor_followers_url' ) ) {
		return new WP_Error( 'ax_forum_moderator_actor', __( 'A public community moderator Actor is required for this change.', 'axismundi-forum' ) );
	}
	$followers = axismundi_op_actor_followers_url( $group );
	$target = axismundi_forum_moderator_collection_url( $group );
	if ( '' === $followers || '' === $target ) {
		return new WP_Error( 'ax_forum_moderator_collection', __( 'The community moderator collection is unavailable.', 'axismundi-forum' ) );
	}
	$type = $moderator ? 'Add' : 'Remove';
	$public = function_exists( 'axismundi_act_public_audience_uri' )
		? axismundi_act_public_audience_uri()
		: 'https://www.w3.org/ns/activitystreams#Public';
	$inner = axismundi_act_record_source_activity(
		array(
			'type'     => $type,
			'actor'    => $actor->get_uri(),
			'object'   => $subject->get_uri(),
			'target'   => $target,
			'to'       => array( $public ),
			'cc'       => array( $group->get_uri() ),
			'audience' => $group->get_uri(),
		),
		'outbound',
		'forum-moderator-' . strtolower( $type ) . ':group:' . $group->get_identity_id() . ':subject:' . $subject->get_identity_id()
	);
	if ( is_wp_error( $inner ) ) {
		return $inner;
	}
	return axismundi_act_record_source_activity(
		array( 'type' => 'Announce', 'actor' => $group->get_uri(), 'object' => $inner->get_payload(), 'to' => array( $followers ) ),
		'outbound',
		'forum-moderator-announce:' . strtolower( $type ) . ':group:' . $group->get_identity_id() . ':inner:' . $inner->get_uri()
	);
}

/**
 * Grant or revoke an explicit Actor moderator role.
 *
 * The caller is a local Group manager. The subject must already be an accepted member: Add
 * does not silently admit someone, and the membership projection stays the membership source.
 */
function axismundi_forum_set_actor_moderator( int $group_identity_id, int $actor_identity_id, int $user_id, bool $moderator ) {
	if ( ! function_exists( 'axismundi_actors_managed_actor_can_manage' ) || ! axismundi_actors_managed_actor_can_manage( $group_identity_id, $user_id, 'manager' ) ) {
		return new WP_Error( 'ax_forum_forbidden', __( 'You may not change community moderators.', 'axismundi-forum' ) );
	}
	$membership = axismundi_forum_get_membership( $group_identity_id, $actor_identity_id );
	if ( ! is_array( $membership ) || 'accepted' !== (string) $membership['membership_state'] ) {
		return new WP_Error( 'ax_forum_moderator_member', __( 'Only an accepted community member may become a moderator.', 'axismundi-forum' ) );
	}
	$group = axismundi_forum_get_community_group( $group_identity_id );
	$subject = function_exists( 'axismundi_actors_get_by_identity' ) ? axismundi_actors_get_by_identity( $actor_identity_id ) : null;
	if ( ! $group instanceof Axismundi_Actor || ! $subject instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_forum_moderator_actor', __( 'The community or member Actor is unavailable.', 'axismundi-forum' ) );
	}
	$subject_user_id = $subject->get_local_user_id();
	if ( null !== $subject_user_id && axismundi_forum_user_can_manage( $group_identity_id, $subject_user_id ) ) {
		return $moderator
			? true
			: new WP_Error( 'ax_forum_moderator_manager', __( 'A community manager is always a moderator. Remove their manager delegation instead.', 'axismundi-forum' ) );
	}
	$role = $moderator ? 'moderator' : 'member';
	if ( $role === (string) $membership['membership_role'] ) {
		return true;
	}
	$activity = axismundi_forum_record_moderator_change( $group, $subject, $user_id, $moderator );
	if ( is_wp_error( $activity ) ) {
		return $activity;
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

/** Promote an explicit local Actor moderator into the local managed-Group manager relation. */
function axismundi_forum_promote_moderator_to_manager( int $group_identity_id, int $actor_identity_id, int $user_id ) {
	if ( ! function_exists( 'axismundi_actors_managed_actor_can_manage' ) || ! axismundi_actors_managed_actor_can_manage( $group_identity_id, $user_id, 'manager' ) ) {
		return new WP_Error( 'ax_forum_forbidden', __( 'You may not delegate community management.', 'axismundi-forum' ) );
	}
	$actor = function_exists( 'axismundi_actors_get_by_identity' ) ? axismundi_actors_get_by_identity( $actor_identity_id ) : null;
	$user_id_to_add = $actor instanceof Axismundi_Actor ? $actor->get_local_user_id() : null;
	if ( ! axismundi_forum_public_local_person( $actor ) || null === $user_id_to_add || ! axismundi_forum_actor_is_moderator( $group_identity_id, $actor ) ) {
		return new WP_Error( 'ax_forum_manager_moderator', __( 'Only a local public community moderator may become a manager.', 'axismundi-forum' ) );
	}
	return axismundi_actors_add_manager( $group_identity_id, $user_id_to_add, 'manager' );
}

/** Remove a non-owner local manager while preserving Actor moderation separately. */
function axismundi_forum_remove_moderator_manager( int $group_identity_id, int $actor_identity_id, int $user_id ) {
	if ( ! function_exists( 'axismundi_actors_managed_actor_can_manage' ) || ! axismundi_actors_managed_actor_can_manage( $group_identity_id, $user_id, 'manager' ) ) {
		return new WP_Error( 'ax_forum_forbidden', __( 'You may not delegate community management.', 'axismundi-forum' ) );
	}
	$actor = function_exists( 'axismundi_actors_get_by_identity' ) ? axismundi_actors_get_by_identity( $actor_identity_id ) : null;
	$target_user_id = $actor instanceof Axismundi_Actor ? $actor->get_local_user_id() : null;
	if ( ! axismundi_forum_public_local_person( $actor ) || null === $target_user_id || ! axismundi_forum_actor_is_moderator( $group_identity_id, $actor ) ) {
		return new WP_Error( 'ax_forum_manager_moderator', __( 'Only a local public community moderator may be removed as a manager.', 'axismundi-forum' ) );
	}
	$role = '';
	foreach ( (array) axismundi_actors_group_managers( $group_identity_id ) as $manager ) {
		if ( $target_user_id === (int) ( $manager['user_id'] ?? 0 ) ) {
			$role = (string) ( $manager['role'] ?? '' );
			break;
		}
	}
	if ( 'manager' !== $role ) {
		return new WP_Error( 'ax_forum_manager_role', __( 'Only a non-owner manager may be removed here.', 'axismundi-forum' ) );
	}
	return axismundi_actors_remove_manager( $group_identity_id, $target_user_id );
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

/** @return string Stable public moderator collection URI for one local Group. */
function axismundi_forum_moderator_collection_url( Axismundi_Actor $group ) : string {
	return rest_url( 'axismundi/v1/forum/groups/' . rawurlencode( $group->get_uuid() ) . '/moderators' );
}

/** Add FEP-1b12 attributedTo only to local public community Groups. */
function axismundi_forum_project_group_moderators( array $object, Axismundi_Actor $actor ) : array {
	if ( axismundi_forum_public_community_group( $actor ) ) {
		$object['attributedTo'] = axismundi_forum_moderator_collection_url( $actor );
	}
	return $object;
}
add_filter( 'axismundi_op_actor_projection_fields', 'axismundi_forum_project_group_moderators', 20, 2 );

/** Serve the public Actor collection FEP-1b12 uses to identify Group moderators. */
function axismundi_forum_get_moderator_collection( WP_REST_Request $request ) {
	$group = function_exists( 'axismundi_actors_get_by_uuid' ) ? axismundi_actors_get_by_uuid( strtolower( (string) $request['uuid'] ) ) : null;
	if ( ! axismundi_forum_public_community_group( $group ) ) {
		return new WP_Error( 'ax_forum_moderators_not_found', __( 'The community moderators collection was not found.', 'axismundi-forum' ), array( 'status' => 404 ) );
	}
	$items = array_map( static fn( Axismundi_Actor $actor ) : string => $actor->get_uri(), axismundi_forum_effective_moderators( $group->get_identity_id() ) );
	$response = rest_ensure_response(
		array(
			'id'           => axismundi_forum_moderator_collection_url( $group ),
			'type'         => 'OrderedCollection',
			'attributedTo' => $group->get_uri(),
			'totalItems'   => count( $items ),
			'orderedItems' => array_values( $items ),
		)
	);
	$response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );
	$response->header( 'Cache-Control', 'public, max-age=60' );
	return $response;
}

/** Register the Forum-owned collection route alongside the Actor projection routes. */
function axismundi_forum_register_moderator_collection_route() : void {
	register_rest_route(
		'axismundi/v1',
		'/forum/groups/(?P<uuid>[0-9a-f-]{36})/moderators',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_forum_get_moderator_collection',
			'permission_callback' => '__return_true',
			'args'                => array( 'uuid' => array( 'required' => true, 'type' => 'string', 'pattern' => '^[0-9a-f-]{36}$' ) ),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_forum_register_moderator_collection_route' );
