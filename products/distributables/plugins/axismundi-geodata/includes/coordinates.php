<?php
/**
 * Coordinate interoperability formatters.
 *
 * One canonical CRS everywhere: WGS 84 (EPSG:4326). These helpers project a stored
 * lat/lng (+ optional altitude / accuracy) to standard string forms. Coordinate
 * ORDER differs by format — mixing it up is the classic "Busan ends up in the sea"
 * bug — so the function names spell out which order they emit:
 *
 *   axismundi_geodata_coords_to_geo_uri()          geo:LAT,LNG       (RFC 5870)
 *   axismundi_geodata_coords_to_iso6709()          +LAT+LNG/         (ISO 6709)
 *   axismundi_geodata_coords_to_geojson_position()  [LNG, LAT]        (RFC 7946)
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * A decimal-degree value as a trimmed string (no trailing zeros), e.g. "35.1547".
 *
 * @param mixed $value    Numeric value.
 * @param int   $decimals Max fractional digits (7 ≈ centimetre precision).
 * @return string
 */
function axismundi_geodata_fmt_decimal( $value, int $decimals = 7 ) : string {
	$out = number_format( (float) $value, $decimals, '.', '' );
	if ( false !== strpos( $out, '.' ) ) {
		$out = rtrim( rtrim( $out, '0' ), '.' );
	}

	return $out;
}

/**
 * A sign-prefixed, zero-padded ISO 6709 degree field, e.g. "+35.1547" / "+129.1199".
 *
 * @param mixed $value      Numeric value.
 * @param int   $int_digits Integer-part width (2 for latitude, 3 for longitude).
 * @return string
 */
function axismundi_geodata_fmt_iso6709_part( $value, int $int_digits ) : string {
	$sign  = (float) $value < 0 ? '-' : '+';
	$parts = explode( '.', axismundi_geodata_fmt_decimal( abs( (float) $value ) ) );
	$parts[0] = str_pad( $parts[0], $int_digits, '0', STR_PAD_LEFT );

	return $sign . implode( '.', $parts );
}

/**
 * A WGS 84 point as a Geo URI (RFC 5870): geo:LAT,LNG[,ALT][;u=ACCURACY].
 *
 * CRS is omitted because RFC 5870's default is already WGS 84. `u` carries the
 * accuracy as an uncertainty radius in metres.
 *
 * @param mixed      $lat      Latitude.
 * @param mixed      $lng      Longitude.
 * @param mixed|null $alt      Altitude in metres, or null.
 * @param mixed|null $accuracy Accuracy radius in metres, or null.
 * @return string Geo URI, or '' when the coordinate is missing.
 */
function axismundi_geodata_coords_to_geo_uri( $lat, $lng, $alt = null, $accuracy = null ) : string {
	if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
		return '';
	}

	$uri = 'geo:' . axismundi_geodata_fmt_decimal( $lat ) . ',' . axismundi_geodata_fmt_decimal( $lng );
	if ( is_numeric( $alt ) ) {
		$uri .= ',' . axismundi_geodata_fmt_decimal( $alt );
	}
	if ( is_numeric( $accuracy ) && (float) $accuracy > 0 ) {
		$uri .= ';u=' . axismundi_geodata_fmt_decimal( $accuracy );
	}

	return $uri;
}

/**
 * A WGS 84 point as an ISO 6709 Annex H string: +LAT+LNG[+ALT]/ (lat then lng).
 *
 * @param mixed      $lat Latitude.
 * @param mixed      $lng Longitude.
 * @param mixed|null $alt Altitude in metres, or null.
 * @return string ISO 6709 string, or '' when the coordinate is missing.
 */
function axismundi_geodata_coords_to_iso6709( $lat, $lng, $alt = null ) : string {
	if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
		return '';
	}

	$out = axismundi_geodata_fmt_iso6709_part( $lat, 2 ) . axismundi_geodata_fmt_iso6709_part( $lng, 3 );
	if ( is_numeric( $alt ) ) {
		$out .= axismundi_geodata_fmt_iso6709_part( $alt, 1 );
	}

	return $out . '/';
}

/**
 * A WGS 84 point as a GeoJSON position array (RFC 7946): [LNG, LAT[, ALT]].
 *
 * The one place lon/lat order is correct — used when building GeoJSON geometry.
 *
 * @param mixed      $lat Latitude.
 * @param mixed      $lng Longitude.
 * @param mixed|null $alt Altitude in metres, or null.
 * @return array<int,float> [lng, lat] or [lng, lat, alt], or [] when missing.
 */
function axismundi_geodata_coords_to_geojson_position( $lat, $lng, $alt = null ) : array {
	if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
		return array();
	}

	$position = array( (float) $lng, (float) $lat );
	if ( is_numeric( $alt ) ) {
		$position[] = (float) $alt;
	}

	return $position;
}
