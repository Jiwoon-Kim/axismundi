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
 * May the viewer manage this actor? Own Person actor with content-writing access,
 * or `manage_options`.
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
	<p><a class="button" href="<?php echo esc_url( add_query_arg( 'ax_actor', $actor->get_uuid(), home_url( '/' ) ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Preview cached profile', 'axismundi-actors' ); ?></a></p>
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
		<input type="hidden" name="remote_actor" value="<?php echo esc_attr( (string) ( $addresses[0]['address'] ?? $actor->get_uri() ) ); ?>">
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
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Host', 'axismundi-actors' ) . '</th><th>' . esc_html__( 'Software', 'axismundi-actors' ) . '</th><th>' . esc_html__( 'Registrations', 'axismundi-actors' ) . '</th><th>' . esc_html__( 'Fetched', 'axismundi-actors' ) . '</th><th>' . esc_html__( 'Status', 'axismundi-actors' ) . '</th></tr></thead><tbody>';
	foreach ( $instances as $instance ) {
		$registrations = null === $instance['open_registrations'] ? '—' : ( (int) $instance['open_registrations'] ? __( 'Open', 'axismundi-actors' ) : __( 'Closed', 'axismundi-actors' ) );
		echo '<tr><td><code>' . esc_html( (string) $instance['host'] ) . '</code></td><td>' . esc_html( trim( (string) $instance['software_name'] . ' ' . (string) $instance['software_version'] ) ) . '</td><td>' . esc_html( $registrations ) . '</td><td>' . esc_html( (string) $instance['fetched_at'] ) . '</td><td>' . esc_html( (string) $instance['fetch_status'] ) . '</td></tr>';
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
	<?php axismundi_actors_media_form( $actor ); ?>
	<?php axismundi_actors_text_form( $actor ); ?>
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
	if ( 'users_page_axismundi-actor-profile' !== $hook && 'settings_page_axismundi-actor-site' !== $hook ) {
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
	$language  = axismundi_actors_admin_text_language( $actor );
	$languages = array_keys( $map );
	$languages[] = $actor->get_default_language() ?: axismundi_actors_site_language();
	$languages[] = axismundi_actors_site_language();
	$user_id = $actor->get_local_user_id();
	if ( $user_id ) {
		$languages[] = axismundi_actors_normalize_language_tag( get_user_locale( $user_id ) );
	}
	$languages = array_values( array_unique( array_filter( $languages ) ) );
	$back      = 'site' === $actor->get_scope()
		? admin_url( 'options-general.php?page=axismundi-actor-site' )
		: axismundi_actors_admin_url( get_current_user_id() === $user_id ? 0 : (int) $user_id );
	?>
	<h2><?php esc_html_e( 'Profile languages', 'axismundi-actors' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Translations are optional. Empty fields continue to use the live WordPress profile or site value.', 'axismundi-actors' ); ?></p>
	<p>
		<?php foreach ( $languages as $candidate ) : ?>
			<a class="button <?php echo $candidate === $language ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'ax_actor_lang', $candidate, $back ) ); ?>"><?php echo esc_html( $candidate ); ?></a>
		<?php endforeach; ?>
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="axismundi_actors_set_texts">
		<input type="hidden" name="identity_id" value="<?php echo esc_attr( (string) $actor->get_identity_id() ); ?>">
		<?php wp_nonce_field( 'ax_actors_texts_' . $actor->get_identity_id() ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ax-actor-language"><?php esc_html_e( 'Language', 'axismundi-actors' ); ?></label></th>
				<td><input id="ax-actor-language" name="language_tag" value="<?php echo esc_attr( $language ); ?>" class="regular-text" required><p class="description"><?php esc_html_e( 'BCP 47 language tag, for example ko-KR or en-US.', 'axismundi-actors' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><label for="ax-actor-name"><?php esc_html_e( 'Name', 'axismundi-actors' ); ?></label></th>
				<td><input id="ax-actor-name" name="name" value="<?php echo esc_attr( $map[ $language ]['name'] ?? '' ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="ax-actor-summary"><?php esc_html_e( 'Summary', 'axismundi-actors' ); ?></label></th>
				<td><textarea id="ax-actor-summary" name="summary" rows="4" class="large-text"><?php echo esc_textarea( $map[ $language ]['summary'] ?? '' ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="ax-actor-content"><?php esc_html_e( 'About', 'axismundi-actors' ); ?></label></th>
				<td><textarea id="ax-actor-content" name="content" rows="8" class="large-text"><?php echo esc_textarea( $map[ $language ]['content'] ?? '' ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Default language', 'axismundi-actors' ); ?></th>
				<td><label><input type="checkbox" name="make_default" value="1" <?php checked( $actor->get_default_language(), $language ); ?>> <?php esc_html_e( 'Use this language for scalar profile fields sent to peers.', 'axismundi-actors' ); ?></label></td>
			</tr>
		</table>
		<?php submit_button( __( 'Save profile language', 'axismundi-actors' ) ); ?>
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
	$back = 'site' === $actor->get_scope()
		? admin_url( 'options-general.php?page=axismundi-actor-site' )
		: axismundi_actors_admin_url( get_current_user_id() === $actor->get_local_user_id() ? 0 : (int) $actor->get_local_user_id() );
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
	$language = isset( $_POST['language_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['language_tag'] ) ) : '';
	$result   = true;
	$values   = array(
		'name'    => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
		'summary' => isset( $_POST['summary'] ) ? wp_kses_post( wp_unslash( $_POST['summary'] ) ) : '',
		'content' => isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '',
	);
	foreach ( $values as $field => $value ) {
		$outcome = axismundi_actors_set_text( $identity_id, $field, $language, $value );
		if ( is_wp_error( $outcome ) && ! is_wp_error( $result ) ) {
			$result = $outcome;
		}
	}
	if ( ! is_wp_error( $result ) && ! empty( $_POST['make_default'] ) ) {
		$result = axismundi_actors_set_default_language( $identity_id, $language );
	}
	$back = 'site' === $actor->get_scope()
		? admin_url( 'options-general.php?page=axismundi-actor-site' )
		: axismundi_actors_admin_url( get_current_user_id() === $actor->get_local_user_id() ? 0 : (int) $actor->get_local_user_id() );
	$normalized = axismundi_actors_normalize_language_tag( $language );
	if ( '' !== $normalized ) {
		$back = add_query_arg( 'ax_actor_lang', $normalized, $back );
	}
	axismundi_actors_redirect_result( $back, $result );
}
add_action( 'admin_post_axismundi_actors_set_texts', 'axismundi_actors_handle_set_texts' );
