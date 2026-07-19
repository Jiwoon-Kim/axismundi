<?php
/**
 * Note structured envelope authoring + REST field regression (dev-only).
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_auth_results   = array();
$ax_auth_post_ids  = array();
$ax_auth_user_ids  = array();
$ax_auth_actor_ids = array();
$ax_auth_prev_user = get_current_user_id();

/** @param bool[] $results Results. */
function ax_auth_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$login = 'ax_auth_' . strtolower( wp_generate_password( 8, false, false ) );
	$uid   = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_auth_user_ids[] = $uid;
	$actor = axismundi_actors_ensure_for_user( $uid );
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_auth_actor_ids[] = $actor->get_identity_id();
		axismundi_actors_register_handle( $actor->get_identity_id(), $login );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	}

	$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $uid, 'post_title' => 'Authoring note' ) );
	$ax_auth_post_ids[] = $post_id;

	// The Note post type is now edited in the block editor, not the Classic Editor.
	ax_auth_assert( $ax_auth_results, 'the Note post type uses the block editor with a restricted palette', use_block_editor_for_post_type( AXISMUNDI_NOTE_POST_TYPE ) && in_array( 'core/paragraph', axismundi_note_allowed_block_types( true, (object) array( 'post' => get_post( $post_id ) ) ), true ) && ! in_array( 'core/image', (array) axismundi_note_allowed_block_types( true, (object) array( 'post' => get_post( $post_id ) ) ), true ) );
	ax_auth_assert( $ax_auth_results, 'the Note post type does not acquire the Core category taxonomy', ! in_array( 'category', get_object_taxonomies( AXISMUNDI_NOTE_POST_TYPE ), true ) );
	$panel_script = file_get_contents( dirname( __DIR__ ) . '/assets/editor/envelope-panel.js' );
	ax_auth_assert( $ax_auth_results, 'the Note Federation panel exposes the authored Quote policy choices', is_string( $panel_script ) && false !== strpos( $panel_script, 'Who can quote this post?' ) && false !== strpos( $panel_script, 'quotePolicy' ) && false !== strpos( $panel_script, "value: 'anyone'" ) && false !== strpos( $panel_script, "value: 'followers'" ) && false !== strpos( $panel_script, "value: 'me'" ) );

	// The structured read exposes envelope defaults for a fresh Note.
	$default = axismundi_note_get_envelope( $post_id );
	ax_auth_assert( $ax_auth_results, 'the structured envelope view exposes defaults for a fresh Note', 'public' === $default['visibility'] && '' === $default['language'] && '' === $default['quotePolicy'] && false === $default['sensitive'] && array() === $default['mentions'] );

	// The structured save round-trips through the fail-closed field contract.
	$mention = 'https://example.com/users/authoring-' . strtolower( wp_generate_password( 6, false, false ) );
	$reply   = 'https://example.com/objects/reply-' . strtolower( wp_generate_password( 6, false, false ) );
	$saved   = axismundi_note_save_envelope(
		$post_id,
		array(
			'visibility'     => 'followers',
			'language'       => 'ko-KR',
			'inReplyTo'      => $reply,
			'quotePolicy'    => 'followers',
			'sensitive'      => true,
			'contentWarning' => 'cw',
			'mentions'       => array( $mention ),
		)
	);
	$view = axismundi_note_get_envelope( $post_id );
	ax_auth_assert( $ax_auth_results, 'a structured envelope save round-trips through the read view', is_array( $saved ) && 'followers' === $view['visibility'] && 'ko-KR' === $view['language'] && $reply === $view['inReplyTo'] && 'followers' === $view['quotePolicy'] && true === $view['sensitive'] && 'cw' === $view['contentWarning'] && array( $mention ) === $view['mentions'] );

	// The structured save fails closed on an explicitly invalid value.
	$bad = axismundi_note_save_envelope( $post_id, array( 'visibility' => 'nonsense' ) );
	ax_auth_assert( $ax_auth_results, 'a structured save fails closed on an invalid field', is_wp_error( $bad ) && 'ax_note_visibility' === $bad->get_error_code() && 'followers' === axismundi_note_get_envelope( $post_id )['visibility'] );

	// The REST field wires the panel write to the server-authoritative save.
	wp_set_current_user( $uid );
	$request = new WP_REST_Request( 'POST', '/wp/v2/' . AXISMUNDI_NOTE_POST_TYPE . '/' . $post_id );
	$request->set_body_params( array( 'axismundi_note_envelope' => array( 'visibility' => 'unlisted', 'language' => 'en', 'quotePolicy' => 'anyone', 'mentions' => array() ) ) );
	$response = rest_do_request( $request );
	$data     = $response instanceof WP_REST_Response ? $response->get_data() : array();
	$after    = axismundi_note_get_envelope( $post_id );
	ax_auth_assert( $ax_auth_results, 'the REST envelope field persists a panel write through save_envelope', ! $response->is_error() && 'unlisted' === $after['visibility'] && 'en' === $after['language'] && 'anyone' === $after['quotePolicy'] && isset( $data['axismundi_note_envelope']['visibility'] ) && 'unlisted' === $data['axismundi_note_envelope']['visibility'] );

	// A REST write with an invalid envelope is rejected rather than silently applied.
	$bad_request = new WP_REST_Request( 'POST', '/wp/v2/' . AXISMUNDI_NOTE_POST_TYPE . '/' . $post_id );
	$bad_request->set_body_params( array( 'axismundi_note_envelope' => array( 'visibility' => 'bogus' ) ) );
	$bad_response = rest_do_request( $bad_request );
	ax_auth_assert( $ax_auth_results, 'the REST envelope field rejects an invalid panel write', $bad_response->is_error() && 'unlisted' === axismundi_note_get_envelope( $post_id )['visibility'] );
} finally {
	wp_set_current_user( $ax_auth_prev_user );
	foreach ( array_unique( $ax_auth_post_ids ) as $pid ) {
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $pid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( get_post( (int) $pid ) instanceof WP_Post ) {
			wp_delete_post( (int) $pid, true );
		}
	}
	foreach ( array_unique( $ax_auth_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( ! empty( $ax_auth_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_auth_user_ids ) as $delete_uid ) {
			if ( get_userdata( (int) $delete_uid ) ) {
				wp_delete_user( (int) $delete_uid );
			}
		}
	}
}

$ax_auth_failures = count( array_filter( $ax_auth_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_auth_results ), $ax_auth_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_auth_failures > 0 ? 1 : 0 );
}
exit( $ax_auth_failures > 0 ? 1 : 0 );
