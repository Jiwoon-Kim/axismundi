<?php
/**
 * Phase 3 projection registry regression (dev-only; dist-excluded).
 *
 * Actors ships NO built-in projection: the profile's primary surface is an activity
 * feed owned by Axismundi Activities, and Articles / Notes / Media are registered by
 * their own plugins. These checks exercise the registry with test projections
 * registered through the public hook, exactly as a domain plugin would.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/routing.php';
require_once dirname( __DIR__ ) . '/includes/projections.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$ax_projection_results = array();
$ax_projection_ids     = array();
$ax_projection_users   = array();
$ax_projection_posts   = array();

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $condition Holds.
 * @return void
 */
function ax_projection_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_actors_install();
	$user_id = (int) wp_insert_user(
		array(
			'user_login'   => 'ax_projection_author',
			'user_pass'    => wp_generate_password(),
			'display_name' => 'Projection Author',
			'role'         => 'author',
		)
	);
	$ax_projection_users[] = $user_id;
	$actor = axismundi_actors_ensure_for_user( $user_id );
	$ax_projection_ids[] = $actor->get_identity_id();
	axismundi_actors_register_handle( $actor->get_identity_id(), 'projection_author' );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$actor = axismundi_actors_get_by_uuid( $actor->get_uuid() );

	$published = wp_insert_post( array( 'post_title' => 'Public projection post', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => $user_id ) );
	$draft     = wp_insert_post( array( 'post_title' => 'Draft projection post', 'post_status' => 'draft', 'post_type' => 'post', 'post_author' => $user_id ) );
	$ax_projection_posts[] = (int) $published;
	$ax_projection_posts[] = (int) $draft;

	// Actors registers nothing itself: a bare load leaves the profile header-only.
	axismundi_actors_load_projections();
	ax_projection_assert( $ax_projection_results, 'Actors ships no built-in projection (header-only until a plugin registers)', array() === axismundi_actors_get_projections( $actor, 0 ) );

	// A domain plugin registers projections through the public hook. `articles`
	// mirrors the future core-post registrar (author archive + published count).
	$external_register = static function () : void {
		axismundi_actors_register_projection(
			'articles',
			array(
				'label'            => 'Articles',
				'url_callback'     => static fn( Axismundi_Actor $a ) : string => $a->get_local_user_id() ? get_author_posts_url( $a->get_local_user_id() ) : '',
				'visible_callback' => static fn( Axismundi_Actor $a ) : bool => (bool) $a->get_local_user_id() && count_user_posts( $a->get_local_user_id(), 'post', true ) > 0,
				'count_callback'   => static fn( Axismundi_Actor $a ) : ?int => $a->get_local_user_id() ? (int) count_user_posts( $a->get_local_user_id(), 'post', true ) : null,
				'priority'         => 20,
			)
		);
		axismundi_actors_register_projection( 'beta', array( 'label' => 'Beta', 'url_callback' => static fn( Axismundi_Actor $a ) : string => home_url( '/beta/' . $a->get_uuid() ), 'priority' => 5 ) );
		axismundi_actors_register_projection( 'alpha', array( 'label' => 'Alpha', 'url_callback' => static fn() : string => home_url( '/alpha/' ), 'visible_callback' => static fn() : bool => true, 'count_callback' => static fn() : int => 7, 'priority' => 30 ) );
		axismundi_actors_register_projection( 'hidden', array( 'label' => 'Hidden', 'url_callback' => static fn() : string => home_url( '/hidden/' ), 'visible_callback' => static fn() : bool => false ) );
		axismundi_actors_register_projection( 'empty-url', array( 'label' => 'Empty', 'url_callback' => static fn() : string => '' ) );
	};
	add_action( 'axismundi_actors_register_projections', $external_register );
	axismundi_actors_load_projections();
	$with_external = axismundi_actors_get_projections( $actor, 0 );
	$external_ids  = array_column( $with_external, 'id' );
	ax_projection_assert( $ax_projection_results, 'a plugin registers projections only through the public hook and API', in_array( 'alpha', $external_ids, true ) && in_array( 'beta', $external_ids, true ) && in_array( 'articles', $external_ids, true ) );
	ax_projection_assert( $ax_projection_results, 'priority orders beta before articles before alpha', array( 'beta', 'articles', 'alpha' ) === $external_ids );
	ax_projection_assert( $ax_projection_results, 'false visibility and empty URL projections are omitted', ! in_array( 'hidden', $external_ids, true ) && ! in_array( 'empty-url', $external_ids, true ) );
	$articles = $with_external[ array_search( 'articles', $external_ids, true ) ];
	ax_projection_assert( $ax_projection_results, 'a domain projection resolves its own URL and published-only count', get_author_posts_url( $user_id ) === $articles['url'] && 1 === $articles['count'] );
	$alpha = $with_external[ array_search( 'alpha', $external_ids, true ) ];
	ax_projection_assert( $ax_projection_results, 'optional count callback is exposed after visibility resolution', 7 === $alpha['count'] );

	// An actor with no readable posts hides the articles projection.
	$empty_user = (int) wp_insert_user( array( 'user_login' => 'ax_projection_empty', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_projection_users[] = $empty_user;
	$empty_actor = axismundi_actors_ensure_for_user( $empty_user );
	$ax_projection_ids[] = $empty_actor->get_identity_id();
	axismundi_actors_register_handle( $empty_actor->get_identity_id(), 'projection_empty' );
	axismundi_actors_set_status( $empty_actor->get_identity_id(), 'public' );
	$empty_actor = axismundi_actors_get_by_uuid( $empty_actor->get_uuid() );
	$empty_ids   = array_column( axismundi_actors_get_projections( $empty_actor, 0 ), 'id' );
	ax_projection_assert( $ax_projection_results, 'a projection hidden by its own visibility callback is omitted', ! in_array( 'articles', $empty_ids, true ) && in_array( 'beta', $empty_ids, true ) );

	// Duplicate ID warns and replaces.
	$doing_it_wrong = 0;
	$wrong_listener = static function ( string $function ) use ( &$doing_it_wrong ) : void {
		if ( 'axismundi_actors_register_projection' === $function ) {
			++$doing_it_wrong;
		}
	};
	add_action( 'doing_it_wrong_run', $wrong_listener );
	$replaced = axismundi_actors_register_projection( 'alpha', array( 'label' => 'Alpha replacement', 'url_callback' => static fn() : string => home_url( '/alpha-new/' ), 'priority' => 1 ) );
	remove_action( 'doing_it_wrong_run', $wrong_listener );
	$after_replace = axismundi_actors_get_projections( $actor, 0 );
	ax_projection_assert( $ax_projection_results, 'duplicate ID warns and the later definition replaces the first', true === $replaced && 1 === $doing_it_wrong && 'alpha' === $after_replace[0]['id'] && 'Alpha replacement' === $after_replace[0]['label'] );

	$invalid = axismundi_actors_register_projection( 'Bad ID', array( 'label' => 'Bad', 'url_callback' => static fn() : string => '/' ) );
	ax_projection_assert( $ax_projection_results, 'invalid projection IDs are rejected before registration', is_wp_error( $invalid ) );

	// Callback isolation: a throwing callback is skipped and fires the error hook.
	$errors = 0;
	add_action( 'axismundi_actors_projection_error', static function () use ( &$errors ) : void { ++$errors; } );
	add_action( 'axismundi_actors_register_projections', static function () : void {
		axismundi_actors_register_projection( 'boom', array( 'label' => 'Boom', 'url_callback' => static function () : string { throw new RuntimeException( 'boom' ); } ) );
	} );
	axismundi_actors_load_projections();
	$isolated = array_column( axismundi_actors_get_projections( $actor, 0 ), 'id' );
	ax_projection_assert( $ax_projection_results, 'a throwing projection callback is isolated and reported', ! in_array( 'boom', $isolated, true ) && $errors >= 1 );

	// Graceful disappearance: remove every registration → header-only again.
	remove_all_actions( 'axismundi_actors_register_projections' );
	axismundi_actors_load_projections();
	ax_projection_assert( $ax_projection_results, 'removing every registering plugin leaves no projections', array() === axismundi_actors_get_projections( $actor, 0 ) );

	// Internal actor exposes no projections to anon; owner preview may.
	add_action( 'axismundi_actors_register_projections', $external_register );
	axismundi_actors_load_projections();
	axismundi_actors_set_status( $actor->get_identity_id(), 'internal' );
	$internal_actor = axismundi_actors_get_by_uuid( $actor->get_uuid() );
	ax_projection_assert( $ax_projection_results, 'an internal actor exposes no projections to an anonymous viewer', array() === axismundi_actors_get_projections( $internal_actor, 0 ) );
	wp_set_current_user( $user_id );
	ax_projection_assert( $ax_projection_results, 'the owner preview may resolve their internal actor projections', in_array( 'articles', array_column( axismundi_actors_get_projections( $internal_actor, $user_id ), 'id' ), true ) );
	wp_set_current_user( 0 );

	// Navigation block renders the registered projections (no built-in Posts).
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$actor = axismundi_actors_get_by_uuid( $actor->get_uuid() );
	$GLOBALS['axismundi_actors_current_actor'] = $actor;
	$rendered = render_block( array( 'blockName' => 'axismundi/actor-projections', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
	ax_projection_assert( $ax_projection_results, 'projection block renders registered links (Articles) and no built-in Posts', false !== strpos( $rendered, 'aria-label="Actor profiles"' ) && false !== strpos( $rendered, '>Articles<' ) && false === strpos( $rendered, '>Posts<' ) );
} finally {
	$GLOBALS['axismundi_actors_current_actor'] = null;
	wp_set_current_user( 0 );
	remove_all_actions( 'axismundi_actors_register_projections' );
	foreach ( $ax_projection_posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
	foreach ( array_unique( $ax_projection_ids ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( $ax_projection_users as $fixture_user_id ) {
		if ( get_userdata( $fixture_user_id ) ) {
			wp_delete_user( $fixture_user_id );
		}
	}
}

$ax_projection_failures = count( array_filter( $ax_projection_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_projection_results ), $ax_projection_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_projection_failures > 0 ? 1 : 0 );
}
exit( $ax_projection_failures > 0 ? 1 : 0 );
