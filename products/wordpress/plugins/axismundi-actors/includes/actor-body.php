<?php
/** Actor-owned identifiers and a separate detail/evidence store. */
defined( 'ABSPATH' ) || exit;

function axismundi_actors_meta_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_actormeta';
}

function axismundi_actors_body_installed() : bool {
	global $wpdb;
	$table = $wpdb->prefix . 'ax_actors';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { return false; }
	$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	return in_array( 'id', $columns, true ) && in_array( 'canonical_uri', $columns, true );
}

/** Raw evidence is read only when a detail consumer requests it. */
function axismundi_actors_get_meta( int $actor_id, string $key ) : ?string {
	global $wpdb;
	$value = $wpdb->get_var( $wpdb->prepare( 'SELECT meta_value FROM %i WHERE actor_id=%d AND meta_key=%s', axismundi_actors_meta_table(), $actor_id, $key ) );
	return null === $value ? null : (string) $value;
}

function axismundi_actors_store_meta( int $actor_id, string $key, string $value ) : bool {
	global $wpdb;
	return false !== $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (actor_id,meta_key,meta_value) VALUES (%d,%s,%s) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)', axismundi_actors_meta_table(), $actor_id, $key, $value ) );
}

/** Narrow projection for indexed lists, attribution and hovercard summaries. */
function axismundi_actors_body_summary( string $uri, array $payload = array() ) : array {
	$host = strtolower( (string) wp_parse_url( $uri, PHP_URL_HOST ) );
	$port = wp_parse_url( $uri, PHP_URL_PORT );
	if ( $port ) { $host .= ':' . $port; }
	$asset = static function ( $value ) : ?string {
		if ( is_array( $value ) ) { $value = $value['url'] ?? null; }
		if ( ! is_string( $value ) ) { return null; }
		$value = esc_url_raw( $value, array( 'https', 'http' ) );
		return '' !== $value ? $value : null;
	};
	return array( 'host' => $host, 'icon_uri' => $asset( $payload['icon'] ?? null ), 'image_uri' => $asset( $payload['image'] ?? null ) );
}

