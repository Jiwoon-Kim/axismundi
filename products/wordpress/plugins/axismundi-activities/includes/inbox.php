<?php
/** Actor-scoped receipt membership, independent of transport and publication state. @package AxismundiActivities */
defined( 'ABSPATH' ) || exit;

function axismundi_act_inbox_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_activity_inbox';
}

/** Additive schema; the ledger's existing identifiers and payloads stay unchanged. */
function axismundi_act_install_inbox() : bool {
	global $wpdb;
	$table = axismundi_act_inbox_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		actor_identity_id bigint(20) unsigned NOT NULL,
		activity_id bigint(20) unsigned NOT NULL,
		received_at datetime NOT NULL,
		received_via varchar(16) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		UNIQUE KEY actor_activity (actor_identity_id, activity_id),
		KEY actor_cursor (actor_identity_id, id),
		KEY activity_id (activity_id),
		KEY shared_cursor (received_via, activity_id, id)
	) ENGINE=InnoDB {$charset};" );
	$columns = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
	$unique = (array) $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'actor_activity'", $table ), ARRAY_A );
	$cursor = (array) $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'actor_cursor'", $table ), ARRAY_A );
	$shared = (array) $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'shared_cursor'", $table ), ARRAY_A );
	$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
	return ! array_diff( array( 'id', 'actor_identity_id', 'activity_id', 'received_at', 'received_via' ), $columns )
		&& array( 'actor_identity_id', 'activity_id' ) === array_column( $unique, 'Column_name' )
		&& 0 === (int) ( $unique[0]['Non_unique'] ?? 1 )
		&& array( 'received_via', 'activity_id', 'id' ) === array_column( $shared, 'Column_name' )
		&& array( 'actor_identity_id', 'id' ) === array_column( $cursor, 'Column_name' ) && 'InnoDB' === $engine;
}

/** Internal: the caller owns the ledger transaction; a retry may add a newly delivered recipient. */
function axismundi_act_write_inbox_memberships( int $activity_id, array $actor_ids, string $received_at, string $received_via = '' ) : bool {
	global $wpdb;
	if ( ! in_array( $received_via, array( '', 'actor', 'shared' ), true ) ) {
		return false;
	}
	foreach ( array_unique( array_map( 'intval', $actor_ids ) ) as $actor_id ) {
		$actor = axismundi_actors_get_by_identity( $actor_id );
		if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || 'public' !== $actor->get_status() ) {
			return false;
		}
		if ( false === $wpdb->query( $wpdb->prepare(
			"INSERT INTO %i (actor_identity_id, activity_id, received_at, received_via) VALUES (%d, %d, %s, %s)
			 ON DUPLICATE KEY UPDATE received_via = IF(VALUES(received_via) = 'shared', 'shared', IF(received_via = 'shared', 'shared', IF(VALUES(received_via) = 'actor', 'actor', received_via)))",
			axismundi_act_inbox_table(), $actor_id, $activity_id, $received_at, $received_via
		) ) ) {
			return false;
		}
	}
	return true;
}

/** Public shared receipts only, grouped before applying the exclusive receipt cursor. */
function axismundi_act_get_shared_inbox_page( int $limit = 20, string $cursor = '' ) {
	global $wpdb;
	if ( AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return new WP_Error( 'ax_act_schema', 'The receipt repository is unavailable.', array( 'status' => 503 ) );
	}
	if ( '' !== $cursor && ! preg_match( '/\Ar[1-9][0-9]{0,17}\z/D', $cursor ) ) {
		return new WP_Error( 'ax_act_inbox_cursor', 'Invalid receipt cursor.', array( 'status' => 400 ) );
	}
	$limit = max( 1, min( 50, $limit ) );
	$items = array();
	$scan = '' === $cursor ? PHP_INT_MAX : (int) substr( $cursor, 1 );
	$last = '';
	do {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT a.*, s.receipt_id FROM %i a INNER JOIN
			 (SELECT m.activity_id, MAX(m.id) AS receipt_id FROM %i m
			  WHERE m.received_via = 'shared' GROUP BY m.activity_id HAVING MAX(m.id) < %d) s
			 ON a.id = s.activity_id WHERE a.direction = 'inbound' AND a.has_public_audience = 1
			 ORDER BY s.receipt_id DESC LIMIT 100",
			axismundi_act_activities_table(), axismundi_act_inbox_table(), $scan
		), ARRAY_A );
		if ( null === $rows || '' !== $wpdb->last_error ) {
			return new WP_Error( 'ax_act_inbox_read', 'The shared Inbox could not be read.', array( 'status' => 503 ) );
		}
		foreach ( $rows as $row ) {
			$scan = (int) $row['receipt_id'];
			$activity = axismundi_act_hydrate( $row );
			// Recheck the immutable audience; a stale candidate flag cannot disclose private data.
			if ( ! axismundi_act_audience_is_public( $activity->get_audience() ) ) { continue; }
			if ( count( $items ) >= $limit ) {
				return array( 'items' => $items, 'has_more' => true, 'next_cursor' => $last );
			}
			$payload = axismundi_act_inbox_payload( $activity->get_payload() );
			// Shared history must not republish obsolete embedded content snapshots.
			if ( in_array( $payload['type'] ?? '', array( 'Create', 'Update' ), true ) && is_array( $payload['object'] ?? null ) ) {
				$payload['object'] = axismundi_act_member_uri( $payload['object'] );
			}
			$items[] = $payload;
			$last = 'r' . $scan;
		}
	} while ( count( $rows ) === 100 );
	return array( 'items' => $items, 'has_more' => false, 'next_cursor' => '' );
}

