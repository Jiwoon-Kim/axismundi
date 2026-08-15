<?php
/**
 * A Calendar as a JSCalendar Group, and the contact card that links to it (dev-only; dist-excluded).
 *
 * Two claims are under test. The document is an agenda rather than an archive -- a public calendar
 * accumulates years of finished events and sending all of them to anybody who opens the link is a
 * bill nobody agreed to pay -- and the link on an Actor's card points only at a calendar that Actor
 * actually published, since a contact card naming a private address is directions around the sharing
 * rules.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_gr_results = array();
$ax_gr_users   = array();
$ax_gr_posts   = array();

/** @param bool[] $results Results. */
function ax_gr_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An activated, published Person. */
function ax_gr_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axgr' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axgr' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

/** One published Event on a calendar. */
function ax_gr_event( array &$posts, int $author, int $calendar_id, string $title, string $start, string $end, array $extra = array() ) : int {
	$post_id = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_title' => $title, 'post_status' => 'draft', 'post_author' => $author )
	);
	$posts[] = $post_id;
	axismundi_cal_event_save( $post_id, array_merge( array( 'calendar_id' => $calendar_id, 'starts_at' => $start, 'ends_at' => $end, 'timezone' => 'UTC' ), $extra ) );
	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;
	return $post_id;
}

/** Titles in a Group document. */
function ax_gr_titles( array $group ) : array {
	return array_map( static fn( array $entry ) : string => (string) $entry['title'], (array) $group['entries'] );
}

