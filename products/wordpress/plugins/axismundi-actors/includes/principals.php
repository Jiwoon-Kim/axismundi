<?php
/** Typed access subjects; authentication and JMAP wire representations remain separate. @package AxismundiActors */
defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTORS_PRINCIPAL_SCHEMA_VERSION = '1';

/** Roles are independent of subject namespace and Actor type. */
function axismundi_actors_principal_roles() : array {
	return array( 'owner' => 3, 'manager' => 2, 'editor' => 1 );
}

function axismundi_actors_principal_role_rank( string $role ) : int {
	return (int) ( axismundi_actors_principal_roles()[ $role ] ?? 0 );
}

/** Creation boundary: a new Account must start with a live direct WP User anchor. */
function axismundi_actors_create_principal_account( array $args, string $owner_kind, int $owner_id ) {
	if ( 'wp_user' !== $owner_kind || ! axismundi_actors_resolve_principal( $owner_kind, $owner_id ) ) {
		return new WP_Error( 'ax_actors_account_owner', 'A new Account requires a live direct WordPress User owner.' );
	}
	$args['initial_owner_user_id'] = $owner_id;
	return axismundi_actors_create_local( $args );
}

/** Internal creation-only WP login boundary, inside create_local's transaction. Never updates a grant. */
function axismundi_actors_seed_account_owner( int $account_id, int $user_id ) {
	if ( ! axismundi_actors_principals_ready() || $account_id <= 0 || ! axismundi_actors_resolve_principal( 'wp_user', $user_id ) ) {
		return new WP_Error( 'ax_actors_principal_unavailable', 'The initial owner is unavailable.' );
	}
	global $wpdb;
	$now = current_time( 'mysql', true );
	$ok = $wpdb->query( $wpdb->prepare(
		"INSERT INTO %i (identity_id, principal_kind, principal_id, user_id, role, created_at, updated_at)
		 VALUES (%d, %s, %d, %d, 'owner', %s, %s)",
		axismundi_actors_managers_table(), $account_id, 'wp_user', $user_id, $user_id, $now, $now
	) );
	return false === $ok ? new WP_Error( 'ax_actors_principal_write', 'Could not seed the initial owner.' ) : true;
}

/** Live direct owners of one namespace; never count missing sources or inherit owners. */
function axismundi_actors_principal_owner_count( int $account_id, string $kind ) : int {
	return count( array_filter( axismundi_actors_account_principals( $account_id ),
		static fn( array $row ) : bool => $kind === $row['principal_kind'] && 'owner' === $row['role']
	) );
}

/** A principal grant is policy data, never authentication or recursive delegation. */
function axismundi_actors_principal_can_manage( int $account_id, string $kind, int $id, ?string $min_role = null ) : bool {
	$role = axismundi_actors_principal_account_role( $account_id, $kind, $id );
	return '' !== $role && ( null === $min_role || ( axismundi_actors_principal_role_rank( $min_role ) > 0
		&& axismundi_actors_principal_role_rank( $role ) >= axismundi_actors_principal_role_rank( $min_role ) ) );
}

/** Active direct grants, retaining the subject namespace and source identifiers. */
function axismundi_actors_account_principals( int $account_id ) : array {
	if ( ! axismundi_actors_account_access_ready() || $account_id <= 0 ) {
		return array();
	}
	global $wpdb;
	$rows = (array) $wpdb->get_results( $wpdb->prepare(
		'SELECT principal_kind, principal_id, role, created_at, updated_at FROM %i WHERE identity_id = %d ORDER BY principal_kind, principal_id',
		axismundi_actors_managers_table(), $account_id
	), ARRAY_A );
	$result = array();
	foreach ( $rows as $row ) {
		$row['principal_id'] = (int) $row['principal_id'];
		if ( '' !== axismundi_actors_principal_account_role( $account_id, $row['principal_kind'], $row['principal_id'] ) ) {
			$result[] = $row;
		}
	}
	usort( $result, static fn( array $a, array $b ) : int => axismundi_actors_principal_role_rank( $b['role'] ) <=> axismundi_actors_principal_role_rank( $a['role'] ) );
	return $result;
}

