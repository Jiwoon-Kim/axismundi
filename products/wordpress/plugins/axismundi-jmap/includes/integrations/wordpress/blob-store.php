<?php
/** Account-scoped immutable C2S bytes; not attachments or a remote-media cache. */
namespace Axismundi\Jmap\Blobs;
defined( 'ABSPATH' ) || exit;

const SCHEMA_VERSION = '4';
const PROBE_VERSION = 2;
const ID_PATTERN = 'b[0-9a-f]{64}';

function new_blob_id( string $digest ) : string { return 'b' . $digest; }
function valid_blob_id( string $id ) : bool { return 1 === preg_match( '/\A' . ID_PATTERN . '\z/D', $id ); }
/** Allocation policy only; existing content is always read from its recorded path. */
function blob_shard( string $id ) : string {
	$digest = substr( $id, 1 );
	return substr( $digest, 0, 2 ) . ( 2 === (int) apply_filters( 'axismundi_jmap_blob_shard_depth', 1 ) ? '/' . substr( $digest, 2, 2 ) : '' );
}

function valid_storage_path( $path ) : bool {
	if ( ! is_string( $path ) || '' === $path || strlen( $path ) > 255 || str_contains( $path, '\\' ) || preg_match( '/[^A-Za-z0-9_\/.\-]/', $path ) ) { return false; }
	foreach ( explode( '/', $path ) as $part ) {
		if ( '' === $part || '.' === $part || '..' === $part ) { return false; }
	}
	return true;
}

/** Both staging and final allocation reject symlinked directory components. */
function ensure_directory( string $root, string $relative ) : bool {
	if ( ! valid_storage_path( $relative ) ) { return false; }
	$directory = $root . '/' . $relative;
	if ( ! wp_mkdir_p( $directory ) ) { return false; }
	$component = $root;
	foreach ( explode( '/', $relative ) as $part ) {
		$component .= '/' . $part;
		if ( is_link( $component ) ) { return false; }
	}
	$resolved = realpath( $directory );
	return $resolved && within( $resolved, $root );
}

function table() : string { global $wpdb; return $wpdb->prefix . 'ax_jmap_blobs'; }
function refs_table() : string { global $wpdb; return $wpdb->prefix . 'ax_jmap_blob_refs'; }
function uploads_table() : string { global $wpdb; return $wpdb->prefix . 'ax_jmap_blob_uploads'; }
function failure( string $message, int $status = 503 ) : \WP_Error {
	return new \WP_Error( 'ax_jmap_blob', $message, array( 'status' => $status ) );
}

