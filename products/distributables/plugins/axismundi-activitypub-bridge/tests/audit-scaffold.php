<?php
/**
 * Bridge scaffold regression (dev-only).
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_bridge_results = array();

/** @param bool[] $results Results. */
function ax_bridge_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

ax_bridge_assert( $ax_bridge_results, 'all four declared plugin dependencies expose their runtime surfaces', axismundi_activitypub_bridge_ready() );

ax_bridge_assert( $ax_bridge_results, 'the bridge keeps automatic post lifecycle ownership in Axismundi', 'axismundi' === axismundi_activitypub_bridge_lifecycle_owner( 'official-activitypub' ) );

ax_bridge_assert( $ax_bridge_results, 'Object Projections owns canonical URL negotiation', axismundi_activitypub_bridge_projection_router( false ) );

ax_bridge_assert( $ax_bridge_results, 'the official Router initializer is dormant', false === has_action( 'init', array( 'Activitypub\\Router', 'init' ) ) );
ax_bridge_assert( $ax_bridge_results, 'the official Scheduler initializer is dormant', false === has_action( 'init', array( 'Activitypub\\Scheduler', 'init' ) ) );
ax_bridge_assert( $ax_bridge_results, 'the official Handler initializer is dormant', false === has_action( 'init', array( 'Activitypub\\Handler', 'init' ) ) );
ax_bridge_assert( $ax_bridge_results, 'the official Dispatcher initializer is dormant', false === has_action( 'init', array( 'Activitypub\\Dispatcher', 'init' ) ) );
ax_bridge_assert( $ax_bridge_results, 'the official post lifecycle callback was never registered', false === has_action( 'wp_after_insert_post', array( 'Activitypub\\Scheduler\\Post', 'triage' ) ) );
ax_bridge_assert( $ax_bridge_results, 'the official Follow domain handler was never registered', false === has_action( 'activitypub_inbox_follow', array( 'Activitypub\\Handler\\Follow', 'handle_follow' ) ) );
ax_bridge_assert( $ax_bridge_results, 'the official outbox processor was never registered', false === has_action( 'activitypub_process_outbox', array( 'Activitypub\\Dispatcher', 'process_outbox' ) ) );

$ax_bridge_inbox = new WP_REST_Request( 'POST', '/activitypub/1.0/inbox' );
$ax_bridge_block = axismundi_activitypub_bridge_block_unclaimed_inbox( null, array(), $ax_bridge_inbox );
ax_bridge_assert( $ax_bridge_results, 'unclaimed shared Inbox writes fail closed with 503', is_wp_error( $ax_bridge_block ) && 503 === (int) $ax_bridge_block->get_error_data()['status'] );

$ax_bridge_actor_inbox = new WP_REST_Request( 'POST', '/activitypub/1.0/actors/1/inbox' );
ax_bridge_assert( $ax_bridge_results, 'unclaimed Actor Inbox writes are recognized', axismundi_activitypub_bridge_is_inbox_request( $ax_bridge_actor_inbox ) );

$ax_bridge_read = new WP_REST_Request( 'GET', '/activitypub/1.0/inbox' );
ax_bridge_assert( $ax_bridge_results, 'non-write requests pass through unchanged', null === axismundi_activitypub_bridge_block_unclaimed_inbox( null, array(), $ax_bridge_read ) );

$GLOBALS['ax_bridge_ready_seen'] = false;
add_action( 'axismundi_activitypub_bridge_ready', static function () : void { $GLOBALS['ax_bridge_ready_seen'] = true; } );
axismundi_activitypub_bridge_boot();
ax_bridge_assert( $ax_bridge_results, 'the ready event is observable without persistence or network behavior', true === $GLOBALS['ax_bridge_ready_seen'] );

$ax_bridge_failures = count( array_filter( $ax_bridge_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_bridge_results ), $ax_bridge_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_bridge_failures > 0 ? 1 : 0 );
}
exit( $ax_bridge_failures > 0 ? 1 : 0 );
