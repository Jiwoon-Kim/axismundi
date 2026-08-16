<?php
/**
 * Plugin Name:       Axismundi Notifications
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-notifications
 * Description:       One inbox for everything an Actor has to look at, projected from the Activity ledger by the domains that own each transition.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-notifications
 *
 * @package AxismundiNotifications
 *
 * A mention, a follow request, a reply and a calendar invitation are the same kind of
 * thing to whoever receives them. This is where they become one list -- addressed to an
 * Actor, projected from Activities the ledger already holds, and never a second record
 * of anything.
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_NTF_VERSION = '0.1.0';

require_once __DIR__ . '/includes/dependencies.php';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/registry.php';
require_once __DIR__ . '/includes/acceptance.php';
require_once __DIR__ . '/includes/preferences.php';
require_once __DIR__ . '/includes/events.php';
require_once __DIR__ . '/includes/deliveries.php';
require_once __DIR__ . '/includes/queue.php';
// The toolbar count is shown on the front end too, so this one is not admin-only.
require_once __DIR__ . '/includes/admin.php';

/**
 * Activation installs the events table.
 *
 * @return void
 */
function axismundi_ntf_activate() : void {
	axismundi_ntf_install_schema();
}
register_activation_hook( __FILE__, 'axismundi_ntf_activate' );

/**
 * Keep the schema current for a plugin updated in place.
 *
 * @return void
 */
function axismundi_ntf_maybe_upgrade() : void {
	if ( AXISMUNDI_NTF_DB_VERSION !== (string) get_option( AXISMUNDI_NTF_DB_VERSION_OPTION, '' ) ) {
		axismundi_ntf_install_schema();
	}
}
add_action( 'plugins_loaded', 'axismundi_ntf_maybe_upgrade', 20 );
