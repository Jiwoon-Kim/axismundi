<?php
/**
 * Plugin Name:       Axismundi Activities
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-activities
 * Description:       ActivityStreams activity ledger and social relationship state for Axismundi. Requires Axismundi Actors and Axismundi Object Projections. It owns no HTTP inbox, signatures, delivery queue, notifications, or Web Push, and makes no network request of its own.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-activities
 *
 * @package AxismundiActivities
 *
 * Implemented: the immutable Activity ledger, social relation materialization, Follow and
 * Block, Like, Dislike, emoji reactions, Announce, Reply and votes with their Undos,
 * FEP-044f QuoteRequest decisions, local Object lifecycle recording, feed blocks,
 * public-safe collection queries, and a read-only administrator log.
 *
 * There is no HTTP client here, no scheduled event, no signature, no delivery queue, and no
 * notification. The public REST routes are reads, plus one anonymous remote-follow endpoint
 * that asks Actors to resolve a handle a visitor typed and answers with the address of
 * their own server; it is rate limited and stores nothing. readme.txt says so publicly.
 *
 * `Requires Plugins` is deliberately absent until the dependency slugs exist on
 * wordpress.org: a header naming a slug that is not there blocks activation instead of
 * explaining anything.
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTIVITIES_VERSION = '0.1.0';

require_once __DIR__ . '/includes/repository.php';
require_once __DIR__ . '/includes/audience.php';
require_once __DIR__ . '/includes/object-lifecycle.php';
require_once __DIR__ . '/includes/actor-lifecycle.php';
require_once __DIR__ . '/includes/relations.php';
require_once __DIR__ . '/includes/quote-authorizations.php';
require_once __DIR__ . '/includes/quote-requests.php';
require_once __DIR__ . '/includes/quote-outbound.php';
require_once __DIR__ . '/includes/local-social.php';
require_once __DIR__ . '/includes/follow-block.php';
require_once __DIR__ . '/includes/votes.php';
require_once __DIR__ . '/includes/reactions.php';
require_once __DIR__ . '/includes/unicode-catalogue.php';
require_once __DIR__ . '/includes/announces.php';
require_once __DIR__ . '/includes/interaction-block.php';
require_once __DIR__ . '/includes/like-block.php';
// After like-block.php, whose shared object-resolution gate this reuses.
require_once __DIR__ . '/includes/quote-block.php';
// After like-block.php, which owns the shared object-resolution gate this reuses.
require_once __DIR__ . '/includes/reaction-summary.php';
require_once __DIR__ . '/includes/reaction-blocks.php';
require_once __DIR__ . '/includes/announce-block.php';
require_once __DIR__ . '/includes/reply-block.php';
require_once __DIR__ . '/includes/actor-feed.php';
require_once __DIR__ . '/includes/feed-patterns.php';
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
