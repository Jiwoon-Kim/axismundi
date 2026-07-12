<?php
/**
 * Media-folder discovery and management UI.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the folder manager beneath Media.
 *
 * @return void
 */
function axismundi_media_register_folders_page() : void {
	if ( ! axismundi_media_is_independent() || ! current_user_can( 'upload_files' ) ) {
		return;
	}
	add_media_page(
		__( 'Media Folders', 'axismundi-media-library' ),
		__( 'Folders', 'axismundi-media-library' ),
		'upload_files',
		'axismundi-media-folders',
		'axismundi_media_render_folders_page'
	);
}
add_action( 'admin_menu', 'axismundi_media_register_folders_page' );

/**
 * Add the public media profile to user row actions.
 *
 * @param array<string,string> $actions Existing actions.
 * @param WP_User              $user    Row user.
 * @return array<string,string>
 */
function axismundi_media_user_profile_action( array $actions, WP_User $user ) : array {
	if ( axismundi_media_is_independent() && current_user_can( 'list_users' ) ) {
		$actions['ax_media_profile'] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( axismundi_media_author_url( (int) $user->ID ) ),
			esc_html__( 'Media profile', 'axismundi-media-library' )
		);
	}
	return $actions;
}
add_filter( 'user_row_actions', 'axismundi_media_user_profile_action', 10, 2 );

/**
 * Folder-manager URL.
 *
 * @param array<string,string|int> $args Query arguments.
 * @return string
 */
function axismundi_media_folders_admin_url( array $args = array() ) : string {
	return add_query_arg( $args, admin_url( 'upload.php?page=axismundi-media-folders' ) );
}

/**
 * Handle create/update/delete actions through the existing service layer.
 *
 * @return void
 */
