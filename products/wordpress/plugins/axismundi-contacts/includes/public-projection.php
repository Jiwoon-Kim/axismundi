<?php
/**
 * What a Card says to strangers, chosen rather than inherited.
 *
 * A Card is one document holding everything somebody wrote down about a person: a mobile number, a
 * home address, a private email, and notes that are frequently about somebody else. Publishing it
 * because its owner turned sharing on would hand all of that to anybody who asked, and would do it
 * on the strength of a setting that reads like "let people see my profile".
 *
 * So sharing says *that* a Card is published, and this says *what*. Nothing is published unless it
 * was named, one entry at a time.
 *
 * `contexts.private` is not the boundary and is not used as one. RFC 9553 defines contexts as where
 * a value is meant to be used -- somebody's work phone is `contexts.work`, and it is not thereby
 * public. A field carrying no context at all is not public either; it is a field nobody classified.
 * Reading either as permission would be inferring consent from a filing decision.
 *
 * The default is the narrowest thing that is still a contact card: the name, what kind of entity it
 * is, and the accounts and links that are already public elsewhere. Everything else starts
 * unpublished, including on Cards that existed before this did -- a Card published yesterday under
 * a rule of "all of it" does not get to keep publishing all of it because it was there first.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Properties that may be published whole, because every part of them is the same fact.
 *
 * A name is a name; there is no half of `kind` to withhold. Splitting these per entry would offer a
 * choice nobody has.
 */
const AXISMUNDI_CONTACTS_PUBLISHABLE_SINGULAR = array(
	'name',
	'kind',
	'language',
	'speakToAs',
	'preferredLanguages',
	/*
	 * When this Card was written and when it last changed. Not facts about a person -- facts about
	 * the document -- and the ones a reader needs to answer "has this changed since I last looked"
	 * without fetching the whole thing again and comparing it.
	 */
	'created',
	'updated',
	// What wrote it, which is an implementation saying so rather than anything about its subject.
	'prodId',
	/*
	 * Who is in a group. A group Card whose membership is withheld is a group nobody can read, and
	 * the relationship is the thing the Card is for. It names Card uids rather than rows in anybody's
	 * database, so a reader that has none of those members still holds a valid group Card.
	 */
	'members',
);

/**
 * Properties whose entries are chosen one at a time.
 *
 * Every one of these is a map of separately-authored things: four email addresses of which one is
 * for strangers, three notes of which two are about other people. A property-level switch here
 * would mean publishing the private ones to publish the public one.
 */
const AXISMUNDI_CONTACTS_PUBLISHABLE_ENTRIES = array(
	'emails',
	'phones',
	'addresses',
	'onlineServices',
	'links',
	'media',
	'organizations',
	'titles',
	'calendars',
	'schedulingAddresses',
	'keywords',
	'personalInfo',
	'anniversaries',
	'notes',
);

/**
 * What a Card publishes when nobody has said.
 *
 * The name, and what kind of thing this is. Not an email address, not a link, not a photo: those are
 * all reasonable things to publish and all things somebody should have said yes to.
 *
 * @return string[] Pointers.
 */
function axismundi_contacts_default_published() : array {
	return array( 'name', 'kind', 'language' );
}

/**
 * What an Actor says about itself no matter what its owner has chosen to publish.
 *
 * A public Actor already answers with its name, its picture and its handle: that is what a profile
 * is, and anybody can read it from the Actor document at `/actors/<uuid>` or the page at `/@handle`
 * without asking anyone. Saying the same three things in JSContact reveals nothing that was not
 * already being served -- it says it in a second format.
 *
 * So this layer is not a thing to switch off, and pretending otherwise would be offering a privacy
 * setting that does not do what it says. What it is instead is a floor: the least a contact card can
 * be and still identify who it is about. Google's profile does the same with a name, a photo and the
 * account address; here the account address is the Actor's own handle.
 *
 * What is here beyond the name is what makes it a Card a reader can use: what kind of thing it is
 * and what language it is written in, so the name can be read; when it was written and last changed,
 * so a reader can ask whether to fetch it again; what wrote it; and, for a group, who is in it.
 * Everything else -- a phone number, a home address, a second email -- is not identity and is not
 * here. That is the extended card, and it is published only when its owner says so and only to the
 * audience they chose.
 *
 * A picture is not here either, and that is the part worth saying out loud. What a profile shows is
 * the Actor's own icon, resolved when the page is drawn, so changing it upstream changes what people
 * see without rewriting anybody's Card. `media` is a different fact: an image somebody deliberately
 * put on this Card. Promoting the first of those to the public identity would publish an image on the
 * strength of its position in a list, and would claim somebody else's picture as this Card's own.
 *
 * @param array<string,mixed> $card Stored Card.
 * @return string[] Pointers.
 */
