<?php
/**
 * Review state transitions.
 *
 * Separate from the admin screen that drives them, because these are the rules and
 * that is a form. Nothing here touches the network: `Queue verification` raises a
 * priority flag for the worker rather than fetching, so an admin page load can never
 * block on a third-party host.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

/** Capability required to decide what this instance displays. */
const AXISMUNDI_EMOJI_CAPABILITY = 'manage_options';

/**
 * The review bucket a row belongs to.
 *
 * Four, not two, because `pending` covers two situations that permit different
 * actions. An unverified row has nothing an operator could sensibly judge — no source
 * URL, no licence, no dimensions — so offering Approve there would be asking for a
 * decision about an emoji nobody has seen. It gets `Queue verification` instead.
 *
 * @param array<string,mixed> $row Registry row.
 * @return string unverified|pending|changed|approved|rejected
 */
function axismundi_emoji_review_bucket( array $row ) : string {
	$status = (string) ( $row['review_status'] ?? 'pending' );
	$reason = (string) ( $row['review_reason'] ?? '' );
	if ( 'approved' === $status || 'rejected' === $status ) {
		return $status;
	}
	if ( 'unverified' === $reason || '' === (string) ( $row['source_url'] ?? '' ) ) {
		return 'unverified';
	}
	return 'changed' === $reason ? 'changed' : 'pending';
}

/**
 * Commands offered for a row, keyed by action slug.
 *
 * @param array<string,mixed> $row Registry row.
 * @return array<string,string> action => label
 */
function axismundi_emoji_review_commands( array $row ) : array {
	switch ( axismundi_emoji_review_bucket( $row ) ) {
		case 'unverified':
			$commands = array( 'reject' => __( 'Reject', 'axismundi-emoji' ) );
			if ( '' !== (string) ( $row['verification_uri'] ?? '' ) ) {
				// A row the worker gave up on is offered a retry rather than the same
				// label, because "try again" and "try for the first time" are different
				// decisions and only one of them says the operator thinks the host is back.
				$commands = array(
					'queue_verification' => 'verify_failed' === (string) ( $row['review_reason'] ?? '' )
						? __( 'Retry verification', 'axismundi-emoji' )
						: __( 'Queue verification', 'axismundi-emoji' ),
				) + $commands;
			}
			return $commands;
		case 'changed':
			return array(
				'approve' => __( 'Approve replacement', 'axismundi-emoji' ),
				'reject'  => __( 'Reject', 'axismundi-emoji' ),
			);
		case 'approved':
			return array( 'revoke' => __( 'Revoke', 'axismundi-emoji' ) );
		case 'rejected':
			return array( 'requeue' => __( 'Move back to review', 'axismundi-emoji' ) );
		default:
			return array(
				'approve' => __( 'Approve', 'axismundi-emoji' ),
				'reject'  => __( 'Reject', 'axismundi-emoji' ),
			);
	}
}

/**
 * Apply one review decision.
 *
 * Approval is refused for a row the operator cannot actually have judged. Verification
 * is a flag, never a fetch: doing the HTTP here would make an admin click hang on
 * somebody else's server and would put network access on a request path that the rest
 * of this plugin deliberately keeps clear of it.
 *
 * @param int    $emoji_id Registry row id.
 * @param string $action   approve|reject|revoke|requeue|queue_verification.
 * @param int    $user_id  Acting user.
 * @return true|WP_Error
 */
