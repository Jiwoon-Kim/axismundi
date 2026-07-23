<?php
/**
 * Neutral single-object view-model registry and the axismundi/object-view block.
 *
 * A product (Note now; remote cache later) registers an adapter that normalizes
 * its own source into one view model. The dynamic `axismundi/object-view` block
 * renders the request's current view model inside a block template, so the human
 * HTML page never depends on a Core `post-content` block or a faked singular
 * query — a Tombstone has no `WP_Post` and must still render.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** @var array<string,array<string,mixed>> View-model adapters, by stable id. */
$GLOBALS['axismundi_op_view_model_adapters'] = array();

/**
 * Register one source-to-view-model adapter.
 *
 * OP never reads product storage; the adapter's supports and transform callbacks
 * do. Definitions follow the transformer registry's deterministic id/priority
 * contract so plugin load order never decides which product owns a source.
 *
 * @param string              $id   Stable lowercase slug.
 * @param array<string,mixed> $args supports, transform, [priority].
 * @return true|WP_Error
 */
function axismundi_op_register_object_view_model( string $id, array $args ) {
	$key = sanitize_key( $id );
	if ( '' === $key || $key !== $id ) {
		return new WP_Error( 'ax_op_view_model_id', __( 'View-model adapter IDs must be non-empty lowercase slugs.', 'axismundi-object-projections' ) );
	}
	if ( ! is_callable( $args['supports'] ?? null ) || ! is_callable( $args['transform'] ?? null ) ) {
		return new WP_Error( 'ax_op_view_model_args', __( 'A view-model adapter requires callable supports and transform callbacks.', 'axismundi-object-projections' ) );
	}
	return axismundi_op_store_transformer(
		'axismundi_op_view_model_adapters',
		array(
			'id'        => $key,
			'supports'  => $args['supports'],
			'transform' => $args['transform'],
			'priority'  => isset( $args['priority'] ) ? (int) $args['priority'] : 10,
		)
	);
}

/**
 * Contract defaults every Object View Model carries.
 *
 * Blocks may read any of these keys without existence checks. An adapter that
 * knows nothing about a field simply leaves the default, so adding a field here
 * never requires editing every product adapter in lockstep.
 *
 * @return array<string,mixed>
 */
function axismundi_op_object_view_model_defaults() : array {
	return array(
		'summary'      => '',
		'human_url'    => '',
		'in_reply_to'  => '',
		'hashtags'     => array(),
		'mentions'     => array(),
		// Raw ActivityStreams `image` exactly as the adapter received it. The
		// renderable single descriptor derived from it is `media.image`.
		'image'        => null,
		'media'        => array(
			'image'          => null,
			'featured'       => null,
			'before_content' => array(),
			'after_content'  => array(),
			'downloads'      => array(),
			'inline_refs'    => array(),
			'layout'         => 'single',
		),
		'presentation' => array( 'profile' => 'text-first' ),
		'visibility'   => array( 'level' => '' ),
		'capabilities' => array( 'can_edit' => false ),
	);
}

/** The WordPress Post backing one source, when it has one. */
function axismundi_op_view_model_backing_post( $source ) : ?WP_Post {
	if ( $source instanceof WP_Post ) {
		return $source;
	}
	if ( is_object( $source ) && method_exists( $source, 'get_post' ) ) {
		$post = $source->get_post();
		return $post instanceof WP_Post ? $post : null;
	}
	return null;
}

/** First resolvable href for one normalized attachment descriptor. */
function axismundi_op_attachment_href( array $descriptor ) : string {
	$urls = $descriptor['url'] ?? array();
	$urls = is_array( $urls ) && array_is_list( $urls ) ? $urls : array( $urls );
	foreach ( $urls as $candidate ) {
		if ( is_string( $candidate ) && '' !== $candidate ) {
			return $candidate;
		}
		if ( is_array( $candidate ) && ! empty( $candidate['href'] ) ) {
			return (string) $candidate['href'];
		}
	}
	return '';
}

/**
 * Normalize an ActivityStreams `image` into one renderable descriptor.
 *
 * FEP-b2b8 gives `image` an explicit "in-stream representative image" meaning,
 * which is a stronger claim than inferring a lead image from the first
 * attachment. It may arrive as a bare URL string, a single Image object, or a
 * list; only the first resolvable member becomes the featured descriptor.
 *
 * Sensitivity is deliberately absent: a representative image inherits the
 * Object's own `sensitive` flag rather than carrying a second one.
 *
 * @param mixed $image Raw AS2 `image` value.
 * @return array{url:string,alt:string,width:int,height:int,mediaType:string}|null
 */
