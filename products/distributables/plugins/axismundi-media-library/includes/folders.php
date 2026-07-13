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
const AXISMUNDI_MEDIA_FOLDER_TIER_META = '_ax_media_folder_tier';
const AXISMUNDI_MEDIA_FOLDER_EFFECTIVE_TIER_META = '_ax_media_folder_effective_tier_rank';
const AXISMUNDI_MEDIA_FOLDER_ACCESS_META = '_ax_media_folder_access';
const AXISMUNDI_MEDIA_FOLDER_PASSWORD_META = '_ax_media_folder_password_hash';
const AXISMUNDI_MEDIA_FOLDER_EFFECTIVE_GATE_META = '_ax_media_folder_effective_gated';
const AXISMUNDI_MEDIA_FOLDER_OWNER_REPAIR_VERSION = 1;
const AXISMUNDI_MEDIA_FOLDER_TIER_CACHE_VERSION = 2;

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
 * Convert a visibility tier to its narrowness rank.
 *
 * @param string $tier Tier name.
 * @return int public=0, unlisted=1, private=2.
 */
function axismundi_media_visibility_rank( string $tier ) : int {
	return array_search( $tier, array( 'public', 'unlisted', 'private' ), true ) ?: 0;
}

/**
 * Convert a narrowness rank back to a visibility tier.
 *
 * @param int $rank Rank.
 * @return string
 */
function axismundi_media_visibility_from_rank( int $rank ) : string {
	return array( 'public', 'unlisted', 'private' )[ max( 0, min( 2, $rank ) ) ];
}

/**
 * A folder's own tier policy. Missing/invalid values inherit from the parent.
 *
 * @param int $term_id Folder term ID.
 * @return string inherit|public|unlisted|private
 */
function axismundi_media_folder_tier( int $term_id ) : string {
	$tier = (string) get_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_TIER_META, true );
	return in_array( $tier, array( 'inherit', 'public', 'unlisted', 'private' ), true ) ? $tier : 'inherit';
}

/**
 * A folder's authored access policy.
 *
 * @param int $term_id Folder term ID.
 * @return string open|password
 */
function axismundi_media_folder_access( int $term_id ) : string {
	return 'password' === get_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_ACCESS_META, true ) ? 'password' : 'open';
}

/**
 * Whether any folder in the chain requires a password.
 *
 * @param int $term_id Folder term ID.
 * @return bool
 */
function axismundi_media_calculate_folder_effective_gate( int $term_id ) : bool {
	$visited = array();
	while ( $term_id > 0 && ! isset( $visited[ $term_id ] ) ) {
		$visited[ $term_id ] = true;
		if ( 'password' === axismundi_media_folder_access( $term_id ) ) {
			return true;
		}
		$term = get_term( $term_id, AXISMUNDI_MEDIA_FOLDER_TAX );
		$term_id = $term instanceof WP_Term ? (int) $term->parent : 0;
	}
	return false;
}

/**
 * Cached effective password-gate state, repaired lazily when absent.
 *
 * @param int $term_id Folder term ID.
 * @return bool
 */
function axismundi_media_folder_effective_gate( int $term_id ) : bool {
	if ( $term_id <= 0 ) {
		return false;
	}
	$cached = get_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_EFFECTIVE_GATE_META, true );
	if ( in_array( (string) $cached, array( '0', '1' ), true ) ) {
		return '1' === (string) $cached;
	}
	$gated = axismundi_media_calculate_folder_effective_gate( $term_id );
	update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_EFFECTIVE_GATE_META, $gated ? 1 : 0 );
	return $gated;
}

/**
 * Calculate a folder's effective tier rank by walking its parent chain.
 *
 * This is the authoritative PHP resolver. The derived term meta written by the
 * refresh function below makes the same result available to collection SQL.
 *
 * @param int $term_id Folder term ID.
 * @return int
 */
function axismundi_media_calculate_folder_effective_tier_rank( int $term_id ) : int {
	$rank    = 0;
	$visited = array();
	while ( $term_id > 0 && ! isset( $visited[ $term_id ] ) ) {
		$visited[ $term_id ] = true;
		$tier = axismundi_media_folder_tier( $term_id );
		if ( 'inherit' !== $tier ) {
			$rank = max( $rank, axismundi_media_visibility_rank( $tier ) );
		}
		$term = get_term( $term_id, AXISMUNDI_MEDIA_FOLDER_TAX );
		$term_id = $term instanceof WP_Term ? (int) $term->parent : 0;
	}
	return $rank;
}

