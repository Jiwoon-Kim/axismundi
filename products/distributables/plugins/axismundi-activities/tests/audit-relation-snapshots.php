<?php
/**
 * Legacy relation provenance and Activity precedence fixture (dev-only).
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_snap_results   = array();
$ax_snap_actor_ids = array();
$ax_snap_uris      = array();
$ax_snap_suffix    = strtolower( wp_generate_password( 8, false, false ) );

/** Record one assertion. */
function ax_snap_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture.
}

/** Create one remote fixture Actor. */
function ax_snap_remote( string $suffix ) {
	$uri = 'https://example.com/users/snapshot_' . $suffix;
	return axismundi_actors_upsert_remote(
		array(
			'uri'                => $uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'snapshot_' . $suffix,
			'display_name'       => 'Snapshot ' . $suffix,
			'profile_url'        => $uri,
			'payload'            => array( 'id' => $uri, 'type' => 'Person' ),
			'endpoints'          => array( 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
		)
	);
}

try {
	$installed = axismundi_act_install();
	$table     = axismundi_act_relations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture schema inspection.
	$columns = (array) $wpdb->get_results( "SHOW COLUMNS FROM {$table}", OBJECT_K );
	ax_snap_assert( $ax_snap_results, 'DB v4 adds provenance, nullable initiating Activity, and legacy_pending width', $installed && '4' === (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION ) && isset( $columns['evidence_type'], $columns['evidence_ref'] ) && 'YES' === $columns['initiating_activity_uri']->Null && 'varchar(16)' === strtolower( $columns['state']->Type ) );

	$site    = axismundi_actors_get_site_actor();
	$remote1 = ax_snap_remote( $ax_snap_suffix . '_one' );
	$remote2 = ax_snap_remote( $ax_snap_suffix . '_two' );
	foreach ( array( $remote1, $remote2 ) as $remote ) {
		if ( $remote instanceof Axismundi_Actor ) {
			$ax_snap_actor_ids[] = $remote->get_identity_id();
		}
	}

	$accepted = axismundi_act_import_follow_snapshot( $site->get_uri(), $remote1->get_uri(), 'accepted', 'activitypub:ap_actor:1:0:_activitypub_followed_by' );
	ax_snap_assert( $ax_snap_results, 'accepted outbound snapshot appears in following with explicit provenance and no fake Activity', is_array( $accepted ) && 'accepted' === $accepted['state'] && 'legacy_snapshot' === $accepted['evidence_type'] && null === $accepted['initiating_activity_uri'] && in_array( $remote1->get_uri(), axismundi_act_get_following( $site->get_uri() ), true ) );

	$again = axismundi_act_import_follow_snapshot( $site->get_uri(), $remote1->get_uri(), 'accepted', 'activitypub:ap_actor:1:0:_activitypub_followed_by' );
	ax_snap_assert( $ax_snap_results, 'identical snapshot import is idempotent', is_array( $again ) && (int) $again['id'] === (int) $accepted['id'] && 'legacy_snapshot' === $again['evidence_type'] );

	$pending = axismundi_act_import_follow_snapshot( $site->get_uri(), $remote2->get_uri(), 'legacy_pending', 'activitypub:ap_actor:2:0:_activitypub_followed_by_pending' );
	ax_snap_assert( $ax_snap_results, 'legacy pending is preserved without entering accepted following', is_array( $pending ) && 'legacy_pending' === $pending['state'] && ! in_array( $remote2->get_uri(), axismundi_act_get_following( $site->get_uri() ), true ) );

	$promoted = axismundi_act_import_follow_snapshot( $site->get_uri(), $remote2->get_uri(), 'accepted', 'activitypub:ap_actor:2:0:_activitypub_followed_by' );
	ax_snap_assert( $ax_snap_results, 'accepted snapshot upgrades legacy pending but never downgrades accepted state', is_array( $promoted ) && 'accepted' === $promoted['state'] && 'legacy_snapshot' === $promoted['evidence_type'] );

	$follow = axismundi_act_record_activity( array( 'type' => 'Follow', 'actor' => $site->get_uri(), 'object' => $remote1->get_uri() ), 'outbound' );
	if ( $follow instanceof Axismundi_Activity ) {
		$ax_snap_uris[] = $follow->get_uri();
	}
	$activity_owned = axismundi_act_get_relation( 'follow', $site->get_uri(), $remote1->get_uri() );
	ax_snap_assert( $ax_snap_results, 'a real Follow takes ownership from snapshot provenance', $follow instanceof Axismundi_Activity && 'pending' === $activity_owned['state'] && 'activity' === $activity_owned['evidence_type'] && $follow->get_uri() === $activity_owned['initiating_activity_uri'] );

	$accept_uri = 'https://example.com/activities/snapshot-accept-' . $ax_snap_suffix;
	$accept = axismundi_act_record_activity( array( 'id' => $accept_uri, 'type' => 'Accept', 'actor' => $remote1->get_uri(), 'object' => $follow->get_uri() ), 'inbound' );
	if ( $accept instanceof Axismundi_Activity ) {
		$ax_snap_uris[] = $accept->get_uri();
	}
	$activity_accepted = axismundi_act_get_relation( 'follow', $site->get_uri(), $remote1->get_uri() );
	ax_snap_assert( $ax_snap_results, 'real Accept transitions the Activity-owned relation to accepted', $accept instanceof Axismundi_Activity && 'accepted' === $activity_accepted['state'] && 'activity' === $activity_accepted['evidence_type'] );

	$ignored = axismundi_act_import_follow_snapshot( $site->get_uri(), $remote1->get_uri(), 'accepted', 'activitypub:ap_actor:3:0:_activitypub_followed_by' );
	ax_snap_assert( $ax_snap_results, 'a later legacy snapshot cannot overwrite Activity provenance', is_array( $ignored ) && 'activity' === $ignored['evidence_type'] && $follow->get_uri() === $ignored['initiating_activity_uri'] );

	$undo = axismundi_act_record_activity( array( 'type' => 'Undo', 'actor' => $site->get_uri(), 'object' => $follow->get_uri() ), 'outbound' );
	if ( $undo instanceof Axismundi_Activity ) {
		$ax_snap_uris[] = $undo->get_uri();
	}
	$undone = axismundi_act_get_relation( 'follow', $site->get_uri(), $remote1->get_uri() );
	ax_snap_assert( $ax_snap_results, 'real Undo remains authoritative and removes a snapshot-origin relation from following', $undo instanceof Axismundi_Activity && 'undone' === $undone['state'] && 'activity' === $undone['evidence_type'] && ! in_array( $remote1->get_uri(), axismundi_act_get_following( $site->get_uri() ), true ) );
} finally {
	foreach ( array_unique( $ax_snap_uris ) as $uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( array( $remote1 ?? null, $remote2 ?? null ) as $remote ) {
		if ( $remote instanceof Axismundi_Actor ) {
			$wpdb->delete( axismundi_act_relations_table(), array( 'object_actor_uri_hash' => hash( 'sha256', $remote->get_uri() ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
	}
	foreach ( array_unique( $ax_snap_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table(), axismundi_actors_identity_relations_table(), axismundi_actors_asset_cache_table(), axismundi_actors_addresses_table(), axismundi_actors_texts_table() ) as $child_table ) {
			$wpdb->delete( $child_table, array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$failures = count( array_filter( $ax_snap_results, static fn( bool $result ) : bool => ! $result ) );
printf( "\n== %d checks, %d failed ==\n", count( $ax_snap_results ), $failures ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture.
exit( $failures > 0 ? 1 : 0 );
