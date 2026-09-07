<?php
/**
 * FileBird CSV compatibility importer.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_MEDIA_FILEBIRD_SOURCE_META    = '_ax_media_import_source';
const AXISMUNDI_MEDIA_FILEBIRD_SOURCE_ID_META = '_ax_media_import_source_id';
const AXISMUNDI_MEDIA_FILEBIRD_JOB_TTL        = 6 * HOUR_IN_SECONDS;

/**
 * Register the importer beneath Media.
 *
 * @return void
 */
function axismundi_media_register_filebird_import_page() : void {
	if ( ! axismundi_media_is_independent() || ! current_user_can( 'upload_files' ) ) {
		return;
	}
	add_media_page(
		__( 'Import Media Folders', 'axismundi-media-library' ),
		__( 'Import Folders', 'axismundi-media-library' ),
		'upload_files',
		'axismundi-media-filebird-import',
		'axismundi_media_render_filebird_import_page'
	);
}
add_action( 'admin_menu', 'axismundi_media_register_filebird_import_page' );

/**
 * Build a transient key without exposing the supplied token as an option name.
 *
 * @param string $token Import token.
 * @return string
 */
function axismundi_media_filebird_job_key( string $token ) : string {
	return 'ax_media_fb_' . md5( $token );
}

/**
 * Read one import job owned by the current user.
 *
 * @param string $token Import token.
 * @param int    $user_id Expected owner.
 * @return array<string,mixed>|false
 */
function axismundi_media_filebird_get_job( string $token, int $user_id ) {
	if ( ! preg_match( '/^[a-f0-9-]{36}$/', $token ) ) {
		return false;
	}
	$job = get_transient( axismundi_media_filebird_job_key( $token ) );
	if ( ! is_array( $job ) || (int) ( $job['user_id'] ?? 0 ) !== $user_id ) {
		return false;
	}
	return $job;
}

/**
 * Save an import job.
 *
 * @param string              $token Import token.
 * @param array<string,mixed> $job   Job state.
 * @return bool
 */
function axismundi_media_filebird_save_job( string $token, array $job ) : bool {
	return set_transient( axismundi_media_filebird_job_key( $token ), $job, AXISMUNDI_MEDIA_FILEBIRD_JOB_TTL );
}

