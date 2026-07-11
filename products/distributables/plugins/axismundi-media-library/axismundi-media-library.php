<?php
/**
 * Plugin Name:       Axismundi Media Library
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-media-library
 * Description:       Promote WordPress attachments to independent media objects. Independent mode gives new uploads their own owner and visibility (public / unlisted / private) with per-surface access guards; Core mode leaves WordPress untouched.
 * Version:           0.0.2
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-media-library
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/**
 * Relationship mode option. `core` (default) leaves WordPress attachment
 * behaviour untouched; `independent` is the future publishing mode whose
 * behaviour ships in Phase 1 — in this Phase 0 scaffold the setting is recorded
 * but has no effect on media. See docs/COMPATIBILITY.md.
 */
const AXISMUNDI_MEDIA_MODE_OPTION  = 'ax_media_relationship_mode';
const AXISMUNDI_MEDIA_MODE_DEFAULT = 'core';

require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/mode.php';
require_once __DIR__ . '/includes/visibility.php';
require_once __DIR__ . '/includes/edit-fields.php';

/**
 * Activation is non-destructive (docs/SPEC.md §4, docs/COMPATIBILITY.md §1): it
 * must not change any existing Attachment, post_parent, post_status, permalink,
 * or option beyond seeding this plugin's own default mode.
 *
 * @return void
 */
function axismundi_media_activate() : void {
	if ( false === get_option( AXISMUNDI_MEDIA_MODE_OPTION, false ) ) {
		add_option( AXISMUNDI_MEDIA_MODE_OPTION, AXISMUNDI_MEDIA_MODE_DEFAULT );
	}
}
register_activation_hook( __FILE__, 'axismundi_media_activate' );

/**
 * Deactivation releases the attachment-pages option we own (restoring the prior
 * value only if it is still ours) and flushes rewrites. No media data, parent,
 * status, or visibility meta is changed — those are retained (docs/COMPATIBILITY.md
 * §5-§6).
 *
 * @return void
 */
function axismundi_media_deactivate() : void {
	axismundi_media_release_attachment_pages();
	flush_rewrite_rules( false );
}
register_deactivation_hook( __FILE__, 'axismundi_media_deactivate' );
