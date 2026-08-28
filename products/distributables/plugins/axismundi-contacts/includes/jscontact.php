<?php
/**
 * The JSContact document an Actor publishes, and the route that serves it.
 *
 * Contacts owns this. The Card is stored here as a document somebody authored, and what is served is
 * the part of it they said could be published -- entry by entry, never the document itself. A Card
 * holds a mobile number, a home address and notes about other people, and "share my profile" is not
 * somebody saying yes to all of that.
 *
 * What is published is taken rather than filtered. A rule of "everything except" hands out every
 * property added later and every property a future revision invents; a rule of "only these" fails by
 * publishing too little, which is the direction to fail in.
 *
 * What other domains own, they contribute. Actors adds the names in the Actor's other languages and
 * the anniversaries it keeps; Calendar adds calendars. Each writes only what it is the authority on,
 * and none of them may change the identity of the Card they are adding to.
 *
 * There is no fallback. An Actor without a profile Card has no JSContact document, and this answers
 * 404 rather than deriving one from the identity registry. A derived Card would need an identifier,
 * this site would mint one, and the day somebody made a real profile Card the published identity
 * would change underneath everybody who had already saved it.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit;

/** The media type RFC 9553 registers. */
const AXISMUNDI_CONTACTS_JSCONTACT_MEDIA_TYPE = 'application/jscontact+json; type=Card';

/**
 * One Actor's Card, as published.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_contacts_jscontact_card( Axismundi_Actor $actor ) {
	$card_id = axismundi_contacts_profile_card( (int) $actor->get_identity_id() );
	if ( $card_id <= 0 ) {
		return new WP_Error(
			'ax_contacts_jscontact_none',
			__( 'That Actor publishes no contact card.', 'axismundi-contacts' ),
			array( 'status' => 404 )
		);
	}
	$stored = axismundi_contacts_card_document( $card_id );
	if ( array() === $stored ) {
		return new WP_Error( 'ax_contacts_jscontact_none', __( 'That contact card could not be read.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	/*
	 * The projection, not the document. What is stored is everything somebody wrote down -- a mobile
	 * number, a home address, notes about other people -- and this is the part they said could be
	 * published, entry by entry. `version` is stated on the way out rather than stored, because the
	 * document is what was authored rather than what it is currently serialised as.
	 */
	/*
	 * Two layers, and they answer to different things. The identity -- name, picture, handle -- is
	 * what a public Actor is already telling everybody, so it is served whenever the Actor is public.
	 * The rest of the Card is served only when its owner has published it to everybody, which is what
	 * `sharing` says and what `published` chooses from.
	 */
	$identity = axismundi_contacts_identity_pointers( $stored );
	$chosen   = 'public' === axismundi_contacts_profile_sharing( (int) $actor->get_identity_id() )
		? axismundi_contacts_published_pointers( (int) $actor->get_identity_id() )
		: array();
	$card     = axismundi_contacts_public_projection( $stored, array_values( array_unique( array_merge( $identity, $chosen ) ) ) );

	/**
	 * Let a domain add what it owns.
	 *
	 * Names in other languages, anniversaries, calendars: facts other plugins keep. Contacts holds
	 * the Card and asks, rather than keeping a second copy of every fact it would need to assemble
	 * one.
	 *
	 * Anything added here goes into a public document, so a contributor must add only what the Actor
	 * has published. The identity of the Card is not a contributor's to change: `uid` and `kind` are
	 * restored after this runs.
	 *
	 * @param array<string,mixed> $card  Card so far.
	 * @param Axismundi_Actor     $actor Actor being described.
	 */
	$enriched = (array) apply_filters( 'axismundi_contacts_jscontact_card', $card, $actor );

	/*
	 * Identity survives enrichment. A contributor adding calendars has no business changing which
	 * Card this is or what kind of thing it describes, and a mistake there would be published as a
	 * different contact rather than as a broken field.
	 */
	foreach ( array( 'uid', 'kind' ) as $fixed ) {
		if ( isset( $card[ $fixed ] ) ) {
			$enriched[ $fixed ] = $card[ $fixed ];
		} else {
			unset( $enriched[ $fixed ] );
		}
	}
	/*
	 * And a contributor may not put back what the projection left out. Everything here is reachable
	 * by anybody, a contributor adding a property is adding it to a public document, and the list of
	 * what this Actor publishes was answered once by the person it describes.
	 */
	foreach ( array_keys( $enriched ) as $property ) {
		if ( ! array_key_exists( $property, $card ) ) {
			unset( $enriched[ $property ] );
		}
	}
	return $enriched;
}

/**
 * Whether this Actor's Card may be handed to an unauthenticated request.
 *
 * Two gates, because they answer different questions. The Actor's profile must be published at all,
 * and the Card itself must be shared publicly -- `contacts` is decided from the owner's own address
 * book and has no answer for a stranger or another server, so it is not served rather than being
 * measured by some weaker test.
 *
 * Defaulting to `off` makes this stricter than the route it replaces, deliberately. That route
 * published a name and a kind; this document carries whatever somebody put on their card, which is
 * telephone numbers and home addresses. Publishing those because nobody had said not to would be
 * the wrong default to have chosen once.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return bool
 */
function axismundi_contacts_jscontact_is_public( Axismundi_Actor $actor ) : bool {
	if ( ! function_exists( 'axismundi_actors_is_public_profile' ) || ! axismundi_actors_is_public_profile( $actor ) ) {
		return false;
	}
	return 'public' === axismundi_contacts_profile_sharing( (int) $actor->get_identity_id() );
}

