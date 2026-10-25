<?php
/** Local deletion cleanup; identity and public key history survive. @package AxismundiActors */
defined( 'ABSPATH' ) || exit;

/** Caller owns the transaction and has locked the Actor and identity. */
function axismundi_actors_cleanup_deleted_actor( int $id ) : bool {
	global $wpdb;
	$now = current_time( 'mysql', true );
	return false !== $wpdb->query( $wpdb->prepare(
		'UPDATE %i SET secret_box = NULL, retired_at = COALESCE(retired_at, %s) WHERE identity_id = %d',
		axismundi_actors_signing_keys_table(), $now, $id
	) ) && false !== $wpdb->update( axismundi_actors_actors_table(), array( 'local_handle_key' => null ), array( 'identity_id' => $id ) )
		&& false !== $wpdb->query( $wpdb->prepare(
			"DELETE FROM %i WHERE identity_id = %d AND address_type IN ('acct','local_handle','former_handle')",
			axismundi_actors_addresses_table(), $id
		) );
}

/** Replay-safe upgrade. Keep the nullable UNIQUE index; dead rows release their value. */
function axismundi_actors_migrate_deletion_cleanup() : bool {
	global $wpdb;
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
		return false;
	}
	$committed = false;
	try {
		$keys = $wpdb->query( $wpdb->prepare(
			"UPDATE %i k LEFT JOIN %i i ON i.id = k.identity_id LEFT JOIN %i a ON a.identity_id = k.identity_id
			 SET k.secret_box = NULL, k.retired_at = COALESCE(k.retired_at, %s)
			 WHERE i.id IS NULL OR a.identity_id IS NULL OR i.status = 'tombstone'",
			axismundi_actors_signing_keys_table(), axismundi_actors_identities_table(), axismundi_actors_actors_table(), current_time( 'mysql', true )
		) );
		$handles = $wpdb->query( $wpdb->prepare(
			"UPDATE %i a INNER JOIN %i i ON i.id = a.identity_id SET a.local_handle_key = NULL
			 WHERE i.origin = 'local' AND i.status = 'tombstone'",
			axismundi_actors_actors_table(), axismundi_actors_identities_table()
		) );
		$addresses = $wpdb->query( $wpdb->prepare(
			"DELETE ad FROM %i ad INNER JOIN %i i ON i.id = ad.identity_id
			 WHERE i.origin = 'local' AND i.status = 'tombstone' AND ad.address_type IN ('acct','local_handle','former_handle')",
			axismundi_actors_addresses_table(), axismundi_actors_identities_table()
		) );
		if ( false === $keys || false === $handles || false === $addresses || false === $wpdb->query( 'COMMIT' ) ) {
			return false;
		}
		$committed = true;
	} finally {
		if ( ! $committed ) {
			$wpdb->query( 'ROLLBACK' );
		}
	}
	return true;
}