/**
 * The cached effective rank, repairing a missing/invalid cache lazily.
 *
 * @param int $term_id Folder term ID.
 * @return int
 */
function axismundi_media_folder_effective_tier_rank( int $term_id ) : int {
	if ( $term_id <= 0 ) {
		return 0;
	}
	$cached = get_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_EFFECTIVE_TIER_META, true );
	if ( in_array( (string) $cached, array( '0', '1', '2' ), true ) ) {
		return (int) $cached;
	}
	$rank = axismundi_media_calculate_folder_effective_tier_rank( $term_id );
	update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_EFFECTIVE_TIER_META, $rank );
	return $rank;
}

/**
 * Recalculate one folder and, optionally, every descendant.
 *
 * @param int  $term_id            Folder term ID.
 * @param bool $include_descendants Refresh the subtree.
 * @return void
 */
function axismundi_media_refresh_folder_effective_tier( int $term_id, bool $include_descendants = true ) : void {
	if ( $term_id <= 0 || ! term_exists( $term_id, AXISMUNDI_MEDIA_FOLDER_TAX ) ) {
		return;
	}
	update_term_meta(
		$term_id,
		AXISMUNDI_MEDIA_FOLDER_EFFECTIVE_TIER_META,
		axismundi_media_calculate_folder_effective_tier_rank( $term_id )
	);
	update_term_meta(
		$term_id,
		AXISMUNDI_MEDIA_FOLDER_EFFECTIVE_GATE_META,
		axismundi_media_calculate_folder_effective_gate( $term_id ) ? 1 : 0
	);
	if ( ! $include_descendants ) {
		return;
	}
	foreach ( (array) get_term_children( $term_id, AXISMUNDI_MEDIA_FOLDER_TAX ) as $child_id ) {
		update_term_meta(
			(int) $child_id,
			AXISMUNDI_MEDIA_FOLDER_EFFECTIVE_TIER_META,
			axismundi_media_calculate_folder_effective_tier_rank( (int) $child_id )
		);
		update_term_meta(
			(int) $child_id,
			AXISMUNDI_MEDIA_FOLDER_EFFECTIVE_GATE_META,
			axismundi_media_calculate_folder_effective_gate( (int) $child_id ) ? 1 : 0
		);
	}
}

/**
 * Keep the derived subtree cache correct when another integration updates a
 * folder's parent through the taxonomy API.
 *
 * @param int $term_id Updated term ID.
 * @return void
 */
function axismundi_media_folder_term_edited( int $term_id ) : void {
	axismundi_media_refresh_folder_effective_tier( $term_id );
}
add_action( 'edited_' . AXISMUNDI_MEDIA_FOLDER_TAX, 'axismundi_media_folder_term_edited', 10, 1 );

/**
 * Give folders created through the taxonomy API (rather than our service) a
 * safe authored default and a collection-query cache immediately.
 *
 * @param int $term_id Created term ID.
 * @return void
 */
function axismundi_media_folder_term_created( int $term_id ) : void {
	if ( '' === (string) get_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_TIER_META, true ) ) {
		update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_TIER_META, 'inherit' );
	}
	if ( '' === (string) get_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_ACCESS_META, true ) ) {
		update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_ACCESS_META, 'open' );
	}
	axismundi_media_refresh_folder_effective_tier( $term_id, false );
}
add_action( 'created_' . AXISMUNDI_MEDIA_FOLDER_TAX, 'axismundi_media_folder_term_created', 10, 1 );

/**
 * Keep the cache correct when an integration writes the authored tier meta
 * directly instead of using axismundi_media_set_folder_tier().
 *
 * @param int    $meta_id Meta row ID.
 * @param int    $term_id Term ID.
 * @param string $meta_key Meta key.
 * @return void
 */
function axismundi_media_folder_tier_meta_changed( int $meta_id, int $term_id, string $meta_key ) : void {
	if ( in_array( $meta_key, array( AXISMUNDI_MEDIA_FOLDER_TIER_META, AXISMUNDI_MEDIA_FOLDER_ACCESS_META ), true ) ) {
		axismundi_media_refresh_folder_effective_tier( $term_id );
	}
}
add_action( 'added_term_meta', 'axismundi_media_folder_tier_meta_changed', 10, 3 );
add_action( 'updated_term_meta', 'axismundi_media_folder_tier_meta_changed', 10, 3 );

