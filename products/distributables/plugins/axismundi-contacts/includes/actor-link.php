<?php
/**
 * Connecting a Card to an Actor, and finding a face for it.
 *
 * Linking is not setting a foreign key. It is a one-time copy: the Actor's public name and its
 * fediverse address are written into the Card as starting values, and from then on the Card is the
 * address book's. That direction is the whole point -- an address book is something a person keeps,
 * and a contact whose name silently changed because somebody renamed themselves upstream would be a
 * contact that cannot be relied on to be the person you wrote down.
 *
 * So values seeded from an Actor are recorded as having come from it. A later refresh may replace
 * exactly those, and nothing else; the moment somebody edits one by hand it becomes theirs and no
 * refresh touches it again. That rule is not written here -- it is the same provenance rule every
 * source obeys.
 *
 * The address itself has a standard home. JSContact's `onlineServices` is defined for exactly this:
 * `uri` identifies the entity on that service and `user` is what it is called there. So a fediverse
 * account is an ordinary entry rather than a field this project invented, and it survives an export
 * to vCard as `SOCIALPROFILE`.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * The entry key this site's own account is written to on a self card.
 *
 * Fixed, because that row is the one seeded and refreshed from the Actor the book belongs to, and a
 * generated key would make it a different row every time it was written.
 *
 * Opaque, because an entry key is an address and not a label. `onlineServices/x1` is what a
 * published pointer names and what a provenance row is written against, and a key spelling out the
 * service would tie both to a word somebody is free to change: rename the service on screen and the
 * consent to publish it, along with the record of where the value came from, would be pointing at a
 * row that no longer exists. What the entry is called is `service`, which is a value and editable.
 */
const AXISMUNDI_CONTACTS_HOME_SERVICE_KEY = 'x1';

/** The kind of source a linked Actor is, with the Actor URI carried as its reference. */
const AXISMUNDI_CONTACTS_SOURCE_ACTOR = 'linked-actor';

/**
 * The `@user@host` an Actor is known by, when it has one.
 *
 * Derived rather than stored: the handle is a locator built from the Actor's username and the host
 * of its URI, and the URI is the identity. A Place or anything else without a username has none,
 * which is normal and not an error.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return string
 */
function axismundi_contacts_actor_handle( Axismundi_Actor $actor ) : string {
	$username = trim( $actor->get_preferred_username() );
	$host     = (string) wp_parse_url( $actor->get_uri(), PHP_URL_HOST );
	return '' !== $username && '' !== $host ? '@' . $username . '@' . $host : '';
}

/**
 * An entry key nothing on this Card is using yet.
 *
 * @param array<string,mixed> $document Card document.
 * @return string
 */
function axismundi_contacts_free_service_key( array $document ) : string {
	/*
	 * Drawn rather than counted. An entry key is an address -- what a published pointer names and
	 * what a provenance row is written against -- so counting up from `x1` hands a deleted account's
	 * address to the next one somebody adds, along with whoever had agreed that one could be
	 * published. The home account keeps its fixed key because that row is the same account every time
	 * it is seeded; everything else gets an id that has never been used here before.
	 */
	$taken = (array) ( $document['onlineServices'] ?? array() );
	do {
		$key = 'x-' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 6 );
	} while ( isset( $taken[ $key ] ) );
	return $key;
}

/**
 * Connect a Card to an Actor, seeding what the Actor already says.
 *
 * Seeds only what is not already somebody's own: an empty name takes the Actor's, and a name a
 * person typed is left alone. Starting from a blank card would make linking useless; overwriting
 * what somebody wrote would make it dangerous.
 *
 * @param int    $card_id   Card id.
 * @param string $actor_uri Actor URI to link.
 * @param string $service   What to call the service on the card.
 * @param string $entry_key Entry key to write, or '' to reuse or generate one.
 * @return true|WP_Error
 */
