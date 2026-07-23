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
	if ( isset( $_GET['ax_op_collection_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect status only.
		$message = sanitize_text_field( wp_unslash( $_GET['ax_op_collection_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- escaped display-only message.
		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	}
}

/** Render a metadata-only remote Collection probe. */
function axismundi_op_render_remote_collection_probe( array $probe ) : void {
	$root  = isset( $probe['root'] ) && is_array( $probe['root'] ) ? $probe['root'] : array();
	$items = isset( $probe['items'] ) && is_array( $probe['items'] ) ? $probe['items'] : array();
	?>
	<h3><?php esc_html_e( 'Remote Collection preview', 'axismundi-object-projections' ); ?></h3>
	<table class="widefat striped" style="max-width:1000px"><tbody>
		<tr><th><?php esc_html_e( 'ID', 'axismundi-object-projections' ); ?></th><td><code><?php echo esc_html( axismundi_op_remote_collection_uri( $root['id'] ?? '' ) ); ?></code></td></tr>
		<tr><th><?php esc_html_e( 'Type', 'axismundi-object-projections' ); ?></th><td><?php echo esc_html( is_array( $root['type'] ?? null ) ? implode( ', ', array_map( 'strval', $root['type'] ) ) : (string) ( $root['type'] ?? '' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Name', 'axismundi-object-projections' ); ?></th><td><?php echo esc_html( (string) ( $root['name'] ?? '' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Attributed to', 'axismundi-object-projections' ); ?></th><td><?php echo esc_html( axismundi_op_remote_collection_uri( $root['attributedTo'] ?? '' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Total items', 'axismundi-object-projections' ); ?></th><td><?php echo esc_html( (string) absint( $root['totalItems'] ?? count( $items ) ) ); ?></td></tr>
	</tbody></table>
	<h4><?php esc_html_e( 'First page items', 'axismundi-object-projections' ); ?></h4>
	<table class="widefat striped" style="max-width:1000px"><thead><tr><th><?php esc_html_e( 'Type', 'axismundi-object-projections' ); ?></th><th><?php esc_html_e( 'Name', 'axismundi-object-projections' ); ?></th><th><?php esc_html_e( 'Object URI', 'axismundi-object-projections' ); ?></th><th><?php esc_html_e( 'Media', 'axismundi-object-projections' ); ?></th></tr></thead><tbody>
	<?php if ( empty( $items ) ) : ?>
		<tr><td colspan="4"><?php esc_html_e( 'No items on the first page.', 'axismundi-object-projections' ); ?></td></tr>
	<?php else : foreach ( $items as $item ) :
		$item_array = is_array( $item ) ? $item : array();
		$uri        = axismundi_op_remote_collection_uri( $item );
		if ( '' === $uri ) {
			$uri = axismundi_op_remote_collection_uri( $item_array['id'] ?? '' );
		}
		$urls       = $item_array['url'] ?? array();
		$url_list   = is_array( $urls ) && array_is_list( $urls ) ? $urls : array( $urls );
		$media      = array();
		foreach ( $url_list as $link ) {
			if ( is_array( $link ) && 'text/html' !== ( $link['mediaType'] ?? '' ) ) {
				$media[] = trim( (string) ( $link['mediaType'] ?? '' ) . ' ' . absint( $link['width'] ?? 0 ) . 'x' . absint( $link['height'] ?? 0 ) );
			}
		}
		?>
		<tr><td><?php echo esc_html( (string) ( $item_array['type'] ?? '' ) ); ?></td><td><?php echo esc_html( (string) ( $item_array['name'] ?? '' ) ); ?></td><td><?php echo '' !== $uri ? '<a href="' . esc_url( $uri ) . '" rel="noopener noreferrer">' . esc_html( $uri ) . '</a>' : '—'; ?></td><td><?php echo esc_html( implode( ', ', array_filter( $media ) ) ); ?></td></tr>
	<?php endforeach; endif; ?>
	</tbody></table>
	<p class="description"><?php esc_html_e( 'Metadata-only probe: item URLs are not fetched and no remote binary is downloaded or cached.', 'axismundi-object-projections' ); ?></p>
	<?php
}

/** Normalize a scalar/object/list ActivityStreams member into a list. */
function axismundi_op_remote_admin_members( $value ) : array {
	if ( null === $value || '' === $value ) {
		return array();
	}
	return is_array( $value ) && array_is_list( $value ) ? $value : array( $value );
}

/** First readable type label from an ActivityStreams member. */
function axismundi_op_remote_admin_member_type( $member ) : string {
	if ( ! is_array( $member ) || ! isset( $member['type'] ) ) {
		return '';
	}
	$types = is_array( $member['type'] ) ? $member['type'] : array( $member['type'] );
	foreach ( $types as $type ) {
		if ( is_scalar( $type ) && '' !== trim( (string) $type ) ) {
			return sanitize_text_field( (string) $type );
		}
	}
	return '';
}

/** URI represented by a scalar or embedded AS Link/object. */
function axismundi_op_remote_admin_member_uri( $member ) : string {
	if ( is_array( $member ) ) {
		foreach ( array( 'href', 'url', 'id' ) as $key ) {
			if ( isset( $member[ $key ] ) ) {
				$uri = axismundi_op_remote_member_uri( $member[ $key ] );
				if ( '' !== $uri ) {
					return $uri;
				}
			}
		}
	}
	return axismundi_op_remote_member_uri( $member );
}

/** Human label represented by a scalar or embedded object. */
function axismundi_op_remote_admin_member_name( $member ) : string {
	if ( is_array( $member ) ) {
		foreach ( array( 'name', 'preferredUsername' ) as $key ) {
			if ( isset( $member[ $key ] ) && is_scalar( $member[ $key ] ) ) {
				return sanitize_text_field( (string) $member[ $key ] );
			}
		}
	}
	return is_scalar( $member ) ? sanitize_text_field( (string) $member ) : '';
}

/**
 * Link an Actor to its cached administrator record when available, otherwise remote.
 *
 * This performs no discovery or network request.
 */
function axismundi_op_remote_admin_reference_link( string $uri, string $label = '', bool $actor_candidate = false ) : string {
	$href     = $uri;
	$internal = false;
	if ( $actor_candidate && function_exists( 'axismundi_actors_get_by_uri' ) ) {
		$actor = axismundi_actors_get_by_uri( $uri );
		if ( $actor instanceof Axismundi_Actor ) {
			if ( $actor->is_local() && '' !== $actor->get_profile_url() ) {
				$href = $actor->get_profile_url();
			} elseif ( function_exists( 'axismundi_actors_remote_admin_url' ) ) {
				$href = add_query_arg( 'actor_id', $actor->get_identity_id(), axismundi_actors_remote_admin_url() );
			}
			$internal = $href !== $uri;
		}
	}
	$label = '' !== $label ? $label : $uri;
	return '<a href="' . esc_url( $href ) . '"' . ( $internal ? '' : ' rel="noopener noreferrer"' ) . '>' . esc_html( $label ) . '</a>';
}

/** Pretty, escaped JSON for diagnostic display. */
function axismundi_op_remote_admin_json( $value ) : string {
	$json = wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return is_string( $json ) ? $json : '';
}

/** Render tag, Mention, Hashtag, and Emoji metadata without remote media. */
function axismundi_op_render_remote_tags( array $payload ) : void {
	$tags = axismundi_op_remote_admin_members( $payload['tag'] ?? null );
	if ( empty( $tags ) ) {
		return;
	}
	?>
	<h3><?php esc_html_e( 'Tags and mentions', 'axismundi-object-projections' ); ?></h3>
	<table class="widefat striped" style="max-width: 1000px"><thead><tr><th><?php esc_html_e( 'Type', 'axismundi-object-projections' ); ?></th><th><?php esc_html_e( 'Name', 'axismundi-object-projections' ); ?></th><th><?php esc_html_e( 'Reference', 'axismundi-object-projections' ); ?></th></tr></thead><tbody>
	<?php foreach ( $tags as $tag ) :
		$type = axismundi_op_remote_admin_member_type( $tag );
		$name = axismundi_op_remote_admin_member_name( $tag );
		$uri  = axismundi_op_remote_admin_member_uri( $tag );
		?>
		<tr><td><?php echo esc_html( '' !== $type ? $type : '—' ); ?></td><td><?php echo esc_html( '' !== $name ? $name : '—' ); ?></td><td><?php echo '' !== $uri ? axismundi_op_remote_admin_reference_link( $uri, $uri, 'Mention' === $type ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes complete anchor. ?></td></tr>
	<?php endforeach; ?>
	</tbody></table>
	<?php
}

/** Render public and private audience declarations for administrator diagnostics. */
function axismundi_op_render_remote_audience( array $payload ) : void {
	$rows = array();
	foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience' ) as $property ) {
		foreach ( axismundi_op_remote_admin_members( $payload[ $property ] ?? null ) as $member ) {
			$rows[] = array( $property, $member );
		}
	}
	if ( empty( $rows ) ) {
		return;
	}
	?>
	<h3><?php esc_html_e( 'Audience', 'axismundi-object-projections' ); ?></h3>
	<table class="widefat striped" style="max-width: 1000px"><thead><tr><th><?php esc_html_e( 'Property', 'axismundi-object-projections' ); ?></th><th><?php esc_html_e( 'Recipient', 'axismundi-object-projections' ); ?></th></tr></thead><tbody>
	<?php foreach ( $rows as $row ) :
		$uri   = axismundi_op_remote_admin_member_uri( $row[1] );
		$label = axismundi_op_remote_admin_member_name( $row[1] );
		?>
		<tr><td><code><?php echo esc_html( $row[0] ); ?></code></td><td><?php echo '' !== $uri ? axismundi_op_remote_admin_reference_link( $uri, '' !== $label ? $label : $uri, true ) : esc_html( '' !== $label ? $label : '—' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes complete anchor. ?></td></tr>
	<?php endforeach; ?>
	</tbody></table>
	<?php
}

/** Render attachment descriptors only; never emit img/video/audio/embed elements. */
function axismundi_op_render_remote_attachments( array $payload ) : void {
	$attachments = axismundi_op_remote_admin_members( $payload['attachment'] ?? null );
	if ( empty( $attachments ) ) {
		return;
	}
	?>
	<h3><?php esc_html_e( 'Attachment metadata', 'axismundi-object-projections' ); ?></h3>
	<table class="widefat striped" style="max-width: 1000px"><thead><tr><th><?php esc_html_e( 'Type', 'axismundi-object-projections' ); ?></th><th><?php esc_html_e( 'Name', 'axismundi-object-projections' ); ?></th><th><?php esc_html_e( 'Media type', 'axismundi-object-projections' ); ?></th><th><?php esc_html_e( 'Dimensions', 'axismundi-object-projections' ); ?></th><th><?php esc_html_e( 'Remote reference', 'axismundi-object-projections' ); ?></th></tr></thead><tbody>
	<?php foreach ( $attachments as $attachment ) :
		$type       = axismundi_op_remote_admin_member_type( $attachment );
		$name       = axismundi_op_remote_admin_member_name( $attachment );
		$media_type = is_array( $attachment ) && isset( $attachment['mediaType'] ) && is_scalar( $attachment['mediaType'] ) ? sanitize_text_field( (string) $attachment['mediaType'] ) : '';
		$width      = is_array( $attachment ) && isset( $attachment['width'] ) ? absint( $attachment['width'] ) : 0;
		$height     = is_array( $attachment ) && isset( $attachment['height'] ) ? absint( $attachment['height'] ) : 0;
		$uri        = axismundi_op_remote_admin_member_uri( $attachment );
		?>
		<tr><td><?php echo esc_html( '' !== $type ? $type : '—' ); ?></td><td><?php echo esc_html( '' !== $name ? $name : '—' ); ?></td><td><?php echo esc_html( '' !== $media_type ? $media_type : '—' ); ?></td><td><?php echo esc_html( $width || $height ? $width . ' × ' . $height : '—' ); ?></td><td><?php echo '' !== $uri ? axismundi_op_remote_admin_reference_link( $uri ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes complete anchor. ?></td></tr>
	<?php endforeach; ?>
	</tbody></table>
	<?php
}

/** Render unnormalized extension properties plus the complete escaped payload. */
function axismundi_op_render_remote_payload( array $payload ) : void {
	$handled = array_flip( array( '@context', 'id', 'type', 'attributedTo', 'inReplyTo', 'url', 'name', 'summary', 'content', 'contentMap', 'mediaType', 'sensitive', 'published', 'updated', 'tag', 'to', 'cc', 'bto', 'bcc', 'audience', 'attachment' ) );
	$extra   = array_diff_key( $payload, $handled );
	if ( ! empty( $extra ) ) :
		?>
		<h3><?php esc_html_e( 'Additional properties', 'axismundi-object-projections' ); ?></h3>
		<table class="widefat striped" style="max-width: 1000px"><tbody>
		<?php foreach ( $extra as $property => $value ) : ?>
			<tr><th scope="row"><code><?php echo esc_html( (string) $property ); ?></code></th><td><pre style="white-space:pre-wrap;overflow-wrap:anywhere;margin:0"><?php echo esc_html( axismundi_op_remote_admin_json( $value ) ); ?></pre></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	<?php endif; ?>
	<details style="margin-top:16px;max-width:1000px">
		<summary><strong><?php esc_html_e( 'Raw JSON', 'axismundi-object-projections' ); ?></strong></summary>
		<pre style="max-height:600px;overflow:auto;white-space:pre-wrap;overflow-wrap:anywhere"><?php echo esc_html( axismundi_op_remote_admin_json( $payload ) ); ?></pre>
	</details>
	<?php
}

/** Render one metadata-only cached object. */
function axismundi_op_render_remote_object_detail( array $object ) : void {
	$payload = isset( $object['payload'] ) && is_array( $object['payload'] ) ? $object['payload'] : array();
	$view_url = function_exists( 'axismundi_op_cached_object_publicly_viewable' ) && axismundi_op_cached_object_publicly_viewable( $object )
		? axismundi_op_cached_object_view_url( (string) $object['object_uri'] )
		: '';
	?>
	<hr>
	<h2><?php echo esc_html( '' !== (string) $object['name'] ? (string) $object['name'] : (string) $object['object_type'] ); ?></h2>
	<table class="widefat striped" style="max-width: 1000px">
		<tbody>
			<tr><th scope="row"><?php esc_html_e( 'Canonical URI', 'axismundi-object-projections' ); ?></th><td><code><?php echo esc_html( (string) $object['object_uri'] ); ?></code></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Type / status', 'axismundi-object-projections' ); ?></th><td><?php echo esc_html( (string) $object['object_type'] . ' / ' . (string) $object['object_status'] ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Attributed to', 'axismundi-object-projections' ); ?></th><td><?php echo empty( $object['attributed_to_uri'] ) ? '—' : axismundi_op_remote_admin_reference_link( (string) $object['attributed_to_uri'], (string) $object['attributed_to_uri'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes complete anchor. ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Published / updated', 'axismundi-object-projections' ); ?></th><td><?php echo esc_html( (string) ( $object['published_at'] ?? '—' ) . ' / ' . (string) ( $object['remote_updated_at'] ?? '—' ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Sensitive', 'axismundi-object-projections' ); ?></th><td><?php echo null === $object['is_sensitive'] ? esc_html__( 'Not declared', 'axismundi-object-projections' ) : ( (int) $object['is_sensitive'] ? esc_html__( 'Yes', 'axismundi-object-projections' ) : esc_html__( 'No', 'axismundi-object-projections' ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Fetched / expires', 'axismundi-object-projections' ); ?></th><td><?php echo esc_html( (string) $object['fetched_at'] . ' / ' . (string) $object['expires_at'] ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Source page', 'axismundi-object-projections' ); ?></th><td><?php echo empty( $object['human_url'] ) ? '—' : '<a href="' . esc_url( (string) $object['human_url'] ) . '" rel="noopener noreferrer">' . esc_html__( 'Open remote page', 'axismundi-object-projections' ) . '</a>'; ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Cached view', 'axismundi-object-projections' ); ?></th><td><?php echo '' === $view_url ? '—' : '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'View', 'axismundi-object-projections' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Complete anchor escaped here. ?></td></tr>
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
	<?php axismundi_op_render_remote_tags( $payload ); ?>
	<?php axismundi_op_render_remote_audience( $payload ); ?>
	<?php axismundi_op_render_remote_attachments( $payload ); ?>
	<?php axismundi_op_render_remote_payload( $payload ); ?>
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
	$collection_probe = null;
	$probe_token      = isset( $_GET['ax_op_collection_probe'] ) ? sanitize_key( wp_unslash( $_GET['ax_op_collection_probe'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Opaque read-only transient token.
	if ( '' !== $probe_token ) {
		$stored_probe = get_transient( 'ax_op_collection_' . $probe_token );
		if ( is_array( $stored_probe ) && get_current_user_id() === (int) ( $stored_probe['user_id'] ?? 0 ) && is_array( $stored_probe['probe'] ?? null ) ) {
			$collection_probe = $stored_probe['probe'];
		}
	}
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

		<h2><?php esc_html_e( 'Remote Collections', 'axismundi-object-projections' ); ?></h2>
		<p><?php esc_html_e( 'Inspect a Collection root and its first page without storing objects or downloading media.', 'axismundi-object-projections' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="axismundi_op_probe_remote_collection">
			<?php wp_nonce_field( 'ax_op_probe_remote_collection' ); ?>
			<label class="screen-reader-text" for="ax-op-remote-collection"><?php esc_html_e( 'Remote Collection URL', 'axismundi-object-projections' ); ?></label>
			<input id="ax-op-remote-collection" type="url" name="remote_collection" class="large-text" placeholder="https://example.social/media/folder/uuid" required>
			<?php submit_button( __( 'Inspect Collection metadata', 'axismundi-object-projections' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php if ( is_array( $collection_probe ) ) { axismundi_op_render_remote_collection_probe( $collection_probe ); } ?>

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
					<?php $view_url = function_exists( 'axismundi_op_cached_object_publicly_viewable' ) && axismundi_op_cached_object_publicly_viewable( $object ) ? axismundi_op_cached_object_view_url( (string) $object['object_uri'] ) : ''; ?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( axismundi_op_remote_admin_url( (string) $object['object_uri'] ) ); ?>"><?php echo esc_html( '' !== (string) $object['name'] ? (string) $object['name'] : (string) $object['object_uri'] ); ?></a></strong>
							<?php if ( '' !== $view_url ) : ?><div class="row-actions"><span class="view"><a href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'View', 'axismundi-object-projections' ); ?></a></span></div><?php endif; ?>
						</td>
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

/** Probe one remote Collection without persistence. */
function axismundi_op_handle_probe_remote_collection() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot inspect remote Collections.', 'axismundi-object-projections' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'ax_op_probe_remote_collection' );
	$url    = isset( $_POST['remote_collection'] ) ? esc_url_raw( wp_unslash( $_POST['remote_collection'] ) ) : '';
	$result = '' !== $url ? axismundi_op_remote_collection_fetch( $url ) : new WP_Error( 'ax_op_collection_input', __( 'Enter a remote Collection URL.', 'axismundi-object-projections' ) );
	if ( is_wp_error( $result ) ) {
		wp_safe_redirect( add_query_arg( 'ax_op_collection_error', rawurlencode( $result->get_error_message() ), axismundi_op_remote_admin_url() ) );
		exit;
	}
	$token = strtolower( wp_generate_password( 20, false, false ) );
	set_transient( 'ax_op_collection_' . $token, array( 'user_id' => get_current_user_id(), 'probe' => $result ), 5 * MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( 'ax_op_collection_probe', $token, axismundi_op_remote_admin_url() ) );
	exit;
}
add_action( 'admin_post_axismundi_op_probe_remote_collection', 'axismundi_op_handle_probe_remote_collection' );

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
