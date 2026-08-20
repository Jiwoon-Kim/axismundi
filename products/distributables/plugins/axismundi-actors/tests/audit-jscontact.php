<?php
/**
 * An Actor as a contact card (dev-only; dist-excluded).
 *
 * The Card belongs to Contacts. What is checked here is the half Actors still owns -- which JSContact
 * kind an Actor type suggests, how a stored name is read in parts, and the names and dates it
 * contributes to somebody else's document -- and, just as importantly, what it no longer does.
 *
 * Actors used to build a Card of its own and mint a `uid` from the Actor's UUID. Two plugins each
 * publishing a Card for one Actor under different identifiers meant anybody who saved both kept the
 * same person twice, so that is gone and nothing here may bring it back.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_jc_results = array();
$ax_jc_users   = array();

/** @param bool[] $results Results. */
function ax_jc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An activated, published Person. */
function ax_jc_person( array &$users ) : Axismundi_Actor {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axjc' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'author' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axjc' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return axismundi_actors_get_by_identity( $actor->get_identity_id() );
}

try {
	$ax_jc_person = ax_jc_person( $ax_jc_users );
	$ax_jc_id     = $ax_jc_person->get_identity_id();
	axismundi_actors_set_default_language( $ax_jc_id, 'ko-KR' );
	$ax_jc_person = axismundi_actors_get_by_identity( $ax_jc_id );

	// -- what a Card is ---------------------------------------------------------------------------------

	/*
	 * An Actor publishes a Card once Contacts holds one for it. Until then there is none, and Actors
	 * does not derive a stand-in: a derived Card would need an identifier, this site would mint one,
	 * and the day a real Card appeared the published identity would change under everybody who had
	 * saved it.
	 */
	ax_jc_assert(
		$ax_jc_results,
		'an Actor with no contact card publishes none, rather than having one derived from the registry',
		is_wp_error( axismundi_actors_jscontact_card( $ax_jc_person ) )
	);
	axismundi_contacts_book_for_actor( (int) $ax_jc_id );
	$ax_jc_card = axismundi_actors_jscontact_card( $ax_jc_person );
	/*
	 * `@type` is always Card; the sort of thing it describes is `kind`. `{"@type":"Person"}` would be
	 * ActivityStreams wearing a JSContact filename, which is the mistake worth a check of its own.
	 */
	ax_jc_assert(
		$ax_jc_results,
		'a Card says it is a Card, and what it describes is its kind',
		is_array( $ax_jc_card ) && 'Card' === $ax_jc_card['@type'] && 'individual' === $ax_jc_card['kind']
	);
	/*
	 * The identifier is the Card's, not the Actor's. An Actor UUID names an agent in the identity
	 * registry; a `uid` names a contact card in an address book, and deriving one from the other is
	 * how two identities for one person came to exist.
	 */
	ax_jc_assert(
		$ax_jc_results,
		'the identifier belongs to the card and is never derived from the Actor uuid',
		'' !== (string) ( $ax_jc_card['uid'] ?? '' )
			&& 'urn:uuid:' . $ax_jc_person->get_uuid() !== (string) $ax_jc_card['uid']
	);
	ax_jc_assert(
		$ax_jc_results,
		'an Organization and a Group are the kinds JSContact has for them',
		'org' === axismundi_actors_jscontact_kind( 'Organization' )
			&& 'group' === axismundi_actors_jscontact_kind( 'Group' )
			&& '' === axismundi_actors_jscontact_kind( 'Service' )
	);

	// -- what Actors contributes, and what it no longer claims ---------------------------------------------

	/*
	 * The parts of a name are the card's. What Actors contributes to a Card is what it is the
	 * authority on -- the primary language, the other languages it is written in, and the
	 * anniversaries it publishes -- and every one of those other languages is a written-out string
	 * rather than a second set of components.
	 */
	axismundi_actors_set_default_language( $ax_jc_id, 'ko-KR' );
	axismundi_actors_set_text( $ax_jc_id, 'name', 'ko-KR', '김지운' );
	axismundi_actors_set_text( $ax_jc_id, 'name', 'en', 'Jiwoon Kim' );
	$ax_jc_card = axismundi_actors_jscontact_card( axismundi_actors_get_by_identity( $ax_jc_id ) );
	ax_jc_assert(
		$ax_jc_results,
		'a localization carries a written-out name and never components this site would have to invent',
		isset( $ax_jc_card['localizations']['en']['name'] )
			&& 'Jiwoon Kim' === (string) $ax_jc_card['localizations']['en']['name']['full']
			&& ! isset( $ax_jc_card['localizations']['en']['name']['components'] )
			&& ! isset( $ax_jc_card['localizations']['en']['name']['isOrdered'] )
	);
	ax_jc_assert(
		$ax_jc_results,
		'the Actor says which language it answers in, and nothing here builds a name out of parts',
		'ko-KR' === (string) ( $ax_jc_card['language'] ?? '' )
			&& ! function_exists( 'axismundi_actors_jscontact_name' )
	);
	// The name each language answers with is the string stored for it, and nothing else.
	ax_jc_assert(
		$ax_jc_results,
		'and the same facts answer for the display name in each language',
		'김지운' === axismundi_actors_person_display_name( axismundi_actors_get_by_identity( $ax_jc_id ), 'ko-KR' )
			&& 'Jiwoon Kim' === axismundi_actors_person_display_name( axismundi_actors_get_by_identity( $ax_jc_id ), 'en' )
	);

	// -- names that have no parts -------------------------------------------------------------------------

	/*
	 * A mononym, a stage name, an organisation. Splitting those into a surname and a given name is how
	 * software ends up addressing somebody by half of their name, so the parts stay optional and what
	 * was already written stands.
	 */
	$ax_jc_plain = ax_jc_person( $ax_jc_users );
	axismundi_contacts_book_for_actor( (int) $ax_jc_plain->get_identity_id() );
	$ax_jc_pcard = axismundi_actors_jscontact_card( $ax_jc_plain );
	ax_jc_assert(
		$ax_jc_results,
		'somebody who filled in no parts still has a name, and no components are invented for them',
		! isset( $ax_jc_pcard['name']['components'] )
			&& ( ! isset( $ax_jc_pcard['name'] ) || '' !== (string) ( $ax_jc_pcard['name']['full'] ?? '' ) )
	);
	// A profile is authored here for an Actor this site keeps, and for no other.
	ax_jc_assert(
		$ax_jc_results,
		'a profile cannot be written for an Actor that is not ours',
		is_wp_error( axismundi_actors_write_person_profile( $ax_jc_id + 100000, array( 'display_name' => 'Nope' ) ) )
	);

	// -- what is not ours to mint ---------------------------------------------------------------------------

	/*
	 * The UUID generated while caching a remote Actor names our snapshot of them. Publishing it as
	 * their contact identity would hand an address book a permanent identifier for a person that only
	 * exists in this database -- and RFC 9982 makes `uid` optional so that nobody has to.
	 */
	$ax_jc_remote = axismundi_actors_upsert_remote(
		array(
			'uri'                => 'https://example.org/users/axjc' . strtolower( wp_generate_password( 6, false, false ) ),
			'actor_type'         => 'Person',
			'preferred_username' => 'axjcremote',
			'payload'            => array( 'type' => 'Person', 'preferredUsername' => 'axjcremote' ),
			// A cached Actor is only stored with somewhere to deliver to, which is the discovery contract
			// rather than anything this projection cares about.
			'endpoints'          => array( 'inbox' => 'https://example.org/users/axjcremote/inbox', 'outbox' => 'https://example.org/users/axjcremote/outbox' ),
		)
	);
	$ax_jc_rcard = $ax_jc_remote instanceof Axismundi_Actor ? axismundi_actors_jscontact_card( $ax_jc_remote ) : null;
	// Asserted rather than skipped: a fixture that quietly fails takes its check with it.
	ax_jc_assert(
		$ax_jc_results,
		'a cached remote Actor publishes nothing of ours: no card, and so no invented uid either',
		$ax_jc_remote instanceof Axismundi_Actor && is_wp_error( $ax_jc_rcard )
	);
	ax_jc_assert(
		$ax_jc_results,
		'a remote name remains the received document rather than becoming something authored here',
		$ax_jc_remote instanceof Axismundi_Actor
			&& is_wp_error( axismundi_actors_write_person_profile( (int) $ax_jc_remote->get_identity_id(), array( 'display_name' => 'Invented' ) ) )
	);
	// -- contributed, not owned -----------------------------------------------------------------------------

	/*
	 * Actors adds what it is the authority on to somebody else's document, through the filter Contacts
	 * opens. It is registered on the Contacts hook and on no hook of its own: a card built here again,
	 * for any reason, would be the second card this inversion removed.
	 */
	ax_jc_assert(
		$ax_jc_results,
		'Actors contributes to the card Contacts renders rather than rendering one of its own',
		false !== has_filter( 'axismundi_contacts_jscontact_card', 'axismundi_actors_jscontact_contribute' )
			&& ! function_exists( 'axismundi_actors_jscontact_uid' )
			&& ! has_action( 'template_redirect', 'axismundi_actors_serve_jscontact' )
	);

} finally {
	foreach ( $ax_jc_users as $ax_jc_user_id ) {
		wp_delete_user( (int) $ax_jc_user_id );
	}
}

$ax_jc_failures = count( array_filter( $ax_jc_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_jc_results ), $ax_jc_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_jc_failures > 0 ? 1 : 0 );
}
exit( $ax_jc_failures > 0 ? 1 : 0 );
