<?php
/** Legacy transport provenance, independent of Account ownership. @package AxismundiActors */
defined( 'ABSPATH' ) || exit;

/** Backfill only uninitialized local bindings. Replays never follow later ownership changes. */
function axismundi_actors_migrate_transport_bindings() : bool {
	global $wpdb;
	$actors = axismundi_actors_actors_table();
	$identities = axismundi_actors_identities_table();
	$columns = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $actors ) );
	if ( array_diff( array( 'transport_kind', 'transport_user_id' ), $columns ) ) {
		return false;
	}
	$index = (array) $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name = 'transport_user_id'", $actors ), ARRAY_A );
	if ( 1 !== count( $index ) || 'transport_user_id' !== $index[0]['Column_name'] ) {
		return false;
	}
	if ( false === $wpdb->query( $wpdb->prepare(
		"UPDATE %i a INNER JOIN %i i ON i.id = a.identity_id
		 SET a.transport_kind = CASE WHEN a.local_user_id > 0 THEN 'upstream_user' ELSE 'upstream_application' END,
		 a.transport_user_id = CASE WHEN a.local_user_id > 0 THEN a.local_user_id ELSE NULL END
		 WHERE i.origin = 'local' AND a.transport_kind IS NULL", $actors, $identities
	) ) ) {
		return false;
	}
	$invalid = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM %i a INNER JOIN %i i ON i.id = a.identity_id WHERE i.origin = 'local'
		 AND (a.transport_kind IS NULL OR a.transport_kind NOT IN ('upstream_user','upstream_application')
		 OR (a.transport_kind = 'upstream_user' AND (a.transport_user_id IS NULL OR a.transport_user_id <= 0))
		 OR (a.transport_kind = 'upstream_application' AND a.transport_user_id IS NOT NULL))", $actors, $identities
	) );
	return null !== $invalid && 0 === (int) $invalid;
}

/** Non-secret descriptor only; never infer transport from an access/ownership column. */
function axismundi_actors_transport_binding( Axismundi_Actor $actor ) : ?array {
	if ( ! $actor->is_local() || AXISMUNDI_ACTORS_DB_VERSION !== (string) get_option( 'ax_actors_db_version', '' ) ) {
		return null;
	}
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT transport_kind, transport_user_id FROM %i WHERE identity_id = %d', axismundi_actors_actors_table(), $actor->get_identity_id() ), ARRAY_A );
	if ( ! is_array( $row ) ) {
		return null;
	}
	if ( 'upstream_user' === $row['transport_kind'] && (int) $row['transport_user_id'] > 0 ) {
		return array( 'kind' => 'upstream_user', 'user_id' => (int) $row['transport_user_id'] );
	}
	return 'upstream_application' === $row['transport_kind'] && null === $row['transport_user_id']
		? array( 'kind' => 'upstream_application', 'user_id' => null ) : null;
}

/** Resolve the old per-user Inbox route by transport provenance, not personal ownership. */
function axismundi_actors_get_transport_actor_for_user( int $user_id ) : ?Axismundi_Actor {
	if ( $user_id <= 0 || AXISMUNDI_ACTORS_DB_VERSION !== (string) get_option( 'ax_actors_db_version', '' ) ) {
		return null;
	}
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT a.identity_id FROM %i a INNER JOIN %i i ON i.id = a.identity_id
		WHERE a.transport_kind = 'upstream_user' AND a.transport_user_id = %d AND i.origin = 'local' LIMIT 2",
		axismundi_actors_actors_table(), axismundi_actors_identities_table(), $user_id ) );
	return is_array( $ids ) && 1 === count( $ids ) ? axismundi_actors_get_by_identity( (int) $ids[0] ) : null;
}
