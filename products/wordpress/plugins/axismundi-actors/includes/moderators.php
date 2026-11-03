<?php
/** Community moderators: explicit Actor membership, never principal or Follow grants. */
defined( 'ABSPATH' ) || exit;

function axismundi_actors_moderators_table() : string { global $wpdb; return $wpdb->prefix . 'ax_actor_moderators'; }
function axismundi_actors_moderators_state_table() : string { global $wpdb; return $wpdb->prefix . 'ax_actor_moderator_state'; }
function axismundi_actors_moderators_log_table() : string { global $wpdb; return $wpdb->prefix . 'ax_actor_moderator_log'; }

function axismundi_actors_install_moderators() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = axismundi_actors_moderators_table(); $state = axismundi_actors_moderators_state_table(); $log = axismundi_actors_moderators_log_table(); $charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		group_id bigint(20) unsigned NOT NULL,
		person_uri text NOT NULL,
		person_hash char(64) NOT NULL,
		position int unsigned NOT NULL,
		appointed_at datetime DEFAULT NULL,
		resolution varchar(12) NOT NULL,
		resolution_error varchar(100) DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY group_person (group_id,person_hash),
		KEY group_order (group_id,position,id)
	) ENGINE=InnoDB {$charset};" );
	dbDelta( "CREATE TABLE {$state} (
		group_id bigint(20) unsigned NOT NULL,
		collection_uri text NOT NULL,
		generation bigint(20) unsigned NOT NULL DEFAULT 0,
		fetched_at datetime DEFAULT NULL,
		last_error varchar(100) DEFAULT NULL,
		PRIMARY KEY  (group_id)
	) ENGINE=InnoDB {$charset};" );
	dbDelta( "CREATE TABLE {$log} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		group_id bigint(20) unsigned NOT NULL,
		actor_uri text NOT NULL,
		person_uri text NOT NULL,
		verb varchar(16) NOT NULL,
		operation_key char(64) NOT NULL,
		activity_uri text DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY operation_key (operation_key),
		KEY group_log (group_id,id)
	) ENGINE=InnoDB {$charset};" );
	foreach ( array( $table => array( 'group_id', 'person_uri', 'position', 'appointed_at', 'resolution' ), $state => array( 'group_id', 'collection_uri', 'generation', 'fetched_at' ), $log => array( 'group_id', 'actor_uri', 'operation_key', 'activity_uri' ) ) as $name => $required ) {
		$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $name ) );
		$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $name ) );
		if ( ! is_array( $columns ) || array_diff( $required, $columns ) || 'InnoDB' !== $engine ) { return false; }
	}
	update_option( 'ax_actors_moderators_schema', '1', false );
	return true;
}
add_action( 'plugins_loaded', static function () : void {
	if ( '1' !== get_option( 'ax_actors_moderators_schema' ) ) { axismundi_actors_install_moderators(); }
} );

function axismundi_actors_is_community( Axismundi_Actor $group ) : bool {
	return $group->is_local() && 'Group' === $group->get_type() && 'community' === axismundi_actors_group_profile( $group );
}

function axismundi_actors_moderators_uri( Axismundi_Actor $group ) : string {
	if ( $group->is_local() ) { return rest_url( 'axismundi/v1/actors/' . $group->get_uuid() . '/moderators' ); }
	$payload = axismundi_actors_get_remote_payload( $group->get_identity_id() );
	$reference = $payload['attributedTo'] ?? null;
	// A typed Actor array remains attribution evidence, not a moderator collection.
	if ( ! is_string( $reference ) && ! ( is_array( $reference ) && in_array( $reference['type'] ?? '', array( 'Collection', 'OrderedCollection' ), true ) ) ) { return ''; }
	$members = axismundi_as2_normalize_members( $reference );
	return 1 === count( $members ) && $members[0]['resolvable'] ? $members[0]['uri'] : '';
}

/** Exact canonical local target; no suffix-based ownership or arbitrary host matching. */
function axismundi_actors_moderators_group_for_uri( string $uri ) : ?Axismundi_Actor {
	if ( ! preg_match( '#/actors/([a-f0-9-]{36})/moderators$#D', $uri, $matches ) ) { return null; }
	$group = axismundi_actors_get_by_uuid( $matches[1] );
	return $group && axismundi_actors_is_community( $group ) && $uri === axismundi_actors_moderators_uri( $group ) ? $group : null;
}

