<?php
/**
 * The ax_note custom post type.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_NOTE_POST_TYPE = 'ax_note';
const AXISMUNDI_NOTE_CAPABILITIES_VERSION = 1;

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
			'capability_type'     => array( 'ax_note', 'ax_notes' ),
			'capabilities'        => array(
				/*
				 * Federated authorship and site operations are different authorities. A public
				 * Actor is required to create/publish a Note, while `edit_ax_notes` admits
				 * operators to the list screen so they can moderate without impersonating an
				 * Actor. Per-object editing stays author-only in the meta-cap mapper below.
				 */
				'create_posts'  => 'create_ax_notes',
				'edit_posts'    => 'edit_ax_notes',
				'publish_posts' => 'create_ax_notes',
				'edit_others_posts'   => 'edit_others_ax_notes',
				'delete_others_posts' => 'delete_others_ax_notes',
				'read_private_posts'  => 'read_private_ax_notes',
			),
			'map_meta_cap'        => true,
		)
	);
}
add_action( 'init', 'axismundi_note_register_cpt' );

/**
 * Grant only site operators the primitive capabilities needed to moderate Notes.
 *
 * Editors are intentionally not included by default. A site may opt them in via
 * the filter, but publishing or editing another Actor's Note is never implied by
 * these capabilities. This changes the roles option, so it is only run at
 * activation or when this capability migration changes.
 */
function axismundi_note_sync_operator_caps() : void {
	$roles = apply_filters( 'axismundi_note_operator_roles', array( 'administrator' ) );
	foreach ( array_unique( array_filter( array_map( 'strval', (array) $roles ) ) ) as $role_name ) {
		$role = get_role( $role_name );
		if ( ! $role instanceof WP_Role ) {
			continue;
		}
		foreach ( array( 'edit_ax_notes', 'delete_ax_notes', 'delete_others_ax_notes', 'read_private_ax_notes' ) as $capability ) {
			$role->add_cap( $capability );
		}
	}
}

/** Apply the role migration once for existing installations. */
function axismundi_note_maybe_sync_operator_caps() : void {
	if ( AXISMUNDI_NOTE_CAPABILITIES_VERSION === (int) get_option( 'axismundi_note_capabilities_version', 0 ) ) {
		return;
	}
	axismundi_note_sync_operator_caps();
	update_option( 'axismundi_note_capabilities_version', AXISMUNDI_NOTE_CAPABILITIES_VERSION );
}
add_action( 'plugins_loaded', 'axismundi_note_maybe_sync_operator_caps', 25 );

/** Whether a user has the public Actor identity needed to author a federated Note. */
function axismundi_note_user_can_author( int $user_id ) : bool {
	$actor = $user_id > 0 && function_exists( 'axismundi_actors_get_for_user' ) ? axismundi_actors_get_for_user( $user_id ) : null;
	return $actor instanceof Axismundi_Actor
		&& $actor->is_local()
		&& 'Person' === $actor->get_type()
		&& 'public' === $actor->get_status()
		&& $actor->is_handle_locked();
}

/** Give public-Actor authors the primitive capabilities used by their own Note screens. */
function axismundi_note_grant_author_entry_cap( array $allcaps, array $caps, array $args, WP_User $user ) : array {
	$author_caps = array( 'edit_ax_notes', 'edit_published_ax_notes', 'delete_ax_notes', 'delete_published_ax_notes' );
	if ( array_intersect( $author_caps, $caps ) && axismundi_note_user_can_author( (int) $user->ID ) ) {
		foreach ( array_intersect( $author_caps, $caps ) as $capability ) {
			$allcaps[ $capability ] = true;
		}
	}
	return $allcaps;
}
add_filter( 'user_has_cap', 'axismundi_note_grant_author_entry_cap', 20, 4 );

/**
 * Keep WordPress editorial capabilities separate from federated Note authorship.
 *
 * A public-Actor author may create and edit only their own Notes. Site operators may
 * read private Notes and delete another author's Note, but may never edit it: a remote
 * peer must not receive an Update that appears to come from the wrong Actor.
 */
function axismundi_note_map_actor_caps( array $caps, string $cap, int $user_id, array $args ) : array {
	if ( 'create_ax_notes' === $cap ) {
		return axismundi_note_user_can_author( $user_id ) ? array( 'read' ) : array( 'do_not_allow' );
	}
	if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'read_post' ), true ) || empty( $args[0] ) ) {
		return $caps;
	}
	$post = get_post( (int) $args[0] );
	if ( ! $post instanceof WP_Post || AXISMUNDI_NOTE_POST_TYPE !== $post->post_type ) {
		return $caps;
	}

	if ( 'read_post' === $cap ) {
		// Keep Core's ordinary public-read semantics intact for published Notes.
		if ( 'publish' === $post->post_status ) {
			return $caps;
		}
		if ( (int) $post->post_author === $user_id && axismundi_note_user_can_author( $user_id ) ) {
			return array( 'read' );
		}
		return user_can( $user_id, 'read_private_ax_notes' ) ? array( 'read' ) : array( 'do_not_allow' );
	}

	if ( (int) $post->post_author === $user_id && axismundi_note_user_can_author( $user_id ) ) {
		return array( 'read' );
	}
	if ( 'delete_post' === $cap && user_can( $user_id, 'delete_others_ax_notes' ) ) {
		return array( 'read' );
	}
	return array( 'do_not_allow' );
}
add_filter( 'map_meta_cap', 'axismundi_note_map_actor_caps', 20, 4 );

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
 * so no media blocks are offered. The reused block-editor mention completer,
 * owned by Object Projections and explicitly enqueued by the Note editor,
 * provides the `a.mention` anchor contract inside `core/paragraph`.
 */
function axismundi_note_allowed_block_types( $allowed, $context ) {
	if ( ! isset( $context->post ) || ! $context->post instanceof WP_Post || AXISMUNDI_NOTE_POST_TYPE !== $context->post->post_type ) {
		return $allowed;
	}
	return array( 'core/paragraph', 'core/embed', 'core/list', 'core/list-item', 'core/quote' );
}
add_filter( 'allowed_block_types_all', 'axismundi_note_allowed_block_types', 10, 2 );
