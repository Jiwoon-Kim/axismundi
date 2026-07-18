<?php
/**
 * Phase 4a - URI-keyed remote ActivityStreams object observations.
 *
 * This repository performs no network requests and exposes no public route. A row is a
 * rebuildable cache snapshot; the canonical remote URI remains the object's identity.
 * See docs/REMOTE-OBJECTS.md.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_OP_DB_VERSION            = '4';
const AXISMUNDI_OP_DB_VERSION_OPTION     = 'ax_object_projections_db_version';
const AXISMUNDI_OP_REMOTE_PAYLOAD_MAX    = 1048576;
const AXISMUNDI_OP_REMOTE_RETENTION_DAYS = 30;

/** @return string Remote-object table name. */
function axismundi_op_remote_objects_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_remote_objects';
}

/** Install/upgrade the Object Projections schema, recording success only after verification. */
function axismundi_op_install() : bool {
	global $wpdb;
	$previous_version = (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION, '' );
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = axismundi_op_remote_objects_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			object_uri text NOT NULL,
			object_uri_hash char(64) NOT NULL,
			object_type varchar(64) NOT NULL,
			object_status varchar(12) NOT NULL DEFAULT 'active',
			attributed_to_uri text DEFAULT NULL,
			attributed_to_uri_hash char(64) DEFAULT NULL,
			in_reply_to_uri text DEFAULT NULL,
			in_reply_to_uri_hash char(64) DEFAULT NULL,
			human_url text DEFAULT NULL,
			name text DEFAULT NULL,
			summary longtext DEFAULT NULL,
			content longtext DEFAULT NULL,
			content_language varchar(35) DEFAULT NULL,
			media_type varchar(127) DEFAULT NULL,
			is_sensitive tinyint(1) DEFAULT NULL,
			published_at datetime DEFAULT NULL,
			remote_updated_at datetime DEFAULT NULL,
			payload_json longtext NOT NULL,
			payload_hash char(64) NOT NULL,
			etag varchar(191) DEFAULT NULL,
			last_modified varchar(191) DEFAULT NULL,
			fetched_at datetime DEFAULT NULL,
			last_success_at datetime DEFAULT NULL,
			next_refresh_at datetime DEFAULT NULL,
			expires_at datetime DEFAULT NULL,
			last_accessed_at datetime DEFAULT NULL,
			failure_count int(10) unsigned NOT NULL DEFAULT 0,
			last_error_code varchar(64) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY object_uri_hash (object_uri_hash),
			KEY attributed_to_uri_hash (attributed_to_uri_hash),
			KEY in_reply_to_uri_hash (in_reply_to_uri_hash),
			KEY refresh_status (next_refresh_at, object_status),
			KEY expires_at (expires_at)
		) ENGINE=InnoDB {$charset};"
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table schema verification.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table schema verification.
	$identity_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'object_uri_hash'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table engine verification.
	$engine = (string) $wpdb->get_var( "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'" );
	if ( 'InnoDB' !== $engine && ! empty( $columns ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off custom table engine upgrade.
		$wpdb->query( "ALTER TABLE {$table} ENGINE=InnoDB" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- verify engine upgrade.
		$engine = (string) $wpdb->get_var( "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'" );
	}

	$unique_identity = ! empty( $identity_index ) && 0 === (int) $identity_index[0]['Non_unique'];
	$valid           = in_array( 'object_uri', $columns, true )
		&& in_array( 'payload_hash', $columns, true )
		&& in_array( 'is_sensitive', $columns, true )
		&& in_array( 'next_refresh_at', $columns, true )
		&& in_array( 'expires_at', $columns, true )
		&& in_array( 'last_accessed_at', $columns, true )
		&& $unique_identity
		&& function_exists( 'axismundi_op_install_lease_schema' )
		&& axismundi_op_install_lease_schema()
		&& function_exists( 'axismundi_op_install_object_relations' )
		&& axismundi_op_install_object_relations()
		&& 'InnoDB' === $engine;
	if ( $valid && version_compare( $previous_version, '4', '<' ) ) {
		$rebuild = function_exists( 'axismundi_op_rebuild_quote_relations' ) ? axismundi_op_rebuild_quote_relations() : array( 'failed' => 1 );
		$valid   = 0 === (int) ( $rebuild['failed'] ?? 1 );
	}
	if ( $valid ) {
		update_option( AXISMUNDI_OP_DB_VERSION_OPTION, AXISMUNDI_OP_DB_VERSION, false );
	}
	return $valid;
}

/** Metadata-only cache retention in days. */
function axismundi_op_remote_retention_days() : int {
	/**
	 * Filter remote object metadata retention.
	 *
	 * @since 0.0.6
	 * @param int $days Retention days, default 30.
	 */
	return max( 1, min( 365, (int) apply_filters( 'axismundi_op_remote_retention_days', AXISMUNDI_OP_REMOTE_RETENTION_DAYS ) ) );
}

/** UTC expiry calculated from a SQL datetime or now. */
function axismundi_op_remote_expiry( ?string $from = null ) : string {
	$timestamp = $from ? strtotime( $from . ' UTC' ) : time();
	return gmdate( 'Y-m-d H:i:s', ( false === $timestamp ? time() : $timestamp ) + DAY_IN_SECONDS * axismundi_op_remote_retention_days() );
}

/** Upgrade without requiring plugin reactivation. */
function axismundi_op_maybe_upgrade() : void {
	if ( AXISMUNDI_OP_DB_VERSION !== (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION, '' ) ) {
		axismundi_op_install();
	}
}
add_action( 'plugins_loaded', 'axismundi_op_maybe_upgrade' );

/**
 * Normalize a fetchable remote object URI without performing a request.
 *
 * @param mixed $value Candidate URI.
 * @return string|WP_Error
 */
function axismundi_op_remote_object_uri( $value ) {
	$uri   = trim( (string) $value );
	$parts = wp_parse_url( $uri );
	if ( ! is_array( $parts )
		|| ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true )
		|| empty( $parts['host'] )
		|| isset( $parts['user'] )
		|| isset( $parts['pass'] )
	) {
		return new WP_Error( 'ax_op_remote_uri', __( 'A remote object requires an absolute HTTP(S) URI without credentials.', 'axismundi-object-projections' ) );
	}
	return $uri;
}

