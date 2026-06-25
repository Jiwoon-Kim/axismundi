<?php
/**
 * Geo metadata registry.
 *
 * Object (post/page/attachment) coordinate meta = the observation / capture
 * point. Term (geo_area/geotag) meta = the named place's facts (centroid,
 * radius, bounds, external id). All single-value, REST-exposed with a schema,
 * sanitised, and edit-gated.
 *
 * WordPress / W3C Geolocation convention keys (geo_latitude, geo_longitude,
 * geo_public, geo_address, geo_altitude, geo_accuracy) stay unprefixed for
 * interop; Axismundi-specific facts (precision, source, plus code, place id, area
 * link, radius, bounds, place type) use the ax_geo_* namespace. The RAW exact
 * coordinate is stored here; public exposure goes through privacy.php, never by
 * reading these keys directly.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Clamp a latitude to [-90, 90].
 *
 * @param mixed $value Raw value.
 * @return float
 */
function axismundi_geodata_sanitize_latitude( $value ) : float {
	return max( -90.0, min( 90.0, (float) $value ) );
}

/**
 * Clamp a longitude to [-180, 180].
 *
 * @param mixed $value Raw value.
 * @return float
 */
function axismundi_geodata_sanitize_longitude( $value ) : float {
	return max( -180.0, min( 180.0, (float) $value ) );
}

/**
 * Clamp a value to a non-negative float (altitude offsets aside, radius /
 * accuracy are non-negative metres).
 *
 * @param mixed $value Raw value.
 * @return float
 */
function axismundi_geodata_sanitize_nonneg( $value ) : float {
	return max( 0.0, (float) $value );
}

/**
 * Cast to float, tolerant of the extra args register_meta passes a sanitize
 * callback. A bare built-in like floatval() throws ArgumentCountError on PHP 8
 * when handed ($value, $meta_key, $object_type, $subtype), which aborts the REST
 * meta save mid-loop — so altitude needs this wrapper, not floatval.
 *
 * @param mixed $value Raw value.
 * @return float
 */
function axismundi_geodata_sanitize_float( $value ) : float {
	return (float) $value;
}

/**
 * Normalise an ISO 3166-1 alpha-2 country code (e.g. "kr" -> "KR").
 *
 * @param mixed $value Raw value.
 * @return string
 */
function axismundi_geodata_sanitize_country_code( $value ) : string {
	return substr( strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) ), 0, 2 );
}

/**
 * The edit capability gate shared by every geo post meta key.
 *
 * @param bool   $allowed   Default permission.
 * @param string $meta_key  Meta key.
 * @param int    $object_id Object ID.
 * @return bool
 */
function axismundi_geodata_post_meta_auth( $allowed, $meta_key, $object_id ) : bool {
	return current_user_can( 'edit_post', (int) $object_id );
}

/**
 * Register coordinate meta on every object type that can carry it, plus the
 * place-fact meta on both geo taxonomies.
 *
 * @return void
 */
