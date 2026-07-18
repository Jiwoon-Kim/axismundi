<?php
/**
 * Rebuildable Object-to-Object relation projections.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Object relation table name. */
function axismundi_op_object_relations_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_object_relations';
}

/** Install and verify the rebuildable relation projection. */
function axismundi_op_install_object_relations() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = axismundi_op_object_relations_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			relation_type varchar(32) NOT NULL,
			source_object_uri text NOT NULL,
			source_object_uri_hash char(64) NOT NULL,
			target_object_uri text NOT NULL,
			target_object_uri_hash char(64) NOT NULL,
			source_actor_uri text DEFAULT NULL,
			source_actor_uri_hash char(64) DEFAULT NULL,
			evidence_type varchar(16) NOT NULL,
			consent_status varchar(24) NOT NULL,
			authorization_uri text DEFAULT NULL,
			authorization_uri_hash char(64) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY relation_identity (relation_type, source_object_uri_hash, target_object_uri_hash),
			KEY target_lookup (relation_type, target_object_uri_hash),
			KEY authorization_lookup (authorization_uri_hash)
		) ENGINE=InnoDB {$charset};"
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom schema verification.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom index verification.
	$identity = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'relation_identity'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom engine verification.
	$engine = (string) $wpdb->get_var( "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}'" );

	return in_array( 'authorization_uri_hash', $columns, true )
		&& in_array( 'consent_status', $columns, true )
		&& count( $identity ) >= 3
		&& 0 === (int) $identity[0]['Non_unique']
		&& 'InnoDB' === $engine;
}

/** Normalize one relation URI without performing a network request. */
function axismundi_op_relation_uri( $value ) : string {
	if ( is_array( $value ) ) {
		$value = $value['id'] ?? $value['href'] ?? '';
	}
	if ( ! is_scalar( $value ) ) {
		return '';
	}
	$uri   = trim( (string) $value );
	$parts = wp_parse_url( $uri );
	if ( ! is_array( $parts )
		|| ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true )
		|| empty( $parts['host'] )
		|| isset( $parts['user'] )
		|| isset( $parts['pass'] )
	) {
		return '';
	}
	return $uri;
}

/** Add one quote candidate to the normalized candidate map. */
function axismundi_op_add_quote_candidate( array &$candidates, $value, string $evidence ) : void {
	$uri = axismundi_op_relation_uri( $value );
	if ( '' === $uri ) {
		return;
	}
	$rank = array( 'legacy' => 1, 'misskey' => 2, 'fep044f' => 3 );
	if ( ! isset( $candidates[ $uri ] ) || $rank[ $evidence ] > $rank[ $candidates[ $uri ] ] ) {
		$candidates[ $uri ] = $evidence;
	}
}

/** Extract all quote targets without silently resolving conflicting aliases. */
function axismundi_op_quote_candidates( array $payload ) : array {
	$candidates = array();
	axismundi_op_add_quote_candidate( $candidates, $payload['quote'] ?? '', 'fep044f' );
	axismundi_op_add_quote_candidate( $candidates, $payload['_misskey_quote'] ?? '', 'misskey' );
	axismundi_op_add_quote_candidate( $candidates, $payload['quoteUrl'] ?? '', 'legacy' );
	axismundi_op_add_quote_candidate( $candidates, $payload['quoteUri'] ?? '', 'legacy' );

	$tags = $payload['tag'] ?? array();
	$tags = is_array( $tags ) && array_is_list( $tags ) ? $tags : array( $tags );
	foreach ( $tags as $tag ) {
		if ( ! is_array( $tag ) || 'Link' !== (string) ( $tag['type'] ?? '' ) ) {
			continue;
		}
		$rels = $tag['rel'] ?? array();
		$rels = is_array( $rels ) ? $rels : array( $rels );
		if ( in_array( 'https://misskey-hub.net/ns/#_misskey_quote', $rels, true ) ) {
			axismundi_op_add_quote_candidate( $candidates, $tag['href'] ?? '', 'misskey' );
		}
	}
	return $candidates;
}

