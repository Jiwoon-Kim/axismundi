<?php
/**
 * Composable blocks and one reusable pattern for a neutral Object view.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** @var array<string,mixed> Per-render options for single vs feed contexts. */
$GLOBALS['axismundi_op_object_template_options'] = array();

/**
 * Read the shared Object Card pattern source.
 *
 * Every card surface renders through this pattern, so a feed or archive page
 * reads it once per item. The source is fixed for the request; caching it keeps
 * that from becoming one file include per rendered Object.
 */
function axismundi_op_object_card_pattern_content() : string {
	static $content = null;
	if ( null !== $content ) {
		return $content;
	}
	$path = dirname( __DIR__ ) . '/templates/object-card.php';
	if ( ! is_readable( $path ) ) {
		return '';
	}
	ob_start();
	include $path;
	$content = (string) ob_get_clean();
	return $content;
}

/** Read the canonical standalone Object template bundled with OP. */
function axismundi_op_single_object_template_content() : string {
	$path = dirname( __DIR__ ) . '/templates/single-object.php';
	if ( ! is_readable( $path ) ) {
		return '';
	}
	ob_start();
	include $path;
	return (string) ob_get_clean();
}

/** Read the privacy-minimal Tombstone template bundled with OP. */
function axismundi_op_tombstone_template_content() : string {
	$path = dirname( __DIR__ ) . '/templates/object-tombstone.php';
	if ( ! is_readable( $path ) ) {
		return '';
	}
	ob_start();
	include $path;
	return (string) ob_get_clean();
}

/**
 * Minimal supports for the current inline server registrations.
 *
 * Core-style supports require block metadata to be registered identically on the
 * server and in the editor. These inline blocks have not yet been migrated to
 * block.json directories, so keep the contract deliberately minimal until that
 * migration is complete.
 *
 * @return array<string,mixed>
 */
function axismundi_op_object_block_supports() : array {
	return array(
		'html' => false,
	);
}

/** Current shared-template rendering option. */
function axismundi_op_object_template_option( string $key, $default = null ) {
	$options = (array) ( $GLOBALS['axismundi_op_object_template_options'] ?? array() );
	return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
}

/**
 * Render the shared starter pattern. Single and archive templates remain separate;
 * each may diverge after insertion in the Site Editor.
 *
 * @param array<string,mixed> $options headingTag and interactions.
 */
function axismundi_op_render_object_pattern( array $options = array() ) : string {
	if ( null === axismundi_op_current_object_view_model() ) {
		return '';
	}
	$previous = (array) ( $GLOBALS['axismundi_op_object_template_options'] ?? array() );
	$GLOBALS['axismundi_op_object_template_options'] = array_merge(
		array( 'headingTag' => 'h1', 'interactions' => true ),
		$options
	);
	try {
		return do_blocks( axismundi_op_object_card_pattern_content() );
	} finally {
		$GLOBALS['axismundi_op_object_template_options'] = $previous;
	}
}

/** Current active model, excluding Tombstones. */
function axismundi_op_active_object_view_model() : ?array {
	$model = axismundi_op_current_object_view_model();
	return is_array( $model ) && 'tombstone' !== (string) ( $model['status'] ?? '' ) ? $model : null;
}

/** Render a deleted-object notice; active objects leave this slot empty. */
function axismundi_op_render_object_status_block() : string {
	$model = axismundi_op_current_object_view_model();
	if ( ! is_array( $model ) || 'tombstone' !== (string) ( $model['status'] ?? '' ) ) {
		return '';
	}
	return '<p ' . get_block_wrapper_attributes( array( 'class' => 'axismundi-object__deleted' ) ) . '>'
		. esc_html__( 'This object has been deleted.', 'axismundi-object-projections' ) . '</p>';
}

/**
 * Supply the current Object's normalized author to Actors-owned display blocks.
 *
 * A cached Actor record is preferable and will be used by Actors itself. The
 * descriptor fallback keeps a freshly observed remote Object readable while
 * its Actor fetch is still pending.
 *
 * @param array<string,mixed>|null $subject Existing subject, if any.
 * @return array<string,mixed>|null
 */
