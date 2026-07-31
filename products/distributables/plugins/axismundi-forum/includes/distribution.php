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

/**
 * Keep a local Topic's Core review state aligned with its community admission state.
 *
 * Remote submissions have no local post and retain Forum's projection-only state. This helper
 * is deliberately private to the local-source lifecycle: WordPress remains the authoring
 * workflow, while the Group decides whether a submitted Topic is distributed.
 */
function axismundi_forum_set_local_topic_review_status( array $entry, string $status ) {
	if ( ! in_array( $status, array( 'pending', 'publish' ), true ) ) {
		return new WP_Error( 'ax_forum_topic_status', __( 'The Topic review status is invalid.', 'axismundi-forum' ) );
	}
	$source_post_id = (int) ( $entry['source_post_id'] ?? 0 );
	if ( $source_post_id <= 0 ) {
		return true;
	}
	$topic = get_post( $source_post_id );
	if ( ! $topic instanceof WP_Post || AXISMUNDI_FORUM_TOPIC_POST_TYPE !== $topic->post_type ) {
		return new WP_Error( 'ax_forum_topic_missing', __( 'The local Topic submission is unavailable.', 'axismundi-forum' ) );
	}
	if ( $status === $topic->post_status ) {
		return true;
	}
	$GLOBALS['axismundi_forum_internal_topic_status_sync'][ $topic->ID ] = true;
	try {
		$updated = wp_update_post( array( 'ID' => $topic->ID, 'post_status' => $status ), true );
	} finally {
		unset( $GLOBALS['axismundi_forum_internal_topic_status_sync'][ $topic->ID ] );
	}
	return is_wp_error( $updated )
		? new WP_Error( 'ax_forum_topic_status_write', __( 'The Topic review status could not be updated.', 'axismundi-forum' ) )
		: true;
}

/** Whether an internal Forum transition currently owns this Topic's Core status update. */
function axismundi_forum_is_internal_topic_status_sync( int $topic_post_id ) : bool {
	return ! empty( $GLOBALS['axismundi_forum_internal_topic_status_sync'][ $topic_post_id ] );
}

/** A Group moderator, the local source author, or a site editor may withdraw a local source. */
function axismundi_forum_user_can_withdraw_announced_entry( array $entry, int $user_id ) : bool {
	if ( $user_id <= 0 ) {
		return false;
	}
	if ( function_exists( 'axismundi_forum_user_can_moderate' ) && axismundi_forum_user_can_moderate( (int) $entry['group_identity_id'], $user_id ) ) {
		return true;
	}
	if ( user_can( $user_id, 'edit_others_posts' ) ) {
		return true;
	}
	$source_post_id = (int) ( $entry['source_post_id'] ?? 0 );
	$topic = $source_post_id > 0 ? get_post( $source_post_id ) : null;
	return $topic instanceof WP_Post && $user_id === (int) $topic->post_author;
}

/** @return array<int,array<string,mixed>> Every recorded Group distribution for one entry. */
function axismundi_forum_entry_distributions( int $entry_id ) : array {
	if ( $entry_id <= 0 ) {
		return array();
	}
	global $wpdb;
	$table = axismundi_forum_entry_distributions_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Forum owns this per-entry distribution ledger.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE entry_id = %d ORDER BY id ASC", $entry_id ), ARRAY_A );
}

