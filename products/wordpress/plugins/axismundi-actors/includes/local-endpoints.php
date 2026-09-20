<?php
/**
 * What a local Actor advertises to the network: its endpoints and its signing key.
 *
 * ActivityPub requires an actor to name an `inbox` and an `outbox`. Those are properties of
 * the identity, so the answer to "what are this Actor's endpoints, and can it prove who it
 * is" belongs here, beside the registry that already stores exactly that for every remote
 * Actor it has observed. A transport plugin supplies the addresses it serves and the key it
 * can sign with; it does not decide whether this site has an Actor to advertise.
 *
 * The rule this file owns is fail-closed, and its reason is other people's caches. An Actor
 * advertised with an Inbox but no verifiable key can be fetched and stored by a remote
 * server, which will then reject our signed traffic until its copy goes stale -- roughly a
 * day on Mastodon. So the transport members are published as one bundle or not at all, and
 * WebFinger withholds the ActivityStreams link in the same condition. What gates them is
 * the presence of a key, not the presence of any particular plugin.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/**
 * The transport endpoints a local Actor serves, keyed by endpoint type.
 *
 * @param Axismundi_Actor $actor Local Actor.
 * @return array<string,string> Non-empty endpoint URIs.
 */
function axismundi_actors_local_endpoints( Axismundi_Actor $actor ) : array {
	if ( ! $actor->is_local() ) {
		return array();
	}
	/**
	 * Supply the endpoint addresses a transport serves for one local Actor.
	 *
	 * Keys are the endpoint types this registry already knows: `inbox`, `shared_inbox`,
	 * and the read collections a representation layer serves.
	 *
	 * @since 0.1.3
	 * @param array<string,string> $endpoints Endpoints resolved so far.
	 * @param Axismundi_Actor      $actor     Local Actor.
	 */
	$endpoints = (array) apply_filters( 'axismundi_actors_local_endpoints', array(), $actor );
	$out       = array();
	foreach ( axismundi_actors_endpoint_types() as $type ) {
		$uri = axismundi_actors_normalize_local_endpoint_uri( $endpoints[ $type ] ?? '' );
		if ( '' !== $uri ) {
			$out[ $type ] = $uri;
		}
	}
	return $out;
}

/**
 * Normalize one endpoint address this site serves itself.
 *
 * A remote endpoint has to be HTTPS, because we fetch and deliver to it. Our own is
 * whatever this site is reachable at, which on a development or staging origin is plain
 * HTTP -- refusing it there would leave the site advertising an Actor with no Inbox for the
 * one reason that has nothing to do with the Actor. What is checked instead is ownership:
 * a transport may only claim an address under this site's own home URL.
 *
 * @param mixed $value Candidate URI.
 * @return string Empty when it is not ours.
 */
function axismundi_actors_normalize_local_endpoint_uri( $value ) : string {
	$url = esc_url_raw( is_string( $value ) ? trim( $value ) : '' );
	if ( '' === $url ) {
		return '';
	}
	$home = trailingslashit( (string) home_url( '/' ) );
	return str_starts_with( trailingslashit( $url ), $home ) || str_starts_with( $url, untrailingslashit( $home ) . '/' ) ? $url : '';
}

/**
 * The public key descriptor a local Actor can prove itself with, or null when it has none.
 *
 * The private half stays with whoever signs; this is the half that goes on the wire.
 *
 * @param Axismundi_Actor $actor Local Actor.
 * @return array{id:string,owner:string,publicKeyPem:string}|null
 */
function axismundi_actors_local_public_key( Axismundi_Actor $actor ) : ?array {
	if ( ! $actor->is_local() ) {
		return null;
	}
	/**
	 * Supply the signing key descriptor for one local Actor.
	 *
	 * @since 0.1.3
	 * @param array|null      $key   Descriptor with id, owner and publicKeyPem, or null.
	 * @param Axismundi_Actor $actor Local Actor.
	 */
	$key = apply_filters( 'axismundi_actors_local_public_key', null, $actor );
	if ( ! is_array( $key ) ) {
		return null;
	}
	$pem   = (string) ( $key['publicKeyPem'] ?? '' );
	$id    = (string) ( $key['id'] ?? '' );
	$owner = (string) ( $key['owner'] ?? $actor->get_uri() );
	if ( '' === $id || ! axismundi_actors_is_public_key_pem( $pem ) ) {
		return null;
	}
	return array( 'id' => $id, 'owner' => $owner, 'publicKeyPem' => $pem );
}

/**
 * Whether this Actor may be advertised to the network right now.
 *
 * Public, local, serving an Inbox, and able to prove itself. Anything less is withheld
 * rather than published half-formed.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return bool
 */
function axismundi_actors_is_federatable( Axismundi_Actor $actor ) : bool {
	if ( ! $actor->is_local() || 'public' !== $actor->get_status() ) {
		return false;
	}
	$endpoints = axismundi_actors_local_endpoints( $actor );
	return '' !== (string) ( $endpoints['inbox'] ?? '' ) && null !== axismundi_actors_local_public_key( $actor );
}

/**
 * The Actor-document members a transport owns, as one atomic bundle.
 *
 * Empty when the Actor cannot be advertised, so a representation layer can merge the result
 * without deciding the question itself.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return array<string,mixed>
 */
function axismundi_actors_transport_members( Axismundi_Actor $actor ) : array {
	if ( ! $actor->is_local() || 'public' !== $actor->get_status() ) {
		return array();
	}
	/*
	 * One reading of the key, and one of the endpoints, for the whole bundle. Asking twice
	 * would let a rotation land between the two questions and publish an Inbox beside the
	 * key it no longer matches.
	 */
	$endpoints = axismundi_actors_local_endpoints( $actor );
	$key       = axismundi_actors_local_public_key( $actor );
	if ( '' === (string) ( $endpoints['inbox'] ?? '' ) || null === $key ) {
		return array();
	}
	$members = array(
		'inbox'     => $endpoints['inbox'],
		'publicKey' => $key,
	);
	if ( '' !== (string) ( $endpoints['shared_inbox'] ?? '' ) ) {
		$members['endpoints'] = array( 'sharedInbox' => $endpoints['shared_inbox'] );
	}
	return $members;
}

/**
 * Advertise the ActivityStreams Actor document through WebFinger once it is federatable.
 *
 * Discovery fails closed with the rest: resolving a handle to a keyless Actor is how a
 * remote server ends up holding a copy it cannot talk to.
 *
 * @param array<int,array<string,string>> $links Links so far.
 * @param Axismundi_Actor                 $actor Actor.
 * @return array<int,array<string,string>>
 */
function axismundi_actors_webfinger_self_link( array $links, Axismundi_Actor $actor ) : array {
	if ( ! axismundi_actors_is_federatable( $actor ) ) {
		return $links;
	}
	foreach ( $links as $link ) {
		if ( 'self' === ( $link['rel'] ?? '' ) ) {
			return $links;
		}
	}
	$links[] = array(
		'rel'  => 'self',
		'type' => 'application/activity+json',
		'href' => $actor->get_uri(),
	);
	return $links;
}
add_filter( 'axismundi_actors_webfinger_links', 'axismundi_actors_webfinger_self_link', 20, 2 );
