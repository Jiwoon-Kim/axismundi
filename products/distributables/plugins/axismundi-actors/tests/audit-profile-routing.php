<?php
/**
 * Phase 2 actor profile routing regression (dev-only; dist-excluded).
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/routing.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$ax_profile_results = array();
$ax_profile_ids     = array();
$ax_profile_users   = array();
$ax_old_permalink  = (string) get_option( 'permalink_structure', '' );

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $condition Holds.
 * @return void
 */
function ax_profile_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_actors_install();
	$user_id = (int) wp_insert_user(
		array(
			'user_login'   => 'ax_profile_alice',
			'user_pass'    => wp_generate_password(),
			'user_email'   => 'alice-private@example.test',
			'display_name' => 'Alice Profile',
			'user_url'     => 'https://alice.example/',
			'description'  => 'A live profile summary.',
			'role'         => 'author',
		)
	);
	$ax_profile_users[] = $user_id;
	$actor = axismundi_actors_ensure_for_user( $user_id );
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_profile_ids[] = $actor->get_identity_id();
	}

	ax_profile_assert( $ax_profile_results, 'fixture creates one internal local Person', $actor instanceof Axismundi_Actor && 'internal' === $actor->get_status() );
	ax_profile_assert( $ax_profile_results, 'anonymous viewers cannot see an internal actor', $actor instanceof Axismundi_Actor && ! axismundi_actors_can_view( $actor, 0 ) );
	ax_profile_assert( $ax_profile_results, 'the linked user can preview their internal actor', $actor instanceof Axismundi_Actor && axismundi_actors_can_preview( $actor, $user_id ) );

	$admin_id = (int) wp_insert_user(
		array(
			'user_login' => 'ax_profile_admin',
			'user_pass'  => wp_generate_password(),
			'role'       => 'administrator',
		)
	);
	$ax_profile_users[] = $admin_id;
	ax_profile_assert( $ax_profile_results, 'manage_options can preview another internal actor', $actor instanceof Axismundi_Actor && axismundi_actors_can_preview( $actor, $admin_id ) );

	$live = axismundi_actors_profile_data( $actor );
	ax_profile_assert( $ax_profile_results, 'local display name, bio, and website are read live from WP_User', 'Alice Profile' === $live['name'] && 'A live profile summary.' === $live['summary'] && 'https://alice.example/' === $live['url'] );

	$original_uuid = $actor->get_uuid();
	$original_uri  = $actor->get_uri();

	// A user's actor is handle-less until they activate; the handle is registered once.
	ax_profile_assert( $ax_profile_results, 'a freshly ensured Person is handle-less until activation', '' === $actor->get_preferred_username() && '' === $actor->get_profile_url() );
	$registered = axismundi_actors_register_handle( $actor->get_identity_id(), 'alice-profile' );
	$actor      = axismundi_actors_get_by_uuid( $original_uuid );
	ax_profile_assert( $ax_profile_results, 'activation registers and locks the handle', true === $registered && $actor instanceof Axismundi_Actor && 'alice-profile' === $actor->get_preferred_username() && $actor->is_handle_locked() );

	ax_profile_assert( $ax_profile_results, 'local handle resolves through local_handle_key', $actor->get_identity_id() === axismundi_actors_get_by_handle( $actor->get_preferred_username() )->get_identity_id() );
	ax_profile_assert( $ax_profile_results, 'UUID route resolves the same local actor', $actor->get_identity_id() === axismundi_actors_resolve_request_actor( $original_uuid, '' )->get_identity_id() );
	ax_profile_assert( $ax_profile_results, 'malformed UUIDs do not resolve', null === axismundi_actors_resolve_request_actor( 'not-a-uuid', '' ) );

	update_option( 'permalink_structure', '' );
	ax_profile_assert( $ax_profile_results, 'plain profile fallback works without pretty permalinks', false !== strpos( $actor->get_profile_url(), 'ax_actor_handle=' ) );
	update_option( 'permalink_structure', '/%postname%/' );
	ax_profile_assert( $ax_profile_results, 'pretty profile alias uses the mutable handle', home_url( '/@' . rawurlencode( $actor->get_preferred_username() ) . '/' ) === $actor->get_profile_url() );

	// The handle is immutable once registered: re-registration is refused and the alias holds.
	$old_handle = $actor->get_preferred_username();
	$again      = axismundi_actors_register_handle( $actor->get_identity_id(), 'alice-profile-moved' );
	$after      = axismundi_actors_get_by_uuid( $original_uuid );
	ax_profile_assert(
		$ax_profile_results,
		'a registered handle is immutable; UUID, URI, and alias stay stable',
		is_wp_error( $again ) && $after instanceof Axismundi_Actor && $after->get_uri() === $original_uri && $after->get_preferred_username() === $old_handle && null === axismundi_actors_get_by_handle( 'alice-profile-moved' )
	);

	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$public_actor = axismundi_actors_get_by_uuid( $original_uuid );
	ax_profile_assert( $ax_profile_results, 'a public actor with a registered handle is visible anonymously', $public_actor instanceof Axismundi_Actor && axismundi_actors_can_view( $public_actor, 0 ) );

	// A public status without a registered handle stays hidden from the public.
	$nohandle = axismundi_actors_ensure_for_user( $admin_id );
	if ( $nohandle instanceof Axismundi_Actor ) {
		$ax_profile_ids[] = $nohandle->get_identity_id();
		axismundi_actors_set_status( $nohandle->get_identity_id(), 'public' );
		$nohandle = axismundi_actors_get_by_uuid( $nohandle->get_uuid() );
	}
	ax_profile_assert( $ax_profile_results, 'public status without a registered handle is not publicly viewable', $nohandle instanceof Axismundi_Actor && ! axismundi_actors_is_public_profile( $nohandle ) && ! axismundi_actors_can_view( $nohandle, 0 ) );

	$GLOBALS['axismundi_actors_current_actor'] = $public_actor;
	$rendered = render_block( array( 'blockName' => 'axismundi/actor-profile', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
	ax_profile_assert( $ax_profile_results, 'profile block renders identity data without exposing email', false !== strpos( $rendered, 'Alice Profile' ) && false !== strpos( $rendered, 'A live profile summary.' ) && false === strpos( $rendered, 'alice-private@example.test' ) );
	$title_parts = axismundi_actors_document_title_parts( array( 'title' => 'Fallback' ) );
	ax_profile_assert( $ax_profile_results, 'document title uses the resolved actor display name', 'Alice Profile' === $title_parts['title'] );

	ob_start();
	axismundi_actors_print_canonical();
	$canonical = (string) ob_get_clean();
	ax_profile_assert( $ax_profile_results, 'canonical link always uses the UUID identity URI', false !== strpos( $canonical, esc_url( $original_uri ) ) && false === strpos( $canonical, '/@' ) );

	$rules = axismundi_actors_rewrite_rules();
	ax_profile_assert( $ax_profile_results, 'canonical and human alias rewrite rules are both registered', isset( $rules['^actors/([0-9a-fA-F-]{36})/?$'], $rules['^@([^/]+)/?$'] ) );
} finally {
	$GLOBALS['axismundi_actors_current_actor'] = null;
	update_option( 'permalink_structure', $ax_old_permalink );
	foreach ( array_unique( $ax_profile_ids ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( $ax_profile_users as $fixture_user_id ) {
		if ( get_userdata( $fixture_user_id ) ) {
			wp_delete_user( $fixture_user_id );
		}
	}
}

$ax_profile_failures = count( array_filter( $ax_profile_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_profile_results ), $ax_profile_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_profile_failures > 0 ? 1 : 0 );
}
exit( $ax_profile_failures > 0 ? 1 : 0 );