function axismundi_op_normalize_featured_image( $image ) : ?array {
	if ( is_array( $image ) && array_is_list( $image ) ) {
		foreach ( $image as $candidate ) {
			$normalized = axismundi_op_normalize_featured_image( $candidate );
			if ( null !== $normalized ) {
				return $normalized;
			}
		}
		return null;
	}
	if ( is_string( $image ) ) {
		$url = esc_url_raw( trim( $image ) );
		return '' === $url ? null : array( 'url' => $url, 'alt' => '', 'width' => 0, 'height' => 0, 'mediaType' => '' );
	}
	if ( ! is_array( $image ) ) {
		return null;
	}
	$url = axismundi_op_attachment_href( $image );
	if ( '' === $url && isset( $image['href'] ) ) {
		$url = (string) $image['href'];
	}
	$url = esc_url_raw( trim( $url ) );
	if ( '' === $url ) {
		return null;
	}
	// AS2 peers state dimensions on the Image itself; a local descriptor states them
	// on the Link it picked, because one attachment advertises several renditions.
	// Both have to reach the block, or a rendered card cannot reserve space for the
	// image ahead of load.
	$link = array();
	if ( isset( $image['url'] ) && is_array( $image['url'] ) && array_is_list( $image['url'] ) ) {
		foreach ( $image['url'] as $candidate ) {
			if ( is_array( $candidate ) && $url === esc_url_raw( trim( (string) ( $candidate['href'] ?? '' ) ) ) ) {
				$link = $candidate;
				break;
			}
		}
	}
	return array(
		'url'       => $url,
		'alt'       => sanitize_text_field( wp_strip_all_tags( (string) ( $image['name'] ?? $image['summary'] ?? '' ) ) ),
		'width'     => max( 0, (int) ( $image['width'] ?? $link['width'] ?? 0 ) ),
		'height'    => max( 0, (int) ( $image['height'] ?? $link['height'] ?? 0 ) ),
		'mediaType' => sanitize_mime_type( (string) ( $image['mediaType'] ?? $link['mediaType'] ?? '' ) ),
	);
}

/**
 * Split attachments into placement roles without hardcoding a source platform.
 *
 * `attachment` says an asset is related, never where it belongs on screen. An
 * asset already referenced by the body is the body's to place; a long or titled
 * object reads as an article and leads with one representative image; a short
 * object carrying unreferenced visuals reads media-first and leads with them.
 *
 * @param array<int,array<string,mixed>> $attachments Normalized descriptors.
 * @param bool                           $has_explicit_featured Whether AS2 `image` already named a lead image.
 * @return array<string,mixed>
 */
function axismundi_op_classify_object_media( array $attachments, string $content_html, string $type, string $title, bool $has_explicit_featured = false ) : array {
	$media   = axismundi_op_object_view_model_defaults()['media'];
	$visual  = array();
	foreach ( $attachments as $descriptor ) {
		if ( ! is_array( $descriptor ) ) {
			continue;
		}
		$href = axismundi_op_attachment_href( $descriptor );
		if ( '' !== $href && false !== strpos( $content_html, $href ) ) {
			$media['inline_refs'][] = $href;
			continue;
		}
		$kind = strtolower( (string) ( $descriptor['type'] ?? '' ) );
		$mime = strtolower( (string) ( $descriptor['mediaType'] ?? '' ) );
		if ( in_array( $kind, array( 'image', 'video', 'audio' ), true )
			|| str_starts_with( $mime, 'image/' ) || str_starts_with( $mime, 'video/' ) || str_starts_with( $mime, 'audio/' )
		) {
			$visual[] = $descriptor;
			continue;
		}
		$media['downloads'][] = $descriptor;
	}

	$length  = strlen( wp_strip_all_tags( $content_html ) );
	$profile = 'text-first';
	if ( 'Article' === $type || $length > 1200 || ( '' !== $title && $length > 600 ) ) {
		$profile = 'article';
	} elseif ( ! empty( $visual ) && $length < 500 ) {
		$profile = 'media-first';
	}

	if ( 'article' === $profile ) {
		// When the Object already declared a representative image, an attachment
		// must not be promoted into that slot as well: it stays a normal
		// attachment instead of silently disappearing from the list.
		$media['featured']      = $has_explicit_featured ? null : array_shift( $visual );
		$media['after_content'] = $visual;
	} elseif ( 'media-first' === $profile ) {
		$media['before_content'] = $visual;
	} else {
		$media['after_content'] = $visual;
	}

	$count           = count( $media['before_content'] ) + count( $media['after_content'] );
	$media['layout'] = $count >= 5 ? 'carousel' : ( $count >= 2 ? 'grid' : 'single' );
	return array( 'media' => $media, 'profile' => $profile );
}

