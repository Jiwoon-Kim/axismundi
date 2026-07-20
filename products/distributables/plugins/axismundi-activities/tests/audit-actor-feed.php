<?php
/**
 * Actor Activity feed projection regression (dev-only).
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_feed_results    = array();
$ax_feed_user_id    = 0;
$ax_feed_identity   = 0;
$ax_feed_activities = array();

/** @param bool[] $results Results. */
function ax_feed_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$login           = 'ax_feed_' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_feed_user_id = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$actor           = axismundi_actors_ensure_for_user( $ax_feed_user_id );
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_feed_identity = $actor->get_identity_id();
		axismundi_actors_register_handle( $ax_feed_identity, $login );
		axismundi_actors_set_status( $ax_feed_identity, 'public' );
		$actor = axismundi_actors_get_for_user( $ax_feed_user_id );
	}

	ax_feed_assert( $ax_feed_results, 'fixture creates a local public Actor', $actor instanceof Axismundi_Actor && $actor->is_local() );
	if ( ! $actor instanceof Axismundi_Actor ) {
		throw new RuntimeException( 'Fixture Actor was not created.' );
	}

	$public_activity_uri = home_url( '/activities/' . wp_generate_uuid4() . '/' );
	$private_activity_uri = home_url( '/activities/' . wp_generate_uuid4() . '/' );
	$ax_feed_activities   = array( $public_activity_uri, $private_activity_uri );
	$public_uri           = 'https://www.w3.org/ns/activitystreams#Public';
	axismundi_act_record_activity(
		array(
			'id'     => $public_activity_uri,
			'type'   => 'Create',
			'actor'  => $actor->get_uri(),
			'object' => array(
				'id'      => home_url( '/notes/' . wp_generate_uuid4() . '/' ),
				'type'    => 'Note',
				'name'    => 'Public activity title',
				'content' => '<p>Public activity body.</p>',
			),
			'to'     => array( $public_uri ),
			'bto'    => array( 'https://example.com/hidden' ),
		),
		'outbound'
	);
	axismundi_act_record_activity(
		array(
			'id'     => $private_activity_uri,
			'type'   => 'Create',
			'actor'  => $actor->get_uri(),
			'object' => array( 'id' => home_url( '/notes/' . wp_generate_uuid4() . '/' ), 'type' => 'Note', 'content' => '<p>Private activity body.</p>' ),
			'to'     => array( $actor->get_uri() . '/followers' ),
		),
		'outbound'
	);

	$items = axismundi_act_actor_feed_items( $actor, 20 );
	$ids   = array_column( $items, 'id' );
	ax_feed_assert( $ax_feed_results, 'the actor feed includes only the public outbound ledger entry', 1 === count( $items ) && in_array( $public_activity_uri, $ids, true ) && ! in_array( $private_activity_uri, $ids, true ) );
	ax_feed_assert( $ax_feed_results, 'the public item preserves its object title and sanitized content', 'Public activity title' === ( $items[0]['title'] ?? '' ) && false !== strpos( (string) ( $items[0]['content_html'] ?? '' ), 'Public activity body.' ) );
	ax_feed_assert( $ax_feed_results, 'the human feed projection never copies blind-recipient fields', ! isset( $items[0]['bto'], $items[0]['bcc'] ) );

	axismundi_actors_set_status( $ax_feed_identity, 'internal' );
	ax_feed_assert( $ax_feed_results, 'a non-public Actor exposes no public Activity feed', array() === axismundi_act_actor_feed_items( $actor, 20 ) );
} finally {
	foreach ( $ax_feed_activities as $activity_uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $activity_uri ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture cleanup.
	}
	if ( $ax_feed_identity > 0 ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $ax_feed_identity ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture cleanup.
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_feed_identity ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_feed_identity ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Fixture cleanup.
	}
	if ( $ax_feed_user_id > 0 && get_userdata( $ax_feed_user_id ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $ax_feed_user_id );
	}
}

$ax_feed_failures = count( array_filter( $ax_feed_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_feed_results ), $ax_feed_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_feed_failures > 0 ? 1 : 0 );
}
exit( $ax_feed_failures > 0 ? 1 : 0 );
