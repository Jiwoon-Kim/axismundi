<?php
/**
 * What happens to an address book when its owner is gone.
 *
 * Ownership is the root here, so an Actor going away takes its own Contacts data with it: the Card
 * it published about itself, the books it kept, the Cards it filed into them, and everything derived
 * from those. Left behind, they are worse than clutter -- an address book nobody can open is other
 * people's phone numbers and somebody's private notes about them, kept forever by a site with no
 * account left that is allowed to read them.
 *
 * The boundary that matters is the one this file is careful about. What goes is what that Actor
 * *owned*. What stays is every Card somebody else keeps *about* that Actor: those are other people's
 * records, written by them, and an Actor closing an account does not reach into address books it
 * never had access to. `owner_actor_id` is the only column consulted, which is most of why that
 * column exists.
 *
 * When that happens is decided per Actor type rather than by one shared trigger. A Person Actor is
 * bound to a WordPress account and shares its lifetime, so deleting the account takes the personal
 * address book with it -- keeping it would mean holding somebody's private notes and other people's
 * phone numbers in a book that no account is allowed to open ever again. An Organization outlives the
 * administrator who happened to be deleted, and its lists belong to the Organization; a Group keeps
 * no books at all. So `tombstone`, which every one of them eventually reaches, is deliberately not
 * the trigger: it says an identity ended, not that this data may be destroyed.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remove everything one Actor owns in Contacts.
 *
 * @param int $actor_id Actor identity.
 * @return array<string,int> What was removed, by table.
 */
function axismundi_contacts_purge_actor( int $actor_id ) : array {
	global $wpdb;
	$removed = array( 'cards' => 0, 'books' => 0, 'profile' => 0 );
	if ( $actor_id <= 0 ) {
		return $removed;
	}

	/*
	 * The profile binding goes first. It points at a Card that is about to stop existing, and a
	 * binding left pointing at a deleted row would answer `which Card describes this Actor` with an
	 * id that resolves to nothing.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$removed['profile'] = (int) $wpdb->delete( axismundi_contacts_profiles_table(), array( 'actor_id' => $actor_id ), array( '%d' ) );

	/*
	 * Cards by ownership, not by book. A Card unfiled from every book is still this Actor's and still
	 * has to go, and deleting book by book would leave exactly those behind.
	 */
	$cards = axismundi_contacts_cards_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$owned = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$cards} WHERE owner_actor_id = %d", $actor_id ) ) );
	foreach ( $owned as $card_id ) {
		axismundi_contacts_delete_card( $card_id );
		++$removed['cards'];
	}

	$books = axismundi_contacts_books_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$book_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$books} WHERE owner_actor_id = %d", $actor_id ) ) );
	foreach ( $book_ids as $book_id ) {
		/*
		 * Any membership rows still naming this book. Deleting a Card clears its own, but a book can
		 * hold rows for Cards that were already gone -- and an orphan membership would file a future
		 * Card, reusing this id, into a book that no longer exists.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->delete( axismundi_contacts_memberships_table(), array( 'address_book_id' => $book_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->delete( $books, array( 'id' => $book_id ), array( '%d' ) );
		++$removed['books'];
	}
	return $removed;
}

/**
 * What one Actor owns in Contacts, without removing any of it.
 *
 * For a screen that has to say what closing an account destroys before it destroys it. A count is
 * the difference between `this cannot be undone` as a warning and as an apology.
 *
 * @param int $actor_id Actor identity.
 * @return array<string,int>
 */
function axismundi_contacts_actor_footprint( int $actor_id ) : array {
	global $wpdb;
	if ( $actor_id <= 0 ) {
		return array( 'cards' => 0, 'books' => 0, 'profile' => 0 );
	}
	$cards = axismundi_contacts_cards_table();
	$books = axismundi_contacts_books_table();
	return array(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed count in this plugin's own table.
		'cards'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$cards} WHERE owner_actor_id = %d", $actor_id ) ),
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed count in this plugin's own table.
		'books'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$books} WHERE owner_actor_id = %d", $actor_id ) ),
		'profile' => axismundi_contacts_profile_card( $actor_id ) > 0 ? 1 : 0,
	);
}

/**
 * What ends this Actor's Contacts data, if anything does.
 *
 * `account` -- the Actor is bound to a WordPress account and shares its lifetime, so deleting that
 * account takes the personal address book with it.
 *
 * `manual` -- somebody has to ask, having been shown what will be destroyed. An Organization is not
 * ended by the deletion of whichever administrator happened to be removed, and its client and
 * partner lists are the Organization's rather than that person's.
 *
 * `none` -- an Actor of this kind keeps nothing here to end.
 *
 * @param string $actor_type ActivityStreams actor type.
 * @return string One of account|manual|none.
 */
function axismundi_contacts_purge_policy( string $actor_type ) : string {
	switch ( $actor_type ) {
		case 'Person':
			return 'account';
		case 'Organization':
		case 'Group':
			return 'manual';
		default:
			return 'none';
	}
}

/**
 * A deleted account takes its own address book with it.
 *
 * Only the Person Actor bound to that account, and only what that Actor owned. An Organization this
 * person administered is a different Actor with a different lifetime, and every Card other people
 * keep about them belongs to those people.
 *
 * Runs after the Actors plugin has tombstoned the identity, which leaves the row standing -- so the
 * Actor is still readable here, and the account it named is already gone.
 *
 * @param int $user_id Deleted user.
 * @return void
 */
function axismundi_contacts_purge_for_deleted_user( int $user_id ) : void {
	if ( ! function_exists( 'axismundi_actors_get_for_user' ) ) {
		return;
	}
	$actor = axismundi_actors_get_for_user( $user_id );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return;
	}
	if ( 'account' !== axismundi_contacts_purge_policy( $actor->get_type() ) ) {
		return;
	}
	axismundi_contacts_purge_actor( (int) $actor->get_identity_id() );
}
add_action( 'deleted_user', 'axismundi_contacts_purge_for_deleted_user', 20 );
