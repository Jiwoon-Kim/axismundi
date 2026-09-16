<?php
/**
 * Editor assets for the emoji picker (docs §9).
 *
 * The block editor gets the picker from this plugin, because since 0.3.0 custom emoji
 * render in any post and the plugin must work on its own: the picker had only appeared
 * where Note or Object Projections asked for it, so a site with Emoji alone had no button
 * (found in the Playground demo, 2026-09-17). Other editing surfaces, such as a profile
 * form, still ask for it through `axismundi_emoji_enqueue_picker()`; this plugin keeps no
 * list of other plugins' screens. Asking twice is harmless.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

/** Script handle consumers enqueue. */
const AXISMUNDI_EMOJI_PICKER_HANDLE = 'axismundi-emoji-picker';

/**
 * Register the picker so any editing surface can ask for it by handle.
 *
 * On `init`, not `admin_enqueue_scripts`. The block editor fires
 * `enqueue_block_editor_assets` from `edit-form-blocks.php` *before* `admin-header.php`
 * runs `admin_enqueue_scripts`, so a consumer enqueuing from the former would find this
 * handle unregistered and its guard would silently decline — no error, no button, and an
 * audit that fires the hooks in the wrong order would still pass. Registration needs no
 * admin context, so the earliest hook that has the plugin loaded is the correct one.
 *
 * @return void
 */
function axismundi_emoji_register_editor_assets() : void {
	$script = __DIR__ . '/../assets/editor/picker.js';
	$style  = __DIR__ . '/../assets/editor/picker.css';
	if ( ! is_readable( $script ) ) {
		return;
	}
	wp_register_script(
		AXISMUNDI_EMOJI_PICKER_HANDLE,
		plugins_url( 'assets/editor/picker.js', dirname( __DIR__ ) . '/axismundi-emoji.php' ),
		array( 'wp-element', 'wp-components', 'wp-block-editor', 'wp-rich-text', 'wp-i18n', 'wp-api-fetch', 'wp-url' ),
		AXISMUNDI_EMOJI_VERSION . '-' . (string) filemtime( $script ),
		true
	);
	wp_set_script_translations( AXISMUNDI_EMOJI_PICKER_HANDLE, 'axismundi-emoji' );
	if ( is_readable( $style ) ) {
		wp_register_style(
			AXISMUNDI_EMOJI_PICKER_HANDLE,
			plugins_url( 'assets/editor/picker.css', dirname( __DIR__ ) . '/axismundi-emoji.php' ),
			array(),
			AXISMUNDI_EMOJI_VERSION . '-' . (string) filemtime( $style )
		);
	}
}
add_action( 'init', 'axismundi_emoji_register_editor_assets' );

/**
 * Enqueue the picker, if this site has it.
 *
 * The one line a consumer needs. Guarded so a product keeps working with Emoji absent —
 * the author simply types the shortcode by hand, which is a supported path anyway since
 * the outbound declaration is rebuilt from the text rather than from what a picker did.
 *
 * @return bool Whether the picker was enqueued.
 */
function axismundi_emoji_enqueue_picker() : bool {
	if ( ! wp_script_is( AXISMUNDI_EMOJI_PICKER_HANDLE, 'registered' ) ) {
		return false;
	}
	static $unicode_passed = false;
	/*
	 * The Unicode tab reads group files by URL. Ten URLs, not the catalogue: the data itself
	 * is fetched when the tab opens, one group at a time, so an editor load carries none of
	 * the 1.7 MB.
	 */
	if ( ! $unicode_passed && function_exists( 'axismundi_emoji_unicode_picker_source' ) ) {
		$source = axismundi_emoji_unicode_picker_source();
		if ( array() !== $source['groups'] ) {
			wp_add_inline_script(
				AXISMUNDI_EMOJI_PICKER_HANDLE,
				'window.axismundiEmojiUnicodeSource = ' . wp_json_encode( array( 'groups' => (object) $source['groups'] ), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ) . ';',
				'before'
			);
		}
		$unicode_passed = true;
	}
	if ( function_exists( 'axismundi_emoji_unicode_enqueue_passive' ) ) {
		axismundi_emoji_unicode_enqueue_passive();
	}
	wp_enqueue_script( AXISMUNDI_EMOJI_PICKER_HANDLE );
	if ( wp_style_is( AXISMUNDI_EMOJI_PICKER_HANDLE, 'registered' ) ) {
		wp_enqueue_style( AXISMUNDI_EMOJI_PICKER_HANDLE );
	}
	return true;
}

/**
 * Offer the picker in every block editor.
 *
 * `enqueue_block_editor_assets` fires for the post, site and widget editors alike. The
 * picker is a rich-text format button, so it appears wherever rich text does.
 *
 * @return void
 */
function axismundi_emoji_enqueue_block_editor_picker() : void {
	axismundi_emoji_enqueue_picker();
}
add_action( 'enqueue_block_editor_assets', 'axismundi_emoji_enqueue_block_editor_picker' );
