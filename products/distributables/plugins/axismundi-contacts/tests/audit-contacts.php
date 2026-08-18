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
require_once dirname( __DIR__ ) . '/includes/admin.php';

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
		str_contains( $ax_ct_form, 'name="full_name"' )
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
		str_contains( $ax_ct_other_form, 'name="full_name"' )
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
			&& ! str_contains( $ax_ct_wrong_book, 'name="full_name"' )
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

	// -- one name, kept by two owners ---------------------------------------------------------------------------

	/*
	 * The Card stores the whole JSContact name because it has to: a Card holding only the parts an
	 * Actor owns would lose a title on the first round trip. So the shared parts are written down
	 * twice and each side keeps what it is the authority on.
	 */
	axismundi_actors_write_person_profile(
		(int) $ax_ct_fresh->get_identity_id(),
		array( 'structured_name_language' => 'ko', 'given' => "\xec\xa7\x80\xec\x9a\xb4", 'surname' => "\xea\xb9\x80", 'display_order' => 'family-given-compact' )
	);
	$ax_ct_named = axismundi_contacts_card_document( $ax_ct_seeded );
	$ax_ct_kinds = array();
	foreach ( (array) ( $ax_ct_named['name']['components'] ?? array() ) as $ax_ct_c ) {
		$ax_ct_kinds[ (string) $ax_ct_c['kind'] ] = (string) $ax_ct_c['value'];
	}
	ax_ct_assert(
		$ax_ct_results,
		'a name written on the Actor screen reaches the card it publishes',
		array( 'surname' => "\xea\xb9\x80", 'given' => "\xec\xa7\x80\xec\x9a\xb4" ) === $ax_ct_kinds
	);
	/*
	 * And what a contact card adds to a name stays put. Actors has nowhere to keep a credential, so a
	 * sync that rebuilt the component list from the Actor's parts alone would delete it.
	 */
	$ax_ct_named['name']['components'][] = array( '@type' => 'NameComponent', 'kind' => 'credential', 'value' => 'Ph.D.' );
	axismundi_contacts_save_card_for_owner( (int) $ax_ct_fresh->get_identity_id(), $ax_ct_named, $ax_ct_seeded );
	axismundi_actors_write_person_profile(
		(int) $ax_ct_fresh->get_identity_id(),
		array( 'structured_name_language' => 'ko', 'given' => "\xec\xa7\x80\xec\x9a\xb4", 'surname' => "\xeb\xa6\xac", 'display_order' => 'family-given-compact' )
	);
	$ax_ct_after = array();
	foreach ( (array) ( axismundi_contacts_card_document( $ax_ct_seeded )['name']['components'] ?? array() ) as $ax_ct_c ) {
		$ax_ct_after[ (string) $ax_ct_c['kind'] ] = (string) $ax_ct_c['value'];
	}
	ax_ct_assert(
		$ax_ct_results,
		'a later Actor edit updates the parts it owns and leaves the credential the card added',
		"\xeb\xa6\xac" === ( $ax_ct_after['surname'] ?? '' ) && 'Ph.D.' === ( $ax_ct_after['credential'] ?? '' )
	);
	/*
	 * And the other direction, without a loop: editing the card writes the Actor's parts back. The
	 * two sides each save on the other's write, so this only terminates because the announcement is
	 * guarded against re-entry.
	 */
	$ax_ct_edit = axismundi_contacts_card_document( $ax_ct_seeded );
	foreach ( $ax_ct_edit['name']['components'] as $ax_ct_i => $ax_ct_c ) {
		if ( 'surname' === $ax_ct_c['kind'] ) {
			$ax_ct_edit['name']['components'][ $ax_ct_i ]['value'] = "\xeb\xb0\x95";
		}
	}
	axismundi_contacts_save_card_for_owner( (int) $ax_ct_fresh->get_identity_id(), $ax_ct_edit, $ax_ct_seeded );
	ax_ct_assert(
		$ax_ct_results,
		'editing the profile card writes the Actor own parts back to it',
		"\xeb\xb0\x95" === (string) ( axismundi_actors_person_profile( (int) $ax_ct_fresh->get_identity_id() )['surname'] ?? '' )
	);
	/*
	 * An ordinary card obeys none of this. Somebody saving a name of their own choosing for a person
	 * whose Actor says otherwise is right, not out of step -- the card is theirs.
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
			&& "\xeb\xb0\x95" === (string) ( axismundi_actors_person_profile( (int) $ax_ct_fresh->get_identity_id() )['surname'] ?? '' )
	);

	/*
	 * A title and a credential belong to the card, not to the Actor. Both tables had a column for the
	 * same component, which is how one card came to carry it twice; Actors now emits neither.
	 */
	$ax_ct_parts = axismundi_actors_jscontact_name(
		array( 'given' => 'Ada', 'surname' => 'Lovelace', 'title' => 'Dr.', 'credential' => 'PhD', 'display_order' => 'given-family' )
	);
	$ax_ct_emitted = array();
	foreach ( (array) ( $ax_ct_parts['components'] ?? array() ) as $ax_ct_c ) {
		$ax_ct_emitted[] = (string) $ax_ct_c['kind'];
	}
	ax_ct_assert(
		$ax_ct_results,
		'a title and a credential belong to the card, and Actors emits neither even when it still has a column for one',
		array( 'given', 'surname' ) === $ax_ct_emitted
	);

	// -- what this plugin is not -------------------------------------------------------------------------------

	/*
	 * Contacts keeps address books. It does not keep a second copy of anybody's public profile, and
	 * it does not decide what an Actor is -- that boundary is the reason a Card may be a person with
	 * a phone number and nothing else.
	 */
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
}

$ax_ct_failures = count( array_filter( $ax_ct_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ct_results ), $ax_ct_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ct_failures > 0 ? 1 : 0 );
}
exit( $ax_ct_failures > 0 ? 1 : 0 );
