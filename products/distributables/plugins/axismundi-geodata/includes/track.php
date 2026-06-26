<?php
/**
 * GPS track attachments — GPX / KML route files registered as WordPress media.
 *
 * The same pattern as PMTiles map packs: allow the upload, read a summary out of
 * the file once, and promote it to attachment custom fields (ax_track_*) so the
 * attachment page and future map/route renderers read postmeta instead of
 * reparsing the file. A track is route data (many points over time), distinct
 * from a geotag (a named place) — front-end rendering belongs to a later Map
 * plugin; here we only recognise and summarise the file.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_GEODATA_GPX_MIME = 'application/gpx+xml';
const AXISMUNDI_GEODATA_KML_MIME = 'application/vnd.google-earth.kml+xml';

/**
 * Allow .gpx / .kml uploads (XML data files; parsed without network entities).
 *
 * @param array $mimes Allowed mime types.
 * @return array
 */
function axismundi_geodata_track_upload_mimes( array $mimes ) : array {
	$mimes['gpx'] = AXISMUNDI_GEODATA_GPX_MIME;
	$mimes['kml'] = AXISMUNDI_GEODATA_KML_MIME;

	return $mimes;
}
add_filter( 'upload_mimes', 'axismundi_geodata_track_upload_mimes' );

/**
 * Map .gpx / .kml filenames to their ext/type so WordPress accepts the upload.
 *
 * @param array  $data     ext/type/proper_filename.
 * @param string $file     Full path.
 * @param string $filename Filename.
 * @return array
 */
function axismundi_geodata_track_filetype( $data, $file, $filename ) {
	if ( preg_match( '/\.gpx$/i', (string) $filename ) ) {
		$data['ext']  = 'gpx';
		$data['type'] = AXISMUNDI_GEODATA_GPX_MIME;
	} elseif ( preg_match( '/\.kml$/i', (string) $filename ) ) {
		$data['ext']  = 'kml';
		$data['type'] = AXISMUNDI_GEODATA_KML_MIME;
	}

	return $data;
}
add_filter( 'wp_check_filetype_and_ext', 'axismundi_geodata_track_filetype', 10, 3 );

/**
 * The track format for an attachment ('gpx' / 'kml'), or '' if it is not a track.
 *
 * @param int $attachment_id Attachment id.
 * @return string
 */
function axismundi_geodata_track_format( int $attachment_id ) : string {
	$mime = get_post_mime_type( $attachment_id );
	if ( AXISMUNDI_GEODATA_GPX_MIME === $mime ) {
		return 'gpx';
	}
	if ( AXISMUNDI_GEODATA_KML_MIME === $mime ) {
		return 'kml';
	}

	$file = get_attached_file( $attachment_id );
	if ( $file && preg_match( '/\.(gpx|kml)$/i', $file, $m ) ) {
		return strtolower( $m[1] );
	}

	return '';
}

/**
 * Whether an attachment is a GPX / KML track.
 *
 * @param int $attachment_id Attachment id.
 * @return bool
 */
function axismundi_geodata_is_track_attachment( int $attachment_id ) : bool {
	return '' !== axismundi_geodata_track_format( $attachment_id );
}

/**
 * Great-circle distance between two points, in metres.
 *
 * @param float $lat1 Latitude 1.
 * @param float $lon1 Longitude 1.
 * @param float $lat2 Latitude 2.
 * @param float $lon2 Longitude 2.
 * @return float
 */
function axismundi_geodata_haversine_m( float $lat1, float $lon1, float $lat2, float $lon2 ) : float {
	$radius = 6371000.0;
	$d_lat  = deg2rad( $lat2 - $lat1 );
	$d_lon  = deg2rad( $lon2 - $lon1 );
	$a      = sin( $d_lat / 2 ) ** 2 + cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) * sin( $d_lon / 2 ) ** 2;

	return $radius * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
}

/**
 * Summarise an ordered list of [lat, lon, ele|null, time|''] points.
 *
 * @param string $name     Track name.
 * @param array  $points   Points.
 * @param int    $segments Segment count.
 * @param string $format   gpx / kml.
 * @param string $fallback_time A document-level timestamp, used when points have none.
 * @return array<string,mixed>
 */
