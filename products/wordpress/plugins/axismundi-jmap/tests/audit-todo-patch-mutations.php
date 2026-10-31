<?php
/** In-memory PatchObject controls; never mutate product files or database rows. */
defined( 'ABSPATH' ) || exit( 1 );

( static function () : void {
	$run = require __DIR__ . '/todo-mutation-fixture.php';
	$reflection = new ReflectionFunction( 'Axismundi\\Jmap\\TodoPoc\\patch' );
	$source = implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
	$anchor = 'if ( $exists && patch_values_equal( $current, $updates->{$path} ) ) { continue; }';
	$check = static function ( callable $patch, bool $reject ) : void {
		$old = (object) array( 'id' => 'a', 'title' => 'Piano' );
		if ( ! $reject ) {
			try { $result = $patch( $old, clone $old, array( 'title' ), array( 'id' ) ); }
			catch ( \Axismundi\Jmap\MethodError $error ) {
				if ( 'invalidProperties' !== $error->type ) { throw $error; }
				throw new RuntimeException( 'whole-object round-trip', 0, $error );
			}
			if ( ! \Axismundi\Jmap\TodoPoc\patch_values_equal( $old, $result ) ) { throw new RuntimeException( 'whole-object round-trip' ); }
			return;
		}
		try { $patch( $old, (object) array( 'id' => 'changed' ), array( 'title' ), array( 'id' ) ); }
		catch ( \Axismundi\Jmap\MethodError $error ) {
			if ( 'invalidProperties' === $error->type && array( 'id' ) === $error->properties ) { return; }
			throw $error;
		}
		throw new RuntimeException( 'differing server-set value rejected' );
	};
	$baseline = 'Axismundi\\Jmap\\TodoPoc\\patch';
	$check( $baseline, false );
	$check( $baseline, true );
	foreach ( array(
		'echo' => array( 'if ( false ) { continue; }', false, 'whole-object round-trip' ),
		'reject' => array( 'if ( $exists ) { continue; }', true, 'differing server-set value rejected' ),
	) as $mode => [ $replacement, $reject, $expected ] ) {
		$count = 0;
		$mutant = str_replace( $anchor, $replacement, $source, $count );
		$name = 'patch_mutant_' . $mode;
		$mutant = str_replace( 'function patch(', 'function ' . $name . '(', $mutant );
		eval( 'namespace Axismundi\\Jmap\\TodoPoc; use Axismundi\\Jmap\\MethodError; use function Axismundi\\Jmap\\copy_json; ' . $mutant );
		$run( $mode, $expected,
			static function () use ( $check, $name, $reject ) : void { $check( 'Axismundi\\Jmap\\TodoPoc\\' . $name, $reject ); },
			static fn() : bool => 1 === $count
		);
	}
	echo "PASS: 2 mutation checks\n";
} )();
