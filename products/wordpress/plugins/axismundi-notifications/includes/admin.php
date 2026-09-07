<?php
/**
 * Where somebody actually reads it.
 *
 * A count in the admin bar and one screen behind it. Deliberately plain: what this slice has to
 * prove is that an Actor's news reaches the right people with the right unread state, and a design
 * would only make that harder to see. The rendering that belongs to a product -- a calendar
 * invitation with Accept beside it -- waits for the resolvers that produce those notices.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit;

/** @return string The inbox screen URL. */
function axismundi_ntf_admin_url() : string {
	return add_query_arg( 'page', 'axismundi-notifications', admin_url( 'index.php' ) );
}

/**
 * The unread count in the toolbar.
 *
 * Everything this person can currently read, across every Actor they are responsible for. A manager
 * of two Organizations has one count, because they have one attention -- which Actor each notice was
 * addressed to is what the screen says.
 *
 * @param WP_Admin_Bar $bar Toolbar.
 * @return void
 */
function axismundi_ntf_admin_bar( WP_Admin_Bar $bar ) : void {
	$user_id = get_current_user_id();
	if ( $user_id <= 0 || ! axismundi_ntf_ready() ) {
		return;
	}
	$unread = axismundi_ntf_unread_count( $user_id );
	$bar->add_node(
		array(
			'id'    => 'axismundi-notifications',
			'title' => $unread > 0
				/* translators: %d: unread notification count. */
				? sprintf( esc_html__( 'Notifications (%d)', 'axismundi-notifications' ), $unread )
				: esc_html__( 'Notifications', 'axismundi-notifications' ),
			'href'  => axismundi_ntf_admin_url(),
			'meta'  => array( 'title' => esc_attr__( 'What you have been sent', 'axismundi-notifications' ) ),
		)
	);
}
add_action( 'admin_bar_menu', 'axismundi_ntf_admin_bar', 80 );

/** @return void */
function axismundi_ntf_menu() : void {
	add_submenu_page(
		'index.php',
		__( 'Notifications', 'axismundi-notifications' ),
		__( 'Notifications', 'axismundi-notifications' ),
		// Not a capability of its own. What somebody may read is decided per Actor by the manager
		// relation, and a capability check here would answer a different question badly.
		'read',
		'axismundi-notifications',
		'axismundi_ntf_render_inbox'
	);
}
add_action( 'admin_menu', 'axismundi_ntf_menu' );

/**
 * How one Actor is named on the screen.
 *
 * @param int $identity_id Recipient identity.
 * @return string
 */
function axismundi_ntf_actor_label( int $identity_id ) : string {
	$actor = axismundi_ntf_has_actors() ? axismundi_actors_get_by_identity( $identity_id ) : null;
	if ( ! $actor instanceof Axismundi_Actor ) {
		return (string) $identity_id;
	}
	$handle = (string) $actor->get_preferred_username();
	$name   = (string) $actor->get_display_name();
	if ( '' === $name ) {
		return '' !== $handle ? '@' . $handle : (string) $actor->get_uri();
	}
	return '' !== $handle ? $name . ' (@' . $handle . ')' : $name;
}

/**
 * The inbox.
 *
 * @return void
 */
