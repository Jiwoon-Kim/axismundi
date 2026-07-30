<?php
/**
 * Local Forum Topics and their ActivityStreams Page projection (Forum F1).
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_FORUM_TOPIC_POST_TYPE = 'ax_topic';
const AXISMUNDI_FORUM_REMOTE_GROUP_META = '_axismundi_forum_remote_group_identity_id';

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

/** @return array<string,string> Stable local Topic-admission policy labels. */
function axismundi_forum_posting_policies() : array {
	return array(
		'open'     => __( 'Anyone who can edit a Topic', 'axismundi-forum' ),
		'members'  => __( 'Accepted community members', 'axismundi-forum' ),
		'managers' => __( 'Group managers only', 'axismundi-forum' ),
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

/** Whether this user may admit this local Topic under the Forum's current F1 policy. */
function axismundi_forum_can_admit_local_topic( int $group_identity_id, int $topic_post_id, int $user_id ) : bool {
	if ( $user_id <= 0 || ! user_can( $user_id, 'edit_post', $topic_post_id ) || ! axismundi_forum_is_community( $group_identity_id ) ) {
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
	$actor      = axismundi_actors_get_for_user( $user_id );
	$membership = $actor instanceof Axismundi_Actor && 'Person' === $actor->get_type()
		? axismundi_forum_get_membership( $group_identity_id, $actor->get_identity_id() )
		: null;
	return is_array( $membership ) && 'accepted' === (string) $membership['membership_state'];
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
	$actor = function_exists( 'axismundi_actors_get_for_user' ) ? axismundi_actors_get_for_user( (int) $topic->post_author ) : null;
	$now   = current_time( 'mysql', true );
	global $wpdb;
	$fields = array(
			'group_identity_id'             => $group_identity_id,
			'object_uri'                    => axismundi_forum_topic_object_uri( $topic ),
			'object_uri_hash'               => hash( 'sha256', axismundi_forum_topic_object_uri( $topic ) ),
			'entry_type'                    => 'topic',
			'source_post_id'                => $topic_post_id,
			'admission_state'               => 'visible',
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
	return false === $ok ? new WP_Error( 'ax_forum_topic_write', __( 'The Topic could not be added to this Forum.', 'axismundi-forum' ) ) : true;
}

/** Get public visible Topic post ids for the Forum Topic list. */
function axismundi_forum_topic_ids( int $group_identity_id, int $limit = 20 ) : array {
	global $wpdb;
	$table = axismundi_forum_entries_table();
	$limit = max( 1, min( 100, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed WordPress posts + Forum-entry table names; values prepared.
	return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT e.source_post_id FROM {$table} e INNER JOIN {$wpdb->posts} p ON p.ID = e.source_post_id WHERE e.group_identity_id = %d AND e.entry_type = 'topic' AND e.admission_state = 'visible' AND e.moderation_state = 'visible' AND p.post_status = 'publish' ORDER BY e.sticky_position IS NULL, e.sticky_position DESC, p.post_date_gmt DESC LIMIT %d", $group_identity_id, $limit ) ) );
}

/** @return array<int,array<string,mixed>> Local and remote visible Topic entries for one Forum. */
function axismundi_forum_visible_topic_entries( int $group_identity_id, int $limit = 20 ) : array {
	if ( $group_identity_id <= 0 ) {
		return array();
	}
	global $wpdb;
	$table = axismundi_forum_entries_table();
	$limit = max( 1, min( 100, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed Forum-context query; remote entries intentionally have no WordPress post row.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE group_identity_id = %d AND entry_type = 'topic' AND admission_state = 'visible' AND moderation_state = 'visible' ORDER BY sticky_position IS NULL, sticky_position DESC, created_at DESC, id DESC LIMIT %d", $group_identity_id, $limit ), ARRAY_A );
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
function axismundi_forum_render_topic_list_block( array $attributes = array(), string $content = '' ) : string {
	unset( $content );
	$group_identity_id = axismundi_forum_context_group_id();
	if ( $group_identity_id <= 0 ) {
		return '';
	}
	$limit = isset( $attributes['perPage'] ) ? (int) $attributes['perPage'] : 20;
	$items = array();
	foreach ( axismundi_forum_visible_topic_entries( $group_identity_id, $limit ) as $entry ) {
		$source_post_id = (int) ( $entry['source_post_id'] ?? 0 );
		if ( $source_post_id > 0 ) {
			$topic = get_post( $source_post_id );
			if ( $topic instanceof WP_Post && 'publish' === $topic->post_status ) {
				$items[] = '<li class="axismundi-forum-topic-list__item"><a href="' . esc_url( get_permalink( $topic ) ) . '">' . esc_html( get_the_title( $topic ) ) . '</a></li>';
			}
			continue;
		}
		$author = function_exists( 'axismundi_actors_get_by_identity' ) ? axismundi_actors_get_by_identity( (int) ( $entry['submission_actor_identity_id'] ?? 0 ) ) : null;
		$card   = function_exists( 'axismundi_op_render_object_by_uri' )
			? axismundi_op_render_object_by_uri( (string) ( $entry['object_uri'] ?? '' ), array( 'headingTag' => 'h3', 'interactions' => false, 'expected_author' => $author instanceof Axismundi_Actor ? $author->get_uri() : '' ) )
			: '';
		if ( '' !== $card ) {
			$items[] = '<li class="axismundi-forum-topic-list__item axismundi-forum-topic-list__item--remote">' . $card . '</li>';
		}
	}
	$body = empty( $items )
		? '<p class="axismundi-forum-topic-list__empty">' . esc_html__( 'No topics yet.', 'axismundi-forum' ) . '</p>'
		: '<ol class="axismundi-forum-topic-list__items">' . implode( '', $items ) . '</ol>';
	/*
	 * This renderer answers to two callers: the block, and the Group profile feed, which has
	 * no block being rendered around it. get_block_wrapper_attributes() reads the block
	 * currently on the stack and warns when there is none, so the plain class attribute is
	 * used in that case rather than asking core for supports that no block declared.
	 */
	$wrapper = null === WP_Block_Supports::$block_to_render
		? 'class="axismundi-forum-topic-list"'
		: get_block_wrapper_attributes( array( 'class' => 'axismundi-forum-topic-list' ) );
	return '<section ' . $wrapper . '><h2>' . esc_html__( 'Topics', 'axismundi-forum' ) . '</h2>' . $body . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapper is a fixed literal or core-escaped.
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
	if ( '' !== $html || ! $actor->is_local() || ! $actor->is_managed() || 'Group' !== $actor->get_type() ) {
		return $html;
	}
	return axismundi_forum_is_community( $actor->get_identity_id() )
		? axismundi_forum_render_topic_list_block()
		: $html;
}
add_filter( 'axismundi_act_actor_feed_html', 'axismundi_forum_actor_feed_html', 10, 2 );

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
	return axismundi_forum_topic_article_supports( $topic ) && 'publish' === $topic->post_status && '' === (string) $topic->post_password && is_post_publicly_viewable( $topic ) && '' !== axismundi_op_local_author_actor_uri( (int) $topic->post_author );
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
	$author_uri = axismundi_op_local_author_actor_uri( (int) $topic->post_author );
	if ( ! $group instanceof Axismundi_Actor || '' === $author_uri ) {
		return new WP_Error( 'ax_forum_topic_projection', __( 'The Topic lacks a valid Forum context or author.', 'axismundi-forum' ) );
	}
	$object_uri = axismundi_forum_topic_object_uri( $topic );
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
		'updated'         => get_post_modified_time( DATE_W3C, true, $topic ),
		'to'              => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		'cc'              => $group->is_local() ? array() : array( $group->get_uri() ),
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
