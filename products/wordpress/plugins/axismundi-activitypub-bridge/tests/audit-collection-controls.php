<?php
/** Forum-independent nested controls: transport proof, immutable records, and no membership effects. */
defined( 'ABSPATH' ) || exit( 1 );
$receive_control = $receive_control ?? 'axismundi_activitypub_bridge_receive_collection_control';
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { ++$checks; if ( ! $ok ) { throw new RuntimeException( $label ); } echo "PASS $label\n"; };
$actors = array(); $uris = array(); $documents = array(); $uid = 0; $original_requests = 0;
$previous = get_current_user_id();
$previous_diagnostics = get_option( 'ax_activitypub_bridge_inbox_diagnostics', null );
$prefix = 'https://example.com/ax-controls-' . wp_generate_uuid4();
$http = static function ( $pre, $args, $url ) use ( &$documents, &$original_requests ) {
	if ( isset( $documents[$url] ) ) { ++$original_requests; return new WP_Error( 'ax_op_remote_signed_fetch_required', 'The original Activity is not accessible to this server.' ); }
	return $pre;
};
$key_lookup = null;
$handler = null;
add_filter( 'pre_http_request', $http, 999, 3 );
try {
	$assert( axismundi_activitypub_bridge_install_collection_controls(), 'Control receipt schema installs' );
	$uid = wp_insert_user( array( 'user_login' => 'axcontrols' . strtolower( wp_generate_password( 10, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	if ( is_wp_error( $uid ) ) { throw new RuntimeException( $uid->get_error_message() ); }
	$local = axismundi_actors_ensure_for_user( $uid ); $actors[] = $local;
	foreach ( array( 'person' => 'Person', 'group' => 'Group', 'other' => 'Group' ) as $name => $type ) {
		$uri = $prefix . '/' . $name;
		$remote[$name] = axismundi_actors_upsert_remote( array( 'uri' => $uri, 'actor_type' => $type, 'endpoints' => array( 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ), 'payload' => array( 'id' => $uri, 'type' => $type, 'attributedTo' => $uri . '/moderators' ) ) );
		if ( ! $remote[$name] instanceof Axismundi_Actor ) { throw new RuntimeException( 'Remote fixture creation failed' ); }
		$actors[] = $remote[$name];
	}
	$person = $remote['person']; $group = $remote['group'];
	$principals = $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A );
	$relations = $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_relations_table() . ' ORDER BY id', ARRAY_A );
	$inner = array( 'id' => $prefix . '/add', 'type' => 'Add', 'actor' => $person->get_uri(), 'object' => $prefix . '/person', 'target' => $group->get_uri() . '/moderators', 'audience' => $group->get_uri() );
	$documents[$inner['id']] = $inner; $uris[] = $inner['id'];
	$outer = array( 'id' => $prefix . '/announce', 'type' => 'Announce', 'actor' => $group->get_uri(), 'object' => $inner, 'to' => array( $local->get_uri() ) );
	$uris[] = $outer['id'];
	$result = $receive_control( $outer, $group, array( $local->get_identity_id() ) );
	$assert( is_array( $result ) && 'unclaimed' === $result['outcome'], 'Unregistered target remains unclaimed' );
	$first = $result['collection_control_receipt'];
	$assert( axismundi_act_get( $outer['id'] ) instanceof Axismundi_Activity && null === axismundi_act_get( $inner['id'] ), 'Group announcement creates no independent Person Activity' );
	$again = $receive_control( $inner, $person, array( $local->get_identity_id() ) );
	$assert( is_array( $again ) && $first === $again['collection_control_receipt'], 'Direct and announced delivery share one inner identity' );
	$assert( axismundi_act_get( $inner['id'] ) instanceof Axismundi_Activity && $person->get_uri() === axismundi_act_get( $inner['id'] )->get_actor_uri(), 'Direct Person delivery records its own Activity' );
	$wrong = $outer; $wrong['id'] = $prefix . '/wrong-owner'; $wrong['actor'] = $remote['other']->get_uri();
	$wrong['object']['id'] = $prefix . '/wrong-owner-inner'; $wrong['object']['audience'] = $remote['other']->get_uri();
	$documents[$wrong['object']['id']] = $wrong['object']; $uris[] = $wrong['id']; $uris[] = $wrong['object']['id'];
	$bad = $receive_control( $wrong, $remote['other'], array( $local->get_identity_id() ) );
	$assert( is_wp_error( $bad ) && 'ax_bridge_control_owner' === $bad->get_error_code(), 'Wrong collection owner is rejected' );
	$wrong = $outer; $wrong['object']['actor'] = $group->get_uri();
	$wrong['id'] = $prefix . '/wrong-author'; $wrong['object']['id'] = $prefix . '/wrong-author-inner';
	$documents[$wrong['object']['id']] = $wrong['object']; $uris[] = $wrong['id']; $uris[] = $wrong['object']['id'];
	$bad = $receive_control( $wrong, $group, array( $local->get_identity_id() ) );
	$assert( is_wp_error( $bad ) && 'ax_bridge_control_actor' === $bad->get_error_code(), 'Inner Group author is rejected' );
	$wrong = $outer; $wrong['object']['type'] = 'Move';
	$bad = $receive_control( $wrong, $group, array( $local->get_identity_id() ) );
	$assert( is_wp_error( $bad ) && 'ax_bridge_control_unsupported' === $bad->get_error_code(), 'Unsupported is not unclaimed' );
	$wrong = $outer; $wrong['id'] = $prefix . '/changed-claim'; $wrong['object']['object'] = $prefix . '/forged-person'; $uris[] = $wrong['id'];
	$claim = $receive_control( $wrong, $group, array( $local->get_identity_id() ) );
	$assert( is_array( $claim ) && $first !== $claim['collection_control_receipt'] && $inner === axismundi_act_get( $inner['id'] )->get_payload(), 'Conflicting Group claim cannot overwrite verified Person history' );
	$idless = $outer; $idless['id'] = $prefix . '/idless'; unset( $idless['object']['id'] ); $uris[] = $idless['id'];
	$anonymous = $receive_control( $idless, $group, array( $local->get_identity_id() ) );
	$assert( is_array( $anonymous ) && 'unclaimed' === $anonymous['outcome'], 'Idless inner Activity records an observation' );
	$oversized = $idless; $oversized['summary'] = str_repeat( 'x', 1024 * 1024 );
	$assert( is_wp_error( $receive_control( $oversized, $group, array( $local->get_identity_id() ) ) ), 'Anonymous inner object cannot bypass the envelope size bound' );
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', axismundi_activitypub_bridge_collection_controls_table(), $anonymous['collection_control_receipt'] ), ARRAY_A );
	$assert( null === $row['remote_activity_uri'] && ! isset( json_decode( $row['payload_json'], true )['id'] ), 'No remote identity is invented' );
	$remove = $outer; $remove['id'] = $prefix . '/announce-remove'; $remove['object']['id'] = $prefix . '/remove'; $remove['object']['type'] = 'Remove';
	$uris[] = $remove['id']; $uris[] = $remove['object']['id']; $documents[$remove['object']['id']] = $remove['object'];
	$assert( is_array( $receive_control( $remove, $group, array( $local->get_identity_id() ) ) ), 'Remove is independently recorded' );
	$hits = 0;
	$handler = static function ( $callback, $target, $owner ) use ( &$hits, $group ) {
		return $target === $group->get_uri() . '/moderators' ? static function ( $control, $context ) use ( &$hits ) { ++$hits; return $context['author_verified']; } : $callback;
	};
	add_filter( 'axismundi_activitypub_bridge_collection_control_handler', $handler, 10, 3 );
	$handled = $receive_control( $outer, $group, array( $local->get_identity_id() ) );
	$receive_control( $outer, $group, array( $local->get_identity_id() ) );
	$assert( 'handled' === $handled['outcome'] && 1 === $hits, 'Owner callback receives verified context once' );
	remove_filter( 'axismundi_activitypub_bridge_collection_control_handler', $handler, 10 ); $handler = null;
	$observed_context = null;
	$handler = static function ( $callback, $target, $owner ) use ( &$observed_context ) {
		return static function ( $control, $context ) use ( &$observed_context ) { $observed_context = $context; return false; };
	};
	add_filter( 'axismundi_activitypub_bridge_collection_control_handler', $handler, 10, 3 );
	$receive_control( $remove, $group, array( $local->get_identity_id() ) );
	$assert( is_array( $observed_context ) && false === $observed_context['author_verified'] && null === axismundi_act_get( $remove['object']['id'] ), 'Group-only evidence never authenticates the Person' );
	$local_control = axismundi_act_record_activity( array( 'type' => 'Add', 'actor' => $local->get_uri(), 'object' => $person->get_uri(), 'target' => $inner['target'], 'audience' => $group->get_uri() ), 'outbound' );
	if ( is_wp_error( $local_control ) ) { throw new RuntimeException( $local_control->get_error_message() ); }
	$uris[] = $local_control->get_uri();
	$local_announcement = $outer; $local_announcement['id'] = $prefix . '/local-author'; $local_announcement['object'] = $local_control->get_payload(); $uris[] = $local_announcement['id'];
	$local_received = $receive_control( $local_announcement, $group, array( $local->get_identity_id() ) );
	$assert( is_array( $local_received ) && true === $observed_context['author_verified'] && 'outbound' === axismundi_act_get( $local_control->get_uri() )->get_direction(), 'Local author evidence is reused without an inbound shadow Activity' );
	remove_filter( 'axismundi_activitypub_bridge_collection_control_handler', $handler, 10 ); $handler = null;
	// Actual transport verification, with a test RSA key served through the upstream key-fetch seam.
	$key = openssl_pkey_new( array( 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
	openssl_pkey_export( $key, $private ); $public = openssl_pkey_get_details( $key )['key']; $key_id = $group->get_uri() . '#key';
	$key_lookup = static function ( $value, $url ) use ( $key_id, $group, $public ) { return $url === $key_id ? array( 'id' => $group->get_uri(), 'type' => 'Group', 'publicKey' => array( 'id' => $key_id, 'owner' => $group->get_uri(), 'publicKeyPem' => $public ) ) : $value; };
	add_filter( 'activitypub_pre_http_get_remote_object', $key_lookup, 10, 2 );
	$wire = $outer; $wire['id'] = $prefix . '/signed'; $uris[] = $wire['id'];
	$body = wp_json_encode( $wire );
	$request = new WP_REST_Request( 'POST', '/axismundi/v1/actors/' . $local->get_uuid() . '/inbox' );
	$request->set_header( 'Content-Type', 'application/activity+json' ); $request->set_header( 'Host', wp_parse_url( home_url(), PHP_URL_HOST ) ); $request->set_body( $body );
	$assert( 401 === rest_do_request( $request )->get_status() && ! axismundi_act_get( $wire['id'] ), 'Unsigned nested control never reaches storage' );
	$signed = ( new Activitypub\Signature\Http_Signature_Draft() )->sign( array( 'method' => 'POST', 'body' => $body, 'key_id' => $key_id, 'private_key' => $private, 'headers' => array( 'Date' => gmdate( 'D, d M Y H:i:s' ) . ' GMT' ) ), axismundi_activitypub_bridge_actor_inbox_url( $local ) );
	foreach ( $signed['headers'] as $name => $value ) { $request->set_header( $name, $value ); }
	$assert( 202 === rest_do_request( $request )->get_status(), 'Signed Group nested control passes actual ingress' );
	$request->set_body( $body . ' ' );
	$assert( 401 === rest_do_request( $request )->get_status(), 'Tampering invalidates the outer signature' );
	foreach ( array( 'wrong-target' => 403, 'unsupported' => 400 ) as $case => $status ) {
		$invalid = $outer; $invalid['id'] = $prefix . '/' . $case; $uris[] = $invalid['id'];
		if ( 'wrong-target' === $case ) { $invalid['object']['target'] = $remote['other']->get_uri() . '/moderators'; }
		else { $invalid['object']['type'] = 'Move'; }
		$invalid_body = wp_json_encode( $invalid );
		$invalid_signed = ( new Activitypub\Signature\Http_Signature_Draft() )->sign( array( 'method' => 'POST', 'body' => $invalid_body, 'key_id' => $key_id, 'private_key' => $private, 'headers' => array( 'Date' => gmdate( 'D, d M Y H:i:s' ) . ' GMT' ) ), axismundi_activitypub_bridge_actor_inbox_url( $local ) );
		$request->set_body( $invalid_body );
		foreach ( $invalid_signed['headers'] as $name => $value ) { $request->set_header( $name, $value ); }
		$assert( $status === rest_do_request( $request )->get_status() && ! axismundi_act_get( $invalid['id'] ), 'Signed invalid control does not become a transient storage error: ' . $case );
	}
	$assert( 0 === $original_requests, 'An inaccessible inner Activity is never fetched as an admission gate' );
	$assert( $principals === $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A ), 'No principal grants are created' );
	$assert( $relations === $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_relations_table() . ' ORDER BY id', ARRAY_A ), 'No relation membership changes' );
} finally {
	if ( null === $previous_diagnostics ) { delete_option( 'ax_activitypub_bridge_inbox_diagnostics' ); }
	else { update_option( 'ax_activitypub_bridge_inbox_diagnostics', $previous_diagnostics, false ); }
	remove_filter( 'pre_http_request', $http, 999 );
	if ( $key_lookup ) { remove_filter( 'activitypub_pre_http_get_remote_object', $key_lookup, 10 ); }
	if ( $handler ) { remove_filter( 'axismundi_activitypub_bridge_collection_control_handler', $handler, 10 ); }
	foreach ( $uris as $uri ) { $activity = axismundi_act_get( $uri ); if ( $activity ) { $wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $activity->get_id() ) ); $wpdb->delete( axismundi_act_activities_table(), array( 'id' => $activity->get_id() ) ); } }
	foreach ( $actors as $actor ) { if ( ! $actor instanceof Axismundi_Actor ) { continue; } $wpdb->delete( axismundi_activitypub_bridge_collection_controls_table(), array( 'group_uri' => $actor->get_uri() ) ); }
	require_once ABSPATH . 'wp-admin/includes/user.php'; if ( is_int( $uid ) && $uid > 0 ) { wp_delete_user( $uid ); }
	foreach ( $actors as $actor ) {
		if ( ! $actor instanceof Axismundi_Actor ) { continue; } $id = $actor->get_identity_id();
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_signing_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_meta_table(), array( 'actor_id' => $id ) ); $wpdb->delete( axismundi_actors_actors_table(), array( 'id' => $id ) );
	}
	wp_set_current_user( $previous );
}
printf( "== %d checks, 0 failed ==\n", $checks );
