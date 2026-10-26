<?php
/**
 * Local account ownership and operating access, independent of JMAP.
 *
 * The existing manager relation is reused; a manager's owner role does not imply
 * personal ownership. Legacy local_user_id remains an upstream compatibility link.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTORS_ACCOUNT_ACCESS_VERSION = '1';

/** Readiness is explicit: never grant legacy authority after a partial migration. */
function axismundi_actors_account_access_ready() : bool {
	return axismundi_actors_principals_ready()
		&& AXISMUNDI_ACTORS_ACCOUNT_ACCESS_VERSION === (string) get_option( 'ax_actors_account_access_version', '' );
}

/** Additive, replay-safe data migration; called only after schema verification. */
function axismundi_actors_migrate_account_access() : bool {
	if ( AXISMUNDI_ACTORS_ACCOUNT_ACCESS_VERSION === (string) get_option( 'ax_actors_account_access_version', '' ) ) {
		return true;
	}
	global $wpdb;
	$actors = axismundi_actors_actors_table();
	$identities = axismundi_actors_identities_table();
	$managers = axismundi_actors_managers_table();
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- migration transaction.
		return false;
	}
	// Only existing user-bound local identities are personal. Managed owner roles stay unchanged.
	$updated = $wpdb->query( $wpdb->prepare(
		"UPDATE %i a INNER JOIN %i i ON i.id = a.identity_id INNER JOIN %i u ON u.ID = a.local_user_id
		 SET a.personal_owner_user_id = a.local_user_id
		 WHERE i.origin = 'local' AND a.actor_scope = 'user' AND a.personal_owner_user_id IS NULL",
		$actors, $identities, $wpdb->users
	) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- fixed schema backfill.
	$inserted = false;
	if ( false !== $updated ) {
		$inserted = $wpdb->query( $wpdb->prepare(
			"INSERT INTO %i (identity_id, principal_kind, principal_id, user_id, role, created_at, updated_at)
			 SELECT a.identity_id, 'wp_user', a.personal_owner_user_id, a.personal_owner_user_id, 'owner', UTC_TIMESTAMP(), UTC_TIMESTAMP()
			 FROM %i a INNER JOIN %i i ON i.id = a.identity_id INNER JOIN %i u ON u.ID = a.personal_owner_user_id
			 WHERE i.origin = 'local' AND a.actor_scope <> 'site'
			 ON DUPLICATE KEY UPDATE role = role",
			$managers, $actors, $identities, $wpdb->users
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- preserves existing manager roles.
	}
	$inconsistent = 1;
	if ( false !== $inserted ) {
		// A conflicting pre-existing role needs repair, not silent promotion.
		$inconsistent = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM %i a INNER JOIN %i i ON i.id = a.identity_id
			 INNER JOIN %i u ON u.ID = a.personal_owner_user_id
			 LEFT JOIN %i m ON m.identity_id = a.identity_id AND m.principal_kind = 'wp_user' AND m.principal_id = a.personal_owner_user_id
			 WHERE i.origin = 'local' AND a.actor_scope <> 'site' AND (m.role IS NULL OR m.role <> 'owner')",
			$actors, $identities, $wpdb->users, $managers
		) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- migration invariant check.
	}
	if ( false === $updated || false === $inserted || null === $inconsistent || 0 !== (int) $inconsistent ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- migration rollback.
		return false;
	}
	if ( false === $wpdb->query( 'COMMIT' ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- migration commit.
		return false;
	}
	update_option( 'ax_actors_account_access_version', AXISMUNDI_ACTORS_ACCOUNT_ACCESS_VERSION, false );
	return axismundi_actors_account_access_ready();
}

/** Role lookup only; callers separately check Actor state and domain permissions. */
function axismundi_actors_account_role( int $identity_id, int $user_id ) : string {
	return axismundi_actors_principal_account_role( $identity_id, 'wp_user', $user_id );
}

/** User-relative personal attribution, never inferred from Actor type or manager role alone. */
function axismundi_actors_account_is_personal( Axismundi_Actor $actor, int $user_id ) : bool {
	return $user_id > 0 && $actor->is_local() && 'site' !== $actor->get_scope()
		&& $user_id === $actor->get_personal_owner_user_id()
		&& 'owner' === axismundi_actors_account_role( $actor->get_identity_id(), $user_id );
}

/** Every local account this user may operate; personal accounts sort first. */
function axismundi_actors_accounts_for_user( int $user_id ) : array {
	// Authenticated WP-login projection, not recursive Account-principal access.
	$result = axismundi_actors_accounts_for_principal( 'wp_user', $user_id );
	uasort( $result, static function ( Axismundi_Actor $a, Axismundi_Actor $b ) use ( $user_id ) : int {
		return ( (int) axismundi_actors_account_is_personal( $b, $user_id ) <=> (int) axismundi_actors_account_is_personal( $a, $user_id ) )
			?: $a->get_identity_id() <=> $b->get_identity_id();
	} );
	return $result;
}

/** All personally attributed local accounts, including retired ones for lifecycle inspection. */
function axismundi_actors_personal_accounts_for_user( int $user_id ) : array {
	if ( $user_id <= 0 || ! axismundi_actors_account_access_ready() ) {
		return array();
	}
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT a.identity_id FROM %i a INNER JOIN %i i ON i.id = a.identity_id
		 WHERE a.personal_owner_user_id = %d AND i.origin = 'local' AND a.actor_scope <> 'site' ORDER BY a.identity_id ASC",
		axismundi_actors_actors_table(), axismundi_actors_identities_table(), $user_id
	) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- personal ownership lookup, not authorization.
	return array_values( array_filter( array_map( 'axismundi_actors_get_by_identity', array_map( 'intval', $ids ) ) ) );
}
