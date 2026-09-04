<?php
/**
 * Dependency manifest for edit.js (no build step). WordPress reads this sibling
 * file to enqueue edit.js with the right script handles + version.
 *
 * The version is the file's own modification time, not the plugin's. A fixed
 * string here survives an edit to edit.js, so a browser that already has the
 * file keeps running the old one until the next release -- the block's
 * stylesheet is already versioned this way, which is why CSS changes appear and
 * editor changes did not. In a distributed copy the file does not change, so the
 * value is stable.
 *
 * @package Axismundi
 */

defined( 'ABSPATH' ) || exit;

$axismundi_theme_switcher_edit_js = __DIR__ . '/edit.js';

return array(
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-compose' ),
	'version'      => file_exists( $axismundi_theme_switcher_edit_js )
		? (string) filemtime( $axismundi_theme_switcher_edit_js )
		: '0.1.7',
);
