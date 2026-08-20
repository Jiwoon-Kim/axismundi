<?php
/**
 * The Cards themselves, and the index derived from them.
 *
 * Writing a Card is one direction, always:
 *
 *   validate the document
 *     -> store it whole
 *     -> rebuild every index row for it
 *     -> bump the revision
 *
 * Nothing goes the other way. There is no path that edits an index row and expects the Card to
 * follow, because the moment one exists there are two records of the same fact and no way to say
 * which is right.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Card fields that are indexed, and how each entry's value is found.
 *
 * Only what an address book is actually searched by. Everything else stays in the document, where
 * it is kept faithfully and read when a Card is opened.
 */
/** How many trailing digits of a telephone number are matched on. */
const AXISMUNDI_CONTACTS_PHONE_MATCH_DIGITS = 9;

const AXISMUNDI_CONTACTS_INDEXED_FIELDS = array(
	'emails'         => 'address',
	'phones'         => 'number',
	'onlineServices' => 'uri',
	'links'          => 'uri',
);

/**
 * One Card by id.
 *
 * @param int $card_id Card id.
 * @return array<string,mixed>
 */
function axismundi_contacts_get_card( int $card_id ) : array {
	global $wpdb;
	if ( $card_id <= 0 ) {
		return array();
	}
	$table = axismundi_contacts_cards_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $card_id ), ARRAY_A );
	return is_array( $row ) ? $row : array();
}

/**
 * The Card document, decoded.
 *
 * @param int $card_id Card id.
 * @return array<string,mixed>
 */
function axismundi_contacts_card_document( int $card_id ) : array {
	$row  = axismundi_contacts_get_card( $card_id );
	$card = array() !== $row ? json_decode( (string) $row['card_json'], true ) : null;
	return is_array( $card ) ? $card : array();
}

/**
 * Check that a document is a Card this store will keep.
 *
 * Deliberately shallow. JSContact has more fields than this code will ever know about, and a
 * validator that rejected everything it did not recognise would throw away the parts of an imported
 * Card that make it worth importing. So this asks only what the store itself relies on, and keeps
 * the rest verbatim.
 *
 * @param array<string,mixed> $card Card document.
 * @return true|WP_Error
 */
