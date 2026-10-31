<?php
/** JMAP-owned Todo proof-of-concept persistence. @package AxismundiJmap */
namespace Axismundi\Jmap\TodoPoc;

defined( 'ABSPATH' ) || exit;

function table( string $suffix ) : string {
	global $wpdb;
	if ( ! in_array( $suffix, array( 'principals', 'lists', 'todos', 'subscriptions', 'accounts', 'changes' ), true ) ) {
		throw new \InvalidArgumentException( 'Unknown Todo store table.' );
	}
	return $wpdb->prefix . 'ax_jmap_poc_' . $suffix;
}

/** Fail on database errors, including errors from read queries. */
function check_db() : void {
	global $wpdb;
	if ( $wpdb->last_error ) { throw new \RuntimeException( 'Todo storage operation failed: ' . $wpdb->last_error ); }
}

function db_execute( string $sql ) : int {
	global $wpdb;
	$result = $wpdb->query( $sql );
	check_db();
	if ( false === $result ) { throw new \RuntimeException( 'Todo storage operation failed.' ); }
	return (int) $result;
}

/** Schema only: call explicitly, never from a read or bootstrap repair path. */
function install() : void {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$id = 'varchar(64) CHARACTER SET ascii COLLATE ascii_bin';
	$definitions = array(
		'principals' => "id {$id} NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			actor_id bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id),
			UNIQUE KEY actor_id (actor_id)",
		'accounts' => "account_id {$id} NOT NULL,
			actor_id bigint(20) unsigned DEFAULT NULL,
			revision bigint(20) unsigned NOT NULL DEFAULT 0,
			acl_revision bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (account_id),
			UNIQUE KEY actor_id (actor_id)",
		'lists' => "id {$id} NOT NULL,
			account_id {$id} NOT NULL,
			name varchar(255) NOT NULL,
			shares_json longtext NOT NULL,
			revision bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY account_list (account_id,id)",
		'todos' => "id {$id} NOT NULL,
			account_id {$id} NOT NULL,
			list_id {$id} NOT NULL,
			title text NOT NULL,
			keywords_json longtext NOT NULL,
			revision bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY account_list (account_id,list_id)",
		'subscriptions' => "list_id {$id} NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			subscribed tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (list_id,user_id)",
		'changes' => "account_id {$id} NOT NULL,
			revision bigint(20) unsigned NOT NULL,
			type varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			object_id {$id} NOT NULL,
			operation varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			PRIMARY KEY  (account_id,revision)"
	);
	foreach ( $definitions as $suffix => $columns ) {
		$name = table( $suffix );
		dbDelta( "CREATE TABLE {$name} (\n{$columns}\n) ENGINE=InnoDB {$charset};" );
		check_db();
		$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $name ) );
		check_db();
		if ( 'InnoDB' !== $engine ) { throw new \RuntimeException( 'Todo storage requires InnoDB.' ); }
	}
	db_execute( 'UPDATE ' . table( 'accounts' ) . ' SET actor_id=CAST(SUBSTRING(account_id,2) AS UNSIGNED) WHERE actor_id IS NULL' );
	$fk_name = 'ax_todo_' . substr( md5( table( 'accounts' ) . ':actor_id' ), 0, 24 );
	$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=%s AND CONSTRAINT_NAME=%s', table( 'accounts' ), $fk_name ) );
	check_db();
	if ( null === $exists ) { db_execute( $wpdb->prepare( 'ALTER TABLE %i ADD CONSTRAINT %i FOREIGN KEY (actor_id) REFERENCES %i (actor_id) ON DELETE CASCADE', table( 'accounts' ), $fk_name, \Axismundi\Jmap\accounts_table() ) ); }
	// dbDelta does not manage foreign keys; add them separately and idempotently.
	foreach ( array(
		array( 'lists', 'account_id', 'accounts', 'account_id' ),
		array( 'todos', 'account_id,list_id', 'lists', 'account_id,id' ),
		array( 'subscriptions', 'list_id', 'lists', 'id' ),
		array( 'changes', 'account_id', 'accounts', 'account_id' ),
	) as [ $child, $columns, $parent, $keys ] ) {
		$name = 'ax_todo_' . substr( md5( table( $child ) . ':' . $columns ), 0, 24 );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=%s AND CONSTRAINT_NAME=%s AND CONSTRAINT_TYPE=%s', table( $child ), $name, 'FOREIGN KEY' ) );
		check_db();
		if ( null === $exists ) {
			db_execute( $wpdb->prepare( 'ALTER TABLE %i ADD CONSTRAINT %i FOREIGN KEY (' . $columns . ') REFERENCES %i (' . $keys . ') ON DELETE CASCADE', table( $child ), $name, table( $parent ) ) );
		}
	}
	update_option( 'ax_jmap_todo_poc_version', '1', false );
	if ( '1' !== get_option( 'ax_jmap_todo_poc_version' ) ) { throw new \RuntimeException( 'Could not record Todo schema version.' ); }
}

