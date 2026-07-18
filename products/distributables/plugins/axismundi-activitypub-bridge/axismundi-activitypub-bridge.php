<?php
/**
 * Plugin Name:       Axismundi ActivityPub Bridge
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-activitypub-bridge
 * Description:       Compatibility boundary between Axismundi's URI-keyed domain stores and the official ActivityPub plugin's S2S transport.
 * Version:           0.0.18
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

const AXISMUNDI_ACTIVITYPUB_BRIDGE_VERSION = '0.0.18';
const AXISMUNDI_ACTIVITYPUB_BRIDGE_REWRITE_VERSION = 1;

require_once __DIR__ . '/includes/transport.php';
require_once __DIR__ . '/includes/delivery.php';
require_once __DIR__ . '/includes/composition.php';
require_once __DIR__ . '/includes/migration-scan.php';
require_once __DIR__ . '/includes/migration-import.php';
if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin.php';
}

register_activation_hook( __FILE__, 'axismundi_activitypub_bridge_install_delivery_table' );

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
function axismundi_activitypub_bridge_inbox_targets( array $activity, array $recipient_user_ids, bool $allow_mention_targets = false ) : array {
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
	if ( $allow_mention_targets ) {
		$tags = $object['tag'] ?? array();
		$tags = is_array( $tags ) && array_is_list( $tags ) ? $tags : array( $tags );
		foreach ( $tags as $tag ) {
			if ( is_array( $tag ) && 'Mention' === (string) ( $tag['type'] ?? '' ) ) {
				$candidates = array_merge( $candidates, axismundi_activitypub_bridge_member_uris( $tag['href'] ?? $tag['id'] ?? '' ) );
			}
		}
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
 * Record one signature-verified Inbox Activity into Axismundi repositories.
 *
 * @param array           $activity Activity payload.
 * @param int[]           $recipient_user_ids Official route recipients, if known.
 * @param bool            $allow_mention_targets Trust Mention href only after the official shared Inbox has verified the request.
 * @return Axismundi_Activity|WP_Error
 */
function axismundi_activitypub_bridge_record_inbox( array $activity, array $recipient_user_ids = array(), bool $allow_mention_targets = false ) {
	if ( ! axismundi_activitypub_bridge_ready() ) {
		return new WP_Error( 'ax_bridge_inbox_not_ready', __( 'The Axismundi Inbox repositories are unavailable.', 'axismundi-activitypub-bridge' ) );
	}
	if ( empty( axismundi_activitypub_bridge_inbox_targets( $activity, $recipient_user_ids, $allow_mention_targets ) ) ) {
		return new WP_Error( 'ax_bridge_inbox_no_target', __( 'No public local Actor target could be resolved.', 'axismundi-activitypub-bridge' ) );
	}

	$actor_uri = function_exists( 'axismundi_act_member_uri' ) ? axismundi_act_member_uri( $activity['actor'] ?? '' ) : '';
	$actor     = '' === $actor_uri ? null : axismundi_actors_get_by_uri( $actor_uri );
	if ( ! $actor instanceof Axismundi_Actor && '' !== $actor_uri && function_exists( 'axismundi_actors_discover_remote_actor_uri' ) ) {
		$actor = axismundi_actors_discover_remote_actor_uri( $actor_uri );
	}
	if ( ! $actor instanceof Axismundi_Actor || $actor->is_local() ) {
		return new WP_Error( 'ax_bridge_inbox_actor', __( 'The remote Actor could not be resolved.', 'axismundi-activitypub-bridge' ) );
	}
	if ( function_exists( 'axismundi_actors_ensure_remote_instance_cached' ) ) {
		axismundi_actors_ensure_remote_instance_cached( $actor );
	}

	$object      = is_array( $activity['object'] ?? null ) ? $activity['object'] : array();
	$object_type = (string) ( $object['type'] ?? '' );
	if ( 'Update' === (string) ( $activity['type'] ?? '' )
		&& in_array( $object_type, array( 'Person', 'Organization', 'Application', 'Service', 'Group' ), true )
	) {
		if ( ! function_exists( 'axismundi_actors_apply_remote_actor_update' ) ) {
			return new WP_Error( 'ax_bridge_actor_update_unavailable', __( 'The remote Actor update repository is unavailable.', 'axismundi-activitypub-bridge' ) );
		}
		$updated = axismundi_actors_apply_remote_actor_update( $object, $actor_uri );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
	}

	return axismundi_act_record_activity( $activity, 'inbound' );
}

