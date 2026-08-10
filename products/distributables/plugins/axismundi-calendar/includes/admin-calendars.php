<?php
/**
 * The Calendars management screen.
 *
 * Ownership is recorded now, while calendars are public and every logged-in manager can see all of
 * them, because it is the answer to "whose calendar is this?" -- and that question becomes
 * unanswerable retroactively. Private calendars, sharing and subscription secrets all need it, and
 * reconstructing ownership after the fact means guessing from post authorship or from who happened
 * to edit last.
 *
 * So `owner_actor_uri` is filled at creation and is what per-calendar permission is judged against,
 * even though nothing today refuses a manager on the strength of it.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current user may reach the Calendars screen at all.
 *
 * Publishing is the floor: a calendar is a public, subscribable surface, so making one is closer to
 * publishing than to drafting.
 *
 * @return bool
 */
function axismundi_cal_can_manage_calendars() : bool {
	return current_user_can( 'publish_posts' );
}

/**
 * Whether the current user may change one particular Calendar.
 *
 * Two ways in: moderation capability over other people's content, or being the Actor the calendar
 * belongs to. The second is why ownership is recorded from the start -- without it the only
 * available rule would be "anyone who can publish may edit anyone's calendar".
 *
 * @param array<string,mixed>|null $calendar Calendar row, or null for a new one.
 * @return bool
 */
function axismundi_cal_can_manage_calendar( ?array $calendar ) : bool {
	if ( ! axismundi_cal_can_manage_calendars() ) {
		return false;
	}
	if ( null === $calendar || current_user_can( 'edit_others_posts' ) ) {
		return true;
	}
	$owner = trim( (string) ( $calendar['owner_actor_uri'] ?? '' ) );
	if ( '' === $owner ) {
		// An unowned calendar predates ownership being recorded, or was made by a route that did not
		// set it. Treated as moderator-only rather than as everyone's, since the safe reading of "no
		// owner" is not "any owner".
		return false;
	}
	return $owner === axismundi_cal_current_actor_uri();
}

/**
 * The Actor URI of the current user, when this site has Actors at all.
 *
 * Optional by design: the calendar engine works without Object Projections or Actors installed, so
 * ownership degrades to unset rather than blocking the screen.
 *
 * @return string
 */
function axismundi_cal_current_actor_uri() : string {
	$user_id = get_current_user_id();
	if ( $user_id <= 0 || ! function_exists( 'axismundi_op_local_author_actor_uri' ) ) {
		return '';
	}
	return (string) axismundi_op_local_author_actor_uri( $user_id );
}

/**
 * Register the Calendars screen beneath Events.
 *
 * @return void
 */
function axismundi_cal_admin_menu() : void {
	add_submenu_page(
		'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE,
		__( 'Calendars', 'axismundi-calendar' ),
		__( 'Calendars', 'axismundi-calendar' ),
		'publish_posts',
		'ax-calendars',
		'axismundi_cal_render_calendars_page'
	);
}
add_action( 'admin_menu', 'axismundi_cal_admin_menu' );

/**
 * Handle a submitted Calendar form.
 *
 * Runs on `admin_post` rather than inside the render, so a successful save redirects and cannot be
 * repeated by a page refresh.
 *
 * @return void
 */
