<?php
/**
 * Cached remote Object human-view route and Tombstone template regression.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

if ( ! function_exists( 'axismundi_op_render_remote_object_detail' ) ) {
	require_once dirname( __DIR__ ) . '/includes/admin.php';
}

global $wpdb;
$ax_ovr_results = array();
$ax_ovr_uris    = array();
$ax_ovr_actor_id = 0;
$ax_ovr_get     = $_GET;
$ax_ovr_server  = $_SERVER;
$ax_ovr_user_id = get_current_user_id();

/** @param bool[] $results Results. */
function ax_ovr_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Run one synthetic cached-Object request through the route claim. */
function ax_ovr_route( string $hash ) : array {
	$_GET                      = array( 'ax_object' => $hash );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	unset( $_SERVER['HTTP_ACCEPT'] );
	$GLOBALS['axismundi_op_object_html_route'] = null;
	axismundi_op_set_current_object_view_model( null );
	$query                = new WP_Query();
	$query->posts         = array( (object) array( 'ID' => 999 ) );
	$query->post          = $query->posts[0];
	$query->post_count    = 1;
	$query->found_posts   = 1;
	$query->is_home       = true;
	$query->is_front_page = true;
	$handled              = axismundi_op_handle_object_html_request( false, $query );
	return array( 'handled' => $handled, 'query' => $query, 'route' => axismundi_op_object_html_route(), 'model' => axismundi_op_current_object_view_model() );
}

