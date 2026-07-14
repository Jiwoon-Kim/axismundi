<?php
/**
 * Remote WebFinger/Actor discovery regression. All HTTP is intercepted; no fixture
 * request leaves wp-env. Dev-only and dist-excluded.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/webfinger.php';
require_once dirname( __DIR__ ) . '/includes/remote-discovery.php';

global $wpdb;
$ax_remote_results = array();
$ax_remote_ids     = array();
$ax_remote_name    = 'Remote Alice';

/** @param array $results Accumulator. @param string $label Contract. @param bool $condition Holds. */
function ax_remote_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** @return array<string,mixed> HTTP response fixture. */
function ax_remote_response( string $type, string $body, int $code = 200 ) : array {
	return array(
		'headers'  => array( 'content-type' => $type ),
		'body'     => $body,
		'response' => array( 'code' => $code, 'message' => 'Fixture' ),
		'cookies'  => array(),
		'filename' => null,
	);
}

$ax_remote_http = static function ( $preempt, array $args, string $url ) use ( &$ax_remote_name ) {
	if ( str_starts_with( $url, 'https://example.com/.well-known/webfinger' ) ) {
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$acct = (string) ( $query['resource'] ?? '' );
		if ( 'acct:remote_alice@example.com' === $acct ) {
			return ax_remote_response(
				'application/jrd+json',
				wp_json_encode( array( 'subject' => $acct, 'links' => array( array( 'rel' => 'self', 'type' => 'application/activity+json', 'href' => 'https://example.com/users/alice' ) ) ) )
			);
		}
		if ( 'acct:id_mismatch@example.com' === $acct ) {
			return ax_remote_response(
				'application/jrd+json',
				wp_json_encode( array( 'subject' => $acct, 'links' => array( array( 'rel' => 'self', 'type' => 'application/activity+json', 'href' => 'https://example.com/users/mismatch' ) ) ) )
			);
		}
	}
	if ( 'https://example.com/users/alice' === $url ) {
		return ax_remote_response(
			'application/activity+json',
			wp_json_encode(
				array(
					'@context'          => 'https://www.w3.org/ns/activitystreams',
					'id'                => $url,
					'type'              => 'Person',
					'preferredUsername' => 'alice-example',
					'name'              => $ax_remote_name,
					'summary'           => '<p>Hello remote world.</p>',
					'url'               => 'https://example.com/@alice',
					'inbox'             => 'https://example.com/users/alice/inbox',
					'outbox'            => 'https://example.com/users/alice/outbox',
					'icon'              => array( 'type' => 'Image', 'url' => 'https://example.com/alice.png' ),
				)
			)
		);
	}
	if ( 'https://example.com/users/mismatch' === $url ) {
		return ax_remote_response(
			'application/activity+json',
			wp_json_encode( array( 'id' => 'https://evil.example/users/substitute', 'type' => 'Person', 'preferredUsername' => 'mallory', 'inbox' => 'https://evil.example/inbox', 'outbox' => 'https://evil.example/outbox' ) )
		);
	}
	if ( 'https://example.com/bad-type' === $url ) {
		return ax_remote_response( 'text/html', '{}' );
	}
	if ( 'https://example.com/too-large' === $url ) {
		return ax_remote_response( 'application/json', str_repeat( 'x', AXISMUNDI_ACTORS_REMOTE_MAX_BYTES ) );
	}
	if ( 'https://example.com/redirect' === $url ) {
		return ax_remote_response( 'application/json', '{}', 302 );
	}
	return new WP_Error( 'ax_remote_unexpected_http', 'Unexpected fixture URL: ' . $url );
};

