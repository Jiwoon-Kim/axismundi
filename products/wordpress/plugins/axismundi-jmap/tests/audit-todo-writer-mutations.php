<?php
/** Exercise writer echoes through real envelopes with process-local handler mutants. */
defined( 'ABSPATH' ) || exit( 1 );

( static function () : void {
	$run = require __DIR__ . '/todo-mutation-fixture.php';
	$source = static function ( string $name ) : string {
		$reflection = new ReflectionFunction( 'Axismundi\\Jmap\\TodoPoc\\' . $name );
		return implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
	};
	$registry = \Axismundi\Jmap\runtime_registry();
	$property = new ReflectionProperty( $registry, 'methods' );
	$original = $property->getValue( $registry );
	foreach ( array(
		'gate' => array(
			'! patch_values_equal( $old->shareWith, $new->shareWith )',
			'property_exists( $values, \'shareWith\' )',
			'Writer without mayAdmin whole-object round-trip',
		),
		'preserve' => array(
			'if ( ! $current_rights->mayAdmin && $existing ) { $shares = $existing[\'shares_json\']; }',
			'',
			'Writer echo preserves stored sharing map',
		),
	) as $mode => [ $anchor, $replacement, $expected ] ) {
		$count = 0;
		$save = str_replace( $anchor, $replacement, $source( 'save_object' ), $count );
		$save_name = 'save_writer_mutant_' . $mode;
		$set_name = 'set_writer_mutant_' . $mode;
		$save = str_replace( 'function save_object(', 'function ' . $save_name . '(', $save );
		$set = str_replace( array( 'function set_objects(', 'save_object(' ), array( 'function ' . $set_name . '(', $save_name . '(' ), $source( 'set_objects' ) );
		eval( 'namespace Axismundi\\Jmap\\TodoPoc; use Axismundi\\Jmap\\{Context, MethodError}; use function Axismundi\\Jmap\\{copy_json, valid_id}; ' . $save . $set );
		$methods = $original;
		$hits = 0;
		$methods['TodoList/set']['handler'] = static function ( $args, $context ) use ( $set_name, &$hits ) {
			++$hits;
			return ( 'Axismundi\\Jmap\\TodoPoc\\' . $set_name )( $args, $context, 'TodoList' );
		};
		$property->setValue( $registry, $methods );
		try {
			$run( $mode, $expected,
				static function () : void { require __DIR__ . '/audit-todo-poc.php'; },
				static function () use ( $count, &$hits ) : bool { return 1 === $count && $hits > 0; }
			);
		} finally { $property->setValue( $registry, $original ); }
	}
	echo "PASS: 2 mutation checks\n";
} )();