/**
 * Parse and normalize a FileBird CSV export.
 *
 * Uploaded files are read from their temporary location and are never persisted.
 * Duplicate attachment IDs are counted and the first assignment wins, preserving
 * Axismundi's one-folder invariant.
 *
 * @param string $path Temporary CSV path.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_media_parse_filebird_csv( string $path ) {
	if ( ! is_readable( $path ) ) {
		return new WP_Error( 'ax_media_filebird_unreadable', __( 'The CSV file could not be read.', 'axismundi-media-library' ) );
	}
	$size = filesize( $path );
	if ( false === $size || $size > 2 * MB_IN_BYTES ) {
		return new WP_Error( 'ax_media_filebird_size', __( 'The CSV file must be smaller than 2 MB.', 'axismundi-media-library' ) );
	}

	$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Parsing an administrator-uploaded temporary CSV.
	if ( false === $handle ) {
		return new WP_Error( 'ax_media_filebird_unreadable', __( 'The CSV file could not be opened.', 'axismundi-media-library' ) );
	}

	try {
		$header = fgetcsv( $handle, 0, ',', '"', '\\' );
		if ( ! is_array( $header ) ) {
			return new WP_Error( 'ax_media_filebird_header', __( 'The CSV file is empty.', 'axismundi-media-library' ) );
		}
		$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] );
		$header    = array_map( 'trim', $header );
		$columns   = array_flip( $header );
		foreach ( array( 'id', 'name', 'parent', 'attachment_ids' ) as $required ) {
			if ( ! isset( $columns[ $required ] ) ) {
				return new WP_Error(
					'ax_media_filebird_header',
					sprintf( /* translators: %s: missing CSV column. */ __( 'Missing required FileBird column: %s.', 'axismundi-media-library' ), $required )
				);
			}
		}

		$folders       = array();
		$attachment_to = array();
		$duplicates    = 0;
		$row_number    = 1;

		while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) ) {
			++$row_number;
			if ( array( null ) === $row || empty( array_filter( $row, static fn( $value ) => '' !== trim( (string) $value ) ) ) ) {
				continue;
			}
			$source_id = absint( $row[ $columns['id'] ] ?? 0 );
			$name      = sanitize_text_field( (string) ( $row[ $columns['name'] ] ?? '' ) );
			$parent    = absint( $row[ $columns['parent'] ] ?? 0 );
			$order     = isset( $columns['ord'] ) ? (int) ( $row[ $columns['ord'] ] ?? 0 ) : 0;
			if ( $source_id <= 0 || '' === $name ) {
				return new WP_Error(
					'ax_media_filebird_row',
					sprintf( /* translators: %d: CSV row number. */ __( 'Invalid folder data on CSV row %d.', 'axismundi-media-library' ), $row_number )
				);
			}
			if ( isset( $folders[ $source_id ] ) ) {
				return new WP_Error( 'ax_media_filebird_duplicate_folder', __( 'The CSV contains duplicate folder IDs.', 'axismundi-media-library' ) );
			}
			$folders[ $source_id ] = array(
				'id'     => $source_id,
				'name'   => $name,
				'parent' => $parent,
				'order'  => $order,
			);

			$raw_ids = trim( (string) ( $row[ $columns['attachment_ids'] ] ?? '' ) );
			if ( '' === $raw_ids ) {
				continue;
			}
			foreach ( explode( '|', $raw_ids ) as $raw_id ) {
				$attachment_id = absint( $raw_id );
				if ( $attachment_id <= 0 ) {
					continue;
				}
				if ( isset( $attachment_to[ $attachment_id ] ) ) {
					if ( $attachment_to[ $attachment_id ] !== $source_id ) {
						++$duplicates;
					}
					continue;
				}
				$attachment_to[ $attachment_id ] = $source_id;
			}
		}
	} finally {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Paired with temporary CSV fopen above.
	}

	if ( empty( $folders ) ) {
		return new WP_Error( 'ax_media_filebird_empty', __( 'No FileBird folders were found.', 'axismundi-media-library' ) );
	}

	$order = axismundi_media_filebird_folder_order( $folders );
	if ( is_wp_error( $order ) ) {
		return $order;
	}

	$assignments = array();
	foreach ( $attachment_to as $attachment_id => $source_id ) {
		$assignments[] = array(
			'attachment_id' => (int) $attachment_id,
			'folder_id'     => (int) $source_id,
		);
	}

	return array(
		'folders'               => $folders,
		'folder_order'          => $order,
		'assignments'           => $assignments,
		'duplicate_assignments' => $duplicates,
	);
}

/**
 * Topologically order FileBird folders, retaining the export order among peers.
 *
 * @param array<int,array<string,mixed>> $folders Folder records keyed by source ID.
 * @return int[]|WP_Error
 */
function axismundi_media_filebird_folder_order( array $folders ) {
	foreach ( $folders as $folder ) {
		$parent = (int) $folder['parent'];
		if ( $parent > 0 && ! isset( $folders[ $parent ] ) ) {
			return new WP_Error(
				'ax_media_filebird_parent',
				sprintf( /* translators: %d: missing parent folder ID. */ __( 'The CSV references missing parent folder %d.', 'axismundi-media-library' ), $parent )
			);
		}
	}

	$remaining = $folders;
	$order     = array();
	$added     = array();
	while ( ! empty( $remaining ) ) {
		uasort(
			$remaining,
			static fn( $left, $right ) => array( (int) $left['order'], (int) $left['id'] ) <=> array( (int) $right['order'], (int) $right['id'] )
		);
		$progress = false;
		foreach ( $remaining as $source_id => $folder ) {
			$parent = (int) $folder['parent'];
			if ( 0 !== $parent && ! isset( $added[ $parent ] ) ) {
				continue;
			}
			$order[]             = (int) $source_id;
			$added[ $source_id ] = true;
			unset( $remaining[ $source_id ] );
			$progress = true;
		}
		if ( ! $progress ) {
			return new WP_Error( 'ax_media_filebird_cycle', __( 'The CSV folder hierarchy contains a cycle.', 'axismundi-media-library' ) );
		}
	}
	return $order;
}

