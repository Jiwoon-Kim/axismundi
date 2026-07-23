<?php
/**
 * Block-editor authoring assets for the Note federation envelope.
 *
 * The increment 3 Classic Editor meta box is retired: the envelope is authored
 * through the React document panel over the structured `axismundi_note_envelope`
 * REST field. A rejected save now surfaces natively through the block editor's
 * REST error, so no meta-box transient notice is needed.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

/** Enqueue the envelope document panel on the Note block editor only. */
function axismundi_note_enqueue_editor_assets() : void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen instanceof WP_Screen || AXISMUNDI_NOTE_POST_TYPE !== $screen->post_type ) {
		return;
	}
	$plugin = dirname( __DIR__ ) . '/axismundi-note.php';
	$attachments_enabled = function_exists( 'axismundi_note_attachments_available' ) && axismundi_note_attachments_available();
	if ( $attachments_enabled ) {
		wp_enqueue_media();
	}

	wp_enqueue_script(
		'axismundi-note-editor-helpers',
		plugins_url( 'assets/editor/editor-helpers.js', $plugin ),
		array( 'wp-element' ),
		AXISMUNDI_NOTE_VERSION,
		true
	);
	// PluginDocumentSettingPanel moved from wp-edit-post to wp-editor; declaring an
	// unregistered handle would silently drop the panel, so depend on whichever exists.
	$deps = array( 'axismundi-note-editor-helpers', 'wp-element', 'wp-plugins', 'wp-data', 'wp-core-data', 'wp-components', 'wp-dom-ready', 'wp-i18n' );
	if ( wp_script_is( 'axismundi-op-mention-token-field', 'registered' ) ) {
		wp_enqueue_script( 'axismundi-op-mention-token-field' );
		$deps[] = 'axismundi-op-mention-token-field';
	}
	if ( $attachments_enabled && wp_script_is( 'media-editor', 'registered' ) ) {
		$deps[] = 'media-editor';
	}
	if ( wp_script_is( 'wp-editor', 'registered' ) ) {
		$deps[] = 'wp-editor';
	} elseif ( wp_script_is( 'wp-edit-post', 'registered' ) ) {
		$deps[] = 'wp-edit-post';
	}
	wp_enqueue_script(
		'axismundi-note-envelope-panel',
		plugins_url( 'assets/editor/envelope-panel.js', $plugin ),
		$deps,
		AXISMUNDI_NOTE_VERSION,
		true
	);
	wp_set_script_translations( 'axismundi-note-envelope-panel', 'axismundi-note' );
	wp_localize_script(
		'axismundi-note-envelope-panel',
		'axismundiNoteEditor',
		array( 'attachmentsEnabled' => $attachments_enabled )
	);
}
add_action( 'enqueue_block_editor_assets', 'axismundi_note_enqueue_editor_assets' );
