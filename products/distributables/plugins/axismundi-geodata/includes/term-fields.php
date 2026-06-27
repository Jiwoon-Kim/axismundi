<?php
/**
 * Term editor fields for the place-fact meta.
 *
 * Adds coordinate / place inputs to the Add New and Edit screens of both geo
 * taxonomies so a term (a named Place) can carry its centroid, radius, type, and
 * identity.
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
		'ax_geo_place_type' => array(
			'label' => $is_area ? __( 'Administrative type', 'axismundi-geodata' ) : __( 'Place type', 'axismundi-geodata' ),
			'type'  => 'select',
			'help'  => $is_area
				? __( 'Which kind of administrative division this is (country, province, city, district, …).', 'axismundi-geodata' )
				: __( 'What kind of place this is, grouped by category.', 'axismundi-geodata' ),
		),
	);

	if ( $is_area ) {
		$fields['ax_geo_country_code'] = array(
			'label' => __( 'Country code', 'axismundi-geodata' ),
			'type'  => 'text',
			'help'  => __( 'ISO 3166-1 alpha-2, e.g. KR (schema.org addressCountry). Set it on the Country term; descendants inherit it through the hierarchy.', 'axismundi-geodata' ),
		);
		$fields['ax_geo_iso_3166_2']   = array(
			'label' => __( 'ISO 3166-2', 'axismundi-geodata' ),
			'type'  => 'text',
			'help'  => __( 'The ISO 3166-2 subdivision code where one exists, e.g. KR-26, US-CA, US-DC (schema.org addressRegion). Not limited to first-order divisions.', 'axismundi-geodata' ),
		);
	}

	// Place identity + address. A lookup fills these (Bind saves them immediately),
	// but they are also editable — paste a namespaced id or correct the address by
	// hand and Update. The Place ID is validated to a known source on save.
	$fields['ax_geo_place_id'] = array(
		'label' => __( 'Place ID', 'axismundi-geodata' ),
		'type'  => 'text',
		'help'  => __( 'A namespaced place id — e.g. google:ChIJ…, osm:way/123456, wikidata:Q12345, geonames:1838524, or manual:my-place. Filled by a lookup, or enter one yourself.', 'axismundi-geodata' ),
	);
	$fields['geo_address']     = array(
		'label' => __( 'Address', 'axismundi-geodata' ),
		'type'  => 'text',
		'help'  => __( 'Formatted address — filled by a lookup or entered by hand. The Geo Area hierarchy is the canonical structure.', 'axismundi-geodata' ),
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
 * Render place-lookup controls for an existing geo term — a button per enabled
 * provider (Google, OSM, …). Keys / endpoints stay server-side; this UI only
 * calls admin-ajax and receives already-normalised candidates.
 *
 * @param WP_Term $term Term being edited.
 * @return void
 */
function axismundi_geodata_render_place_lookup( ?WP_Term $term, string $taxonomy, string $context ) : void {
	if ( ! in_array( $taxonomy, array( 'geo_area', 'geotag' ), true ) ) {
		return;
	}

	$label     = esc_html__( 'Place lookup', 'axismundi-geodata' );
	$providers = axismundi_geodata_lookup_enabled_providers();

	if ( empty( $providers ) ) {
		$message = esc_html__( 'Configure a lookup provider (Google or OpenStreetMap) in Settings > Geodata to find this place.', 'axismundi-geodata' );
		if ( 'edit' === $context ) {
			echo '<tr class="form-field"><th scope="row">' . $label . '</th><td><p class="description">' . $message . '</p></td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		} else {
			echo '<div class="form-field"><label>' . $label . '</label><p>' . $message . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		}
		return;
	}

	$buttons = '';
	$query  = $term instanceof WP_Term ? axismundi_geodata_lookup_term_query( $term ) : '';
	$modes  = '<fieldset class="axgeo-lookup-modes"><legend class="screen-reader-text">' . esc_html__( 'Lookup method', 'axismundi-geodata' ) . '</legend>'
		. '<label><input type="radio" name="axgeo_lookup_mode" value="text" checked /> ' . esc_html__( 'By keyword', 'axismundi-geodata' ) . '</label> '
		. '<label><input type="radio" name="axgeo_lookup_mode" value="map" /> ' . esc_html__( 'From map point', 'axismundi-geodata' ) . '</label></fieldset>';
	$search = sprintf(
		'<input type="search" id="axgeo-lookup-query" class="regular-text" value="%1$s" placeholder="%2$s" /> ',
		esc_attr( $query ),
		esc_attr__( 'Keyword, place name, or address', 'axismundi-geodata' )
	);
	foreach ( $providers as $slug => $provider ) {
		$buttons .= sprintf(
			'<button type="button" class="button axgeo-lookup-btn" data-provider="%s">%s</button> ',
			esc_attr( $slug ),
			/* translators: %s: provider name (Google, OpenStreetMap). */
			esc_html( sprintf( __( 'Lookup with %s', 'axismundi-geodata' ), $provider['label'] ) )
		);
	}
	if ( $term instanceof WP_Term ) {
		$buttons .= sprintf(
			'<button type="button" class="button-link axgeo-unbind-btn" id="axgeo-unbind-btn">%s</button>',
			esc_html__( 'Unbind', 'axismundi-geodata' )
		);
	}

	$status = $term instanceof WP_Term
		? esc_html__( 'Search a provider, then choose a candidate to fill the fields below. Click Update to save.', 'axismundi-geodata' )
		: esc_html__( 'Enter the new term name or an address above, then search. Choose a candidate to fill the fields; it is saved when you add the term.', 'axismundi-geodata' );
	$html   = $modes . $search . $buttons . '<p class="description" id="axgeo-lookup-status">' . $status . '</p><div id="axgeo-lookup-results" class="axgeo-lookup-results" aria-live="polite"></div>';

	if ( 'edit' === $context ) {
		echo '<tr class="form-field"><th scope="row">' . $label . '</th><td>' . $html . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all dynamic pieces escaped above.
	} else {
		echo '<div class="form-field"><label>' . $label . '</label>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all dynamic pieces escaped above.
	}
}

