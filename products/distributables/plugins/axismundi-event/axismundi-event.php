<?php
/**
 * Plugin Name:       Axismundi Event
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi
 * Description:       Events as first-class ActivityStreams Objects, federated as FEP-8a8e.
 * Version:           0.0.1
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Ji-woon Kim
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-event
 *
 * An Event is authored here and projected through Object Projections like any other Object, so it
 * gets the thread graph, interactions, the listing index and the canonical document route without
 * any of them learning what an event is.
 *
 * Deliberately not a bridge. Reading another event plugin's storage would make that plugin's model
 * this one's permanent contract -- GatherPress keeps RSVPs in `wp_comments`, which is a different
 * ledger than the Activity ledger participation belongs in. `event-bridge-for-activitypub` already
 * adapts eight event plugins for people who want that; this owns its own Objects instead, and meets
 * the same wire format so both talk to the same peers.
 *
 * @package AxismundiEvent
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_EVENT_VERSION   = '0.0.1';
const AXISMUNDI_EVENT_POST_TYPE = 'ax_event';

require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/envelope.php';
require_once __DIR__ . '/includes/rest.php';
require_once __DIR__ . '/includes/publish-guard.php';
require_once __DIR__ . '/includes/editor.php';
require_once __DIR__ . '/includes/projection.php';

/**
 * Install the event envelope schema.
 *
 * @return void
 */
function axismundi_event_activate() : void {
	axismundi_event_install_schema();
}
register_activation_hook( __FILE__, 'axismundi_event_activate' );

/**
 * Keep the schema current for a plugin updated in place rather than reactivated.
 *
 * @return void
 */
function axismundi_event_maybe_upgrade() : void {
	if ( AXISMUNDI_EVENT_DB_VERSION !== (string) get_option( AXISMUNDI_EVENT_DB_VERSION_OPTION, '' ) ) {
		axismundi_event_install_schema();
	}
}
add_action( 'init', 'axismundi_event_maybe_upgrade', 5 );
