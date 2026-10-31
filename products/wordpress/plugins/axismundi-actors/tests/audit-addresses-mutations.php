<?php
/** Negative control: suppress handle INSERTs without modifying any product file. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );

// Run directly with wp eval-file. The ordinary audit must exit nonzero and name
// the registration/backfill failures even though acct synchronization still runs.
add_filter( 'query', static function ( string $sql ) : string {
	if ( str_starts_with( $sql, 'INSERT INTO ' ) && str_contains( $sql, 'ax_actor_addresses' ) && str_contains( $sql, "'local_handle'" ) ) {
		return 'SELECT 1';
	}
	return $sql;
} );
require __DIR__ . '/audit-addresses.php';