/**
 * Set a folder's own tier and refresh its derived subtree.
 *
 * @param int      $term_id Folder term ID.
 * @param string   $tier    inherit|public|unlisted|private.
 * @param int|null $user_id Acting user.
 * @return int|WP_Error Effective rank or error.
 */
function axismundi_media_set_folder_tier( int $term_id, string $tier, ?int $user_id = null ) {
	$user_id = $user_id ?? get_current_user_id();
	if ( axismundi_media_is_root_term( $term_id ) || ! axismundi_media_can_manage_folder( $term_id, $user_id ) ) {
		return new WP_Error( 'ax_media_forbidden', __( 'Not allowed.', 'axismundi-media-library' ), array( 'status' => 403 ) );
	}
	if ( ! in_array( $tier, array( 'inherit', 'public', 'unlisted', 'private' ), true ) ) {
		return new WP_Error( 'ax_media_folder_tier', __( 'Invalid folder visibility.', 'axismundi-media-library' ), array( 'status' => 400 ) );
	}
	update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_TIER_META, $tier );
	axismundi_media_refresh_folder_effective_tier( $term_id );
	return axismundi_media_folder_effective_tier_rank( $term_id );
}

/**
 * Set a folder's access gate. Passwords are stored only as WordPress hashes.
 * Passing no password preserves an existing password gate.
 *
 * @param int         $term_id Folder term ID.
 * @param string      $access  open|password.
 * @param string|null $password New plain password, or null to preserve.
 * @param int|null    $user_id Acting user.
 * @return bool|WP_Error
 */
function axismundi_media_set_folder_access( int $term_id, string $access, ?string $password = null, ?int $user_id = null ) {
	$user_id = $user_id ?? get_current_user_id();
	if ( axismundi_media_is_root_term( $term_id ) || ! axismundi_media_can_manage_folder( $term_id, $user_id ) ) {
		return new WP_Error( 'ax_media_forbidden', __( 'Not allowed.', 'axismundi-media-library' ), array( 'status' => 403 ) );
	}
	if ( ! in_array( $access, array( 'open', 'password' ), true ) ) {
		return new WP_Error( 'ax_media_folder_access', __( 'Invalid folder access policy.', 'axismundi-media-library' ), array( 'status' => 400 ) );
	}
	if ( 'open' === $access ) {
		update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_ACCESS_META, 'open' );
		delete_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_PASSWORD_META );
		axismundi_media_refresh_folder_effective_tier( $term_id );
		return true;
	}
	$current_hash = (string) get_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_PASSWORD_META, true );
	if ( null !== $password && '' !== $password ) {
		$current_hash = wp_hash_password( $password );
		update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_PASSWORD_META, $current_hash );
	}
	if ( '' === $current_hash ) {
		return new WP_Error( 'ax_media_folder_password', __( 'A password is required.', 'axismundi-media-library' ), array( 'status' => 400 ) );
	}
	update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_ACCESS_META, 'password' );
	axismundi_media_refresh_folder_effective_tier( $term_id );
	return true;
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
	update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_TIER_META, 'public' );
	update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_EFFECTIVE_TIER_META, 0 );
	update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_ACCESS_META, 'open' );
	update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_EFFECTIVE_GATE_META, 0 );
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
	if ( in_array( sanitize_title( $name ), array( 'page', 'feed' ), true ) ) {
		return new WP_Error( 'ax_media_folder_reserved', __( 'That folder name is reserved for routing.', 'axismundi-media-library' ), array( 'status' => 400 ) );
	}
	if ( $parent > 0 ) {
		if ( ! axismundi_media_can_manage_folder( $parent, $owner ) || axismundi_media_is_root_term( $parent ) ) {
			return new WP_Error( 'ax_media_folder_parent', __( 'Invalid parent folder.', 'axismundi-media-library' ), array( 'status' => 400 ) );
		}
		$actual_parent = $parent;
		// A hierarchy is one owner's namespace. An editor managing another user's
		// tree creates children for that tree, not inside the editor's namespace.
		$owner = axismundi_media_folder_owner( $parent );
	} else {
		$actual_parent = axismundi_media_user_root( $owner );
	}
	$res = wp_insert_term( $name, AXISMUNDI_MEDIA_FOLDER_TAX, array( 'parent' => $actual_parent ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$term_id = (int) $res['term_id'];
	update_term_meta( $term_id, '_ax_media_folder_owner', $owner );
	update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_TIER_META, 'inherit' );
	update_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_ACCESS_META, 'open' );
	axismundi_media_refresh_folder_effective_tier( $term_id, false );
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
	if ( in_array( sanitize_title( $name ), array( 'page', 'feed' ), true ) ) {
		return new WP_Error( 'ax_media_folder_reserved', __( 'That folder name is reserved for routing.', 'axismundi-media-library' ), array( 'status' => 400 ) );
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
		axismundi_media_refresh_folder_effective_tier( (int) $child_id );
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
 * Whether a folder is a valid location for an Attachment.
 *
 * A folder is a location owned by the Attachment author. Administrators may
 * manage another user's folder, but that capability must not create a
 * cross-owner location relation.
 *
 * @param int $attachment_id Attachment ID.
 * @param int $folder_id     Folder term ID.
 * @return bool
 */
function axismundi_media_folder_accepts_attachment( int $attachment_id, int $folder_id ) : bool {
	if ( 'attachment' !== get_post_type( $attachment_id ) || $folder_id <= 0 || axismundi_media_is_root_term( $folder_id ) ) {
		return false;
	}

	return axismundi_media_folder_owner( $folder_id ) === (int) get_post_field( 'post_author', $attachment_id );
}

/**
 * Set the attachment's single folder (0 = unfiled). Enforces the one-folder rule
 * (append = false). No permission check here — callers gate it.
 *
 * @param int $attachment_id Attachment ID.
 * @param int $folder_id     Folder term ID or 0.
 * @return bool Whether the requested location was stored.
 */
function axismundi_media_set_attachment_folder( int $attachment_id, int $folder_id ) : bool {
	if ( 'attachment' !== get_post_type( $attachment_id ) ) {
		return false;
	}
	if ( $folder_id > 0 && ! axismundi_media_folder_accepts_attachment( $attachment_id, $folder_id ) ) {
		return false;
	}

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

	return true;
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
		if (
			'attachment' !== get_post_type( $aid )
			|| ! user_can( $user_id, 'edit_post', $aid )
			|| ( $folder_id > 0 && ! axismundi_media_folder_accepts_attachment( $aid, $folder_id ) )
		) {
			$denied[] = $aid;
			continue;
		}
		if ( axismundi_media_set_attachment_folder( $aid, $folder_id ) ) {
			$moved[] = $aid;
		} else {
			$denied[] = $aid;
		}
	}
	return array(
		'moved'  => $moved,
		'denied' => $denied,
	);
}

