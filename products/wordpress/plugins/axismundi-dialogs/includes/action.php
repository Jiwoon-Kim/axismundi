<?php
/**
 * What a button does, on the server.
 *
 * Both button blocks carry the same `action` axis, so the markup it produces is
 * written once here rather than twice in two render.php files. The author says
 * what the button is for and this decides the attributes; see
 * src/shared/action-controls.js for why the derivation runs in that direction
 * and not the other.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

/**
 * The template-part area each opening action draws its targets from.
 *
 * An area is exactly "the parts that belong in this kind of surface", which is
 * why an action can name one. Mirrors ACTION_AREAS in
 * src/shared/action-controls.js.
 *
 * @return array<string, string> Action name to template-part area.
 */
function axismundi_dialogs_action_areas(): array {
	return array(
		'overlay' => 'navigation-overlay',
	);
}

/**
 * Whether an action runs its own click.
 *
 * Opening and closing are the button's click, so the button cannot also be a
 * toggle: the group would write `actions.toggle` over this action's directive.
 * It still wears the selected look while open, through `aria-expanded`
 * (assets/button.css). Mirrors actionOwnsClick() in
 * src/shared/action-controls.js.
 *
 * @param array $attributes Block attributes.
 * @return bool Whether the action owns the click.
 */
function axismundi_dialogs_action_owns_click( array $attributes ): bool {
	return in_array( (string) ( $attributes['action'] ?? '' ), array( 'overlay', 'overlay-close' ), true );
}

/**
 * Whether a button renders as a link.
 *
 * Only a Command can be an `<a>`: a link cannot submit, reset, open or close
 * anything, so a stored `tagName` of `a` gives way to the Action.
 *
 * @param array $attributes Block attributes.
 * @return bool Whether to render an `<a>`.
 */
function axismundi_dialogs_button_is_link( array $attributes ): bool {
	return 'a' === ( $attributes['tagName'] ?? 'button' ) && '' === (string) ( $attributes['action'] ?? '' );
}

/**
 * Wire an overlay's close controls to this plugin's runtime.
 *
 * `core/navigation-overlay-close` renders a bare <button>: it carries no
 * directive of its own, and core's Navigation block injects one afterwards
 * while it renders the overlay
 * (block_core_navigation_add_directives_to_overlay_close). A part rendered by
 * anything else - this plugin, for one - therefore arrives with a close button
 * that does nothing.
 *
 * So the same injection is done here, with this plugin's action instead of
 * core's. The part stays exactly as the author built it in the Site Editor; it
 * simply closes the surface it is actually inside.
 *
 * @param string $html Rendered template-part markup.
 * @return string The markup with close directives attached.
 */
function axismundi_dialogs_wire_overlay_close( string $html ): string {
	if ( ! str_contains( $html, 'wp-block-navigation-overlay-close' ) ) {
		return $html;
	}
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag(
		array(
			'tag_name'   => 'BUTTON',
			'class_name' => 'wp-block-navigation-overlay-close',
		)
	) ) {
		$tags->set_attribute( 'data-wp-on--click', 'actions.close' );
	}
	return $tags->get_updated_html();
}

/**
 * The control attributes and surface an action produces.
 *
 * The surface copies core's Navigation overlay: a `div` with `role="dialog"`,
 * shown by class, driven by assets/overlay.js. Not this plugin's `<dialog>` -
 * a surface meant to behave like core's is built like core's, and the plugin's
 * own dialogs stay native where nothing has to match.
 *
 * Core keeps its open state in the element context, which welds the overlay to
 * its own button. Here the trigger and the surface are rendered together, so
 * they share a context without being welded, and the surface keeps an id
 * another trigger could name later.
 *
 * @param array $attributes Block attributes.
 * @return array{attrs: string, surface: string, interactive: bool} Control
 *         attributes, the surface markup, and whether the wrapper needs the
 *         store declared on it.
 */
function axismundi_dialogs_button_action( array $attributes ): array {
	$none   = array(
		'attrs'       => '',
		'surface'     => '',
		'interactive' => false,
	);
	$action = (string) ( $attributes['action'] ?? '' );

	// Closing is a plain command: no ARIA of its own, and the surface it closes
	// is the one it sits in. Outside an overlay it simply does nothing, which
	// is the correct failure for a control that names no target.
	if ( 'overlay-close' === $action ) {
		return array(
			'attrs'       => ' data-wp-on--click="actions.close"',
			'surface'     => '',
			'interactive' => false,
		);
	}

	$areas = axismundi_dialogs_action_areas();
	if ( ! isset( $areas[ $action ] ) ) {
		return $none;
	}

	$target = (string) ( $attributes['actionTarget'] ?? '' );
	if ( '' === $target ) {
		return $none;
	}

	$part_id  = str_contains( $target, '//' ) ? $target : get_stylesheet() . '//' . $target;
	$template = get_block_template( $part_id, 'wp_template_part' );
	if ( ! $template instanceof WP_Block_Template ) {
		return $none;
	}

	$id = wp_unique_id( 'ax-overlay-' );

	return array(
		'attrs'       => sprintf(
			' aria-haspopup="dialog" aria-controls="%s" aria-expanded="false" data-wp-bind--aria-expanded="context.isOpen" data-wp-on--click="actions.open"',
			esc_attr( $id )
		),
		'surface'     => sprintf(
			'<div id="%1$s" class="ax-overlay" aria-label="%2$s" tabindex="-1"'
			. ' data-wp-class--is-open="context.isOpen"'
			. ' data-wp-bind--role="state.roleAttribute"'
			. ' data-wp-bind--aria-modal="state.ariaModal"'
			. ' data-wp-watch="callbacks.initOverlay"'
			. ' data-wp-on--keydown="actions.handleKeydown"'
			. ' data-wp-on--focusout="actions.handleFocusout"'
			. '><div class="ax-overlay__content" data-wp-watch="callbacks.focusFirstElement">%3$s</div></div>',
			esc_attr( $id ),
			esc_attr( trim( (string) ( $template->title ?? '' ) ) ),
			axismundi_dialogs_wire_overlay_close( do_blocks( $template->content ) )
		),
		'interactive' => true,
	);
}

/**
 * The context an overlay's trigger and surface share.
 *
 * @return string The `data-wp-context` attribute, with a leading space.
 */
function axismundi_dialogs_action_context(): string {
	return ' ' . wp_interactivity_data_wp_context(
		array(
			'isOpen'        => false,
			'modal'         => null,
			'previousFocus' => null,
		)
	);
}
