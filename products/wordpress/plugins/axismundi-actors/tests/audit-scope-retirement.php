<?php
/** Isolated DDL fixtures; never re-add retired columns to the live Actor table. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$table = $wpdb->prefix . 'ax_scope_audit_' . substr( md5( wp_generate_uuid4() ), 0, 12 );
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
};
$sql = static function ( string $sql ) use ( $wpdb ) {
	$result = $wpdb->query( $sql );
	if ( false === $result ) { throw new RuntimeException( $wpdb->last_error ); }
	return $result;
};
$assert( '1' === get_option( 'ax_actors_scope_retired' ), 'Live retirement receipt exists' );
$assert( ! in_array( 'actor_scope', $wpdb->get_col( 'SHOW COLUMNS FROM ' . axismundi_actors_actors_table() ), true ), 'Live schema has no scope column' );
$assert( is_wp_error( axismundi_actors_create_local( array( 'actor_scope' => 'user' ) ) ), 'Old creation classification is rejected rather than silently reinterpreted' );
try {
	$sql( $wpdb->prepare( 'CREATE TABLE %i LIKE %i', $table, axismundi_actors_actors_table() ) );
	$assert( axismundi_actors_drop_scope_columns( $table ), 'Fresh scope-free schema is accepted' );
	$sql( $wpdb->prepare( 'ALTER TABLE %i ADD actor_scope varchar(8) DEFAULT NULL, ADD KEY scope_type(actor_scope,actor_type)', $table ) );
	$columns = array_diff( $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) ), array( 'object_kind', 'actor_scope' ) );
	$names = implode( ',', array_map( static fn( string $name ) : string => chr(96) . $name . chr(96), $columns ) );
	$sql( $wpdb->prepare( "INSERT INTO %i ({$names}) SELECT {$names} FROM %i ORDER BY id LIMIT 5", $table, axismundi_actors_actors_table() ) );
	$sql( $wpdb->prepare( "UPDATE %i SET actor_scope='site'", $table ) );
	$before = $wpdb->get_results( $wpdb->prepare( "SELECT {$names} FROM %i ORDER BY id", $table ), ARRAY_A );
	$assert( axismundi_actors_drop_scope_columns( $table ), 'Upgrade drops the retired column and index' );
	$after = $wpdb->get_results( $wpdb->prepare( "SELECT {$names} FROM %i ORDER BY id", $table ), ARRAY_A );
	$assert( $before === $after, 'Every remaining Actor column is byte-identical after DDL' );
	$assert( array() === $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name='scope_type'", $table ) ), 'Retired index is absent' );
	$assert( axismundi_actors_drop_scope_columns( $table )
		&& $after === $wpdb->get_results( $wpdb->prepare( "SELECT {$names} FROM %i ORDER BY id", $table ), ARRAY_A ), 'Replay leaves rows unchanged' );
} finally {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
}
echo "PASS: {$checks} scope retirement checks\n";
