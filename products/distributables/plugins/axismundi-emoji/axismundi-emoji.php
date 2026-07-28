<?php
/**
 * Plugin Name:       Axismundi Emoji
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-emoji
 * Description:       Custom emoji for Axismundi. Registry, admission review, and per-authority binary cache for FEP-9098 emoji observed in federated tags, plus local emoji registration and a block-editor picker. Unicode emoji and emoji reactions are deliberately out of scope.
 * Version:           0.1.3
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-emoji
 *
 * @package AxismundiEmoji
 *
 * E1 and E2 complete: a received emoji is observed, reviewed, cached, and rendered;
 * a local one is uploaded or copied from a cached remote, catalogued, and published in
 * the outbound `tag[]` of Notes, Articles, and Actors. The contract, fixtures, and
 * harness under tests/ keep all of it grounded in captured wire evidence.
 *
 * Still deliberately absent: emoji reactions (E4).
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_EMOJI_VERSION = '0.1.3';

/**
 * Cap on emoji declared by a single Object or Actor.
 *
 * A remote controls how many `tag[]` entries it sends, and each one becomes a
 * review-queue row and a candidate download. Without a cap, one hostile or merely
 * enthusiastic post floods both.
 */
const AXISMUNDI_EMOJI_MAX_PER_SUBJECT = 40;

/**
 * The one thing E0 fixes in place: what counts as a shortcode.
 *
 * FEP-9098 asks for at least two characters of `[a-zA-Z0-9_]` between non-alphanumeric
 * boundaries. The optional `@authority` suffix is Misskey's qualified form, observed on
 * reactions such as `:09_bird@hoto.moe:`, and is accepted everywhere because rejecting
 * it costs a whole class of emoji and accepting it costs nothing.
 */
const AXISMUNDI_EMOJI_SHORTCODE_PATTERN = '/^:([a-zA-Z0-9_]{2,}?)(?:@([a-z0-9.-]+\.[a-z]{2,}))?:$/i';

/**
 * Review states (docs §7). `pending` renders as plain text, never as an image.
 *
 * This axis governs *rendering* and nothing else. Neither a restrictive licence nor
 * `localOnly` may force `rejected`: both constrain what we may send, not whether a
 * message we already received is shown the way its author wrote it.
 */
const AXISMUNDI_EMOJI_REVIEW_STATES = array( 'pending', 'approved', 'rejected' );

/**
 * Licence classification (docs §3). Three states, not two, because in a real
 * catalogue unlicensed emoji outnumber explicitly restricted ones — folding
 * `unknown` into `restricted` withholds more than it protects, and folding it into
 * `allowed` re-uses assets whose terms nobody stated.
 */
const AXISMUNDI_EMOJI_LICENSE_STATES = array( 'unknown', 'allowed', 'restricted' );

/**
 * Per-file cap for a cached remote original.
 *
 * Deliberately above FEP-9098's 256 KB recommendation. That figure binds what we
 * publish (§8); on ingestion it would reject the measured misskey.io APNG, which is
 * 1.92 MiB — 7.5× the recommendation — and a large share of a real instance's set
 * with it. Strict on send, lenient on receive.
 */
const AXISMUNDI_EMOJI_MAX_BYTES = 2097152;

/** FEP-9098 Compatibility: what our own outbound emoji must satisfy. */
const AXISMUNDI_EMOJI_OUTBOUND_MAX_BYTES   = 262144;
const AXISMUNDI_EMOJI_OUTBOUND_MEDIA_TYPES = array( 'image/png', 'image/gif', 'image/webp' );

/**
 * The one emoji this plugin ships: `:axismundi:`.
 *
 * A single 200x200 source, because FEP-9098 gives an Emoji exactly one `icon.url`.
 * There is no `srcset` in the emoji contract, so shipping several sizes would federate
 * nothing extra — one square generous enough for a picker tile is scaled down by CSS
 * for the ~1.2em inline case. At 5.6 KB it is a fiftieth of the size the spec allows.
 *
 * Since E2 it is a registered `scope = local` emoji, installed once and governed by
 * local registration rules only: the remote `review_status` axis (§7) never applies to
 * it, because there is no third party whose licence or distribution wishes are in
 * question. Deleting it sticks — the installer records that it has run rather than
 * putting it back on every activation.
 */