/** Store one bounded, content-free Inbox outcome for administrator diagnostics. */
function axismundi_activitypub_bridge_record_inbox_diagnostic( string $route, array $activity, $result ) : void {
	$activity_uri = function_exists( 'axismundi_act_member_uri' ) ? axismundi_act_member_uri( $activity['id'] ?? '' ) : '';
	$outcome      = $result instanceof Axismundi_Activity ? 'recorded' : ( is_wp_error( $result ) ? 'unclaimed' : 'unknown' );
	$entries      = get_option( 'ax_activitypub_bridge_inbox_diagnostics', array() );
	$entries      = is_array( $entries ) ? $entries : array();
	array_unshift(
		$entries,
		array(
			'time'             => current_time( 'mysql', true ),
			'route'            => sanitize_key( $route ),
			'activity_type'    => sanitize_key( (string) ( $activity['type'] ?? '' ) ),
			'activity_id_hash' => '' !== $activity_uri ? hash( 'sha256', $activity_uri ) : hash( 'sha256', (string) wp_json_encode( $activity ) ),
			'outcome'          => $outcome,
			'code'             => is_wp_error( $result ) ? sanitize_key( $result->get_error_code() ) : '',
		)
	);
	update_option( 'ax_activitypub_bridge_inbox_diagnostics', array_slice( $entries, 0, 50 ), false );
}

/** Read the bounded Inbox diagnostic ring buffer. */
function axismundi_activitypub_bridge_inbox_diagnostics() : array {
	$entries = get_option( 'ax_activitypub_bridge_inbox_diagnostics', array() );
	return is_array( $entries ) ? $entries : array();
}

/** Build a request-local key for one Inbox Activity. */
function axismundi_activitypub_bridge_inbox_claim_key( array $activity ) : string {
	$activity_uri = function_exists( 'axismundi_act_member_uri' ) ? axismundi_act_member_uri( $activity['id'] ?? '' ) : '';
	return '' !== $activity_uri ? hash( 'sha256', $activity_uri ) : hash( 'sha256', (string) wp_json_encode( $activity ) );
}

/** Mark or inspect successful Axismundi ownership during the current request. */
function axismundi_activitypub_bridge_inbox_claimed( array $activity, bool $claim = false ) : bool {
	static $claimed = array();
	$key = axismundi_activitypub_bridge_inbox_claim_key( $activity );
	if ( $claim ) {
		$claimed[ $key ] = true;
	}
	return isset( $claimed[ $key ] );
}

/**
 * Consume the per-Actor Inbox action after the official permission callback.
 *
 * The shared controller also fires this deprecated per-recipient action. Ignore
 * that context because `activitypub_inbox_shared` records the Activity once.
 *
 * @param array  $activity Activity payload.
 * @param int    $user_id Recipient WordPress user id.
 * @param string $type Activity type.
 * @param mixed  $object Parsed official Activity object.
 * @param string $context Inbox context.
 */
function axismundi_activitypub_bridge_actor_inbox( array $activity, $user_id, string $type, $object, string $context ) : void {
	unset( $type, $object );
	if ( 'inbox' === $context ) {
		$recorded = axismundi_activitypub_bridge_record_inbox( $activity, array( (int) $user_id ) );
		axismundi_activitypub_bridge_record_inbox_diagnostic( 'actor', $activity, $recorded );
		if ( $recorded instanceof Axismundi_Activity ) {
			axismundi_activitypub_bridge_inbox_claimed( $activity, true );
		}
	}
}
add_action( 'activitypub_inbox', 'axismundi_activitypub_bridge_actor_inbox', 10, 5 );

/** Consume the shared Inbox action once for all resolved recipients. */
function axismundi_activitypub_bridge_shared_inbox( array $activity, array $user_ids, string $type, $object, string $context ) : void {
	unset( $type, $object, $context );
	$recorded = axismundi_activitypub_bridge_record_inbox( $activity, array_map( 'intval', $user_ids ), true );
	axismundi_activitypub_bridge_record_inbox_diagnostic( 'shared', $activity, $recorded );
	if ( $recorded instanceof Axismundi_Activity ) {
		axismundi_activitypub_bridge_inbox_claimed( $activity, true );
	}
}
add_action( 'activitypub_inbox_shared', 'axismundi_activitypub_bridge_shared_inbox', 10, 5 );

/** Keep the official Inbox CPT dormant while Axismundi owns Inbox state. */
function axismundi_activitypub_bridge_skip_inbox_storage( bool $skip, array $activity ) : bool {
	return axismundi_activitypub_bridge_ready() && axismundi_activitypub_bridge_inbox_claimed( $activity ) ? true : $skip;
}
add_filter( 'activitypub_skip_inbox_storage', 'axismundi_activitypub_bridge_skip_inbox_storage', 100, 2 );

/** Announce a ready, behavior-free compatibility boundary to future adapters. */
function axismundi_activitypub_bridge_boot() : void {
	if ( axismundi_activitypub_bridge_ready() ) {
		/** @param string $version Bridge version. */
		do_action( 'axismundi_activitypub_bridge_ready', AXISMUNDI_ACTIVITYPUB_BRIDGE_VERSION );
	}
}
add_action( 'plugins_loaded', 'axismundi_activitypub_bridge_boot', 100 );
