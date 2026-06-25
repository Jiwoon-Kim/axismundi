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
		'geo_latitude'   => array(
			'label' => __( 'Latitude', 'axismundi-geodata' ),
			'type'  => 'number',
			'help'  => __( 'Centre latitude, -90 to 90.', 'axismundi-geodata' ),
		),
		'geo_longitude'  => array(
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
		// Provider caches — rendered read-only (disabled). The geo_area hierarchy is
		// the canonical address and a geocoding adapter fills these, so they are
		// shown for transparency but never hand-typed. Disabled inputs aren't
		// posted, so the save loop also skips them and existing values survive.
		'ax_geo_place_id'   => array(
			'label'    => __( 'Place ID', 'axismundi-geodata' ),
			'type'     => 'text',
			'help'     => __( 'External provider id, set automatically — e.g. Google ChIJ…, OSM node/123456, Wikidata Q12345, GeoNames 1838524.', 'axismundi-geodata' ),
			'disabled' => true,
		),
		'geo_address'    => array(
			'label'    => __( 'Address', 'axismundi-geodata' ),
			'type'     => 'text',
			'help'     => __( 'Formatted address cache from the geocoding provider; the Geo Area hierarchy is the canonical structure.', 'axismundi-geodata' ),
			'disabled' => true,
		),
	);
}

/**
 * Render the geotag -> geo_area select (geotag screens only). A geotag points at
 * a single leaf area; its ancestors come from the geo_area hierarchy.
 *
 * @param string $taxonomy Taxonomy being edited.
 * @param int    $selected Currently selected geo_area term id.
 * @param string $context  'add' (div layout) or 'edit' (table row).
 * @return void
 */
