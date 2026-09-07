<?php
/**
 * Phase 3d legacy post_parent snapshot, detach, and rollback services.
 *
 * Snapshot and mutation are deliberately separate. A detach only touches an
 * Attachment whose current parent exactly matches its stored snapshot; rollback
 * only restores while the Attachment remains detached (`post_parent = 0`).
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/**
 * Attachments that currently have a parent post.
 *
 * @return array<int,array{attachment_id:int,parent_id:int}>
 */
function axismundi_media_legacy_parent_candidates() : array {
	global $wpdb;
	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "SELECT ID AS attachment_id, post_parent AS parent_id FROM {$wpdb->posts} WHERE post_type = %s AND post_parent > 0 ORDER BY ID", 'attachment' ),
		ARRAY_A
	);
	return array_map(
		static fn( array $row ) : array => array( 'attachment_id' => (int) $row['attachment_id'], 'parent_id' => (int) $row['parent_id'] ),
		(array) $rows
	);
}

/**
 * Original parent snapshot for an Attachment (first snapshot wins).
 *
 * @param int $attachment_id Attachment ID.
 * @return int Parent post ID, or 0 when none.
 */
function axismundi_media_legacy_parent_snapshot_for( int $attachment_id ) : int {
	global $wpdb;
	$table = axismundi_media_relations_table();
	return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "SELECT subject_post_id FROM {$table} WHERE relation_kind = 'legacy_parent' AND object_attachment_id = %d ORDER BY id ASC LIMIT 1", $attachment_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
}

/**
 * Store one immutable legacy-parent snapshot. Existing same-parent snapshots are
 * idempotent; a different prior snapshot is a conflict and is never overwritten.
 *
 * @param int $attachment_id Attachment ID.
 * @param int $parent_id     Original parent post ID.
 * @return true|WP_Error
 */
function axismundi_media_legacy_parent_snapshot_one( int $attachment_id, int $parent_id ) {
	$attachment = get_post( $attachment_id );
	if ( $attachment_id <= 0 || $parent_id <= 0 || ! $attachment || 'attachment' !== $attachment->post_type || ! get_post( $parent_id ) ) {
		return new WP_Error( 'ax_media_legacy_parent_invalid', __( 'Invalid legacy parent relationship.', 'axismundi-media-library' ) );
	}
	$existing = axismundi_media_legacy_parent_snapshot_for( $attachment_id );
	if ( $existing > 0 ) {
		return $existing === $parent_id
			? true
			: new WP_Error( 'ax_media_legacy_parent_conflict', __( 'This attachment already has a different legacy-parent snapshot.', 'axismundi-media-library' ) );
	}
	if ( (int) $attachment->post_parent !== $parent_id ) {
		return new WP_Error( 'ax_media_legacy_parent_changed', __( 'The attachment parent changed before it could be snapshotted.', 'axismundi-media-library' ) );
	}

	global $wpdb;
	$now = current_time( 'mysql', true );
	$ok  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		axismundi_media_relations_table(),
		array(
			'relation_kind'        => 'legacy_parent',
			'subject_type'         => 'post',
			'subject_post_id'      => $parent_id,
			'subject_key'          => axismundi_media_relation_key( 'post', $parent_id, null ),
			'predicate'            => 'as:attachment',
			'object_attachment_id' => $attachment_id,
			'object_key'           => axismundi_media_relation_key( 'attachment', $attachment_id, null ),
			'role'                 => 'content',
			'provider'             => 'legacy_parent',
			'source_key'           => 'post_parent',
			'occurrence_count'     => 1,
			'origin'               => 'local',
			'status'               => 'active',
			'created_at'           => $now,
			'updated_at'           => $now,
		)
	);
	return false === $ok ? new WP_Error( 'ax_media_legacy_parent_insert', $wpdb->last_error ) : true;
}

/**
 * Preview or record every current parent relationship.
 *
 * @param bool $dry_run Do not write snapshots.
 * @return array{candidates:int,would_snapshot:int,snapshotted:int,existing:int,conflicts:int,errors:int}
 */
function axismundi_media_legacy_parent_snapshot_all( bool $dry_run = true ) : array {
	$report = array( 'candidates' => 0, 'would_snapshot' => 0, 'snapshotted' => 0, 'existing' => 0, 'conflicts' => 0, 'errors' => 0 );
	foreach ( axismundi_media_legacy_parent_candidates() as $candidate ) {
		++$report['candidates'];
		$existing = axismundi_media_legacy_parent_snapshot_for( $candidate['attachment_id'] );
		if ( $existing > 0 ) {
			++$report[ $existing === $candidate['parent_id'] ? 'existing' : 'conflicts' ];
			continue;
		}
		++$report['would_snapshot'];
		if ( $dry_run ) {
			continue;
		}
		$result = axismundi_media_legacy_parent_snapshot_one( $candidate['attachment_id'], $candidate['parent_id'] );
		if ( is_wp_error( $result ) ) {
			++$report['errors'];
		} else {
			++$report['snapshotted'];
		}
	}
	return $report;
}

