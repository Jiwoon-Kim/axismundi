<?php
/**
 * Where address books are kept.
 *
 * Five tables, and the split between them is the whole design.
 *
 * The Card is stored as the JSContact document somebody authored, whole. Not decomposed into
 * columns: JSContact carries multiple values per field, entries this code has never heard of, and
 * a version that will move again -- and a schema that flattens all of that turns every future
 * field into a migration and every import/export round trip into a lossy one.
 *
 * But a document store alone is an address book nobody can search. Finding somebody by email,
 * sorting by name, or asking which Card links to an Actor would mean reading every row. So the
 * values that get looked up are extracted into index rows beside the document.
 *
 *   ax_contact_cards            card_json -- the record
 *   ax_contact_card_values      the searchable values, derived from it
 *   ax_contact_card_provenance  where each of those values came from
 *   ax_contact_address_books    the containers somebody sorts their contacts into
 *   ax_contact_card_memberships which containers a Card appears in
 *   ax_contact_actor_profiles   which Card an Actor publishes about itself, and how widely
 *
 * Derivation runs one way only. Nothing writes an index row and expects the Card to follow; the
 * moment that path exists there are two records and they disagree.
 *
 * A Card is owned by an Actor and appears in that Actor's books. Ownership is a column, because it
 * is what access is decided by and it never has two answers; book membership is a relation, because
 * `개인`, `업무`, `가족` are how one person files the same contact, which is the shape JMAP addresses
 * as `addressBookIds`. Keeping ownership out of the relation is also what makes it impossible for
 * one Card to sit in two different people's books.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_CONTACTS_DB_VERSION        = '10';
const AXISMUNDI_CONTACTS_DB_VERSION_OPTION = 'ax_contacts_db_version';

/**
 * How widely the Card an Actor keeps about themselves may be read.
 *
 * `contacts` is decided from the owner's own address book -- whether *they* saved the requester --
 * because the other direction cannot be checked without reading somebody else's book. That test is
 * only answerable on this site, so it does not federate: a request arriving from another server is
 * never measured against it, it is simply not served. An audience that degrades to silence off-site
 * is a policy this code can actually keep.
 */
const AXISMUNDI_CONTACTS_SHARING = array( 'off', 'contacts', 'public' );

/**
 * Who a shared profile Card is shared with.
 *
 * Kept apart from whether it is shared at all, because those are different actions. Turning sharing
 * off for a while is not the same as deciding to share with fewer people, and a single three-way
 * setting makes it so: switching off forgets which audience had been chosen and the way back is a
 * second decision somebody has to make again.
 */
const AXISMUNDI_CONTACTS_AUDIENCES = array( 'contacts', 'public' );

/** @return string Address books. */
function axismundi_contacts_books_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_contact_address_books';
}

/** @return string Cards, as authored. */
function axismundi_contacts_cards_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_contact_cards';
}

/** @return string Which Card each Actor publishes about itself. */
function axismundi_contacts_profiles_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_contact_actor_profiles';
}

/** @return string Which books a Card appears in. */
function axismundi_contacts_memberships_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_contact_card_memberships';
}

/** @return string Searchable values derived from a Card. */
function axismundi_contacts_values_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_contact_card_values';
}

/** @return string Where each value in a Card came from. */
function axismundi_contacts_provenance_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_contact_card_provenance';
}

/**
 * Say which revision every Card in the store is written in.
 *
 * Cards were stored without one and only gained a `version` as they were published, so a Card made
 * before that is a v2 document to a stranger and an unversioned one to everything here -- including
 * the editor that reads and saves it back.
 *
 * Every Card, not only the ones this site made. A store holding some documents at 1.0 and some at
 * 2.0 asks every reader and every export to handle both, for no gain: a 1.0 Card is a valid 2.0
 * Card, and what 2.0 changed -- `uid` becoming optional -- takes nothing away from one that has an
 * identifier already. Where the bytes somebody sent matter, they belong in an import snapshot beside
 * the record rather than in the record.
 *
 * @return int How many were changed.
 */
