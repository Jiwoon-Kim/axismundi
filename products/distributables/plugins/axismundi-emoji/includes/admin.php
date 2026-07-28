<?php
/**
 * Emoji review administration.
 *
 * This page is intentionally metadata-only. A remote image is never hotlinked from
 * an admin list, and a click only changes local review state; network verification
 * remains the cron worker's responsibility.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

/** @param array<string,scalar> $args Query arguments. @return string */
function axismundi_emoji_admin_url( array $args = array() ) : string {
	return add_query_arg( $args, admin_url( 'admin.php?page=axismundi-emoji' ) );
}

/** @return void */
function axismundi_emoji_register_admin_page() : void {
	add_menu_page(
		__( 'Axismundi Emoji', 'axismundi-emoji' ),
		__( 'Emojis', 'axismundi-emoji' ),
		AXISMUNDI_EMOJI_CAPABILITY,
		'axismundi-emoji',
		'axismundi_emoji_render_admin_page',
		'dashicons-smiley',
		58
	);
	add_submenu_page(
		'axismundi-emoji',
		__( 'Emojis', 'axismundi-emoji' ),
		__( 'Review', 'axismundi-emoji' ),
		AXISMUNDI_EMOJI_CAPABILITY,
		'axismundi-emoji',
		'axismundi_emoji_render_admin_page'
	);
}
add_action( 'admin_menu', 'axismundi_emoji_register_admin_page' );

