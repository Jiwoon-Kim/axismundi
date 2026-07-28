<?php
/**
 * Emoji registry storage.
 *
 * One row per `(emoji_authority, shortcode_key)`, which is the identity FEP-9098
 * defines and *not* the icon URL: every sample we captured serves its image from a
 * different host than the one that declares it, and one emoji was observed on two
 * different CDN hosts. The URL is a download source that may change under a stable
 * emoji; the authority and the name are what persist.
 *
 * Three independent state columns, because they answer three questions
 * (docs/AXISMUNDI-EMOJI-ARCHITECTURE.md §3, §7):
 *
 *   review_status   has a human looked at it?      governs rendering
 *   license_state   may we re-use it?              governs import and outbound
 *   local_only      may the origin's copy leave?   governs outbound only
 *
 * None derives from another. A restrictive licence still permits an approved render,
 * because displaying a message the way its author wrote it is not re-using their
 * asset as ours.
 *
 * There is deliberately no `attachment_id`. An emoji is a catalogue entry — shortcode,
 * aliases, category, licence, distribution flags, approval, outbound permission — and
 * an attachment post can carry none of that, so routing local emoji through the Media
 * Library would scatter every field that matters. `source_kind` records where the bytes
 * came from instead: `bundled` for the one this plugin ships, `upload` for files taken
 * through `wp_handle_upload()` into `uploads/axismundi-emoji/local/`, `remote` for a
 * cached observation.
 *
 * `content_hash` is the storage key, deliberately separate from `cached_path`. Two
 * emoji can be byte-identical — the same image gets re-uploaded under different names,
 * and one emoji was observed served from two different CDN hosts — so files are stored
 * once per hash and rows point at it. Reference-counted GC then asks how many rows
 * still share a hash, which a path column alone cannot answer. It has to exist before
 * any bytes are written, or the very first cache is already unreclaimable.
 *
 * `verified_at` records when the canonical fields were last written by the authority
 * itself rather than merely asserted by a bystander ({@see axismundi_emoji_observe()}).
 *
 * The `imported_from_*` columns record provenance for a local row copied out of a cached
 * remote one. A local emoji is ours to publish, but where it came from stays a fact worth
 * keeping: the originating authority may revise its emoji, and without a recorded origin
 * there is no way to notice, let alone offer to re-sync. `imported_updated_raw` is the
 * origin's `updated` value at copy time, which is the same signal §3 already uses for
 * invalidation — a newer one upstream means our copy is stale. Recording the authority
 * also keeps the licence question answerable months later, when "where did this come
 * from" is otherwise unrecoverable.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_EMOJI_DB_VERSION = '1.8';

/** @return string Registry table name. */
function axismundi_emoji_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_emojis';
}

/** @return string Authority-scoped decision table name. */
function axismundi_emoji_authorities_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_emoji_authorities';
}

/** @return string Emoji-to-subject reference table name. */
function axismundi_emoji_references_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_emoji_references';
}

/**
 * Create or upgrade the registry schema.
 *
 * @return void
 */
