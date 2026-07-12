<?php
/**
 * Plugin Name:       Axismundi Media Library
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-media-library
 * Description:       Promote WordPress attachments to independent media objects. Independent mode unbinds new uploads and adds visibility controls (public / unlisted / private) with per-surface access guards; Core mode leaves WordPress untouched.
 * Version:           0.0.8
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
 * behaviour untouched; `independent` unbinds new uploads, enables Attachment
 * pages, and enforces the Phase 1 visibility model. See docs/COMPATIBILITY.md.
 */
const AXISMUNDI_MEDIA_MODE_OPTION  = 'ax_media_relationship_mode';
const AXISMUNDI_MEDIA_MODE_DEFAULT = 'core';

require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/mode.php';
require_once __DIR__ . '/includes/visibility.php';
require_once __DIR__ . '/includes/edit-fields.php';
require_once __DIR__ . '/includes/archive.php';
require_once __DIR__ . '/includes/folders.php';
require_once __DIR__ . '/includes/folder-gate.php';
require_once __DIR__ . '/includes/folder-rest.php';
require_once __DIR__ . '/includes/media-modal.php';
require_once __DIR__ . '/includes/admin-folders.php';

/**
 * First activation is non-destructive (docs/SPEC.md §4, docs/COMPATIBILITY.md
 * §1): it changes no existing Attachment, parent, status, or permalink. A later
 * reactivation with Independent mode retained re-acquires the attachment-pages
 * option owned by this plugin.
 *
 * @return void
 */
function axismundi_media_activate() : void {
	if ( false === get_option( AXISMUNDI_MEDIA_MODE_OPTION, false ) ) {
		add_option( AXISMUNDI_MEDIA_MODE_OPTION, AXISMUNDI_MEDIA_MODE_DEFAULT );
	}
	// Reactivating while Independent mode persists must re-acquire the attachment
	// pages option (no option-update event fires on reactivation). ROUTING.md §1.2.
	if ( axismundi_media_is_independent() ) {
		axismundi_media_acquire_attachment_pages();
		axismundi_media_register_rewrite_rules();
	}
	flush_rewrite_rules( false );
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
	axismundi_media_remove_rewrite_rules();
	flush_rewrite_rules( false );
}
register_deactivation_hook( __FILE__, 'axismundi_media_deactivate' );
