<?php
/** wp eval-file: real source-path parity, public disclosure and keyset traversal. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = array();
$posts = array();
$uris = array();
$term = null;
$name = 'Members' . strtolower( wp_generate_password( 10, false, false ) );
$actor = 'https://term-members.example/actors/alice';
$old_query = $GLOBALS['wp_query'];
$old_get = $_GET;
$check = static function ( $name, $ok ) use ( &$checks ) { $checks[] = (bool) $ok; printf( "[%s] [project choice] %s\n", $ok ? 'PASS' : 'FAIL', $name ); };
$set = static function ( $items ) { $out = array_column( $items, 'object_uri' ); sort( $out ); return $out; };
$gate = has_filter( 'pre_delete_post', 'axismundi_act_pre_delete_post_lifecycle' );
if ( false !== $gate ) { remove_filter( 'pre_delete_post', 'axismundi_act_pre_delete_post_lifecycle', $gate ); }
try {
	axismundi_op_install();
	$term = axismundi_op_ensure_hashtag_term( $name );
	$post_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $name . ' local', 'post_content' => '<p>Local member.</p>' ) );
	$posts[] = $post_id;
	wp_set_object_terms( $post_id, array( $term->term_id ), AXISMUNDI_OP_HASHTAG_TAXONOMY );
	$local_uri = axismundi_op_post_object_uri( get_post( $post_id ) );
	$store = static function ( $suffix, $public = true, $published = '2026-07-22T00:00:00Z' ) use ( &$uris, $name, $actor ) {
		$uri = 'https://term-members.example/' . $name . '/' . $suffix;
		$uris[] = $uri;
		return axismundi_op_store_remote_object( array( 'id' => $uri, 'type' => 'Note', 'attributedTo' => $actor, 'to' => $public ? 'https://www.w3.org/ns/activitystreams#Public' : $actor, 'published' => $published, 'content' => '<p>' . $name . ' remote ' . $suffix . '</p>', 'tag' => array( array( 'name' => '#' . $name ) ) ), array( 'complete' => true ) );
	};
	$remote = $store( 'public' );
	$private = $store( 'private', false );
	$draft_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => $name . ' draft' ) );
	$posts[] = $draft_id;
	wp_set_object_terms( $draft_id, array( $term->term_id ), AXISMUNDI_OP_HASHTAG_TAXONOMY );

	// Frozen old reader inputs, not an oracle built from the new usage index.
	$legacy = array();
	foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 50, 'tax_query' => array( array( 'taxonomy' => AXISMUNDI_OP_HASHTAG_TAXONOMY, 'field' => 'term_id', 'terms' => $term->term_id ) ) ) ) as $post ) {
		axismundi_op_hashtag_archive_add_source( $legacy, $post );
	}
	$objects = axismundi_op_remote_objects_table();
	$old_index = axismundi_op_remote_object_hashtags_table();
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT o.* FROM {$objects} o INNER JOIN {$old_index} h ON h.remote_object_id=o.id WHERE h.term_taxonomy_id=%d AND o.object_status='active' ORDER BY COALESCE(o.published_at,o.remote_updated_at,o.created_at) DESC,o.id DESC LIMIT 50", $term->term_taxonomy_id ), ARRAY_A );
	foreach ( $rows as $row ) {
		$row['payload'] = json_decode( $row['payload_json'], true );
		if ( axismundi_op_remote_object_is_publicly_listable( $row ) ) { axismundi_op_hashtag_archive_add_source( $legacy, axismundi_op_resolve_source_by_uri( $row['object_uri'] ) ); }
	}
	$page = axismundi_op_query_term_members( $term );
	$check( 'real local taxonomy and remote cache writes match the old archive member set', 2 === count( $legacy ) && $set( array_values( $legacy ) ) === $set( $page['items'] ) );
	$check( 'missing filters never grant access to private or draft sources', 2 === count( $page['items'] ) && ! in_array( $private['object_uri'], $set( $page['items'] ), true ) );
	$filtered = axismundi_op_query_term_members( $term, array( 'actor' => $actor ) );
	$check( 'Actor URI filtering joins the durable Object registry', array( $remote['object_uri'] ) === $set( $filtered['items'] ) );
	$check( 'type filters narrow without granting private access', array( $remote['object_uri'] ) === $set( axismundi_op_query_term_members( $term, array( 'type' => 'remote' ) )['items'] ) && array( $local_uri ) === $set( axismundi_op_query_term_members( $term, array( 'type' => 'post' ) )['items'] ) );
	$first = axismundi_op_query_term_members( $term, array( 'limit' => 1 ) );
	$second = axismundi_op_query_term_members( $term, array( 'limit' => 1, 'cursor' => $first['next_cursor'] ) );
	$check( 'one cursor traverses both origins without duplication', $set( array_merge( $first['items'], $second['items'] ) ) === $set( $page['items'] ) );
	$store( 'public', true, '2097-01-01T00:00:00Z' );
	$after_update = axismundi_op_query_term_members( $term, array( 'limit' => 1, 'cursor' => $first['next_cursor'] ) );
	$check( 'an Update to source publication metadata cannot move membership across a cursor', $set( $second['items'] ) === $set( $after_update['items'] ) );
	$check( 'changing Actor scope rejects an existing cursor', is_wp_error( axismundi_op_query_term_members( $term, array( 'actor' => $actor, 'cursor' => $first['next_cursor'] ) ) ) );
	$check( 'changing type scope rejects an existing cursor', is_wp_error( axismundi_op_query_term_members( $term, array( 'type' => 'remote', 'cursor' => $first['next_cursor'] ) ) ) );
	$check( 'tampered cursors fail rather than restarting', is_wp_error( axismundi_op_query_term_members( $term, array( 'cursor' => $first['next_cursor'] . 'x' ) ) ) );
	$check( 'published collection still claims no members or total', ! array_intersect( array( 'items', 'orderedItems', 'totalItems', 'first', 'next' ), array_keys( axismundi_op_transform_collection( $term ) ) ) );
	$_GET = array();
	$GLOBALS['wp_query'] = new WP_Query( array( AXISMUNDI_OP_HASHTAG_TAXONOMY => $term->slug ) );
	$html = do_blocks( '<!-- wp:axismundi/hashtag-archive {"perPage":1} /-->' );
	$check( 'HTML uses the same index page and exposes a continuation', str_contains( $html, 'rel="next"' ) && str_contains( $html, 'cursor=' ) );
	$all_html = do_blocks( '<!-- wp:axismundi/hashtag-archive /-->' );
	$check( 'HTML retains both real-source cards and omits private content', str_contains( $all_html, $name . ' local' ) && str_contains( $all_html, $name . ' remote public' ) && ! str_contains( $all_html, $name . ' remote private' ) );

	$usage = axismundi_op_term_usage_table();
	$ref_uri = 'https://term-members.example/' . $name . '/profile';
	axismundi_op_replace_tag_references( $ref_uri, array( array( 'name' => '#' . $name ) ), array( 'provider' => 'actor-profile', 'role' => 'reference', 'complete' => true ) );
	$uris[] = $ref_uri;
	$check( 'Actor profile tags remain references rather than archive membership', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$usage} WHERE source_uri_hash=%s", hash( 'sha256', $ref_uri ) ) ) );
	for ( $i = 0; $i < 105; $i++ ) { $store( 'private-prefix-' . $i, false, '2098-01-01T00:00:00Z' ); }
	$check( 'public members remain reachable beyond a full batch of real private observations', $set( axismundi_op_query_term_members( $term )['items'] ) === $set( $page['items'] ) );
	$values = array();
	for ( $i = 0; $i < 505; $i++ ) {
		$missing = 'https://term-members.example/' . $name . '/evicted-' . $i;
		$values[] = $wpdb->prepare( '(%d,%s,%s,%s,%s,1)', $term->term_taxonomy_id, $missing, hash( 'sha256', $missing ), 'remote-cache', '2099-01-01 00:00:00' );
	}
	$wpdb->query( "INSERT INTO {$usage} (term_taxonomy_id,source_uri,source_uri_hash,provider,feed_sort_at,is_current) VALUES " . implode( ',', $values ) );
	$gap = axismundi_op_query_term_members( $term );
	$after_gap = axismundi_op_query_term_members( $term, array( 'cursor' => $gap['next_cursor'] ) );
	$check( 'scan budget does not terminate traversal behind a long unavailable prefix', array() === $gap['items'] && is_string( $gap['next_cursor'] ) && $set( $after_gap['items'] ) === $set( $page['items'] ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$usage} WHERE term_taxonomy_id=%d AND feed_sort_at=%s", $term->term_taxonomy_id, '2099-01-01 00:00:00' ) );
	$wpdb->delete( $old_index, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
	$check( 'reader no longer depends on the legacy remote hashtag index', $set( axismundi_op_query_term_members( $term )['items'] ) === $set( $page['items'] ) );
	do_action( 'rest_api_init' );
	$request = new WP_REST_Request( 'GET', '/axismundi/v1/hashtags/' . $term->term_id . '/members' );
	$request->set_param( 'requester', 'owner' );
	$response = rest_do_request( $request );
	$check( 'REST is a public application result with no exact total or owner escalation', 200 === $response->get_status() && $set( $response->get_data()['items'] ) === $set( $page['items'] ) && ! isset( $response->get_data()['totalItems'] ) );
	$check( 'REST member results are not shared-cacheable', 'no-store' === ( $response->get_headers()['Cache-Control'] ?? '' ) );
	$request->set_param( 'cursor', 'invalid-cursor' );
	$check( 'REST rejects malformed traversal state with HTTP 400', 400 === rest_do_request( $request )->get_status() );
	$origin = getenv( 'AX_OP_HTTP_TEST_ORIGIN' );
	if ( $origin ) {
		$path = wp_parse_url( rest_url( 'axismundi/v1/hashtags/' . $term->term_id . '/members' ), PHP_URL_PATH );
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$port = wp_parse_url( home_url(), PHP_URL_PORT );
		$http = wp_remote_get( rtrim( $origin, '/' ) . $path, array( 'headers' => array( 'Host' => $host . ( $port ? ':' . $port : '' ) ), 'redirection' => 0, 'timeout' => 20 ) );
		$body = json_decode( wp_remote_retrieve_body( $http ), true );
		$check( 'local HTTP route returns the same public member set without negotiation', 200 === wp_remote_retrieve_response_code( $http ) && is_array( $body ) && $set( $body['items'] ?? array() ) === $set( $page['items'] ) );
	} else {
		echo "HTTP integration skipped: set AX_OP_HTTP_TEST_ORIGIN=http://wordpress in wp-env CLI.\n";
	}
	if ( function_exists( 'axismundi_media_effective_visibility' ) ) {
		$media_id = wp_insert_attachment( array( 'post_mime_type' => 'audio/mpeg', 'post_status' => 'inherit', 'post_title' => 'Member visibility audit', 'guid' => home_url( '/wp-content/uploads/member-visibility-audit.mp3' ) ) );
		$posts[] = $media_id;
		update_post_meta( $media_id, '_ax_media_visibility', 'unlisted' );
		wp_set_object_terms( $media_id, array( $term->term_id ), AXISMUNDI_OP_HASHTAG_TAXONOMY );
		$check( 'an explicit media filter cannot list an unlisted direct-view attachment', array() === axismundi_op_query_term_members( $term, array( 'type' => 'media' ) )['items'] );
		update_post_meta( $media_id, '_ax_media_visibility', 'public' );
		$check( 'public attachments remain available through the existing media bucket', 1 === count( axismundi_op_query_term_members( $term, array( 'type' => 'media' ) )['items'] ) );
	}
} finally {
	$_GET = $old_get;
	$GLOBALS['wp_query'] = $old_query;
	foreach ( $posts as $id ) {
		$profile = axismundi_op_local_object_profile( get_post( $id ) );
		if ( is_array( $profile ) ) { $uris[] = $profile['uri']; }
		wp_delete_post( $id, true );
	}
	foreach ( $uris as $uri ) {
		axismundi_op_delete_remote_object( $uri );
		$identity = axismundi_actors_get_identity_by_uri( $uri );
		if ( is_array( $identity ) ) {
			$wpdb->delete( axismundi_op_objects_table(), array( 'identity_id' => $identity['identity_id'] ) );
			$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity['identity_id'] ) );
		}
		foreach ( array( axismundi_op_tag_references_table(), axismundi_op_term_usage_table() ) as $table ) { $wpdb->delete( $table, array( 'source_uri_hash' => hash( 'sha256', $uri ) ) ); }
	}
	if ( $term instanceof WP_Term ) { wp_delete_term( $term->term_id, AXISMUNDI_OP_HASHTAG_TAXONOMY ); }
	if ( false !== $gate ) { add_filter( 'pre_delete_post', 'axismundi_act_pre_delete_post_lifecycle', $gate, 3 ); }
}
$failed = count( array_filter( $checks, static fn( $ok ) => ! $ok ) );
printf( "\n== %d checks, %d failed ==\n", count( $checks ), $failed );
if ( class_exists( 'WP_CLI' ) ) { WP_CLI::halt( $failed ? 1 : 0 ); }
