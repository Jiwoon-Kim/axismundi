<?php
/**
 * Icon rendering shared by the blocks that draw an icon.
 *
 * One interface, one backend per source. An icon reference is a small
 * descriptor, and this turns it into the element that is painted - always a
 * <span>: the glyph itself, or the box an SVG sits in - without the block box
 * around it, which stays each
 * block's own (dialog-icon wraps it in its block wrapper; a button places it
 * beside its label). Kept in one place so the blocks cannot drift apart on
 * how a source is resolved, sanitised, sized or transformed.
 *
 * The two sources are different things and are not forced into one output:
 *
 *   font      a ligature in an icon font: a <span> carrying the provider's
 *             rendering class (for example material-symbols-outlined). The
 *             font's own utility class does the sizing and the axes.
 *   registry  a WordPress Icon Registry reference ("core/info"), resolved by
 *             wp_get_icon() to sanitised SVG.
 *
 * What they share lives in the interface: the descriptor, the caller's classes
 * and inline style (which is where a block's colour, border, padding and size
 * token arrive), the label, and the stored transforms - flip and rotation - which
 * apply to a glyph exactly as to an SVG.
 *
 * Both come out as a <span class="ax-icon">: span for a glyph, span>svg for a
 * registry icon. One element with one box model whichever the source, so the
 * paint, the size token, the transforms and the label all land on the same
 * element, the editor draws exactly what the page does (it has to host a REST
 * SVG in a span anyway), and a button holds the same thing for either source.
 * core/icon paints the <svg> itself; the span is this primitive's difference.
 *
 * Every painted element carries .ax-icon, whichever source drew it. It is the
 * hook the primitive's own stylesheet (assets/icon.css) keys on, and the one a
 * container keys on to act on its icon - a button's hover or selected state -
 * without knowing where the icon came from.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render an icon reference as its painted element.
 *
 * @param array $icon {
 *     The icon reference.
 *
 *     @type string $source         'font' or 'registry'. Anything else is 'font'.
 *     @type string $name           Glyph name for a font, `collection/name` for the registry.
 *     @type string $class          Font only: the provider's rendering class.
 *     @type bool   $flipHorizontal Mirror left to right.
 *     @type bool   $flipVertical   Mirror top to bottom.
 *     @type int    $rotation       Degrees, clockwise.
 * }
 * @param array $args {
 *     Optional. How the caller paints it.
 *
 *     @type string $class Classes for the painted element.
 *     @type string $style Inline CSS for the painted element.
 *     @type string $label Accessible name, for an icon that means something on
 *                         its own: the element becomes role="img" with this
 *                         name. With none it is decorative and aria-hidden. The
 *                         same on both sources, as wp_get_icon() does it for an
 *                         SVG. An icon inside a button passes none: the button
 *                         carries the name.
 * }
 * @return string The element, or '' when the reference resolves to nothing.
 */
function axismundi_dialogs_get_icon( array $icon, array $args = array() ): string {
	$source = 'registry' === ( $icon['source'] ?? 'font' ) ? 'registry' : 'font';

	return 'registry' === $source
		? axismundi_dialogs_get_registry_icon( $icon, $args )
		: axismundi_dialogs_get_font_icon( $icon, $args );
}

/**
 * Font backend: a ligature glyph in the provider's rendering class.
 *
 * @param array $icon Icon reference; see axismundi_dialogs_get_icon().
 * @param array $args Painting arguments; see axismundi_dialogs_get_icon().
 * @return string
 */
function axismundi_dialogs_get_font_icon( array $icon, array $args ): string {
	// Ligature names are lower-case words joined by underscores. Anything else
	// could only ever render as text, so it is dropped rather than shown.
	$name = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $icon['name'] ?? '' ) ) );
	if ( '' === $name ) {
		return '';
	}

	// The provider's class is a CSS class, not a font preset: see the GAP note
	// in blocks/dialog-icon/render.php.
	$provider = sanitize_html_class( (string) ( $icon['class'] ?? '' ) );
	$provider = '' !== $provider ? $provider : 'material-symbols-outlined';

	return axismundi_dialogs_icon_span( $provider . ' notranslate', $icon, $args, esc_html( $name ), ' translate="no"' );
}

