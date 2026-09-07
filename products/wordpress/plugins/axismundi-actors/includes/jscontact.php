<?php
/**
 * What Actors contributes to an Actor's JSContact Card, and what it no longer owns.
 *
 * The Card itself belongs to Contacts: it is a document somebody authored there, it carries the
 * `uid`, and Contacts serves it. Actors used to build one from the identity registry, which meant
 * two plugins each publishing a Card for the same Actor under different identifiers -- and anybody
 * saving both would have kept the same person twice.
 *
 * It contributes nothing at all now, and that is the point. It used to add the Actor's other
 * languages and the anniversaries it kept, which meant a public contact document carried facts
 * assembled from the identity registry rather than facts somebody wrote on their card and said
 * could be published. Two records of the same thing, one of them published without being chosen.
 *
 * The Card is the ledger. A name in another language, a birthday, a calendar: those are written on
 * the Card, and what a stranger receives is the part of it its owner selected. Published because
 * somebody said so, rather than because a plugin was installed.
 *
 * No `uid` is minted here, by anything, ever. An identifier for an Actor's contact card comes from
 * the Card, and deriving one from the Actor's UUID is what created the duplicate this file no longer
 * does.
 *
 * What is left is the one thing Actors is the authority on for a contact document: what kind of
 * entity an Actor is, in JSContact's vocabulary.
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
