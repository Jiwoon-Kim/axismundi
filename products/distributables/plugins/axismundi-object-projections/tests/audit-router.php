<?php
/**
 * Phase 2 — content-negotiation parser and ownership regression (dev-only).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/registry.php';
require_once dirname( __DIR__ ) . '/includes/renderer.php';
require_once dirname( __DIR__ ) . '/includes/router.php';

$ax_router_results = array();

/** @param array<bool> $results Results. @param string $label Label. @param bool $condition Condition. */
function ax_router_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

ax_router_assert( $ax_router_results, 'application/activity+json negotiates', axismundi_op_accepts_activitystreams( 'application/activity+json' ) );
ax_router_assert( $ax_router_results, 'ActivityStreams-profiled application/ld+json negotiates', axismundi_op_accepts_activitystreams( 'text/html, application/ld+json; profile="https://www.w3.org/ns/activitystreams"' ) );
ax_router_assert( $ax_router_results, 'bare application/json and unprofiled ld+json do not negotiate', ! axismundi_op_accepts_activitystreams( 'application/json, application/ld+json' ) );
ax_router_assert( $ax_router_results, 'q=0 explicitly refuses the ActivityStreams representation', ! axismundi_op_accepts_activitystreams( 'application/activity+json; q=0' ) );
ax_router_assert( $ax_router_results, 'an allowed later range wins after a refused range', axismundi_op_accepts_activitystreams( 'application/activity+json; q=0, application/ld+json; profile="https://www.w3.org/ns/activitystreams"; q=0.8' ) );

$_GET['activitypub'] = '';
ax_router_assert( $ax_router_results, 'an explicit ?activitypub selector requests the representation without changing identity', axismundi_op_explicit_activitypub_requested() );
unset( $_GET['activitypub'] );
ax_router_assert( $ax_router_results, 'the explicit selector is false when the parameter is absent', ! axismundi_op_explicit_activitypub_requested() );

add_filter( 'axismundi_op_standalone_router_enabled', '__return_false' );
ax_router_assert( $ax_router_results, 'the ownership filter can disable standalone negotiation', ! axismundi_op_standalone_router_enabled() );
remove_filter( 'axismundi_op_standalone_router_enabled', '__return_false' );

$merged = axismundi_op_merge_header_token( 'Origin, Accept', 'Accept' );
ax_router_assert( $ax_router_results, 'Vary token merge is case-insensitive and does not duplicate Accept', 1 === substr_count( strtolower( $merged ), 'accept' ) );

$ax_router_failures = count( array_filter( $ax_router_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_router_results ), $ax_router_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_router_failures > 0 ? 1 : 0 );
}
exit( $ax_router_failures > 0 ? 1 : 0 );
