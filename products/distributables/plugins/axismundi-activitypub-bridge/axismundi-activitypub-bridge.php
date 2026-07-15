<?php
/**
 * Plugin Name:       Axismundi ActivityPub Bridge
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-activitypub-bridge
 * Description:       Compatibility boundary between Axismundi's URI-keyed domain stores and the official ActivityPub plugin's S2S transport.
 * Version:           0.0.8
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

const AXISMUNDI_ACTIVITYPUB_BRIDGE_VERSION = '0.0.8';
const AXISMUNDI_ACTIVITYPUB_BRIDGE_REWRITE_VERSION = 1;

require_once __DIR__ . '/includes/transport.php';
require_once __DIR__ . '/includes/migration-scan.php';
require_once __DIR__ . '/includes/migration-import.php';
if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin.php';
}

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
		array( 'runtime.signature', 'runtime.external_delivery', 'rest.server', 'rest.inbox', 'rest.actors_inbox' ),
		true
	);
}
add_filter( 'activitypub_module_enabled', 'axismundi_activitypub_bridge_official_module_enabled', 100, 2 );

/**
 * Put overlapping official modules into a dormant state before `init` runs.
 *
 * Signature and REST server classes remain active for verified Inbox handoff.
 * No official database rows or scheduled events are deleted.
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

/** Collect URI members from one ActivityStreams scalar, object, or list. */
function axismundi_activitypub_bridge_member_uris( $value ) : array {
	if ( is_scalar( $value ) ) {
		$uri = function_exists( 'axismundi_act_uri' ) ? axismundi_act_uri( $value ) : '';
		return '' === $uri ? array() : array( $uri );
	}
	if ( ! is_array( $value ) ) {
		return array();
	}
	if ( ! array_is_list( $value ) ) {
		return axismundi_activitypub_bridge_member_uris( $value['id'] ?? $value['href'] ?? '' );
	}
	$uris = array();
	foreach ( $value as $member ) {
		$uris = array_merge( $uris, axismundi_activitypub_bridge_member_uris( $member ) );
	}
	return array_values( array_unique( $uris ) );
}

/** Resolve a public local Actor, including one of its conventional collection URIs. */
function axismundi_activitypub_bridge_local_actor( string $uri ) : ?Axismundi_Actor {
	$candidates = array( $uri );
	foreach ( array( '/followers', '/following', '/inbox', '/outbox' ) as $suffix ) {
		if ( str_ends_with( rtrim( $uri, '/' ), $suffix ) ) {
			$candidates[] = substr( rtrim( $uri, '/' ), 0, -strlen( $suffix ) );
		}
	}
	foreach ( array_unique( $candidates ) as $candidate ) {
		$actor = axismundi_actors_get_by_uri( $candidate );
		if ( $actor instanceof Axismundi_Actor && $actor->is_local() && 'public' === $actor->get_status() ) {
			return $actor;
		}
	}
	return null;
}

/** Resolve the owning local Actor when a URI identifies a projected WP object. */
function axismundi_activitypub_bridge_object_actor( string $uri ) : ?Axismundi_Actor {
	$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
	$uri_host  = strtolower( (string) wp_parse_url( $uri, PHP_URL_HOST ) );
	if ( '' === $home_host || $home_host !== $uri_host ) {
		return null;
	}
	parse_str( (string) wp_parse_url( $uri, PHP_URL_QUERY ), $query );
	$post_id = (int) ( $query['p'] ?? $query['attachment_id'] ?? 0 );
	$post    = $post_id > 0 ? get_post( $post_id ) : null;
	if ( ! $post instanceof WP_Post ) {
		return null;
	}
	$object = axismundi_op_transform_object( $post );
	if ( is_wp_error( $object ) || ! hash_equals( (string) ( $object['id'] ?? '' ), $uri ) ) {
		return null;
	}
	foreach ( axismundi_activitypub_bridge_member_uris( $object['attributedTo'] ?? '' ) as $actor_uri ) {
		$actor = axismundi_activitypub_bridge_local_actor( $actor_uri );
		if ( $actor ) {
			return $actor;
		}
	}
	return null;
}

