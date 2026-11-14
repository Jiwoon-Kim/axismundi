<?php
/** Dev-only; run with wp eval-file. REST dispatch and a second MySQL connection. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
};
$users = array();
$before = get_current_user_id();
$uri = '';
try {
	$actors = array();
	for ( $i = 0; $i < 2; ++$i ) {
		$login = 'ax_edit_' . strtolower( wp_generate_password( 10, false ) );
		$user = wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
		$assert( is_int( $user ), 'Fixture user exists' );
		$users[] = $user;
		$actor = axismundi_actors_ensure_for_user( $user );
		axismundi_actors_register_handle( $actor->get_identity_id(), $login );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
		$actors[] = axismundi_actors_get_by_identity( $actor->get_identity_id() );
	}
	[ $actor, $other ] = $actors;
	wp_set_current_user( $users[0] );
	$uuid = wp_generate_uuid4();
	$uri = axismundi_op_local_object_uri( $uuid );
	$extension = array( 'nested' => array( 'keep', 'me' ) );
	$initial = axismundi_op_write_local_object( array( 'id' => $uri, 'type' => 'Video',
		'attributedTo' => $actor->get_uri(), 'name' => 'Before', 'summary' => 'Remove me',
		'url' => home_url( '/fixture.webm' ), 'fixtureExtension' => $extension ), $uuid );
	$assert( is_array( $initial ) && empty( $initial['publicly_readable'] ), 'The unaddressed fixture is stored without assuming public access' );
	$base = '/axismundi/activitystreams/local-objects/';
	$dispatch = static function ( string $method, string $path, ?string $acting, ?array $body = null ): WP_REST_Response {
		$request = new WP_REST_Request( $method, $path );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		if ( null !== $acting ) { $request->set_query_params( array( 'actor' => $acting ) ); }
		if ( null !== $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		return rest_do_request( $request );
	};
	$read = static fn( ?string $acting ) => $dispatch( 'GET', $base . $uuid, $acting );
	$patch = static fn( int $version, array $object ) => $dispatch( 'PATCH', $base . $uuid, $actor->get_uuid(), array( 'version' => $version, 'object' => $object ) );
	$submit = static function () use ( $actor, $users, $uri ) {
		return axismundi_act_submit_outbox( $users[0], $actor, array( 'type' => 'Create', 'actor' => $actor->get_uri(), 'object' => $uri ) );
	};
	$assert( 400 === $read( null )->get_status(), 'An authenticated author still must specify the acting Actor' );
	wp_set_current_user( $users[1] );
	$assert( 403 === $read( $other->get_uuid() )->get_status(), 'Another Actor cannot read the original document' );
	$assert( 403 === $read( $actor->get_uuid() )->get_status(), 'Explicit Actor selection does not grant authority' );
	wp_set_current_user( $users[0] );
	$got = $read( $actor->get_uuid() );
	$version = (int) $initial['version'];
	$assert( 200 === $got->get_status() && $version === $got->get_data()['version']
		&& ! isset( $got->get_data()['object']['version'] ), 'GET separates the editing envelope from the AS2 document' );
	$types = $dispatch( 'GET', $base . 'types', null )->get_data()['types'];
	$assert( axismundi_op_local_update_types() === array_keys( $types ) && 'editable' === $types['Video']['properties']['to']['class']
		&& axismundi_op_vocabulary_property( 'duration' )['value_shape'] === $types['Video']['properties']['duration']['value_shape'], 'Discovery combines edit policy and vocabulary metadata' );
	$refusal = $patch( $version, array( 'url' => 'https://example.invalid/elsewhere' ) );
	$outbox = axismundi_act_submit_outbox( $users[0], $actor, array( 'type' => 'Update', 'actor' => $actor->get_uri(),
		'object' => array( 'id' => $uri, 'name' => 'X', 'url' => 'https://example.invalid/elsewhere' ) ) );
	$assert( 422 === $refusal->get_status() && 'server_set' === $refusal->get_data()['data']['properties']['object.url']
		&& 'server_set' === $outbox->get_error_data()['properties']['object.url'], 'Both surfaces classify server-set fields alike' );

	// Attempt publication on an independent connection immediately before the draft UPDATE.
	// Give the fixture a public route so a missing lock would allow that Create to commit.
	$public = array( 'https://www.w3.org/ns/activitystreams#Public' );
	$addressed = $patch( $version, array( 'to' => $public ) );
	$assert( 200 === $addressed->get_status(), 'An unaddressed Video can be made retrievable without publication' );
	++$version;
	$parallel = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$parallel->set_prefix( $wpdb->base_prefix );
	$parallel->set_blog_id( get_current_blog_id() );
	$competing = null;
	$probe = static function ( string $sql ) use ( &$competing, $parallel, $submit ): string {
		global $wpdb;
		if ( null !== $competing || ! str_starts_with( $sql, 'UPDATE `' . axismundi_op_local_objects_table() . '` SET' ) ) { return $sql; }
		$primary = $wpdb;
		$competing = false;
		$wpdb = $parallel;
		$short_wait = static fn( string $query ): string => str_contains( $query, 'SELECT GET_LOCK(' ) ? str_replace( ', 10)', ', 0)', $query ) : $query;
		add_filter( 'query', $short_wait );
		try { $competing = $submit(); }
		finally { remove_filter( 'query', $short_wait ); $wpdb = $primary; }
		return $sql;
	};
	add_filter( 'query', $probe );
	try { $saved = $patch( $version, array( 'name' => 'After', 'summary' => null, 'cc' => $public ) ); }
	finally { remove_filter( 'query', $probe ); }
	$assert( is_wp_error( $competing ) && 'ax_c2s_reference_busy' === $competing->get_error_code(), 'A competing Create reaches the shared lock and cannot enter during PATCH' );
	$document = $saved->get_data()['object'] ?? array();
	$assert( 200 === $saved->get_status() && 'After' === ( $document['name'] ?? '' ) && ! isset( $document['summary'] )
		&& $uri === $document['id'] && $initial['document']['url'] === $document['url'], 'PATCH updates only requested fields and supports null removal' );
	$assert( $extension === ( axismundi_op_get_local_object( $uri )['document']['fixtureExtension'] ?? null ), 'A stored unknown extension survives the patch' );
	$assert( 409 === $patch( $version, array( 'name' => 'Stale' ) )->get_status(), 'A stale version cannot overwrite a completed edit' );
	$assert( array() === axismundi_act_first_outbound_create_row( $uri )
		&& 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE object_uri=%s', axismundi_act_activities_table(), $uri ) )
		&& 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE payload_json LIKE %s', axismundi_activitypub_bridge_delivery_table(), '%' . $wpdb->esc_like( $uuid ) . '%' ) ),
		'Draft editing creates no ledger rows or delivery jobs' );
	$published = $submit();
	$assert( $published instanceof Axismundi_Activity && $uri === $published->get_payload()['object']
		&& $public === $published->get_payload()['cc'], 'Create after PATCH uses the saved addressing and the same URI' );
	$published_version = $saved->get_data()['version'];
	$denied = $patch( $published_version, array( 'name' => 'Too late' ) );
	$assert( 409 === $denied->get_status() && 'ax_op_edit_published' === $denied->get_data()['code']
		&& $published_version === (int) axismundi_op_get_local_object( $uri )['version'], 'Create before PATCH prevents a silent edit even with the current version' );
} finally {
	if ( '' !== $uri ) {
		$row = axismundi_op_get_local_object( $uri );
		if ( $row ) { axismundi_op_tombstone_local_object( $row, $row['attributed_to_uri'] ); }
		$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri' => $uri ) );
	}
	wp_set_current_user( $before );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $user ) { wp_delete_user( $user ); }
}
echo "\n== {$checks} checks, 0 failed ==\n";
