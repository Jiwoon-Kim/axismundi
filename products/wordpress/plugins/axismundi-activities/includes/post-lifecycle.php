<?php
/**
 * Core Post publish candidates → local Create ledger entries.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** Record an initial or post-Delete Create for one projected Core Post. */
function axismundi_act_record_post_create( WP_Post $post, string $object_uri, string $actor_uri ) {
	if ( ! function_exists( 'axismundi_op_post_article_visible' )
		|| ! function_exists( 'axismundi_op_post_object_uri' )
		|| ! function_exists( 'axismundi_op_post_actor_uri' )
		|| ! axismundi_op_post_article_visible( $post )
		|| ! hash_equals( axismundi_op_post_object_uri( $post ), $object_uri )
		|| ! hash_equals( axismundi_op_post_actor_uri( $post ), $actor_uri )
	) {
		return new WP_Error( 'ax_act_post_projection', __( 'The post is not a matching public object projection.', 'axismundi-activities' ) );
	}
	$lifecycle = axismundi_act_get_object_lifecycle( $object_uri );
	if ( $lifecycle instanceof Axismundi_Activity && 'Delete' !== $lifecycle->get_type() ) {
		return $lifecycle;
	}
	$audience = function_exists( 'axismundi_op_post_article_audience' ) ? axismundi_op_post_article_audience( $post ) : null;
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

/** Record an Article Delete from its stable local identity and last ledger audience. */
function axismundi_act_record_post_delete( WP_Post $post ) {
	if ( ! function_exists( 'axismundi_op_post_article_supports' )
		|| ! function_exists( 'axismundi_op_post_lifecycle_owner' )
		|| ! function_exists( 'axismundi_op_post_object_uri' )
		|| ! function_exists( 'axismundi_op_post_actor_uri' )
		|| ! function_exists( 'axismundi_act_record_object_delete' )
		|| ! axismundi_op_post_article_supports( $post )
		|| 'axismundi' !== axismundi_op_post_lifecycle_owner( $post )
	) {
		return null;
	}

	$object_uri = axismundi_op_post_object_uri( $post );
	$actor_uri  = axismundi_op_post_actor_uri( $post );
	if ( '' === $object_uri || '' === $actor_uri ) {
		return new WP_Error( 'ax_act_post_projection', __( 'The post no longer has a matching local object projection.', 'axismundi-activities' ) );
	}

	return axismundi_act_record_object_delete( $object_uri, $actor_uri );
}

/** Surface a failed Article Delete without silently erasing its local source. */
function axismundi_act_post_delete_failed( WP_Error $error, WP_Post $post ) : void {
	/** @param WP_Error $error @param WP_Post $post Failed Article lifecycle Delete. */
	do_action( 'axismundi_act_post_delete_failed', $error, $post );
}

/** Consume the Object Projections publish candidate without blocking post save. */
function axismundi_act_on_object_publish_candidate( WP_Post $post, string $object_uri, string $actor_uri ) : void {
	axismundi_act_record_post_create( $post, $object_uri, $actor_uri );
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
