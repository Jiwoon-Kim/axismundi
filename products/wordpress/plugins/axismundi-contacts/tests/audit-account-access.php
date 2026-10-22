<?php
/** Development-only Account authorization audit: wp eval-file. @package AxismundiContacts */
defined( 'ABSPATH' ) || exit( 1 );
require_once dirname( __DIR__ ) . '/includes/admin.php';
$users = array();
$fixtures = array();
$cards = array();
$books = array();
$original_user = get_current_user_id();
$original_get = $_GET;
$_GET = array();
$checks = 0;
$assert = static function ( bool $condition, string $label ) use ( &$checks ) : void {
	++$checks;
	if ( ! $condition ) {
		throw new RuntimeException( $label );
	}
};
$draft = static function ( int $id, string $method = 'GET', array $data = array() ) {
	$request = new WP_REST_Request( $method, '/' . axismundi_contacts_rest_namespace() . '/cards/' . $id . '/draft' );
	if ( $data ) {
		$request->set_body_params( $data );
	}
	return rest_do_request( $request );
};
try {
	global $wpdb;
	foreach ( array( 'author', 'administrator' ) as $role ) {
		$id = wp_insert_user( array( 'user_login' => 'axcontactaccess' . strtolower( wp_generate_password( 12, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => $role ) );
		if ( is_wp_error( $id ) ) {
			throw new RuntimeException( $id->get_error_message() );
		}
		$users[] = (int) $id;
	}
	wp_set_current_user( $users[0] );
	foreach ( array( 'Person', 'Person', 'Organization' ) as $index => $type ) {
		$actor = axismundi_actors_create_local( array( 'actor_type' => $type, 'actor_scope' => 'Person' === $type ? 'user' : 'managed', 'personal_owner_user_id' => 'Person' === $type ? $users[0] : null, 'initial_owner_user_id' => $users[0], 'display_name' => 'Contacts Account ' . $index ) );
		if ( ! $actor instanceof Axismundi_Actor ) {
			throw new RuntimeException( 'Account fixture failed' );
		}
		$id = $actor->get_identity_id();
		$fixtures[] = $id;
		$book = axismundi_contacts_book_for_actor( $id );
		if ( is_wp_error( $book ) ) {
			throw new RuntimeException( $book->get_error_message() );
		}
		$card = axismundi_contacts_save_card( (int) $book['id'], array( '@type' => 'Card', 'kind' => 'individual', 'name' => array( 'full' => 'Private ' . $index ) ) );
		if ( is_wp_error( $card ) ) {
			throw new RuntimeException( $card->get_error_message() );
		}
		$cards[] = (int) $card;
		$books[] = (int) $book['id'];
		$assert( null === $actor->get_local_user_id() && ! $actor->is_handle_locked(), 'No legacy WP binding or publishing activation required' );
		$assert( axismundi_contacts_can_read_account( $id, $users[0] ) && axismundi_contacts_can_write_account( $id, $users[0] ), 'Owner can read and write Account contacts' );
		$assert( ! axismundi_contacts_can_use_book( $id, $users[1] ), 'Unrelated administrator has no implicit access' );
	}
	axismundi_actors_set_workspace( $users[0], $fixtures[1] );
	$assert( $fixtures[1] === axismundi_contacts_current_account()->get_identity_id() && null === axismundi_actors_acting_actor(), 'Contacts selects data Account without a publishing Actor' );
	$assert( $fixtures[1] === axismundi_contacts_current_book()['actor']->get_identity_id(), 'Contacts screen follows Account selection' );
	$label = axismundi_contacts_label_command( array( 'owner' => $fixtures[1], '_wpnonce' => wp_create_nonce( 'ax_contacts_labels_' . $fixtures[1] ), 'operation' => 'create', 'label_name' => 'Account label' ) );
	$assert( is_int( $label ) && $label > 0, 'Handle-less Account can execute its own filing command' );
	$page = axismundi_contacts_directory_page( $fixtures[1] );
	$assert( array( $cards[1] ) === array_map( static fn( $row ) => (int) $row['id'], $page['cards'] ), 'Directory isolated to selected Account, not another personal Account' );
	$assert( 200 === $draft( $cards[1] )->get_status(), 'Draft GET accepts additional personal Account owner' );
	$document = axismundi_contacts_card_document( $cards[1] );
	$document['name']['full'] = 'Edited private Card';
	$assert( 200 === $draft( $cards[1], 'PUT', array( 'revision' => (int) axismundi_contacts_get_card( $cards[1] )['revision'], 'card' => $document ) )->get_status(), 'Draft PUT accepts owner through write permission service' );
	wp_set_current_user( $users[1] );
	$assert( 404 === $draft( $cards[1] )->get_status() && 404 === $draft( $cards[1], 'PUT', array( 'revision' => 1, 'card' => $document ) )->get_status(), 'Unauthorized IDs do not reveal Cards through GET or PUT' );
	$assert( array() === axismundi_contacts_directory_page( $fixtures[1] )['cards'], 'Unauthorized directory is empty' );
	foreach ( array( 'owner', 'manager', 'editor' ) as $role ) {
		axismundi_actors_write_manager_row( $fixtures[2], $users[1], $role );
		$assert( axismundi_contacts_can_read_account( $fixtures[2], $users[1] ) && axismundi_contacts_can_write_account( $fixtures[2], $users[1] ), 'Existing managed operating role retains Contacts access: ' . $role );
	}
	axismundi_actors_set_workspace( $users[1], $fixtures[2] );
	$assert( 200 === $draft( $cards[2] )->get_status(), 'Delegated Account draft accessible' );
	$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $fixtures[2], 'user_id' => $users[1] ) );
	$assert( ! axismundi_contacts_can_use_book( $fixtures[2], $users[1] ) && 404 === $draft( $cards[2] )->get_status(), 'Revocation applies on next request' );
	$assert( 404 === $draft( $cards[2], 'PUT', array( 'revision' => 1, 'card' => $document ) )->get_status(), 'Revocation also closes draft writes' );
	$assert( null === axismundi_contacts_current_account(), 'Stale selection does not create unauthorized workspace' );
	axismundi_actors_write_manager_row( $fixtures[1], $users[1], 'editor' );
	$assert( 200 === $draft( $cards[1] )->get_status(), 'Explicit delegation also works for personally owned Account' );
	wp_set_current_user( $users[0] );
	foreach ( array( 'disabled', 'tombstone' ) as $status ) {
		axismundi_actors_set_status( $fixtures[1], $status );
		$assert( ! axismundi_contacts_can_read_account( $fixtures[1], $users[0] ) && ! axismundi_contacts_can_write_account( $fixtures[1], $users[0] ), 'Account state denies both operations: ' . $status );
		$assert( 404 === $draft( $cards[1] )->get_status(), 'State excludes draft: ' . $status );
	}
	axismundi_actors_set_status( $fixtures[1], 'internal' );
	$assert( axismundi_contacts_can_use_book( $fixtures[1], $users[0] ), 'Internal Account still usable without publication' );
	$not_ready = static fn() => 'incomplete';
	add_filter( 'pre_option_ax_actors_account_access_version', $not_ready );
	try {
		$assert( ! axismundi_contacts_can_use_book( $fixtures[1], $users[0] ), 'Incomplete migration fails closed' );
	} finally {
		remove_filter( 'pre_option_ax_actors_account_access_version', $not_ready );
	}
	$assert( ! axismundi_contacts_can_read_account( 0, $users[0] ) && ! axismundi_contacts_can_write_account( $fixtures[0], 0 ), 'Invalid Account and anonymous User denied' );
	$assert( ! axismundi_contacts_type_keeps_books( 'Group' ), 'Type-based book policy unchanged in this slice' );
	printf( "PASS: %d Contacts Account access checks\n", $checks );
} finally {
	$_GET = $original_get;
	wp_set_current_user( $original_user );
	foreach ( array_reverse( $fixtures ) as $id ) {
		axismundi_contacts_purge_actor( $id );
		$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $id ) );
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $id ) );
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $id ) {
		wp_delete_user( $id );
	}
}
