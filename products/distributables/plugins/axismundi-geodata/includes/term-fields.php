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
 * geo_area carries administrative-division facts (admin role + country / external
 * codes); geotag carries facility place facts. The shared coordinate / place-type
 * fields differ only in the Place type label and help.
 *
 * @param string $taxonomy Taxonomy being edited.
 * @return array<string,array{label:string,type:string,help:string}>
 */
function axismundi_geodata_term_fields( string $taxonomy = '' ) : array {
	$is_area = 'geo_area' === $taxonomy;

	$fields = array(
		'ax_geo_plus_code'  => array(
			'label' => __( 'Plus Code', 'axismundi-geodata' ),
			'type'  => 'text',
			'help'  => __( 'A full Open Location Code (e.g. 8Q7XMQVC+9G). Fills latitude / longitude on save when those are empty. Short codes with a place name need geocoding (coming later).', 'axismundi-geodata' ),
		),
		'geo_latitude'      => array(
			'label' => __( 'Latitude', 'axismundi-geodata' ),
			'type'  => 'number',
			'help'  => __( 'Centre latitude, -90 to 90.', 'axismundi-geodata' ),
		),
		'geo_longitude'     => array(
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
			'label' => $is_area ? __( 'Administrative type', 'axismundi-geodata' ) : __( 'Place type', 'axismundi-geodata' ),
			'type'  => 'select',
			'help'  => $is_area
				? __( 'Which kind of administrative division this is (country, province, city, district, …).', 'axismundi-geodata' )
				: __( 'What kind of place this is, grouped by category.', 'axismundi-geodata' ),
		),
	);

	if ( $is_area ) {
		$fields['ax_geo_country_code']  = array(
			'label' => __( 'Country code', 'axismundi-geodata' ),
			'type'  => 'text',
			'help'  => __( 'ISO 3166-1 alpha-2, e.g. KR.', 'axismundi-geodata' ),
		);
		$fields['ax_geo_national_code'] = array(
			'label' => __( 'National code', 'axismundi-geodata' ),
			'type'  => 'text',
			'help'  => __( 'National administrative code, e.g. 26 / 26500 / 2650010400. Pair it with Code scheme.', 'axismundi-geodata' ),
		);
		$fields['ax_geo_iso_3166_2']    = array(
			'label' => __( 'ISO 3166-2', 'axismundi-geodata' ),
			'type'  => 'text',
			'help'  => __( 'e.g. KR-26 — first-level divisions only; leave blank otherwise.', 'axismundi-geodata' ),
		);
		$fields['ax_geo_code_scheme']   = array(
			'label' => __( 'Code scheme', 'axismundi-geodata' ),
			'type'  => 'text',
			'help'  => __( 'What National code means — e.g. KR_LEGAL_DONG, KR_ADMIN_DONG, ISO_3166-2, MOIS.', 'axismundi-geodata' ),
		);
	}

	// Provider caches — rendered read-only (disabled). A place lookup / geocoding
	// adapter fills these, so they are shown for transparency but never hand-typed.
	// Disabled inputs aren't posted, so the save loop skips them and values survive.
	$fields['ax_geo_place_id'] = array(
		'label'    => __( 'Place ID', 'axismundi-geodata' ),
		'type'     => 'text',
		'help'     => __( 'Canonical provider id — e.g. google:ChIJ…, osm:node/123456, wikidata:Q12345, geonames:1838524. Set automatically by a place lookup.', 'axismundi-geodata' ),
		'disabled' => true,
	);
	$fields['geo_address']     = array(
		'label'    => __( 'Address', 'axismundi-geodata' ),
		'type'     => 'text',
		'help'     => __( 'Formatted address cache from the geocoding provider; the Geo Area hierarchy is the canonical structure.', 'axismundi-geodata' ),
		'disabled' => true,
	);

	return $fields;
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
 * Build the form control (input or grouped select) for one term field.
 *
 * @param string $key      Meta key / field name.
 * @param array  $field    Field config from axismundi_geodata_term_fields().
 * @param string $value    Current value.
 * @param string $taxonomy Taxonomy being edited.
 * @return string Escaped control markup.
 */
function axismundi_geodata_term_control( string $key, array $field, string $value, string $taxonomy ) : string {
	if ( 'select' === $field['type'] ) {
		return axismundi_geodata_place_type_select( $taxonomy, $key, $value );
	}

	$attrs = ( 'number' === $field['type'] ? ' step="any"' : '' ) . ( empty( $field['disabled'] ) ? '' : ' disabled' );

	return sprintf(
		'<input type="%2$s"%3$s name="%1$s" id="%1$s" value="%4$s" />',
		esc_attr( $key ),
		esc_attr( $field['type'] ),
		$attrs, // $attrs is built from static literals above.
		esc_attr( $value )
	);
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
 * Render Google lookup controls for an existing geo term.
 *
 * The API key stays server-side; this UI only calls admin-ajax and receives
 * already-normalised candidates.
 *
 * @param WP_Term $term Term being edited.
 * @return void
 */
function axismundi_geodata_render_google_lookup( WP_Term $term ) : void {
	if ( ! in_array( $term->taxonomy, array( 'geo_area', 'geotag' ), true ) ) {
		return;
	}

	$has_key = '' !== axismundi_geodata_google_api_key();
	$label   = esc_html__( 'Google lookup', 'axismundi-geodata' );
	$button  = sprintf(
		'<button type="button" class="button" id="axgeo-google-lookup" %1$s>%2$s</button>',
		disabled( $has_key, false, false ),
		esc_html__( 'Lookup with Google', 'axismundi-geodata' )
	);
	$status  = $has_key
		? esc_html__( 'Search Google Places for this term, then bind the selected candidate.', 'axismundi-geodata' )
		: esc_html__( 'Add a Google server API key in Settings > Geodata to enable lookup.', 'axismundi-geodata' );
	$html    = $button . '<p class="description" id="axgeo-google-status">' . $status . '</p><div id="axgeo-google-results" class="axgeo-google-results" aria-live="polite"></div>';

	echo '<tr class="form-field"><th scope="row">' . $label . '</th><td>' . $html . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all dynamic pieces escaped above.
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
	foreach ( axismundi_geodata_term_fields( $taxonomy ) as $key => $field ) {
		printf(
			'<div class="form-field"><label for="%1$s">%2$s</label>%3$s<p>%4$s</p></div>',
			esc_attr( $key ),
			esc_html( $field['label'] ),
			axismundi_geodata_term_control( $key, $field, '', $taxonomy ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in builder.
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
	foreach ( axismundi_geodata_term_fields( $taxonomy ) as $key => $field ) {
		$value = 'ax_geo_place_id' === $key
			? axismundi_geodata_canonical_place_id( $term->term_id )
			: (string) get_term_meta( $term->term_id, $key, true );
		printf(
			'<tr class="form-field"><th scope="row"><label for="%1$s">%2$s</label></th><td>%3$s<p class="description">%4$s</p></td></tr>',
			esc_attr( $key ),
			esc_html( $field['label'] ),
			axismundi_geodata_term_control( $key, $field, $value, $taxonomy ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in builder.
			esc_html( $field['help'] )
		);
	}
	axismundi_geodata_render_google_lookup( $term );
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

	$term     = get_term( $term_id );
	$taxonomy = $term instanceof WP_Term ? $term->taxonomy : '';

	$values = array();
	foreach ( axismundi_geodata_term_fields( $taxonomy ) as $key => $field ) {
		if ( ! empty( $field['disabled'] ) ) {
			continue; // provider cache: read-only, not posted, left untouched.
		}
		$values[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	}

	// Plus Code convenience: a full code fills empty coordinates. This only sets the
	// coordinate, not the place identity (see place-id.php), which hand-entered
	// coordinates don't establish.
	if ( '' !== $values['ax_geo_plus_code'] && ( '' === $values['geo_latitude'] || '' === $values['geo_longitude'] ) ) {
		$decoded = axismundi_geodata_decode_plus_code( $values['ax_geo_plus_code'] );
		if ( null !== $decoded ) {
			$values['geo_latitude']  = (string) $decoded['latitude'];
			$values['geo_longitude'] = (string) $decoded['longitude'];
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
	if ( $tiles['enabled'] ) {
		wp_enqueue_script(
			'axismundi-geodata-term-map',
			plugins_url( 'assets/term-map.js', AXISMUNDI_GEODATA_FILE ),
			array( 'axismundi-geodata-map-field' ),
			AXISMUNDI_GEODATA_VERSION,
			true
		);
		wp_add_inline_script( 'axismundi-geodata-term-map', axismundi_geodata_map_inline_js( $tiles ), 'before' );
	}

	if ( 'term.php' !== $hook ) {
		return;
	}

	// Read-only screen context for enqueue/localization; no state is changed here.
	$term_id = isset( $_GET['tag_ID'] ) ? absint( wp_unslash( $_GET['tag_ID'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $term_id ) {
		return;
	}

	wp_enqueue_script(
		'axismundi-geodata-google-lookup',
		plugins_url( 'assets/google-lookup.js', AXISMUNDI_GEODATA_FILE ),
		array(),
		AXISMUNDI_GEODATA_VERSION,
		true
	);
	wp_add_inline_script(
		'axismundi-geodata-google-lookup',
		'window.axismundiGeodataGoogleLookup = ' . wp_json_encode(
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'axismundi_geodata_google_lookup' ),
				'termId'   => $term_id,
				'hasKey'   => '' !== axismundi_geodata_google_api_key(),
				'i18n'     => array(
					'searching' => __( 'Searching Google Places…', 'axismundi-geodata' ),
					'noResults' => __( 'No candidates found.', 'axismundi-geodata' ),
					'binding'   => __( 'Binding selected place…', 'axismundi-geodata' ),
					'bound'     => __( 'Google place bound.', 'axismundi-geodata' ),
					'bind'      => __( 'Bind', 'axismundi-geodata' ),
					'error'     => __( 'Google lookup failed.', 'axismundi-geodata' ),
				),
			)
		) . ';',
		'before'
	);
	wp_add_inline_style(
		'common',
		'.axgeo-google-results{margin-top:8px;display:grid;gap:8px;max-width:640px}.axgeo-google-candidate{border:1px solid #c3c4c7;border-radius:4px;padding:10px;background:#fff}.axgeo-google-candidate strong{display:block}.axgeo-google-candidate small{display:block;color:#646970;margin-top:2px}.axgeo-google-candidate .button{margin-top:8px}'
	);
}
add_action( 'admin_enqueue_scripts', 'axismundi_geodata_term_enqueue' );
