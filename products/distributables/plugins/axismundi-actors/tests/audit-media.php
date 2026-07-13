<?php
/**
 * Phase 4b — actor avatar / header regression (dev-only; dist-excluded).
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/routing.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$ax_media_results = array();
$ax_media_ids     = array();
$ax_media_users   = array();
$ax_media_atts    = array();

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $cond Holds.
 * @return void
 */
function ax_media_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * @param int    $author Owner.
 * @param string $mime   MIME type.
 * @return int Attachment id.
 */
function ax_media_make_attachment( int $author, string $mime ) : int {
	return (int) wp_insert_attachment( array( 'post_title' => 'AX media', 'post_status' => 'inherit', 'post_mime_type' => $mime, 'post_author' => $author ) );
}

try {
	axismundi_actors_install();

	// Schema upgrade gate.
	$cols = (array) $wpdb->get_col( 'SHOW COLUMNS FROM ' . axismundi_actors_actors_table() ); // phpcs:ignore WordPress.DB
	ax_media_assert( $ax_media_results, 'schema v3 adds avatar/header columns and records the version', in_array( 'avatar_attachment_id', $cols, true ) && in_array( 'header_attachment_id', $cols, true ) && '3' === (string) get_option( 'ax_actors_db_version' ) );

	$uid = (int) wp_insert_user( array( 'user_login' => 'ax_media_alice', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_media_users[] = $uid;
	$bob = (int) wp_insert_user( array( 'user_login' => 'ax_media_bob', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_media_users[] = $bob;

	$actor = axismundi_actors_ensure_for_user( $uid );
	$ax_media_ids[] = $actor->get_identity_id();
	axismundi_actors_register_handle( $actor->get_identity_id(), 'media_alice' );

	$img = ax_media_make_attachment( $uid, 'image/jpeg' );
	$pdf = ax_media_make_attachment( $uid, 'application/pdf' );
	$bob_img = ax_media_make_attachment( $bob, 'image/jpeg' );
	$ax_media_atts = array( $img, $pdf, $bob_img );

	// Save avatar + header.
	$set_a = axismundi_actors_set_profile_media( $actor, 'avatar', $img, $uid );
	$set_h = axismundi_actors_set_profile_media( $actor, 'header', $img, $uid );
	$actor = axismundi_actors_get_by_uuid( $actor->get_uuid() );
	ax_media_assert( $ax_media_results, 'an owner can set avatar and header to their own image', true === $set_a && true === $set_h && $img === $actor->get_avatar_attachment_id() && $img === $actor->get_header_attachment_id() );

	// Reject non-image.
	$bad_mime = axismundi_actors_set_profile_media( $actor, 'avatar', $pdf, $uid );
	ax_media_assert( $ax_media_results, 'a non-image attachment is rejected', is_wp_error( $bad_mime ) && 'ax_actors_media_image' === $bad_mime->get_error_code() );

	// Reject another user's attachment.
	$bad_cap = axismundi_actors_set_profile_media( $actor, 'avatar', $bob_img, $uid );
	ax_media_assert( $ax_media_results, "another user's attachment is rejected", is_wp_error( $bad_cap ) && 'ax_actors_media_cap' === $bad_cap->get_error_code() );

	// Filter can deny (future Media Library private/sensitive policy).
	add_filter( 'axismundi_actors_can_use_profile_media', '__return_false' );
	$denied = axismundi_actors_set_profile_media( $actor, 'avatar', $img, $uid );
	remove_filter( 'axismundi_actors_can_use_profile_media', '__return_false' );
	ax_media_assert( $ax_media_results, 'the allow filter can deny an image', is_wp_error( $denied ) && 'ax_actors_media_denied' === $denied->get_error_code() );

	// Clear only nulls the reference.
	axismundi_actors_set_profile_media( $actor, 'header', 0, $uid );
	$actor = axismundi_actors_get_by_uuid( $actor->get_uuid() );
	ax_media_assert( $ax_media_results, 'clearing nulls the reference without deleting the attachment', 0 === $actor->get_header_attachment_id() && null !== get_post( $img ) );

	// delete_attachment releases the avatar reference.
	wp_delete_attachment( $img, true );
	$actor = axismundi_actors_get_by_uuid( $actor->get_uuid() );
	ax_media_assert( $ax_media_results, 'deleting the attachment releases the avatar reference', 0 === $actor->get_avatar_attachment_id() );

	// Fallback rendering: avatar falls back to core avatar; header renders nothing.
	$avatar_html = axismundi_actors_avatar_html( $actor, 96 );
	$header_html = axismundi_actors_header_html( $actor );
	ax_media_assert( $ax_media_results, 'avatar falls back to a core avatar; header is empty when unset', false !== strpos( $avatar_html, '<img' ) && '' === $header_html );

} finally {
	foreach ( $ax_media_atts as $att ) {
		if ( $att && get_post( $att ) ) {
			wp_delete_attachment( (int) $att, true );
		}
	}
	foreach ( array_unique( $ax_media_ids ) as $iid ) {
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( $ax_media_users as $u ) {
		if ( get_userdata( $u ) ) {
			wp_delete_user( $u );
		}
	}
}

$ax_media_failures = count( array_filter( $ax_media_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_media_results ), $ax_media_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_media_failures > 0 ? 1 : 0 );
}
exit( $ax_media_failures > 0 ? 1 : 0 );
