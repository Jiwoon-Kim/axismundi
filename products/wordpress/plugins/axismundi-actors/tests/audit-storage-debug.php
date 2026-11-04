<?php
/** Site Health observation must not write or disguise failed queries as zero. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { ++$checks; if ( ! $ok ) { throw new RuntimeException( $label ); } echo "PASS {$label}\n"; };
$original = get_current_user_id();
$users = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $users ) { throw new RuntimeException( 'An existing administrator is required.' ); }
$writes = array();
$observe = static function ( $sql ) use ( &$writes ) { if ( ! preg_match( '/^\s*(SELECT|SHOW)\b/i', $sql ) ) { $writes[] = $sql; } return $sql; };
try {
	wp_set_current_user( 0 );
	$assert( array( 'sentinel' => true ) === axismundi_actors_storage_debug( array( 'sentinel' => true ) ), 'Anonymous caller gets no diagnostics' );
	wp_set_current_user( (int) $users[0] );
	add_filter( 'query', $observe );
	try { $info = axismundi_actors_storage_debug( array( 'sentinel' => true ) ); } finally { remove_filter( 'query', $observe ); }
	$fields = $info['axismundi-actors-storage']['fields'];
	$assert( true === $info['sentinel'], 'Other Site Health sections survive' );
	$assert( AXISMUNDI_ACTORS_VERSION === $fields['Plugin version']['value'], 'Runtime version is reported' );
	$assert( wp_json_encode( get_option( 'ax_actors_db_version' ) ) === $fields['ax_actors_db_version']['value'], 'Stored database version is observed without coercion' );
	$assert( ! $writes, 'Diagnostic callback issues no writes' );
	$assert( false === strpos( $fields['ax_actors_next']['value'], '0' ), 'Absent staging table is not a zero count' );
	$assert( count( $fields ) === count( array_filter( $fields, static fn( $field ) => true === $field['private'] ) ), 'Diagnostics are excluded from default copied information' );
	$break = static fn( $sql ) => false !== strpos( $sql, 'SHOW TABLES LIKE' ) ? 'SELECT * FROM `ax_storage_debug_intentionally_missing_table`' : $sql;
	add_filter( 'query', $break );
	try { $broken = axismundi_actors_storage_debug( array() ); } finally { remove_filter( 'query', $break ); }
	$assert( 'query failed (existence unknown)' === $broken['axismundi-actors-storage']['fields']['ax_actors']['value'], 'Failed existence query is unknown, not missing or zero' );
	$assert( 'not applicable (body columns unavailable)' === $broken['axismundi-actors-storage']['fields']['Body invariant violations']['value'], 'Unavailable body is not a zero violation count' );
} finally { wp_set_current_user( $original ); }
echo "== {$checks} checks, 0 failed ==\n";
