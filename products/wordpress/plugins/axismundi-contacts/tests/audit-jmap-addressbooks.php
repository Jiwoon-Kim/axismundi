<?php
/** Account-bound AddressBook foundation. Fixtures and writes are rolled back together. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; };
$users = get_users( array( 'number' => 2, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
if ( count( $users ) < 2 || ! axismundi_contacts_ready() ) { throw new RuntimeException( 'Two existing Users and ready Contacts/JMAP required.' ); }
$old_user = get_current_user_id();
$owner = (int) $users[0]; $other = (int) $users[1];
$tables = array( axismundi_actors_actors_table(), axismundi_actors_managers_table(), \Axismundi\Jmap\accounts_table(), axismundi_contacts_books_table(), axismundi_contacts_cards_table(), axismundi_contacts_profiles_table() );
$snapshot = static function () use ( $wpdb, $tables ) { return array_map( static fn( $table ) => $wpdb->get_results( 'SELECT * FROM ' . $table, ARRAY_A ), $tables ); };
$before = $snapshot();
$network = 0;
$deny = static function () use ( &$network ) { ++$network; return new WP_Error( 'no_network', 'No network in AddressBook audit.' ); };
add_filter( 'pre_http_request', $deny, PHP_INT_MAX );
try {
	$registry = \Axismundi\Jmap\runtime_registry();
	$type = $registry->type( 'AddressBook' );
	$assert( AXISMUNDI_CONTACTS_JMAP_CAPABILITY === $type['capability'], 'Contacts runtime-registers AddressBook under contacts capability' );
	$assert( str_contains( $type['reference'], 'rfc9610' ), 'AddressBook records RFC 9610 provenance' );
	$assert( ! isset( $registry->account_providers()[ AXISMUNDI_CONTACTS_JMAP_CAPABILITY ] ), 'No premature Contacts Account capability advertisement' );
	$assert( null === $registry->method( 'AddressBook/set' ), 'Internal creation is not advertised as complete AddressBook/set' );
	$frozen = false;
	try { $registry->register_type( 'AnotherBook', AXISMUNDI_CONTACTS_JMAP_CAPABILITY, 'https://example.org/spec' ); } catch ( LogicException $error ) { $frozen = true; }
	$assert( $frozen, 'Frozen registry refuses late type registration' );
	$test_registry = new \Axismundi\Jmap\Registry();
	$rejected = false;
	try { $test_registry->register_type( 'AddressBook', AXISMUNDI_CONTACTS_JMAP_CAPABILITY, 'https://example.org/spec' ); } catch ( LogicException $error ) { $rejected = true; }
	$assert( $rejected, 'Type requires declared capability' );
	$test_registry->register_capability( AXISMUNDI_CONTACTS_JMAP_CAPABILITY );
	$test_registry->register_type( 'AddressBook', AXISMUNDI_CONTACTS_JMAP_CAPABILITY, 'https://example.org/spec' );
	$rejected = false;
	try { $test_registry->register_type( 'AddressBook', AXISMUNDI_CONTACTS_JMAP_CAPABILITY, 'https://example.org/spec' ); } catch ( LogicException $error ) { $rejected = true; }
	$assert( $rejected, 'Duplicate type registration is rejected' );
	wp_set_current_user( $owner );
	$result = Axismundi_Contacts_Write::run( static function () use ( $wpdb, $assert, $owner, $other ) {
		$make = static function ( string $type, bool $local = true ) use ( $wpdb, $owner ) {
			$uuid = wp_generate_uuid4(); $now = current_time( 'mysql', true );
			$uri = ( $local ? home_url( '/actors/' ) : 'https://addressbook.example/actors/' ) . $uuid;
			if ( false === $wpdb->insert( axismundi_actors_actors_table(), array( 'uuid' => $uuid, 'canonical_uri' => $uri, 'canonical_uri_hash' => hash( 'sha256', $uri ), 'origin' => $local ? 'local' : 'remote', 'status' => 'public', 'actor_type' => $type, 'created_at' => $now, 'updated_at' => $now ) ) ) { throw new RuntimeException( $wpdb->last_error ); }
			$id = (int) $wpdb->insert_id;
			$wpdb->update( axismundi_actors_actors_table(), array( 'identity_id' => $id ), array( 'id' => $id ) );
			if ( $local ) {
				$wpdb->insert( axismundi_actors_managers_table(), array( 'identity_id' => $id, 'principal_kind' => 'wp_user', 'principal_id' => $owner, 'user_id' => $owner, 'role' => 'owner', 'created_at' => $now, 'updated_at' => $now ) );
				if ( ! \Axismundi\Jmap\provision_account( axismundi_actors_get_by_identity( $id ) ) ) { throw new RuntimeException( 'Account provisioning fixture failed' ); }
			}
			return $id;
		};
		foreach ( array( 'Person', 'Group', 'Organization', 'Service', 'Application' ) as $kind ) { $assert( axismundi_contacts_type_keeps_books( $kind ), $kind . ' Account may keep AddressBooks' ); }
		$a = $make( 'Application' ); $b = $make( 'Group' ); $remote = $make( 'Person', false );
		$aid = \Axismundi\Jmap\account_id_for_actor( $a );
		$assert( 'a' . $a === $aid, 'Account ID comes from JMAP store' );
		$assert( null === \Axismundi\Jmap\account_id_for_actor( $remote ), 'Remote Actor has no local Account' );
		$assert( array() === axismundi_contacts_books_for_account( $aid ), 'Account read creates no default book' );
		$assert( is_wp_error( axismundi_contacts_create_book_for_account( $aid, '' ) ), 'Unnamed additional book rejected' );
		$assert( is_wp_error( axismundi_contacts_create_book_for_account( $aid, str_repeat( 'x', 192 ) ) ), 'Oversized name rejected before insert' );
		$default = axismundi_contacts_create_book_for_account( $aid, '', true );
		$assert( is_int( $default ) && $default > 0, 'Application Account default book explicitly created' );
		$assert( $default === axismundi_contacts_create_book_for_account( $aid, '', true ), 'Default creation is idempotent' );
		$book = axismundi_contacts_create_book_for_account( $aid, 'Work' );
		$assert( is_int( $book ) && $book !== $default, 'Named book created in same Account' );
		$books = axismundi_contacts_books_for_account( $aid );
		$assert( 2 === count( $books ) && array( $a ) === array_values( array_unique( array_map( 'intval', array_column( $books, 'owner_actor_id' ) ) ) ), 'Stored ownership matches JMAP Account Actor ID' );
		$assert( array() === axismundi_contacts_books_for_account( 'a' . $b ), 'Other Account remains isolated and unprovisioned for Contacts' );
		$group_book = axismundi_contacts_create_book_for_account( 'a' . $b, '', true );
		$assert( is_int( $group_book ), 'Group Account may explicitly create a default book' );
		$assert( 0 === axismundi_contacts_profile_card( $a ) && 0 === axismundi_contacts_profile_card( $b ), 'Creating Group/Application AddressBooks does not auto-publish profile Cards' );
		wp_set_current_user( $other );
		$assert( is_wp_error( axismundi_contacts_books_for_account( $aid ) ), 'Unrelated User cannot list Account books' );
		$assert( is_wp_error( axismundi_contacts_create_book_for_account( $aid, 'Stolen' ) ), 'Unrelated User cannot create books' );
		wp_set_current_user( 0 );
		$assert( is_wp_error( axismundi_contacts_books_for_account( $aid ) ), 'Anonymous Account access denied' );
		wp_set_current_user( $owner );
		foreach ( array( '', (string) $a, 'a0', 'a0' . $a, 'a99999999999999999999999999999', 'a' . $remote ) as $invalid ) {
			$assert( is_wp_error( axismundi_contacts_create_book_for_account( $invalid, 'Invalid' ) ), 'Explicit invalid/nonlocal Account denied: ' . $invalid );
		}
		foreach ( array( 'disabled', 'tombstone' ) as $status ) {
			$wpdb->update( axismundi_actors_actors_table(), array( 'status' => $status ), array( 'id' => $a ) );
			$assert( is_wp_error( axismundi_contacts_books_for_account( $aid ) ), $status . ' Account access denied' );
		}
		$wpdb->update( axismundi_actors_actors_table(), array( 'status' => 'public' ), array( 'id' => $a ) );
		$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $a ) );
		$assert( is_wp_error( axismundi_contacts_create_book_for_account( $aid, 'Revoked' ) ), 'Revocation applies before writes' );
		$wpdb->delete( \Axismundi\Jmap\accounts_table(), array( 'actor_id' => $b ) );
		$assert( is_wp_error( axismundi_contacts_book_for_actor( $b ) ), 'Legacy Actor-ID path cannot bypass absent JMAP Account' );
		$missing = static fn() => 'unavailable';
		add_filter( 'pre_option_ax_jmap_db_version', $missing );
		try { $assert( is_wp_error( axismundi_contacts_create_book_for_account( $aid, 'Unavailable' ) ), 'Unavailable JMAP fails closed' ); }
		finally { remove_filter( 'pre_option_ax_jmap_db_version', $missing ); }
		return new WP_Error( 'audit_rollback', 'Roll back all fixtures and writes.' );
	} );
	$assert( is_wp_error( $result ) && 'audit_rollback' === $result->get_error_code(), 'Audit transaction explicitly rolled back' );
} finally {
	remove_filter( 'pre_http_request', $deny, PHP_INT_MAX );
	wp_set_current_user( $old_user );
}
$assert( 0 === $network, 'No remote fetching or synchronization' );
$assert( $before === $snapshot(), 'Existing Actor, principal, Account, book, Card and profile rows preserved' );
echo "PASS jmap-addressbooks {$checks}\n";
