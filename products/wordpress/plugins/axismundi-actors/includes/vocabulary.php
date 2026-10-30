<?php
/** Identity vocabulary migration; readable visibility belongs to each source. */
defined( 'ABSPATH' ) || exit;

/** Replay-safe, bounded migration. Preserve normal status listeners and stable identities. */
function axismundi_actors_migrate_actor_vocabulary( int $limit = 100 ) : bool {
	if ( AXISMUNDI_ACTORS_DB_VERSION !== (string) get_option( 'ax_actors_db_version', '' ) ) { return false; }
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
