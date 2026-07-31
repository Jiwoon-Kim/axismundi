<?php
/**
 * Community vote policy regression (dev-only; dist-excluded).
 *
 * Activities keeps Like and Dislike as independent facts. "One vote per person" is this
 * plugin's rule, so this locks it here: switching sides withdraws the old vote, pressing the
 * same side again clears it, and a failure never leaves an Actor holding both.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/votes.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/reactions.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/topics.php';
require_once __DIR__ . '/../includes/inbound-topics.php';
require_once __DIR__ . '/../includes/votes.php';

global $wpdb;
$ax_fv_results = array();
$ax_fv_users   = array();
$ax_fv_ids     = array();
$ax_fv_posts   = array();

/** @param bool[] $results Results. */
function ax_fv_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$user          = (int) wp_insert_user( array( 'user_login' => 'axfv_' . strtolower( wp_generate_password( 9, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'contributor' ) );
	$ax_fv_users[] = $user;
	$actor         = axismundi_actors_ensure_for_user( $user );
	$ax_fv_ids[]   = $actor instanceof Axismundi_Actor ? $actor->get_identity_id() : 0;
	if ( $actor instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $actor->get_identity_id(), 'axfv' . strtolower( wp_generate_password( 8, false, false ) ) );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
		$actor = axismundi_actors_get_by_identity( $actor->get_identity_id() );
	}
	$object = home_url( '/?p=99999701' );

	$up = $actor instanceof Axismundi_Actor ? axismundi_forum_cast_vote( $actor, $object, 'up' ) : new WP_Error( 'fixture' );
	ax_fv_assert(
		$ax_fv_results,
		'an upvote records a Like and reports the community score from the ledger',
		is_array( $up ) && 1 === $up['up'] && 0 === $up['down'] && 1 === $up['score'] && 'up' === $up['viewer']
	);

	// Switching sides must withdraw the old vote, or the same Actor would be counted twice.
	$down = $actor instanceof Axismundi_Actor ? axismundi_forum_cast_vote( $actor, $object, 'down' ) : new WP_Error( 'fixture' );
	ax_fv_assert(
		$ax_fv_results,
		'switching to a downvote withdraws the upvote instead of holding both',
		is_array( $down ) && 0 === $down['up'] && 1 === $down['down'] && -1 === $down['score'] && 'down' === $down['viewer']
			&& ! axismundi_act_get_like_state( $actor->get_uri(), $object )
			&& axismundi_act_get_dislike_state( $actor->get_uri(), $object )
	);

	// Pressing the held side again is a toggle back to no vote.
	$toggle = $actor instanceof Axismundi_Actor ? axismundi_forum_cast_vote( $actor, $object, 'down' ) : new WP_Error( 'fixture' );
	ax_fv_assert(
		$ax_fv_results,
		'pressing the held side again clears the vote rather than recording it twice',
		is_array( $toggle ) && 0 === $toggle['up'] && 0 === $toggle['down'] && 0 === $toggle['score'] && 'none' === $toggle['viewer']
	);

	// An explicit withdrawal is idempotent.
	$clear = $actor instanceof Axismundi_Actor ? axismundi_forum_cast_vote( $actor, $object, 'none' ) : new WP_Error( 'fixture' );
	$revote = $actor instanceof Axismundi_Actor ? axismundi_forum_cast_vote( $actor, $object, 'up' ) : new WP_Error( 'fixture' );
	ax_fv_assert(
		$ax_fv_results,
		'withdrawing with no vote held is harmless and voting again afterwards still works',
		is_array( $clear ) && 'none' === $clear['viewer'] && is_array( $revote ) && 'up' === $revote['viewer'] && 1 === $revote['up']
	);

	// A ledger that already holds both — which Activities permits — must still answer one way.
	$dislike_too = $actor instanceof Axismundi_Actor ? axismundi_act_dislike_object( $actor, $object ) : new WP_Error( 'fixture' );
	ax_fv_assert(
		$ax_fv_results,
		'an Actor holding both verbs resolves to one deterministic viewer state and contributes to only one side of the Forum score',
		$dislike_too instanceof Axismundi_Activity
			&& in_array( axismundi_forum_actor_vote( $actor->get_uri(), $object ), array( 'up', 'down' ), true )
			&& axismundi_forum_actor_vote( $actor->get_uri(), $object ) === axismundi_forum_actor_vote( $actor->get_uri(), $object )
			&& 1 === ( axismundi_forum_vote_score( $object, $actor->get_uri() )['up'] + axismundi_forum_vote_score( $object, $actor->get_uri() )['down'] )
	);

	ax_fv_assert(
		$ax_fv_results,
		'an invalid direction is refused rather than guessed',
		$actor instanceof Axismundi_Actor && is_wp_error( axismundi_forum_cast_vote( $actor, $object, 'sideways' ) )
	);

	// The endpoint the button calls, exercised as the browser does: a direction, not a verb.
	wp_set_current_user( $user );
	$topic_id = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'ax vote fixture', 'post_author' => $user ) );
	$ax_fv_posts[] = $topic_id;
	$topic_uri = function_exists( 'axismundi_op_post_object_uri' ) ? axismundi_op_post_object_uri( get_post( $topic_id ) ) : '';

	$request = new WP_REST_Request( 'POST', '/axismundi/v1/community-votes' );
	$request->set_body_params( array( 'object_uri' => $topic_uri, 'direction' => 'down' ) );
	$cast = rest_do_request( $request );
	$body = $cast instanceof WP_REST_Response ? (array) $cast->get_data() : array();

	$switch = new WP_REST_Request( 'POST', '/axismundi/v1/community-votes' );
	$switch->set_body_params( array( 'object_uri' => $topic_uri, 'direction' => 'up' ) );
	$switched = rest_do_request( $switch );
	$switched_body = $switched instanceof WP_REST_Response ? (array) $switched->get_data() : array();

	ax_fv_assert(
		$ax_fv_results,
		'the endpoint records a direction and returns the authoritative score the button renders',
		'' !== $topic_uri
			&& 200 === ( $cast instanceof WP_REST_Response ? $cast->get_status() : 0 )
			&& 'down' === ( $body['viewer'] ?? '' ) && 1 === ( $body['down'] ?? 0 ) && -1 === ( $body['score'] ?? 0 )
			&& 200 === ( $switched instanceof WP_REST_Response ? $switched->get_status() : 0 )
			&& 'up' === ( $switched_body['viewer'] ?? '' ) && 0 === ( $switched_body['down'] ?? -1 ) && 1 === ( $switched_body['score'] ?? 0 )
	);

	$bad = new WP_REST_Request( 'POST', '/axismundi/v1/community-votes' );
	$bad->set_body_params( array( 'object_uri' => $topic_uri, 'direction' => 'sideways' ) );
	$bad_response = rest_do_request( $bad );
	$unknown = new WP_REST_Request( 'POST', '/axismundi/v1/community-votes' );
	$unknown->set_body_params( array( 'object_uri' => home_url( '/?p=99999999' ), 'direction' => 'up' ) );
	$unknown_response = rest_do_request( $unknown );
	ax_fv_assert(
		$ax_fv_results,
		'the endpoint rejects an unsupported direction and a vote on an object it cannot resolve',
		$bad_response instanceof WP_REST_Response && 400 === $bad_response->get_status()
			&& $unknown_response instanceof WP_REST_Response && $unknown_response->get_status() >= 400
	);

	/*
	 * The permission gate is only reached once the request validates, so the anonymous check
	 * sends a well-formed body — otherwise a 400 for missing parameters would pass for a
	 * refusal that never actually happened.
	 */
	wp_set_current_user( 0 );
	$anonymous = new WP_REST_Request( 'POST', '/axismundi/v1/community-votes' );
	$anonymous->set_body_params( array( 'object_uri' => $topic_uri, 'direction' => 'up' ) );
	$anonymous_response = rest_do_request( $anonymous );
	ax_fv_assert(
		$ax_fv_results,
		'a well-formed vote from a visitor with no active local Actor is refused',
		$anonymous_response instanceof WP_REST_Response && in_array( $anonymous_response->get_status(), array( 401, 403 ), true )
	);
} finally {
	$table = axismundi_act_activities_table();
	foreach ( array_filter( array_unique( $ax_fv_ids ) ) as $identity_id ) {
		$fixture = axismundi_actors_get_by_identity( (int) $identity_id );
		if ( $fixture instanceof Axismundi_Actor ) {
			$wpdb->delete( $table, array( 'actor_uri_hash' => hash( 'sha256', $fixture->get_uri() ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		foreach ( array( axismundi_actors_endpoints_table(), axismundi_actors_actors_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( array_filter( array_unique( $ax_fv_posts ) ) as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}
	if ( ! empty( $ax_fv_users ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_fv_users ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_fv_failed = count( array_filter( $ax_fv_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_fv_results ) - $ax_fv_failed, count( $ax_fv_results ) );
exit( $ax_fv_failed > 0 ? 1 : 0 );
