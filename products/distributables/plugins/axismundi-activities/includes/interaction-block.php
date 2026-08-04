<?php
/**
 * One block for every way a reader acts on an Object.
 *
 * Reply, Like, Announce, Quote, Reaction and Vote were six blocks that each drew their own
 * button, and six copies of a button contract drift apart the moment one of them is touched — the
 * six stylesheets already disagreed about nothing in particular. Core solves this shape with one
 * block and an attribute (`core/post-terms {"term":"category"}`), and that is what this is: the
 * markup, sizing and state handling live here once, and a type supplies only what makes it that
 * interaction.
 *
 * `interaction` rather than `action` because `actions.*` is the Interactivity API's namespace and
 * appears in this block's own markup. A block named for the runtime binding beside it would be two
 * different things called one word.
 *
 * A type is registered rather than hardcoded, because not all of them belong to this plugin: vote
 * is a Forum concept, and Activities has no business knowing what a community is. The registry is
 * the same seam Forum already uses for profile surfaces and follower roles.
 *
 * ## What this owns and what the theme owns
 *
 * The button carries `wp-element-button`, so the theme's shape, motion and pressed morph reach it
 * and are never redeclared here. What is not redeclared but owned outright is the text-button
 * appearance — transparent ground, primary label, state layers — because the theme's `text`
 * variation structurally cannot reach this block: core resolves a variation against
 * `styles.blocks.<blockName>.variations`, the theme registers that one for `core/button` only, and
 * the class core generates for it is a per-request `wp_unique_id()` counter that nothing can
 * predict. On top of that this block ships to sites running other themes, where there is no such
 * variation at all. Owning what cannot arrive is not duplication.
 *
 * Selectors here carry two classes deliberately. Global styles emit as
 * `:root :where(.wp-element-button, …)`, which `:where()` flattens to the specificity of `:root`
 * alone — a single-class rule ties with it and loses on order, which is exactly how the Follow
 * button's own colours turned out to be dead. Two classes win outright.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register one interaction type.
 *
 * The callback receives the block's attributes and instance and returns a descriptor, not markup.
 * That is the whole point: a type decides what it is — icon, words, count, whether it reads as
 * selected, how its clicks are wired — and never decides what a button looks like.
 *
 * @param string $type       Type slug.
 * @param array  $definition {
 *     @type callable $describe Callable( array $attributes, WP_Block $block ) : ?array.
 *     @type string   $label    Human label for the editor inserter.
 *     @type string   $icon     Material Symbol name, for the inserter preview.
 * }
 * @return void
 */
function axismundi_act_register_interaction_type( string $type, array $definition ) : void {
	$type = sanitize_key( $type );
	if ( '' === $type || ! isset( $definition['describe'] ) || ! is_callable( $definition['describe'] ) ) {
		return;
	}
	$GLOBALS['axismundi_act_interaction_types'][ $type ] = $definition;
}

/**
 * Every registered interaction type.
 *
 * @return array<string,array<string,mixed>>
 */
function axismundi_act_interaction_types() : array {
	if ( ! isset( $GLOBALS['axismundi_act_interaction_types'] ) ) {
		$GLOBALS['axismundi_act_interaction_types'] = array();
	}
	/**
	 * Filter the registered interaction types.
	 *
	 * @param array<string,array<string,mixed>> $types Registered types.
	 */
	return (array) apply_filters( 'axismundi_act_interaction_types', $GLOBALS['axismundi_act_interaction_types'] );
}

/**
 * The descriptor defaults every type is normalized against.
 *
 * @return array<string,mixed>
 */
