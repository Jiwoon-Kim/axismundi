<?php
/**
 * Actor Outbox representation ownership regression (dev-only).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_op_outbox_results  = array();
$ax_op_outbox_user     = 0;
$ax_op_outbox_identity = 0;
$ax_op_outbox_activity = '';

/** @param bool[] $results Results. */
function ax_op_outbox_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$login              = 'ax_op_outbox_' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_op_outbox_user  = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$actor              = axismundi_actors_ensure_for_user( $ax_op_outbox_user );
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_op_outbox_identity = $actor->get_identity_id();
		axismundi_actors_register_handle( $ax_op_outbox_identity, $login );
		axismundi_actors_set_status( $ax_op_outbox_identity, 'public' );
		$actor = axismundi_actors_get_for_user( $ax_op_outbox_user );
	}

	$ax_op_outbox_activity = home_url( '/activities/' . wp_generate_uuid4() . '/' );
	$recorded              = axismundi_act_record_activity(
		array(
			'id'     => $ax_op_outbox_activity,
			'type'   => 'Create',
			'actor'  => $actor->get_uri(),
			'object' => 'https://example.com/objects/outbox-projection',
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'bcc'    => array( 'https://example.com/private-recipient' ),
		),
		'outbound'
	);
	$source     = new Axismundi_OP_Actor_Outbox( $actor );
	$collection = axismundi_op_transform_collection( $source );
	ax_op_outbox_assert( $ax_op_outbox_results, 'Object Projections resolves the Actor Outbox collection transformer', $recorded instanceof Axismundi_Activity && is_array( $collection ) && 'OrderedCollection' === $collection['type'] );
	ax_op_outbox_assert( $ax_op_outbox_results, 'the collection URI uses the representation-owned neutral namespace', is_array( $collection ) && axismundi_op_actor_outbox_url( $actor ) === $collection['id'] && false !== strpos( $collection['id'], '/axismundi/v1/' ) );
	ax_op_outbox_assert( $ax_op_outbox_results, 'the collection is attributed to its Actor and strips blind recipients', is_array( $collection ) && $actor->get_uri() === $collection['attributedTo'] && 1 === count( $collection['orderedItems'] ) && ! isset( $collection['orderedItems'][0]['bcc'] ) );

	$request = new WP_REST_Request( 'GET', '/axismundi/v1/actors/' . $actor->get_uuid() . '/outbox' );
	$request->set_param( 'uuid', $actor->get_uuid() );
	$response = axismundi_op_get_actor_outbox( $request );
	$data     = $response instanceof WP_REST_Response ? $response->get_data() : array();
	ax_op_outbox_assert( $ax_op_outbox_results, 'the public REST callback serves the same ActivityStreams collection', $response instanceof WP_REST_Response && $collection['id'] === $data['id'] && 'OrderedCollection' === $data['type'] );

	axismundi_actors_set_status( $actor->get_identity_id(), 'internal' );
	$hidden = axismundi_op_get_actor_outbox( $request );
	ax_op_outbox_assert( $ax_op_outbox_results, 'an internal Actor Outbox fails closed with 404', is_wp_error( $hidden ) && 404 === (int) $hidden->get_error_data()['status'] );
} finally {
	if ( '' !== $ax_op_outbox_activity ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $ax_op_outbox_activity ) ); // phpcs:ignore WordPress.DB
	}
	if ( $ax_op_outbox_identity > 0 ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => $ax_op_outbox_identity ) ); // phpcs:ignore WordPress.DB
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_op_outbox_identity ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_op_outbox_identity ) ); // phpcs:ignore WordPress.DB
	}
	if ( $ax_op_outbox_user > 0 && get_userdata( $ax_op_outbox_user ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $ax_op_outbox_user );
	}
}

$failures = count( array_filter( $ax_op_outbox_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_op_outbox_results ), $failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failures > 0 ? 1 : 0 );
}
exit( $failures > 0 ? 1 : 0 );
