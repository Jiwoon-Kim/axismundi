<?php
/**
 * Keeping a profile Card's name and its Actor's name from drifting apart.
 *
 * The Card stores the whole JSContact name -- `full` and every component -- because it has to. A
 * Card that kept only the parts Actors happens to own would lose a title and a credential on the
 * first round trip through an import, and a Card that stored nothing and rebuilt the name on render
 * would not be a store at all.
 *
 * So the same components are written down twice, deliberately, and only for the Card an Actor
 * publishes about itself. Each side keeps what it is the authority on:
 *
 *   Actors     full, given, given2, surname, surname2   the Actor's own name
 *   Contacts   title, credential, separator, phonetic   what a contact card adds to it
 *
 * A save on either side carries the shared parts across and leaves the other side's alone. Nothing
 * else in an address book works this way: an ordinary Card is entirely its owner's, and somebody
 * saving `앨리스 - 디자인팀` for a person whose Actor says `Alice Smith` is right rather than out of
 * step.
 *
 * One thing is never done in either direction. A `full` name is not taken apart into components,
 * and components are not overwritten because a `full` changed. Deciding which half of `Kim Jiwoon`
 * is the surname is a guess, and a guess written into an authority field stops being visibly a
 * guess.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/** The name parts an Actor owns. Everything else on a Card's name belongs to Contacts. */
const AXISMUNDI_CONTACTS_ACTOR_NAME_PARTS = array( 'given', 'given2', 'surname', 'surname2' );

/**
 * Rewrite one Card's name, keeping the components Contacts owns where they are.
 *
 * Components are addressed by kind rather than by position: an Actor-owned part is replaced in
 * place, one that is now empty is dropped, and a title or credential somebody added keeps the slot
 * it was in. Rebuilding the list from the Actor's parts alone would delete them.
 *
 * @param array<string,mixed>  $name  Name object as stored.
 * @param array<string,string> $parts Actor-owned parts, by kind.
 * @param string               $full  Assembled name, or '' to leave whatever is there.
 * @return array<string,mixed>
 */
function axismundi_contacts_merge_actor_name( array $name, array $parts, string $full ) : array {
	$name['@type'] = 'Name';
	if ( '' !== $full ) {
		$name['full'] = $full;
	}
	$components = array();
	$seen       = array();
	foreach ( (array) ( $name['components'] ?? array() ) as $component ) {
		if ( ! is_array( $component ) ) {
			continue;
		}
		$kind = (string) ( $component['kind'] ?? '' );
		if ( ! in_array( $kind, AXISMUNDI_CONTACTS_ACTOR_NAME_PARTS, true ) ) {
			$components[] = $component;
			continue;
		}
		$value = trim( (string) ( $parts[ $kind ] ?? '' ) );
		if ( '' === $value ) {
			// Gone from the Actor, so gone from the Card. Leaving it would publish a surname
			// somebody removed.
			continue;
		}
		$component['value'] = $value;
		$components[]       = $component;
		$seen[ $kind ]      = true;
	}
	foreach ( AXISMUNDI_CONTACTS_ACTOR_NAME_PARTS as $kind ) {
		$value = trim( (string) ( $parts[ $kind ] ?? '' ) );
		if ( '' !== $value && ! isset( $seen[ $kind ] ) ) {
			$components[] = array( '@type' => 'NameComponent', 'kind' => $kind, 'value' => $value );
		}
	}
	if ( array() !== $components ) {
		$name['components'] = $components;
	} else {
		unset( $name['components'], $name['isOrdered'] );
	}
	return $name;
}

/**
 * Carry an Actor's name onto the Card it publishes about itself.
 *
 * @param int $actor_id Actor identity.
 * @return void
 */
