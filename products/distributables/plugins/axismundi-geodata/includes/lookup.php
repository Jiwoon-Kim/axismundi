<?php
/**
 * Place-lookup provider registry and shared term context.
 *
 * Each provider (Google, OSM/Nominatim, …) registers a search callback through
 * the axismundi_geodata_lookup_providers filter; the generic AJAX endpoints
 * dispatch to it and bind the chosen candidate through bind_place_identity(), so a
 * provider only implements "term -> candidates". The geo-context helpers (query,
 * country, language) are shared so every provider phrases the same search.
 *
 * Candidate shape (all providers normalise to this):
 *   place_id      raw provider id (node/123, ChIJ…, Q123) — no source prefix
 *   name, address strings
 *   latitude/longitude  floats or null
 *   place_type    mapped ax_geo_place_type slug, or ''
 *   provider_type the provider's own type string (display only)
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registered lookup providers, slug => { label, enabled (callable), search (callable) }.
 *
 * @return array<string,array{label:string,enabled:callable,search:callable}>
 */
function axismundi_geodata_lookup_providers() : array {
	return (array) apply_filters( 'axismundi_geodata_lookup_providers', array() );
}

/**
 * Providers whose `enabled` callback returns true.
 *
 * @return array<string,array>
 */
function axismundi_geodata_lookup_enabled_providers() : array {
	return array_filter(
		axismundi_geodata_lookup_providers(),
		static function ( $provider ) {
			return ! empty( $provider['enabled'] ) && (bool) call_user_func( $provider['enabled'] );
		}
	);
}

/**
 * Locale -> 2-letter language code for the search request.
 *
 * @return string
 */
function axismundi_geodata_lookup_language_code() : string {
	$lang = strtolower( substr( determine_locale(), 0, 2 ) );

	return preg_match( '/^[a-z]{2}$/', $lang ) ? $lang : 'en';
}

/**
 * A term's geo_area context (own/assigned area plus ancestors), leaf first.
 *
 * @param WP_Term $term Term being looked up.
 * @return WP_Term[]
 */
function axismundi_geodata_lookup_area_context( WP_Term $term ) : array {
	$ids = array();

	if ( 'geotag' === $term->taxonomy ) {
		$ids = axismundi_geodata_get_geotag_area_chain( $term->term_id );
	} elseif ( 'geo_area' === $term->taxonomy ) {
		$ids = array_merge( array( $term->term_id ), get_ancestors( $term->term_id, 'geo_area', 'taxonomy' ) );
	}

	$areas = array();
	foreach ( $ids as $id ) {
		$area = get_term( (int) $id, 'geo_area' );
		if ( $area instanceof WP_Term ) {
			$areas[] = $area;
		}
	}

	return $areas;
}

/**
 * Best available ISO 3166-1 alpha-2 country code from a term's area context.
 *
 * @param WP_Term $term Term being looked up.
 * @return string
 */
function axismundi_geodata_lookup_country_code( WP_Term $term ) : string {
	foreach ( axismundi_geodata_lookup_area_context( $term ) as $area ) {
		$code = (string) get_term_meta( $area->term_id, 'ax_geo_country_code', true );
		if ( '' !== $code ) {
			return strtoupper( $code );
		}
	}

	return '';
}

/**
 * Free-text query: term name plus its containing area names.
 *
 * @param WP_Term $term Term being looked up.
 * @return string
 */
function axismundi_geodata_lookup_term_query( WP_Term $term ) : string {
	$parts = array( $term->name );

	foreach ( axismundi_geodata_lookup_area_context( $term ) as $area ) {
		if ( $area->name !== $term->name ) {
			$parts[] = $area->name;
		}
	}

	return implode( ' ', array_values( array_unique( array_filter( $parts ) ) ) );
}

/**
 * Validate a term id for lookup and return the term.
 *
 * @param int $term_id Term id.
 * @return WP_Term|WP_Error
 */
function axismundi_geodata_lookup_get_term( int $term_id ) {
	$term = get_term( $term_id );
	if ( ! $term instanceof WP_Term || ! in_array( $term->taxonomy, array( 'geo_area', 'geotag' ), true ) ) {
		return new WP_Error( 'axismundi_lookup_bad_term', __( 'Invalid geo term.', 'axismundi-geodata' ) );
	}

	return $term;
}

/**
 * Resolve the provider + term from an AJAX request after the shared nonce / cap
 * checks. Sends a JSON error and exits on failure.
 *
 * @return array{0:string,1:array,2:int}
 */
