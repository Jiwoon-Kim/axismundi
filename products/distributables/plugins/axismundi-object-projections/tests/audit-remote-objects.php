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

/** @param array<bool> $results Results. @param string $label Label. @param bool $condition Condition. */
function ax_remote_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	global $wpdb;
	$installed = axismundi_op_install();
	$table     = axismundi_op_remote_objects_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture verifies the custom schema.
	$index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'object_uri_hash'", ARRAY_A );
	ax_remote_assert( $ax_remote_results, 'the schema installs with a unique URI hash and records its verified set version', $installed && AXISMUNDI_OP_DB_VERSION === (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION ) && ! empty( $index ) && 0 === (int) $index[0]['Non_unique'] );

	ax_remote_assert(
		$ax_remote_results,
		'canonical URI validation rejects credentials and non-HTTP identifiers',
		is_wp_error( axismundi_op_remote_object_uri( 'https://user:pass@example.com/object' ) )
			&& is_wp_error( axismundi_op_remote_object_uri( 'urn:example:object' ) )
	);
	$activity = axismundi_op_remote_object_store( array( 'id' => 'https://remote.example/activities/1', 'type' => 'Create' ) );
	$actor    = axismundi_op_remote_object_store( array( 'id' => 'https://remote.example/users/alice', 'type' => 'Person' ) );
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
	);
	$stored  = axismundi_op_remote_object_store( $payload, array( 'etag' => '"phase-4a"' ) );
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

	$first_id              = is_array( $stored ) ? (int) $stored['id'] : 0;
	$payload['content']     = '<p>Updated observation.</p>';
	$payload['sensitive']   = false;
	$updated                = axismundi_op_remote_object_store( $payload );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture counts its own URI row.
	$row_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE object_uri_hash = %s", hash( 'sha256', $ax_remote_uris[0] ) ) );
	ax_remote_assert( $ax_remote_results, 'upserting the same URI is idempotent and preserves explicit sensitive=false', is_array( $updated ) && $first_id === (int) $updated['id'] && 1 === $row_count && 0 === (int) $updated['is_sensitive'] && false !== strpos( (string) $updated['content'], 'Updated observation' ) );

	$bad = axismundi_op_remote_object_store( array( 'id' => $ax_remote_uris[0] ) );
	$after_bad = axismundi_op_remote_object_get( $ax_remote_uris[0] );
	ax_remote_assert( $ax_remote_results, 'invalid refresh input returns an error and preserves the last good snapshot', is_wp_error( $bad ) && is_array( $after_bad ) && (string) $updated['payload_hash'] === (string) $after_bad['payload_hash'] );

	$oversized = axismundi_op_remote_object_store(
		array(
			'id'      => 'https://remote.example/objects/too-large',
			'type'    => 'Note',
			'content' => str_repeat( 'x', AXISMUNDI_OP_REMOTE_PAYLOAD_MAX + 1 ),
		)
	);
	ax_remote_assert( $ax_remote_results, 'payloads over the one MiB repository cap are rejected before writing', is_wp_error( $oversized ) && 'ax_op_remote_payload_size' === $oversized->get_error_code() );

	$tombstone = axismundi_op_remote_object_store(
		array(
			'id'     => $ax_remote_uris[1],
			'type'   => 'Tombstone',
			'formerType' => 'Note',
			'deleted' => '2026-07-14T11:00:00Z',
		)
	);
	ax_remote_assert( $ax_remote_results, 'Tombstone observations are retained as lifecycle state rather than treated as missing', is_array( $tombstone ) && 'tombstone' === $tombstone['object_status'] );

	$deleted = axismundi_op_remote_object_delete( $ax_remote_uris[0] );
	ax_remote_assert( $ax_remote_results, 'cache deletion removes only the addressed local observation', $deleted && null === axismundi_op_remote_object_get( $ax_remote_uris[0] ) && null !== axismundi_op_remote_object_get( $ax_remote_uris[1] ) );
} finally {
	foreach ( $ax_remote_uris as $ax_remote_uri ) {
		axismundi_op_remote_object_delete( $ax_remote_uri );
	}
}

$ax_remote_failures = count( array_filter( $ax_remote_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_remote_results ), $ax_remote_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_remote_failures > 0 ? 1 : 0 );
}
exit( $ax_remote_failures > 0 ? 1 : 0 );