/** Resolve every local Actor targeted by one verified Activity. */
function axismundi_activitypub_bridge_inbox_targets( array $activity, array $recipient_user_ids ) : array {
	$targets = array();
	foreach ( $recipient_user_ids as $user_id ) {
		$actor = axismundi_actors_get_for_user( (int) $user_id );
		if ( $actor instanceof Axismundi_Actor && $actor->is_local() && 'public' === $actor->get_status() ) {
			$targets[ $actor->get_uri() ] = $actor;
		}
	}

	$candidates = array();
	foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience' ) as $field ) {
		$candidates = array_merge( $candidates, axismundi_activitypub_bridge_member_uris( $activity[ $field ] ?? array() ) );
	}
	$object = is_array( $activity['object'] ?? null ) ? $activity['object'] : array();
	foreach ( array( 'actor', 'attributedTo', 'to', 'cc', 'audience' ) as $field ) {
		$candidates = array_merge( $candidates, axismundi_activitypub_bridge_member_uris( $object[ $field ] ?? array() ) );
	}
	if ( in_array( (string) ( $activity['type'] ?? '' ), array( 'Follow', 'Block' ), true ) ) {
		$candidates = array_merge( $candidates, axismundi_activitypub_bridge_member_uris( $activity['object'] ?? '' ) );
	}
	foreach ( array_unique( $candidates ) as $candidate ) {
		$actor = axismundi_activitypub_bridge_local_actor( $candidate );
		if ( $actor ) {
			$targets[ $actor->get_uri() ] = $actor;
		}
	}

	$object_uri   = function_exists( 'axismundi_act_member_uri' ) ? axismundi_act_member_uri( $activity['object'] ?? '' ) : '';
	$object_actor = '' === $object_uri ? null : axismundi_activitypub_bridge_object_actor( $object_uri );
	if ( $object_actor ) {
		$targets[ $object_actor->get_uri() ] = $object_actor;
	}
	return array_values( $targets );
}

/**
 * Claim a signature-verified Inbox Activity into Axismundi repositories.
 *
 * @param mixed           $handled Existing claim result.
 * @param array           $activity Activity payload.
 * @param WP_REST_Request $request Verified request.
 * @param string          $context Inbox context.
 * @param int[]           $recipient_user_ids Official route recipients, if known.
 * @return true|WP_Error
 */
function axismundi_activitypub_bridge_handle_verified_inbox( $handled, array $activity, WP_REST_Request $request, string $context, array $recipient_user_ids ) {
	unset( $request, $context );
	if ( null !== $handled ) {
		return $handled;
	}
	if ( empty( axismundi_activitypub_bridge_inbox_targets( $activity, $recipient_user_ids ) ) ) {
		return new WP_Error( 'ax_bridge_inbox_target', __( 'The Activity has no public local recipient.', 'axismundi-activitypub-bridge' ), array( 'status' => 404 ) );
	}

	$actor_uri = function_exists( 'axismundi_act_member_uri' ) ? axismundi_act_member_uri( $activity['actor'] ?? '' ) : '';
	$actor     = '' === $actor_uri ? null : axismundi_actors_get_by_uri( $actor_uri );
	if ( ! $actor instanceof Axismundi_Actor && '' !== $actor_uri && function_exists( 'axismundi_actors_discover_remote_actor_uri' ) ) {
		$actor = axismundi_actors_discover_remote_actor_uri( $actor_uri );
	}
	if ( ! $actor instanceof Axismundi_Actor || $actor->is_local() ) {
		return new WP_Error( 'ax_bridge_inbox_actor', __( 'The remote Actor could not be resolved.', 'axismundi-activitypub-bridge' ), array( 'status' => 503 ) );
	}

	$recorded = axismundi_act_record_activity( $activity, 'inbound' );
	if ( is_wp_error( $recorded ) ) {
		$status = in_array( $recorded->get_error_code(), array( 'ax_act_schema', 'ax_act_write' ), true ) ? 503 : 422;
		return new WP_Error( 'ax_bridge_inbox_activity', $recorded->get_error_message(), array( 'status' => $status ) );
	}
	return true;
}
add_filter( 'activitypub_pre_handle_verified_inbox', 'axismundi_activitypub_bridge_handle_verified_inbox', 10, 5 );

/**
 * Fail closed on stock upstream versions without the verified handoff.
 *
 * This compatibility fallback runs before signature lookup and validation. The
 * patched upstream skips it and invokes the verified handoff from its controller.
 *
 * @param mixed           $response Existing REST response.
 * @param WP_REST_Server  $server   REST server instance.
 * @param WP_REST_Request $request  Current request.
 * @return mixed
 */
function axismundi_activitypub_bridge_block_unclaimed_inbox( $response, WP_REST_Server $server, WP_REST_Request $request ) {
	unset( $server );
	if ( function_exists( 'Activitypub\\handle_verified_inbox' )
		|| is_wp_error( $response )
		|| ! axismundi_activitypub_bridge_ready()
		|| ! axismundi_activitypub_bridge_is_inbox_request( $request )
	) {
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