/** Return the first URI represented by a scalar, object, or list member. */
function axismundi_op_remote_member_uri( $value ) : string {
	if ( is_string( $value ) ) {
		return is_wp_error( axismundi_op_remote_object_uri( $value ) ) ? '' : trim( $value );
	}
	if ( is_array( $value ) && array_is_list( $value ) ) {
		foreach ( $value as $candidate ) {
			$uri = axismundi_op_remote_member_uri( $candidate );
			if ( '' !== $uri ) {
				return $uri;
			}
		}
		return '';
	}
	if ( is_array( $value ) ) {
		return axismundi_op_remote_member_uri( $value['id'] ?? $value['href'] ?? '' );
	}
	return '';
}

/**
 * Object types owned by this repository. Actors, Activities, and Collections belong to
 * their respective stores and are deliberately excluded.
 *
 * @return string[]
 */
function axismundi_op_remote_object_types() : array {
	$types = array( 'Object', 'Article', 'Audio', 'Document', 'Event', 'Image', 'Note', 'Page', 'Place', 'Profile', 'Question', 'Relationship', 'Tombstone', 'Video' );
	/**
	 * Filter remote AS object types accepted by this repository.
	 *
	 * @since 0.0.5
	 * @param string[] $types Object types. Do not add Actor, Activity, or Collection types.
	 */
	return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) apply_filters( 'axismundi_op_remote_object_types', $types ) ) ) ) );
}

/** Normalize an AS type scalar/list into one supported storage label. */
function axismundi_op_remote_object_type( $value ) : string {
	$types = is_array( $value ) ? $value : array( $value );
	$allowed = axismundi_op_remote_object_types();
	foreach ( $types as $type ) {
		$type = is_string( $type ) ? substr( sanitize_text_field( $type ), 0, 64 ) : '';
		if ( in_array( $type, $allowed, true ) ) {
			return $type;
		}
	}
	return '';
}

