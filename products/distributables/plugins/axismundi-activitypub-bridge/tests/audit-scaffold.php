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

ax_bridge_assert( $ax_bridge_results, 'the scaffold leaves automatic post lifecycle ownership with the official plugin', 'official-activitypub' === axismundi_activitypub_bridge_lifecycle_owner( 'axismundi' ) );

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
