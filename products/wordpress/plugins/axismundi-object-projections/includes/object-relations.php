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
			position int(10) unsigned DEFAULT NULL,
			link_id text DEFAULT NULL,
			link_rel text DEFAULT NULL,
			link_name text DEFAULT NULL,
			link_media_type varchar(255) DEFAULT NULL,
			authorization_uri text DEFAULT NULL,
			authorization_uri_hash char(64) DEFAULT NULL,
			first_seen_at datetime DEFAULT NULL,
			last_seen_at datetime DEFAULT NULL,
			last_confirmed_at datetime DEFAULT NULL,
			not_observed_since datetime DEFAULT NULL,
			evidence_source varchar(32) DEFAULT NULL,
			observation_state varchar(24) NOT NULL DEFAULT 'observed',
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
		&& in_array( 'position', $columns, true )
		&& in_array( 'consent_status', $columns, true )
		&& in_array( 'first_seen_at', $columns, true )
		&& in_array( 'last_seen_at', $columns, true )
		&& in_array( 'last_confirmed_at', $columns, true )
		&& in_array( 'not_observed_since', $columns, true )
		&& in_array( 'evidence_source', $columns, true )
		&& in_array( 'observation_state', $columns, true )
		&& count( $identity ) >= 3
		&& 0 === (int) $identity[0]['Non_unique']
		&& 'InnoDB' === $engine;
}

/**
 * Legacy scalar relation-URI reducer without performing a network request.
 *
 * It deliberately keeps its long-standing id-before-href and list-rejection
 * semantics for existing callers. List-aware AS2 relations must use
 * axismundi_as2_normalize_members() directly.
 */
function axismundi_op_relation_uri( $value ) : string {
	if ( is_array( $value ) ) {
		$value = $value['id'] ?? $value['href'] ?? '';
	}
	if ( ! function_exists( 'axismundi_as2_normalize_uri' ) ) {
		return '';
	}
	$uri = axismundi_as2_normalize_uri( $value );
	return is_array( $uri ) && ! empty( $uri['resolvable'] ) ? (string) $uri['uri'] : '';
}

/**
 * Reconcile preserved `inReplyTo` relations for one Object in document order.
 *
 * Only a complete Object representation may turn a previously observed relation into
 * `not_observed`. Embedded and inbound partial payloads can confirm the members they
 * carry, but cannot make an absence claim about members they do not carry.
 */