function axismundi_cal_handle_calendar_form() : void {
	$base = admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendars' );
	if ( ! axismundi_cal_can_manage_calendars() ) {
		wp_die( esc_html__( 'You are not allowed to manage calendars.', 'axismundi-calendar' ), 403 );
	}

	$id       = isset( $_POST['calendar_id'] ) ? absint( wp_unslash( $_POST['calendar_id'] ) ) : 0;
	$existing = $id > 0 ? axismundi_cal_calendar_get( $id ) : null;
	// Bound to the row being changed, so a nonce for one calendar cannot be replayed against another.
	check_admin_referer( 'ax_cal_save_' . $id );
	if ( $id > 0 && ! is_array( $existing ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', 'missing', $base ) );
		exit;
	}
	if ( ! axismundi_cal_can_manage_calendar( $existing ) ) {
		wp_die( esc_html__( 'You are not allowed to manage that calendar.', 'axismundi-calendar' ), 403 );
	}

	$action = isset( $_POST['ax_cal_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['ax_cal_action'] ) ) : 'save';
	if ( 'delete' === $action && $id > 0 ) {
		axismundi_cal_calendar_delete( $id );
		wp_safe_redirect( add_query_arg( 'ax_cal_notice', 'deleted', $base ) );
		exit;
	}

	$fields = array(
		'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '',
		'slug'        => isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( (string) $_POST['slug'] ) ) : '',
		'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['description'] ) ) : '',
	);
	if ( isset( $_POST['timezone'] ) && '' !== $_POST['timezone'] ) {
		$fields['timezone'] = sanitize_text_field( wp_unslash( (string) $_POST['timezone'] ) );
	}
	if ( null === $existing ) {
		// Recorded at creation, from the Actor of whoever is making it. Asking later, or inferring it
		// from whoever edited most recently, is how ownership becomes a guess.
		$fields['owner_actor_uri'] = axismundi_cal_current_actor_uri();
	} elseif ( current_user_can( 'edit_others_posts' ) && isset( $_POST['owner_actor_uri'] ) ) {
		$fields['owner_actor_uri'] = esc_url_raw( wp_unslash( (string) $_POST['owner_actor_uri'] ) );
	}

	$saved = axismundi_cal_calendar_save( $fields, $id );
	if ( is_wp_error( $saved ) ) {
		wp_safe_redirect( add_query_arg( array( 'ax_cal_error' => rawurlencode( $saved->get_error_code() ), 'ax_cal_edit' => $id ), $base ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_cal_notice', $id > 0 ? 'updated' : 'created', $base ) );
	exit;
}
add_action( 'admin_post_ax_cal_save_calendar', 'axismundi_cal_handle_calendar_form' );

/**
 * The message for one error code.
 *
 * The writer's refusals are already author-facing sentences, so this only maps the codes the screen
 * itself can produce.
 *
 * @param string $code Error code.
 * @return string
 */
function axismundi_cal_admin_error_message( string $code ) : string {
	switch ( $code ) {
		case 'ax_cal_slug_taken':
			return __( 'Another calendar already uses that slug. Subscribers follow the slug, so it has to stay unique.', 'axismundi-calendar' );
		case 'ax_cal_name':
			return __( 'A calendar needs a name.', 'axismundi-calendar' );
		case 'ax_cal_slug':
			return __( 'A calendar needs a slug that can appear in a URL.', 'axismundi-calendar' );
		case 'ax_cal_timezone':
			return __( 'A calendar needs a named place rather than a fixed offset. Leave the timezone empty to follow the site.', 'axismundi-calendar' );
		case 'missing':
			return __( 'That calendar no longer exists.', 'axismundi-calendar' );
		default:
			return __( 'The calendar could not be saved.', 'axismundi-calendar' );
	}
}

/**
 * Render the Calendars screen.
 *
 * @return void
 */
function axismundi_cal_render_calendars_page() : void {
	global $wpdb;
	if ( ! axismundi_cal_can_manage_calendars() ) {
		wp_die( esc_html__( 'You are not allowed to manage calendars.', 'axismundi-calendar' ), 403 );
	}
	$base = admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendars' );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen selection.
	$editing  = isset( $_GET['ax_cal_edit'] ) ? absint( wp_unslash( $_GET['ax_cal_edit'] ) ) : 0;
	$calendar = $editing > 0 ? axismundi_cal_calendar_get( $editing ) : null;
	if ( $editing > 0 && ! axismundi_cal_can_manage_calendar( $calendar ) ) {
		wp_die( esc_html__( 'You are not allowed to manage that calendar.', 'axismundi-calendar' ), 403 );
	}

	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin screen over this plugin's own table.
	$rows = (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
	$notice = isset( $_GET['ax_cal_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['ax_cal_notice'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
	$error = isset( $_GET['ax_cal_error'] ) ? sanitize_key( wp_unslash( (string) $_GET['ax_cal_error'] ) ) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Calendars', 'axismundi-calendar' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'A calendar is a collection of events with its own subscription address. An event can belong to several calendars.', 'axismundi-calendar' ); ?>
		</p>

		<?php if ( '' !== $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Calendar saved.', 'axismundi-calendar' ); ?></p></div>
		<?php endif; ?>
		<?php if ( '' !== $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( axismundi_cal_admin_error_message( $error ) ); ?></p></div>
		<?php endif; ?>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Slug', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Timezone', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Owner', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Events', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Subscribe', 'axismundi-calendar' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No calendars yet.', 'axismundi-calendar' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<?php $can_edit = axismundi_cal_can_manage_calendar( $row ); ?>
					<tr>
						<td>
							<strong>
								<?php if ( $can_edit ) : ?>
									<a href="<?php echo esc_url( add_query_arg( 'ax_cal_edit', (int) $row['id'], $base ) ); ?>"><?php echo esc_html( $row['name'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $row['name'] ); ?>
								<?php endif; ?>
							</strong>
						</td>
						<td><code><?php echo esc_html( $row['slug'] ); ?></code></td>
						<td><?php echo esc_html( '' !== $row['timezone'] ? $row['timezone'] : __( 'Site default', 'axismundi-calendar' ) ); ?></td>
						<td><?php echo esc_html( '' !== $row['owner_actor_uri'] ? $row['owner_actor_uri'] : __( 'Unassigned', 'axismundi-calendar' ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( count( axismundi_cal_calendar_event_ids( (int) $row['id'] ) ) ) ); ?></td>
						<td><a href="<?php echo esc_url( home_url( '/calendar/' . $row['slug'] . '.ics' ) ); ?>"><code>.ics</code></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php echo esc_html( is_array( $calendar ) ? __( 'Edit calendar', 'axismundi-calendar' ) : __( 'Add calendar', 'axismundi-calendar' ) ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ax_cal_save_calendar">
			<input type="hidden" name="calendar_id" value="<?php echo esc_attr( (string) ( $calendar['id'] ?? 0 ) ); ?>">
			<?php wp_nonce_field( 'ax_cal_save_' . (int) ( $calendar['id'] ?? 0 ) ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ax-cal-name"><?php esc_html_e( 'Name', 'axismundi-calendar' ); ?></label></th>
					<td><input name="name" id="ax-cal-name" type="text" class="regular-text" required value="<?php echo esc_attr( (string) ( $calendar['name'] ?? '' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ax-cal-slug"><?php esc_html_e( 'Slug', 'axismundi-calendar' ); ?></label></th>
					<td>
						<input name="slug" id="ax-cal-slug" type="text" class="regular-text" value="<?php echo esc_attr( (string) ( $calendar['slug'] ?? '' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Used in the subscription address. Changing it breaks calendars people have already subscribed to.', 'axismundi-calendar' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ax-cal-timezone"><?php esc_html_e( 'Home timezone', 'axismundi-calendar' ); ?></label></th>
					<td>
						<select name="timezone" id="ax-cal-timezone">
							<option value="">
								<?php
								/*
								 * The resolved value, not the word "default". A site set to a manual UTC
								 * offset has no identifier to show, so "Site default" alone leaves the
								 * author guessing which zone their calendar will actually be read in.
								 */
								printf(
									/* translators: %s: the timezone the site currently resolves to. */
									esc_html__( 'Follow the site timezone (%s)', 'axismundi-calendar' ),
									esc_html( wp_timezone()->getName() )
								);
								?>
							</option>
							<?php
							/*
							 * Core's picker rather than a list of our own: it localizes the city names,
							 * groups them by region and stays current with the tz database. It also offers
							 * manual offsets, which are refused on save -- a calendar stores a place, and
							 * an offset is not one. Leaving the field empty is how you follow a site that
							 * is configured that way.
							 */
							require_once ABSPATH . 'wp-admin/includes/template.php';
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core builds this option list.
							echo wp_timezone_choice( (string) ( $calendar['timezone'] ?? '' ), get_user_locale() );
							?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Where this calendar belongs. It is the suggested timezone for new events and what the subscription feed declares. It does not decide what readers see: an event is shown in the timezone of whoever is reading, and each event keeps the timezone it happens in.', 'axismundi-calendar' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ax-cal-description"><?php esc_html_e( 'Description', 'axismundi-calendar' ); ?></label></th>
					<td><textarea name="description" id="ax-cal-description" rows="3" class="large-text"><?php echo esc_textarea( (string) ( $calendar['description'] ?? '' ) ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="ax-cal-owner"><?php esc_html_e( 'Owner', 'axismundi-calendar' ); ?></label></th>
					<td>
						<?php if ( current_user_can( 'edit_others_posts' ) ) : ?>
							<input name="owner_actor_uri" id="ax-cal-owner" type="url" class="regular-text"
								value="<?php echo esc_attr( (string) ( $calendar['owner_actor_uri'] ?? axismundi_cal_current_actor_uri() ) ); ?>">
							<p class="description"><?php esc_html_e( 'The Actor this calendar belongs to. Recorded now so private calendars and sharing have something to check later.', 'axismundi-calendar' ); ?></p>
						<?php else : ?>
							<code><?php echo esc_html( (string) ( $calendar['owner_actor_uri'] ?? axismundi_cal_current_actor_uri() ) ); ?></code>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary" name="ax_cal_action" value="save">
					<?php echo esc_html( is_array( $calendar ) ? __( 'Save calendar', 'axismundi-calendar' ) : __( 'Add calendar', 'axismundi-calendar' ) ); ?>
				</button>
				<?php if ( is_array( $calendar ) ) : ?>
					<button type="submit" class="button button-link-delete" name="ax_cal_action" value="delete"
						onclick="return confirm( '<?php echo esc_js( __( 'Delete this calendar? The events in it are not deleted.', 'axismundi-calendar' ) ); ?>' );">
						<?php esc_html_e( 'Delete calendar', 'axismundi-calendar' ); ?>
					</button>
					<a class="button button-secondary" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Cancel', 'axismundi-calendar' ); ?></a>
				<?php endif; ?>
			</p>
		</form>
	</div>
	<?php
}
