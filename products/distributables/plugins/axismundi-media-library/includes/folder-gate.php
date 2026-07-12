<?php
/**
 * Phase 2b password challenge for folder chains.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/**
 * Folder chain from hidden root to the assigned folder.
 *
 * @param int $term_id Folder term ID.
 * @return int[]
 */
function axismundi_media_folder_chain( int $term_id ) : array {
	$chain   = array();
	$visited = array();
	while ( $term_id > 0 && ! isset( $visited[ $term_id ] ) ) {
		$visited[ $term_id ] = true;
		array_unshift( $chain, $term_id );
		$term = get_term( $term_id, AXISMUNDI_MEDIA_FOLDER_TAX );
		$term_id = $term instanceof WP_Term ? (int) $term->parent : 0;
	}
	return $chain;
}

/**
 * Cookie name for one password folder.
 *
 * @param int $term_id Folder term ID.
 * @return string
 */
function axismundi_media_gate_cookie_name( int $term_id ) : string {
	return 'ax_media_gate_' . $term_id;
}

/**
 * Signed cookie value. Including the password hash invalidates old cookies when
 * the password changes without storing the password in the browser.
 *
 * @param int $term_id Folder term ID.
 * @return string
 */
function axismundi_media_gate_cookie_token( int $term_id ) : string {
	$hash = (string) get_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_PASSWORD_META, true );
	return hash_hmac( 'sha256', $term_id . '|' . $hash, wp_salt( 'auth' ) );
}

/**
 * Has this individual gate been unlocked in the current browser?
 *
 * @param int $term_id Folder term ID.
 * @return bool
 */
function axismundi_media_gate_cookie_valid( int $term_id ) : bool {
	$name = axismundi_media_gate_cookie_name( $term_id );
	if ( ! isset( $_COOKIE[ $name ] ) ) {
		return false;
	}
	$value = sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
	return hash_equals( axismundi_media_gate_cookie_token( $term_id ), $value );
}

/**
 * First password gate in the chain that still needs a challenge.
 *
 * @param int $term_id Folder term ID.
 * @return int 0 when the chain is open/unlocked or the current user manages it.
 */
function axismundi_media_locked_folder_gate( int $term_id ) : int {
	if ( $term_id <= 0 || axismundi_media_can_manage_folder( $term_id ) ) {
		return 0;
	}
	foreach ( axismundi_media_folder_chain( $term_id ) as $folder_id ) {
		if ( 'password' === axismundi_media_folder_access( $folder_id ) && ! axismundi_media_gate_cookie_valid( $folder_id ) ) {
			return $folder_id;
		}
	}
	return 0;
}

/**
 * Locked gate inherited by an Attachment's assigned folder.
 *
 * @param int $attachment_id Attachment ID.
 * @return int
 */
function axismundi_media_locked_gate_for_attachment( int $attachment_id ) : int {
	return axismundi_media_locked_folder_gate( axismundi_media_attachment_folder( $attachment_id ) );
}

/**
 * Set a signed unlock cookie.
 *
 * @param int $term_id Folder term ID.
 * @return void
 */
function axismundi_media_set_gate_cookie( int $term_id ) : void {
	$name  = axismundi_media_gate_cookie_name( $term_id );
	$value = axismundi_media_gate_cookie_token( $term_id );
	setcookie(
		$name,
		$value,
		array(
			'expires'  => time() + MONTH_IN_SECONDS,
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
	$_COOKIE[ $name ] = $value;
}

/**
 * Process the password challenge and return to the protected URL.
 *
 * @return void
 */
function axismundi_media_handle_gate_unlock() : void {
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
	if ( ! axismundi_media_is_independent() || 'POST' !== $method || ! isset( $_POST['ax_media_gate_action'] ) ) {
		return;
	}
	$term_id = isset( $_POST['folder_id'] ) ? absint( $_POST['folder_id'] ) : 0;
	$redirect = isset( $_POST['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ), axismundi_media_landing_url() ) : axismundi_media_landing_url();
	if ( $term_id <= 0 || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ax_media_unlock_' . $term_id ) ) {
		wp_safe_redirect( add_query_arg( 'ax_media_gate_error', 1, $redirect ) );
		exit;
	}
	$password = isset( $_POST['folder_password'] ) ? (string) wp_unslash( $_POST['folder_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must not be transformed before verification.
	$hash     = (string) get_term_meta( $term_id, AXISMUNDI_MEDIA_FOLDER_PASSWORD_META, true );
	if ( '' !== $hash && wp_check_password( $password, $hash ) ) {
		axismundi_media_set_gate_cookie( $term_id );
		wp_safe_redirect( remove_query_arg( 'ax_media_gate_error', $redirect ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_media_gate_error', 1, $redirect ) );
	exit;
}
add_action( 'template_redirect', 'axismundi_media_handle_gate_unlock', 0 );

/**
 * Use the protected-media template whenever a single or folder route is locked.
 *
 * @param string $template Located template.
 * @return string
 */
function axismundi_media_gate_template_include( string $template ) : string {
	global $wp_query;
	if ( ! axismundi_media_is_independent() || ! $wp_query instanceof WP_Query || ! $wp_query->get( 'ax_media_gate_required' ) ) {
		return $template;
	}
	$templates = array( 'media-protected.php', 'index.php' );
	return locate_block_template( locate_template( $templates ), 'media-protected', $templates );
}
add_filter( 'template_include', 'axismundi_media_gate_template_include', 100 );
