<?php
/**
 * Authoring an Event through the REST envelope field (dev-only; dist-excluded).
 *
 * Three claims are pinned here, each of which fails silently rather than loudly if it breaks:
 *
 * 1. A published Event projects as an `Event`. Nothing else claims `ax_event` -- the Core Post
 *    transformer gates on `post` -- so a regression does not turn Events into Articles, it turns
 *    them into nothing at all, and the page keeps rendering.
 * 2. A Calendar is chosen, and its named timezone becomes the Event default rather than the site
 *    zone being silently stamped on it.
 * 3. An Event cannot reach `publish` without its envelope, because that state is invisible: a
 *    public page, no Object, and no error anywhere.
 *
 * Written through the REST field rather than by calling the writer, so the seam the panel actually
 * uses is what is under test.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_ev_results = array();
$ax_ev_posts   = array();
$ax_ev_users   = array();
$ax_ev_calendars = array();

/** @param bool[] $results Results. */
function ax_ev_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Write the envelope the way the panel does: through the REST resource. */
function ax_ev_rest_write( int $post_id, array $envelope, string $status = '' ) {
	$request = new WP_REST_Request( 'POST', '/wp/v2/' . AXISMUNDI_CAL_EVENT_POST_TYPE . '/' . $post_id );
	$body    = array( 'axismundi_cal_envelope' => $envelope );
	if ( '' !== $status ) {
		$body['status'] = $status;
	}
	$request->set_body_params( $body );
	return rest_get_server()->dispatch( $request );
}

