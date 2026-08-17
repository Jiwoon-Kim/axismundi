<?php
/**
 * An Actor as a JSContact Card (RFC 9553 / 9982).
 *
 * The facts stay here, where they are owned; this says them in the contact vocabulary. It is the
 * second representation of the same Actor -- ActivityStreams is assembled by Object Projections and
 * answers "who is this on the network", while this answers "who is this as an entry in an address
 * book". Neither is derived from the other, and no fact is stored twice to make both work.
 *
 * Two shapes worth stating because getting them wrong is easy and quiet:
 *
 * `@type` is always `Card`. The kind of thing it describes is `kind`, so a Person Card is
 * `{"@type":"Card","kind":"individual"}` and never `{"@type":"Person"}` -- the second reads as
 * ActivityStreams wearing a JSContact filename.
 *
 * `uid` is minted only for identities this site actually owns. A remote Actor's `uid` is whatever
 * that server published, or nothing at all: the UUID we generated while caching them names our
 * snapshot, not their portable identity, and publishing it would hand an address book a permanent
 * identifier for a person that only exists in our database. RFC 9982 makes `uid` optional precisely
 * so that a converter does not have to invent one.
 *
 * MVP scope: identity, kind, and names. Emails, phones, addresses, birthdays, links and calendars are
 * typed facts the Actor does not store yet, and an empty `emails` object would say something untrue
 * about somebody who simply has not been asked.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/** The media type RFC 9553 registers. */
const AXISMUNDI_ACTORS_JSCONTACT_MEDIA_TYPE = 'application/jscontact+json; type=Card';

/**
 * What kind of thing this Actor is, in JSContact's vocabulary.
 *
 * `Service` has no standard value and deliberately gets none: RFC 9553 allows vendor-specific enum
 * values, but minting one before anything consumes it would put an Axismundi-only word into a
 * document whose whole purpose is being read elsewhere.
 *
 * @param string $actor_type ActivityStreams actor type.
 * @return string JSContact kind, or '' when there is no honest answer.
 */
function axismundi_actors_jscontact_kind( string $actor_type ) : string {
	switch ( $actor_type ) {
		case 'Person':
			return 'individual';
		case 'Organization':
			return 'org';
		case 'Group':
			return 'group';
		case 'Application':
			return 'application';
		default:
			return '';
	}
}

/**
 * The `uid` for one Actor, or '' when this site has no business minting one.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return string
 */
function axismundi_actors_jscontact_uid( Axismundi_Actor $actor ) : string {
	if ( ! $actor->is_local() ) {
		// Only what they published themselves, and nothing is a valid answer.
		$payload = axismundi_actors_get_remote_payload( $actor->get_identity_id() );
		$uid     = trim( (string) ( $payload['uid'] ?? '' ) );
		return $uid;
	}
	$uuid = trim( (string) $actor->get_uuid() );
	return '' === $uuid ? '' : 'urn:uuid:' . $uuid;
}

/**
 * One language's name, as JSContact states it.
 *
 * `isOrdered` is the claim that the components are already in the order they are read in, which is
 * the whole reason the order is stored: a consumer must not reassemble a Korean name the way it
 * would an English one.
 *
 * @param array<string,mixed> $row Stored name row.
 * @return array<string,mixed>|null
 */
function axismundi_actors_jscontact_name( array $row ) : ?array {
	$map = array(
		'honorific_prefix' => 'title',
		'first_name'       => 'given',
		'middle_name'  => 'given2',
		'last_name'      => 'surname',
		'honorific_suffix' => 'credential',
	);
	$order = 'family-given' === (string) ( $row['display_order'] ?? '' )
		? array( 'honorific_prefix', 'last_name', 'first_name', 'middle_name', 'honorific_suffix' )
		: array( 'honorific_prefix', 'first_name', 'middle_name', 'last_name', 'honorific_suffix' );

	$components = array();
	foreach ( $order as $part ) {
		$value = trim( (string) ( $row[ $part ] ?? '' ) );
		if ( '' !== $value ) {
			$components[] = array( '@type' => 'NameComponent', 'kind' => $map[ $part ], 'value' => $value );
		}
	}
	$full = axismundi_actors_assemble_person_name( $row );
	if ( array() === $components && '' === $full ) {
		return null;
	}
	$name = array( '@type' => 'Name' );
	if ( '' !== $full ) {
		$name['full'] = $full;
	}
	if ( array() !== $components ) {
		$name['components'] = $components;
		$name['isOrdered']  = true;
	}
	return $name;
}

