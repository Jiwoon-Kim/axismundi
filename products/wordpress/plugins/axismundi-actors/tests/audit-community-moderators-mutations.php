<?php
/** In-memory kernel mutants; every replacement is preceded by the same passing control fixture. */
defined( 'ABSPATH' ) || exit( 1 );
$mutations = array(
	'authority' => array( 'axismundi_actors_write_moderators', 'false === $rank', 'false', 'moderator_write', 'Non-moderator change is refused' ),
	'bootstrap' => array( 'axismundi_actors_write_moderators', '$initialized ||', 'false ||', 'moderator_write', 'Bootstrap cannot appoint a new Person after initialization' ),
	'local-order' => array( 'axismundi_actors_read_moderators', 'appointed_at ASC,id ASC', 'appointed_at DESC,id DESC', 'moderator_read', 'Local appointment order is oldest first' ),
	'remote-order' => array( 'axismundi_actors_read_moderators', 'position ASC,id ASC', 'position DESC,id DESC', 'moderator_read', 'Complete remote membership is reconciled' ),
	'profile' => array( 'axismundi_actors_read_moderators', '( $group->is_local() && ! axismundi_actors_is_community( $group ) )', 'false', 'moderator_read', 'Channel cannot acquire moderators' ),
	'domain' => array( 'axismundi_actors_remote_moderators_reference', '$origin( $uri ) === $origin( $group->get_uri() )', 'true', 'moderator_reference', 'A foreign-origin collection reference is refused' ),
	'fence' => array( 'axismundi_actors_sync_moderators', '$now[\'generation\'] !== $before[\'generation\']', 'false', 'moderator_sync', 'In-flight snapshot cannot resurrect after a newer signal' ),
	'structural' => array( 'axismundi_actors_sync_moderators', 'return $items;', '$items = array();', 'moderator_sync', 'Collection failure preserves the complete previous snapshot' ),
	'resolution' => array( 'axismundi_actors_read_moderators', '\'ready\' === $row[\'resolution\'] && ', '', 'moderator_read', 'Unresolved member allows progress but never leaks cached authority' ),
	'late-add' => array( 'axismundi_activitypub_bridge_receive_moderators', 'return axismundi_actors_request_moderators_sync( $group );', 'global $wpdb; $wpdb->insert(axismundi_actors_moderators_table(), array("group_id"=>$group->get_identity_id(),"person_uri"=>$control["object"],"person_hash"=>hash("sha256",$control["object"]),"position"=>999,"resolution"=>"ready")); return axismundi_actors_request_moderators_sync($group);', 'moderator_receive', 'Late Add cannot optimistically resurrect a remote moderator' ),
	'personal-block' => array( 'axismundi_act_apply_relation_activity', "'Block' === \$root['activity_type'] && ! empty( \$root['target_uri'] )", 'false', 'target_block_apply', 'Target-scoped Block never becomes a personal Block relation' ),
	'principal-leak' => array( 'axismundi_actors_sync_moderators', 'return true;', ' $wpdb->query($wpdb->prepare("INSERT IGNORE INTO %i (identity_id,principal_kind,principal_id,user_id,role,created_at,updated_at) VALUES (%d,\'wp_user\',%d,%d,\'owner\',%s,%s)", axismundi_actors_managers_table(), $id, get_current_user_id(), get_current_user_id(), current_time("mysql",true), current_time("mysql",true))); return true;', 'moderator_sync', 'No principal grants are created' ),
);
$caught = 0;
foreach ( $mutations as $name => [ $function, $needle, $replacement, $slot, $expected ] ) {
	$reflection = new ReflectionFunction( $function );
	$source = implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
	if ( 1 !== substr_count( $source, $needle ) ) { throw new RuntimeException( 'MISSED ' . $name . ': mutation anchor is not unique' ); }
	foreach ( array( false, true ) as $mutated ) {
		$namespace = 'AxModerators_' . str_replace( '-', '_', $name ) . ( $mutated ? '_mutant' : '_control' );
		eval( 'namespace ' . $namespace . '; use \\WP_Error; use \\Axismundi_Actor; use \\Axismundi_Activity; ' . ( $mutated ? str_replace( $needle, $replacement, $source ) : $source ) );
		$hits = 0;
		$callback = static function ( ...$args ) use ( $namespace, $function, &$hits ) { ++$hits; return call_user_func_array( $namespace . '\\' . $function, $args ); };
		$run = static function ( $slot, $callback ) { ${$slot} = $callback; require __DIR__ . '/audit-community-moderators.php'; };
		$error = null; ob_start();
		try { $run( $slot, $callback ); } catch ( RuntimeException $e ) { $error = $e; } finally { ob_end_clean(); }
		if ( $hits < 1 || ( ! $mutated && $error ) ) { throw $error ?? new RuntimeException( 'MISSED ' . $name . ': injected function was not called' ); }
		if ( $mutated ) {
			if ( ! $error || $expected !== $error->getMessage() ) { throw new RuntimeException( 'MISSED ' . $name . ': ' . ( $error ? $error->getMessage() : 'survived' ) ); }
			++$caught; echo 'CAUGHT ' . $name . ': ' . $expected . "\n";
		}
	}
}
printf( "== %d checks, 0 failed ==\n", $caught );
