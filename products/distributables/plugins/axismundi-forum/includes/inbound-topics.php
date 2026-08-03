<?php
/**
 * Admission of public remote Page Creates into a bound local Forum.
 *
 * The Activity ledger verifies the Create and Object Projections stores its Object
 * first. Forum then makes the narrow, contextual decision: an accepted remote
 * follower may create one top-level Page addressed to the bound Group. The source
 * Object remains owned by Object Projections; this file owns only its Forum entry.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/** @return string[] All absolute member URIs represented by a scalar, object, or list. */
function axismundi_forum_member_uris( $value ) : array {
	if ( is_scalar( $value ) ) {
		$uri = function_exists( 'axismundi_act_uri' ) ? axismundi_act_uri( $value ) : '';
		return '' === $uri ? array() : array( $uri );
	}
	if ( ! is_array( $value ) ) {
		return array();
	}
	if ( array_is_list( $value ) ) {
		$uris = array();
		foreach ( $value as $member ) {
			$uris = array_merge( $uris, axismundi_forum_member_uris( $member ) );
		}
		return array_values( array_unique( $uris ) );
	}
	return axismundi_forum_member_uris( $value['id'] ?? $value['href'] ?? '' );
}

/**
 * Whether an inbound object type is an acceptable Forum root post.
 *
 * We publish `Article` (Constitution Article 13), but ActivityStreams never gave forums a
 * root-post type, so every implementation picked its own: Lemmy sends `Page`, NodeBB sends
 * `Note`, Mobilizon sends `Article`. Refusing a peer's choice would refuse the peer. Strict
 * on send, lenient on receive — the type a Forum admits says nothing about the type it emits.
 *
 * `Note` is deliberately absent. A `Note` with no `inReplyTo` addressed to a Group is
 * ambiguous between a NodeBB topic and an ordinary microblog post that merely mentioned the
 * Group, and admitting the second as a Topic would let any passing mention open a thread.
 * NodeBB interop needs a signal beyond the type, and that is its own piece of work.
 *
 * @param string $object_type Stored remote object type.
 * @return bool
 */
function axismundi_forum_is_root_object_type( string $object_type ) : bool {
	/** Filter the inbound object types admitted as Forum root posts. @param string[] $types Defaults. */
	return in_array( $object_type, (array) apply_filters( 'axismundi_forum_root_object_types', array( 'Article', 'Page' ) ), true );
}

/** Whether a remote root post explicitly addresses or contextualizes this Group. */
function axismundi_forum_remote_root_addresses_group( array $payload, Axismundi_Activity $activity, Axismundi_Actor $group ) : bool {
	$group_uri = $group->get_uri();
	foreach ( array( 'context', 'audience', 'to', 'cc' ) as $property ) {
		if ( in_array( $group_uri, axismundi_forum_member_uris( $payload[ $property ] ?? array() ), true ) ) {
			return true;
		}
	}
	foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience' ) as $property ) {
		if ( in_array( $group_uri, (array) ( $activity->get_audience()[ $property ] ?? array() ), true ) ) {
			return true;
		}
	}
	return false;
}

/** One remote Forum entry by its contextual object URI, if it already exists. */
function axismundi_forum_get_remote_entry( int $group_identity_id, string $object_uri ) : ?array {
	if ( $group_identity_id <= 0 || '' === $object_uri ) {
		return null;
	}
	global $wpdb;
	$table = axismundi_forum_entries_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact contextual URI lookup on a Forum-owned projection.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE group_identity_id = %d AND object_uri_hash = %s", $group_identity_id, hash( 'sha256', $object_uri ) ), ARRAY_A );
	return is_array( $row ) && hash_equals( (string) $row['object_uri'], $object_uri ) ? $row : null;
}

/** Whether this remote Actor has an accepted membership in the Forum. */
function axismundi_forum_remote_actor_can_post( int $group_identity_id, Axismundi_Actor $actor ) : bool {
	if ( $actor->is_local() ) {
		return false;
	}
	$membership = axismundi_forum_get_membership( $group_identity_id, $actor->get_identity_id() );
	return is_array( $membership ) && 'accepted' === (string) $membership['membership_state'];
}

/**
 * Admit one already-cached remote Page as a top-level Topic entry.
 *
 * @return true|WP_Error
 */
