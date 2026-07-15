<?php
/**
 * Public Outbox query contract regression (dev-only).
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_outbox_results    = array();
$ax_outbox_user       = 0;
$ax_outbox_identity   = 0;
$ax_outbox_activities = array();

/** @param bool[] $results Results. */
function ax_outbox_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$login          = 'ax_outbox_' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_outbox_user = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$actor          = axismundi_actors_ensure_for_user( $ax_outbox_user );
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_outbox_identity = $actor->get_identity_id();
		axismundi_actors_register_handle( $ax_outbox_identity, $login );
		axismundi_actors_set_status( $ax_outbox_identity, 'public' );
		$actor = axismundi_actors_get_for_user( $ax_outbox_user );
	}

	$public_uri = 'https://www.w3.org/ns/activitystreams#Public';
	$definitions = array(
		array( 'suffix' => 'full', 'direction' => 'outbound', 'audience' => array( 'to' => array( $public_uri ), 'bto' => array( 'https://example.com/hidden' ), 'bcc' => array( 'https://example.com/blind' ) ) ),
		array( 'suffix' => 'compact', 'direction' => 'outbound', 'audience' => array( 'cc' => array( 'as:Public' ) ) ),
		array( 'suffix' => 'private', 'direction' => 'outbound', 'audience' => array( 'to' => array( $actor->get_uri() . '/followers' ) ) ),
		array( 'suffix' => 'local', 'direction' => 'local', 'audience' => array( 'to' => array( $public_uri ) ) ),
	);
	foreach ( $definitions as $definition ) {
		$activity_uri          = home_url( '/activities/' . wp_generate_uuid4() . '/' );
		$ax_outbox_activities[] = $activity_uri;
		$payload               = array_merge(
			array(
				'id'     => $activity_uri,
				'type'   => 'Create',
				'actor'  => $actor->get_uri(),
				'object' => 'https://example.com/objects/' . $definition['suffix'],
			),
			$definition['audience']
		);
		axismundi_act_record_activity( $payload, $definition['direction'] );
	}

	$items = axismundi_act_get_public_outbox( $actor->get_uri() );
	$ids   = array_column( $items, 'id' );
	ax_outbox_assert( $ax_outbox_results, 'full and compact Public audience forms are included', 2 === count( $items ) && in_array( $ax_outbox_activities[0], $ids, true ) && in_array( $ax_outbox_activities[1], $ids, true ) );
	ax_outbox_assert( $ax_outbox_results, 'followers-only and local-only activities are excluded', ! in_array( $ax_outbox_activities[2], $ids, true ) && ! in_array( $ax_outbox_activities[3], $ids, true ) );
	ax_outbox_assert( $ax_outbox_results, 'public payload copies never disclose bto or bcc', ! array_filter( $items, static fn( array $item ) : bool => isset( $item['bto'] ) || isset( $item['bcc'] ) ) );
	$stored = axismundi_act_get( $ax_outbox_activities[0] );
	ax_outbox_assert( $ax_outbox_results, 'the authoritative ledger remains lossless after projection', $stored instanceof Axismundi_Activity && isset( $stored->get_payload()['bto'], $stored->get_payload()['bcc'] ) );
} finally {
	foreach ( $ax_outbox_activities as $activity_uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $activity_uri ) ); // phpcs:ignore WordPress.DB
	}
	if ( $ax_outbox_identity > 0 ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $ax_outbox_identity ) ); // phpcs:ignore WordPress.DB
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_outbox_identity ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_outbox_identity ) ); // phpcs:ignore WordPress.DB
	}
	if ( $ax_outbox_user > 0 && get_userdata( $ax_outbox_user ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $ax_outbox_user );
	}
}

$failures = count( array_filter( $ax_outbox_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_outbox_results ), $failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failures > 0 ? 1 : 0 );
}
exit( $failures > 0 ? 1 : 0 );
