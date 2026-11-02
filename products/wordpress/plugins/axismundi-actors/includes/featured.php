<?php
/** Person featured membership. Local authoring and remote observation never share write authority. */
defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTORS_FEATURED_LIMIT = 5;
const AXISMUNDI_ACTORS_FEATURED_REMOTE_LIMIT = 100;

function axismundi_actors_featured_table() : string { global $wpdb; return $wpdb->prefix . 'ax_actor_featured'; }
function axismundi_actors_featured_state_table() : string { global $wpdb; return $wpdb->prefix . 'ax_actor_featured_state'; }

/** Independent additive schema: no rewrite of Actor identity or existing Activity payloads. */
function axismundi_actors_install_featured() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = axismundi_actors_featured_table();
	$state = axismundi_actors_featured_state_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		actor_id bigint(20) unsigned NOT NULL,
		object_uri text NOT NULL,
		object_hash char(64) NOT NULL,
		position int unsigned NOT NULL DEFAULT 0,
		resolution varchar(12) NOT NULL DEFAULT 'ready',
		resolution_error varchar(100) DEFAULT NULL,
		added_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY actor_object (actor_id,object_hash),
		KEY actor_order (actor_id,position,id)
	) ENGINE=InnoDB {$charset};" );
	dbDelta( "CREATE TABLE {$state} (
		actor_id bigint(20) unsigned NOT NULL,
		generation bigint(20) unsigned NOT NULL DEFAULT 0,
		collection_uri text NOT NULL,
		fetched_at datetime DEFAULT NULL,
		last_error varchar(100) DEFAULT NULL,
		PRIMARY KEY  (actor_id)
	) ENGINE=InnoDB {$charset};" );
	foreach ( array( $table => array( 'actor_id', 'object_uri', 'object_hash', 'position', 'resolution', 'resolution_error', 'added_at' ), $state => array( 'actor_id', 'generation', 'collection_uri', 'fetched_at', 'last_error' ) ) as $name => $columns ) {
		$actual = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $name ) );
		$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $name ) );
		if ( ! is_array( $actual ) || array_diff( $columns, $actual ) || 'InnoDB' !== $engine ) { return false; }
	}
	update_option( 'ax_actors_featured_schema', '2', false );
	return true;
}
add_action( 'plugins_loaded', static function () : void {
	if ( '2' !== get_option( 'ax_actors_featured_schema', '' ) ) { axismundi_actors_install_featured(); }
} );

function axismundi_actors_featured_uri( Axismundi_Actor $actor ) : string {
	return $actor->is_local() ? (string) apply_filters( 'axismundi_actors_featured_uri', '', $actor ) : axismundi_actors_get_endpoint( $actor, 'featured' );
}

/** Public, registry-backed Object authored by this Person; a cache hit alone is not eligibility. */
function axismundi_actors_featured_object( Axismundi_Actor $actor, string $uri ) {
	if ( 'Person' !== $actor->get_type() || ! function_exists( 'axismundi_op_read_object_jsonld' ) ) {
		return new WP_Error( 'ax_featured_type', 'Person featured requires the Object reader.', array( 'status' => 422 ) );
	}
	$object = axismundi_op_get_object_by_uri( $uri );
	if ( ! is_array( $object ) || ( $actor->is_local() ? 'local' : 'remote' ) !== $object['origin']
		|| $actor->get_uri() !== ( $object['attributed_to_uri'] ?? '' ) ) {
		return new WP_Error( 'ax_featured_author', 'Only this Person\'s own Objects may be featured.', array( 'status' => 403 ) );
	}
	$document = axismundi_op_read_object_jsonld( $uri );
	if ( is_wp_error( $document ) ) { return $document; }
	return 'Tombstone' === ( $document['type'] ?? '' ) ? new WP_Error( 'ax_featured_deleted', 'Deleted Objects cannot be featured.' ) : $document;
}

