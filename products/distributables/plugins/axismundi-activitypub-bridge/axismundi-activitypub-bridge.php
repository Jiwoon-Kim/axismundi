<?php
/**
 * Plugin Name:       Axismundi ActivityPub Bridge
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-activitypub-bridge
 * Description:       Compatibility boundary between Axismundi's URI-keyed domain stores and the official ActivityPub plugin's S2S transport.
 * Version:           0.0.3
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Requires Plugins:  activitypub, axismundi-actors, axismundi-object-projections, axismundi-activities
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-activitypub-bridge
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTIVITYPUB_BRIDGE_VERSION = '0.0.3';
const AXISMUNDI_ACTIVITYPUB_BRIDGE_REWRITE_VERSION = 1;

/** Rebuild rewrite rules after dormant ownership callbacks have been applied. */
function axismundi_activitypub_bridge_maybe_refresh_rewrites() : void {
	if ( ! axismundi_activitypub_bridge_ready()
		|| AXISMUNDI_ACTIVITYPUB_BRIDGE_REWRITE_VERSION === (int) get_option( 'ax_activitypub_bridge_rewrite_version', 0 )
	) {
		return;
	}

	flush_rewrite_rules( false );
	update_option( 'ax_activitypub_bridge_rewrite_version', AXISMUNDI_ACTIVITYPUB_BRIDGE_REWRITE_VERSION, false );
}
add_action( 'init', 'axismundi_activitypub_bridge_maybe_refresh_rewrites', 99 );

/** Let the official plugin rebuild its routes on the request after deactivation. */
function axismundi_activitypub_bridge_deactivate() : void {
	delete_option( 'ax_activitypub_bridge_rewrite_version' );
	delete_option( 'rewrite_rules' );
}
register_deactivation_hook( __FILE__, 'axismundi_activitypub_bridge_deactivate' );

/** Whether every required runtime surface is available. */
function axismundi_activitypub_bridge_ready() : bool {
	return defined( 'ACTIVITYPUB_PLUGIN_VERSION' )
		&& defined( 'AXISMUNDI_ACTORS_VERSION' )
		&& defined( 'AXISMUNDI_OP_VERSION' )
		&& defined( 'AXISMUNDI_ACTIVITIES_VERSION' )
		&& function_exists( 'axismundi_actors_get_by_uri' )
		&& function_exists( 'axismundi_op_transform_object' )
		&& function_exists( 'axismundi_act_record_activity' );
}

/**
 * Keep automatic post lifecycle publication in the Axismundi Activity ledger.
 */
function axismundi_activitypub_bridge_lifecycle_owner( string $owner ) : string {
	return axismundi_activitypub_bridge_ready() ? 'axismundi' : $owner;
}
add_filter( 'axismundi_op_post_lifecycle_owner', 'axismundi_activitypub_bridge_lifecycle_owner', 100 );

/** Let Object Projections own canonical-URL content negotiation. */
function axismundi_activitypub_bridge_projection_router( bool $enabled ) : bool {
	return axismundi_activitypub_bridge_ready() ? true : $enabled;
}
add_filter( 'axismundi_op_standalone_router_enabled', 'axismundi_activitypub_bridge_projection_router', 100 );

/**
 * Select the minimum official modules retained by dormant transport mode.
 *
 * @param bool   $enabled Official default.
 * @param string $module Stable upstream module identifier.
 */
function axismundi_activitypub_bridge_official_module_enabled( bool $enabled, string $module ) : bool {
	if ( ! axismundi_activitypub_bridge_ready() ) {
		return $enabled;
	}

	return in_array(
		$module,
		array( 'runtime.signature', 'rest.server', 'rest.inbox', 'rest.actors_inbox' ),
		true
	);
}
add_filter( 'activitypub_module_enabled', 'axismundi_activitypub_bridge_official_module_enabled', 100, 2 );

/**
 * Put overlapping official modules into a dormant state before `init` runs.
 *
 * Signature and REST server classes remain available for the future verified
 * Inbox handoff. No official database rows or scheduled events are deleted.
 */
function axismundi_activitypub_bridge_disable_conflicting_modules() : void {
	if ( ! axismundi_activitypub_bridge_ready() ) {
		return;
	}
	if ( function_exists( 'Activitypub\\is_module_enabled' ) ) {
		return;
	}

	remove_action( 'init', array( 'Activitypub\\Router', 'init' ) );
	remove_action( 'init', array( 'Activitypub\\Scheduler', 'init' ), 0 );
	remove_action( 'init', array( 'Activitypub\\Handler', 'init' ) );
	remove_action( 'init', array( 'Activitypub\\Dispatcher', 'init' ) );
}
add_action( 'plugins_loaded', 'axismundi_activitypub_bridge_disable_conflicting_modules', 50 );

/** Whether one request targets an official Inbox write route. */
function axismundi_activitypub_bridge_is_inbox_request( WP_REST_Request $request ) : bool {
	if ( WP_REST_Server::CREATABLE !== $request->get_method() ) {
		return false;
	}

	$namespace = defined( 'ACTIVITYPUB_REST_NAMESPACE' ) ? ACTIVITYPUB_REST_NAMESPACE : 'activitypub/1.0';
	$route     = trim( $request->get_route(), '/' );
	$pattern   = '#^' . preg_quote( trim( $namespace, '/' ), '#' ) . '/(?:(?:users|actors)/-?\\d+/)?inbox/?$#';

	return 1 === preg_match( $pattern, $route );
}

/**
 * Fail closed until upstream can hand a verified request to the bridge.
 *
 * Dormant mode runs before signature lookup and validation. This avoids even a
 * temporary write to the official remote-Actor cache. Verified processing will
 * replace this guard only after upstream exposes a supported handoff.
 *
 * @param mixed           $response Existing REST response.
 * @param WP_REST_Server  $server   REST server instance.
 * @param WP_REST_Request $request  Current request.
 * @return mixed
 */
function axismundi_activitypub_bridge_block_unclaimed_inbox( $response, WP_REST_Server $server, WP_REST_Request $request ) {
	unset( $server );
	if ( is_wp_error( $response ) || ! axismundi_activitypub_bridge_ready() || ! axismundi_activitypub_bridge_is_inbox_request( $request ) ) {
		return $response;
	}

	return new WP_Error(
		'axismundi_activitypub_bridge_inbox_dormant',
		__( 'Federated Inbox processing is temporarily unavailable.', 'axismundi-activitypub-bridge' ),
		array( 'status' => 503 )
	);
}
add_filter( 'rest_pre_dispatch', 'axismundi_activitypub_bridge_block_unclaimed_inbox', 1, 3 );

/** Announce a ready, behavior-free compatibility boundary to future adapters. */
function axismundi_activitypub_bridge_boot() : void {
	if ( axismundi_activitypub_bridge_ready() ) {
		/** @param string $version Bridge version. */
		do_action( 'axismundi_activitypub_bridge_ready', AXISMUNDI_ACTIVITYPUB_BRIDGE_VERSION );
	}
}
add_action( 'plugins_loaded', 'axismundi_activitypub_bridge_boot', 100 );
