<?php
/**
 * Site-wide emoji settings: how Unicode emoji are drawn, and whether WordPress's image
 * fallback runs at all.
 *
 * A submenu of its own rather than another tab on the Emojis screen. That screen is about
 * custom emoji — reviewing what other servers declared and registering this site's own — and
 * is gated by the review capability. These choices change how every page on the site renders
 * text, so they sit with the other site options and require `manage_options`.
 *
 * Two settings, deliberately independent (docs/AXISMUNDI-EMOJI-UNICODE.md §1 D3, D8):
 *
 * - Rendering: `auto` lets the browser draw what it can and gives the bundled font the
 *   profiles it cannot (flags today); `font` draws every emoji with the complete bundled font;
 *   `core` leaves Unicode emoji to WordPress entirely.
 * - WordPress emoji images: whether Core's detection script, and with it the replacement of
 *   emoji by images from s.w.org, runs on this site's pages. Off means no emoji image
 *   requests to WordPress.org; an emoji neither the browser nor the font can draw is left as
 *   the browser shows it.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_EMOJI_CORE_IMAGE_FALLBACK_OPTION = 'axismundi_emoji_core_image_fallback';
const AXISMUNDI_EMOJI_SETTINGS_PAGE              = 'axismundi-emoji-settings';
const AXISMUNDI_EMOJI_SETTINGS_GROUP             = 'axismundi_emoji_settings';

/**
 * Whether WordPress's emoji image fallback runs on this site's pages. Default on.
 *
 * @return bool
 */
function axismundi_emoji_core_image_fallback_enabled() : bool {
	return '0' !== (string) get_option( AXISMUNDI_EMOJI_CORE_IMAGE_FALLBACK_OPTION, '1' );
}

/**
 * Sanitize the rendering policy. One-argument wrapper: WordPress passes extra arguments to
 * sanitize callbacks, and a built-in with a different signature fails on PHP 8.
 *
 * @param mixed $value Submitted value.
 * @return string
 */
function axismundi_emoji_sanitize_unicode_rendering( $value ) : string {
	return in_array( $value, array( 'auto', 'font', 'core' ), true ) ? $value : 'auto';
}

/**
 * Sanitize the image fallback switch. An unchecked checkbox submits nothing, so absence is off.
 *
 * @param mixed $value Submitted value.
 * @return string '1' or '0'.
 */
function axismundi_emoji_sanitize_core_image_fallback( $value ) : string {
	return '1' === (string) $value ? '1' : '0';
}

/** @return void */
function axismundi_emoji_register_settings() : void {
	register_setting(
		AXISMUNDI_EMOJI_SETTINGS_GROUP,
		AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION,
		array(
			'type'              => 'string',
			'default'           => 'auto',
			'sanitize_callback' => 'axismundi_emoji_sanitize_unicode_rendering',
			'show_in_rest'      => false,
		)
	);
	register_setting(
		AXISMUNDI_EMOJI_SETTINGS_GROUP,
		AXISMUNDI_EMOJI_CORE_IMAGE_FALLBACK_OPTION,
		array(
			'type'              => 'string',
			'default'           => '1',
			'sanitize_callback' => 'axismundi_emoji_sanitize_core_image_fallback',
			'show_in_rest'      => false,
		)
	);
}
add_action( 'admin_init', 'axismundi_emoji_register_settings' );

/** @return void */
function axismundi_emoji_register_settings_page() : void {
	add_submenu_page(
		'axismundi-emoji',
		__( 'Emoji settings', 'axismundi-emoji' ),
		__( 'Settings', 'axismundi-emoji' ),
		'manage_options',
		AXISMUNDI_EMOJI_SETTINGS_PAGE,
		'axismundi_emoji_render_settings_page'
	);
}
// After the Emojis menu itself (registered at the default priority), so the parent exists.
add_action( 'admin_menu', 'axismundi_emoji_register_settings_page', 20 );

