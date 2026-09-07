<?php
/**
 * Looking somebody up, and saving them only when asked.
 *
 * Two different acts, kept apart because they have different consequences. Looking somebody up is
 * reading a public document and showing it: nothing is written down, and closing the screen leaves
 * no trace that it happened. Saving is the write, and it happens once, when a person says so.
 *
 * That split is the whole design. A lookup that quietly cached every Card it fetched would fill an
 * address book with people somebody merely glanced at, and would make "who is in my contacts" a
 * question about browsing history. The Actor cache next door exists for a different reason -- an
 * avatar and an inbox are needed to render a timeline nobody chose to open -- and a contact document
 * is not that.
 *
 * Three ways in, because people arrive holding different things:
 *
 *   `@alice@example.com`   an address, which needs the directory to say where anything is
 *   a profile page          which announces its contact document in a header or a link element
 *   a `.jscontact` URL      which is already the document
 *
 * All three end at one Card and the addresses that found it, so what is saved records how it was
 * reached rather than only what was read.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/**
 * How much of somebody else's response this will read.
 *
 * A contact card is small. A server answering with something enormous is either broken or hostile,
 * and either way this is not the place to find out how large a string PHP can hold.
 */
const AXISMUNDI_CONTACTS_LOOKUP_MAX_BYTES = 262144;

/** What a Card fetched from somewhere else records as its source. */
const AXISMUNDI_CONTACTS_SOURCE_LOOKUP = 'looked-up';

/**
 * Where a saved Card records what it was reached through.
 *
 * These are not paths into the Card, and could not be mistaken for one: a JSContact pointer is a
 * property name or a property and an entry id, and none of them contains a colon. They are facts
 * about the document rather than about the person -- which address answered, which page announced
 * it, which Actor it belongs to -- and a later refresh reads them to find the original again.
 */
const AXISMUNDI_CONTACTS_SOURCE_POINTERS = array(
	'card'    => 'source:card',
	'profile' => 'source:profile',
	'actor'   => 'source:actor',
	/*
	 * And which row in the Actor registry that address turned out to be, when it was one this site
	 * already had. A different fact from the one above and worth keeping apart: `source:actor` is
	 * somebody else's identifier for themselves, while this is where the answer about them is kept
	 * here. One survives this site being rebuilt and the other does not.
	 */
	'row'     => 'source:actor-row',
);

/**
 * What somebody typed, and which of the three ways in it is.
 *
 * Decided by shape rather than by asking, because the three are told apart by looking: an address
 * has an `@` and no scheme, a document announces itself with its own suffix, and everything else is
 * a page that may or may not point at one.
 *
 * @param string $input What somebody typed.
 * @return array{kind:string,value:string} `kind` is `acct`, `card`, `profile` or `''`.
 */
function axismundi_contacts_lookup_route( string $input ) : array {
	$input = trim( $input );
	if ( '' === $input ) {
		return array( 'kind' => '', 'value' => '' );
	}
	$acct = preg_replace( '#^acct:#i', '', $input );
	if ( 1 === preg_match( '#^@?([^@/\s]+)@([^@/\s]+)$#', (string) $acct, $found ) ) {
		return array( 'kind' => 'acct', 'value' => $found[1] . '@' . $found[2] );
	}
	if ( 1 !== preg_match( '#^https?://#i', $input ) ) {
		return array( 'kind' => '', 'value' => '' );
	}
	$path = (string) wp_parse_url( $input, PHP_URL_PATH );
	return array(
		'kind'  => str_ends_with( strtolower( $path ), '.jscontact' ) ? 'card' : 'profile',
		'value' => $input,
	);
}

/**
 * Read something from somewhere else, within the limits this is willing to accept.
 *
 * The same posture the Actor fetcher takes, for the same reasons: `https` only, no redirects to
 * follow somewhere unexpected, a size this will stop reading at, and a content type that has to be
 * what was asked for. `wp_safe_remote_get` is what refuses an address inside this network, which is
 * the difference between fetching a contact card and being asked to knock on a door only this
 * server can reach.
 *
 * @param string   $url   Address to read.
 * @param string[] $types Content types that would be an answer.
 * @return array{body:string,type:string,headers:mixed}|WP_Error
 */
