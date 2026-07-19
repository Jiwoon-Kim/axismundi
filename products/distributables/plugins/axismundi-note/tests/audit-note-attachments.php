<?php
/**
 * Note attachment relation authoring regression (dev-only).
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_na_results     = array();
$ax_na_post_ids    = array();
$ax_na_attach_ids  = array();
$ax_na_user_ids    = array();
$ax_na_previous_user = get_current_user_id();
$ax_na_previous_mode = get_option( AXISMUNDI_MEDIA_MODE_OPTION, AXISMUNDI_MEDIA_MODE_DEFAULT );

/** @param bool[] $results Results. */
function ax_na_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	update_option( AXISMUNDI_MEDIA_MODE_OPTION, 'independent' );
	$uid = (int) wp_insert_user( array( 'user_login' => 'ax_na_' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_na_user_ids[] = $uid;
	wp_set_current_user( $uid );
	$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $uid, 'post_content' => '<p>Attachment fixture.</p>' ) );
	$ax_na_post_ids[] = $post_id;

	foreach ( array( 'One', 'Two', 'Other provider' ) as $title ) {
		$ax_na_attach_ids[] = (int) wp_insert_attachment( array( 'post_title' => $title, 'post_status' => 'inherit', 'post_author' => $uid, 'post_mime_type' => 'image/jpeg' ) );
	}
	list( $one, $two, $other ) = $ax_na_attach_ids;
	axismundi_media_relations_replace(
		array( 'post_id' => $post_id, 'type' => 'post' ),
		'fixture-other',
		array( array( 'object_attachment_id' => $other, 'predicate' => 'as:attachment', 'role' => 'content' ) )
	);

	$saved = axismundi_note_save_envelope( $post_id, array( 'visibility' => 'public', 'attachments' => array( $two, $one, $two ) ) );
	$picker_rows = axismundi_media_relations_for_subject( $post_id, AXISMUNDI_NOTE_ATTACHMENT_PROVIDER );
	ax_na_assert( $ax_na_results, 'one structured save deduplicates and stores the explicit picker order in its own provider', ! is_wp_error( $saved ) && array( $two, $one ) === axismundi_note_attachment_ids( $post_id ) && 2 === count( $picker_rows ) && 'picker:0' === $picker_rows[0]['source_key'] && 'picker:1' === $picker_rows[1]['source_key'] );
	ax_na_assert( $ax_na_results, 'replacing Note picker rows leaves every other Media Library provider untouched', 1 === count( axismundi_media_relations_for_subject( $post_id, 'fixture-other' ) ) );

	axismundi_note_save_envelope( $post_id, array( 'language' => 'en' ) );
	ax_na_assert( $ax_na_results, 'an envelope save without the attachments key preserves the selected relations', array( $two, $one ) === axismundi_note_attachment_ids( $post_id ) );

	$invalid = axismundi_note_save_envelope( $post_id, array( 'visibility' => 'followers', 'attachments' => array( 99999999 ) ) );
	ax_na_assert( $ax_na_results, 'an invalid attachment rejects the whole structured save before changing envelope or relations', is_wp_error( $invalid ) && 'public' === axismundi_note_get_envelope( $post_id )['visibility'] && array( $two, $one ) === axismundi_note_attachment_ids( $post_id ) );

	$too_many = array_fill( 0, AXISMUNDI_NOTE_ATTACHMENT_MAX_COUNT + 1, $one );
	$bounded  = axismundi_note_save_envelope( $post_id, array( 'attachments' => $too_many ) );
	ax_na_assert( $ax_na_results, 'an over-limit picker list fails closed without truncating or replacing prior rows', is_wp_error( $bounded ) && array( $two, $one ) === axismundi_note_attachment_ids( $post_id ) );

	update_option( AXISMUNDI_MEDIA_MODE_OPTION, 'core' );
	$core_mode = axismundi_note_save_envelope( $post_id, array( 'attachments' => array( $one ) ) );
	ax_na_assert( $ax_na_results, 'non-empty attachment authoring fails closed outside Media Library Independent mode', is_wp_error( $core_mode ) && array( $two, $one ) === axismundi_note_attachment_ids( $post_id ) );
	update_option( AXISMUNDI_MEDIA_MODE_OPTION, 'independent' );

	$cleared = axismundi_note_save_envelope( $post_id, array( 'attachments' => array() ) );
	ax_na_assert( $ax_na_results, 'an explicit empty picker list clears only the Note provider', ! is_wp_error( $cleared ) && array() === axismundi_note_attachment_ids( $post_id ) && 1 === count( axismundi_media_relations_for_subject( $post_id, 'fixture-other' ) ) );

	$request = new WP_REST_Request( 'POST', '/wp/v2/' . AXISMUNDI_NOTE_POST_TYPE . '/' . $post_id );
	$request->set_body_params( array( 'axismundi_note_envelope' => array_merge( axismundi_note_get_envelope( $post_id ), array( 'attachments' => array( $one, $two ) ) ) ) );
	$response = rest_do_request( $request );
	ax_na_assert( $ax_na_results, 'the registered structured REST field persists attachment IDs through the Gutenberg save path', 200 === $response->get_status() && array( $one, $two ) === axismundi_note_attachment_ids( $post_id ) );
	wp_delete_attachment( $one, true );
	$ax_na_attach_ids = array_values( array_diff( $ax_na_attach_ids, array( $one ) ) );
	ax_na_assert( $ax_na_results, 'deleting one Media Library object removes its Note relation while preserving surviving attachments', array( $two ) === axismundi_note_attachment_ids( $post_id ) );
} finally {
	update_option( AXISMUNDI_MEDIA_MODE_OPTION, $ax_na_previous_mode );
	wp_set_current_user( $ax_na_previous_user );
	foreach ( array_unique( $ax_na_post_ids ) as $post_id ) {
		if ( function_exists( 'axismundi_media_relations_delete_subject' ) ) {
			axismundi_media_relations_delete_subject( (int) $post_id );
		}
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( get_post( (int) $post_id ) instanceof WP_Post ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_unique( $ax_na_attach_ids ) as $attachment_id ) {
		if ( get_post( (int) $attachment_id ) instanceof WP_Post ) {
			wp_delete_attachment( (int) $attachment_id, true );
		}
	}
	if ( ! empty( $ax_na_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_na_user_ids ) as $uid ) {
			if ( get_userdata( (int) $uid ) ) {
				wp_delete_user( (int) $uid );
			}
		}
	}
}

$ax_na_failures = count( array_filter( $ax_na_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_na_results ), $ax_na_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_na_failures > 0 ? 1 : 0 );
}
exit( $ax_na_failures > 0 ? 1 : 0 );
