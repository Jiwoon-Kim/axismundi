<?php
/**
 * The structured name Actors used to hold, and what to do about the copy left behind.
 *
 * Until the card took it over, Actors held `given`, `surname` and the order they read in, and its
 * profile screen edited them. The migration that moved them refused to overwrite a card that
 * already said the same things -- correctly, because a card somebody authored is not superseded by
 * an older copy of the same fact. But that leaves two editable copies for as long as both screens
 * exist, and after the migration each side could be edited without the other hearing about it.
 *
 * So the two may now disagree, and nothing in the data says which one is later. A row carries no
 * timestamp that means "somebody typed this", string similarity is not evidence, and picking the
 * longer or the newer-looking value would be this code deciding whose name is right. Four states,
 * and only one of them needs a person:
 *
 *   nothing on the Actor         nothing to do
 *   nothing on the card          the Actor's values are carried over -- no question is being begged
 *   the two agree                already carried over; nothing to do
 *   the two disagree             left alone, both of them, and offered as a question
 *
 * The last is the whole point of this file. It is not resolved here and it is not resolved by a
 * migration; it is shown on the profile screen with both readings and settled by whoever owns the
 * name.
 *
 * Nothing here calls Actors' own assembler. That is being removed, and a reconciliation that stopped
 * working the moment the thing it reconciles was deleted would be no use at all -- so the columns
 * are read as data and turned into a JSContact name here.
 *
 * All of this goes when the columns do.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the Actor profile still has the columns this reads.
 *
 * Asked rather than assumed, because the two plugins upgrade independently and in either order. An
 * install whose Actors has already dropped them has nothing left to reconcile, which is the answer
 * rather than a failure.
 *
 * Remembered for the request, because the reconciliation asks it once per Actor. The upgrade that
 * removes the columns runs in a request like any other, so it asks again rather than trusting an
 * answer from before it started.
 *
 * @param bool $recheck Ask the database again rather than reusing this request's answer.
 * @return bool
 */
function axismundi_contacts_legacy_name_columns_present( bool $recheck = false ) : bool {
	global $wpdb;
	static $present = null;
	if ( null !== $present && ! $recheck ) {
		return $present;
	}
	if ( ! function_exists( 'axismundi_actors_profile_table' ) ) {
		$present = false;
		return $present;
	}
	$table = axismundi_actors_profile_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	$present = in_array( 'surname', $columns, true ) && in_array( 'display_order', $columns, true );
	return $present;
}

/**
 * What separates the parts of a legacy row, said outright.
 *
 * A transcription and not a judgement. Actors joined a family-first name with nothing between the
 * parts when neither part was written in Latin letters, which is how `김지운` came out right without
 * anybody choosing a separator; and the compact orders said so directly. Both are read here and
 * written down as the JSContact separator they always amounted to, so that the card states in one
 * field what Actors worked out in two places.
 *
 * @param array<string,mixed> $row Actor profile row.
 * @return string
 */
function axismundi_contacts_legacy_name_separator( array $row ) : string {
	$order = (string) ( $row['display_order'] ?? '' );
	if ( in_array( $order, array( 'family-given-compact', 'given-family-compact' ), true ) ) {
		return '';
	}
	if ( 'family-given' === $order ) {
		$latin = (string) ( $row['surname'] ?? '' ) . (string) ( $row['given'] ?? '' );
		return preg_match( '/[A-Za-z]/', $latin ) ? ' ' : '';
	}
	return ' ';
}

/**
 * The Actor's legacy structured name, as a JSContact name.
 *
 * The components, and nothing else on the row. The label beside them is not legacy: `display_name`
 * is what this Actor is shown as, it is still Actors' own, and it is still edited there -- so it is
 * not something to be carried anywhere, and reading it here would mean every Actor with a chosen
 * label looked like an Actor whose name disagreed with its card.
 *
 * A row that reads `custom` has no components to carry either. That order meant "show the label,
 * ignore the parts", so the parts were already answering nothing, and the label stays where it is.
 *
 * @param int $actor_id Actor identity.
 * @return array<string,mixed> JSContact Name, or an empty array when the Actor holds none.
 */
