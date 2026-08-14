<?php
/**
 * Maintaining the entries of a dataset calendar.
 *
 * Its own screen, and deliberately not the Event editor. What is edited here is a row in a table:
 * a civil date, a name, a classification, and whether the year it belongs to has been checked. What
 * the Event editor edits is a post with an author, a schedule, a recurrence rule and a federated
 * identity. Sharing one screen between them would mean every field on it is wrong for half the
 * things it edits.
 *
 * Organised by year, because that is the unit these are reviewed in. Holiday dates move -- substitute
 * days, temporary holidays, election days -- so a year is checked as a whole against whatever the
 * law actually said, and is worth nothing to a reader until it has been.
 *
 * Nothing here decides who may maintain a dataset. `manage_items` answers that, and the writers
 * refuse the same things independently, so a screen rendered by mistake still cannot write.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dataset Calendars the current user may maintain.
 *
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_manageable_datasets() : array {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_calendars_table();
	/*
	 * The `source` clause narrows the rows the capability check has to walk; it is not what excludes
	 * an ordinary Calendar. `manage_items` is false for one of those whatever this query returns, and
	 * removing the clause changes nothing but how many rows are examined. Two filters look like
	 * belt-and-braces and are not: only the second one is a rule.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- inventory over this plugin's own table.
	$rows = (array) $wpdb->get_results( "SELECT * FROM {$table} WHERE kind = 'system' OR source IN ('manual','import') ORDER BY name ASC", ARRAY_A );
	return array_values( array_filter( $rows, static fn( array $row ) : bool => axismundi_cal_calendar_can( $row, 'manage_items' ) ) );
}

/**
 * Register the screen.
 *
 * @return void
 */
function axismundi_cal_system_items_menu() : void {
	add_submenu_page(
		'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE,
		__( 'System calendars', 'axismundi-calendar' ),
		__( 'System calendars', 'axismundi-calendar' ),
		'publish_posts',
		'ax-calendar-system',
		'axismundi_cal_render_system_items_page'
	);
}
add_action( 'admin_menu', 'axismundi_cal_system_items_menu', 11 );

/**
 * Load review controls only where they are used.
 *
 * @param string $hook Current admin page.
 * @return void
 */
function axismundi_cal_enqueue_system_item_controls( string $hook ) : void {
	if ( ! str_contains( $hook, 'ax-calendar-system' ) ) {
		return;
	}
	$plugin = dirname( __DIR__ ) . '/axismundi-calendar.php';
	$script = dirname( __DIR__ ) . '/assets/admin/system-items.js';
	if ( ! file_exists( $script ) ) {
		return;
	}
	wp_enqueue_script(
		'axismundi-calendar-system-items',
		plugins_url( 'assets/admin/system-items.js', $plugin ),
		array(),
		AXISMUNDI_CAL_VERSION . '-' . (string) filemtime( $script ),
		true
	);
}
add_action( 'admin_enqueue_scripts', 'axismundi_cal_enqueue_system_item_controls' );

/**
 * Create a maintained calendar.
 *
 * Its own path rather than the ordinary Calendar form, because almost nothing that form asks applies
 * here: there is no Actor to own this, no sharing to decide, and its contents are not Events.
 * Sending somebody to that screen and asking them to pick a content type afterwards would be
 * offering a set of choices where most are wrong.
 *
 * @return void
 */
function axismundi_cal_handle_system_calendar_form() : void {
	check_admin_referer( 'ax_cal_create_system' );
	if ( ! axismundi_cal_can_manage_all_calendars() ) {
		wp_die( esc_html__( 'You are not allowed to maintain this site calendars.', 'axismundi-calendar' ), 403 );
	}
	$base = admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendar-system' );

	$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '';
	$provider = isset( $_POST['system_provider'] ) ? sanitize_key( wp_unslash( (string) $_POST['system_provider'] ) ) : '';
	/*
	 * Checked here as well as disabled on the form, because a disabled radio is a hint to a person and
	 * nothing to a request. Re-enabling one in a browser console is the ordinary way somebody ends up
	 * with a second, empty Moon phases calendar that nothing fills.
	 */
	if ( '' !== axismundi_cal_system_provider_unavailable_reason( $provider ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', 'ax_cal_provider_unavailable', $base ) );
		exit;
	}
	$created = axismundi_cal_calendar_save(
		array(
			'kind'        => 'system',
			'source'      => 'manual',
			'name'        => $name,
			'slug'        => isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( (string) $_POST['slug'] ) ) : sanitize_title( $name ),
			'system_provider' => $provider,
			'provider_config' => axismundi_cal_read_provider_config_post(),
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['description'] ) ) : '',
			'timezone'    => isset( $_POST['timezone'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['timezone'] ) ) : axismundi_cal_default_calendar_timezone(),
		)
	);
	if ( is_wp_error( $created ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $created->get_error_code() ), $base ) );
		exit;
	}
	// Straight into the entries, since a maintained calendar with none is not yet anything.
	wp_safe_redirect( add_query_arg( array( 'calendar' => (int) $created, 'ax_cal_notice' => 'calendar_created' ), $base ) );
	exit;
}
add_action( 'admin_post_ax_cal_create_system_calendar', 'axismundi_cal_handle_system_calendar_form' );

/**
 * Read the provider settings a form submitted.
 *
 * `wp_dropdown_languages()` submits an empty string for English (United States), because core has no
 * translation file for the language it is written in and uses '' to mean exactly that. Passed
 * straight through, the writer sees a holiday calendar with no language and refuses it -- so
 * choosing English was the one choice on the list that could not be made.
 *
 * Named here rather than in the writer: '' meaning en_US is a convention of core's control, and an
 * API caller sending no language is still sending no language.
 *
 * @return array<string,string>
 */
function axismundi_cal_read_provider_config_post() : array {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify before reading.
	$config = isset( $_POST['provider_config'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['provider_config'] ) ) : array();
	if ( array_key_exists( 'source_locale', $config ) && '' === trim( (string) $config['source_locale'] ) ) {
		$config['source_locale'] = 'en_US';
	}
	return $config;
}