function axismundi_contacts_state_jscontact_version() : int {
	global $wpdb;
	$cards = axismundi_contacts_cards_table();
	/*
	 * Read whole rather than matched in SQL. Whether a JSON document has a property, and what it says,
	 * are questions about the document -- a `LIKE` for the word `version` would also find a note that
	 * mentioned one.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own table.
	$rows   = (array) $wpdb->get_results( "SELECT id, card_json FROM {$cards}", ARRAY_A );
	$stated = 0;
	foreach ( $rows as $row ) {
		$card = json_decode( (string) $row['card_json'], true );
		if ( ! is_array( $card ) || AXISMUNDI_CONTACTS_JSCONTACT_VERSION === ( $card['version'] ?? null ) ) {
			continue;
		}
		$card['version'] = AXISMUNDI_CONTACTS_JSCONTACT_VERSION;
		$card            = axismundi_contacts_canonical_card( $card );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update(
			$cards,
			array( 'card_json' => (string) wp_json_encode( $card ), 'updated_at' => current_time( 'mysql', true ) ),
			array( 'id' => (int) $row['id'] ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		++$stated;
	}
	return $stated;
}

/**
 * Create or upgrade the tables and record the schema version.
 *
 * @return bool Whether the schema is now what this code expects.
 */
function axismundi_contacts_install_schema() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset     = $wpdb->get_charset_collate();
	$books       = axismundi_contacts_books_table();
	$cards       = axismundi_contacts_cards_table();
	$memberships = axismundi_contacts_memberships_table();
	$profiles    = axismundi_contacts_profiles_table();
	$values      = axismundi_contacts_values_table();
	$provenance  = axismundi_contacts_provenance_table();

	/*
	 * An Actor keeps as many books as they file into, one of which is the default.
	 *
	 * `is_default` is `1` or nothing rather than `1` or `0`, so that the unique key can say `one
	 * default per Actor`: a unique index counts every `0` as the same value and would allow an Actor
	 * only one non-default book, while it allows as many NULLs as you like.
	 *
	 * Which Card an Actor publishes about itself is deliberately not here. That is a fact about the
	 * Actor, and a column on the default book would quietly make `share my profile` and `share my
	 * address book` the same setting -- which they are not, because nobody publishes the three
	 * hundred people they know.
	 */
	dbDelta(
		"CREATE TABLE {$books} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_actor_id bigint(20) unsigned NOT NULL,
			name varchar(191) NOT NULL default '',
			is_default tinyint(1) unsigned DEFAULT NULL,
			revision bigint(20) unsigned NOT NULL default 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY owner_default (owner_actor_id, is_default),
			KEY owner_actor_id (owner_actor_id)
		) ENGINE=InnoDB {$charset};"
	);

	/*
	 * `card_json` is the record. The columns beside it are derived on every write and exist so the
	 * common lookups do not have to open the document: sorting a list, finding a Card by its
	 * JSContact uid, and asking which Card is linked to a given Actor.
	 *
	 * `uid` is NULL when the Card has none. JSContact 2.0 made it optional, so `''` would be a real
	 * value shared by every Card that never had one, and the unique key below would then allow an
	 * Actor exactly one such Card. NULL is the only value a unique index lets repeat.
	 *
	 * That key says: the same Card UID is not stored twice by one owner. It is not a statement about
	 * people. One person can hold several Cards with different UIDs -- a personal one and a work one,
	 * or two that separate systems minted independently -- and deciding those describe one person is
	 * a merge, judged on names and numbers and confirmed by somebody, not something a key can do.
	 *
	 * `revision` is what a later sync needs to know whether the local copy moved since it last
	 * looked, and what an editing screen needs to refuse a save written against a stale view.
	 */
	dbDelta(
		"CREATE TABLE {$cards} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			owner_actor_id bigint(20) unsigned NOT NULL default 0,
			card_json longtext NOT NULL,
			uid varchar(191) DEFAULT NULL,
			display_name varchar(191) NOT NULL default '',
			sort_key varchar(191) NOT NULL default '',
			linked_actor_identity_id bigint(20) unsigned DEFAULT NULL,
			linked_actor_uri text DEFAULT NULL,
			linked_actor_uri_hash char(64) NOT NULL default '',
			revision bigint(20) unsigned NOT NULL default 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY owner_uid (owner_actor_id, uid),
			KEY owner_actor_id (owner_actor_id),
			KEY sort_key (sort_key),
			KEY linked_actor_identity_id (linked_actor_identity_id),
			KEY linked_actor_uri_hash (linked_actor_uri_hash)
		) ENGINE=InnoDB {$charset};"
	);

	/*
	 * Which books a Card appears in. A Card in no book is still owned and still findable; it simply
	 * has not been filed. A Card in two is one Card seen from two places rather than a copy --
	 * editing it from either changes the single record, which is the whole reason this is a relation
	 * and not a column.
	 */
	dbDelta(
		"CREATE TABLE {$memberships} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			address_book_id bigint(20) unsigned NOT NULL,
			card_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY book_card (address_book_id, card_id),
			KEY card_id (card_id)
		) ENGINE=InnoDB {$charset};"
	);

	/*
	 * One profile Card per Actor, and one Actor per profile Card.
	 *
	 * The unit is the Actor rather than the account, because one person acts here as themselves, as
	 * a Group and as an Organization, and each publishes a different card: a Person has a birthday
	 * and relatives, an Organization has a department and a main number. Switching the acting Actor
	 * switches which profile is in front of somebody rather than editing one shared card.
	 *
	 * `sharing` belongs on this binding rather than on the Card, so that a Card carries contact data
	 * and nothing about publication, and rather than on a book, so that adding a second book never
	 * raises the question of which one's audience wins.
	 */
	dbDelta(
		"CREATE TABLE {$profiles} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_id bigint(20) unsigned NOT NULL,
			card_id bigint(20) unsigned NOT NULL,
			sharing_enabled tinyint(1) unsigned NOT NULL default 0,
			audience varchar(16) NOT NULL default 'contacts',
			published_json longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY actor_id (actor_id),
			UNIQUE KEY card_id (card_id)
		) ENGINE=InnoDB {$charset};"
	);

	/*
	 * One row per value a Card holds, so an address book can be searched the way people search one:
	 * by an email, by the last four digits of a number, by a handle somebody remembers.
	 *
	 * `entry_id` is the key that value has inside the Card -- JSContact addresses its multi-value
	 * fields by a map key rather than by position, which is exactly what makes per-entry provenance
	 * possible. `normalized_value` is for matching and never for display; the Card keeps what was
	 * actually typed.
	 */
	dbDelta(
		"CREATE TABLE {$values} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			card_id bigint(20) unsigned NOT NULL,
			field varchar(32) NOT NULL,
			entry_id varchar(191) NOT NULL default '',
			value text NOT NULL,
			normalized_value varchar(191) NOT NULL default '',
			pref smallint(5) unsigned DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY card_field (card_id, field),
			KEY normalized_value (normalized_value)
		) ENGINE=InnoDB {$charset};"
	);

	/*
	 * Provenance per entry, not per Card.
	 *
	 * Two email addresses on one Card where one came from a Google import and the other was typed
	 * in is the ordinary case, not an edge case -- so a single `source` column on the Card would be
	 * wrong for almost every Card that has ever been imported into. The pointer is the entry's own
	 * address inside the Card (`emails/work`), which is stable across edits in a way an array index
	 * is not.
	 *
	 * This lives outside `card_json` because it is not contact data. Nobody exports it, nobody
	 * receives it, and putting it in the Card would ship an account's sync bookkeeping to whoever
	 * asked for a vCard. Per-property visibility, when it comes, belongs in a table of this shape
	 * keyed by the same pointers -- for the same reason, and because JSContact has nowhere inside an
	 * entry to record who may read it.
	 */
	dbDelta(
		"CREATE TABLE {$provenance} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			card_id bigint(20) unsigned NOT NULL,
			pointer varchar(191) NOT NULL,
			source varchar(64) NOT NULL,
			source_ref varchar(191) NOT NULL default '',
			locked tinyint(1) NOT NULL default 0,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY card_pointer (card_id, pointer),
			KEY card_source (card_id, source)
		) ENGINE=InnoDB {$charset};"
	);

	axismundi_contacts_migrate_books_to_memberships();
	/*
	 * And the Cards this site made before it said so are told which revision they are written in. Run
	 * after the tables exist, because it reads provenance to tell ours from somebody else's.
	 */
	axismundi_contacts_state_jscontact_version();
	// And the one entry key that was ever spelled out is moved, with everything that addresses it.
	axismundi_contacts_migrate_home_service_key();
	// So are the phone addresses written before the collections agreed on what to call themselves.
	axismundi_contacts_migrate_phone_keys();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check.
	$card_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$cards}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check.
	$book_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$books}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check.
	$provenance_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$provenance}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check.
	$profile_share_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$profiles}" );
	$valid = in_array( 'published_json', $profile_share_columns, true )
		&& in_array( 'card_json', $card_columns, true )
		&& in_array( 'revision', $card_columns, true )
		&& in_array( 'owner_actor_id', $card_columns, true )
		&& ! in_array( 'address_book_id', $card_columns, true )
		&& in_array( 'linked_actor_uri_hash', $card_columns, true )
		&& in_array( 'is_default', $book_columns, true )
		&& ! in_array( 'self_card_sharing', $book_columns, true )
		&& axismundi_contacts_has_index( axismundi_contacts_profiles_table(), 'actor_id' )
		&& axismundi_contacts_has_index( $cards, 'owner_uid' )
		&& in_array( 'sharing_enabled', $profile_share_columns, true )
		&& ! in_array( 'sharing', $profile_share_columns, true )
		&& in_array( 'pointer', $provenance_columns, true )
		&& in_array( 'source', $provenance_columns, true );
	if ( $valid ) {
		update_option( AXISMUNDI_CONTACTS_DB_VERSION_OPTION, AXISMUNDI_CONTACTS_DB_VERSION, false );
	}
	return $valid;
}