function axismundi_contacts_lookup_get( string $url, array $types ) {
	if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $url ) ) {
		return new WP_Error(
			'ax_contacts_lookup_url',
			__( 'That address cannot be read from here.', 'axismundi-contacts' ),
			array( 'status' => 400 )
		);
	}
	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 10,
			'redirection'         => 0,
			'limit_response_size' => AXISMUNDI_CONTACTS_LOOKUP_MAX_BYTES,
			'headers'             => array( 'Accept' => implode( ', ', $types ) ),
			'user-agent'          => 'Axismundi Contacts/' . AXISMUNDI_CONTACTS_VERSION . '; ' . home_url( '/' ),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error(
			'ax_contacts_lookup_status',
			__( 'That address did not answer with a contact card.', 'axismundi-contacts' ),
			array( 'status' => 502 )
		);
	}
	$type = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] ) );
	if ( array() !== $types && ! in_array( $type, $types, true ) ) {
		return new WP_Error(
			'ax_contacts_lookup_type',
			__( 'That address answered with something else.', 'axismundi-contacts' ),
			array( 'status' => 502 )
		);
	}
	$body = (string) wp_remote_retrieve_body( $response );
	if ( '' === $body || strlen( $body ) >= AXISMUNDI_CONTACTS_LOOKUP_MAX_BYTES ) {
		return new WP_Error(
			'ax_contacts_lookup_size',
			__( 'That answer was empty or larger than this reads.', 'axismundi-contacts' ),
			array( 'status' => 502 )
		);
	}
	return array(
		'body'    => $body,
		'type'    => $type,
		'headers' => wp_remote_retrieve_headers( $response ),
	);
}

/**
 * The contact document announced by a page, if it announces one.
 *
 * Asked of the header first and the markup second, which is the order of how much has to be read to
 * find out. A `Link` header answers before any of the page is parsed; an `alternate` element in the
 * markup answers for a page that says it there instead.
 *
 * What identifies the link is the media type rather than the relation. `alternate` and `describedby`
 * are both reasonable things for a page to say about its own contact document, and a reader that
 * insisted on one of them would miss half the pages that answer correctly.
 *
 * @param string $profile_url The page.
 * @return string|WP_Error The Card address.
 */
function axismundi_contacts_discover_card_url( string $profile_url ) {
	$page = axismundi_contacts_lookup_get( $profile_url, array() );
	if ( is_wp_error( $page ) ) {
		return $page;
	}
	$header = $page['headers'] instanceof WpOrg\Requests\Utility\CaseInsensitiveDictionary
		? $page['headers']->offsetGet( 'link' )
		: null;
	$found = axismundi_contacts_card_url_in_link_header( is_string( $header ) ? $header : '', $profile_url );
	if ( '' === $found && str_contains( $page['type'], 'html' ) ) {
		$found = axismundi_contacts_card_url_in_html( $page['body'], $profile_url );
	}
	if ( '' === $found ) {
		return new WP_Error(
			'ax_contacts_lookup_undiscovered',
			__( 'That page does not say where its contact card is.', 'axismundi-contacts' ),
			array( 'status' => 404 )
		);
	}
	return $found;
}

/**
 * A contact document named in a `Link` header.
 *
 * @param string $header Header value, which may hold several links.
 * @param string $base   The page, for a link written relative to it.
 * @return string Empty when none of them is one.
 */
function axismundi_contacts_card_url_in_link_header( string $header, string $base ) : string {
	foreach ( explode( ',', $header ) as $candidate ) {
		if ( ! str_contains( strtolower( $candidate ), 'application/jscontact+json' ) ) {
			continue;
		}
		if ( 1 === preg_match( '#<([^>]+)>#', $candidate, $found ) ) {
			return axismundi_contacts_absolute_url( trim( $found[1] ), $base );
		}
	}
	return '';
}

/**
 * A contact document named in a page's markup.
 *
 * Read with the parser WordPress already ships rather than with an expression: an attribute may be
 * quoted either way or not at all, and the order they are written in is nobody's promise.
 *
 * @param string $html The page.
 * @param string $base The page's address, for a link written relative to it.
 * @return string Empty when the page names none.
 */
function axismundi_contacts_card_url_in_html( string $html, string $base ) : string {
	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return '';
	}
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( array( 'tag_name' => 'LINK' ) ) ) {
		$type = strtolower( trim( (string) $tags->get_attribute( 'type' ) ) );
		$href = trim( (string) $tags->get_attribute( 'href' ) );
		if ( 'application/jscontact+json' === $type && '' !== $href ) {
			return axismundi_contacts_absolute_url( $href, $base );
		}
	}
	return '';
}

/**
 * One address, read as the page that named it would read it.
 *
 * @param string $href What the link said.
 * @param string $base The page it was said on.
 * @return string
 */
