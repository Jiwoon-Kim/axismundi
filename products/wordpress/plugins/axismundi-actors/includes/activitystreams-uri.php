<?php
/**
 * ActivityStreams URI identity and member normalization.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/** Normalize one URI syntactically without dereferencing it. */
function axismundi_as2_normalize_uri( $value ) : ?array {
	if ( ! is_scalar( $value ) ) {
		return null;
	}
	$uri = trim( (string) $value );
	if ( '' === $uri || preg_match( '/[\x00-\x20]/', $uri ) || ! preg_match( '/^[A-Za-z][A-Za-z0-9+.-]*:/', $uri ) ) {
		return null;
	}
	$parts  = wp_parse_url( $uri );
	$scheme = strtolower( (string) ( is_array( $parts ) ? ( $parts['scheme'] ?? '' ) : '' ) );
	if ( '' === $scheme ) {
		return null;
	}
	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		return array( 'uri' => $uri, 'resolvable' => false );
	}
	if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
		return null;
	}
	$host = strtolower( (string) $parts['host'] );
	if ( function_exists( 'idn_to_ascii' ) && preg_match( '/[^\x00-\x7f]/', $host ) ) {
		$idna = idn_to_ascii( $host, defined( 'IDNA_DEFAULT' ) ? IDNA_DEFAULT : 0, defined( 'INTL_IDNA_VARIANT_UTS46' ) ? INTL_IDNA_VARIANT_UTS46 : 0 );
		if ( false === $idna || '' === $idna ) {
			return null;
		}
		$host = strtolower( $idna );
	}
	$port = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
	$port = ( 'http' === $scheme && 80 === $port ) || ( 'https' === $scheme && 443 === $port ) ? 0 : $port;
	$path = preg_replace_callback( '/%[0-9a-fA-F]{2}/', static fn( array $m ) : string => strtoupper( $m[0] ), (string) ( $parts['path'] ?? '' ) );
	$path = axismundi_as2_remove_dot_segments( (string) $path );
	$query = preg_replace_callback( '/%[0-9a-fA-F]{2}/', static fn( array $m ) : string => strtoupper( $m[0] ), (string) ( $parts['query'] ?? '' ) );
	$fragment = preg_replace_callback( '/%[0-9a-fA-F]{2}/', static fn( array $m ) : string => strtoupper( $m[0] ), (string) ( $parts['fragment'] ?? '' ) );
	$authority = str_contains( $host, ':' ) ? '[' . $host . ']' : $host;
	$normalized = $scheme . '://' . $authority . ( $port > 0 ? ':' . $port : '' ) . $path;
	if ( '' !== $query ) {
		$normalized .= '?' . $query;
	}
	if ( '' !== $fragment ) {
		$normalized .= '#' . $fragment;
	}
	return array( 'uri' => $normalized, 'resolvable' => true );
}

/** RFC 3986 section 5.2.4 dot-segment removal without touching slash policy. */
function axismundi_as2_remove_dot_segments( string $path ) : string {
	$input = $path;
	$output = '';
	while ( '' !== $input ) {
		if ( str_starts_with( $input, '../' ) ) { $input = substr( $input, 3 ); continue; }
		if ( str_starts_with( $input, './' ) ) { $input = substr( $input, 2 ); continue; }
		if ( str_starts_with( $input, '/./' ) || '/.' === $input ) { $input = '/' . ltrim( substr( $input, 2 ), '/' ); continue; }
		if ( str_starts_with( $input, '/../' ) || '/..' === $input ) { $input = '/' . ltrim( substr( $input, 3 ), '/' ); $output = preg_replace( '#/[^/]*$#', '', $output ) ?: ''; continue; }
		if ( '.' === $input || '..' === $input ) { $input = ''; continue; }
		$slash = strpos( ltrim( $input, '/' ), '/' );
		$length = false === $slash ? strlen( $input ) : $slash + ( str_starts_with( $input, '/' ) ? 1 : 0 );
		if ( $length <= 0 ) { $length = strlen( $input ); }
		$output .= substr( $input, 0, $length );
		$input = substr( $input, $length );
	}
	return $output;
}

/** Return every valid AS2 member in document order; never choose a primary member. */
function axismundi_as2_normalize_members( $value ) : array {
	if ( is_array( $value ) && array_is_list( $value ) ) {
		$out = array();
		foreach ( $value as $member ) {
			array_push( $out, ...axismundi_as2_normalize_members( $member ) );
		}
		return $out;
	}
	$link = is_array( $value ) ? $value : array();
	$raw  = is_array( $value ) ? ( $value['href'] ?? $value['id'] ?? null ) : $value;
	$uri  = axismundi_as2_normalize_uri( $raw );
	if ( null === $uri ) {
		return array();
	}
	return array(
		array(
			'uri'        => $uri['uri'],
			'resolvable' => (bool) $uri['resolvable'],
			'link_id'    => is_array( $value ) && isset( $value['href'], $value['id'] ) ? (string) $value['id'] : null,
			'link_rel'   => $link['rel'] ?? null,
			'link_name'  => isset( $link['name'] ) ? (string) $link['name'] : null,
			'link_media_type' => isset( $link['mediaType'] ) ? (string) $link['mediaType'] : null,
		)
	);
}

/** Whether a present member value explicitly means no members, rather than unreadable input. */
function axismundi_as2_members_explicitly_empty( $value ) : bool {
	return null === $value || ( is_string( $value ) && '' === trim( $value ) ) || ( is_array( $value ) && array_is_list( $value ) && empty( $value ) );
}