/** @return void */
function axismundi_emoji_render_settings_page() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot change emoji settings.', 'axismundi-emoji' ), '', array( 'response' => 403 ) );
	}
	$rendering = axismundi_emoji_unicode_rendering_mode();
	$fallback  = axismundi_emoji_core_image_fallback_enabled();
	$has_font  = array() !== axismundi_emoji_unicode_font_manifest()['profiles'];
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Emoji settings', 'axismundi-emoji' ); ?></h1>
		<?php settings_errors(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
			<?php settings_fields( AXISMUNDI_EMOJI_SETTINGS_GROUP ); ?>
			<h2><?php esc_html_e( 'Unicode emoji', 'axismundi-emoji' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Rendering', 'axismundi-emoji' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'Rendering', 'axismundi-emoji' ); ?></legend>
							<p>
								<label>
									<input type="radio" name="<?php echo esc_attr( AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION ); ?>" value="auto" <?php checked( 'auto', $rendering ); ?>>
									<?php esc_html_e( 'Automatic fallback', 'axismundi-emoji' ); ?>
								</label>
								<br><span class="description"><?php esc_html_e( 'The browser draws emoji itself where it can. Where it cannot draw one the bundled Noto Color Emoji font covers (flags, for now), that emoji is drawn with the font — still as text, not as an image. Only a small flags font is downloaded, and only by browsers that need it.', 'axismundi-emoji' ); ?></span>
							</p>
							<p>
								<label>
									<input type="radio" name="<?php echo esc_attr( AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION ); ?>" value="font" <?php checked( 'font', $rendering ); ?>>
									<?php esc_html_e( 'Use Noto Color Emoji', 'axismundi-emoji' ); ?>
								</label>
								<br><span class="description"><?php esc_html_e( 'Every Unicode emoji on this site is drawn with the bundled Noto Color Emoji font, so it looks the same on every device. The complete font (about 1.9 MB) is downloaded once, on the first page with an emoji, and then cached.', 'axismundi-emoji' ); ?></span>
							</p>
							<p>
								<label>
									<input type="radio" name="<?php echo esc_attr( AXISMUNDI_EMOJI_UNICODE_RENDERING_OPTION ); ?>" value="core" <?php checked( 'core', $rendering ); ?>>
									<?php esc_html_e( 'WordPress fallback only', 'axismundi-emoji' ); ?>
								</label>
								<br><span class="description"><?php esc_html_e( 'This plugin does not touch Unicode emoji, and the bundled font is never loaded.', 'axismundi-emoji' ); ?></span>
							</p>
							<?php if ( ! $has_font ) : ?>
								<p class="description"><?php esc_html_e( 'No emoji font is installed with this copy of the plugin, so both font options currently behave like WordPress fallback only.', 'axismundi-emoji' ); ?></p>
							<?php endif; ?>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'WordPress emoji images', 'axismundi-emoji' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><?php esc_html_e( 'WordPress emoji images', 'axismundi-emoji' ); ?></legend>
							<input type="hidden" name="<?php echo esc_attr( AXISMUNDI_EMOJI_CORE_IMAGE_FALLBACK_OPTION ); ?>" value="0">
							<label>
								<input type="checkbox" name="<?php echo esc_attr( AXISMUNDI_EMOJI_CORE_IMAGE_FALLBACK_OPTION ); ?>" value="1" <?php checked( $fallback ); ?>>
								<?php esc_html_e( 'Replace emoji the browser cannot draw with images from WordPress.org', 'axismundi-emoji' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'This is WordPress\'s own fallback, with images loaded from WordPress.org. Turn it off to make no emoji image requests to WordPress.org from this site\'s pages; an emoji that neither the browser nor the bundled font can draw is then shown however the browser shows it.', 'axismundi-emoji' ); ?></p>
							<p class="description"><?php esc_html_e( 'Feeds and emails are not affected: WordPress replaces emoji with images there on its own, because a reader\'s app is not this site\'s page.', 'axismundi-emoji' ); ?></p>
						</fieldset>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Keep WordPress's emoji detection off this site's pages when its image fallback is turned off.
 *
 * Front end and embeds only. The block editor already removes the detection script itself,
 * other admin screens are the site owner's tools rather than published pages, and the feed and
 * email filters (`wp_staticize_emoji`) are left alone on purpose, as the settings screen says.
 *
 * @return void
 */
function axismundi_emoji_apply_core_image_fallback() : void {
	if ( is_admin() || axismundi_emoji_core_image_fallback_enabled() ) {
		return;
	}
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'embed_head', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
}
add_action( 'init', 'axismundi_emoji_apply_core_image_fallback' );
