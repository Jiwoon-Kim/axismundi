<?php
/**
 * The ax_note custom post type.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_NOTE_POST_TYPE = 'ax_note';

/**
 * Register the private Note post type.
 *
 * Increment 3 keeps the type non-public: it is editable in wp-admin and exposed
 * through the REST controller, but not publicly queryable and carries no public
 * rewrite. A followers-only or mentioned-only Note body must never resolve
 * through a Core permalink before the fail-closed content-negotiation route
 * (increment 4) exists.
 */
function axismundi_note_register_cpt() : void {
	register_post_type(
		AXISMUNDI_NOTE_POST_TYPE,
		array(
			'labels'              => array(
				'name'          => __( 'Notes', 'axismundi-note' ),
				'singular_name' => __( 'Note', 'axismundi-note' ),
				'add_new_item'  => __( 'Add New Note', 'axismundi-note' ),
				'edit_item'     => __( 'Edit Note', 'axismundi-note' ),
				'menu_name'     => __( 'Notes', 'axismundi-note' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'menu_icon'           => 'dashicons-format-status',
			'supports'            => array( 'title', 'editor', 'author', 'revisions' ),
			'map_meta_cap'        => true,
		)
	);
}
add_action( 'init', 'axismundi_note_register_cpt' );

/**
 * Force the Classic Editor for Notes.
 *
 * `show_in_rest` alone would hand the type to the block editor; the envelope
 * authoring UI and the `a.mention` anchor contract target the Classic Editor,
 * so the block editor is explicitly withheld for this type.
 */
function axismundi_note_force_classic_editor( bool $use_block_editor, string $post_type ) : bool {
	return AXISMUNDI_NOTE_POST_TYPE === $post_type ? false : $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'axismundi_note_force_classic_editor', 10, 2 );
