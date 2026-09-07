<?php
/**
 * Editor assets for the custom emoji picker (docs §9).
 *
 * Registered here, enqueued by the consumer. This plugin has no business knowing which
 * screens Note, Object Projections, or a future profile editor consider theirs, and a
 * list of other people's screens kept here would need editing every time one of them
 * grew a new surface. The same reasoning that makes every cross-plugin call sit behind
 * `function_exists()` applies to assets.
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
	wp_enqueue_script( AXISMUNDI_EMOJI_PICKER_HANDLE );
	if ( wp_style_is( AXISMUNDI_EMOJI_PICKER_HANDLE, 'registered' ) ) {
		wp_enqueue_style( AXISMUNDI_EMOJI_PICKER_HANDLE );
	}
	return true;
}