try {
	// A public Actor with a handle, because the projection resolves `attributedTo` from it and an
	// Object with no author is refused by the renderer -- correctly, but it would look here like
	// the Event transformer failing.
	$ax_ev_login   = 'ax_ev_' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_ev_editor  = (int) wp_insert_user(
		array( 'user_login' => $ax_ev_login, 'user_pass' => wp_generate_password(), 'role' => 'editor' )
	);
	$ax_ev_users[] = $ax_ev_editor;
	wp_set_current_user( $ax_ev_editor );
	if ( function_exists( 'axismundi_actors_ensure_for_user' ) ) {
		$ax_ev_actor = axismundi_actors_ensure_for_user( $ax_ev_editor );
		if ( $ax_ev_actor instanceof Axismundi_Actor ) {
			axismundi_actors_register_handle( $ax_ev_actor->get_identity_id(), $ax_ev_login );
			axismundi_actors_set_status( $ax_ev_actor->get_identity_id(), 'public' );
		}
	}

	$ax_ev_post = (int) wp_insert_post(
		array(
			'post_type'    => AXISMUNDI_CAL_EVENT_POST_TYPE,
			'post_status'  => 'draft',
			'post_author'  => $ax_ev_editor,
			'post_title'   => 'Authoring fixture Event',
			'post_content' => 'Fixture body.',
		)
	);
	$ax_ev_posts[] = $ax_ev_post;
	$ax_ev_calendar = axismundi_cal_calendar_save( array( 'name' => 'Authoring calendar', 'slug' => 'audit-authoring-' . $ax_ev_post, 'timezone' => 'Asia/Seoul' ) );
	// Published on purpose. Every surface these fixtures exercise is a public one, and a Calendar is
	// private until somebody says otherwise, so the fixture has to say so.
	axismundi_cal_acl_grant( (int) $ax_ev_calendar, '', 'reader', 'public' );
	$ax_ev_calendars[] = (int) $ax_ev_calendar;

	// -- The field exists and reports an empty envelope in one stable shape -----------------

	$ax_ev_read = axismundi_cal_rest_envelope( $ax_ev_post );
	ax_ev_assert( $ax_ev_results, 'an Event with no envelope reads as an empty one rather than null, so the panel has one shape to render', is_array( $ax_ev_read ) && false === $ax_ev_read['complete'] && '' === $ax_ev_read['timezone'] );

	// -- A partial draft is held, not rejected ---------------------------------------------

	$ax_ev_partial = ax_ev_rest_write( $ax_ev_post, array( 'eventStatus' => 'EventScheduled' ) );
	ax_ev_assert( $ax_ev_results, 'a partially filled draft does not error, so authoring one field at a time is possible', $ax_ev_partial instanceof WP_REST_Response && 200 === $ax_ev_partial->get_status() );
	ax_ev_assert( $ax_ev_results, 'and nothing is stored from it, because the table cannot hold half an Event', null === axismundi_cal_event_get( $ax_ev_post ) );

	// -- The Calendar is chosen; timezone inherits from it ----------------------------------

	$ax_ev_no_calendar = axismundi_cal_event_save(
		$ax_ev_post,
		array( 'starts_at' => '2026-09-01 19:00:00', 'ends_at' => '2026-09-01 21:00:00' )
	);
	ax_ev_assert( $ax_ev_results, 'the writer refuses an Event with no Calendar instead of stamping the site\'s timezone', is_wp_error( $ax_ev_no_calendar ) && 'ax_event_calendar' === $ax_ev_no_calendar->get_error_code() );
	ax_ev_assert( $ax_ev_results, 'and nothing is stored until an owning Calendar is chosen', null === axismundi_cal_event_get( $ax_ev_post ) );

	// -- Publishing without an envelope is refused -----------------------------------------

	$ax_ev_early = ax_ev_rest_write( $ax_ev_post, array( 'eventStatus' => 'EventScheduled' ), 'publish' );
	ax_ev_assert( $ax_ev_results, 'publishing an Event with no envelope is refused, instead of publishing an Object nobody can see', $ax_ev_early instanceof WP_Error || ( $ax_ev_early instanceof WP_REST_Response && 400 === $ax_ev_early->get_status() ) );
	ax_ev_assert( $ax_ev_results, 'and the post really did not publish', 'publish' !== get_post_status( $ax_ev_post ) );

	$ax_ev_direct = wp_update_post( array( 'ID' => $ax_ev_post, 'post_status' => 'publish' ), true );
	ax_ev_assert( $ax_ev_results, 'a non-REST publish is held at draft rather than going public with no Object', ! is_wp_error( $ax_ev_direct ) && 'publish' !== get_post_status( $ax_ev_post ) );

	// -- A complete envelope authored through the field ------------------------------------

	$ax_ev_full = ax_ev_rest_write(
		$ax_ev_post,
		array(
			'calendarId'     => (int) $ax_ev_calendar,
			'startsAt'       => '2026-09-01 19:00:00',
			'endsAt'         => '2026-09-01 21:00:00',
			'joinMode'       => 'free',
			'displayEndTime' => true,
		),
		'publish'
	);
	ax_ev_assert( $ax_ev_results, 'a complete envelope saves and publishes through the same field the panel writes', $ax_ev_full instanceof WP_REST_Response && 200 === $ax_ev_full->get_status() && 'publish' === get_post_status( $ax_ev_post ) );

	$ax_ev_stored = axismundi_cal_event_get( $ax_ev_post );
	ax_ev_assert( $ax_ev_results, 'the local wall time is stored as given, with UTC derived from the Calendar timezone', is_array( $ax_ev_stored ) && '2026-09-01 19:00:00' === $ax_ev_stored['starts_at'] && '2026-09-01 10:00:00' === $ax_ev_stored['starts_at_gmt'] && (int) $ax_ev_calendar === (int) $ax_ev_stored['calendar_id'] );

	// -- The projection is an Event, not an Article ----------------------------------------

	// -- The permalink, and the identity that is not the permalink ------------------------

	ax_ev_assert( $ax_ev_results, 'an Event is readable at /event/{slug}', 1 === preg_match( '#/event/[^/]+/?$#', (string) get_permalink( $ax_ev_post ) ) );
	// Registered rewrite rules rather than the permalink alone: `get_permalink()` composes a URL
	// from the post type's settings and answers the same whether or not anything routes it, so it
	// cannot tell a working permalink from a 404.
	$ax_ev_rules = (array) get_option( 'rewrite_rules', array() );
	$ax_ev_event_rules = array_filter( array_keys( $ax_ev_rules ), static fn( string $rule ) : bool => str_starts_with( $rule, 'event/' ) );
	ax_ev_assert( $ax_ev_results, 'and the rewrite rules to route it exist, which activation is responsible for creating', count( $ax_ev_event_rules ) > 0 );

	/*
	 * Identity is the stable post URI, not the permalink. This is what lets the plugin be renamed,
	 * the slug edited and the permalink base moved without every peer holding the old URI deciding
	 * it is looking at a different Object.
	 */
	$ax_ev_uri = axismundi_cal_event_object_uri( get_post( $ax_ev_post ) );
	ax_ev_assert( $ax_ev_results, 'the canonical Object URI is the stable post URI, not the permalink', str_contains( $ax_ev_uri, '?p=' . $ax_ev_post ) && $ax_ev_uri !== get_permalink( $ax_ev_post ) );

	$ax_ev_object = axismundi_op_transform_object( get_post( $ax_ev_post ) );
	ax_ev_assert( $ax_ev_results, 'a published Event projects as an Event', is_array( $ax_ev_object ) && 'Event' === ( $ax_ev_object['type'] ?? '' ) );
	ax_ev_assert( $ax_ev_results, 'and not as an Article, which is what a lost transformer would leave behind', is_array( $ax_ev_object ) && 'Article' !== ( $ax_ev_object['type'] ?? '' ) );
	ax_ev_assert( $ax_ev_results, 'the wire carries the offset of the chosen zone, and the IANA name separately', is_array( $ax_ev_object ) && str_contains( (string) ( $ax_ev_object['startTime'] ?? '' ), '+09:00' ) && 'Asia/Seoul' === ( $ax_ev_object['timezone'] ?? '' ) );

	// -- Refusals the panel surfaces --------------------------------------------------------

	$ax_ev_backwards = ax_ev_rest_write( $ax_ev_post, array( 'startsAt' => '2026-09-01 19:00:00', 'endsAt' => '2026-09-01 18:00:00' ) );
	ax_ev_assert( $ax_ev_results, 'an Event that ends before it starts is refused through the field, so the panel can show why', $ax_ev_backwards instanceof WP_Error || ( $ax_ev_backwards instanceof WP_REST_Response && 200 !== $ax_ev_backwards->get_status() ) );

	$ax_ev_external = ax_ev_rest_write( $ax_ev_post, array( 'joinMode' => 'external', 'externalParticipationUrl' => '' ) );
	ax_ev_assert( $ax_ev_results, 'external participation with no URL is refused rather than published as a dead end', $ax_ev_external instanceof WP_Error || ( $ax_ev_external instanceof WP_REST_Response && 200 !== $ax_ev_external->get_status() ) );

	// -- A move is remembered ---------------------------------------------------------------

	ax_ev_rest_write( $ax_ev_post, array( 'startsAt' => '2026-09-02 19:00:00', 'endsAt' => '2026-09-02 21:00:00' ) );
	$ax_ev_moved = axismundi_cal_event_get( $ax_ev_post );
	ax_ev_assert( $ax_ev_results, 'rescheduling records the previous start, which is what tells a peer this is a move', is_array( $ax_ev_moved ) && '2026-09-01 10:00:00' === (string) $ax_ev_moved['previous_starts_at_gmt'] );

	// -- The panel offers owned local Calendars, never remote caches ---------------------------

	$ax_ev_calendars_for_panel = axismundi_cal_editor_calendars();
	ax_ev_assert( $ax_ev_results, 'the panel offers the local Calendar that owns new Events', in_array( (int) $ax_ev_calendar, array_column( $ax_ev_calendars_for_panel, 'id' ), true ) );
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_ev_posts ) as $ax_ev_post_id ) {
		wp_delete_post( (int) $ax_ev_post_id, true );
	}
	foreach ( array_unique( $ax_ev_calendars ) as $ax_ev_calendar_id ) {
		axismundi_cal_calendar_delete( (int) $ax_ev_calendar_id );
	}
	foreach ( array_unique( $ax_ev_users ) as $ax_ev_user_id ) {
		wp_delete_user( (int) $ax_ev_user_id );
	}
}

$ax_ev_failures = count( array_filter( $ax_ev_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ev_results ), $ax_ev_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ev_failures > 0 ? 1 : 0 );
}
exit( $ax_ev_failures > 0 ? 1 : 0 );