function install() : void {
	if ( '1' !== get_option( 'ax_jmap_db_version' ) || SCHEMA_VERSION === get_option( 'ax_jmap_blob_version' ) ) { return; }
	if ( ! lock( 'schema' ) ) { return; }
	try {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$blobs = table(); $refs = refs_table(); $uploads = uploads_table(); $charset = $wpdb->get_charset_collate();
	// dbDelta cannot replace an existing primary key.
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $refs ) ) ) === $refs ) {
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$refs}" );
		if ( ! in_array( 'account_identity_id', $columns, true ) && false === $wpdb->query( "ALTER TABLE {$refs} ADD account_identity_id bigint(20) unsigned NOT NULL DEFAULT 0" ) ) { return; }
		$primary = $wpdb->get_results( "SHOW INDEX FROM {$refs} WHERE Key_name='PRIMARY'", ARRAY_A );
		if ( ! in_array( 'account_identity_id', array_column( $primary ?: array(), 'Column_name' ), true ) && false === $wpdb->query( "ALTER TABLE {$refs} DROP PRIMARY KEY, ADD PRIMARY KEY (blob_id,account_identity_id,owner_kind,owner_ref)" ) ) { return; }
	}
	dbDelta( "CREATE TABLE {$blobs} (
		blob_id varchar(65) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		content_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		size bigint(20) unsigned NOT NULL,
		content_type varchar(255) NOT NULL,
		storage_root text NOT NULL,
		storage_path varchar(255) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		PRIMARY KEY  (blob_id)
	) ENGINE=InnoDB {$charset};
	CREATE TABLE {$refs} (
		blob_id varchar(65) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		account_identity_id bigint(20) unsigned NOT NULL DEFAULT 0,
		owner_kind varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		owner_ref varchar(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		PRIMARY KEY  (blob_id,account_identity_id,owner_kind,owner_ref)
	) ENGINE=InnoDB {$charset};
	CREATE TABLE {$uploads} (
		blob_id varchar(65) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		account_identity_id bigint(20) unsigned NOT NULL,
		uploader_user_id bigint(20) unsigned NOT NULL,
		size bigint(20) unsigned NOT NULL,
		content_type varchar(255) NOT NULL,
		created_at datetime NOT NULL,
		expires_at datetime NOT NULL,
		PRIMARY KEY  (blob_id,account_identity_id,uploader_user_id),
		KEY account_uploader (account_identity_id,uploader_user_id),
		KEY expires_at (expires_at)
	) ENGINE=InnoDB {$charset};" );
	if ( $wpdb->last_error ) { return; }
	foreach ( array( $blobs, $refs, $uploads ) as $table ) {
		$type = $wpdb->get_row( $wpdb->prepare( 'SHOW COLUMNS FROM %i WHERE Field=%s', $table, 'blob_id' ), ARRAY_A );
		if ( $wpdb->last_error || 'varchar(65)' !== strtolower( (string) ( $type['Type'] ?? '' ) ) ) { return; }
	}
	$primary = $wpdb->get_results( "SHOW INDEX FROM {$refs} WHERE Key_name='PRIMARY'", ARRAY_A );
	if ( ! in_array( 'account_identity_id', array_column( $primary ?: array(), 'Column_name' ), true ) && false === $wpdb->query( "ALTER TABLE {$refs} DROP PRIMARY KEY, ADD PRIMARY KEY (blob_id,account_identity_id,owner_kind,owner_ref)" ) ) { return; }
	$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$blobs}" );
	if ( $wpdb->last_error ) { return; }
	// Finish copying development ownership before removing its obsolete columns.
	if ( in_array( 'account_identity_id', $columns, true ) && in_array( 'uploader_user_id', $columns, true ) && in_array( 'expires_at', $columns, true ) ) {
		if ( false === $wpdb->query( "INSERT IGNORE INTO {$uploads} (blob_id,account_identity_id,uploader_user_id,size,content_type,created_at,expires_at) SELECT blob_id,account_identity_id,uploader_user_id,size,content_type,created_at,expires_at FROM {$blobs} WHERE account_identity_id>0 AND uploader_user_id>0" ) ) { return; }
		if ( false === $wpdb->query( "UPDATE {$refs} r INNER JOIN {$blobs} b ON b.blob_id=r.blob_id SET r.account_identity_id=b.account_identity_id WHERE r.account_identity_id=0 AND b.account_identity_id>0" ) ) { return; }
	}
	// Record the old layout once, without moving files or retaining public legacy routes.
	do {
		$rows = $wpdb->get_results( "SELECT * FROM {$blobs} WHERE storage_path='' LIMIT 100", ARRAY_A );
		if ( $wpdb->last_error ) { return; }
		foreach ( $rows as $entry ) {
			$id = $entry['blob_id'];
			if ( valid_blob_id( $id ) ) { $relative = substr( $id, 1, 2 ) . '/' . $id; }
			elseif ( preg_match( '/\A[a-f0-9]{64}\z/D', $id ) ) { $relative = 'a' . (int) ( $entry['account_identity_id'] ?? 0 ) . '/' . substr( $id, 0, 2 ) . '/' . $id; }
			elseif ( preg_match( '/\Ab[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $id ) ) { $relative = 'a' . (int) ( $entry['account_identity_id'] ?? 0 ) . '/' . substr( $id, 1, 2 ) . '/' . $id; }
			else { return; }
			if ( ! valid_storage_path( $relative ) || false === $wpdb->update( $blobs, array( 'storage_path' => $relative ), array( 'blob_id' => $id ) ) ) { return; }
		}
	} while ( count( $rows ) === 100 );
	foreach ( array( 'account_identity_id', 'uploader_user_id', 'expires_at' ) as $column ) {
		if ( in_array( $column, $columns, true ) && false === $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN %i', $blobs, $column ) ) ) { return; }
	}
	delete_option( 'ax_jmap_blob_verified' );
	update_option( 'ax_jmap_blob_version', SCHEMA_VERSION, false );
	} finally { unlock( 'schema' ); }
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\install', 30 );

function within( string $path, string $parent ) : bool {
	$path = untrailingslashit( wp_normalize_path( $path ) );
	$parent = untrailingslashit( wp_normalize_path( $parent ) );
	return $path === $parent || str_starts_with( $path, $parent . '/' );
}

/** Custom roots must be outside the actual public document root, content and uploads. */
function location() {
	$uploads = wp_upload_dir( null, false );
	if ( ! empty( $uploads['error'] ) ) { return failure( 'Uploads configuration is unavailable.' ); }
	$configured = trim( (string) get_option( 'ax_jmap_blob_root', '' ) );
	$path = '' === $configured ? $uploads['basedir'] . '/axismundi-jmap/blobs' : $configured;
	if ( ! path_is_absolute( $path ) || str_contains( $path, '://' ) || ! wp_mkdir_p( $path ) || ! realpath( $path ) || ! is_writable( $path ) ) { return failure( 'Storage root must be a writable absolute local directory.' ); }
	$root = wp_normalize_path( realpath( $path ) );
	$default = wp_normalize_path( realpath( $uploads['basedir'] ) ?: $uploads['basedir'] ) . '/axismundi-jmap/blobs';
	$public = '' === $configured || $root === $default;
	if ( ! $public ) {
		foreach ( array( ABSPATH, WP_CONTENT_DIR, $uploads['basedir'], $_SERVER['DOCUMENT_ROOT'] ?? ABSPATH ) as $parent ) {
			if ( $parent && ( within( $root, realpath( $parent ) ?: $parent ) || within( realpath( $parent ) ?: $parent, $root ) ) ) { return failure( 'Custom roots must be outside, and not contain, the public web root and uploads.' ); }
		}
	}
	return array( 'root' => $root, 'public' => $public, 'url' => $public ? $uploads['baseurl'] . '/axismundi-jmap/blobs' : null );
}

function guard( string $root ) : bool {
	$rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
	$files = array( '.htaccess' => $rules, 'web.config' => '<?xml version="1.0"?><configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security></system.webServer></configuration>', 'index.php' => "<?php\n// Silence is golden.\n" );
	foreach ( $files as $name => $body ) {
		$file = $root . '/' . $name;
		if ( is_link( $file ) ) { return false; }
		if ( is_readable( $file ) && file_get_contents( $file ) === $body ) { continue; }
		if ( ( file_exists( $file ) && ! is_writable( $file ) ) || ! is_writable( $root ) || strlen( $body ) !== file_put_contents( $file, $body, LOCK_EX ) ) { return false; }
	}
	return true;
}

/** Match directory entries, not incidental two-character substrings in an error page. */
function exposes_directory_entries( string $body, string $id ) : bool {
	if ( str_contains( $body, $id ) ) { return true; }
	$names = array( 'staging', explode( '/', blob_shard( $id ) )[0] );
	$html = new \WP_HTML_Tag_Processor( $body );
	while ( $html->next_tag( 'A' ) ) {
		$href = $html->get_attribute( 'href' );
		if ( ! is_string( $href ) ) { continue; }
		$path = wp_parse_url( $href, PHP_URL_PATH );
		if ( is_string( $path ) && in_array( basename( rtrim( rawurldecode( $path ), '/' ) ), $names, true ) ) { return true; }
	}
	foreach ( preg_split( '/\r\n|\r|\n/', trim( wp_strip_all_tags( $body ) ) ) as $line ) {
		if ( in_array( rtrim( trim( $line ), '/' ), $names, true ) ) { return true; }
	}
	return false;
}

/** A short-lived operational check, not a guarantee against future host/CDN changes. */
function verify_storage() {
	delete_option( 'ax_jmap_blob_verified' );
	$place = location();
	if ( is_wp_error( $place ) ) { return $place; }
	if ( $place['public'] && ! guard( $place['root'] ) ) { return failure( 'Storage guards could not be written.' ); }
	if ( ! $place['public'] ) {
		$result = array( 'root' => $place['root'], 'checked_at' => time(), 'mode' => 'outside-web-root' );
	} else {
		$bytes = random_bytes( 128 );
		$id = new_blob_id( hash( 'sha256', $bytes ) );
		$directory = $place['root'] . '/' . blob_shard( $id );
		if ( ! ensure_directory( $place['root'], blob_shard( $id ) ) ) { return failure( 'Probe directory is unavailable.' ); }
		$path = $directory . '/' . $id;
		if ( 128 !== file_put_contents( $path, $bytes, LOCK_EX ) ) { return failure( 'Probe file could not be written.' ); }
		$codes = array();
		try {
			foreach ( array( false, true, 'root' ) as $probe ) {
				$url = 'root' === $probe ? trailingslashit( $place['url'] ) : $place['url'] . '/' . blob_shard( $id ) . '/' . $id;
				$args = array( 'timeout' => 15, 'redirection' => 5, 'limit_response_size' => 1048576, 'cookies' => array(), 'headers' => array( 'Cache-Control' => 'no-cache' ) );
				if ( true === $probe ) { $args['headers']['Range'] = 'bytes=0-15'; }
				// Deployment adapters may route a loopback through an internal origin; never bypass the byte/status checks.
				$request = apply_filters( 'axismundi_jmap_blob_probe_request', array( 'url' => $url, 'args' => $args ) );
				if ( ! is_array( $request ) || ! is_string( $request['url'] ?? null ) || ! is_array( $request['args'] ?? null ) ) { return failure( 'Invalid storage probe routing.' ); }
				$request['args']['cookies'] = array();
				foreach ( array_keys( $request['args']['headers'] ?? array() ) as $header ) {
					if ( in_array( strtolower( $header ), array( 'cookie', 'authorization' ), true ) ) { unset( $request['args']['headers'][$header] ); }
				}
				$response = wp_remote_get( $request['url'], $request['args'] );
				if ( is_wp_error( $response ) ) { return failure( 'Storage verification could not reach the static URL.' ); }
				$body = wp_remote_retrieve_body( $response );
				$code = wp_remote_retrieve_response_code( $response );
				if ( str_contains( $body, substr( $bytes, 0, 16 ) ) || str_contains( $body, $bytes ) ) { return failure( 'Storage exposes private bytes through a static URL.' ); }
				if ( 'root' === $probe && exposes_directory_entries( $body, $id ) ) { return failure( 'Storage exposes directory entries through its root URL.' ); }
				if ( ! in_array( $code, array( 401, 403, 404, 410 ), true ) ) { return failure( 'Static access denial could not be established.' ); }
				$codes[] = $code;
			}
			$result = array( 'root' => $place['root'], 'checked_at' => time(), 'mode' => 'static-denied', 'http' => $codes );
		} finally { wp_delete_file( $path ); }
	}
	$result['layout'] = blob_shard( 'b' . str_repeat( '0', 64 ) );
	$result['probe_version'] = PROBE_VERSION;
	update_option( 'ax_jmap_blob_verified', $result, false );
	return $result;
}

function ready() {
	if ( SCHEMA_VERSION !== get_option( 'ax_jmap_blob_version' ) ) { return failure( 'Blob tables are unavailable.' ); }
	$place = location();
	if ( is_wp_error( $place ) ) { return $place; }
	if ( $place['public'] && ! guard( $place['root'] ) ) { delete_option( 'ax_jmap_blob_verified' ); return failure( 'Storage guards are unavailable.' ); }
	$verified = get_option( 'ax_jmap_blob_verified', array() );
	if ( ( $verified['probe_version'] ?? 0 ) !== PROBE_VERSION || ( $verified['root'] ?? '' ) !== $place['root'] || ( $verified['layout'] ?? '' ) !== blob_shard( 'b' . str_repeat( '0', 64 ) ) || (int) ( $verified['checked_at'] ?? 0 ) < time() - 600 ) {
		delete_option( 'ax_jmap_blob_verified' );
		$verified = verify_storage();
		if ( is_wp_error( $verified ) ) { return $verified; }
	}
	return $place;
}

function actor( string $account, bool $write = false ) {
	$id = get_current_user_id();
	$actor = \Axismundi\Jmap\resolve_account_actor( $account, $id );
	if ( ! current_user_can( 'read' ) || ! $actor || ( $write && ! in_array( axismundi_actors_account_role( $actor->get_identity_id(), $id ), array( 'owner', 'editor' ), true ) ) ) { return failure( 'Account is unavailable or read-only.', 403 ); }
	return $actor;
}

function project_account( $account, $actor, $user ) {
	// Core blob upload is not publication and does not require a public profile or locked handle.
	if ( in_array( axismundi_actors_account_role( $actor->get_identity_id(), $user ), array( 'owner', 'editor' ), true ) ) { $account->isReadOnly = false; }
	return $account;
}
add_filter( 'axismundi_jmap_account_projection', __NAMESPACE__ . '\\project_account', 10, 3 );

function default_max_size() : int { return min( 10485760, (int) wp_max_upload_size() ); }
function max_size() : int { return \Axismundi\Jmap\server()->core_properties()->maxSizeUpload; }
add_filter( 'axismundi_jmap_core_configuration', static function ( $config ) {
	$config->maxSizeUpload = min( $config->maxSizeUpload ?? default_max_size(), default_max_size() );
	$config->maxConcurrentUpload = min( $config->maxConcurrentUpload ?? 1, 1 );
	// Merge defaults because CoreConfiguration otherwise treats this as a complete configuration.
	return (object) array_merge( (array) ( new \Axismundi\Jmap\CoreConfiguration() )->properties(), (array) $config );
}, PHP_INT_MAX );

function lock( string $key ) : bool {
	global $wpdb;
	return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', 'axblob:' . substr( hash( 'sha256', $wpdb->prefix . $key ), 0, 48 ) ) );
}
function unlock( string $key ) : void {
	global $wpdb;
	$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'axblob:' . substr( hash( 'sha256', $wpdb->prefix . $key ), 0, 48 ) ) );
}

