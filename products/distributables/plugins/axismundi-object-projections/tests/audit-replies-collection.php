<?php
/**
 * Public local Object replies collection regression (dev-only).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_rc_results   = array();
$ax_rc_posts     = array();
$ax_rc_users     = array();
$ax_rc_actor_ids = array();
$ax_rc_remote    = array();
$ax_rc_edges     = array();
$ax_rc_hidden    = '';

/** @param bool[] $results Results. */
function ax_rc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Create one local Person Actor fixture user. */
function ax_rc_user( array &$users, array &$actors ) : WP_User {
	$login = 'ax_rc_' . strtolower( wp_generate_password( 8, false, false ) );
	$id    = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$users[] = $id;
	$actor = axismundi_actors_ensure_for_user( $id );
	if ( $actor instanceof Axismundi_Actor ) {
		$actors[] = $actor->get_identity_id();
		axismundi_actors_register_handle( $actor->get_identity_id(), $login );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	}
	return get_userdata( $id );
}

/** Create and publish one Note fixture. */
function ax_rc_note( array &$posts, int $author_id, string $content, string $parent = '', string $visibility = 'public' ) : array {
	$id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $author_id, 'post_content' => $content ) );
	$posts[] = $id;
	axismundi_note_save( $id, array( 'in_reply_to_uri' => $parent, 'visibility' => $visibility ) );
	wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	$envelope = axismundi_note_get( $id );
	return array( 'post_id' => $id, 'uri' => is_array( $envelope ) ? axismundi_note_object_uri( (string) $envelope['local_uuid'] ) : '' );
}

/** Fixture-only interaction filter. */
function ax_rc_hide_one_reply( bool $include, string $child_uri ) : bool {
	return $include && $child_uri !== (string) ( $GLOBALS['ax_rc_hidden'] ?? '' );
}

