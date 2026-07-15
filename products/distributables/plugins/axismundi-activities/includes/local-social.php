<?php
/**
 * Local-only Follow workflows. No HTTP, signatures, or delivery.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** The current user's activated public Person Actor, if available. */
function axismundi_act_current_local_actor() : ?Axismundi_Actor {
	$user_id = get_current_user_id();
	if ( $user_id <= 0 || ! current_user_can( 'edit_posts' ) ) {
		return null;
	}
	$actor   = $user_id > 0 ? axismundi_actors_get_for_user( $user_id ) : null;
	return $actor instanceof Axismundi_Actor
		&& $actor->is_local()
		&& 'Person' === $actor->get_type()
		&& 'public' === $actor->get_status()
		&& $actor->is_handle_locked()
		? $actor
		: null;
}

/** Whether a local target requires explicit approval. NULL uses the auto-accept default. */
function axismundi_act_local_follow_requires_approval( Axismundi_Actor $target ) : bool {
	$declared = $target->get_policy_flag( 'manually_approves_followers' );
	$required = null === $declared
		? ! (bool) get_option( 'axismundi_activities_auto_accept_local_follows', true )
		: $declared;
	/** @param bool $required Whether this local target requires a request decision. */
	return (bool) apply_filters( 'axismundi_act_local_follow_requires_approval', $required, $target );
}

/** Validate one local Person-to-Person social edge. */
function axismundi_act_validate_local_people( Axismundi_Actor $subject, Axismundi_Actor $object ) {
	if ( ! $subject->is_local() || ! $object->is_local() || 'Person' !== $subject->get_type() || 'Person' !== $object->get_type() ) {
		return new WP_Error( 'ax_act_local_only', __( 'Local social actions require two local Person actors.', 'axismundi-activities' ) );
	}
	if ( $subject->get_uri() === $object->get_uri() ) {
		return new WP_Error( 'ax_act_self_follow', __( 'An Actor cannot follow itself.', 'axismundi-activities' ) );
	}
	if ( 'public' !== $subject->get_status() || 'public' !== $object->get_status() || ! $subject->is_handle_locked() || ! $object->is_handle_locked() ) {
		return new WP_Error( 'ax_act_actor_unavailable', __( 'Both Actor profiles must be activated and public.', 'axismundi-activities' ) );
	}
	return true;
}

/** Validate a local Person acting on one cached remote Actor. */
function axismundi_act_validate_remote_follow( Axismundi_Actor $subject, Axismundi_Actor $object ) {
	if ( ! $subject->is_local() || 'Person' !== $subject->get_type() || 'public' !== $subject->get_status() || ! $subject->is_handle_locked() ) {
		return new WP_Error( 'ax_act_actor_unavailable', __( 'An activated public local Person actor is required.', 'axismundi-activities' ) );
	}
	if ( $object->is_local() || 'tombstone' === $object->get_status() ) {
		return new WP_Error( 'ax_act_remote_target', __( 'The remote Actor is unavailable.', 'axismundi-activities' ) );
	}
	return true;
}

/** Start an outbound Follow for one cached remote Actor. */
function axismundi_act_follow_remote_actor( Axismundi_Actor $subject, Axismundi_Actor $object ) {
	$valid = axismundi_act_validate_remote_follow( $subject, $object );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$existing = axismundi_act_get_relation( 'follow', $subject->get_uri(), $object->get_uri() );
	if ( is_array( $existing ) && in_array( (string) $existing['state'], array( 'pending', 'accepted', 'legacy_pending' ), true ) ) {
		return $existing;
	}
	$follow = axismundi_act_record_activity(
		array(
			'type'   => 'Follow',
			'actor'  => $subject->get_uri(),
			'object' => $object->get_uri(),
			'to'     => array( $object->get_uri() ),
		),
		'outbound'
	);
	return is_wp_error( $follow ) ? $follow : axismundi_act_get_relation( 'follow', $subject->get_uri(), $object->get_uri() );
}

/** Undo an Activity-backed outbound Follow without fabricating legacy history. */
function axismundi_act_unfollow_remote_actor( Axismundi_Actor $subject, Axismundi_Actor $object ) {
	$valid = axismundi_act_validate_remote_follow( $subject, $object );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$relation = axismundi_act_get_relation( 'follow', $subject->get_uri(), $object->get_uri() );
	if ( ! is_array( $relation ) || ! in_array( (string) $relation['state'], array( 'pending', 'accepted' ), true ) ) {
		return new WP_Error( 'ax_act_follow_missing', __( 'There is no active Follow to undo.', 'axismundi-activities' ) );
	}
	$follow_uri = (string) ( $relation['initiating_activity_uri'] ?? '' );
	if ( 'activity' !== (string) ( $relation['evidence_type'] ?? 'activity' ) || '' === $follow_uri ) {
		return new WP_Error( 'ax_act_follow_legacy', __( 'An imported Follow cannot be undone without its original Activity.', 'axismundi-activities' ) );
	}
	$undo = axismundi_act_record_activity(
		array(
			'type'   => 'Undo',
			'actor'  => $subject->get_uri(),
			'object' => $follow_uri,
			'to'     => array( $object->get_uri() ),
		),
		'outbound'
	);
	return is_wp_error( $undo ) ? $undo : axismundi_act_get_relation( 'follow', $subject->get_uri(), $object->get_uri() );
}

