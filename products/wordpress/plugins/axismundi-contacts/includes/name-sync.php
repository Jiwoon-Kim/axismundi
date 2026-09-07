<?php
/**
 * Taking a name from the card that holds it, when somebody says to.
 *
 * The card is where a person's name lives: `full`, the components, the order they are read in, a
 * title and a credential. An Actor publishes one string per language, and which of the card's
 * writings a language shows is a choice somebody makes -- `ko-Latn` is how a Korean name is written
 * in Latin script and `en` may be a different name entirely, so nothing here derives one from the
 * other.
 *
 * Copied once, on request. Choosing a writing takes its value and puts it on that locale; a later
 * card edit does not go looking for it again. The two documents are independent after a profile is
 * first made -- an Actor's display name is what it calls itself in public and a Card's name is a
 * structured record of who somebody is -- and the only thing still inherited between them is the
 * account that says which Actor a Card is about.
 *
 * That independence is the point rather than an omission. A Card edit that rewrote a published
 * display name would make correcting a surname in an address book a change to what strangers see,
 * and it would do it silently, on save. Somebody who wants the two to agree says so, here, and it
 * happens once.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

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
 * Put one of the card's names on one Actor locale.
 *
 * Once, now. What is recorded alongside the value is which writing it was taken from, so the screen
 * can say where it came from and offer to take it again -- not so that anything takes it again on
 * its own. A card edited afterwards leaves this exactly as it is, which is what makes the published
 * name something somebody chose rather than something that moves under them.
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