/** Record the immutable Group Announce that distributed one submission Activity. */
function axismundi_forum_record_entry_distribution( int $entry_id, Axismundi_Activity $submission, Axismundi_Activity $announce ) {
	global $wpdb;
	$table = axismundi_forum_entry_distributions_table();
	$hash = hash( 'sha256', $announce->get_uri() );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- collision-safe idempotent lookup in the Forum-owned ledger.
	$existing = $wpdb->get_var( $wpdb->prepare( "SELECT announce_activity_uri FROM {$table} WHERE entry_id = %d AND announce_activity_uri_hash = %s", $entry_id, $hash ) );
	if ( is_string( $existing ) && '' !== $existing ) {
		return hash_equals( $existing, $announce->get_uri() )
			? true
			: new WP_Error( 'ax_forum_distribution_collision', __( 'A community distribution identity collision was detected.', 'axismundi-forum' ) );
	}
	$inserted = $wpdb->insert(
		$table,
		array(
			'entry_id'                   => $entry_id,
			'submission_activity_uri'    => $submission->get_uri(),
			'announce_activity_uri'      => $announce->get_uri(),
			'announce_activity_uri_hash' => $hash,
			'created_at'                 => current_time( 'mysql', true ),
		),
		array( '%d', '%s', '%s', '%s', '%s' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- immutable Forum distribution record.
	return false === $inserted ? new WP_Error( 'ax_forum_distribution_write', __( 'The community distribution could not be recorded.', 'axismundi-forum' ) ) : true;
}

/** Read the submission Activity wrapped by a Group Announce. */
function axismundi_forum_announce_submission_uri( Axismundi_Activity $announce ) : string {
	return (string) ( $announce->get_payload()['object']['id'] ?? '' );
}

/**
 * Record the Group Announce for one exact submission Activity, returning an existing effective row.
 *
 * Create and Update are approval or edit submissions. Delete is different: the author addresses it
 * directly to the Group, and the Group redistributes it only when this entry has a prior delivery.
 */
function axismundi_forum_record_group_submission_announce( array $entry, Axismundi_Activity $submission ) {
	$group = axismundi_forum_get_community_group( (int) ( $entry['group_identity_id'] ?? 0 ) );
	if ( ! $group instanceof Axismundi_Actor || ! $group->is_local() || 'public' !== $group->get_status()
		|| ! function_exists( 'axismundi_act_record_source_activity' ) || ! function_exists( 'axismundi_op_actor_followers_url' ) ) {
		return new WP_Error( 'ax_forum_announce_group', __( 'The local community Group cannot distribute this Topic.', 'axismundi-forum' ) );
	}
	foreach ( axismundi_forum_entry_distributions( (int) $entry['id'] ) as $distribution ) {
		$existing = function_exists( 'axismundi_act_get' ) ? axismundi_act_get( (string) $distribution['announce_activity_uri'] ) : null;
		if ( $existing instanceof Axismundi_Activity && 'Announce' === $existing->get_type() && $existing->is_effective() && hash_equals( $group->get_uri(), $existing->get_actor_uri() ) && hash_equals( $submission->get_uri(), axismundi_forum_announce_submission_uri( $existing ) ) ) {
			return $existing;
		}
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
	$announce = axismundi_act_record_source_activity(
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
	return is_wp_error( $announce ) ? $announce : ( is_wp_error( axismundi_forum_record_entry_distribution( (int) $entry['id'], $submission, $announce ) ) ? new WP_Error( 'ax_forum_distribution_write', __( 'The community distribution could not be recorded.', 'axismundi-forum' ) ) : $announce );
}

/** Record the Group Announce for one validated Create or Update entry submission. */
function axismundi_forum_record_group_announce( array $entry ) {
	$submission = axismundi_forum_entry_submission_activity( $entry );
	return is_wp_error( $submission ) ? $submission : axismundi_forum_record_group_submission_announce( $entry, $submission );
}

/** Whether a Topic has ever been distributed by its Group, including pre-ledger entries. */
function axismundi_forum_entry_has_distribution_history( array $entry ) : bool {
	if ( ! empty( axismundi_forum_entry_distributions( (int) ( $entry['id'] ?? 0 ) ) ) ) {
		return true;
	}
	$legacy = function_exists( 'axismundi_act_get' ) ? axismundi_act_get( (string) ( $entry['announced_activity_uri'] ?? '' ) ) : null;
	return $legacy instanceof Axismundi_Activity && 'Announce' === $legacy->get_type();
}

/** Record the author's direct, Group-addressed Delete for one admitted local Topic. */
function axismundi_forum_record_topic_delete( WP_Post $topic ) {
	$entry = axismundi_forum_get_topic_entry( $topic->ID );
	$author = function_exists( 'axismundi_actors_get_for_user' ) ? axismundi_actors_get_for_user( (int) $topic->post_author ) : null;
	if ( ! is_array( $entry ) || ! $author instanceof Axismundi_Actor || ! $author->is_local() || 'Person' !== $author->get_type() || ! function_exists( 'axismundi_act_record_object_delete' ) ) {
		return null;
	}
	return axismundi_act_record_object_delete( axismundi_forum_topic_object_uri( $topic ), $author->get_uri() );
}

/**
 * Permanently deleting a Topic records its direct Delete before its Forum entry disappears.
 *
 * A pending Topic without Group delivery only tells the Group that its submitted Object ended.
 * Once any Group Announce exists, the Group also Announce(Delete)s it to the same followers so
 * a withdrawn-but-cached Topic cannot remain a community ghost on remote servers.
 *
 * @param WP_Post|false|null $delete Short-circuit value.
 * @return WP_Post|false|null
 */
function axismundi_forum_pre_delete_topic_distribution( $delete, WP_Post $topic ) {
	if ( false === $delete || AXISMUNDI_FORUM_TOPIC_POST_TYPE !== $topic->post_type ) {
		return $delete;
	}
	$entry = axismundi_forum_get_topic_entry( $topic->ID );
	if ( ! is_array( $entry ) ) {
		return $delete;
	}
	$source_delete = axismundi_forum_record_topic_delete( $topic );
	if ( is_wp_error( $source_delete ) ) {
		return false;
	}
	if ( ! $source_delete instanceof Axismundi_Activity || ! axismundi_forum_entry_has_distribution_history( $entry ) ) {
		return $delete;
	}
	$announce = axismundi_forum_record_group_submission_announce( $entry, $source_delete );
	return is_wp_error( $announce ) ? false : $delete;
}
add_filter( 'pre_delete_post', 'axismundi_forum_pre_delete_topic_distribution', 30, 2 );

/** Resolve the addressed community Group from a Note reply's parent Object. */
function axismundi_forum_note_reply_group( Axismundi_Note_Source $source ) : ?Axismundi_Actor {
	$envelope = $source->get_envelope();
	$parent_uri = (string) ( $envelope['in_reply_to_uri'] ?? '' );
	if ( '' === $parent_uri || ! function_exists( 'axismundi_op_resolve_source_by_uri' ) || ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return null;
	}
	$parent = array();
	$parent_source = axismundi_op_resolve_source_by_uri( $parent_uri );
	if ( null !== $parent_source && ! $parent_source instanceof Axismundi_Op_Remote_Source && function_exists( 'axismundi_op_resolve_object_transformer' ) ) {
		$transformer = axismundi_op_resolve_object_transformer( $parent_source );
		$parent = is_array( $transformer ) ? (array) call_user_func( $transformer['transform'], $parent_source ) : array();
	}
	if ( empty( $parent ) && function_exists( 'axismundi_op_remote_object_get' ) ) {
		$stored = axismundi_op_remote_object_get( $parent_uri, false );
		$parent = is_array( $stored ) ? (array) ( $stored['payload'] ?? array() ) : array();
	}
	/*
	 * A root Topic names its Group in `audience`, while Lemmy comments place the
	 * community in `cc` beside the parent author. Accept either representation:
	 * the Group is the destination to continue the thread in, not necessarily
	 * the parent Note's visibility owner.
	 */
	$group_members = array_merge(
		axismundi_forum_member_uris( $parent['audience'] ?? array() ),
		axismundi_forum_member_uris( $parent['to'] ?? array() ),
		axismundi_forum_member_uris( $parent['cc'] ?? array() )
	);
	foreach ( array_values( array_unique( $group_members ) ) as $uri ) {
		$group = axismundi_actors_get_by_uri( $uri );
		if ( $group instanceof Axismundi_Actor && 'Group' === $group->get_type() && 'public' === $group->get_status() && ( ! $group->is_local() || axismundi_forum_is_community( $group->get_identity_id() ) ) ) {
			return $group;
		}
	}
	return null;
}

/** Whether this user may submit a threaded Note directly to this local or remote Group. */
function axismundi_forum_user_can_submit_reply_to_group( Axismundi_Actor $group, int $user_id ) : bool {
	if ( $group->is_local() ) {
		return axismundi_forum_user_can_post_to_community( $group->get_identity_id(), $user_id );
	}
	return function_exists( 'axismundi_forum_user_can_submit_to_remote_group' )
		&& axismundi_forum_user_can_submit_to_remote_group( $user_id, $group );
}

/** Address a Note reply to its parent Topic's Group instead of the author's personal audience. */
function axismundi_forum_note_reply_audience( array $audience, Axismundi_Note_Source $source ) : array {
	$group = axismundi_forum_note_reply_group( $source );
	$post = $source->get_post();
	$user_id = $post instanceof WP_Post ? (int) $post->post_author : 0;
	if ( ! $group instanceof Axismundi_Actor || ! axismundi_forum_user_can_submit_reply_to_group( $group, $user_id ) ) {
		return $audience;
	}
	$mentions = $post instanceof WP_Post && function_exists( 'axismundi_note_mentions' ) ? axismundi_note_mentions( $post ) : array();
	// Lemmy validates a remote-community submission as public even though only the Group receives it directly.
	if ( ! $group->is_local() ) {
		$mentions[] = function_exists( 'axismundi_act_public_audience_uri' ) ? axismundi_act_public_audience_uri() : 'https://www.w3.org/ns/activitystreams#Public';
	}
	return array( 'public' => ! $group->is_local(), 'to' => array( $group->get_uri() ), 'cc' => array_values( array_unique( $mentions ) ) );
}
add_filter( 'axismundi_note_source_audience', 'axismundi_forum_note_reply_audience', 10, 2 );

/** Name the community on a threaded Note so peers can navigate reply → Topic → Group. */
function axismundi_forum_note_reply_object( array $object, Axismundi_Note_Source $source ) : array {
	$group = axismundi_forum_note_reply_group( $source );
	$post = $source->get_post();
	if ( $group instanceof Axismundi_Actor && $post instanceof WP_Post && axismundi_forum_user_can_submit_reply_to_group( $group, (int) $post->post_author ) ) {
		$object['audience'] = $group->get_uri();
	}
	return $object;
}
add_filter( 'axismundi_note_project_object', 'axismundi_forum_note_reply_object', 10, 2 );

/** Redistribute a local Group-addressed Note lifecycle through that Group's followers. */
function axismundi_forum_distribute_group_reply_activity( Axismundi_Activity $activity ) : void {
	if ( 'outbound' !== $activity->get_direction() || ! $activity->is_effective() || ! in_array( $activity->get_type(), array( 'Create', 'Update', 'Delete' ), true ) ) {
		return;
	}
	$payload = $activity->get_payload();
	$object = $payload['object'] ?? null;
	$note_source = null;
	if ( is_array( $object ) && 'Note' === (string) ( $object['type'] ?? '' ) && ! empty( $object['inReplyTo'] ) && function_exists( 'axismundi_note_local_uuid_from_uri' ) && function_exists( 'axismundi_note_get_by_uuid' ) ) {
		$uuid = axismundi_note_local_uuid_from_uri( (string) ( $object['id'] ?? '' ) );
		$envelope = null === $uuid ? null : axismundi_note_get_by_uuid( $uuid );
		$post = is_array( $envelope ) ? get_post( (int) $envelope['post_id'] ) : null;
		$note_source = is_array( $envelope ) ? new Axismundi_Note_Source( $envelope, $post instanceof WP_Post ? $post : null ) : null;
	} elseif ( is_string( $object ) && function_exists( 'axismundi_note_local_uuid_from_uri' ) && function_exists( 'axismundi_note_get_by_uuid' ) ) {
		$uuid = axismundi_note_local_uuid_from_uri( $object );
		$envelope = null === $uuid ? null : axismundi_note_get_by_uuid( $uuid );
		$post = is_array( $envelope ) ? get_post( (int) $envelope['post_id'] ) : null;
		$note_source = is_array( $envelope ) ? new Axismundi_Note_Source( $envelope, $post instanceof WP_Post ? $post : null ) : null;
	}
	$group = $note_source instanceof Axismundi_Note_Source ? axismundi_forum_note_reply_group( $note_source ) : null;
	$note_post = $note_source instanceof Axismundi_Note_Source ? $note_source->get_post() : null;
	if ( ! $group instanceof Axismundi_Actor || ! $group->is_local() || ! $note_post instanceof WP_Post || ! axismundi_forum_user_can_submit_reply_to_group( $group, (int) $note_post->post_author ) ) {
		return;
	}
	$audience = axismundi_forum_distribution_audience( $group );
	if ( is_wp_error( $audience ) || ! function_exists( 'axismundi_act_record_source_activity' ) ) {
		return;
	}
	axismundi_act_record_source_activity(
		array( 'type' => 'Announce', 'actor' => $group->get_uri(), 'object' => $payload, 'to' => $audience['to'], 'cc' => $audience['cc'] ),
		'outbound',
		'forum-group-reply-announce:' . $group->get_identity_id() . ':' . $activity->get_uri()
	);
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_forum_distribute_group_reply_activity', 30 );

/** Whether an outbound Note lifecycle is a direct threaded submission to a Group. */
function axismundi_forum_is_direct_group_reply_activity( Axismundi_Activity $activity ) : bool {
	if ( 'outbound' !== $activity->get_direction() || ! in_array( $activity->get_type(), array( 'Create', 'Update' ), true ) ) {
		return false;
	}
	$object = $activity->get_payload()['object'] ?? null;
	if ( ! is_array( $object ) || 'Note' !== (string) ( $object['type'] ?? '' ) || empty( $object['inReplyTo'] ) || ! function_exists( 'axismundi_note_local_uuid_from_uri' ) || ! function_exists( 'axismundi_note_get_by_uuid' ) ) {
		return false;
	}
	$group_uri = axismundi_act_member_uri( $object['audience'] ?? '' );
	$uuid = axismundi_note_local_uuid_from_uri( (string) ( $object['id'] ?? '' ) );
	$envelope = null === $uuid ? null : axismundi_note_get_by_uuid( $uuid );
	$post = is_array( $envelope ) ? get_post( (int) $envelope['post_id'] ) : null;
	$source = is_array( $envelope ) && $post instanceof WP_Post ? new Axismundi_Note_Source( $envelope, $post ) : null;
	$group = $source instanceof Axismundi_Note_Source ? axismundi_forum_note_reply_group( $source ) : null;
	return $group instanceof Axismundi_Actor
		&& hash_equals( $group->get_uri(), $group_uri )
		&& in_array( $group_uri, (array) ( $activity->get_audience()['to'] ?? array() ), true )
		&& (string) ( $object['attributedTo'] ?? '' ) === $activity->get_actor_uri();
}

/** The Group's distribution, not the author's profile, is the public surface of a threaded reply. */
function axismundi_forum_group_reply_actor_feed_visible( bool $visible, Axismundi_Activity $activity ) : bool {
	return axismundi_forum_is_direct_group_reply_activity( $activity ) ? false : $visible;
}
add_filter( 'axismundi_act_actor_feed_activity_visible', 'axismundi_forum_group_reply_actor_feed_visible', 30, 2 );

/** Keep public routing for remote Group replies out of the author's public outbox. */
function axismundi_forum_group_reply_public_outbox_payload( $payload, Axismundi_Activity $activity ) {
	return axismundi_forum_is_direct_group_reply_activity( $activity ) ? null : $payload;
}
add_filter( 'axismundi_act_public_outbox_payload', 'axismundi_forum_group_reply_public_outbox_payload', 30, 2 );

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
	if ( ! axismundi_forum_user_can_withdraw_announced_entry( $entry, $user_id ) ) {
		return new WP_Error( 'ax_forum_forbidden', __( 'You may not withdraw this community Topic.', 'axismundi-forum' ) );
	}
	$group = axismundi_forum_get_community_group( (int) $entry['group_identity_id'] );
	if ( ! $group instanceof Axismundi_Actor || ! function_exists( 'axismundi_act_record_source_activity' ) ) {
		return new WP_Error( 'ax_forum_announce_missing', __( 'The active community Announce is unavailable.', 'axismundi-forum' ) );
	}
	$announces = array();
	foreach ( axismundi_forum_entry_distributions( (int) $entry['id'] ) as $distribution ) {
		$announce = function_exists( 'axismundi_act_get' ) ? axismundi_act_get( (string) $distribution['announce_activity_uri'] ) : null;
		if ( $announce instanceof Axismundi_Activity && 'Announce' === $announce->get_type() && $announce->is_effective() && hash_equals( $group->get_uri(), $announce->get_actor_uri() ) ) {
			$announces[ $announce->get_uri() ] = $announce;
		}
	}
	// Retain compatibility with entries distributed before the ledger existed.
	$legacy = function_exists( 'axismundi_act_get' ) ? axismundi_act_get( (string) ( $entry['announced_activity_uri'] ?? '' ) ) : null;
	if ( $legacy instanceof Axismundi_Activity && 'Announce' === $legacy->get_type() && $legacy->is_effective() && hash_equals( $group->get_uri(), $legacy->get_actor_uri() ) ) {
		$announces[ $legacy->get_uri() ] = $legacy;
	}
	if ( empty( $announces ) ) {
		return new WP_Error( 'ax_forum_announce_missing', __( 'The active community Announce is unavailable.', 'axismundi-forum' ) );
	}
	$status = axismundi_forum_set_local_topic_review_status( $entry, 'pending' );
	if ( is_wp_error( $status ) ) {
		return $status;
	}
	foreach ( $announces as $announce ) {
		$undo = axismundi_act_record_source_activity(
			array( 'type' => 'Undo', 'actor' => $group->get_uri(), 'object' => $announce->get_uri(), 'to' => (array) ( $announce->get_audience()['to'] ?? array() ), 'cc' => (array) ( $announce->get_audience()['cc'] ?? array() ) ),
			'outbound',
			'forum-group-withdraw-undo:' . $entry_id . ':announce:' . $announce->get_uri()
		);
		if ( is_wp_error( $undo ) ) {
			// Restore the Core source when the Group's withdrawal could not be recorded.
			axismundi_forum_set_local_topic_review_status( $entry, 'publish' );
			return $undo;
		}
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
 * Turn a local author or site editor's publish-to-pending transition into a Group withdrawal.
 *
 * WordPress owns the editor's status control. The Group still owns its Announce, so this hook
 * records the matching Undo and restores publish if that federated transition cannot be made.
 */
function axismundi_forum_handle_topic_withdrawal_transition( string $new_status, string $old_status, WP_Post $topic ) : void {
	$user_id = get_current_user_id();
	if ( 'publish' !== $old_status || 'pending' !== $new_status || AXISMUNDI_FORUM_TOPIC_POST_TYPE !== $topic->post_type || axismundi_forum_is_internal_topic_status_sync( $topic->ID ) || ( $user_id !== (int) $topic->post_author && ! user_can( $user_id, 'edit_others_posts' ) ) ) {
		return;
	}
	$entry = axismundi_forum_get_topic_entry( $topic->ID );
	if ( ! is_array( $entry ) || 'visible' !== (string) $entry['admission_state'] ) {
		return;
	}
	$result = axismundi_forum_withdraw_announced_entry( (int) $entry['id'], $user_id );
	if ( ! is_wp_error( $result ) ) {
		return;
	}
	// The editor's requested status cannot claim a withdrawal that the Group failed to record.
	axismundi_forum_set_local_topic_review_status( $entry, 'publish' );
}
add_action( 'transition_post_status', 'axismundi_forum_handle_topic_withdrawal_transition', 20, 3 );

/**
 * Redistribute a material edit of an already-approved local Topic as Announce(Update).
 *
 * The lifecycle recorder deduplicates autosaves, status-only changes, and identical content;
 * only its immutable Update is eligible for another Group distribution cycle.
 */
function axismundi_forum_distribute_visible_topic_update( int $post_id, WP_Post $after, WP_Post $before ) : void {
	unset( $before );
	if ( AXISMUNDI_FORUM_TOPIC_POST_TYPE !== $after->post_type || 'publish' !== $after->post_status || axismundi_forum_is_internal_topic_status_sync( $post_id ) ) {
		return;
	}
	$entry = axismundi_forum_get_topic_entry( $post_id );
	if ( ! is_array( $entry ) || 'visible' !== (string) $entry['admission_state'] || $post_id !== (int) $entry['source_post_id'] ) {
		return;
	}
	$submission = axismundi_forum_record_topic_commit( $after );
	if ( ! $submission instanceof Axismundi_Activity || 'Update' !== $submission->get_type() || hash_equals( (string) ( $entry['accepted_activity_uri'] ?? '' ), $submission->get_uri() ) ) {
		return;
	}
	global $wpdb;
	$updated = $wpdb->update(
		axismundi_forum_entries_table(),
		array( 'accepted_activity_uri' => $submission->get_uri(), 'updated_at' => current_time( 'mysql', true ) ),
		array( 'id' => (int) $entry['id'] ),
		array( '%s', '%s' ),
		array( '%d' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- link the visible entry to the exact Update the Group will distribute.
	if ( false === $updated ) {
		return;
	}
	$entry['accepted_activity_uri'] = $submission->get_uri();
	$announce = axismundi_forum_record_group_announce( $entry );
	if ( ! $announce instanceof Axismundi_Activity ) {
		return;
	}
	$wpdb->update(
		axismundi_forum_entries_table(),
		array( 'announced_activity_uri' => $announce->get_uri(), 'updated_at' => current_time( 'mysql', true ) ),
		array( 'id' => (int) $entry['id'] ),
		array( '%s', '%s' ),
		array( '%d' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- latest convenience pointer; the distribution ledger retains all cycles.
}
add_action( 'post_updated', 'axismundi_forum_distribute_visible_topic_update', 30, 3 );

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
	$status = axismundi_forum_set_local_topic_review_status( $entry, 'publish' );
	if ( is_wp_error( $status ) ) {
		return $status;
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
	$status = axismundi_forum_set_local_topic_review_status( $entry, 'pending' );
	if ( is_wp_error( $status ) ) {
		return $status;
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
