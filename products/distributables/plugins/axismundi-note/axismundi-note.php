<?php
/**
 * Plugin Name:       Axismundi Note
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-note
 * Description:       Note-owned local object container with a private authoring CPT, federation envelope, and fail-closed ActivityStreams Note projection.
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
 * Increment 5 records immutable Create, Update, and Delete Activities from
 * strict committed Note snapshots. Delivery remains a Bridge concern; this
 * plugin performs no network request.
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_NOTE_VERSION = '0.0.1';

require_once __DIR__ . '/includes/schema.php';
require_once __DIR__ . '/includes/cpt.php';
require_once __DIR__ . '/includes/envelope.php';
require_once __DIR__ . '/includes/attachments.php';
require_once __DIR__ . '/includes/federation.php';
require_once __DIR__ . '/includes/lifecycle.php';
require_once __DIR__ . '/includes/view.php';
require_once __DIR__ . '/includes/interactions.php';
require_once __DIR__ . '/includes/rest.php';
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
