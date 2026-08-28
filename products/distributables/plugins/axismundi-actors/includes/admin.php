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
 * May the viewer manage this actor? Managed Groups use their explicit Actors
 * owner/manager/editor relation; Person and Site actors retain their existing
 * own-profile / site-administrator rules.
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
	if ( $actor->is_managed() ) {
		return axismundi_actors_managed_actor_can_manage( $actor->get_identity_id(), $viewer );
	}
	if ( user_can( $viewer, 'manage_options' ) ) {
		return true;
	}
	$uid = $actor->get_local_user_id();
	return null !== $uid && $uid === $viewer && user_can( $viewer, 'edit_posts' );
}

/** @return string The Actor Profile admin screen URL, optionally for a user. */
function axismundi_actors_admin_url( int $user_id = 0 ) : string {
	$args = array( 'page' => 'axismundi-actor-profile' );
	if ( $user_id > 0 ) {
		$args['user_id'] = $user_id;
	}
	$parent = current_user_can( 'list_users' ) ? 'users.php' : 'profile.php';
	return add_query_arg( $args, admin_url( $parent ) );
}

/** @return string Remote Actor lookup/cache screen URL. */
function axismundi_actors_remote_admin_url() : string {
	return add_query_arg( 'page', 'axismundi-remote-actors', admin_url( 'users.php' ) );
}

/**
 * @return string Managed actor administration URL, optionally selecting one actor.
 *
 * One screen for every managed actor. A Group and an Organization differ in what they mean on the
 * wire, not in how they are administered: both are actors nobody logs in as, both are run through
 * the manager relation, and both publish under a handle of their own.
 */
function axismundi_actors_managed_actors_admin_url( int $identity_id = 0 ) : string {
	$args = array( 'page' => 'axismundi-managed-actors' );
	if ( $identity_id > 0 ) {
		$args['group_id'] = $identity_id;
	}
	$parent = current_user_can( 'list_users' ) ? 'users.php' : 'profile.php';
	return add_query_arg( $args, admin_url( $parent ) );
}

