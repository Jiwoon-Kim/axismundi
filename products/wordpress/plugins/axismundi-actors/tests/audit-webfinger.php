<?php
/**
 * Local WebFinger endpoint and fail-closed topology regression. Dev-only;
 * excluded from release archives.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/routing.php';
require_once dirname( __DIR__ ) . '/includes/webfinger.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$ax_wf_results = array();
$ax_wf_ids     = array();
$ax_wf_users   = array();

/** @param array $results Accumulator. @param string $label Contract. @param bool $condition Holds. */
function ax_wf_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_actors_install();
	$authority = axismundi_actors_webfinger_authority();

	ax_wf_assert( $ax_wf_results, 'authority includes the current site host and development port', '' !== $authority && str_contains( home_url( '/' ), explode( ':', $authority )[0] ) );
	ax_wf_assert( $ax_wf_results, 'topology policy enables single-site, subdomain, and mapped-domain sites', axismundi_actors_webfinger_policy_allows( false, false, 'example.test', 'example.test' ) && axismundi_actors_webfinger_policy_allows( true, true, 'a.example.test', 'example.test' ) && axismundi_actors_webfinger_policy_allows( true, false, 'mapped.test', 'network.test' ) );
	ax_wf_assert( $ax_wf_results, 'topology policy disables an ambiguous subdirectory multisite authority', ! axismundi_actors_webfinger_policy_allows( true, false, 'example.test', 'example.test' ) );
	ax_wf_assert( $ax_wf_results, 'rewrite and plain query endpoints are registered', isset( axismundi_actors_rewrite_rules()['^\.well-known/webfinger/?$'] ) && in_array( 'ax_webfinger', axismundi_actors_query_vars( array() ), true ) );

	$user_id = (int) wp_insert_user( array( 'user_login' => 'ax_wf_alice', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_wf_users[] = $user_id;
	$actor = axismundi_actors_ensure_for_user( $user_id );
	$ax_wf_ids[] = $actor->get_identity_id();
	axismundi_actors_register_handle( $actor->get_identity_id(), 'wf_alice' );

	$internal = axismundi_actors_webfinger_descriptor( 'acct:wf_alice@' . $authority );
	ax_wf_assert( $ax_wf_results, 'an internal actor is concealed', is_wp_error( $internal ) && 'ax_actors_webfinger_not_found' === $internal->get_error_code() );

	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$actor      = axismundi_actors_get_by_uuid( $actor->get_uuid() );
	$before_read = array_values( array_filter( axismundi_actors_get_addresses( $actor->get_identity_id() ), static fn( array $row ) : bool => 'acct' === $row['address_type'] ) );
	ax_wf_assert( $ax_wf_results, 'publishing a handled actor synchronizes its local acct address before discovery', 1 === count( $before_read ) && 'wf_alice@' . $authority === $before_read[0]['address'] );
	$descriptor = axismundi_actors_webfinger_descriptor( 'acct:wf_alice@' . $authority );
	ax_wf_assert( $ax_wf_results, 'a public actor resolves to an honest JRD subject and aliases', is_array( $descriptor ) && 'acct:wf_alice@' . $authority === $descriptor['subject'] && in_array( $actor->get_uri(), $descriptor['aliases'], true ) && $actor->get_profile_url() === $descriptor['links'][0]['href'] );

	$rows = array_values( array_filter( axismundi_actors_get_addresses( $actor->get_identity_id() ), static fn( array $row ) : bool => 'acct' === $row['address_type'] ) );
	ax_wf_assert( $ax_wf_results, 'successful local resolution records one verified primary acct address', 1 === count( $rows ) && 'wf_alice@' . $authority === $rows[0]['address'] && 'primary' === $rows[0]['status'] && ! empty( $rows[0]['verified_at'] ) );

	$wrong_host = axismundi_actors_webfinger_descriptor( 'acct:wf_alice@elsewhere.example' );
	$bad_form   = axismundi_actors_webfinger_descriptor( 'https://example.test/@wf_alice' );
	ax_wf_assert( $ax_wf_results, 'foreign authorities and non-acct resources are rejected', is_wp_error( $wrong_host ) && is_wp_error( $bad_form ) );

	$add_self = static function ( array $links, Axismundi_Actor $link_actor ) : array {
		$links[] = array( 'rel' => 'self', 'type' => 'application/activity+json', 'href' => $link_actor->get_uri() );
		return $links;
	};
	add_filter( 'axismundi_actors_webfinger_links', $add_self, 10, 2 );
	$extended = axismundi_actors_webfinger_descriptor( 'acct:wf_alice@' . $authority );
	remove_filter( 'axismundi_actors_webfinger_links', $add_self, 10 );
	ax_wf_assert( $ax_wf_results, 'the Federation seam can add the ActivityStreams self link without changing Actors', is_array( $extended ) && 'self' === $extended['links'][1]['rel'] && $actor->get_uri() === $extended['links'][1]['href'] );
} finally {
	foreach ( array_unique( $ax_wf_ids ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_addresses_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( $ax_wf_users as $user_id ) {
		if ( get_userdata( $user_id ) ) {
			wp_delete_user( $user_id );
		}
	}
}

$ax_wf_failures = count( array_filter( $ax_wf_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_wf_results ), $ax_wf_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_wf_failures > 0 ? 1 : 0 );
}
exit( $ax_wf_failures > 0 ? 1 : 0 );
