<?php
/**
 * DB v10a — Actor public-key keyring + remote fetch-state regression (dev-only).
 * No network: exercises payload key extraction, keyring rotation, and fetch bookkeeping.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/routing.php';
require_once dirname( __DIR__ ) . '/includes/webfinger.php';
require_once dirname( __DIR__ ) . '/includes/remote-discovery.php';

global $wpdb;
$ax_key_results = array();
$ax_key_ids     = array();

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $cond Holds.
 * @return void
 */
function ax_key_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * A structurally valid (not cryptographic) PEM public key block.
 *
 * @param string $seed Filler to vary blocks.
 * @return string
 */
function ax_key_pem( string $seed ) : string {
	return "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8" . $seed . "\n-----END PUBLIC KEY-----";
}

/**
 * A minimally valid remote Actor record with an optional publicKey.
 *
 * @param string     $slug Username / URI segment.
 * @param array|null $key  publicKey object, or null to omit.
 * @return array<string,mixed>
 */
function ax_key_record( string $slug, ?array $key ) : array {
	$uri     = 'https://example.com/users/' . $slug;
	$payload = array(
		'id'                => $uri,
		'type'              => 'Person',
		'preferredUsername' => $slug,
		'inbox'             => $uri . '/inbox',
		'outbox'            => $uri . '/outbox',
	);
	if ( null !== $key ) {
		$payload['publicKey'] = $key;
	}
	return axismundi_actors_normalize_remote_actor_payload( $payload, $uri );
}

