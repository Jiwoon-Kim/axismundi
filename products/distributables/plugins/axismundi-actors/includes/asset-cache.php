<?php
/**
 * Remote Actor avatar/header binary cache. Network fetches happen only from cron
 * workers; render-time helpers read already-generated local derivatives.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTORS_ASSET_PROCESSOR_VERSION = 2;
const AXISMUNDI_ACTORS_ASSET_MAX_BYTES         = 8388608;
const AXISMUNDI_ACTORS_ASSET_MAX_DIMENSION     = 12000;
const AXISMUNDI_ACTORS_ASSET_MAX_PIXELS        = 40000000;
const AXISMUNDI_ACTORS_ASSET_BATCH_SIZE        = 3;

/** @return array<string,array<int,array{width:int,height:int}>> Default derivative caps. */
function axismundi_actors_asset_variants() : array {
	return array(
		'avatar' => array(
			96  => array( 'width' => 96, 'height' => 96 ),
			192 => array( 'width' => 192, 'height' => 192 ),
			400 => array( 'width' => 400, 'height' => 400 ),
		),
		'header' => array(
			640  => array( 'width' => 640, 'height' => 0 ),
			1024 => array( 'width' => 1024, 'height' => 0 ),
		),
	);
}

/** Whether optional WebP candidate generation is enabled. Default is off. */
function axismundi_actors_asset_webp_enabled() : bool {
	return (bool) apply_filters( 'axismundi_actors_asset_webp_enabled', (bool) get_option( 'ax_actors_asset_webp_enabled', false ) );
}

/**
 * Extract the first usable HTTPS URL from an ActivityStreams image value.
 *
 * @param mixed $value String, Link/Image object, or list.
 * @return string
 */
function axismundi_actors_remote_asset_source( $value ) : string {
	if ( is_string( $value ) ) {
		$url = esc_url_raw( trim( $value ) );
		return 'https' === strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) )
			&& wp_http_validate_url( $url ) ? $url : '';
	}
	if ( ! is_array( $value ) ) {
		return '';
	}
	if ( array_is_list( $value ) ) {
		foreach ( $value as $candidate ) {
			$url = axismundi_actors_remote_asset_source( $candidate );
			if ( '' !== $url ) {
				return $url;
			}
		}
		return '';
	}
	foreach ( array( 'url', 'href' ) as $key ) {
		if ( array_key_exists( $key, $value ) ) {
			$url = axismundi_actors_remote_asset_source( $value[ $key ] );
			if ( '' !== $url ) {
				return $url;
			}
		}
	}
	return '';
}

