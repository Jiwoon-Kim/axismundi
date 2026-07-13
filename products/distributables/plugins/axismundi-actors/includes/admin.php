<?php
/**
 * Phase 4a — actor activation & profile management (admin surfaces).
 *
 * Surfaces: a read-only summary panel on profile.php / user-edit; an Actor status
 * column on users.php; a dedicated Users > Actor Profile screen holding the
 * activation wizard and management view; and Settings > Actor Profile for the site
 * actor. Activation is a **dedicated nonce'd POST action** (never mixed into the
 * profile.php save), so registering the immutable handle and flipping visibility is
 * one explicit, capability-checked act. No forced login redirect. Avatar / header /
 * translations are Phase 4b/4d — shown here only as notices, never saved.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/**
 * May the viewer manage this actor? Own Person actor, or `manage_options`.
 *
 * @param Axismundi_Actor $actor   Actor.
 * @param int|null        $viewer  Viewer; defaults to current user.
 * @return bool
 */
function axismundi_actors_can_manage( Axismundi_Actor $actor, ?int $viewer = null ) : bool {
	$viewer = null === $viewer ? get_current_user_id() : $viewer;
	if ( $viewer <= 0 ) {
		return false;
	}
	if ( user_can( $viewer, 'manage_options' ) ) {
		return true;
	}
	$uid = $actor->get_local_user_id();
	return null !== $uid && $uid === $viewer;
}

/** @return string The Actor Profile admin screen URL, optionally for a user. */
function axismundi_actors_admin_url( int $user_id = 0 ) : string {
	$args = array( 'page' => 'axismundi-actor-profile' );
	if ( $user_id > 0 ) {
		$args['user_id'] = $user_id;
	}
	return add_query_arg( $args, admin_url( 'users.php' ) );
}

/**
 * Human status label for an actor (or a not-activated user).
 *
 * @param Axismundi_Actor|null $actor Actor.
 * @return string
 */
function axismundi_actors_status_label( ?Axismundi_Actor $actor ) : string {
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_handle_locked() ) {
		return __( 'Not activated', 'axismundi-actors' );
	}
	switch ( $actor->get_status() ) {
		case 'public':
			return __( 'Public', 'axismundi-actors' );
		case 'tombstone':
			return __( 'Tombstone', 'axismundi-actors' );
		case 'disabled':
			return __( 'Disabled', 'axismundi-actors' );
		default:
			return __( 'Internal', 'axismundi-actors' );
	}
}

/* -------------------------------------------------------------------------- *
 * profile.php / user-edit summary panel (read-only; links to the screen).
 * -------------------------------------------------------------------------- */

/**
 * @param WP_User $user Edited user.
 * @return void
 */
