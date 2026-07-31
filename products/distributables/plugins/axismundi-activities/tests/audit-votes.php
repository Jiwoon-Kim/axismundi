<?php
/**
 * Like/Dislike vote ledger regression (dev-only; dist-excluded).
 *
 * A Lemmy downvote on one of our Topics arrives whether or not any community feature is
 * installed. Before this, `Dislike` was not a supported Activity type at all, so those votes
 * were dropped at the ledger boundary and the record of them was simply lost.
 *
 * This locks the ledger contract only: Dislike is recorded, idempotent, and undoable exactly
 * like Like, and the two do not cancel each other. Mutual exclusion is a community policy and
 * belongs to whichever product owns the community, not here.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/repository.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/votes.php';
require_once WP_PLUGIN_DIR . '/axismundi-activities/includes/reactions.php';

global $wpdb;
$ax_v_results = array();
$ax_v_users   = array();
$ax_v_ids     = array();
$ax_v_uris    = array();

/** @param bool[] $results Results. */
function ax_v_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$user          = (int) wp_insert_user( array( 'user_login' => 'axv_' . strtolower( wp_generate_password( 9, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'contributor' ) );
	$ax_v_users[]  = $user;
	$actor         = axismundi_actors_ensure_for_user( $user );
	$ax_v_ids[]    = $actor instanceof Axismundi_Actor ? $actor->get_identity_id() : 0;
	if ( $actor instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $actor->get_identity_id(), 'axv' . strtolower( wp_generate_password( 8, false, false ) ) );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
		$actor = axismundi_actors_get_by_identity( $actor->get_identity_id() );
	}
	$object = home_url( '/?p=99999801' );

	ax_v_assert(
		$ax_v_results,
		'Dislike is a supported Activity type, so an inbound community downvote can be recorded at all',
		in_array( 'Dislike', axismundi_act_types(), true ) && in_array( 'Like', axismundi_act_types(), true )
	);

	$dislike = $actor instanceof Axismundi_Actor ? axismundi_act_dislike_object( $actor, $object ) : new WP_Error( 'fixture' );
	if ( $dislike instanceof Axismundi_Activity ) {
		$ax_v_uris[] = $dislike->get_uri();
	}
	$repeat = $actor instanceof Axismundi_Actor ? axismundi_act_dislike_object( $actor, $object ) : new WP_Error( 'fixture' );
	ax_v_assert(
		$ax_v_results,
		'a Dislike is recorded once and a repeated vote converges on the same Activity',
		$dislike instanceof Axismundi_Activity
			&& 'Dislike' === $dislike->get_type()
			&& $repeat instanceof Axismundi_Activity
			&& $dislike->get_uri() === $repeat->get_uri()
			&& axismundi_act_get_dislike_state( $actor->get_uri(), $object )
			&& 1 === axismundi_act_get_dislike_count( $object )
	);

	// The two verbs are independent judgements at ledger level; neither cancels the other.
	$like = $actor instanceof Axismundi_Actor ? axismundi_act_like_object( $actor, $object ) : new WP_Error( 'fixture' );
	if ( $like instanceof Axismundi_Activity ) {
		$ax_v_uris[] = $like->get_uri();
	}
	ax_v_assert(
		$ax_v_results,
		'a Like does not silently cancel a Dislike: exclusive voting is a community policy, not a ledger rule',
		$like instanceof Axismundi_Activity
			&& 'Like' === $like->get_type()
			&& axismundi_act_get_like_state( $actor->get_uri(), $object )
			&& axismundi_act_get_dislike_state( $actor->get_uri(), $object )
			&& 1 === axismundi_act_get_like_count( $object )
			&& 1 === axismundi_act_get_dislike_count( $object )
	);

	$undo   = $actor instanceof Axismundi_Actor ? axismundi_act_undislike_object( $actor, $object ) : new WP_Error( 'fixture' );
	if ( $undo instanceof Axismundi_Activity ) {
		$ax_v_uris[] = $undo->get_uri();
	}
	$undo_again = $actor instanceof Axismundi_Actor ? axismundi_act_undislike_object( $actor, $object ) : new WP_Error( 'fixture' );
	ax_v_assert(
		$ax_v_results,
		'undoing a Dislike refers to its Activity, leaves the Like standing, and a repeated undo converges',
		$undo instanceof Axismundi_Activity
			&& 'Undo' === $undo->get_type()
			&& $dislike instanceof Axismundi_Activity
			&& $dislike->get_uri() === (string) $undo->get_object_uri()
			&& ! axismundi_act_get_dislike_state( $actor->get_uri(), $object )
			&& 0 === axismundi_act_get_dislike_count( $object )
			&& axismundi_act_get_like_state( $actor->get_uri(), $object )
			&& $undo_again instanceof Axismundi_Activity
			&& $undo->get_uri() === $undo_again->get_uri()
	);

	// An emoji reaction is not a plain vote and must never be counted as one.
	$reaction = $actor instanceof Axismundi_Actor && function_exists( 'axismundi_act_react_to_object' ) ? axismundi_act_react_to_object( $actor, $object, '👍' ) : new WP_Error( 'fixture' );
	if ( $reaction instanceof Axismundi_Activity ) {
		$ax_v_uris[] = $reaction->get_uri();
	}
	ax_v_assert(
		$ax_v_results,
		'an emoji reaction stays out of both vote counts',
		$reaction instanceof Axismundi_Activity
			&& 1 === axismundi_act_get_like_count( $object )
			&& 0 === axismundi_act_get_dislike_count( $object )
	);

	// A re-vote after an undo starts a new cycle rather than reusing the undone Activity.
	$revote = $actor instanceof Axismundi_Actor ? axismundi_act_dislike_object( $actor, $object ) : new WP_Error( 'fixture' );
	if ( $revote instanceof Axismundi_Activity ) {
		$ax_v_uris[] = $revote->get_uri();
	}
	ax_v_assert(
		$ax_v_results,
		'voting again after an undo records a new Activity instead of resurrecting the undone one',
		$revote instanceof Axismundi_Activity
			&& $dislike instanceof Axismundi_Activity
			&& $revote->get_uri() !== $dislike->get_uri()
			&& $revote->is_effective()
			&& 1 === axismundi_act_get_dislike_count( $object )
	);
} finally {
	$table = axismundi_act_activities_table();
	foreach ( array_filter( array_unique( $ax_v_ids ) ) as $identity_id ) {
		$fixture = axismundi_actors_get_by_identity( (int) $identity_id );
		if ( $fixture instanceof Axismundi_Actor ) {
			$wpdb->delete( $table, array( 'actor_uri_hash' => hash( 'sha256', $fixture->get_uri() ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		foreach ( array( axismundi_actors_endpoints_table(), axismundi_actors_actors_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( array_unique( $ax_v_uris ) as $uri ) {
		$wpdb->delete( $table, array( 'activity_uri_hash' => hash( 'sha256', $uri ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	if ( ! empty( $ax_v_users ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_v_users ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_v_failed = count( array_filter( $ax_v_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n%d/%d passed\n", count( $ax_v_results ) - $ax_v_failed, count( $ax_v_results ) );
exit( $ax_v_failed > 0 ? 1 : 0 );