/**
 * Apply the shared contract to one adapter's output.
 *
 * This stays free of database work: it runs for every resolved source, including
 * each node of a reply tree. Index-backed fields are added by
 * `axismundi_op_enrich_object_view_model()` when an object becomes the one being
 * rendered as a full card.
 *
 * @param array<string,mixed> $model  Adapter output.
 * @param mixed               $source Originating source.
 * @return array<string,mixed>
 */
function axismundi_op_normalize_object_view_model( array $model, $source = null ) : array {
	$model = array_merge( axismundi_op_object_view_model_defaults(), $model );
	if ( 'tombstone' === (string) ( $model['status'] ?? '' ) ) {
		return $model;
	}
	$post = axismundi_op_view_model_backing_post( $source );

	// A non-sensitive `summary` is an excerpt, not a content warning. Adapters
	// map AS2 `summary` onto the warning slot, so reclaim it when nothing is
	// actually being warned about. An excerpt is a plain-text contract: a local
	// adapter may hand over an autop-rendered excerpt, and a summary block is a
	// sibling of Core's post excerpt, not a second content region.
	if ( empty( $model['sensitive'] ) && '' === (string) $model['summary'] && '' !== (string) $model['content_warning'] ) {
		$model['summary']         = trim( wp_strip_all_tags( (string) $model['content_warning'] ) );
		$model['content_warning'] = '';
	}
	if ( '' === (string) $model['summary'] && $post instanceof WP_Post && '' !== trim( (string) $post->post_excerpt ) ) {
		$model['summary'] = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
	}

	if ( '' === (string) $model['human_url'] ) {
		if ( $post instanceof WP_Post ) {
			$permalink            = get_permalink( $post );
			$model['human_url']   = is_string( $permalink ) ? $permalink : '';
		} elseif ( $source instanceof Axismundi_Op_Remote_Source ) {
			$row                = $source->get_row();
			$model['human_url'] = (string) ( $row['human_url'] ?? '' );
		}
	}

	if ( '' === (string) $model['visibility']['level'] && $source instanceof Axismundi_Op_Remote_Source ) {
		$row                         = $source->get_row();
		$model['visibility']['level'] = function_exists( 'axismundi_op_remote_object_is_publicly_listable' ) && axismundi_op_remote_object_is_publicly_listable( $row )
			? 'public'
			: 'limited';
	}

	$model['capabilities']['can_edit'] = $post instanceof WP_Post && current_user_can( 'edit_post', $post->ID );

	// A declared `image` outranks anything inferred from the attachment list.
	$featured                = axismundi_op_normalize_featured_image( $model['image'] );
	$classified              = axismundi_op_classify_object_media(
		(array) ( $model['attachments'] ?? array() ),
		(string) ( $model['content_html'] ?? '' ),
		(string) ( $model['type'] ?? '' ),
		(string) ( $model['title'] ?? '' ),
		null !== $featured
	);
	$model['media']          = $classified['media'];
	// `media.image` is the author's declared representative image and nothing else,
	// so a featured-image surface renders it or renders nothing. It never becomes an
	// attachment: promoting one would change the author's meaning on receipt.
	$model['media']['image'] = $featured;
	// `media.featured` is the derived lead/thumbnail candidate -- the declared image
	// when there is one, otherwise an image inferred from the attachment list. It is
	// what a compact card or search result may show, kept distinct from `media.image`
	// so the original meaning and the UI convenience never blur into one field. The
	// attachment form nests its URL in Link objects, so it takes the same normalizer.
	$model['media']['featured'] = null !== $featured
		? $featured
		: axismundi_op_normalize_featured_image( $classified['media']['featured'] );
	$model['presentation']['profile'] = $classified['profile'];

	/**
	 * Let a product complete its own source-specific view-model fields.
	 *
	 * @param array<string,mixed> $model  Normalized view model.
	 * @param mixed               $source Originating source.
	 */
	return (array) apply_filters( 'axismundi_op_object_view_model', $model, $source );
}

