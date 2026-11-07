<?php
/** Actor receipt isolation and transport regression; development WP CLI only. @package AxismundiActivityPubBridge */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$users = array();
$actors = array();
$uris = array();
$before_user = get_current_user_id();
$prefix = 'https://example.com/receipt-' . wp_generate_uuid4();
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $ok ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	echo 'PASS: ' . $label . "\n";
};
$fail = null;
$defer = null;
$key_lookup = null;
$shared_public_before = get_option( 'ax_bridge_shared_inbox_public', null );
try {
	$assert( axismundi_act_install(), 'Receipt schema installs and verifies' );
	for ( $i = 0; $i < 2; ++$i ) {
		$user = wp_insert_user( array( 'user_login' => 'axreceipt' . strtolower( wp_generate_password( 10, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
		if ( is_wp_error( $user ) ) {
			throw new RuntimeException( $user->get_error_message() );
		}
		$users[] = (int) $user;
		$actor = axismundi_actors_ensure_for_user( (int) $user );
		$actors[] = $actor;
		axismundi_actors_register_handle( $actor->get_identity_id(), 'axreceipt' . strtolower( wp_generate_password( 9, false ) ) );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
		$actors[ $i ] = axismundi_actors_get_by_identity( $actor->get_identity_id() );
		// Model an existing pre-S3b Actor; new-Actor keys have a separate audit.
		$wpdb->update( axismundi_actors_actors_table(), array( 'transport_kind' => 'upstream_user', 'local_signing_key_id' => null ), array( 'identity_id' => $actor->get_identity_id() ) );
	}
	$group = axismundi_actors_create_managed_actor( array( 'owner_user_id' => $users[0], 'status' => 'public', 'preferred_username' => 'axreceipt' . strtolower( wp_generate_password( 9, false ) ) ) );
	$assert( $group instanceof Axismundi_Actor, 'A public Group without local_user_id exists' );
	$actors[] = $group;
	$wpdb->update( axismundi_actors_actors_table(), array( 'transport_kind' => 'upstream_application', 'local_signing_key_id' => null ), array( 'identity_id' => $group->get_identity_id() ) );
	$remote_uri = $prefix . '/actor';
	$remote = axismundi_actors_upsert_remote( array( 'uri' => $remote_uri, 'actor_type' => 'Person', 'display_name' => 'Receipt fixture',
		'endpoints' => array( 'inbox' => $remote_uri . '/inbox', 'outbox' => $remote_uri . '/outbox' ),
		'payload' => array( 'id' => $remote_uri, 'type' => 'Person' ) ) );
	$assert( $remote instanceof Axismundi_Actor, 'Remote sender is cached without fetching a request body' );
	$actors[] = $remote;
	$payload = static function ( string $suffix ) use ( $prefix, $remote_uri, &$uris ) : array {
		$uris[] = $prefix . '/' . $suffix;
		return array( 'id' => end( $uris ), 'type' => 'Like', 'actor' => $remote_uri, 'object' => $prefix . '/object', 'published' => '2020-01-01T00:00:00Z' );
	};
	$a = $payload( 'one' );
	$a['to'] = array( $actors[0]->get_uri() );
	$a['bcc'] = array( 'https://example.com/private-recipient' );
	$first = axismundi_activitypub_bridge_record_inbox( $a, array( $users[0] ) );
	$assert( $first instanceof Axismundi_Activity, 'Legacy verified handoff records a receipt' );
	$again = axismundi_activitypub_bridge_record_inbox( $a, array( $users[0] ) );
	$assert( $again instanceof Axismundi_Activity && $first->get_id() === $again->get_id(), 'Identical retries reuse the ledger row' );
	$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE activity_id = %d', axismundi_act_inbox_table(), $first->get_id() ) );
	$assert( 1 === $count, 'Identical delivery adds no receipt duplicate' );
	wp_set_current_user( $users[0] );
	$page = axismundi_act_get_actor_inbox_page( $actors[0]->get_uri() );
	$assert( count( $page['items'] ) === 1 && ! isset( $page['items'][0]['bcc'] ), 'Operator sees receipt with blind recipients removed' );
	$assert( isset( $first->get_payload()['bcc'] ), 'Lossless ledger retains blind recipients' );
	$sanitized = axismundi_act_inbox_payload( array( 'object' => array( 'bcc' => array( 'secret' ), 'content' => 'retained' ) ) );
	$assert( ! isset( $sanitized['object']['bcc'] ) && 'retained' === $sanitized['object']['content'], 'Embedded blind addressing is removed without losing object content' );
	wp_set_current_user( $users[1] );
	$assert( is_wp_error( axismundi_act_get_actor_inbox_page( $actors[0]->get_uri() ) ), 'Another Account owner cannot read this Inbox' );
	$assert( array() === axismundi_act_get_actor_inbox_page( $actors[1]->get_uri() )['items'], 'Addressing no unrelated Actor creates no unrelated membership' );
	wp_set_current_user( 0 );
	$assert( is_wp_error( axismundi_act_get_actor_inbox_page( $actors[0]->get_uri() ) ), 'Anonymous and unbound remote requests cannot read the private collection' );
	$b = $payload( 'group' );
	$b['to'] = array( $group->get_uri() );
	$assert( axismundi_activitypub_bridge_record_inbox( $b ) instanceof Axismundi_Activity, 'Shared delivery can resolve a Group without a WP user' );
	wp_set_current_user( $users[0] );
	$assert( 1 === count( axismundi_act_get_actor_inbox_page( $group->get_uri() )['items'] ), 'Group operator reads Group receipts' );
	$assert( axismundi_activitypub_bridge_inbox_url( $group ) !== axismundi_activitypub_bridge_shared_inbox_url(), 'Group Inbox is not the shared endpoint' );
	$assert( axismundi_activitypub_bridge_inbox_url( $group ) !== axismundi_activitypub_bridge_inbox_url( $actors[0] ), 'Actors have distinct Inbox URLs' );
	$assert( is_wp_error( axismundi_act_get_actor_inbox_page( $group->get_uri(), 20, 'bad' ) ), 'Malformed cursors are rejected' );
	$assert( axismundi_act_record_activity( $a, 'inbound', array( $actors[1]->get_identity_id() ) ) instanceof Axismundi_Activity, 'A later delivery may add a second recipient to an existing Activity' );
	$changed = $a;
	$changed['content'] = 'changed';
	$assert( is_wp_error( axismundi_act_record_activity( $changed, 'inbound', array( $group->get_identity_id() ) ) ), 'Payload conflicts cannot add recipients' );
	$assert( 1 === count( axismundi_act_get_actor_inbox_page( $group->get_uri() )['items'] ), 'Conflicting replay leaves Group receipts untouched' );
	$c = $payload( 'failed-membership' );
	$fail = static fn( string $sql ) : string => str_contains( $sql, 'INSERT INTO' ) && str_contains( $sql, 'ax_activity_inbox' ) ? 'INSERT INTO ax_receipt_missing_table VALUES (1)' : $sql;
	add_filter( 'query', $fail );
	$previous = $wpdb->suppress_errors( true );
	try {
		$result = axismundi_act_record_activity( $c, 'inbound', array( $group->get_identity_id() ) );
	} finally {
		remove_filter( 'query', $fail );
		$fail = null;
		$wpdb->suppress_errors( $previous );
	}
	$assert( is_wp_error( $result ) && null === axismundi_act_get( $c['id'] ), 'Receipt INSERT failure rolls back the new Activity' );
	foreach ( array( 'START TRANSACTION', 'COMMIT' ) as $statement ) {
		$c = $payload( strtolower( str_replace( ' ', '-', $statement ) ) );
		$fail = static fn( string $sql ) : string => $statement === $sql ? 'INSERT INTO ax_receipt_missing_table VALUES (1)' : $sql;
		add_filter( 'query', $fail );
		$previous = $wpdb->suppress_errors( true );
		try {
			$result = axismundi_act_record_activity( $c, 'inbound', array( $group->get_identity_id() ) );
		} finally {
			remove_filter( 'query', $fail );
			$fail = null;
			$wpdb->suppress_errors( $previous );
		}
		$assert( is_wp_error( $result ) && null === axismundi_act_get( $c['id'] ), $statement . ' failure returns an error without a committed Activity' );
	}
	$c = $payload( 'invalid-recipient' );
	$assert( is_wp_error( axismundi_act_record_activity( $c, 'inbound', array( $remote->get_identity_id() ) ) ) && null === axismundi_act_get( $c['id'] ), 'A remote receipt target cannot become a local Inbox member' );
	$public = $payload( 'public-only' );
	$public['to'] = array( 'https://www.w3.org/ns/activitystreams#Public' );
	$assert( array() === axismundi_activitypub_bridge_receipt_targets( $public ), 'Public alone never fans out to all local Actors' );
	$attribution = $public;
	$attribution['object'] = array( 'id' => $prefix . '/object', 'attributedTo' => $group->get_uri() );
	$assert( array() === axismundi_activitypub_bridge_receipt_targets( $attribution ), 'Attribution alone is not a receipt' );
	$old = $payload( 'historical' );
	$old['to'] = array( $group->get_uri() );
	$historical = axismundi_act_record_activity( $old, 'inbound' );
	$report = axismundi_activitypub_bridge_backfill_inbox( $historical->get_id() - 1, 1 );
	$assert( ! is_wp_error( $report ) && 1 === $report['scanned'], 'Bounded backfill indexes explicit historical recipients' );
	$report = axismundi_activitypub_bridge_backfill_inbox( $historical->get_id() - 1, 1 );
	$assert( 2 === count( axismundi_act_get_actor_inbox_page( $group->get_uri() )['items'] ), 'Backfill retry does not duplicate membership' );
	$page = axismundi_act_get_actor_inbox_page( $group->get_uri(), 1 );
	$next = axismundi_act_get_actor_inbox_page( $group->get_uri(), 1, $page['next_cursor'] );
	$assert( $page['has_more'] && $page['items'][0]['id'] !== $next['items'][0]['id'], 'Receipt cursor pages have no duplicate members' );
	$rest = '/axismundi/activitypub/actors/' . $group->get_uuid() . '/inbox';
	$request = new WP_REST_Request( 'GET', $rest );
	$request->set_param( 'page', 1 );
	$response = rest_do_request( $request );
	$assert( 200 === $response->get_status() && 'OrderedCollectionPage' === $response->get_data()['type'], 'Actor Inbox REST GET serves its scoped collection' );
	$assert( 'private, no-store' === $response->get_headers()['Cache-Control'], 'Inbox responses cannot enter a shared cache' );
	wp_set_current_user( $users[1] );
	$assert( 403 === rest_do_request( $request )->get_status(), 'REST GET rechecks Account authorization' );
	wp_set_current_user( $users[0] );
	$post = new WP_REST_Request( 'POST', $rest );
	$post->set_header( 'Content-Type', 'application/activity+json' );
	$d = $payload( 'route' );
	$d['uuid'] = $actors[0]->get_uuid();
	$post->set_body( wp_json_encode( $d ) );
	$assert( 401 === rest_do_request( $post )->get_status() && null === axismundi_act_get( $d['id'] ), 'Unsigned POST is rejected before recording' );
	// This fixture exercises dispatch after the verifier, not a cryptographic signature round trip.
	$defer = static fn() : bool => true;
	add_filter( 'activitypub_defer_signature_verification', $defer );
	try {
		$response = rest_do_request( $post );
	} finally {
		remove_filter( 'activitypub_defer_signature_verification', $defer );
		$defer = null;
	}
	$assert( 202 === $response->get_status(), 'Verified-dispatch fixture reaches the Actor route without redirect' );
	$assert( 3 === count( axismundi_act_get_actor_inbox_page( $group->get_uri() )['items'] ), 'Signed route recipient wins over body uuid, even without to/cc' );
	$key = openssl_pkey_new( array( 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
	openssl_pkey_export( $key, $private );
	$public_pem = openssl_pkey_get_details( $key )['key'];
	$key_id = $remote_uri . '#receipt-key';
	$key_lookup = static function ( $value, $url ) use ( $key_id, $remote_uri, $public_pem ) {
		return $url === $key_id ? array( 'id' => $remote_uri, 'type' => 'Person', 'publicKey' => array( 'id' => $key_id, 'owner' => $remote_uri, 'publicKeyPem' => $public_pem ) ) : $value;
	};
	add_filter( 'activitypub_pre_http_get_remote_object', $key_lookup, 10, 2 );
	$crypto_payload = $payload( 'crypto' );
	$crypto_body = wp_json_encode( $crypto_payload );
	$signed = ( new Activitypub\Signature\Http_Signature_Draft() )->sign( array(
		'method' => 'POST', 'body' => $crypto_body, 'key_id' => $key_id, 'private_key' => $private,
		'headers' => array( 'Date' => gmdate( 'D, d M Y H:i:s' ) . ' GMT' ),
	), axismundi_activitypub_bridge_actor_inbox_url( $group ) );
	$crypto_request = new WP_REST_Request( 'POST', $rest );
	$crypto_request->set_header( 'Content-Type', 'application/activity+json' );
	$crypto_request->set_header( 'Host', wp_parse_url( home_url(), PHP_URL_HOST ) );
	foreach ( $signed['headers'] as $name => $value ) { $crypto_request->set_header( $name, $value ); }
	$crypto_request->set_body( $crypto_body . ' ' );
	$assert( 401 === rest_do_request( $crypto_request )->get_status() && null === axismundi_act_get( $crypto_payload['id'] ), 'Real verifier rejects a body changed after signing' );
	$crypto_request->set_body( $crypto_body );
	$crypto_request->set_route( '/axismundi/activitypub/actors/' . $actors[0]->get_uuid() . '/inbox' );
	$assert( 401 === rest_do_request( $crypto_request )->get_status() && null === axismundi_act_get( $crypto_payload['id'] ), 'Real verifier rejects changing the signed recipient URL' );
	$crypto_request->set_route( $rest );
	$crypto_response = rest_do_request( $crypto_request );
	$assert( 202 === $crypto_response->get_status(), 'RSA-signed POST passes the unmodified upstream verifier and records a receipt' );
	$old_featured = rest_url( 'axismundi/v1/actors/' . $actors[0]->get_uuid() . '/featured' );
	$new_featured = axismundi_actors_featured_uri( $actors[0] );
	$assert( $new_featured === axismundi_actors_collection_target_uri( $old_featured ) && axismundi_act_outbox_same_intent( array( 'type' => 'Add', 'target' => $old_featured ), array( 'type' => 'Add', 'target' => $new_featured ) ), 'Local historical curation targets remain retry-equivalent without rewriting the ledger' );
	$foreign_featured = 'https://foreign.test/wp-json/axismundi/v1/actors/' . $actors[0]->get_uuid() . '/featured';
	$assert( $foreign_featured === axismundi_actors_collection_target_uri( $foreign_featured ), 'A matching UUID under another origin is never treated as our local collection alias' );
	$old_followers = rest_url( 'axismundi/v1/actors/' . $actors[0]->get_uuid() . '/followers' );
	$new_followers = axismundi_op_actor_followers_url( $actors[0] );
	$groups = array( $new_followers => false );
	$assert( 'followers' === axismundi_act_audience_visibility( array( $old_followers ), array(), $new_followers ) && axismundi_op_sent_matches_audience( array( 'to' => array( $old_followers ) ), 'none', $groups ), 'Historical followers addressing keeps its visibility and non-Group Sent classification' );
	$crypto_request->set_route( '/axismundi/activitypub/inbox' );
	$assert( 401 === rest_do_request( $crypto_request )->get_status(), 'Actor Inbox signature cannot be replayed against shared Inbox URL' );
	$shared_payload = $payload( 'shared-crypto' );
	$shared_payload['to'] = array( $actors[0]->get_uri(), $actors[1]->get_uri() );
	$shared_body = wp_json_encode( $shared_payload );
	$shared_request = new WP_REST_Request( 'POST', '/axismundi/activitypub/inbox' );
	$shared_request->set_header( 'Content-Type', 'application/activity+json' );
	$shared_request->set_body( $shared_body );
	$assert( 401 === rest_do_request( $shared_request )->get_status() && null === axismundi_act_get( $shared_payload['id'] ), 'Shared Inbox rejects unsigned delivery before recording' );
	$shared_signed = ( new Activitypub\Signature\Http_Signature_Draft() )->sign( array(
		'method' => 'POST', 'body' => $shared_body, 'key_id' => $key_id, 'private_key' => $private,
		'headers' => array( 'Date' => gmdate( 'D, d M Y H:i:s' ) . ' GMT' ),
	), axismundi_activitypub_bridge_shared_inbox_url() );
	$shared_request->set_header( 'Host', wp_parse_url( home_url(), PHP_URL_HOST ) );
	foreach ( $shared_signed['headers'] as $name => $value ) { $shared_request->set_header( $name, $value ); }
	$shared_response = rest_do_request( $shared_request );
	$shared_activity = axismundi_act_get( $shared_payload['id'] );
	$assert( 202 === $shared_response->get_status() && $shared_activity instanceof Axismundi_Activity, 'RSA-signed shared delivery enters the same Bridge ledger' );
	$assert( 2 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE activity_id=%d', axismundi_act_inbox_table(), $shared_activity->get_id() ) ), 'Shared delivery records only its two explicit local recipients' );
	$assert( 202 === rest_do_request( $shared_request )->get_status() && 2 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE activity_id=%d', axismundi_act_inbox_table(), $shared_activity->get_id() ) ), 'Repeated shared POST creates no duplicate receipts' );
	foreach ( array( '/axismundi/v1/activitypub/inbox' => 'axismundi/v1/activitypub/inbox', '/axismundi/v1/actors/' . $actors[0]->get_uuid() . '/inbox' => 'axismundi/v1/actors/' . $actors[0]->get_uuid() . '/inbox' ) as $old_route => $old_path ) {
		$legacy_request = new WP_REST_Request( 'POST', $old_route );
		$legacy_request->set_header( 'Content-Type', 'application/activity+json' );
		$legacy_request->set_header( 'Host', wp_parse_url( home_url(), PHP_URL_HOST ) );
		$legacy_request->set_body( $shared_body );
		foreach ( $shared_signed['headers'] as $name => $value ) { $legacy_request->set_header( $name, $value ); }
		$assert( 401 === rest_do_request( $legacy_request )->get_status(), 'Canonical shared signature cannot be replayed at legacy target: ' . $old_route );
		$legacy_signed = ( new Activitypub\Signature\Http_Signature_Draft() )->sign( array( 'method' => 'POST', 'body' => $shared_body, 'key_id' => $key_id, 'private_key' => $private, 'headers' => array( 'Date' => gmdate( 'D, d M Y H:i:s' ) . ' GMT' ) ), rest_url( $old_path ) );
		foreach ( $legacy_signed['headers'] as $name => $value ) { $legacy_request->set_header( $name, $value ); }
		$assert( 202 === rest_do_request( $legacy_request )->get_status(), 'Legacy signed POST is handled at its original URL without redirect: ' . $old_route );
	}
	$assert( ! empty( rest_get_server()->get_routes()['/axismundi/activitypub/inbox'][0]['methods']['GET'] ), 'Shared Inbox offers a separate public GET collection' );
	foreach ( array( '/axismundi/activitypub/inbox' => array( 'axismundi_op_get_shared_inbox', 'axismundi_activitypub_bridge_receive_shared_inbox' ), '/axismundi/activitypub/actors/(?P<uuid>[0-9a-f-]{36})/inbox' => array( 'axismundi_op_get_actor_inbox', 'axismundi_activitypub_bridge_receive_actor_inbox' ) ) as $route => $expected_callbacks ) {
		$get = array(); $post = array();
		foreach ( rest_get_server()->get_routes()[$route] as $handler ) {
			if ( ! empty( $handler['methods']['GET'] ) ) { $get[] = $handler['callback']; }
			if ( ! empty( $handler['methods']['POST'] ) ) { $post[] = $handler['callback']; }
		}
		$assert( array( $expected_callbacks[0] ) === $get && array( $expected_callbacks[1] ) === $post, 'Exactly one OP read handler and one Bridge POST handler: ' . $route );
	}
	$server = $GLOBALS['wp_rest_server'];
	try {
		$GLOBALS['wp_rest_server'] = new WP_REST_Server();
		axismundi_op_register_inbox_routes();
		$read_only_routes = rest_get_server()->get_routes();
		$assert( ! empty( $read_only_routes['/axismundi/activitypub/inbox'][0]['methods']['GET'] ) && empty( $read_only_routes['/axismundi/activitypub/inbox'][0]['methods']['POST'] ) && ! empty( $read_only_routes['/axismundi/activitypub/actors/(?P<uuid>[0-9a-f-]{36})/inbox'][0]['methods']['GET'] ), 'OP independently registers Inbox reads without registering transport POST or requiring its verifier' );
	} finally { $GLOBALS['wp_rest_server'] = $server; }
	$follow_snapshot = axismundi_act_import_follow_snapshot( $actors[0]->get_uri(), $remote_uri, 'accepted', 'shared-inbox-test' );
	$assert( ! is_wp_error( $follow_snapshot ), 'Accepted local follower exists for shared delivery' );
	$follower_payload = $payload( 'shared-followers' );
	$follower_payload['to'] = array( 'https://www.w3.org/ns/activitystreams#Public' );
	$follower_request = new WP_REST_Request( 'POST', '/axismundi/activitypub/inbox' );
	$follower_request->set_header( 'Content-Type', 'application/activity+json' );
	$follower_request->set_body( wp_json_encode( $follower_payload ) );
	$defer = static fn() : bool => true;
	add_filter( 'activitypub_defer_signature_verification', $defer );
	try {
		$follower_response = rest_do_request( $follower_request );
	} finally {
		remove_filter( 'activitypub_defer_signature_verification', $defer );
		$defer = null;
	}
	$follower_activity = axismundi_act_get( $follower_payload['id'] );
	$assert( 202 === $follower_response->get_status() && $follower_activity instanceof Axismundi_Activity && 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE activity_id=%d AND actor_identity_id=%d', axismundi_act_inbox_table(), $follower_activity->get_id(), $actors[0]->get_identity_id() ) ), 'Public shared delivery reaches the accepted local follower' );
	$assert( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE activity_id=%d', axismundi_act_inbox_table(), $follower_activity->get_id() ) ), 'Public shared delivery never becomes a site-wide broadcast' );
	delete_option( 'ax_bridge_shared_inbox_public' );
	$read_shared = new WP_REST_Request( 'GET', '/axismundi/activitypub/inbox' );
	$read_shared->set_param( 'page', 1 );
	wp_set_current_user( 0 );
	$public_page = rest_do_request( $read_shared );
	if ( 200 !== $public_page->get_status() ) { throw new RuntimeException( 'Shared GET response: ' . wp_json_encode( $public_page->get_data() ) ); }
	$public_ids = array_column( $public_page->get_data()['orderedItems'] ?? array(), 'id' );
	$assert( 200 === $public_page->get_status() && in_array( $follower_payload['id'], $public_ids, true ) && ! in_array( $shared_payload['id'], $public_ids, true ), 'Default-on anonymous shared GET includes public receipt and excludes private shared receipt' );
	$legacy_public = $payload( 'legacy-public' );
	$legacy_public['to'] = array( 'https://www.w3.org/ns/activitystreams#Public', $actors[0]->get_uri() );
	$legacy_row = axismundi_act_record_activity( $legacy_public, 'inbound', array( $actors[0]->get_identity_id() ) );
	$actor_public = $payload( 'actor-public' );
	$actor_public['to'] = $legacy_public['to'];
	$actor_row = axismundi_activitypub_bridge_record_inbox( $actor_public, array(), false, $actors[0], null, 'actor' );
	$legacy_receipt = $wpdb->get_row( $wpdb->prepare( 'SELECT received_via FROM %i WHERE activity_id=%d LIMIT 1', axismundi_act_inbox_table(), $legacy_row->get_id() ), ARRAY_A );
	$assert( is_array( $legacy_receipt ) && '' === $legacy_receipt['received_via'], 'Unclassified receipts remain unclassified without backfill' );
	$public_page = rest_do_request( $read_shared );
	$public_ids = array_column( $public_page->get_data()['orderedItems'] ?? array(), 'id' );
	$assert( ! in_array( $legacy_public['id'], $public_ids, true ) && ! in_array( $actor_public['id'], $public_ids, true ), 'Public alone cannot disclose legacy or Actor-only receipts through shared GET' );
	$promoted = axismundi_activitypub_bridge_record_inbox( $actor_public, array(), false, null, array( $actors[0]->get_identity_id() => $actors[0], $actors[1]->get_identity_id() => $actors[1] ), 'shared' );
	$assert( $promoted instanceof Axismundi_Activity && $actor_row->get_id() === $promoted->get_id(), 'A newly observed shared retry reuses the Activity instead of backfilling history' );
	axismundi_activitypub_bridge_record_inbox( $actor_public, array(), false, $actors[0], null, 'actor' );
	$assert( 'shared' === $wpdb->get_var( $wpdb->prepare( 'SELECT received_via FROM %i WHERE activity_id=%d AND actor_identity_id=%d', axismundi_act_inbox_table(), $actor_row->get_id(), $actors[0]->get_identity_id() ) ), 'Later Actor delivery cannot erase an observed shared receipt' );
	$shared_one = axismundi_act_get_shared_inbox_page( 1 );
	$shared_two = axismundi_act_get_shared_inbox_page( 1, $shared_one['next_cursor'] );
	$assert( $shared_one['has_more'] && count( $shared_one['items'] ) === 1 && count( $shared_two['items'] ) === 1 && $shared_one['items'][0]['id'] !== $shared_two['items'][0]['id'], 'Shared cursor groups recipients before page slicing without duplicate Activities' );
	$wpdb->update( axismundi_act_activities_table(), array( 'has_public_audience' => 1 ), array( 'id' => $shared_activity->get_id() ) );
	$shared_after_stale = axismundi_act_get_shared_inbox_page( 50 );
	$assert( ! in_array( $shared_payload['id'], array_column( $shared_after_stale['items'], 'id' ), true ), 'Payload audience gate rejects private content even with a stale public candidate flag' );
	$wpdb->update( axismundi_act_activities_table(), array( 'has_public_audience' => 0 ), array( 'id' => $shared_activity->get_id() ) );
	$bad_shared = new WP_REST_Request( 'GET', '/axismundi/activitypub/inbox' );
	$bad_shared->set_param( 'cursor', 'bad' );
	$assert( 400 === rest_do_request( $bad_shared )->get_status(), 'Shared GET rejects malformed cursors' );
	update_option( 'ax_bridge_shared_inbox_public', '0' );
	$assert( 404 === rest_do_request( $read_shared )->get_status(), 'Disclosure setting hides shared GET from all viewers' );
	$assert( 202 === rest_do_request( $shared_request )->get_status(), 'Disabling shared GET does not disable signed POST reception' );
	wp_set_current_user( $users[0] );
	$wpdb->delete( axismundi_act_relations_table(), array( 'subject_actor_uri_hash' => hash( 'sha256', $actors[0]->get_uri() ), 'object_actor_uri_hash' => hash( 'sha256', $remote_uri ) ) );
	remove_filter( 'activitypub_pre_http_get_remote_object', $key_lookup, 10 );
	$key_lookup = null;
	$owner_row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE identity_id = %d AND user_id = %d', axismundi_actors_managers_table(), $group->get_identity_id(), $users[0] ), ARRAY_A );
	$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $group->get_identity_id(), 'user_id' => $users[0] ) );
	try {
		$assert( 403 === rest_do_request( $request )->get_status(), 'Revocation takes effect without refreshing Session or acting-Actor state' );
	} finally {
		$wpdb->insert( axismundi_actors_managers_table(), $owner_row );
	}
	$assert( ! str_contains( wp_json_encode( axismundi_act_get_actor_inbox_page( $group->get_uri() ) ), '$seen' ), 'AP Inbox has no mailbox read-state properties' );
	$binding = axismundi_actors_transport_binding( $actors[0] );
	$old_pem = axismundi_activitypub_bridge_public_key( $actors[0] );
	$old_private = axismundi_activitypub_bridge_private_key( $actors[0] );
	$assert( array( 'kind' => 'upstream_user', 'user_id' => $users[0] ) === $binding, 'Transport records upstream provenance explicitly' );
	$assert( '' !== $old_pem && '' !== $old_private, 'Existing upstream key material available' );
	$wpdb->update( axismundi_actors_actors_table(), array( 'local_user_id' => null ), array( 'identity_id' => $actors[0]->get_identity_id() ) );
	try {
		$detached = axismundi_actors_get_by_identity( $actors[0]->get_identity_id() );
		$assert( null === $detached->get_local_user_id() && $old_pem === axismundi_activitypub_bridge_public_key( $detached ) && $old_private === axismundi_activitypub_bridge_private_key( $detached ), 'Ownership compatibility changes cannot select a different signing key' );
		$assert( axismundi_actors_migrate_transport_bindings() && $binding === axismundi_actors_transport_binding( $detached ), 'Migration replay never rebinds initialized transport' );
		$assert( $detached->get_identity_id() === axismundi_actors_get_transport_actor_for_user( $users[0] )->get_identity_id(), 'Old per-user route resolves transport rather than ownership' );
	} finally {
		$wpdb->update( axismundi_actors_actors_table(), array( 'local_user_id' => $users[0] ), array( 'identity_id' => $actors[0]->get_identity_id() ) );
	}
	$group_pem = axismundi_activitypub_bridge_public_key( $group );
	$wpdb->update( axismundi_actors_actors_table(), array( 'transport_kind' => null, 'transport_user_id' => null ), array( 'identity_id' => $group->get_identity_id() ) );
	$fail = static function ( string $sql ) : string {
		return str_contains( $sql, 'SET a.transport_kind' ) ? 'SELECT * FROM ax_missing_transport_audit' : $sql;
	};
	add_filter( 'query', $fail );
	$old_suppress = $wpdb->suppress_errors();
	try {
		$assert( ! axismundi_actors_migrate_transport_bindings(), 'Backfill write failure is reported' );
		$assert( '' === axismundi_activitypub_bridge_public_key( $group ) && '' === axismundi_activitypub_bridge_private_key( $group ), 'Missing binding withholds keys instead of falling back' );
	} finally {
		remove_filter( 'query', $fail );
		$fail = null;
		$wpdb->suppress_errors( $old_suppress );
	}
	$assert( axismundi_actors_migrate_transport_bindings() && $group_pem === axismundi_activitypub_bridge_public_key( $group ), 'Legacy Group key does not rotate during migration' );
	$fail = static function ( string $sql ) : string {
		return str_contains( $sql, 'SELECT * FROM ' . axismundi_act_relations_table() ) ? 'SELECT * FROM ax_missing_block_audit' : $sql;
	};
	add_filter( 'query', $fail );
	$old_suppress = $wpdb->suppress_errors();
	try {
		$assert( is_wp_error( axismundi_activitypub_bridge_inbox_permitted( $b, $group ) ), 'Block lookup SQL failure is not an absent Block' );
	} finally {
		remove_filter( 'query', $fail );
		$fail = null;
		$wpdb->suppress_errors( $old_suppress );
	}
	$assert( ! is_wp_error( axismundi_act_write_relation( 'block', $group->get_uri(), $remote_uri, 'outbound', 'active', null, null ) ), 'Group Block fixture stored' );
	$blocked = $payload( 'account-blocked' );
	$blocked['to'] = array( $group->get_uri() );
	$assert( is_wp_error( axismundi_activitypub_bridge_record_inbox( $blocked, array(), false, $group ) ) && null === axismundi_act_get( $blocked['id'] ), 'Actor route refuses Account-blocked sender before recording' );
	$assert( is_wp_error( axismundi_activitypub_bridge_record_inbox( $blocked ) ), 'Shared route cannot bypass Account Block' );
	$mixed = $payload( 'mixed-account-block' );
	$mixed['to'] = array( $group->get_uri(), $actors[1]->get_uri() );
	$mixed_row = axismundi_activitypub_bridge_record_inbox( $mixed );
	$assert( $mixed_row instanceof Axismundi_Activity, 'Unblocked recipient still receives shared delivery' );
	$members = $wpdb->get_col( $wpdb->prepare( 'SELECT actor_identity_id FROM %i WHERE activity_id = %d', axismundi_act_inbox_table(), $mixed_row->get_id() ) );
	$assert( array( $actors[1]->get_identity_id() ) === array_map( 'intval', $members ), 'Shared receipts exclude only blocked Account' );
	$mixed_follow = $payload( 'mixed-blocked-follow' );
	$mixed_follow['type'] = 'Follow';
	$wpdb->update( axismundi_actors_actors_table(), array( 'manually_approves_followers' => 1 ), array( 'identity_id' => $group->get_identity_id() ) );
	$mixed_follow['object'] = $group->get_uri();
	$mixed_follow['to'] = array( $group->get_uri(), $actors[1]->get_uri() );
	$assert( axismundi_activitypub_bridge_record_inbox( $mixed_follow ) instanceof Axismundi_Activity
		&& null === axismundi_act_get_relation( 'follow', $remote_uri, $group->get_uri() ), 'Another Account receipt cannot create a Follow relation for blocked Group' );
	$wpdb->update( axismundi_act_relations_table(), array( 'state' => 'undone' ), array( 'relation_type' => 'block', 'subject_actor_uri_hash' => hash( 'sha256', $group->get_uri() ) ) );
	$assert( axismundi_activitypub_bridge_record_inbox( $blocked ) instanceof Axismundi_Activity, 'Undone Block allows later delivery' );
	$site_blocks = get_option( 'activitypub_site_blocked_domains', null );
	update_option( 'activitypub_site_blocked_domains', array( 'example.com' ) );
	try {
		$site_blocked = $payload( 'site-blocked' );
		$site_blocked['to'] = array( $group->get_uri() );
		$assert( is_wp_error( axismundi_activitypub_bridge_record_inbox( $site_blocked ) ) && null === axismundi_act_get( $site_blocked['id'] ), 'Site moderation remains effective without Account Block' );
	} finally {
		if ( null === $site_blocks ) { delete_option( 'activitypub_site_blocked_domains' ); } else { update_option( 'activitypub_site_blocked_domains', $site_blocks ); }
	}
	echo "PASS: {$checks} Actor Inbox checks\n";
} finally {
	if ( null === $shared_public_before ) { delete_option( 'ax_bridge_shared_inbox_public' ); } else { update_option( 'ax_bridge_shared_inbox_public', $shared_public_before ); }
	if ( $fail ) { remove_filter( 'query', $fail ); }
	if ( $defer ) { remove_filter( 'activitypub_defer_signature_verification', $defer ); }
	if ( $key_lookup ) { remove_filter( 'activitypub_pre_http_get_remote_object', $key_lookup, 10 ); }
	wp_set_current_user( $before_user );
	foreach ( $uris as $uri ) {
		$row = axismundi_act_get( $uri );
		if ( $row ) {
			$wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $row->get_id() ) );
			$wpdb->delete( axismundi_act_activities_table(), array( 'id' => $row->get_id() ) );
		}
	}
	foreach ( array_reverse( $actors ) as $actor ) {
		if ( ! $actor instanceof Axismundi_Actor ) { continue; }
		$id = $actor->get_identity_id();
		$wpdb->delete( axismundi_actors_signing_keys_table(), array( 'identity_id' => $id ) );
		$wpdb->delete( axismundi_act_relations_table(), array( 'subject_actor_uri_hash' => hash( 'sha256', $actor->get_uri() ) ) );
		$wpdb->delete( axismundi_act_inbox_table(), array( 'actor_identity_id' => $id ) );
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_actors_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $id ) );
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $user ) { wp_delete_user( $user ); }
}
