<?php
/**
 * The block and classic editors as a local submission adapter.
 *
 * These are not ActivityPub C2S HTTP clients and this file does not pretend otherwise. They
 * are the first adapter over `axismundi_act_submit_c2s()`: they decide *when* a publication is
 * intended and which identity to offer, and the command service decides everything else. A
 * later outbox `POST` endpoint is one more adapter over that same service.
 *
 * What remains inverted is the trigger. Publication is still started by Object Projections
 * observing a committed save rather than by a submitted command, so this adapter reads the
 * principal and the offered identity from the ambient request. `docs/C2S.md` §9 has the order
 * for retiring that.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Record an initial or post-Delete Create for one projected Core Post.
 *
 * The Object URI is checked against the projection, because the identity of the source is what
 * Object Projections owns. The Actor is not resolved here: it arrives already decided and
 * verified by the command service.
 */
function axismundi_act_record_post_create( WP_Post $post, string $object_uri, string $actor_uri ) {
	if ( ! function_exists( 'axismundi_op_post_article_visible' )
		|| ! function_exists( 'axismundi_op_post_object_uri' )
		|| ! axismundi_op_post_article_visible( $post )
		|| ! hash_equals( axismundi_op_post_object_uri( $post ), $object_uri )
	) {
		return new WP_Error( 'ax_act_post_projection', __( 'The post is not a matching public object projection.', 'axismundi-activities' ) );
	}
	if ( '' === $actor_uri ) {
		return new WP_Error( 'ax_act_post_actor', __( 'The post has no identity to publish under.', 'axismundi-activities' ) );
	}
	$lifecycle = axismundi_act_get_object_lifecycle( $object_uri );
	if ( $lifecycle instanceof Axismundi_Activity && 'Delete' !== $lifecycle->get_type() ) {
		return $lifecycle;
	}
	// The Actor is stated, because the attribution this would otherwise derive is the one
	// this Create is about to establish.
	$audience = function_exists( 'axismundi_op_post_article_audience' ) ? axismundi_op_post_article_audience( $post, null, $actor_uri ) : null;
	if ( ! is_array( $audience ) ) {
		return is_wp_error( $audience ) ? $audience : new WP_Error( 'ax_act_post_audience', __( 'The post audience is unavailable.', 'axismundi-activities' ) );
	}

	$generation = $lifecycle instanceof Axismundi_Activity ? $lifecycle->get_uri() : 'initial';
	$event_key  = 'wp-post-create:' . $object_uri . ':after:' . $generation;
	$activity   = axismundi_act_record_source_activity(
		array(
			'type'   => 'Create',
			'actor'  => $actor_uri,
			'object' => $object_uri,
			'to'     => $audience['to'],
			'cc'     => $audience['cc'],
		),
		'outbound',
		$event_key
	);

	if ( is_wp_error( $activity ) ) {
		/** @param WP_Error $activity @param WP_Post $post Failed local lifecycle write. */
		do_action( 'axismundi_act_post_create_failed', $activity, $post );
	}
	return $activity;
}

/** Whether this plugin, rather than the official one, owns this post's lifecycle. */
function axismundi_act_post_lifecycle_applies( WP_Post $post ) : bool {
	return function_exists( 'axismundi_op_post_article_supports' )
		&& function_exists( 'axismundi_op_post_lifecycle_owner' )
		&& axismundi_op_post_article_supports( $post )
		&& 'axismundi' === axismundi_op_post_lifecycle_owner( $post );
}

/**
 * Build the command one editor save submits.
 *
 * The version it states is the one it has just observed, so this adapter cannot itself go
 * stale: it submits inside the request that saved. The guard is for callers that prepare a
 * command ahead of time, which is every other adapter.
 *
 * @return array<string,mixed>
 */
function axismundi_act_post_command( WP_Post $post, string $operation ) : array {
	return array(
		'operation'        => $operation,
		'post_id'          => (int) $post->ID,
		'expected_version' => (string) $post->post_modified_gmt,
	);
}

/**
 * Submit one editor-driven operation through the command service.
 *
 * A withdrawal offers no identity. Authority to delete is the Object's attribution, and the
 * account performing the deletion is frequently not the Actor that published -- an editor
 * emptying the trash, or cron with no session at all.
 */