function axismundi_act_interaction_descriptor_defaults() : array {
	return array(
		'icon'        => '',
		'label'       => '',
		'aria_label'  => '',
		'count'       => null,
		/*
		 * What to print instead of the number, when the number is not the whole truth. A bounded
		 * scan knows "at least this many", and "12+" says that where a bare 12 would claim to be
		 * exact.
		 */
		'count_text'  => '',
		// Set on an entry in `controls` to render a value rather than a control.
		'text'        => '',
		/*
		 * Context paths for the parts that change after a click, without the page changing.
		 *
		 * The server prints the current value and the runtime keeps it current; leave one out and
		 * that part is right only until someone touches it, which is a bug you have to reload to
		 * stop seeing. The count, the value between the vote buttons, and the icon whose fill
		 * follows the reader's own choice all need one.
		 */
		'count_bind'  => '',
		'text_bind'   => '',
		'icon_bind'   => '',
		'selected'    => false,
		'disabled'    => false,
		/*
		 * Where this goes, when it goes somewhere. A Reply is navigation — it opens a composer —
		 * while a Like mutates in place, and the markup has to say which: an anchor that acts and
		 * a button that navigates both lie to anyone not using a mouse.
		 */
		'href'        => '',
		/*
		 * Whether this is a two-state control. Only a toggle gets `aria-pressed`; announcing a
		 * pressed state on a Reply would promise a state it does not have.
		 */
		'toggle'      => false,
		// Interactivity wiring for a button that owns its own clicks.
		'namespace'   => '',
		/*
		 * The script module holding this type's store, registered by whoever owns the type.
		 *
		 * A type brings its own behaviour, and the block loads it only when that type is on the
		 * page — one block declaring a single view module could not do that, and a page with a
		 * Reply on it has no use for the vote store.
		 */
		'module'      => '',
		'context'     => array(),
		/*
		 * Directives that belong to the wrapper rather than to the control.
		 *
		 * `bindings` land on the button, which is right for a click or a disabled state — but a
		 * popover's lifecycle watches the region that contains both the trigger and the popover,
		 * and putting it on the button would give it the wrong element to measure against. The
		 * reaction picker opened into nowhere without this: it was shown, and never positioned.
		 */
		'wrapper'     => array(),
		'bindings'    => array(),
		// Flat attributes for the delegated variant, where a feed owns the clicks instead.
		'delegated'   => array(),
		// Markup appended inside the wrapper, after the button — a status line, a popover.
		'after'       => '',
		/*
		 * Some interactions are one state wearing several controls.
		 *
		 * A vote is up, a score, and down: two buttons that cannot both be pressed, and a number
		 * that is neither. That is one interaction, not three — splitting it would give each half
		 * of a mutually exclusive pair its own idea of what the reader had chosen. So a type may
		 * describe a list instead of a single control, and each entry is described exactly as a
		 * lone control is.
		 *
		 * An entry carrying `text` is a value rather than a control, which is how the score sits
		 * between the two buttons without pretending to be pressable.
		 */
		'controls'    => array(),
		// Names the set for a reader who arrives on it without seeing the buttons.
		'group_label' => '',
	);
}

/**
 * Whether the surrounding surface, rather than this button, owns the clicks.
 *
 * On a single Object page the button is the interaction and must work with no container around
 * it. Inside a feed the same markup repeats across cards that are appended and replaced
 * continuously, and appended DOM is never hydrated, so the feed owns the clicks and this renders
 * as presentation only. The delegated variant omits the directives rather than emitting them
 * behind a runtime guard: absent markup cannot double-fire.
 *
 * @return bool
 */
function axismundi_act_interaction_is_delegated() : bool {
	return function_exists( 'axismundi_op_object_template_option' )
		&& 'feed' === (string) axismundi_op_object_template_option( 'interactionOwner', 'block' );
}

/**
 * Render one interaction control from its descriptor.
 *
 * Selected state and `aria-pressed` are written into the server's markup rather than left to the
 * runtime to add. A control whose class arrives only at hydration renders in the wrong state on
 * every load and stays wrong forever without JavaScript — which for a Like means the reader is
 * shown, until the page finishes booting, that they have not liked the thing they liked.
 *
 * @param string $type       Type slug.
 * @param array  $descriptor Normalized descriptor.
 * @param array  $attributes Block attributes.
 * @return string
 */
