<?php
/** Compare a trusted pre-body SQL backup with current Actor keys. Temporary SQL table only. */
defined( 'ABSPATH' ) || exit( 1 );
if ( ! class_exists( 'WP_CLI' ) ) { throw new RuntimeException( 'Use wp eval-file with AX_ACTOR_URI_BASELINE.' ); }
$path = getenv( 'AX_ACTOR_URI_BASELINE' );
if ( ! $path || ! is_readable( $path ) ) { throw new RuntimeException( 'A readable trusted pre-body SQL dump is required.' ); }
global $wpdb;
$source = $wpdb->prefix . 'ax_identities';
$temporary = 'ax_audit_legacy_actor_uris';
$input = fopen( $path, 'rb' );
if ( false === $input ) { throw new RuntimeException( 'Could not open baseline.' ); }
$created = false;
$in_create = false;
$ddl = '';
$inserted = 0;
$errors = $wpdb->suppress_errors( true );
try {
	while ( false !== ( $line = fgets( $input ) ) ) {
		if ( str_starts_with( $line, 'CREATE TABLE `' . $source . '` (' ) ) { $in_create = true; }
		if ( $in_create ) {
			$ddl .= $line;
			if ( str_ends_with( rtrim( $line ), ';' ) ) {
				$ddl = str_replace( 'CREATE TABLE `' . $source . '`', 'CREATE TEMPORARY TABLE `' . $temporary . '`', $ddl, $count );
				if ( 1 !== $count || false === $wpdb->query( $ddl ) ) { throw new RuntimeException( 'Could not create temporary baseline table.' ); }
				$created = true;
				$in_create = false;
			}
			continue;
		}
		if ( str_starts_with( $line, 'INSERT INTO `' . $source . '` ' ) ) {
			if ( ! $created ) { throw new RuntimeException( 'Baseline insert preceded schema.' ); }
			while ( ! str_ends_with( rtrim( $line ), ';' ) ) {
				$next = fgets( $input );
				if ( false === $next ) { throw new RuntimeException( 'Truncated baseline insert.' ); }
				$line .= $next;
			}
			$sql = str_replace( 'INSERT INTO `' . $source . '`', 'INSERT INTO `' . $temporary . '`', $line, $count );
			if ( 1 !== $count || false === $wpdb->query( $sql ) ) { throw new RuntimeException( 'Could not import temporary baseline rows.' ); }
			++$inserted;
		}
	}
	if ( ! $created || 0 === $inserted ) { throw new RuntimeException( 'Baseline has no shared identity rows.' ); }
	$columns = 'id,uuid,canonical_uri,canonical_uri_hash,origin';
	$before = $wpdb->get_results( "SELECT {$columns} FROM {$temporary} WHERE object_kind='actor' ORDER BY id", ARRAY_A );
	if ( ! $before || $wpdb->last_error ) { throw new RuntimeException( 'Cannot read baseline Actors.' ); }
	$table = axismundi_actors_actors_table();
	$after = $wpdb->get_results( "SELECT {$columns} FROM {$table} ORDER BY id", ARRAY_A );
	if ( $wpdb->last_error || $before !== $after ) { throw new RuntimeException( 'Actor ID, UUID, URI, URI hash or origin differs from baseline.' ); }
	printf( "PASS: %d Actor ID/UUID/URI/hash/origin rows preserved; sha256=%s\n", count( $before ), hash( 'sha256', json_encode( $before, JSON_THROW_ON_ERROR ) ) );
} finally {
	fclose( $input );
	if ( $created ) { $wpdb->query( 'DROP TEMPORARY TABLE `' . $temporary . '`' ); }
	$wpdb->suppress_errors( $errors );
}
