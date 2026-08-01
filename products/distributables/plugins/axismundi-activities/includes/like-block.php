<?php
/**
 * Like REST mutation and Interactivity API block.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** Resolve one supported projected/cached object and its attributed Actor. */
function axismundi_act_resolve_like_target( string $object_uri ) {
	$uri = axismundi_act_uri( $object_uri );
	if ( '' === $uri ) {
		return new WP_Error( 'ax_act_like_target', __( 'The object URI is invalid.', 'axismundi-activities' ) );
	}
	if ( function_exists( 'axismundi_op_remote_object_get' ) ) {
		$remote = axismundi_op_remote_object_get( $uri, false );
		if ( is_array( $remote ) ) {
			if ( 'active' !== (string) $remote['object_status'] || empty( $remote['attributed_to_uri'] ) ) {
				return new WP_Error( 'ax_act_like_target_unavailable', __( 'The remote object cannot currently receive a Like.', 'axismundi-activities' ), array( 'status' => 409 ) );
			}
			return array( 'object_uri' => $uri, 'recipient_uri' => (string) $remote['attributed_to_uri'], 'source' => $remote );
		}
	}

	$source = null;
	$parts  = wp_parse_url( $uri );
	$query  = array();
	if ( is_array( $parts ) && isset( $parts['query'] ) ) {
		parse_str( (string) $parts['query'], $query );
	}
	$post_id = isset( $query['p'] ) ? absint( $query['p'] ) : ( isset( $query['attachment_id'] ) ? absint( $query['attachment_id'] ) : url_to_postid( $uri ) );
	if ( $post_id > 0 ) {
		$source = get_post( $post_id );
	}
	if ( $source instanceof WP_Post
		&& function_exists( 'axismundi_op_post_article_supports' )
		&& function_exists( 'axismundi_op_post_article_visible' )
		&& function_exists( 'axismundi_op_post_object_uri' )
		&& function_exists( 'axismundi_op_post_actor_uri' )
		&& axismundi_op_post_article_supports( $source )
		&& axismundi_op_post_article_visible( $source )
		&& hash_equals( axismundi_op_post_object_uri( $source ), $uri )
	) {
		return array( 'object_uri' => $uri, 'recipient_uri' => axismundi_op_post_actor_uri( $source ), 'source' => $source );
	}
	if ( $source instanceof WP_Post && function_exists( 'axismundi_op_transform_object' ) ) {
		$object = axismundi_op_transform_object( $source );
		if ( is_array( $object ) && isset( $object['id'], $object['attributedTo'] ) && hash_equals( (string) $object['id'], $uri ) ) {
			return array( 'object_uri' => $uri, 'recipient_uri' => axismundi_act_member_uri( $object['attributedTo'] ), 'source' => $source );
		}
	}

	/** @param array<string,mixed>|WP_Error $target Resolved target. @param string $uri Canonical object URI. */
	return apply_filters( 'axismundi_act_resolve_like_target', new WP_Error( 'ax_act_like_target_missing', __( 'The object is not available in a local projection or remote cache.', 'axismundi-activities' ), array( 'status' => 404 ) ), $uri );
}

/** REST permission gate: an activated public Actor is required. */
function axismundi_act_like_rest_permission() : bool {
	return axismundi_act_current_local_actor() instanceof Axismundi_Actor;
}

