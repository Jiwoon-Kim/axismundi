<?php
/** Exercise production upgrade orchestration with a failing installer, without DDL. */
defined( 'ABSPATH' ) || exit( 1 );
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { ++$checks; if ( ! $ok ) { throw new RuntimeException( $label ); } echo "PASS {$label}\n"; };
$source = '';
foreach ( array( 'axismundi_op_finish_local_listing_projection_upgrade', 'axismundi_op_maybe_upgrade' ) as $name ) {
	$reflection = new ReflectionFunction( $name );
	$lines = file( $reflection->getFileName() );
	$source .= implode( '', array_slice( $lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) ) . "\n";
}
$namespace = 'AxUpgrade' . str_replace( '-', '', wp_generate_uuid4() );
eval( 'namespace ' . $namespace . '; ' . <<<'PHP'
function get_option( $key, $default = false ) { return $GLOBALS[__NAMESPACE__]['options'][$key] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS[__NAMESPACE__]['options'][$key] = $value; }
function axismundi_op_backfill_local_object_listing_projection() { ++$GLOBALS[__NAMESPACE__]['rebuilds']; }
function axismundi_op_install() {
 ++$GLOBALS[__NAMESPACE__]['calls'];
 if ( ! $GLOBALS[__NAMESPACE__]['succeed'] ) { return false; }
 update_option( AXISMUNDI_OP_DB_VERSION_OPTION, AXISMUNDI_OP_DB_VERSION );
 update_option( 'ax_op_object_identifiers_migrated', '1' );
 return true;
}
PHP
. $source );
$GLOBALS[$namespace] = array( 'options' => array( AXISMUNDI_OP_DB_VERSION_OPTION => '11' ), 'calls' => 0, 'rebuilds' => 0, 'succeed' => false );
$finish = $namespace . '\axismundi_op_finish_local_listing_projection_upgrade';
$upgrade = $namespace . '\axismundi_op_maybe_upgrade';
try {
	$upgrade();
	$finish();
	$state = &$GLOBALS[$namespace];
	$assert( '11' === $state['options'][AXISMUNDI_OP_DB_VERSION_OPTION], 'Failed install remains pending after init' );
	$assert( ! isset( $state['options']['ax_op_object_identifiers_migrated'] ), 'Failure does not claim identifier migration' );
	$assert( 2 === $state['calls'] && 0 === $state['rebuilds'], 'Init uses full verified installer, not an isolated rebuild' );
	$state['options'][AXISMUNDI_OP_DB_VERSION_OPTION] = AXISMUNDI_OP_DB_VERSION;
	$upgrade();
	$assert( 3 === $state['calls'], 'A stale latest-version marker cannot hide pending identifiers' );
	$state['succeed'] = true;
	$upgrade();
	$assert( '1' === $state['options']['ax_op_object_identifiers_migrated'], 'Retry can complete identifier migration' );
	$assert( AXISMUNDI_OP_DB_VERSION === $state['options'][AXISMUNDI_OP_DB_VERSION_OPTION], 'Verified installer records completion' );
	$upgrade(); $finish();
	$assert( 4 === $state['calls'], 'Completed migration is not rerun' );
} finally { unset( $GLOBALS[$namespace] ); }
echo "== {$checks} checks, 0 failed ==\n";
