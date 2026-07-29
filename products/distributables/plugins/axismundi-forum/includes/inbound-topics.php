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

/** Whether a remote Page explicitly addresses or contextualizes this Group. */
function axismundi_forum_remote_page_addresses_group( array $payload, Axismundi_Activity $activity, Axismundi_Actor $group ) : bool {
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
function axismundi_forum_get_remote_entry( int $forum_post_id, string $object_uri ) : ?array {
	if ( $forum_post_id <= 0 || '' === $object_uri ) {
		return null;
	}
	global $wpdb;
	$table = axismundi_forum_entries_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact contextual URI lookup on a Forum-owned projection.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE forum_post_id = %d AND object_uri_hash = %s", $forum_post_id, hash( 'sha256', $object_uri ) ), ARRAY_A );
	return is_array( $row ) && hash_equals( (string) $row['object_uri'], $object_uri ) ? $row : null;
}

/** Whether this remote Actor has an accepted membership in the Forum. */
function axismundi_forum_remote_actor_can_post( int $forum_post_id, Axismundi_Actor $actor ) : bool {
	if ( $actor->is_local() ) {
		return false;
	}
	$membership = axismundi_forum_get_membership( $forum_post_id, $actor->get_identity_id() );
	return is_array( $membership ) && 'accepted' === (string) $membership['membership_state'];
}

/**
 * Admit one already-cached remote Page as a top-level Topic entry.
 *
 * @return true|WP_Error
 */
function axismundi_forum_admit_remote_page( array $stored, Axismundi_Activity $activity ) {
	if ( 'Create' !== $activity->get_type() || 'inbound' !== $activity->get_direction() || ! $activity->is_effective()
		|| ! function_exists( 'axismundi_act_is_publicly_renderable' ) || ! axismundi_act_is_publicly_renderable( $activity )
		|| ! function_exists( 'axismundi_op_remote_object_is_publicly_listable' ) || ! axismundi_op_remote_object_is_publicly_listable( $stored )
		|| 'Page' !== (string) ( $stored['object_type'] ?? '' ) || ! empty( $stored['in_reply_to_uri'] ) ) {
		return new WP_Error( 'ax_forum_remote_page_ineligible', __( 'The remote Object is not an eligible public top-level Page.', 'axismundi-forum' ) );
	}
	$author = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $activity->get_actor_uri() ) : null;
	if ( ! $author instanceof Axismundi_Actor || $author->is_local() || ! hash_equals( $author->get_uri(), (string) ( $stored['attributed_to_uri'] ?? '' ) ) ) {
		return new WP_Error( 'ax_forum_remote_page_author', __( 'The remote Page author does not match its Create Actor.', 'axismundi-forum' ) );
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
		$forum_post_id = axismundi_forum_get_forum_for_group( $group->get_identity_id() );
		if ( $forum_post_id <= 0 || ! axismundi_forum_remote_page_addresses_group( $payload, $activity, $group ) || ! axismundi_forum_remote_actor_can_post( $forum_post_id, $author ) ) {
			continue;
		}
		$existing = axismundi_forum_get_remote_entry( $forum_post_id, $object_uri );
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
				'forum_post_id'                => $forum_post_id,
				'group_identity_id'             => $group->get_identity_id(),
				'object_uri'                    => $object_uri,
				'object_uri_hash'               => hash( 'sha256', $object_uri ),
				'entry_type'                    => 'topic',
				'submission_actor_identity_id' => $author->get_identity_id(),
				'admission_state'               => 'visible',
				'moderation_state'              => 'visible',
				'accepted_activity_uri'          => $activity->get_uri(),
				'created_at'                    => $now,
				'updated_at'                    => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- membership-authorized contextual projection; source Object remains remote-cache owned.
		return false === $inserted ? new WP_Error( 'ax_forum_remote_page_write', __( 'The remote Page could not be admitted to this Forum.', 'axismundi-forum' ) ) : true;
	}
	return new WP_Error( 'ax_forum_remote_page_target', __( 'The remote Page does not target an eligible local Forum Group.', 'axismundi-forum' ) );
}

/** Observe verified remote Objects after Object Projections has stored the source. */
function axismundi_forum_observe_remote_page( array $stored, Axismundi_Activity $activity ) : void {
	axismundi_forum_admit_remote_page( $stored, $activity );
}
add_action( 'axismundi_op_remote_object_observed', 'axismundi_forum_observe_remote_page', 20, 2 );
