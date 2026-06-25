<?php
/**
 * Map Packs — self-hosted PMTiles registered as WordPress attachments.
 *
 * A "map pack" is a .pmtiles file (a single-file vector/raster tile archive) the
 * operator uploads or places on the server. The plugin does not generate or bundle
 * tile data — that is user-provided content built with external tools (Protomaps,
 * Planetiler, …). Here we only: allow the upload, read the PMTiles header to learn
 * the bounds / zoom / tile type, and store an editable Map Pack record on the
 * attachment. Rendering (a MapLibre/PMTiles front-end map block) is a later step.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_GEODATA_PMTILES_MIME = 'application/vnd.pmtiles';

/**
 * Allow .pmtiles uploads (data file, not executable; gated by upload_files cap).
 *
 * @param array $mimes Allowed mime types.
 * @return array
 */
function axismundi_geodata_pmtiles_upload_mimes( array $mimes ) : array {
	$mimes['pmtiles'] = AXISMUNDI_GEODATA_PMTILES_MIME;

	return $mimes;
}
add_filter( 'upload_mimes', 'axismundi_geodata_pmtiles_upload_mimes' );

/**
 * Map a .pmtiles filename to its ext/type so WordPress accepts the upload.
 *
 * @param array  $data     ext/type/proper_filename.
 * @param string $file     Full path.
 * @param string $filename Filename.
 * @return array
 */
function axismundi_geodata_pmtiles_filetype( $data, $file, $filename ) {
	if ( preg_match( '/\.pmtiles$/i', (string) $filename ) ) {
		$data['ext']  = 'pmtiles';
		$data['type'] = AXISMUNDI_GEODATA_PMTILES_MIME;
	}

	return $data;
}
add_filter( 'wp_check_filetype_and_ext', 'axismundi_geodata_pmtiles_filetype', 10, 3 );

/**
 * Whether an attachment is a PMTiles map pack.
 *
 * @param int $attachment_id Attachment id.
 * @return bool
 */
function axismundi_geodata_is_pmtiles_attachment( int $attachment_id ) : bool {
	if ( AXISMUNDI_GEODATA_PMTILES_MIME === get_post_mime_type( $attachment_id ) ) {
		return true;
	}

	$file = get_attached_file( $attachment_id );

	return $file && preg_match( '/\.pmtiles$/i', $file );
}

/**
 * Parse the fixed 127-byte PMTiles v3 header (little-endian).
 *
 * @param string $path File path.
 * @return array<string,mixed>|null
 */
