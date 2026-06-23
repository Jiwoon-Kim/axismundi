<?php
/**
 * Term editor fields for the place-fact meta.
 *
 * Adds coordinate / place inputs to the Add New and Edit screens of both geo
 * taxonomies so a term (a named Place) can carry its centroid, radius, type, and
 * identity. A Plus Code field is a convenience: a full Open Location Code entered
 * there is decoded to fill latitude/longitude on save when those are left blank.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * The term meta keys surfaced as editor fields, with labels and help text.
 *
 * @return array<string,array{label:string,type:string,help:string}>
 */
function axismundi_geodata_term_fields() : array {
	return array(
		'ax_geo_plus_code'  => array(
			'label' => __( 'Plus Code', 'axismundi-geodata' ),
			'type'  => 'text',
			'help'  => __( 'A full Open Location Code (e.g. 8Q7XMQVC+9G). Fills latitude / longitude on save when those are empty. Short codes with a place name need geocoding (coming later).', 'axismundi-geodata' ),
		),
		'ax_geo_latitude'   => array(
			'label' => __( 'Latitude', 'axismundi-geodata' ),
			'type'  => 'number',
			'help'  => __( 'Centre latitude, -90 to 90.', 'axismundi-geodata' ),
		),
		'ax_geo_longitude'  => array(
			'label' => __( 'Longitude', 'axismundi-geodata' ),
			'type'  => 'number',
			'help'  => __( 'Centre longitude, -180 to 180.', 'axismundi-geodata' ),
		),
		'ax_geo_radius'     => array(
			'label' => __( 'Radius (m)', 'axismundi-geodata' ),
			'type'  => 'number',
			'help'  => __( 'Approximate area radius in metres.', 'axismundi-geodata' ),
		),
		'ax_geo_place_type' => array(
			'label' => __( 'Place type', 'axismundi-geodata' ),
			'type'  => 'text',
			'help'  => __( 'e.g. beach, station, venue, district, city.', 'axismundi-geodata' ),
		),
		'ax_geo_place_id'   => array(
			'label' => __( 'Place ID', 'axismundi-geodata' ),
			'type'  => 'text',
			'help'  => __( 'External place identifier (provider-specific).', 'axismundi-geodata' ),
		),
		'ax_geo_address'    => array(
			'label' => __( 'Address', 'axismundi-geodata' ),
			'type'  => 'text',
			'help'  => __( 'Human-readable address (display / cache).', 'axismundi-geodata' ),
		),
	);
}

/**
 * Render the fields on the "Add New" term screen (stacked div layout).
 *
 * @return void
 */
function axismundi_geodata_term_add_fields() : void {
	wp_nonce_field( 'axismundi_geodata_term', 'axismundi_geodata_term_nonce' );
	foreach ( axismundi_geodata_term_fields() as $key => $field ) {
		$step = 'number' === $field['type'] ? ' step="any"' : '';
		printf(
			'<div class="form-field"><label for="%1$s">%2$s</label><input type="%3$s"%4$s name="%1$s" id="%1$s" value="" /><p>%5$s</p></div>',
			esc_attr( $key ),
			esc_html( $field['label'] ),
			esc_attr( $field['type'] ),
			$step, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static literal.
			esc_html( $field['help'] )
		);
	}
}

/**
 * Render the fields on the "Edit" term screen (table-row layout).
 *
 * @param WP_Term $term The term being edited.
 * @return void
 */
function axismundi_geodata_term_edit_fields( WP_Term $term ) : void {
	wp_nonce_field( 'axismundi_geodata_term', 'axismundi_geodata_term_nonce' );
	foreach ( axismundi_geodata_term_fields() as $key => $field ) {
		$value = get_term_meta( $term->term_id, $key, true );
		$step  = 'number' === $field['type'] ? ' step="any"' : '';
		printf(
			'<tr class="form-field"><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="%3$s"%4$s name="%1$s" id="%1$s" value="%5$s" /><p class="description">%6$s</p></td></tr>',
			esc_attr( $key ),
			esc_html( $field['label'] ),
			esc_attr( $field['type'] ),
			$step, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static literal.
			esc_attr( $value ),
			esc_html( $field['help'] )
		);
	}
}

/**
 * Persist the term fields, decoding a Plus Code into coordinates when needed.
 *
 * @param int $term_id The term being saved.
 * @return void
 */
function axismundi_geodata_term_save( int $term_id ) : void {
	if (
		! isset( $_POST['axismundi_geodata_term_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['axismundi_geodata_term_nonce'] ) ), 'axismundi_geodata_term' )
		|| ! current_user_can( 'manage_categories' )
	) {
		return;
	}

	$values = array();
	foreach ( array_keys( axismundi_geodata_term_fields() ) as $key ) {
		$values[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	// Plus Code convenience: a full code fills empty coordinates.
	if ( '' !== $values['ax_geo_plus_code'] && ( '' === $values['ax_geo_latitude'] || '' === $values['ax_geo_longitude'] ) ) {
		$decoded = axismundi_geodata_decode_plus_code( $values['ax_geo_plus_code'] );
		if ( null !== $decoded ) {
			$values['ax_geo_latitude']  = (string) $decoded['latitude'];
			$values['ax_geo_longitude'] = (string) $decoded['longitude'];
			if ( '' === get_term_meta( $term_id, 'ax_geo_source', true ) ) {
				update_term_meta( $term_id, 'ax_geo_source', 'pluscode' );
			}
		}
	}

	foreach ( $values as $key => $value ) {
		if ( '' === $value ) {
			delete_term_meta( $term_id, $key );
		} else {
			// register_term_meta sanitises per its callback on update.
			update_term_meta( $term_id, $key, $value );
		}
	}
}

/**
 * Wire the add / edit / save hooks for both geo taxonomies.
 *
 * @return void
 */
function axismundi_geodata_term_fields_init() : void {
	foreach ( array( 'geo_area', 'geotag' ) as $taxonomy ) {
		add_action( "{$taxonomy}_add_form_fields", 'axismundi_geodata_term_add_fields' );
		add_action( "{$taxonomy}_edit_form_fields", 'axismundi_geodata_term_edit_fields' );
		add_action( "created_{$taxonomy}", 'axismundi_geodata_term_save' );
		add_action( "edited_{$taxonomy}", 'axismundi_geodata_term_save' );
	}
}
add_action( 'admin_init', 'axismundi_geodata_term_fields_init' );
