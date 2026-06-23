<?php
/**
 * Plugin Name:       Axismundi Geodata
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-geodata
 * Description:       Canonical geo store for Axismundi — geo_area / geotag taxonomies and privacy-aware coordinate metadata for posts and attachments, exposed over REST for the editor, map blocks, and federation serializers.
 * Version:           0.1.0
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

define( 'AXISMUNDI_GEODATA_VERSION', '0.1.0' );

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
require_once __DIR__ . '/includes/taxonomy.php';
require_once __DIR__ . '/includes/meta.php';
