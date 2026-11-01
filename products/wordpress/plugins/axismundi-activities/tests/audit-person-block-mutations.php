<?php
/** Four source-derived in-memory mutants; no product files are modified. */
defined( 'ABSPATH' ) || exit( 1 );
( static function () : void {
	$exercise = require __DIR__ . '/person-block-mutation-fixtures.php';
	$exercise( 'axismundi_act_record_source_activity', 'axismundi_act_reconcile_follow' );
	$extract = static function ( string $name ) : string {
		$r = new ReflectionFunction( $name );
		return implode( '', array_slice( file( $r->getFileName() ), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1 ) );
	};
	foreach ( array( 'close' => 'block-ends-follow', 'guard' => 'blocked-follow-denied', 'restore' => 'unblock-no-restore', 'reconcile' => 'reconcile-does-not-revive' ) as $mode => $expected ) {
		$namespace = 'AxPersonBlockMutation' . $mode;
		$count = 0;
		if ( 'reconcile' === $mode ) {
			$source = str_replace( '$relation = axismundi_act_get_relation(', 'return null; $relation = axismundi_act_get_relation(', $extract( 'axismundi_act_follow_termination_block' ), $count ) . $extract( 'axismundi_act_reconcile_follow' );
		} else {
			$apply = $extract( 'axismundi_act_apply_relation_activity' );
			if ( 'guard' === $mode ) {
				$anchor = "if ( \$blocked ) { return 'inbound' === \$normalized['direction'] ? array() : new WP_Error( 'ax_act_follow_blocked', 'A blocked Actor pair cannot start a Follow.' ); }";
				$source = str_replace( $anchor, '', $apply, $count );
			} else {
				$close = $extract( 'axismundi_act_close_block_follows' );
				$replacement = 'return array(); $events = array();';
				if ( 'restore' === $mode ) {
					$replacement = <<<'PHP'
$events = array();
if ( 'undone' === $block['effective_status'] ) {
    foreach ( array( array( $block['actor_uri'], $block['object_uri'] ), array( $block['object_uri'], $block['actor_uri'] ) ) as [ $subject, $object ] ) {
        $old = axismundi_act_get_relation( 'follow', $subject, $object );
        if ( is_array( $old ) ) { $events[] = axismundi_act_write_relation( 'follow', $subject, $object, $old['direction'], 'accepted', $old['initiating_activity_uri'], $block['activity_uri'] ); }
    }
    return $events;
}
PHP;
				}
				$source = str_replace( '$events = array();', $replacement, $close, $count ) . $apply;
			}
			$source .= $extract( 'axismundi_act_record_source_activity' );
		}
		if ( 1 !== $count ) { throw new RuntimeException( 'MISSED ' . $mode . ': source anchor changed' ); }
		$baseline_namespace = $namespace . 'Baseline';
		$baseline_source = 'reconcile' === $mode
			? $extract( 'axismundi_act_follow_termination_block' ) . $extract( 'axismundi_act_reconcile_follow' )
			: $extract( 'axismundi_act_close_block_follows' ) . $extract( 'axismundi_act_apply_relation_activity' ) . $extract( 'axismundi_act_record_source_activity' );
		$imports = '; use \\WP_Error; use \\Axismundi_Activity; use \\Axismundi_Actor; ';
		eval( 'namespace ' . $baseline_namespace . $imports . $baseline_source );
		$exercise( 'reconcile' === $mode ? 'axismundi_act_record_source_activity' : $baseline_namespace . '\\axismundi_act_record_source_activity', 'reconcile' === $mode ? $baseline_namespace . '\\axismundi_act_reconcile_follow' : 'axismundi_act_reconcile_follow' );
		eval( 'namespace ' . $namespace . $imports . $source );
		$hits = 0;
		$record = 'reconcile' === $mode ? 'axismundi_act_record_source_activity' : static function ( ...$args ) use ( $namespace, &$hits ) { ++$hits; return ( $namespace . '\\axismundi_act_record_source_activity' )( ...$args ); };
		$reconcile = 'reconcile' !== $mode ? 'axismundi_act_reconcile_follow' : static function ( $row ) use ( $namespace, &$hits ) { ++$hits; return ( $namespace . '\\axismundi_act_reconcile_follow' )( $row ); };
		$caught = false;
		try { $exercise( $record, $reconcile ); }
		catch ( RuntimeException $error ) {
			if ( get_class( $error ) !== RuntimeException::class || $expected !== $error->getMessage() ) { throw $error; }
			if ( 0 === $hits ) { throw new RuntimeException( 'MISSED ' . $mode . ': mutant was not invoked', 0, $error ); }
			$caught = true;
			echo 'CAUGHT ' . $mode . ': ' . $expected . "\n";
		}
		if ( ! $caught ) { throw new RuntimeException( 'MISSED ' . $mode . ': mutant survived' ); }
	}
	echo "PASS: 4 mutation checks\n";
} )();