/**
 * Create a resumable import job from normalized CSV data.
 *
 * @param array<string,mixed> $parsed  Parsed data.
 * @param int                 $user_id Importing user and folder owner.
 * @return array<string,mixed>
 */
function axismundi_media_filebird_create_job( array $parsed, int $user_id ) : array {
	return array(
		'version'          => 1,
		'user_id'          => $user_id,
		'status'           => 'ready',
		'created_at'       => time(),
		'folders'          => $parsed['folders'],
		'folder_order'     => $parsed['folder_order'],
		'assignments'      => $parsed['assignments'],
		'folder_map'       => array(),
		'folder_index'     => 0,
		'assignment_index' => 0,
		'errors'           => array(),
		'stats'            => array(
			'folders_total'          => count( $parsed['folder_order'] ),
			'assignments_total'      => count( $parsed['assignments'] ),
			'duplicate_assignments'  => (int) $parsed['duplicate_assignments'],
			'folders_created'        => 0,
			'folders_reused'         => 0,
			'attachments_assigned'   => 0,
			'attachments_already'    => 0,
			'attachments_missing'    => 0,
			'attachments_denied'     => 0,
			'attachments_preserved'  => 0,
		),
	);
}

/**
 * Find a prior folder created from the same FileBird source record.
 *
 * @param int $owner     Axismundi folder owner.
 * @param int $source_id FileBird folder ID.
 * @return int
 */
function axismundi_media_filebird_find_folder( int $owner, int $source_id ) : int {
	$terms = get_terms(
		array(
			'taxonomy'   => AXISMUNDI_MEDIA_FOLDER_TAX,
			'hide_empty' => false,
			'number'     => 1,
			'fields'     => 'ids',
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Small, administrator-initiated compatibility lookup.
				'relation' => 'AND',
				array(
					'key'   => AXISMUNDI_MEDIA_FILEBIRD_SOURCE_META,
					'value' => 'filebird',
				),
				array(
					'key'   => AXISMUNDI_MEDIA_FILEBIRD_SOURCE_ID_META,
					'value' => (string) $source_id,
				),
				array(
					'key'   => '_ax_media_folder_owner',
					'value' => (string) $owner,
				),
			),
		)
	);
	return ! is_wp_error( $terms ) && ! empty( $terms ) ? (int) $terms[0] : 0;
}

/**
 * Append a bounded diagnostic message to a job.
 *
 * @param array<string,mixed> $job     Job state.
 * @param string              $message Message.
 * @return void
 */
function axismundi_media_filebird_job_error( array &$job, string $message ) : void {
	if ( count( $job['errors'] ) < 50 ) {
		$job['errors'][] = $message;
	}
}

/**
 * Process one bounded importer batch.
 *
 * Existing Axismundi assignments are preserved. A rerun recognizes source-tagged
 * folders and attachments already in the intended imported folder.
 *
 * @param array<string,mixed> $job              Job state, updated by reference.
 * @param int                 $folder_limit     Folder operations per request.
 * @param int                 $assignment_limit Attachment operations per request.
 * @return void
 */
