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

/** Create one fixture Note, optionally leaving its local Post as a draft. */
function ax_nh_note( int $author_id, string $visibility, array &$posts, bool $publish = true ) : array {
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
	if ( $publish ) {
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	}
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
	$draft     = ax_nh_note( $author_id, 'public', $ax_nh_post_ids, false );
	$public_id = axismundi_note_object_uri( (string) $public['local_uuid'] );
	$private_id = axismundi_note_object_uri( (string) $private['local_uuid'] );
	$draft_id  = axismundi_note_object_uri( (string) $draft['local_uuid'] );
	$public_post = get_post( (int) $public['post_id'] );
	$private_post = get_post( (int) $private['post_id'] );
	$draft_post = get_post( (int) $draft['post_id'] );
	$public_actions = $public_post instanceof WP_Post ? axismundi_note_admin_view_row_action( array(), $public_post ) : array();
	$private_actions = $private_post instanceof WP_Post ? axismundi_note_admin_view_row_action( array(), $private_post ) : array();
	$draft_actions = $draft_post instanceof WP_Post ? axismundi_note_admin_view_row_action( array(), $draft_post ) : array();
	ax_nh_assert( $ax_nh_results, 'the private Note list adds View only for canonical documents anonymous visitors may read', isset( $public_actions['view'] ) && false !== strpos( $public_actions['view'], esc_url( $public_id ) ) && ! isset( $private_actions['view'] ) && ! isset( $draft_actions['view'] ) );

	$template = function_exists( 'get_block_template' ) ? get_block_template( 'axismundi-object-projections//single-object', 'wp_template' ) : null;
	$content  = function_exists( 'axismundi_op_single_object_template_content' ) ? axismundi_op_single_object_template_content() : '';
	ax_nh_assert( $ax_nh_results, 'Object Projections registers the editable single-Object template that the Note route reuses', $template instanceof WP_Block_Template && 'plugin' === $template->source && false !== strpos( $content, 'wp:axismundi/object-content' ) && false !== strpos( $content, 'wp:axismundi/replies' ) && false === strpos( $content, 'wp:query' ) && false === strpos( $content, 'wp:post-content' ) );
	$tombstone_content = function_exists( 'axismundi_op_tombstone_template_content' ) ? axismundi_op_tombstone_template_content() : '';
	ax_nh_assert( $ax_nh_results, 'Object Projections owns a separate privacy-minimal Tombstone template for local and cached remote 410 routes', false !== strpos( $tombstone_content, 'wp:axismundi/object-status' ) && false === strpos( $tombstone_content, 'wp:axismundi/object-content' ) && false === strpos( $tombstone_content, 'wp:axismundi/replies' ) );

	$public_route = ax_nh_route( $public_id, array( 'ax_note' => (string) $public['local_uuid'] ) );
	$public_query = $public_route['query'];
	ax_nh_assert( $ax_nh_results, 'an exact public Note route returns 200 and clears the fallback home loop without faking a singular Post', true === $public_route['handled'] && 200 === (int) $public_route['route']['status'] && is_array( $public_route['model'] ) && array() === $public_query->posts && ! $public_query->is_home && ! $public_query->is_singular && ! $public_query->is_404 );

	$active_html    = axismundi_op_render_object_view_block();
	$pattern_html   = axismundi_op_render_object_pattern();
	ax_nh_assert( $ax_nh_results, 'the active human view renders title and content, while the editable Object pattern owns nested Reply, Like, and Repost controls', false !== strpos( $active_html, 'Optional Note title' ) && 'Optional Note title' === axismundi_note_object_document_title( 'fallback' ) && false !== strpos( $active_html, 'Human Note route.' ) && false !== strpos( $active_html, 'axismundi-reply-button' ) && false !== strpos( $active_html, 'axismundi-like-button' ) && false !== strpos( $active_html, 'axismundi-announce-button' ) && false !== strpos( $pattern_html, 'axismundi-object__interactions' ) && false !== strpos( $pattern_html, 'axismundi-reply-button' ) && false !== strpos( $pattern_html, 'axismundi-like-button' ) && false !== strpos( $pattern_html, 'axismundi-announce-button' ) );

	$like_target = axismundi_act_resolve_like_target( $public_id );
	$boost_target = axismundi_act_resolve_announce_target( $public_id );
	ax_nh_assert( $ax_nh_results, 'Like and Announce resolve the same exact public Note and frozen recipient Actor without network access', is_array( $like_target ) && is_array( $boost_target ) && $public_id === $like_target['object_uri'] && $actor->get_uri() === $like_target['recipient_uri'] && $actor->get_uri() === $boost_target['recipient_uri'] );
	wp_set_current_user( $author_id );
	$reply_button = do_blocks( '<!-- wp:axismundi/reply-button {"objectUri":"' . esc_url_raw( $public_id ) . '"} /-->' );
	ax_nh_assert( $ax_nh_results, 'the authenticated Reply control opens the Note editor with the canonical parent URI prefilled', false !== strpos( $reply_button, 'axismundi-reply-button' ) && false !== strpos( $reply_button, 'ax_reply_to=' ) && false !== strpos( $reply_button, $public_id ) && false !== strpos( $reply_button, '>reply<' ) );
	$announce_menu = do_blocks( '<!-- wp:axismundi/announce-button {"objectUri":"' . esc_url_raw( $public_id ) . '"} /-->' );
	ax_nh_assert( $ax_nh_results, 'the authenticated Announce control opens a Dialogs menu with distinct repost and Quote commands', false !== strpos( $announce_menu, 'data-wp-interactive="axismundi/announce-button"' ) && false !== strpos( $announce_menu, 'ax-interaction-dialog' ) && false !== strpos( $announce_menu, 'ax_quote_target' ) && false !== strpos( $announce_menu, 'Quote</a>' ) );
	wp_set_current_user( 0 );

	$private_route = ax_nh_route( $private_id, array( 'ax_note' => (string) $private['local_uuid'] ) );
	$private_query = $private_route['query'];
	$private_target = axismundi_note_reaction_target( $private_id );
	ax_nh_assert( $ax_nh_results, 'followers-only Note HTML and reactions fail closed as an empty 404', 404 === (int) $private_route['route']['status'] && $private_query->is_404 && array() === $private_query->posts && null === $private_route['model'] && is_wp_error( $private_target ) );

	wp_set_current_user( $author_id );
	$owner_actions = axismundi_note_admin_view_row_action( array(), $private_post );
	$owner_route   = ax_nh_route( $private_id, array( 'ax_note' => (string) $private['local_uuid'] ) );
	$owner_robots  = axismundi_note_object_robots( array() );
	ax_nh_assert(
		$ax_nh_results,
		'the author can preview their own not-yet-public Note: a row-action link is offered, the route returns 200 instead of 404, and the preview stays out of indexes',
		isset( $owner_actions['view'] ) && false !== strpos( $owner_actions['view'], esc_url( $private_id ) )
			&& 200 === (int) $owner_route['route']['status'] && is_array( $owner_route['model'] )
			&& ! empty( $owner_robots['noindex'] ) && ! empty( $owner_robots['nofollow'] )
	);

	$other_actor = ax_nh_actor( $ax_nh_user_ids, $ax_nh_actor_ids );
	$other_id    = $other_actor instanceof Axismundi_Actor ? (int) $other_actor->get_local_user_id() : 0;
	wp_set_current_user( $other_id );
	$stranger_actions = axismundi_note_admin_view_row_action( array(), $private_post );
	$stranger_route   = ax_nh_route( $private_id, array( 'ax_note' => (string) $private['local_uuid'] ) );
	ax_nh_assert(
		$ax_nh_results,
		'a different logged-in Author cannot preview someone else\'s not-yet-public Note',
		! isset( $stranger_actions['view'] ) && 404 === (int) $stranger_route['route']['status']
	);
	wp_set_current_user( $author_id );
	$draft_actions = $draft_post instanceof WP_Post ? axismundi_note_admin_view_row_action( array(), $draft_post ) : array();
	$draft_route   = ax_nh_route( $draft_id, array( 'ax_note' => (string) $draft['local_uuid'] ) );
	$draft_robots  = axismundi_note_object_robots( array() );
	ax_nh_assert(
		$ax_nh_results,
		'the author gets a Preview action for an envelope-backed draft and the draft route renders only as a noindex owner preview',
		isset( $draft_actions['view'] ) && false !== strpos( $draft_actions['view'], esc_url( $draft_id ) ) && false !== strpos( $draft_actions['view'], 'Preview' )
			&& 200 === (int) $draft_route['route']['status'] && is_array( $draft_route['model'] )
			&& ! empty( $draft_robots['noindex'] ) && ! empty( $draft_robots['nofollow'] )
	);
	wp_set_current_user( 0 );

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
