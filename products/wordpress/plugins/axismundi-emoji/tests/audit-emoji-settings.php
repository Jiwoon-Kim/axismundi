<?php
/**
 * Emoji settings: Unicode rendering and WordPress's image fallback (dev-only; dist-excluded).
 *
 * Run: npx wp-env run cli wp eval-file wp-content/plugins/axismundi-emoji/tests/audit-emoji-settings.php
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/axismundi-emoji.php';
// WP-CLI loads no admin includes: add_submenu_page() and settings_fields() live in plugin.php,
// submit_button() and settings_errors() in template.php.
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';

$ax_st_results = array();

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_st_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/** Put Core's emoji hooks back exactly as default-filters.php has them. */
function ax_st_restore_core_hooks() : void {
	add_action( 'wp_head', 'print_emoji_detection_script', 7 );
	add_action( 'embed_head', 'print_emoji_detection_script' );
	add_action( 'wp_print_styles', 'print_emoji_styles' );
	add_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
}

$ax_st_saved = array(
	'rendering' => get_option( AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION, null ),
	'fallback'  => get_option( AXISMUNDI_EMOJI_CORE_IMAGE_FALLBACK_OPTION, null ),
);

try {
	// -- Where the settings live --------------------------------------------------------------

	$ax_st_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	wp_set_current_user( (int) ( $ax_st_admins[0] ?? 0 ) );
	global $submenu;
	do_action( 'admin_menu' );
	$ax_st_items = array_column( (array) ( $submenu['axismundi-emoji'] ?? array() ), 2 );
	ax_st_assert( $ax_st_results, 'a Settings submenu sits under Emojis, apart from the custom emoji review screen', in_array( AXISMUNDI_EMOJI_SETTINGS_PAGE, $ax_st_items, true ) && in_array( 'axismundi-emoji', $ax_st_items, true ) );
	$ax_st_entry = array_values( array_filter( (array) ( $submenu['axismundi-emoji'] ?? array() ), static fn( $item ) : bool => AXISMUNDI_EMOJI_SETTINGS_PAGE === ( $item[2] ?? '' ) ) );
	ax_st_assert( $ax_st_results, 'and it needs manage_options, since it changes how every page renders', 'manage_options' === ( $ax_st_entry[0][1] ?? '' ) );

	// This plugin's registration only: firing all of admin_init runs every other plugin's admin code too.
	axismundi_emoji_register_settings();
	$ax_st_registered = get_registered_settings();
	ax_st_assert( $ax_st_results, 'both options are registered with the Settings API under one group', AXISMUNDI_EMOJI_SETTINGS_GROUP === ( $ax_st_registered[ AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION ]['group'] ?? '' ) && AXISMUNDI_EMOJI_SETTINGS_GROUP === ( $ax_st_registered[ AXISMUNDI_EMOJI_CORE_IMAGE_FALLBACK_OPTION ]['group'] ?? '' ) );

	ob_start();
	axismundi_emoji_render_settings_page();
	$ax_st_page = (string) ob_get_clean();
	ax_st_assert( $ax_st_results, 'the page posts to options.php with the group\'s nonce', str_contains( $ax_st_page, 'options.php' ) && 1 === preg_match( '/name=[\'"]option_page[\'"] value=[\'"]' . preg_quote( AXISMUNDI_EMOJI_SETTINGS_GROUP, '/' ) . '[\'"]/', $ax_st_page ) );
	ax_st_assert( $ax_st_results, 'it offers the three rendering modes: automatic fallback, Noto Color Emoji, WordPress fallback only', str_contains( $ax_st_page, 'value="auto"' ) && str_contains( $ax_st_page, 'value="font"' ) && str_contains( $ax_st_page, 'value="core"' ) );
	ax_st_assert( $ax_st_results, 'the image switch posts 0 when unchecked, so turning it off is saved', str_contains( $ax_st_page, 'type="hidden" name="' . AXISMUNDI_EMOJI_CORE_IMAGE_FALLBACK_OPTION . '" value="0"' ) );

	// -- Sanitizing ---------------------------------------------------------------------------

	ax_st_assert( $ax_st_results, 'the three modes are kept and an unknown value falls back to auto', 'font' === axismundi_emoji_sanitize_unicode_rendering( 'font' ) && 'core' === axismundi_emoji_sanitize_unicode_rendering( 'core' ) && 'auto' === axismundi_emoji_sanitize_unicode_rendering( 'strict' ) );
	ax_st_assert( $ax_st_results, 'the image switch stores only 1 or 0', '1' === axismundi_emoji_sanitize_core_image_fallback( '1' ) && '0' === axismundi_emoji_sanitize_core_image_fallback( 'yes' ) && '0' === axismundi_emoji_sanitize_core_image_fallback( null ) );
	ax_st_assert( $ax_st_results, 'sanitize callbacks survive WordPress passing extra arguments (PHP 8)', '0' === sanitize_option( AXISMUNDI_EMOJI_CORE_IMAGE_FALLBACK_OPTION, 'x' ) && 'auto' === sanitize_option( AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION, 'x' ) );

	// -- The image fallback switch ------------------------------------------------------------

	delete_option( AXISMUNDI_EMOJI_CORE_IMAGE_FALLBACK_OPTION );
	ax_st_assert( $ax_st_results, 'WordPress\'s image fallback is on by default', axismundi_emoji_core_image_fallback_enabled() );
	$ax_st_active_before = axismundi_emoji_unicode_is_active();

	wp_set_current_user( 0 );
	axismundi_emoji_apply_core_image_fallback();
	ax_st_assert( $ax_st_results, 'left on, Core\'s detection script stays on pages and embeds', 7 === has_action( 'wp_head', 'print_emoji_detection_script' ) && false !== has_action( 'embed_head', 'print_emoji_detection_script' ) );

	update_option( AXISMUNDI_EMOJI_CORE_IMAGE_FALLBACK_OPTION, '0' );
	axismundi_emoji_apply_core_image_fallback();
	ax_st_assert( $ax_st_results, 'turned off, the detection script is removed from pages and embeds', false === has_action( 'wp_head', 'print_emoji_detection_script' ) && false === has_action( 'embed_head', 'print_emoji_detection_script' ) );
	ax_st_assert( $ax_st_results, 'and so are its image styles', false === has_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' ) && false === has_action( 'wp_print_styles', 'print_emoji_styles' ) );
	ax_st_assert( $ax_st_results, 'feeds and emails keep WordPress\'s own image replacement', false !== has_filter( 'the_content_feed', 'wp_staticize_emoji' ) && false !== has_filter( 'wp_mail', 'wp_staticize_emoji_for_email' ) );
	ax_st_assert( $ax_st_results, 'the Unicode font layer is independent of the switch', axismundi_emoji_unicode_is_active() === $ax_st_active_before );
	ax_st_restore_core_hooks();
} catch ( Throwable $ax_st_error ) {
	ax_st_assert( $ax_st_results, 'the settings suite ran to completion: ' . $ax_st_error->getMessage(), false );
} finally {
	foreach ( array( AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION => $ax_st_saved['rendering'], AXISMUNDI_EMOJI_CORE_IMAGE_FALLBACK_OPTION => $ax_st_saved['fallback'] ) as $ax_st_name => $ax_st_value ) {
		if ( null === $ax_st_value ) {
			delete_option( $ax_st_name );
		} else {
			update_option( $ax_st_name, $ax_st_value );
		}
	}
}

$ax_st_failures = count( array_filter( $ax_st_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_st_results ), $ax_st_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_st_failures > 0 ? 1 : 0 );
}
exit( $ax_st_failures > 0 ? 1 : 0 );
