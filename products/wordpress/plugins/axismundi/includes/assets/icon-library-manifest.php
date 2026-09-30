<?php
/**
 * Axismundi Icon Library manifest.
 *
 * Core's runtime icon manifest shape is preserved at the top level: `label` and `filePath` map
 * directly to wp_register_icon() arguments. `catalogue` is Axismundi-owned enrichment for a future
 * Assets Hub; WordPress Icon Registry does not receive it.
 *
 * @package Axismundi
 */

defined( 'ABSPATH' ) || exit;

return array(
	'design' => array(
		'label'    => _x( 'Design', 'icon label', 'axismundi' ),
		'filePath' => 'design.svg',
		'catalogue' => array(
			'keywords'   => array( 'design', 'presentation', 'editor' ),
			'provenance' => array(
				'provider'    => 'Material Symbols',
				'name'        => 'design_services',
				'variant'     => 'outlined',
				'fill'        => 0,
				'weight'      => 400,
				'grade'       => 0,
				'opticalSize' => 24,
				'license'     => 'Apache-2.0',
			),
		),
	),
	'styles' => array(
		'label'    => _x( 'Styles', 'icon label', 'axismundi' ),
		'filePath' => 'styles.svg',
		'catalogue' => array(
			'keywords'   => array( 'styles', 'palette', 'colour', 'theme' ),
			'provenance' => array(
				'provider'    => 'Material Symbols',
				'name'        => 'palette',
				'variant'     => 'outlined',
				'fill'        => 0,
				'weight'      => 400,
				'grade'       => 0,
				'opticalSize' => 24,
				'license'     => 'Apache-2.0',
			),
		),
	),
	'templates' => array(
		'label'    => _x( 'Templates', 'icon label', 'axismundi' ),
		'filePath' => 'templates.svg',
		'catalogue' => array(
			'keywords'   => array( 'templates', 'layout', 'page', 'browse' ),
			'provenance' => array(
				'provider'    => 'Material Symbols',
				'name'        => 'browse',
				'variant'     => 'outlined',
				'fill'        => 0,
				'weight'      => 400,
				'grade'       => 0,
				'opticalSize' => 24,
				'license'     => 'Apache-2.0',
			),
		),
	),
	'patterns' => array(
		'label'    => _x( 'Patterns', 'icon label', 'axismundi' ),
		'filePath' => 'patterns.svg',
		'catalogue' => array(
			'keywords'   => array( 'patterns', 'blocks', 'composition', 'brick' ),
			'provenance' => array(
				'provider'    => 'Material Symbols',
				'name'        => 'brick',
				'variant'     => 'outlined',
				'fill'        => 0,
				'weight'      => 400,
				'grade'       => 0,
				'opticalSize' => 24,
				'license'     => 'Apache-2.0',
			),
		),
	),
	'components' => array(
		'label'    => _x( 'Components', 'icon label', 'axismundi' ),
		'filePath' => 'components.svg',
		'catalogue' => array(
			'keywords'   => array( 'components', 'interface', 'catalogue', 'exchange' ),
			'provenance' => array(
				'provider'    => 'Material Symbols',
				'name'        => 'component_exchange',
				'variant'     => 'outlined',
				'fill'        => 0,
				'weight'      => 400,
				'grade'       => 0,
				'opticalSize' => 24,
				'license'     => 'Apache-2.0',
			),
		),
	),
	'icons' => array(
		'label'    => _x( 'Icons', 'icon label', 'axismundi' ),
		'filePath' => 'icons.svg',
		'catalogue' => array(
			'keywords'   => array( 'icons', 'symbols', 'library', 'catalogue', 'interests' ),
			'provenance' => array(
				'provider'    => 'Material Symbols',
				'name'        => 'interests',
				'variant'     => 'outlined',
				'fill'        => 0,
				'weight'      => 400,
				'grade'       => 0,
				'opticalSize' => 24,
				'license'     => 'Apache-2.0',
			),
		),
	),
);