function axismundi_act_render_interaction_control( string $type, array $descriptor, array $attributes ) : string {
	// Small is the size every other button on the site is, so it is what an interaction is unless
	// asked otherwise. Extra small exists for dense surfaces — a community archive card — and is
	// chosen, never inherited by accident.
	$size       = isset( $attributes['size'] ) && 'xs' === $attributes['size'] ? 'xs' : 'sm';
	$show_label = ! empty( $attributes['showLabel'] );
	$show_count = ! empty( $attributes['showCount'] ) && null !== $descriptor['count'];
	$delegated  = axismundi_act_interaction_is_delegated();

	$classes = array( 'wp-element-button', 'axismundi-interaction__button', 'is-size-' . $size );
	if ( $descriptor['selected'] ) {
		$classes[] = 'is-selected';
	}

	// An anchor cannot be disabled, so a destination the reader may not use is rendered as the
	// inert button it actually is rather than as a link that quietly does nothing.
	$href    = $descriptor['disabled'] ? '' : (string) $descriptor['href'];
	$is_link = '' !== $href;
	$label   = (string) ( '' !== $descriptor['aria_label'] ? $descriptor['aria_label'] : $descriptor['label'] );

	$attrs = $is_link
		? array( 'class' => implode( ' ', $classes ), 'href' => $href )
		: array( 'type' => 'button', 'class' => implode( ' ', $classes ) );
	if ( $descriptor['toggle'] ) {
		$attrs['aria-pressed'] = $descriptor['selected'] ? 'true' : 'false';
	}
	$attrs['aria-label'] = $label;
	$attrs['title']      = $label;
	foreach ( ( $delegated ? $descriptor['delegated'] : $descriptor['bindings'] ) as $name => $value ) {
		$attrs[ $name ] = (string) $value;
	}

	$rendered = '';
	foreach ( $attrs as $name => $value ) {
		$rendered .= ' ' . esc_attr( $name ) . '="' . ( 'href' === $name ? esc_url( $value ) : esc_attr( $value ) ) . '"';
	}
	if ( $descriptor['disabled'] && ! $is_link ) {
		$rendered .= ' disabled';
	}

	// A binding is only useful where the runtime is; a delegated control is presentation.
	$bind = static function ( string $path ) use ( $delegated ) : string {
		return $delegated || '' === $path ? '' : ' data-wp-text="' . esc_attr( $path ) . '"';
	};

	$inner = '<span class="material-symbols-outlined" aria-hidden="true"' . $bind( (string) $descriptor['icon_bind'] ) . '>' . esc_html( (string) $descriptor['icon'] ) . '</span>';
	if ( $show_label && '' !== (string) $descriptor['label'] ) {
		$inner .= '<span class="axismundi-interaction__label" aria-hidden="true">' . esc_html( (string) $descriptor['label'] ) . '</span>';
	}
	if ( $show_count ) {
		$printed = '' !== (string) $descriptor['count_text'] ? (string) $descriptor['count_text'] : number_format_i18n( (int) $descriptor['count'] );
		$inner  .= '<span class="axismundi-interaction__count" aria-hidden="true"' . $bind( (string) $descriptor['count_bind'] ) . '>' . esc_html( $printed ) . '</span>';
	}

	$tag = $is_link ? 'a' : 'button';
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every part is escaped above.
	return '<' . $tag . $rendered . '>' . $inner . '</' . $tag . '>';
}

/**
 * Render the interaction block.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Inner content.
 * @param WP_Block $block      Block instance.
 * @return string
 */
