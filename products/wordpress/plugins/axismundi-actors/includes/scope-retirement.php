<?php
/** Physical retirement only. Never reclassifies Actor rows. */
defined( 'ABSPATH' ) || exit;

function axismundi_actors_retire_scope() : bool {
	global $wpdb;
	if ( ! axismundi_actors_body_installed() || ! axismundi_actors_account_access_ready()
		|| ! is_array( get_option( 'ax_actors_legacy_site_retired', false ) ) ) { return false; }
	$table = axismundi_actors_actors_table();
	if ( ! axismundi_actors_drop_scope_columns( $table ) ) { return false; }
	update_option( 'ax_actors_scope_retired', '1', false );
	return '1' === get_option( 'ax_actors_scope_retired' );
}

/** Trusted schema migration entry point, also exercised on isolated fixture tables. */
function axismundi_actors_drop_scope_columns( string $table ) : bool {
	global $wpdb;
	$lock = 'ax-scope-' . md5( DB_NAME . ':' . $table );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $lock ) ) ) { return false; }
	try {
		$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
		if ( $wpdb->last_error ) { return false; }
		$indexes = $wpdb->get_col( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name='scope_type'", $table ) );
		if ( $wpdb->last_error ) { return false; }
		$changes = array();
		if ( $indexes ) { $changes[] = 'DROP INDEX scope_type'; }
		if ( in_array( 'actor_scope', $columns, true ) ) { $changes[] = 'DROP COLUMN actor_scope'; }
		if ( $changes && false === $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ' . implode( ', ', $changes ), $table ) ) ) { return false; }
		$remaining = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );
		if ( $wpdb->last_error || in_array( 'actor_scope', $remaining, true ) ) { return false; }
		$indexes = $wpdb->get_col( $wpdb->prepare( "SHOW INDEX FROM %i WHERE Key_name='scope_type'", $table ) );
		if ( $wpdb->last_error || $indexes ) { return false; }
		return true;
	} finally {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}

add_action( 'init', static function () : void {
	if ( '1' !== get_option( 'ax_actors_scope_retired' ) ) { axismundi_actors_retire_scope(); }
}, 60 );