/** @param int $identity_id Identity id. @param string $role avatar|header. @return array<string,mixed>|null */
function axismundi_actors_get_asset_cache_row( int $identity_id, string $role ) : ?array {
	global $wpdb;
	if ( $identity_id <= 0 || ! in_array( $role, array( 'avatar', 'header' ), true ) ) {
		return null;
	}
	$table = axismundi_actors_asset_cache_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom cache table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE identity_id = %d AND asset_role = %s", $identity_id, $role ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/** Queue one bounded asset worker, coalescing repeated discoveries. */
function axismundi_actors_queue_asset_worker( int $delay = 10 ) : bool {
	if ( ! wp_next_scheduled( 'axismundi_actors_process_asset_batch' ) ) {
		$result = wp_schedule_single_event( time() + max( 1, $delay ), 'axismundi_actors_process_asset_batch', array(), true );
		return ! is_wp_error( $result ) && false !== $result;
	}
	return true;
}

/** Number of cache rows currently due for processing. */
function axismundi_actors_asset_due_count() : int {
	global $wpdb;
	$table = axismundi_actors_asset_cache_table();
	$now   = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed queue-health query on a fixed custom table.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE next_refresh_at IS NOT NULL AND next_refresh_at <= %s AND fetch_status IN ('pending','stale','error')", $now ) );
}

/** Remove legacy timer deadlines from successful rows once per refresh-policy version. */
function axismundi_actors_normalize_asset_refresh_policy() : void {
	global $wpdb;
	if ( 2 <= (int) get_option( 'ax_actors_asset_refresh_policy_version', 0 ) ) {
		return;
	}
	$table = axismundi_actors_asset_cache_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration of the fixed custom cache table.
	$updated = $wpdb->query( "UPDATE {$table} SET expires_at = NULL, next_refresh_at = NULL WHERE fetch_status = 'ready'" );
	if ( false !== $updated ) {
		update_option( 'ax_actors_asset_refresh_policy_version', 2, false );
	}
}
add_action( 'init', 'axismundi_actors_normalize_asset_refresh_policy', 19 );

/** Recover due rows orphaned when a plugin replacement cleared the one-shot event. */
function axismundi_actors_recover_asset_worker() : bool {
	if ( wp_next_scheduled( 'axismundi_actors_process_asset_batch' ) ) {
		return true;
	}
	return axismundi_actors_asset_due_count() > 0 ? axismundi_actors_queue_asset_worker( 1 ) : false;
}
add_action( 'init', 'axismundi_actors_recover_asset_worker', 20 );

/**
 * Synchronize one remote Actor role to a source URI without fetching it.
 *
 * @param int    $identity_id Remote actor identity.
 * @param string $role        avatar|header.
 * @param string $source_uri  Validated remote URI, or empty to remove the mapping.
 * @return bool
 */
function axismundi_actors_sync_asset_source( int $identity_id, string $role, string $source_uri ) : bool {
	global $wpdb;
	if ( $identity_id <= 0 || ! in_array( $role, array( 'avatar', 'header' ), true ) ) {
		return false;
	}
	$table = axismundi_actors_asset_cache_table();
	$row   = axismundi_actors_get_asset_cache_row( $identity_id, $role );
	if ( '' === $source_uri ) {
		if ( $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- source disappeared; physical bytes remain for grace-period GC.
			$wpdb->delete( $table, array( 'id' => (int) $row['id'] ), array( '%d' ) );
		}
		return true;
	}
	$source_uri = axismundi_actors_remote_asset_source( $source_uri );
	if ( '' === $source_uri ) {
		return false;
	}
	if ( $row && AXISMUNDI_ACTORS_ASSET_PROCESSOR_VERSION === (int) $row['processor_version'] && hash_equals( (string) $row['source_uri_hash'], hash( 'sha256', $source_uri ) ) ) {
		return true;
	}
	$now    = current_time( 'mysql', true );
	$status = $row && '' !== (string) ( $row['content_hash'] ?? '' ) ? 'stale' : 'pending';
	$data   = array(
		'source_uri'           => $source_uri,
		'source_uri_hash'      => hash( 'sha256', $source_uri ),
		'source_etag'          => null,
		'source_last_modified' => null,
		'fetch_status'         => $status,
		'next_refresh_at'      => $now,
		'failure_count'        => 0,
		'last_error_code'      => null,
		'updated_at'           => $now,
	);
	if ( $row ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom cache table.
		$done = $wpdb->update( $table, $data, array( 'id' => (int) $row['id'] ) );
	} else {
		$data['identity_id']       = $identity_id;
		$data['asset_role']        = $role;
		$data['processor_version'] = AXISMUNDI_ACTORS_ASSET_PROCESSOR_VERSION;
		$data['created_at']        = $now;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom cache table.
		$done = $wpdb->insert( $table, $data );
	}
	if ( false !== $done ) {
		axismundi_actors_queue_asset_worker();
		return true;
	}
	return false;
}

/** @param Axismundi_Actor $actor Discovered remote Actor. */
function axismundi_actors_sync_remote_actor_assets( Axismundi_Actor $actor ) : void {
	if ( $actor->is_local() ) {
		return;
	}
	$payload = axismundi_actors_get_remote_payload( $actor->get_identity_id() );
	axismundi_actors_sync_asset_source( $actor->get_identity_id(), 'avatar', axismundi_actors_remote_asset_source( $payload['icon'] ?? null ) );
	axismundi_actors_sync_asset_source( $actor->get_identity_id(), 'header', axismundi_actors_remote_asset_source( $payload['image'] ?? null ) );
}
add_action( 'axismundi_actors_remote_actor_discovered', 'axismundi_actors_sync_remote_actor_assets', 20 );
add_action( 'axismundi_actors_remote_actor_updated', 'axismundi_actors_sync_remote_actor_assets', 20 );

/** Queue a paged backfill for remote snapshots cached before DB v9. */
function axismundi_actors_queue_asset_backfill() : void {
	if ( (int) get_option( 'ax_actors_asset_backfill_version', 0 ) >= AXISMUNDI_ACTORS_ASSET_PROCESSOR_VERSION ) {
		return;
	}
	if ( ! wp_next_scheduled( 'axismundi_actors_asset_backfill_batch' ) ) {
		wp_schedule_single_event( time() + 5, 'axismundi_actors_asset_backfill_batch' );
	}
}
add_action( 'plugins_loaded', 'axismundi_actors_queue_asset_backfill', 20 );

/** Process up to fifty pre-v9 remote Actor snapshots, then yield. */
function axismundi_actors_asset_backfill_batch() : void {
	global $wpdb;
	$cursor     = (int) get_option( 'ax_actors_asset_backfill_cursor', 0 );
	$identities = axismundi_actors_identities_table();
	$actors     = axismundi_actors_actors_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom tables; paged migration.
	$ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT i.id FROM {$identities} i INNER JOIN {$actors} a ON a.identity_id = i.id WHERE i.origin = 'remote' AND i.id > %d ORDER BY i.id ASC LIMIT 50", $cursor ) );
	foreach ( $ids as $identity_id ) {
		$actor = axismundi_actors_get_by_identity( (int) $identity_id );
		if ( $actor ) {
			axismundi_actors_sync_remote_actor_assets( $actor );
		}
		$cursor = (int) $identity_id;
	}
	if ( 50 === count( $ids ) ) {
		update_option( 'ax_actors_asset_backfill_cursor', $cursor, false );
		wp_schedule_single_event( time() + 10, 'axismundi_actors_asset_backfill_batch' );
	} else {
		delete_option( 'ax_actors_asset_backfill_cursor' );
		update_option( 'ax_actors_asset_backfill_version', AXISMUNDI_ACTORS_ASSET_PROCESSOR_VERSION, false );
	}
}
add_action( 'axismundi_actors_asset_backfill_batch', 'axismundi_actors_asset_backfill_batch' );

/** @return array{basedir:string,baseurl:string}|WP_Error Cache base. */
function axismundi_actors_asset_base() {
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		return new WP_Error( 'ax_actors_asset_uploads', (string) $uploads['error'] );
	}
	$suffix = '/axismundi-cache/actors';
	return array(
		'basedir' => untrailingslashit( (string) $uploads['basedir'] ) . $suffix,
		'baseurl' => untrailingslashit( (string) $uploads['baseurl'] ) . $suffix,
	);
}

