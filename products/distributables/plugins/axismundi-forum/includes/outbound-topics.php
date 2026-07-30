<?php
/**
 * Outbound Topic Pages addressed to cached remote Group Actors.
 *
 * A remote community is never copied into ax_forum. It is an Actors-registry
 * destination selected by a local Topic source. Following a remote community is
 * useful for its feed, but is not a prerequisite for submitting a Topic there.
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
		&& ! $group->is_local()
		&& 'Group' === $group->get_type()
		&& 'public' === $group->get_status();
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
	$groups    = array();
	$following = axismundi_act_get_follow_collection_page( 'subject', $author->get_uri(), 1, 12 );
	foreach ( (array) ( $following['items'] ?? array() ) as $uri ) {
		$group = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( (string) $uri ) : null;
		if ( $group instanceof Axismundi_Actor && axismundi_forum_user_can_submit_to_remote_group( $user_id, $group ) ) {
			$groups[ $group->get_identity_id() ] = $group;
		}
	}
	return $groups;
}

/** @return array<int,Axismundi_Actor> Recently used local or cached remote Topic destinations. */
function axismundi_forum_recent_topic_communities_for_user( int $user_id ) : array {
	if ( $user_id <= 0 || ! function_exists( 'axismundi_actors_get_by_identity' ) ) {
		return array();
	}
	global $wpdb;
	$entries = axismundi_forum_entries_table();
	$recent  = array();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded, author-scoped Forum projection lookup.
	$local_rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT e.group_identity_id, MAX(e.updated_at) AS used_at FROM {$entries} e INNER JOIN {$wpdb->posts} p ON p.ID = e.source_post_id WHERE p.post_author = %d AND p.post_type = %s GROUP BY e.group_identity_id ORDER BY used_at DESC LIMIT 12", $user_id, AXISMUNDI_FORUM_TOPIC_POST_TYPE ), ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded, author-scoped Topic meta lookup.
	$remote_rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT CAST(m.meta_value AS UNSIGNED) AS group_identity_id, MAX(p.post_modified_gmt) AS used_at FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND p.post_author = %d AND p.post_type = %s GROUP BY m.meta_value ORDER BY used_at DESC LIMIT 12", AXISMUNDI_FORUM_REMOTE_GROUP_META, $user_id, AXISMUNDI_FORUM_TOPIC_POST_TYPE ), ARRAY_A );
	foreach ( array_merge( $local_rows, $remote_rows ) as $row ) {
		$group_id = (int) ( $row['group_identity_id'] ?? 0 );
		if ( $group_id <= 0 ) {
			continue;
		}
		$used_at = (string) ( $row['used_at'] ?? '' );
		if ( ! isset( $recent[ $group_id ] ) || $used_at > $recent[ $group_id ] ) {
			$recent[ $group_id ] = $used_at;
		}
	}
	arsort( $recent, SORT_STRING );
	$groups = array();
	foreach ( array_slice( array_keys( $recent ), 0, 12 ) as $group_id ) {
		$group = axismundi_actors_get_by_identity( (int) $group_id );
		if ( $group instanceof Axismundi_Actor ) {
			$groups[ $group->get_identity_id() ] = $group;
		}
	}
	return $groups;
}

/** Add one eligible destination and merge its personal reasons without duplicating it. */
function axismundi_forum_add_topic_community_candidate( array &$items, Axismundi_Actor $group, int $topic_post_id, int $user_id, string $reason = '' ) : void {
	$is_local = $group->is_local();
	$allowed  = $is_local
		? axismundi_forum_can_admit_local_topic( $group->get_identity_id(), $topic_post_id, $user_id )
		: axismundi_forum_user_can_submit_to_remote_group( $user_id, $group );
	if ( ! $allowed ) {
		return;
	}
	$key = ( $is_local ? 'local:' : 'remote:' ) . $group->get_identity_id();
	if ( ! isset( $items[ $key ] ) ) {
		$items[ $key ] = array(
			'value'   => $key,
			'name'    => $group->get_display_name() ?: $group->get_preferred_username(),
			'handle'  => function_exists( 'axismundi_actors_mention_handle' ) ? axismundi_actors_mention_handle( $group ) : '@' . $group->get_preferred_username(),
			'local'   => $is_local,
			'reasons' => array(),
		);
	}
	if ( '' !== $reason && ! in_array( $reason, $items[ $key ]['reasons'], true ) ) {
		$items[ $key ]['reasons'][] = $reason;
	}
}

