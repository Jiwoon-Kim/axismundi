<?php
/**
 * Playground setup: the rendering demo page.
 *
 * Source for the blueprints. build.py puts demo-page.html where the marker line
 * below is and embeds this file as the runPHP step's code, because a runPHP
 * step takes inline code only.
 *
 * @package AxismundiEmoji
 */

require_once '/wordpress/wp-load.php';

// The page carries a status script; kses would strip it for a request with no user.
wp_set_current_user( 1 );
kses_remove_filters();

$content = <<<'HTML'
/* demo-page.html */
HTML;

// The blueprint's landingPage opens this page by its slug.
$page_id = wp_insert_post(
	array(
		'post_title'   => 'Axismundi Emoji rendering demo',
		'post_name'    => 'emoji-demo',
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_content' => $content,
	)
);

if ( $page_id && ! is_wp_error( $page_id ) ) {
	wp_update_post(
		array(
			'ID'           => $page_id,
			'post_content' => strtr(
				$content,
				array(
					'{{SETTINGS_URL}}' => esc_url( admin_url( 'admin.php?page=axismundi-emoji-settings' ) ),
					'{{EDIT_URL}}'     => esc_url( admin_url( 'post.php?post=' . $page_id . '&action=edit' ) ),
				)
			),
		)
	);
}
