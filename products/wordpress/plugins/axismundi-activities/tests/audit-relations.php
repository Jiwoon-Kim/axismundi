<?php
/**
 * Phase 2 social relation state-machine regression fixture (dev-only).
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/relations.php';
require_once dirname( __DIR__ ) . '/includes/admin.php';

$ax_rel_results       = array();
$ax_rel_activity_uris = array();
$ax_rel_remote_id     = 0;
$ax_rel_old_user      = get_current_user_id();
$ax_rel_old_get       = $_GET;
$ax_rel_suffix        = strtolower( wp_generate_password( 8, false, false ) );
$ax_rel_remote_uri    = 'https://example.com/users/ax_relation_' . $ax_rel_suffix;
$GLOBALS['ax_rel_changed'] = array();

/** Record one fixture result. */
function ax_rel_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Track relation hooks, which must only run after the enclosing transaction commits. */
function ax_rel_observe_change( array $relation ) : void {
	$GLOBALS['ax_rel_changed'][] = $relation;
}

/** Record and track one successful fixture Activity. */
function ax_rel_record( array $payload, string $direction, array &$uris ) {
	$activity = axismundi_act_record_activity( $payload, $direction );
	if ( $activity instanceof Axismundi_Activity ) {
		$uris[] = $activity->get_uri();
	}
	return $activity;
}

