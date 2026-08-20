<?php
/**
 * An Actor's name following the card that holds it.
 *
 * The card is where a person's name lives: `full`, the components, the order they are read in, a
 * title and a credential. An Actor publishes one string per language, and which of the card's
 * writings a language shows is a binding somebody makes -- `ko-Latn` is how a Korean name is
 * written in Latin script and `en` may be a different name entirely, so nothing here derives one
 * from the other.
 *
 * One direction only. A card edit reaches every locale pointed at it; nothing is ever written back,
 * and a locale that follows nothing keeps the string it was given. There is no second copy of a
 * name to fall out of step with the first, which is most of why this file is as short as it is.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * A saved profile Card reaches every Actor locale that follows one of its names.
 *
 * One direction only. Nothing is written back into the identity registry: the card holds the name,
 * and an Actor locale either follows one of its writings or holds a string somebody typed.
 *
 * @param int                 $card_id  Card id.
 * @param int                 $actor_id Owner.
 * @param array<string,mixed> $document Card document.
 * @return void
 */
function axismundi_contacts_on_card_saved( int $card_id, int $actor_id, array $document ) : void {
	// Every Actor locale pointed at one of this card's names follows it.
	axismundi_contacts_refresh_bound_names( $actor_id );
}
add_action( 'axismundi_contacts_card_saved', 'axismundi_contacts_on_card_saved', 10, 3 );

/** What an Actor text bound to a contact card records as its source. */
const AXISMUNDI_CONTACTS_NAME_SOURCE = 'contact-card';

/**
 * The names this Actor's own card offers, as things an Actor locale can be pointed at.
 *
 * Candidates, not assignments. `ko-Latn` is how a Korean name is written in Latin script and `en`
 * may be a different name entirely, so which of them an English-speaking reader should see is a
 * choice somebody makes once -- never a mapping this code derives.
 *
 * @param int $actor_id Actor identity.
 * @return array<string,string> Source tag => name, with '' for the card's primary name.
 */
function axismundi_contacts_name_representations( int $actor_id ) : array {
	$card_id = axismundi_contacts_profile_card( $actor_id );
	if ( $card_id <= 0 ) {
		return array();
	}
	$card    = axismundi_contacts_card_document( $card_id );
	$offered = array();
	// Read rather than looked up: a name given in components and no written-out form still offers the
	// string it reads as, and the Card is not rewritten so that this function can find one.
	$primary = trim( axismundi_contacts_name_text( is_array( $card['name'] ?? null ) ? $card['name'] : array() ) );
	if ( '' !== $primary ) {
		// The card's own name, whose tag is empty because it is not one of the localizations.
		$offered[''] = $primary;
	}
	foreach ( axismundi_contacts_localized_name_tags( $card ) as $tag ) {
		$full = trim( axismundi_contacts_name_text( axismundi_contacts_localized_name( $card, $tag ) ) );
		if ( '' !== $full ) {
			$offered[ $tag ] = $full;
		}
	}
	return $offered;
}

/**
 * Carry a changed card into every Actor locale that follows one of its names.
 *
 * A binding is what makes correcting `Jiwoon Kim` to `Ji-woon Kim` reach the four locales that were
 * pointed at it. A locale somebody typed into follows nothing and is left alone.
 *
 * A source that has gone leaves the value standing. The published name is what strangers and remote
 * servers have; deleting a writing in an address book should not empty it, so the binding stays and
 * the screen can say it needs choosing again.
 *
 * @param int $actor_id Actor identity.
 * @return void
 */
function axismundi_contacts_refresh_bound_names( int $actor_id ) : void {
	if ( ! function_exists( 'axismundi_actors_bound_texts' ) ) {
		return;
	}
	$offered = axismundi_contacts_name_representations( $actor_id );
	foreach ( axismundi_actors_bound_texts( $actor_id, AXISMUNDI_CONTACTS_NAME_SOURCE ) as $language => $source_tag ) {
		if ( ! array_key_exists( $source_tag, $offered ) ) {
			// Broken rather than emptied. What was published stays published until somebody chooses.
			continue;
		}
		axismundi_actors_bind_text( $actor_id, 'name', $language, $offered[ $source_tag ], AXISMUNDI_CONTACTS_NAME_SOURCE, $source_tag );
	}
}

/**
 * Point one Actor locale at one of the names on its card.
 *
 * @param int    $actor_id   Actor identity.
 * @param string $language   Actor locale to show it for.
 * @param string $source_tag Which name on the card, '' for its primary one.
 * @return true|WP_Error
 */
function axismundi_contacts_bind_actor_name( int $actor_id, string $language, string $source_tag ) {
	$offered = axismundi_contacts_name_representations( $actor_id );
	if ( ! array_key_exists( $source_tag, $offered ) ) {
		return new WP_Error( 'ax_contacts_name_source', __( 'That card has no name written that way.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	if ( ! function_exists( 'axismundi_actors_bind_text' ) ) {
		return new WP_Error( 'ax_contacts_actors', __( 'Profile names need Axismundi Actors.', 'axismundi-contacts' ) );
	}
	return axismundi_actors_bind_text( $actor_id, 'name', $language, $offered[ $source_tag ], AXISMUNDI_CONTACTS_NAME_SOURCE, $source_tag );
}
