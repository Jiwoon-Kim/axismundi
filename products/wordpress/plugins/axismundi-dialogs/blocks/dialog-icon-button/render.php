<?php
/**
 * axismundi/dialog-icon-button - server render.
 *
 * An icon button: dialog-button's shell with an icon in it instead of a label
 * (M3 Icon button). The shell is the same markup - `.wp-block-button` around
 * `.wp-block-button__link` - so everything written for the button reaches this
 * one too: the size and shape tokens (assets/button.css), and the group's
 * selection rule and runtime (axismundi_dialogs_button_group_selection).
 *
 * The icon comes from the shared renderer (includes/icon.php), the same
 * primitive dialog-icon draws with. It is always decorative here: an icon
 * button has no label in its anatomy, so the button carries the name, as text
 * a screen reader reads and nobody sees.
 *
 * Rendered on the server, as core/icon is, because a registry icon resolves
 * through wp_get_icon(). So `text` - the name - is stored in the block comment
 * rather than read back from saved markup.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

$axismundi_dialogs_ib_icon = axismundi_dialogs_get_button_icon(
	$attributes,
	axismundi_dialogs_is_button_togglable( $attributes, $block )
);

// The name, as plain text: a converted button can bring formatted rich text,
// and none of it can be seen here.
$axismundi_dialogs_ib_name = trim( wp_strip_all_tags( (string) ( $attributes['text'] ?? '' ) ) );

/*
 * What this button does.
 *
 * The surface copies core's Navigation overlay: a `div` with `role="dialog"`,
 * shown by class, driven by its own runtime (assets/overlay.js,
 * assets/overlay.css). Not this plugin's `<dialog>`, and not its M3 Dialog
 * anatomy - a surface meant to behave like core's is built like core's.
 *
 * The plugin's own dialogs stay native. The choice is per surface, not a house
 * style: `<dialog>` is better where nothing has to match core, and the point
 * here is that something does.
 *
 * Core's Navigation keeps its open state in the element context, which welds
 * the overlay to its own button - nothing else can open it. Here the trigger
 * and the surface are rendered together, so they share a context without being
 * welded, and the surface keeps an id a second trigger could name later.
 */
$axismundi_dialogs_ib_action = (string) ( $attributes['action'] ?? '' );
$axismundi_dialogs_ib_target = (string) ( $attributes['actionTarget'] ?? '' );
$axismundi_dialogs_ib_overlay = '';
$axismundi_dialogs_ib_overlay_id = '';
if ( 'overlay' === $axismundi_dialogs_ib_action && '' !== $axismundi_dialogs_ib_target ) {
	$axismundi_dialogs_ib_part_id = str_contains( $axismundi_dialogs_ib_target, '//' )
		? $axismundi_dialogs_ib_target
		: get_stylesheet() . '//' . $axismundi_dialogs_ib_target;
	$axismundi_dialogs_ib_template = get_block_template( $axismundi_dialogs_ib_part_id, 'wp_template_part' );
	if ( $axismundi_dialogs_ib_template instanceof WP_Block_Template ) {
		$axismundi_dialogs_ib_overlay_id = wp_unique_id( 'ax-overlay-' );
		$axismundi_dialogs_ib_overlay    = sprintf(
			'<div id="%1$s" class="ax-overlay" aria-label="%2$s" tabindex="-1"'
			. ' data-wp-class--is-open="context.isOpen"'
			. ' data-wp-bind--role="state.roleAttribute"'
			. ' data-wp-bind--aria-modal="state.ariaModal"'
			. ' data-wp-watch="callbacks.initOverlay"'
			. ' data-wp-on--keydown="actions.handleKeydown"'
			. ' data-wp-on--focusout="actions.handleFocusout"'
			. '><div class="ax-overlay__content" data-wp-watch="callbacks.focusFirstElement">%3$s</div></div>',
			esc_attr( $axismundi_dialogs_ib_overlay_id ),
			esc_attr( trim( (string) ( $axismundi_dialogs_ib_template->title ?? '' ) ) ),
			axismundi_dialogs_wire_overlay_close( do_blocks( $axismundi_dialogs_ib_template->content ) )
		);
	}
}

// The trigger's own semantics. `aria-pressed` is a two-state button and
// `aria-expanded` a control over a region that opens; a button is one or the
// other, so the trigger wins and the toggle directives are left off. The
// selected LOOK still arrives, keyed on aria-expanded (assets/button.css).
$axismundi_dialogs_ib_action_attrs = '';
if ( '' !== $axismundi_dialogs_ib_overlay_id ) {
	$axismundi_dialogs_ib_action_attrs = sprintf(
		' aria-haspopup="dialog" aria-controls="%s" aria-expanded="false" data-wp-bind--aria-expanded="context.isOpen" data-wp-on--click="actions.open"',
		esc_attr( $axismundi_dialogs_ib_overlay_id )
	);
} elseif ( 'overlay-close' === $axismundi_dialogs_ib_action ) {
	// A close control, as core/navigation-overlay-close is: a plain command,
	// no ARIA of its own. It reaches the surface through the context it sits
	// in, so it works wherever the overlay put it and nowhere else - which is
	// the correct failure.
	$axismundi_dialogs_ib_action_attrs = ' data-wp-on--click="actions.close"';
}

