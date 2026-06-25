<?php
/**
 * Map provider settings (Settings > Geodata).
 *
 * Providers: `none` (default — no external map requests), `osm` (OpenStreetMap
 * public tiles, **admin preview only** — an explicit opt-in convenience, never
 * used for front-end visitor traffic since OSM's tile policy forbids that), and
 * `custom_raster` (a user-supplied XYZ raster tile URL — their own or an allowed
 * third-party server). Google / Naver / MapLibre / PMTiles are later providers
 * that need their own adapters; front-end map rendering and geocoding are
 * deliberately separated from admin preview because the key/abuse risk differs.
 *
 * Tile resolution goes through axismundi_geodata_resolve_tiles() so the
 * admin-only OSM rule lives in one place.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

/**
 * Current geodata settings, merged over defaults.
 *
 * @return array{provider:string,tile_url:string,attribution:string,min_zoom:int,max_zoom:int}
 */
function axismundi_geodata_get_settings() : array {
	$defaults = array(
		'provider'              => 'none',
		'tile_url'              => '',
		'attribution'           => '',
		'min_zoom'              => 1,
		'max_zoom'              => 19,
		'google_server_api_key' => '',
	);

	$saved = get_option( 'axismundi_geodata_settings', array() );

	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

/**
 * The Google server-side API key for place lookup / geocoding, or '' if unset.
 *
 * Server-side only — used from admin AJAX, never enqueued to the browser. A
 * separate browser key would be needed for any front-end Google map.
 *
 * @return string
 */
function axismundi_geodata_google_api_key() : string {
	return (string) axismundi_geodata_get_settings()['google_server_api_key'];
}

/**
 * Resolve the effective tile layer for a context.
 *
 * The `osm` provider resolves to OpenStreetMap's public tiles only in the
 * `admin` context — it is a low-volume preview convenience and must never tile a
 * front-end map (OSM's tile usage policy forbids it). Front-end callers pass
 * `front`, which falls through to `custom_raster` (or a keyed provider later) and
 * never to OSM.
 *
 * @param string     $context 'admin' or 'front'.
 * @param array|null $cfg     Settings (defaults to the saved settings).
 * @return array{enabled:bool,tile_url:string,attribution:string,min_zoom:int,max_zoom:int}
 */
function axismundi_geodata_resolve_tiles( string $context = 'admin', ?array $cfg = null ) : array {
	$cfg         = $cfg ?? axismundi_geodata_get_settings();
	$tile_url    = '';
	$attribution = '';

	if ( 'osm' === $cfg['provider'] && 'admin' === $context ) {
		$tile_url    = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
		$attribution = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
	} elseif ( 'custom_raster' === $cfg['provider'] && '' !== $cfg['tile_url'] ) {
		$tile_url    = $cfg['tile_url'];
		$attribution = $cfg['attribution'];
	}

	return array(
		'enabled'     => '' !== $tile_url,
		'tile_url'    => $tile_url,
		'attribution' => $attribution,
		'min_zoom'    => (int) $cfg['min_zoom'],
		'max_zoom'    => (int) $cfg['max_zoom'],
	);
}

/**
 * Enqueue Leaflet + the reusable map field when admin tiles are configured.
 *
 * Shared by the attachment editor and the term screens. The caller enqueues its
 * own behaviour script, depends it on 'axismundi-geodata-map-field' when enabled,
 * and localizes the payload from axismundi_geodata_map_inline_js().
 *
 * @return array Resolved tiles (see axismundi_geodata_resolve_tiles()).
 */
function axismundi_geodata_enqueue_map_field() : array {
	$tiles = axismundi_geodata_resolve_tiles( 'admin' );
	if ( $tiles['enabled'] ) {
		wp_enqueue_style( 'axismundi-leaflet', plugins_url( 'assets/vendor/leaflet/leaflet.css', AXISMUNDI_GEODATA_FILE ), array(), '1.9.4' );
		wp_enqueue_script( 'axismundi-leaflet', plugins_url( 'assets/vendor/leaflet/leaflet.js', AXISMUNDI_GEODATA_FILE ), array(), '1.9.4', true );
		wp_enqueue_script( 'axismundi-geodata-map-field', plugins_url( 'assets/map-field.js', AXISMUNDI_GEODATA_FILE ), array( 'axismundi-leaflet' ), AXISMUNDI_GEODATA_VERSION, true );
	}

	return $tiles;
}

/**
 * The window.axismundiGeodataMap assignment that configures the map field.
 *
 * @param array $tiles Resolved tiles.
 * @return string JS statement.
 */
function axismundi_geodata_map_inline_js( array $tiles ) : string {
	return 'window.axismundiGeodataMap = ' . wp_json_encode(
		array(
			'mapEnabled'  => $tiles['enabled'],
			'tileUrl'     => $tiles['tile_url'],
			'attribution' => $tiles['attribution'],
			'minZoom'     => $tiles['min_zoom'],
			'maxZoom'     => $tiles['max_zoom'],
			'imagePath'   => plugins_url( 'assets/vendor/leaflet/images/', AXISMUNDI_GEODATA_FILE ),
		)
	) . ';';
}

/**
 * Sanitize the settings form.
 *
 * @param mixed $input Raw form input.
 * @return array
 */
function axismundi_geodata_sanitize_settings( $input ) : array {
	$input = is_array( $input ) ? $input : array();
	$out   = axismundi_geodata_get_settings();

	$out['provider'] = in_array( $input['provider'] ?? '', array( 'none', 'osm', 'custom_raster' ), true )
		? $input['provider']
		: 'none';

	$url             = isset( $input['tile_url'] ) ? trim( wp_strip_all_tags( (string) $input['tile_url'] ) ) : '';
	$out['tile_url'] = preg_match( '#^https?://#i', $url ) ? $url : '';

	$out['attribution'] = isset( $input['attribution'] )
		? wp_kses(
			(string) $input['attribution'],
			array(
				'a'    => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
				'abbr' => array( 'title' => array() ),
			)
		)
		: '';

	$out['min_zoom'] = max( 0, min( 22, absint( $input['min_zoom'] ?? 1 ) ) );
	$out['max_zoom'] = max( $out['min_zoom'], min( 22, absint( $input['max_zoom'] ?? 19 ) ) );

	$out['google_server_api_key'] = isset( $input['google_server_api_key'] )
		? trim( sanitize_text_field( (string) $input['google_server_api_key'] ) )
		: '';

	return $out;
}

/**
 * Register the setting, section, and fields.
 *
 * @return void
 */
function axismundi_geodata_register_settings() : void {
	register_setting(
		'axismundi_geodata',
		'axismundi_geodata_settings',
		array(
			'type'              => 'object',
			'sanitize_callback' => 'axismundi_geodata_sanitize_settings',
			'default'           => array(),
		)
	);

	add_settings_section(
		'axismundi_geodata_map',
		__( 'Map provider', 'axismundi-geodata' ),
		static function () {
			echo '<p>' . esc_html__( 'Choose how map previews are tiled. With "None" no external map requests are made.', 'axismundi-geodata' ) . '</p>';
		},
		'axismundi_geodata'
	);

	$fields = array(
		'provider'    => __( 'Provider', 'axismundi-geodata' ),
		'tile_url'    => __( 'Tile URL', 'axismundi-geodata' ),
		'attribution' => __( 'Attribution', 'axismundi-geodata' ),
		'min_zoom'    => __( 'Min zoom', 'axismundi-geodata' ),
		'max_zoom'    => __( 'Max zoom', 'axismundi-geodata' ),
	);

	foreach ( $fields as $key => $label ) {
		add_settings_field(
			"axismundi_geodata_{$key}",
			$label,
			'axismundi_geodata_render_field',
			'axismundi_geodata',
			'axismundi_geodata_map',
			array( 'key' => $key, 'label_for' => "axismundi_geodata_{$key}" )
		);
	}

	add_settings_section(
		'axismundi_geodata_lookup',
		__( 'Place lookup', 'axismundi-geodata' ),
		static function () {
			echo '<p>' . esc_html__( 'Optional. A Google server API key lets the term editor look up a place’s coordinates and id. It is used server-side only and is never sent to the browser.', 'axismundi-geodata' ) . '</p>';
		},
		'axismundi_geodata'
	);
	add_settings_field(
		'axismundi_geodata_google_server_api_key',
		__( 'Google API key', 'axismundi-geodata' ),
		'axismundi_geodata_render_field',
		'axismundi_geodata',
		'axismundi_geodata_lookup',
		array( 'key' => 'google_server_api_key', 'label_for' => 'axismundi_geodata_google_server_api_key' )
	);
}
add_action( 'admin_init', 'axismundi_geodata_register_settings' );

/**
 * Render a single settings field.
 *
 * @param array $args Field args.
 * @return void
 */
function axismundi_geodata_render_field( array $args ) : void {
	$key      = $args['key'];
	$settings = axismundi_geodata_get_settings();
	$id       = "axismundi_geodata_{$key}";
	$name     = "axismundi_geodata_settings[{$key}]";
	$value    = $settings[ $key ];

	switch ( $key ) {
		case 'provider':
			printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
			foreach ( array(
				'none'          => __( 'None (no map)', 'axismundi-geodata' ),
				'osm'           => __( 'OpenStreetMap (admin preview only)', 'axismundi-geodata' ),
				'custom_raster' => __( 'Custom raster tiles (XYZ)', 'axismundi-geodata' ),
			) as $opt => $opt_label ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $opt_label ) );
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'OpenStreetMap uses its public tiles for admin map previews only (a low-volume convenience) and is never used to render front-end maps. For the front end, host your own tiles or use a permitted provider via “Custom raster tiles”.', 'axismundi-geodata' ) . '</p>';
			break;

		case 'tile_url':
			printf(
				'<input type="text" id="%s" name="%s" value="%s" class="large-text" placeholder="https://tiles.example.com/{z}/{x}/{y}.png" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value )
			);
			echo '<p class="description">' . esc_html__( 'An XYZ raster tile template with {z}/{x}/{y} (and optional {s}). The public https://tile.openstreetmap.org/{z}/{x}/{y}.png server is not allowed for production use — host your own or use a provider that permits it.', 'axismundi-geodata' ) . '</p>';
			break;

		case 'attribution':
			printf(
				'<input type="text" id="%s" name="%s" value="%s" class="large-text" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value )
			);
			echo '<p class="description">' . esc_html__( 'Required tile attribution (HTML links allowed).', 'axismundi-geodata' ) . '</p>';
			break;

		case 'min_zoom':
		case 'max_zoom':
			printf(
				'<input type="number" id="%s" name="%s" value="%d" min="0" max="22" class="small-text" />',
				esc_attr( $id ),
				esc_attr( $name ),
				(int) $value
			);
			break;

		case 'google_server_api_key':
			printf(
				'<input type="password" id="%s" name="%s" value="%s" class="regular-text" autocomplete="off" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value )
			);
			echo '<p class="description">' . esc_html__( 'A Google Maps Platform server key with the Places API enabled. Restrict it to your server IP and to the Places API in the Google Cloud console.', 'axismundi-geodata' ) . '</p>';
			break;
	}
}

/**
 * Add the settings page under Settings.
 *
 * @return void
 */
function axismundi_geodata_settings_menu() : void {
	add_options_page(
		__( 'Geodata', 'axismundi-geodata' ),
		__( 'Geodata', 'axismundi-geodata' ),
		'manage_options',
		'axismundi-geodata',
		'axismundi_geodata_settings_page'
	);
}
add_action( 'admin_menu', 'axismundi_geodata_settings_menu' );

/**
 * Render the settings page.
 *
 * @return void
 */
function axismundi_geodata_settings_page() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo '<div class="wrap"><h1>' . esc_html__( 'Geodata', 'axismundi-geodata' ) . '</h1>';
	echo '<form action="options.php" method="post">';
	settings_fields( 'axismundi_geodata' );
	do_settings_sections( 'axismundi_geodata' );
	submit_button();
	echo '</form></div>';
}