function axismundi_emoji_review_apply( int $emoji_id, string $action, int $user_id ) {
	global $wpdb;
	if ( ! axismundi_emoji_ready() ) {
		return new WP_Error( 'ax_emoji_not_ready', __( 'The emoji registry is unavailable.', 'axismundi-emoji' ) );
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $emoji_id ), ARRAY_A );
	if ( ! is_array( $row ) ) {
		return new WP_Error( 'ax_emoji_missing', __( 'That emoji is no longer in the registry.', 'axismundi-emoji' ) );
	}
	if ( ! array_key_exists( $action, axismundi_emoji_review_commands( $row ) ) ) {
		return new WP_Error( 'ax_emoji_action', __( 'That action is not available for this emoji.', 'axismundi-emoji' ) );
	}

	$now    = current_time( 'mysql', true );
	$fields = array();

	switch ( $action ) {
		case 'approve':
			$fields['reviewed_at']   = $now;
			$fields['reviewed_by']   = $user_id;
			$fields['review_status'] = 'approved';
			$fields['review_reason'] = null;
			$fields['approval_batch'] = null;
			// Bytes are the worker's job; approval only permits it.
			break;
		case 'reject':
			$fields['reviewed_at']   = $now;
			$fields['reviewed_by']   = $user_id;
			$fields['review_status'] = 'rejected';
			$fields['review_reason'] = null;
			$fields['cached_path']   = null;
			$fields['static_path']   = null;
			$fields['approval_batch'] = null;
			break;
		case 'revoke':
			$fields['reviewed_at']   = $now;
			$fields['reviewed_by']   = $user_id;
			// Back to the queue rather than to `rejected`: revoking says "stop showing
			// this", which is not the same as "never show this".
			$fields['review_status'] = 'pending';
			$fields['review_reason'] = 'revoked';
			$fields['cached_path']   = null;
			$fields['static_path']   = null;
			$fields['approval_batch'] = null;
			break;
		case 'requeue':
			$fields['reviewed_at']   = $now;
			$fields['reviewed_by']   = $user_id;
			$fields['review_status'] = 'pending';
			$fields['review_reason'] = null;
			$fields['approval_batch'] = null;
			break;
		case 'queue_verification':
			// Clearing both counters is the whole content of the decision: the operator is
			// asserting the host is worth trying again, which the backoff cannot know.
			$fields['review_reason']   = 'verify_queued';
			$fields['failure_count']   = 0;
			$fields['next_attempt_at'] = null;
			break;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	$done = $wpdb->update( $table, $fields, array( 'id' => $emoji_id ) );
	if ( false === $done ) {
		return new WP_Error( 'ax_emoji_write', __( 'The decision could not be saved.', 'axismundi-emoji' ) );
	}
	if ( 'queue_verification' === $action ) {
		/** Fires after an administrator has explicitly queued an authority-side fetch. */
		do_action( 'axismundi_emoji_verification_queued' );
	}
	if ( 'approve' === $action ) {
		/**
		 * Fires once an emoji is permitted to be cached.
		 *
		 * Approval only grants permission; the bytes are fetched by the cron worker, so
		 * the click that grants it never waits on a download.
		 */
		do_action( 'axismundi_emoji_approved', $emoji_id );
	}
	return true;
}

/**
 * Rows awaiting a decision, newest sighting first.
 *
 * @param string $bucket unverified|pending|changed|approved|rejected|all.
 * @param int    $limit      Maximum rows.
 * @param string $authority  Optional authority filter.
 * @return array<int,array<string,mixed>>
 */