function axismundi_ntf_render_inbox() : void {
	$user_id = get_current_user_id();
	if ( ! axismundi_ntf_ready() ) {
		$unmet = axismundi_ntf_unmet_dependencies();
		echo '<div class="wrap"><h1>' . esc_html__( 'Notifications', 'axismundi-notifications' ) . '</h1><p>'
			. esc_html(
				array() === $unmet
					? __( 'Notifications is still setting up its storage.', 'axismundi-notifications' )
					: sprintf(
						/* translators: %s: comma-separated plugin names. */
						__( 'Notifications needs %s.', 'axismundi-notifications' ),
						implode( ', ', $unmet )
					)
			)
			. '</p></div>';
		return;
	}
	$rows = axismundi_ntf_inbox( $user_id, 100 );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Notifications', 'axismundi-notifications' ); ?></h1>
		<?php if ( array() === $rows ) : ?>
			<p><?php esc_html_e( 'Nothing has been sent to you yet.', 'axismundi-notifications' ); ?></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:1em">
				<?php wp_nonce_field( 'ax_ntf_read_all' ); ?>
				<input type="hidden" name="action" value="ax_ntf_read_all">
				<button type="submit" class="button"><?php esc_html_e( 'Mark everything read', 'axismundi-notifications' ); ?></button>
			</form>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Sent to', 'axismundi-notifications' ); ?></th>
						<th scope="col"><?php esc_html_e( 'What', 'axismundi-notifications' ); ?></th>
						<th scope="col"><?php esc_html_e( 'When', 'axismundi-notifications' ); ?></th>
						<th scope="col"><?php esc_html_e( 'State', 'axismundi-notifications' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$snapshot = json_decode( (string) $row['snapshot'], true );
					$title    = is_array( $snapshot ) ? (string) ( $snapshot['title'] ?? '' ) : '';
					$unread   = null !== $row['delivery_id'] && null === $row['read_at'];
					?>
					<tr>
						<td><?php echo esc_html( axismundi_ntf_actor_label( (int) $row['recipient_actor_id'] ) ); ?></td>
						<td>
							<strong><?php echo esc_html( (string) $row['kind'] ); ?></strong>
							<?php if ( '' !== $title ) : ?>
								<br><?php echo esc_html( $title ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $row['occurred_at'] ) ); ?></td>
						<td>
							<?php if ( $unread ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'ax_ntf_read_' . (int) $row['id'] ); ?>
									<input type="hidden" name="action" value="ax_ntf_read">
									<input type="hidden" name="notification" value="<?php echo esc_attr( (string) $row['id'] ); ?>">
									<button type="submit" class="button button-small"><?php esc_html_e( 'Mark read', 'axismundi-notifications' ); ?></button>
								</form>
							<?php elseif ( null === $row['delivery_id'] ) : ?>
								<?php
								/*
								 * Something the Actor was told before this person could read it. Shown, because
								 * it is the Actor's history and they are responsible for the Actor now; not
								 * counted, because arriving as a hundred unread notices about months somebody
								 * was not there for is how an inbox becomes something to clear rather than read.
								 */
								esc_html_e( 'Before you had access', 'axismundi-notifications' );
								?>
							<?php else : ?>
								<?php esc_html_e( 'Read', 'axismundi-notifications' ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<?php
		/*
		 * The half that makes filtering safe to turn on. Somebody with a policy against messages from
		 * strangers has to be able to find the one legitimate stranger who wrote to them, and a
		 * quarantine nobody can look through is a polite name for deleting.
		 */
		$requests = axismundi_ntf_requests( $user_id, 50 );
		?>
		<?php if ( array() !== $requests ) : ?>
			<h2><?php esc_html_e( 'Held for review', 'axismundi-notifications' ); ?></h2>
			<p><?php esc_html_e( 'These matched a filter you turned on. Nothing has been deleted.', 'axismundi-notifications' ); ?></p>
			<table class="widefat striped">
				<tbody>
				<?php foreach ( $requests as $request ) : ?>
					<tr>
						<td><?php echo esc_html( axismundi_ntf_actor_label( (int) $request['recipient_actor_id'] ) ); ?></td>
						<td><?php echo esc_html( (string) $request['kind'] ); ?></td>
						<td><?php echo esc_html( (string) $request['actor_uri'] ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'ax_ntf_accept_' . (int) $request['id'] ); ?>
								<input type="hidden" name="action" value="ax_ntf_accept">
								<input type="hidden" name="notification" value="<?php echo esc_attr( (string) $request['id'] ); ?>">
								<button type="submit" class="button button-small"><?php esc_html_e( 'Accept', 'axismundi-notifications' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'What you want to be told about', 'axismundi-notifications' ); ?></h2>
		<?php
		/*
		 * Categories rather than kinds, because that is the choice most people actually have: nobody
		 * wants to answer eleven questions about calendars. The model underneath answers per kind and
		 * per Actor as well, and a screen that needs that granularity can ask for it without this one
		 * having to grow.
		 */
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ax_ntf_preferences' ); ?>
			<input type="hidden" name="action" value="ax_ntf_preferences">
			<table class="form-table" role="presentation">
				<tbody>
				<?php foreach ( AXISMUNDI_NTF_CATEGORIES as $category ) : ?>
					<?php if ( in_array( $category, AXISMUNDI_NTF_UNFILTERABLE, true ) ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $category ); ?></th>
							<td>
								<?php
								// Said rather than hidden. Somebody looking for the switch should find out that
								// there isn't one and why, instead of concluding the screen is incomplete.
								esc_html_e( 'Always shown. Security and moderation notices cannot be turned off.', 'axismundi-notifications' );
								?>
							</td>
						</tr>
						<?php continue; ?>
					<?php endif; ?>
					<tr>
						<th scope="row"><?php echo esc_html( $category ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="categories[]" value="<?php echo esc_attr( $category ); ?>"
									<?php checked( axismundi_ntf_wants( $user_id, 0, '', $category, 'in_app' ) ); ?>>
								<?php esc_html_e( 'Show these in my notifications', 'axismundi-notifications' ); ?>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">
				<?php esc_html_e( 'This changes what reaches you from now on. Nothing already sent to you is removed.', 'axismundi-notifications' ); ?>
			</p>
			<?php submit_button( __( 'Save', 'axismundi-notifications' ) ); ?>
		</form>

		<h2><?php esc_html_e( 'Email', 'axismundi-notifications' ); ?></h2>
		<?php
		$mailbox   = axismundi_ntf_mailbox( $user_id );
		$alternate = axismundi_ntf_alternate_mailbox( $user_id, false );
		?>
		<?php if ( is_array( $mailbox ) ) : ?>
			<p>
				<?php
				printf(
					'account' === $mailbox['source']
						/* translators: %s: the account's email address. */
						? esc_html__( 'Email would go to %s, the address on your account.', 'axismundi-notifications' )
						/* translators: %s: a confirmed alternate address. */
						: esc_html__( 'Email would go to %s.', 'axismundi-notifications' ),
					'<code>' . esc_html( (string) $mailbox['address'] ) . '</code>'
				);
				?>
			</p>
			<p class="description">
				<?php
				/*
				 * Said, because the distinction is the point: this address is where the site writes to
				 * this account, and it is not published anywhere. An Actor's public contact is a
				 * separate thing somebody opts into on their profile.
				 */
				esc_html_e( 'This is private. It is not the public contact address on your Actor, and is never published.', 'axismundi-notifications' );
				?>
			</p>
		<?php endif; ?>

		<h3><?php esc_html_e( 'What to email', 'axismundi-notifications' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ax_ntf_email_preferences' ); ?>
			<input type="hidden" name="action" value="ax_ntf_email_preferences">
			<?php foreach ( AXISMUNDI_NTF_CATEGORIES as $category ) : ?>
				<p>
					<label>
						<input type="checkbox" name="categories[]" value="<?php echo esc_attr( $category ); ?>"
							<?php checked( axismundi_ntf_wants( $user_id, 0, '', $category, 'email' ) ); ?>>
						<?php echo esc_html( $category ); ?>
					</label>
				</p>
			<?php endforeach; ?>
			<p class="description">
				<?php esc_html_e( 'Nothing is emailed until you tick something here. Then only when you have been away for a few minutes, and never for something you have already read.', 'axismundi-notifications' ); ?>
			</p>
			<?php submit_button( __( 'Save', 'axismundi-notifications' ) ); ?>
		</form>

		<h2><?php esc_html_e( 'Push', 'axismundi-notifications' ); ?></h2>
		<?php
		/*
		 * Where the browser half goes, rendered by the plugin that owns devices. It belongs on this
		 * page because a subscription is between one person and one browser -- their own settings, not
		 * a site-wide screen -- and it is not drawn here because service workers and permissions are
		 * nothing this plugin should know about.
		 */
		do_action( 'axismundi_notification_device_settings' );
		?>
		<?php if ( ! axismundi_ntf_push_available() ) : ?>
			<p>
				<?php
				/*
				 * Said rather than drawn as a switch that would do nothing. Whether a browser can be
				 * reached is the PWA plugin's answer, and when it says no there is nothing here to offer.
				 */
				esc_html_e( 'This site cannot send push notifications yet, so there is nothing to turn on.', 'axismundi-notifications' );
				?>
			</p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ax_ntf_push_preferences' ); ?>
				<input type="hidden" name="action" value="ax_ntf_push_preferences">
				<?php foreach ( AXISMUNDI_NTF_CATEGORIES as $category ) : ?>
					<p>
						<label>
							<input type="checkbox" name="categories[]" value="<?php echo esc_attr( $category ); ?>"
								<?php checked( axismundi_ntf_wants( $user_id, 0, '', $category, 'push' ) ); ?>>
							<?php echo esc_html( $category ); ?>
						</label>
					</p>
				<?php endforeach; ?>
				<p class="description">
					<?php esc_html_e( 'Sent to the browsers you have registered, and only while you are away. The message itself says nothing but that something arrived.', 'axismundi-notifications' ); ?>
				</p>
				<?php submit_button( __( 'Save', 'axismundi-notifications' ) ); ?>
			</form>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Send it somewhere else', 'axismundi-notifications' ); ?></h3>
		<?php if ( is_array( $alternate ) && null === $alternate['confirmed_at'] ) : ?>
			<p>
				<?php
				printf(
					/* translators: %s: the address awaiting confirmation. */
					esc_html__( 'Waiting for %s to be confirmed. Check that mailbox.', 'axismundi-notifications' ),
					'<code>' . esc_html( (string) $alternate['address'] ) . '</code>'
				);
				?>
			</p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ax_ntf_mailbox' ); ?>
			<input type="hidden" name="action" value="ax_ntf_mailbox">
			<input type="email" name="address" class="regular-text" placeholder="<?php esc_attr_e( 'you@example.com', 'axismundi-notifications' ); ?>" required>
			<?php submit_button( __( 'Send confirmation', 'axismundi-notifications' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php if ( is_array( $alternate ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ax_ntf_mailbox' ); ?>
				<input type="hidden" name="action" value="ax_ntf_mailbox">
				<input type="hidden" name="forget" value="1">
				<?php submit_button( __( 'Go back to my account address', 'axismundi-notifications' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Ask for, confirm, or give up an address.
 *
 * @return void
 */
function axismundi_ntf_handle_mailbox() : void {
	check_admin_referer( 'ax_ntf_mailbox' );
	$user_id = get_current_user_id();
	if ( isset( $_POST['forget'] ) ) {
		axismundi_ntf_forget_mailbox( $user_id );
		wp_safe_redirect( axismundi_ntf_admin_url() );
		exit;
	}
	$address = isset( $_POST['address'] ) ? sanitize_email( wp_unslash( (string) $_POST['address'] ) ) : '';
	$asked   = axismundi_ntf_request_mailbox( $user_id, $address );
	if ( is_wp_error( $asked ) ) {
		wp_die( esc_html( $asked->get_error_message() ), 400 );
	}
	wp_safe_redirect( axismundi_ntf_admin_url() );
	exit;
}
add_action( 'admin_post_ax_ntf_mailbox', 'axismundi_ntf_handle_mailbox' );

/**
 * Save which categories are worth an email.
 *
 * @return void
 */
function axismundi_ntf_handle_email_preferences() : void {
	check_admin_referer( 'ax_ntf_email_preferences' );
	$user_id = get_current_user_id();
	$wanted  = isset( $_POST['categories'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['categories'] ) ) : array();
	foreach ( AXISMUNDI_NTF_CATEGORIES as $category ) {
		axismundi_ntf_set_preference( $user_id, 0, 'category', $category, 'email', in_array( $category, $wanted, true ) );
	}
	wp_safe_redirect( axismundi_ntf_admin_url() );
	exit;
}
add_action( 'admin_post_ax_ntf_email_preferences', 'axismundi_ntf_handle_email_preferences' );

/**
 * Save which categories are worth waking a device for.
 *
 * @return void
 */
function axismundi_ntf_handle_push_preferences() : void {
	check_admin_referer( 'ax_ntf_push_preferences' );
	$user_id = get_current_user_id();
	$wanted  = isset( $_POST['categories'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['categories'] ) ) : array();
	foreach ( AXISMUNDI_NTF_CATEGORIES as $category ) {
		axismundi_ntf_set_preference( $user_id, 0, 'category', $category, 'push', in_array( $category, $wanted, true ) );
	}
	wp_safe_redirect( axismundi_ntf_admin_url() );
	exit;
}
add_action( 'admin_post_ax_ntf_push_preferences', 'axismundi_ntf_handle_push_preferences' );

/**
 * Take a confirmation link.
 *
 * @return void
 */
function axismundi_ntf_maybe_confirm_mailbox() : void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token in the link is the credential, and it was mailed to the address being confirmed.
	$token = isset( $_GET['ax_ntf_confirm'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['ax_ntf_confirm'] ) ) : '';
	if ( '' === $token ) {
		return;
	}
	axismundi_ntf_confirm_mailbox( get_current_user_id(), $token );
}
add_action( 'load-dashboard_page_axismundi-notifications', 'axismundi_ntf_maybe_confirm_mailbox' );

/**
 * Save what somebody wants.
 *
 * @return void
 */
function axismundi_ntf_handle_preferences() : void {
	check_admin_referer( 'ax_ntf_preferences' );
	$user_id = get_current_user_id();
	$wanted  = isset( $_POST['categories'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['categories'] ) ) : array();
	foreach ( AXISMUNDI_NTF_CATEGORIES as $category ) {
		if ( in_array( $category, AXISMUNDI_NTF_UNFILTERABLE, true ) ) {
			continue;
		}
		axismundi_ntf_set_preference( $user_id, 0, 'category', $category, 'in_app', in_array( $category, $wanted, true ) );
	}
	wp_safe_redirect( axismundi_ntf_admin_url() );
	exit;
}
add_action( 'admin_post_ax_ntf_preferences', 'axismundi_ntf_handle_preferences' );

/**
 * Let a held notice through.
 *
 * @return void
 */
function axismundi_ntf_handle_accept() : void {
	$id = isset( $_POST['notification'] ) ? (int) $_POST['notification'] : 0;
	check_admin_referer( 'ax_ntf_accept_' . $id );
	$done = axismundi_ntf_accept_request( $id, get_current_user_id() );
	if ( is_wp_error( $done ) ) {
		wp_die( esc_html( $done->get_error_message() ), 403 );
	}
	wp_safe_redirect( axismundi_ntf_admin_url() );
	exit;
}
add_action( 'admin_post_ax_ntf_accept', 'axismundi_ntf_handle_accept' );

/**
 * Mark one read.
 *
 * @return void
 */
function axismundi_ntf_handle_read() : void {
	$id = isset( $_POST['notification'] ) ? (int) $_POST['notification'] : 0;
	check_admin_referer( 'ax_ntf_read_' . $id );
	// The model decides, not the form: whether this person may read that Actor's inbox is re-asked
	// here, because the row being in front of them proves only that it was once delivered.
	$done = axismundi_ntf_mark_read( $id, get_current_user_id() );
	if ( is_wp_error( $done ) ) {
		wp_die( esc_html( $done->get_error_message() ), 403 );
	}
	wp_safe_redirect( axismundi_ntf_admin_url() );
	exit;
}
add_action( 'admin_post_ax_ntf_read', 'axismundi_ntf_handle_read' );

/**
 * Mark everything read.
 *
 * @return void
 */
function axismundi_ntf_handle_read_all() : void {
	check_admin_referer( 'ax_ntf_read_all' );
	axismundi_ntf_mark_all_read( get_current_user_id() );
	wp_safe_redirect( axismundi_ntf_admin_url() );
	exit;
}
add_action( 'admin_post_ax_ntf_read_all', 'axismundi_ntf_handle_read_all' );
