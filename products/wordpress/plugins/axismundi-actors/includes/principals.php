<?php
/** Typed access subjects; authentication and JMAP wire representations remain separate. @package AxismundiActors */
defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTORS_PRINCIPAL_SCHEMA_VERSION = '1';

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
			|| ( 'local_account' === $kind ) !== $source->is_local()
			|| ( 'local_account' === $kind && 'site' === $source->get_scope() ) ) {
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
	if ( ! $account instanceof Axismundi_Actor || ! $account->is_local() || 'site' === $account->get_scope()
		|| in_array( $account->get_status(), array( 'disabled', 'tombstone' ), true ) ) {
		return '';
	}
	global $wpdb;
	$role = (string) $wpdb->get_var( $wpdb->prepare(
		'SELECT role FROM %i WHERE identity_id = %d AND principal_kind = %s AND principal_id = %d',
		axismundi_actors_managers_table(), $account_id, $kind, $id
	) );
	return array_key_exists( $role, axismundi_actors_manager_roles() ) ? $role : '';
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
