<?php
/**
 * Plugin Name: Axismundi JMAP
 * Description: JMAP development foundation with an authenticated Todo sharing proof of concept.
 * Version: 0.1.1-dev
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Requires Plugins: axismundi-actors
 * Author: Ji-woon Kim
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: axismundi-jmap
 *
 * @package AxismundiJmap
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/integrations/wordpress/accounts.php';
require_once __DIR__ . '/includes/integrations/wordpress/account-store.php';
require_once __DIR__ . '/includes/integrations/wordpress/runtime.php';
require_once __DIR__ . '/includes/integrations/wordpress/http.php';
require_once __DIR__ . '/includes/integrations/wordpress/account-selection.php';
require_once __DIR__ . '/modules/todo-poc/store.php';
require_once __DIR__ . '/modules/todo-poc/methods.php';
require_once __DIR__ . '/modules/todo-poc/runtime.php';
require_once __DIR__ . '/includes/integrations/wordpress/todo-admin.php';

// The authenticated admin PoC is not a public JMAP discovery/blob/SSE server.
register_activation_hook( __FILE__, '\\Axismundi\\Jmap\\install_account_store' );
