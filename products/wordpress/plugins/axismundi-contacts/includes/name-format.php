<?php
/**
 * Making a stored name presentation-ready without inventing anything.
 *
 * A JSContact `Name` may carry components, a `full` string, or both. RFC 9553 says a consumer that
 * finds no `full` builds one by joining the components, which means every reader of a Card would
 * otherwise have to implement the same join -- and the Actor name bindings do not implement it at
 * all: `axismundi_contacts_name_representations()` reads `full` and nothing else, so a Card with
 * components and no `full` offers no name to bind, and the Actor's writing in that language goes
 * blank.
 *
 * So the Card is made complete when it is stored, once, rather than each reader guessing. What is
 * written is a derivation and not a guess: the components are already in the order somebody chose,
 * and joining them says nothing that was not said.
 *
 * Nothing here writes. Names are read this way and stored as authored: the one place a `full` is
 * written down is the editor, when somebody has actually typed a name and left the written-out form
 * blank, and that is them asking for it rather than the system deciding.
 *
 * The other direction stays forbidden everywhere. A `full` is never taken apart into components --
 * deciding which half of `Kim Jiwoon` is the surname is a guess, and a guess written into an
 * authority field stops being visibly a guess.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * What separates two components when the Card does not say.
 *
 * A space, because that is what RFC 9553 defines the absent `defaultSeparator` to mean. A Card that
 * wants none says so with an empty string, which is how `김지운` is stated: not a different kind of
 * name, just a separator that is nothing.
 */
const AXISMUNDI_CONTACTS_NAME_SEPARATOR = ' ';

/**
 * Join one name's components into the string it reads as.
 *
 * Components are joined in the sequence they are stored in. That sequence is the only statement of
 * reading order there is, and reordering it by kind would be this code deciding that a name it does
 * not recognise should be read the way an English one is.
 *
 * A `separator` component is a literal: RFC 9553 lets a Card put the exact text between two parts,
 * and where one appears it replaces the default rather than being placed beside it.
 *
 * @param array<string,mixed> $name JSContact Name object.
 * @return string The assembled name, or '' when there are no components to assemble.
 */
function axismundi_contacts_assemble_name( array $name ) : string {
	$default = isset( $name['defaultSeparator'] ) && is_string( $name['defaultSeparator'] )
		? $name['defaultSeparator']
		: AXISMUNDI_CONTACTS_NAME_SEPARATOR;

	$assembled = '';
	$pending   = '';
	$written   = false;
	foreach ( (array) ( $name['components'] ?? array() ) as $component ) {
		if ( ! is_array( $component ) ) {
			continue;
		}
		$kind  = (string) ( $component['kind'] ?? '' );
		$value = (string) ( $component['value'] ?? '' );
		if ( 'separator' === $kind ) {
			// Stated outright, so it stands whether or not it is blank, and the default does not
			// also apply on this join.
			$pending = $value;
			continue;
		}
		$value = trim( $value );
		if ( '' === $value ) {
			continue;
		}
		if ( $written ) {
			$assembled .= $pending;
		}
		$assembled .= $value;
		$pending    = $default;
		$written    = true;
	}
	return $assembled;
}

/**
 * The string one name reads as.
 *
 * `full` when the Card carries one, and the components joined when it does not. RFC 9553 leaves that
 * to whoever displays the name, which is exactly where it belongs: a Card written in components and
 * no written-out form is complete, and answering on its behalf at the point it is shown costs
 * nothing and changes nothing.
 *
 * Not written back. This used to run as Cards were stored, so an import of a components-only Card
 * came out of the database with a `full` its author never wrote -- a document rewritten by the
 * system that was asked to keep it, and an import whose output could no longer be compared with its
 * input.
 *
 * @param array<string,mixed> $name JSContact Name object.
 * @return string
 */
function axismundi_contacts_name_text( array $name ) : string {
	$written = trim( (string) ( $name['full'] ?? '' ) );
	return '' !== $written ? $written : axismundi_contacts_assemble_name( $name );
}