try {
	add_filter( 'pre_http_request', $ax_remote_http, 10, 3 );

	$unsafe_local = axismundi_actors_normalize_remote_acct( 'alice@127.0.0.1' );
	$unsafe_http  = axismundi_actors_remote_get_json( 'http://example.com/actor', array( 'application/json' ) );
	ax_remote_assert( $ax_remote_results, 'private/local authorities and non-HTTPS URLs are rejected before fetch', is_wp_error( $unsafe_local ) && is_wp_error( $unsafe_http ) );

	$actor = axismundi_actors_discover_remote_actor( '@remote_alice@example.com' );
	ax_remote_assert( $ax_remote_results, 'WebFinger discovers and persists one remote Person', $actor instanceof Axismundi_Actor && ! $actor->is_local() && null === $actor->get_scope() && 'Person' === $actor->get_type() && 'https://example.com/users/alice' === $actor->get_uri() );
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_remote_ids[] = $actor->get_identity_id();
	}

	$rows = $actor instanceof Axismundi_Actor ? axismundi_actors_get_addresses( $actor->get_identity_id() ) : array();
	$acct_rows = array_values( array_filter( $rows, static fn( array $row ) : bool => 'acct' === $row['address_type'] ) );
	ax_remote_assert( $ax_remote_results, 'the requested acct is recorded only after successful Actor validation', 1 === count( $acct_rows ) && 'remote_alice@example.com' === $acct_rows[0]['address'] && ! empty( $acct_rows[0]['verified_at'] ) );

	$stored = $actor instanceof Axismundi_Actor ? axismundi_actors_get_by_uri( $actor->get_uri() ) : null;
	ax_remote_assert( $ax_remote_results, 'remote snapshot fields are normalized while the bounded payload remains available', $stored instanceof Axismundi_Actor && 'alice-example' === $stored->get_preferred_username() && 'Remote Alice' === $stored->get_display_name() && str_contains( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT payload_json FROM ' . axismundi_actors_actors_table() . ' WHERE identity_id = %d', $stored->get_identity_id() ) ), 'alice.png' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture assertion on custom table.

	$first_id       = $actor instanceof Axismundi_Actor ? $actor->get_identity_id() : 0;
	$ax_remote_name = 'Remote Alice Updated';
	$refreshed      = axismundi_actors_discover_remote_actor( 'acct:remote_alice@example.com' );
	ax_remote_assert( $ax_remote_results, 'rediscovery refreshes the same identity instead of duplicating it', $refreshed instanceof Axismundi_Actor && $first_id === $refreshed->get_identity_id() && 'Remote Alice Updated' === $refreshed->get_display_name() );

	$mismatch = axismundi_actors_discover_remote_actor( 'id_mismatch@example.com' );
	ax_remote_assert( $ax_remote_results, 'an Actor id that differs from the WebFinger self URI is rejected', is_wp_error( $mismatch ) && 'ax_actors_remote_identity' === $mismatch->get_error_code() && null === axismundi_actors_get_by_uri( 'https://evil.example/users/substitute' ) );

	$wrong_type = axismundi_actors_remote_get_json( 'https://example.com/bad-type', array( 'application/json' ) );
	$too_large  = axismundi_actors_remote_get_json( 'https://example.com/too-large', array( 'application/json' ) );
	$redirect   = axismundi_actors_remote_get_json( 'https://example.com/redirect', array( 'application/json' ) );
	ax_remote_assert( $ax_remote_results, 'content type, one-megabyte limit, and redirect denial are enforced', is_wp_error( $wrong_type ) && 'ax_actors_remote_content_type' === $wrong_type->get_error_code() && is_wp_error( $too_large ) && 'ax_actors_remote_size' === $too_large->get_error_code() && is_wp_error( $redirect ) && 'ax_actors_remote_status' === $redirect->get_error_code() );

	if ( $refreshed instanceof Axismundi_Actor ) {
		axismundi_actors_set_status( $refreshed->get_identity_id(), 'tombstone' );
		$tombstoned = axismundi_actors_discover_remote_actor( 'remote_alice@example.com' );
		ax_remote_assert( $ax_remote_results, 'rediscovery never resurrects a tombstoned remote identity', is_wp_error( $tombstoned ) && 'ax_actors_remote_conflict' === $tombstoned->get_error_code() );
	}
} finally {
	remove_filter( 'pre_http_request', $ax_remote_http, 10 );
	foreach ( array_unique( $ax_remote_ids ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_addresses_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$ax_remote_failures = count( array_filter( $ax_remote_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_remote_results ), $ax_remote_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_remote_failures > 0 ? 1 : 0 );
}
exit( $ax_remote_failures > 0 ? 1 : 0 );