/** Create the new body on a staging table; never overwrite an existing ID. */
function axismundi_actors_migrate_body() : bool {
	global $wpdb;
	$actors = $wpdb->prefix . 'ax_actors';
	$legacy = $wpdb->prefix . 'ax_identities';
	$next = $wpdb->prefix . 'ax_actors_next';
	$old = $wpdb->prefix . 'ax_actors_legacy';
	$meta = axismundi_actors_meta_table();
	$lock = 'ax_actor_body_' . md5( $wpdb->prefix );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $lock ) ) ) { return false; }
	try {
		if ( axismundi_actors_body_installed() ) { return axismundi_actors_finish_body_migration(); }
		if ( 0 !== (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$legacy} WHERE object_kind<>'actor'" ) || $wpdb->last_error ) { return false; }
		if ( 0 !== (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$legacy} i LEFT JOIN {$actors} a ON a.identity_id=i.id WHERE a.identity_id IS NULL" ) || $wpdb->last_error ) { return false; }
		if ( 0 !== (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$actors} a LEFT JOIN {$legacy} i ON i.id=a.identity_id WHERE i.id IS NULL" ) || $wpdb->last_error ) { return false; }
		$next_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $legacy ) );
		if ( $next_id < 1 ) { return false; }
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$meta} (
			meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) unsigned NOT NULL,
			meta_key varchar(191) NOT NULL,
			meta_value longtext NOT NULL,
			PRIMARY KEY  (meta_id),
			UNIQUE KEY actor_property (actor_id, meta_key)
		) ENGINE=InnoDB {$charset};" );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $next ) ) ) === $next ) {
			// This name is exclusively migration-owned and is never a live writer.
			if ( false === $wpdb->query( "DROP TABLE {$next}" ) ) { return false; }
		}
		if ( false === $wpdb->query( "CREATE TABLE {$next} LIKE {$actors}" ) ) { return false; }
		if ( false === $wpdb->query( "ALTER TABLE {$next}
			MODIFY identity_id bigint(20) unsigned DEFAULT NULL,
			DROP PRIMARY KEY,
			ADD id bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST,
			ADD uuid char(36) NOT NULL,
			ADD canonical_uri text NOT NULL,
			ADD canonical_uri_hash char(64) NOT NULL,
			ADD origin varchar(10) NOT NULL,
			ADD status varchar(12) NOT NULL,
			ADD object_kind varchar(20) GENERATED ALWAYS AS ('actor') STORED,
			ADD host varchar(255) NOT NULL DEFAULT '',
			ADD icon_uri text DEFAULT NULL,
			ADD image_uri text DEFAULT NULL,
			ADD UNIQUE KEY legacy_actor_id (identity_id),
			ADD UNIQUE KEY uuid (uuid),
			ADD UNIQUE KEY canonical_uri_hash (canonical_uri_hash),
			ADD KEY origin_status (origin,status),
			ADD KEY host (host),
			DROP COLUMN payload_json" ) ) { return false; }
		$columns = array_values( array_diff( $wpdb->get_col( "SHOW COLUMNS FROM {$actors}" ), array( 'payload_json' ) ) );
		$names = implode( ',', array_map( static fn( $name ) => '`' . $name . '`', $columns ) );
		$select = implode( ',', array_map( static fn( $name ) => 'a.`' . $name . '`', $columns ) );
		$wpdb->query( 'START TRANSACTION' );
		$anchors = $wpdb->get_results( "SELECT * FROM {$legacy} ORDER BY id FOR UPDATE", ARRAY_A );
		if ( $wpdb->last_error ) { $wpdb->query( 'ROLLBACK' ); return false; }
		if ( false === $wpdb->query( "INSERT INTO {$next} (id,uuid,canonical_uri,canonical_uri_hash,origin,status,{$names}) SELECT i.id,i.uuid,i.canonical_uri,i.canonical_uri_hash,i.origin,i.status,{$select} FROM {$legacy} i INNER JOIN {$actors} a ON a.identity_id=i.id" ) ) { $wpdb->query( 'ROLLBACK' ); return false; }
		$rows = $wpdb->get_results( "SELECT a.identity_id,a.payload_json,i.canonical_uri,i.updated_at AS identity_updated_at FROM {$actors} a INNER JOIN {$legacy} i ON i.id=a.identity_id FOR UPDATE", ARRAY_A );
		foreach ( $rows as $row ) {
			$id = (int) $row['identity_id'];
			$payload = null === $row['payload_json'] ? array() : json_decode( $row['payload_json'], true );
			$summary = axismundi_actors_body_summary( $row['canonical_uri'], is_array( $payload ) ? $payload : array() );
			if ( false === $wpdb->update( $next, $summary, array( 'id' => $id ) ) ) { $wpdb->query( 'ROLLBACK' ); return false; }
			if ( null !== $row['payload_json'] && ! axismundi_actors_store_meta( $id, 'remote_payload', $row['payload_json'] ) ) { $wpdb->query( 'ROLLBACK' ); return false; }
			if ( ! axismundi_actors_store_meta( $id, 'legacy_identity_updated_at', $row['identity_updated_at'] ) ) { $wpdb->query( 'ROLLBACK' ); return false; }
		}
		$bad = $wpdb->get_var( "SELECT COUNT(*) FROM {$legacy} i LEFT JOIN {$next} n ON n.id=i.id WHERE n.id IS NULL OR NOT (n.uuid<=>i.uuid) OR NOT (n.canonical_uri<=>i.canonical_uri) OR NOT (n.canonical_uri_hash<=>i.canonical_uri_hash) OR NOT (n.origin<=>i.origin) OR NOT (n.status<=>i.status)" );
		if ( null === $bad || (int) $bad || $wpdb->last_error ) { $wpdb->query( 'ROLLBACK' ); return false; }
		foreach ( $columns as $column ) {
			$bad = $wpdb->get_var( "SELECT COUNT(*) FROM {$actors} a INNER JOIN {$next} n ON n.id=a.identity_id WHERE NOT (BINARY n.`{$column}` <=> BINARY a.`{$column}`)" );
			if ( null === $bad || (int) $bad || $wpdb->last_error ) { $wpdb->query( 'ROLLBACK' ); return false; }
		}
		$bad = $wpdb->get_var( "SELECT COUNT(*) FROM {$actors} a LEFT JOIN {$meta} m ON m.actor_id=a.identity_id AND m.meta_key='remote_payload' WHERE a.payload_json IS NOT NULL AND NOT (BINARY a.payload_json <=> BINARY m.meta_value)" );
		if ( null === $bad || (int) $bad || $wpdb->last_error ) { $wpdb->query( 'ROLLBACK' ); return false; }
		if ( false === $wpdb->query( 'COMMIT' ) ) { return false; }
		// A deleted historical Actor can still be referenced by a grant. Never
		// restart allocation at MAX(live Actor ID), which would reuse that ID.
		if ( false === $wpdb->query( "ALTER TABLE {$next} AUTO_INCREMENT={$next_id}" ) ) { return false; }
		if ( false === $wpdb->query( "RENAME TABLE {$actors} TO {$old}, {$next} TO {$actors}" ) ) { return false; }
		return axismundi_actors_finish_body_migration();
	} finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
}

