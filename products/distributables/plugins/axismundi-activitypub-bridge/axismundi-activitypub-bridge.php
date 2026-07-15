<?php
/**
 * Plugin Name:       Axismundi ActivityPub Bridge
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-activitypub-bridge
 * Description:       Compatibility boundary between Axismundi's URI-keyed domain stores and the official ActivityPub plugin's S2S transport.
 * Version:           0.0.1
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

const AXISMUNDI_ACTIVITYPUB_BRIDGE_VERSION = '0.0.1';

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
 * Keep automatic post lifecycle publication with the official plugin.
 *
 * A later transport adapter may transfer ownership only after the corresponding
 * official scheduler path can be suppressed through a supported upstream API.
 */
function axismundi_activitypub_bridge_lifecycle_owner( string $owner ) : string {
	return axismundi_activitypub_bridge_ready() ? 'official-activitypub' : $owner;
}
add_filter( 'axismundi_op_post_lifecycle_owner', 'axismundi_activitypub_bridge_lifecycle_owner', 100 );

/** Announce a ready, behavior-free compatibility boundary to future adapters. */
function axismundi_activitypub_bridge_boot() : void {
	if ( axismundi_activitypub_bridge_ready() ) {
		/** @param string $version Bridge version. */
		do_action( 'axismundi_activitypub_bridge_ready', AXISMUNDI_ACTIVITYPUB_BRIDGE_VERSION );
	}
}
add_action( 'plugins_loaded', 'axismundi_activitypub_bridge_boot', 100 );