/**
 * Add index-backed relations to one view model.
 *
 * Deliberately separate from normalization: a bounded reply tree resolves a
 * model per node and must not pay for these lookups per node.
 *
 * @param array<string,mixed> $model Normalized view model.
 * @return array<string,mixed>
 */
function axismundi_op_enrich_object_view_model( array $model ) : array {
	$model = array_merge( axismundi_op_object_view_model_defaults(), $model );
	$uri   = (string) ( $model['object_uri'] ?? $model['id'] ?? '' );
	if ( '' === $uri || 'tombstone' === (string) ( $model['status'] ?? '' ) ) {
		$model['_enriched'] = true;
		return $model;
	}
	if ( '' === (string) $model['in_reply_to'] && function_exists( 'axismundi_op_get_thread_parent_uri' ) ) {
		$model['in_reply_to'] = axismundi_op_get_thread_parent_uri( $uri );
	}
	if ( empty( $model['hashtags'] ) && function_exists( 'axismundi_op_object_hashtag_chips' ) ) {
		$model['hashtags'] = axismundi_op_object_hashtag_chips( $uri );
	}
	if ( empty( $model['mentions'] ) && function_exists( 'axismundi_op_mentions_for_object' ) ) {
		$model['mentions'] = axismundi_op_mentions_for_object( $uri );
	}
	$model['_enriched'] = true;
	return $model;
}

/**
 * Resolve one source into its normalized view model, or null.
 *
 * @return array<string,mixed>|null
 */
function axismundi_op_object_view_model( $source ) : ?array {
	foreach ( axismundi_op_sort_registry( (array) $GLOBALS['axismundi_op_view_model_adapters'] ) as $adapter ) {
		try {
			if ( true !== call_user_func( $adapter['supports'], $source ) ) {
				continue;
			}
			$model = call_user_func( $adapter['transform'], $source );
			if ( is_array( $model ) ) {
				return axismundi_op_normalize_object_view_model( $model, $source );
			}
		} catch ( Throwable $error ) {
			continue;
		}
	}
	return null;
}

/**
 * Set the view model the object blocks render for this request.
 *
 * Becoming the current object is exactly the boundary where a full card is
 * assembled, so index-backed relations are resolved here once rather than for
 * every model a thread or feed happens to touch.
 */
function axismundi_op_set_current_object_view_model( ?array $model ) : void {
	if ( is_array( $model ) && empty( $model['_enriched'] ) ) {
		$model = axismundi_op_enrich_object_view_model( $model );
	}
	$GLOBALS['axismundi_op_current_view_model'] = $model;
}

/** The view model bound to this request, or null. */
function axismundi_op_current_object_view_model() : ?array {
	$model = $GLOBALS['axismundi_op_current_view_model'] ?? null;
	return is_array( $model ) ? $model : null;
}

/** Render one author byline from a view model. */
function axismundi_op_object_view_author( array $model ) : string {
	$author = isset( $model['author'] ) && is_array( $model['author'] ) ? $model['author'] : array();
	$name   = (string) ( $author['name'] ?? '' );
	if ( '' === $name ) {
		return '';
	}
	$handle = (string) ( $author['handle'] ?? '' );
	$url    = (string) ( $author['url'] ?? '' );
	$label  = '' !== $handle
		? esc_html( $name ) . ' <span class="axismundi-object__handle">' . esc_html( $handle ) . '</span>'
		: esc_html( $name );
	$inner  = '' !== $url ? '<a href="' . esc_url( $url ) . '" rel="author">' . $label . '</a>' : $label;
	return '<p class="axismundi-object__author">' . $inner . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above.
}

