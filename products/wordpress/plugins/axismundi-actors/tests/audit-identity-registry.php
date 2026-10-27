<?php
/** Non-Actor allocation is no longer a service of Actors. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$before = $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_identities_table() . ' ORDER BY id', ARRAY_A );
$checks = 0;
foreach ( array( 'actor', 'object', 'folder', 'collection', 'media', 'activity', 'place' ) as $kind ) {
	$result = axismundi_actors_register_identity( array( 'object_kind' => $kind, 'uri_template' => home_url( '/retired-registry/{uuid}' ) ) );
	if ( ! is_wp_error( $result ) ) { throw new RuntimeException( 'Shared allocation survived for ' . $kind ); }
	++$checks;
}
foreach ( $before as $row ) {
	if ( 'actor' !== $row['object_kind'] ) { throw new RuntimeException( 'Non-Actor identity remains.' ); }
	$identity = axismundi_actors_get_identity_by_uri( $row['canonical_uri'] );
	if ( ! is_array( $identity ) || (int) $row['id'] !== $identity['identity_id'] ) { throw new RuntimeException( 'Actor lookup changed.' ); }
}
++ $checks;
if ( $before !== $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_identities_table() . ' ORDER BY id', ARRAY_A ) ) { throw new RuntimeException( 'Read/rejection audit changed Actor rows.' ); }
++ $checks;
printf( "PASS: %d Actor-only registry checks\n", $checks );
