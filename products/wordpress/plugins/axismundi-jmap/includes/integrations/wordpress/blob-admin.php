<?php
/** Operational C2S blob interface; site administration never overrides blob ACLs. */
namespace Axismundi\Jmap\Blobs\Admin;
use Axismundi\Jmap\Blobs;
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', static function () : void {
	$hook = add_management_page( 'JMAP Blobs', 'JMAP Blobs', 'read', 'axismundi-jmap-blobs', __NAMESPACE__ . '\\page' );
	add_action( 'admin_enqueue_scripts', static function ( $screen ) use ( $hook ) : void {
		if ( $screen === $hook ) {
			$file = dirname( __DIR__, 3 ) . '/assets/blobs.css';
			wp_enqueue_style( 'ax-jmap-blobs', plugins_url( 'assets/blobs.css', dirname( __DIR__, 3 ) . '/axismundi-jmap.php' ), array(), filemtime( $file ) );
		}
	} );
} );

/** The manager has no original filename: supply an explicit name and the stored media type. */
function download_url( string $account, array $entry ) : string {
	$type = (string) $entry['content_type'];
	$extension = wp_get_default_extension_for_mime_type( strtolower( trim( explode( ';', $type )[0] ) ) );
	$extension = is_string( $extension ) && preg_match( '/\A[a-z0-9]+\z/iD', $extension ) ? $extension : 'bin';
	$name = $entry['blob_id'] . '.' . $extension;
	return add_query_arg( array( '_wpnonce' => wp_create_nonce( 'wp_rest' ), 'type' => $type ), rest_url( 'axismundi/jmap/download/' . $account . '/' . $entry['blob_id'] . '/' . rawurlencode( $name ) ) );
}

function page() : void {
	if ( ! current_user_can( 'read' ) ) { return; }
	$accounts = \Axismundi\Jmap\account_actors( get_current_user_id() );
	$selected = isset( $_GET['account'] ) && is_string( $_GET['account'] ) ? sanitize_text_field( wp_unslash( $_GET['account'] ) ) : '';
	$page = isset( $_GET['blob_page'] ) && is_string( $_GET['blob_page'] ) ? max( 1, min( 100000, absint( wp_unslash( $_GET['blob_page'] ) ) ) ) : 1;
	$notice = get_transient( 'ax_jmap_blob_notice_' . get_current_user_id() );
	delete_transient( 'ax_jmap_blob_notice_' . get_current_user_id() );
	echo '<div class="wrap" id="ax-jmap-blobs"><h1>' . esc_html__( 'JMAP Blobs', 'axismundi-jmap' ) . '</h1>';
	if ( is_string( $notice ) ) { echo '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>'; }
	$verified = get_option( 'ax_jmap_blob_verified', array() );
	$place = Blobs\location();
	$valid = ! is_wp_error( $place ) && ( $verified['probe_version'] ?? 0 ) === Blobs\PROBE_VERSION && ( $verified['root'] ?? '' ) === $place['root'] && ( $verified['layout'] ?? '' ) === Blobs\blob_shard( 'b' . str_repeat( '0', 64 ) ) && ( $verified['checked_at'] ?? 0 ) >= time() - 600;
	echo '<p><strong>' . esc_html( $valid ? __( 'Storage verified', 'axismundi-jmap' ) : __( 'Storage unverified; verification required', 'axismundi-jmap' ) ) . '</strong></p>';
	if ( ! is_wp_error( $place ) && current_user_can( 'manage_options' ) ) { echo '<p><code>' . esc_html( $place['root'] ) . '</code></p>'; }
	if ( current_user_can( 'manage_options' ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'ax_jmap_blob_admin' );
		echo '<input type="hidden" name="action" value="ax_jmap_blob_admin"><input type="hidden" name="command" value="settings"><label for="blob-root">' . esc_html__( 'Private storage root', 'axismundi-jmap' ) . '</label> <input class="large-text" id="blob-root" name="root" value="' . esc_attr( (string) get_option( 'ax_jmap_blob_root', '' ) ) . '">';
		submit_button( __( 'Save and Verify Storage', 'axismundi-jmap' ) );
		echo '</form>';
	} elseif ( ! $valid ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'ax_jmap_blob_admin' );
		echo '<input type="hidden" name="action" value="ax_jmap_blob_admin"><input type="hidden" name="command" value="verify">';
		submit_button( __( 'Verify Storage', 'axismundi-jmap' ) );
		echo '</form>';
	}
	echo '<form method="get"><input type="hidden" name="page" value="axismundi-jmap-blobs"><label for="blob-account">' . esc_html__( 'Account', 'axismundi-jmap' ) . '</label> <select id="blob-account" name="account"><option value="">' . esc_html__( 'Select Account', 'axismundi-jmap' ) . '</option>';
	foreach ( $accounts as $id => $actor ) { echo '<option value="' . esc_attr( $id ) . '" ' . selected( $selected, $id, false ) . '>' . esc_html( $actor->get_display_name() . ' (' . $id . ')' ) . '</option>'; }
	echo '</select> ';
	submit_button( __( 'Open', 'axismundi-jmap' ), 'secondary', 'submit', false );
	echo '</form>';
	if ( ! isset( $accounts[$selected] ) ) { echo '</div>'; return; }
	echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'ax_jmap_blob_admin' );
	echo '<input type="hidden" name="action" value="ax_jmap_blob_admin"><input type="hidden" name="command" value="upload"><input type="hidden" name="account" value="' . esc_attr( $selected ) . '"><input type="file" name="blob" required aria-label="' . esc_attr__( 'File', 'axismundi-jmap' ) . '">';
	submit_button( __( 'Upload', 'axismundi-jmap' ), 'primary', 'submit', false, $valid ? array() : array( 'disabled' => 'disabled' ) );
	echo '</form><table class="widefat striped"><thead><tr><th>blobId</th><th>' . esc_html__( 'Type', 'axismundi-jmap' ) . '</th><th>' . esc_html__( 'Size', 'axismundi-jmap' ) . '</th><th>' . esc_html__( 'Actions', 'axismundi-jmap' ) . '</th></tr></thead><tbody>';
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE account_identity_id=%d AND uploader_user_id=%d ORDER BY created_at DESC,blob_id DESC LIMIT 51 OFFSET %d', Blobs\uploads_table(), $accounts[$selected]->get_identity_id(), get_current_user_id(), ( $page - 1 ) * 50 ), ARRAY_A );
	foreach ( array_slice( $rows ?: array(), 0, 50 ) as $entry ) {
		// Referenced bytes need their referencing object's ACL, even in the upload history.
		if ( is_wp_error( Blobs\row( $selected, $entry['blob_id'] ) ) ) { continue; }
		$url = download_url( $selected, $entry );
		echo '<tr><td class="blob-id"><code>' . esc_html( $entry['blob_id'] ) . '</code></td><td>' . esc_html( $entry['content_type'] ) . '</td><td>' . esc_html( size_format( (int) $entry['size'] ) ) . '</td><td><a href="' . esc_url( $url ) . '">' . esc_html__( 'Download', 'axismundi-jmap' ) . '</a><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'ax_jmap_blob_admin' );
		echo '<input type="hidden" name="action" value="ax_jmap_blob_admin"><input type="hidden" name="command" value="delete"><input type="hidden" name="account" value="' . esc_attr( $selected ) . '"><input type="hidden" name="blob_id" value="' . esc_attr( $entry['blob_id'] ) . '">';
		submit_button( __( 'Delete', 'axismundi-jmap' ), 'delete', 'submit', false );
		echo '</form></td></tr>';
	}
	echo '</tbody></table><div class="tablenav"><div class="tablenav-pages">';
	foreach ( array( $page - 1 => __( 'Previous', 'axismundi-jmap' ), $page + 1 => __( 'Next', 'axismundi-jmap' ) ) as $target => $label ) {
		if ( $target < 1 || ( $target > $page && count( $rows ?: array() ) <= 50 ) ) { continue; }
		echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'axismundi-jmap-blobs', 'account' => $selected, 'blob_page' => $target ), admin_url( 'tools.php' ) ) ) . '">' . esc_html( $label ) . '</a> ';
	}
	echo '</div></div></div>';
}