function axismundi_geodata_register_meta() : void {
	$number  = array( 'type' => 'number' );
	$string  = array( 'type' => 'string' );
	$boolean = array( 'type' => 'boolean' );

	// Object coordinate meta: observation / capture point.
	$post_meta = array(
		'geo_latitude'            => array( 'type' => 'number', 'sanitize' => 'axismundi_geodata_sanitize_latitude', 'schema' => $number ),
		'geo_longitude'           => array( 'type' => 'number', 'sanitize' => 'axismundi_geodata_sanitize_longitude', 'schema' => $number ),
		'geo_public'              => array( 'type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'schema' => $boolean, 'default' => false ),
		'geo_altitude'            => array( 'type' => 'number', 'sanitize' => 'axismundi_geodata_sanitize_float', 'schema' => $number ),
		'geo_accuracy'            => array( 'type' => 'number', 'sanitize' => 'axismundi_geodata_sanitize_nonneg', 'schema' => $number ),
		'geo_address'             => array( 'type' => 'string', 'sanitize' => 'sanitize_text_field', 'schema' => $string ),
		'ax_geo_source'           => array( 'type' => 'string', 'sanitize' => 'sanitize_key', 'schema' => $string ),
		'ax_geo_public_precision' => array(
			'type'     => 'string',
			'sanitize' => 'axismundi_geodata_sanitize_precision',
			'default'  => axismundi_geodata_default_precision(),
			'schema'   => array( 'type' => 'string', 'enum' => array_keys( axismundi_geodata_precision_levels() ) ),
		),
	);

	foreach ( axismundi_geodata_coordinate_object_types() as $object_type ) {
		foreach ( $post_meta as $key => $def ) {
			$args = array(
				'type'              => $def['type'],
				'single'            => true,
				'sanitize_callback' => $def['sanitize'],
				'auth_callback'     => 'axismundi_geodata_post_meta_auth',
				'show_in_rest'      => array( 'schema' => $def['schema'] ),
			);

			// Only number/boolean defaults that match the type — a '' default on a
			// number-typed meta silently aborts registration.
			if ( array_key_exists( 'default', $def ) ) {
				$args['default'] = $def['default'];
			}

			register_post_meta( $object_type, $key, $args );
		}
	}

	// Term place-fact meta: the named place's own coordinates and identity.
	$term_meta = array(
		'geo_latitude'      => array( 'type' => 'number', 'sanitize' => 'axismundi_geodata_sanitize_latitude', 'schema' => $number ),
		'geo_longitude'     => array( 'type' => 'number', 'sanitize' => 'axismundi_geodata_sanitize_longitude', 'schema' => $number ),
		'geo_address'       => array( 'type' => 'string', 'sanitize' => 'sanitize_text_field', 'schema' => $string ),
		'ax_geo_radius'     => array( 'type' => 'number', 'sanitize' => 'axismundi_geodata_sanitize_nonneg', 'schema' => $number ),
		'ax_geo_bounds'     => array( 'type' => 'string', 'sanitize' => 'sanitize_text_field', 'schema' => $string ),
		'ax_geo_place_id'   => array( 'type' => 'string', 'sanitize' => 'sanitize_text_field', 'schema' => $string ),
		'ax_geo_place_type' => array( 'type' => 'string', 'sanitize' => 'sanitize_key', 'schema' => $string ),
		'ax_geo_plus_code'  => array( 'type' => 'string', 'sanitize' => 'sanitize_text_field', 'schema' => $string ),
		'ax_geo_source'     => array( 'type' => 'string', 'sanitize' => 'sanitize_key', 'schema' => $string ),
	);

	foreach ( array( 'geo_area', 'geotag' ) as $taxonomy ) {
		foreach ( $term_meta as $key => $def ) {
			register_term_meta(
				$taxonomy,
				$key,
				array(
					'type'              => $def['type'],
					'single'            => true,
					'sanitize_callback' => $def['sanitize'],
					'auth_callback'     => function () {
						return current_user_can( 'manage_categories' );
					},
					'show_in_rest'      => array( 'schema' => $def['schema'] ),
				)
			);
		}
	}

	// geo_area administrative-division facts (geo_area only). ax_geo_place_type is
	// the named administrative type (country, province, city, district…); these
	// carry the country plus external codes used to map onto Google address
	// components, Schema.org, and national address APIs. admin_level and a separate
	// admin_role are NOT stored — level is derived from the hierarchy, and the role
	// would just restate the administrative type.
	$geo_area_meta = array(
		'ax_geo_country_code'  => 'axismundi_geodata_sanitize_country_code',
		'ax_geo_national_code' => 'sanitize_text_field',
		'ax_geo_iso_3166_2'    => 'sanitize_text_field',
		'ax_geo_code_scheme'   => 'sanitize_text_field',
	);
	foreach ( $geo_area_meta as $key => $sanitize ) {
		register_term_meta(
			'geo_area',
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'sanitize_callback' => $sanitize,
				'auth_callback'     => function () {
					return current_user_can( 'manage_categories' );
				},
				'show_in_rest'      => array( 'schema' => $string ),
			)
		);
	}

	// geotag -> geo_area relationship: a single leaf geo_area term id. The full
	// containment chain (수영구 > 부산광역시 > ...) is derived from the geo_area
	// hierarchy, never stored, so re-parenting an area can't leave stale chains.
	register_term_meta(
		'geotag',
		'ax_geo_area',
		array(
			'type'              => 'integer',
			'single'            => true,
			'sanitize_callback' => 'absint',
			'auth_callback'     => function () {
				return current_user_can( 'manage_categories' );
			},
			'show_in_rest'      => array( 'schema' => array( 'type' => 'integer' ) ),
		)
	);
}
add_action( 'init', 'axismundi_geodata_register_meta' );
