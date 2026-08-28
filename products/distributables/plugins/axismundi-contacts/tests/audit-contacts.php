<?php
/**
 * An address book somebody keeps (dev-only; dist-excluded).
 *
 * What this pins is mostly the direction things flow. The Card is the record and the index is
 * derived from it, so nothing may write the index and expect the Card to follow. An import may
 * replace what it wrote and nothing else, so a person's own edits survive a sync. And the book
 * belongs to an Actor rather than to whoever administers the site, because a list of who somebody
 * knows is not site configuration.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit( 1 );

// WP-CLI does not load the administrator screen this fixture renders.
require_once dirname( __DIR__ ) . '/includes/card-detail.php';
require_once dirname( __DIR__ ) . '/includes/card-editor.php';
require_once dirname( __DIR__ ) . '/includes/admin.php';
require_once dirname( __DIR__ ) . '/includes/profile-screen.php';
require_once dirname( __DIR__ ) . '/includes/name-bindings.php';

$ax_ct_results = array();
$ax_ct_users   = array();
$ax_ct_books   = array();
$ax_ct_loose   = array();

/** @param bool[] $results Results. */
function ax_ct_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/**
 * Every Actor this file creates, so the registry ends the run holding what it started with.
 *
 * Deleting the account is not enough: an ended identity leaves its Actor row standing, which is
 * correct for a site and wrong for a fixture. A test that leaves rows behind is one that cannot say
 * whether the thing it is testing leaves rows behind.
 *
 * @var int[]
 */
$GLOBALS['ax_ct_made_actors'] = array();

/**
 * Whether everything in one document survived into the other, unchanged.
 *
 * Containment rather than equality, because storing a Card may add to it: a name with components and
 * no written-out form is given one, so that a locale pointed at it has something to bind to. What
 * must never happen is a value going missing or coming back different, and that is what this asks.
 *
 * Structural rather than textual. The order of an object's keys carries no meaning in JSON and this
 * codebase deliberately changes it; the order of an array's items is data and must not move. Types
 * are compared strictly, so a number that came back as a string is a difference.
 *
 * @param mixed    $before Document as authored.
 * @param mixed    $after  Document as stored.
 * @param string[] $lost   Collects the paths that did not survive.
 * @param string   $path   Path so far.
 * @return bool
 */
function ax_ct_preserves( $before, $after, array &$lost, string $path = '' ) : bool {
	if ( is_array( $before ) !== is_array( $after ) ) {
		$lost[] = $path;
		return false;
	}
	if ( ! is_array( $before ) ) {
		if ( $before !== $after ) {
			$lost[] = $path;
		}
		return $before === $after;
	}
	$list = array() === $before || array_keys( $before ) === range( 0, count( $before ) - 1 );
	if ( $list && count( $before ) !== count( $after ) ) {
		// An array is ordered data; losing or gaining an item changes what it says.
		$lost[] = $path;
		return false;
	}
	$intact = true;
	foreach ( $before as $key => $value ) {
		if ( ! array_key_exists( $key, $after ) ) {
			$lost[] = $path . '/' . $key;
			$intact = false;
			continue;
		}
		$intact = ax_ct_preserves( $value, $after[ $key ], $lost, $path . '/' . $key ) && $intact;
	}
	return $intact;
}

/**
 * Every path present after storing that was not there before.
 *
 * @param mixed    $before Document as authored.
 * @param mixed    $after  Document as stored.
 * @param string[] $added  Collects the paths.
 * @param string   $path   Path so far.
 * @return void
 */
function ax_ct_collect_additions( $before, $after, array &$added, string $path = '' ) : void {
	if ( ! is_array( $before ) || ! is_array( $after ) ) {
		return;
	}
	foreach ( $after as $key => $value ) {
		if ( ! array_key_exists( $key, $before ) ) {
			$added[] = $path . '/' . $key;
			continue;
		}
		ax_ct_collect_additions( $before[ $key ], $value, $added, $path . '/' . $key );
	}
}

/** An account with a published Person Actor. */
function ax_ct_actor( array &$users ) : Axismundi_Actor {
	$login = 'axct' . strtolower( wp_generate_password( 8, false, false ) );
	$id    = (int) wp_insert_user(
		array( 'user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), $login );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$GLOBALS['ax_ct_made_actors'][] = (int) $actor->get_identity_id();
	return axismundi_actors_get_for_user( $id );
}

try {
	$ax_ct_owner    = ax_ct_actor( $ax_ct_users );
	$ax_ct_owner_id = (int) $ax_ct_owner->get_identity_id();
	$ax_ct_book     = axismundi_contacts_book_for_actor( $ax_ct_owner_id );
	$ax_ct_books[]  = (int) ( $ax_ct_book['id'] ?? 0 );
	$ax_ct_book_id  = (int) ( $ax_ct_book['id'] ?? 0 );

	// -- whose book it is ------------------------------------------------------------------------------------

	ax_ct_assert(
		$ax_ct_results,
		'an address book belongs to an Actor and is made the first time it is opened',
		is_array( $ax_ct_book ) && $ax_ct_book_id > 0 && $ax_ct_owner_id === (int) $ax_ct_book['owner_actor_id']
	);
	/*
	 * The gate that matters. Administering an identity and reading the private address book kept
	 * under it are different powers -- one is about the site, the other is somebody's list of who
	 * they know and the numbers they were given.
	 */
	$ax_ct_stranger = ax_ct_actor( $ax_ct_users );
	ax_ct_assert(
		$ax_ct_results,
		'a site administrator who is not this Actor cannot open its address book',
		axismundi_contacts_can_use_book( $ax_ct_owner_id, (int) $ax_ct_owner->get_local_user_id() )
			&& user_can( (int) $ax_ct_stranger->get_local_user_id(), 'manage_options' )
			&& ! axismundi_contacts_can_use_book( $ax_ct_owner_id, (int) $ax_ct_stranger->get_local_user_id() )
	);

	// -- a card with almost nothing in it ---------------------------------------------------------------------

	/*
	 * The most common row in any real address book, and the one a model built from federation would
	 * refuse: a name and a telephone number, no identity, no URI, nothing to fetch.
	 */
	$ax_ct_phone_card = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type' => 'Card',
			'name'  => array( 'full' => '홍길동' ),
			'phones' => array( 'mobile' => array( 'number' => '010-1234-5678' ) ),
		)
	);
	ax_ct_assert(
		$ax_ct_results,
		'a card with a name and a telephone number is a complete contact',
		is_int( $ax_ct_phone_card ) && $ax_ct_phone_card > 0
			&& '홍길동' === (string) axismundi_contacts_get_card( $ax_ct_phone_card )['display_name']
			&& '' === (string) axismundi_contacts_get_card( $ax_ct_phone_card )['uid']
	);
	// And it is found the way somebody looks for it, however they wrote the number down.
	ax_ct_assert(
		$ax_ct_results,
		'and it is found by that number whichever way it is typed',
		1 === count( axismundi_contacts_find_by_value( $ax_ct_book_id, '+82 10 1234 5678' ) )
			&& 1 === count( axismundi_contacts_find_by_value( $ax_ct_book_id, '01012345678' ) )
	);
	ax_ct_assert(
		$ax_ct_results,
		'an empty document is not a card',
		is_wp_error( axismundi_contacts_save_card( $ax_ct_book_id, array( '@type' => 'Card' ) ) )
	);

	// -- the card is the record; the index is derived ----------------------------------------------------------

	$ax_ct_card = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type'  => 'Card',
			'name'   => array( 'full' => 'Jiwoon Kim' ),
			'emails' => array(
				'work' => array( 'address' => 'Work@Example.TEST' ),
				'home' => array( 'address' => 'home@example.test' ),
			),
			// A field this store knows nothing about, kept because somebody or some import wrote it.
			'preferredLanguages' => array( 'ko' => array( 'language' => 'ko-KR', 'pref' => 1 ) ),
		)
	);
	$ax_ct_doc = axismundi_contacts_card_document( $ax_ct_card );
	ax_ct_assert(
		$ax_ct_results,
		'a card is kept whole, including the parts this code does not read',
		isset( $ax_ct_doc['preferredLanguages']['ko'] )
			&& 'Work@Example.TEST' === (string) $ax_ct_doc['emails']['work']['address']
	);
	/*
	 * The index holds a folded copy for matching and the document holds what was typed. Searching
	 * must not be the reason somebody's address gets rewritten.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'values are indexed folded for matching while the card keeps what was written',
		1 === count( axismundi_contacts_find_by_value( $ax_ct_book_id, 'work@example.test' ) )
			&& 'Work@Example.TEST' === (string) axismundi_contacts_card_document( $ax_ct_card )['emails']['work']['address']
	);
	// Removing an entry has to remove it from the index too, or a deleted address stays findable.
	axismundi_contacts_save_card(
		$ax_ct_book_id,
		array( '@type' => 'Card', 'name' => array( 'full' => 'Jiwoon Kim' ), 'emails' => array( 'home' => array( 'address' => 'home@example.test' ) ) ),
		$ax_ct_card
	);
	ax_ct_assert(
		$ax_ct_results,
		'an entry removed from the card leaves the index with it',
		array() === axismundi_contacts_find_by_value( $ax_ct_book_id, 'work@example.test' )
			&& 1 === count( axismundi_contacts_find_by_value( $ax_ct_book_id, 'home@example.test' ) )
	);
	// A save written against a version somebody else replaced is refused rather than silently winning.
	$ax_ct_stale = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array( '@type' => 'Card', 'name' => array( 'full' => 'Stale' ) ),
		$ax_ct_card,
		1
	);
	ax_ct_assert(
		$ax_ct_results,
		'an edit written against a stale revision is refused, not merged in silence',
		is_wp_error( $ax_ct_stale ) && 'ax_contacts_card_conflict' === $ax_ct_stale->get_error_code()
	);

	// -- provenance is per entry -------------------------------------------------------------------------------

	/*
	 * The reason a single `source` column would not do. One card, two email addresses, one imported
	 * and one typed in -- which is what an address book looks like the moment anybody imports into
	 * one they already keep.
	 */
	axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type'  => 'Card',
			'name'   => array( 'full' => 'Jiwoon Kim' ),
			'emails' => array(
				'work' => array( 'address' => 'work@example.test' ),
				'home' => array( 'address' => 'home@example.test' ),
			),
		),
		$ax_ct_card
	);
	axismundi_contacts_set_provenance( $ax_ct_card, 'emails/work', 'google', 'sync-account@example.test' );
	axismundi_contacts_set_provenance( $ax_ct_card, 'emails/home', AXISMUNDI_CONTACTS_SOURCE_LOCAL );
	$ax_ct_prov = axismundi_contacts_card_provenance( $ax_ct_card );
	ax_ct_assert(
		$ax_ct_results,
		'two values on one card can come from two different places',
		'google' === (string) ( $ax_ct_prov['emails/work']['source'] ?? '' )
			&& 'sync-account@example.test' === (string) ( $ax_ct_prov['emails/work']['source_ref'] ?? '' )
			&& 'local' === (string) ( $ax_ct_prov['emails/home']['source'] ?? '' )
	);
	/*
	 * And that is what decides who may overwrite what. A source may replace what it wrote and
	 * nothing else, so a sync cannot quietly eat an address somebody typed in themselves.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'an import may replace what it wrote, and may not touch what somebody typed',
		axismundi_contacts_source_may_write( $ax_ct_card, 'emails/work', 'google', 'sync-account@example.test' )
			&& ! axismundi_contacts_source_may_write( $ax_ct_card, 'emails/home', 'google', 'sync-account@example.test' )
			&& axismundi_contacts_source_may_write( $ax_ct_card, 'emails/work', AXISMUNDI_CONTACTS_SOURCE_LOCAL )
			// A second Google account is a different source, and inherits nothing from the first.
			&& ! axismundi_contacts_source_may_write( $ax_ct_card, 'emails/work', 'google', 'other@example.test' )
	);
	// A value nobody recorded is treated as authored here: unrecorded is not unowned.
	ax_ct_assert(
		$ax_ct_results,
		'a value with no recorded source is not free for the taking',
		! axismundi_contacts_source_may_write( $ax_ct_card, 'phones/mobile', 'google', 'sync-account@example.test' )
	);
	// And an entry can be pinned so even its own source stops changing it.
	axismundi_contacts_lock_entry( $ax_ct_card, 'emails/work' );
	ax_ct_assert(
		$ax_ct_results,
		'a pinned entry is refused even to the source that wrote it',
		! axismundi_contacts_source_may_write( $ax_ct_card, 'emails/work', 'google', 'sync-account@example.test' )
	);
	/* Updating a source record must not undo a person's explicit lock on the value. */
	axismundi_contacts_set_provenance( $ax_ct_card, 'emails/work', AXISMUNDI_CONTACTS_SOURCE_LOCAL, 'linked-actor@example.test' );
	$ax_ct_locked_provenance = axismundi_contacts_card_provenance( $ax_ct_card );
	ax_ct_assert(
		$ax_ct_results,
		'updating provenance never unlocks an entry somebody locked',
		1 === (int) ( $ax_ct_locked_provenance['emails/work']['locked'] ?? 0 )
			&& AXISMUNDI_CONTACTS_SOURCE_LOCAL === (string) ( $ax_ct_locked_provenance['emails/work']['source'] ?? '' )
			&& 'linked-actor@example.test' === (string) ( $ax_ct_locked_provenance['emails/work']['source_ref'] ?? '' )
	);
	// None of that bookkeeping belongs in the document that gets exported.
	ax_ct_assert(
		$ax_ct_results,
		'provenance never enters the card, which is what gets exported',
		! str_contains( (string) axismundi_contacts_get_card( $ax_ct_card )['card_json'], 'sync-account@example.test' )
			&& ! str_contains( (string) axismundi_contacts_get_card( $ax_ct_card )['card_json'], 'source_ref' )
	);

	// -- the self card is an ordinary card ----------------------------------------------------------------------

	ax_ct_assert(
		$ax_ct_results,
		'the card describing the owner is pointed at rather than being a different kind of row',
		true === axismundi_contacts_set_profile_card( $ax_ct_owner_id, $ax_ct_card )
			&& $ax_ct_card === axismundi_contacts_profile_card( $ax_ct_owner_id )
	);
	$ax_ct_other_book = axismundi_contacts_book_for_actor( (int) $ax_ct_stranger->get_identity_id() );
	$ax_ct_books[]    = (int) ( $ax_ct_other_book['id'] ?? 0 );
	ax_ct_assert(
		$ax_ct_results,
		'and one Actor cannot claim a card another Actor keeps',
		is_wp_error( axismundi_contacts_set_profile_card( (int) $ax_ct_stranger->get_identity_id(), $ax_ct_card ) )
	);

	// -- somebody can reach it ---------------------------------------------------------------------------------

	/*
	 * A store nobody can write to is what several slices kept producing, so the screen is part of this
	 * one. What changed is which screen: the PHP form is gone and the editor is the only thing that
	 * writes a Card, so what is checked here is that it is reachable and that nothing else is.
	 */
	wp_set_current_user( (int) $ax_ct_owner->get_local_user_id() );
	ob_start();
	axismundi_contacts_card_editor_screen( $ax_ct_card, $ax_ct_book_id );
	$ax_ct_form = (string) ob_get_clean();
	ax_ct_assert(
		$ax_ct_results,
		'the card is editable on a screen that renders itself rather than its own source',
		str_contains( $ax_ct_form, 'id="ax-contacts-card-editor"' )
			&& ! str_contains( $ax_ct_form, '<?php' )
			&& ! str_contains( $ax_ct_form, '<form' )
	);
	/*
	 * One editor for everybody. The owner's card and a card about somebody else are the same kind of
	 * thing, so a separate "my details" form would drift from the general one and end up supporting
	 * different fields.
	 */
	ob_start();
	axismundi_contacts_card_editor_screen( $ax_ct_phone_card, $ax_ct_book_id );
	$ax_ct_other_form = (string) ob_get_clean();
	ax_ct_assert(
		$ax_ct_results,
		'a card about somebody else opens the same editor as the owner card',
		str_contains( $ax_ct_other_form, 'id="ax-contacts-card-editor"' )
			&& ! str_contains( $ax_ct_other_form, 'axismundi_contacts_save_card' )
	);
	/*
	 * And there is one writer. The form that used to post a Card to `admin-post.php` is gone, along
	 * with the helpers that existed only to read it back -- two ways to write one document is two sets
	 * of rules about revisions and provenance, and the one that fell behind would have been whichever
	 * was touched less.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'nothing in this plugin writes a Card except the draft route',
		! function_exists( 'axismundi_contacts_card_editor' )
			&& ! function_exists( 'axismundi_contacts_handle_save_card' )
			&& ! function_exists( 'axismundi_contacts_entries_from_request' )
			&& ! function_exists( 'axismundi_contacts_name_from_request' )
			&& ! has_action( 'admin_post_axismundi_contacts_save_card' )
	);
	/*
	 * A contact is made and then edited, rather than typed into a form and then made. What it starts as
	 * is the least a JSContact document can say and still be one: which revision it is written in, and
	 * what kind of thing it describes. No name is invented, and whether there is one at all is answered
	 * in the editor.
	 */
	$ax_ct_made_id = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array( '@type' => 'Card', 'version' => AXISMUNDI_CONTACTS_JSCONTACT_VERSION, 'kind' => 'individual' )
	);
	$ax_ct_loose[] = is_wp_error( $ax_ct_made_id ) ? 0 : (int) $ax_ct_made_id;
	$ax_ct_made    = is_wp_error( $ax_ct_made_id ) ? array() : axismundi_contacts_card_document( (int) $ax_ct_made_id );
	ax_ct_assert(
		$ax_ct_results,
		'a new contact starts as a card that says only what it is, with no name invented for it',
		! is_wp_error( $ax_ct_made_id )
			&& 'individual' === (string) ( $ax_ct_made['kind'] ?? '' )
			&& ! array_key_exists( 'name', $ax_ct_made )
			&& has_action( 'admin_post_axismundi_contacts_create_card' )
	);
	/*
	 * And it says which revision it is written in, in the ledger rather than only on the way out. A
	 * document that only gained a `version` as it was published would be a v2 Card to a stranger and
	 * an unversioned one to everything here, including the editor that saves it back.
	 */
	$ax_ct_self_actor = ax_ct_actor( $ax_ct_users );
	$ax_ct_self_made  = axismundi_contacts_create_profile_card( (int) $ax_ct_self_actor->get_identity_id() );
	$ax_ct_loose[]    = is_wp_error( $ax_ct_self_made ) ? 0 : (int) $ax_ct_self_made;
	$ax_ct_self_doc   = is_wp_error( $ax_ct_self_made ) ? array() : axismundi_contacts_card_document( (int) $ax_ct_self_made );
	ax_ct_assert(
		$ax_ct_results,
		'a card made here says which revision of JSContact it is written in, in the ledger itself',
		'2.0' === AXISMUNDI_CONTACTS_JSCONTACT_VERSION
			&& AXISMUNDI_CONTACTS_JSCONTACT_VERSION === (string) ( $ax_ct_made['version'] ?? '' )
			&& AXISMUNDI_CONTACTS_JSCONTACT_VERSION === (string) ( $ax_ct_self_doc['version'] ?? '' )
	);
	/*
	 * A document that came from somewhere else is not told what it is. One carrying its own version
	 * keeps it, and one that arrived without stays without: stamping a revision onto somebody's import
	 * would be this site asserting something about a document it did not write.
	 */
	$ax_ct_v1_id = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array( '@type' => 'Card', 'version' => '1.0', 'kind' => 'individual', 'name' => array( '@type' => 'Name', 'full' => 'From elsewhere' ) )
	);
	$ax_ct_loose[] = is_wp_error( $ax_ct_v1_id ) ? 0 : (int) $ax_ct_v1_id;
	$ax_ct_bare_id = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array( '@type' => 'Card', 'kind' => 'individual', 'name' => array( '@type' => 'Name', 'full' => 'No version given' ) )
	);
	$ax_ct_loose[] = is_wp_error( $ax_ct_bare_id ) ? 0 : (int) $ax_ct_bare_id;
	/*
	 * And a document that arrives at an older revision, or at none, is stored as what it now is. A
	 * store holding some Cards at 1.0 and some at 2.0 asks every reader and every export to handle
	 * both, for no gain -- a 1.0 Card is a valid 2.0 Card, and what 2.0 changed takes nothing away
	 * from one that already has an identifier. The bytes somebody sent belong in an import snapshot
	 * beside the record, not in the record.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a document that arrives at an older revision, or none, is stored as the one this ledger keeps',
		AXISMUNDI_CONTACTS_JSCONTACT_VERSION === (string) ( axismundi_contacts_card_document( (int) $ax_ct_v1_id )['version'] ?? '' )
			&& AXISMUNDI_CONTACTS_JSCONTACT_VERSION === (string) ( axismundi_contacts_card_document( (int) $ax_ct_bare_id )['version'] ?? '' )
			&& 'From elsewhere' === (string) ( axismundi_contacts_card_document( (int) $ax_ct_v1_id )['name']['full'] ?? '' )
	);
	wp_set_current_user( 0 );

	// -- connecting a card to an Actor -------------------------------------------------------------------------

	/*
	 * A new book opens with a card for its owner rather than a blank form, seeded from the Actor the
	 * book belongs to. Asking somebody to type in what the site already knows about them is the kind
	 * of empty first screen that makes a feature feel unfinished.
	 */
	$ax_ct_fresh      = ax_ct_actor( $ax_ct_users );
	$ax_ct_fresh_book = axismundi_contacts_book_for_actor( (int) $ax_ct_fresh->get_identity_id() );
	$ax_ct_books[]    = (int) $ax_ct_fresh_book['id'];
	$ax_ct_seeded     = axismundi_contacts_profile_card( (int) $ax_ct_fresh->get_identity_id() );
	$ax_ct_seeded_doc = $ax_ct_seeded > 0 ? axismundi_contacts_card_document( $ax_ct_seeded ) : array();
	ax_ct_assert(
		$ax_ct_results,
		'a new address book opens with a card for its owner, connected to that Actor',
		$ax_ct_seeded > 0
			&& $ax_ct_fresh->get_display_name() === (string) ( $ax_ct_seeded_doc['name']['full'] ?? '' )
			&& $ax_ct_fresh->get_uri() === (string) axismundi_contacts_get_card( $ax_ct_seeded )['linked_actor_uri']
	);
	/*
	 * The address goes where the standard puts it: an `onlineServices` entry whose `uri` identifies
	 * the account and whose `user` is the handle a person reads. Not a field this project invented,
	 * so it survives an export.
	 *
	 * Which of an Actor's two addresses goes in `uri` is the question. An Actor has an `id` that
	 * identifies it between servers -- `/actors/<uuid>` here, `/users/alice` on Mastodon -- and a
	 * `url` that is the profile a person opens and shares: `/@admin`, `/@alice`. RFC 9553's own
	 * example writes a Mastodon account as `https://example2.com/@alice`, and a Card is something a
	 * person reads, so it takes the profile.
	 *
	 * The id is not lost. It goes where a sync identifier belongs -- the record of where the entry
	 * came from, and the column the Card is looked up by -- and that is what a refresh and an avatar
	 * lookup ask for. Two addresses, two jobs, neither doing the other's.
	 */
	$ax_ct_service = $ax_ct_seeded_doc['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ] ?? array();
	ax_ct_assert(
		$ax_ct_results,
		'the account entry carries the profile a person opens, and the identifier is kept elsewhere',
		'Axismundi' === (string) ( $ax_ct_service['service'] ?? '' )
			&& $ax_ct_fresh->get_profile_url() === (string) ( $ax_ct_service['uri'] ?? '' )
			&& $ax_ct_fresh->get_uri() !== (string) ( $ax_ct_service['uri'] ?? '' )
			&& str_starts_with( (string) ( $ax_ct_service['user'] ?? '' ), '@' )
			// The identifier, where the machinery reads it.
			&& $ax_ct_fresh->get_uri() === (string) ( axismundi_contacts_card_provenance( $ax_ct_seeded )[ 'onlineServices/' . AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ]['source_ref'] ?? '' )
			&& $ax_ct_fresh->get_uri() === (string) axismundi_contacts_get_card( $ax_ct_seeded )['linked_actor_uri']
			/*
			 * And the way back still works: an entry showing a profile is still resolvable to the
			 * Actor it came from, which is what a refresh and an avatar walk on.
			 */
			&& $ax_ct_fresh->get_uri() === (string) ( axismundi_contacts_card_actor_links( $ax_ct_seeded )[0]['uri'] ?? '' )
	);
	/*
	 * And the tie survives being edited. `source` says who may write the value; `source_ref` says what
	 * the value is about, and those are different questions -- an account somebody corrected by hand
	 * is still that person's account on that server. Before the entry showed a profile this did not
	 * matter, because the value itself was the identifier and anything wanting the Actor read it. Now
	 * the reference is the only thing holding the two together, and dropping it when somebody edits
	 * the row would cost the Card its avatar, its refresh and the column it is looked up by -- in
	 * exchange for a keystroke.
	 */
	$ax_ct_before_edit = axismundi_contacts_card_document( $ax_ct_seeded );
	$ax_ct_edited      = $ax_ct_before_edit;
	$ax_ct_edited['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ]['user'] = '@renamed@example.test';
	axismundi_contacts_save_card_for_owner( (int) $ax_ct_fresh->get_identity_id(), $ax_ct_edited, $ax_ct_seeded );
	/*
	 * Through the step the editor's save takes: saving is how a sync writes too, so what makes a value
	 * somebody's own is this, and an audit that skipped it would be testing a path nobody uses.
	 */
	axismundi_contacts_record_local_edits( $ax_ct_seeded, $ax_ct_before_edit, axismundi_contacts_card_document( $ax_ct_seeded ) );
	$ax_ct_after_edit = axismundi_contacts_card_provenance( $ax_ct_seeded )[ 'onlineServices/' . AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ] ?? array();
	ax_ct_assert(
		$ax_ct_results,
		'editing an account makes the value yours without forgetting whose account it is',
		'local' === (string) ( $ax_ct_after_edit['source'] ?? '' )
			&& $ax_ct_fresh->get_uri() === (string) ( $ax_ct_after_edit['source_ref'] ?? '' )
			// Still resolvable, which is what the avatar and the refresh walk on.
			&& $ax_ct_fresh->get_uri() === (string) ( axismundi_contacts_card_actor_links( $ax_ct_seeded )[0]['uri'] ?? '' )
			// And still refused as something a sync may overwrite, which is what `local` is for.
			&& ! axismundi_contacts_source_may_write(
				$ax_ct_seeded,
				'onlineServices/' . AXISMUNDI_CONTACTS_HOME_SERVICE_KEY,
				AXISMUNDI_CONTACTS_SOURCE_ACTOR,
				$ax_ct_fresh->get_uri()
			)
	);

	/*
	 * A Card written before any of this said the identifier where it meant the profile, and lost the
	 * reference the first time somebody touched the row. Both are repaired on upgrade, in that order:
	 * the reference first, because it is what says which Actor the entry is, and only then the value.
	 *
	 * What is not repaired is a value somebody chose. A migration that rewrote those would be deciding
	 * that its idea of an address beats a person's, on a Card they keep.
	 */
	$ax_ct_old = axismundi_contacts_card_document( $ax_ct_seeded );
	$ax_ct_old['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ]['uri'] = $ax_ct_fresh->get_uri();
	axismundi_contacts_save_card_for_owner( (int) $ax_ct_fresh->get_identity_id(), $ax_ct_old, $ax_ct_seeded );
	// As an old Card had it: the identifier in the value, and nothing recorded about where it came from.
	axismundi_contacts_set_provenance( $ax_ct_seeded, 'onlineServices/' . AXISMUNDI_CONTACTS_HOME_SERVICE_KEY, AXISMUNDI_CONTACTS_SOURCE_ACTOR, '' );
	axismundi_contacts_restore_service_actor_refs();
	$ax_ct_restored = axismundi_contacts_card_provenance( $ax_ct_seeded )[ 'onlineServices/' . AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ] ?? array();
	axismundi_contacts_migrate_service_profile_uris();
	$ax_ct_moved = axismundi_contacts_card_document( $ax_ct_seeded );
	ax_ct_assert(
		$ax_ct_results,
		'an upgrade gives an account back its reference, and then shows the profile instead of the identifier',
		// The reference, read back from the value that was still the identifier.
		$ax_ct_fresh->get_uri() === (string) ( $ax_ct_restored['source_ref'] ?? '' )
			// And the value, once there was something to tell it from.
			&& $ax_ct_fresh->get_profile_url() === (string) ( $ax_ct_moved['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ]['uri'] ?? '' )
			// The Card is still looked up by the identifier.
			&& $ax_ct_fresh->get_uri() === (string) axismundi_contacts_get_card( $ax_ct_seeded )['linked_actor_uri']
	);

	/*
	 * Seeded values are recorded as the Actor's, which is what lets a later rename be pulled in --
	 * and what stops it once somebody edits the value themselves.
	 */
	$ax_ct_actor_source = AXISMUNDI_CONTACTS_SOURCE_ACTOR;
	ax_ct_assert(
		$ax_ct_results,
		'what came from the Actor says so, so a refresh knows what it may replace',
		$ax_ct_actor_source === (string) ( axismundi_contacts_card_provenance( $ax_ct_seeded )['name']['source'] ?? '' )
			&& $ax_ct_fresh->get_uri() === (string) ( axismundi_contacts_card_provenance( $ax_ct_seeded )['name']['source_ref'] ?? '' )
			&& axismundi_contacts_source_may_write( $ax_ct_seeded, 'name', $ax_ct_actor_source, $ax_ct_fresh->get_uri() )
	);
	// Somebody typing their own name takes it back, and no refresh puts the Actor's over the top.
	axismundi_contacts_save_card(
		(int) $ax_ct_fresh_book['id'],
		array_merge( axismundi_contacts_card_document( $ax_ct_seeded ), array( 'name' => array( 'full' => 'What I call myself' ) ) ),
		$ax_ct_seeded
	);
	axismundi_contacts_set_provenance( $ax_ct_seeded, 'name', AXISMUNDI_CONTACTS_SOURCE_LOCAL );
	axismundi_contacts_refresh_from_actor( $ax_ct_seeded );
	ax_ct_assert(
		$ax_ct_results,
		'a name somebody wrote themselves survives a refresh from the Actor',
		'What I call myself' === (string) ( axismundi_contacts_card_document( $ax_ct_seeded )['name']['full'] ?? '' )
	);
	// An Actor this site has never heard of is refused rather than fetched from here.
	ax_ct_assert(
		$ax_ct_results,
		'linking does not fetch: an unknown Actor is refused, since discovery is the Actors plugin',
		is_wp_error( axismundi_contacts_link_actor( $ax_ct_phone_card, 'https://example.invalid/users/nobody' ) )
	);

	/*
	 * One person keeps several accounts, and they belong on one card rather than several. The order is
	 * theirs: the top one is what they lead with, which is also what decides the face shown for them.
	 */
	$ax_ct_many = axismundi_contacts_card_document( $ax_ct_seeded );
	$ax_ct_many['onlineServices']['mastodon'] = array( '@type' => 'OnlineService', 'service' => 'Mastodon', 'user' => '@pfefferle@mastodon.social', 'uri' => 'https://mastodon.social/users/pfefferle', 'pref' => 2 );
	$ax_ct_many['onlineServices']['misskey']  = array( '@type' => 'OnlineService', 'service' => 'Misskey', 'user' => '@pfefferle@misskey.io', 'uri' => 'https://misskey.io/users/pfefferle', 'pref' => 3 );
	$ax_ct_many['onlineServices']['bluesky']  = array( '@type' => 'OnlineService', 'service' => 'Bluesky', 'user' => '@pfefferle.org@bsky.brid.gy', 'uri' => 'https://bsky.brid.gy/ap/pfefferle.org', 'pref' => 4 );
	axismundi_contacts_save_card( (int) $ax_ct_fresh_book['id'], $ax_ct_many, $ax_ct_seeded );
	$ax_ct_ordered = axismundi_contacts_ordered_services( axismundi_contacts_card_document( $ax_ct_seeded ) );
	ax_ct_assert(
		$ax_ct_results,
		'one card holds every account somebody keeps, in the order they put them in',
		4 === count( $ax_ct_ordered )
			&& AXISMUNDI_CONTACTS_HOME_SERVICE_KEY === (string) $ax_ct_ordered[0]['entry_id']
			&& 'Mastodon' === (string) $ax_ct_ordered[1]['service']
			&& 'Bluesky' === (string) $ax_ct_ordered[3]['service']
	);
	// Each of them is searchable, so a handle somebody half-remembers still finds the card.
	ax_ct_assert(
		$ax_ct_results,
		'every account on the card is indexed, not only the first',
		1 === count( axismundi_contacts_find_by_value( (int) $ax_ct_fresh_book['id'], 'https://misskey.io/users/pfefferle' ) )
	);
	/*
	 * An account this site has never spoken to is still a real account. Only the ones it can resolve
	 * become candidates for a face or a refresh; the rest sit on the card exactly as written.
	 */
	$ax_ct_links = axismundi_contacts_card_actor_links( $ax_ct_seeded );
	ax_ct_assert(
		$ax_ct_results,
		'accounts on servers this site has never met are kept, and simply resolve to nothing',
		1 === count( $ax_ct_links )
			&& AXISMUNDI_CONTACTS_HOME_SERVICE_KEY === (string) $ax_ct_links[0]['entry_id']
	);

	// -- a face for a card ---------------------------------------------------------------------------------------

	/*
	 * The order is the decision. A picture chosen for this contact wins over anything upstream,
	 * because that is what choosing it meant.
	 */
	axismundi_contacts_save_card(
		(int) $ax_ct_fresh_book['id'],
		array_merge(
			axismundi_contacts_card_document( $ax_ct_seeded ),
			array( 'media' => array( 'photo' => array( '@type' => 'Media', 'kind' => 'photo', 'uri' => 'https://example.test/chosen.jpg' ) ) )
		),
		$ax_ct_seeded
	);
	ax_ct_assert(
		$ax_ct_results,
		'a photo on the card wins over the linked Actor icon, because somebody chose it',
		'https://example.test/chosen.jpg' === axismundi_contacts_card_avatar( $ax_ct_seeded )['url']
			&& 'card' === axismundi_contacts_card_avatar( $ax_ct_seeded )['source']
	);
	// With no photo of its own, the linked Actor answers -- through Actors, which owns that cache.
	$ax_ct_avatar = axismundi_contacts_card_avatar( $ax_ct_phone_card );
	ax_ct_assert(
		$ax_ct_results,
		'a card with no photo and no linked Actor has no picture, and nobody is asked for one',
		'' === $ax_ct_avatar['url'] && '' === $ax_ct_avatar['source']
	);
	/*
	 * And an email address is never turned into an avatar lookup. Handing a third party the addresses
	 * in a private address book, one request at a time, is not something to do quietly for a picture.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'no address in this book is sent anywhere to find a face for it',
		! function_exists( 'axismundi_contacts_gravatar_url' )
			&& ! str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/includes/actor-link.php' ), 'gravatar.com' )
	);

	// -- a label is a reading, not a value -----------------------------------------------------------------------

	/*
	 * `직장팩스` and `Work fax` are the same fact, and only the standard axes say so. So what is stored
	 * is the pair, and the word on screen is rendered from it -- otherwise an export is unreadable to
	 * anybody else and an import from a phone cannot be matched to what is already here.
	 */
	$ax_ct_labelled = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type'  => 'Card',
			'name'   => array( 'full' => 'Labelled' ),
			'phones' => array(
				'a' => axismundi_contacts_apply_preset( 'phones', array( 'number' => '02-123-4567' ), 'work-fax' ),
				'b' => axismundi_contacts_apply_preset( 'phones', array( 'number' => '010-000-0000' ), 'custom', '회신' ),
			),
		)
	);
	$ax_ct_labels = axismundi_contacts_card_document( $ax_ct_labelled )['phones'];
	ax_ct_assert(
		$ax_ct_results,
		'a label is stored as the standard context and feature behind it, not as the word',
		array( 'work' => true ) === ( $ax_ct_labels['a']['contexts'] ?? array() )
			&& array( 'fax' => true ) === ( $ax_ct_labels['a']['features'] ?? array() )
			&& ! isset( $ax_ct_labels['a']['label'] )
	);
	/*
	 * Except when somebody types their own. A person writing their own word is saying the enumeration
	 * did not have what they meant, so nothing is guessed from it.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a label somebody typed is the fact, and no context or feature is invented from it',
		'회신' === (string) ( $ax_ct_labels['b']['label'] ?? '' )
			&& ! isset( $ax_ct_labels['b']['contexts'], $ax_ct_labels['b']['features'] )
	);
	// And the word comes back out of the pair, which is what makes the round trip work.
	ax_ct_assert(
		$ax_ct_results,
		'the word on screen is read back from what was stored',
		'work-fax' === axismundi_contacts_entry_preset( 'phones', $ax_ct_labels['a'] )
			&& 'custom' === axismundi_contacts_entry_preset( 'phones', $ax_ct_labels['b'] )
			&& '회신' === axismundi_contacts_entry_label( 'phones', $ax_ct_labels['b'] )
	);
	/*
	 * An email has no second axis -- there is no such thing as a fax email -- so its vocabulary is
	 * smaller on purpose rather than by omission.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'an email offers contexts and no features, because it has nothing a feature would describe',
		array() === axismundi_contacts_presets_for( 'emails' )['private']['features']
			&& array( 'private' ) === axismundi_contacts_presets_for( 'emails' )['private']['contexts']
	);

	// -- a book is a container, and a card is owned rather than contained --------------------------------------

	/*
	 * `개인`, `업무`, `가족` is how one person files the same contacts, and a card belongs in as many of
	 * those as they put it in. So containment is a relation and ownership is the column: the first
	 * has many answers, the second has exactly one and is what access is decided by.
	 */
	$ax_ct_second  = axismundi_contacts_create_book( $ax_ct_owner_id, 'Work' );
	$ax_ct_books[] = is_wp_error( $ax_ct_second ) ? 0 : (int) $ax_ct_second;
	$ax_ct_shelf   = axismundi_contacts_books_for_actor( $ax_ct_owner_id );
	ax_ct_assert(
		$ax_ct_results,
		'an Actor keeps as many books as they file into, exactly one of which is the default',
		! is_wp_error( $ax_ct_second )
			&& 2 === count( $ax_ct_shelf )
			&& 1 === count( array_filter( $ax_ct_shelf, static fn( array $b ) : bool => 1 === (int) $b['is_default'] ) )
			&& 1 === (int) $ax_ct_shelf[0]['is_default']
	);
	ob_start();
	axismundi_contacts_groups_sidebar( $ax_ct_owner, $ax_ct_book, (int) $ax_ct_second );
	$ax_ct_sidebar = (string) ob_get_clean();
	ax_ct_assert(
		$ax_ct_results,
		'AddressBooks render as private contact groups in the sidebar, not as group Cards or Group Actors',
		str_contains( $ax_ct_sidebar, '>All contacts<' )
			&& str_contains( $ax_ct_sidebar, '>Work<' )
			&& str_contains( $ax_ct_sidebar, 'group=' . (int) $ax_ct_second )
			&& str_contains( $ax_ct_sidebar, 'axismundi_contacts_create_group' )
			&& ! str_contains( $ax_ct_sidebar, 'kind="group"' )
	);
	ax_ct_assert(
		$ax_ct_results,
		'a card filed into two books is one record seen from both, never a copy',
		true === axismundi_contacts_add_card_to_book( $ax_ct_card, (int) $ax_ct_second )
			&& array( $ax_ct_card ) === array_map( static fn( array $r ) : int => (int) $r['id'], axismundi_contacts_cards_in_book( (int) $ax_ct_second ) )
			&& 2 === count( axismundi_contacts_card_books( $ax_ct_card ) )
			&& 1 === axismundi_contacts_card_count_in_book( (int) $ax_ct_second )
			&& count( axismundi_contacts_cards_for_owner( $ax_ct_owner_id ) ) === axismundi_contacts_card_count_for_owner( $ax_ct_owner_id )
	);
	/*
	 * Unfiling is not deleting, and the difference is somebody's notes. A card taken out of every book
	 * is still theirs and still reachable; only `delete` throws anything away.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'taking a card out of a book leaves the card, because unfiling is not deleting',
		axismundi_contacts_remove_card_from_book( $ax_ct_card, (int) $ax_ct_second )
			&& array() === axismundi_contacts_cards_in_book( (int) $ax_ct_second )
			&& array() !== axismundi_contacts_get_card( $ax_ct_card )
			&& in_array( $ax_ct_card, array_map( static fn( array $r ) : int => (int) $r['id'], axismundi_contacts_cards_for_owner( $ax_ct_owner_id ) ), true )
	);
	ax_ct_assert(
		$ax_ct_results,
		'and a card cannot be filed into a book somebody else keeps',
		is_wp_error( axismundi_contacts_add_card_to_book( $ax_ct_card, (int) $ax_ct_other_book['id'] ) )
	);

	// -- what a uid is, and what it is not ----------------------------------------------------------------------

	/*
	 * The card describing its owner is the one other people will hold copies of, so it is minted with
	 * a uid. That identifier says which card this is; it never says who may read it, and nothing here
	 * may come to treat holding one as permission.
	 */
	$ax_ct_seed_uid = (string) ( $ax_ct_seeded_doc['uid'] ?? '' );
	ax_ct_assert(
		$ax_ct_results,
		'the card describing an owner is minted with a uid, so a copy of it can be recognised later',
		1 === preg_match( '#^urn:uuid:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$#', $ax_ct_seed_uid )
	);
	$ax_ct_retitled        = $ax_ct_seeded_doc;
	$ax_ct_retitled['uid'] = 'urn:uuid:00000000-0000-4000-8000-000000000000';
	axismundi_contacts_save_card( (int) $ax_ct_fresh_book['id'], $ax_ct_retitled, $ax_ct_seeded );
	ax_ct_assert(
		$ax_ct_results,
		'a uid is identity and does not move because the card was edited',
		$ax_ct_seed_uid === (string) ( axismundi_contacts_card_document( $ax_ct_seeded )['uid'] ?? '' )
	);
	/*
	 * Refused rather than merged, and the refusal says which card already holds it -- because two rows
	 * under one uid is the same card stored twice, and afterwards no update can say which it meant.
	 */
	$ax_ct_dupe = axismundi_contacts_save_card(
		(int) $ax_ct_fresh_book['id'],
		array( '@type' => 'Card', 'uid' => $ax_ct_seed_uid, 'name' => array( '@type' => 'Name', 'full' => 'Copy' ) )
	);
	ax_ct_assert(
		$ax_ct_results,
		'one owner does not hold the same card uid twice, and the refusal names the card that has it',
		is_wp_error( $ax_ct_dupe ) && $ax_ct_seeded === (int) ( $ax_ct_dupe->get_error_data()['card_id'] ?? 0 )
	);
	/*
	 * A card with no uid is not a card whose uid is empty. JSContact 2.0 made it optional, and storing
	 * `''` would make every such card collide with every other under that same key.
	 */
	$ax_ct_bare_one = axismundi_contacts_save_card( $ax_ct_book_id, array( '@type' => 'Card', 'name' => array( '@type' => 'Name', 'full' => 'Nameless one' ) ) );
	$ax_ct_bare_two = axismundi_contacts_save_card( $ax_ct_book_id, array( '@type' => 'Card', 'name' => array( '@type' => 'Name', 'full' => 'Nameless two' ) ) );
	$ax_ct_loose[]  = is_wp_error( $ax_ct_bare_one ) ? 0 : (int) $ax_ct_bare_one;
	$ax_ct_loose[]  = is_wp_error( $ax_ct_bare_two ) ? 0 : (int) $ax_ct_bare_two;
	ax_ct_assert(
		$ax_ct_results,
		'a card without a uid is not a card with an empty one, so an owner may keep many',
		! is_wp_error( $ax_ct_bare_one ) && ! is_wp_error( $ax_ct_bare_two )
			&& null === axismundi_contacts_get_card( (int) $ax_ct_bare_one )['uid']
	);

	// -- how widely the card about yourself may be read ---------------------------------------------------------

	/*
	 * `contacts` is answered from the owner's own book -- whether they saved the person asking -- and
	 * that question has no answer off this site. So it does not federate: it is not degraded to a
	 * weaker check for a remote request, it is simply not served. `followers` is refused here for the
	 * same reason it will one day be accepted: an audience is only offered once it can be verified.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'the card about yourself is shared with nobody until you say otherwise, and only audiences this site can decide are accepted',
		'off' === axismundi_contacts_profile_sharing( $ax_ct_owner_id )
			&& true === axismundi_contacts_set_profile_sharing( $ax_ct_owner_id, 'contacts' )
			&& is_wp_error( axismundi_contacts_set_profile_sharing( $ax_ct_owner_id, 'followers' ) )
			&& 'contacts' === axismundi_contacts_profile_sharing( $ax_ct_owner_id )
	);
	/*
	 * What gets published is the Actor's card, never the book it happens to sit in. Opening a second
	 * book must not raise the question of whose audience wins, so the audience is not a property any
	 * book has -- and an Actor with no profile card has no audience to set either.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'the audience belongs to the Actor rather than to any of its address books',
		! array_key_exists( 'self_card_sharing', axismundi_contacts_get_book( $ax_ct_book_id ) )
			&& ! array_key_exists( 'self_card_id', axismundi_contacts_get_book( $ax_ct_book_id ) )
			&& 'contacts' === axismundi_contacts_profile_sharing( $ax_ct_owner_id )
			&& axismundi_contacts_profile_card( $ax_ct_owner_id ) === axismundi_contacts_profile_card( $ax_ct_owner_id )
	);
	$ax_ct_actorless = ax_ct_actor( $ax_ct_users );
	ax_ct_assert(
		$ax_ct_results,
		'and an Actor with nothing to publish has no audience to choose',
		is_wp_error( axismundi_contacts_set_profile_sharing( (int) $ax_ct_actorless->get_identity_id(), 'public' ) )
			&& 'off' === axismundi_contacts_profile_sharing( (int) $ax_ct_actorless->get_identity_id() )
	);

	// -- what a Card kind is, and what it is not ----------------------------------------------------------------

	/*
	 * `Card.kind` and an ActivityStreams actor type are two vocabularies answering different questions,
	 * and they do not line up: `Service` has no JSContact kind at all. So a type suggests a starting
	 * value and constrains nothing -- a Service Actor may honestly be `application` or `org`, and only
	 * whoever runs it knows which.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'an Actor type suggests a kind and never fixes one, and a Service has no honest suggestion',
		'individual' === axismundi_contacts_default_kind( 'Person' )
			&& 'org' === axismundi_contacts_default_kind( 'Organization' )
			&& 'group' === axismundi_contacts_default_kind( 'Group' )
			&& '' === axismundi_contacts_default_kind( 'Service' )
	);
	ax_ct_assert(
		$ax_ct_results,
		'a card seeded for a Person starts as an individual, and the kind is the card\'s from then on',
		'individual' === (string) ( axismundi_contacts_card_document( $ax_ct_seeded )['kind'] ?? '' )
	);
	/*
	 * The one rule JSContact makes about its own document is enforced rather than suggested: a Card
	 * that lists members is a group. A reader elsewhere is entitled to rely on that.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a card that lists members is a group, which the standard requires and this refuses to break',
		is_wp_error(
			axismundi_contacts_save_card(
				$ax_ct_book_id,
				array( '@type' => 'Card', 'kind' => 'individual', 'name' => array( '@type' => 'Name', 'full' => 'Team' ), 'members' => array( 'urn:uuid:x' => true ) )
			)
		)
	);
	$ax_ct_group_card = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array( '@type' => 'Card', 'kind' => 'group', 'name' => array( '@type' => 'Name', 'full' => 'Team' ), 'members' => array( 'urn:uuid:x' => true ) )
	);
	$ax_ct_loose[]    = is_wp_error( $ax_ct_group_card ) ? 0 : (int) $ax_ct_group_card;
	/*
	 * A member uid that names nothing here is kept, not dropped. JMAP says the same: the card it points
	 * at may live in an address book this account cannot see today and become readable tomorrow, and a
	 * store that tidied the reference away would have destroyed the group.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a group keeps a member uid it cannot resolve, because the card may become visible later',
		! is_wp_error( $ax_ct_group_card )
			&& array( 'urn:uuid:x' => true ) === (array) ( axismundi_contacts_card_document( (int) $ax_ct_group_card )['members'] ?? array() )
	);
	/*
	 * A kind this code has never heard of is stored as written. RFC 9553 allows vendor values and a
	 * newer revision will add more; refusing them would throw away the part of an import worth having.
	 */
	$ax_ct_odd_kind = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array( '@type' => 'Card', 'kind' => 'example.com:kiosk', 'name' => array( '@type' => 'Name', 'full' => 'Kiosk' ) )
	);
	$ax_ct_loose[]  = is_wp_error( $ax_ct_odd_kind ) ? 0 : (int) $ax_ct_odd_kind;
	ax_ct_assert(
		$ax_ct_results,
		'a kind from a vocabulary this code does not know is kept as written',
		! is_wp_error( $ax_ct_odd_kind )
			&& 'example.com:kiosk' === (string) ( axismundi_contacts_card_document( (int) $ax_ct_odd_kind )['kind'] ?? '' )
	);

	// -- not every Actor is a contact keeper --------------------------------------------------------------------

	/*
	 * A Card is not a by-product of an Actor existing. A Group is a set of relationships rather than
	 * somebody who files contacts; an Application or a Service is administered from the Actor screens,
	 * and giving either an address book would be a second place to look for what lives elsewhere.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a Person and an Organization keep address books; a Group, an Application and a Service do not',
		axismundi_contacts_type_keeps_books( 'Person' )
			&& axismundi_contacts_type_keeps_books( 'Organization' )
			&& ! axismundi_contacts_type_keeps_books( 'Group' )
			&& ! axismundi_contacts_type_keeps_books( 'Application' )
			&& ! axismundi_contacts_type_keeps_books( 'Service' )
	);
	ax_ct_assert(
		$ax_ct_results,
		'a profile card is automatic only for a Person, offered to a Group or an Organization, and never minted for a Service',
		'auto' === axismundi_contacts_profile_policy( 'Person' )
			&& 'optional' === axismundi_contacts_profile_policy( 'Organization' )
			&& 'optional' === axismundi_contacts_profile_policy( 'Group' )
			&& 'none' === axismundi_contacts_profile_policy( 'Service' )
			&& 'none' === axismundi_contacts_profile_policy( 'Application' )
	);

	// -- when the owner is gone --------------------------------------------------------------------------------

	/*
	 * An address book nobody can open is not harmless leftovers. It is other people's phone numbers
	 * and somebody's private notes about them, kept by a site with no account left that is allowed to
	 * read them. So closing an owner takes what that owner owned.
	 *
	 * And only that. The stranger's book holds its own card about the same person, written by them,
	 * and an Actor going away does not reach into a book it never had access to.
	 */
	$ax_ct_doomed      = ax_ct_actor( $ax_ct_users );
	$ax_ct_doomed_id   = (int) $ax_ct_doomed->get_identity_id();
	$ax_ct_doomed_book = axismundi_contacts_book_for_actor( $ax_ct_doomed_id );
	$ax_ct_books[]     = is_wp_error( $ax_ct_doomed_book ) ? 0 : (int) $ax_ct_doomed_book['id'];
	$ax_ct_doomed_card = axismundi_contacts_save_card(
		(int) $ax_ct_doomed_book['id'],
		array( '@type' => 'Card', 'name' => array( '@type' => 'Name', 'full' => 'Someone they knew' ) )
	);
	$ax_ct_witness     = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array( '@type' => 'Card', 'name' => array( '@type' => 'Name', 'full' => 'Someone they knew' ) )
	);
	$ax_ct_loose[]     = is_wp_error( $ax_ct_witness ) ? 0 : (int) $ax_ct_witness;
	$ax_ct_before      = axismundi_contacts_actor_footprint( $ax_ct_doomed_id );
	ax_ct_assert(
		$ax_ct_results,
		'what an owner keeps can be counted before anything is destroyed',
		2 === $ax_ct_before['cards'] && 1 === $ax_ct_before['books'] && 1 === $ax_ct_before['profile']
	);
	$ax_ct_purged = axismundi_contacts_purge_actor( $ax_ct_doomed_id );
	ax_ct_assert(
		$ax_ct_results,
		'closing an owner takes the profile card, the books, and every card they owned',
		2 === $ax_ct_purged['cards'] && 1 === $ax_ct_purged['books']
			&& array( 'cards' => 0, 'books' => 0, 'profile' => 0 ) === axismundi_contacts_actor_footprint( $ax_ct_doomed_id )
			&& array() === axismundi_contacts_get_card( (int) $ax_ct_doomed_card )
			&& 0 === axismundi_contacts_profile_card( $ax_ct_doomed_id )
	);
	ax_ct_assert(
		$ax_ct_results,
		'and leaves every card somebody else wrote about them, because those are not theirs to take',
		array() !== axismundi_contacts_get_card( (int) $ax_ct_witness )
			&& in_array( (int) $ax_ct_witness, array_map( static fn( array $r ) : int => (int) $r['id'], axismundi_contacts_cards_for_owner( $ax_ct_owner_id ) ), true )
	);
	/*
	 * What ends this data is decided per type rather than by one shared trigger. A Person shares a
	 * lifetime with the account they are, so deleting the account takes their personal book. An
	 * Organization outlives whichever administrator was removed and its lists are the Organization's.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a Person\'s address book ends with their account; an Organization\'s and a Group\'s end only when somebody asks',
		'account' === axismundi_contacts_purge_policy( 'Person' )
			&& 'manual' === axismundi_contacts_purge_policy( 'Organization' )
			&& 'manual' === axismundi_contacts_purge_policy( 'Group' )
			&& 'none' === axismundi_contacts_purge_policy( 'Service' )
	);
	/*
	 * `tombstone` is reached by every Actor eventually and says an identity ended, not that this data
	 * may be destroyed. Wiring the purge to it would make ending an Organization's identity silently
	 * delete lists that outlive it.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'ending an identity is not permission to destroy an address book, so no status change triggers a purge',
		! has_action( 'axismundi_actors_status_changed', 'axismundi_contacts_purge_actor' )
			&& ! has_action( 'axismundi_actors_status_changed', 'axismundi_contacts_purge_for_deleted_user' )
			&& false !== has_action( 'deleted_user', 'axismundi_contacts_purge_for_deleted_user' )
	);
	/*
	 * The account path, end to end. Deleting the account takes the Person's own book and leaves the
	 * card a stranger wrote about the same person, because that card is the stranger's record.
	 */
	$ax_ct_leaver    = ax_ct_actor( $ax_ct_users );
	$ax_ct_leaver_id = (int) $ax_ct_leaver->get_identity_id();
	$ax_ct_leaver_bk = axismundi_contacts_book_for_actor( $ax_ct_leaver_id );
	$ax_ct_books[]   = is_wp_error( $ax_ct_leaver_bk ) ? 0 : (int) $ax_ct_leaver_bk['id'];
	axismundi_contacts_save_card(
		(int) $ax_ct_leaver_bk['id'],
		array( '@type' => 'Card', 'name' => array( '@type' => 'Name', 'full' => 'A number they were given' ) )
	);
	wp_delete_user( (int) $ax_ct_leaver->get_local_user_id() );
	ax_ct_assert(
		$ax_ct_results,
		'deleting the account deletes the personal address book it was the only reader of',
		array( 'cards' => 0, 'books' => 0, 'profile' => 0 ) === axismundi_contacts_actor_footprint( $ax_ct_leaver_id )
	);

	// -- who owns the published document ------------------------------------------------------------------------

	/*
	 * Contacts holds the Card and renders it. Actors used to build one of its own from the identity
	 * registry, which meant two plugins publishing a Card for the same Actor under different
	 * identifiers -- anybody saving both kept the same person twice. The old entry point still works
	 * and now hands back exactly this one.
	 */
	$ax_ct_pub     = axismundi_contacts_jscontact_card( $ax_ct_fresh );
	$ax_ct_pub_uid = is_wp_error( $ax_ct_pub ) ? '' : (string) ( $ax_ct_pub['uid'] ?? '' );
	ax_ct_assert(
		$ax_ct_results,
		'the published card is the stored profile card, and the Actors entry point returns that same one',
		! is_wp_error( $ax_ct_pub )
			&& $ax_ct_seed_uid === $ax_ct_pub_uid
			&& $ax_ct_pub === axismundi_actors_jscontact_card( $ax_ct_fresh )
	);
	/*
	 * No fallback. A Card derived from the identity registry would need an identifier, this site
	 * would mint one, and the day a real profile Card appeared the published identity would change
	 * underneath everybody who had already saved it. That is the duplicate this inversion removed.
	 */
	$ax_ct_cardless = ax_ct_actor( $ax_ct_users );
	ax_ct_assert(
		$ax_ct_results,
		'an Actor with no profile card publishes nothing, rather than having one derived for it',
		is_wp_error( axismundi_contacts_jscontact_card( $ax_ct_cardless ) )
			&& is_wp_error( axismundi_actors_jscontact_card( $ax_ct_cardless ) )
	);
	/*
	 * The uid comes from the Card and from nowhere else. An Actor's UUID is the identity registry's
	 * identifier for an agent, not an address book's identifier for a contact card, and deriving one
	 * from the other is exactly how two of them came to exist.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'no published card carries an identifier derived from the Actor UUID',
		'' !== $ax_ct_pub_uid
			&& 'urn:uuid:' . (string) $ax_ct_fresh->get_uuid() !== $ax_ct_pub_uid
	);
	$ax_ct_src = '';
	foreach ( array( 'axismundi-contacts', 'axismundi-actors' ) as $ax_ct_plugin ) {
		foreach ( (array) glob( WP_PLUGIN_DIR . '/' . $ax_ct_plugin . '/includes/*.php' ) as $ax_ct_file ) {
			$ax_ct_src .= (string) file_get_contents( $ax_ct_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this project's own source.
		}
	}
	// The runtime answer above proves this Actor; the source says no path can do it for any Actor.
	ax_ct_assert(
		$ax_ct_results,
		'and no code path can build one: nothing joins urn:uuid: to an Actor uuid',
		1 !== preg_match( '/urn:uuid:\s*.\s*[^;]*get_uuid/', $ax_ct_src )
			&& 1 !== preg_match( '/get_uuid\s*\(\s*\)[^;]*urn:uuid:/', $ax_ct_src )
	);
	/*
	 * A contributor may not change what the Card is, and may not add to it either. Both used to be
	 * one rule about identity; the second is now the same rule as everything else on this route --
	 * what a stranger receives is what the person published, and a plugin is not the person.
	 *
	 * This is a real change for the plugins that contributed. Calendar added calendar URLs and Actors
	 * added names and anniversaries, and neither reaches a public document any more. Those are facts
	 * that belong on the Card, which is the ledger; published from there, they are published because
	 * somebody said so rather than because a plugin was installed.
	 */
	add_filter(
		'axismundi_contacts_jscontact_card',
		static function ( array $card ) : array {
			$card['uid']       = 'urn:uuid:00000000-0000-4000-8000-00000000dead';
			$card['kind']      = 'device';
			$card['calendars'] = array( 'primary' => array( '@type' => 'Calendar' ) );
			return $card;
		},
		99
	);
	$ax_ct_enriched = axismundi_contacts_jscontact_card( $ax_ct_fresh );
	remove_all_filters( 'axismundi_contacts_jscontact_card', 99 );
	ax_ct_assert(
		$ax_ct_results,
		'a contributor changes neither which card this is nor what it publishes',
		is_array( $ax_ct_enriched )
			&& 'urn:uuid:00000000-0000-4000-8000-00000000dead' !== (string) ( $ax_ct_enriched['uid'] ?? '' )
			&& 'device' !== (string) ( $ax_ct_enriched['kind'] ?? '' )
			&& ! isset( $ax_ct_enriched['calendars'] )
	);
	/*
	 * Serving is gated on the audience as well as on the profile being published. The route this
	 * replaced handed out a name and a kind; this document carries whatever is on somebody's card,
	 * which is telephone numbers and home addresses.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a card is advertised only when its Actor shares it publicly, not merely because the profile is public',
		! axismundi_contacts_jscontact_is_public( $ax_ct_fresh )
			&& 'off' === axismundi_contacts_profile_sharing( (int) $ax_ct_fresh->get_identity_id() )
	);

	/*
	 * The identity account, brought up to date rather than merely filled in.
	 *
	 * This is not something somebody wrote and might have meant: it is this site's own answer to which
	 * Actor the Card is about, and the save path refuses to let anybody change it. So a Card still
	 * carrying an address from before profile URLs were preferred, or a handle from before a rename,
	 * would carry it for good with no way back -- the one door in was closed behind it.
	 *
	 * What the Actor does not own is kept. A label somebody wrote on the row is theirs, and an upgrade
	 * that tidied it away would be taking something while claiming to repair something else. Neither
	 * does it touch any other account: those are the ones a person actually keeps.
	 */
	$ax_ct_stale_doc = axismundi_contacts_card_document( $ax_ct_seeded );
	$ax_ct_stale_doc['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ] = array(
		'@type'   => 'OnlineService',
		'service' => 'Axismundi',
		// As a Card written before the profile was preferred had it, under a handle since changed.
		'uri'     => $ax_ct_fresh->get_uri(),
		'user'    => '@renamed@example.test',
		'pref'    => 1,
		'label'   => 'mine',
	);
	$ax_ct_stale_doc['onlineServices']['onl-kept'] = array(
		'@type'   => 'OnlineService',
		'service' => 'Mastodon',
		'uri'     => 'https://example.test/@someone',
	);
	axismundi_contacts_save_card_for_owner( (int) $ax_ct_fresh->get_identity_id(), $ax_ct_stale_doc, $ax_ct_seeded );
	axismundi_contacts_ensure_identity_services();
	$ax_ct_repaired = axismundi_contacts_card_document( $ax_ct_seeded );
	$ax_ct_repaired_x1 = (array) ( $ax_ct_repaired['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ] ?? array() );
	ax_ct_assert(
		$ax_ct_results,
		'an upgrade brings the identity account up to date, and leaves everything it does not answer',
		$ax_ct_fresh->get_profile_url() === (string) ( $ax_ct_repaired_x1['uri'] ?? '' )
			&& axismundi_contacts_actor_handle( $ax_ct_fresh ) === (string) ( $ax_ct_repaired_x1['user'] ?? '' )
			&& 'Axismundi' === (string) ( $ax_ct_repaired_x1['service'] ?? '' )
			&& 1 === (int) ( $ax_ct_repaired_x1['pref'] ?? 0 )
			// What the Actor does not answer stays.
			&& 'mine' === (string) ( $ax_ct_repaired_x1['label'] ?? '' )
			// And the account somebody actually keeps is untouched.
			&& 'https://example.test/@someone' === (string) ( $ax_ct_repaired['onlineServices']['onl-kept']['uri'] ?? '' )
	);

	/*
	 * What a public Actor Card has to carry to be worth fetching twice. A name and a handle say who
	 * this is; `created` and `updated` are what let a reader ask whether anything has changed since
	 * last time instead of pulling the whole document to find out that nothing has; `kind` and
	 * `language` are how to read the name; `prodId` says what wrote it; and `members` is the whole
	 * point of a group Card, since a group that will not say who is in it is not describing a group.
	 *
	 * `members` names Card uids rather than rows in anybody's database, so a reader holding none of
	 * those members still holds a valid group Card. That is a property of the format and not a gap.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a public identity is a Card a reader can follow, not only a name',
		array( 'name', 'kind', 'language', 'created', 'updated', 'prodId', 'members', 'onlineServices/' . AXISMUNDI_CONTACTS_HOME_SERVICE_KEY )
			=== axismundi_contacts_identity_pointers( array( 'onlineServices' => array( AXISMUNDI_CONTACTS_HOME_SERVICE_KEY => array( 'uri' => 'https://example.test/@a' ) ) ) )
			// Each of them is a pointer the projection can actually act on.
			&& axismundi_contacts_is_publishable_pointer( 'members' )
			&& axismundi_contacts_is_publishable_pointer( 'created' )
			&& axismundi_contacts_is_publishable_pointer( 'updated' )
			&& axismundi_contacts_is_publishable_pointer( 'prodId' )
			/*
			 * A name written for another script comes with the name, because a localization is as
			 * public as what it translates and the name is always published. That is proven where the
			 * published fixture lives, further down, rather than asserted twice here.
			 */
	);

	/*
	 * When a document was written and when it last changed, which are facts about the document and
	 * not about its subject. `updated` moves when this site changes the Card -- and only then: storing
	 * somebody else's Card unchanged is a copy, and stamping it would overwrite what they said about
	 * their own document with the time we happened to read it. `created` is written once and kept like
	 * the uid, so an edit form that never drew the field cannot reset the age of a Card by saving it.
	 */
	$ax_ct_when_id  = (int) axismundi_contacts_save_card(
		$ax_ct_book_id,
		array( '@type' => 'Card', 'name' => array( 'full' => 'When' ) )
	);
	$ax_ct_loose[]  = $ax_ct_when_id;
	$ax_ct_when     = axismundi_contacts_card_document( $ax_ct_when_id );
	axismundi_contacts_save_card_for_owner( $ax_ct_owner_id, $ax_ct_when, $ax_ct_when_id );
	$ax_ct_unmoved  = axismundi_contacts_card_document( $ax_ct_when_id );
	/*
	 * The change carries a stale `updated` with it, so what proves the stamp is that the stale value
	 * did not survive. Comparing two wall-clock stamps would prove nothing here: both saves land in
	 * the same second, and an audit that passes only when the machine is slow is not an audit.
	 */
	$ax_ct_changed  = $ax_ct_unmoved;
	$ax_ct_changed['name']    = array( 'full' => 'When, corrected' );
	$ax_ct_changed['updated'] = '2000-01-01T00:00:00Z';
	axismundi_contacts_save_card_for_owner( $ax_ct_owner_id, $ax_ct_changed, $ax_ct_when_id );
	$ax_ct_moved_at = axismundi_contacts_card_document( $ax_ct_when_id );
	// A Card from somewhere else, saying its own dates and naming its own product.
	$ax_ct_import_id = (int) axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type'   => 'Card',
			'name'    => array( 'full' => 'Imported' ),
			'created' => '2019-01-02T03:04:05Z',
			'updated' => '2020-02-03T04:05:06Z',
			'prodId'  => 'Some Other Product',
		)
	);
	$ax_ct_loose[]   = $ax_ct_import_id;
	$ax_ct_imported  = axismundi_contacts_card_document( $ax_ct_import_id );
	ax_ct_assert(
		$ax_ct_results,
		'a document says when it was written and when it changed, and a copy is not a change',
		1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string) ( $ax_ct_when['created'] ?? '' ) )
			&& AXISMUNDI_CONTACTS_PROD_ID === (string) ( $ax_ct_when['prodId'] ?? '' )
			// Saving the same document again changed nothing, so it says nothing changed.
			&& (string) $ax_ct_when['updated'] === (string) ( $ax_ct_unmoved['updated'] ?? '' )
			// Correcting the name did change it, so the stale stamp submitted with it did not stand.
			&& '2000-01-01T00:00:00Z' !== (string) ( $ax_ct_moved_at['updated'] ?? '' )
			&& 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string) ( $ax_ct_moved_at['updated'] ?? '' ) )
			// And the age of the Card is not reset by editing it.
			&& (string) $ax_ct_when['created'] === (string) ( $ax_ct_moved_at['created'] ?? '' )
			/*
			 * A Card from elsewhere keeps what it said about itself. Overwriting a product name to
			 * advertise this one would erase where the document came from.
			 */
			&& '2019-01-02T03:04:05Z' === (string) ( $ax_ct_imported['created'] ?? '' )
			&& '2020-02-03T04:05:06Z' === (string) ( $ax_ct_imported['updated'] ?? '' )
			&& 'Some Other Product' === (string) ( $ax_ct_imported['prodId'] ?? '' )
	);

	/*
	 * The account that says which Actor a self Card is about is this site's answer, not the editor's.
	 * It is what tells one profile from another with the same name, it is served to everybody as part
	 * of the public identity, and it is answered by the Actor -- so the editor may not repoint it,
	 * rename what it says, or retire it.
	 *
	 * Deleting it is not refused, though: it is put back. A Card that simply does not have it is one
	 * written before this was guaranteed, and refusing the save would leave somebody unable to edit
	 * their own profile until a migration ran for them. Changing it is refused, because somebody meant
	 * to change it, and quietly restoring the Actor's answer would tell them their edit was saved.
	 *
	 * Exercised through the route rather than asserted about its source: what a screen may do to a
	 * document is decided by what the server accepts.
	 */
	$ax_ct_was_user = get_current_user_id();
	wp_set_current_user( (int) $ax_ct_fresh->get_local_user_id() );
	$ax_ct_put = static function ( int $card_id, array $card ) {
		$request = new WP_REST_Request( 'PUT', '/' . axismundi_contacts_rest_namespace() . '/cards/' . $card_id . '/draft' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array( 'card' => $card, 'revision' => (int) ( axismundi_contacts_get_card( $card_id )['revision'] ?? 0 ) )
			)
		);
		return rest_do_request( $request );
	};
	$ax_ct_id_doc  = axismundi_contacts_card_document( $ax_ct_seeded );
	$ax_ct_renamed = $ax_ct_id_doc;
	$ax_ct_renamed['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ]['service'] = 'Something else';
	$ax_ct_moved_id = $ax_ct_id_doc;
	$ax_ct_moved_id['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ]['uri'] = 'https://example.test/@someone';
	$ax_ct_dropped = $ax_ct_id_doc;
	unset( $ax_ct_dropped['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ] );
	$ax_ct_renamed_res = $ax_ct_put( $ax_ct_seeded, $ax_ct_renamed );
	$ax_ct_moved_res   = $ax_ct_put( $ax_ct_seeded, $ax_ct_moved_id );
	$ax_ct_dropped_res = $ax_ct_put( $ax_ct_seeded, $ax_ct_dropped );
	$ax_ct_id_after    = axismundi_contacts_card_document( $ax_ct_seeded );
	wp_set_current_user( $ax_ct_was_user );
	ax_ct_assert(
		$ax_ct_results,
		'the account that says which Actor a profile is cannot be repointed, renamed, or retired',
		409 === $ax_ct_renamed_res->get_status()
			&& 409 === $ax_ct_moved_res->get_status()
			/*
			 * Removing it is refused too, rather than accepted and quietly put back. Answering "saved"
			 * to a save that did not happen is a lie a client will act on, and a request arriving
			 * between the accept and the repair would read a Card that says nothing about whose it is.
			 */
			&& 409 === $ax_ct_dropped_res->get_status()
			// Every refusal left the document exactly as it was.
			&& $ax_ct_fresh->get_profile_url() === (string) ( $ax_ct_id_after['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ]['uri'] ?? '' )
			&& 1 === (int) ( $ax_ct_id_after['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ]['pref'] ?? 0 )
			&& 'Something else' !== (string) ( $ax_ct_id_after['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ]['service'] ?? '' )
			/*
			 * Which is only fair to somebody if the account is there to begin with, so every self Card
			 * is given one on upgrade rather than being refused for a gap nobody made.
			 */
			&& str_contains(
				(string) file_get_contents( dirname( __DIR__ ) . '/includes/schema.php' ),
				'function axismundi_contacts_ensure_identity_services() : void {'
			)
	);

	/*
	 * What the address answers with is two layers, and only one of them is a choice.
	 *
	 * A public Actor is already telling everybody its name, its picture and its handle: that is what
	 * a profile is, readable at `/actors/<uuid>` and `/@handle` without asking anyone. Saying the same
	 * three things in JSContact publishes nothing new, so it is not offered as a setting -- a switch
	 * that claims to hide what is being served next door would be a promise this cannot keep. Google's
	 * profile draws the same line: a name, a photo and the account address are public, and the rest of
	 * the card is not.
	 *
	 * Everything past identity is a choice, and off until it is made. A telephone number, a home
	 * address, a note about somebody else: none of that is who this is, and none of it is served until
	 * its owner has published it to everybody.
	 */
	$ax_ct_id_card = axismundi_contacts_jscontact_card( $ax_ct_fresh );
	/*
	 * A picture is not one of the things identity says. What a profile shows is the Actor's own icon,
	 * resolved when the page is drawn, so changing it upstream changes what people see without
	 * rewriting anybody's Card -- and this site never claims somebody else's image as a photo on a
	 * Card it keeps. `media` is the other thing: an image deliberately put on this Card, published
	 * only if its owner published it.
	 */
	$ax_ct_pic_doc = axismundi_contacts_card_document( $ax_ct_seeded );
	$ax_ct_pic_doc['media'] = array(
		'med-1' => array( '@type' => 'Media', 'kind' => 'photo', 'uri' => 'https://example.test/face.png' ),
	);
	axismundi_contacts_save_card_for_owner( (int) $ax_ct_fresh->get_identity_id(), $ax_ct_pic_doc, $ax_ct_seeded );
	$ax_ct_pic_out = axismundi_contacts_jscontact_card( $ax_ct_fresh );
	// An Actor that is not published at all. Its card answers nothing, whatever its owner selected.
	$ax_ct_hidden  = ax_ct_actor( $ax_ct_users );
	axismundi_actors_set_status( (int) $ax_ct_hidden->get_identity_id(), 'internal' );
	$ax_ct_hidden  = axismundi_actors_get_by_identity( (int) $ax_ct_hidden->get_identity_id() );
	ax_ct_assert(
		$ax_ct_results,
		'a public Actor answers with who it is, and with nothing else until it has said so',
		// Sharing is off, and the address still answers.
		'off' === axismundi_contacts_profile_sharing( (int) $ax_ct_fresh->get_identity_id() )
			&& axismundi_contacts_jscontact_is_readable( $ax_ct_fresh )
			&& is_array( $ax_ct_id_card )
			// Who it is: the name, and the account that says which Actor this is.
			&& '' !== (string) ( $ax_ct_id_card['name']['full'] ?? '' )
			&& $ax_ct_fresh->get_profile_url() === (string) ( $ax_ct_id_card['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ]['uri'] ?? '' )
			// And nothing anybody would have had to agree to.
			&& ! isset( $ax_ct_id_card['emails'], $ax_ct_id_card['phones'], $ax_ct_id_card['addresses'], $ax_ct_id_card['notes'] )
			// An Actor that is not answering at its own address answers nothing here either.
			&& ! axismundi_contacts_jscontact_is_readable( $ax_ct_hidden )
			/*
			 * And a photo on the Card is not promoted to identity by being the first one there. It is
			 * published when its owner publishes it, and until then the screen shows the Actor's icon
			 * without the Card claiming it.
			 */
			&& is_array( $ax_ct_pic_out )
			&& ! isset( $ax_ct_pic_out['media'] )
			&& ! in_array( 'media/med-1', axismundi_contacts_identity_pointers( $ax_ct_pic_doc ), true )
	);

	// -- the card owns the structured name ------------------------------------------------------------------------

	/*
	 * It used to be written down twice and carried across on every save. Two tables holding the same
	 * components is how they come to disagree, so the card keeps them and the identity registry is no
	 * longer asked -- which also ends the question of which side wins when they differ.
	 */
	$ax_ct_owned = axismundi_contacts_card_document( $ax_ct_seeded );
	$ax_ct_owned['name'] = array(
		'@type'      => 'Name',
		'full'       => "\xea\xb9\x80\xec\xa7\x80\xec\x9a\xb4",
		'components' => array(
			array( '@type' => 'NameComponent', 'kind' => 'surname', 'value' => "\xea\xb9\x80" ),
			array( '@type' => 'NameComponent', 'kind' => 'given', 'value' => "\xec\xa7\x80\xec\x9a\xb4" ),
			array( '@type' => 'NameComponent', 'kind' => 'credential', 'value' => 'Ph.D.' ),
		),
		'isOrdered'  => true,
	);
	axismundi_contacts_save_card_for_owner( (int) $ax_ct_fresh->get_identity_id(), $ax_ct_owned, $ax_ct_seeded );
	/*
	 * And an Actor write no longer reaches it. The columns are still there until they are dropped, so
	 * this is what says the write path is closed rather than merely unused.
	 */
	axismundi_actors_write_person_profile(
		(int) $ax_ct_fresh->get_identity_id(),
		array( 'structured_name_language' => 'ko', 'given' => 'Nope', 'surname' => 'Nope', 'display_order' => 'given-family' )
	);
	$ax_ct_kept = array();
	foreach ( (array) ( axismundi_contacts_card_document( $ax_ct_seeded )['name']['components'] ?? array() ) as $ax_ct_c ) {
		$ax_ct_kept[ (string) $ax_ct_c['kind'] ] = (string) $ax_ct_c['value'];
	}
	ax_ct_assert(
		$ax_ct_results,
		'the card keeps the structured name, and writing one on the Actor no longer reaches it',
		"\xea\xb9\x80" === ( $ax_ct_kept['surname'] ?? '' )
			&& "\xec\xa7\x80\xec\x9a\xb4" === ( $ax_ct_kept['given'] ?? '' )
			&& 'Ph.D.' === ( $ax_ct_kept['credential'] ?? '' )
	);
	/*
	 * An ordinary card obeys none of this either. Somebody saving a name of their own choosing for a
	 * person whose Actor says otherwise is right, not out of step -- the card is theirs.
	 */
	$ax_ct_theirs = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type' => 'Card',
			'name'  => array(
				'@type'      => 'Name',
				'full'       => 'Alice from the design team',
				'components' => array( array( '@type' => 'NameComponent', 'kind' => 'surname', 'value' => 'Smith' ) ),
			),
		)
	);
	$ax_ct_loose[] = is_wp_error( $ax_ct_theirs ) ? 0 : (int) $ax_ct_theirs;
	ax_ct_assert(
		$ax_ct_results,
		'a card about somebody else is its owner to write, and reaches no Actor',
		! is_wp_error( $ax_ct_theirs )
			&& 'Alice from the design team' === (string) axismundi_contacts_get_card( (int) $ax_ct_theirs )['display_name']
	);

	// -- the same name written another way ----------------------------------------------------------------------

	/*
	 * A romanisation and a foreign name are different facts. `ko-Latn` is how the Korean name is
	 * written in Latin script; `en` is the name this person uses in English, which may be unrelated to
	 * it. Collapsing them loses which is which, so each is its own localization with its own
	 * components and its own reading order.
	 */
	$ax_ct_loc = axismundi_contacts_card_document( $ax_ct_seeded );
	$ax_ct_loc = axismundi_contacts_set_localized_name(
		$ax_ct_loc,
		'ko-Latn',
		array(
			'full'       => 'Jiwoon Kim',
			'components' => array(
				array( '@type' => 'NameComponent', 'kind' => 'given', 'value' => 'Jiwoon' ),
				array( '@type' => 'NameComponent', 'kind' => 'surname', 'value' => 'Kim' ),
			),
			'isOrdered'  => true,
		)
	);
	$ax_ct_loc = axismundi_contacts_set_localized_name( $ax_ct_loc, 'en', array( 'full' => 'Trump' ) );
	axismundi_contacts_save_card_for_owner( (int) $ax_ct_fresh->get_identity_id(), $ax_ct_loc, $ax_ct_seeded );
	$ax_ct_back  = axismundi_contacts_card_document( $ax_ct_seeded );
	$ax_ct_latin = axismundi_contacts_localized_name( $ax_ct_back, 'ko-Latn' );
	$ax_ct_en    = axismundi_contacts_localized_name( $ax_ct_back, 'en' );
	ax_ct_assert(
		$ax_ct_results,
		'a romanisation and a foreign name are kept apart, each surviving a round trip on its own',
		'Jiwoon Kim' === (string) ( $ax_ct_latin['full'] ?? '' )
			&& 'Trump' === (string) ( $ax_ct_en['full'] ?? '' )
	);
	/*
	 * And each keeps its own reading order. The primary name is read family first and the romanisation
	 * is not; an order shared between them would publish one of the two wrong.
	 */
	$ax_ct_primary_kinds = array();
	foreach ( (array) ( $ax_ct_back['name']['components'] ?? array() ) as $ax_ct_c ) {
		$ax_ct_primary_kinds[] = (string) $ax_ct_c['kind'];
	}
	$ax_ct_latin_kinds = array();
	foreach ( (array) ( $ax_ct_latin['components'] ?? array() ) as $ax_ct_c ) {
		$ax_ct_latin_kinds[] = (string) $ax_ct_c['kind'];
	}
	ax_ct_assert(
		$ax_ct_results,
		'each writing of a name keeps its own component order',
		array( 'surname', 'given' ) === array_slice( $ax_ct_primary_kinds, 0, 2 )
			&& array( 'given', 'surname' ) === $ax_ct_latin_kinds
	);
	// A name given as a written-out string stays one. Nothing guesses which half is the surname.
	ax_ct_assert(
		$ax_ct_results,
		'a localized name written out in full is not taken apart into components',
		! isset( $ax_ct_en['components'] )
	);
	/*
	 * JSContact says localizations as patches, and an import may address one value inside a name
	 * rather than replacing the whole thing -- `name/components/0/phonetic` is the shape RFC 9553's
	 * own example uses. Reading only the coarse form would show such a Card as having no localized
	 * name at all.
	 */
	$ax_ct_deep = $ax_ct_back;
	$ax_ct_deep['localizations']['ja'] = array( 'name/full' => "\xe3\x82\xad\xe3\x83\xa0", 'nicknames/n1/name' => 'Kimu' );
	ax_ct_assert(
		$ax_ct_results,
		'a localization addressed one value at a time is read as a name, and paths for other fields are left alone',
		"\xe3\x82\xad\xe3\x83\xa0" === (string) ( axismundi_contacts_localized_name( $ax_ct_deep, 'ja' )['full'] ?? '' )
			&& 'Kimu' === (string) ( axismundi_contacts_set_localized_name( $ax_ct_deep, 'ja', array( 'full' => 'Kim' ) )['localizations']['ja']['nicknames/n1/name'] ?? '' )
	);
	/*
	 * The Card is the ledger, so what is published in a language is what the Card says in it. A name
	 * the Actor happens to keep for the same tag is not an answer to the question `what does this
	 * person publish`, and a language the Card is silent in is one this document is silent in too.
	 *
	 * That silence used to be filled by whoever contributed. It is not any more, for the same reason
	 * an email address is not: a public document says what its owner wrote and selected, and `de`
	 * being absent from their card is them not having written it.
	 */
	axismundi_actors_set_text( (int) $ax_ct_fresh->get_identity_id(), 'name', 'en', 'Jiwoon from Actors' );
	axismundi_actors_set_text( (int) $ax_ct_fresh->get_identity_id(), 'name', 'de', 'Jiwoon Kim' );
	$ax_ct_rendered = axismundi_contacts_jscontact_card( axismundi_actors_get_by_identity( (int) $ax_ct_fresh->get_identity_id() ) );
	ax_ct_assert(
		$ax_ct_results,
		'a language the card writes in is published, and one only the Actor holds is not',
		! is_wp_error( $ax_ct_rendered )
			&& 'Trump' === (string) ( axismundi_contacts_localized_name( $ax_ct_rendered, 'en' )['full'] ?? '' )
			&& array() === axismundi_contacts_localized_name( $ax_ct_rendered, 'de' )
			&& 'Jiwoon Kim' === (string) ( axismundi_actors_get_text_map( (int) $ax_ct_fresh->get_identity_id() )['de']['name'] ?? '' )
	);
	/*
	 * Absence is what lets a contributor fill a gap, so removing a localization removes the tag rather
	 * than leaving an empty one behind. An empty patch would answer `this Card has a name here` and
	 * block the fallback it was meant to restore.
	 */
	$ax_ct_cleared = axismundi_contacts_set_localized_name( $ax_ct_back, 'en', array() );
	ax_ct_assert(
		$ax_ct_results,
		'removing a localized name removes the tag, because absence is what allows a fallback',
		! isset( $ax_ct_cleared['localizations']['en'] )
			&& ! in_array( 'en', axismundi_contacts_localized_name_tags( $ax_ct_cleared ), true )
	);

	// -- editing those writings -----------------------------------------------------------------------------------

	/*
	 * Writing a localized name replaces the name and nothing else. A localization may carry paths for
	 * fields no name editor knows about, and rewriting the whole patch would throw them away -- the
	 * value below is a nickname somebody localized, which editing a name must not be able to delete.
	 */
	$ax_ct_form_card = axismundi_contacts_set_localized_name( array( '@type' => 'Card' ), 'en', array( 'full' => 'Trump' ) );
	$ax_ct_form_card['localizations']['en']['nicknames/n1/name'] = 'Don';
	$ax_ct_saved_form = axismundi_contacts_set_localized_name( $ax_ct_form_card, 'en', array( '@type' => 'Name', 'full' => 'Donald' ) );
	ax_ct_assert(
		$ax_ct_results,
		'editing a localized name changes the name and leaves that language’s other localizations alone',
		'Donald' === (string) ( axismundi_contacts_localized_name( $ax_ct_saved_form, 'en' )['full'] ?? '' )
			&& 'Don' === (string) ( $ax_ct_saved_form['localizations']['en']['nicknames/n1/name'] ?? '' )
	);
	/*
	 * And a name written out in full gains no components from being stored. Nothing here may decide
	 * which half of a name is the surname -- not the store, and not the editor either.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a name typed as one string gains no components from being written down',
		! isset( axismundi_contacts_localized_name( $ax_ct_saved_form, 'en' )['components'] )
	);
	/*
	 * Removing the name leaves the tag, because something else is still localized under it. The tag
	 * only goes when nothing is left, which is what lets a contributor fill the gap again.
	 */
	$ax_ct_removed = axismundi_contacts_set_localized_name( $ax_ct_saved_form, 'en', array() );
	ax_ct_assert(
		$ax_ct_results,
		'removing a writing removes the name and keeps a tag that still localizes something else',
		array() === axismundi_contacts_localized_name( $ax_ct_removed, 'en' )
			&& 'Don' === (string) ( $ax_ct_removed['localizations']['en']['nicknames/n1/name'] ?? '' )
	);
	/*
	 * A name given in parts is stored in parts, in the order they were put in, and said to be ordered
	 * -- because that sequence is one somebody chose rather than one a reader should work out for
	 * itself. Nothing writes a written-out form for it: components alone are a whole name, and what it
	 * reads as is worked out where it is shown.
	 */
	$ax_ct_typed_id = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type' => 'Card',
			'kind'  => 'individual',
			'name'  => array(
				'@type'            => 'Name',
				'isOrdered'        => true,
				'defaultSeparator' => '',
				'components'       => array(
					array( '@type' => 'NameComponent', 'kind' => 'surname', 'value' => "\xea\xb9\x80" ),
					array( '@type' => 'NameComponent', 'kind' => 'given', 'value' => "\xec\xa7\x80\xec\x9a\xb4" ),
				),
			),
		)
	);
	$ax_ct_loose[] = is_wp_error( $ax_ct_typed_id ) ? 0 : (int) $ax_ct_typed_id;
	$ax_ct_typed   = is_wp_error( $ax_ct_typed_id ) ? array() : ( axismundi_contacts_card_document( (int) $ax_ct_typed_id )['name'] ?? array() );
	ax_ct_assert(
		$ax_ct_results,
		'a name given in parts keeps them, in the order chosen, and is not written out on its behalf',
		array( 'surname', 'given' ) === array_column( (array) ( $ax_ct_typed['components'] ?? array() ), 'kind' )
			&& true === ( $ax_ct_typed['isOrdered'] ?? false )
			&& ! isset( $ax_ct_typed['full'] )
			&& "\xea\xb9\x80\xec\xa7\x80\xec\x9a\xb4" === axismundi_contacts_name_text( (array) $ax_ct_typed )
	);

	// -- sharing is two decisions ---------------------------------------------------------------------------------

	/*
	 * Whether to share and who with are different actions. Turning sharing off for a while is not a
	 * decision to share with fewer people, and one three-way setting made it one: switching off forgot
	 * the audience, so switching back on was a choice somebody had to make again.
	 */
	$ax_ct_sid = (int) $ax_ct_fresh->get_identity_id();
	axismundi_contacts_set_profile_audience( $ax_ct_sid, 'public' );
	axismundi_contacts_set_profile_sharing_enabled( $ax_ct_sid, true );
	axismundi_contacts_set_profile_sharing_enabled( $ax_ct_sid, false );
	ax_ct_assert(
		$ax_ct_results,
		'turning sharing off keeps the audience, so turning it back on restores what was chosen',
		'public' === axismundi_contacts_profile_audience( $ax_ct_sid )
			&& ! axismundi_contacts_profile_sharing_enabled( $ax_ct_sid )
			&& 'off' === axismundi_contacts_profile_sharing( $ax_ct_sid )
	);
	axismundi_contacts_set_profile_sharing_enabled( $ax_ct_sid, true );
	ax_ct_assert(
		$ax_ct_results,
		'and it comes back as it was rather than as the narrower default',
		'public' === axismundi_contacts_profile_sharing( $ax_ct_sid )
	);

	// -- what another domain has to ask -----------------------------------------------------------------------

	/*
	 * One question, answered by whoever owns the policy. Actors puts this on the Actor document and
	 * needs to know nothing about profile bindings or what `public` is called in these tables.
	 *
	 * The link says where the Card is, not what it is. Repeating the Card's `uid` here would make two
	 * places authoritative about one identity, and an address that later moves would take the identity
	 * with it.
	 */
	$ax_ct_link = axismundi_contacts_public_profile_link( $ax_ct_sid );
	ax_ct_assert(
		$ax_ct_results,
		'a public card is offered as a link that says where it is and not which card it is',
		is_array( $ax_ct_link )
			&& 'Link' === $ax_ct_link['type']
			&& str_ends_with( (string) $ax_ct_link['href'], '.jscontact' )
			&& str_starts_with( (string) $ax_ct_link['mediaType'], 'application/jscontact+json' )
			&& ! isset( $ax_ct_link['uid'] )
	);
	/*
	 * And nothing is offered in any other case. `contacts` is decided from this address book, which no
	 * other server can answer, so advertising it would point at a route that refuses them.
	 */
	axismundi_contacts_set_profile_audience( $ax_ct_sid, 'contacts' );
	$ax_ct_contacts_link = axismundi_contacts_public_profile_link( $ax_ct_sid );
	axismundi_contacts_set_profile_sharing_enabled( $ax_ct_sid, false );
	$ax_ct_off_link = axismundi_contacts_public_profile_link( $ax_ct_sid );
	ax_ct_assert(
		$ax_ct_results,
		'sharing with saved people only, or not sharing, offers no link at all',
		null === $ax_ct_contacts_link && null === $ax_ct_off_link
	);
	ax_ct_assert(
		$ax_ct_results,
		'and an Actor with no card of its own offers none either',
		null === axismundi_contacts_public_profile_link( (int) $ax_ct_cardless->get_identity_id() )
	);

	// -- how the network finds it ---------------------------------------------------------------------------------

	/*
	 * AS2 already says this. `attachment` is what is associated with an Object by inclusion, which is a
	 * contact document hanging off a profile; `tag` is association by reference, for entities an object
	 * mentions, and would be the wrong word. The media type is what makes it discoverable as a contact
	 * card rather than as one more link.
	 */
	axismundi_contacts_set_profile_audience( $ax_ct_sid, 'public' );
	axismundi_contacts_set_profile_sharing_enabled( $ax_ct_sid, true );
	$ax_ct_actor_doc = axismundi_contacts_actor_attachment( array( 'type' => 'Person' ), axismundi_actors_get_by_identity( $ax_ct_sid ) );
	ax_ct_assert(
		$ax_ct_results,
		'a public card appears on the Actor document as one attached link with its media type',
		1 === count( (array) ( $ax_ct_actor_doc['attachment'] ?? array() ) )
			&& 'Link' === (string) ( $ax_ct_actor_doc['attachment'][0]['type'] ?? '' )
			&& str_starts_with( (string) ( $ax_ct_actor_doc['attachment'][0]['mediaType'] ?? '' ), 'application/jscontact+json' )
			&& ! isset( $ax_ct_actor_doc['tag'] )
	);
	/*
	 * Appended, never assigned. What is already there is what somebody put on their profile, and an
	 * attachment list replaced wholesale would drop their website to make room for this.
	 */
	$ax_ct_had = array( 'type' => 'Person', 'attachment' => array( array( 'type' => 'PropertyValue', 'name' => 'Website', 'value' => 'https://example.test' ) ) );
	$ax_ct_now = axismundi_contacts_actor_attachment( $ax_ct_had, axismundi_actors_get_by_identity( $ax_ct_sid ) );
	ax_ct_assert(
		$ax_ct_results,
		'an attachment somebody already had survives, and the card is added beside it',
		2 === count( $ax_ct_now['attachment'] )
			&& 'PropertyValue' === (string) $ax_ct_now['attachment'][0]['type']
			&& 'Link' === (string) $ax_ct_now['attachment'][1]['type']
	);
	// Added once. A projection may be built twice in a request, and two copies would say two cards.
	ax_ct_assert(
		$ax_ct_results,
		'running again adds nothing, because a second copy would claim a second card',
		2 === count( axismundi_contacts_actor_attachment( $ax_ct_now, axismundi_actors_get_by_identity( $ax_ct_sid ) )['attachment'] )
	);
	/*
	 * And nothing is advertised that a stranger cannot fetch. `contacts` is decided from this address
	 * book, which no other server can answer, so a link to it would point at a route that refuses them.
	 */
	axismundi_contacts_set_profile_audience( $ax_ct_sid, 'contacts' );
	$ax_ct_private_doc = axismundi_contacts_actor_attachment( array( 'type' => 'Person' ), axismundi_actors_get_by_identity( $ax_ct_sid ) );
	axismundi_contacts_set_profile_sharing_enabled( $ax_ct_sid, false );
	$ax_ct_off_doc = axismundi_contacts_actor_attachment( array( 'type' => 'Person' ), axismundi_actors_get_by_identity( $ax_ct_sid ) );
	$ax_ct_none_doc = axismundi_contacts_actor_attachment( array( 'type' => 'Person' ), $ax_ct_cardless );
	ax_ct_assert(
		$ax_ct_results,
		'a card shared with saved people, an unshared one, and an Actor with none are all not advertised',
		! isset( $ax_ct_private_doc['attachment'] ) && ! isset( $ax_ct_off_doc['attachment'] ) && ! isset( $ax_ct_none_doc['attachment'] )
	);

	// -- what an Actor shows, and what it follows -------------------------------------------------------------

	/*
	 * The card offers names; the Actor chooses one per locale. `ko-Latn` is how a Korean name is
	 * written in Latin script and `en` may be a different name entirely, so which an English reader
	 * should see is a choice somebody makes -- never a mapping derived from the tags.
	 */
	$ax_ct_offered = axismundi_contacts_name_representations( $ax_ct_sid );
	ax_ct_assert(
		$ax_ct_results,
		'the card offers each of its names as something an Actor locale can be pointed at',
		array_key_exists( '', $ax_ct_offered )
			&& 'Trump' === (string) ( $ax_ct_offered['en'] ?? '' )
			&& 'Jiwoon Kim' === (string) ( $ax_ct_offered['ko-Latn'] ?? '' )
	);
	/*
	 * One representation may serve several locales, which is most of why this is a binding rather than
	 * a copy: correcting the romanisation once reaches all of them.
	 */
	axismundi_contacts_bind_actor_name( $ax_ct_sid, 'en-US', 'ko-Latn' );
	axismundi_contacts_bind_actor_name( $ax_ct_sid, 'de-DE', 'ko-Latn' );
	axismundi_contacts_bind_actor_name( $ax_ct_sid, 'fr-FR', 'en' );
	$ax_ct_texts = axismundi_actors_get_text_map( $ax_ct_sid );
	ax_ct_assert(
		$ax_ct_results,
		'one writing can be shown in several locales, and a different one in another',
		'Jiwoon Kim' === (string) ( $ax_ct_texts['en-US']['name'] ?? '' )
			&& 'Jiwoon Kim' === (string) ( $ax_ct_texts['de-DE']['name'] ?? '' )
			&& 'Trump' === (string) ( $ax_ct_texts['fr-FR']['name'] ?? '' )
	);
	// Correcting the card reaches every locale that follows it, and no others.
	$ax_ct_fixdoc = axismundi_contacts_card_document( $ax_ct_seeded );
	$ax_ct_fixdoc = axismundi_contacts_set_localized_name( $ax_ct_fixdoc, 'ko-Latn', array( 'full' => 'Ji-woon Kim' ) );
	axismundi_contacts_save_card_for_owner( $ax_ct_sid, $ax_ct_fixdoc, $ax_ct_seeded );
	$ax_ct_texts = axismundi_actors_get_text_map( $ax_ct_sid );
	ax_ct_assert(
		$ax_ct_results,
		'correcting a writing reaches every locale bound to it and leaves the rest alone',
		'Ji-woon Kim' === (string) ( $ax_ct_texts['en-US']['name'] ?? '' )
			&& 'Ji-woon Kim' === (string) ( $ax_ct_texts['de-DE']['name'] ?? '' )
			&& 'Trump' === (string) ( $ax_ct_texts['fr-FR']['name'] ?? '' )
	);
	/*
	 * A name typed on the Actor follows nothing. Somebody who wrote it chose it, and a later change to
	 * whatever it once resembled is not a reason to overwrite what they wrote.
	 */
	axismundi_actors_set_text( $ax_ct_sid, 'name', 'de-DE', 'Herr Kim' );
	$ax_ct_fixdoc = axismundi_contacts_set_localized_name( $ax_ct_fixdoc, 'ko-Latn', array( 'full' => 'Jiwoon KIM' ) );
	axismundi_contacts_save_card_for_owner( $ax_ct_sid, $ax_ct_fixdoc, $ax_ct_seeded );
	$ax_ct_texts = axismundi_actors_get_text_map( $ax_ct_sid );
	ax_ct_assert(
		$ax_ct_results,
		'a name typed on the Actor follows nothing and survives the next card edit',
		'Herr Kim' === (string) ( $ax_ct_texts['de-DE']['name'] ?? '' )
			&& 'custom' === axismundi_actors_text_binding( $ax_ct_sid, 'name', 'de-DE' )['source']
			&& 'Jiwoon KIM' === (string) ( $ax_ct_texts['en-US']['name'] ?? '' )
	);
	/*
	 * And a writing that is deleted leaves the published name standing. What strangers and remote
	 * servers hold should not empty itself because somebody tidied an address book; the binding stays,
	 * so a screen can say it needs choosing again.
	 */
	$ax_ct_fixdoc = axismundi_contacts_set_localized_name( $ax_ct_fixdoc, 'ko-Latn', array() );
	axismundi_contacts_save_card_for_owner( $ax_ct_sid, $ax_ct_fixdoc, $ax_ct_seeded );
	$ax_ct_texts = axismundi_actors_get_text_map( $ax_ct_sid );
	ax_ct_assert(
		$ax_ct_results,
		'deleting a writing leaves the name it was shown as, rather than emptying what was published',
		'Jiwoon KIM' === (string) ( $ax_ct_texts['en-US']['name'] ?? '' )
			&& 'ko-Latn' === axismundi_actors_text_binding( $ax_ct_sid, 'name', 'en-US' )['source_tag']
	);
	// A locale can only be pointed at a name the card actually has.
	ax_ct_assert(
		$ax_ct_results,
		'and a locale cannot be pointed at a writing the card does not have',
		is_wp_error( axismundi_contacts_bind_actor_name( $ax_ct_sid, 'en-US', 'ko-Hani' ) )
	);

	// -- choosing what each language shows ------------------------------------------------------------------------

	/*
	 * The control is keyed on the source, not on the string it currently produces. `Jiwoon Kim` can sit
	 * under two tags at once, and a control keyed on the text could not say which was picked -- so a
	 * later correction would follow the wrong one.
	 */
	$ax_ct_bind_doc = axismundi_contacts_card_document( $ax_ct_seeded );
	$ax_ct_bind_doc = axismundi_contacts_set_localized_name( $ax_ct_bind_doc, 'ko-Latn', array( 'full' => 'Same Text' ) );
	$ax_ct_bind_doc = axismundi_contacts_set_localized_name( $ax_ct_bind_doc, 'en', array( 'full' => 'Same Text' ) );
	axismundi_contacts_save_card_for_owner( $ax_ct_sid, $ax_ct_bind_doc, $ax_ct_seeded );
	axismundi_contacts_apply_binding_row( $ax_ct_sid, array( 'locale' => 'en-US', 'source' => 'tag:ko-Latn' ) );
	ax_ct_assert(
		$ax_ct_results,
		'two writings that read the same are still told apart, because the choice names the source',
		'ko-Latn' === axismundi_actors_text_binding( $ax_ct_sid, 'name', 'en-US' )['source_tag']
	);
	/*
	 * Choosing a source binds; choosing `custom` and typing does not. Those are the two write paths,
	 * and the screen keeps them apart so nothing has to guess afterwards which happened.
	 */
	axismundi_contacts_apply_binding_row( $ax_ct_sid, array( 'locale' => 'en-US', 'source' => 'custom', 'custom' => 'Donald' ) );
	$ax_ct_after_custom = axismundi_actors_text_binding( $ax_ct_sid, 'name', 'en-US' );
	ax_ct_assert(
		$ax_ct_results,
		'typing a name makes it follow nothing, and choosing a writing makes it follow that one',
		'custom' === $ax_ct_after_custom['source']
			&& '' === $ax_ct_after_custom['source_tag']
			&& 'Donald' === (string) ( axismundi_actors_get_text_map( $ax_ct_sid )['en-US']['name'] ?? '' )
	);
	/*
	 * A row whose source has gone is left exactly as it was. Somebody saving this form without touching
	 * it has decided nothing about it, and rewriting it as typed here would throw away the binding they
	 * still have to fix.
	 */
	axismundi_contacts_apply_binding_row( $ax_ct_sid, array( 'locale' => 'en-US', 'source' => 'tag:ko-Latn' ) );
	$ax_ct_bind_doc = axismundi_contacts_set_localized_name( $ax_ct_bind_doc, 'ko-Latn', array() );
	axismundi_contacts_save_card_for_owner( $ax_ct_sid, $ax_ct_bind_doc, $ax_ct_seeded );
	$ax_ct_before_save = axismundi_actors_get_text_map( $ax_ct_sid )['en-US']['name'] ?? '';
	axismundi_contacts_apply_binding_row( $ax_ct_sid, array( 'locale' => 'en-US', 'source' => 'broken' ) );
	$ax_ct_after_save = axismundi_actors_text_binding( $ax_ct_sid, 'name', 'en-US' );
	ax_ct_assert(
		$ax_ct_results,
		'saving the form without touching a broken row keeps its value and its binding',
		'ko-Latn' === $ax_ct_after_save['source_tag']
			&& AXISMUNDI_CONTACTS_NAME_SOURCE === $ax_ct_after_save['source']
			&& $ax_ct_before_save === ( axismundi_actors_get_text_map( $ax_ct_sid )['en-US']['name'] ?? '' )
	);
	// Removing a language takes the name and the binding; there is no longer one to explain.
	axismundi_contacts_apply_binding_row( $ax_ct_sid, array( 'locale' => 'en-US', 'remove' => '1' ) );
	ax_ct_assert(
		$ax_ct_results,
		'removing a language takes its name and its binding together',
		! isset( axismundi_actors_get_text_map( $ax_ct_sid )['en-US']['name'] )
			&& '' === axismundi_actors_text_binding( $ax_ct_sid, 'name', 'en-US' )['source']
	);
	/*
	 * And the two axes stay apart. Pointing a locale at a writing says who sees it; it does not create
	 * a slot named after the writing.
	 */
	$ax_ct_en_before = axismundi_actors_get_text_map( $ax_ct_sid )['en']['name'] ?? null;
	axismundi_contacts_apply_binding_row( $ax_ct_sid, array( 'locale' => 'fr-FR', 'source' => 'tag:en' ) );
	$ax_ct_map_after = axismundi_actors_get_text_map( $ax_ct_sid );
	ax_ct_assert(
		$ax_ct_results,
		'choosing a writing for a language does not touch a language named after that writing',
		isset( $ax_ct_map_after['fr-FR']['name'] )
			&& $ax_ct_en_before === ( $ax_ct_map_after['en']['name'] ?? null )
			&& '' === axismundi_actors_text_binding( $ax_ct_sid, 'name', 'en' )['source_tag']
	);

	// -- what this plugin is not -------------------------------------------------------------------------------

	/*
	 * Contacts keeps address books. It does not keep a second copy of anybody's public profile, and
	 * it does not decide what an Actor is -- that boundary is the reason a Card may be a person with
	 * a phone number and nothing else.
	 */
	// -- a card is presentation-ready when it is stored -------------------------------------------------------

	/*
	 * The gap this closes: the name bindings offer one writing per tag, so a card holding components
	 * and no written-out form used to offer that language nothing and the Actor went quiet in it.
	 *
	 * Closed where the name is read, not by rewriting the card. A Card written only in components is
	 * complete and RFC 9553 leaves working out what it reads as to whoever shows it -- filling it in
	 * on the way to storage meant an import came back out as a document its author had not written.
	 */
	$ax_ct_fmt_id = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type' => 'Card',
			'kind'  => 'individual',
			'name'  => array(
				'@type'      => 'Name',
				'isOrdered'  => true,
				'components' => array(
					array( '@type' => 'NameComponent', 'kind' => 'given', 'value' => 'Jiwoon' ),
					array( '@type' => 'NameComponent', 'kind' => 'surname', 'value' => 'Kim' ),
				),
			),
		)
	);
	$ax_ct_loose[] = is_wp_error( $ax_ct_fmt_id ) ? 0 : (int) $ax_ct_fmt_id;
	$ax_ct_fmt     = is_wp_error( $ax_ct_fmt_id ) ? array() : axismundi_contacts_card_document( (int) $ax_ct_fmt_id );
	ax_ct_assert(
		$ax_ct_results,
		'a card of components alone is stored as written, and still reads as a name',
		! isset( $ax_ct_fmt['name']['full'] )
			&& 'Jiwoon Kim' === axismundi_contacts_name_text( (array) ( $ax_ct_fmt['name'] ?? array() ) )
	);

	/*
	 * Compact is a separator and not a different kind of name. `defaultSeparator` empty is what
	 * JSContact already says for it, so a reader that knows the standard needs nothing from us.
	 */
	$ax_ct_compact_id = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type' => 'Card',
			'kind'  => 'individual',
			'name'  => array(
				'@type'            => 'Name',
				'isOrdered'        => true,
				'defaultSeparator' => '',
				'components'       => array(
					array( '@type' => 'NameComponent', 'kind' => 'surname', 'value' => '김' ),
					array( '@type' => 'NameComponent', 'kind' => 'given', 'value' => '지운' ),
				),
			),
		)
	);
	$ax_ct_loose[]  = is_wp_error( $ax_ct_compact_id ) ? 0 : (int) $ax_ct_compact_id;
	$ax_ct_compact  = is_wp_error( $ax_ct_compact_id ) ? array() : axismundi_contacts_card_document( (int) $ax_ct_compact_id );
	ax_ct_assert(
		$ax_ct_results,
		'an empty default separator stays on the card and joins the parts with nothing between them',
		'김지운' === axismundi_contacts_name_text( (array) ( $ax_ct_compact['name'] ?? array() ) )
			&& '' === (string) ( $ax_ct_compact['name']['defaultSeparator'] ?? 'unset' )
	);

	/*
	 * The forbidden direction, pinned. Completing a name is a derivation -- the components were
	 * already in the order somebody chose -- and taking a written-out name apart would be a guess
	 * about which half of it is a surname, written into a field that does not look like a guess
	 * afterwards.
	 */
	$ax_ct_fullonly_id = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array( '@type' => 'Card', 'kind' => 'individual', 'name' => array( '@type' => 'Name', 'full' => 'Jiwoon Kim' ) )
	);
	$ax_ct_loose[]  = is_wp_error( $ax_ct_fullonly_id ) ? 0 : (int) $ax_ct_fullonly_id;
	$ax_ct_fullonly = is_wp_error( $ax_ct_fullonly_id ) ? array() : axismundi_contacts_card_document( (int) $ax_ct_fullonly_id );
	ax_ct_assert(
		$ax_ct_results,
		'a written-out name is never taken apart into components',
		'Jiwoon Kim' === (string) ( $ax_ct_fullonly['name']['full'] ?? '' )
			&& ! isset( $ax_ct_fullonly['name']['components'] )
	);

	/*
	 * A `full` somebody typed wins over what the components join to. Writing `Dr. Kim` beside a
	 * surname is two statements on purpose, and the second must not overwrite the first.
	 */
	$ax_ct_both_id = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type' => 'Card',
			'kind'  => 'individual',
			'name'  => array(
				'@type'      => 'Name',
				'full'       => 'Dr. Kim',
				'components' => array( array( '@type' => 'NameComponent', 'kind' => 'surname', 'value' => 'Kim' ) ),
			),
		)
	);
	$ax_ct_loose[] = is_wp_error( $ax_ct_both_id ) ? 0 : (int) $ax_ct_both_id;
	$ax_ct_both    = is_wp_error( $ax_ct_both_id ) ? array() : axismundi_contacts_card_document( (int) $ax_ct_both_id );
	ax_ct_assert(
		$ax_ct_results,
		'a written-out name that differs from its components is left as it was written',
		'Dr. Kim' === (string) ( $ax_ct_both['name']['full'] ?? '' )
	);

	// A localized name is a name: the bindings read it the same way, so it is completed the same way.
	$ax_ct_loc_id = axismundi_contacts_save_card(
		$ax_ct_book_id,
		axismundi_contacts_set_localized_name(
			array( '@type' => 'Card', 'kind' => 'individual', 'name' => array( '@type' => 'Name', 'full' => 'Jiwoon Kim' ) ),
			'ko-KR',
			array(
				'@type'            => 'Name',
				'isOrdered'        => true,
				'defaultSeparator' => '',
				'components'       => array(
					array( '@type' => 'NameComponent', 'kind' => 'surname', 'value' => '김' ),
					array( '@type' => 'NameComponent', 'kind' => 'given', 'value' => '지운' ),
				),
			)
		)
	);
	$ax_ct_loose[] = is_wp_error( $ax_ct_loc_id ) ? 0 : (int) $ax_ct_loc_id;
	$ax_ct_loc     = is_wp_error( $ax_ct_loc_id ) ? array() : axismundi_contacts_card_document( (int) $ax_ct_loc_id );
	ax_ct_assert(
		$ax_ct_results,
		'a localized name of components alone reads the same way, and is stored the same way',
		! isset( axismundi_contacts_localized_name( $ax_ct_loc, 'ko-KR' )['full'] )
			&& '김지운' === axismundi_contacts_name_text( axismundi_contacts_localized_name( $ax_ct_loc, 'ko-KR' ) )
	);

	/*
	 * The separator is a value on the name and travels with it. A compact name -- the parts written
	 * with nothing between them -- says so with an empty `defaultSeparator`, and a separator an import
	 * brought that no screen offers as a choice is still just a value, stored and read like any other.
	 * Nothing normalises it on the way through.
	 */
	$ax_ct_sep_id = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type' => 'Card',
			'kind'  => 'individual',
			'name'  => array(
				'@type'            => 'Name',
				'isOrdered'        => true,
				'defaultSeparator' => '-',
				'components'       => array(
					array( '@type' => 'NameComponent', 'kind' => 'given', 'value' => 'Jiwoon' ),
					array( '@type' => 'NameComponent', 'kind' => 'surname', 'value' => 'Kim' ),
				),
			),
		)
	);
	$ax_ct_loose[] = is_wp_error( $ax_ct_sep_id ) ? 0 : (int) $ax_ct_sep_id;
	$ax_ct_sep     = is_wp_error( $ax_ct_sep_id ) ? array() : ( axismundi_contacts_card_document( (int) $ax_ct_sep_id )['name'] ?? array() );
	ax_ct_assert(
		$ax_ct_results,
		'a separator nobody offers as a choice is stored as written and read back the same way',
		'-' === (string) ( $ax_ct_sep['defaultSeparator'] ?? '' )
			&& 'Jiwoon-Kim' === axismundi_contacts_name_text( (array) $ax_ct_sep )
	);

	/*
	 * A `separator` component is a literal RFC 9553 allows between two parts, and where one appears it
	 * stands in place of the default rather than beside it.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a separator component is used as written, in place of the default',
		'Kim, Jiwoon' === axismundi_contacts_assemble_name(
			array(
				'@type'      => 'Name',
				'components' => array(
					array( '@type' => 'NameComponent', 'kind' => 'surname', 'value' => 'Kim' ),
					array( '@type' => 'NameComponent', 'kind' => 'separator', 'value' => ', ' ),
					array( '@type' => 'NameComponent', 'kind' => 'given', 'value' => 'Jiwoon' ),
				),
			)
		)
	);

	/*
	 * And the reason all of the above matters: the Actor's own card, holding components alone, now
	 * offers that name to a locale. Before this it offered nothing, and the only thing hiding it was
	 * that Actors still assembled the name on its own side.
	 */
	$ax_ct_fmt_actor = ax_ct_actor( $ax_ct_users );
	$ax_ct_fmt_sid   = (int) $ax_ct_fmt_actor->get_identity_id();
	$ax_ct_fmt_made  = axismundi_contacts_create_profile_card( $ax_ct_fmt_sid );
	$ax_ct_fmt_card  = is_wp_error( $ax_ct_fmt_made ) ? 0 : (int) $ax_ct_fmt_made;
	$ax_ct_loose[]   = $ax_ct_fmt_card;
	if ( $ax_ct_fmt_card > 0 ) {
		$ax_ct_fmt_doc         = axismundi_contacts_card_document( $ax_ct_fmt_card );
		$ax_ct_fmt_doc['name'] = array(
			'@type'      => 'Name',
			'isOrdered'  => true,
			'components' => array(
				array( '@type' => 'NameComponent', 'kind' => 'given', 'value' => 'Jiwoon' ),
				array( '@type' => 'NameComponent', 'kind' => 'surname', 'value' => 'Kim' ),
			),
		);
		axismundi_contacts_save_card_for_owner( $ax_ct_fmt_sid, $ax_ct_fmt_doc, $ax_ct_fmt_card );
	}
	ax_ct_assert(
		$ax_ct_results,
		'a profile card holding only components offers its name to an Actor locale',
		'Jiwoon Kim' === (string) ( axismundi_contacts_name_representations( $ax_ct_fmt_sid )[''] ?? '' )
	);

	// -- a name an Actor shows is chosen, never assembled ------------------------------------------------------

	/*
	 * Actors stopped building names out of parts, so what an Actor answers with in a language now has
	 * exactly three sources: a label somebody typed on the Actor, a card writing a binding points at,
	 * or a string that was already there. Nothing fills a language in because a card happens to have
	 * something that would fit.
	 *
	 * That is a contract rather than a gap. A card may carry `ko-Kore`, `ko-Latn` and `en` and the
	 * Actor still answer in two locales, because which writing a German-speaking reader should see is
	 * a choice somebody makes -- and a site that guessed it would be inventing an answer nobody gave.
	 */
	$ax_ct_nm_actor = ax_ct_actor( $ax_ct_users );
	$ax_ct_nm_sid   = (int) $ax_ct_nm_actor->get_identity_id();
	$ax_ct_nm_made  = axismundi_contacts_create_profile_card( $ax_ct_nm_sid );
	$ax_ct_nm_card  = is_wp_error( $ax_ct_nm_made ) ? 0 : (int) $ax_ct_nm_made;
	$ax_ct_loose[]  = $ax_ct_nm_card;

	$ax_ct_nm_doc = axismundi_contacts_card_document( $ax_ct_nm_card );
	$ax_ct_nm_doc['name'] = array( '@type' => 'Name', 'full' => 'Jiwoon Kim' );
	$ax_ct_nm_doc = axismundi_contacts_set_localized_name( $ax_ct_nm_doc, 'ko-Latn', array( '@type' => 'Name', 'full' => 'Jiwoon KIM' ) );
	axismundi_contacts_save_card_for_owner( $ax_ct_nm_sid, $ax_ct_nm_doc, $ax_ct_nm_card );

	// A name typed on the Actor, in a locale pointed at nothing.
	axismundi_actors_set_text( $ax_ct_nm_sid, 'name', 'de-DE', 'Eigener Name' );
	axismundi_contacts_bind_actor_name( $ax_ct_nm_sid, 'en-US', 'ko-Latn' );

	ax_ct_assert(
		$ax_ct_results,
		'a name written on the Actor and pointed at nothing stays exactly as it was written',
		'Eigener Name' === (string) ( axismundi_actors_get_text_map( $ax_ct_nm_sid )['de-DE']['name'] ?? '' )
			&& 'Jiwoon KIM' === (string) ( axismundi_actors_get_text_map( $ax_ct_nm_sid )['en-US']['name'] ?? '' )
	);

	// Changing the card reaches the locale that follows it, and only that one.
	$ax_ct_nm_doc = axismundi_contacts_card_document( $ax_ct_nm_card );
	$ax_ct_nm_doc = axismundi_contacts_set_localized_name( $ax_ct_nm_doc, 'ko-Latn', array( '@type' => 'Name', 'full' => 'Ji-woon Kim' ) );
	axismundi_contacts_save_card_for_owner( $ax_ct_nm_sid, $ax_ct_nm_doc, $ax_ct_nm_card );
	$ax_ct_nm_map = axismundi_actors_get_text_map( $ax_ct_nm_sid );
	ax_ct_assert(
		$ax_ct_results,
		'a card edit reaches the locale bound to it and leaves an unbound one alone',
		'Ji-woon Kim' === (string) ( $ax_ct_nm_map['en-US']['name'] ?? '' )
			&& 'Eigener Name' === (string) ( $ax_ct_nm_map['de-DE']['name'] ?? '' )
	);

	/*
	 * And a writing the card offers that nothing points at does not become a locale. `ko-Latn` is on
	 * the card and is not a language somebody chose to be shown in; making it one would decide, on
	 * their behalf, that a reader asking for Korean-in-Latin-script should get it.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a writing the card offers creates no Actor locale of its own',
		array_key_exists( 'ko-Latn', axismundi_contacts_name_representations( $ax_ct_nm_sid ) )
			&& ! array_key_exists( 'ko-Latn', axismundi_actors_get_text_map( $ax_ct_nm_sid ) )
	);

	/*
	 * The one that mattered most for the removal: a name already stored keeps standing. Nothing
	 * republishes it, nothing regenerates it, and no assembler is left to overwrite it -- which is
	 * exactly why removing the assembler was safe to do.
	 */
	$ax_ct_nm_actor = axismundi_actors_get_by_identity( $ax_ct_nm_sid );
	ax_ct_assert(
		$ax_ct_results,
		'and the Actor still answers with what is stored, from a store nothing writes on its own',
		$ax_ct_nm_actor instanceof Axismundi_Actor
			&& 'Eigener Name' === axismundi_actors_person_display_name( $ax_ct_nm_actor, 'de-DE' )
			&& ! function_exists( 'axismundi_contacts_sync_name_to_actor' )
			&& ! function_exists( 'axismundi_contacts_legacy_name_state' )
	);

	// -- what a Card says to strangers ------------------------------------------------------------------------

	/*
	 * The Card below is the shape a real one takes: a private email, a mobile number, a home address,
	 * and two notes of which one is about somebody else. Turning sharing on is somebody saying their
	 * profile may be looked at. It is not somebody handing over their phone number.
	 */
	$ax_ct_pub_actor = ax_ct_actor( $ax_ct_users );
	$ax_ct_pub_sid   = (int) $ax_ct_pub_actor->get_identity_id();
	$ax_ct_pub_made  = axismundi_contacts_create_profile_card( $ax_ct_pub_sid );
	$ax_ct_pub_card  = is_wp_error( $ax_ct_pub_made ) ? 0 : (int) $ax_ct_pub_made;
	$ax_ct_loose[]   = $ax_ct_pub_card;

	$ax_ct_pub_doc = axismundi_contacts_card_document( $ax_ct_pub_card );
	$ax_ct_pub_doc = array_merge(
		$ax_ct_pub_doc,
		array(
			'kind'     => 'individual',
			'language' => 'ko-KR',
			'name'     => array( '@type' => 'Name', 'full' => '김지운', 'defaultSeparator' => '' ),
			'emails'   => array(
				'e1' => array( 'address' => 'private@example.test', 'contexts' => array( 'private' => true ) ),
				'e2' => array( 'address' => 'hello@example.test' ),
			),
			'phones'   => array(
				'tel0' => array( 'number' => 'tel:+821000000000', 'contexts' => array( 'private' => true ) ),
			),
			'addresses' => array(
				'home' => array(
					'contexts'   => array( 'private' => true ),
					'components' => array( array( 'kind' => 'locality', 'value' => '남구' ) ),
					'countryCode' => 'KR',
				),
			),
			'onlineServices' => array(
				'x2' => array( 'service' => 'Mastodon', 'uri' => 'https://mastodon.example/users/someone' ),
			),
			'notes' => array(
				'profile' => array( 'note' => 'Site owner.' ),
				'n1'      => array( 'note' => 'University classmate. Lives in Busan.' ),
			),
			'anniversaries' => array(
				'birth' => array( 'kind' => 'birth', 'date' => array( 'year' => 1996, 'month' => 11, 'day' => 20 ) ),
			),
		)
	);
	$ax_ct_pub_doc = axismundi_contacts_set_localized_name( $ax_ct_pub_doc, 'en', array( '@type' => 'Name', 'full' => 'Jiwoon Kim' ) );
	// A localization of a withheld value, in the patch form an import uses. It does not look like an
	// address until it is applied to one, which is exactly what makes it easy to publish by accident.
	$ax_ct_pub_doc['localizations']['en']['addresses/home/components'] = array( array( 'kind' => 'locality', 'value' => 'Nam-gu' ) );
	axismundi_contacts_save_card_for_owner( $ax_ct_pub_sid, $ax_ct_pub_doc, $ax_ct_pub_card );

	axismundi_contacts_set_profile_audience( $ax_ct_pub_sid, 'public' );
	axismundi_contacts_set_profile_sharing_enabled( $ax_ct_pub_sid, true );
	$ax_ct_pub_out = axismundi_contacts_jscontact_card( axismundi_actors_get_by_identity( $ax_ct_pub_sid ) );

	ax_ct_assert(
		$ax_ct_results,
		'a shared Card publishes no email, phone, address, note or anniversary that nobody named',
		is_array( $ax_ct_pub_out )
			&& ! isset( $ax_ct_pub_out['emails'] )
			&& ! isset( $ax_ct_pub_out['phones'] )
			&& ! isset( $ax_ct_pub_out['addresses'] )
			&& ! isset( $ax_ct_pub_out['notes'] )
			&& ! isset( $ax_ct_pub_out['anniversaries'] )
			&& ! isset( $ax_ct_pub_out['onlineServices'] )
	);
	ax_ct_assert(
		$ax_ct_results,
		'and what it does publish is a name, a kind and a language, which is a contact card and nothing about a person',
		is_array( $ax_ct_pub_out )
			&& '김지운' === (string) ( $ax_ct_pub_out['name']['full'] ?? '' )
			&& 'individual' === (string) ( $ax_ct_pub_out['kind'] ?? '' )
			&& 'ko-KR' === (string) ( $ax_ct_pub_out['language'] ?? '' )
			&& '' !== (string) ( $ax_ct_pub_out['uid'] ?? '' )
	);

	/*
	 * A localization is exactly as public as the value it translates. The English rendering of a
	 * withheld home address would have withheld nothing.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a localization of a withheld value is withheld, and one of a published value comes with it',
		is_array( $ax_ct_pub_out )
			&& 'Jiwoon Kim' === (string) ( axismundi_contacts_localized_name( $ax_ct_pub_out, 'en' )['full'] ?? '' )
			&& ! isset( $ax_ct_pub_out['localizations']['en']['addresses/home/components'] )
	);

	// -- named, one entry at a time ---------------------------------------------------------------------------

	/*
	 * And a context is not permission. `e1` is marked private and is published here because somebody
	 * said so; `e2` carries no context at all and is withheld because nobody did. Reading either way
	 * round would be inferring consent from a filing decision.
	 */
	axismundi_contacts_set_published_pointers(
		$ax_ct_pub_sid,
		array( 'name', 'kind', 'emails/e1', 'onlineServices/x2', 'notes/profile' )
	);
	$ax_ct_pub_out = axismundi_contacts_jscontact_card( axismundi_actors_get_by_identity( $ax_ct_pub_sid ) );
	ax_ct_assert(
		$ax_ct_results,
		'a named entry is published and its siblings are not, whatever context either of them carries',
		is_array( $ax_ct_pub_out )
			&& 'private@example.test' === (string) ( $ax_ct_pub_out['emails']['e1']['address'] ?? '' )
			&& ! isset( $ax_ct_pub_out['emails']['e2'] )
			&& isset( $ax_ct_pub_out['onlineServices']['x2'] )
			&& isset( $ax_ct_pub_out['notes']['profile'] )
			&& ! isset( $ax_ct_pub_out['notes']['n1'] )
	);
	/*
	 * And the floor is a floor. Dropping a pointer stops publishing it -- that is the whole rule --
	 * with the exception of the three things that say who this is: the name, what kind of thing it is,
	 * and the language it is written in. None of those is a fact about a person, all three are already
	 * being served at the Actor's own address, and a Card that withheld them would be published as
	 * somebody unidentifiable rather than as somebody private.
	 *
	 * `language` is the one that shows it, because it is the only one of the three that this list does
	 * not name: it is served anyway.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'what says who this is cannot be dropped, and everything else still can',
		is_array( $ax_ct_pub_out )
			&& ! in_array( 'language', axismundi_contacts_published_pointers( $ax_ct_pub_sid ), true )
			&& 'ko-KR' === (string) ( $ax_ct_pub_out['language'] ?? '' )
			// And a note nobody named is still a note nobody sees.
			&& ! isset( $ax_ct_pub_out['notes']['n1'] )
			&& ! isset( $ax_ct_pub_out['emails']['e2'] )
	);

	/*
	 * A pointer nobody can act on is refused rather than stored. Somebody who is shown a stored
	 * `phones` believes they published their number; if nothing serves it they are wrong in the safe
	 * direction, and if something later does they are wrong in the other one.
	 */
	axismundi_contacts_set_published_pointers( $ax_ct_pub_sid, array( 'name', 'phones', 'vCards/v1', 'notes/n1/note', 'emails/e1' ) );
	ax_ct_assert(
		$ax_ct_results,
		'a pointer this cannot act on is not stored, so nobody is told they published something that never went out',
		array( 'name', 'emails/e1' ) === axismundi_contacts_published_pointers( $ax_ct_pub_sid )
			&& ! axismundi_contacts_is_publishable_pointer( 'phones' )
			&& ! axismundi_contacts_is_publishable_pointer( 'notes/n1/note' )
			&& axismundi_contacts_is_publishable_pointer( 'phones/tel0' )
	);

	/*
	 * And a contributor may not put back what was left out. Everything reaching this filter is going
	 * into a document anybody may fetch, and the question of what this Actor publishes was answered
	 * once, by the person it describes.
	 */
	$ax_ct_pub_smuggle = static function ( array $card ) : array {
		$card['phones'] = array( 'tel0' => array( 'number' => 'tel:+821000000000' ) );
		$card['notes']  = array( 'n1' => array( 'note' => 'University classmate.' ) );
		return $card;
	};
	add_filter( 'axismundi_contacts_jscontact_card', $ax_ct_pub_smuggle, 99 );
	$ax_ct_pub_out = axismundi_contacts_jscontact_card( axismundi_actors_get_by_identity( $ax_ct_pub_sid ) );
	remove_filter( 'axismundi_contacts_jscontact_card', $ax_ct_pub_smuggle, 99 );
	ax_ct_assert(
		$ax_ct_results,
		'a contributor cannot add a property the projection left out',
		is_array( $ax_ct_pub_out )
			&& ! isset( $ax_ct_pub_out['phones'] )
			&& ! isset( $ax_ct_pub_out['notes'] )
			&& isset( $ax_ct_pub_out['emails']['e1'] )
	);

	/*
	 * The projection is built by taking rather than by removing, so a property invented after this was
	 * written is unpublished without anybody having to remember to add it to a list.
	 */
	$ax_ct_pub_future = axismundi_contacts_public_projection(
		array( 'uid' => 'urn:uuid:x', 'name' => array( 'full' => 'A' ), 'cryptoKeys' => array( 'k1' => array( 'uri' => 'data:x' ) ) ),
		array( 'name', 'cryptoKeys/k1' )
	);
	ax_ct_assert(
		$ax_ct_results,
		'a property this code has never heard of is not published, even when it is named',
		! isset( $ax_ct_pub_future['cryptoKeys'] )
			&& 'A' === (string) ( $ax_ct_pub_future['name']['full'] ?? '' )
			&& 'urn:uuid:x' === (string) ( $ax_ct_pub_future['uid'] ?? '' )
	);

	// -- reading a contact is not editing one ------------------------------------------------------------------

	/*
	 * The list used to open the edit form. Somebody clicking a name usually wants to read it, and an
	 * edit form answers that while putting every value one keystroke from being changed. So the two are
	 * different screens with different addresses, the way the media library separates looking at an
	 * attachment from editing one.
	 */
	$ax_ct_split_actor = ax_ct_actor( $ax_ct_users );
	$ax_ct_split_sid   = (int) $ax_ct_split_actor->get_identity_id();
	$ax_ct_split_book  = axismundi_contacts_book_for_actor( $ax_ct_split_sid );
	$ax_ct_books[]     = (int) ( $ax_ct_split_book['id'] ?? 0 );
	$ax_ct_split_made  = axismundi_contacts_save_card(
		(int) $ax_ct_split_book['id'],
		array(
			'@type'  => 'Card',
			'kind'   => 'individual',
			'name'   => array( '@type' => 'Name', 'full' => 'Someone To Read' ),
			'phones' => array( 'tel0' => array( 'number' => 'tel:+821011112222', 'contexts' => array( 'private' => true ) ) ),
			'notes'  => array( 'n1' => array( 'note' => 'A note about somebody else.' ) ),
		)
	);
	$ax_ct_split_card = is_wp_error( $ax_ct_split_made ) ? 0 : (int) $ax_ct_split_made;
	$ax_ct_loose[]    = $ax_ct_split_card;

	/*
	 * One Card has one name in the address whatever is being done to it. Two names would mean two ways
	 * to build a link, two things to check a permission against, and a back button that returned
	 * somewhere other than where somebody had been.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a Card is called the same thing whether it is being read or changed, and the action says which',
		false === strpos( axismundi_contacts_screen_url( $ax_ct_split_card ), 'action=edit' )
			&& false !== strpos( axismundi_contacts_screen_url( $ax_ct_split_card ), 'item=' . $ax_ct_split_card )
			&& false !== strpos( axismundi_contacts_edit_url( $ax_ct_split_card ), 'item=' . $ax_ct_split_card )
			&& false !== strpos( axismundi_contacts_edit_url( $ax_ct_split_card ), 'action=edit' )
			&& false === strpos( axismundi_contacts_edit_url( $ax_ct_split_card ), 'card=' )
	);

	wp_set_current_user( (int) $ax_ct_users[ count( $ax_ct_users ) - 1 ] );
	ob_start();
	axismundi_contacts_card_detail( $ax_ct_split_card, 0, 0, $ax_ct_split_sid );
	$ax_ct_detail = (string) ob_get_clean();
	ax_ct_assert(
		$ax_ct_results,
		'the record reads the contact out and offers no field to type into',
		str_contains( $ax_ct_detail, 'Someone To Read' )
			&& str_contains( $ax_ct_detail, 'tel:+821011112222' )
			&& str_contains( $ax_ct_detail, 'A note about somebody else.' )
			&& ! str_contains( $ax_ct_detail, '<input' )
			&& ! str_contains( $ax_ct_detail, '<form' )
			&& str_contains( $ax_ct_detail, 'action=edit' )
	);

	/*
	 * And a contact that is not the Actor's own card is not published by anybody, so the screen does not
	 * raise the question. Somebody's address book is theirs; the public projection belongs only to the
	 * one Card an Actor publishes about itself.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a contact about somebody else says nothing about being published, because it is not',
		! str_contains( $ax_ct_detail, 'What strangers receive' )
	);

	// -- and the profile card says what a stranger gets ---------------------------------------------------------

	$ax_ct_split_profile = axismundi_contacts_create_profile_card( $ax_ct_split_sid );
	$ax_ct_profile_card  = is_wp_error( $ax_ct_split_profile ) ? 0 : (int) $ax_ct_split_profile;
	$ax_ct_loose[]       = $ax_ct_profile_card;
	$ax_ct_profile_doc   = axismundi_contacts_card_document( $ax_ct_profile_card );
	$ax_ct_profile_doc['emails'] = array(
		'e1' => array( 'address' => 'hello@example.test' ),
		'e2' => array( 'address' => 'secret@example.test' ),
	);
	axismundi_contacts_save_card_for_owner( $ax_ct_split_sid, $ax_ct_profile_doc, $ax_ct_profile_card );
	axismundi_contacts_set_published_pointers( $ax_ct_split_sid, array( 'name', 'emails/e1' ) );

	ob_start();
	axismundi_contacts_card_detail( $ax_ct_profile_card, 0, $ax_ct_profile_card, $ax_ct_split_sid );
	$ax_ct_own = (string) ob_get_clean();
	ax_ct_assert(
		$ax_ct_results,
		'the Actor’s own card shows what a stranger receives, which is not what is stored',
		str_contains( $ax_ct_own, 'What strangers receive' )
			&& str_contains( $ax_ct_own, 'hello@example.test' )
			&& str_contains( $ax_ct_own, 'secret@example.test' )
			&& 1 === substr_count( $ax_ct_own, 'secret@example.test' )
			&& 2 === substr_count( $ax_ct_own, 'hello@example.test' )
	);

	/*
	 * The record says what is published and offers no way to change it. A tick that published a phone
	 * number from a page labelled as a view would be the same mistake the projection fixed -- and the
	 * only thing that writes the selection at all is the editor, through the draft route.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a record shows what is published and changes nothing, and only the editor writes that choice',
		! str_contains( $ax_ct_own, 'name="published' )
			&& ! str_contains( $ax_ct_own, '<form' )
			&& ! function_exists( 'axismundi_contacts_publish_fields' )
	);
	/*
	 * A tick is attached to the entry's own id, never to the text beside it or the row it was on.
	 * Somebody correcting a typo, reordering their links or translating a label has not changed which
	 * value they publish -- and consent that travelled with a display string would land on a different
	 * value the first time either of those happened.
	 */
	$ax_ct_moved = axismundi_contacts_card_document( $ax_ct_profile_card );
	$ax_ct_moved['emails'] = array(
		// Reordered, and the published one's text corrected. Neither is a change of mind.
		'e2' => array( 'address' => 'secret@example.test' ),
		'e1' => array( 'address' => 'hello.corrected@example.test' ),
	);
	axismundi_contacts_save_card_for_owner( $ax_ct_split_sid, $ax_ct_moved, $ax_ct_profile_card );
	$ax_ct_after_move = axismundi_contacts_public_projection(
		axismundi_contacts_card_document( $ax_ct_profile_card ),
		axismundi_contacts_published_pointers( $ax_ct_split_sid )
	);
	ax_ct_assert(
		$ax_ct_results,
		'correcting a published value or reordering its siblings leaves the consent on the same entry',
		array( 'name', 'emails/e1' ) === axismundi_contacts_published_pointers( $ax_ct_split_sid )
			&& 'hello.corrected@example.test' === (string) ( $ax_ct_after_move['emails']['e1']['address'] ?? '' )
			&& ! isset( $ax_ct_after_move['emails']['e2'] )
	);
	wp_set_current_user( 0 );

	// -- a Card survives being stored ---------------------------------------------------------------------------

	/*
	 * The whole document goes in and the whole document comes back. Lossless means structurally, not
	 * byte for byte: whitespace and the order of an object's keys carry no meaning in JSON, and this
	 * deliberately changes both. Everything that does carry meaning has to survive --
	 *
	 *   every property, standard or invented       every entry id
	 *   the JSON type of every value               array order
	 *   localization patch paths, in both forms    contexts, personalInfo, extensions
	 *
	 * The fixture mirrors a real Card's structure with placeholder values. A test fixture holding
	 * somebody's actual telephone number would publish it to everybody who ever clones this.
	 */
	$ax_ct_fixture = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/card-full.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own fixture.
	$ax_ct_rt_actor = ax_ct_actor( $ax_ct_users );
	$ax_ct_rt_book  = axismundi_contacts_book_for_actor( (int) $ax_ct_rt_actor->get_identity_id() );
	$ax_ct_books[]  = (int) ( $ax_ct_rt_book['id'] ?? 0 );
	$ax_ct_rt_saved = axismundi_contacts_save_card( (int) $ax_ct_rt_book['id'], (array) $ax_ct_fixture );
	$ax_ct_rt_id    = is_wp_error( $ax_ct_rt_saved ) ? 0 : (int) $ax_ct_rt_saved;
	$ax_ct_loose[]  = $ax_ct_rt_id;
	$ax_ct_rt_back  = $ax_ct_rt_id > 0 ? axismundi_contacts_card_document( $ax_ct_rt_id ) : array();

	$ax_ct_rt_lost = array();
	$ax_ct_rt_kept = is_array( $ax_ct_fixture ) && ax_ct_preserves( $ax_ct_fixture, $ax_ct_rt_back, $ax_ct_rt_lost );
	if ( array() !== $ax_ct_rt_lost ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "       lost or changed: %s\n", implode( ', ', $ax_ct_rt_lost ) );
	}
	ax_ct_assert(
		$ax_ct_results,
		'a whole Card is stored and read back with nothing lost and nothing changed',
		$ax_ct_rt_kept
	);
	/*
	 * And adds nothing. Storing used to fill in a written-out name where a Card carried only
	 * components -- which meant an import came back out of the database as a document its author had
	 * not written, and an import whose output cannot be compared with its input. The string is worked
	 * out where the name is shown instead.
	 */
	$ax_ct_rt_added = array();
	ax_ct_collect_additions( $ax_ct_fixture, $ax_ct_rt_back, $ax_ct_rt_added );
	if ( array() !== $ax_ct_rt_added ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "       added: %s\n", implode( ', ', $ax_ct_rt_added ) );
	}
	ax_ct_assert(
		$ax_ct_results,
		'and storing a Card adds nothing to it, so an import can be compared with what was imported',
		array() === $ax_ct_rt_added
			&& ! isset( $ax_ct_rt_back['localizations']['ja-Kana']['name']['full'] )
			&& ! function_exists( 'axismundi_contacts_complete_card_names' )
	);
	/*
	 * The string is still available where a name is read. Cards written only in components are
	 * complete, and RFC 9553 leaves working out what they read as to whoever displays them.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a name given only in parts still reads as something, worked out where it is shown',
		"\xe3\x82\xad\xe3\x83\xa0\xe3\x83\xbb\xe3\x82\xb8\xe3\x82\xa6\xe3\x83\xb3" === axismundi_contacts_name_text(
			(array) ( $ax_ct_rt_back['localizations']['ja-Kana']['name'] ?? array() )
		)
			&& "\xea\xb9\x80\xec\xa7\x80\xec\x9a\xb4" === axismundi_contacts_name_text( (array) ( $ax_ct_rt_back['name'] ?? array() ) )
	);

	/*
	 * Named separately because these are the ones a canonicalizer is most likely to quietly tidy away.
	 * An entry id is an address -- what a published pointer and a provenance row name -- so renaming
	 * `e1` would sever somebody's publishing consent from the value they gave it to.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'entry ids are addresses and are never reissued, however the map is written',
		array( 'e1', 'e2' ) === array_keys( (array) ( $ax_ct_rt_back['emails'] ?? array() ) )
			&& array( 'x1', 'x2' ) === array_keys( (array) ( $ax_ct_rt_back['onlineServices'] ?? array() ) )
			&& isset( $ax_ct_rt_back['personalInfo']['wordpress'], $ax_ct_rt_back['notes']['n1'], $ax_ct_rt_back['media']['icon'] )
	);
	ax_ct_assert(
		$ax_ct_results,
		'a property nobody here has heard of survives being stored, with its values and their types',
		array( 'a', 'b' ) === (array) ( $ax_ct_rt_back['example.com:favouriteColour']['flags'] ?? array() )
			&& 3 === ( $ax_ct_rt_back['example.com:favouriteColour']['rank'] ?? null )
			&& true === ( $ax_ct_rt_back['keywords']['ActivityPub'] ?? null )
	);
	/*
	 * A localization is a patch, and its keys are paths into the Card rather than an object to tidy.
	 * Both forms are in the fixture because an import brings the fine-grained one and this screen
	 * writes the whole-property one.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'localization patches keep their paths, in whichever form they arrived',
		"\xe9\x87\x91" === (string) ( $ax_ct_rt_back['localizations']['ko-Hani']['name/components/0/value'] ?? '' )
			&& 'Latn' === (string) ( $ax_ct_rt_back['localizations']['zh-Hant-TW']['name/phoneticScript'] ?? '' )
			&& 'Jiwoon' === (string) ( $ax_ct_rt_back['localizations']['en']['name']['components'][0]['value'] ?? '' )
			&& 'Site Owner' === (string) ( $ax_ct_rt_back['localizations']['en']['titles/siteOwner']['name'] ?? '' )
	);
	/*
	 * And an array is ordered data. The components of a name are a reading order and the separator
	 * between them is one of them; sorting either would rewrite somebody's name.
	 */
	$ax_ct_rt_ja = (array) ( $ax_ct_rt_back['localizations']['ja-Kana']['name']['components'] ?? array() );
	ax_ct_assert(
		$ax_ct_results,
		'array order is data and is left exactly as it was',
		array( 'surname', 'separator', 'given' ) === array_column( $ax_ct_rt_ja, 'kind' )
			&& array( 'country', 'region', 'locality' ) === array_column( (array) ( $ax_ct_rt_back['addresses']['home']['components'] ?? array() ), 'kind' )
	);

	// -- and is written down in the order the standard documents ------------------------------------------------

	/*
	 * None of this changes what the Card means; it changes whether the stored document, its diff and
	 * the Advanced JSON box can be scanned. The order is the standard's own, because a stored Card is
	 * read beside the specification -- and deliberately not the order the editor uses, where an Actor
	 * is the identity and a profile leads with `onlineServices`. A Card in an address book may be a
	 * shop with a phone number and nothing else, and a serializer following a profile's reading order
	 * would bend every one of those to suit a model it is not in.
	 */
	$ax_ct_rt_keys = array_keys( $ax_ct_rt_back );
	$ax_ct_at = static function ( string $key ) use ( $ax_ct_rt_keys ) : int {
		$found = array_search( $key, $ax_ct_rt_keys, true );
		return false === $found ? PHP_INT_MAX : (int) $found;
	};
	ax_ct_assert(
		$ax_ct_results,
		'a Card is written in the groups the standard documents, and in that order within each',
		$ax_ct_at( 'uid' ) < $ax_ct_at( 'name' )
			&& $ax_ct_at( 'name' ) < $ax_ct_at( 'organizations' )
			&& $ax_ct_at( 'organizations' ) < $ax_ct_at( 'titles' )
			&& $ax_ct_at( 'titles' ) < $ax_ct_at( 'emails' )
			&& $ax_ct_at( 'emails' ) < $ax_ct_at( 'onlineServices' )
			&& $ax_ct_at( 'onlineServices' ) < $ax_ct_at( 'phones' )
			&& $ax_ct_at( 'phones' ) < $ax_ct_at( 'calendars' )
			&& $ax_ct_at( 'calendars' ) < $ax_ct_at( 'addresses' )
			&& $ax_ct_at( 'addresses' ) < $ax_ct_at( 'links' )
			&& $ax_ct_at( 'links' ) < $ax_ct_at( 'media' )
			&& $ax_ct_at( 'media' ) < $ax_ct_at( 'anniversaries' )
			&& $ax_ct_at( 'anniversaries' ) < $ax_ct_at( 'notes' )
	);
	ax_ct_assert(
		$ax_ct_results,
		'the patches that apply over all of it are written after all of it, and inventions after those',
		$ax_ct_at( 'notes' ) < $ax_ct_at( 'localizations' )
			&& $ax_ct_at( 'localizations' ) < $ax_ct_at( 'example.com:favouriteColour' )
	);
	// An empty object says nothing an absent property does not say, so it is not written down.
	$ax_ct_empty = axismundi_contacts_canonical_card(
		array( '@type' => 'Card', 'name' => array( 'full' => 'A' ), 'emails' => array(), 'phones' => array() )
	);
	ax_ct_assert(
		$ax_ct_results,
		'an empty map is not stored, so a screen may offer a blank row without the document recording it',
		! array_key_exists( 'emails', $ax_ct_empty )
			&& ! array_key_exists( 'phones', $ax_ct_empty )
			&& 'A' === (string) ( $ax_ct_empty['name']['full'] ?? '' )
	);

	// -- the one door the editor writes through -----------------------------------------------------------------

	/*
	 * The visual form, the localizations screen and the Advanced JSON box are three views of one
	 * document, so they read and write one thing. What this pins is that the document survives the
	 * trip: a Card that lost whatever a view does not display would be lost by whichever view saved
	 * last, and nobody would know which.
	 */
	$ax_ct_dr_actor = ax_ct_actor( $ax_ct_users );
	$ax_ct_dr_sid   = (int) $ax_ct_dr_actor->get_identity_id();
	$ax_ct_dr_book  = axismundi_contacts_book_for_actor( $ax_ct_dr_sid );
	$ax_ct_books[]  = (int) ( $ax_ct_dr_book['id'] ?? 0 );
	$ax_ct_dr_made  = axismundi_contacts_save_card( (int) $ax_ct_dr_book['id'], (array) $ax_ct_fixture );
	$ax_ct_dr_id    = is_wp_error( $ax_ct_dr_made ) ? 0 : (int) $ax_ct_dr_made;
	$ax_ct_loose[]  = $ax_ct_dr_id;

	wp_set_current_user( (int) $ax_ct_users[ count( $ax_ct_users ) - 1 ] );

	$ax_ct_dr_get = rest_do_request( new WP_REST_Request( 'GET', '/axismundi-contacts/v1/cards/' . $ax_ct_dr_id . '/draft' ) );
	$ax_ct_dr_one = (array) $ax_ct_dr_get->get_data();

	$ax_ct_dr_put = new WP_REST_Request( 'PUT', '/axismundi-contacts/v1/cards/' . $ax_ct_dr_id . '/draft' );
	$ax_ct_dr_put->set_body_params(
		array( 'revision' => (int) ( $ax_ct_dr_one['revision'] ?? 0 ), 'card' => (array) ( $ax_ct_dr_one['card'] ?? array() ) )
	);
	$ax_ct_dr_saved = rest_do_request( $ax_ct_dr_put );

	$ax_ct_dr_get2 = rest_do_request( new WP_REST_Request( 'GET', '/axismundi-contacts/v1/cards/' . $ax_ct_dr_id . '/draft' ) );
	$ax_ct_dr_two  = (array) $ax_ct_dr_get2->get_data();

	$ax_ct_dr_lost = array();
	$ax_ct_dr_add  = array();
	ax_ct_preserves( (array) ( $ax_ct_dr_one['card'] ?? array() ), (array) ( $ax_ct_dr_two['card'] ?? array() ), $ax_ct_dr_lost );
	ax_ct_collect_additions( (array) ( $ax_ct_dr_one['card'] ?? array() ), (array) ( $ax_ct_dr_two['card'] ?? array() ), $ax_ct_dr_add );
	if ( array() !== $ax_ct_dr_lost || array() !== $ax_ct_dr_add ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "       moved: %s %s\n", implode( ',', $ax_ct_dr_lost ), implode( ',', $ax_ct_dr_add ) );
	}
	ax_ct_assert(
		$ax_ct_results,
		'a whole Card goes out and comes back through the editor door unchanged',
		200 === $ax_ct_dr_get->get_status()
			&& 200 === $ax_ct_dr_saved->get_status()
			&& array() === $ax_ct_dr_lost
			&& array() === $ax_ct_dr_add
			&& (int) ( $ax_ct_dr_two['revision'] ?? 0 ) > (int) ( $ax_ct_dr_one['revision'] ?? 0 )
	);
	ax_ct_assert(
		$ax_ct_results,
		'and what it holds is the ledger and the revision it was read at, with no provenance mixed in',
		array( 'card', 'revision' ) === array_keys( $ax_ct_dr_one )
			&& isset( $ax_ct_dr_two['card']['localizations']['zh-Hant-TW']['name/phoneticScript'] )
			&& isset( $ax_ct_dr_two['card']['example.com:favouriteColour'] )
	);

	/*
	 * A save written against a version somebody else has replaced is refused whole. The editor still
	 * holds its own copy, which is the point: a half-applied save would leave a ledger nobody authored
	 * and an editor that could not tell which half it had.
	 */
	$ax_ct_dr_stale = new WP_REST_Request( 'PUT', '/axismundi-contacts/v1/cards/' . $ax_ct_dr_id . '/draft' );
	$ax_ct_dr_stale->set_body_params(
		array(
			'revision' => (int) ( $ax_ct_dr_one['revision'] ?? 0 ),
			'card'     => array_merge( (array) ( $ax_ct_dr_two['card'] ?? array() ), array( 'kind' => 'org' ) ),
		)
	);
	$ax_ct_dr_refused = rest_do_request( $ax_ct_dr_stale );
	ax_ct_assert(
		$ax_ct_results,
		'a save written against a version that has been replaced changes nothing',
		409 === $ax_ct_dr_refused->get_status()
			&& 'individual' === (string) ( axismundi_contacts_card_document( $ax_ct_dr_id )['kind'] ?? '' )
	);

	/*
	 * And a document that is not a Card is refused before anything is written. A hand-edited JSON box
	 * can send anything, which is the whole reason this is checked here rather than trusted.
	 */
	$ax_ct_dr_bad = static function ( array $card ) use ( $ax_ct_dr_id, $ax_ct_dr_two ) {
		$request = new WP_REST_Request( 'PUT', '/axismundi-contacts/v1/cards/' . $ax_ct_dr_id . '/draft' );
		$request->set_body_params( array( 'revision' => (int) ( $ax_ct_dr_two['revision'] ?? 0 ), 'card' => $card ) );
		return rest_do_request( $request )->get_status();
	};
	$ax_ct_dr_ok = (array) ( $ax_ct_dr_two['card'] ?? array() );
	ax_ct_assert(
		$ax_ct_results,
		'a document that is not a JSContact Card is refused, and a name that says nothing with it',
		400 === $ax_ct_dr_bad( array_diff_key( $ax_ct_dr_ok, array( '@type' => true ) ) )
			&& 400 === $ax_ct_dr_bad( array_merge( $ax_ct_dr_ok, array( 'version' => 7 ) ) )
			&& 400 === $ax_ct_dr_bad( array_merge( $ax_ct_dr_ok, array( 'name' => array( '@type' => 'Name' ) ) ) )
			&& 400 === $ax_ct_dr_bad( array_merge( $ax_ct_dr_ok, array( 'localizations' => array( 'en' => array( 'localizations/en' => 'x' ) ) ) ) )
			&& 'individual' === (string) ( axismundi_contacts_card_document( $ax_ct_dr_id )['kind'] ?? '' )
	);
	/*
	 * Either half of a name is a whole name. Components with nothing written out is what an import
	 * brings; a written-out name that was never taken apart is how most of the world's names are best
	 * recorded.
	 */
	$ax_ct_dr_plain = array_diff_key( $ax_ct_dr_ok, array( 'localizations' => true ) );
	ax_ct_assert(
		$ax_ct_results,
		'a name is complete written out, or complete in parts, and neither needs the other',
		200 === $ax_ct_dr_bad( array_merge( $ax_ct_dr_plain, array( 'name' => array( '@type' => 'Name', 'full' => 'Prince' ) ) ) )
	);

	// -- a localization patches something that is there ----------------------------------------------------------

	/*
	 * A patch names a value inside the Card. Everything but the last step of that path has to be there
	 * already -- a patch replaces a value inside something rather than conjuring the thing that holds
	 * it -- so a name rewritten without its parts leaves every patch that pointed into them with
	 * nothing to apply to, and the save is refused rather than storing a document that cannot be read.
	 */
	/*
	 * Its own copy of the fixture, without the uid: one owner may keep one Card per uid, and the checks
	 * above have already changed the one they were given.
	 */
	$ax_ct_pa_made = axismundi_contacts_save_card(
		(int) $ax_ct_dr_book['id'],
		array_diff_key( (array) $ax_ct_fixture, array( 'uid' => true ) )
	);
	$ax_ct_pa_key   = is_wp_error( $ax_ct_pa_made ) ? 0 : (int) $ax_ct_pa_made;
	$ax_ct_loose[]  = $ax_ct_pa_key;
	$ax_ct_pa_draft = (array) rest_do_request( new WP_REST_Request( 'GET', '/axismundi-contacts/v1/cards/' . $ax_ct_pa_key . '/draft' ) )->get_data();
	// Written against whatever the record says now, so that a refusal here is about the patch rather
	// than about a revision an earlier check moved on from.
	$ax_ct_pa_send = static function ( array $card ) use ( $ax_ct_pa_key ) {
		$request = new WP_REST_Request( 'PUT', '/axismundi-contacts/v1/cards/' . $ax_ct_pa_key . '/draft' );
		$request->set_body_params(
			array( 'revision' => (int) ( axismundi_contacts_get_card( $ax_ct_pa_key )['revision'] ?? 0 ), 'card' => $card )
		);
		return rest_do_request( $request );
	};
	$ax_ct_pa_card = (array) ( $ax_ct_pa_draft['card'] ?? array() );

	ax_ct_assert(
		$ax_ct_results,
		'a card that arrived with patches is stored and read back with all of them, unchanged',
		200 === $ax_ct_pa_send( $ax_ct_pa_card )->get_status()
			&& "\xe9\x87\x91" === (string) ( axismundi_contacts_card_document( $ax_ct_pa_key )['localizations']['ko-Hani']['name/components/0/value'] ?? '' )
	);
	ax_ct_assert(
		$ax_ct_results,
		'and taking away what a patch points into is refused, because the patch would have nothing to apply to',
		400 === $ax_ct_pa_send(
			array_merge( $ax_ct_pa_card, array( 'name' => array( '@type' => 'Name', 'full' => 'Prince' ) ) )
		)->get_status()
	);

	/*
	 * The rules a PatchObject has, checked the same way whatever wrote the patch. Validating an import
	 * differently from a typed edit would mean a Card that arrived could not be read out and written
	 * back unchanged, which is the one thing the draft route exists to guarantee.
	 */
	$ax_ct_pa_with = static function ( array $patch ) use ( $ax_ct_pa_card ) : array {
		$card                        = $ax_ct_pa_card;
		$card['localizations']['de'] = $patch;
		return $card;
	};
	ax_ct_assert(
		$ax_ct_results,
		'a path through something the card does not have is refused, and so is a position past the end of a list',
		400 === $ax_ct_pa_send( $ax_ct_pa_with( array( 'nicknames/n1/name' => 'Kim' ) ) )->get_status()
			&& 400 === $ax_ct_pa_send( $ax_ct_pa_with( array( 'name/components/9/value' => 'Kim' ) ) )->get_status()
	);
	ax_ct_assert(
		$ax_ct_results,
		'appending to a list is not something a localization does, and neither is patching the localizations',
		400 === $ax_ct_pa_send( $ax_ct_pa_with( array( 'name/components/-/value' => 'Kim' ) ) )->get_status()
			&& 400 === $ax_ct_pa_send( $ax_ct_pa_with( array( 'localizations/en/name' => array( 'full' => 'x' ) ) ) )->get_status()
	);
	ax_ct_assert(
		$ax_ct_results,
		'two patches to the same value are refused, because there is no order between them',
		400 === $ax_ct_pa_send( $ax_ct_pa_with( array( 'name' => array( 'full' => 'Kim' ), 'name/full' => 'Kim' ) ) )->get_status()
	);
	/*
	 * And the last step is deliberately not required to exist. Setting a property the base Card does
	 * not carry is something the standard advises a writer against rather than something a reader may
	 * refuse -- so a document that arrived with one is stored, and the screens follow the advice by
	 * offering only paths that are already there.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'setting a value the card does not have yet is allowed, because that is advice to a writer rather than a rule',
		200 === $ax_ct_pa_send( $ax_ct_pa_with( array( 'name/full' => 'Jiwoon Kim' ) ) )->get_status()
	);
	/*
	 * And what the patch leaves behind has to be a Card. A path can be perfectly well formed and still
	 * produce a document no reader can use, so the answer is asked of the result rather than of the
	 * change -- these three are well formed and none of them may be stored.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a patch that would leave a value the wrong shape is refused, however well formed its path is',
		400 === $ax_ct_pa_send( $ax_ct_pa_with( array( 'name/isOrdered' => 'yes' ) ) )->get_status()
	);
	ax_ct_assert(
		$ax_ct_results,
		'and one that would empty a position in a list, leaving a hole where a part of a name was',
		400 === $ax_ct_pa_send( $ax_ct_pa_with( array( 'name/components/0' => null ) ) )->get_status()
	);
	ax_ct_assert(
		$ax_ct_results,
		'and one that would take away something an entry of that kind has to have',
		400 === $ax_ct_pa_send( $ax_ct_pa_with( array( 'media/icon/kind' => null ) ) )->get_status()
			// A label may go: what may be removed is decided by what is left, not by the shape of the patch.
			&& 200 === $ax_ct_pa_send( $ax_ct_pa_with( array( 'emails/e1/label' => null ) ) )->get_status()
	);
	/*
	 * A property nobody here has heard of is not held to rules nobody has written. Refusing what is
	 * merely unrecognised is how an editor becomes the reason somebody's data cannot be stored.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a value in a property this code does not know is left alone, whatever shape it is',
		200 === $ax_ct_pa_send( $ax_ct_pa_with( array( 'example.com:favouriteColour/value' => array( 1, 2 ) ) ) )->get_status()
	);
	/*
	 * And the Card itself is held to the same rules. A document the editor may not produce by patching
	 * is not one it should be able to produce by typing either.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'the card itself answers to the same rules as anything a patch would leave behind',
		400 === $ax_ct_pa_send(
			array_merge( $ax_ct_pa_card, array( 'name' => array( '@type' => 'Name', 'full' => 'Kim', 'isOrdered' => 'yes' ) ) )
		)->get_status()
			&& 400 === $ax_ct_pa_send(
				array_merge( $ax_ct_pa_card, array( 'emails' => array( 'e1' => array( 'label' => 'Home' ) ) ) )
			)->get_status()
	);

	$ax_ct_pa_offer = axismundi_contacts_patchable_paths( $ax_ct_pa_card );
	ax_ct_assert(
		$ax_ct_results,
		'and a screen offers only what the card already says, not what the document is',
		in_array( 'name/full', $ax_ct_pa_offer, true )
			&& in_array( 'name/components/0/value', $ax_ct_pa_offer, true )
			&& ! in_array( 'uid', $ax_ct_pa_offer, true )
			&& ! in_array( 'version', $ax_ct_pa_offer, true )
			&& ! in_array( 'localizations', $ax_ct_pa_offer, true )
	);

	// -- what belongs to the Card an Actor publishes about itself -----------------------------------------------

	/*
	 * The public selection is one Card's, and absent rather than empty everywhere else. An ordinary
	 * contact has no public policy to send, and a field that arrived anyway is a caller confused about
	 * which Card this is rather than one asking to publish nothing.
	 */
	$ax_ct_dr_profile = axismundi_contacts_create_profile_card( $ax_ct_dr_sid );
	$ax_ct_dr_pcard   = is_wp_error( $ax_ct_dr_profile ) ? 0 : (int) $ax_ct_dr_profile;
	$ax_ct_loose[]    = $ax_ct_dr_pcard;
	$ax_ct_dr_pget    = (array) rest_do_request( new WP_REST_Request( 'GET', '/axismundi-contacts/v1/cards/' . $ax_ct_dr_pcard . '/draft' ) )->get_data();

	$ax_ct_dr_pput = new WP_REST_Request( 'PUT', '/axismundi-contacts/v1/cards/' . $ax_ct_dr_pcard . '/draft' );
	$ax_ct_dr_pput->set_body_params(
		array(
			'revision'          => (int) ( $ax_ct_dr_pget['revision'] ?? 0 ),
			'card'             => (array) ( $ax_ct_dr_pget['card'] ?? array() ),
			'publishedPointers' => array( 'name', 'kind' ),
		)
	);
	$ax_ct_dr_pres = rest_do_request( $ax_ct_dr_pput );
	ax_ct_assert(
		$ax_ct_results,
		'the public selection travels with the Card that has one, and with no other',
		array_key_exists( 'publishedPointers', $ax_ct_dr_pget )
			&& ! array_key_exists( 'publishedPointers', $ax_ct_dr_one )
			&& 200 === $ax_ct_dr_pres->get_status()
			&& array( 'name', 'kind' ) === axismundi_contacts_published_pointers( $ax_ct_dr_sid )
	);

	$ax_ct_dr_wrong = new WP_REST_Request( 'PUT', '/axismundi-contacts/v1/cards/' . $ax_ct_dr_id . '/draft' );
	$ax_ct_dr_wrong->set_body_params(
		array(
			'revision'          => (int) ( axismundi_contacts_get_card( $ax_ct_dr_id )['revision'] ?? 0 ),
			'card'             => $ax_ct_dr_ok,
			'publishedPointers' => array( 'name' ),
		)
	);
	$ax_ct_dr_pbad = new WP_REST_Request( 'PUT', '/axismundi-contacts/v1/cards/' . $ax_ct_dr_pcard . '/draft' );
	$ax_ct_dr_pbad->set_body_params(
		array(
			'revision'          => (int) ( axismundi_contacts_get_card( $ax_ct_dr_pcard )['revision'] ?? 0 ),
			'card'             => (array) axismundi_contacts_card_document( $ax_ct_dr_pcard ),
			'publishedPointers' => array( 'phones' ),
		)
	);
	ax_ct_assert(
		$ax_ct_results,
		'a selection sent for an ordinary contact is refused, and so is one naming nothing publishable',
		400 === rest_do_request( $ax_ct_dr_wrong )->get_status()
			&& 400 === rest_do_request( $ax_ct_dr_pbad )->get_status()
			&& array( 'name', 'kind' ) === axismundi_contacts_published_pointers( $ax_ct_dr_sid )
	);

	// -- whose Card it is decides, not which group it was opened from -------------------------------------------

	/*
	 * A Card belongs to as many groups as somebody files it into, so which sidebar it was opened from
	 * says nothing about who may change it. The answer comes from its owner, and somebody else's Card
	 * answers the same way a Card that does not exist does -- so this route cannot be used to ask which
	 * contacts a stranger keeps.
	 */
	$ax_ct_dr_other = ax_ct_actor( $ax_ct_users );
	wp_set_current_user( (int) $ax_ct_users[ count( $ax_ct_users ) - 1 ] );
	$ax_ct_dr_denied = rest_do_request( new WP_REST_Request( 'GET', '/axismundi-contacts/v1/cards/' . $ax_ct_dr_id . '/draft' ) );
	$ax_ct_dr_ghost  = rest_do_request( new WP_REST_Request( 'GET', '/axismundi-contacts/v1/cards/99999999/draft' ) );
	ax_ct_assert(
		$ax_ct_results,
		'somebody else’s contact answers exactly as one that does not exist',
		404 === $ax_ct_dr_denied->get_status()
			&& $ax_ct_dr_ghost->get_status() === $ax_ct_dr_denied->get_status()
			&& $ax_ct_dr_other instanceof Axismundi_Actor
	);
	wp_set_current_user( 0 );

	// -- an edit made through the editor becomes yours -----------------------------------------------------------

	// Everything below is done as the person whose address book it is, through the route they use.
	wp_set_current_user( (int) $ax_ct_dr_actor->get_local_user_id() );

	/*
	 * A value that came from an Actor is refreshed when the Actor changes, which is what makes linking
	 * a contact worth doing. The moment somebody edits one, that has to stop being true of it -- or
	 * the next refresh puts the Actor's answer back over the top of theirs.
	 *
	 * The screen form has always done this. Doing it only there would have made the editor this route
	 * exists for the one place where editing a linked value did not make it yours.
	 */
	$ax_ct_pv_card = axismundi_contacts_save_card(
		(int) $ax_ct_dr_book['id'],
		array(
			'@type'  => 'Card',
			'kind'   => 'individual',
			'name'   => array(
				'@type'      => 'Name',
				'full'       => 'Jiwoon Kim',
				'isOrdered'  => true,
				'components' => array(
					array( '@type' => 'NameComponent', 'kind' => 'given', 'value' => 'Jiwoon' ),
					array( '@type' => 'NameComponent', 'kind' => 'surname', 'value' => 'Kim' ),
				),
			),
			'emails' => array( 'e1' => array( 'address' => 'from-actor@example.invalid', 'label' => 'Work' ) ),
		)
	);
	$ax_ct_pv_id   = is_wp_error( $ax_ct_pv_card ) ? 0 : (int) $ax_ct_pv_card;
	$ax_ct_loose[] = $ax_ct_pv_id;
	axismundi_contacts_set_provenance( $ax_ct_pv_id, 'name', AXISMUNDI_CONTACTS_SOURCE_ACTOR );
	axismundi_contacts_set_provenance( $ax_ct_pv_id, 'emails/e1', AXISMUNDI_CONTACTS_SOURCE_ACTOR );

	/*
	 * The edit that used to slip through: a surname corrected while the written-out name is left
	 * alone. Nothing a list displays has changed, so a comparison of `name.full` saw no edit and the
	 * value stayed the Actor's -- to be replaced on the next refresh by the name somebody had just
	 * corrected.
	 */
	$ax_ct_pv_draft = (array) rest_do_request( new WP_REST_Request( 'GET', '/axismundi-contacts/v1/cards/' . $ax_ct_pv_id . '/draft' ) )->get_data();
	$ax_ct_pv_edit  = (array) ( $ax_ct_pv_draft['card'] ?? array() );
	$ax_ct_pv_edit['name']['components'][1]['value'] = 'KIM';
	$ax_ct_pv_edit['emails']['e1']['label']          = 'Personal';
	$ax_ct_pv_put = new WP_REST_Request( 'PUT', '/axismundi-contacts/v1/cards/' . $ax_ct_pv_id . '/draft' );
	$ax_ct_pv_put->set_body_params( array( 'revision' => (int) ( $ax_ct_pv_draft['revision'] ?? 0 ), 'card' => $ax_ct_pv_edit ) );
	$ax_ct_pv_res  = rest_do_request( $ax_ct_pv_put );
	$ax_ct_pv_prov = axismundi_contacts_card_provenance( $ax_ct_pv_id );
	ax_ct_assert(
		$ax_ct_results,
		'correcting a name in parts makes the name yours, even when the written-out form never moved',
		200 === $ax_ct_pv_res->get_status()
			&& AXISMUNDI_CONTACTS_SOURCE_LOCAL === (string) ( $ax_ct_pv_prov['name']['source'] ?? '' )
			&& 'Jiwoon Kim' === (string) ( axismundi_contacts_card_document( $ax_ct_pv_id )['name']['full'] ?? '' )
	);
	ax_ct_assert(
		$ax_ct_results,
		'and relabelling an entry is editing it, so that one becomes yours too',
		AXISMUNDI_CONTACTS_SOURCE_LOCAL === (string) ( $ax_ct_pv_prov['emails/e1']['source'] ?? '' )
			&& ! axismundi_contacts_source_may_write( $ax_ct_pv_id, 'emails/e1', AXISMUNDI_CONTACTS_SOURCE_ACTOR )
	);

	/*
	 * And what was left alone keeps its source, so a refresh may still update it. Promoting everything
	 * on every save would quietly unlink a contact somebody linked on purpose.
	 */
	$ax_ct_pv_second = (array) rest_do_request( new WP_REST_Request( 'GET', '/axismundi-contacts/v1/cards/' . $ax_ct_pv_id . '/draft' ) )->get_data();
	$ax_ct_pv_note   = (array) ( $ax_ct_pv_second['card'] ?? array() );
	$ax_ct_pv_note['notes'] = array( 'n1' => array( 'note' => 'Something unrelated.' ) );
	axismundi_contacts_set_provenance( $ax_ct_pv_id, 'emails/e1', AXISMUNDI_CONTACTS_SOURCE_ACTOR );
	$ax_ct_pv_put2 = new WP_REST_Request( 'PUT', '/axismundi-contacts/v1/cards/' . $ax_ct_pv_id . '/draft' );
	$ax_ct_pv_put2->set_body_params( array( 'revision' => (int) ( $ax_ct_pv_second['revision'] ?? 0 ), 'card' => $ax_ct_pv_note ) );
	rest_do_request( $ax_ct_pv_put2 );
	ax_ct_assert(
		$ax_ct_results,
		'a value nobody touched keeps its source, so the Actor it came from may still update it',
		AXISMUNDI_CONTACTS_SOURCE_ACTOR === (string) ( axismundi_contacts_card_provenance( $ax_ct_pv_id )['emails/e1']['source'] ?? '' )
	);

	/*
	 * And the promotion is part of the save rather than an afterthought to it. A Card that stored
	 * somebody's edit while provenance still said an Actor owned the value is the exact state a later
	 * refresh silently undoes -- so if that write cannot be made, none of the save is.
	 *
	 * Forced here by taking the table away, which is the only way to make the write fail on demand.
	 */
	$ax_ct_rb_draft = (array) rest_do_request( new WP_REST_Request( 'GET', '/axismundi-contacts/v1/cards/' . $ax_ct_pv_id . '/draft' ) )->get_data();
	$ax_ct_rb_card  = (array) ( $ax_ct_rb_draft['card'] ?? array() );
	$ax_ct_rb_card['name'] = array( '@type' => 'Name', 'full' => 'Never Stored' );
	$ax_ct_rb_before = axismundi_contacts_card_document( $ax_ct_pv_id );

	global $wpdb;
	$ax_ct_prov_table = axismundi_contacts_provenance_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture keeps the rows and puts the table back below.
	$wpdb->query( "CREATE TABLE {$ax_ct_prov_table}_axct LIKE {$ax_ct_prov_table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	$wpdb->query( "INSERT INTO {$ax_ct_prov_table}_axct SELECT * FROM {$ax_ct_prov_table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture.
	$wpdb->query( "DROP TABLE {$ax_ct_prov_table}" );
	$ax_ct_rb_hide = $wpdb->suppress_errors( true );

	$ax_ct_rb_put = new WP_REST_Request( 'PUT', '/axismundi-contacts/v1/cards/' . $ax_ct_pv_id . '/draft' );
	$ax_ct_rb_put->set_body_params( array( 'revision' => (int) ( $ax_ct_rb_draft['revision'] ?? 0 ), 'card' => $ax_ct_rb_card ) );
	$ax_ct_rb_res = rest_do_request( $ax_ct_rb_put );

	$wpdb->suppress_errors( $ax_ct_rb_hide );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture restore.
	$wpdb->query( "CREATE TABLE {$ax_ct_prov_table} LIKE {$ax_ct_prov_table}_axct" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture restore.
	$wpdb->query( "INSERT INTO {$ax_ct_prov_table} SELECT * FROM {$ax_ct_prov_table}_axct" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( "DROP TABLE {$ax_ct_prov_table}_axct" );

	ax_ct_assert(
		$ax_ct_results,
		'a save that cannot record whose the edit is does not store the edit either',
		200 !== $ax_ct_rb_res->get_status()
			// Named, so this cannot pass because the request was refused for some other reason.
			&& 'ax_contacts_provenance_save' === (string) ( ( (array) $ax_ct_rb_res->get_data() )['code'] ?? '' )
			&& $ax_ct_rb_before === axismundi_contacts_card_document( $ax_ct_pv_id )
			&& 'Never Stored' !== (string) ( axismundi_contacts_card_document( $ax_ct_pv_id )['name']['full'] ?? '' )
	);

	// -- the same person, two address books ----------------------------------------------------------------------

	/*
	 * A `uid` says two Cards are about the same somebody. It says nothing about who may change either
	 * of them: that is decided by who owns the record. So the Card in my address book for Alice may
	 * carry the `uid` from the Card Alice publishes about herself, and I may still call her what I
	 * call her, add the number she gave me, and write a note she will never see.
	 */
	$ax_ct_uid_shared = 'urn:uuid:5f2c1b90-8a34-4e2f-9f01-2c5b6d7e8a90';
	$ax_ct_uid_mine   = axismundi_contacts_save_card(
		(int) $ax_ct_dr_book['id'],
		array( '@type' => 'Card', 'kind' => 'individual', 'uid' => $ax_ct_uid_shared, 'name' => array( '@type' => 'Name', 'full' => 'Alice' ) )
	);
	$ax_ct_loose[] = is_wp_error( $ax_ct_uid_mine ) ? 0 : (int) $ax_ct_uid_mine;

	$ax_ct_uid_actor = ax_ct_actor( $ax_ct_users );
	$ax_ct_uid_sid   = (int) $ax_ct_uid_actor->get_identity_id();
	$ax_ct_uid_book  = axismundi_contacts_book_for_actor( $ax_ct_uid_sid );
	$ax_ct_books[]   = (int) ( $ax_ct_uid_book['id'] ?? 0 );
	$ax_ct_uid_hers  = axismundi_contacts_save_card(
		(int) $ax_ct_uid_book['id'],
		array( '@type' => 'Card', 'kind' => 'individual', 'uid' => $ax_ct_uid_shared, 'name' => array( '@type' => 'Name', 'full' => 'Alice Smith' ) )
	);
	$ax_ct_loose[] = is_wp_error( $ax_ct_uid_hers ) ? 0 : (int) $ax_ct_uid_hers;

	ax_ct_assert(
		$ax_ct_results,
		'two people may each keep a Card for the same somebody, under the same uid',
		! is_wp_error( $ax_ct_uid_mine )
			&& ! is_wp_error( $ax_ct_uid_hers )
			&& (int) $ax_ct_uid_mine !== (int) $ax_ct_uid_hers
			&& $ax_ct_uid_shared === (string) ( axismundi_contacts_card_document( (int) $ax_ct_uid_mine )['uid'] ?? '' )
			&& $ax_ct_uid_shared === (string) ( axismundi_contacts_card_document( (int) $ax_ct_uid_hers )['uid'] ?? '' )
	);

	// Each edits their own, and neither reaches the other.
	$ax_ct_uid_draft = (array) rest_do_request( new WP_REST_Request( 'GET', '/axismundi-contacts/v1/cards/' . (int) $ax_ct_uid_mine . '/draft' ) )->get_data();
	$ax_ct_uid_edit  = (array) ( $ax_ct_uid_draft['card'] ?? array() );
	$ax_ct_uid_edit['name']  = array( '@type' => 'Name', 'full' => "\xec\x95\xa8\xeb\xa6\xac\xec\x8a\xa4 \xeb\x88\x84\xeb\x82\x98" );
	$ax_ct_uid_edit['notes'] = array( 'n1' => array( 'note' => 'Met in Busan.' ) );
	$ax_ct_uid_put = new WP_REST_Request( 'PUT', '/axismundi-contacts/v1/cards/' . (int) $ax_ct_uid_mine . '/draft' );
	$ax_ct_uid_put->set_body_params( array( 'revision' => (int) ( $ax_ct_uid_draft['revision'] ?? 0 ), 'card' => $ax_ct_uid_edit ) );
	$ax_ct_uid_res = rest_do_request( $ax_ct_uid_put );
	ax_ct_assert(
		$ax_ct_results,
		'what I call somebody in my address book is mine to decide, and never reaches theirs',
		200 === $ax_ct_uid_res->get_status()
			&& "\xec\x95\xa8\xeb\xa6\xac\xec\x8a\xa4 \xeb\x88\x84\xeb\x82\x98" === (string) ( axismundi_contacts_card_document( (int) $ax_ct_uid_mine )['name']['full'] ?? '' )
			&& 'Alice Smith' === (string) ( axismundi_contacts_card_document( (int) $ax_ct_uid_hers )['name']['full'] ?? '' )
			&& ! isset( axismundi_contacts_card_document( (int) $ax_ct_uid_hers )['notes'] )
	);
	// And the other owner's Card is still not mine to open, uid or no uid.
	ax_ct_assert(
		$ax_ct_results,
		'and sharing a uid does not open somebody else’s record to me',
		404 === rest_do_request( new WP_REST_Request( 'GET', '/axismundi-contacts/v1/cards/' . (int) $ax_ct_uid_hers . '/draft' ) )->get_status()
	);
	wp_set_current_user( 0 );

	// -- the editor, and the one thing it may write through -----------------------------------------------------

	/*
	 * The screen is a heading and an empty element. Drawing the Card here in PHP as well would be a
	 * second rendering of the same document, and the two would disagree the first time one of them
	 * was changed.
	 */
	wp_set_current_user( (int) $ax_ct_dr_actor->get_local_user_id() );
	ob_start();
	axismundi_contacts_card_editor_screen( $ax_ct_dr_id, 0 );
	$ax_ct_ed_screen = (string) ob_get_clean();
	ax_ct_assert(
		$ax_ct_results,
		'the edit screen mounts the editor and renders no second copy of the card',
		str_contains( $ax_ct_ed_screen, 'id="ax-contacts-card-editor"' )
			&& ! str_contains( $ax_ct_ed_screen, '<form' )
			&& ! str_contains( $ax_ct_ed_screen, 'name="primary_name' )
	);

	/*
	 * What it is handed to start from is the same payload the route returns, from the same function.
	 * Two shapes would be two things to keep in step, and the one that drifted would be the one nobody
	 * was looking at.
	 */
	$ax_ct_ed_payload = axismundi_contacts_draft_payload( axismundi_contacts_get_card( $ax_ct_dr_id ) );
	ax_ct_assert(
		$ax_ct_results,
		'the editor starts from the draft the route would have given it',
		array( 'card', 'revision' ) === array_keys( $ax_ct_ed_payload )
			&& isset( $ax_ct_ed_payload['card']['example.com:favouriteColour'] )
	);

	/*
	 * And the script writes through the draft and nothing else. A second save path would be a second
	 * set of rules about revisions, provenance and what may be published, maintained separately from
	 * the first and diverging from it quietly.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_ed_source = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/card-editor.js' );
	ax_ct_assert(
		$ax_ct_results,
		'the editor saves through the draft route and has no second way to write a card',
		'' !== $ax_ct_ed_source
			&& str_contains( $ax_ct_ed_source, 'config.draftPath' )
			&& 1 === substr_count( $ax_ct_ed_source, "method: 'PUT'" )
			&& ! str_contains( $ax_ct_ed_source, 'admin-post.php' )
			&& ! str_contains( $ax_ct_ed_source, "method: 'POST'" )
	);
	/*
	 * The revision it read at goes back with the save, so a write against a version somebody else has
	 * replaced is refused rather than merged.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'and it sends back the revision it read, so a stale save is refused rather than merged',
		str_contains( $ax_ct_ed_source, 'revision: revision' )
			&& str_contains( $ax_ct_ed_source, 'setRevision( response.revision )' )
	);
	/*
	 * One draft behind three views. A Card carries `contexts`, `personalInfo` and properties this
	 * editor has no field for, and an editor that rebuilt the document from its inputs would drop
	 * every one of them -- so the fields and the JSON box write the same object.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'the fields and the card itself are one draft, so a property with no field survives being edited',
		str_contains( $ax_ct_ed_source, 'Object.assign( {}, card )' )
			// Written down in the order a stored Card is written in, so the two never look different.
			&& str_contains( $ax_ct_ed_source, 'var written = ordered( next );' )
			&& str_contains( $ax_ct_ed_source, 'function formatJson( value )' )
			&& str_contains( $ax_ct_ed_source, 'setJson( formatJson( written ) )' )
	);
	/*
	 * A new entry gets a new id. An entry id is the address a published pointer and a provenance row
	 * name, so handing a fresh value the id of one that was removed would hand it that value's
	 * publishing consent along with it.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a new entry is given an id of its own rather than one that has been used before',
		str_contains( $ax_ct_ed_source, "Math.random().toString( 36 )" )
	);
	wp_set_current_user( 0 );

	// -- one screen writes a Card ------------------------------------------------------------------------------

	/*
	 * My profile used to carry a second form for the same document: a name, some entries, the public
	 * selection. Two writers with two sets of rules about revisions and provenance, and the one that
	 * fell behind would have been whichever was touched less.
	 *
	 * What is left there is everything that is not the card -- whether it exists, who may read it, and
	 * which of its writings each Actor locale follows. None of those is a property of the document.
	 */
	wp_set_current_user( (int) $ax_ct_dr_actor->get_local_user_id() );
	ob_start();
	axismundi_contacts_profile_editor( (int) $ax_ct_dr_book['id'], $ax_ct_dr_actor );
	$ax_ct_ow_profile = (string) ob_get_clean();
	wp_set_current_user( 0 );

	ax_ct_assert(
		$ax_ct_results,
		'My profile no longer writes the card, and sends people to the one screen that does',
		! str_contains( $ax_ct_ow_profile, 'value="axismundi_contacts_save_card"' )
			&& ! str_contains( $ax_ct_ow_profile, 'name="primary_name' )
			&& ! str_contains( $ax_ct_ow_profile, 'name="published' )
			&& str_contains( $ax_ct_ow_profile, 'action=edit' )
	);
	ax_ct_assert(
		$ax_ct_results,
		'and keeps what is not the card: who may read it, and which writing each locale follows',
		str_contains( $ax_ct_ow_profile, 'value="axismundi_contacts_set_sharing"' )
			&& str_contains( $ax_ct_ow_profile, 'name="audience"' )
	);

	// -- and the ones that were stored before it said so --------------------------------------------------------

	/*
	 * Stating the revision on new Cards leaves every Card stored before it unversioned -- a v2 document
	 * to a stranger and an unversioned one to everything here. The upgrade says it for them, and says
	 * the same thing for a Card that arrived at an older revision: this ledger keeps one.
	 *
	 * Written straight into the column, because nothing offers to store a Card at another revision any
	 * more. That is exactly the state an install upgrading from before this is in.
	 */
	$ax_ct_vm_cards = axismundi_contacts_cards_table();
	$ax_ct_vm_write = static function ( int $card_id, array $card ) use ( $wpdb, $ax_ct_vm_cards ) : void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture writes the state an upgrade finds.
		$wpdb->update( $ax_ct_vm_cards, array( 'card_json' => (string) wp_json_encode( $card ) ), array( 'id' => $card_id ), array( '%s' ), array( '%d' ) );
	};

	$ax_ct_vm_mine = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array( '@type' => 'Card', 'kind' => 'individual', 'name' => array( '@type' => 'Name', 'full' => 'Stored long ago' ) )
	);
	$ax_ct_vm_mine_id = is_wp_error( $ax_ct_vm_mine ) ? 0 : (int) $ax_ct_vm_mine;
	$ax_ct_loose[]    = $ax_ct_vm_mine_id;
	$ax_ct_vm_write( $ax_ct_vm_mine_id, array( '@type' => 'Card', 'kind' => 'individual', 'name' => array( '@type' => 'Name', 'full' => 'Stored long ago' ) ) );

	$ax_ct_vm_old = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array( '@type' => 'Card', 'kind' => 'individual', 'name' => array( '@type' => 'Name', 'full' => 'An older revision' ) )
	);
	$ax_ct_vm_old_id = is_wp_error( $ax_ct_vm_old ) ? 0 : (int) $ax_ct_vm_old;
	$ax_ct_loose[]   = $ax_ct_vm_old_id;
	$ax_ct_vm_write( $ax_ct_vm_old_id, array( '@type' => 'Card', 'version' => '1.0', 'kind' => 'individual', 'name' => array( '@type' => 'Name', 'full' => 'An older revision' ) ) );

	$ax_ct_vm_stated = axismundi_contacts_state_jscontact_version();
	ax_ct_assert(
		$ax_ct_results,
		'a card stored before this said so is told which revision the ledger keeps, and keeps everything else',
		$ax_ct_vm_stated >= 2
			&& AXISMUNDI_CONTACTS_JSCONTACT_VERSION === (string) ( axismundi_contacts_card_document( $ax_ct_vm_mine_id )['version'] ?? '' )
			&& AXISMUNDI_CONTACTS_JSCONTACT_VERSION === (string) ( axismundi_contacts_card_document( $ax_ct_vm_old_id )['version'] ?? '' )
			&& 'An older revision' === (string) ( axismundi_contacts_card_document( $ax_ct_vm_old_id )['name']['full'] ?? '' )
	);
	/*
	 * Running again changes nothing, because the question is answered by the database it just changed.
	 * An upgrade that restated it on every load would rewrite every row for no reason and make
	 * `updated_at` stop meaning anything.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'and a second run changes nothing, because there is nothing left saying anything else',
		0 === axismundi_contacts_state_jscontact_version()
	);

	// -- the pictures on the buttons ------------------------------------------------------------------------------

	/*
	 * Registered once and named everywhere else. A screen that inlined its own SVG would be one more
	 * place a bin could look different from the bin next to it, and the second screen to need `delete`
	 * would have had to copy the first.
	 */
	do_action( 'init' );
	$ax_ct_ic_missing = array();
	foreach ( array_keys( axismundi_contacts_icons() ) as $ax_ct_ic_name ) {
		if ( '' === axismundi_contacts_icon( (string) $ax_ct_ic_name ) ) {
			$ax_ct_ic_missing[] = (string) $ax_ct_ic_name;
		}
	}
	ax_ct_assert(
		$ax_ct_results,
		'every icon this plugin names is in the registry and readable from it',
		array() === $ax_ct_ic_missing
			&& count( axismundi_contacts_icons() ) >= 13
	);
	/*
	 * And each takes the colour of what it sits in. The downloads carry a literal grey, which would
	 * render the same pale shade whatever the surface around it was doing -- a picture of an icon
	 * rather than an icon.
	 */
	$ax_ct_ic_literal = array();
	foreach ( array_keys( axismundi_contacts_icons() ) as $ax_ct_ic_name ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own asset in a dev fixture.
		$ax_ct_ic_svg = (string) file_get_contents( dirname( __DIR__ ) . '/assets/icons/' . $ax_ct_ic_name . '.svg' );
		if ( ! str_contains( $ax_ct_ic_svg, 'currentColor' ) || 1 === preg_match( '/fill="#[0-9a-fA-F]/', $ax_ct_ic_svg ) ) {
			$ax_ct_ic_literal[] = (string) $ax_ct_ic_name;
		}
	}
	ax_ct_assert(
		$ax_ct_results,
		'and every one of them takes its colour from what it sits in rather than carrying its own',
		array() === $ax_ct_ic_literal
			&& file_exists( dirname( __DIR__ ) . '/assets/icons/LICENSE.md' )
	);

	// -- the fields the screens are built from ------------------------------------------------------------------

	/*
	 * Three primitives and no more: a text field, a multi-line one, and a button with a picture on it.
	 * A `Select` is deliberately absent -- the path picker that will want one has to decide first
	 * whether it is a menu or something you type into, and a fake select bolted onto a text field would
	 * answer that by accident.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_fd_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/fields.js' );
	ax_ct_assert(
		$ax_ct_results,
		'the field adapter offers a text field, a multi-line one and an icon button, and no select',
		str_contains( $ax_ct_fd_js, 'TextField: TextField' )
			&& str_contains( $ax_ct_fd_js, 'Textarea: Textarea' )
			&& str_contains( $ax_ct_fd_js, 'IconButton: IconButton' )
			&& ! str_contains( $ax_ct_fd_js, 'Select: ' )
	);
	/*
	 * The label floats because the input is empty, which the input already knows. Asking a script would
	 * mean the label sat wrong for as long as the script took to load, so every input carries a space
	 * as its placeholder and the CSS reads `:placeholder-shown`.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own asset in a dev fixture.
	$ax_ct_fd_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/fields.css' );
	ax_ct_assert(
		$ax_ct_results,
		'the label floats from what the field already knows rather than from anything a script says',
		str_contains( $ax_ct_fd_js, "placeholder: ' '" )
			&& str_contains( $ax_ct_fd_css, ':placeholder-shown' )
			&& str_contains( $ax_ct_fd_js, "'aria-describedby': describedBy" )
	);
	/*
	 * Colours come from wp-admin and are asked for by handle. WordPress registers `wp-theme` against
	 * its own stylesheet and Gutenberg replaces the source with its build, so a plugin naming either
	 * path breaks the moment the other is in charge. Every token has an M3 baseline behind it, so a
	 * context without them still draws a field somebody can use.
	 */
	$ax_ct_fd_tokens = array(
		'--wpds-color-stroke-interactive-neutral',
		'--wpds-color-stroke-focus',
		'--wpds-color-foreground-content-error',
		'--wpds-border-radius-md',
	);
	$ax_ct_fd_missing = array();
	foreach ( $ax_ct_fd_tokens as $ax_ct_fd_token ) {
		if ( ! str_contains( $ax_ct_fd_css, $ax_ct_fd_token . ',' ) ) {
			$ax_ct_fd_missing[] = $ax_ct_fd_token;
		}
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading WordPress's own stylesheet in a dev fixture.
	$ax_ct_fd_core = (string) file_get_contents( ABSPATH . 'wp-includes/css/dist/theme/design-tokens.css' );
	$ax_ct_fd_absent = array();
	foreach ( $ax_ct_fd_tokens as $ax_ct_fd_token ) {
		if ( ! str_contains( $ax_ct_fd_core, $ax_ct_fd_token ) ) {
			$ax_ct_fd_absent[] = $ax_ct_fd_token;
		}
	}
	ax_ct_assert(
		$ax_ct_results,
		'every colour it uses is one WordPress itself defines, with a baseline behind it for when nothing does',
		array() === $ax_ct_fd_missing
			&& array() === $ax_ct_fd_absent
			&& ! str_contains( $ax_ct_fd_css, 'gutenberg' )
	);
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_fd_php = (string) file_get_contents( dirname( __DIR__ ) . '/includes/card-editor.php' );
	/*
	 * A field is its content plus its outline, and `min-height` is a floor rather than a ceiling. So
	 * thickening the outline on focus has to be given back somewhere, or the field grows by two pixels
	 * and pushes the page below it down as somebody tabs through. Measured in the browser at 56px in
	 * both states; what is pinned here is the mechanism, so a later edit cannot quietly bring the two
	 * pixels back.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a field counts its outline in its height, and the padding gives back whatever focus takes',
		str_contains( $ax_ct_fd_css, 'box-sizing: border-box' )
			&& str_contains( $ax_ct_fd_css, '--ax-field-border: var( --ax-field-outline-width );' )
			&& str_contains( $ax_ct_fd_css, '--ax-field-border: var( --ax-field-outline-width-focus );' )
			&& str_contains( $ax_ct_fd_css, 'padding: calc( 16px - var( --ax-field-border ) ) 0;' )
			&& str_contains( $ax_ct_fd_css, 'padding: 0 calc( var( --ax-field-inline ) - var( --ax-field-border ) );' )
			// Focus changes the one variable and nothing else restates the width.
			&& ! str_contains( $ax_ct_fd_css, 'border-width:' )
	);
	ax_ct_assert(
		$ax_ct_results,
		'and it asks for them by handle rather than by a path that belongs to whoever is in charge today',
		str_contains( $ax_ct_fd_php, "wp_style_is( 'wp-theme', 'registered' )" )
			&& ! str_contains( $ax_ct_fd_php, 'design-tokens.css' )
			&& ! str_contains( $ax_ct_fd_php, 'wp-components' )
	);

	// -- the other languages, and what removing a part costs -----------------------------------------------------

	/*
	 * The picker offers paths rather than taking them typed, and offers only what the Card already
	 * says. A patch naming something the Card does not have is one the server refuses, so a picker that
	 * allowed it would be handing somebody a save that fails; what the list cannot express is written
	 * in the JSON box, which is the escape hatch and says so.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_lo_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/card-editor.js' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_lo_fields = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/fields.js' );
	ax_ct_assert(
		$ax_ct_results,
		'a language patches a path chosen from what the card already says, typed into rather than scrolled',
		str_contains( $ax_ct_lo_js, 'patchablePaths' )
			&& str_contains( $ax_ct_lo_js, 'NOT_TRANSLATED' )
			&& str_contains( $ax_ct_lo_fields, 'Combobox: Combobox' )
			&& ! str_contains( $ax_ct_lo_fields, 'Select: ' )
	);
	/*
	 * And what the picker offers is the same set the server computes. Two answers to "which paths may
	 * be translated" would drift, and the one that drifted would be the one somebody was looking at.
	 */
	$ax_ct_lo_card = array(
		'@type'   => 'Card',
		'version' => AXISMUNDI_CONTACTS_JSCONTACT_VERSION,
		'uid'     => 'urn:uuid:0d4b1e9c-6a2f-4c31-9f77-2b8e5a1c4d60',
		'kind'    => 'individual',
		'name'    => array(
			'@type'      => 'Name',
			'components' => array( array( '@type' => 'NameComponent', 'kind' => 'given', 'value' => 'Jiwoon' ) ),
		),
	);
	$ax_ct_lo_offer = axismundi_contacts_patchable_paths( $ax_ct_lo_card );
	ax_ct_assert(
		$ax_ct_results,
		'and the paths it offers are values the card holds, never what the document is',
		in_array( 'name/components/0/value', $ax_ct_lo_offer, true )
			&& ! in_array( 'uid', $ax_ct_lo_offer, true )
			&& ! in_array( 'version', $ax_ct_lo_offer, true )
	);
	/*
	 * Removing a part of a name that a language patches into is the one place the editor has to ask.
	 * Doing it silently would either leave a patch pointing at nothing -- which the server refuses, so
	 * the next save would fail for a reason nobody could see -- or delete somebody's translation along
	 * with a part they were not looking at.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'removing a part that a language translates asks first, and never deletes the translation on its own',
		str_contains( $ax_ct_lo_js, 'patchesUnder' )
			&& str_contains( $ax_ct_lo_js, 'props.onBlocked( at, affected )' )
			&& str_contains( $ax_ct_lo_js, 'Remove them and the part' )
			&& str_contains( $ax_ct_lo_js, 'Keep everything' )
	);
	/*
	 * That the refusal is real, from the server's side: this is the state the editor is protecting
	 * somebody from reaching by accident.
	 */
	$ax_ct_lo_id   = axismundi_contacts_save_card( $ax_ct_book_id, $ax_ct_lo_card );
	$ax_ct_lo_key  = is_wp_error( $ax_ct_lo_id ) ? 0 : (int) $ax_ct_lo_id;
	$ax_ct_loose[] = $ax_ct_lo_key;
	$ax_ct_lo_doc  = axismundi_contacts_card_document( $ax_ct_lo_key );
	$ax_ct_lo_doc['localizations'] = array( 'ja-Kana' => array( 'name/components/0/value' => "\xe3\x82\xad\xe3\x83\xa0" ) );
	axismundi_contacts_save_card_for_owner( (int) $ax_ct_owner->get_identity_id(), $ax_ct_lo_doc, $ax_ct_lo_key );

	$ax_ct_lo_without         = axismundi_contacts_card_document( $ax_ct_lo_key );
	$ax_ct_lo_without['name'] = array( '@type' => 'Name', 'full' => 'Jiwoon' );
	$ax_ct_lo_refused = axismundi_contacts_rest_put_draft(
		( function () use ( $ax_ct_lo_key, $ax_ct_lo_without ) {
			$request = new WP_REST_Request( 'PUT', '/axismundi-contacts/v1/cards/' . $ax_ct_lo_key . '/draft' );
			$request->set_param( 'id', $ax_ct_lo_key );
			$request->set_param( 'revision', (int) ( axismundi_contacts_get_card( $ax_ct_lo_key )['revision'] ?? 0 ) );
			$request->set_param( 'card', $ax_ct_lo_without );
			return $request;
		} )()
	);
	ax_ct_assert(
		$ax_ct_results,
		'because taking the part away while a language still patches into it is refused outright',
		is_wp_error( $ax_ct_lo_refused )
			&& "\xe3\x82\xad\xe3\x83\xa0" === (string) ( axismundi_contacts_card_document( $ax_ct_lo_key )['localizations']['ja-Kana']['name/components/0/value'] ?? '' )
	);

	// -- what a card is, and how it says a name ------------------------------------------------------------------

	/*
	 * What a Card describes decides what the rest of it is for -- a person has a given name, an
	 * organisation has units, a group has members -- so it is asked first rather than after somebody
	 * has filled the card in.
	 *
	 * On the Card an Actor publishes about itself it is not a question. That Actor is a Person, a Group
	 * or an Organization in a registry that federates, and a Card claiming otherwise would say one
	 * thing to a reader and the Actor document another. Shown, and shown as decided.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_kn_php = (string) file_get_contents( dirname( __DIR__ ) . '/includes/card-editor.php' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_kn_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/card-editor.js' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_kn_kinds = (string) file_get_contents( dirname( __DIR__ ) . '/includes/card-editor.php' );
	ax_ct_assert(
		$ax_ct_results,
		'every kind the standard registers is something somebody can say a card is',
		str_contains( $ax_ct_kn_kinds, "'value' => 'individual'" )
			&& str_contains( $ax_ct_kn_kinds, "'value' => 'org'" )
			&& str_contains( $ax_ct_kn_kinds, "'value' => 'group'" )
			&& str_contains( $ax_ct_kn_kinds, "'value' => 'location'" )
			&& str_contains( $ax_ct_kn_kinds, "'value' => 'application'" )
			&& str_contains( $ax_ct_kn_kinds, "'value' => 'device'" )
	);
	ax_ct_assert(
		$ax_ct_results,
		'the card an Actor publishes says what that Actor is, and does not offer to say otherwise',
		str_contains( $ax_ct_kn_php, 'axismundi_contacts_is_profile_card( $row )' )
			&& str_contains( $ax_ct_kn_php, 'axismundi_actors_jscontact_kind' )
			&& str_contains( $ax_ct_kn_js, 'disabled: !! locked' )
			&& 'individual' === axismundi_actors_jscontact_kind( 'Person' )
			&& 'group' === axismundi_actors_jscontact_kind( 'Group' )
			&& 'org' === axismundi_actors_jscontact_kind( 'Organization' )
	);
	/*
	 * A name may be written out, given in parts, or both. The checkboxes decide which editor is open
	 * and nothing else: unticking one leaves its value exactly where it was, because a screen tidying
	 * itself is not somebody deleting their name.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'how much of a name is on screen is the screen deciding, never the card losing a value',
		str_contains( $ax_ct_kn_js, 'var [ expanded, setExpanded ]' )
			&& str_contains( $ax_ct_kn_js, 'var [ phonetic, setPhonetic ]' )
			// What is stored decides what opens, and closing something writes nothing.
			&& str_contains( $ax_ct_kn_js, 'useState( hasPhonetic( components ) )' )
			&& str_contains( $ax_ct_kn_js, 'setExpanded( ! expanded );' )
			&& str_contains( $ax_ct_kn_js, 'setPhonetic( event.target.checked );' )
			// And nobody is asked which half of a name they are writing any more.
			&& ! str_contains( $ax_ct_kn_js, "__( 'Give the name in parts', 'axismundi-contacts' )" )
	);
	/*
	 * A person has a given name and a surname far more often than a credential or a second middle
	 * name, so those two are the section and everything else is behind one control. What a card
	 * describes decides even that: an organisation has no surname, and its name is the name.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a name opens as the two lines a name usually is, and what is not a person opens as one',
		str_contains( $ax_ct_kn_js, "var BASIC_SLOTS = [ 'given', 'surname' ];" )
			&& str_contains( $ax_ct_kn_js, "var NAME_SLOTS = [ 'title', 'given', 'given2', 'surname', 'surname2', 'credential' ];" )
			&& str_contains( $ax_ct_kn_js, 'slotRows( components, BASIC_SLOTS ).map( line )' )
			&& str_contains( $ax_ct_kn_js, 'slotRows( components, MIDDLE_SLOTS, pending, true ).map( line )' )
			&& str_contains( $ax_ct_kn_js, "var personal = 'individual' === ( props.kind || 'individual' );" )
			&& str_contains( $ax_ct_kn_js, 'written || ! personal' )
	);
	/*
	 * A pronunciation belongs beside the part it is a pronunciation of, and only for the parts that
	 * are somebody's name: a separator is `-` and a title is `Dr`, and neither is being said.
	 */
	/*
	 * A line holding a part of the name can be picked up and moved, the way a row of accounts is.
	 * An empty line cannot: there is nothing there yet to be anywhere, so it keeps the column and
	 * leaves the grip out.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a line of a name is moved by picking it up, and an empty one has nothing to pick up',
		str_contains( $ax_ct_kn_js, 'var movable = !! props.part && props.ordered' )
			&& str_contains( $ax_ct_kn_js, 'draggable: movable,' )
			// An empty line is a place to type, so opening the screen it appears on writes nothing.
			&& str_contains( $ax_ct_kn_js, "rows.push( { key: kind + '#0', kind: kind, part: null } );" )
			&& str_contains( $ax_ct_kn_js, "movable ? icon( 'drag-indicator' ) : ''" )
			// And moving one moves the part it stands for, in the document.
			&& str_contains( $ax_ct_kn_js, 'var moved = list.splice( dragging, 1 )[ 0 ];' )
	);
	ax_ct_assert(
		$ax_ct_results,
		'how a part sounds sits beside what it says, for the parts that are somebody rather than punctuation',
		str_contains( $ax_ct_kn_js, "var PHONETIC_SLOTS = [ 'given', 'given2', 'surname', 'surname2' ];" )
			&& str_contains( $ax_ct_kn_js, "props.phonetic && -1 !== PHONETIC_SLOTS.indexOf( props.kind )" )
			&& str_contains( $ax_ct_kn_js, "__( 'Add pronunciation', 'axismundi-contacts' )" )
	);
	/*
	 * And there is one screen. A name with two middle names, something written between two parts, a
	 * kind out of somebody else's export: each of those is a row, in the same stack, rather than a
	 * reason for the editor to turn into a different editor while somebody is part-way through a
	 * name. What a screen cannot draw it loses, so it draws everything.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'every part of a name is a row in one stack, whatever kind it is',
		str_contains( $ax_ct_kn_js, 'function slotRows( components, kinds, pending, everything )' )
			&& str_contains( $ax_ct_kn_js, '-1 === NAME_SLOTS.indexOf( part.kind ) || -1 !== kinds.indexOf( part.kind )' )
			// No second view to fall into, and nothing left that could switch to one.
			&& ! str_contains( $ax_ct_kn_js, 'fitsSlots' )
			&& ! str_contains( $ax_ct_kn_js, 'var custom' )
			&& ! str_contains( $ax_ct_kn_js, 'function Component(' )
	);
	/*
	 * The written-out name is never built from the parts and never removed on their account. It is
	 * shown when the card carries one -- an import of a name nobody took apart -- and asked for
	 * otherwise, and turning it off throws away what somebody else wrote, so it asks first.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a full name is what a card already says, not something this builds or quietly drops',
		// Shown because the card arrived with a name and nothing else, or because somebody asked.
		str_contains( $ax_ct_kn_js, 'var [ written, setWritten ] = useState( ! components.length && undefined !== name.full );' )
			&& str_contains( $ax_ct_kn_js, "setAsking( 'written' )" )
			&& str_contains( $ax_ct_kn_js, "__( 'Full name', 'axismundi-contacts' )" )
	);
	/*
	 * And a card carrying both is stored carrying both. Neither is derived from the other, in either
	 * direction, at any point between the editor and the database.
	 */
	$ax_ct_kn_id = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type' => 'Card',
			'kind'  => 'individual',
			'name'  => array(
				'@type'      => 'Name',
				'full'       => 'Kim Jiwoon',
				'components' => array( array( '@type' => 'NameComponent', 'kind' => 'surname', 'value' => 'Kim' ) ),
				'sortAs'     => array( 'surname' => 'Kim' ),
			),
		)
	);
	$ax_ct_kn_key  = is_wp_error( $ax_ct_kn_id ) ? 0 : (int) $ax_ct_kn_id;
	$ax_ct_loose[] = $ax_ct_kn_key;
	$ax_ct_kn_name = axismundi_contacts_card_document( $ax_ct_kn_key )['name'] ?? array();
	ax_ct_assert(
		$ax_ct_results,
		'a name written out and given in parts keeps both, and how it files is a third answer',
		'Kim Jiwoon' === (string) ( $ax_ct_kn_name['full'] ?? '' )
			&& 'Kim' === (string) ( $ax_ct_kn_name['components'][0]['value'] ?? '' )
			&& 'Kim' === (string) ( $ax_ct_kn_name['sortAs']['surname'] ?? '' )
	);
	/*
	 * The card's language and the languages this contact prefers are asked with the same control and
	 * are never the same value. One says what the card above is written in; the other says what
	 * somebody would rather receive -- a person whose card is in Korean may ask to be written to in
	 * English, and a card that conflated them would have no way to say so.
	 */
	$ax_ct_kn_langs = axismundi_contacts_card_document( $ax_ct_kn_key );
	$ax_ct_kn_langs['language'] = 'ko-KR';
	$ax_ct_kn_langs['preferredLanguages'] = array( 'l1' => array( '@type' => 'LanguagePref', 'language' => 'en', 'pref' => 1 ) );
	axismundi_contacts_save_card_for_owner( (int) $ax_ct_owner->get_identity_id(), $ax_ct_kn_langs, $ax_ct_kn_key );
	$ax_ct_kn_stored = axismundi_contacts_card_document( $ax_ct_kn_key );
	ax_ct_assert(
		$ax_ct_results,
		'what a card is written in and what its subject prefers to receive are two answers, kept apart',
		'ko-KR' === (string) ( $ax_ct_kn_stored['language'] ?? '' )
			&& 'en' === (string) ( $ax_ct_kn_stored['preferredLanguages']['l1']['language'] ?? '' )
			// Both ask with the same control, from the one list this site offers everywhere.
			&& str_contains( $ax_ct_kn_js, 'var LANGUAGES = config.languages || [];' )
			// A shortcut rather than a list of the languages that exist.
			&& str_contains( $ax_ct_kn_js, 'allowFree: true' )
	);

	// -- the standard's own words -------------------------------------------------------------------------------

	/*
	 * The head of a stored Card answers, in order, what somebody opening one asks: what kind of
	 * document, which version of the standard, which record, what it describes, when it was written
	 * and last touched, what language it says that in, and what wrote it.
	 */
	$ax_ct_hd_order = array_slice( axismundi_contacts_canonical_order(), 0, 8 );
	ax_ct_assert(
		$ax_ct_results,
		'a stored card opens with what it is, which record, and what it describes, in that order',
		array( '@type', 'version', 'uid', 'kind', 'created', 'updated', 'language', 'prodId' ) === $ax_ct_hd_order
	);
	/*
	 * `@type` stays only where it says something. Under `name` it restates the property it is written
	 * on -- there is nowhere else a Name can appear -- and the standard's own examples leave it off.
	 * On the Card it is the top of the document and the only thing telling a reader what it has.
	 *
	 * The date on an anniversary is the exception the rule is written around: there it is a Timestamp
	 * or a PartialDate, and which of the two is an answer nothing else carries.
	 */
	$ax_ct_ty_card = axismundi_contacts_canonical_card(
		array(
			'@type'                 => 'Card',
			'version'               => '2.0',
			'name'                  => array(
				'@type'      => 'Name',
				'components' => array( array( '@type' => 'NameComponent', 'kind' => 'given', 'value' => 'Jiwoon' ) ),
			),
			'anniversaries'         => array(
				'a1' => array(
					'@type' => 'Anniversary',
					'kind'  => 'birth',
					'date'  => array( '@type' => 'PartialDate', 'year' => 1975 ),
				),
				'a2' => array(
					'kind' => 'wedding',
					'date' => array( '@type' => 'Timestamp', 'utc' => '2022-05-01T00:00:00Z' ),
				),
			),
			// Somebody else's property, holding an object that calls itself by a name this file knows.
			'example.com:favourite' => array( '@type' => 'Media', 'uri' => 'https://example.test/x' ),
		)
	);
	ax_ct_assert(
		$ax_ct_results,
		'a type a position already states is not written down, and one that answers something is',
		'Card' === (string) ( $ax_ct_ty_card['@type'] ?? '' )
			&& ! array_key_exists( '@type', $ax_ct_ty_card['name'] )
			&& ! array_key_exists( '@type', $ax_ct_ty_card['name']['components'][0] )
			&& ! array_key_exists( '@type', $ax_ct_ty_card['anniversaries']['a1'] )
			// A date is a PartialDate unless it says otherwise, so that one is implied and goes.
			&& ! array_key_exists( '@type', $ax_ct_ty_card['anniversaries']['a1']['date'] )
			// And a Timestamp there is the answer `@type` is carrying, so it stays.
			&& 'Timestamp' === (string) ( $ax_ct_ty_card['anniversaries']['a2']['date']['@type'] ?? '' )
	);
	/*
	 * By position and never by the word. An object under somebody else's property is whatever they
	 * say it is, in a place this file knows nothing about -- and matching on the type name alone
	 * would strip the one line saying what it was out of a document this plugin does not own.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'and a type somewhere this does not recognise is left alone, whatever it calls itself',
		'Media' === (string) ( $ax_ct_ty_card['example.com:favourite']['@type'] ?? '' )
	);
	/*
	 * How a name files. The value is free: RFC 9553 files a surname of `Shou Chang` under
	 * `Pau Shou Chang`, because a sort key is what a directory should compare rather than a copy of
	 * what the name says.
	 */
	$ax_ct_sa_card = array(
		'@type'   => 'Card',
		'version' => '2.0',
		'name'    => array(
			'components' => array(
				array( 'kind' => 'given', 'value' => 'Robert' ),
				array( 'kind' => 'surname', 'value' => 'Shou Chang' ),
			),
			'sortAs'     => array( 'surname' => 'Pau Shou Chang', 'given' => 'Robert' ),
		),
	);
	$ax_ct_sa_free = axismundi_contacts_validate_card_values( $ax_ct_sa_card );
	// But every key names a part. Filing by a `given` this card does not have tells a directory to
	// sort it under something nobody wrote down.
	$ax_ct_sa_card['name']['components'] = array( array( 'kind' => 'surname', 'value' => 'Shou Chang' ) );
	$ax_ct_sa_missing = axismundi_contacts_validate_card_values( $ax_ct_sa_card );
	ax_ct_assert(
		$ax_ct_results,
		'a name files under whatever text somebody chooses, but only by a part the name actually has',
		true === $ax_ct_sa_free
			&& is_wp_error( $ax_ct_sa_missing )
			&& str_contains( (string) $ax_ct_sa_missing->get_error_message(), 'name/sortAs/given' )
	);
	/*
	 * The separator between the parts, where empty is an answer. A name written without spaces says
	 * `""`, and a card that says nothing about separators at all is a different card -- so the screen
	 * asks with a checkbox and the store keeps the two apart.
	 */
	$ax_ct_sep_empty = axismundi_contacts_canonical_card(
		array(
			'@type' => 'Card',
			'name'  => array(
				'defaultSeparator' => '',
				'components'       => array( array( 'kind' => 'surname', 'value' => 'Kim' ) ),
			),
		)
	);
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_sep_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/card-editor.js' );
	ax_ct_assert(
		$ax_ct_results,
		'a name written with nothing between its parts says so, rather than saying nothing',
		array_key_exists( 'defaultSeparator', $ax_ct_sep_empty['name'] )
			&& '' === $ax_ct_sep_empty['name']['defaultSeparator']
			// The checkbox is whether the card answers: ticking it writes the empty answer.
			&& str_contains( $ax_ct_sep_js, 'checked: undefined !== name.defaultSeparator' )
			&& str_contains( $ax_ct_sep_js, "setName( Object.assign( {}, name, { defaultSeparator: '' } ) );" )
			// And turning it off throws an answer away, so when there is one to lose it asks first.
			&& str_contains( $ax_ct_sep_js, "setAsking( 'separator' )" )
			&& str_contains( $ax_ct_sep_js, "__( 'Keep it', 'axismundi-contacts' )" )
	);
	/*
	 * A separator is a row like the others: added at the end and dragged where it belongs, the same
	 * as a second middle name. A control hidden in the space between two rows put a button between
	 * every pair of lines to serve the one card in fifty that wants one.
	 *
	 * It still exists only for a name whose parts are in the order they are read, because joining
	 * parts nobody has put in order joins them in no order.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'something written between two parts is a part, added and moved like the rest of them',
		str_contains( $ax_ct_sep_js, "var ADDABLE = [ 'given2', 'surname2', 'separator' ];" )
			&& ! str_contains( $ax_ct_sep_js, 'ax-ce-slot-gap' )
			&& ! str_contains( $ax_ct_sep_js, 'addSeparator' )
			&& str_contains( $ax_ct_sep_js, 'ordered && components.length' )
			&& str_contains( $ax_ct_sep_js, 'draggable: movable' )
	);
	/*
	 * How a name files is asked only of somebody who says it is not what the parts already say. Left
	 * alone, a directory reads the parts themselves; the two keys are the two columns a directory
	 * has, and RFC 9553's own example writes a `given2` into the surname key rather than inventing a
	 * third.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a name is filed by its parts unless somebody says otherwise, and a directory has two columns',
		str_contains( $ax_ct_sep_js, "__( 'Custom sorting', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_sep_js, "var SORT_KEYS = [ 'given', 'surname' ];" )
			// Offered only for a part this name has, which is also all the store will accept.
			&& str_contains( $ax_ct_sep_js, 'return hasKind( components, kind );' )
			&& str_contains( $ax_ct_sep_js, "setAsking( 'sorting' )" )
	);
	/*
	 * A title opens a name and a credential closes it, so the buttons for those two put them there.
	 * Everything else joins the end of the name proper, which is in front of any credential already
	 * written -- a name whose letters after it end up in the middle is a name somebody has to drag
	 * back into shape after every addition.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a title opens a name and a credential closes it, wherever somebody adds them from',
		str_contains( $ax_ct_sep_js, 'function endsFirstAndLast( components )' )
			&& str_contains( $ax_ct_sep_js, "var FIXED_FIRST = 'title';" )
			&& str_contains( $ax_ct_sep_js, "var FIXED_LAST = 'credential';" )
			// Applied after anything that moves a part, so no drag can leave them in the middle.
			&& str_contains( $ax_ct_sep_js, 'setComponents( endsFirstAndLast( list ) );' )
			&& str_contains( $ax_ct_sep_js, 'setComponents( endsFirstAndLast( list ), components.length' )
			// And neither of the two is something to pick up in the first place.
			&& str_contains( $ax_ct_sep_js, 'FIXED_FIRST !== props.kind && FIXED_LAST !== props.kind' )
	);
	/*
	 * A name this editor builds is a name whose order it knows -- the list somebody is looking at is
	 * the order they are putting it in -- so the first part written here says so. A name that arrived
	 * saying otherwise keeps saying it: an import that did not know the reading order is not made to
	 * claim one because somebody opened the screen.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a name built here says its parts are in the order they are read, and an import is not made to',
		str_contains( $ax_ct_sep_js, 'components.length ? {} : { isOrdered: true }' )
			&& str_contains( $ax_ct_sep_js, "__( 'Say they are in the order they are read', 'axismundi-contacts' )" )
	);
	/*
	 * And a part somebody added and never filled in is somebody who changed their mind, so it is
	 * dropped on the way out rather than stored empty. A separator keeps its empty value, because
	 * there that is the answer.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a part nobody filled in is not part of a name, and an empty separator still is',
		str_contains( $ax_ct_sep_js, 'function prepare( card )' )
			&& str_contains( $ax_ct_sep_js, "|| ( part.value && String( part.value ).trim() )" )
			// And a part somebody has said the sound of but not yet written is still being written.
			&& str_contains( $ax_ct_sep_js, "|| ( part.phonetic && String( part.phonetic ).trim() )" )
			&& str_contains( $ax_ct_sep_js, 'card: prepare( card )' )
			// Left alone when a language patches into the parts, because dropping one would move the rest.
			&& str_contains( $ax_ct_sep_js, "patchesUnder( card.localizations, 'name/components' ).length" )
	);
	/*
	 * What a Card describes is written down, even when the standard would let it be left out. A Card
	 * with no `kind` is a Card about a person -- that is the default, not an unknown -- and a store
	 * that omitted it would make every reader carry the default: the editor deciding which fields to
	 * draw, the projection deciding what to publish, the importer deciding what it received.
	 */
	$ax_ct_kd_id  = axismundi_contacts_save_card( $ax_ct_book_id, array( '@type' => 'Card', 'name' => array( 'full' => 'No kind stated' ) ) );
	$ax_ct_kd_key = is_wp_error( $ax_ct_kd_id ) ? 0 : (int) $ax_ct_kd_id;
	$ax_ct_loose[] = $ax_ct_kd_key;
	$ax_ct_kd_doc = axismundi_contacts_card_document( $ax_ct_kd_key );
	// And a kind that is already there is never touched, including one this file has never heard of.
	$ax_ct_kd_odd  = axismundi_contacts_save_card( $ax_ct_book_id, array( '@type' => 'Card', 'kind' => 'example.com:vessel', 'name' => array( 'full' => 'Something else' ) ) );
	$ax_ct_kd_okey = is_wp_error( $ax_ct_kd_odd ) ? 0 : (int) $ax_ct_kd_odd;
	$ax_ct_loose[] = $ax_ct_kd_okey;
	ax_ct_assert(
		$ax_ct_results,
		'a card says what it describes even when the standard would let it stay unsaid',
		'individual' === (string) ( $ax_ct_kd_doc['kind'] ?? '' )
			&& 'example.com:vessel' === (string) ( axismundi_contacts_card_document( $ax_ct_kd_okey )['kind'] ?? '' )
			// Written where the standard writes it rather than appended after the fact.
			&& array_search( 'kind', array_keys( $ax_ct_kd_doc ), true ) < array_search( 'name', array_keys( $ax_ct_kd_doc ), true )
			&& is_wp_error( axismundi_contacts_validate_card_values( array( 'kind' => '' ) ) )
	);

	// -- an account is a service, a name there, and an address --------------------------------------------------

	/*
	 * An entry key is an address, not a label. `onlineServices/x1` is what a published pointer names
	 * and what a provenance row is written against, so a key spelling out the service would tie both
	 * to a word somebody is free to change: rename the service and the consent to publish it, along
	 * with the record of where the value came from, point at a row that no longer exists.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'the key an account is stored under says nothing, because it is an address and not a label',
		'x1' === AXISMUNDI_CONTACTS_HOME_SERVICE_KEY
			/*
			 * And a new one is drawn rather than counted. Counting up hands a deleted account's
			 * address to the next one somebody adds, along with whoever had agreed that one could be
			 * published -- the home row keeps its fixed key because that row is the same account
			 * every time it is seeded.
			 */
			&& 1 === preg_match( '/^onl-[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', axismundi_contacts_free_service_key( array() ) )
			&& axismundi_contacts_free_service_key( array() ) !== axismundi_contacts_free_service_key( array() )
	);
	/*
	 * Moving the one key that did spell itself out moves everything that addresses it. A Card renamed
	 * without its published pointer would quietly unpublish an account somebody chose to publish; a
	 * Card renamed without its provenance row would turn a seeded value into an authored one that
	 * never refreshes again. All three or none.
	 */
	$ax_ct_mv_actor = ax_ct_actor( $ax_ct_users );
	$ax_ct_mv_sid   = (int) $ax_ct_mv_actor->get_identity_id();
	$ax_ct_mv_made  = axismundi_contacts_create_profile_card( $ax_ct_mv_sid );
	$ax_ct_mv_card  = is_wp_error( $ax_ct_mv_made ) ? 0 : (int) $ax_ct_mv_made;
	$ax_ct_loose[]  = $ax_ct_mv_card;
	$ax_ct_mv_doc   = axismundi_contacts_card_document( $ax_ct_mv_card );
	// Put it back the way it was written before the key was opaque.
	$ax_ct_mv_doc['onlineServices'] = array(
		'axismundi' => array( 'service' => 'Axismundi', 'user' => '@someone@localhost', 'uri' => $ax_ct_mv_actor->get_uri(), 'pref' => 1 ),
		'x2'        => array( 'service' => 'Mastodon', 'user' => '@someone@mastodon.test', 'uri' => 'https://mastodon.test/@someone', 'pref' => 2 ),
	);
	axismundi_contacts_save_card_for_owner( $ax_ct_mv_sid, $ax_ct_mv_doc, $ax_ct_mv_card );
	axismundi_contacts_set_provenance( $ax_ct_mv_card, 'onlineServices/axismundi', AXISMUNDI_CONTACTS_SOURCE_ACTOR, $ax_ct_mv_actor->get_uri() );
	axismundi_contacts_set_published_pointers( $ax_ct_mv_sid, array( 'name', 'onlineServices/axismundi' ) );

	axismundi_contacts_migrate_home_service_key();

	$ax_ct_mv_after = axismundi_contacts_card_document( $ax_ct_mv_card );
	$ax_ct_mv_prov  = axismundi_contacts_card_provenance( $ax_ct_mv_card );
	$ax_ct_mv_pub   = axismundi_contacts_published_pointers( $ax_ct_mv_sid );
	ax_ct_assert(
		$ax_ct_results,
		'moving an account to an opaque key takes its provenance and its consent to publish with it',
		array( 'x1', 'x2' ) === array_keys( (array) $ax_ct_mv_after['onlineServices'] )
			// The account itself is untouched: only where it is addressed changed.
			&& 'Axismundi' === (string) ( $ax_ct_mv_after['onlineServices']['x1']['service'] ?? '' )
			&& 'Mastodon' === (string) ( $ax_ct_mv_after['onlineServices']['x2']['service'] ?? '' )
			&& isset( $ax_ct_mv_prov['onlineServices/x1'] )
			&& ! isset( $ax_ct_mv_prov['onlineServices/axismundi'] )
			&& in_array( 'onlineServices/x1', $ax_ct_mv_pub, true )
			&& ! in_array( 'onlineServices/axismundi', $ax_ct_mv_pub, true )
	);
	/*
	 * And it is that Card's alone. A contact somebody imported may have arrived with an entry called
	 * `axismundi` for reasons of its own -- exported from another site that used the same word -- and
	 * rewriting it would be this migration editing a document it did not author.
	 */
	$ax_ct_mv_theirs = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type'          => 'Card',
			'name'           => array( 'full' => 'Somebody else' ),
			'onlineServices' => array( 'axismundi' => array( 'service' => 'Axismundi', 'uri' => 'https://elsewhere.test/@them' ) ),
		)
	);
	$ax_ct_mv_tkey = is_wp_error( $ax_ct_mv_theirs ) ? 0 : (int) $ax_ct_mv_theirs;
	$ax_ct_loose[] = $ax_ct_mv_tkey;
	/*
	 * A language that says something about the account moves with it. A localization key is a path
	 * into the Card, so it is the same address written a third way, and a patch left behind would
	 * point at an entry that is no longer there -- which the store refuses.
	 */
	$ax_ct_mv_second = ax_ct_actor( $ax_ct_users );
	$ax_ct_mv_ssid   = (int) $ax_ct_mv_second->get_identity_id();
	$ax_ct_mv_smade  = axismundi_contacts_create_profile_card( $ax_ct_mv_ssid );
	$ax_ct_mv_scard  = is_wp_error( $ax_ct_mv_smade ) ? 0 : (int) $ax_ct_mv_smade;
	$ax_ct_loose[]   = $ax_ct_mv_scard;
	$ax_ct_mv_sdoc   = axismundi_contacts_card_document( $ax_ct_mv_scard );

	$ax_ct_mv_sdoc['onlineServices'] = array(
		'axismundi' => array( 'service' => 'Axismundi', 'user' => '@them@localhost', 'uri' => $ax_ct_mv_second->get_uri(), 'pref' => 1 ),
	);
	$ax_ct_mv_sdoc['localizations'] = array(
		'ko-KR' => array( 'onlineServices/axismundi/service' => 'AX' ),
	);
	axismundi_contacts_save_card_for_owner( $ax_ct_mv_ssid, $ax_ct_mv_sdoc, $ax_ct_mv_scard );

	axismundi_contacts_migrate_home_service_key();

	$ax_ct_mv_stheirs = axismundi_contacts_card_document( $ax_ct_mv_tkey );
	$ax_ct_mv_safter  = axismundi_contacts_card_document( $ax_ct_mv_scard );
	ax_ct_assert(
		$ax_ct_results,
		'it moves what this site wrote and nothing anybody imported, and a translation moves with it',
		// Somebody else's card, and somebody else's word for it, are exactly as they arrived.
		array( 'axismundi' ) === array_keys( (array) $ax_ct_mv_stheirs['onlineServices'] )
			&& array( 'x1' ) === array_keys( (array) $ax_ct_mv_safter['onlineServices'] )
			&& isset( $ax_ct_mv_safter['localizations']['ko-KR']['onlineServices/x1/service'] )
			&& ! isset( $ax_ct_mv_safter['localizations']['ko-KR']['onlineServices/axismundi/service'] )
	);

	/*
	 * And it is safe to run twice, and refuses to run onto an address something else already has --
	 * renaming onto an occupied key would merge two accounts into one.
	 */
	$ax_ct_mv_taken = axismundi_contacts_card_document( $ax_ct_mv_card );
	$ax_ct_mv_taken['onlineServices'] = array(
		'axismundi' => array( 'service' => 'Axismundi', 'uri' => $ax_ct_mv_actor->get_uri() ),
		'x1'        => array( 'service' => 'Mastodon', 'uri' => 'https://mastodon.test/@someone' ),
	);
	axismundi_contacts_save_card_for_owner( $ax_ct_mv_sid, $ax_ct_mv_taken, $ax_ct_mv_card );
	axismundi_contacts_migrate_home_service_key();
	$ax_ct_mv_kept = axismundi_contacts_card_document( $ax_ct_mv_card );
	ax_ct_assert(
		$ax_ct_results,
		'and it never renames onto an address something else is already using',
		array( 'axismundi', 'x1' ) === array_keys( (array) $ax_ct_mv_kept['onlineServices'] )
			&& 'Mastodon' === (string) ( $ax_ct_mv_kept['onlineServices']['x1']['service'] ?? '' )
	);
	/*
	 * What the screen shows instead of that key. An account is a service and a name on it, and the
	 * address goes underneath -- a Published checkbox beside a bare URI asks a question nobody can
	 * answer, which is what it was doing.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_os_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/card-editor.js' );
	ax_ct_assert(
		$ax_ct_results,
		'an account is edited as what it is called, who somebody is there, and where it lives without a second label',
		str_contains( $ax_ct_os_js, "__( 'Service', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_os_js, "__( 'Username', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_os_js, "withKey( entry, 'service', value )" )
			&& str_contains( $ax_ct_os_js, "withKey( entry, 'user', value )" )
			&& ! str_contains(
				substr( $ax_ct_os_js, strpos( $ax_ct_os_js, 'function OnlineService' ), strpos( $ax_ct_os_js, 'function OnlineServices' ) - strpos( $ax_ct_os_js, 'function OnlineService' ) ),
				"withKey( entry, 'label', value )"
			)
			// And the published list reads the same way rather than showing the address alone.
			&& str_contains( $ax_ct_os_js, "named.join( ' · ' )" )
			&& str_contains( $ax_ct_os_js, 'function entryLabel' )
	);
	/*
	 * Order is `pref` and nothing else. The list, the account a reader leads with and the face taken
	 * from it are one answer, so dragging a row rewrites the numbers rather than storing a second
	 * order beside them.
	 */
	$ax_ct_os_order = axismundi_contacts_ordered_services(
		array(
			'onlineServices' => array(
				'x1' => array( 'service' => 'Later', 'pref' => 3 ),
				'x2' => array( 'service' => 'Unranked' ),
				'x3' => array( 'service' => 'First', 'pref' => 1 ),
			),
		)
	);
	ax_ct_assert(
		$ax_ct_results,
		'accounts are read in the order they are preferred, and an unranked one waits behind those that said',
		array( 'x3', 'x1', 'x2' ) === array_column( $ax_ct_os_order, 'entry_id' )
			&& str_contains( $ax_ct_os_js, 'function orderedByPreference' )
			&& str_contains( $ax_ct_os_js, 'pref: index + 1' )
	);

	// -- which record this is, and who decides it ---------------------------------------------------------------

	/*
	 * The uid is what somebody holding a copy of this Card finds it by. Changing it would leave
	 * everybody who saved the first holding a record they can no longer match to this one -- two
	 * contacts for one person, and no way to tell which is current.
	 *
	 * Refused rather than quietly put back. Somebody who typed a uid into the JSON box meant it, and
	 * a save that discarded it while reporting success would leave them believing the Card carries an
	 * identity it does not.
	 */
	$ax_ct_id_actor = ax_ct_actor( $ax_ct_users );
	$ax_ct_id_sid   = (int) $ax_ct_id_actor->get_identity_id();
	$ax_ct_id_made  = axismundi_contacts_create_profile_card( $ax_ct_id_sid );
	$ax_ct_id_card  = is_wp_error( $ax_ct_id_made ) ? 0 : (int) $ax_ct_id_made;
	$ax_ct_loose[]  = $ax_ct_id_card;
	$ax_ct_id_uid   = (string) ( axismundi_contacts_get_card( $ax_ct_id_card )['uid'] ?? '' );

	$ax_ct_id_put = static function ( array $document ) use ( $ax_ct_id_card ) {
		$request = new WP_REST_Request( 'PUT', '/axismundi-contacts/v1/cards/' . $ax_ct_id_card . '/draft' );
		$request->set_param( 'id', $ax_ct_id_card );
		$request->set_param( 'revision', (int) ( axismundi_contacts_get_card( $ax_ct_id_card )['revision'] ?? 0 ) );
		$request->set_param( 'card', $document );
		return axismundi_contacts_rest_put_draft( $request );
	};
	wp_set_current_user( (int) $ax_ct_id_actor->get_local_user_id() );

	$ax_ct_id_other        = axismundi_contacts_card_document( $ax_ct_id_card );
	$ax_ct_id_other['uid'] = 'urn:uuid:00000000-0000-4000-8000-000000000000';
	$ax_ct_id_refused      = $ax_ct_id_put( $ax_ct_id_other );

	// Leaving it out is not changing it, so a caller that never mentions the uid still saves.
	$ax_ct_id_silent = axismundi_contacts_card_document( $ax_ct_id_card );
	unset( $ax_ct_id_silent['uid'] );
	$ax_ct_id_kept = $ax_ct_id_put( $ax_ct_id_silent );
	ax_ct_assert(
		$ax_ct_results,
		'a card keeps the identity it was published under, and a request that never mentions it changes nothing',
		is_wp_error( $ax_ct_id_refused )
			&& 'ax_contacts_draft_uid' === $ax_ct_id_refused->get_error_code()
			&& ! is_wp_error( $ax_ct_id_kept )
			&& $ax_ct_id_uid === (string) ( axismundi_contacts_get_card( $ax_ct_id_card )['uid'] ?? '' )
	);
	/*
	 * And what the Card an Actor publishes about itself describes is that Actor's answer. The radio
	 * at the top shows it decided; the JSON box below could still say otherwise, and this is where
	 * that is answered -- a Person's card claiming to be an organisation would say one thing to a
	 * reader and the Actor document another, both served from this site.
	 */
	$ax_ct_id_wrong         = axismundi_contacts_card_document( $ax_ct_id_card );
	$ax_ct_id_wrong['kind'] = 'org';
	$ax_ct_id_kind          = $ax_ct_id_put( $ax_ct_id_wrong );
	ax_ct_assert(
		$ax_ct_results,
		'and the card an Actor publishes cannot be told it describes something the Actor is not',
		is_wp_error( $ax_ct_id_kind )
			&& 'ax_contacts_draft_kind' === $ax_ct_id_kind->get_error_code()
			&& 'individual' === (string) ( axismundi_contacts_card_document( $ax_ct_id_card )['kind'] ?? '' )
	);
	wp_set_current_user( (int) $ax_ct_owner->get_local_user_id() );
	/*
	 * Which is why the JSON box is one fold away rather than a second screen beside the first. It is
	 * how to reach a property this editor has no field for yet; it is not the way to answer questions
	 * the fields above already ask, and a section competing with them for the same answers is a
	 * screen inviting somebody to disagree with themselves.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_id_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/card-editor.js' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_id_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/fields.css' );
	ax_ct_assert(
		$ax_ct_results,
		'the json is an escape hatch one fold away, and which record this is has a field of its own',
		str_contains( $ax_ct_id_js, "__( 'Advanced JSON', 'axismundi-contacts' )" )
			&& ! str_contains( $ax_ct_id_js, "__( 'The card itself', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_id_js, 'ax-ce-json-section' )
			&& str_contains( $ax_ct_id_js, 'function Identity' )
			&& str_contains( $ax_ct_id_js, "__( 'Unique identifier', 'axismundi-contacts' )" )
	);
	/*
	 * And it is read at the size the admin around it is read at. Material's 12px is calibrated for a
	 * phone held at arm's length; here a label smaller than the page it sits on reads as an
	 * afterthought rather than as the name of the field somebody is typing in.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a label and what it explains are read at the size everything around them is read at',
		str_contains( $ax_ct_id_css, '--ax-field-label-size-floated: 13px;' )
			&& str_contains( $ax_ct_id_css, '--ax-field-support-size: 13px;' )
			&& ! str_contains( $ax_ct_id_css, 'font-size: 12px;' )
	);

	/*
	 * A property somebody opened and did not fill in. JSON's `{}` and `[]` both arrive as an empty PHP
	 * array and nothing afterwards tells them apart, so a validator reading an empty map as a list
	 * refuses `"emails": {}` -- and with it every screen that ticks a box before there is anything to
	 * put behind it. The store drops empties on the way in, which is the right place to deal with a
	 * document that says the same as one that never mentioned the property at all.
	 */
	$ax_ct_mt_card = array(
		'@type'  => 'Card',
		'name'   => array(
			'full'       => 'Opened and not filled in',
			'components' => array( array( 'kind' => 'given', 'value' => 'Opened' ) ),
			// Ticked, and nothing typed behind it yet.
			'sortAs'     => array(),
		),
		'emails' => array(),
	);
	$ax_ct_mt_ok   = axismundi_contacts_validate_card_values( $ax_ct_mt_card );
	$ax_ct_mt_id   = axismundi_contacts_save_card( $ax_ct_book_id, $ax_ct_mt_card );
	$ax_ct_mt_key  = is_wp_error( $ax_ct_mt_id ) ? 0 : (int) $ax_ct_mt_id;
	$ax_ct_loose[] = $ax_ct_mt_key;
	$ax_ct_mt_doc  = axismundi_contacts_card_document( $ax_ct_mt_key );
	ax_ct_assert(
		$ax_ct_results,
		'a property with nothing in it yet is stored as no property, rather than refused at the door',
		true === $ax_ct_mt_ok
			&& $ax_ct_mt_key > 0
			&& ! array_key_exists( 'emails', $ax_ct_mt_doc )
			&& ! array_key_exists( 'sortAs', (array) $ax_ct_mt_doc['name'] )
	);

	// -- how a name is said ------------------------------------------------------------------------------------

	/*
	 * A pronunciation belongs to the part it is a pronunciation of. `\xea\xb9\x80` and `/kim/` are one thing
	 * written two ways, and a component of its own for the sound would be a second name to keep in
	 * step with the first. What system or script those sounds are written in belongs to the name,
	 * because it is the same answer for all of them.
	 */
	$ax_ct_ph_card = array(
		'@type' => 'Card',
		'name'  => array(
			'components'       => array(
				array( 'kind' => 'surname', 'value' => "\xea\xb9\x80", 'phonetic' => '/kim/' ),
				array( 'kind' => 'given', 'value' => "\xec\xa7\x80\xec\x9a\xb4", 'phonetic' => '/t\xc9\x95i.un/' ),
			),
			'phoneticSystem'   => 'ipa',
			'defaultSeparator' => '',
			'isOrdered'        => true,
			'full'             => "\xea\xb9\x80\xec\xa7\x80\xec\x9a\xb4",
		),
	);
	$ax_ct_ph_id   = axismundi_contacts_save_card( $ax_ct_book_id, $ax_ct_ph_card );
	$ax_ct_ph_key  = is_wp_error( $ax_ct_ph_id ) ? 0 : (int) $ax_ct_ph_id;
	$ax_ct_loose[] = $ax_ct_ph_key;
	$ax_ct_ph_doc  = axismundi_contacts_card_document( $ax_ct_ph_key );
	$ax_ct_ph_part = array_keys( (array) ( $ax_ct_ph_doc['name']['components'][0] ?? array() ) );
	ax_ct_assert(
		$ax_ct_results,
		'how a part of a name is said is stored on that part, next to what it says',
		'/kim/' === (string) ( $ax_ct_ph_doc['name']['components'][0]['phonetic'] ?? '' )
			&& 'ipa' === (string) ( $ax_ct_ph_doc['name']['phoneticSystem'] ?? '' )
			&& array( 'kind', 'value', 'phonetic' ) === $ax_ct_ph_part
	);
	/*
	 * And sounds in an unstated alphabet are sounds nobody can read. `Jin` is Pinyin, the same letters
	 * are something else in IPA, and the standard requires the name to say which -- so a pronunciation
	 * with neither system nor script is refused rather than stored for somebody to guess at.
	 */
	$ax_ct_ph_mute = $ax_ct_ph_card;
	unset( $ax_ct_ph_mute['name']['phoneticSystem'] );
	$ax_ct_ph_alone = axismundi_contacts_validate_card_values( $ax_ct_ph_mute );
	// A script alone answers it, which is what a card romanising a name has.
	$ax_ct_ph_mute['name']['phoneticScript'] = 'Latn';
	$ax_ct_ph_script = axismundi_contacts_validate_card_values( $ax_ct_ph_mute );
	ax_ct_assert(
		$ax_ct_results,
		'a pronunciation says nothing until the name says what it is written in, and either answer does',
		is_wp_error( $ax_ct_ph_alone )
			&& str_contains( (string) $ax_ct_ph_alone->get_error_message(), 'phonetic' )
			&& true === $ax_ct_ph_script
	);
	/*
	 * The other way of writing the same name is a language patching the same paths. Nothing new is
	 * invented for it: `name/components/0/phonetic` is where the sound lives in the base card, so it
	 * is where a language says its own.
	 */
	$ax_ct_ph_local = axismundi_contacts_card_document( $ax_ct_ph_key );
	$ax_ct_ph_local['localizations'] = array(
		'zh-Hant-TW' => array(
			'name/phoneticSystem'          => 'piny',
			'name/phoneticScript'          => 'Latn',
			'name/components/0/value'      => "\xe9\x87\x91",
			'name/components/0/phonetic'   => "J\xc4\xabn",
		),
	);
	$ax_ct_ph_saved = axismundi_contacts_save_card_for_owner( $ax_ct_owner_id, $ax_ct_ph_local, $ax_ct_ph_key );
	$ax_ct_ph_back  = axismundi_contacts_card_document( $ax_ct_ph_key );
	ax_ct_assert(
		$ax_ct_results,
		'another language says how it is written by patching where it was written, inventing nothing',
		! is_wp_error( $ax_ct_ph_saved )
			&& "J\xc4\xabn" === (string) ( $ax_ct_ph_back['localizations']['zh-Hant-TW']['name/components/0/phonetic'] ?? '' )
			&& 'piny' === (string) ( $ax_ct_ph_back['localizations']['zh-Hant-TW']['name/phoneticSystem'] ?? '' )
			// And the base card still says what it said.
			&& 'ipa' === (string) ( $ax_ct_ph_back['name']['phoneticSystem'] ?? '' )
	);
	/*
	 * A name with no parts has nothing to file by, which the standard states outright and the question
	 * answers by itself: a sort key names a part, and there are none.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a name with no parts is not filed by one',
		is_wp_error( axismundi_contacts_validate_card_values( array( 'name' => array( 'full' => 'Only written out', 'sortAs' => array( 'surname' => 'Kim' ) ) ) ) )
	);
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_ph_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/card-editor.js' );
	ax_ct_assert(
		$ax_ct_results,
		'the screen asks how a part is said beside it, and what that is written in once for the name',
		str_contains( $ax_ct_ph_js, "props.onChange( props.row, 'phonetic', value )" )
			&& str_contains( $ax_ct_ph_js, "var PHONETIC_SYSTEMS = [ 'ipa', 'jyut', 'piny' ];" )
			&& str_contains( $ax_ct_ph_js, "withKey( name, 'phoneticSystem', value )" )
			&& str_contains( $ax_ct_ph_js, "withKey( name, 'phoneticScript', value )" )
			// Asked as soon as there is a pronunciation to read, and not before.
			&& str_contains( $ax_ct_ph_js, 'return part && part.phonetic && String( part.phonetic ).trim();' )
	);

	/*
	 * The document beside the fields, which is one draft seen twice rather than two editors of one
	 * card. There is exactly one JSON box either way: folded under the fields, or standing beside
	 * them -- two of them open at once would be two places to type the same property into, and the
	 * question of which one wins is a question nobody should have to answer.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_sp_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/card-editor.js' );
	ax_ct_assert(
		$ax_ct_results,
		'the document stands beside the fields or folds under them, and is one draft either way',
		str_contains( $ax_ct_sp_js, 'beside ? null : el( AdvancedJson,' )
			&& str_contains( $ax_ct_sp_js, 'beside ? el( AdvancedJson,' )
			&& str_contains( $ax_ct_sp_js, "{ className: 'ax-ce-json-pane' }," )
			// Both write through `onJson`, which is the one way anything reaches the draft.
			&& 2 === substr_count( $ax_ct_sp_js, 'onChange: onJson' )
	);
	/*
	 * Where the split sits is somebody's, and stays theirs. Useful while the fields are being built --
	 * type on the left, watch what it writes on the right -- and noise for somebody writing down a
	 * phone number, so it is remembered per person rather than decided for everybody.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'where the split sits is remembered for whoever moved it, rather than decided for everybody',
		str_contains( $ax_ct_sp_js, "var SPLIT_KEY = 'axismundiContactsSplit';" )
			&& str_contains( $ax_ct_sp_js, 'var SPLIT_DEFAULT = 40;' )
			&& str_contains( $ax_ct_sp_js, 'window.localStorage.setItem( SPLIT_KEY, String( Math.round( next ) ) );' )
			&& str_contains( $ax_ct_sp_js, 'Math.min( 80, Math.max( 20, value ) )' )
	);
	/*
	 * And it is moved with the arrow keys as well as dragged. A divider that can only be dragged is a
	 * divider some people cannot move at all.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'the handle between the two is something to drag and something to press a key on',
		str_contains( $ax_ct_sp_js, "role: 'separator'" )
			&& str_contains( $ax_ct_sp_js, "'aria-valuenow': Math.round( props.value )" )
			&& str_contains( $ax_ct_sp_js, "if ( 'ArrowLeft' === event.key )" )
			&& str_contains( $ax_ct_sp_js, 'setPointerCapture( event.pointerId )' )
	);

	/*
	 * What a card is about is drawn beside its name, and follows what the card says it is. An icon of
	 * a person on a card describing an office would be the screen saying something the card does not,
	 * and the radio at the top is exactly where somebody changes their mind about that.
	 *
	 * A kind nothing here recognises gets the address-book mark rather than a guess: somebody else's
	 * vendor value is a real answer, and drawing a person for it would be inventing one.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_ic_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/card-editor.js' );
	ax_ct_assert(
		$ax_ct_results,
		'what a card is about is drawn beside its name, and changes when the card does',
		str_contains( $ax_ct_ic_js, 'var KIND_ICONS = {' )
			&& str_contains( $ax_ct_ic_js, "individual: 'person'," )
			&& str_contains( $ax_ct_ic_js, "org: 'domain'," )
			&& str_contains( $ax_ct_ic_js, "location: 'location-on'," )
			&& str_contains( $ax_ct_ic_js, "device: 'devices'" )
			&& str_contains( $ax_ct_ic_js, "return KIND_ICONS[ kind || 'individual' ] || 'contacts';" )
			&& str_contains( $ax_ct_ic_js, 'icon: kindIcon( props.kind )' )
	);
	/*
	 * And every icon a screen names is one the registry holds. A mark that resolves to nothing is a
	 * section with a gap where its subject should be, which nobody notices until it ships.
	 */
	$ax_ct_ic_named = array( 'person', 'domain', 'group', 'location-on', 'apps', 'devices', 'mail', 'call', 'alternate-email', 'link', 'image', 'language', 'language-international', 'notes', 'keyboard-arrow-down' );
	$ax_ct_ic_have  = array_keys( axismundi_contacts_icons() );
	$ax_ct_ic_files = true;
	foreach ( $ax_ct_ic_named as $ax_ct_ic_one ) {
		if ( ! in_array( $ax_ct_ic_one, $ax_ct_ic_have, true ) || ! is_readable( dirname( __DIR__ ) . '/assets/icons/' . $ax_ct_ic_one . '.svg' ) ) {
			$ax_ct_ic_files = false;
		}
	}
	ax_ct_assert(
		$ax_ct_results,
		'every mark a section draws is one the registry holds and one that is on disk',
		$ax_ct_ic_files
	);
	/*
	 * Drawn once beside the stack rather than on every row. Six rows of email addresses do not each
	 * need telling that they are email addresses, and an icon repeated down a column is a column of
	 * noise.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a section says what it is about once, beside the stack rather than down it',
		str_contains( $ax_ct_ic_js, 'function Section( props )' )
			&& str_contains( $ax_ct_ic_js, "className: 'ax-ce-section__mark'" )
			// A simple collection draws its own mark and says its own name, whatever else it is passed.
			&& str_contains( $ax_ct_ic_js, 'icon: props.field.icon,' )
			&& str_contains( $ax_ct_ic_js, 'label: props.field.label,' )
			&& str_contains( $ax_ct_ic_js, "{ icon: 'alternate-email', label:" )
			// The heading is still there for anybody reading the page rather than looking at it.
			&& str_contains( $ax_ct_ic_js, "{ className: 'screen-reader-text' }, props.label )" )
	);

	/*
	 * What a card is written in and what a translation of it is written in are different shapes of the
	 * same standard. `ko-KR` is Korean as written in Korea, which is what a card says; `ko-Latn` is
	 * Korean in Latin letters, which is what a translation of it usually is. Offering the script forms
	 * in both places would put the wrong answer in front of whoever is filling in either.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_lg_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/card-editor.js' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_lg_php = (string) file_get_contents( dirname( __DIR__ ) . '/includes/card-editor.php' );
	ax_ct_assert(
		$ax_ct_results,
		'the languages a card offers are the ones this site offers everywhere, plus the ones it uses',
		// One list, from the surface every other authoring screen here reads.
		str_contains( $ax_ct_lg_js, 'var LANGUAGES = config.languages || [];' )
			&& ! str_contains( $ax_ct_lg_js, 'COMMON_LANGUAGES' )
			&& ! str_contains( $ax_ct_lg_js, 'SCRIPT_LANGUAGES' )
			&& str_contains( $ax_ct_lg_php, 'axismundi_actors_profile_language_options' )
			// Whatever this Card already says is added to it, listed or not.
			&& str_contains( $ax_ct_lg_php, "axismundi_contacts_card_languages( \$draft['card'] )" )
	);
	/*
	 * And a tag is written down the way the Actor registry writes one. Three plugins each normalising
	 * their own way is three spellings of Korean-in-Latin-letters in one database, which nothing
	 * downstream can match up.
	 */
	$ax_ct_lg_id   = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type'              => 'Card',
			'language'           => 'ko_kr',
			'name'               => array( 'full' => 'Written any old way' ),
			'preferredLanguages' => array( 'l1' => array( 'language' => 'EN-gb', 'pref' => 1 ) ),
			'localizations'      => array( 'ko-latn' => array( 'name/full' => 'Kim Jiwoon' ) ),
		)
	);
	$ax_ct_lg_key  = is_wp_error( $ax_ct_lg_id ) ? 0 : (int) $ax_ct_lg_id;
	$ax_ct_loose[] = $ax_ct_lg_key;
	$ax_ct_lg_doc  = axismundi_contacts_card_document( $ax_ct_lg_key );
	ax_ct_assert(
		$ax_ct_results,
		'a language tag is written the way this site writes one, wherever a card names a language',
		'ko-KR' === (string) ( $ax_ct_lg_doc['language'] ?? '' )
			&& 'en-GB' === (string) ( $ax_ct_lg_doc['preferredLanguages']['l1']['language'] ?? '' )
			&& array( 'ko-Latn' ) === array_keys( (array) ( $ax_ct_lg_doc['localizations'] ?? array() ) )
	);
	/*
	 * And a field inside a section that already says what it is does not say it again. The label is
	 * still written down -- a field a screen reader cannot name is a field only some people can use --
	 * it is just not read out twice to everybody else.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_lg_fields = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/fields.js' );
	ax_ct_assert(
		$ax_ct_results,
		'a field under a heading that names it is still named, and does not say it twice',
		str_contains( $ax_ct_lg_js, 'hideLabel: true,' )
			&& str_contains( $ax_ct_lg_fields, "props.hideLabel ? ' screen-reader-text' : ''" )
			// Through the combobox as well, which is a text field wearing a list.
			&& str_contains( $ax_ct_lg_fields, 'hideLabel: props.hideLabel,' )
	);
	/*
	 * Asking for a pronunciation asks what it is written in, rather than waiting until one is typed.
	 * The standard requires the system or the script the moment any part carries a sound, so a screen
	 * that waited for the value would let somebody fill in a name and then refuse to save it.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'saying you are writing pronunciations down asks what they are written in, before one is',
		str_contains( $ax_ct_lg_js, 'phonetic && expanded' )
			&& str_contains( $ax_ct_lg_js, "options: PHONETIC_SYSTEMS," )
			&& str_contains( $ax_ct_lg_js, "options: PHONETIC_SCRIPTS," )
	);

	/*
	 * A field somebody has clicked back into still shows what they chose. It held `ko-KR`, and
	 * clicking it emptied the box while the card still said `ko-KR` -- the field was showing the
	 * search, and the search starts empty. Now the search is a separate thing that does not exist
	 * until somebody types, so opening the list shows the answer and offers all of them.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_cb_js = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/fields.js' );
	ax_ct_assert(
		$ax_ct_results,
		'a chosen value is still in the field when somebody clicks back into it, with everything on offer',
		// Nothing typed is `null`, which is not the same as having typed nothing.
		str_contains( $ax_ct_cb_js, 'var [ query, setQuery ] = wp.element.useState( null );' )
			&& str_contains( $ax_ct_cb_js, 'var typing = null !== query;' )
			// And what it shows is what the chosen option is called, rather than the value behind it.
			&& str_contains( $ax_ct_cb_js, "value: typing ? query : ( ( chosen && chosen.label ) || props.value || '' )," )
			&& str_contains( $ax_ct_cb_js, 'return option.value === props.value;' )
			// So the list is narrowed by typing rather than by having a value already.
			&& str_contains( $ax_ct_cb_js, '! typing || ! query ||' )
			&& str_contains( $ax_ct_cb_js, 'setQuery( null );' )
	);
	/*
	 * And coming back to the field does not close the list that coming back opened: the blur that
	 * fired on the way out is waiting to close it, and focusing again cancels that.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'leaving and returning to the field leaves it open, rather than closing what returning opened',
		str_contains( $ax_ct_cb_js, 'var closing = useRef( null );' )
			&& str_contains( $ax_ct_cb_js, 'function stopClosing()' )
			&& str_contains( $ax_ct_cb_js, 'closing.current = window.setTimeout(' )
			&& str_contains( $ax_ct_cb_js, "onFocus: function () {\n\t\t\t\t\tstopClosing();" )
	);
	/*
	 * Two parts of the same kind are two rows. A name may carry two middle names -- RFC 9553's own
	 * example does -- and a row named after its kind alone means the second is the first as far as
	 * the screen is concerned: editing one edits the other, and deleting one deletes the other.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_cb_editor = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/card-editor.js' );
	ax_ct_assert(
		$ax_ct_results,
		'two parts of one kind are two rows, each writing to the part it stands for',
		// Named by which one of its kind it is, and writing by where that part sits.
		str_contains( $ax_ct_cb_editor, "key: part.kind + '#' + ( seen[ part.kind ] - 1 )," )
			&& str_contains( $ax_ct_cb_editor, 'function writeSlot( row, key, value )' )
			&& str_contains( $ax_ct_cb_editor, 'var at = row.index;' )
			&& str_contains( $ax_ct_cb_editor, 'key: row.key,' )
			// And never by looking up the first of that kind, which is what made them one row.
			&& ! str_contains( $ax_ct_cb_editor, 'var at = slotIndex( components, kind );' )
	);
	/*
	 * Adding opens a line rather than writing a part. A card does not collect empty parts from people
	 * who clicked a button and thought better of it -- and the line keeps its name when it becomes a
	 * part, so the field somebody is typing into is not replaced under them at the first keystroke.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'adding a part opens somewhere to type, and typing there is what writes the part down',
		str_contains( $ax_ct_cb_editor, 'var [ pending, setPending ] = useState( [] );' )
			&& str_contains( $ax_ct_cb_editor, "setPending( pending.concat( [ { id: 'row-' + rows, kind: kind } ] ) );" )
			&& str_contains( $ax_ct_cb_editor, 'if ( row.pendingId ) {' )
			&& str_contains( $ax_ct_cb_editor, "rows.push( {\n\t\t\t\tkey: row.kind + '#' + ( seen[ row.kind ] - 1 )," )
	);
	/*
	 * And a separator is added where it goes. Reached from a list of kinds it had to be put somewhere
	 * first -- which was the front of the name, because a separator is not one of the lines and so
	 * sorted before all of them -- and dragged into place afterwards.
	 */
	/*
	 * And a part with no line of its own joins the end rather than the front. A separator is not one
	 * of the six, so a rule that sorted by which line a kind belongs to put it before all of them --
	 * at the head of the name, to be dragged back afterwards.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a part with no line of its own joins the end of the name, not the head of it',
		str_contains( $ax_ct_cb_editor, 'if ( -1 === rank ) {' )
			&& str_contains( $ax_ct_cb_editor, 'return list.length;' )
			// And it is removable, along with anything past the first of its kind.
			&& str_contains( $ax_ct_cb_editor, '-1 === NAME_SLOTS.indexOf( props.kind )' )
			&& str_contains( $ax_ct_cb_editor, 'props.row.occurrence > 0' )
	);

	/*
	 * A list says something when nothing in it matches, and saying it needs the thing that translates
	 * it. That string is drawn on exactly one render -- the one where somebody has typed a tag the
	 * list does not have, which is how `ko-Hani` is typed -- so a missing function took the editor
	 * down at the moment it was most in use. The dependency is declared, and the file does not fall
	 * over when it is not there.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'the fields can say that nothing matches, which is the one render nobody had reached',
		str_contains( $ax_ct_cb_js, 'var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function ( text ) {' )
			&& str_contains( $ax_ct_lg_php, "array( 'wp-element', 'wp-i18n' )," )
			&& str_contains( $ax_ct_lg_php, "wp_set_script_translations( 'axismundi-contacts-fields'" )
	);

	/*
	 * A language that gives the whole name gets the whole name editor. `Kim Jiwoon` in English is a
	 * name -- parts, an order, a way of being said -- and the screen was handing somebody a read-only
	 * box holding `{"components":[{"kind":"given",...}]}` and calling that a translation. Nobody
	 * writes that. It is the same editor as the one above because it is the same question.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a language that gives the whole name is given the same editor the name has',
		str_contains( $ax_ct_cb_editor, "if ( 'name' === path && isObject ) {" )
			&& str_contains( $ax_ct_cb_editor, "var isObject = value && 'object' === typeof value && ! Array.isArray( value );" )
			&& str_contains( $ax_ct_cb_editor, "className: 'ax-ce-localization__name'" )
			&& str_contains( $ax_ct_cb_editor, "__( 'Stop giving the name in %s', 'axismundi-contacts' )" )
	);
	/*
	 * And translating something starts from what the card already says there, rather than from an
	 * empty box: changing a name means changing one, and rebuilding the structure by hand is the
	 * serializer's job rather than the translator's.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'translating something starts from what the card says there, not from nothing',
		str_contains( $ax_ct_cb_editor, 'function valueAt( card, path )' )
			&& str_contains( $ax_ct_cb_editor, 'var from = valueAt( props.card, path );' )
			&& str_contains( $ax_ct_cb_editor, 'JSON.parse( JSON.stringify( from ) )' )
	);
	/*
	 * The store took all of this before the screen could show any of it: a patch may set a whole
	 * object, and an editor with no field for the property it names is why somebody would want to.
	 */
	$ax_ct_or_card = array(
		'@type'         => 'Card',
		'name'          => array( 'components' => array( array( 'kind' => 'given', 'value' => 'Jiwoon' ) ), 'isOrdered' => true ),
		'organizations' => array( 'o1' => array( 'name' => 'Axismundi', 'units' => array( array( 'name' => 'Contacts' ) ) ) ),
		'titles'        => array( 't1' => array( 'name' => 'Site Owner', 'kind' => 'title', 'organizationId' => 'o1' ) ),
		'localizations' => array(
			'ko-KR' => array(
				'name'      => array( 'components' => array( array( 'kind' => 'given', 'value' => "\xec\xa7\x80\xec\x9a\xb4" ) ), 'isOrdered' => true ),
				'titles/t1' => array( 'name' => "\xec\x82\xac\xec\x9d\xb4\xed\x8a\xb8" ),
			),
		),
	);
	$ax_ct_or_id   = axismundi_contacts_save_card( $ax_ct_book_id, $ax_ct_or_card );
	$ax_ct_or_key  = is_wp_error( $ax_ct_or_id ) ? 0 : (int) $ax_ct_or_id;
	$ax_ct_loose[] = $ax_ct_or_key;
	$ax_ct_or_back = axismundi_contacts_card_document( $ax_ct_or_key );
	ax_ct_assert(
		$ax_ct_results,
		'a language may give a whole property, and what it gives comes back the way it was given',
		'Axismundi' === (string) ( $ax_ct_or_back['organizations']['o1']['name'] ?? '' )
			&& 'Contacts' === (string) ( $ax_ct_or_back['organizations']['o1']['units'][0]['name'] ?? '' )
			// A title says which of them it belongs to rather than repeating its name.
			&& 'o1' === (string) ( $ax_ct_or_back['titles']['t1']['organizationId'] ?? '' )
			&& "\xec\xa7\x80\xec\x9a\xb4" === (string) ( $ax_ct_or_back['localizations']['ko-KR']['name']['components'][0]['value'] ?? '' )
			&& "\xec\x82\xac\xec\x9d\xb4\xed\x8a\xb8" === (string) ( $ax_ct_or_back['localizations']['ko-KR']['titles/t1']['name'] ?? '' )
	);
	$ax_ct_metadata_at   = strpos( $ax_ct_cb_editor, "__( 'Metadata', 'axismundi-contacts' )" );
	$ax_ct_name_group_at = strpos( $ax_ct_cb_editor, "__( 'Name and organization', 'axismundi-contacts' )" );
	$ax_ct_contact_at    = strpos( $ax_ct_cb_editor, "__( 'Contact properties', 'axismundi-contacts' )" );
	$ax_ct_addresses_at  = false !== $ax_ct_contact_at ? strpos( $ax_ct_cb_editor, 'el( Addresses', $ax_ct_contact_at ) : false;
	$ax_ct_metadata_ui   = false !== $ax_ct_metadata_at && false !== $ax_ct_name_group_at
		? substr( $ax_ct_cb_editor, $ax_ct_metadata_at, $ax_ct_name_group_at - $ax_ct_metadata_at )
		: '';
	$ax_ct_name_ui       = false !== $ax_ct_name_group_at && false !== $ax_ct_contact_at
		? substr( $ax_ct_cb_editor, $ax_ct_name_group_at, $ax_ct_contact_at - $ax_ct_name_group_at )
		: '';
	$ax_ct_contact_ui    = false !== $ax_ct_contact_at && false !== $ax_ct_addresses_at
		? substr( $ax_ct_cb_editor, $ax_ct_contact_at, $ax_ct_addresses_at - $ax_ct_contact_at )
		: '';
	// And each editable metadata property currently has somewhere to be typed.
	ax_ct_assert(
		$ax_ct_results,
		'metadata properties share the top cluster without inventing empty editors',
		str_contains( $ax_ct_cb_editor, "__( 'Metadata', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_cb_editor, 'function KindField( props )' )
			&& str_contains( $ax_ct_cb_editor, 'function CardLanguage( props )' )
			&& str_contains( $ax_ct_cb_editor, 'function Identity( props )' )
			&& 3 === substr_count( $ax_ct_metadata_ui, "headingTag: 'h3'" ),
		$ax_ct_cb_editor
	);

	ax_ct_assert(
		$ax_ct_results,
		'name and organization properties keep the RFC cluster and use fields rather than json',
		str_contains( $ax_ct_cb_editor, 'function Organizations( props )' )
			&& str_contains( $ax_ct_cb_editor, 'function Titles( props )' )
			&& str_contains( $ax_ct_cb_editor, "__( 'Name and organization', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_cb_editor, "headingTag: 'h3'" )
			&& 3 === substr_count( $ax_ct_name_ui, 'showHeading: true' )
			&& str_contains( $ax_ct_cb_editor, "__( 'Department', 'axismundi-contacts' )" )
			// Departments are a path beneath one organization, never flex siblings of its name field.
			&& str_contains( $ax_ct_cb_editor, "{ className: 'ax-ce-org__units' }" )
			// Organization and department sort keys look alike in JSON; their controls must name their owner.
			&& str_contains( $ax_ct_cb_editor, "__( 'More about this organization', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_cb_editor, "__( 'Sort this organization as', 'axismundi-contacts' )" )
			// OrgUnit.sortAs is valid but only compares departments at one hierarchy level; the form preserves it
			// from imports without presenting it beside the organization-wide sort key.
			&& ! str_contains( $ax_ct_cb_editor, "__( 'Sort this department as', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_cb_editor, "__( 'Use this organization for', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_cb_editor, "__( 'Job title', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_cb_editor, "__( 'Role', 'axismundi-contacts' )" )
			// Which employer a title belongs to names an entry above rather than repeating it.
			&& str_contains( $ax_ct_cb_editor, "write( row, 'organizationId', value )" )
			// A row becomes an organization with either answer RFC 9553 permits, not only a name.
			&& str_contains( $ax_ct_cb_editor, 'function organizationHasContent( entry )' )
			&& str_contains( $ax_ct_cb_editor, 'if ( ! organizationHasContent( made ) )' )
	);

	/*
	 * Which of several comes first is a question the standard already answers. `pref` is what an entry
	 * says about it -- 1 is the most preferred -- and an entry that says nothing has no preference
	 * rather than the lowest one, so it is written after everything that does.
	 *
	 * The stored form used to order entries by their ids, because an id is not a property name and the
	 * key ordering had nothing else to do with it. So a Card could say an address was the preferred one
	 * and have it written third, and the fields on the left read in one order while the JSON on the
	 * right read in another. One comparator now decides both.
	 *
	 * A collection nobody has expressed a preference about is written exactly as it was before: every
	 * entry ties, and ties go to the id. That also keeps the canonical form independent of the order
	 * the members arrived in, which is what makes it canonical.
	 */
	$ax_ct_pref_card = axismundi_contacts_canonical_card(
		array(
			'@type'  => 'Card',
			'name'   => array( 'full' => 'Preference' ),
			'emails' => array(
				'eml-c' => array( 'address' => 'c@example.test' ),
				'eml-a' => array( 'address' => 'a@example.test', 'pref' => 2 ),
				'eml-b' => array( 'address' => 'b@example.test', 'pref' => 1 ),
			),
			'phones' => array(
				'tel-z' => array( 'number' => 'tel:+8210000001' ),
				'tel-a' => array( 'number' => 'tel:+8210000002' ),
			),
		)
	);
	// The same Card with its members handed over in a different order canonicalises to the same thing.
	$ax_ct_pref_again = axismundi_contacts_canonical_card(
		array(
			'@type'  => 'Card',
			'name'   => array( 'full' => 'Preference' ),
			'emails' => array(
				'eml-b' => array( 'address' => 'b@example.test', 'pref' => 1 ),
				'eml-c' => array( 'address' => 'c@example.test' ),
				'eml-a' => array( 'address' => 'a@example.test', 'pref' => 2 ),
			),
			'phones' => array(
				'tel-a' => array( 'number' => 'tel:+8210000002' ),
				'tel-z' => array( 'number' => 'tel:+8210000001' ),
			),
		)
	);
	ax_ct_assert(
		$ax_ct_results,
		'a collection is written most preferred first, and one with no preference is written as it was',
		array( 'eml-b', 'eml-a', 'eml-c' ) === array_keys( (array) $ax_ct_pref_card['emails'] )
			// Nobody said anything about the numbers, so nothing about them moved.
			&& array( 'tel-a', 'tel-z' ) === array_keys( (array) $ax_ct_pref_card['phones'] )
			// And the order the members arrived in decides nothing.
			&& wp_json_encode( $ax_ct_pref_card ) === wp_json_encode( $ax_ct_pref_again )
			// One comparator, and the screen reads by it too rather than only for accounts.
			&& str_contains( $ax_ct_cb_editor, 'var rows = orderedByPreference( entries ).map( function ( id ) {' )
			&& ! str_contains( $ax_ct_cb_editor, "'onlineServices' === property ? orderedByPreference( entries ) : Object.keys( entries )" )
	);

	$ax_ct_where_at    = strpos( $ax_ct_cb_editor, "__( 'Address and location properties', 'axismundi-contacts' )" );
	$ax_ct_resource_at = strpos( $ax_ct_cb_editor, "__( 'Resource properties', 'axismundi-contacts' )" );
	$ax_ct_lang_at     = strpos( $ax_ct_cb_editor, "__( 'Multilingual properties', 'axismundi-contacts' )" );
	$ax_ct_extra_at    = strpos( $ax_ct_cb_editor, "__( 'Additional properties', 'axismundi-contacts' )" );
	$ax_ct_slice       = static function ( string $source, $from, $to ) : string {
		return false !== $from && false !== $to && $to > $from ? substr( $source, (int) $from, (int) $to - (int) $from ) : '';
	};
	$ax_ct_where_ui    = $ax_ct_slice( $ax_ct_cb_editor, $ax_ct_where_at, $ax_ct_resource_at );
	$ax_ct_resource_ui = $ax_ct_slice( $ax_ct_cb_editor, $ax_ct_resource_at, $ax_ct_lang_at );
	$ax_ct_lang_ui     = $ax_ct_slice( $ax_ct_cb_editor, $ax_ct_lang_at, $ax_ct_extra_at );
	$ax_ct_extra_ui    = substr( $ax_ct_cb_editor, false !== $ax_ct_extra_at ? (int) $ax_ct_extra_at : 0 );

	/*
	 * The rest of the Card, under the headings the standard reads it under and in that order: where
	 * somebody is, what they are reachable through, what the card says in other languages, and what
	 * is left over. Following the document rather than the shape of this code is the whole point --
	 * links, media and notes are built from one list because they are built the same way, and being
	 * built the same way is not a reason to read them under one heading.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'the rest of the card is read under the standard’s own headings, in the standard’s own order',
		false !== $ax_ct_contact_at && false !== $ax_ct_where_at
			&& $ax_ct_contact_at < $ax_ct_where_at
			&& $ax_ct_where_at < $ax_ct_resource_at
			&& $ax_ct_resource_at < $ax_ct_lang_at
			&& $ax_ct_lang_at < $ax_ct_extra_at
			// Where somebody is.
			&& str_contains( $ax_ct_where_ui, 'el( Addresses, {' )
			&& str_contains( $ax_ct_where_ui, 'showHeading: true' )
			// What they are reachable through, and not the note that is none of those.
			&& str_contains( $ax_ct_resource_ui, "entryFieldsIn( 'resource' )" )
			&& str_contains( $ax_ct_cb_editor, "{ key: 'links', prefix: 'lnk', cluster: 'resource'" )
			&& str_contains( $ax_ct_cb_editor, "{ key: 'media', prefix: 'med', cluster: 'resource'" )
			&& str_contains( $ax_ct_cb_editor, "{ key: 'notes', prefix: 'not', cluster: 'additional'" )
			// What it says in other languages.
			&& str_contains( $ax_ct_lang_ui, 'el( Localizations, {' )
			&& str_contains( $ax_ct_lang_ui, "headingTag: 'h3'" )
			// And what is left over, which the standard reads after the languages rather than before.
			&& str_contains( $ax_ct_extra_ui, "entryFieldsIn( 'additional' )" )
	);

	/*
	 * The headings descend a level at a time, including inside a translation. A cluster is a level,
	 * the section in it is a level, the language a patch is written in is a level under that, the path
	 * it patches is under that, and the editor that opens for the path is under that again. Skipping
	 * or repeating a level makes the page read, to anything navigating by headings, as one flat list
	 * of equals -- which is what this had: a translation's languages were siblings of the heading that
	 * lists them, and the name editor inside announced itself as an `h2` from three levels down.
	 *
	 * Measured on the screen as well, where a walk of every heading in the editor now reports no skips.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a translation nests one heading level at a time rather than starting again from the top',
		str_contains( $ax_ct_cb_editor, "el( 'h4', null, props.tag )," )
			&& str_contains( $ax_ct_cb_editor, "el( 'h5', null, path )," )
			&& str_contains( $ax_ct_cb_editor, "headingTag: 'h6'," )
			// Every patch says which path it is, the name included.
			&& 2 === substr_count( $ax_ct_cb_editor, "el( 'h5', null, path )," )
			// And nothing inside a translation opens at the top level again.
			&& ! str_contains( $ax_ct_cb_editor, "el( 'h3', null, props.tag )," )
	);

	/*
	 * And nothing is promised that is not there. Half of these groups hold properties this editor has
	 * no field for -- keys, directories, calendars, anniversaries, keywords, personal information --
	 * and a heading over an empty space reads as a screen that failed to load rather than one that
	 * has not been written yet. They are reached through the JSON until they have a field.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a heading is only drawn for a property somebody can actually fill in',
		! str_contains( $ax_ct_cb_editor, "__( 'Calendaring and scheduling properties', 'axismundi-contacts' )" )
			&& ! str_contains( $ax_ct_cb_editor, "__( 'Anniversaries', 'axismundi-contacts' )" )
			&& ! str_contains( $ax_ct_cb_editor, "__( 'Keywords', 'axismundi-contacts' )" )
			&& ! str_contains( $ax_ct_cb_editor, "__( 'Personal information', 'axismundi-contacts' )" )
			&& ! str_contains( $ax_ct_cb_editor, "__( 'Cryptographic keys', 'axismundi-contacts' )" )
			&& ! str_contains( $ax_ct_cb_editor, "__( 'Directories', 'axismundi-contacts' )" )
			// What they are is still a Card this store accepts and hands back unchanged.
			&& true === axismundi_contacts_validate_card_values(
				array( 'keywords' => array( 'friend' => true ), 'anniversaries' => array() )
			)
	);

	ax_ct_assert(
		$ax_ct_results,
		'contact properties share one RFC cluster in the order this editor uses them',
		str_contains( $ax_ct_cb_editor, "__( 'Contact properties', 'axismundi-contacts' )" )
			&& 4 === substr_count( $ax_ct_contact_ui, "headingTag: 'h3'" )
			&& 3 === substr_count( $ax_ct_contact_ui, 'showHeading: true' )
			&& false !== strpos( $ax_ct_contact_ui, 'el( OnlineServices' )
			&& strpos( $ax_ct_contact_ui, 'el( OnlineServices' ) < strpos( $ax_ct_contact_ui, 'el( Emails' )
			&& strpos( $ax_ct_contact_ui, 'el( Emails' ) < strpos( $ax_ct_contact_ui, 'el( Phones' )
			&& strpos( $ax_ct_contact_ui, 'el( Phones' ) < strpos( $ax_ct_contact_ui, 'el( PreferredLanguages' )
	);

	$ax_ct_units_only = array(
		'@type'         => 'Card',
		'organizations' => array(
			'o-units' => array(
				'units'    => array( array( 'name' => 'Development', 'sortAs' => 'Dev' ) ),
				'sortAs'   => 'Axismundi',
				'contexts' => array( 'work' => true ),
			),
		),
	);
	$ax_ct_units_only_id  = axismundi_contacts_save_card( $ax_ct_book_id, $ax_ct_units_only );
	$ax_ct_units_only_key = is_wp_error( $ax_ct_units_only_id ) ? 0 : (int) $ax_ct_units_only_id;
	$ax_ct_loose[]        = $ax_ct_units_only_key;
	$ax_ct_units_only_back = axismundi_contacts_card_document( $ax_ct_units_only_key );
	ax_ct_assert(
		$ax_ct_results,
		'an organization may be only a department, and keeps its sort keys and context',
		! is_wp_error( $ax_ct_units_only_id )
			&& ! isset( $ax_ct_units_only_back['organizations']['o-units']['name'] )
			&& 'Development' === (string) ( $ax_ct_units_only_back['organizations']['o-units']['units'][0]['name'] ?? '' )
			&& 'Dev' === (string) ( $ax_ct_units_only_back['organizations']['o-units']['units'][0]['sortAs'] ?? '' )
			&& 'Axismundi' === (string) ( $ax_ct_units_only_back['organizations']['o-units']['sortAs'] ?? '' )
			&& array( 'work' => true ) === ( $ax_ct_units_only_back['organizations']['o-units']['contexts'] ?? array() )
	);

	ax_ct_assert(
		$ax_ct_results,
		'an organization with neither a name nor a department is rejected',
		is_wp_error( axismundi_contacts_validate_card_values( array( '@type' => 'Card', 'organizations' => array( 'empty' => array() ) ) ) )
	);

	// -- what points at what, through an actual save ------------------------------------------------------------

	/*
	 * A card for a company carries no personal name, so the list has to read one off the organization
	 * it names. It looked under one fixed entry id -- the word one importer happened to use -- and
	 * every card written by anything else appeared in the list as nothing at all. The first one with
	 * a name, in the order the document holds them, is the answer.
	 */
	$ax_ct_rf_org  = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type'         => 'Card',
			'kind'          => 'org',
			// The ids a screen makes, which are not the id anything should look for.
			'organizations' => array( 'o1' => array( 'name' => 'Axismundi' ) ),
		)
	);
	$ax_ct_rf_key  = is_wp_error( $ax_ct_rf_org ) ? 0 : (int) $ax_ct_rf_org;
	$ax_ct_loose[] = $ax_ct_rf_key;
	ax_ct_assert(
		$ax_ct_results,
		'a card with no personal name is listed by the organization it names, whatever that entry is called',
		'Axismundi' === (string) ( axismundi_contacts_get_card( $ax_ct_rf_key )['display_name'] ?? '' )
			&& 'Axismundi' === axismundi_contacts_first_organization_name( array( 'organizations' => array( 'x' => array( 'name' => '' ), 'y' => array( 'name' => 'Axismundi' ) ) ) )
	);
	/*
	 * A title says which organization it belongs to by naming an entry rather than repeating its
	 * name. The other side of that bargain is that the entry has to be there: a title pointing at an
	 * organization the card no longer holds is a title whose employer nothing can resolve.
	 */
	$ax_ct_rf_actor = ax_ct_actor( $ax_ct_users );
	$ax_ct_rf_sid   = (int) $ax_ct_rf_actor->get_identity_id();
	$ax_ct_rf_made  = axismundi_contacts_create_profile_card( $ax_ct_rf_sid );
	$ax_ct_rf_card  = is_wp_error( $ax_ct_rf_made ) ? 0 : (int) $ax_ct_rf_made;
	$ax_ct_loose[]  = $ax_ct_rf_card;
	$ax_ct_rf_doc   = axismundi_contacts_card_document( $ax_ct_rf_card );
	$ax_ct_rf_doc['organizations'] = array( 'o1' => array( 'name' => 'Axismundi' ) );
	$ax_ct_rf_doc['titles']        = array( 't1' => array( 'name' => 'Site Owner', 'organizationId' => 'o1' ) );
	axismundi_contacts_save_card_for_owner( $ax_ct_rf_sid, $ax_ct_rf_doc, $ax_ct_rf_card );

	wp_set_current_user( (int) $ax_ct_rf_actor->get_local_user_id() );
	$ax_ct_rf_put = static function ( array $document ) use ( $ax_ct_rf_card ) {
		$request = new WP_REST_Request( 'PUT', '/axismundi-contacts/v1/cards/' . $ax_ct_rf_card . '/draft' );
		$request->set_param( 'id', $ax_ct_rf_card );
		$request->set_param( 'revision', (int) ( axismundi_contacts_get_card( $ax_ct_rf_card )['revision'] ?? 0 ) );
		$request->set_param( 'card', $document );
		return axismundi_contacts_rest_put_draft( $request );
	};

	// The organization taken away on its own, which is what a screen that only deleted one would send.
	$ax_ct_rf_alone = axismundi_contacts_card_document( $ax_ct_rf_card );
	unset( $ax_ct_rf_alone['organizations'] );
	$ax_ct_rf_orphan = $ax_ct_rf_put( $ax_ct_rf_alone );

	// And both together, which is what it sends now.
	$ax_ct_rf_both = axismundi_contacts_card_document( $ax_ct_rf_card );
	unset( $ax_ct_rf_both['organizations'] );
	unset( $ax_ct_rf_both['titles']['t1']['organizationId'] );
	$ax_ct_rf_ok = $ax_ct_rf_put( $ax_ct_rf_both );
	ax_ct_assert(
		$ax_ct_results,
		'a title cannot be left pointing at an organization the card no longer holds',
		is_wp_error( $ax_ct_rf_orphan )
			&& str_contains( (string) $ax_ct_rf_orphan->get_error_message(), 'organizationId' )
			&& ! is_wp_error( $ax_ct_rf_ok )
			&& 'Site Owner' === (string) ( axismundi_contacts_card_document( $ax_ct_rf_card )['titles']['t1']['name'] ?? '' )
	);
	/*
	 * And an organization with an empty list of parts is a property saying nothing, while one with a
	 * part that is not a list is a document the store refuses. A screen that wrote either would be a
	 * screen whose Save button fails on a card somebody filled in correctly.
	 */
	$ax_ct_rf_units = axismundi_contacts_card_document( $ax_ct_rf_card );
	$ax_ct_rf_units['organizations'] = array( 'o1' => array( 'name' => 'Axismundi', 'units' => array() ) );
	$ax_ct_rf_empty = $ax_ct_rf_put( $ax_ct_rf_units );
	$ax_ct_rf_units['organizations']['o1']['units'] = '';
	$ax_ct_rf_bad = $ax_ct_rf_put( $ax_ct_rf_units );
	wp_set_current_user( (int) $ax_ct_owner->get_local_user_id() );
	ax_ct_assert(
		$ax_ct_results,
		'the parts of an organization are a list when there are any, and nothing at all when there are not',
		! is_wp_error( $ax_ct_rf_empty )
			&& ! array_key_exists( 'units', (array) ( axismundi_contacts_card_document( $ax_ct_rf_card )['organizations']['o1'] ?? array() ) )
			&& is_wp_error( $ax_ct_rf_bad )
	);
	/*
	 * And the screen asks before it sends any of that: what points at an organization is named, and
	 * somebody decides, rather than finding out from a save that failed.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'removing an organization names what points at it first, and lets go of both together',
		str_contains( $ax_ct_cb_editor, 'function dependsOnOrganization( card, id )' )
			&& str_contains( $ax_ct_cb_editor, "patchesUnder( ( card || {} ).localizations, 'organizations/' + id )" )
			&& str_contains( $ax_ct_cb_editor, "__( 'Remove them and the organization', 'axismundi-contacts' )" )
			// The title stays; what it said about where it was held does not.
			&& str_contains( $ax_ct_cb_editor, "titles[ key ] = withKey( titles[ key ], 'organizationId', '' );" )
	);
	/*
	 * The order a stored Card is written in is the server's, and the screen is handed it rather than
	 * writing it down again. A property assigned to a JavaScript object lands at the end of it, so a
	 * card that gained a `language` showed it below `localizations` until a save moved it -- and the
	 * JSON somebody is reading while they type is the one that looked wrong.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'the draft is written in the order a stored card is, before it is saved rather than after',
		str_contains( $ax_ct_lg_php, "'order'               => axismundi_contacts_canonical_order()," )
			&& str_contains( $ax_ct_cb_editor, 'function ordered( card )' )
			&& str_contains( $ax_ct_cb_editor, '( config.order || [] ).forEach(' )
			// Except the box somebody is typing in, where tidying would move the cursor.
			&& str_contains( $ax_ct_cb_editor, '// Not reordered: this is somebody typing' )
	);

	/*
	 * A title is a title because it has a name -- the standard requires one -- so a row where
	 * somebody has picked the kind and the employer and not yet typed the name is a row and not a
	 * title. Written down at that point it is a document the store refuses, which is a Save button
	 * failing on a card somebody was part-way through filling in.
	 */
	wp_set_current_user( (int) $ax_ct_rf_actor->get_local_user_id() );
	$ax_ct_tn_half = axismundi_contacts_card_document( $ax_ct_rf_card );
	$ax_ct_tn_half['titles']['t2'] = array( 'kind' => 'role' );
	$ax_ct_tn_refused = $ax_ct_rf_put( $ax_ct_tn_half );
	wp_set_current_user( (int) $ax_ct_owner->get_local_user_id() );
	ax_ct_assert(
		$ax_ct_results,
		'a title with a kind and no name is not a title, and the screen holds it rather than writing it',
		is_wp_error( $ax_ct_tn_refused )
			// Held on the row until the name arrives, with what was answered already on it.
			&& str_contains( $ax_ct_cb_editor, "// Still only a row: what has been answered waits here for the name." )
			&& str_contains( $ax_ct_cb_editor, 'var held = withKey( row.entry, key, value );' )
			&& str_contains( $ax_ct_cb_editor, "return each.id === row.pending ? { id: each.id, entry: held } : each;" )
	);
	// And a part of an organization is a row until it is named, the same way.
	ax_ct_assert(
		$ax_ct_results,
		'a part of an organization nobody has named yet is a line on the screen and nothing in the card',
		str_contains( $ax_ct_cb_editor, 'function openUnit( key )' )
			&& str_contains( $ax_ct_cb_editor, "( openUnits[ row.key ] || [] ).map( function ( unit ) {" )
			&& str_contains( $ax_ct_cb_editor, 'closeUnit( row.key, unit );' )
			// Nothing writes an empty one on the way in any more.
			&& ! str_contains( $ax_ct_cb_editor, "concat( [ { name: '' } ] )" )
	);
	/*
	 * A language says where a title is held in one of two shapes -- the whole title, or just the line
	 * naming its employer -- and both name the organization by the id being taken away. Looking only
	 * under `organizations/` found neither: they are written under `titles/`, about a different
	 * property, and they would have gone on pointing at something the card no longer holds.
	 */
	$ax_ct_tn_card = array(
		'@type'         => 'Card',
		'name'          => array( 'full' => 'Points at an organization' ),
		'organizations' => array( 'o1' => array( 'name' => 'Axismundi' ) ),
		'titles'        => array( 't1' => array( 'name' => "\xec\x82\xac\xec\x9d\xb4\xed\x8a\xb8", 'organizationId' => 'o1' ) ),
		'localizations' => array(
			'en' => array(
				'titles/t1/organizationId' => 'o1',
				'organizations/o1'         => array( 'name' => 'Axismundi' ),
			),
		),
	);
	$ax_ct_tn_id   = axismundi_contacts_save_card( $ax_ct_book_id, $ax_ct_tn_card );
	$ax_ct_tn_key  = is_wp_error( $ax_ct_tn_id ) ? 0 : (int) $ax_ct_tn_id;
	$ax_ct_loose[] = $ax_ct_tn_key;
	// The organization taken away with the base title cleaned up but the languages left behind.
	$ax_ct_tn_left = axismundi_contacts_card_document( $ax_ct_tn_key );
	unset( $ax_ct_tn_left['organizations'], $ax_ct_tn_left['titles']['t1']['organizationId'] );
	// Checked the way a save checks it: a patch names a path, and every step of that path has to be
	// somewhere on the card it patches.
	$ax_ct_tn_stale = axismundi_contacts_validate_patch( $ax_ct_tn_left, 'en', $ax_ct_tn_left['localizations']['en'] );
	ax_ct_assert(
		$ax_ct_results,
		'a language saying where a title is held is found by the id it names, wherever it names it',
		// Left behind, it names an organization the card no longer holds, and the next save refuses.
		is_wp_error( $ax_ct_tn_stale )
			&& str_contains( $ax_ct_cb_editor, "/^titles\\/[^/]+\\/organizationId$/.test( path ) && id === value" )
			&& str_contains( $ax_ct_cb_editor, "/^titles\\/[^/]+$/.test( path ) && value && 'object' === typeof value && id === value.organizationId" )
			// A patch about more than the organization keeps the rest of what it says.
			&& str_contains( $ax_ct_cb_editor, 'function withoutPatches( localizations, going )' )
			&& str_contains( $ax_ct_cb_editor, 'delete value.organizationId;' )
	);
	/*
	 * And taking the title itself away leaves every language that said it in their own words naming a
	 * property the card no longer has.
	 */
	$ax_ct_tn_gone = axismundi_contacts_card_document( $ax_ct_tn_key );
	$ax_ct_tn_gone['localizations']['en'] = array( 'titles/t1/name' => 'Site Owner' );
	axismundi_contacts_save_card_for_owner( (int) $ax_ct_owner->get_identity_id(), $ax_ct_tn_gone, $ax_ct_tn_key );
	$ax_ct_tn_without = axismundi_contacts_card_document( $ax_ct_tn_key );
	unset( $ax_ct_tn_without['titles'] );
	$ax_ct_tn_orphan = axismundi_contacts_validate_patch( $ax_ct_tn_without, 'en', $ax_ct_tn_without['localizations']['en'] );
	ax_ct_assert(
		$ax_ct_results,
		'removing a position names the languages that give it, and lets go of both together',
		is_wp_error( $ax_ct_tn_orphan )
			&& str_contains( $ax_ct_cb_editor, "patchesUnder( ( props.card || {} ).localizations, 'titles/' + row.id )" )
			&& str_contains( $ax_ct_cb_editor, "__( 'Remove them and the position', 'axismundi-contacts' )" )
	);

	// -- a phone number, and where to read it from --------------------------------------------------------------

	/*
	 * What a national number means -- which leading zero is a trunk prefix and which is part of the
	 * number, where Italy differs from Korea -- is a table per country that changes. A plugin writing
	 * its own would be wrong about somewhere within a year, so Google's rules are carried as a browser
	 * build with its licence and its version recorded beside it.
	 */
	$ax_ct_ph_dir = dirname( __DIR__ ) . '/assets/vendor/libphonenumber-js/';
	ax_ct_assert(
		$ax_ct_results,
		'the rules for reading a phone number are carried rather than guessed at, with their licence',
		is_readable( $ax_ct_ph_dir . 'libphonenumber.min.js' )
			&& is_readable( $ax_ct_ph_dir . 'LICENSE' )
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own asset in a dev fixture.
			&& str_contains( (string) file_get_contents( $ax_ct_ph_dir . 'VERSION' ), 'libphonenumber-js 1.13.11' )
			&& str_contains( $ax_ct_lg_php, "'axismundi-contacts-libphonenumber'," )
	);
	/*
	 * The country is a hint for reading what somebody types and is never written down. The card keeps
	 * `tel:+82…`, which says Korea itself; a second field saying `KR` beside it would be the same
	 * answer twice, with nothing keeping the two in step.
	 */
	$ax_ct_ph_num = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type'  => 'Card',
			'name'   => array( 'full' => 'Three shapes of number' ),
			'phones' => array(
				'p1' => array( 'number' => 'tel:+821027421672', 'contexts' => array( 'private' => true ), 'features' => array( 'mobile' => true ) ),
				// An extension, which the standard writes into the URI.
				'p2' => array( 'number' => 'tel:+15555555555;ext=5555', 'label' => 'Google Voice' ),
				// And something that is not a number at all, which is still an entry in somebody's book.
				'p3' => array( 'number' => 'x4821' ),
			),
		)
	);
	$ax_ct_ph_key  = is_wp_error( $ax_ct_ph_num ) ? 0 : (int) $ax_ct_ph_num;
	$ax_ct_loose[] = $ax_ct_ph_key;
	$ax_ct_ph_read = axismundi_contacts_card_document( $ax_ct_ph_key );
	ax_ct_assert(
		$ax_ct_results,
		'a number this cannot read is kept as it was written, and no entry says which country it is in',
		'x4821' === (string) ( $ax_ct_ph_read['phones']['p3']['number'] ?? '' )
			&& 'tel:+15555555555;ext=5555' === (string) ( $ax_ct_ph_read['phones']['p2']['number'] ?? '' )
			// Nothing anywhere stores the region: it is a hint the screen holds while somebody types.
			&& ! array_key_exists( 'region', (array) $ax_ct_ph_read['phones']['p1'] )
			&& ! str_contains( $ax_ct_cb_editor, "withKey( each, 'region'" )
			&& str_contains( $ax_ct_cb_editor, 'var [ hints, setHints ] = useState( {} );' )
	);
	/*
	 * Settled when the box is left and not one keystroke sooner. Somebody writing `010-2742-1672` is
	 * writing the number the way they know it, and a field rewriting it into `+82 10…` under their
	 * hands would be arguing with them about their own phone number.
	 *
	 * And settled only when it is valid, not merely possible: a Korean number typed into a row that
	 * says United States is the right length for one, and storing `tel:+102…` would invent a number
	 * nobody has.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a number is settled when the box is left, and only when this is sure what it says',
		str_contains( $ax_ct_cb_editor, '// Settled when the box is left, and not one keystroke sooner.' )
			&& str_contains( $ax_ct_cb_editor, 'read && read.isValid() ? read.getURI() : typed' )
			&& str_contains( $ax_ct_cb_editor, 'function storeNumber( text, region )' )
			// Without the rules loaded the field still works: what is typed is what is stored.
			&& str_contains( $ax_ct_cb_editor, 'if ( ! phoneRules || ! typed ) {' )
	);
	/*
	 * What a row is called is stored as the standard's two axes rather than as the word on the
	 * screen. A mobile is somebody's own phone unless they say otherwise, so it is `private` as well
	 * as `mobile`; the number a place answers on is not always somebody's number at work, so `main`
	 * carries no context at all.
	 */
	$ax_ct_ph_presets = axismundi_contacts_phone_presets();
	ax_ct_assert(
		$ax_ct_results,
		'what a phone row is called is stored as what it means, and a mobile is somebody’s own',
		array( 'private' ) === $ax_ct_ph_presets['mobile']['contexts']
			&& array( 'mobile' ) === $ax_ct_ph_presets['mobile']['features']
			&& array( 'work' ) === $ax_ct_ph_presets['work-mobile']['contexts']
			&& array() === $ax_ct_ph_presets['main']['contexts']
			// `voice` is not written alongside `mobile`: what kind of thing it is is already said.
			&& ! in_array( 'voice', $ax_ct_ph_presets['mobile']['features'], true )
			// And a stored entry reads back as the row it was chosen from.
			&& 'mobile' === axismundi_contacts_entry_preset( 'phones', (array) $ax_ct_ph_read['phones']['p1'] )
			&& 'custom' === axismundi_contacts_entry_preset( 'phones', (array) $ax_ct_ph_read['phones']['p2'] )
	);
	/*
	 * And where to read a number typed without a country. The card comes first -- one written in
	 * `ko-KR` is one whose numbers are most likely Korean -- and the site after it. Neither is a
	 * guarantee, which is why it is a control somebody can change rather than a decision.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'where to read a number from is suggested by the card, then the site, and never decided',
		'KR' === axismundi_contacts_default_region( array( 'language' => 'ko-KR' ) )
			&& 'GB' === axismundi_contacts_default_region( array( 'language' => 'en-GB' ) )
			// A language with no place in it says nothing about where a number is from.
			&& axismundi_contacts_default_region( array( 'language' => 'ko' ) ) === axismundi_contacts_default_region( array() )
	);

	/*
	 * An entry id is an address: what a provenance row is written against and what a published
	 * pointer names. Counting up from `p1` hands a deleted number's address to the next one somebody
	 * types, along with where that old value came from and whoever had agreed it could be published.
	 * Every id is drawn, and drawn again if the card already has it.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a new entry gets an address nothing here has used, rather than the next number up',
		str_contains( $ax_ct_cb_editor, 'function newEntryId( prefix, entries )' )
			&& str_contains( $ax_ct_cb_editor, "id = prefix + '-' + uniqueId();" )
			// A whole uuid: what this Card holds says nothing about what it used to hold.
			&& str_contains( $ax_ct_cb_editor, 'window.crypto.randomUUID()' )
			&& str_contains( $ax_ct_cb_editor, 'window.crypto.getRandomValues( new Uint8Array( 16 ) )' )
			/*
			 * And the address says which collection it is in, not what the entry is called: a number
			 * that moves from `Mobile` to `Work` keeps the address its provenance and its publishing
			 * consent were written against.
			 */
			&& str_contains( $ax_ct_cb_editor, "prefix: 'tel', required: 'number'" )
			&& str_contains( $ax_ct_cb_editor, "prefix: 'eml', required: 'address'" )
			&& str_contains( $ax_ct_cb_editor, "newEntryId( options.prefix, latest.current )" )
			&& str_contains( $ax_ct_cb_editor, "newEntryId( 'onl', entries )" )
			&& str_contains( $ax_ct_cb_editor, "newEntryId( 'org', entries )" )
			&& str_contains( $ax_ct_cb_editor, "newEntryId( 'ttl', entries )" )
			&& str_contains( $ax_ct_cb_editor, "prefix: 'eml'" )
			&& str_contains( $ax_ct_cb_editor, "newEntryId( props.field.prefix, entries )" )
			// Nothing counts up any more, here or in the accounts the server seeds.
			&& ! str_contains( $ax_ct_cb_editor, "'p' + index" )
			&& ! str_contains( $ax_ct_cb_editor, "'x' + index" )
	);
	/*
	 * A phone entry is a number, so a row where somebody has picked what to call it and not yet typed
	 * one is a row. What they answered waits on it: picking `Work mobile` and then typing the number
	 * means both, and dropping the first because the second came second is the screen forgetting what
	 * it was told.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'what a number is called can be answered first, and is still there when the number arrives',
		str_contains( $ax_ct_cb_editor, 'var made = change( row.entry );' )
			// One set of row mechanics, because it was got wrong once per collection.
			&& str_contains( $ax_ct_cb_editor, 'function useEntryRows( props, options )' )
			&& str_contains( $ax_ct_cb_editor, 'function resolveEntryRemoval( card, property, question, update )' )
			&& str_contains( $ax_ct_cb_editor, "return each.id === row.pending ? { id: each.id, entry: made } : each;" )
	);
	/*
	 * And a number rubbed out is a number removed. An entry that is only a label is one the store
	 * refuses -- `number` is required -- so what is left is a row holding everything else it said,
	 * waiting for another number.
	 */
	$ax_ct_pi_only = array(
		'@type'  => 'Card',
		'name'   => array( 'full' => 'A label and no number' ),
		'phones' => array( 'pho-abc123' => array( 'contexts' => array( 'work' => true ), 'features' => array( 'mobile' => true ) ) ),
	);
	ax_ct_assert(
		$ax_ct_results,
		'an entry that is only a label is not an entry, and rubbing out a number leaves a row instead',
		is_wp_error( axismundi_contacts_validate_card_values( $ax_ct_pi_only ) )
			&& str_contains( $ax_ct_cb_editor, "if ( ! keeps && ! String( written[ options.required ] || '' ).trim() ) {" )
			&& str_contains( $ax_ct_cb_editor, "setPending( pending.concat( [ { id: newEntryId( 'row', {} ), entry: written } ] ) );" )
			// Unless a language says something about it, in which case taking it away is a question.
			&& str_contains( $ax_ct_cb_editor, "patchesUnder( ( props.card || {} ).localizations, options.property + '/' + id )" )
			&& str_contains( $ax_ct_cb_editor, "__( 'Remove them and the number', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_cb_editor, "__( 'Remove them and the address', 'axismundi-contacts' )" )
	);

	/*
	 * `pho-` was written for a few days before the collections agreed on what to call themselves.
	 * `tel-` is what a phone entry is: it reads straight in an export beside `"number": "tel:+82…"`,
	 * and a document addressing one collection two ways depending on when it was written is one
	 * nobody can search or explain.
	 *
	 * Moving one moves everything that addresses it, in one transaction -- the key, any language
	 * patching a path through it, the provenance row, and the pointer saying it may be published.
	 */
	$ax_ct_tk_actor = ax_ct_actor( $ax_ct_users );
	$ax_ct_tk_sid   = (int) $ax_ct_tk_actor->get_identity_id();
	$ax_ct_tk_made  = axismundi_contacts_create_profile_card( $ax_ct_tk_sid );
	$ax_ct_tk_card  = is_wp_error( $ax_ct_tk_made ) ? 0 : (int) $ax_ct_tk_made;
	$ax_ct_loose[]  = $ax_ct_tk_card;
	$ax_ct_tk_doc   = axismundi_contacts_card_document( $ax_ct_tk_card );
	$ax_ct_tk_doc['phones'] = array(
		'pho-aaaaaa' => array( 'number' => 'tel:+821027421672', 'features' => array( 'mobile' => true ) ),
		'tel-bbbbbb' => array( 'number' => 'tel:+8221234567' ),
	);
	$ax_ct_tk_doc['localizations'] = array( 'en' => array( 'phones/pho-aaaaaa/label' => 'Mobile' ) );
	axismundi_contacts_save_card_for_owner( $ax_ct_tk_sid, $ax_ct_tk_doc, $ax_ct_tk_card );
	axismundi_contacts_set_provenance( $ax_ct_tk_card, 'phones/pho-aaaaaa', AXISMUNDI_CONTACTS_SOURCE_ACTOR, $ax_ct_tk_actor->get_uri() );
	axismundi_contacts_set_published_pointers( $ax_ct_tk_sid, array( 'name', 'phones/pho-aaaaaa' ) );

	axismundi_contacts_migrate_phone_keys();
	// Twice, because a migration that is not safe to run again is one that runs again anyway.
	axismundi_contacts_migrate_phone_keys();

	$ax_ct_tk_after = axismundi_contacts_card_document( $ax_ct_tk_card );
	$ax_ct_tk_prov  = axismundi_contacts_card_provenance( $ax_ct_tk_card );
	$ax_ct_tk_pub   = axismundi_contacts_published_pointers( $ax_ct_tk_sid );
	ax_ct_assert(
		$ax_ct_results,
		'a phone moved onto the name its collection goes by takes everything that addressed it',
		array( 'tel-aaaaaa', 'tel-bbbbbb' ) === array_keys( (array) $ax_ct_tk_after['phones'] )
			// The entry itself is untouched: only where it is addressed changed.
			&& 'tel:+821027421672' === (string) ( $ax_ct_tk_after['phones']['tel-aaaaaa']['number'] ?? '' )
			&& isset( $ax_ct_tk_after['localizations']['en']['phones/tel-aaaaaa/label'] )
			&& ! isset( $ax_ct_tk_after['localizations']['en']['phones/pho-aaaaaa/label'] )
			&& isset( $ax_ct_tk_prov['phones/tel-aaaaaa'] )
			&& ! isset( $ax_ct_tk_prov['phones/pho-aaaaaa'] )
			&& in_array( 'phones/tel-aaaaaa', $ax_ct_tk_pub, true )
			&& ! in_array( 'phones/pho-aaaaaa', $ax_ct_tk_pub, true )
	);
	/*
	 * And the two migrations are one mover. The older one moved an account and this one moves a
	 * number, and what has to move with either is the same four things -- written twice, they would
	 * have drifted the first time one of them learned something.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_tk_schema = (string) file_get_contents( dirname( __DIR__ ) . '/includes/schema.php' );
	ax_ct_assert(
		$ax_ct_results,
		'moving an entry from one address to another is one thing this knows how to do, not two',
		str_contains( $ax_ct_tk_schema, 'function axismundi_contacts_move_entry_key(' )
			// Defined once and called from both migrations.
			&& 3 === substr_count( $ax_ct_tk_schema, 'axismundi_contacts_move_entry_key(' )
			&& str_contains( $ax_ct_tk_schema, "'onlineServices'," )
			&& str_contains( $ax_ct_tk_schema, "'phones'," )
	);

	/*
	 * And a phone whose new address is already taken -- two entries whose ids differ only by the
	 * prefix -- moves to a fresh one rather than staying where it is. Merging them would put one
	 * number's provenance and publishing consent onto another; leaving it would leave a `pho-` behind
	 * for good, and which address a document uses would depend on the week it was written.
	 */
	$ax_ct_tc_doc = axismundi_contacts_card_document( $ax_ct_tk_card );
	$ax_ct_tc_doc['phones'] = array(
		'pho-cccccc' => array( 'number' => 'tel:+821011112222' ),
		'tel-cccccc' => array( 'number' => 'tel:+821033334444' ),
	);
	$ax_ct_tc_doc['localizations'] = array( 'en' => array( 'phones/pho-cccccc/label' => 'The one that had to move' ) );
	axismundi_contacts_save_card_for_owner( $ax_ct_tk_sid, $ax_ct_tc_doc, $ax_ct_tk_card );
	axismundi_contacts_set_provenance( $ax_ct_tk_card, 'phones/pho-cccccc', AXISMUNDI_CONTACTS_SOURCE_ACTOR, $ax_ct_tk_actor->get_uri() );
	axismundi_contacts_set_published_pointers( $ax_ct_tk_sid, array( 'name', 'phones/pho-cccccc' ) );

	axismundi_contacts_migrate_phone_keys();

	$ax_ct_tc_after = axismundi_contacts_card_document( $ax_ct_tk_card );
	$ax_ct_tc_keys  = array_keys( (array) $ax_ct_tc_after['phones'] );
	$ax_ct_tc_new   = array_values( array_diff( $ax_ct_tc_keys, array( 'tel-cccccc' ) ) );
	$ax_ct_tc_moved = $ax_ct_tc_new[0] ?? '';
	$ax_ct_tc_prov  = axismundi_contacts_card_provenance( $ax_ct_tk_card );
	ax_ct_assert(
		$ax_ct_results,
		'a number whose new address is occupied is given another, rather than left where it was',
		2 === count( $ax_ct_tc_keys )
			// Nothing is left on the old name, and nothing was merged into the address already there.
			&& ! in_array( 'pho-cccccc', $ax_ct_tc_keys, true )
			&& 1 === preg_match( '/^tel-[0-9a-f]{8}-/', $ax_ct_tc_moved )
			&& 'tel:+821011112222' === (string) ( $ax_ct_tc_after['phones'][ $ax_ct_tc_moved ]['number'] ?? '' )
			&& 'tel:+821033334444' === (string) ( $ax_ct_tc_after['phones']['tel-cccccc']['number'] ?? '' )
			// And everything that addressed it went with it.
			&& isset( $ax_ct_tc_after['localizations']['en'][ 'phones/' . $ax_ct_tc_moved . '/label' ] )
			&& isset( $ax_ct_tc_prov[ 'phones/' . $ax_ct_tc_moved ] )
			&& in_array( 'phones/' . $ax_ct_tc_moved, axismundi_contacts_published_pointers( $ax_ct_tk_sid ), true )
	);

	/*
	 * What somebody would rather be written to in is not only which language. French at work and
	 * French with friends are two things a person can prefer, and RFC 9553's own example says exactly
	 * that -- so a row is a language and where it applies, and two rows differing only in the second
	 * are two answers rather than a mistake.
	 *
	 * The store took this the whole time. The screen could not: it edited the language and nothing
	 * else, which meant the only way to write that example was the JSON box.
	 */
	$ax_ct_pl_id = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type'              => 'Card',
			'name'               => array( 'full' => 'Three preferences' ),
			'preferredLanguages' => array(
				'lng-a' => array( 'language' => 'en', 'contexts' => array( 'work' => true ), 'pref' => 1 ),
				'lng-b' => array( 'language' => 'fr', 'contexts' => array( 'work' => true ), 'pref' => 2 ),
				// The same language again, somewhere else in their life, and with no place in the order.
				'lng-c' => array( 'language' => 'fr', 'contexts' => array( 'private' => true ) ),
			),
		)
	);
	$ax_ct_pl_key  = is_wp_error( $ax_ct_pl_id ) ? 0 : (int) $ax_ct_pl_id;
	$ax_ct_loose[] = $ax_ct_pl_key;
	$ax_ct_pl_back = axismundi_contacts_card_document( $ax_ct_pl_key );
	ax_ct_assert(
		$ax_ct_results,
		'a language can be preferred at work and another way at home, and the screen can say so',
		'fr' === (string) ( $ax_ct_pl_back['preferredLanguages']['lng-b']['language'] ?? '' )
			&& 'fr' === (string) ( $ax_ct_pl_back['preferredLanguages']['lng-c']['language'] ?? '' )
			&& true === ( $ax_ct_pl_back['preferredLanguages']['lng-b']['contexts']['work'] ?? null )
			&& true === ( $ax_ct_pl_back['preferredLanguages']['lng-c']['contexts']['private'] ?? null )
			// A row nobody has put anywhere carries no place in the order at all.
			&& ! array_key_exists( 'pref', (array) $ax_ct_pl_back['preferredLanguages']['lng-c'] )
			&& str_contains( $ax_ct_cb_editor, 'var CONTEXT_CHOICES = [' )
			&& str_contains( $ax_ct_cb_editor, "{ value: 'private+work'," )
			&& str_contains( $ax_ct_cb_editor, 'function withContext( entry, choice )' )
	);
	/*
	 * Where it applies can be answered before which language, the way it can everywhere else, and the
	 * row holds it until the language arrives. And `pref` is written by dragging, because dragging is
	 * somebody stating an order -- a row nobody has moved is not given a number this invented.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a preference is a row until it names a language, and its place in the order is stated rather than assumed',
		str_contains( $ax_ct_cb_editor, "// A preference is a language. Until there is one, this is a line and what has been" )
			&& str_contains( $ax_ct_cb_editor, "newEntryId( 'lng', entries )" )
			&& str_contains( $ax_ct_cb_editor, 'next[ id ] = Object.assign( {}, latest.current[ id ], { pref: index + 1 } );' )
			// Read in the same order the accounts are, because `pref` means the same thing in both.
			&& str_contains( $ax_ct_cb_editor, 'var order = orderedByPreference( entries );' )
			&& str_contains( $ax_ct_cb_editor, 'function orderedByPreference( entries )' )
	);

	/*
	 * An email address has one axis where a phone has two. A phone can be a fax and a mobile at once,
	 * which is why it says both what it is for and where it belongs; an address is only ever somewhere
	 * in somebody's life. Anything the three answers cannot say is somebody's own word for it, which
	 * the standard stores as a label -- and the two are exclusive, because an entry saying both `work`
	 * and `Newsletter` is claiming two answers to one question.
	 */
	$ax_ct_em_presets = axismundi_contacts_email_presets();
	$ax_ct_em_id      = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type'  => 'Card',
			'name'   => array( 'full' => 'Two addresses' ),
			'emails' => array(
				'eml-one' => array( 'address' => 'me@example.test', 'contexts' => array( 'private' => true ) ),
				'eml-two' => array( 'address' => 'listed@example.test', 'label' => 'Newsletter' ),
			),
		)
	);
	$ax_ct_em_key  = is_wp_error( $ax_ct_em_id ) ? 0 : (int) $ax_ct_em_id;
	$ax_ct_loose[] = $ax_ct_em_key;
	$ax_ct_em_back = axismundi_contacts_card_document( $ax_ct_em_key );
	ax_ct_assert(
		$ax_ct_results,
		'an address says where it belongs, or what somebody calls it, and never both',
		array() === $ax_ct_em_presets['private']['features']
			&& 'private' === axismundi_contacts_entry_preset( 'emails', (array) $ax_ct_em_back['emails']['eml-one'] )
			&& 'custom' === axismundi_contacts_entry_preset( 'emails', (array) $ax_ct_em_back['emails']['eml-two'] )
			&& ! array_key_exists( 'contexts', (array) $ax_ct_em_back['emails']['eml-two'] )
			&& str_contains( $ax_ct_cb_editor, 'function Emails( props )' )
			&& str_contains( $ax_ct_cb_editor, "__( 'Add an email address', 'axismundi-contacts' )" )
	);
	/*
	 * And the rows work the way every other collection's do, because they are the same rows. Each of
	 * these was got wrong once, in a different section, before it was written down in one place:
	 * opening a row writes nothing, what is answered first waits for what makes it an entry, emptying
	 * that takes the entry away and leaves the row, and an entry a language talks about is a question
	 * rather than a keystroke.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'every collection asks the same things of a row, because it is the same row',
		str_contains( $ax_ct_cb_editor, "useEntryRows( props, { prefix: 'tel', required: 'number', property: 'phones' } )" )
			&& str_contains( $ax_ct_cb_editor, "useEntryRows( props, { prefix: 'eml', required: 'address', property: 'emails' } )" )
			&& str_contains( $ax_ct_cb_editor, "resolveEntryRemoval( card, 'phones', question, update )" )
			&& str_contains( $ax_ct_cb_editor, "resolveEntryRemoval( card, 'emails', question, update )" )
			// Written once because it was got wrong once per collection.
			&& str_contains( $ax_ct_cb_editor, 'Written once because it was got wrong once per collection.' )
	);

	/*
	 * An address is not a name. A name is six parts in an order, the same six everywhere; an address
	 * is a different shape in every country -- Korean runs largest to smallest and British the other
	 * way, and the fields themselves differ. A screen taking one apart into boxes would be picking one
	 * country's form and asking the rest of the world to fit it.
	 *
	 * So a row is the country, the address as somebody writes it, and where it belongs in their life.
	 * `components` is neither built from the line nor overwritten by it.
	 */
	$ax_ct_ad_id = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type'     => 'Card',
			'name'      => array( 'full' => 'Two addresses' ),
			'addresses' => array(
				'adr-one' => array( 'countryCode' => 'KR', 'full' => "\xeb\xb6\x80\xec\x82\xb0", 'contexts' => array( 'private' => true ) ),
				// Arrived in pieces from something that took it apart, with no line to read.
				'adr-two' => array(
					'countryCode'      => 'GB',
					'components'       => array( array( 'kind' => 'locality', 'value' => 'London' ) ),
					'defaultSeparator' => ', ',
				),
			),
		)
	);
	$ax_ct_ad_key  = is_wp_error( $ax_ct_ad_id ) ? 0 : (int) $ax_ct_ad_id;
	$ax_ct_loose[] = $ax_ct_ad_key;
	$ax_ct_ad_back = axismundi_contacts_card_document( $ax_ct_ad_key );
	ax_ct_assert(
		$ax_ct_results,
		'an address is what somebody wrote and which country reads it, and its parts are left alone',
		'KR' === (string) ( $ax_ct_ad_back['addresses']['adr-one']['countryCode'] ?? '' )
			// The one that came in pieces keeps them, and gains no line it did not have.
			&& 'London' === (string) ( $ax_ct_ad_back['addresses']['adr-two']['components'][0]['value'] ?? '' )
			&& ', ' === (string) ( $ax_ct_ad_back['addresses']['adr-two']['defaultSeparator'] ?? '' )
			&& ! array_key_exists( 'full', (array) $ax_ct_ad_back['addresses']['adr-two'] )
			&& str_contains( $ax_ct_cb_editor, 'function Addresses( props )' )
			// Nothing takes an address apart or puts it back together.
			&& ! str_contains( $ax_ct_cb_editor, 'addressComponents' )
	);
	/*
	 * And an address that arrived in pieces survives somebody typing a line into it and rubbing it out
	 * again. What makes a new entry is not the only thing that makes an existing one worth keeping,
	 * and losing a structure this screen never showed them would be the worst kind of loss: silent,
	 * and of something they did not know was there.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'an entry stays for what it holds, not only for the one thing that would have started it',
		str_contains( $ax_ct_cb_editor, "keeps: [ 'components', 'coordinates', 'countryCode', 'timeZone' ]" )
			&& str_contains( $ax_ct_cb_editor, 'var keeps = ( options.keeps || [] ).some( function ( key ) {' )
			&& str_contains( $ax_ct_cb_editor, "required: 'full'," )
	);
	/*
	 * Nothing here sends an address anywhere to be completed or checked. The fields carry the
	 * platform's own autofill tokens instead, so somebody who has told their browser where they live
	 * can hand it over themselves -- the browser holds the addresses and the person chooses, which is
	 * the opposite of a screen asking a service on their behalf.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a browser may offer what somebody told it, and nothing is asked of anybody else',
		str_contains( $ax_ct_cb_editor, 'function addressAutofill( kind )' )
			&& str_contains( $ax_ct_cb_editor, "locality: 'address-level2'," )
			// The line's own token, handed to the field that draws it.
			&& str_contains( $ax_ct_cb_editor, "fullInputProps: { autoComplete: 'street-address' }," )
			// Nothing fetches, and nothing names a service that would.
			&& ! str_contains( $ax_ct_cb_editor, 'maps.googleapis' )
			&& ! str_contains( $ax_ct_cb_editor, 'nominatim' )
			&& ! str_contains( $ax_ct_cb_editor, 'geocod' )
			&& str_contains( $ax_ct_cb_editor, 'Nothing is sent anywhere: the browser holds the addresses' )
	);

	/*
	 * The standard's own example, made and read back without losing anything. An address is written
	 * two ways and a Card may hold both -- the parts it is made of and the line it is written as --
	 * and neither is built from the other: the Tokyo example in RFC 9553 carries components, a `full`
	 * and a Japanese localization of both.
	 */
	$ax_ct_k23 = array(
		'contexts'         => array( 'work' => true ),
		'components'       => array(
			array( 'kind' => 'number', 'value' => '54321' ),
			array( 'kind' => 'separator', 'value' => ' ' ),
			array( 'kind' => 'name', 'value' => 'Oak St' ),
			array( 'kind' => 'locality', 'value' => 'Reston' ),
			array( 'kind' => 'region', 'value' => 'VA' ),
			array( 'kind' => 'separator', 'value' => ' ' ),
			array( 'kind' => 'postcode', 'value' => '20190' ),
			array( 'kind' => 'country', 'value' => 'USA' ),
		),
		'countryCode'      => 'US',
		'defaultSeparator' => ', ',
		'isOrdered'        => true,
	);
	$ax_ct_k23_id  = axismundi_contacts_save_card( $ax_ct_book_id, array( '@type' => 'Card', 'name' => array( 'full' => 'k23' ), 'addresses' => array( 'k23' => $ax_ct_k23 ) ) );
	$ax_ct_k23_key = is_wp_error( $ax_ct_k23_id ) ? 0 : (int) $ax_ct_k23_id;
	$ax_ct_loose[] = $ax_ct_k23_key;
	$ax_ct_k23_back = (array) ( axismundi_contacts_card_document( $ax_ct_k23_key )['addresses']['k23'] ?? array() );
	ax_ct_assert(
		$ax_ct_results,
		'the standard’s own address comes back the way it went in, separators and all',
		array_column( $ax_ct_k23['components'], 'kind' ) === array_column( (array) $ax_ct_k23_back['components'], 'kind' )
			&& array_column( $ax_ct_k23['components'], 'value' ) === array_column( (array) $ax_ct_k23_back['components'], 'value' )
			// `US` is the code and `USA` is what somebody wrote in the address: two facts, both kept.
			&& 'US' === (string) $ax_ct_k23_back['countryCode']
			&& 'USA' === (string) $ax_ct_k23_back['components'][7]['value']
			&& ', ' === (string) $ax_ct_k23_back['defaultSeparator']
			&& true === $ax_ct_k23_back['isOrdered']
	);
	// And the parts are a line each, with a way to ask for the ones that are not.
	ax_ct_assert(
		$ax_ct_results,
		'an address is written as its parts, in whatever order that address is read in',
		str_contains( $ax_ct_cb_editor, "var ADDRESS_SLOTS = [ 'number', 'name', 'district', 'locality', 'region', 'postcode', 'country' ];" )
			&& str_contains( $ax_ct_cb_editor, "'postOfficeBox', 'separator' ];" )
			&& str_contains( $ax_ct_cb_editor, 'function AddressParts( props )' )
			&& str_contains( $ax_ct_cb_editor, 'slotRows( components, ADDRESS_SLOTS, pending, true )' )
			// Nothing at an end: a Korean address opens with the region and an American one closes.
			&& ! str_contains( $ax_ct_cb_editor, 'ADDRESS_FIXED' )
	);
	/*
	 * An address made only of the things that go between its parts is an address with no parts:
	 * `, , ,` names nowhere. Everything else is left as it arrived -- a separator on an unordered
	 * address is wrong rather than meaningless, and refusing it would refuse somebody's import over a
	 * rule written for whoever is doing the writing.
	 */
	$ax_ct_k23_empty = array(
		'@type'     => 'Card',
		'name'      => array( 'full' => 'Nowhere' ),
		'addresses' => array( 'adr-x' => array( 'components' => array( array( 'kind' => 'separator', 'value' => ', ' ) ), 'isOrdered' => true ) ),
	);
	ax_ct_assert(
		$ax_ct_results,
		'an address made only of separators names nowhere, and is refused for saying nothing',
		is_wp_error( axismundi_contacts_validate_card_values( $ax_ct_k23_empty ) )
			// While one whose parts are in no order keeps whatever it arrived with.
			&& true === axismundi_contacts_validate_card_values(
				array( 'addresses' => array( 'adr-y' => array( 'components' => array( array( 'kind' => 'locality', 'value' => 'London' ) ), 'defaultSeparator' => ', ' ) ) )
			)
	);
	/*
	 * Where an address belongs is more than one answer. A work address may also be where things are
	 * delivered, and the standard says both with two contexts rather than making somebody choose --
	 * which is why this row has checkboxes where a phone row has a list.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'an address can be two places in somebody’s life at once, which a phone cannot',
		str_contains( $ax_ct_cb_editor, 'var ADDRESS_CONTEXTS = [' )
			&& str_contains( $ax_ct_cb_editor, "{ value: 'billing'," )
			&& str_contains( $ax_ct_cb_editor, "{ value: 'delivery'," )
			&& str_contains( $ax_ct_cb_editor, "className: 'ax-ce-address__contexts'" )
			// Asked as one question rather than four loose boxes under the fields.
			&& str_contains( $ax_ct_cb_editor, "__( 'Use this address for', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_cb_editor, "el( 'legend', null," )
	);

	/*
	 * The standard's Tokyo example, which is the one that proves the whole shape: an address written
	 * both as parts and as a line, and a language giving the same address again in its own order with
	 * its own separator and its own line. The Latin form reads block-first and the Japanese form
	 * region-first -- the same place, written the way each language writes it -- and neither is
	 * derived from the other.
	 */
	$ax_ct_k26 = array(
		'components'       => array(
			array( 'kind' => 'block', 'value' => '2-7' ),
			array( 'kind' => 'separator', 'value' => '-' ),
			array( 'kind' => 'number', 'value' => '2' ),
			array( 'kind' => 'separator', 'value' => ' ' ),
			array( 'kind' => 'district', 'value' => 'Marunouchi' ),
			array( 'kind' => 'locality', 'value' => 'Chiyoda-ku' ),
			array( 'kind' => 'region', 'value' => 'Tokyo' ),
			array( 'kind' => 'separator', 'value' => ' ' ),
			array( 'kind' => 'postcode', 'value' => '100-8994' ),
		),
		'defaultSeparator' => ', ',
		'full'             => '2-7-2 Marunouchi, Chiyoda-ku, Tokyo 100-8994',
		'isOrdered'        => true,
	);
	$ax_ct_k26_ja = array(
		'components'       => array(
			array( 'kind' => 'region', 'value' => "\xe6\x9d\xb1\xe4\xba\xac\xe9\x83\xbd" ),
			array( 'kind' => 'locality', 'value' => "\xe5\x8d\x83\xe4\xbb\xa3\xe7\x94\xb0\xe5\x8c\xba" ),
			array( 'kind' => 'district', 'value' => "\xe4\xb8\xb8\xe3\x83\x8e\xe5\x86\x85" ),
			array( 'kind' => 'block', 'value' => '2-7' ),
			array( 'kind' => 'separator', 'value' => '-' ),
			array( 'kind' => 'number', 'value' => '2' ),
			array( 'kind' => 'postcode', 'value' => "\xe3\x80\x92100-8994" ),
		),
		// Written without anything between the parts, which is how the address is read there.
		'defaultSeparator' => '',
		'full'             => "\xe3\x80\x92100-8994\xe6\x9d\xb1\xe4\xba\xac\xe9\x83\xbd\xe5\x8d\x83\xe4\xbb\xa3\xe7\x94\xb0\xe5\x8c\xba\xe4\xb8\xb8\xe3\x83\x8e\xe5\x86\x852-7-2",
		'isOrdered'        => true,
	);
	$ax_ct_k26_id  = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type'         => 'Card',
			'name'          => array( 'full' => 'Tokyo' ),
			'addresses'     => array( 'k26' => $ax_ct_k26 ),
			'localizations' => array( 'jp' => array( 'addresses/k26' => $ax_ct_k26_ja ) ),
		)
	);
	$ax_ct_k26_key = is_wp_error( $ax_ct_k26_id ) ? 0 : (int) $ax_ct_k26_id;
	$ax_ct_loose[] = $ax_ct_k26_key;
	$ax_ct_k26_back = axismundi_contacts_card_document( $ax_ct_k26_key );
	$ax_ct_k26_bja  = (array) ( $ax_ct_k26_back['localizations']['jp']['addresses/k26'] ?? array() );
	ax_ct_assert(
		$ax_ct_results,
		'one address written as parts and as a line, and a language giving both again its own way',
		// Both representations survive on the base card, neither built from the other.
		array_column( $ax_ct_k26['components'], 'value' ) === array_column( (array) $ax_ct_k26_back['addresses']['k26']['components'], 'value' )
			&& $ax_ct_k26['full'] === (string) $ax_ct_k26_back['addresses']['k26']['full']
			// And the language replaces the whole thing: its own order, separator and line.
			&& array_column( $ax_ct_k26_ja['components'], 'kind' ) === array_column( (array) $ax_ct_k26_bja['components'], 'kind' )
			&& '' === (string) $ax_ct_k26_bja['defaultSeparator']
			&& $ax_ct_k26_ja['full'] === (string) $ax_ct_k26_bja['full']
			// Read one way it opens with the block, the other with the region. Same place.
			&& 'block' === (string) $ax_ct_k26_back['addresses']['k26']['components'][0]['kind']
			&& 'region' === (string) $ax_ct_k26_bja['components'][0]['kind']
	);
	// And a language giving a whole address is given the address editor, not a box of JSON.
	ax_ct_assert(
		$ax_ct_results,
		'a language that gives a whole address is given the same editor the address has',
		str_contains( $ax_ct_cb_editor, "if ( isObject && /^addresses\\/[^/]+$/.test( path ) ) {" )
			&& str_contains( $ax_ct_cb_editor, "__( 'Stop giving this address in %s', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_cb_editor, "__( 'The whole address written out in this language, kept as written.', 'axismundi-contacts' )" )
	);
	/*
	 * And an address that arrived saying its parts are in no order keeps saying it. Nothing decides
	 * that on its behalf: the parts may have come from something that recorded what they were and not
	 * how they are written, and until somebody states an order there is nothing to drag and nothing
	 * to put between them.
	 */
	$ax_ct_k26_loose = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type'     => 'Card',
			'name'      => array( 'full' => 'No order stated' ),
			'addresses' => array(
				'adr-z' => array(
					'components' => array(
						array( 'kind' => 'locality', 'value' => 'Bristol' ),
						array( 'kind' => 'postcode', 'value' => 'BS1' ),
					),
					'isOrdered'  => false,
				),
			),
		)
	);
	$ax_ct_k26_lkey = is_wp_error( $ax_ct_k26_loose ) ? 0 : (int) $ax_ct_k26_loose;
	$ax_ct_loose[]  = $ax_ct_k26_lkey;
	$ax_ct_k26_lback = (array) ( axismundi_contacts_card_document( $ax_ct_k26_lkey )['addresses']['adr-z'] ?? array() );
	ax_ct_assert(
		$ax_ct_results,
		'an address whose parts are in no order stays that way until somebody says otherwise',
		false === $ax_ct_k26_lback['isOrdered']
			&& ! array_key_exists( 'defaultSeparator', $ax_ct_k26_lback )
			&& 2 === count( (array) $ax_ct_k26_lback['components'] )
			// The screen offers the order rather than writing it, and holds back what needs one.
			&& str_contains( $ax_ct_cb_editor, "__( 'Say they are in the order they are read', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_cb_editor, "disabled: 'separator' === kind && ! ordered," )
			&& str_contains( $ax_ct_cb_editor, 'var movable = !! props.row.part && props.ordered;' )
			&& str_contains( $ax_ct_cb_editor, 'ordered && components.length' )
	);

	/*
	 * What a browser fills in without telling anybody. Autofill writes the value straight into the
	 * field and fires nothing a script listens for, so the box goes yellow while the document behind
	 * it still holds what was there before -- and the JSON beside it disagrees with the screen. The
	 * one signal that does arrive is a CSS animation, which is why there is one that does nothing and
	 * exists to be noticed.
	 */
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading this plugin's own source in a dev fixture.
	$ax_ct_af_css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/fields.css' );
	ax_ct_assert(
		$ax_ct_results,
		'a field the browser filled in reaches the card, which is not something the browser says',
		/*
		 * Watched rather than waited on. The animation a browser starts when it fills a field is one
		 * way of hearing about it and is not offered everywhere -- on the address fields in Chrome the
		 * rule does not match at all -- so what this actually does is look: at every field on screen,
		 * while somebody is in one of them, for anything showing a value the document does not say.
		 */
		str_contains( $ax_ct_cb_js, 'function sweep()' )
			&& str_contains( $ax_ct_cb_js, 'function watchField( node, current )' )
			&& str_contains( $ax_ct_cb_js, 'function lookForAWhile()' )
			&& str_contains( $ax_ct_cb_js, 'function lookingAtAField()' )
			&& str_contains( $ax_ct_cb_js, 'function stopLookingWhenFocusLeaves()' )
			&& str_contains( $ax_ct_cb_js, 'window.setInterval( sweep, 120 )' )
			// A suggestion may remain open longer than an arbitrary timer, so focus ends the watch.
			&& str_contains( $ax_ct_cb_js, 'if ( looking && ! lookingAtAField() ) {' )
			// Every field, not the one with focus: a browser fills in the boxes beside it too.
			&& str_contains( $ax_ct_cb_js, 'onScreen.forEach( function ( each ) {' )
			&& str_contains( $ax_ct_cb_js, 'held.onChange( node.value );' )
			// Compared against what this code drew, so a formatted field is not mistaken for a change.
			&& str_contains( $ax_ct_cb_js, "var shown = undefined === held.value || null === held.value ? '' : String( held.value );" )
			// And the animation, where a browser does start one.
			&& str_contains( $ax_ct_af_css, '@keyframes ax-contacts-autofill' )
			&& 2 === substr_count( $ax_ct_cb_js, 'onAnimationStart: autofilled(),' )
	);
	/*
	 * And a browser fills several boxes at once. Each of them working out what to store from the copy
	 * this render started with means the last one wins and the rest are lost -- the postcode lands and
	 * the region it arrived with is gone, which is worse than autofill not working at all.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'several fields filled in one go all survive, rather than the last one winning',
		str_contains( $ax_ct_cb_editor, 'var latest = useRef( entries );' )
			&& str_contains( $ax_ct_cb_editor, 'var latest = useRef( address );' )
			&& str_contains( $ax_ct_cb_editor, 'var latest = useRef( card );' )
			// Reset on every render, so it follows the card; moved forward on every write.
			&& str_contains( $ax_ct_cb_editor, 'latest.current = next;' )
			&& str_contains( $ax_ct_cb_editor, 'var written = change( latest.current[ row.id ] || {} );' )
			&& str_contains( $ax_ct_cb_editor, 'var next = Object.assign( {}, latest.current );' )
	);
	/*
	 * Only the parts a browser can hand over whole. Its idea of the first line is `123 Oak St` -- the
	 * number and the street together -- and those are separate parts here, so asking for it would put
	 * a house number inside a street name and call the address structured. The line as a whole has its
	 * own field, which is where that token belongs.
	 *
	 * The country picker takes nothing: it shows a country's name and stores its code, and a browser
	 * handing over either would be writing into a field whose displayed value it cannot see.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a browser is asked only for the parts it can give whole, and never for the first line',
		str_contains( $ax_ct_cb_editor, "region: 'address-level1'," )
			&& str_contains( $ax_ct_cb_editor, "postcode: 'postal-code'," )
			&& str_contains( $ax_ct_cb_editor, "country: 'country-name'" )
			&& ! str_contains( $ax_ct_cb_editor, "name: 'address-line1'," )
			&& str_contains( $ax_ct_cb_editor, "inputProps: { autoComplete: 'off' }," )
			// And the whole line is what `street-address` is for.
			&& str_contains( $ax_ct_cb_editor, "fullInputProps: { autoComplete: 'street-address' }," )
	);

	/*
	 * Said once, and made once. Between writing a value and the screen being drawn again there is a
	 * gap, and a check running every tenth of a second inside it keeps finding the same difference --
	 * which for a row that becomes an entry the first time it is filled in means three filled boxes
	 * turning into three addresses, each holding a bit more than the last. That is what happened.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a value noticed once is written once, and a row becomes an entry once',
		str_contains( $ax_ct_cb_js, 'if ( node.value === each.pushed ) {' )
			&& str_contains( $ax_ct_cb_js, 'each.pushed = node.value;' )
			// Once the document catches up, the same value may be autofilled again later.
			&& str_contains( $ax_ct_cb_js, 'each.pushed = undefined;' )
			&& str_contains( $ax_ct_cb_editor, 'var adopted = useRef( {} );' )
			&& str_contains( $ax_ct_cb_editor, 'if ( row.pending && adopted.current[ row.pending ] ) {' )
			&& str_contains( $ax_ct_cb_editor, 'adopted.current[ row.pending ] = made_id;' )
	);
	/*
	 * And what turns a row into an entry is not always one field. An address is an address if it says
	 * where it is at all -- the standard needs one of components, coordinates, a country code, a line
	 * or a time zone -- so a browser filling in the parts of a new one leaves them on the card rather
	 * than on a row that never became anything.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'an address is one for any of the things that make it one, not for a written-out line alone',
		str_contains( $ax_ct_cb_editor, "makes: [ 'full', 'components', 'countryCode', 'coordinates', 'timeZone' ]," )
			&& str_contains( $ax_ct_cb_editor, "( options.makes || [ options.required ] ).some( function ( key ) {" )
			// And the store agrees: an address that says only where in the world it is, is an address.
			&& true === axismundi_contacts_validate_card_values( array( 'addresses' => array( 'adr-q' => array( 'countryCode' => 'KR' ) ) ) )
	);

	/*
	 * Which timezone an address is in is a question WordPress already asks on its own Settings screen,
	 * and it asks it well: the city names are localized, they are grouped by region, and the list
	 * follows the tz database as WordPress updates. A list written here would be a worse copy of it.
	 *
	 * What this checks is that the two ends agree. Core also offers fixed offsets, which a site may
	 * legitimately run its clock on but an address cannot be in -- `UTC+9` is not a name in the IANA
	 * database and cannot say when summer time starts. So the offsets come out, and what is left has
	 * to be exactly the set the store will accept: an answer offered but refused on save would waste
	 * somebody's work, and a stored zone the picker cannot show would go blank on screen and be lost
	 * the next time that address was touched.
	 */
	require_once dirname( __DIR__ ) . '/includes/card-editor.php';
	$ax_ct_tz_markup = axismundi_contacts_timezone_choice();
	preg_match_all( '/<option[^>]*value="([^"]*)"/', $ax_ct_tz_markup, $ax_ct_tz_hit );
	$ax_ct_tz_offered = array_values( array_filter( $ax_ct_tz_hit[1] ) );
	ax_ct_assert(
		$ax_ct_results,
		'the timezone a place is in is offered exactly as it may be saved, and an offset is neither',
		array() !== $ax_ct_tz_offered
			// Both directions: nothing offered is refused, and nothing acceptable is missing.
			&& array() === array_diff( $ax_ct_tz_offered, timezone_identifiers_list() )
			&& array() === array_diff( timezone_identifiers_list(), $ax_ct_tz_offered )
			&& array() === preg_grep( '/^UTC[+-]/', $ax_ct_tz_offered )
			// A region left with nothing in it is a heading over nothing.
			&& 0 === preg_match( '/<optgroup\b[^>]*>\s*<\/optgroup>/i', $ax_ct_tz_markup )
			&& str_contains( $ax_ct_tz_markup, '<optgroup' )
			&& true === axismundi_contacts_validate_card_values( array( 'addresses' => array( 'adr-tz' => array( 'timeZone' => 'Asia/Seoul' ) ) ) )
			&& is_wp_error( axismundi_contacts_validate_card_values( array( 'addresses' => array( 'adr-tz' => array( 'timeZone' => 'UTC+9' ) ) ) ) )
	);

	/*
	 * And it stays Core's markup rather than a list this plugin assembles: Core returns it, so a
	 * capture that expected it to be printed would leave the picker with nothing in it -- a field that
	 * looks like a field and cannot answer anything. The control it feeds is the timezone's own, not a
	 * general Select: the other fixed lists on this screen are long enough to be searched rather than
	 * scrolled, and one dropdown does not make a component.
	 */
	$ax_ct_cb_fields = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/fields.js' );
	ax_ct_assert(
		$ax_ct_results,
		'the picker is Core’s own markup, drawn by the timezone’s control rather than a general Select',
		str_contains( $ax_ct_cb_editor, 'el( TimeZonePicker, {' )
			&& str_contains( $ax_ct_cb_editor, 'options: config.timeZoneOptions,' )
			&& str_contains( $ax_ct_cb_fields, 'function TimeZonePicker( props )' )
			&& ! str_contains( $ax_ct_cb_fields, 'function Select( props )' )
			// Core returns this markup; capturing output instead would silently empty the field.
			&& str_contains( $ax_ct_tz_markup, 'value="Asia/Seoul"' )
			/*
			 * And it stands the same height as every field beside it. A select is the one control that
			 * will not take the 24px line the others are built from -- browsers force `normal` on it --
			 * so the line is stated as a box. Measured on the screen rather than here: this only records
			 * which line is load-bearing so that removing it is a decision.
			 */
			&& str_contains(
				(string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/fields.css' ),
				'height: calc( 24px + 2 * ( 16px - var( --ax-field-border ) ) );'
			)
	);

	/*
	 * An address may not say which zone it is in, and a dropdown always shows something. So there is
	 * an answer for not having one, first, before any zone -- otherwise an address that names none
	 * would read as being in whichever zone happens to sort first, and somebody who saved that page
	 * would have said it. Choosing it again takes the answer off the Card rather than writing an
	 * empty one, which is also the only reading the store accepts.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'an address may not say which timezone it is in, and saying so takes the answer off the Card',
		str_starts_with( $ax_ct_tz_markup, '<option value="">' )
			// One of them, and only one: Core carries its own blank prompt, which reads as an
			// instruction rather than an answer somebody chose.
			&& 1 === preg_match_all( '/<option[^>]*value=""/', $ax_ct_tz_markup )
			&& ! str_contains( $ax_ct_tz_markup, 'Select a city' )
			&& str_contains( $ax_ct_cb_editor, "return withKey( each, 'timeZone', value );" )
			// An emptied key is gone, not present and blank: the store refuses a zone that is no zone.
			&& str_contains( $ax_ct_cb_editor, "if ( '' === value || undefined === value || null === value ) {" )
			&& true === axismundi_contacts_validate_card_values( array( 'addresses' => array( 'adr-tz' => array( 'countryCode' => 'KR' ) ) ) )
			&& is_wp_error( axismundi_contacts_validate_card_values( array( 'addresses' => array( 'adr-tz' => array( 'timeZone' => '' ) ) ) ) )
	);

	/*
	 * A Korean address with everything in it: a region that opens rather than closes, a building, a
	 * floor and a room, three different separators, and a subdistrict in brackets. Twelve parts, of
	 * which three are the punctuation between the others.
	 *
	 * Built through the screen part by part -- adding each in the order it is read appends it in that
	 * order, so nothing had to be dragged. The store keeps every one of them.
	 */
	$ax_ct_kr = array(
		'countryCode'      => 'KR',
		'components'       => array(
			array( 'kind' => 'region', 'value' => "\xeb\xb6\x80\xec\x82\xb0\xea\xb4\x91\xec\x97\xad\xec\x8b\x9c" ),
			array( 'kind' => 'district', 'value' => "\xeb\x82\xa8\xea\xb5\xac" ),
			array( 'kind' => 'name', 'value' => "\xeb\x8f\x99\xeb\xaa\x85\xeb\xa1\x9c" ),
			array( 'kind' => 'number', 'value' => '26' ),
			array( 'kind' => 'separator', 'value' => ', ' ),
			array( 'kind' => 'building', 'value' => "102\xeb\x8f\x99" ),
			array( 'kind' => 'floor', 'value' => "7\xec\xb8\xb5" ),
			array( 'kind' => 'room', 'value' => "706\xed\x98\xb8" ),
			array( 'kind' => 'separator', 'value' => ' (' ),
			array( 'kind' => 'subdistrict', 'value' => "\xec\x9a\xa9\xeb\x8b\xb9\xeb\x8f\x99" ),
			array( 'kind' => 'separator', 'value' => ')' ),
			array( 'kind' => 'postcode', 'value' => '48559' ),
		),
		'defaultSeparator' => ' ',
		'isOrdered'        => true,
		'full'             => "\xeb\xb6\x80\xec\x82\xb0\xea\xb4\x91\xec\x97\xad\xec\x8b\x9c \xeb\x82\xa8\xea\xb5\xac \xeb\x8f\x99\xeb\xaa\x85\xeb\xa1\x9c 26, 102\xeb\x8f\x99 706\xed\x98\xb8 (\xec\x9a\xa9\xeb\x8b\xb9\xeb\x8f\x99)",
	);
	$ax_ct_kr_id   = axismundi_contacts_save_card( $ax_ct_book_id, array( '@type' => 'Card', 'name' => array( 'full' => 'Busan' ), 'addresses' => array( 'adr-kr' => $ax_ct_kr ) ) );
	$ax_ct_kr_key  = is_wp_error( $ax_ct_kr_id ) ? 0 : (int) $ax_ct_kr_id;
	$ax_ct_loose[] = $ax_ct_kr_key;
	$ax_ct_kr_back = (array) ( axismundi_contacts_card_document( $ax_ct_kr_key )['addresses']['adr-kr'] ?? array() );
	ax_ct_assert(
		$ax_ct_results,
		'an address of twelve parts, three of them punctuation, keeps all twelve and their order',
		array_column( $ax_ct_kr['components'], 'kind' ) === array_column( (array) $ax_ct_kr_back['components'], 'kind' )
			&& array_column( $ax_ct_kr['components'], 'value' ) === array_column( (array) $ax_ct_kr_back['components'], 'value' )
			// Three separators, each saying something different.
			&& 3 === count( array_filter( (array) $ax_ct_kr_back['components'], static fn( $c ) : bool => 'separator' === $c['kind'] ) )
			// A single space is a separator somebody chose, not an empty one.
			&& ' ' === (string) $ax_ct_kr_back['defaultSeparator']
			&& $ax_ct_kr['full'] === (string) $ax_ct_kr_back['full']
			&& 'KR' === (string) $ax_ct_kr_back['countryCode']
	);
	/*
	 * Moving a part rebuilds the list as it stands rather than as it stood when the row was drawn. A
	 * part added a moment ago is in one and not the other, and rebuilding from the older copy drops
	 * it -- a drag that quietly deletes something somebody typed. Every collection that can be
	 * rearranged had it.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'moving something rebuilds the list that exists, not the one that was drawn',
		// Two lists of parts and one of accounts, each guarded against a position that is not there.
		2 === substr_count( $ax_ct_cb_editor, 'undefined === list[ dragging ]' )
			&& 1 === substr_count( $ax_ct_cb_editor, 'undefined === ids[ dragging ]' )
			&& str_contains( $ax_ct_cb_editor, "var list = ( latest.current.components || [] ).slice();" )
			&& str_contains( $ax_ct_cb_editor, 'var ids = orderedByPreference( latest.current );' )
			&& str_contains( $ax_ct_cb_editor, 'order = orderedByPreference( latest.current );' )
			// And nothing rearranges from a render's own copy any more.
			&& ! str_contains( $ax_ct_cb_editor, 'var list = components.slice();' )
	);
	// The address editor: the parts, and the tick that asks whether there is a line as well.
	$ax_ct_cb_parts = substr(
		$ax_ct_cb_editor,
		(int) strpos( $ax_ct_cb_editor, 'function AddressParts' ),
		(int) strpos( $ax_ct_cb_editor, 'function Addresses' ) - (int) strpos( $ax_ct_cb_editor, 'function AddressParts' )
	);
	/*
	 * The address written out as one line is a way of saying where somewhere is, not a summary of the
	 * parts. So it is read where somebody would write it -- above them, the way the name's full form
	 * sits above its parts -- and the question of whether to ask for one at all sits in the fold with
	 * the other things about this address rather than in the middle of it.
	 *
	 * Two different things in two different places, which is why the tick is its own component: the
	 * field is part of the address and the tick is a question about it.
	 *
	 * It has one writer. The line used to be drawn twice -- once in the address row and once in the
	 * localization editor -- and two fields writing one key is how they end up disagreeing about what
	 * it says.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'the written-out address is one field, read above the parts, and asked for from the fold',
		1 === substr_count( $ax_ct_cb_editor, "className: 'ax-ce-address__full'," )
			// Above the parts: the field itself is drawn before the rows it is taken apart into.
			&& strpos( $ax_ct_cb_parts, "className: 'ax-ce-address__full'," ) < strpos( $ax_ct_cb_parts, 'rows.map( function ( row ) {' )
			// And the tick is in the fold, before the two facts a machine wants and a person rarely does.
			&& strpos( $ax_ct_cb_editor, 'el( FullAddressToggle, {' ) > strpos( $ax_ct_cb_editor, "el( 'summary', null, __( 'More about this address', 'axismundi-contacts' ) )," )
			&& strpos( $ax_ct_cb_editor, 'el( FullAddressToggle, {' ) < strpos( $ax_ct_cb_editor, "label: __( 'Coordinates', 'axismundi-contacts' )," )
			/*
			 * Ticked and empty is somebody about to write one, so what is ticked is held by whoever
			 * draws the row. Read back from the value it would untick itself under the cursor.
			 */
			&& str_contains( $ax_ct_cb_editor, 'return undefined === lines[ row.key ] ? undefined !== row.entry.full : lines[ row.key ];' )
			// And turning it off throws an answer away, so when there is one to lose it asks first.
			&& str_contains( $ax_ct_cb_editor, 'if ( props.hasLine ) {' )
			&& str_contains( $ax_ct_cb_editor, "__( 'Turning this off removes the address as it was written out.', 'axismundi-contacts' )" )
			/*
			 * A browser fills in where somebody lives, which is an answer in their own language. The
			 * localization editor asks for a translation of it, and autofilling that would put the
			 * same address under a heading saying it is in another language.
			 */
			&& 1 === substr_count( $ax_ct_cb_editor, "fullInputProps: { autoComplete: 'street-address' }," )
	);

	/*
	 * And the country is asked outright rather than behind the same tickbox. Both are optional to the
	 * standard; they are not the same kind of optional to somebody filling this in. Which country an
	 * address is read in is a question nearly every address has an answer to and the one that says how
	 * to read the rest, while a line written out is a second way of saying what the parts already say.
	 *
	 * Optional still means optional: nothing marks it required and nothing fills one in. An address
	 * with no country is a complete address, and a country nobody stated is a fact about it rather
	 * than a gap to close.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'the country is asked outright, is never required, and is never filled in on somebody’s behalf',
		str_contains( $ax_ct_cb_editor, "label: __( 'Country', 'axismundi-contacts' )," )
			// Drawn whenever there is a registry to draw it from, and behind nothing else.
			&& str_contains( $ax_ct_cb_editor, 'countries.length' )
			/*
			 * These four answers are opt-in. Full address has its own toggle because its field sits above
			 * the parts; coordinates and time zone share the same toggle and removal contract in the fold.
			 */
			&& str_contains( $ax_ct_cb_parts, "__( 'Default separator', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_cb_editor, 'function FullAddressToggle( props )' )
			&& 2 === substr_count( $ax_ct_cb_editor, 'el( AddressPropertyToggle, {' )
			&& str_contains( $ax_ct_cb_editor, "label: __( 'Coordinates', 'axismundi-contacts' )," )
			&& str_contains( $ax_ct_cb_editor, "label: __( 'Time zone', 'axismundi-contacts' )," )
			&& str_contains( $ax_ct_cb_editor, "removeMessage: __( 'Turning this off removes the coordinates for this address.', 'axismundi-contacts' )," )
			&& str_contains( $ax_ct_cb_editor, "removeMessage: __( 'Turning this off removes the time zone for this address.', 'axismundi-contacts' )," )
			&& true === axismundi_contacts_validate_card_values(
				array( 'addresses' => array( 'adr-n' => array( 'components' => array( array( 'kind' => 'name', 'value' => 'Oak St' ) ) ) ) )
			)
			// And the picker takes a code the registry does not know rather than deciding it is not one.
			&& str_contains( $ax_ct_cb_editor, "allowFree: true," )
	);

	$ax_ct_cb_address_toggle = substr(
		$ax_ct_cb_editor,
		(int) strpos( $ax_ct_cb_editor, 'function AddressPropertyToggle' ),
		(int) strpos( $ax_ct_cb_editor, 'function Addresses' ) - (int) strpos( $ax_ct_cb_editor, 'function AddressPropertyToggle' )
	);
	ax_ct_assert(
		$ax_ct_results,
		'an optional address value can be removed or kept',
		str_contains( $ax_ct_cb_address_toggle, "'p',\n\t\t\t\t\t\tnull,\n\t\t\t\t\t\tel(" )
			&& str_contains( $ax_ct_cb_address_toggle, "__( 'Remove it', 'axismundi-contacts' )" )
			&& str_contains( $ax_ct_cb_address_toggle, "__( 'Keep it', 'axismundi-contacts' )" )
	);

	/*
	 * One line, in a box that holds one line. A written-out address is a string and this asks for it as
	 * one, which leaves the case of a line that arrived with a break in it: a single-line box drops the
	 * break, so what it holds differs from what the Card says without anybody having touched it.
	 *
	 * That difference used to be read as somebody typing. The watcher that notices a browser filling a
	 * field in compares what the box holds against what was drawn, and a break is exactly such a
	 * difference -- so clicking into the field, with no key pressed, wrote `남구\n동명로` back as
	 * `남구동명로`. Two ends of the same fix: the watcher no longer counts a break a box removed as a
	 * change, and the field shows the break as the space it stands for, so the words either side of it
	 * do not run together. What is stored keeps its break until somebody edits the line.
	 */
	$ax_ct_cb_fields = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/fields.js' );
	ax_ct_assert(
		$ax_ct_results,
		'a line break a single-line box removes is the box, not somebody typing, and is not written back',
		// One line: the field is a text field, and nothing in the address editor is a box that grows.
		! str_contains( $ax_ct_cb_parts, 'el( Textarea, {' )
			&& str_contains( $ax_ct_cb_parts, "className: 'ax-ce-address__full'," )
			// Shown as the space it stands for rather than as nothing.
			&& str_contains( $ax_ct_cb_parts, "( address.full || '' ).replace( /[\\r\\n]+/g, ' ' )," )
			// And the watcher compares against what a box of that kind can actually hold.
			&& str_contains( $ax_ct_cb_fields, "if ( 'TEXTAREA' !== node.tagName ) {" )
			&& str_contains( $ax_ct_cb_fields, "shown = shown.replace( /[\\r\\n]+/g, '' );" )
			// A line with a break in it is still a line the store keeps as it is.
			&& true === axismundi_contacts_validate_card_values(
				array( 'addresses' => array( 'adr-b' => array( 'full' => "ë¶ì°
ë¨êµ¬" ) ) )
			)
	);

	/*
	 * And a line on its own is a whole address. The standard asks for any one of the parts, the line,
	 * a country code, coordinates or a time zone -- so `{"full": "부산광역시 남구 동명로 26"}` is an
	 * address and nothing may require the parts, or a country, before it will accept one. A screen
	 * that marked the country required would be inventing a rule the standard does not have, and a
	 * person who does not know it would have to make one up.
	 */
	$ax_ct_line       = "ë¶ì°ê´ì­ì ë¨êµ¬ ëëªë¡ 26";
	$ax_ct_line_id    = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array( '@type' => 'Card', 'name' => array( 'full' => 'Line only' ), 'addresses' => array( 'adr-line' => array( 'full' => $ax_ct_line ) ) )
	);
	$ax_ct_line_key   = is_wp_error( $ax_ct_line_id ) ? 0 : (int) $ax_ct_line_id;
	$ax_ct_loose[]    = $ax_ct_line_key;
	$ax_ct_line_back  = (array) ( axismundi_contacts_card_document( $ax_ct_line_key )['addresses']['adr-line'] ?? array() );
	ax_ct_assert(
		$ax_ct_results,
		'an address written out and nothing else is a whole address, with no country asked of it',
		true === axismundi_contacts_validate_card_values( array( 'addresses' => array( 'adr-x' => array( 'full' => $ax_ct_line ) ) ) )
			&& array( 'full' => $ax_ct_line ) === $ax_ct_line_back
	);

	/*
	 * And the list a person actually leaves behind after moving things around. This one was not
	 * written here first: it was built on the screen -- a separator and a building added, the
	 * separator dragged above the building, two more separators and a subdistrict added between
	 * them, the building dragged past the subdistrict and back again, and then every one of the six
	 * values retyped -- and this is what the screen held when it was done.
	 *
	 * What makes it worth keeping is the shape rather than the count. The separator at position one
	 * is only there because somebody dragged it there, so a list that survives being written in
	 * order says nothing about this one. The comparison is the strings themselves: `", "`, `" ("`,
	 * `")"` and `102동` are what a reader sees, and a check on kinds alone would pass while every
	 * separator said the wrong thing.
	 */
	$ax_ct_moved      = array(
		array( 'kind' => 'name', 'value' => "íí¤ëë¡ 152" ),
		array( 'kind' => 'separator', 'value' => ', ' ),
		array( 'kind' => 'building', 'value' => "102ë" ),
		array( 'kind' => 'separator', 'value' => ' (' ),
		array( 'kind' => 'subdistrict', 'value' => "ì­ì¼1ë" ),
		array( 'kind' => 'separator', 'value' => ')' ),
	);
	$ax_ct_moved_id   = axismundi_contacts_save_card(
		$ax_ct_book_id,
		array(
			'@type'     => 'Card',
			'name'      => array( 'full' => 'Dragged' ),
			'addresses' => array( 'adr-a' => array( 'components' => $ax_ct_moved, 'isOrdered' => true, 'countryCode' => 'KR' ) ),
		)
	);
	$ax_ct_moved_key  = is_wp_error( $ax_ct_moved_id ) ? 0 : (int) $ax_ct_moved_id;
	$ax_ct_loose[]    = $ax_ct_moved_key;
	$ax_ct_moved_back = (array) ( axismundi_contacts_card_document( $ax_ct_moved_key )['addresses']['adr-a'] ?? array() );
	ax_ct_assert(
		$ax_ct_results,
		'an address that was rearranged and then retyped keeps every string, in the order it was left',
		$ax_ct_moved === (array) ( $ax_ct_moved_back['components'] ?? array() )
			// The entry keeps its own name through all of it: a moved part is not a new address.
			&& array_key_exists( 'adr-a', (array) ( axismundi_contacts_card_document( $ax_ct_moved_key )['addresses'] ?? array() ) )
			&& true === ( $ax_ct_moved_back['isOrdered'] ?? null )
	);

	/*
	 * And a part is never refused because another of its kind is waiting to be typed. An address may
	 * want three separators, two of them open at once, and a button going grey without saying why
	 * reads as "you cannot have another one of those".
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a second part of the same kind is never refused for the first one being empty',
		str_contains( $ax_ct_cb_editor, "disabled: 'separator' === kind && ! ordered," )
	);

	/*
	 * Which countries an address may be in comes from the plugin whose subject that is. It was coming
	 * from the telephone numbering rules, which is a list of places with a phone plan -- so Antarctica,
	 * Pitcairn and the French Southern Territories were missing from a list of places somebody can
	 * live, and removing the phone library would have taken the address country picker with it.
	 *
	 * The phone row still asks the phone rules, because which countries have numbering plans is
	 * exactly what they know.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'where an address is comes from the geography, and where a number is from the numbering plans',
		str_contains( $ax_ct_lg_php, "function_exists( 'axismundi_geodata_country_options' )" )
			&& str_contains( $ax_ct_cb_editor, 'function addressCountries()' )
			&& str_contains( $ax_ct_cb_editor, 'var countries = addressCountries();' )
			// The phone row is the only thing left asking the numbering plans for a list of places.
			&& 1 === substr_count( $ax_ct_cb_editor, 'regionOptions( true )' )
			&& ! str_contains( $ax_ct_cb_editor, 'regionOptions( false )' )
			// And it works without the geography: it stops suggesting rather than stops working.
			&& str_contains( $ax_ct_cb_editor, '( config.countries || [] ).map(' )
			&& str_contains( $ax_ct_cb_editor, 'allowFree: true' )
	);
	// Which is a list of every country there is, not of every country with a telephone.
	ax_ct_assert(
		$ax_ct_results,
		'and it is every country there is, including the ones nobody dials',
		function_exists( 'axismundi_geodata_country_options' )
			&& 249 === count( axismundi_geodata_country_options() )
			&& axismundi_geodata_is_country_code( 'AQ' )
			&& axismundi_geodata_is_country_code( 'PN' )
	);

	ax_ct_assert(
		$ax_ct_results,
		'this plugin stores address books and imitates neither the Actor registry nor its profiles',
		! function_exists( 'axismundi_contacts_set_actor_profile' )
			&& ! function_exists( 'axismundi_contacts_create_actor' )
			&& axismundi_contacts_has_actors()
	);
} finally {
	global $wpdb;
	foreach ( array_unique( array_filter( $ax_ct_loose ) ) as $ax_ct_loose_card ) {
		axismundi_contacts_delete_card( (int) $ax_ct_loose_card );
	}
	foreach ( array_unique( array_filter( $ax_ct_books ) ) as $ax_ct_book_row ) {
		foreach ( axismundi_contacts_cards_in_book( (int) $ax_ct_book_row, 500 ) as $ax_ct_row ) {
			axismundi_contacts_delete_card( (int) $ax_ct_row['id'] );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_contacts_books_table(), array( 'id' => (int) $ax_ct_book_row ), array( '%d' ) );
	}
	foreach ( array_unique( $ax_ct_users ) as $ax_ct_user_id ) {
		wp_delete_user( (int) $ax_ct_user_id );
	}
	/*
	 * And the Actor rows themselves. `wp_delete_user()` tombstones them, which is what a site wants
	 * and not what a fixture wants: left standing they accumulate one run at a time, and every one of
	 * them is a Person whose account is gone -- which is exactly what the orphan sweep is for, so this
	 * file would be manufacturing the thing that audit has to find.
	 */
	foreach ( array_unique( array_filter( (array) ( $GLOBALS['ax_ct_made_actors'] ?? array() ) ) ) as $ax_ct_made ) {
		axismundi_contacts_purge_actor( (int) $ax_ct_made );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $ax_ct_made ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $ax_ct_made ), array( '%d' ) );
	}
}

$ax_ct_failures = count( array_filter( $ax_ct_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ct_results ), $ax_ct_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ct_failures > 0 ? 1 : 0 );
}
exit( $ax_ct_failures > 0 ? 1 : 0 );