/** @param int|null $processor Processor version; current by default. @return array{basedir:string,baseurl:string}|WP_Error */
function axismundi_actors_asset_root( ?int $processor = null ) {
	$base = axismundi_actors_asset_base();
	if ( is_wp_error( $base ) ) {
		return $base;
	}
	$processor = null === $processor ? AXISMUNDI_ACTORS_ASSET_PROCESSOR_VERSION : max( 1, $processor );
	return array(
		'basedir' => $base['basedir'] . '/v' . $processor,
		'baseurl' => $base['baseurl'] . '/v' . $processor,
	);
}

/** @param string $content_hash Source-byte sha256. @param int|null $processor Processor version. @return array{path:string,url:string}|WP_Error */
function axismundi_actors_asset_content_root( string $content_hash, ?int $processor = null ) {
	if ( ! preg_match( '/^[a-f0-9]{64}$/', $content_hash ) ) {
		return new WP_Error( 'ax_actors_asset_hash', __( 'Invalid cached asset hash.', 'axismundi-actors' ) );
	}
	$root = axismundi_actors_asset_root( $processor );
	if ( is_wp_error( $root ) ) {
		return $root;
	}
	$suffix = '/' . substr( $content_hash, 0, 2 ) . '/' . substr( $content_hash, 2, 2 ) . '/' . $content_hash;
	return array( 'path' => $root['basedir'] . $suffix, 'url' => $root['baseurl'] . $suffix );
}

/** @param string $mime MIME type. @return string */
function axismundi_actors_asset_extension( string $mime ) : string {
	return array( 'image/webp' => 'webp', 'image/jpeg' => 'jpg', 'image/png' => 'png' )[ $mime ] ?? '';
}

/**
 * Open and constrain an image without ever upscaling it.
 *
 * Avatars become square only when both source dimensions can supply the requested
 * crop. Smaller or unusually narrow images retain their aspect ratio. Headers retain
 * their aspect ratio and are reduced only when wider than the requested cap.
 *
 * @param string $source_path Source image.
 * @param string $role        avatar|header.
 * @param int    $cap         Requested maximum dimension/width.
 * @return WP_Image_Editor|WP_Error
 */
function axismundi_actors_prepare_asset_editor( string $source_path, string $role, int $cap ) {
	$editor = wp_get_image_editor( $source_path );
	if ( is_wp_error( $editor ) ) {
		return $editor;
	}
	$size   = $editor->get_size();
	$width  = (int) ( $size['width'] ?? 0 );
	$height = (int) ( $size['height'] ?? 0 );
	if ( $width <= 0 || $height <= 0 ) {
		return new WP_Error( 'ax_actors_asset_dimensions', __( 'The cached image has invalid dimensions.', 'axismundi-actors' ) );
	}

	if ( 'avatar' === $role && ( $width > $cap || $height > $cap ) ) {
		$crop    = min( $width, $height ) >= $cap;
		$resized = $editor->resize( $cap, $cap, $crop );
		return is_wp_error( $resized ) ? $resized : $editor;
	}
	if ( 'header' === $role && $width > $cap ) {
		$target_height = max( 1, (int) round( $height * ( $cap / $width ) ) );
		$resized       = $editor->resize( $cap, $target_height, false );
		return is_wp_error( $resized ) ? $resized : $editor;
	}
	return $editor;
}