/**
 * Move the home account from a key that spelled itself out to one that does not.
 *
 * An entry key is an address. `onlineServices/axismundi` is what a published pointer names and what
 * a provenance row is written against, so a key that reads as a label ties both to a word somebody
 * is free to change -- and what the entry is called belongs in `service`, which is a value.
 *
 * Only the Card an Actor publishes about itself, which is the only place this site ever wrote that
 * key. A contact somebody imported may have arrived with an entry called `axismundi` for reasons of
 * its own -- a Card exported from another site that used the same word -- and rewriting it would be
 * this migration editing a document it did not author, to fix a convention that document never had.
 *
 * Four things say that address and all four move together or none of them do: the entry in the Card,
 * any localization patching into it, the pointers its owner selected for publication, and the
 * provenance row saying the value came from an Actor. A Card renamed without its pointer would
 * quietly unpublish an account somebody chose to publish; without its provenance row it would turn a
 * seeded value into an authored one that never refreshes again; without its localization patches it
 * would leave a translation pointing at nothing, which the store refuses. Hence one transaction.
 *
 * Written against the data and safe to run twice. A Card already using the new key has nothing to
 * move; a Card where the new key is taken by something else keeps the old one, because renaming onto
 * an occupied address would merge two accounts into one.
 *
 * @return void
 */
