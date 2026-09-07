<?php
/**
 * A throwaway render of the lookup screen, because the audit cannot reach it.
 *
 * `lookup-screen.php` is loaded only under `is_admin()`, which is false in wp-cli, so the audit
 * checks the file as text rather than as a screen. This requires it outright and draws it once with
 * a mocked directory behind it, which is the only way to find out whether the presenter renders what
 * the lookup read.
 *
 * @package AxismundiContacts
 */

$dir = WP_PLUGIN_DIR . '/axismundi-contacts/includes/';
require_once $dir . 'card-detail.php';
require_once $dir . 'card-editor.php';
require_once $dir . 'admin.php';
require_once $dir . 'lookup-screen.php';
require_once $dir . 'profile-screen.php';

$login = 'axsmoke' . strtolower( wp_generate_password( 6, false, false ) );
$uid   = (int) wp_insert_user(
	array( 'user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
);
$actor = axismundi_actors_ensure_for_user( $uid );
axismundi_actors_register_handle( $actor->get_identity_id(), $login );
wp_set_current_user( $uid );

$card = array(
	'@type'          => 'Card',
	'version'        => '1.0',
	'uid'            => 'urn:uuid:99999999-2222-4333-8444-555555555555',
	'kind'           => 'individual',
	'name'           => array( '@type' => 'Name', 'full' => 'Alice Example' ),
	'emails'         => array( 'e1' => array( '@type' => 'EmailAddress', 'address' => 'alice@example.com' ) ),
	'onlineServices' => array( 's1' => array( '@type' => 'OnlineService', 'uri' => 'https://example.com/@alice' ) ),
);
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) use ( $card ) {
		$headers = static fn( string $type ) => new WpOrg\Requests\Utility\CaseInsensitiveDictionary( array( 'content-type' => $type ) );
		if ( str_contains( $url, '/.well-known/webfinger' ) ) {
			return array(
				'headers'  => $headers( 'application/jrd+json' ),
				'response' => array( 'code' => 200 ),
				'body'     => (string) wp_json_encode(
					array(
						'links' => array(
							array( 'rel' => 'self', 'type' => 'application/activity+json', 'href' => 'https://example.com/actors/alice' ),
							array( 'rel' => 'describedby', 'type' => 'application/jscontact+json', 'href' => 'https://example.com/@alice.jscontact' ),
						),
					)
				),
			);
		}
		return array(
			'headers'  => $headers( 'application/jscontact+json' ),
			'response' => array( 'code' => 200 ),
			'body'     => (string) wp_json_encode( $card ),
		);
	},
	10,
	3
);

$_POST['ax_contacts_lookup'] = '@alice@example.com';
$_REQUEST['_wpnonce']        = wp_create_nonce( 'ax_contacts_lookup' );
$_POST['_wpnonce']           = $_REQUEST['_wpnonce'];
$_SERVER['HTTP_REFERER']     = admin_url();

ob_start();
axismundi_contacts_lookup_screen( $actor );
$html = (string) ob_get_clean();

$checks = array(
	'the name is read out'          => str_contains( $html, '>Alice Example<' ),
	'the email is shown'            => str_contains( $html, 'alice@example.com' ),
	'the card address is shown'     => str_contains( $html, 'https://example.com/@alice.jscontact' ),
	'the Actor is shown'            => str_contains( $html, 'https://example.com/actors/alice' ),
	'the result carries a seal'     => 1 === preg_match( '/name="sealed" value="([^"]+)"/', $html, $sealed ),
	'saving is offered'             => str_contains( $html, 'axismundi_contacts_save_lookup' ),
	'no field is editable'          => ! str_contains( $html, 'name="card[' ),
);
$before = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . axismundi_contacts_cards_table() );

// And the seal that was drawn is the one the save handler would accept.
$unsealed                     = axismundi_contacts_lookup_unseal( html_entity_decode( $sealed[1] ?? '', ENT_QUOTES ), (int) $actor->get_identity_id() );
$checks['the seal round-trips'] = ! is_wp_error( $unsealed ) && 'Alice Example' === (string) ( $unsealed['card']['name']['full'] ?? '' );
$checks['drawing it wrote nothing'] = $before === (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . axismundi_contacts_cards_table() );

/*
 * And then the write, through the handler a person actually reaches. The audit exercises unsealing
 * and saving separately; this is the one path that does both, decides whose book, and says where to
 * go next -- so it is worth running rather than reasoning about.
 *
 * `wp_safe_redirect()` is followed by `exit`, so the redirect filter throws to get out of the handler
 * with the destination in hand.
 */
