<?php
/**
 * Phase 1 — the actor repository: schema, value object, create / lookup / seed /
 * tombstone. Integrity is enforced here, not by a physical FOREIGN KEY: the
 * identity↔actor link is a **logical** FK keyed by identity_id, because ON DELETE
 * CASCADE would fight the tombstone contract (a deleted user must leave the identity
 * standing, tombstoned). See docs/DATA-MODEL.md, docs/PHASES.md Phase 1.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTORS_DB_VERSION = '6';

/** @return string identities table name. */
function axismundi_actors_identities_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_identities';
}

/** @return string actors table name. */
function axismundi_actors_actors_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_actors';
}

/** @return string multilingual actor text table name. */
function axismundi_actors_texts_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_actor_texts';
}

/** @return string actor address (handle / acct) ledger table name. */
function axismundi_actors_addresses_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_actor_addresses';
}

/** @return string remote instance (host / NodeInfo) ledger table name. */
function axismundi_actors_instances_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_instances';
}

/**
 * Hash of a host authority (the per-host key).
 *
 * @param string $host Host authority (host[:port]).
 * @return string sha-256 hex.
 */
function axismundi_actors_host_hash( string $host ) : string {
	return hash( 'sha256', strtolower( trim( $host ) ) );
}

/**
 * Namespace-scoped hash for an address. Local `local_handle` and `former_handle`
 * share the **`handle`** namespace, so a reserved former handle blocks a new handle
 * of the same string under `UNIQUE(address_hash)`. `acct:` addresses hash under their
 * own namespace and never collide with handles.
 *
 * @param string $type    Address type.
 * @param string $address Address value.
 * @return string sha-256 hex.
 */
function axismundi_actors_address_hash( string $type, string $address ) : string {
	$namespace = in_array( $type, array( 'local_handle', 'former_handle' ), true ) ? 'handle' : $type;
	return hash( 'sha256', $namespace . ':' . strtolower( trim( $address ) ) );
}

/**
 * Create/upgrade both tables (dbDelta) and record the schema version. Logical FK
 * only — no physical FOREIGN KEY (tombstone contract).
 *
 * @return void
 */
