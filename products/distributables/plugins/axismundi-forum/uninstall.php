<?php
/**
 * Development-only Forum cleanup.
 *
 * Removing this pre-release plugin removes only Forum-owned relational data. Managed Group
 * Actors and Topics are owned by their respective products and intentionally survive.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;
foreach ( array( 'ax_forum_settings', 'ax_forum_entries', 'ax_forum_memberships', 'ax_forum_bindings' ) as $suffix ) {
	$table = $wpdb->prefix . $suffix;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- plugin uninstall removes only its own tables.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}
delete_option( 'ax_forum_db_version' );
