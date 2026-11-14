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
	foreach ( array( 'replies', 'likes', 'shares', 'http://fedibird.com/ns#emojiReactions' ) as $field ) { $note[ $field ] = 'https://example.test/untrusted-collection'; }
	$note['bto'] = $note['bcc'] = array( $actor->get_uri() );
	$command = array( 'type' => 'Create', 'actor' => $actor->get_uri(), 'object' => $note );
	$policy = axismundi_op_normative_requirement( 'local-note-attribution' );
	$assert( is_array( $policy ) && 'LOCAL_POLICY' === $policy['level'] && 'ax_op_local_author' === $policy['enforced_by']['error_code'], 'Existing local Note attribution guard has a product-policy record' );
	$effect_tables = array( axismundi_act_activities_table(), axismundi_op_local_objects_table(), axismundi_op_objects_table(), axismundi_op_object_index_table(), axismundi_act_inbox_table() );
	if ( function_exists( 'axismundi_activitypub_bridge_delivery_table' ) ) { $effect_tables[] = axismundi_activitypub_bridge_delivery_table(); }
	$count_effects = static function () use ( $wpdb, $effect_tables ) : array {
		$result = array();
		foreach ( $effect_tables as $table ) { $result[$table] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ); }
		return $result;
	};
	foreach ( array( 'foreign-scalar' => 'https://example.test/other-author', 'singleton-array' => array( $actor->get_uri() ), 'joint-array' => array( $actor->get_uri(), 'https://example.test/collaborator' ) ) as $case => $attribution ) {
		$counts = $count_effects(); $event_count = count( $recorded ); $writes = array();
		$watch_writes = static function ( $sql ) use ( &$writes, $effect_tables ) {
			if ( preg_match( '/^\s*(INSERT|REPLACE|UPDATE|DELETE)\b/i', $sql ) ) {
				foreach ( $effect_tables as $table ) { if ( str_contains( $sql, $table ) ) { $writes[] = $sql; break; } }
			}
			return $sql;
		};
		$invalid = $command; $invalid['object']['attributedTo'] = $attribution;
		add_filter( 'query', $watch_writes );
		try { $denied = axismundi_act_submit_outbox( $user, $actor, $invalid, 'attribution-' . wp_generate_uuid4() ); }
		finally { remove_filter( 'query', $watch_writes ); }
		$assert( is_wp_error( $denied ) && 'ax_op_local_author' === $denied->get_error_code() && 403 === $denied->get_error_data()['status'], 'The attribution guard, not the type gate, rejects ' . $case );
		$assert( array() === $writes && $counts === $count_effects() && $event_count === count( $recorded ), 'Rejected attribution allocates no Object, Activity, receipt or delivery and emits no commit: ' . $case );
	}
	$command['object']['attributedTo'] = $actor->get_uri();
	$created = axismundi_act_submit_outbox( $user, $actor, $command, 'create-audit-' . wp_generate_uuid4() );
	$assert( $created instanceof Axismundi_Activity, 'Create commits local Note and Activity: ' . ( is_wp_error( $created ) ? $created->get_error_message() : '' ) );
	$uri = $created->get_object_uri(); $uris[] = $uri;
	$row = axismundi_op_get_local_object( $uri );
	$assert( 1 === (int) ( axismundi_op_get_object_listing_projection( $uri )['federated'] ?? 0 ), 'Committed outbound Create sets the publication projection' );
	$wpdb->update( axismundi_op_object_index_table(), array( 'federated' => 0 ), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) );
	$start = hrtime( true );
	$assert( axismundi_op_rebuild_object_publication_projection() && 1 === (int) ( axismundi_op_get_object_listing_projection( $uri )['federated'] ?? 0 ), 'Publication projection rebuild repairs a stale bit from the ledger' );
	printf( "Publication projection rebuild: %.2f ms\n", ( hrtime( true ) - $start ) / 1e6 );
	$assert( is_array( $row ) && $uri !== $created->get_uri() && str_contains( $uri, '/objects/' ), 'Object and Create have independent server identities' );
	$assert( $row['document']['id'] === $row['document']['url'] && ! str_contains( $row['document_json'], '<script' ) && count( $row['document']['contentMap'] ) === 2, 'Sanitized current document preserves language maps and unified URL' );
	$assert( $actor->get_uri() === axismundi_act_get_object_attribution( $uri ) && is_array( axismundi_op_get_object_by_uri( $uri ) ), 'Registry and stored attribution agree with committed Create' );
	$assert( ! empty( axismundi_op_mentions_for_object( $uri ) ), 'Mention projection participates' );
	$document = axismundi_op_transform_object( new Axismundi_OP_Local_Source( $row ) );
	$assert( $note['bto'] === $created->get_audience()['bto'] && $note['bcc'] === $created->get_audience()['bcc'] && $note['bto'] === $row['document']['bto'] && $note['bcc'] === $row['document']['bcc'], 'Blind addresses remain available to transport and in the stored Note' );
	$assert( is_array( $document ) && ! array_key_exists( 'bto', $document ) && ! array_key_exists( 'bcc', $document ), 'The public Note document never discloses its blind addresses' );
	foreach ( array( 'replies' => 'axismundi_op_object_replies_url', 'likes' => 'axismundi_op_object_likes_url', 'shares' => 'axismundi_op_object_shares_url', 'http://fedibird.com/ns#emojiReactions' => 'axismundi_op_object_emoji_reactions_url' ) as $field => $factory ) {
		$assert( is_array( $document ) && ( $document[ $field ] ?? '' ) === $factory( $uri ), 'Local Note advertises server-owned collection: ' . $field );
	}
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
	$registered = axismundi_op_get_object_by_uri( $wp_uri );
	$assert( false === axismundi_act_has_outbound_create( $wp_uri ), 'Locally published WordPress source has no outbound Create by default' );
	$assert( is_array( $registered ) && empty( $registered['attributed_to_uri'] ) && ! axismundi_act_get_object_lifecycle( $wp_uri ), 'Source registry admission does not invent publication or attribution' );
	$raw = axismundi_op_register_object( array( 'canonical_uri' => $wp_uri, 'source_owner' => 'wordpress-core', 'source_kind' => 'post', 'source_ref' => $post_id, 'as2_type' => 'Article', 'attributed_to_uri' => $actor->get_uri() ) );
	$assert( is_wp_error( $raw ), 'Direct registry entry cannot override source attribution' );
	$wp_create = axismundi_act_record_activity( array( 'type' => 'Create', 'actor' => $actor->get_uri(), 'object' => $wp_uri ), 'outbound' );
	$assert( $wp_create instanceof Axismundi_Activity && is_array( axismundi_op_get_object_by_uri( $wp_uri ) ), 'Recorded Create admits the matching WordPress source' );
	$wp_reply = axismundi_act_submit_outbox( $user, $actor, array( 'type' => 'Note', 'content' => '<p>Reply to WP</p>', 'inReplyTo' => $wp_uri, 'to' => $note['to'] ) );
	$assert( $wp_reply instanceof Axismundi_Activity, 'C2S Note can reply to a WordPress Object URI' );
	$reply_uri = $wp_reply->get_object_uri(); $uris[] = $reply_uri;
	$assert( $wp_uri === axismundi_op_get_local_object( $reply_uri )['document']['inReplyTo'], 'WordPress reply target is preserved in the local body' );
	$wp_document = axismundi_op_transform_object( get_post( $post_id ) );
	$assert( is_array( $wp_document ) && ! empty( $wp_document['replies'] ), 'WordPress parent advertises its replies collection' );
	$root_request = new WP_REST_Request( 'GET', '/axismundi/activitypub/objects/replies' );
	$root_request->set_param( 'object', $wp_uri );
	$root_response = rest_do_request( $root_request );
	$root_data = $root_response->get_data();
	$page_request = new WP_REST_Request( 'GET', '/axismundi/activitypub/objects/replies' );
	$page_request->set_param( 'object', $wp_uri ); $page_request->set_param( 'page', 1 );
	$page_response = rest_do_request( $page_request );
	$assert( 200 === $root_response->get_status() && $wp_document['replies'] === ( $root_data['id'] ?? '' ) && 200 === $page_response->get_status() && in_array( $reply_uri, $page_response->get_data()['orderedItems'] ?? array(), true ), 'WordPress advertised replies root and page resolve to the C2S child' );
	$patch = array( 'type' => 'Update', 'actor' => $actor->get_uri(), 'object' => array( 'id' => $uri, 'content' => '<p>Revised</p>', 'summary' => null ) );
	$update = axismundi_act_submit_outbox( $user, $actor, $patch, 'update-note' );
	$updated = axismundi_op_get_local_object( $uri );
	$assert( $update instanceof Axismundi_Activity && 'Update' === $update->get_type() && $uri === $updated['document']['id'] && '<p>Revised</p>' === $updated['document']['content'] && ! array_key_exists( 'summary', $updated['document'] ), 'Partial Update preserves identity and removes explicit null' );
	$assert( $updated['document']['to'] === $row['document']['to'] && $updated['document']['tag'] === $row['document']['tag'] && $updated['document']['contentMap'] === $row['document']['contentMap'], 'Partial Update preserves omitted audience, tags and translations' );
	$retry = axismundi_act_submit_outbox( $user, $actor, $patch, 'update-note' );
	$assert( $retry instanceof Axismundi_Activity && $update->get_uri() === $retry->get_uri() && $updated['version'] === axismundi_op_get_local_object( $uri )['version'], 'Update retry does not write a second version' );
	$patch['object']['to'] = array();
	$assert( is_wp_error( axismundi_act_submit_outbox( $user, $actor, $patch ) ), 'Update rejects audience edits' );
	$deleted = axismundi_act_submit_outbox( $user, $actor, array( 'type' => 'Delete', 'actor' => $actor->get_uri(), 'object' => $uri ) );
	$tombstone = axismundi_op_get_local_object( $uri );
	$assert( $deleted instanceof Axismundi_Activity && 'tombstone' === $tombstone['object_status'] && 4 === count( $tombstone['document'] ), 'Delete leaves only the minimal Tombstone body' );
	$assert( ! str_contains( $tombstone['document_json'], 'Stored body' ) && ! str_contains( $tombstone['document_json'], 'attachment' ) && ! str_contains( $tombstone['document_json'], 'summary' ), 'Deleted body, attachments and CW are absent' );
	if ( function_exists( 'axismundi_media_relations_table' ) ) {
		$assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE subject_uri=%s AND provider=%s', axismundi_media_relations_table(), $uri, 'ax-op-local' ) ), 'Tombstone removes media usage links without deleting shared files' );
	}
	// This ledger only grows, so a repeat Delete must return the recorded one rather than
	// record a second event for an Object that is already a Tombstone (FEP-c3ab rule 7).
	$deletes = static function () use ( $wpdb, $uri ) : int {
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE object_uri_hash=%s AND activity_type=%s AND direction=%s',
			axismundi_act_activities_table(), hash( 'sha256', $uri ), 'Delete', 'outbound' ) );
	};
	$before_repeat = $deletes();
	$repeat = axismundi_act_submit_outbox( $user, $actor, array( 'type' => 'Delete', 'actor' => $actor->get_uri(), 'object' => $uri ) );
	$assert( $repeat instanceof Axismundi_Activity && $deleted->get_uri() === $repeat->get_uri() && $before_repeat === $deletes(),
		'A repeat Delete returns the recorded Delete and records no second one' );
	// An Activity URI is not a deletable Object, and saying so is not an authority answer.
	$not_object = axismundi_act_submit_outbox( $user, $actor, array( 'type' => 'Delete', 'actor' => $actor->get_uri(), 'object' => $deleted->get_uri() ) );
	$assert( is_wp_error( $not_object ) && 'ax_c2s_delete_target' === $not_object->get_error_code()
		&& 422 === ( $not_object->get_error_data()['status'] ?? 0 ),
		'Delete naming an Activity URI is refused as a target, not as authority' );

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
printf( "== %d checks, 0 failed ==\n", $checks );
