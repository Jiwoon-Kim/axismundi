<?php
/** Registry-only JSON-LD application reads; isolated remote fixtures, no network. */
defined( 'ABSPATH' ) || exit( 1 );
$GLOBALS['ax_read_results'] = array();
function ax_read_assert( string $label, bool $holds ) : void {
	$GLOBALS['ax_read_results'][] = $holds;
	printf( "[%s] [project choice] %s\n", $holds ? 'PASS' : 'FAIL', $label );
}
function ax_read_error( $result, string $code ) : bool {
	return is_wp_error( $result ) && $code === $result->get_error_code();
}
$base = 'https://read-api.example/objects/' . wp_generate_uuid4();
$uris = array( $base . '/article', $base . '/poll', $base . '/private', $base . '/tombstone', $base . '/language' );
$assemblies = axismundi_op_object_assemblies();
$local_fixture_identity = null;
$network_calls = 0;
$deny_network = static function () use ( &$network_calls ) { ++$network_calls; return new WP_Error( 'audit_no_network', 'No network during this audit.' ); };
add_filter( 'pre_http_request', $deny_network, PHP_INT_MAX );
try {
	global $wpdb;
	$post = get_page_by_path( 'vqa-demo-post-1', OBJECT, 'post' );
	if ( ! $post instanceof WP_Post ) {
		throw new RuntimeException( 'The public baseline Article fixture is missing.' );
	}
	$published = axismundi_op_transform_object( $post );
	$calls = 0;
	axismundi_op_register_object_assembly( 'Article', 'axismundi_op_assemble_object_view_facts', static function ( array $document ) use ( &$calls ) : array {
		++$calls;
		return axismundi_op_assemble_read_jsonld( $document );
	} );
	if ( null === axismundi_op_get_object_by_uri( $published['id'] ) ) {
		ax_read_assert( 'an unregistered real local post URI is not guessed', ax_read_error( axismundi_op_read_object_jsonld( $published['id'] ), 'ax_op_object_read_not_found' ) );
		$old_identity = axismundi_op_get_object_by_uri( $published['id'] );
		$registered = axismundi_op_register_local_post_object( $post );
		if ( ! is_array( $registered ) ) {
			throw new RuntimeException( 'Could not register the local read fixture.' );
		}
		$local_fixture_identity = array( 'id' => $registered['identity_id'], 'created' => null === $old_identity );
	}
	$local = axismundi_op_read_object_jsonld( $published['id'] );
	ax_read_assert( 'a registered local Article is readable by canonical URI', is_array( $local ) && 'Article' === $local['type'] );
	if ( ! is_array( $local ) ) {
		throw new RuntimeException( is_wp_error( $local ) ? $local->get_error_message() : 'Local read failed.' );
	}
	$payload = $published;
	$payload['id'] = $uris[0];
	$payload['extensionProbe'] = array( 'html' => '<img src=x onerror="alert(1)">', 'nested' => array( 'verbatim' => true ) );
	$payload['tag'] = array( array( 'type' => 'Mention', 'href' => 'https://read-api.example/actors/alice', 'name' => '@alice' ) );
	$row = axismundi_op_store_remote_object( $payload, array( 'etag' => '"read-audit"' ) );
	if ( is_wp_error( $row ) ) {
		throw new RuntimeException( $row->get_error_message() );
	}
	$remote = axismundi_op_read_object_jsonld( $uris[0] );
	if ( ! is_array( $remote ) ) {
		throw new RuntimeException( is_wp_error( $remote ) ? $remote->get_error_code() : 'Remote read failed.' );
	}
	ax_read_assert( 'local and remote Articles execute the same registered JSON-LD assembly', 2 === $calls );
	ax_read_assert( 'local Article retains its map-only publication policy', isset( $local['nameMap'], $local['contentMap'] ) && ! array_key_exists( 'name', $local ) && ! array_key_exists( 'content', $local ) && $published['nameMap'] === $local['nameMap'] && $published['contentMap'] === $local['contentMap'] );
	$note_document = null;
	foreach ( get_posts( array( 'post_type' => 'ax_note', 'post_status' => 'publish', 'posts_per_page' => 50 ) ) as $note_post ) {
		$envelope = axismundi_note_get( $note_post->ID );
		if ( ! is_array( $envelope ) || '' === trim( $note_post->post_title ) ) {
			continue;
		}
		$note = axismundi_op_transform_object( new Axismundi_Note_Source( $envelope, $note_post ) );
		if ( is_array( $note ) && isset( $note['name'], $note['nameMap'], $note['content'], $note['contentMap'] ) ) {
			$note_document = axismundi_op_assemble_read_jsonld( $note );
			break;
		}
	}
	ax_read_assert( 'a local Note through its own source retains both scalar and map members', is_array( $note_document ) && $note['name'] === $note_document['name'] && $note['nameMap'] === $note_document['nameMap'] && $note['content'] === $note_document['content'] && $note['contentMap'] === $note_document['contentMap'] );
	foreach ( array( 'type', 'url', 'attributedTo', 'mediaType', 'sensitive', 'published', 'updated' ) as $field ) {
		ax_read_assert( 'equivalent local and remote Article facts agree on ' . $field, ( $local[ $field ] ?? null ) === ( $remote[ $field ] ?? null ) );
		if ( ( $local[ $field ] ?? null ) !== ( $remote[ $field ] ?? null ) ) {
			printf( "DIFF %s %s\n", $field, wp_json_encode( array( 'local' => $local[ $field ] ?? null, 'remote' => $remote[ $field ] ?? null ) ) );
		}
	}
	$identity = axismundi_op_get_object_by_uri( $uris[0] );
	ax_read_assert( 'integer input means registry identity id, not a WordPress post id', $remote === axismundi_op_read_object_jsonld( (int) $identity['identity_id'] ) );
	ax_read_assert( 'opaque extensions survive verbatim without becoming structured content', ! array_key_exists( 'extensionProbe', $remote ) && false === ( $remote['axUninterpreted']['trusted'] ?? null ) && $payload['extensionProbe'] === ( $remote['axUninterpreted']['members']['extensionProbe'] ?? null ) );
	$context = end( $remote['@context'] );
	ax_read_assert( 'extension and provenance containers are JSON literals, not active remote contexts', '@json' === $context['axUninterpreted']['@type'] && '@json' === $context['axObservation']['@type'] );
	ax_read_assert( 'remote provenance states observation, source and stored observation time', 'remote-observation' === ( $remote['axObservation']['kind'] ?? '' ) && $uris[0] === ( $remote['axObservation']['source_uri'] ?? '' ) && ! empty( $row['fetched_at'] ) && $row['fetched_at'] === ( $remote['axObservation']['fetched_at'] ?? null ) && $row['payload_hash'] === ( $remote['axObservation']['payload_hash'] ?? null ) );
	ax_read_assert( 'structured Mention tags come from the side table', 'Mention' === ( $remote['tag'][0]['type'] ?? '' ) && 'https://read-api.example/actors/alice' === ( $remote['tag'][0]['href'] ?? '' ) );
	axismundi_op_replace_object_mentions( $uris[0], array() );
	$without_mention = axismundi_op_read_object_jsonld( $uris[0] );
	ax_read_assert( 'a removed side-table Mention is not revived from the old payload', empty( $without_mention['tag'] ) );
	$wpdb->update( axismundi_op_remote_objects_table(), array( 'content' => '<p>Structured override.</p>' ), array( 'id' => $row['id'] ) );
	$override = axismundi_op_read_object_jsonld( $uris[0] );
	ax_read_assert( 'flattened content cannot overwrite the received map or synthesize a scalar', ! array_key_exists( 'content', $override ) && $payload['contentMap'] === ( $override['contentMap'] ?? null ) );
	$unchanged = axismundi_op_get_remote_object( $uris[0], false );
	ax_read_assert( 'read API does not mutate or refresh its source snapshot', $row['payload_hash'] === $unchanged['payload_hash'] && $row['fetched_at'] === $unchanged['fetched_at'] );
	$language_cases = array(
		'plain-only' => array( 'name' => 'Plain title', 'summary' => '<p>Plain summary.</p>', 'content' => '<p>Plain body.</p>' ),
		'map-only' => array( 'nameMap' => array( 'en' => 'English', 'fr' => 'French' ), 'summaryMap' => array( 'en' => '<p>Summary.</p>' ), 'contentMap' => array( 'en' => '<p>English.</p>', 'fr' => '<p>French.</p>' ) ),
		'both' => array( 'name' => 'Scalar title', 'nameMap' => array( 'en' => 'Different title' ), 'content' => '<p>Scalar body.</p>', 'contentMap' => array( 'en' => '<p>Different body.</p>', 'fr' => '<p>French body.</p>' ) ),
		'neither' => array(),
	);
	foreach ( $language_cases as $label => $members ) {
		axismundi_op_store_remote_object( array_merge( array( 'id' => $uris[4], 'type' => 'Note', 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) ), $members ) );
		$language_row = axismundi_op_get_remote_object( $uris[4], false );
		$wpdb->update( axismundi_op_remote_objects_table(), array( 'name' => 'Index title', 'content' => '<p>Index body.</p>', 'content_language' => 'de' ), array( 'id' => $language_row['id'] ) );
		$language_doc = axismundi_op_read_object_jsonld( $uris[4] );
		foreach ( array( 'name', 'nameMap', 'summary', 'summaryMap', 'content', 'contentMap' ) as $term ) {
			ax_read_assert( 'remote ' . $label . ' preserves authored presence and value of ' . $term, is_array( $language_doc ) && array_key_exists( $term, $members ) === array_key_exists( $term, $language_doc ) && ( ! array_key_exists( $term, $members ) || $members[ $term ] === $language_doc[ $term ] ) );
		}
	}
	$poll = array( '@context' => 'https://www.w3.org/ns/activitystreams', 'id' => $uris[1], 'type' => 'Question', 'attributedTo' => 'https://read-api.example/actors/alice', 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ), 'oneOf' => array( array( 'type' => 'Note', 'name' => 'Yes' ), array( 'type' => 'Note', 'name' => 'No' ) ), 'anyOf' => array( array( 'type' => 'Note', 'name' => 'Maybe' ) ), 'closed' => false, 'votersCount' => 3 );
	axismundi_op_store_remote_object( $poll );
	$question = axismundi_op_read_object_jsonld( $uris[1] );
	foreach ( array( 'oneOf', 'anyOf', 'closed', 'votersCount' ) as $field ) {
		ax_read_assert( 'remote Question retains ' . $field . ' through its named fallback', is_array( $question ) && array_key_exists( $field, $question ) && $poll[ $field ] === $question[ $field ] && ! empty( $question['axPayloadFallback'][ $field ] ) );
	}
	$private = array( 'id' => $uris[2], 'type' => 'Note', 'content' => '<p>Private.</p>', 'to' => array( 'https://read-api.example/actors/alice' ) );
	axismundi_op_store_remote_object( $private );
	ax_read_assert( 'public disclosure hides a non-public cached object', ax_read_error( axismundi_op_read_object_jsonld( $uris[2] ), 'ax_op_object_read_not_found' ) );
	ax_read_assert( 'elevated requester classes require an explicit trusted grant', ax_read_error( axismundi_op_read_object_jsonld( $uris[2], 'owner' ), 'ax_op_object_read_forbidden' ) && is_array( axismundi_op_read_object_jsonld( $uris[2], 'owner', true ) ) );
	$request = new WP_REST_Request( 'GET', '/axismundi/v1/objects/read' );
	$request->set_query_params( array( 'uri' => $uris[2], 'requester' => 'owner', 'granted' => true ) );
	$response = rest_do_request( $request );
	ax_read_assert( 'HTTP query parameters cannot grant private disclosure', 404 === $response->get_status() );
	$request->set_query_params( array( 'uri' => $uris[0] ) );
	$response = rest_do_request( $request );
	ax_read_assert( 'the public REST route returns JSON-LD with a no-store cache boundary', 200 === $response->get_status() && 0 === strpos( $response->get_headers()['Content-Type'] ?? '', 'application/ld+json' ) && 'no-store' === ( $response->get_headers()['Cache-Control'] ?? '' ) );
	$object = axismundi_op_get_object_by_uri( $uris[0] );
	$wpdb->delete( axismundi_op_objects_table(), array( 'id' => $object['id'] ) );
	ax_read_assert( 'an existing cache URI without its registry row is not guessed', ax_read_error( axismundi_op_read_object_jsonld( $uris[0] ), 'ax_op_object_read_not_found' ) );
	ax_read_assert( 'a wholly unknown URI is not found', ax_read_error( axismundi_op_read_object_jsonld( $base . '/absent' ), 'ax_op_object_read_not_found' ) );
	$tombstone = axismundi_op_store_remote_object( array( 'id' => $uris[3], 'type' => 'Tombstone', 'content' => 'Old secret', 'oldExtension' => 'Old secret' ) );
	$deleted = axismundi_op_read_object_jsonld( $uris[3] );
	ax_read_assert( 'a tombstone cannot revive old content or opaque extensions', is_array( $deleted ) && 'Tombstone' === $deleted['type'] && ! isset( $deleted['content'] ) && ! isset( $deleted['axUninterpreted'] ) );
	ax_read_assert( 'source reads do not perform network requests', 0 === $network_calls );
} catch ( Throwable $error ) {
	ax_read_assert( 'fixture completes: ' . $error->getMessage(), false );
} finally {
	$GLOBALS['axismundi_op_object_assemblies'] = $assemblies;
	if ( is_array( $local_fixture_identity ) ) {
		$wpdb->delete( axismundi_op_objects_table(), array( 'id' => $local_fixture_identity['id'] ) );
	}
	foreach ( $uris as $uri ) {
		axismundi_op_delete_remote_object( $uri );
		$identity = axismundi_op_get_object_by_uri( $uri );
		if ( is_array( $identity ) ) {
			$wpdb->delete( axismundi_op_objects_table(), array( 'id' => $identity['id'] ) );
		}
	}
	remove_filter( 'pre_http_request', $deny_network, PHP_INT_MAX );
}
$failures = count( array_filter( $GLOBALS['ax_read_results'], static fn( bool $value ) : bool => ! $value ) );
printf( "\n== %d checks, %d failed ==\n", count( $GLOBALS['ax_read_results'] ), $failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failures ? 1 : 0 );
}
