<?php
/**
 * Legacy ActivityPub dry-scan regression (dev-only).
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit( 1 );

if ( ! function_exists( 'axismundi_activitypub_bridge_render_legacy_scan' ) ) {
	require_once dirname( __DIR__ ) . '/includes/admin.php';
}

global $wpdb;
$ax_scan_results      = array();
$ax_scan_posts        = array();
$ax_scan_user         = 0;
$ax_scan_identity_ids = array();
$ax_scan_remote_uri   = 'https://example.com/users/legacy-' . wp_generate_uuid4();
$ax_scan_object_uri   = 'https://example.com/objects/legacy-' . wp_generate_uuid4();
$ax_scan_http_count   = 0;

/** @param bool[] $results Results. */
function ax_scan_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Count any attempted HTTP request while allowing WordPress to continue normally. */
function ax_scan_http_probe( $response ) {
	unset( $response );
	++$GLOBALS['ax_scan_http_count'];
	return new WP_Error( 'ax_scan_http_forbidden', 'Dry scan attempted HTTP.' );
}

/** Find one exact classified report row. */
function ax_scan_row( array $report, string $source, string $source_id ) : ?array {
	foreach ( $report['rows'] as $row ) {
		if ( $source === $row['source'] && $source_id === $row['source_id'] ) {
			return $row;
		}
	}
	return null;
}