function axismundi_media_handle_folder_admin_action() : void {
	if ( ! axismundi_media_is_independent() || ! current_user_can( 'upload_files' ) ) {
		wp_die( esc_html__( 'You cannot manage media folders.', 'axismundi-media-library' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'ax_media_folder_action' );
	$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
	$term_id   = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	$result    = true;

	if ( 'create' === $operation ) {
		$name   = isset( $_POST['folder_name'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_name'] ) ) : '';
		$parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
		$result = axismundi_media_create_folder( $name, $parent );
	} elseif ( 'update' === $operation ) {
		$name = isset( $_POST['folder_name'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_name'] ) ) : '';
		$tier = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : 'inherit';
		$access = isset( $_POST['access'] ) ? sanitize_key( wp_unslash( $_POST['access'] ) ) : 'open';
		$password = isset( $_POST['folder_password'] ) ? (string) wp_unslash( $_POST['folder_password'] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must not be transformed before hashing.
		if ( 'password' === $access && ( null === $password || '' === $password ) && '' === (string) get_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_PASSWORD_META, true ) ) {
			$result = new WP_Error( 'ax_media_folder_password', __( 'A password is required.', 'axismundi-media-library' ) );
		} else {
			$result = axismundi_media_rename_folder( $term_id, $name );
		}
		if ( ! is_wp_error( $result ) ) {
			$result = axismundi_media_set_folder_tier( $term_id, $tier );
		}
		if ( ! is_wp_error( $result ) ) {
			$result = axismundi_media_set_folder_access( $term_id, $access, $password );
		}
		if ( ! is_wp_error( $result ) ) {
			$result = axismundi_media_set_folder_feed_enabled( $term_id, ! empty( $_POST['feed_enabled'] ) );
		}
	} elseif ( 'delete' === $operation ) {
		$result = axismundi_media_delete_folder( $term_id );
	} else {
		$result = new WP_Error( 'ax_media_folder_action', __( 'Unknown folder action.', 'axismundi-media-library' ) );
	}

	$args = is_wp_error( $result )
		? array( 'ax_media_error' => rawurlencode( $result->get_error_message() ) )
		: array( 'ax_media_updated' => 1 );
	wp_safe_redirect( axismundi_media_folders_admin_url( $args ) );
	exit;
}
add_action( 'admin_post_axismundi_media_folder_action', 'axismundi_media_handle_folder_admin_action' );

/**
 * Render one nested folder row.
 *
 * @param array<string,mixed>              $folder   Folder data.
 * @param array<int,array<string,mixed>[]> $children Child index.
 * @param int                              $depth    Depth.
 * @return void
 */
function axismundi_media_render_folder_row( array $folder, array $children, int $depth = 0 ) : void {
	$term_id = (int) $folder['id'];
	?>
	<tr>
		<td><strong><?php echo esc_html( str_repeat( '— ', $depth ) . (string) $folder['name'] ); ?></strong></td>
		<td><?php echo esc_html( (string) $folder['count'] ); ?> / <?php echo esc_html( (string) $folder['recursive_count'] ); ?></td>
		<td><?php echo esc_html( (string) $folder['effective_tier'] . ( $folder['effective_gate'] ? ' + password' : '' ) ); ?></td>
		<td>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ax-media-folder-row-form">
				<input type="hidden" name="action" value="axismundi_media_folder_action">
				<input type="hidden" name="operation" value="update">
				<input type="hidden" name="folder_id" value="<?php echo esc_attr( (string) $term_id ); ?>">
				<?php wp_nonce_field( 'ax_media_folder_action' ); ?>
				<label class="screen-reader-text" for="ax-folder-name-<?php echo esc_attr( (string) $term_id ); ?>"><?php esc_html_e( 'Folder name', 'axismundi-media-library' ); ?></label>
				<input id="ax-folder-name-<?php echo esc_attr( (string) $term_id ); ?>" type="text" name="folder_name" value="<?php echo esc_attr( (string) $folder['name'] ); ?>" required>
				<label class="screen-reader-text" for="ax-folder-tier-<?php echo esc_attr( (string) $term_id ); ?>"><?php esc_html_e( 'Visibility', 'axismundi-media-library' ); ?></label>
				<select id="ax-folder-tier-<?php echo esc_attr( (string) $term_id ); ?>" name="tier">
					<?php foreach ( array( 'inherit', 'public', 'unlisted', 'private' ) as $tier ) : ?>
						<option value="<?php echo esc_attr( $tier ); ?>" <?php selected( $folder['tier'], $tier ); ?>><?php echo esc_html( ucfirst( $tier ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<label class="screen-reader-text" for="ax-folder-access-<?php echo esc_attr( (string) $term_id ); ?>"><?php esc_html_e( 'Access', 'axismundi-media-library' ); ?></label>
				<select id="ax-folder-access-<?php echo esc_attr( (string) $term_id ); ?>" name="access">
					<option value="open" <?php selected( $folder['access'], 'open' ); ?>><?php esc_html_e( 'Open', 'axismundi-media-library' ); ?></option>
					<option value="password" <?php selected( $folder['access'], 'password' ); ?>><?php esc_html_e( 'Password', 'axismundi-media-library' ); ?></option>
				</select>
				<label class="screen-reader-text" for="ax-folder-password-<?php echo esc_attr( (string) $term_id ); ?>"><?php esc_html_e( 'New password', 'axismundi-media-library' ); ?></label>
				<input id="ax-folder-password-<?php echo esc_attr( (string) $term_id ); ?>" type="password" name="folder_password" placeholder="<?php echo esc_attr__( 'New password (leave blank to keep)', 'axismundi-media-library' ); ?>" autocomplete="new-password">
				<?php $axismundi_feed_on = '0' !== (string) get_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_FEED_META, true ); ?>
				<label title="<?php esc_attr_e( 'Public folders publish an Atom feed by default; uncheck to opt out.', 'axismundi-media-library' ); ?>"><input type="checkbox" name="feed_enabled" value="1" <?php checked( $axismundi_feed_on ); ?>> <?php esc_html_e( 'Feed', 'axismundi-media-library' ); ?></label>
				<?php submit_button( __( 'Save', 'axismundi-media-library' ), 'secondary small', 'submit', false ); ?>
			</form>
		</td>
		<td><a href="<?php echo esc_url( axismundi_media_folder_url( get_current_user_id(), $term_id ) ); ?>"><?php esc_html_e( 'View', 'axismundi-media-library' ); ?></a></td>
		<td>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="axismundi_media_folder_action">
				<input type="hidden" name="operation" value="delete">
				<input type="hidden" name="folder_id" value="<?php echo esc_attr( (string) $term_id ); ?>">
				<?php wp_nonce_field( 'ax_media_folder_action' ); ?>
				<?php submit_button( __( 'Delete', 'axismundi-media-library' ), 'delete small', 'submit', false ); ?>
			</form>
		</td>
	</tr>
	<?php
	foreach ( $children[ $term_id ] ?? array() as $child ) {
		axismundi_media_render_folder_row( $child, $children, $depth + 1 );
	}
}

/**
 * Render Media > Folders.
 *
 * @return void
 */
function axismundi_media_render_folders_page() : void {
	$folders  = axismundi_media_user_folders( get_current_user_id() );
	$children = array();
	foreach ( $folders as $folder ) {
		$children[ (int) $folder['parent'] ][] = $folder;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Media Folders', 'axismundi-media-library' ); ?></h1>
		<?php if ( isset( $_GET['ax_media_updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Status-only redirect flag. ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Folder updated.', 'axismundi-media-library' ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['ax_media_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Escaped display-only redirect message. ?>
			<div class="notice notice-error"><p><?php echo esc_html( rawurldecode( sanitize_text_field( wp_unslash( $_GET['ax_media_error'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?></p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Add folder', 'axismundi-media-library' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="axismundi_media_folder_action">
			<input type="hidden" name="operation" value="create">
			<?php wp_nonce_field( 'ax_media_folder_action' ); ?>
			<label for="ax-media-new-folder"><?php esc_html_e( 'Name', 'axismundi-media-library' ); ?></label>
			<input id="ax-media-new-folder" type="text" name="folder_name" required>
			<label for="ax-media-new-parent"><?php esc_html_e( 'Parent', 'axismundi-media-library' ); ?></label>
			<select id="ax-media-new-parent" name="parent">
				<option value="0"><?php esc_html_e( 'Top level', 'axismundi-media-library' ); ?></option>
				<?php foreach ( $folders as $folder ) : ?>
					<option value="<?php echo esc_attr( (string) $folder['id'] ); ?>"><?php echo esc_html( (string) $folder['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Add folder', 'axismundi-media-library' ), 'primary', 'submit', false ); ?>
		</form>

		<h2><?php esc_html_e( 'Your folders', 'axismundi-media-library' ); ?></h2>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Folder', 'axismundi-media-library' ); ?></th><th><?php esc_html_e( 'Items (direct / total)', 'axismundi-media-library' ); ?></th><th><?php esc_html_e( 'Effective visibility', 'axismundi-media-library' ); ?></th><th><?php esc_html_e( 'Settings', 'axismundi-media-library' ); ?></th><th><?php esc_html_e( 'Archive', 'axismundi-media-library' ); ?></th><th><?php esc_html_e( 'Delete', 'axismundi-media-library' ); ?></th></tr></thead>
			<tbody>
			<?php if ( empty( $folders ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No folders yet.', 'axismundi-media-library' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $children[0] ?? array() as $folder ) { axismundi_media_render_folder_row( $folder, $children ); } ?>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}
