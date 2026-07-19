<?php
/** Quote relation projection regression (dev-only; dist-excluded). */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_or_results       = array();
$ax_or_suffix        = strtolower( wp_generate_password( 8, false, false ) );
$ax_or_target        = 'https://origin.example/objects/' . $ax_or_suffix;
$ax_or_source        = 'https://remote.example/notes/' . $ax_or_suffix;
$ax_or_conflict      = $ax_or_source . '-conflict';
$ax_or_private       = $ax_or_source . '-private';
$ax_or_tombstone     = $ax_or_source . '-gone';
$ax_or_authorization = 'https://origin.example/quote-authorizations/' . $ax_or_suffix;
$ax_or_real_prefix   = $wpdb->prefix;
$ax_or_shadow_prefix = '';

/** Print one fixture assertion. */
function ax_or_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$installed = axismundi_op_install();
	$table     = axismundi_op_object_relations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture verifies the custom schema.
	$unique = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'relation_identity'", ARRAY_A );
	ax_or_assert( $ax_or_results, 'schema installs a unique source-target quote identity before recording its version', $installed && AXISMUNDI_OP_DB_VERSION === (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION ) && count( $unique ) >= 3 && 0 === (int) $unique[0]['Non_unique'] );

	$public = array(
		'id'                 => $ax_or_source,
		'type'               => 'Note',
		'attributedTo'       => 'https://remote.example/users/alice',
		'to'                 => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		'quote'              => $ax_or_target,
		'_misskey_quote'     => $ax_or_target,
		'quoteUrl'           => $ax_or_target,
		'quoteAuthorization' => $ax_or_authorization,
	);
	axismundi_op_remote_object_store( $public );
	$relations = axismundi_op_quote_relations_for_target( $ax_or_target );
	ax_or_assert( $ax_or_results, 'equivalent FEP and compatibility aliases collapse to one strongest-evidence relation', 1 === count( $relations ) && 'fep044f' === $relations[0]['evidence_type'] );
	ax_or_assert( $ax_or_results, 'a declared quoteAuthorization is retained but never treated as approval evidence', 'legacy_unverified' === $relations[0]['consent_status'] && $ax_or_authorization === $relations[0]['authorization_uri'] && null === axismundi_op_quote_relation_for_authorization( $ax_or_authorization ) );

	$verified = axismundi_op_verify_quote_consent( $ax_or_source, $ax_or_target, $ax_or_authorization, 'approved' );
	axismundi_op_remote_object_store( $public );
	$approved = axismundi_op_quote_relation_for_authorization( $ax_or_authorization );
	ax_or_assert( $ax_or_results, 'explicit verification marks approval and a later payload refresh cannot downgrade it', $verified && is_array( $approved ) && 'approved' === $approved['consent_status'] );
	if ( class_exists( 'Axismundi_Activity' ) ) {
		$delete = Axismundi_Activity::from_row(
			array(
				'id' => 0, 'activity_uri' => 'https://origin.example/activities/delete-' . $ax_or_suffix,
				'local_uuid' => null, 'activity_type' => 'Delete', 'actor_uri' => 'https://origin.example/users/author',
				'object_uri' => $ax_or_authorization, 'target_uri' => null, 'instrument_uri' => null,
				'direction' => 'inbound', 'effective_status' => 'active', 'audience' => array(), 'payload' => array(), 'published_at' => null,
			)
		);
		axismundi_op_observe_quote_authorization_delete( $delete );
	}
	$revoked_mapping = axismundi_op_quote_relation_for_authorization( $ax_or_authorization );
	ax_or_assert( $ax_or_results, 'a verified inbound authorization Delete revokes only its exact approved mapping', is_array( $revoked_mapping ) && 'revoked' === $revoked_mapping['consent_status'] );

	$conflicting = array(
		'id'           => $ax_or_conflict,
		'type'         => 'Note',
		'attributedTo' => 'https://remote.example/users/bob',
		'to'           => array( 'as:Public' ),
		'quote'        => $ax_or_target,
		'quoteUrl'     => $ax_or_target . '-other',
	);
	axismundi_op_remote_object_store( $conflicting );
	$conflict_rows = array_values( array_filter( axismundi_op_quote_relations_for_target( $ax_or_target ), static fn( array $row ) : bool => $ax_or_conflict === $row['source_object_uri'] ) );
	ax_or_assert( $ax_or_results, 'conflicting aliases retain every candidate as ambiguous instead of choosing one', 1 === count( $conflict_rows ) && 'ambiguous' === $conflict_rows[0]['consent_status'] && 2 === count( axismundi_op_quote_candidates( $conflicting ) ) );
	$e232 = axismundi_op_quote_candidates(
		array(
			'tag' => array(
				array( 'type' => 'Link', 'rel' => 'https://misskey-hub.net/ns/#_misskey_quote', 'href' => $ax_or_target ),
			),
		)
	);
	ax_or_assert( $ax_or_results, 'the applicable FEP-e232 Link form normalizes as Misskey evidence', 'misskey' === ( $e232[ $ax_or_target ] ?? '' ) );

	ax_or_assert( $ax_or_results, 'public quote count is distinct by source Object and excludes ambiguous aliases', 1 === axismundi_op_get_quote_count( $ax_or_target ) );
	ax_or_assert( $ax_or_results, 'revoked consent does not erase the still-public observed quote fact', 1 === axismundi_op_get_quote_count( $ax_or_target ) );

	axismundi_op_remote_object_store(
		array(
			'id'           => $ax_or_private,
			'type'         => 'Note',
			'attributedTo' => 'https://remote.example/users/carol',
			'to'           => array( 'https://remote.example/users/recipient' ),
			'quoteUri'     => $ax_or_target,
		)
	);
	ax_or_assert( $ax_or_results, 'followers-only or direct quote Objects are observed but excluded from public count', 1 === axismundi_op_get_quote_count( $ax_or_target ) );

	$block = static fn( $allowed, array $row ) : bool => $ax_or_source !== (string) $row['object_uri'];
	add_filter( 'axismundi_op_public_quote_source_allowed', $block, 10, 2 );
	ax_or_assert( $ax_or_results, 'the moderation seam excludes a blocked public source without rewriting its relation', 0 === axismundi_op_get_quote_count( $ax_or_target ) );
	remove_filter( 'axismundi_op_public_quote_source_allowed', $block, 10 );

	axismundi_op_remote_object_store( array( 'id' => $ax_or_tombstone, 'type' => 'Note', 'attributedTo' => 'https://remote.example/users/dan', 'to' => array( 'as:Public' ), '_misskey_quote' => $ax_or_target ) );
	axismundi_op_remote_object_store( array( 'id' => $ax_or_tombstone, 'type' => 'Tombstone' ) );
	ax_or_assert( $ax_or_results, 'a Tombstone refresh removes the source relation from public count', 1 === axismundi_op_get_quote_count( $ax_or_target ) );

	// Reproduce a v3 -> v4 upgrade on fixture-owned tables. The real cache and relation
	// table must never be dropped to prove a migration.
	$real_rows_before     = count( axismundi_op_quote_relations_for_target( $ax_or_target ) );
	$ax_or_shadow_prefix = $ax_or_real_prefix . 'axor' . $ax_or_suffix . '_';
	$wpdb->prefix        = $ax_or_shadow_prefix;
	$shadow_relation     = axismundi_op_object_relations_table();
	$shadow_built        = axismundi_op_install();
	$shadow_source       = 'https://shadow.example/notes/' . $ax_or_suffix;
	$shadow_target       = 'https://shadow.example/objects/' . $ax_or_suffix;
	axismundi_op_remote_object_store( array( 'id' => $shadow_source, 'type' => 'Note', 'attributedTo' => 'https://shadow.example/users/alice', 'to' => array( 'as:Public' ), 'quoteUrl' => $shadow_target ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- fixture-owned shadow table.
	$wpdb->query( "DROP TABLE IF EXISTS {$shadow_relation}" );
	$shadow_absent   = ! (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$shadow_relation}'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned shadow table.
	update_option( AXISMUNDI_OP_DB_VERSION_OPTION, '3', false );
	$shadow_upgraded = axismundi_op_install();
	$shadow_present  = (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$shadow_relation}'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned shadow table.
	$shadow_backfill = axismundi_op_quote_relations_for_target( $shadow_target );
	$wpdb->prefix    = $ax_or_real_prefix;
	ax_or_assert( $ax_or_results, 'v3 to v4 creates and backfills the relation projection on a throwaway prefix without touching real rows', $shadow_built && $shadow_absent && $shadow_upgraded && $shadow_present && 1 === count( $shadow_backfill ) && $real_rows_before === count( axismundi_op_quote_relations_for_target( $ax_or_target ) ) );
} finally {
	$wpdb->prefix = $ax_or_real_prefix;
	foreach ( array( $ax_or_source, $ax_or_conflict, $ax_or_private, $ax_or_tombstone ) as $source_uri ) {
		axismundi_op_remote_object_delete( $source_uri );
	}
	if ( '' !== $ax_or_shadow_prefix ) {
		foreach ( array( 'ax_remote_objects', 'ax_object_leases', 'ax_object_relations' ) as $shadow_name ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$ax_or_shadow_prefix}{$shadow_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- fixture-owned shadow cleanup.
		}
	}
}

$ax_or_failures = count( array_filter( $ax_or_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_or_results ), $ax_or_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_or_failures > 0 ? 1 : 0 );
}
exit( $ax_or_failures > 0 ? 1 : 0 );
