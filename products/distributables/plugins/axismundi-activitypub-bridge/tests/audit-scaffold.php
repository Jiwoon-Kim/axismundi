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

ax_bridge_assert( $ax_bridge_results, 'official protocol and domain initializers remain available', 0 === has_action( 'init', array( 'Activitypub\\Scheduler', 'init' ) ) && 10 === has_action( 'init', array( 'Activitypub\\Handler', 'init' ) ) && 10 === has_action( 'init', array( 'Activitypub\\Dispatcher', 'init' ) ) );
ax_bridge_assert( $ax_bridge_results, 'the overlapping official presentation initializer alone is dormant', false === has_action( 'init', array( 'Activitypub\\Router', 'init' ) ) );
ax_bridge_assert( $ax_bridge_results, 'the superseded fork delivery initializer alone is dormant', false === has_action( 'init', array( 'Activitypub\\External_Delivery', 'init' ) ) );
ax_bridge_assert( $ax_bridge_results, 'official presentation callbacks yield to Object Projections', false === has_action( 'init', array( 'Activitypub\\Router', 'add_rewrite_rules' ) ) && false === has_filter( 'template_include', array( 'Activitypub\\Router', 'render_activitypub_template' ) ) );
ax_bridge_assert( $ax_bridge_results, 'the official post lifecycle callback is unhooked after scheduler registration', false === has_action( 'wp_after_insert_post', array( 'Activitypub\\Scheduler\\Post', 'triage' ) ) );
ax_bridge_assert( $ax_bridge_results, 'official local Actor lifecycle callbacks are unhooked without disabling remote cache maintenance', false === has_action( 'profile_update', array( 'Activitypub\\Scheduler\\Actor', 'user_update' ) ) && 10 === has_action( 'activitypub_update_remote_actors', array( 'Activitypub\\Scheduler', 'update_remote_actors' ) ) );
ax_bridge_assert( $ax_bridge_results, 'the official Follow domain handler is unhooked after handler registration', false === has_action( 'activitypub_inbox_follow', array( 'Activitypub\\Handler\\Follow', 'handle_follow' ) ) );
ax_bridge_assert( $ax_bridge_results, 'the official Dispatcher remains available for its own Outbox rows', 10 === has_action( 'activitypub_process_outbox', array( 'Activitypub\\Dispatcher', 'process_outbox' ) ) );
ax_bridge_assert( $ax_bridge_results, 'the Bridge owns a distinct private delivery post type and worker hook', post_type_exists( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_POST_TYPE ) && 10 === has_action( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, 'axismundi_activitypub_bridge_process_delivery' ) );
ax_bridge_assert( $ax_bridge_results, 'the bridge consumes the existing per-Actor Inbox action', 10 === has_action( 'activitypub_inbox', 'axismundi_activitypub_bridge_actor_inbox' ) );
ax_bridge_assert( $ax_bridge_results, 'the bridge consumes the existing shared Inbox action', 10 === has_action( 'activitypub_inbox_shared', 'axismundi_activitypub_bridge_shared_inbox' ) );
$ax_bridge_claim_probe = array( 'id' => 'https://example.com/activities/' . wp_generate_uuid4() );
ax_bridge_assert( $ax_bridge_results, 'unclaimed Activities retain official Inbox fallback storage', false === apply_filters( 'activitypub_skip_inbox_storage', false, $ax_bridge_claim_probe ) );
axismundi_activitypub_bridge_inbox_claimed( $ax_bridge_claim_probe, true );
ax_bridge_assert( $ax_bridge_results, 'only successfully claimed Activities skip official Inbox CPT storage', true === apply_filters( 'activitypub_skip_inbox_storage', false, $ax_bridge_claim_probe ) );

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
