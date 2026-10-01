<?php
/**
 * Plugin Name:       Axismundi
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/wordpress/plugins/axismundi
 * Description:       React applications and integration surfaces for Axismundi.
 * Version:           0.1.0-alpha0.1
 * Requires at least: 7.1
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi
 *
 * @package Axismundi
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_CAPSTONE_VERSION          = '0.1.0-alpha0.1';
const AXISMUNDI_CAPSTONE_REWRITE_VERSION  = '3';
const AXISMUNDI_CAPSTONE_QUERY_VAR        = 'axismundi_capstone';
const AXISMUNDI_CAPSTONE_ROUTE            = 'social';
const AXISMUNDI_CAPSTONE_ADMIN_PAGE       = 'axismundi';
const AXISMUNDI_CAPSTONE_ADMIN_CAPABILITY = 'manage_options';

require_once __DIR__ . '/includes/assets.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/route.php';
require_once __DIR__ . '/includes/admin.php';

/** @return void */
function axismundi_capstone_activate() : void {
	axismundi_capstone_add_rewrite_rule();
	flush_rewrite_rules( false );
}
register_activation_hook( __FILE__, 'axismundi_capstone_activate' );

/** @return void */
function axismundi_capstone_deactivate() : void {
	flush_rewrite_rules( false );
}
register_deactivation_hook( __FILE__, 'axismundi_capstone_deactivate' );
