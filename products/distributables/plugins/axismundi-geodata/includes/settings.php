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
		'map_pack_id'           => 0,
		'front_provider'        => 'none',
		'front_tile_url'        => '',
		'front_attribution'     => '',
		'front_map_pack_id'     => 0,
		'google_server_api_key' => '',
		'nominatim_mode'        => 'none',
		'nominatim_endpoint'    => '',
		'nominatim_contact'     => '',
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
	$cfg = $cfg ?? axismundi_geodata_get_settings();

	// Admin preview and front-end map blocks are separate provider axes. OSM public
	// tiles stay admin-only and are not a front option.
	if ( 'front' === $context ) {
		$provider    = (string) $cfg['front_provider'];
		$pack_id     = (int) $cfg['front_map_pack_id'];
		$raster_url  = (string) $cfg['front_tile_url'];
		$raster_attr = (string) $cfg['front_attribution'];
	} else {
		$provider    = (string) $cfg['provider'];
		$pack_id     = (int) $cfg['map_pack_id'];
		$raster_url  = (string) $cfg['tile_url'];
		$raster_attr = (string) $cfg['attribution'];
	}

	$tile_url    = '';
	$attribution = '';

	// PMTiles map pack: a self-hosted vector basemap rendered with MapLibre. A whole
	// different stack from raster tiles, flagged with kind = 'pmtiles'.
	if ( 'pmtiles' === $provider ) {
		$pack = $pack_id && function_exists( 'axismundi_geodata_is_pmtiles_attachment' ) && axismundi_geodata_is_pmtiles_attachment( $pack_id )
			? axismundi_geodata_map_pack( $pack_id )
			: array();
		$url  = ! empty( $pack ) ? (string) wp_get_attachment_url( $pack_id ) : '';

		$bounds = array();
		$center = array();
		if ( '' !== $url && ! empty( $pack['bounds'] ) ) {
			$parts = array_map( 'floatval', explode( ',', $pack['bounds'] ) );
			if ( 4 === count( $parts ) ) {
				$bounds = $parts; // W, S, E, N.
				$center = array( ( $parts[0] + $parts[2] ) / 2, ( $parts[1] + $parts[3] ) / 2 );
			}
		}

		// Only the Protomaps schema has a built-in style today; other schemas need an
		// uploaded style before the preview can render them.
		$schema = ! empty( $pack['schema'] ) ? (string) $pack['schema'] : '';

		return array(
			'enabled'     => '' !== $url && 'protomaps' === $schema,
			'kind'        => 'pmtiles',
			'schema'      => $schema,
			'tile_url'    => '',
			'pack_url'    => $url,
			'bounds'      => $bounds,
			'center'      => $center,
			'attribution' => ! empty( $pack['attribution'] ) ? (string) $pack['attribution'] : '',
			'min_zoom'    => ! empty( $pack['min_zoom'] ) ? (int) $pack['min_zoom'] : 0,
			'max_zoom'    => ! empty( $pack['max_zoom'] ) ? (int) $pack['max_zoom'] : 18,
		);
	}

	if ( 'osm' === $provider && 'admin' === $context ) {
		$tile_url    = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
		$attribution = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
	} elseif ( 'custom_raster' === $provider && '' !== $raster_url ) {
		$tile_url    = $raster_url;
		$attribution = $raster_attr;
	}

	return array(
		'enabled'     => '' !== $tile_url,
		'kind'        => 'raster',
		'schema'      => '',
		'tile_url'    => $tile_url,
		'pack_url'    => '',
		'bounds'      => array(),
		'center'      => array(),
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
	if ( ! $tiles['enabled'] ) {
		return $tiles;
	}

	// Both renderers register the same 'axismundi-geodata-map-field' handle and the
	// same window.axismundiGeodataInitMapField contract, so consumers (term / media
	// editors) don't care which one is active.
	if ( 'pmtiles' === $tiles['kind'] ) {
		wp_enqueue_style( 'axismundi-maplibre', plugins_url( 'assets/vendor/maplibre/maplibre-gl.css', AXISMUNDI_GEODATA_FILE ), array(), '5.24.0' );
		wp_enqueue_script( 'axismundi-maplibre', plugins_url( 'assets/vendor/maplibre/maplibre-gl.js', AXISMUNDI_GEODATA_FILE ), array(), '5.24.0', true );
		wp_enqueue_script( 'axismundi-pmtiles', plugins_url( 'assets/vendor/pmtiles/pmtiles.js', AXISMUNDI_GEODATA_FILE ), array(), '4.4.1', true );
		wp_enqueue_script( 'axismundi-protomaps-basemaps', plugins_url( 'assets/vendor/protomaps/basemaps.js', AXISMUNDI_GEODATA_FILE ), array(), '5.7.2', true );
		wp_enqueue_script( 'axismundi-geodata-map-field', plugins_url( 'assets/map-field-maplibre.js', AXISMUNDI_GEODATA_FILE ), array( 'axismundi-maplibre', 'axismundi-pmtiles', 'axismundi-protomaps-basemaps' ), AXISMUNDI_GEODATA_VERSION, true );
	} else {
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
			'kind'        => $tiles['kind'] ?? 'raster',
			'tileUrl'     => $tiles['tile_url'],
			'attribution' => $tiles['attribution'],
			'minZoom'     => $tiles['min_zoom'],
			'maxZoom'     => $tiles['max_zoom'],
			'imagePath'   => plugins_url( 'assets/vendor/leaflet/images/', AXISMUNDI_GEODATA_FILE ),
			'packUrl'     => $tiles['pack_url'] ?? '',
			'center'      => $tiles['center'] ?? array(),
			'bounds'      => $tiles['bounds'] ?? array(),
			'glyphs'      => 'https://protomaps.github.io/basemaps-assets/fonts/{fontstack}/{range}.pbf',
			'sprite'      => 'https://protomaps.github.io/basemaps-assets/sprites/v4/light',
			'lang'        => axismundi_geodata_lookup_language_code(),
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

	$out['provider'] = in_array( $input['provider'] ?? '', array( 'none', 'osm', 'custom_raster', 'pmtiles' ), true )
		? $input['provider']
		: 'none';

	$out['map_pack_id'] = absint( $input['map_pack_id'] ?? 0 );

	$out['front_provider'] = in_array( $input['front_provider'] ?? '', array( 'none', 'custom_raster', 'pmtiles' ), true )
		? $input['front_provider']
		: 'none';

	$front_url             = isset( $input['front_tile_url'] ) ? trim( wp_strip_all_tags( (string) $input['front_tile_url'] ) ) : '';
	$out['front_tile_url'] = preg_match( '#^https?://#i', $front_url ) ? $front_url : '';

	$out['front_attribution'] = isset( $input['front_attribution'] )
		? wp_kses(
			(string) $input['front_attribution'],
			array(
				'a'    => array( 'href' => array(), 'target' => array(), 'rel' => array() ),
				'abbr' => array( 'title' => array() ),
			)
		)
		: '';

	$out['front_map_pack_id'] = absint( $input['front_map_pack_id'] ?? 0 );

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

	$out['nominatim_mode'] = in_array( $input['nominatim_mode'] ?? '', array( 'none', 'public', 'custom' ), true )
		? $input['nominatim_mode']
		: 'none';

	$nurl                      = isset( $input['nominatim_endpoint'] ) ? trim( wp_strip_all_tags( (string) $input['nominatim_endpoint'] ) ) : '';
	$out['nominatim_endpoint'] = preg_match( '#^https?://#i', $nurl ) ? $nurl : '';

	$out['nominatim_contact'] = isset( $input['nominatim_contact'] )
		? trim( sanitize_text_field( (string) $input['nominatim_contact'] ) )
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
		__( 'Admin preview maps', 'axismundi-geodata' ),
		static function () {
			echo '<p>' . esc_html__( 'Tiles for the coordinate-picker maps in the term and attachment editors (admin only). With "None" no external map requests are made. The front-end map block has its own provider in “Front-end map blocks” below — this admin setting does not affect visitors.', 'axismundi-geodata' ) . '</p>';
		},
		'axismundi_geodata'
	);

	$fields = array(
		'provider'    => __( 'Provider', 'axismundi-geodata' ),
		'map_pack_id' => __( 'Map pack', 'axismundi-geodata' ),
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
		'axismundi_geodata_front',
		__( 'Front-end map blocks', 'axismundi-geodata' ),
		static function () {
			echo '<p>' . esc_html__( 'The provider for the Axismundi Map block shown to visitors — separate from the admin preview. Self-host the tiles: a custom raster tile URL (rendered with Leaflet) or an uploaded PMTiles map pack (MapLibre). The public OpenStreetMap tile server is not offered here.', 'axismundi-geodata' ) . '</p>';
		},
		'axismundi_geodata'
	);
	foreach ( array(
		'front_provider'    => __( 'Provider', 'axismundi-geodata' ),
		'front_map_pack_id' => __( 'Map pack', 'axismundi-geodata' ),
		'front_tile_url'    => __( 'Tile URL', 'axismundi-geodata' ),
		'front_attribution' => __( 'Attribution', 'axismundi-geodata' ),
	) as $key => $label ) {
		add_settings_field(
			"axismundi_geodata_{$key}",
			$label,
			'axismundi_geodata_render_field',
			'axismundi_geodata',
			'axismundi_geodata_front',
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
	foreach ( array(
		'google_server_api_key' => __( 'Google API key', 'axismundi-geodata' ),
		'nominatim_mode'        => __( 'OpenStreetMap lookup', 'axismundi-geodata' ),
		'nominatim_endpoint'    => __( 'Nominatim endpoint', 'axismundi-geodata' ),
		'nominatim_contact'     => __( 'Nominatim contact', 'axismundi-geodata' ),
	) as $key => $label ) {
		add_settings_field(
			"axismundi_geodata_{$key}",
			$label,
			'axismundi_geodata_render_field',
			'axismundi_geodata',
			'axismundi_geodata_lookup',
			array( 'key' => $key, 'label_for' => "axismundi_geodata_{$key}" )
		);
	}
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
				'pmtiles'       => __( 'Uploaded PMTiles map pack', 'axismundi-geodata' ),
			) as $opt => $opt_label ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $opt_label ) );
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'OpenStreetMap public tiles are admin-preview only. “Uploaded PMTiles map pack” renders a self-hosted vector basemap (Media > a .pmtiles file) with MapLibre: map tiles are served from the uploaded file, while label glyphs and sprites may load from the public Protomaps assets host.', 'axismundi-geodata' ) . '</p>';
			break;

		case 'front_provider':
			printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
			foreach ( array(
				'none'          => __( 'None (no map)', 'axismundi-geodata' ),
				'custom_raster' => __( 'Custom raster tiles (XYZ)', 'axismundi-geodata' ),
				'pmtiles'       => __( 'Uploaded PMTiles map pack', 'axismundi-geodata' ),
			) as $opt => $opt_label ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $opt_label ) );
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'The basemap visitors see in the Axismundi Map block. Self-hosted only — a custom raster tile URL (Leaflet) or an uploaded PMTiles map pack (MapLibre).', 'axismundi-geodata' ) . '</p>';
			break;

		case 'map_pack_id':
		case 'front_map_pack_id':
			$packs = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_mime_type' => AXISMUNDI_GEODATA_PMTILES_MIME,
					'post_status'    => 'inherit',
					'numberposts'    => 100,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
			printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
			printf( '<option value="0">%s</option>', esc_html__( '— Select a map pack —', 'axismundi-geodata' ) );
			foreach ( $packs as $pack ) {
				printf( '<option value="%d"%s>%s</option>', (int) $pack->ID, selected( (int) $value, (int) $pack->ID, false ), esc_html( get_the_title( $pack ) ) );
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Used when Provider is “Uploaded PMTiles map pack”. Upload a .pmtiles file under Media, then choose it here. Label fonts load from the public Protomaps assets host.', 'axismundi-geodata' ) . '</p>';
			break;

		case 'tile_url':
		case 'front_tile_url':
			printf(
				'<input type="text" id="%s" name="%s" value="%s" class="large-text" placeholder="https://tiles.example.com/{z}/{x}/{y}.png" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value )
			);
			echo '<p class="description">' . esc_html__( 'An XYZ raster tile template with {z}/{x}/{y} (and optional {s}). The public https://tile.openstreetmap.org/{z}/{x}/{y}.png server is not allowed for production use — host your own or use a provider that permits it.', 'axismundi-geodata' ) . '</p>';
			if ( 'front_tile_url' === $key ) {
				echo '<p class="description">' . esc_html__( 'Examples you can paste:', 'axismundi-geodata' ) . '</p>';
				echo '<ul class="description">';
				foreach ( array(
					'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}.png',
					'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png',
					'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png',
					'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
					'https://api.maptiler.com/maps/streets-v4/256/{z}/{x}/{y}.png?key=YOUR_MAPTILER_API_KEY',
					'https://api.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=YOUR_THUNDERFOREST_API_KEY',
					'https://tiles.stadiamaps.com/tiles/alidade_smooth/{z}/{x}/{y}.png?api_key=YOUR_STADIA_API_KEY',
				) as $example ) {
					printf( '<li><code>%s</code></li>', esc_html( $example ) );
				}
				echo '</ul>';
			}
			break;

		case 'attribution':
		case 'front_attribution':
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
			echo '<p class="description">' . esc_html__( 'A Google Maps Platform server key with the Places API enabled, for place lookup only. Restrict it to your server IP and to the Places API. A front-end Google map would need a separate browser key (HTTP-referrer restricted) — this one is never sent to the browser.', 'axismundi-geodata' ) . '</p>';
			break;

		case 'nominatim_mode':
			printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
			foreach ( array(
				'none'   => __( 'Disabled', 'axismundi-geodata' ),
				'public' => __( 'Public Nominatim (admin manual lookup only)', 'axismundi-geodata' ),
				'custom' => __( 'Custom Nominatim endpoint', 'axismundi-geodata' ),
			) as $opt => $opt_label ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $opt_label ) );
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Disabled by default. “Public Nominatim” uses nominatim.openstreetmap.org for low-volume manual admin lookup only — never for autocomplete, bulk import, or front-end use. For anything more, run your own instance and choose “Custom”.', 'axismundi-geodata' ) . '</p>';
			break;

		case 'nominatim_endpoint':
			printf(
				'<input type="text" id="%s" name="%s" value="%s" class="large-text" placeholder="https://nominatim.example.com" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value )
			);
			echo '<p class="description">' . esc_html__( 'Base URL of your Nominatim instance (used when OpenStreetMap lookup is set to Custom).', 'axismundi-geodata' ) . '</p>';
			break;

		case 'nominatim_contact':
			printf(
				'<input type="text" id="%s" name="%s" value="%s" class="regular-text" placeholder="you@example.com" />',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value )
			);
			echo '<p class="description">' . esc_html__( 'A contact email or URL added to the Nominatim User-Agent, as its usage policy requests.', 'axismundi-geodata' ) . '</p>';
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
