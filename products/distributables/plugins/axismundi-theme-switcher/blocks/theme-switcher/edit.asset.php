<?php
/**
 * Dependency manifest for edit.js (no build step). WordPress reads this sibling
 * file to enqueue edit.js with the right script handles + version.
 *
 * @package Axismundi
 */

return array(
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element' ),
	'version'      => '0.1.5',
);
