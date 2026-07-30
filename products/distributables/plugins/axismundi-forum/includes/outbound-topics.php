<?php
/**
 * Outbound Topic Pages addressed to cached remote Group Actors.
 *
 * A remote community is never copied into ax_forum. It is an Actors-registry
 * destination selected by a local Topic source and evidenced by the author's own
 * outbound Follow relation.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/** @return array<string,mixed>|null The author-to-Group Follow relation eligible for submission. */
function axismundi_forum_remote_group_follow( Axismundi_Actor $author, Axismundi_Actor $group ) : ?array {
	if ( ! function_exists( 'axismundi_act_get_relation' ) || ! $author->is_local() || 'Person' !== $author->get_type() || $group->is_local() || 'Group' !== $group->get_type() ) {
		return null;
	}
	$relation = axismundi_act_get_relation( 'follow', $author->get_uri(), $group->get_uri() );
	return is_array( $relation ) && 'outbound' === (string) $relation['direction'] && in_array( (string) $relation['state'], array( 'pending', 'accepted', 'legacy_pending' ), true ) ? $relation : null;
}

/** Whether this WordPress user may submit a Page as their public Person to this remote Group. */
function axismundi_forum_user_can_submit_to_remote_group( int $user_id, Axismundi_Actor $group ) : bool {
	$author = function_exists( 'axismundi_actors_get_for_user' ) ? axismundi_actors_get_for_user( $user_id ) : null;
	return $user_id > 0
		&& user_can( $user_id, 'edit_posts' )
		&& $author instanceof Axismundi_Actor
		&& $author->is_local()
		&& 'Person' === $author->get_type()
		&& 'public' === $author->get_status()
		&& $author->is_handle_locked()
		&& null !== axismundi_forum_remote_group_follow( $author, $group );
}

/**
 * Cached remote Groups this user's public Person currently follows and may address.
 *
 * Activities owns the following projection. Forum only filters it down to Group Actors that
 * pass its own submission gate; it does not query the relation table or infer a relationship
 * from a cached Group record.
 *
 * @return array<int,Axismundi_Actor> Remote Groups keyed by identity id.
 */
function axismundi_forum_followed_remote_groups_for_user( int $user_id ) : array {
	$author = function_exists( 'axismundi_actors_get_for_user' ) ? axismundi_actors_get_for_user( $user_id ) : null;
	if ( ! $author instanceof Axismundi_Actor || ! $author->is_local() || 'Person' !== $author->get_type() || ! function_exists( 'axismundi_act_get_follow_collection_page' ) ) {
		return array();
	}
	$groups = array();
	for ( $page = 1; ; ++$page ) {
		$following = axismundi_act_get_follow_collection_page( 'subject', $author->get_uri(), $page, 100 );
		foreach ( (array) ( $following['items'] ?? array() ) as $uri ) {
			$group = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( (string) $uri ) : null;
			if ( $group instanceof Axismundi_Actor && ! $group->is_local() && 'Group' === $group->get_type() && 'tombstone' !== $group->get_status() && axismundi_forum_user_can_submit_to_remote_group( $user_id, $group ) ) {
				$groups[ $group->get_identity_id() ] = $group;
			}
		}
		if ( empty( $following['has_more'] ) ) {
			break;
		}
	}
	return $groups;
}

/** Bind an unadmitted local Topic source to exactly one eligible remote Group. */
function axismundi_forum_bind_remote_topic_group( int $topic_post_id, int $user_id, int $group_identity_id ) {
	$topic = get_post( $topic_post_id );
	$group = function_exists( 'axismundi_actors_get_by_identity' ) ? axismundi_actors_get_by_identity( $group_identity_id ) : null;
	if ( ! $topic instanceof WP_Post || AXISMUNDI_FORUM_TOPIC_POST_TYPE !== $topic->post_type || ! $group instanceof Axismundi_Actor || $group->is_local() || 'Group' !== $group->get_type() || 'tombstone' === $group->get_status() ) {
		return new WP_Error( 'ax_forum_remote_topic_target', __( 'A cached remote Group and local Topic are required.', 'axismundi-forum' ) );
	}
	if ( null !== axismundi_forum_get_topic_entry( $topic_post_id ) || (int) $topic->post_author !== $user_id || ! user_can( $user_id, 'edit_post', $topic_post_id ) || ! axismundi_forum_user_can_submit_to_remote_group( $user_id, $group ) ) {
		return new WP_Error( 'ax_forum_remote_topic_forbidden', __( 'You may not bind this Topic to that remote Group.', 'axismundi-forum' ) );
	}
	$existing = (int) get_post_meta( $topic_post_id, AXISMUNDI_FORUM_REMOTE_GROUP_META, true );
	if ( $existing > 0 && $existing !== $group_identity_id ) {
		return new WP_Error( 'ax_forum_remote_topic_context', __( 'A Topic cannot change its remote Group context.', 'axismundi-forum' ) );
	}
	if ( $existing === $group_identity_id ) {
		return true;
	}
	return false === update_post_meta( $topic_post_id, AXISMUNDI_FORUM_REMOTE_GROUP_META, $group_identity_id )
		? new WP_Error( 'ax_forum_remote_topic_write', __( 'The remote Group context could not be saved.', 'axismundi-forum' ) )
		: true;
}