function axismundi_actors_install() : void {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$identities = axismundi_actors_identities_table();
	$actors     = axismundi_actors_actors_table();
	$texts      = axismundi_actors_texts_table();

	dbDelta(
		"CREATE TABLE {$identities} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uuid char(36) NOT NULL,
			canonical_uri text NOT NULL,
			canonical_uri_hash char(64) NOT NULL,
			object_kind varchar(20) NOT NULL,
			origin varchar(10) NOT NULL,
			status varchar(12) NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			UNIQUE KEY canonical_uri_hash (canonical_uri_hash),
			KEY kind_origin_status (object_kind, origin, status)
		) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$actors} (
			identity_id bigint(20) unsigned NOT NULL,
			actor_type varchar(16) NOT NULL,
			actor_scope varchar(8) DEFAULT NULL,
			preferred_username varchar(191) DEFAULT NULL,
			local_handle_key varchar(191) DEFAULT NULL,
			handle_locked_at datetime DEFAULT NULL,
			local_user_id bigint(20) unsigned DEFAULT NULL,
			display_name varchar(191) DEFAULT NULL,
			summary text DEFAULT NULL,
			profile_url text DEFAULT NULL,
			inbox_uri text DEFAULT NULL,
			outbox_uri text DEFAULT NULL,
			payload_json longtext DEFAULT NULL,
			avatar_attachment_id bigint(20) unsigned DEFAULT NULL,
			header_attachment_id bigint(20) unsigned DEFAULT NULL,
			default_language varchar(35) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (identity_id),
			UNIQUE KEY local_handle_key (local_handle_key),
			UNIQUE KEY local_user_id (local_user_id),
			KEY preferred_username (preferred_username),
			KEY scope_type (actor_scope, actor_type)
		) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$texts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			identity_id bigint(20) unsigned NOT NULL,
			field_name varchar(16) NOT NULL,
			language_tag varchar(35) NOT NULL,
			value longtext NOT NULL,
			media_type varchar(64) DEFAULT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY identity_field_language (identity_id, field_name, language_tag),
			KEY identity_language (identity_id, language_tag)
		) {$charset};"
	);

	$addresses = axismundi_actors_addresses_table();
	dbDelta(
		"CREATE TABLE {$addresses} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			identity_id bigint(20) unsigned NOT NULL,
			address_type varchar(16) NOT NULL,
			address varchar(191) NOT NULL,
			address_hash char(64) NOT NULL,
			status varchar(12) NOT NULL,
			verified_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			retired_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY address_hash (address_hash),
			KEY identity_status (identity_id, status)
		) {$charset};"
	);

	$instances = axismundi_actors_instances_table();
	dbDelta(
		"CREATE TABLE {$instances} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			host varchar(191) NOT NULL,
			host_hash char(64) NOT NULL,
			base_uri text NOT NULL,
			software_name varchar(64) DEFAULT NULL,
			software_version varchar(64) DEFAULT NULL,
			nodeinfo_schema varchar(191) DEFAULT NULL,
			name varchar(191) DEFAULT NULL,
			description text DEFAULT NULL,
			icon_uri text DEFAULT NULL,
			open_registrations tinyint(1) DEFAULT NULL,
			fetched_at datetime DEFAULT NULL,
			fetch_status varchar(12) DEFAULT NULL,
			payload_json longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY host_hash (host_hash)
		) {$charset};"
	);

	// dbDelta reliably ADDs columns but does NOT relax an existing NOT NULL to
	// nullable, so do that explicitly (idempotent — a no-op on a fresh install where
	// CREATE TABLE already made it nullable). Handle-less actors need a NULL handle.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off schema upgrade on a custom table.
	$wpdb->query( "ALTER TABLE {$actors} MODIFY preferred_username varchar(191) DEFAULT NULL" );

	// Backfill: any local actor that already carries a handle key predates the
	// immutability contract, so lock it (a handle key means it was assigned).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off upgrade backfill on a custom table.
	$wpdb->query( "UPDATE {$actors} SET handle_locked_at = updated_at WHERE local_handle_key IS NOT NULL AND handle_locked_at IS NULL" );

	// Existing local actors receive the site language as their scalar/map default,
	// but no translated text rows are synthesized from WP_User or site data.
	$site_language = function_exists( 'axismundi_actors_normalize_language_tag' )
		? axismundi_actors_normalize_language_tag( get_locale() )
		: str_replace( '_', '-', (string) get_locale() );
	if ( '' !== $site_language ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off schema upgrade backfill on a custom table.
		$wpdb->query( $wpdb->prepare( "UPDATE {$actors} SET default_language = %s WHERE default_language IS NULL AND actor_scope IN ('site','user')", $site_language ) );
	}

	// Backfill: every local actor that already has a handle gets a `primary`
	// `local_handle` address row (the routing ledger). Idempotent — keyed on the
	// namespaced address hash.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off backfill on a custom table.
	$handled = (array) $wpdb->get_results( "SELECT identity_id, local_handle_key FROM {$actors} WHERE local_handle_key IS NOT NULL", ARRAY_A );
	foreach ( $handled as $row ) {
		axismundi_actors_record_handle_address( (int) $row['identity_id'], (string) $row['local_handle_key'], 'primary' );
	}

	// Only record the schema version once the new columns/tables/indexes actually
	// exist, so a failed upgrade retries next load rather than being marked done.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$columns      = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$actors}" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$text_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$texts}" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$text_indexes = (array) $wpdb->get_col( "SHOW INDEX FROM {$texts} WHERE Key_name = 'identity_field_language'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$address_indexes = (array) $wpdb->get_col( "SHOW INDEX FROM {$addresses} WHERE Key_name = 'address_hash'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check on a custom table.
	$instance_indexes = (array) $wpdb->get_col( "SHOW INDEX FROM {$instances} WHERE Key_name = 'host_hash'" );
	if (
		in_array( 'avatar_attachment_id', $columns, true )
		&& in_array( 'header_attachment_id', $columns, true )
		&& in_array( 'default_language', $columns, true )
		&& in_array( 'language_tag', $text_columns, true )
		&& in_array( 'value', $text_columns, true )
		&& ! empty( $text_indexes )
		&& ! empty( $address_indexes )
		&& ! empty( $instance_indexes )
	) {
		update_option( 'ax_actors_db_version', AXISMUNDI_ACTORS_DB_VERSION, false );
	}
}

/**
 * Read-only actor value object (frozen surface, docs/PROJECTIONS.md §1.5). Holds a
 * joined identity+actor row; resolves display/profile live for local actors.
 */
final class Axismundi_Actor {

	/** @var array<string,mixed> Joined identity + actor row. */
	private array $row;

	/** @param array<string,mixed> $row Joined row. */
	private function __construct( array $row ) {
		$this->row = $row;
	}

	/**
	 * @param array<string,mixed> $row Joined identity + actor row.
	 * @return self
	 */
	public static function from_row( array $row ) : self {
		return new self( $row );
	}

	public function get_identity_id() : int {
		return (int) $this->row['id'];
	}

	public function get_uuid() : string {
		return (string) $this->row['uuid'];
	}

	public function get_uri() : string {
		return (string) $this->row['canonical_uri'];
	}

	public function get_preferred_username() : string {
		return (string) ( $this->row['preferred_username'] ?? '' );
	}

	public function is_handle_locked() : bool {
		return ! empty( $this->row['handle_locked_at'] );
	}

	public function get_avatar_attachment_id() : int {
		return (int) ( $this->row['avatar_attachment_id'] ?? 0 );
	}

	public function get_header_attachment_id() : int {
		return (int) ( $this->row['header_attachment_id'] ?? 0 );
	}

	public function get_default_language() : string {
		$language = (string) ( $this->row['default_language'] ?? '' );
		return function_exists( 'axismundi_actors_normalize_language_tag' )
			? axismundi_actors_normalize_language_tag( $language )
			: $language;
	}

	public function get_profile_url() : string {
		if ( $this->is_local() ) {
			$handle = $this->get_preferred_username();
			if ( '' === $handle ) {
				return ''; // A handle-less (not-yet-registered) local actor has no /@ alias.
			}
			return get_option( 'permalink_structure' )
				? home_url( '/@' . rawurlencode( $handle ) . '/' )
				: add_query_arg( 'ax_actor_handle', $handle, home_url( '/' ) );
		}
		return (string) ( $this->row['profile_url'] ?? '' );
	}