/** Accounts accessible by this exact subject, not by subjects operating it. */
function axismundi_actors_accounts_for_principal( string $kind, int $id, ?string $min_role = null ) : array {
	if ( ! axismundi_actors_account_access_ready() || ! axismundi_actors_resolve_principal( $kind, $id ) ) {
		return array();
	}
	global $wpdb;
	$ids = (array) $wpdb->get_col( $wpdb->prepare(
		'SELECT identity_id FROM %i WHERE principal_kind = %s AND principal_id = %d ORDER BY identity_id',
		axismundi_actors_managers_table(), $kind, $id
	) );
	$result = array();
	foreach ( $ids as $account_id ) {
		if ( axismundi_actors_principal_can_manage( (int) $account_id, $kind, $id, $min_role ) ) {
			$result[ (int) $account_id ] = axismundi_actors_get_by_identity( (int) $account_id );
		}
	}
	return $result;
}

/**
 * Trusted local policy write. Callers must authorize the operator separately.
 * Remote creation/updates stay closed; exact stale remote rows may only be revoked.
 * Serialize updates to retain the live direct WP owner anchor.
 * A null role revokes a grant, including a now-disabled subject's stale grant.
 *
 * @return true|WP_Error
 */
function axismundi_actors_set_principal_role( int $account_id, string $kind, int $id, ?string $role, bool $create_only = false ) {
	$account = axismundi_actors_get_by_identity( $account_id );
	if ( ! axismundi_actors_account_access_ready() || ! $account instanceof Axismundi_Actor || ! $account->is_local()
		|| in_array( $account->get_status(), array( 'disabled', 'tombstone' ), true )
		|| $id <= 0 || ! in_array( $kind, array( 'wp_user', 'local_account', 'remote_actor' ), true )
		|| ( 'remote_actor' === $kind && null !== $role )
		|| ( null !== $role && ( ! axismundi_actors_resolve_principal( $kind, $id ) || ! isset( axismundi_actors_principal_roles()[ $role ] ) ) ) ) {
		return new WP_Error( 'ax_actors_principal_unavailable', 'The account, principal or role is unavailable.' );
	}
	global $wpdb;
	$lock = 'ax-grant-' . substr( hash( 'sha256', DB_NAME . ':' . axismundi_actors_managers_table() . ':' . $account_id ), 0, 40 );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) {
		return new WP_Error( 'ax_actors_principal_lock', 'Could not lock the account grants.' );
	}
	try {
		if ( $create_only ) {
			$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT role FROM %i WHERE identity_id = %d AND principal_kind = %s AND principal_id = %d', axismundi_actors_managers_table(), $account_id, $kind, $id ) );
			if ( null !== $existing ) {
				return $existing === $role ? true : new WP_Error( 'ax_actors_principal_conflict', 'The selected Account already has a different explicit role.' );
			}
		}
		if ( 'wp_user' === $kind && 'owner' !== $role ) {
			if ( $id === $account->get_personal_owner_user_id() ) {
				return new WP_Error( 'ax_actors_personal_owner', 'Transfer personal ownership before changing its owner access.' );
			}
			$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT role FROM %i WHERE identity_id = %d AND principal_kind = %s AND principal_id = %d', axismundi_actors_managers_table(), $account_id, $kind, $id ) );
			if ( 'owner' === $existing && axismundi_actors_resolve_principal( 'wp_user', $id )
				&& axismundi_actors_principal_owner_count( $account_id, 'wp_user' ) <= 1 ) {
				return new WP_Error( 'ax_actors_last_owner', 'The last direct WordPress User owner cannot be removed or demoted.' );
			}
		}
		$table = axismundi_actors_managers_table();
		if ( null === $role ) {
			$ok = $wpdb->delete( $table, array( 'identity_id' => $account_id, 'principal_kind' => $kind, 'principal_id' => $id ), array( '%d', '%s', '%d' ) );
		} else {
			$now = current_time( 'mysql', true );
			// user_id is an intentional compatibility projection, never a second subject key.
			$user_sql = 'wp_user' === $kind ? (string) $id : 'NULL';
			$ok = $wpdb->query( $wpdb->prepare(
				"INSERT INTO %i (identity_id, principal_kind, principal_id, user_id, role, created_at, updated_at)
				 VALUES (%d, %s, %d, {$user_sql}, %s, %s, %s)
				 ON DUPLICATE KEY UPDATE role = VALUES(role), updated_at = VALUES(updated_at)",
				$table, $account_id, $kind, $id, $role, $now, $now
			) );
		}
		return false === $ok ? new WP_Error( 'ax_actors_principal_write', 'Could not update the principal grant.' ) : true;
	} finally {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}

