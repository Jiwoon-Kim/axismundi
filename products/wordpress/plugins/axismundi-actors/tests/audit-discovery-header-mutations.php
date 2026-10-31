<?php
/** Removing either response's CORS header must fail its contract. No disk mutation. */
defined( 'ABSPATH' ) || exit( 1 );
( static function () : void {
	$probe = require __DIR__ . '/discovery-header-fixtures.php';
	$probe();
	foreach ( array( 'webfinger', 'nodeinfo' ) as $kind ) {
		$caught = false;
		try { $probe( true, $kind ); }
		catch ( RuntimeException $error ) {
			if ( $kind . ' CORS response' !== $error->getMessage() ) { throw $error; }
			$caught = true;
			echo 'CAUGHT ' . $kind . ': ' . $error->getMessage() . "\n";
		}
		if ( ! $caught ) { throw new RuntimeException( 'MISSED ' . $kind ); }
	}
	echo "PASS: 2 mutation checks\n";
} )();
