<?php
/**
 * DB v10b — observed identity relation regression (dev-only).
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/routing.php';
require_once dirname( __DIR__ ) . '/includes/webfinger.php';
require_once dirname( __DIR__ ) . '/includes/remote-discovery.php';

global $wpdb;
$ax_rel_results = array();
$ax_rel_ids     = array();

/** @param array<bool> $results Results. @param string $label Contract. @param bool $condition Holds. */
function ax_rel_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/**
 * @param string                   $slug Actor slug.
 * @param array<string,mixed>|null $relation_payload Relation fields; null omits them.
 * @return array<string,mixed>|WP_Error
 */
function ax_rel_record( string $slug, ?array $relation_payload ) {
	$uri     = 'https://example.com/users/' . $slug;
	$payload = array(
		'id'                => $uri,
		'type'              => 'Person',
		'preferredUsername' => $slug,
		'inbox'             => $uri . '/inbox',
		'outbox'            => $uri . '/outbox',
	);
	if ( null !== $relation_payload ) {
		$payload = array_merge( $payload, $relation_payload );
	}
	return axismundi_actors_normalize_remote_actor_payload( $payload, $uri );
}

try {
	axismundi_actors_install();
	$table = axismundi_actors_identity_relations_table();
	$index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'identity_relation'", ARRAY_A ); // phpcs:ignore WordPress.DB
	ax_rel_assert( $ax_rel_results, 'v10b creates the unique identity relation ledger and records schema 10.1', ! empty( $index ) && '0' === (string) $index[0]['Non_unique'] && version_compare( (string) get_option( 'ax_actors_db_version' ), '10.1', '>=' ) );

	$source = 'https://example.com/users/ax_rel_a';
	$extracted = axismundi_actors_extract_identity_relations_from_payload(
		array(
			'alsoKnownAs' => array( 'https://example.net/users/a', array( 'id' => 'https://www.example.com/@a' ), 'https://example.net/users/a', $source, 'http://example.org/a' ),
			'movedTo'     => array( 'id' => 'https://example.org/users/a' ),
		),
		$source
	);
	ax_rel_assert( $ax_rel_results, 'extractor accepts scalar/object/list forms, deduplicates, and rejects self or non-HTTPS targets', 3 === count( $extracted ) );

	$record = ax_rel_record(
		'ax_rel_a',
		array(
			'alsoKnownAs' => array( 'https://example.net/users/a', 'https://www.example.com/@a' ),
			'movedTo'     => 'https://example.org/users/a',
		)
	);
	$actor = is_wp_error( $record ) ? $record : axismundi_actors_upsert_remote( $record );
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_rel_ids[] = $actor->get_identity_id();
	}
	$rows = $actor instanceof Axismundi_Actor ? axismundi_actors_get_identity_relations( $actor->get_identity_id() ) : array();
	ax_rel_assert( $ax_rel_results, 'remote upsert stores every claim as observed without assigning trust', 3 === count( $rows ) && 3 === count( array_filter( $rows, static fn( array $row ) : bool => 'observed' === $row['verification_state'] ) ) );

	$again = is_wp_error( $record ) ? $record : axismundi_actors_upsert_remote( $record );
	$rows_again = $actor instanceof Axismundi_Actor ? axismundi_actors_get_identity_relations( $actor->get_identity_id() ) : array();
	ax_rel_assert( $ax_rel_results, 'refresh is idempotent under the compound unique identity', $again instanceof Axismundi_Actor && 3 === count( $rows_again ) );

	if ( $actor instanceof Axismundi_Actor ) {
		$wpdb->delete( $table, array( 'identity_id' => $actor->get_identity_id() ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		update_option( 'ax_actors_db_version', '10', false );
		axismundi_actors_install();
	}
	$backfilled = $actor instanceof Axismundi_Actor ? axismundi_actors_get_identity_relations( $actor->get_identity_id() ) : array();
	ax_rel_assert( $ax_rel_results, 'v10b upgrade backfills claims from existing remote payload snapshots', 3 === count( $backfilled ) && '10.1' === (string) get_option( 'ax_actors_db_version' ) );

	$empty_record = ax_rel_record( 'ax_rel_a', null );
	if ( ! is_wp_error( $empty_record ) ) {
		axismundi_actors_upsert_remote( $empty_record );
	}
	$preserved = $actor instanceof Axismundi_Actor ? axismundi_actors_get_identity_relations( $actor->get_identity_id() ) : array();
	ax_rel_assert( $ax_rel_results, 'a relation-less refresh preserves prior observations', 3 === count( $preserved ) );

	$verified = $actor instanceof Axismundi_Actor && axismundi_actors_set_identity_relation_verification( $actor->get_identity_id(), 'moved_to', 'https://example.org/users/a', 'verified' );
	$verified_rows = $actor instanceof Axismundi_Actor ? axismundi_actors_get_identity_relations( $actor->get_identity_id(), 'moved_to' ) : array();
	ax_rel_assert( $ax_rel_results, 'Federation seam can promote an observed relation to verified', $verified && 1 === count( $verified_rows ) && 'verified' === $verified_rows[0]['verification_state'] && ! empty( $verified_rows[0]['verified_at'] ) );

	if ( ! is_wp_error( $record ) ) {
		axismundi_actors_upsert_remote( $record );
	}
	$not_downgraded = $actor instanceof Axismundi_Actor ? axismundi_actors_get_identity_relations( $actor->get_identity_id(), 'moved_to' ) : array();
	ax_rel_assert( $ax_rel_results, 'a later inbound observation never downgrades a verified relation', 1 === count( $not_downgraded ) && 'verified' === $not_downgraded[0]['verification_state'] );

	$rejected = $actor instanceof Axismundi_Actor && axismundi_actors_set_identity_relation_verification( $actor->get_identity_id(), 'also_known_as', 'https://example.net/users/a', 'rejected' );
	$invalid  = $actor instanceof Axismundi_Actor && axismundi_actors_set_identity_relation_verification( $actor->get_identity_id(), 'moved_to', 'https://example.org/users/a', 'observed' );
	ax_rel_assert( $ax_rel_results, 'verification accepts only explicit verified/rejected decisions', $rejected && ! $invalid );

	$normalized = axismundi_actors_normalize_identity_relation( array( 'relation_type' => 'unknown', 'target_uri' => 'https://example.net/x' ), $source );
	ax_rel_assert( $ax_rel_results, 'repository validation rejects unknown relation types', null === $normalized );
} finally {
	foreach ( array_unique( array_filter( $ax_rel_ids ) ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_identity_relations_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_keys_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_fetch_state_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB
	}
}

$ax_rel_failures = count( array_filter( $ax_rel_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_rel_results ), $ax_rel_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_rel_failures > 0 ? 1 : 0 );
}
exit( $ax_rel_failures > 0 ? 1 : 0 );
