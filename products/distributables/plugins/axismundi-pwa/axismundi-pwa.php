<?php
/**
 * Plugin Name:       Axismundi PWA
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-pwa
 * Description:       Owns this site's installable-app surface: push subscriptions per device, the service worker handlers that receive them, and the capability other products ask before offering push.
 * Version:           0.1.1
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Requires Plugins:  pwa
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-pwa
 *
 * @package AxismundiPwa
 *
 * A site may have exactly one service worker, so somebody has to own it. That is this
 * plugin, and not whichever product happened to want push first -- if Notifications owned
 * the service worker, then offline behaviour, install prompts and app icons would all end
 * up hanging off the notification plugin, and turning notifications off would take the
 * installable app with them.
 *
 * The division with Axismundi Notifications:
 *
 *   here          how a device is reached: subscriptions, keys, handlers, capability
 *   Notifications whether and what to send: preferences, who is away, what a delivery is
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_PWA_VERSION = '0.1.1';

/*
 * The Web Push library, loaded once here rather than by whichever function happens to be called
 * first. Doing it lazily made the answer to "is the library present" depend on what had run before
 * it, which is the sort of ordering bug that passes every audit that happens to ask in the right
 * order.
 */
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/includes/capability.php';
require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/subscriptions.php';
require_once __DIR__ . '/includes/keys.php';
require_once __DIR__ . '/includes/sender.php';
require_once __DIR__ . '/includes/service-worker.php';
require_once __DIR__ . '/includes/rest.php';
// Not admin-only. It registers an action another plugin's screen fires and an `admin-post` handler,
// and gating it on `is_admin()` made both invisible to anything asking outside a browser request --
// including the audits, which is how this was noticed.
require_once __DIR__ . '/includes/admin-devices.php';

/** @return void */
function axismundi_pwa_activate() : void {
	axismundi_pwa_install_schema();
}
register_activation_hook( __FILE__, 'axismundi_pwa_activate' );

/** @return void */
function axismundi_pwa_maybe_upgrade() : void {
	if ( AXISMUNDI_PWA_DB_VERSION !== (string) get_option( AXISMUNDI_PWA_DB_VERSION_OPTION, '' ) ) {
		axismundi_pwa_install_schema();
	}
}
add_action( 'plugins_loaded', 'axismundi_pwa_maybe_upgrade', 20 );