function axismundi_media_filebird_process_job( array &$job, int $folder_limit = 10, int $assignment_limit = 100 ) : void {
	$user_id       = (int) $job['user_id'];
	$folder_limit  = max( 1, min( 50, $folder_limit ) );
	$assign_limit  = max( 1, min( 250, $assignment_limit ) );
	$job['status'] = 'running';

	$folder_processed = 0;
	while ( $job['folder_index'] < count( $job['folder_order'] ) && $folder_processed < $folder_limit ) {
		$source_id = (int) $job['folder_order'][ $job['folder_index'] ];
		$folder    = $job['folders'][ $source_id ];
		$term_id   = axismundi_media_filebird_find_folder( $user_id, $source_id );
		if ( $term_id > 0 ) {
			$job['folder_map'][ $source_id ] = $term_id;
			++$job['stats']['folders_reused'];
		} else {
			$source_parent = (int) $folder['parent'];
			$parent        = 0;
			if ( $source_parent > 0 ) {
				$parent = (int) ( $job['folder_map'][ $source_parent ] ?? 0 );
				if ( $parent <= 0 ) {
					$job['folder_map'][ $source_id ] = 0;
					axismundi_media_filebird_job_error( $job, sprintf( 'Folder %d skipped because its parent was not imported.', $source_id ) );
					++$job['folder_index'];
					++$folder_processed;
					continue;
				}
			}
			$created = axismundi_media_create_folder( (string) $folder['name'], $parent, $user_id );
			if ( is_wp_error( $created ) ) {
				$job['folder_map'][ $source_id ] = 0;
				axismundi_media_filebird_job_error( $job, sprintf( 'Folder %d: %s', $source_id, $created->get_error_message() ) );
			} else {
				$term_id                              = (int) $created;
				$job['folder_map'][ $source_id ]       = $term_id;
				$job['stats']['folders_created']++;
				update_term_meta( $term_id, AXISMUNDI_MEDIA_FILEBIRD_SOURCE_META, 'filebird' );
				update_term_meta( $term_id, AXISMUNDI_MEDIA_FILEBIRD_SOURCE_ID_META, $source_id );
			}
		}
		++$job['folder_index'];
		++$folder_processed;
	}

	if ( $job['folder_index'] < count( $job['folder_order'] ) ) {
		return;
	}

	$assignment_processed = 0;
	while ( $job['assignment_index'] < count( $job['assignments'] ) && $assignment_processed < $assign_limit ) {
		$assignment   = $job['assignments'][ $job['assignment_index'] ];
		$attachment_id = (int) $assignment['attachment_id'];
		$source_id     = (int) $assignment['folder_id'];
		$target_id     = (int) ( $job['folder_map'][ $source_id ] ?? 0 );

		if ( $target_id <= 0 ) {
			++$job['stats']['attachments_missing'];
		} elseif ( 'attachment' !== get_post_type( $attachment_id ) ) {
			++$job['stats']['attachments_missing'];
		} elseif ( ! user_can( $user_id, 'edit_post', $attachment_id ) ) {
			++$job['stats']['attachments_denied'];
		} else {
			$current = axismundi_media_attachment_folder( $attachment_id );
			if ( $current === $target_id ) {
				++$job['stats']['attachments_already'];
			} elseif ( $current > 0 ) {
				++$job['stats']['attachments_preserved'];
			} else {
				$result = axismundi_media_move_attachments( array( $attachment_id ), $target_id, $user_id );
				if ( is_wp_error( $result ) || empty( $result['moved'] ) ) {
					++$job['stats']['attachments_denied'];
				} else {
					++$job['stats']['attachments_assigned'];
				}
			}
		}

		++$job['assignment_index'];
		++$assignment_processed;
	}

	if ( $job['assignment_index'] >= count( $job['assignments'] ) ) {
		$job['status']       = 'done';
		$job['completed_at'] = time();
	}
}

/**
 * Turn job state into a small response payload.
 *
 * @param array<string,mixed> $job Job state.
 * @return array<string,mixed>
 */
function axismundi_media_filebird_job_response( array $job ) : array {
	$total = count( $job['folder_order'] ) + count( $job['assignments'] );
	$done  = (int) $job['folder_index'] + (int) $job['assignment_index'];
	return array(
		'done'     => 'done' === $job['status'],
		'progress' => $total > 0 ? min( 100, (int) floor( 100 * $done / $total ) ) : 100,
		'processed' => $done,
		'total'    => $total,
		'stats'    => $job['stats'],
		'errors'   => $job['errors'],
	);
}

/**
 * Handle the Analyze CSV form.
 *
 * @return void
 */
