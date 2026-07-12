<?php
/**
 * Phase 3a — used-in relation core.
 *
 * The `wp_ax_media_relations` table (DATA-MODEL.md §4) + its store service: identity
 * keys, atomic per-(subject, provider) replace with dedup/aggregation, and a
 * read-filtered reverse lookup. Providers and incremental hooks are Phase 3b; the
 * `legacy_parent` migration is Phase 3d — this file only owns `usage` relations.
 *
 * Keys are stored as sha256 **hex** in CHAR(64) ascii columns (not BINARY) so a
 * single NOT NULL identity dedups local + remote without the nullable-UNIQUE pitfall,
 * with no binary-in-wpdb quirks and a compact index.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_MEDIA_RELATIONS_DB_VERSION = 1;
const AXISMUNDI_MEDIA_RELATIONS_DB_OPTION  = 'ax_media_relations_db_version';

/**
 * Fully-qualified relations table name.
 *
 * @return string
 */
function axismundi_media_relations_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_media_relations';
}

/**
 * Create/upgrade the table via dbDelta, gated by a schema-version option.
 *
 * @return void
 */
function axismundi_media_relations_install() : void {
	if ( (int) get_option( AXISMUNDI_MEDIA_RELATIONS_DB_OPTION, 0 ) >= AXISMUNDI_MEDIA_RELATIONS_DB_VERSION ) {
		return;
	}
	global $wpdb;
	$table   = axismundi_media_relations_table();
	$collate = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		relation_kind varchar(20) NOT NULL DEFAULT 'usage',
		subject_type varchar(20) NOT NULL DEFAULT 'post',
		subject_post_id bigint(20) unsigned DEFAULT NULL,
		subject_uri text DEFAULT NULL,
		subject_uri_hash char(64) CHARACTER SET ascii DEFAULT NULL,
		subject_key char(64) CHARACTER SET ascii NOT NULL,
		predicate varchar(40) NOT NULL DEFAULT 'as:attachment',
		object_attachment_id bigint(20) unsigned DEFAULT NULL,
		object_uri text DEFAULT NULL,
		object_uri_hash char(64) CHARACTER SET ascii DEFAULT NULL,
		object_key char(64) CHARACTER SET ascii NOT NULL,
		role varchar(20) NOT NULL DEFAULT 'content',
		provider varchar(40) NOT NULL,
		source_key varchar(191) DEFAULT NULL,
		occurrence_count int unsigned NOT NULL DEFAULT 1,
		origin varchar(10) NOT NULL DEFAULT 'local',
		status varchar(10) NOT NULL DEFAULT 'active',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY relation_identity (relation_kind,subject_key,object_key,predicate,role,provider),
		KEY subject_provider (relation_kind,subject_key,provider),
		KEY reverse_local (relation_kind,status,object_attachment_id),
		KEY reverse_uri (relation_kind,status,object_uri_hash),
		KEY subject_post (subject_post_id)
	) ENGINE=InnoDB {$collate};";

	dbDelta( $sql );

	// Only advance the schema version once the table AND its dedup UNIQUE index really
	// exist — otherwise a failed create would permanently skip re-install.
	$table_ok = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$index_ok = false;
	if ( $table_ok ) {
		$index_ok = (bool) $wpdb->get_var( "SHOW INDEX FROM {$table} WHERE Key_name = 'relation_identity'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}
	if ( $table_ok && $index_ok ) {
		update_option( AXISMUNDI_MEDIA_RELATIONS_DB_OPTION, AXISMUNDI_MEDIA_RELATIONS_DB_VERSION );
	}
}
add_action( 'admin_init', 'axismundi_media_relations_install' );

/**
 * Normalized NOT NULL identity for a subject/object: a local id wins, else a URI.
 *
 * @param string      $local_prefix `post` | `attachment`.
 * @param int         $local_id     Local ID (0 = none).
 * @param string|null $uri          Canonical URI.
 * @return string sha256 hex, or '' when neither is present.
 */
function axismundi_media_relation_key( string $local_prefix, int $local_id, ?string $uri ) : string {
	if ( $local_id > 0 ) {
		return hash( 'sha256', $local_prefix . ':' . $local_id );
	}
	if ( null !== $uri && '' !== $uri ) {
		return hash( 'sha256', 'uri:' . $uri );
	}
	return '';
}

/**
 * sha256-hex of a URI (for remote reverse lookup), or null.
 *
 * @param string|null $uri URI.
 * @return string|null
 */
function axismundi_media_relation_uri_hash( ?string $uri ) : ?string {
	return ( null !== $uri && '' !== $uri ) ? hash( 'sha256', $uri ) : null;
}

/**
 * Atomically replace a subject's relations for one provider (DATA-MODEL §4).
 * Dedups + aggregates the input by (object_key, predicate, role), deletes the
 * existing (subject, provider) rows, and inserts the fresh set in a transaction. An
 * empty input just clears that provider's rows for the subject.
 *
 * @param array<string,mixed>              $subject   ['post_id'=>int, 'uri'=>string, 'type'=>string].
 * @param string                           $provider  Provider slug.
 * @param array<int,array<string,mixed>>   $relations Each: predicate, role, object_attachment_id, object_uri, source_key.
 * @param string                           $kind      relation_kind (default `usage`).
 * @return int|WP_Error Rows written, or error.
 */
function axismundi_media_relations_replace( array $subject, string $provider, array $relations, string $kind = 'usage' ) {
	global $wpdb;
	$table        = axismundi_media_relations_table();
	$subject_post = isset( $subject['post_id'] ) ? (int) $subject['post_id'] : 0;
	$subject_uri  = isset( $subject['uri'] ) ? (string) $subject['uri'] : '';
	// Validate/normalize BEFORE any mutation, so a bad provider result never deletes
	// the existing rows and then fails to re-insert.
	$subject_type = in_array( (string) ( $subject['type'] ?? '' ), array( 'post', 'remote_object' ), true )
		? (string) $subject['type']
		: ( $subject_post > 0 ? 'post' : 'remote_object' );
	$kind         = in_array( $kind, array( 'usage', 'legacy_parent' ), true ) ? $kind : 'usage';
	$subject_key  = axismundi_media_relation_key( 'post', $subject_post, '' !== $subject_uri ? $subject_uri : null );
	if ( '' === $subject_key ) {
		return new WP_Error( 'ax_media_relation_subject', __( 'Invalid relation subject.', 'axismundi-media-library' ) );
	}
	if ( '' === $provider || strlen( $provider ) > 40 ) {
		return new WP_Error( 'ax_media_relation_provider', __( 'Invalid relation provider.', 'axismundi-media-library' ) );
	}

	// Dedup + aggregate by (object_key, predicate, role).
	$rows = array();
	foreach ( $relations as $rel ) {
		$oa  = isset( $rel['object_attachment_id'] ) ? (int) $rel['object_attachment_id'] : 0;
		$ou  = isset( $rel['object_uri'] ) ? (string) $rel['object_uri'] : '';
		$key = axismundi_media_relation_key( 'attachment', $oa, '' !== $ou ? $ou : null );
		if ( '' === $key ) {
			continue;
		}
		$predicate = isset( $rel['predicate'] ) ? (string) $rel['predicate'] : 'as:attachment';
		if ( ! in_array( $predicate, array( 'as:attachment', 'as:image', 'as:icon', 'schema:associatedMedia' ), true ) ) {
			$predicate = 'as:attachment';
		}
		$role = isset( $rel['role'] ) ? (string) $rel['role'] : 'content';
		if ( ! in_array( $role, array( 'featured', 'content', 'gallery', 'cover', 'media_text', 'file', 'audio', 'video', 'poster', 'decorative' ), true ) ) {
			$role = 'content';
		}
		$dedup = $key . '|' . $predicate . '|' . $role;
		if ( isset( $rows[ $dedup ] ) ) {
			++$rows[ $dedup ]['occurrence_count'];
			continue;
		}
		$rows[ $dedup ] = array(
			'object_attachment_id' => $oa > 0 ? $oa : null,
			'object_uri'           => '' !== $ou ? $ou : null,
			'object_uri_hash'      => axismundi_media_relation_uri_hash( '' !== $ou ? $ou : null ),
			'object_key'           => $key,
			'predicate'            => $predicate,
			'role'                 => $role,
			'source_key'           => isset( $rel['source_key'] ) ? (string) $rel['source_key'] : null,
			'occurrence_count'     => 1,
		);
	}

	$now = current_time( 'mysql', true );
	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "DELETE FROM {$table} WHERE relation_kind = %s AND subject_key = %s AND provider = %s", $kind, $subject_key, $provider )
	);
	if ( false === $deleted ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return new WP_Error( 'ax_media_relation_delete', $wpdb->last_error );
	}

	$written = 0;
	foreach ( $rows as $row ) {
		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			array(
				'relation_kind'        => $kind,
				'subject_type'         => $subject_type,
				'subject_post_id'      => $subject_post > 0 ? $subject_post : null,
				'subject_uri'          => '' !== $subject_uri ? $subject_uri : null,
				'subject_uri_hash'     => axismundi_media_relation_uri_hash( '' !== $subject_uri ? $subject_uri : null ),
				'subject_key'          => $subject_key,
				'predicate'            => $row['predicate'],
				'object_attachment_id' => $row['object_attachment_id'],
				'object_uri'           => $row['object_uri'],
				'object_uri_hash'      => $row['object_uri_hash'],
				'object_key'           => $row['object_key'],
				'role'                 => $row['role'],
				'provider'             => $provider,
				'source_key'           => $row['source_key'],
				'occurrence_count'     => $row['occurrence_count'],
				'origin'               => 'remote_object' === $subject_type ? 'remote' : 'local',
				'status'               => 'active',
				'created_at'           => $now,
				'updated_at'           => $now,
			)
		);
		if ( false === $ok ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return new WP_Error( 'ax_media_relation_insert', $wpdb->last_error );
		}
		++$written;
	}
	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return $written;
}

