<?php
/**
 * Who else may see a Calendar.
 *
 * The screen for the ACL, kept beside the Calendar it governs and available to `owner` alone. A
 * writer may add Events; deciding who else may read the Calendar is administering it, which is a
 * different grant and one a writer was never given.
 *
 * Sharing is stated per principal, and the public is a principal like any other rather than a
 * checkbox meaning something else. That is what lets "anyone may read this" and "anyone may see
 * when I am busy" be two different answers instead of one flag with a footnote.
 *
 * Every refusal here comes from `axismundi_cal_acl_grant()` and the revoke guard rather than being
 * re-implemented: the screen and the REST API must not disagree about what a valid rule is.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current user may administer one Calendar's rules.
 *
 * @param array<string,mixed>|null $calendar Calendar row.
 * @return bool
 */
function axismundi_cal_can_share_calendar( ?array $calendar ) : bool {
	if ( ! is_array( $calendar ) || 'local' !== (string) $calendar['kind'] ) {
		// A subscribed Calendar is somebody else's to share. This site holds a cached copy of it and
		// has nothing to grant anyone.
		return false;
	}
	return axismundi_cal_acl_rank( axismundi_cal_request_role( (int) $calendar['id'] ) ) >= axismundi_cal_acl_rank( 'owner' );
}

/**
 * How one rule reads on screen.
 *
 * @param array<string,mixed> $rule Rule row.
 * @return string
 */
function axismundi_cal_share_principal_label( array $rule ) : string {
	if ( 'public' === (string) $rule['principal_type'] ) {
		return __( 'Anyone', 'axismundi-calendar' );
	}
	$label = axismundi_cal_admin_actor_label( (string) $rule['principal_uri'] );
	// A remote Actor this site has never seen has no name to show, and the URI is what identifies it.
	return '' !== $label ? $label : (string) $rule['principal_uri'];
}

/**
 * The roles the sharing form offers, in the order they are worth reading.
 *
 * @return array<string,string>
 */
function axismundi_cal_share_role_labels() : array {
	return array(
		'freeBusyReader' => __( 'See when it is busy, without titles', 'axismundi-calendar' ),
		'reader'         => __( 'Read every event', 'axismundi-calendar' ),
		'writer'         => __( 'Read and add events', 'axismundi-calendar' ),
		'owner'          => __( 'Read, add events and manage sharing', 'axismundi-calendar' ),
	);
}

/**
 * Apply one sharing change.
 *
 * @return void
 */
function axismundi_cal_handle_share_form() : void {
	check_admin_referer( 'ax_cal_share' );
	$calendar_id = isset( $_POST['calendar_id'] ) ? absint( wp_unslash( $_POST['calendar_id'] ) ) : 0;
	$calendar    = $calendar_id > 0 ? axismundi_cal_calendar_get( $calendar_id ) : null;
	if ( ! axismundi_cal_can_share_calendar( $calendar ) ) {
		wp_die( esc_html__( 'You are not allowed to share that calendar.', 'axismundi-calendar' ), 403 );
	}
	$base = add_query_arg(
		'ax_cal_edit',
		$calendar_id,
		admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendars' )
	);

	$action    = isset( $_POST['ax_cal_share_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['ax_cal_share_action'] ) ) : '';
	$type      = isset( $_POST['principal_type'] ) && 'public' === $_POST['principal_type'] ? 'public' : 'actor';
	$principal = 'public' === $type ? '' : esc_url_raw( wp_unslash( (string) ( $_POST['principal'] ?? '' ) ) );

	if ( 'revoke' === $action ) {
		$rule = axismundi_cal_acl_rule( $calendar_id, $principal, $type );
		if ( is_array( $rule ) && 'owner' === (string) $rule['role'] && 1 >= axismundi_cal_acl_owner_count( $calendar_id ) ) {
			// A Calendar with no owner cannot be shared, renamed or deleted by anybody, and nothing in
			// the interface can undo it. Refused here rather than repaired later.
			wp_safe_redirect( add_query_arg( 'ax_cal_error', 'ax_cal_last_owner', $base ) );
			exit;
		}
		axismundi_cal_acl_revoke( $calendar_id, $principal, $type );
		wp_safe_redirect( add_query_arg( 'ax_cal_notice', 'unshared', $base ) );
		exit;
	}

	if ( 'public' === $action ) {
		/*
		 * The public row is three states in one control, because they are answers to one question and
		 * a person setting it is choosing between them. "Not shared" is the absence of the rule rather
		 * than a rule saying no: an ACL that has to store every negative is one where forgetting to
		 * store one grants access.
		 */
		$choice = sanitize_text_field( wp_unslash( (string) ( $_POST['public_choice'] ?? 'none' ) ) );
		if ( 'none' === $choice ) {
			axismundi_cal_acl_revoke( $calendar_id, '', 'public' );
			wp_safe_redirect( add_query_arg( 'ax_cal_notice', 'unshared', $base ) );
			exit;
		}
		$public = axismundi_cal_acl_grant( $calendar_id, '', $choice, 'public' );
		if ( is_wp_error( $public ) ) {
			wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $public->get_error_code() ), $base ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'ax_cal_notice', 'shared', $base ) );
		exit;
	}

	$role   = sanitize_text_field( wp_unslash( (string) ( $_POST['role'] ?? '' ) ) );
	$result = axismundi_cal_acl_grant( $calendar_id, $principal, $role, $type );
	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $result->get_error_code() ), $base ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_cal_notice', 'shared', $base ) );
	exit;
}
add_action( 'admin_post_ax_cal_share_calendar', 'axismundi_cal_handle_share_form' );

