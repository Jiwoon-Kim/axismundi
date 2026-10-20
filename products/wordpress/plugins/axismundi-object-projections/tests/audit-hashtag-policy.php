<?php
/**
 * Numeric hashtag routing, lexical recognition and declaration-only rendering.
 *
 * Run with wp eval-file. For local HTTP checks, set AX_OP_HTTP_TEST_ORIGIN to
 * http://wordpress in the wp-env CLI container. Without it HTTP checks report
 * SKIP; no external peer receives a publication from this audit.
 */
defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$checks = array();
$terms = array();
$posts = array();
$shared_tt = 0;
$original_get = $_GET;
$original_request_uri = $_SERVER['REQUEST_URI'] ?? null;
$remote_uri = 'https://hashtag-policy.example/' . wp_generate_uuid4();
$name = 'Policy' . wp_generate_password( 10, false, false );
$check = static function ( string $label, bool $ok ) use ( &$checks ) {
	$checks[] = $ok;
	printf( "[%s] [project choice] %s\n", $ok ? 'PASS' : 'FAIL', $label );
};
$delete_gate = has_filter( 'pre_delete_post', 'axismundi_act_pre_delete_post_lifecycle' );
if ( false !== $delete_gate ) {
	remove_filter( 'pre_delete_post', 'axismundi_act_pre_delete_post_lifecycle', $delete_gate );
}
try {
	axismundi_op_install();
	$term = axismundi_op_ensure_hashtag_term( $name );
	$terms[] = array( $term->term_id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	$id = home_url( '/?tag_id=' . $term->term_id );
	$descriptor = array( 'id' => $id, 'name' => '#' . $name );
	$check( 'hashtag archive identity uses the stable term id', $id === axismundi_op_hashtag_archive_uri( $term ) );
	$check( 'Hashtag is not registered as a vocabulary type', null === axismundi_op_vocabulary_type( 'Hashtag' ) );
	$query = apply_filters( 'request', array( 'tag_id' => (string) $term->term_id ) );
	$check( 'numeric lookup becomes the owned taxonomy query, not post_tag', ! isset( $query['tag_id'] ) && AXISMUNDI_OP_HASHTAG_TAXONOMY === $query['taxonomy'] && $term->slug === $query['term'] );
	$wp_query_fixture = new WP_Query( $query );
	$queried = $wp_query_fixture->get_queried_object();
	$check( 'WP_Query resolves the numeric request to the correct term', $queried instanceof WP_Term && $term->term_id === $queried->term_id && AXISMUNDI_OP_HASHTAG_TAXONOMY === $queried->taxonomy );
	$_GET = array( 'tag_id' => (string) $term->term_id );
	$_SERVER['REQUEST_URI'] = wp_parse_url( home_url( '/' ), PHP_URL_PATH ) . '?tag_id=' . $term->term_id;
	$raw_query = axismundi_op_route_numeric_hashtag( array() );
	$check( 'our raw numeric URL is claimed even though Core drops the private query var', AXISMUNDI_OP_HASHTAG_TAXONOMY === ( $raw_query['taxonomy'] ?? '' ) );
	$_SERVER['REQUEST_URI'] = '/@some-actor/?tag_id=' . $term->term_id;
	$check( 'a numeric query on another product path does not hijack that namespace', array() === axismundi_op_route_numeric_hashtag( array() ) );
	$_SERVER['REQUEST_URI'] = $original_request_uri ?? '/';
	$_GET = $original_get;
	$shared = $wpdb->insert( $wpdb->term_taxonomy, array( 'term_id' => $term->term_id, 'taxonomy' => 'post_tag', 'description' => '', 'parent' => 0, 'count' => 0 ) );
	$shared_tt = false === $shared ? 0 : (int) $wpdb->insert_id;
	clean_term_cache( $term->term_id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	$check( 'a legacy shared term never steals the Core post_tag identity', $shared_tt > 0 && null === axismundi_op_numeric_hashtag_term( array( 'tag_id' => $term->term_id ) ) && 'core-shared' === axismundi_op_numeric_hashtag_canonical( 'core-shared', $id ) );
	if ( $shared_tt ) {
		$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $shared_tt ) );
		$shared_tt = 0;
		clean_term_cache( $term->term_id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	}
	$collection = axismundi_op_transform_collection( $term );
	$check( 'archive metadata uses a scalar human url, hashtag name and no invented language maps', get_term_link( $term ) === $collection['url'] && '#' . $term->name === $collection['name'] && ! isset( $collection['nameMap'], $collection['summaryMap'], $collection['content'] ) );
	$check( 'an empty term description omits summary', ! isset( $collection['summary'] ) );
	$described = clone $term;
	$described->description = '<p>Topic <strong>description</strong></p><script>alert(1)</script>';
	$described_document = axismundi_op_hashtag_archive_transform( $described );
	$check( 'term description becomes sanitized summary', wp_kses_post( $described->description ) === $described_document['summary'] && ! str_contains( $described_document['summary'], '<script' ) );
	$check( 'the human permalink uses the plural namespace', str_contains( get_term_link( $term ), '/hashtags/' ) );
	$lower = axismundi_op_ensure_hashtag_term( strtolower( $name ) );
	$check( 'case variants resolve to the same local term', $term->term_id === $lower->term_id );
	$underscore = axismundi_op_normalize_hashtag( '#Text_Text' );
	$dash = axismundi_op_normalize_hashtag( '#text-text' );
	$check( 'underscore is preserved rather than folded into a hyphen', 'text_text' === $underscore['key'] && $underscore['key'] !== $dash['key'] && 'text_text' === sanitize_title( 'Text_Text' ) );
	$check( 'dereference describes our OrderedCollection and separates the human url', $id === $collection['id'] && 'OrderedCollection' === $collection['type'] && get_term_link( $term ) === axismundi_op_object_html_url( $collection ) );
	$check( 'identity does not fabricate totals or member enumeration', ! array_intersect( array( 'totalItems', 'items', 'orderedItems', 'first', 'next' ), array_keys( $collection ) ) );
	$check( 'canonical redirects do not replace numeric identity with a slug', false === axismundi_op_numeric_hashtag_canonical( get_term_link( $term ), $id ) );
	$check( 'the debug selector does not change numeric identity', false === axismundi_op_numeric_hashtag_canonical( get_term_link( $term ), $id . '&activitypub' ) );
	$check( 'the numeric query cannot disable another path canonical redirect', 'other-redirect' === axismundi_op_numeric_hashtag_canonical( 'other-redirect', home_url( '/other-page/?tag_id=' . $term->term_id ) ) );

	$core = wp_insert_term( $name . 'Core', 'post_tag' );
	$terms[] = array( (int) $core['term_id'], 'post_tag' );
	$core_query = array( 'tag_id' => (string) $core['term_id'] );
	$check( 'Core post_tag numeric routing remains untouched', $core_query === axismundi_op_route_numeric_hashtag( $core_query ) && null === axismundi_op_numeric_hashtag_term( $core_query ) );
	$check( 'Core tag canonical redirects remain enabled', 'core-redirect' === axismundi_op_numeric_hashtag_canonical( 'core-redirect', home_url( '/?tag_id=' . $core['term_id'] ) ) );
	$_GET = $core_query;
	$check( 'raw Core tag ids are not newly promoted into public query variables', array() === axismundi_op_route_numeric_hashtag( array() ) );
	$_GET = $original_get;
	foreach ( array( array(), array( 'tag_id' => array( $term->term_id ) ), array( 'tag_id' => $term->term_id . 'junk' ), array( 'tag_id' => '-1' ), array( 'tag_id' => '9999999999999999999999999999' ), array( 'tag_id' => '999999999' ), array( 'tag_id' => $term->term_id, 'p' => 15 ), array( 'tag_id' => $term->term_id, 'ax_note' => 'some-note' ) ) as $invalid ) {
		$check( 'unowned or ambiguous query is not hijacked: ' . wp_json_encode( $invalid ), $invalid === axismundi_op_route_numeric_hashtag( $invalid ) );
	}
	$old_slug = $term->slug;
	wp_update_term( $term->term_id, AXISMUNDI_OP_HASHTAG_TAXONOMY, array( 'slug' => $old_slug . '-changed' ) );
	$term = get_term( $term->term_id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	$check( 'a slug change does not change collection identity or numeric resolution', $id === axismundi_op_hashtag_archive_uri( $term ) && $term->slug === axismundi_op_route_numeric_hashtag( array( 'tag_id' => $term->term_id ) )['term'] );

	$post_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'Hashtag policy audit' ) );
	$posts[] = $post_id;
	wp_set_object_terms( $post_id, array( (int) $term->term_id ), AXISMUNDI_OP_HASHTAG_TAXONOMY );
	$check( 'local tag emission contains exactly id and name', array( $descriptor ) === axismundi_op_post_hashtag_tags( get_post( $post_id ) ) );
	$payload = array( 'id' => $remote_uri, 'type' => 'Note', 'to' => 'https://www.w3.org/ns/activitystreams#Public', 'content' => '#' . $name, 'tag' => array( 'id' => 'https://hashtag-policy.example/tags/original', 'name' => '#' . $name ) );
	$row = axismundi_op_store_remote_object( $payload, array( 'complete' => true ) );
	$extracted = axismundi_op_remote_object_hashtag_descriptors( $payload );
	$check( 'a singular untyped id/name tag is indexed', 1 === count( $extracted ) && 'https://hashtag-policy.example/tags/original' === $extracted[0]['href'] );
	$legacy_table = axismundi_op_remote_object_hashtags_table();
	$check( 'untyped receipt reaches the existing archive membership index', 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$legacy_table} WHERE remote_object_id=%d AND term_taxonomy_id=%d", $row['id'], $term->term_taxonomy_id ) ) );
	$without_hash = axismundi_op_remote_object_hashtag_descriptors( array( 'tag' => array( 'type' => 'Hashtag', 'name' => $name ) ) );
	$check( 'the Hashtag label alone does not classify a descriptor', array() === $without_hash );
	$read = axismundi_op_read_object_jsonld( $remote_uri );
	$check( 'remote read preserves its received descriptor rather than inventing a local id or type', array( $payload['tag'] ) === ( $read['tag'] ?? array() ) );
	$payload['tag']['type'] = 'Hashtag';
	$payload['tag']['x:extra'] = array( 'untrusted' => '<b>raw extension</b>' );
	axismundi_op_store_remote_object( $payload, array( 'complete' => true ) );
	$read = axismundi_op_read_object_jsonld( $remote_uri );
	$check( 'observed type is retained but unknown tag fields remain opaque evidence', 'Hashtag' === ( $read['tag'][0]['type'] ?? '' ) && ! isset( $read['tag'][0]['x:extra'] ) && $payload['tag'] === ( $read['axUninterpreted']['members']['tag'][0] ?? null ) );
	$before = axismundi_op_get_remote_object( $remote_uri )['payload_hash'];
	$html = axismundi_op_render_declared_hashtags( '<p>#' . $name . ' #undeclared <code>#' . $name . '</code><pre>#' . $name . '</pre><a href="https://old.example/"><span>#' . $name . '</span></a></p>', array( $payload['tag'] ) );
	$check( 'declared plain text is linked to the local taxonomy first', str_contains( $html, '<a href="' . esc_url( $id ) . '" rel="tag">#' . $name . '</a>' ) );
	$check( 'undeclared hashtag text is not guessed', str_contains( $html, ' #undeclared ' ) );
	$check( 'code and pre content are untouched', str_contains( $html, '<code>#' . $name . '</code><pre>#' . $name . '</pre>' ) );
	$check( 'existing hashtag anchors are localized without nested anchors', str_contains( $html, '<a href="' . esc_url( $id ) . '"><span>#' . $name . '</span></a>' ) && 2 === substr_count( $html, '<a ' ) );
	$check( 'presentation never rewrites stored remote evidence', $before === axismundi_op_get_remote_object( $remote_uri )['payload_hash'] );
	$check( 'linkification is idempotent', $html === axismundi_op_render_declared_hashtags( $html, array( $payload['tag'] ) ) );
	$protected = '<script>#' . $name . '</script><style>#' . $name . '</style><textarea>#' . $name . '</textarea><a href="https://else.example/">other #' . $name . '</a><span data-tag="#' . $name . '"></span><!-- #' . $name . ' -->';
	$check( 'raw-text elements, ordinary anchors, attributes and comments are untouched', $protected === axismundi_op_render_declared_hashtags( $protected, array( $descriptor ) ) );
	$boundary = '#' . $name . 'Suffix https://example.org/#' . $name . ' #' . $name . '_extra';
	$check( 'prefixes and URL fragments do not become partial hashtags', $boundary === axismundi_op_render_declared_hashtags( $boundary, array( $descriptor ) ) );
	$unicode = "#\u{BD80}\u{C0B0}" . wp_generate_password( 6, false, false );
	$check( 'Unicode declarations are not restricted to ASCII hashtag spelling', str_contains( axismundi_op_render_declared_hashtags( $unicode, array( array( 'name' => $unicode, 'href' => 'https://peer.example/unicode' ) ) ), '>' . $unicode . '</a>' ) );
	$fallback_name = '#Fallback' . wp_generate_password( 10, false, false );
	$fallback_tag = array( 'name' => $fallback_name, 'id' => 'https://peer.example/id', 'href' => 'https://peer.example/href' );
	$count_before = (int) wp_count_terms( array( 'taxonomy' => AXISMUNDI_OP_HASHTAG_TAXONOMY, 'hide_empty' => false ) );
	$check( 'a missing local term falls back to the declared id', str_contains( axismundi_op_render_declared_hashtags( $fallback_name, array( $fallback_tag ) ), 'href="https://peer.example/id"' ) );
	$fallback_tag['id'] = 'javascript:alert(1)';
	$check( 'an unsafe id is ignored and a safe href is the next fallback', str_contains( axismundi_op_render_declared_hashtags( $fallback_name, array( $fallback_tag ) ), 'href="https://peer.example/href"' ) );
	$fallback_tag['href'] = 'javascript:alert(1)';
	$check( 'without a safe destination the text stays unchanged', $fallback_name === axismundi_op_render_declared_hashtags( $fallback_name, array( $fallback_tag ) ) );
	$check( 'rendering creates no vocabulary rows', $count_before === (int) wp_count_terms( array( 'taxonomy' => AXISMUNDI_OP_HASHTAG_TAXONOMY, 'hide_empty' => false ) ) );
	$post_uri = axismundi_op_local_object_profile( get_post( $post_id ) )['uri'];
	$check( 'local presentation consumes its own stored term references', str_contains( axismundi_op_localize_object_hashtag_links( '#' . $name, array( 'id' => $post_uri ) ), 'href="' . esc_url( $id ) . '"' ) );

	// Optional HTTP integration: explicitly target the local development server only.
	$origin = getenv( 'AX_OP_HTTP_TEST_ORIGIN' );
	if ( $origin ) {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$port = wp_parse_url( home_url(), PHP_URL_PORT );
		$host .= $port ? ':' . $port : '';
		foreach ( array( 'application/activity+json', 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ) as $accept ) {
			$response = wp_remote_get( rtrim( $origin, '/' ) . '/?tag_id=' . $term->term_id, array( 'headers' => array( 'Host' => $host, 'Accept' => $accept ), 'redirection' => 0, 'timeout' => 20 ) );
			$document = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $document ) || $id !== ( $document['id'] ?? '' ) ) {
				printf( "HTTP diagnostic: %s\n", wp_json_encode( is_wp_error( $response ) ? $response->get_error_message() : array( 'status' => wp_remote_retrieve_response_code( $response ), 'type' => wp_remote_retrieve_header( $response, 'content-type' ), 'body' => substr( wp_remote_retrieve_body( $response ), 0, 100 ) ) ) );
			}
			$check( 'HTTP numeric identity negotiates without redirect: ' . $accept, 200 === wp_remote_retrieve_response_code( $response ) && $id === ( $document['id'] ?? '' ) && 'OrderedCollection' === ( $document['type'] ?? '' ) );
		}
		$response = wp_remote_get( rtrim( $origin, '/' ) . '/?tag_id=' . $term->term_id . '&activitypub', array( 'headers' => array( 'Host' => $host, 'Accept' => 'text/html' ), 'redirection' => 0, 'timeout' => 20 ) );
		$check( 'HTTP debug selector reaches the same representation', $id === ( json_decode( wp_remote_retrieve_body( $response ), true )['id'] ?? '' ) && 200 === wp_remote_retrieve_response_code( $response ) );
		$response = wp_remote_get( rtrim( $origin, '/' ) . '/?tag_id=' . $term->term_id, array( 'headers' => array( 'Host' => $host, 'Accept' => 'text/html' ), 'redirection' => 0, 'timeout' => 20 ) );
		$check( 'HTTP numeric HTML view stays at its numeric address', 200 === wp_remote_retrieve_response_code( $response ) && str_contains( wp_remote_retrieve_header( $response, 'content-type' ), 'text/html' ) && ! wp_remote_retrieve_header( $response, 'location' ) );
		$response = wp_remote_request( rtrim( $origin, '/' ) . '/?tag_id=' . $term->term_id, array( 'method' => 'HEAD', 'headers' => array( 'Host' => $host, 'Accept' => 'application/activity+json' ), 'redirection' => 0, 'timeout' => 20 ) );
		$check( 'HTTP HEAD negotiates with an empty body', 200 === wp_remote_retrieve_response_code( $response ) && str_contains( wp_remote_retrieve_header( $response, 'content-type' ), 'application/activity+json' ) && '' === wp_remote_retrieve_body( $response ) );
		$response = wp_remote_get( rtrim( $origin, '/' ) . wp_parse_url( get_term_link( $term ), PHP_URL_PATH ), array( 'headers' => array( 'Host' => $host, 'Accept' => 'application/activity+json' ), 'redirection' => 0, 'timeout' => 20 ) );
		$check( 'HTTP pretty archive remains a negotiated alias of the numeric id', 200 === wp_remote_retrieve_response_code( $response ) && $id === ( json_decode( wp_remote_retrieve_body( $response ), true )['id'] ?? '' ) );
		$legacy_path = str_replace( '/hashtags/', '/hashtag/', wp_parse_url( get_term_link( $term ), PHP_URL_PATH ) );
		$response = wp_remote_get( rtrim( $origin, '/' ) . $legacy_path, array( 'headers' => array( 'Host' => $host, 'Accept' => 'application/activity+json' ), 'redirection' => 0, 'timeout' => 20 ) );
		$check( 'HTTP legacy singular path still negotiates the same identity', 200 === wp_remote_retrieve_response_code( $response ) && $id === ( json_decode( wp_remote_retrieve_body( $response ), true )['id'] ?? '' ) );
		$response = wp_remote_get( rtrim( $origin, '/' ) . $legacy_path, array( 'headers' => array( 'Host' => $host, 'Accept' => 'text/html' ), 'redirection' => 0, 'timeout' => 20 ) );
		$check( 'HTTP legacy HTML path redirects to the plural human permalink', 301 === wp_remote_retrieve_response_code( $response ) && get_term_link( $term ) === wp_remote_retrieve_header( $response, 'location' ) );
	} else {
		echo "HTTP integration skipped: set AX_OP_HTTP_TEST_ORIGIN to the local server.\n";
	}
} finally {
	$_GET = $original_get;
	if ( null === $original_request_uri ) {
		unset( $_SERVER['REQUEST_URI'] );
	} else {
		$_SERVER['REQUEST_URI'] = $original_request_uri;
	}
	if ( $shared_tt ) {
		$wpdb->delete( $wpdb->term_taxonomy, array( 'term_taxonomy_id' => $shared_tt ) );
		clean_term_cache( $term->term_id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	}
	foreach ( $posts as $post_id ) {
		$post = get_post( $post_id );
		$profile = $post instanceof WP_Post ? axismundi_op_local_object_profile( $post ) : null;
		wp_delete_post( $post_id, true );
		if ( is_array( $profile ) ) {
			$identity = axismundi_actors_get_identity_by_uri( $profile['uri'] );
			if ( is_array( $identity ) ) {
				$wpdb->delete( axismundi_op_objects_table(), array( 'identity_id' => $identity['identity_id'] ) );
				$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity['identity_id'] ) );
			}
			foreach ( array( axismundi_op_tag_references_table(), axismundi_op_term_usage_table() ) as $table ) {
				$wpdb->delete( $table, array( 'source_uri_hash' => hash( 'sha256', $profile['uri'] ) ) );
			}
		}
	}
	axismundi_op_delete_remote_object( $remote_uri );
	$identity = axismundi_actors_get_identity_by_uri( $remote_uri );
	if ( is_array( $identity ) ) {
		$wpdb->delete( axismundi_op_objects_table(), array( 'identity_id' => $identity['identity_id'] ) );
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity['identity_id'] ) );
	}
	foreach ( array( axismundi_op_tag_references_table(), axismundi_op_term_usage_table() ) as $table ) {
		$wpdb->delete( $table, array( 'source_uri_hash' => hash( 'sha256', $remote_uri ) ) );
	}
	foreach ( $terms as $entry ) {
		wp_delete_term( $entry[0], $entry[1] );
	}
	if ( false !== $delete_gate ) {
		add_filter( 'pre_delete_post', 'axismundi_act_pre_delete_post_lifecycle', $delete_gate, 2 );
	}
}
$failed = count( array_filter( $checks, static fn( $ok ) => ! $ok ) );
printf( "\n== %d checks, %d failed ==\n", count( $checks ), $failed );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failed ? 1 : 0 );
}