/** Explicit, additive promotion: never select an Actor or remove the User grant. */
function axismundi_actors_promote_user_principal( int $account_id, int $user_id, int $selected_account_id ) {
	$role = axismundi_actors_principal_account_role( $account_id, 'wp_user', $user_id );
	if ( '' === $role || ! axismundi_actors_principal_can_manage( $selected_account_id, 'wp_user', $user_id ) ) {
		return new WP_Error( 'ax_actors_principal_selection', 'An accessible, explicitly selected local Account and an existing User grant are required.' );
	}
	// Existing explicit Actor grants coexist; promotion must not silently overwrite one.
	$existing = axismundi_actors_principal_account_role( $account_id, 'local_account', $selected_account_id );
	if ( '' !== $existing ) {
		return $existing === $role ? true : new WP_Error( 'ax_actors_principal_conflict', 'The selected Account already has a different explicit role.' );
	}
	return axismundi_actors_set_principal_role( $account_id, 'local_account', $selected_account_id, $role, true );
}

function axismundi_actors_principals_ready() : bool {
	return AXISMUNDI_ACTORS_PRINCIPAL_SCHEMA_VERSION === (string) get_option( 'ax_actors_principal_schema_version', '' );
}

/** Source identifiers are retained, not allocated from a second identity store. No access is granted. */
function axismundi_actors_resolve_principal( string $kind, int $id ) : ?array {
	if ( $id <= 0 || ! in_array( $kind, array( 'wp_user', 'local_account', 'remote_actor' ), true ) ) {
		return null;
	}
	if ( 'wp_user' === $kind ) {
		$source = get_userdata( $id );
	} else {
		$source = axismundi_actors_get_by_identity( $id );
		if ( ! $source instanceof Axismundi_Actor || in_array( $source->get_status(), array( 'disabled', 'tombstone' ), true )
			|| ( 'local_account' === $kind ) !== $source->is_local() ) {
			return null;
		}
	}
	return $source ? array( 'kind' => $kind, 'id' => $id, 'key' => $kind . '_' . $id, 'source' => $source ) : null;
}

/** Internal policy lookup, not proof that an HTTP caller represents this subject. No inheritance yet. */
function axismundi_actors_principal_account_role( int $account_id, string $kind, int $id ) : string {
	if ( $account_id <= 0 || ! axismundi_actors_account_access_ready() || ! axismundi_actors_resolve_principal( $kind, $id ) ) {
		return '';
	}
	$account = axismundi_actors_get_by_identity( $account_id );
	if ( ! $account instanceof Axismundi_Actor || ! $account->is_local()
		|| in_array( $account->get_status(), array( 'disabled', 'tombstone' ), true ) ) {
		return '';
	}
	global $wpdb;
	$role = (string) $wpdb->get_var( $wpdb->prepare(
		'SELECT role FROM %i WHERE identity_id = %d AND principal_kind = %s AND principal_id = %d',
		axismundi_actors_managers_table(), $account_id, $kind, $id
	) );
	return array_key_exists( $role, axismundi_actors_principal_roles() ) ? $role : '';
}