function axismundi_act_render_interaction_block( array $attributes, string $content, WP_Block $block ) : string {
	unset( $content );
	$type  = isset( $attributes['type'] ) ? sanitize_key( (string) $attributes['type'] ) : '';
	/**
	 * Substitute the interaction type this instance renders as.
	 *
	 * Some controls are the same act read in a wider context rather than a different act: a
	 * community vote is a Like and a Dislike offered together, and `vote_verb('up')` is literally
	 * `Like`. Which of the two an Object should offer follows from the Object, not from the author
	 * who placed the block — and it cannot be a saved attribute, because one saved card renders
	 * every row of a feed and a thread, where community and ordinary Objects sit side by side.
	 *
	 * So the choice is made here, once, on the way in. This plugin learns only that a type may
	 * stand in for another; what a community is stays with the product that has the concept, which
	 * is the same boundary `axismundi_act_register_interaction_type()` exists to hold.
	 *
	 * @param string   $type       Authored interaction type.
	 * @param array    $attributes Block attributes.
	 * @param WP_Block $block      Block instance.
	 */
	$type  = sanitize_key( (string) apply_filters( 'axismundi_act_interaction_type', $type, $attributes, $block ) );
	$types = axismundi_act_interaction_types();
	if ( ! isset( $types[ $type ] ) ) {
		return '';
	}
	$described = call_user_func( $types[ $type ]['describe'], $attributes, $block );
	if ( ! is_array( $described ) ) {
		// A type declining to describe itself is how it says "not here" — an object that cannot be
		// resolved, a viewer with no Actor. Rendering nothing is the answer, not an inert button.
		return '';
	}
	$descriptor = array_merge( axismundi_act_interaction_descriptor_defaults(), $described );
	if ( ! empty( $descriptor['controls'] ) ) {
		$parts = '';
		foreach ( (array) $descriptor['controls'] as $entry ) {
			$part = array_merge( axismundi_act_interaction_descriptor_defaults(), (array) $entry );
			$parts .= '' !== (string) $part['text']
				? '<span class="axismundi-interaction__value"'
					. ( '' !== (string) $part['aria_label'] ? ' title="' . esc_attr( (string) $part['aria_label'] ) . '"' : '' )
					. ( '' !== (string) $part['text_bind'] && ! axismundi_act_interaction_is_delegated() ? ' data-wp-text="' . esc_attr( (string) $part['text_bind'] ) . '"' : '' )
					. '>' . esc_html( (string) $part['text'] ) . '</span>'
				: axismundi_act_render_interaction_control( $type, $part, $attributes );
		}
		$control = '<div class="axismundi-interaction__group" role="group"'
			. ( '' !== (string) $descriptor['group_label'] ? ' aria-label="' . esc_attr( (string) $descriptor['group_label'] ) . '"' : '' )
			. '>' . $parts . '</div>';
	} else {
		$control = axismundi_act_render_interaction_control( $type, $descriptor, $attributes );
	}

	$wrapper = array( 'class' => 'axismundi-interaction is-type-' . $type );
	$extra   = '';
	if ( ! axismundi_act_interaction_is_delegated() && '' !== (string) $descriptor['namespace'] ) {
		$extra = ' data-wp-interactive="' . esc_attr( (string) $descriptor['namespace'] ) . '" '
			. wp_interactivity_data_wp_context( (array) $descriptor['context'] );
		foreach ( (array) $descriptor['wrapper'] as $name => $value ) {
			$extra .= ' ' . esc_attr( (string) $name ) . '="' . esc_attr( (string) $value ) . '"';
		}
		// The store that answers those directives, loaded only because this type is on the page.
		// A delegated control has no clicks of its own, so it asks for nothing.
		if ( '' !== (string) $descriptor['module'] && function_exists( 'wp_enqueue_script_module' ) ) {
			wp_enqueue_script_module( (string) $descriptor['module'] );
		}
	}
	/*
	 * This renderer answers to the block and, through the registry, to surfaces that compose it
	 * directly. get_block_wrapper_attributes() reads the block currently on the stack and warns
	 * when there is none, so a plain class attribute is used in that case rather than asking core
	 * for supports that no block declared.
	 */
	$wrapper_attributes = null === WP_Block_Supports::$block_to_render
		? 'class="' . esc_attr( $wrapper['class'] ) . '"'
		: get_block_wrapper_attributes( $wrapper );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wrapper is core-escaped or a literal; parts are escaped.
	return '<div ' . $wrapper_attributes . $extra . '>' . $control . (string) $descriptor['after'] . '</div>';
}

/**
 * Keep a page carrying interaction controls out of shared caches for a signed-in reader.
 *
 * Every control states something personal — whether this reader liked it, may vote, holds a REST
 * nonce — so one page cached for everyone would hand one reader another's state. There is one
 * check now rather than one per control, which is the only reason this used to be repeated.
 */
function axismundi_act_prepare_interaction_cache_policy() : void {
	$post = get_queried_object();
	if ( $post instanceof WP_Post && has_block( 'axismundi/interaction', $post ) ) {
		axismundi_act_no_cache_like_state();
	}
}
add_action( 'template_redirect', 'axismundi_act_prepare_interaction_cache_policy', 1 );

/** Register the unified interaction block once its types have had a chance to register. */
function axismundi_act_register_interaction_block() : void {
	/**
	 * Register interaction types.
	 *
	 * Fires before the block is registered so a type is never asked for before it exists.
	 */
	do_action( 'axismundi_act_register_interaction_types' );
	register_block_type( dirname( __DIR__ ) . '/blocks/interaction', array( 'render_callback' => 'axismundi_act_render_interaction_block' ) );
}
add_action( 'init', 'axismundi_act_register_interaction_block', 15 );

/**
 * Register the script modules the built-in interaction types bring with them.
 *
 * They were each a block's `viewScriptModule` before the six became one, and one block can only
 * declare a single view module — so the stores are registered here and pulled in per type, which
 * also means a page never carries a store for an interaction it does not show.
 */
function axismundi_act_register_interaction_modules() : void {
	if ( ! function_exists( 'wp_register_script_module' ) ) {
		return;
	}
	$base = dirname( __DIR__ ) . '/axismundi-activities.php';
	foreach ( array( 'like', 'announce' ) as $type ) {
		$path = dirname( __DIR__ ) . '/assets/interactions/' . $type . '.js';
		if ( is_readable( $path ) ) {
			wp_register_script_module(
				'axismundi-interaction-' . $type,
				plugins_url( 'assets/interactions/' . $type . '.js', $base ),
				array( '@wordpress/interactivity' ),
				(string) filemtime( $path )
			);
		}
	}
}
add_action( 'init', 'axismundi_act_register_interaction_modules', 5 );
