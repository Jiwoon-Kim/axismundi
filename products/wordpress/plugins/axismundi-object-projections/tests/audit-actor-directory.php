<?php
/** Public directory fixtures, rolled back without creating grants or signers. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$original_user = get_current_user_id();
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
};
$request = static function ( array $query ) : WP_REST_Response {
	$r = new WP_REST_Request( 'GET', '/axismundi/activitypub/actors' );
	$r->set_query_params( $query );
	return rest_do_request( $r );
};
$network = 0;
$deny = static function () use ( &$network ) { ++$network; return new WP_Error( 'audit_no_http', 'No network' ); };
add_filter( 'pre_http_request', $deny, PHP_INT_MAX );
wp_set_current_user( 0 );
$assert( false !== $wpdb->query( 'START TRANSACTION' ), 'Fixture transaction started' );
try {
	$uris = array();
	foreach ( range( 1, 22 ) as $_ ) {
		$uuid = wp_generate_uuid4();
		$uri = home_url( '/actors/' . $uuid );
		$now = current_time( 'mysql', true );
		$assert( 1 === $wpdb->insert( axismundi_actors_actors_table(), array(
			'uuid' => $uuid, 'canonical_uri' => $uri, 'canonical_uri_hash' => hash( 'sha256', $uri ),
			'origin' => 'local', 'status' => 'public', 'actor_type' => 'Person', 'created_at' => $now, 'updated_at' => $now,
		) ), 'Handle-less directory fixture inserted' );
		$id = (int) $wpdb->insert_id;
		$wpdb->update( axismundi_actors_actors_table(), array( 'identity_id' => $id ), array( 'id' => $id ) );
		$uris[] = $uri;
	}
	$remote_uri = 'https://directory-audit.example/actors/' . wp_generate_uuid4();
	$remote_id = $id;
	$wpdb->update( axismundi_actors_actors_table(), array(
		'origin' => 'remote', 'canonical_uri' => $remote_uri, 'canonical_uri_hash' => hash( 'sha256', $remote_uri ),
	), array( 'id' => $remote_id ) );
	$assert( axismundi_actors_store_meta( $remote_id, 'remote_payload', wp_json_encode( array( 'id' => $remote_uri, 'type' => 'Person', 'name' => 'Remote fixture' ) ) ), 'Cached remote Actor payload stored' );
	array_pop( $uris );
	$first = $request( array( 'type' => 'Person' ) );
	$first_data = $first->get_data();
	$first_ids = array_column( $first_data['orderedItems'] ?? array(), 'id' );
	$assert( 200 === $first->get_status() && 'OrderedCollection' === $first_data['type'] && ! isset( $first_data['id'] ) && isset( $first_data['url'] ), 'Directory is an AS2 collection without a synthetic identity' );
	$assert( 20 === count( $first_ids ) && ! in_array( $remote_uri, $first_ids, true ), 'Default page contains local public Actors only' );
	$parts = array();
	parse_str( wp_parse_url( $first_data['next'], PHP_URL_QUERY ), $parts );
	$second = $request( $parts )->get_data();
	$second_ids = array_column( $second['orderedItems'], 'id' );
	$assert( ! array_intersect( $first_ids, $second_ids ) && ! array_diff( $uris, array_merge( $first_ids, $second_ids ) ), 'Cursor continuation reaches every fixture without duplication' );
	$remote = $request( array( 'type' => 'Person', 'origin' => 'remote' ) )->get_data();
	$assert( in_array( $remote_uri, array_column( $remote['orderedItems'], 'id' ), true ), 'Explicit remote origin reads cached Actors' );
	$system = axismundi_actors_get_system_actor();
	$applications = $request( array( 'type' => 'Application', 'cursor' => (string) ( $system->get_identity_id() + 1 ) ) )->get_data();
	$assert( $system && in_array( $system->get_uri(), array_column( $applications['orderedItems'], 'id' ), true ), 'Public system Actor is included without granting user Account access' );
	$wpdb->update( axismundi_actors_actors_table(), array( 'status' => 'disabled' ), array( 'id' => $remote_id ) );
	$remote = $request( array( 'type' => 'Person', 'origin' => 'remote' ) )->get_data();
	$assert( ! in_array( $remote_uri, array_column( $remote['orderedItems'], 'id' ), true ), 'Disabled cached Actor is excluded' );
	$assert( 400 === $request( array( 'type' => 'person' ) )->get_status() && 400 === $request( array( 'type' => 'Note' ) )->get_status(), 'Type filter requires exact Actor type spelling' );
	$assert( 400 === $request( array( 'origin' => 'all' ) )->get_status() && 400 === $request( array( 'scope' => 'user' ) )->get_status(), 'No implicit all-origins or retired scope filter' );
	$assert( 0 === $network, 'Directory does not fetch remote documents' );
} finally {
	$wpdb->query( 'ROLLBACK' );
	wp_set_current_user( $original_user );
	remove_filter( 'pre_http_request', $deny, PHP_INT_MAX );
}
echo "PASS: {$checks} Actor directory checks\n";
