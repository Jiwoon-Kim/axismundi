<?php
/**
 * Phase 5a post lifecycle emitter regression (dev-only).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_lifecycle_results = array();
$ax_lifecycle_posts   = array();
$GLOBALS['ax_lifecycle_events'] = array();

/** @param bool[] $results Results. */
function ax_lifecycle_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Observe emitted candidates. */
function ax_lifecycle_observe( WP_Post $post, string $object_uri, string $actor_uri ) : void {
	$GLOBALS['ax_lifecycle_events'][] = array( $post->ID, $object_uri, $actor_uri );
}

try {
	$admins    = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
	$author_id = isset( $admins[0] ) ? (int) $admins[0] : 0;
	$site      = axismundi_actors_get_site_actor();
	$actor_uri = $site instanceof Axismundi_Actor ? $site->get_uri() : '';
	add_filter( 'axismundi_op_post_actor_uri', static fn() : string => $actor_uri );
	add_filter( 'axismundi_op_post_lifecycle_owner', static fn() : string => 'axismundi' );
	add_action( 'axismundi_op_object_publish_candidate', 'ax_lifecycle_observe', 1, 3 );
	remove_action( 'axismundi_op_object_publish_candidate', 'axismundi_act_on_object_publish_candidate', 10 );

	$draft_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'draft', 'post_author' => $author_id, 'post_title' => 'Lifecycle draft' ) );
	$ax_lifecycle_posts[] = $draft_id;
	ax_lifecycle_assert( $ax_lifecycle_results, 'draft creation emits no publish candidate', is_int( $draft_id ) && array() === $GLOBALS['ax_lifecycle_events'] );

	wp_update_post( array( 'ID' => $draft_id, 'post_status' => 'publish' ) );
	$first = $GLOBALS['ax_lifecycle_events'][0] ?? array();
	ax_lifecycle_assert( $ax_lifecycle_results, 'a committed public Core Post emits its object and Actor URI once', 1 === count( $GLOBALS['ax_lifecycle_events'] ) && $draft_id === (int) ( $first[0] ?? 0 ) && axismundi_op_post_object_uri( get_post( $draft_id ) ) === ( $first[1] ?? '' ) && $actor_uri === ( $first[2] ?? '' ) );

	$password_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $author_id, 'post_title' => 'Lifecycle locked', 'post_password' => 'secret' ) );
	$page_id     = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_author' => $author_id, 'post_title' => 'Lifecycle page' ) );
	$attachment  = wp_insert_attachment( array( 'post_title' => 'Lifecycle attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_author' => $author_id ) );
	$ax_lifecycle_posts = array_merge( $ax_lifecycle_posts, array( $password_id, $page_id, $attachment ) );
	ax_lifecycle_assert( $ax_lifecycle_results, 'password posts, pages, and attachments never emit Core Post publish candidates', 1 === count( $GLOBALS['ax_lifecycle_events'] ) );

	add_filter( 'axismundi_op_post_lifecycle_owner', static fn() : string => 'official-activitypub', 99 );
	$gated_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $author_id, 'post_title' => 'Lifecycle official owner' ) );
	$ax_lifecycle_posts[] = $gated_id;
	ax_lifecycle_assert( $ax_lifecycle_results, 'a different lifecycle owner suppresses Axismundi emission to prevent two Create activities', 1 === count( $GLOBALS['ax_lifecycle_events'] ) );
} finally {
	remove_action( 'axismundi_op_object_publish_candidate', 'ax_lifecycle_observe', 1 );
	if ( function_exists( 'axismundi_act_on_object_publish_candidate' ) ) {
		add_action( 'axismundi_op_object_publish_candidate', 'axismundi_act_on_object_publish_candidate', 10, 3 );
	}
	remove_all_filters( 'axismundi_op_post_actor_uri' );
	remove_all_filters( 'axismundi_op_post_lifecycle_owner' );
	foreach ( array_filter( $ax_lifecycle_posts, 'is_int' ) as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

$ax_lifecycle_failures = count( array_filter( $ax_lifecycle_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_lifecycle_results ), $ax_lifecycle_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_lifecycle_failures > 0 ? 1 : 0 );
}
exit( $ax_lifecycle_failures > 0 ? 1 : 0 );
