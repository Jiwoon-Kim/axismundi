<?php
/** Repeated subprocess audits must preserve every pre-existing principal row. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
if ( ! class_exists( 'WP_CLI' ) ) {
	throw new RuntimeException( 'Run with wp eval-file.' );
}
global $wpdb;
$snapshot = static function () use ( $wpdb ) : array {
	$rows = $wpdb->get_results( $wpdb->prepare(
		'SELECT * FROM %i ORDER BY identity_id, principal_kind, principal_id',
		axismundi_actors_managers_table()
	), ARRAY_A );
	if ( null === $rows || '' !== $wpdb->last_error ) {
		throw new RuntimeException( 'Cannot snapshot principal rows.' );
	}
	return $rows;
};
$audits = array(
	'axismundi-actors/tests/audit-vocabulary.php',
	'axismundi-actors/tests/audit-actor-axis.php',
	'axismundi-media-library/tests/audit-folder-identity.php',
	'axismundi-forum/tests/audit-forum-moderation.php',
	'axismundi-calendar/tests/audit-event-attribution-axis.php',
	'axismundi-activities/tests/audit-immediate-publication.php',
	'axismundi-activities/tests/audit-post-create.php',
	'axismundi-activities/tests/audit-audience.php',
	'axismundi-activities/tests/audit-public-outbox.php',
	'axismundi-notifications/tests/audit-deliveries.php',
);
$checks = 0;
foreach ( array( 1, 2 ) as $round ) {
	foreach ( $audits as $audit ) {
		$before = $snapshot();
		$result = WP_CLI::runcommand( 'eval-file ' . escapeshellarg( WP_PLUGIN_DIR . '/' . $audit ), array(
			'launch' => true, 'return' => 'all', 'exit_error' => false,
		) );
		$after = $snapshot();
		if ( $before !== $after ) {
			throw new RuntimeException( sprintf( 'Principal fixture leak in %s (round %d): %d -> %d rows.', $audit, $round, count( $before ), count( $after ) ) );
		}
		if ( 0 !== $result->return_code ) {
			throw new RuntimeException( $audit . " failed:\n" . $result->stdout . $result->stderr );
		}
		++$checks;
		printf( "PASS: round %d %s preserves %d principal rows\n", $round, $audit, count( $before ) );
	}
}
printf( "PASS: %d principal fixture cleanup checks\n", $checks );
