<?php
/**
 * Phase 4b - administrator Remote Objects fetch/cache inspector.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Remote Objects admin URL. */
function axismundi_op_remote_admin_url( string $object_uri = '' ) : string {
	$url = add_query_arg( 'page', 'axismundi-remote-objects', admin_url( 'tools.php' ) );
	return '' === $object_uri ? $url : add_query_arg( 'object_uri', $object_uri, $url );
}

/** Register the admin-only inspector. */
function axismundi_op_register_admin_page() : void {
	add_management_page(
		__( 'Remote Objects', 'axismundi-object-projections' ),
		__( 'Remote Objects', 'axismundi-object-projections' ),
		'manage_options',
		'axismundi-remote-objects',
		'axismundi_op_render_remote_admin_page'
	);
}
add_action( 'admin_menu', 'axismundi_op_register_admin_page' );

/** Text-oriented HTML allowlist: deliberately excludes every media/embed element. */
function axismundi_op_remote_preview_html( string $html ) : string {
	$allowed = array(
		'a'          => array( 'href' => true, 'rel' => true, 'title' => true ),
		'blockquote' => array(),
		'br'         => array(),
		'code'       => array(),
		'em'         => array(),
		'li'         => array(),
		'ol'         => array(),
		'p'          => array(),
		'pre'        => array(),
		's'          => array(),
		'span'       => array( 'lang' => true ),
		'strong'     => array(),
		'ul'         => array(),
	);
	return wp_kses( $html, $allowed, array( 'http', 'https' ) );
}