/**
 * Render selected term-meta controls in a stable editorial order.
 *
 * @param array       $keys     Meta keys to render.
 * @param array       $fields   Field definitions.
 * @param string      $taxonomy Current taxonomy.
 * @param string      $context  add or edit.
 * @param WP_Term|null $term    Existing term on edit screens.
 * @return void
 */
function axismundi_geodata_render_term_controls( array $keys, array $fields, string $taxonomy, string $context, ?WP_Term $term = null ) : void {
	foreach ( $keys as $key ) {
		if ( ! isset( $fields[ $key ] ) ) {
			continue;
		}
		$field = $fields[ $key ];
		$value = $term instanceof WP_Term
			? ( 'ax_geo_place_id' === $key ? axismundi_geodata_canonical_place_id( $term->term_id ) : (string) get_term_meta( $term->term_id, $key, true ) )
			: '';
		$control = axismundi_geodata_term_control( $key, $field, $value, $taxonomy );
		if ( 'edit' === $context ) {
			printf(
				'<tr class="form-field"><th scope="row"><label for="%1$s">%2$s</label></th><td>%3$s<p class="description">%4$s</p></td></tr>',
				esc_attr( $key ), esc_html( $field['label'] ), $control, esc_html( $field['help'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- control escaped in builder.
			);
		} else {
			printf(
				'<div class="form-field"><label for="%1$s">%2$s</label>%3$s<p>%4$s</p></div>',
				esc_attr( $key ), esc_html( $field['label'] ), $control, esc_html( $field['help'] ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- control escaped in builder.
			);
		}
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
	$fields = axismundi_geodata_term_fields( $taxonomy );
	axismundi_geodata_render_area_select( $taxonomy, 0, 'add' );
	axismundi_geodata_render_term_controls( array( 'ax_geo_place_type' ), $fields, $taxonomy, 'add' );
	axismundi_geodata_render_place_lookup( null, $taxonomy, 'add' );
	axismundi_geodata_render_term_map( 'add' );
	axismundi_geodata_render_term_controls( array( 'ax_geo_place_id', 'geo_address', 'geo_latitude', 'geo_longitude' ), $fields, $taxonomy, 'add' );
	axismundi_geodata_render_term_controls( array( 'ax_geo_country_code', 'ax_geo_iso_3166_2' ), $fields, $taxonomy, 'add' );
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
	$fields = axismundi_geodata_term_fields( $taxonomy );
	axismundi_geodata_render_area_select( $taxonomy, axismundi_geodata_get_geotag_area_id( $term->term_id ), 'edit' );
	axismundi_geodata_render_term_controls( array( 'ax_geo_place_type' ), $fields, $taxonomy, 'edit', $term );
	axismundi_geodata_render_place_lookup( $term, $taxonomy, 'edit' );
	axismundi_geodata_render_term_map( 'edit' );
	axismundi_geodata_render_term_controls( array( 'ax_geo_place_id', 'geo_address', 'geo_latitude', 'geo_longitude' ), $fields, $taxonomy, 'edit', $term );
	axismundi_geodata_render_term_controls( array( 'ax_geo_country_code', 'ax_geo_iso_3166_2' ), $fields, $taxonomy, 'edit', $term );
}

/**
 * Persist the term fields.
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

	// A hand-entered Place ID must be a known namespaced identity (source:id); an
	// invalid one is dropped so it can't overwrite an existing binding.
	if ( isset( $values['ax_geo_place_id'] ) && '' !== $values['ax_geo_place_id'] ) {
		$parsed = axismundi_geodata_parse_place_id( $values['ax_geo_place_id'] );
		if ( '' !== $parsed['source'] && '' !== $parsed['id'] && axismundi_geodata_is_place_source( $parsed['source'] ) ) {
			$values['ax_geo_place_id'] = $parsed['source'] . ':' . $parsed['id'];
		} else {
			unset( $values['ax_geo_place_id'] );
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

	// Country code is only meaningful on a Country term (descendants inherit it via
	// the hierarchy), so show that row only while Administrative type = Country.
	if ( 'geo_area' === $screen->taxonomy ) {
		wp_add_inline_script(
			'common',
			'(function(){function s(){var t=document.getElementById("ax_geo_place_type"),c=document.getElementById("ax_geo_country_code");if(!t||!c)return;var r=c.closest(".form-field");if(r){r.style.display=("country"===t.value)?"":"none";}}document.addEventListener("DOMContentLoaded",function(){var t=document.getElementById("ax_geo_place_type");if(t){t.addEventListener("change",s);s();}});})();'
		);
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

	// Read-only screen context for enqueue/localization; no state is changed here.
	$term_id = isset( $_GET['tag_ID'] ) ? absint( wp_unslash( $_GET['tag_ID'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( empty( axismundi_geodata_lookup_enabled_providers() ) ) {
		return;
	}

	wp_enqueue_script(
		'axismundi-geodata-lookup',
		plugins_url( 'assets/lookup.js', AXISMUNDI_GEODATA_FILE ),
		array(),
		(string) filemtime( dirname( AXISMUNDI_GEODATA_FILE ) . '/assets/lookup.js' ),
		true
	);
	wp_add_inline_script(
		'axismundi-geodata-lookup',
		'window.axismundiGeodataLookup = ' . wp_json_encode(
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'axismundi_geodata_lookup' ),
				'termId'  => $term_id,
				'taxonomy' => $screen->taxonomy,
				'i18n'    => array(
					'searching'  => __( 'Searching…', 'axismundi-geodata' ),
					'noResults'  => __( 'No candidates found.', 'axismundi-geodata' ),
					'choose'     => __( 'Choose a candidate from the list or the map.', 'axismundi-geodata' ),
					'use'        => __( 'Use this place', 'axismundi-geodata' ),
					'selected'   => __( 'Candidate selected. Save the term to keep it.', 'axismundi-geodata' ),
					'enterQuery' => __( 'Enter a keyword, name, or address before searching.', 'axismundi-geodata' ),
					'textMode'   => __( 'Search by keyword, place name, or address.', 'axismundi-geodata' ),
					'mapMode'    => __( 'Click the map to set a point, then choose a lookup provider.', 'axismundi-geodata' ),
					'enterPoint' => __( 'Click the map to set a valid point before searching.', 'axismundi-geodata' ),
					'unbound'    => __( 'Place id cleared. Save the term to apply.', 'axismundi-geodata' ),
					'error'      => __( 'Lookup failed.', 'axismundi-geodata' ),
				),
			)
		) . ';',
		'before'
	);
	wp_add_inline_style(
		'common',
		'.axgeo-lookup-modes{display:flex;gap:16px;margin:0 0 8px}.axgeo-lookup-results{margin-top:8px;display:grid;gap:8px;max-width:640px}.axgeo-lookup-candidate{border:1px solid #c3c4c7;border-radius:4px;padding:10px;background:#fff}.axgeo-lookup-candidate strong{display:block}.axgeo-lookup-candidate small{display:block;color:#646970;margin-top:2px}.axgeo-lookup-candidate .button{margin-top:8px}.axgeo-lookup-btn{margin:6px 6px 0 0}#axgeo-lookup-query{display:block;max-width:640px}'
	);
}
add_action( 'admin_enqueue_scripts', 'axismundi_geodata_term_enqueue' );
