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

const AXISMUNDI_ACTORS_DB_VERSION = '2';

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
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (identity_id),
			UNIQUE KEY local_handle_key (local_handle_key),
			UNIQUE KEY local_user_id (local_user_id),
			KEY preferred_username (preferred_username),
			KEY scope_type (actor_scope, actor_type)
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

	update_option( 'ax_actors_db_version', AXISMUNDI_ACTORS_DB_VERSION, false );
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
			'created_at'         => $now,
			'updated_at'         => $now,
		),
		array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
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
	$now  = current_time( 'mysql', true );
	$done = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$actors,
		array( 'preferred_username' => $key, 'local_handle_key' => $key, 'handle_locked_at' => $now, 'updated_at' => $now ),
		array( 'identity_id' => $identity_id ),
		array( '%s', '%s', '%s', '%s' ),
		array( '%d' )
	);
	return false === $done ? new WP_Error( 'ax_actors_handle_update', __( 'Could not register the handle.', 'axismundi-actors' ) ) : true;
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