function axismundi_forum_admit_remote_root( array $stored, Axismundi_Activity $activity ) {
	if ( 'Create' !== $activity->get_type() || 'inbound' !== $activity->get_direction() || ! $activity->is_effective()
		|| ! function_exists( 'axismundi_act_is_publicly_renderable' ) || ! axismundi_act_is_publicly_renderable( $activity )
		|| ! function_exists( 'axismundi_op_remote_object_is_publicly_listable' ) || ! axismundi_op_remote_object_is_publicly_listable( $stored )
		|| ! axismundi_forum_is_root_object_type( (string) ( $stored['object_type'] ?? '' ) ) || ! empty( $stored['in_reply_to_uri'] ) ) {
		return new WP_Error( 'ax_forum_remote_root_ineligible', __( 'The remote Object is not an eligible public top-level Forum root post.', 'axismundi-forum' ) );
	}
	$author = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $activity->get_actor_uri() ) : null;
	if ( ! $author instanceof Axismundi_Actor || $author->is_local() || ! hash_equals( $author->get_uri(), (string) ( $stored['attributed_to_uri'] ?? '' ) ) ) {
		return new WP_Error( 'ax_forum_remote_root_author', __( 'The remote Page author does not match its Create Actor.', 'axismundi-forum' ) );
	}

	$payload = (array) ( $stored['payload'] ?? array() );
	$object_uri = (string) ( $stored['object_uri'] ?? '' );
	// A local managed Group is selected only from the addressed/contextualized Actor
	// identities. A remote Group URI never creates a local Forum implicitly.
	foreach ( array_unique( array_merge(
		axismundi_forum_member_uris( $payload['context'] ?? array() ),
		axismundi_forum_member_uris( $payload['audience'] ?? array() ),
		axismundi_forum_member_uris( $payload['to'] ?? array() ),
		axismundi_forum_member_uris( $payload['cc'] ?? array() ),
		(array) ( $activity->get_audience()['to'] ?? array() ),
		(array) ( $activity->get_audience()['cc'] ?? array() )
	) ) as $candidate_uri ) {
		$group = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $candidate_uri ) : null;
		if ( ! $group instanceof Axismundi_Actor || ! $group->is_local() || ! $group->is_managed() || 'Group' !== $group->get_type() ) {
			continue;
		}
		$group_identity_id = $group->get_identity_id();
		if ( ! axismundi_forum_is_community( $group_identity_id ) || ! axismundi_forum_remote_root_addresses_group( $payload, $activity, $group ) || ! axismundi_forum_remote_actor_can_post( $group_identity_id, $author ) ) {
			continue;
		}
		$existing = axismundi_forum_get_remote_entry( $group_identity_id, $object_uri );
		if ( is_array( $existing ) ) {
			return (int) $existing['submission_actor_identity_id'] === $author->get_identity_id()
				? true
				: new WP_Error( 'ax_forum_remote_page_conflict', __( 'That remote Page already has a conflicting Forum entry.', 'axismundi-forum' ) );
		}
		$now = current_time( 'mysql', true );
		global $wpdb;
		$inserted = $wpdb->insert(
			axismundi_forum_entries_table(),
			array(
				'group_identity_id'             => $group_identity_id,
				'object_uri'                    => $object_uri,
				'object_uri_hash'               => hash( 'sha256', $object_uri ),
				'entry_type'                    => 'topic',
				'submission_actor_identity_id' => $author->get_identity_id(),
				// A remote submission is no more visible than a local one until the Group has
				// durably recorded its own Announce of the preserved incoming Create.
				'admission_state'               => 'pending',
				'moderation_state'              => 'visible',
				'accepted_activity_uri'          => $activity->get_uri(),
				'created_at'                    => $now,
				'updated_at'                    => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- membership-authorized contextual projection; source Object remains remote-cache owned.
		if ( false === $inserted ) {
			return new WP_Error( 'ax_forum_remote_page_write', __( 'The remote Page could not be admitted to this Forum.', 'axismundi-forum' ) );
		}
		$entry = axismundi_forum_get_remote_entry( $group_identity_id, $object_uri );
		if ( 'open' !== axismundi_forum_get_topic_approval_policy( $group_identity_id ) || ! is_array( $entry ) ) {
			return true;
		}
		return function_exists( 'axismundi_forum_publish_validated_pending_entry' )
			? axismundi_forum_publish_validated_pending_entry( $entry )
			: new WP_Error( 'ax_forum_remote_page_publish', __( 'The Group publication recorder is unavailable.', 'axismundi-forum' ) );
	}
	return new WP_Error( 'ax_forum_remote_page_target', __( 'The remote Page does not target an eligible local Forum Group.', 'axismundi-forum' ) );
}

/**
 * Accept and redistribute one public remote Note reply addressed to a local Group.
 *
 * Unlike a root Topic, a reply has no Forum entry of its own. Its acceptance is the
 * Group's immutable Announce of the verified incoming Create. Addressing alone is
 * not sufficient: the author must already be an accepted member of the Group.
 *
 * @return true|WP_Error
 */