function axismundi_contacts_absolute_url( string $href, string $base ) : string {
	if ( 1 === preg_match( '#^https?://#i', $href ) ) {
		return $href;
	}
	$parts  = (array) wp_parse_url( $base );
	$scheme = (string) ( $parts['scheme'] ?? 'https' );
	$host   = (string) ( $parts['host'] ?? '' );
	$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
	if ( '' === $host ) {
		return '';
	}
	if ( str_starts_with( $href, '/' ) ) {
		return $scheme . '://' . $host . $port . $href;
	}
	// Relative to the directory the page is in, which is what a browser would do with it.
	$path = (string) ( $parts['path'] ?? '/' );
	$dir  = substr( $path, 0, (int) strrpos( $path, '/' ) + 1 );
	return $scheme . '://' . $host . $port . ( '' !== $dir ? $dir : '/' ) . $href;
}

/**
 * What the directory says about an address.
 *
 * @param string $acct `alice@example.com`.
 * @return array{actor:string,card:string}|WP_Error
 */
function axismundi_contacts_lookup_webfinger( string $acct ) {
	$host = (string) ( explode( '@', $acct )[1] ?? '' );
	if ( '' === $host ) {
		return new WP_Error( 'ax_contacts_lookup_acct', __( 'That is not an address this can look up.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	$jrd = axismundi_contacts_lookup_get(
		add_query_arg( 'resource', rawurlencode( 'acct:' . $acct ), 'https://' . $host . '/.well-known/webfinger' ),
		array( 'application/jrd+json', 'application/json' )
	);
	if ( is_wp_error( $jrd ) ) {
		return $jrd;
	}
	$data = json_decode( $jrd['body'], true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'ax_contacts_lookup_jrd', __( 'That directory answered with something this could not read.', 'axismundi-contacts' ), array( 'status' => 502 ) );
	}
	$found = array( 'actor' => '', 'card' => '' );
	foreach ( (array) ( $data['links'] ?? array() ) as $link ) {
		if ( ! is_array( $link ) ) {
			continue;
		}
		$rel  = strtolower( trim( (string) ( $link['rel'] ?? '' ) ) );
		$type = strtolower( trim( explode( ';', (string) ( $link['type'] ?? '' ) )[0] ) );
		$href = trim( (string) ( $link['href'] ?? '' ) );
		if ( '' === $href ) {
			continue;
		}
		/*
		 * Matched on the relation and the type together. A directory is free to answer the relation
		 * filter it was not asked for, and RFC 7033 says a client may not assume its `rel` parameter
		 * was honoured -- so this reads what came back rather than trusting that it was narrowed.
		 */
		if ( 'self' === $rel && 'application/activity+json' === $type && '' === $found['actor'] ) {
			$found['actor'] = $href;
		}
		if ( 'application/jscontact+json' === $type && '' === $found['card'] ) {
			$found['card'] = $href;
		}
	}
	if ( '' === $found['card'] ) {
		return new WP_Error(
			'ax_contacts_lookup_nocard',
			__( 'That address has no contact card to read.', 'axismundi-contacts' ),
			array( 'status' => 404 )
		);
	}
	return $found;
}

/**
 * Read the Card at an address, and say nothing about it beyond what it says.
 *
 * Canonicalised on the way in, which is how everything else here reads a document somebody else
 * wrote: the same shape, the same key order, the same rules about what an entry is. Not validated
 * into refusal, though -- a Card from another implementation may say things this one has no field
 * for, and refusing to show it would make this a reader of its own documents only.
 *
 * @param string $url Card address.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_contacts_lookup_card( string $url ) {
	$read = axismundi_contacts_lookup_get( $url, array( 'application/jscontact+json' ) );
	if ( is_wp_error( $read ) ) {
		return $read;
	}
	try {
		$card = json_decode( $read['body'], true, 64, JSON_THROW_ON_ERROR );
	} catch ( JsonException $error ) {
		return new WP_Error( 'ax_contacts_lookup_json', __( 'That card was not valid JSON.', 'axismundi-contacts' ), array( 'status' => 502 ) );
	}
	if ( ! is_array( $card ) || 'Card' !== (string) ( $card['@type'] ?? 'Card' ) ) {
		return new WP_Error( 'ax_contacts_lookup_card', __( 'That address answered with something that is not a card.', 'axismundi-contacts' ), array( 'status' => 502 ) );
	}
	return axismundi_contacts_canonical_card( $card );
}

/**
 * Somebody, as they publish themselves, held in memory and written nowhere.
 *
 * @param string $input An address, a profile page, or a card.
 * @return array{card:array<string,mixed>,card_url:string,profile_url:string,actor_uri:string}|WP_Error
 */
function axismundi_contacts_lookup( string $input ) {
	$route = axismundi_contacts_lookup_route( $input );
	$found = array( 'card' => array(), 'card_url' => '', 'profile_url' => '', 'actor_uri' => '' );
	if ( 'acct' === $route['kind'] ) {
		$directory = axismundi_contacts_lookup_webfinger( $route['value'] );
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}
		$found['card_url']  = $directory['card'];
		$found['actor_uri'] = $directory['actor'];
	} elseif ( 'profile' === $route['kind'] ) {
		$card_url = axismundi_contacts_discover_card_url( $route['value'] );
		if ( is_wp_error( $card_url ) ) {
			return $card_url;
		}
		$found['card_url']    = $card_url;
		$found['profile_url'] = $route['value'];
	} elseif ( 'card' === $route['kind'] ) {
		$found['card_url'] = $route['value'];
	} else {
		return new WP_Error(
			'ax_contacts_lookup_input',
			__( 'Look somebody up by their address, their profile page, or their card.', 'axismundi-contacts' ),
			array( 'status' => 400 )
		);
	}
	$card = axismundi_contacts_lookup_card( $found['card_url'] );
	if ( is_wp_error( $card ) ) {
		return $card;
	}
	$found['card'] = $card;
	return $found;
}

