<?php
/**
 * Local NodeInfo 2.1 regression (dev-only; dist-excluded).
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/routing.php';
require_once dirname( __DIR__ ) . '/includes/webfinger.php';
require_once dirname( __DIR__ ) . '/includes/nodeinfo.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$ax_ni_results = array();
$ax_ni_ids     = array();
$ax_ni_users   = array();
$ax_ni_prev_reg = get_option( 'users_can_register' );

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $cond Holds.
 * @return void
 */
function ax_ni_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_actors_install();

	// Discovery links to the 2.1 document with the correct rel.
	$discovery = axismundi_actors_nodeinfo_discovery();
	$link      = $discovery['links'][0] ?? array();
	ax_ni_assert( $ax_ni_results, 'discovery advertises the NodeInfo 2.1 schema and document URL', 'http://nodeinfo.diaspora.software/ns/schema/2.1' === ( $link['rel'] ?? '' ) && $link['href'] === axismundi_actors_nodeinfo_document_url() );

	// Document shape: version, software, registration, usage.
	update_option( 'users_can_register', 1 );
	$doc = axismundi_actors_nodeinfo_document();
	ax_ni_assert( $ax_ni_results, 'the document is NodeInfo 2.1 with our software and open registrations', '2.1' === $doc['version'] && 'axismundi' === $doc['software']['name'] && AXISMUNDI_ACTORS_VERSION === $doc['software']['version'] && true === $doc['openRegistrations'] );
	update_option( 'users_can_register', 0 );
	$doc2 = axismundi_actors_nodeinfo_document();
	ax_ni_assert( $ax_ni_results, 'openRegistrations reflects the WordPress setting', false === $doc2['openRegistrations'] && isset( $doc2['usage']['localPosts'], $doc2['usage']['localComments'] ) );

	// User count = only public local actors with a locked handle.
	$base = axismundi_actors_nodeinfo_user_count();
	$uid  = (int) wp_insert_user( array( 'user_login' => 'ax_ni_alice', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_ni_users[] = $uid;
	$actor = axismundi_actors_ensure_for_user( $uid );
	$ax_ni_ids[] = $actor->get_identity_id();
	$after_internal = axismundi_actors_nodeinfo_user_count();
	axismundi_actors_register_handle( $actor->get_identity_id(), 'ni_alice' );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$after_public = axismundi_actors_nodeinfo_user_count();
	ax_ni_assert( $ax_ni_results, 'usage.users.total counts only public local actors with a handle', $after_internal === $base && $after_public === $base + 1 );

	// The software block and protocols are filterable for the Federation plugin.
	add_filter( 'axismundi_actors_nodeinfo_protocols', static fn() : array => array( 'activitypub' ) );
	$doc3 = axismundi_actors_nodeinfo_document();
	remove_all_filters( 'axismundi_actors_nodeinfo_protocols' );
	ax_ni_assert( $ax_ni_results, 'protocols are filterable (empty until the Federation plugin adds them)', array( 'activitypub' ) === $doc3['protocols'] && array() === axismundi_actors_nodeinfo_document()['protocols'] );

	// The routes are registered.
	$rules = axismundi_actors_rewrite_rules();
	ax_ni_assert( $ax_ni_results, 'the .well-known/nodeinfo and /nodeinfo/2.1 routes are registered', isset( $rules['^\.well-known/nodeinfo/?$'], $rules['^nodeinfo/2\.1/?$'] ) );

} finally {
	update_option( 'users_can_register', $ax_ni_prev_reg );
	remove_all_filters( 'axismundi_actors_nodeinfo_protocols' );
	foreach ( array_unique( $ax_ni_ids ) as $iid ) {
		$wpdb->delete( axismundi_actors_addresses_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $iid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( $ax_ni_users as $u ) {
		if ( get_userdata( $u ) ) {
			wp_delete_user( $u );
		}
	}
}

$ax_ni_failures = count( array_filter( $ax_ni_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ni_results ), $ax_ni_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ni_failures > 0 ? 1 : 0 );
}
exit( $ax_ni_failures > 0 ? 1 : 0 );
