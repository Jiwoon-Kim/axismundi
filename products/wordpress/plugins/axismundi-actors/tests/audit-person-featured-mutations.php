<?php
/** Known-positive controls precede each isolated in-memory mutant. Product files are never edited. */
defined( 'ABSPATH' ) || exit( 1 );

$mutants = array(
	'authority' => array( 'axismundi_actors_write_featured', '! axismundi_actors_can_act_as( $actor, $user_id )', 'false', 'Membership writer rechecks curation authority', 'featured_write' ),
	'order' => array( 'axismundi_actors_read_featured', 'ORDER BY position ASC,id DESC', 'ORDER BY position DESC,id ASC', 'Remote orderedItems order is retained', 'featured_read' ),
	'resolution' => array( 'axismundi_actors_read_featured', '\'ready\' === $row[\'resolution\'] && ', '', 'Unresolved member is hidden despite a readable cached copy', 'featured_read' ),
	'item-progress' => array( 'axismundi_actors_sync_featured', '$items = axismundi_actors_fetch_featured_snapshot( $actor, $before[\'collection_uri\'] );', '$items = axismundi_actors_fetch_featured_snapshot( $actor, $before[\'collection_uri\'] ); if (!is_wp_error($items)) { foreach ($items as $member) { if ("ready" !== $member["resolution"]) { return new WP_Error("ax_featured_mutant", "All-or-nothing item resolution"); } } }', 'Item fetch failure permits complete membership progress', 'featured_sync' ),
	'structural-abort' => array( 'axismundi_actors_sync_featured', 'return $items;', '$items = array();', 'Fetch failure preserves the previous snapshot', 'featured_sync' ),
	'late-add' => array( 'axismundi_activitypub_bridge_receive_featured', '$result = axismundi_actors_request_featured_sync( $sender );', '$wpdb->insert( axismundi_actors_featured_table(), array( "actor_id" => $sender->get_identity_id(), "object_uri" => $payload["object"], "object_hash" => hash("sha256", $payload["object"]), "position" => 0, "added_at" => current_time("mysql", true) ) ); $result = axismundi_actors_request_featured_sync( $sender );', 'Late Add cannot revive a removed remote member', 'featured_receive' ),
);
$caught = 0;
$run_fixture = static function ( array $callbacks ) : void {
	extract( $callbacks, EXTR_SKIP );
	require __DIR__ . '/audit-person-featured.php';
};
foreach ( $mutants as $name => $mutation ) {
	[ $function, $needle, $replacement, $expected, $slot ] = $mutation;
	$reflection = new ReflectionFunction( $function );
	$lines = file( $reflection->getFileName() );
	$source = implode( '', array_slice( $lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
	if ( 1 !== substr_count( $source, $needle ) ) { throw new RuntimeException( 'MISSED ' . $name . ': source anchor is not unique' ); }
	foreach ( array( false, true ) as $mutated ) {
		$namespace = 'AxFeatured_' . str_replace( '-', '_', $name ) . ( $mutated ? '_mutant' : '_control' );
		$body = $mutated ? str_replace( $needle, $replacement, $source ) : $source;
		eval( 'namespace ' . $namespace . '; use \\WP_Error; use \\Axismundi_Actor; ' . $body );
		$featured_read = 'axismundi_actors_read_featured'; $featured_write = 'axismundi_actors_write_featured'; $featured_receive = 'axismundi_activitypub_bridge_receive_featured'; $featured_sync = 'axismundi_actors_sync_featured';
		$hits = 0;
		${$slot} = static function ( ...$args ) use ( $namespace, $function, &$hits ) { ++$hits; return call_user_func_array( $namespace . '\\' . $function, $args ); };
		$failure = null;
		ob_start();
		try { $run_fixture( compact( 'featured_read', 'featured_write', 'featured_receive', 'featured_sync' ) ); }
		catch ( RuntimeException $error ) { $failure = $error; }
		finally { ob_end_clean(); }
		if ( $hits < 1 ) { throw new RuntimeException( 'MISSED ' . $name . ': mutant was not called' ); }
		if ( ! $mutated && $failure ) { throw $failure; }
		if ( $mutated ) {
			if ( ! $failure ) { throw new RuntimeException( 'MISSED ' . $name . ': mutation survived' ); }
			if ( $expected !== $failure->getMessage() ) { throw $failure; }
			++$caught; echo 'CAUGHT ' . $name . ': ' . $expected . "\n";
		}
	}
}
printf( "== %d checks, 0 failed ==\n", $caught );