const AXISMUNDI_EMOJI_BUNDLED_SHORTCODE = ':axismundi:';
const AXISMUNDI_EMOJI_BUNDLED_FILE      = 'emoji/axismundi.webp';

/**
 * Every emoji this plugin ships, and the terms each one travels under.
 *
 * Not one licence for the directory. `:axismundi:` is ours and is released under the
 * plugin's own GPL; `:wordpress:` is the WordPress Foundation's trademark, included to
 * *refer to* WordPress the way Mastodon and Misskey bundle each other's marks. Trademark
 * permission is not a copyright licence, so that file is never described as GPL — the
 * distinction is recorded in `emoji/LICENSE.txt` beside the files themselves, where
 * anyone unpacking the ZIP will find it.
 */
const AXISMUNDI_EMOJI_BUNDLED = array(
	'axismundi' => array(
		'file'     => 'emoji/axismundi.webp',
		'category' => 'Axismundi',
	),
	'wordpress' => array(
		'file'     => 'emoji/wordpress.webp',
		'category' => 'WordPress',
	),
);

/** Absolute path to the bundled emoji, or '' when it is missing. */
function axismundi_emoji_bundled_path() : string {
	$path = __DIR__ . '/' . AXISMUNDI_EMOJI_BUNDLED_FILE;
	return is_readable( $path ) ? $path : '';
}

require_once __DIR__ . '/includes/repository.php';
require_once __DIR__ . '/includes/parser.php';
require_once __DIR__ . '/includes/observation.php';
require_once __DIR__ . '/includes/review.php';
require_once __DIR__ . '/includes/verification-worker.php';
require_once __DIR__ . '/includes/binary-cache.php';
require_once __DIR__ . '/includes/local.php';
require_once __DIR__ . '/includes/catalogue.php';
require_once __DIR__ . '/includes/outbound.php';
require_once __DIR__ . '/includes/renderer.php';
require_once __DIR__ . '/includes/integrations/actors.php';
require_once __DIR__ . '/includes/integrations/object-projections.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/editor.php';

/**
 * Load the inline emoji typography contract wherever custom emoji can render.
 *
 * This belongs to Emoji rather than an individual consumer: Actors is the first
 * integration, but Object content and future editor surfaces use the same markup.
 * `1em` lets each surrounding text style choose the rendered emoji size.
 *
 * @return void
 */
function axismundi_emoji_enqueue_frontend_style() : void {
	$path = __DIR__ . '/assets/emoji.css';
	if ( ! is_readable( $path ) ) {
		return;
	}
	wp_enqueue_style(
		'axismundi-emoji',
		plugins_url( 'assets/emoji.css', __FILE__ ),
		array(),
		(string) filemtime( $path )
	);
}
add_action( 'wp_enqueue_scripts', 'axismundi_emoji_enqueue_frontend_style' );

/** Install the registry schema and the emoji route on activation. */
function axismundi_emoji_activate() : void {
	axismundi_emoji_install();
	// The route serves the `id` we publish in every outbound `tag[]`, so it has to exist
	// from the first request rather than from the first `init` after activation.
	axismundi_emoji_add_rewrite();
	flush_rewrite_rules( false );
}
register_activation_hook( __FILE__, 'axismundi_emoji_activate' );

/** Remove plugin-owned recurring work when the plugin is deactivated. */
function axismundi_emoji_deactivate() : void {
	wp_clear_scheduled_hook( 'axismundi_emoji_process_verification_batch' );
	wp_clear_scheduled_hook( 'axismundi_emoji_verification_recovery' );
	wp_clear_scheduled_hook( 'axismundi_emoji_process_download_batch' );
	wp_clear_scheduled_hook( 'axismundi_emoji_download_recovery' );
	// Drop the emoji route with the plugin, so the path stops matching instead of
	// lingering as a rule nothing answers.
	flush_rewrite_rules( false );
}
register_deactivation_hook( __FILE__, 'axismundi_emoji_deactivate' );
