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
 * The other direction stays forbidden. A `full` is never taken apart into components -- deciding
 * which half of `Kim Jiwoon` is the surname is a guess, and a guess written into an authority field
 * stops being visibly a guess. A name given as one written-out string keeps no components, and this
 * leaves it exactly as it is.
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
 * Give a name a `full` when it has components and no written-out form.
 *
 * Only when it is missing. A `full` somebody typed is what they want read, even where it differs
 * from what the components join to -- somebody who writes `Dr. Kim` and keeps `given` and `surname`
 * beside it has said both things on purpose.
 *
 * @param array<string,mixed> $name JSContact Name object.
 * @return array<string,mixed>
 */
function axismundi_contacts_complete_name( array $name ) : array {
	if ( '' !== trim( (string) ( $name['full'] ?? '' ) ) ) {
		return $name;
	}
	$assembled = axismundi_contacts_assemble_name( $name );
	if ( '' !== $assembled ) {
		$name['full'] = $assembled;
	}
	return $name;
}

/**
 * Complete every name a Card states: its own, and each localization's.
 *
 * A localized name is a name. The reason `full` has to be there is the same for `ko-KR` as for the
 * Card's own -- the bindings offer one writing per tag, from `full` -- so the rule cannot apply to
 * only one of them.
 *
 * A localization may be written whole (`name`) or addressed finely (`name/components/0/value`), and
 * a Card that came from an import commonly uses the second. So the effective name is resolved the
 * way every other reader resolves it, and the `full` is written back in whichever form that
 * localization is already using. Converting one form into the other would rewrite somebody's
 * document to suit this function.
 *
 * @param array<string,mixed> $card Card document.
 * @return array<string,mixed>
 */
function axismundi_contacts_complete_card_names( array $card ) : array {
	if ( is_array( $card['name'] ?? null ) ) {
		$card['name'] = axismundi_contacts_complete_name( $card['name'] );
	}
	$localizations = (array) ( $card['localizations'] ?? array() );
	foreach ( $localizations as $tag => $patch ) {
		if ( ! is_array( $patch ) ) {
			continue;
		}
		$name = axismundi_contacts_localized_name( $card, (string) $tag );
		if ( array() === $name || '' !== trim( (string) ( $name['full'] ?? '' ) ) ) {
			continue;
		}
		$assembled = axismundi_contacts_assemble_name( $name );
		if ( '' === $assembled ) {
			continue;
		}
		if ( is_array( $patch['name'] ?? null ) ) {
			$localizations[ $tag ]['name']['full'] = $assembled;
		} else {
			$localizations[ $tag ]['name/full'] = $assembled;
		}
	}
	if ( array() !== $localizations ) {
		$card['localizations'] = $localizations;
	}
	return $card;
}
