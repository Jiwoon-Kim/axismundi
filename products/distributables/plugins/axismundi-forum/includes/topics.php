<?php
/**
 * Local Forum Topics and their ActivityStreams Page projection (Forum F1).
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_FORUM_TOPIC_POST_TYPE = 'ax_topic';
const AXISMUNDI_FORUM_REMOTE_GROUP_META = '_axismundi_forum_remote_group_identity_id';
const AXISMUNDI_FORUM_TOPIC_CONTENT_MODIFIED_META = '_axismundi_forum_topic_content_modified_gmt';

/** Return the last material Topic submission time, excluding Core review-status transitions. */
function axismundi_forum_topic_content_modified_time( WP_Post $topic ) : string {
	$modified = (string) get_post_meta( $topic->ID, AXISMUNDI_FORUM_TOPIC_CONTENT_MODIFIED_META, true );
	if ( '' !== $modified && false !== strtotime( $modified . ' UTC' ) ) {
		return get_date_from_gmt( $modified, DATE_W3C );
	}
	return get_post_modified_time( DATE_W3C, true, $topic );
}

/** Register the local Topic authoring container. */
function axismundi_forum_register_topic_post_type() : void {
	register_post_type(
		AXISMUNDI_FORUM_TOPIC_POST_TYPE,
		array(
			'labels' => array(
				'name'          => __( 'Topics', 'axismundi-forum' ),
				'singular_name' => __( 'Topic', 'axismundi-forum' ),
				'menu_name'     => __( 'Topics', 'axismundi-forum' ),
				'add_new_item'  => __( 'Add New Topic', 'axismundi-forum' ),
				'edit_item'     => __( 'Edit Topic', 'axismundi-forum' ),
			),
			'public'       => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-format-chat',
			'rewrite'      => array( 'slug' => 'topic', 'with_front' => false ),
			'supports'     => array( 'title', 'editor', 'excerpt', 'revisions' ),
		)
	);
}
add_action( 'init', 'axismundi_forum_register_topic_post_type', 5 );

/** Stable local Page id for one Topic. */
function axismundi_forum_topic_object_uri( WP_Post $topic ) : string {
	return add_query_arg( 'p', $topic->ID, home_url( '/' ) );
}

/** Resolve the cached remote Group selected as an outbound Topic's destination. */
function axismundi_forum_get_remote_topic_group( WP_Post $topic ) : ?Axismundi_Actor {
	if ( AXISMUNDI_FORUM_TOPIC_POST_TYPE !== $topic->post_type || null !== axismundi_forum_get_topic_entry( $topic->ID ) || ! function_exists( 'axismundi_actors_get_by_identity' ) ) {
		return null;
	}
	$identity_id = (int) get_post_meta( $topic->ID, AXISMUNDI_FORUM_REMOTE_GROUP_META, true );
	$group       = $identity_id > 0 ? axismundi_actors_get_by_identity( $identity_id ) : null;
	return $group instanceof Axismundi_Actor && ! $group->is_local() && 'Group' === $group->get_type() && 'tombstone' !== $group->get_status() ? $group : null;
}

