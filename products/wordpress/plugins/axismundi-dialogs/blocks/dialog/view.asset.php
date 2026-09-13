<?php
/**
 * Dependency manifest for view.js (no build step): a plain module with no
 * dependencies, versioned by its modification time.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

return array(
	'dependencies' => array(),
	'version'      => (string) filemtime( __DIR__ . '/view.js' ),
);
