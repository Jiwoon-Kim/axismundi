<?php
/**
 * FEP-c0e0 `emojiReactions` collection projection (dev-only; dist-excluded).
 *
 * Three things here are easy to get wrong and impossible to see from the outside once
 * shipped, so each is pinned:
 *
 * 1. The property name is an IRI, and `http://fedibird.com/ns#emojiReactions` is a
 *    different property from the `https` spelling. Getting it wrong publishes a document
 *    that validates, renders, and is invisible to every peer looking for the term.
 * 2. The collection carries whole Activities, which means it forwards whatever addressing
 *    they arrived with. `bto`/`bcc` name recipients the other recipients are not supposed
 *    to know about.
 * 3. `totalItems` must count the ledger, not the page. The bound is 100 by default, so a
 *    mistake here is correct on every object anyone tests by hand.
 *
 * What is deliberately *not* asserted is an `as:Public` gate. FEP-c0e0's own examples
 * address a reaction to the sender's followers and the object's author and nothing else,
 * so requiring public addressing would empty this collection for spec-conformant senders.
 *
 * No network: reacting Actors are `example.com` fixtures, because `wp_http_validate_url()`
 * refuses a `.test` host and the Actor would be rejected before any Activity is recorded.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_rx_results    = array();
$ax_rx_posts      = array();
$ax_rx_note_posts = array();
$ax_rx_note_uris  = array();
$ax_rx_actors     = array();
$ax_rx_users      = array();
$ax_rx_identities = array();

/** @param bool[] $results Results. */
function ax_rx_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Cache one remote Person so the ledger will accept Activities from it. */
function ax_rx_actor( array &$actors ) : string {
	$uri = 'https://example.com/users/rx-' . strtolower( wp_generate_password( 10, false, false ) );
	axismundi_actors_upsert_remote(
		array(
			'uri'                => $uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'u' . substr( hash( 'sha256', $uri ), 0, 8 ),
			'display_name'       => 'Reaction fixture',
			'profile_url'        => $uri,
			'endpoints'          => array( 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
			'payload'            => array( 'id' => $uri, 'type' => 'Person', 'inbox' => $uri . '/inbox', 'outbox' => $uri . '/outbox' ),
		)
	);
	$actors[] = $uri;
	return $uri;
}

/**
 * Remove one fixture identity and everything hanging off it.
 *
 * Only identities this file created: a blanket delete by scope would take real
 * user-scoped Actors with it.
 */
function ax_rx_forget_identity( int $identity_id ) : void {
	global $wpdb;
	foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) {
		$wpdb->delete( $table, array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/** One local Person Actor to own the fixture post, since an unowned post does not project. */
function ax_rx_local_author( array &$users, array &$identities ) : int {
	$login = 'ax_rx_' . strtolower( wp_generate_password( 8, false, false ) );
	$id    = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$users[] = $id;
	$actor = axismundi_actors_ensure_for_user( $id );
	if ( $actor instanceof Axismundi_Actor ) {
		$identities[] = $actor->get_identity_id();
		axismundi_actors_register_handle( $actor->get_identity_id(), $login );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	}
	return $id;
}

/** Create and publish one Note through its own envelope lifecycle. */
function ax_rx_note( array &$posts, array &$uris, int $author_id ) : string {
	$post_id = (int) wp_insert_post(
		array(
			'post_type'    => AXISMUNDI_NOTE_POST_TYPE,
			'post_status'  => 'draft',
			'post_author'  => $author_id,
			'post_title'   => 'Emoji reaction collection Note fixture',
			'post_content' => 'Fixture Note body.',
		)
	);
	$posts[] = $post_id;
	axismundi_note_save( $post_id, array( 'visibility' => 'public' ) );
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$envelope = axismundi_note_get( $post_id );
	$uri      = is_array( $envelope ) ? axismundi_note_object_uri( (string) $envelope['local_uuid'] ) : '';
	$uris[]   = $uri;
	return $uri;
}

try {
	axismundi_op_install();

	$ax_rx_post = (int) wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_author'  => ax_rx_local_author( $ax_rx_users, $ax_rx_identities ),
			'post_title'   => 'Emoji reaction collection fixture',
			'post_content' => 'Fixture body.',
		)
	);
	$ax_rx_posts[] = $ax_rx_post;
	$ax_rx_object  = axismundi_op_transform_object( get_post( $ax_rx_post ) );
	ax_rx_assert( $ax_rx_results, 'the fixture post projects to a public Object', is_array( $ax_rx_object ) && ! empty( $ax_rx_object['id'] ) );

	$ax_rx_uri = (string) $ax_rx_object['id'];
	$ax_rx_note_uri = ax_rx_note( $ax_rx_note_posts, $ax_rx_note_uris, (int) get_post_field( 'post_author', $ax_rx_post ) );
	$ax_rx_note_source = axismundi_op_authoritative_source_from_object_uri( $ax_rx_note_uri );
	$ax_rx_note_object = null !== $ax_rx_note_source ? axismundi_op_transform_object( $ax_rx_note_source ) : null;
	ax_rx_assert( $ax_rx_results, 'a public Note resolves through the authoritative source path and advertises all three interaction collections', is_array( $ax_rx_note_object ) && isset( $ax_rx_note_object['likes'], $ax_rx_note_object['shares'], $ax_rx_note_object['http://fedibird.com/ns#emojiReactions'] ) );

	// -- The property is an IRI, and only one spelling is the right one -----------------

	ax_rx_assert( $ax_rx_results, 'the Object advertises the collection under the Fedibird IRI the FEP names', isset( $ax_rx_object['http://fedibird.com/ns#emojiReactions'] ) );
	ax_rx_assert( $ax_rx_results, 'and not under the https spelling, which is a different property nobody reads', ! isset( $ax_rx_object['https://fedibird.com/ns#emojiReactions'] ) );
	ax_rx_assert( $ax_rx_results, 'the advertised URL is this site\'s collection endpoint', str_contains( (string) $ax_rx_object['http://fedibird.com/ns#emojiReactions'], 'objects/emoji-reactions' ) );
	ax_rx_assert( $ax_rx_results, 'and it stays separate from the plain likes collection', (string) $ax_rx_object['http://fedibird.com/ns#emojiReactions'] !== (string) ( $ax_rx_object['likes'] ?? '' ) );

	// -- Contents: whole Activities, addressed the way the FEP addresses them ------------

	$ax_rx_alice = ax_rx_actor( $ax_rx_actors );
	$ax_rx_bob   = ax_rx_actor( $ax_rx_actors );

	/*
	 * Addressed exactly as FEP-c0e0's examples are: followers plus the object's author, no
	 * `as:Public`. If a gate is ever added upstream, this fixture is what disappears.
	 */
	$ax_rx_react = static function ( string $actor, string $object, string $content, string $author ) : array {
		return array(
			'id'      => 'https://example.com/a/' . wp_generate_uuid4(),
			'type'    => 'EmojiReact',
			'actor'   => $actor,
			'object'  => $object,
			'content' => $content,
			'to'      => array( $actor . '/followers', $author ),
			'bto'     => array( 'https://example.com/users/blind-witness' ),
			'bcc'     => array( 'https://example.com/users/blind-witness-2' ),
		);
	};
	$ax_rx_author = (string) $ax_rx_object['attributedTo'];
	foreach ( array( array( $ax_rx_alice, '❤' ), array( $ax_rx_bob, '❤' ), array( $ax_rx_bob, '👍' ) ) as $ax_rx_case ) {
		axismundi_act_record_activity( $ax_rx_react( $ax_rx_case[0], $ax_rx_uri, $ax_rx_case[1], $ax_rx_author ), 'inbound' );
	}
	// A plain Like, which belongs to the other collection and must not appear in this one.
	axismundi_act_record_activity(
		array( 'id' => 'https://example.com/a/' . wp_generate_uuid4(), 'type' => 'Like', 'actor' => $ax_rx_alice, 'object' => $ax_rx_uri, 'to' => array( $ax_rx_author ) ),
		'inbound'
	);
	$ax_rx_note_author = is_array( $ax_rx_note_object ) ? (string) $ax_rx_note_object['attributedTo'] : '';
	axismundi_act_record_activity(
		array( 'id' => 'https://example.com/a/' . wp_generate_uuid4(), 'type' => 'Like', 'actor' => $ax_rx_alice, 'object' => $ax_rx_note_uri, 'to' => array( $ax_rx_note_author ) ),
		'inbound'
	);
	axismundi_act_record_activity(
		$ax_rx_react( $ax_rx_bob, $ax_rx_note_uri, '👍', $ax_rx_note_author ),
		'inbound'
	);
	$ax_rx_note_likes_request = new WP_REST_Request( 'GET', '/axismundi/v1/objects/likes' );
	$ax_rx_note_likes_request->set_param( 'object', $ax_rx_note_uri );
	$ax_rx_note_reactions_request = new WP_REST_Request( 'GET', '/axismundi/v1/objects/emoji-reactions' );
	$ax_rx_note_reactions_request->set_param( 'object', $ax_rx_note_uri );
	$ax_rx_note_shares_request = new WP_REST_Request( 'GET', '/axismundi/v1/objects/shares' );
	$ax_rx_note_shares_request->set_param( 'object', $ax_rx_note_uri );
	$ax_rx_note_likes_response = axismundi_op_get_object_likes( $ax_rx_note_likes_request );
	$ax_rx_note_reactions_response = axismundi_op_get_object_emoji_reactions( $ax_rx_note_reactions_request );
	$ax_rx_note_shares_response = axismundi_op_get_object_shares( $ax_rx_note_shares_request );
	ax_rx_assert( $ax_rx_results, 'a Note\'s advertised likes, shares, and emojiReactions URLs all resolve instead of returning a Post-only 404', $ax_rx_note_likes_response instanceof WP_REST_Response && $ax_rx_note_reactions_response instanceof WP_REST_Response && $ax_rx_note_shares_response instanceof WP_REST_Response && 200 === $ax_rx_note_likes_response->get_status() && 200 === $ax_rx_note_reactions_response->get_status() && 200 === $ax_rx_note_shares_response->get_status() );
	$ax_rx_note_likes_data = $ax_rx_note_likes_response instanceof WP_REST_Response ? $ax_rx_note_likes_response->get_data() : array();
	$ax_rx_note_reactions_data = $ax_rx_note_reactions_response instanceof WP_REST_Response ? $ax_rx_note_reactions_response->get_data() : array();
	$ax_rx_note_shares_data = $ax_rx_note_shares_response instanceof WP_REST_Response ? $ax_rx_note_shares_response->get_data() : array();
	ax_rx_assert( $ax_rx_results, 'each Note collection reports the ledger count for its own interaction kind', 1 === (int) ( $ax_rx_note_likes_data['totalItems'] ?? -1 ) && 1 === (int) ( $ax_rx_note_reactions_data['totalItems'] ?? -1 ) && 0 === (int) ( $ax_rx_note_shares_data['totalItems'] ?? -1 ) );

	$ax_rx_source = new Axismundi_OP_Object_Emoji_Reactions( $ax_rx_uri, get_post( $ax_rx_post ) );
	$ax_rx_coll   = axismundi_op_object_emoji_reactions_transform( $ax_rx_source );
	$ax_rx_items  = (array) ( $ax_rx_coll['orderedItems'] ?? array() );

	ax_rx_assert( $ax_rx_results, 'a reaction addressed to followers and the author appears, because that is how the FEP addresses one', 3 === count( $ax_rx_items ) );
	ax_rx_assert(
		$ax_rx_results,
		'the items are the original Activities, not aggregate counts',
		3 === count( array_filter( $ax_rx_items, static fn( $item ) : bool => is_array( $item ) && isset( $item['content'], $item['actor'] ) && in_array( (string) ( $item['type'] ?? '' ), array( 'EmojiReact', 'Like' ), true ) ) )
	);
	ax_rx_assert(
		$ax_rx_results,
		'the plain Like stays out of the reaction collection',
		0 === count( array_filter( $ax_rx_items, static fn( $item ) : bool => ! isset( $item['content'] ) ) )
	);
	ax_rx_assert(
		$ax_rx_results,
		'and it is still counted as a like, so neither collection borrows from the other',
		1 === axismundi_act_get_like_count( $ax_rx_uri )
	);

	// -- Blind copies are not forwarded --------------------------------------------------

	ax_rx_assert(
		$ax_rx_results,
		'no published item discloses a bto recipient',
		0 === count( array_filter( $ax_rx_items, static fn( $item ) : bool => isset( $item['bto'] ) ) )
	);
	ax_rx_assert(
		$ax_rx_results,
		'nor a bcc recipient',
		0 === count( array_filter( $ax_rx_items, static fn( $item ) : bool => isset( $item['bcc'] ) ) )
	);
	ax_rx_assert(
		$ax_rx_results,
		'while the ordinary to addressing survives, since it was never private',
		3 === count( array_filter( $ax_rx_items, static fn( $item ) : bool => ! empty( $item['to'] ) ) )
	);

	// -- totalItems counts the ledger, not the page --------------------------------------

	ax_rx_assert( $ax_rx_results, 'totalItems matches the ledger when nothing is truncated', 3 === (int) $ax_rx_coll['totalItems'] );
	ax_rx_assert(
		$ax_rx_results,
		'and it is read from a count rather than from the bounded page, so a busy Object is not understated',
		2 === count( axismundi_act_get_effective_reactions( $ax_rx_uri, 2 ) )
			&& 3 === axismundi_act_get_reaction_activity_count( $ax_rx_uri )
	);

	// -- Undo removes an item without disturbing the rest --------------------------------

	$ax_rx_first = axismundi_act_get_effective_reactions( $ax_rx_uri, 100 );
	axismundi_act_record_activity(
		array(
			'id'     => 'https://example.com/a/' . wp_generate_uuid4(),
			'type'   => 'Undo',
			'actor'  => $ax_rx_first[0]->get_actor_uri(),
			'object' => $ax_rx_first[0]->get_uri(),
			'to'     => array( $ax_rx_author ),
		),
		'inbound'
	);
	$ax_rx_after = axismundi_op_object_emoji_reactions_transform( $ax_rx_source );
	ax_rx_assert( $ax_rx_results, 'an undone reaction leaves the collection', 2 === count( (array) $ax_rx_after['orderedItems'] ) );
	ax_rx_assert( $ax_rx_results, 'and totalItems follows it down', 2 === (int) $ax_rx_after['totalItems'] );

	// -- The collection is a projection of a public Object -------------------------------

	ax_rx_assert( $ax_rx_results, 'the collection is attributed to the Object\'s author', (string) $ax_rx_after['attributedTo'] === $ax_rx_author );
	ax_rx_assert( $ax_rx_results, 'and it is visible only while the Object itself projects publicly', axismundi_op_object_emoji_reactions_visible( $ax_rx_source ) );
} finally {
	foreach ( array_filter( array_unique( $ax_rx_note_uris ) ) as $ax_rx_note_uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', (string) $ax_rx_note_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_unique( $ax_rx_actors ) as $ax_rx_actor_uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri_hash' => hash( 'sha256', (string) $ax_rx_actor_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ax_rx_cached = axismundi_actors_get_by_uri( (string) $ax_rx_actor_uri );
		if ( $ax_rx_cached instanceof Axismundi_Actor ) {
			ax_rx_forget_identity( (int) $ax_rx_cached->get_identity_id() );
		}
	}
	foreach ( array_unique( $ax_rx_posts ) as $ax_rx_post_id ) {
		wp_delete_post( (int) $ax_rx_post_id, true );
	}
	foreach ( array_unique( $ax_rx_note_posts ) as $ax_rx_note_post_id ) {
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $ax_rx_note_post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_delete_post( (int) $ax_rx_note_post_id, true );
	}
	foreach ( array_unique( $ax_rx_identities ) as $ax_rx_identity ) {
		ax_rx_forget_identity( (int) $ax_rx_identity );
	}
	foreach ( array_unique( $ax_rx_users ) as $ax_rx_user_id ) {
		wp_delete_user( (int) $ax_rx_user_id );
	}
}

$ax_rx_failures = count( array_filter( $ax_rx_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_rx_results ), $ax_rx_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_rx_failures > 0 ? 1 : 0 );
}
exit( $ax_rx_failures > 0 ? 1 : 0 );
