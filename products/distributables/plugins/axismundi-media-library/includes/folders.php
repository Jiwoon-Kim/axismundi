<?php
/**
 * Phase 2a virtual folders — the ax_media_folder taxonomy + service layer.
 *
 * Model (docs/DATA-MODEL.md §3, PHASES.md Phase 2):
 * - Hierarchical taxonomy on attachments; a hidden per-user ROOT term parents each
 *   user's top-level folders so two users can both have a "Travel" folder.
 * - Owner is `_ax_media_folder_owner` term meta.
 * - Single relation: an attachment is in 0 or 1 folder (wp_set_object_terms with
 *   append=false).
 * - Managing a folder (create child / rename / delete) needs folder ownership or
 *   `edit_others_posts`. MOVING an attachment additionally needs `edit_post` on
 *   THAT attachment — the FileBird gap (upload_files-only) we avoid.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_MEDIA_FOLDER_TAX = 'ax_media_folder';

/**
 * Register the folder taxonomy (a passive data container — the plugin owns the UI,
 * REST, and routing, so no public archive / admin column / rewrite). Registered in
 * both modes so existing folder terms stay queryable; behaviour is gated elsewhere.
 *
 * @return void
 */
function axismundi_media_register_folder_taxonomy() : void {
	register_taxonomy(
		AXISMUNDI_MEDIA_FOLDER_TAX,
		'attachment',
		array(
			'hierarchical'       => true,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'show_in_nav_menus'  => false,
			'show_in_rest'       => false,
			'show_admin_column'  => false,
			'rewrite'            => false,
			'query_var'          => false,
			// Attachments are post_status=inherit; the default post term counter only
			// counts 'publish', so use the generic (status-agnostic) counter or every
			// folder count stays 0.
			'update_count_callback' => '_update_generic_term_count',
			'labels'             => array( 'name' => __( 'Media Folders', 'axismundi-media-library' ) ),
		)
	);
}
add_action( 'init', 'axismundi_media_register_folder_taxonomy', 8 );

/**
 * The owner (user ID) of a folder term.
 *
 * @param int $term_id Term ID.
 * @return int
 */
function axismundi_media_folder_owner( int $term_id ) : int {
	return (int) get_term_meta( $term_id, '_ax_media_folder_owner', true );
}

/**
 * May a user create children under / rename / delete this folder?
 * (Owner or an editor with `edit_others_posts`.)
 *
 * @param int      $term_id Term ID.
 * @param int|null $user_id User (defaults to current).
 * @return bool
 */
function axismundi_media_can_manage_folder( int $term_id, ?int $user_id = null ) : bool {
	$user_id = $user_id ?? get_current_user_id();
	if ( $user_id <= 0 ) {
		return false;
	}
	return $user_id === axismundi_media_folder_owner( $term_id ) || user_can( $user_id, 'edit_others_posts' );
}

/**
 * The hidden per-user root term ID, creating it on demand.
 *
 * @param int  $user_id User ID.
 * @param bool $create  Create when missing.
 * @return int 0 when absent and not created.
 */
