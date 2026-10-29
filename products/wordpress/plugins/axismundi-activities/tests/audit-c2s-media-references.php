<?php
/** REST-dispatch media reuse and identity checks; no network or binary upload. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; };
$original_user = get_current_user_id(); $actor = null; $user = 0; $attachment = 0; $post_id = 0; $uris = array(); $events = array();
$before = $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_activities_table() . ' ORDER BY id', ARRAY_A );
foreach ( $wpdb->get_results( 'SELECT identity_id,principal_id FROM ' . axismundi_actors_managers_table() . " WHERE principal_kind='wp_user'", ARRAY_A ) as $grant ) {
	$candidate = axismundi_actors_get_by_identity( (int) $grant['identity_id'] );
	if ( $candidate instanceof Axismundi_Actor && true === axismundi_act_outbox_authorize( (int) $grant['principal_id'], $candidate ) ) { $actor = $candidate; $user = (int) $grant['principal_id']; break; }
}
$assert( $actor instanceof Axismundi_Actor, 'Existing Account operator available' );
$deny_edit = static function ( $caps, $cap, $id, $args ) use ( &$attachment ) { return 'edit_post' === $cap && $attachment && (int) ( $args[0] ?? 0 ) === $attachment ? array( 'do_not_allow' ) : $caps; };
$observe = static function ( $activity ) use ( &$events, &$uris ) { $events[] = $activity->get_uri(); $uris[] = $activity->get_object_uri(); };
add_filter( 'map_meta_cap', $deny_edit, 999, 4 );
add_action( 'axismundi_act_activity_recorded', $observe, 999 );
$remote = 'https://media-fixture.example/posts/' . wp_generate_uuid4();
$file = 'https://media-fixture.example/files/movie.mp4';
$submit = static function ( string $url, bool $public = true ) use ( $actor ) {
	$request = new WP_REST_Request( 'POST', '/axismundi/v1/actors/' . $actor->get_uuid() . '/outbox' );
	$request->set_header( 'Content-Type', 'application/activity+json' );
	$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	$request->set_body( wp_json_encode( array( 'type' => 'Note', 'content' => '<p>Shared media</p>', 'attachment' => array( array( 'type' => 'Image', 'url' => $url ) ), 'to' => array( $public ? 'https://www.w3.org/ns/activitystreams#Public' : $actor->get_uri() ) ) ) );
	return rest_do_request( $request );
};
try {
	wp_set_current_user( $user );
	$attachment = wp_insert_attachment( array( 'post_title' => 'Shared attachment fixture', 'post_author' => 0, 'post_status' => 'private', 'post_mime_type' => 'image/jpeg' ) );
	$assert( is_int( $attachment ) && $attachment > 0 && ! user_can( $user, 'edit_post', $attachment ), 'Submitter cannot edit another author private/unattached media fixture' );
	$relative = '2099/01/c2s-' . wp_generate_uuid4() . '.jpg';
	$small = str_replace( '.jpg', '-240x300.jpg', wp_basename( $relative ) );
	update_post_meta( $attachment, '_wp_attached_file', $relative );
	wp_update_attachment_metadata( $attachment, array( 'file' => $relative, 'width' => 800, 'height' => 1000, 'sizes' => array( 'medium' => array( 'file' => $small, 'width' => 240, 'height' => 300, 'mime-type' => 'image/jpeg' ) ) ) );
	$base = trailingslashit( wp_get_upload_dir()['baseurl'] );
	$original = $base . $relative;
	$urls = array( 'original' => $original, 'rendition' => $base . dirname( $relative ) . '/' . $small, 'query' => $original . '?v=2', 'protocol-relative' => preg_replace( '#^https?:#', '', $original ), 'photon' => 'https://i0.wp.com/' . preg_replace( '#^https?://#', '', $original ) . '?resize=240,300' );
	foreach ( $urls as $label => $url ) {
		$assert( $attachment === axismundi_op_local_attachment_id( $url ), 'Media identity resolves: ' . $label );
		$response = $submit( $url );
		$assert( 201 === $response->get_status(), 'Non-editor can reuse shared media: ' . $label . ' ' . wp_json_encode( $response->get_data() ) );
		$activity = axismundi_act_get( $response->get_headers()['Location'] );
		$id = $wpdb->get_var( $wpdb->prepare( 'SELECT object_attachment_id FROM %i WHERE subject_uri=%s AND provider=%s', axismundi_media_relations_table(), $activity->get_object_uri(), 'ax-op-local' ) );
		$assert( $attachment === (int) $id, 'used-in stores the original attachment ID: ' . $label );
	}
	$used = axismundi_media_relations_used_in( $attachment, $user );
	$assert( 5 === count( array_filter( $used, static fn( $row ) => 'ax-op-local' === $row['provider'] ) ), 'All five URI forms appear in used-in' );
	$foreign = str_replace( wp_parse_url( $original, PHP_URL_HOST ), 'unrelated.example', $original );
	$assert( 0 === axismundi_op_local_attachment_id( $foreign ), 'An unrelated host with the same path is not local media' );
	$stored = axismundi_op_store_remote_object( array( 'id' => $remote, 'type' => 'Video', 'attributedTo' => 'https://media-fixture.example/actors/channel', 'url' => $file, 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) ) );
	$assert( ! is_wp_error( $stored ) && is_array( axismundi_op_get_remote_object( $remote, false ) ), 'Remote publication fixture is cached' );
	$denied = $submit( $remote );
	$assert( 422 === $denied->get_status() && 'ax_op_attachment_publication' === ( $denied->get_data()['code'] ?? '' ), 'Cached remote Object ID is rejected as a file URL' );
	$file_response = $submit( $file );
	$assert( 201 === $file_response->get_status(), 'The same publication media file URL remains attachable' );
	$local_uri = axismundi_act_get( $file_response->get_headers()['Location'] )->get_object_uri();
	$private_response = $submit( $file, false );
	$assert( 201 === $private_response->get_status(), 'Private local Object fixture is created through C2S' );
	$private_uri = axismundi_act_get( $private_response->get_headers()['Location'] )->get_object_uri();
	$owner = static fn() => 'fixture';
	add_filter( 'axismundi_op_post_lifecycle_owner', $owner, PHP_INT_MAX );
	try { $post_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $user, 'post_title' => 'Attachment document URL fixture' ) ); }
	finally { remove_filter( 'axismundi_op_post_lifecycle_owner', $owner, PHP_INT_MAX ); }
	$assert( is_int( $post_id ) && $post_id > 0, 'WP Object fixture exists without automatic federation' );
	$wp_uri = axismundi_op_post_object_uri( get_post( $post_id ) ); $uris[] = $wp_uri;
	foreach ( array( 'local' => $local_uri, 'local-query' => $local_uri . '?v=2', 'local-fragment' => $local_uri . '#preview', 'private-local' => $private_uri, 'wp-post' => $wp_uri, 'attachment-page' => add_query_arg( 'attachment_id', $attachment, home_url( '/' ) ) ) as $label => $document_uri ) {
		$count = count( $events );
		$denied = $submit( $document_uri );
		$assert( 422 === $denied->get_status() && 'ax_op_attachment_publication' === ( $denied->get_data()['code'] ?? '' ) && $count === count( $events ), 'Known document URL rejected before recording: ' . $label );
	}
	$assert( ! user_can( $user, 'edit_post', $attachment ) && 0 === (int) get_post_field( 'post_author', $attachment ), 'Reuse grants no edit permission and changes no uploader' );
} finally {
	remove_action( 'axismundi_act_activity_recorded', $observe, 999 );
	remove_filter( 'map_meta_cap', $deny_edit, 999 );
	if ( $post_id ) {
		$owner = static fn() => 'fixture';
		add_filter( 'axismundi_op_post_lifecycle_owner', $owner, PHP_INT_MAX );
		try { wp_delete_post( $post_id, true ); }
		finally { remove_filter( 'axismundi_op_post_lifecycle_owner', $owner, PHP_INT_MAX ); }
	}
	if ( $attachment ) { wp_delete_attachment( $attachment, true ); }
	axismundi_op_delete_remote_object( $remote );
	$uris[] = $remote;
	foreach ( $events as $uri ) { $a = axismundi_act_get( $uri ); if ( $a ) { $wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $a->get_id() ) ); } $wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $uri ) ); }
	foreach ( array_unique( $uris ) as $uri ) {
		$wpdb->delete( axismundi_media_relations_table(), array( 'subject_uri' => $uri, 'provider' => 'ax-op-local' ) );
		foreach ( array( axismundi_op_local_objects_table() => 'object_uri', axismundi_op_objects_table() => 'canonical_uri', axismundi_op_object_relations_table() => 'source_object_uri', axismundi_op_object_mentions_table() => 'source_object_uri', axismundi_op_tag_references_table() => 'source_uri', axismundi_op_term_usage_table() => 'source_uri', axismundi_op_thread_edges_table() => 'child_uri' ) as $table => $column ) { $wpdb->delete( $table, array( $column => $uri ) ); }
		$wpdb->delete( axismundi_op_object_index_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) );
	}
	wp_set_current_user( $original_user );
}
$assert( $before === $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_activities_table() . ' ORDER BY id', ARRAY_A ), 'Existing ledger contents preserved' );
printf( "PASS: %d C2S media reference checks\n", $checks );