function axismundi_geodata_pmtiles_read_header( string $path ) : ?array {
	if ( ! is_readable( $path ) ) {
		return null;
	}
	// Ranged read of the fixed 127-byte header; WP_Filesystem has no partial read,
	// and loading a multi-MB tile archive whole just to read 127 bytes is wasteful.
	$bytes = file_get_contents( $path, false, null, 0, 127 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- ranged binary read.
	if ( false === $bytes || strlen( $bytes ) < 127 || 'PMTiles' !== substr( $bytes, 0, 7 ) || 3 !== ord( $bytes[7] ) ) {
		return null;
	}

	$u64 = static function ( int $off ) use ( $bytes ) {
		$value = unpack( 'P', substr( $bytes, $off, 8 ) );
		return $value ? $value[1] : 0;
	};
	$u8  = static function ( int $off ) use ( $bytes ) {
		return ord( $bytes[ $off ] );
	};
	$e7  = static function ( int $off ) use ( $bytes ) {
		$value = unpack( 'V', substr( $bytes, $off, 4 ) )[1];
		if ( $value >= 2147483648 ) {
			$value -= 4294967296;
		}
		return $value / 1e7;
	};

	$tile_types = array( 1 => 'mvt', 2 => 'png', 3 => 'jpeg', 4 => 'webp', 5 => 'avif' );

	return array(
		'metadata_offset'      => $u64( 24 ),
		'metadata_length'      => $u64( 32 ),
		'internal_compression' => $u8( 97 ),
		'tile_type'            => $tile_types[ $u8( 99 ) ] ?? 'unknown',
		'min_zoom'             => $u8( 100 ),
		'max_zoom'             => $u8( 101 ),
		'bounds'               => array( $e7( 102 ), $e7( 106 ), $e7( 110 ), $e7( 114 ) ), // W, S, E, N.
		'center_zoom'          => $u8( 118 ),
		'center'               => array( $e7( 119 ), $e7( 123 ) ), // lon, lat.
	);
}

/**
 * Read the PMTiles metadata JSON blob (for attribution / name).
 *
 * @param string $path   File path.
 * @param array  $header Parsed header.
 * @return array<string,mixed>
 */
function axismundi_geodata_pmtiles_read_metadata( string $path, array $header ) : array {
	$length = (int) $header['metadata_length'];
	$offset = (int) $header['metadata_offset'];
	if ( $length <= 0 || $length > 2000000 || ! is_readable( $path ) ) {
		return array();
	}

	// Ranged read of the metadata blob at its header offset.
	$blob = file_get_contents( $path, false, null, $offset, $length ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- ranged binary read.
	if ( false === $blob ) {
		return array();
	}

	if ( 2 === (int) $header['internal_compression'] && function_exists( 'gzdecode' ) ) {
		$decoded = @gzdecode( $blob ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- tolerate non-gzip blob.
		if ( false !== $decoded ) {
			$blob = $decoded;
		}
	}

	$json = json_decode( $blob, true );

	return is_array( $json ) ? $json : array();
}

/**
 * The tile schemas a map pack can carry. The schema decides which style renders:
 * only `protomaps` has a built-in style (Protomaps light); others need an uploaded
 * style later, and `raster` would use a raster source.
 *
 * @return array<string,string>
 */
function axismundi_geodata_map_pack_schemas() : array {
	return array(
		'protomaps'    => __( 'Protomaps', 'axismundi-geodata' ),
		'openmaptiles' => __( 'OpenMapTiles', 'axismundi-geodata' ),
		'custom'       => __( 'Custom vector', 'axismundi-geodata' ),
		'raster'       => __( 'Raster', 'axismundi-geodata' ),
	);
}

/**
 * Detect the tile schema from the header tile type and metadata. Done once at
 * save time, not on every render.
 *
 * @param array $header   Parsed PMTiles header.
 * @param array $metadata Parsed PMTiles metadata JSON.
 * @return string Schema slug.
 */
function axismundi_geodata_pmtiles_detect_schema( array $header, array $metadata ) : string {
	if ( in_array( $header['tile_type'], array( 'png', 'jpeg', 'webp', 'avif' ), true ) ) {
		return 'raster';
	}

	if ( false !== stripos( (string) ( $metadata['name'] ?? '' ), 'protomaps' ) ) {
		return 'protomaps';
	}

	$layer_ids = array();
	foreach ( (array) ( $metadata['vector_layers'] ?? array() ) as $layer ) {
		if ( is_array( $layer ) && isset( $layer['id'] ) ) {
			$layer_ids[] = (string) $layer['id'];
		}
	}
	if ( array_intersect( array( 'earth', 'pois', 'physical_line' ), $layer_ids ) ) {
		return 'protomaps';
	}
	if ( in_array( 'transportation', $layer_ids, true ) && in_array( 'water_name', $layer_ids, true ) ) {
		return 'openmaptiles';
	}

	return 'custom';
}

/**
 * The default style slug for a schema. Only protomaps has a built-in style today.
 *
 * @param string $schema Schema slug.
 * @return string
 */
function axismundi_geodata_pmtiles_default_style( string $schema ) : string {
	switch ( $schema ) {
		case 'protomaps':
			return 'protomaps_light';
		case 'raster':
			return 'raster';
		default:
			return '';
	}
}

/**
 * The stored (or freshly parsed) Map Pack record for an attachment.
 *
 * @param int $attachment_id Attachment id.
 * @return array<string,mixed>
 */
function axismundi_geodata_map_pack( int $attachment_id ) : array {
	$bounds = (string) get_post_meta( $attachment_id, 'ax_map_bounds', true );
	if ( '' !== $bounds ) {
		return array(
			'format'      => (string) get_post_meta( $attachment_id, 'ax_map_format', true ),
			'schema'      => (string) get_post_meta( $attachment_id, 'ax_map_schema', true ),
			'style'       => (string) get_post_meta( $attachment_id, 'ax_map_style', true ),
			'bounds'      => $bounds,
			'min_zoom'    => (int) get_post_meta( $attachment_id, 'ax_map_min_zoom', true ),
			'max_zoom'    => (int) get_post_meta( $attachment_id, 'ax_map_max_zoom', true ),
			'attribution' => (string) get_post_meta( $attachment_id, 'ax_map_attribution', true ),
		);
	}

	// Not saved yet: derive defaults from the file header / metadata.
	$file   = get_attached_file( $attachment_id );
	$header = $file ? axismundi_geodata_pmtiles_read_header( $file ) : null;
	if ( ! $header ) {
		return array(
			'format'      => '',
			'schema'      => '',
			'style'       => '',
			'bounds'      => '',
			'min_zoom'    => 0,
			'max_zoom'    => 0,
			'attribution' => '',
		);
	}

	$meta   = axismundi_geodata_pmtiles_read_metadata( $file, $header );
	$schema = axismundi_geodata_pmtiles_detect_schema( $header, $meta );

	return array(
		'format'      => $header['tile_type'],
		'schema'      => $schema,
		'style'       => axismundi_geodata_pmtiles_default_style( $schema ),
		'bounds'      => implode( ',', array_map( static function ( $n ) {
			return rtrim( rtrim( number_format( $n, 6, '.', '' ), '0' ), '.' );
		}, $header['bounds'] ) ),
		'min_zoom'    => (int) $header['min_zoom'],
		'max_zoom'    => (int) $header['max_zoom'],
		'attribution' => isset( $meta['attribution'] ) ? wp_strip_all_tags( (string) $meta['attribution'] ) : '',
	);
}

/**
 * Register the Map Pack meta box on .pmtiles attachments.
 *
 * @param WP_Post $post Attachment.
 * @return void
 */
function axismundi_geodata_map_pack_meta_box( WP_Post $post ) : void {
	if ( ! axismundi_geodata_is_pmtiles_attachment( $post->ID ) ) {
		return;
	}
	add_meta_box( 'axismundi-geodata-map-pack', __( 'Map Pack', 'axismundi-geodata' ), 'axismundi_geodata_render_map_pack_box', 'attachment', 'normal' );
}
add_action( 'add_meta_boxes_attachment', 'axismundi_geodata_map_pack_meta_box' );

/**
 * Render the Map Pack meta box.
 *
 * @param WP_Post $post Attachment.
 * @return void
 */
function axismundi_geodata_render_map_pack_box( WP_Post $post ) : void {
	wp_nonce_field( 'axismundi_geodata_map_pack', 'axismundi_geodata_map_pack_nonce' );
	$pack = axismundi_geodata_map_pack( $post->ID );

	echo '<p class="description">' . esc_html__( 'Self-hosted tile pack. Format, schema, bounds, and zoom are read from the file; correct them or set the attribution as needed. The admin preview currently renders the Protomaps schema only.', 'axismundi-geodata' ) . '</p>';

	printf(
		'<p><label>%s</label> <input type="text" value="%s" class="small-text" readonly /></p>',
		esc_html__( 'Tile format', 'axismundi-geodata' ),
		esc_attr( '' !== $pack['format'] ? $pack['format'] : '—' )
	);
	printf( '<p><label for="ax_map_schema">%s</label> <select id="ax_map_schema" name="ax_map_schema">', esc_html__( 'Schema', 'axismundi-geodata' ) );
	foreach ( axismundi_geodata_map_pack_schemas() as $slug => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $pack['schema'], $slug, false ), esc_html( $label ) );
	}
	echo '</select></p>';
	printf(
		'<p><label for="ax_map_style">%s</label> <input type="text" id="ax_map_style" name="ax_map_style" value="%s" class="regular-text" placeholder="protomaps_light" /></p>',
		esc_html__( 'Style', 'axismundi-geodata' ),
		esc_attr( $pack['style'] )
	);
	printf(
		'<p><label for="ax_map_bounds">%s</label> <input type="text" id="ax_map_bounds" name="ax_map_bounds" value="%s" class="regular-text" placeholder="west,south,east,north" /></p>',
		esc_html__( 'Bounds', 'axismundi-geodata' ),
		esc_attr( $pack['bounds'] )
	);
	printf(
		'<p><label for="ax_map_min_zoom">%s</label> <input type="number" id="ax_map_min_zoom" name="ax_map_min_zoom" value="%d" min="0" max="24" class="small-text" /> ',
		esc_html__( 'Min zoom', 'axismundi-geodata' ),
		(int) $pack['min_zoom']
	);
	printf(
		'<label for="ax_map_max_zoom">%s</label> <input type="number" id="ax_map_max_zoom" name="ax_map_max_zoom" value="%d" min="0" max="24" class="small-text" /></p>',
		esc_html__( 'Max zoom', 'axismundi-geodata' ),
		(int) $pack['max_zoom']
	);
	printf(
		'<p><label for="ax_map_attribution">%s</label><br /><input type="text" id="ax_map_attribution" name="ax_map_attribution" value="%s" class="large-text" /></p>',
		esc_html__( 'Attribution', 'axismundi-geodata' ),
		esc_attr( $pack['attribution'] )
	);
}

/**
 * Save the Map Pack fields.
 *
 * @param int $post_id Attachment id.
 * @return void
 */
function axismundi_geodata_save_map_pack( int $post_id ) : void {
	if (
		! isset( $_POST['axismundi_geodata_map_pack_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['axismundi_geodata_map_pack_nonce'] ) ), 'axismundi_geodata_map_pack' )
		|| ! current_user_can( 'edit_post', $post_id )
	) {
		return;
	}

	$format = axismundi_geodata_map_pack( $post_id )['format'];
	if ( '' !== $format ) {
		update_post_meta( $post_id, 'ax_map_format', sanitize_key( $format ) );
	}

	if ( isset( $_POST['ax_map_schema'] ) ) {
		$schema = sanitize_key( wp_unslash( $_POST['ax_map_schema'] ) );
		if ( array_key_exists( $schema, axismundi_geodata_map_pack_schemas() ) ) {
			update_post_meta( $post_id, 'ax_map_schema', $schema );
		}
	}
	if ( isset( $_POST['ax_map_style'] ) ) {
		update_post_meta( $post_id, 'ax_map_style', sanitize_text_field( wp_unslash( $_POST['ax_map_style'] ) ) );
	}

	if ( isset( $_POST['ax_map_bounds'] ) ) {
		$bounds = preg_replace( '/[^0-9.,\- ]/', '', sanitize_text_field( wp_unslash( $_POST['ax_map_bounds'] ) ) );
		update_post_meta( $post_id, 'ax_map_bounds', trim( (string) $bounds ) );
	}
	update_post_meta( $post_id, 'ax_map_min_zoom', isset( $_POST['ax_map_min_zoom'] ) ? max( 0, min( 24, absint( $_POST['ax_map_min_zoom'] ) ) ) : 0 );
	update_post_meta( $post_id, 'ax_map_max_zoom', isset( $_POST['ax_map_max_zoom'] ) ? max( 0, min( 24, absint( $_POST['ax_map_max_zoom'] ) ) ) : 0 );
	if ( isset( $_POST['ax_map_attribution'] ) ) {
		update_post_meta( $post_id, 'ax_map_attribution', sanitize_text_field( wp_unslash( $_POST['ax_map_attribution'] ) ) );
	}
}
add_action( 'edit_attachment', 'axismundi_geodata_save_map_pack' );
