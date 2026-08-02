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
function axismundi_op_object_card_pattern_content( string $slug = 'object-card-default' ) : string {
	static $cache = array();
	$slug = in_array( $slug, array( 'object-card-default', 'object-card-article' ), true )
		? $slug
		: 'object-card-default';
	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}
	/*
	 * One file, two names. The slugs stay because callers and saved references use them, but they
	 * now select a variant of one card rather than two separate documents — the difference between
	 * them shrank to a modifier class once the body moved into its own block.
	 *
	 * The cache is still keyed by slug, which is what keeps that variant from leaking: a feed
	 * renders both kinds, and a cache keyed by anything coarser would hand the second kind
	 * whichever card the first one produced.
	 */
	// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- read by the included template.
	$axismundi_op_card_variant = 'object-card-article' === $slug ? 'article' : 'default';
	$path                      = dirname( __DIR__ ) . '/templates/object-card.php';
	if ( ! is_readable( $path ) ) {
		return '';
	}
	ob_start();
	include $path;
	$cache[ $slug ] = (string) ob_get_clean();
	return $cache[ $slug ];
}

/**
 * The card composition one Object is shown with in a stream.
 *
 * Type selection lives here, in the feed renderer, and nowhere else. An Article's card
 * and a Note's card differ in what they contain rather than in switches they carry, so
 * the choice is made once — before any block renders — instead of every block asking
 * what type it is looking at. The canonical page is a separate concern with its own
 * template, which is what keeps `surface` from multiplying against type.
 *
 * @param array<string,mixed>|null $model Object view model, or null for the current one.
 * @return string Card pattern slug.
 */
function axismundi_op_object_card_slug( ?array $model = null ) : string {
	$model = is_array( $model ) ? $model : axismundi_op_current_object_view_model();
	return is_array( $model ) && 'Article' === (string) ( $model['type'] ?? '' )
		? 'object-card-article'
		: 'object-card-default';
}

