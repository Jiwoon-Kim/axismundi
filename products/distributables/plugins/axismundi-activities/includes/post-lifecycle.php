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

	$generation = $lifecycle instanceof Axismundi_Activity ? $lifecycle->get_uri() : 'initial';
	$event_key  = 'wp-post-create:' . $object_uri . ':after:' . $generation;
	$activity   = axismundi_act_record_source_activity(
		array(
			'type'   => 'Create',
			'actor'  => $actor_uri,
			'object' => $object_uri,
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
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

/** Consume the Object Projections publish candidate without blocking post save. */
function axismundi_act_on_object_publish_candidate( WP_Post $post, string $object_uri, string $actor_uri ) : void {
	axismundi_act_record_post_create( $post, $object_uri, $actor_uri );
}
add_action( 'axismundi_op_object_publish_candidate', 'axismundi_act_on_object_publish_candidate', 10, 3 );
