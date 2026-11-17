<?php
/** Isolated Actor-explicit application reads. No remote requests or Activity submission. */
defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';
global $wpdb;
$checks = 0;
$users = $ids = array();
$initial_user = get_current_user_id();
$cookies = $_COOKIE;
$transaction = false;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
};
$network = 0;
$block = static function () use ( &$network ) { ++$network; return new WP_Error( 'audit_network', 'No network during local read audit' ); };
add_filter( 'pre_http_request', $block, PHP_INT_MAX );
$request = static function ( string $route, ?string $uri = null, array $query = array() ) : WP_REST_Response {
	$request = new WP_REST_Request( 'GET', $route );
	if ( null !== $uri ) { $query['actor'] = $uri; }
	$request->set_query_params( $query );
	$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	return rest_do_request( $request );
};
try {
	foreach ( array( 'owner', 'outsider' ) as $role ) {
		$user = wp_insert_user( array( 'user_login' => 'axread' . $role . strtolower( wp_generate_password( 10, false, false ) ), 'user_pass' => wp_generate_password() ) );
		$assert( is_int( $user ), 'Fixture User created' );
		$users[] = $user;
	}
	wp_set_current_user( $users[0] );
	unset( $_COOKIE[ LOGGED_IN_COOKIE ], $_COOKIE[ axismundi_actors_selection_cookie_name() ] );
	$actors = array();
	foreach ( array( 'author', 'follower', 'unrelated', 'draft' ) as $kind ) {
		$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'initial_owner_user_id' => $users[0], 'display_name' => $kind ) );
		$assert( $actor instanceof Axismundi_Actor, 'Fixture Actor created: ' . $kind );
		$id = $actor->get_identity_id();
		$ids[] = $id;
		if ( 'draft' !== $kind ) {
			axismundi_actors_register_handle( $id, 'axread' . strtolower( wp_generate_password( 12, false, false ) ) );
		}
		$actors[] = axismundi_actors_get_by_identity( $id );
	}
	[ $a, $b, $c, $draft ] = $actors;
	$assert( ! axismundi_op_actor_visible( $draft ), 'Draft profile is not publicly visible' );
	$self_route = '/axismundi/activitypub/actors';
	$self_query = array( 'ids' => $draft->get_uri() );
	$self = $request( $self_route, null, $self_query );
	$assert( 200 === $self->get_status() && 'OrderedCollection' === $self->get_data()['type']
		&& ! isset( $self->get_data()['id'] ) && $draft->get_uri() === $self->get_data()['orderedItems'][0]['id']
		&& str_contains( $self->get_headers()['Cache-Control'], 'no-store' ), 'Owner can read own nonpublic Actor document without selecting it in a browser: ' . $self->get_status() . ' ' . ( $self->get_data()['code'] ?? '' ) );
	$assert( 400 === $request( $self_route, null, array( 'id' => $draft->get_uri() ) )->get_status(), 'Actor collection requires ids, not id' );
	$actors_result = $request( $self_route, null, array( 'ids' => implode( ',', array( $draft->get_uri(), 'https://example.test/missing', $a->get_uri(), $draft->get_uri() ) ) ) );
	$assert( 200 === $actors_result->get_status() && array( $draft->get_uri(), $a->get_uri() ) === array_column( $actors_result->get_data()['orderedItems'], 'id' ), 'Actor collection preserves requested order, omits missing IDs and removes duplicates' );
	$assert( 400 === $request( $self_route, null, array( 'ids' => implode( ',', array_fill( 0, 21, $a->get_uri() ) ) ) )->get_status(), 'Over-limit batches are rejected before deduplication' );
	$assert( 400 === $request( $self_route, null, array( 'ids' => $a->get_uri() . ',' ) )->get_status(), 'A malformed member rejects the batch' );
	$assert( ! in_array( $draft->get_uri(), array_column( axismundi_act_c2s_session()['actors'], 'id' ), true ),
		'Self-readable draft is not advertised as a submission Actor' );

	$wpdb->query( 'START TRANSACTION' );
	$transaction = true;
	$followers = axismundi_op_actor_followers_url( $a );
	$endpoints = axismundi_actors_get_endpoints( $a );
	$endpoints['followers'] = $followers;
	$assert( axismundi_actors_write_endpoints( $a->get_identity_id(), $endpoints ), 'Followers endpoint installed' );
	axismundi_act_write_relation( 'follow', $b->get_uri(), $a->get_uri(), 'outbound', 'accepted', null, null );
	$uuid = wp_generate_uuid4();
	$uri = axismundi_op_local_object_uri( $uuid );
	$doc = array( 'id' => $uri, 'url' => $uri, 'type' => 'Note', 'attributedTo' => $a->get_uri(), 'to' => array( $followers ), 'bcc' => array( 'https://hidden.example/actor' ), 'content' => '<p>Restricted body</p>' );
	$assert( ! is_wp_error( axismundi_op_write_local_object( $doc, $uuid ) ), 'Followers-only Object stored' );
	$object = axismundi_op_get_object_by_uri( $uri );
	$route = '/axismundi/v1/objects/' . $object['id'];
	$uri_route = '/axismundi/activitypub/objects';
	$snapshot = Axismundi_Activity::from_row( array(
		'direction' => 'outbound', 'effective_status' => 'active', 'activity_type' => 'Create', 'audience' => array( 'to' => $doc['to'] ),
		'payload' => array( 'id' => home_url( '/activities/' . wp_generate_uuid4() ), 'type' => 'Create', 'actor' => $a->get_uri(), 'to' => $doc['to'], 'object' => $uri ),
	) );
	foreach ( array( array( $b, true ), array( $a, true ), array( $c, false ) ) as [ $viewer, $allowed ] ) {
		$assert( axismundi_actors_can_act_as( $viewer, $users[0] ), 'Caller can use each viewer Actor' );
		$response = $request( $route, $viewer->get_uri() );
		[ $reader ] = axismundi_op_actor_collection_reader( $a, $viewer );
		$assert( $allowed === is_array( $reader( $snapshot ) )
			&& ( $allowed ? 200 === $response->get_status() && $uri === $response->get_data()['id'] : 404 === $response->get_status() ),
			'Matching Activity and Object audience for ' . $viewer->get_display_name() );
		$by_uri = $request( $uri_route, $viewer->get_uri(), array( 'ids' => $snapshot->get_payload()['object'] ) );
		$assert( 200 === $by_uri->get_status() && 'OrderedCollection' === $by_uri->get_data()['type']
			&& ( $allowed ? array( $response->get_data() ) : array() ) === $by_uri->get_data()['orderedItems'],
			'The Object collection preserves the numeric route disclosure policy' );
	}
	$assert( 400 === $request( $uri_route, $a->get_uri(), array( 'object' => $uri ) )->get_status(), 'Object lookup requires ids, not object' );
	$unknown = $request( $uri_route, $a->get_uri(), array( 'ids' => 'https://example.test/unknown' ) );
	$assert( 200 === $unknown->get_status() && array() === $unknown->get_data()['orderedItems'], 'Unknown Object URI is not fetched or separately reported' );
	$assert( ! array_key_exists( 'bcc', $request( $route, $b->get_uri() )->get_data() ), 'Follower response does not disclose blind recipients' );
	$assert( $doc['bcc'] === ( $request( $route, $a->get_uri() )->get_data()['bcc'] ?? null ), 'Explicit author sees the stored top-level blind recipients' );
	$coauthored = $doc;
	$coauthored['attributedTo'] = array( $a->get_uri(), array( 'id' => $b->get_uri() ) );
	$json = wp_json_encode( $coauthored );
	$assert( 1 === $wpdb->update( axismundi_op_local_objects_table(), array( 'document_json' => $json, 'document_hash' => hash( 'sha256', $json ) ), array( 'object_uri' => $uri ) ), 'Read-only fixture has multiple authors but unchanged first-author index' );
	$assert( $doc['bcc'] === ( $request( $route, $b->get_uri() )->get_data()['bcc'] ?? null ), 'Second credited Actor sees blind recipients after passing existing audience checks' );
	$activity = $snapshot->get_payload();
	$activity['actor'] = array( $a->get_uri(), array( 'id' => $b->get_uri() ) );
	$activity['bcc'] = $doc['bcc'];
	$activity['object'] = $doc;
	$activity['object']['attributedTo'] = $c->get_uri();
	$multi_actor = Axismundi_Activity::from_row( array(
		'direction' => 'outbound', 'effective_status' => 'active', 'activity_type' => 'Create', 'audience' => array( 'to' => $doc['to'] ),
		'payload' => $activity,
	) );
	[ $reader ] = axismundi_op_actor_collection_reader( $a, $b );
	$visible = $reader( $multi_actor );
	$assert( $doc['bcc'] === ( $visible['bcc'] ?? null ) && ! isset( $visible['object']['bcc'] ), 'Second Activity actor sees only that Activity top-level blind recipients' );
	$activity['actor'] = $a->get_uri();
	$activity['object']['attributedTo'] = $b->get_uri();
	$single_actor = Axismundi_Activity::from_row( array(
		'direction' => 'outbound', 'effective_status' => 'active', 'activity_type' => 'Create', 'audience' => array( 'to' => $doc['to'] ),
		'payload' => $activity,
	) );
	$visible = $reader( $single_actor );
	$assert( is_array( $visible ) && ! isset( $visible['bcc'] ) && ! isset( $visible['object']['bcc'] ), 'Embedded Object authorship does not disclose blind addresses on an Activity' );
	$public_uuid = wp_generate_uuid4();
	$public_uri = axismundi_op_local_object_uri( $public_uuid );
	$public_doc = array_replace( $doc, array( 'id' => $public_uri, 'url' => $public_uri, 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) ) );
	$assert( ! is_wp_error( axismundi_op_write_local_object( $public_doc, $public_uuid ) ), 'Public Object with blind recipients stored' );
	$public_id = (int) axismundi_op_get_object_by_uri( $public_uri )['id'];
	$batch = $request( $uri_route, $c->get_uri(), array( 'ids' => $uri . ',https://example.test/unknown,' . $public_uri ) );
	$assert( 200 === $batch->get_status() && array( $public_uri ) === array_column( $batch->get_data()['orderedItems'], 'id' ), 'Mixed Object batch omits inaccessible and unknown members alike' );
	$assert( $doc['bcc'] === ( $request( '/axismundi/v1/objects/' . $public_id, $a->get_uri() )->get_data()['bcc'] ?? null )
		&& ! isset( axismundi_op_read_object_jsonld( $public_id )['bcc'] ), 'Public body has blind recipients only in explicit author response' );
	$assert( is_wp_error( axismundi_op_read_object_jsonld( (int) $object['id'] ) ), 'Public read still conceals restricted Object' );
	wp_set_current_user( $users[1] );
	$assert( 403 === $request( $route, $a->get_uri() )->get_status(), 'Another login cannot impersonate author' );
	$hidden = $request( $self_route, null, $self_query );
	$assert( 200 === $hidden->get_status() && array() === $hidden->get_data()['orderedItems'], 'Another login cannot distinguish a draft Actor from a missing Actor' );
	$assert( 403 === $request( $uri_route, $a->get_uri(), array( 'ids' => $uri ) )->get_status(), 'URI lookup cannot impersonate the author' );
	wp_set_current_user( 0 );
	$assert( 401 === $request( $route, $a->get_uri() )->get_status(), 'Anonymous explicit Object request denied' );
	$assert( 401 === $request( $self_route, null, $self_query )->get_status(), 'Anonymous Actor document request denied' );
	$assert( 0 === $network, 'Local reads do not fetch remote resources' );
	printf( "PASS: %d explicit local read checks\n", $checks );
} finally {
	if ( $transaction ) { $wpdb->query( 'ROLLBACK' ); }
	remove_filter( 'pre_http_request', $block, PHP_INT_MAX );
	$_COOKIE = $cookies;
	wp_set_current_user( $initial_user );
	foreach ( $ids as $id ) {
		$wpdb->delete( axismundi_actors_meta_table(), array( 'actor_id' => $id ) );
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_endpoints_table(), axismundi_actors_addresses_table(), axismundi_actors_texts_table(), axismundi_actors_signing_keys_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $id ) );
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	foreach ( $users as $user ) { wp_delete_user( $user ); }
}
