<?php
/** Negative control: replace stored shares at the SQL read boundary, process-local only. */
defined( 'ABSPATH' ) || exit( 1 );
use function Axismundi\Jmap\TodoPoc\{principal_for_user, table};
( static function () : void {
$run = require __DIR__ . '/todo-mutation-fixture.php';
$hits = 0;
$mutate = static function ( string $sql ) use ( &$hits ) : string {
	global $wpdb;
	$needle = 'SELECT * FROM `' . table( 'lists' ) . '` WHERE';
	if ( ! str_contains( $sql, $needle ) || get_current_user_id() <= 0 ) { return $sql; }
	$id = principal_for_user( get_current_user_id() );
	if ( null === $id ) { return $sql; }
	$shares = wp_json_encode( (object) array( $id => (object) array( 'mayRead' => true, 'mayWrite' => true, 'mayAdmin' => true ) ) );
	++$hits;
	return str_replace( $needle, $wpdb->prepare( 'SELECT id,account_id,name,%s AS shares_json,revision FROM %i WHERE', $shares, table( 'lists' ) ), $sql );
};
add_filter( 'query', $mutate );
try {
	$run( 'rights', 'B receives read-only rights without share map',
		static function () : void { require __DIR__ . '/audit-todo-poc.php'; },
		static function () use ( &$hits ) : bool { return $hits > 0; }
	);
}
finally { remove_filter( 'query', $mutate ); }
} )();