/**
 * Registry backend: the registered SVG, in the span that carries the paint.
 *
 * @param array $icon Icon reference; see axismundi_dialogs_get_icon().
 * @param array $args Painting arguments; see axismundi_dialogs_get_icon().
 * @return string
 */
function axismundi_dialogs_get_registry_icon( array $icon, array $args ): string {
	if ( ! function_exists( 'wp_get_icon' ) ) {
		return '';
	}

	// wp_get_icon() looks the name up exactly and returns '' for anything
	// unregistered. No size: the size comes from the span, never an attribute.
	// No label either: the SVG is always decorative (aria-hidden, unfocusable),
	// and the name, when there is one, is the span's - as for a glyph.
	$svg = wp_get_icon(
		(string) ( $icon['name'] ?? '' ),
		array(
			'size'  => null,
			'class' => '',
			'label' => '',
		)
	);
	if ( '' === $svg ) {
		return '';
	}

	return axismundi_dialogs_icon_span( 'ax-icon--svg', $icon, $args, $svg );
}

/**
 * The painted span, the same for both sources: the caller's classes and style,
 * the stored transforms, and the label.
 *
 * @param string $kind    Source class: the provider's class and notranslate for
 *                        a glyph, ax-icon--svg for a registry icon.
 * @param array  $icon    Icon reference.
 * @param array  $args    Painting arguments; see axismundi_dialogs_get_icon().
 * @param string $content Inner HTML, already escaped or sanitised.
 * @param string $extra   Extra attributes, already escaped.
 * @return string
 */
function axismundi_dialogs_icon_span( string $kind, array $icon, array $args, string $content, string $extra = '' ): string {
	$class = preg_replace(
		'/\s+/',
		' ',
		trim( 'ax-icon ' . $kind . ' ' . ( $args['class'] ?? '' ) . ' ' . axismundi_dialogs_icon_flip_classes( $icon ) )
	);
	$style = trim( trim( (string) ( $args['style'] ?? '' ) ) . ' ' . axismundi_dialogs_icon_rotation_css( $icon ) );

	// As wp_get_icon() does for an SVG: a labelled icon is an image with that
	// name, and role="img" keeps a glyph's ligature out of it; an unlabelled one
	// is hidden, or a screen reader reads a ligature out ("info") - inside a
	// button, as part of the button's own name.
	$label = trim( (string) ( $args['label'] ?? '' ) );
	$aria  = '' !== $label
		? ' role="img" aria-label="' . esc_attr( $label ) . '"'
		: ' aria-hidden="true"';

	return sprintf(
		'<span class="%1$s"%2$s%3$s%4$s>%5$s</span>',
		esc_attr( $class ),
		$extra,
		$aria,
		'' !== $style ? ' style="' . esc_attr( $style ) . '"' : '',
		$content
	);
}

/**
 * The flip classes for an icon reference, the same for both sources.
 *
 * @param array $icon Icon reference.
 * @return string Space-separated classes, or ''.
 */
function axismundi_dialogs_icon_flip_classes( array $icon ): string {
	return trim(
		( ! empty( $icon['flipHorizontal'] ) ? 'is-flip-horizontal' : '' ) . ' ' .
		( ! empty( $icon['flipVertical'] ) ? 'is-flip-vertical' : '' )
	);
}

/**
 * The stored rotation as an inline declaration. The individual `rotate`
 * property, not `transform`, which stays free for state (assets/icon.css).
 *
 * @param array $icon Icon reference.
 * @return string Declaration, or ''.
 */
function axismundi_dialogs_icon_rotation_css( array $icon ): string {
	$rotation = isset( $icon['rotation'] ) ? (int) $icon['rotation'] : 0;
	return $rotation ? 'rotate: ' . $rotation . 'deg;' : '';
}
