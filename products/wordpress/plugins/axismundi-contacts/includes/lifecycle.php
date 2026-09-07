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
 * Contacts data whose owner is no longer anybody.
 *
 * Two different things go wrong, and telling them apart is most of the work:
 *
 *   referential   `owner_actor_id` names an Actor that is not in the registry at all. Whatever
 *                 removed it did not come through here, so nothing was cleaned up on the way.
 *
 *   lifecycle     the Actor is still in the registry, as it should be -- an ended identity leaves a
 *                 row standing -- but it is a Person whose WordPress account is gone, and a Person's
 *                 Contacts data ends with that account. The purge that should have run when the
 *                 account was deleted did not, or ran before this data existed.
 *
 * A tombstone is not either of them. Every Actor reaches one eventually and it says an identity
 * ended, not that this data may be destroyed: an Organization keeps its lists after the
 * administrator who happened to be deleted, a Group keeps no books to begin with, and a remote
 * Actor's Cards were never ours. Only the kind whose policy is `account` is looked at, and only
 * where the account really is gone.
 *
 * Nothing here decides that a name or a note is stale. It answers one question -- is there still
 * somebody this belongs to -- and where the answer is no, `axismundi_contacts_purge_actor()` does
 * the removing, on exactly the terms it always has: what that Actor owned, and nothing anybody else
 * keeps about them.
 *
 * @param bool $with_footprint Include what each one is still holding.
 * @return array<int,array<string,mixed>> Actor id => reason, and optionally its footprint.
 */
function axismundi_contacts_orphaned_contact_owners( bool $with_footprint = true ) : array {
	global $wpdb;
	$books  = axismundi_contacts_books_table();
	$cards  = axismundi_contacts_cards_table();
	$found  = array();

	if ( ! function_exists( 'axismundi_actors_actors_table' ) ) {
		// Without the registry there is no way to ask whether an owner exists, and guessing that none
		// of them do would delete every address book on the site.
		return array();
	}
	$actors     = axismundi_actors_actors_table();
	$identities = axismundi_actors_identities_table();

	/*
	 * Owners the registry has never heard of. Asked of both tables because a Card unfiled from every
	 * book is still owned by somebody, and looking only at books would miss it.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- maintenance sweep over this plugin's own tables.
	$dangling = (array) $wpdb->get_col(
		"SELECT DISTINCT owner FROM (
			SELECT owner_actor_id AS owner FROM {$books}
			UNION SELECT owner_actor_id AS owner FROM {$cards}
		) owners
		LEFT JOIN {$actors} a ON a.identity_id = owners.owner
		WHERE a.identity_id IS NULL AND owners.owner > 0"
	);
	foreach ( $dangling as $actor_id ) {
		$found[ (int) $actor_id ] = array( 'reason' => 'referential' );
	}

	/*
	 * And local Person Actors whose account is gone. `local_user_id` is kept when an identity is
	 * tombstoned -- which is what makes this answerable at all -- so the account it names is looked
	 * up rather than assumed.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- maintenance sweep over the Actors registry.
	$people = (array) $wpdb->get_results(
		"SELECT a.identity_id, a.local_user_id, a.actor_type FROM {$actors} a
			INNER JOIN {$identities} i ON i.id = a.identity_id
			WHERE i.origin = 'local' AND a.local_user_id > 0",
		ARRAY_A
	);
	foreach ( $people as $person ) {
		$actor_id = (int) $person['identity_id'];
		if ( isset( $found[ $actor_id ] ) ) {
			continue;
		}
		if ( 'account' !== axismundi_contacts_purge_policy( (string) $person['actor_type'] ) ) {
			continue;
		}
		if ( get_userdata( (int) $person['local_user_id'] ) ) {
			// The account is still there, so this Actor's data has an owner whatever its status says.
			continue;
		}
		if ( 0 === array_sum( axismundi_contacts_actor_footprint( $actor_id ) ) ) {
			continue;
		}
		$found[ $actor_id ] = array( 'reason' => 'lifecycle' );
	}

	if ( $with_footprint ) {
		foreach ( $found as $actor_id => $entry ) {
			$found[ $actor_id ]['footprint'] = axismundi_contacts_actor_footprint( (int) $actor_id );
		}
	}
	ksort( $found );
	return $found;
}

/**
 * Profile bindings pointing at a Card that is not there.
 *
 * Its own question, and not an ownership one: the Actor may be perfectly current and the Card simply
 * deleted out from under the binding. Nothing is destroyed by answering it -- the Card is already
 * gone -- so this is the one part of the sweep that cannot cost anybody anything.
 *
 * @return int[] Actor ids.
 */
function axismundi_contacts_dangling_profile_bindings() : array {
	global $wpdb;
	$profiles = axismundi_contacts_profiles_table();
	$cards    = axismundi_contacts_cards_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- maintenance sweep over this plugin's own tables.
	return array_map(
		'intval',
		(array) $wpdb->get_col(
			"SELECT p.actor_id FROM {$profiles} p
				LEFT JOIN {$cards} c ON c.id = p.card_id
				WHERE p.card_id > 0 AND c.id IS NULL"
		)
	);
}

/**
 * Remove what no longer belongs to anybody.
 *
 * Every removal goes through `axismundi_contacts_purge_actor()`, so the boundary is the one that has
 * always applied: what that Actor owned, never a Card somebody else keeps about them. Running it
 * again finds nothing, because the second run asks the same question of a database where the answer
 * has changed.
 *
 * @return array<string,mixed> What was found and what was removed.
 */
function axismundi_contacts_purge_orphaned_contact_owners() : array {
	$orphans = axismundi_contacts_orphaned_contact_owners( false );
	$report  = array( 'actors' => array(), 'removed' => array( 'cards' => 0, 'books' => 0, 'profile' => 0 ), 'bindings' => 0 );
	foreach ( $orphans as $actor_id => $entry ) {
		$removed = axismundi_contacts_purge_actor( (int) $actor_id );
		$report['actors'][ (int) $actor_id ] = array( 'reason' => $entry['reason'], 'removed' => $removed );
		foreach ( $removed as $key => $count ) {
			$report['removed'][ $key ] += (int) $count;
		}
	}
	/*
	 * Then the bindings that point at nothing, which the purge above will have cleared for anybody it
	 * touched and which may exist for an Actor it had no business touching.
	 */
	global $wpdb;
	foreach ( axismundi_contacts_dangling_profile_bindings() as $actor_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->delete( axismundi_contacts_profiles_table(), array( 'actor_id' => $actor_id ), array( '%d' ) );
		++$report['bindings'];
	}
	return $report;
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
