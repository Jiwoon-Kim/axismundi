<?php
/** Actor-scoped media authoring. Uploading does not submit an Activity. */
defined( 'ABSPATH' ) || exit;

function axismundi_op_media_upload_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_op_media_uploads';
}

function axismundi_op_media_upload_install() : void {
	if ( '1' === get_option( 'ax_op_media_upload_schema' ) ) { return; }
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = axismundi_op_media_upload_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		local_uuid char(36) NOT NULL,
		request_key char(64) DEFAULT NULL,
		intent_hash char(64) NOT NULL,
		storage_path varchar(255) NOT NULL,
		PRIMARY KEY  (local_uuid),
		UNIQUE KEY request_key (request_key)
	) ENGINE=InnoDB {$charset};" );
	$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
	$keys = $wpdb->get_results( "SHOW INDEX FROM {$table}", ARRAY_A );
	$unique = array();
	foreach ( $keys ?? array() as $key ) {
		if ( 0 === (int) $key['Non_unique'] ) { $unique[ $key['Key_name'] ][] = $key['Column_name']; }
	}
	if ( is_array( $columns ) && ! array_diff( array( 'local_uuid', 'request_key', 'intent_hash', 'storage_path' ), $columns )
		&& 'InnoDB' === $engine && array( 'local_uuid' ) === ( $unique['PRIMARY'] ?? array() )
		&& array( 'request_key' ) === ( $unique['request_key'] ?? array() ) ) {
		update_option( 'ax_op_media_upload_schema', '1', false );
	}
}
add_action( 'plugins_loaded', 'axismundi_op_media_upload_install' );

function axismundi_op_media_upload_error( string $code, string $message, int $status ) : WP_Error {
	return new WP_Error( $code, $message, array( 'status' => $status ) );
}

function axismundi_op_media_upload_problem( WP_Error $error ) : WP_REST_Response {
	$status = (int) ( $error->get_error_data()['status'] ?? 500 );
	return new WP_REST_Response( array(
		'type' => 'about:blank',
		'title' => get_status_header_desc( $status ),
		'status' => $status,
		'detail' => $error->get_error_message(),
		'code' => $error->get_error_code(),
	), $status, array( 'Content-Type' => 'application/problem+json', 'Cache-Control' => 'no-store' ) );
}

function axismundi_op_media_upload_authorize( WP_REST_Request $request ) {
	$user = get_current_user_id();
	if ( ! $user ) { return axismundi_op_media_upload_error( 'ax_op_media_auth', 'Authentication is required.', 401 ); }
	if ( ! rest_get_authenticated_app_password() && ! wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
		return axismundi_op_media_upload_error( 'ax_op_media_nonce', 'Cookie authentication requires a REST nonce.', 403 );
	}
	$actor = axismundi_actors_get_by_uuid( (string) $request->get_url_params()['uuid'] );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || 'public' !== $actor->get_status()
		|| ! $actor->is_handle_locked() || ! axismundi_actors_can_act_as( $actor, $user ) || ! user_can( $user, 'upload_files' ) ) {
		return axismundi_op_media_upload_error( 'ax_op_media_forbidden', 'The principal cannot upload for this Actor.', 403 );
	}
	return $actor;
}

function axismundi_op_media_upload_mimes() : array {
	return array( 'webm' => 'video/webm', 'mp4' => 'video/mp4' );
}

function axismundi_op_media_property_shape( $value, string $shape ) : bool {
	if ( is_array( $value ) && array_is_list( $value ) ) {
		foreach ( $value as $member ) {
			if ( ! axismundi_op_media_property_shape( $member, $shape ) ) { return false; }
		}
		return true;
	}
	return match ( $shape ) {
		'iri' => is_string( $value ) && null !== axismundi_as2_normalize_uri( $value ),
		'iri-or-link', 'iri-or-object', 'collection', 'members', 'ordered-members' =>
			is_array( $value ) || ( is_string( $value ) && null !== axismundi_as2_normalize_uri( $value ) ),
		'number' => is_int( $value ) || is_float( $value ),
		'boolean-or-timestamp-or-object' => is_bool( $value ) || is_array( $value ) || is_string( $value ),
		default => is_string( $value ),
	};
}

/** Core moves only a verified HTTP upload; the scoped filter never re-enters wp_upload_dir(). */
function axismundi_op_media_receive( array $file, array $mimes, bool $sideload = false ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$subdir = '/axismundi-object-projections/blobs/incoming/' . wp_generate_uuid4();
	$redirect = static function ( array $dirs ) use ( $subdir ) : array {
		$dirs['subdir'] = $subdir;
		$dirs['path'] = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url'] = $dirs['baseurl'] . $dirs['subdir'];
		return $dirs;
	};
	add_filter( 'upload_dir', $redirect, PHP_INT_MAX );
	try {
		$overrides = array( 'test_form' => false, 'mimes' => $mimes );
		return $sideload ? wp_handle_sideload( $file, $overrides ) : wp_handle_upload( $file, $overrides );
	} finally {
		remove_filter( 'upload_dir', $redirect, PHP_INT_MAX );
	}
}