/** Register the Like mutation endpoint. */
function axismundi_act_register_like_rest_route() : void {
	register_rest_route(
		'axismundi/v1',
		'/likes',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'axismundi_act_rest_like_object',
				'permission_callback' => 'axismundi_act_like_rest_permission',
				'args'                => array( 'object_uri' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ) ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'axismundi_act_rest_unlike_object',
				'permission_callback' => 'axismundi_act_like_rest_permission',
				'args'                => array( 'object_uri' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ) ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_act_register_like_rest_route' );

/** Prevent shared caches from storing a logged-in visitor's Like state and REST nonce. */
function axismundi_act_no_cache_like_state() : void {
	if ( ! is_user_logged_in() ) {
		return;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	if ( ! headers_sent() ) {
		nocache_headers();
	}
}


/** Build the authoritative mutation response. */
function axismundi_act_like_rest_response( Axismundi_Actor $actor, string $object_uri, Axismundi_Activity $activity ) : WP_REST_Response {
	return new WP_REST_Response(
		array(
			'object_uri'   => $object_uri,
			'is_liked'     => axismundi_act_get_like_state( $actor->get_uri(), $object_uri ),
			'like_count'   => axismundi_act_get_like_count( $object_uri ),
			'activity_uri' => $activity->get_uri(),
		),
		200
	);
}

/** Handle Like. */
function axismundi_act_rest_like_object( WP_REST_Request $request ) {
	$actor  = axismundi_act_current_local_actor();
	$target = axismundi_act_resolve_like_target( (string) $request['object_uri'] );
	if ( ! $actor instanceof Axismundi_Actor || is_wp_error( $target ) ) {
		return is_wp_error( $target ) ? $target : new WP_Error( 'ax_act_like_actor', __( 'No active local Actor is available.', 'axismundi-activities' ), array( 'status' => 403 ) );
	}
	$activity = axismundi_act_like_object( $actor, (string) $target['object_uri'], (string) $target['recipient_uri'] );
	return is_wp_error( $activity ) ? $activity : axismundi_act_like_rest_response( $actor, (string) $target['object_uri'], $activity );
}

/** Handle Undo(Like). */
function axismundi_act_rest_unlike_object( WP_REST_Request $request ) {
	$actor  = axismundi_act_current_local_actor();
	$target = axismundi_act_resolve_like_target( (string) $request['object_uri'] );
	if ( ! $actor instanceof Axismundi_Actor || is_wp_error( $target ) ) {
		return is_wp_error( $target ) ? $target : new WP_Error( 'ax_act_like_actor', __( 'No active local Actor is available.', 'axismundi-activities' ), array( 'status' => 403 ) );
	}
	$activity = axismundi_act_unlike_object( $actor, (string) $target['object_uri'] );
	return is_wp_error( $activity ) ? $activity : axismundi_act_like_rest_response( $actor, (string) $target['object_uri'], $activity );
}

/** Resolve the object URI represented by one Like block instance. */
function axismundi_act_like_block_object_uri( array $attributes, WP_Block $block ) : string {
	$uri = isset( $attributes['objectUri'] ) ? axismundi_act_uri( (string) $attributes['objectUri'] ) : '';
	// Object cards rendered inside an archive or Actor timeline inherit the outer
	// template's postId. The active view model identifies the card itself.
	if ( '' === $uri && function_exists( 'axismundi_op_current_object_view_model' ) ) {
		$model = axismundi_op_current_object_view_model();
		$uri   = is_array( $model ) ? axismundi_act_uri( (string) ( $model['object_uri'] ?? '' ) ) : '';
	}
	if ( '' === $uri && ! empty( $block->context['postId'] ) && function_exists( 'axismundi_op_transform_object' ) ) {
		$post = get_post( (int) $block->context['postId'] );
		if ( $post instanceof WP_Post && function_exists( 'axismundi_op_post_article_supports' ) && function_exists( 'axismundi_op_post_article_visible' ) && function_exists( 'axismundi_op_post_object_uri' ) && axismundi_op_post_article_supports( $post ) && axismundi_op_post_article_visible( $post ) ) {
			$uri = axismundi_act_uri( axismundi_op_post_object_uri( $post ) );
		} else {
			$object = $post instanceof WP_Post ? axismundi_op_transform_object( $post ) : null;
			$uri    = is_array( $object ) && isset( $object['id'] ) ? axismundi_act_uri( (string) $object['id'] ) : '';
		}
	}
	/** @param string $uri Object URI or empty. @param array<string,mixed> $attributes Block attributes. @param WP_Block $block Block instance. */
	return (string) apply_filters( 'axismundi_act_like_button_object_uri', $uri, $attributes, $block );
}


/**
 * Describe a Like for the unified interaction block.
 *
 * Only what makes this a Like: its icon, its words, its count, whether the reader has already
 * done it, and how its clicks are wired. The button around all of that is not this function's
 * business, which is the entire reason the six buttons became one.
 *
 * @param array    $attributes Block attributes.
 * @param WP_Block $block      Block instance.
 * @return array<string,mixed>|null
 */
function axismundi_act_describe_like_interaction( array $attributes, WP_Block $block ) : ?array {
	$object_uri = axismundi_act_like_block_object_uri( $attributes, $block );
	if ( '' === $object_uri ) {
		return null;
	}
	axismundi_act_no_cache_like_state();
	$actor    = axismundi_act_current_local_actor();
	$can_like = $actor instanceof Axismundi_Actor && ! is_wp_error( axismundi_act_resolve_like_target( $object_uri ) );
	$is_liked = $actor instanceof Axismundi_Actor ? axismundi_act_get_like_state( $actor->get_uri(), $object_uri ) : false;
	$endpoint = rest_url( 'axismundi/v1/likes' );
	$context  = array(
		'objectUri'     => $object_uri,
		'likes'         => axismundi_act_get_like_count( $object_uri ),
		'isLiked'       => $is_liked,
		'isPending'     => false,
		'canLike'       => $can_like,
		// On the context rather than derived in the store, so the server and the runtime agree —
		// a `state` getter defined only in JavaScript resolves to nothing during server-side
		// directive processing, and the attribute it controls is stripped instead of set.
		'isDisabled'    => ! $can_like,
		'endpoint'      => $endpoint,
		'nonce'         => $can_like ? wp_create_nonce( 'wp_rest' ) : '',
		'error'         => '',
		'errorFallback' => __( 'The Like could not be saved.', 'axismundi-activities' ),
	);
	return array(
		'icon'       => 'favorite',
		'label'      => __( 'Like', 'axismundi-activities' ),
		'aria_label' => $can_like
			? __( 'Like', 'axismundi-activities' )
			: ( is_user_logged_in() ? __( 'Activate a public Actor profile to Like.', 'axismundi-activities' ) : __( 'Log in to Like.', 'axismundi-activities' ) ),
		'count'      => (int) $context['likes'],
		'count_bind' => 'context.likes',
		'selected'   => $is_liked,
		'toggle'     => true,
		'disabled'   => ! $can_like,
		'namespace'  => 'axismundi/like-button',
		'module'     => 'axismundi-interaction-like',
		'context'    => $context,
		'bindings'   => array(
			'data-wp-on--click'          => 'actions.toggleLike',
			'data-wp-bind--disabled'     => 'context.isDisabled',
			'data-wp-class--is-selected' => 'context.isLiked',
			'data-wp-bind--aria-pressed' => 'context.isLiked',
		),
		'delegated'  => array(
			'data-ax-action'     => 'like',
			'data-ax-object-uri' => $object_uri,
			'data-ax-endpoint'   => $endpoint,
			'data-ax-nonce'      => $can_like ? (string) $context['nonce'] : '',
		),
	);
}

/** Offer Like as an interaction type. */
function axismundi_act_register_like_interaction_type() : void {
	if ( function_exists( 'axismundi_act_register_interaction_type' ) ) {
		axismundi_act_register_interaction_type(
			'like',
			array(
				'describe' => 'axismundi_act_describe_like_interaction',
				'label'    => __( 'Like', 'axismundi-activities' ),
				'icon'     => 'favorite',
			)
		);
	}
}
add_action( 'axismundi_act_register_interaction_types', 'axismundi_act_register_like_interaction_type' );
