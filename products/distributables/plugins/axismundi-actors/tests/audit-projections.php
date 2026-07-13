<?php
/**
 * Phase 3 projection registry regression (dev-only; dist-excluded).
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
	axismundi_actors_register_handle( $actor->get_identity_id(), 'projection-author' );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$actor = axismundi_actors_get_by_uuid( $actor->get_uuid() );

	$published = wp_insert_post( array( 'post_title' => 'Public projection post', 'post_status' => 'publish', 'post_type' => 'post', 'post_author' => $user_id ) );
	$draft     = wp_insert_post( array( 'post_title' => 'Draft projection post', 'post_status' => 'draft', 'post_type' => 'post', 'post_author' => $user_id ) );
	$ax_projection_posts[] = (int) $published;
	$ax_projection_posts[] = (int) $draft;

	axismundi_actors_load_projections();
	$base = axismundi_actors_get_projections( $actor, 0 );
	ax_projection_assert( $ax_projection_results, 'built-in Posts projection appears for a public actor with published posts', 1 === count( $base ) && 'posts' === $base[0]['id'] );
	ax_projection_assert( $ax_projection_results, 'Posts URL uses the core author archive', get_author_posts_url( $user_id ) === $base[0]['url'] );
	ax_projection_assert( $ax_projection_results, 'Posts count includes published posts only', 1 === $base[0]['count'] );

	$empty_user = (int) wp_insert_user( array( 'user_login' => 'ax_projection_empty', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_projection_users[] = $empty_user;
	$empty_actor = axismundi_actors_ensure_for_user( $empty_user );
	$ax_projection_ids[] = $empty_actor->get_identity_id();
	axismundi_actors_register_handle( $empty_actor->get_identity_id(), 'projection-empty' );
	axismundi_actors_set_status( $empty_actor->get_identity_id(), 'public' );
	$empty_actor = axismundi_actors_get_by_uuid( $empty_actor->get_uuid() );
	ax_projection_assert( $ax_projection_results, 'Posts projection is hidden when no readable posts exist', array() === axismundi_actors_get_projections( $empty_actor, 0 ) );

	$external_register = static function () : void {
		axismundi_actors_register_projection(
			'beta',
			array(
				'label'        => 'Beta',
				'url_callback' => static fn( Axismundi_Actor $actor ) : string => home_url( '/beta/' . $actor->get_uuid() ),
				'priority'     => 5,
			)
		);
		axismundi_actors_register_projection(
			'alpha',
			array(
				'label'            => 'Alpha',
				'url_callback'     => static fn() : string => home_url( '/alpha/' ),
				'visible_callback' => static fn() : bool => true,
				'count_callback'   => static fn() : int => 7,
				'priority'         => 30,
			)
		);
		axismundi_actors_register_projection( 'hidden', array( 'label' => 'Hidden', 'url_callback' => static fn() : string => home_url( '/hidden/' ), 'visible_callback' => static fn() : bool => false ) );
		axismundi_actors_register_projection( 'empty-url', array( 'label' => 'Empty', 'url_callback' => static fn() : string => '' ) );
	};
	add_action( 'axismundi_actors_register_projections', $external_register );
	axismundi_actors_load_projections();
	$with_external = axismundi_actors_get_projections( $actor, 0 );
	$external_ids = array_column( $with_external, 'id' );
	ax_projection_assert( $ax_projection_results, 'a plugin registers projections only through the public hook and API', in_array( 'alpha', $external_ids, true ) && in_array( 'beta', $external_ids, true ) );
	ax_projection_assert( $ax_projection_results, 'priority orders beta before Posts and alpha', array( 'beta', 'posts', 'alpha' ) === $external_ids );
	ax_projection_assert( $ax_projection_results, 'false visibility and empty URL projections are omitted', ! in_array( 'hidden', $external_ids, true ) && ! in_array( 'empty-url', $external_ids, true ) );
	$alpha = $with_external[ array_search( 'alpha', $external_ids, true ) ];
	ax_projection_assert( $ax_projection_results, 'optional count callback is exposed after visibility resolution', 7 === $alpha['count'] );

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

	remove_action( 'axismundi_actors_register_projections', $external_register );
	axismundi_actors_load_projections();
	ax_projection_assert( $ax_projection_results, 'deactivating a registering plugin removes its projections next request', array( 'posts' ) === array_column( axismundi_actors_get_projections( $actor, 0 ), 'id' ) );

	axismundi_actors_set_status( $actor->get_identity_id(), 'internal' );
	$internal_actor = axismundi_actors_get_by_uuid( $actor->get_uuid() );
	ax_projection_assert( $ax_projection_results, 'an internal actor exposes no projections to an anonymous viewer', array() === axismundi_actors_get_projections( $internal_actor, 0 ) );
	wp_set_current_user( $user_id );
	ax_projection_assert( $ax_projection_results, 'the owner preview may resolve their internal actor projections', array( 'posts' ) === array_column( axismundi_actors_get_projections( $internal_actor, $user_id ), 'id' ) );
	wp_set_current_user( 0 );

	$GLOBALS['axismundi_actors_current_actor'] = $actor;
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$actor = axismundi_actors_get_by_uuid( $actor->get_uuid() );
	$GLOBALS['axismundi_actors_current_actor'] = $actor;
	$rendered = render_block( array( 'blockName' => 'axismundi/actor-projections', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
	ax_projection_assert( $ax_projection_results, 'projection block renders an accessible Posts link and count', false !== strpos( $rendered, 'aria-label="Actor profiles"' ) && false !== strpos( $rendered, '>Posts<' ) && false !== strpos( $rendered, '>1<' ) );
} finally {
	$GLOBALS['axismundi_actors_current_actor'] = null;
	wp_set_current_user( 0 );
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