/** @return void */
function axismundi_emoji_render_admin_page() : void {
	if ( ! current_user_can( AXISMUNDI_EMOJI_CAPABILITY ) ) {
		wp_die( esc_html__( 'You cannot review custom emoji.', 'axismundi-emoji' ), '', array( 'response' => 403 ) );
	}

	$buckets = array(
		'local'       => __( 'Local', 'axismundi-emoji' ),
		'all'         => __( 'All', 'axismundi-emoji' ),
		'unverified'  => __( 'Unverified', 'axismundi-emoji' ),
		'pending'     => __( 'Ready for review', 'axismundi-emoji' ),
		'changed'     => __( 'Changed', 'axismundi-emoji' ),
		'approved'    => __( 'Approved', 'axismundi-emoji' ),
		'rejected'    => __( 'Rejected', 'axismundi-emoji' ),
		'authorities' => __( 'Authorities', 'axismundi-emoji' ),
	);
	$bucket = isset( $_GET['bucket'] ) ? sanitize_key( wp_unslash( $_GET['bucket'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
	$bucket = array_key_exists( $bucket, $buckets ) ? $bucket : 'all';
	$authority = isset( $_GET['authority'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['authority'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
	$authority = preg_match( '/^[a-z0-9.-]+(?::\d+)?$/', $authority ) ? $authority : '';
	$rows      = in_array( $bucket, array( 'authorities', 'local' ), true ) ? array() : axismundi_emoji_review_queue( $bucket, 100, $authority );
	$done   = isset( $_GET['ax_emoji_done'] ) ? sanitize_key( wp_unslash( $_GET['ax_emoji_done'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect notice only.
	$error  = isset( $_GET['ax_emoji_error'] ) ? sanitize_text_field( wp_unslash( $_GET['ax_emoji_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect notice only.
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Emojis', 'axismundi-emoji' ); ?></h1>
		<p><?php esc_html_e( 'Review observed remote emoji before their bytes are cached or rendered. This screen never loads remote images.', 'axismundi-emoji' ); ?></p>
		<?php if ( '' !== $authority ) : ?>
			<p><strong><?php echo esc_html( sprintf( __( 'Authority: %s', 'axismundi-emoji' ), $authority ) ); ?></strong> <a href="<?php echo esc_url( axismundi_emoji_admin_url() ); ?>"><?php esc_html_e( 'Show all', 'axismundi-emoji' ); ?></a></p>
		<?php endif; ?>

		<?php if ( 'approve_pending' === $done ) : ?>
			<?php $swept = isset( $_GET['ax_emoji_count'] ) ? absint( $_GET['ax_emoji_count'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect notice only. ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php echo esc_html( sprintf( _n( '%s waiting emoji approved.', '%s waiting emoji approved.', $swept, 'axismundi-emoji' ), number_format_i18n( $swept ) ) ); ?>
				<?php esc_html_e( 'Emoji that are still unverified, or whose licence forbids reuse, stay in the queue for a person to decide.', 'axismundi-emoji' ); ?>
			</p></div>
		<?php elseif ( 'uploaded' === $done ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Emoji added.', 'axismundi-emoji' ); ?></p></div>
		<?php elseif ( 'imported' === $done ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php esc_html_e( 'Copied into your registry, using the image already cached — no new storage and no new request.', 'axismundi-emoji' ); ?>
				<?php esc_html_e( 'It starts local-only: check the licence before publishing it with your messages.', 'axismundi-emoji' ); ?>
			</p></div>
		<?php elseif ( 'updated' === $done ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Emoji updated.', 'axismundi-emoji' ); ?></p></div>
		<?php elseif ( 'deleted' === $done ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Emoji removed.', 'axismundi-emoji' ); ?></p></div>
		<?php elseif ( 'authority_default' === $done ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Standing decision saved. It applies to emoji this authority declares from now on.', 'axismundi-emoji' ); ?></p></div>
		<?php elseif ( '' !== $done ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Emoji review state updated.', 'axismundi-emoji' ); ?></p></div>
		<?php endif; ?>
		<?php if ( '' !== $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Emoji review states', 'axismundi-emoji' ); ?>">
			<?php foreach ( $buckets as $slug => $label ) : ?>
				<a class="nav-tab<?php echo $bucket === $slug ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( axismundi_emoji_admin_url( array_filter( array( 'bucket' => 'all' === $slug ? null : $slug, 'authority' => $authority ) ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<?php if ( 'local' === $bucket ) : ?>
			<?php axismundi_emoji_render_local_tab(); ?>
		<?php elseif ( 'authorities' === $bucket ) : ?>
			<?php axismundi_emoji_render_authorities_tab(); ?>
		<?php elseif ( empty( $rows ) ) : ?>
			<p><?php esc_html_e( 'No remote emoji match this review state.', 'axismundi-emoji' ); ?></p>
		<?php else : ?>
			<table class="widefat fixed striped">
				<thead><tr>
					<th scope="col"><?php esc_html_e( 'Emoji', 'axismundi-emoji' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Authority', 'axismundi-emoji' ); ?></th>
					<th scope="col"><?php esc_html_e( 'State', 'axismundi-emoji' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Metadata', 'axismundi-emoji' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Observed', 'axismundi-emoji' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'axismundi-emoji' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php axismundi_emoji_render_review_row( $row, $bucket ); ?>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * This site's own emoji.
 *
 * Unlike Review, this screen shows the images. There is no reason not to: these files
 * are on this site's own disk, so displaying one discloses nothing and contacts nobody —
 * which is precisely the property the Review queue lacks and why it stays metadata-only.
 *
 * @return void
 */
function axismundi_emoji_render_local_tab() : void {
	$rows      = axismundi_emoji_local_all();
	$can_write = current_user_can( 'upload_files' );
	?>
	<h2><?php esc_html_e( 'Upload an emoji', 'axismundi-emoji' ); ?></h2>
	<?php if ( ! $can_write ) : ?>
		<p><?php esc_html_e( 'You do not have permission to upload files.', 'axismundi-emoji' ); ?></p>
	<?php else : ?>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="axismundi_emoji_upload">
			<?php wp_nonce_field( 'axismundi_emoji_upload' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ax-emoji-file"><?php esc_html_e( 'Image', 'axismundi-emoji' ); ?></label></th>
					<td>
						<input type="file" id="ax-emoji-file" name="emoji_file" accept="image/png,image/gif,image/webp" required>
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: maximum file size. */
									__( 'PNG, GIF, or WebP. Square, and %s or smaller — these are what FEP-9098 asks of emoji this site publishes, so other servers render them correctly.', 'axismundi-emoji' ),
									size_format( AXISMUNDI_EMOJI_OUTBOUND_MAX_BYTES )
								)
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ax-emoji-shortcode"><?php esc_html_e( 'Shortcode', 'axismundi-emoji' ); ?></label></th>
					<td>
						<code>:</code><input type="text" id="ax-emoji-shortcode" name="shortcode" class="regular-text" pattern="[a-zA-Z0-9_]{2,}" required><code>:</code>
						<p class="description"><?php esc_html_e( 'Letters, digits, and underscores; at least two characters. This is what people type.', 'axismundi-emoji' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ax-emoji-category"><?php esc_html_e( 'Category', 'axismundi-emoji' ); ?></label></th>
					<td><input type="text" id="ax-emoji-category" name="category" class="regular-text" list="ax-emoji-categories">
						<datalist id="ax-emoji-categories">
							<?php foreach ( array_unique( array_filter( wp_list_pluck( $rows, 'category' ) ) ) as $known ) : ?>
								<option value="<?php echo esc_attr( (string) $known ); ?>"></option>
							<?php endforeach; ?>
						</datalist>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sharing', 'axismundi-emoji' ); ?></th>
					<td>
						<label><input type="checkbox" name="local_only" value="1"> <?php esc_html_e( 'Keep on this site only', 'axismundi-emoji' ); ?></label>
						<p class="description"><?php esc_html_e( 'A local-only emoji is never published in an outgoing message, so other servers see the shortcode as text.', 'axismundi-emoji' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Add emoji', 'axismundi-emoji' ) ); ?>
		</form>
	<?php endif; ?>

	<h2><?php esc_html_e( 'This site’s emoji', 'axismundi-emoji' ); ?></h2>
	<?php if ( empty( $rows ) ) : ?>
		<p><?php esc_html_e( 'No local emoji yet.', 'axismundi-emoji' ); ?></p>
		<?php return; ?>
	<?php endif; ?>
	<table class="widefat striped">
		<thead><tr>
			<th scope="col" style="width:56px;"><?php esc_html_e( 'Image', 'axismundi-emoji' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Shortcode', 'axismundi-emoji' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Category', 'axismundi-emoji' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Aliases', 'axismundi-emoji' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Licence', 'axismundi-emoji' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Sharing', 'axismundi-emoji' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Image', 'axismundi-emoji' ); ?></th>
			<?php if ( $can_write ) : ?><th scope="col"><?php esc_html_e( 'Actions', 'axismundi-emoji' ); ?></th><?php endif; ?>
		</tr></thead>
		<tbody>
		<?php foreach ( $rows as $row ) : ?>
			<?php
			$id      = (int) $row['id'];
			$src     = axismundi_emoji_file_url( $row );
			$form    = 'ax-emoji-local-' . $id;
			$aliases = json_decode( (string) ( $row['aliases'] ?? '' ), true );
			$aliases = is_array( $aliases ) ? implode( ', ', array_map( 'strval', $aliases ) ) : '';
			$details = array_filter(
				array(
					(string) ( $row['media_type'] ?? '' ),
					sprintf( '%d×%d', (int) $row['width'], (int) $row['height'] ),
					size_format( (int) $row['byte_size'] ),
					! empty( $row['animated'] ) ? __( 'animated', 'axismundi-emoji' ) : '',
				)
			);
			$origin = (string) ( $row['imported_from_authority'] ?? '' );
			?>
			<tr>
				<td><?php if ( '' !== $src ) : ?><img src="<?php echo esc_url( $src ); ?>" alt="" width="40" height="40" style="width:40px;height:40px;object-fit:contain;"><?php endif; ?></td>
				<td>
					<code><?php echo esc_html( (string) $row['shortcode'] ); ?></code>
					<?php if ( '' !== $origin ) : ?>
						<br><span class="description"><?php echo esc_html( sprintf( /* translators: %s: origin host. */ __( 'copied from %s', 'axismundi-emoji' ), $origin ) ); ?></span>
					<?php endif; ?>
					<?php if ( $can_write ) : ?>
						<form id="<?php echo esc_attr( $form ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
							<input type="hidden" name="action" value="axismundi_emoji_update_local">
							<input type="hidden" name="emoji_id" value="<?php echo esc_attr( (string) $id ); ?>">
							<?php wp_nonce_field( 'axismundi_emoji_update_local_' . $id ); ?>
						</form>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $can_write ) : ?>
						<input form="<?php echo esc_attr( $form ); ?>" type="text" name="category" class="small-text" list="ax-emoji-categories" value="<?php echo esc_attr( (string) ( $row['category'] ?? '' ) ); ?>">
					<?php else : ?>
						<?php echo esc_html( (string) ( $row['category'] ?? '' ) ); ?>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $can_write ) : ?>
						<input form="<?php echo esc_attr( $form ); ?>" type="text" name="aliases" class="small-text" value="<?php echo esc_attr( $aliases ); ?>" placeholder="<?php esc_attr_e( 'comma separated', 'axismundi-emoji' ); ?>">
					<?php else : ?>
						<?php echo esc_html( $aliases ); ?>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $can_write ) : ?>
						<input form="<?php echo esc_attr( $form ); ?>" type="text" name="license_text" class="small-text" value="<?php echo esc_attr( (string) ( $row['license_text'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. CC0', 'axismundi-emoji' ); ?>">
					<?php else : ?>
						<?php echo esc_html( (string) ( $row['license_text'] ?? '' ) ); ?>
					<?php endif; ?>
					<br><span class="description"><?php echo esc_html( (string) ( $row['license_state'] ?? 'unknown' ) ); ?></span>
				</td>
				<td>
					<?php if ( $can_write ) : ?>
						<?php $restricted = 'restricted' === (string) ( $row['license_state'] ?? '' ); ?>
						<select form="<?php echo esc_attr( $form ); ?>" name="local_only" <?php disabled( $restricted ); ?>>
							<option value="0"<?php selected( empty( $row['local_only'] ) ); ?>><?php esc_html_e( 'Published with messages', 'axismundi-emoji' ); ?></option>
							<option value="1"<?php selected( ! empty( $row['local_only'] ) ); ?>><?php esc_html_e( 'This site only', 'axismundi-emoji' ); ?></option>
						</select>
						<?php if ( $restricted ) : ?>
							<br><span class="description"><?php esc_html_e( 'Its licence forbids republishing.', 'axismundi-emoji' ); ?></span>
						<?php endif; ?>
						<br><label><input form="<?php echo esc_attr( $form ); ?>" type="checkbox" name="is_sensitive" value="1" <?php checked( ! empty( $row['is_sensitive'] ) ); ?>> <?php esc_html_e( 'Sensitive', 'axismundi-emoji' ); ?></label>
					<?php else : ?>
						<?php echo esc_html( empty( $row['local_only'] ) ? __( 'Published with messages', 'axismundi-emoji' ) : __( 'This site only', 'axismundi-emoji' ) ); ?>
						<?php if ( ! empty( $row['is_sensitive'] ) ) : ?><br><?php esc_html_e( 'Sensitive', 'axismundi-emoji' ); ?><?php endif; ?>
					<?php endif; ?>
				</td>
				<td>
					<?php echo esc_html( implode( ' · ', $details ) ); ?>
					<?php if ( '' !== $src ) : ?>
						<br><a href="<?php echo esc_url( $src ); ?>" target="_blank" rel="noreferrer noopener"><?php esc_html_e( 'Open file', 'axismundi-emoji' ); ?></a>
					<?php endif; ?>
				</td>
				<?php if ( $can_write ) : ?>
					<td>
						<?php submit_button( __( 'Save', 'axismundi-emoji' ), 'secondary small', 'submit', false, array( 'form' => $form ) ); ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:4px 0 0;" onsubmit="return confirm('<?php echo esc_attr( sprintf( /* translators: %s: shortcode. */ __( 'Delete %s? Messages that already used it will show the shortcode as text.', 'axismundi-emoji' ), (string) $row['shortcode'] ) ); ?>');">
							<input type="hidden" name="action" value="axismundi_emoji_delete_local">
							<input type="hidden" name="emoji_id" value="<?php echo esc_attr( (string) $id ); ?>">
							<?php wp_nonce_field( 'axismundi_emoji_delete_local_' . $id ); ?>
							<?php submit_button( __( 'Delete', 'axismundi-emoji' ), 'delete small', 'submit', false ); ?>
						</form>
					</td>
				<?php endif; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description">
		<?php esc_html_e( 'Category, aliases, and licence are filled in here because ActivityPub does not carry them: an Emoji on the wire has a name, an image, and a version, and nothing else. A copied emoji therefore arrives without them, and re-fetching would not help.', 'axismundi-emoji' ); ?>
	</p>
	<?php
}

/**
 * The per-authority standing decision.
 *
 * The review queue asks "is this emoji acceptable"; this tab asks the prior question,
 * "is this server's judgement acceptable". Trusting `misskey.io` once removes the
 * per-emoji friction for everything it declares afterwards, while an unfamiliar
 * authority keeps arriving as pending — which is the only reason the queue is bearable
 * at all once a site follows more than a handful of instances.
 *
 * @return void
 */
function axismundi_emoji_render_authorities_tab() : void {
	$summary = axismundi_emoji_authority_summary();
	if ( empty( $summary ) ) {
		echo '<p>' . esc_html__( 'No remote authority has declared an emoji here yet.', 'axismundi-emoji' ) . '</p>';
		return;
	}
	$defaults = array(
		'pending'  => __( 'Review each one', 'axismundi-emoji' ),
		'approved' => __( 'Approve automatically', 'axismundi-emoji' ),
		'rejected' => __( 'Reject automatically', 'axismundi-emoji' ),
	);
	?>
	<p class="description"><?php esc_html_e( 'A standing decision applies to emoji this authority declares from now on. It never reaches backwards: use “Approve the waiting emoji” for the ones already in the queue. Fallback priority is display-only: set it only on trusted representative instances, such as misskey.io. 0 disables substitution; 1 is the first fallback source, 2 the next, and so on. When the declaring server has no cached emoji, the lowest non-zero priority wins. A tie for the same shortcode stays text rather than choosing an authority arbitrarily.', 'axismundi-emoji' ); ?></p>
	<table class="widefat fixed striped">
		<thead><tr>
			<th scope="col"><?php esc_html_e( 'Authority', 'axismundi-emoji' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Emoji', 'axismundi-emoji' ); ?></th>
			<th scope="col"><?php esc_html_e( 'New emoji from this authority', 'axismundi-emoji' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Fallback priority', 'axismundi-emoji' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $summary as $row ) : ?>
			<?php
			$host       = (string) ( $row['emoji_authority'] ?? '' );
			$current    = (string) ( $row['review_default'] ?? 'pending' );
			$current    = isset( $defaults[ $current ] ) ? $current : 'pending';
			$fallback_priority = max( 0, (int) ( $row['fallback_priority'] ?? 0 ) );
			$pending    = (int) ( $row['pending'] ?? 0 );
			$latest_batch = function_exists( 'axismundi_emoji_latest_approval_batch' ) ? axismundi_emoji_latest_approval_batch( $host ) : null;
			$unbatched_approved = function_exists( 'axismundi_emoji_unbatched_approved_count' ) ? axismundi_emoji_unbatched_approved_count( $host ) : 0;
			$restricted = (int) ( $row['restricted'] ?? 0 );
			$counts     = array(
				sprintf( __( '%s total', 'axismundi-emoji' ), number_format_i18n( (int) ( $row['total'] ?? 0 ) ) ),
				sprintf( __( '%s approved', 'axismundi-emoji' ), number_format_i18n( (int) ( $row['approved'] ?? 0 ) ) ),
				sprintf( __( '%s pending', 'axismundi-emoji' ), number_format_i18n( $pending ) ),
			);
			if ( $restricted > 0 ) {
				$counts[] = sprintf( __( '%s licence-restricted', 'axismundi-emoji' ), number_format_i18n( $restricted ) );
			}
			?>
			<tr>
				<td><?php axismundi_emoji_render_authority_link( $host ); ?></td>
				<td>
					<a href="<?php echo esc_url( axismundi_emoji_admin_url( array( 'authority' => $host ) ) ); ?>"><?php echo esc_html( implode( ' · ', $counts ) ); ?></a>
				</td>
				<td>
					<form id="ax-emoji-authority-<?php echo esc_attr( md5( $host ) ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin:0;">
						<input type="hidden" name="action" value="axismundi_emoji_authority">
						<input type="hidden" name="authority" value="<?php echo esc_attr( $host ); ?>">
						<?php wp_nonce_field( 'axismundi_emoji_authority_' . $host ); ?>
						<label class="screen-reader-text" for="ax-emoji-default-<?php echo esc_attr( md5( $host ) ); ?>"><?php echo esc_html( sprintf( __( 'Default for %s', 'axismundi-emoji' ), $host ) ); ?></label>
						<select id="ax-emoji-default-<?php echo esc_attr( md5( $host ) ); ?>" name="review_default">
							<?php foreach ( $defaults as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php submit_button( __( 'Save', 'axismundi-emoji' ), 'secondary small', 'save_default', false ); ?>
						<?php if ( $pending > 0 ) : ?>
						<?php submit_button( __( 'Approve the waiting emoji', 'axismundi-emoji' ), 'small', 'approve_pending', false, array( 'onclick' => "return confirm('" . esc_attr__( 'Approve every waiting emoji from this authority? You can undo this batch afterwards.', 'axismundi-emoji' ) . "');" ) ); ?>
						<?php endif; ?>
						<?php if ( is_array( $latest_batch ) ) : ?>
							<input type="hidden" name="approval_batch" value="<?php echo esc_attr( (string) $latest_batch['batch'] ); ?>">
							<?php submit_button( sprintf( _n( 'Undo latest bulk approval (%s emoji)', 'Undo latest bulk approval (%s emoji)', (int) $latest_batch['count'], 'axismundi-emoji' ), number_format_i18n( (int) $latest_batch['count'] ) ), 'secondary small', 'undo_batch', false, array( 'onclick' => "return confirm('" . esc_attr__( 'Move only the emoji approved by this bulk action back to review and release their cached files?', 'axismundi-emoji' ) . "');" ) ); ?>
						<?php endif; ?>
						<?php if ( $unbatched_approved > 0 ) : ?>
							<span class="description"><?php printf( wp_kses_post( _n( '%1$s approved emoji is not part of a reversible bulk batch. <a href="%2$s">Review it individually.</a>', '%1$s approved emoji are not part of a reversible bulk batch. <a href="%2$s">Review them individually.</a>', $unbatched_approved, 'axismundi-emoji' ) ), number_format_i18n( $unbatched_approved ), esc_url( axismundi_emoji_admin_url( array( 'bucket' => 'approved', 'authority' => $host ) ) ) ); ?></span>
						<?php endif; ?>
					</form>
				</td>
				<td>
					<label class="screen-reader-text" for="ax-emoji-fallback-<?php echo esc_attr( md5( $host ) ); ?>"><?php echo esc_html( sprintf( __( 'Fallback priority for %s', 'axismundi-emoji' ), $host ) ); ?></label>
					<input form="ax-emoji-authority-<?php echo esc_attr( md5( $host ) ); ?>" type="number" min="0" max="999" step="1" id="ax-emoji-fallback-<?php echo esc_attr( md5( $host ) ); ?>" name="fallback_priority" value="<?php echo esc_attr( (string) $fallback_priority ); ?>">
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Compact metadata for an operator.
 *
 * APNG is a PNG container, so a byte sniffer correctly calls it `image/png` while an
 * ActivityPub document may use the more useful `image/apng` declaration. Showing both
 * as competing facts is noise. Prefer the declaration when present; otherwise show the
 * detected type so a valid document which omitted `icon.mediaType` is not blank.
 *
 * @param array<string,mixed> $row Registry row.
 * @return string[]
 */
function axismundi_emoji_review_metadata( array $row ) : array {
	$metadata = array();
	$declared = (string) ( $row['declared_media_type'] ?? '' );
	$detected = (string) ( $row['media_type'] ?? '' );
	$type = '' !== $declared ? $declared : $detected;
	if ( '' !== $type ) {
		$metadata[] = $type;
	}
	$license = (string) ( $row['license_state'] ?? 'unknown' );
	if ( '' !== $license ) {
		$metadata[] = $license;
	}
	return $metadata;
}

/** @param array<string,mixed> $row @param string $bucket Current list filter. @return void */
function axismundi_emoji_render_review_row( array $row, string $bucket ) : void {
	$id       = (int) ( $row['id'] ?? 0 );
	$bucket   = axismundi_emoji_review_bucket( $row );
	$commands = axismundi_emoji_review_commands( $row );
	$links    = array();
	if ( '' !== (string) ( $row['source_url'] ?? '' ) ) {
		$links[] = '<a href="' . esc_url( (string) $row['source_url'] ) . '" target="_blank" rel="noreferrer noopener">' . esc_html__( 'Open original', 'axismundi-emoji' ) . '</a>';
	} elseif ( '' !== (string) ( $row['verification_uri'] ?? '' ) ) {
		$links[] = '<a href="' . esc_url( (string) $row['verification_uri'] ) . '" target="_blank" rel="noreferrer noopener">' . esc_html__( 'Open verification document', 'axismundi-emoji' ) . '</a>';
	}
	$metadata = axismundi_emoji_review_metadata( $row );
	$references = axismundi_emoji_reference_count( $id );
	?>
	<tr>
		<td><code><?php echo esc_html( (string) ( $row['shortcode'] ?? '' ) ); ?></code></td>
	<td><?php axismundi_emoji_render_authority_link( (string) ( $row['emoji_authority'] ?? '' ) ); ?></td>
		<td><strong><?php echo esc_html( ucfirst( $bucket ) ); ?></strong><?php if ( '' !== (string) ( $row['review_reason'] ?? '' ) ) : ?><br><span class="description"><?php echo esc_html( (string) $row['review_reason'] ); ?></span><?php endif; ?></td>
		<td><?php echo esc_html( implode( ' · ', $metadata ) ); ?><?php if ( ! empty( $links ) ) : ?><br><?php echo wp_kses_post( implode( ' · ', $links ) ); ?><?php endif; ?></td>
		<td><?php echo esc_html( sprintf( _n( '%s reference', '%s references', $references, 'axismundi-emoji' ), number_format_i18n( $references ) ) ); ?><br><span class="description"><?php echo esc_html( (string) ( $row['last_seen_at'] ?? '' ) ); ?></span></td>
		<td>
			<?php foreach ( $commands as $action => $label ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin:0 4px 4px 0;">
					<input type="hidden" name="action" value="axismundi_emoji_review">
					<input type="hidden" name="emoji_id" value="<?php echo esc_attr( (string) $id ); ?>">
					<input type="hidden" name="review_action" value="<?php echo esc_attr( $action ); ?>">
					<input type="hidden" name="bucket" value="<?php echo esc_attr( $bucket ); ?>">
					<?php wp_nonce_field( 'axismundi_emoji_review_' . $id ); ?>
					<?php submit_button( $label, 'secondary small', 'submit', false ); ?>
				</form>
			<?php endforeach; ?>
			<?php
			/*
			 * Offered only once the bytes are here and approved, because the import copies
			 * what is on disk rather than fetching again — which is what makes it free, and
			 * what keeps the copy byte-identical to the original instead of whatever a proxy
			 * would have re-encoded on the way.
			 */
			$importable = 'approved' === (string) ( $row['review_status'] ?? '' )
				&& '' !== (string) ( $row['cached_path'] ?? '' )
				&& 'restricted' !== (string) ( $row['license_state'] ?? '' )
				&& current_user_can( 'upload_files' );
			?>
			<?php if ( $importable ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin:0 4px 4px 0;">
					<input type="hidden" name="action" value="axismundi_emoji_import">
					<input type="hidden" name="emoji_id" value="<?php echo esc_attr( (string) $id ); ?>">
					<input type="hidden" name="bucket" value="<?php echo esc_attr( $bucket ); ?>">
					<?php wp_nonce_field( 'axismundi_emoji_import_' . $id ); ?>
					<?php submit_button( __( 'Copy to Local', 'axismundi-emoji' ), 'secondary small', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

/** Render an authority link into this plugin's filtered emoji catalogue. @param string $authority Host. @return void */
function axismundi_emoji_render_authority_link( string $authority ) : void {
	$authority = strtolower( trim( $authority ) );
	if ( '' === $authority ) {
		return;
	}
	$url = axismundi_emoji_admin_url( array( 'authority' => $authority ) );
	echo '<a href="' . esc_url( $url ) . '"><code>' . esc_html( $authority ) . '</code></a>';
}

/** @return void */
function axismundi_emoji_handle_review_action() : void {
	if ( ! current_user_can( AXISMUNDI_EMOJI_CAPABILITY ) ) {
		wp_die( esc_html__( 'You cannot review custom emoji.', 'axismundi-emoji' ), '', array( 'response' => 403 ) );
	}
	$emoji_id = isset( $_POST['emoji_id'] ) ? absint( $_POST['emoji_id'] ) : 0;
	$action   = isset( $_POST['review_action'] ) ? sanitize_key( wp_unslash( $_POST['review_action'] ) ) : '';
	$bucket   = isset( $_POST['bucket'] ) ? sanitize_key( wp_unslash( $_POST['bucket'] ) ) : 'all';
	check_admin_referer( 'axismundi_emoji_review_' . $emoji_id );
	$result = axismundi_emoji_review_apply( $emoji_id, $action, get_current_user_id() );
	$back   = axismundi_emoji_admin_url( 'all' === $bucket ? array() : array( 'bucket' => $bucket ) );
	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'ax_emoji_error', rawurlencode( $result->get_error_message() ), $back ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_emoji_done', $action, $back ) );
	exit;
}
add_action( 'admin_post_axismundi_emoji_review', 'axismundi_emoji_handle_review_action' );

/**
 * Store an authority's standing decision, and optionally sweep its waiting queue.
 *
 * The two buttons share a form but not a meaning, so which was pressed decides what
 * happens: saving records a judgement about the future, sweeping acts on the present.
 * Pressing the sweep also saves, because an operator who trusts a queue enough to clear
 * it has said something about the authority too.
 *
 * @return void
 */
function axismundi_emoji_handle_authority_action() : void {
	if ( ! current_user_can( AXISMUNDI_EMOJI_CAPABILITY ) ) {
		wp_die( esc_html__( 'You cannot review custom emoji.', 'axismundi-emoji' ), '', array( 'response' => 403 ) );
	}
	$authority = isset( $_POST['authority'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['authority'] ) ) ) : '';
	check_admin_referer( 'axismundi_emoji_authority_' . $authority );

	$back = axismundi_emoji_admin_url( array( 'bucket' => 'authorities' ) );
	if ( '' === $authority || ! preg_match( '/^[a-z0-9.-]+(?::\d+)?$/', $authority ) ) {
		wp_safe_redirect( add_query_arg( 'ax_emoji_error', rawurlencode( __( 'That authority is not a host this site has observed.', 'axismundi-emoji' ) ), $back ) );
		exit;
	}

	$default = isset( $_POST['review_default'] ) ? sanitize_key( wp_unslash( $_POST['review_default'] ) ) : 'pending';
	$default = in_array( $default, AXISMUNDI_EMOJI_REVIEW_STATES, true ) ? $default : 'pending';
	$priority = isset( $_POST['fallback_priority'] ) ? absint( $_POST['fallback_priority'] ) : 0;
	$saved   = axismundi_emoji_set_authority_default( $authority, $default, get_current_user_id(), $priority );
	if ( ! $saved ) {
		wp_safe_redirect( add_query_arg( 'ax_emoji_error', rawurlencode( __( 'The standing decision could not be stored.', 'axismundi-emoji' ) ), $back ) );
		exit;
	}
	if ( isset( $_POST['undo_batch'] ) ) {
		$batch  = isset( $_POST['approval_batch'] ) ? sanitize_text_field( wp_unslash( $_POST['approval_batch'] ) ) : '';
		$undone = axismundi_emoji_undo_approval_batch( $authority, $batch, get_current_user_id() );
		wp_safe_redirect( add_query_arg( array( 'ax_emoji_done' => 'undo_approval_batch', 'ax_emoji_count' => $undone ), $back ) );
		exit;
	}

	if ( isset( $_POST['approve_pending'] ) ) {
		$approved = axismundi_emoji_approve_pending_for_authority( $authority, get_current_user_id() );
		wp_safe_redirect( add_query_arg( array( 'ax_emoji_done' => 'approve_pending', 'ax_emoji_count' => $approved ), $back ) );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'ax_emoji_done', 'authority_default', $back ) );
	exit;
}
add_action( 'admin_post_axismundi_emoji_authority', 'axismundi_emoji_handle_authority_action' );

/** @return void */
function axismundi_emoji_handle_upload_action() : void {
	if ( ! current_user_can( AXISMUNDI_EMOJI_CAPABILITY ) || ! current_user_can( 'upload_files' ) ) {
		wp_die( esc_html__( 'You cannot add custom emoji.', 'axismundi-emoji' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'axismundi_emoji_upload' );
	$back = axismundi_emoji_admin_url( array( 'bucket' => 'local' ) );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the $_FILES entry is handed to wp_handle_upload(), which validates it.
	$file = isset( $_FILES['emoji_file'] ) && is_array( $_FILES['emoji_file'] ) ? $_FILES['emoji_file'] : array();
	if ( array() === $file || ! isset( $file['tmp_name'] ) || '' === (string) $file['tmp_name'] ) {
		wp_safe_redirect( add_query_arg( 'ax_emoji_error', rawurlencode( __( 'Choose an image to upload.', 'axismundi-emoji' ) ), $back ) );
		exit;
	}

	$result = axismundi_emoji_handle_upload(
		$file,
		isset( $_POST['shortcode'] ) ? sanitize_text_field( wp_unslash( $_POST['shortcode'] ) ) : '',
		array(
			'category'   => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'local_only' => ! empty( $_POST['local_only'] ),
		)
	);
	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'ax_emoji_error', rawurlencode( $result->get_error_message() ), $back ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_emoji_done', 'uploaded', $back ) );
	exit;
}
add_action( 'admin_post_axismundi_emoji_upload', 'axismundi_emoji_handle_upload_action' );

/** @return void */
function axismundi_emoji_handle_import_action() : void {
	if ( ! current_user_can( AXISMUNDI_EMOJI_CAPABILITY ) || ! current_user_can( 'upload_files' ) ) {
		wp_die( esc_html__( 'You cannot add custom emoji.', 'axismundi-emoji' ), '', array( 'response' => 403 ) );
	}
	$emoji_id = isset( $_POST['emoji_id'] ) ? absint( $_POST['emoji_id'] ) : 0;
	check_admin_referer( 'axismundi_emoji_import_' . $emoji_id );
	$bucket = isset( $_POST['bucket'] ) ? sanitize_key( wp_unslash( $_POST['bucket'] ) ) : 'all';
	$back   = axismundi_emoji_admin_url( 'all' === $bucket ? array() : array( 'bucket' => $bucket ) );
	$result = axismundi_emoji_import_remote( $emoji_id );
	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'ax_emoji_error', rawurlencode( $result->get_error_message() ), $back ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_emoji_done', 'imported', axismundi_emoji_admin_url( array( 'bucket' => 'local' ) ) ) );
	exit;
}
add_action( 'admin_post_axismundi_emoji_import', 'axismundi_emoji_handle_import_action' );

/** @return void */
function axismundi_emoji_handle_update_local_action() : void {
	if ( ! current_user_can( AXISMUNDI_EMOJI_CAPABILITY ) || ! current_user_can( 'upload_files' ) ) {
		wp_die( esc_html__( 'You cannot change custom emoji.', 'axismundi-emoji' ), '', array( 'response' => 403 ) );
	}
	$emoji_id = isset( $_POST['emoji_id'] ) ? absint( $_POST['emoji_id'] ) : 0;
	check_admin_referer( 'axismundi_emoji_update_local_' . $emoji_id );
	$back = axismundi_emoji_admin_url( array( 'bucket' => 'local' ) );

	/*
	 * An unchecked checkbox posts nothing, so its absence is the value `0` rather than
	 * "leave alone". The other fields are always present in this form; keying on the POST
	 * body directly would silently clear them for any caller that omitted one.
	 */
	$result = axismundi_emoji_update_local(
		$emoji_id,
		array(
			'category'     => isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : '',
			'aliases'      => isset( $_POST['aliases'] ) ? sanitize_text_field( wp_unslash( $_POST['aliases'] ) ) : '',
			'license_text' => isset( $_POST['license_text'] ) ? sanitize_text_field( wp_unslash( $_POST['license_text'] ) ) : '',
			'local_only'   => ! empty( $_POST['local_only'] ),
			'is_sensitive' => ! empty( $_POST['is_sensitive'] ),
		)
	);
	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'ax_emoji_error', rawurlencode( $result->get_error_message() ), $back ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_emoji_done', 'updated', $back ) );
	exit;
}
add_action( 'admin_post_axismundi_emoji_update_local', 'axismundi_emoji_handle_update_local_action' );

/** @return void */
function axismundi_emoji_handle_delete_local_action() : void {
	if ( ! current_user_can( AXISMUNDI_EMOJI_CAPABILITY ) || ! current_user_can( 'upload_files' ) ) {
		wp_die( esc_html__( 'You cannot remove custom emoji.', 'axismundi-emoji' ), '', array( 'response' => 403 ) );
	}
	$emoji_id = isset( $_POST['emoji_id'] ) ? absint( $_POST['emoji_id'] ) : 0;
	check_admin_referer( 'axismundi_emoji_delete_local_' . $emoji_id );
	$back = axismundi_emoji_admin_url( array( 'bucket' => 'local' ) );
	if ( ! axismundi_emoji_delete_local( $emoji_id ) ) {
		wp_safe_redirect( add_query_arg( 'ax_emoji_error', rawurlencode( __( 'That emoji could not be removed.', 'axismundi-emoji' ) ), $back ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_emoji_done', 'deleted', $back ) );
	exit;
}
add_action( 'admin_post_axismundi_emoji_delete_local', 'axismundi_emoji_handle_delete_local_action' );
