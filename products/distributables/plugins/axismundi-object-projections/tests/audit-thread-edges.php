<?php
/**
 * URI-keyed thread graph, unified local/remote reply resolution, and the
 * reply-context/replies blocks regression (dev-only).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_te_results   = array();
$ax_te_post_ids  = array();
$ax_te_user_ids  = array();
$ax_te_actor_ids = array();
$ax_te_remote_uris = array();
$ax_te_edge_uris = array();

/** @param bool[] $results Results. */
function ax_te_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Create one local Actor for one fixture user. */
function ax_te_author( array &$user_ids, array &$actor_ids, string $status = 'public' ) : ?WP_User {
	$login = 'ax_te_' . strtolower( wp_generate_password( 8, false, false ) );
	$uid   = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	if ( $uid <= 0 ) {
		return null;
	}
	$user_ids[] = $uid;
	$actor = axismundi_actors_ensure_for_user( $uid );
	if ( $actor instanceof Axismundi_Actor ) {
		$actor_ids[] = $actor->get_identity_id();
		axismundi_actors_register_handle( $actor->get_identity_id(), $login );
		axismundi_actors_set_status( $actor->get_identity_id(), $status );
	}
	return get_userdata( $uid ) ?: null;
}

/** Create, optionally reply, and publish one fixture Note. */
function ax_te_note( array &$post_ids, int $author_id, string $content, string $in_reply_to = '', string $visibility = 'public' ) : array {
	$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $author_id, 'post_content' => $content ) );
	$post_ids[] = $post_id;
	axismundi_note_save( $post_id, array( 'in_reply_to_uri' => $in_reply_to, 'visibility' => $visibility ) );
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$envelope = axismundi_note_get( $post_id );
	return array( 'post_id' => $post_id, 'uri' => is_array( $envelope ) ? axismundi_note_object_uri( (string) $envelope['local_uuid'] ) : '' );
}

