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
$ax_fv_objects = array();

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
	$group = axismundi_actors_create_managed_actor( array( 'owner_user_id' => $user, 'preferred_username' => 'axfvg' . strtolower( wp_generate_password( 7, false, false ) ), 'status' => 'public' ) );
	$ax_fv_ids[] = $group instanceof Axismundi_Actor ? $group->get_identity_id() : 0;
	$object = 'https://example.com/notes/' . wp_generate_uuid4();
	$ax_fv_objects[] = $object;
	axismundi_op_store_remote_object(
		array(
			'id' => $object, 'type' => 'Note', 'attributedTo' => 'https://example.com/users/axfv',
			'audience' => $group instanceof Axismundi_Actor ? $group->get_uri() : '',
			'content' => '<p>Community vote fixture.</p>', 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		)
	);

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
	$loose_uri = function_exists( 'axismundi_op_post_object_uri' ) ? axismundi_op_post_object_uri( get_post( $topic_id ) ) : '';

	// The ledger-state assertions above deliberately leave both verb rows behind; the endpoint
	// starts from no selection so its first direction has one unambiguous expected result.
	axismundi_forum_cast_vote( $actor, $object, 'none' );
	$topic_uri = $object;

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
	$outside = new WP_REST_Request( 'POST', '/axismundi/v1/community-votes' );
	$outside->set_body_params( array( 'object_uri' => $loose_uri, 'direction' => 'up' ) );
	$outside_response = rest_do_request( $outside );
	ax_fv_assert(
		$ax_fv_results,
		'the endpoint rejects an unsupported direction, an unknown object, and an object outside a community',
		$bad_response instanceof WP_REST_Response && 400 === $bad_response->get_status()
			&& $unknown_response instanceof WP_REST_Response && $unknown_response->get_status() >= 400
			&& $outside_response instanceof WP_REST_Response && 409 === $outside_response->get_status()
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

	/*
	 * The reason the interaction registry exists. Activities owns the control and has no idea
	 * what a community is; a vote is only meaningful inside one. Forum answers for it here, and
	 * nothing in Activities had to learn the concept.
	 */
	$anon_vote = do_blocks( '<!-- wp:axismundi/interaction {"type":"vote","objectUri":"' . esc_url_raw( $topic_uri ) . '"} /-->' );
	wp_set_current_user( $user );
	ax_fv_assert(
		$ax_fv_results,
		'the community vote is offered as an interaction type by Forum, not built into Activities',
		function_exists( 'axismundi_act_interaction_types' )
			&& array_key_exists( 'vote', axismundi_act_interaction_types() )
			// Where the callback is defined is the claim: this plugin answers, Activities does not
			// know what a community is.
			&& false !== strpos(
				(string) ( new ReflectionFunction( axismundi_act_interaction_types()['vote']['describe'] ) )->getFileName(),
				'axismundi-forum'
			)
	);

	/*
	 * One state, three parts. Up and down cannot both be pressed, and the score between them is
	 * neither — so this is one interaction describing several controls rather than several
	 * interactions each holding their own idea of what the reader chose.
	 */
	$ax_fv_read = static function ( string $html ) : array {
		preg_match_all( '#<button\b([^>]*)>#', $html, $found );
		$rows = array();
		foreach ( $found[1] as $tag ) {
			preg_match( '#\bclass="([^"]*)"#', $tag, $class );
			preg_match( '#(?<![-\w])aria-pressed="([^"]*)"#', $tag, $pressed );
			$rows[] = array(
				'pressed'  => $pressed[1] ?? '',
				'selected' => false !== strpos( $class[1] ?? '', 'is-selected' ),
				'disabled' => false !== strpos( $tag, ' disabled' ),
			);
		}
		return $rows;
	};
	// Start from no vote: this Actor already voted through the endpoint above, and casting the
	// side they hold would clear it rather than set it.
	axismundi_forum_cast_vote( $actor, $topic_uri, 'none' );
	axismundi_forum_cast_vote( $actor, $topic_uri, 'up' );
	$up_rows = $ax_fv_read( do_blocks( '<!-- wp:axismundi/interaction {"type":"vote","objectUri":"' . esc_url_raw( $topic_uri ) . '"} /-->' ) );
	axismundi_forum_cast_vote( $actor, $topic_uri, 'down' );
	$down_rows = $ax_fv_read( do_blocks( '<!-- wp:axismundi/interaction {"type":"vote","objectUri":"' . esc_url_raw( $topic_uri ) . '"} /-->' ) );

	ax_fv_assert(
		$ax_fv_results,
		'the vote renders as one group of two controls whose pressed state is mutually exclusive and server-rendered',
		2 === count( $up_rows ) && 2 === count( $down_rows )
			&& 'true' === $up_rows[0]['pressed'] && true === $up_rows[0]['selected']
			&& 'false' === $up_rows[1]['pressed'] && false === $up_rows[1]['selected']
			&& 'false' === $down_rows[0]['pressed'] && false === $down_rows[0]['selected']
			&& 'true' === $down_rows[1]['pressed'] && true === $down_rows[1]['selected']
	);

	/*
	 * The rule the control now keeps by itself.
	 *
	 * A vote counts toward a community, so an Object in none has nothing to count it. This used to
	 * hold only because the block appeared on the two community templates and nowhere else, which
	 * made a correctness property depend on template placement -- and this is the assertion that
	 * fails if the gate is ever removed and the placement is trusted again.
	 *
	 * The subject is a plain published post: real, interactable, and in no community.
	 */
	/*
	 * One authored control, two contexts.
	 *
	 * Templates place the two independent Activity facts. A community Object turns that pair into
	 * one vote group, which keeps the shared card usable in mixed feeds without duplicating the
	 * down side next to the group.
	 */
	$ax_fv_like = static function ( string $uri ) : string {
		return do_blocks( '<!-- wp:axismundi/interaction {"type":"like","objectUri":"' . esc_url_raw( $uri ) . '"} /-->' );
	};
	$ax_fv_community_like = $ax_fv_like( $topic_uri );
	$ax_fv_plain_like     = $ax_fv_like( $loose_uri );
	$ax_fv_dislike = static function ( string $uri ) : string {
		return do_blocks( '<!-- wp:axismundi/interaction {"type":"dislike","objectUri":"' . esc_url_raw( $uri ) . '"} /-->' );
	};
	$ax_fv_community_dislike = $ax_fv_dislike( $topic_uri );
	$ax_fv_plain_dislike     = $ax_fv_dislike( $loose_uri );
	ax_fv_assert(
		$ax_fv_results,
		'an authored Like/Dislike pair renders as one vote on a community Object and as two facts everywhere else',
		// Rendered, not resolved: calling the mapper directly would still pass with nothing hooked
		// to it, which is the difference between the rule existing and the rule being applied.
		false !== strpos( $ax_fv_community_like, 'is-type-vote' )
			&& false !== strpos( $ax_fv_community_like, 'thumb_down' )
			&& false === strpos( $ax_fv_community_like, 'is-type-like' )
			&& '' === trim( $ax_fv_community_dislike )
			&& false !== strpos( $ax_fv_plain_like, 'is-type-like' )
			&& false === strpos( $ax_fv_plain_like, 'is-type-vote' )
			&& false !== strpos( $ax_fv_plain_dislike, 'is-type-dislike' )
			&& false === strpos( $ax_fv_plain_dislike, 'is-type-vote' )
			// Other controls are not part of the sentiment-pair composition.
			&& false !== strpos( do_blocks( '<!-- wp:axismundi/interaction {"type":"reply","objectUri":"' . esc_url_raw( $topic_uri ) . '"} /-->' ), 'is-type-reply' )
			&& 'Like' === axismundi_forum_vote_verb( 'up' )
	);

	$loose_rows = $ax_fv_read( do_blocks( '<!-- wp:axismundi/interaction {"type":"vote","objectUri":"' . esc_url_raw( $loose_uri ) . '"} /-->' ) );
	ax_fv_assert(
		$ax_fv_results,
		'an Object belonging to no community is offered no vote, whatever template asks for one',
		'' !== $loose_uri
			&& null === axismundi_forum_object_community_group( $loose_uri )
			&& array() === $loose_rows
			&& array() !== $up_rows
	);

	// A visitor who may not vote is shown the control and told why, rather than shown a gap.
	$anon_rows = $ax_fv_read( $anon_vote );
	ax_fv_assert(
		$ax_fv_results,
		'a visitor who cannot vote gets both controls, still disabled after directives are processed',
		2 === count( $anon_rows ) && true === $anon_rows[0]['disabled'] && true === $anon_rows[1]['disabled']
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
	foreach ( array_filter( array_unique( $ax_fv_objects ) ) as $object_uri ) {
		axismundi_op_delete_remote_object( (string) $object_uri );
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
