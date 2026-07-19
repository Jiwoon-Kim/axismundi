<?php
/**
 * Note human HTML route, template, and interaction regression (dev-only).
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_nh_results   = array();
$ax_nh_post_ids  = array();
$ax_nh_user_ids  = array();
$ax_nh_actor_ids = array();
$ax_nh_get       = $_GET;
$ax_nh_server    = $_SERVER;

/** @param bool[] $results Results. */
function ax_nh_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Create one public local Actor with a locked handle. */
function ax_nh_actor( array &$users, array &$actors ) : ?Axismundi_Actor {
	$login = 'ax_nh_' . strtolower( wp_generate_password( 8, false, false ) );
	$uid   = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	if ( $uid <= 0 ) {
		return null;
	}
	$users[] = $uid;
	$actor   = axismundi_actors_ensure_for_user( $uid );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return null;
	}
	$actors[] = $actor->get_identity_id();
	axismundi_actors_register_handle( $actor->get_identity_id(), $login );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	axismundi_actors_set_default_language( $actor->get_identity_id(), 'en' );
	return axismundi_actors_get_by_uri( $actor->get_uri() );
}

/** Create and publish one fixture Note. */
function ax_nh_note( int $author_id, string $visibility, array &$posts ) : array {
	$post_id = (int) wp_insert_post(
		array(
			'post_type'    => AXISMUNDI_NOTE_POST_TYPE,
			'post_status'  => 'draft',
			'post_author'  => $author_id,
			'post_title'   => 'Optional Note title',
			'post_content' => '<!-- wp:paragraph --><p>Human Note route.</p><!-- /wp:paragraph -->',
		)
	);
	$posts[] = $post_id;
	axismundi_note_save_envelope( $post_id, array( 'visibility' => $visibility ) );
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	return (array) axismundi_note_get( $post_id );
}

/** Run one synthetic main-query request through the route claim. */
function ax_nh_route( string $uri, array $get ) : array {
	$_GET                     = $get;
	$_SERVER['REQUEST_URI']   = (string) wp_parse_url( $uri, PHP_URL_PATH ) . ( wp_parse_url( $uri, PHP_URL_QUERY ) ? '?' . wp_parse_url( $uri, PHP_URL_QUERY ) : '' );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	unset( $_SERVER['HTTP_ACCEPT'] );
	$GLOBALS['axismundi_note_html_route'] = null;
	axismundi_op_set_current_object_view_model( null );
	$query                  = new WP_Query();
	$query->posts           = array( (object) array( 'ID' => 999 ) );
	$query->post            = $query->posts[0];
	$query->post_count      = 1;
	$query->found_posts     = 1;
	$query->is_home         = true;
	$query->is_front_page   = true;
	$handled                = axismundi_note_handle_html_request( false, $query );
	return array( 'handled' => $handled, 'query' => $query, 'route' => axismundi_note_html_route(), 'model' => axismundi_op_current_object_view_model() );
}