function axismundi_op_current_object_author_subject( $subject, string $context_actor_id ) {
	unset( $context_actor_id );
	if ( is_array( $subject ) ) {
		return $subject;
	}
	$model  = axismundi_op_active_object_view_model();
	$author = is_array( $model['author'] ?? null ) ? $model['author'] : array();
	if ( empty( $author ) ) {
		return null;
	}
	return array(
		'name'               => (string) ( $author['name'] ?? '' ),
		'preferred_username' => (string) ( $author['preferred_username'] ?? '' ),
		'handle'             => (string) ( $author['handle'] ?? '' ),
		'url'                => (string) ( $author['url'] ?? '' ),
		'avatar_url'         => (string) ( $author['avatar_url'] ?? '' ),
		'type'               => '',
	);
}
add_filter( 'axismundi_actors_block_subject', 'axismundi_op_current_object_author_subject', 10, 2 );

/** Render the legacy Object avatar alias through the Actors-owned block. */
function axismundi_op_render_object_avatar_block( array $attributes = array() ) : string {
	$size = max( 24, min( 192, (int) ( $attributes['size'] ?? 48 ) ) );
	return do_blocks( '<!-- wp:axismundi/actor-avatar {"size":' . $size . ',"variant":"compact"} /-->' );
}

/** Render the legacy Object identity alias through the Actors-owned block. */
function axismundi_op_render_object_identity_block() : string {
	return do_blocks( '<!-- wp:axismundi/actor-identity {"variant":"compact"} /-->' );
}

/** Render object type and publication time. */
function axismundi_op_render_object_meta_block() : string {
	$model = axismundi_op_active_object_view_model();
	if ( ! is_array( $model ) ) {
		return '';
	}
	$parts = array();
	$type  = sanitize_text_field( (string) ( $model['type'] ?? '' ) );
	if ( '' !== $type ) {
		$parts[] = '<span class="axismundi-object__type">' . esc_html( $type ) . '</span>';
	}
	$published = (string) ( $model['published'] ?? '' );
	$timestamp = '' !== $published ? strtotime( $published ) : false;
	if ( false !== $timestamp ) {
		$parts[] = '<time datetime="' . esc_attr( gmdate( 'c', $timestamp ) ) . '">'
			. esc_html( wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ), $timestamp ) ) . '</time>';
	}
	return empty( $parts ) ? '' : '<div ' . get_block_wrapper_attributes( array( 'class' => 'axismundi-object__meta' ) ) . '>' . implode( '', $parts ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values escaped above.
}

/** Render the optional authored title. */
function axismundi_op_render_object_title_block() : string {
	$model = axismundi_op_active_object_view_model();
	$title = is_array( $model ) ? trim( (string) ( $model['title'] ?? '' ) ) : '';
	if ( '' === $title ) {
		return '';
	}
	$tag = (string) axismundi_op_object_template_option( 'headingTag', 'h1' );
	$tag = in_array( $tag, array( 'h1', 'h2', 'h3', 'h4' ), true ) ? $tag : 'h1';
	return '<' . $tag . ' ' . get_block_wrapper_attributes( array( 'class' => 'axismundi-object__title' ) ) . '>' . esc_html( $title ) . '</' . $tag . '>';
}

/** Render content with the authored sensitive-content gate. */
function axismundi_op_render_object_content_block( array $attributes = array() ) : string {
	$model = axismundi_op_active_object_view_model();
	if ( ! is_array( $model ) ) {
		return '';
	}
	$body = wp_kses_post( (string) ( $model['content_html'] ?? '' ) );
	if ( ! empty( $model['sensitive'] ) ) {
		$warning = trim( (string) ( $model['content_warning'] ?? '' ) );
		$summary = '' !== $warning ? esc_html( $warning ) : esc_html__( 'Sensitive content', 'axismundi-object-projections' );
		return '<details ' . get_block_wrapper_attributes( array( 'class' => 'axismundi-object__sensitive' ) ) . '><summary>' . $summary . '</summary><div class="axismundi-object__content">' . $body . '</div></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Body sanitized above.
	}
	$tag = (string) ( $attributes['tagName'] ?? 'div' );
	$tag = in_array( $tag, array( 'article', 'div', 'main', 'section' ), true ) ? $tag : 'div';
	return '<' . $tag . ' ' . get_block_wrapper_attributes( array( 'class' => 'axismundi-object__content' ) ) . '>' . $body . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Body sanitized above.
}