/** Resume after an interrupted atomic rename; remove only verified old tables. */
function axismundi_actors_finish_body_migration() : bool {
	global $wpdb;
	$actors = $wpdb->prefix . 'ax_actors';
	$legacy = $wpdb->prefix . 'ax_identities';
	$old = $wpdb->prefix . 'ax_actors_legacy';
	if ( ! axismundi_actors_body_installed() ) { return false; }
	$meta = axismundi_actors_meta_table();
	$bad = $wpdb->get_var( "SELECT COUNT(*) FROM {$actors} WHERE identity_id IS NULL OR identity_id<>id OR canonical_uri_hash<>SHA2(canonical_uri,256)" );
	if ( null === $bad || (int) $bad || $wpdb->last_error ) { return false; }
	foreach ( array( $legacy => 'id', $old => 'identity_id' ) as $table => $key ) {
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) { continue; }
		$bad = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} o LEFT JOIN {$actors} a ON a.id=o.{$key} WHERE a.id IS NULL OR a.identity_id<>a.id" );
		if ( null === $bad || (int) $bad || $wpdb->last_error ) { return false; }
		if ( $table === $legacy ) {
			$bad = $wpdb->get_var( "SELECT COUNT(*) FROM {$legacy} i INNER JOIN {$actors} a ON a.id=i.id WHERE i.object_kind<>'actor' OR NOT (BINARY i.uuid<=>BINARY a.uuid) OR NOT (BINARY i.canonical_uri<=>BINARY a.canonical_uri)" );
			if ( null === $bad || (int) $bad || $wpdb->last_error ) { return false; }
		}
	}
	update_option( 'ax_actors_body_version', '1', false );
	if ( '1' !== get_option( 'ax_actors_body_version' ) ) { return false; }
	$foreign_key = $wpdb->get_var( $wpdb->prepare( "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME='actor_id' AND REFERENCED_TABLE_NAME=%s", $meta, $actors ) );
	if ( null === $foreign_key && false === $wpdb->query( "ALTER TABLE {$meta} ADD FOREIGN KEY (actor_id) REFERENCES {$actors}(id) ON DELETE CASCADE" ) ) { return false; }
	foreach ( array( $legacy, $old ) as $table ) {
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table && false === $wpdb->query( "DROP TABLE {$table}" ) ) { return false; }
	}
	return true;
}
