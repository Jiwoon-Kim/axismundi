<?php
/**
 * Phase 2 media-library tree, modal filter, and upload target integration.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/**
 * Folder options for the current user's media picker.
 *
 * @return array<int,array{id:int,label:string}>
 */
function axismundi_media_modal_folder_options() : array {
	$folders = axismundi_media_user_folders( get_current_user_id() );
	$by_parent = array();
	foreach ( $folders as $folder ) {
		$by_parent[ (int) $folder['parent'] ][] = $folder;
	}
	$options = array();
	$walk = static function ( int $parent, int $depth ) use ( &$walk, &$options, $by_parent ) : void {
		foreach ( $by_parent[ $parent ] ?? array() as $folder ) {
			$options[] = array(
				'id'              => (int) $folder['id'],
				'name'            => (string) $folder['name'],
				'label'           => str_repeat( '— ', $depth ) . $folder['name'],
				'parent'          => (int) $folder['parent'],
				'count'           => (int) $folder['count'],
				'recursive_count' => (int) $folder['recursive_count'],
				'protected'       => ! empty( $folder['effective_gate'] ),
			);
			$walk( (int) $folder['id'], $depth + 1 );
		}
	};
	$walk( 0, 0 );
	return $options;
}

/**
 * The upload.php view mode ('grid' or 'list'). The `mode` query arg wins; otherwise
 * the saved per-user preference, defaulting to grid.
 *
 * @return string
 */
function axismundi_media_upload_mode() : string {
	$requested = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view preference, no state change.
	if ( 'list' === $requested ) {
		return 'list';
	}
	if ( '' === $requested && 'list' === get_user_option( 'media_library_mode' ) ) {
		return 'list';
	}
	return 'grid';
}

/**
 * Enqueue the folder sidebar + toolbar filter on the Media Library screen (grid and
 * list) and on media-picker screens (post editor). The #wpbody sidebar renders only
 * on upload.php (JS gates on the body class); the toolbar dropdown backs the grid
 * and modal query.
 *
 * @return void
 */
function axismundi_media_enqueue_modal_folders() : void {
	if ( ! axismundi_media_is_independent() || ! current_user_can( 'upload_files' ) ) {
		return;
	}
	$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$is_upload = $screen && 'upload' === $screen->id;
	$has_media = wp_script_is( 'media-views', 'registered' );
	if ( ! $is_upload && ! $has_media ) {
		return;
	}

	$mode = $is_upload ? axismundi_media_upload_mode() : 'grid';
	// List mode has no media picker, so jQuery is enough; grid/editor need
	// media-views for the toolbar dropdown integration.
	$deps = ( $is_upload && 'list' === $mode ) ? array( 'jquery' ) : array( 'media-views' );

	$base = dirname( __DIR__ ) . '/axismundi-media-library.php';
	$js   = dirname( __DIR__ ) . '/assets/media-folders.js';
	$css  = dirname( __DIR__ ) . '/assets/media-folders.css';

	wp_enqueue_script(
		'axismundi-media-folders',
		plugins_url( 'assets/media-folders.js', $base ),
		$deps,
		file_exists( $js ) ? (string) filemtime( $js ) : false,
		true
	);
	wp_localize_script(
		'axismundi-media-folders',
		'axMediaFolders',
		array(
			'mode'        => $mode,
			'listBaseUrl' => admin_url( 'upload.php' ),
			'all'         => __( 'All media', 'axismundi-media-library' ),
			'unfiled'     => __( 'Unfiled', 'axismundi-media-library' ),
			'label'       => __( 'Filter by media folder', 'axismundi-media-library' ),
			'treeTitle'   => __( 'Media folders', 'axismundi-media-library' ),
			'breadcrumbLabel' => __( 'Folder path', 'axismundi-media-library' ),
			'manage'      => __( 'Manage folders', 'axismundi-media-library' ),
			'manageUrl'   => axismundi_media_folders_admin_url(),
			'items'       => __( 'items', 'axismundi-media-library' ),
			'folders'     => axismundi_media_modal_folder_options(),
		)
	);
	wp_enqueue_style(
		'axismundi-media-folders',
		plugins_url( 'assets/media-folders.css', $base ),
		array( 'dashicons' ),
		// filemtime so every CSS edit busts the browser cache (a static string left
		// stale styles cached across releases).
		file_exists( $css ) ? (string) filemtime( $css ) : false
	);
}
add_action( 'admin_enqueue_scripts', 'axismundi_media_enqueue_modal_folders', 20 );

/**
 * Filter the list-mode Media Library table by the selected folder (grid mode uses
 * the ajax query filter below instead). Owner/editor rights are enforced; an
 * unauthorized or invalid folder yields an empty result rather than leaking.
 *
 * @param WP_Query $query Main query.
 * @return void
 */
