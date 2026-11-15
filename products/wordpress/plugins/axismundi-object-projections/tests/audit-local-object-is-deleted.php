<?php
/** Dev-only: wp eval-file this file. */
defined( 'ABSPATH' ) || exit( 1 );

$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks;
};
$assert( ! axismundi_op_local_object_is_deleted( array( 'object_status' => 'active', 'object_type' => 'Tombstone' ) ), 'An authored Tombstone is not deleted' );
$assert( axismundi_op_local_object_is_deleted( array( 'object_status' => 'tombstone', 'object_type' => 'Note' ) ), 'Deletion does not depend on the authored type' );
$assert( ! axismundi_op_local_object_is_deleted( array() ), 'Missing lifecycle data is not evidence of deletion' );

$plugins = dirname( __DIR__, 2 );
$paths = array(
	'axismundi-object-projections' => array( 'local-objects', 'application-object-read', 'activitystreams-api',
		'local-object-route', 'renderer', 'replies-collection', 'integrations/actors', 'admin' ),
	'axismundi-activities' => array( 'c2s-local-object', 'c2s-outbox', 'admin-workflows', 'community-replies', 'community-posts' ),
	'axismundi-actors' => array( 'featured' ),
	'axismundi-media-library' => array( 'relations' ),
);
foreach ( $paths as $plugin => $files ) {
	foreach ( $files as $file ) {
		$path = "$plugins/$plugin/includes/$file.php";
		$source = file_get_contents( $path );
		$assert( is_string( $source ) && '' !== $source, "Read audit source: $path" );
		foreach ( explode( "\n", $source ) as $line ) {
			if ( ! str_contains( $line, 'object_status' ) || ! str_contains( $line, "'tombstone'" ) ) { continue; }
			// The predicate, SQL transition, and remote cache checks are not local read sites.
			if ( 'local-objects' === $file && ( str_starts_with( trim( $line ), "return 'tombstone' ===" ) || str_contains( $line, '$wpdb->update(' ) ) ) { continue; }
			if ( 'featured' === $file && str_contains( $line, '$cached[' ) && ! str_contains( $line, '$local[' ) ) { continue; }
			throw new RuntimeException( "Raw local deletion check in $path: " . trim( $line ) );
		}
		++$checks;
	}
}
printf( "== %d checks, 0 failed ==\n", $checks );
