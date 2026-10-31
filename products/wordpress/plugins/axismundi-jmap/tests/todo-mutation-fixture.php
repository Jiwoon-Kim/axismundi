<?php
/** Shared mutation runner; loading this file does not execute an audit. */
return static function ( string $mode, string $expected, callable $audit, callable $applied ) : void {
	try {
		$audit();
	} catch ( Throwable $error ) {
		if ( get_class( $error ) !== RuntimeException::class || $expected !== $error->getMessage() ) {
			throw $error;
		}
		if ( ! $applied() ) {
			throw new RuntimeException( 'MISSED ' . $mode . ': mutation was not applied', 0, $error );
		}
		printf( "CAUGHT %s: %s\nPASS: 1 mutation checks\n", $mode, $expected );
		return;
	}
	throw new RuntimeException( 'MISSED ' . $mode . ': mutant survived' );
};