function axismundi_forum_admit_remote_reply( array $stored, Axismundi_Activity $activity ) {
	if ( 'Create' !== $activity->get_type() || 'inbound' !== $activity->get_direction() || ! $activity->is_effective()
		|| ! function_exists( 'axismundi_act_is_publicly_renderable' ) || ! axismundi_act_is_publicly_renderable( $activity )
		|| ! function_exists( 'axismundi_op_remote_object_is_publicly_listable' ) || ! axismundi_op_remote_object_is_publicly_listable( $stored )
		|| 'Note' !== (string) ( $stored['object_type'] ?? '' ) || empty( $stored['in_reply_to_uri'] ) ) {
		return new WP_Error( 'ax_forum_remote_reply_ineligible', __( 'The remote Object is not an eligible public Forum reply.', 'axismundi-forum' ) );
	}
	$object_uri = (string) ( $stored['object_uri'] ?? '' );
	$author = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $activity->get_actor_uri() ) : null;
	if ( '' === $object_uri || ! $author instanceof Axismundi_Actor || $author->is_local() || ! hash_equals( $author->get_uri(), (string) ( $stored['attributed_to_uri'] ?? '' ) ) ) {
		return new WP_Error( 'ax_forum_remote_reply_author', __( 'The remote Note author does not match its Create Actor.', 'axismundi-forum' ) );
	}
	$payload = (array) ( $stored['payload'] ?? array() );
	foreach ( array_unique( array_merge(
		axismundi_forum_member_uris( $payload['context'] ?? array() ),
		axismundi_forum_member_uris( $payload['audience'] ?? array() ),
		axismundi_forum_member_uris( $payload['to'] ?? array() ),
		axismundi_forum_member_uris( $payload['cc'] ?? array() ),
		(array) ( $activity->get_audience()['to'] ?? array() ),
		(array) ( $activity->get_audience()['cc'] ?? array() )
	) ) as $candidate_uri ) {
		$group = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $candidate_uri ) : null;
		if ( ! $group instanceof Axismundi_Actor || ! $group->is_local() || ! $group->is_managed() || 'Group' !== $group->get_type()
			|| ! axismundi_forum_is_community( $group->get_identity_id() ) || ! axismundi_forum_remote_root_addresses_group( $payload, $activity, $group )
			|| ! axismundi_forum_remote_actor_can_post( $group->get_identity_id(), $author ) ) {
			continue;
		}
		$audience = function_exists( 'axismundi_forum_distribution_audience' ) ? axismundi_forum_distribution_audience( $group ) : new WP_Error( 'ax_forum_announce_group' );
		if ( is_wp_error( $audience ) || ! function_exists( 'axismundi_act_record_source_activity' ) ) {
			return is_wp_error( $audience ) ? $audience : new WP_Error( 'ax_forum_remote_reply_announce', __( 'The Group publication recorder is unavailable.', 'axismundi-forum' ) );
		}
		$announce = axismundi_act_record_source_activity(
			array(
				'type'   => 'Announce',
				'actor'  => $group->get_uri(),
				'object' => $activity->get_payload(),
				'to'     => $audience['to'],
				'cc'     => $audience['cc'],
			),
			'outbound',
			'forum-group-inbound-reply-announce:' . $group->get_identity_id() . ':' . $activity->get_uri()
		);
		return is_wp_error( $announce ) ? $announce : true;
	}
	return new WP_Error( 'ax_forum_remote_reply_target', __( 'The remote Note does not target an eligible local Forum Group.', 'axismundi-forum' ) );
}

/** Observe verified remote Objects after Object Projections has stored the source. */
function axismundi_forum_observe_remote_root( array $stored, Axismundi_Activity $activity ) : void {
	axismundi_forum_admit_remote_root( $stored, $activity );
	axismundi_forum_admit_remote_reply( $stored, $activity );
}
add_action( 'axismundi_op_remote_object_observed', 'axismundi_forum_observe_remote_root', 20, 2 );

/**
 * Reconsider an already-recorded inbound Create when its cached Object is refreshed.
 *
 * Remote fetches do not create a second ledger Activity. Looking up the verified Create
 * preserves the same admission evidence while allowing deployments to repair cached replies
 * that arrived before Forum learned to announce them.
 */
function axismundi_forum_observe_fetched_remote_reply( array $stored ) : void {
	$object_uri = trim( (string) ( $stored['object_uri'] ?? '' ) );
	if ( '' === $object_uri || 'Note' !== (string) ( $stored['object_type'] ?? '' ) || empty( $stored['in_reply_to_uri'] ) || ! function_exists( 'axismundi_act_get_by_object' ) ) {
		return;
	}
	foreach ( axismundi_act_get_by_object( $object_uri, 50 ) as $activity ) {
		if ( $activity instanceof Axismundi_Activity && 'Create' === $activity->get_type() && 'inbound' === $activity->get_direction() && $activity->is_effective() ) {
			axismundi_forum_admit_remote_reply( $stored, $activity );
		}
	}
}
add_action( 'axismundi_op_remote_object_fetched', 'axismundi_forum_observe_fetched_remote_reply', 20 );
