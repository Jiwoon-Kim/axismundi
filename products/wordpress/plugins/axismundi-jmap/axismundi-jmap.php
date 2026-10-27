<?php
/**
 * Plugin Name: Axismundi JMAP
 * Description: Development foundation for JMAP over Actor-backed accounts. No public protocol routes yet.
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
require_once __DIR__ . '/includes/integrations/wordpress/account-selection.php';

// No public JMAP discovery/API/blob/SSE routes or capability advertisement.
register_activation_hook( __FILE__, '\\Axismundi\\Jmap\\install_account_store' );
