<?php
/**
 * Plugin Name:       Axismundi Note
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/wordpress/plugins/axismundi-note
 * Description:       Deprecated, inert legacy Note plugin retained for installation inventory only.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-note
 *
 * @package AxismundiNote
 *
 * Hard cutoff: no legacy runtime loads. New local Notes belong to Object Projections.
 */

defined( 'ABSPATH' ) || exit;

// Do not load legacy includes or register hooks, including on activation/deactivation.
// Existing posts, tables, options, capabilities and relation rows remain untouched.
