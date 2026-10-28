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
	$assert( 422 === $request( $path, array_merge( $like, array( 'type' => 'Create' ) ), $auth )[0], 'Unimplemented C2S verbs are closed despite ledger support' );
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