function axismundi_emoji_review_queue( string $bucket = 'all', int $limit = 100, string $authority = '' ) : array {
	global $wpdb;
	if ( ! axismundi_emoji_ready() ) {
		return array();
	}
	$table = axismundi_emoji_table();
	$limit = max( 1, min( 500, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	$where = "scope = 'remote'";
	if ( 'unverified' === $bucket ) {
		$where .= " AND review_status = 'pending' AND (review_reason = 'unverified' OR review_reason = 'verify_queued' OR source_url IS NULL OR source_url = '')";
	} elseif ( 'changed' === $bucket ) {
		$where .= " AND review_status = 'pending' AND review_reason = 'changed'";
	} elseif ( 'pending' === $bucket ) {
		$where .= " AND review_status = 'pending' AND (review_reason IS NULL OR review_reason = '') AND source_url IS NOT NULL AND source_url <> ''";
	} elseif ( in_array( $bucket, array( 'approved', 'rejected' ), true ) ) {
		$where .= $wpdb->prepare( ' AND review_status = %s', $bucket );
	}
	if ( '' !== $authority ) {
		$where .= $wpdb->prepare( ' AND emoji_authority = %s', strtolower( $authority ) );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned static queue clauses; limit prepared.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY last_seen_at DESC, id DESC LIMIT %d", $limit ), ARRAY_A );
}

/**
 * Count registry entries for one authority, for optional host-ledger integrations.
 *
 * @param string $authority Emoji authority host.
 * @return int
 */
function axismundi_emoji_count_authority( string $authority ) : int {
	global $wpdb;
	$authority = strtolower( trim( $authority ) );
	if ( '' === $authority || ! axismundi_emoji_ready() ) {
		return 0;
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE scope = 'remote' AND emoji_authority = %s", $authority ) );
}

/**
 * Every authority this site has observed, with its standing decision and its counts.
 *
 * @return array<int,array<string,mixed>>
 */
function axismundi_emoji_authority_summary() : array {
	global $wpdb;
	if ( ! axismundi_emoji_ready() ) {
		return array();
	}
	$emojis      = axismundi_emoji_table();
	$authorities = axismundi_emoji_authorities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned tables.
	$rows = (array) $wpdb->get_results(
		"SELECT e.emoji_authority,
				COUNT(*) AS total,
				SUM(CASE WHEN e.review_status = 'pending'  THEN 1 ELSE 0 END) AS pending,
				SUM(CASE WHEN e.review_status = 'approved' THEN 1 ELSE 0 END) AS approved,
				SUM(CASE WHEN e.review_status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
				SUM(CASE WHEN e.license_state = 'restricted' THEN 1 ELSE 0 END) AS restricted,
				COALESCE(a.review_default, 'pending') AS review_default,
				COALESCE(a.fallback_priority, 0) AS fallback_priority
		 FROM {$emojis} e
		 LEFT JOIN {$authorities} a ON a.emoji_authority = e.emoji_authority
		 WHERE e.scope = 'remote'
		 GROUP BY e.emoji_authority, a.review_default, a.fallback_priority
		 ORDER BY pending DESC, e.emoji_authority ASC",
		ARRAY_A
	);
	return $rows;
}

/**
 * Record what should happen to emoji this authority declares from now on.
 *
 * @param string $authority Declaring authority.
 * @param string $default   pending|approved|rejected.
 * @param int    $user_id   Acting user.
 * @param int|null $fallback_priority Display-substitution priority; null preserves it.
 * @return bool
 */
function axismundi_emoji_set_authority_default( string $authority, string $default, int $user_id, ?int $fallback_priority = null ) : bool {
	global $wpdb;
	$authority = strtolower( trim( $authority ) );
	$fallback_priority = null === $fallback_priority ? null : max( 0, min( 999, $fallback_priority ) );
	if ( '' === $authority || ! in_array( $default, AXISMUNDI_EMOJI_REVIEW_STATES, true ) || ! axismundi_emoji_ready() ) {
		return false;
	}
	$table = axismundi_emoji_authorities_table();
	/*
	 * Upsert rather than REPLACE: REPLACE deletes the row and inserts a new one, which would
	 * silently reset `rejected_streak` — the accumulated evidence about this authority — every
	 * time an operator changed the standing decision. Only the three columns being decided here
	 * may move.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; values prepared.
	$sql = null === $fallback_priority
		? "INSERT INTO {$table} (emoji_authority, review_default, reviewed_at, reviewed_by) VALUES (%s, %s, %s, %d) ON DUPLICATE KEY UPDATE review_default = VALUES(review_default), reviewed_at = VALUES(reviewed_at), reviewed_by = VALUES(reviewed_by)"
		: "INSERT INTO {$table} (emoji_authority, review_default, fallback_priority, reviewed_at, reviewed_by) VALUES (%s, %s, %d, %s, %d) ON DUPLICATE KEY UPDATE review_default = VALUES(review_default), fallback_priority = VALUES(fallback_priority), reviewed_at = VALUES(reviewed_at), reviewed_by = VALUES(reviewed_by)";
	$args = null === $fallback_priority
		? array( $authority, $default, current_time( 'mysql', true ), $user_id )
		: array( $authority, $default, $fallback_priority, current_time( 'mysql', true ), $user_id );
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- each branch above is a fixed plugin-owned statement; values are prepared below.
	return false !== $wpdb->query( $wpdb->prepare( $sql, ...$args ) );
}

/**
 * Apply an authority's standing decision to the emoji already waiting from it.
 *
 * Setting a default governs what arrives next; it deliberately does not reach backwards,
 * because a stored decision and a retroactive sweep are different acts and conflating
 * them would let one click approve a queue the operator has not seen. This is that
 * sweep, offered separately.
 *
 * Two kinds of row are held back. An unverified one has no source URL or licence yet, so
 * there is nothing an authority-level judgement could be about; and one carrying an
 * explicit prohibition is exactly the case the licence axis exists to surface — "allowed
 * once approved" means a person approved it, not that a blanket setting swept past it.
 *
 * @param string $authority Declaring authority.
 * @param int    $user_id   Acting user.
 * @return int Number of emoji approved.
 */
function axismundi_emoji_approve_pending_for_authority( string $authority, int $user_id ) : int {
	global $wpdb;
	$authority = strtolower( trim( $authority ) );
	if ( '' === $authority || ! axismundi_emoji_ready() ) {
		return 0;
	}
	$table = axismundi_emoji_table();
	$batch = wp_generate_uuid4();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; values prepared.
	$approved = (int) $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table}
			 SET review_status = 'approved', review_reason = NULL, reviewed_at = %s, reviewed_by = %d, approval_batch = %s
			 WHERE emoji_authority = %s
			   AND scope = 'remote'
			   AND review_status = 'pending'
			   AND license_state <> 'restricted'
			   AND source_url IS NOT NULL AND source_url <> ''
			   AND ( review_reason IS NULL OR review_reason NOT IN ('unverified', 'verify_queued', 'verify_failed') )",
			current_time( 'mysql', true ),
			$user_id,
			$batch,
			$authority
		)
	);
	if ( $approved > 0 ) {
		/** Fires when emoji become eligible for caching. */
		do_action( 'axismundi_emoji_approved', 0 );
	}
	return $approved;
}

/**
 * The newest still-reversible bulk approval for one authority.
 *
 * @param string $authority Declaring authority.
 * @return array{batch:string,count:int}|null
 */
function axismundi_emoji_latest_approval_batch( string $authority ) : ?array {
	global $wpdb;
	$authority = strtolower( trim( $authority ) );
	if ( '' === $authority || ! axismundi_emoji_ready() ) {
		return null;
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT approval_batch AS batch, COUNT(*) AS count FROM {$table} WHERE emoji_authority = %s AND scope = 'remote' AND review_status = 'approved' AND approval_batch IS NOT NULL AND approval_batch <> '' GROUP BY approval_batch ORDER BY MAX(reviewed_at) DESC LIMIT 1",
			$authority
		),
		ARRAY_A
	);
	return is_array( $row ) && '' !== (string) ( $row['batch'] ?? '' )
		? array( 'batch' => (string) $row['batch'], 'count' => (int) $row['count'] )
		: null;
}

/**
 * Approved remote emoji which do not belong to a reversible bulk-approval batch.
 *
 * This includes decisions made before batch recording was introduced and later
 * individual approvals. Neither is safe to undo as a group.
 *
 * @param string $authority Declaring authority.
 * @return int
 */
function axismundi_emoji_unbatched_approved_count( string $authority ) : int {
	global $wpdb;
	$authority = strtolower( trim( $authority ) );
	if ( '' === $authority || ! axismundi_emoji_ready() ) {
		return 0;
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE emoji_authority = %s AND scope = 'remote' AND review_status = 'approved' AND (approval_batch IS NULL OR approval_batch = '')",
			$authority
		)
	);
}

/**
 * Undo exactly one recorded bulk approval and release its cache claims.
 *
 * A later individual decision clears `approval_batch`, so this never reaches across a
 * human's subsequent per-emoji decision. Shared blobs are retained by
 * axismundi_emoji_maybe_delete_file(); only a now-unclaimed file can disappear.
 *
 * @param string $authority Declaring authority.
 * @param string $batch     Batch identifier returned by axismundi_emoji_latest_approval_batch().
 * @param int    $user_id   Acting user.
 * @return int Number of rows reverted.
 */
function axismundi_emoji_undo_approval_batch( string $authority, string $batch, int $user_id ) : int {
	global $wpdb;
	$authority = strtolower( trim( $authority ) );
	$batch     = strtolower( trim( $batch ) );
	if ( '' === $authority || ! preg_match( '/^[a-f0-9-]{36}$/', $batch ) || ! axismundi_emoji_ready() ) {
		return 0;
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; values prepared.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, cached_path, content_hash FROM {$table} WHERE emoji_authority = %s AND scope = 'remote' AND review_status = 'approved' AND approval_batch = %s", $authority, $batch ), ARRAY_A );
	if ( empty( $rows ) ) {
		return 0;
	}
	$ids = array_map( static fn( array $row ) : int => (int) $row['id'], $rows );
	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$args = array_merge( array( current_time( 'mysql', true ), $user_id ), $ids );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; fixed placeholder list is generated from integer ids.
	$updated = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET review_status = 'pending', review_reason = NULL, reviewed_at = %s, reviewed_by = %d, cached_path = NULL, static_path = NULL, approval_batch = NULL WHERE id IN ({$placeholders})", ...$args ) );
	if ( $updated < 1 ) {
		return 0;
	}
	if ( function_exists( 'axismundi_emoji_maybe_delete_file' ) ) {
		foreach ( $rows as $row ) {
			$path = (string) ( $row['cached_path'] ?? '' );
			$hash = (string) ( $row['content_hash'] ?? '' );
			if ( '' !== $path && '' !== $hash ) {
				axismundi_emoji_maybe_delete_file( trailingslashit( axismundi_emoji_cache_dir() ) . ltrim( $path, '/' ), $hash );
			}
		}
	}
	return $updated;
}

/**
 * How many references point at one emoji.
 *
 * The only usage signal available before anything is fetched, and the one thing that
 * distinguishes an emoji seen once from one that is everywhere.
 *
 * @param int $emoji_id Registry row id.
 * @return int
 */
function axismundi_emoji_reference_count( int $emoji_id ) : int {
	global $wpdb;
	if ( $emoji_id <= 0 || ! axismundi_emoji_ready() ) {
		return 0;
	}
	$table = axismundi_emoji_references_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE emoji_id = %d", $emoji_id ) );
}
