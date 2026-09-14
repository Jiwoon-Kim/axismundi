<?php
/**
 * Live Preview setup: the Dialog Surface parts and the VQA page.
 *
 * Source for blueprint.json. build.py puts vqa-page.html where the marker line
 * below is and embeds this file as the runPHP step's code, because a runPHP
 * step takes inline code only.
 *
 * @package AxismundiDialogs
 */

require_once '/wordpress/wp-load.php';

/*
 * Each part starts from one of the plugin's own starter patterns, so the
 * preview also proves the patterns work as real parts; a few change only the
 * root Dialog block's settings.
 */
function axismundi_vqa_pattern( $name ) {
	$pattern = WP_Block_Patterns_Registry::get_instance()->get_registered( 'axismundi-dialogs/dialog-surface-' . $name );
	return $pattern ? $pattern['content'] : '';
}

function axismundi_vqa_with_root_attrs( $content, $attrs ) {
	$blocks = parse_blocks( $content );
	foreach ( $blocks as $i => $block ) {
		if ( 'axismundi/dialog' === $block['blockName'] ) {
			$blocks[ $i ]['attrs'] = array_merge( (array) $block['attrs'], $attrs );
			break;
		}
	}
	return serialize_blocks( $blocks );
}

$parts = array(
	'dialog-basic'              => array( 'Dialog - Basic', 'basic', array() ),
	'dialog-basic-icon'         => array( 'Dialog - Basic with icon', 'basic-icon', array() ),
	'dialog-list'               => array( 'Dialog - List', 'list', array() ),
	'dialog-dismiss-none'       => array( 'Dialog - Basic, no light dismiss', 'basic', array( 'dismissal' => 'none' ) ),
	'dialog-full-screen'        => array( 'Dialog - Full screen', 'full-screen', array() ),
	'sheets-bottom'             => array( 'Sheets - Bottom', 'bottom-sheet', array() ),
	'sheets-side'               => array( 'Sheets - Side', 'side-sheet-modal', array() ),
	'sheets-side-start'         => array( 'Sheets - Side, start edge', 'side-sheet-modal', array( 'edge' => 'start' ) ),
	'sheets-side-standard'      => array( 'Sheets - Side, standard (resize)', 'side-sheet-standard', array() ),
	'sheets-side-standard-move' => array( 'Sheets - Side, standard (move)', 'side-sheet-standard', array( 'pageShare' => 'move' ) ),
);

foreach ( $parts as $slug => list( $title, $pattern, $attrs ) ) {
	$content = axismundi_vqa_pattern( $pattern );
	if ( '' === $content ) {
		continue;
	}
	if ( $attrs ) {
		$content = axismundi_vqa_with_root_attrs( $content, $attrs );
	}
	$id = wp_insert_post(
		array(
			'post_type'    => 'wp_template_part',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => $content,
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		wp_set_object_terms( $id, get_stylesheet(), 'wp_theme' );
		wp_set_object_terms( $id, 'dialog-surface', 'wp_template_part_area' );
	}
}

$content = <<<'HTML'
/* vqa-page.html */
HTML;

// The blueprint's landingPage opens this page by its slug.
wp_insert_post(
	array(
		'post_title'   => 'Plugin VQA - Axismundi Dialogs',
		'post_name'    => 'dialogs-vqa',
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_content' => $content,
	)
);