/** Verify the complete key and legacy projection contract, not just the presence of columns. */
function axismundi_actors_principal_relation_schema_valid( string $table ) : bool {
	global $wpdb;
	$columns = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ), ARRAY_A );
	if ( ! is_array( $columns ) || array_diff( array( 'identity_id', 'principal_kind', 'principal_id', 'user_id', 'role' ), array_column( $columns, 'Field' ) ) ) {
		return false;
	}
	$nullable = array_column( $columns, 'Null', 'Field' );
	$primary = (array) $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'PRIMARY'", $table ), ARRAY_A );
	$legacy = (array) $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'legacy_user'", $table ), ARRAY_A );
	$subject = (array) $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'principal_role'", $table ), ARRAY_A );
	foreach ( array( &$primary, &$legacy, &$subject ) as &$index ) {
		usort( $index, static fn( array $a, array $b ) : int => (int) $a['Seq_in_index'] <=> (int) $b['Seq_in_index'] );
	}
	unset( $index );
	$invalid = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM %i WHERE principal_id <= 0 OR principal_kind NOT IN ('wp_user','local_account','remote_actor')
		 OR (principal_kind = 'wp_user' AND (user_id IS NULL OR user_id <> principal_id))
		 OR (principal_kind <> 'wp_user' AND user_id IS NOT NULL)", $table
	) );
	return 'YES' === ( $nullable['user_id'] ?? '' ) && null !== $invalid && 0 === (int) $invalid
		&& array( 'identity_id', 'principal_kind', 'principal_id' ) === array_column( $primary, 'Column_name' )
		&& array( 'identity_id', 'user_id' ) === array_column( $legacy, 'Column_name' )
		&& 0 === (int) ( $legacy[0]['Non_unique'] ?? 1 )
		&& array( 'principal_kind', 'principal_id', 'role' ) === array_column( $subject, 'Column_name' );
}

/** Replay-safe MySQL DDL migration. DDL is not transactional; every intermediate step retains user_id. */
function axismundi_actors_upgrade_principal_relations( string $table ) : bool {
	global $wpdb;
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	if ( null === $exists && '' === $wpdb->last_error ) {
		return true; // The installer will create the final schema on a fresh site.
	}
	if ( $table !== $exists ) {
		return false;
	}
	$lock = 'ax-principal-' . substr( hash( 'sha256', DB_NAME . ':' . $table ), 0, 40 );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) {
		return false;
	}
	try {
		$columns = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
		foreach ( array( 'principal_kind' => "varchar(16) NOT NULL DEFAULT 'wp_user'", 'principal_id' => 'bigint(20) unsigned NOT NULL DEFAULT 0' ) as $name => $definition ) {
			if ( ! in_array( $name, $columns, true ) && false === $wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN %i {$definition}", $table, $name ) ) ) {
				return false;
			}
		}
		if ( false === $wpdb->query( $wpdb->prepare(
			"UPDATE %i SET principal_id = user_id WHERE principal_kind = 'wp_user' AND principal_id = 0 AND user_id > 0", $table
		) ) ) {
			return false;
		}
		$invalid = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i WHERE principal_id <= 0 OR principal_kind NOT IN ('wp_user','local_account','remote_actor')
			 OR (principal_kind = 'wp_user' AND (user_id IS NULL OR principal_id <> user_id))
			 OR (principal_kind <> 'wp_user' AND user_id IS NOT NULL)", $table
		) );
		if ( null === $invalid || 0 !== (int) $invalid ) {
			return false;
		}
		$primary = (array) $wpdb->get_col( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'PRIMARY'", $table ), 4 );
		if ( array( 'identity_id', 'user_id' ) === $primary ) {
			if ( false === $wpdb->query( $wpdb->prepare(
				'ALTER TABLE %i DROP PRIMARY KEY, MODIFY user_id bigint(20) unsigned NULL DEFAULT NULL, ADD PRIMARY KEY (identity_id, principal_kind, principal_id)', $table
			) ) ) {
				return false;
			}
		} elseif ( array( 'identity_id', 'principal_kind', 'principal_id' ) !== $primary ) {
			return false;
		}
		foreach ( array( 'legacy_user' => 'UNIQUE KEY legacy_user (identity_id, user_id)', 'principal_role' => 'KEY principal_role (principal_kind, principal_id, role)' ) as $name => $definition ) {
			$index = $wpdb->get_var( $wpdb->prepare( 'SHOW INDEX FROM %i WHERE Key_name = %s', $table, $name ) );
			if ( null === $index && false === $wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD {$definition}", $table ) ) ) {
				return false;
			}
		}
		return axismundi_actors_principal_relation_schema_valid( $table );
	} finally {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}