/**
 * Reverse "used in" lookup for a local attachment: active `usage` relations whose
 * subject the viewer may read (an owner never sees a source they cannot read; admins
 * may). Providers/UI (Phase 3b/3c) consume this.
 *
 * @param int      $attachment_id Attachment ID.
 * @param int|null $viewer_id     Viewer (defaults to current user).
 * @return array<int,array<string,mixed>>
 */
function axismundi_media_relations_used_in( int $attachment_id, ?int $viewer_id = null ) : array {
	global $wpdb;
	$viewer_id = $viewer_id ?? get_current_user_id();
	$table     = axismundi_media_relations_table();
	$rows      = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "SELECT * FROM {$table} WHERE relation_kind = 'usage' AND status = 'active' AND object_attachment_id = %d ORDER BY role, id", $attachment_id ),
		ARRAY_A
	);
	if ( ! $rows ) {
		return array();
	}
	$out = array();
	foreach ( $rows as $row ) {
		$sp = (int) $row['subject_post_id'];
		if ( 'post' === $row['subject_type'] && $sp > 0 && ! axismundi_media_relation_can_read_subject( $sp, (int) $viewer_id ) ) {
			continue;
		}
		$out[] = $row;
	}
	return $out;
}

/**
 * A subject's relation rows (all, or one provider). For UI/CLI and tests.
 *
 * @param int         $post_id  Subject post ID.
 * @param string|null $provider Provider slug (null = all).
 * @return array<int,array<string,mixed>>
 */