function axismundi_geodata_track_summary( string $name, array $points, int $segments, string $format, string $fallback_time = '' ) : array {
	$count = count( $points );
	if ( 0 === $count ) {
		return array(
			'format'     => $format,
			'name'       => $name,
			'bounds'     => '',
			'distance_m' => 0,
			'points'     => 0,
			'segments'   => $segments,
			'start_time' => '',
			'end_time'   => '',
			'ele_min'    => null,
			'ele_max'    => null,
		);
	}

	$min_lat = 90.0;
	$max_lat = -90.0;
	$min_lon = 180.0;
	$max_lon = -180.0;
	$min_ele = null;
	$max_ele = null;
	$start   = '';
	$end     = '';
	$dist    = 0.0;
	$prev    = null;

	foreach ( $points as $point ) {
		list( $lat, $lon, $ele, $time ) = $point;
		$min_lat = min( $min_lat, $lat );
		$max_lat = max( $max_lat, $lat );
		$min_lon = min( $min_lon, $lon );
		$max_lon = max( $max_lon, $lon );
		if ( null !== $ele ) {
			$min_ele = null === $min_ele ? $ele : min( $min_ele, $ele );
			$max_ele = null === $max_ele ? $ele : max( $max_ele, $ele );
		}
		if ( null !== $prev ) {
			$dist += axismundi_geodata_haversine_m( $prev[0], $prev[1], $lat, $lon );
		}
		$prev = array( $lat, $lon );
		if ( '' !== $time ) {
			if ( '' === $start ) {
				$start = $time;
			}
			$end = $time;
		}
	}

	if ( '' === $start && '' !== $fallback_time ) {
		$start = $fallback_time;
	}

	return array(
		'format'     => $format,
		'name'       => $name,
		'bounds'     => implode( ',', array( round( $min_lon, 6 ), round( $min_lat, 6 ), round( $max_lon, 6 ), round( $max_lat, 6 ) ) ),
		'distance_m' => (int) round( $dist ),
		'points'     => $count,
		'segments'   => max( $segments, 1 ),
		'start_time' => $start,
		'end_time'   => $end,
		'ele_min'    => null === $min_ele ? null : round( $min_ele, 1 ),
		'ele_max'    => null === $max_ele ? null : round( $max_ele, 1 ),
	);
}

/**
 * Load an XML track file safely (no network entities, capped size).
 *
 * @param string $path File path.
 * @return SimpleXMLElement|null
 */
