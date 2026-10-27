<?php
/** Migration exercised on isolated table copies, never production fixture IDs. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$prefix = $wpdb->prefix;
$marker = get_option( 'ax_op_object_identifiers_migrated', false );
$test_prefix = $prefix . 'ax_sep_' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 12 ) . '_';
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) : void { ++$checks; if ( ! $ok ) { throw new RuntimeException( $label ); } };
$tables = array();
try {
	foreach ( array( 'ax_identities', 'ax_objects', 'ax_actors', 'ax_actor_managers', 'ax_identity_relations' ) as $suffix ) {
		$table = $test_prefix . $suffix;
		$sql = 'ax_identities' === $suffix
			? "CREATE TABLE {$table} (id bigint unsigned PRIMARY KEY,uuid char(36),canonical_uri text,canonical_uri_hash char(64) UNIQUE,object_kind varchar(20),origin varchar(10),status varchar(12),created_at datetime,updated_at datetime) ENGINE=InnoDB"
			: "CREATE TABLE {$table} LIKE {$prefix}{$suffix}";
		$assert( false !== $wpdb->query( $sql ), 'Isolated table created: ' . $suffix );
		$tables[] = $table;
	}
	$uuid = wp_generate_uuid4();
	$uri = home_url( '/separation/' . $uuid );
	$now = current_time( 'mysql', true );
	$anchor = array( 'id' => 9, 'uuid' => $uuid, 'canonical_uri' => $uri, 'canonical_uri_hash' => hash( 'sha256', $uri ), 'object_kind' => 'object', 'origin' => 'local', 'status' => 'public', 'created_at' => $now, 'updated_at' => $now );
	$assert( false !== $wpdb->insert( $test_prefix . 'ax_identities', $anchor ), 'Historical Object fixture inserted' );
	$assert( false !== $wpdb->insert( $test_prefix . 'ax_objects', array( 'id' => 9, 'source_owner' => 'fixture', 'source_kind' => 'test', 'created_at' => $now, 'updated_at' => $now ) ), 'Historical Object metadata inserted' );
	$unbound = $anchor;
	$unbound['id'] = 8;
	$unbound['uuid'] = wp_generate_uuid4();
	$unbound['canonical_uri'] .= '/unbound';
	$unbound['canonical_uri_hash'] = hash( 'sha256', $unbound['canonical_uri'] );
	$assert( false !== $wpdb->insert( $test_prefix . 'ax_identities', $unbound ), 'Historical anchor without source metadata inserted' );
	$actor_anchor = $anchor;
	$actor_anchor['id'] = 10;
	unset( $actor_anchor['object_kind'] );
	$actor_anchor['identity_id'] = 10;
	$actor_anchor['actor_type'] = 'Person';
	$actor_anchor['uuid'] = wp_generate_uuid4();
	$actor_anchor['canonical_uri'] .= '/actor';
	$actor_anchor['canonical_uri_hash'] = hash( 'sha256', $actor_anchor['canonical_uri'] );
	$assert( false !== $wpdb->insert( $test_prefix . 'ax_actors', $actor_anchor ), 'Separate Actor domain fixture inserted' );
	$wpdb->prefix = $test_prefix;
	delete_option( 'ax_op_object_identifiers_migrated' );
	$wpdb->update( axismundi_op_objects_table(), array( 'canonical_uri' => $uri . '/conflict' ), array( 'id' => 9 ) );
	$assert( ! axismundi_op_migrate_object_identifiers(), 'A conflicting anchor prevents migration completion' );
	$assert( 2 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $test_prefix . "ax_identities WHERE object_kind='object'" ) && null === axismundi_op_get_object_by_uri( $unbound['canonical_uri'] ), 'Failed migration rolls back copied rows and retains original anchors' );
	$wpdb->update( axismundi_op_objects_table(), array( 'canonical_uri' => null ), array( 'id' => 9 ) );
	$assert( axismundi_op_migrate_object_identifiers(), 'Migration completes on isolated data' );
	$copy = axismundi_op_get_object_by_uri( $uri );
	$assert( is_array( $copy ) && 9 === $copy['id'] && $uuid === $copy['uuid'] && 'fixture' === $copy['source_owner'], 'ID, UUID, URI and provenance are preserved' );
	$assert( null === axismundi_actors_get_identity_by_uri( $uri ), 'Object no longer lives in the Actor index' );
	$assert( 'unbound' === axismundi_op_get_object_by_uri( $unbound['canonical_uri'] )['source_kind'], 'Missing source provenance is not invented' );
	$assert( axismundi_op_migrate_object_identifiers() && $copy === axismundi_op_get_object_by_uri( $uri ), 'Replay preserves the complete row' );
	$new = axismundi_op_register_object( array( 'canonical_uri' => home_url( '/separation/' . wp_generate_uuid4() ), 'source_owner' => 'fixture', 'source_kind' => 'test', 'as2_type' => 'Note' ) );
	$assert( is_array( $new ) && 10 === $new['id'] && null === axismundi_actors_get_identity_by_uri( $new['canonical_uri'] ), 'New Object allocation does not touch Actor identifiers' );
	$assert( $actor_anchor['canonical_uri'] === axismundi_actors_get_identity( 10 )['canonical_uri'] && $new['canonical_uri'] === axismundi_op_get_object_by_identity( 10 )['canonical_uri'], 'The same numeric ID resolves independently in Actor and Object domains' );
	$assert( is_wp_error( axismundi_actors_register_identity( array( 'object_kind' => 'object' ) ) ) && is_wp_error( axismundi_actors_register_identity( array( 'object_kind' => 'folder' ) ) ), 'Legacy Object and folder producers are rejected' );
	$assert( is_wp_error( axismundi_op_read_object_jsonld( 9 ) ), 'Unknown source provenance is not readable' );
	$assert( is_wp_error( axismundi_op_register_object( array( 'canonical_uri' => $uri, 'origin' => 'remote', 'source_owner' => 'fixture', 'source_kind' => 'test', 'as2_type' => 'Note' ) ) ), 'Object origin is immutable' );
	$wpdb->query( 'ALTER TABLE ' . axismundi_op_objects_table() . ' CHANGE id identity_id bigint(20) unsigned NOT NULL' );
	$assert( axismundi_op_install_objects_schema() && $copy === axismundi_op_get_object_by_identity( 9 ), 'Legacy primary-key rename preserves the Object row' );
} finally {
	$wpdb->prefix = $prefix;
	if ( false === $marker ) { delete_option( 'ax_op_object_identifiers_migrated' ); } else { update_option( 'ax_op_object_identifiers_migrated', $marker ); }
	foreach ( $tables as $table ) { $wpdb->query( "DROP TABLE {$table}" ); }
}
printf( "PASS: %d identifier separation checks\n", $checks );
