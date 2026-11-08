<?php
/** Isolated temporary-table schema-3 fixtures; never modifies real blob tables. */
defined( 'ABSPATH' ) || exit( 1 );
use Axismundi\Jmap\Blobs;
global $wpdb;
$checks = 0;
$assert = static function ( $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks; echo "PASS $label\n";
};
$assert( 'local' === wp_get_environment_type(), 'Migration audit requires an explicit local environment' );
$initial_version = get_option( 'ax_jmap_blob_version' );
$initial_verified = get_option( 'ax_jmap_blob_verified', null );
$initial_prefix = $wpdb->prefix;
// Change only the repository prefix; WordPress options/posts table properties stay untouched.
$wpdb->prefix .= 'axpathaudit_' . bin2hex( random_bytes( 8 ) ) . '_';
$tables = array( Blobs\table(), Blobs\uploads_table(), Blobs\refs_table() );
$root = untrailingslashit( get_temp_dir() ) . '/jmap-path-audit-' . wp_generate_uuid4();
$ids = array( 'b' . hash( 'sha256', random_bytes( 32 ) ), bin2hex( random_bytes( 32 ) ), 'b' . wp_generate_uuid4() );
$paths = array(); $directories = array(); $added_columns = array();
try {
	update_option( 'ax_jmap_blob_version', 'audit', false );
	Blobs\install();
	foreach ( $tables as $table ) {
		$assert( '0' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ), 'Only a new isolated fixture table is used: ' . $table );
	}
	foreach ( array( 'account_identity_id' => 'bigint unsigned NOT NULL DEFAULT 0', 'uploader_user_id' => 'bigint unsigned NOT NULL DEFAULT 0', 'expires_at' => 'datetime NULL' ) as $column => $definition ) {
		$assert( false !== $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN %i ', Blobs\table(), $column ) . $definition ), 'Schema-3 fixture adds obsolete column: ' . $column );
		$added_columns[] = $column;
	}
	foreach ( $ids as $index => $id ) {
		$relative = 0 === $index ? substr( $id, 1, 2 ) . '/' . $id : 'a987654/' . substr( $id, 2 === $index ? 1 : 0, 2 ) . '/' . $id;
		$file = $root . '/' . $relative;
		$bytes = 'immutable migration fixture ' . $id;
		$assert( wp_mkdir_p( dirname( $file ) ) && strlen( $bytes ) === file_put_contents( $file, $bytes ), 'Fixture bytes exist in the original physical layout' );
		$paths[$id] = $file;
		for ( $dir = dirname( $file ); Blobs\within( $dir, $root ); $dir = dirname( $dir ) ) { $directories[$dir] = true; if ( $dir === $root ) { break; } }
		$assert( false !== $wpdb->insert( Blobs\table(), array( 'blob_id' => $id, 'account_identity_id' => 0 === $index ? 0 : 987654, 'uploader_user_id' => 0 === $index ? 0 : 1, 'content_hash' => hash( 'sha256', $bytes ), 'size' => strlen( $bytes ), 'content_type' => 'text/plain', 'storage_root' => $root, 'storage_path' => '', 'created_at' => gmdate( 'Y-m-d H:i:s' ), 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) ) ), 'Schema-3 content fixture is inserted' );
	}
	$wpdb->insert( Blobs\refs_table(), array( 'blob_id' => $ids[2], 'account_identity_id' => 0, 'owner_kind' => 'MigrationAudit', 'owner_ref' => 'fixture' ) );
	update_option( 'ax_jmap_blob_version', '3', false );
	Blobs\install();
	$assert( Blobs\SCHEMA_VERSION === get_option( 'ax_jmap_blob_version' ), 'Schema migration completes and advances its marker' );
	$columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . Blobs\table() );
	$assert( in_array( 'storage_path', $columns, true ) && ! array_intersect( $added_columns, $columns ), 'Migration removes obsolete ownership columns only after path materialization' );
	foreach ( $ids as $id ) {
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE blob_id=%s', Blobs\table(), $id ), ARRAY_A );
		$assert( $root . '/' . $row['storage_path'] === $paths[$id] && Blobs\path( $row ) === realpath( $paths[$id] ) && hash_file( 'sha256', $paths[$id] ) === $row['content_hash'], 'Existing ID, physical location and bytes remain unchanged' );
	}
	$assert( '2' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE account_identity_id=987654 AND uploader_user_id=1', Blobs\uploads_table() ) ), 'Development ownership is copied before its source columns are dropped' );
	$assert( '987654' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT account_identity_id FROM %i WHERE blob_id=%s', Blobs\refs_table(), $ids[2] ) ), 'Development reference retains its Account binding' );
	$before = $wpdb->get_results( 'SELECT * FROM ' . Blobs\table() . ' ORDER BY blob_id', ARRAY_A );
	Blobs\install();
	$assert( $before === $wpdb->get_results( 'SELECT * FROM ' . Blobs\table() . ' ORDER BY blob_id', ARRAY_A ), 'Repeated installation leaves recorded paths unchanged' );
	echo "PASS $checks storage migration checks\n";
} finally {
	foreach ( $ids as $id ) {
		foreach ( array( Blobs\refs_table(), Blobs\uploads_table(), Blobs\table() ) as $table ) { $wpdb->delete( $table, array( 'blob_id' => $id ) ); }
	}
	foreach ( $paths as $file ) { if ( is_file( $file ) ) { wp_delete_file( $file ); } }
	$dirs = array_keys( $directories ); usort( $dirs, static fn( $a, $b ) => strlen( $b ) <=> strlen( $a ) );
	foreach ( $dirs as $dir ) { if ( is_dir( $dir ) ) { rmdir( $dir ); } }
	foreach ( $tables as $table ) { $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) ); }
	$wpdb->prefix = $initial_prefix;
	update_option( 'ax_jmap_blob_version', $initial_version, false );
	if ( null === $initial_verified ) { delete_option( 'ax_jmap_blob_verified' ); } else { update_option( 'ax_jmap_blob_verified', $initial_verified, false ); }
}