function axismundi_op_media_upload_submit( WP_REST_Request $request, Axismundi_Actor $actor ) {
	global $wpdb;
	if ( '1' !== get_option( 'ax_op_media_upload_schema' ) || '1' !== get_option( 'ax_op_local_objects_schema' ) ) {
		return axismundi_op_media_upload_error( 'ax_op_media_store', 'Media storage is unavailable.', 503 );
	}
	$key = (string) $request->get_header( 'Idempotency-Key' );
	if ( '' !== $key && ! preg_match( '/^[\x21-\x7e]{1,128}$/D', $key ) ) {
		return axismundi_op_media_upload_error( 'ax_op_media_key', 'Invalid Idempotency-Key.', 400 );
	}
	$parts = $request->get_body_params();
	$files = $request->get_file_params();
	// PHP can expose an application/ld+json Blob part as an uploaded file.
	$raw = $parts['object'] ?? null;
	if ( null === $raw && isset( $files['object']['tmp_name'] ) && is_string( $files['object']['tmp_name'] )
		&& is_uploaded_file( $files['object']['tmp_name'] ) && filesize( $files['object']['tmp_name'] ) <= 900000 ) {
		$raw = file_get_contents( $files['object']['tmp_name'] );
	}
	$input = is_string( $raw ) && strlen( $raw ) <= 900000 ? json_decode( $raw, true, 64 ) : null;
	if ( ! is_array( $input ) || array_is_list( $input ) ) {
		return axismundi_op_media_upload_error( 'ax_op_media_object', 'An AS2 object part is required.', 400 );
	}
	foreach ( array_keys( $input ) as $field ) {
		if ( ! axismundi_op_local_property_known( (string) $field ) ) {
			return axismundi_op_media_upload_error( 'ax_op_media_property', 'Unsupported Object property: ' . $field, 422 );
		}
		$shape = axismundi_op_vocabulary_property( (string) $field )['value_shape'] ?? null;
		if ( is_string( $shape ) && ! axismundi_op_media_property_shape( $input[$field], $shape ) ) {
			return axismundi_op_media_upload_error( 'ax_op_media_property_shape', 'Invalid Object property value: ' . $field, 422 );
		}
	}
	$document = axismundi_op_prepare_local_document( $input, $actor, array( 'Video' ) );
	if ( is_wp_error( $document ) ) { return $document; }
	foreach ( array( 'mediaType', 'duration', 'width', 'height', 'replies', 'likes', 'shares' ) as $derived ) { unset( $document[$derived] ); }
	$file = $files['file'] ?? null;
	if ( ! is_array( $file ) || ! is_int( $file['error'] ?? null ) || UPLOAD_ERR_OK !== $file['error']
		|| ! is_string( $file['tmp_name'] ?? null ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return axismundi_op_media_upload_error( 'ax_op_media_file', 'A successful binary file upload is required.', 400 );
	}
	$size = filesize( $file['tmp_name'] );
	if ( false === $size || 0 === $size || $size > wp_max_upload_size() ) {
		return axismundi_op_media_upload_error( 'ax_op_media_size', 'The file is empty or exceeds the upload limit.', 413 );
	}
	$mimes = axismundi_op_media_upload_mimes();
	$mime = class_exists( 'finfo' ) ? ( new finfo( FILEINFO_MIME_TYPE ) )->file( $file['tmp_name'] ) : false;
	$ext = array_search( $mime, $mimes, true );
	if ( false === $ext ) { return axismundi_op_media_upload_error( 'ax_op_media_type', 'The file is not an allowed video format.', 415 ); }
	$hash = hash_file( 'sha256', $file['tmp_name'] );
	if ( ! is_string( $hash ) ) { return axismundi_op_media_upload_error( 'ax_op_media_hash', 'Cannot read uploaded bytes.', 500 ); }
	$sort = static function ( $value ) use ( &$sort ) {
		if ( is_array( $value ) ) {
			if ( ! array_is_list( $value ) ) { ksort( $value ); }
			return array_map( $sort, $value );
		}
		return $value;
	};
	$intent = hash( 'sha256', wp_json_encode( array( $hash, $sort( $input ) ) ) );
	$request_key = '' === $key ? null : hash( 'sha256', wp_json_encode( array( $actor->get_identity_id(), get_current_user_id(), $key ) ) );
	$lock = null === $request_key ? null : 'ax-media-' . substr( hash( 'sha256', DB_NAME . ':' . $wpdb->prefix . ':' . $request_key ), 0, 55 );
	if ( null !== $lock && '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
		return axismundi_op_media_upload_error( 'ax_op_media_busy', 'The upload is busy. Retry the request.', 503 );
	}
	try {
		$table = axismundi_op_media_upload_table();
		if ( null !== $request_key ) {
			$prior = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE request_key=%s', $table, $request_key ), ARRAY_A );
			if ( $wpdb->last_error ) { return axismundi_op_media_upload_error( 'ax_op_media_store', 'Media storage is unavailable.', 503 ); }
			if ( $prior ) {
				if ( ! hash_equals( $prior['intent_hash'], $intent ) ) { return axismundi_op_media_upload_error( 'ax_op_media_conflict', 'Idempotency-Key already identifies different input.', 409 ); }
				return axismundi_op_media_upload_response( axismundi_op_local_object_uri( $prior['local_uuid'] ), 200 );
			}
		}
		$uploads = wp_upload_dir( null, false );
		$relative = 'axismundi-object-projections/blobs/' . substr( $hash, 0, 2 ) . '/' . $hash . '.' . $ext;
		$target = $uploads['basedir'] . '/' . $relative;
		if ( ! empty( $uploads['error'] ) || ! wp_mkdir_p( dirname( $target ) ) ) {
			return axismundi_op_media_upload_error( 'ax_op_media_store', 'Media storage is unavailable.', 503 );
		}
		// The name and MIME passed to Core come from inspected bytes, not the multipart headers.
		$file['name'] = $hash . '.' . $ext;
		$file['type'] = $mime;
		$received = axismundi_op_media_receive( $file, $mimes );
		if ( empty( $received['file'] ) || ! empty( $received['error'] ) ) {
			return axismundi_op_media_upload_error( 'ax_op_media_receive', 'WordPress rejected the upload.', 400 );
		}
		$staged = $received['file'];
		try {
			if ( hash_file( 'sha256', $staged ) !== $hash || is_link( $target )
				|| ( is_file( $target ) ? hash_file( 'sha256', $target ) !== $hash : ! rename( $staged, $target ) ) ) {
				return axismundi_op_media_upload_error( 'ax_op_media_publish', 'Cannot publish verified media bytes.', 500 );
			}
		} finally {
			if ( is_file( $staged ) ) { wp_delete_file( $staged ); }
			$incoming = $uploads['basedir'] . '/axismundi-object-projections/blobs/incoming/';
			if ( str_starts_with( dirname( $staged ) . '/', $incoming ) ) { rmdir( dirname( $staged ) ); }
		}
		$uuid = wp_generate_uuid4();
		$uri = axismundi_op_local_object_uri( $uuid );
		$document['id'] = $uri;
		$document['url'] = $uploads['baseurl'] . '/' . $relative;
		$document['mediaType'] = $mime;
		$document['published'] = gmdate( 'c' );
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return axismundi_op_media_upload_error( 'ax_op_media_store', 'Cannot start the media transaction.', 500 );
		}
		$committed = false;
		try {
			$row = axismundi_op_write_local_object( $document, $uuid );
			$committed = ! is_wp_error( $row ) && false !== $wpdb->insert( $table, array(
				'local_uuid' => $uuid, 'request_key' => $request_key, 'intent_hash' => $intent, 'storage_path' => $relative,
			) ) && false !== $wpdb->query( 'COMMIT' );
		} finally {
			if ( ! $committed ) { $wpdb->query( 'ROLLBACK' ); }
		}
		return $committed ? axismundi_op_media_upload_response( $uri, 201 )
			: axismundi_op_media_upload_error( 'ax_op_media_write', 'Cannot record the media Object.', 500 );
	} finally {
		if ( null !== $lock ) { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
	}
}

function axismundi_op_media_upload_response( string $uri, int $status ) : WP_REST_Response {
	return new WP_REST_Response( array( 'id' => $uri ), $status, array( 'Location' => $uri, 'Cache-Control' => 'no-store' ) );
}

function axismundi_op_media_upload_rest( WP_REST_Request $request ) : WP_REST_Response {
	$actor = axismundi_op_media_upload_authorize( $request );
	$result = is_wp_error( $actor ) ? $actor : axismundi_op_media_upload_submit( $request, $actor );
	return is_wp_error( $result ) ? axismundi_op_media_upload_problem( $result ) : $result;
}

add_action( 'rest_api_init', static function () : void {
	register_rest_route( 'axismundi/activitypub', '/actors/(?P<uuid>[0-9a-f-]{36})/uploadMedia', array(
		'methods' => WP_REST_Server::CREATABLE,
		'permission_callback' => '__return_true',
		'callback' => 'axismundi_op_media_upload_rest',
	) );
} );