/**
 * The sharing section of the Calendar edit screen.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return void
 */
function axismundi_cal_render_sharing( array $calendar ) : void {
	if ( ! axismundi_cal_can_share_calendar( $calendar ) ) {
		return;
	}
	$calendar_id = (int) $calendar['id'];
	$rules       = axismundi_cal_acl_rules( $calendar_id );
	$public      = axismundi_cal_acl_rule( $calendar_id, '', 'public' );
	$public_role = is_array( $public ) ? (string) $public['role'] : '';
	$roles       = axismundi_cal_share_role_labels();
	?>
	<h2><?php esc_html_e( 'Sharing', 'axismundi-calendar' ); ?></h2>

	<h3><?php esc_html_e( 'Anyone', 'axismundi-calendar' ); ?></h3>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ax_cal_share_calendar">
		<input type="hidden" name="calendar_id" value="<?php echo esc_attr( (string) $calendar_id ); ?>">
		<input type="hidden" name="principal_type" value="public">
		<?php wp_nonce_field( 'ax_cal_share' ); ?>
		<p>
			<label>
				<input type="radio" name="public_choice" value="none" <?php checked( '', $public_role ); ?>>
				<?php esc_html_e( 'Not shared publicly', 'axismundi-calendar' ); ?>
			</label><br>
			<label>
				<input type="radio" name="public_choice" value="freeBusyReader" <?php checked( 'freeBusyReader', $public_role ); ?>>
				<?php esc_html_e( 'Anyone can see when it is busy, without titles', 'axismundi-calendar' ); ?>
			</label><br>
			<label>
				<input type="radio" name="public_choice" value="reader" <?php checked( 'reader', $public_role ); ?>>
				<?php esc_html_e( 'Anyone can read every event', 'axismundi-calendar' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'Reading publicly is what puts this calendar on the site, in its iCalendar feed and on other servers. Free/busy discloses that time is taken without disclosing what takes it, and no surface publishes it yet.', 'axismundi-calendar' ); ?>
		</p>
		<p>
			<button type="submit" class="button button-secondary" name="ax_cal_share_action" value="public">
				<?php esc_html_e( 'Save public access', 'axismundi-calendar' ); ?>
			</button>
		</p>
	</form>

	<h3><?php esc_html_e( 'People and groups', 'axismundi-calendar' ); ?></h3>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Who', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Access', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Remove', 'axismundi-calendar' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $rules ) ) : ?>
				<tr><td colspan="3"><?php esc_html_e( 'Nobody else has access.', 'axismundi-calendar' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rules as $rule ) : ?>
				<?php if ( 'public' === (string) $rule['principal_type'] ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<tr>
					<td>
						<strong><?php echo esc_html( axismundi_cal_share_principal_label( $rule ) ); ?></strong>
						<p><code><?php echo esc_html( (string) $rule['principal_uri'] ); ?></code></p>
					</td>
					<td><?php echo esc_html( $roles[ (string) $rule['role'] ] ?? (string) $rule['role'] ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="ax_cal_share_calendar">
							<input type="hidden" name="calendar_id" value="<?php echo esc_attr( (string) $calendar_id ); ?>">
							<input type="hidden" name="principal" value="<?php echo esc_attr( (string) $rule['principal_uri'] ); ?>">
							<?php wp_nonce_field( 'ax_cal_share' ); ?>
							<button type="submit" class="button button-link-delete" name="ax_cal_share_action" value="revoke">
								<?php esc_html_e( 'Remove access', 'axismundi-calendar' ); ?>
							</button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ax_cal_share_calendar">
		<input type="hidden" name="calendar_id" value="<?php echo esc_attr( (string) $calendar_id ); ?>">
		<?php wp_nonce_field( 'ax_cal_share' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ax-cal-principal"><?php esc_html_e( 'Actor address', 'axismundi-calendar' ); ?></label></th>
				<td>
					<input name="principal" id="ax-cal-principal" type="url" class="regular-text" placeholder="https://example.com/actors/…">
					<p class="description">
						<?php esc_html_e( 'The Actor URI of a person or group, on this site or another. Granting access does not add the calendar to their list; they choose that themselves.', 'axismundi-calendar' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ax-cal-role"><?php esc_html_e( 'Access', 'axismundi-calendar' ); ?></label></th>
				<td>
					<select name="role" id="ax-cal-role">
						<?php foreach ( $roles as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( 'reader', $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Granting the same Actor again changes their access rather than adding a second rule.', 'axismundi-calendar' ); ?></p>
				</td>
			</tr>
		</table>
		<p>
			<button type="submit" class="button button-secondary" name="ax_cal_share_action" value="grant">
				<?php esc_html_e( 'Share calendar', 'axismundi-calendar' ); ?>
			</button>
		</p>
	</form>
	<?php
}
