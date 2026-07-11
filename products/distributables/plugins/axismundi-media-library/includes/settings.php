<?php
/**
 * Phase 0 admin surface: the relationship-mode toggle and a read-only
 * attachment parent-relationship scan (the migration preview). Nothing here
 * mutates media.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/**
 * Current relationship mode, always one of the known values.
 *
 * @return string 'core' | 'independent'
 */
function axismundi_media_get_mode() : string {
	$mode = (string) get_option( AXISMUNDI_MEDIA_MODE_OPTION, AXISMUNDI_MEDIA_MODE_DEFAULT );
	return in_array( $mode, array( 'core', 'independent' ), true ) ? $mode : AXISMUNDI_MEDIA_MODE_DEFAULT;
}

/**
 * Register the whitelisted mode setting.
 *
 * @return void
 */
function axismundi_media_register_settings() : void {
	register_setting(
		'axismundi_media',
		AXISMUNDI_MEDIA_MODE_OPTION,
		array(
			'type'              => 'string',
			'default'           => AXISMUNDI_MEDIA_MODE_DEFAULT,
			'show_in_rest'      => false,
			'sanitize_callback' => static function ( $value ) : string {
				return in_array( $value, array( 'core', 'independent' ), true ) ? (string) $value : AXISMUNDI_MEDIA_MODE_DEFAULT;
			},
		)
	);
}
add_action( 'admin_init', 'axismundi_media_register_settings' );

/**
 * Add the settings page under Settings.
 *
 * @return void
 */
function axismundi_media_admin_menu() : void {
	add_options_page(
		__( 'Axismundi Media Library', 'axismundi-media-library' ),
		__( 'Media Library (Axismundi)', 'axismundi-media-library' ),
		'manage_options',
		'axismundi-media-library',
		'axismundi_media_render_settings_page'
	);
}
add_action( 'admin_menu', 'axismundi_media_admin_menu' );

/**
 * Read-only scan of attachment -> parent relationships (migration preview).
 * Counts only; no mutation.
 *
 * @return array{total:int,with_parent:int}
 */
function axismundi_media_scan_parents() : array {
	global $wpdb;

	// Read-only, on-demand admin preview counts; no user input; caching is
	// unnecessary for a manually-viewed preview.
	$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'attachment' )
	);
	$with_parent = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_parent > 0", 'attachment' )
	);

	return array(
		'total'       => $total,
		'with_parent' => $with_parent,
	);
}

/**
 * Render the settings page.
 *
 * @return void
 */
function axismundi_media_render_settings_page() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$axismundi_media_mode = axismundi_media_get_mode();
	$axismundi_media_scan = axismundi_media_scan_parents();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Axismundi Media Library', 'axismundi-media-library' ); ?></h1>
		<p><?php esc_html_e( 'Phase 0 — compatibility boundary. Activating this plugin changes no media. The relationship mode below is recorded but does not alter attachment behaviour until Phase 1.', 'axismundi-media-library' ); ?></p>

		<form action="options.php" method="post">
			<?php settings_fields( 'axismundi_media' ); ?>
			<h2><?php esc_html_e( 'Relationship mode', 'axismundi-media-library' ); ?></h2>
			<fieldset>
				<label>
					<input type="radio" name="<?php echo esc_attr( AXISMUNDI_MEDIA_MODE_OPTION ); ?>" value="core" <?php checked( $axismundi_media_mode, 'core' ); ?> />
					<strong><?php esc_html_e( 'Core attached-to', 'axismundi-media-library' ); ?></strong>
					&mdash; <?php esc_html_e( 'Keep stock WordPress attachment relationships.', 'axismundi-media-library' ); ?>
				</label><br />
				<label>
					<input type="radio" name="<?php echo esc_attr( AXISMUNDI_MEDIA_MODE_OPTION ); ?>" value="independent" <?php checked( $axismundi_media_mode, 'independent' ); ?> />
					<strong><?php esc_html_e( 'Independent media', 'axismundi-media-library' ); ?></strong>
					&mdash; <?php esc_html_e( 'New uploads become independent media objects. Behaviour ships in Phase 1.', 'axismundi-media-library' ); ?>
				</label>
			</fieldset>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Relationship scan (read-only)', 'axismundi-media-library' ); ?></h2>
		<p><?php esc_html_e( 'Preview of what a future migration would touch. This scan changes nothing.', 'axismundi-media-library' ); ?></p>
		<table class="widefat striped" style="max-width:520px">
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Total attachments', 'axismundi-media-library' ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $axismundi_media_scan['total'] ) ); ?></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Attachments with a parent post', 'axismundi-media-library' ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $axismundi_media_scan['with_parent'] ) ); ?></td>
				</tr>
			</tbody>
		</table>
		<p class="description">
			<?php
			printf(
				/* translators: %s: number of attachments that have a parent post. */
				esc_html__( 'Enabling Independent mode would eventually detach %s attachment(s) from their parent — but no bulk change ships in 0.0.1 (scan / preview only).', 'axismundi-media-library' ),
				esc_html( number_format_i18n( $axismundi_media_scan['with_parent'] ) )
			);
			?>
		</p>

		<h2><?php esc_html_e( 'Boundary', 'axismundi-media-library' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Disabling these controls (deactivating the plugin or turning off Independent mode) disables Axismundi media visibility and access controls. Original file URLs are not protected by these controls.', 'axismundi-media-library' ); ?>
		</p>
	</div>
	<?php
}
