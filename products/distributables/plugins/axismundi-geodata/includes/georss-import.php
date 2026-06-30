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
			'properties' => axismundi_geodata_georss_entry_properties( $entry ),
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

const AXISMUNDI_GEODATA_MRSS_NS   = 'http://search.yahoo.com/mrss/';
const AXISMUNDI_GEODATA_DC_NS     = 'http://purl.org/dc/elements/1.1/';
const AXISMUNDI_GEODATA_RSSCNT_NS = 'http://purl.org/rss/1.0/modules/content/';

/**
 * Normalise one feed entry into the popup property set the Map block consumes.
 *
 * Everything is plain text or a validated URL except byline_html, which is the
 * feed's lead paragraph passed through a strict wp_kses allowlist so the source
 * "X posted a photo:" line keeps its author link without becoming an XSS vector.
 *
 * @param SimpleXMLElement $entry Feed item/entry.
 * @return array<string,mixed>
 */
function axismundi_geodata_georss_entry_properties( SimpleXMLElement $entry ) : array {
	$content = axismundi_geodata_georss_entry_content( $entry );
	$split   = axismundi_geodata_georss_split_content( $content );
	$author  = axismundi_geodata_georss_entry_author( $entry );

	return array(
		'type'        => 'georss',
		'title'       => axismundi_geodata_georss_entry_title( $entry ),
		'url'         => axismundi_geodata_georss_entry_link( $entry ),
		'thumbnail'   => axismundi_geodata_georss_entry_image( $entry, $content ),
		'author_name' => $author['name'],
		'author_url'  => $author['url'],
		'byline_html' => $split['byline'],
		'published'   => axismundi_geodata_georss_entry_date( $entry ),
		'excerpt'     => $split['excerpt'],
	);
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
 * Best-effort permalink: RSS <link>text, else Atom rel="alternate" / first link.
 *
 * @param SimpleXMLElement $entry Feed item/entry.
 * @return string
 */
function axismundi_geodata_georss_entry_link( SimpleXMLElement $entry ) : string {
	if ( ! isset( $entry->link ) ) {
		return '';
	}

	// RSS carries the URL as element text.
	$text = trim( (string) $entry->link );
	if ( '' !== $text ) {
		return esc_url_raw( $text );
	}

	// Atom: prefer rel="alternate" (or no rel); never the enclosure.
	$fallback = '';
	foreach ( $entry->link as $link ) {
		$href = (string) $link['href'];
		if ( '' === $href ) {
			continue;
		}
		$rel = (string) $link['rel'];
		if ( 'alternate' === $rel || '' === $rel ) {
			return esc_url_raw( $href );
		}
		if ( '' === $fallback && 'enclosure' !== $rel ) {
			$fallback = $href;
		}
	}

	return '' !== $fallback ? esc_url_raw( $fallback ) : '';
}

/**
 * The entry's content markup: Atom content/summary, RSS content:encoded /
 * description.
 *
 * @param SimpleXMLElement $entry Feed item/entry.
 * @return string
 */
function axismundi_geodata_georss_entry_content( SimpleXMLElement $entry ) : string {
	if ( isset( $entry->content ) && '' !== trim( (string) $entry->content ) ) {
		return (string) $entry->content;
	}
	$encoded = $entry->children( AXISMUNDI_GEODATA_RSSCNT_NS );
	if ( isset( $encoded->encoded ) && '' !== trim( (string) $encoded->encoded ) ) {
		return (string) $encoded->encoded;
	}
	if ( isset( $entry->summary ) && '' !== trim( (string) $entry->summary ) ) {
		return (string) $entry->summary;
	}
	if ( isset( $entry->description ) ) {
		return (string) $entry->description;
	}
	return '';
}

/**
 * Split content into a sanitised lead-paragraph byline and a plain-text excerpt.
 *
 * A lead paragraph that carries a link but no image is treated as a byline
 * (e.g. Flickr's "<a>user</a> posted a photo:") and dropped from the excerpt.
 *
 * @param string $html Content markup.
 * @return array{byline:string,excerpt:string}
 */
function axismundi_geodata_georss_split_content( string $html ) : array {
	$byline = '';
	$rest   = $html;

	if ( preg_match( '#<p\b[^>]*>(.*?)</p>#is', $html, $m ) ) {
		$first = $m[1];
		if ( false !== stripos( $first, '<a' ) && false === stripos( $first, '<img' ) ) {
			$byline = axismundi_geodata_georss_sanitize_byline( $first );
			$rest   = preg_replace( '#<p\b[^>]*>.*?</p>#is', '', $html, 1 );
		}
	}

	$excerpt = trim( preg_replace( '/\s+/', ' ', (string) wp_strip_all_tags( (string) $rest ) ) );

	return array(
		'byline'  => $byline,
		'excerpt' => $excerpt,
	);
}

/**
 * Sanitise a byline fragment to a strict inline allowlist (links + emphasis).
 *
 * @param string $html Byline markup.
 * @return string
 */
function axismundi_geodata_georss_sanitize_byline( string $html ) : string {
	$allowed = array(
		'a'      => array(
			'href'  => true,
			'title' => true,
		),
		'strong' => array(),
		'em'     => array(),
		'b'      => array(),
		'i'      => array(),
		'span'   => array(),
	);

	return trim( wp_kses( $html, $allowed ) );
}

/**
 * Author name and profile URL: Atom author/name + uri, else RSS dc:creator.
 *
 * @param SimpleXMLElement $entry Feed item/entry.
 * @return array{name:string,url:string}
 */
function axismundi_geodata_georss_entry_author( SimpleXMLElement $entry ) : array {
	$name = '';
	$url  = '';

	if ( isset( $entry->author ) ) {
		if ( isset( $entry->author->name ) ) {
			$name = trim( (string) $entry->author->name );
		}
		if ( isset( $entry->author->uri ) ) {
			$url = esc_url_raw( trim( (string) $entry->author->uri ) );
		}
	}

	if ( '' === $name ) {
		$dc = $entry->children( AXISMUNDI_GEODATA_DC_NS );
		if ( isset( $dc->creator ) ) {
			$name = trim( (string) $dc->creator );
		}
	}

	return array(
		'name' => $name,
		'url'  => $url,
	);
}

/**
 * Representative image URL: Media RSS, then an image enclosure, then the first
 * inline <img> in the content.
 *
 * @param SimpleXMLElement $entry   Feed item/entry.
 * @param string           $content Content markup.
 * @return string
 */
function axismundi_geodata_georss_entry_image( SimpleXMLElement $entry, string $content ) : string {
	$media = $entry->children( AXISMUNDI_GEODATA_MRSS_NS );
	foreach ( array( 'content', 'thumbnail' ) as $tag ) {
		if ( isset( $media->$tag ) ) {
			foreach ( $media->$tag as $node ) {
				$url = esc_url_raw( (string) $node['url'] );
				if ( '' !== $url ) {
					return $url;
				}
			}
		}
	}

	foreach ( $entry->link as $link ) {
		if ( 'enclosure' === (string) $link['rel'] && 0 === stripos( (string) $link['type'], 'image/' ) ) {
			$url = esc_url_raw( (string) $link['href'] );
			if ( '' !== $url ) {
				return $url;
			}
		}
	}

	if ( isset( $entry->enclosure ) && 0 === stripos( (string) $entry->enclosure['type'], 'image/' ) ) {
		$url = esc_url_raw( (string) $entry->enclosure['url'] );
		if ( '' !== $url ) {
			return $url;
		}
	}

	if ( preg_match( '#<img[^>]+src=["\']([^"\']+)["\']#i', $content, $m ) ) {
		return esc_url_raw( $m[1] );
	}

	return '';
}

/**
 * Localised published date string: Atom published/updated, RSS pubDate/dc:date.
 *
 * @param SimpleXMLElement $entry Feed item/entry.
 * @return string
 */
function axismundi_geodata_georss_entry_date( SimpleXMLElement $entry ) : string {
	$raw = '';
	foreach ( array( 'published', 'updated', 'pubDate' ) as $tag ) {
		if ( isset( $entry->$tag ) && '' !== trim( (string) $entry->$tag ) ) {
			$raw = (string) $entry->$tag;
			break;
		}
	}
	if ( '' === $raw ) {
		$dc = $entry->children( AXISMUNDI_GEODATA_DC_NS );
		if ( isset( $dc->date ) ) {
			$raw = (string) $dc->date;
		}
	}

	$timestamp = $raw ? strtotime( $raw ) : false;
	return false !== $timestamp ? date_i18n( (string) get_option( 'date_format' ), $timestamp ) : '';
}