/** Both admin multipart and raw HTTP upload call this service with a local temporary file. */
function store( string $account, array $file, bool $http_upload = false ) {
	$owner = actor( $account, true );
	if ( is_wp_error( $owner ) ) { return $owner; }
	if ( max_size() < 1 || \Axismundi\Jmap\server()->core_properties()->maxConcurrentUpload < 1 ) { return failure( 'Uploads are disabled by Core configuration.' ); }
	if ( ! is_string( $file['name'] ?? null ) || ! is_string( $file['type'] ?? null ) ) { return failure( 'Invalid upload metadata.', 400 ); }
	$size = is_string( $file['tmp_name'] ?? null ) && is_file( $file['tmp_name'] ) ? filesize( $file['tmp_name'] ) : false;
	if ( false === $size || $size > max_size() || UPLOAD_ERR_OK !== ( $file['error'] ?? UPLOAD_ERR_OK ) ) { return failure( 'Upload is too large or incomplete.', 413 ); }
	$place = ready();
	if ( is_wp_error( $place ) ) { return $place; }
	$key = 'upload:' . get_current_user_id();
	if ( ! lock( $key ) ) { return failure( 'Another upload is in progress.', 429 ); }
	$staged = null; $destination = null; $content_key = null;
	try {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$original_hash = hash_file( 'sha256', $file['tmp_name'] );
		$mimes = get_allowed_mime_types( get_current_user_id() );
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
		// Administrators with unfiltered_upload must not bypass the blob store's Core file policy.
		if ( ! $checked['ext'] || ! $checked['type'] ) { return failure( 'Core upload validation rejected this file.', 415 ); }
		// Always validate and stage the complete upload before checking for a dedup hit.
		$temporary_id = 'stage-' . wp_generate_uuid4();
		$directory = $place['root'] . '/staging';
		if ( ! ensure_directory( $place['root'], 'staging' ) ) { return failure( 'Blob directory is unavailable.' ); }
		$filter = static fn( $dirs ) => array_merge( $dirs, array( 'path' => $directory, 'basedir' => $place['root'], 'subdir' => '', 'url' => '', 'baseurl' => '', 'error' => false ) );
		add_filter( 'upload_dir', $filter, 999 );
		try {
			$overrides = array( 'test_form' => false, 'test_size' => false, 'mimes' => $mimes, 'unique_filename_callback' => static fn( $dir, $name, $ext ) => $temporary_id . $ext );
			$accepted = $http_upload ? wp_handle_upload( $file, $overrides ) : wp_handle_sideload( $file, $overrides );
		} finally { remove_filter( 'upload_dir', $filter, 999 ); }
		if ( ! is_array( $accepted ) || isset( $accepted['error'] ) ) { return failure( 'Core upload validation rejected this file.', 415 ); }
		$staged = $accepted['file'];
		$digest = hash_file( 'sha256', $staged );
		if ( ! is_string( $original_hash ) || ! is_string( $digest ) || ! hash_equals( $original_hash, $digest ) || filesize( $staged ) !== $size ) { return failure( 'Upload processing changed the blob bytes.', 500 ); }
		$type = (string) ( $file['type'] ?? $accepted['type'] );
		if ( strlen( $type ) > 255 || preg_match( '/[\x00-\x1f\x7f]/', $type ) || ! preg_match( '~\A[a-zA-Z0-9!#$&^_.+-]+/[a-zA-Z0-9!#$&^_.+-]+(?:;[^\r\n]*)?\z~D', $type ) ) { return failure( 'Invalid media type.', 400 ); }
		global $wpdb;
		$id = new_blob_id( $digest );
		if ( ! lock( $id ) ) { return failure( 'Blob is busy.', 409 ); }
		$content_key = $id;
		$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE blob_id=%s', table(), $id ), ARRAY_A );
		if ( $wpdb->last_error ) { return failure( 'Blob metadata is unavailable.' ); }
		if ( $existing ) {
			$existing_path = path( $existing );
			if ( is_wp_error( $existing_path ) || (int) $existing['size'] !== $size || ! hash_equals( $digest, (string) hash_file( 'sha256', $existing_path ) ) ) { return failure( 'Stored blob integrity check failed.' ); }
		} else {
			$relative = blob_shard( $id ) . '/' . $id;
			$directory = $place['root'] . '/' . dirname( $relative );
			if ( ! ensure_directory( $place['root'], dirname( $relative ) ) ) { return failure( 'Blob directory is unavailable.' ); }
			$target = $directory . '/' . $id;
			if ( file_exists( $target ) || is_link( $target ) || ! rename( $staged, $target ) ) { return failure( 'Immutable blob could not be stored.' ); }
			$staged = null; $destination = $target;
			$ok = $wpdb->insert( table(), array( 'blob_id' => $id, 'content_hash' => $digest, 'size' => $size, 'content_type' => 'application/octet-stream', 'storage_root' => $place['root'], 'storage_path' => $relative, 'created_at' => gmdate( 'Y-m-d H:i:s' ) ) );
			if ( false === $ok ) { return failure( 'Blob metadata could not be stored.' ); }
			// Keep valid content on upload-row failure; a later sweep can reclaim the orphan.
			$destination = null;
		}
		$ok = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (blob_id,account_identity_id,uploader_user_id,size,content_type,created_at,expires_at) VALUES (%s,%d,%d,%d,%s,%s,%s) ON DUPLICATE KEY UPDATE expires_at=VALUES(expires_at),content_type=VALUES(content_type)', uploads_table(), $id, $owner->get_identity_id(), get_current_user_id(), $size, $type, gmdate( 'Y-m-d H:i:s' ), gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) ) );
		if ( false === $ok ) { return failure( 'Upload ownership could not be stored.' ); }
		$destination = null;
		return array( 'accountId' => $account, 'blobId' => $id, 'type' => $type, 'size' => $size );
	} finally {
		if ( $staged && is_file( $staged ) ) { wp_delete_file( $staged ); }
		if ( $destination && is_file( $destination ) ) { wp_delete_file( $destination ); }
		if ( $content_key ) { unlock( $content_key ); }
		unlock( $key );
	}
}

