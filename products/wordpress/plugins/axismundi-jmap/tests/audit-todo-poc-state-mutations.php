<?php
/** Negative control: hide change-log results without editing product files. */
defined( 'ABSPATH' ) || exit( 1 );
use function Axismundi\Jmap\TodoPoc\table;
( static function () : void {
$run = require __DIR__ . '/todo-mutation-fixture.php';
$hits = 0;
$mutate = static function ( string $sql ) use ( &$hits ) : string {
	if ( str_starts_with( $sql, 'SELECT * FROM `' . table( 'changes' ) . '` WHERE ' ) ) {
		++$hits;
		return str_replace( ' WHERE ', ' WHERE 1=0 AND ', $sql );
	}
	return $sql;
};
add_filter( 'query', $mutate );
try {
	$run( 'state', 'Create advances state and changes',
		static function () : void { require __DIR__ . '/audit-todo-poc.php'; },
		static function () use ( &$hits ) : bool { return $hits > 0; }
	);
}
finally { remove_filter( 'query', $mutate ); }
} )();