function axismundi_contacts_legacy_name( int $actor_id ) : array {
	if ( ! axismundi_contacts_legacy_name_columns_present() || ! function_exists( 'axismundi_actors_person_profile' ) ) {
		return array();
	}
	$row = axismundi_actors_person_profile( $actor_id );
	if ( array() === $row ) {
		return array();
	}
	$order = (string) ( $row['display_order'] ?? '' );
	if ( 'custom' === $order ) {
		return array();
	}
	$sequence = in_array( $order, array( 'family-given', 'family-given-compact' ), true )
		? array( 'surname', 'surname2', 'given', 'given2' )
		: array( 'given', 'given2', 'surname', 'surname2' );
	$components = array();
	foreach ( $sequence as $kind ) {
		$value = trim( (string) ( $row[ $kind ] ?? '' ) );
		if ( '' !== $value ) {
			$components[] = array( '@type' => 'NameComponent', 'kind' => $kind, 'value' => $value );
		}
	}
	if ( array() === $components ) {
		return array();
	}
	$name = array( '@type' => 'Name', 'components' => $components, 'isOrdered' => true );
	$separator = axismundi_contacts_legacy_name_separator( $row );
	if ( '' === $separator ) {
		$name['defaultSeparator'] = '';
	}
	return axismundi_contacts_complete_name( $name );
}

/**
 * The Actor-owned components of one name, by kind, for comparison.
 *
 * @param array<string,mixed> $name Name object.
 * @return array<string,string>
 */
function axismundi_contacts_name_actor_parts( array $name ) : array {
	$parts = array();
	foreach ( (array) ( $name['components'] ?? array() ) as $component ) {
		if ( ! is_array( $component ) ) {
			continue;
		}
		$kind = (string) ( $component['kind'] ?? '' );
		if ( in_array( $kind, AXISMUNDI_CONTACTS_ACTOR_NAME_PARTS, true ) ) {
			$parts[ $kind ] = trim( (string) ( $component['value'] ?? '' ) );
		}
	}
	return $parts;
}

/**
 * Where one Actor stands between its legacy name and its card.
 *
 *   none        the Actor holds no structured name, so there is nothing to carry or to ask about
 *   adoptable   the Actor holds one and the card does not -- safe to carry over unasked
 *   settled     both hold the same one; the migration already did its work
 *   conflict    both hold one and they differ, and only a person can say which is meant
 *
 * @param int $actor_id Actor identity.
 * @return string
 */
function axismundi_contacts_legacy_name_state( int $actor_id ) : string {
	$legacy = axismundi_contacts_legacy_name( $actor_id );
	if ( array() === $legacy ) {
		return 'none';
	}
	$card_id = axismundi_contacts_profile_card( $actor_id );
	if ( $card_id <= 0 ) {
		// No card to disagree with. The values stay where they are rather than being written into a
		// document that does not exist.
		return 'none';
	}
	$card = axismundi_contacts_card_document( $card_id );
	$name = is_array( $card['name'] ?? null ) ? $card['name'] : array();
	$here = axismundi_contacts_name_actor_parts( $name );
	$there = axismundi_contacts_name_actor_parts( $legacy );
	if ( array() === $here ) {
		/*
		 * The card has no components. Adding them is not a disagreement about parts -- there are none
		 * to disagree with -- unless the card also reads as a different name, which is the case where
		 * somebody typed one thing here and something else on the Actor.
		 *
		 * A card made for an Actor is given a written-out name at birth, so requiring the card to be
		 * silent altogether would make a conflict of every profile that has one. What matters is
		 * whether the two read the same.
		 */
		$written = trim( (string) ( $name['full'] ?? '' ) );
		return '' === $written || $written === trim( (string) ( $legacy['full'] ?? '' ) ) ? 'adoptable' : 'conflict';
	}
	if ( $here !== $there ) {
		return 'conflict';
	}
	// The parts agree. A separator that does not is still a difference in how the name reads.
	$separator_here  = isset( $name['defaultSeparator'] ) && '' === $name['defaultSeparator'];
	$separator_there = isset( $legacy['defaultSeparator'] ) && '' === $legacy['defaultSeparator'];
	return $separator_here === $separator_there ? 'settled' : 'conflict';
}

