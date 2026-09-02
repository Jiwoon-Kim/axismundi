<?php
/**
 * The order a Card is written down in.
 *
 * JSON says nothing about the order of an object's keys, so none of this changes what a Card means.
 * It changes what a person sees: a stored document, a diff, and the Advanced JSON box are all read
 * by somebody, and a Card whose properties land in whatever order they were last edited is one
 * nobody can scan or review.
 *
 * The order is the standard's own: the groups RFC 9553 documents, in the order it documents them. A
 * stored Card is read beside the specification -- when checking an import, when comparing two
 * exports, when working out what a field is called -- and an order somebody has to learn first is an
 * order that makes all three harder.
 *
 * Deliberately not the order the editor uses. There, an Actor is the identity this site is built on
 * and a profile reads media, name, `onlineServices`, then how to reach somebody directly; a Card in
 * an address book may be a shop with a phone number and nothing else. Presentation is where a
 * reading order belongs, and a serializer that followed one would bend every ordinary Card to suit
 * a model most of them are not in.
 *
 * Nothing about entry ids or array order is touched. A key in a map is an address -- `emails/e1` is
 * what a published-pointer and a provenance row name -- and tidying `e1` into `e0` would sever
 * somebody's publishing consent from the value they gave it to. Arrays are ordered data: the
 * components of a name are a reading order, and sorting them would rewrite the name.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every property this knows, in the order it is written.
 *
 * A property missing from this list is not dropped: it is written after everything here, in lexical
 * order. A vendor extension and a property from a revision of the standard newer than this file are
 * the same case, and neither is something to lose because nobody updated a list.
 *
 * @return string[]
 */
function axismundi_contacts_canonical_order() : array {
	return array(
		/*
		 * What this document is, and which record it is. Read top to bottom it answers the questions
		 * in the order somebody opening a stored Card asks them: what kind of document, which version
		 * of the standard, which record, what it describes, when it was written and last touched, what
		 * language it says all of that in, and what wrote it.
		 */
		'@type',
		'version',
		'uid',
		'kind',
		'created',
		'updated',
		'language',
		'prodId',

		// Who a group card is a card for.
		'members',

		// Name and organization.
		'name',
		'nicknames',
		'organizations',
		'speakToAs',
		'titles',

		// Contact.
		'emails',
		'onlineServices',
		'phones',
		'preferredLanguages',

		// Calendaring.
		'calendars',
		'schedulingAddresses',

		// Address.
		'addresses',

		// Resources.
		'cryptoKeys',
		'directories',
		'links',
		'media',

		// Additional facts.
		'anniversaries',
		'keywords',
		'notes',
		'personalInfo',
		'relatedTo',

		// Applied over everything above, so it is written after all of it.
		'localizations',
	);
}

/**
 * The order the parts of one entry are written in.
 *
 * The same shape every time: what it is, what kind of thing, how it is classified, how much it is
 * preferred, what it is called. An email and a phone number read the same way down the page.
 *
 * @return string[]
 */
function axismundi_contacts_canonical_entry_order() : array {
	return array(
		'@type',
		'kind',
		'address',
		'number',
		'uri',
		'user',
		'service',
		'name',
		'note',
		'value',
		// How the value above is said, which is written next to it rather than anywhere else.
		'phonetic',
		'date',
		'components',
		'isOrdered',
		'defaultSeparator',
		'phoneticSystem',
		'phoneticScript',
		'full',
		'countryCode',
		'timeZone',
		'coordinates',
		'mediaType',
		'features',
		'contexts',
		'level',
		'listAs',
		'sortAs',
		'pref',
		'label',
	);
}

