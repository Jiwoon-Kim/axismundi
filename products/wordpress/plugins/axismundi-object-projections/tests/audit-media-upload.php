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
$delivery_id = 0;
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
	$counts = static function () use ( $wpdb, $actor ) : array {
		return array( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='attachment'" ),
			$wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE actor_uri=%s', axismundi_act_activities_table(), $actor->get_uri() ) ) );
	};
	$initial_counts = $counts();
	$submit = static function ( string $name, string $body_bytes, array $headers, array $addressing = array( 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) ) ) use ( $endpoint, $host ) : array {
		$boundary = 'ax' . wp_generate_uuid4();
		$document = wp_json_encode( array( 'type' => 'Video', 'name' => $name ) + $addressing );
		$body = "--{$boundary}\r\nContent-Disposition: form-data; name=\"object\"; filename=\"object.json\"\r\nContent-Type: application/ld+json\r\n\r\n{$document}\r\n"
			. "--{$boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"clip.webm\"\r\nContent-Type: video/webm\r\n\r\n{$body_bytes}\r\n--{$boundary}--\r\n";
		$response = wp_remote_post( $endpoint, array( 'timeout' => 60, 'redirection' => 0, 'body' => $body,
			'headers' => $headers + array( 'Host' => $host, 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ) ) );
		if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return array( wp_remote_retrieve_response_code( $response ), (string) wp_remote_retrieve_header( $response, 'location' ),
			(string) wp_remote_retrieve_header( $response, 'content-type' ), is_array( $decoded ) ? $decoded : array() );
	};
	$anonymous = $submit( 'Unauthorized', '', array() );
	$assert( 401 === $anonymous[0], 'Anonymous uploads are denied' );
	// The permission callback refuses before the body is read, and still in one shape.
	$assert( str_starts_with( $anonymous[2], 'application/problem+json' ) && 'ax_op_media_auth' === ( $anonymous[3]['code'] ?? '' ),
		'A refusal from the permission callback is problem details too' );
	// Core dispatches routes case-insensitively, so a non-canonical spelling reaches the same
	// endpoint and must not get a different refusal shape.
	$lowercased = wp_remote_post( str_replace( '/uploadMedia', '/uploadmedia', $endpoint ), array(
		'timeout' => 30, 'redirection' => 0, 'headers' => array( 'Host' => $host ),
	) );
	$assert( 401 === wp_remote_retrieve_response_code( $lowercased )
		&& str_starts_with( (string) wp_remote_retrieve_header( $lowercased, 'content-type' ), 'application/problem+json' ),
		'A non-canonical route spelling keeps the refusal shape' );
	$bad = $submit( 'Not video', '{"type":"Note"}', $auth );
	$assert( 415 === $bad[0] && str_starts_with( $bad[2], 'application/problem+json' ), 'Disguised JSON is refused with problem details' );
	// Putting a public file on this site and having an identity to author as are separate
	// conditions, so each has to be able to fail alone. First: an Actor that is fine, held by
	// a principal with no file capability.
	$reader = wp_insert_user( array( 'user_login' => 'ax_reader_' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	$assert( is_int( $reader ) && ! user_can( $reader, 'upload_files' ), 'A principal without the file capability exists' );
	try {
		$reader_actor = axismundi_actors_ensure_for_user( $reader );
		axismundi_actors_register_handle( $reader_actor->get_identity_id(), get_userdata( $reader )->user_login );
		axismundi_actors_set_status( $reader_actor->get_identity_id(), 'public' );
		$reader_actor = axismundi_actors_get_by_identity( $reader_actor->get_identity_id() );
		$assert( axismundi_op_media_upload_may_author( $reader_actor, $reader ), 'That principal may author as its own Actor' );
		$reader_expires = time() + 300;
		$reader_token = WP_Session_Tokens::get_instance( $reader )->create( $reader_expires );
		$reader_cookie = wp_generate_auth_cookie( $reader, $reader_expires, 'logged_in', $reader_token );
		$_COOKIE[ LOGGED_IN_COOKIE ] = $reader_cookie;
		wp_set_current_user( $reader );
		$reader_auth = array( 'Cookie' => LOGGED_IN_COOKIE . '=' . $reader_cookie, 'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ) );
		$reader_endpoint = $origin . '/wp-json/axismundi/activitypub/actors/' . $reader_actor->get_uuid() . '/uploadMedia';
		$denied = wp_remote_post( $reader_endpoint, array( 'timeout' => 30, 'redirection' => 0,
			'headers' => $reader_auth + array( 'Host' => $host ) ) );
		$denied_body = json_decode( (string) wp_remote_retrieve_body( $denied ), true );
		$assert( 403 === wp_remote_retrieve_response_code( $denied ) && 'ax_op_media_capability' === ( $denied_body['code'] ?? '' ),
			'An identity to author as does not grant the file capability' );
	} finally {
		wp_set_current_user( $user );
		$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
		require_once ABSPATH . 'wp-admin/includes/user.php';
		if ( is_int( $reader ) ) { wp_delete_user( $reader ); }
	}
	// And the reverse: the capability is held, the identity is withdrawn. This pins the
	// behaviour rather than the layer -- authorization asks Actors one question, so if acting
	// as a disabled Actor ever becomes allowed there, this fails here instead of passing.
	$assert( axismundi_actors_set_status( $actor->get_identity_id(), 'disabled' ), 'The fixture Actor can be deactivated' );
	$withdrawn = $submit( 'Deactivated', '', $auth );
	$assert( axismundi_actors_set_status( $actor->get_identity_id(), 'public' ), 'The fixture Actor is reactivated' );
	$assert( 403 === $withdrawn[0] && 'ax_op_media_forbidden' === ( $withdrawn[3]['code'] ?? '' ),
		'The file capability does not stand in for an identity to author as' );

	$keyed = $auth + array( 'Idempotency-Key' => 'fixture-upload' );
	$one = $submit( 'First', $bytes, $keyed );
	$uris[] = $one[1];
	$assert( 201 === $one[0] && '' !== $one[1], 'First upload creates an object' );
	$again = $submit( 'First', $bytes, $keyed );
	$assert( 200 === $again[0] && $one[1] === $again[1], 'Retry returns the same Location' );
	$assert( 409 === $submit( 'Different', $bytes, $keyed )[0], 'Same key with different intent conflicts' );
	$two = $submit( 'Second', $bytes, $auth, array() );
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

	$peer = 'https://example.com/users/' . wp_generate_uuid4();
	$inbox = $peer . '/inbox';
	$remote = axismundi_actors_upsert_remote( array( 'uri' => $peer, 'actor_type' => 'Person',
		'preferred_username' => 'video_peer', 'display_name' => 'Video Peer', 'profile_url' => $peer,
		'endpoints' => array( 'inbox' => $inbox, 'outbox' => $peer . '/outbox' ),
		'payload' => array( 'id' => $peer, 'type' => 'Person', 'preferredUsername' => 'video_peer' ) ) );
	$assert( $remote instanceof Axismundi_Actor, is_wp_error( $remote ) ? $remote->get_error_message() : 'A remote peer with a cached inbox is available' );
	$addressing = array( 'to' => array( 'https://www.w3.org/ns/activitystreams#Public', $peer ) );
	$three = $submit( 'For publication', $bytes, $auth, $addressing );
	$uris[] = $three[1];
	$assert( 201 === $three[0] && $counts() === $initial_counts, 'Uploading a deliverable Video still does not record an Activity' );
	$stored = axismundi_op_get_local_object( $three[1] );
	$publish = static function ( string $uri, array $extra = array() ) use ( $endpoint, $auth, $host, $actor ) : array {
		$response = wp_remote_post( substr( $endpoint, 0, -strlen( 'uploadMedia' ) ) . 'outbox', array(
			'headers' => $auth + array( 'Host' => $host, 'Content-Type' => 'application/activity+json' ),
			'body' => wp_json_encode( array( 'type' => 'Create', 'actor' => $actor->get_uri(), 'object' => $uri ) + $extra ),
			'timeout' => 30, 'redirection' => 0,
		) );
		if ( is_wp_error( $response ) ) { throw new RuntimeException( $response->get_error_message() ); }
		return array( wp_remote_retrieve_response_code( $response ), (string) wp_remote_retrieve_header( $response, 'location' ),
			json_decode( wp_remote_retrieve_body( $response ), true ) );
	};
	$assert( 409 === $publish( $one[1] )[0], 'A tombstone cannot earn a Create' );
	$assert( 422 === $publish( $two[1] )[0], 'Public bytes do not make an unaddressed Video eligible for reference publication' );
	$assert( 400 === $publish( $three[1], array( 'to' => array( $peer ) ) )[0] && $counts() === $initial_counts,
		'An addressing override is rejected and refused submissions leave no Activity' );
	$created = $publish( $three[1], array( 'id' => $peer . '/create' ) );
	$assert( 201 === $created[0] && '' !== $created[1] && $peer . '/create' !== $created[1]
		&& $three[1] === ( $created[2]['object'] ?? null ), 'Outbox records a server-identified Create for the existing Video URI' );
	$activity = axismundi_act_get( $created[1] );
	$assert( $activity instanceof Axismundi_Activity && $three[1] === $activity->get_payload()['object']
		&& $stored === axismundi_op_get_local_object( $three[1] ), 'Publication records only the reference and leaves the source untouched' );
	$delivery_id = axismundi_activitypub_bridge_find_delivery( $created[1] );
	$job = axismundi_activitypub_bridge_get_delivery( $delivery_id );
	$wire = $job ? json_decode( $job->payload_json, true ) : array();
	$assert( $job && array( $inbox ) === json_decode( $job->inboxes_json, true )
		&& $created[1] === ( $wire['id'] ?? null ) && $three[1] === ( $wire['object'] ?? null )
		&& $addressing['to'] === ( $wire['to'] ?? null ), 'The queued S2S body preserves the same URI and stored addressing' );
	$replay = $publish( $three[1] );
	$assert( 200 === $replay[0] && $created[1] === $replay[1], 'Replay returns the original Activity Location with HTTP 200' );
	$assert( (int) $initial_counts[1] + 1 === (int) $counts()[1], 'Replay leaves exactly one Activity for the fixture author' );
	$assert( $delivery_id === axismundi_activitypub_bridge_find_delivery( $created[1] ), 'Replay does not replace the existing delivery job' );
} finally {
	if ( $delivery_id ) {
		axismundi_activitypub_bridge_cancel_delivery( $delivery_id );
		$wpdb->delete( axismundi_activitypub_bridge_delivery_recipients_table(), array( 'delivery_id' => $delivery_id ) );
		$wpdb->delete( axismundi_activitypub_bridge_delivery_table(), array( 'id' => $delivery_id ) );
	}
	if ( ! empty( $created[1] ) ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri_hash' => hash( 'sha256', $created[1] ), 'actor_uri' => $actor->get_uri() ) );
	}
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