	public function get_local_user_id() : ?int {
		return null !== $this->row['local_user_id'] ? (int) $this->row['local_user_id'] : null;
	}

	public function get_type() : string {
		return (string) $this->row['actor_type'];
	}

	public function get_scope() : ?string {
		return null !== $this->row['actor_scope'] ? (string) $this->row['actor_scope'] : null;
	}

	public function get_status() : string {
		return (string) $this->row['status'];
	}

	public function is_local() : bool {
		return 'local' === (string) $this->row['origin'];
	}

	public function get_display_name() : string {
		if ( $this->is_local() && 'site' === $this->get_scope() ) {
			return (string) get_bloginfo( 'name' );
		}
		$uid = $this->get_local_user_id();
		if ( $this->is_local() && $uid ) {
			$name = (string) get_the_author_meta( 'display_name', $uid );
			return '' !== $name ? $name : (string) get_the_author_meta( 'user_login', $uid );
		}
		return (string) ( $this->row['display_name'] ?? $this->get_preferred_username() );
	}
}

/**
 * Whether a string is a valid LOCAL actor handle. The rule is stricter than
 * ActivityPub `preferredUsername` (which has no character rule) on purpose: local
 * handles must survive naive `@\w+` mention parsers on other servers. So lowercase
 * letters, digits, and underscores only (never hyphens or dots — `@kim-jiwoon`
 * would be split to `@kim`), 1–30 chars, no leading/trailing underscore. See
 * docs/DATA-MODEL.md §6 (mention-interop policy).
 *
 * @param string $key Candidate handle.
 * @return bool
 */
function axismundi_actors_is_valid_handle( string $key ) : bool {
	return 1 === preg_match( '/^[a-z0-9](?:[a-z0-9_]{0,28}[a-z0-9])?$/', $key );
}

/**
 * Turn arbitrary text into a *suggested* handle (candidate stage only). Lowercase,
 * fold hyphens/dots/spaces/other to underscore, collapse and trim underscores, cap
 * at 30. Returns '' if nothing valid remains. This transform never runs at final
 * registration — the handle is immutable, so `register_handle()` validates without
 * silently changing what the user confirmed.
 *
 * @param string $raw Raw input.
 * @return string
 */
function axismundi_actors_suggest_handle( string $raw ) : string {
	$key = strtolower( remove_accents( $raw ) );
	$key = (string) preg_replace( '/[^a-z0-9_]+/', '_', $key );
	$key = (string) preg_replace( '/_+/', '_', $key );
	$key = trim( $key, '_' );
	$key = trim( substr( $key, 0, 30 ), '_' );
	return axismundi_actors_is_valid_handle( $key ) ? $key : '';
}

/**
 * Handles that would shadow routing or a reserved actor.
 *
 * @param string $key Normalized handle.
 * @return bool
 */
function axismundi_actors_is_reserved_handle( string $key ) : bool {
	$reserved = array( 'actors', 'ap', 'author', 'media', 'notes', 'feed', 'admin', 'login', 'wp', 'wp_admin', 'wp_json', 'wp_login' );
	return in_array( $key, $reserved, true );
}

/**
 * A local handle key not already taken (or reserved), suffixing on collision with an
 * underscore (never a hyphen — see the handle rule).
 *
 * @param string $base Desired handle.
 * @return string
 */
function axismundi_actors_unique_local_handle( string $base ) : string {
	global $wpdb;
	$key = axismundi_actors_suggest_handle( $base );
	if ( '' === $key || axismundi_actors_is_reserved_handle( $key ) ) {
		$key = 'actor';
	}
	$actors    = axismundi_actors_actors_table();
	$candidate = $key;
	$i         = 2;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table existence probe.
	while ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$actors} WHERE local_handle_key = %s", $candidate ) ) > 0 ) {
		$candidate = substr( $key, 0, 27 ) . '_' . $i;
		++$i;
	}
	return $candidate;
}

/**
 * Create a local actor (identity + actor rows) in one transaction. Remote actors
 * are a later phase; this path always sets origin=local and a local_handle_key.
 *
 * @param array<string,mixed> $args actor_type, actor_scope, preferred_username, local_user_id.
 * @return Axismundi_Actor|WP_Error
 */
