<?php
/** Actor mention autocomplete regression. Dev-only and dist-excluded. */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/mention-search.php';

global $wpdb;
$ax_mention_results   = array();
$ax_mention_actor_ids = array();
$ax_mention_user      = get_current_user_id();

/** Record one assertion. */
function ax_mention_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
	wp_set_current_user( (int) ( $admins[0] ?? 0 ) );
	$local = axismundi_actors_create_local(
		array(
			'actor_type'         => 'Person',
			'actor_scope'        => 'user',
			'preferred_username' => 'mention_local_' . strtolower( wp_generate_password( 6, false, false ) ),
			'status'             => 'public',
		)
	);
	$remote_uri = 'https://example.com/users/mention_' . strtolower( wp_generate_password( 6, false, false ) );
	$remote     = axismundi_actors_upsert_remote(
		array(
			'uri'                => $remote_uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'mention_remote',
			'display_name'       => 'Mention Remote',
			'profile_url'        => $remote_uri,
			'endpoints'          => array( 'inbox' => $remote_uri . '/inbox', 'outbox' => $remote_uri . '/outbox' ),
			'payload'            => array( 'id' => $remote_uri, 'type' => 'Person', 'preferredUsername' => 'mention_remote', 'name' => 'Mention Remote' ),
		)
	);
	foreach ( array( $local, $remote ) as $actor ) {
		if ( $actor instanceof Axismundi_Actor ) {
			$ax_mention_actor_ids[] = $actor->get_identity_id();
		}
	}
	if ( $remote instanceof Axismundi_Actor ) {
		axismundi_actors_record_verified_acct_address( $remote->get_identity_id(), 'mention_remote@example.com' );
	}

	$local_results  = axismundi_actors_search_mentionable( $local instanceof Axismundi_Actor ? $local->get_preferred_username() : 'missing' );
	$remote_results = axismundi_actors_search_mentionable( '@mention_remote@example.com' );
	ax_mention_assert( $ax_mention_results, 'mention search returns a public local Actor by its immutable handle', $local instanceof Axismundi_Actor && 1 === count( array_filter( $local_results, static fn( Axismundi_Actor $item ) : bool => $item->get_uri() === $local->get_uri() ) ) );
	ax_mention_assert( $ax_mention_results, 'mention search returns an already-cached remote Actor by verified acct address without discovery', $remote instanceof Axismundi_Actor && 1 === count( array_filter( $remote_results, static fn( Axismundi_Actor $item ) : bool => $item->get_uri() === $remote->get_uri() ) ) && '@mention_remote@example.com' === axismundi_actors_mention_handle( $remote ) );
	ax_mention_assert( $ax_mention_results, 'federated Mention names are fully qualified for both local and remote consumers', $local instanceof Axismundi_Actor && str_starts_with( axismundi_actors_federated_mention_name( $local ), '@' . $local->get_preferred_username() . '@' ) && $remote instanceof Axismundi_Actor && '@mention_remote@example.com' === axismundi_actors_federated_mention_name( $remote ) );

	wp_set_current_user( 0 );
	ax_mention_assert( $ax_mention_results, 'anonymous visitors cannot search the private editor endpoint', ! axismundi_actors_can_search_mentions() );
	wp_set_current_user( (int) ( $admins[0] ?? 0 ) );
	$response = axismundi_actors_rest_search_mentions( new WP_REST_Request( 'GET', '/axismundi/v1/actors/mention-search' ) );
	$data     = $response->get_data();
	ax_mention_assert( $ax_mention_results, 'the editor response exposes canonical URI, label, handle, and avatar fields only from repository Actors', is_array( $data ) && isset( $data[0]['uri'], $data[0]['name'], $data[0]['handle'], $data[0]['avatar'] ) );
} finally {
	foreach ( $ax_mention_actor_ids as $identity_id ) {
		$wpdb->delete( axismundi_actors_addresses_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	wp_set_current_user( $ax_mention_user );
}

$ax_mention_failures = count( array_filter( $ax_mention_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_mention_results ), $ax_mention_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_mention_failures > 0 ? 1 : 0 );
}
exit( $ax_mention_failures > 0 ? 1 : 0 );
