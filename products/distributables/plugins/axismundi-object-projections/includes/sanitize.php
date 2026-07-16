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
		'img'        => array( 'src' => true, 'alt' => true, 'title' => true, 'width' => true, 'height' => true ),
		'video'      => array( 'src' => true, 'controls' => true, 'loop' => true, 'poster' => true, 'width' => true, 'height' => true ),
		'audio'      => array( 'src' => true, 'controls' => true, 'loop' => true ),
		'source'     => array( 'src' => true, 'type' => true ),
		'ruby'       => array(),
		'rt'         => array(),
		'rp'         => array(),
		'figure'     => array(),
		'figcaption' => array(),
		'hr'         => array(),
		'div'        => array(),
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
