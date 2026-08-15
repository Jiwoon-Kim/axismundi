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
	// Through the capability table, so "who may share this" is answered in one place rather than
	// restated by every screen that offers the form.
	return axismundi_cal_calendar_can( $calendar, 'share' );
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
 * Turn what somebody typed into the Actor it names.
 *
 * Handles first, because that is what people know each other by and what an address book takes:
 * `@name` here, `@name@example.org` anywhere. A remote handle is resolved through Actors' own
 * discovery -- WebFinger, then the Actor document -- so the URI stored is the one that server says is
 * canonical rather than one guessed from the address.
 *
 * An Actor URI is still accepted, because somebody who has one in hand should not have to work
 * backwards to a handle. An email address is deliberately not: it names a person's mailbox, not the
 * identity access is granted to, and one person may run several Actors.
 *
 * @param string $input Typed value.
 * @return string|WP_Error Canonical Actor URI.
 */
function axismundi_cal_resolve_share_principal( string $input ) {
	$input = trim( $input );
	if ( '' === $input ) {
		return new WP_Error( 'ax_cal_share_principal', __( 'Enter a handle to share with.', 'axismundi-calendar' ) );
	}
	if ( str_starts_with( $input, 'http://' ) || str_starts_with( $input, 'https://' ) ) {
		return esc_url_raw( $input );
	}
	if ( is_email( ltrim( $input, '@' ) ) && ! str_starts_with( $input, '@' ) ) {
		return new WP_Error(
			'ax_cal_share_email',
			__( 'That looks like an email address. Calendars are shared with an Actor handle, such as @name or @name@example.org.', 'axismundi-calendar' )
		);
	}

	$handle = ltrim( $input, '@' );
	if ( ! str_contains( $handle, '@' ) ) {
		$actor = function_exists( 'axismundi_actors_get_by_handle' ) ? axismundi_actors_get_by_handle( $handle ) : null;
		if ( ! $actor instanceof Axismundi_Actor ) {
			return new WP_Error( 'ax_cal_share_unknown', __( 'No Actor on this site has that handle.', 'axismundi-calendar' ) );
		}
		return (string) $actor->get_uri();
	}

	if ( ! function_exists( 'axismundi_actors_discover_remote_actor' ) ) {
		return new WP_Error( 'ax_cal_share_remote', __( 'Remote Actors cannot be looked up on this site yet.', 'axismundi-calendar' ) );
	}
	$remote = axismundi_actors_discover_remote_actor( '@' . $handle );
	if ( is_wp_error( $remote ) ) {
		return $remote;
	}
	return $remote instanceof Axismundi_Actor
		? (string) $remote->get_uri()
		: new WP_Error( 'ax_cal_share_unknown', __( 'That handle could not be resolved to an Actor.', 'axismundi-calendar' ) );
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
	// Text rather than a URL, because this field now takes a handle. `esc_url_raw()` would turn
	// `@name@example.org` into something with a scheme bolted onto the front of it.
	$principal = 'public' === $type ? '' : sanitize_text_field( wp_unslash( (string) ( $_POST['principal'] ?? '' ) ) );

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

	// Resolved only for a new grant. A revoke names a rule that already exists by the URI it was stored
	// under, and re-resolving there would fail for an Actor whose server has since gone away -- leaving
	// somebody unable to withdraw access precisely when they most want to.
	$resolved = axismundi_cal_resolve_share_principal( $principal );
	if ( is_wp_error( $resolved ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $resolved->get_error_code() ), $base ) );
		exit;
	}
	$role   = sanitize_text_field( wp_unslash( (string) ( $_POST['role'] ?? '' ) ) );
	$result = axismundi_cal_acl_grant( $calendar_id, $resolved, $role, $type );
	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $result->get_error_code() ), $base ) );
		exit;
	}
	/*
	 * Access now, the calendar on their screen only if they want it. Recorded even when it cannot be
	 * delivered, so the rule and the asking stay one story -- and the person sharing is told which of
	 * the two actually happened rather than being left to assume both did.
	 */
	axismundi_cal_share_invite( $calendar_id, $resolved, $role, axismundi_cal_authoring_actor_uri() );
	wp_safe_redirect(
		add_query_arg(
			'ax_cal_notice',
			axismundi_cal_invitation_deliverable( $resolved ) ? 'shared' : 'shared_undeliverable',
			$base
		)
	);
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

	<?php
	// "Public access" rather than "Anyone", and never "event access": this is the most any anonymous
	// reader may be shown, and a private Event stays hidden inside a public calendar regardless.
	?>
	<h3><?php esc_html_e( 'Public access', 'axismundi-calendar' ); ?></h3>
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

	<?php
	/*
	 * Google says "Share with specific people or groups"; this says accounts, because an Organization
	 * or a Service Actor holds access on exactly the same terms and naming two of the four kinds reads
	 * as a rule about which kinds may be given it. Not "Shared with" either -- that names the list
	 * below rather than the thing this section does, and the section is where sharing happens.
	 */
	?>
	<h3><?php esc_html_e( 'Share with specific accounts', 'axismundi-calendar' ); ?></h3>
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
				<th scope="row"><label for="ax-cal-principal"><?php esc_html_e( 'Add account', 'axismundi-calendar' ); ?></label></th>
				<td>
					<?php
					/*
					 * A handle, the way an address book takes one. Never an email address: email is a way to
					 * reach somebody, not an identity here, and one person may run several Actors -- so the
					 * field that decides who gets access has to name the Actor rather than the human behind
					 * it. An Actor URI is still accepted for the case where somebody has one in hand.
					 */
					?>
					<input name="principal" id="ax-cal-principal" type="text" class="regular-text" placeholder="@handle or @handle@example.org">
					<p class="description">
						<?php esc_html_e( 'A handle on this site (@name) or on another server (@name@example.org). Granting access does not add the calendar to their list; they choose that themselves.', 'axismundi-calendar' ); ?>
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
