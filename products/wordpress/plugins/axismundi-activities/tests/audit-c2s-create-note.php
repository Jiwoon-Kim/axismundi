<?php
/** Local Note atomicity and WordPress index admission. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; };
$actor = null; $user = 0; $uris = array(); $post_id = 0;
$before = $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_activities_table() . ' ORDER BY id', ARRAY_A );
foreach ( $wpdb->get_results( 'SELECT m.identity_id,m.principal_id FROM ' . axismundi_actors_managers_table() . " m JOIN {$wpdb->users} u ON u.ID=m.principal_id WHERE m.principal_kind='wp_user'", ARRAY_A ) as $r ) {
	$candidate = axismundi_actors_get_by_identity( (int) $r['identity_id'] );
	if ( $candidate instanceof Axismundi_Actor && true === axismundi_act_outbox_authorize( (int) $r['principal_id'], $candidate ) ) { $actor = $candidate; $user = (int) $r['principal_id']; break; }
}
$assert( $actor instanceof Axismundi_Actor, 'Existing Account operator available' );
$owner = static fn() => 'fixture';
add_filter( 'axismundi_op_post_lifecycle_owner', $owner, PHP_INT_MAX );
$recorded = array();
$observe = static function ( $activity ) use ( &$recorded ) { $recorded[] = $activity->get_uri(); };
add_action( 'axismundi_act_activity_recorded', $observe, 999 );
try {
	$note = array( 'type' => 'Note', 'content' => '<p>Stored body<script>bad()</script></p>', 'summary' => 'CW', 'contentMap' => array( 'ko' => '<p>본문</p>', 'en' => '<p>Body</p>' ), 'tag' => array( array( 'type' => 'Mention', 'href' => $actor->get_uri(), 'name' => '@fixture' ) ), 'attachment' => array( array( 'type' => 'Image', 'url' => 'https://example.test/image.png' ) ), 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) );
	$command = array( 'type' => 'Create', 'actor' => $actor->get_uri(), 'object' => $note );
	$created = axismundi_act_submit_outbox( $user, $actor, $command, 'create-audit-' . wp_generate_uuid4() );
	$assert( $created instanceof Axismundi_Activity, 'Create commits local Note and Activity: ' . ( is_wp_error( $created ) ? $created->get_error_message() : '' ) );
	$uri = $created->get_object_uri(); $uris[] = $uri;
	$row = axismundi_op_get_local_object( $uri );
	$assert( is_array( $row ) && $uri !== $created->get_uri() && str_contains( $uri, '/objects/' ), 'Object and Create have independent server identities' );
	$assert( $row['document']['id'] === $row['document']['url'] && ! str_contains( $row['document_json'], '<script' ) && count( $row['document']['contentMap'] ) === 2, 'Sanitized current document preserves language maps and unified URL' );
	$assert( $actor->get_uri() === axismundi_act_get_object_attribution( $uri ) && is_array( axismundi_op_get_object_by_uri( $uri ) ), 'Registry and stored attribution agree with committed Create' );
	$assert( ! empty( axismundi_op_mentions_for_object( $uri ) ), 'Mention projection participates' );
	$faults = array( 'body' => 'ax_local_objects', 'registry' => 'ax_objects', 'relations' => 'ax_object_relations', 'tags' => 'ax_tag_references', 'listing' => 'ax_object_index' );
	if ( function_exists( 'axismundi_media_relations_table' ) ) { $faults['media'] = substr( axismundi_media_relations_table(), strlen( $wpdb->prefix ) ); }
	foreach ( $faults as $label => $table ) {
		$counts = array( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_act_activities_table() ), (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_op_local_objects_table() ), (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_op_objects_table() ) );
		$events = count( $recorded ); $hit = false;
		$fault = static function ( $sql ) use ( $wpdb, $table, &$hit ) { if ( ! $hit && preg_match( '/^(INSERT|REPLACE)\s+INTO\s+`?' . preg_quote( $wpdb->prefix . $table, '/' ) . '`?\s/i', $sql ) ) { $hit = true; return 'SELECT * FROM ax_c2s_deliberately_missing_table'; } return $sql; };
		$bad = $command; $bad['object']['inReplyTo'] = $uri;
		$errors = $wpdb->suppress_errors(); add_filter( 'query', $fault );
		try { $result = axismundi_act_submit_outbox( $user, $actor, $bad, 'failure-' . wp_generate_uuid4() ); }
		finally { remove_filter( 'query', $fault ); $wpdb->suppress_errors( $errors ); }
		$assert( $hit && is_wp_error( $result ), $label . ' failure is exercised, not a vacuous pass' );
		$after = array( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_act_activities_table() ), (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_op_local_objects_table() ), (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_op_objects_table() ) );
		$assert( $counts === $after && count( $recorded ) === $events, $label . ' failure rolls back both identities and emits no committed event' );
	}
	$post_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $user, 'post_title' => 'Unpublished federation fixture' ) );
	$wp_uri = axismundi_op_post_object_uri( get_post( $post_id ) ); $uris[] = $wp_uri;
	$assert( null === axismundi_op_get_object_by_uri( $wp_uri ), 'save_post alone cannot enter the Object registry' );
	$raw = axismundi_op_register_object( array( 'canonical_uri' => $wp_uri, 'source_owner' => 'wordpress-core', 'source_kind' => 'post', 'source_ref' => $post_id, 'as2_type' => 'Article', 'attributed_to_uri' => $actor->get_uri() ) );
	$assert( is_wp_error( $raw ), 'Direct registry entry cannot bypass the Create gate' );
	$wp_create = axismundi_act_record_activity( array( 'type' => 'Create', 'actor' => $actor->get_uri(), 'object' => $wp_uri ), 'outbound' );
	$assert( $wp_create instanceof Axismundi_Activity && is_array( axismundi_op_get_object_by_uri( $wp_uri ) ), 'Recorded Create admits the matching WordPress source' );
	$deleted = axismundi_act_submit_outbox( $user, $actor, array( 'type' => 'Delete', 'actor' => $actor->get_uri(), 'object' => $uri ) );
	$tombstone = axismundi_op_get_local_object( $uri );
	$assert( $deleted instanceof Axismundi_Activity && 'tombstone' === $tombstone['object_status'] && 4 === count( $tombstone['document'] ), 'Delete leaves only the minimal Tombstone body' );
	$assert( ! str_contains( $tombstone['document_json'], 'Stored body' ) && ! str_contains( $tombstone['document_json'], 'attachment' ) && ! str_contains( $tombstone['document_json'], 'summary' ), 'Deleted body, attachments and CW are absent' );
	if ( function_exists( 'axismundi_media_relations_table' ) ) {
		$assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE subject_uri=%s AND provider=%s', axismundi_media_relations_table(), $uri, 'ax-op-local' ) ), 'Tombstone removes media usage links without deleting shared files' );
	}
	$stale = axismundi_op_tombstone_local_object( $row, $actor->get_uri() );
	$assert( is_wp_error( $stale ) && 'ax_op_local_version' === $stale->get_error_code(), 'Stale body version cannot overwrite current state' );
} finally {
	remove_action( 'axismundi_act_activity_recorded', $observe, 999 );
	if ( $post_id ) { wp_delete_post( $post_id, true ); }
	remove_filter( 'axismundi_op_post_lifecycle_owner', $owner, PHP_INT_MAX );
	foreach ( $recorded as $activity_uri ) { $a = axismundi_act_get( $activity_uri ); if ( $a ) { $wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $a->get_id() ) ); } $wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $activity_uri ) ); }
	foreach ( $uris as $uri ) {
		if ( function_exists( 'axismundi_media_relations_table' ) ) { $wpdb->delete( axismundi_media_relations_table(), array( 'subject_uri' => $uri, 'provider' => 'ax-op-local' ) ); }
		foreach ( array( axismundi_op_local_objects_table() => 'object_uri', axismundi_op_objects_table() => 'canonical_uri', axismundi_op_object_relations_table() => 'source_object_uri', axismundi_op_object_mentions_table() => 'source_object_uri', axismundi_op_tag_references_table() => 'source_uri', axismundi_op_term_usage_table() => 'source_uri', axismundi_op_thread_edges_table() => 'child_uri' ) as $table => $column ) { $wpdb->delete( $table, array( $column => $uri ) ); }
		$wpdb->delete( axismundi_op_object_index_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) );
	}
}
$assert( $before === $wpdb->get_results( 'SELECT * FROM ' . axismundi_act_activities_table() . ' ORDER BY id', ARRAY_A ), 'Existing ledger rows preserved' );
printf( "PASS: %d Create(Note) storage checks\n", $checks );
