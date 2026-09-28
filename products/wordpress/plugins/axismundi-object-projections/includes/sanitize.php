<?php
/**
 * FEP-b2b8 aligned HTML sanitization for federated representations.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/**
 * Elements whose contents are meaningless or unsafe after the wrapper is removed.
 *
 * @return array<int,string>
 */
function axismundi_op_stripped_html_elements() : array {
	return array( 'script', 'style', 'button', 'nav', 'form', 'textarea', 'select', 'input', 'fieldset', 'iframe', 'embed', 'object' );
}

/**
 * Positive allowlist derived from FEP-b2b8, with WordPress document and safe MathML
 * extensions aligned with the official ActivityPub plugin.
 *
 * This deliberately does not inherit `wp_kses_allowed_html( 'post' )` or the global
 * `wp_kses_allowed_html` filter.
 *
 * @return array<string,array<string,bool>>
 */
function axismundi_op_allowed_html() : array {
	$allowed = array(
		'p'          => array(),
		'span'       => array( 'class' => true ),
		'br'         => array(),
		'a'          => array( 'href' => true, 'rel' => true, 'class' => true, 'title' => true ),
		'h1'         => array(),
		'h2'         => array(),
		'h3'         => array(),
		'h4'         => array(),
		'h5'         => array(),
		'h6'         => array(),
		'del'        => array(),
		'pre'        => array(),
		'code'       => array(),
		'em'         => array(),
		'strong'     => array(),
		'b'          => array(),
		'i'          => array(),
		'u'          => array(),
		'ul'         => array(),
		'ol'         => array( 'start' => true, 'reversed' => true ),
		'li'         => array( 'value' => true ),
		'blockquote' => array( 'cite' => true ),
		'img'        => array( 'src' => true, 'alt' => true, 'title' => true, 'width' => true, 'height' => true, 'class' => true ),
		'video'      => array( 'src' => true, 'controls' => true, 'loop' => true, 'poster' => true, 'width' => true, 'height' => true ),
		'audio'      => array( 'src' => true, 'controls' => true, 'loop' => true ),
		'source'     => array( 'src' => true, 'type' => true ),
		'ruby'       => array(),
		'rt'         => array(),
		'rp'         => array(),
		// Inert classes retain Core Gallery geometry and the shared sensitive-media
		// presentation for WordPress-to-WordPress Article content. No style, event,
		// data, or arbitrary attributes are admitted.
		'figure'     => array( 'class' => true ),
		'figcaption' => array(),
		'hr'         => array(),
		'div'        => array( 'class' => true ),
		'table'      => array(),
		'thead'      => array(),
		'tbody'      => array(),
		'tfoot'      => array(),
		'tr'         => array(),
		'th'         => array( 'colspan' => true, 'rowspan' => true ),
		'td'         => array( 'colspan' => true, 'rowspan' => true ),
		'caption'    => array(),
		'dl'         => array(),
		'dt'         => array(),
		'dd'         => array(),
		's'          => array(),
		'sub'        => array(),
		'sup'        => array(),
		'abbr'       => array( 'title' => true ),
		'mark'       => array(),
		'ins'        => array(),
		'cite'       => array(),
		'time'       => array( 'datetime' => true ),
		'track'      => array( 'src' => true, 'kind' => true, 'label' => true, 'srclang' => true ),
	);

	$math_global = array(
		'dir' => true, 'displaystyle' => true, 'mathbackground' => true,
		'mathcolor' => true, 'mathsize' => true, 'scriptlevel' => true,
		'intent' => true, 'arg' => true,
	);
	foreach ( array( 'merror', 'mi', 'mmultiscripts', 'mn', 'mover', 'mprescripts', 'mroot', 'mrow', 'ms', 'msqrt', 'mstyle', 'msub', 'msubsup', 'msup', 'mtable', 'mtext', 'mtr', 'munder' ) as $element ) {
		$allowed[ $element ] = $math_global;
	}
	$allowed['math']       = array_merge( $math_global, array( 'display' => true ) );
	$allowed['mfrac']      = array_merge( $math_global, array( 'linethickness' => true ) );
	$allowed['mo']         = array_merge( $math_global, array_fill_keys( array( 'form', 'fence', 'separator', 'lspace', 'rspace', 'stretchy', 'symmetric', 'maxsize', 'minsize', 'largeop', 'movablelimits' ), true ) );
	$allowed['mpadded']    = array_merge( $math_global, array_fill_keys( array( 'width', 'height', 'depth', 'lspace', 'voffset' ), true ) );
	$allowed['mspace']     = array_merge( $math_global, array( 'width' => true, 'height' => true, 'depth' => true ) );
	$allowed['mtd']        = array_merge( $math_global, array( 'columnspan' => true, 'rowspan' => true ) );
	$allowed['munderover'] = array_merge( $math_global, array( 'accent' => true, 'accentunder' => true ) );
	$allowed['semantics']  = array_merge( $math_global, array( 'encoding' => true ) );
	$allowed['annotation'] = array_merge( $math_global, array( 'encoding' => true ) );

	/**
	 * Filter the dedicated federated HTML allowlist.
	 *
	 * @since 0.0.13
	 * @param array<string,array<string,bool>> $allowed Positive allowlist.
	 */
	return (array) apply_filters( 'axismundi_op_allowed_html', $allowed );
}

