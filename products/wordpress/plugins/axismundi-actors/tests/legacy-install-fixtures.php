<?php
/** Isolated pre-body tables for testing the real installer; never downgrade the live schema. */
defined( 'ABSPATH' ) || exit( 1 );

function axismundi_actors_audit_legacy_install( callable $prepare, callable $verify ) : bool {
	global $wpdb, $wp_object_cache;
	if ( wp_using_ext_object_cache() ) {
		throw new RuntimeException( 'Legacy install fixtures require a process-local object cache.' );
	}
	$prefix = $wpdb->prefix;
	$options = $wpdb->options;
	$cache = $wp_object_cache;
	$test = $prefix . 'ax_audit_install_' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 12 ) . '_';
	$foreign_keys = (int) $wpdb->get_var( 'SELECT @@SESSION.FOREIGN_KEY_CHECKS' );
	$live_markers = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE option_name LIKE %s ORDER BY option_name', $options, $wpdb->esc_like( 'ax_actors_' ) . '%' ), ARRAY_A );
	try {
		if ( false === $wpdb->query( "CREATE TABLE {$test}options LIKE {$options}" )
			|| false === $wpdb->query( $wpdb->prepare( "INSERT INTO {$test}options SELECT * FROM %i WHERE option_name NOT LIKE %s", $options, $wpdb->esc_like( 'ax_actors_' ) . '%' ) )
			|| false === $wpdb->query( "CREATE TABLE {$test}ax_identities (id bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,uuid char(36) NOT NULL,canonical_uri text NOT NULL,canonical_uri_hash char(64) NOT NULL UNIQUE,object_kind varchar(20) NOT NULL,origin varchar(10) NOT NULL,status varchar(12) NOT NULL,created_at datetime NOT NULL,updated_at datetime NOT NULL) ENGINE=InnoDB" )
			|| false === $wpdb->query( "CREATE TABLE {$test}ax_actors (identity_id bigint(20) unsigned NOT NULL PRIMARY KEY,actor_type varchar(16) NOT NULL,actor_scope varchar(8) DEFAULT NULL,preferred_username varchar(191) DEFAULT NULL,local_handle_key varchar(191) DEFAULT NULL,payload_json longtext DEFAULT NULL,created_at datetime NOT NULL,updated_at datetime NOT NULL) ENGINE=InnoDB" ) ) {
			throw new RuntimeException( 'Could not create legacy install fixture: ' . $wpdb->last_error );
		}
		$wpdb->prefix = $test;
		$wpdb->options = $test . 'options';
		$wp_object_cache = new WP_Object_Cache();
		if ( axismundi_actors_body_installed() ) { throw new RuntimeException( 'Fixture unexpectedly has an installed Actor body.' ); }
		$state = $prepare();
		axismundi_actors_install();
		return $verify( $state );
	} finally {
		$wpdb->prefix = $prefix;
		$wpdb->options = $options;
		$wp_object_cache = $cache;
		try {
			$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $test ) . '%' ) );
			$wpdb->query( 'SET SESSION FOREIGN_KEY_CHECKS=0' );
			foreach ( $tables as $table ) {
				if ( ! str_starts_with( $table, $test ) || false === $wpdb->query( $wpdb->prepare( 'DROP TABLE %i', $table ) ) ) {
					throw new RuntimeException( 'Could not remove legacy install fixture table.' );
				}
			}
		} finally {
			$wpdb->query( 'SET SESSION FOREIGN_KEY_CHECKS=' . $foreign_keys );
		}
		if ( $live_markers !== $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE option_name LIKE %s ORDER BY option_name', $options, $wpdb->esc_like( 'ax_actors_' ) . '%' ), ARRAY_A ) ) {
			throw new RuntimeException( 'Legacy fixture changed live Actor migration markers.' );
		}
	}
}

/** Insert one historical Actor before the installer creates auxiliary ledgers. */
function axismundi_actors_audit_legacy_actor( string $origin, string $uri, ?string $handle = null, ?array $payload = null ) : int {
	global $wpdb;
	$now = current_time( 'mysql', true );
	if ( false === $wpdb->insert( $wpdb->prefix . 'ax_identities', array( 'uuid' => wp_generate_uuid4(), 'canonical_uri' => $uri, 'canonical_uri_hash' => hash( 'sha256', $uri ), 'object_kind' => 'actor', 'origin' => $origin, 'status' => 'public', 'created_at' => $now, 'updated_at' => $now ) ) ) {
		throw new RuntimeException( 'Could not insert legacy identity.' );
	}
	$id = (int) $wpdb->insert_id;
	if ( false === $wpdb->insert( $wpdb->prefix . 'ax_actors', array( 'identity_id' => $id, 'actor_type' => 'Person', 'actor_scope' => 'local' === $origin ? 'user' : 'remote', 'preferred_username' => $handle, 'local_handle_key' => 'local' === $origin ? $handle : null, 'payload_json' => null === $payload ? null : wp_json_encode( $payload ), 'created_at' => $now, 'updated_at' => $now ) ) ) {
		throw new RuntimeException( 'Could not insert legacy Actor.' );
	}
	return $id;
}