/** Replace the quote projection for one source Object from its preserved payload. */
function axismundi_op_index_quote_relations( array $row ) : bool {
	global $wpdb;
	$source = axismundi_op_relation_uri( $row['object_uri'] ?? '' );
	$payload = (array) ( $row['payload'] ?? array() );
	if ( '' === $source ) {
		return false;
	}
	$candidates    = axismundi_op_quote_candidates( $payload );
	$ambiguous     = count( $candidates ) > 1;
	$actor         = axismundi_op_relation_uri( $row['attributed_to_uri'] ?? '' );
	$authorization = axismundi_op_relation_uri( $payload['quoteAuthorization'] ?? '' );
	$table         = axismundi_op_object_relations_table();
	$now           = current_time( 'mysql', true );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic rebuildable projection replacement.
	$wpdb->query( 'START TRANSACTION' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact source replacement under a row-range lock.
	$existing_rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE relation_type = 'quote' AND source_object_uri_hash = %s AND source_object_uri = %s FOR UPDATE", hash( 'sha256', $source ), $source ), ARRAY_A );
	$existing      = array();
	foreach ( $existing_rows as $existing_row ) {
		$existing[ (string) $existing_row['target_object_uri'] ] = $existing_row;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rebuildable source projection replacement.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE relation_type = 'quote' AND source_object_uri_hash = %s AND source_object_uri = %s", hash( 'sha256', $source ), $source ) );

	foreach ( $candidates as $target => $evidence ) {
		$prior          = (array) ( $existing[ $target ] ?? array() );
		$prior_status   = (string) ( $prior['consent_status'] ?? '' );
		$verified       = ! $ambiguous && in_array( $prior_status, array( 'approved', 'rejected', 'revoked' ), true );
		$relation_auth  = $verified ? axismundi_op_relation_uri( $prior['authorization_uri'] ?? '' ) : $authorization;
		$consent_status = $ambiguous ? 'ambiguous' : ( $verified ? $prior_status : 'legacy_unverified' );
		$ok = $wpdb->insert(
			$table,
			array(
				'relation_type'             => 'quote',
				'source_object_uri'         => $source,
				'source_object_uri_hash'    => hash( 'sha256', $source ),
				'target_object_uri'         => $target,
				'target_object_uri_hash'    => hash( 'sha256', $target ),
				'source_actor_uri'          => '' !== $actor ? $actor : null,
				'source_actor_uri_hash'     => '' !== $actor ? hash( 'sha256', $actor ) : null,
				'evidence_type'             => $evidence,
				'consent_status'            => $consent_status,
				'authorization_uri'         => ! $ambiguous && '' !== $relation_auth ? $relation_auth : null,
				'authorization_uri_hash'    => ! $ambiguous && '' !== $relation_auth ? hash( 'sha256', $relation_auth ) : null,
				'created_at'                => $now,
				'updated_at'                => $now,
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom rebuildable projection.
		if ( false === $ok ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rollback custom projection transaction.
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- commit custom projection transaction.
	$wpdb->query( 'COMMIT' );
	return true;
}

/** Remove all rebuildable relations whose source observation was removed. */
function axismundi_op_delete_object_relations_for_source( string $source_uri ) : void {
	global $wpdb;
	$source = axismundi_op_relation_uri( $source_uri );
	if ( '' === $source ) {
		return;
	}
	$wpdb->delete(
		axismundi_op_object_relations_table(),
		array( 'source_object_uri_hash' => hash( 'sha256', $source ), 'source_object_uri' => $source ),
		array( '%s', '%s' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rebuildable projection cleanup.
}

/** Rebuild every remote quote relation from the preserved payload snapshots. */
function axismundi_op_rebuild_quote_relations() : array {
	global $wpdb;
	$report = array( 'scanned' => 0, 'indexed' => 0, 'failed' => 0 );
	$remote = axismundi_op_remote_objects_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit rebuild of a disposable projection.
	$rows = (array) $wpdb->get_results( "SELECT * FROM {$remote} ORDER BY id ASC", ARRAY_A );
	foreach ( $rows as $row ) {
		++$report['scanned'];
		$payload        = json_decode( (string) $row['payload_json'], true );
		$row['payload'] = is_array( $payload ) ? $payload : array();
		if ( axismundi_op_index_quote_relations( $row ) ) {
			++$report['indexed'];
		} else {
			++$report['failed'];
		}
	}
	return $report;
}

/** Remove relation projections whose source observation no longer exists. */
function axismundi_op_purge_orphan_object_relations() : int {
	global $wpdb;
	$relations = axismundi_op_object_relations_table();
	$objects   = axismundi_op_remote_objects_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rebuildable orphan cleanup with exact URI join.
	$deleted = $wpdb->query( "DELETE r FROM {$relations} r LEFT JOIN {$objects} o ON o.object_uri_hash = r.source_object_uri_hash AND o.object_uri = r.source_object_uri WHERE o.id IS NULL" );
	return false === $deleted ? 0 : (int) $deleted;
}

/** Return exact relation rows for one target. */
function axismundi_op_quote_relations_for_target( string $target_uri ) : array {
	global $wpdb;
	$target = axismundi_op_relation_uri( $target_uri );
	if ( '' === $target ) {
		return array();
	}
	$table = axismundi_op_object_relations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed projection lookup with exact URI verification.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE relation_type = 'quote' AND target_object_uri_hash = %s AND target_object_uri = %s", hash( 'sha256', $target ), $target ), ARRAY_A );
}

/** Count distinct public quote Objects, independent of consent state. */
function axismundi_op_get_quote_count( string $target_uri ) : int {
	$counted = array();
	foreach ( axismundi_op_quote_relations_for_target( $target_uri ) as $relation ) {
		if ( 'ambiguous' === (string) $relation['consent_status'] ) {
			continue;
		}
		$source = (string) $relation['source_object_uri'];
		$row    = function_exists( 'axismundi_op_remote_object_get' ) ? axismundi_op_remote_object_get( $source, false ) : null;
		if ( ! is_array( $row ) || ! function_exists( 'axismundi_op_remote_object_is_announceable' ) || ! axismundi_op_remote_object_is_announceable( $row ) ) {
			continue;
		}
		/** Filter whether moderation permits one otherwise-public quote source in public counts. */
		if ( ! (bool) apply_filters( 'axismundi_op_public_quote_source_allowed', true, $row, $relation ) ) {
			continue;
		}
		$counted[ hash( 'sha256', $source ) . ':' . $source ] = true;
	}
	return count( $counted );
}

/**
 * Record a verified authorization decision for one exact quote relation.
 *
 * Merely declaring `quoteAuthorization` in an Object never calls this API.
 */
function axismundi_op_verify_quote_consent( string $source_uri, string $target_uri, string $authorization_uri, string $status = 'approved' ) : bool {
	global $wpdb;
	if ( ! in_array( $status, array( 'approved', 'rejected', 'revoked' ), true ) ) {
		return false;
	}
	$source        = axismundi_op_relation_uri( $source_uri );
	$target        = axismundi_op_relation_uri( $target_uri );
	$authorization = axismundi_op_relation_uri( $authorization_uri );
	if ( '' === $source || '' === $target || '' === $authorization ) {
		return false;
	}
	$table = axismundi_op_object_relations_table();
	$sql = $wpdb->prepare(
			"UPDATE {$table} SET consent_status = %s, authorization_uri = %s, authorization_uri_hash = %s, updated_at = %s WHERE relation_type = 'quote' AND source_object_uri_hash = %s AND source_object_uri = %s AND target_object_uri_hash = %s AND target_object_uri = %s AND consent_status <> 'ambiguous'",
			$status,
			$authorization,
			hash( 'sha256', $authorization ),
			current_time( 'mysql', true ),
			hash( 'sha256', $source ),
			$source,
			hash( 'sha256', $target ),
			$target
		);
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact verified projection update; SQL was prepared above.
	$updated = $wpdb->query( $sql );
	return 1 === $updated;
}

/** Resolve a remote authorization only after its quote mapping was explicitly verified. */
function axismundi_op_quote_relation_for_authorization( string $authorization_uri ) : ?array {
	global $wpdb;
	$authorization = axismundi_op_relation_uri( $authorization_uri );
	if ( '' === $authorization ) {
		return null;
	}
	$table = axismundi_op_object_relations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed verified authorization lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE authorization_uri_hash = %s AND consent_status IN ('approved','revoked') ORDER BY id DESC LIMIT 1", hash( 'sha256', $authorization ) ), ARRAY_A );
	return is_array( $row ) && hash_equals( (string) $row['authorization_uri'], $authorization ) ? $row : null;
}

/** Apply a verified inbound Delete to its already-verified quote mapping. */
function axismundi_op_observe_quote_authorization_delete( $activity ) : void {
	global $wpdb;
	if ( ! $activity instanceof Axismundi_Activity || 'Delete' !== $activity->get_type() || 'inbound' !== $activity->get_direction() ) {
		return;
	}
	$authorization = (string) $activity->get_object_uri();
	$relation      = axismundi_op_quote_relation_for_authorization( $authorization );
	if ( ! is_array( $relation ) || 'approved' !== (string) $relation['consent_status'] ) {
		return;
	}
	$table = axismundi_op_object_relations_table();
	// The authorization mapping was verified earlier. The Delete itself reached this hook
	// only after the Bridge's signature-verified Inbox committed it to Activities.
	$updated = $wpdb->update(
		$table,
		array( 'consent_status' => 'revoked', 'updated_at' => current_time( 'mysql', true ) ),
		array( 'id' => (int) $relation['id'], 'consent_status' => 'approved' ),
		array( '%s', '%s' ),
		array( '%d', '%s' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- verified projection lifecycle update.
	if ( 1 === (int) $updated ) {
		/**
		 * Fires after a verified remote authorization Delete revokes its mapped quote relation.
		 *
		 * This is an observation seam, not a delivery instruction. Forwarding the remote
		 * actor's Activity requires a transport contract that preserves actor/signature
		 * semantics; callers must not re-sign it as a different local actor.
		 *
		 * @since 0.0.26
		 */
		do_action( 'axismundi_op_quote_authorization_deleted', $relation, $activity );
	}
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_op_observe_quote_authorization_delete', 20 );
