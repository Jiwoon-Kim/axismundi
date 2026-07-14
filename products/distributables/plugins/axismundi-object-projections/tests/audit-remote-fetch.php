<?php
/**
 * Phase 4b - bounded remote fetch + metadata-only admin inspector (dev-only).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/remote-objects.php';
require_once dirname( __DIR__ ) . '/includes/remote-fetch.php';
require_once dirname( __DIR__ ) . '/includes/admin.php';

$ax_fetch_results = array();
$ax_fetch_url     = 'https://example.com/objects/ax-phase-4b';
$ax_fetch_signed  = 'https://example.com/objects/ax-signed';
$ax_fetch_mode    = 'success';
$ax_fetch_args    = array();
$ax_fetch_user    = get_current_user_id();
$GLOBALS['ax_fetch_mode'] = 'success';
$GLOBALS['ax_fetch_args'] = array();

/** @param array<bool> $results Results. @param string $label Label. @param bool $condition Condition. */
function ax_fetch_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Mock bounded HTTP responses. */
function ax_fetch_mock( $preempt, array $args, string $url ) {
	$GLOBALS['ax_fetch_args'] = $args;
	$mode = (string) $GLOBALS['ax_fetch_mode'];
	if ( 'signed' === $mode ) {
		return array( 'headers' => array( 'content-type' => 'application/activity+json' ), 'body' => '', 'response' => array( 'code' => 401, 'message' => 'Unauthorized' ), 'cookies' => array(), 'filename' => null );
	}
	if ( 'wrong-type' === $mode ) {
		return array( 'headers' => array( 'content-type' => 'text/html' ), 'body' => '<p>not json</p>', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	}
	if ( 'not-modified' === $mode ) {
		return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 304, 'message' => 'Not Modified' ), 'cookies' => array(), 'filename' => null );
	}
	$payload = array(
		'@context'  => 'https://www.w3.org/ns/activitystreams',
		'id'        => $url,
		'type'      => 'Note',
		'name'      => 'Fetched remote note',
		'content'   => '<p>Visible text.</p><img src="https://remote.invalid/tracker.jpg"><video src="https://remote.invalid/video.mp4"></video>',
		'sensitive' => true,
	);
	return array(
		'headers'  => array( 'content-type' => 'application/activity+json; charset=utf-8', 'etag' => '"ax-phase-4b"', 'last-modified' => 'Tue, 14 Jul 2026 10:00:00 GMT' ),
		'body'     => wp_json_encode( $payload ),
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'cookies'  => array(),
		'filename' => null,
	);
}

try {
	add_filter( 'pre_http_request', 'ax_fetch_mock', 10, 3 );
	$stored = axismundi_op_remote_object_fetch( $ax_fetch_url );
	ax_fetch_assert(
		$ax_fetch_results,
		'fetch uses a bounded no-redirect request with ActivityStreams Accept and no cookies',
		is_array( $stored )
			&& 0 === (int) $GLOBALS['ax_fetch_args']['redirection']
			&& AXISMUNDI_OP_REMOTE_PAYLOAD_MAX + 1 === (int) $GLOBALS['ax_fetch_args']['limit_response_size']
			&& false !== strpos( (string) $GLOBALS['ax_fetch_args']['headers']['Accept'], 'application/activity+json' )
			&& empty( $GLOBALS['ax_fetch_args']['cookies'] )
	);
	ax_fetch_assert( $ax_fetch_results, 'a valid response stores text metadata, tri-state sensitivity, validators, and expiry', is_array( $stored ) && 'Note' === $stored['object_type'] && 1 === (int) $stored['is_sensitive'] && '"ax-phase-4b"' === $stored['etag'] && ! empty( $stored['expires_at'] ) );

	$GLOBALS['ax_fetch_mode'] = 'not-modified';
	$not_modified             = axismundi_op_remote_object_fetch( $ax_fetch_url );
	ax_fetch_assert( $ax_fetch_results, 'conditional refresh sends validators and preserves the snapshot on 304', is_array( $not_modified ) && '"ax-phase-4b"' === (string) $GLOBALS['ax_fetch_args']['headers']['If-None-Match'] && 0 === (int) $not_modified['failure_count'] && (string) $stored['payload_hash'] === (string) $not_modified['payload_hash'] );

	$GLOBALS['ax_fetch_mode'] = 'wrong-type';
	$wrong_type               = axismundi_op_remote_object_fetch( $ax_fetch_url );
	$after_failure            = axismundi_op_remote_object_get( $ax_fetch_url );
	ax_fetch_assert( $ax_fetch_results, 'unsupported response MIME records backoff but preserves the last successful payload', is_wp_error( $wrong_type ) && 'ax_op_remote_fetch_content_type' === $wrong_type->get_error_code() && is_array( $after_failure ) && 1 === (int) $after_failure['failure_count'] && (string) $stored['payload_hash'] === (string) $after_failure['payload_hash'] );

	$GLOBALS['ax_fetch_mode'] = 'signed';
	$signed                   = axismundi_op_remote_object_fetch( $ax_fetch_signed );
	ax_fetch_assert( $ax_fetch_results, '401/403 becomes an explicit signed-fetch-not-supported result', is_wp_error( $signed ) && 'ax_op_remote_signed_fetch_required' === $signed->get_error_code() );

	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
	wp_set_current_user( isset( $admins[0] ) ? (int) $admins[0] : 0 );
	$_GET['object_uri'] = $ax_fetch_url;
	ob_start();
	axismundi_op_render_remote_admin_page();
	$admin_html = (string) ob_get_clean();
	unset( $_GET['object_uri'] );
	ax_fetch_assert( $ax_fetch_results, 'admin preview renders text metadata without any remote image/video/audio/embed element', false !== strpos( $admin_html, 'Visible text.' ) && false === stripos( $admin_html, '<img' ) && false === stripos( $admin_html, '<video' ) && false === stripos( $admin_html, '<audio' ) && false !== strpos( $admin_html, 'Metadata-only preview' ) );

	global $wpdb;
	$table = axismundi_op_remote_objects_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture makes its row due for expiry.
	$wpdb->update( $table, array( 'expires_at' => '2000-01-01 00:00:00' ), array( 'object_uri_hash' => hash( 'sha256', $ax_fetch_url ) ) );
	$dry_count = axismundi_op_remote_objects_purge_expired( true );
	$purged    = axismundi_op_remote_objects_purge_expired();
	ax_fetch_assert( $ax_fetch_results, 'expired metadata is counted and purged without touching a remote resource', 1 === $dry_count && 1 === $purged && null === axismundi_op_remote_object_get( $ax_fetch_url ) );
} finally {
	remove_filter( 'pre_http_request', 'ax_fetch_mock', 10 );
	axismundi_op_remote_object_delete( $ax_fetch_url );
	axismundi_op_remote_object_delete( $ax_fetch_signed );
	wp_set_current_user( $ax_fetch_user );
}

$ax_fetch_failures = count( array_filter( $ax_fetch_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_fetch_results ), $ax_fetch_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_fetch_failures > 0 ? 1 : 0 );
}
exit( $ax_fetch_failures > 0 ? 1 : 0 );