function axismundi_contacts_link_actor( int $card_id, string $actor_uri, string $service = 'ActivityPub', string $entry_key = '' ) {
	$actor_uri = trim( $actor_uri );
	$card      = axismundi_contacts_get_card( $card_id );
	if ( array() === $card ) {
		return new WP_Error( 'ax_contacts_card_missing', __( 'That card does not exist.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	if ( '' === $actor_uri || ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return new WP_Error( 'ax_contacts_actor_uri', __( 'An Actor address is needed to connect a card.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	$actor = axismundi_actors_get_by_uri( $actor_uri );
	if ( ! $actor instanceof Axismundi_Actor ) {
		/*
		 * Not fetched from here. Discovering and caching a remote Actor is the Actors plugin's work,
		 * and doing it as a side effect of linking would put a second fetcher in the tree.
		 */
		return new WP_Error(
			'ax_contacts_actor_unknown',
			__( 'That Actor is not known to this site yet.', 'axismundi-contacts' ),
			array( 'status' => 404 )
		);
	}
	$document = axismundi_contacts_card_document( $card_id );
	$source   = AXISMUNDI_CONTACTS_SOURCE_ACTOR;
	$ref      = $actor->get_uri();
	$prov     = axismundi_contacts_card_provenance( $card_id );
	$written  = array();

	// The name, only into a card that has none. What somebody typed is theirs.
	if ( '' === trim( (string) ( $document['name']['full'] ?? '' ) ) ) {
		$name = trim( $actor->get_display_name() );
		if ( '' !== $name ) {
			$document['name'] = array( '@type' => 'Name', 'full' => $name );
			$written[]        = 'name';
		}
	}

	/*
	 * An account is added, not assigned. One person keeps several -- a Mastodon account, a blog, a
	 * Misskey account, a bridged Bluesky handle -- and each is its own entry on the same Card. An
	 * Actor already present is updated in place rather than added twice.
	 */
	$key = '' !== $entry_key ? $entry_key : '';
	if ( '' === $key ) {
		foreach ( axismundi_contacts_ordered_services( $document ) as $entry ) {
			if ( trim( (string) ( $entry['uri'] ?? '' ) ) === $ref ) {
				$key = (string) $entry['entry_id'];
				break;
			}
		}
	}
	if ( '' === $key ) {
		$key = axismundi_contacts_free_service_key( $document );
	}
	$pointer = 'onlineServices/' . $key;
	$owned   = ( $prov[ $pointer ]['source'] ?? $source ) === $source
		&& ( $prov[ $pointer ]['source_ref'] ?? $ref ) === $ref;
	if ( $owned && 1 !== (int) ( $prov[ $pointer ]['locked'] ?? 0 ) ) {
		$existing = (array) ( $document['onlineServices'][ $key ] ?? array() );
		$entry    = array_merge(
			$existing,
			array( '@type' => 'OnlineService', 'service' => $service, 'uri' => $ref )
		);
		$handle = axismundi_contacts_actor_handle( $actor );
		if ( '' !== $handle ) {
			$entry['user'] = $handle;
		}
		if ( ! isset( $entry['pref'] ) ) {
			// New accounts go to the end; the order somebody chose is theirs to change.
			$entry['pref'] = count( (array) ( $document['onlineServices'] ?? array() ) ) + 1;
		}
		$document['onlineServices'][ $key ] = $entry;
		$written[]                          = $pointer;
	}

	$saved = axismundi_contacts_save_card( axismundi_contacts_home_book_id( (int) $card['owner_actor_id'] ), $document, $card_id );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	foreach ( $written as $pointer ) {
		$recorded = axismundi_contacts_set_provenance( $card_id, $pointer, $source, $ref );
		if ( is_wp_error( $recorded ) ) {
			// A seeded value whose origin was not recorded would look authored, and never refresh again.
			return $recorded;
		}
	}
	return true;
}

/**
 * Bring a linked Card back into step with its Actor, on request.
 *
 * Explicit because it overwrites. Only values still recorded as having come from this Actor are
 * replaced -- anything edited by hand became local and stays local, which is what makes an address
 * book a record rather than a mirror.
 *
 * @param int $card_id Card id.
 * @return true|WP_Error
 */
function axismundi_contacts_refresh_from_actor( int $card_id ) {
	$card = axismundi_contacts_get_card( $card_id );
	if ( array() === $card ) {
		return new WP_Error( 'ax_contacts_card_missing', __( 'That card does not exist.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	$actor_uri = trim( (string) ( $card['linked_actor_uri'] ?? '' ) );
	if ( '' === $actor_uri || ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return new WP_Error( 'ax_contacts_card_unlinked', __( 'That card is not connected to an Actor.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	$actor = axismundi_actors_get_by_uri( $actor_uri );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_contacts_actor_unknown', __( 'That Actor is not known to this site.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	$source   = AXISMUNDI_CONTACTS_SOURCE_ACTOR;
	$document = axismundi_contacts_card_document( $card_id );
	$changed  = false;

	if ( axismundi_contacts_source_may_write( $card_id, 'name', $source, $actor_uri ) ) {
		$name = trim( $actor->get_display_name() );
		if ( '' !== $name && $name !== (string) ( $document['name']['full'] ?? '' ) ) {
			$document['name'] = array_merge( (array) ( $document['name'] ?? array() ), array( '@type' => 'Name', 'full' => $name ) );
			$changed          = true;
		}
	}
	/*
	 * Every account that resolves is refreshed from its own Actor, not from the first one. A handle
	 * changes on the server that issued it, and the Card holds several servers.
	 */
	foreach ( axismundi_contacts_card_actor_links( $card_id ) as $link ) {
		$pointer = 'onlineServices/' . $link['entry_id'];
		if ( ! axismundi_contacts_source_may_write( $card_id, $pointer, $source, $link['uri'] ) ) {
			continue;
		}
		$handle = axismundi_contacts_actor_handle( $link['actor'] );
		$entry  = (array) ( $document['onlineServices'][ $link['entry_id'] ] ?? array() );
		$after  = array_merge( $entry, array( 'uri' => $link['uri'] ) );
		if ( '' !== $handle ) {
			$after['user'] = $handle;
		}
		if ( $after !== $entry ) {
			$document['onlineServices'][ $link['entry_id'] ] = $after;
			$changed                                         = true;
		}
	}
	if ( ! $changed ) {
		return true;
	}
	$saved = axismundi_contacts_save_card( axismundi_contacts_home_book_id( (int) $card['owner_actor_id'] ), $document, $card_id );
	return is_wp_error( $saved ) ? $saved : true;
}

/**
 * The accounts on a Card that resolve to an Actor this site knows, in the Card's own order.
 *
 * A binding is not stored anywhere: the service entry already holds the Actor URI, and asking the
 * Actor registry whether it knows that URI is the whole question. A binding table would be a second
 * copy of a fact the Card already states, and the two would eventually disagree.
 *
 * Entries that resolve to nothing are still real. Somebody's account on a service this site has
 * never spoken to belongs on their card exactly as much as one it has.
 *
 * @param int $card_id Card id.
 * @return array<int,array{entry_id:string,uri:string,actor:Axismundi_Actor}>
 */
function axismundi_contacts_card_actor_links( int $card_id ) : array {
	$document = axismundi_contacts_card_document( $card_id );
	if ( array() === $document || ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return array();
	}
	$links = array();
	foreach ( axismundi_contacts_ordered_services( $document ) as $entry ) {
		$uri = trim( (string) ( $entry['uri'] ?? '' ) );
		if ( '' === $uri || 1 !== preg_match( '#^https?://#', $uri ) ) {
			continue;
		}
		$actor = axismundi_actors_get_by_uri( $uri );
		if ( $actor instanceof Axismundi_Actor ) {
			$links[] = array( 'entry_id' => (string) $entry['entry_id'], 'uri' => $uri, 'actor' => $actor );
		}
	}
	return $links;
}

/**
 * The face to show for a Card.
 *
 * In order, and the order is the point:
 *
 *   1. a photo on the Card itself      -- somebody chose it, for this contact
 *   2. the first account with a face    -- the one they lead with, then the next
 *   3. nothing                          -- the caller draws initials
 *
 * Walking the accounts rather than taking one is what makes a Card with a Mastodon account, a blog,
 * a Misskey account and a bridged Bluesky handle show the right person: the top account is the one
 * they present themselves as, and the next only answers when that one has no picture yet.
 *
 * An email address is never turned into an avatar lookup. Handing a third party the addresses in
 * somebody's private address book, one request at a time, is not a thing to do quietly for a
 * picture; if that is ever wanted it is a provider somebody switches on.
 *
 * @param int $card_id Card id.
 * @param int $size    Requested pixel size.
 * @return array{url:string,source:string} Empty url when there is no picture to show.
 */
function axismundi_contacts_card_avatar( int $card_id, int $size = 96 ) : array {
	$none     = array( 'url' => '', 'source' => '' );
	$document = axismundi_contacts_card_document( $card_id );
	if ( array() === $document ) {
		return $none;
	}
	foreach ( (array) ( $document['media'] ?? array() ) as $entry ) {
		$uri = is_array( $entry ) ? trim( (string) ( $entry['uri'] ?? '' ) ) : '';
		if ( '' !== $uri && 'photo' === (string) ( $entry['kind'] ?? 'photo' ) ) {
			return array( 'url' => $uri, 'source' => 'card' );
		}
	}
	if ( ! function_exists( 'axismundi_actors_avatar_url' ) ) {
		return $none;
	}
	foreach ( axismundi_contacts_card_actor_links( $card_id ) as $link ) {
		/*
		 * Whatever Actors has, which is a local attachment for a local Actor and a cached copy for a
		 * remote one. Contacts does not fetch or store the image: two caches of one avatar is one too
		 * many, and the one that knows when it went stale is the one that fetched it.
		 */
		$url = (string) axismundi_actors_avatar_url( $link['actor'], $size );
		if ( '' !== $url ) {
			return array(
				'url'    => $url,
				'source' => $link['actor']->is_local() ? 'actor' : 'actor-remote',
			);
		}
	}
	return $none;
}

/**
 * Give a new address book a card for its owner, and file it there.
 *
 * A book that opens with an empty self card asks somebody to type in what the site already knows.
 * Seeding it from the Actor is the same one-time copy linking always does -- and because the copy is
 * recorded as the Actor's, a later name change can be pulled in until the moment somebody edits it
 * themselves.
 *
 * The card itself is made by the profile layer, which is where the rules about what an Actor
 * publishes live. This only puts the result in the book, because a Person's own card belongs in
 * their list and an Organization's, made on request, has no book to go in.
 *
 * @param int $book_id        Address book id.
 * @param int $owner_actor_id Actor that owns the book.
 * @return int|WP_Error Card id.
 */
function axismundi_contacts_seed_self_card( int $book_id, int $owner_actor_id ) {
	$book = axismundi_contacts_get_book( $book_id );
	if ( array() === $book ) {
		return new WP_Error( 'ax_contacts_book_missing', __( 'That address book does not exist.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	$card = axismundi_contacts_create_profile_card( $owner_actor_id );
	if ( is_wp_error( $card ) ) {
		return $card;
	}
	axismundi_contacts_add_card_to_book( (int) $card, $book_id );
	return (int) $card;
}
