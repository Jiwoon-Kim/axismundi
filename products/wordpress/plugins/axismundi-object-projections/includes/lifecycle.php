<?php
/**
 * Local object lifecycle event emission.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve which integration owns automatic post lifecycle Activities.
 *
 * The official ActivityPub plugin owns this surface whenever it is active unless
 * a compatibility adapter explicitly transfers ownership to Axismundi.
 */
function axismundi_op_post_lifecycle_owner( WP_Post $post ) : string {
	$default = defined( 'ACTIVITYPUB_PLUGIN_VERSION' ) ? 'official-activitypub' : 'axismundi';
	/**
	 * Filter the single owner of automatic post lifecycle Activities.
	 *
	 * A compatibility adapter may return `axismundi` only after it suppresses the
	 * official plugin's corresponding scheduler path.
	 *
	 * @since 0.0.8
	 * @param string  $owner Default lifecycle owner.
	 * @param WP_Post $post  Saved post.
	 */
	return sanitize_key( (string) apply_filters( 'axismundi_op_post_lifecycle_owner', $default, $post ) );
}

/**
 * Emit a public Core Post publish candidate after terms and post meta are stored.
 *
 * Object Projections owns source interpretation only. It performs no Activity
 * write and no network request; Activities consumes the post-commit candidate.
 *
 * @param int          $post_id    Post id.
 * @param WP_Post      $post       Saved post.
 * @param bool         $update     Whether this was an update.
 * @param WP_Post|null $post_before Previous post snapshot.
 */
function axismundi_op_emit_post_publish_candidate( int $post_id, WP_Post $post, bool $update, ?WP_Post $post_before, bool $rest_complete = false ) : void {
	unset( $post_id, $update );
	if ( ( defined( 'WP_IMPORTING' ) && WP_IMPORTING )
		|| ( wp_is_serving_rest_request() && ! $rest_complete )
		|| ! axismundi_op_post_article_supports( $post )
		|| ! axismundi_op_post_article_visible( $post )
		|| 'axismundi' !== axismundi_op_post_lifecycle_owner( $post )
	) {
		return;
	}

	$object_uri = axismundi_op_post_object_uri( $post );
	if ( '' === $object_uri ) {
		return;
	}
	/*
	 * Deliberately not gated on attribution. A first publication has none -- it is what the
	 * publication creates -- so requiring one here would mean no Object could ever earn its
	 * first Create. The value passed on is whatever is already recorded, which is empty until
	 * a consumer records something.
	 */
	$actor_uri = axismundi_op_post_actor_uri( $post );

	/**
	 * Fires after a public Core Post is committed and can be projected.
	 *
	 * The event is intentionally idempotent. Consumers must derive lifecycle state
	 * from their own ledger instead of assuming every callback is a first publish.
	 *
	 * NOTE: `$actor_uri` is the attribution already on record, which is empty for a post
	 * that has never been published. It is passed for the consumers written against this
	 * signature and is not an instruction: who a save publishes as is decided by the
	 * submitting client and verified by Axismundi Activities, not settled here. What
	 * remains inverted is the trigger -- this event is still a projection observing a
	 * save rather than an intent arriving from an editor.
	 *
	 * @since 0.0.8
	 * @param WP_Post      $post        Saved post.
	 * @param string       $object_uri  Stable projected object URI.
	 * @param string       $actor_uri   Public owning Actor URI.
	 * @param WP_Post|null $post_before Previous post snapshot.
	 */
	do_action( 'axismundi_op_object_publish_candidate', $post, $object_uri, $actor_uri, $post_before );
}
add_action( 'wp_after_insert_post', 'axismundi_op_emit_post_publish_candidate', 40, 4 );

/** Emit after REST-backed post metadata has been committed by the block editor. */
function axismundi_op_emit_rest_post_publish_candidate( WP_Post $post, WP_REST_Request $request, bool $creating ) : void {
	unset( $request );
	axismundi_op_emit_post_publish_candidate( $post->ID, $post, ! $creating, null, true );
}
add_action( 'rest_after_insert_post', 'axismundi_op_emit_rest_post_publish_candidate', 40, 3 );