/**
 * Where a type is already stated by the property it sits on.
 *
 * RFC 9553 gives every object an `@type` and requires only that it be right when it is there. In
 * almost every case it restates what the property already says: the object under `name` is a Name,
 * and the objects in its `components` are NameComponents, because there is nowhere else either can
 * appear. Written out it is noise in a document meant to be read -- the standard's own examples
 * leave it off -- so a stored Card leaves it off too.
 *
 * By position and never by the word. A vendor's own property may hold an object that calls itself a
 * Media, and it is not one: it is whatever that vendor says it is, in a place this file knows
 * nothing about. Matching on the type name alone would strip the one line saying what that object
 * was, out of a document this plugin does not own the meaning of. So a type is dropped only where
 * this map says a type of exactly that name is what the position means, and everything else -- a
 * property from a newer revision, an extension, an object somewhere unexpected -- is written down
 * exactly as it arrived.
 *
 * `date` on an anniversary shows what "already stated" means precisely. It is a PartialDate or a
 * Timestamp, and the standard's default is PartialDate -- so a PartialDate there is implied and
 * goes, while a Timestamp is the answer `@type` is carrying and stays.
 *
 * The Card's own `@type` stays too. That one is not implied by anything: it is the top of the
 * document, and a reader handed the bytes has nothing else to tell it what it has been handed.
 *
 * Each node says what the objects at that property are (`type`), whether the property holds a
 * collection of them (`map` for entries addressed by id, `list` for ordered data) and what its own
 * properties are in turn (`children`).
 *
 * @return array<string,array<string,mixed>>
 */
function axismundi_contacts_typed_positions() : array {
	$address = array( 'type' => 'Address', 'children' => array( 'components' => array( 'list' => true, 'type' => 'AddressComponent' ) ) );
	return array(
		'name'                => array(
			'type'     => 'Name',
			'children' => array( 'components' => array( 'list' => true, 'type' => 'NameComponent' ) ),
		),
		'nicknames'           => array( 'map' => true, 'type' => 'Nickname' ),
		'organizations'       => array(
			'map'      => true,
			'type'     => 'Organization',
			'children' => array( 'units' => array( 'list' => true, 'type' => 'OrgUnit' ) ),
		),
		'speakToAs'           => array(
			'type'     => 'SpeakToAs',
			'children' => array( 'pronouns' => array( 'map' => true, 'type' => 'Pronouns' ) ),
		),
		'titles'              => array( 'map' => true, 'type' => 'Title' ),
		'emails'              => array( 'map' => true, 'type' => 'EmailAddress' ),
		'onlineServices'      => array( 'map' => true, 'type' => 'OnlineService' ),
		'phones'              => array( 'map' => true, 'type' => 'Phone' ),
		'preferredLanguages'  => array( 'map' => true, 'type' => 'LanguagePref' ),
		'calendars'           => array( 'map' => true, 'type' => 'Calendar' ),
		'schedulingAddresses' => array( 'map' => true, 'type' => 'SchedulingAddress' ),
		'addresses'           => array( 'map' => true ) + $address,
		'cryptoKeys'          => array( 'map' => true, 'type' => 'CryptoKey' ),
		'directories'         => array( 'map' => true, 'type' => 'Directory' ),
		'links'               => array( 'map' => true, 'type' => 'Link' ),
		'media'               => array( 'map' => true, 'type' => 'Media' ),
		'anniversaries'       => array(
			'map'      => true,
			'type'     => 'Anniversary',
			'children' => array(
				// A PartialDate is what a date here means unless it says otherwise, so only that goes.
				'date'  => array( 'type' => 'PartialDate' ),
				'place' => $address,
			),
		),
		'notes'               => array(
			'map'      => true,
			'type'     => 'Note',
			'children' => array( 'author' => array( 'type' => 'Author' ) ),
		),
		'personalInfo'        => array( 'map' => true, 'type' => 'PersonalInfo' ),
		'relatedTo'           => array( 'map' => true, 'type' => 'Relation' ),
	);
}

/**
 * Sort one object's keys by a stated order, leaving anything unlisted after it.
 *
 * @param array<string,mixed> $value Object.
 * @param string[]            $order Keys, in the order they are written.
 * @return array<string,mixed>
 */
function axismundi_contacts_order_keys( array $value, array $order ) : array {
	$out = array();
	foreach ( $order as $key ) {
		if ( array_key_exists( $key, $value ) ) {
			$out[ $key ] = $value[ $key ];
		}
	}
	$rest = array_diff_key( $value, $out );
	ksort( $rest );
	foreach ( $rest as $key => $unlisted ) {
		$out[ $key ] = $unlisted;
	}
	return $out;
}

/**
 * Whether an array is a list rather than a map.
 *
 * A list is ordered data and is left exactly as it is. A map is keyed by an id whose position says
 * nothing, so its entries may be written in a stated order -- but the ids themselves never change.
 *
 * @param array<mixed> $value Array.
 * @return bool
 */
