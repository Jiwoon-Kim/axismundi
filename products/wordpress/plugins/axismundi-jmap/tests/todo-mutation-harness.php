<?php
/** Standalone: php tests/todo-mutation-harness.php (no WordPress or database). */
$run = require __DIR__ . '/todo-mutation-fixture.php';
$mode = $argv[1] ?? null;
if ( null !== $mode ) {
	$unexpected = new LogicException( 'Unexpected audit failure' );
	try {
		$run( $mode, 'Expected assertion', static function () use ( $mode, $unexpected ) : void {
			if ( 'noop' === $mode ) { return; }
			if ( 'unexpected' === $mode ) { throw $unexpected; }
			throw new RuntimeException( 'Expected assertion' );
		}, static function () use ( $mode ) : bool { return 'unapplied' !== $mode; } );
	} catch ( Throwable $error ) {
		if ( 'unexpected' === $mode && $error !== $unexpected ) { throw new RuntimeException( 'Exception identity lost' ); }
		throw $error;
	}
	exit( 0 );
}
foreach ( array( 'caught', 'noop', 'unexpected', 'unapplied' ) as $case ) {
	$output = array();
	exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' ' . escapeshellarg( $case ) . ' 2>&1', $output, $status );
	$text = implode( "\n", $output );
	$pass = 'caught' === $case;
	$marker = $pass ? 'CAUGHT caught: Expected assertion' : ( 'unexpected' === $case ? 'LogicException: Unexpected audit failure' : 'MISSED ' . $case );
	$has_summary = 1 === preg_match( '/(?:\A|\n)PASS: 1 mutation checks\z/', $text );
	if ( ( $pass ? 0 !== $status : 0 === $status ) || ! str_contains( $text, $marker ) || $pass !== $has_summary || ( ! $pass && str_contains( $text, 'PASS' ) ) ) {
		throw new RuntimeException( 'Harness case failed: ' . $case . "\n" . $text );
	}
}
echo "PASS mutation harness: caught, no-op, unexpected rethrow, unapplied\n";
