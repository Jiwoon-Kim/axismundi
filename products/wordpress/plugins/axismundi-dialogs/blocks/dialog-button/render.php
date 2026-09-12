<?php
/**
 * axismundi/dialog-button - server render.
 *
 * WHY THIS BLOCK RENDERS ON THE SERVER, where core/button does not.
 *
 * core/button keeps a `save.js` and post-processes the saved markup in
 * `render_block_core_button()`. That shape would work here too, up to the point
 * where an icon comes from the icon registry: resolving it means calling
 * `wp_get_icon()` and putting an <svg> inside the control, and
 * WP_HTML_Tag_Processor cannot set an element's contents - only its attributes.
 * Doing it anyway means bookmarks and string splicing over saved markup. The
 * icon font needs none of that, so the alternative was two mechanisms for one
 * axis. This block renders instead, as its sibling dialog-icon-button already
 * does, and the two now produce their markup the same way.
 *
 * The trade the other way, recorded so it is not rediscovered: core cannot make
 * this choice. Its buttons are already saved in millions of posts, and a block
 * that stops emitting markup takes its content with it. Ours were not.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

/*
 * Buttons saved before this block rendered on the server. Their label, URL,
 * target and rel live in the saved markup and nowhere else, so rendering from
 * the attributes would drop all four and leave an empty button - the editor's
 * deprecation migrates them on load, but nothing opens a post to read it.
 *
 * That markup is already complete: the old save() wrote the wrapper, its
 * data-size and data-shape, the control and the label. So it is handed back
 * untouched, which is what core/button's render does with content it does not
 * need to change. A block saved the new way has no inner markup at all, so an
 * icon-only button with no label does not take this path.
 */
if ( '' === (string) ( $attributes['text'] ?? '' ) && '' !== trim( (string) $content ) ) {
	// Echoed, not returned: a `render` template is included inside an output
	// buffer, so the buffer is the block's output and a return value is thrown
	// away. Returning $content rendered an empty button - measured.
	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- saved post content, passed through unchanged.
	return;
}

/*
 * The icon is optional here - M3: "Can contain an optional leading icon" - so
 * it is a setting of its own rather than a side effect of a name being stored.
 * Absence is off, and the icon a button had is kept while it is off, the way
 * `selected` survives on a button that has stopped being a toggle: turning it
 * back on restores the choice instead of starting again at the default.
 *
 * Its sibling dialog-icon-button has no such switch: an icon button with no
 * icon is nothing at all.
 */
$axismundi_dialogs_b_icon = empty( $attributes['showIcon'] )
	? ''
	: axismundi_dialogs_get_button_icon(
		$attributes,
		axismundi_dialogs_is_button_togglable( $attributes, $block )
	);

/*
 * The label. Rich text, so the author's formatting survives - this is the one
 * place the two button blocks differ, the icon button's name being plain text
 * nobody sees.
 *
 * Wrapped in a span only when the button shows an icon. The control is a flex
 * box and a bare text run is already a flex item that `gap` separates, so the
 * span buys nothing on its own - and leaving it out keeps an icon-less button's
 * markup exactly what core/button saves. With an icon it is worth it: the two
 * items are then both elements, which is what the editor draws as well.
 *
 * On `showIcon`, not on whether the icon resolved: the editor keys the same
 * structure on the same stored value, because deriving it from resolution made
 * the label's element change mid-keystroke (src/dialog-button.js). A name that
 * resolves to nothing therefore leaves an empty slot rather than restructuring
 * the button.
 */
$axismundi_dialogs_b_label = (string) ( $attributes['text'] ?? '' );
if ( '' !== $axismundi_dialogs_b_label && ! empty( $attributes['showIcon'] ) ) {
	$axismundi_dialogs_b_label = '<span class="wp-block-button__label">' . wp_kses_post( $axismundi_dialogs_b_label ) . '</span>';
} else {
	$axismundi_dialogs_b_label = wp_kses_post( $axismundi_dialogs_b_label );
}

// An empty button renders nothing, as core/button decided in 6.6 - except that
// an icon is content too, so a button that is only an icon stays.
if ( '' === trim( wp_strip_all_tags( $axismundi_dialogs_b_label ) ) && '' === $axismundi_dialogs_b_icon ) {
	return '';
}

/*
 * Disabled. A <button> has the attribute; a link has no such thing, so the
 * href goes instead - an <a> without one is not a link, is not focusable, and
 * cannot be followed - and `aria-disabled` says why it is still there.
 *
 * Nothing else is needed to stop a toggle: a disabled button fires no click, so
 * the Interactivity directive never runs.
 */
$axismundi_dialogs_b_disabled = ! empty( $attributes['disabled'] );

$axismundi_dialogs_b_is_link = 'a' === ( $attributes['tagName'] ?? 'button' );
$axismundi_dialogs_b_control = $axismundi_dialogs_b_is_link
	? sprintf(
		'<a class="wp-block-button__link wp-element-button"%1$s%2$s%3$s%4$s>',
		! empty( $attributes['url'] ) && ! $axismundi_dialogs_b_disabled ? ' href="' . esc_url( $attributes['url'] ) . '"' : '',
		! empty( $attributes['linkTarget'] ) ? ' target="' . esc_attr( $attributes['linkTarget'] ) . '"' : '',
		! empty( $attributes['rel'] ) ? ' rel="' . esc_attr( $attributes['rel'] ) . '"' : '',
		$axismundi_dialogs_b_disabled ? ' aria-disabled="true"' : ''
	)
	: sprintf(
		'<button type="%1$s" class="wp-block-button__link wp-element-button"%2$s>',
		esc_attr( in_array( $attributes['type'] ?? 'button', array( 'button', 'submit', 'reset' ), true ) ? $attributes['type'] ?? 'button' : 'button' ),
		$axismundi_dialogs_b_disabled ? ' disabled' : ''
	);

// data-size and data-shape only when stored: an unsized button is the theme's,
// which is already M3 Small (assets/button.css).
$axismundi_dialogs_b_wrapper = array( 'class' => 'wp-block-button' );
foreach ( array( 'size', 'shape' ) as $axismundi_dialogs_b_axis ) {
	if ( ! empty( $attributes[ $axismundi_dialogs_b_axis ] ) ) {
		$axismundi_dialogs_b_wrapper[ 'data-' . $axismundi_dialogs_b_axis ] = (string) $attributes[ $axismundi_dialogs_b_axis ];
	}
}

// Absence is on: M3 fills a selected toggle's icon. Only a deliberate
// "off" is stored, and the stylesheet keys on it (assets/button.css).
if ( isset( $attributes['fillOnSelect'] ) && ! $attributes['fillOnSelect'] ) {
	$axismundi_dialogs_b_wrapper['data-fill-on-select'] = 'false';
}

printf(
	'<div %1$s>%2$s%3$s%4$s</%5$s></div>',
	get_block_wrapper_attributes( $axismundi_dialogs_b_wrapper ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core.
	$axismundi_dialogs_b_control, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	$axismundi_dialogs_b_icon, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped/sanitised by the renderer.
	$axismundi_dialogs_b_label, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post above.
	$axismundi_dialogs_b_is_link ? 'a' : 'button'
);
