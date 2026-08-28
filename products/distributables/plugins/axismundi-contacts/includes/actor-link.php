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
 * Derived rather than stored: the handle is a locator built from the Actor's username and the
 * authority of its URI, and the URI is the identity. A Place or anything else without a username has
 * none, which is normal and not an error.
 *
 * The authority is asked of the directory's own rule rather than taken as the bare host, because the
 * handle's whole purpose is to be an address somebody can look up -- and a directory answers for
 * `alice@example.com:8884`, not for `alice@example.com`, when that is where it lives. Taking the
 * host alone produced a handle that could be read off a card, pasted into a lookup, and refused: the
 * two strings were built by two different rules and only agreed when the port happened to be the
 * default one. Now there is one rule, and a default port is still left off because that rule leaves
 * it off.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return string
 */
function axismundi_contacts_actor_handle( Axismundi_Actor $actor ) : string {
	$username = trim( $actor->get_preferred_username() );
	/*
	 * For an Actor of this site, the authority this site's own directory answers on -- not the one in
	 * the Actor's stored URI. The two agree until they do not: a site that has moved carries Actor
	 * URIs written under the old name, and a handle built from those would be an address nothing
	 * answers to. The directory is the thing being addressed, so the directory says what it is called.
	 *
	 * For anybody else's Actor it is their URI, which is the only statement available about where they
	 * live.
	 */
	if ( $actor->is_local() && function_exists( 'axismundi_actors_webfinger_authority' ) ) {
		$authority = (string) axismundi_actors_webfinger_authority();
	} elseif ( function_exists( 'axismundi_actors_webfinger_authority_from_url' ) ) {
		$authority = axismundi_actors_webfinger_authority_from_url( $actor->get_uri() );
	} else {
		$authority = (string) wp_parse_url( $actor->get_uri(), PHP_URL_HOST );
	}
	return '' !== $username && '' !== $authority ? '@' . $username . '@' . $authority : '';
}

/**
 * The account entry that says which Actor a self Card is about.
 *
 * Not an account somebody added: the one the Card is. It is what a reader uses to tell this profile
 * from another with the same name, it is served to everybody as part of the public identity, and it
 * is answered by the Actor rather than typed -- so it is assembled here, from the Actor, every time
 * it is needed.
 *
 * The parallel is a Google profile's account address: the thing that says whose profile this is,
 * shown to everyone, and not a row in the list of addresses somebody keeps.
 *
 * @param Axismundi_Actor $actor Actor the Card describes.
 * @param string          $service What this site's own service is called on a Card.
 * @return array<string,mixed>
 */
function axismundi_contacts_identity_service( Axismundi_Actor $actor, string $service = 'Axismundi' ) : array {
	$entry = array(
		'@type'   => 'OnlineService',
		'service' => $service,
		'uri'     => axismundi_contacts_actor_service_uri( $actor ),
		// Most preferred, because it is the one this profile leads with wherever it is read.
		'pref'    => 1,
	);
	$handle = axismundi_contacts_actor_handle( $actor );
	if ( '' !== $handle ) {
		$entry['user'] = $handle;
	}
	return $entry;
}

/**
 * Whether an entry is what the Actor says, whole.
 *
 * Every part of it, not the four keys the Actor happens to fill in. This row is the binding itself --
 * which Actor this Card is -- so there is no part of it left over for somebody to write on. A label
 * on an ordinary account is a name its owner gave it; a label here would be a second name for the
 * handle, sitting in the one row nobody may edit and published to everybody as part of the identity.
 *
 * `@type` is left out of the comparison because the store drops it: the position already says what
 * the object is, so a stored entry does not repeat it while a freshly built one does.
 *
 * @param array<string,mixed> $entry Entry as submitted.
 * @param array<string,mixed> $fixed Entry as the Actor answers it.
 * @return bool
 */
function axismundi_contacts_identity_service_matches( array $entry, array $fixed ) : bool {
	unset( $entry['@type'], $fixed['@type'] );
	ksort( $entry );
	ksort( $fixed );
	return $entry === $fixed;
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
		/*
		 * The whole uuid. Checking against the keys this Card holds says nothing about the keys it
		 * used to hold, and a few characters of one come round often enough to hand a deleted
		 * account's address -- with its provenance and whoever agreed it could be published -- to an
		 * account added later. The key is never shown, so its length costs nobody anything.
		 */
		$key = 'onl-' . wp_generate_uuid4();
	} while ( isset( $taken[ $key ] ) );
	return $key;
}

