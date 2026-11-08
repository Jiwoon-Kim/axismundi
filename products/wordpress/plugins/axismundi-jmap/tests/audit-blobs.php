<?php
/** Real storage, HTTP bytes, static full/Range denial and Account ACL regressions. */
defined( 'ABSPATH' ) || exit( 1 );
use Axismundi\Jmap\Blobs;
require_once ABSPATH . 'wp-admin/includes/user.php';
$checks = 0;
$assert = static function ( $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks; echo "PASS $label\n";
};
$origin = rtrim( getenv( 'AX_JMAP_HTTP_ORIGIN' ) ?: home_url(), '/' );
$home = wp_parse_url( home_url() );
$host = $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
$http = static function ( string $method, string $path, array $headers = array(), string $body = '' ) use ( $origin, $host ) {
	return wp_remote_request( $origin . $path, array( 'method' => $method, 'timeout' => 30, 'redirection' => 0, 'headers' => array_merge( array( 'Host' => $host ), $headers ), 'body' => $body ) );
};
$route_probe = static function ( $request ) use ( $origin, $host ) {
	$request['url'] = $origin . wp_parse_url( $request['url'], PHP_URL_PATH );
	$request['args']['headers']['Host'] = $host;
	return $request;
};
$initial = get_current_user_id(); $users = array(); $actors = array(); $files = array();
$saved_root = get_option( 'ax_jmap_blob_root', '' ); $saved_verified = get_option( 'ax_jmap_blob_verified', null );
$temp = null; $public_control = null; $listing_control = null;
try {
	update_option( 'ax_jmap_blob_root', '', false );
	add_filter( 'axismundi_jmap_blob_probe_request', $route_probe );
	$place = Blobs\location();
	$assert( is_array( $place ) && $place['public'], 'Default storage is the dedicated uploads directory' );
	$existing_canary_paths = glob( $place['root'] . '/*/b*' ) ?: array();
	$unreachable = static fn() => new WP_Error( 'test_loopback', 'Unreachable' );
	add_filter( 'pre_http_request', $unreachable );
	$assert( is_wp_error( Blobs\verify_storage() ) && ! get_option( 'ax_jmap_blob_verified' ), 'Loopback failure does not enable storage' );
	remove_filter( 'pre_http_request', $unreachable );
	$leak = static function ( $pre, $args, $url ) use ( $place ) {
		$relative = substr( wp_parse_url( $url, PHP_URL_PATH ), strlen( wp_parse_url( $place['url'], PHP_URL_PATH ) ) );
		if ( ! $relative ) { return $pre; }
		$body = file_get_contents( $place['root'] . $relative );
		return array( 'headers' => array(), 'body' => isset( $args['headers']['Range'] ) ? substr( $body, 0, 16 ) : $body, 'response' => array( 'code' => 206, 'message' => 'Partial Content' ) );
	};
	add_filter( 'pre_http_request', $leak, 10, 3 );
	$assert( is_wp_error( Blobs\verify_storage() ) && ! get_option( 'ax_jmap_blob_verified' ), 'Exposed bytes reject storage verification' );
	remove_filter( 'pre_http_request', $leak, 10 );
	$range_leak = static function ( $pre, $args, $url ) use ( $leak ) {
		return isset( $args['headers']['Range'] ) ? $leak( $pre, $args, $url ) : array( 'headers' => array(), 'body' => 'Denied', 'response' => array( 'code' => 403, 'message' => 'Forbidden' ) );
	};
	add_filter( 'pre_http_request', $range_leak, 10, 3 );
	$assert( is_wp_error( Blobs\verify_storage() ) && ! get_option( 'ax_jmap_blob_verified' ), 'A Range-only leak fails even when full GET is denied' );
	remove_filter( 'pre_http_request', $range_leak, 10 );
	$verified = Blobs\verify_storage();
	$assert( is_array( $verified ) && 3 === count( $verified['http'] ), 'Actual Apache blocks full GET, Range and the existing storage root' );
	$assert( ! array_diff( glob( $place['root'] . '/*/b*' ) ?: array(), $existing_canary_paths ), 'Canary files are removed' );
	$uploads = wp_upload_dir();
	// Enable indexing only in a disposable public directory containing dummy entries.
	$listing_control = $uploads['basedir'] . '/jmap-listing-control-' . wp_generate_uuid4();
	$assert( wp_mkdir_p( $listing_control ), 'Known-positive autoindex fixture directory exists' );
	file_put_contents( $listing_control . '/.htaccess', "Options +Indexes\nDirectoryIndex disabled\n" );
	file_put_contents( $listing_control . '/staging', 'Public listing audit marker' );
	$listing_path = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH ) . '/' . basename( $listing_control ) . '/';
	$listing = $http( 'GET', $listing_path );
	$sample_id = 'b01' . str_repeat( '0', 62 );
	$assert( ! is_wp_error( $listing ) && 200 === wp_remote_retrieve_response_code( $listing ) && Blobs\exposes_directory_entries( wp_remote_retrieve_body( $listing ), $sample_id ), 'Known-positive real Apache autoindex exposes the dummy entry to the detector' );
	$root_url_path = trailingslashit( wp_parse_url( $place['url'], PHP_URL_PATH ) );
	$root_requests = 0;
	$listed_root = static function ( $pre, $args, $url ) use ( $root_url_path, $listing, &$root_requests ) {
		if ( wp_parse_url( $url, PHP_URL_PATH ) !== $root_url_path ) { return $pre; }
		++$root_requests;
		return $listing;
	};
	add_filter( 'pre_http_request', $listed_root, 10, 3 );
	try { $assert( is_wp_error( Blobs\verify_storage() ) && 1 === $root_requests && ! get_option( 'ax_jmap_blob_verified' ), 'An autoindexed root fails storage verification even while file requests are denied' ); }
	finally { remove_filter( 'pre_http_request', $listed_root, 10 ); }
	$listing_denied_status = $listing;
	$listing_denied_status['response']['code'] = 403;
	$listed_error = static fn( $pre, $args, $url ) => wp_parse_url( $url, PHP_URL_PATH ) === $root_url_path ? $listing_denied_status : $pre;
	add_filter( 'pre_http_request', $listed_error, 10, 3 );
	try { $assert( is_wp_error( Blobs\verify_storage() ) && ! get_option( 'ax_jmap_blob_verified' ), 'A nominal 403 cannot hide directory entries present in its body' ); }
	finally { remove_filter( 'pre_http_request', $listed_error, 10 ); }
	$assert( Blobs\exposes_directory_entries( '<a href="01/">01/</a>', $sample_id ) && Blobs\exposes_directory_entries( "staging/\n", $sample_id ), 'Shard links and plain directory-entry lines are detected' );
	$assert( ! Blobs\exposes_directory_entries( '<p>Forbidden: request 01 was denied.</p>', $sample_id ), 'A two-character shard occurring inside an error sentence is not a listing' );
	$old_probe = $verified; $old_probe['probe_version'] = 1;
	update_option( 'ax_jmap_blob_verified', $old_probe, false );
	add_filter( 'pre_http_request', $unreachable );
	try { $assert( is_wp_error( Blobs\ready() ) && ! get_option( 'ax_jmap_blob_verified' ), 'Cached file-only verification cannot bypass the new root probe' ); }
	finally { remove_filter( 'pre_http_request', $unreachable ); }
	$assert( is_array( Blobs\verify_storage() ), 'Normal storage verification recovers after listing and loopback controls' );
	$public_control = $uploads['basedir'] . '/jmap-public-control-' . bin2hex( random_bytes( 8 ) ) . '.txt';
	$control_bytes = bin2hex( random_bytes( 64 ) );
	file_put_contents( $public_control, $control_bytes );
	$control = $http( 'GET', wp_parse_url( $uploads['baseurl'], PHP_URL_PATH ) . '/' . basename( $public_control ) );
	$assert( ! is_wp_error( $control ) && wp_remote_retrieve_body( $control ) === $control_bytes, 'Known-positive public static download proves the byte tester works' );
	foreach ( array( 'owner', 'collaborator', 'outsider' ) as $label ) {
		$user = wp_insert_user( array( 'user_login' => 'blob' . $label . bin2hex( random_bytes( 5 ) ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
		$assert( is_int( $user ), 'Temporary user created: ' . $label ); $users[] = $user;
	}
	wp_set_current_user( $users[0] );
	$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'personal_owner_user_id' => $users[0], 'initial_owner_user_id' => $users[0], 'display_name' => 'Blob Person fixture' ) );
	$assert( $actor instanceof Axismundi_Actor, 'Person Account is available for blob upload' ); $actors[] = $actor;
	$account = 'a' . $actor->get_identity_id();
	axismundi_actors_write_manager_row( $actor->get_identity_id(), $users[1], 'editor' );
	$bytes = "JMAP immutable binary fixture\n" . bin2hex( random_bytes( 32 ) );
	$temp = wp_tempnam( 'blob-audit' ); file_put_contents( $temp, $bytes );
	$post_count = (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} WHERE post_type='attachment'" );
	$entry = Blobs\store( $account, array( 'name' => 'original.txt', 'type' => 'text/plain', 'tmp_name' => $temp, 'size' => strlen( $bytes ), 'error' => UPLOAD_ERR_OK ) );
	if ( is_wp_error( $entry ) ) { echo 'UPLOAD ERROR: ' . $entry->get_error_message() . "\n"; }
	$assert( is_array( $entry ), 'Core-validated upload creates an Account blob' );
	$id = $entry['blobId']; $files[] = $id;
	$assert( 65 === strlen( $id ) && $id === 'b' . hash( 'sha256', $bytes ) && \Axismundi\Jmap\valid_id( $id ), 'New blobId is b plus the SHA-256 of the exact bytes' );
	$assert( '09' === Blobs\blob_shard( 'b09' . str_repeat( '0', 62 ) ) && 'ff' === Blobs\blob_shard( 'bff' . str_repeat( '0', 62 ) ), 'Content sharding skips the prefix and retains all 256 hex buckets' );
	$assert( ! Blobs\valid_blob_id( '../file' ) && ! Blobs\valid_blob_id( 'b098951e1-2a08-421e-889e-1c5239d17347' ) && ! Blobs\valid_blob_id( str_repeat( '0', 64 ) ) && ! Blobs\valid_blob_id( strtoupper( $id ) ), 'Traversal, development IDs, digit-start and case-variant IDs are rejected' );
	$row = Blobs\row( $account, $id );
	$assert( is_array( $row ) && $row['content_hash'] === hash( 'sha256', $bytes ), 'Stored digest matches uploaded bytes' );
	$disk = Blobs\path( $row );
	$assert( is_string( $disk ) && file_get_contents( $disk ) === $bytes && basename( $disk ) === $id && ! str_contains( $disk, 'original.txt' ), 'Opaque extensionless file preserves bytes without original filename' );
	$assert( $disk === $place['root'] . '/' . Blobs\blob_shard( $id ) . '/' . $id, 'Physical path has no Account segment' );
	$assert( $row['storage_path'] === Blobs\blob_shard( $id ) . '/' . $id && Blobs\valid_storage_path( $row['storage_path'] ), 'Known-positive recorded relative path resolves to the actual bytes' );
	$columns = $GLOBALS['wpdb']->get_col( 'SHOW COLUMNS FROM ' . Blobs\table() );
	$assert( in_array( 'storage_path', $columns, true ) && ! array_intersect( array( 'account_identity_id', 'uploader_user_id', 'expires_at' ), $columns ), 'Content schema records the path without obsolete ownership columns' );
	foreach ( array( '', '../' . basename( $place['root'] ) . '/' . $row['storage_path'], './' . $row['storage_path'], $disk, str_replace( '/', '\\', $row['storage_path'] ), 'file://' . $disk, 'bad//' . $id, 'other-id' ) as $unsafe_path ) {
		$bad_row = array_replace( $row, array( 'storage_path' => $unsafe_path ) );
		$bad_path = Blobs\path( $bad_row );
		$assert( is_wp_error( $bad_path ) && 404 === $bad_path->get_error_data()['status'], 'Invalid recorded path fails closed: ' . ( '' === $unsafe_path ? '(empty)' : $unsafe_path ) );
	}
	$link_name = 'audit-link-' . wp_generate_uuid4();
	$link_path = $place['root'] . '/' . $link_name;
	$assert( symlink( dirname( $disk ), $link_path ), 'Known-positive symlink points to a readable shard' );
	try {
		$symlink_row = array_replace( $row, array( 'storage_path' => $link_name . '/' . $id ) );
		$assert( is_wp_error( Blobs\path( $symlink_row ) ), 'A symlink in a middle path component is rejected even inside the root' );
		$assert( ! Blobs\ensure_directory( $place['root'], $link_name ), 'Allocation also rejects an existing symlinked directory' );
	} finally { unlink( $link_path ); }
	$assert( $post_count === (int) $GLOBALS['wpdb']->get_var( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->posts} WHERE post_type='attachment'" ), 'No attachment post is created' );
	$static_path = wp_parse_url( $place['url'], PHP_URL_PATH ) . '/' . Blobs\blob_shard( $id ) . '/' . $id;
	foreach ( array( array(), array( 'Range' => 'bytes=0-15' ) ) as $headers ) {
		$r = $http( 'GET', $static_path, $headers );
		$assert( ! is_wp_error( $r ) && in_array( wp_remote_retrieve_response_code( $r ), array( 401, 403, 404, 410 ), true ) && ! str_contains( wp_remote_retrieve_body( $r ), substr( $bytes, 0, 16 ) ), 'Real blob static full/Range requests do not expose bytes' );
	}
	$diagnostic = $http( 'GET', dirname( $static_path ) . '/' . str_repeat( '0', 64 ) );
	echo 'DIAGNOSTIC missing static URL status=' . wp_remote_retrieve_response_code( $diagnostic ) . ' sha256=' . hash( 'sha256', wp_remote_retrieve_body( $diagnostic ) ) . "\n";
	wp_set_current_user( $users[1] );
	$assert( ! is_wp_error( Blobs\actor( $account ) ) && is_wp_error( Blobs\row( $account, $id ) ), 'Shared Account membership does not expose another uploader\'s unreferenced blob' );
	wp_set_current_user( $users[2] );
	$assert( is_wp_error( Blobs\actor( $account ) ), 'Unrelated User cannot resolve the Account' );
	wp_set_current_user( $users[0] );
	$assert( is_wp_error( Blobs\row( 'a99999999', $id ) ), 'blobId cannot cross Account scope' );
	$oversize = new WP_REST_Request( 'POST', '/axismundi/jmap/upload/' . $account );
	$oversize->set_url_params( array( 'accountId' => $account ) );
	$oversize->set_body( str_repeat( 'x', Blobs\max_size() + 1 ) );
	$large_error = Blobs\upload( $oversize );
	$assert( is_wp_error( $large_error ) && 413 === $large_error->get_error_data()['status'], 'Raw upload size is bounded before temporary storage' );
	unset( $oversize );
	$assert( is_wp_error( Blobs\disposition( "bad\r\nheader" ) ) && is_wp_error( Blobs\disposition( '../file' ) ), 'Download filename rejects header and path injection' );
	$app = WP_Application_Passwords::create_new_application_password( $users[0], array( 'name' => 'Disposable blob audit' ) );
	$assert( is_array( $app ), 'Real Application Password created' );
	$auth = array( 'Authorization' => 'Basic ' . base64_encode( get_userdata( $users[0] )->user_login . ':' . $app[0] ) );
	$collaborator_app = WP_Application_Passwords::create_new_application_password( $users[1], array( 'name' => 'Disposable dedup audit' ) );
	$collaborator_auth = array( 'Authorization' => 'Basic ' . base64_encode( get_userdata( $users[1] )->user_login . ':' . $collaborator_app[0] ) );
	$start = hrtime( true );
	$duplicate = $http( 'POST', '/wp-json/axismundi/jmap/upload/' . $account, array_merge( $collaborator_auth, array( 'Content-Type' => 'text/plain' ) ), $bytes );
	echo 'DIAGNOSTIC duplicate-upload-seconds=' . ( ( hrtime( true ) - $start ) / 1e9 ) . "\n";
	$duplicate_data = json_decode( wp_remote_retrieve_body( $duplicate ), true );
	$assert( 201 === wp_remote_retrieve_response_code( $duplicate ) && $duplicate_data === $entry, 'A second uploader gets the same ID and identical response fields' );
	$assert( 2 === (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT COUNT(*) FROM %i WHERE blob_id=%s', Blobs\uploads_table(), $id ) ), 'Identical bytes have two independent uploader bindings' );
	foreach ( array( $auth, $collaborator_auth ) as $download_auth ) {
		$both = $http( 'GET', '/wp-json/axismundi/jmap/download/' . $account . '/' . $id . '/same.txt', $download_auth );
		$assert( 200 === wp_remote_retrieve_response_code( $both ) && $bytes === wp_remote_retrieve_body( $both ), 'Each uploader can download their own identical unreferenced upload' );
	}
	wp_set_current_user( $users[1] );
	$assert( 2 * strlen( $bytes ) === Blobs\usage( $account ), 'Account usage charges both uploaders full logical bytes despite physical reuse' );
	$GLOBALS['wpdb']->update( Blobs\uploads_table(), array( 'expires_at' => '2000-01-01 00:00:00' ), array( 'blob_id' => $id, 'uploader_user_id' => $users[1] ) );
	Blobs\expire();
	$assert( is_file( $disk ) && is_wp_error( Blobs\row( $account, $id ) ), 'One expired upload does not remove another uploader\'s bytes' );
	wp_set_current_user( $users[0] );
	$assert( strlen( $bytes ) === Blobs\usage( $account ) && is_array( Blobs\row( $account, $id ) ), 'The remaining uploader retains access and logical usage' );
	Blobs\lock( $id );
	try {
		$locked_duplicate = $http( 'POST', '/wp-json/axismundi/jmap/upload/' . $account, array_merge( $collaborator_auth, array( 'Content-Type' => 'text/plain' ) ), $bytes );
		$assert( 409 === wp_remote_retrieve_response_code( $locked_duplicate ) && is_file( $disk ), 'Content lock serializes another HTTP upload with collection' );
	} finally { Blobs\unlock( $id ); }
	$download_path = '/wp-json/axismundi/jmap/download/' . $account . '/' . $id . '/' . rawurlencode( '사진.txt' ) . '?type=text%2Fplain';
	$r = $http( 'GET', $download_path, $auth );
	if ( is_wp_error( $r ) || $bytes !== wp_remote_retrieve_body( $r ) ) { echo 'DOWNLOAD ERROR: ' . ( is_wp_error( $r ) ? $r->get_error_message() : wp_remote_retrieve_response_code( $r ) . ' ' . substr( wp_remote_retrieve_body( $r ), 0, 600 ) ) . "\n"; }
	$assert( ! is_wp_error( $r ) && 200 === wp_remote_retrieve_response_code( $r ) && $bytes === wp_remote_retrieve_body( $r ), 'Authenticated HTTP download returns exactly the uploaded bytes' );
	try {
		foreach ( array( '', '../' . basename( $place['root'] ) . '/' . $row['storage_path'] ) as $unsafe_path ) {
			$GLOBALS['wpdb']->update( Blobs\table(), array( 'storage_path' => $unsafe_path ), array( 'blob_id' => $id ) );
			$bad_download = $http( 'GET', $download_path, $auth );
			$assert( 404 === wp_remote_retrieve_response_code( $bad_download ) && ! str_contains( wp_remote_retrieve_body( $bad_download ), $bytes ), 'A corrupt DB storage_path is rejected by the real HTTP download route' );
		}
	} finally { $GLOBALS['wpdb']->update( Blobs\table(), array( 'storage_path' => $row['storage_path'] ), array( 'blob_id' => $id ) ); }
	echo 'DIAGNOSTIC disposition=' . wp_remote_retrieve_header( $r, 'content-disposition' ) . "\n";
	$assert( str_contains( wp_remote_retrieve_header( $r, 'content-disposition' ), "filename*=UTF-8''" . rawurlencode( '사진.txt' ) ), 'Download Content-Disposition preserves the requested UTF-8 name' );
	$assert( str_contains( wp_remote_retrieve_header( $r, 'cache-control' ), 'private' ) && 'nosniff' === wp_remote_retrieve_header( $r, 'x-content-type-options' ), 'Binary response remains private and cannot sniff executable content' );
	$anon = $http( 'GET', $download_path );
	$assert( ! is_wp_error( $anon ) && in_array( wp_remote_retrieve_response_code( $anon ), array( 401, 403 ), true ) && ! str_contains( wp_remote_retrieve_body( $anon ), substr( $bytes, 0, 16 ) ), 'Anonymous authenticated-route request does not expose bytes' );
	$raw_bytes = $bytes . 'raw';
	$start = hrtime( true );
	$raw = $http( 'POST', '/wp-json/axismundi/jmap/upload/' . $account, array_merge( $auth, array( 'Content-Type' => 'text/plain' ) ), $raw_bytes );
	echo 'DIAGNOSTIC new-http-upload-seconds=' . ( ( hrtime( true ) - $start ) / 1e9 ) . "\n";
	$raw_data = json_decode( wp_remote_retrieve_body( $raw ), true );
	$assert( ! is_wp_error( $raw ) && 201 === wp_remote_retrieve_response_code( $raw ) && isset( $raw_data['blobId'] ), 'Real raw HTTP upload uses the same blob store' );
	$files[] = $raw_data['blobId'];
	$start = hrtime( true );
	$raw_again = $http( 'POST', '/wp-json/axismundi/jmap/upload/' . $account, array_merge( $auth, array( 'Content-Type' => 'text/plain' ) ), $raw_bytes );
	echo 'DIAGNOSTIC same-http-upload-hit-seconds=' . ( ( hrtime( true ) - $start ) / 1e9 ) . "\n";
	$assert( wp_remote_retrieve_response_code( $raw ) === wp_remote_retrieve_response_code( $raw_again ) && wp_remote_retrieve_body( $raw ) === wp_remote_retrieve_body( $raw_again ) && 1 === (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT COUNT(*) FROM %i WHERE blob_id=%s', Blobs\uploads_table(), $raw_data['blobId'] ) ), 'Repeat upload returns identical HTTP status/body and renews one binding rather than charging twice' );
	Blobs\lock( 'upload:' . $users[0] );
	try { $busy = $http( 'POST', '/wp-json/axismundi/jmap/upload/' . $account, array_merge( $auth, array( 'Content-Type' => 'text/plain' ) ), $bytes ); }
	finally { Blobs\unlock( 'upload:' . $users[0] ); }
	$assert( 429 === wp_remote_retrieve_response_code( $busy ), 'Concurrent upload limit is enforced across HTTP workers' );
	$assert( $raw_data['accountId'] === $account && $raw_data['type'] === 'text/plain' && $raw_data['size'] === strlen( $raw_bytes ), 'Upload response reports accountId/blobId/type/size' );
	$expires = time() + 600;
	$token = WP_Session_Tokens::get_instance( $users[0] )->create( $expires );
	$logged_in = wp_generate_auth_cookie( $users[0], $expires, 'logged_in', $token );
	$_COOKIE[LOGGED_IN_COOKIE] = $logged_in;
	$cookie = LOGGED_IN_COOKIE . '=' . $logged_in . '; ' . AUTH_COOKIE . '=' . wp_generate_auth_cookie( $users[0], $expires, 'auth', $token );
	$nonce = wp_create_nonce( 'ax_jmap_blob_admin' );
	$link_path = $download_path . '&_wpnonce=' . rawurlencode( wp_create_nonce( 'wp_rest' ) );
	$link_download = $http( 'GET', $link_path, array( 'Cookie' => $cookie ) );
	$assert( 200 === wp_remote_retrieve_response_code( $link_download ) && $bytes === wp_remote_retrieve_body( $link_download ), 'Browser-style cookie plus query nonce downloads exact bytes without a custom header' );
	$nonce_only = $http( 'GET', $link_path );
	$assert( in_array( wp_remote_retrieve_response_code( $nonce_only ), array( 401, 403 ), true ), 'Query nonce alone does not replace authentication' );
	$invalid_link = $http( 'GET', $download_path . '&_wpnonce=invalid', array( 'Cookie' => $cookie ) );
	$assert( in_array( wp_remote_retrieve_response_code( $invalid_link ), array( 401, 403 ), true ), 'Cookie with invalid query nonce cannot download' );
	// Read the real admin HTML, not a hand-built URL, so the link producer is part of the regression.
	$admin_html = $http( 'GET', '/wp-admin/tools.php?page=axismundi-jmap-blobs&account=' . $account, array( 'Cookie' => $cookie ) );
	$parser = new WP_HTML_Tag_Processor( wp_remote_retrieve_body( $admin_html ) );
	$admin_url = null;
	while ( $parser->next_tag( 'A' ) ) {
		$href = $parser->get_attribute( 'href' );
		if ( is_string( $href ) && str_contains( $href, '/download/' . $account . '/' . $id . '/' ) ) { $admin_url = $href; break; }
	}
	$assert( 200 === wp_remote_retrieve_response_code( $admin_html ) && is_string( $admin_url ), 'Admin list exposes a download link for the stored blob' );
	$parts = wp_parse_url( $admin_url );
	parse_str( $parts['query'] ?? '', $admin_query );
	$assert( basename( $parts['path'] ) === $id . '.txt' && 'text/plain' === ( $admin_query['type'] ?? '' ), 'Admin URL supplies blobId plus extension and the stored MIME type' );
	$admin_download = $http( 'GET', $parts['path'] . '?' . $parts['query'], array( 'Cookie' => $cookie ) );
	$assert( 200 === wp_remote_retrieve_response_code( $admin_download ) && $bytes === wp_remote_retrieve_body( $admin_download ) && str_contains( wp_remote_retrieve_header( $admin_download, 'content-disposition' ), 'filename="' . $id . '.txt"' ) && str_starts_with( wp_remote_retrieve_header( $admin_download, 'content-type' ), 'text/plain' ), 'Following the actual admin link yields exact bytes with a usable filename and MIME type' );
	foreach ( array( 'image/png' => 'png', 'audio/ogg' => 'ogg', 'application/x-unknown-blob' => 'bin' ) as $mime => $extension ) {
		$url = \Axismundi\Jmap\Blobs\Admin\download_url( $account, array( 'blob_id' => $id, 'content_type' => $mime ) );
		$assert( basename( wp_parse_url( $url, PHP_URL_PATH ) ) === $id . '.' . $extension, 'Admin download extension follows MIME or safely falls back: ' . $mime );
	}
	foreach ( array( str_repeat( '0', 64 ), 'b098951e1-2a08-421e-889e-1c5239d17347' ) as $old_id ) {
		$old_response = $http( 'GET', '/wp-json/axismundi/jmap/download/' . $account . '/' . $old_id . '/old.txt', $auth );
		$assert( 404 === wp_remote_retrieve_response_code( $old_response ), 'Unreleased old ID format has no public route' );
	}
	$boundary = 'blob-audit-' . bin2hex( random_bytes( 12 ) );
	$multipart = '';
	foreach ( array( 'action' => 'ax_jmap_blob_admin', 'command' => 'upload', 'account' => $account, '_wpnonce' => $nonce ) as $key => $value ) {
		$multipart .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n";
	}
	$multipart .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"blob\"; filename=\"admin.txt\"\r\nContent-Type: text/plain\r\n\r\n" . $bytes . "admin\r\n--$boundary--\r\n";
	$admin_upload = $http( 'POST', '/wp-admin/admin-post.php', array( 'Cookie' => $cookie, 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ), $multipart );
	$all = $GLOBALS['wpdb']->get_col( $GLOBALS['wpdb']->prepare( 'SELECT blob_id FROM %i WHERE account_identity_id=%d AND uploader_user_id=%d', Blobs\uploads_table(), $actor->get_identity_id(), $users[0] ) );
	$added = array_values( array_diff( $all, $files ) );
	foreach ( $added as $added_id ) { $files[] = $added_id; }
	$assert( 302 === wp_remote_retrieve_response_code( $admin_upload ) && 1 === count( $added ), 'Admin multipart POST creates a blob through wp_handle_upload' );
	$admin_row = Blobs\row( $account, $added[0] );
	$assert( is_array( $admin_row ) && file_get_contents( Blobs\path( $admin_row ) ) === $bytes . 'admin', 'Admin-uploaded bytes match the original exactly' );
	$admin_delete = $http( 'POST', '/wp-admin/admin-post.php', array( 'Cookie' => $cookie, 'Content-Type' => 'application/x-www-form-urlencoded' ), http_build_query( array( 'action' => 'ax_jmap_blob_admin', 'command' => 'delete', 'account' => $account, 'blob_id' => $added[0], '_wpnonce' => $nonce ) ) );
	$assert( 302 === wp_remote_retrieve_response_code( $admin_delete ) && is_wp_error( Blobs\row( $account, $added[0] ) ), 'Admin deletion uses the same ACL and cleanup service' );
	$deeper_shards = static fn() => 2;
	add_filter( 'axismundi_jmap_blob_shard_depth', $deeper_shards );
	try {
		$assert( substr( $id, 1, 2 ) . '/' . substr( $id, 3, 2 ) === Blobs\blob_shard( $id ) && Blobs\path( Blobs\row( $account, $id ) ) === $disk, 'Changing allocation to 2/2 sharding does not change an existing recorded path' );
		$deep_bytes = $bytes . 'two-level-shard';
		$temp = wp_tempnam( 'blob-deep-audit' ); file_put_contents( $temp, $deep_bytes );
		$deep = Blobs\store( $account, array( 'name' => 'deep.txt', 'type' => 'text/plain', 'tmp_name' => $temp, 'error' => UPLOAD_ERR_OK ) );
		$assert( is_array( $deep ), 'New content can be allocated with two-level sharding' );
		$files[] = $deep['blobId'];
		$deep_row = Blobs\row( $account, $deep['blobId'] );
		$assert( $deep_row['storage_path'] === Blobs\blob_shard( $deep['blobId'] ) . '/' . $deep['blobId'] && file_get_contents( Blobs\path( $deep_row ) ) === $deep_bytes, 'Two-level relative path is recorded with unchanged bytes' );
	} finally { remove_filter( 'axismundi_jmap_blob_shard_depth', $deeper_shards ); }
	$assert( is_array( Blobs\verify_storage() ), 'Restoring the allocation policy re-verifies its static path before subsequent uploads' );
	$deep_http = $http( 'GET', '/wp-json/axismundi/jmap/download/' . $account . '/' . $deep['blobId'] . '/deep.txt', $auth );
	$assert( 200 === wp_remote_retrieve_response_code( $deep_http ) && $deep_bytes === wp_remote_retrieve_body( $deep_http ), 'A two-level blob still downloads when the active allocation policy returns to one level' );
	$assert( true === Blobs\remove( $account, $deep['blobId'] ), 'Deletion uses the recorded path rather than the current shard policy' );
	$session = json_decode( wp_remote_retrieve_body( $http( 'GET', '/wp-json/axismundi/jmap/session', $auth ) ), true );
	$assert( false === $session['accounts'][$account]['isReadOnly'], 'A Core blob writer Account is not advertised as read-only' );
	$assert( Blobs\max_size() === $session['capabilities']['urn:ietf:params:jmap:core']['maxSizeUpload'] && 1 === $session['capabilities']['urn:ietf:params:jmap:core']['maxConcurrentUpload'], 'Session advertises the implemented upload limits' );
	$reject = $http( 'POST', '/wp-json/axismundi/jmap/upload/' . $account, array_merge( $auth, array( 'Content-Type' => 'text/html' ) ), '<script>alert(1)</script>' );
	$assert( 415 === wp_remote_retrieve_response_code( $reject ), 'Disallowed media does not bypass Core upload policy' );
	$assert( true === Blobs\reference( $account, $id, 'TestObject', 'fixture' ), 'Trusted repository can pin a readable blob' );
	$assert( is_wp_error( Blobs\row( $account, $id ) ), 'Referenced blob requires a reference-level read grant even for uploader' );
	$grant = static fn( $allowed, $ref ) => 'TestObject' === $ref['owner_kind'];
	add_filter( 'axismundi_jmap_blob_reference_readable', $grant, 10, 2 );
	$assert( is_array( Blobs\row( $account, $id ) ) && is_wp_error( Blobs\remove( $account, $id ) ), 'Object grant allows reading but referenced bytes cannot be deleted' );
	$GLOBALS['wpdb']->update( Blobs\uploads_table(), array( 'expires_at' => '2000-01-01 00:00:00' ), array( 'blob_id' => $id ) );
	Blobs\expire();
	$assert( is_array( Blobs\row( $account, $id ) ) && is_file( $disk ), 'Automatic expiry leaves referenced bytes intact' );
	$assert( true === Blobs\reference( $account, $id, 'TestObject', 'fixture', false ), 'Removing final reference renews the unreferenced grace period' );
	remove_filter( 'axismundi_jmap_blob_reference_readable', $grant, 10 );
	$assert( is_array( Blobs\row( $account, $id ) ), 'Uploader can read the newly unreferenced blob during grace period' );
	$GLOBALS['wpdb']->update( Blobs\uploads_table(), array( 'expires_at' => '2000-01-01 00:00:00' ), array( 'blob_id' => $id ) );
	$primary_connection = $GLOBALS['wpdb'];
	$contender = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$contender->set_prefix( $primary_connection->prefix );
	Blobs\lock( $id );
	try {
		$GLOBALS['wpdb'] = $contender;
		Blobs\expire();
		$assert( is_file( $disk ), 'Expiry on another database connection cannot collect locked content' );
	} finally {
		$GLOBALS['wpdb'] = $primary_connection;
		Blobs\unlock( $id );
		$contender->close();
	}
	Blobs\expire();
	$assert( is_wp_error( Blobs\row( $account, $id ) ) && ! is_file( $disk ), 'Expiry removes only unreferenced bytes and their row' );
	$assert( true === Blobs\remove( $account, $raw_data['blobId'] ), 'Uploader can explicitly delete an unreferenced blob' );
	$group = axismundi_actors_create_local( array( 'actor_type' => 'Group', 'actor_scope' => 'managed', 'initial_owner_user_id' => $users[0], 'display_name' => 'Blob Group fixture' ) );
	$assert( $group instanceof Axismundi_Actor, 'Group Account fixture created' ); $actors[] = $group;
	$group_account = 'a' . $group->get_identity_id();
	$group_upload = $http( 'POST', '/wp-json/axismundi/jmap/upload/' . $group_account, array_merge( $auth, array( 'Content-Type' => 'text/plain' ) ), $bytes );
	$group_data = json_decode( wp_remote_retrieve_body( $group_upload ), true );
	$assert( 201 === wp_remote_retrieve_response_code( $group_upload ) && isset( $group_data['blobId'] ), 'Group and Person use the same HTTP upload service' );
	$files[] = $group_data['blobId'];
	$assert( is_wp_error( Blobs\row( $account, $group_data['blobId'] ) ), 'Group blob cannot be read through the Person Account' );
	$cross = $http( 'POST', '/wp-json/axismundi/jmap/upload/' . $account, array_merge( $auth, array( 'Content-Type' => 'text/plain' ) ), $bytes );
	$cross_data = json_decode( wp_remote_retrieve_body( $cross ), true );
	$assert( 201 === wp_remote_retrieve_response_code( $cross ) && $cross_data['blobId'] === $group_data['blobId'], 'Two Accounts receive the same content-derived ID' );
	$group_row = Blobs\row( $group_account, $group_data['blobId'] );
	$person_row = Blobs\row( $account, $cross_data['blobId'] );
	$assert( Blobs\path( $group_row ) === Blobs\path( $person_row ) && 1 === (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT COUNT(*) FROM %i WHERE blob_id=%s', Blobs\table(), $group_data['blobId'] ) ), 'Cross-Account dedup stores exactly one physical content file' );
	$assert( strlen( $bytes ) === Blobs\usage( $account ) && strlen( $bytes ) === Blobs\usage( $group_account ), 'Each Account is charged the full logical size' );
	$assert( true === Blobs\reference( $group_account, $id, 'TestObject', 'other-account' ), 'Reference belongs to the Group Account' );
	$grant = static fn() => true;
	add_filter( 'axismundi_jmap_blob_reference_readable', $grant );
	$assert( true === Blobs\remove( $account, $id ) && is_array( Blobs\row( $group_account, $id ) ) && is_wp_error( Blobs\row( $account, $id ) ), 'A reference in another Account cannot grant access after a personal upload is removed' );
	$assert( true === Blobs\reference( $group_account, $id, 'TestObject', 'other-account', false ), 'Removing a Group reference does not affect another Account' );
	remove_filter( 'axismundi_jmap_blob_reference_readable', $grant );
	$manager = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( 'SELECT * FROM %i WHERE identity_id=%d AND principal_kind=%s AND principal_id=%d', axismundi_actors_managers_table(), $group->get_identity_id(), 'wp_user', $users[0] ), ARRAY_A );
	$GLOBALS['wpdb']->delete( axismundi_actors_managers_table(), array( 'identity_id' => $group->get_identity_id(), 'principal_kind' => 'wp_user', 'principal_id' => $users[0] ) );
	$revoked = $http( 'POST', '/wp-json/axismundi/jmap/upload/' . $group_account, array_merge( $auth, array( 'Content-Type' => 'text/plain' ) ), $bytes );
	$assert( 403 === wp_remote_retrieve_response_code( $revoked ), 'Valid App Password cannot upload after Account access is revoked' );
	$GLOBALS['wpdb']->insert( axismundi_actors_managers_table(), $manager );
	$assert( true === Blobs\remove( $group_account, $group_data['blobId'] ), 'Restored Group authority can remove its unreferenced blob' );
	echo "PASS $checks blob checks\n";
} finally {
	global $wpdb;
	foreach ( $files as $id ) {
		$wpdb->delete( Blobs\refs_table(), array( 'blob_id' => $id ) );
		$wpdb->delete( Blobs\uploads_table(), array( 'blob_id' => $id ) );
		$entry = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE blob_id=%s', Blobs\table(), $id ), ARRAY_A );
		if ( $entry ) { Blobs\delete_unreferenced( $entry ); }
	}
	if ( $temp && is_file( $temp ) ) { wp_delete_file( $temp ); }
	if ( $public_control && is_file( $public_control ) ) { wp_delete_file( $public_control ); }
	if ( $listing_control && is_dir( $listing_control ) ) {
		foreach ( array( '.htaccess', 'staging' ) as $name ) { wp_delete_file( $listing_control . '/' . $name ); }
		rmdir( $listing_control );
	}
	foreach ( $actors as $actor ) {
		$id = $actor->get_identity_id();
		foreach ( array( axismundi_actors_addresses_table(), axismundi_actors_managers_table(), axismundi_actors_signing_keys_table(), axismundi_actors_actors_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	foreach ( $users as $user ) { WP_Application_Passwords::delete_all_application_passwords( $user ); wp_delete_user( $user ); }
	remove_filter( 'axismundi_jmap_blob_probe_request', $route_probe );
	update_option( 'ax_jmap_blob_root', $saved_root, false );
	if ( null === $saved_verified ) { delete_option( 'ax_jmap_blob_verified' ); } else { update_option( 'ax_jmap_blob_verified', $saved_verified, false ); }
	wp_set_current_user( $initial );
	unset( $_COOKIE[LOGGED_IN_COOKIE] );
}