/**
 * Keep somebody, once, because a person said to.
 *
 * The Card is saved as it was published, uid and all. That uid is what makes a later copy of the
 * same person recognisable as the same person, and minting a new one here would leave this address
 * book holding a record nothing else can match.
 *
 * A uid already in this book is not saved twice and not written over. Two rows for one person is
 * the state from which nothing can say which is current, and overwriting would throw away whatever
 * the person keeping this book had already written about them. The card they already have is handed
 * back instead, for the screen to open.
 *
 * A Card with no uid is saved every time it is saved. RFC 9553 makes `uid` optional, so there are
 * documents that state nothing about which person they are a copy of, and this does not decide the
 * question on their behalf. The two ways to make it idempotent both cost more than the duplicate
 * does: minting a local uid produces an identifier no other implementation can match, and treating
 * the address the card was fetched from as the person makes one person into two the moment they are
 * served from a second place, and two people into one the moment an address is reused.
 *
 * So the honest answer is that a document declining to identify itself is not recognised, and a
 * second save of one makes a second contact. Somebody who pressed the button twice can delete the
 * copy; a book that had silently merged two people cannot be un-merged.
 *
 * Nothing is refreshed or merged from here. What a saved contact does when the original changes is
 * a policy about saved contacts, and it is not decided by the act of saving one.
 *
 * @param int                                                                                    $owner_actor_id Whose book.
 * @param array{card:array<string,mixed>,card_url:string,profile_url:string,actor_uri:string} $found          What the lookup read.
 * @return array{card_id:int,existed:bool}|WP_Error
 */
