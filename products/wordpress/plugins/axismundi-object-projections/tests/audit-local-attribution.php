<?php
/**
 * Attribution direction regression (dev-only).
 *
 * Projection asks who published an Object; it does not decide. These checks pin the direction
 * so a later change cannot quietly reintroduce deriving an Actor from `post_author`.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_attr_results = array();
$ax_attr_posts   = array();
$ax_attr_objects = array();
$ax_attr_users   = array();
$ax_attr_ids     = array();

/** @param bool[] $results Results. */
function ax_attr_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$author_id = (int) wp_insert_user( array( 'user_login' => 'ax-attr-' . wp_generate_password( 8, false, false ), 'user_pass' => wp_generate_password( 20 ), 'role' => 'administrator' ) );
	$ax_attr_users[] = $author_id;
	wp_set_current_user( $author_id );
	$actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'local_user_id' => $author_id, 'preferred_username' => 'attr-' . strtolower( wp_generate_password( 8, false, false ) ) ) );
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_attr_ids[] = $actor->get_identity_id();
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
		$actor = axismundi_actors_get_by_identity( $actor->get_identity_id() );
	}

	$post_id = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $author_id, 'post_title' => 'Attributed', 'post_content' => 'body' ) );
	$ax_attr_posts[] = $post_id;
	$object_uri = axismundi_op_post_object_uri( get_post( $post_id ) );
	$ax_attr_objects[] = $object_uri;
	$recorded  = function_exists( 'axismundi_act_get_object_attribution' ) ? axismundi_act_get_object_attribution( $object_uri ) : '';
	$projected = axismundi_op_transform_object( get_post( $post_id ) );
	ax_attr_assert( $ax_attr_results, 'a published Object is attributed to what the ledger recorded, not to anything derived from its author', '' !== $recorded && is_array( $projected ) && $recorded === (string) ( $projected['attributedTo'] ?? '' ) );

	// With no provider answering, this plugin must still project -- it never reads the ledger.
	$had_provider = function_exists( 'axismundi_act_local_object_attribution' ) && remove_filter( 'axismundi_op_local_object_attribution', 'axismundi_act_local_object_attribution', 10 );
	$without = axismundi_op_transform_object( get_post( $post_id ) );
	if ( $had_provider ) {
		add_filter( 'axismundi_op_local_object_attribution', 'axismundi_act_local_object_attribution', 10, 3 );
	}
	ax_attr_assert( $ax_attr_results, 'projection survives its attribution provider being absent, omitting the member rather than failing or emitting it empty', is_array( $without ) && ! array_key_exists( 'attributedTo', $without ) && isset( $without['id'], $without['type'], $without['url'] ) );

	// An author with no Actor at all: readable, unattributed, and published by nobody.
	$plain_id = (int) wp_insert_user( array( 'user_login' => 'ax-attr-plain-' . wp_generate_password( 8, false, false ), 'user_pass' => wp_generate_password( 20 ), 'role' => 'author' ) );
	$ax_attr_users[] = $plain_id;
	wp_set_current_user( $plain_id );
	$actorless_id = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $plain_id, 'post_title' => 'Actorless', 'post_content' => 'body' ) );
	$ax_attr_posts[] = $actorless_id;
	$actorless_uri = axismundi_op_post_object_uri( get_post( $actorless_id ) );
	$ax_attr_objects[] = $actorless_uri;
	$actorless = axismundi_op_transform_object( get_post( $actorless_id ) );
	$actorless_rows = function_exists( 'axismundi_act_get_by_object' ) ? axismundi_act_get_by_object( $actorless_uri ) : array();
	$actorless_audience = axismundi_op_post_article_audience( get_post( $actorless_id ) );
	wp_set_current_user( $author_id );
	ax_attr_assert( $ax_attr_results, 'a post whose author has no Actor is readable and unattributed, addressed publicly, and published by nobody', is_array( $actorless ) && ! array_key_exists( 'attributedTo', $actorless ) && axismundi_op_post_article_publicly_readable( get_post( $actorless_id ) ) && array() === $actorless_rows && is_array( $actorless_audience ) && array( axismundi_op_public_audience_uri() ) === $actorless_audience['to'] && array() === $actorless_audience['cc'] );

	// The permission this buys is for locally authored sources only.
	$remote = axismundi_op_finalize_object( array( 'id' => 'https://remote.example/o/1', 'type' => 'Note', 'url' => 'https://remote.example/o/1' ), 'https://remote.example/o/1' );
	$remote_ok = axismundi_op_finalize_object( array( 'id' => 'https://remote.example/o/2', 'type' => 'Note', 'url' => 'https://remote.example/o/2', 'attributedTo' => 'https://remote.example/actors/a' ), 'https://remote.example/o/2' );
	ax_attr_assert( $ax_attr_results, 'a payload that is not from a local source still requires an author, so an unattributed remote Object stays refused', is_wp_error( $remote ) && 'ax_op_invalid_object' === $remote->get_error_code() && is_array( $remote_ok ) );

	// The provenance signal is a property of the transformer, never a member of the object.
	$leaked = is_array( $actorless ) ? array_intersect( array( 'local', 'local_source', 'localSource' ), array_keys( $actorless ) ) : array( 'unprojectable' );
	ax_attr_assert( $ax_attr_results, 'the local-source signal never reaches the emitted JSON-LD', array() === $leaked );
} finally {
	global $wpdb;
	wp_set_current_user( isset( $author_id ) ? (int) $author_id : 0 );
	foreach ( array_unique( array_filter( $ax_attr_objects ) ) as $uri ) {
		if ( function_exists( 'axismundi_act_activities_table' ) ) {
			$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
	}
	foreach ( array_filter( $ax_attr_posts ) as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( array_filter( $ax_attr_ids ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( array_filter( $ax_attr_users ) as $id ) {
		wp_delete_user( $id );
	}
}

$ax_attr_failures = count( array_filter( $ax_attr_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_attr_results ), $ax_attr_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_attr_failures > 0 ? 1 : 0 );
}
exit( $ax_attr_failures > 0 ? 1 : 0 );
