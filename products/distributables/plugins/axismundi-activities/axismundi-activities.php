<?php
/**
 * Plugin Name:       Axismundi Activities
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-activities
 * Description:       ActivityStreams activity ledger and social relationship state for Axismundi. It owns no HTTP inbox, signatures, delivery queue, notifications, or Web Push.
 * Version:           0.0.14
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Requires Plugins:  axismundi-actors
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-activities
 *
 * @package AxismundiActivities
 *
 * Phase 3 implements the immutable Activity ledger, social relation materialization,
 * Follow, Like, and personal Announce controls, Core Post Create recording, public-safe collection queries, and a read-only administrator log. It creates no public
 * Activity route, scheduled event, notification, signature, delivery queue, or
 * network request. Reply remains deferred until the Notes CPT contract is established.
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTIVITIES_VERSION = '0.0.13';

require_once __DIR__ . '/includes/repository.php';
require_once __DIR__ . '/includes/relations.php';
require_once __DIR__ . '/includes/local-social.php';
require_once __DIR__ . '/includes/reactions.php';
require_once __DIR__ . '/includes/announces.php';
require_once __DIR__ . '/includes/like-block.php';
require_once __DIR__ . '/includes/announce-block.php';
require_once __DIR__ . '/includes/post-lifecycle.php';
require_once __DIR__ . '/includes/local-social-ui.php';
if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin.php';
}

/** Install the Activity ledger. */
function axismundi_activities_activate() : void {
	axismundi_act_install();
}
register_activation_hook( __FILE__, 'axismundi_activities_activate' );