try {
	axismundi_actors_install();
	$keys_table  = axismundi_actors_keys_table();
	$state_table = axismundi_actors_fetch_state_table();

	// Schema gate.
	$key_idx      = (array) $wpdb->get_col( "SHOW INDEX FROM {$keys_table} WHERE Key_name = 'key_uri_hash'" ); // phpcs:ignore WordPress.DB
	$state_cols   = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$state_table}" ); // phpcs:ignore WordPress.DB
	ax_key_assert( $ax_key_results, 'v10 creates the keyring (unique key_uri_hash) and fetch_state, records the version', ! empty( $key_idx ) && in_array( 'next_refresh_at', $state_cols, true ) && (int) get_option( 'ax_actors_db_version' ) >= 10 );

	// Extraction: valid key kept; owner mismatch and missing PEM rejected.
	$uri   = 'https://example.com/users/ax_key_a';
	$good  = axismundi_actors_extract_keys_from_payload( array( 'publicKey' => array( 'id' => $uri . '#main-key', 'owner' => $uri, 'publicKeyPem' => ax_key_pem( 'A' ) ) ), $uri );
	$wrong = axismundi_actors_extract_keys_from_payload( array( 'publicKey' => array( 'id' => $uri . '#main-key', 'owner' => 'https://evil.example/users/x', 'publicKeyPem' => ax_key_pem( 'A' ) ) ), $uri );
	$nopem = axismundi_actors_extract_keys_from_payload( array( 'publicKey' => array( 'id' => $uri . '#main-key', 'owner' => $uri ) ), $uri );
	ax_key_assert( $ax_key_results, 'key extraction keeps an owned key and rejects a foreign owner or missing PEM', 1 === count( $good ) && $uri . '#main-key' === $good[0]['key_uri'] && array() === $wrong && array() === $nopem );

	// Discovery persistence: the active key lands in the keyring.
	$a1  = axismundi_actors_upsert_remote( ax_key_record( 'ax_key_a', array( 'id' => $uri . '#main-key', 'owner' => $uri, 'publicKeyPem' => ax_key_pem( 'A' ) ) ) );
	$ax_key_ids[] = $a1 instanceof Axismundi_Actor ? $a1->get_identity_id() : 0;
	$active = $a1 instanceof Axismundi_Actor ? axismundi_actors_get_keys( $a1->get_identity_id(), 'active' ) : array();
	ax_key_assert( $ax_key_results, 'upsert_remote stores the declared public key as active', 1 === count( $active ) && ax_key_pem( 'A' ) === $active[0]['public_key_pem'] );

	// Rotation: a new key URI retires the old key, never deleting the history.
	axismundi_actors_upsert_remote( ax_key_record( 'ax_key_a', array( 'id' => $uri . '#key-2', 'owner' => $uri, 'publicKeyPem' => ax_key_pem( 'B' ) ) ) );
	$active_after  = axismundi_actors_get_keys( $a1->get_identity_id(), 'active' );
	$retired_after = axismundi_actors_get_keys( $a1->get_identity_id(), 'retired' );
	ax_key_assert( $ax_key_results, 'rotating to a new key URI retires the old key and keeps it as history', 1 === count( $active_after ) && $uri . '#key-2' === $active_after[0]['key_uri'] && 1 === count( $retired_after ) && $uri . '#main-key' === $retired_after[0]['key_uri'] );

	// A refresh that declares no key is a no-op — a partial fetch must not wipe a key.
	axismundi_actors_upsert_remote( ax_key_record( 'ax_key_a', null ) );
	$active_noop = axismundi_actors_get_keys( $a1->get_identity_id(), 'active' );
	ax_key_assert( $ax_key_results, 'a keyless refresh does not wipe the known active key', 1 === count( $active_noop ) && $uri . '#key-2' === $active_noop[0]['key_uri'] );

	// Fetch-state: success records the validators + a horizon and zeroes failures.
	axismundi_actors_record_fetch_success( $a1->get_identity_id(), array( 'payload_hash' => str_repeat( 'a', 64 ), 'etag' => 'W/"abc"', 'last_modified' => 'Mon, 01 Jan 2024 00:00:00 GMT' ) );
	$ok_state = axismundi_actors_get_fetch_state( $a1->get_identity_id() );
	ax_key_assert(
		$ax_key_results,
		'a successful fetch records the payload hash + validators, a horizon, and zero failures',
		is_array( $ok_state ) && str_repeat( 'a', 64 ) === $ok_state['payload_hash'] && 'W/"abc"' === $ok_state['etag'] && 0 === (int) $ok_state['failure_count'] && ! empty( $ok_state['next_refresh_at'] )
	);

	// Consecutive failures increment the count and push next_refresh out (exponential backoff).
	axismundi_actors_record_fetch_failure( $a1->get_identity_id(), 'ax_actors_remote_status' );
	$fail1 = axismundi_actors_get_fetch_state( $a1->get_identity_id() );
	axismundi_actors_record_fetch_failure( $a1->get_identity_id(), 'ax_actors_remote_status' );
	$fail2 = axismundi_actors_get_fetch_state( $a1->get_identity_id() );
	ax_key_assert(
		$ax_key_results,
		'each failure increments the count and backs the next refresh further out, keeping the last snapshot',
		1 === (int) $fail1['failure_count'] && 2 === (int) $fail2['failure_count'] && 'ax_actors_remote_status' === $fail2['last_error_code'] && $fail2['next_refresh_at'] > $fail1['next_refresh_at'] && str_repeat( 'a', 64 ) === $fail2['payload_hash']
	);

	// PEM shape gate rejects a non-key blob.
	ax_key_assert( $ax_key_results, 'the PEM shape gate rejects a non-key blob', axismundi_actors_is_public_key_pem( ax_key_pem( 'Z' ) ) && ! axismundi_actors_is_public_key_pem( 'not a key' ) );

} finally {
	foreach ( array_unique( array_filter( $ax_key_ids ) ) as $iid ) {
		$wpdb->delete( axismundi_actors_keys_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_fetch_state_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$ax_key_failures = count( array_filter( $ax_key_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_key_results ), $ax_key_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_key_failures > 0 ? 1 : 0 );
}
exit( $ax_key_failures > 0 ? 1 : 0 );