/** Render one already-sanitized local media descriptor without guessing storage. */
function axismundi_op_object_view_attachment( array $descriptor ) : string {
	$urls = $descriptor['url'] ?? array();
	$urls = is_array( $urls ) && array_is_list( $urls ) ? $urls : array( $urls );
	$link = null;
	foreach ( $urls as $candidate ) {
		if ( is_array( $candidate ) && ! empty( $candidate['href'] ) ) {
			$link = $candidate;
			break;
		}
	}
	if ( ! is_array( $link ) ) {
		return '';
	}
	$href = esc_url( (string) $link['href'] );
	if ( '' === $href ) {
		return '';
	}
	$name       = trim( (string) ( $descriptor['name'] ?? '' ) );
	$media_type = strtolower( (string) ( $link['mediaType'] ?? $descriptor['mediaType'] ?? '' ) );
	$type       = strtolower( (string) ( $descriptor['type'] ?? 'document' ) );
	if ( 'image' === $type && str_starts_with( $media_type, 'image/' ) ) {
		$body = '<figure class="axismundi-object__attachment axismundi-object__attachment--image"><img src="' . $href . '" alt="' . esc_attr( $name ) . '" loading="lazy" />';
		if ( '' !== $name ) {
			$body .= '<figcaption>' . esc_html( $name ) . '</figcaption>';
		}
		$body .= '</figure>';
	} else {
		$label = '' !== $name ? $name : __( 'Open attachment', 'axismundi-object-projections' );
		$body  = '<p class="axismundi-object__attachment axismundi-object__attachment--file"><a href="' . $href . '">' . esc_html( $label ) . '</a></p>';
	}
	if ( ! empty( $descriptor['sensitive'] ) ) {
		$warning = trim( (string) ( $descriptor['summary'] ?? '' ) );
		$summary = '' !== $warning ? esc_html( $warning ) : esc_html__( 'Sensitive media', 'axismundi-object-projections' );
		return '<details class="axismundi-object__attachment-warning"><summary>' . $summary . '</summary>' . $body . '</details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Body escaped above.
	}
	return $body;
}

/**
 * Render the request's current object view model.
 *
 * A Tombstone renders a minimal deleted notice with no author, content, or
 * interaction affordances. An active object renders its content with an optional
 * content-warning wrapper and an interactions slot products may fill.
 */
function axismundi_op_render_object_view_block( array $attributes = array(), string $content = '' ) : string {
	unset( $content );
	$model = axismundi_op_current_object_view_model();
	if ( null === $model ) {
		return '';
	}
	$heading_tag          = isset( $attributes['headingTag'] ) && in_array( $attributes['headingTag'], array( 'h1', 'h2', 'h3', 'h4' ), true ) ? $attributes['headingTag'] : 'h1';
	$interactions_enabled = ! isset( $attributes['interactions'] ) || (bool) $attributes['interactions'];

	if ( 'tombstone' === (string) ( $model['status'] ?? '' ) ) {
		return '<div class="axismundi-object axismundi-object--tombstone">'
			. '<p class="axismundi-object__deleted">' . esc_html__( 'This object has been deleted.', 'axismundi-object-projections' ) . '</p>'
			. '</div>';
	}

	$parts   = array();
	$title   = trim( (string) ( $model['title'] ?? '' ) );
	if ( '' !== $title ) {
		$parts[] = '<' . $heading_tag . ' class="axismundi-object__title">' . esc_html( $title ) . '</' . $heading_tag . '>';
	}
	$parts[] = axismundi_op_object_view_author( $model );

	$published = (string) ( $model['published'] ?? '' );
	if ( '' !== $published ) {
		$timestamp = strtotime( $published );
		if ( false !== $timestamp ) {
			$parts[] = '<p class="axismundi-object__published"><time datetime="' . esc_attr( gmdate( 'c', $timestamp ) ) . '">'
				. esc_html( wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $timestamp ) )
				. '</time></p>';
		}
	}

	$body = wp_kses_post( (string) ( $model['content_html'] ?? '' ) );
	if ( ! empty( $model['sensitive'] ) ) {
		$warning = (string) ( $model['content_warning'] ?? '' );
		$summary = '' !== trim( $warning ) ? esc_html( $warning ) : esc_html__( 'Sensitive content', 'axismundi-object-projections' );
		$parts[] = '<details class="axismundi-object__sensitive"><summary>' . $summary . '</summary>'
			. '<div class="axismundi-object__content">' . $body . '</div></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Body is wp_kses_post rendered by the adapter.
	} else {
		$parts[] = '<div class="axismundi-object__content">' . $body . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Body is wp_kses_post rendered by the adapter.
	}

	$attachment_html = array();
	foreach ( (array) ( $model['attachments'] ?? array() ) as $descriptor ) {
		if ( is_array( $descriptor ) ) {
			$rendered = axismundi_op_object_view_attachment( $descriptor );
			if ( '' !== $rendered ) {
				$attachment_html[] = $rendered;
			}
		}
	}
	if ( $attachment_html ) {
		$parts[] = '<div class="axismundi-object__attachments">' . implode( '', $attachment_html ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Children escaped by renderer.
	}

	/**
	 * Filter interaction affordances (Like / Announce) for one active object view.
	 *
	 * Interactions are never rendered for a Tombstone. The canonical object URI is
	 * passed so a consumer can bind actions without a personalized shared cache.
	 *
	 * @param string               $html  Interaction markup (empty by default).
	 * @param array<string,mixed>  $model The active object view model.
	 */
	$interactions = $interactions_enabled ? (string) apply_filters( 'axismundi_op_object_view_interactions', '', $model ) : '';
	if ( '' !== $interactions ) {
		$parts[] = '<div class="axismundi-object__interactions">' . $interactions . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Interaction consumer owns escaping.
	}

	$type_class = sanitize_html_class( strtolower( (string) ( $model['type'] ?? 'object' ) ), 'object' );
	return '<article class="axismundi-object axismundi-object--' . esc_attr( $type_class ) . '">'
		. implode( '', array_filter( $parts ) )
		. '</article>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above.
}