function row( string $account, string $id ) {
	$owner = actor( $account );
	if ( is_wp_error( $owner ) || ! valid_blob_id( $id ) ) { return failure( 'Blob not found.', 404 ); }
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT b.*,u.account_identity_id AS access_account_id,u.uploader_user_id,u.expires_at,u.content_type FROM %i b INNER JOIN %i u ON u.blob_id=b.blob_id WHERE b.blob_id=%s AND u.account_identity_id=%d AND u.uploader_user_id=%d', table(), uploads_table(), $id, $owner->get_identity_id(), get_current_user_id() ), ARRAY_A );
	// Referenced content may be readable without a personal upload, but never without an Account binding.
	if ( ! $row && ! $wpdb->last_error ) {
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT b.*,r.account_identity_id AS access_account_id FROM %i b INNER JOIN %i r ON r.blob_id=b.blob_id WHERE b.blob_id=%s AND r.account_identity_id=%d LIMIT 1', table(), refs_table(), $id, $owner->get_identity_id() ), ARRAY_A );
	}
	if ( ! $row || $wpdb->last_error ) { return failure( 'Blob not found.', 404 ); }
	$refs = $wpdb->get_results( $wpdb->prepare( 'SELECT owner_kind,owner_ref FROM %i WHERE blob_id=%s AND account_identity_id=%d', refs_table(), $id, $owner->get_identity_id() ), ARRAY_A );
	if ( $wpdb->last_error ) { return failure( 'Reference storage is unavailable.' ); }
	if ( ! $refs ) {
		if ( (int) ( $row['uploader_user_id'] ?? 0 ) !== get_current_user_id() || ( $row['expires_at'] ?? '' ) <= gmdate( 'Y-m-d H:i:s' ) ) { return failure( 'Blob not found.', 404 ); }
	} else {
		// A referencing backend must explicitly grant object read access. Account membership is insufficient.
		$allowed = false;
		foreach ( $refs as $ref ) { $allowed = $allowed || true === apply_filters( 'axismundi_jmap_blob_reference_readable', false, $ref, $account, get_current_user_id() ); }
		if ( ! $allowed ) { return failure( 'Blob not found.', 404 ); }
	}
	return $row;
}