try {
	$login        = 'ax_scan_' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_scan_user = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$local        = axismundi_actors_ensure_for_user( $ax_scan_user );
	if ( $local instanceof Axismundi_Actor ) {
		$ax_scan_identity_ids[] = $local->get_identity_id();
		axismundi_actors_register_handle( $local->get_identity_id(), $login );
		axismundi_actors_set_status( $local->get_identity_id(), 'public' );
		$local = axismundi_actors_get_for_user( $ax_scan_user );
	}

	$remote_payload = array(
		'id'                => $ax_scan_remote_uri,
		'type'              => 'Person',
		'preferredUsername' => 'legacy_scan',
		'name'              => 'Legacy Scan',
		'inbox'             => $ax_scan_remote_uri . '/inbox',
		'outbox'            => $ax_scan_remote_uri . '/outbox',
	);
	$remote = axismundi_actors_upsert_remote(
		array(
			'uri'                => $ax_scan_remote_uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'legacy_scan',
			'display_name'       => 'Legacy Scan',
			'profile_url'        => $ax_scan_remote_uri,
			'endpoints'          => array( 'inbox' => $ax_scan_remote_uri . '/inbox', 'outbox' => $ax_scan_remote_uri . '/outbox' ),
			'payload'            => $remote_payload,
		)
	);
	if ( $remote instanceof Axismundi_Actor ) {
		$ax_scan_identity_ids[] = $remote->get_identity_id();
	}

	$actor_post = wp_insert_post( array( 'post_type' => 'ap_actor', 'post_status' => 'publish', 'post_title' => 'Legacy Actor', 'post_content' => wp_slash( wp_json_encode( $remote_payload ) ), 'guid' => $ax_scan_remote_uri ) );
	$ax_scan_posts[] = $actor_post;
	add_post_meta( $actor_post, '_activitypub_following', $ax_scan_user );

	$object_payload = array( 'id' => $ax_scan_object_uri, 'type' => 'Note', 'attributedTo' => $ax_scan_remote_uri, 'content' => '<p>Legacy object</p>' );
	axismundi_op_remote_object_store( $object_payload );
	$object_post = wp_insert_post( array( 'post_type' => 'ap_post', 'post_status' => 'publish', 'post_title' => 'Legacy Object', 'post_content' => wp_slash( wp_json_encode( $object_payload ) ), 'guid' => $ax_scan_object_uri ) );
	$ax_scan_posts[] = $object_post;

	$activity_uri = 'https://example.com/activities/' . wp_generate_uuid4();
	$inbox_payload = array( 'id' => $activity_uri, 'type' => 'Follow', 'actor' => $ax_scan_remote_uri, 'object' => $local->get_uri() );
	$inbox_post = wp_insert_post( array( 'post_type' => 'ap_inbox', 'post_status' => 'publish', 'post_title' => 'Legacy Follow', 'post_content' => wp_slash( wp_json_encode( $inbox_payload ) ), 'guid' => $activity_uri ) );
	$ax_scan_posts[] = $inbox_post;
	add_post_meta( $inbox_post, '_activitypub_user_id', $ax_scan_user );

	$outbox_post = wp_insert_post( array( 'post_type' => 'ap_outbox', 'post_status' => 'pending', 'post_title' => 'Pending Legacy Delivery', 'post_content' => '{}', 'guid' => home_url( '/legacy-outbox/' . wp_generate_uuid4() ) ) );
	$ax_scan_posts[] = $outbox_post;

	$lifecycle_post = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Legacy federated lifecycle', 'post_content' => 'Legacy' ) );
	$ax_scan_posts[] = $lifecycle_post;
	update_post_meta( $lifecycle_post, 'activitypub_status', 'federated' );

	$extra_post = wp_insert_post( array( 'post_type' => 'ap_extrafield', 'post_status' => 'publish', 'post_title' => 'Website', 'post_content' => 'https://example.com/' ) );
	$ax_scan_posts[] = $extra_post;

	$before = array(
		'official_posts' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('ap_actor','ap_post','ap_inbox','ap_outbox','ap_extrafield','ap_extrafield_blog')" ), // phpcs:ignore WordPress.DB
		'actors'         => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_actors_table() ), // phpcs:ignore WordPress.DB
		'objects'        => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_op_remote_objects_table() ), // phpcs:ignore WordPress.DB
		'activities'     => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_act_activities_table() ), // phpcs:ignore WordPress.DB
	);
	add_filter( 'pre_http_request', 'ax_scan_http_probe', 99 );
	$report = axismundi_activitypub_bridge_scan_legacy_data();
	remove_filter( 'pre_http_request', 'ax_scan_http_probe', 99 );
	$after = array(
		'official_posts' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('ap_actor','ap_post','ap_inbox','ap_outbox','ap_extrafield','ap_extrafield_blog')" ), // phpcs:ignore WordPress.DB
		'actors'         => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_actors_table() ), // phpcs:ignore WordPress.DB
		'objects'        => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_op_remote_objects_table() ), // phpcs:ignore WordPress.DB
		'activities'     => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_act_activities_table() ), // phpcs:ignore WordPress.DB
	);

	$row = ax_scan_row( $report, 'ap_actor', (string) $actor_post );
	ax_scan_assert( $ax_scan_results, 'an already cached remote Actor is duplicate but remains runtime-required for official signature verification', is_array( $row ) && 'duplicate' === $row['import'] && 'runtime_required' === $row['purge'] );
	$row = ax_scan_row( $report, 'follower_snapshot', $actor_post . ':' . $ax_scan_user );
	ax_scan_assert( $ax_scan_results, 'a follower snapshot without replayed Follow history is importable with provenance while ap_actor remains runtime-required', is_array( $row ) && 'snapshot_importable' === $row['import'] && 'runtime_required' === $row['purge'] );
	$row = ax_scan_row( $report, 'ap_post', (string) $object_post );
	ax_scan_assert( $ax_scan_results, 'an already cached remote Object is duplicate and conditionally purgeable', is_array( $row ) && 'duplicate' === $row['import'] && 'purgeable' === $row['purge'] );
	$row = ax_scan_row( $report, 'ap_inbox', (string) $inbox_post );
	ax_scan_assert( $ax_scan_results, 'a valid Inbox Activity with resolvable actor and recipient is importable and conditionally purgeable', is_array( $row ) && 'importable' === $row['import'] && 'purgeable' === $row['purge'] );
	$row = ax_scan_row( $report, 'ap_outbox', (string) $outbox_post );
	ax_scan_assert( $ax_scan_results, 'a pending official Outbox row is transport-pending and blocked', is_array( $row ) && 'transport_pending' === $row['import'] && 'blocked' === $row['purge'] );
	$row = ax_scan_row( $report, 'activitypub_status', (string) $lifecycle_post );
	ax_scan_assert( $ax_scan_results, 'a federated post without an Axismundi lifecycle baseline is deferred and blocked', is_array( $row ) && 'deferred' === $row['import'] && 'blocked' === $row['purge'] );
	$row = ax_scan_row( $report, 'ap_extrafield', (string) $extra_post );
	ax_scan_assert( $ax_scan_results, 'profile extra fields remain deferred until the verified-link contract exists', is_array( $row ) && 'deferred' === $row['import'] && 'deferred' === $row['purge'] );
	ax_scan_assert( $ax_scan_results, 'dry scan performs no repository write and no network request', $before === $after && 0 === $ax_scan_http_count && 0 === $report['writes'] && 0 === $report['network_requests'] );
	ax_scan_assert( $ax_scan_results, 'signing key custody is explicitly runtime-required', isset( $report['summary']['signing_keys']['purge']['runtime_required'] ) );

	ob_start();
	axismundi_activitypub_bridge_render_legacy_scan( $report );
	$html = (string) ob_get_clean();
	ax_scan_assert( $ax_scan_results, 'administrator dry-run UI renders separate import and purge decisions without exposing payload content', false !== strpos( $html, 'Run migration dry scan' ) && false !== strpos( $html, 'runtime_required' ) && false === strpos( $html, 'Legacy object</p>' ) );
} finally {
	remove_filter( 'pre_http_request', 'ax_scan_http_probe', 99 );
	foreach ( array_reverse( array_filter( array_map( 'intval', $ax_scan_posts ) ) ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	if ( function_exists( 'axismundi_op_remote_object_delete' ) ) {
		axismundi_op_remote_object_delete( $ax_scan_object_uri );
	}
	foreach ( array_unique( array_map( 'intval', $ax_scan_identity_ids ) ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ) ); // phpcs:ignore WordPress.DB
	}
	if ( $ax_scan_user > 0 && get_userdata( $ax_scan_user ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $ax_scan_user );
	}
}

$failures = count( array_filter( $ax_scan_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_scan_results ), $failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failures > 0 ? 1 : 0 );
}
exit( $failures > 0 ? 1 : 0 );
