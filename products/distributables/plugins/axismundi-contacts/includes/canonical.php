<?php
/**
 * The order a Card is written down in.
 *
 * JSON says nothing about the order of an object's keys, so none of this changes what a Card means.
 * It changes what a person sees: a stored document, a diff, and the Advanced JSON box are all read
 * by somebody, and a Card whose properties land in whatever order they were last edited is one
 * nobody can scan or review.
 *
 * The order is Axismundi's rather than the RFC's table of contents, because the reading order here
 * is different. An Actor is the identity this site is built on, so a Card answers who somebody is,
 * then where they are on the network, then how to reach them directly:
 *
 *   name, media          who this is
 *   onlineServices       where they are -- the Actor URI and the handles live here
 *   emails, phones       how to reach them
 *
 * `onlineServices` above `emails` is the whole point of that choice. Elsewhere it would be a
 * resource among others; here it carries the federated identity, and a reader scanning a Card is
 * looking for the handle before the phone number.
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
		// Metadata: what this document is and which record it is.
		'@type',
		'version',
		'uid',
		'kind',
		'created',
		'updated',
		'language',
		'prodId',
		'relatedTo',
		'members',

		// Who this is.
		'name',
		'nicknames',
		'speakToAs',
		'media',

		// Where they are on the network.
		'onlineServices',

		// Who they are part of.
		'organizations',
		'titles',

		// How to reach them directly.
		'emails',
		'phones',
		'preferredLanguages',

		// When and where.
		'calendars',
		'schedulingAddresses',
		'addresses',

		// What else is attached.
		'links',
		'directories',
		'cryptoKeys',

		// Facts about the person.
		'anniversaries',
		'personalInfo',
		'keywords',
		'notes',

		// Applied over everything above, so it is read after all of it.
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
		'date',
		'components',
		'isOrdered',
		'defaultSeparator',
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
	return axismundi_contacts_order_keys( $card, axismundi_contacts_canonical_order() );
}

/**
 * One value, ordered and pruned according to how deep it sits.
 *
 * `localizations` is left alone below its language tags. Its keys are JSON pointers into the Card --
 * `name/components/0/value` -- and their shape is the patch, not an object to be tidied. Ordering
 * the tags themselves is safe and makes four languages readable.
 *
 * @param mixed $value Value.
 * @param int   $depth How far from the Card's own properties.
 * @param bool  $patch Whether this sits inside a localization patch.
 * @return mixed
 */
function axismundi_contacts_canonical_value( $value, int $depth, bool $patch = false ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}
	if ( axismundi_contacts_is_list( $value ) ) {
		// Ordered data. Each item is still written in a stable key order; the sequence is untouched.
		$out = array();
		foreach ( $value as $item ) {
			$out[] = axismundi_contacts_canonical_value( $item, $depth + 1, $patch );
		}
		return $out;
	}
	$out = array();
	foreach ( $value as $key => $item ) {
		$key      = (string) $key;
		$in_patch = $patch || ( 0 === $depth && 'localizations' === $key );
		$item     = axismundi_contacts_canonical_value( $item, $depth + 1, $in_patch && $depth >= 1 );
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
		return axismundi_contacts_order_keys( $out, axismundi_contacts_canonical_entry_order() );
	}
	return $out;
}