function axismundi_contacts_sync_name_from_actor( int $actor_id ) : void {
	$card_id = axismundi_contacts_profile_card( $actor_id );
	if ( $card_id <= 0 || ! function_exists( 'axismundi_actors_get_by_identity' ) ) {
		return;
	}
	$actor = axismundi_actors_get_by_identity( $actor_id );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return;
	}
	$profile = function_exists( 'axismundi_actors_person_profile' ) ? axismundi_actors_person_profile( $actor_id ) : array();
	$parts   = array();
	foreach ( AXISMUNDI_CONTACTS_ACTOR_NAME_PARTS as $kind ) {
		$parts[ $kind ] = trim( (string) ( $profile[ $kind ] ?? '' ) );
	}
	/*
	 * An Actor with no components still has a name. Somebody who never split theirs, an Organization,
	 * a mononym: `full` alone is a complete statement and the components stay absent rather than
	 * being invented from it.
	 */
	$full     = trim( $actor->get_display_name() );
	$document = axismundi_contacts_card_document( $card_id );
	$before   = is_array( $document['name'] ?? null ) ? $document['name'] : array();
	// Kept for the comparison at the end, because `$before` is about to be rearranged.
	$original = $before;

	/*
	 * The Actor's parts are laid out in the order Actors reads them, every time. Reading order is the
	 * Actor's own answer -- `김지운` and `Kim Jiwoon` are not the same sequence, and somebody who
	 * switches theirs has said so once, in the one place that records it. Only those components are
	 * taken from Actors: a credential the Card added keeps its place after them rather than being
	 * emitted twice from two tables that both have a column for it.
	 */
	$ordered = array() !== $profile && function_exists( 'axismundi_actors_jscontact_name' )
		? axismundi_actors_jscontact_name( $profile )
		: null;
	if ( is_array( $ordered ) ) {
		$layout = array();
		foreach ( (array) ( $ordered['components'] ?? array() ) as $component ) {
			if ( is_array( $component ) && in_array( (string) ( $component['kind'] ?? '' ), AXISMUNDI_CONTACTS_ACTOR_NAME_PARTS, true ) ) {
				$layout[] = $component;
			}
		}
		foreach ( (array) ( $before['components'] ?? array() ) as $component ) {
			if ( is_array( $component ) && ! in_array( (string) ( $component['kind'] ?? '' ), AXISMUNDI_CONTACTS_ACTOR_NAME_PARTS, true ) ) {
				$layout[] = $component;
			}
		}
		$before['components'] = $layout;
		foreach ( array( 'isOrdered', 'defaultSeparator' ) as $claim ) {
			if ( isset( $ordered[ $claim ] ) ) {
				$before[ $claim ] = $ordered[ $claim ];
			} else {
				unset( $before[ $claim ] );
			}
		}
	}
	$document['name'] = axismundi_contacts_merge_actor_name( $before, $parts, $full );
	if ( $document['name'] === $original ) {
		return;
	}
	// Saved for the owner rather than into a book: the Card may be filed anywhere or nowhere.
	axismundi_contacts_save_card_for_owner( $actor_id, $document, $card_id );
}

/**
 * Carry the parts an Actor owns back from the Card somebody just edited.
 *
 * Only for a profile Card, and only the Actor's own parts. A title or a credential typed here is
 * the Card's and is not pushed into an identity registry that has nowhere to keep it.
 *
 * @param int                 $card_id  Card that was saved.
 * @param int                 $actor_id Actor that owns it.
 * @param array<string,mixed> $document Card document as saved.
 * @return void
 */
function axismundi_contacts_sync_name_to_actor( int $card_id, int $actor_id, array $document ) : void {
	if ( $card_id <= 0 || axismundi_contacts_profile_card( $actor_id ) !== $card_id ) {
		return;
	}
	if ( ! function_exists( 'axismundi_actors_write_person_profile' ) || ! function_exists( 'axismundi_actors_get_by_identity' ) ) {
		return;
	}
	$actor = axismundi_actors_get_by_identity( $actor_id );
	// Only a Person keeps structured name components; an Organization's name is a name.
	if ( ! $actor instanceof Axismundi_Actor || 'Person' !== $actor->get_type() || ! $actor->is_local() ) {
		return;
	}
	$parts = array();
	foreach ( (array) ( $document['name']['components'] ?? array() ) as $component ) {
		$kind = is_array( $component ) ? (string) ( $component['kind'] ?? '' ) : '';
		if ( in_array( $kind, AXISMUNDI_CONTACTS_ACTOR_NAME_PARTS, true ) ) {
			$parts[ $kind ] = trim( (string) ( $component['value'] ?? '' ) );
		}
	}
	if ( array() === $parts ) {
		/*
		 * A Card with no components says nothing about the Actor's parts. It does not say they are
		 * empty -- a person may keep a card that carries only a full name while their Actor still
		 * records how it is written -- so nothing is cleared here.
		 */
		return;
	}
	foreach ( AXISMUNDI_CONTACTS_ACTOR_NAME_PARTS as $kind ) {
		if ( ! array_key_exists( $kind, $parts ) ) {
			$parts[ $kind ] = '';
		}
	}
	axismundi_actors_write_person_profile( $actor_id, $parts );
}

/**
 * A profile Card edited here updates the Actor whose card it is.
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

/**
 * An Actor profile edited in Actors updates the Card it publishes.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return void
 */
