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

// What this button does (includes/action.php). Both button blocks share the
// axis, so the markup it produces is written once there.
$axismundi_dialogs_ib_act = axismundi_dialogs_button_action( $attributes );

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

$axismundi_dialogs_ib_is_link = axismundi_dialogs_button_is_link( $attributes );
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
		$axismundi_dialogs_ib_act['attrs']
	);

// The shell's attributes, as dialog-button saves them, plus this block's own:
// width, and Standard - an icon-button colour mode, not a block style (see
// src/dialog-icon-button.js).
$axismundi_dialogs_ib_wrapper = array( 'class' => 'wp-block-button' );
if ( $axismundi_dialogs_ib_act['interactive'] ) {
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
	$axismundi_dialogs_ib_act['interactive'] ? axismundi_dialogs_action_context() : '',
	$axismundi_dialogs_ib_act['surface'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() output.
);
