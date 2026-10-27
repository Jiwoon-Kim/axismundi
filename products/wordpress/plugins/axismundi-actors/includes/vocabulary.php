<?php
/** Identity vocabulary migration; readable visibility belongs to each source. */
defined( 'ABSPATH' ) || exit;

/** Preserve the historical instance identity as a configured use, never an Actor type. */
function axismundi_actors_migrate_instance_binding() : bool {
	if ( false !== get_option( 'ax_actors_instance_identity_id', false ) ) { return true; }
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT a.identity_id FROM %i a INNER JOIN %i i ON i.id = a.identity_id WHERE i.origin = 'local' AND a.actor_scope = 'site' ORDER BY a.identity_id LIMIT 2",
		axismundi_actors_actors_table(), axismundi_actors_identities_table()
	) );
	if ( '' !== $wpdb->last_error || count( $ids ) > 1 ) { return false; }
	add_option( 'ax_actors_instance_identity_id', $ids ? (int) $ids[0] : 0, '', false );
	return false !== get_option( 'ax_actors_instance_identity_id', false );
}

/** Only an explicitly configured local identity is the instance identity. */
function axismundi_actors_is_instance_actor( Axismundi_Actor $actor ) : bool {
	return $actor->is_local() && $actor->get_identity_id() === (int) get_option( 'ax_actors_instance_identity_id', 0 );
}

/** Replay-safe, bounded migration. Preserve normal status listeners and stable identities. */
function axismundi_actors_migrate_actor_vocabulary( int $limit = 100 ) : bool {
	if ( AXISMUNDI_ACTORS_DB_VERSION !== (string) get_option( 'ax_actors_db_version', '' ) || ! axismundi_actors_migrate_instance_binding() ) { return false; }
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT id FROM %i WHERE status = 'internal' ORDER BY id LIMIT %d",
		axismundi_actors_identities_table(), max( 1, min( 500, $limit ) )
	) );
	if ( '' !== $wpdb->last_error ) { return false; }
	foreach ( $ids as $id ) {
		if ( ! axismundi_actors_set_status( (int) $id, 'public', 'internal' ) ) { return false; }
	}
	$remaining = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM %i WHERE status = 'internal'",
		axismundi_actors_identities_table()
	) );
	if ( null === $remaining || '' !== $wpdb->last_error ) { return false; }
	if ( 0 !== (int) $remaining ) { return false; }
	update_option( 'ax_actors_vocabulary_version', '2', false );
	return true;
}

/** Retry incomplete batches after every plugin has registered its normal listeners. */
function axismundi_actors_maybe_migrate_actor_vocabulary() : void {
	if ( '2' !== get_option( 'ax_actors_vocabulary_version', '' ) ) {
		axismundi_actors_migrate_actor_vocabulary();
	}
}
add_action( 'init', 'axismundi_actors_maybe_migrate_actor_vocabulary', 5 );

/** Retire only folder anchors after their producer has been removed. */
function axismundi_actors_retire_folder_identities() : bool {
	global $wpdb;
	$table = axismundi_actors_identities_table();
	$wpdb->query( 'START TRANSACTION' );
	$wpdb->get_col( "SELECT id FROM {$table} WHERE object_kind='folder' FOR UPDATE" );
	if ( $wpdb->last_error ) { $wpdb->query( 'ROLLBACK' ); return false; }
	foreach ( array( 'ax_actors', 'ax_actor_managers', 'ax_identity_relations' ) as $suffix ) {
		$dependent = $wpdb->prefix . $suffix;
		if ( 0 !== (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$dependent} d INNER JOIN {$table} i ON i.id=d.identity_id WHERE i.object_kind='folder'" ) || $wpdb->last_error ) {
			$wpdb->query( 'ROLLBACK' ); return false;
		}
	}
	if ( false === $wpdb->query( "DELETE FROM {$table} WHERE object_kind='folder'" ) ) { $wpdb->query( 'ROLLBACK' ); return false; }
	if ( false === $wpdb->query( 'COMMIT' ) ) { return false; }
	update_option( 'ax_actors_folder_identities_retired', '1', false );
	return true;
}

function axismundi_actors_maybe_retire_folder_identities() : void {
	// An older active Media Library must be upgraded before removing its anchors.
	if ( function_exists( 'axismundi_media_folder_identity_uuid' ) ) { return; }
	if ( '1' !== get_option( 'ax_actors_folder_identities_retired' ) ) { axismundi_actors_retire_folder_identities(); }
}
add_action( 'init', 'axismundi_actors_maybe_retire_folder_identities', 30 );