function axismundi_geodata_render_area_select( string $taxonomy, int $selected, string $context ) : void {
	if ( 'geotag' !== $taxonomy ) {
		return;
	}

	$dropdown = wp_dropdown_categories(
		array(
			'taxonomy'          => 'geo_area',
			'name'              => 'ax_geo_area',
			'id'                => 'ax_geo_area',
			'hierarchical'      => true,
			'hide_empty'        => false,
			'show_option_none'  => __( '— None —', 'axismundi-geodata' ),
			'option_none_value' => '0',
			'selected'          => $selected,
			'orderby'           => 'name',
			'echo'              => false,
		)
	);

	$label = esc_html__( 'Geo Area', 'axismundi-geodata' );
	$help  = esc_html__( 'The administrative area this place sits in; its parents are derived from the area hierarchy.', 'axismundi-geodata' );

	// wp_dropdown_categories() returns escaped markup.
	if ( 'edit' === $context ) {
		echo '<tr class="form-field"><th scope="row"><label for="ax_geo_area">' . $label . '</label></th><td>' . $dropdown . '<p class="description">' . $help . '</p></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		echo '<div class="form-field"><label for="ax_geo_area">' . $label . '</label>' . $dropdown . '<p>' . $help . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Render the Leaflet map field, bound to the latitude / longitude inputs by
 * term-map.js. Only output when an admin tile provider is configured.
 *
 * @param string $context 'add' (div layout) or 'edit' (table row).
 * @return void
 */
function axismundi_geodata_render_term_map( string $context ) : void {
	if ( ! axismundi_geodata_resolve_tiles( 'admin' )['enabled'] ) {
		return;
	}

	$label = esc_html__( 'Map', 'axismundi-geodata' );
	$help  = esc_html__( 'Click the map or drag the marker to set the centre coordinates.', 'axismundi-geodata' );
	$map   = '<div id="axgeo-term-map" style="height:260px;max-width:520px;border-radius:4px;overflow:hidden;"></div>';

	// $map is a static literal; $label / $help are escaped above.
	if ( 'edit' === $context ) {
		echo '<tr class="form-field"><th scope="row">' . $label . '</th><td>' . $map . '<p class="description">' . $help . '</p></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		echo '<div class="form-field"><label>' . $label . '</label>' . $map . '<p>' . $help . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

/**
 * Render the fields on the "Add New" term screen (stacked div layout).
 *
 * @param string $taxonomy Taxonomy being added to.
 * @return void
 */
function axismundi_geodata_term_add_fields( string $taxonomy = '' ) : void {
	wp_nonce_field( 'axismundi_geodata_term', 'axismundi_geodata_term_nonce' );
	axismundi_geodata_render_area_select( $taxonomy, 0, 'add' );
	foreach ( axismundi_geodata_term_fields() as $key => $field ) {
		$attrs = ( 'number' === $field['type'] ? ' step="any"' : '' ) . ( empty( $field['disabled'] ) ? '' : ' disabled' );
		printf(
			'<div class="form-field"><label for="%1$s">%2$s</label><input type="%3$s"%4$s name="%1$s" id="%1$s" value="" /><p>%5$s</p></div>',
			esc_attr( $key ),
			esc_html( $field['label'] ),
			esc_attr( $field['type'] ),
			$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static literal.
			esc_html( $field['help'] )
		);
	}
	axismundi_geodata_render_term_map( 'add' );
}

/**
 * Render the fields on the "Edit" term screen (table-row layout).
 *
 * @param WP_Term $term     The term being edited.
 * @param string  $taxonomy Taxonomy being edited.
 * @return void
 */
function axismundi_geodata_term_edit_fields( WP_Term $term, string $taxonomy = '' ) : void {
	wp_nonce_field( 'axismundi_geodata_term', 'axismundi_geodata_term_nonce' );
	axismundi_geodata_render_area_select( $taxonomy, axismundi_geodata_get_geotag_area_id( $term->term_id ), 'edit' );
	foreach ( axismundi_geodata_term_fields() as $key => $field ) {
		$value = get_term_meta( $term->term_id, $key, true );
		$attrs = ( 'number' === $field['type'] ? ' step="any"' : '' ) . ( empty( $field['disabled'] ) ? '' : ' disabled' );
		printf(
			'<tr class="form-field"><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="%3$s"%4$s name="%1$s" id="%1$s" value="%5$s" /><p class="description">%6$s</p></td></tr>',
			esc_attr( $key ),
			esc_html( $field['label'] ),
			esc_attr( $field['type'] ),
			$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static literal.
			esc_attr( $value ),
			esc_html( $field['help'] )
		);
	}
	axismundi_geodata_render_term_map( 'edit' );
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
	foreach ( axismundi_geodata_term_fields() as $key => $field ) {
		if ( ! empty( $field['disabled'] ) ) {
			continue; // provider cache: read-only, not posted, left untouched.
		}
		$values[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	// Plus Code convenience: a full code fills empty coordinates.
	if ( '' !== $values['ax_geo_plus_code'] && ( '' === $values['geo_latitude'] || '' === $values['geo_longitude'] ) ) {
		$decoded = axismundi_geodata_decode_plus_code( $values['ax_geo_plus_code'] );
		if ( null !== $decoded ) {
			$values['geo_latitude']  = (string) $decoded['latitude'];
			$values['geo_longitude'] = (string) $decoded['longitude'];
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

	// geotag -> geo_area pointer; only the geotag screen posts this field. Store
	// only a real geo_area term id, else clear it.
	if ( isset( $_POST['ax_geo_area'] ) ) {
		$area      = absint( wp_unslash( $_POST['ax_geo_area'] ) );
		$area_term = $area ? get_term( $area ) : null;
		if ( $area_term instanceof WP_Term && 'geo_area' === $area_term->taxonomy ) {
			update_term_meta( $term_id, 'ax_geo_area', $area );
		} else {
			delete_term_meta( $term_id, 'ax_geo_area' );
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
		add_action( "{$taxonomy}_edit_form_fields", 'axismundi_geodata_term_edit_fields', 10, 2 );
		add_action( "created_{$taxonomy}", 'axismundi_geodata_term_save' );
		add_action( "edited_{$taxonomy}", 'axismundi_geodata_term_save' );
	}
}
add_action( 'admin_init', 'axismundi_geodata_term_fields_init' );

/**
 * Enqueue the Leaflet map field on the geo taxonomy term screens.
 *
 * @param string $hook Current admin page.
 * @return void
 */
function axismundi_geodata_term_enqueue( string $hook ) : void {
	if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->taxonomy, array( 'geo_area', 'geotag' ), true ) ) {
		return;
	}

	$tiles = axismundi_geodata_enqueue_map_field();
	if ( ! $tiles['enabled'] ) {
		return;
	}

	wp_enqueue_script(
		'axismundi-geodata-term-map',
		plugins_url( 'assets/term-map.js', AXISMUNDI_GEODATA_FILE ),
		array( 'axismundi-geodata-map-field' ),
		AXISMUNDI_GEODATA_VERSION,
		true
	);
	wp_add_inline_script( 'axismundi-geodata-term-map', axismundi_geodata_map_inline_js( $tiles ), 'before' );
}
add_action( 'admin_enqueue_scripts', 'axismundi_geodata_term_enqueue' );