function axismundi_contacts_migrate_home_service_key() : void {
	global $wpdb;
	$cards = axismundi_contacts_cards_table();
	$from  = 'axismundi';
	$to    = AXISMUNDI_CONTACTS_HOME_SERVICE_KEY;
	if ( $from === $to ) {
		return;
	}
	$profiles = axismundi_contacts_profiles_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
	$rows = (array) $wpdb->get_results(
		"SELECT c.id, c.owner_actor_id FROM {$cards} c
			INNER JOIN {$profiles} p ON p.card_id = c.id
			WHERE c.card_json LIKE '%\"onlineServices\"%'",
		ARRAY_A
	);
	foreach ( $rows as $row ) {
		axismundi_contacts_move_entry_key(
			(int) $row['id'],
			(int) $row['owner_actor_id'],
			axismundi_contacts_card_document( (int) $row['id'] ),
			'onlineServices',
			$from,
			$to
		);
	}
}

/**
 * Move one entry from one address to another, with everything that addresses it.
 *
 * Four things say where an entry lives and all four move together or none of them do: the key in
 * the Card, any language patching a path through it, the provenance row saying where the value came
 * from, and the pointer saying its owner agreed it could be published. A Card renamed without its
 * pointer quietly unpublishes something somebody chose to publish; without its provenance row a
 * seeded value becomes an authored one that never refreshes again; without its patches a translation
 * points at nothing, which the store refuses. Hence one transaction.
 *
 * @param int                 $card_id  Card id.
 * @param int                 $owner    Owning Actor identity.
 * @param array<string,mixed> $document Card document, already read.
 * @param string              $property Which collection the entry is in.
 * @param string              $from     The address it has.
 * @param string              $to       The address it should have.
 * @return bool Whether it moved.
 */
