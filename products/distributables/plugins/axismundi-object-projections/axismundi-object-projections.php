<?php
/**
 * Plugin Name:       Axismundi Object Projections
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-object-projections
 * Description:       Projects WordPress objects, Actors, and collections into ActivityStreams JSON-LD through a transformer registry and a single renderer. It owns representation and public read routes, not Activity state, Inbox writes, signatures, or delivery.
 * Version:           0.0.33
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-object-projections
 *
 * @package AxismundiObjectProjections
 *
 * Local object projection plus the URI-keyed remote observation repository are
 * implemented. Public representation routes may be exposed for collections, but there is
 * deliberately no Activity store, Inbox write handling, signature handling, or delivery.
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_OP_VERSION = '0.0.33';

require_once __DIR__ . '/includes/object-relations.php';
require_once __DIR__ . '/includes/remote-objects.php';
require_once __DIR__ . '/includes/leases.php';
require_once __DIR__ . '/includes/remote-fetch.php';
require_once __DIR__ . '/includes/inbox-observations.php';
require_once __DIR__ . '/includes/remote-collections.php';
require_once __DIR__ . '/includes/registry.php';
require_once __DIR__ . '/includes/sanitize.php';
require_once __DIR__ . '/includes/renderer.php';
require_once __DIR__ . '/includes/object-view-model.php';
require_once __DIR__ . '/includes/post-settings.php';
require_once __DIR__ . '/includes/post-article.php';
require_once __DIR__ . '/includes/quote-authorizations.php';
require_once __DIR__ . '/includes/lifecycle.php';
require_once __DIR__ . '/includes/integrations/actors.php';
require_once __DIR__ . '/includes/integrations/media-library.php';
require_once __DIR__ . '/includes/integrations/media-folders.php';
require_once __DIR__ . '/includes/integrations/reactions.php';
require_once __DIR__ . '/includes/router.php';
if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin.php';
}

/** Install the remote-object observation and lease schema. */
function axismundi_op_activate() : void {
	axismundi_op_install();
	if ( ! wp_next_scheduled( 'axismundi_op_remote_objects_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'axismundi_op_remote_objects_daily' );
	}
}
register_activation_hook( __FILE__, 'axismundi_op_activate' );

/** Ensure upgrades from pre-cache releases receive the maintenance event. */
function axismundi_op_ensure_maintenance_schedule() : void {
	if ( ! wp_next_scheduled( 'axismundi_op_remote_objects_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'axismundi_op_remote_objects_daily' );
	}
}
add_action( 'admin_init', 'axismundi_op_ensure_maintenance_schedule' );

/** Remove only this plugin's scheduled maintenance event. */
function axismundi_op_deactivate() : void {
	wp_clear_scheduled_hook( 'axismundi_op_remote_objects_daily' );
	wp_clear_scheduled_hook( 'axismundi_op_discover_remote_actor' );
}
register_deactivation_hook( __FILE__, 'axismundi_op_deactivate' );