/** Render normalized Object attachments. */
function axismundi_op_render_object_attachments_block() : string {
	$model = axismundi_op_active_object_view_model();
	if ( ! is_array( $model ) ) {
		return '';
	}
	$items = array();
	foreach ( (array) ( $model['attachments'] ?? array() ) as $descriptor ) {
		if ( is_array( $descriptor ) ) {
			$html = axismundi_op_object_view_attachment( $descriptor );
			if ( '' !== $html ) {
				$items[] = $html;
			}
		}
	}
	return empty( $items ) ? '' : '<div ' . get_block_wrapper_attributes( array( 'class' => 'axismundi-object__attachments' ) ) . '>' . implode( '', $items ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Child renderer escapes descriptors.
}

/**
 * The lead image this block renders, and the warning it inherits.
 *
 * An Actor's header image and an Article's featured image are the same thing in
 * two contexts: the one image that represents a subject in stream. The block is
 * placed by context, so the context decides which subject it is asking about --
 * an explicit `axismundi/actorId` says "this belongs to that Actor", and anything
 * else falls back to the Object currently being rendered.
 *
 * @param WP_Block|null $block Block instance, for its context.
 * @return array{url:string,alt:string,width:int,height:int,sensitive:bool,warning:string,href:string}|null
 */
function axismundi_op_featured_image_subject( $block ) : ?array {
	// The presence of the context is the claim, not its value: on a profile route the
	// Actor comes from the route and the id travels empty, exactly as it does for the
	// other Actor blocks.
	$has_actor_context = $block instanceof WP_Block && array_key_exists( 'axismundi/actorId', (array) $block->context );
	if ( $has_actor_context && function_exists( 'axismundi_actors_resolve_block_actor' ) ) {
		$actor = axismundi_actors_resolve_block_actor( (string) ( $block->context['axismundi/actorId'] ?? '' ) );
		if ( ! $actor ) {
			return null;
		}
		// Actors owns how an Actor's header resolves -- local attachment, cached
		// remote asset, or nothing -- so the URL is read back out of that markup
		// rather than re-derived here.
		$url = axismundi_op_first_image_src( axismundi_actors_header_html( $actor ) );
		return '' === $url
			? null
			: array( 'url' => $url, 'alt' => '', 'width' => 0, 'height' => 0, 'sensitive' => false, 'warning' => '', 'href' => '' );
	}
	$model    = axismundi_op_active_object_view_model();
	// Only a declared `image` is this Object's representative image. An attachment is
	// related media, not a hero the author chose, so a titled article with no `image`
	// renders no lead image here rather than promoting its first attachment -- that
	// inference is a compact-card thumbnail's job, not a featured image's.
	$featured = is_array( $model ) ? ( $model['media']['image'] ?? null ) : null;
	if ( ! is_array( $featured ) || '' === (string) ( $featured['url'] ?? '' ) ) {
		return null;
	}
	return array(
		'url'    => (string) $featured['url'],
		'alt'    => (string) ( $featured['alt'] ?? '' ),
		'width'  => (int) ( $featured['width'] ?? 0 ),
		'height' => (int) ( $featured['height'] ?? 0 ),
		// The lead image inherits the Object's flag instead of answering
		// sensitivity a second time, so one warning covers the whole card.
		'sensitive' => ! empty( $model['sensitive'] ),
		'warning'   => trim( (string) ( $model['content_warning'] ?? '' ) ),
		'href'      => (string) ( $model['human_url'] ?? '' ),
	);
}

/**
 * The `src` of the first image in a fragment, or an empty string.
 *
 * @param string $html Image markup.
 * @return string
 */
function axismundi_op_first_image_src( string $html ) : string {
	if ( '' === trim( $html ) || ! preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches ) ) {
		return '';
	}
	return esc_url_raw( (string) $matches[1] );
}

/**
 * Render the lead image of the Object or Actor in context.
 *
 * A sibling of Core's Featured Image that also answers Cover's questions, because
 * a lead image is asked to be both: sized and cropped like a featured image, and
 * dimmed, tinted, and focal-point-positioned like a cover.
 *
 * The wrapper deliberately carries no Core block class. Wearing another block's
 * class also inherits that block's global styles -- a theme styling
 * `core/post-featured-image` would silently restyle this block -- so only Core's
 * genuinely global colour and gradient preset classes are used. Sizing is a real
 * `dimensions` support, which means Core generates the wrapper CSS and the author
 * finds the controls where every other block keeps them.
 *
 * @param array<string,mixed> $attributes Block attributes.
 * @param string              $content    Block content (unused).
 * @param WP_Block|null       $block      Block instance.
 * @return string
 */
