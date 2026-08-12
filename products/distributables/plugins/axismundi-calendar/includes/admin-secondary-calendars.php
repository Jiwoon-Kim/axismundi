<?php
/**
 * The secondary calendars screen.
 *
 * One screen for every registered calendar system, not one screen named after the first one that
 * shipped. Korean lunisolar is a provider here; Hebrew, Chinese and Islamic would be more, and a
 * page called "Lunar calendar" would have to be renamed the day the second arrives -- and would be
 * wrong twice over when the second is not lunar.
 *
 * Each provider renders its own section, because what a provider needs configured is its own
 * business: KASI needs a service key, and a system computed locally needs nothing at all.
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
		<?php if ( '' !== $notice ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<?php if ( '' !== $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

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
									<?php esc_html_e( 'Recorded for formatting and interoperability only. It is not where these dates come from, and it has not been checked against the authority above year by year.', 'axismundi-calendar' ); ?>
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
 * How many months of a lunisolar system are stored, and what they span.
 *
 * @param string $system System id.
 * @return array{months:int,from:string,to:string}
 */
function axismundi_cal_lunar_coverage( string $system = AXISMUNDI_CAL_KOREAN_LUNISOLAR ) : array {
	global $wpdb;
	$empty = array( 'months' => 0, 'from' => '', 'to' => '' );
	if ( ! axismundi_cal_ready() ) {
		return $empty;
	}
	$table = axismundi_cal_lunar_months_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT COUNT(*) AS months, MIN(start_absolute_day) AS first_day, MAX(start_absolute_day + days - 1) AS last_day
			 FROM {$table} WHERE system = %s",
			$system
		),
		ARRAY_A
	);
	if ( ! is_array( $row ) || 0 === (int) $row['months'] ) {
		return $empty;
	}
	return array(
		'months' => (int) $row['months'],
		'from'   => axismundi_cal_absolute_day_to_iso( (int) $row['first_day'] ),
		'to'     => axismundi_cal_absolute_day_to_iso( (int) $row['last_day'] ),
	);
}

/**
 * The Korean provider's own section: its key, and what has been fetched with it.
 *
 * @return void
 */
function axismundi_cal_render_kasi_settings() : void {
	$coverage = axismundi_cal_lunar_coverage();
	$has_key  = '' !== axismundi_cal_kasi_key();
	?>
	<h3><?php esc_html_e( 'Service key', 'axismundi-calendar' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'From 공공데이터포털 (data.go.kr): 한국천문연구원_음양력 정보. The key is stored encrypted and is never sent to the browser or included in a page.', 'axismundi-calendar' ); ?>
	</p>
	<?php if ( axismundi_cal_kasi_key_is_constant() ) : ?>
		<p><strong><?php esc_html_e( 'The key is defined in wp-config.php, so there is nothing to store here.', 'axismundi-calendar' ); ?></strong></p>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( axismundi_cal_secondary_page_url() ); ?>">
			<?php wp_nonce_field( 'ax_cal_secondary' ); ?>
			<input type="hidden" name="ax_cal_secondary_action" value="kasi-key" />
			<?php /* Write-only. What is stored is never rendered back: a field that shows the key turns every page view into a chance to read it over somebody's shoulder, and "saved" is the only thing the screen needs to say. */ ?>
			<input type="password" class="regular-text" name="ax_cal_kasi_key" value="" autocomplete="off" spellcheck="false"
				aria-label="<?php esc_attr_e( 'KASI service key', 'axismundi-calendar' ); ?>"
				placeholder="<?php echo esc_attr( $has_key ? __( 'A key is stored. Type a new one to replace it.', 'axismundi-calendar' ) : __( 'Paste the key from data.go.kr', 'axismundi-calendar' ) ); ?>" />
			<p class="description">
				<?php echo esc_html( $has_key ? __( 'A key is stored. Leave this empty and save to remove it.', 'axismundi-calendar' ) : __( 'No key is stored yet.', 'axismundi-calendar' ) ); ?>
			</p>
			<?php submit_button( __( 'Save key', 'axismundi-calendar' ), 'secondary' ); ?>
		</form>
	<?php endif; ?>

	<h3><?php esc_html_e( 'Stored months', 'axismundi-calendar' ); ?></h3>
	<?php if ( 0 === $coverage['months'] ) : ?>
		<p><?php esc_html_e( 'Nothing has been fetched yet, so no day has a second date.', 'axismundi-calendar' ); ?></p>
	<?php else : ?>
		<p>
			<?php
			printf(
				/* translators: 1: number of months, 2: first date, 3: last date. */
				esc_html__( '%1$d lunar months, covering %2$s to %3$s.', 'axismundi-calendar' ),
				(int) $coverage['months'],
				esc_html( $coverage['from'] ),
				esc_html( $coverage['to'] )
			);
			?>
		</p>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( axismundi_cal_secondary_page_url() ); ?>">
		<?php wp_nonce_field( 'ax_cal_secondary' ); ?>
		<input type="hidden" name="ax_cal_secondary_action" value="kasi-fetch" />
		<label for="ax_cal_from_year"><?php esc_html_e( 'Years', 'axismundi-calendar' ); ?></label>
		<input type="number" id="ax_cal_from_year" name="ax_cal_from_year" value="<?php echo esc_attr( (string) (int) gmdate( 'Y' ) ); ?>" class="small-text" />
		&ndash;
		<input type="number" name="ax_cal_to_year" value="<?php echo esc_attr( (string) ( (int) gmdate( 'Y' ) + 1 ) ); ?>" class="small-text"
			aria-label="<?php esc_attr_e( 'Last year to fetch', 'axismundi-calendar' ); ?>" />
		<p class="description"><?php esc_html_e( 'Twelve requests a year. Fetching a year already stored corrects it rather than duplicating it.', 'axismundi-calendar' ); ?></p>
		<?php submit_button( __( 'Fetch months', 'axismundi-calendar' ), 'secondary', 'submit', true, $has_key ? array() : array( 'disabled' => 'disabled' ) ); ?>
	</form>
	<?php
}

