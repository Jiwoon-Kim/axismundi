<?php
/** Raw binary transport over the same store used by admin uploads. */
namespace Axismundi\Jmap\Blobs;
defined( 'ABSPATH' ) || exit;

function upload( \WP_REST_Request $request ) {
	$account = $request->get_url_params()['accountId'] ?? '';
	$owner = actor( $account, true );
	if ( is_wp_error( $owner ) ) { return $owner; }
	$body = $request->get_body();
	if ( strlen( $body ) > max_size() ) { return failure( 'Upload exceeds maxSizeUpload.', 413 ); }
	$type = $request->get_header( 'Content-Type' ) ?: 'application/octet-stream';
	$base_type = strtolower( trim( explode( ';', $type )[0] ) );
	$extension = null;
	foreach ( get_allowed_mime_types( get_current_user_id() ) as $extensions => $mime ) {
		if ( $mime === $base_type ) { $extension = explode( '|', $extensions )[0]; break; }
	}
	if ( null === $extension ) { return failure( 'This media type is not allowed by WordPress upload policy.', 415 ); }
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$temp = wp_tempnam( 'jmap-upload' );
	if ( ! $temp ) { return failure( 'Temporary storage is unavailable.' ); }
	try {
		if ( strlen( $body ) !== file_put_contents( $temp, $body, LOCK_EX ) ) { return failure( 'Temporary write failed.' ); }
		$result = store( $account, array( 'name' => 'upload.' . $extension, 'type' => $type, 'tmp_name' => $temp, 'size' => strlen( $body ), 'error' => UPLOAD_ERR_OK ) );
		return is_wp_error( $result ) ? $result : new \WP_REST_Response( $result, 201 );
	} finally { if ( is_file( $temp ) ) { wp_delete_file( $temp ); } }
}

function disposition( string $name ) {
	if ( '' === $name || strlen( $name ) > 255 || preg_match( '/[\x00-\x1f\x7f\/\\\\]/', $name ) || ! seems_utf8( $name ) ) { return failure( 'Invalid download name.', 400 ); }
	$fallback = preg_replace( '/[^A-Za-z0-9 ._-]/', '_', $name );
	return 'attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode( $name );
}

function download( \WP_REST_Request $request ) {
	$params = $request->get_url_params();
	$entry = row( $params['accountId'], $params['blobId'] );
	if ( is_wp_error( $entry ) ) { return $entry; }
	$file = path( $entry );
	if ( is_wp_error( $file ) ) { return $file; }
	$name = disposition( rawurldecode( $params['name'] ) );
	if ( is_wp_error( $name ) ) { return $name; }
	$type = $request->get_query_params()['type'] ?? $entry['content_type'];
	if ( ! is_string( $type ) || strlen( $type ) > 255 || preg_match( '/[\x00-\x1f\x7f]/', $type ) || ! preg_match( '~\A[a-zA-Z0-9!#$&^_.+-]+/[a-zA-Z0-9!#$&^_.+-]+(?:;[^\r\n]*)?\z~D', $type ) ) { return failure( 'Invalid download media type.', 400 ); }
	$digest = hash_file( 'sha256', $file );
	if ( (int) filesize( $file ) !== (int) $entry['size'] || ! is_string( $digest ) || ! hash_equals( $entry['content_hash'], $digest ) ) { return failure( 'Blob integrity check failed.', 500 ); }
	// The serving filter streams only after route permission and object ACL checks succeeded.
	$response = new \WP_REST_Response( null, 200, array( 'Content-Type' => $type, 'Content-Length' => (string) $entry['size'], 'Content-Disposition' => $name, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Accept-Ranges' => 'none' ) );
	$streams = $GLOBALS['ax_jmap_blob_streams'] ?? new \WeakMap();
	$streams[$response] = $file;
	$GLOBALS['ax_jmap_blob_streams'] = $streams;
	return $response;
}

add_filter( 'rest_pre_serve_request', static function ( $served, $response, $request ) {
	$streams = $GLOBALS['ax_jmap_blob_streams'] ?? null;
	if ( $served || ! $streams instanceof \WeakMap || ! isset( $streams[$response] ) ) { return $served; }
	$file = $streams[$response];
	unset( $streams[$response] );
	if ( 'HEAD' !== $request->get_method() ) { readfile( $file ); }
	return true;
}, 10, 3 );

// Prevent REST JSON/form parameter parsing from interpreting raw file bytes or overriding path parameters.
add_filter( 'rest_pre_dispatch', static function ( $result, $server, $request ) {
	if ( null !== $result || 'POST' !== $request->get_method() || ! preg_match( '#\A/axismundi/jmap/upload/(a[1-9][0-9]*)\z#D', $request->get_route(), $match ) ) { return $result; }
	$allowed = \Axismundi\Jmap\Http\permission( $request );
	if ( true !== $allowed ) { return $allowed; }
	$request->set_url_params( array( 'accountId' => $match[1] ) );
	return upload( $request );
}, 9, 3 );

add_action( 'rest_api_init', static function () : void {
	register_rest_route( 'axismundi/jmap', '/upload/(?P<accountId>a[1-9][0-9]*)', array( 'methods' => 'POST', 'permission_callback' => '\\Axismundi\\Jmap\\Http\\permission', 'callback' => __NAMESPACE__ . '\\upload' ) );
	register_rest_route( 'axismundi/jmap', '/download/(?P<accountId>a[1-9][0-9]*)/(?P<blobId>' . ID_PATTERN . ')/(?P<name>[^/]+)', array( 'methods' => 'GET', 'permission_callback' => '\\Axismundi\\Jmap\\Http\\permission', 'callback' => __NAMESPACE__ . '\\download' ) );
} );