function axismundi_contacts_is_list( array $value ) : bool {
	return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
}

/**
 * Write one Card down in a stable order.
 *
 * Empty objects are dropped. A Card carrying `"emails": {}` says the same thing as one that does not
 * mention emails at all, and the screen can offer an empty row without the document having to record
 * that somebody looked at it.
 *
 * @param array<string,mixed> $card Card.
 * @return array<string,mixed>
 */
function axismundi_contacts_canonical_card( array $card ) : array {
	$card = axismundi_contacts_canonical_value( $card, 0 );
	// The Card's own type is not implied by anything, so it is never one of the ones dropped below.
	return axismundi_contacts_order_keys( $card, axismundi_contacts_canonical_order() );
}

/**
 * One value, ordered and pruned according to how deep it sits.
 *
 * `localizations` is left alone below its language tags. Its keys are JSON pointers into the Card --
 * `name/components/0/value` -- and their shape is the patch, not an object to be tidied. Ordering
 * the tags themselves is safe and makes four languages readable.
 *
 * Inside a localization there is no position to read: a patch's keys are paths and its values are
 * whatever goes there, so nothing is recognised and nothing is dropped.
 *
 * @param mixed              $value Value.
 * @param int                $depth How far from the Card's own properties.
 * @param bool               $patch Whether this sits inside a localization patch.
 * @param array<string,mixed>|null $spec What this position is known to hold, or null when it is not
 *                                       a position this file knows.
 * @return mixed
 */
function axismundi_contacts_canonical_value( $value, int $depth, bool $patch = false, ?array $spec = null ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}
	/*
	 * A property that holds several of something: entries addressed by id, or ordered data. The spec
	 * describes what each of them is, so the collection itself is nothing and its items are objects.
	 */
	$collection = null !== $spec && ( ! empty( $spec['map'] ) || ! empty( $spec['list'] ) );
	$item_spec  = $collection
		? array( 'type' => (string) ( $spec['type'] ?? '' ), 'children' => (array) ( $spec['children'] ?? array() ) )
		: null;

	if ( axismundi_contacts_is_list( $value ) ) {
		// Ordered data. Each item is still written in a stable key order; the sequence is untouched.
		$out = array();
		foreach ( $value as $item ) {
			$out[] = axismundi_contacts_canonical_value( $item, $depth + 1, $patch, $item_spec );
		}
		return $out;
	}
	$out = array();
	foreach ( $value as $key => $item ) {
		$key      = (string) $key;
		$in_patch = $patch || ( 0 === $depth && 'localizations' === $key );
		if ( $collection ) {
			// An entry, addressed by an id that says nothing about what it holds.
			$child = $item_spec;
		} elseif ( 0 === $depth ) {
			$child = axismundi_contacts_typed_positions()[ $key ] ?? null;
		} else {
			$child = null !== $spec ? ( (array) ( $spec['children'] ?? array() ) )[ $key ] ?? null : null;
		}
		$item = axismundi_contacts_canonical_value( $item, $depth + 1, $in_patch && $depth >= 1, $in_patch ? null : $child );
		if ( is_array( $item ) && array() === $item && ! $patch ) {
			// An empty object says nothing the absent property does not say.
			continue;
		}
		$out[ $key ] = $item;
	}
	if ( $patch ) {
		// Patch paths are addresses into the Card. Sorted, so a language with six of them is readable.
		ksort( $out );
		return $out;
	}
	if ( $depth >= 1 ) {
		/*
		 * A collection's members are entry ids, which are not property names and have no canonical
		 * order of their own to be written in. What they have is `pref`: the standard's own answer to
		 * which of these comes first. So a collection is written most preferred first, and the ids
		 * settle the rest -- which is what the key ordering below did to them anyway, and is why an
		 * entry marked most preferred could be written third.
		 */
		if ( $collection && ! axismundi_contacts_is_list( $value ) ) {
			return axismundi_contacts_by_preference( $out );
		}
		if ( ! $collection && null !== $spec && isset( $out['@type'] )
			&& is_string( $out['@type'] ) && $out['@type'] === (string) ( $spec['type'] ?? '' ) ) {
			// This position means exactly that, so saying it again says nothing.
			unset( $out['@type'] );
		}
		return axismundi_contacts_order_keys( $out, axismundi_contacts_canonical_entry_order() );
	}
	return $out;
}