try {
	$active_uri = 'https://remote.example/objects/' . strtolower( wp_generate_password( 8, false, false ) );
	$gone_uri   = 'https://remote.example/objects/' . strtolower( wp_generate_password( 8, false, false ) );
	$private_uri = 'https://remote.example/objects/' . strtolower( wp_generate_password( 8, false, false ) );
	$actor_uri   = 'https://example.com/users/' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_ovr_uris = array( $active_uri, $gone_uri, $private_uri );
	$actor       = axismundi_actors_upsert_remote(
		array(
			'uri'                => $actor_uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'cached_alice',
			'display_name'       => 'Cached Alice',
			'profile_url'        => 'https://example.com/@cached_alice',
			'endpoints'          => array(
				'inbox'  => $actor_uri . '/inbox',
				'outbox' => $actor_uri . '/outbox',
			),
			'payload'            => array(
				'id'                => $actor_uri,
				'type'              => 'Person',
				'preferredUsername' => 'cached_alice',
				'name'              => 'Cached Alice',
			),
		)
	);
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_ovr_actor_id = $actor->get_identity_id();
	}
	$active = axismundi_op_remote_object_store(
		array(
			'id'           => $active_uri,
			'type'         => 'Note',
			'attributedTo' => $actor_uri,
			'to'           => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'content'      => '<p>Cached human view.</p>',
		)
	);
	$gone = axismundi_op_remote_object_store( array( 'id' => $gone_uri, 'type' => 'Tombstone', 'formerType' => 'Note' ) );
	$private = axismundi_op_remote_object_store(
		array(
			'id'           => $private_uri,
			'type'         => 'Note',
			'attributedTo' => 'https://remote.example/users/alice',
			'to'           => array( 'https://remote.example/users/alice/followers' ),
			'content'      => '<p>Followers only.</p>',
		)
	);
	$active_hash = hash( 'sha256', $active_uri );
	$gone_hash   = hash( 'sha256', $gone_uri );

	ax_ovr_assert( $ax_ovr_results, 'the local cached view uses a stable opaque hash while preserving the remote identity outside the URL', is_array( $active ) && is_array( $gone ) && axismundi_op_cached_object_view_url( $active_uri ) === add_query_arg( 'ax_object', $active_hash, home_url( '/' ) ) );
	$active_route = ax_ovr_route( $active_hash );
	ax_ovr_assert( $ax_ovr_results, 'an active cached Object returns 200, binds the remote view model, and clears the fallback home loop', true === $active_route['handled'] && 200 === (int) $active_route['route']['status'] && $active_uri === (string) ( $active_route['model']['id'] ?? '' ) && array() === $active_route['query']->posts && ! $active_route['query']->is_home && ! $active_route['query']->is_singular && ! $active_route['query']->is_404 );
	$active_pattern = axismundi_op_render_object_pattern();
	$legacy_avatar  = WP_Block_Type_Registry::get_instance()->get_registered( 'axismundi/object-avatar' );
	$legacy_identity = WP_Block_Type_Registry::get_instance()->get_registered( 'axismundi/object-identity' );
	// Name and handle are separate Actors-owned blocks so each can carry its own
	// typography. The composite and the legacy aliases stay registered for
	// compatibility but out of the inserter.
	$legacy_composite = WP_Block_Type_Registry::get_instance()->get_registered( 'axismundi/actor-identity' );
	ax_ovr_assert(
		$ax_ovr_results,
		'a cached Object resolves its cached remote Actor through the shared Actors avatar, name, and handle blocks while legacy aliases stay out of the inserter',
		$actor instanceof Axismundi_Actor
			&& false !== strpos( $active_pattern, 'wp-block-axismundi-actor-avatar' )
			&& false !== strpos( $active_pattern, 'wp-block-axismundi-actor-name' )
			&& false !== strpos( $active_pattern, 'wp-block-axismundi-actor-handle' )
			&& false !== strpos( $active_pattern, 'Cached Alice' )
			&& false !== strpos( $active_pattern, 'is-compact' )
			&& is_object( $legacy_composite )
			&& is_object( $legacy_avatar )
			&& is_object( $legacy_identity )
			&& empty( $legacy_avatar->supports['inserter'] )
			&& empty( $legacy_identity->supports['inserter'] )
	);
	ax_ovr_assert( $ax_ovr_results, 'the cached view keeps the remote Object URI canonical and remains out of search indexes', false !== strpos( axismundi_op_object_view_document_title( 'fallback' ), 'Object' ) && ! empty( axismundi_op_object_view_robots( array() )['noindex'] ) );

	$tombstone_template = axismundi_op_tombstone_template_content();
	ax_ovr_assert( $ax_ovr_results, 'the dedicated Tombstone template is privacy-minimal and excludes identity, content, interactions, poll, and replies', false !== strpos( $tombstone_template, 'wp:axismundi/object-status' ) && false === strpos( $tombstone_template, 'object-identity' ) && false === strpos( $tombstone_template, 'object-content' ) && false === strpos( $tombstone_template, 'object-interactions' ) && false === strpos( $tombstone_template, 'wp:axismundi/question' ) && false === strpos( $tombstone_template, 'wp:axismundi/replies' ) );
	$gone_route = ax_ovr_route( $gone_hash );
	$gone_robots = axismundi_op_object_view_robots( array() );
	ax_ovr_assert( $ax_ovr_results, 'a cached Tombstone preserves the URI as a noindex 410 document rather than becoming a no-results or 404 response', 410 === (int) $gone_route['route']['status'] && 'tombstone' === (string) ( $gone_route['model']['status'] ?? '' ) && ! $gone_route['query']->is_404 && ! empty( $gone_robots['noindex'] ) && ! empty( $gone_robots['nofollow'] ) );
	$unknown_route = ax_ovr_route( str_repeat( 'f', 64 ) );
	ax_ovr_assert( $ax_ovr_results, 'an unknown cache identity remains a real empty 404', 404 === (int) $unknown_route['route']['status'] && $unknown_route['query']->is_404 && array() === $unknown_route['query']->posts && null === $unknown_route['model'] );
	$private_route = ax_ovr_route( hash( 'sha256', $private_uri ) );
	ax_ovr_assert( $ax_ovr_results, 'a cached followers-only Object is not promoted into an anonymous standalone human view', is_array( $private ) && 404 === (int) $private_route['route']['status'] && $private_route['query']->is_404 && null === $private_route['model'] );

	ob_start();
	axismundi_op_render_remote_object_detail( (array) axismundi_op_remote_object_get( $active_uri ) );
	$admin_html = (string) ob_get_clean();
	ax_ovr_assert( $ax_ovr_results, 'the Remote Objects inspector exposes the local human View route separately from the remote source page', false !== strpos( $admin_html, esc_url( axismundi_op_cached_object_view_url( $active_uri ) ) ) && false !== strpos( $admin_html, 'View' ) );
	wp_set_current_user( 1 );
	$_GET = array();
	ob_start();
	axismundi_op_render_remote_admin_page();
	$list_html = (string) ob_get_clean();
	ax_ovr_assert( $ax_ovr_results, 'the Remote Objects list offers View only for public active Objects and Tombstones', false !== strpos( $list_html, esc_url( axismundi_op_cached_object_view_url( $active_uri ) ) ) && false !== strpos( $list_html, esc_url( axismundi_op_cached_object_view_url( $gone_uri ) ) ) && false === strpos( $list_html, esc_url( axismundi_op_cached_object_view_url( $private_uri ) ) ) );
} finally {
	$_GET    = $ax_ovr_get;
	$_SERVER = $ax_ovr_server;
	$GLOBALS['axismundi_op_object_html_route'] = null;
	axismundi_op_set_current_object_view_model( null );
	wp_set_current_user( $ax_ovr_user_id );
	foreach ( $ax_ovr_uris as $uri ) {
		$wpdb->delete( axismundi_op_remote_objects_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( $ax_ovr_actor_id > 0 ) {
		$wpdb->delete( axismundi_actors_endpoints_table(), array( 'identity_id' => $ax_ovr_actor_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_ovr_actor_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_ovr_actor_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
}

$ax_ovr_failures = count( array_filter( $ax_ovr_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ovr_results ), $ax_ovr_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ovr_failures > 0 ? 1 : 0 );
}
exit( $ax_ovr_failures > 0 ? 1 : 0 );
