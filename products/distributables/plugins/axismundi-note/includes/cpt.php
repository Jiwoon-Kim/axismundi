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
			// Title-less by default: a Note is short-form. Programmatic authors may still
			// set post_title and the transformer preserves it as AS `name`; the later
			// opt-in title control will expose that capability through the editor REST field.
			'supports'            => array( 'editor', 'author', 'revisions' ),
			'map_meta_cap'        => true,
		)
	);
}
add_action( 'init', 'axismundi_note_register_cpt' );

/** Opt Notes into Object Projections' shared social hashtag vocabulary. */
function axismundi_note_hashtag_object_types( array $types ) : array {
	$types[] = AXISMUNDI_NOTE_POST_TYPE;
	return array_values( array_unique( $types ) );
}
add_filter( 'axismundi_op_hashtag_object_types', 'axismundi_note_hashtag_object_types' );

/** Notes may serialize their explicitly assigned shared hashtags. */
function axismundi_note_hashtags_are_federated( bool $federated, WP_Post $post ) : bool {
	return AXISMUNDI_NOTE_POST_TYPE === $post->post_type ? true : $federated;
}
add_filter( 'axismundi_op_hashtag_is_federated', 'axismundi_note_hashtags_are_federated', 10, 2 );

/**
 * Restrict the Note block editor to a short-form palette.
 *
 * A Note is a short, linear body: a paragraph plus optional embeds. Media is
 * managed through the Media Library attachment relationship, never a body block,
 * so no media blocks are offered. The reused block-editor mention completer
 * (owned by Object Projections and enqueued globally) provides the `a.mention`
 * anchor contract inside `core/paragraph`.
 */
function axismundi_note_allowed_block_types( $allowed, $context ) {
	if ( ! isset( $context->post ) || ! $context->post instanceof WP_Post || AXISMUNDI_NOTE_POST_TYPE !== $context->post->post_type ) {
		return $allowed;
	}
	return array( 'core/paragraph', 'core/embed', 'core/list', 'core/list-item', 'core/quote' );
}
add_filter( 'allowed_block_types_all', 'axismundi_note_allowed_block_types', 10, 2 );
