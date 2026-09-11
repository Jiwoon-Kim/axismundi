<?php
/**
 * axismundi/dialog-icon-button - server render.
 *
 * An icon button: dialog-button's shell with an icon in it instead of a label
 * (M3 Icon button). The shell is the same markup - `.wp-block-button` around
 * `.wp-block-button__link` - so everything written for the button reaches this
 * one too: the size and shape tokens (assets/button.css), and the group's
 * selection rule and runtime (axismundi_dialogs_buttons_selection).
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

$axismundi_dialogs_ib_source = 'registry' === ( $attributes['iconSource'] ?? 'font' ) ? 'registry' : 'font';
$axismundi_dialogs_ib_class  = (string) ( $attributes['iconClass'] ?? 'material-symbols-outlined' );
$axismundi_dialogs_ib_icon   = axismundi_dialogs_get_icon(
	array(
		'source' => $axismundi_dialogs_ib_source,
		'name'   => (string) ( $attributes['icon'] ?? '' ),
		'class'  => $axismundi_dialogs_ib_class,
	)
);

// Icon(selected): a second reference, for a source with no state axis - a
// static font or the registry, where the filled star is another icon. A
// variable font needs none: selected fills the same glyph (assets/button.css).
//
// Selection is the visitor's, at runtime, so the server cannot pick one icon.
// A toggle with a selected icon carries both, and aria-pressed shows one
// (assets/button.css). Whether this button is a toggle is its own choice, else
// its group's - the rule in src/shared/selection.js.
$axismundi_dialogs_ib_togglable = isset( $attributes['togglable'] )
	? (bool) $attributes['togglable']
	: ! empty( $block->context['axismundi/togglable'] );
$axismundi_dialogs_ib_selected_name = trim( (string) ( $attributes['selectedIcon'] ?? '' ) );
if ( $axismundi_dialogs_ib_togglable && '' !== $axismundi_dialogs_ib_selected_name && '' !== $axismundi_dialogs_ib_icon ) {
	$axismundi_dialogs_ib_selected = axismundi_dialogs_get_icon(
		array(
			'source' => $axismundi_dialogs_ib_source,
			'name'   => $axismundi_dialogs_ib_selected_name,
			'class'  => $axismundi_dialogs_ib_class,
		),
		array( 'class' => 'ax-icon--selected' )
	);
	if ( '' !== $axismundi_dialogs_ib_selected ) {
		$axismundi_dialogs_ib_icon = axismundi_dialogs_get_icon(
			array(
				'source' => $axismundi_dialogs_ib_source,
				'name'   => (string) ( $attributes['icon'] ?? '' ),
				'class'  => $axismundi_dialogs_ib_class,
			),
			array( 'class' => 'ax-icon--unselected' )
		) . $axismundi_dialogs_ib_selected;
	}
}

// The name, as plain text: a converted button can bring formatted rich text,
// and none of it can be seen here.
$axismundi_dialogs_ib_name = trim( wp_strip_all_tags( (string) ( $attributes['text'] ?? '' ) ) );

$axismundi_dialogs_ib_is_link = 'a' === ( $attributes['tagName'] ?? 'button' );
$axismundi_dialogs_ib_control = $axismundi_dialogs_ib_is_link
	? sprintf(
		'<a class="wp-block-button__link wp-element-button"%1$s%2$s%3$s>',
		! empty( $attributes['url'] ) ? ' href="' . esc_url( $attributes['url'] ) . '"' : '',
		! empty( $attributes['linkTarget'] ) ? ' target="' . esc_attr( $attributes['linkTarget'] ) . '"' : '',
		! empty( $attributes['rel'] ) ? ' rel="' . esc_attr( $attributes['rel'] ) . '"' : ''
	)
	: sprintf(
		'<button type="%s" class="wp-block-button__link wp-element-button">',
		esc_attr( in_array( $attributes['type'] ?? 'button', array( 'button', 'submit', 'reset' ), true ) ? $attributes['type'] ?? 'button' : 'button' )
	);

// The shell's attributes, as dialog-button saves them, plus this block's own:
// width, and Standard - an icon-button colour mode, not a block style (see
// src/dialog-icon-button.js).
$axismundi_dialogs_ib_wrapper = array( 'class' => 'wp-block-button' );
foreach ( array( 'size', 'shape', 'width' ) as $axismundi_dialogs_ib_axis ) {
	if ( ! empty( $attributes[ $axismundi_dialogs_ib_axis ] ) ) {
		$axismundi_dialogs_ib_wrapper[ 'data-' . $axismundi_dialogs_ib_axis ] = (string) $attributes[ $axismundi_dialogs_ib_axis ];
	}
}
if ( ! empty( $attributes['standard'] ) ) {
	$axismundi_dialogs_ib_wrapper['data-standard'] = 'true';
}

printf(
	'<div %1$s>%2$s%3$s<span class="screen-reader-text">%4$s</span></%5$s></div>',
	get_block_wrapper_attributes( $axismundi_dialogs_ib_wrapper ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core.
	$axismundi_dialogs_ib_control, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	$axismundi_dialogs_ib_icon, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped/sanitised by the renderer.
	esc_html( $axismundi_dialogs_ib_name ),
	$axismundi_dialogs_ib_is_link ? 'a' : 'button'
);