/** Convert an ISO date to a UTC SQL datetime, or null when invalid. */
function axismundi_op_remote_datetime( $value ) : ?string {
	if ( ! is_string( $value ) || '' === trim( $value ) ) {
		return null;
	}
	$timestamp = strtotime( $value );
	return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
}

/**
 * Validate and normalize a remote payload before any database mutation.
 *
 * @param array<string,mixed> $payload Decoded remote object.
 * @param array<string,mixed> $fetch   Optional response metadata.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_op_remote_object_normalize( array $payload, array $fetch = array() ) {
	$uri = axismundi_op_remote_object_uri( $payload['id'] ?? '' );
	if ( is_wp_error( $uri ) ) {
		return $uri;
	}
	$type = axismundi_op_remote_object_type( $payload['type'] ?? '' );
	if ( '' === $type ) {
		return new WP_Error( 'ax_op_remote_type', __( 'A remote object requires a type.', 'axismundi-object-projections' ) );
	}
	$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( ! is_string( $json ) || strlen( $json ) > AXISMUNDI_OP_REMOTE_PAYLOAD_MAX ) {
		return new WP_Error( 'ax_op_remote_payload_size', __( 'The remote object payload exceeds the storage limit.', 'axismundi-object-projections' ) );
	}

	$actor_uri = axismundi_op_remote_member_uri( $payload['attributedTo'] ?? '' );
	$reply_uri = axismundi_op_remote_member_uri( $payload['inReplyTo'] ?? '' );
	$human_url = axismundi_op_remote_member_uri( $payload['url'] ?? '' );
	$fetched   = axismundi_op_remote_datetime( $fetch['fetched_at'] ?? '' ) ?? current_time( 'mysql', true );
	$next      = axismundi_op_remote_datetime( $fetch['next_refresh_at'] ?? '' );
	if ( null === $next ) {
		$next = gmdate( 'Y-m-d H:i:s', strtotime( $fetched . ' UTC +1 day' ) );
	}

	return array(
		'object_uri'             => $uri,
		'object_uri_hash'        => hash( 'sha256', $uri ),
		'object_type'            => $type,
		'object_status'          => 'Tombstone' === $type ? 'tombstone' : 'active',
		'attributed_to_uri'      => '' !== $actor_uri ? $actor_uri : null,
		'attributed_to_uri_hash' => '' !== $actor_uri ? hash( 'sha256', $actor_uri ) : null,
		'in_reply_to_uri'        => '' !== $reply_uri ? $reply_uri : null,
		'in_reply_to_uri_hash'   => '' !== $reply_uri ? hash( 'sha256', $reply_uri ) : null,
		'human_url'              => '' !== $human_url ? $human_url : null,
		'name'                   => isset( $payload['name'] ) && is_scalar( $payload['name'] ) ? sanitize_text_field( (string) $payload['name'] ) : null,
		'summary'                => isset( $payload['summary'] ) && is_scalar( $payload['summary'] ) ? wp_kses_post( (string) $payload['summary'] ) : null,
		'content'                => isset( $payload['content'] ) && is_scalar( $payload['content'] ) ? wp_kses_post( (string) $payload['content'] ) : null,
		'content_language'       => ! empty( $payload['contentMap'] ) && is_array( $payload['contentMap'] ) ? substr( sanitize_text_field( (string) array_key_first( $payload['contentMap'] ) ), 0, 35 ) : null,
		'media_type'             => isset( $payload['mediaType'] ) ? substr( sanitize_mime_type( (string) $payload['mediaType'] ), 0, 127 ) : null,
		'is_sensitive'           => array_key_exists( 'sensitive', $payload ) && is_bool( $payload['sensitive'] ) ? (int) $payload['sensitive'] : null,
		'published_at'           => axismundi_op_remote_datetime( $payload['published'] ?? '' ),
		'remote_updated_at'      => axismundi_op_remote_datetime( $payload['updated'] ?? '' ),
		'payload_json'           => $json,
		'payload_hash'           => hash( 'sha256', $json ),
		'etag'                   => isset( $fetch['etag'] ) ? substr( sanitize_text_field( (string) $fetch['etag'] ), 0, 191 ) : null,
		'last_modified'          => isset( $fetch['last_modified'] ) ? substr( sanitize_text_field( (string) $fetch['last_modified'] ), 0, 191 ) : null,
		'fetched_at'             => $fetched,
		'last_success_at'        => $fetched,
		'next_refresh_at'        => $next,
		'expires_at'             => axismundi_op_remote_expiry( $fetched ),
		'last_accessed_at'       => $fetched,
		'failure_count'          => 0,
		'last_error_code'        => null,
	);
}

/**
 * Store the last valid observation atomically by canonical URI.
 *
 * @param array<string,mixed> $payload Decoded remote object.
 * @param array<string,mixed> $fetch   Optional response metadata.
 * @return array<string,mixed>|WP_Error Stored row.
 */