try {
	$actor     = ax_nh_actor( $ax_nh_user_ids, $ax_nh_actor_ids );
	$author_id = $actor instanceof Axismundi_Actor ? (int) $actor->get_local_user_id() : 0;
	$public    = ax_nh_note( $author_id, 'public', $ax_nh_post_ids );
	$private   = ax_nh_note( $author_id, 'followers', $ax_nh_post_ids );
	$public_id = axismundi_note_object_uri( (string) $public['local_uuid'] );
	$private_id = axismundi_note_object_uri( (string) $private['local_uuid'] );

	$template = function_exists( 'get_block_template' ) ? get_block_template( 'axismundi-note//axismundi-note-object', 'wp_template' ) : null;
	$content  = axismundi_note_object_template_content();
	ax_nh_assert( $ax_nh_results, 'Note registers one plugin-owned functional block template containing only the neutral object view', $template instanceof WP_Block_Template && false !== strpos( $content, 'wp:axismundi/object-view' ) && false === strpos( $content, 'wp:post-content' ) );

	$public_route = ax_nh_route( $public_id, array( 'ax_note' => (string) $public['local_uuid'] ) );
	$public_query = $public_route['query'];
	ax_nh_assert( $ax_nh_results, 'an exact public Note route returns 200 and clears the fallback home loop without faking a singular Post', true === $public_route['handled'] && 200 === (int) $public_route['route']['status'] && is_array( $public_route['model'] ) && array() === $public_query->posts && ! $public_query->is_home && ! $public_query->is_singular && ! $public_query->is_404 );

	$active_html = axismundi_op_render_object_view_block();
	ax_nh_assert( $ax_nh_results, 'the active human view conditionally renders its title, content, Like and Boost but no Quote affordance', false !== strpos( $active_html, 'Optional Note title' ) && 'Optional Note title' === axismundi_note_object_document_title( 'fallback' ) && false !== strpos( $active_html, 'Human Note route.' ) && false !== strpos( $active_html, 'axismundi-like-button' ) && false !== strpos( $active_html, 'axismundi-boost-button' ) && false === stripos( $active_html, 'quote' ) );

	$like_target = axismundi_act_resolve_like_target( $public_id );
	$boost_target = axismundi_act_resolve_announce_target( $public_id );
	ax_nh_assert( $ax_nh_results, 'Like and Announce resolve the same exact public Note and frozen recipient Actor without network access', is_array( $like_target ) && is_array( $boost_target ) && $public_id === $like_target['object_uri'] && $actor->get_uri() === $like_target['recipient_uri'] && $actor->get_uri() === $boost_target['recipient_uri'] );

	$private_route = ax_nh_route( $private_id, array( 'ax_note' => (string) $private['local_uuid'] ) );
	$private_query = $private_route['query'];
	$private_target = axismundi_note_reaction_target( $private_id );
	ax_nh_assert( $ax_nh_results, 'followers-only Note HTML and reactions fail closed as an empty 404', 404 === (int) $private_route['route']['status'] && $private_query->is_404 && array() === $private_query->posts && null === $private_route['model'] && is_wp_error( $private_target ) );

	$alias_route = ax_nh_route( $public_id . '&extra=1', array( 'ax_note' => (string) $public['local_uuid'], 'extra' => '1' ) );
	$unknown_uuid = wp_generate_uuid4();
	$unknown_id   = axismundi_note_object_uri( $unknown_uuid );
	$unknown_route = ax_nh_route( $unknown_id, array( 'ax_note' => $unknown_uuid ) );
	ax_nh_assert( $ax_nh_results, 'aliases and unknown UUIDs converge on the same empty 404 instead of exposing the home query', 404 === (int) $alias_route['route']['status'] && $alias_route['query']->is_404 && array() === $alias_route['query']->posts && 404 === (int) $unknown_route['route']['status'] && $unknown_route['query']->is_404 && array() === $unknown_route['query']->posts );

	$untouched = ax_nh_route( home_url( '/?p=1' ), array( 'p' => '1' ) );
	ax_nh_assert( $ax_nh_results, 'a non-Note Article request is not claimed or rewritten', false === $untouched['handled'] && null === $untouched['route'] && true === $untouched['query']->is_home && 1 === $untouched['query']->post_count );

	wp_delete_post( (int) $public['post_id'], true );
	$tomb_route = ax_nh_route( $public_id, array( 'ax_note' => (string) $public['local_uuid'] ) );
	$tomb_query = $tomb_route['query'];
	$tomb_html  = axismundi_op_render_object_view_block();
	$tomb_target = axismundi_note_reaction_target( $public_id );
	$robots     = axismundi_note_object_robots( array() );
	ax_nh_assert( $ax_nh_results, 'a Post-less Tombstone returns 410 with a minimal non-interactive view and remains out of indexes', 410 === (int) $tomb_route['route']['status'] && array() === $tomb_query->posts && ! $tomb_query->is_singular && false !== strpos( $tomb_html, 'has been deleted' ) && false === strpos( $tomb_html, 'axismundi-like-button' ) && is_wp_error( $tomb_target ) && ! empty( $robots['noindex'] ) );
} finally {
	$_GET    = $ax_nh_get;
	$_SERVER = $ax_nh_server;
	$GLOBALS['axismundi_note_html_route'] = null;
	axismundi_op_set_current_object_view_model( null );
	foreach ( array_unique( $ax_nh_post_ids ) as $pid ) {
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $pid ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( get_post( (int) $pid ) instanceof WP_Post ) {
			wp_delete_post( (int) $pid, true );
		}
	}
	foreach ( array_unique( $ax_nh_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( ! empty( $ax_nh_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_nh_user_ids ) as $uid ) {
			if ( get_userdata( (int) $uid ) ) {
				wp_delete_user( (int) $uid );
			}
		}
	}
}

$ax_nh_failures = count( array_filter( $ax_nh_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_nh_results ), $ax_nh_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_nh_failures > 0 ? 1 : 0 );
}
exit( $ax_nh_failures > 0 ? 1 : 0 );