function axismundi_contacts_identity_pointers( array $card ) : array {
	/*
	 * What kind of thing this is and what language it is written in come with the name, because
	 * neither is a fact about the person: they are how to read the rest of the sentence. So do the
	 * document's own dates and the implementation that wrote it, because a public Card that cannot be
	 * checked for changes has to be fetched in full to find out there were none. And a group's
	 * members, because a group Card that will not say who is in it is not describing a group.
	 */
	$pointers = array( 'name', 'kind', 'language', 'created', 'updated', 'prodId', 'members' );
	/*
	 * The account that says which Actor this is. Its key is fixed for exactly this reason: a pointer
	 * naming the identity has to keep naming it, and a generated key would make the floor of a public
	 * profile depend on which row an editor happened to write.
	 */
	if ( isset( $card['onlineServices'][ AXISMUNDI_CONTACTS_HOME_SERVICE_KEY ] ) ) {
		$pointers[] = 'onlineServices/' . AXISMUNDI_CONTACTS_HOME_SERVICE_KEY;
	}
	return $pointers;
}

/**
 * Whether a pointer names something that may be published at all.
 *
 * `name`, or `emails/e1`. Anything else -- a property this does not know, a path reaching inside an
 * entry -- is refused rather than stored, because a pointer that is not understood here is a
 * pointer that would be silently ignored later, and a person would have said yes to something that
 * never happened.
 *
 * @param string $pointer Pointer.
 * @return bool
 */
function axismundi_contacts_is_publishable_pointer( string $pointer ) : bool {
	if ( in_array( $pointer, AXISMUNDI_CONTACTS_PUBLISHABLE_SINGULAR, true ) ) {
		return true;
	}
	$parts = explode( '/', $pointer );
	if ( 2 !== count( $parts ) ) {
		return false;
	}
	return in_array( $parts[0], AXISMUNDI_CONTACTS_PUBLISHABLE_ENTRIES, true ) && '' !== $parts[1];
}

/** @return string The table holding what each Actor publishes. */
function axismundi_contacts_published_column() : string {
	return 'published_json';
}

/**
 * What this Actor has said may be published.
 *
 * @param int $actor_id Actor identity.
 * @return string[] Pointers.
 */
function axismundi_contacts_published_pointers( int $actor_id ) : array {
	global $wpdb;
	if ( $actor_id <= 0 ) {
		return array();
	}
	$table = axismundi_contacts_profiles_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$stored = $wpdb->get_var( $wpdb->prepare( "SELECT published_json FROM {$table} WHERE actor_id = %d", $actor_id ) );
	if ( null === $stored || '' === (string) $stored ) {
		return axismundi_contacts_default_published();
	}
	$decoded = json_decode( (string) $stored, true );
	if ( ! is_array( $decoded ) ) {
		return axismundi_contacts_default_published();
	}
	return array_values( array_filter( array_map( 'strval', $decoded ), 'axismundi_contacts_is_publishable_pointer' ) );
}

/**
 * Record what may be published.
 *
 * Pointers this does not understand are dropped rather than stored. A stored pointer that nothing
 * acts on is a person believing they published something they did not.
 *
 * @param int      $actor_id Actor identity.
 * @param string[] $pointers Pointers.
 * @return true|WP_Error
 */
