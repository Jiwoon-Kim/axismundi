<?php
/**
 * Whose address book this is, and who may open it.
 *
 * The book belongs to an Actor rather than to a WordPress account, because a person and an
 * Organization each keep one and the acting-Actor switch already decides which is in front of
 * somebody. Access is asked again on every read, so losing a manager role closes the book even
 * though the rows are still there.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether one person may read and write one Actor's address book.
 *
 * Deliberately narrower than `axismundi_actors_can_manage()`, which grants a site administrator
 * authority over every Actor. Being able to administer an identity and being able to read the
 * private address book kept under it are different powers: the first is about the site, the second
 * is somebody's list of who they know, their private notes about them, and the phone numbers they
 * were given. So a user-scope Actor's book is its own person's, with no capability override; a
 * managed Actor's book is its managers'.
 *
 * @param int $owner_actor_id Actor identity that owns the book.
 * @param int $user_id        Local user asking.
 * @return bool
 */
function axismundi_contacts_can_use_book( int $owner_actor_id, int $user_id ) : bool {
	if ( $owner_actor_id <= 0 || $user_id <= 0 || ! axismundi_contacts_has_actors() ) {
		return false;
	}
	$actor = axismundi_actors_get_by_identity( $owner_actor_id );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() ) {
		return false;
	}
	if ( $actor->is_managed() ) {
		return function_exists( 'axismundi_actors_managed_actor_can_manage' )
			&& axismundi_actors_managed_actor_can_manage( $owner_actor_id, $user_id );
	}
	return (int) $actor->get_local_user_id() === $user_id;
}

/**
 * One Actor's default address book, creating it the first time it is asked for.
 *
 * Created on demand rather than when an Actor is, because most Actors will never keep one and a
 * table of empty books is a table of rows that only exist to be joined against.
 *
 * An Actor may keep several books, but exactly one of them is the default: it is where a Card goes
 * when nobody said where. What the Actor publishes about itself is not kept here -- that belongs to
 * the Actor rather than to any one of its containers.
 *
 * @param int $owner_actor_id Actor identity.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_contacts_book_for_actor( int $owner_actor_id ) {
	global $wpdb;
	if ( ! axismundi_contacts_has_actors() ) {
		return new WP_Error( 'ax_contacts_actors', __( 'Address books need Axismundi Actors.', 'axismundi-contacts' ) );
	}
	$actor = axismundi_actors_get_by_identity( $owner_actor_id );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() ) {
		return new WP_Error(
			'ax_contacts_book_owner',
			__( 'An address book belongs to a local Actor.', 'axismundi-contacts' ),
			array( 'status' => 400 )
		);
	}
	/*
	 * Not every Actor is somebody who files contacts. A Group is a set of relationships, and an
	 * Application or Service is administered from the Actor screens -- handing either an address book
	 * would put a second place to look for something that already lives elsewhere.
	 */
	if ( ! axismundi_contacts_type_keeps_books( $actor->get_type() ) ) {
		return new WP_Error(
			'ax_contacts_book_type',
			__( 'Actors of that kind do not keep address books.', 'axismundi-contacts' ),
			array( 'status' => 400 )
		);
	}
	$table = axismundi_contacts_books_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE owner_actor_id = %d AND is_default = 1", $owner_actor_id ), ARRAY_A );
	if ( is_array( $row ) ) {
		return $row;
	}
	$now = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$created = $wpdb->insert(
		$table,
		array(
			'owner_actor_id' => $owner_actor_id,
			'name'           => '',
			'is_default'     => 1,
			'revision'       => 0,
			'created_at'     => $now,
			'updated_at'     => $now,
		),
		array( '%d', '%s', '%d', '%d', '%s', '%s' )
	);
	if ( false === $created ) {
		return new WP_Error( 'ax_contacts_book_create', __( 'The address book could not be created.', 'axismundi-contacts' ) );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read back what was just written.
	$book = (array) $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE owner_actor_id = %d AND is_default = 1", $owner_actor_id ), ARRAY_A );
	/*
	 * A Person's book opens with a card for its owner, seeded from the Actor. Opening onto a blank form
	 * asks somebody to type in what the site already knows about them -- and because the seeded values
	 * are recorded as the Actor's, a later rename can still be pulled in until they edit it themselves.
	 *
	 * An Organization's does not. A company keeping a list of clients has not thereby said it wants a
	 * contact card published about itself, and making one uninvited is the plugin deciding that for
	 * them; `axismundi_contacts_create_profile_card()` is how they ask.
	 */
	if ( 'auto' === axismundi_contacts_profile_policy( $actor->get_type() ) && function_exists( 'axismundi_contacts_seed_self_card' ) ) {
		axismundi_contacts_seed_self_card( (int) $book['id'], $owner_actor_id );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the pointer was just written.
		$book = (array) $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $book['id'] ), ARRAY_A );
	}
	return $book;
}

/**
 * The id of an Actor's default book, or 0 if they cannot have one.
 *
 * For callers holding a Card and needing somewhere to save it back to. A Card knows its owner but
 * not which book an edit arrived from, and the owner's default book is the answer that is always
 * right for a save that is not moving anything.
 *
 * @param int $owner_actor_id Actor identity.
 * @return int
 */
