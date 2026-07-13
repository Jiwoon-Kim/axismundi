<?php
/**
 * Plugin Name:       Axismundi Actors
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-actors
 * Description:       Identity registry for Axismundi. Gives every local person, the site itself, and (later) remote actors one immutable identity URI and one human profile hub, and wires each domain plugin's archive in as a projection. Identity only — it owns no content, likes, collections, or activity.
 * Version:           0.0.6
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-actors
 *
 * @package AxismundiActors
 *
 * The repository and profile routing are implemented. Projection registration,
 * admin integration, and federation remain later phases.
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTORS_VERSION = '0.0.6';

require_once __DIR__ . '/includes/repository.php';
require_once __DIR__ . '/includes/routing.php';
require_once __DIR__ . '/includes/projections.php';

/**
 * Activation: install the schema, then seed (always the site actor; the site-owner
 * Person only when the activating user is a valid admin). Idempotent.
 *
 * @return void
 */
function axismundi_actors_activate() : void {
	axismundi_actors_install();
	axismundi_actors_seed();
	axismundi_actors_register_rewrite_rules();
	flush_rewrite_rules( false );
}
register_activation_hook( __FILE__, 'axismundi_actors_activate' );

/** @return void */
function axismundi_actors_deactivate() : void {
	axismundi_actors_remove_rewrite_rules();
	flush_rewrite_rules( false );
}
register_deactivation_hook( __FILE__, 'axismundi_actors_deactivate' );

/**
 * Run dbDelta when the stored schema version lags the code (upgrades without a
 * reactivation).
 *
 * @return void
 */
function axismundi_actors_maybe_upgrade() : void {
	if ( (string) get_option( 'ax_actors_db_version', '' ) !== AXISMUNDI_ACTORS_DB_VERSION ) {
		axismundi_actors_install();
	}
}
add_action( 'plugins_loaded', 'axismundi_actors_maybe_upgrade' );

// Phase 4+ admin integration is wired here.
