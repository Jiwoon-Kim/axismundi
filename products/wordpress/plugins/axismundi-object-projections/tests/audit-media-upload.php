<?php
/**
 * Dev-only HTTP audit. It posts over real HTTP, so it needs two things the container does not
 * have by itself.
 *
 * `AX_MEDIA_FIXTURE` is a readable video file **inside** the container. `assets/` is not
 * mounted, so copy one in first. `AX_MEDIA_HTTP_ORIGIN` is needed because the CLI container
 * cannot reach the host's published port; use the WordPress service name on the Docker
 * network. Git Bash rewrites container paths unless `MSYS_NO_PATHCONV=1` is set.
 *
 *     docker cp assets/media/video/<clip>.webm <project>-cli-1:/tmp/clip.webm
 *     MSYS_NO_PATHCONV=1 docker exec \
 *       -e AX_MEDIA_FIXTURE=/tmp/clip.webm -e AX_MEDIA_HTTP_ORIGIN=http://wordpress \
 *       <project>-cli-1 wp eval-file \
 *       wp-content/plugins/axismundi-object-projections/tests/audit-media-upload.php --allow-root
 *
 * Mutations this has been broken with, each caught by the named assertion: leaving the
 * `upload_dir` filter registered past the call, calling a path helper inside that filter, and
 * widening the extension allowlist with one a static server maps to an AS2 media type.
 */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
};
$fixture = getenv( 'AX_MEDIA_FIXTURE' );
$bytes = $fixture && is_readable( $fixture ) ? file_get_contents( $fixture ) : false;
$assert( is_string( $bytes ) && strlen( $bytes ) > 0, 'WebM fixture is readable' );
$assert( array( 'webm' => 'video/webm', 'mp4' => 'video/mp4' ) === axismundi_op_media_upload_mimes(), 'Only video extensions can be served on this origin' );
$before_upload_dir = wp_upload_dir( null, false, true );
$copy = wp_tempnam( 'media-audit.webm' );
$assert( is_string( $copy ) && copy( $fixture, $copy ), 'A disposable sideload fixture is available' );
$recursion_guard = static function ( array $dirs ) : array {
	$stack = array_column( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), 'function' );
	if ( count( array_keys( $stack, 'wp_upload_dir', true ) ) > 1 ) { throw new RuntimeException( 'upload_dir filter re-entered' ); }
	return $dirs;
};
add_filter( 'upload_dir', $recursion_guard, PHP_INT_MIN );
try {
	$probe = axismundi_op_media_receive( array( 'name' => 'test.webm', 'tmp_name' => $copy, 'error' => UPLOAD_ERR_OK ), axismundi_op_media_upload_mimes(), true );
} finally {
	remove_filter( 'upload_dir', $recursion_guard, PHP_INT_MIN );
	if ( is_file( $copy ) ) { wp_delete_file( $copy ); }
}
$after_upload_dir = wp_upload_dir( null, false, true );
$staged_probe = $probe['file'] ?? '';
if ( is_file( $staged_probe ) ) { wp_delete_file( $staged_probe ); }
$assert( str_contains( $staged_probe, '/axismundi-object-projections/blobs/incoming/' )
	&& $before_upload_dir['path'] === $after_upload_dir['path'], 'The scoped upload filter ran without recursion and did not redirect a subsequent upload' );
