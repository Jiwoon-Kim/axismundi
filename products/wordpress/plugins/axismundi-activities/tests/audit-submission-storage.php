<?php
/** Submission source isolation and replay-safe v12 copy, using disposable tables. @package AxismundiActivities */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $label ); }
};
$prefix = $wpdb->prefix;
$shadow = $prefix . 'audit_submission_' . strtolower( wp_generate_password( 8, false, false ) ) . '_';
$target = $shadow . 'ax_activity_submission';
$legacy = $shadow . 'v12';
$fault = static function ( string $sql ) use ( $target, $legacy ) : string {
	return str_contains( $sql, 'SELECT COUNT(*)' ) && str_contains( $sql, $target ) && str_contains( $sql, $legacy ) ? 'INVALID SQL SUBMISSION_VERIFY' : $sql;
};
$locks = array();
$observe = static function ( string $sql ) use ( &$locks ) : string {
	if ( str_contains( $sql, 'SELECT GET_LOCK(' ) ) { $locks[] = $sql; }
	return $sql;
};
$errors = $wpdb->suppress_errors();
try {
	$assert( axismundi_act_install(), 'Current submission schema installs and verifies' );
	$assert( false !== $wpdb->query( $wpdb->prepare( 'CREATE TABLE %i LIKE %i', $target, axismundi_act_submissions_table() ) ), 'Disposable source-neutral table created' );
	$assert( false !== $wpdb->query( $wpdb->prepare( "CREATE TABLE %i (
		post_id bigint unsigned NOT NULL PRIMARY KEY, intent_uuid char(36) NOT NULL UNIQUE,
		principal_user_id bigint unsigned NOT NULL, actor_identity_id bigint unsigned NOT NULL,
		actor_uri text NOT NULL, object_uri text NOT NULL, expected_version datetime NOT NULL,
		source_hash char(64) NOT NULL, audience_json longtext NOT NULL, scheduled_for datetime NOT NULL,
		state varchar(16) NOT NULL, error_code varchar(64) NOT NULL, updated_at datetime NOT NULL
	) ENGINE=InnoDB", $legacy ) ), 'Disposable v12 fixture created' );
	$row = array( 'post_id' => 41, 'intent_uuid' => wp_generate_uuid4(), 'principal_user_id' => 7,
		'actor_identity_id' => 9, 'actor_uri' => 'https://example.com/Actor/A', 'object_uri' => 'https://example.com/Object/A',
		'expected_version' => '2026-10-26 00:00:00', 'source_hash' => str_repeat( 'a', 64 ),
		'audience_json' => '{"to":["https://www.w3.org/ns/activitystreams#Public"],"cc":[]}',
		'scheduled_for' => '2026-10-27 00:00:00', 'state' => 'pending', 'error_code' => '', 'updated_at' => '2026-10-26 00:00:00' );
	$assert( false !== $wpdb->insert( $legacy, $row ), 'Legacy intent fixture inserted' );
	$assert( axismundi_act_migrate_pending_submissions( $legacy, $target ), 'Legacy intent copied' );
	$wpdb->prefix = $shadow;
	$copy = axismundi_act_get_activity_submission( 'wordpress-core', 'post', 41 );
	$assert( $copy && 'Create' === $copy['activity_type'] && $row['intent_uuid'] === $copy['intent_uuid'], 'Source and explicit Activity type preserved' );
	foreach ( $row as $field => $value ) {
		if ( 'post_id' !== $field ) { $assert( (string) $copy[ $field ] === (string) $value, 'Exact legacy field preserved: ' . $field ); }
	}
	$assert( axismundi_act_migrate_pending_submissions( $legacy, $target ), 'Copy is replay-safe' );
	foreach ( array( array( 'other-provider', 'post' ), array( 'wordpress-core', 'other-kind' ) ) as $source ) {
		$other = array_merge( $copy, array( 'intent_uuid' => wp_generate_uuid4(), 'source_owner' => $source[0], 'source_kind' => $source[1] ) );
		$assert( false !== $wpdb->insert( $target, $other ), 'Same integer ref coexists under a different source' );
		$assert( $other['intent_uuid'] === axismundi_act_get_activity_submission( $source[0], $source[1], 41 )['intent_uuid'], 'Lookup uses complete source tuple' );
	}
	$duplicate = array_merge( $copy, array( 'intent_uuid' => wp_generate_uuid4() ) );
	$assert( false === $wpdb->insert( $target, $duplicate ), 'Duplicate source triple rejected despite different UUID' );
	$assert( false !== $wpdb->replace( $target, $duplicate ), 'Replacing one source changes its generation' );
	$assert( 3 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $target ) ), 'Replacement leaves other providers intact' );
	$assert( ! axismundi_act_migrate_pending_submissions( $legacy, $target ), 'Conflicting migration fails closed instead of overwriting new intent' );
	$assert( $duplicate['intent_uuid'] === axismundi_act_scheduled_post_submission( 41 )['intent_uuid'], 'Conflict leaves new generation intact' );
	$assert( 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $legacy ) ), 'Legacy archive remains intact' );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $target ) );
	add_filter( 'query', $fault );
	try { $failed = axismundi_act_migrate_pending_submissions( $legacy, $target ); }
	finally { remove_filter( 'query', $fault ); }
	$assert( ! $failed && 0 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $target ) ), 'Verification failure rolls migration writes back' );
	$assert( axismundi_act_migrate_pending_submissions( $legacy, $target ), 'Migration retries after verification failure' );
	$current = axismundi_act_scheduled_post_submission( 41 );
	$consume = axismundi_act_submission_commit_callback( $current );
	$wrong_type = $consume( array( 'activity_type' => 'Like', 'actor_uri' => $current['actor_uri'], 'object_uri' => $current['object_uri'] ) );
	$assert( is_wp_error( $wrong_type ) && 'ax_act_pending_identity' === $wrong_type->get_error_code(), 'Consumption cannot change the submitted Activity type' );
	$wpdb->query( 'START TRANSACTION' );
	$assert( true === $consume( array( 'activity_type' => 'Create', 'actor_uri' => $current['actor_uri'], 'object_uri' => $current['object_uri'] ) ), 'Matching Create consumes the exact source generation' );
	$wpdb->query( 'ROLLBACK' );
	$assert( 'pending' === axismundi_act_scheduled_post_submission( 41 )['state'], 'Consumption participates in its caller transaction' );
	add_filter( 'query', $observe );
	try {
		foreach ( array( array( 'wordpress-core', 'post' ), array( 'other-provider', 'post' ), array( 'wordpress-core', 'other-kind' ) ) as $source ) {
			$assert( true === axismundi_act_submission_lock( array( 'source_owner' => $source[0], 'source_kind' => $source[1], 'source_ref' => 41 ), static fn() => true ), 'Source lock acquired' );
		}
	} finally { remove_filter( 'query', $observe ); }
	$assert( 3 === count( array_unique( $locks ) ), 'Same integer reference does not share owner/kind lock keys' );
	$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $target ) );
	$assert( ! array_intersect( array( 'post_id', 'activity_uri', 'local_uuid' ), $columns ), 'Storage has neither a Post-only key nor a published Activity identity' );
	printf( "PASS: %d submission storage checks\n", $checks );
} finally {
	remove_filter( 'query', $fault );
	remove_filter( 'query', $observe );
	$wpdb->query( 'ROLLBACK' );
	$wpdb->prefix = $prefix;
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $target ) );
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $legacy ) );
	$wpdb->suppress_errors( $errors );
}