/** Read the canonical standalone Object template bundled with OP. */
function axismundi_op_single_object_template_content( string $slug = 'single-object' ) : string {
	$slug = in_array( $slug, array( 'single-object', 'single-object-article', 'single-object-reply' ), true ) ? $slug : 'single-object';
	$path = dirname( __DIR__ ) . '/templates/' . $slug . '.php';
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
 * Wrap an author-edited card body in the frame the Object decides.
 *
 * The frame is not editable and deliberately so: the thread wrapper, the `<article>`, the type
 * modifier and the reply-context above the card are all answers to "which Object is this", and
 * that is known here and nowhere the author is editing. The same reasoning that put the type
 * decision in one place when the two card templates merged applies to a card supplied from
 * outside.
 *
 * @param string $inner Serialized card contents.
 * @return string
 */
function axismundi_op_object_card_frame( string $inner ) : string {
	$model = axismundi_op_current_object_view_model();
	$class = is_array( $model ) && 'Article' === (string) ( $model['type'] ?? '' )
		? 'axismundi-object-card axismundi-object-card--article'
		: 'axismundi-object-card';
	return '<!-- wp:group {"className":"axismundi-object-thread-item","layout":{"type":"constrained"}} -->'
		. '<div class="wp-block-group axismundi-object-thread-item">'
		. '<!-- wp:axismundi/reply-context /-->'
		. '<!-- wp:group {"tagName":"article","className":"' . esc_attr( $class ) . '","layout":{"type":"constrained"}} -->'
		. '<article class="wp-block-group ' . esc_attr( $class ) . '">'
		. $inner
		. '</article><!-- /wp:group --></div><!-- /wp:group -->';
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
	// `surface` is stated rather than inferred. One Object Card pattern assembles the
	// single page, the Actor timeline, and the hashtag archive, so blocks that must
	// behave differently per surface need to ask directly — reading it off `headingTag`
	// or `interactions` would tie a content decision to a typography decision. The
	// default is `single`, which is also what an un-wrapped render (the single Object
	// template, which emits the pattern markup without calling this) correctly sees.
	$GLOBALS['axismundi_op_object_template_options'] = array_merge(
		array( 'headingTag' => 'h1', 'interactions' => true, 'surface' => 'single', 'interactionOwner' => 'block' ),
		$options
	);
	try {
		/*
		 * A caller may supply the card to repeat instead of using the bundled one.
		 *
		 * A feed's cards are edited once in a template and rendered twice — on the first page
		 * while that template is being rendered, and again for every page fetched afterwards,
		 * where there is no template at all. Passing the same saved markup into both is what
		 * keeps the second batch from being a different card than the first.
		 *
		 * What arrives is the card's contents, not its frame: the `<article>`, its type modifier
		 * and the reply-context above it depend on the Object being rendered, and only this
		 * renderer knows which Object that is.
		 */
		$supplied = isset( $options['cardTemplate'] ) ? (string) $options['cardTemplate'] : '';
		if ( '' !== $supplied ) {
			return do_blocks( axismundi_op_object_card_frame( $supplied ) );
		}
		return do_blocks( axismundi_op_object_card_pattern_content( axismundi_op_object_card_slug() ) );
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
function axismundi_op_render_object_tombstone_block() : string {
	$model = axismundi_op_current_object_view_model();
	if ( ! is_array( $model ) || 'tombstone' !== (string) ( $model['status'] ?? '' ) ) {
		return '';
	}
	return '<p ' . get_block_wrapper_attributes( array( 'class' => 'axismundi-object__deleted' ) ) . '>'
		. esc_html__( 'This object has been deleted.', 'axismundi-object-projections' ) . '</p>';
}

/**
 * The row a feed entry opens with to say why it is there — reserved, and empty until it is built.
 *
 * `object-status` has until now rendered the deleted-object notice, which is not what its name
 * says and not what the templates place it for: every card template opens with it, above the
 * identity row, which is where a "boosted", "replied", or "mentioned you" line belongs. A
 * tombstone is a property of the Object, not of why this entry appears in this list, and the two
 * only looked alike because a hidden slot and an empty notice render the same nothing.
 *
 * The notice moved to `object-tombstone`, which the 410 route now places directly. This name is
 * kept registered rather than removed because every saved card template already contains it: a
 * block that stops existing turns those into editor errors, while one that renders nothing looks
 * exactly like what it rendered on a feed card yesterday. A tombstoned Object is never selected
 * into a feed in the first place, so nothing that used to be visible here has been lost.
 */
/**
 * The status row's markup, shared by the block and by the external-reference fallback.
 *
 * An uncached Announce renders as a bare external link built without the card template, so it has
 * no blocks in it at all — and it is the entry that most needs the explanation, because otherwise
 * a link to another site appears on a timeline with nothing saying who put it there.
 *
 * @param array<string,mixed> $status Status descriptor from the selecting product.
 * @param string              $attributes Wrapper attributes, if this is being drawn as a block.
 */
function axismundi_op_object_status_html( array $status, string $attributes = '' ) : string {
	if ( 'announce' !== (string) ( $status['kind'] ?? '' ) ) {
		return '';
	}
	$actor_uri = (string) ( $status['actor_uri'] ?? '' );
	$actor     = '' !== $actor_uri && function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $actor_uri ) : null;
	/*
	 * The name of whoever did it when we hold their record, and the plain verb when we do not.
	 *
	 * A boost is evidence that this Actor acted, and the row is worth drawing on that evidence
	 * alone; an unresolved Actor makes the sentence shorter, not the row absent. Falling back to
	 * the raw URI would put a machine identifier in a sentence about a person.
	 */
	$name  = $actor instanceof Axismundi_Actor ? (string) $actor->get_display_name() : '';
	$name  = '' === $name && $actor instanceof Axismundi_Actor ? (string) $actor->get_preferred_username() : $name;
	$label = '' === $name
		? esc_html__( 'Boosted', 'axismundi-object-projections' )
		/* translators: %s: display name of the Actor who boosted the object. */
		: esc_html( sprintf( __( '%s boosted', 'axismundi-object-projections' ), $name ) );
	return '<p ' . ( '' !== $attributes ? $attributes : 'class="axismundi-object-card__status axismundi-object-card__status--announce"' ) . '>'
		. '<span class="material-symbols-outlined" aria-hidden="true">sync</span> '
		. $label // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		. '</p>';
}

function axismundi_op_render_object_status_block() : string {
	$options = (array) ( $GLOBALS['axismundi_op_object_template_options'] ?? array() );
	$status  = is_array( $options['status'] ?? null ) ? $options['status'] : array();
	return axismundi_op_object_status_html(
		$status,
		get_block_wrapper_attributes( array( 'class' => 'axismundi-object-card__status axismundi-object-card__status--announce' ) )
	);
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
	/*
	 * Someone other than the author, when the surface asked for it.
	 *
	 * On a Person's community surface the page is already that Person's profile, so repeating
	 * their avatar and handle on every row says nothing a reader did not know; which community
	 * the entry went to is what they came to see. The Actor is named by the selecting product and
	 * only used when we hold its record — an unresolved override falls through to the author
	 * rather than emptying the row, because showing the wrong identity is worse than showing the
	 * one that is merely less useful here.
	 */
	$options     = (array) ( $GLOBALS['axismundi_op_object_template_options'] ?? array() );
	$header_uri  = (string) ( $options['headerActor'] ?? '' );
	if ( '' !== $header_uri && function_exists( 'axismundi_actors_get_by_uri' ) && function_exists( 'axismundi_actors_block_subject_from_actor' ) ) {
		$header_actor = axismundi_actors_get_by_uri( $header_uri );
		if ( $header_actor instanceof Axismundi_Actor ) {
			return axismundi_actors_block_subject_from_actor( $header_actor );
		}
	}
	$model  = axismundi_op_active_object_view_model();
	$author = is_array( $model['author'] ?? null ) ? $model['author'] : array();
	if ( empty( $author ) ) {
		return $subject;
	}
	$author_uri = (string) ( $author['id'] ?? '' );
	if ( '' !== $author_uri && function_exists( 'axismundi_actors_get_by_uri' ) && function_exists( 'axismundi_actors_block_subject_from_actor' ) ) {
		$actor = axismundi_actors_get_by_uri( $author_uri );
		if ( $actor instanceof Axismundi_Actor ) {
			return axismundi_actors_block_subject_from_actor( $actor );
		}
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
	return do_blocks( '<!-- wp:axismundi/actor-avatar {"size":' . $size . '} /-->' );
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

/**
 * How each audience is drawn, and what it is called.
 *
 * Audience is not the same question as where something was posted. An Object addressed to a
 * community and an Object addressed to followers are answers to different questions, and a
 * Topic in a members-only community is both at once — so a community marker is a separate
 * signal beside this one, owned by whichever product knows what a community is, and never a
 * fifth value here.
 *
 * `unlisted` is drawn as a house rather than a lock because it is not a restriction: the
 * permalink is public and anyone may read it, and what is withheld is only its appearance in
 * public timelines and search. This matches what Mastodon calls Quiet public and what Misskey
 * calls Home. Both, notably, keep suppressing *outbound federation* as a separate switch, which
 * is why there is no glyph for it here — we do not have that switch, and drawing one would
 * promise a reach we do not control.
 *
 * `limited` is the honest gap: an Object known to be non-public whose exact audience we cannot
 * prove, because the author's followers collection was never observed. It is deliberately not
 * drawn as a lock, which would claim followers-only.
 *
 * @return array<string,array{icon:string,label:string}>
 */
function axismundi_op_object_visibility_vocabulary() : array {
	return array(
		'public'    => array( 'icon' => 'public', 'label' => __( 'Public', 'axismundi-object-projections' ) ),
		'unlisted'  => array( 'icon' => 'home', 'label' => __( 'Quiet public', 'axismundi-object-projections' ) ),
		'followers' => array( 'icon' => 'lock', 'label' => __( 'Followers only', 'axismundi-object-projections' ) ),
		'mentioned' => array( 'icon' => 'alternate_email', 'label' => __( 'Mentioned people only', 'axismundi-object-projections' ) ),
		'limited'   => array( 'icon' => 'private_connectivity', 'label' => __( 'Restricted', 'axismundi-object-projections' ) ),
	);
}

/**
 * Whether the audience marker is shown to readers at all.
 *
 * Off, and off deliberately. The five audiences are a real and necessary *internal* model — a
 * remote Object arrives addressed some way and we have to read that correctly to decide who may
 * see it — but they are not yet a feature, because nothing on the writing side offers an author a
 * choice among them and no delivery rule is closed behind one.
 *
 * Marking a card with an audience the author could not choose, and that nothing enforces, states a
 * promise the product does not keep. `home` is the sharpest case: it reads as "local only" while
 * meaning nothing of the sort — the permalink stays public and anyone may fetch it. Real local-only
 * is not a shape of `to`/`cc` at all; it is a delivery policy about which bridges an object is
 * never handed to, and Lemmy models it as a property of a community rather than a per-post
 * audience. Until that policy exists there is nothing honest for this marker to say.
 *
 * The block stays registered so the work can be finished and looked at, and the model keeps being
 * resolved because access control needs it either way.
 */
function axismundi_op_object_visibility_block_enabled() : bool {
	/** @param bool $enabled Whether to draw the audience marker on cards. */
	return (bool) apply_filters( 'axismundi_op_object_visibility_marker_enabled', false );
}

/** Render the audience one Object was addressed to. */
function axismundi_op_render_object_visibility_block( array $attributes = array() ) : string {
	if ( ! axismundi_op_object_visibility_block_enabled() ) {
		return '';
	}
	$model = axismundi_op_active_object_view_model();
	if ( ! is_array( $model ) ) {
		return '';
	}
	$level      = (string) ( $model['visibility']['level'] ?? '' );
	$vocabulary = axismundi_op_object_visibility_vocabulary();
	if ( ! isset( $vocabulary[ $level ] ) ) {
		// An Object whose audience was never resolved says nothing, rather than defaulting to
		// the friendliest value on the list.
		return '';
	}
	$entry = $vocabulary[ $level ];
	$label = (string) $entry['label'];
	/*
	 * The glyph is decorative and the name is the content. A reader using a screen reader gets
	 * the name either way — putting it on screen is a display choice, not an accessibility one.
	 */
	$inner = '<span class="material-symbols-outlined" aria-hidden="true">' . esc_html( (string) $entry['icon'] ) . '</span>';
	$inner .= empty( $attributes['showLabel'] )
		? '<span class="screen-reader-text">' . esc_html( $label ) . '</span>'
		: '<span class="axismundi-object__visibility-label">' . esc_html( $label ) . '</span>';

	return '<span ' . get_block_wrapper_attributes(
		array(
			'class'            => 'axismundi-object__visibility',
			'data-visibility'  => $level,
		)
	) . '>' . $inner . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Glyph and label escaped above.
}

/** Render one Object publication or modification date. */
function axismundi_op_render_object_date_block( array $attributes = array() ) : string {
	$model = axismundi_op_active_object_view_model();
	if ( ! is_array( $model ) ) {
		return '';
	}
	$field = 'updated' === (string) ( $attributes['field'] ?? 'published' ) ? 'updated' : 'published';
	$value = trim( (string) ( $model[ $field ] ?? '' ) );
	$time  = '' !== $value ? strtotime( $value ) : false;
	if ( false === $time ) {
		return '';
	}
	$format = trim( (string) ( $attributes['format'] ?? '' ) );
	$format = '' !== $format ? $format : (string) get_option( 'date_format' );
	$inner  = esc_html( wp_date( $format, $time ) );
	if ( ! empty( $attributes['isLink'] ) ) {
		$url = trim( (string) ( $model['human_url'] ?? '' ) );
		if ( '' !== $url ) {
			$inner = '<a href="' . esc_url( $url ) . '">' . $inner . '</a>';
		}
	}
	$class = 'wp-block-post-date axismundi-object__date';
	if ( 'updated' === $field ) {
		$class .= ' wp-block-post-date__modified-date';
	}
	return '<time ' . get_block_wrapper_attributes( array( 'class' => $class, 'datetime' => gmdate( 'c', $time ) ) ) . '>' . $inner . '</time>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Date and optional link are escaped above.
}

/** Render the ActivityStreams type, optionally as a heading. */
function axismundi_op_render_object_type_block( array $attributes = array() ) : string {
	$model = axismundi_op_active_object_view_model();
	$type  = is_array( $model ) ? sanitize_text_field( (string) ( $model['type'] ?? '' ) ) : '';
	if ( '' === $type ) {
		return '';
	}
	$level = isset( $attributes['level'] ) ? (int) $attributes['level'] : 0;
	$tag   = $level >= 1 && $level <= 6 ? 'h' . $level : 'span';
	return '<' . $tag . ' ' . get_block_wrapper_attributes( array( 'class' => 'wp-block-query-title axismundi-object__type' ) ) . '>' . esc_html( $type ) . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Type and wrapper attributes are escaped above.
}

/** Render the optional authored title. */
function axismundi_op_render_object_title_block( array $attributes = array() ) : string {
	$model = axismundi_op_active_object_view_model();
	$title = is_array( $model ) ? trim( (string) ( $model['title'] ?? '' ) ) : '';
	if ( '' === $title ) {
		return '';
	}
	$level = isset( $attributes['level'] ) ? (int) $attributes['level'] : 0;
	$tag   = $level >= 1 && $level <= 6
		? 'h' . $level
		: (string) axismundi_op_object_template_option( 'headingTag', 'h1' );
	$tag = in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ? $tag : 'h1';

	$inner = esc_html( $title );
	if ( ! empty( $attributes['isLink'] ) ) {
		$url = is_array( $model ) ? trim( (string) ( $model['human_url'] ?? '' ) ) : '';
		if ( '' !== $url ) {
			$target = '_blank' === (string) ( $attributes['linkTarget'] ?? '_self' ) ? '_blank' : '_self';
			$rel    = sanitize_text_field( (string) ( $attributes['rel'] ?? '' ) );
			if ( '_blank' === $target ) {
				$rel = trim( $rel . ' noreferrer noopener' );
			}
			$inner = '<a href="' . esc_url( $url ) . '" target="' . esc_attr( $target ) . '"'
				. ( '' !== $rel ? ' rel="' . esc_attr( $rel ) . '"' : '' ) . '>' . $inner . '</a>';
		}
	}
	return '<' . $tag . ' ' . get_block_wrapper_attributes( array( 'class' => 'wp-block-post-title axismundi-object__title' ) ) . '>' . $inner . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Title and optional URL are escaped above.
}

/**
 * Build a safe, useful label for a sensitive Object.
 *
 * FEP-b2b8 recommends `dcterms:subject`, then hashtags, name, and only finally
 * summary. The local Article adapter maps `dcterms:subject` to content_warning.
 */
function axismundi_op_sensitive_content_label( array $model ) : string {
	$subject = trim( (string) ( $model['content_warning'] ?? '' ) );
	if ( '' !== $subject ) {
		return $subject;
	}
	foreach ( (array) ( $model['hashtags'] ?? array() ) as $tag ) {
		$name = trim( (string) ( is_array( $tag ) ? ( $tag['name'] ?? '' ) : $tag ) );
		if ( '' !== $name ) {
			return '#' . ltrim( $name, '#' );
		}
	}
	foreach ( array( 'title', 'summary' ) as $field ) {
		$value = trim( (string) ( $model[ $field ] ?? '' ) );
		if ( '' !== $value ) {
			return $value;
		}
	}
	return __( 'Sensitive content', 'axismundi-object-projections' );
}

/**
 * Fold everything an authored warning covers behind one disclosure.
 *
 * Mastodon and Misskey hide a warned post as a unit: body, poll, quote preview, and
 * attachments all sit behind the same "Show more". Gating only the body would leave the
 * poll question, the quoted post, and the filenames legible beside a closed warning, so
 * the parts have to share one gate rather than each carrying its own.
 *
 * Inner blocks arrive already rendered, and the children that would otherwise gate
 * themselves stand down through the `axismundi/objectDisclosure` context this block
 * provides. Attachments are the deliberate exception: opening a post warning does not
 * clear a per-file `sensitive` flag, so a revealed post can still hold blurred media —
 * which is exactly how both peers behave.
 *
 * @param array<string,mixed> $attributes Block attributes.
 * @param string              $content    Rendered inner blocks.
 * @return string
 */
function axismundi_op_render_object_content_warning_block( array $attributes = array(), string $content = '' ) : string {
	unset( $attributes );
	if ( '' === trim( $content ) ) {
		return '';
	}
	$model = axismundi_op_active_object_view_model();
	if ( ! is_array( $model ) || ! axismundi_op_object_folds_behind_warning( $model ) ) {
		// Nothing to fold: the wrapper must be invisible rather than an empty box, so its
		// children keep the card's own spacing and layout.
		return $content;
	}
	return '<details ' . get_block_wrapper_attributes( array( 'class' => 'axismundi-object__sensitive axismundi-object__content-warning' ) ) . '>'
		. '<summary>' . esc_html( axismundi_op_sensitive_content_label( $model ) ) . '</summary>'
		. '<div class="axismundi-object__content-warning-body">' . $content . '</div>'
		. '</details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Label escaped; inner blocks rendered by Core.
}

/**
 * Whether an Object folds behind a post-level content warning.
 *
 * `sensitive` alone does not fold a post. On Mastodon and Misskey the two flags answer
 * different questions: an authored warning (`summary` / `cw`) hides the post until the
 * reader opens it, while `sensitive` marks the media. Posting a blurred photo with no
 * warning text sets `sensitive: true` and leaves the text readable — folding there would
 * hide writing the author chose to show. A warning is therefore only a warning when the
 * author actually wrote one.
 *
 * The raw `content_warning` is deliberate: `axismundi_op_sensitive_content_label()` falls
 * back to a hashtag or title so a gate always has *something* to display, but an inferred
 * label is not an authored warning and must not decide whether to fold.
 *
 * @param array<string,mixed> $model Object view model.
 * @return bool
 */
function axismundi_op_object_has_content_warning( array $model ) : bool {
	return ! empty( $model['sensitive'] )
		&& '' !== trim( wp_strip_all_tags( (string) ( $model['content_warning'] ?? '' ) ) );
}

/**
 * Whether an Object's body folds behind the post-level disclosure.
 *
 * The two Object shapes protect different things, so they cannot share one rule:
 *
 *   Note / Question  the body IS the post, so it folds only when the author wrote a
 *                    warning. Media marked sensitive with no warning text blurs the
 *                    attachments and leaves the writing readable.
 *   Article          the body is not in the stream at all; its summary carries the
 *                    protection, and `object-summary` obscures that in place.
 *
 * An Article therefore never uses this fold. Applying the Note rule to one would cover
 * a body the reader reached deliberately through "Read more", which is the moment the
 * warning has already been answered.
 *
 * @param array<string,mixed> $model Object view model.
 * @return bool
 */
function axismundi_op_object_folds_behind_warning( array $model ) : bool {
	if ( 'Article' === (string) ( $model['type'] ?? '' ) ) {
		return false;
	}
	return axismundi_op_object_has_content_warning( $model );
}

/**
 * Render content with the authored sensitive-content gate.
 *
 * @param array<string,mixed> $attributes Block attributes.
 * @param bool                $delegated  Whether an enclosing wrapper already owns the
 *                                        post-level warning for this card.
 * @return string
 */
/**
 * The Object body, sanitized and then decorated.
 *
 * One function because the body appears in more than one place — the card and the media
 * dialog — and those must not drift apart: a decoration applied to only one of them
 * would make the same post read differently depending on where a reader met it.
 *
 * The order is fixed. Sanitizing first and decorating after is the only arrangement
 * that is safe, because a decorator emits markup: run it before `wp_kses_post()` and
 * that markup is either stripped or the sanitizer has to be widened to admit it, which
 * would admit the same tags from the remote author too.
 *
 * @param array<string,mixed> $model Object view model.
 * @return string
 */
function axismundi_op_object_body_html( array $model ) : string {
	$body = wp_kses_post( (string) ( $model['content_html'] ?? '' ) );

	/**
	 * Decorate the sanitized Object body. Hooked code must touch text nodes only.
	 *
	 * @param string              $body  Sanitized body HTML.
	 * @param array<string,mixed> $model Object view model.
	 */
	return (string) apply_filters( 'axismundi_op_object_content_html', $body, $model );
}

function axismundi_op_render_object_content_block( array $attributes = array(), bool $delegated = false ) : string {
	$model = axismundi_op_active_object_view_model();
	if ( ! is_array( $model ) ) {
		return '';
	}

	// An Article carries a summary for streams and a body for its own page. When the
	// author has separated the two, a timeline that prints both says everything twice,
	// so the body can stand down there and the summary's "Read article" link leads on.
	//
	// Restricted to Article on purpose. A summary means something different on a Note:
	// it is a preview or a content warning, and the body IS the post — there is no
	// separate page to send the reader to. Keying only on "a summary exists" would make
	// the body of every summarised Note disappear from timelines the moment Notes can
	// carry one, which is a change already in motion.
	if ( ! empty( $attributes['hideInFeed'] )
		&& 'Article' === (string) ( $model['type'] ?? '' )
		&& 'feed' === (string) axismundi_op_object_template_option( 'surface', 'single' )
		&& '' !== trim( wp_strip_all_tags( (string) ( $model['summary'] ?? '' ) ) )
	) {
		return '';
	}

	$body = axismundi_op_object_body_html( $model );

	// A post-level warning covers the body, the poll, the quote, and the attachments
	// together, so `axismundi/object-content-warning` owns it whenever the card uses that
	// wrapper. This block only gates itself when nothing above it is doing so — which
	// keeps a template that places `object-content` on its own from leaking a warned body.
	if ( empty( $delegated ) && axismundi_op_object_folds_behind_warning( $model ) ) {
		$summary = esc_html( axismundi_op_sensitive_content_label( $model ) );
		return '<details ' . get_block_wrapper_attributes( array( 'class' => 'axismundi-object__sensitive' ) ) . '><summary>' . $summary . '</summary><div class="axismundi-object__content">' . $body . '</div></details>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Body sanitized above.
	}
	$tag = (string) ( $attributes['tagName'] ?? 'div' );
	$tag = in_array( $tag, array( 'article', 'div', 'main', 'section' ), true ) ? $tag : 'div';
	return '<' . $tag . ' ' . get_block_wrapper_attributes( array( 'class' => 'axismundi-object__content' ) ) . '>' . $body . '</' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Body sanitized above.
}

/**
 * The side-panel payload the media dialog shows beside an Object's attachments.
 *
 * Object Projections owns this markup because it is a projection of the Object, not
 * dialog chrome: identity, body, canonical link, and the single `inReplyTo` ancestor.
 * The dialog plugin owns the surface that displays it and never reaches into the view
 * model itself. It ships inside a `<template>`, so it costs no layout or accessibility
 * weight until a dialog clones it.
 *
 * @param array<string,mixed> $model Enriched Object view model.
 * @return string
 */
function axismundi_op_render_object_media_panel( array $model ) : string {
	$author = (array) ( $model['author'] ?? array() );
	$parts  = array();

	$name   = trim( (string) ( $author['name'] ?? '' ) );
	$handle = trim( (string) ( $author['handle'] ?? '' ) );
	if ( '' !== $name || '' !== $handle ) {
		$identity = '';
		$avatar   = (string) ( $author['avatar_url'] ?? '' );
		if ( '' !== $avatar ) {
			$identity .= '<img class="axismundi-object__media-panel-avatar" src="' . esc_url( $avatar ) . '" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" />';
		}
		$identity .= '<span class="axismundi-object__media-panel-names">';
		if ( '' !== $name ) {
			$identity .= '<strong class="axismundi-object__media-panel-name">' . esc_html( $name ) . '</strong>';
		}
		if ( '' !== $handle ) {
			$identity .= '<span class="axismundi-object__media-panel-handle">' . esc_html( $handle ) . '</span>';
		}
		$identity .= '</span>';

		$author_url = (string) ( $author['url'] ?? '' );
		$parts[]    = '' !== $author_url
			? '<a class="axismundi-object__media-panel-author" href="' . esc_url( $author_url ) . '">' . $identity . '</a>'
			: '<p class="axismundi-object__media-panel-author">' . $identity . '</p>';
	}

	$title = trim( (string) ( $model['title'] ?? '' ) );
	if ( '' !== $title ) {
		$parts[] = '<h2 class="axismundi-object__media-panel-title">' . esc_html( $title ) . '</h2>';
	}

	// The Object's own content warning still governs its body here: the dialog is
	// another place the body is displayed, not a way around the warning.
	$body = axismundi_op_object_body_html( $model );
	if ( '' !== trim( wp_strip_all_tags( $body ) ) ) {
		if ( ! empty( $model['sensitive'] ) ) {
			$parts[] = '<details class="axismundi-object__media-panel-body axismundi-object__sensitive"><summary>'
				. esc_html( axismundi_op_sensitive_content_label( $model ) )
				. '</summary>' . $body . '</details>';
		} else {
			$parts[] = '<div class="axismundi-object__media-panel-body">' . $body . '</div>';
		}
	}

	$human_url = (string) ( $model['human_url'] ?? '' );
	if ( '' !== $human_url ) {
		$parts[] = '<p class="axismundi-object__media-panel-permalink"><a href="' . esc_url( $human_url ) . '">'
			. esc_html__( 'Open the original post', 'axismundi-object-projections' ) . '</a></p>';
	}

	// reply_context is exactly one ancestor by contract. The whole conversation lives on
	// the parent's own page, which is what its link is for -- a media dialog is the wrong
	// place to render a thread.
	$reply = $model['reply_context'] ?? null;
	if ( is_array( $reply ) ) {
		$reply_parts = array( '<p class="axismundi-object__reply-context-label">' . esc_html__( 'In reply to', 'axismundi-object-projections' ) . '</p>' );
		if ( ! empty( $reply['available'] ) ) {
			$reply_author = (array) ( $reply['author'] ?? array() );
			$reply_name   = trim( (string) ( $reply_author['name'] ?? '' ) );
			$reply_handle = trim( (string) ( $reply_author['handle'] ?? '' ) );
			if ( '' !== $reply_name || '' !== $reply_handle ) {
				$reply_parts[] = '<p class="axismundi-object__reply-context-author">' . esc_html( '' !== $reply_name ? $reply_name : $reply_handle ) . '</p>';
			}
			$excerpt = trim( (string) ( $reply['excerpt'] ?? '' ) );
			if ( '' !== $excerpt ) {
				$reply_parts[] = '<p class="axismundi-object__reply-context-excerpt">' . esc_html( $excerpt ) . '</p>';
			} elseif ( ! empty( $reply['sensitive'] ) ) {
				$reply_parts[] = '<p class="axismundi-object__reply-context-warning">'
					. esc_html( axismundi_op_sensitive_content_label( $reply ) ) . '</p>';
			}
		} else {
			$reply_parts[] = '<p class="axismundi-object__reply-context-excerpt">' . esc_html__( 'This post is not available here.', 'axismundi-object-projections' ) . '</p>';
		}
		$reply_url = (string) ( $reply['url'] ?? '' );
		if ( '' !== $reply_url ) {
			$reply_parts[] = '<p class="axismundi-object__reply-context-link"><a href="' . esc_url( $reply_url ) . '">'
				. esc_html__( 'View the post being replied to', 'axismundi-object-projections' ) . '</a></p>';
		}
		$parts[] = '<div class="axismundi-object__reply-context">' . implode( '', $reply_parts ) . '</div>';
	}

	return implode( '', $parts );
}

/**
 * Put a "+N more" badge inside one already-rendered figure.
 *
 * A real element rather than a `::after`: the figure's pseudo-elements are contested
 * territory (Core Gallery and the block editor both style them), and the sensitive
 * overlay already sits in this figure. An explicit child cannot collide with either.
 *
 * @param string $figure    Rendered figure markup.
 * @param int    $remaining Attachments beyond the preview limit.
 * @return string
 */
function axismundi_op_append_attachment_overflow_badge( string $figure, int $remaining ) : string {
	if ( $remaining < 1 ) {
		return $figure;
	}
	$close = strrpos( $figure, '</figure>' );
	if ( false === $close ) {
		return $figure;
	}
	$badge = '<span class="axismundi-object__attachment-more" aria-hidden="true">+' . (int) $remaining . '</span>';
	return substr_replace( $figure, $badge, $close, 0 );
}

/** Render normalized Object attachments. */
function axismundi_op_render_object_attachments_block( array $attributes = array() ) : string {
	$model = axismundi_op_active_object_view_model();
	if ( ! is_array( $model ) || 'tombstone' === (string) ( $model['status'] ?? '' ) ) {
		return '';
	}

	// The classifier already decided which attachments are visual and where they sit;
	// this block renders those placements. `downloads` stays out (a non-visual file is
	// a link, not a gallery item) and `inline_refs` are already placed by the body.
	$media = (array) ( $model['media'] ?? array() );
	$items = array_merge(
		array_values( (array) ( $media['before_content'] ?? array() ) ),
		array_values( (array) ( $media['after_content'] ?? array() ) )
	);
	if ( empty( $items ) ) {
		return '';
	}

	$mode    = 'carousel' === (string) ( $attributes['displayMode'] ?? 'gallery' ) ? 'carousel' : 'gallery';
	$columns = isset( $attributes['columns'] ) ? (int) $attributes['columns'] : 2;
	$columns = $columns >= 1 && $columns <= 8 ? $columns : 2;
	$crop    = ! isset( $attributes['imageCrop'] ) || (bool) $attributes['imageCrop'];
	$ratio   = (string) ( $attributes['aspectRatio'] ?? '5/3' );
	$link_to = 'media' === (string) ( $attributes['linkTo'] ?? 'none' ) ? 'media' : 'none';
	$target  = '_self' === (string) ( $attributes['linkTarget'] ?? '_blank' ) ? '_self' : '_blank';

	// `$figures` is every item in document order (the gallery). `$carousel_figures` is the
	// subset that forms the media sequence, and `$aside_figures` is what renders outside
	// it — see the sequence note in the loop.
	$figures          = array();
	$carousel_figures = array();
	$aside_figures    = array();
	foreach ( $items as $descriptor ) {
		if ( ! is_array( $descriptor ) ) {
			continue;
		}
		$href = esc_url_raw( trim( axismundi_op_attachment_href( $descriptor ) ) );
		if ( '' === $href ) {
			continue;
		}

		$mime = strtolower( (string) ( $descriptor['mediaType'] ?? '' ) );
		$kind = strtolower( (string) ( $descriptor['type'] ?? '' ) );
		$alt  = sanitize_text_field( wp_strip_all_tags( (string) ( $descriptor['name'] ?? '' ) ) );

		// Dimensions may sit on the descriptor or on the Link it advertised; both reach
		// the markup so the grid reserves space before a remote image loads.
		$link = array();
		$urls = $descriptor['url'] ?? array();
		$urls = is_array( $urls ) && array_is_list( $urls ) ? $urls : array( $urls );
		foreach ( $urls as $candidate ) {
			if ( is_array( $candidate ) && $href === esc_url_raw( trim( (string) ( $candidate['href'] ?? '' ) ) ) ) {
				$link = $candidate;
				break;
			}
		}
		$width  = max( 0, (int) ( $descriptor['width'] ?? $link['width'] ?? 0 ) );
		$height = max( 0, (int) ( $descriptor['height'] ?? $link['height'] ?? 0 ) );

		// A fixed ratio is applied to the media element itself rather than through a new
		// stylesheet, so this block adds no CSS of its own to Core's gallery layout.
		$media_style = '';
		if ( '' !== $ratio && 'auto' !== $ratio ) {
			$media_style = ' style="aspect-ratio:' . esc_attr( $ratio ) . ';object-fit:' . ( $crop ? 'cover' : 'contain' ) . '"';
		}

		// A peer states the concrete media type; `type` is often the generic `Document`
		// (Misskey does exactly this), so the MIME decides how an item renders.
		$is_image = false;
		$is_audio = false;
		if ( str_starts_with( $mime, 'video/' ) || 'video' === $kind ) {
			// NON-STANDARD: Core Gallery admits only images. Dropping a peer's video
			// would lose sent content, so non-image visual media renders as its own
			// item inside the same grid instead of disappearing.
			$body = '<video class="axismundi-object__attachment-media" controls preload="metadata" src="' . esc_url( $href ) . '"' . $media_style . '></video>';
		} elseif ( str_starts_with( $mime, 'audio/' ) || 'audio' === $kind ) {
			$is_audio = true;
			$body     = '<audio class="axismundi-object__attachment-media" controls preload="metadata" src="' . esc_url( $href ) . '"></audio>';
		} else {
			$is_image = true;
			// Remote media is hot-linked on purpose: binary caching is a later increment.
			// Hot-linking discloses the viewer's IP to the origin host, so no referrer
			// is sent with the request.
			$body = '<img class="axismundi-object__attachment-media" src="' . esc_url( $href ) . '" alt="' . esc_attr( $alt ) . '"'
				. ( $width > 0 ? ' width="' . $width . '"' : '' )
				. ( $height > 0 ? ' height="' . $height . '"' : '' )
				. ' loading="lazy" decoding="async" referrerpolicy="no-referrer"' . $media_style . ' />';
			if ( 'media' === $link_to ) {
				$body = '<a href="' . esc_url( $href ) . '" target="' . esc_attr( $target ) . '"'
					. ( '_blank' === $target ? ' rel="noreferrer noopener"' : '' ) . '>' . $body . '</a>';
			}
		}

		// The dialog opener is an absolutely-positioned button over the image rather than
		// a wrapper around it, mirroring how Core attaches its own lightbox trigger: a
		// wrapping element would change the figure's child structure and Core Gallery
		// sizes those children. A real button keeps the surface keyboard-reachable and
		// announced, which a click handler bound to the figure would not.
		//
		// It is added only for images (a video needs its own controls), and it is added
		// before the sensitive overlay below, so a gated item's overlay paints on top and
		// intercepts the click — media stays unopenable until the viewer reveals it.
		// Audio is deliberately not part of the media sequence. A carousel is a way to
		// look through things, and there is nothing to look at in an audio player —
		// swiping past one, or landing on it from a photo, is not navigation. It still
		// renders (dropping a peer's audio would lose sent content), just outside the
		// sequence. `data-ax-media-index` is what marks sequence membership, so the
		// dialog's clone filter inherits this rule without repeating it.
		$in_sequence = ! $is_audio;
		$slide_index = count( $carousel_figures );
		if ( $in_sequence && $is_image && 'media' !== $link_to ) {
			$body .= '<button type="button" class="axismundi-object__attachment-open" data-ax-media-open="' . (int) $slide_index . '" aria-label="'
				. esc_attr( '' !== $alt ? $alt : __( 'View media', 'axismundi-object-projections' ) ) . '"></button>';
		}

		if ( '' !== $alt ) {
			$body .= '<figcaption class="wp-element-caption">' . esc_html( $alt ) . '</figcaption>';
		}
		$figure = '<figure class="wp-block-image"'
			. ( $in_sequence ? ' data-ax-media-index="' . (int) $slide_index . '"' : ' data-ax-media-aside="true"' )
			. '>' . $body . '</figure>';

		// Sensitivity is resolved per attachment against the Object, then handed to the
		// Media Library's reveal overlay so a viewer never meets two different warning
		// treatments on one page. That helper decorates this figure in place rather than
		// wrapping it, which is what keeps the item inside Core's gallery grid. Without
		// the plugin the media renders unwrapped: this block owns placement, not the
		// content-warning UI.
		if ( axismundi_op_attachment_is_sensitive( $descriptor, $model )
			&& function_exists( 'axismundi_media_sensitive_overlay_with_warning' )
		) {
			$figure = axismundi_media_sensitive_overlay_with_warning(
				$figure,
				axismundi_op_attachment_warning_text( $descriptor, $model )
			);
		}
		$figures[] = $figure;
		if ( $in_sequence ) {
			$carousel_figures[] = $figure;
		} else {
			$aside_figures[] = $figure;
		}
	}

	if ( empty( $figures ) ) {
		return '';
	}

	// What the block hands the media dialog: which Object these attachments belong to,
	// and the Object's side panel. The dialog surface itself belongs to Axismundi
	// Dialogs; this block only publishes the data and the open affordances, so the
	// attachments render identically whether or not a dialog is on the page.
	$object_uri = (string) ( $model['object_uri'] ?? $model['id'] ?? '' );
	$panel      = axismundi_op_render_object_media_panel( $model );
	$panel_html = '' === $panel
		? ''
		: '<template class="axismundi-object__media-panel-data">' . $panel . '</template>';
	$dialog_data = array(
		'data-ax-object-media' => 'true',
		'data-ax-object-uri'   => $object_uri,
	);

	// Carousel is this block's own presentation, so it deliberately does NOT carry
	// Core's gallery grid classes: those exist to drive the grid, and inheriting them
	// would leave Core sizing items that a track is already positioning.
	if ( 'carousel' === $mode ) {
		$total  = count( $carousel_figures );
		$slides = array();
		foreach ( $carousel_figures as $index => $figure ) {
			$slides[] = sprintf(
				'<div class="axismundi-object__carousel-slide" role="group" aria-roledescription="%1$s" aria-label="%2$s">%3$s</div>',
				esc_attr__( 'slide', 'axismundi-object-projections' ),
				/* translators: 1: slide number, 2: total slides. */
				esc_attr( sprintf( __( '%1$d of %2$d', 'axismundi-object-projections' ), $index + 1, $total ) ),
				$figure
			);
		}

		$controls = '';
		if ( $total > 1 ) {
			$dots = '';
			for ( $i = 0; $i < $total; $i++ ) {
				$dots .= sprintf(
					'<button type="button" class="axismundi-object__carousel-dot%1$s" aria-label="%2$s"%3$s></button>',
					0 === $i ? ' is-active' : '',
					/* translators: %d: slide number. */
					esc_attr( sprintf( __( 'Go to slide %d', 'axismundi-object-projections' ), $i + 1 ) ),
					0 === $i ? ' aria-current="true"' : ''
				);
			}
			$controls = '<button type="button" class="axismundi-object__carousel-nav axismundi-object__carousel-nav--prev" aria-label="' . esc_attr__( 'Previous media', 'axismundi-object-projections' ) . '"></button>'
				. '<button type="button" class="axismundi-object__carousel-nav axismundi-object__carousel-nav--next" aria-label="' . esc_attr__( 'Next media', 'axismundi-object-projections' ) . '"></button>'
				. '<div class="axismundi-object__carousel-dots">' . $dots . '</div>';
		}

		$carousel = empty( $slides ) ? '' : sprintf(
			'<div class="axismundi-object__carousel" data-ax-carousel role="group" aria-roledescription="%1$s" aria-label="%2$s">'
				. '<div class="axismundi-object__carousel-viewport"><div class="axismundi-object__carousel-track">%3$s</div></div>%4$s</div>',
			esc_attr__( 'carousel', 'axismundi-object-projections' ),
			esc_attr__( 'Attached media', 'axismundi-object-projections' ),
			implode( '', $slides ),
			$controls
		);

		// Whatever the sequence excluded still renders, below the carousel, so an Object
		// that arrived with audio does not quietly lose it when the view is switched.
		$aside = empty( $aside_figures )
			? ''
			: '<div class="axismundi-object__attachments-aside">' . implode( '', $aside_figures ) . '</div>';

		$wrapper = get_block_wrapper_attributes(
			array_merge( array( 'class' => 'axismundi-object__attachments is-mode-carousel' ), $dialog_data )
		);
		return '<figure ' . $wrapper . '>' . $carousel . $aside . $panel_html . '</figure>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapper from Core; every child escaped above.
	}

	// A preview limit hides the overflow rather than dropping it. The media dialog builds
	// its carousel by cloning this block's figures, so an Object with six attachments
	// previewing four must still carry all six in the DOM — otherwise opening the fourth
	// would silently lose the last two. The count badge goes on the last visible tile.
	$preview = isset( $attributes['previewCount'] ) ? (int) $attributes['previewCount'] : 4;
	$shown   = count( $figures );
	if ( $preview > 0 && $shown > $preview ) {
		$figures[ $preview - 1 ] = axismundi_op_append_attachment_overflow_badge(
			$figures[ $preview - 1 ],
			$shown - $preview
		);
		for ( $i = $preview; $i < $shown; $i++ ) {
			$figures[ $i ] = str_replace(
				'<figure class="wp-block-image',
				'<figure class="wp-block-image is-preview-overflow',
				$figures[ $i ]
			);
		}
	}

	// `wp-block-gallery` has to be stated explicitly: Core derives it from the block
	// name, and this block's own generated class is
	// `wp-block-axismundi-object-attachments`. Carrying both is what lets Core's
	// stylesheet lay out the grid unmodified.
	$classes = 'wp-block-gallery has-nested-images is-mode-gallery';
	$classes .= $columns > 0 ? ' columns-' . $columns : ' columns-default';
	if ( $crop ) {
		$classes .= ' is-cropped';
	}

	// Core sizes each item with `calc(50% - var(--wp--style--unstable-gallery-gap)/2)`
	// while the flex container's real spacing comes from block gap. If the two ever
	// disagree the row overflows, wraps, and `flex-grow` stretches every item to full
	// width -- a two-column gallery silently becomes one column. Core keeps them in
	// step by emitting the variable from its own render; binding the variable to the
	// same block-gap value the layout uses makes disagreement impossible.
	$gap = $attributes['style']['spacing']['blockGap'] ?? null;
	$gap = is_array( $gap ) ? ( $gap['left'] ?? $gap['top'] ?? null ) : $gap;
	$gap = is_string( $gap ) && str_starts_with( $gap, 'var:preset|spacing|' )
		? 'var(--wp--preset--spacing--' . sanitize_html_class( substr( $gap, strlen( 'var:preset|spacing|' ) ) ) . ')'
		: ( is_string( $gap ) && '' !== $gap ? $gap : 'var(--wp--style--block-gap, 0.5em)' );

	$wrapper = get_block_wrapper_attributes(
		array_merge(
			array(
				'class' => $classes,
				'style' => '--wp--style--unstable-gallery-gap:' . $gap . ';gap:' . $gap,
			),
			$dialog_data
		)
	);
	return '<figure ' . $wrapper . '>' . implode( '', $figures ) . $panel_html . '</figure>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapper from Core; every child escaped above.
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
		'warning'   => axismundi_op_sensitive_content_label( $model ),
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
/**
 * The one part of a card that depends on what kind of Object it is.
 *
 * Everything else a card is made of — status, header, hashtags, reactions, the action row — is
 * the same whatever is inside it, and belongs in a template an author can rearrange. The body is
 * not: a Note *is* its text, so the card shows the text; an Article is a piece the card is only
 * pointing at, so the card shows a lead image, a title and a summary and stops.
 *
 * That difference cannot be a static block tree, because one feed mixes both and the choice is
 * only knowable per row at render time. Hence one dynamic block here rather than two card
 * templates that are otherwise identical.
 *
 * It renders blocks rather than markup. The composition stays inspectable, the blocks stay the
 * ones the rest of the card already uses, and a type's body can be filtered without this function
 * learning anything new.
 *
 * @return string
 */
function axismundi_op_render_object_card_body_block() : string {
	$model = axismundi_op_active_object_view_model();
	if ( ! is_array( $model ) ) {
		return '';
	}
	$type = (string) ( $model['type'] ?? '' );

	/*
	 * An Article in a stream is a lead-in, not the piece: image, title, summary, and a way through
	 * to the full text. There is deliberately no content block, because the body lives on the
	 * Article's own page — which is what Read More is for.
	 *
	 * That absence is what keeps the rules from piling up. With no body in the card there is
	 * nothing for a post-level content warning to fold, so this carries no `object-content-warning`
	 * wrapper and needs no "hide in feed" switch: a sensitive Article is protected by
	 * `object-summary`, which obscures the summary in place and leaves the route to the piece
	 * reachable outside the cover. Covering that route too would leave a warned Article unable to
	 * reach itself.
	 *
	 * The lead image is given a fixed height for the same reason it is a lead-in. A stream is a
	 * list of posts, and letting each Article's image take its intrinsic height makes the card as
	 * tall as the picture happens to be, pushing the next post off screen. The Article's own page
	 * shows it at its real size.
	 */
	$article = '<!-- wp:axismundi/object-featured-image {"style":{"dimensions":{"height":"200px"}}} /-->'
		. '<!-- wp:axismundi/object-title /-->'
		. '<!-- wp:axismundi/object-summary /-->'
		. '<!-- wp:axismundi/object-read-more /-->';

	/*
	 * An authored content warning covers the whole post, so the body, the quote preview, the poll,
	 * and the attachments share one disclosure — matching how Mastodon and Misskey present a warned
	 * post. The header, hashtags and actions stay outside it: they are the surrounding
	 * conversation, not the warned material. Without an authored warning the wrapper renders its
	 * children untouched.
	 */
	$default = '<!-- wp:axismundi/object-featured-image /-->'
		. '<!-- wp:axismundi/object-title /-->'
		. '<!-- wp:axismundi/object-summary /-->'
		. '<!-- wp:axismundi/object-content-warning -->'
		. '<!-- wp:axismundi/object-content /-->'
		. '<!-- wp:axismundi/quote-context /-->'
		. '<!-- wp:axismundi/question /-->'
		. '<!-- wp:axismundi/object-attachments /-->'
		. '<!-- /wp:axismundi/object-content-warning -->';

	$template = 'Article' === $type ? $article : $default;
	/**
	 * Filter the block template one kind of Object's card body is built from.
	 *
	 * @param string              $template Block markup.
	 * @param string              $type     Object type.
	 * @param array<string,mixed> $model    Active Object view model.
	 */
	$template = (string) apply_filters( 'axismundi_op_object_card_body_template', $template, $type, $model );
	return do_blocks( $template );
}

/**
 * The link into an Object's full representation, as its own block.
 *
 * `object-summary` can already end with one, and that stays: it is where the link has always
 * been, and templates depend on it. But a link welded to the end of a paragraph cannot be moved,
 * aligned, or grouped on its own — an author who wants it pushed to the right of the card has to
 * push the summary with it. Separating the two costs one block and gives the card a footer
 * element instead of a sentence ending.
 *
 * The words begin with the FEP example's "Read more" and may be replaced with the publication's
 * own idiom. An omitted or empty value still uses that default: a stream Article must retain a
 * visible route to its full representation.
 *
 * The destination is not the author's. It is the Object's own cached view, falling back to the
 * page it came from — the same resolution `object-summary` uses, so the two cannot send a reader
 * to different places.
 *
 * @param array<string,mixed> $attributes Block attributes.
 * @return string
 */
function axismundi_op_render_object_read_more_block( array $attributes = array() ) : string {
	$model = axismundi_op_active_object_view_model();
	$text  = trim( (string) ( $attributes['text'] ?? '' ) );
	if ( '' === $text ) {
		$text = __( 'Read more', 'axismundi-object-projections' );
	}
	if ( ! is_array( $model ) ) {
		return '';
	}
	$url = trim( (string) ( $model['cached_view_url'] ?? '' ) );
	if ( '' === $url ) {
		$url = trim( (string) ( $model['human_url'] ?? '' ) );
	}
	if ( '' === $url ) {
		return '';
	}
	$align   = isset( $attributes['textAlign'] ) ? sanitize_html_class( (string) $attributes['textAlign'] ) : '';
	$classes = 'axismundi-object__read-more' . ( '' !== $align ? ' has-text-align-' . $align : '' );
	$wrapper = null === WP_Block_Supports::$block_to_render
		? 'class="' . esc_attr( $classes ) . '"'
		: get_block_wrapper_attributes( array( 'class' => $classes ) );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapper is core-escaped or a literal.
	return '<p ' . $wrapper . '><a class="axismundi-object__read-more-link" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a></p>';
}

function axismundi_op_render_object_summary_block( array $attributes = array() ) : string {
	$model   = axismundi_op_active_object_view_model();
	$summary = is_array( $model ) ? trim( (string) ( $model['summary'] ?? '' ) ) : '';
	if ( '' === $summary ) {
		return '';
	}
	$length  = max( 1, (int) ( $attributes['excerptLength'] ?? 55 ) );
	$excerpt = wp_trim_words( $summary, $length, '…' );
	$more    = trim( (string) ( $attributes['moreText'] ?? '' ) );
	// An Article's stream lead is a route into the full cached representation. The
	// original page remains available as "Open the original post" in the detail panel.
	$url     = '';
	if ( is_array( $model ) ) {
		$url = trim( (string) ( $model['cached_view_url'] ?? '' ) );
		if ( '' === $url ) {
			$url = trim( (string) ( $model['human_url'] ?? '' ) );
		}
	}
	$newline = ! array_key_exists( 'showMoreOnNewLine', $attributes ) || (bool) $attributes['showMoreOnNewLine'];
	$excerpt_class = 'wp-block-post-excerpt__excerpt' . ( ! $newline && '' !== $more && '' !== $url ? ' is-inline' : '' );
	$link    = '';
	if ( '' !== $more && '' !== $url ) {
		$anchor = '<a class="wp-block-post-excerpt__more-link" href="' . esc_url( $url ) . '">' . esc_html( $more ) . '</a>';
		$link   = $newline
			? '<p class="wp-block-post-excerpt__more-text">' . $anchor . '</p>'
			: ' ' . $anchor;
	}
	/*
	 * A sensitive Article in a stream is three lines, per FEP-b2b8's in-stream guidance:
	 *
	 *   Content warning: Citizen Kane   the label
	 *   click to view                   the summary, obscured in place
	 *   read more                       the way to the full representation
	 *
	 * "Obscured", not collapsed: the text stays where it is and is made unreadable, which
	 * is why this is a spoiler rather than the `<details>` a Note uses. An Article's body
	 * is not in the stream at all, so its summary is the thing to protect — the mirror
	 * image of a Note, where the body is the post and the wrapper hides it.
	 *
	 * `sensitive` alone is enough here. The Note rule (fold only when the author wrote a
	 * warning) does not transfer: FEP asks an Article's content to stay covered until the
	 * reader asks for it, and supplies a label fallback chain precisely so a missing
	 * `dcterms:subject` never disables the protection.
	 *
	 * Threads renders the same effect by painting a `<canvas>` over the words. That is
	 * not copied: a bitmap over text breaks selection, translation, zoom, and high
	 * contrast. The behaviour rules and the visual tokens are shared with the Media
	 * Library's media overlay, but not its figure-shaped markup, which this is not.
	 *
	 * The "read more" link sits outside the spoiler. It is the route to the canonical
	 * page, and covering it would leave a warned Article with no way to reach itself.
	 */
	if ( ! empty( $model['sensitive'] ) && 'Article' === (string) ( $model['type'] ?? '' ) ) {
		$spoiler_id = wp_unique_id( 'ax-object-spoiler-' );
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'axismundi-object__summary-gate' ) ) . '>'
			. '<div class="axismundi-object__spoiler is-obscured" data-ax-spoiler>'
			. '<p class="axismundi-object__spoiler-warning">'
			/* translators: %s: the content-warning label. */
			. esc_html( sprintf( __( 'Content warning: %s', 'axismundi-object-projections' ), axismundi_op_sensitive_content_label( $model ) ) )
			. '</p>'
			// Hidden from assistive technology while covered, so the warning is met first
			// there too rather than the summary simply being read out.
			. '<div class="axismundi-object__spoiler-text" id="' . esc_attr( $spoiler_id ) . '" aria-hidden="true">'
			. '<p class="' . esc_attr( $excerpt_class ) . '">' . esc_html( $excerpt ) . '</p></div>'
			. '<button type="button" class="axismundi-object__spoiler-reveal" aria-expanded="false" aria-controls="' . esc_attr( $spoiler_id ) . '">'
			. esc_html__( 'Click to view', 'axismundi-object-projections' ) . '</button>'
			. '</div>' . $link . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Excerpt, label, link, and wrapper attributes escaped above.
	}
	return '<div ' . get_block_wrapper_attributes( array( 'class' => 'wp-block-post-excerpt axismundi-object__summary' ) ) . '><p class="' . esc_attr( $excerpt_class ) . '">' . esc_html( $excerpt ) . '</p>' . $link . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Summary, links, and wrapper attributes are escaped above.
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

/** Render the compact author line shared by Quote embeds and references. */
function axismundi_op_render_quote_context_author( array $author ) : string {
	$name   = trim( (string) ( $author['name'] ?? '' ) );
	$handle = trim( (string) ( $author['handle'] ?? '' ) );
	$url    = trim( (string) ( $author['url'] ?? '' ) );
	$label  = '' !== $name ? $name : $handle;
	if ( '' === $label ) {
		return '';
	}
	$identity = '<strong>' . esc_html( $label ) . '</strong>';
	if ( '' !== $handle && $handle !== $label ) {
		$identity .= '<span class="axismundi-object__quote-handle">' . esc_html( $handle ) . '</span>';
	}
	if ( '' !== $url ) {
		$identity = '<a href="' . esc_url( $url ) . '">' . $identity . '</a>';
	}
	return '<div class="axismundi-object__quote-author">' . $identity . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Identity values escaped above.
}

/** Render a bounded, consent-aware quote context from the enriched view model. */
function axismundi_op_render_quote_context_block( array $attributes = array() ) : string {
	unset( $attributes ); // Preview selection is editor-only; the live relation is authoritative.
	$model   = axismundi_op_active_object_view_model();
	$context = is_array( $model ) ? ( $model['quote_context'] ?? null ) : null;
	if ( ! is_array( $context ) || '' === (string) ( $context['target_uri'] ?? '' ) ) {
		return '';
	}

	$target_uri = (string) $context['target_uri'];
	$state      = (string) ( $context['display_state'] ?? 'unavailable' );
	$reason     = (string) ( $context['reason'] ?? 'unavailable' );
	$classes    = 'axismundi-object__quote';

	if ( 'embed' !== $state || ! is_array( $context['target'] ?? null ) ) {
		$labels = array(
			'pending'    => array( 'schedule', __( 'Quote approval pending', 'axismundi-object-projections' ), __( 'The quoted preview will appear once approval is available.', 'axismundi-object-projections' ) ),
			'rejected'   => array( 'block', __( 'Quote request rejected', 'axismundi-object-projections' ), __( 'The original author did not approve this quoted preview.', 'axismundi-object-projections' ) ),
			'revoked'    => array( 'link_off', __( 'Quote authorization revoked', 'axismundi-object-projections' ), __( 'The original preview was removed at the author’s request.', 'axismundi-object-projections' ) ),
			'tombstone'  => array( 'delete', __( 'Quoted object deleted', 'axismundi-object-projections' ), __( 'The original object is no longer available.', 'axismundi-object-projections' ) ),
			'cycle'      => array( 'format_quote', __( 'Nested quote reference', 'axismundi-object-projections' ), __( 'A circular quote cannot be expanded here.', 'axismundi-object-projections' ) ),
			'unverified' => array( 'help', __( 'Quoted object unavailable', 'axismundi-object-projections' ), __( 'This quoted preview could not be verified.', 'axismundi-object-projections' ) ),
			'unavailable'=> array( 'link_off', __( 'Quoted object unavailable', 'axismundi-object-projections' ), __( 'The original object cannot be previewed here.', 'axismundi-object-projections' ) ),
		);
		$copy = $labels[ $reason ] ?? $labels['unavailable'];
		return '<aside ' . get_block_wrapper_attributes( array( 'class' => $classes . ' axismundi-object__quote--placeholder is-' . sanitize_html_class( $reason ) ) ) . '>'
			. '<span class="material-symbols-outlined" aria-hidden="true">' . esc_html( $copy[0] ) . '</span>'
			. '<div><strong>' . esc_html( $copy[1] ) . '</strong><p>' . esc_html( $copy[2] ) . '</p>'
			. '<a href="' . esc_url( $target_uri ) . '">' . esc_html__( 'Open original object', 'axismundi-object-projections' ) . '</a></div></aside>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All placeholder values escaped above.
	}

	$target     = (array) $context['target'];
	$type       = (string) ( $target['type'] ?? 'Note' );
	$target_url = (string) ( $target['url'] ?? '' );
	$target_url = '' !== $target_url ? $target_url : $target_uri;
	$article    = 'Article' === $type;
	$body       = axismundi_op_render_quote_context_author( (array) ( $target['author'] ?? array() ) );
	$title      = trim( (string) ( $target['title'] ?? '' ) );
	$excerpt    = trim( (string) ( $target['excerpt'] ?? '' ) );
	$warning    = trim( (string) ( $target['content_warning'] ?? '' ) );
	if ( $article && '' !== $title ) {
		$body .= '<h3 class="axismundi-object__quote-title"><a href="' . esc_url( $target_url ) . '">' . esc_html( $title ) . '</a></h3>';
	}
	$thumbnail = is_array( $target['thumbnail'] ?? null ) ? (array) $target['thumbnail'] : array();
	$thumbnail_url = function_exists( 'axismundi_op_attachment_href' ) ? axismundi_op_attachment_href( $thumbnail ) : '';
	if ( $article && '' !== $thumbnail_url ) {
		$body .= '<img class="axismundi-object__quote-thumbnail" src="' . esc_url( $thumbnail_url ) . '" alt="' . esc_attr( $title ) . '" loading="lazy">';
	}
	if ( ! empty( $target['sensitive'] ) && '' !== $warning ) {
		$body .= '<p class="axismundi-object__quote-warning">' . esc_html( $warning ) . '</p>';
	} elseif ( '' !== $excerpt ) {
		$body .= '<p class="axismundi-object__quote-excerpt">' . esc_html( $excerpt ) . '</p>';
	}
	if ( $article ) {
		$body .= '<a class="axismundi-object__quote-read-more" href="' . esc_url( $target_url ) . '">' . esc_html__( 'Read article', 'axismundi-object-projections' ) . '</a>';
	}
	$nested = is_array( $context['nested'] ?? null ) ? (array) $context['nested'] : array();
	if ( ! empty( $nested['uri'] ) ) {
		$nested_uri    = (string) $nested['uri'];
		$nested_url    = trim( (string) ( $nested['url'] ?? '' ) );
		$nested_url    = '' !== $nested_url ? $nested_url : $nested_uri;
		$nested_author = (array) ( $nested['author'] ?? array() );
		$nested_label  = trim( (string) ( $nested['title'] ?? $nested['name'] ?? '' ) );
		if ( '' === $nested_label ) {
			$nested_label = trim( (string) ( $nested_author['handle'] ?? '' ) );
		}
		if ( '' === $nested_label ) {
			$nested_label = __( 'Quoted object', 'axismundi-object-projections' );
		}
		$body .= '<aside class="axismundi-object__quote-reference' . ( ! empty( $nested['cycle'] ) ? ' is-cycle' : '' ) . '">'
			. '<span class="material-symbols-outlined" aria-hidden="true">format_quote</span>'
			. '<a href="' . esc_url( $nested_url ) . '">' . esc_html( $nested_label ) . '</a></aside>';
	}
	return '<blockquote ' . get_block_wrapper_attributes( array( 'class' => $classes . ' axismundi-object__quote--embed' . ( $article ? ' is-article' : ' is-note' ) ) ) . '>' . $body . '</blockquote>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Quote fragments escaped above.
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
		'object-tombstone'    => array( 'Object Tombstone', 'axismundi_op_render_object_tombstone_block' ),
		'object-avatar'       => array( 'Legacy Object Actor Avatar', 'axismundi_op_render_object_avatar_block' ),
		'object-identity'     => array( 'Legacy Object Actor Identity', 'axismundi_op_render_object_identity_block' ),
		'object-meta'         => array( 'Object Metadata', 'axismundi_op_render_object_meta_block' ),
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
						'inserter' => ! in_array( $slug, array( 'object-avatar', 'object-identity', 'object-meta' ), true ),
					)
				),
				'attributes'      => 'object-avatar' === $slug ? array( 'size' => array( 'type' => 'number', 'default' => 48 ) ) : array(),
			)
		);
	}
	// Blocks migrated to `block.json` directories register from their metadata.
	// WordPress then bootstraps one identical definition to the editor, so Core
	// -style supports need no hand-maintained JavaScript copy.
	register_block_type( dirname( __DIR__ ) . '/blocks/object-content-warning' );
	register_block_type( dirname( __DIR__ ) . '/blocks/object-content' );
	register_block_type( dirname( __DIR__ ) . '/blocks/object-title' );
	register_block_type( dirname( __DIR__ ) . '/blocks/object-date' );
	register_block_type( dirname( __DIR__ ) . '/blocks/object-visibility' );
	register_block_type( dirname( __DIR__ ) . '/blocks/object-type' );
	register_block_type( dirname( __DIR__ ) . '/blocks/object-summary' );
	register_block_type( dirname( __DIR__ ) . '/blocks/object-read-more' );
	register_block_type( dirname( __DIR__ ) . '/blocks/object-card-body' );
	register_block_type( dirname( __DIR__ ) . '/blocks/object-hashtags' );
	register_block_type( dirname( __DIR__ ) . '/blocks/interactions' );
	register_block_type( dirname( __DIR__ ) . '/blocks/object-featured-image' );
	register_block_type( dirname( __DIR__ ) . '/blocks/object-attachments' );
	register_block_type( dirname( __DIR__ ) . '/blocks/quote-context' );
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
			'axismundi-object-projections//single-object-article',
			array(
				'title'       => __( 'Axismundi Single Object: Article', 'axismundi-object-projections' ),
				'description' => __( 'The canonical full-text view for a cached remote Article, shown without the stream lead-in.', 'axismundi-object-projections' ),
				'content'     => axismundi_op_single_object_template_content( 'single-object-article' ),
			)
		);
		register_block_template(
			'axismundi-object-projections//single-object-reply',
			array(
				'title'       => __( 'Axismundi Single Object: Reply', 'axismundi-object-projections' ),
				'description' => __( 'The canonical view for an Object that replies to another, shown with the conversation it continues.', 'axismundi-object-projections' ),
				'content'     => axismundi_op_single_object_template_content( 'single-object-reply' ),
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