/** Follow either a local Person or a cached remote Actor through the correct state machine. */
function axismundi_act_follow_actor( Axismundi_Actor $subject, Axismundi_Actor $object ) {
	return $object->is_local()
		? axismundi_act_follow_local_actor( $subject, $object )
		: axismundi_act_follow_remote_actor( $subject, $object );
}

/** Unfollow either a local Person or a cached remote Actor. */
function axismundi_act_unfollow_actor( Axismundi_Actor $subject, Axismundi_Actor $object ) {
	return $object->is_local()
		? axismundi_act_unfollow_local_actor( $subject, $object )
		: axismundi_act_unfollow_remote_actor( $subject, $object );
}

/** Start a local Follow and auto-Accept unless the target requires approval. */
function axismundi_act_follow_local_actor( Axismundi_Actor $subject, Axismundi_Actor $object ) {
	$valid = axismundi_act_validate_local_people( $subject, $object );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$existing = axismundi_act_get_relation( 'follow', $subject->get_uri(), $object->get_uri() );
	if ( is_array( $existing ) && in_array( (string) $existing['state'], array( 'pending', 'accepted' ), true ) ) {
		return $existing;
	}
	$follow = axismundi_act_record_activity(
		array( 'type' => 'Follow', 'actor' => $subject->get_uri(), 'object' => $object->get_uri() ),
		'local'
	);
	if ( is_wp_error( $follow ) ) {
		return $follow;
	}
	if ( ! axismundi_act_local_follow_requires_approval( $object ) ) {
		$accept = axismundi_act_record_activity(
			array( 'type' => 'Accept', 'actor' => $object->get_uri(), 'object' => $follow->get_uri() ),
			'local'
		);
		if ( is_wp_error( $accept ) ) {
			return $accept;
		}
	}
	return axismundi_act_get_relation( 'follow', $subject->get_uri(), $object->get_uri() );
}

/** Undo a pending or accepted local Follow. */
function axismundi_act_unfollow_local_actor( Axismundi_Actor $subject, Axismundi_Actor $object ) {
	$valid = axismundi_act_validate_local_people( $subject, $object );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$relation = axismundi_act_get_relation( 'follow', $subject->get_uri(), $object->get_uri() );
	if ( ! is_array( $relation ) || ! in_array( (string) $relation['state'], array( 'pending', 'accepted' ), true ) ) {
		return new WP_Error( 'ax_act_follow_missing', __( 'There is no active Follow to undo.', 'axismundi-activities' ) );
	}
	$undo = axismundi_act_record_activity(
		array( 'type' => 'Undo', 'actor' => $subject->get_uri(), 'object' => (string) $relation['initiating_activity_uri'] ),
		'local'
	);
	return is_wp_error( $undo ) ? $undo : axismundi_act_get_relation( 'follow', $subject->get_uri(), $object->get_uri() );
}

/** Accept or reject a pending Follow as its local target Actor. */
function axismundi_act_respond_to_local_follow( Axismundi_Actor $target, string $follow_activity_uri, string $decision ) {
	if ( ! in_array( $decision, array( 'accept', 'reject' ), true ) ) {
		return new WP_Error( 'ax_act_follow_decision', __( 'The Follow decision is invalid.', 'axismundi-activities' ) );
	}
	$follow = axismundi_act_get( $follow_activity_uri );
	if ( ! $follow instanceof Axismundi_Activity || 'Follow' !== $follow->get_type() || $target->get_uri() !== $follow->get_object_uri() ) {
		return new WP_Error( 'ax_act_follow_request', __( 'That Follow request does not belong to this Actor.', 'axismundi-activities' ) );
	}
	$relation = axismundi_act_get_relation( 'follow', $follow->get_actor_uri(), $target->get_uri() );
	if ( ! is_array( $relation ) || 'pending' !== (string) $relation['state'] || $follow_activity_uri !== (string) $relation['initiating_activity_uri'] ) {
		return new WP_Error( 'ax_act_follow_request_state', __( 'That Follow request is no longer pending.', 'axismundi-activities' ) );
	}
	$follower = axismundi_actors_get_by_uri( $follow->get_actor_uri() );
	$remote   = $follower instanceof Axismundi_Actor && ! $follower->is_local();
	$payload  = array( 'type' => 'accept' === $decision ? 'Accept' : 'Reject', 'actor' => $target->get_uri(), 'object' => $follow_activity_uri );
	if ( $remote ) {
		$payload['to'] = array( $follower->get_uri() );
	}
	$activity = axismundi_act_record_activity( $payload, $remote ? 'outbound' : 'local' );
	return is_wp_error( $activity ) ? $activity : axismundi_act_get_relation( 'follow', $follow->get_actor_uri(), $target->get_uri() );
}
