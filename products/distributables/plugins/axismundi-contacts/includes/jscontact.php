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
	$card = axismundi_contacts_public_projection( $stored, axismundi_contacts_published_pointers( (int) $actor->get_identity_id() ) );

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
	$card  = $actor instanceof Axismundi_Actor && axismundi_contacts_jscontact_is_public( $actor )
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

/**
 * Put the public Card on the Actor document, when there is one anybody may fetch.
 *
 * AS2 says this with what it already has: `attachment` is what is associated with an Object by
 * inclusion, which is exactly a contact document hanging off a profile. `tag` would be wrong -- that
 * is association by reference, for the entities an object mentions. The `mediaType` is what makes it
 * discoverable as a contact card rather than as one more link.
 *
 * Contributed from here rather than from the identity registry, so nothing else has to know what
 * `public` means in these tables. This adds a link or does nothing.
 *
 * @param array<string,mixed> $object Actor document so far.
 * @param Axismundi_Actor     $actor  Actor being described.
 * @return array<string,mixed>
 */
function axismundi_contacts_actor_attachment( array $object, Axismundi_Actor $actor ) : array {
	$link = axismundi_contacts_public_profile_link( (int) $actor->get_identity_id() );
	if ( null === $link ) {
		return $object;
	}
	$attachments = isset( $object['attachment'] ) && is_array( $object['attachment'] ) ? $object['attachment'] : array();
	foreach ( $attachments as $existing ) {
		/*
		 * Added once. A projection may be built more than once in a request, and another contributor
		 * may name the same document; a second copy would tell a reader there are two cards.
		 */
		if ( is_array( $existing ) && ( $existing['href'] ?? '' ) === $link['href'] ) {
			return $object;
		}
	}
	/*
	 * Appended, never assigned. What is already there is somebody's profile fields, and an attachment
	 * list replaced wholesale would drop the website they put on their profile to make room for this.
	 */
	$attachments[]        = $link;
	$object['attachment'] = $attachments;
	return $object;
}
add_filter( 'axismundi_op_actor_projection_fields', 'axismundi_contacts_actor_attachment', 10, 2 );