/**
 * Write or remove one entry.
 *
 * @return void
 */
function axismundi_cal_handle_system_item_form() : void {
	$calendar_id = isset( $_POST['calendar_id'] ) ? absint( wp_unslash( $_POST['calendar_id'] ) ) : 0;
	check_admin_referer( 'ax_cal_system_item_' . $calendar_id );
	$calendar = $calendar_id > 0 ? axismundi_cal_calendar_get( $calendar_id ) : null;
	if ( ! axismundi_cal_calendar_can( $calendar, 'manage_items' ) ) {
		wp_die( esc_html__( 'You are not allowed to maintain that calendar.', 'axismundi-calendar' ), 403 );
	}
	$base    = add_query_arg( 'calendar', $calendar_id, admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendar-system' ) );
	$item_id = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
	$action  = isset( $_POST['ax_cal_item_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['ax_cal_item_action'] ) ) : 'save';

	/*
	 * An entry belongs to one Calendar for its whole life. Checked rather than assumed, because the
	 * id arrives in the same form as the Calendar and a mismatched pair would otherwise edit somebody
	 * else's dataset with this Calendar's permission.
	 */
	if ( $item_id > 0 ) {
		$existing = axismundi_cal_system_item_get( $item_id );
		if ( ! is_array( $existing ) || (int) $existing['calendar_id'] !== $calendar_id ) {
			wp_safe_redirect( add_query_arg( 'ax_cal_error', 'ax_cal_item_missing', $base ) );
			exit;
		}
	}

	if ( 'delete' === $action && $item_id > 0 ) {
		axismundi_cal_system_item_delete( $item_id );
		wp_safe_redirect( add_query_arg( 'ax_cal_notice', 'item_deleted', $base ) );
		exit;
	}

	$fields = array(
		'title'        => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['title'] ) ) : '',
		'description'  => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['description'] ) ) : '',
		'start_date'   => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['start_date'] ) ) : '',
		'end_date'     => isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['end_date'] ) ) : '',
		'categories'   => isset( $_POST['categories'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['categories'] ) ) : array(),
		'transparency' => isset( $_POST['transparency'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['transparency'] ) ) : 'TRANSPARENT',
		'status'       => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( (string) $_POST['status'] ) ) : 'draft',
	);
	if ( isset( $_POST['batch_year'] ) && '' !== $_POST['batch_year'] ) {
		$fields['batch_year'] = absint( wp_unslash( $_POST['batch_year'] ) );
	}

	$saved = axismundi_cal_system_item_save( $calendar_id, $fields, $item_id );
	if ( is_wp_error( $saved ) ) {
		wp_safe_redirect( add_query_arg( array( 'ax_cal_error' => rawurlencode( $saved->get_error_code() ), 'item' => $item_id ), $base ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_cal_notice', $item_id > 0 ? 'item_updated' : 'item_added', $base ) );
	exit;
}
add_action( 'admin_post_ax_cal_save_system_item', 'axismundi_cal_handle_system_item_form' );

/**
 * Save classifications and publish the checked entries of a holiday review.
 *
 * @return void
 */
function axismundi_cal_handle_holiday_review() : void {
	$calendar_id = isset( $_POST['calendar_id'] ) ? absint( wp_unslash( $_POST['calendar_id'] ) ) : 0;
	check_admin_referer( 'ax_cal_holiday_review_' . $calendar_id );
	$calendar = $calendar_id > 0 ? axismundi_cal_calendar_get( $calendar_id ) : null;
	if ( ! axismundi_cal_calendar_can( $calendar, 'manage_items' ) ) {
		wp_die( esc_html__( 'You are not allowed to maintain that calendar.', 'axismundi-calendar' ), 403 );
	}
	$year = isset( $_POST['year'] ) ? absint( wp_unslash( $_POST['year'] ) ) : 0;
	$base = add_query_arg( array( 'calendar' => $calendar_id, 'year' => $year ), admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendar-system' ) );
	$reviews = isset( $_POST['review'] ) && is_array( $_POST['review'] ) ? wp_unslash( $_POST['review'] ) : array();
	$selected = isset( $_POST['selected_items'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['selected_items'] ) ) : array();
	$selected = array_values( array_unique( array_filter( $selected ) ) );
	$action   = isset( $_POST['holiday_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['holiday_action'] ) ) : 'save';
	if ( 'observance' === $action ) {
		$result = axismundi_cal_bulk_classify_holiday_items( $calendar_id, $selected, 'OBSERVANCE', true );
	} elseif ( 'public_holiday' === $action ) {
		$result = axismundi_cal_bulk_classify_holiday_items( $calendar_id, $selected, 'PUBLIC-HOLIDAY', true );
	} elseif ( 'publish' === $action ) {
		$result = axismundi_cal_review_holiday_items( $calendar_id, array_intersect_key( $reviews, array_fill_keys( $selected, true ) ), $selected );
	} else {
		$result = axismundi_cal_review_holiday_items( $calendar_id, $reviews, array() );
	}
	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $result->get_error_code() ), $base ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_cal_notice', 'holiday_reviewed', $base ) );
	exit;
}
add_action( 'admin_post_ax_cal_review_holidays', 'axismundi_cal_handle_holiday_review' );

/**
 * Wording for one outcome on this screen.
 *
 * @param string $code Notice or error key.
 * @return string
 */
function axismundi_cal_system_item_message( string $code ) : string {
	switch ( $code ) {
		case 'calendar_created':
			return __( 'Calendar created. Add its entries below.', 'axismundi-calendar' );
		case 'ax_cal_system_provider':
			return __( 'Choose what kind of dataset this calendar holds.', 'axismundi-calendar' );
		case 'ax_cal_provider_unavailable':
			return __( 'That kind of calendar cannot be created by hand yet.', 'axismundi-calendar' );
		case 'managed_saved':
			return __( 'Computed calendars saved.', 'axismundi-calendar' );
		case 'ax_cal_provider_region':
			return __( 'A holiday calendar needs a two-letter country or region code, such as KR.', 'axismundi-calendar' );
		case 'ax_cal_provider_locale':
			return __( 'A holiday calendar needs the language its names are written in, such as ko-KR.', 'axismundi-calendar' );
		case 'ax_cal_system_categories':
			return __( 'Choose at least one category for this system calendar.', 'axismundi-calendar' );
		case 'ax_cal_slug_taken':
			return __( 'Another calendar already uses that slug.', 'axismundi-calendar' );
		case 'ax_cal_name':
			return __( 'A calendar needs a name.', 'axismundi-calendar' );
		case 'ax_cal_timezone':
			return __( 'A calendar needs a named IANA timezone such as Asia/Seoul.', 'axismundi-calendar' );
		case 'catalog_joined':
			return __( 'Dataset saved. Entries can now be linked to the holidays in it.', 'axismundi-calendar' );
		case 'item_linked':
			return __( 'Linked.', 'axismundi-calendar' );
		case 'item_unlinked':
			return __( 'Unlinked.', 'axismundi-calendar' );
		case 'holiday_created':
			return __( 'Holiday created from that entry, with its classification.', 'axismundi-calendar' );
		case 'ax_cal_concept_catalog':
			return __( 'Join this calendar to a dataset before creating holidays in it.', 'axismundi-calendar' );
		case 'ax_cal_catalog_jurisdiction':
			return __( 'A dataset covers a country or region, which this calendar has not been given.', 'axismundi-calendar' );
		case 'imported':
			return __( 'Imported as drafts. Classify them and mark the year reviewed to publish it.', 'axismundi-calendar' );
		case 'holiday_reviewed':
			return __( 'Holiday review saved.', 'axismundi-calendar' );
		case 'ax_cal_holiday_selection':
			return __( 'Choose at least one entry first.', 'axismundi-calendar' );
		case 'ax_cal_holiday_category':
			return __( 'Classify every entry before publishing it.', 'axismundi-calendar' );
		case 'ax_cal_import_fetch':
			return __( 'That address could not be read.', 'axismundi-calendar' );
		case 'ax_cal_import_parse':
			return __( 'That address returned something that is not an iCalendar document.', 'axismundi-calendar' );
		case 'ax_cal_import_expired':
			return __( 'That import waited too long. Read the address again.', 'axismundi-calendar' );
		case 'ax_cal_import_no_years':
			return __( 'Choose at least one year to import.', 'axismundi-calendar' );
		case 'ax_cal_source_url':
		case 'ax_cal_source_private':
			return __( 'That address cannot be read from this server.', 'axismundi-calendar' );
		case 'item_added':
			return __( 'Entry added.', 'axismundi-calendar' );
		case 'item_updated':
			return __( 'Entry saved.', 'axismundi-calendar' );
		case 'item_deleted':
			return __( 'Entry removed.', 'axismundi-calendar' );
		case 'ax_cal_item_title':
			return __( 'An entry needs a name.', 'axismundi-calendar' );
		case 'ax_cal_item_date':
			return __( 'An entry needs a date, written as YYYY-MM-DD.', 'axismundi-calendar' );
		case 'ax_cal_item_range':
			return __( 'An entry ends the day after it starts, at the earliest. The end date is the first day it no longer covers.', 'axismundi-calendar' );
		case 'ax_cal_item_status':
			return __( 'An entry is either a draft or published.', 'axismundi-calendar' );
		case 'ax_cal_item_missing':
			return __( 'That entry is not on this calendar.', 'axismundi-calendar' );
		case 'ax_cal_not_dataset':
			return __( 'Only a maintained calendar holds entries like these.', 'axismundi-calendar' );
	}
	return __( 'The entry could not be saved.', 'axismundi-calendar' );
}

/**
 * The screen.
 *
 * @return void
 */
function axismundi_cal_render_system_items_page() : void {
	if ( ! axismundi_cal_can_manage_calendars() ) {
		wp_die( esc_html__( 'You are not allowed to manage calendars.', 'axismundi-calendar' ), 403 );
	}
	$base      = admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendar-system' );
	$datasets  = axismundi_cal_manageable_datasets();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen selection.
	$chosen_id = isset( $_GET['calendar'] ) ? absint( wp_unslash( $_GET['calendar'] ) ) : 0;
	$chosen    = null;
	foreach ( $datasets as $dataset ) {
		if ( (int) $dataset['id'] === $chosen_id ) {
			$chosen = $dataset;
		}
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen selection.
	$notice = isset( $_GET['ax_cal_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['ax_cal_notice'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen selection.
	$error = isset( $_GET['ax_cal_error'] ) ? sanitize_key( wp_unslash( (string) $_GET['ax_cal_error'] ) ) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'System calendars', 'axismundi-calendar' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Calendars this site maintains as data rather than as events somebody wrote: public holidays, observances, moon phases. Entries are whole days, they belong to nobody, and they are reviewed a year at a time.', 'axismundi-calendar' ); ?>
		</p>

		<?php if ( '' !== $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( axismundi_cal_system_item_message( $notice ) ); ?></p></div>
		<?php endif; ?>
		<?php if ( '' !== $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( axismundi_cal_system_item_message( $error ) ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! empty( $datasets ) ) : ?>
		<h2><?php esc_html_e( 'Maintained calendars', 'axismundi-calendar' ); ?></h2>
		<ul class="subsubsub" style="float:none;">
			<?php foreach ( $datasets as $dataset ) : ?>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'calendar', (int) $dataset['id'], $base ) ); ?>"
						<?php echo (int) $dataset['id'] === $chosen_id ? 'class="current"' : ''; ?>>
						<?php echo esc_html( axismundi_cal_calendar_display_name( $dataset ) ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php endif; ?>

		<?php if ( is_array( $chosen ) ) : ?>
			<?php axismundi_cal_render_system_item_editor( $chosen, $base ); ?>
		<?php elseif ( ! empty( $datasets ) ) : ?>
			<p><?php esc_html_e( 'Choose a calendar to see the entries on it.', 'axismundi-calendar' ); ?></p>
		<?php endif; ?>

		<?php if ( axismundi_cal_can_manage_all_calendars() ) : ?>
			<?php axismundi_cal_render_managed_calendar_settings( $base ); ?>
			<?php axismundi_cal_render_system_calendar_form(); ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * The form that makes one.
 *
 * @return void
 */
function axismundi_cal_render_system_calendar_form() : void {
	?>
	<h2><?php esc_html_e( 'New maintained calendar', 'axismundi-calendar' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Made here rather than on the Calendars screen: this one belongs to the site rather than to an Actor, it is readable by everyone, and there is no sharing to decide.', 'axismundi-calendar' ); ?>
	</p>
	<form id="ax-cal-holiday-review" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ax_cal_create_system_calendar">
		<?php wp_nonce_field( 'ax_cal_create_system' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ax-cal-system-name"><?php esc_html_e( 'Name', 'axismundi-calendar' ); ?></label></th>
				<td>
					<input name="name" id="ax-cal-system-name" type="text" class="regular-text" required placeholder="<?php esc_attr_e( 'Public holidays in South Korea', 'axismundi-calendar' ); ?>">
					<p class="description"><?php esc_html_e( 'In the language this site is read in. It can be changed later without breaking anything.', 'axismundi-calendar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'What it holds', 'axismundi-calendar' ); ?></th>
				<td>
					<?php foreach ( AXISMUNDI_CAL_SYSTEM_PROVIDERS as $provider_key ) : ?>
						<?php
						$provider_labels = axismundi_cal_system_provider_labels( $provider_key );
						/*
						 * Shown rather than hidden, including the ones that cannot be picked. The list is what
						 * tells somebody what a system calendar can be, and a kind that silently does not
						 * appear reads as one that does not exist -- which would make the astronomy calendars
						 * already on the site look like they came from somewhere else.
						 */
						$provider_blocked = axismundi_cal_system_provider_unavailable_reason( $provider_key );
						?>
						<p>
							<label>
								<input type="radio" name="system_provider" value="<?php echo esc_attr( $provider_key ); ?>"
									<?php checked( 'holiday', $provider_key ); ?>
									<?php disabled( '' !== $provider_blocked ); ?>>
								<strong><?php echo esc_html( $provider_labels['label'] ); ?></strong>
							</label>
							<br>
							<span class="description" style="margin-inline-start:1.8em;"><?php echo esc_html( $provider_labels['description'] ); ?></span>
							<?php if ( '' !== $provider_blocked ) : ?>
								<br>
								<em class="description" style="margin-inline-start:1.8em;"><?php echo esc_html( $provider_blocked ); ?></em>
							<?php endif; ?>
						</p>
					<?php endforeach; ?>
					<p class="description">
						<?php esc_html_e( 'One choice, and fixed afterwards. It decides how entries get here and what they mean, not just where the calendar is listed.', 'axismundi-calendar' ); ?>
					</p>
				</td>
			</tr>
			<?php foreach ( axismundi_cal_system_provider_config_fields( 'holiday' ) as $config_key => $config_field ) : ?>
				<tr>
					<th scope="row">
						<label for="ax-cal-config-<?php echo esc_attr( $config_key ); ?>"><?php echo esc_html( $config_field['label'] ); ?></label>
					</th>
					<td>
						<?php if ( 'source_locale' === $config_key ) : ?>
							<?php
							/*
							 * Core's own list, so the language of a dataset is one of the languages this
							 * site actually has. Typed by hand it is a spelling test whose wrong answers
							 * are stored and only noticed when a translation link fails to match.
							 */
							wp_dropdown_languages(
								array(
									'id'                          => 'ax-cal-config-source_locale',
									'name'                        => 'provider_config[source_locale]',
									'selected'                    => get_locale(),
									'languages'                   => get_available_languages(),
									'show_available_translations' => false,
								)
							);
							?>
						<?php else : ?>
							<input name="provider_config[<?php echo esc_attr( $config_key ); ?>]" id="ax-cal-config-<?php echo esc_attr( $config_key ); ?>"
								type="text" class="regular-text" placeholder="KR">
						<?php endif; ?>
						<p class="description"><?php echo esc_html( $config_field['description'] ); ?></p>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><label for="ax-cal-system-timezone"><?php esc_html_e( 'Timezone', 'axismundi-calendar' ); ?></label></th>
				<td>
					<select name="timezone" id="ax-cal-system-timezone">
						<?php echo wp_timezone_choice( axismundi_cal_default_calendar_timezone(), get_user_locale() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core option markup. ?>
					</select>
					<p class="description"><?php esc_html_e( 'Barely used here, since entries are whole days that fall on the same date everywhere. It is what the feed declares itself in.', 'axismundi-calendar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ax-cal-system-description"><?php esc_html_e( 'Description', 'axismundi-calendar' ); ?></label></th>
				<td><textarea name="description" id="ax-cal-system-description" rows="2" class="large-text"></textarea></td>
			</tr>
		</table>
		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Create calendar', 'axismundi-calendar' ); ?></button>
		</p>
	</form>
	<?php
}

/**
 * The entries of one dataset Calendar, and the form that writes them.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @param string              $base     Screen URL.
 * @return void
 */
function axismundi_cal_render_system_item_editor( array $calendar, string $base ) : void {
	$calendar_id = (int) $calendar['id'];
	$years       = axismundi_cal_system_item_years( $calendar_id );
	$this_year   = (int) gmdate( 'Y' );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen selection.
	$year = isset( $_GET['year'] ) ? absint( wp_unslash( $_GET['year'] ) ) : 0;
	if ( $year <= 0 ) {
		$year = array() !== $years ? (int) $years[ count( $years ) - 1 ]['year'] : $this_year;
	}
	// Drafts included: this screen exists for the people who review them, and a review screen that
	// hides what needs reviewing is the one thing it must not be.
	$items = axismundi_cal_system_items_in_range( $calendar_id, $year . '-01-01', ( $year + 1 ) . '-01-01', array(), true );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen selection.
	$editing = isset( $_GET['item'] ) ? absint( wp_unslash( $_GET['item'] ) ) : 0;
	$current = $editing > 0 ? axismundi_cal_system_item_get( $editing ) : null;
	if ( is_array( $current ) && (int) $current['calendar_id'] !== $calendar_id ) {
		$current = null;
	}
	$selected = axismundi_cal_normalize_categories( is_array( $current ) ? (string) $current['categories'] : '' );
	?>
	<h2><?php echo esc_html( axismundi_cal_calendar_display_name( $calendar ) ); ?></h2>

	<p>
		<?php esc_html_e( 'Year:', 'axismundi-calendar' ); ?>
		<?php if ( count( $years ) > 8 ) : ?>
			<?php
			/*
			 * A holiday feed carries a decade or more, and twelve year links wrap into a paragraph
			 * nobody reads. Said as a span, with the year in view and its neighbours reachable.
			 */
			$first = (int) $years[0]['year'];
			$last  = (int) $years[ count( $years ) - 1 ]['year'];
			?>
			<?php echo esc_html( sprintf( /* translators: 1: first year, 2: last year. */ __( '%1$d to %2$d', 'axismundi-calendar' ), $first, $last ) ); ?>
			&mdash;
			<?php foreach ( array( $year - 1, $year, $year + 1 ) as $near ) : ?>
				<?php if ( $near >= $first && $near <= $last ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'calendar' => $calendar_id, 'year' => $near ), $base ) ); ?>"
						<?php echo $near === $year ? 'class="current"' : ''; ?>><?php echo esc_html( (string) $near ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
			<label class="screen-reader-text" for="ax-cal-year-jump"><?php esc_html_e( 'Go to year', 'axismundi-calendar' ); ?></label>
			<select id="ax-cal-year-jump" onchange="if(this.value){window.location=this.value;}">
				<?php foreach ( $years as $summary ) : ?>
					<option value="<?php echo esc_url( add_query_arg( array( 'calendar' => $calendar_id, 'year' => $summary['year'] ), $base ) ); ?>" <?php selected( (int) $summary['year'], $year ); ?>>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: year, 2: published entries, 3: total entries. */
								__( '%1$d (%2$d of %3$d reviewed)', 'axismundi-calendar' ),
								$summary['year'],
								$summary['published'],
								$summary['total']
							)
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>
		<?php else : ?>
		<?php foreach ( $years as $summary ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array( 'calendar' => $calendar_id, 'year' => $summary['year'] ), $base ) ); ?>">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: year, 2: published entries, 3: total entries. */
						__( '%1$d (%2$d of %3$d reviewed)', 'axismundi-calendar' ),
						$summary['year'],
						$summary['published'],
						$summary['total']
					)
				);
				?>
			</a>
		<?php endforeach; ?>
		<?php endif; ?>
		<?php if ( array() === $years ) : ?>
			<?php echo esc_html( (string) $year ); ?>
		<?php endif; ?>
	</p>

	<?php $holiday_review = 'holiday' === axismundi_cal_system_provider( $calendar ); ?>
	<?php if ( $holiday_review ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ax_cal_review_holidays">
		<input type="hidden" name="calendar_id" value="<?php echo esc_attr( (string) $calendar_id ); ?>">
		<input type="hidden" name="year" value="<?php echo esc_attr( (string) $year ); ?>">
		<?php wp_nonce_field( 'ax_cal_holiday_review_' . $calendar_id ); ?>
	<?php endif; ?>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<?php if ( $holiday_review ) : ?>
					<th scope="col"><label class="screen-reader-text" for="ax-cal-select-all-holidays"><?php esc_html_e( 'Select all entries', 'axismundi-calendar' ); ?></label><input id="ax-cal-select-all-holidays" type="checkbox" onchange="window.axismundiCalendarSystemItems.toggleAll(this, this.form)"></th>
				<?php endif; ?>
				<th scope="col"><?php esc_html_e( 'Date', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Name', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Categories', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Reviewed', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Source', 'axismundi-calendar' ); ?></th>
				<?php if ( ! $holiday_review ) : ?>
					<th scope="col"><?php esc_html_e( 'Remove', 'axismundi-calendar' ); ?></th>
				<?php endif; ?>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $items ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'Nothing for this year yet.', 'axismundi-calendar' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $items as $item ) : ?>
				<?php
				$item_categories = axismundi_cal_normalize_categories( (string) $item['categories'] );
				$class_value     = in_array( 'PUBLIC-HOLIDAY', $item_categories, true ) ? 'PUBLIC-HOLIDAY' : ( in_array( 'OBSERVANCE', $item_categories, true ) ? 'OBSERVANCE' : '' );
				$occurrence      = axismundi_cal_holiday_occurrence_get( (int) $item['holiday_occurrence_id'] );
				?>
				<tr>
					<?php if ( $holiday_review ) : ?>
						<td><input class="ax-cal-holiday-selection" data-draft="<?php echo esc_attr( 'published' === (string) $item['status'] ? '0' : '1' ); ?>" type="checkbox" name="selected_items[]" value="<?php echo esc_attr( (string) $item['id'] ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Select %s', 'axismundi-calendar' ), axismundi_cal_item_display_name( $item ) ) ); ?>" onchange="window.axismundiCalendarSystemItems.syncAll(this.form)"></td>
					<?php endif; ?>
					<td>
						<?php
						/*
						 * A whole day has a date and a moment does not, so the moment is shown as what it
						 * actually is. Printing `start_date` for both left this column empty for every
						 * computed row -- the column was reading a field that is NULL by design, which looks
						 * like missing data rather than a different shape of entry.
						 *
						 * UTC, and labelled. A moon phase at 00:30Z is the 28th in Seoul and the 27th in Los
						 * Angeles, so rendering it in the site's zone would print one reading of it as though
						 * it were the fact. The grid is where a reader's own day is worked out; this table is
						 * the stored row.
						 */
						if ( AXISMUNDI_CAL_TEMPORAL_INSTANT === (string) $item['temporal_kind'] ) :
							?>
							<code><?php echo esc_html( substr( (string) $item['start_utc'], 0, 16 ) ); ?></code>
							<span class="description">UTC</span>
						<?php else : ?>
							<code><?php echo esc_html( (string) $item['start_date'] ); ?></code>
						<?php endif; ?>
					</td>
					<td>
						<strong>
							<a href="<?php echo esc_url( add_query_arg( array( 'calendar' => $calendar_id, 'year' => $year, 'item' => (int) $item['id'] ), $base ) ); ?>">
								<?php echo esc_html( axismundi_cal_item_display_name( $item ) ); ?>
							</a>
						</strong>
					</td>
					<td>
						<?php if ( $holiday_review ) : ?>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Classification', 'axismundi-calendar' ); ?></legend>
								<label><input type="radio" name="review[<?php echo esc_attr( (string) $item['id'] ); ?>][classification]" value="PUBLIC-HOLIDAY" <?php checked( 'PUBLIC-HOLIDAY', $class_value ); ?>> <?php esc_html_e( 'Public holiday', 'axismundi-calendar' ); ?></label><br>
								<label><input type="radio" name="review[<?php echo esc_attr( (string) $item['id'] ); ?>][classification]" value="OBSERVANCE" <?php checked( 'OBSERVANCE', $class_value ); ?>> <?php esc_html_e( 'Observance', 'axismundi-calendar' ); ?></label><br>
								<label><input type="radio" name="review[<?php echo esc_attr( (string) $item['id'] ); ?>][classification]" value="" <?php checked( '', $class_value ); ?>> <?php esc_html_e( 'Unclassified', 'axismundi-calendar' ); ?></label>
								<?php if ( is_array( $occurrence ) && 'substitute' === (string) $occurrence['role'] ) : ?>
									<label style="margin-left:1em;"><input type="checkbox" checked disabled> <?php esc_html_e( 'Substitute day', 'axismundi-calendar' ); ?></label>
								<?php endif; ?>
							</fieldset>
						<?php else : ?>
							<code><?php echo esc_html( (string) $item['categories'] ); ?></code>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $holiday_review ) : ?>
							<?php echo esc_html( 'published' === (string) $item['status'] ? __( 'Published', 'axismundi-calendar' ) : __( 'Draft', 'axismundi-calendar' ) ); ?>
						<?php else : ?>
							<?php echo esc_html( 'published' === (string) $item['status'] ? __( 'Yes', 'axismundi-calendar' ) : __( 'Draft', 'axismundi-calendar' ) ); ?>
						<?php endif; ?>
					</td>
					<td>
						<?php
						/*
						 * Where it came from, because these behave differently and look identical on screen.
						 * An import re-runs and overwrites; a computed row is regenerated from arithmetic and
						 * pruned when it leaves the window; a typed one is only ever changed by a person.
						 *
						 * Three states rather than two. Both an import and a generator write `source_uid` --
						 * it is what makes a second pass update rather than duplicate -- so reading only that
						 * labelled every moon phase "Imported", which named a feed that does not exist and
						 * implied somebody could stop it by removing a source.
						 */
						if ( '' === (string) ( $item['source_uid'] ?? '' ) ) {
							echo esc_html__( 'Entered here', 'axismundi-calendar' );
						} elseif ( '' !== (string) ( $item['source_url'] ?? '' ) ) {
							echo esc_html__( 'Imported', 'axismundi-calendar' );
						} else {
							echo esc_html__( 'Computed', 'axismundi-calendar' );
						}
						?>
					</td>
					<?php if ( ! $holiday_review ) : ?>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="ax_cal_save_system_item">
							<input type="hidden" name="calendar_id" value="<?php echo esc_attr( (string) $calendar_id ); ?>">
							<input type="hidden" name="item_id" value="<?php echo esc_attr( (string) $item['id'] ); ?>">
							<?php wp_nonce_field( 'ax_cal_system_item_' . $calendar_id ); ?>
							<button type="submit" class="button button-link-delete" name="ax_cal_item_action" value="delete"
								onclick="return confirm( '<?php echo esc_js( __( 'Remove this entry?', 'axismundi-calendar' ) ); ?>' );">
								<?php esc_html_e( 'Remove', 'axismundi-calendar' ); ?>
							</button>
						</form>
					</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( $holiday_review ) : ?>
		<p class="description"><?php esc_html_e( 'The bulk actions classify and publish the selected entries together. Use the individual choices below only when a date needs an exception.', 'axismundi-calendar' ); ?></p>
		<p>
			<button type="button" class="button" id="ax-cal-select-drafts" onclick="window.axismundiCalendarSystemItems.selectDrafts(this.form)"><?php esc_html_e( 'Select drafts', 'axismundi-calendar' ); ?></button>
			<button type="button" class="button" id="ax-cal-invert-holiday-selection" onclick="window.axismundiCalendarSystemItems.invert(this.form)"><?php esc_html_e( 'Invert selection', 'axismundi-calendar' ); ?></button>
		</p>
		<p class="submit">
			<button type="submit" class="button button-secondary" name="holiday_action" value="observance"><?php esc_html_e( 'Set and publish selected as observances', 'axismundi-calendar' ); ?></button>
			<button type="submit" class="button button-primary" name="holiday_action" value="public_holiday"><?php esc_html_e( 'Set and publish selected as public holidays', 'axismundi-calendar' ); ?></button>
			<button type="submit" class="button button-primary" name="holiday_action" value="publish"><?php esc_html_e( 'Publish selected', 'axismundi-calendar' ); ?></button>
			<button type="submit" class="button-link" name="holiday_action" value="save"><?php esc_html_e( 'Save individual changes', 'axismundi-calendar' ); ?></button>
		</p>
	</form>
	<?php endif; ?>

	<?php axismundi_cal_render_catalog_join( $calendar ); ?>

	<?php axismundi_cal_render_item_links( $calendar, $items, $year ); ?>

	<?php axismundi_cal_render_system_import( $calendar ); ?>

	<h3><?php echo esc_html( is_array( $current ) ? __( 'Edit entry', 'axismundi-calendar' ) : __( 'Add entry', 'axismundi-calendar' ) ); ?></h3>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ax_cal_save_system_item">
		<input type="hidden" name="calendar_id" value="<?php echo esc_attr( (string) $calendar_id ); ?>">
		<input type="hidden" name="item_id" value="<?php echo esc_attr( (string) ( $current['id'] ?? 0 ) ); ?>">
		<?php wp_nonce_field( 'ax_cal_system_item_' . $calendar_id ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ax-cal-item-title"><?php esc_html_e( 'Name', 'axismundi-calendar' ); ?></label></th>
				<td>
					<?php
					/*
					 * Not `required`, because a row whose categories name it has no title to show and forcing
					 * one here would write a translated phase name into a row that had been keeping the key
					 * instead. The placeholder is what the row currently reads as, so the field looks
					 * answered rather than empty, and the writer decides whether blank is allowed.
					 */
					$ax_cal_item_generated = is_array( $current ) ? axismundi_cal_item_generated_name( $current['categories'] ?? array() ) : '';
					?>
					<input name="title" id="ax-cal-item-title" type="text" class="regular-text"
						<?php echo '' !== $ax_cal_item_generated ? 'placeholder="' . esc_attr( $ax_cal_item_generated ) . '"' : ''; ?>
						value="<?php echo esc_attr( (string) ( $current['title'] ?? '' ) ); ?>">
					<p class="description"><?php esc_html_e( 'As people here should read it. This is a translation, not an identity, so it can be corrected without breaking anything.', 'axismundi-calendar' ); ?></p>
					<?php if ( '' !== $ax_cal_item_generated ) : ?>
						<p class="description"><?php esc_html_e( 'Left blank, this entry is named by its category in whatever language each reader uses.', 'axismundi-calendar' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ax-cal-item-start"><?php esc_html_e( 'Date', 'axismundi-calendar' ); ?></label></th>
				<td>
					<input name="start_date" id="ax-cal-item-start" type="date" required
						value="<?php echo esc_attr( (string) ( $current['start_date'] ?? '' ) ); ?>">
					<p class="description"><?php esc_html_e( 'A whole day, the same day everywhere. No timezone is applied to it.', 'axismundi-calendar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ax-cal-item-end"><?php esc_html_e( 'Ends before', 'axismundi-calendar' ); ?></label></th>
				<td>
					<input name="end_date" id="ax-cal-item-end" type="date"
						value="<?php echo esc_attr( (string) ( $current['end_date'] ?? '' ) ); ?>">
					<p class="description"><?php esc_html_e( 'The first day it no longer covers. Leave this empty for a single day.', 'axismundi-calendar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Categories', 'axismundi-calendar' ); ?></th>
				<td>
					<?php
					/*
					 * None of the top-level keys are offered here, not merely the ones this Calendar has.
					 * `HOLIDAY`, `ASTRONOMY`, `RELIGIOUS`, `CIVIC` and `ACADEMIC` are the Calendar layer of
					 * the vocabulary: `RELIGIOUS` is what a Religious observances calendar is, not a tag an
					 * entry on some other calendar wears. A Buddhist public holiday says so with `BUDDHIST`
					 * beside `PUBLIC-HOLIDAY`, which is the tradition rather than the classification and is
					 * the part that actually varies between entries.
					 *
					 * The Calendar's own keys are shown above as inherited, so the full set stays legible
					 * without a ticked box that cannot be unticked without lying.
					 */
					$inherited_categories = axismundi_cal_normalize_system_calendar_categories( (string) ( $calendar['system_categories'] ?? '' ) );
					?>
					<?php if ( array() !== $inherited_categories ) : ?>
						<p>
							<?php foreach ( $inherited_categories as $inherited_category ) : ?>
								<code><?php echo esc_html( $inherited_category ); ?></code>
							<?php endforeach; ?>
							<span class="description">
								<?php esc_html_e( 'From this calendar. Every entry on it has this, so it is not stored on each one.', 'axismundi-calendar' ); ?>
							</span>
						</p>
					<?php endif; ?>
					<?php foreach ( array_diff( AXISMUNDI_CAL_ITEM_CATEGORIES, AXISMUNDI_CAL_SYSTEM_CALENDAR_CATEGORIES ) as $category ) : ?>
						<label style="display:inline-block;min-width:14em;">
							<input type="checkbox" name="categories[]" value="<?php echo esc_attr( $category ); ?>"
								<?php checked( in_array( $category, $selected, true ) ); ?>>
							<code><?php echo esc_html( $category ); ?></code>
						</label>
					<?php endforeach; ?>
					<p class="description">
						<?php esc_html_e( 'What readers filter on. More than one is normal: a day can be a public holiday and a religious observance at the same time.', 'axismundi-calendar' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ax-cal-item-batch"><?php esc_html_e( 'Belongs to year', 'axismundi-calendar' ); ?></label></th>
				<td>
					<input name="batch_year" id="ax-cal-item-batch" type="number" min="1" step="1" class="small-text"
						value="<?php echo esc_attr( (string) ( $current['batch_year'] ?? '' ) ); ?>">
					<p class="description"><?php esc_html_e( 'Left empty this follows the date. Set it when they differ, as for a substitute day in January that belongs to the year before.', 'axismundi-calendar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Busy', 'axismundi-calendar' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="transparency" value="OPAQUE" <?php checked( 'OPAQUE', (string) ( $current['transparency'] ?? '' ) ); ?>>
						<?php esc_html_e( 'Counts as time taken', 'axismundi-calendar' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Off for most of these. A public holiday is worth knowing about and does not make anyone unavailable.', 'axismundi-calendar' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Reviewed', 'axismundi-calendar' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="status" value="published" <?php checked( 'published', (string) ( $current['status'] ?? 'draft' ) ); ?>>
						<?php esc_html_e( 'Checked and ready to show', 'axismundi-calendar' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Until this is set, only people maintaining this calendar can see the entry.', 'axismundi-calendar' ); ?></p>
				</td>
			</tr>
			<?php if ( is_array( $current ) && '' !== (string) ( $current['source_uid'] ?? '' ) ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Imported from', 'axismundi-calendar' ); ?></th>
					<td>
						<code><?php echo esc_html( (string) $current['source_uid'] ); ?></code>
						<?php if ( '' !== (string) $current['source_url'] ) : ?>
							<p><code><?php echo esc_html( (string) $current['source_url'] ); ?></code></p>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Kept so a repeated import updates this entry rather than adding a second copy. Corrections made here survive it.', 'axismundi-calendar' ); ?></p>
					</td>
				</tr>
			<?php endif; ?>
		</table>
		<p class="submit">
			<button type="submit" class="button button-primary" name="ax_cal_item_action" value="save">
				<?php echo esc_html( is_array( $current ) ? __( 'Save entry', 'axismundi-calendar' ) : __( 'Add entry', 'axismundi-calendar' ) ); ?>
			</button>
			<?php if ( is_array( $current ) ) : ?>
				<a class="button button-secondary" href="<?php echo esc_url( add_query_arg( array( 'calendar' => $calendar_id, 'year' => $year ), $base ) ); ?>">
					<?php esc_html_e( 'Cancel', 'axismundi-calendar' ); ?>
				</a>
			<?php endif; ?>
		</p>
	</form>
	<?php
}

/**
 * The datasets this plugin can produce, and whether this site produces them.
 *
 * Two switches govern a computed calendar and they answer different questions. This one is the
 * site's: whether the server runs the generator at all, keeps its rolling window current, and offers
 * the calendar to anybody. The other is each person's, in the workspace, and decides only whether it
 * is drawn on their own grid.
 *
 * The ones nothing can compute yet are listed rather than hidden. A dataset that is simply absent
 * reads as one this plugin has no opinion about, which leaves somebody looking for equinoxes with
 * nothing to conclude; shown and unavailable says what is coming and why it is not here.
 *
 * @param string $base Screen URL.
 * @return void
 */
function axismundi_cal_render_managed_calendar_settings( string $base ) : void {
	?>
	<h2><?php esc_html_e( 'Computed calendars', 'axismundi-calendar' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Datasets this plugin works out for itself. Nothing is fetched and nothing is reviewed, so the only decision is whether this site produces them at all. Each person still chooses separately whether to show one on their own calendar.', 'axismundi-calendar' ); ?>
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ax_cal_save_managed_calendars">
		<?php wp_nonce_field( 'ax_cal_managed_settings' ); ?>
		<table class="form-table" role="presentation">
			<?php foreach ( array_keys( AXISMUNDI_CAL_MANAGED_CALENDARS ) as $key ) : ?>
				<?php
				$key       = (string) $key;
				$available = axismundi_cal_managed_calendar_available( $key );
				$calendar  = axismundi_cal_managed_calendar_get( $key );
				?>
				<tr>
					<th scope="row"><?php echo esc_html( axismundi_cal_managed_calendar_name( $key ) ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="managed[]" value="<?php echo esc_attr( $key ); ?>"
								<?php checked( axismundi_cal_managed_calendar_enabled( $key ) ); ?>
								<?php disabled( ! $available ); ?>>
							<?php esc_html_e( 'Produce this calendar on this site', 'axismundi-calendar' ); ?>
						</label>
						<p class="description"><?php echo esc_html( axismundi_cal_managed_calendar_description( $key ) ); ?></p>
						<?php if ( ! $available ) : ?>
							<p class="description">
								<em><?php esc_html_e( 'Not yet: nothing computes this one so far. Switching it on would leave an empty calendar that looks broken.', 'axismundi-calendar' ); ?></em>
							</p>
						<?php elseif ( is_array( $calendar ) ) : ?>
							<p class="description">
								<a href="<?php echo esc_url( add_query_arg( 'calendar', (int) $calendar['id'], $base ) ); ?>"><?php esc_html_e( 'See its entries', 'axismundi-calendar' ); ?></a>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php submit_button( __( 'Save computed calendars', 'axismundi-calendar' ) ); ?>
	</form>
	<p class="description">
		<?php esc_html_e( 'Switching one off removes its calendar and its entries, and its subscription address stops answering. Nothing is lost that cannot be worked out again: switching it back on recomputes the same dates at the same address.', 'axismundi-calendar' ); ?>
	</p>
	<p class="description">
		<?php
		/*
		 * Said plainly, because the opposite is what somebody would assume. Nothing here reaches into a
		 * subscriber's calendar: no ICS client is obliged to remove what it already holds when a feed
		 * stops answering, and most treat it as the subscription having ended rather than as the dates
		 * having been withdrawn.
		 */
		esc_html_e( 'This only stops the site publishing. People who already subscribe may keep the dates they have and need to remove the subscription themselves.', 'axismundi-calendar' );
		?>
	</p>
	<?php
}

/**
 * Save which computed calendars this site produces.
 *
 * @return void
 */
function axismundi_cal_handle_managed_calendar_settings() : void {
	check_admin_referer( 'ax_cal_managed_settings' );
	if ( ! axismundi_cal_can_manage_all_calendars() ) {
		wp_die( esc_html__( 'You are not allowed to maintain this site calendars.', 'axismundi-calendar' ), 403 );
	}
	$base = admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendar-system' );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
	$chosen = isset( $_POST['managed'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['managed'] ) ) : array();
	foreach ( array_keys( AXISMUNDI_CAL_MANAGED_CALENDARS ) as $key ) {
		$key = (string) $key;
		/*
		 * An unavailable dataset is skipped rather than switched off, so a disabled checkbox -- which a
		 * browser does not submit -- cannot be read as somebody having unticked it.
		 */
		if ( ! axismundi_cal_managed_calendar_available( $key ) ) {
			continue;
		}
		axismundi_cal_set_managed_calendar_enabled( $key, in_array( $key, $chosen, true ) );
	}
	wp_safe_redirect( add_query_arg( 'ax_cal_notice', 'managed_saved', $base ) );
	exit;
}
add_action( 'admin_post_ax_cal_save_managed_calendars', 'axismundi_cal_handle_managed_calendar_settings' );
