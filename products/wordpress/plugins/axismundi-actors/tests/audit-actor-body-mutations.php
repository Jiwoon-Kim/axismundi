<?php
/** Mutations challenge allocator preservation and detail ownership. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
$mode = (string) getenv( 'AX_ACTOR_BODY_MUTATION' );
if ( 'allocator' === $mode ) {
	$name = 'axismundi_actors_migrate_body';
	$from = 'AUTO_INCREMENT={$next_id}';
	$to = 'AUTO_INCREMENT=1';
	$expected = 'Historical allocator high-water mark is preserved';
} elseif ( 'payload' === $mode ) {
	$name = 'axismundi_actors_get_remote_payload';
	$from = 'json_decode( $json, true )';
	$to = 'array()';
	$expected = 'Detail reads retain unrecognized JSON properties';
} else { throw new RuntimeException( 'Set AX_ACTOR_BODY_MUTATION to allocator or payload.' ); }
$reflection = new ReflectionFunction( $name );
$source = implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
if ( 1 !== substr_count( $source, $from ) ) { throw new RuntimeException( 'Actor body mutation anchor changed.' ); }
$mutant = 'axismundi_body_mutant';
eval( str_replace( $from, $to, str_replace( $name . '(', $mutant . '(', $source ) ) );
$audit = str_replace( $name . '(', $mutant . '(', file_get_contents( __DIR__ . '/audit-actor-body.php' ) );
try { eval( '?>' . $audit ); }
catch ( RuntimeException $error ) {
	if ( $expected !== $error->getMessage() ) { throw $error; }
	echo 'CAUGHT ' . $mode . ': ' . $expected . "\n";
	return;
}
throw new RuntimeException( 'Actor body mutation survived.' );
