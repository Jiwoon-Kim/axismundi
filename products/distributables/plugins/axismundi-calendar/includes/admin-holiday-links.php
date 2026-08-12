<?php
/**
 * Saying which dataset a calendar is, and which holiday a row is about.
 *
 * Both are sitelinks in Wikidata's sense and neither is ever inferred. A calendar joins a catalog
 * because somebody said so, and a row is about a day of a holiday because somebody said so -- the
 * settings matching and the dates matching are proposals, and the difference between a proposal and
 * a fact is the whole reason this screen exists rather than a nightly job.
 *
 * The promotion path is here too, because it is the same act seen from the other side: a row with no
 * candidate is a holiday nobody has recorded yet, and the person looking at it is the one who knows.
 * Its classification comes with it, which is what stops the reviewer classifying 설날 once per
 * language and again every year.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Join a calendar to a catalog, or make the catalog it belongs to.
 *
 * @return void
 */
function axismundi_cal_handle_catalog_join() : void {
	$calendar_id = isset( $_POST['calendar_id'] ) ? absint( wp_unslash( $_POST['calendar_id'] ) ) : 0;
	check_admin_referer( 'ax_cal_catalog_' . $calendar_id );
	$calendar = $calendar_id > 0 ? axismundi_cal_calendar_get( $calendar_id ) : null;
	if ( ! axismundi_cal_calendar_can( $calendar, 'manage_items' ) ) {
		wp_die( esc_html__( 'You are not allowed to maintain that calendar.', 'axismundi-calendar' ), 403 );
	}
	$base   = add_query_arg( 'calendar', $calendar_id, admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendar-system' ) );
	$config = axismundi_cal_provider_config( $calendar );
	$chosen = isset( $_POST['catalog_id'] ) ? absint( wp_unslash( $_POST['catalog_id'] ) ) : 0;

	if ( 0 === $chosen ) {
		/*
		 * A separate dataset, which is the answer whenever the offered one is about something else.
		 * Two collections of Korean holidays can legitimately coexist, and the wrong join is far
		 * harder to notice afterwards than the wrong split.
		 */
		$chosen = axismundi_cal_holiday_catalog_save(
			array(
				'provider'     => 'holiday',
				'jurisdiction' => (string) ( $config['region'] ?? '' ),
				'scope'        => isset( $_POST['scope'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['scope'] ) ) : 'public-holidays-and-observances',
				'label'        => (string) $calendar['name'],
			)
		);
		if ( is_wp_error( $chosen ) ) {
			wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $chosen->get_error_code() ), $base ) );
			exit;
		}
	}
	$joined = axismundi_cal_join_holiday_catalog( $calendar_id, (int) $chosen );
	if ( is_wp_error( $joined ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $joined->get_error_code() ), $base ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_cal_notice', 'catalog_joined', $base ) );
	exit;
}
add_action( 'admin_post_ax_cal_join_catalog', 'axismundi_cal_handle_catalog_join' );

/**
 * Link one row to a day of a holiday, or make the holiday from it.
 *
 * @return void
 */