/** Sanitize one rendered HTML fragment for federation. */
function axismundi_op_clean_html( string $html ) : string {
	if ( '' === $html ) {
		return '';
	}
	$elements = implode( '|', array_map( 'preg_quote', axismundi_op_stripped_html_elements() ) );
	$html     = (string) preg_replace( '@<(' . $elements . ')[^>]*?>.*?</\\1>@si', '', $html );
	$html     = (string) preg_replace( '@<(' . $elements . ')[^>]*?/?>@si', '', $html );
	return wp_kses( $html, axismundi_op_allowed_html(), wp_allowed_protocols() );
}

/**
 * The HTML one of this plugin's server-rendered blocks may emit.
 *
 * Escaping late is the rule, and `wp_kses_post()` is the usual answer, but measured against a
 * rendered Object card it silently removes things these blocks depend on: the `template` element
 * and `input` are not in the post set at all, and `srcset`, `decoding`, `draggable` and
 * `aria-pressed` are missing from the elements that carry them. Losing those does not look like
 * a security fix, it looks like the Interactivity API and responsive images quietly breaking.
 *
 * So the allowlist is the post set plus exactly what a block here renders. `data-*` is already
 * wildcarded by core, which is what carries the Interactivity directives; `data-wp-*` is not a
 * pattern KSES understands, so do not narrow it that way.
 *
 * `audit-object-renderer.php` asserts that filtering a full card changes nothing, so an element
 * or attribute added to a block without being added here fails a test rather than disappearing
 * from the page.
 *
 * @return array<string,array<string,bool>>
 */
function axismundi_op_allowed_block_html() : array {
	$allowed = wp_kses_allowed_html( 'post' );

	// Attributes core omits from the elements these blocks put them on.
	$extra = array(
		'srcset'       => true,
		'sizes'        => true,
		'decoding'     => true,
		'draggable'    => true,
		'aria-pressed' => true,
		'aria-busy'    => true,
		'inert'        => true,
		'part'         => true,
		'data-*'       => true,
	);
	foreach ( $allowed as $element => $attributes ) {
		$allowed[ $element ] = is_array( $attributes ) ? array_merge( $attributes, $extra ) : $extra;
	}

	$common = array_merge(
		array( 'class' => true, 'id' => true, 'style' => true, 'title' => true, 'role' => true, 'hidden' => true, 'tabindex' => true, 'lang' => true, 'dir' => true ),
		$extra
	);
	// Elements core's post set does not carry at all.
	$allowed['template'] = $common;
	$allowed['input']    = array_merge(
		$common,
		array( 'type' => true, 'name' => true, 'value' => true, 'placeholder' => true, 'checked' => true, 'disabled' => true, 'readonly' => true, 'required' => true, 'min' => true, 'max' => true, 'step' => true, 'autocomplete' => true, 'inputmode' => true, 'maxlength' => true, 'aria-controls' => true, 'aria-describedby' => true, 'aria-expanded' => true, 'aria-label' => true, 'aria-labelledby' => true )
	);

	/**
	 * Filter the HTML a rendered Object block may emit.
	 *
	 * @since 0.1.2
	 * @param array<string,array<string,bool>> $allowed Allowed elements and attributes.
	 */
	return (array) apply_filters( 'axismundi_op_allowed_block_html', $allowed );
}

/**
 * Escape one block's rendered HTML at the point it is printed.
 *
 * @param string $html Server-rendered block HTML.
 * @return string
 */
function axismundi_op_kses_block_html( string $html ) : string {
	return wp_kses( $html, axismundi_op_allowed_block_html() );
}
