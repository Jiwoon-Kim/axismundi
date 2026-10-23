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
		PRIMARY KEY  (id),
		UNIQUE KEY actor_activity (actor_identity_id, activity_id),
		KEY actor_cursor (actor_identity_id, id),
		KEY activity_id (activity_id)
	) ENGINE=InnoDB {$charset};" );
	$columns = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
	$unique = (array) $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'actor_activity'", $table ), ARRAY_A );
	$cursor = (array) $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'actor_cursor'", $table ), ARRAY_A );
	$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
	return ! array_diff( array( 'id', 'actor_identity_id', 'activity_id', 'received_at' ), $columns )
		&& array( 'actor_identity_id', 'activity_id' ) === array_column( $unique, 'Column_name' )
		&& 0 === (int) ( $unique[0]['Non_unique'] ?? 1 )
		&& array( 'actor_identity_id', 'id' ) === array_column( $cursor, 'Column_name' ) && 'InnoDB' === $engine;
}

/** Internal: the caller owns the ledger transaction; a retry may add a newly delivered recipient. */
function axismundi_act_write_inbox_memberships( int $activity_id, array $actor_ids, string $received_at ) : bool {
	global $wpdb;
	foreach ( array_unique( array_map( 'intval', $actor_ids ) ) as $actor_id ) {
		$actor = axismundi_actors_get_by_identity( $actor_id );
		if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || 'public' !== $actor->get_status() ) {
			return false;
		}
		if ( false === $wpdb->query( $wpdb->prepare(
			'INSERT INTO %i (actor_identity_id, activity_id, received_at) VALUES (%d, %d, %s) ON DUPLICATE KEY UPDATE activity_id = activity_id',
			axismundi_act_inbox_table(), $actor_id, $activity_id, $received_at
		) ) ) {
			return false;
		}
	}
	return true;
}

/** Never disclose blind recipient metadata, including metadata inside embedded objects. */
function axismundi_act_inbox_payload( array $payload ) : array {
	unset( $payload['bto'], $payload['bcc'] );
	foreach ( $payload as $key => $value ) {
		if ( is_array( $value ) ) {
			$payload[ $key ] = axismundi_act_inbox_payload( $value );
		}
	}
	return $payload;
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
		 WHERE m.actor_identity_id = %d AND a.direction = 'inbound' {$where} ORDER BY m.id DESC LIMIT %d",
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
