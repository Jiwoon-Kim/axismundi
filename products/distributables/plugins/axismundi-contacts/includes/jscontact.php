<?php
/**
 * The JSContact document an Actor publishes, and the route that serves it.
 *
 * Contacts owns this. The Card is stored here as a document somebody authored, so rendering it is
 * mostly handing back what is already written down rather than assembling facts from elsewhere --
 * which is the point: a representation built fresh from other people's tables each time is a second
 * record of the same facts, and the two eventually disagree.
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
	$card = axismundi_contacts_card_document( $card_id );
	if ( array() === $card ) {
		return new WP_Error( 'ax_contacts_jscontact_none', __( 'That contact card could not be read.', 'axismundi-contacts' ), array( 'status' => 404 ) );
	}
	// Stated on the way out rather than stored: the document is what was authored, not what it is
	// currently serialised as, and a version pinned into every stored row is a migration waiting.
	$card = array_merge( array( '@type' => 'Card', 'version' => '2.0' ), $card );

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
