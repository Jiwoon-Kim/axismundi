<?php
/**
 * Asset metadata for assets/overlay.js.
 *
 * A view module's dependencies come from this file and nowhere else. Without
 * it, `@wordpress/interactivity` reaches the page's import map only because
 * some other block - core/navigation, usually - happens to pull it in, and the
 * module dies quietly on a page that has none. Measured: on a page carrying the
 * theme's header the map held exactly one entry, put there by core.
 *
 * It also carries the version. Without one WordPress falls back to core's, so
 * an edited module keeps serving from cache.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

return array(
	'dependencies' => array(
		array(
			'id'     => '@wordpress/interactivity',
			'import' => 'static',
		),
	),
	'version'      => (string) filemtime( __DIR__ . '/overlay.js' ),
);
