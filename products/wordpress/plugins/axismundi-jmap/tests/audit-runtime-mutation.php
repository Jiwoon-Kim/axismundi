<?php
/** Run with --skip-plugins=axismundi-jmap; mutants exist only in this PHP process. */
defined( 'ABSPATH' ) || exit( 1 );
if ( function_exists( 'Axismundi\\Jmap\\server' ) ) { throw new RuntimeException( 'Skip JMAP before loading a runtime mutant.' ); }
$root = dirname( __DIR__ );
require_once $root . '/includes/bootstrap.php';
require_once $root . '/includes/integrations/wordpress/accounts.php';
require_once $root . '/includes/integrations/wordpress/account-store.php';
$mode = getenv( 'AX_JMAP_MUTATION' );
$reflection = new ReflectionClass( \Axismundi\Jmap\SessionProjector::class );
$lines = file( $reflection->getFileName() );
$source = implode( '', array_slice( $lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
$source = str_replace( 'final class SessionProjector', 'final class MutantSessionProjector', $source );
$mutations = array(
	'writable' => array( '$writes && ! $grants[ $capability ]->writable', 'false' ),
	'policy' => array( "if ( null === \$policy || ! ( \$policy->enabled ?? false ) ) { continue; }", "if ( null === \$policy ) { \$policy = (object) array( 'enabled' => true, 'limits' => str_ends_with( \$uri, '/runtime' ) ? (object) array( 'maxItems' => 2 ) : new \\stdClass() ); }" ),
);
$runtime = file_get_contents( $root . '/includes/integrations/wordpress/runtime.php' );
if ( isset( $mutations[ $mode ] ) ) {
	$source = str_replace( $mutations[ $mode ][0], $mutations[ $mode ][1], $source, $count );
} elseif ( 'quota' === $mode ) {
	$runtime = str_replace( '$used <= $limit && $increase <= $limit - $used', 'true', $runtime, $count );
} elseif ( 'revocation' === $mode ) {
	$source = str_replace( '$account = $this->project_account( $actor, $user_id, $grants );', "static \$cache = array();\n\t\t\$key = \$account_id . ':' . \$capability;\n\t\tif ( isset( \$cache[ \$key ] ) ) { return \$cache[ \$key ]; }\n\t\t\$account = \$this->project_account( \$actor, \$user_id, \$grants );", $source, $count );
	$source = str_replace( "if ( \$writes && ! \$grants[ \$capability ]->writable ) { throw new MethodError( 'accountReadOnly' ); }", "if ( \$writes && ! \$grants[ \$capability ]->writable ) { throw new MethodError( 'accountReadOnly' ); }\n\t\t\$cache[ \$key ] = \$account;", $source );
} else { throw new RuntimeException( 'Unknown runtime mutation.' ); }
if ( 1 !== $count ) { throw new RuntimeException( 'Mutation anchor must match exactly once.' ); }
eval( 'namespace Axismundi\\Jmap; ' . $source );
$runtime = str_replace( 'SessionProjector', 'MutantSessionProjector', $runtime );
eval( substr( $runtime, 5 ) );
try {
	require __DIR__ . '/audit-runtime.php';
} catch ( RuntimeException $error ) {
	$expected = array(
		'writable' => 'Writable capability A does not authorize writes through read-only capability B',
		'policy' => 'Provider registration alone does not grant a capability',
		'quota' => 'WordPress PHP callers encounter the same limit',
		'revocation' => 'Policy revocation is rechecked between methods in one batch',
	);
	if ( $expected[ $mode ] !== $error->getMessage() ) { throw $error; }
	printf( "CAUGHT %s: %s\n", $mode, $error->getMessage() );
	return;
}
throw new RuntimeException( 'Runtime mutation survived: ' . $mode );