/** @param string $source_mime Validated source MIME. @return string Normalized baseline MIME. */
function axismundi_actors_asset_baseline_mime( string $source_mime ) : string {
	if ( in_array( $source_mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
		return $source_mime;
	}
	return 'image/jpeg';
}

/**
 * Save one normalized candidate.
 *
 * @param string $source_path Source image.
 * @param string $role        avatar|header.
 * @param int    $cap         Requested maximum dimension/width.
 * @param string $path        Candidate path.
 * @param string $mime        Output MIME.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_actors_save_asset_candidate( string $source_path, string $role, int $cap, string $path, string $mime ) {
	$editor = axismundi_actors_prepare_asset_editor( $source_path, $role, $cap );
	if ( is_wp_error( $editor ) ) {
		return $editor;
	}
	return $editor->save( $path, $mime );
}

/**
 * Build the role's capped derivative and atomically move it into the shared
 * content-addressed directory. WebP is selected only when it is smaller than the
 * normalized baseline; sources smaller than the cap are re-encoded but not enlarged.
 *
 * @param string $source_path Source temp file.
 * @param string $content_hash Source-byte sha256.
 * @param string $role avatar|header.
 * @param string $source_mime Validated source MIME.
 * @return array<string,array<string,int|string>>|WP_Error
 */
function axismundi_actors_generate_asset_variants( string $source_path, string $content_hash, string $role, string $source_mime ) {
	$all = axismundi_actors_asset_variants();
	if ( ! isset( $all[ $role ] ) ) {
		return new WP_Error( 'ax_actors_asset_role', __( 'Invalid cached asset role.', 'axismundi-actors' ) );
	}
	$root = axismundi_actors_asset_content_root( $content_hash );
	if ( is_wp_error( $root ) ) {
		return $root;
	}
	$staging = $root['path'] . '.tmp-' . wp_generate_uuid4();
	if ( ! wp_mkdir_p( $staging ) || ! wp_mkdir_p( $root['path'] ) ) {
		return new WP_Error( 'ax_actors_asset_directory', __( 'Could not create the remote asset cache directory.', 'axismundi-actors' ) );
	}
	$manifest = array();
	$generated_dimensions = array();
	foreach ( $all[ $role ] as $size => $dimensions ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- dimensions document the cap contract.
		$baseline_mime = axismundi_actors_asset_baseline_mime( $source_mime );
		$baseline_ext  = axismundi_actors_asset_extension( $baseline_mime );
		$baseline      = axismundi_actors_save_asset_candidate( $source_path, $role, (int) $size, $staging . '/baseline-' . $size . '.' . $baseline_ext, $baseline_mime );
		if ( is_wp_error( $baseline ) ) {
			axismundi_actors_remove_asset_tree( $staging );
			return $baseline;
		}
		$chosen = $baseline;
		if ( axismundi_actors_asset_webp_enabled() && 'image/webp' !== $baseline_mime && wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			$webp = axismundi_actors_save_asset_candidate( $source_path, $role, (int) $size, $staging . '/candidate-' . $size . '.webp', 'image/webp' );
			if ( ! is_wp_error( $webp ) && filesize( (string) $webp['path'] ) < filesize( (string) $baseline['path'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- bounded cache candidates.
				$chosen = $webp;
			}
		}
		$dimension_key = (int) $chosen['width'] . 'x' . (int) $chosen['height'];
		if ( isset( $generated_dimensions[ $dimension_key ] ) ) {
			continue;
		}
		$generated_dimensions[ $dimension_key ] = true;
		$extension = axismundi_actors_asset_extension( (string) $chosen['mime-type'] );
		$filename  = $role . '-' . $size . '.' . $extension;
		$final = $root['path'] . '/' . $filename;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- same-filesystem atomic cache publish; WP_Filesystem does not guarantee atomicity.
		if ( ! file_exists( $final ) && ! rename( (string) $chosen['path'], $final ) ) {
			axismundi_actors_remove_asset_tree( $staging );
			return new WP_Error( 'ax_actors_asset_publish', __( 'Could not publish a cached image derivative.', 'axismundi-actors' ) );
		}
		foreach ( array( 'webp', 'jpg', 'png' ) as $obsolete_extension ) {
			$obsolete = $root['path'] . '/' . $role . '-' . $size . '.' . $obsolete_extension;
			if ( $obsolete !== $final && file_exists( $obsolete ) ) {
				unlink( $obsolete ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- cache-owned obsolete alternative encoding.
			}
		}
		$manifest[ (string) $size ] = array(
			'width'  => (int) $chosen['width'],
			'height' => (int) $chosen['height'],
			'bytes'  => (int) filesize( $final ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filesize -- cache metadata.
			'mime'   => (string) $chosen['mime-type'],
		);
	}
	axismundi_actors_remove_asset_tree( $staging );
	return $manifest;
}

/** @param string $path Cache-owned directory only. */
function axismundi_actors_remove_asset_tree( string $path ) : void {
	if ( ! is_dir( $path ) ) {
		return;
	}
	$items = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $items as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- cache-owned tree.
		} else {
			unlink( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- cache-owned tree.
		}
	}
	rmdir( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- cache-owned tree.
}

/** @param int $identity_id Identity id. @param string $role avatar|header. @param string $code Stable error code. */
function axismundi_actors_asset_fetch_failed( int $identity_id, string $role, string $code ) : void {
	global $wpdb;
	$row = axismundi_actors_get_asset_cache_row( $identity_id, $role );
	if ( ! $row ) {
		return;
	}
	$failures = (int) $row['failure_count'] + 1;
	$delay    = min( WEEK_IN_SECONDS, HOUR_IN_SECONDS * ( 2 ** min( 7, $failures ) ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom cache state.
	$wpdb->update(
		axismundi_actors_asset_cache_table(),
		array(
			'fetch_status'    => '' !== (string) ( $row['content_hash'] ?? '' ) ? 'stale' : 'error',
			'failure_count'   => $failures,
			'last_error_code' => sanitize_key( $code ),
			'next_refresh_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ),
			'updated_at'      => current_time( 'mysql', true ),
		),
		array( 'id' => (int) $row['id'] )
	);
}

/**
 * Fetch and derive one cache row. Intended for cron/CLI workers, never render paths.
 *
 * @param int    $identity_id Identity id.
 * @param string $role avatar|header.
 * @return true|WP_Error
 */
function axismundi_actors_fetch_remote_asset( int $identity_id, string $role ) {
	global $wpdb;
	$row = axismundi_actors_get_asset_cache_row( $identity_id, $role );
	if ( ! $row ) {
		return new WP_Error( 'ax_actors_asset_missing', __( 'No remote asset cache record exists.', 'axismundi-actors' ) );
	}
	$headers = array( 'Accept' => 'image/avif,image/webp,image/png,image/jpeg,image/gif;q=0.8' );
	if ( '' !== (string) $row['source_etag'] ) {
		$headers['If-None-Match'] = (string) $row['source_etag'];
	}
	if ( '' !== (string) $row['source_last_modified'] ) {
		$headers['If-Modified-Since'] = (string) $row['source_last_modified'];
	}
	$response = wp_safe_remote_get(
		(string) $row['source_uri'],
		array(
			'timeout'             => 15,
			'redirection'         => 0,
			'limit_response_size' => AXISMUNDI_ACTORS_ASSET_MAX_BYTES + 1,
			'headers'             => $headers,
			'user-agent'          => 'Axismundi Actors/' . AXISMUNDI_ACTORS_VERSION . '; ' . home_url( '/' ),
		)
	);
	if ( is_wp_error( $response ) ) {
		axismundi_actors_asset_fetch_failed( $identity_id, $role, $response->get_error_code() );
		return $response;
	}
	$status = (int) wp_remote_retrieve_response_code( $response );
	$now    = current_time( 'mysql', true );
	if ( 304 === $status && '' !== (string) ( $row['content_hash'] ?? '' ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- conditional refresh state.
		$wpdb->update( axismundi_actors_asset_cache_table(), array( 'fetch_status' => 'ready', 'fetched_at' => $now, 'expires_at' => null, 'next_refresh_at' => null, 'failure_count' => 0, 'last_error_code' => null, 'updated_at' => $now ), array( 'id' => (int) $row['id'] ) );
		return true;
	}
	if ( 200 !== $status ) {
		axismundi_actors_asset_fetch_failed( $identity_id, $role, 'http_' . $status );
		return new WP_Error( 'ax_actors_asset_status', __( 'The remote image returned an unexpected status.', 'axismundi-actors' ) );
	}
	$body = (string) wp_remote_retrieve_body( $response );
	if ( '' === $body || strlen( $body ) > AXISMUNDI_ACTORS_ASSET_MAX_BYTES ) {
		axismundi_actors_asset_fetch_failed( $identity_id, $role, 'size' );
		return new WP_Error( 'ax_actors_asset_size', __( 'The remote image was empty or too large.', 'axismundi-actors' ) );
	}
	$temp = wp_tempnam( 'ax-actor-asset' );
	if ( ! $temp || false === file_put_contents( $temp, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- bounded temporary image body.
		axismundi_actors_asset_fetch_failed( $identity_id, $role, 'temp' );
		return new WP_Error( 'ax_actors_asset_temp', __( 'Could not create a temporary image.', 'axismundi-actors' ) );
	}
	try {
		$mime = (string) wp_get_image_mime( $temp );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif' ), true ) ) {
			throw new RuntimeException( 'mime' );
		}
		$source_extension = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/avif' => 'avif' )[ $mime ];
		$typed_temp       = $temp . '.' . $source_extension;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- same-filesystem temporary rename required by image editors that inspect extensions.
		if ( ! rename( $temp, $typed_temp ) ) {
			throw new RuntimeException( 'temp_rename' );
		}
		$temp = $typed_temp;
		$dimensions = wp_getimagesize( $temp );
		$width      = is_array( $dimensions ) ? (int) $dimensions[0] : 0;
		$height     = is_array( $dimensions ) ? (int) $dimensions[1] : 0;
		if ( $width <= 0 || $height <= 0 || $width > AXISMUNDI_ACTORS_ASSET_MAX_DIMENSION || $height > AXISMUNDI_ACTORS_ASSET_MAX_DIMENSION || $width * $height > AXISMUNDI_ACTORS_ASSET_MAX_PIXELS ) {
			throw new RuntimeException( 'dimensions' );
		}
		$content_hash = hash( 'sha256', $body );
		$manifest     = axismundi_actors_generate_asset_variants( $temp, $content_hash, $role, $mime );
		if ( is_wp_error( $manifest ) ) {
			throw new RuntimeException( $manifest->get_error_code() );
		}
		$encoded = wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES );
		if ( false === $encoded ) {
			throw new RuntimeException( 'manifest' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- completed cache swap.
		$wpdb->update(
			axismundi_actors_asset_cache_table(),
			array(
				'content_hash'         => $content_hash,
				'source_etag'          => sanitize_text_field( (string) wp_remote_retrieve_header( $response, 'etag' ) ),
				'source_last_modified' => sanitize_text_field( (string) wp_remote_retrieve_header( $response, 'last-modified' ) ),
				'source_mime_type'     => $mime,
				'source_width'         => $width,
				'source_height'        => $height,
				'source_byte_size'     => strlen( $body ),
				'variants_json'        => $encoded,
				'processor_version'    => AXISMUNDI_ACTORS_ASSET_PROCESSOR_VERSION,
				'fetch_status'         => 'ready',
				'fetched_at'           => $now,
				'expires_at'           => null,
				'next_refresh_at'      => null,
				'last_accessed_at'     => $now,
				'failure_count'        => 0,
				'last_error_code'      => null,
				'updated_at'           => $now,
			),
			array( 'id' => (int) $row['id'] )
		);
	} catch ( RuntimeException $error ) {
		axismundi_actors_asset_fetch_failed( $identity_id, $role, $error->getMessage() );
		return new WP_Error( 'ax_actors_asset_process', __( 'The remote image could not be validated or processed.', 'axismundi-actors' ) );
	} finally {
		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- bounded temporary source is never retained.
	}
	return true;
}

/** Process a bounded number of due rows and yield to another cron request. */
function axismundi_actors_process_asset_batch() : void {
	global $wpdb;
	$table = axismundi_actors_asset_cache_table();
	$now   = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded due-cache queue.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT identity_id, asset_role FROM {$table} WHERE next_refresh_at IS NOT NULL AND next_refresh_at <= %s AND fetch_status IN ('pending','stale','error') ORDER BY next_refresh_at ASC LIMIT %d", $now, AXISMUNDI_ACTORS_ASSET_BATCH_SIZE ), ARRAY_A );
	foreach ( $rows as $row ) {
		axismundi_actors_fetch_remote_asset( (int) $row['identity_id'], (string) $row['asset_role'] );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- determine whether another bounded batch is due.
	$remaining = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE next_refresh_at IS NOT NULL AND next_refresh_at <= %s AND fetch_status IN ('pending','stale','error')", $now ) );
	if ( $remaining > 0 ) {
		axismundi_actors_queue_asset_worker( 30 );
	}
}
add_action( 'axismundi_actors_process_asset_batch', 'axismundi_actors_process_asset_batch' );

/**
 * Resolve one local derivative URL without fetching. Returns empty for pending/error.
 *
 * @param int    $identity_id Actor identity.
 * @param string $role avatar|header.
 * @param int    $preferred_size Requested width.
 * @return string
 */
function axismundi_actors_get_cached_asset_url( int $identity_id, string $role, int $preferred_size ) : string {
	global $wpdb;
	$row = axismundi_actors_get_asset_cache_row( $identity_id, $role );
	if ( ! $row || '' === (string) ( $row['content_hash'] ?? '' ) || ! in_array( (string) $row['fetch_status'], array( 'ready', 'stale' ), true ) ) {
		return '';
	}
	$manifest = json_decode( (string) $row['variants_json'], true );
	if ( ! is_array( $manifest ) || empty( $manifest ) ) {
		return '';
	}
	$sizes = array_map( 'intval', array_keys( $manifest ) );
	sort( $sizes, SORT_NUMERIC );
	$chosen = end( $sizes );
	foreach ( $sizes as $size ) {
		if ( $size >= $preferred_size ) {
			$chosen = $size;
			break;
		}
	}
	$meta      = $manifest[ (string) $chosen ] ?? array();
	$extension = axismundi_actors_asset_extension( (string) ( $meta['mime'] ?? '' ) );
	$root      = axismundi_actors_asset_content_root( (string) $row['content_hash'], (int) $row['processor_version'] );
	if ( '' === $extension || is_wp_error( $root ) ) {
		return '';
	}
	$file = $role . '-' . $chosen . '.' . $extension;
	if ( ! file_exists( $root['path'] . '/' . $file ) ) {
		return '';
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- LRU touch after a successful local hit.
	$wpdb->update( axismundi_actors_asset_cache_table(), array( 'last_accessed_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $row['id'] ), array( '%s' ), array( '%d' ) );
	return $root['url'] . '/' . rawurlencode( $file );
}

/**
 * Resolve every existing local derivative and its actual pixel width.
 *
 * @param int    $identity_id Actor identity.
 * @param string $role        avatar|header.
 * @return array<string,int> URL keyed map of actual widths.
 */
function axismundi_actors_get_cached_asset_sources( int $identity_id, string $role ) : array {
	$row = axismundi_actors_get_asset_cache_row( $identity_id, $role );
	if ( ! $row || '' === (string) ( $row['content_hash'] ?? '' ) || ! in_array( (string) $row['fetch_status'], array( 'ready', 'stale' ), true ) ) {
		return array();
	}
	$manifest = json_decode( (string) $row['variants_json'], true );
	$root     = axismundi_actors_asset_content_root( (string) $row['content_hash'], (int) $row['processor_version'] );
	if ( ! is_array( $manifest ) || is_wp_error( $root ) ) {
		return array();
	}
	$sources = array();
	foreach ( $manifest as $size => $meta ) {
		$extension = axismundi_actors_asset_extension( (string) ( $meta['mime'] ?? '' ) );
		$file      = $role . '-' . (int) $size . '.' . $extension;
		if ( '' !== $extension && file_exists( $root['path'] . '/' . $file ) ) {
			$sources[ $root['url'] . '/' . rawurlencode( $file ) ] = (int) ( $meta['width'] ?? $size );
		}
	}
	asort( $sources, SORT_NUMERIC );
	return $sources;
}

/**
 * Remove unreferenced content directories after a grace period.
 *
 * @param bool $dry_run Whether to report only.
 * @param int  $grace_seconds Minimum orphan age.
 * @return array{directories:int,bytes:int}
 */
function axismundi_actors_asset_gc( bool $dry_run = true, int $grace_seconds = 604800 ) : array {
	global $wpdb;
	$base = axismundi_actors_asset_base();
	if ( is_wp_error( $base ) || ! is_dir( $base['basedir'] ) ) {
		return array( 'directories' => 0, 'bytes' => 0 );
	}
	$table   = axismundi_actors_asset_cache_table();
	$result  = array( 'directories' => 0, 'bytes' => 0 );
	foreach ( (array) glob( $base['basedir'] . '/v[0-9]*', GLOB_ONLYDIR ) as $version_root ) {
		$processor = (int) substr( basename( $version_root ), 1 );
		$pattern   = $version_root . '/[a-f0-9][a-f0-9]/[a-f0-9][a-f0-9]/' . str_repeat( '[a-f0-9]', 64 );
		foreach ( (array) glob( $pattern, GLOB_ONLYDIR ) as $directory ) {
			$hash = basename( $directory );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- GC reference check on custom table.
			$references = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE content_hash = %s AND processor_version = %d", $hash, $processor ) );
			if ( $references > 0 || filemtime( $directory ) > time() - max( 0, $grace_seconds ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_filemtime -- cache GC age.
				continue;
			}
			$bytes = 0;
			foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ) ) as $item ) {
				if ( $item->isFile() ) {
					$bytes += $item->getSize();
				}
			}
			++$result['directories'];
			$result['bytes'] += $bytes;
			if ( ! $dry_run ) {
				axismundi_actors_remove_asset_tree( $directory );
			}
		}
	}
	return $result;
}

/**
 * Rows affected by an administrative cache scope.
 *
 * @param string $scope actor|instance|all.
 * @param string $value Identity id or host authority.
 * @return array<int,array<string,mixed>>
 */
function axismundi_actors_asset_scope_rows( string $scope, string $value = '' ) : array {
	global $wpdb;
	$table = axismundi_actors_asset_cache_table();
	if ( 'actor' === $scope ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom cache table.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE identity_id = %d", (int) $value ), ARRAY_A );
	}
	if ( 'instance' === $scope ) {
		$authority = axismundi_actors_webfinger_authority_from_url( 'https://' . strtolower( trim( $value ) ) . '/' );
		if ( '' === $authority ) {
			return array();
		}
		$identities = axismundi_actors_identities_table();
		$prefix     = $wpdb->esc_like( 'https://' . $authority . '/' ) . '%';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom cache/identity tables; rare admin scope operation.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT c.* FROM {$table} c INNER JOIN {$identities} i ON i.id = c.identity_id WHERE i.origin = 'remote' AND i.canonical_uri LIKE %s", $prefix ), ARRAY_A );
	}
	if ( 'all' === $scope ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom cache table; explicit admin operation.
		return (array) $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A );
	}
	return array();
}

/**
 * Dry-run or purge Actor cache mappings at actor/instance/all scope. Physical
 * directories are removed only when no remaining row references the content hash.
 *
 * @param string $scope actor|instance|all.
 * @param string $value Identity id or host.
 * @param bool   $dry_run Report without mutation.
 * @return array{rows:int,directories:int,bytes:int}
 */
function axismundi_actors_purge_asset_cache( string $scope, string $value = '', bool $dry_run = true ) : array {
	global $wpdb;
	$rows   = axismundi_actors_asset_scope_rows( $scope, $value );
	$result = array( 'rows' => count( $rows ), 'directories' => 0, 'bytes' => 0 );
	if ( $dry_run || empty( $rows ) ) {
		return $result;
	}
	$table  = axismundi_actors_asset_cache_table();
	$hashes = array();
	foreach ( $rows as $row ) {
		if ( '' !== (string) ( $row['content_hash'] ?? '' ) ) {
			$hashes[ (string) $row['content_hash'] . ':' . (int) $row['processor_version'] ] = array( (string) $row['content_hash'], (int) $row['processor_version'] );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit administrator purge.
		$wpdb->delete( $table, array( 'id' => (int) $row['id'] ), array( '%d' ) );
	}
	foreach ( $hashes as [ $hash, $processor ] ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- post-purge reference check.
		$references = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE content_hash = %s AND processor_version = %d", $hash, $processor ) );
		if ( $references > 0 ) {
			continue;
		}
		$root = axismundi_actors_asset_content_root( $hash, $processor );
		if ( ! is_array( $root ) || ! is_dir( $root['path'] ) ) {
			continue;
		}
		$bytes = 0;
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root['path'], FilesystemIterator::SKIP_DOTS ) ) as $item ) {
			if ( $item->isFile() ) {
				$bytes += $item->getSize();
			}
		}
		axismundi_actors_remove_asset_tree( $root['path'] );
		++$result['directories'];
		$result['bytes'] += $bytes;
	}
	return $result;
}

/** @param int $identity_id Actor identity. @return int Number of rows queued. */
function axismundi_actors_refresh_asset_cache( int $identity_id ) : int {
	global $wpdb;
	$rows = axismundi_actors_asset_scope_rows( 'actor', (string) $identity_id );
	$now  = current_time( 'mysql', true );
	foreach ( $rows as $row ) {
		$status = '' !== (string) ( $row['content_hash'] ?? '' ) ? 'stale' : 'pending';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit administrator refresh queue.
		$wpdb->update( axismundi_actors_asset_cache_table(), array( 'fetch_status' => $status, 'next_refresh_at' => $now, 'updated_at' => $now ), array( 'id' => (int) $row['id'] ) );
	}
	if ( ! empty( $rows ) ) {
		axismundi_actors_queue_asset_worker( 1 );
	}
	return count( $rows );
}

/** Toggle optional WebP generation and queue every mapping for asynchronous rebuild. */
function axismundi_actors_set_asset_webp_enabled( bool $enabled ) : int {
	global $wpdb;
	$previous = (bool) get_option( 'ax_actors_asset_webp_enabled', false );
	update_option( 'ax_actors_asset_webp_enabled', $enabled ? 1 : 0, false );
	if ( $previous === $enabled ) {
		return 0;
	}
	$table = axismundi_actors_asset_cache_table();
	$now   = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit global cache rebuild after an administrator setting change.
	$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET fetch_status = IF(content_hash IS NULL, 'pending', 'stale'), source_etag = NULL, source_last_modified = NULL, next_refresh_at = %s, updated_at = %s", $now, $now ) );
	if ( false !== $updated && $updated > 0 ) {
		axismundi_actors_queue_asset_worker( 1 );
	}
	return false === $updated ? 0 : (int) $updated;
}