/** SQL participant. The Activity service owns the transaction and post-commit delivery. */
function axismundi_actors_write_featured( Axismundi_Actor $actor, string $uri, bool $add, int $user_id ) {
	global $wpdb;
	if ( '2' !== get_option( 'ax_actors_featured_schema', '' ) ) { return new WP_Error( 'ax_featured_schema', 'Featured storage is unavailable.', array( 'status' => 503 ) ); }
	if ( ! $actor->is_local() || 'Person' !== $actor->get_type() || ! axismundi_actors_can_act_as( $actor, $user_id ) ) {
		return new WP_Error( 'ax_featured_forbidden', 'The principal cannot curate this Person.', array( 'status' => 403 ) );
	}
	$table = axismundi_actors_featured_table();
	$state = axismundi_actors_featured_state_table();
	$id = $actor->get_identity_id();
	if ( false === $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (actor_id,collection_uri) VALUES (%d,%s)', $state, $id, axismundi_actors_featured_uri( $actor ) ) ) ) { return new WP_Error( 'ax_featured_write', 'Featured state is unavailable.' ); }
	$lock = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE actor_id=%d FOR UPDATE', $state, $id ), ARRAY_A );
	if ( ! $lock ) { return new WP_Error( 'ax_featured_write', 'Featured state could not be locked.' ); }
	if ( ! $add ) {
		return false !== $wpdb->delete( $table, array( 'actor_id' => $id, 'object_hash' => hash( 'sha256', $uri ), 'object_uri' => $uri ) ) ? true : new WP_Error( 'ax_featured_write', 'Featured removal failed.' );
	}
	// A confirmed deletion frees capacity; unavailable/private members are not assumed deleted.
	$existing_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT object_uri FROM %i WHERE actor_id=%d', $table, $id ), ARRAY_A );
	if ( ! is_array( $existing_rows ) || '' !== $wpdb->last_error ) { return new WP_Error( 'ax_featured_read', 'Featured membership is unavailable.' ); }
	foreach ( $existing_rows as $row ) {
		$local = axismundi_op_get_local_object( $row['object_uri'] );
		$registered = axismundi_op_get_object_by_uri( $row['object_uri'] );
		if ( 'tombstone' === ( $local['object_status'] ?? '' ) || 'tombstone' === ( $registered['status'] ?? '' ) ) {
			if ( false === $wpdb->delete( $table, array( 'actor_id' => $id, 'object_uri' => $row['object_uri'] ) ) ) { return new WP_Error( 'ax_featured_write', 'Deleted membership cleanup failed.' ); }
		}
	}
	$eligible = axismundi_actors_featured_object( $actor, $uri );
	if ( is_wp_error( $eligible ) ) { return $eligible; }
	$old = $wpdb->get_var( $wpdb->prepare( 'SELECT object_uri FROM %i WHERE actor_id=%d AND object_hash=%s', $table, $id, hash( 'sha256', $uri ) ) );
	if ( null !== $old ) { return $old === $uri ? true : new WP_Error( 'ax_featured_collision', 'Featured identity conflict.' ); }
	$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE actor_id=%d', $table, $id ) );
	if ( '' !== $wpdb->last_error || null === $count ) { return new WP_Error( 'ax_featured_read', 'Featured membership is unavailable.' ); }
	if ( (int) $count >= AXISMUNDI_ACTORS_FEATURED_LIMIT ) { return new WP_Error( 'ax_featured_limit', 'At most five Objects may be featured.', array( 'status' => 422 ) ); }
	if ( false === $wpdb->query( $wpdb->prepare( 'UPDATE %i SET position=position+1 WHERE actor_id=%d', $table, $id ) )
		|| false === $wpdb->insert( $table, array( 'actor_id' => $id, 'object_uri' => $uri, 'object_hash' => hash( 'sha256', $uri ), 'position' => 0, 'added_at' => current_time( 'mysql', true ) ) ) ) {
		return new WP_Error( 'ax_featured_write', 'Featured addition failed.' );
	}
	return true;
}

/** No member URI leaks when a member is private, deleted, or no longer readable. No network. */
function axismundi_actors_read_featured( Axismundi_Actor $actor ) {
	global $wpdb;
	if ( '2' !== get_option( 'ax_actors_featured_schema', '' ) || 'Person' !== $actor->get_type() ) { return new WP_Error( 'ax_featured_schema', 'Person featured storage is unavailable.', array( 'status' => 503 ) ); }
	if ( ! $actor->is_local() ) {
		$state = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE actor_id=%d', axismundi_actors_featured_state_table(), $actor->get_identity_id() ), ARRAY_A );
		if ( ! $state || ! $state['fetched_at'] || $state['collection_uri'] !== axismundi_actors_featured_uri( $actor ) ) { return new WP_Error( 'ax_featured_unobserved', 'No complete snapshot of this advertised collection is available.' ); }
	}
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE actor_id=%d ORDER BY position ASC,id DESC LIMIT %d', axismundi_actors_featured_table(), $actor->get_identity_id(), AXISMUNDI_ACTORS_FEATURED_REMOTE_LIMIT + 1 ), ARRAY_A );
	if ( ! is_array( $rows ) || '' !== $wpdb->last_error || count( $rows ) > AXISMUNDI_ACTORS_FEATURED_REMOTE_LIMIT ) { return new WP_Error( 'ax_featured_read', 'Featured membership is unavailable.' ); }
	$items = array();
	foreach ( $rows as $row ) {
		if ( 'ready' === $row['resolution'] && ! is_wp_error( axismundi_actors_featured_object( $actor, $row['object_uri'] ) ) ) { $items[] = $row['object_uri']; }
	}
	return array( 'items' => $items, 'total' => count( $items ) );
}

/** A signal invalidates in-flight snapshots; it never guesses the remote collection order. */
function axismundi_actors_request_featured_sync( Axismundi_Actor $actor ) {
	global $wpdb;
	$uri = axismundi_actors_featured_uri( $actor );
	if ( '2' !== get_option( 'ax_actors_featured_schema', '' ) || $actor->is_local() || 'Person' !== $actor->get_type() || 'tombstone' === $actor->get_status() || '' === $uri ) { return new WP_Error( 'ax_featured_remote', 'No remote Person featured collection is advertised.' ); }
	$ok = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (actor_id,collection_uri,generation) VALUES (%d,%s,1) ON DUPLICATE KEY UPDATE fetched_at=IF(collection_uri=VALUES(collection_uri),fetched_at,NULL),collection_uri=VALUES(collection_uri),generation=generation+1', axismundi_actors_featured_state_table(), $actor->get_identity_id(), $uri ) );
	if ( false === $ok ) { return new WP_Error( 'ax_featured_write', 'Featured synchronization could not be scheduled.' ); }
	$args = array( $actor->get_identity_id() );
	if ( ! wp_next_scheduled( 'axismundi_actors_sync_featured', $args ) ) { wp_schedule_single_event( time() + 5, 'axismundi_actors_sync_featured', $args ); }
	return true;
}
add_action( 'axismundi_actors_remote_actor_discovered', 'axismundi_actors_request_featured_sync' );
add_action( 'axismundi_actors_remote_actor_updated', 'axismundi_actors_request_featured_sync' );

/** Complete bounded traversal. Partial, cyclic, cross-origin, or contradictory snapshots are errors. */
function axismundi_actors_fetch_featured_snapshot( Axismundi_Actor $actor, string $uri ) {
	$root = axismundi_actors_remote_get_json( $uri, array( 'application/activity+json', 'application/ld+json', 'application/json' ) );
	if ( is_wp_error( $root ) ) { return $root; }
	if ( ( $root['id'] ?? '' ) !== $uri || 'OrderedCollection' !== ( $root['type'] ?? '' ) ) { return new WP_Error( 'ax_featured_collection', 'The advertised OrderedCollection identity does not match.' ); }
	$document = $root;
	$items = array();
	$seen = array( $uri => true );
	for ( $page = 0; $page < 6; ++$page ) {
		if ( array_key_exists( 'orderedItems', $document ) ) {
			if ( ! is_array( $document['orderedItems'] ) || ! array_is_list( $document['orderedItems'] ) ) { return new WP_Error( 'ax_featured_items', 'Malformed orderedItems.' ); }
			$items = array_merge( $items, $document['orderedItems'] );
		} elseif ( ! isset( $document['first'] ) ) { return new WP_Error( 'ax_featured_incomplete', 'The collection does not enumerate its members.' ); }
		if ( count( $items ) > AXISMUNDI_ACTORS_FEATURED_REMOTE_LIMIT ) { return new WP_Error( 'ax_featured_limit', 'Remote featured exceeds one hundred members.' ); }
		$next = $document['next'] ?? ( 0 === $page ? ( $document['first'] ?? null ) : null );
		if ( null === $next ) { break; }
		if ( is_array( $next ) && isset( $next['orderedItems'] ) ) {
			$document = $next;
		} else {
			$url = is_string( $next ) ? $next : ( $next['id'] ?? '' );
			if ( ! is_string( $url ) || isset( $seen[ $url ] ) || wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( $uri, PHP_URL_HOST ) || wp_parse_url( $url, PHP_URL_PORT ) !== wp_parse_url( $uri, PHP_URL_PORT ) ) { return new WP_Error( 'ax_featured_page', 'Unsafe or repeated featured page.' ); }
			$seen[ $url ] = true;
			$document = axismundi_actors_remote_get_json( $url, array( 'application/activity+json', 'application/ld+json', 'application/json' ) );
			if ( is_wp_error( $document ) ) { return $document; }
			if ( ( $document['id'] ?? '' ) !== $url ) { return new WP_Error( 'ax_featured_page', 'Featured page identity mismatch.' ); }
		}
		if ( 'OrderedCollectionPage' !== ( $document['type'] ?? '' ) || $uri !== ( is_string( $document['partOf'] ?? null ) ? $document['partOf'] : ( $document['partOf']['id'] ?? '' ) ) ) { return new WP_Error( 'ax_featured_page', 'Featured page belongs to another collection.' ); }
		if ( 5 === $page ) { return new WP_Error( 'ax_featured_incomplete', 'Featured page limit reached.' ); }
	}
	if ( isset( $root['totalItems'] ) && ( ! is_int( $root['totalItems'] ) || count( $items ) !== $root['totalItems'] ) ) { return new WP_Error( 'ax_featured_incomplete', 'The complete featured count does not match.' ); }
	$uris = array();
	foreach ( $items as $item ) {
		$url = is_string( $item ) ? $item : ( is_array( $item ) ? ( $item['id'] ?? '' ) : '' );
		if ( ! is_string( $url ) || '' === axismundi_actors_normalize_endpoint_uri( $url ) || isset( $uris[ $url ] ) ) { return new WP_Error( 'ax_featured_item', 'Invalid or duplicate featured Object identity.' ); }
		$uris[ $url ] = true;
	}
	$members = array();
	foreach ( array_keys( $uris ) as $url ) {
		$member = array( 'uri' => $url, 'resolution' => 'unresolved', 'error' => null );
		// Resolve independently: an inline item is not proof of authorship or current visibility.
		$cached = function_exists( 'axismundi_op_remote_object_fetch' ) ? axismundi_op_remote_object_fetch( $url ) : new WP_Error( 'ax_featured_reader', 'Remote Object fetching is unavailable.' );
		if ( is_wp_error( $cached ) ) {
			$member['error'] = $cached->get_error_code();
		} elseif ( ( $cached['object_uri'] ?? '' ) !== $url ) {
			$member['resolution'] = 'excluded';
			$member['error'] = 'ax_featured_item_identity';
		} elseif ( 'tombstone' === ( $cached['object_status'] ?? '' ) || $actor->get_uri() !== ( $cached['attributed_to_uri'] ?? '' ) ) {
			$member['resolution'] = 'excluded';
			$member['error'] = 'tombstone' === ( $cached['object_status'] ?? '' ) ? 'ax_featured_deleted' : 'ax_featured_author';
		} elseif ( ! axismundi_op_cached_object_publicly_viewable( $cached ) ) {
			$member['resolution'] = 'excluded';
			$member['error'] = 'ax_featured_not_public';
		} else {
			$eligible = axismundi_actors_featured_object( $actor, $url );
			$member['error'] = is_wp_error( $eligible ) ? $eligible->get_error_code() : null;
			$member['resolution'] = is_wp_error( $eligible ) ? 'unresolved' : 'ready';
		}
		$members[] = $member;
	}
	return $members;
}

/** Replace only a complete snapshot, under a generation fence. Network work is outside SQL locks. */
function axismundi_actors_sync_featured( int $id ) {
	global $wpdb;
	$actor = axismundi_actors_get_by_identity( $id );
	if ( ! $actor || $actor->is_local() || 'Person' !== $actor->get_type() ) { return new WP_Error( 'ax_featured_remote', 'A remote Person is required.' ); }
	$state = axismundi_actors_featured_state_table();
	$before = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE actor_id=%d', $state, $id ), ARRAY_A );
	if ( ! $before ) { return new WP_Error( 'ax_featured_state', 'No synchronization request exists.' ); }
	$items = axismundi_actors_fetch_featured_snapshot( $actor, $before['collection_uri'] );
	if ( is_wp_error( $items ) ) {
		$wpdb->update( $state, array( 'last_error' => substr( $items->get_error_code(), 0, 100 ) ), array( 'actor_id' => $id ) );
		return $items;
	}
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return new WP_Error( 'ax_featured_transaction', 'Could not start synchronization.' ); }
	$now = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE actor_id=%d FOR UPDATE', $state, $id ), ARRAY_A );
	if ( ! $now || $now['generation'] !== $before['generation'] || $now['collection_uri'] !== $before['collection_uri'] || axismundi_actors_featured_uri( $actor ) !== $before['collection_uri'] ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'ax_featured_stale', 'A newer signal invalidated this snapshot.' );
	}
	$table = axismundi_actors_featured_table();
	$ok = false !== $wpdb->delete( $table, array( 'actor_id' => $id ) );
	$unresolved = false;
	foreach ( $items as $position => $member ) {
		$uri = $member['uri'];
		$unresolved = $unresolved || 'unresolved' === $member['resolution'];
		$ok = $ok && false !== $wpdb->insert( $table, array( 'actor_id' => $id, 'object_uri' => $uri, 'object_hash' => hash( 'sha256', $uri ), 'position' => $position, 'resolution' => $member['resolution'], 'resolution_error' => null === $member['error'] ? null : substr( $member['error'], 0, 100 ), 'added_at' => current_time( 'mysql', true ) ) );
	}
	$ok = $ok && false !== $wpdb->update( $state, array( 'fetched_at' => current_time( 'mysql', true ), 'last_error' => $unresolved ? 'ax_featured_items_unresolved' : null ), array( 'actor_id' => $id ) );
	if ( ! $ok || false === $wpdb->query( 'COMMIT' ) ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'ax_featured_write', 'Featured snapshot could not be committed.' ); }
	return true;
}
/** Periodic reconciliation covers missed delivery; failures preserve membership and retry later. */
function axismundi_actors_featured_sync_worker( int $id ) : void {
	global $wpdb;
	$result = axismundi_actors_sync_featured( $id );
	$pending = $wpdb->get_var( $wpdb->prepare( 'SELECT last_error FROM %i WHERE actor_id=%d', axismundi_actors_featured_state_table(), $id ) );
	$actor = axismundi_actors_get_by_identity( $id );
	if ( $actor && ! $actor->is_local() && 'Person' === $actor->get_type() && 'tombstone' !== $actor->get_status() && '' !== axismundi_actors_featured_uri( $actor )
		&& ! wp_next_scheduled( 'axismundi_actors_sync_featured', array( $id ) ) ) {
		wp_schedule_single_event( time() + ( is_wp_error( $result ) || 'ax_featured_items_unresolved' === $pending ? 15 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS ), 'axismundi_actors_sync_featured', array( $id ) );
	}
}
add_action( 'axismundi_actors_sync_featured', 'axismundi_actors_featured_sync_worker' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'axismundi featured sync', static function ( array $args ) : void {
		$actor = axismundi_actors_get_by_uri( (string) ( $args[0] ?? '' ) );
		if ( ! $actor ) { WP_CLI::error( 'Supply a cached remote Person URI.' ); }
		$result = axismundi_actors_request_featured_sync( $actor );
		if ( ! is_wp_error( $result ) ) { $result = axismundi_actors_sync_featured( $actor->get_identity_id() ); }
		if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
		WP_CLI::success( 'Complete featured snapshot committed.' );
	} );
}
