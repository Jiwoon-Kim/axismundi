<?php
/** Negative control: remove an Account-bound registration in this process only. */
defined( 'ABSPATH' ) || exit( 1 );
( static function () : void {
$run = require __DIR__ . '/todo-mutation-fixture.php';
$registry = \Axismundi\Jmap\runtime_registry();
$property = new ReflectionProperty( $registry, 'methods' );
$methods = $property->getValue( $registry );
$mutated = $methods;
$mutated['Todo/get']['account_bound'] = false;
$property->setValue( $registry, $mutated );
try {
	$run( 'registration', 'Missing accountId cannot use own Account',
		static function () : void { require __DIR__ . '/audit-todo-poc.php'; },
		static function () use ( $property, $registry, $methods ) : bool {
			$current = $property->getValue( $registry );
			return true === $methods['Todo/get']['account_bound'] && false === $current['Todo/get']['account_bound'];
		}
	);
}
finally { $property->setValue( $registry, $methods ); }
} )();
