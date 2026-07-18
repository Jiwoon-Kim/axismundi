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
 * @param WP_Post     $post    Post.
 * @param string|null $content Optional fragment; defaults to the full post content.
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
 * Resolve Mention descriptors from the Actors authority.
 *
 * Strict authoring rejects an unresolved recipient. Tolerant live projection
 * keeps the Article available and omits only the stale display descriptor.
 */
function axismundi_op_post_article_mention_tags( WP_Post $post, bool $strict = true ) {
	$tags = array();
	foreach ( axismundi_op_post_mentions( $post ) as $uri ) {
		$actor = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $uri ) : null;
		$name  = $actor instanceof Axismundi_Actor && function_exists( 'axismundi_actors_federated_mention_name' )
			? axismundi_actors_federated_mention_name( $actor )
			: '';
		if ( ! $actor instanceof Axismundi_Actor || 'public' !== $actor->get_status() || '' === $name ) {
			if ( $strict ) {
				return new WP_Error( 'ax_op_post_mention_actor', __( 'A mentioned Actor could not be resolved safely.', 'axismundi-object-projections' ) );
			}
			continue;
		}
		$tags[] = array( 'type' => 'Mention', 'name' => $name, 'href' => $uri );
	}
	return $tags;
}

/**
 * Resolve the authored Article audience through the Activities policy owner.
 *
 * Omitting `$mention_uris` is the strict authoring boundary. Live projections
 * pass their already-sanitized stored URI snapshot so stale Actor cache state
 * cannot make an existing Article unavailable.
 */
function axismundi_op_post_article_audience( WP_Post $post, ?array $mention_uris = null ) {
	$actor_uri = axismundi_op_post_actor_uri( $post );
	$actor     = '' !== $actor_uri && function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $actor_uri ) : null;
	if ( ! $actor instanceof Axismundi_Actor || ! function_exists( 'axismundi_act_resolve_audience' ) ) {
		return new WP_Error( 'ax_op_post_audience', __( 'The post audience cannot be resolved.', 'axismundi-object-projections' ) );
	}
	if ( null === $mention_uris ) {
		$mention_tags = axismundi_op_post_article_mention_tags( $post, true );
		if ( is_wp_error( $mention_tags ) ) {
			return $mention_tags;
		}
		$mention_uris = array_column( $mention_tags, 'href' );
	}
	return axismundi_act_resolve_audience( $actor, axismundi_op_post_visibility( $post ), $mention_uris );
}

/** Whether anonymous ActivityStreams negotiation may disclose this Article. */
function axismundi_op_post_article_publicly_readable( WP_Post $post ) : bool {
	$audience = axismundi_op_post_article_visible( $post ) ? axismundi_op_post_article_audience( $post, axismundi_op_post_mentions( $post ) ) : null;
	return is_array( $audience ) && true === $audience['public'];
}

/**
 * Render post content through WordPress's normal content pipeline.
 *
 * @param WP_Post     $post    Post.
 * @param string|null $content Optional fragment; defaults to the full post content.
 * @return string
 */
