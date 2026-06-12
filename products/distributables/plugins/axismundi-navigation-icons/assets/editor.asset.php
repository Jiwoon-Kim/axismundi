<?php
/**
 * Dependency manifest for editor.js (no build step). WordPress reads this sibling
 * file to enqueue editor.js with the right script handles + version.
 *
 * @package AxismundiNavigationIcons
 */

return array(
	'dependencies' => array(
		'wp-blocks',
		'wp-element',
		'wp-block-editor',
		'wp-components',
		'wp-compose',
		'wp-hooks',
		'wp-i18n',
	),
	'version'      => '0.1.0',
);