/** Remote self-description defines the reference; only a fetched Person collection supplies roles. */
function axismundi_actors_remote_moderators_reference( Axismundi_Actor $group, string $uri ) : bool {
	if ( $group->is_local() || 'Group' !== $group->get_type() || 'public' !== $group->get_status() || '' === $uri ) { return false; }
	$payload = axismundi_actors_get_remote_payload( $group->get_identity_id() );
	$refs = array_column( axismundi_as2_normalize_members( $payload['attributedTo'] ?? array() ), 'uri' );
	$origin = static fn( $url ) => array( wp_parse_url( $url, PHP_URL_SCHEME ), wp_parse_url( $url, PHP_URL_HOST ), wp_parse_url( $url, PHP_URL_PORT ) );
	return in_array( $uri, $refs, true ) && $origin( $uri ) === $origin( $group->get_uri() );
}

/** Public membership and authorization both exclude unresolved or no-longer-valid Actors. */
function axismundi_actors_read_moderators( Axismundi_Actor $group ) {
	global $wpdb;
	if ( '1' !== get_option( 'ax_actors_moderators_schema' ) || 'Group' !== $group->get_type() || 'public' !== $group->get_status()
		|| ( $group->is_local() && ! axismundi_actors_is_community( $group ) ) ) { return new WP_Error( 'ax_mod_profile', 'Moderators require the Community profile.' ); }
	if ( ! $group->is_local() ) {
		$state = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE group_id=%d', axismundi_actors_moderators_state_table(), $group->get_identity_id() ), ARRAY_A );
		if ( ! $state || ! $state['fetched_at'] || $state['collection_uri'] !== axismundi_actors_moderators_uri( $group ) || ! axismundi_actors_remote_moderators_reference( $group, $state['collection_uri'] ) ) { return new WP_Error( 'ax_mod_unobserved', 'No complete referenced moderator snapshot is available.' ); }
	}
	$order = $group->is_local() ? 'appointed_at ASC,id ASC' : 'position ASC,id ASC';
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE group_id=%d ORDER BY {$order}", axismundi_actors_moderators_table(), $group->get_identity_id() ), ARRAY_A );
	if ( ! is_array( $rows ) || $wpdb->last_error ) { return new WP_Error( 'ax_mod_read', 'Moderator membership could not be read.' ); }
	$items = array();
	foreach ( $rows as $row ) {
		$person = axismundi_actors_get_by_uri( $row['person_uri'] );
		if ( 'ready' === $row['resolution'] && $person && 'Person' === $person->get_type() && 'public' === $person->get_status() ) { $items[] = $row['person_uri']; }
	}
	return array( 'items' => $items );
}

/** Reverse membership lookup, independent of the operating WP User or any UI. */
function axismundi_actors_local_communities_moderated_by( Axismundi_Actor $person ) : array {
	if ( 'Person' !== $person->get_type() || 'public' !== $person->get_status() ) { return array(); }
	$groups = array();
	foreach ( axismundi_actors_list_local_actors( 'public' ) as $group ) {
		if ( ! axismundi_actors_is_community( $group ) ) { continue; }
		$members = axismundi_actors_read_moderators( $group );
		if ( ! is_wp_error( $members ) && in_array( $person->get_uri(), $members['items'], true ) ) { $groups[] = $group; }
	}
	return $groups;
}