function axismundi_op_post_article_content( WP_Post $post, ?string $content = null ) : string {
	/**
	 * Filter whether the normal `the_content` pipeline renders Article content.
	 *
	 * @since 0.0.2
	 * @param bool    $use_pipeline True by default.
	 * @param WP_Post $post         Post.
	 */
	$use_pipeline = (bool) apply_filters( 'axismundi_op_use_the_content', true, $post );
	$content = null === $content ? (string) $post->post_content : $content;
	if ( ! $use_pipeline ) {
		return $content;
	}

	$postdata_keys = array( 'post', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages' );
	$previous      = array();
	foreach ( $postdata_keys as $key ) {
		$previous[ $key ] = array(
			'exists' => array_key_exists( $key, $GLOBALS ),
			'value'  => $GLOBALS[ $key ] ?? null,
		);
	}

	$GLOBALS['post'] = $post;
	setup_postdata( $post );
	try {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core content rendering pipeline.
		return (string) apply_filters( 'the_content', $content );
	} finally {
		foreach ( $previous as $key => $state ) {
			if ( $state['exists'] ) {
				$GLOBALS[ $key ] = $state['value'];
			} else {
				unset( $GLOBALS[ $key ] );
			}
		}
	}
}

/** Build the optional embedded Note preview without minting another object id. */
function axismundi_op_post_article_preview( WP_Post $post, string $attributed_to, string $published ) : ?array {
	$excerpt = trim( (string) $post->post_excerpt );
	$parts    = get_extended( (string) $post->post_content );
	$has_more = false !== strpos( (string) $post->post_content, '<!--more' );
	$content  = '';
	if ( $has_more && '' !== trim( (string) ( $parts['main'] ?? '' ) ) ) {
		$content = axismundi_op_post_article_content( $post, (string) $parts['main'] );
	} elseif ( '' !== $excerpt ) {
		$content = '<p><strong>' . esc_html( get_the_title( $post ) ) . '</strong></p>' . wpautop( $excerpt );
	}
	if ( '' === trim( $content ) ) {
		return null;
	}
	return array(
		'type'         => 'Note',
		'attributedTo' => $attributed_to,
		'content'      => $content,
		'published'    => $published,
	);
}

/** Resolve the site Application used as the Article generator, when public. */
function axismundi_op_post_article_generator() : ?array {
	if ( ! function_exists( 'axismundi_actors_get_site_actor' ) ) {
		return null;
	}
	$actor = axismundi_actors_get_site_actor();
	if ( ! $actor || ! function_exists( 'axismundi_actors_is_public_profile' ) || ! axismundi_actors_is_public_profile( $actor ) ) {
		return null;
	}
	return array(
		'type' => 'Application',
		'id'   => (string) $actor->get_uri(),
		'name' => sanitize_text_field( wp_strip_all_tags( get_bloginfo( 'name' ) ) ),
	);
}

/**
 * Project an explicitly authored FEP-044f canQuote policy.
 *
 * The declaration is advisory and never fabricates QuoteAuthorization evidence.
 *
 * @return array<string,mixed>|null
 */
function axismundi_op_post_quote_interaction_policy( WP_Post $post, string $actor_uri ) : ?array {
	$policy = axismundi_op_post_quote_policy( $post );
	if ( '' === $policy ) {
		return null;
	}
	$automatic = '';
	if ( 'anyone' === $policy ) {
		$automatic = 'https://www.w3.org/ns/activitystreams#Public';
	} elseif ( 'me' === $policy ) {
		$automatic = $actor_uri;
	} elseif ( 'followers' === $policy && function_exists( 'axismundi_actors_get_by_uri' ) && function_exists( 'axismundi_op_actor_followers_url' ) ) {
		$actor = axismundi_actors_get_by_uri( $actor_uri );
		if ( $actor instanceof Axismundi_Actor && $actor->is_local() ) {
			$automatic = axismundi_op_actor_followers_url( $actor );
		}
	}
	if ( '' === $automatic ) {
		return null;
	}
	return array(
		'canQuote' => array(
			'automaticApproval' => $automatic,
		),
	);
}

/** Supply Activities with one local Post's explicit Quote policy and author. */
function axismundi_op_resolve_quote_request_target( $target, string $object_uri ) {
	$source = function_exists( 'axismundi_op_local_source_from_object_uri' ) ? axismundi_op_local_source_from_object_uri( $object_uri ) : null;
	if ( ! $source instanceof WP_Post || 'post' !== $source->post_type ) {
		return $target;
	}
	$object = axismundi_op_transform_object( $source );
	$policy = axismundi_op_post_quote_policy( $source );
	if ( ! is_array( $object ) || empty( $object['id'] ) || empty( $object['attributedTo'] ) ) {
		return $target;
	}
	return array(
		'object_uri'       => (string) $object['id'],
		'author_actor_uri' => axismundi_op_remote_member_uri( $object['attributedTo'] ),
		'policy'           => $policy,
	);
}
add_filter( 'axismundi_act_resolve_quote_request_target', 'axismundi_op_resolve_quote_request_target', 10, 2 );

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
	$mention_uris = axismundi_op_post_mentions( $post );
	$mentions     = axismundi_op_post_article_mention_tags( $post, false );
	$audience     = axismundi_op_post_article_audience( $post, $mention_uris );
	if ( is_wp_error( $audience ) ) {
		return $audience;
	}

	$published = get_post_time( DATE_W3C, true, $post );
	$article   = array(
		'id'           => $id,
		'type'         => 'Article',
		'attributedTo' => $attributed_to,
		'url'          => array( 'type' => 'Link', 'href' => $url, 'mediaType' => 'text/html' ),
		'name'         => get_the_title( $post ),
		'content'      => axismundi_op_post_article_content( $post ),
		'mediaType'    => 'text/html',
		'published'    => $published,
		'updated'      => get_post_modified_time( DATE_W3C, true, $post ),
		'to'           => $audience['to'],
		'cc'           => $audience['cc'],
	);
	if ( ! empty( $mentions ) ) {
		$article['tag'] = $mentions;
	}

	if ( '' !== trim( (string) $post->post_excerpt ) ) {
		$article['summary'] = wpautop( (string) $post->post_excerpt );
	}
	$preview = axismundi_op_post_article_preview( $post, $attributed_to, $published );
	if ( null !== $preview ) {
		$article['preview'] = $preview;
	}
	$generator = axismundi_op_post_article_generator();
	if ( null !== $generator ) {
		$article['generator'] = $generator;
	}
	/** Filter whether this Article is sensitive. */
	$article['sensitive'] = (bool) apply_filters( 'axismundi_op_post_sensitive', axismundi_op_post_is_sensitive( $post ), $post );
	$warning = axismundi_op_post_content_warning( $post );
	if ( $article['sensitive'] && '' !== $warning ) {
		$article['dcterms:subject'] = $warning;
	}
	$interaction_policy = axismundi_op_post_quote_interaction_policy( $post, $attributed_to );
	if ( null !== $interaction_policy ) {
		$article['interactionPolicy'] = $interaction_policy;
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
