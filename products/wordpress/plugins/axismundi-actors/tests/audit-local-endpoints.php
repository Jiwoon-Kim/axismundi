<?php
/**
 * What a local Actor advertises (dev-only).
 *
 * ActivityPub requires an actor to name an Inbox, so the answer belongs to this registry
 * rather than to whichever transport happens to be installed. A transport supplies the
 * addresses it serves and the key it can sign with; the decision to advertise, and the rule
 * that the transport members go out together or not at all, are asserted here.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_le_results = array();

/** @param bool[] $results Results. */
function ax_le_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

$ax_le_user  = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
$ax_le_actor = $ax_le_user ? axismundi_actors_get_for_user( (int) $ax_le_user[0]->ID ) : null;
if ( ! $ax_le_actor instanceof Axismundi_Actor ) {
	ax_le_assert( $ax_le_results, 'a local Actor exists to advertise', false );
	printf( "\n%d/%d passed\n", 0, 1 );
	return;
}

// An address is ours or it is nobody's. A transport may only claim what this site serves.
ax_le_assert(
	$ax_le_results,
	'an endpoint under this site is accepted whatever its scheme',
	home_url( '/wp-json/x/inbox' ) === axismundi_actors_normalize_local_endpoint_uri( home_url( '/wp-json/x/inbox' ) )
);
ax_le_assert(
	$ax_le_results,
	'an address on somebody else\'s host is refused',
	'' === axismundi_actors_normalize_local_endpoint_uri( 'https://evil.example/inbox' )
		&& '' === axismundi_actors_normalize_local_endpoint_uri( 'https://evil.example/?x=' . rawurlencode( home_url( '/' ) ) )
);

$ax_le_supplied = static fn( array $endpoints ) : array => array_merge( $endpoints, array( 'inbox' => home_url( '/wp-json/fixture/inbox' ) ) );
$ax_le_key      = static fn() : array => array(
	'id'           => $ax_le_actor->get_uri() . '#fixture-key',
	'owner'        => $ax_le_actor->get_uri(),
	'publicKeyPem' => "-----BEGIN PUBLIC KEY-----\nZml4dHVyZQ==\n-----END PUBLIC KEY-----",
);

// With both halves present the bundle is complete.
add_filter( 'axismundi_actors_local_endpoints', $ax_le_supplied, 99 );
add_filter( 'axismundi_actors_local_public_key', $ax_le_key, 99 );
$ax_le_members = axismundi_actors_transport_members( $ax_le_actor );
$ax_le_links   = axismundi_actors_webfinger_self_link( array(), $ax_le_actor );
remove_filter( 'axismundi_actors_local_public_key', $ax_le_key, 99 );
// Silence every key provider, including the transport's real one, to read the site as it
// looks before a key exists.
add_filter( 'axismundi_actors_local_public_key', '__return_null', 999 );
$ax_le_keyless_members = axismundi_actors_transport_members( $ax_le_actor );
$ax_le_keyless_links   = axismundi_actors_webfinger_self_link( array(), $ax_le_actor );
remove_filter( 'axismundi_actors_local_public_key', '__return_null', 999 );
remove_filter( 'axismundi_actors_local_endpoints', $ax_le_supplied, 99 );

ax_le_assert(
	$ax_le_results,
	'a transport-supplied Inbox and key are advertised together with the key descriptor',
	home_url( '/wp-json/fixture/inbox' ) === ( $ax_le_members['inbox'] ?? '' )
		&& $ax_le_actor->get_uri() === ( $ax_le_members['publicKey']['owner'] ?? '' )
);
ax_le_assert(
	$ax_le_results,
	'WebFinger advertises the ActivityStreams Actor document once it is federatable',
	array( array( 'rel' => 'self', 'type' => 'application/activity+json', 'href' => $ax_le_actor->get_uri() ) ) === $ax_le_links
);

// Without a key nothing goes out: a remote server that caches a keyless Actor rejects our
// signed traffic until its copy goes stale.
ax_le_assert( $ax_le_results, 'a keyless Actor advertises no transport members at all', array() === $ax_le_keyless_members );
ax_le_assert( $ax_le_results, 'a keyless Actor is withheld from WebFinger too', array() === $ax_le_keyless_links );

// The registry answers the same question for a remote Actor from its stored observation,
// which is why the local answer belongs beside it rather than in a transport plugin.
ax_le_assert(
	$ax_le_results,
	'the same registry answers endpoints for remote Actors from what it observed',
	function_exists( 'axismundi_actors_get_endpoint' ) && in_array( 'inbox', axismundi_actors_endpoint_types(), true )
);

printf( "\n%d/%d passed\n", count( array_filter( $ax_le_results ) ), count( $ax_le_results ) );
