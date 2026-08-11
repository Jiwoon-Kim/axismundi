<?php
/**
 * The Calendar read API (dev-only; dist-excluded).
 *
 * Asserted through `rest_do_request()` rather than by calling the handlers, so the registration, the
 * permission callbacks, the argument schema and the status codes are all in the answer. A handler
 * tested directly cannot show that its route was never registered.
 *
 * The property is the five-way split the public gate could not express on its own:
 *
 *   anonymous, publicly readable    200 with detail
 *   signed-in ACL reader            200 with detail, private Calendar included
 *   signed-in freeBusyReader        403 -- occupied time is not a title
 *   signed-in with no role          403
 *   anonymous, private or unknown   404, indistinguishable from each other
 *
 * The reader case is the one that needs the serializer split behind it: a private Calendar's Events
 * are withheld from every public surface, and must still reach the person who was granted them.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_rr_results   = array();
$ax_rr_calendars = array();
$ax_rr_posts     = array();
$ax_rr_users     = array();

/** @param bool[] $results Results. */
function ax_rr_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A user with a public Person Actor. */
function ax_rr_user( array &$users ) : array {
	$login   = 'ax_rr_' . strtolower( wp_generate_password( 8, false, false ) );
	$id      = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$users[] = $id;
	$uri     = '';
	if ( function_exists( 'axismundi_actors_ensure_for_user' ) ) {
		$actor = axismundi_actors_ensure_for_user( $id );
		if ( $actor instanceof Axismundi_Actor ) {
			axismundi_actors_register_handle( $actor->get_identity_id(), $login );
			axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
			$uri = (string) $actor->get_uri();
		}
	}
	return array( 'user_id' => $id, 'actor_uri' => $uri );
}