function path( array $row ) {
	$relative = $row['storage_path'] ?? null;
	$root = $row['storage_root'] ?? null;
	if ( ! valid_storage_path( $relative ) || basename( $relative ) !== ( $row['blob_id'] ?? null ) || ! is_string( $root ) || ! path_is_absolute( $root ) || str_contains( $root, '://' ) ) { return failure( 'Blob bytes are unavailable.', 404 ); }
	$resolved_root = realpath( $root );
	if ( ! $resolved_root ) { return failure( 'Blob bytes are unavailable.', 404 ); }
	$file = $root;
	// Reject symlinks in every relative component, not only the final file.
	foreach ( explode( '/', $relative ) as $part ) {
		$file .= '/' . $part;
		if ( is_link( $file ) ) { return failure( 'Blob bytes are unavailable.', 404 ); }
	}
	$resolved = realpath( $file );
	if ( ! $resolved || ! within( $resolved, $resolved_root ) || ! is_file( $resolved ) || ! is_readable( $resolved ) ) { return failure( 'Blob bytes are unavailable.', 404 ); }
	return $resolved;
}

/** Trusted object repositories call this while committing/removing their own reference. */
function reference( string $account, string $id, string $kind, string $ref, bool $add = true ) {
	$owner = actor( $account, true );
	if ( is_wp_error( $owner ) || ! valid_blob_id( $id ) || ! preg_match( '/\A[A-Za-z][A-Za-z0-9_-]{0,63}\z/D', $kind ) || '' === $ref || strlen( $ref ) > 191 || preg_match( '/[^\x20-\x7e]/', $ref ) ) { return failure( 'Invalid blob reference.', 400 ); }
	if ( ! lock( $id ) ) { return failure( 'Blob is busy.', 409 ); }
	try {
		global $wpdb;
		if ( $add && is_wp_error( row( $account, $id ) ) ) { return failure( 'Blob is not readable.', 404 ); }
		if ( $add ) { $ok = $wpdb->replace( refs_table(), array( 'blob_id' => $id, 'account_identity_id' => $owner->get_identity_id(), 'owner_kind' => $kind, 'owner_ref' => $ref ) ); }
		else {
			$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT blob_id FROM %i WHERE blob_id=%s AND account_identity_id=%d AND owner_kind=%s AND owner_ref=%s', refs_table(), $id, $owner->get_identity_id(), $kind, $ref ) );
			if ( ! $exists ) { return failure( 'Blob not found.', 404 ); }
			// Renew first: a failed renewal must not turn a pinned blob into immediately expired bytes.
			if ( false === $wpdb->update( uploads_table(), array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) ), array( 'blob_id' => $id, 'account_identity_id' => $owner->get_identity_id() ) ) ) { return failure( 'Reference grace period could not be renewed.' ); }
			$ok = $wpdb->delete( refs_table(), array( 'blob_id' => $id, 'account_identity_id' => $owner->get_identity_id(), 'owner_kind' => $kind, 'owner_ref' => $ref ) );
		}
		return false !== $ok ? true : failure( 'Reference could not be stored.' );
	} finally { unlock( $id ); }
}

