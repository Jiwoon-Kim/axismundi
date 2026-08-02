<?php
/**
 * Sending and withdrawing reactions (dev-only; dist-excluded).
 *
 * Two things here are easy to get wrong in ways that only show up later.
 *
 * The first is that reacting with a custom emoji *declares* it: the Activity carries the
 * `tag[]` that tells a receiver what `:foo:` is and where its picture lives. A cached copy
 * of somebody else's emoji is theirs, so re-declaring it under our Activity would hand out
 * their asset as if it were ours to publish. The gate that governs publishing an emoji in
 * a Note has to be the same gate here, and these checks prove it is the same gate rather
 * than a second one that happens to agree today.
 *
 * The second is the plural. FEP-c0e0 permits several reactions per Actor and Akkoma sends
 * them, so an Undo that named only the object would be ambiguous about which one it
 * retracts. Every withdrawal here is keyed on the reaction.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_rm_results = array();
$ax_rm_posts   = array();
$ax_rm_users   = array();
$ax_rm_ids     = array();
$ax_rm_remote  = array();

/** @param bool[] $results Results. */
function ax_rm_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Remove one fixture identity and everything hanging off it. */
function ax_rm_forget_identity( int $identity_id ) : void {
	global $wpdb;
	foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) {
		$wpdb->delete( $table, array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

try {
	$ax_rm_login = 'ax_rm_' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_rm_user  = (int) wp_insert_user( array( 'user_login' => $ax_rm_login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_rm_users[] = $ax_rm_user;
	$ax_rm_actor   = axismundi_actors_ensure_for_user( $ax_rm_user );
	ax_rm_assert( $ax_rm_results, 'the fixture user has a local Actor', $ax_rm_actor instanceof Axismundi_Actor );
	$ax_rm_ids[] = $ax_rm_actor->get_identity_id();
	axismundi_actors_register_handle( $ax_rm_actor->get_identity_id(), $ax_rm_login );
	axismundi_actors_set_status( $ax_rm_actor->get_identity_id(), 'public' );
	$ax_rm_actor = axismundi_actors_get_by_identity( $ax_rm_actor->get_identity_id() );

	$ax_rm_post = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $ax_rm_user, 'post_title' => 'Reaction mutation fixture', 'post_content' => 'Body.' ) );
	$ax_rm_posts[] = $ax_rm_post;
	$ax_rm_object  = axismundi_op_transform_object( get_post( $ax_rm_post ) );
	$ax_rm_uri     = (string) $ax_rm_object['id'];
	$ax_rm_owner   = (string) $ax_rm_object['attributedTo'];

	// -- Sending --------------------------------------------------------------------------

	$ax_rm_heart = axismundi_act_react_to_object( $ax_rm_actor, $ax_rm_uri, '❤', $ax_rm_owner );
	ax_rm_assert( $ax_rm_results, 'a reaction is recorded as an EmojiReact', $ax_rm_heart instanceof Axismundi_Activity && 'EmojiReact' === $ax_rm_heart->get_type() );
	ax_rm_assert(
		$ax_rm_results,
		'reacting twice with the same emoji returns the reaction already standing, so a double click is one reaction',
		( axismundi_act_react_to_object( $ax_rm_actor, $ax_rm_uri, '❤', $ax_rm_owner )->get_uri() ?? '' ) === $ax_rm_heart->get_uri()
	);
	$ax_rm_thumb = axismundi_act_react_to_object( $ax_rm_actor, $ax_rm_uri, '👍', $ax_rm_owner );
	ax_rm_assert( $ax_rm_results, 'and a second, different emoji is a second reaction rather than a replacement', $ax_rm_thumb instanceof Axismundi_Activity && $ax_rm_thumb->get_uri() !== $ax_rm_heart->get_uri() );
	ax_rm_assert( $ax_rm_results, 'prose is refused', is_wp_error( axismundi_act_react_to_object( $ax_rm_actor, $ax_rm_uri, 'nice post', $ax_rm_owner ) ) );

	// -- Custom emoji: the outbound gate is the gate ---------------------------------------

	$ax_rm_custom = axismundi_act_react_to_object( $ax_rm_actor, $ax_rm_uri, ':axismundi:', $ax_rm_owner );
	ax_rm_assert( $ax_rm_results, 'a local emoji this site may publish can be reacted with', $ax_rm_custom instanceof Axismundi_Activity );
	if ( $ax_rm_custom instanceof Axismundi_Activity ) {
		$ax_rm_payload = $ax_rm_custom->get_payload();
		$ax_rm_tag     = is_array( $ax_rm_payload['tag'] ?? null ) ? ( $ax_rm_payload['tag'][0] ?? array() ) : array();
		ax_rm_assert( $ax_rm_results, 'and the declaration travels with it, since a shortcode alone names nothing', ':axismundi:' === (string) ( $ax_rm_tag['name'] ?? '' ) && '' !== (string) ( $ax_rm_tag['icon']['url'] ?? '' ) );
		ax_rm_assert(
			$ax_rm_results,
			'the reaction keys on the authority the registry filed the emoji under',
			'custom:' . axismundi_emoji_local_authority() . ':axismundi' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT reaction_key FROM ' . axismundi_act_activities_table() . ' WHERE activity_uri_hash = %s', hash( 'sha256', $ax_rm_custom->get_uri() ) ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		);
	}

	/*
	 * The check this file exists for. `:blobcat_hip:` is a real cached misskey.io emoji:
	 * renderable here, and not ours to hand out. If this ever passes, a reaction has become
	 * a way to republish somebody else's asset.
	 */
	$ax_rm_theirs = axismundi_emoji_get( 'misskey.io', 'blobcat_hip' );
	if ( is_array( $ax_rm_theirs ) ) {
		ax_rm_assert( $ax_rm_results, 'the cached remote emoji used for this check is renderable here', axismundi_emoji_is_renderable( $ax_rm_theirs ) );
		ax_rm_assert(
			$ax_rm_results,
			'yet reacting with it is refused, because reacting with an emoji publishes its declaration',
			is_wp_error( axismundi_act_react_to_object( $ax_rm_actor, $ax_rm_uri, ':blobcat_hip:', $ax_rm_owner ) )
		);
	}
	ax_rm_assert( $ax_rm_results, 'a shortcode naming nothing at all is refused', is_wp_error( axismundi_act_react_to_object( $ax_rm_actor, $ax_rm_uri, ':nosuchemoji:', $ax_rm_owner ) ) );

	// -- The aggregate agrees, and never leaks into the like count -------------------------

	$ax_rm_summary = axismundi_act_object_reaction_summary( $ax_rm_uri, $ax_rm_actor );
	ax_rm_assert( $ax_rm_results, 'all three reactions show as chips', 3 === count( $ax_rm_summary['chips'] ) );
	ax_rm_assert( $ax_rm_results, 'all three are reported as the reader\'s own', 3 === count( $ax_rm_summary['mine'] ) );
	ax_rm_assert( $ax_rm_results, 'and none of them is counted as a like', 0 === (int) $ax_rm_summary['like_count'] );

	// -- Withdrawing is keyed on the reaction, not the object ------------------------------

	$ax_rm_undo = axismundi_act_unreact_to_object( $ax_rm_actor, $ax_rm_uri, 'unicode:U+2764' );
	ax_rm_assert( $ax_rm_results, 'withdrawing one reaction records an Undo', $ax_rm_undo instanceof Axismundi_Activity && 'Undo' === $ax_rm_undo->get_type() );
	ax_rm_assert(
		$ax_rm_results,
		'withdrawing again returns the Undo that already did it rather than failing',
		( axismundi_act_unreact_to_object( $ax_rm_actor, $ax_rm_uri, 'unicode:U+2764' )->get_uri() ?? '' ) === $ax_rm_undo->get_uri()
	);
	$ax_rm_after = axismundi_act_object_reaction_summary( $ax_rm_uri, $ax_rm_actor );
	ax_rm_assert( $ax_rm_results, 'only the withdrawn reaction leaves', 2 === count( $ax_rm_after['chips'] ) );
	ax_rm_assert( $ax_rm_results, 'and the Actor\'s other reactions are untouched', ! in_array( 'unicode:U+2764', $ax_rm_after['mine'], true ) && in_array( 'unicode:U+1F44D', $ax_rm_after['mine'], true ) );
	ax_rm_assert( $ax_rm_results, 'withdrawing one that was never sent is an error, not a silent success', is_wp_error( axismundi_act_unreact_to_object( $ax_rm_actor, $ax_rm_uri, 'unicode:U+1F600' ) ) );

	// -- A reaction to a cached remote object keeps that object alive ----------------------

	/*
	 * Without this the object could be collected while a chip still points at it. Leases are
	 * per Activity URI, so an Actor holding several reactions holds several leases and
	 * withdrawing one releases only that one.
	 */
	$ax_rm_remote_uri = 'https://remote.example/objects/' . strtolower( wp_generate_password( 10, false, false ) );
	$ax_rm_remote[]   = $ax_rm_remote_uri;
	$ax_rm_remote_actor = 'https://remote.example/users/' . strtolower( wp_generate_password( 8, false, false ) );
	axismundi_op_store_remote_object( array( 'id' => $ax_rm_remote_uri, 'type' => 'Note', 'attributedTo' => $ax_rm_remote_actor, 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ), 'content' => 'Remote fixture.' ) );
	$ax_rm_before_leases = axismundi_op_active_lease_count( $ax_rm_remote_uri );
	$ax_rm_remote_react  = axismundi_act_react_to_object( $ax_rm_actor, $ax_rm_remote_uri, '❤' );
	if ( $ax_rm_remote_react instanceof Axismundi_Activity ) {
		ax_rm_assert( $ax_rm_results, 'reacting to a cached remote object takes an interaction lease on it', axismundi_op_active_lease_count( $ax_rm_remote_uri ) > $ax_rm_before_leases );
		axismundi_act_unreact_to_object( $ax_rm_actor, $ax_rm_remote_uri, 'unicode:U+2764' );
		ax_rm_assert( $ax_rm_results, 'and withdrawing it releases that lease again', axismundi_op_active_lease_count( $ax_rm_remote_uri ) === $ax_rm_before_leases );
	} else {
		ax_rm_assert( $ax_rm_results, 'a cached remote object can be reacted to', false );
	}

	// -- The REST surface ------------------------------------------------------------------

	wp_set_current_user( $ax_rm_user );
	$ax_rm_request = new WP_REST_Request( 'POST', '/axismundi/v1/reactions' );
	$ax_rm_request->set_param( 'object_uri', $ax_rm_uri );
	$ax_rm_request->set_param( 'content', '🎉' );
	$ax_rm_response = axismundi_act_rest_react( $ax_rm_request );
	ax_rm_assert( $ax_rm_results, 'the endpoint answers with the whole aggregate, so the client never has to derive it', $ax_rm_response instanceof WP_REST_Response && isset( $ax_rm_response->get_data()['chips'], $ax_rm_response->get_data()['mine'], $ax_rm_response->get_data()['like_count'] ) );
	ax_rm_assert( $ax_rm_results, 'and the new reaction is already in it', $ax_rm_response instanceof WP_REST_Response && in_array( 'unicode:U+1F389', (array) $ax_rm_response->get_data()['mine'], true ) );

	$ax_rm_request = new WP_REST_Request( 'DELETE', '/axismundi/v1/reactions' );
	$ax_rm_request->set_param( 'object_uri', $ax_rm_uri );
	$ax_rm_request->set_param( 'reaction_key', 'unicode:U+1F389' );
	$ax_rm_response = axismundi_act_rest_unreact( $ax_rm_request );
	ax_rm_assert( $ax_rm_results, 'withdrawing through the endpoint answers the same way', $ax_rm_response instanceof WP_REST_Response && ! in_array( 'unicode:U+1F389', (array) $ax_rm_response->get_data()['mine'], true ) );

	wp_set_current_user( 0 );
	ax_rm_assert( $ax_rm_results, 'a visitor with no Actor may not react at all', ! axismundi_act_like_rest_permission() );
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_rm_ids ) as $ax_rm_identity ) {
		$ax_rm_gone = axismundi_actors_get_by_identity( (int) $ax_rm_identity );
		if ( $ax_rm_gone instanceof Axismundi_Actor ) {
			$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri_hash' => hash( 'sha256', $ax_rm_gone->get_uri() ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		ax_rm_forget_identity( (int) $ax_rm_identity );
	}
	foreach ( array_unique( $ax_rm_remote ) as $ax_rm_remote_object ) {
		$wpdb->delete( axismundi_op_object_leases_table(), array( 'object_uri_hash' => hash( 'sha256', (string) $ax_rm_remote_object ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_op_remote_objects_table(), array( 'object_uri_hash' => hash( 'sha256', (string) $ax_rm_remote_object ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_unique( $ax_rm_posts ) as $ax_rm_post_id ) {
		wp_delete_post( (int) $ax_rm_post_id, true );
	}
	foreach ( array_unique( $ax_rm_users ) as $ax_rm_user_id ) {
		wp_delete_user( (int) $ax_rm_user_id );
	}
}

$ax_rm_failures = count( array_filter( $ax_rm_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_rm_results ), $ax_rm_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_rm_failures > 0 ? 1 : 0 );
}
exit( $ax_rm_failures > 0 ? 1 : 0 );
