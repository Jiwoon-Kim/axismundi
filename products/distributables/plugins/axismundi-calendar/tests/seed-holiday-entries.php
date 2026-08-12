<?php
/**
 * One-off: put the site's holiday datasets into the administrator's own calendar list.
 *
 * A system calendar is readable by everyone, which is not the same as being on anybody's screen.
 * Membership is per-Actor state and nothing has written it yet, so until a browse screen exists the
 * entries are seeded here rather than left to be wondered about.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( empty( $admins ) ) {
	echo "no administrator\n";
	return;
}
wp_set_current_user( (int) $admins[0] );
$actor = axismundi_cal_current_actor_uri();
if ( '' === $actor ) {
	echo "administrator has no Actor\n";
	return;
}
$table = axismundi_cal_calendars_table();
$rows  = (array) $wpdb->get_results( "SELECT id, name FROM {$table} WHERE kind = 'system' ORDER BY id ASC", ARRAY_A );
foreach ( $rows as $row ) {
	$existing = axismundi_cal_list_entry( (int) $row['id'], $actor );
	if ( is_array( $existing ) ) {
		printf( "already listed: %s\n", $row['name'] );
		continue;
	}
	$saved = axismundi_cal_list_set( (int) $row['id'], $actor, 'reader', array( 'selected' => true, 'hidden' => false ) );
	printf( "%s: %s\n", is_wp_error( $saved ) ? 'failed ' . $saved->get_error_code() : 'listed', $row['name'] );
}
wp_set_current_user( 0 );