function axismundi_emoji_install() : void {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();

	/*
	 * `shortcode` keeps the name exactly as it arrived, colons and any `@authority`
	 * included, because that string has to be reproduced verbatim in `alt`, in `title`,
	 * and on every plain-text surface. `shortcode_key` is the normalized lookup form.
	 * Storing only one of them would either break those surfaces or break lookup.
	 */
	dbDelta(
		'CREATE TABLE ' . axismundi_emoji_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scope varchar(8) NOT NULL DEFAULT 'remote',
			emoji_authority varchar(191) NOT NULL,
			shortcode varchar(191) NOT NULL,
			shortcode_key varchar(191) NOT NULL,
			declared_id text DEFAULT NULL,
			verification_uri text DEFAULT NULL,
			source_url text DEFAULT NULL,
			updated_raw varchar(64) DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			declared_media_type varchar(64) DEFAULT NULL,
			media_type varchar(64) DEFAULT NULL,
			animated tinyint(1) DEFAULT NULL,
			frame_count int(10) unsigned DEFAULT NULL,
			byte_size bigint(20) unsigned DEFAULT NULL,
			static_byte_size bigint(20) unsigned DEFAULT NULL,
			reserved_bytes bigint(20) unsigned DEFAULT NULL,
			reserved_at datetime DEFAULT NULL,
			width int(10) unsigned DEFAULT NULL,
			height int(10) unsigned DEFAULT NULL,
			content_hash char(64) DEFAULT NULL,
			cached_path text DEFAULT NULL,
			static_path text DEFAULT NULL,
			license_raw longtext DEFAULT NULL,
			license_text text DEFAULT NULL,
			license_state varchar(16) NOT NULL DEFAULT 'unknown',
			review_status varchar(16) NOT NULL DEFAULT 'pending',
			review_reason varchar(191) DEFAULT NULL,
			reviewed_at datetime DEFAULT NULL,
			reviewed_by bigint(20) unsigned DEFAULT NULL,
			approval_batch varchar(64) DEFAULT NULL,
			local_only tinyint(1) DEFAULT NULL,
			is_sensitive tinyint(1) DEFAULT NULL,
			category varchar(191) DEFAULT NULL,
			picker_visible tinyint(1) NOT NULL DEFAULT 0,
			outbound_allowed tinyint(1) NOT NULL DEFAULT 0,
			aliases text DEFAULT NULL,
			source_kind varchar(16) NOT NULL DEFAULT 'remote',
			imported_from_authority varchar(191) DEFAULT NULL,
			imported_from_id bigint(20) unsigned DEFAULT NULL,
			imported_updated_raw varchar(64) DEFAULT NULL,
			imported_at datetime DEFAULT NULL,
			first_seen_at datetime NOT NULL,
			last_seen_at datetime NOT NULL,
			last_accessed_at datetime DEFAULT NULL,
			fetched_at datetime DEFAULT NULL,
			verified_at datetime DEFAULT NULL,
			failure_count int(10) unsigned NOT NULL DEFAULT 0,
			next_attempt_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY emoji_identity (emoji_authority, shortcode_key),
			KEY review_queue (review_status, last_seen_at),
			KEY approval_batch (approval_batch),
			KEY authority_scope (emoji_authority, scope),
			KEY content (content_hash),
			KEY retry (review_reason, next_attempt_at)
		) ENGINE=InnoDB {$charset};"
	);

	/*
	 * Scope-level decisions exist from the first release even though only per-emoji
	 * review has a UI. An approval is the one thing in this store that cannot be
	 * reconstructed by re-fetching, so the scope it attaches to has to be right before
	 * any are recorded; adding it later would mean migrating human decisions.
	 */
	dbDelta(
		'CREATE TABLE ' . axismundi_emoji_authorities_table() . " (
			emoji_authority varchar(191) NOT NULL,
			review_default varchar(16) NOT NULL DEFAULT 'pending',
			fallback_priority smallint(5) unsigned NOT NULL DEFAULT 0,
			rejected_streak int(10) unsigned NOT NULL DEFAULT 0,
			reviewed_at datetime DEFAULT NULL,
			reviewed_by bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY  (emoji_authority)
		) ENGINE=InnoDB {$charset};"
	);

	dbDelta(
		'CREATE TABLE ' . axismundi_emoji_references_table() . " (
			emoji_id bigint(20) unsigned NOT NULL,
			subject_kind varchar(8) NOT NULL,
			subject_uri_hash char(64) NOT NULL,
			last_seen_at datetime NOT NULL,
			PRIMARY KEY  (emoji_id, subject_kind, subject_uri_hash),
			KEY subject (subject_kind, subject_uri_hash)
		) ENGINE=InnoDB {$charset};"
	);

	update_option( 'ax_emoji_db_version', AXISMUNDI_EMOJI_DB_VERSION, false );

	/*
	 * The blob store predates any row that points into it, and the guard predates any
	 * file. Creating them here rather than lazily means an operator who looks at the
	 * uploads directory after activating never finds a browsable one.
	 */
	if ( function_exists( 'axismundi_emoji_ensure_blob_dir' ) ) {
		axismundi_emoji_ensure_blob_dir();
	}
	if ( function_exists( 'axismundi_emoji_register_bundled' ) ) {
		axismundi_emoji_register_bundled();
	}
}

/** @return bool Whether the registry tables are queryable. */
function axismundi_emoji_ready() : bool {
	global $wpdb;
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema probe on a plugin-owned table.
	return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
}

/**
 * Run dbDelta when the stored schema version lags the code, so an upgrade does not
 * require deactivating and reactivating the plugin.
 *
 * @return void
 */
function axismundi_emoji_maybe_upgrade() : void {
	if ( AXISMUNDI_EMOJI_DB_VERSION === (string) get_option( 'ax_emoji_db_version', '' ) && axismundi_emoji_ready() ) {
		return;
	}
	axismundi_emoji_install();
}
add_action( 'init', 'axismundi_emoji_maybe_upgrade', 5 );

/**
 * Provision a newly shipped bundled emoji even when the database schema is unchanged.
 *
 * @return void
 */
function axismundi_emoji_bootstrap_bundled() : void {
	if ( axismundi_emoji_ready() && function_exists( 'axismundi_emoji_register_bundled' ) ) {
		axismundi_emoji_register_bundled();
	}
}
add_action( 'init', 'axismundi_emoji_bootstrap_bundled', 6 );

/**
 * One registry row by identity.
 *
 * @param string $authority Declaring authority.
 * @param string $key       Normalized shortcode key.
 * @return array<string,mixed>|null
 */
function axismundi_emoji_get( string $authority, string $key ) : ?array {
	global $wpdb;
	if ( '' === $authority || '' === $key || ! axismundi_emoji_ready() ) {
		return null;
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; values prepared.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE emoji_authority = %s AND shortcode_key = %s", $authority, $key ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * The review default an authority carries, used when a new emoji from it is first seen.
 *
 * @param string $authority Declaring authority.
 * @return string pending|approved|rejected
 */
function axismundi_emoji_authority_default( string $authority ) : string {
	global $wpdb;
	if ( '' === $authority || ! axismundi_emoji_ready() ) {
		return 'pending';
	}
	$table = axismundi_emoji_authorities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	$value = (string) $wpdb->get_var( $wpdb->prepare( "SELECT review_default FROM {$table} WHERE emoji_authority = %s", $authority ) );
	return in_array( $value, AXISMUNDI_EMOJI_REVIEW_STATES, true ) ? $value : 'pending';
}