/** Resolve the one Group a submitted local Topic addresses. */
function axismundi_forum_get_topic_destination_group( WP_Post $topic ) : ?Axismundi_Actor {
	$entry = axismundi_forum_get_topic_entry( $topic->ID );
	if ( is_array( $entry ) ) {
		$group = axismundi_forum_get_community_group( (int) $entry['group_identity_id'] );
		return $group instanceof Axismundi_Actor ? $group : null;
	}
	return axismundi_forum_get_remote_topic_group( $topic );
}

/** Record the direct Create or Update submitted by a Person Actor to one Group Actor. */
function axismundi_forum_record_topic_commit( WP_Post $topic ) {
	$group = axismundi_forum_get_topic_destination_group( $topic );
	if ( ! $group instanceof Axismundi_Actor || ! function_exists( 'axismundi_act_record_object_commit' ) || ! function_exists( 'axismundi_op_finalize_object' ) ) {
		return new WP_Error( 'ax_forum_topic_context', __( 'The Topic has no eligible Group context.', 'axismundi-forum' ) );
	}
	if ( ! $group->is_local() && ! axismundi_forum_user_can_submit_to_remote_group( (int) $topic->post_author, $group ) ) {
		return new WP_Error( 'ax_forum_remote_topic_follow', __( 'The author does not have an active remote Group Follow.', 'axismundi-forum' ) );
	}
	$object = axismundi_forum_topic_to_article( $topic );
	if ( is_wp_error( $object ) ) {
		return $object;
	}
	$object = axismundi_op_finalize_object( $object, axismundi_forum_topic_object_uri( $topic ) );
	return is_wp_error( $object ) ? $object : axismundi_act_record_object_commit( $object );
}

/** Backward-compatible name for the remote-only caller. */
function axismundi_forum_record_remote_topic_commit( WP_Post $topic ) {
	return axismundi_forum_record_topic_commit( $topic );
}

/** Create and publish one remote-community Topic through the only supported authoring API. */
function axismundi_forum_create_remote_topic( int $user_id, int $group_identity_id, array $fields = array() ) {
	$group = function_exists( 'axismundi_actors_get_by_identity' ) ? axismundi_actors_get_by_identity( $group_identity_id ) : null;
	if ( ! $group instanceof Axismundi_Actor || ! axismundi_forum_user_can_submit_to_remote_group( $user_id, $group ) ) {
		return new WP_Error( 'ax_forum_remote_topic_forbidden', __( 'You may not submit to that remote Group.', 'axismundi-forum' ) );
	}
	$topic_id = wp_insert_post(
		array(
			'post_type' => AXISMUNDI_FORUM_TOPIC_POST_TYPE, 'post_status' => 'draft', 'post_author' => $user_id,
			'post_title' => sanitize_text_field( (string) ( $fields['title'] ?? '' ) ),
			'post_content' => wp_kses_post( (string) ( $fields['content'] ?? '' ) ),
			'post_excerpt' => sanitize_textarea_field( (string) ( $fields['excerpt'] ?? '' ) ),
		),
		true
	);
	if ( is_wp_error( $topic_id ) ) {
		return $topic_id;
	}
	$bound = axismundi_forum_bind_remote_topic_group( (int) $topic_id, $user_id, $group_identity_id );
	if ( is_wp_error( $bound ) ) {
		wp_delete_post( (int) $topic_id, true );
		return $bound;
	}
	$published = wp_update_post( array( 'ID' => (int) $topic_id, 'post_status' => 'publish' ), true );
	if ( is_wp_error( $published ) ) {
		return $published;
	}
	$topic = get_post( (int) $topic_id );
	$result = $topic instanceof WP_Post ? axismundi_forum_record_remote_topic_commit( $topic ) : new WP_Error( 'ax_forum_remote_topic_missing', __( 'The published Topic could not be read.', 'axismundi-forum' ) );
	return is_wp_error( $result ) ? $result : $topic;
}
