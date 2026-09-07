<?php
/**
 * Multi-reason retention leases for URI-keyed remote object observations.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** @return string Lease table name. */
function axismundi_op_object_leases_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_object_leases';
}

/** Create and verify the lease table. */
function axismundi_op_install_lease_schema() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = axismundi_op_object_leases_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_uri text NOT NULL,
			object_uri_hash char(64) NOT NULL,
			lease_type varchar(32) NOT NULL,
			lease_ref text NOT NULL,
			lease_ref_hash char(64) NOT NULL,
			expires_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY object_reason_ref (object_uri_hash, lease_type, lease_ref_hash),
			KEY object_uri_hash (object_uri_hash),
			KEY expires_at (expires_at)
		) ENGINE=InnoDB {$charset};"
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom schema verification.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom schema verification.
	$unique = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'object_reason_ref'", ARRAY_A );
	return in_array( 'lease_ref_hash', $columns, true ) && ! empty( $unique ) && 0 === (int) $unique[0]['Non_unique'];
}

/** Normalize one supported lease reason. */
function axismundi_op_lease_type( string $type ) : string {
	$type = sanitize_key( $type );
	return in_array( $type, array( 'transient', 'interaction', 'collection', 'shared_shadow' ), true ) ? $type : '';
}

/**
 * Add or renew one retention lease. Replays are idempotent.
 *
 * @param string      $object_uri Canonical remote object URI.
 * @param string      $lease_type Lease reason.
 * @param string      $lease_ref  Stable owner/reference URI.
 * @param string|null $expires_at UTC MySQL datetime; null means no automatic expiry.
 */
function axismundi_op_add_lease( string $object_uri, string $lease_type, string $lease_ref, ?string $expires_at = null ) : bool {
	global $wpdb;
	$uri  = axismundi_op_remote_object_uri( $object_uri );
	$type = axismundi_op_lease_type( $lease_type );
	$ref  = axismundi_op_remote_object_uri( $lease_ref );
	if ( is_wp_error( $uri ) || is_wp_error( $ref ) || '' === $type ) {
		return false;
	}
	if ( AXISMUNDI_OP_DB_VERSION !== (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION, '' ) && ! axismundi_op_install() ) {
		return false;
	}
	$expires = null;
	if ( null !== $expires_at && '' !== $expires_at ) {
		$timestamp = strtotime( $expires_at );
		if ( false === $timestamp ) {
			return false;
		}
		$expires = gmdate( 'Y-m-d H:i:s', $timestamp );
	}
	$table    = axismundi_op_object_leases_table();
	$now      = current_time( 'mysql', true );
	$uri_hash = hash( 'sha256', $uri );
	$ref_hash = hash( 'sha256', $ref );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact custom lease lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE object_uri_hash = %s AND lease_type = %s AND lease_ref_hash = %s", $uri_hash, $type, $ref_hash ), ARRAY_A );
	if ( is_array( $row ) ) {
		if ( ! hash_equals( (string) $row['object_uri'], $uri ) || ! hash_equals( (string) $row['lease_ref'], $ref ) ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- idempotent lease renewal.
		return false !== $wpdb->update( $table, array( 'expires_at' => $expires, 'updated_at' => $now ), array( 'id' => (int) $row['id'] ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom lease insert.
	return false !== $wpdb->insert(
		$table,
		array(
			'object_uri'      => $uri,
			'object_uri_hash' => $uri_hash,
			'lease_type'      => $type,
			'lease_ref'       => $ref,
			'lease_ref_hash'  => $ref_hash,
			'expires_at'      => $expires,
			'created_at'      => $now,
			'updated_at'      => $now,
		)
	);
}

/** Release one exact lease. Missing rows are already released. */
function axismundi_op_release_lease( string $object_uri, string $lease_type, string $lease_ref ) : bool {
	global $wpdb;
	$uri  = axismundi_op_remote_object_uri( $object_uri );
	$type = axismundi_op_lease_type( $lease_type );
	$ref  = axismundi_op_remote_object_uri( $lease_ref );
	if ( is_wp_error( $uri ) || is_wp_error( $ref ) || '' === $type ) {
		return false;
	}
	$table = axismundi_op_object_leases_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact custom lease deletion.
	$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE object_uri_hash = %s AND object_uri = %s AND lease_type = %s AND lease_ref_hash = %s AND lease_ref = %s", hash( 'sha256', $uri ), $uri, $type, hash( 'sha256', $ref ), $ref ) );
	return false !== $result;
}

/** Count currently active leases for one exact object URI. */
function axismundi_op_active_lease_count( string $object_uri ) : int {
	global $wpdb;
	$uri = axismundi_op_remote_object_uri( $object_uri );
	if ( is_wp_error( $uri ) ) {
		return 0;
	}
	$table = axismundi_op_object_leases_table();
	$now   = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed exact lease count.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE object_uri_hash = %s AND object_uri = %s AND (expires_at IS NULL OR expires_at > %s)", hash( 'sha256', $uri ), $uri, $now ) );
}

