<?php
/** Repeat focused integration audits and compare stored row contents, development only. */
defined( 'ABSPATH' ) || exit( 1 );
if ( ! class_exists( 'WP_CLI' ) ) { throw new RuntimeException( 'Use wp eval-file.' ); }
global $wpdb;
$snapshot = static function () use ( $wpdb ) : array {
	$result = array();
	$tables = array( axismundi_actors_actors_table(), axismundi_actors_managers_table(), axismundi_actors_meta_table(), axismundi_actors_addresses_table(), axismundi_actors_signing_keys_table(), axismundi_actors_keys_table(), axismundi_actors_endpoints_table(), axismundi_actors_texts_table(), axismundi_actors_fetch_state_table(), axismundi_actors_asset_cache_table(), axismundi_actors_identity_relations_table(), axismundi_op_objects_table(), \Axismundi\Jmap\accounts_table(), \Axismundi\Jmap\policies_table() );
	foreach ( $tables as $table ) {
		$rows = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A );
		if ( null === $rows || $wpdb->last_error ) { throw new RuntimeException( 'Fixture snapshot failed.' ); }
		$result[ $table ] = array_map( static fn( $row ) => json_encode( $row, JSON_THROW_ON_ERROR ), $rows );
		sort( $result[ $table ], SORT_STRING );
	}
	return $result;
};
$audits = array( 'axismundi-jmap/tests/audit-accounts.php', 'axismundi-jmap/tests/audit-session.php', 'axismundi-actors/tests/audit-selection-adapter.php', 'axismundi-jmap/tests/audit-runtime.php', 'axismundi-activities/tests/audit-scheduled-publication.php' );
$checks = 0;
foreach ( array( 1, 2 ) as $round ) {
	foreach ( $audits as $audit ) {
		$before = $snapshot();
		$run = WP_CLI::runcommand( 'eval-file ' . escapeshellarg( WP_PLUGIN_DIR . '/' . $audit ), array( 'launch' => true, 'return' => 'all', 'exit_error' => false ) );
		$after = $snapshot();
		foreach ( $before as $table => $rows ) { if ( $rows !== $after[ $table ] ) { throw new RuntimeException( 'Fixture rows changed in ' . $table . ' after ' . $audit ); } }
		if ( 0 !== $run->return_code ) { throw new RuntimeException( $audit . " failed:\n" . $run->stdout . $run->stderr ); }
		++$checks;
		printf( "PASS round %d: %s preserves all fixture tables\n", $round, $audit );
	}
}
printf( "PASS: %d runtime fixture cleanup checks\n", $checks );