/**
 * Handle what the sections submit.
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
	$error  = '';

	if ( 'kasi-key' === $action ) {
		// Not sanitised into oblivion: this is an opaque credential, and stripping characters out of
		// it would store something that is not the key while reporting success.
		$key = isset( $_POST['ax_cal_kasi_key'] ) ? trim( (string) wp_unslash( $_POST['ax_cal_kasi_key'] ) ) : '';
		$set = axismundi_cal_kasi_key_set( $key );
		if ( is_wp_error( $set ) ) {
			$error = $set->get_error_message();
		} else {
			$notice = '' === $key ? __( 'The key was removed.', 'axismundi-calendar' ) : __( 'The key was saved.', 'axismundi-calendar' );
		}
	}

	if ( 'kasi-fetch' === $action ) {
		$from = isset( $_POST['ax_cal_from_year'] ) ? (int) $_POST['ax_cal_from_year'] : 0;
		$to   = isset( $_POST['ax_cal_to_year'] ) ? (int) $_POST['ax_cal_to_year'] : 0;
		if ( $from < 1 || $to < $from ) {
			$error = __( 'Give a year, and an end year not before it.', 'axismundi-calendar' );
		} elseif ( $to - $from > 5 ) {
			// Bounded because this is twelve requests a year in one page load. A century is a job for
			// repeated runs, not for one request that times out halfway and leaves nothing said.
			$error = __( 'Fetch at most six years at a time.', 'axismundi-calendar' );
		} else {
			$result = axismundi_cal_kasi_materialise_years( $from, $to );
			if ( '' !== $result['error'] ) {
				$error = $result['error'];
			}
			$notice = sprintf(
				/* translators: 1: number of lunar months, 2: number of requests. */
				__( 'Stored %1$d lunar months from %2$d requests.', 'axismundi-calendar' ),
				$result['stored'],
				$result['months']
			);
		}
	}

	wp_safe_redirect(
		add_query_arg(
			array( 'ax_cal_notice' => rawurlencode( $notice ), 'ax_cal_error' => rawurlencode( $error ) ),
			axismundi_cal_secondary_page_url()
		)
	);
	exit;
}
add_action( 'admin_init', 'axismundi_cal_secondary_handle_post' );
