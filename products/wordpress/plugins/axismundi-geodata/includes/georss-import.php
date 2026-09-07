<?php
/**
 * GeoRSS import adapter: read an external GeoRSS / W3C Geo feed and normalise it
 * into the GeoJSON FeatureCollection shape the REST endpoints emit, so the Map
 * block can render it through its existing renderers.
 *
 * Generic feed work — safe HTTP (wp_safe_remote_request via WP_SimplePie_File),
 * transient caching shared with core/rss, and RSS/Atom field normalisation — is
 * delegated to WordPress core's fetch_feed() / SimplePie. This file only adds
 * what core does not have: reading the GeoRSS Simple geometry tags and turning
 * them into GeoJSON. Responsibility split is unchanged: Geodata owns the data,
 * the Map plugin owns rendering, and there is no public URL-proxy endpoint — the
 * Map block calls the adapter at render time and inlines the result.
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

// A render-performance ceiling, not a security limit; SimplePie/fetch_feed owns
// the network trust model (the same one core/rss uses).
const AXISMUNDI_GEODATA_GEORSS_MAX_FEATURES = 100;

/**
 * Fetch an external feed (via core's fetch_feed) and convert located entries to
 * a GeoJSON FeatureCollection.
 *
 * @param string $url Feed URL.
 * @return array{type:string,features:array<int,array<string,mixed>>} FeatureCollection (features may be empty).
 */
function axismundi_geodata_georss_geojson( string $url ) : array {
	$collection = array(
		'type'     => 'FeatureCollection',
		'features' => array(),
	);

	$url    = trim( $url );
	$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		return $collection;
	}

	if ( ! function_exists( 'fetch_feed' ) ) {
		require_once ABSPATH . WPINC . '/feed.php';
	}

	$feed = fetch_feed( $url );
	if ( is_wp_error( $feed ) ) {
		return $collection;
	}

	foreach ( $feed->get_items( 0, AXISMUNDI_GEODATA_GEORSS_MAX_FEATURES ) as $item ) {
		$geometry = axismundi_geodata_georss_item_geometry( $item );
		if ( null === $geometry ) {
			continue;
		}
		$collection['features'][] = array(
			'type'       => 'Feature',
			'geometry'   => $geometry,
			'properties' => axismundi_geodata_georss_item_properties( $item ),
		);
	}

	return $collection;
}

/**
 * Extract a single GeoJSON geometry from one SimplePie item, or null.
 *
 * @param SimplePie\Item|object $item Feed item.
 * @return array<string,mixed>|null
 */
function axismundi_geodata_georss_item_geometry( $item ) : ?array {
	$point = axismundi_geodata_georss_tag_data( $item, AXISMUNDI_GEODATA_GEORSS_NS, 'point' );
	if ( '' !== $point ) {
		$ring = axismundi_geodata_georss_positions( $point );
		if ( ! empty( $ring ) ) {
			return array( 'type' => 'Point', 'coordinates' => $ring[0] );
		}
	}

	$line = axismundi_geodata_georss_tag_data( $item, AXISMUNDI_GEODATA_GEORSS_NS, 'line' );
	if ( '' !== $line ) {
		$ring = axismundi_geodata_georss_positions( $line );
		if ( count( $ring ) >= 2 ) {
			return array( 'type' => 'LineString', 'coordinates' => $ring );
		}
	}

	$polygon = axismundi_geodata_georss_tag_data( $item, AXISMUNDI_GEODATA_GEORSS_NS, 'polygon' );
	if ( '' !== $polygon ) {
		$ring = axismundi_geodata_georss_positions( $polygon );
		if ( count( $ring ) >= 3 ) {
			return array( 'type' => 'Polygon', 'coordinates' => array( axismundi_geodata_close_ring( $ring ) ) );
		}
	}

	$box = axismundi_geodata_georss_tag_data( $item, AXISMUNDI_GEODATA_GEORSS_NS, 'box' );
	if ( '' !== $box ) {
		$ring = axismundi_geodata_georss_box_ring( $box );
		if ( ! empty( $ring ) ) {
			return array( 'type' => 'Polygon', 'coordinates' => array( $ring ) );
		}
	}

	$lat = axismundi_geodata_georss_tag_data( $item, AXISMUNDI_GEODATA_W3CGEO_NS, 'lat' );
	$lng = axismundi_geodata_georss_tag_data( $item, AXISMUNDI_GEODATA_W3CGEO_NS, 'long' );
	if ( '' !== $lat && '' !== $lng ) {
		$position = axismundi_geodata_coords_to_geojson_position( $lat, $lng );
		if ( ! empty( $position ) ) {
			return array( 'type' => 'Point', 'coordinates' => $position );
		}
	}

	return null;
}