function axismundi_contacts_set_published_pointers( int $actor_id, array $pointers ) {
	global $wpdb;
	if ( $actor_id <= 0 || axismundi_contacts_profile_card( $actor_id ) <= 0 ) {
		return new WP_Error( 'ax_contacts_published_none', __( 'That Actor publishes no contact card.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	$clean = array_values( array_unique( array_filter( array_map( 'strval', $pointers ), 'axismundi_contacts_is_publishable_pointer' ) ) );
	$table = axismundi_contacts_profiles_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update(
		$table,
		array( 'published_json' => (string) wp_json_encode( $clean ), 'updated_at' => current_time( 'mysql', true ) ),
		array( 'actor_id' => $actor_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
	return true;
}

/**
 * Cut one Card down to what was published.
 *
 * Built by taking, never by removing. A projection written as "the Card, minus these" publishes
 * every property somebody adds later and every property a future revision of the standard invents,
 * because neither was on the list of things to take out. Taking only what was asked for fails the
 * other way, which is the way to fail.
 *
 * `uid` and `@type` come along because they are what makes this a Card rather than a fragment, and
 * `version` because a reader needs to know what it is reading. None of the three says anything
 * about the person.
 *
 * @param array<string,mixed> $card      Stored Card.
 * @param string[]            $published Pointers.
 * @return array<string,mixed>
 */
function axismundi_contacts_public_projection( array $card, array $published ) : array {
	$out = array( '@type' => 'Card', 'version' => AXISMUNDI_CONTACTS_JSCONTACT_VERSION );
	if ( isset( $card['uid'] ) ) {
		$out['uid'] = $card['uid'];
	}
	foreach ( $published as $pointer ) {
		if ( in_array( $pointer, AXISMUNDI_CONTACTS_PUBLISHABLE_SINGULAR, true ) ) {
			if ( isset( $card[ $pointer ] ) ) {
				$out[ $pointer ] = $card[ $pointer ];
			}
			continue;
		}
		$parts = explode( '/', $pointer );
		if ( 2 !== count( $parts ) ) {
			continue;
		}
		list( $property, $key ) = $parts;
		if ( ! in_array( $property, AXISMUNDI_CONTACTS_PUBLISHABLE_ENTRIES, true ) ) {
			continue;
		}
		if ( ! isset( $card[ $property ] ) || ! is_array( $card[ $property ] ) || ! array_key_exists( $key, $card[ $property ] ) ) {
			continue;
		}
		if ( ! isset( $out[ $property ] ) ) {
			$out[ $property ] = array();
		}
		$out[ $property ][ $key ] = $card[ $property ][ $key ];
	}
	$out = axismundi_contacts_project_localizations( $card, $out );
	return $out;
}

/**
 * Carry over only the localizations of what survived.
 *
 * A localization is a translation of a value, so it is exactly as public as the value it translates
 * and no more. A Card that withheld a home address and published its English rendering would have
 * withheld nothing -- and the patch form makes that easy to miss, because `addresses/home/components`
 * does not look like an address until it is applied to one.
 *
 * @param array<string,mixed> $card Stored Card.
 * @param array<string,mixed> $out  Projection so far.
 * @return array<string,mixed>
 */
function axismundi_contacts_project_localizations( array $card, array $out ) : array {
	$localizations = (array) ( $card['localizations'] ?? array() );
	$kept          = array();
	foreach ( $localizations as $tag => $patch ) {
		if ( ! is_array( $patch ) ) {
			continue;
		}
		$surviving = array();
		foreach ( $patch as $path => $value ) {
			$path    = (string) $path;
			$segments = explode( '/', $path );
			$property = (string) ( $segments[0] ?? '' );
			if ( in_array( $property, AXISMUNDI_CONTACTS_PUBLISHABLE_SINGULAR, true ) ) {
				// A whole-property localization is as public as that property.
				if ( isset( $out[ $property ] ) ) {
					$surviving[ $path ] = $value;
				}
				continue;
			}
			$key = (string) ( $segments[1] ?? '' );
			if ( '' === $key || ! isset( $out[ $property ] ) || ! is_array( $out[ $property ] ) ) {
				continue;
			}
			if ( array_key_exists( $key, $out[ $property ] ) ) {
				$surviving[ $path ] = $value;
			}
		}
		if ( array() !== $surviving ) {
			$kept[ (string) $tag ] = $surviving;
		}
	}
	if ( array() !== $kept ) {
		$out['localizations'] = $kept;
	}
	return $out;
}
