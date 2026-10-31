<?php
/** PatchObject value and path contract, without database fixtures. */
defined( 'ABSPATH' ) || exit( 1 );
use Axismundi\Jmap\MethodError;
use function Axismundi\Jmap\TodoPoc\{patch, patch_values_equal};

( static function () : void {
	$checks = 0;
	$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
		++$checks;
		if ( ! $ok ) { throw new RuntimeException( $label ); }
	};
	$old = (object) array( 'id' => 'a', 'title' => 'Piano', 'myRights' => (object) array( 'mayRead' => true, 'mayWrite' => false ) );
	$echo = (object) array( 'myRights' => (object) array( 'mayWrite' => false, 'mayRead' => true ), 'id' => 'a', 'title' => 'New' );
	$result = patch( $old, $echo, array( 'title' ), array( 'id', 'myRights' ) );
	$assert( 'New' === $result->title && 'Piano' === $old->title, 'Echo ignores object order and isolates the original' );
	$result = patch( $old, (object) array( 'myRights/mayRead' => true ), array( 'title' ), array( 'myRights' ) );
	$assert( patch_values_equal( $old, $result ), 'Identical nested computed property accepted' );
	foreach ( array(
		array( 'id', 1 ), array( 'id', null ), array( 'myRights/mayRead', 1 ),
		array( 'myRights/missing', null ), array( 'unknown', null ),
		array( 'myRights', (object) array( 'mayRead' => true ) ),
	) as [ $path, $value ] ) {
		$error = null;
		try { patch( $old, (object) array( 'title' => 'Rejected', $path => $value ), array( 'title' ), array( 'id', 'myRights' ) ); }
		catch ( MethodError $caught ) { $error = $caught; }
		$assert( $error && 'invalidProperties' === $error->type && array( $path ) === $error->arguments()->properties && 'Piano' === $old->title, 'Invalid echo rejected atomically: ' . $path );
	}
	foreach ( array(
		(object) array( 'myRights' => $old->myRights, 'myRights/mayRead' => true ),
		(object) array( 'myRights/~2' => true ),
	) as $updates ) {
		$error = null;
		try { patch( $old, $updates, array( 'title' ), array( 'myRights' ) ); }
		catch ( MethodError $caught ) { $error = $caught; }
		$assert( $error && 'invalidPatch' === $error->type, 'Echo does not bypass invalid path checks' );
	}
	$assert( ! patch_values_equal( (object) array(), array() ), 'JSON object differs from array' );
	$assert( ! patch_values_equal( array( true, false ), array( false, true ) ), 'JSON array ordering is significant' );
	echo "PASS: {$checks} patch checks\n";
} )();