try {
	$ax_gr_user  = ax_gr_user( $ax_gr_users );
	wp_set_current_user( $ax_gr_user );
	$ax_gr_actor = axismundi_actors_get_for_user( $ax_gr_user );
	$ax_gr_cal   = axismundi_cal_primary_calendar( (string) $ax_gr_actor->get_uri() );
	$ax_gr_id    = (int) $ax_gr_cal['id'];

	$ax_gr_past    = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
	$ax_gr_running = gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) );
	$ax_gr_soon    = gmdate( 'Y-m-d H:i:s', strtotime( '+7 days' ) );

	ax_gr_event( $ax_gr_posts, $ax_gr_user, $ax_gr_id, 'Finished', $ax_gr_past, gmdate( 'Y-m-d H:i:s', strtotime( '-30 days +2 hours' ) ) );
	ax_gr_event( $ax_gr_posts, $ax_gr_user, $ax_gr_id, 'Happening now', $ax_gr_running, gmdate( 'Y-m-d H:i:s', strtotime( '+3 hours' ) ) );
	ax_gr_event( $ax_gr_posts, $ax_gr_user, $ax_gr_id, 'Coming up', $ax_gr_soon, gmdate( 'Y-m-d H:i:s', strtotime( '+7 days +1 hour' ) ) );
	ax_gr_event( $ax_gr_posts, $ax_gr_user, $ax_gr_id, 'Still weekly', $ax_gr_past, gmdate( 'Y-m-d H:i:s', strtotime( '-30 days +1 hour' ) ), array( 'rrule' => 'FREQ=WEEKLY' ) );
	ax_gr_event( $ax_gr_posts, $ax_gr_user, $ax_gr_id, 'Series that ended', $ax_gr_past, gmdate( 'Y-m-d H:i:s', strtotime( '-30 days +1 hour' ) ), array( 'rrule' => 'FREQ=DAILY;COUNT=2' ) );

	// -- not published, not served -------------------------------------------------------------------

	ax_gr_assert(
		$ax_gr_results,
		'a calendar nobody published is not served as a Group either',
		is_wp_error( axismundi_cal_jscalendar_group( array() ) )
			&& ! axismundi_cal_collection_visible( new Axismundi_Cal_Collection( axismundi_cal_calendar_get( $ax_gr_id ) ) )
	);
	axismundi_cal_acl_grant( $ax_gr_id, '', 'reader', 'public' );
	$ax_gr_group  = axismundi_cal_jscalendar_group( axismundi_cal_calendar_get( $ax_gr_id ) );
	$ax_gr_titles = ax_gr_titles( $ax_gr_group );

	// -- the envelope JSCalendar already has ------------------------------------------------------------

	/*
	 * `Group` is the standard's own container for calendar objects, so nothing here invents a wrapper
	 * for a job RFC 8984 already does -- and `source` names the Calendar rather than this rendering of
	 * it, which is what lets the other representations be found from here.
	 */
	ax_gr_assert(
		$ax_gr_results,
		'the document is a JSCalendar Group naming the Calendar it came from',
		is_array( $ax_gr_group )
			&& 'Group' === (string) $ax_gr_group['@type']
			&& 'urn:uuid:' . $ax_gr_cal['uuid'] === (string) $ax_gr_group['uid']
			&& axismundi_cal_collection_uri_for( axismundi_cal_calendar_get( $ax_gr_id ) ) === (string) $ax_gr_group['source']
	);

	// -- an agenda, not an archive -----------------------------------------------------------------------

	/*
	 * The narrowing, and the case that is easiest to get wrong in the direction people notice: an Event
	 * that started an hour ago and runs until tonight is on today's agenda, and dropping it the moment
	 * it begins would fail exactly when a calendar is most looked at.
	 */
	ax_gr_assert(
		$ax_gr_results,
		'what is happening now and what is coming are both in it',
		in_array( 'Happening now', $ax_gr_titles, true ) && in_array( 'Coming up', $ax_gr_titles, true )
	);
	ax_gr_assert(
		$ax_gr_results,
		'and what is over is not, so opening the link does not send years of finished events',
		! in_array( 'Finished', $ax_gr_titles, true ) && ! in_array( 'Series that ended', $ax_gr_titles, true )
	);
	// A series is judged by whether it still has occurrences, not by when it started.
	ax_gr_assert(
		$ax_gr_results,
		'a series that began long ago is current if it is still running',
		in_array( 'Still weekly', $ax_gr_titles, true )
	);
	// The entries are whole JSCalendar objects, so a reader needs no second request per event.
	ax_gr_assert(
		$ax_gr_results,
		'each entry is a JSCalendar Event rather than a link to fetch one',
		array() !== (array) $ax_gr_group['entries']
			&& 'Event' === (string) $ax_gr_group['entries'][0]['@type']
			&& '' !== (string) $ax_gr_group['entries'][0]['uid']
	);

	// -- the link on the contact card ---------------------------------------------------------------------

	/*
	 * Contributed by this plugin rather than assembled in Actors, because the calendar is this plugin's
	 * fact. And only a published one: a card naming a private address is directions around the sharing
	 * rules.
	 */
	$ax_gr_card = axismundi_actors_jscontact_card( axismundi_actors_get_by_identity( $ax_gr_actor->get_identity_id() ) );
	ax_gr_assert(
		$ax_gr_results,
		'a published calendar is on the Actor\'s contact card, with the media type it actually answers with',
		axismundi_cal_jscalendar_group_url( axismundi_cal_calendar_get( $ax_gr_id ) ) === (string) $ax_gr_card['calendars']['primary']['uri']
			&& AXISMUNDI_CAL_JSCALENDAR_GROUP_MEDIA_TYPE === (string) $ax_gr_card['calendars']['primary']['mediaType']
	);
	axismundi_cal_acl_revoke( $ax_gr_id, '', 'public' );
	$ax_gr_private_card = axismundi_actors_jscontact_card( axismundi_actors_get_by_identity( $ax_gr_actor->get_identity_id() ) );
	ax_gr_assert(
		$ax_gr_results,
		'and unpublishing it takes the address off the card rather than leaving a link that 404s',
		! isset( $ax_gr_private_card['calendars'] )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_gr_posts ) as $ax_gr_post_id ) {
		wp_delete_post( (int) $ax_gr_post_id, true );
	}
	foreach ( array_unique( $ax_gr_users ) as $ax_gr_user_id ) {
		wp_delete_user( (int) $ax_gr_user_id );
	}
}

$ax_gr_failures = count( array_filter( $ax_gr_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_gr_results ), $ax_gr_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_gr_failures > 0 ? 1 : 0 );
}
exit( $ax_gr_failures > 0 ? 1 : 0 );