/**
 * Put the Actor's legacy name onto its card, replacing what the card says about the name.
 *
 * Everything a card holds that is not the name is untouched, and so is every localization: this
 * settles one question and is not an import.
 *
 * @param int $actor_id Actor identity.
 * @return true|WP_Error
 */
function axismundi_contacts_write_legacy_name_to_card( int $actor_id ) {
	$legacy  = axismundi_contacts_legacy_name( $actor_id );
	$card_id = axismundi_contacts_profile_card( $actor_id );
	if ( array() === $legacy || $card_id <= 0 ) {
		return new WP_Error( 'ax_contacts_legacy_name_absent', __( 'There is no earlier name to use.', 'axismundi-contacts' ) );
	}
	$card         = axismundi_contacts_card_document( $card_id );
	$card['name'] = $legacy;
	$saved        = axismundi_contacts_save_card_for_owner( $actor_id, $card, $card_id );
	return is_wp_error( $saved ) ? $saved : true;
}

/**
 * Carry one Actor's legacy name over, where doing so answers no question.
 *
 * Nothing happens unless the card is silent about the parts and reads as the same name, which is the
 * whole of what "safe" means here. A disagreement is left standing for somebody to settle.
 *
 * @param int $actor_id Actor identity.
 * @return bool Whether anything was carried over.
 */
function axismundi_contacts_adopt_legacy_name( int $actor_id ) : bool {
	if ( 'adoptable' !== axismundi_contacts_legacy_name_state( $actor_id ) ) {
		return false;
	}
	return true === axismundi_contacts_write_legacy_name_to_card( $actor_id );
}

/**
 * Carry over every legacy name the card side is silent about, and leave every disagreement alone.
 *
 * Safe to run again: an Actor already carried over compares equal and is skipped, and a conflict is
 * skipped by definition. Nothing here resolves anything.
 *
 * @return int How many Actors were carried over.
 */
function axismundi_contacts_reconcile_legacy_names() : int {
	global $wpdb;
	if ( ! axismundi_contacts_legacy_name_columns_present( true ) ) {
		return 0;
	}
	$table = axismundi_actors_profile_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time reconciliation.
	$rows    = (array) $wpdb->get_col( "SELECT identity_id FROM {$table}" );
	$carried = 0;
	foreach ( $rows as $identity_id ) {
		if ( axismundi_contacts_adopt_legacy_name( (int) $identity_id ) ) {
			++$carried;
		}
	}
	return $carried;
}

/**
 * Settle a disagreement the way somebody chose.
 *
 *   card    the card stands, and the Actor stops holding an answer nobody is using
 *   actor   the earlier name is written onto the card, and the two then agree
 *
 * Either way the Actor stops holding a structured name afterwards. Once somebody has said which of
 * the two is meant, the other is not a second opinion worth keeping -- and leaving it there would
 * either put the question back on the screen tomorrow or, where the choice was the Actor's own name,
 * leave a copy that the next edit could make differ all over again.
 *
 * @param int    $actor_id Actor identity.
 * @param string $choice   'card' or 'actor'.
 * @return true|WP_Error
 */
function axismundi_contacts_resolve_legacy_name( int $actor_id, string $choice ) {
	if ( ! in_array( $choice, array( 'card', 'actor' ), true ) ) {
		return new WP_Error( 'ax_contacts_legacy_name_choice', __( 'Choose which name to keep.', 'axismundi-contacts' ) );
	}
	if ( 'conflict' !== axismundi_contacts_legacy_name_state( $actor_id ) ) {
		return new WP_Error( 'ax_contacts_legacy_name_settled', __( 'There is nothing left to settle here.', 'axismundi-contacts' ) );
	}
	if ( 'actor' === $choice ) {
		$written = axismundi_contacts_write_legacy_name_to_card( $actor_id );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
	}
	if ( function_exists( 'axismundi_actors_discard_legacy_structured_name' ) ) {
		axismundi_actors_discard_legacy_structured_name( $actor_id );
	}
	return true;
}
