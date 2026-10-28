<?php
/** Dev-only subprocess mutations; no source files or historical rows are edited. */
defined( 'ABSPATH' ) || exit( 1 );
if ( ! class_exists( 'WP_CLI' ) ) { throw new RuntimeException( 'Run with wp eval-file.' ); }
$original = getenv( 'AX_ACTIVITY_VOCAB_MUTATION' );
$mutations = array(
	'type' => 'Standard intransitive types come from OP',
	'exclusion' => 'Arrive records without object',
	'question' => 'Question records without object',
);
try {
	foreach ( $mutations as $mutation => $message ) {
		putenv( 'AX_ACTIVITY_VOCAB_MUTATION=' . $mutation );
		$result = WP_CLI::runcommand( 'eval-file ' . escapeshellarg( __DIR__ . '/audit-vocabulary-contract.php' ), array( 'launch' => true, 'return' => 'all', 'exit_error' => false ) );
		if ( 0 === $result->return_code || ! str_contains( $result->stdout . $result->stderr, $message ) ) {
			throw new RuntimeException( 'Mutation not caught at its intended assertion: ' . $mutation . "\n" . $result->stdout . $result->stderr );
		}
		printf( "CAUGHT %s: %s\n", $mutation, $message );
	}
} finally {
	putenv( false === $original ? 'AX_ACTIVITY_VOCAB_MUTATION' : 'AX_ACTIVITY_VOCAB_MUTATION=' . $original );
}
