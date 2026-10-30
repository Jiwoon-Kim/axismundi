<?php
/** Independent profiles and mandatory same-Account AddressBook anchors; rollback-only fixtures. */
defined( 'ABSPATH' ) || exit( 1 );
global $wpdb;
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { if ( ! $ok ) { throw new RuntimeException( $label ); } ++$checks; };
$users = get_users( array( 'number' => 2, 'fields' => 'ids' ) );
if ( count( $users ) < 2 || ! axismundi_contacts_ready() ) { throw new RuntimeException( 'Ready Contacts and two existing Users required.' ); }
$old_user = get_current_user_id();
$tables = array( axismundi_actors_actors_table(), axismundi_actors_managers_table(), \Axismundi\Jmap\accounts_table(), axismundi_contacts_books_table(), axismundi_contacts_cards_table(), axismundi_contacts_memberships_table(), axismundi_contacts_profiles_table(), axismundi_contacts_profile_documents_table(), axismundi_contacts_values_table(), axismundi_contacts_provenance_table() );
$snapshot = static fn() => array_map( static fn( $table ) => $wpdb->get_results( 'SELECT * FROM ' . $table, ARRAY_A ), $tables );
$before = $snapshot();
$cards = axismundi_contacts_cards_table(); $books = axismundi_contacts_books_table();
$assert( '15' === get_option( AXISMUNDI_CONTACTS_DB_VERSION_OPTION ), 'Profile split migration ready' );
$assert( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$cards} c LEFT JOIN {$books} b ON b.id=c.primary_address_book_id AND b.owner_actor_id=c.owner_actor_id WHERE b.id IS NULL" ), 'Every persisted ContactCard has a valid same-Account anchor' );
$assert( 0 === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_contacts_profiles_table() . ' p LEFT JOIN ' . axismundi_contacts_profile_documents_table() . ' d ON d.id=p.card_id WHERE d.id IS NULL' ), 'All profile bindings resolve to independent documents' );
$actor_id = 0;
try {
	wp_set_current_user( (int) $users[0] );
	$result = Axismundi_Contacts_Write::run( static function () use ( $wpdb, $assert, $users, &$actor_id, $cards, $books ) {
		$make = static function ( int $user ) use ( $wpdb ) {
			$uuid = wp_generate_uuid4(); $uri = home_url( '/actors/' . $uuid ); $now = current_time( 'mysql', true );
			if ( false === $wpdb->insert( axismundi_actors_actors_table(), array( 'uuid' => $uuid, 'canonical_uri' => $uri, 'canonical_uri_hash' => hash( 'sha256', $uri ), 'origin' => 'local', 'status' => 'public', 'actor_type' => 'Person', 'created_at' => $now, 'updated_at' => $now ) ) ) { throw new RuntimeException( $wpdb->last_error ); }
			$id = (int) $wpdb->insert_id;
			$wpdb->update( axismundi_actors_actors_table(), array( 'identity_id' => $id ), array( 'id' => $id ) );
			$wpdb->insert( axismundi_actors_managers_table(), array( 'identity_id' => $id, 'principal_kind' => 'wp_user', 'principal_id' => $user, 'user_id' => $user, 'role' => 'owner', 'created_at' => $now, 'updated_at' => $now ) );
			\Axismundi\Jmap\provision_account( axismundi_actors_get_by_identity( $id ) );
			return $id;
		};
		$actor_id = $make( (int) $users[0] ); $other = $make( (int) $users[1] );
		$profile = axismundi_contacts_create_profile_card( $actor_id );
		$assert( is_int( $profile ), 'Profile created without AddressBook' );
		$assert( array() === axismundi_contacts_books_for_actor( $actor_id ), 'Profile creation creates no AddressBook' );
		$assert( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$cards} WHERE owner_actor_id=%d", $actor_id ) ), 'Profile creation creates no ContactCard' );
		$document = axismundi_contacts_profile_document( $profile ); $uid = $document['uid'];
		$assert( str_starts_with( $uid, 'urn:uuid:' ), 'Independent profile owns stable UID' );
		$assert( $profile === axismundi_contacts_create_profile_card( $actor_id ), 'Profile creation idempotent' );
		$projection = axismundi_contacts_jscontact_card( axismundi_actors_get_by_identity( $actor_id ) );
		$assert( ! is_wp_error( $projection ) && $uid === $projection['uid'], 'JSContact projection reads independent profile UID' );
		$request = static function ( string $path, string $method, array $data = array() ) {
			$r = new WP_REST_Request( $method, '/' . axismundi_contacts_rest_namespace() . $path );
			if ( $data ) { $r->set_body_params( $data ); }
			return rest_do_request( $r );
		};
		$get = $request( '/profiles/' . $profile . '/draft', 'GET' );
		$assert( 200 === $get->get_status() && $uid === $get->get_data()['card']['uid'], 'Dedicated profile draft reads correct store' );
		$document['name']['full'] = 'Independent profile edit';
		$put = $request( '/profiles/' . $profile . '/draft', 'PUT', array( 'revision' => 1, 'card' => $document ) );
		$assert( 200 === $put->get_status(), 'Dedicated profile draft writes successfully' );
		$assert( 409 === $request( '/profiles/' . $profile . '/draft', 'PUT', array( 'revision' => 1, 'card' => $document ) )->get_status(), 'Profile stale revision rejected' );
		wp_set_current_user( (int) $users[1] );
		$assert( 404 === $request( '/profiles/' . $profile . '/draft', 'GET' )->get_status(), 'Other User cannot read private profile draft' );
		wp_set_current_user( (int) $users[0] );
		$book = axismundi_contacts_book_for_actor( $actor_id ); $book_id = (int) $book['id'];
		$contact = axismundi_contacts_save_card( $book_id, $document );
		$assert( is_int( $contact ), 'Profile document may be saved as an independent address-book copy' );
		$row = axismundi_contacts_get_card( $contact );
		$assert( $book_id === (int) $row['primary_address_book_id'], 'ContactCard has mandatory anchor from creation' );
		$assert( in_array( $book_id, axismundi_contacts_card_books( $contact ), true ), 'Primary membership visible to collection readers' );
		$assert( ! axismundi_contacts_remove_card_from_book( $contact, $book_id ), 'Last AddressBook cannot be removed' );
		$other_book = axismundi_contacts_book_for_actor( $other );
		$old = $wpdb->suppress_errors( true );
		try {
			$assert( false === $wpdb->update( $cards, array( 'primary_address_book_id' => null ), array( 'id' => $contact ) ), 'DB rejects NULL primary AddressBook' );
			$assert( false === $wpdb->update( $cards, array( 'primary_address_book_id' => 999999999 ), array( 'id' => $contact ) ), 'DB rejects nonexistent AddressBook' );
			$assert( false === $wpdb->update( $cards, array( 'primary_address_book_id' => $other_book['id'] ), array( 'id' => $contact ) ), 'DB rejects cross-Account anchor' );
			$assert( false === $wpdb->delete( $books, array( 'id' => $book_id ) ), 'DB rejects deleting referenced primary AddressBook' );
			$raw = $row; unset( $raw['id'], $raw['primary_address_book_id'] );
			$raw['uid'] = 'urn:uuid:' . wp_generate_uuid4();
			$raw_document = json_decode( $raw['card_json'], true ); $raw_document['uid'] = $raw['uid']; $raw['card_json'] = wp_json_encode( $raw_document );
			$assert( false === $wpdb->insert( $cards, $raw ), 'DB rejects ContactCard insertion without AddressBook' );
		} finally { $wpdb->suppress_errors( $old ); }
		$next = axismundi_contacts_create_book( $actor_id, 'Second' );
		$assert( is_int( $next ) && true === axismundi_contacts_add_card_to_book( $contact, $next ), 'Additional membership supported' );
		$assert( axismundi_contacts_remove_card_from_book( $contact, $book_id ), 'Primary membership may move to remaining book' );
		$assert( array( $next ) === axismundi_contacts_card_books( $contact ), 'Membership readers reflect moved anchor' );
		$copy = axismundi_contacts_card_document( $contact ); $copy['name']['full'] = 'Private copy edit';
		$assert( is_int( axismundi_contacts_save_card( $next, $copy, $contact ) ), 'AddressBook copy is independently editable' );
		$assert( 'Independent profile edit' === axismundi_contacts_profile_document( $profile )['name']['full'], 'ContactCard edits do not mutate public profile' );
		$assert( axismundi_contacts_delete_card( $contact ), 'ContactCard copy may be deleted' );
		$assert( $uid === axismundi_contacts_profile_document( $profile )['uid'] && $profile === axismundi_contacts_profile_card( $actor_id ), 'Deleting contact copy preserves profile and UID' );
		return new WP_Error( 'rollback_fixture', 'Rollback fixture transaction.' );
	} );
	$assert( is_wp_error( $result ) && 'rollback_fixture' === $result->get_error_code(), 'Fixture transaction rolled back' );
} finally { wp_set_current_user( $old_user ); }
$assert( $before === $snapshot(), 'All pre-existing rows preserved; no fixture leaks' );
echo "PASS profile-separation {$checks}\n";
