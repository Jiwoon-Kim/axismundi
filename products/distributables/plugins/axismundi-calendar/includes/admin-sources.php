<?php
/**
 * External calendars this site subscribes to.
 *
 * Its own screen, because a subscription is a different kind of thing from a Calendar somebody here
 * owns. It has a URL that can stop resolving, a fetch that can fail, a publisher to be polite to and
 * a cache that goes stale -- none of which a local Calendar has, and all of which are invisible on a
 * screen built to edit names and timezones.
 *
 * The split that matters: the source is cached once per instance, while subscribing is personal. Ten
 * people following the same feed produce one fetch and ten calendar-list entries, so "unsubscribe"
 * removes the entry and only takes the source away when the last person leaves. Sharing one cache is
 * an optimisation nobody should be able to feel.
 *
 * Nothing here is a permission over the remote Calendar. Subscribing to somebody's feed grants no
 * authority over it and no right to change it; the copy stays read-only however many people follow
 * it.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sources with the Calendar each one is, and whether the caller follows it.
 *
 * Everyone sees their own subscriptions. An administrator can see the rest, because a feed that has
 * been failing for a month is nobody's problem until somebody can find it, and the person who added
 * it may no longer have an account.
 *
 * @param bool $all Include sources the caller has not subscribed to.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_admin_source_rows( bool $all = false ) : array {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return array();
	}
	$actor_uri = axismundi_cal_current_actor_uri();
	$table     = axismundi_cal_sources_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- inventory over this plugin's own table.
	$sources = (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A );

	$rows = array();
	foreach ( $sources as $source ) {
		$calendar   = axismundi_cal_calendar_get( (int) $source['calendar_id'] );
		$subscribed = '' !== $actor_uri && is_array( axismundi_cal_list_entry( (int) $source['calendar_id'], $actor_uri ) );
		if ( ! $subscribed && ! ( $all && axismundi_cal_can_manage_all_calendars() ) ) {
			continue;
		}
		$source['calendar']    = is_array( $calendar ) ? $calendar : null;
		$source['subscribed']  = $subscribed;
		$source['followers']   = count( axismundi_cal_calendar_list_entries( (int) $source['calendar_id'] ) );
		$rows[]                = $source;
	}
	return $rows;
}

/**
 * Whether the caller may act on one source.
 *
 * @param array<string,mixed> $source Source row.
 * @return bool
 */
function axismundi_cal_can_manage_source( array $source ) : bool {
	if ( axismundi_cal_can_manage_all_calendars() ) {
		return true;
	}
	$actor_uri = axismundi_cal_current_actor_uri();
	return '' !== $actor_uri && is_array( axismundi_cal_list_entry( (int) $source['calendar_id'], $actor_uri ) );
}

/**
 * How a sync outcome reads.
 *
 * @param array<string,mixed> $source Source row.
 * @return string
 */
function axismundi_cal_source_status_label( array $source ) : string {
	switch ( (string) $source['sync_status'] ) {
		case 'ok':
			return __( 'Up to date', 'axismundi-calendar' );
		case 'unchanged':
			return __( 'Unchanged since the last check', 'axismundi-calendar' );
		case 'error':
			return __( 'Failing', 'axismundi-calendar' );
		case 'pending':
			return __( 'Not fetched yet', 'axismundi-calendar' );
	}
	return (string) $source['sync_status'];
}

/**
 * A stored UTC datetime as "3 hours ago", or a dash.
 *
 * @param string|null $stamp Stored datetime, or null.
 * @return string
 */
function axismundi_cal_source_when( ?string $stamp ) : string {
	$stamp = trim( (string) $stamp );
	if ( '' === $stamp || '0000-00-00 00:00:00' === $stamp ) {
		return '—';
	}
	/* translators: %s: human-readable time difference. */
	return sprintf( __( '%s ago', 'axismundi-calendar' ), human_time_diff( (int) strtotime( $stamp . ' UTC' ), time() ) );
}

/**
 * Fetch one source now, or leave it.
 *
 * @return void
 */