function remove( string $account, string $id ) {
	$entry = row( $account, $id );
	if ( is_wp_error( $entry ) ) { return $entry; }
	if ( (int) ( $entry['uploader_user_id'] ?? 0 ) !== get_current_user_id() || is_wp_error( actor( $account, true ) ) ) { return failure( 'Deletion is not permitted.', 403 ); }
	if ( ! lock( $id ) ) { return failure( 'Blob is busy.', 409 ); }
	try {
		global $wpdb;
		$refs = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE blob_id=%s AND account_identity_id=%d', refs_table(), $id, $entry['access_account_id'] ) );
		if ( $wpdb->last_error || null === $refs ) { return failure( 'Reference storage is unavailable.' ); }
		if ( (int) $refs ) { return failure( 'Referenced blobs cannot be deleted here.', 409 ); }
		if ( false === $wpdb->delete( uploads_table(), array( 'blob_id' => $id, 'account_identity_id' => $entry['access_account_id'], 'uploader_user_id' => get_current_user_id() ) ) ) { return failure( 'Upload could not be removed.' ); }
		return collect_content( $entry );
	} finally { unlock( $id ); }
}

/** Caller holds the content lock; other uploaders and all Accounts retain their bytes. */
function collect_content( array $entry ) {
	global $wpdb;
	$id = $entry['blob_id'];
	foreach ( array( refs_table(), uploads_table() ) as $table ) {
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE blob_id=%s', $table, $id ) );
		if ( $wpdb->last_error || null === $count ) { return failure( 'Blob bindings could not be checked.' ); }
		if ( (int) $count ) { return true; }
	}
	$file = path( $entry );
	if ( ! is_wp_error( $file ) ) {
		wp_delete_file( $file );
		clearstatcache( true, $file );
		if ( is_file( $file ) ) { return failure( 'Blob file could not be removed.' ); }
	} else {
		// Do not erase evidence of a broken path or unreadable existing file.
		return $file;
	}
	return false !== $wpdb->delete( table(), array( 'blob_id' => $id ) ) ? true : failure( 'Blob row could not be removed.' );
}

