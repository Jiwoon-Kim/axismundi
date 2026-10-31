<?php
/** Removing the Activities bridge must break all three previously divergent cases. */
defined( 'ABSPATH' ) || exit( 1 );
( static function () : void {
	$probe = require __DIR__ . '/follow-advertisement-fixtures.php';
	foreach ( $probe() as $label => $ok ) { if ( ! $ok ) { throw new RuntimeException( 'Baseline: ' . $label ); } }
	$hook = 'axismundi_op_actor_manually_approves_followers';
	$callback = 'axismundi_act_supply_actor_follow_approval';
	if ( ! remove_filter( $hook, $callback, 10 ) ) { throw new RuntimeException( 'MISSED: bridge was not removed' ); }
	try {
		$results = $probe();
		foreach ( array( 'site option matches effective approval', 'policy filter matches effective approval', 'Group matches effective approval' ) as $label ) {
			if ( false !== ( $results[ $label ] ?? null ) ) { throw new RuntimeException( 'MISSED: ' . $label ); }
			echo 'CAUGHT bridge removal: ' . $label . "\n";
		}
		if ( true !== $results['automatic Person advertises false'] ) { throw new RuntimeException( 'OP fallback changed' ); }
	} finally { add_filter( $hook, $callback, 10, 2 ); }
	echo "PASS: 3 mutation checks\n";
} )();