function axismundi_geodata_lookup_ajax_context() : array {
	check_ajax_referer( 'axismundi_geodata_lookup', 'nonce' );

	if ( ! current_user_can( 'manage_categories' ) ) {
		wp_send_json_error( array( 'message' => __( 'You are not allowed to manage geo terms.', 'axismundi-geodata' ) ), 403 );
	}

	$provider  = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
	$term_id   = isset( $_POST['term_id'] ) ? absint( wp_unslash( $_POST['term_id'] ) ) : 0;
	$providers = axismundi_geodata_lookup_enabled_providers();
	if ( '' === $provider || ! isset( $providers[ $provider ] ) ) {
		wp_send_json_error( array( 'message' => __( 'Unknown or disabled lookup provider.', 'axismundi-geodata' ) ), 400 );
	}

	return array( $provider, $providers[ $provider ], $term_id );
}

/**
 * AJAX: search a provider for term candidates (cached briefly per query).
 *
 * @return void
 */
function axismundi_geodata_ajax_lookup() : void {
	list( $provider, $config, $term_id ) = axismundi_geodata_lookup_ajax_context();

	$term = axismundi_geodata_lookup_get_term( $term_id );
	if ( is_wp_error( $term ) ) {
		wp_send_json_error( array( 'message' => $term->get_error_message() ), 400 );
	}

	$cache_key = 'axgeo_lk_' . $provider . '_' . md5( axismundi_geodata_lookup_term_query( $term ) . '|' . axismundi_geodata_lookup_language_code() );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		wp_send_json_success( array( 'candidates' => $cached ) );
	}

	$result = call_user_func( $config['search'], $term_id );
	if ( is_wp_error( $result ) ) {
		$data   = $result->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
		wp_send_json_error( array( 'message' => $result->get_error_message() ), $status );
	}

	set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
	wp_send_json_success( array( 'candidates' => $result ) );
}
add_action( 'wp_ajax_axismundi_geodata_lookup', 'axismundi_geodata_ajax_lookup' );

/**
 * AJAX: bind a selected candidate to the term through the shared identity path.
 *
 * @return void
 */
function axismundi_geodata_ajax_bind() : void {
	list( $provider, $config, $term_id ) = axismundi_geodata_lookup_ajax_context();

	$term = axismundi_geodata_lookup_get_term( $term_id );
	if ( is_wp_error( $term ) ) {
		wp_send_json_error( array( 'message' => $term->get_error_message() ), 400 );
	}

	// Nonce + capability are verified in axismundi_geodata_lookup_ajax_context() above.
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	$place_id = isset( $_POST['place_id'] ) ? sanitize_text_field( wp_unslash( $_POST['place_id'] ) ) : '';
	$lat      = isset( $_POST['latitude'] ) ? sanitize_text_field( wp_unslash( $_POST['latitude'] ) ) : '';
	$lng      = isset( $_POST['longitude'] ) ? sanitize_text_field( wp_unslash( $_POST['longitude'] ) ) : '';
	$facts    = array(
		'geo_latitude'      => '' !== $lat ? axismundi_geodata_sanitize_latitude( $lat ) : null,
		'geo_longitude'     => '' !== $lng ? axismundi_geodata_sanitize_longitude( $lng ) : null,
		'geo_address'       => isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '',
		'ax_geo_place_type' => isset( $_POST['place_type'] ) ? sanitize_key( wp_unslash( $_POST['place_type'] ) ) : '',
	);
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	if ( '' === $place_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid place binding.', 'axismundi-geodata' ) ), 400 );
	}

	$canonical = axismundi_geodata_bind_place_identity( $term_id, $provider, $place_id, $facts );
	if ( '' === $canonical ) {
		wp_send_json_error( array( 'message' => __( 'Could not bind the selected place.', 'axismundi-geodata' ) ), 400 );
	}

	wp_send_json_success(
		array(
			'canonical' => $canonical,
			'place_id'  => $canonical,
			'facts'     => $facts,
		)
	);
}
add_action( 'wp_ajax_axismundi_geodata_bind', 'axismundi_geodata_ajax_bind' );

/**
 * AJAX: remove a term's place binding (the immediate counterpart to Bind).
 *
 * @return void
 */
function axismundi_geodata_ajax_unbind() : void {
	check_ajax_referer( 'axismundi_geodata_lookup', 'nonce' );

	if ( ! current_user_can( 'manage_categories' ) ) {
		wp_send_json_error( array( 'message' => __( 'You are not allowed to manage geo terms.', 'axismundi-geodata' ) ), 403 );
	}

	$term_id = isset( $_POST['term_id'] ) ? absint( wp_unslash( $_POST['term_id'] ) ) : 0;
	$term    = axismundi_geodata_lookup_get_term( $term_id );
	if ( is_wp_error( $term ) ) {
		wp_send_json_error( array( 'message' => $term->get_error_message() ), 400 );
	}

	delete_term_meta( $term_id, 'ax_geo_place_id' );
	delete_term_meta( $term_id, 'ax_geo_source' ); // Legacy split key.

	wp_send_json_success();
}
add_action( 'wp_ajax_axismundi_geodata_unbind', 'axismundi_geodata_ajax_unbind' );