try {
	axismundi_op_install();
	$author = ax_rc_user( $ax_rc_users, $ax_rc_actor_ids );
	$other  = ax_rc_user( $ax_rc_users, $ax_rc_actor_ids );
	$root   = ax_rc_note( $ax_rc_posts, (int) $author->ID, 'Replies collection root.' );
	$local  = ax_rc_note( $ax_rc_posts, (int) $other->ID, 'Visible local reply.', $root['uri'] );
	$private = ax_rc_note( $ax_rc_posts, (int) $other->ID, 'Private local reply.', $root['uri'], 'followers' );
	array_push( $ax_rc_edges, $local['uri'], $private['uri'] );

	$remote_actor = 'https://remote.example/actors/' . strtolower( wp_generate_password( 7, false, false ) );
	$remote_uri   = 'https://remote.example/objects/' . strtolower( wp_generate_password( 7, false, false ) );
	$hidden_uri   = 'https://remote.example/objects/' . strtolower( wp_generate_password( 7, false, false ) );
	$remote_parent = 'https://remote.example/objects/' . strtolower( wp_generate_password( 7, false, false ) );
	$ax_rc_remote = array( $remote_uri, $hidden_uri, $remote_parent );
	axismundi_op_remote_object_store( array( 'id' => $remote_uri, 'type' => 'Note', 'attributedTo' => $remote_actor, 'inReplyTo' => $root['uri'], 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ), 'content' => 'Visible remote reply.' ) );
	axismundi_op_remote_object_store( array( 'id' => $hidden_uri, 'type' => 'Note', 'attributedTo' => $remote_actor, 'inReplyTo' => $root['uri'], 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ), 'content' => 'Hidden interaction.' ) );
	axismundi_op_remote_object_store( array( 'id' => $remote_parent, 'type' => 'Note', 'attributedTo' => $remote_actor, 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ), 'content' => 'Remote parent.' ) );
	array_push( $ax_rc_edges, $remote_uri, $hidden_uri );

	$root_source = axismundi_op_authoritative_source_from_object_uri( $root['uri'] );
	$root_object = null !== $root_source ? axismundi_op_transform_object( $root_source ) : null;
	$replies_url = axismundi_op_object_replies_url( $root['uri'] );
	ax_rc_assert( $ax_rc_results, 'an active local Note advertises a stable replies collection while a remote cache source cannot own one', is_array( $root_object ) && $replies_url === ( $root_object['replies'] ?? '' ) && null === axismundi_op_authoritative_source_from_object_uri( $remote_parent ) );

	$root_collection = axismundi_op_transform_collection( new Axismundi_OP_Object_Replies( $root['uri'], $root_source ) );
	ax_rc_assert( $ax_rc_results, 'the root is an OrderedCollection with a first page and no partial totalItems claim', is_array( $root_collection ) && 'OrderedCollection' === ( $root_collection['type'] ?? '' ) && axismundi_op_object_replies_url( $root['uri'], 1 ) === ( $root_collection['first'] ?? '' ) && ! array_key_exists( 'totalItems', $root_collection ) );

	$GLOBALS['ax_rc_hidden'] = $hidden_uri;
	add_filter( 'axismundi_op_thread_include_reply', 'ax_rc_hide_one_reply', 1, 2 );
	$page_one = axismundi_op_get_public_reply_collection_page( $root['uri'], 1, 1 );
	$page_two = axismundi_op_get_public_reply_collection_page( $root['uri'], 2, 1 );
	$count    = axismundi_op_get_public_reply_collection_count( $root['uri'] );
	$request = new WP_REST_Request( 'GET', '/axismundi/v1/objects/replies' );
	$request->set_param( 'object', $root['uri'] );
	$request->set_param( 'page', 1 );
	$rest = axismundi_op_get_object_replies( $request );
	$rest_data = $rest instanceof WP_REST_Response ? $rest->get_data() : array();
	remove_filter( 'axismundi_op_thread_include_reply', 'ax_rc_hide_one_reply', 1 );
	unset( $GLOBALS['ax_rc_hidden'] );
	ax_rc_assert( $ax_rc_results, 'pages contain canonical IDs only, skip private and filtered interaction rows, and advance after accepted members', 1 === count( $page_one['uris'] ) && 1 === count( $page_two['uris'] ) && ! in_array( $private['uri'], array_merge( $page_one['uris'], $page_two['uris'] ), true ) && ! in_array( $hidden_uri, array_merge( $page_one['uris'], $page_two['uris'] ), true ) && in_array( $local['uri'], array_merge( $page_one['uris'], $page_two['uris'] ), true ) && in_array( $remote_uri, array_merge( $page_one['uris'], $page_two['uris'] ), true ) && 'OrderedCollectionPage' === ( $rest_data['type'] ?? '' ) && $replies_url === ( $rest_data['partOf'] ?? '' ) );
	ax_rc_assert( $ax_rc_results, 'the bounded display count applies the same public visibility and reply-inclusion gates as collection pages', 2 === (int) $count['count'] && empty( $count['truncated'] ) );

	$private_root = ax_rc_note( $ax_rc_posts, (int) $author->ID, 'Private parent.', '', 'followers' );
	$not_found = new WP_REST_Request( 'GET', '/axismundi/v1/objects/replies' );
	$not_found->set_param( 'object', $private_root['uri'] );
	$private_response = axismundi_op_get_object_replies( $not_found );
	$remote_request = new WP_REST_Request( 'GET', '/axismundi/v1/objects/replies' );
	$remote_request->set_param( 'object', $remote_parent );
	$remote_response = axismundi_op_get_object_replies( $remote_request );
	ax_rc_assert( $ax_rc_results, 'private local and cached remote parents fail closed instead of exposing a replies collection', is_wp_error( $private_response ) && 404 === (int) ( $private_response->get_error_data()['status'] ?? 0 ) && is_wp_error( $remote_response ) && 404 === (int) ( $remote_response->get_error_data()['status'] ?? 0 ) );
} finally {
	remove_filter( 'axismundi_op_thread_include_reply', 'ax_rc_hide_one_reply', 1 );
	foreach ( array_unique( $ax_rc_remote ) as $uri ) {
		axismundi_op_remote_object_delete( $uri );
	}
	foreach ( array_unique( $ax_rc_edges ) as $uri ) {
		$wpdb->delete( axismundi_op_thread_edges_table(), array( 'child_uri_hash' => hash( 'sha256', $uri ), 'child_uri' => $uri ), array( '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_unique( $ax_rc_posts ) as $post_id ) {
		$envelope = axismundi_note_get( (int) $post_id );
		if ( is_array( $envelope ) ) {
			$wpdb->delete( axismundi_op_thread_edges_table(), array( 'child_uri_hash' => hash( 'sha256', axismundi_note_object_uri( (string) $envelope['local_uuid'] ) ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( get_post( (int) $post_id ) instanceof WP_Post ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_unique( $ax_rc_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_unique( $ax_rc_users ) as $user_id ) {
		wp_delete_user( (int) $user_id );
	}
}

$ax_rc_failures = count( array_filter( $ax_rc_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_rc_results ), $ax_rc_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_rc_failures > 0 ? 1 : 0 );
}
exit( $ax_rc_failures > 0 ? 1 : 0 );
