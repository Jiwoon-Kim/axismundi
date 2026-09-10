<?php
/**
 * axismundi/dialog-icon — server render.
 *
 * A decorative leading icon for a basic dialog header (M3 optional hero icon).
 *
 * The icon comes from one of two sources, and the block stores which one it
 * meant rather than guessing from the value:
 *
 *   font      a Material Symbols ligature from an icon font ("info").
 *   registry  a WordPress Icon Registry reference ("core/info"), stored in the
 *             same form core/icon stores it, so the two blocks share one
 *             identity for the same icon.
 *
 * The source is explicit because the value alone stops being enough as soon as
 * there is more than one icon font: a slash tells registry from font, but it
 * cannot tell one font from another. `font` is the default, which is what keeps
 * every dialog-icon saved before this attribute existed rendering exactly as it
 * did.
 *
 * The value is stored in each source's own format and not split into
 * provider/collection/name: Core owns the registry identity, and a provider
 * layer, if Core adds one, is Core's format to define.
 *
 * GAP: Font Library currently identifies a font family, not an icon-font
 * provider. It cannot tell this block which glyph names, ligature behaviour,
 * fixed icon-slot rules, or variable axes a selected font supplies. Font
 * choice belongs in this block's Source settings, not Typography: the latter
 * serializes a text-style `has-*-font-family` class. The editor currently
 * lists active Font Library families as an experiment, including text fonts.
 * A future Core icon-font flag must filter that list and expose each eligible
 * provider's axes. iconClass carries the provider's rendering class (for
 * example, material-symbols-outlined), not a typography preset.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

$axismundi_dialogs_icon_source = 'registry' === ( $attributes['iconSource'] ?? 'font' ) ? 'registry' : 'font';
// Keep an intentional empty value empty. Only legacy content which predates
// the icon attribute receives the source's default icon.
$axismundi_dialogs_icon_value  = array_key_exists( 'icon', $attributes )
	? (string) $attributes['icon']
	: ( 'registry' === $axismundi_dialogs_icon_source ? 'core/info' : 'info' );
$axismundi_dialogs_icon_tag    = 'div' === ( $attributes['tagName'] ?? 'span' ) ? 'div' : 'span';
$axismundi_dialogs_icon_class  = sanitize_html_class( (string) ( $attributes['iconClass'] ?? 'material-symbols-outlined' ) );
$axismundi_dialogs_icon_class  = '' !== $axismundi_dialogs_icon_class ? $axismundi_dialogs_icon_class : 'material-symbols-outlined';
$axismundi_dialogs_typography  = $attributes['style']['typography'] ?? array();
$axismundi_dialogs_variation_settings = $axismundi_dialogs_typography['fontVariationSettings'] ?? array();
$axismundi_dialogs_axes = array();

if ( is_array( $axismundi_dialogs_variation_settings ) ) {
	foreach ( $axismundi_dialogs_variation_settings as $axismundi_dialogs_setting ) {
		if ( is_array( $axismundi_dialogs_setting ) ) {
			$axismundi_dialogs_axes = array_merge( $axismundi_dialogs_axes, $axismundi_dialogs_setting );
		}
	}
}

// `fontVariationSettings` is dialog-icon's forward-compatible block-style
// contract. The two fallback forms keep content saved by the pre-contract
// experiment rendering unchanged.
$axismundi_dialogs_icon_weight = (int) ( $axismundi_dialogs_axes['wght'] ?? $axismundi_dialogs_typography['wght'] ?? $attributes['iconWeight'] ?? $axismundi_dialogs_typography['fontWeight'] ?? 400 );
$axismundi_dialogs_icon_weight = min( 700, max( 100, $axismundi_dialogs_icon_weight ?: 400 ) );
$axismundi_dialogs_icon_fill   = '1' === (string) ( $axismundi_dialogs_axes['FILL'] ?? 0 ) ? 1 : 0;
$axismundi_dialogs_icon_grade  = (int) ( $axismundi_dialogs_axes['GRAD'] ?? 0 );
$axismundi_dialogs_icon_grade  = min( 200, max( -25, $axismundi_dialogs_icon_grade ) );
$axismundi_dialogs_icon_opsz   = (int) ( $axismundi_dialogs_axes['opsz'] ?? 24 );
$axismundi_dialogs_icon_opsz   = in_array( $axismundi_dialogs_icon_opsz, array( 20, 24, 40, 48 ), true ) ? $axismundi_dialogs_icon_opsz : 24;
$axismundi_dialogs_icon_axes = array();
if ( array_key_exists( 'fontSize', $attributes ) || array_key_exists( 'fontSize', $axismundi_dialogs_typography ) ) {
	$axismundi_dialogs_icon_size = ! empty( $attributes['fontSize'] )
		? 'var:preset|font-size|' . $attributes['fontSize']
		: $axismundi_dialogs_typography['fontSize'];
	$axismundi_dialogs_icon_size = function_exists( 'wp_get_typography_font_size_value' )
		? wp_get_typography_font_size_value( array( 'size' => $axismundi_dialogs_icon_size ) )
		: $axismundi_dialogs_icon_size;
	if ( is_string( $axismundi_dialogs_icon_size ) && '' !== $axismundi_dialogs_icon_size ) {
		$axismundi_dialogs_icon_axes[] = '--md-icon-size: ' . $axismundi_dialogs_icon_size . ';';
	}
}
if ( array_key_exists( 'FILL', $axismundi_dialogs_axes ) ) {
	$axismundi_dialogs_icon_axes[] = '--md-icon-fill: ' . $axismundi_dialogs_icon_fill . ';';
}
if ( array_key_exists( 'wght', $axismundi_dialogs_axes ) || array_key_exists( 'wght', $axismundi_dialogs_typography ) || array_key_exists( 'iconWeight', $attributes ) || array_key_exists( 'fontWeight', $axismundi_dialogs_typography ) ) {
	$axismundi_dialogs_icon_axes[] = '--md-icon-wght: ' . $axismundi_dialogs_icon_weight . ';';
}
if ( array_key_exists( 'GRAD', $axismundi_dialogs_axes ) ) {
	$axismundi_dialogs_icon_axes[] = '--md-icon-grad: ' . $axismundi_dialogs_icon_grade . ';';
}
if ( array_key_exists( 'opsz', $axismundi_dialogs_axes ) ) {
	$axismundi_dialogs_icon_axes[] = '--md-icon-opsz: ' . $axismundi_dialogs_icon_opsz . ';';
}
$axismundi_dialogs_icon_axes = implode( ' ', $axismundi_dialogs_icon_axes );

// Colour, border and padding skip serialisation (block.json), so WordPress
// writes none of them onto the wrapper and this file has to apply them itself.
// They are computed once, for both sources, and then land on whichever element
// is actually painted: the SVG on the registry path, as core/icon does, and the
// glyph wrapper on the font path, where the wrapper IS the icon.
//
// Computing them for the registry path alone - which this file once did - let
// the inspector offer colour, background, border and padding on a font icon and
// then drop every one of them: a chosen textColor never reached the page, and
// the template looked right only because style.css defaults to secondary.
$axismundi_dialogs_color_styles = array(
	'text'       => array_key_exists( 'textColor', $attributes ) ? "var:preset|color|{$attributes['textColor']}" : ( $attributes['style']['color']['text'] ?? null ),
	'background' => array_key_exists( 'backgroundColor', $attributes ) ? "var:preset|color|{$attributes['backgroundColor']}" : ( $attributes['style']['color']['background'] ?? null ),
);
$axismundi_dialogs_border_styles = array();
foreach ( array( 'top', 'right', 'bottom', 'left' ) as $axismundi_dialogs_side ) {
	$axismundi_dialogs_border = $attributes['style']['border'][ $axismundi_dialogs_side ] ?? null;
	$axismundi_dialogs_border_styles[ $axismundi_dialogs_side ] = array(
		'color' => $axismundi_dialogs_border['color'] ?? null,
		'style' => $axismundi_dialogs_border['style'] ?? null,
		'width' => $axismundi_dialogs_border['width'] ?? null,
	);
}
$axismundi_dialogs_border_styles['radius'] = $attributes['style']['border']['radius'] ?? null;
$axismundi_dialogs_border_styles['style']  = $attributes['style']['border']['style'] ?? null;
$axismundi_dialogs_border_styles['width']  = $attributes['style']['border']['width'] ?? null;
$axismundi_dialogs_border_styles['color']  = array_key_exists( 'borderColor', $attributes ) ? "var:preset|color|{$attributes['borderColor']}" : ( $attributes['style']['border']['color'] ?? null );

$axismundi_dialogs_box_styles = array(
	'color'   => $axismundi_dialogs_color_styles,
	'border'  => $axismundi_dialogs_border_styles,
	'spacing' => array( 'padding' => $attributes['style']['spacing']['padding'] ?? null ),
);

if ( 'registry' === $axismundi_dialogs_icon_source ) {
	// Match core/icon's split: width, padding, border, and colour belong to the
	// SVG; margin and alignment belong to this block wrapper. Width is registry
	// only - a glyph is sized by its font-size, not by a box width.
	$axismundi_dialogs_styles = wp_style_engine_get_styles(
		$axismundi_dialogs_box_styles + array(
			'dimensions' => array( 'width' => $attributes['style']['dimensions']['width'] ?? null ),
		)
	);

	// wp_get_icon() looks the name up exactly and returns '' for anything
	// unregistered. With no label it marks the SVG aria-hidden and unfocusable.
	$axismundi_dialogs_icon_markup = function_exists( 'wp_get_icon' )
		? wp_get_icon(
			$axismundi_dialogs_icon_value,
			array(
				'size'  => null,
				'class' => $axismundi_dialogs_styles['classnames'] ?? '',
				'label' => $attributes['ariaLabel'] ?? '',
			)
		)
		: '';

	// Nothing rather than a placeholder: the icon is decoration, and an empty
	// box suggests something failed to load.
	if ( '' === $axismundi_dialogs_icon_markup ) {
		return;
	}

	$axismundi_dialogs_processor = new WP_HTML_Tag_Processor( $axismundi_dialogs_icon_markup );
	if ( $axismundi_dialogs_processor->next_tag( 'svg' ) ) {
		if ( ! empty( $axismundi_dialogs_styles['css'] ) ) {
			$axismundi_dialogs_existing_style = $axismundi_dialogs_processor->get_attribute( 'style' );
			$axismundi_dialogs_existing_style = is_string( $axismundi_dialogs_existing_style ) ? rtrim( trim( $axismundi_dialogs_existing_style ), ';' ) : '';
			$axismundi_dialogs_processor->set_attribute( 'style', '' !== $axismundi_dialogs_existing_style ? $axismundi_dialogs_existing_style . '; ' . $axismundi_dialogs_styles['css'] : $axismundi_dialogs_styles['css'] );
		}
		if ( ! empty( $attributes['flipHorizontal'] ) ) {
			$axismundi_dialogs_processor->add_class( 'is-flip-horizontal' );
		}
		if ( ! empty( $attributes['flipVertical'] ) ) {
			$axismundi_dialogs_processor->add_class( 'is-flip-vertical' );
		}
		$axismundi_dialogs_rotation = isset( $attributes['rotation'] ) ? (int) $attributes['rotation'] : 0;
		if ( $axismundi_dialogs_rotation ) {
			$axismundi_dialogs_existing_style = $axismundi_dialogs_processor->get_attribute( 'style' );
			$axismundi_dialogs_existing_style = is_string( $axismundi_dialogs_existing_style ) ? rtrim( trim( $axismundi_dialogs_existing_style ), ';' ) : '';
			$axismundi_dialogs_rotation_style = 'rotate: ' . $axismundi_dialogs_rotation . 'deg;';
			$axismundi_dialogs_processor->set_attribute( 'style', '' !== $axismundi_dialogs_existing_style ? $axismundi_dialogs_existing_style . '; ' . $axismundi_dialogs_rotation_style : $axismundi_dialogs_rotation_style );
		}
		$axismundi_dialogs_icon_markup = $axismundi_dialogs_processor->get_updated_html();
	}

	$axismundi_dialogs_icon_attrs = array(
		'class'               => 'ax-dialog-icon ax-dialog-icon--registry',
		'data-ax-dialog-icon' => 'true',
	);
	if ( empty( $attributes['ariaLabel'] ) ) {
		$axismundi_dialogs_icon_attrs['aria-hidden'] = 'true';
	}
} else {
	$axismundi_dialogs_icon_name   = preg_replace( '/[^a-z0-9_]/', '', strtolower( $axismundi_dialogs_icon_value ) );
	if ( '' === $axismundi_dialogs_icon_name ) {
		return;
	}
	$axismundi_dialogs_icon_markup = esc_html( $axismundi_dialogs_icon_name );

	// Same attributes in the same order as before iconSource existed.
	$axismundi_dialogs_icon_attrs = array(
		'class'               => 'ax-dialog-icon ' . $axismundi_dialogs_icon_class . ' notranslate',
		'style'               => $axismundi_dialogs_icon_axes,
		'translate'           => 'no',
		'aria-hidden'         => 'true',
		'data-ax-dialog-icon' => 'true',
	);

	// The glyph wrapper is the painted element here, so the skipped supports
	// land on it - serialised exactly as the block supports themselves would
	// have serialised them onto this same wrapper, because that is what they
	// replace. Colour goes through convert_vars_to_classnames, as
	// block-supports/colors.php does: a preset is a class, not an inline var.
	// Border and padding do not, as border.php and spacing.php do not - with
	// the option a spacing preset has no class to become, and would be lost.
	//
	// The payoff is that saved content keeps its markup. A template's
	// textColor renders `has-text-color has-secondary-color` after the block
	// class, as it did before colour skipped serialisation.
	$axismundi_dialogs_color_css = wp_style_engine_get_styles(
		array( 'color' => $axismundi_dialogs_box_styles['color'] ),
		array( 'convert_vars_to_classnames' => true )
	);
	$axismundi_dialogs_box_css   = wp_style_engine_get_styles(
		array(
			'border'  => $axismundi_dialogs_box_styles['border'],
			'spacing' => $axismundi_dialogs_box_styles['spacing'],
		)
	);
	$axismundi_dialogs_styles    = array(
		'classnames' => trim( ( $axismundi_dialogs_color_css['classnames'] ?? '' ) . ' ' . ( $axismundi_dialogs_box_css['classnames'] ?? '' ) ),
		'css'        => trim( ( $axismundi_dialogs_color_css['css'] ?? '' ) . ( $axismundi_dialogs_box_css['css'] ?? '' ) ),
	);
}

// The wrapper is kept on both paths: margin and alignment belong to it. The
// font classes stay off the registry path: an <svg> has no use for a glyph
// font, and the ligature rules would size it.
$axismundi_dialogs_icon_wrapper = get_block_wrapper_attributes( $axismundi_dialogs_icon_attrs );
$axismundi_dialogs_icon_html    = sprintf(
	'<%1$s %2$s>%3$s</%1$s>',
	tag_escape( $axismundi_dialogs_icon_tag ),
	$axismundi_dialogs_icon_wrapper,
	$axismundi_dialogs_icon_markup
);

if ( 'font' === $axismundi_dialogs_icon_source && ( ! empty( $axismundi_dialogs_styles['classnames'] ) || ! empty( $axismundi_dialogs_styles['css'] ) ) ) {
	$axismundi_dialogs_processor = new WP_HTML_Tag_Processor( $axismundi_dialogs_icon_html );
	if ( $axismundi_dialogs_processor->next_tag() ) {
		foreach ( preg_split( '/\s+/', (string) ( $axismundi_dialogs_styles['classnames'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY ) as $axismundi_dialogs_class_name ) {
			$axismundi_dialogs_processor->add_class( $axismundi_dialogs_class_name );
		}
		if ( ! empty( $axismundi_dialogs_styles['css'] ) ) {
			$axismundi_dialogs_existing_style = $axismundi_dialogs_processor->get_attribute( 'style' );
			$axismundi_dialogs_existing_style = is_string( $axismundi_dialogs_existing_style ) ? rtrim( trim( $axismundi_dialogs_existing_style ), ';' ) : '';
			$axismundi_dialogs_processor->set_attribute( 'style', '' !== $axismundi_dialogs_existing_style ? $axismundi_dialogs_existing_style . '; ' . $axismundi_dialogs_styles['css'] : $axismundi_dialogs_styles['css'] );
		}
		$axismundi_dialogs_icon_html = $axismundi_dialogs_processor->get_updated_html();
	}
}

echo $axismundi_dialogs_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- glyph escaped above, SVG sanitised by wp_get_icon(), wrapper from get_block_wrapper_attributes().