/**
 * The address of an Actor that belongs on a Card, which is the one a person opens.
 *
 * An Actor has two addresses and they are not interchangeable. Its `id` is what identifies it
 * between servers -- what a Follow is addressed to, what a cache is keyed by, what a signature is
 * checked against -- and on Mastodon it looks like `https://mastodon.social/users/alice`. Its `url`
 * is the profile a person opens, shares and recognises: `https://mastodon.social/@alice`.
 *
 * JSContact wants the second one. `uri` on an OnlineService identifies the entity on that service,
 * and the standard's own example gives a Mastodon account as `https://example2.com/@alice` beside
 * the `@alice@example2.com` that names it. A Card is something a person reads, and handing them the
 * machine identifier because the two happen to resolve to the same account would be showing them
 * the plumbing.
 *
 * The id is not thrown away -- it is what provenance records for the entry, which is where a sync
 * identifier belongs. See `axismundi_contacts_service_actor_uri()` for the way back.
 *
 * Falls back to the id when there is no usable profile: a handle-less local Actor has none yet, and
 * a remote one that published no `url` is cached with its id in that column. An address that is
 * only ever the machine one is still better than an empty entry.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return string
 */
function axismundi_contacts_actor_service_uri( Axismundi_Actor $actor ) : string {
	if ( ! method_exists( $actor, 'get_profile_url' ) ) {
		return $actor->get_uri();
	}
	$profile = trim( (string) $actor->get_profile_url() );
	/*
	 * Plain http counts, for the reason the linked column allows it: a site behind a VPN or a
	 * development one is still somewhere a person opens, and refusing it would silently push every
	 * such Card back onto the machine identifier.
	 */
	return 1 === preg_match( '#^https?://#', $profile ) ? $profile : $actor->get_uri();
}

/**
 * Which Actor an account entry is, when this site knows of one.
 *
 * Asked of provenance first, because that is where the identifier lives now. An entry seeded from
 * an Actor carries the profile URL a person reads, and the `id` that identifies it was written
 * against the same pointer when the value was seeded -- so the way from an entry back to its Actor
 * is the record of where the entry came from, not the value itself.
 *
 * Falling back to the entry's own `uri` covers two real cases: an account somebody typed in by hand
 * that happens to be an Actor id, and every Card written before the profile URL was preferred.
 *
 * @param array<string,array<string,mixed>> $provenance Card provenance, keyed by pointer.
 * @param string                            $entry_id   Entry key.
 * @param array<string,mixed>               $entry      The entry itself.
 * @return string Empty when the entry names nothing dereferenceable.
 */
function axismundi_contacts_service_actor_uri( array $provenance, string $entry_id, array $entry ) : string {
	$pointer = 'onlineServices/' . $entry_id;
	$record  = (array) ( $provenance[ $pointer ] ?? array() );
	/*
	 * Whatever the source now says. `source` answers who may write the value and `source_ref` answers
	 * what it is about, and an account somebody edited by hand is still that person's account on that
	 * server -- so the reference is read from a local row as readily as a seeded one. Only the writing
	 * is refused there, which is `axismundi_contacts_source_may_write()`'s business and not this one.
	 */
	$ref = trim( (string) ( $record['source_ref'] ?? '' ) );
	if ( '' !== $ref ) {
		return $ref;
	}
	$uri = trim( (string) ( $entry['uri'] ?? '' ) );
	return 1 === preg_match( '#^https?://#', $uri ) ? $uri : '';
}

