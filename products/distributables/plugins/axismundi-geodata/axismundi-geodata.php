<?php
/**
 * Plugin Name:       Axismundi Geodata
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-geodata
 * Description:       Canonical geo store for Axismundi — geo_area / geotag taxonomies and privacy-aware coordinate metadata for posts and attachments, exposed over REST for the editor, map blocks, and federation serializers.
 * Version:           0.2.1
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-geodata
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

define( 'AXISMUNDI_GEODATA_VERSION', '0.2.1' );
define( 'AXISMUNDI_GEODATA_FILE', __FILE__ );

/**
 * Object types that can carry geo TERMS (geo_area / geotag).
 *
 * Attachments are intentionally excluded — they carry coordinate meta only (from
 * file/EXIF metadata), never auto-applied place terms. A future ax_note CPT opts
 * in through this filter:
 *
 *   add_filter( 'axismundi_geodata_object_types', fn( $t ) => [ ...$t, 'ax_note' ] );
 *
 * @return string[]
 */
function axismundi_geodata_object_types() : array {
	$types = apply_filters( 'axismundi_geodata_object_types', array( 'post', 'page' ) );

	return array_values( array_unique( array_filter( (array) $types, 'is_string' ) ) );
}

/**
 * Object types that can carry coordinate META — the term-bearers above plus
 * attachments, whose coordinates come from EXIF / file metadata. Adding a type
 * to axismundi_geodata_object_types() therefore gives it both terms and meta;
 * attachments get meta only.
 *
 * @return string[]
 */
function axismundi_geodata_coordinate_object_types() : array {
	$types = array_merge( axismundi_geodata_object_types(), array( 'attachment' ) );

	return array_values( array_unique( $types ) );
}

require_once __DIR__ . '/includes/privacy.php';
require_once __DIR__ . '/includes/coordinates.php';
require_once __DIR__ . '/includes/taxonomy.php';
require_once __DIR__ . '/includes/templates.php';
require_once __DIR__ . '/includes/meta.php';
require_once __DIR__ . '/includes/place-types.php';
require_once __DIR__ . '/includes/place-id.php';
require_once __DIR__ . '/includes/lookup.php';
require_once __DIR__ . '/includes/google.php';
require_once __DIR__ . '/includes/osm.php';
require_once __DIR__ . '/includes/map-pack.php';
require_once __DIR__ . '/includes/track.php';
require_once __DIR__ . '/includes/geojson.php';
require_once __DIR__ . '/includes/georss.php';
require_once __DIR__ . '/includes/georss-import.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/map-assets.php';
require_once __DIR__ . '/includes/cli.php';

if ( is_admin() ) {
	require_once __DIR__ . '/includes/term-fields.php';
	require_once __DIR__ . '/includes/admin-columns.php';
	require_once __DIR__ . '/includes/attachment-gps.php';
}

/**
 * Register the taxonomies once on activation, then flush rewrite rules so the
 * public /geo-area/ and /geotag/ archives resolve immediately on pretty-permalink
 * installs instead of 404ing until Settings > Permalinks is re-saved.
 *
 * @return void
 */
function axismundi_geodata_activate() : void {
	axismundi_geodata_register_taxonomies();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'axismundi_geodata_activate' );

/**
 * Drop the taxonomy rewrite rules on deactivation.
 *
 * @return void
 */
function axismundi_geodata_deactivate() : void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'axismundi_geodata_deactivate' );