/** An Event published on one Calendar. */
function ax_rr_event( array &$posts, int $calendar_id, string $title ) : int {
	$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_title' => $title ) );
	$posts[] = $post_id;
	axismundi_cal_event_save(
		$post_id,
		array( 'calendar_id' => $calendar_id, 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-09-10 19:00:00', 'ends_at' => '2026-09-10 21:00:00' )
	);
	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;
	return $post_id;
}

/** Dispatch one GET and return [status, data]. */
function ax_rr_get( string $route, array $params = array() ) : array {
	$request = new WP_REST_Request( 'GET', $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$response = rest_do_request( $request );
	return array( (int) $response->get_status(), (array) $response->get_data() );
}

try {
	$ax_rr_reader   = ax_rr_user( $ax_rr_users );
	$ax_rr_busy     = ax_rr_user( $ax_rr_users );
	$ax_rr_stranger = ax_rr_user( $ax_rr_users );

	/*
	 * The API resolves the caller's Actor through the same helper the admin screen uses. Asserted,
	 * because every permission answer below is wrong if the URI it compares is not this one.
	 */
	wp_set_current_user( $ax_rr_reader['user_id'] );
	ax_rr_assert( $ax_rr_results, "the request's principal is the signed-in user's Actor", $ax_rr_reader['actor_uri'] === axismundi_cal_current_actor_uri() );
	wp_set_current_user( 0 );

	$ax_rr_public = (int) axismundi_cal_calendar_save( array( 'name' => 'Read public', 'slug' => 'ax-rr-public', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_rr_reader['actor_uri'] ) );
	$ax_rr_private = (int) axismundi_cal_calendar_save( array( 'name' => 'Read private', 'slug' => 'ax-rr-private', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_rr_reader['actor_uri'] ) );
	$ax_rr_calendars[] = $ax_rr_public;
	$ax_rr_calendars[] = $ax_rr_private;
	axismundi_cal_acl_grant( $ax_rr_public, '', 'reader', 'public' );
	axismundi_cal_acl_grant( $ax_rr_private, $ax_rr_reader['actor_uri'], 'reader' );
	axismundi_cal_acl_grant( $ax_rr_private, $ax_rr_busy['actor_uri'], 'freeBusyReader' );

	$ax_rr_public_row  = (array) axismundi_cal_calendar_get( $ax_rr_public );
	$ax_rr_private_row = (array) axismundi_cal_calendar_get( $ax_rr_private );
	$ax_rr_public_uuid  = (string) $ax_rr_public_row['uuid'];
	$ax_rr_private_uuid = (string) $ax_rr_private_row['uuid'];

	ax_rr_event( $ax_rr_posts, $ax_rr_public, 'Read public event' );
	ax_rr_event( $ax_rr_posts, $ax_rr_private, 'Read private event' );

	// -- Anonymous ---------------------------------------------------------------------------------

	list( $ax_rr_status, $ax_rr_body ) = ax_rr_get( '/axismundi/v1/calendars/' . $ax_rr_public_uuid );
	ax_rr_assert( $ax_rr_results, 'a publicly readable Calendar answers an anonymous request', 200 === $ax_rr_status );
	ax_rr_assert( $ax_rr_results, 'with its detail', 'Read public' === ( $ax_rr_body['summary'] ?? '' ) );
	ax_rr_assert( $ax_rr_results, 'and the role the public holds', 'reader' === ( $ax_rr_body['accessRole'] ?? '' ) );
	ax_rr_assert( $ax_rr_results, 'and a subscription URL, since that URL resolves', str_contains( (string) ( $ax_rr_body['icsUrl'] ?? '' ), '.ics' ) );

	list( $ax_rr_status, $ax_rr_body ) = ax_rr_get( '/axismundi/v1/calendars/' . $ax_rr_private_uuid );
	ax_rr_assert( $ax_rr_results, 'a private Calendar is not found by an anonymous request', 404 === $ax_rr_status );
	ax_rr_assert( $ax_rr_results, 'and says nothing about it', ! str_contains( wp_json_encode( $ax_rr_body ), 'Read private' ) );

	/*
	 * The refusal must be the same one a Calendar that does not exist produces. A `403` here would
	 * answer "does this slug exist?" for anyone who cares to ask.
	 */
	list( $ax_rr_unknown_status ) = ax_rr_get( '/axismundi/v1/calendars/' . wp_generate_uuid4() );
	ax_rr_assert( $ax_rr_results, 'a Calendar that does not exist is refused identically', 404 === $ax_rr_unknown_status );

	list( $ax_rr_status, $ax_rr_body ) = ax_rr_get( '/axismundi/v1/calendars/' . $ax_rr_public_uuid . '/events' );
	ax_rr_assert( $ax_rr_results, 'the public range answers anonymously', 200 === $ax_rr_status );
	ax_rr_assert(
		$ax_rr_results,
		'and carries the Event',
		1 === count( array_filter( (array) $ax_rr_body['items'], static fn( array $i ) : bool => 'Read public event' === $i['summary'] ) )
	);
	ax_rr_assert( $ax_rr_results, 'with both the instant and the local wall time', str_contains( (string) $ax_rr_body['items'][0]['startUtc'], '2026-09-10' ) && '2026-09-10 19:00:00' === (string) $ax_rr_body['items'][0]['startLocal'] );

	list( $ax_rr_status ) = ax_rr_get( '/axismundi/v1/calendars/' . $ax_rr_private_uuid . '/events' );
	ax_rr_assert( $ax_rr_results, 'the private range is not found anonymously', 404 === $ax_rr_status );

	list( $ax_rr_status ) = ax_rr_get( '/axismundi/v1/actors/me/calendarList' );
	ax_rr_assert( $ax_rr_results, '`me` with nobody signed in is unauthenticated rather than forbidden', 401 === $ax_rr_status );

	// -- The granted reader ---------------------------------------------------------------------------

	wp_set_current_user( $ax_rr_reader['user_id'] );

	list( $ax_rr_status, $ax_rr_body ) = ax_rr_get( '/axismundi/v1/calendars/' . $ax_rr_private_uuid );
	ax_rr_assert( $ax_rr_results, 'a granted reader receives the private Calendar', 200 === $ax_rr_status && 'Read private' === ( $ax_rr_body['summary'] ?? '' ) );
	ax_rr_assert( $ax_rr_results, 'with the role they actually hold', 'reader' === ( $ax_rr_body['accessRole'] ?? '' ) );
	ax_rr_assert( $ax_rr_results, 'and no subscription URL, because that URL would answer 404', ! array_key_exists( 'icsUrl', $ax_rr_body ) );

	/*
	 * The case the serializer split exists for. This Event is withheld from the grid, both feeds, its
	 * own permalink and federation -- and must still reach the person who was granted it.
	 */
	list( $ax_rr_status, $ax_rr_body ) = ax_rr_get( '/axismundi/v1/calendars/' . $ax_rr_private_uuid . '/events' );
	ax_rr_assert(
		$ax_rr_results,
		'and the Events of a Calendar that is on no public surface at all',
		200 === $ax_rr_status && 1 === count( array_filter( (array) $ax_rr_body['items'], static fn( array $i ) : bool => 'Read private event' === $i['summary'] ) )
	);
	ax_rr_assert(
		$ax_rr_results,
		'while that same Event stays off the public grid, so the API did not reopen the gate',
		false === axismundi_cal_event_listable( get_post( $ax_rr_posts[1] ) )
	);

	list( $ax_rr_status, $ax_rr_body ) = ax_rr_get( '/axismundi/v1/actors/me/calendarList' );
	$ax_rr_listed = array_column( (array) $ax_rr_body['items'], 'accessRole', 'id' );
	ax_rr_assert( $ax_rr_results, 'their calendar list answers', 200 === $ax_rr_status );
	ax_rr_assert( $ax_rr_results, 'and holds the Calendar they were granted, which has no list entry of its own', 'reader' === ( $ax_rr_listed[ $ax_rr_private_uuid ] ?? '' ) );

	/*
	 * The list is the ACL's answer, not the entry's copy of it. An entry claiming `owner` after the
	 * rule was revoked would tell a client it may write to something it may not read.
	 */
	axismundi_cal_list_set( $ax_rr_private, $ax_rr_reader['actor_uri'], 'owner', array( 'summary_override' => 'My name for it', 'color' => '#336699', 'hidden' => 1 ) );
	list( , $ax_rr_body ) = ax_rr_get( '/axismundi/v1/actors/me/calendarList' );
	$ax_rr_entry = array_values( array_filter( (array) $ax_rr_body['items'], static fn( array $i ) : bool => $ax_rr_private_uuid === $i['id'] ) )[0] ?? array();
	ax_rr_assert( $ax_rr_results, 'a list entry claiming a stronger role does not grant one', 'reader' === ( $ax_rr_entry['accessRole'] ?? '' ) );
	ax_rr_assert( $ax_rr_results, 'while the view state on that entry is reported as theirs', 'My name for it' === ( $ax_rr_entry['summaryOverride'] ?? '' ) && true === ( $ax_rr_entry['hidden'] ?? false ) );

	axismundi_cal_acl_revoke( $ax_rr_private, $ax_rr_reader['actor_uri'] );
	list( , $ax_rr_body ) = ax_rr_get( '/axismundi/v1/actors/me/calendarList' );
	ax_rr_assert(
		$ax_rr_results,
		'and a revoked rule drops the Calendar from the list even though the entry remains',
		0 === count( array_filter( (array) $ax_rr_body['items'], static fn( array $i ) : bool => $ax_rr_private_uuid === $i['id'] ) )
			&& is_array( axismundi_cal_list_entry( $ax_rr_private, $ax_rr_reader['actor_uri'] ) )
	);
	list( $ax_rr_status ) = ax_rr_get( '/axismundi/v1/calendars/' . $ax_rr_private_uuid );
	ax_rr_assert( $ax_rr_results, 'and they are refused the Calendar itself from then on', 403 === $ax_rr_status );

	// -- free/busy is not reading ----------------------------------------------------------------------

	wp_set_current_user( $ax_rr_busy['user_id'] );
	list( $ax_rr_status, $ax_rr_body ) = ax_rr_get( '/axismundi/v1/calendars/' . $ax_rr_private_uuid );
	ax_rr_assert( $ax_rr_results, 'a freeBusyReader is refused the detail', 403 === $ax_rr_status );
	ax_rr_assert( $ax_rr_results, 'and told nothing about what is on it', ! str_contains( wp_json_encode( $ax_rr_body ), 'Read private' ) );
	list( $ax_rr_status ) = ax_rr_get( '/axismundi/v1/calendars/' . $ax_rr_private_uuid . '/events' );
	ax_rr_assert( $ax_rr_results, 'and refused the Events with it, since a title is what the role withholds', 403 === $ax_rr_status );

	// -- Signed in with no relation ---------------------------------------------------------------------

	wp_set_current_user( $ax_rr_stranger['user_id'] );
	list( $ax_rr_status ) = ax_rr_get( '/axismundi/v1/calendars/' . $ax_rr_private_uuid );
	ax_rr_assert( $ax_rr_results, 'a signed-in stranger is forbidden rather than told it is missing', 403 === $ax_rr_status );
	list( $ax_rr_status ) = ax_rr_get( '/axismundi/v1/calendars/' . wp_generate_uuid4() );
	ax_rr_assert( $ax_rr_results, 'though a Calendar that really is missing is still 404 for them', 404 === $ax_rr_status );
	list( $ax_rr_status, $ax_rr_body ) = ax_rr_get( '/axismundi/v1/calendars/' . $ax_rr_public_uuid );
	ax_rr_assert( $ax_rr_results, 'and the public Calendar is theirs to read like anyone else', 200 === $ax_rr_status );
	list( , $ax_rr_body ) = ax_rr_get( '/axismundi/v1/actors/me/calendarList' );
	ax_rr_assert( $ax_rr_results, 'their own list is empty, not everything they can see', array() === (array) $ax_rr_body['items'] );

	// -- Range arguments ----------------------------------------------------------------------------------

	wp_set_current_user( 0 );
	list( $ax_rr_status ) = ax_rr_get( '/axismundi/v1/calendars/' . $ax_rr_public_uuid . '/events', array( 'start' => '2026-09-01', 'end' => '2026-08-01' ) );
	ax_rr_assert( $ax_rr_results, 'a range that ends before it starts is refused', 400 === $ax_rr_status );
	list( $ax_rr_status ) = ax_rr_get( '/axismundi/v1/calendars/' . $ax_rr_public_uuid . '/events', array( 'start' => '2026-01-01', 'end' => '2036-01-01' ) );
	ax_rr_assert( $ax_rr_results, 'and a decade is refused rather than truncated, so nine empty years are not implied', 400 === $ax_rr_status );
	list( $ax_rr_status, $ax_rr_body ) = ax_rr_get( '/axismundi/v1/calendars/' . $ax_rr_public_uuid . '/events', array( 'start' => '2027-01-01', 'end' => '2027-02-01' ) );
	ax_rr_assert( $ax_rr_results, 'while a range with nothing in it is an empty answer, not an error', 200 === $ax_rr_status && array() === (array) $ax_rr_body['items'] );

	// -- Read-only ------------------------------------------------------------------------------------------

	/*
	 * Nothing here writes. Asserted through the route table rather than by trying a write, because a
	 * `POST` that fails could be failing for any reason -- this says the method was never offered.
	 */
	$ax_rr_routes  = rest_get_server()->get_routes();
	$ax_rr_methods = array();
	foreach ( $ax_rr_routes as $ax_rr_route => $ax_rr_handlers ) {
		/*
		 * The three read routes by name. Deliberately not every route under `/calendars`: the ACL and
		 * calendar-list routes live beneath the same prefix and are supposed to accept writes, so a
		 * prefix match here would either fail or, worse, start passing again if a write were added to
		 * one of these three.
		 */
		$ax_rr_read_route = '/axismundi/v1/actors/me/calendarList' === $ax_rr_route
			|| '/axismundi/v1/calendars/(?P<uuid>[0-9a-fA-F-]{36})' === $ax_rr_route
			|| '/axismundi/v1/calendars/(?P<uuid>[0-9a-fA-F-]{36})/events' === $ax_rr_route;
		if ( ! $ax_rr_read_route ) {
			continue;
		}
		foreach ( $ax_rr_handlers as $ax_rr_handler ) {
			$ax_rr_methods = array_merge( $ax_rr_methods, array_keys( array_filter( (array) $ax_rr_handler['methods'] ) ) );
		}
	}
	ax_rr_assert( $ax_rr_results, 'the read API offers GET and nothing else', array( 'GET' ) === array_values( array_unique( $ax_rr_methods ) ) );
	// And all three were found, so the check above is not passing because it matched nothing.
	ax_rr_assert( $ax_rr_results, 'on all three routes', 3 === count( $ax_rr_methods ) );
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_rr_posts as $ax_rr_post ) {
		wp_delete_post( (int) $ax_rr_post, true );
	}
	foreach ( $ax_rr_calendars as $ax_rr_calendar ) {
		axismundi_cal_calendar_delete( (int) $ax_rr_calendar );
	}
	foreach ( $ax_rr_users as $ax_rr_user_id ) {
		wp_delete_user( (int) $ax_rr_user_id );
	}
}

$ax_rr_failures = count( array_filter( $ax_rr_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_rr_results ), $ax_rr_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_rr_failures > 0 ? 1 : 0 );
}
exit( $ax_rr_failures > 0 ? 1 : 0 );
