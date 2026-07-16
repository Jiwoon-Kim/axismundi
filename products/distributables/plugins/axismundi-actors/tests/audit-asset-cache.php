<?php
/**
 * Remote Actor asset cache regression. HTTP is fully mocked; dev/dist-excluded.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/asset-cache.php';

global $wpdb;
$ax_asset_results = array();
$ax_asset_ids     = array();
$ax_asset_hash    = '';
$ax_asset_http_calls = 0;
$ax_asset_invalid = false;
$ax_asset_webp_original = get_option( 'ax_actors_asset_webp_enabled', null );

/** @param array $results Accumulator. @param string $label Contract. @param bool $condition Holds. */
function ax_asset_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** @param string $slug Fixture slug. @return Axismundi_Actor|WP_Error */
function ax_asset_actor( string $slug ) {
	$uri = 'https://example.com/users/' . $slug;
	return axismundi_actors_upsert_remote(
		array(
			'uri'                => $uri,
			'actor_type'         => 'Person',
			'preferred_username' => $slug,
			'display_name'       => $slug,
			'profile_url'        => 'https://example.com/@' . $slug,
			'endpoints'          => array( 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
			'payload'            => array( 'id' => $uri, 'type' => 'Person', 'preferredUsername' => $slug ),
		)
	);
}

/** @return array<string,mixed> Mock HTTP response. */
function ax_asset_response( string $body, int $code = 200, string $type = 'image/png' ) : array {
	return array(
		'headers'  => array( 'content-type' => $type, 'etag' => '"asset-v1"', 'last-modified' => 'Mon, 01 Jun 2026 00:00:00 GMT' ),
		'body'     => $body,
		'response' => array( 'code' => $code, 'message' => 'Fixture' ),
		'cookies'  => array(),
		'filename' => null,
	);
}

$ax_asset_color = hexdec( substr( md5( wp_generate_uuid4() ), 0, 6 ) );
$ax_asset_image = imagecreatetruecolor( 400, 400 );
imagefill( $ax_asset_image, 0, 0, imagecolorallocate( $ax_asset_image, ( $ax_asset_color >> 16 ) & 255, ( $ax_asset_color >> 8 ) & 255, $ax_asset_color & 255 ) );
ob_start();
imagepng( $ax_asset_image );
$ax_asset_png = (string) ob_get_clean();
imagedestroy( $ax_asset_image );
$ax_asset_http = static function ( $preempt, array $args, string $url ) use ( &$ax_asset_http_calls, &$ax_asset_invalid, $ax_asset_png ) {
	++$ax_asset_http_calls;
	if ( $ax_asset_invalid || str_contains( $url, 'invalid' ) ) {
		return ax_asset_response( '<svg></svg>', 200, 'image/svg+xml' );
	}
	if ( isset( $args['headers']['If-None-Match'] ) && '"asset-v1"' === $args['headers']['If-None-Match'] ) {
		return ax_asset_response( '', 304 );
	}
	return ax_asset_response( (string) $ax_asset_png );
};

try {
	axismundi_actors_install();
	update_option( 'ax_actors_asset_webp_enabled', 0, false );
	ax_asset_assert( $ax_asset_results, 'WebP candidate generation is opt-in and defaults to disabled', ! axismundi_actors_asset_webp_enabled() );
	update_option( 'ax_actors_asset_webp_enabled', 1, false );
	$webp_enabled = axismundi_actors_asset_webp_enabled();
	update_option( 'ax_actors_asset_webp_enabled', 0, false );
	ax_asset_assert( $ax_asset_results, 'the WebP policy can be enabled explicitly and returned to the low-compute default', $webp_enabled && ! axismundi_actors_asset_webp_enabled() );
	$table = axismundi_actors_asset_cache_table();
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ); // phpcs:ignore WordPress.DB
	$identity_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'identity_asset'", ARRAY_A ); // phpcs:ignore WordPress.DB
	$content_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'content_processor'", ARRAY_A ); // phpcs:ignore WordPress.DB
	ax_asset_assert( $ax_asset_results, 'DB v9 creates the single cache table and required identity/content indexes', (int) get_option( 'ax_actors_db_version' ) >= 9 && in_array( 'variants_json', $columns, true ) && ! empty( $identity_index ) && '0' === (string) $identity_index[0]['Non_unique'] && ! empty( $content_index ) );

	$source = axismundi_actors_remote_asset_source( array( 'type' => 'Image', 'url' => 'https://example.com/assets/avatar.png' ) );
	$unsafe = axismundi_actors_remote_asset_source( 'http://example.com/insecure.png' );
	ax_asset_assert( $ax_asset_results, 'ActivityStreams image objects resolve to HTTPS while unsafe sources are rejected', 'https://example.com/assets/avatar.png' === $source && '' === $unsafe );

	$first = ax_asset_actor( 'asset_one' );
	if ( $first instanceof Axismundi_Actor ) {
		$ax_asset_ids[] = $first->get_identity_id();
	}
	$before_calls = $ax_asset_http_calls;
	$synced = $first instanceof Axismundi_Actor && axismundi_actors_sync_asset_source( $first->get_identity_id(), 'avatar', $source );
	$pending = $first instanceof Axismundi_Actor ? axismundi_actors_get_asset_cache_row( $first->get_identity_id(), 'avatar' ) : null;
	ax_asset_assert( $ax_asset_results, 'source synchronization creates pending state without a render-time/network fetch', $synced && is_array( $pending ) && 'pending' === $pending['fetch_status'] && null === $pending['content_hash'] && $before_calls === $ax_asset_http_calls );
	wp_clear_scheduled_hook( 'axismundi_actors_process_asset_batch' );
	$recovered_worker = axismundi_actors_recover_asset_worker();
	$recovered_event  = wp_next_scheduled( 'axismundi_actors_process_asset_batch' );
	ax_asset_assert( $ax_asset_results, 'queue health recovers a due pending row after its one-shot worker was cleared', $recovered_worker && false !== $recovered_event && 1 <= axismundi_actors_asset_due_count() && $before_calls === $ax_asset_http_calls );
	wp_clear_scheduled_hook( 'axismundi_actors_process_asset_batch' );

	add_filter( 'pre_http_request', $ax_asset_http, 10, 3 );
	$fetched = $first instanceof Axismundi_Actor ? axismundi_actors_fetch_remote_asset( $first->get_identity_id(), 'avatar' ) : null;
	$ready = $first instanceof Axismundi_Actor ? axismundi_actors_get_asset_cache_row( $first->get_identity_id(), 'avatar' ) : null;
	$url = $first instanceof Axismundi_Actor ? axismundi_actors_get_cached_asset_url( $first->get_identity_id(), 'avatar', 192 ) : '';
	$manifest = is_array( $ready ) ? json_decode( (string) $ready['variants_json'], true ) : array();
	$ax_asset_hash = is_array( $ready ) ? (string) $ready['content_hash'] : '';
	$root = '' !== $ax_asset_hash ? axismundi_actors_asset_content_root( $ax_asset_hash ) : null;
	ax_asset_assert( $ax_asset_results, 'worker validates source bytes, creates local derivatives, and flips the row to ready', true === $fetched && is_array( $ready ) && 'ready' === $ready['fetch_status'] && 64 === strlen( $ax_asset_hash ) && '' !== $url && is_array( $root ) && is_dir( $root['path'] ) );
	$before_ready_idle = $ax_asset_http_calls;
	$same_source_idle  = $first instanceof Axismundi_Actor && axismundi_actors_sync_asset_source( $first->get_identity_id(), 'avatar', $source );
	$ready_idle        = $first instanceof Axismundi_Actor ? axismundi_actors_get_asset_cache_row( $first->get_identity_id(), 'avatar' ) : null;
	ax_asset_assert( $ax_asset_results, 'a ready cache has no timer deadline and is not fetched again until its Actor source changes', $same_source_idle && is_array( $ready_idle ) && 'ready' === $ready_idle['fetch_status'] && null === $ready_idle['expires_at'] && null === $ready_idle['next_refresh_at'] && $before_ready_idle === $ax_asset_http_calls );
	ax_asset_assert( $ax_asset_results, 'avatar derivatives use 96/192/400 caps without upscaling the 400px source', array( 96, 192, 400 ) === array_keys( $manifest ) && 400 === (int) $manifest[400]['width'] && max( array_column( $manifest, 'width' ) ) <= 400 );
	$valid_mimes = ! empty( $manifest ) && empty( array_diff( array_column( $manifest, 'mime' ), array( 'image/png' ) ) );
	ax_asset_assert( $ax_asset_results, 'default processing keeps normalized PNG and skips optional WebP computation', $valid_mimes );

	if ( $first instanceof Axismundi_Actor ) {
		axismundi_actors_sync_asset_source( $first->get_identity_id(), 'header', 'https://example.com/assets/header.png' );
	}
	$header_fetch    = $first instanceof Axismundi_Actor ? axismundi_actors_fetch_remote_asset( $first->get_identity_id(), 'header' ) : null;
	$header_row      = $first instanceof Axismundi_Actor ? axismundi_actors_get_asset_cache_row( $first->get_identity_id(), 'header' ) : null;
	$header_manifest = is_array( $header_row ) ? json_decode( (string) $header_row['variants_json'], true ) : array();
	ax_asset_assert( $ax_asset_results, 'a header smaller than both caps is normalized once and never upscaled', true === $header_fetch && array( 640 ) === array_keys( $header_manifest ) && 400 === (int) $header_manifest[640]['width'] );

	$second = ax_asset_actor( 'asset_two' );
	if ( $second instanceof Axismundi_Actor ) {
		$ax_asset_ids[] = $second->get_identity_id();
		axismundi_actors_sync_asset_source( $second->get_identity_id(), 'avatar', 'https://example.com/assets/avatar-copy.png' );
	}
	$second_fetch = $second instanceof Axismundi_Actor ? axismundi_actors_fetch_remote_asset( $second->get_identity_id(), 'avatar' ) : null;
	$second_row = $second instanceof Axismundi_Actor ? axismundi_actors_get_asset_cache_row( $second->get_identity_id(), 'avatar' ) : null;
	$references = '' !== $ax_asset_hash ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE content_hash = %s AND processor_version = %d", $ax_asset_hash, AXISMUNDI_ACTORS_ASSET_PROCESSOR_VERSION ) ) : 0; // phpcs:ignore WordPress.DB
	ax_asset_assert( $ax_asset_results, 'different source URIs and roles with identical bytes share one content-addressed directory', true === $second_fetch && is_array( $second_row ) && $ax_asset_hash === $second_row['content_hash'] && 3 === $references );
	$dry_run = axismundi_actors_purge_asset_cache( 'actor', $first instanceof Axismundi_Actor ? (string) $first->get_identity_id() : '', true );
	ax_asset_assert( $ax_asset_results, 'administrator purge preview reports mappings without mutating them', 2 === $dry_run['rows'] && 2 === count( axismundi_actors_asset_scope_rows( 'actor', (string) $first->get_identity_id() ) ) );

	if ( $first instanceof Axismundi_Actor ) {
		axismundi_actors_sync_asset_source( $first->get_identity_id(), 'avatar', 'https://example.com/assets/invalid.png' );
	}
	$stale = $first instanceof Axismundi_Actor ? axismundi_actors_get_asset_cache_row( $first->get_identity_id(), 'avatar' ) : null;
	$stale_url = $first instanceof Axismundi_Actor ? axismundi_actors_get_cached_asset_url( $first->get_identity_id(), 'avatar', 96 ) : '';
	ax_asset_assert( $ax_asset_results, 'a changed source becomes stale while the last good derivative remains renderable', is_array( $stale ) && 'stale' === $stale['fetch_status'] && $ax_asset_hash === $stale['content_hash'] && '' !== $stale_url );

	$ax_asset_invalid = true;
	$failed = $first instanceof Axismundi_Actor ? axismundi_actors_fetch_remote_asset( $first->get_identity_id(), 'avatar' ) : null;
	$after_failure = $first instanceof Axismundi_Actor ? axismundi_actors_get_asset_cache_row( $first->get_identity_id(), 'avatar' ) : null;
	ax_asset_assert( $ax_asset_results, 'invalid MIME is rejected with backoff without discarding the last good cache', is_wp_error( $failed ) && is_array( $after_failure ) && 'stale' === $after_failure['fetch_status'] && $ax_asset_hash === $after_failure['content_hash'] && (int) $after_failure['failure_count'] === 1 );

	foreach ( $ax_asset_ids as $identity_id ) {
		$wpdb->delete( $table, array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}
	$gc = axismundi_actors_asset_gc( false, 0 );
	ax_asset_assert( $ax_asset_results, 'GC removes an unreferenced content-hash directory after its grace period', $gc['directories'] >= 1 && ( ! is_array( $root ) || ! is_dir( $root['path'] ) ) );
} finally {
	remove_filter( 'pre_http_request', $ax_asset_http, 10 );
	wp_clear_scheduled_hook( 'axismundi_actors_process_asset_batch' );
	if ( null === $ax_asset_webp_original ) {
		delete_option( 'ax_actors_asset_webp_enabled' );
	} else {
		update_option( 'ax_actors_asset_webp_enabled', $ax_asset_webp_original, false );
	}
	foreach ( array_unique( $ax_asset_ids ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_asset_cache_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}
	if ( '' !== $ax_asset_hash ) {
		$cleanup_root = axismundi_actors_asset_content_root( $ax_asset_hash );
		if ( is_array( $cleanup_root ) ) {
			axismundi_actors_remove_asset_tree( $cleanup_root['path'] );
		}
	}
}

$ax_asset_failures = count( array_filter( $ax_asset_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_asset_results ), $ax_asset_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_asset_failures > 0 ? 1 : 0 );
}
exit( $ax_asset_failures > 0 ? 1 : 0 );