function live_account( string $account ) : bool {
	$actor = \Axismundi\Jmap\account_actor( $account );
	return $actor && $actor->is_local() && ! in_array( $actor->get_status(), array( 'disabled', 'tombstone' ), true );
}

function operator( string $account, int $user ) : bool {
	if ( $user <= 0 || ! live_account( $account ) ) { return false; }
	$actor = \Axismundi\Jmap\account_actor( $account );
	if ( function_exists( 'axismundi_actors_is_system_actor' ) && axismundi_actors_is_system_actor( $actor ) ) { return user_can( $user, 'manage_options' ); }
	// PoC policy: Account owners own their lists. Other operating grades need a resource share.
	return user_can( $user, 'read' ) && 'owner' === axismundi_actors_account_role( $actor->get_identity_id(), $user );
}

/** The two identity domains intentionally allocate different Principal rows. */
function mapped_principal( string $column, int $value, bool $create ) : ?string {
	global $wpdb;
	if ( ! in_array( $column, array( 'user_id', 'actor_id' ), true ) || $value <= 0 ) { return null; }
	$id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE %i=%d', table( 'principals' ), $column, $value ) );
	check_db();
	if ( null !== $id || ! $create ) { return $id; }
	$id = 'P' . wp_generate_uuid4();
	// A concurrent provisioner may win the unique mapping; preserve its opaque ID.
	db_execute( $wpdb->prepare( 'INSERT INTO %i (id,%i) VALUES (%s,%d) ON DUPLICATE KEY UPDATE id=id', table( 'principals' ), $column, $id, $value ) );
	return mapped_principal( $column, $value, false );
}

/** create=true is reserved for explicit enable/registration/admin workflows. */
function principal_for_user( int $user, bool $create = false ) : ?string {
	if ( $user <= 0 || ( $create && ! get_userdata( $user ) ) ) { return null; }
	return mapped_principal( 'user_id', $user, $create );
}

function principal_for_account( string $account, bool $create = false ) : ?string {
	$actor = \Axismundi\Jmap\account_actor( $account );
	if ( ! $actor || ( $create && ! live_account( $account ) ) ) { return null; }
	return mapped_principal( 'actor_id', (int) $actor->get_identity_id(), $create );
}

function get_principal_row( string $id ) : ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id,user_id,actor_id FROM %i WHERE id=%s', table( 'principals' ), $id ), ARRAY_A );
	check_db();
	return $row ?: null;
}

/** Explicit provisioning; callers enforce Account capability policy separately. */
function provision_todo_account( string $account ) : void {
	global $wpdb;
	if ( ! live_account( $account ) ) { throw new \InvalidArgumentException( 'A live local Account is required.' ); }
	principal_for_account( $account, true );
	foreach ( get_users( array( 'fields' => 'ID' ) ) as $user ) { principal_for_user( (int) $user, true ); }
	db_execute( $wpdb->prepare( 'INSERT INTO %i (account_id,actor_id) VALUES (%s,%d) ON DUPLICATE KEY UPDATE account_id=account_id', table( 'accounts' ), $account, (int) substr( $account, 1 ) ) );
}

function list_row( string $id, string $account ) : ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%s AND account_id=%s', table( 'lists' ), $id, $account ), ARRAY_A );
	check_db();
	return $row ?: null;
}

