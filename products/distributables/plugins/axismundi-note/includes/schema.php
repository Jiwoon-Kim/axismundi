<?php
/**
 * Note envelope store installation and verification.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_NOTE_DB_VERSION        = '5';
const AXISMUNDI_NOTE_DB_VERSION_OPTION = 'ax_note_db_version';

/** Envelope table for the current site. */
function axismundi_note_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_notes';
}

/**
 * Create or upgrade the Note envelope store and record only a verified version.
 *
 * The version option is recorded only after the columns, both unique keys, and
 * the InnoDB engine are confirmed present, so a partial dbDelta never advertises
 * a ready store.
 */
function axismundi_note_install_table() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = axismundi_note_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			local_uuid char(36) NOT NULL,
			actor_uri text NOT NULL,
			actor_uri_hash char(64) NOT NULL,
			visibility varchar(16) NOT NULL,
			language_tag varchar(35) NOT NULL,
			in_reply_to_uri text NOT NULL,
			in_reply_to_uri_hash char(64) NOT NULL,
			context_uri text NOT NULL,
			context_uri_hash char(64) NOT NULL,
			quote_target_uri text NULL,
			quote_target_uri_hash char(64) NOT NULL DEFAULT '',
			quote_generation bigint(20) unsigned NOT NULL DEFAULT 1,
			quote_policy varchar(16) NOT NULL DEFAULT '',
			quote_policy_authored tinyint(1) unsigned NOT NULL DEFAULT 0,
			is_sensitive tinyint(1) unsigned NOT NULL DEFAULT 0,
			content_warning varchar(500) NOT NULL DEFAULT '',
			mention_actor_uris_json longtext NOT NULL,
			object_status varchar(16) NOT NULL DEFAULT 'active',
			attribution_locked_at datetime NULL,
			deleted_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY post_id (post_id),
			UNIQUE KEY local_uuid (local_uuid),
			KEY actor_uri_hash (actor_uri_hash),
			KEY in_reply_to_uri_hash (in_reply_to_uri_hash),
			KEY context_uri_hash (context_uri_hash),
			KEY quote_target_uri_hash (quote_target_uri_hash),
			KEY object_status (object_status)
		) ENGINE=InnoDB {$charset};"
	);
	// Pre-v4 non-default values were necessarily authored. `anyone` is ambiguous
	// because v4 backfilled it, so it remains un-authored and therefore denies
	// until the author explicitly saves a policy again.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Trusted table identifier; fixed values.
	$wpdb->query( "UPDATE {$table} SET quote_policy_authored = 1 WHERE quote_policy IN ('followers','me') AND quote_policy_authored = 0" );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
	$columns  = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
	$post_key = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'post_id'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
	$uuid_key = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'local_uuid'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
	$indexes = array_unique( (array) $wpdb->get_col( "SHOW INDEX FROM {$table}", 2 ) );
	$status  = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $table ) ), ARRAY_A );
	$needed  = array(
		'id', 'post_id', 'local_uuid', 'actor_uri', 'actor_uri_hash', 'visibility',
		'language_tag', 'in_reply_to_uri', 'in_reply_to_uri_hash', 'context_uri',
		'context_uri_hash', 'quote_target_uri', 'quote_target_uri_hash', 'quote_generation',
		'quote_policy', 'quote_policy_authored', 'is_sensitive',
		'content_warning', 'mention_actor_uris_json',
		'object_status', 'attribution_locked_at', 'deleted_at', 'created_at', 'updated_at',
	);
	$required_indexes = array( 'post_id', 'local_uuid', 'actor_uri_hash', 'in_reply_to_uri_hash', 'context_uri_hash', 'quote_target_uri_hash', 'object_status' );
	$valid = empty( array_diff( $needed, $columns ) )
		&& empty( array_diff( $required_indexes, $indexes ) )
		&& ! empty( $post_key ) && 0 === (int) $post_key[0]['Non_unique']
		&& ! empty( $uuid_key ) && 0 === (int) $uuid_key[0]['Non_unique']
		&& 'InnoDB' === (string) ( $status['Engine'] ?? '' );
	if ( $valid ) {
		update_option( AXISMUNDI_NOTE_DB_VERSION_OPTION, AXISMUNDI_NOTE_DB_VERSION, false );
	}
	return $valid;
}

/** Ensure upgrades run on ordinary plugin updates as well as activation. */
function axismundi_note_maybe_install() : void {
	if ( AXISMUNDI_NOTE_DB_VERSION !== (string) get_option( AXISMUNDI_NOTE_DB_VERSION_OPTION, '' ) ) {
		axismundi_note_install_table();
	}
}
add_action( 'plugins_loaded', 'axismundi_note_maybe_install', 20 );

/** Whether the verified envelope store is ready. */
function axismundi_note_ready() : bool {
	return AXISMUNDI_NOTE_DB_VERSION === (string) get_option( AXISMUNDI_NOTE_DB_VERSION_OPTION, '' );
}
