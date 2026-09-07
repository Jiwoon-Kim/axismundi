<?php
/**
 * DB v8 — follower/discovery policy axes regression (dev-only; dist-excluded).
 * No network: exercises payload extraction + repository persistence directly.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/routing.php';
require_once dirname( __DIR__ ) . '/includes/webfinger.php';
require_once dirname( __DIR__ ) . '/includes/remote-discovery.php';

global $wpdb;
$ax_pol_results = array();
$ax_pol_ids     = array();
$ax_pol_site_actor = null;
$ax_pol_old_follow_visibility = null;
$ax_pol_admin_id = 0;

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $cond Holds.
 * @return void
 */
function ax_pol_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * A minimally valid remote Actor record for upsert_remote, merged with $extra.
 *
 * @param string $slug  Unique username / URI segment.
 * @param array  $extra Payload flags to merge.
 * @return array<string,mixed>
 */
function ax_pol_record( string $slug, array $extra ) : array {
	$uri     = 'https://example.com/users/' . $slug;
	$payload = array_merge(
		array(
			'id'                => $uri,
			'type'              => 'Person',
			'preferredUsername' => $slug,
			'inbox'             => 'https://example.com/users/' . $slug . '/inbox',
			'outbox'            => 'https://example.com/users/' . $slug . '/outbox',
		),
		$extra
	);
	return axismundi_actors_normalize_remote_actor_payload( $payload, $uri );
}

try {
	axismundi_actors_install();
	$actors = axismundi_actors_actors_table();

	// Schema gate.
	$cols = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$actors}" ); // phpcs:ignore WordPress.DB
	$has_all = ! array_diff( array( 'published_at', 'manually_approves_followers', 'discoverable', 'indexable', 'follow_collections_visibility' ), $cols );
	ax_pol_assert( $ax_pol_results, 'v8 adds the five policy columns and records the version', $has_all && (int) get_option( 'ax_actors_db_version' ) >= 8 );

	// Extraction preserves the unreported vs explicit distinction at the payload layer.
	$declared = axismundi_actors_extract_policy_from_payload( array( 'manuallyApprovesFollowers' => false, 'discoverable' => true, 'published' => '2019-05-01T00:00:00Z' ) );
	$silent   = axismundi_actors_extract_policy_from_payload( array( 'preferredUsername' => 'x' ) );
	ax_pol_assert(
		$ax_pol_results,
		'payload extraction keeps declared booleans (incl. false) and omits undeclared keys',
		false === $declared['manually_approves_followers'] && true === $declared['discoverable'] && '2019-05-01 00:00:00' === $declared['published_at']
			&& ! array_key_exists( 'manually_approves_followers', $silent ) && ! array_key_exists( 'discoverable', $silent )
	);

	// A locked, discoverable-false actor stores 1 and 0 — not NULL.
	$rec  = ax_pol_record( 'ax_pol_locked', array( 'manuallyApprovesFollowers' => true, 'discoverable' => false, 'indexable' => true ) );
	$a1   = axismundi_actors_upsert_remote( $rec );
	$ax_pol_ids[] = $a1 instanceof Axismundi_Actor ? $a1->get_identity_id() : 0;
	ax_pol_assert(
		$ax_pol_results,
		'explicit true/false persist as tri-state true/false via the getters',
		$a1 instanceof Axismundi_Actor && true === $a1->get_policy_flag( 'manually_approves_followers' ) && false === $a1->get_policy_flag( 'discoverable' ) && true === $a1->get_policy_flag( 'indexable' )
	);

	// An actor that declares nothing keeps every policy NULL (unreported ≠ false).
	$rec2 = ax_pol_record( 'ax_pol_silent', array() );
	$a2   = axismundi_actors_upsert_remote( $rec2 );
	$ax_pol_ids[] = $a2 instanceof Axismundi_Actor ? $a2->get_identity_id() : 0;
	ax_pol_assert(
		$ax_pol_results,
		'an undeclared policy stays NULL, never coerced to false',
		$a2 instanceof Axismundi_Actor && null === $a2->get_policy_flag( 'manually_approves_followers' ) && null === $a2->get_policy_flag( 'discoverable' ) && null === $a2->get_policy_flag( 'indexable' ) && null === $a2->get_follow_collections_visibility()
	);

	// A refresh that drops a previously declared flag resets it to NULL.
	$rec3 = ax_pol_record( 'ax_pol_locked', array() );
	$a1b  = axismundi_actors_upsert_remote( $rec3 );
	ax_pol_assert(
		$ax_pol_results,
		'refreshing without a flag resets it from true back to NULL (no stale false)',
		$a1b instanceof Axismundi_Actor && null === $a1b->get_policy_flag( 'manually_approves_followers' ) && null === $a1b->get_policy_flag( 'indexable' )
	);

	// follow_collections_visibility only accepts the three enum values.
	$ok_fields  = axismundi_actors_normalize_policy_fields( array( 'follow_collections_visibility' => 'followers' ) );
	$bad_fields = axismundi_actors_normalize_policy_fields( array( 'follow_collections_visibility' => 'everyone' ) );
	ax_pol_assert( $ax_pol_results, 'follow_collections_visibility validates to the enum or NULL', 'followers' === $ok_fields['follow_collections_visibility'] && null === $bad_fields['follow_collections_visibility'] );

	$ax_pol_site_actor = axismundi_actors_get_site_actor();
	$admin_ids         = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
	$ax_pol_admin_id   = isset( $admin_ids[0] ) ? (int) $admin_ids[0] : 0;
	$ax_pol_old_follow_visibility = $ax_pol_site_actor instanceof Axismundi_Actor ? $ax_pol_site_actor->get_follow_collections_visibility() : null;
	$set_public = $ax_pol_site_actor instanceof Axismundi_Actor
		? axismundi_actors_set_follow_collections_visibility( $ax_pol_site_actor, 'public', $ax_pol_admin_id )
		: new WP_Error( 'ax_pol_site_actor' );
	$refreshed = $ax_pol_site_actor instanceof Axismundi_Actor ? axismundi_actors_get_by_uuid( $ax_pol_site_actor->get_uuid() ) : null;
	ax_pol_assert( $ax_pol_results, 'the repository permission-checks and persists local Follow collection visibility', true === $set_public && $refreshed instanceof Axismundi_Actor && 'public' === $refreshed->get_follow_collections_visibility() );
	$invalid = $ax_pol_site_actor instanceof Axismundi_Actor
		? axismundi_actors_set_follow_collections_visibility( $ax_pol_site_actor, 'everyone', $ax_pol_admin_id )
		: null;
	ax_pol_assert( $ax_pol_results, 'the local Follow collection setter rejects values outside the enum', is_wp_error( $invalid ) && 'ax_actors_follow_collections_visibility' === $invalid->get_error_code() );

} finally {
	if ( $ax_pol_site_actor instanceof Axismundi_Actor && $ax_pol_admin_id > 0 ) {
		axismundi_actors_set_follow_collections_visibility( $ax_pol_site_actor, $ax_pol_old_follow_visibility, $ax_pol_admin_id );
	}
	foreach ( array_unique( array_filter( $ax_pol_ids ) ) as $iid ) {
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_addresses_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$ax_pol_failures = count( array_filter( $ax_pol_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_pol_results ), $ax_pol_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_pol_failures > 0 ? 1 : 0 );
}
exit( $ax_pol_failures > 0 ? 1 : 0 );