function axismundi_cal_handle_item_link() : void {
	$calendar_id = isset( $_POST['calendar_id'] ) ? absint( wp_unslash( $_POST['calendar_id'] ) ) : 0;
	check_admin_referer( 'ax_cal_link_' . $calendar_id );
	$calendar = $calendar_id > 0 ? axismundi_cal_calendar_get( $calendar_id ) : null;
	if ( ! axismundi_cal_calendar_can( $calendar, 'manage_items' ) ) {
		wp_die( esc_html__( 'You are not allowed to maintain that calendar.', 'axismundi-calendar' ), 403 );
	}
	$base    = add_query_arg(
		array( 'calendar' => $calendar_id, 'year' => isset( $_POST['year'] ) ? absint( wp_unslash( $_POST['year'] ) ) : 0 ),
		admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendar-system' )
	);
	$item_id = isset( $_POST['item_id'] ) ? absint( wp_unslash( $_POST['item_id'] ) ) : 0;
	$item    = axismundi_cal_system_item_get( $item_id );
	if ( ! is_array( $item ) || (int) $item['calendar_id'] !== $calendar_id ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', 'ax_cal_item_missing', $base ) );
		exit;
	}
	$catalog_id = (int) $calendar['holiday_catalog_id'];
	$action     = isset( $_POST['ax_cal_link_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['ax_cal_link_action'] ) ) : 'link';
	if ( 'update-role' === $action ) {
		$occurrence = axismundi_cal_holiday_occurrence_get( (int) $item['holiday_occurrence_id'] );
		$concept    = is_array( $occurrence ) ? axismundi_cal_holiday_concept_get( (int) $occurrence['concept_id'] ) : null;
		if ( ! is_array( $occurrence ) || ! is_array( $concept ) || $catalog_id !== (int) $concept['catalog_id'] ) {
			wp_safe_redirect( add_query_arg( 'ax_cal_error', 'ax_cal_occurrence_missing', $base ) );
			exit;
		}
		$updated = axismundi_cal_holiday_occurrence_save(
			(int) $concept['id'],
			array(
				'role'           => isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['role'] ) ) : (string) $occurrence['role'],
			),
			(int) $occurrence['id']
		);
		if ( is_wp_error( $updated ) ) {
			wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $updated->get_error_code() ), $base ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'ax_cal_notice', 'occurrence_updated', $base ) );
		exit;
	}

	if ( 'promote' === $action ) {
		/*
		 * A row with no candidate is a holiday nobody has recorded yet. Its classification comes with
		 * it, which is the point: 설날 is a public holiday once, not once per language and again every
		 * year -- and the reviewer looking at this row is the person who knows which it is.
		 */
		$concept = axismundi_cal_holiday_concept_save(
			array(
				'catalog_id' => $catalog_id,
				'label'      => (string) $item['title'],
				'categories' => (string) $item['categories'],
			)
		);
		if ( is_wp_error( $concept ) ) {
			wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $concept->get_error_code() ), $base ) );
			exit;
		}
		$occurrence = axismundi_cal_holiday_occurrence_save(
			(int) $concept,
			array(
				'start_date' => (string) $item['start_date'],
				'end_date'   => (string) $item['end_date'],
				'batch_year' => (int) $item['batch_year'],
				'role'       => isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['role'] ) ) : 'principal',
				'status'     => (string) $item['status'],
			)
		);
		if ( is_wp_error( $occurrence ) ) {
			wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $occurrence->get_error_code() ), $base ) );
			exit;
		}
		axismundi_cal_link_item_to_occurrence( $item_id, (int) $occurrence );
		wp_safe_redirect( add_query_arg( 'ax_cal_notice', 'holiday_created', $base ) );
		exit;
	}

	$occurrence_id = isset( $_POST['occurrence_id'] ) ? absint( wp_unslash( $_POST['occurrence_id'] ) ) : 0;
	$linked        = axismundi_cal_link_item_to_occurrence( $item_id, $occurrence_id );
	if ( is_wp_error( $linked ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $linked->get_error_code() ), $base ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_cal_notice', $occurrence_id > 0 ? 'item_linked' : 'item_unlinked', $base ) );
	exit;
}
add_action( 'admin_post_ax_cal_link_item', 'axismundi_cal_handle_item_link' );

/**
 * Which dataset this calendar is, and the chance to say.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return void
 */