/** Resource rights only; capability policy remains an independent, deny-by-default gate. */
function rights( array $list, int $user ) : \stdClass {
	$names = array( 'mayRead', 'mayWrite', 'mayAdmin' );
	$result = (object) array_fill_keys( $names, false );
	$account = (string) ( $list['account_id'] ?? '' );
	if ( $user <= 0 || ! live_account( $account ) ) { return $result; }
	if ( operator( $account, $user ) ) { return (object) array_fill_keys( $names, true ); }
	$principal = principal_for_user( $user, false );
	if ( null === $principal ) { return $result; }
	$shares = json_decode( (string) ( $list['shares_json'] ?? '{}' ), false, 32, JSON_THROW_ON_ERROR );
	$grant = $shares instanceof \stdClass ? ( $shares->{$principal} ?? null ) : null;
	if ( ! $grant instanceof \stdClass || true !== ( $grant->mayRead ?? false ) ) { return $result; }
	foreach ( $names as $name ) { $result->{$name} = true === ( $grant->{$name} ?? false ); }
	return $result;
}

function visible_lists( string $account, int $user ) : array {
	global $wpdb;
	if ( $user <= 0 || ! live_account( $account ) ) { return array(); }
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE account_id=%s ORDER BY id', table( 'lists' ), $account ), ARRAY_A );
	check_db();
	return array_values( array_filter( $rows, static fn( array $row ) : bool => rights( $row, $user )->mayRead ) );
}

/** No implicit provisioning. Caller must commit()/rollback(); nested transactions are unsupported. */
function begin( string $account ) : void {
	global $wpdb;
	db_execute( 'START TRANSACTION' );
	try {
		// Serialize capability-policy revocation with data access without reading Actors storage.
		$policy_lock = $wpdb->get_var( $wpdb->prepare( 'SELECT actor_id FROM %i WHERE actor_id=%d FOR UPDATE', \Axismundi\Jmap\accounts_table(), (int) substr( $account, 1 ) ) );
		check_db();
		if ( null === $policy_lock ) { throw new \RuntimeException( 'JMAP Account is unavailable.' ); }
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT revision,acl_revision FROM %i WHERE account_id=%s FOR UPDATE', table( 'accounts' ), $account ), ARRAY_A );
		check_db();
		if ( ! $row ) { throw new \RuntimeException( 'Todo Account has not been enabled.' ); }
	} catch ( \Throwable $error ) {
		rollback();
		throw $error;
	}
}

function commit() : void { db_execute( 'COMMIT' ); }
function rollback() : void { db_execute( 'ROLLBACK' ); }

function read_counter( string $account, string $column ) : int {
	global $wpdb;
	if ( ! in_array( $column, array( 'revision', 'acl_revision' ), true ) ) { throw new \InvalidArgumentException( 'Unknown clock.' ); }
	$value = $wpdb->get_var( $wpdb->prepare( 'SELECT %i FROM %i WHERE account_id=%s', $column, table( 'accounts' ), $account ) );
	check_db();
	return null === $value ? 0 : (int) $value;
}

function read_clock( string $account ) : int { return read_counter( $account, 'revision' ); }
function read_acl_revision( string $account ) : int { return read_counter( $account, 'acl_revision' ); }

/** Call inside begin(account), only for share-map or subscription changes. */
function bump_acl_revision( string $account ) : int {
	global $wpdb;
	if ( 1 !== db_execute( $wpdb->prepare( 'UPDATE %i SET acl_revision=acl_revision+1 WHERE account_id=%s', table( 'accounts' ), $account ) ) ) {
		throw new \RuntimeException( 'Todo Account clock is missing.' );
	}
	return read_acl_revision( $account );
}

/** Call inside begin(account); data writes and change logging share the transaction. */
function touch( string $account, string $type, string $id, string $operation ) : int {
	global $wpdb;
	if ( '' === $type || strlen( $type ) > 64 || '' === $id || strlen( $id ) > 64 || ! in_array( $operation, array( 'create', 'update', 'destroy' ), true ) ) {
		throw new \InvalidArgumentException( 'Invalid Todo change.' );
	}
	if ( 1 !== db_execute( $wpdb->prepare( 'UPDATE %i SET revision=revision+1 WHERE account_id=%s', table( 'accounts' ), $account ) ) ) {
		throw new \RuntimeException( 'Todo Account clock is missing.' );
	}
	$revision = read_clock( $account );
	db_execute( $wpdb->prepare( 'INSERT INTO %i (account_id,revision,type,object_id,operation) VALUES (%s,%d,%s,%s,%s)', table( 'changes' ), $account, $revision, $type, $id, $operation ) );
	return $revision;
}
