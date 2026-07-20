<?php
/**
 * Note view-model adapter + axismundi/object-view block regression (dev-only).
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_vm_results   = array();
$ax_vm_post_ids  = array();
$ax_vm_user_ids  = array();
$ax_vm_actor_ids = array();

/** @param bool[] $results Results. */
function ax_vm_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$login = 'axvm' . strtolower( wp_generate_password( 8, false, false ) );
	$uid   = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_vm_user_ids[] = $uid;
	$actor = axismundi_actors_ensure_for_user( $uid );
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_vm_actor_ids[] = $actor->get_identity_id();
		axismundi_actors_register_handle( $actor->get_identity_id(), $login );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	}

	$post_id = (int) wp_insert_post(
		array(
			'post_type'    => AXISMUNDI_NOTE_POST_TYPE,
			'post_status'  => 'draft',
			'post_author'  => $uid,
			'post_title'   => 'View model note.',
			'post_content' => '<!-- wp:paragraph --><p>Hello from a note.</p><!-- /wp:paragraph -->',
		)
	);
	$ax_vm_post_ids[] = $post_id;
	axismundi_note_save_envelope( $post_id, array( 'visibility' => 'public' ) );
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );

	$envelope = axismundi_note_get( $post_id );
	$active   = new Axismundi_Note_Source( (array) $envelope, get_post( $post_id ) );
	$uuid     = (string) $envelope['local_uuid'];

	// A broken earlier adapter cannot prevent the owning Note adapter from resolving.
	axismundi_op_register_object_view_model(
		'broken-fixture',
		array(
			'supports'  => static fn() : bool => throw new RuntimeException( 'fixture' ),
			'transform' => static fn() : array => array(),
			'priority'  => 1,
		)
	);

	// The Note adapter answers through the deterministic, exception-isolated OP registry.
	$vm = axismundi_op_object_view_model( $active );
	ax_vm_assert( $ax_vm_results, 'the OP registry resolves a Note source deterministically after an earlier adapter fails', is_array( $vm ) && 'Note' === $vm['type'] && 'active' === $vm['status'] && axismundi_note_object_uri( $uuid ) === $vm['id'] && false !== strpos( (string) $vm['content_html'], 'Hello from a note.' ) && '' !== (string) $vm['author']['name'] );

	// A non-Note source is passed through, not claimed.
	ax_vm_assert( $ax_vm_results, 'the Note adapter passes on a source it does not own', null === axismundi_op_object_view_model( 'not-a-source' ) );

	// The object-view block renders the bound active model.
	axismundi_op_set_current_object_view_model( $vm );
	$active_html = axismundi_op_render_object_view_block();
	axismundi_op_set_current_object_view_model( null );
	ax_vm_assert( $ax_vm_results, 'the object-view block renders an active Note with content and author but no deleted notice', false !== strpos( $active_html, 'axismundi-object--note' ) && false !== strpos( $active_html, 'Hello from a note.' ) && false !== strpos( $active_html, 'axismundi-object__author' ) && false === strpos( $active_html, 'has been deleted' ) );

	// An embedded object caller can choose a smaller heading and suppress the
	// personalized interaction slot without changing the source view model.
	axismundi_op_set_current_object_view_model( $vm );
	$compact_html = axismundi_op_render_object_view_block( array( 'headingTag' => 'h3', 'interactions' => false ) );
	axismundi_op_set_current_object_view_model( null );
	ax_vm_assert( $ax_vm_results, 'the object renderer supports a compact non-personalized embedding mode', false !== strpos( $compact_html, '<h3 class="axismundi-object__title">' ) && false === strpos( $compact_html, 'axismundi-object__interactions' ) );

	// The Actor feed is ledger-selected by Activities but a local Create(Note)
	// resolves the current Note source and renderer rather than duplicating the
	// stored Activity snapshot. The request binding must be restored afterward.
	$feed_item = array( 'type' => 'Create', 'actor_uri' => $actor->get_uri(), 'object_uri' => axismundi_note_object_uri( $uuid ) );
	axismundi_op_set_current_object_view_model( array( 'id' => 'fixture-previous' ) );
	$feed_html = (string) apply_filters( 'axismundi_act_actor_feed_object_html', '', $feed_item );
	$restored  = axismundi_op_current_object_view_model();
	axismundi_op_set_current_object_view_model( null );
	ax_vm_assert( $ax_vm_results, 'a matching public Create(Note) renders through the canonical compact Note view and restores request state', false !== strpos( $feed_html, 'axismundi-object--note' ) && false !== strpos( $feed_html, 'Hello from a note.' ) && false !== strpos( $feed_html, '<h3 class="axismundi-object__title">' ) && ! empty( $restored ) && 'fixture-previous' === ( $restored['id'] ?? '' ) );
	$wrong_feed_html = (string) apply_filters( 'axismundi_act_actor_feed_object_html', '', array_merge( $feed_item, array( 'actor_uri' => 'https://example.invalid/actors/not-the-author' ) ) );
	ax_vm_assert( $ax_vm_results, 'an Actor feed Create with mismatched attribution cannot render a Note object', '' === $wrong_feed_html );

	$media_vm = $vm;
	$media_vm['attachments'] = array(
		array(
			'type'      => 'Document',
			'mediaType' => 'application/pdf',
			'name'      => 'A document',
			'url'       => array( array( 'type' => 'Link', 'href' => home_url( '/document.pdf' ), 'mediaType' => 'application/pdf' ) ),
			'sensitive' => true,
			'summary'   => 'Attachment warning',
		),
	);
	axismundi_op_set_current_object_view_model( $media_vm );
	$media_html = axismundi_op_render_object_view_block();
	axismundi_op_set_current_object_view_model( null );
	ax_vm_assert( $ax_vm_results, 'the object-view block renders descriptor links behind Media-owned sensitivity', false !== strpos( $media_html, 'axismundi-object__attachments' ) && false !== strpos( $media_html, 'Attachment warning' ) && false !== strpos( $media_html, 'document.pdf' ) );

	// OP sanitizes the neutral model again instead of trusting every future adapter.
	$unsafe_vm                 = $vm;
	$unsafe_vm['content_html'] = '<p>safe</p><script>unsafe()</script>';
	axismundi_op_set_current_object_view_model( $unsafe_vm );
	$safe_html = axismundi_op_render_object_view_block();
	axismundi_op_set_current_object_view_model( null );
	ax_vm_assert( $ax_vm_results, 'the object-view render boundary strips unsafe adapter HTML', false !== strpos( $safe_html, '<p>safe</p>' ) && false === strpos( $safe_html, '<script' ) );

	// The block renders nothing when no model is bound to the request.
	axismundi_op_set_current_object_view_model( null );
	ax_vm_assert( $ax_vm_results, 'the object-view block renders nothing without a bound model', '' === axismundi_op_render_object_view_block() );

	// A tombstone survives the post and renders a privacy-minimal deleted notice.
	wp_delete_post( $post_id, true );
	$tomb_env = axismundi_note_get_by_uuid( $uuid );
	$tomb     = new Axismundi_Note_Source( (array) $tomb_env, null );
	$tomb_vm  = axismundi_op_object_view_model( $tomb );
	ax_vm_assert( $ax_vm_results, 'a tombstone source resolves to a minimal Tombstone view model', is_array( $tomb_vm ) && 'Tombstone' === $tomb_vm['type'] && 'tombstone' === $tomb_vm['status'] && ! isset( $tomb_vm['content_html'] ) && ! isset( $tomb_vm['author'] ) );

	axismundi_op_set_current_object_view_model( $tomb_vm );
	$tomb_html = axismundi_op_render_object_view_block();
	axismundi_op_set_current_object_view_model( null );
	ax_vm_assert( $ax_vm_results, 'the object-view block renders a tombstone as a deleted notice with no content or interactions', false !== strpos( $tomb_html, 'axismundi-object--tombstone' ) && false !== strpos( $tomb_html, 'has been deleted' ) && false === strpos( $tomb_html, 'Hello from a note.' ) && false === strpos( $tomb_html, 'axismundi-object__interactions' ) );
} finally {
	axismundi_op_set_current_object_view_model( null );
	foreach ( array_unique( $ax_vm_post_ids ) as $pid ) {
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $pid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( get_post( (int) $pid ) instanceof WP_Post ) {
			wp_delete_post( (int) $pid, true );
		}
	}
	foreach ( array_unique( $ax_vm_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( ! empty( $ax_vm_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_vm_user_ids ) as $delete_uid ) {
			if ( get_userdata( (int) $delete_uid ) ) {
				wp_delete_user( (int) $delete_uid );
			}
		}
	}
}

$ax_vm_failures = count( array_filter( $ax_vm_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_vm_results ), $ax_vm_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_vm_failures > 0 ? 1 : 0 );
}
exit( $ax_vm_failures > 0 ? 1 : 0 );
