<?php
/**
 * What Actors contributes to an Actor's JSContact Card, and what it no longer owns.
 *
 * The Card itself belongs to Contacts: it is a document somebody authored there, it carries the
 * `uid`, and Contacts serves it. Actors used to build one from the identity registry, which meant
 * two plugins each publishing a Card for the same Actor under different identifiers -- and anybody
 * saving both would have kept the same person twice.
 *
 * So this contributes what Actors is actually the authority on, and nothing else:
 *
 *   language        the Actor's primary language tag
 *   localizations   what the Actor is called in its other languages
 *   anniversaries   the dates it has chosen to publish
 *
 * The name in the primary language is not contributed. It lives on the Contacts Card, kept in step
 * with the Actor's stored name components, because a Card also carries parts Actors does not have --
 * a title, a credential, a separator -- and rebuilding the whole name here on every render would
 * flatten those away.
 *
 * No `uid` is minted here, by anything, ever. An identifier for an Actor's contact card comes from
 * the Card, and deriving one from the Actor's UUID is what created the duplicate this file no longer
 * does.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/**
 * What kind of thing this Actor is, in JSContact's vocabulary.
 *
 * `Service` has no standard value and deliberately gets none: RFC 9553 allows vendor-specific enum
 * values, but minting one before anything consumes it would put an Axismundi-only word into a
 * document whose whole purpose is being read elsewhere.
 *
 * @param string $actor_type ActivityStreams actor type.
 * @return string JSContact kind, or '' when there is no honest answer.
 */
function axismundi_actors_jscontact_kind( string $actor_type ) : string {
	switch ( $actor_type ) {
		case 'Person':
			return 'individual';
		case 'Organization':
			return 'org';
		case 'Group':
			return 'group';
		case 'Application':
			return 'application';
		default:
			return '';
	}
}

/**
 * Add what Actors owns to the Card Contacts is publishing.
 *
 * @param array<string,mixed> $card  Card so far.
 * @param Axismundi_Actor     $actor Actor being described.
 * @return array<string,mixed>
 */
function axismundi_actors_jscontact_contribute( array $card, Axismundi_Actor $actor ) : array {
	$language = axismundi_actors_normalize_language_tag( (string) $actor->get_default_language() );
	if ( '' !== $language && ! isset( $card['language'] ) ) {
		$card['language'] = $language;
	}

	/*
	 * The other languages, as JSContact keeps them: a patch per tag rather than a second Card. Each
	 * carries `full` and nothing else: the parts of a name are the card's, and an Actor's other
	 * languages are written-out names rather than sets of components.
	 */
	$map = axismundi_actors_get_text_map( $actor->get_identity_id() );

	$localizations = array();
	foreach ( $map as $tag => $fields ) {
		$tag     = (string) $tag;
		$written = trim( (string) ( $fields['name'] ?? '' ) );
		if ( $tag === $language || '' === $written ) {
			continue;
		}
		// The written-out name and nothing else. What an Actor holds for a language is a string
		// somebody typed or bound; claiming components under it would be this site deciding which
		// half of `Jiwoon Kim` is a surname, which nobody asked it to decide.
		$localizations[ $tag ] = array( 'name' => array( '@type' => 'Name', 'full' => $written ) );
	}
	if ( array() !== $localizations ) {
		/*
		 * Only where the Card is silent. A localization on the Card is the contact data somebody
		 * authored; what Actors has for the same tag is a display name it happens to hold, and letting
		 * the second overwrite the first would mean an edit in Contacts vanished on the next render.
		 * So this fills gaps and never replaces -- the same rule that keeps an imported value from
		 * being clobbered by an older copy of the same fact.
		 */
		$existing = (array) ( $card['localizations'] ?? array() );
		$owned    = function_exists( 'axismundi_contacts_localized_name_tags' )
			? axismundi_contacts_localized_name_tags( $card )
			: array_keys( $existing );
		foreach ( $localizations as $tag => $patch ) {
			if ( ! in_array( (string) $tag, $owned, true ) ) {
				$existing[ $tag ] = array_merge( (array) ( $existing[ $tag ] ?? array() ), $patch );
			}
		}
		$card['localizations'] = $existing;
	}

	/*
	 * A birthday, as JSContact states one: an entry in `anniversaries` with `kind: "birth"`, not a
	 * field of its own. The vocabulary is general on purpose -- a death or a wedding is the same shape
	 * with a different kind -- and using it means no reader needs a Misskey-specific vCard extension
	 * to learn when somebody was born.
	 *
	 * `PartialDate` is what lets the year be withheld: a month and a day with no year is a complete,
	 * valid statement, which is exactly "you may wish me happy birthday, you may not have my birth
	 * year".
	 */
	$anniversary_rows = $actor->is_local()
		? axismundi_actors_public_anniversaries( $actor->get_identity_id() )
		: array();
	$anniversaries = array();
	foreach ( $anniversary_rows as $id => $anniversary ) {
		$date = array( '@type' => 'PartialDate' );
		if ( isset( $anniversary['date']['year'] ) ) {
			$date['year'] = (int) $anniversary['date']['year'];
		}
		$date['month'] = (int) $anniversary['date']['month'];
		$date['day']   = (int) $anniversary['date']['day'];
		$anniversaries[ (string) $id ] = array( '@type' => 'Anniversary', 'kind' => (string) $anniversary['kind'], 'date' => $date );
	}
	if ( array() !== $anniversaries ) {
		$card['anniversaries'] = array_merge( (array) ( $card['anniversaries'] ?? array() ), $anniversaries );
	}
	return $card;
}
add_filter( 'axismundi_contacts_jscontact_card', 'axismundi_actors_jscontact_contribute', 10, 2 );

/**
 * One Actor as a JSContact Card, from whoever owns that document.
 *
 * Kept as the name callers already use. It builds nothing: Contacts holds the Card, and without
 * Contacts there is no JSContact representation of an Actor at all. There is deliberately no
 * fallback -- a Card derived from the identity registry would need an identifier, this site would
 * mint one, and the day a real profile Card appeared the published identity would change underneath
 * everybody who had saved it.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_actors_jscontact_card( Axismundi_Actor $actor ) {
	if ( ! function_exists( 'axismundi_contacts_jscontact_card' ) ) {
		return new WP_Error(
			'ax_actors_jscontact_owner',
			__( 'Contact cards need Axismundi Contacts.', 'axismundi-actors' ),
			array( 'status' => 404 )
		);
	}
	return axismundi_contacts_jscontact_card( $actor );
}
