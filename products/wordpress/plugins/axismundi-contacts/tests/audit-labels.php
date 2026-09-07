<?php
/** Real-table regression audit for private filing. Run only in disposable wp-env. */
defined( 'ABSPATH' ) || exit( 1 );
require_once dirname( __DIR__ ) . '/includes/admin.php';
require_once dirname( __DIR__ ) . '/includes/card-detail.php';
$GLOBALS['ax_label_checks'] = array();
$users = array();
$owners = array();
$initial_user = get_current_user_id();
$initial_get = $_GET;
function ax_label_assert( string $name, bool $pass ) : void {
	$GLOBALS['ax_label_checks'][] = $pass;
	printf( "[%s] %s\n", $pass ? 'PASS' : 'FAIL', $name );
}
function ax_label_test_command( array $overrides = array() ) {
	return axismundi_contacts_label_command( array_merge( $GLOBALS['label_input'], $overrides ) );
}
try {
	global $wpdb;
	foreach ( array( 0, 1 ) as $which ) {
		$login = 'axlabel' . strtolower( wp_generate_password( 10, false, false ) );
		$user = wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
		if ( is_wp_error( $user ) ) { throw new RuntimeException( $user->get_error_message() ); }
		$users[] = (int) $user;
		$actor = axismundi_actors_ensure_for_user( $user );
		$owners[] = (int) $actor->get_identity_id();
		axismundi_actors_register_handle( $actor->get_identity_id(), $login );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	}
	wp_set_current_user( $users[0] );
	$owner = $owners[0];
	$home = axismundi_contacts_book_for_actor( $owner );
	$other = axismundi_contacts_create_book( $owners[1], 'Foreign secret label' );
	$profile = axismundi_contacts_profile_card( $owner );
	$GLOBALS['label_input'] = array( 'owner' => $owner, '_wpnonce' => wp_create_nonce( 'ax_contacts_labels_' . $owner ), 'operation' => 'create', 'label_name' => '업무 & Friends' );
	$label = ax_label_test_command();
	ax_label_assert( 'create makes one ordinary non-default AddressBook', is_int( $label ) && $label > 0 && 1 !== (int) axismundi_contacts_get_book( $label )['is_default'] );
	$second = ax_label_test_command( array( 'label_name' => 'Second label' ) );
	foreach ( array( '', '  ', str_repeat( 'x', 192 ), array( 'bad' ) ) as $bad ) {
		ax_label_assert( 'invalid label name is rejected', is_wp_error( ax_label_test_command( array( 'label_name' => $bad ) ) ) );
	}
	$doc = array( '@type' => 'Card', 'version' => '2.0', 'name' => array( 'full' => 'Label fixture' ), 'emails' => array( 'a' => array( 'address' => 'fixture@example.test' ) ), 'example.test:extension' => array( 'keep' => true ), 'localizations' => array( 'ko' => array( 'name/full' => '라벨 검사' ) ) );
	$a = axismundi_contacts_save_card_for_owner( $owner, $doc );
	$b = axismundi_contacts_save_card_for_owner( $owner, array( '@type' => 'Card', 'version' => '2.0', 'name' => array( 'full' => 'Other fixture' ) ) );
	$foreign = axismundi_contacts_save_card_for_owner( $owners[1], $doc );
	$before = axismundi_contacts_get_card( $a );
	$provenance = axismundi_contacts_card_provenance( $a );
	$values = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . axismundi_contacts_values_table() . ' WHERE card_id = %d', $a ), ARRAY_A );
	$saved_hooks = did_action( 'axismundi_contacts_card_saved' );
	$GLOBALS['label_input'] = array_merge( $GLOBALS['label_input'], array( 'operation' => 'add', 'label_id' => $label, 'cards' => array( $a, $b ) ) );
	ax_label_assert( 'a two-contact selection files both without copies', 2 === ax_label_test_command() && 2 === axismundi_contacts_directory_count( $owner, $label ) && 2 === axismundi_contacts_directory_count( $owner ) );
	$revision = (int) axismundi_contacts_get_book( $label )['revision'];
	ax_label_assert( 'reapplying is a no-op with no revision churn', 0 === ax_label_test_command() && $revision === (int) axismundi_contacts_get_book( $label )['revision'] );
	ax_label_assert( 'one Card can appear in two labels', 1 === ax_label_test_command( array( 'label_id' => $second, 'cards' => array( $a, $a ) ) ) && 2 === count( axismundi_contacts_card_books( $a ) ) );
	ax_label_assert( 'remove affects only the selected label', 1 === ax_label_test_command( array( 'operation' => 'remove', 'cards' => array( $a ) ) ) && array( $second ) === axismundi_contacts_card_books( $a ) );
	ax_label_assert( 'removing last label retains an unfiled Card', 1 === ax_label_test_command( array( 'operation' => 'remove', 'label_id' => $second, 'cards' => array( $a ) ) ) && array() === axismundi_contacts_card_books( $a ) && 2 === axismundi_contacts_directory_count( $owner ) );
	foreach ( array( array(), 'bad', array( array( 'bad' ) ), array( -$a ), array( $a . 'junk' ), array_fill( 0, 51, $a ), array( $a, $foreign ), array( $a, $profile ), array( $a, PHP_INT_MAX ) ) as $bad ) {
		ax_label_assert( 'invalid or mixed selection is refused before any write', is_wp_error( ax_label_test_command( array( 'cards' => $bad ) ) ) && array() === axismundi_contacts_card_books( $a ) );
	}
	foreach ( array( $other, (int) $home['id'], 0 ) as $bad_label ) {
		ax_label_assert( 'foreign/default/missing labels cannot be changed', is_wp_error( ax_label_test_command( array( 'label_id' => $bad_label ) ) ) );
	}
	ax_label_assert( 'invalid nonce cannot write', is_wp_error( ax_label_test_command( array( '_wpnonce' => 'invalid' ) ) ) );
	ax_label_assert( 'a nonce for a different owner cannot write', is_wp_error( ax_label_test_command( array( '_wpnonce' => wp_create_nonce( 'ax_contacts_labels_' . $owners[1] ) ) ) ) );
	wp_set_current_user( $users[1] );
	ax_label_assert( 'another administrator cannot file or list private labels', is_wp_error( ax_label_test_command() ) && array() === axismundi_contacts_labels( $owner ) );
	wp_set_current_user( 0 );
	ax_label_assert( 'anonymous access cannot file or list labels', is_wp_error( ax_label_test_command() ) && array() === axismundi_contacts_labels( $owner ) );
	wp_set_current_user( $users[0] );
	$revision = (int) axismundi_contacts_get_book( $label )['revision'];
	ax_label_assert( 'rename retains label id and members and advances revision', 1 === ax_label_test_command( array( 'operation' => 'rename', 'label_name' => 'Renamed label', 'label_revision' => $revision ) ) && 'Renamed label' === axismundi_contacts_get_book( $label )['name'] && 1 === axismundi_contacts_directory_count( $owner, $label ) );
	ax_label_assert( 'stale rename cannot overwrite another change', is_wp_error( ax_label_test_command( array( 'operation' => 'rename', 'label_name' => 'Stale', 'label_revision' => $revision ) ) ) );
	ax_label_assert( 'stale delete cannot remove a changed label', is_wp_error( ax_label_test_command( array( 'operation' => 'delete', 'confirm_remove' => '1', 'label_revision' => $revision ) ) ) );
	$revision++;
	ax_label_assert( 'delete requires explicit label-only confirmation', is_wp_error( ax_label_test_command( array( 'operation' => 'delete', 'label_revision' => $revision ) ) ) );
	// Fail the second insert: the first insert and revision must roll back too.
	$inserts = 0;
	$fault = static function ( $sql ) use ( &$inserts, $wpdb ) {
		if ( str_starts_with( $sql, 'INSERT INTO `' . axismundi_contacts_memberships_table() . '`' ) && ++$inserts === 2 ) { return 'INSERT INTO ax_contacts_test_missing_table VALUES (1)'; }
		return $sql;
	};
	$old_suppress = $wpdb->suppress_errors( true );
	add_filter( 'query', $fault );
	try { $failed = ax_label_test_command( array( 'label_id' => $second ) ); }
	finally { remove_filter( 'query', $fault ); $wpdb->suppress_errors( $old_suppress ); }
	ax_label_assert( 'storage failure rolls back the whole selection and revision', is_wp_error( $failed ) && 0 === axismundi_contacts_directory_count( $owner, $second ) && 2 === (int) axismundi_contacts_get_book( $second )['revision'] );
	$_GET = array( 'search' => 'fixture', 'contact_page' => '3' );
	ob_start(); axismundi_contacts_card_detail( $a, $label, $profile, $owner ); $html = html_entity_decode( ob_get_clean(), ENT_QUOTES, 'UTF-8' );
	ax_label_assert( 'detail filing and edit links retain directory context', str_contains( $html, 'name="return_item" value="' . $a . '"' ) && str_contains( $html, 'name="return_search" value="fixture"' ) && str_contains( $html, 'contact_page=3' ) && ! str_contains( $html, 'Foreign secret label' ) );
	ob_start(); axismundi_contacts_directory( $owner, (int) $home['id'], $profile, 'All contacts', 0 ); $html = ob_get_clean();
	ax_label_assert( 'list exposes native named checkboxes, current-page selection and endpoint scan', str_contains( $html, 'name="cards[]"' ) && str_contains( $html, 'Select all contacts on this page' ) && str_contains( $html, 'fixture@example.test' ) && ! str_contains( $html, '>Mine<' ) );
	ax_label_assert( 'delete removes only the label and its memberships', 1 === ax_label_test_command( array( 'operation' => 'delete', 'confirm_remove' => '1', 'label_revision' => $revision ) ) && array() === axismundi_contacts_get_book( $label ) && 2 === axismundi_contacts_directory_count( $owner ) && array() === axismundi_contacts_card_books( $b ) );
	$url = axismundi_contacts_label_return_url( array( 'owner' => $owner, 'return_group' => $label, 'return_search' => 'fixture', 'return_page' => 3, 'return_url' => 'https://evil.example/' ) );
	ax_label_assert( 'deleted-label return falls back to All contacts and retains safe context', str_starts_with( $url, admin_url( 'users.php' ) ) && ! str_contains( $url, 'group=' ) && str_contains( $url, 'search=fixture' ) && str_contains( $url, 'contact_page=3' ) );
	ax_label_assert( 'filing never changes canonical bytes/revision, provenance, indexes or emits Card saves', $before === axismundi_contacts_get_card( $a ) && $provenance === axismundi_contacts_card_provenance( $a ) && $values === $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . axismundi_contacts_values_table() . ' WHERE card_id = %d', $a ), ARRAY_A ) && $saved_hooks === did_action( 'axismundi_contacts_card_saved' ) );
	$org = axismundi_actors_create_managed_actor( array( 'owner_user_id' => $users[0], 'preferred_username' => 'axlabelorg' . strtolower( wp_generate_password( 8, false, false ) ), 'actor_type' => 'Organization', 'status' => 'public' ) );
	$org_id = (int) $org->get_identity_id();
	$owners[] = $org_id;
	axismundi_actors_set_acting_actor( $users[0], $org_id );
	ax_label_assert( 'switching Actor invalidates an old personal filing form', is_wp_error( ax_label_test_command( array( 'label_id' => $second ) ) ) );
	$org_input = array( 'owner' => $org_id, '_wpnonce' => wp_create_nonce( 'ax_contacts_labels_' . $org_id ), 'operation' => 'create', 'label_name' => 'Organization label' );
	ax_label_assert( 'the acting Organization manager can create a private label', is_int( axismundi_contacts_label_command( $org_input ) ) );
	axismundi_actors_add_manager( $org_id, $users[1], 'editor' );
	wp_set_current_user( $users[1] );
	axismundi_actors_set_acting_actor( $users[1], $org_id );
	$org_input['_wpnonce'] = wp_create_nonce( 'ax_contacts_labels_' . $org_id );
	ax_label_assert( 'a permitted manager can use the same command', is_int( axismundi_contacts_label_command( $org_input ) ) );
	axismundi_actors_remove_manager( $org_id, $users[1] );
	ax_label_assert( 'revoked management closes an already-issued form', is_wp_error( axismundi_contacts_label_command( $org_input ) ) );
} finally {
	$_GET = $initial_get;
	wp_set_current_user( $initial_user );
	foreach ( $users as $user ) { wp_delete_user( $user ); }
	foreach ( $owners as $owner ) {
		axismundi_contacts_purge_actor( $owner );
		$wpdb->delete( axismundi_actors_managers_table(), array( 'identity_id' => $owner ), array( '%d' ) );
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $owner ), array( '%d' ) );
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $owner ), array( '%d' ) );
	}
}
$checks = $GLOBALS['ax_label_checks'];
$failures = count( array_filter( $checks, static fn( bool $pass ) : bool => ! $pass ) );
printf( "\n== %d checks, %d failed ==\n", count( $checks ), $failures );
WP_CLI::halt( $failures ? 1 : 0 );