function axismundi_media_user_root( int $user_id, bool $create = true ) : int {
	if ( $user_id <= 0 ) {
		return 0;
	}
	$found = get_terms(
		array(
			'taxonomy'   => AXISMUNDI_MEDIA_FOLDER_TAX,
			'hide_empty' => false,
			'number'     => 1,
			'fields'     => 'ids',
			'meta_key'   => '_ax_media_folder_root', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => (string) $user_id,       // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( ! is_wp_error( $found ) && ! empty( $found ) ) {
		return (int) $found[0];
	}
	if ( ! $create ) {
		return 0;
	}
	$res = wp_insert_term( '__ax_root_' . $user_id, AXISMUNDI_MEDIA_FOLDER_TAX, array( 'parent' => 0 ) );
	if ( is_wp_error( $res ) ) {
		return 0;
	}
	$term_id = (int) $res['term_id'];
	update_term_meta( $term_id, '_ax_media_folder_root', $user_id );
	update_term_meta( $term_id, '_ax_media_folder_owner', $user_id );
	return $term_id;
}

/**
 * Is this term a hidden root (never shown / assignable)?
 *
 * @param int $term_id Term ID.
 * @return bool
 */
function axismundi_media_is_root_term( int $term_id ) : bool {
	return '' !== (string) get_term_meta( $term_id, '_ax_media_folder_root', true );
}

/**
 * Create a folder owned by a user. Top-level folders are parented to the user's
 * hidden root so per-user names don't collide.
 *
 * @param string   $name   Folder name.
 * @param int      $parent Parent folder term (0 = top level).
 * @param int|null $owner  Owner (defaults to current user).
 * @return int|WP_Error New term ID.
 */
function axismundi_media_create_folder( string $name, int $parent = 0, ?int $owner = null ) {
	$owner = $owner ?? get_current_user_id();
	if ( $owner <= 0 ) {
		return new WP_Error( 'ax_media_no_user', __( 'Not allowed.', 'axismundi-media-library' ), array( 'status' => 403 ) );
	}
	$name = trim( wp_strip_all_tags( $name ) );
	if ( '' === $name ) {
		return new WP_Error( 'ax_media_folder_name', __( 'A folder name is required.', 'axismundi-media-library' ), array( 'status' => 400 ) );
	}
	if ( $parent > 0 ) {
		if ( ! axismundi_media_can_manage_folder( $parent, $owner ) || axismundi_media_is_root_term( $parent ) ) {
			return new WP_Error( 'ax_media_folder_parent', __( 'Invalid parent folder.', 'axismundi-media-library' ), array( 'status' => 400 ) );
		}
		$actual_parent = $parent;
	} else {
		$actual_parent = axismundi_media_user_root( $owner );
	}
	$res = wp_insert_term( $name, AXISMUNDI_MEDIA_FOLDER_TAX, array( 'parent' => $actual_parent ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$term_id = (int) $res['term_id'];
	update_term_meta( $term_id, '_ax_media_folder_owner', $owner );
	return $term_id;
}

/**
 * Rename a folder.
 *
 * @param int      $term_id Term ID.
 * @param string   $name    New name.
 * @param int|null $user_id Acting user.
 * @return int|WP_Error
 */
function axismundi_media_rename_folder( int $term_id, string $name, ?int $user_id = null ) {
	$user_id = $user_id ?? get_current_user_id();
	if ( axismundi_media_is_root_term( $term_id ) || ! axismundi_media_can_manage_folder( $term_id, $user_id ) ) {
		return new WP_Error( 'ax_media_forbidden', __( 'Not allowed.', 'axismundi-media-library' ), array( 'status' => 403 ) );
	}
	$name = trim( wp_strip_all_tags( $name ) );
	if ( '' === $name ) {
		return new WP_Error( 'ax_media_folder_name', __( 'A folder name is required.', 'axismundi-media-library' ), array( 'status' => 400 ) );
	}
	$res = wp_update_term( $term_id, AXISMUNDI_MEDIA_FOLDER_TAX, array( 'name' => $name ) );
	return is_wp_error( $res ) ? $res : $term_id;
}

/**
 * Delete a folder: its attachments move to the root (unfiled) — never deleted —
 * and its direct child folders are reparented to the deleted folder's parent.
 *
 * @param int      $term_id Term ID.
 * @param int|null $user_id Acting user.
 * @return true|WP_Error
 */
function axismundi_media_delete_folder( int $term_id, ?int $user_id = null ) {
	$user_id = $user_id ?? get_current_user_id();
	if ( axismundi_media_is_root_term( $term_id ) || ! axismundi_media_can_manage_folder( $term_id, $user_id ) ) {
		return new WP_Error( 'ax_media_forbidden', __( 'Not allowed.', 'axismundi-media-library' ), array( 'status' => 403 ) );
	}
	$term = get_term( $term_id, AXISMUNDI_MEDIA_FOLDER_TAX );
	if ( ! $term instanceof WP_Term ) {
		return new WP_Error( 'ax_media_notfound', __( 'Folder not found.', 'axismundi-media-library' ), array( 'status' => 404 ) );
	}
	foreach ( (array) get_objects_in_term( $term_id, AXISMUNDI_MEDIA_FOLDER_TAX ) as $attachment_id ) {
		axismundi_media_set_attachment_folder( (int) $attachment_id, 0 );
	}
	$children = get_terms(
		array(
			'taxonomy'   => AXISMUNDI_MEDIA_FOLDER_TAX,
			'hide_empty' => false,
			'parent'     => $term_id,
			'fields'     => 'ids',
		)
	);
	foreach ( (array) $children as $child_id ) {
		wp_update_term( (int) $child_id, AXISMUNDI_MEDIA_FOLDER_TAX, array( 'parent' => (int) $term->parent ) );
	}
	$deleted = wp_delete_term( $term_id, AXISMUNDI_MEDIA_FOLDER_TAX );
	return is_wp_error( $deleted ) ? $deleted : true;
}

/**
 * The attachment's current folder term ID (0 = unfiled).
 *
 * @param int $attachment_id Attachment ID.
 * @return int
 */
function axismundi_media_attachment_folder( int $attachment_id ) : int {
	$terms = wp_get_object_terms( $attachment_id, AXISMUNDI_MEDIA_FOLDER_TAX, array( 'fields' => 'ids' ) );
	return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (int) $terms[0] : 0;
}

/**
 * Set the attachment's single folder (0 = unfiled). Enforces the one-folder rule
 * (append = false). No permission check here — callers gate it.
 *
 * @param int $attachment_id Attachment ID.
 * @param int $folder_id     Folder term ID or 0.
 * @return void
 */
function axismundi_media_set_attachment_folder( int $attachment_id, int $folder_id ) : void {
	wp_set_object_terms(
		$attachment_id,
		$folder_id > 0 ? array( $folder_id ) : array(),
		AXISMUNDI_MEDIA_FOLDER_TAX,
		false
	);
	if ( $folder_id > 0 ) {
		update_post_meta( $attachment_id, '_ax_media_folder_added_at', current_time( 'mysql' ) );
	} else {
		delete_post_meta( $attachment_id, '_ax_media_folder_added_at' );
	}
}

/**
 * Move attachments into a folder. Requires `edit_post` on EACH attachment (the
 * FileBird gap we avoid) plus manage rights on the target folder.
 *
 * @param int[]    $attachment_ids Attachment IDs.
 * @param int      $folder_id      Target folder (0 = unfiled/root).
 * @param int|null $user_id        Acting user.
 * @return array{moved:int[],denied:int[]}|WP_Error
 */
function axismundi_media_move_attachments( array $attachment_ids, int $folder_id, ?int $user_id = null ) {
	$user_id = $user_id ?? get_current_user_id();
	if ( $folder_id > 0 && ( axismundi_media_is_root_term( $folder_id ) || ! axismundi_media_can_manage_folder( $folder_id, $user_id ) ) ) {
		return new WP_Error( 'ax_media_folder_target', __( 'You cannot use that folder.', 'axismundi-media-library' ), array( 'status' => 403 ) );
	}
	$moved  = array();
	$denied = array();
	foreach ( $attachment_ids as $raw ) {
		$aid = (int) $raw;
		if ( 'attachment' !== get_post_type( $aid ) || ! user_can( $user_id, 'edit_post', $aid ) ) {
			$denied[] = $aid;
			continue;
		}
		axismundi_media_set_attachment_folder( $aid, $folder_id );
		$moved[] = $aid;
	}
	return array(
		'moved'  => $moved,
		'denied' => $denied,
	);
}

/**
 * Direct object count of a folder (one folder per attachment, so the term count
 * is the direct count).
 *
 * @param int $term_id Term ID.
 * @return int
 */
function axismundi_media_folder_direct_count( int $term_id ) : int {
	$term = get_term( $term_id, AXISMUNDI_MEDIA_FOLDER_TAX );
	return $term instanceof WP_Term ? (int) $term->count : 0;
}

/**
 * Recursive object count: this folder plus all descendants.
 *
 * @param int $term_id Term ID.
 * @return int
 */
function axismundi_media_folder_recursive_count( int $term_id ) : int {
	$total = axismundi_media_folder_direct_count( $term_id );
	foreach ( (array) get_term_children( $term_id, AXISMUNDI_MEDIA_FOLDER_TAX ) as $child_id ) {
		$total += axismundi_media_folder_direct_count( (int) $child_id );
	}
	return $total;
}

/**
 * A user's folders as a flat list (excludes the hidden root), each with counts.
 *
 * @param int $user_id User ID.
 * @return array<int,array<string,mixed>>
 */
function axismundi_media_user_folders( int $user_id ) : array {
	$terms = get_terms(
		array(
			'taxonomy'   => AXISMUNDI_MEDIA_FOLDER_TAX,
			'hide_empty' => false,
			'meta_key'   => '_ax_media_folder_owner', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => (string) $user_id,         // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	$root = axismundi_media_user_root( $user_id, false );
	$out  = array();
	foreach ( $terms as $term ) {
		if ( axismundi_media_is_root_term( $term->term_id ) ) {
			continue;
		}
		$out[] = array(
			'id'              => (int) $term->term_id,
			'name'            => $term->name,
			'parent'          => ( (int) $term->parent === $root ) ? 0 : (int) $term->parent,
			'count'           => axismundi_media_folder_direct_count( (int) $term->term_id ),
			'recursive_count' => axismundi_media_folder_recursive_count( (int) $term->term_id ),
		);
	}
	return $out;
}
