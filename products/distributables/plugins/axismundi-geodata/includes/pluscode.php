<?php
/**
 * Open Location Code (Plus Code) decoding.
 *
 * Decodes a FULL Plus Code (the kind with at least 8 digits before the '+', e.g.
 * "8Q7XMQVC+9G") to the centre latitude/longitude of its cell. Short codes —
 * the "8Q7X+5H 부산" form that needs a reference locality — are NOT decoded here;
 * recovering those requires geocoding the locality, which lands with the
 * geocoding adapter. This keeps the term-editor convenience standalone and
 * dependency-free.
 *
 * Algorithm: Google Open Location Code (Apache-2.0 spec), floating-point decode.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Decode a full Plus Code to its centre coordinate.
 *
 * @param string $code A full Open Location Code (no locality suffix).
 * @return array{latitude:float,longitude:float}|null Null if not a decodable full code.
 */
function axismundi_geodata_decode_plus_code( string $code ) : ?array {
	$alphabet = '23456789CFGHJMPQRVWX';
	$code     = strtoupper( trim( $code ) );

	// A locality-qualified short code ("8Q7X+5H Busan") can't be decoded alone.
	if ( false !== strpos( $code, ' ' ) ) {
		return null;
	}

	$sep = strpos( $code, '+' );
	if ( false === $sep || $sep < 8 || 0 !== $sep % 2 ) {
		return null; // not a full code.
	}

	$digits = str_replace( '+', '', $code );
	$digits = rtrim( $digits, '0' ); // drop trailing padding, if any.
	$len    = strlen( $digits );

	if ( $len < 8 ) {
		return null;
	}
	if ( strlen( $digits ) !== strspn( $digits, $alphabet ) ) {
		return null; // illegal character.
	}

	$pair_resolutions = array( 20.0, 1.0, 0.05, 0.0025, 0.000125 );

	$latitude  = -90.0;
	$longitude = -180.0;

	// Pair section: up to 10 digits, alternating lat / lng.
	$pair_len = min( $len, 10 );
	for ( $i = 0; $i < $pair_len; $i++ ) {
		$value = strpos( $alphabet, $digits[ $i ] );
		$res   = $pair_resolutions[ intdiv( $i, 2 ) ];
		if ( 0 === $i % 2 ) {
			$latitude += $value * $res;
		} else {
			$longitude += $value * $res;
		}
	}

	$lat_res = $pair_resolutions[ intdiv( $pair_len, 2 ) - 1 ];
	$lng_res = $lat_res;

	// Grid refinement section: digits 10+, a 5-row x 4-column grid per digit.
	if ( $len > 10 ) {
		$lat_place = 0.000125;
		$lng_place = 0.000125;
		$grid_len  = min( $len, 15 );
		for ( $i = 10; $i < $grid_len; $i++ ) {
			$lat_place /= 5.0;
			$lng_place /= 4.0;
			$value      = strpos( $alphabet, $digits[ $i ] );
			$latitude  += intdiv( $value, 4 ) * $lat_place;
			$longitude += ( $value % 4 ) * $lng_place;
		}
		$lat_res = $lat_place;
		$lng_res = $lng_place;
	}

	// Return the centre of the cell, clamped to valid ranges.
	return array(
		'latitude'  => max( -90.0, min( 90.0, $latitude + $lat_res / 2.0 ) ),
		'longitude' => max( -180.0, min( 180.0, $longitude + $lng_res / 2.0 ) ),
	);
}