try {
	$installed = axismundi_op_install();
	$table     = axismundi_op_thread_edges_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixture schema probe.
	$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixture schema probe.
	$unique = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'child_uri_hash'", ARRAY_A );
	ax_te_assert( $ax_te_results, 'schema installs the thread-edge index with a unique child identity', $installed && in_array( 'parent_uri', $columns, true ) && in_array( 'resolution_state', $columns, true ) && ! empty( $unique ) && 0 === (int) $unique[0]['Non_unique'] );

	$author = ax_te_author( $ax_te_user_ids, $ax_te_actor_ids );
	$other  = ax_te_author( $ax_te_user_ids, $ax_te_actor_ids );

	// A local root, a local public reply to it, and a local followers-only reply to
	// the same root -- the private reply must never surface through thread reads.
	$root  = ax_te_note( $ax_te_post_ids, (int) $author->ID, 'Root note.' );
	$reply = ax_te_note( $ax_te_post_ids, (int) $other->ID, 'Public reply.', $root['uri'], 'public' );
	$private_reply = ax_te_note( $ax_te_post_ids, (int) $other->ID, 'Followers-only reply.', $root['uri'], 'followers' );
	array_push( $ax_te_edge_uris, $root['uri'], $reply['uri'], $private_reply['uri'] );

	$edge = axismundi_op_get_thread_edge( $reply['uri'] );
	ax_te_assert( $ax_te_results, 'publishing a local reply records a resolved direct-parent edge with root and depth', is_array( $edge ) && $root['uri'] === $edge['parent_uri'] && 'resolved' === $edge['resolution_state'] && $root['uri'] === $edge['root_uri'] && 1 === (int) $edge['depth'] );

	$reply_models = axismundi_op_get_reply_view_models( $root['uri'] );
	$reply_uris   = array_column( $reply_models, 'object_uri' );
	ax_te_assert( $ax_te_results, 'reply lookup returns the public local reply and never the followers-only one', in_array( $reply['uri'], $reply_uris, true ) && ! in_array( $private_reply['uri'], $reply_uris, true ) && 2 === count( axismundi_op_get_thread_reply_uris( $root['uri'] ) ) );

	$parent_model = axismundi_op_get_parent_view_model( $reply['uri'] );
	ax_te_assert( $ax_te_results, 'parent lookup resolves the local root through the same unified accessor', is_array( $parent_model ) && $root['uri'] === ( $parent_model['object_uri'] ?? '' ) && 'active' === ( $parent_model['status'] ?? '' ) );

	// A reply to an as-yet-unknown remote URI stays unresolved without being dropped,
	// then flips to resolved the moment that remote Note is cached.
	$unknown_parent = 'https://remote.example/notes/' . strtolower( wp_generate_password( 8, false, false ) );
	$waiting_reply  = ax_te_note( $ax_te_post_ids, (int) $author->ID, 'Reply to an unseen remote parent.', $unknown_parent );
	$ax_te_edge_uris[] = $waiting_reply['uri'];
	$waiting_edge   = axismundi_op_get_thread_edge( $waiting_reply['uri'] );
	ax_te_assert( $ax_te_results, 'a reply to an unknown parent is preserved as an unresolved edge, not dropped', is_array( $waiting_edge ) && $unknown_parent === $waiting_edge['parent_uri'] && 'unresolved' === $waiting_edge['resolution_state'] && null === $waiting_edge['root_uri'] );
	ax_te_assert( $ax_te_results, 'an unresolved parent has no view model yet', null === axismundi_op_get_parent_view_model( $waiting_reply['uri'] ) );

	$remote_actor_uri = 'https://remote.example/actors/' . strtolower( wp_generate_password( 6, false, false ) );
	$stored_parent = axismundi_op_remote_object_store( array( 'id' => $unknown_parent, 'type' => 'Note', 'attributedTo' => $remote_actor_uri, 'content' => 'Now cached remote parent.' ) );
	$ax_te_remote_uris[] = $unknown_parent;
	$reconciled_edge = axismundi_op_get_thread_edge( $waiting_reply['uri'] );
	ax_te_assert( $ax_te_results, 'caching the remote parent reconciles the waiting edge to resolved with root and depth', is_array( $stored_parent ) && is_array( $reconciled_edge ) && 'resolved' === $reconciled_edge['resolution_state'] && $unknown_parent === $reconciled_edge['root_uri'] && 1 === (int) $reconciled_edge['depth'] );

	$reconciled_parent_model = axismundi_op_get_parent_view_model( $waiting_reply['uri'] );
	ax_te_assert( $ax_te_results, 'the reconciled remote parent resolves through the wrapped remote-cache view model', is_array( $reconciled_parent_model ) && $unknown_parent === ( $reconciled_parent_model['object_uri'] ?? '' ) && 'Note' === ( $reconciled_parent_model['type'] ?? '' ) );

	// A remote child replying to our local root: the unified reply lookup must
	// surface it exactly like a local reply, and a tombstoned remote child must
	// still appear (as a deleted placeholder), never silently vanish.
	$remote_reply_uri = 'https://remote.example/notes/' . strtolower( wp_generate_password( 8, false, false ) );
	$stored_remote_reply = axismundi_op_remote_object_store( array( 'id' => $remote_reply_uri, 'type' => 'Note', 'attributedTo' => $remote_actor_uri, 'inReplyTo' => $root['uri'], 'content' => 'Remote reply to our root.' ) );
	$ax_te_remote_uris[] = $remote_reply_uri;
	$ax_te_edge_uris[]   = $remote_reply_uri;
	$mixed_reply_uris = axismundi_op_get_thread_reply_uris( $root['uri'] );
	ax_te_assert( $ax_te_results, 'a remote reply to a local root is indexed by the same generic ledger-agnostic write path', is_array( $stored_remote_reply ) && in_array( $remote_reply_uri, $mixed_reply_uris, true ) );

	$remote_reply_models = axismundi_op_get_reply_view_models( $root['uri'] );
	$remote_reply_model  = null;
	foreach ( $remote_reply_models as $candidate ) {
		if ( $remote_reply_uri === ( $candidate['object_uri'] ?? '' ) ) {
			$remote_reply_model = $candidate;
		}
	}
	ax_te_assert( $ax_te_results, 'the unified reply view models mix local and remote replies to the same parent', is_array( $remote_reply_model ) && 'Note' === ( $remote_reply_model['type'] ?? '' ) );

	$tombstoned_remote_reply = axismundi_op_remote_object_store( array( 'id' => $remote_reply_uri, 'type' => 'Tombstone', 'formerType' => 'Note' ) );
	$tombstone_models = axismundi_op_get_reply_view_models( $root['uri'] );
	$tombstone_model  = null;
	foreach ( $tombstone_models as $candidate ) {
		if ( $remote_reply_uri === ( $candidate['id'] ?? '' ) ) {
			$tombstone_model = $candidate;
		}
	}
	ax_te_assert( $ax_te_results, 'a tombstoned remote reply keeps its edge and still resolves, as a Tombstone view model', is_array( $tombstoned_remote_reply ) && is_array( $tombstone_model ) && 'tombstone' === ( $tombstone_model['status'] ?? '' ) );

	// A local Note that stops being a reply (edited to clear in_reply_to, then
	// republished) drops its edge -- the envelope write alone does not, since the
	// index only reads the object actually committed to the ledger.
	axismundi_note_save( $reply['post_id'], array( 'in_reply_to_uri' => '' ) );
	wp_update_post( array( 'ID' => $reply['post_id'], 'post_content' => 'Public reply, no longer a reply.' ) );
	ax_te_assert( $ax_te_results, 'clearing an authored in_reply_to and republishing removes the standing edge rather than leaving a stale parent', null === axismundi_op_get_thread_edge( $reply['uri'] ) );

	// The reply-context and replies blocks read the current view model, not $_GET.
	$root_source = new Axismundi_Note_Source( axismundi_note_get( $root['post_id'] ), get_post( $root['post_id'] ) );
	$root_model  = axismundi_op_object_view_model( $root_source );
	axismundi_op_set_current_object_view_model( $root_model );
	$replies_html = axismundi_op_render_replies_block();
	axismundi_op_set_current_object_view_model( null );
	ax_te_assert( $ax_te_results, 'the replies block renders the remaining public reply for the bound current view model', false !== strpos( $replies_html, 'axismundi-thread--replies' ) && false === strpos( $replies_html, $private_reply['uri'] ) );

	$waiting_source = new Axismundi_Note_Source( axismundi_note_get( $waiting_reply['post_id'] ), get_post( $waiting_reply['post_id'] ) );
	$waiting_model  = axismundi_op_object_view_model( $waiting_source );
	axismundi_op_set_current_object_view_model( $waiting_model );
	$context_html = axismundi_op_render_reply_context_block();
	axismundi_op_set_current_object_view_model( null );
	ax_te_assert( $ax_te_results, 'the reply-context block renders the resolved remote parent\'s context line', false !== strpos( $context_html, 'axismundi-thread__context' ) && false !== strpos( $context_html, esc_url( $unknown_parent ) ) );

	$root_no_parent = axismundi_op_get_parent_view_model( $root['uri'] );
	ax_te_assert( $ax_te_results, 'a root object has no parent view model', null === $root_no_parent );
} finally {
	foreach ( array_unique( $ax_te_remote_uris ) as $uri ) {
		axismundi_op_remote_object_delete( $uri );
	}
	foreach ( array_unique( $ax_te_edge_uris ) as $uri ) {
		$wpdb->delete( axismundi_op_thread_edges_table(), array( 'child_uri_hash' => hash( 'sha256', $uri ), 'child_uri' => $uri ), array( '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_unique( $ax_te_post_ids ) as $post_id ) {
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( get_post( (int) $post_id ) instanceof WP_Post ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_unique( $ax_te_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( ! empty( $ax_te_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_te_user_ids ) as $uid ) {
			if ( get_userdata( (int) $uid ) ) {
				wp_delete_user( (int) $uid );
			}
		}
	}
}

$ax_te_failures = count( array_filter( $ax_te_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_te_results ), $ax_te_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_te_failures > 0 ? 1 : 0 );
}
exit( $ax_te_failures > 0 ? 1 : 0 );
