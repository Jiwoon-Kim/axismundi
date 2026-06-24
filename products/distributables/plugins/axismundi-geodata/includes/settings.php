<?php
/**
 * Map provider settings (Settings > Geodata).
 *
 * v0.3 ships two providers: `none` (default — no external map requests) and
 * `custom_raster` (a user-supplied XYZ raster tile URL, e.g. their own or an
 * allowed third-party tile server). Google / Naver / MapLibre / PMTiles are
 * later providers that need their own adapters. The public OSM tile server is
 * intentionally not a built-in default — its tile policy forbids that use.
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
		'provider'    => 'none',
		'tile_url'    => '',
		'attribution' => '',
		'min_zoom'    => 1,
		'max_zoom'    => 19,
	);

	$saved = get_option( 'axismundi_geodata_settings', array() );

	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
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

	$out['provider'] = in_array( $input['provider'] ?? '', array( 'none', 'custom_raster' ), true )
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
				'custom_raster' => __( 'Custom raster tiles (XYZ)', 'axismundi-geodata' ),
			) as $opt => $opt_label ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $opt ), selected( $value, $opt, false ), esc_html( $opt_label ) );
			}
			echo '</select>';
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
