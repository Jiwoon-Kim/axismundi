<?php
/**
 * The secondary calendars screen.
 *
 * One screen for every registered calendar system, listed the same way and reached the same way.
 * Korean, Chinese, Hebrew and Islamic are peers here: none of them is the one the plugin is really
 * about with the others bolted beside it.
 *
 * A provider may still render its own section -- the seam is kept for the day one needs a key -- but
 * none does today, because every system here is computed on this server.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the screen.
 *
 * @return void
 */
function axismundi_cal_secondary_menu() : void {
	add_submenu_page(
		'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE,
		__( 'Secondary calendars', 'axismundi-calendar' ),
		__( 'Secondary calendars', 'axismundi-calendar' ),
		'manage_options',
		'ax-calendar-secondary',
		'axismundi_cal_render_secondary_page'
	);
}
add_action( 'admin_menu', 'axismundi_cal_secondary_menu' );

/** @return string The screen's own URL. */
function axismundi_cal_secondary_page_url() : string {
	return admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendar-secondary' );
}

/**
 * The screen.
 *
 * @return void
 */
function axismundi_cal_render_secondary_page() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage this site.', 'axismundi-calendar' ), 403 );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
	$notice = isset( $_GET['ax_cal_notice'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['ax_cal_notice'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
	$error = isset( $_GET['ax_cal_error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['ax_cal_error'] ) ) : '';

	$types = array(
		'lunisolar' => __( 'Lunisolar', 'axismundi-calendar' ),
		'lunar'     => __( 'Lunar', 'axismundi-calendar' ),
		'solar'     => __( 'Solar', 'axismundi-calendar' ),
		'other'     => __( 'Other', 'axismundi-calendar' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Secondary calendars', 'axismundi-calendar' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Other ways of naming a day the calendar already shows. A secondary calendar is not a calendar to subscribe to: it adds no events, and each person turns it on for themselves in the calendar view.', 'axismundi-calendar' ); ?>
		</p>
		<p class="description">
			<?php esc_html_e( 'All of these are computed on this server. None needs a key, an account or any setup.', 'axismundi-calendar' ); ?>
		</p>
		<?php if ( '' !== $notice ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<?php if ( '' !== $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'How the second date is written', 'axismundi-calendar' ); ?></h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'ax_cal_secondary' ); ?>
			<input type="hidden" name="ax_cal_secondary_action" value="format" />
			<fieldset>
				<legend class="screen-reader-text"><?php esc_html_e( 'Second date notation', 'axismundi-calendar' ); ?></legend>
				<label>
					<input type="radio" name="ax_cal_secondary_format" value="numeric" <?php checked( 'numeric', axismundi_cal_secondary_format() ); ?> />
					<?php esc_html_e( 'Numbers', 'axismundi-calendar' ); ?>
					<span class="description"><?php esc_html_e( '7.1 — how Korean and Chinese lunar dates are usually written.', 'axismundi-calendar' ); ?></span>
				</label><br />
				<label>
					<input type="radio" name="ax_cal_secondary_format" value="locale" <?php checked( 'locale', axismundi_cal_secondary_format() ); ?> />
					<?php esc_html_e( 'Calendar names', 'axismundi-calendar' ); ?>
					<span class="description"><?php esc_html_e( 'The month as that calendar names it, in each viewer’s own language. Hebrew and Islamic months have names rather than numbers.', 'axismundi-calendar' ); ?></span>
				</label>
			</fieldset>
			<p class="description"><?php esc_html_e( 'This is notation, not language. Every viewer sees their own locale either way, and the full date is always in the day’s tooltip.', 'axismundi-calendar' ); ?></p>
			<?php submit_button( __( 'Save format', 'axismundi-calendar' ), 'secondary', 'submit', false ); ?>
		</form>

		<?php foreach ( axismundi_cal_calendar_systems() as $system ) : ?>
			<h2><?php echo esc_html( (string) $system['label'] ); ?></h2>
			<table class="widefat striped" style="max-width:52em;margin-bottom:1em;">
				<tbody>
					<tr>
						<th scope="row" style="width:12em;"><?php esc_html_e( 'Identifier', 'axismundi-calendar' ); ?></th>
						<td><code><?php echo esc_html( (string) $system['id'] ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Kind', 'axismundi-calendar' ); ?></th>
						<td><?php echo esc_html( $types[ (string) $system['type'] ] ?? (string) $system['type'] ); ?></td>
					</tr>
					<?php if ( '' !== (string) $system['authority'] ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Dates come from', 'axismundi-calendar' ); ?></th>
							<td><?php echo esc_html( (string) $system['authority'] ); ?></td>
						</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Covers', 'axismundi-calendar' ); ?></th>
						<td>
							<?php
							echo esc_html(
								null === $system['coverage_from'] && null === $system['coverage_to']
									? __( 'Every date', 'axismundi-calendar' )
									: sprintf(
										/* translators: 1: first date, 2: last date. */
										__( '%1$s to %2$s. Outside that range the calendar is unchanged and no second date is shown.', 'axismundi-calendar' ),
										null === $system['coverage_from'] ? '…' : axismundi_cal_absolute_day_to_iso( (int) $system['coverage_from'] ),
										null === $system['coverage_to'] ? '…' : axismundi_cal_absolute_day_to_iso( (int) $system['coverage_to'] )
									)
							);
							?>
						</td>
					</tr>
					<?php if ( '' !== (string) $system['icu_calendar'] ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Unicode calendar', 'axismundi-calendar' ); ?></th>
							<td>
								<code>u-ca-<?php echo esc_html( (string) $system['icu_calendar'] ); ?></code>
								<p class="description">
									<?php esc_html_e( 'The Unicode identifier for this calendar, which is also what computes it here.', 'axismundi-calendar' ); ?>
								</p>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
			<?php
			if ( null !== $system['settings'] ) {
				call_user_func( $system['settings'] );
			}
			?>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Handle nothing, for now.
 *
 * Kept as a seam rather than deleted with the provider that used it. Every system here is computed
 * and needs no configuration, so there is nothing to submit -- but the next authority provider will
 * need somewhere to put its key, and the screen already knows how to give it a section.
 *
 * @return void
 */
function axismundi_cal_secondary_handle_post() : void {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified immediately below.
	$action = isset( $_POST['ax_cal_secondary_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['ax_cal_secondary_action'] ) ) : '';
	if ( '' === $action ) {
		return;
	}
	check_admin_referer( 'ax_cal_secondary' );
	$notice = '';
	if ( 'format' === $action ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$format = isset( $_POST['ax_cal_secondary_format'] ) ? sanitize_key( wp_unslash( (string) $_POST['ax_cal_secondary_format'] ) ) : '';
		axismundi_cal_secondary_format_set( $format );
		$notice = __( 'Secondary date format saved.', 'axismundi-calendar' );
	}
	wp_safe_redirect( add_query_arg( 'ax_cal_notice', rawurlencode( $notice ), axismundi_cal_secondary_page_url() ) );
	exit;
}
add_action( 'admin_init', 'axismundi_cal_secondary_handle_post' );