/** One Forum Entry belonging to a local Topic post, if admitted. */
function axismundi_forum_get_topic_entry( int $topic_post_id ) : ?array {
	if ( $topic_post_id <= 0 ) {
		return null;
	}
	global $wpdb;
	$table = axismundi_forum_entries_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- UNIQUE local Topic lookup on a custom table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE source_post_id = %d", $topic_post_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/** One contextual Topic entry, including remote entries with no WordPress post. */
function axismundi_forum_get_entry( int $entry_id ) : ?array {
	if ( $entry_id <= 0 ) {
		return null;
	}
	global $wpdb;
	$table = axismundi_forum_entries_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- primary-key lookup on a Forum-owned entry projection.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entry_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/** Count entries in one Forum without conflating them with its activity feed. */
function axismundi_forum_count_entries( int $group_identity_id ) : int {
	global $wpdb;
	$table = axismundi_forum_entries_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed Forum-context count on a custom table.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE group_identity_id = %d", $group_identity_id ) );
}

/** Remove one community's contextual entries; source objects remain. */
function axismundi_forum_delete_entries_for_community( int $group_identity_id ) : void {
	global $wpdb;
	$wpdb->delete( axismundi_forum_entries_table(), array( 'group_identity_id' => $group_identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- contextual projection cleanup; never deletes source objects.
}

/** Remove a local Topic's Forum context when its source post is deleted. */
function axismundi_forum_delete_entries_for_topic( int $post_id ) : void {
	if ( AXISMUNDI_FORUM_TOPIC_POST_TYPE !== get_post_type( $post_id ) ) {
		return;
	}
	global $wpdb;
	$wpdb->delete( axismundi_forum_entries_table(), array( 'source_post_id' => $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- contextual projection cleanup; source deletion owns lifecycle separately.
}
add_action( 'before_delete_post', 'axismundi_forum_delete_entries_for_topic' );

/** Refresh the OP-owned listing state for one Topic that Forum has contextualized. */
function axismundi_forum_refresh_topic_listing_projection( WP_Post $topic ) : void {
	if ( AXISMUNDI_FORUM_TOPIC_POST_TYPE !== $topic->post_type || ! function_exists( 'axismundi_op_refresh_object_listing_projection' ) ) {
		return;
	}
	axismundi_op_refresh_object_listing_projection( axismundi_forum_topic_object_uri( $topic ) );
}

/** Save transitions cover published local and remote-Group Topics after their context exists. */
function axismundi_forum_refresh_saved_topic_listing_projection( int $post_id, WP_Post $post, bool $update ) : void {
	unset( $post_id, $update );
	if ( ! wp_is_post_revision( $post->ID ) ) {
		axismundi_forum_refresh_topic_listing_projection( $post );
	}
}
add_action( 'save_post_' . AXISMUNDI_FORUM_TOPIC_POST_TYPE, 'axismundi_forum_refresh_saved_topic_listing_projection', 50, 3 );

/** A Core status change alters a Topic's public projection independently of its entry row. */
function axismundi_forum_refresh_topic_transition_listing_projection( string $new_status, string $old_status, WP_Post $post ) : void {
	unset( $new_status, $old_status );
	axismundi_forum_refresh_topic_listing_projection( $post );
}
add_action( 'transition_post_status', 'axismundi_forum_refresh_topic_transition_listing_projection', 50, 3 );

/** Unlike a Note, permanent Topic deletion has no local Tombstone source to retain. */
function axismundi_forum_delete_topic_listing_projection( int $post_id, WP_Post $post ) : void {
	if ( AXISMUNDI_FORUM_TOPIC_POST_TYPE === $post->post_type && function_exists( 'axismundi_op_delete_object_listing_projection' ) ) {
		axismundi_op_delete_object_listing_projection( axismundi_forum_topic_object_uri( $post ) );
	}
}
add_action( 'before_delete_post', 'axismundi_forum_delete_topic_listing_projection', 20, 2 );

/** @return array<string,string> Stable local Topic-admission policy labels. */
function axismundi_forum_posting_policies() : array {
	return array(
		'open'     => __( 'Anyone who can edit a Topic', 'axismundi-forum' ),
		'members'  => __( 'Accepted community members', 'axismundi-forum' ),
		'managers' => __( 'Group managers only', 'axismundi-forum' ),
	);
}

/** @return array<string,string> Stable Group validation choices for submitted Topics. */
function axismundi_forum_topic_approval_policies() : array {
	return array(
		'open'     => __( 'Announce accepted Topics automatically', 'axismundi-forum' ),
		'approval' => __( 'Require moderator approval before Announce', 'axismundi-forum' ),
	);
}

/** Read one Forum's local Topic-admission policy, defaulting new Forums to F1 open posting. */
function axismundi_forum_get_posting_policy( int $group_identity_id ) : string {
	$community = axismundi_forum_get_community( $group_identity_id );
	$policy = is_array( $community ) ? (string) $community['posting_policy'] : '';
	return array_key_exists( $policy, axismundi_forum_posting_policies() ) ? $policy : 'open';
}

/** Whether a WP user manages this community's Group. */
function axismundi_forum_user_can_manage( int $group_identity_id, int $user_id ) : bool {
	return axismundi_forum_is_community( $group_identity_id )
		&& axismundi_forum_actors_available()
		&& axismundi_actors_managed_actor_can_manage( $group_identity_id, $user_id );
}

/** Persist a Forum's policy; only a manager of its bound Group may change it. */
function axismundi_forum_set_posting_policy( int $group_identity_id, int $user_id, string $policy ) {
	if ( ! array_key_exists( $policy, axismundi_forum_posting_policies() ) ) {
		return new WP_Error( 'ax_forum_posting_policy', __( 'The Forum posting policy is invalid.', 'axismundi-forum' ) );
	}
	if ( ! axismundi_forum_user_can_manage( $group_identity_id, $user_id ) ) {
		return new WP_Error( 'ax_forum_forbidden', __( 'You do not manage this Forum Group.', 'axismundi-forum' ) );
	}
	if ( $policy === axismundi_forum_get_posting_policy( $group_identity_id ) ) {
		return true;
	}
	return axismundi_forum_update_community_policy( $group_identity_id, $user_id, 'posting_policy', $policy );
}

/** Read one community's Topic validation policy. */
function axismundi_forum_get_topic_approval_policy( int $group_identity_id ) : string {
	$community = axismundi_forum_get_community( $group_identity_id );
	$policy = is_array( $community ) ? (string) $community['topic_approval_policy'] : '';
	return array_key_exists( $policy, axismundi_forum_topic_approval_policies() ) ? $policy : 'open';
}

/** Change whether valid Topic submissions await a Group moderator. */
function axismundi_forum_set_topic_approval_policy( int $group_identity_id, int $user_id, string $policy ) {
	if ( ! array_key_exists( $policy, axismundi_forum_topic_approval_policies() ) ) {
		return new WP_Error( 'ax_forum_topic_approval_policy', __( 'The Topic approval policy is invalid.', 'axismundi-forum' ) );
	}
	if ( $policy === axismundi_forum_get_topic_approval_policy( $group_identity_id ) ) {
		return true;
	}
	return axismundi_forum_update_community_policy( $group_identity_id, $user_id, 'topic_approval_policy', $policy );
}

/** Whether this local Person may submit any authored content to one community. */
function axismundi_forum_user_can_post_to_community( int $group_identity_id, int $user_id ) : bool {
	if ( $user_id <= 0 || ! axismundi_forum_is_community( $group_identity_id ) ) {
		return false;
	}
	$author = function_exists( 'axismundi_actors_get_for_user' ) ? axismundi_actors_get_for_user( $user_id ) : null;
	if ( ! $author instanceof Axismundi_Actor || ! $author->is_local() || 'Person' !== $author->get_type() || ! function_exists( 'axismundi_actors_is_public_profile' ) || ! axismundi_actors_is_public_profile( $author ) ) {
		return false;
	}
	$policy = axismundi_forum_get_posting_policy( $group_identity_id );
	if ( 'open' === $policy ) {
		return true;
	}
	if ( 'managers' === $policy ) {
		return axismundi_forum_user_can_manage( $group_identity_id, $user_id );
	}
	if ( 'members' !== $policy || ! function_exists( 'axismundi_actors_get_for_user' ) || ! function_exists( 'axismundi_forum_get_membership' ) ) {
		return false;
	}
	$membership = axismundi_forum_get_membership( $group_identity_id, $author->get_identity_id() );
	return is_array( $membership ) && 'accepted' === (string) $membership['membership_state'];
}

/** Whether this user may admit this local Topic under the Forum's current F1 policy. */
function axismundi_forum_can_admit_local_topic( int $group_identity_id, int $topic_post_id, int $user_id ) : bool {
	return $user_id > 0 && user_can( $user_id, 'edit_post', $topic_post_id ) && axismundi_forum_user_can_post_to_community( $group_identity_id, $user_id );
}

/** Update one entry field after verifying the caller manages its bound Group. */
function axismundi_forum_update_topic_entry( int $topic_post_id, int $user_id, array $fields ) {
	$entry = axismundi_forum_get_topic_entry( $topic_post_id );
	if ( ! is_array( $entry ) ) {
		return new WP_Error( 'ax_forum_topic_context', __( 'This Topic has no Forum context.', 'axismundi-forum' ) );
	}
	if ( ! axismundi_forum_user_can_manage( (int) $entry['group_identity_id'], $user_id ) ) {
		return new WP_Error( 'ax_forum_forbidden', __( 'You do not manage this Forum Group.', 'axismundi-forum' ) );
	}
	$fields['updated_at'] = current_time( 'mysql', true );
	global $wpdb;
	$updated = $wpdb->update(
		axismundi_forum_entries_table(),
		$fields,
		array( 'id' => (int) $entry['id'] ),
		array_fill( 0, count( $fields ), '%s' ),
		array( '%d' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- manager-authorized update on Forum-owned contextual state.
	return false === $updated
		? new WP_Error( 'ax_forum_topic_state_write', __( 'The Topic state could not be saved.', 'axismundi-forum' ) )
		: true;
}

/**
 * Change the local visibility decision for a contextual Topic entry.
 *
 * `pending` is deliberately an internal moderation queue state. It neither deletes the source
 * object nor makes a federation claim. With Group distribution enabled, withdrawing a prior
 * Announce is a separate `Undo(Announce(Create))` operation.
 */
function axismundi_forum_set_entry_moderation_state( int $entry_id, int $user_id, string $state ) {
	if ( ! in_array( $state, array( 'visible', 'pending' ), true ) ) {
		return new WP_Error( 'ax_forum_moderation_state', __( 'The moderation state is invalid.', 'axismundi-forum' ) );
	}
	$entry = axismundi_forum_get_entry( $entry_id );
	if ( ! is_array( $entry ) ) {
		return new WP_Error( 'ax_forum_entry_missing', __( 'The Forum entry does not exist.', 'axismundi-forum' ) );
	}
	$site_moderator = $user_id > 0 && user_can( $user_id, 'edit_others_posts' );
	if ( ! $site_moderator && ! function_exists( 'axismundi_forum_user_can_moderate' )
		|| ( ! $site_moderator && ! axismundi_forum_user_can_moderate( (int) $entry['group_identity_id'], $user_id ) ) ) {
		return new WP_Error( 'ax_forum_forbidden', __( 'You may not moderate this community entry.', 'axismundi-forum' ) );
	}
	if ( $state === (string) $entry['moderation_state'] ) {
		return true;
	}
	global $wpdb;
	$updated = $wpdb->update(
		axismundi_forum_entries_table(),
		array( 'moderation_state' => $state, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'id' => $entry_id ),
		array( '%s', '%s' ),
		array( '%d' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- entry-scoped moderation state, including remote entries.
	return false === $updated ? new WP_Error( 'ax_forum_moderation_write', __( 'The moderation state could not be saved.', 'axismundi-forum' ) ) : true;
}

/** @return array<int,array<string,mixed>> Topic submissions awaiting Group Announce approval. */
function axismundi_forum_pending_topic_entries( int $group_identity_id, int $limit = 100 ) : array {
	if ( $group_identity_id <= 0 ) {
		return array();
	}
	global $wpdb;
	$table = axismundi_forum_entries_table();
	$limit = max( 1, min( 100, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed Group-scoped pending Topic queue.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE group_identity_id = %d AND entry_type = 'topic' AND admission_state = 'pending' ORDER BY created_at ASC, id ASC LIMIT %d", $group_identity_id, $limit ), ARRAY_A );
}

/** Lock or reopen a Topic for replies; source object content remains unchanged. */
function axismundi_forum_set_topic_locked( int $topic_post_id, int $user_id, bool $locked ) {
	$entry = axismundi_forum_get_topic_entry( $topic_post_id );
	if ( ! is_array( $entry ) ) {
		return new WP_Error( 'ax_forum_topic_context', __( 'This Topic has no Forum context.', 'axismundi-forum' ) );
	}
	if ( $locked === ! empty( $entry['locked_at'] ) ) {
		return axismundi_forum_user_can_manage( (int) $entry['group_identity_id'], $user_id )
			? true
			: new WP_Error( 'ax_forum_forbidden', __( 'You do not manage this Forum Group.', 'axismundi-forum' ) );
	}
	return axismundi_forum_update_topic_entry(
		$topic_post_id,
		$user_id,
		array( 'locked_at' => $locked ? current_time( 'mysql', true ) : null )
	);
}

/** Pin or unpin a Topic inside its Forum; newer pins sort first. */
function axismundi_forum_set_topic_sticky( int $topic_post_id, int $user_id, bool $sticky ) {
	$entry = axismundi_forum_get_topic_entry( $topic_post_id );
	if ( ! is_array( $entry ) ) {
		return new WP_Error( 'ax_forum_topic_context', __( 'This Topic has no Forum context.', 'axismundi-forum' ) );
	}
	if ( $sticky === ! empty( $entry['sticky_position'] ) ) {
		return axismundi_forum_user_can_manage( (int) $entry['group_identity_id'], $user_id )
			? true
			: new WP_Error( 'ax_forum_forbidden', __( 'You do not manage this Forum Group.', 'axismundi-forum' ) );
	}
	$position = null;
	if ( $sticky ) {
		global $wpdb;
		$table    = axismundi_forum_entries_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed per-Forum sticky sequence on a custom table.
		$position = 1 + (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE( MAX( sticky_position ), 0 ) FROM {$table} WHERE group_identity_id = %d", (int) $entry['group_identity_id'] ) );
	}
	return axismundi_forum_update_topic_entry( $topic_post_id, $user_id, array( 'sticky_position' => $position ) );
}

/**
 * Admit a local Topic into one bound Forum under the explicit F1 local policy.
 */
function axismundi_forum_admit_local_topic( int $group_identity_id, int $topic_post_id, int $user_id ) {
	if ( AXISMUNDI_FORUM_TOPIC_POST_TYPE !== get_post_type( $topic_post_id ) || ! axismundi_forum_is_community( $group_identity_id ) ) {
		return new WP_Error( 'ax_forum_topic_target', __( 'An enabled community and Topic are required.', 'axismundi-forum' ) );
	}
	if ( null !== axismundi_forum_get_topic_entry( $topic_post_id ) ) {
		return new WP_Error( 'ax_forum_topic_context', __( 'This Topic already belongs to a Forum.', 'axismundi-forum' ) );
	}
	$topic = get_post( $topic_post_id );
	if ( ! $topic instanceof WP_Post ) {
		return new WP_Error( 'ax_forum_topic_missing', __( 'The Topic does not exist.', 'axismundi-forum' ) );
	}
	if ( ! axismundi_forum_can_admit_local_topic( $group_identity_id, $topic_post_id, $user_id ) ) {
		return new WP_Error( 'ax_forum_topic_forbidden', __( 'You may not add this Topic to the selected Forum.', 'axismundi-forum' ) );
	}
	/*
	 * A Group submission is a Core review item until that Group has recorded an effective
	 * Announce. This also lets WordPress contributors use their native Submit for Review path.
	 */
	if ( 'pending' !== $topic->post_status ) {
		$pending = wp_update_post( array( 'ID' => $topic->ID, 'post_status' => 'pending' ), true );
		if ( is_wp_error( $pending ) ) {
			return new WP_Error( 'ax_forum_topic_status_write', __( 'The Topic could not enter community review.', 'axismundi-forum' ) );
		}
		$topic = get_post( $topic_post_id );
		if ( ! $topic instanceof WP_Post || 'pending' !== $topic->post_status ) {
			return new WP_Error( 'ax_forum_topic_status_write', __( 'The Topic could not enter community review.', 'axismundi-forum' ) );
		}
	}
	$actor = function_exists( 'axismundi_actors_get_for_user' ) ? axismundi_actors_get_for_user( (int) $topic->post_author ) : null;
	$now   = current_time( 'mysql', true );
	global $wpdb;
	$fields = array(
			'group_identity_id'             => $group_identity_id,
			'object_uri'                    => axismundi_forum_topic_object_uri( $topic ),
			'object_uri_hash'               => hash( 'sha256', axismundi_forum_topic_object_uri( $topic ) ),
			'entry_type'                    => 'topic',
			'source_post_id'                => $topic_post_id,
			// Every accepted submission passes through Group publication. Even an open policy is
			// pending until its Announce is durably recorded.
			'admission_state'               => 'pending',
			'moderation_state'              => 'visible',
			'created_at'                    => $now,
			'updated_at'                    => $now,
	);
	$formats = array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' );
	if ( $actor instanceof Axismundi_Actor ) {
		$fields['submission_actor_identity_id'] = $actor->get_identity_id();
		$formats[]                              = '%d';
	}
	$ok = $wpdb->insert(
		axismundi_forum_entries_table(),
		$fields,
		$formats
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Forum-owned contextual projection.
	if ( false === $ok ) {
		return new WP_Error( 'ax_forum_topic_write', __( 'The Topic could not be added to this Forum.', 'axismundi-forum' ) );
	}
	$create = function_exists( 'axismundi_forum_record_topic_commit' ) ? axismundi_forum_record_topic_commit( $topic ) : new WP_Error( 'ax_forum_topic_create', __( 'The Topic Create recorder is unavailable.', 'axismundi-forum' ) );
	if ( is_wp_error( $create ) ) {
		$wpdb->delete( axismundi_forum_entries_table(), array( 'source_post_id' => $topic_post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- do not leave a pending entry that lacks the immutable submitted Create.
		return $create;
	}
	$updated = $wpdb->update(
		axismundi_forum_entries_table(),
		array( 'accepted_activity_uri' => $create->get_uri(), 'updated_at' => current_time( 'mysql', true ) ),
		array( 'source_post_id' => $topic_post_id ),
		array( '%s', '%s' ),
		array( '%d' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- entry points to the immutable submitted Create that Group approval will wrap.
	if ( false === $updated ) {
		$wpdb->delete( axismundi_forum_entries_table(), array( 'source_post_id' => $topic_post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the entry is unusable unless it retains its submitted Create URI.
		return new WP_Error( 'ax_forum_topic_create_link', __( 'The Topic Create could not be linked to its community entry.', 'axismundi-forum' ) );
	}
	$entry = axismundi_forum_get_topic_entry( $topic_post_id );
	axismundi_forum_refresh_topic_listing_projection( $topic );
	if ( 'open' !== axismundi_forum_get_topic_approval_policy( $group_identity_id ) || ! is_array( $entry ) ) {
		return true;
	}
	return function_exists( 'axismundi_forum_publish_validated_pending_entry' )
		? axismundi_forum_publish_validated_pending_entry( $entry )
		: new WP_Error( 'ax_forum_topic_publish', __( 'The Group publication recorder is unavailable.', 'axismundi-forum' ) );
}

/** Re-materialize one community's local Topic rows after a policy changes their visibility. */
function axismundi_forum_refresh_community_topic_listing_projections( int $group_identity_id ) : void {
	if ( $group_identity_id <= 0 || ! function_exists( 'axismundi_op_refresh_object_listing_projection' ) ) {
		return;
	}
	global $wpdb;
	$table = axismundi_forum_entries_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed Forum context lookup feeding OP's one writer.
	$post_ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT source_post_id FROM {$table} WHERE group_identity_id = %d AND entry_type = 'topic' AND source_post_id > 0", $group_identity_id ) );
	foreach ( $post_ids as $post_id ) {
		$topic = get_post( (int) $post_id );
		if ( $topic instanceof WP_Post ) {
			axismundi_forum_refresh_topic_listing_projection( $topic );
		}
	}
}

/** Get public visible Topic post ids for the Forum Topic list. */
function axismundi_forum_topic_ids( int $group_identity_id, int $limit = 20 ) : array {
	global $wpdb;
	$table = axismundi_forum_entries_table();
	$limit = max( 1, min( 100, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed WordPress posts + Forum-entry table names; values prepared.
	return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT e.source_post_id FROM {$table} e INNER JOIN {$wpdb->posts} p ON p.ID = e.source_post_id WHERE e.group_identity_id = %d AND e.entry_type = 'topic' AND e.admission_state = 'visible' AND e.moderation_state = 'visible' AND p.post_status = 'publish' ORDER BY e.sticky_position IS NULL, e.sticky_position DESC, p.post_date_gmt DESC LIMIT %d", $group_identity_id, $limit ) ) );
}

/** Count local and remote visible Topic entries for one community. */
function axismundi_forum_visible_topic_entry_count( int $group_identity_id ) : int {
	if ( $group_identity_id <= 0 ) {
		return 0;
	}
	global $wpdb;
	$table = axismundi_forum_entries_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed community-context query; value prepared.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE group_identity_id = %d AND entry_type = 'topic' AND admission_state = 'visible' AND moderation_state = 'visible'", $group_identity_id ) );
}

/** @return array<int,array<string,mixed>> Local and remote visible Topic entries for one community page. */
function axismundi_forum_visible_topic_entries( int $group_identity_id, int $limit = 20, int $page = 1 ) : array {
	if ( $group_identity_id <= 0 ) {
		return array();
	}
	global $wpdb;
	$table = axismundi_forum_entries_table();
	$limit = max( 1, min( 100, $limit ) );
	$page  = max( 1, $page );
	$offset = ( $page - 1 ) * $limit;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed Forum-context query; remote entries intentionally have no WordPress post row.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE group_identity_id = %d AND entry_type = 'topic' AND admission_state = 'visible' AND moderation_state = 'visible' ORDER BY sticky_position IS NULL, sticky_position DESC, created_at DESC, id DESC LIMIT %d OFFSET %d", $group_identity_id, $limit, $offset ), ARRAY_A );
}

/** Whether the current local account may read this community's profile Topic feed. */
function axismundi_forum_can_view_community_topics( int $group_identity_id, ?int $user_id = null ) : bool {
	if ( 'members' !== axismundi_forum_get_distribution_scope( $group_identity_id ) ) {
		return true;
	}
	$user_id = null === $user_id ? get_current_user_id() : $user_id;
	if ( $user_id <= 0 || ! user_can( $user_id, 'read' ) ) {
		return false;
	}
	// A site operator remains responsible for local content even when it is member-distributed.
	if ( user_can( $user_id, 'manage_options' ) || ( function_exists( 'axismundi_forum_user_can_moderate' ) && axismundi_forum_user_can_moderate( $group_identity_id, $user_id ) ) ) {
		return true;
	}
	$actor = function_exists( 'axismundi_actors_get_for_user' ) ? axismundi_actors_get_for_user( $user_id ) : null;
	$membership = $actor instanceof Axismundi_Actor && function_exists( 'axismundi_forum_get_membership' )
		? axismundi_forum_get_membership( $group_identity_id, $actor->get_identity_id() )
		: null;
	return is_array( $membership ) && 'accepted' === (string) ( $membership['membership_state'] ?? '' );
}

/**
 * Whether one reader may read one Topic.
 *
 * The community rule plus the one case it cannot express: an author may always read what they
 * wrote. Membership can be revoked and a community can change its scope after the fact, and
 * neither should take someone's own post away from them.
 *
 * @param WP_Post  $topic   Topic post.
 * @param int|null $user_id Reader, or null for the current one.
 * @return bool
 */
function axismundi_forum_can_read_topic( WP_Post $topic, ?int $user_id = null ) : bool {
	$entry = axismundi_forum_get_topic_entry( $topic->ID );
	if ( ! is_array( $entry ) ) {
		return true;
	}
	$user_id = null === $user_id ? get_current_user_id() : $user_id;
	if ( $user_id > 0 && (int) $topic->post_author === $user_id ) {
		return true;
	}
	return axismundi_forum_can_view_community_topics( (int) $entry['group_identity_id'], $user_id );
}

/**
 * Refuse a member-distributed Topic to a reader who is not entitled to it.
 *
 * A 404 rather than a message. A notice saying "this post is for members" still discloses that
 * the post exists, who wrote it, and — through the title in `<title>` and the URL slug — what it
 * is about. For a community whose whole point is that its threads are not public, that disclosure
 * is the leak.
 *
 * The ActivityStreams route already withholds these through
 * `axismundi_forum_topic_article_visible()`; without this the HTML permalink answered 200 for the
 * same Topic, which made the protection true only of the representation nobody reads by hand.
 */
function axismundi_forum_guard_topic_request() : void {
	if ( ! is_singular( AXISMUNDI_FORUM_TOPIC_POST_TYPE ) ) {
		return;
	}
	$topic = get_queried_object();
	if ( ! $topic instanceof WP_Post || axismundi_forum_can_read_topic( $topic ) ) {
		return;
	}
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}
add_action( 'template_redirect', 'axismundi_forum_guard_topic_request', 1 );

/**
 * Keep member-distributed Topics out of public listings for readers without access.
 *
 * The permalink guard alone would still leave the title in search results and archives, which
 * discloses the same thing the guard exists to withhold.
 *
 * @param WP_Query $query Query being prepared.
 * @return void
 */
function axismundi_forum_exclude_restricted_topics( WP_Query $query ) : void {
	if ( is_admin() || $query->is_singular() ) {
		return;
	}
	$types = (array) $query->get( 'post_type' );
	if ( ! in_array( AXISMUNDI_FORUM_TOPIC_POST_TYPE, $types, true ) && ! $query->is_search() && ! $query->is_home() ) {
		return;
	}
	$restricted = axismundi_forum_restricted_topic_ids( get_current_user_id() );
	if ( empty( $restricted ) ) {
		return;
	}
	$query->set( 'post__not_in', array_values( array_unique( array_merge( (array) $query->get( 'post__not_in' ), $restricted ) ) ) );
}
add_action( 'pre_get_posts', 'axismundi_forum_exclude_restricted_topics' );

/**
 * Topic ids one reader may not see, from the communities that restrict distribution.
 *
 * Only member-scoped communities are consulted, so a site whose communities are all public pays
 * one cheap lookup and gets an empty list.
 *
 * @param int $user_id Reader.
 * @return int[]
 */
function axismundi_forum_restricted_topic_ids( int $user_id ) : array {
	global $wpdb;
	$settings = axismundi_forum_settings_table();
	$entries  = axismundi_forum_entries_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own tables, no user input.
	$groups = (array) $wpdb->get_col( "SELECT group_identity_id FROM {$settings} WHERE distribution_scope = 'members'" );
	$hidden = array();
	foreach ( $groups as $group_identity_id ) {
		$group_identity_id = (int) $group_identity_id;
		if ( axismundi_forum_can_view_community_topics( $group_identity_id, $user_id ) ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- own table, prepared id.
		$ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT source_post_id FROM {$entries} WHERE group_identity_id = %d AND source_post_id > 0", $group_identity_id ) );
		foreach ( $ids as $id ) {
			$id = (int) $id;
			// An author keeps their own post in listings even where the community is closed.
			if ( $id > 0 && ( $user_id <= 0 || (int) get_post_field( 'post_author', $id ) !== $user_id ) ) {
				$hidden[] = $id;
			}
		}
	}
	return $hidden;
}

/**
 * The Group identity the page being rendered is about.
 *
 * @return int Group identity, or 0 when this page is about no community.
 */
function axismundi_forum_context_group_id() : int {
	$actor = function_exists( 'axismundi_actors_current_actor' ) ? axismundi_actors_current_actor() : null;
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || ! $actor->is_managed() || 'Group' !== $actor->get_type() ) {
		return 0;
	}
	return axismundi_forum_is_community( $actor->get_identity_id() ) ? $actor->get_identity_id() : 0;
}

/** Render the Forum-owned Topic index; this intentionally is not a Core Query Loop. */
function axismundi_forum_render_topic_list_block( array $attributes = array(), string $content = '', bool $standalone = true ) : string {
	unset( $content );
	$group_identity_id = axismundi_forum_context_group_id();
	if ( $group_identity_id <= 0 ) {
		return '';
	}
	if ( ! axismundi_forum_can_view_community_topics( $group_identity_id ) ) {
		// Claim the Group feed without falling back to its Activity timeline.
		return '<section class="axismundi-forum-topic-list" hidden aria-hidden="true"></section>';
	}
	$limit = isset( $attributes['perPage'] ) ? (int) $attributes['perPage'] : 20;
	$limit = max( 1, min( 100, $limit ) );
	$page  = axismundi_forum_group_archive_page_number();
	$total = axismundi_forum_visible_topic_entry_count( $group_identity_id );
	$pages = max( 1, (int) ceil( $total / $limit ) );
	if ( $page > $pages ) {
		$page = $pages;
	}
	/*
	 * One card for both, because a Topic is one thing.
	 *
	 * A Topic is projected as an `Article`, and a remote community's Topics were already shown
	 * that way — while our own were a title on a line. So the archive said, in its own markup,
	 * that a Topic from elsewhere is a post and a Topic from here is a link, and a reader could
	 * see the asymmetry without knowing why it was there. The same renderer answers for both now,
	 * which also means the Person community surface can reuse this by adding an author filter
	 * rather than by growing a third way to draw the same object.
	 */
	$items = array();
	foreach ( axismundi_forum_visible_topic_entries( $group_identity_id, $limit, $page ) as $entry ) {
		$source_post_id = (int) ( $entry['source_post_id'] ?? 0 );
		$local          = $source_post_id > 0 ? get_post( $source_post_id ) : null;
		if ( $source_post_id > 0 && ( ! $local instanceof WP_Post || 'publish' !== $local->post_status ) ) {
			continue;
		}
		$author = function_exists( 'axismundi_actors_get_by_identity' ) ? axismundi_actors_get_by_identity( (int) ( $entry['submission_actor_identity_id'] ?? 0 ) ) : null;
		$uri    = $local instanceof WP_Post ? axismundi_forum_topic_object_uri( $local ) : (string) ( $entry['object_uri'] ?? '' );
		$card   = function_exists( 'axismundi_op_render_object_by_uri' )
			? axismundi_op_render_object_by_uri(
				$uri,
				array(
					'headingTag'      => 'h3',
					/*
					 * The community's own saved card, so a Topic here is drawn the way the Group
					 * profile template says and not by a second, poorer card kept in this file.
					 * Forum chooses which Topics appear; what a card contains is the template's.
					 */
					'cardTemplate'     => axismundi_forum_archive_card_template( $group_identity_id ),
					'interactions'     => '' !== axismundi_forum_archive_card_template( $group_identity_id ),
					'interactionOwner' => 'block',
					'expected_author' => $author instanceof Axismundi_Actor ? $author->get_uri() : '',
					/*
					 * The renderer's public gate is the federation gate, and it withholds a
					 * members-only Topic from everyone — correctly, because that gate answers
					 * "may this be republished", not "may this reader see it". The entitlement
					 * question was already answered above for this whole list, so the answer is
					 * carried here rather than asked again with less context. Naming the
					 * community makes the claim checkable: the filter refuses a Topic that is
					 * not in it.
					 */
					'communityViewer' => $group_identity_id,
				)
			)
			: '';
		if ( '' !== $card ) {
			$items[] = '<li class="axismundi-forum-topic-list__item' . ( $local instanceof WP_Post ? '' : ' axismundi-forum-topic-list__item--remote' ) . '">' . $card . '</li>';
			continue;
		}
		/*
		 * A members-only Topic has no card to give, even to the member reading it.
		 *
		 * The card gate takes an opt-in from this caller, but the view model underneath it does
		 * not: it asks whether the source is publicly visible and has no way to be told that this
		 * reader was already authorized. So for a members-only community the card comes back
		 * empty for everyone, including the people the community exists for.
		 *
		 * Falling back to the title keeps that reader seeing exactly what they saw before rather
		 * than an empty page — this list is already behind the community's own entitlement check.
		 * It is a smaller row than a public community gets, and that difference is the honest
		 * shape of the limitation rather than something hidden by rendering nothing.
		 */
		if ( $local instanceof WP_Post && axismundi_forum_can_read_topic( $local ) ) {
			$items[] = '<li class="axismundi-forum-topic-list__item axismundi-forum-topic-list__item--title-only"><a href="' . esc_url( get_permalink( $local ) ) . '">' . esc_html( get_the_title( $local ) ) . '</a></li>';
		}
	}
	/*
	 * `ol`, the same element the Activity timeline uses.
	 *
	 * These are the same kind of list read two ways, so they must not disagree about what kind of
	 * list they are. The order carries meaning in both — newest first, and here the position is
	 * also what the page numbers count — which is what an ordered list means and what an unordered
	 * one denies. `start` continues the numbering across pages so a screen reader is told the
	 * entry's place in the archive rather than being told every page begins at one.
	 */
	$body = empty( $items )
		? '<p class="axismundi-forum-topic-list__empty">' . esc_html__( 'No topics yet.', 'axismundi-forum' ) . '</p>'
		: '<ol class="axismundi-forum-topic-list__items" start="' . esc_attr( (string) ( ( ( $page - 1 ) * $limit ) + 1 ) ) . '">' . implode( '', $items ) . '</ol>';
	// Both community collections page the same way and say so with the same words, so the
	// pagination is the archive's rather than one this list keeps to itself.
	$pagination = axismundi_forum_render_archive_pagination( $page, $pages, 'posts' );
	if ( ! $standalone ) {
		return $body . $pagination;
	}
	/*
	 * This renderer answers to two callers: the block, and the Group profile feed, which has
	 * no block being rendered around it. get_block_wrapper_attributes() reads the block
	 * currently on the stack and warns when there is none, so the plain class attribute is
	 * used in that case rather than asking core for supports that no block declared.
	 */
	$wrapper = null === WP_Block_Supports::$block_to_render
		? 'class="axismundi-forum-topic-list"'
		: get_block_wrapper_attributes( array( 'class' => 'axismundi-forum-topic-list' ) );
	return '<section ' . $wrapper . '><h2>' . esc_html__( 'Topics', 'axismundi-forum' ) . '</h2>' . $body . $pagination . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapper is a fixed literal or core-escaped.
}

/**
 * A bound Group's profile shows its community's Topics instead of an Activity timeline.
 *
 * This is the whole point of binding a Group to a Forum. A Group does not post; people post
 * to it, and a chronology of the Group's own Create and Announce rows is a record of
 * plumbing, not a community. Claiming the profile feed here means the federated address and
 * the community page are one surface, as they are everywhere else in the fediverse, instead
 * of a profile that sends readers to a second URL to find the actual conversation.
 *
 * Only the feed slot is claimed. The avatar, header, name, summary, and Follow control above
 * it stay Actors' and stay the Actor's own data: nothing here copies them into the Forum.
 *
 * @param string          $html  Whatever an earlier product returned.
 * @param Axismundi_Actor $actor Actor whose profile is being rendered.
 * @return string
 */
function axismundi_forum_actor_feed_html( string $html, Axismundi_Actor $actor ) : string {
	unset( $actor );
	/*
	 * Forum no longer answers here.
	 *
	 * It used to return the whole community archive as HTML, which meant a Group profile never ran
	 * the feed at all: the loop, the saved card template and the pagination block were all bypassed,
	 * and Forum ended up making decisions that were never its own — whether cards carried
	 * interactions, and whether the list was an `ol` or a `ul`. Both had to be corrected by hand,
	 * which is the sign that the seam was in the wrong place.
	 *
	 * A community is now a *surface*: Forum says which entries it contains and that it is paged by
	 * number, and the feed renders them exactly as it renders a Person's. The divergence is not
	 * fixed so much as no longer expressible.
	 */
	return $html;
}

/**
 * A community Group's profile is its archive, and that is the only surface it has.
 *
 * The Activity surface is removed rather than joined, because a chronology of the Group Actor's own
 * Activities is not a second way to read this profile — it is a thinner, mostly empty thing, and
 * offering it as a tab invites a reader away from the only content there is. With one surface the
 * feed draws no surface navigation at all, which is why the Person's Activity/Community switch does
 * not appear here.
 *
 * Posts and Comments stay inside this surface as its collections. They are not profile surfaces:
 * they are two lists of the same community, addressed the same way and paged the same way, which is
 * what a filter on one surface means.
 *
 * @param array<string,array<string,mixed>> $surfaces Registered surfaces.
 * @return array<string,array<string,mixed>>
 */
function axismundi_forum_actor_profile_surfaces( array $surfaces, Axismundi_Actor $actor ) : array {
	if ( ! $actor->is_local() || ! $actor->is_managed() || 'Group' !== $actor->get_type() ) {
		return $surfaces;
	}
	if ( ! axismundi_forum_is_community( $actor->get_identity_id() ) ) {
		return $surfaces;
	}
	return array(
		'community' => array(
			'label'          => __( 'Community', 'axismundi-forum' ),
			'heading'        => __( 'Community', 'axismundi-forum' ),
			'filters'        => axismundi_forum_group_archive_filters(),
			'default_filter' => 'posts',
			// Counted and jumpable, unlike a ledger nobody asks the length of.
			'mode'           => 'pagination',
			'page'           => 'axismundi_forum_community_surface_page',
		),
	);
}
add_filter( 'axismundi_act_actor_profile_surfaces', 'axismundi_forum_actor_profile_surfaces', 10, 2 );

/**
 * One numbered page of a community, as feed item descriptors.
 *
 * The entries are the Group's own effective Announces, which is what admission to a community *is*
 * — so "what this community contains" and "what it has accepted" cannot drift apart into two
 * answers. Each descriptor is shaped exactly like an Activity feed item, because from the feed's
 * side that is what it is.
 *
 * @param Axismundi_Actor $actor    Community Group.
 * @param int             $limit    Entries per page.
 * @param string          $position Requested page number, as the address carries it.
 * @param string          $filter   Active collection.
 * @return array{items:array<int,array<string,mixed>>,page:int,pages:int,total:int,has_more:bool,next_cursor:string,filter:string}
 */
function axismundi_forum_community_surface_page( Axismundi_Actor $actor, int $limit, string $position, string $filter = 'posts', bool $inclusive = false, bool $head_window = false ) : array {
	unset( $inclusive, $head_window );
	$filter = 'comments' === $filter ? 'comments' : 'posts';
	$group  = $actor->get_identity_id();
	$empty  = array( 'items' => array(), 'page' => 1, 'pages' => 1, 'total' => 0, 'has_more' => false, 'next_cursor' => '', 'filter' => $filter );
	if ( ! axismundi_forum_can_view_community_topics( $group ) ) {
		return $empty;
	}
	/*
	 * Public-scope communities only, for now, and deliberately empty rather than half-open.
	 *
	 * A members-scope community needs a viewer-aware card renderer, and there isn't one: the
	 * Object view model closes a members-only Topic through the transformer's federation
	 * visibility, which is the right answer to "may this be republished" and the wrong gate to be
	 * answering "may this member read it". Carrying an entitlement that far is a real piece of
	 * work, not a flag.
	 *
	 * Until then this surface says nothing rather than guessing. Note what is being given up: the
	 * old archive fell back to a bare title link whenever a card came back empty, so an accepted
	 * member of a members-scope community was seeing a list of titles — not by design but because
	 * the fallback hid that the cards had never rendered. Losing that is a real reduction and is
	 * chosen here rather than allowed to happen quietly. Anonymous readers saw nothing before and
	 * still see nothing.
	 *
	 * Lemmy has no private communities either, so this is not a gap against the thing we are
	 * following; it is a feature neither product has built yet.
	 */
	if ( 'public' !== axismundi_forum_get_distribution_scope( $group ) ) {
		return $empty;
	}
	$requested = max( 1, (int) $position );
	$uris      = 'comments' === $filter
		? axismundi_forum_group_comment_uris( $actor )['uris']
		: array();
	if ( 'comments' === $filter ) {
		$total = count( $uris );
		$pages = max( 1, (int) ceil( $total / $limit ) );
		$page  = min( $requested, $pages );
		$slice = array_slice( $uris, ( $page - 1 ) * $limit, $limit );
		$items = array();
		foreach ( $slice as $uri ) {
			$items[] = array( 'kind' => 'activity', 'type' => 'Announce', 'actor_uri' => $actor->get_uri(), 'object_uri' => (string) $uri, 'community_viewer' => $group );
		}
		return array( 'items' => $items, 'page' => $page, 'pages' => $pages, 'total' => $total, 'has_more' => $page < $pages, 'next_cursor' => '', 'filter' => $filter );
	}
	$total = axismundi_forum_visible_topic_entry_count( $group );
	$pages = max( 1, (int) ceil( $total / $limit ) );
	$page  = min( $requested, $pages );
	$items = array();
	foreach ( axismundi_forum_visible_topic_entries( $group, $limit, $page ) as $entry ) {
		$source_post_id = (int) ( $entry['source_post_id'] ?? 0 );
		$local          = $source_post_id > 0 ? get_post( $source_post_id ) : null;
		if ( $source_post_id > 0 && ( ! $local instanceof WP_Post || 'publish' !== $local->post_status ) ) {
			continue;
		}
		$uri = $local instanceof WP_Post ? axismundi_forum_topic_object_uri( $local ) : (string) ( $entry['object_uri'] ?? '' );
		if ( '' === $uri ) {
			continue;
		}
		$items[] = array( 'kind' => 'activity', 'type' => 'Announce', 'actor_uri' => $actor->get_uri(), 'object_uri' => $uri, 'community_viewer' => $group );
	}
	return array( 'items' => $items, 'page' => $page, 'pages' => $pages, 'total' => $total, 'has_more' => $page < $pages, 'next_cursor' => '', 'filter' => $filter );
}

/** A member-distributed community feed varies by the logged-in viewer. */
function axismundi_forum_group_profile_requires_nocache( bool $requires_nocache, Axismundi_Actor $actor ) : bool {
	if ( $requires_nocache || ! $actor->is_local() || ! $actor->is_managed() || 'Group' !== $actor->get_type() ) {
		return $requires_nocache;
	}
	return axismundi_forum_is_community( $actor->get_identity_id() )
		&& 'members' === axismundi_forum_get_distribution_scope( $actor->get_identity_id() );
}
add_filter( 'axismundi_actors_profile_requires_nocache', 'axismundi_forum_group_profile_requires_nocache', 10, 2 );

/** Register the Forum Topic List block from its server-owned metadata. */
function axismundi_forum_register_topic_list_block() : void {
	register_block_type( dirname( __DIR__ ) . '/blocks/topic-list' );
}
add_action( 'init', 'axismundi_forum_register_topic_list_block', 20 );

/** A Topic becomes an AS2 Page only after it has Forum context. */
function axismundi_forum_topic_article_supports( $source ) : bool {
	return $source instanceof WP_Post
		&& AXISMUNDI_FORUM_TOPIC_POST_TYPE === $source->post_type
		&& ( null !== axismundi_forum_get_topic_entry( $source->ID ) || axismundi_forum_get_remote_topic_group( $source ) instanceof Axismundi_Actor );
}

/** F1 permits only public, unprotected local Topic Pages to project. */
function axismundi_forum_topic_article_visible( WP_Post $topic ) : bool {
	$entry = axismundi_forum_get_topic_entry( $topic->ID );
	return axismundi_forum_topic_article_supports( $topic ) && 'publish' === $topic->post_status && '' === (string) $topic->post_password && is_post_publicly_viewable( $topic ) && '' !== axismundi_op_local_author_actor_uri( (int) $topic->post_author )
		&& ( ! is_array( $entry ) || 'members' !== axismundi_forum_get_distribution_scope( (int) $entry['group_identity_id'] ) );
}

/**
 * Transform an admitted Topic into an Article without starting an activity lifecycle.
 *
 * `Article` rather than the `Page` Lemmy publishes: `Page` is a `Document` subtype, and
 * `Document` is already how this site publishes every non-image/audio/video attachment, so
 * `Page` would file a forum thread beside a PDF in our own ontology (Constitution Article 13).
 *
 * `audience` carries the Group, which is who the post is addressed to and who redistributes
 * it. `context` carries the thread, which is a different question — what conversation this
 * belongs to — and previously carried the Group as well. FEP-7888 permits a forum as
 * `context`, so that was not invalid, but it made every reply in the Group share one context
 * and left nothing able to name an individual thread.
 */
function axismundi_forum_topic_to_article( WP_Post $topic ) {
	$entry = axismundi_forum_get_topic_entry( $topic->ID );
	$group = is_array( $entry ) && function_exists( 'axismundi_actors_get_by_identity' ) ? axismundi_actors_get_by_identity( (int) $entry['group_identity_id'] ) : axismundi_forum_get_remote_topic_group( $topic );
	$author = function_exists( 'axismundi_actors_get_for_user' ) ? axismundi_actors_get_for_user( (int) $topic->post_author ) : null;
	if ( ! $group instanceof Axismundi_Actor || ! $author instanceof Axismundi_Actor || ! $author->is_local() || 'Person' !== $author->get_type() || ! function_exists( 'axismundi_actors_is_public_profile' ) || ! axismundi_actors_is_public_profile( $author ) ) {
		return new WP_Error( 'ax_forum_topic_projection', __( 'The Topic lacks a valid Forum context or author.', 'axismundi-forum' ) );
	}
	$author_uri = $author->get_uri();
	$object_uri = axismundi_forum_topic_object_uri( $topic );
	$cc         = array();
	/*
	 * Threadiverse peers, including Lemmy, validate that a submitted thread Object is
	 * public even though its Create is delivered directly to the Group. `cc` preserves
	 * that public-routing signal without making the Person's direct submission their
	 * profile delivery; the Group Announce remains the public community surface.
	 * Bridge recognises the direct Group submission and does not fan it out through the
	 * author's followers.
	 */
	$public_submission = ! $group->is_local()
		|| ( is_array( $entry ) && 'public' === axismundi_forum_get_distribution_scope( (int) $entry['group_identity_id'] ) );
	if ( $public_submission ) {
		$cc[] = axismundi_act_public_audience_uri();
	}
	if ( ! $group->is_local() ) {
		$followers = function_exists( 'axismundi_actors_get_endpoint' ) ? axismundi_actors_get_endpoint( $group, 'followers' ) : '';
		if ( '' !== $followers ) {
			$cc[] = $followers;
		}
	}
	return array(
		'id'              => $object_uri,
		'type'            => 'Article',
		'attributedTo'    => $author_uri,
		'audience'        => $group->get_uri(),
		'context'         => axismundi_forum_topic_context_uri( $topic ),
		'url'             => array( 'type' => 'Link', 'href' => get_permalink( $topic ), 'mediaType' => 'text/html' ),
		'name'            => get_the_title( $topic ),
		'content'         => axismundi_op_render_post_content( $topic ),
		'mediaType'       => 'text/html',
		'published'       => get_post_time( DATE_W3C, true, $topic ),
		'updated'         => axismundi_forum_topic_content_modified_time( $topic ),
		// A Topic is submitted to its Group. The Group's Announce, not the author's Create,
		// is the distribution event, so this never addresses the author's followers.
		'to'              => array( $group->get_uri() ),
		'cc'              => $cc,
		'commentsEnabled' => ! is_array( $entry ) || empty( $entry['locked_at'] ),
	);
}

/** Resolve a local Topic source from its stable Page URI. */
function axismundi_forum_resolve_topic_source( $source, string $uri ) {
	if ( null !== $source ) {
		return $source;
	}
	$parts = wp_parse_url( $uri );
	if ( ! is_array( $parts ) || empty( $parts['query'] ) ) {
		return null;
	}
	parse_str( (string) $parts['query'], $args );
	$topic = isset( $args['p'] ) ? get_post( absint( $args['p'] ) ) : null;
	return $topic instanceof WP_Post && axismundi_forum_topic_article_supports( $topic ) && hash_equals( $uri, axismundi_forum_topic_object_uri( $topic ) ) ? $topic : null;
}
add_filter( 'axismundi_op_resolve_source_by_uri', 'axismundi_forum_resolve_topic_source', 11, 2 );

/**
 * Let an entitled reader see a members-only Topic's card on the community's own archive.
 *
 * The renderer's default gate asks whether a source may be republished publicly, and for a
 * members-only Topic the answer is no for everyone — which is right for the federated
 * representation and wrong for the member reading the community they belong to. Without this the
 * archive would render a row of nothing to exactly the people it exists for.
 *
 * Two things keep this from being a hole. The caller has to ask, by naming the community it
 * already authorized the reader against, so no other surface is widened by accident. And the
 * Topic has to actually be in that community and readable by this reader, which is the same
 * question the HTML permalink answers — a caller naming the wrong community, or a reader who
 * simply is not a member, gets the public answer back unchanged.
 *
 * @param bool  $public Whether the source is publicly card-renderable.
 * @param mixed $source Resolved source.
 * @param array $opts   Renderer options supplied by the caller.
 * @return bool
 */
function axismundi_forum_open_community_topic_card( bool $public, $source, array $opts ) : bool {
	$group_identity_id = isset( $opts['communityViewer'] ) ? (int) $opts['communityViewer'] : 0;
	if ( $public || $group_identity_id <= 0 || ! $source instanceof WP_Post || ! axismundi_forum_topic_article_supports( $source ) ) {
		return $public;
	}
	$entry = axismundi_forum_get_topic_entry( $source->ID );
	if ( ! is_array( $entry ) || (int) $entry['group_identity_id'] !== $group_identity_id || 'publish' !== $source->post_status ) {
		return $public;
	}
	return axismundi_forum_can_read_topic( $source );
}
add_filter( 'axismundi_op_object_card_renderable', 'axismundi_forum_open_community_topic_card', 10, 3 );

/** Register the Forum Topic Page transformer through OP's public registry seam. */
function axismundi_forum_register_topic_page_transformer() : void {
	if ( ! function_exists( 'axismundi_op_register_object_transformer' ) ) {
		return;
	}
	axismundi_op_register_object_transformer(
		'forum-topic-article',
		array(
			'supports'   => 'axismundi_forum_topic_article_supports',
			'object_uri' => 'axismundi_forum_topic_object_uri',
			'transform'  => 'axismundi_forum_topic_to_article',
			'visible'    => 'axismundi_forum_topic_article_visible',
			'priority'   => 20,
		)
	);
}
add_action( 'axismundi_op_register_transformers', 'axismundi_forum_register_topic_page_transformer' );