function axismundi_media_handle_filebird_analyze() : void {
	if ( ! axismundi_media_is_independent() || ! current_user_can( 'upload_files' ) ) {
		wp_die( esc_html__( 'You cannot import media folders.', 'axismundi-media-library' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'ax_media_filebird_analyze' );
	$file = isset( $_FILES['filebird_csv'] ) && is_array( $_FILES['filebird_csv'] ) ? $_FILES['filebird_csv'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated below and parsed as data, never persisted.
	$name = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
	if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || 'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
		wp_safe_redirect( add_query_arg( 'ax_media_error', rawurlencode( __( 'Choose a valid FileBird CSV export.', 'axismundi-media-library' ) ), axismundi_media_filebird_import_url() ) );
		exit;
	}
	$parsed = axismundi_media_parse_filebird_csv( (string) ( $file['tmp_name'] ?? '' ) );
	if ( is_wp_error( $parsed ) ) {
		wp_safe_redirect( add_query_arg( 'ax_media_error', rawurlencode( $parsed->get_error_message() ), axismundi_media_filebird_import_url() ) );
		exit;
	}

	$token = wp_generate_uuid4();
	$job   = axismundi_media_filebird_create_job( $parsed, get_current_user_id() );
	axismundi_media_filebird_save_job( $token, $job );
	wp_safe_redirect( add_query_arg( 'job', $token, axismundi_media_filebird_import_url() ) );
	exit;
}
add_action( 'admin_post_axismundi_media_filebird_analyze', 'axismundi_media_handle_filebird_analyze' );

/**
 * Process one authenticated AJAX batch.
 *
 * @return void
 */
function axismundi_media_ajax_filebird_import_batch() : void {
	if ( ! axismundi_media_is_independent() || ! current_user_can( 'upload_files' ) ) {
		wp_send_json_error( array( 'message' => __( 'You cannot import media folders.', 'axismundi-media-library' ) ), 403 );
	}
	$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
	check_ajax_referer( 'ax_media_filebird_import_' . $token, 'nonce' );
	$job = axismundi_media_filebird_get_job( $token, get_current_user_id() );
	if ( false === $job ) {
		wp_send_json_error( array( 'message' => __( 'The import job expired. Analyze the CSV again.', 'axismundi-media-library' ) ), 404 );
	}
	if ( 'done' !== $job['status'] ) {
		axismundi_media_filebird_process_job( $job );
		axismundi_media_filebird_save_job( $token, $job );
	}
	wp_send_json_success( axismundi_media_filebird_job_response( $job ) );
}
add_action( 'wp_ajax_axismundi_media_filebird_import_batch', 'axismundi_media_ajax_filebird_import_batch' );

/**
 * Importer admin URL.
 *
 * @param array<string,string|int> $args Query arguments.
 * @return string
 */
function axismundi_media_filebird_import_url( array $args = array() ) : string {
	return add_query_arg( $args, admin_url( 'upload.php?page=axismundi-media-filebird-import' ) );
}

/**
 * Enqueue the importer runner only for its admin screen.
 *
 * @param string $hook_suffix Current admin hook.
 * @return void
 */
function axismundi_media_filebird_import_assets( string $hook_suffix ) : void {
	if ( 'media_page_axismundi-media-filebird-import' !== $hook_suffix ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only selector for a current-user-owned transient.
	$token = isset( $_GET['job'] ) ? sanitize_text_field( wp_unslash( $_GET['job'] ) ) : '';
	$job   = axismundi_media_filebird_get_job( $token, get_current_user_id() );
	if ( false === $job ) {
		return;
	}
	$path = dirname( __DIR__ ) . '/assets/filebird-import.js';
	$base = dirname( __DIR__ ) . '/axismundi-media-library.php';
	wp_enqueue_script(
		'axismundi-media-filebird-import',
		plugins_url( 'assets/filebird-import.js', $base ),
		array(),
		(string) filemtime( $path ),
		true
	);
	wp_localize_script(
		'axismundi-media-filebird-import',
		'axMediaFileBirdImport',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'token'   => $token,
			'nonce'   => wp_create_nonce( 'ax_media_filebird_import_' . $token ),
			'labels'  => array(
				'running' => __( 'Importing folders and media…', 'axismundi-media-library' ),
				'done'    => __( 'Import complete.', 'axismundi-media-library' ),
				'resume'  => __( 'Resume import', 'axismundi-media-library' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'axismundi_media_filebird_import_assets' );

/**
 * Render one compact statistics table.
 *
 * @param array<string,int> $stats Job statistics.
 * @return void
 */
function axismundi_media_render_filebird_stats( array $stats ) : void {
	$labels = array(
		'folders_total'         => __( 'Folders found', 'axismundi-media-library' ),
		'assignments_total'     => __( 'Media assignments found', 'axismundi-media-library' ),
		'duplicate_assignments' => __( 'Duplicate assignments ignored', 'axismundi-media-library' ),
		'folders_created'       => __( 'Folders created', 'axismundi-media-library' ),
		'folders_reused'        => __( 'Imported folders reused', 'axismundi-media-library' ),
		'attachments_assigned'  => __( 'Media assigned', 'axismundi-media-library' ),
		'attachments_already'   => __( 'Media already in target folder', 'axismundi-media-library' ),
		'attachments_missing'   => __( 'Media or target folders missing', 'axismundi-media-library' ),
		'attachments_denied'    => __( 'Media denied', 'axismundi-media-library' ),
		'attachments_preserved' => __( 'Existing Axismundi assignments preserved', 'axismundi-media-library' ),
	);
	?>
	<table class="widefat striped" style="max-width:720px"><tbody>
		<?php foreach ( $labels as $key => $label ) : ?>
			<?php if ( array_key_exists( $key, $stats ) ) : ?>
				<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td data-ax-media-stat="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( (string) $stats[ $key ] ); ?></td></tr>
			<?php endif; ?>
		<?php endforeach; ?>
	</tbody></table>
	<?php
}

/**
 * Render Media > Import Folders.
 *
 * @return void
 */
function axismundi_media_render_filebird_import_page() : void {
	if ( ! current_user_can( 'upload_files' ) ) {
		wp_die( esc_html__( 'You cannot import media folders.', 'axismundi-media-library' ), '', array( 'response' => 403 ) );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only selector for a current-user-owned transient.
	$token = isset( $_GET['job'] ) ? sanitize_text_field( wp_unslash( $_GET['job'] ) ) : '';
	$job   = axismundi_media_filebird_get_job( $token, get_current_user_id() );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only escaped notice from this plugin's redirect.
	$error = isset( $_GET['ax_media_error'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['ax_media_error'] ) ) ) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Import Media Folders', 'axismundi-media-library' ); ?></h1>
		<p><?php esc_html_e( 'Import a FileBird CSV export into the current user’s Axismundi folder tree. Uploaded CSV files are parsed once and are not stored.', 'axismundi-media-library' ); ?></p>
		<?php if ( '' !== $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<?php if ( false === $job ) : ?>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="axismundi_media_filebird_analyze">
				<?php wp_nonce_field( 'ax_media_filebird_analyze' ); ?>
				<table class="form-table"><tbody><tr>
					<th scope="row"><label for="filebird_csv"><?php esc_html_e( 'FileBird CSV', 'axismundi-media-library' ); ?></label></th>
					<td><input id="filebird_csv" name="filebird_csv" type="file" accept=".csv,text/csv" required><p class="description"><?php esc_html_e( 'Maximum 2 MB. Existing Axismundi folder assignments are preserved.', 'axismundi-media-library' ); ?></p></td>
				</tr></tbody></table>
				<?php submit_button( __( 'Analyze CSV', 'axismundi-media-library' ) ); ?>
			</form>
		<?php else : ?>
			<h2><?php esc_html_e( 'Analysis', 'axismundi-media-library' ); ?></h2>
			<?php axismundi_media_render_filebird_stats( $job['stats'] ); ?>
			<p><?php esc_html_e( 'Folders will be created for the current user. Missing media is skipped, and existing Axismundi assignments are never overwritten.', 'axismundi-media-library' ); ?></p>
			<?php if ( 'done' !== $job['status'] ) : ?>
				<p><button type="button" class="button button-primary" id="ax-media-filebird-start"><?php esc_html_e( 'Start import', 'axismundi-media-library' ); ?></button></p>
			<?php endif; ?>
			<progress id="ax-media-filebird-progress" max="100" value="<?php echo esc_attr( 'done' === $job['status'] ? '100' : '0' ); ?>" style="width:min(720px,100%);"></progress>
			<p id="ax-media-filebird-status" aria-live="polite"><?php echo 'done' === $job['status'] ? esc_html__( 'Import complete.', 'axismundi-media-library' ) : ''; ?></p>
			<ul id="ax-media-filebird-errors"></ul>
			<p><a class="button" href="<?php echo esc_url( axismundi_media_filebird_import_url() ); ?>"><?php esc_html_e( 'Analyze another CSV', 'axismundi-media-library' ); ?></a></p>
		<?php endif; ?>
	</div>
	<?php
}