function axismundi_contacts_save_looked_up( int $owner_actor_id, array $found ) {
	$card = (array) ( $found['card'] ?? array() );
	if ( array() === $card ) {
		return new WP_Error( 'ax_contacts_lookup_empty', __( 'There is no card here to save.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	$uid = trim( (string) ( $card['uid'] ?? '' ) );
	if ( '' !== $uid ) {
		$holder = axismundi_contacts_find_by_uid( $owner_actor_id, $uid );
		if ( 0 !== $holder ) {
			return array( 'card_id' => $holder, 'existed' => true );
		}
	}
	$book = axismundi_contacts_home_book_id( $owner_actor_id );
	if ( $book <= 0 ) {
		return new WP_Error( 'ax_contacts_lookup_book', __( 'There is no address book to save this into.', 'axismundi-contacts' ), array( 'status' => 400 ) );
	}
	$saved = axismundi_contacts_save_card( $book, $card );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	$card_id = (int) $saved;
	/*
	 * How it was reached, so that finding it again does not depend on somebody remembering. The
	 * address it was fetched from, the page that announced it where there was one, and the Actor it
	 * belongs to where the directory said so.
	 */
	/*
	 * If the Actor the directory named is one this site already knows, the row it is kept in is
	 * written down too. That is the confident answer to "whose picture goes with this card": a
	 * contact saved by looking somebody up never has to be matched by guesswork afterwards.
	 *
	 * Only if it is already known. Fetching an Actor to fill this in would turn saving a contact into
	 * a second act of federation, and the picture is not worth that.
	 */
	$row = '';
	if ( '' !== (string) ( $found['actor_uri'] ?? '' ) && function_exists( 'axismundi_actors_get_by_uri' ) ) {
		$known = axismundi_actors_get_by_uri( (string) $found['actor_uri'] );
		$row   = $known instanceof Axismundi_Actor ? (string) $known->get_identity_id() : '';
	}
	foreach (
		array(
			'card'    => (string) ( $found['card_url'] ?? '' ),
			'profile' => (string) ( $found['profile_url'] ?? '' ),
			'actor'   => (string) ( $found['actor_uri'] ?? '' ),
			'row'     => $row,
		) as $which => $value
	) {
		if ( '' === $value ) {
			continue;
		}
		axismundi_contacts_set_provenance(
			$card_id,
			AXISMUNDI_CONTACTS_SOURCE_POINTERS[ $which ],
			AXISMUNDI_CONTACTS_SOURCE_LOOKUP,
			$value
		);
	}
	return array( 'card_id' => $card_id, 'existed' => false );
}

/**
 * How long a result somebody is looking at stays saveable.
 *
 * Long enough to read a card and decide, short enough that a form left open in a tab overnight is
 * not still a licence to write. There is nothing to expire on the server, so this is the only thing
 * that ends it.
 */
const AXISMUNDI_CONTACTS_LOOKUP_SEAL_TTL = 900;

/**
 * Hand a result to the next request without writing it down.
 *
 * The awkward part of a two-step lookup is where the result waits. Keeping it on the server -- a
 * transient, a draft row -- contradicts the thing this screen promises: that looking somebody up
 * leaves no trace. Fetching it again when Save is pressed avoids the write but breaks the other
 * promise, because the card that arrives the second time is not necessarily the one that was read,
 * and somebody would be saving a document they were never shown.
 *
 * So the result travels with the person. It goes into the form as text and comes back with it, and a
 * signature over that text is what makes it trustworthy: this site wrote it, recently, for this
 * person acting as this Actor. A payload that fails any of those is not a card that arrived late, it
 * is a card this site never fetched, and it is refused rather than saved.
 *
 * @param array{card:array<string,mixed>,card_url:string,profile_url:string,actor_uri:string} $found What the lookup read.
 * @param int                                                                                 $owner_actor_id Whose book it would be saved into.
 * @return string Opaque to everything but `axismundi_contacts_lookup_unseal()`.
 */
function axismundi_contacts_lookup_seal( array $found, int $owner_actor_id ) : string {
	$body = base64_encode(
		(string) wp_json_encode(
			array(
				'found'   => $found,
				'owner'   => $owner_actor_id,
				'user'    => get_current_user_id(),
				'expires' => time() + AXISMUNDI_CONTACTS_LOOKUP_SEAL_TTL,
			)
		)
	);
	return $body . '.' . hash_hmac( 'sha256', $body, wp_salt( 'nonce' ) );
}

/**
 * The result somebody was shown, or nothing.
 *
 * Every refusal here answers the same way. Which of the four checks failed is a fact about how the
 * payload was tampered with, and saying so out loud is free help to whoever tampered with it.
 *
 * @param string $sealed         What came back with the form.
 * @param int    $owner_actor_id Who is acting now.
 * @return array{card:array<string,mixed>,card_url:string,profile_url:string,actor_uri:string}|WP_Error
 */
function axismundi_contacts_lookup_unseal( string $sealed, int $owner_actor_id ) {
	$stale = new WP_Error(
		'ax_contacts_lookup_sealed',
		__( 'That result is no longer available to save. Look them up again.', 'axismundi-contacts' ),
		array( 'status' => 400 )
	);
	$at = strrpos( $sealed, '.' );
	if ( false === $at ) {
		return $stale;
	}
	$body      = substr( $sealed, 0, $at );
	$signature = substr( $sealed, $at + 1 );
	// Compared in constant time, which is what keeps a signature from being guessed one byte at a time.
	if ( ! hash_equals( hash_hmac( 'sha256', $body, wp_salt( 'nonce' ) ), $signature ) ) {
		return $stale;
	}
	$payload = json_decode( (string) base64_decode( $body, true ), true );
	if ( ! is_array( $payload ) ) {
		return $stale;
	}
	if (
		(int) ( $payload['expires'] ?? 0 ) < time()
		|| (int) ( $payload['user'] ?? 0 ) !== get_current_user_id()
		|| (int) ( $payload['owner'] ?? 0 ) !== $owner_actor_id
	) {
		return $stale;
	}
	$found = (array) ( $payload['found'] ?? array() );
	return array(
		'card'        => (array) ( $found['card'] ?? array() ),
		'card_url'    => (string) ( $found['card_url'] ?? '' ),
		'profile_url' => (string) ( $found['profile_url'] ?? '' ),
		'actor_uri'   => (string) ( $found['actor_uri'] ?? '' ),
	);
}
