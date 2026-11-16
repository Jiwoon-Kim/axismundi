<?php
/** Named production-function mutations; every mutant has an unmodified exercised control. */
defined( 'ABSPATH' ) || exit( 1 );
$names = array( 'axismundi_act_migrate_community_group', 'axismundi_actors_migrate_community_profile', 'axismundi_act_community_migration_request', 'axismundi_op_project_community_moderators' );
$sources = array();
foreach ( $names as $name ) {
	$r = new ReflectionFunction( $name );
	$sources[$name] = implode( '', array_slice( file( $r->getFileName() ), $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1 ) );
}
$mutations = array(
	'authority' => array( array(
		'axismundi_act_migrate_community_group' => array( "! user_can( \$user, 'manage_options' )", 'false', 2 ),
		'axismundi_actors_migrate_community_profile' => array( "! user_can( \$user, 'manage_options' )", 'false', 1 ),
	), 'An ordinary operator cannot migrate profiles' ),
	'bootstrap' => array( array( 'axismundi_actors_migrate_community_profile' => array( "return axismundi_actors_write_moderators( \$fresh, \$person, \$person, true, hash( 'sha256', \$activity_uri ), \$activity_uri, true, \$user );", 'return true;', 1 ) ), 'member failure is exercised' ),
	'nonce' => array( array( 'axismundi_act_community_migration_request' => array( "if ( '1' !== \$input['confirm_migration'] || ! wp_verify_nonce( \$input['_wpnonce'], axismundi_act_community_migration_nonce( \$input['group_uri'], \$input['person_uri'] ) ) ) {", 'if ( false ) {', 1 ) ), 'Admin migration rejects invalid nonce' ),
	'projection' => array( array( 'axismundi_op_project_community_moderators' => array( 'axismundi_actors_is_community( $actor ) && ', '', 1 ) ), 'Legacy Group advertises no replacement before migration' ),
);
$caught = 0;
foreach ( $mutations as $label => [ $changes, $expected ] ) {
	foreach ( array( false, true ) as $mutated ) {
		$namespace = 'AxMigration_' . $label . ( $mutated ? '_mutant' : '_control' );
		$code = $sources;
		foreach ( $changes as $name => [ $needle, $replacement, $count ] ) {
			if ( $count !== substr_count( $code[$name], $needle ) ) { throw new RuntimeException( 'Ambiguous mutation anchor: ' . $label ); }
			if ( $mutated ) { $code[$name] = str_replace( $needle, $replacement, $code[$name] ); }
		}
		eval( 'namespace ' . $namespace . '; use \\Axismundi_Actor; use \\Axismundi_Activity; use \\WP_Error; ' . implode( "\n", $code ) );
		$hits = 0;
		$call = static function ( string $name ) use ( $namespace, &$hits ) { return static function ( ...$args ) use ( $namespace, $name, &$hits ) { ++$hits; return call_user_func_array( $namespace . '\\' . $name, $args ); }; };
		$error = null; ob_start();
		try {
			(static function ( $community_migrate, $community_migrate_request, $community_migration_projection ) { require __DIR__ . '/audit-community-migration.php'; })( $call( 'axismundi_act_migrate_community_group' ), $call( 'axismundi_act_community_migration_request' ), $call( 'axismundi_op_project_community_moderators' ) );
		} catch ( RuntimeException $e ) { $error = $e; } finally { ob_end_clean(); }
		if ( ! $hits || ( ! $mutated && $error ) ) { throw $error ?? new RuntimeException( 'Mutation control was not exercised' ); }
		if ( $mutated ) {
			if ( ! $error || $error->getMessage() !== $expected ) { throw new RuntimeException( 'MISSED ' . $label . ': ' . ( $error ? $error->getMessage() : 'survived' ) ); }
			echo 'CAUGHT ' . $label . "\n"; ++$caught;
		}
	}
}
printf( "== %d checks, 0 failed ==\n", $caught );
