<?php
/**
 * Geo taxonomy archive template ownership regression.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_geo_tpl_results = array();

/** @param bool[] $results Results. */
function ax_geo_tpl_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

$registry         = WP_Block_Templates_Registry::get_instance();
$pattern_registry = WP_Block_Patterns_Registry::get_instance();
$area             = $registry->get_registered( 'axismundi-geodata//taxonomy-axismundi_geo_area' );
$tag              = $registry->get_registered( 'axismundi-geodata//taxonomy-axismundi_geotag' );
$pattern           = $pattern_registry->get_registered( 'axismundi-geodata/geo-archive-results' );
$post_terms_pattern = $pattern_registry->get_registered( 'axismundi-geodata/post-geo-terms' );

ax_geo_tpl_assert(
	$ax_geo_tpl_results,
	'Geodata registers both standard taxonomy hierarchy templates',
	$area instanceof WP_Block_Template
		&& $tag instanceof WP_Block_Template
		&& 'taxonomy-axismundi_geo_area' === $area->slug
		&& 'taxonomy-axismundi_geotag' === $tag->slug
		&& 'axismundi-geodata' === $area->plugin
		&& 'axismundi-geodata' === $tag->plugin
);

ax_geo_tpl_assert(
	$ax_geo_tpl_results,
	'Geodata owns the opt-in post geo-terms pattern with both prefixed taxonomies',
	is_array( $post_terms_pattern )
		&& str_contains( (string) $post_terms_pattern['content'], '"term":"axismundi_geo_area"' )
		&& str_contains( (string) $post_terms_pattern['content'], '"term":"axismundi_geotag"' )
);

$content = $area instanceof WP_Block_Template ? (string) $area->content : '';
ax_geo_tpl_assert(
	$ax_geo_tpl_results,
	'both templates retain the existing archive composition under the Geodata namespace',
	str_contains( $content, '"slug":"axismundi-geodata/geo-archive-results"' )
		&& ! str_contains( $content, '"theme":"axismundi"' )
		&& $area->content === $tag->content
);
ax_geo_tpl_assert(
	$ax_geo_tpl_results,
	'the moved pattern preserves the map, inherited feed, and geo navigation',
	is_array( $pattern )
		&& str_contains( (string) $pattern['content'], '<!-- wp:axismundi/map' )
		&& str_contains( (string) $pattern['content'], '"inherit":true' )
		&& str_contains( (string) $pattern['content'], '"taxonomy":"axismundi_geo_area"' )
		&& str_contains( (string) $pattern['content'], '"taxonomy":"axismundi_geotag"' )
);

$resolved = get_block_template( get_stylesheet() . '//taxonomy-axismundi_geotag', 'wp_template' );
ax_geo_tpl_assert(
	$ax_geo_tpl_results,
	'without a theme override WordPress resolves the Geodata plugin template',
	$resolved instanceof WP_Block_Template
		&& 'plugin' === $resolved->source
		&& 'axismundi-geodata' === $resolved->plugin
);

$ax_geo_tpl_failures = count( array_filter( $ax_geo_tpl_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_geo_tpl_results ), $ax_geo_tpl_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_geo_tpl_failures > 0 ? 1 : 0 );
}
exit( $ax_geo_tpl_failures > 0 ? 1 : 0 );