function axismundi_cal_render_catalog_join( array $calendar ) : void {
	if ( 'holiday' !== axismundi_cal_system_provider( $calendar ) ) {
		return;
	}
	$calendar_id = (int) $calendar['id'];
	$config      = axismundi_cal_provider_config( $calendar );
	$catalog     = axismundi_cal_holiday_catalog_get( (int) $calendar['holiday_catalog_id'] );
	?>
	<h3><?php esc_html_e( 'Dataset', 'axismundi-calendar' ); ?></h3>
	<?php if ( is_array( $catalog ) ) : ?>
		<?php $siblings = axismundi_cal_catalog_calendars( (int) $catalog['id'] ); ?>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: catalog name, 2: region, 3: scope. */
					__( 'Part of %1$s (%2$s, %3$s).', 'axismundi-calendar' ),
					(string) $catalog['label'],
					(string) $catalog['jurisdiction'],
					(string) $catalog['scope']
				)
			);
			?>
		</p>
		<p class="description">
			<?php esc_html_e( 'The same dataset in each of these languages. None of them is the original, and none is a translation of another.', 'axismundi-calendar' ); ?>
		</p>
		<ul>
			<?php foreach ( $siblings as $sibling ) : ?>
				<?php $sibling_config = axismundi_cal_provider_config( $sibling ); ?>
				<li>
					<code><?php echo esc_html( (string) ( $sibling_config['source_locale'] ?? '?' ) ); ?></code>
					<?php if ( (int) $sibling['id'] === $calendar_id ) : ?>
						<strong><?php echo esc_html( (string) $sibling['name'] ); ?></strong>
					<?php else : ?>
						<a href="<?php echo esc_url( add_query_arg( 'calendar', (int) $sibling['id'], admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendar-system' ) ) ); ?>">
							<?php echo esc_html( (string) $sibling['name'] ); ?>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php return; ?>
	<?php endif; ?>

	<?php $candidates = axismundi_cal_holiday_catalog_candidates( 'holiday', (string) ( $config['region'] ?? '' ) ); ?>
	<p class="description">
		<?php esc_html_e( 'This calendar is not yet part of a dataset. Joining one is what makes it the same holidays in another language, rather than a second list of them.', 'axismundi-calendar' ); ?>
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ax_cal_join_catalog">
		<input type="hidden" name="calendar_id" value="<?php echo esc_attr( (string) $calendar_id ); ?>">
		<?php wp_nonce_field( 'ax_cal_catalog_' . $calendar_id ); ?>
		<?php foreach ( $candidates as $candidate ) : ?>
			<p>
				<label>
					<input type="radio" name="catalog_id" value="<?php echo esc_attr( (string) $candidate['id'] ); ?>" checked>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: catalog name, 2: region, 3: scope. */
							__( 'Join %1$s (%2$s, %3$s)', 'axismundi-calendar' ),
							(string) $candidate['label'],
							(string) $candidate['jurisdiction'],
							(string) $candidate['scope']
						)
					);
					?>
				</label>
			</p>
		<?php endforeach; ?>
		<p>
			<label>
				<input type="radio" name="catalog_id" value="0" <?php checked( array() === $candidates ); ?>>
				<?php esc_html_e( 'Start a separate dataset', 'axismundi-calendar' ); ?>
			</label>
		</p>
		<?php if ( array() !== $candidates ) : ?>
			<p class="description">
				<?php esc_html_e( 'Offered because the country matches, which is not the same as being the same dataset. Two collections of one country&rsquo;s holidays can legitimately coexist, and a wrong join is harder to notice afterwards than a wrong split.', 'axismundi-calendar' ); ?>
			</p>
		<?php endif; ?>
		<p class="submit">
			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Save dataset', 'axismundi-calendar' ); ?></button>
		</p>
	</form>
	<?php
}

/**
 * Which holiday each row of a year is about.
 *
 * Only the rows that are about nothing yet, plus what the linked ones are attached to. A reviewer
 * working through a year wants the list of decisions still to make, and a table repeating every
 * settled row is one they stop reading.
 *
 * @param array<string,mixed>            $calendar Calendar row.
 * @param array<int,array<string,mixed>> $items    Entries of the year in view.
 * @param int                            $year     Year in view.
 * @return void
 */