/** SQL participant only. The Activity service owns the surrounding transaction. */
function axismundi_actors_write_moderators( Axismundi_Actor $group, Axismundi_Actor $actor, Axismundi_Actor $member, bool $add, string $operation, string $activity_uri, bool $bootstrap = false, int $user = 0 ) {
	global $wpdb;
	if ( ! axismundi_actors_is_community( $group ) || 'public' !== $group->get_status() || 'Person' !== $actor->get_type() || 'Person' !== $member->get_type() || 'public' !== $actor->get_status() || ( $add && 'public' !== $member->get_status() ) ) { return new WP_Error( 'ax_mod_profile', 'Only active Community Groups and eligible Person members are supported.' ); }
	$id = $group->get_identity_id(); $state = axismundi_actors_moderators_state_table(); $table = axismundi_actors_moderators_table(); $log = axismundi_actors_moderators_log_table();
	if ( false === $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (group_id,collection_uri) VALUES (%d,%s)', $state, $id, axismundi_actors_moderators_uri( $group ) ) )
		|| ! $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE group_id=%d FOR UPDATE', $state, $id ) ) ) { return new WP_Error( 'ax_mod_write', 'Could not lock moderator state.' ); }
	$old = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE operation_key=%s', $log, $operation ), ARRAY_A );
	if ( $wpdb->last_error ) { return new WP_Error( 'ax_mod_read', 'Could not read moderation log.' ); }
	if ( $old ) { return (int) $old['group_id'] === $id && $old['actor_uri'] === $actor->get_uri() && $old['person_uri'] === $member->get_uri() && $old['verb'] === ( $bootstrap ? 'Initialize' : ( $add ? 'Add' : 'Remove' ) ) ? true : new WP_Error( 'ax_mod_conflict', 'Moderation identity conflict.' ); }
	$members = axismundi_actors_read_moderators( $group );
	if ( is_wp_error( $members ) ) { return $members; }
	$items = $members['items'];
	$rank = array_search( $actor->get_uri(), $items, true );
	$target_rank = array_search( $member->get_uri(), $items, true );
	if ( $bootstrap ) {
		// Explicit first appointment is not inferred from ownership or the current session Actor.
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE group_id=%d', $log, $id ) );
		if ( $wpdb->last_error ) { return new WP_Error( 'ax_mod_read', 'Could not read moderator initialization history.' ); }
		$member_count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE group_id=%d', $table, $id ) );
		$initialized = (int) $count > 0 || (int) $member_count > 0 || $items;
		if ( $wpdb->last_error || $initialized || ! $add || $actor->get_uri() !== $member->get_uri() || ! axismundi_actors_can_act_as( $group, $user ) || ! axismundi_actors_can_act_as( $actor, $user ) ) { return new WP_Error( 'ax_mod_bootstrap', 'Explicit first appointment requires an uninitialized Community and both operating permissions.', array( 'status' => 403 ) ); }
	} elseif ( false === $rank || ( false !== $target_rank && $target_rank < $rank ) || ( ! $add && 1 === count( $items ) && false !== $target_rank ) ) {
		return new WP_Error( 'ax_mod_forbidden', 'An existing moderator of sufficient rank is required; the last moderator cannot be removed.', array( 'status' => 403 ) );
	}
	if ( $add && false === $target_rank ) {
		$ok = false !== $wpdb->insert( $table, array( 'group_id' => $id, 'person_uri' => $member->get_uri(), 'person_hash' => hash( 'sha256', $member->get_uri() ), 'position' => count( $items ), 'appointed_at' => current_time( 'mysql', true ), 'resolution' => 'ready' ) );
	} elseif ( ! $add ) {
		$ok = false !== $wpdb->delete( $table, array( 'group_id' => $id, 'person_uri' => $member->get_uri() ) );
	} else { $ok = true; }
	if ( ! $ok || false === $wpdb->insert( $log, array( 'group_id' => $id, 'actor_uri' => $actor->get_uri(), 'person_uri' => $member->get_uri(), 'verb' => $bootstrap ? 'Initialize' : ( $add ? 'Add' : 'Remove' ), 'operation_key' => $operation, 'activity_uri' => $activity_uri, 'created_at' => current_time( 'mysql', true ) ) ) ) { return new WP_Error( 'ax_mod_write', 'Moderator membership and log could not be written.' ); }
	return true;
}

/** Signals invalidate in-flight observations; never optimistically apply a remote Add. */
function axismundi_actors_request_moderators_sync( Axismundi_Actor $group ) {
	global $wpdb;
	$uri = axismundi_actors_moderators_uri( $group );
	if ( ! axismundi_actors_remote_moderators_reference( $group, $uri ) ) { return new WP_Error( 'ax_mod_reference', 'No same-origin moderator collection reference is available.' ); }
	$ok = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (group_id,collection_uri,generation) VALUES (%d,%s,1) ON DUPLICATE KEY UPDATE fetched_at=IF(collection_uri=VALUES(collection_uri),fetched_at,NULL),collection_uri=VALUES(collection_uri),generation=generation+1', axismundi_actors_moderators_state_table(), $group->get_identity_id(), $uri ) );
	if ( false === $ok ) { return new WP_Error( 'ax_mod_write', 'Could not request moderator synchronization.' ); }
	if ( ! wp_next_scheduled( 'axismundi_actors_sync_moderators', array( $group->get_identity_id() ) ) ) { wp_schedule_single_event( time() + 5, 'axismundi_actors_sync_moderators', array( $group->get_identity_id() ) ); }
	return true;
}
add_action( 'axismundi_actors_remote_actor_discovered', 'axismundi_actors_request_moderators_sync' );
add_action( 'axismundi_actors_remote_actor_updated', 'axismundi_actors_request_moderators_sync' );

function axismundi_actors_fetch_moderators_snapshot( string $uri ) {
	$document = axismundi_actors_remote_get_json( $uri, array( 'application/activity+json', 'application/ld+json', 'application/json' ) );
	if ( is_wp_error( $document ) ) { return $document; }
	$items = $document['orderedItems'] ?? null;
	if ( ( $document['id'] ?? '' ) !== $uri || 'OrderedCollection' !== ( $document['type'] ?? '' ) || ! is_array( $items ) || ! array_is_list( $items ) || count( $items ) > 1000 || isset( $document['next'] ) || isset( $document['first'] )
		|| ( isset( $document['totalItems'] ) && ( ! is_int( $document['totalItems'] ) || $document['totalItems'] !== count( $items ) ) ) ) { return new WP_Error( 'ax_mod_structure', 'A complete bounded moderator OrderedCollection is required.' ); }
	$seen = array();
	foreach ( $items as $item ) {
		if ( ! is_string( $item ) || '' === axismundi_actors_normalize_endpoint_uri( $item ) || isset( $seen[$item] ) ) { return new WP_Error( 'ax_mod_structure', 'Invalid or duplicate moderator identity.' ); }
		$seen[$item] = true;
	}
	$result = array();
	foreach ( $items as $item ) {
		$member = array( 'uri' => $item, 'resolution' => 'unresolved', 'error' => null );
		$local = axismundi_actors_get_by_uri( $item );
		if ( $local && $local->is_local() ) {
			$member['resolution'] = 'Person' === $local->get_type() && 'public' === $local->get_status() ? 'ready' : 'excluded';
		} else {
			$person = axismundi_actors_remote_get_json( $item, array( 'application/activity+json', 'application/ld+json', 'application/json' ) );
			if ( is_wp_error( $person ) ) { $member['error'] = $person->get_error_code(); }
			elseif ( ( $person['id'] ?? '' ) !== $item || 'Person' !== ( $person['type'] ?? '' ) ) { $member['resolution'] = 'excluded'; $member['error'] = 'ax_mod_member'; }
			else {
				$record = axismundi_actors_normalize_remote_actor_payload( $person, $item );
				$stored = is_wp_error( $record ) ? $record : axismundi_actors_upsert_remote( $record );
				if ( is_wp_error( $stored ) ) { $member['error'] = $stored->get_error_code(); }
				else { $member['resolution'] = 'ready'; }
			}
		}
		$result[] = $member;
	}
	return $result;
}

function axismundi_actors_sync_moderators( int $id ) {
	global $wpdb;
	$group = axismundi_actors_get_by_identity( $id );
	$state = axismundi_actors_moderators_state_table(); $table = axismundi_actors_moderators_table();
	if ( ! $group || ! axismundi_actors_remote_moderators_reference( $group, axismundi_actors_moderators_uri( $group ) ) ) { return new WP_Error( 'ax_mod_reference', 'No same-origin remote moderator collection.' ); }
	$before = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE group_id=%d', $state, $id ), ARRAY_A );
	if ( ! $before ) { return new WP_Error( 'ax_mod_state', 'No moderator synchronization request exists.' ); }
	$items = axismundi_actors_fetch_moderators_snapshot( $before['collection_uri'] );
	if ( is_wp_error( $items ) ) { $wpdb->update( $state, array( 'last_error' => $items->get_error_code() ), array( 'group_id' => $id ) ); return $items; }
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return new WP_Error( 'ax_mod_write', 'Could not start moderator reconciliation.' ); }
	$now = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE group_id=%d FOR UPDATE', $state, $id ), ARRAY_A );
	$fresh = axismundi_actors_get_by_identity( $id );
	if ( ! $now || $now['generation'] !== $before['generation'] || $now['collection_uri'] !== $before['collection_uri'] || ! $fresh || axismundi_actors_moderators_uri( $fresh ) !== $before['collection_uri'] || ! axismundi_actors_remote_moderators_reference( $fresh, $before['collection_uri'] ) ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'ax_mod_stale', 'A newer moderator signal invalidated this snapshot.' ); }
	$ok = false !== $wpdb->delete( $table, array( 'group_id' => $id ) ); $unresolved = false;
	foreach ( $items as $position => $member ) {
		$unresolved = $unresolved || 'unresolved' === $member['resolution'];
		$ok = $ok && false !== $wpdb->insert( $table, array( 'group_id' => $id, 'person_uri' => $member['uri'], 'person_hash' => hash( 'sha256', $member['uri'] ), 'position' => $position, 'appointed_at' => null, 'resolution' => $member['resolution'], 'resolution_error' => $member['error'] ) );
	}
	$ok = $ok && false !== $wpdb->update( $state, array( 'fetched_at' => current_time( 'mysql', true ), 'last_error' => $unresolved ? 'ax_mod_unresolved' : null ), array( 'group_id' => $id ) );
	if ( ! $ok || false === $wpdb->query( 'COMMIT' ) ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'ax_mod_write', 'Could not commit moderator reconciliation.' ); }
	return true;
}
add_action( 'axismundi_actors_sync_moderators', static function ( int $id ) : void {
	$result = axismundi_actors_sync_moderators( $id );
	$group = axismundi_actors_get_by_identity( $id );
	if ( $group && axismundi_actors_remote_moderators_reference( $group, axismundi_actors_moderators_uri( $group ) ) && ! wp_next_scheduled( 'axismundi_actors_sync_moderators', array( $id ) ) ) { wp_schedule_single_event( time() + ( is_wp_error( $result ) ? 15 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS ), 'axismundi_actors_sync_moderators', array( $id ) ); }
} );