function action() : void {
	$input = static fn( $key ) => isset( $_POST[$key] ) && is_string( $_POST[$key] ) ? sanitize_text_field( wp_unslash( $_POST[$key] ) ) : '';
	if ( ! current_user_can( 'read' ) || ! wp_verify_nonce( $input( '_wpnonce' ), 'ax_jmap_blob_admin' ) ) { wp_die( esc_html__( 'Permission denied.', 'axismundi-jmap' ), '', array( 'response' => 403 ) ); }
	$account = $input( 'account' ); $command = $input( 'command' );
	$result = Blobs\failure( 'Unknown command.', 400 );
	if ( 'settings' === $command && current_user_can( 'manage_options' ) ) {
		$old = get_option( 'ax_jmap_blob_root', '' );
		update_option( 'ax_jmap_blob_root', $input( 'root' ), false );
		delete_option( 'ax_jmap_blob_verified' );
		$result = Blobs\verify_storage();
		if ( is_wp_error( $result ) ) { update_option( 'ax_jmap_blob_root', $old, false ); }
	} elseif ( 'verify' === $command ) { $result = Blobs\verify_storage();
	} elseif ( 'upload' === $command ) {
		$file = isset( $_FILES['blob'] ) && is_array( $_FILES['blob'] ) ? $_FILES['blob'] : array();
		if ( is_string( $file['tmp_name'] ?? null ) && is_string( $file['name'] ?? null ) && is_string( $file['type'] ?? null ) && is_int( $file['error'] ?? null ) ) {
			$file['name'] = sanitize_file_name( $file['name'] );
			$file['type'] = sanitize_mime_type( $file['type'] );
			$result = Blobs\store( $account, $file, true );
		}
	} elseif ( 'delete' === $command ) { $result = Blobs\remove( $account, $input( 'blob_id' ) ); }
	set_transient( 'ax_jmap_blob_notice_' . get_current_user_id(), is_wp_error( $result ) ? $result->get_error_message() : __( 'Completed.', 'axismundi-jmap' ), MINUTE_IN_SECONDS );
	wp_safe_redirect( add_query_arg( array( 'page' => 'axismundi-jmap-blobs', 'account' => $account ), admin_url( 'tools.php' ) ) );
	exit;
}
add_action( 'admin_post_ax_jmap_blob_admin', __NAMESPACE__ . '\\action' );
