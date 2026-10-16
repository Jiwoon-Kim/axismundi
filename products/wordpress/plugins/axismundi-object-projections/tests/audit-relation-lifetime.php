<?php
/**
 * Relation lifetime regression (dev-only).
 *
 * A remote cache is disposable, but its observed graph is not. This audit proves
 * complete payloads reconcile relations while partial payloads and cache eviction
 * preserve history for a later fetch.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/object-relations.php';
require_once dirname( __DIR__ ) . '/includes/remote-objects.php';
require_once dirname( __DIR__ ) . '/includes/thread-edges.php';

global $wpdb;
$ax_relation_lifetime_results = array();
$ax_relation_lifetime_uri     = 'https://fixture.example/objects/relation-lifetime-' . wp_generate_uuid4();
$ax_relation_lifetime_parent_a = 'https://fixture.example/parents/a-' . wp_generate_uuid4();
$ax_relation_lifetime_parent_b = 'https://fixture.example/parents/b-' . wp_generate_uuid4();

/** @param bool[] $results Results. */
function ax_relation_lifetime_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** @return array<string,mixed>|null */
function ax_relation_lifetime_row( string $source, string $target ) : ?array {
	global $wpdb;
	$table = axismundi_op_object_relations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact fixture relation read.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE relation_type = 'in_reply_to' AND source_object_uri_hash = %s AND source_object_uri = %s AND target_object_uri_hash = %s AND target_object_uri = %s", hash( 'sha256', $source ), $source, hash( 'sha256', $target ), $target ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

try {
	$installed = axismundi_op_install();
	$table     = axismundi_op_object_relations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture schema probe.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	ax_relation_lifetime_assert(
		$ax_relation_lifetime_results,
		'the relation schema records independent observation lifetime fields',
		$installed && in_array( 'first_seen_at', $columns, true ) && in_array( 'last_seen_at', $columns, true ) && in_array( 'last_confirmed_at', $columns, true ) && in_array( 'not_observed_since', $columns, true ) && in_array( 'observation_state', $columns, true )
	);

	$first = axismundi_op_store_remote_object(
		array( 'id' => $ax_relation_lifetime_uri, 'type' => 'Note', 'inReplyTo' => $ax_relation_lifetime_parent_a, 'content' => 'first' ),
		array( 'complete' => true )
	);
	$row_a_first = ax_relation_lifetime_row( $ax_relation_lifetime_uri, $ax_relation_lifetime_parent_a );
	ax_relation_lifetime_assert(
		$ax_relation_lifetime_results,
		'a complete fetch creates an observed relation with first-seen and confirmation times',
		is_array( $first ) && is_array( $row_a_first ) && 'observed' === (string) $row_a_first['observation_state'] && ! empty( $row_a_first['first_seen_at'] ) && ! empty( $row_a_first['last_confirmed_at'] )
	);
	if ( is_array( $row_a_first ) ) {
		$wpdb->update( $table, array( 'first_seen_at' => null, 'last_seen_at' => null, 'last_confirmed_at' => null, 'evidence_source' => null, 'observation_state' => '' ), array( 'id' => (int) $row_a_first['id'] ), array( '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- simulate a pre-v14 relation row for the migration check.
		update_option( AXISMUNDI_OP_DB_VERSION_OPTION, '13', false );
		$upgraded = axismundi_op_install();
		$row_a_first = ax_relation_lifetime_row( $ax_relation_lifetime_uri, $ax_relation_lifetime_parent_a );
		ax_relation_lifetime_assert(
			$ax_relation_lifetime_results,
			'the v14 upgrade backfills lifetime fields from existing relation evidence instead of resetting its history',
			$upgraded && is_array( $row_a_first ) && ! empty( $row_a_first['first_seen_at'] ) && ! empty( $row_a_first['last_seen_at'] ) && ! empty( $row_a_first['last_confirmed_at'] ) && 'as2' === (string) $row_a_first['evidence_source'] && 'observed' === (string) $row_a_first['observation_state']
		);
	}

	$confirmed = axismundi_op_store_remote_object(
		array( 'id' => $ax_relation_lifetime_uri, 'type' => 'Note', 'inReplyTo' => $ax_relation_lifetime_parent_a, 'content' => 'confirmed' ),
		array( 'complete' => true )
	);
	$row_a_confirmed = ax_relation_lifetime_row( $ax_relation_lifetime_uri, $ax_relation_lifetime_parent_a );
	ax_relation_lifetime_assert(
		$ax_relation_lifetime_results,
		're-fetching an unchanged relation confirms it without resetting first_seen_at or duplicating the row',
		is_array( $confirmed ) && is_array( $row_a_confirmed ) && (string) $row_a_first['first_seen_at'] === (string) $row_a_confirmed['first_seen_at'] && 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE relation_type = 'in_reply_to' AND source_object_uri_hash = %s AND source_object_uri = %s AND target_object_uri_hash = %s AND target_object_uri = %s", hash( 'sha256', $ax_relation_lifetime_uri ), $ax_relation_lifetime_uri, hash( 'sha256', $ax_relation_lifetime_parent_a ), $ax_relation_lifetime_parent_a ) )
	);

	$changed = axismundi_op_store_remote_object(
		array( 'id' => $ax_relation_lifetime_uri, 'type' => 'Note', 'inReplyTo' => $ax_relation_lifetime_parent_b, 'content' => 'changed' ),
		array( 'complete' => true )
	);
	$row_a_stale = ax_relation_lifetime_row( $ax_relation_lifetime_uri, $ax_relation_lifetime_parent_a );
	$row_b       = ax_relation_lifetime_row( $ax_relation_lifetime_uri, $ax_relation_lifetime_parent_b );
	ax_relation_lifetime_assert(
		$ax_relation_lifetime_results,
		'a relation absent from a complete re-fetch remains as not_observed while the new relation is observed',
		is_array( $changed ) && is_array( $row_a_stale ) && 'not_observed' === (string) $row_a_stale['observation_state'] && ! empty( $row_a_stale['not_observed_since'] ) && is_array( $row_b ) && 'observed' === (string) $row_b['observation_state']
	);

	$partial = axismundi_op_store_remote_object( array( 'id' => $ax_relation_lifetime_uri, 'type' => 'Note', 'content' => 'partial' ) );
	$row_b_partial = ax_relation_lifetime_row( $ax_relation_lifetime_uri, $ax_relation_lifetime_parent_b );
	ax_relation_lifetime_assert(
		$ax_relation_lifetime_results,
		'a partial payload that omits inReplyTo does not mark a standing relation absent',
		is_array( $partial ) && is_array( $row_b_partial ) && 'observed' === (string) $row_b_partial['observation_state']
	);

	$evicted = axismundi_op_delete_remote_object( $ax_relation_lifetime_uri );
	$row_a_evicted = ax_relation_lifetime_row( $ax_relation_lifetime_uri, $ax_relation_lifetime_parent_a );
	$row_b_evicted = ax_relation_lifetime_row( $ax_relation_lifetime_uri, $ax_relation_lifetime_parent_b );
	ax_relation_lifetime_assert(
		$ax_relation_lifetime_results,
		'evicting a remote cache row retains relation history and marks observed evidence stale',
		$evicted && is_array( $row_a_evicted ) && 'not_observed' === (string) $row_a_evicted['observation_state'] && is_array( $row_b_evicted ) && 'stale' === (string) $row_b_evicted['observation_state']
	);
} finally {
	$wpdb->delete( axismundi_op_object_relations_table(), array( 'source_object_uri_hash' => hash( 'sha256', $ax_relation_lifetime_uri ), 'source_object_uri' => $ax_relation_lifetime_uri ), array( '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->delete( axismundi_op_thread_edges_table(), array( 'child_uri_hash' => hash( 'sha256', $ax_relation_lifetime_uri ), 'child_uri' => $ax_relation_lifetime_uri ), array( '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- derived fixture cleanup.
	axismundi_op_delete_remote_object( $ax_relation_lifetime_uri );
}

$ax_relation_lifetime_failed = count( array_filter( $ax_relation_lifetime_results, static fn( $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_relation_lifetime_results ) - $ax_relation_lifetime_failed, count( $ax_relation_lifetime_results ) );
exit( $ax_relation_lifetime_failed > 0 ? 1 : 0 );
