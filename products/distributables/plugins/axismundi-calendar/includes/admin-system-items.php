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
	$rows = (array) $wpdb->get_results( "SELECT * FROM {$table} WHERE source IN ('manual','import') ORDER BY name ASC", ARRAY_A );
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
 * Wording for one outcome on this screen.
 *
 * @param string $code Notice or error key.
 * @return string
 */
function axismundi_cal_system_item_message( string $code ) : string {
	switch ( $code ) {
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

		<?php if ( empty( $datasets ) ) : ?>
			<p>
				<?php esc_html_e( 'No maintained calendars yet. Create one on the Calendars screen and set where its contents come from.', 'axismundi-calendar' ); ?>
			</p>
			<?php return; ?>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Maintained calendars', 'axismundi-calendar' ); ?></h2>
		<ul class="subsubsub" style="float:none;">
			<?php foreach ( $datasets as $dataset ) : ?>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'calendar', (int) $dataset['id'], $base ) ); ?>"
						<?php echo (int) $dataset['id'] === $chosen_id ? 'class="current"' : ''; ?>>
						<?php echo esc_html( (string) $dataset['name'] ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php
		if ( ! is_array( $chosen ) ) {
			echo '<p>' . esc_html__( 'Choose a calendar to see the entries on it.', 'axismundi-calendar' ) . '</p></div>';
			return;
		}
		axismundi_cal_render_system_item_editor( $chosen, $base );
		?>
	</div>
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
	<h2><?php echo esc_html( (string) $calendar['name'] ); ?></h2>

	<p>
		<?php esc_html_e( 'Year:', 'axismundi-calendar' ); ?>
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
		<?php if ( array() === $years ) : ?>
			<?php echo esc_html( (string) $year ); ?>
		<?php endif; ?>
	</p>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Date', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Name', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Categories', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Reviewed', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Source', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Remove', 'axismundi-calendar' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $items ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'Nothing for this year yet.', 'axismundi-calendar' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td><code><?php echo esc_html( (string) $item['start_date'] ); ?></code></td>
					<td>
						<strong>
							<a href="<?php echo esc_url( add_query_arg( array( 'calendar' => $calendar_id, 'year' => $year, 'item' => (int) $item['id'] ), $base ) ); ?>">
								<?php echo esc_html( (string) $item['title'] ); ?>
							</a>
						</strong>
					</td>
					<td><code><?php echo esc_html( (string) $item['categories'] ); ?></code></td>
					<td><?php echo esc_html( 'published' === (string) $item['status'] ? __( 'Yes', 'axismundi-calendar' ) : __( 'Draft', 'axismundi-calendar' ) ); ?></td>
					<td>
						<?php
						// Where it came from, because a corrected entry and an imported one look identical
						// on screen and behave differently the next time the import runs.
						echo esc_html( '' !== (string) ( $item['source_uid'] ?? '' ) ? __( 'Imported', 'axismundi-calendar' ) : __( 'Entered here', 'axismundi-calendar' ) );
						?>
					</td>
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
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

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
					<input name="title" id="ax-cal-item-title" type="text" class="regular-text" required
						value="<?php echo esc_attr( (string) ( $current['title'] ?? '' ) ); ?>">
					<p class="description"><?php esc_html_e( 'As people here should read it. This is a translation, not an identity, so it can be corrected without breaking anything.', 'axismundi-calendar' ); ?></p>
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
					<?php foreach ( AXISMUNDI_CAL_ITEM_CATEGORIES as $category ) : ?>
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