function axismundi_op_replace_in_reply_to_relations( string $source_uri, $value, bool $complete = false, string $evidence_source = 'as2' ) : bool {
	global $wpdb;
	$source = axismundi_op_relation_uri( $source_uri );
	if ( '' === $source || ! function_exists( 'axismundi_as2_normalize_members' ) ) {
		return false;
	}
	$members = axismundi_as2_normalize_members( $value );
	if ( empty( $members ) && ! axismundi_as2_members_explicitly_empty( $value ) ) {
		return false;
	}
	$table = axismundi_op_object_relations_table();
	$now = current_time( 'mysql', true );
	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic relation reconciliation.
	$existing_rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE relation_type = 'in_reply_to' AND source_object_uri_hash = %s AND source_object_uri = %s FOR UPDATE", hash( 'sha256', $source ), $source ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact source lock for reconciliation.
	$existing = array();
	foreach ( $existing_rows as $row ) {
		$existing[ (string) $row['target_object_uri'] ] = $row;
	}
	$observed = array();
	foreach ( $members as $position => $member ) {
		$target = (string) $member['uri'];
		$observed[ $target ] = true;
		$data = array( 'position' => $position, 'link_id' => $member['link_id'], 'link_rel' => is_array( $member['link_rel'] ) ? wp_json_encode( $member['link_rel'] ) : $member['link_rel'], 'link_name' => $member['link_name'], 'link_media_type' => $member['link_media_type'], 'last_seen_at' => $now, 'evidence_source' => $evidence_source, 'observation_state' => 'observed', 'not_observed_since' => null, 'updated_at' => $now );
		if ( $complete ) {
			$data['last_confirmed_at'] = $now;
		}
		if ( isset( $existing[ $target ] ) ) {
			$ok = false !== $wpdb->update( $table, $data, array( 'id' => (int) $existing[ $target ]['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact relation reconciliation update.
		} else {
			$data = array_merge( array( 'relation_type' => 'in_reply_to', 'source_object_uri' => $source, 'source_object_uri_hash' => hash( 'sha256', $source ), 'target_object_uri' => $target, 'target_object_uri_hash' => hash( 'sha256', $target ), 'evidence_type' => 'as2', 'consent_status' => 'not_applicable', 'first_seen_at' => $now, 'last_confirmed_at' => $complete ? $now : null, 'created_at' => $now ), $data );
			$ok = false !== $wpdb->insert( $table, $data, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit strings prevent WordPress core's link_id integer field-type inference from corrupting Link identifiers.
		}
		if ( false === $ok ) { $wpdb->query( 'ROLLBACK' ); return false; } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- transaction rollback.
	}
	if ( $complete ) {
		foreach ( $existing as $target => $row ) {
			if ( isset( $observed[ $target ] ) ) {
				continue;
			}
			$ok = false !== $wpdb->update( $table, array( 'observation_state' => 'not_observed', 'not_observed_since' => null === $row['not_observed_since'] ? $now : $row['not_observed_since'], 'updated_at' => $now ), array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- retain stale graph evidence rather than deleting it.
			if ( false === $ok ) { $wpdb->query( 'ROLLBACK' ); return false; } // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- transaction rollback.
		}
	}
	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- transaction commit.
	return true;
}

/** First preserved reply parent for the derived single-parent thread view. */
function axismundi_op_primary_in_reply_to_relation( string $source_uri ) : ?string {
	global $wpdb;
	$source = axismundi_op_relation_uri( $source_uri );
	if ( '' === $source ) { return null; }
	$table = axismundi_op_object_relations_table();
	$value = $wpdb->get_var( $wpdb->prepare( "SELECT target_object_uri FROM {$table} WHERE relation_type = 'in_reply_to' AND observation_state = 'observed' AND source_object_uri_hash = %s AND source_object_uri = %s ORDER BY position ASC, id ASC LIMIT 1", hash( 'sha256', $source ), $source ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ordered derived-view lookup.
	return is_string( $value ) && '' !== $value ? $value : null;
}

/** All observed reply parents in source order, for structured Object reads. */
function axismundi_op_read_reply_parent_uris( string $source_uri ) : array {
	global $wpdb;
	$table = axismundi_op_object_relations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ordered exact relation lookup.
	return array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT target_object_uri FROM {$table} WHERE relation_type = 'in_reply_to' AND observation_state = 'observed' AND source_object_uri_hash = %s AND source_object_uri = %s ORDER BY position ASC, id ASC", hash( 'sha256', $source_uri ), $source_uri ) ) );
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
	$complete      = ! empty( $row['_relation_observation_complete'] );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic rebuildable projection replacement.
	$wpdb->query( 'START TRANSACTION' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact source replacement under a row-range lock.
	$existing_rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE relation_type = 'quote' AND source_object_uri_hash = %s AND source_object_uri = %s FOR UPDATE", hash( 'sha256', $source ), $source ), ARRAY_A );
	$existing      = array();
	foreach ( $existing_rows as $existing_row ) {
		$existing[ (string) $existing_row['target_object_uri'] ] = $existing_row;
	}
	$observed = array();
	foreach ( $candidates as $target => $evidence ) {
		$observed[ $target ] = true;
		$prior          = (array) ( $existing[ $target ] ?? array() );
		$prior_status   = (string) ( $prior['consent_status'] ?? '' );
		$verified       = ! $ambiguous && in_array( $prior_status, array( 'approved', 'rejected', 'revoked' ), true );
		$relation_auth  = $verified ? axismundi_op_relation_uri( $prior['authorization_uri'] ?? '' ) : $authorization;
		$consent_status = $ambiguous ? 'ambiguous' : ( $verified ? $prior_status : 'legacy_unverified' );
		/*
		 * FEP-044f states two ways a recipient may treat a quote as approved: a dereferenced
		 * QuoteAuthorization, or the quote and the quoted post sharing an author. Only the
		 * first was implemented, so somebody quoting their own post read as unapproved here.
		 *
		 * Both authors have to be known for this. An unknown target author is left alone:
		 * assuming they are the same actor would manufacture the consent this whole path
		 * exists to verify.
		 */
		if ( ! $ambiguous && ! $verified && '' !== $actor && axismundi_op_quote_shares_author( $actor, (string) $target ) ) {
			$consent_status = 'self';
		}
		$data = array(
			'source_actor_uri'       => '' !== $actor ? $actor : null,
			'source_actor_uri_hash'  => '' !== $actor ? hash( 'sha256', $actor ) : null,
			'evidence_type'          => $evidence,
			'consent_status'         => $consent_status,
			'authorization_uri'      => ! $ambiguous && '' !== $relation_auth ? $relation_auth : null,
			'authorization_uri_hash' => ! $ambiguous && '' !== $relation_auth ? hash( 'sha256', $relation_auth ) : null,
			'last_seen_at'           => $now,
			'evidence_source'        => $complete ? 'remote_fetch' : 'inbound_payload',
			'observation_state'      => 'observed',
			'not_observed_since'     => null,
			'updated_at'             => $now,
		);
		if ( $complete ) {
			$data['last_confirmed_at'] = $now;
		}
		if ( isset( $existing[ $target ] ) ) {
			$ok = false !== $wpdb->update( $table, $data, array( 'id' => (int) $existing[ $target ]['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- preserve quote relation history while refreshing its evidence.
		} else {
			$data = array_merge(
				array(
					'relation_type'          => 'quote',
					'source_object_uri'      => $source,
					'source_object_uri_hash' => hash( 'sha256', $source ),
					'target_object_uri'      => $target,
					'target_object_uri_hash' => hash( 'sha256', $target ),
					'first_seen_at'          => $now,
					'last_confirmed_at'      => $complete ? $now : null,
					'created_at'             => $now,
				),
				$data
			);
			$ok = false !== $wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- new quote relation observation.
		}
		if ( false === $ok ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rollback custom projection transaction.
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
	}
	if ( $complete ) {
		foreach ( $existing as $target => $prior ) {
			if ( isset( $observed[ $target ] ) ) {
				continue;
			}
			$ok = false !== $wpdb->update( $table, array( 'observation_state' => 'not_observed', 'not_observed_since' => null === $prior['not_observed_since'] ? $now : $prior['not_observed_since'], 'updated_at' => $now ), array( 'id' => (int) $prior['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- preserve withdrawn quote evidence rather than deleting it.
			if ( false === $ok ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rollback custom projection transaction.
				return false;
			}
		}
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- commit custom projection transaction.
	$wpdb->query( 'COMMIT' );
	return true;
}

/**
 * Mark source relations stale after discarding a cache observation.
 *
 * Cache eviction must not erase what was once observed: a later fetch needs those rows
 * to distinguish a stable graph from a changed one. Local source deletion has a separate
 * lifecycle and is the only future path permitted to remove relation history.
 *
 * It was called `..._delete_object_relations_for_source` for as long as it deleted. The name
 * outlived the behaviour by one commit and misled a reader inside that window -- a check aimed
 * at this path passed because the call it broke no longer removed anything. Renamed without a
 * compatibility alias on purpose: one caller, inside this plugin, in the same unreleased slice.
 */
function axismundi_op_mark_object_relations_stale( string $source_uri ) : void {
	global $wpdb;
	$source = axismundi_op_relation_uri( $source_uri );
	if ( '' === $source ) {
		return;
	}
	$now = current_time( 'mysql', true );
	$wpdb->query( $wpdb->prepare( "UPDATE " . axismundi_op_object_relations_table() . " SET observation_state = 'stale', updated_at = %s WHERE source_object_uri_hash = %s AND source_object_uri = %s AND observation_state = 'observed'", $now, hash( 'sha256', $source ), $source ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- preserve relation history when a disposable cache row leaves.
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

/**
 * Historical compatibility hook for cache maintenance.
 *
 * Relations no longer become orphaned when their cache row expires, so there is no cleanup
 * query here. Keeping the function avoids a version-skew fatal while callers are upgraded.
 */
function axismundi_op_purge_orphan_object_relations() : int {
	return 0;
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
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE relation_type = 'quote' AND observation_state = 'observed' AND target_object_uri_hash = %s AND target_object_uri = %s", hash( 'sha256', $target ), $target ), ARRAY_A );
}

/**
 * Exact, unambiguous quote relation for one source Object -- URI and its indexed
 * consent state -- or null when there is none or the candidate set is ambiguous.
 *
 * @return array{target_uri:string,consent_status:string}|null
 */
function axismundi_op_quote_relation_for_source( string $source_uri ) : ?array {
	global $wpdb;
	$source = axismundi_op_relation_uri( $source_uri );
	if ( '' === $source ) {
		return null;
	}
	$table = axismundi_op_object_relations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed exact source lookup.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT target_object_uri, consent_status FROM {$table} WHERE relation_type = 'quote' AND observation_state = 'observed' AND source_object_uri_hash = %s AND source_object_uri = %s ORDER BY id ASC LIMIT 2", hash( 'sha256', $source ), $source ), ARRAY_A );
	if ( 1 !== count( $rows ) || 'ambiguous' === (string) $rows[0]['consent_status'] ) {
		return null;
	}
	return array(
		'target_uri'     => (string) $rows[0]['target_object_uri'],
		'consent_status' => (string) $rows[0]['consent_status'],
	);
}

/** Exact, unambiguous quote target for one source Object, or ''. */
function axismundi_op_quote_target_for_source( string $source_uri ) : string {
	$relation = axismundi_op_quote_relation_for_source( $source_uri );
	return null !== $relation ? $relation['target_uri'] : '';
}

/** Count distinct public quote Objects, independent of consent state. */
function axismundi_op_get_quote_count( string $target_uri ) : int {
	$counted = array();
	foreach ( axismundi_op_quote_relations_for_target( $target_uri ) as $relation ) {
		if ( 'ambiguous' === (string) $relation['consent_status'] ) {
			continue;
		}
		$source = (string) $relation['source_object_uri'];
		$row    = function_exists( 'axismundi_op_get_remote_object' ) ? axismundi_op_get_remote_object( $source, false ) : null;
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
 * Whether one Actor is demonstrably the author of the Object being quoted.
 *
 * Answered only from what this site already holds: a local Object's attribution, or a
 * cached remote Object's. An address this site has never seen is not an argument that the
 * authors match, so it returns false and the quote stays unapproved.
 *
 * @param string $actor_uri  Author of the quoting Object.
 * @param string $target_uri Object being quoted.
 * @return bool
 */
function axismundi_op_quote_shares_author( string $actor_uri, string $target_uri ) : bool {
	$actor_uri  = axismundi_op_relation_uri( $actor_uri );
	$target_uri = axismundi_op_relation_uri( $target_uri );
	if ( '' === $actor_uri || '' === $target_uri ) {
		return false;
	}
	$remote = axismundi_op_get_remote_object( $target_uri );
	if ( is_array( $remote ) ) {
		$attributed = axismundi_op_relation_uri( (string) ( $remote['attributed_to_uri'] ?? '' ) );
		return '' !== $attributed && hash_equals( $attributed, $actor_uri );
	}
	$source = function_exists( 'axismundi_op_local_source_from_object_uri' ) ? axismundi_op_local_source_from_object_uri( $target_uri ) : null;
	if ( ! $source instanceof WP_Post || ! function_exists( 'axismundi_op_post_actor_uri' ) ) {
		return false;
	}
	$attributed = axismundi_op_relation_uri( axismundi_op_post_actor_uri( $source ) );
	return '' !== $attributed && hash_equals( $attributed, $actor_uri );
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
