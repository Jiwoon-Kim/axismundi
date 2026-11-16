<?php
/** Known-positive nested-control fixtures exercise isolated in-memory mutants. */
defined( 'ABSPATH' ) || exit( 1 );
$mutations = array(
	'owner' => array( '! axismundi_activitypub_bridge_group_advertises_collection( $group, $inner[\'target\'] )', 'false', 'Wrong collection owner is rejected' ),
	'person' => array( '\'Person\' !== $person->get_type()', 'false', 'Inner Group author is rejected' ),
	'idempotency' => array( '$key = hash( \'sha256\', $group->get_uri()', '$key = hash( \'sha256\', wp_generate_uuid4() . $group->get_uri()', 'Direct and announced delivery share one inner identity' ),
	'shadow-person' => array( 'if ( ! $announced && isset( $inner[\'id\'] ) )', 'if ( isset( $inner[\'id\'] ) )', 'Group announcement creates no independent Person Activity' ),
	'authorship' => array( '$author_verified = ! $announced;', '$author_verified = true;', 'Group-only evidence never authenticates the Person' ),
);
$function = 'axismundi_activitypub_bridge_receive_collection_control';
$reflection = new ReflectionFunction( $function );
$source = implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
$caught = 0;
foreach ( $mutations as $name => [ $needle, $replacement, $expected ] ) {
	if ( 1 !== substr_count( $source, $needle ) ) { throw new RuntimeException( 'MISSED ' . $name . ': ambiguous mutation anchor' ); }
	foreach ( array( false, true ) as $mutated ) {
		$namespace = 'AxControls_' . str_replace( '-', '_', $name ) . ( $mutated ? '_mutant' : '_control' );
		eval( 'namespace ' . $namespace . '; use \\WP_Error; use \\Axismundi_Actor; use \\Axismundi_Activity; ' . ( $mutated ? str_replace( $needle, $replacement, $source ) : $source ) );
		$hits = 0;
		$callback = static function ( ...$args ) use ( $namespace, $function, &$hits ) { ++$hits; return call_user_func_array( $namespace . '\\' . $function, $args ); };
		$run = static function ( $callback ) { $receive_control = $callback; require __DIR__ . '/audit-collection-controls.php'; };
		$error = null;
		ob_start();
		try { $run( $callback ); } catch ( RuntimeException $e ) { $error = $e; } finally { ob_end_clean(); }
		if ( $hits < 1 || ( ! $mutated && $error ) ) { throw $error ?? new RuntimeException( 'MISSED ' . $name . ': receiver not called' ); }
		if ( $mutated ) {
			if ( ! $error || $expected !== $error->getMessage() ) { throw new RuntimeException( 'MISSED ' . $name . ': ' . ( $error ? $error->getMessage() : 'survived' ) ); }
			++$caught; echo 'CAUGHT ' . $name . ': ' . $expected . "\n";
		}
	}
}
printf( "== %d checks, 0 failed ==\n", $caught );
