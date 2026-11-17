<?php
/** Registry admission and live disclosure audit. Run with wp eval-file. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
};
$read = static function ( array $query ) : WP_REST_Response {
	$request = new WP_REST_Request( 'GET', '/axismundi/activitypub/objects' );
	$request->set_query_params( $query );
	return rest_do_request( $request );
};
$remote = 'https://collection-audit.example/articles/' . wp_generate_uuid4() . '?one=1&two=2';
$public = 'https://www.w3.org/ns/activitystreams#Public';
$author = 'https://collection-audit.example/actor/' . wp_generate_uuid4();
$first = get_current_user_id();
wp_set_current_user( 0 );
$assert( false !== $wpdb->query( 'START TRANSACTION' ), 'Fixture transaction started' );
try {
	$wpdb->insert( $wpdb->posts, array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Collection audit', 'post_content' => 'Fixture body', 'post_date' => current_time( 'mysql' ), 'post_date_gmt' => current_time( 'mysql', true ) ) );
	$post_id = (int) $wpdb->insert_id;
	$local = axismundi_op_post_object_uri( get_post( $post_id ) );
	$wpdb->insert( $wpdb->posts, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Collection audit page' ) );
	$page_id = (int) $wpdb->insert_id;
	$assert( null === axismundi_op_get_object_by_uri( $local ), 'Unregistered Core Article fixture' );
	$assert( is_int( axismundi_op_backfill_core_objects() ) && 0 === axismundi_op_backfill_core_objects(), 'Core backfill is replay-safe' );
	$assert( 'Article' === ( axismundi_op_get_object_by_uri( $local )['as2_type'] ?? null ), 'Missing Core post registered' );
	$assert( null === axismundi_op_get_object_by_uri( add_query_arg( 'p', $page_id, home_url( '/' ) ) )
		&& null === axismundi_op_local_object_profile( get_post( $page_id ) ), 'Core pages are not admitted' );
	$legacy_page = add_query_arg( 'page_id', $page_id, home_url( '/' ) );
	$now = current_time( 'mysql', true );
	$assert( 1 === $wpdb->insert( axismundi_op_objects_table(), array(
		'uuid' => wp_generate_uuid4(), 'canonical_uri' => $legacy_page, 'canonical_uri_hash' => hash( 'sha256', $legacy_page ),
		'origin' => 'local', 'status' => 'public', 'source_owner' => 'wordpress-core', 'source_kind' => 'page',
		'source_ref' => $page_id, 'as2_type' => 'Page', 'created_at' => $now, 'updated_at' => $now,
	) ), 'Legacy Core page projection fixture' );
	$pages = $read( array( 'type' => 'Page' ) );
	$assert( 200 === $pages->get_status() && ! in_array( $legacy_page, array_column( $pages->get_data()['orderedItems'], 'id' ), true )
		&& null !== axismundi_op_get_object_by_uri( $legacy_page ), 'Legacy Core page is omitted without deleting its registry row' );
	$assert( ! is_wp_error( axismundi_op_store_remote_object( array(
		'id' => $remote, 'type' => 'Article', 'content' => '<p>Remote body</p>', 'attributedTo' => $author, 'to' => array( $public ),
	) ) ), 'Remote Article stored under exact URI' );
	$result = $read( array( 'type' => 'Article' ) );
	$ids = array_column( $result->get_data()['orderedItems'] ?? array(), 'id' );
	$assert( 200 === $result->get_status() && in_array( $local, $ids, true ) && in_array( $remote, $ids, true ), 'Public listing includes live Core and remote Articles' );
	$filtered = $read( array( 'type' => 'Article', 'attributedTo' => $author ) );
	$assert( array( $remote ) === array_column( $filtered->get_data()['orderedItems'] ?? array(), 'id' ), 'Attribution filter matches only explicit attribution' );
	$parts = array();
	parse_str( http_build_query( array( 'ids' => $remote ), '', '&', PHP_QUERY_RFC3986 ), $parts );
	$assert( $remote === $parts['ids'] && $remote === axismundi_op_read_object_jsonld( $parts['ids'] )['id'], 'Encoded URI preserves multiple query parameters and resolves exact cache key' );
	$assert( 400 === $read( array( 'type' => 'article' ) )->get_status(), 'Type spelling remains exact' );
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'trash' ), array( 'ID' => $post_id ) );
	clean_post_cache( $post_id );
	$assert( ! in_array( $local, array_column( $read( array( 'type' => 'Article' ) )->get_data()['orderedItems'], 'id' ), true ), 'Stale indexed status cannot expose a trashed Core source' );
	$wpdb->delete( $wpdb->posts, array( 'ID' => $post_id ) );
	clean_post_cache( $post_id );
	$assert( null !== axismundi_op_get_object_by_uri( $local )
		&& ! in_array( $local, array_column( $read( array( 'type' => 'Article' ) )->get_data()['orderedItems'], 'id' ), true ), 'Missing Core source is omitted without deleting registry history' );
} finally {
	$wpdb->query( 'ROLLBACK' );
	wp_set_current_user( $first );
}
echo "PASS: {$checks} Object collection checks\n";
