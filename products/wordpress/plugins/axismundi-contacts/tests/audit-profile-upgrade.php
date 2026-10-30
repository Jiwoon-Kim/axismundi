<?php
/** Repeatable schema upgrade must not overwrite independent profiles or address-book copies. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$tables = array( axismundi_contacts_profile_documents_table(), axismundi_contacts_profiles_table(), axismundi_contacts_cards_table(), axismundi_contacts_books_table(), axismundi_contacts_memberships_table() );
$snapshot = static fn() => array_map( static fn( $table ) => $wpdb->get_results( 'SELECT * FROM ' . $table, ARRAY_A ), $tables );
$before = $snapshot();
if ( ! axismundi_contacts_install_schema() ) { throw new RuntimeException( 'Schema upgrade failed.' ); }
$after = $snapshot();
if ( $before !== $after ) {
	$changed = array();
	foreach ( $tables as $i => $table ) { if ( $before[$i] !== $after[$i] ) { $changed[] = $table; } }
	throw new RuntimeException( 'Repeated upgrade changed rows in: ' . implode( ', ', $changed ) );
}
$cards = axismundi_contacts_cards_table();
$fk = $wpdb->get_results( $wpdb->prepare( 'SELECT COLUMN_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND REFERENCED_TABLE_NAME=%s ORDER BY ORDINAL_POSITION', $cards, axismundi_contacts_books_table() ), ARRAY_A );
if ( array( array( 'COLUMN_NAME' => 'primary_address_book_id', 'REFERENCED_COLUMN_NAME' => 'id' ), array( 'COLUMN_NAME' => 'owner_actor_id', 'REFERENCED_COLUMN_NAME' => 'owner_actor_id' ) ) !== $fk ) { throw new RuntimeException( 'Same-Account primary AddressBook FK is missing.' ); }
echo "PASS profile-upgrade 3\n";
