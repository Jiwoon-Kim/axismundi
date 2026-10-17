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
$ax_op_outbox_activities = array();
$ax_op_outbox_private_activity = '';
$ax_op_outbox_opted_in_activity = '';
$ax_op_outbox_protocol_activities = array();
$ax_op_outbox_payload_filter = null;

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
	$visibility = $actor instanceof Axismundi_Actor
		? axismundi_actors_set_follow_collections_visibility( $actor, 'public', $ax_op_outbox_user )
		: new WP_Error( 'ax_op_fixture_actor' );
	$actor = $actor instanceof Axismundi_Actor ? axismundi_actors_get_for_user( $ax_op_outbox_user ) : null;

	$recorded = null;
	for ( $i = 0; $i < 21; ++$i ) {
		$activity_uri = home_url( '/activities/' . wp_generate_uuid4() . '/' );
		$created = axismundi_act_record_activity(
			array(
				'id'     => $activity_uri,
				'type'   => 'Create',
				'actor'  => $actor->get_uri(),
				'object' => 'https://example.com/objects/outbox-projection-' . $i,
				'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
				'bcc'    => array( 'https://example.com/private-recipient' ),
			),
			'outbound'
		);
		if ( $created instanceof Axismundi_Activity ) {
			$ax_op_outbox_activities[] = $activity_uri;
			$recorded = $created;
		}
	}
	foreach ( array( 'Update', 'Delete' ) as $type ) {
		$activity_uri = home_url( '/activities/' . wp_generate_uuid4() . '/' );
		$protocol_activity = axismundi_act_record_activity(
			array(
				'id'     => $activity_uri,
				'type'   => $type,
				'actor'  => $actor->get_uri(),
				'object' => 'https://example.com/objects/outbox-protocol-' . strtolower( $type ),
				'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			),
			'outbound'
		);
		if ( $protocol_activity instanceof Axismundi_Activity ) {
			$ax_op_outbox_protocol_activities[] = $activity_uri;
		}
	}
	$ax_op_outbox_private_activity = home_url( '/activities/' . wp_generate_uuid4() . '/' );
	$private_recorded = axismundi_act_record_activity(
		array(
			'id'     => $ax_op_outbox_private_activity,
			'type'   => 'Announce',
			'actor'  => $actor->get_uri(),
			'object' => 'https://example.com/objects/private-outbox-projection',
			'to'     => array( 'https://example.com/actors/followers' ),
		),
		'outbound'
	);
	$ax_op_outbox_opted_in_activity = home_url( '/activities/' . wp_generate_uuid4() . '/' );
	$opted_in_recorded = axismundi_act_record_activity(
		array(
			'id'     => $ax_op_outbox_opted_in_activity,
			'type'   => 'Announce',
			'actor'  => $actor->get_uri(),
			'object' => 'https://example.com/objects/outbox-opted-in-projection',
			'to'     => array( 'https://example.com/actors/followers' ),
		),
		'outbound'
	);
	$ax_op_outbox_payload_filter = static function ( $payload, Axismundi_Activity $activity ) use ( &$ax_op_outbox_opted_in_activity ) {
		return $ax_op_outbox_opted_in_activity === $activity->get_uri()
			? $activity->get_payload()
			: $payload;
	};
	add_filter( 'axismundi_act_public_outbox_payload', $ax_op_outbox_payload_filter, 10, 2 );
	$source     = new Axismundi_OP_Actor_Outbox( $actor );
	$collection = axismundi_op_transform_collection( $source );
	ax_op_outbox_assert( $ax_op_outbox_results, 'Object Projections resolves the Actor Outbox collection transformer', $recorded instanceof Axismundi_Activity && 21 === count( $ax_op_outbox_activities ) && 2 === count( $ax_op_outbox_protocol_activities ) && is_array( $collection ) && 'OrderedCollection' === $collection['type'] );
	ax_op_outbox_assert( $ax_op_outbox_results, 'the collection URI uses the representation-owned neutral namespace', is_array( $collection ) && axismundi_op_actor_outbox_url( $actor ) === $collection['id'] && false !== strpos( $collection['id'], '/axismundi/v1/' ) );
	ax_op_outbox_assert( $ax_op_outbox_results, 'the Outbox root names its first page without claiming a truncated total or embedding members', $private_recorded instanceof Axismundi_Activity && is_array( $collection ) && $actor->get_uri() === $collection['attributedTo'] && isset( $collection['first'] ) && ! isset( $collection['totalItems'], $collection['orderedItems'] ) );

	/*
	 * Page size is instance policy, so the chain assertions below state the size they were
	 * written for instead of inheriting whatever ships. The fixture holds twenty-four public
	 * members and the assertions expect twenty on the first page and four on the second;
	 * when that was implicit, raising the default to fifty turned four of them red by
	 * putting every member on one page, which is a fact about the default rather than about
	 * the chain.
	 */
	$ax_op_outbox_page_size = static fn() : int => 20;
	add_filter( 'axismundi_op_outbox_page_size', $ax_op_outbox_page_size );
	$first_page = axismundi_op_transform_collection( new Axismundi_OP_Actor_Outbox( $actor, true ) );
	$next_query = array();
	parse_str( (string) wp_parse_url( (string) ( $first_page['next'] ?? '' ), PHP_URL_QUERY ), $next_query );
	$second_page = isset( $next_query['cursor'] )
		? axismundi_op_transform_collection( new Axismundi_OP_Actor_Outbox( $actor, true, (string) $next_query['cursor'] ) )
		: array();
	$first_ids  = is_array( $first_page ) ? array_map( static fn( array $payload ) : string => (string) ( $payload['id'] ?? '' ), (array) ( $first_page['orderedItems'] ?? array() ) ) : array();
	$second_ids = is_array( $second_page ) ? array_map( static fn( array $payload ) : string => (string) ( $payload['id'] ?? '' ), (array) ( $second_page['orderedItems'] ?? array() ) ) : array();
	$all_ids    = array_merge( $first_ids, $second_ids );
	ax_op_outbox_assert( $ax_op_outbox_results, 'the first Outbox page is an OrderedCollectionPage with a root partOf and a continuation', is_array( $first_page ) && 'OrderedCollectionPage' === $first_page['type'] && $collection['id'] === $first_page['partOf'] && 20 === count( $first_ids ) && isset( $first_page['next'] ) );
	ax_op_outbox_assert( $ax_op_outbox_results, 'the continuation page reaches every public Activity exactly once, preserves an Activity-domain opt-in, strips blind recipients, and skips an ordinary private Activity', $opted_in_recorded instanceof Axismundi_Activity && is_array( $second_page ) && 'OrderedCollectionPage' === $second_page['type'] && 24 === count( $all_ids ) && 24 === count( array_unique( $all_ids ) ) && in_array( $ax_op_outbox_opted_in_activity, $all_ids, true ) && ! in_array( $ax_op_outbox_private_activity, $all_ids, true ) && empty( array_filter( array_merge( (array) $first_page['orderedItems'], (array) $second_page['orderedItems'] ), static fn( array $payload ) : bool => isset( $payload['bcc'] ) ) ) && ! isset( $second_page['next'] ) );
	ax_op_outbox_assert( $ax_op_outbox_results, 'the Outbox retains public Update and Delete Activities instead of applying the profile feed type allowlist', 2 === count( $ax_op_outbox_protocol_activities ) && empty( array_diff( $ax_op_outbox_protocol_activities, $all_ids ) ) );

	/*
	 * A continuation that only a lenient client can follow is not a continuation. The ledger
	 * cursor carries a SQL datetime, so it contains a space, and `add_query_arg()` does not
	 * encode what it is handed -- `build_query()` passes `$urlencode = false`. Measured
	 * 2026-10-18: the published `next` held a raw space, a conformant client refused the
	 * whole chain as an invalid URL, and this file stayed green because reading the cursor
	 * back with `parse_str()` tolerates exactly what an HTTP client will not.
	 */
	$ax_op_outbox_next_url = (string) ( $first_page['next'] ?? '' );
	$ax_op_outbox_next_q   = (string) wp_parse_url( $ax_op_outbox_next_url, PHP_URL_QUERY );
	ax_op_outbox_assert(
		$ax_op_outbox_results,
		'the published continuation is a URL an exacting client can request verbatim',
		'' !== $ax_op_outbox_next_url
			&& $ax_op_outbox_next_url === esc_url_raw( $ax_op_outbox_next_url )
			&& 0 === preg_match( '/[\s<>"{}|\^`]/', $ax_op_outbox_next_url )
	);
	ax_op_outbox_assert(
		$ax_op_outbox_results,
		'the encoded continuation still decodes to the ledger cursor it was built from',
		'' !== $ax_op_outbox_next_q
			&& isset( $next_query['cursor'] )
			&& axismundi_op_actor_outbox_page_url( $actor, (string) $next_query['cursor'] ) === $ax_op_outbox_next_url
			&& null !== axismundi_act_parse_feed_cursor( (string) $next_query['cursor'] )
	);

	$request = new WP_REST_Request( 'GET', '/axismundi/v1/actors/' . $actor->get_uuid() . '/outbox' );
	$request->set_param( 'uuid', $actor->get_uuid() );
	$response = axismundi_op_get_actor_outbox( $request );
	$data     = $response instanceof WP_REST_Response ? $response->get_data() : array();
	ax_op_outbox_assert( $ax_op_outbox_results, 'the public REST callback serves the same ActivityStreams collection root', $response instanceof WP_REST_Response && $collection['id'] === $data['id'] && 'OrderedCollection' === $data['type'] && $collection['first'] === $data['first'] );
	$page_request = new WP_REST_Request( 'GET', '/axismundi/v1/actors/' . $actor->get_uuid() . '/outbox' );
	$page_request->set_param( 'uuid', $actor->get_uuid() );
	$page_request->set_param( 'page', 1 );
	$page_response = axismundi_op_get_actor_outbox( $page_request );
	$page_data     = $page_response instanceof WP_REST_Response ? $page_response->get_data() : array();
	$page_headers  = $page_response instanceof WP_REST_Response ? $page_response->get_headers() : array();
	ax_op_outbox_assert( $ax_op_outbox_results, 'the public REST callback distinguishes the first Outbox page from its collection root and mirrors next as an HTTP Link header', $page_response instanceof WP_REST_Response && is_array( $first_page ) && $first_page['id'] === $page_data['id'] && 'OrderedCollectionPage' === $page_data['type'] && $collection['id'] === $page_data['partOf'] && isset( $page_data['next'], $page_headers['Link'] ) && '<' . $page_data['next'] . '>; rel="next"' === $page_headers['Link'] );
	remove_filter( 'axismundi_op_outbox_page_size', $ax_op_outbox_page_size );
	$invalid_request = new WP_REST_Request( 'GET', '/axismundi/v1/actors/' . $actor->get_uuid() . '/outbox' );
	$invalid_request->set_param( 'uuid', $actor->get_uuid() );
	$invalid_request->set_param( 'page', 1 );
	$invalid_request->set_param( 'cursor', 'not-a-ledger-cursor' );
	$invalid_response = axismundi_op_get_actor_outbox( $invalid_request );
	ax_op_outbox_assert( $ax_op_outbox_results, 'an invalid Outbox cursor is refused instead of silently restarting at the newest page', is_wp_error( $invalid_response ) && 400 === (int) $invalid_response->get_error_data()['status'] );

	$followers_source     = new Axismundi_OP_Actor_Followers( $actor );
	$followers_collection = axismundi_op_transform_collection( $followers_source );
	$followers_request    = new WP_REST_Request( 'GET', '/axismundi/v1/actors/' . $actor->get_uuid() . '/followers' );
	$followers_request->set_param( 'collection', 'followers' );
	$followers_response = rest_do_request( $followers_request );
	$followers_data     = $followers_response instanceof WP_REST_Response ? $followers_response->get_data() : array();
	ax_op_outbox_assert( $ax_op_outbox_results, 'the Actors repository accepts the explicit public disclosure policy', true === $visibility && 'public' === $actor->get_follow_collections_visibility() );
	ax_op_outbox_assert( $ax_op_outbox_results, 'the Followers URI serves an OrderedCollection root with a stable count', is_array( $followers_collection ) && $followers_response instanceof WP_REST_Response && 'OrderedCollection' === $followers_data['type'] && 0 === $followers_data['totalItems'] && ! isset( $followers_data['orderedItems'] ) );
	$actor_document = axismundi_op_actor_transform( $actor );
	ax_op_outbox_assert( $ax_op_outbox_results, 'the Actor document advertises stable Followers and Following URIs', is_array( $followers_collection ) && isset( $actor_document['followers'], $actor_document['following'], $followers_data['id'] ) && axismundi_op_actor_followers_url( $actor ) === $actor_document['followers'] && axismundi_op_actor_follow_collection_url( $actor, 'following' ) === $actor_document['following'] && $followers_collection['id'] === $followers_data['id'] );

	axismundi_actors_set_follow_collections_visibility( $actor, 'private', $ax_op_outbox_user );
	$hidden_followers = rest_do_request( $followers_request );
	$hidden_data = $hidden_followers instanceof WP_REST_Response ? $hidden_followers->get_data() : array();
	ax_op_outbox_assert( $ax_op_outbox_results, 'a non-public policy keeps the advertised root alive but withholds its first page', $hidden_followers instanceof WP_REST_Response && 200 === $hidden_followers->get_status() && 'OrderedCollection' === $hidden_data['type'] && ! isset( $hidden_data['first'] ) );

	axismundi_actors_set_status( $actor->get_identity_id(), 'internal' );
	$hidden = axismundi_op_get_actor_outbox( $request );
	ax_op_outbox_assert( $ax_op_outbox_results, 'an internal Actor Outbox fails closed with 404', is_wp_error( $hidden ) && 404 === (int) $hidden->get_error_data()['status'] );
} finally {
	if ( null !== $ax_op_outbox_payload_filter ) {
		remove_filter( 'axismundi_act_public_outbox_payload', $ax_op_outbox_payload_filter, 10 );
	}
	foreach ( $ax_op_outbox_activities as $activity_uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $activity_uri ) ); // phpcs:ignore WordPress.DB
	}
	foreach ( $ax_op_outbox_protocol_activities as $activity_uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $activity_uri ) ); // phpcs:ignore WordPress.DB
	}
	if ( '' !== $ax_op_outbox_private_activity ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $ax_op_outbox_private_activity ) ); // phpcs:ignore WordPress.DB
	}
	if ( '' !== $ax_op_outbox_opted_in_activity ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $ax_op_outbox_opted_in_activity ) ); // phpcs:ignore WordPress.DB
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