function axismundi_op_remote_object_store( array $payload, array $fetch = array() ) {
	global $wpdb;
	$normalized = axismundi_op_remote_object_normalize( $payload, $fetch );
	if ( is_wp_error( $normalized ) ) {
		return $normalized;
	}
	if ( AXISMUNDI_OP_DB_VERSION !== (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION, '' ) && ! axismundi_op_install() ) {
		return new WP_Error( 'ax_op_remote_schema', __( 'The remote object schema is unavailable.', 'axismundi-object-projections' ) );
	}

	$table = axismundi_op_remote_objects_table();
	$now   = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic custom repository transaction.
	$wpdb->query( 'START TRANSACTION' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom repository row lock.
	$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE object_uri_hash = %s FOR UPDATE", $normalized['object_uri_hash'] ), ARRAY_A );
	if ( is_array( $existing ) && ! hash_equals( (string) $existing['object_uri'], (string) $normalized['object_uri'] ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rollback custom repository transaction.
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'ax_op_remote_hash_collision', __( 'A remote object URI hash collision was detected.', 'axismundi-object-projections' ) );
	}

	$ok = is_array( $existing )
		? false !== $wpdb->update( $table, array_merge( $normalized, array( 'updated_at' => $now ) ), array( 'id' => (int) $existing['id'] ) )
		: false !== $wpdb->insert( $table, array_merge( $normalized, array( 'created_at' => $now, 'updated_at' => $now ) ) );
	if ( ! $ok ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rollback custom repository transaction.
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'ax_op_remote_write', __( 'The remote object snapshot could not be stored.', 'axismundi-object-projections' ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- commit custom repository transaction.
	$wpdb->query( 'COMMIT' );
	$stored = axismundi_op_remote_object_get( (string) $normalized['object_uri'] );
	if ( is_array( $stored ) && function_exists( 'axismundi_op_index_quote_relations' ) ) {
		axismundi_op_index_quote_relations( $stored );
	}
	return $stored;
}

/** @return array<string,mixed>|null Exact URI row with decoded `payload`. */
function axismundi_op_remote_object_get( string $uri, bool $touch = false ) : ?array {
	global $wpdb;
	if ( AXISMUNDI_OP_DB_VERSION !== (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION, '' ) ) {
		return null;
	}
	$valid = axismundi_op_remote_object_uri( $uri );
	if ( is_wp_error( $valid ) ) {
		return null;
	}
	$table = axismundi_op_remote_objects_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- canonical custom repository lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE object_uri_hash = %s", hash( 'sha256', $valid ) ), ARRAY_A );
	if ( ! is_array( $row ) || ! hash_equals( (string) $row['object_uri'], $valid ) ) {
		return null;
	}
	if ( $touch ) {
		$now = current_time( 'mysql', true );
		$wpdb->update(
			$table,
			array( 'last_accessed_at' => $now, 'expires_at' => axismundi_op_remote_expiry( $now ) ),
			array( 'id' => (int) $row['id'] )
		);
		$row['last_accessed_at'] = $now;
		$row['expires_at']       = axismundi_op_remote_expiry( $now );
	}
	$payload        = json_decode( (string) $row['payload_json'], true );
	$row['payload'] = is_array( $payload ) ? $payload : array();
	return $row;
}

/** Record a failed refresh without destroying the last successful payload. */
function axismundi_op_remote_object_record_failure( string $uri, string $error_code ) : bool {
	global $wpdb;
	$row = axismundi_op_remote_object_get( $uri );
	if ( null === $row ) {
		return false;
	}
	$failures = (int) $row['failure_count'] + 1;
	$delay    = min( DAY_IN_SECONDS, 5 * MINUTE_IN_SECONDS * ( 2 ** min( 8, $failures - 1 ) ) );
	return false !== $wpdb->update(
		axismundi_op_remote_objects_table(),
		array(
			'failure_count'   => $failures,
			'last_error_code' => substr( sanitize_key( $error_code ), 0, 64 ),
			'next_refresh_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ),
			'updated_at'      => current_time( 'mysql', true ),
		),
		array( 'id' => (int) $row['id'] )
	);
}

/** Mark a conditional 304 as fresh while retaining the payload. */
function axismundi_op_remote_object_not_modified( string $uri ) : ?array {
	global $wpdb;
	$row = axismundi_op_remote_object_get( $uri );
	if ( null === $row ) {
		return null;
	}
	$now = current_time( 'mysql', true );
	$wpdb->update(
		axismundi_op_remote_objects_table(),
		array(
			'fetched_at'       => $now,
			'last_success_at'  => $now,
			'next_refresh_at'  => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ),
			'expires_at'       => axismundi_op_remote_expiry( $now ),
			'last_accessed_at' => $now,
			'failure_count'    => 0,
			'last_error_code'  => null,
			'updated_at'       => $now,
		),
		array( 'id' => (int) $row['id'] )
	);
	return axismundi_op_remote_object_get( $uri );
}

/** Return recent cache rows for the administrator inspector. */
function axismundi_op_remote_objects_list( int $limit = 50 ) : array {
	global $wpdb;
	if ( AXISMUNDI_OP_DB_VERSION !== (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION, '' ) ) {
		return array();
	}
	$table = axismundi_op_remote_objects_table();
	$limit = max( 1, min( 200, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded admin repository listing.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT %d", $limit ), ARRAY_A );
}

/** Purge expired metadata observations; returns deleted/would-delete row count. */
function axismundi_op_remote_objects_purge_expired( bool $dry_run = false ) : int {
	global $wpdb;
	if ( AXISMUNDI_OP_DB_VERSION !== (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION, '' ) ) {
		return 0;
	}
	$table  = axismundi_op_remote_objects_table();
	$leases = axismundi_op_object_leases_table();
	$now    = current_time( 'mysql', true );
	$where  = "o.expires_at IS NOT NULL AND o.expires_at <= %s AND NOT EXISTS (SELECT 1 FROM {$leases} l WHERE l.object_uri_hash = o.object_uri_hash AND l.object_uri = o.object_uri AND (l.expires_at IS NULL OR l.expires_at > %s))";
	if ( $dry_run ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded cache maintenance count.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} o WHERE {$where}", $now, $now ) );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit cache expiry maintenance.
	$result = $wpdb->query( $wpdb->prepare( "DELETE o FROM {$table} o WHERE {$where}", $now, $now ) );
	if ( false !== $result && function_exists( 'axismundi_op_purge_orphan_object_relations' ) ) {
		axismundi_op_purge_orphan_object_relations();
	}
	return false === $result ? 0 : (int) $result;
}

/** Daily cron callback. */
function axismundi_op_remote_objects_daily_maintenance() : void {
	axismundi_op_remote_objects_purge_expired();
}
add_action( 'axismundi_op_remote_objects_daily', 'axismundi_op_remote_objects_daily_maintenance' );

/** Delete one local cache observation only. */
function axismundi_op_remote_object_delete( string $uri ) : bool {
	global $wpdb;
	if ( AXISMUNDI_OP_DB_VERSION !== (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION, '' ) ) {
		return false;
	}
	$valid = axismundi_op_remote_object_uri( $uri );
	if ( is_wp_error( $valid ) ) {
		return false;
	}
	$table = axismundi_op_remote_objects_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit cache deletion.
	$deleted = false !== $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE object_uri_hash = %s AND object_uri = %s", hash( 'sha256', $valid ), $valid ) );
	if ( $deleted && function_exists( 'axismundi_op_delete_object_relations_for_source' ) ) {
		axismundi_op_delete_object_relations_for_source( $valid );
	}
	return $deleted;
}
