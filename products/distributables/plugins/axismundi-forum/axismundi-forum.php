<?php
/**
 * Plugin Name:       Axismundi Forum
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-forum
 * Description:       Federated community support for Axismundi managed Group Actors, with Topic Article projection and membership policy.
 * Version:           0.9.22
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-forum
 *
 * @package AxismundiForum
 *
 * A managed Group Actor is the community. Forum owns only discussion settings and
 * projections keyed to that immutable Group identity.
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_FORUM_VERSION = '0.9.22';

require_once __DIR__ . '/includes/repository.php';
require_once __DIR__ . '/includes/group-archive.php';
require_once __DIR__ . '/includes/topics.php';
require_once __DIR__ . '/includes/templates.php';
require_once __DIR__ . '/includes/community-card.php';
require_once __DIR__ . '/includes/thread-context.php';
require_once __DIR__ . '/includes/memberships.php';
require_once __DIR__ . '/includes/moderators.php';
require_once __DIR__ . '/includes/inbound-topics.php';
require_once __DIR__ . '/includes/outbound-topics.php';
require_once __DIR__ . '/includes/distribution.php';
require_once __DIR__ . '/includes/votes.php';
require_once __DIR__ . '/includes/vote-block.php';
if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin.php';
}

/**
 * Activation installs Forum's Group-keyed projection schema.
 *
 * @return void
 */
function axismundi_forum_activate() : void {
	axismundi_forum_install();
	axismundi_forum_register_topic_post_type();
	flush_rewrite_rules( false );
}
register_activation_hook( __FILE__, 'axismundi_forum_activate' );

/** @return void */
function axismundi_forum_deactivate() : void {
	flush_rewrite_rules( false );
}
register_deactivation_hook( __FILE__, 'axismundi_forum_deactivate' );

/**
 * Run dbDelta when the stored schema version lags the code (upgrades without a
 * reactivation), mirroring the Actors upgrade seam.
 *
 * @return void
 */
function axismundi_forum_maybe_upgrade() : void {
	if ( (string) get_option( 'ax_forum_db_version', '' ) !== AXISMUNDI_FORUM_DB_VERSION ) {
		axismundi_forum_install();
	}
}
add_action( 'plugins_loaded', 'axismundi_forum_maybe_upgrade' );

/**
 * Forum leans on the Axismundi Actors authority kernel for every community decision.
 *
 * @return void
 */
function axismundi_forum_requires_actors_notice() : void {
	if ( function_exists( 'axismundi_actors_managed_actor_can_manage' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Axismundi Forum requires the Axismundi Actors plugin to be active. Community controls are disabled until Actors is available.', 'axismundi-forum' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'axismundi_forum_requires_actors_notice' );
