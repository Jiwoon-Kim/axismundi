<?php
/**
 * Phase 5 / DB v5 — actor address ledger (handle routing + reservation) regression.
 * Dev-only; dist-excluded.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$ax_addr_results = array();
$ax_addr_ids     = array();
$ax_addr_users   = array();

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $cond Holds.
 * @return void
 */
function ax_addr_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_actors_install();
	$addresses = axismundi_actors_addresses_table();

	// Schema gate.
	$cols    = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$addresses}" ); // phpcs:ignore WordPress.DB
	$indexes = (array) $wpdb->get_col( "SHOW INDEX FROM {$addresses} WHERE Key_name = 'address_hash'" ); // phpcs:ignore WordPress.DB
	ax_addr_assert( $ax_addr_results, 'v5 creates the address ledger with a unique address_hash and records the version', in_array( 'address_hash', $cols, true ) && ! empty( $indexes ) && '5' === (string) get_option( 'ax_actors_db_version' ) );

	// register_handle writes a primary local_handle address the ledger can resolve.
	$u1 = (int) wp_insert_user( array( 'user_login' => 'ax_addr_alice', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_addr_users[] = $u1;
	$a1 = axismundi_actors_ensure_for_user( $u1 );
	$ax_addr_ids[] = $a1->get_identity_id();
	axismundi_actors_register_handle( $a1->get_identity_id(), 'addr_alice' );
	$rows = axismundi_actors_get_addresses( $a1->get_identity_id() );
	ax_addr_assert( $ax_addr_results, 'register_handle records a primary local_handle address', $a1->get_identity_id() === axismundi_actors_handle_owner( 'addr_alice' ) && 1 === count( $rows ) && 'primary' === $rows[0]['status'] && 'local_handle' === $rows[0]['address_type'] );

	// Backfill: an actor created with a handle but no address row gets one on install.
	$seed = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'preferred_username' => 'addr_seed' ) );
	$ax_addr_ids[] = $seed->get_identity_id();
	$before = axismundi_actors_handle_owner( 'addr_seed' );
	axismundi_actors_install();
	ax_addr_assert( $ax_addr_results, 'install() backfills a primary address for a pre-existing handle', 0 === $before && $seed->get_identity_id() === axismundi_actors_handle_owner( 'addr_seed' ) );

	// A retired/reserved handle is never recycled to another actor.
	$u2 = (int) wp_insert_user( array( 'user_login' => 'ax_addr_bob', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_addr_users[] = $u2;
	$a2 = axismundi_actors_ensure_for_user( $u2 );
	$ax_addr_ids[] = $a2->get_identity_id();
	$reserved = axismundi_actors_reserve_former_handle( $a1->get_identity_id(), 'addr_old' );
	$blocked  = axismundi_actors_register_handle( $a2->get_identity_id(), 'addr_old' );
	$same_ok  = axismundi_actors_reserve_former_handle( $a1->get_identity_id(), 'addr_old' );
	ax_addr_assert( $ax_addr_results, 'a reserved handle blocks another actor but not its owner', true === $reserved && $a1->get_identity_id() === axismundi_actors_handle_owner( 'addr_old' ) && is_wp_error( $blocked ) && 'ax_actors_handle_reserved' === $blocked->get_error_code() && true === $same_ok );

	// The acct: namespace never collides with the handle namespace.
	$acct_hash = axismundi_actors_address_hash( 'acct', 'ns_test' );
	$wpdb->insert( $addresses, array( 'identity_id' => $a1->get_identity_id(), 'address_type' => 'acct', 'address' => 'ns_test', 'address_hash' => $acct_hash, 'status' => 'primary', 'created_at' => current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB
	$u3 = (int) wp_insert_user( array( 'user_login' => 'ax_addr_carol', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_addr_users[] = $u3;
	$a3 = axismundi_actors_ensure_for_user( $u3 );
	$ax_addr_ids[] = $a3->get_identity_id();
	$handle_free = axismundi_actors_register_handle( $a3->get_identity_id(), 'ns_test' );
	ax_addr_assert( $ax_addr_results, 'an acct address does not reserve the same string as a handle', 0 === axismundi_actors_handle_owner( 'ns_test_zzz' ) && true === $handle_free && $a3->get_identity_id() === axismundi_actors_handle_owner( 'ns_test' ) );

} finally {
	foreach ( array_unique( $ax_addr_ids ) as $iid ) {
		$wpdb->delete( axismundi_actors_addresses_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( $ax_addr_users as $u ) {
		if ( get_userdata( $u ) ) {
			wp_delete_user( $u );
		}
	}
}

$ax_addr_failures = count( array_filter( $ax_addr_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_addr_results ), $ax_addr_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_addr_failures > 0 ? 1 : 0 );
}
exit( $ax_addr_failures > 0 ? 1 : 0 );
