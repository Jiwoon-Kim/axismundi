<?php
/**
 * Privacy / public-precision model.
 *
 * The store keeps the raw, exact coordinate; what gets EXPOSED (REST public
 * views, federation, map embeds) is always passed through the post's public
 * precision first. Two independent gates:
 *
 *   geo_public               bool   — is any coordinate exposed at all?
 *   ax_geo_public_precision  enum   — how precise is the exposed coordinate?
 *
 * `geo_public = false` means the raw coordinate never leaves the site, whatever
 * the precision says. This file owns the enum and the rounding; callers (REST,
 * the future ActivityStreams Place serializer) ask it for the public coordinate
 * rather than reading the raw meta directly.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Allowed public-precision values, coarsest first, mapped to the number of
 * decimal places the exposed coordinate is rounded to.
 *
 *   hidden        — never exposed (null)
 *   city          — ~11 km     (1 dp)
 *   coarse        — ~1.1 km    (2 dp)   [default]
 *   neighborhood  — ~110 m     (3 dp)
 *   exact         — full precision (no rounding)
 *
 * @return array<string,int|null>
 */
function axismundi_geodata_precision_levels() : array {
	return array(
		'hidden'       => null,
		'city'         => 1,
		'coarse'       => 2,
		'neighborhood' => 3,
		'exact'        => -1, // sentinel: no rounding.
	);
}

/**
 * The default public precision for new content (privacy-first: coarse, not exact).
 *
 * @return string
 */
function axismundi_geodata_default_precision() : string {
	return (string) apply_filters( 'axismundi_geodata_default_precision', 'coarse' );
}

/**
 * Normalise an arbitrary value to a valid precision key, falling back to the default.
 *
 * @param mixed $value Raw precision value.
 * @return string
 */
function axismundi_geodata_sanitize_precision( $value ) : string {
	$value = is_string( $value ) ? $value : '';

	return array_key_exists( $value, axismundi_geodata_precision_levels() )
		? $value
		: axismundi_geodata_default_precision();
}

/**
 * Reduce a raw coordinate to the precision a viewer is allowed to see.
 *
 * Returns null when the precision is `hidden`, so callers can drop the location
 * entirely. `exact` returns the value untouched.
 *
 * @param float  $latitude  Raw latitude.
 * @param float  $longitude Raw longitude.
 * @param string $precision Precision key.
 * @return array{latitude:float,longitude:float}|null
 */
function axismundi_geodata_apply_precision( float $latitude, float $longitude, string $precision ) : ?array {
	$levels = axismundi_geodata_precision_levels();
	$key    = array_key_exists( $precision, $levels ) ? $precision : axismundi_geodata_default_precision();
	$dp     = $levels[ $key ];

	if ( null === $dp ) {
		return null; // hidden.
	}

	if ( -1 === $dp ) {
		return array(
			'latitude'  => $latitude,
			'longitude' => $longitude,
		); // exact.
	}

	return array(
		'latitude'  => round( $latitude, $dp ),
		'longitude' => round( $longitude, $dp ),
	);
}

/**
 * Whether an object's coordinate may be exposed at all (the geo_public gate).
 *
 * @param int    $object_id   Post or attachment ID.
 * @param string $object_type 'post' (covers attachments) for now.
 * @return bool
 */
function axismundi_geodata_is_public( int $object_id, string $object_type = 'post' ) : bool {
	$public = get_metadata( $object_type, $object_id, 'geo_public', true );

	return ! empty( $public ) && '0' !== (string) $public;
}

/**
 * The public-facing coordinate for an object, already gated and rounded — the
 * single entry point REST and federation serializers should use instead of
 * reading the raw geo_latitude / geo_longitude meta.
 *
 * @param int    $object_id   Post or attachment ID.
 * @param string $object_type Meta object type ('post').
 * @return array{latitude:float,longitude:float}|null Null when private, hidden, or unset.
 */
function axismundi_geodata_public_coordinate( int $object_id, string $object_type = 'post' ) : ?array {
	if ( ! axismundi_geodata_is_public( $object_id, $object_type ) ) {
		return null;
	}

	$lat = get_metadata( $object_type, $object_id, 'geo_latitude', true );
	$lng = get_metadata( $object_type, $object_id, 'geo_longitude', true );

	if ( '' === $lat || '' === $lng || null === $lat || null === $lng ) {
		return null;
	}

	$precision = axismundi_geodata_sanitize_precision(
		get_metadata( $object_type, $object_id, 'ax_geo_public_precision', true )
	);

	return axismundi_geodata_apply_precision( (float) $lat, (float) $lng, $precision );
}
