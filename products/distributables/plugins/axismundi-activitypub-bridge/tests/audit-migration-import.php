<?php
/**
 * Legacy ActivityPub import regression (dev-only; dist-excluded).
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit( 1 );

if ( ! function_exists( 'axismundi_activitypub_bridge_render_legacy_scan' ) ) {
	require_once dirname( __DIR__ ) . '/includes/admin.php';
}
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$ax_import_results = array();
$ax_import_posts   = array();
$ax_import_ids     = array();
$ax_import_user    = 0;
$ax_import_http    = 0;

/** Record one assertion. */
function ax_import_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = array( $label, $condition );
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture.
}

/** Count any unexpected HTTP request. */
function ax_import_http_probe( $preempt, $args, $url ) {
	global $ax_import_http;
	++$ax_import_http;
	return new WP_Error( 'ax_import_network', 'Import must not request ' . (string) $url );
}

/** Remove one fixture Actor and all repository-owned child rows. */
function ax_import_delete_actor( int $identity_id ) : void {
	global $wpdb;
	foreach ( array(
		axismundi_actors_texts_table(),
		axismundi_actors_addresses_table(),
		axismundi_actors_endpoints_table(),
		axismundi_actors_asset_cache_table(),
		axismundi_actors_keys_table(),
		axismundi_actors_fetch_state_table(),
		axismundi_actors_identity_relations_table(),
	) as $table ) {
		$wpdb->delete( $table, array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- exact fixture cleanup.
	}
	$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- exact fixture cleanup.
	$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- exact fixture cleanup.
}

try {
	add_filter( 'pre_http_request', 'ax_import_http_probe', 99, 3 );
	$ax_import_user = (int) wp_insert_user( array( 'user_login' => 'ax_import_local', 'user_pass' => wp_generate_password(), 'role' => 'contributor' ) );
	$local          = axismundi_actors_ensure_for_user( $ax_import_user );
	$ax_import_ids[] = $local->get_identity_id();

	$remote_uri = 'https://example.com/users/import_' . wp_generate_uuid4();
	$object_uri = 'https://example.com/objects/' . wp_generate_uuid4();
	$activity_uri = 'https://example.com/activities/' . wp_generate_uuid4();
	$actor_payload = array(
		'id'                => $remote_uri,
		'type'              => 'Person',
		'preferredUsername' => 'legacy_import',
		'name'              => 'Legacy Import',
		'inbox'             => $remote_uri . '/inbox',
		'outbox'            => $remote_uri . '/outbox',
	);
	$object_payload = array( 'id' => $object_uri, 'type' => 'Note', 'attributedTo' => $remote_uri, 'content' => '<p>Import payload sentinel</p>' );
	$inbox_payload  = array( 'id' => $activity_uri, 'type' => 'Follow', 'actor' => $remote_uri, 'object' => $local->get_uri() );

	$actor_post = wp_insert_post( array( 'post_type' => 'ap_actor', 'post_status' => 'publish', 'post_title' => 'Import Actor', 'post_content' => wp_slash( wp_json_encode( $actor_payload ) ), 'guid' => $remote_uri ) );
	$object_post = wp_insert_post( array( 'post_type' => 'ap_post', 'post_status' => 'publish', 'post_title' => 'Import Object', 'post_content' => wp_slash( wp_json_encode( $object_payload ) ), 'guid' => $object_uri ) );
	$inbox_post = wp_insert_post( array( 'post_type' => 'ap_inbox', 'post_status' => 'publish', 'post_title' => 'Import Follow', 'post_content' => wp_slash( wp_json_encode( $inbox_payload ) ), 'guid' => $activity_uri ) );
	$ax_import_posts = array( $actor_post, $object_post, $inbox_post );
	add_post_meta( $actor_post, '_activitypub_following', $ax_import_user );
	add_post_meta( $inbox_post, '_activitypub_user_id', $ax_import_user );

	$source_before = count( array_filter( array_map( 'get_post', $ax_import_posts ) ) );
	$result = axismundi_activitypub_bridge_import_legacy_data();
	$remote = axismundi_actors_get_by_uri( $remote_uri );
	if ( $remote instanceof Axismundi_Actor ) {
		$ax_import_ids[] = $remote->get_identity_id();
	}
	$object   = axismundi_op_remote_object_get( $object_uri, false );
	$activity = axismundi_act_get( $activity_uri );
	$relation = axismundi_act_get_relation( 'follow', $remote_uri, $local->get_uri() );

	ax_import_assert( $ax_import_results, 'import completes through existing repositories with no delete or network request', ! empty( $result['complete'] ) && 3 === $result['writes'] && 0 === $result['deletes'] && 0 === $result['network_requests'] && 0 === $ax_import_http );
	ax_import_assert( $ax_import_results, 'remote Actor is imported and verified by canonical URI', $remote instanceof Axismundi_Actor && 'Person' === $remote->get_type() );
	ax_import_assert( $ax_import_results, 'remote Object is imported with the full bounded payload snapshot', is_array( $object ) && false !== strpos( (string) $object['payload_json'], 'Import payload sentinel' ) );
	ax_import_assert( $ax_import_results, 'Inbox Follow is replayed into the Activity ledger', $activity instanceof Axismundi_Activity && 'Follow' === $activity->get_type() );
	ax_import_assert( $ax_import_results, 'relation state is derived by replay rather than written from the follower snapshot', is_array( $relation ) && 'pending' === $relation['state'] );
	ax_import_assert( $ax_import_results, 'all official source rows remain after import', $source_before === count( array_filter( array_map( 'get_post', $ax_import_posts ) ) ) );

	$again = axismundi_activitypub_bridge_import_legacy_data();
	ax_import_assert( $ax_import_results, 'a second import is idempotent and reports verified existing rows', ! empty( $again['complete'] ) && 0 === $again['writes'] && 3 === array_sum( array_map( static fn( array $counts ) : int => (int) ( $counts['verified_existing'] ?? 0 ), $again['summary'] ) ) );
	ax_import_assert( $ax_import_results, 'import remains offline across repeated runs', 0 === $ax_import_http && 0 === $again['network_requests'] );

	ob_start();
	axismundi_activitypub_bridge_render_legacy_scan( null, $result );
	$html = (string) ob_get_clean();
	ax_import_assert( $ax_import_results, 'administrator result separates writes from source deletes without rendering payloads', false !== strpos( $html, 'Import result' ) && false !== strpos( $html, 'Source deletes: 0' ) && false === strpos( $html, 'Import payload sentinel' ) );

	$unaddressed_uri = 'https://example.com/activities/' . wp_generate_uuid4();
	$unaddressed = wp_insert_post(
		array(
			'post_type'    => 'ap_inbox',
			'post_status'  => 'publish',
			'post_title'   => 'Unaddressed Activity',
			'post_content' => wp_slash( wp_json_encode( array( 'id' => $unaddressed_uri, 'type' => 'Follow', 'actor' => $remote_uri, 'object' => $local->get_uri() ) ) ),
			'guid'         => $unaddressed_uri,
		)
	);
	$ax_import_posts[] = $unaddressed;
	$rejected = axismundi_activitypub_bridge_import_legacy_data();
	ax_import_assert( $ax_import_results, 'Inbox history without a mapped local recipient fails closed', empty( $rejected['complete'] ) && ! axismundi_act_get( $unaddressed_uri ) instanceof Axismundi_Activity );
} finally {
	remove_filter( 'pre_http_request', 'ax_import_http_probe', 99 );
	$wpdb->delete( axismundi_act_relations_table(), array( 'subject_actor_uri_hash' => hash( 'sha256', $remote_uri ?? '' ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- exact fixture cleanup.
	$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri_hash' => hash( 'sha256', $activity_uri ?? '' ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- exact fixture cleanup.
	if ( isset( $object_uri ) ) {
		axismundi_op_remote_object_delete( $object_uri );
	}
	foreach ( array_reverse( array_filter( array_map( 'intval', $ax_import_posts ) ) ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	if ( $ax_import_user && get_userdata( $ax_import_user ) ) {
		wp_delete_user( $ax_import_user );
	}
	foreach ( array_unique( array_map( 'intval', $ax_import_ids ) ) as $identity_id ) {
		ax_import_delete_actor( $identity_id );
	}
}

$failed = count( array_filter( $ax_import_results, static fn( array $result ) : bool => ! $result[1] ) );
printf( "\n== %d checks, %d failed ==\n", count( $ax_import_results ), $failed ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture.
exit( $failed > 0 ? 1 : 0 );