function axismundi_cal_handle_source_form() : void {
	check_admin_referer( 'ax_cal_source' );
	$source_id = isset( $_POST['source_id'] ) ? absint( wp_unslash( $_POST['source_id'] ) ) : 0;
	$source    = $source_id > 0 ? axismundi_cal_source_get( $source_id ) : null;
	if ( ! is_array( $source ) || ! axismundi_cal_can_manage_source( $source ) ) {
		wp_die( esc_html__( 'You are not allowed to manage that subscription.', 'axismundi-calendar' ), 403 );
	}
	$base   = admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendar-sources' );
	$action = isset( $_POST['ax_cal_source_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['ax_cal_source_action'] ) ) : '';

	if ( 'unsubscribe' === $action ) {
		$outcome = axismundi_cal_release_subscription( $source_id, axismundi_cal_current_actor_uri() );
		wp_safe_redirect( add_query_arg( 'ax_cal_notice', $outcome, $base ) );
		exit;
	}

	// A manual fetch ignores the stored validators, because the reason somebody presses this is that
	// they believe the cached answer is wrong.
	$result = axismundi_cal_sync_source( $source_id, true );
	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $result->get_error_code() ), $base ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_cal_notice', 'synced', $base ) );
	exit;
}
add_action( 'admin_post_ax_cal_sync_source', 'axismundi_cal_handle_source_form' );

/**
 * One Actor stops following a subscription.
 *
 * The cache outlives one person leaving. It is dropped only when nobody is left following it,
 * because until then it is still somebody's subscription -- and re-fetching a feed the site already
 * holds is rude to the publisher for no gain. Left as its own function rather than inline in the
 * handler so that both screens that offer "unsubscribe" make the same decision, and so the decision
 * can be asserted without going through a request that exits.
 *
 * @param int    $source_id Source id.
 * @param string $actor_uri Actor leaving.
 * @return string `unsubscribed`, or `source_removed` when that was the last one.
 */
function axismundi_cal_release_subscription( int $source_id, string $actor_uri ) : string {
	$source = axismundi_cal_source_get( $source_id );
	if ( ! is_array( $source ) ) {
		return 'source_removed';
	}
	$calendar_id = (int) $source['calendar_id'];
	axismundi_cal_list_remove( $calendar_id, $actor_uri );
	if ( array() !== axismundi_cal_calendar_list_entries( $calendar_id ) ) {
		return 'unsubscribed';
	}
	axismundi_cal_remove_source( $source_id );
	return 'source_removed';
}

/**
 * Register the subscriptions screen.
 *
 * @return void
 */
function axismundi_cal_sources_menu() : void {
	add_submenu_page(
		'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE,
		__( 'External calendars', 'axismundi-calendar' ),
		__( 'External calendars', 'axismundi-calendar' ),
		'publish_posts',
		'ax-calendar-sources',
		'axismundi_cal_render_sources_page'
	);
}
add_action( 'admin_menu', 'axismundi_cal_sources_menu' );

/**
 * The subscriptions screen.
 *
 * @return void
 */
function axismundi_cal_render_sources_page() : void {
	if ( ! axismundi_cal_can_manage_calendars() ) {
		wp_die( esc_html__( 'You are not allowed to manage calendars.', 'axismundi-calendar' ), 403 );
	}
	$base = admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendar-sources' );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen selection.
	$show_all = isset( $_GET['ax_cal_scope'] ) && 'all' === sanitize_key( wp_unslash( (string) $_GET['ax_cal_scope'] ) );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
	$notice = isset( $_GET['ax_cal_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['ax_cal_notice'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
	$error = isset( $_GET['ax_cal_error'] ) ? sanitize_key( wp_unslash( (string) $_GET['ax_cal_error'] ) ) : '';
	$rows  = axismundi_cal_admin_source_rows( $show_all );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'External calendars', 'axismundi-calendar' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Calendars published elsewhere that this site follows. They are read-only: an event that disappears from a feed is missing from it, which is not the same as having been cancelled, so nothing here is ever deleted on that basis.', 'axismundi-calendar' ); ?>
		</p>

		<?php if ( '' !== $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( axismundi_cal_source_notice_message( $notice ) ); ?></p></div>
		<?php endif; ?>
		<?php if ( '' !== $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( axismundi_cal_admin_error_message( $error ) ); ?></p></div>
		<?php endif; ?>

		<?php if ( axismundi_cal_can_manage_all_calendars() ) : ?>
			<p>
				<?php if ( $show_all ) : ?>
					<a href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( '&larr; Only the ones I follow', 'axismundi-calendar' ); ?></a>
				<?php else : ?>
					<a href="<?php echo esc_url( add_query_arg( 'ax_cal_scope', 'all', $base ) ); ?>"><?php esc_html_e( 'Show every subscription on this site', 'axismundi-calendar' ); ?></a>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Calendar', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Address', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last checked', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last received', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Followers', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'axismundi-calendar' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No subscriptions. Add one from the Calendars screen.', 'axismundi-calendar' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( is_array( $row['calendar'] ) ? (string) $row['calendar']['name'] : __( 'Unknown calendar', 'axismundi-calendar' ) ); ?></strong>
							<?php if ( ! $row['subscribed'] ) : ?>
								<br><em><?php esc_html_e( 'You do not follow this one', 'axismundi-calendar' ); ?></em>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( (string) $row['source_url'] ); ?></code></td>
						<td>
							<?php echo esc_html( axismundi_cal_source_status_label( $row ) ); ?>
							<?php if ( '' !== trim( (string) $row['sync_error'] ) ) : ?>
								<p class="description"><?php echo esc_html( (string) $row['sync_error'] ); ?></p>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( axismundi_cal_source_when( $row['last_checked_at'] ) ); ?></td>
						<td><?php echo esc_html( axismundi_cal_source_when( $row['last_success_at'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row['followers'] ) ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="ax_cal_sync_source">
								<input type="hidden" name="source_id" value="<?php echo esc_attr( (string) $row['id'] ); ?>">
								<?php wp_nonce_field( 'ax_cal_source' ); ?>
								<button type="submit" class="button button-secondary" name="ax_cal_source_action" value="sync">
									<?php esc_html_e( 'Fetch now', 'axismundi-calendar' ); ?>
								</button>
								<?php if ( $row['subscribed'] ) : ?>
									<button type="submit" class="button button-link-delete" name="ax_cal_source_action" value="unsubscribe">
										<?php esc_html_e( 'Unsubscribe', 'axismundi-calendar' ); ?>
									</button>
								<?php endif; ?>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Wording for one outcome on this screen.
 *
 * @param string $notice Notice key.
 * @return string
 */
function axismundi_cal_source_notice_message( string $notice ) : string {
	switch ( $notice ) {
		case 'synced':
			return __( 'The calendar was fetched.', 'axismundi-calendar' );
		case 'unsubscribed':
			return __( 'Removed from your calendars. Other people here still follow it, so the cached copy is kept.', 'axismundi-calendar' );
		case 'source_removed':
			return __( 'Removed. Nobody was following it, so the cached copy was dropped too.', 'axismundi-calendar' );
	}
	return __( 'Done.', 'axismundi-calendar' );
}
