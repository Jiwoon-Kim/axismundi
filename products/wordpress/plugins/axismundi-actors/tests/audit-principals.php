<?php
/** Development-only typed relation migration audit: wp eval-file. @package AxismundiActors */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$table = $wpdb->prefix . 'ax_principal_audit_' . strtolower( wp_generate_password( 8, false, false ) );
$checks = 0;
$filter = null;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	++$checks;
};
try {
	$assert( axismundi_actors_principals_ready(), 'Installed schema is ready' );
	$assert( null === axismundi_actors_resolve_principal( 'invalid', 1 ), 'Unknown subject namespace rejected' );
	$assert( null === axismundi_actors_resolve_principal( 'wp_user', 0 ), 'Invalid source rejected' );
	foreach ( array( 'ADD COLUMN', 'UPDATE', 'DROP PRIMARY KEY', 'ADD UNIQUE KEY', 'ADD KEY principal_role' ) as $failure ) {
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		$assert( false !== $wpdb->query( $wpdb->prepare( "CREATE TABLE %i (identity_id bigint unsigned NOT NULL, user_id bigint unsigned NOT NULL, role varchar(16) NOT NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY(identity_id,user_id)) ENGINE=InnoDB", $table ) ), 'Legacy fixture created' );
		$wpdb->query( $wpdb->prepare( "INSERT INTO %i VALUES (11,21,'owner','2020-01-01 00:00:00','2021-01-01 00:00:00'),(11,22,'editor','2020-02-01 00:00:00','2021-02-01 00:00:00')", $table ) );
		$before = $wpdb->get_results( $wpdb->prepare( 'SELECT identity_id,user_id,role,created_at,updated_at FROM %i ORDER BY user_id', $table ), ARRAY_A );
		$injected = false;
		$filter = static function ( string $query ) use ( $table, $failure, &$injected ) : string {
			if ( ! $injected && str_contains( $query, $table ) && str_contains( $query, $failure ) ) {
				$injected = true;
				return 'SELECT * FROM ax_intentionally_missing_principal_audit';
			}
			return $query;
		};
		add_filter( 'query', $filter );
		$old = $wpdb->suppress_errors();
		try {
			$result = axismundi_actors_upgrade_principal_relations( $table );
		} finally {
			$wpdb->suppress_errors( $old );
			remove_filter( 'query', $filter );
			$filter = null;
		}
		$assert( $injected && ! $result, 'Injected migration failure is reported: ' . $failure );
		$assert( axismundi_actors_upgrade_principal_relations( $table ), 'Partial migration can retry' );
		$assert( axismundi_actors_upgrade_principal_relations( $table ), 'Completed migration is replay safe' );
		$after = $wpdb->get_results( $wpdb->prepare( 'SELECT identity_id,user_id,role,created_at,updated_at FROM %i ORDER BY user_id', $table ), ARRAY_A );
		$assert( $before === $after, 'Legacy facts preserved' );
		$assert( 2 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE principal_kind='wp_user' AND principal_id=user_id", $table ) ), 'Legacy subjects backfilled' );
		$assert( false !== $wpdb->insert( $table, array( 'identity_id' => 11, 'principal_kind' => 'remote_actor', 'principal_id' => 21, 'user_id' => null, 'role' => 'viewer', 'created_at' => '2020-01-01 00:00:00', 'updated_at' => '2020-01-01 00:00:00' ) ), 'Same numeric subject ID has independent namespace' );
		$assert( axismundi_actors_principal_relation_schema_valid( $table ), 'Typed relation schema verified' );
	}
	echo "PASS: {$checks} principal checks\n";
} finally {
	if ( $filter ) {
		remove_filter( 'query', $filter );
	}
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
}