try {
	global $wpdb;
	$installed = axismundi_act_install();
	$table     = axismundi_act_relations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture verifies its custom table.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture verifies its custom index.
	$index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'relation_identity'", ARRAY_A );
	ax_rel_assert( $ax_rel_results, 'the relation schema retains unique URI identity without blog_id after later upgrades', $installed && (int) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION ) >= 2 && ! empty( $index ) && 0 === (int) $index[0]['Non_unique'] && ! in_array( 'blog_id', $columns, true ) );

	$site_actor = axismundi_actors_get_site_actor();
	$remote     = axismundi_actors_upsert_remote(
		array(
			'uri'                => $ax_rel_remote_uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'ax_relation_' . $ax_rel_suffix,
			'display_name'       => 'Relation fixture remote Actor',
			'profile_url'        => 'https://example.com/@ax_relation_' . $ax_rel_suffix,
			'payload'            => array( 'id' => $ax_rel_remote_uri, 'type' => 'Person' ),
			'endpoints'          => array(
				'inbox'  => $ax_rel_remote_uri . '/inbox',
				'outbox' => $ax_rel_remote_uri . '/outbox',
			),
		)
	);
	$ax_rel_remote_id = $remote instanceof Axismundi_Actor ? $remote->get_identity_id() : 0;
	ax_rel_assert( $ax_rel_results, 'fixture uses known local and remote Actors from the required Actors repository', $site_actor instanceof Axismundi_Actor && $site_actor->is_local() && $remote instanceof Axismundi_Actor && ! $remote->is_local() );

	add_action( 'axismundi_act_relation_changed', 'ax_rel_observe_change' );

	$outbound_follow = ax_rel_record(
		array( 'type' => 'Follow', 'actor' => $site_actor->get_uri(), 'object' => $ax_rel_remote_uri ),
		'outbound',
		$ax_rel_activity_uris
	);
	$outbound_row = $outbound_follow instanceof Axismundi_Activity ? axismundi_act_get_relation( 'follow', $site_actor->get_uri(), $ax_rel_remote_uri ) : null;
	ax_rel_assert( $ax_rel_results, 'outbound Follow materializes one pending outbound relation', $outbound_follow instanceof Axismundi_Activity && is_array( $outbound_row ) && 'pending' === $outbound_row['state'] && 'outbound' === $outbound_row['direction'] );

	$outbound_accept = ax_rel_record(
		array( 'id' => 'https://example.com/activities/accept-' . $ax_rel_suffix, 'type' => 'Accept', 'actor' => $ax_rel_remote_uri, 'object' => $outbound_follow->get_uri() ),
		'inbound',
		$ax_rel_activity_uris
	);
	$outbound_row = axismundi_act_get_relation( 'follow', $site_actor->get_uri(), $ax_rel_remote_uri );
	ax_rel_assert( $ax_rel_results, 'only the followed Actor Accepts an outbound Follow and following is derived from accepted edges', $outbound_accept instanceof Axismundi_Activity && 'accepted' === $outbound_row['state'] && in_array( $ax_rel_remote_uri, axismundi_act_get_following( $site_actor->get_uri() ), true ) );

	$outbound_undo = ax_rel_record(
		array( 'type' => 'Undo', 'actor' => $site_actor->get_uri(), 'object' => $outbound_follow->get_uri() ),
		'outbound',
		$ax_rel_activity_uris
	);
	$outbound_row = axismundi_act_get_relation( 'follow', $site_actor->get_uri(), $ax_rel_remote_uri );
	ax_rel_assert( $ax_rel_results, 'Undo(Follow) marks the edge undone and removes it from following', $outbound_undo instanceof Axismundi_Activity && 'undone' === $outbound_row['state'] && ! in_array( $ax_rel_remote_uri, axismundi_act_get_following( $site_actor->get_uri() ), true ) );

	$inbound_follow_uri = 'https://example.com/activities/follow-in-' . $ax_rel_suffix;
	$inbound_follow     = ax_rel_record(
		array( 'id' => $inbound_follow_uri, 'type' => 'Follow', 'actor' => $ax_rel_remote_uri, 'object' => $site_actor->get_uri() ),
		'inbound',
		$ax_rel_activity_uris
	);
	$inbound_accept = ax_rel_record(
		array( 'type' => 'Accept', 'actor' => $site_actor->get_uri(), 'object' => $inbound_follow_uri ),
		'outbound',
		$ax_rel_activity_uris
	);
	$inbound_row = axismundi_act_get_relation( 'follow', $ax_rel_remote_uri, $site_actor->get_uri() );
	ax_rel_assert( $ax_rel_results, 'inbound Follow accepted by the local target appears in derived followers and the count-only query', $inbound_follow instanceof Axismundi_Activity && $inbound_accept instanceof Axismundi_Activity && 'accepted' === $inbound_row['state'] && in_array( $ax_rel_remote_uri, axismundi_act_get_followers( $site_actor->get_uri() ), true ) && 1 === axismundi_act_get_follower_count( $site_actor->get_uri() ) );

	$inbound_undo = ax_rel_record(
		array( 'id' => 'https://example.com/activities/undo-in-' . $ax_rel_suffix, 'type' => 'Undo', 'actor' => $ax_rel_remote_uri, 'object' => $inbound_follow_uri ),
		'inbound',
		$ax_rel_activity_uris
	);
	$inbound_row = axismundi_act_get_relation( 'follow', $ax_rel_remote_uri, $site_actor->get_uri() );
	ax_rel_assert( $ax_rel_results, 'remote Undo(Follow) removes the accepted inbound follower edge and decrements the count-only query', $inbound_undo instanceof Axismundi_Activity && 'undone' === $inbound_row['state'] && ! in_array( $ax_rel_remote_uri, axismundi_act_get_followers( $site_actor->get_uri() ), true ) && 0 === axismundi_act_get_follower_count( $site_actor->get_uri() ) );

	$reject_follow = ax_rel_record(
		array( 'id' => 'https://example.com/activities/follow-reject-' . $ax_rel_suffix, 'type' => 'Follow', 'actor' => $ax_rel_remote_uri, 'object' => $site_actor->get_uri() ),
		'inbound',
		$ax_rel_activity_uris
	);
	$reject = ax_rel_record(
		array( 'type' => 'Reject', 'actor' => $site_actor->get_uri(), 'object' => $reject_follow->get_uri() ),
		'outbound',
		$ax_rel_activity_uris
	);
	$reject_row = axismundi_act_get_relation( 'follow', $ax_rel_remote_uri, $site_actor->get_uri() );
	ax_rel_assert( $ax_rel_results, 'Reject transitions the currently initiating Follow to rejected', $reject instanceof Axismundi_Activity && 'rejected' === $reject_row['state'] && $reject->get_uri() === $reject_row['state_activity_uri'] );

	$unauthorized_follow = ax_rel_record(
		array( 'type' => 'Follow', 'actor' => $site_actor->get_uri(), 'object' => $ax_rel_remote_uri ),
		'outbound',
		$ax_rel_activity_uris
	);
	$unauthorized_uri = axismundi_act_local_uri( wp_generate_uuid4() );
	$unauthorized     = axismundi_act_record_activity(
		array( 'id' => $unauthorized_uri, 'type' => 'Accept', 'actor' => $site_actor->get_uri(), 'object' => $unauthorized_follow->get_uri() ),
		'local'
	);
	ax_rel_assert( $ax_rel_results, 'an Actor cannot Accept its own outbound Follow and the invalid Activity transaction rolls back', is_wp_error( $unauthorized ) && 'ax_act_follow_transition_actor' === $unauthorized->get_error_code() && null === axismundi_act_get( $unauthorized_uri ) );

	$block = ax_rel_record(
		array( 'type' => 'Block', 'actor' => $site_actor->get_uri(), 'object' => $ax_rel_remote_uri ),
		'outbound',
		$ax_rel_activity_uris
	);
	$block_row = axismundi_act_get_relation( 'block', $site_actor->get_uri(), $ax_rel_remote_uri );
	$block_undo = ax_rel_record(
		array( 'type' => 'Undo', 'actor' => $site_actor->get_uri(), 'object' => $block->get_uri() ),
		'outbound',
		$ax_rel_activity_uris
	);
	$block_undone = axismundi_act_get_relation( 'block', $site_actor->get_uri(), $ax_rel_remote_uri );
	ax_rel_assert( $ax_rel_results, 'Block materializes active and Undo(Block) transitions the same edge to undone', $block instanceof Axismundi_Activity && 'active' === $block_row['state'] && $block_undo instanceof Axismundi_Activity && 'undone' === $block_undone['state'] );

	$future_follow_uri = 'https://example.com/activities/future-follow-' . $ax_rel_suffix;
	$early_accept      = ax_rel_record(
		array( 'type' => 'Accept', 'actor' => $site_actor->get_uri(), 'object' => $future_follow_uri ),
		'outbound',
		$ax_rel_activity_uris
	);
	$future_follow = ax_rel_record(
		array( 'id' => $future_follow_uri, 'type' => 'Follow', 'actor' => $ax_rel_remote_uri, 'object' => $site_actor->get_uri() ),
		'inbound',
		$ax_rel_activity_uris
	);
	$future_row = axismundi_act_get_relation( 'follow', $ax_rel_remote_uri, $site_actor->get_uri() );
	ax_rel_assert( $ax_rel_results, 'an out-of-order Accept is retained and reconciled when its Follow arrives', $early_accept instanceof Axismundi_Activity && $future_follow instanceof Axismundi_Activity && 'accepted' === $future_row['state'] && $early_accept->get_uri() === $future_row['state_activity_uri'] );

	$change_count_before_replay = count( $GLOBALS['ax_rel_changed'] );
	$replay                     = axismundi_act_record_activity( $future_follow->get_payload(), 'inbound' );
	ax_rel_assert( $ax_rel_results, 'idempotent Activity replay emits no duplicate relation change', $replay instanceof Axismundi_Activity && $future_follow->get_id() === $replay->get_id() && $change_count_before_replay === count( $GLOBALS['ax_rel_changed'] ) );

	$admins   = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
	$admin_id = ! empty( $admins ) ? (int) $admins[0] : 0;
	wp_set_current_user( $admin_id );
	$_GET['activity_uri'] = $future_follow->get_uri();
	ob_start();
	axismundi_act_render_admin_page();
	$admin_html = (string) ob_get_clean();
	ax_rel_assert( $ax_rel_results, 'Tools Activity Log renders read-only recent Activities, relations, and escaped immutable payload', $admin_id > 0 && str_contains( $admin_html, 'Activity Log' ) && str_contains( $admin_html, 'Recent activities' ) && str_contains( $admin_html, 'Social relations' ) && str_contains( $admin_html, 'Immutable payload' ) && ! str_contains( $admin_html, '<form' ) );

	ax_rel_assert( $ax_rel_results, 'relation change hooks observe committed snapshots only', ! empty( $GLOBALS['ax_rel_changed'] ) && 0 === count( array_filter( $GLOBALS['ax_rel_changed'], static fn( array $row ) : bool => null === axismundi_act_get_relation( (string) $row['relation_type'], (string) $row['subject_actor_uri'], (string) $row['object_actor_uri'] ) ) ) );
} finally {
	remove_action( 'axismundi_act_relation_changed', 'ax_rel_observe_change' );
	wp_set_current_user( $ax_rel_old_user );
	$_GET = $ax_rel_old_get;
	global $wpdb;
	foreach ( array_unique( $ax_rel_activity_uris ) as $ax_rel_activity_uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri_hash' => hash( 'sha256', $ax_rel_activity_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned cleanup.
	}
	$wpdb->delete( axismundi_act_relations_table(), array( 'subject_actor_uri_hash' => hash( 'sha256', $ax_rel_remote_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned cleanup.
	$wpdb->delete( axismundi_act_relations_table(), array( 'object_actor_uri_hash' => hash( 'sha256', $ax_rel_remote_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned cleanup.
	if ( $ax_rel_remote_id > 0 ) {
		foreach ( array( axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table(), axismundi_actors_identity_relations_table(), axismundi_actors_asset_cache_table(), axismundi_actors_addresses_table(), axismundi_actors_texts_table() ) as $ax_rel_child_table ) {
			$wpdb->delete( $ax_rel_child_table, array( 'identity_id' => $ax_rel_remote_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned Actor cleanup.
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_rel_remote_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned Actor cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_rel_remote_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned Actor cleanup.
	}
}

$ax_rel_failures = count( array_filter( $ax_rel_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_rel_results ), $ax_rel_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_rel_failures > 0 ? 1 : 0 );
}
exit( $ax_rel_failures > 0 ? 1 : 0 );
