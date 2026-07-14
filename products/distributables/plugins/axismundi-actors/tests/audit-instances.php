<?php
/**
 * Remote instance (host / NodeInfo) ledger regression (dev-only; dist-excluded).
 * Fully isolated with an HTTP mock — no external network.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/routing.php';
require_once dirname( __DIR__ ) . '/includes/webfinger.php';
require_once dirname( __DIR__ ) . '/includes/remote-discovery.php';
require_once dirname( __DIR__ ) . '/includes/instances.php';

global $wpdb;
$ax_inst_results = array();
$ax_inst_software = 'mastodon';

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $cond Holds.
 * @return void
 */
function ax_inst_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * @param string $type Content type.
 * @param string $body Body.
 * @param int    $code Status.
 * @return array<string,mixed>
 */
function ax_inst_response( string $type, string $body, int $code = 200 ) : array {
	return array(
		'headers'  => array( 'content-type' => $type ),
		'body'     => $body,
		'response' => array( 'code' => $code, 'message' => 'OK' ),
	);
}

// Use DNS-resolvable reserved domains so wp_http_validate_url passes before the mock.
$ax_inst_http = static function ( $pre, array $args, string $url ) use ( &$ax_inst_software ) {
	if ( 'https://example.com/.well-known/nodeinfo' === $url ) {
		return ax_inst_response( 'application/json', wp_json_encode( array( 'links' => array(
			array( 'rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.0', 'href' => 'https://example.com/nodeinfo/2.0' ),
			array( 'rel' => 'http://nodeinfo.diaspora.software/ns/schema/2.1', 'href' => 'https://example.com/nodeinfo/2.1' ),
		) ) ) );
	}
	if ( 'https://example.com/nodeinfo/2.1' === $url ) {
		return ax_inst_response( 'application/json', wp_json_encode( array(
			'version'           => '2.1',
			'software'          => array( 'name' => $ax_inst_software, 'version' => '4.3.0' ),
			'openRegistrations' => true,
			'metadata'          => array( 'nodeName' => 'Social Example', 'nodeDescription' => '<b>A</b> test node.' ),
		) ) );
	}
	if ( 'https://example.net/.well-known/nodeinfo' === $url ) {
		return ax_inst_response( 'application/json', wp_json_encode( array( 'links' => array() ) ) );
	}
	return new WP_Error( 'ax_inst_unexpected', 'Unexpected fixture URL: ' . $url );
};

try {
	axismundi_actors_install();

	// Schema gate.
	$instances = axismundi_actors_instances_table();
	$idx = (array) $wpdb->get_col( "SHOW INDEX FROM {$instances} WHERE Key_name = 'host_hash'" ); // phpcs:ignore WordPress.DB
	ax_inst_assert( $ax_inst_results, 'v6 creates wp_ax_instances with a unique host_hash and records the version', ! empty( $idx ) && (int) get_option( 'ax_actors_db_version' ) >= 6 );

	add_filter( 'pre_http_request', $ax_inst_http, 10, 3 );

	// Discovery caches software/version/policy, preferring schema 2.1.
	$row = axismundi_actors_discover_remote_instance( 'example.com' );
	ax_inst_assert( $ax_inst_results, 'NodeInfo discovery caches software, version, schema, and registration policy', is_array( $row ) && 'mastodon' === $row['software_name'] && '4.3.0' === $row['software_version'] && '2.1' === $row['nodeinfo_schema'] && '1' === (string) $row['open_registrations'] && 'ok' === $row['fetch_status'] && 'A test node.' === $row['description'] );

	// One row per host: rediscovery updates in place.
	$ax_inst_software = 'akkoma';
	axismundi_actors_discover_remote_instance( 'example.com' );
	$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$instances} WHERE host_hash = %s", axismundi_actors_host_hash( 'example.com' ) ) ); // phpcs:ignore WordPress.DB
	$updated = axismundi_actors_get_instance( 'example.com' );
	ax_inst_assert( $ax_inst_results, 'rediscovery updates the same host row instead of duplicating', 1 === $count && 'akkoma' === $updated['software_name'] );

	// A host without a usable NodeInfo link records an error row (for later backoff).
	$noinfo = axismundi_actors_discover_remote_instance( 'example.net' );
	$noinfo_row = axismundi_actors_get_instance( 'example.net' );
	ax_inst_assert( $ax_inst_results, 'a host without a NodeInfo link is recorded as an error attempt', is_wp_error( $noinfo ) && is_array( $noinfo_row ) && 'error' === $noinfo_row['fetch_status'] );

	// The local host is never treated as a remote instance.
	$local = axismundi_actors_discover_remote_instance( axismundi_actors_webfinger_authority() );
	ax_inst_assert( $ax_inst_results, 'the local host is refused as a remote instance', is_wp_error( $local ) && null === axismundi_actors_get_instance( axismundi_actors_webfinger_authority() ) );

} finally {
	remove_filter( 'pre_http_request', $ax_inst_http, 10 );
	$wpdb->query( "DELETE FROM " . axismundi_actors_instances_table() . " WHERE host IN ('example.com','example.net')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- fixture cleanup, fixed hosts.
}

$ax_inst_failures = count( array_filter( $ax_inst_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_inst_results ), $ax_inst_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_inst_failures > 0 ? 1 : 0 );
}
exit( $ax_inst_failures > 0 ? 1 : 0 );
