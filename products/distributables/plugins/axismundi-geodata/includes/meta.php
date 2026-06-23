<?php
/**
 * Geo metadata registry.
 *
 * Object (post/page/attachment) coordinate meta = the observation / capture
 * point. Term (geo_area/geotag) meta = the named place's facts (centroid,
 * radius, bounds, external id). All single-value, REST-exposed with a schema,
 * sanitised, and edit-gated.
 *
 * WordPress-convention keys (geo_latitude, geo_longitude, geo_public) are kept
 * for interop; Axismundi extensions use the ax_geo_* namespace. The RAW exact
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
		'ax_geo_altitude'         => array( 'type' => 'number', 'sanitize' => 'floatval', 'schema' => $number ),
		'ax_geo_accuracy_meters'  => array( 'type' => 'number', 'sanitize' => 'axismundi_geodata_sanitize_nonneg', 'schema' => $number ),
		'ax_geo_address'          => array( 'type' => 'string', 'sanitize' => 'sanitize_text_field', 'schema' => $string ),
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
		'ax_geo_latitude'   => array( 'type' => 'number', 'sanitize' => 'axismundi_geodata_sanitize_latitude', 'schema' => $number ),
		'ax_geo_longitude'  => array( 'type' => 'number', 'sanitize' => 'axismundi_geodata_sanitize_longitude', 'schema' => $number ),
		'ax_geo_radius'     => array( 'type' => 'number', 'sanitize' => 'axismundi_geodata_sanitize_nonneg', 'schema' => $number ),
		'ax_geo_bounds'     => array( 'type' => 'string', 'sanitize' => 'sanitize_text_field', 'schema' => $string ),
		'ax_geo_place_id'   => array( 'type' => 'string', 'sanitize' => 'sanitize_text_field', 'schema' => $string ),
		'ax_geo_place_type' => array( 'type' => 'string', 'sanitize' => 'sanitize_key', 'schema' => $string ),
		'ax_geo_source'     => array( 'type' => 'string', 'sanitize' => 'sanitize_key', 'schema' => $string ),
		'ax_geo_address'    => array( 'type' => 'string', 'sanitize' => 'sanitize_text_field', 'schema' => $string ),
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
}
add_action( 'init', 'axismundi_geodata_register_meta' );
