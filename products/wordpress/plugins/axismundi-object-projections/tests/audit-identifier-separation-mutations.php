<?php
/** In-memory regressions of lookup ownership and migration rollback. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
$mode = (string) getenv( 'AX_OP_ID_MUTATION' );
if ( 'lookup' === $mode ) {
	$name = 'axismundi_op_get_object_by_uri';
	$from = 'global $wpdb;';
	$to = 'return axismundi_actors_get_identity_by_uri( $uri );';
	$expected = 'ID, UUID, URI and provenance are preserved';
} elseif ( 'rollback' === $mode ) {
	$name = 'axismundi_op_migrate_object_identifiers';
	$from = '$wpdb->query( \'ROLLBACK\' );';
	$to = '$wpdb->query( \'COMMIT\' );';
	$expected = 'Failed migration rolls back copied rows and retains original anchors';
} else { throw new RuntimeException( 'Set AX_OP_ID_MUTATION to lookup or rollback.' ); }
$reflection = new ReflectionFunction( $name );
$source = implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
if ( 1 !== substr_count( $source, $from ) ) { throw new RuntimeException( 'Mutation anchor changed.' ); }
$mutant = 'axismundi_op_separation_mutant';
eval( str_replace( $from, $to, str_replace( $name . '(', $mutant . '(', $source ) ) );
$audit = str_replace( $name . '(', $mutant . '(', file_get_contents( __DIR__ . '/audit-identifier-separation.php' ) );
try { eval( '?>' . $audit ); }
catch ( RuntimeException $error ) {
	if ( $expected !== $error->getMessage() ) { throw $error; }
	echo 'CAUGHT ' . $mode . ': ' . $expected . "\n";
	return;
}
throw new RuntimeException( 'Identifier mutation survived.' );