/**
 * Repair cross-owner, hidden-root, or multi-folder relations created before the
 * attachment-author ownership invariant was enforced.
 *
 * Invalid locations are normalized to Unfiled. A bounded batch runs on each
 * admin request so large libraries do not block an update request.
 *
 * @return void
 */
function axismundi_media_repair_folder_owner_relations() : void {
	if ( (int) get_option( 'ax_media_folder_owner_repair_version', 0 ) >= AXISMUNDI_MEDIA_FOLDER_OWNER_REPAIR_VERSION ) {
		return;
	}

	global $wpdb;
	$limit = 100;
	$sql   = $wpdb->prepare(
		"SELECT p.ID
		FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
		INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
		LEFT JOIN {$wpdb->termmeta} owner_meta ON owner_meta.term_id = tt.term_id AND owner_meta.meta_key = '_ax_media_folder_owner'
		LEFT JOIN {$wpdb->termmeta} root_meta ON root_meta.term_id = tt.term_id AND root_meta.meta_key = '_ax_media_folder_root'
		WHERE p.post_type = 'attachment' AND tt.taxonomy = %s
		GROUP BY p.ID, p.post_author
		HAVING COUNT(*) <> 1
			OR MAX(CASE WHEN root_meta.meta_id IS NOT NULL OR CAST(COALESCE(owner_meta.meta_value, '0') AS UNSIGNED) <> p.post_author THEN 1 ELSE 0 END) = 1
		LIMIT %d",
		AXISMUNDI_MEDIA_FOLDER_TAX,
		$limit
	);
	$ids   = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded one-time invariant repair.

	if ( ! empty( $wpdb->last_error ) ) {
		return;
	}

	foreach ( $ids as $attachment_id ) {
		axismundi_media_set_attachment_folder( (int) $attachment_id, 0 );
	}

	if ( count( $ids ) < $limit ) {
		update_option( 'ax_media_folder_owner_repair_version', AXISMUNDI_MEDIA_FOLDER_OWNER_REPAIR_VERSION, false );
	}
}
add_action( 'admin_init', 'axismundi_media_repair_folder_owner_relations', 30 );

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
 * Count only Attachments visible to the current viewer in a folder subtree.
 * Front-end folder tiles must not disclose raw private/unlisted item counts.
 *
 * @param int  $term_id         Folder term ID.
 * @param int  $max_rank        Maximum public visibility rank for this route.
 * @param bool $include_children Include descendants.
 * @return int
 */
