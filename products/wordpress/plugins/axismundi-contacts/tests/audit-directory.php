<?php
/** Saved-contact retrieval, exercised against real WordPress tables (dev-only). */
defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/admin.php';
require_once dirname( __DIR__ ) . '/includes/card-detail.php';
require_once dirname( __DIR__ ) . '/includes/card-editor.php';

$GLOBALS['ax_directory_results'] = array();
$users   = array();
$owners  = array();
$initial_user = get_current_user_id();
$initial_get  = $_GET;

function ax_directory_assert( string $label, bool $condition ) : void {
	$GLOBALS['ax_directory_results'][] = $condition;
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

function ax_directory_ids( array $result ) : array {
	return array_map( 'intval', array_column( $result['cards'], 'id' ) );
}

try {
	global $wpdb;
	foreach ( array( 0, 1 ) as $which ) {
		$login = 'axdir' . strtolower( wp_generate_password( 10, false, false ) );
		$user = wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
		if ( is_wp_error( $user ) ) {
			throw new RuntimeException( $user->get_error_message() );
		}
		$users[] = (int) $user;
		$actor   = axismundi_actors_ensure_for_user( (int) $user );
		$owners[] = (int) $actor->get_identity_id();
		axismundi_actors_register_handle( $actor->get_identity_id(), $login );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	}
	wp_set_current_user( $users[0] );
	$owner   = $owners[0];
	$book    = axismundi_contacts_book_for_actor( $owner );
	$group   = (int) axismundi_contacts_create_book( $owner, 'Directory fixture group' );
	$other   = axismundi_contacts_book_for_actor( $owners[1] );
	$profile = (int) axismundi_contacts_create_profile_card( $owner );
	$ids     = array();
	for ( $i = 0; $i < 205; $i++ ) {
		$saved = axismundi_contacts_save_card( (int) $book['id'], array(
			'@type' => 'Card', 'version' => '2.0', 'kind' => 'individual',
			'name' => array( '@type' => 'Name', 'full' => sprintf( 'Directory %03d', $i ) ),
		) );
		if ( is_wp_error( $saved ) ) {
			throw new RuntimeException( $saved->get_error_message() );
		}
		$ids[] = (int) $saved;
	}
	$document = array(
		'@type' => 'Card', 'version' => '2.0', 'kind' => 'individual',
		'name' => array( '@type' => 'Name', 'full' => "김지운 100%_ O'Neil" ),
		'emails' => array(
			'a' => array( '@type' => 'EmailAddress', 'address' => 'directory@example.test' ),
			'b' => array( '@type' => 'EmailAddress', 'address' => 'directory+work@example.test' ),
		),
		'phones' => array( 'a' => array( '@type' => 'Phone', 'number' => 'tel:+821012345678' ) ),
		'links' => array( 'a' => array( '@type' => 'Link', 'uri' => 'https://directory.example.test/portfolio' ) ),
		'example.test:retained' => array( 'untouched' => true ),
	);
	$special = axismundi_contacts_save_card_for_owner( $owner, $document );
	if ( is_wp_error( $special ) ) {
		throw new RuntimeException( $special->get_error_message() );
	}
	$special = (int) $special;
	$before  = axismundi_contacts_get_card( $special );
	ax_directory_assert( 'All contacts includes an unfiled Card and excludes the separate profile before counting', 206 === axismundi_contacts_directory_count( $owner ) );
	$first = axismundi_contacts_directory_page( $owner );
	$last  = axismundi_contacts_directory_page( $owner, 0, '', 999 );
	ax_directory_assert( 'a large directory has a complete count and bounded first page', 206 === $first['total'] && 50 === count( $first['cards'] ) && 5 === $first['pages'] );
	ax_directory_assert( 'an out-of-range page lands on the last page, including Cards beyond the old 200-row cap', 5 === $last['page'] && 6 === count( $last['cards'] ) && in_array( $ids[204], ax_directory_ids( $last ), true ) );
	$all = array();
	for ( $page = 1; $page <= 5; $page++ ) {
		$all = array_merge( $all, ax_directory_ids( axismundi_contacts_directory_page( $owner, 0, '', $page ) ) );
	}
	ax_directory_assert( 'every saved Card appears exactly once across pages and the profile never appears', 206 === count( array_unique( $all ) ) && ! in_array( $profile, $all, true ) );
	ax_directory_assert( 'search reaches a name beyond the former cap', array( $ids[204] ) === ax_directory_ids( axismundi_contacts_directory_page( $owner, 0, 'directory 204' ) ) );
	ax_directory_assert( 'a Unicode name is found', array( $special ) === ax_directory_ids( axismundi_contacts_directory_page( $owner, 0, '김지운' ) ) );
	ax_directory_assert( 'partial email search is case-insensitive and matching several entries yields one Card', array( $special ) === ax_directory_ids( axismundi_contacts_directory_page( $owner, 0, 'DIRECTORY@' ) ) && 1 === axismundi_contacts_directory_page( $owner, 0, 'example.test' )['total'] );
	ax_directory_assert( 'national phone formatting finds the stored international number', array( $special ) === ax_directory_ids( axismundi_contacts_directory_page( $owner, 0, '010-1234-5678' ) ) );
	ax_directory_assert( 'a phone suffix finds the same Card without claiming identity', array( $special ) === ax_directory_ids( axismundi_contacts_directory_page( $owner, 0, '5678' ) ) );
	ax_directory_assert( 'digits in arbitrary text do not turn into a phone search', 0 === axismundi_contacts_directory_page( $owner, 0, 'absent5678@example.test' )['total'] );
	ax_directory_assert( 'URLs are found locally', array( $special ) === ax_directory_ids( axismundi_contacts_directory_page( $owner, 0, '/portfolio' ) ) );
	ax_directory_assert( 'SQL wildcard characters are literal search text', array( $special ) === ax_directory_ids( axismundi_contacts_directory_page( $owner, 0, '%_' ) ) );
	ax_directory_assert( 'quotes remain search text', array( $special ) === ax_directory_ids( axismundi_contacts_directory_page( $owner, 0, "O'Neil" ) ) && 0 === axismundi_contacts_directory_page( $owner, 0, "' OR 1=1 --" )['total'] );
	ax_directory_assert( 'an empty result resets pagination safely', 0 === axismundi_contacts_directory_page( $owner, 0, 'not present', 999 )['total'] && 1 === axismundi_contacts_directory_page( $owner, 0, 'not present', 999 )['page'] );
	ax_directory_assert( 'negative pages and excessive page sizes are bounded', 1 === axismundi_contacts_directory_page( $owner, 0, '', -2, 999 )['page'] && 100 === count( axismundi_contacts_directory_page( $owner, 0, '', 1, 999 )['cards'] ) );
	axismundi_contacts_add_card_to_book( $special, $group );
	axismundi_contacts_add_card_to_book( $special, (int) $book['id'] );
	ax_directory_assert( 'filing into two groups does not duplicate All contacts', 206 === axismundi_contacts_directory_count( $owner ) && 1 === axismundi_contacts_directory_count( $owner, $group ) );
	ax_directory_assert( 'group search stays within its membership', array( $special ) === ax_directory_ids( axismundi_contacts_directory_page( $owner, $group, '김' ) ) && 0 === axismundi_contacts_directory_page( $owner, $group, 'Directory 204' )['total'] );
	ax_directory_assert( 'a foreign group cannot be used as a read scope', 0 === axismundi_contacts_directory_page( $owner, (int) $other['id'] )['total'] );
	wp_set_current_user( $users[1] );
	ax_directory_assert( 'even another administrator cannot read the owner rows or count', array() === axismundi_contacts_directory_page( $owner )['cards'] && 0 === axismundi_contacts_directory_count( $owner ) );
	wp_set_current_user( 0 );
	ax_directory_assert( 'anonymous queries return no rows or counts', 0 === axismundi_contacts_directory_page( $owner, 0, 'Directory' )['total'] );
	wp_set_current_user( $users[0] );
	ax_directory_assert( 'retrieval did not mutate the canonical Card, its revision or extension', $before === axismundi_contacts_get_card( $special ) );

	$structured = axismundi_contacts_save_card_for_owner( $owner, array(
		'@type' => 'Card', 'version' => '2.0',
		'name' => array( '@type' => 'Name', 'components' => array( array( 'kind' => 'given', 'value' => 'Structured' ), array( 'kind' => 'surname', 'value' => 'Contact' ) ) ),
	) );
	if ( is_wp_error( $structured ) ) {
		throw new RuntimeException( $structured->get_error_message() );
	}
	ob_start();
	axismundi_contacts_card_detail( (int) $structured, 0, $profile, $owner );
	$detail = ob_get_clean();
	ax_directory_assert( 'a result with only structured name parts keeps its name on the detail screen', str_contains( $detail, 'Structured Contact</h1>' ) );
	wp_set_current_user( $users[1] );
	ob_start();
	axismundi_contacts_card_detail( $special, 0, 0, $owners[1] );
	$foreign_detail = ob_get_clean();
	ob_start();
	axismundi_contacts_card_detail( 0, 0, 0, $owners[1] );
	$missing_detail = ob_get_clean();
	ax_directory_assert( 'opening a foreign Card is indistinguishable from a missing Card', $foreign_detail === $missing_detail && ! str_contains( $foreign_detail, 'directory@example.test' ) );
	ob_start();
	axismundi_contacts_card_detail( $special, 0, $profile, $owner );
	ax_directory_assert( 'passing the correct owner still cannot bypass the current-user gate', $missing_detail === ob_get_clean() );
	ob_start();
	axismundi_contacts_card_editor_screen( $special, 0 );
	$foreign_editor = ob_get_clean();
	ob_start();
	axismundi_contacts_card_editor_screen( 0, 0 );
	$missing_editor = ob_get_clean();
	ax_directory_assert( 'the foreign editor reveals neither a name nor a mount and matches a missing Card',
		$foreign_editor === $missing_editor
		&& ! str_contains( $foreign_editor, '김지운' )
		&& ! str_contains( $foreign_editor, 'id="ax-contacts-card-editor"' )
	);
	$_GET = array( 'page' => 'axismundi-contacts', 'action' => 'edit', 'item' => (string) $special );
	ob_start();
	axismundi_contacts_render_screen();
	$foreign_edit_route = ob_get_clean();
	$_GET['item'] = (string) PHP_INT_MAX;
	ob_start();
	axismundi_contacts_render_screen();
	$missing_edit_route = ob_get_clean();
	ax_directory_assert( 'the complete foreign edit-URL dispatch gives the same missing-Card response',
		$foreign_edit_route === $missing_edit_route
		&& str_contains( $foreign_edit_route, 'That contact does not exist.' )
	);
	wp_set_current_user( 0 );
	ob_start();
	axismundi_contacts_card_editor_screen( $special, $group );
	ax_directory_assert( 'an anonymous editor render cannot bypass the gate by naming the owning group', $missing_editor === ob_get_clean() );
	wp_set_current_user( $users[0] );
	ob_start();
	axismundi_contacts_card_editor_screen( (int) $structured, 0 );
	$own_editor = ob_get_clean();
	ax_directory_assert( 'the current owner still sees the name and editor mount',
		str_contains( $own_editor, 'Structured Contact</h1>' )
		&& str_contains( $own_editor, 'id="ax-contacts-card-editor"' )
	);

	$_GET = array( 'search' => 'Directory', 'contact_page' => '3' );
	ob_start();
	axismundi_contacts_directory( $owner, (int) $book['id'], $profile, 'All contacts', 0 );
	$html = html_entity_decode( ob_get_clean(), ENT_QUOTES, 'UTF-8' );
	ax_directory_assert( 'the rendered form names its local scope and does not retain the old page on a new search', str_contains( $html, 'Search saved contacts' ) && str_contains( $html, 'role="search"' ) && ! str_contains( $html, 'name="contact_page"' ) );
	ax_directory_assert( 'pagination retains search and exposes previous and next navigation', str_contains( $html, 'Previous contacts' ) && str_contains( $html, 'Next contacts' ) && str_contains( $html, 'contact_page=4' ) && str_contains( $html, 'search=Directory' ) );
	ax_directory_assert( 'opening a result carries its search and page into the detail URL', str_contains( $html, 'item=' . $ids[100] . '&search=Directory&contact_page=3' ) );
	$_GET = array( 'search' => 'no such contact' );
	ob_start();
	axismundi_contacts_directory( $owner, (int) $book['id'], $profile, 'Group', $group );
	$html = html_entity_decode( ob_get_clean(), ENT_QUOTES, 'UTF-8' );
	ax_directory_assert( 'an unmatched search explains the result and offers clearing within the group', str_contains( $html, 'No saved contacts match this search.' ) && str_contains( $html, 'Clear search' ) && str_contains( $html, 'name="group" value="' . $group . '"' ) );
	$_GET = array( 'search' => array( 'bad' ), 'contact_page' => array( 'bad' ) );
	ax_directory_assert( 'malformed query arrays become an empty first-page context', array( 'search' => '', 'contact_page' => 1 ) === axismundi_contacts_directory_context() );
	axismundi_contacts_admin_assets( 'users_page_axismundi-contacts' );
	$asset_root = plugins_url( 'assets/', dirname( __DIR__ ) . '/axismundi-contacts.php' );
	ax_directory_assert( 'directory assets resolve from the plugin root rather than a nonexistent includes/assets directory',
		$asset_root . 'contacts.css' === wp_styles()->registered['axismundi-contacts-admin']->src
		&& $asset_root . 'card-editor.js' === wp_scripts()->registered['axismundi-contacts-card-editor']->src
	);
} finally {
	$_GET = $initial_get;
	wp_set_current_user( $initial_user );
	foreach ( $users as $user ) {
		wp_delete_user( $user );
	}
	foreach ( $owners as $owner ) {
		axismundi_contacts_purge_actor( $owner );
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $owner ), array( '%d' ) );
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $owner ), array( '%d' ) );
	}
}

$results = $GLOBALS['ax_directory_results'];
$failures = count( array_filter( $results, static fn( bool $pass ) : bool => ! $pass ) );
printf( "\n== %d checks, %d failed ==\n", count( $results ), $failures );
WP_CLI::halt( $failures ? 1 : 0 );
