<?php
/**
 * Object Replies block: the reply thread of the Object a page is about.
 *
 * The replies collection has existed for some time and is what a peer reads over
 * ActivityStreams, but nothing rendered it as HTML. A Lemmy comment could arrive, be cached,
 * be indexed into the thread graph, and be returned by `/objects/replies` — and still be
 * invisible on the page it belongs to, because the only thing missing was a block.
 *
 * This renders the same collection the API serves, through the same object card renderer used
 * by feeds and Forum Topic lists. There is deliberately no second visibility rule here: a
 * reply appears if the collection would name it publicly, so the page and the API can never
 * disagree about who is in a conversation.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Object URI this request is about, for blocks that describe the current object.
 *
 * The cached-remote-object route binds a view model, but an ordinary singular page for a
 * local post does not — a Topic or Note single is rendered by Core's post blocks. So the
 * queried post is resolved through the transformer registry, which is the same seam that
 * decides an object's canonical URI everywhere else. Asking the registry rather than
 * hard-coding a post type keeps this working for every product that registers one.
 *
 * @return string Canonical object URI, or '' when this request is about no object.
 */
function axismundi_op_request_object_uri() : string {
	$model = axismundi_op_current_object_view_model();
	if ( is_array( $model ) && '' !== (string) ( $model['id'] ?? '' ) ) {
		return (string) $model['id'];
	}
	if ( ! is_singular() || ! function_exists( 'axismundi_op_resolve_object_transformer' ) ) {
		return '';
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post ) {
		return '';
	}
	$transformer = axismundi_op_resolve_object_transformer( $post );
	if ( null === $transformer || empty( $transformer['uri'] ) ) {
		return '';
	}
	try {
		return (string) call_user_func( $transformer['uri'], $post );
	} catch ( \Throwable $error ) {
		// An object that cannot name itself has no replies collection to show.
		return '';
	}
}

/**
 * Render the public reply thread for the current Object.
 *
 * Only direct replies. Nesting is a separate contract — each reply advertises its own
 * replies collection — and drawing an unbounded tree here would mean an unbounded number of
 * resolves on a public page, which is the shape a hostile thread is built to exploit.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function axismundi_op_render_object_replies_block( array $attributes = array() ) : string {
	$object_uri = axismundi_op_request_object_uri();
	if ( '' === $object_uri || ! function_exists( 'axismundi_op_get_public_reply_collection_page' ) ) {
		return '';
	}
	$limit = isset( $attributes['perPage'] ) ? max( 1, min( 50, (int) $attributes['perPage'] ) ) : 20;
	$page  = axismundi_op_get_public_reply_collection_page( $object_uri, 1, $limit );
	$cards = array();
	foreach ( (array) ( $page['uris'] ?? array() ) as $reply_uri ) {
		$card = axismundi_op_render_object_by_uri( (string) $reply_uri, array( 'headingTag' => 'h3', 'interactions' => false ) );
		if ( '' !== $card ) {
			$cards[] = '<li class="axismundi-object-replies__item">' . $card . '</li>';
		}
	}
	if ( empty( $cards ) ) {
		return '';
	}
	$heading = '<h2 class="axismundi-object-replies__heading">' . esc_html__( 'Replies', 'axismundi-object-projections' ) . '</h2>';
	$more    = ! empty( $page['has_next'] ) || ! empty( $page['truncated'] )
		// The collection is bounded, so say the thread continues rather than implying this is all of it.
		? '<p class="axismundi-object-replies__more">' . esc_html__( 'This thread continues.', 'axismundi-object-projections' ) . '</p>'
		: '';
	/*
	 * get_block_wrapper_attributes() reads the block currently on the render stack and warns
	 * when there is none. This renderer is also callable directly, so the plain class attribute
	 * stands in rather than asking Core for supports no block declared.
	 */
	$wrapper = null === WP_Block_Supports::$block_to_render
		? 'class="axismundi-object-replies"'
		: get_block_wrapper_attributes( array( 'class' => 'axismundi-object-replies' ) );
	return '<section ' . $wrapper . '>'
		. $heading
		. '<ol class="axismundi-object-replies__items">' . implode( '', $cards ) . '</ol>'
		. $more
		. '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Cards are produced and escaped by the shared object renderer.
}

/** Register the Object Replies block from its server-owned metadata. */
function axismundi_op_register_object_replies_block() : void {
	register_block_type( dirname( __DIR__ ) . '/blocks/object-replies' );
}
add_action( 'init', 'axismundi_op_register_object_replies_block', 20 );
