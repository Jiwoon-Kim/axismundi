<?php
/**
 * Reaction aggregate for the front end (dev-only; dist-excluded).
 *
 * The federated collection and this aggregate answer different questions about the same
 * ledger rows, so the thing worth pinning is that they never contradict it or each other:
 * a plain `Like` is counted once as a like and never as a chip, a chip counts people
 * rather than Activities, and one reader's `mine` never becomes another's.
 *
 * The other half is presentation. A custom reaction is somebody else's image, and the
 * Emoji plugin's review gate decides whether we may show it. A chip is not a surface that
 * gets to skip that gate, so the checks here flip a real registry row and watch the image
 * disappear.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_rs_results = array();
$ax_rs_actors  = array();
$ax_rs_posts   = array();
$ax_rs_users   = array();
$ax_rs_ids     = array();

/** @param bool[] $results Results. */
function ax_rs_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Remove one fixture identity and everything hanging off it. */
function ax_rs_forget_identity( int $identity_id ) : void {
	global $wpdb;
	foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) {
		$wpdb->delete( $table, array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/** Cache one remote Person so the ledger will accept Activities from it. */
function ax_rs_remote_actor( array &$actors ) : string {
	$uri = 'https://example.com/users/rs-' . strtolower( wp_generate_password( 10, false, false ) );
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

try {
	$ax_rs_picker_script = (string) file_get_contents( dirname( __DIR__ ) . '/assets/reactions.js' );
	ax_rs_assert( $ax_rs_results, 'a server summary without an image descriptor preserves an optimistic custom chip URL for the same reaction key', str_contains( $ax_rs_picker_script, 'chip.imageUrl || prior?.imageUrl' ) );
	$ax_rs_bar_template = (string) file_get_contents( dirname( __DIR__ ) . '/includes/reaction-blocks.php' );
	ax_rs_assert( $ax_rs_results, 'a dynamically inserted custom chip starts with its shortcode fallback hidden, so binding cannot flash it before the image state applies', str_contains( $ax_rs_bar_template, 'class="axismundi-reaction-bar__shortcode" hidden data-wp-class--is-glyph' ) );

	// -- Fixture: one public Object owned by a local Actor -------------------------------

	$ax_rs_login = 'ax_rs_' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_rs_user  = (int) wp_insert_user( array( 'user_login' => $ax_rs_login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_rs_users[] = $ax_rs_user;
	$ax_rs_local = axismundi_actors_ensure_for_user( $ax_rs_user );
	if ( $ax_rs_local instanceof Axismundi_Actor ) {
		$ax_rs_ids[] = $ax_rs_local->get_identity_id();
		axismundi_actors_register_handle( $ax_rs_local->get_identity_id(), $ax_rs_login );
		axismundi_actors_set_status( $ax_rs_local->get_identity_id(), 'public' );
		$ax_rs_local = axismundi_actors_get_by_identity( $ax_rs_local->get_identity_id() );
	}
	$ax_rs_post = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $ax_rs_user, 'post_title' => 'Reaction summary fixture', 'post_content' => 'Body.' ) );
	$ax_rs_posts[] = $ax_rs_post;
	$ax_rs_object  = axismundi_op_transform_object( get_post( $ax_rs_post ) );
	ax_rs_assert( $ax_rs_results, 'the fixture post projects to a public Object', is_array( $ax_rs_object ) );
	$ax_rs_uri    = (string) $ax_rs_object['id'];
	$ax_rs_author = (string) $ax_rs_object['attributedTo'];

	$ax_rs_react = static function ( string $actor, string $content, ?array $tag = null ) use ( $ax_rs_uri, $ax_rs_author ) {
		$payload = array(
			'id'      => 'https://example.com/a/' . wp_generate_uuid4(),
			'type'    => 'EmojiReact',
			'actor'   => $actor,
			'object'  => $ax_rs_uri,
			'content' => $content,
			'to'      => array( $actor . '/followers', $ax_rs_author ),
		);
		if ( null !== $tag ) {
			$payload['tag'] = array( $tag );
		}
		return axismundi_act_record_activity( $payload, 'inbound' );
	};

	// -- Chips count people, and the two collections partition the ledger ----------------

	$ax_rs_alice = ax_rs_remote_actor( $ax_rs_actors );
	$ax_rs_bob   = ax_rs_remote_actor( $ax_rs_actors );
	$ax_rs_react( $ax_rs_alice, '❤' );
	$ax_rs_react( $ax_rs_alice, '❤' ); // Same person, same reaction, twice.
	$ax_rs_react( $ax_rs_bob, '❤' );
	$ax_rs_react( $ax_rs_bob, '👍' );
	axismundi_act_record_activity(
		array( 'id' => 'https://example.com/a/' . wp_generate_uuid4(), 'type' => 'Like', 'actor' => $ax_rs_alice, 'object' => $ax_rs_uri, 'to' => array( $ax_rs_author ) ),
		'inbound'
	);

	$ax_rs_summary = axismundi_act_object_reaction_summary( $ax_rs_uri );
	$ax_rs_by_key  = array_column( $ax_rs_summary['chips'], null, 'key' );

	ax_rs_assert( $ax_rs_results, 'one chip per distinct reaction, not one per Activity', 2 === count( $ax_rs_summary['chips'] ) );
	ax_rs_assert( $ax_rs_results, 'a chip counts people, so the same Actor reacting twice counts once', 2 === (int) ( $ax_rs_by_key['unicode:U+2764']['count'] ?? 0 ) );
	ax_rs_assert( $ax_rs_results, 'and an Actor on two chips counts on each', 1 === (int) ( $ax_rs_by_key['unicode:U+1F44D']['count'] ?? 0 ) );
	ax_rs_assert( $ax_rs_results, 'the plain Like is counted as a like', 1 === (int) $ax_rs_summary['like_count'] );
	ax_rs_assert( $ax_rs_results, 'and never also as a chip, so the two numbers partition the ledger rather than double-count', ! isset( $ax_rs_by_key['unicode:'] ) && 2 === count( $ax_rs_summary['chips'] ) );
	ax_rs_assert( $ax_rs_results, 'chips arrive most-reacted first, so the UI need not sort', 'unicode:U+2764' === (string) $ax_rs_summary['chips'][0]['key'] );

	// -- `mine` belongs to the reader and to nobody else ---------------------------------

	ax_rs_assert( $ax_rs_results, 'a reader with no Actor has no reactions of their own', array() === $ax_rs_summary['mine'] );
	ax_rs_assert(
		$ax_rs_results,
		'and no chip claims to be theirs',
		0 === count( array_filter( $ax_rs_summary['chips'], static fn( array $chip ) : bool => (bool) $chip['mine'] ) )
	);

	if ( $ax_rs_local instanceof Axismundi_Actor ) {
		axismundi_act_record_activity(
			array(
				'id'      => home_url( '/activities/' . wp_generate_uuid4() ),
				'type'    => 'EmojiReact',
				'actor'   => $ax_rs_local->get_uri(),
				'object'  => $ax_rs_uri,
				'content' => '👍',
				'to'      => array( $ax_rs_author ),
			),
			'local'
		);
		$ax_rs_mine = axismundi_act_object_reaction_summary( $ax_rs_uri, $ax_rs_local );
		ax_rs_assert( $ax_rs_results, 'a reader who reacted sees their own key reported', in_array( 'unicode:U+1F44D', $ax_rs_mine['mine'], true ) );
		ax_rs_assert(
			$ax_rs_results,
			'the per-chip flag agrees with that list, because both are read from it',
			1 === count( array_filter( $ax_rs_mine['chips'], static fn( array $chip ) : bool => $chip['mine'] && 'unicode:U+1F44D' === $chip['key'] ) )
		);
		ax_rs_assert(
			$ax_rs_results,
			'and another reader does not inherit it',
			array() === axismundi_act_object_reaction_summary( $ax_rs_uri )['mine']
		);
	}

	// -- Presentation: the Emoji review gate applies to chips too ------------------------

	ax_rs_assert( $ax_rs_results, 'a Unicode chip is text and carries no image', null === $ax_rs_by_key['unicode:U+2764']['image'] );
	ax_rs_assert( $ax_rs_results, 'and is labelled with the emoji its sender wrote', '❤' === (string) $ax_rs_by_key['unicode:U+2764']['label'] );

	$ax_rs_carol = ax_rs_remote_actor( $ax_rs_actors );
	$ax_rs_react(
		$ax_rs_carol,
		':unreviewed:',
		array( 'type' => 'Emoji', 'name' => ':unreviewed:', 'id' => 'https://example.com/emojis/unreviewed', 'icon' => array( 'type' => 'Image', 'url' => 'https://example.com/e.png' ) )
	);
	$ax_rs_unreviewed = array_column( axismundi_act_object_reaction_summary( $ax_rs_uri )['chips'], null, 'key' );
	ax_rs_assert( $ax_rs_results, 'a custom reaction this site never reviewed still gets a chip', isset( $ax_rs_unreviewed['custom:example.com:unreviewed'] ) );
	ax_rs_assert( $ax_rs_results, 'but no image, so an unreviewed remote asset is never shown', null === $ax_rs_unreviewed['custom:example.com:unreviewed']['image'] );
	ax_rs_assert( $ax_rs_results, 'and it falls back to the shortcode its sender wrote', ':unreviewed:' === (string) $ax_rs_unreviewed['custom:example.com:unreviewed']['label'] );

	// The bundled emoji is a real approved registry row, so it exercises the other branch.
	$ax_rs_authority = axismundi_emoji_local_authority();
	$ax_rs_bundled   = axismundi_emoji_get( $ax_rs_authority, 'axismundi' );
	if ( is_array( $ax_rs_bundled ) ) {
		$ax_rs_key   = 'custom:' . $ax_rs_authority . ':axismundi';
		$ax_rs_shown = axismundi_act_reaction_presentation( $ax_rs_key, ':axismundi:' );
		ax_rs_assert( $ax_rs_results, 'an approved emoji with cached bytes resolves to an image', is_array( $ax_rs_shown['image'] ) && '' !== (string) $ax_rs_shown['image']['url'] );
		ax_rs_assert(
			$ax_rs_results,
			'and its authority parses even though it carries a port, which splitting left to right would break',
			str_contains( $ax_rs_authority, ':' ) ? is_array( $ax_rs_shown['image'] ) : true
		);

		$ax_rs_was = (string) $ax_rs_bundled['review_status'];
		$wpdb->update( axismundi_emoji_table(), array( 'review_status' => 'pending' ), array( 'emoji_authority' => $ax_rs_authority, 'shortcode_key' => 'axismundi' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ax_rs_pending = axismundi_act_reaction_presentation( $ax_rs_key, ':axismundi:' );
		$wpdb->update( axismundi_emoji_table(), array( 'review_status' => $ax_rs_was ), array( 'emoji_authority' => $ax_rs_authority, 'shortcode_key' => 'axismundi' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		ax_rs_assert( $ax_rs_results, 'withdrawing approval withdraws the image from the chip as well', null === $ax_rs_pending['image'] );
		ax_rs_assert( $ax_rs_results, 'and restoring it brings the same image back', ( axismundi_act_reaction_presentation( $ax_rs_key, ':axismundi:' )['image']['url'] ?? '' ) === (string) $ax_rs_shown['image']['url'] );
	}

	ax_rs_assert( $ax_rs_results, 'a malformed custom key is still custom, not mistaken for a Unicode emoji', 'custom' === axismundi_act_reaction_presentation( 'custom:nohost', ':x:' )['kind'] );

	// -- The read endpoint is gated on the Object, not on the reader ---------------------

	$ax_rs_private = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'private', 'post_author' => $ax_rs_user, 'post_title' => 'Private fixture', 'post_content' => 'Body.' ) );
	$ax_rs_posts[] = $ax_rs_private;
	$ax_rs_request = new WP_REST_Request( 'GET', '/axismundi/v1/reactions' );
	$ax_rs_request->set_param( 'object_uri', add_query_arg( 'p', $ax_rs_private, home_url( '/' ) ) );
	ax_rs_assert( $ax_rs_results, 'reactions on an Object that does not project publicly cannot be read', is_wp_error( axismundi_act_rest_object_reactions( $ax_rs_request ) ) );

	$ax_rs_request = new WP_REST_Request( 'GET', '/axismundi/v1/reactions' );
	$ax_rs_request->set_param( 'object_uri', $ax_rs_uri );
	$ax_rs_response = axismundi_act_rest_object_reactions( $ax_rs_request );
	ax_rs_assert( $ax_rs_results, 'a public Object answers', $ax_rs_response instanceof WP_REST_Response && 200 === $ax_rs_response->get_status() );
	ax_rs_assert(
		$ax_rs_results,
		'and forbids shared caching, since every response carries one reader\'s own reactions',
		$ax_rs_response instanceof WP_REST_Response && str_contains( (string) $ax_rs_response->get_headers()['Cache-Control'], 'no-store' )
	);
} finally {
	foreach ( array_unique( $ax_rs_actors ) as $ax_rs_actor_uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri_hash' => hash( 'sha256', (string) $ax_rs_actor_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ax_rs_cached = axismundi_actors_get_by_uri( (string) $ax_rs_actor_uri );
		if ( $ax_rs_cached instanceof Axismundi_Actor ) {
			ax_rs_forget_identity( (int) $ax_rs_cached->get_identity_id() );
		}
	}
	foreach ( array_unique( $ax_rs_ids ) as $ax_rs_identity ) {
		$ax_rs_gone = axismundi_actors_get_by_identity( (int) $ax_rs_identity );
		if ( $ax_rs_gone instanceof Axismundi_Actor ) {
			$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri_hash' => hash( 'sha256', $ax_rs_gone->get_uri() ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		ax_rs_forget_identity( (int) $ax_rs_identity );
	}
	foreach ( array_unique( $ax_rs_posts ) as $ax_rs_post_id ) {
		wp_delete_post( (int) $ax_rs_post_id, true );
	}
	foreach ( array_unique( $ax_rs_users ) as $ax_rs_user_id ) {
		wp_delete_user( (int) $ax_rs_user_id );
	}
}

$ax_rs_failures = count( array_filter( $ax_rs_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_rs_results ), $ax_rs_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_rs_failures > 0 ? 1 : 0 );
}
exit( $ax_rs_failures > 0 ? 1 : 0 );
