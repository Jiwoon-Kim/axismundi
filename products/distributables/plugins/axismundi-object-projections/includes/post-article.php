<?php
/**
 * Core Post → ActivityStreams Article projection.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether this transformer owns the supplied source.
 *
 * @param mixed $source Candidate source.
 * @return bool
 */
function axismundi_op_post_article_supports( $source ) : bool {
	return $source instanceof WP_Post
		&& 'post' === $source->post_type
		&& ! wp_is_post_revision( $source )
		&& ! wp_is_post_autosave( $source );
}

/**
 * Resolve a public local user Actor, with a deliberately public site Actor fallback.
 *
 * Actors remains the identity owner; projections never create an Actor from a render
 * request. This shared resolver is used by Core Post and first-party domain adapters.
 *
 * @param int $user_id Local WordPress user id.
 * @return string
 */
function axismundi_op_local_author_actor_uri( int $user_id ) : string {
	$actor = null;
	if ( function_exists( 'axismundi_actors_get_for_user' ) ) {
		$actor = axismundi_actors_get_for_user( $user_id );
	}
	if ( ( ! $actor || ! function_exists( 'axismundi_actors_is_public_profile' ) || ! axismundi_actors_is_public_profile( $actor ) ) && function_exists( 'axismundi_actors_get_site_actor' ) ) {
		$actor = axismundi_actors_get_site_actor();
	}

	return $actor && function_exists( 'axismundi_actors_is_public_profile' ) && axismundi_actors_is_public_profile( $actor )
		? (string) $actor->get_uri()
		: '';
}

/**
 * Resolve the public Actor URI that owns a post.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function axismundi_op_post_actor_uri( WP_Post $post ) : string {
	$uri = axismundi_op_local_author_actor_uri( (int) $post->post_author );

	/**
	 * Filter the Actor URI attributed to one local post.
	 *
	 * Returning an empty string makes the post non-projectable. An adapter may use
	 * this seam when another Actor provider owns local identity.
	 *
	 * @since 0.0.2
	 * @param string  $uri  Actor URI or empty string.
	 * @param WP_Post $post Post.
	 */
	return (string) apply_filters( 'axismundi_op_post_actor_uri', $uri, $post );
}

/**
 * Stable standalone object URI for a core post.
 *
 * The official ActivityPub compatibility adapter must filter this value to preserve
 * that plugin's per-post legacy permalink-ID decision. The standalone router is
 * disabled while that plugin is active, so two negotiators never mint competing ids.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function axismundi_op_post_object_uri( WP_Post $post ) : string {
	$uri = add_query_arg( 'p', (int) $post->ID, home_url( '/' ) );
	/**
	 * Filter the stable ActivityStreams id of a local post.
	 *
	 * @since 0.0.2
	 * @param string  $uri  Default `/?p={ID}` URI.
	 * @param WP_Post $post Post.
	 */
	return (string) apply_filters( 'axismundi_op_post_object_uri', $uri, $post );
}

/**
 * Public projection gate for a core post.
 *
 * @param WP_Post $post Post.
 * @return bool
 */
function axismundi_op_post_article_visible( WP_Post $post ) : bool {
	return 'publish' === $post->post_status
		&& '' === (string) $post->post_password
		&& is_post_publicly_viewable( $post )
		&& '' !== axismundi_op_post_actor_uri( $post );
}

/**
 * Render post content through WordPress's normal content pipeline.
 *
 * @param WP_Post $post Post.
 * @return string
 */
function axismundi_op_post_article_content( WP_Post $post ) : string {
	/**
	 * Filter whether the normal `the_content` pipeline renders Article content.
	 *
	 * @since 0.0.2
	 * @param bool    $use_pipeline True by default.
	 * @param WP_Post $post         Post.
	 */
	$use_pipeline = (bool) apply_filters( 'axismundi_op_use_the_content', true, $post );
	return $use_pipeline
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content rendering pipeline.
		? (string) apply_filters( 'the_content', $post->post_content )
		: (string) $post->post_content;
}

/**
 * Transform a public core post into an Article.
 *
 * @param WP_Post $post Post.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_op_post_to_article( WP_Post $post ) {
	$id            = axismundi_op_post_object_uri( $post );
	$attributed_to = axismundi_op_post_actor_uri( $post );
	$url           = get_permalink( $post );
	if ( ! $url || '' === $attributed_to ) {
		return new WP_Error( 'ax_op_post_identity', __( 'The post has no public object or Actor URI.', 'axismundi-object-projections' ) );
	}

	$article = array(
		'id'           => $id,
		'type'         => 'Article',
		'attributedTo' => $attributed_to,
		'url'          => $url,
		'name'         => get_the_title( $post ),
		'content'      => axismundi_op_post_article_content( $post ),
		'mediaType'    => 'text/html',
		'published'    => get_post_time( DATE_W3C, true, $post ),
		'updated'      => get_post_modified_time( DATE_W3C, true, $post ),
	);

	if ( '' !== trim( (string) $post->post_excerpt ) ) {
		$article['summary'] = (string) $post->post_excerpt;
	}

	/**
	 * Filter the Core Post → Article projection before renderer validation.
	 *
	 * The callback must not add @context or change id away from the declared URI.
	 *
	 * @since 0.0.2
	 * @param array<string,mixed> $article Projection.
	 * @param WP_Post            $post    Source post.
	 */
	return apply_filters( 'axismundi_op_post_article', $article, $post );
}

/**
 * Register the built-in Core Post transformer.
 *
 * @return void
 */
function axismundi_op_register_post_article_transformer() : void {
	axismundi_op_register_object_transformer(
		'core-post-article',
		array(
			'supports'   => 'axismundi_op_post_article_supports',
			'object_uri' => 'axismundi_op_post_object_uri',
			'transform'  => 'axismundi_op_post_to_article',
			'visible'    => 'axismundi_op_post_article_visible',
			'priority'   => 10,
		)
	);
}
add_action( 'axismundi_op_register_transformers', 'axismundi_op_register_post_article_transformer' );