function axismundi_contacts_home_book_id( int $owner_actor_id ) : int {
	$book = axismundi_contacts_book_for_actor( $owner_actor_id );
	return is_wp_error( $book ) ? 0 : (int) $book['id'];
}

/**
 * Every book one Actor keeps, default first.
 *
 * @param int $owner_actor_id Actor identity.
 * @return array<int,array<string,mixed>>
 */
function axismundi_contacts_books_for_actor( int $owner_actor_id ) : array {
	global $wpdb;
	if ( $owner_actor_id <= 0 ) {
		return array();
	}
	$table = axismundi_contacts_books_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE owner_actor_id = %d ORDER BY is_default DESC, name ASC, id ASC", $owner_actor_id ),
		ARRAY_A
	);
}

/**
 * Open another book for an Actor.
 *
 * Never the default: an Actor's default book is made once, by asking for it. A second book is
 * something somebody chose to open and named.
 *
 * @param int    $owner_actor_id Actor identity.
 * @param string $name           What to call it.
 * @return int|WP_Error Book id.
 */
function axismundi_contacts_create_book( int $owner_actor_id, string $name ) {
	global $wpdb;
	$name = trim( $name );
	if ( '' === $name ) {
		return new WP_Error( 'ax_contacts_book_name', __( 'An address book needs a name.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	// Asking for the default first, so an Actor cannot end up with a second book and no first one.
	$default = axismundi_contacts_book_for_actor( $owner_actor_id );
	if ( is_wp_error( $default ) ) {
		return $default;
	}
	$now = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$created = $wpdb->insert(
		axismundi_contacts_books_table(),
		array(
			'owner_actor_id' => $owner_actor_id,
			'name'           => $name,
			'is_default'     => null,
			'revision'       => 0,
			'created_at'     => $now,
			'updated_at'     => $now,
		)
	);
	if ( false === $created ) {
		return new WP_Error( 'ax_contacts_book_create', __( 'The address book could not be created.', 'axismundi-contacts' ) );
	}
	return (int) $wpdb->insert_id;
}

/**
 * File a Card into a book.
 *
 * A Card can only be filed into a book its owner keeps. The check is not a formality: without it,
 * anybody who could name a book id could put a Card they own into somebody else's list.
 *
 * @param int $card_id Card id.
 * @param int $book_id Address book id.
 * @return true|WP_Error
 */
function axismundi_contacts_add_card_to_book( int $card_id, int $book_id ) {
	global $wpdb;
	$card = axismundi_contacts_get_card( $card_id );
	$book = axismundi_contacts_get_book( $book_id );
	if ( array() === $card || array() === $book ) {
		return new WP_Error( 'ax_contacts_membership', __( 'That card or address book does not exist.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	if ( (int) $card['owner_actor_id'] !== (int) $book['owner_actor_id'] ) {
		return new WP_Error( 'ax_contacts_membership_owner', __( 'A card can only be filed into its own owner\'s address book.', 'axismundi-contacts' ), array( 'status' => 403 ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->query(
		$wpdb->prepare(
			// Filing a Card into a book it is already in is not an error, it is a no-op.
			'INSERT IGNORE INTO ' . axismundi_contacts_memberships_table() . ' (address_book_id, card_id, created_at) VALUES (%d, %d, %s)',
			$book_id,
			$card_id,
			current_time( 'mysql', true )
		)
	);
	axismundi_contacts_touch_book( $book_id );
	return true;
}

/**
 * Take a Card out of a book.
 *
 * The Card itself is untouched. Removing it from every book leaves it owned but filed nowhere,
 * which is a different thing from deleting it -- and the difference matters, because one of them
 * throws away somebody's notes. `axismundi_contacts_cards_for_owner()` is what still finds it.
 *
 * @param int $card_id Card id.
 * @param int $book_id Address book id.
 * @return true
 */
function axismundi_contacts_remove_card_from_book( int $card_id, int $book_id ) : bool {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete(
		axismundi_contacts_memberships_table(),
		array( 'card_id' => $card_id, 'address_book_id' => $book_id ),
		array( '%d', '%d' )
	);
	axismundi_contacts_touch_book( $book_id );
	return true;
}

/**
 * Which books a Card is filed into.
 *
 * @param int $card_id Card id.
 * @return array<int,int> Book ids.
 */
function axismundi_contacts_card_books( int $card_id ) : array {
	global $wpdb;
	if ( $card_id <= 0 ) {
		return array();
	}
	$table = axismundi_contacts_memberships_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT address_book_id FROM {$table} WHERE card_id = %d ORDER BY address_book_id ASC", $card_id ) ) );
}

/**
 * One address book by id.
 *
 * @param int $book_id Address book id.
 * @return array<string,mixed>
 */
function axismundi_contacts_get_book( int $book_id ) : array {
	global $wpdb;
	if ( $book_id <= 0 ) {
		return array();
	}
	$table = axismundi_contacts_books_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $book_id ), ARRAY_A );
	return is_array( $row ) ? $row : array();
}

/** Bump the book's revision, so a sync can tell it moved without reading every Card. */
function axismundi_contacts_touch_book( int $book_id ) : void {
	global $wpdb;
	$table = axismundi_contacts_books_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET revision = revision + 1, updated_at = %s WHERE id = %d", current_time( 'mysql', true ), $book_id ) );
}
