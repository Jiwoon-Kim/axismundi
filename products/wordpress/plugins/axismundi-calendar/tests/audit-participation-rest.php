<?php
/**
 * The participation routes (dev-only; dist-excluded).
 *
 * The rule this file exists for is that the Actor is never taken from the request. A body naming who
 * is joining would make impersonation a field -- any signed-in user could put somebody else's Actor
 * URI in it, and the ledger would record the `Join` as theirs because nothing downstream can tell the
 * difference. Every route is therefore asked to act as somebody else, and expected to act as the
 * caller instead.
 *
 * Only the three routes that change something. An admin screen reads the model in PHP, and a personal
 * list has to answer for Events on other servers -- a projection that does not exist yet -- so both
 * read shapes are left unpublished rather than guessed at and then kept working while corrected.
 *
 * The second rule is that a screen is a courtesy. Approval is only shown on a moderated Event, but a
 * request can arrive from anywhere, so the mode, the state and the authority are re-established at
 * the endpoint rather than trusted from whatever drew the button.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_pr_results   = array();
$ax_pr_posts     = array();
$ax_pr_calendars = array();
$ax_pr_users     = array();

/** @param bool[] $results Results. */
function ax_pr_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A user with an activated public Person Actor. */
function ax_pr_user( array &$users ) : array {
	$handle  = 'axpr' . strtolower( wp_generate_password( 8, false, false ) );
	$id      = (int) wp_insert_user( array( 'user_login' => $handle, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$users[] = $id;
	$uri     = '';
	if ( function_exists( 'axismundi_actors_ensure_for_user' ) ) {
		$actor = axismundi_actors_ensure_for_user( $id );
		if ( $actor instanceof Axismundi_Actor ) {
			axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
			axismundi_actors_register_handle( $actor->get_identity_id(), $handle );
			$uri = (string) $actor->get_uri();
		}
	}
	return array( 'user_id' => $id, 'actor_uri' => $uri );
}

/** Dispatch one request as the given user. */
function ax_pr_call( int $user_id, string $method, string $route, array $body = array() ) : WP_REST_Response {
	wp_set_current_user( $user_id );
	$request = new WP_REST_Request( $method, $route );
	foreach ( $body as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_get_server()->dispatch( $request );
}

try {
	rest_get_server();
	do_action( 'rest_api_init' );

	$ax_pr_host    = ax_pr_user( $ax_pr_users );
	$ax_pr_alice   = ax_pr_user( $ax_pr_users );
	$ax_pr_bob     = ax_pr_user( $ax_pr_users );
	$ax_pr_actorless = (int) wp_insert_user( array( 'user_login' => 'ax_pr_plain_' . strtolower( wp_generate_password( 6, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_pr_users[] = $ax_pr_actorless;

	wp_set_current_user( $ax_pr_host['user_id'] );
	$ax_pr_suffix   = strtolower( wp_generate_password( 6, false, false ) );
	$ax_pr_calendar = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Route fixture', 'slug' => 'ax-pr-' . $ax_pr_suffix, 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_pr_host['actor_uri'] )
	);
	$ax_pr_calendars[] = $ax_pr_calendar;
	axismundi_cal_acl_grant( $ax_pr_calendar, '', 'reader', 'public' );

	$ax_pr_make = static function ( array &$posts, int $calendar, string $title, array $fields ) : int {
		$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_title' => $title, 'post_author' => get_current_user_id() ) );
		$posts[] = $post_id;
		axismundi_cal_event_save(
			$post_id,
			array_merge( array( 'calendar_id' => $calendar, 'timezone' => 'Asia/Seoul', 'starts_at' => '2027-01-09 19:00:00', 'ends_at' => '2027-01-09 21:00:00' ), $fields )
		);
		$GLOBALS['axismundi_cal_rest_write'] = true;
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		$GLOBALS['axismundi_cal_rest_write'] = false;
		return $post_id;
	};

	$ax_pr_open = $ax_pr_make( $ax_pr_posts, $ax_pr_calendar, 'Open house', array( 'join_mode' => 'free' ) );
	$ax_pr_mod  = $ax_pr_make( $ax_pr_posts, $ax_pr_calendar, 'Moderated', array( 'join_mode' => 'restricted' ) );

	// -- the caller is who they are -------------------------------------------------------------------

	/*
	 * Asked to act as somebody else, in the two shapes a client could try: the Actor URI and the row
	 * the model keys on. Either being honoured would make the ledger record a `Join` by a person who
	 * never sent one, and nothing downstream could tell.
	 */
	$ax_pr_impersonation = ax_pr_call(
		$ax_pr_alice['user_id'],
		'POST',
		'/axismundi/v1/events/' . $ax_pr_open . '/join',
		array( 'actorUri' => $ax_pr_bob['actor_uri'], 'actor_uri' => $ax_pr_bob['actor_uri'] )
	);
	ax_pr_assert(
		$ax_pr_results,
		'a request naming somebody else joins as the caller instead',
		201 === $ax_pr_impersonation->get_status()
			&& $ax_pr_alice['actor_uri'] === (string) $ax_pr_impersonation->get_data()['actorUri']
	);
	ax_pr_assert(
		$ax_pr_results,
		'and the person it named has said nothing at all',
		null === axismundi_cal_event_participation( $ax_pr_open, $ax_pr_bob['actor_uri'] )
	);
	ax_pr_assert(
		$ax_pr_results,
		'an open event answers on arrival, which the route reports as accepted',
		'accepted' === (string) $ax_pr_impersonation->get_data()['state']
	);

	// -- signed in is not enough ----------------------------------------------------------------------

	/*
	 * Participation is Actor to Actor on both ends, so a user with no Actor is refused rather than
	 * quietly served -- there is no half of the relationship a local user id could stand in for.
	 */
	ax_pr_assert(
		$ax_pr_results,
		'a signed-in user without an actor profile is told why rather than joining',
		403 === ax_pr_call( $ax_pr_actorless, 'POST', '/axismundi/v1/events/' . $ax_pr_open . '/join' )->get_status()
	);
	wp_set_current_user( 0 );
	ax_pr_assert(
		$ax_pr_results,
		'and a caller who is nobody is refused before any of that is asked',
		401 === rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/axismundi/v1/events/' . $ax_pr_open . '/join' ) )->get_status()
	);

	// -- answering is the organizer's ------------------------------------------------------------------

	$ax_pr_asked = ax_pr_call( $ax_pr_alice['user_id'], 'POST', '/axismundi/v1/events/' . $ax_pr_mod . '/join' );
	$ax_pr_row   = (int) $ax_pr_asked->get_data()['id'];
	ax_pr_assert(
		$ax_pr_results,
		'a moderated event holds the request rather than admitting it',
		'pending' === (string) $ax_pr_asked->get_data()['state']
	);
	ax_pr_assert(
		$ax_pr_results,
		'somebody who does not run the event cannot approve their own request',
		403 === ax_pr_call( $ax_pr_alice['user_id'], 'POST', '/axismundi/v1/events/' . $ax_pr_mod . '/participants/' . $ax_pr_row . '/accept' )->get_status()
			&& 'pending' === (string) axismundi_cal_event_participation( $ax_pr_mod, $ax_pr_alice['actor_uri'] )['state']
	);
	ax_pr_assert(
		$ax_pr_results,
		'nor can an unrelated guest approve somebody else',
		403 === ax_pr_call( $ax_pr_bob['user_id'], 'POST', '/axismundi/v1/events/' . $ax_pr_mod . '/participants/' . $ax_pr_row . '/accept' )->get_status()
	);
	ax_pr_assert(
		$ax_pr_results,
		'the organizer can, and the reply says so',
		'accepted' === (string) ax_pr_call( $ax_pr_host['user_id'], 'POST', '/axismundi/v1/events/' . $ax_pr_mod . '/participants/' . $ax_pr_row . '/accept' )->get_data()['state']
	);

	/*
	 * A request belonging to another Event, named by its own id. Scoping the lookup to the Event is
	 * what stops somebody who runs one Event from answering for a different one.
	 */
	$ax_pr_elsewhere = ax_pr_call( $ax_pr_alice['user_id'], 'POST', '/axismundi/v1/events/' . $ax_pr_open . '/join' );
	ax_pr_assert(
		$ax_pr_results,
		'a request from another event is not this one to answer',
		404 === ax_pr_call( $ax_pr_host['user_id'], 'POST', '/axismundi/v1/events/' . $ax_pr_mod . '/participants/' . (int) $ax_pr_elsewhere->get_data()['id'] . '/reject' )->get_status()
	);

	/*
	 * The mode, re-established at the moment of answering rather than trusted from the screen that
	 * offered the button. An open Event has already admitted everybody who asked, so approving one of
	 * them would be an acceptance of a request that no longer needed one.
	 */
	ax_pr_assert(
		$ax_pr_results,
		'an event that admits people on arrival holds nothing for approval',
		409 === ax_pr_call( $ax_pr_host['user_id'], 'POST', '/axismundi/v1/events/' . $ax_pr_open . '/participants/' . (int) $ax_pr_elsewhere->get_data()['id'] . '/accept' )->get_status()
	);

	// -- taking a request back ------------------------------------------------------------------------

	$ax_pr_undo_ev = $ax_pr_make( $ax_pr_posts, $ax_pr_calendar, 'Withdrawable', array( 'join_mode' => 'restricted' ) );
	ax_pr_call( $ax_pr_bob['user_id'], 'POST', '/axismundi/v1/events/' . $ax_pr_undo_ev . '/join' );
	ax_pr_assert(
		$ax_pr_results,
		'a pending request is taken back by the person who made it',
		'withdrawn' === (string) ax_pr_call( $ax_pr_bob['user_id'], 'DELETE', '/axismundi/v1/events/' . $ax_pr_undo_ev . '/join' )->get_data()['state']
	);
	/*
	 * The same route leaves as well, those having turned out to be one act: the guest undoes their own
	 * `Join`, whether or not it was granted. What it still refuses is a request that was turned down,
	 * where the `Join` is spent and the refusal is what stands.
	 */
	ax_pr_assert(
		$ax_pr_results,
		'and an accepted reply is retracted by the same route, leaving being undoing your own request',
		'withdrawn' === (string) ax_pr_call( $ax_pr_alice['user_id'], 'DELETE', '/axismundi/v1/events/' . $ax_pr_mod . '/join' )->get_data()['state']
	);
	ax_pr_assert(
		$ax_pr_results,
		'and nobody withdraws on behalf of somebody else',
		404 === ax_pr_call( $ax_pr_host['user_id'], 'DELETE', '/axismundi/v1/events/' . $ax_pr_undo_ev . '/join' )->get_status()
	);

	ax_pr_assert(
		$ax_pr_results,
		'a route about an event that does not exist says so',
		404 === ax_pr_call( $ax_pr_alice['user_id'], 'POST', '/axismundi/v1/events/999999/join' )->get_status()
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_pr_posts as $ax_pr_post ) {
		wp_delete_post( (int) $ax_pr_post, true );
	}
	foreach ( $ax_pr_calendars as $ax_pr_cal ) {
		axismundi_cal_calendar_delete( (int) $ax_pr_cal );
	}
	foreach ( $ax_pr_users as $ax_pr_user_id ) {
		wp_delete_user( (int) $ax_pr_user_id );
	}
}

$ax_pr_failures = count( array_filter( $ax_pr_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_pr_results ), $ax_pr_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_pr_failures > 0 ? 1 : 0 );
}
exit( $ax_pr_failures > 0 ? 1 : 0 );