/**
 * Read the text of the first matching namespaced tag on a SimplePie item.
 *
 * @param SimplePie\Item|object $item      Feed item.
 * @param string                $namespace Tag namespace URI.
 * @param string                $tag       Local tag name.
 * @return string Trimmed tag text, or '' when absent.
 */
function axismundi_geodata_georss_tag_data( $item, string $namespace, string $tag ) : string {
	$tags = $item->get_item_tags( $namespace, $tag );
	if ( is_array( $tags ) && isset( $tags[0]['data'] ) ) {
		return trim( (string) $tags[0]['data'] );
	}
	return '';
}

/**
 * Normalise one SimplePie item into the popup property set the Map block uses.
 *
 * Everything is plain text or a validated URL except byline_html, which is the
 * feed's lead paragraph passed through a strict wp_kses allowlist so the source
 * "X posted a photo:" line keeps its author link without becoming an XSS vector.
 *
 * @param SimplePie\Item|object $item Feed item.
 * @return array<string,mixed>
 */
function axismundi_geodata_georss_item_properties( $item ) : array {
	$content = (string) $item->get_content();
	if ( '' === trim( $content ) ) {
		$content = (string) $item->get_description();
	}
	$split = axismundi_geodata_georss_split_content( $content );

	$author_name = '';
	$author_url  = '';
	$author      = $item->get_author();
	if ( $author ) {
		$author_name = trim( (string) $author->get_name() );
		$author_url  = esc_url_raw( (string) $author->get_link() );
	}

	$timestamp = $item->get_date( 'U' );

	return array(
		'type'        => 'georss',
		'title'       => trim( (string) $item->get_title() ),
		'url'         => esc_url_raw( (string) $item->get_permalink() ),
		'thumbnail'   => axismundi_geodata_georss_item_image( $item, $content ),
		'author_name' => $author_name,
		'author_url'  => $author_url,
		'byline_html' => $split['byline'],
		'published'   => $timestamp ? date_i18n( (string) get_option( 'date_format' ), (int) $timestamp ) : '',
		'excerpt'     => $split['excerpt'],
	);
}

/**
 * Representative image URL: SimplePie enclosure (media:content/thumbnail or an
 * image enclosure), then the first inline <img> in the content.
 *
 * @param SimplePie\Item|object $item    Feed item.
 * @param string                $content Content markup.
 * @return string
 */
function axismundi_geodata_georss_item_image( $item, string $content ) : string {
	$enclosure = $item->get_enclosure();
	if ( $enclosure ) {
		$thumbnail = esc_url_raw( (string) $enclosure->get_thumbnail() );
		if ( '' !== $thumbnail ) {
			return $thumbnail;
		}
		$link = (string) $enclosure->get_link();
		$type = (string) $enclosure->get_type();
		if ( '' !== $link && 0 === stripos( $type, 'image/' ) ) {
			return esc_url_raw( $link );
		}
	}

	if ( preg_match( '#<img[^>]+src=["\']([^"\']+)["\']#i', $content, $m ) ) {
		return esc_url_raw( $m[1] );
	}

	return '';
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
	$pairs     = (int) floor( count( $tokens ) / 2 );
	for ( $i = 0; $i < $pairs; $i++ ) {
		$position = axismundi_geodata_coords_to_geojson_position( $tokens[ $i * 2 ], $tokens[ $i * 2 + 1 ] );
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
