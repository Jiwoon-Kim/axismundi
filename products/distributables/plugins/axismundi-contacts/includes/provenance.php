<?php
/**
 * Where each value in a Card came from.
 *
 * Recorded per entry, not per Card. A Card with a work address imported from Google and a home
 * address somebody typed in is the ordinary result of importing into an address book, so a single
 * `source` column would be wrong for nearly every Card that has ever been imported into -- and the
 * question a sync has to answer is exactly the per-entry one: *may I replace this value?*
 *
 * The pointer is the entry's own address inside the Card:
 *
 *   emails/work          the work email
 *   phones/mobile        the mobile number
 *   name                 the whole name object
 *
 * JSContact addresses its multi-value fields by map key rather than by position, which is what
 * makes this stable: adding an email does not renumber the others, so provenance written last year
 * still points at the value it was written for.
 *
 * This is not contact data and never enters `card_json`. Nobody exports it and no peer receives it;
 * putting an account's sync bookkeeping inside the Card would hand it to whoever asked for a vCard.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Where a value can have come from.
 *
 * `local` is the default and the strongest: somebody wrote it. An imported source is named with the
 * account it came from, so two Google accounts are two sources rather than one.
 */
const AXISMUNDI_CONTACTS_SOURCE_LOCAL = 'local';

/**
 * Record where one entry in a Card came from.
 *
 * Two parts, because they answer different questions and only one of them is bounded. `source` is
 * the kind of thing that wrote the value -- `local`, `google`, `linked-actor` -- and `source_ref`
 * says which one: an account address, an Actor URI, a remote record id. Packing both into one
 * string looks tidier until an Actor URI is longer than the column, at which point the write is
 * rejected and the value quietly belongs to nobody.
 *
 * @param int    $card_id    Card id.
 * @param string $pointer    Entry address inside the Card, e.g. `emails/work`.
 * @param string $source     Kind of source: `local`, `google`, `linked-actor`.
 * @param string $source_ref Which one, when there is more than one of that kind.
 * @return true|WP_Error
 */
function axismundi_contacts_set_provenance( int $card_id, string $pointer, string $source, string $source_ref = '' ) {
	global $wpdb;
	$pointer = trim( $pointer );
	$source  = trim( $source );
	if ( $card_id <= 0 || '' === $pointer || '' === $source ) {
		return new WP_Error( 'ax_contacts_provenance_key', __( 'Provenance needs a card, a pointer and a source.', 'axismundi-contacts' ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$written = $wpdb->replace(
		axismundi_contacts_provenance_table(),
		array(
			'card_id'    => $card_id,
			'pointer'    => $pointer,
			'source'     => $source,
			'source_ref' => $source_ref,
			'locked'     => 0,
			'updated_at' => current_time( 'mysql', true ),
		),
		array( '%d', '%s', '%s', '%s', '%d', '%s' )
	);
	return false === $written
		? new WP_Error( 'ax_contacts_provenance_save', __( 'The provenance could not be saved.', 'axismundi-contacts' ) )
		: true;
}

/**
 * Everything known about where one Card's values came from, keyed by pointer.
 *
 * @param int $card_id Card id.
 * @return array<string,array<string,mixed>>
 */
function axismundi_contacts_card_provenance( int $card_id ) : array {
	global $wpdb;
	if ( $card_id <= 0 ) {
		return array();
	}
	$table = axismundi_contacts_provenance_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE card_id = %d", $card_id ), ARRAY_A );
	$out  = array();
	foreach ( $rows as $row ) {
		$out[ (string) $row['pointer'] ] = $row;
	}
	return $out;
}

/**
 * Whether a sync from one source may replace one entry.
 *
 * The rule that keeps an import from eating somebody's work: a source may replace what it wrote,
 * and nothing else. A value with no provenance is treated as authored here -- unrecorded is not
 * unowned, and assuming otherwise would let the first sync claim every value on the Card.
 *
 * A locked entry is refused even to its own source, which is how "stop changing this one" is said.
 *
 * @param int    $card_id    Card id.
 * @param string $pointer    Entry address inside the Card.
 * @param string $source     Kind of source proposing the change.
 * @param string $source_ref Which one of that kind.
 * @return bool
 */
function axismundi_contacts_source_may_write( int $card_id, string $pointer, string $source, string $source_ref = '' ) : bool {
	$known = axismundi_contacts_card_provenance( $card_id )[ trim( $pointer ) ] ?? array();
	if ( array() === $known ) {
		return AXISMUNDI_CONTACTS_SOURCE_LOCAL === $source;
	}
	if ( 1 === (int) $known['locked'] ) {
		return false;
	}
	// Somebody editing by hand always wins; that is what makes this an address book they keep.
	if ( AXISMUNDI_CONTACTS_SOURCE_LOCAL === $source ) {
		return true;
	}
	/*
	 * Both halves have to match. Two Google accounts are two sources, and a Card re-linked to a
	 * different Actor must not inherit the first one's claim over the values it seeded.
	 */
	return (string) $known['source'] === $source && (string) $known['source_ref'] === $source_ref;
}

/**
 * Pin one entry so no sync touches it again.
 *
 * @param int    $card_id Card id.
 * @param string $pointer Entry address inside the Card.
 * @param bool   $locked  Whether to lock it.
 * @return true|WP_Error
 */
function axismundi_contacts_lock_entry( int $card_id, string $pointer, bool $locked = true ) {
	global $wpdb;
	$pointer = trim( $pointer );
	if ( $card_id <= 0 || '' === $pointer ) {
		return new WP_Error( 'ax_contacts_provenance_key', __( 'Locking an entry needs a card and a pointer.', 'axismundi-contacts' ) );
	}
	$known = axismundi_contacts_card_provenance( $card_id )[ $pointer ] ?? array();
	if ( array() === $known ) {
		// Nothing recorded yet, so record it as authored here and lock that.
		$set = axismundi_contacts_set_provenance( $card_id, $pointer, AXISMUNDI_CONTACTS_SOURCE_LOCAL );
		if ( is_wp_error( $set ) ) {
			return $set;
		}
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update(
		axismundi_contacts_provenance_table(),
		array( 'locked' => $locked ? 1 : 0, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'card_id' => $card_id, 'pointer' => $pointer ),
		array( '%d', '%s' ),
		array( '%d', '%s' )
	);
	return true;
}
