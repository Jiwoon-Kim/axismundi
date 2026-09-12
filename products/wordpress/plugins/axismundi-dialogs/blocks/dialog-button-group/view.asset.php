<?php
/**
 * Asset metadata for the Dialog Button Group view module.
 *
 * Hand-written, as view.js is not built. The dependency is what puts
 * @wordpress/interactivity in the page's import map: without it the module
 * would only work on pages where another block happened to depend on it.
 *
 * @package Axismundi_Dialogs
 */

return array(
	'dependencies' => array(
		array(
			'id'     => '@wordpress/interactivity',
			'import' => 'static',
		),
	),
	'version'      => (string) filemtime( __DIR__ . '/view.js' ),
);
