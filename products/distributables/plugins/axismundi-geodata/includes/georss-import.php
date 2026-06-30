<?php
/**
 * GeoRSS import adapter: fetch an external GeoRSS / W3C Geo feed and normalise
 * it into the same GeoJSON FeatureCollection shape the REST endpoints emit, so
 * the Map block can render it through its existing renderers.
 *
 * Responsibility split: this file owns the network fetch, caching, XML parsing
 * and GeoJSON conversion (Geodata). The Map plugin owns rendering. There is no
 * public URL-proxy endpoint — the Map block calls the adapter at render time and
 * inlines the result, so this never becomes an open SSRF proxy.
 *
 * Supported geometry (GeoRSS Simple + W3C Basic Geo):
 *   georss:point   -> Point          georss:line    -> LineString
 *   georss:polygon -> Polygon        georss:box     -> Polygon (rectangle)
 *   geo:lat/geo:long (W3C)           -> Point
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_GEODATA_GEORSS_NS = 'http://www.georss.org/georss';
const AXISMUNDI_GEODATA_W3CGEO_NS = 'http://www.w3.org/2003/01/geo/wgs84_pos#';

const AXISMUNDI_GEODATA_GEORSS_MAX_FEATURES = 100;
const AXISMUNDI_GEODATA_GEORSS_MAX_BYTES    = 1048576; // 1 MB.
const AXISMUNDI_GEODATA_GEORSS_TIMEOUT      = 5;
const AXISMUNDI_GEODATA_GEORSS_CACHE_OK     = 900; // 15 minutes.
const AXISMUNDI_GEODATA_GEORSS_CACHE_FAIL   = 120; // 2 minutes.

/**
 * Fetch an external feed and convert it to a GeoJSON FeatureCollection.
 *
 * @param string $url Feed URL.
 * @return array{type:string,features:array<int,array<string,mixed>>} FeatureCollection (features may be empty).
 */
function axismundi_geodata_georss_geojson( string $url ) : array {
	$empty = array(
		'type'     => 'FeatureCollection',
		'features' => array(),
	);

	$xml = axismundi_geodata_fetch_georss( $url );
	if ( '' === $xml ) {
		return $empty;
	}

	return axismundi_geodata_georss_to_geojson( $xml );
}

/**
 * Fetch a GeoRSS feed body over HTTP(S) with SSRF and resource guards, cached.
 *
 * @param string $url Feed URL.
 * @return string The response body, or '' on any failure.
 */
function axismundi_geodata_fetch_georss( string $url ) : string {
	$url = trim( $url );

	// HTTP(S) only, and reject hosts that resolve to private/reserved ranges.
	$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || ! wp_http_validate_url( $url ) ) {
		return '';
	}

	$cache_key = 'axgeo_georss_' . md5( $url );
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return is_string( $cached ) ? $cached : '';
	}

	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => AXISMUNDI_GEODATA_GEORSS_TIMEOUT,
			'redirection'         => 2,
			'limit_response_size' => AXISMUNDI_GEODATA_GEORSS_MAX_BYTES,
			'headers'             => array( 'Accept' => 'application/rss+xml, application/atom+xml, application/xml;q=0.9, */*;q=0.5' ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		set_transient( $cache_key, '', AXISMUNDI_GEODATA_GEORSS_CACHE_FAIL );
		return '';
	}

	// Accept declared XML/RSS/Atom content types; tolerate a missing type.
	$content_type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
	if ( '' !== $content_type && ! preg_match( '#(?:xml|rss|atom)#', $content_type ) ) {
		set_transient( $cache_key, '', AXISMUNDI_GEODATA_GEORSS_CACHE_FAIL );
		return '';
	}

	$body = (string) wp_remote_retrieve_body( $response );
	if ( '' === $body || strlen( $body ) > AXISMUNDI_GEODATA_GEORSS_MAX_BYTES ) {
		set_transient( $cache_key, '', AXISMUNDI_GEODATA_GEORSS_CACHE_FAIL );
		return '';
	}

	set_transient( $cache_key, $body, AXISMUNDI_GEODATA_GEORSS_CACHE_OK );
	return $body;
}

/**
 * Parse a GeoRSS / W3C Geo XML document into a GeoJSON FeatureCollection.
 *
 * Pure function: no network. External entity loading is disabled (LIBXML_NONET)
 * to prevent XXE; CDATA is folded into text nodes.
 *
 * @param string $xml Feed XML.
 * @return array{type:string,features:array<int,array<string,mixed>>}
 */
function axismundi_geodata_georss_to_geojson( string $xml ) : array {
	$collection = array(
		'type'     => 'FeatureCollection',
		'features' => array(),
	);

	if ( '' === trim( $xml ) ) {
		return $collection;
	}

	$previous = libxml_use_internal_errors( true );
	$doc      = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( false === $doc ) {
		return $collection;
	}

	// RSS 2.0 <channel><item>, then Atom <entry>.
	$entries = array();
	if ( isset( $doc->channel ) ) {
		foreach ( $doc->channel->item as $item ) {
			$entries[] = $item;
		}
	}
	foreach ( $doc->entry as $entry ) {
		$entries[] = $entry;
	}

	foreach ( $entries as $entry ) {
		if ( count( $collection['features'] ) >= AXISMUNDI_GEODATA_GEORSS_MAX_FEATURES ) {
			break;
		}

		$geometry = axismundi_geodata_georss_entry_geometry( $entry );
		if ( null === $geometry ) {
			continue;
		}

		$collection['features'][] = array(
			'type'       => 'Feature',
			'geometry'   => $geometry,
			'properties' => array(
				'type'  => 'georss',
				'title' => axismundi_geodata_georss_entry_title( $entry ),
				'url'   => axismundi_geodata_georss_entry_link( $entry ),
			),
		);
	}

	return $collection;
}

