<?php
/** Dev-only real HTTP round trips. AX_C2S_HTTP_ORIGIN may name wp-env's internal host. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
};
$users = array();
$actors = array();
$original_user = get_current_user_id();
$cookies = $_COOKIE;
$ledger_before = $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_activities_table() . ' ORDER BY id', ARRAY_A );
$principals_before = $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A );
$meta_before = $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_meta_table() . ' ORDER BY actor_id,meta_key', ARRAY_A );
$http_origin = rtrim( getenv( 'AX_C2S_HTTP_ORIGIN' ) ?: home_url(), '/' );
$host = wp_parse_url( home_url(), PHP_URL_HOST );
$port = wp_parse_url( home_url(), PHP_URL_PORT );
$host .= $port ? ':' . $port : '';
$request = static function ( string $path, ?array $body = null, array $headers = array() ) use ( $http_origin, $host ) : array {
	$result = wp_remote_request( $http_origin . '/wp-json/axismundi/v1/' . $path, array(
		'method' => null === $body ? 'GET' : 'POST', 'timeout' => 30, 'redirection' => 0,
		'headers' => array_merge( array( 'Host' => $host, 'Content-Type' => 'application/activity+json' ), $headers ),
		'body' => null === $body ? null : wp_json_encode( $body ),
	) );
	if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
	return array( wp_remote_retrieve_response_code( $result ), json_decode( wp_remote_retrieve_body( $result ), true ), wp_remote_retrieve_headers( $result ) );
};
try {
	foreach ( array( 'owner', 'stranger' ) as $kind ) {
		$login = 'ax_c2s_' . $kind . '_' . strtolower( wp_generate_password( 8, false, false ) );
		$user = wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber' ) );
		$assert( is_int( $user ), 'Fixture User created' );
		$users[] = $user;
		$actor = axismundi_actors_ensure_for_user( $user );
		$assert( $actor instanceof Axismundi_Actor, 'Fixture Actor created' );
		$actors[] = $actor;
		axismundi_actors_register_handle( $actor->get_identity_id(), $login );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	}
	$owner = axismundi_actors_get_by_identity( $actors[0]->get_identity_id() );
	$other = axismundi_actors_get_by_identity( $actors[1]->get_identity_id() );
	wp_set_current_user( $users[0] );
	$expires = time() + 3600;
	$token = WP_Session_Tokens::get_instance( $users[0] )->create( $expires );
	$cookie = wp_generate_auth_cookie( $users[0], $expires, 'logged_in', $token );
	$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
	$auth = array( 'Cookie' => LOGGED_IN_COOKIE . '=' . $cookie, 'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ) );
	$path = 'actors/' . $owner->get_uuid() . '/outbox';
	$liked = 'actors/' . $owner->get_uuid() . '/liked?page=1';
	$object = 'https://example.test/c2s/' . wp_generate_uuid4();
	$like = array( 'type' => 'Like', 'actor' => $owner->get_uri(), 'object' => $object, 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) );
	$assert( 401 === $request( $path, $like )[0], 'Anonymous POST is denied' );
	$assert( 401 === $request( $path, $like, array( 'Cookie' => $auth['Cookie'] ) )[0], 'Cookie without nonce is denied by real WP REST authentication' );
	$assert( 403 === $request( $path, $like, array_merge( $auth, array( 'X-WP-Nonce' => 'invalid' ) ) )[0], 'Invalid cookie nonce is denied' );
	$assert( 403 === $request( $path, array_merge( $like, array( 'actor' => $other->get_uri() ) ), $auth )[0], 'Body actor cannot impersonate another Actor' );
	$assert( 403 === $request( 'actors/' . $other->get_uuid() . '/outbox', array_merge( $like, array( 'uuid' => $owner->get_uuid() ) ), $auth )[0], 'JSON uuid cannot shadow an unauthorized URL Actor' );
	$assert( 422 === $request( $path, array_merge( $like, array( 'type' => 'Update' ) ), $auth )[0], 'Unimplemented C2S verbs are closed despite ledger support' );
	$first = $request( $path, array_merge( $like, array( 'id' => 'https://example.test/client-id' ) ), array_merge( $auth, array( 'Idempotency-Key' => 'like-1' ) ) );
	$first_uri = (string) ( $first[2]['location'] ?? '' );
	$assert( 201 === $first[0] && '' !== $first_uri && 'https://example.test/client-id' !== $first_uri, 'Cookie POST returns server identity in 201 Location' );
	$assert( axismundi_act_get( $first_uri ) instanceof Axismundi_Activity, 'HTTP POST commits one real ledger Like' );
	$assert( 200 === $request( $path )[0], 'Adding POST preserves existing outbox GET' );
	$assert( 'private' === axismundi_actors_liked_visibility( $owner ) && 403 === $request( $liked )[0], 'Liked collection starts private for anonymous readers' );
	$assert( 403 === $request( $liked, null, $auth )[0], 'AP private collection is closed even to its authenticated operator' );
	$assert( is_wp_error( axismundi_actors_set_liked_visibility( $owner, 'public', $users[1] ) ), 'Another principal cannot publish the Account liked collection' );
	$assert( true === axismundi_actors_set_liked_visibility( $owner, 'public', $users[0] ), 'Account operator explicitly publishes URI membership' );
	$page = $request( $liked );
	$assert( $page[1] === $request( $liked, null, $auth )[1], 'AP public representation is identical with and without operator cookies' );
	$assert( 200 === $page[0] && array( $object ) === $page[1]['orderedItems'] && ! isset( $page[1]['totalItems'] ), 'Anonymous liked exposes public membership without private totals' );
	$private = array_merge( $like, array( 'object' => $object . '/private', 'to' => array( $owner->get_uri() ) ) );
	$assert( 201 === $request( $path, $private, $auth )[0] && array( $object . '/private', $object ) === $request( $liked )[1]['orderedItems'], 'Explicitly public collection includes private-addressed Like URIs, not member bodies' );
	$assert( true === axismundi_actors_set_liked_visibility( $owner, 'private', $users[0] ) && 403 === $request( $liked )[0]
		&& 403 === $request( $liked, null, $auth )[0], 'Closing the AP collection blocks anonymous and operator reads alike' );
	axismundi_actors_set_liked_visibility( $owner, 'public', $users[0] );
	$undo = array( 'type' => 'Undo', 'actor' => $owner->get_uri(), 'object' => $first_uri );
	$assert( 201 === $request( $path, $undo, array_merge( $auth, array( 'Idempotency-Key' => 'undo-1' ) ) )[0], 'Undo accepts the explicitly named Like' );
	$assert( ! axismundi_act_get( $first_uri )->is_effective() && array( $object . '/private' ) === $request( $liked )[1]['orderedItems'], 'Undo removes only its targeted liked membership' );
	$second = $request( $path, $like, array_merge( $auth, array( 'Idempotency-Key' => 'like-2' ) ) );
	$second_uri = (string) $second[2]['location'];
	$assert( 201 === $second[0] && $second_uri !== $first_uri, 'A new intent after Undo creates a new Like' );
	$old_retry = $request( $path, $like, array_merge( $auth, array( 'Idempotency-Key' => 'like-1' ) ) );
	$assert( 201 === $old_retry[0] && $first_uri === (string) $old_retry[2]['location'] && ! axismundi_act_get( $first_uri )->is_effective(), 'Old Like retry returns its original undone record, never resurrects it' );
	$assert( 201 === $request( $path, $undo, array_merge( $auth, array( 'Idempotency-Key' => 'undo-2' ) ) )[0]
		&& axismundi_act_get( $second_uri )->is_effective(), 'Even a fresh Undo of the old Like cannot cancel the new Like' );
	$assert( 409 === $request( $path, $private, array_merge( $auth, array( 'Idempotency-Key' => 'like-1' ) ) )[0], 'Retry key reuse with different input is a conflict' );
	$wrong_undo = array_merge( $undo, array( 'actor' => $other->get_uri() ) );
	$assert( is_wp_error( axismundi_act_submit_outbox( $users[1], $other, $wrong_undo ) ), 'Authorized different Actor still cannot Undo owner Like' );
	$app = WP_Application_Passwords::create_new_application_password( $users[0], array( 'name' => 'C2S HTTP audit' ) );
	$assert( is_array( $app ), 'Application Password fixture created' );
	$basic = array( 'Authorization' => 'Basic ' . base64_encode( get_userdata( $users[0] )->user_login . ':' . $app[0] ) );
	$assert( 201 === $request( $path, $private, $basic )[0], 'Real Application Password authentication works without a nonce' );
	WP_Application_Passwords::delete_application_password( $users[0], $app[1]['uuid'] );
	$assert( 401 === $request( $path, $private, $basic )[0], 'Revoked Application Password is rejected' );
	$note = array( 'type' => 'Note', 'id' => 'https://example.test/untrusted-object-id', 'content' => '<p>C2S authored body</p>', 'summary' => 'CW secret', 'sensitive' => true, 'attachment' => array( array( 'type' => 'Image', 'url' => 'https://example.test/attachment.png' ) ), 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) );
	$create = array( 'type' => 'Create', 'actor' => $owner->get_uri(), 'object' => $note );
	$create_headers = array_merge( $auth, array( 'Idempotency-Key' => 'create-note-1' ) );
	$created = $request( $path, $create, $create_headers );
	$create_uri = (string) ( $created[2]['location'] ?? '' );
	$create_activity = axismundi_act_get( $create_uri );
	$assert( 201 === $created[0] && $create_activity instanceof Axismundi_Activity, 'HTTP Create(Note) records the server Create identity' );
	$note_uri = $create_activity->get_object_uri();
	$assert( $note_uri !== $create_uri && str_contains( $note_uri, '/objects/' ) && 'https://example.test/untrusted-object-id' !== $note_uri, 'Create and Object receive distinct server IDs' );
	$read_object = static function ( string $uri, string $accept = 'application/activity+json', string $method = 'GET' ) use ( $http_origin, $host ) {
		$query = wp_parse_url( $uri, PHP_URL_QUERY );
		return wp_remote_request( $http_origin . wp_parse_url( $uri, PHP_URL_PATH ) . ( $query ? '?' . $query : '' ), array( 'method' => $method, 'headers' => array( 'Host' => $host, 'Accept' => $accept ), 'timeout' => 30, 'redirection' => 0 ) );
	};
	$object_response = $read_object( $note_uri );
	$document = json_decode( wp_remote_retrieve_body( $object_response ), true );
	$assert( 200 === wp_remote_retrieve_response_code( $object_response ) && $note_uri === ( $document['id'] ?? '' ) && $note_uri === $document['url'] && 'Note' === $document['type'], 'Canonical Object GET negotiates Note JSON with unified id/url' );
	$assert( 200 === wp_remote_retrieve_response_code( $read_object( $note_uri, 'text/html' ) ), 'Canonical Object URL also serves HTML' );
	$collection_fields = array( 'replies', 'likes', 'shares', 'http://fedibird.com/ns#emojiReactions' );
	foreach ( $collection_fields as $field ) {
		$assert( ! empty( $document[ $field ] ), 'Local Note advertises ' . $field );
		$collection = $read_object( $document[ $field ] );
		$body = json_decode( wp_remote_retrieve_body( $collection ), true );
		$assert( 200 === wp_remote_retrieve_response_code( $collection ) && $document[ $field ] === ( $body['id'] ?? '' ), 'Advertised collection resolves: ' . $field );
	}
	$reply_result = $request( $path, array( 'type' => 'Note', 'content' => '<p>Reply</p>', 'inReplyTo' => $note_uri, 'to' => $note['to'] ), $auth );
	$reply_activity = axismundi_act_get( (string) ( $reply_result[2]['location'] ?? '' ) );
	$replies_root = json_decode( wp_remote_retrieve_body( $read_object( $document['replies'] ) ), true );
	$replies_page = json_decode( wp_remote_retrieve_body( $read_object( $replies_root['first'] ) ), true );
	$assert( 201 === $reply_result[0] && $reply_activity instanceof Axismundi_Activity && in_array( $reply_activity->get_object_uri(), $replies_page['orderedItems'] ?? array(), true ), 'HTTP-created reply appears in the local parent advertised replies page' );
	$note_like = $request( $path, array_merge( $like, array( 'object' => $note_uri ) ), $auth );
	$likes_body = json_decode( wp_remote_retrieve_body( $read_object( $document['likes'] ) ), true );
	$assert( 201 === $note_like[0] && 1 === ( $likes_body['totalItems'] ?? null ), 'C2S Like is reflected in the local Object likes collection' );
	$note_undo = $request( $path, array( 'type' => 'Undo', 'actor' => $owner->get_uri(), 'object' => (string) $note_like[2]['location'] ), $auth );
	$likes_after_undo = json_decode( wp_remote_retrieve_body( $read_object( $document['likes'] ) ), true );
	$assert( 201 === $note_undo[0] && 0 === ( $likes_after_undo['totalItems'] ?? null ), 'Targeted Undo removes the local Object Like without contaminating liked paging fixtures' );
	$retried = $request( $path, $create, $create_headers );
	$assert( 201 === $retried[0] && $create_uri === (string) $retried[2]['location'], 'Create retry creates neither a second Note nor a second Create' );
	$assert( 409 === $request( $path, $like, $create_headers )[0], 'A Create retry key cannot be reused for Like' );
	$assert( 409 === $request( $path, $create, array_merge( $auth, array( 'Idempotency-Key' => 'like-1' ) ) )[0], 'A Like retry key cannot be reused for Create' );
	$bare = array_merge( $note, array( 'content' => '<p>Private bare Note</p>', 'to' => array( $owner->get_uri() ) ) );
	$bare_result = $request( $path, $bare, $auth );
	$bare_activity = axismundi_act_get( (string) ( $bare_result[2]['location'] ?? '' ) );
	$assert( 201 === $bare_result[0] && $bare_activity instanceof Axismundi_Activity && 'Create' === $bare_activity->get_type(), 'Bare Note is wrapped in a server Create' );
	$assert( 404 === wp_remote_retrieve_response_code( $read_object( $bare_activity->get_object_uri() ) ), 'Restricted Object is not exposed by the public canonical route' );
	foreach ( array( 'replies', 'likes', 'shares', 'emoji-reactions' ) as $kind ) {
		$assert( 404 === $request( 'objects/' . $kind . '?object=' . rawurlencode( $bare_activity->get_object_uri() ) )[0], 'Private Object collection fails closed: ' . $kind );
	}
	$delete = $request( $path, array( 'type' => 'Delete', 'actor' => $owner->get_uri(), 'object' => $note_uri ), $auth );
	$gone = $read_object( $note_uri );
	$tombstone = json_decode( wp_remote_retrieve_body( $gone ), true );
	$assert( 201 === $delete[0] && 410 === wp_remote_retrieve_response_code( $gone ) && 'Tombstone' === ( $tombstone['type'] ?? '' ), 'Delete records its Activity and canonical AP GET becomes 410 Tombstone' );
	$assert( ! isset( $tombstone['content'], $tombstone['attachment'], $tombstone['summary'] ) && ! str_contains( wp_remote_retrieve_body( $gone ), 'CW secret' ), 'Tombstone does not expose body, media or CW' );
	foreach ( $collection_fields as $field ) {
		$assert( ! isset( $tombstone[ $field ] ) && 404 === wp_remote_retrieve_response_code( $read_object( $document[ $field ] ) ), 'Tombstone neither advertises nor serves collection: ' . $field );
	}
	$assert( 410 === wp_remote_retrieve_response_code( $read_object( $note_uri, 'text/html' ) ) && '' === wp_remote_retrieve_body( $read_object( $note_uri, 'application/activity+json', 'HEAD' ) ), 'Deleted HTML is 410 and HEAD has no body' );
	$assert( ! post_type_exists( 'ax_note' ) && ! function_exists( 'axismundi_note_object_uri' ), 'Retired Note bootstrap exposes no CPT or legacy runtime fallback' );
	// Preserve the existing block's state-oriented service rather than routing it through AP.
	$legacy = axismundi_act_like_object( $owner, $object . '/legacy' );
	$assert( $legacy instanceof Axismundi_Activity && $legacy->get_uri() === axismundi_act_like_object( $owner, $object . '/legacy' )->get_uri(), 'Existing Like service retains active-state idempotency' );
	$assert( axismundi_act_unlike_object( $owner, $object . '/legacy' ) instanceof Axismundi_Activity && ! axismundi_act_get( $legacy->get_uri() )->is_effective(), 'Existing Unlike service still undoes its current Like' );
	$one = axismundi_act_get_liked_collection_page( $owner->get_uri(), PHP_INT_MAX, 1 );
	$two = axismundi_act_get_liked_collection_page( $owner->get_uri(), $one['next'], 1 );
	$assert( array( $object . '/private' ) === $one['items'] && array( $object ) === $two['items'] && null === $two['next'], 'Cursor paging deduplicates repeated Likes before applying page boundaries' );
	$settings_headers = array( 'Host' => $host, 'Cookie' => $auth['Cookie'] . '; ' . AUTH_COOKIE . '=' . wp_generate_auth_cookie( $users[0], $expires, 'auth', $token ) );
	$settings_body = array( 'action' => 'axismundi_actors_set_liked_visibility', 'identity_id' => $owner->get_identity_id(), 'visibility' => 'private', '_wpnonce' => 'invalid' );
	$settings_post = static function ( array $body ) use ( $http_origin, $settings_headers ) {
		return wp_remote_post( $http_origin . '/wp-admin/admin-post.php', array( 'headers' => $settings_headers, 'body' => $body, 'redirection' => 0, 'timeout' => 30 ) );
	};
	$assert( 403 === wp_remote_retrieve_response_code( $settings_post( $settings_body ) ) && 'public' === axismundi_actors_liked_visibility( $owner ), 'Settings POST rejects invalid CSRF nonce without changing disclosure' );
	$settings_body['_wpnonce'] = wp_create_nonce( 'ax_actors_liked_' . $owner->get_identity_id() );
	$assert( 302 === wp_remote_retrieve_response_code( $settings_post( $settings_body ) ) && 'private' === axismundi_actors_liked_visibility( $owner ), 'Real admin POST saves collection disclosure for the explicit Actor' );
	if ( ! function_exists( 'axismundi_actors_liked_form' ) ) { require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/admin.php'; }
	ob_start();
	axismundi_actors_liked_form( $owner );
	$form = ob_get_clean();
	$assert( str_contains( $form, 'Publish my liked collection' ) && str_contains( $form, 'private-addressed Likes' ) && ! str_contains( $form, "checked='checked'" ), 'Native settings form reflects private state and explicit URI disclosure consent' );
} finally {
	foreach ( $actors as $actor ) {
		$uri = $actor->get_uri();
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT object_uri FROM %i WHERE attributed_to_uri=%s', axismundi_op_local_objects_table(), $uri ) ) as $object_uri ) {
			if ( function_exists( 'axismundi_media_relations_table' ) ) { $wpdb->delete( axismundi_media_relations_table(), array( 'subject_uri' => $object_uri, 'provider' => 'ax-op-local' ) ); }
			foreach ( array( axismundi_op_local_objects_table() => 'object_uri', axismundi_op_objects_table() => 'canonical_uri', axismundi_op_object_relations_table() => 'source_object_uri', axismundi_op_object_mentions_table() => 'source_object_uri', axismundi_op_tag_references_table() => 'source_uri', axismundi_op_term_usage_table() => 'source_uri', axismundi_op_thread_edges_table() => 'child_uri' ) as $table => $column ) { $wpdb->delete( $table, array( $column => $object_uri ) ); }
			$wpdb->delete( axismundi_op_object_index_table(), array( 'object_uri_hash' => hash( 'sha256', $object_uri ) ) );
		}
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE actor_uri=%s', axismundi_act_activities_table(), $uri ) );
		foreach ( $rows as $id ) { $wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $id ) ); }
		$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri' => $uri ) );
		$id = $actor->get_identity_id();
		$wpdb->delete( axismundi_actors_meta_table(), array( 'actor_id' => $id ) );
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_endpoints_table(), axismundi_actors_addresses_table(), axismundi_actors_texts_table(), axismundi_actors_signing_keys_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $id ) );
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	wp_set_current_user( $original_user );
	$_COOKIE = $cookies;
	foreach ( $users as $id ) { wp_delete_user( $id ); }
}
$assert( $ledger_before === $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_activities_table() . ' ORDER BY id', ARRAY_A ), 'All pre-existing ledger rows remain byte-for-byte unchanged' );
$assert( $principals_before === $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A ), 'All pre-existing principal rows remain unchanged' );
$assert( $meta_before === $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_meta_table() . ' ORDER BY actor_id,meta_key', ARRAY_A ), 'Actor metadata and disclosure settings leave no fixture rows' );
printf( "PASS: %d C2S HTTP checks\n", $checks );
