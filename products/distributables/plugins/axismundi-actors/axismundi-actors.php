<?php
/**
 * Plugin Name:       Axismundi Actors
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-actors
 * Description:       Identity registry for Axismundi. Gives every local person, the site itself, and (later) remote actors one immutable identity URI and one human profile hub, and wires each domain plugin's archive in as a projection. Identity only — it owns no content, likes, collections, or activity.
 * Version:           0.0.2
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
 * Phase 0 scaffold — the contract docs in docs/ (SPEC, DATA-MODEL, ROUTING,
 * SECURITY, PROJECTIONS, PHASES) are the deliverable at this stage. The repository
 * (wp_ax_identities + wp_ax_actors), routing, and projection registry are
 * implemented from Phase 1 onward; this file intentionally ships no behaviour yet.
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTORS_VERSION = '0.0.2';

require_once __DIR__ . '/includes/repository.php';

/**
 * Activation: install the schema, then seed (always the site actor; the site-owner
 * Person only when the activating user is a valid admin). Idempotent.
 *
 * @return void
 */
function axismundi_actors_activate() : void {
	axismundi_actors_install();
	axismundi_actors_seed();
}
register_activation_hook( __FILE__, 'axismundi_actors_activate' );

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

// Phase 2+ requires (routing, profile page, projection registry) are wired here.