/** @return string The appropriate editor return URL for any local Actor scope. */
function axismundi_actors_management_back_url( Axismundi_Actor $actor ) : string {
	if ( $actor->is_managed() ) {
		return axismundi_actors_managed_actors_admin_url( $actor->get_identity_id() );
	}
	if ( 'site' === $actor->get_scope() ) {
		return admin_url( 'options-general.php?page=axismundi-actor-site' );
	}
	$user_id = $actor->get_local_user_id();
	return axismundi_actors_admin_url( get_current_user_id() === $user_id ? 0 : (int) $user_id );
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
	if ( ! ( current_user_can( 'manage_options' ) || ( (int) $user->ID === $viewer && current_user_can( 'edit_posts' ) ) ) ) {
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
	if ( current_user_can( 'list_users' ) ) {
		add_users_page(
			__( 'Actor Profile', 'axismundi-actors' ),
			__( 'Actor Profile', 'axismundi-actors' ),
			'edit_posts',
			'axismundi-actor-profile',
			'axismundi_actors_render_admin_page'
		);
	} else {
		add_submenu_page(
			'profile.php',
			__( 'Actor Profile', 'axismundi-actors' ),
			__( 'Actor Profile', 'axismundi-actors' ),
			'edit_posts',
			'axismundi-actor-profile',
			'axismundi_actors_render_admin_page'
		);
	}
	add_users_page(
		__( 'Remote Actors', 'axismundi-actors' ),
		__( 'Remote Actors', 'axismundi-actors' ),
		'manage_options',
		'axismundi-remote-actors',
		'axismundi_actors_render_remote_admin_page'
	);
	$managed_actors_parent = current_user_can( 'list_users' ) ? 'users.php' : 'profile.php';
	add_submenu_page(
		$managed_actors_parent,
		__( 'Managed actors', 'axismundi-actors' ),
		__( 'Managed actors', 'axismundi-actors' ),
		'read',
		'axismundi-managed-actors',
		'axismundi_actors_render_managed_actors_page'
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

/** Render the managed actor creation and profile-management surface. */
function axismundi_actors_render_managed_actors_page() : void {
	if ( ! current_user_can( 'read' ) ) {
		wp_die( esc_html__( 'You cannot manage actors.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	$user_id = get_current_user_id();
	$is_site_admin = current_user_can( 'manage_options' );
	$moderated_groups = function_exists( 'axismundi_forum_moderated_communities' )
		? axismundi_forum_moderated_communities( $user_id )
		: axismundi_actors_list_manageable_actors( $user_id );
	$all_groups       = $is_site_admin ? axismundi_actors_list_all_managed_actors() : array();
	$selected_id = isset( $_GET['group_id'] ) ? absint( $_GET['group_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only selection.
	$selected = $selected_id > 0 ? axismundi_actors_get_by_identity( $selected_id ) : null;
	$selected_is_manager = $selected instanceof Axismundi_Actor && axismundi_actors_can_manage( $selected, $user_id );
	$selected_is_moderator = $selected instanceof Axismundi_Actor && function_exists( 'axismundi_forum_user_can_moderate' ) && axismundi_forum_user_can_moderate( $selected->get_identity_id(), $user_id );
	if ( ! $selected instanceof Axismundi_Actor || ! $selected->is_managed() || ( ! $is_site_admin && ! $selected_is_manager && ! $selected_is_moderator ) ) {
		$selected = null;
	}
	$selected_is_manager = $selected instanceof Axismundi_Actor && axismundi_actors_can_manage( $selected, $user_id );
	$selected_is_moderator = $selected instanceof Axismundi_Actor && function_exists( 'axismundi_forum_user_can_moderate' ) && axismundi_forum_user_can_moderate( $selected->get_identity_id(), $user_id );
	$is_selected_public = $selected instanceof Axismundi_Actor && axismundi_actors_is_public_profile( $selected );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Managed actors', 'axismundi-actors' ); ?></h1>
		<?php axismundi_actors_admin_notice(); ?>
		<?php if ( $selected instanceof Axismundi_Actor ) : ?>
			<p><a href="<?php echo esc_url( axismundi_actors_managed_actors_admin_url() ); ?>">&larr; <?php esc_html_e( 'All managed actors', 'axismundi-actors' ); ?></a></p>
			<h2><?php echo esc_html( $selected->get_display_name() ?: '@' . $selected->get_preferred_username() ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Handle', 'axismundi-actors' ); ?></th><td><code>@<?php echo esc_html( $selected->get_preferred_username() ); ?></code></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Identity URI', 'axismundi-actors' ); ?></th><td><code><?php echo esc_html( $selected->get_uri() ); ?></code></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Status', 'axismundi-actors' ); ?></th><td><strong><?php echo esc_html( axismundi_actors_status_label( $selected ) ); ?></strong><?php if ( axismundi_actors_is_public_profile( $selected ) ) : ?> · <a href="<?php echo esc_url( $selected->get_profile_url() ); ?>"><?php esc_html_e( 'View public profile', 'axismundi-actors' ); ?></a><?php endif; ?></td></tr>
			</table>
			<?php if ( ! $selected_is_manager && $is_site_admin ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="axismundi_actors_claim_managed_group"><input type="hidden" name="identity_id" value="<?php echo esc_attr( (string) $selected->get_identity_id() ); ?>">
					<?php wp_nonce_field( 'ax_actors_claim_managed_group_' . $selected->get_identity_id() ); ?>
					<?php submit_button( __( 'Register myself as manager', 'axismundi-actors' ), 'secondary' ); ?>
				</form>
			<?php elseif ( $selected_is_manager ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="axismundi_actors_set_managed_group_visibility">
				<input type="hidden" name="identity_id" value="<?php echo esc_attr( (string) $selected->get_identity_id() ); ?>">
				<?php wp_nonce_field( 'ax_actors_managed_visibility_' . $selected->get_identity_id() ); ?>
				<input type="hidden" name="status" value="<?php echo $is_selected_public ? 'internal' : 'public'; ?>">
				<?php submit_button( $is_selected_public ? __( 'Make internal (unpublish)', 'axismundi-actors' ) : __( 'Publish (make public)', 'axismundi-actors' ), 'secondary' ); ?>
			</form>
			<?php axismundi_actors_media_form( $selected ); ?>
			<?php axismundi_actors_text_form( $selected ); ?>
			<?php axismundi_actors_profile_fields_form( $selected ); ?>
			<?php axismundi_actors_managers_form( $selected ); ?>
			<?php do_action( 'axismundi_actors_managed_group_admin_sections', $selected ); ?>
			<?php elseif ( $selected_is_moderator ) : ?>
			<?php do_action( 'axismundi_actors_managed_group_admin_sections', $selected ); ?>
			<?php endif; ?>
		<?php else : ?>
			<?php if ( current_user_can( 'edit_posts' ) ) : ?>
			<h2><?php esc_html_e( 'Create a managed actor', 'axismundi-actors' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="axismundi_actors_create_managed_actor">
				<?php wp_nonce_field( 'ax_actors_create_managed_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Kind', 'axismundi-actors' ); ?></th>
						<td>
							<?php
							/*
							 * Permanent, because the handle and the identity are. What a peer has cached as an
							 * Organization does not quietly become a Group, and the three mean different things
							 * on the wire: a Group has members and carries their posts, an Organization is a
							 * single entity that is not a person, and a Service publishes on a system's behalf.
							 */
							foreach (
								array(
									'Organization' => __( 'Organization — a company, institution, department, or association', 'axismundi-actors' ),
									'Group'        => __( 'Group — a community whose members post to it', 'axismundi-actors' ),
									'Service'      => __( 'Service — an automated publisher, such as a data feed', 'axismundi-actors' ),
								) as $ax_kind => $ax_kind_label
							) :
								?>
								<label style="display:block;margin-bottom:.35em">
									<input type="radio" name="actor_type" value="<?php echo esc_attr( $ax_kind ); ?>"<?php checked( 'Organization', $ax_kind ); ?>>
									<?php echo esc_html( $ax_kind_label ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Cannot be changed later, because peers cache what this actor is.', 'axismundi-actors' ); ?></p>
						</td>
					</tr>
					<tr><th scope="row"><label for="ax-managed-group-handle"><?php esc_html_e( 'Handle', 'axismundi-actors' ); ?></label></th><td><span>@</span><input id="ax-managed-group-handle" name="handle" type="text" class="regular-text" required><p class="description"><?php esc_html_e( 'Permanent federated address. Lowercase letters, numbers, and underscores only.', 'axismundi-actors' ); ?></p></td></tr>
					<tr><th scope="row"><label for="ax-managed-group-name"><?php esc_html_e( 'Name', 'axismundi-actors' ); ?></label></th><td><input id="ax-managed-group-name" name="name" type="text" class="regular-text" required></td></tr>
					<tr><th scope="row"><label for="ax-managed-group-summary"><?php esc_html_e( 'Summary', 'axismundi-actors' ); ?></label></th><td><textarea id="ax-managed-group-summary" name="summary" rows="4" class="large-text"></textarea></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Visibility', 'axismundi-actors' ); ?></th><td><label><input type="radio" name="visibility" value="public" checked> <?php esc_html_e( 'Public', 'axismundi-actors' ); ?></label><br><label><input type="radio" name="visibility" value="internal"> <?php esc_html_e( 'Internal', 'axismundi-actors' ); ?></label></td></tr>
				</table>
				<?php submit_button( __( 'Create actor', 'axismundi-actors' ) ); ?>
			</form>
			<?php endif; ?>
			<h2><?php esc_html_e( 'Groups I moderate', 'axismundi-actors' ); ?></h2>
			<?php if ( empty( $moderated_groups ) ) : ?>
				<p class="description"><?php esc_html_e( 'You do not moderate any communities yet.', 'axismundi-actors' ); ?></p>
			<?php else : ?>
				<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'axismundi-actors' ); ?></th><th><?php esc_html_e( 'Handle', 'axismundi-actors' ); ?></th><th><?php esc_html_e( 'Status', 'axismundi-actors' ); ?></th></tr></thead><tbody>
				<?php foreach ( $moderated_groups as $group ) : ?><tr><td><a href="<?php echo esc_url( axismundi_actors_managed_actors_admin_url( $group->get_identity_id() ) ); ?>"><?php echo esc_html( $group->get_display_name() ?: __( 'Untitled Group', 'axismundi-actors' ) ); ?></a></td><td><code>@<?php echo esc_html( $group->get_preferred_username() ); ?></code></td><td><?php echo esc_html( axismundi_actors_status_label( $group ) ); ?></td></tr><?php endforeach; ?>
				</tbody></table>
			<?php endif; ?>
			<?php if ( $is_site_admin && ! empty( $all_groups ) ) : ?>
				<h2><?php esc_html_e( 'All local managed actors', 'axismundi-actors' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Site-administrator recovery list. Register yourself as manager from a Group record before changing it.', 'axismundi-actors' ); ?></p>
				<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Name', 'axismundi-actors' ); ?></th><th><?php esc_html_e( 'Handle', 'axismundi-actors' ); ?></th><th><?php esc_html_e( 'Status', 'axismundi-actors' ); ?></th></tr></thead><tbody>
				<?php foreach ( $all_groups as $group ) : ?><tr><td><a href="<?php echo esc_url( axismundi_actors_managed_actors_admin_url( $group->get_identity_id() ) ); ?>"><?php echo esc_html( $group->get_display_name() ?: __( 'Untitled Group', 'axismundi-actors' ) ); ?></a></td><td><code>@<?php echo esc_html( $group->get_preferred_username() ); ?></code></td><td><?php echo esc_html( axismundi_actors_status_label( $group ) ); ?></td></tr><?php endforeach; ?>
				</tbody></table>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Remote Actor lookup and cache inspector. Network writes happen only through the
 * nonce-protected POST action below; this renderer is read-only.
 *
 * @return void
 */
function axismundi_actors_render_remote_admin_page() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot inspect remote Actors.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	$selected_id  = isset( $_GET['actor_id'] ) ? absint( $_GET['actor_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only selection.
	$selected     = $selected_id > 0 ? axismundi_actors_get_by_identity( $selected_id ) : null;
	$actor_search = isset( $_GET['ax_actor_search'] ) ? sanitize_text_field( wp_unslash( $_GET['ax_actor_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search.
	$actor_page   = isset( $_GET['ax_actor_page'] ) ? max( 1, absint( $_GET['ax_actor_page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
	$actor_total  = axismundi_actors_count_remote_actors( $actor_search );
	$actor_pages  = max( 1, (int) ceil( $actor_total / 50 ) );
	$actor_page   = min( $actor_page, $actor_pages );
	$remote_actors = axismundi_actors_get_remote_actors( 50, ( $actor_page - 1 ) * 50, $actor_search );
	/* translators: %s: number of cached remote Actors. */
	$actor_count_label = sprintf( _n( '%s item', '%s items', $actor_total, 'axismundi-actors' ), number_format_i18n( $actor_total ) );
	if ( $selected instanceof Axismundi_Actor && $selected->is_local() ) {
		$selected = null;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Remote Actors', 'axismundi-actors' ); ?></h1>
		<?php axismundi_actors_remote_admin_notice(); ?>
		<p><?php esc_html_e( 'Resolve an acct address, a /@handle profile URL, or a canonical ActivityStreams Actor URL. A successful lookup ensures the Actor and instance caches exist.', 'axismundi-actors' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="axismundi_actors_discover_remote">
			<?php wp_nonce_field( 'ax_actors_discover_remote' ); ?>
			<p class="search-box">
				<label class="screen-reader-text" for="ax-remote-actor-input"><?php esc_html_e( 'Remote Actor address', 'axismundi-actors' ); ?></label>
				<input id="ax-remote-actor-input" type="search" name="remote_actor" class="regular-text" placeholder="@user@example.social or https://example.social/@user" required>
				<?php submit_button( __( 'Fetch Actor', 'axismundi-actors' ), 'primary', 'submit', false ); ?>
			</p>
		</form>

		<?php if ( $selected instanceof Axismundi_Actor ) : ?>
			<?php axismundi_actors_render_remote_actor_detail( $selected ); ?>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Cached remote Actors', 'axismundi-actors' ); ?></h2>
		<form method="get" action="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">
			<input type="hidden" name="page" value="axismundi-remote-actors">
			<label class="screen-reader-text" for="ax-remote-actor-search"><?php esc_html_e( 'Search cached remote Actors', 'axismundi-actors' ); ?></label>
			<input id="ax-remote-actor-search" type="search" name="ax_actor_search" value="<?php echo esc_attr( $actor_search ); ?>" placeholder="<?php esc_attr_e( 'Handle, name, or Actor URI', 'axismundi-actors' ); ?>">
			<?php submit_button( __( 'Search cached Actors', 'axismundi-actors' ), 'secondary', 'submit', false ); ?>
			<span class="displaying-num"><?php echo esc_html( $actor_count_label ); ?></span>
		</form>
		<?php axismundi_actors_render_remote_actor_table( $remote_actors ); ?>
		<?php if ( $actor_pages > 1 ) : ?>
			<div class="tablenav"><div class="tablenav-pages">
				<?php
				echo wp_kses_post(
					paginate_links(
						array(
							'base'      => add_query_arg( array( 'ax_actor_page' => '%#%', 'ax_actor_search' => $actor_search ), axismundi_actors_remote_admin_url() ),
							'format'    => '',
							'current'   => $actor_page,
							'total'     => $actor_pages,
							'prev_text' => __( '&laquo; Previous', 'axismundi-actors' ),
							'next_text' => __( 'Next &raquo;', 'axismundi-actors' ),
						)
					)
				);
				?>
			</div></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Cached instances', 'axismundi-actors' ); ?></h2>
		<?php axismundi_actors_render_instance_table( axismundi_actors_get_instances() ); ?>

		<h2><?php esc_html_e( 'Remote image cache', 'axismundi-actors' ); ?></h2>
		<p><?php esc_html_e( 'Preview or purge all cached remote avatar/header mappings. Physical files are removed only when no Actor still references their content hash.', 'axismundi-actors' ); ?></p>
		<?php
		$ax_asset_due       = axismundi_actors_asset_due_count();
		$ax_asset_scheduled = wp_next_scheduled( 'axismundi_actors_process_asset_batch' );
		$ax_asset_worker_status = sprintf(
			/* translators: 1: number of due image-cache rows, 2: scheduled UTC time or not scheduled. */
			__( 'Worker status: %1$s due; next run %2$s.', 'axismundi-actors' ),
			number_format_i18n( $ax_asset_due ),
			false === $ax_asset_scheduled ? __( 'not scheduled', 'axismundi-actors' ) : gmdate( 'Y-m-d H:i:s', $ax_asset_scheduled ) . ' UTC'
		);
		?>
		<p><strong><?php echo esc_html( $ax_asset_worker_status ); ?></strong></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="axismundi_actors_asset_settings">
			<?php wp_nonce_field( 'ax_actors_asset_settings' ); ?>
			<label><input type="checkbox" name="webp_enabled" value="1" <?php checked( axismundi_actors_asset_webp_enabled() ); ?>> <?php esc_html_e( 'Generate WebP candidates when they are smaller than JPEG/PNG', 'axismundi-actors' ); ?></label>
			<p class="description"><?php esc_html_e( 'Disabled by default to reduce image-processing cost. Changing this queues an asynchronous cache rebuild.', 'axismundi-actors' ); ?></p>
			<?php submit_button( __( 'Save image settings', 'axismundi-actors' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php axismundi_actors_render_asset_cache_action( 'inspect', 'all', '', __( 'Preview full cache purge', 'axismundi-actors' ) ); ?>
		<?php axismundi_actors_render_asset_cache_action( 'purge', 'all', '', __( 'Purge full image cache', 'axismundi-actors' ), true ); ?>
	</div>
	<?php
}

/** Remote lookup-specific success/error notice. */
function axismundi_actors_remote_admin_notice() : void {
	if ( isset( $_GET['ax_actor_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only status flag.
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Remote Actor and instance cache updated.', 'axismundi-actors' ) . '</p></div>';
	}
	if ( isset( $_GET['ax_actor_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- escaped display-only message.
		echo '<div class="notice notice-error"><p>' . esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['ax_actor_error'] ) ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	if ( isset( $_GET['ax_asset_rows'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only operation result.
		$rows = absint( $_GET['ax_asset_rows'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$dirs = isset( $_GET['ax_asset_dirs'] ) ? absint( $_GET['ax_asset_dirs'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		/* translators: 1: number of cache mappings, 2: number of content directories. */
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( sprintf( __( 'Remote image cache operation: %1$d mapping(s), %2$d content directorie(s).', 'axismundi-actors' ), $rows, $dirs ) ) . '</p></div>';
	}
}

/**
 * @param string $operation refresh|inspect|purge.
 * @param string $scope actor|instance|all.
 * @param string $value Identity id or host.
 * @param string $label Button label.
 * @param bool   $destructive Whether to require browser confirmation.
 */
function axismundi_actors_render_asset_cache_action( string $operation, string $scope, string $value, string $label, bool $destructive = false ) : void {
	$confirm = $destructive ? "return window.confirm('" . esc_js( __( 'Purge this remote image cache scope?', 'axismundi-actors' ) ) . "');" : '';
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:8px 8px 8px 0;">
		<input type="hidden" name="action" value="axismundi_actors_asset_cache">
		<input type="hidden" name="operation" value="<?php echo esc_attr( $operation ); ?>">
		<input type="hidden" name="scope" value="<?php echo esc_attr( $scope ); ?>">
		<input type="hidden" name="scope_value" value="<?php echo esc_attr( $value ); ?>">
		<?php wp_nonce_field( 'ax_actors_asset_cache' ); ?>
		<button type="submit" class="button<?php echo $destructive ? ' button-link-delete' : ''; ?>"<?php echo '' !== $confirm ? ' onclick="' . esc_attr( $confirm ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- complete attribute is escaped. ?>><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
}

/**
 * Human label for a tri-state policy flag: unreported (NULL) is shown distinctly from
 * an explicit yes/no so an admin can tell "the remote never declared it" from "off".
 *
 * @param bool|null $flag Policy value.
 * @return string
 */
function axismundi_actors_policy_flag_label( ?bool $flag ) : string {
	if ( null === $flag ) {
		return __( 'not reported', 'axismundi-actors' );
	}
	return $flag ? __( 'yes', 'axismundi-actors' ) : __( 'no', 'axismundi-actors' );
}

/**
 * Refresh input that re-verifies a cached remote Actor's human acct address.
 *
 * A canonical Actor URI can refresh its payload, but it cannot prove the
 * WebFinger acct address that owns the profile hub. Prefer that profile URL
 * when the address was never recorded, so a refresh repairs older cache rows
 * and makes their `/@name@host` alias routable.
 *
 * @param Axismundi_Actor $actor Cached remote Actor.
 * @return string Acct, profile URL, or canonical URI.
 */
function axismundi_actors_remote_refresh_input( Axismundi_Actor $actor ) : string {
	$acct = axismundi_actors_primary_acct_address( $actor );
	if ( '' !== $acct ) {
		return '@' . $acct;
	}
	$profile_url = $actor->get_profile_url();
	return '' !== $profile_url ? $profile_url : $actor->get_uri();
}

/** @param Axismundi_Actor $actor Remote actor. @return void */
function axismundi_actors_render_remote_actor_detail( Axismundi_Actor $actor ) : void {
	$payload   = axismundi_actors_get_remote_payload( $actor->get_identity_id() );
	$endpoints = axismundi_actors_get_endpoints( $actor );
	$host      = axismundi_actors_webfinger_authority_from_url( $actor->get_uri() );
	$instance  = '' !== $host ? axismundi_actors_get_instance( $host ) : null;
	$addresses = array_values( array_filter( axismundi_actors_get_addresses( $actor->get_identity_id() ), static fn( array $row ) : bool => 'acct' === $row['address_type'] ) );
	$assets    = axismundi_actors_asset_scope_rows( 'actor', (string) $actor->get_identity_id() );
	$relations = axismundi_actors_get_identity_relations( $actor->get_identity_id() );
	?>
	<hr>
	<h2><?php echo esc_html( $actor->get_display_name() ?: $actor->get_preferred_username() ); ?></h2>
	<table class="widefat striped" role="presentation">
		<tbody>
			<tr><th><?php esc_html_e( 'Actor URI', 'axismundi-actors' ); ?></th><td><a href="<?php echo esc_url( $actor->get_uri() ); ?>" rel="noreferrer noopener" target="_blank"><code><?php echo esc_html( $actor->get_uri() ); ?></code></a></td></tr>
			<tr><th><?php esc_html_e( 'Type', 'axismundi-actors' ); ?></th><td><?php echo esc_html( $actor->get_type() ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Preferred username', 'axismundi-actors' ); ?></th><td><?php echo esc_html( $actor->get_preferred_username() ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Verified addresses', 'axismundi-actors' ); ?></th><td><?php echo esc_html( implode( ', ', array_column( $addresses, 'address' ) ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Endpoints', 'axismundi-actors' ); ?></th><td><?php foreach ( $endpoints as $type => $uri ) : ?><div><strong><?php echo esc_html( $type ); ?></strong>: <code><?php echo esc_html( $uri ); ?></code></div><?php endforeach; ?></td></tr>
			<tr><th><?php esc_html_e( 'Instance', 'axismundi-actors' ); ?></th><td><?php echo esc_html( $instance ? trim( (string) ( $instance['software_name'] ?? '' ) . ' ' . (string) ( $instance['software_version'] ?? '' ) ) : $host ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Manually approves followers', 'axismundi-actors' ); ?></th><td><?php echo esc_html( axismundi_actors_policy_flag_label( $actor->get_policy_flag( 'manually_approves_followers' ) ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Discoverable', 'axismundi-actors' ); ?></th><td><?php echo esc_html( axismundi_actors_policy_flag_label( $actor->get_policy_flag( 'discoverable' ) ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Indexable', 'axismundi-actors' ); ?></th><td><?php echo esc_html( axismundi_actors_policy_flag_label( $actor->get_policy_flag( 'indexable' ) ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Follow collections', 'axismundi-actors' ); ?></th><td><?php echo esc_html( $actor->get_follow_collections_visibility() ?? esc_html__( 'not reported', 'axismundi-actors' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Published', 'axismundi-actors' ); ?></th><td><?php echo esc_html( '' !== $actor->get_published_at() ? $actor->get_published_at() : esc_html__( 'not reported', 'axismundi-actors' ) ); ?></td></tr>
			<?php
			$ax_keys      = axismundi_actors_get_keys( $actor->get_identity_id(), 'active' );
			$ax_fetch     = axismundi_actors_get_fetch_state( $actor->get_identity_id() );
			$ax_key_label = empty( $ax_keys )
				? esc_html__( 'none captured', 'axismundi-actors' )
				: sprintf(
					/* translators: 1: key URI, 2: fingerprint prefix. */
					__( '%1$s (fp %2$s…)', 'axismundi-actors' ),
					(string) $ax_keys[0]['key_uri'],
					substr( (string) $ax_keys[0]['fingerprint'], 0, 12 )
				);
			?>
			<tr><th><?php esc_html_e( 'Public key', 'axismundi-actors' ); ?></th><td><code><?php echo esc_html( $ax_key_label ); ?></code></td></tr>
			<tr><th><?php esc_html_e( 'Identity relations', 'axismundi-actors' ); ?></th><td>
				<?php if ( empty( $relations ) ) : ?>
					<?php esc_html_e( 'none reported', 'axismundi-actors' ); ?>
				<?php else : ?>
					<?php foreach ( $relations as $relation ) : ?>
						<div><strong><?php echo esc_html( (string) $relation['relation_type'] ); ?></strong>: <code><?php echo esc_html( (string) $relation['target_uri'] ); ?></code> (<?php echo esc_html( (string) $relation['verification_state'] ); ?>)</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</td></tr>
			<tr><th><?php esc_html_e( 'Last fetched', 'axismundi-actors' ); ?></th><td><?php echo esc_html( $ax_fetch && ! empty( $ax_fetch['fetched_at'] ) ? (string) $ax_fetch['fetched_at'] : esc_html__( 'never', 'axismundi-actors' ) ); ?></td></tr>
		</tbody>
	</table>
	<h3><?php esc_html_e( 'Avatar and header cache', 'axismundi-actors' ); ?></h3>
	<p><a class="button" href="<?php echo esc_url( axismundi_actors_profile_hub_url( $actor ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View cached profile', 'axismundi-actors' ); ?></a></p>
	<?php do_action( 'axismundi_actors_remote_actor_actions', $actor ); ?>
	<?php if ( empty( $assets ) ) : ?>
		<p><?php esc_html_e( 'No remote image sources were reported.', 'axismundi-actors' ); ?></p>
	<?php else : ?>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Role', 'axismundi-actors' ); ?></th><th><?php esc_html_e( 'Status', 'axismundi-actors' ); ?></th><th><?php esc_html_e( 'Source', 'axismundi-actors' ); ?></th><th><?php esc_html_e( 'Next refresh', 'axismundi-actors' ); ?></th></tr></thead><tbody>
		<?php foreach ( $assets as $asset ) : ?>
			<tr><td><?php echo esc_html( (string) $asset['asset_role'] ); ?></td><td><?php echo esc_html( (string) $asset['fetch_status'] ); ?></td><td><code><?php echo esc_html( (string) $asset['source_uri'] ); ?></code></td><td><?php echo esc_html( (string) $asset['next_refresh_at'] ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	<?php endif; ?>
	<?php axismundi_actors_render_asset_cache_action( 'refresh', 'actor', (string) $actor->get_identity_id(), __( 'Refresh cached images', 'axismundi-actors' ) ); ?>
	<?php axismundi_actors_render_asset_cache_action( 'inspect', 'actor', (string) $actor->get_identity_id(), __( 'Preview Actor cache purge', 'axismundi-actors' ) ); ?>
	<?php axismundi_actors_render_asset_cache_action( 'purge', 'actor', (string) $actor->get_identity_id(), __( 'Purge Actor image cache', 'axismundi-actors' ), true ); ?>
	<?php if ( '' !== $host ) : ?>
		<?php axismundi_actors_render_asset_cache_action( 'inspect', 'instance', $host, __( 'Preview instance cache purge', 'axismundi-actors' ) ); ?>
		<?php axismundi_actors_render_asset_cache_action( 'purge', 'instance', $host, __( 'Purge instance image cache', 'axismundi-actors' ), true ); ?>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="axismundi_actors_discover_remote">
		<input type="hidden" name="remote_actor" value="<?php echo esc_attr( axismundi_actors_remote_refresh_input( $actor ) ); ?>">
		<?php wp_nonce_field( 'ax_actors_discover_remote' ); ?>
		<?php submit_button( __( 'Refresh cached Actor', 'axismundi-actors' ), 'secondary', 'submit', false ); ?>
	</form>
	<details>
		<summary><?php esc_html_e( 'Raw Actor JSON', 'axismundi-actors' ); ?></summary>
		<textarea class="large-text code" rows="18" readonly><?php echo esc_textarea( (string) wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
	</details>
	<?php
}

/** @param Axismundi_Actor[] $actors Remote actors. @return void */
function axismundi_actors_render_remote_actor_table( array $actors ) : void {
	if ( empty( $actors ) ) {
		echo '<p>' . esc_html__( 'No remote Actors cached.', 'axismundi-actors' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Actor', 'axismundi-actors' ) . '</th><th>' . esc_html__( 'Type', 'axismundi-actors' ) . '</th><th>' . esc_html__( 'Host', 'axismundi-actors' ) . '</th><th>' . esc_html__( 'Status', 'axismundi-actors' ) . '</th></tr></thead><tbody>';
	foreach ( $actors as $actor ) {
		$url = add_query_arg( 'actor_id', $actor->get_identity_id(), axismundi_actors_remote_admin_url() );
		echo '<tr><td><a href="' . esc_url( $url ) . '">' . esc_html( $actor->get_display_name() ?: $actor->get_preferred_username() ) . '</a><br><code>' . esc_html( $actor->get_uri() ) . '</code></td><td>' . esc_html( $actor->get_type() ) . '</td><td>' . esc_html( axismundi_actors_webfinger_authority_from_url( $actor->get_uri() ) ) . '</td><td>' . esc_html( $actor->get_status() ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

/** @param array<int,array<string,mixed>> $instances Cached instances. @return void */
function axismundi_actors_render_instance_table( array $instances ) : void {
	if ( empty( $instances ) ) {
		echo '<p>' . esc_html__( 'No remote instances cached.', 'axismundi-actors' ) . '</p>';
		return;
	}
	$has_emoji = function_exists( 'axismundi_emoji_count_authority' );
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Host', 'axismundi-actors' ) . '</th><th>' . esc_html__( 'Software', 'axismundi-actors' ) . '</th><th>' . esc_html__( 'Registrations', 'axismundi-actors' ) . '</th><th>' . esc_html__( 'Fetched', 'axismundi-actors' ) . '</th><th>' . esc_html__( 'Status', 'axismundi-actors' ) . '</th>' . ( $has_emoji ? '<th>' . esc_html__( 'Emojis', 'axismundi-actors' ) . '</th>' : '' ) . '</tr></thead><tbody>';
	foreach ( $instances as $instance ) {
		$registrations = null === $instance['open_registrations'] ? '—' : ( (int) $instance['open_registrations'] ? __( 'Open', 'axismundi-actors' ) : __( 'Closed', 'axismundi-actors' ) );
		$host          = (string) $instance['host'];
		$emoji         = $has_emoji ? axismundi_emoji_count_authority( $host ) : 0;
		$emoji_cell    = $has_emoji ? ( 0 === $emoji ? '—' : '<a href="' . esc_url( add_query_arg( 'authority', $host, admin_url( 'admin.php?page=axismundi-emoji' ) ) ) . '">' . esc_html( number_format_i18n( $emoji ) ) . '</a>' ) : '';
		echo '<tr id="ax-instance-' . esc_attr( substr( hash( 'sha256', $host ), 0, 12 ) ) . '"><td><code>' . esc_html( $host ) . '</code></td><td>' . esc_html( trim( (string) $instance['software_name'] . ' ' . (string) $instance['software_version'] ) ) . '</td><td>' . esc_html( $registrations ) . '</td><td>' . esc_html( (string) $instance['fetched_at'] ) . '</td><td>' . esc_html( (string) $instance['fetch_status'] ) . '</td>' . ( $has_emoji ? '<td>' . $emoji_cell . '</td>' : '' ) . '</tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constructed escaped HTML.
	}
	echo '</tbody></table>';
}

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
 * Warn before plain permalinks strand already-issued Actor identity URLs.
 *
 * `/@handle` and `/actors/{uuid}` are deliberately independent of the chosen
 * pretty permalink structure. Plain permalinks are different: Apache does not
 * pass either path to WordPress, while immutable Actor IDs may already have
 * been published through WebFinger and ActivityPub.
 *
 * @return void
 */
function axismundi_actors_plain_permalink_notice() : void {
	if ( ! current_user_can( 'manage_options' ) || '' !== (string) get_option( 'permalink_structure', '' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'options-permalink' !== $screen->id ) {
		return;
	}
	global $wpdb;
	$identities = axismundi_actors_identities_table();
	$issued     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$identities}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bundled custom table, a settings-screen warning, and no user input.
	if ( $issued <= 0 ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s</p></div>',
		esc_html__( 'Axismundi Actor profiles require a non-plain permalink structure. Changing this site to Plain would make already-issued /@handle and /actors/{uuid} URLs unreachable.', 'axismundi-actors' )
	);
}
add_action( 'admin_notices', 'axismundi_actors_plain_permalink_notice' );

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
	<p class="description"><?php esc_html_e( 'Avatar, header image, and profile translations can be set after activation.', 'axismundi-actors' ); ?></p>
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
	<?php axismundi_actors_media_form( $actor ); ?>
	<?php axismundi_actors_text_form( $actor ); ?>
	<?php axismundi_actors_profile_fields_form( $actor ); ?>
	<?php axismundi_actors_follow_collections_form( $actor ); ?>
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
	?>
	<table class="form-table" role="presentation">
		<tr><th scope="row"><?php esc_html_e( 'Handle', 'axismundi-actors' ); ?></th><td><code>@<?php echo esc_html( $actor->get_preferred_username() ); ?></code></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Actor type', 'axismundi-actors' ); ?></th><td><?php esc_html_e( 'Application', 'axismundi-actors' ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Status', 'axismundi-actors' ); ?></th><td><strong><?php esc_html_e( 'Disabled', 'axismundi-actors' ); ?></strong></td></tr>
	</table>
	<p class="description"><?php esc_html_e( 'The Site Actor is reserved for a future Instance Actor implementation and cannot publish or be configured yet.', 'axismundi-actors' ); ?></p>
	</div>
	<?php
}

/* -------------------------------------------------------------------------- *
 * Avatar / header media pickers (core Media modal; assets on these screens only).
 * -------------------------------------------------------------------------- */

/**
 * Enqueue the Media modal + picker script only on the actor screens.
 *
 * @param string $hook Current admin page hook suffix.
 * @return void
 */
function axismundi_actors_enqueue_media_picker( string $hook ) : void {
	if ( ! in_array( $hook, array( 'users_page_axismundi-actor-profile', 'users_page_axismundi-managed-groups', 'settings_page_axismundi-actor-site' ), true ) ) {
		return;
	}
	wp_enqueue_media();
	$base = dirname( __DIR__ ) . '/axismundi-actors.php';
	$js   = dirname( __DIR__ ) . '/assets/actor-media.js';
	wp_enqueue_script(
		'axismundi-actors-media',
		plugins_url( 'assets/actor-media.js', $base ),
		array( 'jquery' ),
		file_exists( $js ) ? (string) filemtime( $js ) : false,
		true
	);
	$fields_js  = dirname( __DIR__ ) . '/assets/actor-profile-fields.js';
	$fields_css = dirname( __DIR__ ) . '/assets/actor-profile-fields.css';
	wp_enqueue_script(
		'axismundi-actors-profile-fields',
		plugins_url( 'assets/actor-profile-fields.js', $base ),
		array(),
		file_exists( $fields_js ) ? (string) filemtime( $fields_js ) : false,
		true
	);
	wp_enqueue_style(
		'axismundi-actors-profile-fields',
		plugins_url( 'assets/actor-profile-fields.css', $base ),
		array(),
		file_exists( $fields_css ) ? (string) filemtime( $fields_css ) : false
	);
}
add_action( 'admin_enqueue_scripts', 'axismundi_actors_enqueue_media_picker' );

/**
 * One avatar/header picker field (preview + hidden id + select/remove buttons).
 *
 * @param string $role          avatar | header.
 * @param int    $attachment_id Current attachment id (0 = none).
 * @return void
 */
function axismundi_actors_media_field( string $role, int $attachment_id ) : void {
	?>
	<div class="ax-actor-media-field" data-role="<?php echo esc_attr( $role ); ?>">
		<div class="ax-actor-media-preview">
			<?php
			if ( $attachment_id > 0 ) {
				echo wp_get_attachment_image( $attachment_id, 'thumbnail', false, array( 'style' => 'max-width:150px;height:auto;' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-generated image markup.
			}
			?>
		</div>
		<input type="hidden" name="<?php echo esc_attr( $role ); ?>_attachment_id" value="<?php echo esc_attr( (string) $attachment_id ); ?>">
		<button type="button" class="button ax-actor-media-select"><?php esc_html_e( 'Select image', 'axismundi-actors' ); ?></button>
		<button type="button" class="button-link ax-actor-media-remove"><?php esc_html_e( 'Remove', 'axismundi-actors' ); ?></button>
	</div>
	<?php
}

/**
 * The avatar + header form for one actor (Person management or site settings).
 *
 * @param Axismundi_Actor $actor Actor.
 * @return void
 */
function axismundi_actors_media_form( Axismundi_Actor $actor ) : void {
	?>
	<h2><?php esc_html_e( 'Avatar & header', 'axismundi-actors' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="axismundi_actors_set_media">
		<input type="hidden" name="identity_id" value="<?php echo esc_attr( (string) $actor->get_identity_id() ); ?>">
		<?php wp_nonce_field( 'ax_actors_media_' . $actor->get_identity_id() ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Avatar', 'axismundi-actors' ); ?></th>
				<td><?php axismundi_actors_media_field( 'avatar', $actor->get_avatar_attachment_id() ); ?><p class="description"><?php esc_html_e( 'Square image. Falls back to your Gravatar / site icon when empty.', 'axismundi-actors' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Header image', 'axismundi-actors' ); ?></th>
				<td><?php axismundi_actors_media_field( 'header', $actor->get_header_attachment_id() ); ?><p class="description"><?php esc_html_e( 'Wide cover image. Not shown when empty.', 'axismundi-actors' ); ?></p></td>
			</tr>
		</table>
		<?php submit_button( __( 'Save images', 'axismundi-actors' ) ); ?>
	</form>
	<?php
}

/**
 * Language currently selected for editing on an Actor admin screen.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return string
 */
function axismundi_actors_admin_text_language( Axismundi_Actor $actor ) : string {
	$requested = isset( $_GET['ax_actor_lang'] ) ? sanitize_text_field( wp_unslash( $_GET['ax_actor_lang'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only language selector.
	$language  = axismundi_actors_normalize_language_tag( $requested );
	return '' !== $language ? $language : ( $actor->get_default_language() ?: axismundi_actors_site_language() );
}

/**
 * Render explicit multilingual Actor text editing. Empty fields remain live WP
 * fallbacks and do not create rows.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return void
 */
function axismundi_actors_text_form( Axismundi_Actor $actor ) : void {
	$map       = axismundi_actors_get_text_map( $actor->get_identity_id() );
	$primary   = axismundi_actors_serialization_language( $actor );
	$language  = axismundi_actors_admin_text_language( $actor );
	$secondary = array_values(
		array_unique(
			array_filter(
				array_merge( array_keys( $map ), array( $language ) ),
				static function ( $candidate ) use ( $primary ) : bool {
					return is_string( $candidate ) && '' !== $candidate && $candidate !== $primary;
				}
			)
		)
	);
	$languages = array_merge( array( $primary ), $secondary );
	$language_options = axismundi_actors_profile_language_options( $languages );
	$back      = axismundi_actors_management_back_url( $actor );
	$add_url   = remove_query_arg( 'ax_actor_lang', $back );
	$adding_translation = isset( $_GET['ax_actor_add_language'] ) && '1' === (string) $_GET['ax_actor_add_language']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- changes no state.
	/*
	 * The name parts belong to the base profile, so they are edited in the Actor's own language and
	 * nowhere else. Switching to another language offers a plain name box, because a translation of a
	 * profile is a written-out name and nothing more -- nobody should have to re-enter `Jiwoon` and
	 * `Kim` as components to say their profile in English too.
	 */
	/*
	 * The name details belong to the primary profile, and only to it. A secondary language is the same
	 * profile written out again -- a name and a summary -- so it is never asked which parts of a name
	 * it holds, and switching languages never moves anything.
	 */
	$person_name = 'Person' === $actor->get_type() && $actor->is_local()
		? axismundi_actors_person_profile( $actor->get_identity_id() )
		: array();
	/*
	 * The label and the pronunciation are edited on the primary profile, and nothing else about a name
	 * is edited here at all. The parts of a name live on the contact card now, and the box below is a
	 * plain string: typed here, or followed from a card by a binding, or left as it was.
	 */
	$structured = 'Person' === $actor->get_type() && $actor->is_local() && $language === $primary;
	?>
	<h2><?php esc_html_e( 'Profile languages', 'axismundi-actors' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Translations are optional. Empty fields continue to use the live WordPress profile or site value.', 'axismundi-actors' ); ?></p>
	<p>
		<?php foreach ( $languages as $candidate ) : ?>
			<a class="button <?php echo $candidate === $language ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'ax_actor_lang', $candidate, $back ) ); ?>">
				<?php echo $candidate === $primary ? esc_html__( 'primary', 'axismundi-actors' ) . ' &middot; ' : ''; ?><?php echo esc_html( $candidate ); ?>
			</a>
		<?php endforeach; ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'The primary language is what other servers get when they ask for no language in particular. Every other language is the same profile written out again.', 'axismundi-actors' ); ?>
	</p>
	<p><a class="button" href="<?php echo esc_url( add_query_arg( 'ax_actor_add_language', '1', $add_url ) ); ?>"><?php esc_html_e( 'Add translated profile', 'axismundi-actors' ); ?></a></p>
	<?php if ( $adding_translation ) : ?>
		<form method="get" action="<?php echo esc_url( $add_url ); ?>">
			<label for="ax-actor-add-language"><?php esc_html_e( 'Profile language', 'axismundi-actors' ); ?></label>
			<input id="ax-actor-add-language" name="ax_actor_lang" list="ax-actor-language-options" class="regular-text" placeholder="en-US" required>
			<datalist id="ax-actor-language-options">
				<?php foreach ( $language_options as $tag => $label ) : ?>
					<option value="<?php echo esc_attr( $tag ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</datalist>
			<?php submit_button( __( 'Open translated profile', 'axismundi-actors' ), 'secondary', 'submit', false ); ?>
		</form>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="axismundi_actors_set_texts">
		<input type="hidden" name="identity_id" value="<?php echo esc_attr( (string) $actor->get_identity_id() ); ?>">
		<input type="hidden" name="language_tag" value="<?php echo esc_attr( $language ); ?>">
		<?php wp_nonce_field( 'ax_actors_texts_' . $actor->get_identity_id() ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ax-actor-primary-language"><?php esc_html_e( 'Profile language', 'axismundi-actors' ); ?></label></th>
				<td>
					<select id="ax-actor-primary-language" name="profile_language">
						<?php foreach ( $language_options as $tag => $label ) : ?>
							<option value="<?php echo esc_attr( $tag ); ?>" <?php selected( $language, $tag ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'This is the language key this profile is filed under: changing it re-files the same name and summary, and never overwrites another language that is already written.', 'axismundi-actors' ); ?>
					</p>
					<?php if ( $language !== $primary ) : ?>
						<p><button type="submit" class="button button-secondary" name="make_primary" value="1"><?php esc_html_e( 'Set this profile as primary', 'axismundi-actors' ); ?></button></p>
						<p class="description">
							<?php
							printf(
								/* translators: %s: BCP 47 tag of the current primary language. */
								esc_html__( 'A separate decision. Re-filing a profile changes which language it is written in; making it primary changes what peers get when they ask for no language in particular, and moves the name details away from %s.', 'axismundi-actors' ),
								esc_html( $primary )
							);
							?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ax-actor-name"><?php esc_html_e( 'Name', 'axismundi-actors' ); ?></label></th>
				<td>
					<input id="ax-actor-name" name="name" value="<?php echo esc_attr( (string) ( $map[ $language ]['name'] ?? '' ) ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ax-actor-summary"><?php esc_html_e( 'Summary', 'axismundi-actors' ); ?></label></th>
				<td><textarea id="ax-actor-summary" name="summary" rows="4" class="large-text"><?php echo esc_textarea( $map[ $language ]['summary'] ?? '' ); ?></textarea></td>
			</tr>
			<?php if ( $structured ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'How this name is shown and said', 'axismundi-actors' ); ?></th>
					<td>
						<?php
						/*
						 * A label somebody chose for this Actor, and how the name is pronounced. The parts it
						 * is made of are not here: they belong to the contact card, which is where a title and
						 * a credential already lived, and holding a second copy of them is what let the two
						 * disagree.
						 */
						?>
						<p><label for="ax-actor-custom-display-name" style="display:block"><?php esc_html_e( 'Shown as', 'axismundi-actors' ); ?></label><input id="ax-actor-custom-display-name" name="display_name" value="<?php echo esc_attr( (string) ( $person_name['display_name'] ?? '' ) ); ?>" class="regular-text"></p>
						<p class="description"><?php esc_html_e( 'Left empty, this Actor is shown as the name written above for its primary language.', 'axismundi-actors' ); ?></p>
						<?php /* One name, said one way: a pronunciation belongs to the parts and not to each translation. */ ?>
						<p>
							<label for="ax-actor-ph-system" style="display:block"><?php esc_html_e( 'Pronunciation notation or script', 'axismundi-actors' ); ?></label>
							<select id="ax-actor-ph-system" name="phonetic_system">
								<option value=""><?php esc_html_e( 'None', 'axismundi-actors' ); ?></option>
								<?php foreach ( AXISMUNDI_ACTORS_PHONETIC_SYSTEMS as $ax_system ) : ?>
									<option value="<?php echo esc_attr( $ax_system ); ?>" <?php selected( (string) ( $person_name['phonetic_system'] ?? '' ), $ax_system ); ?>><?php echo esc_html( $ax_system ); ?></option>
								<?php endforeach; ?>
							</select>
							<input name="phonetic_script" value="<?php echo esc_attr( (string) ( $person_name['phonetic_script'] ?? '' ) ); ?>" class="small-text" placeholder="Hira" aria-label="<?php esc_attr_e( 'Script subtag', 'axismundi-actors' ); ?>">
						</p>
						<div style="max-width:26em">
							<p><label for="ax-ph-given" style="display:block"><?php esc_html_e( 'Pronunciation of given name', 'axismundi-actors' ); ?></label><input id="ax-ph-given" name="phonetic_given" value="<?php echo esc_attr( (string) ( $person_name['phonetic_given'] ?? '' ) ); ?>" class="regular-text"></p>
							<p><label for="ax-ph-surname" style="display:block"><?php esc_html_e( 'Pronunciation of family name', 'axismundi-actors' ); ?></label><input id="ax-ph-surname" name="phonetic_surname" value="<?php echo esc_attr( (string) ( $person_name['phonetic_surname'] ?? '' ) ); ?>" class="regular-text"></p>
							<p><label for="ax-ph-surname2" style="display:block"><?php esc_html_e( 'Pronunciation of second family name', 'axismundi-actors' ); ?></label><input id="ax-ph-surname2" name="phonetic_surname2" value="<?php echo esc_attr( (string) ( $person_name['phonetic_surname2'] ?? '' ) ); ?>" class="regular-text"></p>
						</div>
					</td>
				</tr>
			<?php endif; ?>
		</table>
		<?php submit_button( __( 'Save profile language', 'axismundi-actors' ) ); ?>
	</form>
	<?php
}

/** Render local Actor links as ActivityStreams PropertyValue attachments. */
function axismundi_actors_profile_fields_form( Axismundi_Actor $actor ) : void {
	$fields = axismundi_actors_get_profile_fields( $actor->get_identity_id() );
	while ( count( $fields ) < 3 ) {
		$fields[] = array( 'name' => '', 'url' => '' );
	}
	?>
	<h2><?php esc_html_e( 'Profile links', 'axismundi-actors' ); ?></h2>
	<p class="description"><?php esc_html_e( 'These are published as ActivityStreams PropertyValue attachments. Use them for verified or reciprocal profile links.', 'axismundi-actors' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="axismundi_actors_set_profile_fields">
		<input type="hidden" name="identity_id" value="<?php echo esc_attr( (string) $actor->get_identity_id() ); ?>">
		<?php wp_nonce_field( 'ax_actors_profile_fields_' . $actor->get_identity_id() ); ?>
		<table class="widefat striped ax-actor-profile-fields" data-max-fields="8" data-label="<?php echo esc_attr__( 'Profile link', 'axismundi-actors' ); ?>" style="max-width: 900px">
			<thead><tr><th class="ax-actor-profile-fields__order"><span class="screen-reader-text"><?php esc_html_e( 'Order', 'axismundi-actors' ); ?></span></th><th><?php esc_html_e( 'Label', 'axismundi-actors' ); ?></th><th><?php esc_html_e( 'Web address', 'axismundi-actors' ); ?></th><th><?php esc_html_e( 'Verification', 'axismundi-actors' ); ?></th><th><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'axismundi-actors' ); ?></span></th></tr></thead>
			<tbody class="ax-actor-profile-fields__rows">
			<?php foreach ( $fields as $field ) : ?>
				<tr class="ax-actor-profile-fields__row" draggable="true">
					<td class="ax-actor-profile-fields__order">
						<button type="button" class="button-link ax-actor-profile-fields__drag" aria-label="<?php esc_attr_e( 'Drag to reorder', 'axismundi-actors' ); ?>"><span class="dashicons dashicons-menu"></span></button>
						<button type="button" class="button-link ax-actor-profile-fields__move-up" aria-label="<?php esc_attr_e( 'Move up', 'axismundi-actors' ); ?>">&#8593;</button>
						<button type="button" class="button-link ax-actor-profile-fields__move-down" aria-label="<?php esc_attr_e( 'Move down', 'axismundi-actors' ); ?>">&#8595;</button>
					</td>
					<td><input class="regular-text" name="profile_field_name[]" value="<?php echo esc_attr( (string) $field['name'] ); ?>" maxlength="191"></td>
					<td><input class="large-text" type="url" name="profile_field_url[]" value="<?php echo esc_attr( (string) $field['url'] ); ?>" placeholder="https://example.com/"></td>
					<td class="ax-actor-profile-fields__verification">
						<?php if ( ! empty( $field['id'] ) ) : ?>
							<?php if ( 'verified' === ( $field['verification_status'] ?? '' ) ) : ?>
								<span class="ax-actor-profile-fields__verified" aria-label="<?php esc_attr_e( 'Verified reciprocal link', 'axismundi-actors' ); ?>">&#10003; <?php esc_html_e( 'Verified', 'axismundi-actors' ); ?></span>
							<?php elseif ( 'failed' === ( $field['verification_status'] ?? '' ) ) : ?>
								<span class="ax-actor-profile-fields__failed"><?php esc_html_e( 'Not verified', 'axismundi-actors' ); ?></span>
							<?php else : ?>
								<span><?php esc_html_e( 'Not checked', 'axismundi-actors' ); ?></span>
							<?php endif; ?>
							<button type="submit" class="button-link ax-actor-profile-fields__verify" name="verify_profile_field_url" value="<?php echo esc_attr( (string) $field['url'] ); ?>"><?php esc_html_e( 'Verify', 'axismundi-actors' ); ?></button>
						<?php endif; ?>
					</td>
					<td><button type="button" class="button-link-delete ax-actor-profile-fields__remove" aria-label="<?php esc_attr_e( 'Remove profile link', 'axismundi-actors' ); ?>">&times;</button></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p><button type="button" class="button ax-actor-profile-fields__add"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add link', 'axismundi-actors' ); ?></button></p>
		<?php submit_button( __( 'Save profile links', 'axismundi-actors' ) ); ?>
	</form>
	<?php
}

/** Default-public disclosure toggle for local follower/following lists. */
function axismundi_actors_follow_collections_form( Axismundi_Actor $actor ) : void {
	if ( ! $actor->is_local() ) {
		return;
	}
	$current = axismundi_actors_follow_collections_policy( $actor );
	$choices = array(
		'public'     => __( 'Show counts and lists', 'axismundi-actors' ),
		'count-only' => __( 'Show counts only, hide the lists', 'axismundi-actors' ),
		'private'    => __( 'Hide counts and lists', 'axismundi-actors' ),
	);
	?>
	<h2><?php esc_html_e( 'Follower and following lists', 'axismundi-actors' ); ?></h2>
	<p class="description"><?php esc_html_e( 'How many accounts follow you and who they are can be disclosed separately. This applies to profile pages and to ActivityPub collections alike.', 'axismundi-actors' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="axismundi_actors_set_follow_collections_visibility">
		<input type="hidden" name="identity_id" value="<?php echo esc_attr( (string) $actor->get_identity_id() ); ?>">
		<?php wp_nonce_field( 'ax_actors_follow_collections_' . $actor->get_identity_id() ); ?>
		<?php foreach ( $choices as $value => $label ) : ?>
			<p><label><input type="radio" name="visibility" value="<?php echo esc_attr( $value ); ?>" <?php checked( $current, $value ); ?>> <?php echo esc_html( $label ); ?></label></p>
		<?php endforeach; ?>
		<?php submit_button( __( 'Save follow list visibility', 'axismundi-actors' ), 'secondary', 'submit', false ); ?>
	</form>
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

/** Fetch/cache a remote Actor and its instance, then show the cached record. */
function axismundi_actors_handle_discover_remote() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot fetch remote Actors.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'ax_actors_discover_remote' );
	$input  = isset( $_POST['remote_actor'] ) ? sanitize_text_field( wp_unslash( $_POST['remote_actor'] ) ) : '';
	$result = '' !== $input ? axismundi_actors_discover_remote_input( $input ) : new WP_Error( 'ax_actors_remote_input', __( 'Enter a remote Actor address.', 'axismundi-actors' ) );
	$back   = axismundi_actors_remote_admin_url();
	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'ax_actor_error', rawurlencode( $result->get_error_message() ), $back ) );
		exit;
	}
	$host = axismundi_actors_webfinger_authority_from_url( $result->get_uri() );
	if ( '' !== $host && null === axismundi_actors_get_instance( $host ) ) {
		axismundi_actors_discover_remote_instance( $host );
	}
	wp_safe_redirect( add_query_arg( array( 'ax_actor_done' => 1, 'actor_id' => $result->get_identity_id() ), $back ) );
	exit;
}
add_action( 'admin_post_axismundi_actors_discover_remote', 'axismundi_actors_handle_discover_remote' );

/** Inspect, refresh, or purge a bounded remote image-cache scope. */
function axismundi_actors_handle_asset_cache() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot manage the remote image cache.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'ax_actors_asset_cache' );
	$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
	$scope     = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : '';
	$value     = isset( $_POST['scope_value'] ) ? sanitize_text_field( wp_unslash( $_POST['scope_value'] ) ) : '';
	$back      = axismundi_actors_remote_admin_url();
	if ( 'actor' === $scope && absint( $value ) > 0 ) {
		$back = add_query_arg( 'actor_id', absint( $value ), $back );
	}
	if ( ! in_array( $scope, array( 'actor', 'instance', 'all' ), true ) || ! in_array( $operation, array( 'refresh', 'inspect', 'purge' ), true ) || ( 'refresh' === $operation && 'actor' !== $scope ) ) {
		wp_safe_redirect( add_query_arg( 'ax_actor_error', rawurlencode( __( 'Invalid remote image cache operation.', 'axismundi-actors' ) ), $back ) );
		exit;
	}
	if ( 'refresh' === $operation ) {
		$result = array( 'rows' => axismundi_actors_refresh_asset_cache( absint( $value ) ), 'directories' => 0 );
	} else {
		$result = axismundi_actors_purge_asset_cache( $scope, $value, 'inspect' === $operation );
	}
	wp_safe_redirect(
		add_query_arg(
			array(
				'ax_asset_rows' => (int) $result['rows'],
				'ax_asset_dirs' => (int) $result['directories'],
			),
			$back
		)
	);
	exit;
}
add_action( 'admin_post_axismundi_actors_asset_cache', 'axismundi_actors_handle_asset_cache' );

/** Save the optional remote image conversion policy. */
function axismundi_actors_handle_asset_settings() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot manage the remote image cache.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'ax_actors_asset_settings' );
	$updated = axismundi_actors_set_asset_webp_enabled( ! empty( $_POST['webp_enabled'] ) );
	wp_safe_redirect( add_query_arg( 'ax_asset_rows', $updated, axismundi_actors_remote_admin_url() ) );
	exit;
}
add_action( 'admin_post_axismundi_actors_asset_settings', 'axismundi_actors_handle_asset_settings' );

/** Notify representation and transport plugins after a durable local profile edit. */
function axismundi_actors_profile_updated( int $identity_id ) : void {
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( $actor instanceof Axismundi_Actor && $actor->is_local() ) {
		/**
		 * Actors owns the durable profile state, not its Activity representation or delivery.
		 * Consumers record only a public, changed representation and may safely ignore this.
		 *
		 * @param Axismundi_Actor $actor Fresh local Actor after a successful edit.
		 */
		do_action( 'axismundi_actors_local_actor_profile_updated', $actor );
	}
}

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
	if ( $ok ) {
		axismundi_actors_profile_updated( $actor->get_identity_id() );
	}
	axismundi_actors_redirect_result( axismundi_actors_admin_url( get_current_user_id() === $user_id ? 0 : $user_id ), $ok ? true : new WP_Error( 'ax_actors_status', __( 'Could not update visibility.', 'axismundi-actors' ) ) );
}
add_action( 'admin_post_axismundi_actors_set_visibility', 'axismundi_actors_handle_set_visibility' );

/** Create a managed Group owned by the current user. */
function axismundi_actors_handle_create_managed_actor() : void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You cannot create managed actors.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'ax_actors_create_managed_group' );
	$handle = isset( $_POST['handle'] ) ? sanitize_text_field( wp_unslash( $_POST['handle'] ) ) : '';
	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$summary = isset( $_POST['summary'] ) ? wp_kses_post( wp_unslash( $_POST['summary'] ) ) : '';
	$status = isset( $_POST['visibility'] ) && 'internal' === $_POST['visibility'] ? 'internal' : 'public';
	/*
	 * Validated by the model rather than trusted from the form, and defaulting to `Group` so that
	 * anything still posting the older shape keeps creating what it always did -- a missing field must
	 * not silently start producing a different kind of actor.
	 */
	$type = isset( $_POST['actor_type'] ) ? sanitize_text_field( wp_unslash( $_POST['actor_type'] ) ) : 'Group';
	$actor = axismundi_actors_create_managed_actor(
		array(
			'owner_user_id'      => get_current_user_id(),
			'preferred_username' => $handle,
			'actor_type'         => $type,
			'status'             => $status,
		)
	);
	$back = axismundi_actors_managed_actors_admin_url();
	if ( is_wp_error( $actor ) ) {
		axismundi_actors_redirect_result( $back, $actor );
	}
	$language = axismundi_actors_site_language();
	$results = array(
		axismundi_actors_set_text( $actor->get_identity_id(), 'name', $language, $name ),
		axismundi_actors_set_text( $actor->get_identity_id(), 'summary', $language, $summary ),
		axismundi_actors_set_default_language( $actor->get_identity_id(), $language ),
	);
	foreach ( $results as $result ) {
		if ( is_wp_error( $result ) ) {
			axismundi_actors_redirect_result( $back, $result );
		}
	}
	axismundi_actors_profile_updated( $actor->get_identity_id() );
	axismundi_actors_redirect_result( axismundi_actors_managed_actors_admin_url( $actor->get_identity_id() ), true );
}
add_action( 'admin_post_axismundi_actors_create_managed_actor', 'axismundi_actors_handle_create_managed_actor' );

/** Change a managed Group's public lifecycle state through its manager relation. */
function axismundi_actors_handle_set_managed_group_visibility() : void {
	$identity_id = isset( $_POST['identity_id'] ) ? absint( $_POST['identity_id'] ) : 0;
	check_admin_referer( 'ax_actors_managed_visibility_' . $identity_id );
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_managed() || ! axismundi_actors_can_manage( $actor ) ) {
		wp_die( esc_html__( 'You cannot manage this Group.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	$status = isset( $_POST['status'] ) && 'internal' === $_POST['status'] ? 'internal' : 'public';
	$ok = axismundi_actors_set_status( $identity_id, $status );
	if ( $ok ) {
		axismundi_actors_profile_updated( $identity_id );
	}
	axismundi_actors_redirect_result( axismundi_actors_managed_actors_admin_url( $identity_id ), $ok ? true : new WP_Error( 'ax_actors_status', __( 'Could not update visibility.', 'axismundi-actors' ) ) );
}
add_action( 'admin_post_axismundi_actors_set_managed_group_visibility', 'axismundi_actors_handle_set_managed_group_visibility' );

/** Explicit site-administrator recovery registration for one managed Group. */
function axismundi_actors_handle_claim_managed_group() : void {
	$identity_id = isset( $_POST['identity_id'] ) ? absint( $_POST['identity_id'] ) : 0;
	check_admin_referer( 'ax_actors_claim_managed_group_' . $identity_id );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot claim this Group.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	axismundi_actors_redirect_result( axismundi_actors_managed_actors_admin_url( $identity_id ), axismundi_actors_claim_managed_group( $identity_id, get_current_user_id() ) );
}
add_action( 'admin_post_axismundi_actors_claim_managed_group', 'axismundi_actors_handle_claim_managed_group' );

/** @return void */
function axismundi_actors_handle_set_media() : void {
	$identity_id = isset( $_POST['identity_id'] ) ? absint( $_POST['identity_id'] ) : 0;
	check_admin_referer( 'ax_actors_media_' . $identity_id );
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! axismundi_actors_can_manage( $actor ) ) {
		wp_die( esc_html__( 'You cannot manage this actor profile.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	$result = true;
	foreach ( array( 'avatar', 'header' ) as $role ) {
		$attachment_id = isset( $_POST[ $role . '_attachment_id' ] ) ? absint( $_POST[ $role . '_attachment_id' ] ) : 0;
		$outcome       = axismundi_actors_set_profile_media( $actor, $role, $attachment_id );
		if ( is_wp_error( $outcome ) && ! is_wp_error( $result ) ) {
			$result = $outcome;
		}
	}
	if ( ! is_wp_error( $result ) ) {
		axismundi_actors_profile_updated( $identity_id );
	}
	$back = axismundi_actors_management_back_url( $actor );
	axismundi_actors_redirect_result( $back, $result );
}
add_action( 'admin_post_axismundi_actors_set_media', 'axismundi_actors_handle_set_media' );

/** @return void */
function axismundi_actors_handle_set_texts() : void {
	$identity_id = isset( $_POST['identity_id'] ) ? absint( $_POST['identity_id'] ) : 0;
	check_admin_referer( 'ax_actors_texts_' . $identity_id );
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! axismundi_actors_can_manage( $actor ) ) {
		wp_die( esc_html__( 'You cannot manage this actor profile.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	$source_language  = isset( $_POST['language_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['language_tag'] ) ) : '';
	$profile_language = isset( $_POST['profile_language'] ) ? sanitize_text_field( wp_unslash( $_POST['profile_language'] ) ) : $source_language;
	$source_language  = axismundi_actors_normalize_language_tag( $source_language );
	$language         = $source_language;
	$target_language  = axismundi_actors_normalize_language_tag( $profile_language );
	$result           = true;
	if ( '' === $language || '' === $target_language ) {
		$result = new WP_Error( 'ax_actors_text_language', __( 'Enter a valid profile language.', 'axismundi-actors' ) );
	}
	$current_primary = axismundi_actors_serialization_language( $actor );
	if ( ! is_wp_error( $result ) && $target_language !== $language ) {
		$result = axismundi_actors_rename_text_language( $identity_id, $language, $target_language );
		if ( ! is_wp_error( $result ) && $language === $current_primary ) {
			$result = axismundi_actors_set_default_language( $identity_id, $target_language );
			if ( ! is_wp_error( $result ) && 'Person' === $actor->get_type() && $actor->is_local() ) {
				$profile = axismundi_actors_person_profile( $identity_id );
				if ( $language === axismundi_actors_normalize_language_tag( (string) ( $profile['structured_name_language'] ?? '' ) ) ) {
					$result = axismundi_actors_write_person_profile( $identity_id, array( 'structured_name_language' => $target_language ) );
				}
			}
		}
		if ( ! is_wp_error( $result ) ) {
			$language = $target_language;
		}
	}
	$values = array(
		'summary' => isset( $_POST['summary'] ) ? wp_kses_post( wp_unslash( $_POST['summary'] ) ) : '',
		// Always what was typed. Nothing assembles this string any more, so nothing can be overwritten
		// by a set of parts that published themselves a moment ago.
		'name'    => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
	);
	/*
	 * The label and the pronunciation arrive only from the primary profile, because that is the only
	 * screen offering them. A secondary language sends a name and a summary and nothing else.
	 */
	if ( ! is_wp_error( $result ) && 'Person' === $actor->get_type() && $actor->is_local()
		&& $source_language === $current_primary ) {
		$parts = array( 'structured_name_language' => $language );
		foreach ( array( 'display_name', 'phonetic_given', 'phonetic_given2', 'phonetic_surname', 'phonetic_surname2', 'phonetic_system', 'phonetic_script' ) as $field ) {
			// Present-and-empty clears the stored value; a field this form did not send is left alone.
			if ( isset( $_POST[ $field ] ) ) {
				$parts[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			}
		}
		$outcome = axismundi_actors_write_person_profile( $identity_id, $parts );
		if ( is_wp_error( $outcome ) ) {
			$result = $outcome;
		}
	}
	if ( ! is_wp_error( $result ) ) {
		foreach ( $values as $field => $value ) {
			$outcome = axismundi_actors_set_text( $identity_id, $field, $language, $value );
			if ( is_wp_error( $outcome ) && ! is_wp_error( $result ) ) {
				$result = $outcome;
			}
		}
	}
	if ( ! is_wp_error( $result ) && ! empty( $_POST['make_primary'] ) ) {
		$result = axismundi_actors_make_profile_primary( $identity_id, $language );
	}
	if ( ! is_wp_error( $result ) ) {
		axismundi_actors_profile_updated( $identity_id );
	}
	$back = axismundi_actors_management_back_url( $actor );
	$normalized = axismundi_actors_normalize_language_tag( $language );
	if ( '' !== $normalized ) {
		$back = add_query_arg( 'ax_actor_lang', $normalized, $back );
	}
	axismundi_actors_redirect_result( $back, $result );
}
add_action( 'admin_post_axismundi_actors_set_texts', 'axismundi_actors_handle_set_texts' );

/** @return void */
function axismundi_actors_handle_set_profile_fields() : void {
	$identity_id = isset( $_POST['identity_id'] ) ? absint( $_POST['identity_id'] ) : 0;
	check_admin_referer( 'ax_actors_profile_fields_' . $identity_id );
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! axismundi_actors_can_manage( $actor ) ) {
		wp_die( esc_html__( 'You cannot manage this actor profile.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	$names  = isset( $_POST['profile_field_name'] ) && is_array( $_POST['profile_field_name'] ) ? wp_unslash( $_POST['profile_field_name'] ) : array();
	$urls   = isset( $_POST['profile_field_url'] ) && is_array( $_POST['profile_field_url'] ) ? wp_unslash( $_POST['profile_field_url'] ) : array();
	$fields = array();
	foreach ( array_values( $names ) as $position => $name ) {
		$fields[] = array( 'name' => is_scalar( $name ) ? (string) $name : '', 'url' => isset( $urls[ $position ] ) && is_scalar( $urls[ $position ] ) ? (string) $urls[ $position ] : '' );
	}
	$result = axismundi_actors_save_profile_fields( $actor, $fields );
	$saved  = ! is_wp_error( $result );
	if ( $saved && isset( $_POST['verify_profile_field_url'] ) && is_scalar( $_POST['verify_profile_field_url'] ) ) {
		$verify_url = esc_url_raw( trim( (string) wp_unslash( $_POST['verify_profile_field_url'] ) ) );
		$result     = axismundi_actors_verify_profile_field( $actor, $verify_url );
	}
	if ( $saved ) {
		axismundi_actors_profile_updated( $identity_id );
	}
	$back = axismundi_actors_management_back_url( $actor );
	axismundi_actors_redirect_result( $back, $result );
}
add_action( 'admin_post_axismundi_actors_set_profile_fields', 'axismundi_actors_handle_set_profile_fields' );

/** @return void */
function axismundi_actors_handle_set_follow_collections_visibility() : void {
	$identity_id = isset( $_POST['identity_id'] ) ? absint( $_POST['identity_id'] ) : 0;
	check_admin_referer( 'ax_actors_follow_collections_' . $identity_id );
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! axismundi_actors_can_manage( $actor ) ) {
		wp_die( esc_html__( 'You cannot manage this actor profile.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	$visibility = isset( $_POST['visibility'] ) ? sanitize_key( wp_unslash( $_POST['visibility'] ) ) : '';
	$visibility = in_array( $visibility, array( 'public', 'count-only', 'private' ), true ) ? $visibility : 'public';
	$result     = axismundi_actors_set_follow_collections_visibility( $actor, $visibility );
	$back = axismundi_actors_management_back_url( $actor );
	axismundi_actors_redirect_result( $back, $result );
}
add_action( 'admin_post_axismundi_actors_set_follow_collections_visibility', 'axismundi_actors_handle_set_follow_collections_visibility' );

/**
 * Who runs one managed actor.
 *
 * Nobody logs in as an Organization. Everyone who publishes as it signs in with their own account
 * and holds a role here, which is what keeps an action attributable to a person afterwards -- a
 * shared password would make "who posted this" unanswerable, and revoking one person's access would
 * mean changing everybody's.
 *
 * Delegating needs `manager`. An `editor` may publish as the actor but not decide who else can,
 * because the authority to add people is what turns a role into a way of keeping it.
 *
 * @param Axismundi_Actor $actor Managed actor.
 * @return void
 */
function axismundi_actors_managers_form( Axismundi_Actor $actor ) : void {
	if ( ! $actor->is_managed() ) {
		return;
	}
	$identity_id  = $actor->get_identity_id();
	$can_delegate = axismundi_actors_managed_actor_can_manage( $identity_id, get_current_user_id(), 'manager' );
	$managers     = axismundi_actors_group_managers( $identity_id );
	$roles        = array_keys( axismundi_actors_manager_roles() );
	$owner_count  = axismundi_actors_managed_owner_count( $identity_id );
	?>
	<h2><?php esc_html_e( 'Managers', 'axismundi-actors' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Everyone here signs in with their own account and publishes as this actor. Nobody shares a password.', 'axismundi-actors' ); ?></p>
	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Who', 'axismundi-actors' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Role', 'axismundi-actors' ); ?></th>
				<?php if ( $can_delegate ) : ?><th scope="col"><?php esc_html_e( 'Change', 'axismundi-actors' ); ?></th><?php endif; ?>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $managers as $ax_manager ) : ?>
			<?php
			$ax_user = get_userdata( (int) $ax_manager['user_id'] );
			$ax_role = (string) $ax_manager['role'];
			// The one row the form does not offer to change: an actor with no owner has nobody left who
			// could appoint one, so the model refuses it and the screen does not pretend otherwise.
			$ax_last_owner = 'owner' === $ax_role && 1 === $owner_count;
			?>
			<tr>
				<td>
					<?php
					echo esc_html(
						$ax_user instanceof WP_User
							? $ax_user->display_name . ' (' . $ax_user->user_login . ')'
							/* translators: %d: user ID of an account that no longer exists. */
							: sprintf( __( 'Deleted account #%d', 'axismundi-actors' ), (int) $ax_manager['user_id'] )
					);
					?>
				</td>
				<td><?php echo esc_html( $ax_role ); ?></td>
				<?php if ( $can_delegate ) : ?>
				<td>
					<?php if ( $ax_last_owner ) : ?>
						<span class="description"><?php esc_html_e( 'The last owner stays.', 'axismundi-actors' ); ?></span>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
							<?php wp_nonce_field( 'ax_actors_managers_' . $identity_id ); ?>
							<input type="hidden" name="action" value="axismundi_actors_save_manager">
							<input type="hidden" name="identity_id" value="<?php echo esc_attr( (string) $identity_id ); ?>">
							<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) (int) $ax_manager['user_id'] ); ?>">
							<select name="role">
								<?php foreach ( $roles as $ax_role_option ) : ?>
									<option value="<?php echo esc_attr( $ax_role_option ); ?>"<?php selected( $ax_role_option, $ax_role ); ?>><?php echo esc_html( $ax_role_option ); ?></option>
								<?php endforeach; ?>
							</select>
							<button type="submit" class="button"><?php esc_html_e( 'Save', 'axismundi-actors' ); ?></button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
							<?php wp_nonce_field( 'ax_actors_managers_' . $identity_id ); ?>
							<input type="hidden" name="action" value="axismundi_actors_remove_manager">
							<input type="hidden" name="identity_id" value="<?php echo esc_attr( (string) $identity_id ); ?>">
							<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) (int) $ax_manager['user_id'] ); ?>">
							<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Remove', 'axismundi-actors' ); ?></button>
						</form>
					<?php endif; ?>
				</td>
				<?php endif; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( $can_delegate ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ax_actors_managers_' . $identity_id ); ?>
			<input type="hidden" name="action" value="axismundi_actors_save_manager">
			<input type="hidden" name="identity_id" value="<?php echo esc_attr( (string) $identity_id ); ?>">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ax-manager-login-<?php echo esc_attr( (string) $identity_id ); ?>"><?php esc_html_e( 'Add a manager', 'axismundi-actors' ); ?></label></th>
					<td>
						<input id="ax-manager-login-<?php echo esc_attr( (string) $identity_id ); ?>" name="user_login" type="text" class="regular-text" required>
						<select name="role">
							<?php foreach ( $roles as $ax_role_option ) : ?>
								<option value="<?php echo esc_attr( $ax_role_option ); ?>"<?php selected( 'editor', $ax_role_option ); ?>><?php echo esc_html( $ax_role_option ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Username or email address of an existing account on this site.', 'axismundi-actors' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Add manager', 'axismundi-actors' ), 'secondary' ); ?>
		</form>
	<?php endif; ?>
	<?php
}

/**
 * Grant one manager role, or change one.
 *
 * The authority is re-established from the relation and not taken from the form that drew the
 * button. The screen only renders these controls for a manager, but a POST can arrive from anywhere,
 * and this is the door rather than the decoration on it.
 *
 * @return void
 */
function axismundi_actors_handle_save_manager() : void {
	$identity_id = isset( $_POST['identity_id'] ) ? absint( $_POST['identity_id'] ) : 0;
	check_admin_referer( 'ax_actors_managers_' . $identity_id );
	if ( ! axismundi_actors_managed_actor_can_manage( $identity_id, get_current_user_id(), 'manager' ) ) {
		wp_die( esc_html__( 'You cannot change who manages this actor.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	$back    = axismundi_actors_managed_actors_admin_url( $identity_id );
	$role    = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
	$login   = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	if ( $user_id <= 0 && '' !== $login ) {
		$found = get_user_by( 'login', $login );
		if ( ! $found instanceof WP_User ) {
			$found = get_user_by( 'email', $login );
		}
		$user_id = $found instanceof WP_User ? (int) $found->ID : 0;
	}
	if ( $user_id <= 0 ) {
		axismundi_actors_redirect_result( $back, new WP_Error( 'ax_actors_manager_user', __( 'No account on this site matches that name.', 'axismundi-actors' ) ) );
	}
	axismundi_actors_redirect_result( $back, axismundi_actors_add_manager( $identity_id, $user_id, $role ) );
}
add_action( 'admin_post_axismundi_actors_save_manager', 'axismundi_actors_handle_save_manager' );

/**
 * Revoke one manager.
 *
 * @return void
 */
function axismundi_actors_handle_remove_manager() : void {
	$identity_id = isset( $_POST['identity_id'] ) ? absint( $_POST['identity_id'] ) : 0;
	check_admin_referer( 'ax_actors_managers_' . $identity_id );
	if ( ! axismundi_actors_managed_actor_can_manage( $identity_id, get_current_user_id(), 'manager' ) ) {
		wp_die( esc_html__( 'You cannot change who manages this actor.', 'axismundi-actors' ), '', array( 'response' => 403 ) );
	}
	$back    = axismundi_actors_managed_actors_admin_url( $identity_id );
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	axismundi_actors_redirect_result( $back, axismundi_actors_remove_manager( $identity_id, $user_id ) );
}
add_action( 'admin_post_axismundi_actors_remove_manager', 'axismundi_actors_handle_remove_manager' );