function axismundi_act_submit_post_operation( WP_Post $post, string $operation ) {
	$principal = axismundi_act_publish_principal( $post );
	$actor     = 'Delete' === $operation ? null : axismundi_act_publish_actor( $post, $principal );
	return axismundi_act_submit_c2s(
		$principal,
		$actor instanceof Axismundi_Actor ? $actor->get_identity_id() : 0,
		axismundi_act_post_command( $post, $operation ),
		array( 'surface' => 'editor' )
	);
}

/** Record an Article Delete from its stable local identity and last ledger audience. */
function axismundi_act_record_post_delete( WP_Post $post ) {
	if ( ! axismundi_act_post_lifecycle_applies( $post ) ) {
		return null;
	}
	return axismundi_act_submit_post_operation( $post, 'Delete' );
}

/** Surface a failed Article Delete without silently erasing its local source. */
function axismundi_act_post_delete_failed( WP_Error $error, WP_Post $post ) : void {
	/** @param WP_Error $error @param WP_Post $post Failed Article lifecycle Delete. */
	do_action( 'axismundi_act_post_delete_failed', $error, $post );
}

/**
 * Submit a publication when Object Projections reports a committed, publishable save.
 *
 * The event still carries an Actor URI for the plugins written against it. This adapter
 * ignores it: reading the publishing identity off the projection is the inversion the command
 * service exists to undo.
 */
function axismundi_act_on_object_publish_candidate( WP_Post $post, string $object_uri, string $actor_uri ) : void {
	unset( $object_uri, $actor_uri );
	/*
	 * A publication with nobody making it is not federated yet.
	 *
	 * A scheduled post reaches this with no session, so there is no chosen identity to submit
	 * and the adapter would fall back to the author's own Person. That is a guess, and the
	 * wrong one for anything scheduled as an Organization: the choice was made when the post
	 * was scheduled and nothing durable recorded it. Until a scheduled submission can freeze
	 * its identity -- `docs/C2S.md` §8 -- the honest outcome is that the post publishes in
	 * WordPress and is readable as an unattributed Object, and no Create is made in a name
	 * nobody selected.
	 *
	 * Withdrawals are deliberately not gated this way. A Delete has one valid Actor, already
	 * on record, so nothing is being guessed.
	 */
	if ( get_current_user_id() <= 0 ) {
		/** @param WP_Post $post A publication with no authenticated principal to attribute it to. */
		do_action( 'axismundi_act_unattributed_publication', $post );
		return;
	}
	axismundi_act_submit_post_operation( $post, 'Create' );
}
add_action( 'axismundi_op_object_publish_candidate', 'axismundi_act_on_object_publish_candidate', 10, 3 );

/** Withdraw an Article when it leaves the published state. */
function axismundi_act_transition_post_lifecycle( string $new_status, string $old_status, WP_Post $post ) : void {
	if ( 'post' !== $post->post_type || 'publish' !== $old_status || 'publish' === $new_status ) {
		return;
	}
	$result = axismundi_act_record_post_delete( $post );
	if ( is_wp_error( $result ) ) {
		axismundi_act_post_delete_failed( $result, $post );
	}
}
add_action( 'transition_post_status', 'axismundi_act_transition_post_lifecycle', 40, 3 );

/**
 * Refuse permanent deletion until a previously federated Article has a durable Delete.
 *
 * Status transitions cover trash and drafts. This catches direct permanent deletion,
 * where WordPress removes the source without a publish-to-nonpublish transition.
 *
 * @param WP_Post|false|null $delete Short-circuit value.
 * @return WP_Post|false|null
 */
function axismundi_act_pre_delete_post_lifecycle( $delete, WP_Post $post ) {
	if ( false === $delete || 'post' !== $post->post_type ) {
		return $delete;
	}
	$result = axismundi_act_record_post_delete( $post );
	if ( is_wp_error( $result ) ) {
		axismundi_act_post_delete_failed( $result, $post );
		return false;
	}
	return $delete;
}
add_filter( 'pre_delete_post', 'axismundi_act_pre_delete_post_lifecycle', 20, 2 );
