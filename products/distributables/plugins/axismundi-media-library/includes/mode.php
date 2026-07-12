<?php
/**
 * Independent-mode side effects: attachment-pages ownership + rewrite flush.
 * Core mode leaves everything untouched. See docs/ROUTING.md §1.2,
 * docs/COMPATIBILITY.md §4-§5.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_MEDIA_PAGES_PREV  = 'ax_media_attach_pages_prev';
const AXISMUNDI_MEDIA_PAGES_SET   = 'ax_media_attach_pages_set';
const AXISMUNDI_MEDIA_PAGES_OWNED = 'ax_media_attach_pages_owned';

/**
 * True while Independent media mode is active.
 *
 * @return bool
 */
function axismundi_media_is_independent() : bool {
	return 'independent' === axismundi_media_get_mode();
}

/**
 * React to a relationship-mode change.
 *
 * @param mixed $old Previous value.
 * @param mixed $new New value.
 * @return void
 */
function axismundi_media_on_mode_change( $old, $new ) : void {
	if ( $old === $new ) {
		return;
	}
	if ( 'independent' === $new ) {
		axismundi_media_acquire_attachment_pages();
		axismundi_media_register_rewrite_rules();
	} elseif ( 'independent' === $old ) {
		axismundi_media_release_attachment_pages();
		axismundi_media_remove_rewrite_rules();
	}
	flush_rewrite_rules( false );
}
add_action( 'update_option_' . AXISMUNDI_MEDIA_MODE_OPTION, 'axismundi_media_on_mode_change', 10, 2 );
add_action(
	'add_option_' . AXISMUNDI_MEDIA_MODE_OPTION,
	static function ( $name, $value ) : void {
		if ( 'independent' === $value ) {
			axismundi_media_on_mode_change( 'core', 'independent' );
		}
	},
	10,
	2
);

/**
 * Enable attachment pages, recording prev + our value + ownership so we only
 * restore our own value later and never clobber another admin/plugin change.
 *
 * @return void
 */
function axismundi_media_acquire_attachment_pages() : void {
	if ( get_option( AXISMUNDI_MEDIA_PAGES_OWNED ) ) {
		return; // Already own it.
	}
	$prev = (int) get_option( 'wp_attachment_pages_enabled', 1 );
	update_option( AXISMUNDI_MEDIA_PAGES_PREV, $prev );
	update_option( 'wp_attachment_pages_enabled', 1 );
	update_option( AXISMUNDI_MEDIA_PAGES_SET, 1 );
	update_option( AXISMUNDI_MEDIA_PAGES_OWNED, 1 );
}

/**
 * Restore the prior attachment-pages value ONLY if the current value is still the
 * one we wrote (ROUTING.md §1.2). Drops ownership either way.
 *
 * @return void
 */
function axismundi_media_release_attachment_pages() : void {
	if ( ! get_option( AXISMUNDI_MEDIA_PAGES_OWNED ) ) {
		return;
	}
	$current = (int) get_option( 'wp_attachment_pages_enabled', 1 );
	$set     = (int) get_option( AXISMUNDI_MEDIA_PAGES_SET, 1 );
	if ( $current === $set ) {
		update_option( 'wp_attachment_pages_enabled', (int) get_option( AXISMUNDI_MEDIA_PAGES_PREV, 1 ) );
	}
	delete_option( AXISMUNDI_MEDIA_PAGES_OWNED );
	delete_option( AXISMUNDI_MEDIA_PAGES_SET );
	delete_option( AXISMUNDI_MEDIA_PAGES_PREV );
}
