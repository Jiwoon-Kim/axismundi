<?php
/**
 * Plugin Name:       Axismundi Forum
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-forum
 * Description:       Federated community surface for Axismundi. A Forum is an ax_forum CPT bound one-to-one to a managed Group Actor administered by Axismundi Actors, with local Topic Article projection and contextual Forum entries.
 * Version:           0.5.0
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
 * F1 baseline (docs/AXISMUNDI-FORUM-ARCHITECTURE.md §6): the ax_forum CPT, a
 * dedicated 1:1 binding to a previously created managed Group Actor, local ax_topic
 * Article projection, and contextual Forum entries. Identity, authority, and Group
 * lifecycle stay in Axismundi Actors; Forum consumes its APIs and never writes Actor
 * tables.
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_FORUM_VERSION = '0.5.0';

require_once __DIR__ . '/includes/repository.php';
require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/topics.php';
require_once __DIR__ . '/includes/thread-context.php';
require_once __DIR__ . '/includes/memberships.php';
require_once __DIR__ . '/includes/inbound-topics.php';
require_once __DIR__ . '/includes/outbound-topics.php';
require_once __DIR__ . '/includes/templates.php';
if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin.php';
}

/**
 * Activation: install the binding schema and register the CPT + rewrite rules so the
 * archive/single permalinks resolve immediately. Idempotent.
 *
 * @return void
 */
function axismundi_forum_activate() : void {
	axismundi_forum_install();
	axismundi_forum_register_post_type();
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
 * Forum leans on the Axismundi Actors authority kernel for every binding decision.
 * If Actors is not active, surface an admin notice instead of failing silently — the
 * binding API itself fails closed (see repository.php).
 *
 * @return void
 */
function axismundi_forum_requires_actors_notice() : void {
	if ( function_exists( 'axismundi_actors_managed_actor_can_manage' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Axismundi Forum requires the Axismundi Actors plugin to be active. Group binding is disabled until Actors is available.', 'axismundi-forum' );
	echo '</p></div>';
}
add_action( 'admin_notices', 'axismundi_forum_requires_actors_notice' );