/** Return a small, personal set of likely Topic destinations before search starts. */
function axismundi_forum_suggest_topic_communities( int $topic_post_id, int $user_id ) : array {
	$items = array();
	foreach ( axismundi_forum_followed_remote_groups_for_user( $user_id ) as $group ) {
		axismundi_forum_add_topic_community_candidate( $items, $group, $topic_post_id, $user_id, __( 'Following', 'axismundi-forum' ) );
	}
	foreach ( function_exists( 'axismundi_forum_joined_local_communities_for_user' ) ? axismundi_forum_joined_local_communities_for_user( $user_id ) : array() as $group ) {
		axismundi_forum_add_topic_community_candidate( $items, $group, $topic_post_id, $user_id, __( 'Following', 'axismundi-forum' ) );
	}
	foreach ( function_exists( 'axismundi_forum_moderated_communities' ) ? axismundi_forum_moderated_communities( $user_id ) : array() as $group ) {
		axismundi_forum_add_topic_community_candidate( $items, $group, $topic_post_id, $user_id, __( 'Moderating', 'axismundi-forum' ) );
	}
	foreach ( axismundi_forum_recent_topic_communities_for_user( $user_id ) as $group ) {
		axismundi_forum_add_topic_community_candidate( $items, $group, $topic_post_id, $user_id, __( 'Recent', 'axismundi-forum' ) );
	}
	return array_slice( array_values( $items ), 0, 20 );
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
		return new WP_Error( 'ax_forum_remote_topic_forbidden', __( 'The author may not submit to that remote Group.', 'axismundi-forum' ) );
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

/** Return bounded, eligible Community search results for one Topic editor. */
function axismundi_forum_search_topic_communities( string $search, int $topic_post_id, int $user_id ) : array {
	$topic = get_post( $topic_post_id );
	if ( ! $topic instanceof WP_Post || AXISMUNDI_FORUM_TOPIC_POST_TYPE !== $topic->post_type || ! user_can( $user_id, 'edit_post', $topic_post_id ) || ! function_exists( 'axismundi_actors_search_public_groups' ) ) {
		return array();
	}
	if ( '' === trim( $search ) ) {
		return axismundi_forum_suggest_topic_communities( $topic_post_id, $user_id );
	}
	$items = array();
	foreach ( axismundi_actors_search_public_groups( $search, 20 ) as $group ) {
		axismundi_forum_add_topic_community_candidate( $items, $group, $topic_post_id, $user_id );
	}
	return array_values( $items );
}

/** REST permission for the Topic Community picker. */
function axismundi_forum_can_search_topic_communities( WP_REST_Request $request ) : bool {
	return current_user_can( 'edit_post', (int) $request->get_param( 'post_id' ) );
}

/** REST endpoint for the bounded Topic Community picker. */
function axismundi_forum_rest_search_topic_communities( WP_REST_Request $request ) : WP_REST_Response {
	return rest_ensure_response(
		axismundi_forum_search_topic_communities(
			sanitize_text_field( (string) $request->get_param( 'search' ) ),
			(int) $request->get_param( 'post_id' ),
			get_current_user_id()
		)
	);
}

/** Register the private, cache-only Community search route. */
function axismundi_forum_register_topic_community_search_route() : void {
	register_rest_route(
		'axismundi/v1',
		'/forum/community-search',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_forum_rest_search_topic_communities',
			'permission_callback' => 'axismundi_forum_can_search_topic_communities',
			'args'                => array(
				'search'  => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'post_id' => array( 'type' => 'integer', 'required' => true ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_forum_register_topic_community_search_route' );

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