/**
 * Preview or detach one exact, snapshotted relationship.
 *
 * @param int  $attachment_id Attachment ID.
 * @param bool $dry_run       Do not update post_parent.
 * @return string|WP_Error would_detach|detached|unsnapshotted|conflict|missing.
 */
function axismundi_media_legacy_parent_detach_one( int $attachment_id, bool $dry_run = true ) {
	$attachment = get_post( $attachment_id );
	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return 'missing';
	}
	$snapshot = axismundi_media_legacy_parent_snapshot_for( $attachment_id );
	if ( 0 === $snapshot ) {
		return 'unsnapshotted';
	}
	if ( (int) $attachment->post_parent !== $snapshot ) {
		return 'conflict';
	}
	if ( $dry_run ) {
		return 'would_detach';
	}
	$result = wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => 0 ), true );
	return is_wp_error( $result ) ? $result : 'detached';
}

/**
 * Preview or detach current relationships that have an exact snapshot match.
 *
 * @param bool $dry_run Do not update post_parent.
 * @return array{candidates:int,would_detach:int,detached:int,unsnapshotted:int,conflicts:int,errors:int}
 */
function axismundi_media_legacy_parent_detach_all( bool $dry_run = true ) : array {
	$report = array( 'candidates' => 0, 'would_detach' => 0, 'detached' => 0, 'unsnapshotted' => 0, 'conflicts' => 0, 'errors' => 0 );
	foreach ( axismundi_media_legacy_parent_candidates() as $candidate ) {
		++$report['candidates'];
		$result = axismundi_media_legacy_parent_detach_one( $candidate['attachment_id'], $dry_run );
		if ( is_wp_error( $result ) ) {
			++$report['errors'];
		} elseif ( isset( $report[ $result ] ) ) {
			++$report[ $result ];
		}
	}
	return $report;
}

/**
 * All first-wins legacy snapshots, one per Attachment.
 *
 * @return array<int,array{attachment_id:int,parent_id:int}>
 */
function axismundi_media_legacy_parent_snapshots() : array {
	global $wpdb;
	$table = axismundi_media_relations_table();
	$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		"SELECT r.object_attachment_id AS attachment_id, r.subject_post_id AS parent_id FROM {$table} AS r INNER JOIN ( SELECT object_attachment_id, MIN(id) AS first_id FROM {$table} WHERE relation_kind = 'legacy_parent' GROUP BY object_attachment_id ) AS first_row ON first_row.first_id = r.id ORDER BY r.object_attachment_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		ARRAY_A
	);
	return array_map(
		static fn( array $row ) : array => array( 'attachment_id' => (int) $row['attachment_id'], 'parent_id' => (int) $row['parent_id'] ),
		(array) $rows
	);
}

/**
 * Preview or restore one snapshot while the Attachment remains detached.
 *
 * @param int  $attachment_id Attachment ID.
 * @param bool $dry_run       Do not update post_parent.
 * @return string|WP_Error would_restore|restored|already_restored|conflict|missing.
 */
function axismundi_media_legacy_parent_rollback_one( int $attachment_id, bool $dry_run = true ) {
	$attachment = get_post( $attachment_id );
	$parent_id  = axismundi_media_legacy_parent_snapshot_for( $attachment_id );
	$parent     = $parent_id > 0 ? get_post( $parent_id ) : null;
	if ( ! $attachment || 'attachment' !== $attachment->post_type || ! $parent ) {
		return 'missing';
	}
	if ( (int) $attachment->post_parent === $parent_id ) {
		return 'already_restored';
	}
	if ( (int) $attachment->post_parent !== 0 ) {
		return 'conflict';
	}
	if ( $dry_run ) {
		return 'would_restore';
	}
	$result = wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $parent_id ), true );
	return is_wp_error( $result ) ? $result : 'restored';
}

/**
 * Preview or restore snapshots while ownership is still clear (`post_parent=0`).
 * Existing nonzero parents are never overwritten.
 *
 * @param bool $dry_run Do not update post_parent.
 * @return array{snapshots:int,would_restore:int,restored:int,already_restored:int,conflicts:int,missing:int,errors:int}
 */
function axismundi_media_legacy_parent_rollback_all( bool $dry_run = true ) : array {
	$report = array( 'snapshots' => 0, 'would_restore' => 0, 'restored' => 0, 'already_restored' => 0, 'conflicts' => 0, 'missing' => 0, 'errors' => 0 );
	foreach ( axismundi_media_legacy_parent_snapshots() as $snapshot ) {
		++$report['snapshots'];
		$result = axismundi_media_legacy_parent_rollback_one( $snapshot['attachment_id'], $dry_run );
		if ( is_wp_error( $result ) ) {
			++$report['errors'];
		} elseif ( isset( $report[ $result ] ) ) {
			++$report[ $result ];
		}
	}
	return $report;
}
