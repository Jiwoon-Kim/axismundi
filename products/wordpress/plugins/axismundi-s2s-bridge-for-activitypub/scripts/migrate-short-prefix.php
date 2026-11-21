<?php
/**
 * Operator migration: move a pre-release install off the short "ax" names.
 *
 * The wp.org review required every global name to carry a prefix longer than four characters. The
 * plugin now uses `axismundi_activitypub_bridge_` for its options, settings and tables. Installs
 * that ran an earlier build still hold their data under the old names, and the shipped plugin does
 * not carry the old names to look for them. This script, which is not part of the distributed ZIP,
 * moves that data once.
 *
 * Run it right after installing the renamed plugin. Until it runs, the plugin works on fresh, empty
 * tables and default settings, and the earlier queue and settings sit untouched under the old names.
 *
 *     wp eval-file scripts/migrate-short-prefix.php          # dry run: report only
 *     wp eval-file scripts/migrate-short-prefix.php apply    # perform
 *
 * Multisite: run once per site with --url, since every table and option here is per site.
 *
 * Rules
 * - Option: moved only when the new name is absent; the old name is then deleted. A new name that
 *   already exists was written by the renamed plugin and wins.
 * - Table: renamed in place, so row ids — and the delivery cron events that carry them — stay valid.
 *   If the renamed plugin already created the new table and it is empty, that empty table is dropped
 *   first. If it already holds rows, nothing is changed and the counts are reported: two live queues
 *   are not merged silently.
 * - The pre-0.0.16 delivery migration is gone from the plugin; its completion flag is deleted.
 *
 * @package AxismundiS2SBridgeForActivityPub
 */

defined( 'ABSPATH' ) || exit;

$apply = in_array( 'apply', $args ?? array(), true );
$log   = static function ( string $line ) : void {
	WP_CLI::log( $line );
};

global $wpdb;

$options = array(
	'ax_activitypub_bridge_rewrite_version'     => 'axismundi_activitypub_bridge_rewrite_version',
	'ax_activitypub_bridge_inbox_diagnostics'   => 'axismundi_activitypub_bridge_inbox_diagnostics',
	'ax_activitypub_bridge_delivery_db_version' => 'axismundi_activitypub_bridge_delivery_db_version',
	'ax_bridge_collection_controls_schema'      => 'axismundi_activitypub_bridge_collection_controls_schema',
	'ax_bridge_featured_receipts_schema'        => 'axismundi_activitypub_bridge_featured_receipts_schema',
	'ax_bridge_shared_inbox_public'             => 'axismundi_activitypub_bridge_shared_inbox_public',
);
$obsolete = array( 'ax_activitypub_bridge_delivery_migration' );
$tables   = array(
	'ax_ap_deliveries'          => 'axismundi_activitypub_bridge_deliveries',
	'ax_ap_delivery_recipients' => 'axismundi_activitypub_bridge_delivery_recipients',
	'ax_collection_controls'    => 'axismundi_activitypub_bridge_collection_controls',
	'ax_featured_receipts'      => 'axismundi_activitypub_bridge_featured_receipts',
);

$option_exists = static function ( string $name ) use ( $wpdb ) : bool {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Existence, not value: false is a valid stored value.
	return null !== $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $name ) );
};
$table_exists = static function ( string $table ) use ( $wpdb ) : bool {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
};
$rows = static function ( string $table ) use ( $wpdb ) : int {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
};

$log( $apply ? '== apply ==' : '== dry run (pass "apply" to perform) ==' );
$conflicts = 0;

foreach ( $tables as $old => $new ) {
	$old_table = $wpdb->prefix . $old;
	$new_table = $wpdb->prefix . $new;
	if ( ! $table_exists( $old_table ) ) {
		$log( "table   {$old_table}: absent, nothing to move" );
		continue;
	}
	$old_rows = $rows( $old_table );
	if ( $table_exists( $new_table ) ) {
		$new_rows = $rows( $new_table );
		if ( $new_rows > 0 ) {
			++$conflicts;
			$log( "table   {$old_table} ({$old_rows} rows) -> {$new_table}: CONFLICT, the new table already holds {$new_rows} rows; left unchanged" );
			continue;
		}
		$log( "table   {$new_table}: empty, created by the renamed plugin; drop it" );
		if ( $apply ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $new_table ) );
		}
	}
	$log( "table   {$old_table} ({$old_rows} rows) -> {$new_table}" );
	if ( $apply ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
		if ( false === $wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $old_table, $new_table ) ) ) {
			WP_CLI::error( "rename failed for {$old_table}: {$wpdb->last_error}" );
		}
	}
}

foreach ( $options as $old => $new ) {
	if ( ! $option_exists( $old ) ) {
		$log( "option  {$old}: absent" );
		continue;
	}
	if ( $option_exists( $new ) ) {
		$log( "option  {$old}: {$new} already set by the renamed plugin and kept; delete the old one" );
	} else {
		$log( "option  {$old} -> {$new}" );
		if ( $apply ) {
			add_option( $new, get_option( $old ), '', false );
		}
	}
	if ( $apply ) {
		delete_option( $old );
	}
}

foreach ( $obsolete as $old ) {
	if ( $option_exists( $old ) ) {
		$log( "option  {$old}: obsolete, delete" );
		if ( $apply ) {
			delete_option( $old );
		}
	}
}

if ( $conflicts > 0 ) {
	WP_CLI::warning( "{$conflicts} table(s) were left unchanged; merge them by hand before deleting the old table." );
} else {
	WP_CLI::success( $apply ? 'Moved.' : 'Dry run complete.' );
}