function axismundi_media_list_folder_filter( WP_Query $query ) : void {
	if ( ! is_admin() || ! axismundi_media_is_independent() || ! $query->is_main_query() ) {
		return;
	}
	if ( 'upload.php' !== ( $GLOBALS['pagenow'] ?? '' ) || 'attachment' !== $query->get( 'post_type' ) ) {
		return;
	}
	if ( ! isset( $_GET['ax_media_folder'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin folder filter.
		return;
	}
	$folder = (int) $_GET['ax_media_folder']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 0 === $folder ) {
		$query->set( 'tax_query', array( array( 'taxonomy' => AXISMUNDI_MEDIA_FOLDER_TAX, 'operator' => 'NOT EXISTS' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		return;
	}
	if ( $folder > 0 && ! axismundi_media_is_root_term( $folder ) && axismundi_media_can_manage_folder( $folder ) ) {
		$query->set( 'tax_query', array( array( 'taxonomy' => AXISMUNDI_MEDIA_FOLDER_TAX, 'field' => 'term_id', 'terms' => array( $folder ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	} else {
		$query->set( 'post__in', array( 0 ) );
	}
}
add_action( 'pre_get_posts', 'axismundi_media_list_folder_filter' );

/**
 * Apply the selected folder to the media modal's scoped attachment query.
 *
 * @param array<string,mixed> $args Attachment query args.
 * @return array<string,mixed>
 */
function axismundi_media_modal_folder_query( array $args ) : array {
	if ( ! axismundi_media_is_independent() || ! isset( $_REQUEST['query'] ) || ! is_array( $_REQUEST['query'] ) || ! isset( $_REQUEST['query']['ax_media_folder'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core query-attachments request verifies its own nonce; read-only filter.
		return $args;
	}
	$requested = sanitize_text_field( wp_unslash( $_REQUEST['query']['ax_media_folder'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'all' === $requested ) {
		$folder = -1;
	} elseif ( 'unfiled' === $requested ) {
		$folder = 0;
	} elseif ( str_starts_with( $requested, 'folder-' ) ) {
		$folder = absint( substr( $requested, 7 ) );
	} else {
		$folder = (int) $requested;
	}
	update_user_meta( get_current_user_id(), '_ax_media_active_folder', max( 0, $folder ) );
	if ( -1 === $folder ) {
		return $args;
	}
	$clause = array(
		'taxonomy' => AXISMUNDI_MEDIA_FOLDER_TAX,
		'operator' => 'NOT EXISTS',
	);
	if ( $folder > 0 ) {
		if ( axismundi_media_is_root_term( $folder ) || ! axismundi_media_can_manage_folder( $folder ) ) {
			$args['post__in'] = array( 0 );
			return $args;
		}
		$clause = array(
			'taxonomy' => AXISMUNDI_MEDIA_FOLDER_TAX,
			'field'    => 'term_id',
			'terms'    => array( $folder ),
		);
	}
	$tax_query = isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ? $args['tax_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Explicit user-selected media folder.
	$tax_query[] = $clause;
	$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Explicit user-selected media folder.
	return $args;
}
add_filter( 'ajax_query_attachments_args', 'axismundi_media_modal_folder_query', 20 );

/**
 * Assign a newly uploaded Attachment to the selected folder. Uploading directly
 * into a folder explicitly opts the new item into folder visibility inheritance.
 *
 * @param int $attachment_id Attachment ID.
 * @return void
 */
function axismundi_media_assign_uploaded_folder( int $attachment_id ) : void {
	if ( ! axismundi_media_is_independent() ) {
		return;
	}
	$folder = isset( $_REQUEST['ax_media_folder'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- Core upload request verifies its own upload nonce.
		? absint( $_REQUEST['ax_media_folder'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended
		: absint( get_user_meta( get_current_user_id(), '_ax_media_active_folder', true ) );
	if (
		$folder <= 0
		|| ! axismundi_media_folder_accepts_attachment( $attachment_id, $folder )
		|| ! axismundi_media_can_manage_folder( $folder )
		|| ! current_user_can( 'edit_post', $attachment_id )
	) {
		return;
	}
	update_post_meta( $attachment_id, '_ax_media_visibility', 'inherit' );
	if ( ! axismundi_media_set_attachment_folder( $attachment_id, $folder ) ) {
		return;
	}
	// Snapshot the folder default license onto the new upload (Phase 4b). Later
	// moves never re-stamp, and an attachment-set license is never overwritten.
	if ( function_exists( 'axismundi_media_stamp_folder_default_license' ) ) {
		axismundi_media_stamp_folder_default_license( $attachment_id, $folder );
	}
}
add_action( 'add_attachment', 'axismundi_media_assign_uploaded_folder', 20 );