/**
 * Connect a Card to an Actor, seeding what the Actor already says.
 *
 * Seeds only what is not already somebody's own: an empty name takes the Actor's, and a name a
 * person typed is left alone. Starting from a blank card would make linking useless; overwriting
 * what somebody wrote would make it dangerous.
 *
 * A starting value, and only that. The name is copied when there is none and then belongs to the
 * Card: nothing carries a later Actor rename into it, because a display name and a structured name
 * are different answers to different questions and keeping them equal would make every profile edit
 * an edit to somebody's address book. What goes on being inherited is the account below.
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
		/*
		 * Which entry is already this Actor, asked the way everything else asks it: of the record of
		 * where the entry came from. Comparing the entry's `uri` would have missed it, because that
		 * now holds the profile somebody opens and the Actor is identified by its id.
		 */
		foreach ( axismundi_contacts_ordered_services( $document ) as $entry ) {
			if ( axismundi_contacts_service_actor_uri( $prov, (string) $entry['entry_id'], $entry ) === $ref ) {
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
			array( '@type' => 'OnlineService', 'service' => $service, 'uri' => axismundi_contacts_actor_service_uri( $actor ) )
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

	/*
	 * Recorded before it is saved, which is the opposite of the obvious order and the right one. The
	 * record is now what says which Actor an entry is, so a value saved ahead of it is a value
	 * nothing can resolve -- including the columns derived during that very save. Written first, the
	 * worst a failed save leaves is a record of where a value would have come from, which the next
	 * refresh fills in; written second, a failed record leaves a seeded value that looks authored and
	 * never refreshes again.
	 */
	foreach ( $written as $pointer ) {
		$recorded = axismundi_contacts_set_provenance( $card_id, $pointer, $source, $ref );
		if ( is_wp_error( $recorded ) ) {
			return $recorded;
		}
	}
	$saved = axismundi_contacts_save_card( axismundi_contacts_home_book_id( (int) $card['owner_actor_id'] ), $document, $card_id );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	return true;
}

/**
 * Bring a Card's linked accounts back into step with the Actors they name, on request.
 *
 * Accounts, and nothing else. An Actor's name and the name on a Card are different answers to
 * different questions: an Actor's is a display name its owner may write as `Jiwoon Kim`, `김지운` or
 * `Jiwoon | Axismundi`, and a Card's is a structured name with parts, an order and a language. The
 * Card takes the Actor's as a starting value when it is first made, and from then on the two are
 * independent -- so a rename upstream is not an edit to somebody's address book, and correcting a
 * surname here does not reach across and rewrite a profile.
 *
 * What is still inherited is the account that says which Actor a Card is about, and the handle and
 * profile of any other Actor a Card names. Those are facts the Actor owns: it is the thing that
 * knows where it lives and what it is called there.
 *
 * Explicit because it overwrites. Only values still recorded as having come from an Actor are
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
	unset( $actor );

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
		$after  = array_merge( $entry, array( 'uri' => axismundi_contacts_actor_service_uri( $link['actor'] ) ) );
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
 * A binding is not stored anywhere new: the entry already carries where it came from, and asking the
 * Actor registry whether it knows that address is the whole question. A binding table would be a
 * second copy of a fact the Card already states, and the two would eventually disagree.
 *
 * What is asked has moved, though. The entry's own `uri` is the profile a person opens, so the
 * address that identifies the Actor is read from the record of where the entry was seeded -- and
 * from the entry itself for one somebody typed, or one written before the profile was preferred.
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
	$prov  = axismundi_contacts_card_provenance( $card_id );
	foreach ( axismundi_contacts_ordered_services( $document ) as $entry ) {
		$uri = axismundi_contacts_service_actor_uri( $prov, (string) $entry['entry_id'], $entry );
		if ( '' === $uri ) {
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
 *   3. the bundled default image         -- an honest, local fallback
 *
 * The Actor's own picture is resolved here rather than copied onto the Card, and the difference
 * matters twice over: somebody who changes their avatar changes what this shows without anything
 * being rewritten, and this site never claims another person's image as a photo on a Card it keeps.
 * A `media` entry is the other thing -- an image somebody deliberately put here -- and it wins,
 * because it was chosen for this contact.
 *
 * Which Actor to ask is read from the record of where an account came from, not from which account
 * happens to be first. Preference decides what a person reads first and is theirs to change; it has
 * no business deciding whose picture this is. A self Card's own account answers before the others,
 * because that one is not an account somebody added -- it is which Actor this Card is.
 *
 * Nothing here goes looking for a picture by email address. Handing a third party the addresses in
 * somebody's private address book, one request at a time, is not a thing to do quietly for an image;
 * a caller with no picture to show uses the bundled mark. It makes no network request and is not
 * contact data.
 *
 * An email address is never turned into an avatar lookup. Handing a third party the addresses in
 * somebody's private address book, one request at a time, is not a thing to do quietly for a
 * picture; if that is ever wanted it is a provider somebody switches on.
 *
 * @param int $card_id Card id.
 * @param int $size    Requested pixel size.
 * @return array{url:string,source:string}
 */
function axismundi_contacts_card_avatar( int $card_id, int $size = 96 ) : array {
	$default  = array( 'url' => axismundi_contacts_default_avatar_url(), 'source' => 'default' );
	$document = axismundi_contacts_card_document( $card_id );
	if ( array() === $document ) {
		return $default;
	}
	foreach ( (array) ( $document['media'] ?? array() ) as $entry ) {
		$uri = is_array( $entry ) ? trim( (string) ( $entry['uri'] ?? '' ) ) : '';
		if ( '' !== $uri && 'photo' === (string) ( $entry['kind'] ?? 'photo' ) ) {
			return array( 'url' => $uri, 'source' => 'card' );
		}
	}
	if ( ! function_exists( 'axismundi_actors_avatar_url' ) ) {
		return $default;
	}
	/*
	 * The account that says which Actor this Card is, ahead of the accounts somebody added to it.
	 * Everything else keeps the Card's own order, which is where a person put them.
	 */
	$links = axismundi_contacts_card_actor_links( $card_id );
	usort(
		$links,
		static function ( array $a, array $b ) : int {
			$a_self = AXISMUNDI_CONTACTS_HOME_SERVICE_KEY === $a['entry_id'] ? 0 : 1;
			$b_self = AXISMUNDI_CONTACTS_HOME_SERVICE_KEY === $b['entry_id'] ? 0 : 1;
			return $a_self <=> $b_self;
		}
	);
	foreach ( $links as $link ) {
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
	return $default;
}

/**
 * The local image used when a Card and every known Actor are both without a picture.
 *
 * This is a rendering fallback, not a Media entry. It deliberately has no provenance and never
 * enters JSContact, so exporting an email-only contact does not turn a generic silhouette into a
 * claim about that person.
 *
 * @return string
 */
function axismundi_contacts_default_avatar_url() : string {
	return plugins_url( 'assets/avatar-default.svg', dirname( __DIR__ ) . '/axismundi-contacts.php' );
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