function axismundi_cal_render_item_links( array $calendar, array $items, int $year ) : void {
	if ( 'holiday' !== axismundi_cal_system_provider( $calendar ) ) {
		return;
	}
	$calendar_id = (int) $calendar['id'];
	$catalog_id  = (int) $calendar['holiday_catalog_id'];
	?>
	<h3><?php esc_html_e( 'Which holiday each entry is', 'axismundi-calendar' ); ?></h3>
	<?php if ( $catalog_id <= 0 ) : ?>
		<p class="description"><?php esc_html_e( 'Join this calendar to a dataset first. Until then there are no holidays for its entries to be about.', 'axismundi-calendar' ); ?></p>
		<?php return; ?>
	<?php endif; ?>

	<p class="description">
		<?php esc_html_e( 'Saying an entry is a day of a holiday is what lets the same holiday appear in another language, and what carries its classification across years. Candidates are offered by date and confirmed by you: several holidays can share a date, and a name in one language cannot be matched against a name in another.', 'axismundi-calendar' ); ?>
	</p>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Date', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Entry', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Holiday', 'axismundi-calendar' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $items as $item ) : ?>
				<?php
				$occurrence = axismundi_cal_holiday_occurrence_get( (int) $item['holiday_occurrence_id'] );
				$concept    = is_array( $occurrence ) ? axismundi_cal_holiday_concept_get( (int) $occurrence['concept_id'] ) : null;
				$candidates = is_array( $occurrence ) ? array() : axismundi_cal_occurrence_candidates( $item, $catalog_id );
				?>
				<tr>
					<td><code><?php echo esc_html( (string) $item['start_date'] ); ?></code></td>
					<td><?php echo esc_html( (string) $item['title'] ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="ax_cal_link_item">
							<input type="hidden" name="calendar_id" value="<?php echo esc_attr( (string) $calendar_id ); ?>">
							<input type="hidden" name="item_id" value="<?php echo esc_attr( (string) $item['id'] ); ?>">
							<input type="hidden" name="year" value="<?php echo esc_attr( (string) $year ); ?>">
							<?php wp_nonce_field( 'ax_cal_link_' . $calendar_id ); ?>
							<?php if ( is_array( $concept ) ) : ?>
								<strong><?php echo esc_html( (string) $concept['label'] ); ?></strong>
								<fieldset class="ax-cal-occurrence-role">
									<legend class="screen-reader-text"><?php esc_html_e( 'Day role', 'axismundi-calendar' ); ?></legend>
									<?php foreach ( AXISMUNDI_CAL_OCCURRENCE_ROLES as $role ) : ?>
										<label>
											<input type="radio" name="role" value="<?php echo esc_attr( $role ); ?>" <?php checked( $role, (string) $occurrence['role'] ); ?>>
											<?php echo esc_html( axismundi_cal_occurrence_role_label( $role ) ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
								<button type="submit" class="button button-small" name="ax_cal_link_action" value="update-role">
									<?php esc_html_e( 'Save role', 'axismundi-calendar' ); ?>
								</button>
								<input type="hidden" name="occurrence_id" value="0">
								<button type="submit" class="button-link" name="ax_cal_link_action" value="link">
									<?php esc_html_e( 'Unlink', 'axismundi-calendar' ); ?>
								</button>
							<?php elseif ( array() !== $candidates ) : ?>
								<select name="occurrence_id">
									<?php foreach ( $candidates as $candidate ) : ?>
										<option value="<?php echo esc_attr( (string) $candidate['id'] ); ?>">
											<?php echo esc_html( (string) $candidate['label'] . ' — ' . $candidate['role'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<button type="submit" class="button button-small" name="ax_cal_link_action" value="link">
									<?php esc_html_e( 'This one', 'axismundi-calendar' ); ?>
								</button>
							<?php else : ?>
								<fieldset class="ax-cal-occurrence-role">
									<legend class="screen-reader-text"><?php esc_html_e( 'Day role', 'axismundi-calendar' ); ?></legend>
									<?php foreach ( AXISMUNDI_CAL_OCCURRENCE_ROLES as $role ) : ?>
										<label>
											<input type="radio" name="role" value="<?php echo esc_attr( $role ); ?>" <?php checked( 'principal', $role ); ?>>
											<?php echo esc_html( axismundi_cal_occurrence_role_label( $role ) ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
								<button type="submit" class="button button-small" name="ax_cal_link_action" value="promote">
									<?php esc_html_e( 'New holiday from this', 'axismundi-calendar' ); ?>
								</button>
							<?php endif; ?>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}