/**
 * Resolve one object URI and render it as a compact object view for a feed.
 *
 * OP owns turning an object URI into a card: a local product claims the URI
 * through the resolve filter, a cached remote observation is the fallback.
 * Visibility reuses the same public/unlisted-only gate the thread, JSON-LD, and
 * HTML routes enforce, so a followers-only or draft local object never leaks
 * through a feed. Tombstones, hidden sources, and unresolvable URIs render
 * nothing, so the caller can simply drop the row. When `expected_author` is a
 * non-empty URI the resolved object's author must equal it (an authored Create
 * belongs to the acting Actor); a boosted Announce passes '' because its object
 * is authored by someone else.
 *
 * @param string              $uri  Canonical object URI.
 * @param array<string,mixed> $opts headingTag, interactions, expected_author.
 * @return string Rendered object HTML, or '' when nothing should be shown.
 */
function axismundi_op_render_object_by_uri( string $uri, array $opts = array() ) : string {
	$uri = is_string( $uri ) ? trim( $uri ) : '';
	if ( '' === $uri || ! function_exists( 'axismundi_op_resolve_source_by_uri' ) ) {
		return '';
	}
	$source = axismundi_op_resolve_source_by_uri( $uri );
	if ( null === $source
		|| ! function_exists( 'axismundi_op_object_card_publicly_renderable' )
		|| ! axismundi_op_object_card_publicly_renderable( $source )
	) {
		return '';
	}
	$model = axismundi_op_object_view_model( $source );
	if ( ! is_array( $model ) || 'tombstone' === (string) ( $model['status'] ?? '' ) ) {
		return '';
	}
	$expected_author = isset( $opts['expected_author'] ) ? (string) $opts['expected_author'] : '';
	if ( '' !== $expected_author && $expected_author !== (string) ( $model['author']['id'] ?? '' ) ) {
		return '';
	}
	$options = array(
		'headingTag'   => $opts['headingTag'] ?? 'h3',
		'interactions' => isset( $opts['interactions'] ) ? (bool) $opts['interactions'] : false,
	);
	$previous = axismundi_op_current_object_view_model();
	axismundi_op_set_current_object_view_model( $model );
	try {
		// One composition for every surface. The Object Card pattern is the single
		// assembly point, so a block added to it appears on the single Object page,
		// the Actor timeline, and the hashtag archive at once. The monolithic
		// `axismundi/object-view` block stays registered for compatibility but no
		// longer assembles cards. The pattern already contains Question and Quote,
		// so neither is appended here.
		return function_exists( 'axismundi_op_render_object_pattern' )
			? axismundi_op_render_object_pattern( $options )
			: axismundi_op_render_object_view_block( $options );
	} finally {
		axismundi_op_set_current_object_view_model( $previous );
	}
}

/** Register the server-rendered object-view block (no editor script). */
function axismundi_op_register_object_view_block() : void {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}
	register_block_type(
		'axismundi/object-view',
		array(
			'api_version'     => 3,
			'title'           => __( 'Axismundi Object', 'axismundi-object-projections' ),
			'category'        => 'theme',
			'render_callback' => 'axismundi_op_render_object_view_block',
			'supports'        => array( 'html' => false, 'inserter' => false ),
		)
	);
}
add_action( 'init', 'axismundi_op_register_object_view_block' );