// A plain tooltip (M3 34.2) - the visual label for a control that shows no text
// of its own, which is every icon button. Required by M3, so absence means on;
// only a deliberate "off" is stored. The runtime (assets/tooltip.js) reads the
// name out of .screen-reader-text, so a button with no name gets none: there
// would be nothing to show.
$axismundi_dialogs_ib_tooltip = ( ! isset( $attributes['showTooltips'] ) || $attributes['showTooltips'] )
	&& '' !== $axismundi_dialogs_ib_name
	? ' data-ax-tooltip="true"'
	: '';

/*
 * Disabled. A <button> has the attribute; a link has no such thing, so the
 * href goes instead - an <a> without one is not a link, is not focusable, and
 * cannot be followed - and `aria-disabled` says why it is still there.
 *
 * Nothing else is needed to stop a toggle: a disabled button fires no click, so
 * the Interactivity directive never runs.
 */
$axismundi_dialogs_ib_disabled = ! empty( $attributes['disabled'] );

$axismundi_dialogs_ib_is_link = 'a' === ( $attributes['tagName'] ?? 'button' );
$axismundi_dialogs_ib_control = $axismundi_dialogs_ib_is_link
	? sprintf(
		'<a class="wp-block-button__link wp-element-button"%1$s%2$s%3$s%4$s%5$s>',
		! empty( $attributes['url'] ) && ! $axismundi_dialogs_ib_disabled ? ' href="' . esc_url( $attributes['url'] ) . '"' : '',
		! empty( $attributes['linkTarget'] ) ? ' target="' . esc_attr( $attributes['linkTarget'] ) . '"' : '',
		! empty( $attributes['rel'] ) ? ' rel="' . esc_attr( $attributes['rel'] ) . '"' : '',
		$axismundi_dialogs_ib_tooltip,
		$axismundi_dialogs_ib_disabled ? ' aria-disabled="true"' : ''
	)
	: sprintf(
		'<button type="%1$s" class="wp-block-button__link wp-element-button"%2$s%3$s%4$s>',
		esc_attr( in_array( $attributes['type'] ?? 'button', array( 'button', 'submit', 'reset' ), true ) ? $attributes['type'] ?? 'button' : 'button' ),
		$axismundi_dialogs_ib_tooltip,
		$axismundi_dialogs_ib_disabled ? ' disabled' : '',
		$axismundi_dialogs_ib_action_attrs
	);

// The shell's attributes, as dialog-button saves them, plus this block's own:
// width, and Standard - an icon-button colour mode, not a block style (see
// src/dialog-icon-button.js).
$axismundi_dialogs_ib_wrapper = array( 'class' => 'wp-block-button' );
if ( '' !== $axismundi_dialogs_ib_overlay_id ) {
	$axismundi_dialogs_ib_wrapper['data-wp-interactive'] = 'axismundi/overlay';
}
foreach ( array( 'size', 'shape', 'width' ) as $axismundi_dialogs_ib_axis ) {
	if ( ! empty( $attributes[ $axismundi_dialogs_ib_axis ] ) ) {
		$axismundi_dialogs_ib_wrapper[ 'data-' . $axismundi_dialogs_ib_axis ] = (string) $attributes[ $axismundi_dialogs_ib_axis ];
	}
}

// Absence is on: M3 fills a selected toggle's icon. Only a deliberate
// "off" is stored, and the stylesheet keys on it (assets/button.css).
if ( isset( $attributes['fillOnSelect'] ) && ! $attributes['fillOnSelect'] ) {
	$axismundi_dialogs_ib_wrapper['data-fill-on-select'] = 'false';
}
// An empty name is an unnamed control, not an empty span: the editor fills the
// Label in from the icon when an icon is chosen (src/dialog-icon-button.js), so
// this is only reached when the author emptied it.
$axismundi_dialogs_ib_label = '' !== $axismundi_dialogs_ib_name
	? '<span class="screen-reader-text">' . esc_html( $axismundi_dialogs_ib_name ) . '</span>'
	: '';

printf(
	'<div %1$s%6$s>%2$s%3$s%4$s</%5$s>%7$s</div>',
	get_block_wrapper_attributes( $axismundi_dialogs_ib_wrapper ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core.
	$axismundi_dialogs_ib_control, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	$axismundi_dialogs_ib_icon, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped/sanitised by the renderer.
	$axismundi_dialogs_ib_label, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	$axismundi_dialogs_ib_is_link ? 'a' : 'button',
	'' !== $axismundi_dialogs_ib_overlay_id
		? ' ' . wp_interactivity_data_wp_context(
			array(
				'isOpen'        => false,
				'modal'         => null,
				'previousFocus' => null,
			)
		)
		: '',
	$axismundi_dialogs_ib_overlay // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() output.
);