function axismundi_contacts_on_actor_profile_updated( $actor ) : void {
	if ( $actor instanceof Axismundi_Actor ) {
		axismundi_contacts_sync_name_from_actor( (int) $actor->get_identity_id() );
	}
}
/*
 * Deliberately not hooked any more. The card is where a structured name lives, so there is nothing
 * for an Actor write to carry over and nothing to carry back: `axismundi_contacts_sync_name_from_actor()`
 * survives only as what the migration below uses once.
 */

/**
 * Move a title and a credential off the Actor and onto the Card that publishes them.
 *
 * `Dr.` and `PhD` are what a contact card adds to a name, not what an identity registry knows about
 * an agent, and they were only in the Actors profile because Actors used to assemble the whole
 * JSContact document. Both tables having a column for the same component is how one card comes to
 * carry it twice.
 *
 * Run from the Contacts upgrade rather than the Actors one, and written to read the old columns
 * only if they are still there. That way the copy happens while the values still exist, whichever
 * plugin upgrades first, and running it again finds nothing to do.
 *
 * @return void
 */
function axismundi_contacts_adopt_actor_name_extras() : void {
	global $wpdb;
	if ( ! function_exists( 'axismundi_actors_profile_table' ) ) {
		return;
	}
	$profiles = axismundi_actors_profile_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema self-check.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$profiles}" );
	if ( ! in_array( 'title', $columns, true ) && ! in_array( 'credential', $columns, true ) ) {
		return;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration.
	$rows = (array) $wpdb->get_results( "SELECT identity_id, title, credential FROM {$profiles} WHERE title <> '' OR credential <> ''", ARRAY_A );
	foreach ( $rows as $row ) {
		$actor_id = (int) $row['identity_id'];
		$card_id  = axismundi_contacts_profile_card( $actor_id );
		if ( $card_id <= 0 ) {
			// Nothing published about this Actor yet. The value stays where it is rather than being
			// written into a Card that does not exist.
			continue;
		}
		$document   = axismundi_contacts_card_document( $card_id );
		$components = (array) ( $document['name']['components'] ?? array() );
		$present    = array();
		foreach ( $components as $component ) {
			if ( is_array( $component ) ) {
				$present[ (string) ( $component['kind'] ?? '' ) ] = true;
			}
		}
		$changed = false;
		foreach ( array( 'title', 'credential' ) as $kind ) {
			$value = trim( (string) ( $row[ $kind ] ?? '' ) );
			// A component already on the Card is the Card's answer and is not overwritten by an older
			// copy of the same fact.
			if ( '' === $value || isset( $present[ $kind ] ) ) {
				continue;
			}
			// A title leads the name and a credential trails it, which is the order they were read in.
			if ( 'title' === $kind ) {
				array_unshift( $components, array( '@type' => 'NameComponent', 'kind' => 'title', 'value' => $value ) );
			} else {
				$components[] = array( '@type' => 'NameComponent', 'kind' => 'credential', 'value' => $value );
			}
			$changed = true;
		}
		if ( ! $changed ) {
			continue;
		}
		$document['name']               = is_array( $document['name'] ?? null ) ? $document['name'] : array( '@type' => 'Name' );
		$document['name']['components'] = $components;
		axismundi_contacts_save_card_for_owner( $actor_id, $document, $card_id );
	}
}

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
	$primary = trim( (string) ( $card['name']['full'] ?? '' ) );
	if ( '' !== $primary ) {
		// The card's own name, whose tag is empty because it is not one of the localizations.
		$offered[''] = $primary;
	}
	foreach ( axismundi_contacts_localized_name_tags( $card ) as $tag ) {
		$full = trim( (string) ( axismundi_contacts_localized_name( $card, $tag )['full'] ?? '' ) );
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

/**
 * Move the structured name out of the identity registry and onto the card.
 *
 * Kept as the name the upgrade already calls, and now no more than a way in. What it used to do --
 * carry the components across where the card was silent -- is what
 * `axismundi_contacts_reconcile_legacy_names()` does, and doing it in one place means the rule for
 * an upgrade and the rule for an install that has been running for a week are the same rule.
 *
 * The difference that matters is what happens afterwards. This ran once, at the version it was
 * written for, and both screens stayed editable after it; so the two copies can now disagree, and a
 * migration is not who decides that. See `includes/legacy-names.php`.
 *
 * @return void
 */
function axismundi_contacts_adopt_structured_names() : void {
	axismundi_contacts_reconcile_legacy_names();
}
