<?php
/** Actor advertising must match Activities' actual auto-accept policy. */
defined( 'ABSPATH' ) || exit( 1 );
$probe = require __DIR__ . '/follow-advertisement-fixtures.php';
$results = $probe();
foreach ( $results as $label => $ok ) {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	echo 'PASS: ' . $label . "\n";
}
echo 'PASS: ' . count( $results ) . " advertisement checks\n";