function axismundi_geodata_track_load_xml( string $path ) : ?SimpleXMLElement {
	if ( ! is_readable( $path ) || filesize( $path ) > 30000000 ) {
		return null;
	}
	$data = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading a small XML track file to parse.
	if ( false === $data || '' === $data ) {
		return null;
	}

	$previous = libxml_use_internal_errors( true );
	// LIBXML_NONET blocks network access for any entities; do NOT pass LIBXML_NOENT,
	// so external entities are never expanded (XXE-safe for user uploads).
	$xml = simplexml_load_string( $data, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	return false === $xml ? null : $xml;
}

/**
 * Parse a GPX file into a track summary.
 *
 * @param SimpleXMLElement $xml GPX root.
 * @return array<string,mixed>
 */
function axismundi_geodata_parse_gpx( SimpleXMLElement $xml ) : array {
	$name = '';
	if ( isset( $xml->metadata->name ) && '' !== (string) $xml->metadata->name ) {
		$name = (string) $xml->metadata->name;
	} elseif ( isset( $xml->trk->name ) ) {
		$name = (string) $xml->trk->name;
	}

	$points   = array();
	$segments = 0;
	foreach ( $xml->trk as $trk ) {
		foreach ( $trk->trkseg as $seg ) {
			++$segments;
			foreach ( $seg->trkpt as $pt ) {
				$points[] = array(
					(float) $pt['lat'],
					(float) $pt['lon'],
					isset( $pt->ele ) && '' !== (string) $pt->ele ? (float) $pt->ele : null,
					isset( $pt->time ) ? (string) $pt->time : '',
				);
			}
		}
	}

	return axismundi_geodata_track_summary( sanitize_text_field( $name ), $points, $segments, 'gpx' );
}

/**
 * Parse a KML file (LineString tracks) into a track summary.
 *
 * @param SimpleXMLElement $xml KML root.
 * @return array<string,mixed>
 */
function axismundi_geodata_parse_kml( SimpleXMLElement $xml ) : array {
	$xml->registerXPathNamespace( 'k', 'http://www.opengis.net/kml/2.2' );

	$name        = '';
	$name_nodes  = $xml->xpath( '//k:Document/k:name' );
	if ( ! empty( $name_nodes ) ) {
		$name = (string) $name_nodes[0];
	}
	$time_nodes = $xml->xpath( '//k:Document/k:TimeStamp/k:when' );
	$fallback   = ! empty( $time_nodes ) ? (string) $time_nodes[0] : '';

	$points   = array();
	$segments = 0;
	foreach ( (array) $xml->xpath( '//k:LineString/k:coordinates' ) as $coords ) {
		++$segments;
		foreach ( preg_split( '/\s+/', trim( (string) $coords ) ) as $tuple ) {
			if ( '' === $tuple ) {
				continue;
			}
			$parts = explode( ',', $tuple );
			if ( count( $parts ) >= 2 ) {
				$points[] = array(
					(float) $parts[1],
					(float) $parts[0],
					isset( $parts[2] ) && '' !== $parts[2] ? (float) $parts[2] : null,
					'',
				);
			}
		}
	}

	return axismundi_geodata_track_summary( sanitize_text_field( $name ), $points, $segments, 'kml', $fallback );
}

/**
 * Parse a track attachment directly from its file.
 *
 * @param int $attachment_id Attachment id.
 * @return array<string,mixed>|null
 */
function axismundi_geodata_parse_track_file( int $attachment_id ) : ?array {
	$format = axismundi_geodata_track_format( $attachment_id );
	$file   = get_attached_file( $attachment_id );
	if ( '' === $format || ! $file ) {
		return null;
	}
	$xml = axismundi_geodata_track_load_xml( $file );
	if ( ! $xml ) {
		return null;
	}

	return 'kml' === $format ? axismundi_geodata_parse_kml( $xml ) : axismundi_geodata_parse_gpx( $xml );
}

/**
 * The stored (or freshly parsed) track record for an attachment.
 *
 * @param int $attachment_id Attachment id.
 * @return array<string,mixed>
 */
function axismundi_geodata_track( int $attachment_id ) : array {
	if ( '' !== (string) get_post_meta( $attachment_id, 'ax_track_bounds', true ) ) {
		return array(
			'format'     => (string) get_post_meta( $attachment_id, 'ax_track_format', true ),
			'name'       => (string) get_post_meta( $attachment_id, 'ax_track_name', true ),
			'bounds'     => (string) get_post_meta( $attachment_id, 'ax_track_bounds', true ),
			'distance_m' => (int) get_post_meta( $attachment_id, 'ax_track_distance_m', true ),
			'points'     => (int) get_post_meta( $attachment_id, 'ax_track_points', true ),
			'segments'   => (int) get_post_meta( $attachment_id, 'ax_track_segments', true ),
			'start_time' => (string) get_post_meta( $attachment_id, 'ax_track_start_time', true ),
			'end_time'   => (string) get_post_meta( $attachment_id, 'ax_track_end_time', true ),
			'ele_min'    => get_post_meta( $attachment_id, 'ax_track_ele_min', true ),
			'ele_max'    => get_post_meta( $attachment_id, 'ax_track_ele_max', true ),
		);
	}

	$parsed = axismundi_geodata_parse_track_file( $attachment_id );

	return $parsed ?? array(
		'format'     => axismundi_geodata_track_format( $attachment_id ),
		'name'       => '',
		'bounds'     => '',
		'distance_m' => 0,
		'points'     => 0,
		'segments'   => 0,
		'start_time' => '',
		'end_time'   => '',
		'ele_min'    => null,
		'ele_max'    => null,
	);
}

/**
 * Promote parsed track facts to attachment custom fields.
 *
 * @param int $attachment_id Attachment id.
 * @return bool
 */
function axismundi_geodata_persist_track_meta( int $attachment_id ) : bool {
	if ( ! axismundi_geodata_is_track_attachment( $attachment_id ) ) {
		return false;
	}
	$track = axismundi_geodata_parse_track_file( $attachment_id );
	if ( ! $track ) {
		return false;
	}

	$fields = array(
		'ax_track_format'     => sanitize_key( (string) $track['format'] ),
		'ax_track_name'       => sanitize_text_field( (string) $track['name'] ),
		'ax_track_bounds'     => (string) $track['bounds'],
		'ax_track_distance_m' => (int) $track['distance_m'],
		'ax_track_points'     => (int) $track['points'],
		'ax_track_segments'   => (int) $track['segments'],
		'ax_track_start_time' => sanitize_text_field( (string) $track['start_time'] ),
		'ax_track_end_time'   => sanitize_text_field( (string) $track['end_time'] ),
	);
	if ( null !== $track['ele_min'] ) {
		$fields['ax_track_ele_min'] = (float) $track['ele_min'];
	}
	if ( null !== $track['ele_max'] ) {
		$fields['ax_track_ele_max'] = (float) $track['ele_max'];
	}

	foreach ( $fields as $key => $value ) {
		update_post_meta( $attachment_id, $key, $value );
	}

	return true;
}

/**
 * Persist track facts when a GPX / KML attachment is created.
 *
 * @param int $attachment_id Attachment id.
 * @return void
 */
function axismundi_geodata_persist_track_on_add( int $attachment_id ) : void {
	axismundi_geodata_persist_track_meta( $attachment_id );
}
add_action( 'add_attachment', 'axismundi_geodata_persist_track_on_add' );

/**
 * Persist track facts during the attachment metadata generation path.
 *
 * @param array $metadata      Attachment metadata.
 * @param int   $attachment_id Attachment id.
 * @return array
 */
function axismundi_geodata_persist_track_on_metadata( array $metadata, int $attachment_id ) : array {
	axismundi_geodata_persist_track_meta( $attachment_id );

	return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'axismundi_geodata_persist_track_on_metadata', 20, 2 );

/**
 * Register the Track meta box on GPX / KML attachments.
 *
 * @param WP_Post $post Attachment.
 * @return void
 */
function axismundi_geodata_track_meta_box( WP_Post $post ) : void {
	if ( axismundi_geodata_is_track_attachment( $post->ID ) ) {
		add_meta_box( 'axismundi-geodata-track', __( 'GPS Track', 'axismundi-geodata' ), 'axismundi_geodata_render_track_box', 'attachment', 'normal' );
	}
}
add_action( 'add_meta_boxes_attachment', 'axismundi_geodata_track_meta_box' );

/**
 * Render the Track meta box (a read-only summary; facts come from the file).
 *
 * @param WP_Post $post Attachment.
 * @return void
 */
function axismundi_geodata_render_track_box( WP_Post $post ) : void {
	$track = axismundi_geodata_track( $post->ID );

	$km   = $track['distance_m'] > 0 ? number_format( $track['distance_m'] / 1000, 2 ) . ' km' : '—';
	$ele  = ( null !== $track['ele_min'] && '' !== (string) $track['ele_min'] )
		? sprintf( '%s – %s m', $track['ele_min'], $track['ele_max'] )
		: '—';
	$span = '' !== $track['start_time']
		? trim( $track['start_time'] . ( '' !== $track['end_time'] ? ' → ' . $track['end_time'] : '' ) )
		: '—';

	$rows = array(
		__( 'Format', 'axismundi-geodata' )    => '' !== $track['format'] ? strtoupper( $track['format'] ) : '—',
		__( 'Name', 'axismundi-geodata' )      => '' !== $track['name'] ? $track['name'] : '—',
		__( 'Distance', 'axismundi-geodata' )  => $km,
		__( 'Points', 'axismundi-geodata' )    => (string) $track['points'] . ' / ' . (string) $track['segments'] . ' ' . __( 'segment(s)', 'axismundi-geodata' ),
		__( 'Elevation', 'axismundi-geodata' ) => $ele,
		__( 'Time', 'axismundi-geodata' )      => $span,
		__( 'Bounds', 'axismundi-geodata' )    => '' !== $track['bounds'] ? $track['bounds'] : '—',
	);

	echo '<p class="description">' . esc_html__( 'Route summary read from the file. Stored as attachment custom fields (ax_track_*).', 'axismundi-geodata' ) . '</p>';
	echo '<table class="widefat striped" style="max-width:520px"><tbody>';
	foreach ( $rows as $label => $value ) {
		printf( '<tr><th scope="row" style="width:120px">%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( $value ) );
	}
	echo '</tbody></table>';
}
