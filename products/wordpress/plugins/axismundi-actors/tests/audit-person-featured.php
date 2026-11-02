<?php
/** Person featured integration fixture. No real remote HTTP or existing Actor edits. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$featured_read = $featured_read ?? 'axismundi_actors_read_featured';
$featured_write = $featured_write ?? 'axismundi_actors_write_featured';
$featured_receive = $featured_receive ?? 'axismundi_activitypub_bridge_receive_featured';
$featured_sync = $featured_sync ?? 'axismundi_actors_sync_featured';
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; echo '[PASS] ' . $label . "\n"; };
$users = array(); $actors = array(); $objects = array(); $recorded = array();
$previous_user = get_current_user_id(); $key_lookup = null;
$before_principals = $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A );
$before_ledger = $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_activities_table() . ' ORDER BY id', ARRAY_A );
$prefix = 'https://example.com/ax-featured-' . wp_generate_uuid4();
$documents = array();
$http = static function ( $pre, array $args, string $url ) use ( &$documents ) {
	if ( ! array_key_exists( $url, $documents ) ) { return new WP_Error( 'ax_featured_fixture_http', 'No network outside fixture: ' . $url ); }
	$value = $documents[ $url ];
	if ( is_wp_error( $value ) ) { return $value; }
	return array( 'headers' => array( 'content-type' => 'application/activity+json' ), 'body' => wp_json_encode( $value ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
};
$observe = static function ( Axismundi_Activity $activity ) use ( &$recorded ) { $recorded[] = $activity->get_uri(); };
add_filter( 'pre_http_request', $http, 999, 3 );
add_action( 'axismundi_act_activity_recorded', $observe, 999 );
remove_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', 10 );
try {
	foreach ( array( 'owner', 'other' ) as $label ) {
		$user = wp_insert_user( array( 'user_login' => 'axft_' . $label . '_' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password( 32 ), 'role' => 'subscriber' ) );
		$assert( is_int( $user ), 'Fixture user allocated' ); $users[] = $user;
		$actor = axismundi_actors_ensure_for_user( $user ); $actors[] = $actor;
		$assert( $actor instanceof Axismundi_Actor, 'Fixture Person allocated' );
		axismundi_actors_register_handle( $actor->get_identity_id(), 'axft' . strtolower( wp_generate_password( 10, false, false ) ) );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	}
	$actor = axismundi_actors_get_by_identity( $actors[0]->get_identity_id() );
	$other = axismundi_actors_get_by_identity( $actors[1]->get_identity_id() );
	wp_set_current_user( $users[0] );
	$public = array( 'https://www.w3.org/ns/activitystreams#Public' );
	for ( $i = 0; $i < 3; ++$i ) {
		$a = axismundi_act_submit_outbox( $users[0], $actor, array( 'type' => 'Note', 'content' => '<p>Featured fixture</p>', 'to' => $public ) );
		$assert( $a instanceof Axismundi_Activity, 'Public Note created' ); $objects[] = $a->get_object_uri();
	}
	$target = axismundi_actors_featured_uri( $actor );
	$command = static fn( string $type, string $object ) : array => array( 'type' => $type, 'actor' => $actor->get_uri(), 'object' => $object, 'target' => $target );
	$add = axismundi_act_submit_outbox( $users[0], $actor, $command( 'Add', $objects[0] ), 'featured-a' );
	$assert( $add instanceof Axismundi_Activity && '' !== $add->get_uri() && $target === $add->get_target_uri(), 'Add has a real outbound Activity identity and exact collection target' );
	$assert( is_wp_error( axismundi_act_submit_outbox( $users[1], $actor, $command( 'Add', $objects[1] ) ) ), 'Unauthorized principal rejected' );
	$wpdb->query( 'START TRANSACTION' );
	try { $denied_write = $featured_write( $actor, $objects[1], true, $users[1] ); }
	finally { $wpdb->query( 'ROLLBACK' ); }
	$assert( is_wp_error( $denied_write ), 'Membership writer rechecks curation authority' );
	$assert( is_wp_error( axismundi_act_submit_outbox( $users[0], $actor, array_replace( $command( 'Add', $objects[0] ), array( 'target' => $target . '/other' ) ) ) ), 'Foreign target rejected' );
	$bad = axismundi_act_submit_outbox( $users[1], $other, array( 'type' => 'Add', 'actor' => $other->get_uri(), 'object' => $objects[0], 'target' => axismundi_actors_featured_uri( $other ) ) );
	$assert( is_wp_error( $bad ), 'Authorized different Person cannot pin someone else\'s Object' );
	$second = axismundi_act_submit_outbox( $users[0], $actor, $command( 'Add', $objects[1] ) );
	$assert( $second instanceof Axismundi_Activity && array( $objects[1], $objects[0] ) === axismundi_actors_read_featured( $actor )['items'], 'New pin precedes old pin independently of publication order' );
	$duplicate = axismundi_act_submit_outbox( $users[0], $actor, $command( 'Add', $objects[0] ) );
	$assert( $duplicate instanceof Axismundi_Activity && array( $objects[1], $objects[0] ) === axismundi_actors_read_featured( $actor )['items'], 'Duplicate Add neither duplicates nor reorders membership' );
	$removed = axismundi_act_submit_outbox( $users[0], $actor, $command( 'Remove', $objects[0] ) );
	$assert( $removed instanceof Axismundi_Activity && array( $objects[1] ) === axismundi_actors_read_featured( $actor )['items'], 'Remove drops membership, not the Object' );
	$retry = axismundi_act_submit_outbox( $users[0], $actor, $command( 'Add', $objects[0] ), 'featured-a' );
	$assert( $retry instanceof Axismundi_Activity && $retry->get_uri() === $add->get_uri() && array( $objects[1] ) === axismundi_actors_read_featured( $actor )['items'], 'Old retry returns evidence without reviving removed pin' );
	$assert( is_wp_error( axismundi_act_submit_outbox( $users[0], $actor, $command( 'Add', $objects[2] ), 'featured-a' ) ), 'Retry intent conflict rejected' );
	$create_key = 'featured-create-key';
	$created_with_key = axismundi_act_submit_outbox( $users[0], $actor, array( 'type' => 'Note', 'content' => '<p>Cross-method retry fixture</p>', 'to' => $public ), $create_key );
	$assert( $created_with_key instanceof Axismundi_Activity, 'Create retry fixture allocated' ); $objects[] = $created_with_key->get_object_uri();
	$assert( is_wp_error( axismundi_act_submit_outbox( $users[0], $actor, $command( 'Add', $created_with_key->get_object_uri() ), $create_key ) ), 'A Create retry key cannot be reused for featured Add' );
	$path = '/axismundi/v1/actors/' . $actor->get_uuid() . '/featured';
	$request = new WP_REST_Request( 'GET', $path );
	$response = rest_do_request( $request ); $data = $response->get_data();
	$assert( 200 === $response->get_status() && 'OrderedCollection' === ( $data['type'] ?? '' ) && array( $objects[1] ) === ( $data['orderedItems'] ?? null ), 'Public REST collection has exact ordered membership: ' . wp_json_encode( $data ) );
	$assert( $target === axismundi_op_actor_transform( $actor )['featured'] && 1 === $data['totalItems'], 'Actor discovery and disclosed exact total agree' );
	$actor_document = axismundi_op_transform_object( $actor );
	$declaration = null;
	foreach ( (array) $actor_document['@context'] as $context ) { if ( is_array( $context ) && isset( $context['featured'] ) ) { $declaration = $context['featured']; } }
	$assert( is_array( $declaration ) && 'http://joinmastodon.org/ns#featured' === $declaration['@id'] && '@id' === $declaration['@type'], 'Actor JSON-LD declares the canonical Mastodon featured IRI as a reference' );
	$contract = axismundi_op_collection_contract( 'ap-featured' );
	$assert( 'membership-row' === $contract['membership_authority'] && 'owner-only' === $contract['append_policy'] && array( 'Object' ) === $contract['member_models'], 'OP exposes the domain membership contract' );
	$deleted = axismundi_act_submit_outbox( $users[0], $actor, array( 'type' => 'Delete', 'actor' => $actor->get_uri(), 'object' => $objects[1] ) );
	$assert( $deleted instanceof Axismundi_Activity && array() === axismundi_actors_read_featured( $actor )['items'] && axismundi_act_get( $second->get_uri() ) instanceof Axismundi_Activity, 'Deleted pin is not served; original Add evidence survives' );
	$assert( is_wp_error( axismundi_act_submit_outbox( $users[0], $actor, $command( 'Add', $objects[1] ) ) ), 'Deleted Object cannot be newly pinned' );
	$private_note = axismundi_act_submit_outbox( $users[0], $actor, array( 'type' => 'Note', 'content' => '<p>Private</p>', 'to' => array( $actor->get_uri() ) ) );
	$assert( $private_note instanceof Axismundi_Activity, 'Private Note fixture created' ); $objects[] = $private_note->get_object_uri();
	$assert( is_wp_error( axismundi_act_submit_outbox( $users[0], $actor, $command( 'Add', $private_note->get_object_uri() ) ) ), 'Private Object URI is not exposed through featured' );
	$events_before = count( $recorded ); $fault_hit = false;
	$fault = static function ( string $sql ) use ( &$fault_hit ) : string {
		if ( preg_match( '/^INSERT INTO `?[^ ]*ax_actor_featured`?\s/i', $sql ) ) { $fault_hit = true; return 'SELECT * FROM ax_featured_deliberate_failure'; } return $sql;
	};
	$errors = $wpdb->suppress_errors(); add_filter( 'query', $fault );
	try { $failed_add = axismundi_act_submit_outbox( $users[0], $actor, $command( 'Add', $objects[2] ) ); }
	finally { remove_filter( 'query', $fault ); $wpdb->suppress_errors( $errors ); }
	$assert( $fault_hit && is_wp_error( $failed_add ) && $events_before === count( $recorded ) && array() === axismundi_actors_read_featured( $actor )['items'], 'Membership failure rolls back Activity and emits no committed event' );
	// One remote Person owns the authoritative collection. All HTTP remains mocked.
	$remote_uri = $prefix . '/person'; $collection = $remote_uri . '/featured';
	$remote = axismundi_actors_upsert_remote( array( 'uri' => $remote_uri, 'actor_type' => 'Person', 'display_name' => 'Featured peer', 'endpoints' => array( 'inbox' => $remote_uri . '/inbox', 'outbox' => $remote_uri . '/outbox', 'featured' => $collection ), 'payload' => array( 'id' => $remote_uri, 'type' => 'Person', 'featured' => $collection ) ) );
	$assert( $remote instanceof Axismundi_Actor, 'Remote Person fixture cached' ); $actors[] = $remote;
	$r1 = $prefix . '/one'; $r2 = $prefix . '/two'; $objects[] = $r1; $objects[] = $r2;
	foreach ( array( $r1, $r2 ) as $uri ) { $documents[ $uri ] = array( '@context' => 'https://www.w3.org/ns/activitystreams', 'id' => $uri, 'type' => 'Note', 'attributedTo' => $remote_uri, 'content' => '<p>Remote fixture</p>', 'to' => $public ); }
	$documents[ $collection ] = array( 'id' => $collection, 'type' => 'OrderedCollection', 'totalItems' => 2, 'orderedItems' => array( $r2, $r1 ) );
	$assert( true === axismundi_actors_request_featured_sync( $remote ) && true === $featured_sync( $remote->get_identity_id() ), 'Complete remote snapshot commits' );
	$assert( array( $r2, $r1 ) === $featured_read( $remote )['items'], 'Remote orderedItems order is retained' );
	$follow = axismundi_act_record_activity( array( 'id' => $prefix . '/follow', 'type' => 'Follow', 'actor' => $remote_uri, 'object' => $actor->get_uri(), 'to' => array( $actor->get_uri() ) ), 'inbound' );
	$assert( $follow instanceof Axismundi_Activity, 'Remote follower fixture recorded' );
	$accept = axismundi_act_record_activity( array( 'type' => 'Accept', 'actor' => $actor->get_uri(), 'object' => $follow->get_uri() ), 'outbound' );
	$assert( $accept instanceof Axismundi_Activity, 'Follower accepted for delivery fixture' );
	$wire_add = axismundi_act_submit_outbox( $users[0], $actor, $command( 'Add', $objects[2] ) );
	$assert( $wire_add instanceof Axismundi_Activity, 'Local featured Add prepared for delivery' );
	axismundi_activitypub_bridge_queue_outbound( $wire_add );
	$job = axismundi_activitypub_bridge_get_delivery( axismundi_activitypub_bridge_find_delivery( $wire_add->get_uri() ) );
	$wire = $job ? json_decode( $job->payload_json, true ) : null;
	$assert( $job && $wire_add->get_uri() === $wire['id'] && 'Add' === $wire['type'] && $objects[2] === $wire['object'] && $target === $wire['target'] && ! isset( $wire['origin'] ) && array( $remote_uri . '/inbox' ) === json_decode( $job->inboxes_json, true ), 'Existing transport queues stable-id Add with Object and target for accepted followers' );
	$signal = array( 'type' => 'Remove', 'actor' => $remote_uri, 'object' => $r1, 'target' => $collection, 'to' => array( $actor->get_uri() ) );
	$ledger_count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_act_activities_table() );
	$receipt = axismundi_activitypub_bridge_record_inbox( $signal, array(), false, $actor );
	$assert( is_array( $receipt ) && isset( $receipt['featured_receipt'] ), 'Verified-ingress path records an idless featured receipt' );
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', axismundi_activitypub_bridge_featured_receipts_table(), $receipt['featured_receipt'] ), ARRAY_A );
	$assert( null === $row['remote_activity_uri'] && ! isset( json_decode( $row['payload_json'], true )['id'] ) && $ledger_count === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_act_activities_table() ), 'Receipt does not fabricate a remote Activity identity' );
	$documents[ $collection ]['orderedItems'] = array( $r2 ); $documents[ $collection ]['totalItems'] = 1;
	$assert( true === $featured_sync( $remote->get_identity_id() ) && array( $r2 ) === axismundi_actors_read_featured( $remote )['items'], 'Reconciliation removes absent membership' );
	$signal['type'] = 'Add';
	$late = $featured_receive( $signal, $remote );
	$assert( is_array( $late ) && array( $r2 ) === axismundi_actors_read_featured( $remote )['items'], 'Late Add cannot revive a removed remote member' );
	$assert( true === $featured_sync( $remote->get_identity_id() ) && array( $r2 ) === axismundi_actors_read_featured( $remote )['items'], 'Authoritative re-fetch confirms absence despite late Add' );
	// Real RSA verification, including the special idless shared-Inbox dispatch path.
	$key = openssl_pkey_new( array( 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
	openssl_pkey_export( $key, $private ); $pem = openssl_pkey_get_details( $key )['key'];
	$key_id = $remote_uri . '#featured-key';
	$key_lookup = static fn( $value, $url ) => $url === $key_id ? array( 'id' => $remote_uri, 'type' => 'Person', 'publicKey' => array( 'id' => $key_id, 'owner' => $remote_uri, 'publicKeyPem' => $pem ) ) : $value;
	add_filter( 'activitypub_pre_http_get_remote_object', $key_lookup, 10, 2 );
	foreach ( array( '/axismundi/v1/actors/' . $actor->get_uuid() . '/inbox', '/activitypub/1.0/inbox' ) as $route ) {
		$body = wp_json_encode( $signal );
		$post = new WP_REST_Request( 'POST', $route ); $post->set_header( 'Content-Type', 'application/activity+json' ); $post->set_body( $body );
		$assert( 202 !== rest_do_request( $post )->get_status(), 'Unsigned idless control is rejected' );
		$signed = ( new Activitypub\Signature\Http_Signature_Draft() )->sign( array( 'method' => 'POST', 'body' => $body, 'key_id' => $key_id, 'private_key' => $private, 'headers' => array( 'Date' => gmdate( 'D, d M Y H:i:s' ) . ' GMT' ) ), rest_url( ltrim( $route, '/' ) ) );
		$post->set_header( 'Host', wp_parse_url( home_url(), PHP_URL_HOST ) );
		foreach ( $signed['headers'] as $name => $value ) { $post->set_header( $name, $value ); }
		$post->set_body( $body . ' ' );
		$assert( 202 !== rest_do_request( $post )->get_status(), 'Tampered idless control fails the real verifier' );
		$post->set_body( $body ); $received = rest_do_request( $post );
		$assert( 202 === $received->get_status(), 'RSA-signed idless control reaches featured receipt storage: ' . $route . ' ' . wp_json_encode( $received->get_data() ) );
	}
	remove_filter( 'activitypub_pre_http_get_remote_object', $key_lookup, 10 ); $key_lookup = null;
	$signal['target'] = $prefix . '/foreign';
	$assert( is_wp_error( axismundi_activitypub_bridge_record_inbox( $signal, array(), false, $actor ) ), 'Remote sender cannot claim an unadvertised target collection' );
	$documents[ $collection ] = new WP_Error( 'ax_featured_fixture_timeout', 'Temporary failure' );
	$assert( is_wp_error( $featured_sync( $remote->get_identity_id() ) ) && array( $r2 ) === axismundi_actors_read_featured( $remote )['items'], 'Fetch failure preserves the previous snapshot' );
	$documents[ $collection ] = array( 'id' => $collection, 'type' => 'OrderedCollection', 'totalItems' => 2, 'orderedItems' => array( $r1 ) );
	$assert( is_wp_error( $featured_sync( $remote->get_identity_id() ) ) && array( $r2 ) === axismundi_actors_read_featured( $remote )['items'], 'Incomplete count cannot erase a complete snapshot' );
	$documents[ $collection ] = array( 'id' => $collection, 'type' => 'OrderedCollection', 'first' => $collection . '?page=1', 'totalItems' => 2 );
	$documents[ $collection . '?page=1' ] = array( 'id' => $collection . '?page=1', 'partOf' => $collection, 'type' => 'OrderedCollectionPage', 'orderedItems' => array( $r1 ), 'next' => $collection . '?page=2' );
	$documents[ $collection . '?page=2' ] = array( 'id' => $collection . '?page=2', 'partOf' => $collection, 'type' => 'OrderedCollectionPage', 'orderedItems' => array( $r2 ) );
	$assert( true === $featured_sync( $remote->get_identity_id() ) && array( $r1, $r2 ) === axismundi_actors_read_featured( $remote )['items'], 'Complete paged snapshot retains page order' );
	$documents[ $collection . '?page=2' ] = new WP_Error( 'ax_featured_fixture_timeout', 'Second page failure' );
	$assert( is_wp_error( $featured_sync( $remote->get_identity_id() ) ) && array( $r1, $r2 ) === axismundi_actors_read_featured( $remote )['items'], 'Partial page failure preserves all previous membership' );
	$r3 = $prefix . '/three'; $objects[] = $r3;
	$documents[ $r3 ] = array_replace( $documents[ $r2 ], array( 'id' => $r3 ) );
	$r1_document = $documents[ $r1 ];
	$documents[ $collection ] = array( 'id' => $collection, 'type' => 'OrderedCollection', 'orderedItems' => array( $r1, $r3, $r2 ), 'totalItems' => 3 );
	$documents[ $r1 ] = new WP_Error( 'ax_featured_fixture_timeout', 'Individual Object timed out' );
	$partial = $featured_sync( $remote->get_identity_id() );
	$assert( true === $partial && array( $r3, $r2 ) === axismundi_actors_read_featured( $remote )['items'], 'Item fetch failure permits complete membership progress' );
	$observed = $wpdb->get_results( $wpdb->prepare( 'SELECT object_uri,resolution,resolution_error FROM %i WHERE actor_id=%d ORDER BY position ASC', axismundi_actors_featured_table(), $remote->get_identity_id() ), ARRAY_A );
	$assert( array( $r1, $r3, $r2 ) === array_column( $observed, 'object_uri' ) && 'unresolved' === $observed[0]['resolution'] && ! empty( $observed[0]['resolution_error'] ), 'Unresolved URI and authoritative order survive without exposing stale cached content' );
	$assert( ! is_wp_error( axismundi_actors_featured_object( $remote, $r1 ) ), 'Cached copy remains publicly readable while its network refresh fails' );
	$assert( array( $r3, $r2 ) === $featured_read( $remote )['items'], 'Unresolved member is hidden despite a readable cached copy' );
	$documents[ $r1 ] = array_replace( $r1_document, array( 'to' => array( $remote_uri ) ) );
	$assert( true === $featured_sync( $remote->get_identity_id() ) && array( $r3, $r2 ) === axismundi_actors_read_featured( $remote )['items'], 'A private member does not freeze other membership updates' );
	$private_state = $wpdb->get_row( $wpdb->prepare( 'SELECT resolution,resolution_error FROM %i WHERE actor_id=%d AND object_uri=%s', axismundi_actors_featured_table(), $remote->get_identity_id(), $r1 ), ARRAY_A );
	$assert( 'excluded' === $private_state['resolution'] && 'ax_featured_not_public' === $private_state['resolution_error'], 'Confirmed non-public is distinct from a transient fetch failure' );
	$foreign = axismundi_actors_upsert_remote( array( 'uri' => $prefix . '/other-person', 'actor_type' => 'Person', 'display_name' => 'Other featured author', 'endpoints' => array( 'inbox' => $prefix . '/other-inbox', 'outbox' => $prefix . '/other-outbox' ), 'payload' => array( 'id' => $prefix . '/other-person', 'type' => 'Person' ) ) );
	$assert( $foreign instanceof Axismundi_Actor, 'Other remote author fixture cached' ); $actors[] = $foreign;
	$r4 = $prefix . '/foreign-object'; $objects[] = $r4;
	$documents[ $r4 ] = array_replace( $r1_document, array( 'id' => $r4, 'attributedTo' => $foreign->get_uri() ) );
	$documents[ $collection ]['orderedItems'] = array( $r4, $r3, $r2 );
	$assert( true === $featured_sync( $remote->get_identity_id() ) && array( $r3, $r2 ) === axismundi_actors_read_featured( $remote )['items'], 'Foreign authorship excludes only that display item, not the remote snapshot' );
	$foreign_state = $wpdb->get_row( $wpdb->prepare( 'SELECT resolution,resolution_error FROM %i WHERE actor_id=%d AND object_uri=%s', axismundi_actors_featured_table(), $remote->get_identity_id(), $r4 ), ARRAY_A );
	$assert( 'excluded' === $foreign_state['resolution'] && 'ax_featured_author' === $foreign_state['resolution_error'], 'Self-authorship remains a featured-specific display rule' );
	$documents[ $r3 ] = array( '@context' => 'https://www.w3.org/ns/activitystreams', 'id' => $r3, 'type' => 'Tombstone', 'formerType' => 'Note', 'attributedTo' => $remote_uri, 'deleted' => gmdate( 'c' ), 'to' => $public );
	$assert( true === $featured_sync( $remote->get_identity_id() ) && array( $r2 ) === axismundi_actors_read_featured( $remote )['items'], 'Confirmed deletion hides only that member while the complete snapshot advances' );
	$deleted_state = $wpdb->get_row( $wpdb->prepare( 'SELECT resolution,resolution_error FROM %i WHERE actor_id=%d AND object_uri=%s', axismundi_actors_featured_table(), $remote->get_identity_id(), $r3 ), ARRAY_A );
	$assert( 'excluded' === $deleted_state['resolution'] && 'ax_featured_deleted' === $deleted_state['resolution_error'], 'Observed deleted membership is retained separately from the public display' );
	$documents[ $r1 ] = $r1_document;
	$documents[ $collection ] = array( 'id' => $collection, 'type' => 'OrderedCollection', 'orderedItems' => array( $r1, $r2 ), 'totalItems' => 2 );
	$assert( true === $featured_sync( $remote->get_identity_id() ) && array( $r1, $r2 ) === axismundi_actors_read_featured( $remote )['items'], 'Recovered Object becomes readable again without a new Add' );
	$documents[ $collection ] = array( 'id' => $collection, 'type' => 'OrderedCollection', 'orderedItems' => array( $r2, $r1 ), 'totalItems' => 2 );
	$invalidate = static function ( $pre, array $args, string $url ) use ( $collection, $remote ) {
		if ( $url === $collection ) { axismundi_actors_request_featured_sync( $remote ); } return $pre;
	};
	add_filter( 'pre_http_request', $invalidate, 998, 3 );
	try { $stale = $featured_sync( $remote->get_identity_id() ); }
	finally { remove_filter( 'pre_http_request', $invalidate, 998 ); }
	$assert( is_wp_error( $stale ) && 'ax_featured_stale' === $stale->get_error_code() && array( $r1, $r2 ) === axismundi_actors_read_featured( $remote )['items'], 'A signal received during fetch fences off the stale snapshot' );
	$documents[ $collection ] = array( 'id' => $collection, 'type' => 'OrderedCollection', 'orderedItems' => array(), 'totalItems' => 0 );
	$assert( true === $featured_sync( $remote->get_identity_id() ) && array() === axismundi_actors_read_featured( $remote )['items'], 'Explicit complete empty snapshot may clear membership' );
} finally {
	if ( $key_lookup ) { remove_filter( 'activitypub_pre_http_get_remote_object', $key_lookup, 10 ); }
	remove_filter( 'pre_http_request', $http, 999 ); remove_action( 'axismundi_act_activity_recorded', $observe, 999 );
	add_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', 10 );
	foreach ( $recorded as $uri ) {
		$a = axismundi_act_get( $uri ); if ( $a ) { $wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $a->get_id() ) ); }
		$job_id = axismundi_activitypub_bridge_find_delivery( $uri );
		if ( $job_id ) { for ( $n = 0; $n <= 20; ++$n ) { wp_clear_scheduled_hook( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $job_id, $n ) ); } $wpdb->delete( axismundi_activitypub_bridge_delivery_table(), array( 'id' => $job_id ) ); }
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $uri ) );
	}
	foreach ( $objects as $uri ) {
		foreach ( array( axismundi_op_local_objects_table() => 'object_uri', axismundi_op_remote_objects_table() => 'object_uri', axismundi_op_objects_table() => 'canonical_uri', axismundi_op_object_relations_table() => 'source_object_uri', axismundi_op_object_mentions_table() => 'source_object_uri', axismundi_op_tag_references_table() => 'source_uri', axismundi_op_term_usage_table() => 'source_uri', axismundi_op_thread_edges_table() => 'child_uri' ) as $table => $column ) { $wpdb->delete( $table, array( $column => $uri ) ); }
		$wpdb->delete( axismundi_op_object_index_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) );
	}
	foreach ( $actors as $a ) {
		if ( ! $a instanceof Axismundi_Actor ) { continue; } $id = $a->get_identity_id();
		$wpdb->delete( axismundi_act_relations_table(), array( 'subject_actor_uri' => $a->get_uri() ) ); $wpdb->delete( axismundi_act_relations_table(), array( 'object_actor_uri' => $a->get_uri() ) );
		wp_clear_scheduled_hook( 'axismundi_actors_sync_featured', array( $id ) );
		foreach ( array( axismundi_actors_featured_table(), axismundi_actors_featured_state_table(), axismundi_activitypub_bridge_featured_receipts_table() ) as $t ) { $wpdb->delete( $t, array( 'actor_id' => $id ) ); }
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) { wp_delete_user( $id ); }
	foreach ( $actors as $a ) {
		if ( ! $a instanceof Axismundi_Actor ) { continue; } $id = $a->get_identity_id();
		foreach ( array( axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_fetch_state_table(), axismundi_actors_managers_table(), axismundi_actors_keys_table(), axismundi_actors_signing_keys_table() ) as $t ) { $wpdb->delete( $t, array( 'identity_id' => $id ) ); }
		$wpdb->delete( axismundi_actors_texts_table(), array( 'identity_id' => $id ) ); $wpdb->delete( axismundi_actors_meta_table(), array( 'actor_id' => $id ) );
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $id ) ); $wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	wp_set_current_user( $previous_user );
}
$assert( $before_principals === $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A ), 'Existing principal rows preserved' );
$assert( $before_ledger === $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_activities_table() . ' ORDER BY id', ARRAY_A ), 'Existing Activity rows preserved' );
printf( "== %d checks, 0 failed ==\n", $checks );