/**
 * One Actor as a JSContact Card.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_actors_jscontact_card( Axismundi_Actor $actor ) {
	$kind = axismundi_actors_jscontact_kind( $actor->get_type() );
	if ( '' === $kind ) {
		return new WP_Error(
			'ax_actors_jscontact_kind',
			__( 'There is no contact kind for that sort of Actor.', 'axismundi-actors' ),
			array( 'status' => 404 )
		);
	}
	$card = array(
		'@type'   => 'Card',
		'version' => '2.0',
		'kind'    => $kind,
	);
	$uid = axismundi_actors_jscontact_uid( $actor );
	if ( '' !== $uid ) {
		$card['uid'] = $uid;
	}

	$language = axismundi_actors_normalize_language_tag( (string) $actor->get_default_language() );
	if ( '' !== $language ) {
		$card['language'] = $language;
	}

	$names = 'Person' === $actor->get_type() ? axismundi_actors_person_names( $actor->get_identity_id() ) : array();
	$name  = isset( $names[ $language ] ) ? axismundi_actors_jscontact_name( $names[ $language ] ) : null;
	if ( null === $name ) {
		// Whatever this Actor is already called. An Organization has a name and no components, and so
		// does a person who never filled any in.
		$display = trim( $actor->get_display_name() );
		$name    = '' !== $display ? array( '@type' => 'Name', 'full' => $display ) : null;
	}
	if ( null !== $name ) {
		$card['name'] = $name;
	}

	/*
	 * The other languages, as JSContact keeps them: a patch per tag rather than a second Card. Only
	 * the name is localized here, because it is the only thing this slice stores per language.
	 */
	$localizations = array();
	foreach ( $names as $tag => $row ) {
		if ( $tag === $language ) {
			continue;
		}
		$localized = axismundi_actors_jscontact_name( $row );
		if ( null !== $localized ) {
			$localizations[ $tag ] = array( 'name' => $localized );
		}
	}
	if ( array() !== $localizations ) {
		$card['localizations'] = $localizations;
	}
	/**
	 * Let a domain add what it owns.
	 *
	 * Calendars, and later places and typed contact facts, are other plugins' knowledge. An identity
	 * registry that assembled them would be holding a second copy of facts it does not own -- so it
	 * builds the identity half and asks.
	 *
	 * Anything added here is going into a public document: contributors must add only what the Actor
	 * has published.
	 *
	 * @param array<string,mixed> $card  Card so far.
	 * @param Axismundi_Actor     $actor Actor being described.
	 */
	return (array) apply_filters( 'axismundi_actors_jscontact_card', $card, $actor );
}

/** @return array<string,string> */
function axismundi_actors_jscontact_rewrite_rules() : array {
	return array( '^@([^/]+)\.jscontact$' => 'index.php?ax_actor_jscontact=$matches[1]' );
}

/** @return void */
function axismundi_actors_register_jscontact_routes() : void {
	foreach ( axismundi_actors_jscontact_rewrite_rules() as $regex => $query ) {
		add_rewrite_rule( $regex, $query, 'top' );
	}
}
add_action( 'init', 'axismundi_actors_register_jscontact_routes', 7 );

/**
 * @param string[] $vars Query vars.
 * @return string[]
 */
function axismundi_actors_jscontact_query_vars( array $vars ) : array {
	$vars[] = 'ax_actor_jscontact';
	return $vars;
}
add_filter( 'query_vars', 'axismundi_actors_jscontact_query_vars' );

/**
 * Serve one Actor's Card.
 *
 * Public profiles only, through the same predicate the profile hub uses. A Card is a document
 * strangers fetch, and an Actor whose profile is not published has not agreed to be one.
 *
 * @return void
 */
function axismundi_actors_serve_jscontact() : void {
	$handle = (string) get_query_var( 'ax_actor_jscontact' );
	if ( '' === $handle ) {
		return;
	}
	$actor = axismundi_actors_get_by_handle( $handle );
	$card  = $actor instanceof Axismundi_Actor && axismundi_actors_is_public_profile( $actor )
		? axismundi_actors_jscontact_card( $actor )
		: new WP_Error( 'ax_actors_jscontact_missing', 'not_found' );
	if ( is_wp_error( $card ) ) {
		status_header( 404 );
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo wp_json_encode( array( 'error' => 'not_found' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response.
		exit;
	}
	status_header( 200 );
	header( 'Content-Type: ' . AXISMUNDI_ACTORS_JSCONTACT_MEDIA_TYPE . '; charset=' . get_option( 'blog_charset' ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Access-Control-Allow-Origin: *' );
	echo wp_json_encode( $card ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response.
	exit;
}
add_action( 'template_redirect', 'axismundi_actors_serve_jscontact', 4 );