/**
 * Extract a single GeoJSON geometry from one feed entry, or null if unlocated.
 *
 * @param SimpleXMLElement $entry Feed item/entry.
 * @return array<string,mixed>|null
 */
function axismundi_geodata_georss_entry_geometry( SimpleXMLElement $entry ) : ?array {
	$georss = $entry->children( AXISMUNDI_GEODATA_GEORSS_NS );

	if ( isset( $georss->point ) ) {
		$ring = axismundi_geodata_georss_positions( (string) $georss->point );
		if ( ! empty( $ring ) ) {
			return array( 'type' => 'Point', 'coordinates' => $ring[0] );
		}
	}

	if ( isset( $georss->line ) ) {
		$ring = axismundi_geodata_georss_positions( (string) $georss->line );
		if ( count( $ring ) >= 2 ) {
			return array( 'type' => 'LineString', 'coordinates' => $ring );
		}
	}

	if ( isset( $georss->polygon ) ) {
		$ring = axismundi_geodata_georss_positions( (string) $georss->polygon );
		if ( count( $ring ) >= 3 ) {
			return array( 'type' => 'Polygon', 'coordinates' => array( axismundi_geodata_close_ring( $ring ) ) );
		}
	}

	if ( isset( $georss->box ) ) {
		$ring = axismundi_geodata_georss_box_ring( (string) $georss->box );
		if ( ! empty( $ring ) ) {
			return array( 'type' => 'Polygon', 'coordinates' => array( $ring ) );
		}
	}

	// W3C Basic Geo: <geo:lat>, <geo:long>.
	$w3c = $entry->children( AXISMUNDI_GEODATA_W3CGEO_NS );
	if ( isset( $w3c->lat, $w3c->long ) ) {
		$position = axismundi_geodata_coords_to_geojson_position( (string) $w3c->lat, (string) $w3c->long );
		if ( ! empty( $position ) ) {
			return array( 'type' => 'Point', 'coordinates' => $position );
		}
	}

	return null;
}

/**
 * Parse GeoRSS "lat lng lat lng …" text into GeoJSON [lng, lat] positions.
 *
 * @param string $text Whitespace-separated latitude/longitude pairs.
 * @return array<int,array<int,float>>
 */
function axismundi_geodata_georss_positions( string $text ) : array {
	$tokens = preg_split( '/\s+/', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $tokens ) || count( $tokens ) < 2 ) {
		return array();
	}

	$positions = array();
	$pairs      = (int) floor( count( $tokens ) / 2 );
	for ( $i = 0; $i < $pairs; $i++ ) {
		$lat = $tokens[ $i * 2 ];
		$lng = $tokens[ $i * 2 + 1 ];
		$position = axismundi_geodata_coords_to_geojson_position( $lat, $lng );
		if ( empty( $position ) ) {
			return array();
		}
		$positions[] = $position;
	}

	return $positions;
}

/**
 * Convert a GeoRSS box ("S W N E") into a closed GeoJSON polygon ring.
 *
 * @param string $text Box text.
 * @return array<int,array<int,float>> Closed ring, or [] when incomplete.
 */
function axismundi_geodata_georss_box_ring( string $text ) : array {
	$tokens = preg_split( '/\s+/', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $tokens ) || 4 !== count( $tokens ) || 4 !== count( array_filter( $tokens, 'is_numeric' ) ) ) {
		return array();
	}

	list( $south, $west, $north, $east ) = array_map( 'floatval', $tokens );

	return array(
		array( $west, $south ),
		array( $east, $south ),
		array( $east, $north ),
		array( $west, $north ),
		array( $west, $south ),
	);
}

/**
 * Ensure a polygon ring is closed (first position repeated at the end).
 *
 * @param array<int,array<int,float>> $ring Ring positions.
 * @return array<int,array<int,float>>
 */
function axismundi_geodata_close_ring( array $ring ) : array {
	$first = reset( $ring );
	$last  = end( $ring );
	if ( $first !== $last ) {
		$ring[] = $first;
	}

	return $ring;
}

/**
 * Best-effort title for a feed entry (RSS or Atom).
 *
 * @param SimpleXMLElement $entry Feed item/entry.
 * @return string
 */
function axismundi_geodata_georss_entry_title( SimpleXMLElement $entry ) : string {
	return isset( $entry->title ) ? trim( (string) $entry->title ) : '';
}

/**
 * Best-effort permalink for a feed entry (RSS <link>text or Atom <link href>).
 *
 * @param SimpleXMLElement $entry Feed item/entry.
 * @return string
 */
function axismundi_geodata_georss_entry_link( SimpleXMLElement $entry ) : string {
	if ( ! isset( $entry->link ) ) {
		return '';
	}

	// RSS carries the URL as element text; Atom carries it in an href attribute.
	$text = trim( (string) $entry->link );
	if ( '' !== $text ) {
		return esc_url_raw( $text );
	}

	$href = (string) $entry->link['href'];
	return '' !== $href ? esc_url_raw( $href ) : '';
}