function axismundi_contacts_move_entry_key( int $card_id, int $owner, array $document, string $property, string $from, string $to ) : bool {
	global $wpdb;
	$entries = (array) ( $document[ $property ] ?? array() );
	if ( $from === $to || ! isset( $entries[ $from ] ) || isset( $entries[ $to ] ) ) {
		// Nothing to move, or somewhere already occupied -- renaming onto that would merge two entries.
		return false;
	}
	// Rebuilt rather than reassigned, so the entry keeps the position it had in the document.
	$moved = array();
	foreach ( $entries as $key => $entry ) {
		$moved[ $from === (string) $key ? $to : (string) $key ] = $entry;
	}
	$document[ $property ] = $moved;

	$was = $property . '/' . $from;
	$now = $property . '/' . $to;
	$localizations = (array) ( $document['localizations'] ?? array() );
	foreach ( $localizations as $tag => $patch ) {
		$rewritten = array();
		foreach ( (array) $patch as $path => $patched ) {
			$path = (string) $path;
			if ( $was === $path || str_starts_with( $path, $was . '/' ) ) {
				$path = $now . substr( $path, strlen( $was ) );
			}
			$rewritten[ $path ] = $patched;
		}
		$localizations[ $tag ] = $rewritten;
	}
	if ( array() !== $localizations ) {
		$document['localizations'] = $localizations;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
	$wpdb->query( 'START TRANSACTION' );
	$saved = axismundi_contacts_save_card_for_owner( $owner, $document, $card_id );
	if ( is_wp_error( $saved ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'ROLLBACK' );
		return false;
	}
	/*
	 * Anything already written against the new address is stale by definition: this Card has no entry
	 * there -- one that did was left alone above -- so the row records where a value came from that
	 * nothing on the Card holds any more. It goes inside the same transaction, because the alternative
	 * is the rename failing on a unique key and leaving the Card renamed with its provenance behind.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
	$wpdb->delete( axismundi_contacts_provenance_table(), array( 'card_id' => $card_id, 'pointer' => $now ), array( '%d', '%s' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
	$wpdb->update(
		axismundi_contacts_provenance_table(),
		array( 'pointer' => $now ),
		array( 'card_id' => $card_id, 'pointer' => $was ),
		array( '%s' ),
		array( '%d', '%s' )
	);
	$pointers = axismundi_contacts_published_pointers( $owner );
	if ( in_array( $was, $pointers, true ) ) {
		$pointers = array_map(
			static function ( string $pointer ) use ( $was, $now ) : string {
				return $was === $pointer ? $now : $pointer;
			},
			$pointers
		);
		$published = axismundi_contacts_set_published_pointers( $owner, $pointers );
		if ( is_wp_error( $published ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
	$wpdb->query( 'COMMIT' );
	return true;
}

/**
 * Move phone addresses onto the name the collections agreed on.
 *
 * `pho-` was written for a few days before the prefixes were settled, and `tel-` is what a phone
 * entry is called: it reads straight in an export beside `"number": "tel:+82…"`, and a document
 * where the same collection is addressed two ways depending on when it was written is one nobody
 * can search or explain.
 *
 * Safe to run twice, and safe on a Card that has both -- the one already moved is left alone.
 *
 * @return void
 */
function axismundi_contacts_migrate_phone_keys() : void {
	global $wpdb;
	$cards = axismundi_contacts_cards_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
	$rows = (array) $wpdb->get_results( "SELECT id, owner_actor_id, card_json FROM {$cards} WHERE card_json LIKE '%\"pho-%'", ARRAY_A );
	foreach ( $rows as $row ) {
		$document = json_decode( (string) ( $row['card_json'] ?? '' ), true );
		if ( ! is_array( $document ) ) {
			continue;
		}
		foreach ( array_keys( (array) ( $document['phones'] ?? array() ) ) as $key ) {
			$key = (string) $key;
			if ( ! str_starts_with( $key, 'pho-' ) ) {
				continue;
			}
			$current = axismundi_contacts_card_document( (int) $row['id'] );
			/*
			 * Usually the same address with the collection's name on it, so a diff of this migration
			 * reads as one word changing. When a Card already holds that address -- two entries whose
			 * ids differ only by the prefix -- the number moves to a new one instead. Merging them
			 * would put one number's provenance and publishing consent onto another; leaving it would
			 * leave a `pho-` behind for good, and the address a document uses would depend on the week
			 * it was written.
			 */
			$target = 'tel-' . substr( $key, 4 );
			while ( isset( $current['phones'][ $target ] ) ) {
				$target = 'tel-' . wp_generate_uuid4();
			}
			axismundi_contacts_move_entry_key(
				(int) $row['id'],
				(int) $row['owner_actor_id'],
				$current,
				'phones',
				$key,
				$target
			);
		}
	}
}

/**
 * Move Cards from `belongs to one book` to `owned by an Actor, filed into books`.
 *
 * Written against the columns rather than against the recorded version, so it is safe to run on a
 * fresh install, on a half-finished upgrade, and twice. dbDelta will not drop a column or loosen one
 * that has data behind it, so everything that changes an existing shape is done here explicitly.
 *
 * @return void
 */
function axismundi_contacts_migrate_books_to_memberships() : void {
	global $wpdb;
	$books       = axismundi_contacts_books_table();
	$cards       = axismundi_contacts_cards_table();
	$memberships = axismundi_contacts_memberships_table();

	/*
	 * Sharing splits into whether and to whom. An `off` row has no record of which audience it had, so
	 * it takes the narrower one: a setting nobody chose should not be the more revealing of the two.
	 */
	$profiles = axismundi_contacts_profiles_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check.
	$profile_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$profiles}" );
	if ( in_array( 'sharing', $profile_columns, true ) && in_array( 'sharing_enabled', $profile_columns, true ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
		$wpdb->query( "UPDATE {$profiles} SET sharing_enabled = 1, audience = 'contacts' WHERE sharing = 'contacts'" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
		$wpdb->query( "UPDATE {$profiles} SET sharing_enabled = 1, audience = 'public' WHERE sharing = 'public'" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
		$wpdb->query( "ALTER TABLE {$profiles} DROP COLUMN sharing" );
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check.
	$card_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$cards}" );

	if ( in_array( 'address_book_id', $card_columns, true ) && in_array( 'owner_actor_id', $card_columns, true ) ) {
		/*
		 * Ownership comes from the book the Card was in, and the book it was in becomes its first
		 * membership. Both before the column goes, because afterwards there is nothing left to read
		 * it from.
		 */
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own tables.
		$wpdb->query( "UPDATE {$cards} c INNER JOIN {$books} b ON b.id = c.address_book_id SET c.owner_actor_id = b.owner_actor_id WHERE c.owner_actor_id = 0" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own tables.
		$wpdb->query( "INSERT IGNORE INTO {$memberships} (address_book_id, card_id, created_at) SELECT c.address_book_id, c.id, c.created_at FROM {$cards} c" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
		$wpdb->query( "ALTER TABLE {$cards} DROP COLUMN address_book_id" );
	}

	/*
	 * An empty uid is not a uid. It has to become NULL before the unique key exists, or the second
	 * Card an Actor saves without one collides with the first.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
	$wpdb->query( "UPDATE {$cards} SET uid = NULL WHERE uid = ''" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
	$wpdb->query( "ALTER TABLE {$cards} MODIFY uid varchar(191) DEFAULT NULL" );
	axismundi_contacts_drop_index( $cards, 'uid' );
	axismundi_contacts_drop_index( $cards, 'address_book_id' );
	axismundi_contacts_add_index( $cards, 'UNIQUE KEY owner_uid (owner_actor_id, uid)', 'owner_uid' );
	axismundi_contacts_add_index( $cards, 'KEY owner_actor_id (owner_actor_id)', 'owner_actor_id' );

	/*
	 * Every book that already exists is the only one its Actor has, so it is their default. The key
	 * that used to say `one book per Actor` has to go before a second one can be made.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
	$wpdb->query( "UPDATE {$books} SET is_default = 1 WHERE is_default IS NULL" );
	axismundi_contacts_drop_index( $books, 'owner_actor_id' );
	axismundi_contacts_add_index( $books, 'UNIQUE KEY owner_default (owner_actor_id, is_default)', 'owner_default' );
	axismundi_contacts_add_index( $books, 'KEY owner_actor_id (owner_actor_id)', 'owner_actor_id' );

	/*
	 * Which Card describes an Actor moves off the book and onto the Actor. It was only ever on the
	 * default book because that row happened to exist per Actor, and a second book would have made
	 * the question `whose audience wins` out of something that has one answer.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check.
	$book_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$books}" );
	if ( in_array( 'self_card_id', $book_columns, true ) ) {
		$profiles = axismundi_contacts_profiles_table();
		$sharing  = in_array( 'self_card_sharing', $book_columns, true ) ? 'b.self_card_sharing' : "'off'";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own tables.
		$wpdb->query(
			"INSERT IGNORE INTO {$profiles} (actor_id, card_id, sharing, created_at, updated_at)
				SELECT b.owner_actor_id, b.self_card_id, {$sharing}, b.created_at, b.updated_at
				FROM {$books} b WHERE b.self_card_id IS NOT NULL"
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
		$wpdb->query( "ALTER TABLE {$books} DROP COLUMN self_card_id" );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check.
	$book_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$books}" );
	if ( in_array( 'self_card_sharing', $book_columns, true ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
		$wpdb->query( "ALTER TABLE {$books} DROP COLUMN self_card_sharing" );
	}
}

/**
 * Whether a table carries an index by that name.
 *
 * @param string $table Table name.
 * @param string $index Index name.
 * @return bool
 */
function axismundi_contacts_has_index( string $table, string $index ) : bool {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name = %s", $index ), ARRAY_A );
	return array() !== $rows;
}

/**
 * Drop an index if it is there.
 *
 * @param string $table Table name.
 * @param string $index Index name.
 * @return void
 */
function axismundi_contacts_drop_index( string $table, string $index ) : void {
	global $wpdb;
	if ( ! axismundi_contacts_has_index( $table, $index ) ) {
		return;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
	$wpdb->query( "ALTER TABLE {$table} DROP INDEX `{$index}`" );
}

/**
 * Add an index if it is not there.
 *
 * @param string $table      Table name.
 * @param string $definition Index definition, as it appears after ADD.
 * @param string $index      Index name.
 * @return void
 */
function axismundi_contacts_add_index( string $table, string $definition, string $index ) : void {
	global $wpdb;
	if ( axismundi_contacts_has_index( $table, $index ) ) {
		return;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration in this plugin's own table.
	$wpdb->query( "ALTER TABLE {$table} ADD {$definition}" );
}