function axismundi_actors_profile_panel( WP_User $user ) : void {
	$viewer = get_current_user_id();
	$actor  = axismundi_actors_get_for_user( (int) $user->ID );
	if ( ! ( (int) $user->ID === $viewer || current_user_can( 'manage_options' ) ) ) {
		return;
	}
	$is_self = (int) $user->ID === $viewer;
	?>
	<h2><?php esc_html_e( 'Actor Profile', 'axismundi-actors' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'axismundi-actors' ); ?></th>
			<td>
				<p><strong><?php echo esc_html( axismundi_actors_status_label( $actor ) ); ?></strong>
				<?php if ( $actor instanceof Axismundi_Actor && $actor->is_handle_locked() ) : ?>
					· <code>@<?php echo esc_html( $actor->get_preferred_username() ); ?></code>
				<?php endif; ?>
				</p>
				<?php if ( $actor instanceof Axismundi_Actor && axismundi_actors_is_public_profile( $actor ) ) : ?>
					<p><a href="<?php echo esc_url( $actor->get_profile_url() ); ?>"><?php esc_html_e( 'View public profile', 'axismundi-actors' ); ?></a></p>
				<?php endif; ?>
				<p>
					<a class="button" href="<?php echo esc_url( axismundi_actors_admin_url( $is_self ? 0 : (int) $user->ID ) ); ?>">
						<?php echo $actor instanceof Axismundi_Actor && $actor->is_handle_locked() ? esc_html__( 'Manage actor profile', 'axismundi-actors' ) : esc_html__( 'Activate actor profile', 'axismundi-actors' ); ?>
					</a>
				</p>
				<p class="description"><?php esc_html_e( 'The actor handle is separate from your WordPress username and author URL, and cannot be changed once set.', 'axismundi-actors' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'axismundi_actors_profile_panel' );
add_action( 'edit_user_profile', 'axismundi_actors_profile_panel' );

/* -------------------------------------------------------------------------- *
 * users.php Actor status column.
 * -------------------------------------------------------------------------- */

/**
 * @param array<string,string> $columns Columns.
 * @return array<string,string>
 */
function axismundi_actors_users_column( array $columns ) : array {
	$columns['ax_actor'] = __( 'Actor', 'axismundi-actors' );
	return $columns;
}
add_filter( 'manage_users_columns', 'axismundi_actors_users_column' );

/**
 * @param string $output Column HTML.
 * @param string $column Column key.
 * @param int    $user_id Row user.
 * @return string
 */
function axismundi_actors_users_column_content( string $output, string $column, int $user_id ) : string {
	if ( 'ax_actor' !== $column ) {
		return $output;
	}
	$actor = axismundi_actors_get_for_user( $user_id );
	$label = esc_html( axismundi_actors_status_label( $actor ) );
	if ( current_user_can( 'edit_user', $user_id ) ) {
		$label .= ' — <a href="' . esc_url( axismundi_actors_admin_url( $user_id ) ) . '">' . esc_html__( 'Manage', 'axismundi-actors' ) . '</a>';
	}
	if ( $actor instanceof Axismundi_Actor && axismundi_actors_is_public_profile( $actor ) ) {
		$label .= ' · <a href="' . esc_url( $actor->get_profile_url() ) . '">' . esc_html__( 'View', 'axismundi-actors' ) . '</a>';
	}
	return $label;
}
add_filter( 'manage_users_custom_column', 'axismundi_actors_users_column_content', 10, 3 );

/* -------------------------------------------------------------------------- *
 * Dedicated Users > Actor Profile screen (wizard + management).
 * -------------------------------------------------------------------------- */

/** @return void */
function axismundi_actors_register_admin_pages() : void {
	add_users_page(
		__( 'Actor Profile', 'axismundi-actors' ),
		__( 'Actor Profile', 'axismundi-actors' ),
		'read',
		'axismundi-actor-profile',
		'axismundi_actors_render_admin_page'
	);
	add_options_page(
		__( 'Actor Profile', 'axismundi-actors' ),
		__( 'Actor Profile', 'axismundi-actors' ),
		'manage_options',
		'axismundi-actor-site',
		'axismundi_actors_render_site_page'
	);
}
add_action( 'admin_menu', 'axismundi_actors_register_admin_pages' );

/**
 * The target user for the management screen: `user_id` (needs edit_user) or self.
 *
 * @return int
 */
function axismundi_actors_admin_target_user() : int {
	$requested = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen routing.
	if ( $requested > 0 && current_user_can( 'edit_user', $requested ) ) {
		return $requested;
	}
	return get_current_user_id();
}

/** @return void */
function axismundi_actors_render_admin_page() : void {
	$user_id = axismundi_actors_admin_target_user();
	if ( $user_id <= 0 ) {
		return;
	}
	$actor = axismundi_actors_ensure_for_user( $user_id );
	if ( is_wp_error( $actor ) || ! axismundi_actors_can_manage( $actor ) ) {
		wp_die( esc_html__( 'You cannot manage this actor profile.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	echo '<div class="wrap">';
	echo '<h1>' . esc_html__( 'Actor Profile', 'axismundi-actors' ) . '</h1>';
	axismundi_actors_admin_notice();
	if ( $actor->is_handle_locked() ) {
		axismundi_actors_render_management( $actor, $user_id );
	} else {
		axismundi_actors_render_wizard( $actor, $user_id );
	}
	echo '</div>';
}

/** @return void */
function axismundi_actors_admin_notice() : void {
	if ( isset( $_GET['ax_actor_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only status flag.
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Actor profile updated.', 'axismundi-actors' ) . '</p></div>';
	}
	if ( isset( $_GET['ax_actor_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- escaped display-only message.
		echo '<div class="notice notice-error"><p>' . esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['ax_actor_error'] ) ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
}

/**
 * @param Axismundi_Actor $actor   Handle-less actor.
 * @param int             $user_id Target user.
 * @return void
 */
function axismundi_actors_render_wizard( Axismundi_Actor $actor, int $user_id ) : void {
	$candidates = axismundi_actors_handle_candidates( $user_id );
	$default    = $candidates[0] ?? '';
	?>
	<h2><?php esc_html_e( 'Activate actor profile', 'axismundi-actors' ); ?></h2>
	<p><?php esc_html_e( 'Choose an actor handle. This is your federated identity name and is shown as @handle. It is independent of your WordPress username and author URL, and cannot be changed after activation.', 'axismundi-actors' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="axismundi_actors_activate">
		<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user_id ); ?>">
		<?php wp_nonce_field( 'ax_actors_activate_' . $user_id ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ax-actor-handle"><?php esc_html_e( 'Actor handle', 'axismundi-actors' ); ?></label></th>
				<td>
					<span>@</span><input name="handle" id="ax-actor-handle" type="text" class="regular-text" value="<?php echo esc_attr( $default ); ?>" required>
					<?php if ( $candidates ) : ?>
						<p class="description"><?php esc_html_e( 'Suggestions:', 'axismundi-actors' ); ?> <?php echo esc_html( implode( ', ', $candidates ) ); ?></p>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'Lowercase letters, numbers, and underscores (no leading or trailing underscore), up to 30 characters. Your handle cannot be changed after activation.', 'axismundi-actors' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Visibility', 'axismundi-actors' ); ?></th>
				<td>
					<label><input type="radio" name="visibility" value="internal" checked> <?php esc_html_e( 'Internal — only you and admins can see it', 'axismundi-actors' ); ?></label><br>
					<label><input type="radio" name="visibility" value="public"> <?php esc_html_e( 'Public — anyone can see the profile', 'axismundi-actors' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Confirm', 'axismundi-actors' ); ?></th>
				<td><label><input type="checkbox" name="confirm_immutable" value="1" required> <?php esc_html_e( 'I understand the @handle cannot be changed after activation.', 'axismundi-actors' ); ?></label></td>
			</tr>
		</table>
		<?php submit_button( __( 'Activate actor profile', 'axismundi-actors' ) ); ?>
	</form>
	<p class="description"><?php esc_html_e( 'Avatar, header image, and profile translations are added in a later update.', 'axismundi-actors' ); ?></p>
	<?php
}

/**
 * @param Axismundi_Actor $actor   Activated actor.
 * @param int             $user_id Target user.
 * @return void
 */
function axismundi_actors_render_management( Axismundi_Actor $actor, int $user_id ) : void {
	$is_public = axismundi_actors_is_public_profile( $actor );
	?>
	<table class="form-table" role="presentation">
		<tr><th scope="row"><?php esc_html_e( 'Handle', 'axismundi-actors' ); ?></th><td><code>@<?php echo esc_html( $actor->get_preferred_username() ); ?></code> <span class="description">(<?php esc_html_e( 'permanent', 'axismundi-actors' ); ?>)</span></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Identity URI', 'axismundi-actors' ); ?></th><td><code><?php echo esc_html( $actor->get_uri() ); ?></code></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Status', 'axismundi-actors' ); ?></th><td><strong><?php echo esc_html( axismundi_actors_status_label( $actor ) ); ?></strong>
		<?php if ( $is_public ) : ?> · <a href="<?php echo esc_url( $actor->get_profile_url() ); ?>"><?php esc_html_e( 'View public profile', 'axismundi-actors' ); ?></a><?php endif; ?></td></tr>
	</table>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="axismundi_actors_set_visibility">
		<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user_id ); ?>">
		<?php wp_nonce_field( 'ax_actors_visibility_' . $user_id ); ?>
		<?php if ( $is_public ) : ?>
			<input type="hidden" name="status" value="internal">
			<?php submit_button( __( 'Make internal (unpublish)', 'axismundi-actors' ), 'secondary' ); ?>
		<?php else : ?>
			<input type="hidden" name="status" value="public">
			<?php submit_button( __( 'Publish (make public)', 'axismundi-actors' ) ); ?>
		<?php endif; ?>
	</form>
	<p class="description"><?php esc_html_e( 'Avatar, header image, and profile translations are added in a later update.', 'axismundi-actors' ); ?></p>
	<?php
}

/** @return void */
function axismundi_actors_render_site_page() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot manage the site actor.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	$actor = axismundi_actors_get_site_actor();
	echo '<div class="wrap"><h1>' . esc_html__( 'Site Actor Profile', 'axismundi-actors' ) . '</h1>';
	axismundi_actors_admin_notice();
	if ( ! $actor instanceof Axismundi_Actor ) {
		echo '<p>' . esc_html__( 'The site actor has not been seeded yet.', 'axismundi-actors' ) . '</p></div>';
		return;
	}
	$is_public = axismundi_actors_is_public_profile( $actor );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="axismundi_actors_site_settings">
		<?php wp_nonce_field( 'ax_actors_site_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><?php esc_html_e( 'Handle', 'axismundi-actors' ); ?></th><td><code>@<?php echo esc_html( $actor->get_preferred_username() ); ?></code></td></tr>
			<tr>
				<th scope="row"><label for="ax-site-type"><?php esc_html_e( 'Actor type', 'axismundi-actors' ); ?></label></th>
				<td>
					<select name="actor_type" id="ax-site-type">
						<option value="Application" <?php selected( 'Application', $actor->get_type() ); ?>><?php esc_html_e( 'Application (the site as a system)', 'axismundi-actors' ); ?></option>
						<option value="Organization" <?php selected( 'Organization', $actor->get_type() ); ?>><?php esc_html_e( 'Organization (a real org or brand)', 'axismundi-actors' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Visibility', 'axismundi-actors' ); ?></th>
				<td>
					<label><input type="radio" name="status" value="internal" <?php checked( ! $is_public ); ?>> <?php esc_html_e( 'Internal', 'axismundi-actors' ); ?></label><br>
					<label><input type="radio" name="status" value="public" <?php checked( $is_public ); ?>> <?php esc_html_e( 'Public', 'axismundi-actors' ); ?></label>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Save site actor', 'axismundi-actors' ) ); ?>
	</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------- *
 * Dedicated POST actions (nonce + capability; never the profile.php save).
 * -------------------------------------------------------------------------- */

/**
 * Redirect back to a screen with a success or error flag.
 *
 * @param string          $url    Base URL.
 * @param true|WP_Error   $result Outcome.
 * @return void
 */
function axismundi_actors_redirect_result( string $url, $result ) : void {
	$args = is_wp_error( $result )
		? array( 'ax_actor_error' => rawurlencode( $result->get_error_message() ) )
		: array( 'ax_actor_done' => 1 );
	wp_safe_redirect( add_query_arg( $args, $url ) );
	exit;
}

/** @return void */
function axismundi_actors_handle_activate() : void {
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	check_admin_referer( 'ax_actors_activate_' . $user_id );
	$actor = $user_id > 0 ? axismundi_actors_ensure_for_user( $user_id ) : new WP_Error( 'ax_actors_no_user', __( 'No such user.', 'axismundi-actors' ) );
	if ( is_wp_error( $actor ) || ! axismundi_actors_can_manage( $actor ) ) {
		wp_die( esc_html__( 'You cannot manage this actor profile.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	$back = axismundi_actors_admin_url( get_current_user_id() === $user_id ? 0 : $user_id );
	if ( empty( $_POST['confirm_immutable'] ) ) {
		axismundi_actors_redirect_result( $back, new WP_Error( 'ax_actors_confirm', __( 'Please confirm the handle is permanent.', 'axismundi-actors' ) ) );
	}
	$handle = isset( $_POST['handle'] ) ? sanitize_text_field( wp_unslash( $_POST['handle'] ) ) : '';
	$result = axismundi_actors_register_handle( $actor->get_identity_id(), $handle );
	if ( ! is_wp_error( $result ) ) {
		$visibility = isset( $_POST['visibility'] ) && 'public' === $_POST['visibility'] ? 'public' : 'internal';
		axismundi_actors_set_status( $actor->get_identity_id(), $visibility );
	}
	axismundi_actors_redirect_result( $back, $result );
}
add_action( 'admin_post_axismundi_actors_activate', 'axismundi_actors_handle_activate' );

/** @return void */
function axismundi_actors_handle_set_visibility() : void {
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	check_admin_referer( 'ax_actors_visibility_' . $user_id );
	$actor = axismundi_actors_get_for_user( $user_id );
	if ( ! $actor instanceof Axismundi_Actor || ! axismundi_actors_can_manage( $actor ) ) {
		wp_die( esc_html__( 'You cannot manage this actor profile.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	$status = isset( $_POST['status'] ) && 'public' === $_POST['status'] ? 'public' : 'internal';
	$ok     = axismundi_actors_set_status( $actor->get_identity_id(), $status );
	axismundi_actors_redirect_result( axismundi_actors_admin_url( get_current_user_id() === $user_id ? 0 : $user_id ), $ok ? true : new WP_Error( 'ax_actors_status', __( 'Could not update visibility.', 'axismundi-actors' ) ) );
}
add_action( 'admin_post_axismundi_actors_set_visibility', 'axismundi_actors_handle_set_visibility' );

/** @return void */
function axismundi_actors_handle_site_settings() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot manage the site actor.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'ax_actors_site_settings' );
	$actor = axismundi_actors_get_site_actor();
	$back  = admin_url( 'options-general.php?page=axismundi-actor-site' );
	if ( ! $actor instanceof Axismundi_Actor ) {
		axismundi_actors_redirect_result( $back, new WP_Error( 'ax_actors_no_site', __( 'No site actor.', 'axismundi-actors' ) ) );
	}
	$type = isset( $_POST['actor_type'] ) && 'Organization' === $_POST['actor_type'] ? 'Organization' : 'Application';
	update_option( 'ax_actors_site_actor_type', $type );
	axismundi_actors_set_actor_type( $actor->get_identity_id(), $type );
	$status = isset( $_POST['status'] ) && 'public' === $_POST['status'] ? 'public' : 'internal';
	axismundi_actors_set_status( $actor->get_identity_id(), $status );
	axismundi_actors_redirect_result( $back, true );
}
add_action( 'admin_post_axismundi_actors_site_settings', 'axismundi_actors_handle_site_settings' );
