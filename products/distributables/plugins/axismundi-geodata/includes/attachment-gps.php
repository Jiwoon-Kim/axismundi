<?php
/**
 * Attachment GPS editor (Edit Media screen).
 *
 * An attachment carries the capture coordinate of the file itself — a file
 * property, not a place tag — so it lives in postmeta (geo_latitude / longitude /
 * altitude / public), editable on the Edit Media screen with a Leaflet mini map.
 * "Import from EXIF" reads the ORIGINAL file's GPS once, on demand; nothing is
 * auto-imported or auto-published (geo_public defaults false). Core never reads
 * GPS into _wp_attachment_metadata, so the import goes straight to the file.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Convert one EXIF rational ("129/1") to a float.
 *
 * @param mixed $rational Rational string.
 * @return float|null
 */
function axismundi_geodata_gps_rational( $rational ) : ?float {
	if ( ! is_string( $rational ) && ! is_numeric( $rational ) ) {
		return null;
	}
	$parts = explode( '/', (string) $rational );
	$den   = isset( $parts[1] ) ? (float) $parts[1] : 1.0;

	return 0.0 === $den ? 0.0 : (float) $parts[0] / $den;
}

/**
 * Convert an EXIF DMS triple to a signed decimal degree.
 *
 * @param mixed  $parts [deg, min, sec] rationals.
 * @param string $ref   N/S/E/W reference.
 * @return float|null
 */
function axismundi_geodata_gps_dms_to_decimal( $parts, $ref ) : ?float {
	if ( ! is_array( $parts ) || count( $parts ) < 3 ) {
		return null;
	}
	$deg = axismundi_geodata_gps_rational( $parts[0] );
	$min = axismundi_geodata_gps_rational( $parts[1] );
	$sec = axismundi_geodata_gps_rational( $parts[2] );
	if ( null === $deg || null === $min || null === $sec ) {
		return null;
	}

	$decimal = $deg + $min / 60 + $sec / 3600;
	if ( in_array( strtoupper( (string) $ref ), array( 'S', 'W' ), true ) ) {
		$decimal = -$decimal;
	}

	return $decimal;
}

/**
 * Read GPS coordinates from an attachment's ORIGINAL file EXIF.
 *
 * @param int $attachment_id Attachment ID.
 * @return array{latitude:float,longitude:float,altitude:float|null}|null
 */
function axismundi_geodata_attachment_exif_gps( int $attachment_id ) : ?array {
	if ( ! function_exists( 'exif_read_data' ) ) {
		return null;
	}
	$original = wp_get_original_image_path( $attachment_id );
	$file     = $original ? $original : get_attached_file( $attachment_id );
	if ( ! $file || ! is_readable( $file ) ) {
		return null;
	}

	$exif = @exif_read_data( $file, 'GPS', true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- non-image / no-EXIF files warn.
	$gps  = is_array( $exif ) && ! empty( $exif['GPS'] ) ? $exif['GPS'] : null;
	if ( empty( $gps['GPSLatitude'] ) || empty( $gps['GPSLongitude'] ) ) {
		return null;
	}

	$lat = axismundi_geodata_gps_dms_to_decimal( $gps['GPSLatitude'], $gps['GPSLatitudeRef'] ?? 'N' );
	$lng = axismundi_geodata_gps_dms_to_decimal( $gps['GPSLongitude'], $gps['GPSLongitudeRef'] ?? 'E' );
	if ( null === $lat || null === $lng ) {
		return null;
	}

	$alt = null;
	if ( isset( $gps['GPSAltitude'] ) ) {
		$alt = axismundi_geodata_gps_rational( $gps['GPSAltitude'] );
		if ( null !== $alt && isset( $gps['GPSAltitudeRef'] ) && 1 === (int) $gps['GPSAltitudeRef'] ) {
			$alt = -$alt;
		}
	}

	return array(
		'latitude'  => round( $lat, 7 ),
		'longitude' => round( $lng, 7 ),
		'altitude'  => null === $alt ? null : round( $alt, 2 ),
	);
}

/**
 * Register the Location meta box on attachments.
 *
 * @return void
 */
function axismundi_geodata_attachment_meta_box() : void {
	add_meta_box(
		'axismundi-geodata-gps',
		__( 'Location (GPS)', 'axismundi-geodata' ),
		'axismundi_geodata_render_attachment_box',
		'attachment',
		'normal'
	);
}
add_action( 'add_meta_boxes_attachment', 'axismundi_geodata_attachment_meta_box' );

/**
 * Render the attachment Location meta box.
 *
 * @param WP_Post $post Attachment post.
 * @return void
 */
function axismundi_geodata_render_attachment_box( WP_Post $post ) : void {
	wp_nonce_field( 'axismundi_geodata_attachment', 'axismundi_geodata_attachment_nonce' );

	$lat      = get_post_meta( $post->ID, 'geo_latitude', true );
	$lng      = get_post_meta( $post->ID, 'geo_longitude', true );
	$alt      = get_post_meta( $post->ID, 'geo_altitude', true );
	$public   = (bool) get_post_meta( $post->ID, 'geo_public', true );
	$has_exif = null !== axismundi_geodata_attachment_exif_gps( $post->ID );
	$cfg      = axismundi_geodata_get_settings();

	echo '<p>';
	printf(
		'<label for="axgeo-lat">%s</label> <input type="number" step="any" id="axgeo-lat" name="geo_latitude" value="%s" /> ',
		esc_html__( 'Latitude', 'axismundi-geodata' ),
		esc_attr( $lat )
	);
	printf(
		'<label for="axgeo-lng">%s</label> <input type="number" step="any" id="axgeo-lng" name="geo_longitude" value="%s" /> ',
		esc_html__( 'Longitude', 'axismundi-geodata' ),
		esc_attr( $lng )
	);
	printf(
		'<label for="axgeo-alt">%s</label> <input type="number" step="any" id="axgeo-alt" name="geo_altitude" value="%s" />',
		esc_html__( 'Altitude (m)', 'axismundi-geodata' ),
		esc_attr( $alt )
	);
	echo '</p>';

	echo '<p>';
	printf(
		'<label><input type="checkbox" name="geo_public" value="1"%s /> %s</label>',
		checked( $public, true, false ),
		esc_html__( 'Public — may be shown on the site / map / REST', 'axismundi-geodata' )
	);
	echo '</p>';

	if ( $has_exif ) {
		printf(
			'<p><button type="button" class="button" id="axgeo-import-exif" data-id="%d">%s</button> ' .
			'<button type="button" class="button-link" id="axgeo-clear">%s</button></p>',
			(int) $post->ID,
			esc_html__( 'Import from EXIF', 'axismundi-geodata' ),
			esc_html__( 'Clear', 'axismundi-geodata' )
		);
	} else {
		printf( '<p class="description">%s</p>', esc_html__( 'No GPS found in this file’s EXIF.', 'axismundi-geodata' ) );
	}

	if ( 'custom_raster' === $cfg['provider'] && '' !== $cfg['tile_url'] ) {
		echo '<div id="axgeo-map" style="height:260px;max-width:520px;margin-top:8px;border-radius:4px;overflow:hidden;"></div>';
	} else {
		printf( '<p class="description">%s</p>', esc_html__( 'Configure a map provider in Settings → Geodata to enable the map.', 'axismundi-geodata' ) );
	}
}

/**
 * Save the attachment Location fields.
 *
 * @param int $post_id Attachment ID.
 * @return void
 */
function axismundi_geodata_save_attachment( int $post_id ) : void {
	if (
		! isset( $_POST['axismundi_geodata_attachment_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['axismundi_geodata_attachment_nonce'] ) ), 'axismundi_geodata_attachment' )
		|| ! current_user_can( 'edit_post', $post_id )
	) {
		return;
	}

	foreach ( array( 'geo_latitude', 'geo_longitude', 'geo_altitude' ) as $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}
		$raw = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		if ( '' === $raw ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, (float) $raw );
		}
	}

	update_post_meta( $post_id, 'geo_public', empty( $_POST['geo_public'] ) ? false : true );
}
add_action( 'edit_attachment', 'axismundi_geodata_save_attachment' );

/**
 * AJAX: return an attachment's EXIF GPS as decimals for the Import button.
 *
 * @return void
 */
function axismundi_geodata_ajax_exif() : void {
	check_ajax_referer( 'axismundi_geodata_exif' );
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
		wp_send_json_error();
	}

	$gps = axismundi_geodata_attachment_exif_gps( $id );
	if ( ! $gps ) {
		wp_send_json_error();
	}

	wp_send_json_success( $gps );
}
add_action( 'wp_ajax_axismundi_geodata_exif', 'axismundi_geodata_ajax_exif' );

