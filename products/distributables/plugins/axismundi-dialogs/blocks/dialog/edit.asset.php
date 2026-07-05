<?php
/**
 * Dependency manifest for edit.js (no build step). WordPress reads this sibling
 * file to enqueue edit.js with the right script handles + version.
 *
 * @package AxismundiDialogs
 */

return array(
	'dependencies' => array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
	'version'      => '0.1.0',
);