function axismundi_op_render_object_featured_image_block( array $attributes = array(), string $content = '', $block = null ) : string {
	$subject = axismundi_op_featured_image_subject( $block );

	if ( null === $subject ) {
		// Like Core's Featured Image, an absent image is absent: the block leaves no
		// empty box behind. A banner slot is the exception -- most Actors have no
		// header image, and collapsing it would move every profile's layout -- so a
		// caller can ask for the calm placeholder instead.
		if ( empty( $attributes['showPlaceholder'] ) ) {
			return '';
		}
		$wrapper = get_block_wrapper_attributes( array( 'class' => 'axismundi-object__featured-image' ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated wrapper attributes.
		return '<figure ' . $wrapper . '><div aria-hidden="true" class="axismundi-object__featured-image-media is-empty"></div></figure>';
	}

	$focal    = is_array( $attributes['focalPoint'] ?? null ) ? $attributes['focalPoint'] : array();
	$position = sprintf(
		'%s%% %s%%',
		round( (float) ( $focal['x'] ?? 0.5 ) * 100, 2 ),
		round( (float) ( $focal['y'] ?? 0.5 ) * 100, 2 )
	);
	$parallax = ! empty( $attributes['hasParallax'] );
	$repeated = ! empty( $attributes['isRepeated'] );
	$scale    = (string) ( $attributes['scale'] ?? 'cover' );
	$scale    = in_array( $scale, array( 'cover', 'contain', 'fill' ), true ) ? $scale : 'cover';

	if ( $parallax || $repeated ) {
		// A fixed or tiled lead image is a painted background, not a replaced
		// element, so it cannot be an `img`. It keeps an accessible name instead.
		$classes = 'axismundi-object__featured-image-media' . ( $parallax ? ' has-parallax' : '' ) . ( $repeated ? ' is-repeated' : '' );
		$styles  = array( 'background-image:url(' . esc_url( $subject['url'] ) . ')', 'background-position:' . $position );
		$media   = sprintf(
			'<div role="img"%1$s class="%2$s" style="%3$s"></div>',
			'' !== $subject['alt'] ? ' aria-label="' . esc_attr( $subject['alt'] ) . '"' : '',
			esc_attr( $classes ),
			esc_attr( implode( ';', $styles ) )
		);
	} else {
		$styles = array( 'object-fit:' . $scale, 'object-position:' . $position );
		$media  = sprintf(
			'<img class="axismundi-object__featured-image-media" src="%1$s" alt="%2$s"%3$s%4$s loading="lazy" decoding="async" style="%5$s" />',
			esc_url( $subject['url'] ),
			esc_attr( $subject['alt'] ),
			$subject['width'] > 0 ? ' width="' . (int) $subject['width'] . '"' : '',
			$subject['height'] > 0 ? ' height="' . (int) $subject['height'] . '"' : '',
			esc_attr( implode( ';', $styles ) )
		);
	}

	if ( ! empty( $attributes['isLink'] ) && '' !== $subject['href'] ) {
		$target = '_blank' === (string) ( $attributes['linkTarget'] ?? '_self' ) ? '_blank' : '_self';
		$rel    = trim( (string) ( $attributes['rel'] ?? '' ) );
		if ( '_blank' === $target && '' === $rel ) {
			$rel = 'noreferrer noopener';
		}
		$media = sprintf(
			'<a href="%1$s" target="%2$s"%3$s>%4$s</a>',
			esc_url( $subject['href'] ),
			esc_attr( $target ),
			'' !== $rel ? ' rel="' . esc_attr( $rel ) . '"' : '',
			$media
		);
	}

	// The Object's own warning gates its lead image, using the Media Library's
	// reveal overlay so a viewer never meets two different warning treatments.
	if ( $subject['sensitive'] && function_exists( 'axismundi_media_sensitive_overlay_with_warning' ) ) {
		$media = axismundi_media_sensitive_overlay_with_warning( $media, $subject['warning'] );
	}

	$dim = max( 0, min( 100, (int) ( $attributes['dimRatio'] ?? 0 ) ) );
	if ( $dim > 0 ) {
		$overlay_class  = 'axismundi-object__featured-image-overlay';
		$overlay_styles = array( 'opacity:' . round( $dim / 100, 2 ) );
		if ( ! empty( $attributes['overlayColor'] ) ) {
			$overlay_class .= ' has-' . sanitize_html_class( (string) $attributes['overlayColor'] ) . '-background-color has-background';
		} elseif ( ! empty( $attributes['customOverlayColor'] ) ) {
			$overlay_styles[] = 'background-color:' . sanitize_hex_color( (string) $attributes['customOverlayColor'] );
		}
		if ( ! empty( $attributes['gradient'] ) ) {
			$overlay_class .= ' has-' . sanitize_html_class( (string) $attributes['gradient'] ) . '-gradient-background has-background';
		} elseif ( ! empty( $attributes['customGradient'] ) ) {
			// A custom gradient is a CSS function, not a URL. Only the gradient
			// functions are accepted, so a crafted attribute cannot smuggle in a
			// second declaration or an external request.
			$gradient = trim( (string) $attributes['customGradient'] );
			if ( preg_match( '/^(repeating-)?(linear|radial|conic)-gradient\([^;{}<>"\']*\)$/i', $gradient ) ) {
				$overlay_styles[] = 'background-image:' . $gradient;
			}
		}
		$media .= sprintf(
			'<span aria-hidden="true" class="%1$s" style="%2$s"></span>',
			esc_attr( $overlay_class ),
			esc_attr( implode( ';', array_filter( $overlay_styles ) ) )
		);
	}

	$wrapper = get_block_wrapper_attributes( array( 'class' => 'axismundi-object__featured-image' ) );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every part is escaped where it is built.
	return '<figure ' . $wrapper . '>' . $media . '</figure>';
}

/**
 * Render the Object's excerpt.
 *
 * A sibling of Core's Post Excerpt. This is deliberately not the content
 * warning: the view model separates a plain summary from a warning, so a
 * spoiler never renders as an excerpt.
 */
function axismundi_op_render_object_summary_block() : string {
	$model   = axismundi_op_active_object_view_model();
	$summary = is_array( $model ) ? trim( (string) ( $model['summary'] ?? '' ) ) : '';
	if ( '' === $summary ) {
		return '';
	}
	return '<p ' . get_block_wrapper_attributes( array( 'class' => 'axismundi-object__summary' ) ) . '>' . esc_html( $summary ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated wrapper attributes.
}

/**
 * Render the Object's shared hashtags as chips.
 *
 * A sibling of Core's Post Terms. Local and cached remote Objects resolve to the
 * same shared vocabulary, so a chip always links to this site's hashtag archive
 * rather than to a remote tag page.
 */
function axismundi_op_render_object_hashtags_block( array $attributes = array() ) : string {
	$model = axismundi_op_active_object_view_model();
	$tags  = is_array( $model ) ? (array) ( $model['hashtags'] ?? array() ) : array();
	$items = array();
	foreach ( $tags as $tag ) {
		$name = is_array( $tag ) ? trim( (string) ( $tag['name'] ?? '' ) ) : '';
		if ( '' === $name ) {
			continue;
		}
		// The ActivityStreams name carries its own "#", so the marker travels with
		// the term instead of being supplied by a decorative glyph. The wrapper
		// deliberately omits `taxonomy-ax_hashtag`: that class is what triggers the
		// theme's leading glyph on core/post-terms, and both markers at once reads
		// as duplication.
		$url     = is_array( $tag ) ? (string) ( $tag['url'] ?? '' ) : '';
		$items[] = '' !== $url
			? '<a class="axismundi-object__hashtag" href="' . esc_url( $url ) . '" rel="tag">' . esc_html( '#' . $name ) . '</a>'
			: '<span class="axismundi-object__hashtag">' . esc_html( '#' . $name ) . '</span>';
	}
	if ( empty( $items ) ) {
		return '';
	}
	$prefix = trim( (string) ( $attributes['prefix'] ?? '' ) );
	$suffix = trim( (string) ( $attributes['suffix'] ?? '' ) );
	$inner  = '';
	if ( '' !== $prefix ) {
		$inner .= '<span class="wp-block-post-terms__prefix">' . esc_html( $prefix ) . '</span>';
	}
	$inner .= implode( '', $items );
	if ( '' !== $suffix ) {
		$inner .= '<span class="wp-block-post-terms__suffix">' . esc_html( $suffix ) . '</span>';
	}
	// Core's own base class so a theme's Post Terms style variations, including
	// the Tags chip geometry, apply to this sibling without being restated.
	return '<div ' . get_block_wrapper_attributes( array( 'class' => 'wp-block-post-terms axismundi-object__hashtags' ) ) . '>' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Chips and affixes escaped above.
}

/** Render one quoted Object preview from the same neutral model registry. */
function axismundi_op_render_quote_context_block() : string {
	$model = axismundi_op_active_object_view_model();
	$uri   = is_array( $model ) ? (string) ( $model['quote_uri'] ?? '' ) : '';
	if ( '' === $uri || ! function_exists( 'axismundi_op_resolve_source_by_uri' ) ) {
		return '';
	}
	$source = axismundi_op_resolve_source_by_uri( $uri );
	if ( null === $source || ! axismundi_op_object_card_publicly_renderable( $source ) ) {
		return '';
	}
	$quote = axismundi_op_object_view_model( $source );
	if ( ! is_array( $quote ) ) {
		return '';
	}
	if ( 'tombstone' === (string) ( $quote['status'] ?? '' ) ) {
		$body = esc_html__( 'The quoted object has been deleted.', 'axismundi-object-projections' );
	} else {
		$author  = trim( (string) ( $quote['author']['name'] ?? '' ) );
		$excerpt = wp_trim_words( wp_strip_all_tags( (string) ( $quote['content_html'] ?? '' ) ), 40 );
		$body    = ( '' !== $author ? '<strong>' . esc_html( $author ) . '</strong>' : '' )
			. '<div class="axismundi-object__quote-excerpt">' . esc_html( $excerpt ) . '</div>';
	}
	return '<blockquote ' . get_block_wrapper_attributes( array( 'class' => 'axismundi-object__quote' ) ) . '><a href="' . esc_url( $uri ) . '">' . $body . '</a></blockquote>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values escaped above.
}

/** Render the Object-card action row; child blocks own their individual behavior. */
function axismundi_op_render_object_interactions_block( array $attributes, string $content ) : string {
	$model = axismundi_op_active_object_view_model();
	if ( ! is_array( $model ) || ! (bool) axismundi_op_object_template_option( 'interactions', true ) ) {
		return '';
	}
	return '' === trim( $content ) ? '' : '<div ' . get_block_wrapper_attributes( array( 'class' => 'axismundi-object__interactions' ) ) . '>' . $content . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Nested blocks render their own escaped output.
}

/** Register shared block assets before thread/question blocks reference them. */
function axismundi_op_register_object_block_assets() : void {
	$base = dirname( __DIR__ ) . '/axismundi-object-projections.php';
	$js   = dirname( __DIR__ ) . '/assets/object-blocks.js';
	$css  = dirname( __DIR__ ) . '/assets/object-view.css';
	wp_register_script( 'axismundi-op-object-blocks', plugins_url( 'assets/object-blocks.js', $base ), array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n' ), file_exists( $js ) ? (string) filemtime( $js ) : AXISMUNDI_OP_VERSION, true );
	wp_register_style( 'axismundi-op-object-view', plugins_url( 'assets/object-view.css', $base ), array(), file_exists( $css ) ? (string) filemtime( $css ) : AXISMUNDI_OP_VERSION );
}

/**
 * Hand the editor the server's own list of Object blocks.
 *
 * Every server-rendered block also needs a client-side registration or the Site
 * Editor reports it as unsupported. Deriving that list from the block registry
 * instead of repeating it in JavaScript means registering a block on the server
 * is enough: the previous hardcoded list silently desynchronized whenever a
 * block was added on one side only.
 */
function axismundi_op_enqueue_object_block_editor_data() : void {
	$blocks = array();
	foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
		$handles = (array) ( $type->editor_script_handles ?? array() );
		if ( ! in_array( 'axismundi-op-object-blocks', $handles, true ) ) {
			continue;
		}
		$supports        = (array) ( $type->supports ?? array() );
		$blocks[ $name ] = array(
			'apiVersion' => (int) ( $type->api_version ?? 3 ),
			'attributes' => (array) ( $type->attributes ?? array() ),
			'category'   => (string) ( $type->category ?? 'theme' ),
			'label'      => (string) ( $type->title ?? $name ),
			'supports'   => $supports,
		);
	}
	if ( empty( $blocks ) ) {
		return;
	}
	wp_add_inline_script( 'axismundi-op-object-blocks', 'window.axismundiOpObjectBlocks = ' . wp_json_encode( $blocks ) . ';', 'before' );
}
add_action( 'enqueue_block_editor_assets', 'axismundi_op_enqueue_object_block_editor_data', 5 );
add_action( 'init', 'axismundi_op_register_object_block_assets', 5 );

/** Register the shared pattern and its small dynamic block vocabulary. */
function axismundi_op_register_object_blocks() : void {
	$blocks = array(
		'object-status'       => array( 'Object Status', 'axismundi_op_render_object_status_block' ),
		'object-avatar'       => array( 'Legacy Object Actor Avatar', 'axismundi_op_render_object_avatar_block' ),
		'object-identity'     => array( 'Legacy Object Actor Identity', 'axismundi_op_render_object_identity_block' ),
		'object-meta'         => array( 'Object Metadata', 'axismundi_op_render_object_meta_block' ),
		'object-title'        => array( 'Object Title', 'axismundi_op_render_object_title_block' ),
		'object-summary'      => array( 'Object Summary', 'axismundi_op_render_object_summary_block' ),
		'object-attachments'  => array( 'Object Attachments', 'axismundi_op_render_object_attachments_block' ),
		'quote-context'       => array( 'Quote Context', 'axismundi_op_render_quote_context_block' ),
		'object-interactions' => array( 'Object Interactions', 'axismundi_op_render_object_interactions_block' ),
	);
	foreach ( $blocks as $slug => $definition ) {
		register_block_type(
			'axismundi/' . $slug,
			array(
				'api_version'     => 3,
				'title'           => __( $definition[0], 'axismundi-object-projections' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Fixed internal registration map.
				'category'        => 'theme',
				'editor_script'   => 'axismundi-op-object-blocks',
				'style'           => 'axismundi-op-object-view',
				'editor_style'    => 'axismundi-op-object-view',
				'render_callback' => $definition[1],
				'supports'        => array_merge(
					axismundi_op_object_block_supports(),
					array(
						// `object-interactions` is the deprecated name for
						// `object-actions`; it stays registered so a saved template
						// keeps rendering, but out of the inserter.
						'inserter' => ! in_array( $slug, array( 'object-avatar', 'object-identity', 'object-interactions' ), true ),
						'layout'   => 'object-interactions' === $slug,
					)
				),
				'attributes'      => 'object-avatar' === $slug ? array( 'size' => array( 'type' => 'number', 'default' => 48 ) ) : array(),
			)
		);
	}
	// Blocks migrated to `block.json` directories register from their metadata.
	// WordPress then bootstraps one identical definition to the editor, so Core
	// -style supports need no hand-maintained JavaScript copy.
	register_block_type( dirname( __DIR__ ) . '/blocks/object-content' );
	register_block_type( dirname( __DIR__ ) . '/blocks/object-hashtags' );
	register_block_type( dirname( __DIR__ ) . '/blocks/object-actions' );
	register_block_type( dirname( __DIR__ ) . '/blocks/object-featured-image' );
	if ( function_exists( 'register_block_pattern' ) ) {
		register_block_pattern(
			'axismundi/object-card',
			array(
				'title'       => __( 'Axismundi Object Card', 'axismundi-object-projections' ),
				'description' => __( 'A reusable local or remote Note, Question, or Quote composition for single and archive templates.', 'axismundi-object-projections' ),
				'categories'  => array( 'featured' ),
				'content'     => axismundi_op_object_card_pattern_content(),
			)
		);
	}
	if ( function_exists( 'register_block_template' ) ) {
		register_block_template(
			'axismundi-object-projections//single-object',
			array(
				'title'       => __( 'Axismundi Single Object', 'axismundi-object-projections' ),
				'description' => __( 'The canonical standalone view for a local or cached remote Object.', 'axismundi-object-projections' ),
				'content'     => axismundi_op_single_object_template_content(),
			)
		);
		register_block_template(
			'axismundi-object-projections//object-tombstone',
			array(
				'title'       => __( 'Axismundi Object Tombstone', 'axismundi-object-projections' ),
				'description' => __( 'The privacy-minimal 410 view for a deleted local or cached remote Object.', 'axismundi-object-projections' ),
				'content'     => axismundi_op_tombstone_template_content(),
			)
		);
	}
}
add_action( 'init', 'axismundi_op_register_object_blocks', 20 );
