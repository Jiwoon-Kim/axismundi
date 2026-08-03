<?php
/**
 * Phase 4a - URI-keyed remote ActivityStreams object observations.
 *
 * This repository performs no network requests. A row is a rebuildable cache snapshot;
 * public observations may receive a noindex local human view, while the canonical remote
 * URI remains the object's identity.
 * See docs/REMOTE-OBJECTS.md.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_OP_DB_VERSION            = '8';
const AXISMUNDI_OP_DB_VERSION_OPTION     = 'ax_object_projections_db_version';
const AXISMUNDI_OP_REMOTE_PAYLOAD_MAX    = 1048576;
const AXISMUNDI_OP_REMOTE_RETENTION_DAYS = 30;

/** @return string Remote-object table name. */
function axismundi_op_remote_objects_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_remote_objects';
}

/** @return string Queryable listing state for local and remote Objects, keyed by canonical URI hash. */
function axismundi_op_object_index_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_object_index';
}

/** Install and verify the rebuildable Object listing projection. */
function axismundi_op_install_object_index_schema() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = axismundi_op_object_index_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
			object_uri_hash char(64) NOT NULL,
			publicly_listable tinyint(1) NOT NULL DEFAULT 0,
			has_group_context tinyint(1) NOT NULL DEFAULT 0,
			primary_group_uri_hash char(64) DEFAULT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (object_uri_hash),
			KEY listing_context (publicly_listable, has_group_context)
		) ENGINE=InnoDB {$charset};"
	);
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table schema verification.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table schema verification.
	$identity = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'PRIMARY'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table engine verification.
	$engine = (string) $wpdb->get_var( "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'" );
	if ( 'InnoDB' !== $engine && ! empty( $columns ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- required for the snapshot/projection transaction.
		$wpdb->query( "ALTER TABLE {$table} ENGINE=InnoDB" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- verify engine upgrade.
		$engine = (string) $wpdb->get_var( "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'" );
	}
	return in_array( 'publicly_listable', $columns, true )
		&& in_array( 'has_group_context', $columns, true )
		&& in_array( 'primary_group_uri_hash', $columns, true )
		&& ! empty( $identity )
		&& 0 === (int) $identity[0]['Non_unique']
		&& 'InnoDB' === $engine;
}

/** @return array<string,mixed>|null Queryable projection row for one canonical Object URI. */
function axismundi_op_get_object_listing_projection( string $object_uri ) : ?array {
	global $wpdb;
	$object_uri = trim( $object_uri );
	if ( '' === $object_uri || AXISMUNDI_OP_DB_VERSION !== (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION, '' ) ) {
		return null;
	}
	$table = axismundi_op_object_index_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- canonical custom projection lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE object_uri_hash = %s", hash( 'sha256', $object_uri ) ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/** Delete the listing projection for one Object URI. */
function axismundi_op_delete_object_listing_projection( string $object_uri ) : bool {
	global $wpdb;
	$object_uri = trim( $object_uri );
	if ( '' === $object_uri || AXISMUNDI_OP_DB_VERSION !== (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION, '' ) ) {
		return false;
	}
	return false !== $wpdb->delete( axismundi_op_object_index_table(), array( 'object_uri_hash' => hash( 'sha256', $object_uri ) ) );
}

/**
 * Write one source row's listability projection. Activities owns Group-context classification;
 * Object Projections persists that answer when Activities is available and otherwise fails closed.
 *
 * @param array<string,mixed> $row Stored source row with decoded `payload`.
 */
function axismundi_op_write_object_listing_projection( string $object_uri, array $row ) : bool {
	global $wpdb;
	$object_uri = trim( $object_uri );
	if ( '' === $object_uri ) {
		return false;
	}
	$context = array( 'has_group_context' => false, 'primary_group_uri' => '' );
	if ( function_exists( 'axismundi_act_group_context' ) ) {
		$context = (array) axismundi_act_group_context( (array) ( $row['payload'] ?? array() ), $object_uri );
	}
	$primary = trim( (string) ( $context['primary_group_uri'] ?? '' ) );
	return false !== $wpdb->replace(
		axismundi_op_object_index_table(),
		array(
			'object_uri_hash'        => hash( 'sha256', $object_uri ),
			'publicly_listable'      => (int) axismundi_op_remote_object_is_publicly_listable( $row ),
			'has_group_context'      => ! empty( $context['has_group_context'] ) ? 1 : 0,
			'primary_group_uri_hash' => '' !== $primary ? hash( 'sha256', $primary ) : null,
			'updated_at'             => current_time( 'mysql', true ),
		),
		array( '%s', '%d', '%d', '%s', '%s' )
	);
}

/**
 * Refresh the one listing projection writer from its current source snapshot.
 *
 * Phase one supplies remote observations. A caller that has just persisted a source row may
 * pass it to keep both writes in one transaction; later local products call this same writer
 * rather than maintaining a competing state column.
 *
 * @param array<string,mixed>|null $source_row Stored source row with decoded `payload`, when already available.
 */
function axismundi_op_refresh_object_listing_projection( string $object_uri, ?array $source_row = null ) : bool {
	$object_uri = trim( $object_uri );
	if ( '' === $object_uri ) {
		return false;
	}
	$row = $source_row ?? axismundi_op_get_remote_object( $object_uri );
	if ( ! is_array( $row ) ) {
		return axismundi_op_delete_object_listing_projection( $object_uri );
	}
	return axismundi_op_write_object_listing_projection( $object_uri, $row );
}

/** Rebuild the remote-cache share of the listing projection during an upgrade. */
function axismundi_op_backfill_object_listing_projection() : bool {
	global $wpdb;
	$objects = axismundi_op_remote_objects_table();
	$after   = 0;
	$batch   = 200;
	$valid   = true;
	do {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded upgrade backfill over the repository's own rows.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, object_uri, object_status, attributed_to_uri, payload_json FROM {$objects} WHERE id > %d ORDER BY id ASC LIMIT %d", $after, $batch ), ARRAY_A );
		foreach ( $rows as $row ) {
			$after          = (int) $row['id'];
			$payload        = json_decode( (string) $row['payload_json'], true );
			$row['payload'] = is_array( $payload ) ? $payload : array();
			$valid          = axismundi_op_write_object_listing_projection( (string) $row['object_uri'], $row ) && $valid;
		}
	} while ( count( $rows ) === $batch );
	return $valid;
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
		&& function_exists( 'axismundi_op_install_remote_object_hashtag_schema' )
		&& axismundi_op_install_remote_object_hashtag_schema()
		&& function_exists( 'axismundi_op_install_object_mentions' )
		&& axismundi_op_install_object_mentions()
		&& function_exists( 'axismundi_op_install_thread_edges' )
		&& axismundi_op_install_thread_edges()
		&& axismundi_op_install_object_index_schema()
		&& 'InnoDB' === $engine;
	if ( $valid && version_compare( $previous_version, '4', '<' ) ) {
		$rebuild = function_exists( 'axismundi_op_rebuild_quote_relations' ) ? axismundi_op_rebuild_quote_relations() : array( 'failed' => 1 );
		$valid   = 0 === (int) ( $rebuild['failed'] ?? 1 );
	}
	if ( $valid && version_compare( $previous_version, '8', '<' ) ) {
		$valid = axismundi_op_backfill_object_listing_projection();
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
	$types = array( 'Object', 'Article', 'Audio', 'Document', 'Event', 'Image', 'Note', 'Page', 'Place', 'Profile', 'Question', 'QuoteAuthorization', 'Relationship', 'Tombstone', 'Video' );
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
 * Resolve one natural-language member from a remote AS2 payload.
 *
 * Scalar members remain the compatibility preference. Map-only documents use
 * their first scalar map value until the object view has a visitor-language
 * negotiation layer. The original map always remains in `payload_json`.
 */
function axismundi_op_remote_natural_language_value( array $payload, string $member ) : string {
	if ( array_key_exists( $member, $payload ) && is_scalar( $payload[ $member ] ) ) {
		return (string) $payload[ $member ];
	}
	$map = $payload[ $member . 'Map' ] ?? null;
	if ( ! is_array( $map ) ) {
		return '';
	}
	foreach ( $map as $value ) {
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}
	}
	return '';
}

/** Return the first declared language key for one remote natural-language map. */
function axismundi_op_remote_natural_language_language( array $payload, string $member ) : ?string {
	$map = $payload[ $member . 'Map' ] ?? null;
	if ( ! is_array( $map ) ) {
		return null;
	}
	foreach ( $map as $language => $value ) {
		if ( is_scalar( $value ) ) {
			$language = substr( sanitize_text_field( (string) $language ), 0, 35 );
			return '' !== $language ? $language : null;
		}
	}
	return null;
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

	$name    = axismundi_op_remote_natural_language_value( $payload, 'name' );
	$summary = axismundi_op_remote_natural_language_value( $payload, 'summary' );
	$content = axismundi_op_remote_natural_language_value( $payload, 'content' );

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
		'name'                   => '' !== $name ? sanitize_text_field( $name ) : null,
		'summary'                => '' !== $summary ? wp_kses_post( $summary ) : null,
		'content'                => '' !== $content ? wp_kses_post( $content ) : null,
		'content_language'       => axismundi_op_remote_natural_language_language( $payload, 'content' ),
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
function axismundi_op_store_remote_object( array $payload, array $fetch = array() ) {
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
	$projection_row            = $normalized;
	$projection_row['payload'] = $payload;
	if ( ! axismundi_op_refresh_object_listing_projection( (string) $normalized['object_uri'], $projection_row ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rollback the snapshot when its required listing projection cannot be written.
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'ax_op_object_index', __( 'The object listing projection could not be stored.', 'axismundi-object-projections' ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- commit custom repository transaction.
	$wpdb->query( 'COMMIT' );
	$stored = axismundi_op_get_remote_object( (string) $normalized['object_uri'] );
	if ( is_array( $stored ) && function_exists( 'axismundi_op_index_quote_relations' ) ) {
		axismundi_op_index_quote_relations( $stored );
	}
	if ( is_array( $stored ) && function_exists( 'axismundi_op_index_thread_edge_from_remote_object' ) ) {
		axismundi_op_index_thread_edge_from_remote_object( $stored );
	}
	if ( is_array( $stored ) && function_exists( 'axismundi_op_index_remote_object_hashtags' ) ) {
		axismundi_op_index_remote_object_hashtags( $stored );
	}
	if ( is_array( $stored ) && function_exists( 'axismundi_op_index_remote_object_mentions' ) ) {
		axismundi_op_index_remote_object_mentions( $stored );
	}
	return $stored;
}

/** @return array<string,mixed>|null Exact URI row with decoded `payload`. */
function axismundi_op_get_remote_object( string $uri, bool $touch = false ) : ?array {
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

/** @return array<string,mixed>|null Exact SHA-256 identity row with decoded `payload`. */
function axismundi_op_get_remote_object_by_hash( string $hash, bool $touch = false ) : ?array {
	global $wpdb;
	$hash = strtolower( trim( $hash ) );
	if ( AXISMUNDI_OP_DB_VERSION !== (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION, '' )
		|| 1 !== preg_match( '/\A[a-f0-9]{64}\z/', $hash )
	) {
		return null;
	}
	$table = axismundi_op_remote_objects_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- canonical custom repository lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE object_uri_hash = %s", $hash ), ARRAY_A );
	if ( ! is_array( $row ) || ! hash_equals( $hash, hash( 'sha256', (string) $row['object_uri'] ) ) ) {
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

/** Whether an observed remote Object explicitly declares a public audience. */
function axismundi_op_remote_object_is_publicly_listable( array $row ) : bool {
	if ( 'active' !== (string) ( $row['object_status'] ?? '' ) || empty( $row['attributed_to_uri'] ) ) {
		return false;
	}
	$payload = (array) ( $row['payload'] ?? array() );
	$public  = array( 'https://www.w3.org/ns/activitystreams#Public', 'as:Public' );
	foreach ( array( 'to', 'cc' ) as $property ) {
		$members = $payload[ $property ] ?? array();
		$members = is_array( $members ) && array_is_list( $members ) ? $members : array( $members );
		foreach ( $members as $member ) {
			$uri = axismundi_op_remote_member_uri( $member );
			if ( in_array( is_scalar( $member ) ? (string) $member : $uri, $public, true ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Return public remote Objects observed for one Actor but not anchored by a
 * known Create Activity. These are observations, never synthesized Activities.
 *
 * @param string[] $create_object_uris Canonical Object URIs already represented by Create rows.
 * @return array<int,array{id:string,kind:string,type:string,actor_uri:string,object_uri:string,published:string}>
 */
function axismundi_op_get_observed_actor_objects( string $actor_uri, array $create_object_uris = array(), int $limit = 20 ) : array {
	global $wpdb;
	$actor_uri = axismundi_op_remote_member_uri( $actor_uri );
	if ( '' === $actor_uri || AXISMUNDI_OP_DB_VERSION !== (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION, '' ) ) {
		return array();
	}
	$limit    = max( 1, min( 50, $limit ) );
	$excluded = array_fill_keys( array_filter( array_map( 'strval', $create_object_uris ) ), true );
	$table    = axismundi_op_remote_objects_table();
	$items    = array();
	$offset   = 0;
	$batch    = 100;
	// Publicness lives in payload_json, so scan bounded candidate batches rather
	// than pretending every locally cached remote observation is public.
	while ( count( $items ) < $limit && $offset < 1000 ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table with prepared values and bounded pagination.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE attributed_to_uri_hash = %s AND attributed_to_uri = %s AND object_status = 'active' ORDER BY COALESCE(published_at, remote_updated_at, created_at) DESC, id DESC LIMIT %d OFFSET %d", hash( 'sha256', $actor_uri ), $actor_uri, $batch, $offset ), ARRAY_A );
		if ( empty( $rows ) ) {
			break;
		}
		$offset += count( $rows );
		foreach ( $rows as $row ) {
			$payload        = json_decode( (string) ( $row['payload_json'] ?? '' ), true );
			$row['payload'] = is_array( $payload ) ? $payload : array();
			$object_uri     = (string) ( $row['object_uri'] ?? '' );
			if ( '' === $object_uri || isset( $excluded[ $object_uri ] ) || ! axismundi_op_remote_object_is_publicly_listable( $row ) ) {
				continue;
			}
			$published = (string) ( $row['published_at'] ?? $row['remote_updated_at'] ?? $row['created_at'] ?? '' );
			$timestamp = '' !== $published ? strtotime( $published . ' UTC' ) : false;
			$items[]   = array(
				'id'         => 'observed:' . hash( 'sha256', $object_uri ),
				'kind'       => 'observed_object',
				'type'       => 'Object',
				'actor_uri'  => $actor_uri,
				'object_uri' => $object_uri,
				'published'  => false === $timestamp ? '' : gmdate( 'c', $timestamp ),
			);
			if ( count( $items ) >= $limit ) {
				break;
			}
		}
	}
	return $items;
}

/** Record a failed refresh without destroying the last successful payload. */
function axismundi_op_remote_object_record_failure( string $uri, string $error_code ) : bool {
	global $wpdb;
	$row = axismundi_op_get_remote_object( $uri );
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
	$row = axismundi_op_get_remote_object( $uri );
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
	return axismundi_op_get_remote_object( $uri );
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
	// Keep the listing projection in lockstep with cache eviction without assuming that future
	// local sources share the remote table.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- collect exactly the cache rows about to be evicted.
	$purged_uris = (array) $wpdb->get_col( $wpdb->prepare( "SELECT o.object_uri FROM {$table} o WHERE {$where}", $now, $now ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit cache expiry maintenance.
	$result = $wpdb->query( $wpdb->prepare( "DELETE o FROM {$table} o WHERE {$where}", $now, $now ) );
	if ( false !== $result ) {
		foreach ( $purged_uris as $purged_uri ) {
			axismundi_op_delete_object_listing_projection( (string) $purged_uri );
		}
		if ( function_exists( 'axismundi_op_purge_orphan_object_relations' ) ) {
			axismundi_op_purge_orphan_object_relations();
		}
		if ( function_exists( 'axismundi_op_purge_orphan_remote_object_hashtags' ) ) {
			axismundi_op_purge_orphan_remote_object_hashtags();
		}
	}
	return false === $result ? 0 : (int) $result;
}

/** Daily cron callback. */
function axismundi_op_remote_objects_daily_maintenance() : void {
	axismundi_op_remote_objects_purge_expired();
}
add_action( 'axismundi_op_remote_objects_daily', 'axismundi_op_remote_objects_daily_maintenance' );

/** Delete one local cache observation only. */
function axismundi_op_delete_remote_object( string $uri ) : bool {
	global $wpdb;
	if ( AXISMUNDI_OP_DB_VERSION !== (string) get_option( AXISMUNDI_OP_DB_VERSION_OPTION, '' ) ) {
		return false;
	}
	$valid = axismundi_op_remote_object_uri( $uri );
	if ( is_wp_error( $valid ) ) {
		return false;
	}
	$table = axismundi_op_remote_objects_table();
	$existing = axismundi_op_get_remote_object( $valid );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit cache deletion.
	$deleted = false !== $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE object_uri_hash = %s AND object_uri = %s", hash( 'sha256', $valid ), $valid ) );
	if ( $deleted && function_exists( 'axismundi_op_delete_object_relations_for_source' ) ) {
		axismundi_op_delete_object_relations_for_source( $valid );
	}
	if ( $deleted && is_array( $existing ) && function_exists( 'axismundi_op_delete_remote_object_hashtags' ) ) {
		axismundi_op_delete_remote_object_hashtags( (int) $existing['id'] );
	}
	if ( $deleted && is_array( $existing ) && function_exists( 'axismundi_op_replace_object_mentions' ) ) {
		axismundi_op_replace_object_mentions( (string) $existing['object_uri'], array() );
	}
	if ( $deleted ) {
		axismundi_op_delete_object_listing_projection( $valid );
	}
	return $deleted;
}

/*
 * The names these four had before, kept working for one release.
 *
 * They read `remote_object_get`, `remote_object_store`, `remote_object_delete` — noun first, verb
 * last, which is the order nothing else in this codebase uses and the reverse of the neighbours
 * they are called beside (`axismundi_op_delete_object_relations_for_source`,
 * `axismundi_op_index_remote_object_hashtags`). Renaming them is worth doing precisely because
 * they are called from five products, and that is also why the old names cannot simply vanish:
 * a rename applied to definitions and call sites in one commit is a fatal error for anyone whose
 * checkout lands between the two halves.
 *
 * So the definitions move now, the call sites move per product after, and these go when the last
 * one has. They forward silently rather than through `_deprecated_function()`: while the products
 * are still migrating, every notice would be about a call this same commit deliberately left
 * working. The notice belongs in the commit that removes the last caller, not in the one that
 * makes the old name safe.
 *
 * These live in this file, not a separate deprecations file, because the audits require
 * `remote-objects.php` directly rather than booting the plugin. Anything that had these names
 * before still has them, in the same load.
 */

/** @deprecated Use axismundi_op_get_remote_object(). */
function axismundi_op_remote_object_get( string $uri, bool $touch = false ) : ?array {
	return axismundi_op_get_remote_object( $uri, $touch );
}

/** @deprecated Use axismundi_op_get_remote_object_by_hash(). */
function axismundi_op_remote_object_get_by_hash( string $hash, bool $touch = false ) : ?array {
	return axismundi_op_get_remote_object_by_hash( $hash, $touch );
}

/**
 * @deprecated Use axismundi_op_store_remote_object().
 *
 * @param array<string,mixed> $payload Decoded remote object.
 * @param array<string,mixed> $fetch   Optional response metadata.
 * @return array<string,mixed>|WP_Error Stored row.
 */
function axismundi_op_remote_object_store( array $payload, array $fetch = array() ) {
	return axismundi_op_store_remote_object( $payload, $fetch );
}

/** @deprecated Use axismundi_op_delete_remote_object(). */
function axismundi_op_remote_object_delete( string $uri ) : bool {
	return axismundi_op_delete_remote_object( $uri );
}