/**
 * Whether this address answers at all.
 *
 * A different question from the one above, and they were the same question until identity stopped
 * being something a person could switch off. A public Actor already answers with its name, its
 * picture and its handle at its own address; the same facts in JSContact are readable for the same
 * reason, whatever its owner has chosen to publish beyond them.
 *
 * What sharing decides is how much comes back -- identity alone, or identity and the card its owner
 * published to everybody. That is `axismundi_contacts_jscontact_card()`'s business, and it is why
 * this may say yes where the question above says no.
 *
 * An Actor that is not public answers nothing here whatever its owner published, because the person
 * this card is about is not answering at their own address either.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return bool
 */
function axismundi_contacts_jscontact_is_readable( Axismundi_Actor $actor ) : bool {
	return function_exists( 'axismundi_actors_is_public_profile' ) && axismundi_actors_is_public_profile( $actor );
}

/**
 * Where an Actor's Card is read, whether or not anything advertises it.
 *
 * One place builds this address, because two would eventually disagree about it and the one nobody
 * looked at would be the one in the JRD.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return string Empty when this Actor publishes no Card, or has no handle to publish it under.
 */
function axismundi_contacts_jscontact_url( Axismundi_Actor $actor ) : string {
	$handle = trim( $actor->get_preferred_username() );
	if ( '' === $handle || axismundi_contacts_profile_card( (int) $actor->get_identity_id() ) <= 0 ) {
		return '';
	}
	return home_url( '/@' . rawurlencode( $handle ) . '.jscontact' );
}

/**
 * Say, in answer to `acct:`, where the contact document for that account is.
 *
 * WebFinger is the question "what is there about this account", and this is one of the answers. The
 * relation is `describedby` -- a registered one, meaning a resource that describes the subject --
 * rather than something invented here, because the relation says what the link is *for* and the
 * media type says what it *is*. A private relation naming the format would put the same fact in two
 * places and make every reader learn a word this project made up. It also leaves room for a second
 * description in another format later, which is the whole reason the two are separate fields.
 *
 * Advertised whenever the address answers. Since a public Actor's Card is readable for the same
 * reason its name and picture are -- it says the things the Actor is already saying next door -- a
 * JRD that omitted the link would be describing the account less completely than the account itself
 * does. What sharing decides is how much comes back from that address, which is the Card's business
 * and not the directory's.
 *
 * @param array<int,array<string,string>> $links Links so far.
 * @param Axismundi_Actor                 $actor Actor being described.
 * @return array<int,array<string,string>>
 */
function axismundi_contacts_webfinger_jscontact_link( array $links, $actor ) : array {
	if ( ! $actor instanceof Axismundi_Actor || ! axismundi_contacts_jscontact_is_readable( $actor ) ) {
		return $links;
	}
	$href = axismundi_contacts_jscontact_url( $actor );
	if ( '' === $href ) {
		return $links;
	}
	/*
	 * The media type without its parameter. What is served states `type=Card`, which is the standard's
	 * own parameter and worth saying in a response; here it would only give a reader comparing strings
	 * a way to miss the link it is looking for.
	 */
	$links[] = array(
		'rel'  => 'describedby',
		'type' => 'application/jscontact+json',
		'href' => $href,
	);
	return $links;
}
add_filter( 'axismundi_actors_webfinger_links', 'axismundi_contacts_webfinger_jscontact_link', 10, 2 );

/** @return array<string,string> */
function axismundi_contacts_jscontact_rewrite_rules() : array {
	// The URL the Actors plugin served this at, kept exactly: the owner moved, the address did not.
	return array( '^@([^/]+)\.jscontact$' => 'index.php?ax_actor_jscontact=$matches[1]' );
}

/** @return void */
function axismundi_contacts_register_jscontact_routes() : void {
	foreach ( axismundi_contacts_jscontact_rewrite_rules() as $regex => $query ) {
		add_rewrite_rule( $regex, $query, 'top' );
	}
}
add_action( 'init', 'axismundi_contacts_register_jscontact_routes', 7 );

/**
 * @param string[] $vars Query vars.
 * @return string[]
 */
function axismundi_contacts_jscontact_query_vars( array $vars ) : array {
	$vars[] = 'ax_actor_jscontact';
	return $vars;
}
add_filter( 'query_vars', 'axismundi_contacts_jscontact_query_vars' );

/**
 * Serve one Actor's Card.
 *
 * @return void
 */
function axismundi_contacts_serve_jscontact() : void {
	$handle = (string) get_query_var( 'ax_actor_jscontact' );
	if ( '' === $handle || ! function_exists( 'axismundi_actors_get_by_handle' ) ) {
		return;
	}
	$actor = axismundi_actors_get_by_handle( $handle );
	$card  = $actor instanceof Axismundi_Actor && axismundi_contacts_jscontact_is_readable( $actor )
		? axismundi_contacts_jscontact_card( $actor )
		: new WP_Error( 'ax_contacts_jscontact_missing', 'not_found' );
	if ( is_wp_error( $card ) ) {
		/*
		 * The same 404 whether the Actor does not exist, publishes no Card, or shares it only with
		 * people they have saved. Answering differently would turn this route into a way to ask who
		 * somebody keeps in their address book.
		 */
		status_header( 404 );
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo wp_json_encode( array( 'error' => 'not_found' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response.
		exit;
	}
	status_header( 200 );
	header( 'Content-Type: ' . AXISMUNDI_CONTACTS_JSCONTACT_MEDIA_TYPE . '; charset=' . get_option( 'blog_charset' ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Access-Control-Allow-Origin: *' );
	echo wp_json_encode( $card ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response.
	exit;
}
add_action( 'template_redirect', 'axismundi_contacts_serve_jscontact', 4 );