function axismundi_media_relations_for_subject( int $post_id, ?string $provider = null ) : array {
	global $wpdb;
	$table = axismundi_media_relations_table();
	if ( null !== $provider ) {
		return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT * FROM {$table} WHERE subject_post_id = %d AND provider = %s ORDER BY role, id", $post_id, $provider ),
			ARRAY_A
		);
	}
	return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "SELECT * FROM {$table} WHERE subject_post_id = %d ORDER BY provider, role, id", $post_id ),
		ARRAY_A
	);
}

/**
 * May the viewer read a source post? Published is public; otherwise the viewer needs
 * read/edit access (admins via read_others/edit_others).
 *
 * @param int $post_id   Source post ID.
 * @param int $viewer_id Viewer.
 * @return bool
 */
function axismundi_media_relation_can_read_subject( int $post_id, int $viewer_id ) : bool {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return false;
	}
	// Publicly viewable = a viewable status AND a public post type — not merely
	// `publish`, since a non-publicly_queryable internal CPT can be published too.
	if ( is_post_publicly_viewable( $post ) ) {
		return true;
	}
	return $viewer_id > 0 && ( user_can( $viewer_id, 'read_post', $post_id ) || user_can( $viewer_id, 'edit_post', $post_id ) );
}

/**
 * Remove every relation whose subject is this post (Phase 3b hooks this on delete).
 *
 * @param int $post_id Subject post ID.
 * @return void
 */
function axismundi_media_relations_delete_subject( int $post_id ) : void {
	global $wpdb;
	$wpdb->delete( axismundi_media_relations_table(), array( 'subject_post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

/**
 * Remove every relation whose OBJECT is this attachment (Phase 3b hooks this on an
 * attachment delete, alongside delete_subject).
 *
 * @param int $attachment_id Object attachment ID.
 * @return void
 */
function axismundi_media_relations_delete_object( int $attachment_id ) : void {
	global $wpdb;
	$wpdb->delete( axismundi_media_relations_table(), array( 'object_attachment_id' => (int) $attachment_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}
