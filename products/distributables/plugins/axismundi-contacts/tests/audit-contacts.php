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
require_once dirname( __DIR__ ) . '/includes/name-editor.php';
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
	 * A store nobody can write to is what the last few slices kept producing, so the screen is part of
	 * this one. Rendered here for the reason the Actors forms are: a template whose PHP block closes
	 * early prints its own source, and no amount of correct storage underneath shows that.
	 */
	wp_set_current_user( (int) $ax_ct_owner->get_local_user_id() );
	ob_start();
	axismundi_contacts_card_editor( $ax_ct_book_id, $ax_ct_card, $ax_ct_card );
	$ax_ct_form = (string) ob_get_clean();
	ax_ct_assert(
		$ax_ct_results,
		'the card is editable on a screen that renders itself rather than its own source',
		str_contains( $ax_ct_form, 'name="primary_name[full]"' )
			&& str_contains( $ax_ct_form, 'name="emails_value[]"' )
			&& str_contains( $ax_ct_form, 'name="phones_value[]"' )
			&& str_contains( $ax_ct_form, '_wpnonce' )
			&& ! str_contains( $ax_ct_form, '<?php' )
	);
	/*
	 * The entry key travels with the row. Regenerating it on every save would detach provenance from
	 * the value it was written for, which is the whole reason provenance is keyed by pointer.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'each entry keeps the key its provenance is recorded against',
		str_contains( $ax_ct_form, 'name="emails_key[]" value="work"' )
			&& str_contains( $ax_ct_form, 'name="emails_key[]" value="home"' )
	);
	/*
	 * One editor for everybody. The owner's card and a card about somebody else are the same kind of
	 * thing, so a separate "my details" form would drift from the general one and end up supporting
	 * different fields.
	 */
	ob_start();
	axismundi_contacts_card_editor( $ax_ct_book_id, $ax_ct_phone_card, $ax_ct_card );
	$ax_ct_other_form = (string) ob_get_clean();
	ax_ct_assert(
		$ax_ct_results,
		'a card about somebody else uses the same form as the owner card',
		str_contains( $ax_ct_other_form, 'name="primary_name[full]"' )
			&& str_contains( $ax_ct_other_form, 'name="phones_value[]"' )
			&& str_contains( $ax_ct_other_form, 'axismundi_contacts_save_card' )
	);
	/*
	 * A contact has as many numbers as it has, so the form grows. Fixed rows would turn the model's
	 * multiple values into a limit nobody chose -- and the blank row means it still grows with the
	 * script switched off.
	 */
	// The label control sits on every row, and the address and note fields are on the same form.
	ax_ct_assert(
		$ax_ct_results,
		'each row carries its own label control, and addresses and a note are edited here too',
		str_contains( $ax_ct_form, 'name="phones_preset[]"' )
			&& str_contains( $ax_ct_form, 'name="phones_label[]"' )
			&& str_contains( $ax_ct_form, 'name="addresses_value[]"' )
			&& str_contains( $ax_ct_form, 'name="note"' )
	);
	ax_ct_assert(
		$ax_ct_results,
		'a repeating field offers a way to add another and always leaves one row blank',
		str_contains( $ax_ct_form, 'data-ax-contacts-add="ax-contacts-emails"' )
			&& str_contains( $ax_ct_form, 'name="emails_key[]" value=""' )
	);
	// A card id from another book is refused however the URL asks for it.
	ob_start();
	axismundi_contacts_card_editor( (int) $ax_ct_other_book['id'], $ax_ct_card, 0 );
	$ax_ct_wrong_book = (string) ob_get_clean();
	ax_ct_assert(
		$ax_ct_results,
		'a card belonging to another book cannot be opened by asking for its id',
		str_contains( $ax_ct_wrong_book, 'not in this address book' )
			&& ! str_contains( $ax_ct_wrong_book, 'name="primary_name[full]"' )
	);
	// And an imported value says so, beside the field, before somebody wonders why it changed back.
	ax_ct_assert(
		$ax_ct_results,
		'a value that came from a sync is marked as such on the screen',
		str_contains( $ax_ct_form, 'from google' )
	);
	/*
	 * This screen edits contact facts and never the Actor. A public name and a private phone number
	 * arriving under one save button is how the second gets published.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'and it does not edit the Actor whose book this is',
		! str_contains( $ax_ct_form, 'name="display_name"' )
			&& ! str_contains( $ax_ct_form, 'name="summary"' )
			&& ! str_contains( $ax_ct_form, 'axismundi_actors_' )
	);

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
	 * The address goes where the standard puts it: an `onlineServices` entry whose `uri` can be
	 * dereferenced and whose `user` is the handle a person reads. Not a field this project invented,
	 * so it survives an export.
	 */
	$ax_ct_service = $ax_ct_seeded_doc['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ] ?? array();
	ax_ct_assert(
		$ax_ct_results,
		'the fediverse address is an ordinary online service entry, with the URI and the handle',
		'Axismundi' === (string) ( $ax_ct_service['service'] ?? '' )
			&& $ax_ct_fresh->get_uri() === (string) ( $ax_ct_service['uri'] ?? '' )
			&& str_starts_with( (string) ( $ax_ct_service['user'] ?? '' ), '@' )
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
	ax_ct_assert(
		$ax_ct_results,
		'a card filed into two books is one record seen from both, never a copy',
		true === axismundi_contacts_add_card_to_book( $ax_ct_card, (int) $ax_ct_second )
			&& array( $ax_ct_card ) === array_map( static fn( array $r ) : int => (int) $r['id'], axismundi_contacts_cards_in_book( (int) $ax_ct_second ) )
			&& 2 === count( axismundi_contacts_card_books( $ax_ct_card ) )
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
	 * Contributors add what they own and may not change what the Card is. A calendar arriving with a
	 * different uid would be published as a different contact rather than as a broken field.
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
		'a contributor may add what it owns and may not change which card this is',
		! is_wp_error( $ax_ct_enriched )
			&& $ax_ct_seed_uid === (string) ( $ax_ct_enriched['uid'] ?? '' )
			&& 'individual' === (string) ( $ax_ct_enriched['kind'] ?? '' )
			&& isset( $ax_ct_enriched['calendars'] )
	);
	/*
	 * Serving is gated on the audience as well as on the profile being published. The route this
	 * replaced handed out a name and a kind; this document carries whatever is on somebody's card,
	 * which is telephone numbers and home addresses.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a card is served only when its Actor shares it publicly, not merely because the profile is public',
		! axismundi_contacts_jscontact_is_public( $ax_ct_fresh )
			&& 'off' === axismundi_contacts_profile_sharing( (int) $ax_ct_fresh->get_identity_id() )
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
	 * Contacts holds the Card, so the Card's localization is the answer. What Actors has for the same
	 * tag is a display name it happens to keep, and letting it win would mean an edit made in Contacts
	 * vanished on the next render.
	 */
	axismundi_actors_set_text( (int) $ax_ct_fresh->get_identity_id(), 'name', 'en', 'Jiwoon from Actors' );
	$ax_ct_rendered = axismundi_contacts_jscontact_card( axismundi_actors_get_by_identity( (int) $ax_ct_fresh->get_identity_id() ) );
	ax_ct_assert(
		$ax_ct_results,
		'where the card has a name for a language, that is the one published',
		! is_wp_error( $ax_ct_rendered )
			&& 'Trump' === (string) ( axismundi_contacts_localized_name( $ax_ct_rendered, 'en' )['full'] ?? '' )
	);
	/*
	 * And where it does not, another domain may still contribute one. Precedence is per tag: holding a
	 * romanisation says nothing about what to publish in Japanese.
	 */
	axismundi_actors_set_text( (int) $ax_ct_fresh->get_identity_id(), 'name', 'de', 'Jiwoon Kim' );
	$ax_ct_rendered = axismundi_contacts_jscontact_card( axismundi_actors_get_by_identity( (int) $ax_ct_fresh->get_identity_id() ) );
	ax_ct_assert(
		$ax_ct_results,
		'and where it has none, a contributor may still supply one, tag by tag',
		! is_wp_error( $ax_ct_rendered )
			&& 'Jiwoon Kim' === (string) ( axismundi_contacts_localized_name( $ax_ct_rendered, 'de' )['full'] ?? '' )
			&& 'Trump' === (string) ( axismundi_contacts_localized_name( $ax_ct_rendered, 'en' )['full'] ?? '' )
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
	 * A save from the form replaces the name and nothing else. A localization may carry paths for
	 * fields this screen knows nothing about, and rewriting the whole patch would throw them away --
	 * the value below is a nickname somebody localized, which no name editor should be able to delete.
	 */
	$ax_ct_form_card = axismundi_contacts_set_localized_name( array( '@type' => 'Card' ), 'en', array( 'full' => 'Trump' ) );
	$ax_ct_form_card['localizations']['en']['nicknames/n1/name'] = 'Don';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- fixture standing in for a verified post.
	$_POST = array(
		'localized'             => array( array( 'tag' => 'en', 'full' => 'Donald' ) ),
		'localized_detail_0'    => array( 'order' => 'given-family' ),
	);
	$ax_ct_saved_form = axismundi_contacts_localized_names_from_request( $ax_ct_form_card );
	ax_ct_assert(
		$ax_ct_results,
		'editing a localized name changes the name and leaves that language\u0027s other localizations alone',
		'Donald' === (string) ( axismundi_contacts_localized_name( $ax_ct_saved_form, 'en' )['full'] ?? '' )
			&& 'Don' === (string) ( $ax_ct_saved_form['localizations']['en']['nicknames/n1/name'] ?? '' )
	);
	/*
	 * And a name written out in full gains no components from being edited. The screen may not decide
	 * which half of a name is the surname either -- that is the same rule the store keeps.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a name typed as one string gains no components from passing through the form',
		! isset( axismundi_contacts_localized_name( $ax_ct_saved_form, 'en' )['components'] )
	);
	/*
	 * Removing the name leaves the tag, because something else is still localized under it. The tag
	 * only goes when nothing is left, which is what lets a contributor fill the gap again.
	 */
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- fixture standing in for a verified post.
	$_POST = array( 'localized' => array( array( 'tag' => 'en', 'full' => 'Donald', 'remove' => '1' ) ) );
	$ax_ct_removed = axismundi_contacts_localized_names_from_request( $ax_ct_saved_form );
	ax_ct_assert(
		$ax_ct_results,
		'removing a writing removes the name and keeps a tag that still localizes something else',
		array() === axismundi_contacts_localized_name( $ax_ct_removed, 'en' )
			&& 'Don' === (string) ( $ax_ct_removed['localizations']['en']['nicknames/n1/name'] ?? '' )
	);
	/*
	 * Typing components is how components appear, and the layout chosen is the order they are stored
	 * in -- said to be ordered, because the sequence is one somebody picked rather than one a reader
	 * should reassemble its own way.
	 */
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- fixture standing in for a verified post.
	$_POST = array( 'primary_name' => array( 'full' => 'Kim Jiwoon', 'order' => 'family-given', 'surname' => 'Kim', 'given' => 'Jiwoon' ) );
	$ax_ct_typed = axismundi_contacts_name_from_request( 'primary_name' );
	$ax_ct_typed_kinds = array();
	foreach ( (array) ( $ax_ct_typed['components'] ?? array() ) as $ax_ct_c ) {
		$ax_ct_typed_kinds[] = (string) $ax_ct_c['kind'];
	}
	ax_ct_assert(
		$ax_ct_results,
		'components appear where somebody typed them, in the layout they chose',
		array( 'surname', 'given' ) === $ax_ct_typed_kinds && true === ( $ax_ct_typed['isOrdered'] ?? false )
	);
	$_POST = array();

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
	 * The gap this closes: the name bindings offer one writing per tag and read `full` to do it, so a
	 * card holding components and no written-out form offered that language nothing. Nothing noticed
	 * while Actors still assembled the name itself; the moment it stops, the Actor goes quiet in that
	 * language. So the card is completed on the way in, once, rather than by each reader.
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
		'a card stored with components and no written-out name is given one, so a locale has something to bind to',
		'Jiwoon Kim' === (string) ( $ax_ct_fmt['name']['full'] ?? '' )
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
		'an empty default separator assembles with nothing between the parts, and stays on the card',
		'김지운' === (string) ( $ax_ct_compact['name']['full'] ?? '' )
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
		'a localized name with components and no written-out form is completed too',
		'김지운' === (string) ( axismundi_contacts_localized_name( $ax_ct_loc, 'ko-KR' )['full'] ?? '' )
	);

	/*
	 * The editor round trip, which is where the separator could be lost. A layout it never offered --
	 * a hyphen an import brought -- is not flattened to a space because somebody opened the screen and
	 * saved it, and the compact layout it does offer survives being read back out of the card.
	 */
	$_POST             = array( 'ax_ct_name' => array( 'full' => '', 'order' => 'family-given-compact', 'surname' => '김', 'given' => '지운' ) );
	$ax_ct_editor_name = axismundi_contacts_name_from_request( 'ax_ct_name' );
	ax_ct_assert(
		$ax_ct_results,
		'the editor states a compact layout as an empty default separator, and reads it back as the same layout',
		'' === (string) ( $ax_ct_editor_name['defaultSeparator'] ?? 'unset' )
			&& 'family-given-compact' === axismundi_contacts_name_order( $ax_ct_editor_name )
			&& '김지운' === axismundi_contacts_assemble_name( $ax_ct_editor_name )
	);

	$_POST      = array( 'ax_ct_name' => array( 'full' => '', 'order' => 'given-family', 'given' => 'Jiwoon', 'surname' => 'Kim' ) );
	$ax_ct_kept = axismundi_contacts_name_from_request( 'ax_ct_name', array( '@type' => 'Name', 'defaultSeparator' => '-' ) );
	ax_ct_assert(
		$ax_ct_results,
		'a separator the editor cannot show is left where it is rather than flattened to a space',
		'-' === (string) ( $ax_ct_kept['defaultSeparator'] ?? '' )
			&& 'Jiwoon-Kim' === axismundi_contacts_assemble_name( $ax_ct_kept )
	);
	$_POST = array();

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

	// -- the copy Actors was left holding ---------------------------------------------------------------------

	/*
	 * Both sides were editable between the migration that moved the structured name and the removal of
	 * the screen that edited it here. So the two copies may now differ, and nothing in either says
	 * which was typed last. What follows pins that no code decides that.
	 */

	/*
	 * The columns are put back for the length of this section. A site that has finished upgrading no
	 * longer has them, and the reconciliation is precisely the code that runs on one that has not --
	 * so checking it means arranging the state it exists for. The schema is restored at the end.
	 */
	global $wpdb;
	foreach ( array( 'given', 'given2', 'surname', 'surname2' ) as $ax_ct_legacy_column ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture schema check.
		$ax_ct_profile_columns = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . axismundi_actors_profile_table() );
		if ( ! in_array( $ax_ct_legacy_column, $ax_ct_profile_columns, true ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture schema setup.
			$wpdb->query( 'ALTER TABLE ' . axismundi_actors_profile_table() . " ADD COLUMN {$ax_ct_legacy_column} varchar(191) NOT NULL default ''" );
		}
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture schema check.
	$ax_ct_profile_columns = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . axismundi_actors_profile_table() );
	if ( ! in_array( 'display_order', $ax_ct_profile_columns, true ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture schema setup.
		$wpdb->query( 'ALTER TABLE ' . axismundi_actors_profile_table() . " ADD COLUMN display_order varchar(32) NOT NULL default 'given-family'" );
	}
	// The schema just changed under this request, so the answer remembered from before it is stale.
	axismundi_contacts_legacy_name_columns_present( true );
	$ax_ct_legacy_restored = true;

	/** One Actor with a profile card and a structured name still on the Actor side. */
	$ax_ct_legacy_actor = static function ( array &$users, array &$loose, array $parts ) : array {
		global $wpdb;
		$actor = ax_ct_actor( $users );
		$sid   = (int) $actor->get_identity_id();
		$card  = axismundi_contacts_create_profile_card( $sid );
		$card  = is_wp_error( $card ) ? 0 : (int) $card;
		$loose[] = $card;
		/*
		 * Written straight into the columns, because nothing offers to write them any more -- and
		 * replaced rather than updated, because an Actor that has only ever had components has no
		 * profile row of its own. That is exactly the state a site mid-upgrade is in.
		 */
		$row = array_merge(
			axismundi_actors_person_profile( $sid ),
			array( 'structured_name_language' => 'en-US' ),
			$parts,
			array( 'identity_id' => $sid, 'updated_at' => current_time( 'mysql', true ) )
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture write into the Actors table.
		$wpdb->replace( axismundi_actors_profile_table(), $row );
		return array( $sid, $card );
	};

	list( $ax_ct_lg_sid, $ax_ct_lg_card ) = $ax_ct_legacy_actor(
		$ax_ct_users,
		$ax_ct_loose,
		array( 'given' => 'Jiwoon', 'surname' => 'Kim', 'display_order' => 'given-family' )
	);
	// The card is made with a written-out name and no components, which is every profile card's start.
	$ax_ct_lg_doc = axismundi_contacts_card_document( $ax_ct_lg_card );
	$ax_ct_lg_doc['name'] = array( '@type' => 'Name', 'full' => 'Jiwoon Kim' );
	axismundi_contacts_save_card_for_owner( $ax_ct_lg_sid, $ax_ct_lg_doc, $ax_ct_lg_card );
	ax_ct_assert(
		$ax_ct_results,
		'a card with no components of its own takes the ones the Actor was holding',
		'adoptable' === axismundi_contacts_legacy_name_state( $ax_ct_lg_sid )
			&& axismundi_contacts_reconcile_legacy_names() >= 1
			&& array( 'given' => 'Jiwoon', 'surname' => 'Kim' ) === axismundi_contacts_name_actor_parts(
				axismundi_contacts_card_document( $ax_ct_lg_card )['name'] ?? array()
			)
	);

	ax_ct_assert(
		$ax_ct_results,
		'and once they agree there is nothing left to carry, however often it runs',
		'settled' === axismundi_contacts_legacy_name_state( $ax_ct_lg_sid )
			&& 0 === axismundi_contacts_reconcile_legacy_names()
			&& 'Jiwoon Kim' === (string) ( axismundi_contacts_card_document( $ax_ct_lg_card )['name']['full'] ?? '' )
	);

	/*
	 * The state this whole file exists for. Both sides say a name, the two are not the same name, and
	 * no timestamp, length or similarity is evidence of which somebody meant.
	 */
	list( $ax_ct_cf_sid, $ax_ct_cf_card ) = $ax_ct_legacy_actor(
		$ax_ct_users,
		$ax_ct_loose,
		array( 'given' => '지운2', 'surname' => '김', 'display_order' => 'family-given-compact' )
	);
	$ax_ct_cf_doc = axismundi_contacts_card_document( $ax_ct_cf_card );
	$ax_ct_cf_doc['name'] = array(
		'@type'            => 'Name',
		'isOrdered'        => true,
		'defaultSeparator' => '',
		'components'       => array(
			array( '@type' => 'NameComponent', 'kind' => 'surname', 'value' => '김' ),
			array( '@type' => 'NameComponent', 'kind' => 'given', 'value' => '지운' ),
		),
	);
	axismundi_contacts_save_card_for_owner( $ax_ct_cf_sid, $ax_ct_cf_doc, $ax_ct_cf_card );
	$ax_ct_cf_before = axismundi_contacts_card_document( $ax_ct_cf_card )['name'];
	ax_ct_assert(
		$ax_ct_results,
		'two structured names that differ are a question rather than a merge',
		'conflict' === axismundi_contacts_legacy_name_state( $ax_ct_cf_sid )
	);

	// Running the reconciliation again touches neither side, which is what makes it safe to leave in.
	axismundi_contacts_reconcile_legacy_names();
	axismundi_contacts_adopt_structured_names();
	ax_ct_assert(
		$ax_ct_results,
		'a conflict survives the migration running again, on both sides and unchanged',
		'conflict' === axismundi_contacts_legacy_name_state( $ax_ct_cf_sid )
			&& $ax_ct_cf_before === axismundi_contacts_card_document( $ax_ct_cf_card )['name']
			&& '지운2' === (string) ( axismundi_actors_person_profile( $ax_ct_cf_sid )['given'] ?? '' )
	);

	// Answered in favour of the card: the card stands, and the Actor stops holding a second opinion.
	$ax_ct_kept_card = axismundi_contacts_resolve_legacy_name( $ax_ct_cf_sid, 'card' );
	ax_ct_assert(
		$ax_ct_results,
		'keeping the card leaves it exactly as it was and the question does not come back',
		true === $ax_ct_kept_card
			&& $ax_ct_cf_before === axismundi_contacts_card_document( $ax_ct_cf_card )['name']
			&& 'none' === axismundi_contacts_legacy_name_state( $ax_ct_cf_sid )
			&& '' === (string) ( axismundi_actors_person_profile( $ax_ct_cf_sid )['given'] ?? '' )
	);

	// Answered the other way: the earlier name is written onto the card, in full and in parts.
	list( $ax_ct_ac_sid, $ax_ct_ac_card ) = $ax_ct_legacy_actor(
		$ax_ct_users,
		$ax_ct_loose,
		array( 'given' => '지운', 'surname' => '김', 'display_order' => 'family-given-compact' )
	);
	$ax_ct_ac_doc = axismundi_contacts_card_document( $ax_ct_ac_card );
	$ax_ct_ac_doc['name'] = array(
		'@type'      => 'Name',
		'isOrdered'  => true,
		'components' => array( array( '@type' => 'NameComponent', 'kind' => 'given', 'value' => 'Jiwoon' ) ),
	);
	axismundi_contacts_save_card_for_owner( $ax_ct_ac_sid, $ax_ct_ac_doc, $ax_ct_ac_card );
	$ax_ct_took = axismundi_contacts_resolve_legacy_name( $ax_ct_ac_sid, 'actor' );
	$ax_ct_ac_after = axismundi_contacts_card_document( $ax_ct_ac_card )['name'] ?? array();
	ax_ct_assert(
		$ax_ct_results,
		'choosing the earlier name writes it onto the card, and the Actor stops holding it too',
		true === $ax_ct_took
			&& array( 'surname' => '김', 'given' => '지운' ) === axismundi_contacts_name_actor_parts( $ax_ct_ac_after )
			&& 'none' === axismundi_contacts_legacy_name_state( $ax_ct_ac_sid )
	);

	/*
	 * A compact reading order was two facts on the Actor -- the order, and a rule about whether the
	 * parts were written in Latin letters -- and is one field on a card. Carrying it over states it
	 * outright rather than leaving the card to work it out again.
	 */
	ax_ct_assert(
		$ax_ct_results,
		'a compact reading order arrives as an empty default separator and reads the same',
		'' === (string) ( $ax_ct_ac_after['defaultSeparator'] ?? 'unset' )
			&& '김지운' === (string) ( $ax_ct_ac_after['full'] ?? '' )
	);

	/*
	 * And the label an Actor is shown as is not legacy at all. `display_name` is still Actors' own and
	 * still edited there, so there is nothing to carry -- and reading it here would make every Actor
	 * with a chosen label look like one whose name disagreed with its card, which is a question nobody
	 * has.
	 *
	 * The `custom` reading order is the same answer from the other side: it meant "show the label,
	 * ignore the parts", so the parts were already answering nothing.
	 */
	list( $ax_ct_cu_sid, $ax_ct_cu_card ) = $ax_ct_legacy_actor(
		$ax_ct_users,
		$ax_ct_loose,
		array( 'given' => 'Jiwoon', 'surname' => 'Kim', 'display_order' => 'custom', 'display_name' => 'Jiwoon of Busan' )
	);
	$ax_ct_cu_doc = axismundi_contacts_card_document( $ax_ct_cu_card );
	$ax_ct_cu_doc['name'] = array( '@type' => 'Name', 'full' => 'Jiwoon Kim' );
	axismundi_contacts_save_card_for_owner( $ax_ct_cu_sid, $ax_ct_cu_doc, $ax_ct_cu_card );
	ax_ct_assert(
		$ax_ct_results,
		'a label the Actor is shown as is not a name the card is missing, and asks nobody anything',
		'none' === axismundi_contacts_legacy_name_state( $ax_ct_cu_sid )
			&& 'Jiwoon Kim' === (string) ( axismundi_contacts_card_document( $ax_ct_cu_card )['name']['full'] ?? '' )
			&& 'Jiwoon of Busan' === (string) ( axismundi_actors_person_profile( $ax_ct_cu_sid )['display_name'] ?? '' )
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
			&& ! function_exists( 'axismundi_contacts_sync_name_from_actor' )
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
	/*
	 * The columns this file put back come off again, through the migration that owns that decision
	 * rather than by dropping them here: it is the thing that knows when it is safe, and the fixtures
	 * above are gone by now.
	 */
	if ( isset( $ax_ct_legacy_restored ) && function_exists( 'axismundi_actors_install' ) ) {
		$ax_ct_restore_schema = true;
	}
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
	if ( isset( $ax_ct_restore_schema ) ) {
		axismundi_actors_install();
	}
}

$ax_ct_failures = count( array_filter( $ax_ct_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ct_results ), $ax_ct_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ct_failures > 0 ? 1 : 0 );
}
exit( $ax_ct_failures > 0 ? 1 : 0 );