/**
 * Enqueue Leaflet + the map-field / attachment scripts on the Edit Media screen.
 *
 * @param string $hook Current admin page.
 * @return void
 */
function axismundi_geodata_attachment_enqueue( string $hook ) : void {
	if ( 'post.php' !== $hook ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'attachment' !== $screen->post_type ) {
		return;
	}

	$cfg         = axismundi_geodata_get_settings();
	$map_enabled = 'custom_raster' === $cfg['provider'] && '' !== $cfg['tile_url'];
	$deps        = array( 'wp-i18n' );

	if ( $map_enabled ) {
		wp_enqueue_style( 'axismundi-leaflet', plugins_url( 'assets/vendor/leaflet/leaflet.css', AXISMUNDI_GEODATA_FILE ), array(), '1.9.4' );
		wp_enqueue_script( 'axismundi-leaflet', plugins_url( 'assets/vendor/leaflet/leaflet.js', AXISMUNDI_GEODATA_FILE ), array(), '1.9.4', true );
		wp_enqueue_script( 'axismundi-geodata-map-field', plugins_url( 'assets/map-field.js', AXISMUNDI_GEODATA_FILE ), array( 'axismundi-leaflet' ), AXISMUNDI_GEODATA_VERSION, true );
		$deps[] = 'axismundi-geodata-map-field';
	}

	wp_enqueue_script(
		'axismundi-geodata-attachment',
		plugins_url( 'assets/attachment-gps.js', AXISMUNDI_GEODATA_FILE ),
		$deps,
		AXISMUNDI_GEODATA_VERSION,
		true
	);

	wp_add_inline_script(
		'axismundi-geodata-attachment',
		'window.axismundiGeodataMap = ' . wp_json_encode(
			array(
				'mapEnabled'  => $map_enabled,
				'tileUrl'     => $cfg['tile_url'],
				'attribution' => $cfg['attribution'],
				'minZoom'     => (int) $cfg['min_zoom'],
				'maxZoom'     => (int) $cfg['max_zoom'],
				'imagePath'   => plugins_url( 'assets/vendor/leaflet/images/', AXISMUNDI_GEODATA_FILE ),
			)
		) . '; window.axismundiGeodataAjax = ' . wp_json_encode(
			array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'axismundi_geodata_exif' ),
			)
		) . ';',
		'before'
	);

	wp_set_script_translations( 'axismundi-geodata-attachment', 'axismundi-geodata' );
}
add_action( 'admin_enqueue_scripts', 'axismundi_geodata_attachment_enqueue' );
