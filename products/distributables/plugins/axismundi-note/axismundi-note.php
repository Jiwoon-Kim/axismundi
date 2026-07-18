<?php
/**
 * Plugin Name:       Axismundi Note
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-note
 * Description:       Note-owned local object container: the ax_note CPT plus a federation envelope (visibility, language, reply/context, sensitivity, mentions). Authoring storage only; transport and JSON-LD projection stay with the base plugins.
 * Version:           0.0.1
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Requires Plugins:  axismundi-actors, axismundi-object-projections, axismundi-activities
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-note
 *
 * @package AxismundiNote
 *
 * Increment 3 owns the storage substrate only: the private ax_note CPT, the
 * wp_ax_notes envelope table, a Classic Editor authoring UI, and a read/write
 * envelope API. It registers no JSON-LD transformer, opens no public object
 * route, records no Activity, and performs no network request. The Note object
 * transformer (increment 4) and Create/Update/Delete lifecycle (increment 5)
 * are deliberately deferred so followers-only and mentioned-only bodies cannot
 * leak before the fail-closed content-negotiation route exists.
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_NOTE_VERSION = '0.0.1';

require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/envelope.php';
if ( is_admin() ) {
	require_once __DIR__ . '/includes/editor.php';
}

/** Install the Note envelope store. */
function axismundi_note_activate() : void {
	axismundi_note_install_table();
	axismundi_note_register_cpt();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'axismundi_note_activate' );

/** Drop the private rewrite rules the CPT registered. */
function axismundi_note_deactivate() : void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'axismundi_note_deactivate' );
