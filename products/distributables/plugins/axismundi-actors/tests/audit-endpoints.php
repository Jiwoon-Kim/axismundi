<?php
/**
 * Actor endpoint ledger and DB v6 migration regression. Dev-only/dist-excluded.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';

global $wpdb;
$ax_endpoint_results = array();
$ax_endpoint_ids     = array();

/** @param array $results Accumulator. @param string $label Contract. @param bool $condition Holds. */
function ax_endpoint_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/**
 * @param string               $slug      Fixture slug.
 * @param array<string,string> $endpoints Endpoint map.
 * @return Axismundi_Actor|WP_Error
 */
function ax_endpoint_actor( string $slug, array $endpoints ) {
	$uri = 'https://example.com/users/' . $slug;
	return axismundi_actors_upsert_remote(
		array(
			'uri'                => $uri,
			'actor_type'         => 'Person',
			'preferred_username' => $slug,
			'display_name'       => $slug,
			'profile_url'        => 'https://example.com/@' . $slug,
			'endpoints'          => $endpoints,
			'payload'            => array(
				'id'                => $uri,
				'type'              => 'Person',
				'preferredUsername' => $slug,
			),
		)
	);
}

try {
	axismundi_actors_install();
	$table = axismundi_actors_endpoints_table();
	$actors = axismundi_actors_actors_table();
	$identity_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'identity_endpoint'", ARRAY_A ); // phpcs:ignore WordPress.DB
	$hash_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'endpoint_uri_hash'", ARRAY_A ); // phpcs:ignore WordPress.DB
	$actor_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$actors}" ); // phpcs:ignore WordPress.DB
	$engines = (array) $wpdb->get_col( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s,%s,%s)', axismundi_actors_identities_table(), $actors, $table ) ); // phpcs:ignore WordPress.DB
	ax_endpoint_assert( $ax_endpoint_results, 'DB v7 creates an InnoDB endpoint ledger, unique actor-role key, non-unique URI hash, and removes legacy columns', (int) get_option( 'ax_actors_db_version' ) >= 7 && 3 === count( array_filter( $engines, static fn( string $engine ) : bool => 'InnoDB' === $engine ) ) && ! empty( $identity_index ) && '0' === (string) $identity_index[0]['Non_unique'] && ! empty( $hash_index ) && '1' === (string) $hash_index[0]['Non_unique'] && ! in_array( 'inbox_uri', $actor_columns, true ) && ! in_array( 'outbox_uri', $actor_columns, true ) );

	$shared = 'https://example.com/inbox/shared';
	$first = ax_endpoint_actor(
		'endpoint_one',
		array(
			'inbox'        => 'https://example.com/users/endpoint_one/inbox',
			'outbox'       => 'https://example.com/users/endpoint_one/outbox',
			'followers'    => 'https://example.com/users/endpoint_one/followers',
			'following'    => 'https://example.com/users/endpoint_one/following',
			'featured'     => 'https://example.com/users/endpoint_one/featured',
			'shared_inbox' => $shared,
		)
	);
	if ( $first instanceof Axismundi_Actor ) {
		$ax_endpoint_ids[] = $first->get_identity_id();
	}
	$first_map = $first instanceof Axismundi_Actor ? axismundi_actors_get_endpoints( $first ) : array();
	ax_endpoint_assert( $ax_endpoint_results, 'remote upsert stores all six endpoint roles and resolver reads them', 6 === count( $first_map ) && str_ends_with( $first_map['featured'] ?? '', '/featured' ) && $shared === ( $first_map['shared_inbox'] ?? '' ) );

	$second = ax_endpoint_actor(
		'endpoint_two',
		array(
			'inbox'        => 'https://example.com/users/endpoint_two/inbox',
			'outbox'       => 'https://example.com/users/endpoint_two/outbox',
			'shared_inbox' => $shared,
		)
	);
	if ( $second instanceof Axismundi_Actor ) {
		$ax_endpoint_ids[] = $second->get_identity_id();
	}
	$shared_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE endpoint_uri_hash = %s", hash( 'sha256', $shared ) ) ); // phpcs:ignore WordPress.DB
	ax_endpoint_assert( $ax_endpoint_results, 'multiple Actors may share one sharedInbox URI', $second instanceof Axismundi_Actor && 2 === $shared_count );

	$refreshed = ax_endpoint_actor(
		'endpoint_one',
		array(
			'inbox'  => 'https://example.com/users/endpoint_one/inbox',
			'outbox' => 'https://example.com/users/endpoint_one/outbox',
		)
	);
	$refreshed_map = $refreshed instanceof Axismundi_Actor ? axismundi_actors_get_endpoints( $refreshed ) : array();
	ax_endpoint_assert( $ax_endpoint_results, 'refresh is an exact replacement and removes stale optional roles', 2 === count( $refreshed_map ) && ! isset( $refreshed_map['featured'] ) && ! isset( $refreshed_map['shared_inbox'] ) );

	$before_invalid = $first instanceof Axismundi_Actor ? axismundi_actors_get_endpoints( $first ) : array();
	$invalid = $first instanceof Axismundi_Actor ? axismundi_actors_replace_endpoints( $first->get_identity_id(), array( 'inbox' => 'http://unsafe.example/inbox' ) ) : null;
	$after_invalid = $first instanceof Axismundi_Actor ? axismundi_actors_get_endpoints( $first ) : array();
	ax_endpoint_assert( $ax_endpoint_results, 'invalid replacement is rejected before existing endpoint rows are deleted', is_wp_error( $invalid ) && $before_invalid === $after_invalid );

	// Recreate the v6 shape and prove install migrates before dropping columns.
	$wpdb->query( "ALTER TABLE {$actors} ADD COLUMN inbox_uri text DEFAULT NULL" ); // phpcs:ignore WordPress.DB
	$wpdb->query( "ALTER TABLE {$actors} ADD COLUMN outbox_uri text DEFAULT NULL" ); // phpcs:ignore WordPress.DB
	$legacy_inbox  = 'https://example.com/users/endpoint_one/legacy-inbox';
	$legacy_outbox = 'https://example.com/users/endpoint_one/legacy-outbox';
	$legacy_payload = wp_json_encode(
		array(
			'id'        => 'https://example.com/users/endpoint_one',
			'type'      => 'Person',
			'inbox'     => $legacy_inbox,
			'outbox'    => $legacy_outbox,
			'followers' => 'https://example.com/users/endpoint_one/legacy-followers',
			'endpoints' => array( 'sharedInbox' => $shared ),
		),
		JSON_UNESCAPED_SLASHES
	);
	if ( $first instanceof Axismundi_Actor ) {
		$wpdb->delete( $table, array( 'identity_id' => $first->get_identity_id() ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		$wpdb->update( $actors, array( 'inbox_uri' => $legacy_inbox, 'outbox_uri' => $legacy_outbox, 'payload_json' => $legacy_payload ), array( 'identity_id' => $first->get_identity_id() ) ); // phpcs:ignore WordPress.DB
	}
	update_option( 'ax_actors_db_version', '6', false );
	axismundi_actors_install();
	$migrated_map = $first instanceof Axismundi_Actor ? axismundi_actors_get_endpoints( $first ) : array();
	$final_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$actors}" ); // phpcs:ignore WordPress.DB
	ax_endpoint_assert( $ax_endpoint_results, 'v6 migration backfills legacy and payload roles before dropping old columns', $legacy_inbox === ( $migrated_map['inbox'] ?? '' ) && $legacy_outbox === ( $migrated_map['outbox'] ?? '' ) && isset( $migrated_map['followers'], $migrated_map['shared_inbox'] ) && ! in_array( 'inbox_uri', $final_columns, true ) && ! in_array( 'outbox_uri', $final_columns, true ) && '7' === (string) get_option( 'ax_actors_db_version' ) );
} finally {
	foreach ( array_unique( $ax_endpoint_ids ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}
}

$ax_endpoint_failures = count( array_filter( $ax_endpoint_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_endpoint_results ), $ax_endpoint_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_endpoint_failures > 0 ? 1 : 0 );
}
exit( $ax_endpoint_failures > 0 ? 1 : 0 );