function axismundi_contacts_validate_card( array $card ) {
	if ( 'Card' !== (string) ( $card['@type'] ?? 'Card' ) ) {
		return new WP_Error( 'ax_contacts_card_type', __( 'That is not a JSContact Card.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	foreach ( array_keys( AXISMUNDI_CONTACTS_INDEXED_FIELDS ) as $field ) {
		if ( isset( $card[ $field ] ) && ! is_array( $card[ $field ] ) ) {
			return new WP_Error(
				'ax_contacts_card_field',
				/* translators: %s: JSContact field name. */
				sprintf( __( 'The %s in a Card is a map of entries.', 'axismundi-contacts' ), $field ),
				array( 'status' => 400 )
			);
		}
	}
	/*
	 * The one consistency JSContact itself demands: a Card listing members is a group. Enforced here
	 * rather than suggested, because it is the standard's rule about its own document and a reader
	 * elsewhere is entitled to rely on it.
	 */
	if ( isset( $card['members'] ) ) {
		if ( ! is_array( $card['members'] ) ) {
			return new WP_Error( 'ax_contacts_card_members', __( 'The members of a Card are a map of uids.', 'axismundi-contacts' ), array( 'status' => 400 ) );
		}
		if ( 'group' !== (string) ( $card['kind'] ?? '' ) ) {
			return new WP_Error( 'ax_contacts_card_group', __( 'A card that lists members is a group.', 'axismundi-contacts' ), array( 'status' => 400 ) );
		}
	}
	/*
	 * `kind` is not checked against a closed list. RFC 9553 allows vendor values, an imported Card may
	 * carry one from a newer revision, and rejecting what this code does not recognise would throw
	 * away the part of an import worth having. What is refused is a kind that is not a word at all.
	 */
	if ( isset( $card['kind'] ) && ( ! is_string( $card['kind'] ) || '' === trim( $card['kind'] ) ) ) {
		return new WP_Error( 'ax_contacts_card_kind', __( 'The kind of a Card is a word.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	/*
	 * A Card with nothing in it is not a contact. Everything else is allowed: a name alone, a phone
	 * number alone, an imported document full of fields this code does not read.
	 */
	if ( array() === array_diff_key( $card, array( '@type' => true, 'version' => true, 'kind' => true ) ) ) {
		return new WP_Error( 'ax_contacts_card_empty', __( 'A card needs to say something.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	return true;
}

/**
 * Store a Card, as authored.
 *
 * @param int                  $book_id  Address book the Card belongs to.
 * @param array<string,mixed>  $card     JSContact Card document.
 * @param int                  $card_id  Existing Card to replace, or 0 to create.
 * @param int|null             $revision Revision the edit was written against, or null to skip the check.
 * @return int|WP_Error Card id.
 */
function axismundi_contacts_save_card( int $book_id, array $card, int $card_id = 0, ?int $revision = null ) {
	$book = axismundi_contacts_get_book( $book_id );
	if ( array() === $book ) {
		return new WP_Error( 'ax_contacts_book_missing', __( 'That address book does not exist.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	$saved = axismundi_contacts_save_card_for_owner( (int) $book['owner_actor_id'], $card, $card_id, $revision );
	if ( ! is_wp_error( $saved ) && 0 === $card_id ) {
		// A new Card is filed where it was made. An existing one keeps whatever filing it has.
		axismundi_contacts_add_card_to_book( (int) $saved, $book_id );
	}
	return $saved;
}

/**
 * Store a Card for an owner, filed nowhere in particular.
 *
 * The path for Cards that have no book to be made in. A Group Actor keeps no address book but may
 * still publish a card about itself, and refusing to store one until somebody invents a container
 * for it would be the container deciding what may exist.
 *
 * @param int                 $owner_actor_id Actor that owns the record.
 * @param array<string,mixed> $card           JSContact Card document.
 * @param int                 $card_id        Existing Card to replace, or 0 to create.
 * @param int|null            $revision       Revision the edit was written against, or null to skip the check.
 * @return int|WP_Error Card id.
 */
function axismundi_contacts_save_card_for_owner( int $owner_actor_id, array $card, int $card_id = 0, ?int $revision = null ) {
	global $wpdb;
	if ( $owner_actor_id <= 0 ) {
		return new WP_Error( 'ax_contacts_card_owner', __( 'A card belongs to an Actor.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	$valid = axismundi_contacts_validate_card( $card );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	/*
	 * Made complete on the way in, so that a name with components and no written-out form gets one.
	 * Here rather than in the editor because an import arrives the same way and needs the same thing,
	 * and because the Actor name bindings read `full` alone -- a Card stored without one offers that
	 * language no name to bind, and the Actor stops answering in it.
	 *
	 * Only that direction. Nothing is taken apart, and a `full` somebody wrote is never replaced.
	 */
	$card = axismundi_contacts_complete_card_names( $card );
	/*
	 * And written down in a stable order. Nothing about the Card changes -- JSON says nothing about
	 * the order of an object's keys -- but the stored document, its diff and the Advanced JSON box
	 * are all read by somebody, and a Card whose properties land wherever the last edit put them is
	 * one nobody can scan.
	 */
	$card = axismundi_contacts_canonical_card( $card );
	$existing = $card_id > 0 ? axismundi_contacts_get_card( $card_id ) : array();
	if ( $card_id > 0 && array() === $existing ) {
		return new WP_Error( 'ax_contacts_card_missing', __( 'That card does not exist.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	if ( $card_id > 0 && (int) $existing['owner_actor_id'] !== $owner_actor_id ) {
		return new WP_Error( 'ax_contacts_card_book', __( 'That card belongs to somebody else.', 'axismundi-contacts' ), array( 'status' => 403 ) );
	}
	/*
	 * A Card's uid is its identity, and identity does not change because somebody corrected a phone
	 * number. Once one is stored it stays, whatever the submitted document says: an edit form round
	 * trips the whole Card, and a form that dropped a field it never displayed would otherwise
	 * quietly release an identity other people have already saved. A Card that has none can still be
	 * given one, which is what an import needs.
	 */
	if ( $card_id > 0 && '' !== (string) ( $existing['uid'] ?? '' ) ) {
		$card['uid'] = (string) $existing['uid'];
	}
	/*
	 * A save written against a version somebody else has already replaced is refused rather than
	 * merged. Two people editing one Card is rare; silently keeping the later save and discarding
	 * the earlier one without either of them knowing is the kind of loss nobody reports.
	 */
	if ( null !== $revision && $card_id > 0 && (int) $existing['revision'] !== $revision ) {
		return new WP_Error(
			'ax_contacts_card_conflict',
			__( 'This card changed since it was opened.', 'axismundi-contacts' ),
			array( 'status' => 409, 'revision' => (int) $existing['revision'] )
		);
	}

	$uid = trim( (string) ( $card['uid'] ?? '' ) );
	/*
	 * Refused rather than merged, and the id of the Card already holding that uid is handed back so
	 * whoever asked can offer to merge into it. Two Cards with one uid is not a duplicate contact --
	 * it is the same Card stored twice, and afterwards nothing can say which of the two an update is
	 * meant for.
	 */
	if ( '' !== $uid ) {
		$holder = axismundi_contacts_find_by_uid( $owner_actor_id, $uid );
		if ( 0 !== $holder && $holder !== $card_id ) {
			return new WP_Error(
				'ax_contacts_card_duplicate',
				__( 'A card with that uid is already in this address book.', 'axismundi-contacts' ),
				array( 'status' => 409, 'card_id' => $holder )
			);
		}
	}

	$now     = current_time( 'mysql', true );
	$derived = axismundi_contacts_derive_card_columns( $card );
	$row     = array_merge(
		$derived,
		array(
			'owner_actor_id' => $owner_actor_id,
			'card_json'      => (string) wp_json_encode( $card ),
			'updated_at'     => $now,
		)
	);
	if ( $card_id > 0 ) {
		$row['revision'] = (int) $existing['revision'] + 1;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( axismundi_contacts_cards_table(), $row, array( 'id' => $card_id ), null, array( '%d' ) );
	} else {
		$row['revision']   = 1;
		$row['created_at'] = $now;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		if ( false === $wpdb->insert( axismundi_contacts_cards_table(), $row ) ) {
			return new WP_Error( 'ax_contacts_card_save', __( 'The card could not be saved.', 'axismundi-contacts' ) );
		}
		$card_id = (int) $wpdb->insert_id;
	}
	axismundi_contacts_reindex_card( $card_id, $card );
	// Every book the Card is filed in moved, not just the one the edit came from.
	foreach ( axismundi_contacts_card_books( $card_id ) as $filed ) {
		axismundi_contacts_touch_book( $filed );
	}

	/**
	 * A Card was written.
	 *
	 * Guarded against re-entry because a listener may write back to a place that writes here again:
	 * the profile Card and its Actor keep the same name components, and each side saving carries them
	 * to the other. Without this the first edit would bounce between them.
	 *
	 * @param int                 $card_id        Card id.
	 * @param int                 $owner_actor_id Actor that owns it.
	 * @param array<string,mixed> $card           Card document as stored.
	 */
	static $announcing = false;
	if ( ! $announcing ) {
		$announcing = true;
		do_action( 'axismundi_contacts_card_saved', $card_id, $owner_actor_id, $card );
		$announcing = false;
	}
	return $card_id;
}

/**
 * The Card one owner already keeps under a JSContact uid, if any.
 *
 * @param int    $owner_actor_id Actor identity that owns the Cards.
 * @param string $uid            JSContact uid.
 * @return int Card id, or 0.
 */
function axismundi_contacts_find_by_uid( int $owner_actor_id, string $uid ) : int {
	global $wpdb;
	$uid = trim( $uid );
	if ( $owner_actor_id <= 0 || '' === $uid ) {
		return 0;
	}
	$table = axismundi_contacts_cards_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE owner_actor_id = %d AND uid = %s", $owner_actor_id, $uid ) );
}

/**
 * The columns a Card is looked up by, read out of the document.
 *
 * @param array<string,mixed> $card Card document.
 * @return array<string,mixed>
 */
function axismundi_contacts_derive_card_columns( array $card ) : array {
	$name = trim( (string) ( $card['name']['full'] ?? '' ) );
	if ( '' === $name ) {
		// A Card with components and no assembled name still has to appear in a list.
		$components = is_array( $card['name']['components'] ?? null ) ? $card['name']['components'] : array();
		$name       = trim( implode( ' ', array_map( static fn( $part ) : string => (string) ( $part['value'] ?? '' ), $components ) ) );
	}
	if ( '' === $name ) {
		$name = trim( (string) ( $card['organizations']['org']['name'] ?? '' ) );
	}
	/*
	 * The first account this person has, in the order they put them in. One Card holds as many as
	 * somebody keeps -- a Mastodon account, a blog, a Misskey account, a bridged Bluesky handle -- and
	 * the top one is the one they lead with. This column is that entry, derived rather than chosen
	 * separately, so a list can be drawn without opening every document.
	 */
	$uri = '';
	foreach ( axismundi_contacts_ordered_services( $card ) as $service ) {
		$candidate = trim( (string) ( $service['uri'] ?? '' ) );
		/*
		 * A dereferenceable Actor URI, which is what makes it a link rather than a label. `acct:` is a
		 * locator for one and not one itself. Plain http counts: a site behind a VPN or a development
		 * one is still addressable, and refusing it would mean the link silently never forms there.
		 */
		if ( '' !== $candidate && 1 === preg_match( '#^https?://#', $candidate ) ) {
			$uri = $candidate;
			break;
		}
	}
	$identity_id = 0;
	if ( '' !== $uri && function_exists( 'axismundi_actors_get_by_uri' ) ) {
		$actor       = axismundi_actors_get_by_uri( $uri );
		$identity_id = $actor instanceof Axismundi_Actor ? (int) $actor->get_identity_id() : 0;
	}
	$uid = trim( (string) ( $card['uid'] ?? '' ) );
	return array(
		// NULL rather than '', so that every Card without a uid does not collide on the unique key.
		'uid'                      => '' !== $uid ? $uid : null,
		'display_name'             => $name,
		// Sorted by a case-folded copy, because a list ordered by byte value puts `alice` after `Zoe`.
		'sort_key'                 => mb_strtolower( $name ),
		'linked_actor_uri'         => '' !== $uri ? $uri : null,
		'linked_actor_uri_hash'    => '' !== $uri ? hash( 'sha256', $uri ) : '',
		'linked_actor_identity_id' => $identity_id > 0 ? $identity_id : null,
	);
}

/**
 * A Card's online services, in the order its owner put them in.
 *
 * JSContact keys its multi-value fields by name rather than by position, so the order somebody
 * chose has to be said rather than implied by the JSON. `pref` is where the standard says it: lower
 * is more preferred, and an entry with none sorts after those that have one.
 *
 * The order is not decoration. It decides which account is the one this person leads with, and
 * therefore which face is shown for them.
 *
 * @param array<string,mixed> $card Card document.
 * @return array<int,array<string,mixed>> Entries with their `entry_id` added.
 */
function axismundi_contacts_ordered_services( array $card ) : array {
	$entries = array();
	$index   = 0;
	foreach ( (array) ( $card['onlineServices'] ?? array() ) as $entry_id => $entry ) {
		if ( ! is_array( $entry ) ) {
			continue;
		}
		$entry['entry_id'] = (string) $entry_id;
		// Position within the document breaks ties, so entries with no preference keep their order.
		$entry['_seq']     = $index;
		$entries[]         = $entry;
		++$index;
	}
	usort(
		$entries,
		static function ( array $a, array $b ) : int {
			$pref_a = isset( $a['pref'] ) ? (int) $a['pref'] : PHP_INT_MAX;
			$pref_b = isset( $b['pref'] ) ? (int) $b['pref'] : PHP_INT_MAX;
			return $pref_a === $pref_b ? $a['_seq'] <=> $b['_seq'] : $pref_a <=> $pref_b;
		}
	);
	return array_map(
		static function ( array $entry ) : array {
			unset( $entry['_seq'] );
			return $entry;
		},
		$entries
	);
}

/**
 * Rebuild the index rows for one Card.
 *
 * Replaced wholesale rather than diffed: an entry removed from the document has to leave the index
 * with it, and reconciling two shapes is more code than writing the derived one again.
 *
 * @param int                 $card_id Card id.
 * @param array<string,mixed> $card    Card document.
 * @return void
 */
function axismundi_contacts_reindex_card( int $card_id, array $card ) : void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_contacts_values_table(), array( 'card_id' => $card_id ), array( '%d' ) );
	foreach ( AXISMUNDI_CONTACTS_INDEXED_FIELDS as $field => $value_key ) {
		foreach ( (array) ( $card[ $field ] ?? array() ) as $entry_id => $entry ) {
			$value = is_array( $entry ) ? trim( (string) ( $entry[ $value_key ] ?? '' ) ) : '';
			if ( '' === $value ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
			$wpdb->insert(
				axismundi_contacts_values_table(),
				array(
					'card_id'          => $card_id,
					'field'            => $field,
					'entry_id'         => (string) $entry_id,
					'value'            => $value,
					'normalized_value' => axismundi_contacts_normalize_value( $field, $value ),
					'pref'             => isset( $entry['pref'] ) ? (int) $entry['pref'] : null,
				)
			);
		}
	}
}

/**
 * A value reduced to what matching needs, never to what is shown.
 *
 * A phone number typed as `010-1234-5678` and the same number as `+82 10 1234 5678` are one number,
 * and somebody searching for either should find the Card. What they wrote stays in the document;
 * this is only the key it is found by.
 *
 * @param string $field Field the value came from.
 * @param string $value Value as authored.
 * @return string
 */
function axismundi_contacts_normalize_value( string $field, string $value ) : string {
	if ( 'phones' === $field ) {
		/*
		 * The last nine digits, which is what makes `010-1234-5678` and `+82 10 1234 5678` the same
		 * number. They are the same number, and any key that keeps them apart fails the first time
		 * somebody saves a contact from a business card and later searches for it from their call log.
		 *
		 * Deliberately not an attempt to parse dialling plans: doing that properly needs a country for
		 * every number and a copy of every national numbering rule, and getting it half right turns a
		 * search key into a wrong answer. A suffix collides occasionally, which is acceptable in a
		 * personal address book because this is how a Card is *found* and never how two Cards are
		 * decided to be the same person.
		 */
		$digits = (string) preg_replace( '/[^0-9]/', '', $value );
		return strlen( $digits ) > AXISMUNDI_CONTACTS_PHONE_MATCH_DIGITS
			? substr( $digits, -AXISMUNDI_CONTACTS_PHONE_MATCH_DIGITS )
			: $digits;
	}
	return mb_strtolower( trim( $value ) );
}

/**
 * The Cards in one address book, in the order a person reads a list.
 *
 * @param int $book_id Address book id.
 * @param int $limit   Maximum rows.
 * @return array<int,array<string,mixed>>
 */
function axismundi_contacts_cards_in_book( int $book_id, int $limit = 200 ) : array {
	global $wpdb;
	if ( $book_id <= 0 ) {
		return array();
	}
	$table       = axismundi_contacts_cards_table();
	$memberships = axismundi_contacts_memberships_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own tables.
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT c.* FROM {$table} c
				INNER JOIN {$memberships} m ON m.card_id = c.id
				WHERE m.address_book_id = %d
				ORDER BY c.sort_key ASC, c.id ASC LIMIT %d",
			$book_id,
			$limit
		),
		ARRAY_A
	);
}

/**
 * Every Card one Actor owns, whether or not it has been filed into a book.
 *
 * @param int $owner_actor_id Actor identity.
 * @param int $limit          Maximum rows.
 * @return array<int,array<string,mixed>>
 */
function axismundi_contacts_cards_for_owner( int $owner_actor_id, int $limit = 200 ) : array {
	global $wpdb;
	if ( $owner_actor_id <= 0 ) {
		return array();
	}
	$table = axismundi_contacts_cards_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE owner_actor_id = %d ORDER BY sort_key ASC, id ASC LIMIT %d", $owner_actor_id, $limit ),
		ARRAY_A
	);
}

/**
 * Count every Card one Contacts account keeps.
 *
 * This is deliberately separate from `cards_for_owner()`: the list is capped for
 * an admin screen, while the sidebar count must not quietly become "200+" because
 * somebody has a real address book.
 *
 * @param int $owner_actor_id Contacts-account Actor identity.
 * @return int
 */
function axismundi_contacts_card_count_for_owner( int $owner_actor_id ) : int {
	global $wpdb;
	if ( $owner_actor_id <= 0 ) {
		return 0;
	}
	$table = axismundi_contacts_cards_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed count in this plugin's own table.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE owner_actor_id = %d", $owner_actor_id ) );
}

/**
 * Count the Cards filed into one AddressBook.
 *
 * @param int $book_id AddressBook id.
 * @return int
 */
function axismundi_contacts_card_count_in_book( int $book_id ) : int {
	global $wpdb;
	if ( $book_id <= 0 ) {
		return 0;
	}
	$table = axismundi_contacts_memberships_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed count in this plugin's own table.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE address_book_id = %d", $book_id ) );
}

/**
 * Find Cards in one book by a value somebody remembers.
 *
 * @param int    $book_id Address book id.
 * @param string $needle  Email, number, handle or URI.
 * @return array<int,array<string,mixed>>
 */
function axismundi_contacts_find_by_value( int $book_id, string $needle ) : array {
	global $wpdb;
	$needle = trim( $needle );
	if ( $book_id <= 0 || '' === $needle ) {
		return array();
	}
	$cards       = axismundi_contacts_cards_table();
	$values      = axismundi_contacts_values_table();
	$memberships = axismundi_contacts_memberships_table();
	/*
	 * Matched against the normalized column in both directions -- the stored value is normalized on
	 * write, and what somebody typed is normalized here, so `010-1234-5678` finds `+821012345678`.
	 */
	// Normalized by the same function that wrote the index, so the two cannot drift apart.
	$phone = axismundi_contacts_normalize_value( 'phones', $needle );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own tables.
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DISTINCT c.* FROM {$cards} c
				INNER JOIN {$values} v ON v.card_id = c.id
				INNER JOIN {$memberships} m ON m.card_id = c.id
				WHERE m.address_book_id = %d AND ( v.normalized_value = %s OR ( %s <> '' AND v.normalized_value = %s ) )
				ORDER BY c.sort_key ASC",
			$book_id,
			mb_strtolower( $needle ),
			$phone,
			$phone
		),
		ARRAY_A
	);
}

/**
 * Remove a Card and everything derived from it.
 *
 * @param int $card_id Card id.
 * @return true
 */
function axismundi_contacts_delete_card( int $card_id ) : bool {
	global $wpdb;
	$card = axismundi_contacts_get_card( $card_id );
	if ( array() === $card ) {
		return true;
	}
	// Read before the rows go, so every book the Card was filed in still gets told it moved.
	$books = axismundi_contacts_card_books( $card_id );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_contacts_memberships_table(), array( 'card_id' => $card_id ), array( '%d' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_contacts_values_table(), array( 'card_id' => $card_id ), array( '%d' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_contacts_provenance_table(), array( 'card_id' => $card_id ), array( '%d' ) );
	/*
	 * And any Actor that pointed at this Card as the one describing it. A binding left behind answers
	 * `which Card describes this Actor` with an id that resolves to nothing -- and every reader has to
	 * handle that, or quietly show an Actor as having a profile card it does not have.
	 *
	 * Addressed by card rather than by Actor, because that is what is known here: the Card is going,
	 * and whoever was pointing at it stops pointing at it, whether or not they own it.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_contacts_profiles_table(), array( 'card_id' => $card_id ), array( '%d' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_contacts_cards_table(), array( 'id' => $card_id ), array( '%d' ) );
	foreach ( $books as $book_id ) {
		axismundi_contacts_touch_book( $book_id );
	}
	return true;
}
