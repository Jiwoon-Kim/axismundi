<?php
/** Actor body migration and lazy detail reads on isolated tables. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$prefix = $wpdb->prefix;
$marker = get_option( 'ax_actors_body_version', false );
$test = $prefix . 'ax_body_' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 10 ) . '_';
$tables = array_map( static fn( $name ) => $test . $name, array( 'ax_actormeta', 'ax_actors_next', 'ax_actors', 'ax_actors_legacy', 'ax_identities' ) );
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void { ++$checks; if ( ! $ok ) { throw new RuntimeException( $label ); } };
try {
	$assert( false !== $wpdb->query( "CREATE TABLE {$test}ax_identities (id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,uuid char(36),canonical_uri text,canonical_uri_hash char(64) UNIQUE,object_kind varchar(20),origin varchar(10),status varchar(12),created_at datetime,updated_at datetime) ENGINE=InnoDB AUTO_INCREMENT=500" ), 'Legacy anchor fixture schema exists' );
	$assert( false !== $wpdb->query( "CREATE TABLE {$test}ax_actors (identity_id bigint unsigned PRIMARY KEY,actor_type varchar(16) NOT NULL,preferred_username varchar(191),display_name varchar(191),summary text,local_user_id bigint unsigned,payload_json longtext,created_at datetime NOT NULL,updated_at datetime NOT NULL) ENGINE=InnoDB" ), 'Legacy profile fixture schema exists' );
	$now = current_time( 'mysql', true );
	$uri = 'https://example.com/actors/body-' . wp_generate_uuid4();
	$uuid = wp_generate_uuid4();
	$raw = " {\n\"id\":\"{$uri}\",\"icon\":{\"url\":\"https://example.com/icon.png\"},\"image\":\"https://example.com/header.png\",\"extension\":{\"unknown\":true}} ";
	$assert( false !== $wpdb->insert( $test . 'ax_identities', array( 'id' => 51, 'uuid' => $uuid, 'canonical_uri' => $uri, 'canonical_uri_hash' => hash( 'sha256', $uri ), 'object_kind' => 'object', 'origin' => 'remote', 'status' => 'public', 'created_at' => $now, 'updated_at' => $now ) ), 'Old fixture anchor inserted' );
	$assert( false !== $wpdb->insert( $test . 'ax_actors', array( 'identity_id' => 51, 'actor_type' => 'Person', 'preferred_username' => 'alice', 'display_name' => 'Alice', 'payload_json' => $raw, 'created_at' => $now, 'updated_at' => $now ) ), 'Old fixture profile inserted' );
	$wpdb->prefix = $test;
	delete_option( 'ax_actors_body_version' );
	$assert( ! axismundi_actors_migrate_body(), 'Non-Actor anchors block body migration' );
	$wpdb->update( $test . 'ax_identities', array( 'object_kind' => 'actor' ), array( 'id' => 51 ) );
	$assert( axismundi_actors_migrate_body(), 'Verified Actor body migration completes' );
	$next_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', axismundi_actors_actors_table() ) );
	$assert( $next_id >= 500, 'Historical allocator high-water mark is preserved' );
	$assert( $test . 'ax_actors' === axismundi_actors_identities_table(), 'Compatibility lookup points to the Actor body, not a parent' );
	$assert( null === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $test . 'ax_identities' ) ) ), 'Shared identity table is removed' );
	$columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . axismundi_actors_actors_table() );
	$assert( in_array( 'id', $columns, true ) && ! in_array( 'payload_json', $columns, true ), 'Body owns its primary key but not raw JSON' );
	$queries = $wpdb->num_queries;
	$actor = axismundi_actors_get_by_uri( $uri );
	$assert( $actor instanceof Axismundi_Actor && 51 === $actor->get_identity_id() && $uuid === $actor->get_uuid() && $uri === $actor->get_uri(), 'ID, UUID and canonical URI are preserved' );
	$assert( 'Alice' === $actor->get_display_name() && 'alice' === $actor->get_preferred_username() && 'example.com' === $actor->get_host() && 'https://example.com/icon.png' === $actor->get_icon_uri() && 'https://example.com/header.png' === $actor->get_image_uri(), 'List and hovercard fields come from the body' );
	$assert( 1 === $wpdb->num_queries - $queries, 'Lookup and compact fields need one query, no meta join' );
	$assert( $raw === axismundi_actors_get_meta( 51, 'remote_payload' ), 'Original JSON is preserved byte-for-byte in meta' );
	$detail = axismundi_actors_get_remote_payload( 51 );
	$assert( true === ( $detail['extension']['unknown'] ?? null ), 'Detail reads retain unrecognized JSON properties' );
	$assert( axismundi_actors_migrate_body(), 'Completed migration is replay-safe' );
	$assert( 1 === $wpdb->delete( axismundi_actors_actors_table(), array( 'id' => 51 ) ), 'Fixture body can be physically removed' );
	$assert( null === axismundi_actors_get_meta( 51, 'remote_payload' ), 'Physical fixture deletion cascades only its detail rows' );
} finally {
	$wpdb->prefix = $prefix;
	if ( false === $marker ) { delete_option( 'ax_actors_body_version' ); } else { update_option( 'ax_actors_body_version', $marker, false ); }
	foreach ( $tables as $table ) { $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); }
}
printf( "PASS: %d Actor body checks\n", $checks );
