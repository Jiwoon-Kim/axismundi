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
	$assets = dirname( __DIR__ ) . '/assets/editor/';
	$attachments_enabled = function_exists( 'axismundi_note_attachments_available' ) && axismundi_note_attachments_available();
	if ( $attachments_enabled ) {
		wp_enqueue_media();
	}

	wp_enqueue_script(
		'axismundi-note-editor-helpers',
		plugins_url( 'assets/editor/editor-helpers.js', $plugin ),
		array( 'wp-element' ),
		AXISMUNDI_NOTE_VERSION . '-' . (string) filemtime( $assets . 'editor-helpers.js' ),
		true
	);
	// PluginDocumentSettingPanel moved from wp-edit-post to wp-editor; declaring an
	// unregistered handle would silently drop the panel, so depend on whichever exists.
	$deps = array( 'axismundi-note-editor-helpers', 'wp-element', 'wp-plugins', 'wp-data', 'wp-core-data', 'wp-components', 'wp-dom-ready', 'wp-i18n', 'wp-api-fetch' );
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
		AXISMUNDI_NOTE_VERSION . '-' . (string) filemtime( $assets . 'envelope-panel.js' ),
		true
	);
	wp_set_script_translations( 'axismundi-note-envelope-panel', 'axismundi-note' );
	/*
	 * The custom emoji picker, if this site has one. It adds a button to the inline
	 * RichText toolbar and inserts `:shortcode:` as text; the outbound declaration is
	 * assembled from the body at publish time, so nothing here has to be kept in sync and
	 * typing a shortcode by hand works identically.
	 */
	if ( function_exists( 'axismundi_emoji_enqueue_picker' ) ) {
		axismundi_emoji_enqueue_picker();
	}
	$editor_user_id   = function_exists( 'axismundi_op_editor_language_user_id' )
		? axismundi_op_editor_language_user_id( AXISMUNDI_NOTE_POST_TYPE )
		: get_current_user_id();
	$default_language = function_exists( 'axismundi_op_default_language_for_user' )
		? axismundi_op_default_language_for_user( $editor_user_id )
		: array( 'language' => function_exists( 'axismundi_actors_site_language' ) ? axismundi_actors_site_language() : get_locale(), 'source' => 'site' );
	wp_localize_script(
		'axismundi-note-envelope-panel',
		'axismundiNoteEditor',
		array(
			'attachmentsEnabled' => $attachments_enabled,
			'languageOptions'    => function_exists( 'axismundi_op_language_options' ) ? axismundi_op_language_options() : array(),
			'defaultLanguage'    => $default_language,
		)
	);
}
add_action( 'enqueue_block_editor_assets', 'axismundi_note_enqueue_editor_assets' );
