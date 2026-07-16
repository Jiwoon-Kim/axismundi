<?php
/** Object retention lease regression (dev-only). */

defined( 'ABSPATH' ) || exit( 1 );

$ax_lease_results = array();
$ax_lease_suffix  = strtolower( wp_generate_password( 8, false, false ) );
$ax_lease_object  = 'https://remote.example/objects/' . $ax_lease_suffix;
$ax_lease_ref     = home_url( '/activities/' . wp_generate_uuid4() . '/' );

function ax_lease_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_op_install();
	$stored = axismundi_op_remote_object_store(
		array( 'id' => $ax_lease_object, 'type' => 'Note', 'attributedTo' => 'https://remote.example/users/author', 'content' => 'Lease fixture.' ),
		array( 'fetched_at' => gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ) )
	);
	global $wpdb;
	$wpdb->update( axismundi_op_remote_objects_table(), array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ), array( 'object_uri_hash' => hash( 'sha256', $ax_lease_object ) ) ); // phpcs:ignore WordPress.DB
	$added = axismundi_op_add_lease( $ax_lease_object, 'interaction', $ax_lease_ref );
	$again = axismundi_op_add_lease( $ax_lease_object, 'interaction', $ax_lease_ref );
	ax_lease_assert( $ax_lease_results, 'DB v3 creates an idempotent URI/reason/reference lease', is_array( $stored ) && $added && $again && 1 === axismundi_op_active_lease_count( $ax_lease_object ) );
	ax_lease_assert( $ax_lease_results, 'an active lease prevents expiry dry-run and deletion', 0 === axismundi_op_remote_objects_purge_expired( true ) && 0 === axismundi_op_remote_objects_purge_expired() && null !== axismundi_op_remote_object_get( $ax_lease_object ) );
	$released = axismundi_op_release_lease( $ax_lease_object, 'interaction', $ax_lease_ref );
	ax_lease_assert( $ax_lease_results, 'releasing the final lease makes the expired observation purgeable', $released && 0 === axismundi_op_active_lease_count( $ax_lease_object ) && 1 <= axismundi_op_remote_objects_purge_expired( true ) && 1 <= axismundi_op_remote_objects_purge_expired() && null === axismundi_op_remote_object_get( $ax_lease_object ) );
} finally {
	global $wpdb;
	$wpdb->delete( axismundi_op_object_leases_table(), array( 'object_uri_hash' => hash( 'sha256', $ax_lease_object ) ) ); // phpcs:ignore WordPress.DB
	$wpdb->delete( axismundi_op_remote_objects_table(), array( 'object_uri_hash' => hash( 'sha256', $ax_lease_object ) ) ); // phpcs:ignore WordPress.DB
}

$ax_lease_failures = count( array_filter( $ax_lease_results, static fn( bool $passed ) : bool => ! $passed ) );
printf( "\n== %d checks, %d failed ==\n", count( $ax_lease_results ), $ax_lease_failures ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
exit( $ax_lease_failures > 0 ? 1 : 0 );