/** Render success/error query notices. */
function axismundi_op_remote_admin_notices() : void {
	if ( isset( $_GET['ax_op_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect status only.
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Remote object cache updated.', 'axismundi-object-projections' ) . '</p></div>';
	}
	if ( isset( $_GET['ax_op_purged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect status only.
		$count = absint( $_GET['ax_op_purged'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only integer.
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( /* translators: %d: rows. */ __( 'Purged %d cached object(s).', 'axismundi-object-projections' ), $count ) ) . '</p></div>';
	}
	if ( isset( $_GET['ax_op_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect status only.
		$message = sanitize_text_field( wp_unslash( $_GET['ax_op_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- escaped display-only message.
		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	}
}

/** Render one metadata-only cached object. */
function axismundi_op_render_remote_object_detail( array $object ) : void {
	?>
	<hr>
	<h2><?php echo esc_html( '' !== (string) $object['name'] ? (string) $object['name'] : (string) $object['object_type'] ); ?></h2>
	<table class="widefat striped" style="max-width: 1000px">
		<tbody>
			<tr><th scope="row"><?php esc_html_e( 'Canonical URI', 'axismundi-object-projections' ); ?></th><td><code><?php echo esc_html( (string) $object['object_uri'] ); ?></code></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Type / status', 'axismundi-object-projections' ); ?></th><td><?php echo esc_html( (string) $object['object_type'] . ' / ' . (string) $object['object_status'] ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Attributed to', 'axismundi-object-projections' ); ?></th><td><?php echo empty( $object['attributed_to_uri'] ) ? '—' : '<a href="' . esc_url( (string) $object['attributed_to_uri'] ) . '" rel="noopener noreferrer">' . esc_html( (string) $object['attributed_to_uri'] ) . '</a>'; ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Published / updated', 'axismundi-object-projections' ); ?></th><td><?php echo esc_html( (string) ( $object['published_at'] ?? '—' ) . ' / ' . (string) ( $object['remote_updated_at'] ?? '—' ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Sensitive', 'axismundi-object-projections' ); ?></th><td><?php echo null === $object['is_sensitive'] ? esc_html__( 'Not declared', 'axismundi-object-projections' ) : ( (int) $object['is_sensitive'] ? esc_html__( 'Yes', 'axismundi-object-projections' ) : esc_html__( 'No', 'axismundi-object-projections' ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Fetched / expires', 'axismundi-object-projections' ); ?></th><td><?php echo esc_html( (string) $object['fetched_at'] . ' / ' . (string) $object['expires_at'] ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Source page', 'axismundi-object-projections' ); ?></th><td><?php echo empty( $object['human_url'] ) ? '—' : '<a href="' . esc_url( (string) $object['human_url'] ) . '" rel="noopener noreferrer">' . esc_html__( 'Open remote page', 'axismundi-object-projections' ) . '</a>'; ?></td></tr>
		</tbody>
	</table>
	<?php if ( ! empty( $object['summary'] ) ) : ?>
		<h3><?php esc_html_e( 'Summary', 'axismundi-object-projections' ); ?></h3>
		<div class="ax-op-remote-summary"><?php echo axismundi_op_remote_preview_html( (string) $object['summary'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bounded allowlist above. ?></div>
	<?php endif; ?>
	<?php if ( ! empty( $object['content'] ) ) : ?>
		<h3><?php esc_html_e( 'Content preview', 'axismundi-object-projections' ); ?></h3>
		<div class="ax-op-remote-content"><?php echo axismundi_op_remote_preview_html( (string) $object['content'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bounded allowlist above. ?></div>
	<?php endif; ?>
	<p class="description"><?php esc_html_e( 'Metadata-only preview: remote images, video, audio, embeds, and attachment binaries are never rendered or downloaded.', 'axismundi-object-projections' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
		<input type="hidden" name="action" value="axismundi_op_fetch_remote_object">
		<input type="hidden" name="remote_object" value="<?php echo esc_attr( (string) $object['object_uri'] ); ?>">
		<?php wp_nonce_field( 'ax_op_fetch_remote_object' ); ?>
		<?php submit_button( __( 'Refresh metadata', 'axismundi-object-projections' ), 'secondary', 'submit', false ); ?>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-left:8px">
		<input type="hidden" name="action" value="axismundi_op_delete_remote_object">
		<input type="hidden" name="remote_object" value="<?php echo esc_attr( (string) $object['object_uri'] ); ?>">
		<?php wp_nonce_field( 'ax_op_delete_remote_object' ); ?>
		<?php submit_button( __( 'Delete cached metadata', 'axismundi-object-projections' ), 'delete', 'submit', false ); ?>
	</form>
	<?php
}

/** Render read-only list + explicit fetch controls. */
function axismundi_op_render_remote_admin_page() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot inspect remote objects.', 'axismundi-object-projections' ), '', array( 'response' => 403 ) );
	}
	$selected_uri = isset( $_GET['object_uri'] ) ? esc_url_raw( wp_unslash( $_GET['object_uri'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only selection.
	$selected     = '' !== $selected_uri ? axismundi_op_remote_object_get( $selected_uri, true ) : null;
	$objects      = axismundi_op_remote_objects_list();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Remote Objects', 'axismundi-object-projections' ); ?></h1>
		<?php axismundi_op_remote_admin_notices(); ?>
		<p><?php esc_html_e( 'Fetch a canonical ActivityStreams object URL. This first version stores and previews text and metadata only; it never hotlinks or downloads remote media.', 'axismundi-object-projections' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="axismundi_op_fetch_remote_object">
			<?php wp_nonce_field( 'ax_op_fetch_remote_object' ); ?>
			<label class="screen-reader-text" for="ax-op-remote-object"><?php esc_html_e( 'Remote object URL', 'axismundi-object-projections' ); ?></label>
			<input id="ax-op-remote-object" type="url" name="remote_object" class="large-text" placeholder="https://example.social/users/alice/statuses/123" required>
			<?php submit_button( __( 'Fetch object metadata', 'axismundi-object-projections' ), 'primary', 'submit', false ); ?>
		</form>

		<?php if ( is_array( $selected ) ) : ?>
			<?php axismundi_op_render_remote_object_detail( $selected ); ?>
		<?php endif; ?>

		<hr>
		<h2><?php esc_html_e( 'Cached objects', 'axismundi-object-projections' ); ?></h2>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Object', 'axismundi-object-projections' ); ?></th><th><?php esc_html_e( 'Type', 'axismundi-object-projections' ); ?></th><th><?php esc_html_e( 'Actor', 'axismundi-object-projections' ); ?></th><th><?php esc_html_e( 'Fetched', 'axismundi-object-projections' ); ?></th><th><?php esc_html_e( 'Expires', 'axismundi-object-projections' ); ?></th></tr></thead>
			<tbody>
			<?php if ( empty( $objects ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No cached remote objects.', 'axismundi-object-projections' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $objects as $object ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( axismundi_op_remote_admin_url( (string) $object['object_uri'] ) ); ?>"><?php echo esc_html( '' !== (string) $object['name'] ? (string) $object['name'] : (string) $object['object_uri'] ); ?></a></td>
						<td><?php echo esc_html( (string) $object['object_type'] ); ?></td>
						<td><?php echo esc_html( (string) ( $object['attributed_to_uri'] ?? '—' ) ); ?></td>
						<td><?php echo esc_html( (string) $object['fetched_at'] ); ?></td>
						<td><?php echo esc_html( (string) $object['expires_at'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="axismundi_op_purge_remote_objects">
			<?php wp_nonce_field( 'ax_op_purge_remote_objects' ); ?>
			<?php submit_button( __( 'Purge expired metadata', 'axismundi-object-projections' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

/** Fetch action. */
function axismundi_op_handle_fetch_remote_object() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot fetch remote objects.', 'axismundi-object-projections' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'ax_op_fetch_remote_object' );
	$url    = isset( $_POST['remote_object'] ) ? esc_url_raw( wp_unslash( $_POST['remote_object'] ) ) : '';
	$result = '' !== $url ? axismundi_op_remote_object_fetch( $url ) : new WP_Error( 'ax_op_remote_input', __( 'Enter a remote object URL.', 'axismundi-object-projections' ) );
	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'ax_op_error', rawurlencode( $result->get_error_message() ), axismundi_op_remote_admin_url() ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_op_done', 1, axismundi_op_remote_admin_url( (string) $result['object_uri'] ) ) );
	exit;
}
add_action( 'admin_post_axismundi_op_fetch_remote_object', 'axismundi_op_handle_fetch_remote_object' );

/** Delete one cache row. */
function axismundi_op_handle_delete_remote_object() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot delete remote object metadata.', 'axismundi-object-projections' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'ax_op_delete_remote_object' );
	$url = isset( $_POST['remote_object'] ) ? esc_url_raw( wp_unslash( $_POST['remote_object'] ) ) : '';
	axismundi_op_remote_object_delete( $url );
	wp_safe_redirect( axismundi_op_remote_admin_url() );
	exit;
}
add_action( 'admin_post_axismundi_op_delete_remote_object', 'axismundi_op_handle_delete_remote_object' );

/** Purge expired metadata now. */
function axismundi_op_handle_purge_remote_objects() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot purge remote object metadata.', 'axismundi-object-projections' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'ax_op_purge_remote_objects' );
	$count = axismundi_op_remote_objects_purge_expired();
	wp_safe_redirect( add_query_arg( 'ax_op_purged', $count, axismundi_op_remote_admin_url() ) );
	exit;
}
add_action( 'admin_post_axismundi_op_purge_remote_objects', 'axismundi_op_handle_purge_remote_objects' );
