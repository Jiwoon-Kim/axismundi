<?php
/** JMAP-owned Account configuration; Actors owns identity, lifetime and access. */
namespace Axismundi\Jmap;
defined( 'ABSPATH' ) || exit;

function accounts_table() : string { global $wpdb; return $wpdb->prefix . 'ax_jmap_accounts'; }
function policies_table() : string { global $wpdb; return $wpdb->prefix . 'ax_jmap_account_capabilities'; }

function install_account_store() : bool {
	if ( '1' !== get_option( 'ax_actors_body_version' ) || ! function_exists( 'axismundi_actors_actors_table' ) ) { return false; }
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$accounts = accounts_table();
	$policies = policies_table();
	$actors = axismundi_actors_actors_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$accounts} (
		actor_id bigint(20) unsigned NOT NULL,
		revision bigint(20) unsigned NOT NULL DEFAULT 1,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (actor_id)
	) ENGINE=InnoDB {$charset};
	CREATE TABLE {$policies} (
		account_id bigint(20) unsigned NOT NULL,
		capability_uri varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
		enabled tinyint(1) NOT NULL DEFAULT 0,
		limits_json longtext NOT NULL,
		updated_by bigint(20) unsigned NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (account_id, capability_uri)
	) ENGINE=InnoDB {$charset};" );
	foreach ( array( array( $accounts, 'actor_id', $actors, 'id' ), array( $policies, 'account_id', $accounts, 'actor_id' ) ) as [ $table, $column, $parent, $key ] ) {
		$fk = $wpdb->get_var( $wpdb->prepare( 'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND COLUMN_NAME=%s AND REFERENCED_TABLE_NAME=%s', $table, $column, $parent ) );
		if ( $wpdb->last_error ) { return false; }
		if ( null === $fk && false === $wpdb->query( "ALTER TABLE {$table} ADD FOREIGN KEY ({$column}) REFERENCES {$parent}({$key}) ON DELETE CASCADE" ) ) { return false; }
	}
	// Activation/recovery writes configuration, never new Actors or access grants.
	if ( false === $wpdb->query( "INSERT INTO {$accounts} (actor_id,revision,updated_at) SELECT id,1,UTC_TIMESTAMP() FROM {$actors} WHERE origin='local' ON DUPLICATE KEY UPDATE actor_id=VALUES(actor_id)" ) ) { return false; }
	update_option( 'ax_jmap_db_version', '1', false );
	return '1' === get_option( 'ax_jmap_db_version' );
}

function maybe_install_account_store() : void {
	if ( '1' !== get_option( 'ax_jmap_db_version' ) ) { install_account_store(); }
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\maybe_install_account_store', 20 );

function provision_account( \Axismundi_Actor $actor ) : bool {
	if ( ! $actor->is_local() || '1' !== get_option( 'ax_jmap_db_version' ) ) { return false; }
	global $wpdb;
	return false !== $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (actor_id,revision,updated_at) VALUES (%d,1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE actor_id=VALUES(actor_id)', accounts_table(), $actor->get_identity_id() ) );
}
add_action( 'axismundi_actors_local_actor_created', __NAMESPACE__ . '\\provision_account' );

/** Absence is denial. This function neither provisions nor repairs on a read. */
function account_policy( int $actor_id, string $capability ) : ?\stdClass {
	if ( '1' !== get_option( 'ax_jmap_db_version' ) ) { return null; }
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT enabled,limits_json FROM %i WHERE account_id=%d AND capability_uri=%s', policies_table(), $actor_id, $capability ), ARRAY_A );
	if ( $wpdb->last_error ) { throw new \RuntimeException( 'Account policy storage is unavailable.' ); }
	if ( ! $row ) { return null; }
	$limits = json_decode( $row['limits_json'], false, 32, JSON_THROW_ON_ERROR );
	if ( ! $limits instanceof \stdClass ) { throw new \LogicException( 'Invalid stored Account policy.' ); }
	return (object) array( 'enabled' => 1 === (int) $row['enabled'], 'limits' => $limits );
}

/** Administrative entitlement is not a grant to operate the Actor. */
function set_account_policy( int $actor_id, string $capability, bool $enabled, \stdClass $limits, Registry $registry, ?int $expected_revision = null ) {
	if ( get_current_user_id() <= 0 || ! current_user_can( 'manage_options' ) ) { return new \WP_Error( 'ax_jmap_policy_forbidden', 'Administrative authority is required.' ); }
	$actor = axismundi_actors_get_by_identity( $actor_id );
	if ( ! $actor instanceof \Axismundi_Actor || ! $actor->is_local() || 'tombstone' === $actor->get_status() ) { return new \WP_Error( 'ax_jmap_policy_account', 'A live local Account is required.' ); }
	if ( CORE === $capability || ! $registry->validate_policy( $capability, $limits ) ) { return new \WP_Error( 'ax_jmap_policy_invalid', 'Unsupported capability or invalid limits.' ); }
	if ( '1' !== get_option( 'ax_jmap_db_version' ) ) { return new \WP_Error( 'ax_jmap_policy_storage', 'Account storage is unavailable.' ); }
	global $wpdb;
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return new \WP_Error( 'ax_jmap_policy_storage', 'Could not start policy update.' ); }
	try {
		$state = $wpdb->get_row( $wpdb->prepare( 'SELECT origin,status FROM %i WHERE id=%d FOR UPDATE', axismundi_actors_actors_table(), $actor_id ), ARRAY_A );
		if ( ! $state || 'local' !== $state['origin'] || 'tombstone' === $state['status'] || ! provision_account( $actor ) ) { throw new \RuntimeException( 'Account is unavailable.' ); }
		$revision = $wpdb->get_var( $wpdb->prepare( 'SELECT revision FROM %i WHERE actor_id=%d FOR UPDATE', accounts_table(), $actor_id ) );
		if ( null === $revision || $wpdb->last_error ) { throw new \RuntimeException( 'Could not lock Account configuration.' ); }
		if ( null !== $expected_revision && $expected_revision !== (int) $revision ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'ax_jmap_policy_stale', 'Account configuration changed.' ); }
		$json = json_encode( $limits, JSON_THROW_ON_ERROR );
		$ok = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (account_id,capability_uri,enabled,limits_json,updated_by,updated_at) VALUES (%d,%s,%d,%s,%d,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),limits_json=VALUES(limits_json),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)', policies_table(), $actor_id, $capability, (int) $enabled, $json, get_current_user_id() ) );
		if ( false === $ok || false === $wpdb->query( $wpdb->prepare( 'UPDATE %i SET revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE actor_id=%d', accounts_table(), $actor_id ) ) ) { throw new \RuntimeException( 'Could not store Account configuration.' ); }
		if ( false === $wpdb->query( 'COMMIT' ) ) { throw new \RuntimeException( 'Could not commit Account configuration.' ); }
		return true;
	} catch ( \Throwable $error ) {
		$wpdb->query( 'ROLLBACK' );
		return new \WP_Error( 'ax_jmap_policy_storage', 'Account policy update failed.' );
	}
}
