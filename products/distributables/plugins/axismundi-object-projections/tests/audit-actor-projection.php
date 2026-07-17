<?php
/**
 * Actor projection ownership regression (dev-only; dist-excluded).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_actor_projection_results = array();

/** @param bool[] $results Results. */
function ax_actor_projection_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

$actor = axismundi_actors_get_site_actor();
add_filter(
	'axismundi_op_actor_transport_fields',
	static fn() : array => array(
		'id'        => 'https://evil.example/actor',
		'inbox'     => 'https://transport.example/inbox',
		'outbox'    => 'https://transport.example/outbox',
		'followers' => 'https://transport.example/followers',
		'following' => 'https://transport.example/following',
		'featured'  => 'https://transport.example/featured',
		'endpoints' => array( 'sharedInbox' => 'https://transport.example/shared' ),
		'publicKey' => array( 'id' => 'https://transport.example/key', 'owner' => 'https://transport.example/actor', 'publicKeyPem' => 'fixture' ),
	)
);
$raw       = $actor instanceof Axismundi_Actor ? axismundi_op_actor_transform( $actor ) : array();
$finalized = $actor instanceof Axismundi_Actor ? axismundi_op_finalize_object( $raw, $actor->get_uri() ) : null;
remove_all_filters( 'axismundi_op_actor_transport_fields' );

ax_actor_projection_assert( $ax_actor_projection_results, 'the Actors plugin supplies a site Actor source', $actor instanceof Axismundi_Actor );
ax_actor_projection_assert( $ax_actor_projection_results, 'Actor projection owns immutable identity and ignores a transport id override', is_array( $raw ) && $actor->get_uri() === $raw['id'] && $actor->get_type() === $raw['type'] );
ax_actor_projection_assert( $ax_actor_projection_results, 'Actor finalization does not require attributedTo', is_array( $finalized ) && ! isset( $finalized['attributedTo'] ) );
ax_actor_projection_assert( $ax_actor_projection_results, 'transport fields may supply inbox, sharedInbox, and publicKey but cannot override the representation-owned outbox', is_array( $finalized ) && isset( $finalized['inbox'], $finalized['outbox'], $finalized['endpoints']['sharedInbox'], $finalized['publicKey'] ) && axismundi_op_actor_outbox_url( $actor ) === $finalized['outbox'] );
ax_actor_projection_assert( $ax_actor_projection_results, 'transport fields cannot override followers or inject other representation-owned collections', is_array( $finalized ) && axismundi_op_actor_followers_url( $actor ) === $finalized['followers'] && ! isset( $finalized['following'], $finalized['featured'] ) );

$failures = count( array_filter( $ax_actor_projection_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_actor_projection_results ), $failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failures > 0 ? 1 : 0 );
}
exit( $failures > 0 ? 1 : 0 );
