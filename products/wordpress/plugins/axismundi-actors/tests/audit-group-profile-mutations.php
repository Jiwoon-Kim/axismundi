<?php
/** Known-positive controls and isolated in-memory policy mutants. */
defined( 'ABSPATH' ) || exit( 1 );
$mutations = array(
	'type' => array( 'axismundi_actors_validate_group_profile', "'Group' !== \$type", 'false', 'validate_profile', 'Non-Group profile rejected' ),
	'channel' => array( 'axismundi_actors_validate_group_profile', "'community' !== \$args['federation_profile']", 'false', 'validate_profile', 'Channel profile rejected' ),
	'immutable' => array( 'axismundi_actors_check_group_profile_update', "array_key_exists( 'federation_profile', \$args )", 'false', 'check_profile_update', 'Existing profile cannot change' ),
);
$caught = 0;
foreach ( $mutations as $name => [ $function, $needle, $replacement, $slot, $expected ] ) {
	$reflection = new ReflectionFunction( $function );
	$source = implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
	if ( 1 !== substr_count( $source, $needle ) ) { throw new RuntimeException( 'Mutation anchor missing: ' . $name ); }
	foreach ( array( false, true ) as $mutated ) {
		$namespace = 'AxGroupProfile_' . $name . ( $mutated ? '_mutant' : '_control' );
		eval( 'namespace ' . $namespace . '; use \\WP_Error; ' . ( $mutated ? str_replace( $needle, $replacement, $source ) : $source ) );
		$run = static function ( string $slot, string $callback ) { ${$slot} = $callback; require __DIR__ . '/audit-group-profile.php'; };
		$error = null;
		ob_start();
		try { $run( $slot, $namespace . '\\' . $function ); } catch ( RuntimeException $e ) { $error = $e; } finally { ob_end_clean(); }
		if ( ! $mutated && $error ) { throw $error; }
		if ( $mutated ) {
			if ( ! $error || $expected !== $error->getMessage() ) { throw new RuntimeException( 'MISSED ' . $name . ': ' . ( $error ? $error->getMessage() : 'survived' ) ); }
			++$caught; echo 'CAUGHT ' . $name . "\n";
		}
	}
}
printf( "== %d checks, 0 failed ==\n", $caught );
