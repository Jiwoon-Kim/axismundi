<?php
/**
 * The `ax_forum` custom post type (docs/AXISMUNDI-FORUM-ARCHITECTURE.md §3.1).
 *
 * The Forum's authored content — title, description/body, and (later) posting,
 * membership, and moderation policy — lives on this CPT so it inherits authoring,
 * revisions, media, and Site Editor integration. Its binding to a managed Group Actor
 * lives in the dedicated join table, never in post meta.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Forum post type. Public with an archive so `/forum/{slug}/` and the
 * board index resolve; block-editor enabled so its body is composable.
 *
 * @return void
 */
function axismundi_forum_register_post_type() : void {
	$labels = array(
		'name'               => _x( 'Forums', 'post type general name', 'axismundi-forum' ),
		'singular_name'      => _x( 'Forum', 'post type singular name', 'axismundi-forum' ),
		'menu_name'          => _x( 'Forums', 'admin menu', 'axismundi-forum' ),
		'add_new'            => __( 'Add New', 'axismundi-forum' ),
		'add_new_item'       => __( 'Add New Forum', 'axismundi-forum' ),
		'edit_item'          => __( 'Edit Forum', 'axismundi-forum' ),
		'new_item'           => __( 'New Forum', 'axismundi-forum' ),
		'view_item'          => __( 'View Forum', 'axismundi-forum' ),
		'search_items'       => __( 'Search Forums', 'axismundi-forum' ),
		'not_found'          => __( 'No forums found', 'axismundi-forum' ),
		'not_found_in_trash' => __( 'No forums found in Trash', 'axismundi-forum' ),
		'all_items'          => __( 'All Forums', 'axismundi-forum' ),
	);

	register_post_type(
		'ax_forum',
		array(
			'labels'       => $labels,
			'public'       => true,
			'has_archive'  => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-groups',
			'rewrite'      => array( 'slug' => 'forum', 'with_front' => false ),
			'supports'     => array( 'title', 'editor', 'excerpt', 'revisions', 'custom-fields' ),
		)
	);
}
add_action( 'init', 'axismundi_forum_register_post_type' );