/** Never disclose blind recipient metadata, including metadata inside embedded objects. */
function axismundi_act_inbox_payload( array $payload ) : array {
	return axismundi_act_omit_blind_addresses( $payload );
}

/** Trusted internal delivery of an already committed local Activity; never records or replays it. */
function axismundi_act_receive_local_activity( string $activity_uri, int $recipient_id ) {
	global $wpdb;
	if ( AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return new WP_Error( 'ax_act_schema', 'The Inbox repository is unavailable.' );
	}
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
		return new WP_Error( 'ax_act_inbox_write', 'The receipt transaction could not start.' );
	}
	$committed = false;
	try {
		$row = axismundi_act_transaction_row( $activity_uri );
		$activity = is_array( $row ) ? axismundi_act_hydrate( $row ) : null;
		$sender = $activity ? axismundi_actors_get_by_uri( $activity->get_actor_uri() ) : null;
		if ( ! $activity || ! hash_equals( $activity_uri, $activity->get_uri() )
			|| 'outbound' !== $activity->get_direction() || ! $sender instanceof Axismundi_Actor
			|| ! $sender->is_local() || 'public' !== $sender->get_status() ) {
			return new WP_Error( 'ax_act_inbox_direction', 'Local delivery requires a stored outbound local Activity.' );
		}
		if ( ! axismundi_act_write_inbox_memberships( $activity->get_id(), array( $recipient_id ), current_time( 'mysql', true ) )
			|| false === $wpdb->query( 'COMMIT' ) ) {
			return new WP_Error( 'ax_act_inbox_write', 'The Inbox receipt could not be stored.' );
		}
		$committed = true;
		return $activity;
	} finally {
		// Also releases locks after validation, INSERT, or COMMIT failure.
		if ( ! $committed ) {
			$wpdb->query( 'ROLLBACK' );
		}
	}
}

/** Private receipt collection, ordered by immutable receipt id, not sender-controlled published time. */
function axismundi_act_get_actor_inbox_page( string $actor_uri, int $limit = 20, string $cursor = '' ) {
	global $wpdb;
	$actor = axismundi_actors_get_by_uri( $actor_uri );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || 'public' !== $actor->get_status()
		|| ! function_exists( 'axismundi_actors_resolve_workspace' )
		|| null === axismundi_actors_resolve_workspace( $actor->get_identity_id(), get_current_user_id() ) ) {
		return new WP_Error( 'ax_act_inbox_forbidden', 'This Account Inbox is not accessible.', array( 'status' => 403 ) );
	}
	if ( AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return new WP_Error( 'ax_act_schema', 'The Inbox repository is unavailable.', array( 'status' => 503 ) );
	}
	if ( '' !== $cursor && ( ! preg_match( '/\Ar[1-9][0-9]{0,17}\z/D', $cursor ) || (int) substr( $cursor, 1 ) <= 0 ) ) {
		return new WP_Error( 'ax_act_inbox_cursor', 'Invalid receipt cursor.', array( 'status' => 400 ) );
	}
	$limit = max( 1, min( 50, $limit ) );
	$where = '' === $cursor ? '' : $wpdb->prepare( ' AND m.id < %d', (int) substr( $cursor, 1 ) );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT a.*, m.id AS receipt_id FROM %i m INNER JOIN %i a ON a.id = m.activity_id
		 WHERE m.actor_identity_id = %d {$where} ORDER BY m.id DESC LIMIT %d",
		axismundi_act_inbox_table(), axismundi_act_activities_table(), $actor->get_identity_id(), $limit + 1
	), ARRAY_A );
	if ( null === $rows || '' !== $wpdb->last_error ) {
		return new WP_Error( 'ax_act_inbox_read', 'The Inbox could not be read.', array( 'status' => 503 ) );
	}
	$more = count( $rows ) > $limit;
	$rows = array_slice( $rows, 0, $limit );
	$items = array();
	$last = '';
	foreach ( $rows as $row ) {
		// Undo does not erase receipt history: no effective-status filter here.
		$items[] = axismundi_act_inbox_payload( axismundi_act_hydrate( $row )->get_payload() );
		$last = 'r' . $row['receipt_id'];
	}
	return array( 'items' => $items, 'has_more' => $more, 'next_cursor' => $more ? $last : '' );
}
