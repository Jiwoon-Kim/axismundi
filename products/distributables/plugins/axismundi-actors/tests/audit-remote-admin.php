<?php
/**
 * Remote Actor administration surface regression. Dev-only and dist-excluded.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/webfinger.php';
require_once dirname( __DIR__ ) . '/includes/remote-discovery.php';
require_once dirname( __DIR__ ) . '/includes/instances.php';
require_once dirname( __DIR__ ) . '/includes/admin.php';

global $wpdb;
$ax_remote_admin_results = array();
$ax_remote_admin_id      = 0;
$ax_remote_admin_user    = get_current_user_id();
$ax_remote_admin_action  = static function ( Axismundi_Actor $actor ) : void {
	echo '<span class="ax-remote-action-fixture">' . esc_html( $actor->get_uri() ) . '</span>';
};

/** @param array $results Accumulator. @param string $label Contract. @param bool $condition Holds. */
function ax_remote_admin_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
	wp_set_current_user( (int) ( $admins[0] ?? 0 ) );
	$actor = axismundi_actors_upsert_remote(
		array(
			'uri'                => 'https://example.com/users/admin_fixture',
			'actor_type'         => 'Person',
			'preferred_username' => 'admin_fixture',
			'display_name'       => 'Admin Fixture',
			'summary'            => '<p>Fixture summary.</p>',
			'profile_url'        => 'https://example.com/@admin_fixture',
			'endpoints'          => array(
				'inbox'  => 'https://example.com/users/admin_fixture/inbox',
				'outbox' => 'https://example.com/users/admin_fixture/outbox',
			),
			'payload'            => array( 'id' => 'https://example.com/users/admin_fixture', 'type' => 'Person', 'preferredUsername' => 'admin_fixture', 'name' => 'Admin Fixture' ),
		)
	);
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_remote_admin_id = $actor->get_identity_id();
	}
	axismundi_actors_record_verified_acct_address( $ax_remote_admin_id, 'admin_fixture@example.com' );
	axismundi_actors_upsert_instance( 'example.com', array( 'software_name' => 'fixture', 'software_version' => '1.2.3', 'open_registrations' => 1, 'fetch_status' => 'ok', 'payload_json' => '{}' ) );

	ax_remote_admin_assert( $ax_remote_admin_results, 'the remote screen lives under Users and requires manage_options', str_contains( axismundi_actors_remote_admin_url(), 'users.php?page=axismundi-remote-actors' ) && current_user_can( 'manage_options' ) );
	ax_remote_admin_assert( $ax_remote_admin_results, 'repository list APIs return the cached Actor and host rows', 1 <= count( array_filter( axismundi_actors_get_remote_actors(), static fn( Axismundi_Actor $item ) : bool => $item->get_identity_id() === $ax_remote_admin_id ) ) && null !== axismundi_actors_get_instance( 'example.com' ) );
	ax_remote_admin_assert( $ax_remote_admin_results, 'remote Actor search and count include verified acct addresses outside an unfiltered recent-page assumption', 1 === count( axismundi_actors_get_remote_actors( 50, 0, '@admin_fixture@example.com' ) ) && 1 === axismundi_actors_count_remote_actors( '@admin_fixture@example.com' ) );

	$_GET['actor_id'] = $ax_remote_admin_id;
	add_action( 'axismundi_actors_remote_actor_actions', $ax_remote_admin_action );
	ob_start();
	axismundi_actors_render_remote_admin_page();
	$html = (string) ob_get_clean();
	ax_remote_admin_assert( $ax_remote_admin_results, 'screen renders the nonce-protected acct/URL lookup form', str_contains( $html, 'axismundi_actors_discover_remote' ) && str_contains( $html, 'Fetch Actor' ) && str_contains( $html, '_wpnonce' ) );
	ax_remote_admin_assert( $ax_remote_admin_results, 'screen exposes cached Actor search, total count, and pagination-ready controls', str_contains( $html, 'ax_actor_search' ) && str_contains( $html, 'Search cached Actors' ) && str_contains( $html, 'displaying-num' ) );
	ax_remote_admin_assert( $ax_remote_admin_results, 'selected Actor shows normalized identity, endpoints, verified acct, and escaped raw JSON', str_contains( $html, 'Admin Fixture' ) && str_contains( $html, 'admin_fixture@example.com' ) && str_contains( $html, 'Endpoints' ) && str_contains( $html, '/inbox' ) && str_contains( $html, 'Raw Actor JSON' ) && str_contains( $html, 'preferredUsername' ) );
	ax_remote_admin_assert( $ax_remote_admin_results, 'screen shows the linked instance software cache', str_contains( $html, 'fixture 1.2.3' ) && str_contains( $html, 'Cached instances' ) );
	ax_remote_admin_assert( $ax_remote_admin_results, 'selected Actor exposes the administrator action seam to companion plugins', str_contains( $html, 'ax-remote-action-fixture' ) && $actor instanceof Axismundi_Actor && str_contains( $html, $actor->get_uri() ) );
	ax_remote_admin_assert( $ax_remote_admin_results, 'remote cache controls, optional WebP setting, and public cached profile are linked', str_contains( $html, 'Remote image cache' ) && str_contains( $html, 'axismundi_actors_asset_cache' ) && str_contains( $html, 'axismundi_actors_asset_settings' ) && str_contains( $html, 'View cached profile' ) && $actor instanceof Axismundi_Actor && axismundi_actors_can_view( $actor, get_current_user_id() ) && axismundi_actors_can_view( $actor, 0 ) && str_contains( $html, '/actors/' . $actor->get_uuid() . '/' ) );
	if ( $actor instanceof Axismundi_Actor ) {
		$GLOBALS['axismundi_actors_current_actor'] = $actor;
		$profile_data = axismundi_actors_profile_data( $actor );
		$robots       = axismundi_actors_remote_preview_robots( array() );
		$GLOBALS['axismundi_actors_current_actor'] = null;
		ax_remote_admin_assert( $ax_remote_admin_results, 'public remote profile reads cached payload text without a forced noindex directive', 'Admin Fixture' === $profile_data['name'] && ! isset( $robots['noindex'] ) && ! isset( $robots['nofollow'] ) );
	}
} finally {
	remove_action( 'axismundi_actors_remote_actor_actions', $ax_remote_admin_action );
	unset( $_GET['actor_id'] );
	if ( $ax_remote_admin_id > 0 ) {
		$wpdb->delete( axismundi_actors_addresses_table(), array( 'identity_id' => $ax_remote_admin_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => $ax_remote_admin_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_remote_admin_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_remote_admin_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	$wpdb->delete( axismundi_actors_instances_table(), array( 'host_hash' => axismundi_actors_host_hash( 'example.com' ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	wp_set_current_user( $ax_remote_admin_user );
}

$ax_remote_admin_failures = count( array_filter( $ax_remote_admin_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_remote_admin_results ), $ax_remote_admin_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_remote_admin_failures > 0 ? 1 : 0 );
}
exit( $ax_remote_admin_failures > 0 ? 1 : 0 );