$went = '';
add_filter(
	'wp_redirect',
	static function ( $location ) use ( &$went ) {
		$went = (string) $location;
		throw new RuntimeException( 'redirected' );
	},
	// Ahead of wp-cli's own redirect handler, which otherwise prints a backtrace for each one.
	1,
	1
);
$_POST                = array( 'action' => 'axismundi_contacts_save_lookup', 'sealed' => html_entity_decode( $sealed[1] ?? '', ENT_QUOTES ) );
$_REQUEST['_wpnonce'] = wp_create_nonce( 'ax_contacts_save_lookup' );
$_POST['_wpnonce']    = $_REQUEST['_wpnonce'];
try {
	axismundi_contacts_handle_save_lookup();
} catch ( RuntimeException $stopped ) {
	unset( $stopped );
}
$saved_id = 0;
if ( 1 === preg_match( '/item=(\d+)/', $went, $where ) ) {
	$saved_id = (int) $where[1];
}
$saved_doc  = $saved_id > 0 ? axismundi_contacts_card_document( $saved_id ) : array();
$saved_prov = $saved_id > 0 ? axismundi_contacts_card_provenance( $saved_id ) : array();

$checks['saving lands on the contact it made'] = $saved_id > 0
	&& $card['uid'] === (string) ( $saved_doc['uid'] ?? '' )
	&& 'https://example.com/@alice.jscontact' === (string) ( $saved_prov['source:card']['source_ref'] ?? '' )
	&& 'https://example.com/actors/alice' === (string) ( $saved_prov['source:actor']['source_ref'] ?? '' );

// A second press of the same button opens what is there rather than filing a second copy.
$went = '';
try {
	axismundi_contacts_handle_save_lookup();
} catch ( RuntimeException $stopped ) {
	unset( $stopped );
}
$checks['pressing it twice opens the one contact'] = 1 === preg_match( '/item=(\d+)/', $went, $again )
	&& $saved_id === (int) $again[1];

// And a seal this site did not write is refused rather than saved.
$went = '';
$_POST['sealed'] = 'not.a.seal';
try {
	axismundi_contacts_handle_save_lookup();
} catch ( RuntimeException $stopped ) {
	unset( $stopped );
}
$checks['a seal this site did not write is refused'] = str_contains( $went, 'ax_contacts_error' )
	&& ! str_contains( $went, 'item=' );

/*
 * And the case the uid check cannot cover. `uid` is optional in RFC 9553, so a Card may say nothing
 * about which person it is a copy of -- and a seal stays valid for its whole life, so the same form
 * can be sent twice by a reload as easily as by a second press.
 *
 * Two contacts is what that produces, deliberately. This runs it rather than describing it, because
 * the behaviour is a decision and a decision nobody exercises is a decision that quietly changes.
 */
$anon       = array(
	'@type'   => 'Card',
	'version' => '1.0',
	'kind'    => 'individual',
	'name'    => array( '@type' => 'Name', 'full' => 'Nameless Example' ),
);
$anon_found = array(
	'card'        => axismundi_contacts_canonical_card( $anon ),
	'card_url'    => 'https://example.com/anon.jscontact',
	'profile_url' => '',
	'actor_uri'   => '',
);
$anon_ids   = array();
$_POST['sealed'] = axismundi_contacts_lookup_seal( $anon_found, (int) $actor->get_identity_id() );
foreach ( array( 1, 2 ) as $press ) {
	$went = '';
	try {
		axismundi_contacts_handle_save_lookup();
	} catch ( RuntimeException $stopped ) {
		unset( $stopped );
	}
	if ( 1 === preg_match( '/item=(\d+)/', $went, $anon_where ) ) {
		$anon_ids[] = (int) $anon_where[1];
	}
}
$checks['a card claiming no uid is saved again rather than recognised'] = 2 === count( $anon_ids )
	&& $anon_ids[0] > 0
	&& $anon_ids[0] !== $anon_ids[1];

$failed = 0;
foreach ( $checks as $what => $ok ) {
	echo ( $ok ? '[PASS] ' : '[FAIL] ' ) . $what . "\n";
	$failed += $ok ? 0 : 1;
}
echo '== ' . count( $checks ) . ' checks, ' . $failed . " failed ==\n";

// Cleaned up the way the audit cleans up, so a smoke run leaves no Actor behind.
foreach ( array_merge( array( $saved_id ), $anon_ids ) as $made ) {
	if ( (int) $made > 0 ) {
		axismundi_contacts_delete_card( (int) $made );
	}
}
$identity = (int) $actor->get_identity_id();
wp_delete_user( $uid );
axismundi_contacts_purge_actor( $identity );
$GLOBALS['wpdb']->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity ), array( '%d' ) );
$GLOBALS['wpdb']->delete( axismundi_actors_identities_table(), array( 'id' => $identity ), array( '%d' ) );