function delete_unreferenced( array $entry, bool $expired_only = false ) {
	$id = $entry['blob_id'];
	if ( ! lock( $id ) ) { return failure( 'Blob is busy.', 409 ); }
	try {
		global $wpdb;
		if ( $expired_only ) {
			$ok = $wpdb->query( $wpdb->prepare( 'DELETE u FROM %i u WHERE u.blob_id=%s AND u.expires_at<UTC_TIMESTAMP() AND NOT EXISTS (SELECT 1 FROM %i r WHERE r.blob_id=u.blob_id AND r.account_identity_id=u.account_identity_id)', uploads_table(), $id, refs_table() ) );
			if ( false === $ok ) { return failure( 'Expired uploads could not be removed.' ); }
		}
		return collect_content( $entry );
	} finally { unlock( $id ); }
}

function expire() : void {
	if ( SCHEMA_VERSION !== get_option( 'ax_jmap_blob_version' ) ) { return; }
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT b.* FROM %i b WHERE EXISTS (SELECT 1 FROM %i u WHERE u.blob_id=b.blob_id AND u.expires_at<UTC_TIMESTAMP() AND NOT EXISTS (SELECT 1 FROM %i r WHERE r.blob_id=u.blob_id AND r.account_identity_id=u.account_identity_id)) OR (b.created_at<UTC_TIMESTAMP()-INTERVAL 1 DAY AND NOT EXISTS (SELECT 1 FROM %i u WHERE u.blob_id=b.blob_id) AND NOT EXISTS (SELECT 1 FROM %i r WHERE r.blob_id=b.blob_id)) LIMIT 100', table(), uploads_table(), refs_table(), uploads_table(), refs_table() ), ARRAY_A );
	foreach ( $rows ?: array() as $entry ) { delete_unreferenced( $entry, true ); }
}

/** Account logical usage sums uploader bindings, independent of physical deduplication. */
function usage( string $account ) {
	$owner = actor( $account );
	if ( is_wp_error( $owner ) ) { return $owner; }
	global $wpdb;
	$size = $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(u.size),0) FROM %i u WHERE u.account_identity_id=%d AND (u.expires_at>=UTC_TIMESTAMP() OR EXISTS (SELECT 1 FROM %i r WHERE r.blob_id=u.blob_id AND r.account_identity_id=u.account_identity_id))', uploads_table(), $owner->get_identity_id(), refs_table() ) );
	return $wpdb->last_error ? failure( 'Usage is unavailable.' ) : (int) $size;
}
add_action( 'axismundi_jmap_blob_expire', __NAMESPACE__ . '\\expire' );
add_action( 'init', static function () : void {
	if ( ! wp_next_scheduled( 'axismundi_jmap_blob_expire' ) ) { wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'axismundi_jmap_blob_expire' ); }
} );