function axismundi_media_folder_visible_count( int $term_id, int $max_rank = 0, bool $include_children = true ) : int {
	$query = new WP_Query(
		array(
			'post_type'                     => 'attachment',
			'post_status'                   => 'inherit',
			'posts_per_page'                => 1,
			'fields'                        => 'ids',
			'no_found_rows'                 => false,
			'author'                        => axismundi_media_folder_owner( $term_id ),
			'ax_media_visibility_filter'    => true,
			'ax_media_visibility_max_rank'  => min( 1, max( 0, $max_rank ) ),
			'tax_query'                     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Explicit folder count scope.
				array(
					'taxonomy'         => AXISMUNDI_MEDIA_FOLDER_TAX,
					'field'            => 'term_id',
					'terms'            => array( $term_id ),
					'include_children' => $include_children,
				),
			),
		)
	);
	return (int) $query->found_posts;
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
			'tier'            => axismundi_media_folder_tier( (int) $term->term_id ),
			'effective_tier'  => axismundi_media_visibility_from_rank( axismundi_media_folder_effective_tier_rank( (int) $term->term_id ) ),
			'access'          => axismundi_media_folder_access( (int) $term->term_id ),
			'effective_gate'  => axismundi_media_folder_effective_gate( (int) $term->term_id ),
		);
	}
	return $out;
}

/**
 * A user's folders in hierarchy order with indented labels.
 *
 * @param int $user_id User ID.
 * @return array<int,array<string,mixed>>
 */
function axismundi_media_user_folder_options( int $user_id ) : array {
	$folders   = axismundi_media_user_folders( $user_id );
	$by_parent = array();
	foreach ( $folders as $folder ) {
		$by_parent[ (int) $folder['parent'] ][] = $folder;
	}

	$options = array();
	$walk    = static function ( int $parent, int $depth ) use ( &$walk, &$options, $by_parent ) : void {
		foreach ( $by_parent[ $parent ] ?? array() as $folder ) {
			$folder['label'] = str_repeat( '— ', $depth ) . $folder['name'];
			$options[]       = $folder;
			$walk( (int) $folder['id'], $depth + 1 );
		}
	};
	$walk( 0, 0 );

	return $options;
}

/**
 * One-time cache backfill for folders created before derived ranks existed.
 *
 * @return void
 */
function axismundi_media_backfill_folder_tier_cache() : void {
	if ( (int) get_option( 'ax_media_folder_tier_cache_version', 0 ) >= AXISMUNDI_MEDIA_FOLDER_TIER_CACHE_VERSION ) {
		return;
	}
	$roots = get_terms(
		array(
			'taxonomy'   => AXISMUNDI_MEDIA_FOLDER_TAX,
			'hide_empty' => false,
			'parent'     => 0,
			'fields'     => 'ids',
		)
	);
	if ( ! is_wp_error( $roots ) ) {
		foreach ( $roots as $root_id ) {
			if ( '' === (string) get_term_meta( (int) $root_id, AXISMUNDI_MEDIA_FOLDER_TIER_META, true ) ) {
				update_term_meta( (int) $root_id, AXISMUNDI_MEDIA_FOLDER_TIER_META, axismundi_media_is_root_term( (int) $root_id ) ? 'public' : 'inherit' );
			}
			if ( '' === (string) get_term_meta( (int) $root_id, AXISMUNDI_MEDIA_FOLDER_ACCESS_META, true ) ) {
				update_term_meta( (int) $root_id, AXISMUNDI_MEDIA_FOLDER_ACCESS_META, 'open' );
			}
			axismundi_media_refresh_folder_effective_tier( (int) $root_id );
		}
	}
	update_option( 'ax_media_folder_tier_cache_version', AXISMUNDI_MEDIA_FOLDER_TIER_CACHE_VERSION, false );
}
add_action( 'init', 'axismundi_media_backfill_folder_tier_cache', 12 );
