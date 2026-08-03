<?php
/**
 * Phase 4a - remote object repository regression (dev-only).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/object-relations.php';
require_once dirname( __DIR__ ) . '/includes/remote-objects.php';

$ax_remote_results = array();
$ax_remote_uris    = array(
	'https://remote.example/objects/phase-4a-note',
	'https://remote.example/objects/phase-4a-tombstone',
);
$GLOBALS['ax_remote_orphans_purged'] = 0;

/** @param array<bool> $results Results. @param string $label Label. @param bool $condition Condition. */
function ax_remote_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Record the maintenance event without making the projection cleaner depend on logging. */
function ax_remote_orphans_purged( int $count ) : void {
	$GLOBALS['ax_remote_orphans_purged'] += $count;
}

try {
	global $wpdb;
	$installed = axismundi_op_install();
	$table     = axismundi_op_remote_objects_table();
	$index_table = axismundi_op_object_index_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture verifies the custom schema.
	$index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'object_uri_hash'", ARRAY_A );
	ax_remote_assert( $ax_remote_results, 'the schema installs with a unique URI hash and records its verified set version', $installed && AXISMUNDI_OP_DB_VERSION === (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION ) && ! empty( $index ) && 0 === (int) $index[0]['Non_unique'] );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture verifies the projection schema.
	$listing_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$index_table}" );
	ax_remote_assert( $ax_remote_results, 'the listing projection has one URI identity and queryable public, lifecycle, source, and Group-context state', in_array( 'object_uri_hash', $listing_columns, true ) && in_array( 'publicly_listable', $listing_columns, true ) && in_array( 'object_status', $listing_columns, true ) && in_array( 'source', $listing_columns, true ) && in_array( 'has_group_context', $listing_columns, true ) && in_array( 'primary_group_uri_hash', $listing_columns, true ) );

	ax_remote_assert(
		$ax_remote_results,
		'canonical URI validation rejects credentials and non-HTTP identifiers',
		is_wp_error( axismundi_op_remote_object_uri( 'https://user:pass@example.com/object' ) )
			&& is_wp_error( axismundi_op_remote_object_uri( 'urn:example:object' ) )
	);
	$activity = axismundi_op_store_remote_object( array( 'id' => 'https://remote.example/activities/1', 'type' => 'Create' ) );
	$actor    = axismundi_op_store_remote_object( array( 'id' => 'https://remote.example/users/alice', 'type' => 'Person' ) );
	ax_remote_assert( $ax_remote_results, 'Activity and Actor documents are rejected instead of crossing repository ownership', is_wp_error( $activity ) && is_wp_error( $actor ) && 'ax_op_remote_type' === $activity->get_error_code() && 'ax_op_remote_type' === $actor->get_error_code() );

	$payload = array(
		'@context'     => 'https://www.w3.org/ns/activitystreams',
		'id'           => $ax_remote_uris[0],
		'type'         => array( 'Note', 'https://example.com/Extension' ),
		'attributedTo' => array( array( 'id' => 'https://remote.example/users/alice' ) ),
		'inReplyTo'    => 'https://remote.example/objects/parent',
		'url'          => array( 'type' => 'Link', 'href' => 'https://remote.example/@alice/notes/1' ),
		'name'         => '<b>Remote title</b>',
		'summary'      => '<p>Summary</p><script>alert(1)</script>',
		'content'      => '<p>Hello <strong>remote</strong>.</p><script>alert(2)</script>',
		'contentMap'   => array( 'ko-KR' => '<p>안녕하세요.</p>' ),
		'mediaType'    => 'text/html',
		'published'    => '2026-07-14T10:00:00Z',
		'to'           => array( 'https://www.w3.org/ns/activitystreams#Public' ),
	);
	$stored  = axismundi_op_store_remote_object( $payload, array( 'etag' => '"phase-4a"' ) );
	ax_remote_assert(
		$ax_remote_results,
		'a Note snapshot stores canonical identity, scalar relation URIs, and response validators',
		is_array( $stored ) && 'Note' === $stored['object_type']
			&& 'https://remote.example/users/alice' === $stored['attributed_to_uri']
			&& 'https://remote.example/objects/parent' === $stored['in_reply_to_uri']
			&& '"phase-4a"' === $stored['etag']
	);
	ax_remote_assert(
		$ax_remote_results,
		'normalized display text is sanitized while the decoded source payload remains available',
		is_array( $stored ) && 'Remote title' === $stored['name']
			&& false === strpos( (string) $stored['summary'], '<script' )
			&& false === strpos( (string) $stored['content'], '<script' )
			&& isset( $stored['payload']['@context'] )
			&& 'ko-KR' === $stored['content_language']
	);
	ax_remote_assert( $ax_remote_results, 'an undeclared sensitive member remains NULL rather than becoming false', is_array( $stored ) && null === $stored['is_sensitive'] );
	$projection = axismundi_op_get_object_listing_projection( $ax_remote_uris[0] );
	ax_remote_assert(
		$ax_remote_results,
		'storing a public remote Object refreshes its queryable listing projection without copying payload JSON',
		is_array( $projection )
			&& 1 === (int) $projection['publicly_listable']
			&& 0 === (int) $projection['has_group_context']
			&& null === $projection['primary_group_uri_hash']
			&& ! array_key_exists( 'payload_json', $projection )
	);

	$first_id              = is_array( $stored ) ? (int) $stored['id'] : 0;
	$payload['content']     = '<p>Updated observation.</p>';
	$payload['sensitive']   = false;
	$updated                = axismundi_op_store_remote_object( $payload );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture counts its own URI row.
	$row_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE object_uri_hash = %s", hash( 'sha256', $ax_remote_uris[0] ) ) );
	ax_remote_assert( $ax_remote_results, 'upserting the same URI is idempotent and preserves explicit sensitive=false', is_array( $updated ) && $first_id === (int) $updated['id'] && 1 === $row_count && 0 === (int) $updated['is_sensitive'] && false !== strpos( (string) $updated['content'], 'Updated observation' ) );
	$wpdb->delete( $index_table, array( 'object_uri_hash' => hash( 'sha256', $ax_remote_uris[0] ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- simulate the pre-v8 cache row that upgrade backfill must repair.
	update_option( AXISMUNDI_OP_DB_VERSION_OPTION, '7', false );
	$backfilled = axismundi_op_install();
	$projection = axismundi_op_get_object_listing_projection( $ax_remote_uris[0] );
	ax_remote_assert( $ax_remote_results, 'the v8 upgrade backfills existing remote cache rows, not only future stores', $backfilled && AXISMUNDI_OP_DB_VERSION === (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION ) && is_array( $projection ) && 1 === (int) $projection['publicly_listable'] );
	$wpdb->update( $index_table, array( 'source' => '' ), array( 'object_uri_hash' => hash( 'sha256', $ax_remote_uris[0] ) ), array( '%s' ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- simulate the short-lived v9 writer before source fallback was fixed.
	update_option( AXISMUNDI_OP_DB_VERSION_OPTION, '9', false );
	$normalized = axismundi_op_install();
	$projection = axismundi_op_get_object_listing_projection( $ax_remote_uris[0] );
	ax_remote_assert( $ax_remote_results, 'the v10 upgrade classifies v9 blank-source rows as remote before future sweeps', $normalized && is_array( $projection ) && 'remote' === (string) $projection['source'] );

	$bad = axismundi_op_store_remote_object( array( 'id' => $ax_remote_uris[0] ) );
	$after_bad = axismundi_op_get_remote_object( $ax_remote_uris[0] );
	ax_remote_assert( $ax_remote_results, 'invalid refresh input returns an error and preserves the last good snapshot', is_wp_error( $bad ) && is_array( $after_bad ) && (string) $updated['payload_hash'] === (string) $after_bad['payload_hash'] );

	$oversized = axismundi_op_store_remote_object(
		array(
			'id'      => 'https://remote.example/objects/too-large',
			'type'    => 'Note',
			'content' => str_repeat( 'x', AXISMUNDI_OP_REMOTE_PAYLOAD_MAX + 1 ),
		)
	);
	ax_remote_assert( $ax_remote_results, 'payloads over the one MiB repository cap are rejected before writing', is_wp_error( $oversized ) && 'ax_op_remote_payload_size' === $oversized->get_error_code() );

	$tombstone = axismundi_op_store_remote_object(
		array(
			'id'     => $ax_remote_uris[1],
			'type'   => 'Tombstone',
			'formerType' => 'Note',
			'deleted' => '2026-07-14T11:00:00Z',
		)
	);
	ax_remote_assert( $ax_remote_results, 'Tombstone observations are retained as lifecycle state rather than treated as missing', is_array( $tombstone ) && 'tombstone' === $tombstone['object_status'] );
	$tombstone_projection = axismundi_op_get_object_listing_projection( $ax_remote_uris[1] );
	ax_remote_assert( $ax_remote_results, 'a Tombstone refreshes the same projection to non-listable state', is_array( $tombstone_projection ) && 0 === (int) $tombstone_projection['publicly_listable'] );

	/*
	 * Why an Object is unlistable, and who answers for the row.
	 *
	 * A tombstone and a private Object are both absent from every card list, and a reader is owed
	 * different things by each — a thread can say an Object was deleted, and must say nothing at
	 * all about one it may not see. Both were `publicly_listable = 0` and nothing else, so the
	 * distinction would have had to be fetched from the snapshot the projection exists to avoid
	 * reading.
	 */
	$ax_remote_tomb_projection = axismundi_op_get_object_listing_projection( $ax_remote_uris[1] );
	$ax_remote_live_projection = axismundi_op_get_object_listing_projection( $ax_remote_uris[0] );
	ax_remote_assert(
		$ax_remote_results,
		'the projection records why an Object is unlistable, not only that it is',
		is_array( $ax_remote_tomb_projection ) && is_array( $ax_remote_live_projection )
			&& 'tombstone' === (string) $ax_remote_tomb_projection['object_status']
			&& 0 === (int) $ax_remote_tomb_projection['publicly_listable']
			&& 'active' === (string) $ax_remote_live_projection['object_status']
			&& 'remote' === (string) $ax_remote_live_projection['source']
	);

	/*
	 * A rebuild has to remove as well as write.
	 *
	 * A snapshot deleted while the projection was not updated leaves a row every later rebuild
	 * preserves, and a candidate query joining the index then counts an Object that is not there —
	 * which is the one property this projection exists to provide. Ten such rows were in the
	 * development database when this was written.
	 */
	$ax_remote_orphan_uri = 'https://remote.example/objects/phase-4a-orphan';
	$ax_remote_uris[]     = $ax_remote_orphan_uri;
	axismundi_op_store_remote_object( array( 'id' => $ax_remote_orphan_uri, 'type' => 'Note', 'content' => 'orphan', 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) ) );
	$wpdb->update( $index_table, array( 'source' => '' ), array( 'object_uri_hash' => hash( 'sha256', $ax_remote_orphan_uri ) ), array( '%s' ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- v9 blank-source orphan fixture.
	$wpdb->delete( $table, array( 'object_uri_hash' => hash( 'sha256', $ax_remote_orphan_uri ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture removes a snapshot behind the projection's back.
	$ax_remote_orphan_before = axismundi_op_get_object_listing_projection( $ax_remote_orphan_uri );
	update_option( AXISMUNDI_OP_DB_VERSION_OPTION, '9', false );
	axismundi_op_install();
	ax_remote_assert(
		$ax_remote_results,
		'the v10 upgrade normalizes and drops a blank-source row this store no longer answers for',
		is_array( $ax_remote_orphan_before )
			&& null === axismundi_op_get_object_listing_projection( $ax_remote_orphan_uri )
			// And leaves the rows it does answer for, or the sweep would be emptying the table.
			&& is_array( axismundi_op_get_object_listing_projection( $ax_remote_uris[0] ) )
			&& is_array( axismundi_op_get_object_listing_projection( $ax_remote_uris[1] ) )
	);
	$ax_remote_daily_orphan_uri = 'https://remote.example/objects/phase-4a-daily-orphan';
	$ax_remote_uris[]           = $ax_remote_daily_orphan_uri;
	axismundi_op_store_remote_object( array( 'id' => $ax_remote_daily_orphan_uri, 'type' => 'Note', 'content' => 'daily orphan', 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ) ) );
	$wpdb->delete( $table, array( 'object_uri_hash' => hash( 'sha256', $ax_remote_daily_orphan_uri ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture creates a post-upgrade orphan outside the normal delete path.
	add_action( 'axismundi_op_remote_listing_projection_orphans_purged', 'ax_remote_orphans_purged' );
	axismundi_op_remote_objects_daily_maintenance();
	remove_action( 'axismundi_op_remote_listing_projection_orphans_purged', 'ax_remote_orphans_purged' );
	ax_remote_assert(
		$ax_remote_results,
		'daily remote maintenance reconciles post-upgrade orphan rows and reports the count to observers',
		null === axismundi_op_get_object_listing_projection( $ax_remote_daily_orphan_uri ) && $GLOBALS['ax_remote_orphans_purged'] >= 1
	);

	$deleted = axismundi_op_delete_remote_object( $ax_remote_uris[0] );
	ax_remote_assert( $ax_remote_results, 'cache deletion removes only the addressed observation and its listing projection', $deleted && null === axismundi_op_get_remote_object( $ax_remote_uris[0] ) && null === axismundi_op_get_object_listing_projection( $ax_remote_uris[0] ) && null !== axismundi_op_get_remote_object( $ax_remote_uris[1] ) );
} finally {
	remove_action( 'axismundi_op_remote_listing_projection_orphans_purged', 'ax_remote_orphans_purged' );
	foreach ( $ax_remote_uris as $ax_remote_uri ) {
		axismundi_op_delete_remote_object( $ax_remote_uri );
	}
}

$ax_remote_failures = count( array_filter( $ax_remote_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_remote_results ), $ax_remote_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_remote_failures > 0 ? 1 : 0 );
}
exit( $ax_remote_failures > 0 ? 1 : 0 );