function axismundi_actors_create_local( array $args ) {
	global $wpdb;
	$type  = (string) ( $args['actor_type'] ?? 'Person' );
	$scope = isset( $args['actor_scope'] ) ? (string) $args['actor_scope'] : null;
	$uid   = isset( $args['local_user_id'] ) ? (int) $args['local_user_id'] : null;

	$uuid   = wp_generate_uuid4();
	$uri    = home_url( '/actors/' . $uuid );
	$hash   = hash( 'sha256', $uri );
	$now    = current_time( 'mysql', true );
	$language = function_exists( 'axismundi_actors_normalize_language_tag' )
		? axismundi_actors_normalize_language_tag( get_locale() )
		: str_replace( '_', '-', (string) get_locale() );

	// A handle is OPTIONAL at creation. When one is given (e.g. the site actor
	// seed) it is uniquified, stored as the alias == routing key, and locked. A
	// user Person is created handle-less; the handle is registered once, later, at
	// account activation and is then immutable (docs/DATA-MODEL §6, PHASES 2.1).
	$provided = trim( (string) ( $args['preferred_username'] ?? '' ) );
	if ( '' !== $provided ) {
		$handle_key   = axismundi_actors_unique_local_handle( $provided );
		$username     = $handle_key;
		$handle_locked = $now;
	} else {
		$handle_key    = null;
		$username      = null;
		$handle_locked = null;
	}

	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		axismundi_actors_identities_table(),
		array(
			'uuid'               => $uuid,
			'canonical_uri'      => $uri,
			'canonical_uri_hash' => $hash,
			'object_kind'        => 'actor',
			'origin'             => 'local',
			'status'             => (string) ( $args['status'] ?? 'internal' ),
			'created_at'         => $now,
			'updated_at'         => $now,
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
	if ( false === $ok ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return new WP_Error( 'ax_actors_identity_insert', __( 'Could not create the identity.', 'axismundi-actors' ) );
	}
	$identity_id = (int) $wpdb->insert_id;

	$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		axismundi_actors_actors_table(),
		array(
			'identity_id'        => $identity_id,
			'actor_type'         => $type,
			'actor_scope'        => $scope,
			'preferred_username' => $username,
			'local_handle_key'   => $handle_key,
			'handle_locked_at'   => $handle_locked,
			'local_user_id'      => $uid,
			'default_language'   => '' !== $language ? $language : null,
			'created_at'         => $now,
			'updated_at'         => $now,
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
	);
	if ( false === $ok ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return new WP_Error( 'ax_actors_actor_insert', __( 'Could not create the actor.', 'axismundi-actors' ) );
	}

	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return axismundi_actors_get_by_uuid( $uuid );
}

/**
 * Hydrate one actor from a WHERE clause on the joined tables.
 *
 * @param string $where   Prepared WHERE (without the keyword).
 * @param mixed  ...$args  Prepare args.
 * @return Axismundi_Actor|null
 */
function axismundi_actors_query_one( string $where, ...$args ) : ?Axismundi_Actor {
	global $wpdb;
	$identities = axismundi_actors_identities_table();
	$actors     = axismundi_actors_actors_table();
	$sql        = "SELECT i.*, a.* FROM {$identities} i INNER JOIN {$actors} a ON a.identity_id = i.id WHERE {$where} LIMIT 1";
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $where is a caller-fixed clause; args are prepared.
	$row = $wpdb->get_row( $args ? $wpdb->prepare( $sql, ...$args ) : $sql, ARRAY_A );
	return $row ? Axismundi_Actor::from_row( $row ) : null;
}

/**
 * @param string $uuid Identity UUID.
 * @return Axismundi_Actor|null
 */
function axismundi_actors_get_by_uuid( string $uuid ) : ?Axismundi_Actor {
	return axismundi_actors_query_one( 'i.uuid = %s', $uuid );
}

/**
 * @param string $uri Canonical URI.
 * @return Axismundi_Actor|null
 */
function axismundi_actors_get_by_uri( string $uri ) : ?Axismundi_Actor {
	return axismundi_actors_query_one( 'i.canonical_uri_hash = %s', hash( 'sha256', $uri ) );
}

/**
 * Resolve one local actor by its mutable human handle.
 *
 * Remote preferred usernames are intentionally excluded: they are not locally
 * unique and remain addressable only by their canonical remote URI.
 *
 * @param string $handle Human handle.
 * @return Axismundi_Actor|null
 */
function axismundi_actors_get_by_handle( string $handle ) : ?Axismundi_Actor {
	$key = strtolower( trim( $handle ) );
	return '' !== $key
		? axismundi_actors_query_one( 'a.local_handle_key = %s AND i.origin = %s', $key, 'local' )
		: null;
}

/**
 * @param int $user_id WP user ID.
 * @return Axismundi_Actor|null
 */
function axismundi_actors_get_for_user( int $user_id ) : ?Axismundi_Actor {
	return $user_id > 0 ? axismundi_actors_query_one( 'a.local_user_id = %d', $user_id ) : null;
}

/**
 * Insert or refresh a normalized remote Actor snapshot. HTTP discovery and JSON
 * validation happen outside this repository; this function owns only persistence.
 *
 * @param array<string,mixed> $record Normalized remote Actor fields.
 * @return Axismundi_Actor|WP_Error
 */
function axismundi_actors_upsert_remote( array $record ) {
	global $wpdb;
	$uri  = trim( (string) ( $record['uri'] ?? '' ) );
	$type = (string) ( $record['actor_type'] ?? '' );
	if ( 'https' !== strtolower( (string) wp_parse_url( $uri, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $uri ) || ! in_array( $type, array( 'Person', 'Organization', 'Application', 'Service', 'Group' ), true ) ) {
		return new WP_Error( 'ax_actors_remote_record', __( 'Invalid remote Actor record.', 'axismundi-actors' ) );
	}
	$payload = $record['payload'] ?? null;
	if ( ! is_array( $payload ) ) {
		return new WP_Error( 'ax_actors_remote_payload', __( 'Invalid remote Actor payload.', 'axismundi-actors' ) );
	}
	$payload_json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( false === $payload_json ) {
		return new WP_Error( 'ax_actors_remote_payload', __( 'Could not encode the remote Actor payload.', 'axismundi-actors' ) );
	}
	$now      = current_time( 'mysql', true );
	$existing = axismundi_actors_get_by_uri( $uri );
	$fields   = array(
		'actor_type'         => $type,
		'actor_scope'        => null,
		'preferred_username' => (string) ( $record['preferred_username'] ?? '' ),
		'local_handle_key'   => null,
		'handle_locked_at'   => null,
		'local_user_id'      => null,
		'display_name'       => (string) ( $record['display_name'] ?? '' ),
		'summary'            => (string) ( $record['summary'] ?? '' ),
		'profile_url'        => (string) ( $record['profile_url'] ?? '' ),
		'inbox_uri'          => (string) ( $record['inbox_uri'] ?? '' ),
		'outbox_uri'         => (string) ( $record['outbox_uri'] ?? '' ),
		'payload_json'       => $payload_json,
		'updated_at'         => $now,
	);
	if ( $existing ) {
		if ( $existing->is_local() || 'tombstone' === $existing->get_status() ) {
			return new WP_Error( 'ax_actors_remote_conflict', __( 'That identity cannot be refreshed as a remote Actor.', 'axismundi-actors' ) );
		}
		$done = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom actor snapshot table.
			axismundi_actors_actors_table(),
			$fields,
			array( 'identity_id' => $existing->get_identity_id() ),
			null,
			array( '%d' )
		);
		if ( false === $done ) {
			return new WP_Error( 'ax_actors_remote_update', __( 'Could not refresh the remote Actor.', 'axismundi-actors' ) );
		}
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom identity table.
			axismundi_actors_identities_table(),
			array( 'updated_at' => $now ),
			array( 'id' => $existing->get_identity_id() ),
			array( '%s' ),
			array( '%d' )
		);
		return axismundi_actors_get_by_uri( $uri );
	}

	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom identity table.
		axismundi_actors_identities_table(),
		array(
			'uuid'               => wp_generate_uuid4(),
			'canonical_uri'      => $uri,
			'canonical_uri_hash' => hash( 'sha256', $uri ),
			'object_kind'        => 'actor',
			'origin'             => 'remote',
			'status'             => 'public',
			'created_at'         => $now,
			'updated_at'         => $now,
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
	if ( false === $inserted ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return new WP_Error( 'ax_actors_remote_identity', __( 'Could not create the remote identity.', 'axismundi-actors' ) );
	}
	$identity_id          = (int) $wpdb->insert_id;
	$fields['identity_id'] = $identity_id;
	$fields['created_at']  = $now;
	$inserted = $wpdb->insert( axismundi_actors_actors_table(), $fields ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom actor snapshot table.
	if ( false === $inserted ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return new WP_Error( 'ax_actors_remote_actor', __( 'Could not create the remote Actor.', 'axismundi-actors' ) );
	}
	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return axismundi_actors_get_by_uri( $uri );
}

/**
 * @param int $identity_id Identity/actor key.
 * @return Axismundi_Actor|null
 */
function axismundi_actors_get_by_identity( int $identity_id ) : ?Axismundi_Actor {
	return $identity_id > 0 ? axismundi_actors_query_one( 'i.id = %d', $identity_id ) : null;
}

/**
 * Set (attachment id) or clear (0) an actor's avatar or header image. The shared
 * gate for both admin surfaces: it must be an image attachment the viewer may edit,
 * and the `axismundi_actors_can_use_profile_media` filter must allow it (the seam
 * for a future Media Library private/sensitive policy). Clearing only nulls the
 * reference — the attachment file is never deleted.
 *
 * @param Axismundi_Actor $actor         Target actor.
 * @param string          $role          avatar | header.
 * @param int             $attachment_id Attachment id, or 0 to clear.
 * @param int|null        $viewer        Acting user; defaults to current.
 * @return true|WP_Error
 */
function axismundi_actors_set_profile_media( Axismundi_Actor $actor, string $role, int $attachment_id, ?int $viewer = null ) {
	global $wpdb;
	if ( ! in_array( $role, array( 'avatar', 'header' ), true ) ) {
		return new WP_Error( 'ax_actors_media_role', __( 'Unknown media role.', 'axismundi-actors' ) );
	}
	$column = 'avatar' === $role ? 'avatar_attachment_id' : 'header_attachment_id';
	$viewer = null === $viewer ? get_current_user_id() : $viewer;
	$value  = null;
	if ( $attachment_id > 0 ) {
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error( 'ax_actors_media_attachment', __( 'That is not an attachment.', 'axismundi-actors' ) );
		}
		if ( 0 !== strpos( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
			return new WP_Error( 'ax_actors_media_image', __( 'The avatar and header must be images.', 'axismundi-actors' ) );
		}
		if ( ! user_can( $viewer, 'edit_post', $attachment_id ) ) {
			return new WP_Error( 'ax_actors_media_cap', __( 'You cannot use that image.', 'axismundi-actors' ) );
		}
		/**
		 * Allow or deny an attachment as an actor avatar / header. A future Media
		 * Library integration can deny private or sensitive media here.
		 *
		 * @param bool            $allowed       Whether it may be used.
		 * @param int             $attachment_id Attachment id.
		 * @param Axismundi_Actor $actor         Target actor.
		 * @param string          $role          avatar | header.
		 */
		if ( ! (bool) apply_filters( 'axismundi_actors_can_use_profile_media', true, $attachment_id, $actor, $role ) ) {
			return new WP_Error( 'ax_actors_media_denied', __( 'That image cannot be used here.', 'axismundi-actors' ) );
		}
		$value = $attachment_id;
	}
	$done = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		axismundi_actors_actors_table(),
		array( $column => $value, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'identity_id' => $actor->get_identity_id() ),
		array( '%d', '%s' ),
		array( '%d' )
	);
	return false === $done ? new WP_Error( 'ax_actors_media_save', __( 'Could not save the image.', 'axismundi-actors' ) ) : true;
}

/**
 * Release avatar / header references to a deleted attachment (logical cleanup; the
 * attachment itself is deleted by core, not here).
 *
 * @param int $post_id Deleted attachment id.
 * @return void
 */
function axismundi_actors_clear_deleted_attachment( int $post_id ) : void {
	global $wpdb;
	$actors = axismundi_actors_actors_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
	$wpdb->query( $wpdb->prepare( "UPDATE {$actors} SET avatar_attachment_id = NULL WHERE avatar_attachment_id = %d", $post_id ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
	$wpdb->query( $wpdb->prepare( "UPDATE {$actors} SET header_attachment_id = NULL WHERE header_attachment_id = %d", $post_id ) );
}
add_action( 'delete_attachment', 'axismundi_actors_clear_deleted_attachment' );

/**
 * The single local site actor, if seeded.
 *
 * @return Axismundi_Actor|null
 */
function axismundi_actors_get_site_actor() : ?Axismundi_Actor {
	return axismundi_actors_query_one( "a.actor_scope = 'site' AND i.origin = 'local'" );
}

/**
 * Return the user's local Person actor, creating an internal, **handle-less** one
 * if absent. The handle is not assigned here: it is registered once, later, when
 * the user activates their actor account (axismundi_actors_register_handle), and is
 * immutable thereafter. A handle-less internal actor is still a valid identity /
 * membership key.
 *
 * @param int $user_id WP user ID.
 * @return Axismundi_Actor|WP_Error
 */
function axismundi_actors_ensure_for_user( int $user_id ) {
	$existing = axismundi_actors_get_for_user( $user_id );
	if ( $existing ) {
		return $existing;
	}
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return new WP_Error( 'ax_actors_no_user', __( 'No such user.', 'axismundi-actors' ) );
	}
	return axismundi_actors_create_local(
		array(
			'actor_type'    => 'Person',
			'actor_scope'   => 'user',
			'local_user_id' => $user_id,
			'status'        => 'internal',
		)
	);
}

/**
 * Suggested handles for the activation UI. Never `user_login` (it may be an email
 * or a login identifier); `user_nicename` and the nickname are the safe candidates.
 *
 * @param int $user_id WP user ID.
 * @return string[] Normalized, de-duplicated candidate handles.
 */
function axismundi_actors_handle_candidates( int $user_id ) : array {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return array();
	}
	$out = array();
	foreach ( array( $user->user_nicename, get_user_meta( $user_id, 'nickname', true ) ) as $raw ) {
		$key = axismundi_actors_suggest_handle( (string) $raw );
		if ( '' !== $key && ! axismundi_actors_is_reserved_handle( $key ) && ! in_array( $key, $out, true ) ) {
			$out[] = $key;
		}
	}
	return $out;
}

/**
 * Register a local actor's handle **once**, at account activation. The handle is
 * then immutable: a second call on a locked actor is refused. This is deliberately
 * NOT a rename API — an exceptional change is a future admin recovery + alias/Move
 * concern (docs/DATA-MODEL §6, SECURITY §3).
 *
 * @param int    $identity_id Identity/actor key.
 * @param string $handle      Desired handle.
 * @return true|WP_Error
 */
/**
 * The identity that owns a local handle string in the address ledger — as its
 * `primary`, `reserved` (a retired handle held for the same actor), or `redirect`
 * address — or 0. This is the reservation check: a retired handle is never recycled
 * to a different actor.
 *
 * @param string $handle_key Normalized handle.
 * @return int Identity id, or 0.
 */
function axismundi_actors_handle_owner( string $handle_key ) : int {
	global $wpdb;
	$addresses = axismundi_actors_addresses_table();
	$hash      = axismundi_actors_address_hash( 'local_handle', $handle_key );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT identity_id FROM {$addresses} WHERE address_hash = %s AND status IN ('primary','reserved','redirect') LIMIT 1", $hash ) );
}

/**
 * Upsert an actor's local handle address row (routing ledger).
 *
 * @param int    $identity_id Identity id.
 * @param string $handle_key  Normalized handle.
 * @param string $status      primary | reserved | redirect | alias.
 * @return void
 */
function axismundi_actors_record_handle_address( int $identity_id, string $handle_key, string $status = 'primary' ) : void {
	global $wpdb;
	$addresses = axismundi_actors_addresses_table();
	$hash      = axismundi_actors_address_hash( 'local_handle', $handle_key );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
	$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$addresses} WHERE address_hash = %s", $hash ) );
	$type     = 'primary' === $status ? 'local_handle' : 'former_handle';
	if ( $existing > 0 ) {
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$addresses,
			array( 'address_type' => $type, 'status' => $status, 'retired_at' => 'primary' === $status ? null : current_time( 'mysql', true ) ),
			array( 'id' => $existing ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		return;
	}
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$addresses,
		array(
			'identity_id'  => $identity_id,
			'address_type' => $type,
			'address'      => $handle_key,
			'address_hash' => $hash,
			'status'       => $status,
			'created_at'   => current_time( 'mysql', true ),
			'retired_at'   => 'primary' === $status ? null : current_time( 'mysql', true ),
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
}

/**
 * Reserve a (former) handle to an actor so no other actor can ever take it — the
 * infrastructure for a future site-actor rename / admin recovery. Fails if the handle
 * belongs to a different actor.
 *
 * @param int    $identity_id Identity id.
 * @param string $handle      Handle to reserve.
 * @return true|WP_Error
 */
function axismundi_actors_reserve_former_handle( int $identity_id, string $handle ) {
	$key = strtolower( trim( $handle ) );
	if ( ! axismundi_actors_is_valid_handle( $key ) ) {
		return new WP_Error( 'ax_actors_handle', __( 'Invalid handle.', 'axismundi-actors' ) );
	}
	$owner = axismundi_actors_handle_owner( $key );
	if ( $owner > 0 && $owner !== $identity_id ) {
		return new WP_Error( 'ax_actors_handle_reserved', __( 'That handle belongs to another actor.', 'axismundi-actors' ) );
	}
	axismundi_actors_record_handle_address( $identity_id, $key, 'reserved' );
	return true;
}

/**
 * An identity's address ledger rows.
 *
 * @param int $identity_id Identity id.
 * @return array<int,array<string,mixed>>
 */
function axismundi_actors_get_addresses( int $identity_id ) : array {
	global $wpdb;
	$addresses = axismundi_actors_addresses_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$addresses} WHERE identity_id = %d", $identity_id ), ARRAY_A );
}

/**
 * Record the locally authoritative acct address for an actor. The local server
 * controls this address, so it is verified at write time; remote acct addresses
 * remain a later HTTPS WebFinger-discovery concern.
 *
 * @param int    $identity_id Identity id.
 * @param string $acct        Bare acct address (`handle@example.test`).
 * @return bool
 */
function axismundi_actors_record_verified_acct_address( int $identity_id, string $acct ) : bool {
	global $wpdb;
	$acct = strtolower( trim( $acct ) );
	if ( $identity_id <= 0 || '' === $acct ) {
		return false;
	}
	$addresses = axismundi_actors_addresses_table();
	$hash      = axismundi_actors_address_hash( 'acct', $acct );
	$now       = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom address ledger.
	$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$addresses} WHERE address_hash = %s", $hash ) );
	if ( $existing > 0 ) {
		$owner = (int) $wpdb->get_var( $wpdb->prepare( "SELECT identity_id FROM {$addresses} WHERE id = %d", $existing ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom address ledger.
		if ( $owner !== $identity_id ) {
			return false;
		}
		$done = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$addresses,
			array( 'address_type' => 'acct', 'address' => $acct, 'status' => 'primary', 'verified_at' => $now, 'retired_at' => null ),
			array( 'id' => $existing ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		return false !== $done;
	}
	$done = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$addresses,
		array(
			'identity_id'  => $identity_id,
			'address_type' => 'acct',
			'address'      => $acct,
			'address_hash' => $hash,
			'status'       => 'primary',
			'verified_at'  => $now,
			'created_at'   => $now,
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
	return false !== $done;
}

/**
 * Record a locally authoritative acct address.
 *
 * @param int    $identity_id Identity id.
 * @param string $acct        Bare acct address.
 * @return bool
 */
function axismundi_actors_record_local_acct_address( int $identity_id, string $acct ) : bool {
	return axismundi_actors_record_verified_acct_address( $identity_id, $acct );
}

function axismundi_actors_register_handle( int $identity_id, string $handle ) {
	global $wpdb;
	$actors = axismundi_actors_actors_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
	$locked = $wpdb->get_var( $wpdb->prepare( "SELECT handle_locked_at FROM {$actors} WHERE identity_id = %d", $identity_id ) );
	if ( ! empty( $locked ) ) {
		return new WP_Error( 'ax_actors_handle_locked', __( 'This handle is already set and cannot be changed.', 'axismundi-actors' ) );
	}
	// Case-fold only; every other character must already conform (no silent rewrite
	// of an immutable handle).
	$key = strtolower( trim( $handle ) );
	if ( ! axismundi_actors_is_valid_handle( $key ) ) {
		return new WP_Error( 'ax_actors_handle', __( 'Handles use lowercase letters, numbers, and underscores, 1–30 characters, with no leading or trailing underscore.', 'axismundi-actors' ) );
	}
	if ( axismundi_actors_is_reserved_handle( $key ) ) {
		return new WP_Error( 'ax_actors_handle_reserved', __( 'That handle is reserved.', 'axismundi-actors' ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
	$taken = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$actors} WHERE local_handle_key = %s AND identity_id <> %d", $key, $identity_id ) );
	if ( $taken > 0 ) {
		return new WP_Error( 'ax_actors_handle_taken', __( 'That handle is already in use.', 'axismundi-actors' ) );
	}
	// Reservation ledger: a handle retired by another actor is never recycled.
	$owner = axismundi_actors_handle_owner( $key );
	if ( $owner > 0 && $owner !== $identity_id ) {
		return new WP_Error( 'ax_actors_handle_reserved', __( 'That handle is reserved to another actor.', 'axismundi-actors' ) );
	}
	$now  = current_time( 'mysql', true );
	$done = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$actors,
		array( 'preferred_username' => $key, 'local_handle_key' => $key, 'handle_locked_at' => $now, 'updated_at' => $now ),
		array( 'identity_id' => $identity_id ),
		array( '%s', '%s', '%s', '%s' ),
		array( '%d' )
	);
	if ( false === $done ) {
		return new WP_Error( 'ax_actors_handle_update', __( 'Could not register the handle.', 'axismundi-actors' ) );
	}
	axismundi_actors_record_handle_address( $identity_id, $key, 'primary' );
	do_action( 'axismundi_actors_handle_registered', $identity_id, $key );
	return true;
}

/**
 * A cached remote instance row by host, or null.
 *
 * @param string $host Host authority.
 * @return array<string,mixed>|null
 */
function axismundi_actors_get_instance( string $host ) : ?array {
	global $wpdb;
	$instances = axismundi_actors_instances_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$instances} WHERE host_hash = %s", axismundi_actors_host_hash( $host ) ), ARRAY_A );
	return $row ?: null;
}

/**
 * Upsert a remote instance row keyed on host_hash. `$fields` may include
 * software_name / software_version / nodeinfo_schema / name / description / icon_uri
 * / open_registrations / fetch_status. Always stamps `fetched_at` / `updated_at`.
 *
 * @param string              $host   Host authority.
 * @param array<string,mixed> $fields Instance fields.
 * @return void
 */
function axismundi_actors_upsert_instance( string $host, array $fields ) : void {
	global $wpdb;
	$instances = axismundi_actors_instances_table();
	$hash      = axismundi_actors_host_hash( $host );
	$now       = current_time( 'mysql', true );
	$allowed   = array( 'software_name', 'software_version', 'nodeinfo_schema', 'name', 'description', 'icon_uri', 'open_registrations', 'fetch_status', 'payload_json' );
	$data      = array_intersect_key( $fields, array_flip( $allowed ) );
	$data['fetched_at'] = $now;
	$data['updated_at'] = $now;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table.
	$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$instances} WHERE host_hash = %s", $hash ) );
	if ( $existing > 0 ) {
		$wpdb->update( $instances, $data, array( 'id' => $existing ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return;
	}
	$data['host']       = strtolower( trim( $host ) );
	$data['host_hash']  = $hash;
	$data['base_uri']   = 'https://' . strtolower( trim( $host ) ) . '/';
	$data['created_at'] = $now;
	$wpdb->insert( $instances, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

/**
 * Set an identity's status (internal|public|disabled|tombstone).
 *
 * @param int    $identity_id Identity key.
 * @param string $status      New status.
 * @return bool
 */
function axismundi_actors_set_status( int $identity_id, string $status ) : bool {
	global $wpdb;
	if ( ! in_array( $status, array( 'internal', 'public', 'disabled', 'tombstone' ), true ) ) {
		return false;
	}
	$done = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		axismundi_actors_identities_table(),
		array( 'status' => $status, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'id' => $identity_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
	if ( false !== $done ) {
		do_action( 'axismundi_actors_status_changed', $identity_id, $status );
	}
	return false !== $done;
}

/**
 * Set a local actor's ActivityStreams type (e.g. the site actor Application ↔
 * Organization). Person/user actors keep `Person`.
 *
 * @param int    $identity_id Identity key.
 * @param string $type        Person | Organization | Application | Service | Group.
 * @return bool
 */
function axismundi_actors_set_actor_type( int $identity_id, string $type ) : bool {
	global $wpdb;
	if ( ! in_array( $type, array( 'Person', 'Organization', 'Application', 'Service', 'Group' ), true ) ) {
		return false;
	}
	$done = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		axismundi_actors_actors_table(),
		array( 'actor_type' => $type, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'identity_id' => $identity_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
	return false !== $done;
}

/**
 * Idempotently seed the always-present site actor, and — only when the activating
 * user is a valid administrator — the site-owner Person actor. Never depends on a
 * specific account existing (docs/SPEC.md §4).
 *
 * @return void
 */
function axismundi_actors_seed() : void {
	if ( ! axismundi_actors_get_site_actor() ) {
		$type = (string) get_option( 'ax_actors_site_actor_type', 'Application' );
		axismundi_actors_create_local(
			array(
				'actor_type'         => in_array( $type, array( 'Application', 'Organization' ), true ) ? $type : 'Application',
				'actor_scope'        => 'site',
				'preferred_username' => 'blog',
				'status'             => 'internal',
			)
		);
	}
	$uid = get_current_user_id();
	if ( $uid > 0 && user_can( $uid, 'manage_options' ) ) {
		axismundi_actors_ensure_for_user( $uid );
		if ( false === get_option( 'ax_actors_site_owner_user_id', false ) ) {
			update_option( 'ax_actors_site_owner_user_id', $uid, false );
		}
	}
}

/**
 * On user deletion, tombstone the linked identity — never delete the actor
 * (federation identity and back-references must survive).
 *
 * @param int $user_id Deleted user ID.
 * @return void
 */
function axismundi_actors_tombstone_for_user( int $user_id ) : void {
	$actor = axismundi_actors_get_for_user( $user_id );
	if ( $actor ) {
		axismundi_actors_set_status( $actor->get_identity_id(), 'tombstone' );
	}
}
add_action( 'deleted_user', 'axismundi_actors_tombstone_for_user' );
