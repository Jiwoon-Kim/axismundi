<?php
/**
 * Phase 2a media-modal folder filter and upload target integration.
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
				'id'    => (int) $folder['id'],
				'label' => str_repeat( '— ', $depth ) . $folder['name'],
			);
			$walk( (int) $folder['id'], $depth + 1 );
		}
	};
	$walk( 0, 0 );
	return $options;
}

/**
 * Add the folder toolbar filter to any admin screen that loads media-views.
 *
 * @return void
 */
function axismundi_media_enqueue_modal_folders() : void {
	if ( ! axismundi_media_is_independent() || ! current_user_can( 'upload_files' ) || ! wp_script_is( 'media-views', 'registered' ) ) {
		return;
	}
	wp_localize_script(
		'media-views',
		'axMediaFolders',
		array(
			'all'       => __( 'All media', 'axismundi-media-library' ),
			'unfiled'   => __( 'Unfiled', 'axismundi-media-library' ),
			'label'     => __( 'Filter by media folder', 'axismundi-media-library' ),
			'folders'   => axismundi_media_modal_folder_options(),
		)
	);
	$script = file_get_contents( __DIR__ . '/../assets/media-folders.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bundled asset.
	if ( false !== $script ) {
		wp_add_inline_script( 'media-views', $script, 'after' );
	}
}
add_action( 'admin_enqueue_scripts', 'axismundi_media_enqueue_modal_folders', 20 );

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
	if ( $folder <= 0 || axismundi_media_is_root_term( $folder ) || ! axismundi_media_can_manage_folder( $folder ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
		return;
	}
	update_post_meta( $attachment_id, '_ax_media_visibility', 'inherit' );
	axismundi_media_set_attachment_folder( $attachment_id, $folder );
}
add_action( 'add_attachment', 'axismundi_media_assign_uploaded_folder', 20 );