$origin = rtrim( getenv( 'AX_MEDIA_HTTP_ORIGIN' ) ?: home_url(), '/' );
$host = wp_parse_url( home_url(), PHP_URL_HOST );
$port = wp_parse_url( home_url(), PHP_URL_PORT );
$host .= $port ? ':' . $port : '';
$user = wp_insert_user( array( 'user_login' => 'ax_upload_' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
$assert( is_int( $user ), 'Fixture author is created' );
$before = get_current_user_id();
$cookies = $_COOKIE;
$uris = array();
try {
	$actor = axismundi_actors_ensure_for_user( $user );
	$assert( $actor instanceof Axismundi_Actor, 'Fixture Actor is created' );
	axismundi_actors_register_handle( $actor->get_identity_id(), get_userdata( $user )->user_login );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$actor = axismundi_actors_get_by_identity( $actor->get_identity_id() );
	wp_set_current_user( $user );
	$expires = time() + 300;
	$token = WP_Session_Tokens::get_instance( $user )->create( $expires );
	$cookie = wp_generate_auth_cookie( $user, $expires, 'logged_in', $token );
	$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
	$auth = array( 'Cookie' => LOGGED_IN_COOKIE . '=' . $cookie, 'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ) );
	$endpoint = $origin . '/wp-json/axismundi/activitypub/actors/' . $actor->get_uuid() . '/uploadMedia';
	$counts = static function () use ( $wpdb ) : array {
		return array( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment'" ),
			$wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_act_activities_table() ) );
	};
	$initial_counts = $counts();
	$submit = static function ( string $name, string $body_bytes, array $headers ) use ( $endpoint, $host ) : array {
		$boundary = 'ax' . wp_generate_uuid4();
		$document = wp_json_encode( array( 'type' => 'Video', 'name' => $name, 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) ) );
		$body = "--{$boundary}\r\nContent-Disposition: form-data; name=\"object\"; filename=\"object.json\"\r\nContent-Type: application/ld+json\r\n\r\n{$document}\r\n"
			. "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"clip.webm\"\r\nContent-Type: video/webm\r\n\r\n{$body_bytes}\r\n--{$boundary}--\r\n";
		$response = wp_remote_post( $endpoint, array( 'timeout' => 60, 'redirection' => 0, 'body' => $body,
			'headers' => $headers + array( 'Host' => $host, 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ) ) );
		if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
		return array( wp_remote_retrieve_response_code( $response ), (string) wp_remote_retrieve_header( $response, 'location' ), (string) wp_remote_retrieve_header( $response, 'content-type' ) );
	};
	$assert( 401 === $submit( 'Unauthorized', '', array() )[0], 'Anonymous uploads are denied' );
	$bad = $submit( 'Not video', '{"type":"Note"}', $auth );
	$assert( 415 === $bad[0] && str_starts_with( $bad[2], 'application/problem+json' ), 'Disguised JSON is refused with problem details' );
	$keyed = $auth + array( 'Idempotency-Key' => 'fixture-upload' );
	$one = $submit( 'First', $bytes, $keyed );
	$uris[] = $one[1];
	$assert( 201 === $one[0] && '' !== $one[1], 'First upload creates an object' );
	$again = $submit( 'First', $bytes, $keyed );
	$assert( 200 === $again[0] && $one[1] === $again[1], 'Retry returns the same Location' );
	$assert( 409 === $submit( 'Different', $bytes, $keyed )[0], 'Same key with different intent conflicts' );
	$two = $submit( 'Second', $bytes, $auth );
	$uris[] = $two[1];
	$assert( 201 === $two[0] && $one[1] !== $two[1], 'Different intent creates a different Object' );
	$assert( $counts() === $initial_counts, 'No attachment or Activity rows were created' );
	$first = axismundi_op_get_local_object( $one[1] );
	$second = axismundi_op_get_local_object( $two[1] );
	$assert( 'Video' === ( $first['object_type'] ?? '' ) && $one[1] === $first['document']['id'], 'Location is the Video identity' );
	$object_get = wp_remote_get( $origin . wp_parse_url( $one[1], PHP_URL_PATH ), array(
		'headers' => array( 'Host' => $host, 'Accept' => 'application/activity+json' ), 'timeout' => 30,
	) );
	$object_body = json_decode( wp_remote_retrieve_body( $object_get ), true );
	$assert( 200 === wp_remote_retrieve_response_code( $object_get ) && $one[1] === ( $object_body['id'] ?? null ), 'Location dereferences to the AS2 Video' );
	$url = $first['document']['url'];
	$assert( $url === ( $second['document']['url'] ?? null ), 'Both objects share one file URL' );
	$relative = $wpdb->get_var( $wpdb->prepare( 'SELECT storage_path FROM %i WHERE local_uuid=%s', axismundi_op_media_upload_table(), $first['local_uuid'] ) );
	$hash = hash( 'sha256', $bytes );
	$assert( 'axismundi-object-projections/blobs/' . substr( $hash, 0, 2 ) . '/' . $hash . '.webm' === $relative, 'The recorded path is the OP content-addressed path' );
	$path = wp_upload_dir()['basedir'] . '/' . $relative;
	$assert( is_file( $path ) && $hash === hash_file( 'sha256', $path ), 'The final bytes match the uploaded file' );
	$assert( true === axismundi_op_tombstone_local_object( $first, $actor->get_uri() ), 'One Object is deleted without an Activity' );
	$download = wp_remote_get( $origin . wp_parse_url( $url, PHP_URL_PATH ), array( 'headers' => array( 'Host' => $host ), 'timeout' => 30 ) );
	$assert( 200 === wp_remote_retrieve_response_code( $download ) && $hash === hash( 'sha256', wp_remote_retrieve_body( $download ) ), 'The second Object still has anonymously retrievable bytes' );
} finally {
	foreach ( array_filter( $uris ) as $uri ) {
		$row = axismundi_op_get_local_object( $uri );
		if ( $row && 'active' === $row['object_status'] ) { axismundi_op_tombstone_local_object( $row, $row['attributed_to_uri'] ); }
	}
	wp_set_current_user( $before );
	$_COOKIE = $cookies;
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $user );
}
echo "\n== {$checks} checks, 0 failed ==\n";
