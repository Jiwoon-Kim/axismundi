<?php
/**
 * Where an Event happens, when that is more than one place (dev-only; dist-excluded).
 *
 * FEP-8a8e takes `location` as a list, and a hybrid Event is the ordinary reason: a room, a meeting
 * link, and often a stream of the same thing. One text column could hold the first of those and
 * nothing else, so the model here is a list and "hybrid" is derived from what is in it rather than
 * stored beside it as a flag that can disagree.
 *
 * iCalendar is narrower than the federated model, and the projection is where that shows. `LOCATION`
 * is a single TEXT property, so several physical places cannot each be one; `CONFERENCE` (RFC 7986)
 * takes as many remote-participation URIs as there are. The rules are asserted here because a lossy
 * projection is right and an accidental one is not -- repeating `LOCATION` produces a document some
 * clients reject and others silently take the last of.
 *
 * The per-occurrence override is a different axis and keeps working: a series whose fourth meeting
 * moved to another room is not an Event with two locations.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

/** A link name carrying every character that could end a parameter early. */
const AX_EL_AWKWARD = '"Main"; Hall, upstairs';
const AX_EL_AWKWARD_EXPECTED = 'LABEL="Main; Hall, upstairs"';

global $wpdb;
$ax_el_results   = array();
$ax_el_posts     = array();
$ax_el_calendars = array();
$ax_el_users     = array();

/** @param bool[] $results Results. */
function ax_el_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** The content lines of one document, unfolded. */
function ax_el_lines( string $body ) : array {
	return explode( "\r\n", str_replace( "\r\n ", '', $body ) );
}

/** Every line beginning with one property name. */
function ax_el_props( string $body, string $property ) : array {
	return array_values( array_filter( ax_el_lines( $body ), static function ( string $line ) use ( $property ) : bool {
		return str_starts_with( $line, $property . ':' ) || str_starts_with( $line, $property . ';' );
	} ) );
}

try {
	$ax_el_login = 'ax_el_' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_el_user  = (int) wp_insert_user( array( 'user_login' => $ax_el_login, 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
	$ax_el_users[] = $ax_el_user;
	wp_set_current_user( $ax_el_user );
	$ax_el_uri = '';
	if ( function_exists( 'axismundi_actors_ensure_for_user' ) ) {
		$ax_el_actor = axismundi_actors_ensure_for_user( $ax_el_user );
		if ( $ax_el_actor instanceof Axismundi_Actor ) {
			axismundi_actors_set_status( $ax_el_actor->get_identity_id(), 'public' );
			$ax_el_uri = (string) $ax_el_actor->get_uri();
		}
	}

	$ax_el_suffix   = strtolower( wp_generate_password( 6, false, false ) );
	$ax_el_calendar = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Locations fixture', 'slug' => 'ax-el-' . $ax_el_suffix, 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_el_uri )
	);
	$ax_el_calendars[] = $ax_el_calendar;
	axismundi_cal_acl_grant( $ax_el_calendar, '', 'reader', 'public' );

	$ax_el_make = static function ( array &$posts, int $calendar, string $title, array $fields = array() ) : int {
		$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_title' => $title ) );
		$posts[] = $post_id;
		axismundi_cal_event_save(
			$post_id,
			array_merge( array( 'calendar_id' => $calendar, 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-09-15 19:00:00', 'ends_at' => '2026-09-15 21:00:00' ), $fields )
		);
		$GLOBALS['axismundi_cal_rest_write'] = true;
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		$GLOBALS['axismundi_cal_rest_write'] = false;
		return $post_id;
	};

	// -- A list, in the order somebody arranged it -----------------------------------------------------

	$ax_el_hybrid = $ax_el_make(
		$ax_el_posts,
		$ax_el_calendar,
		'Hybrid meeting',
		array(
			'locations' => array(
				array( 'kind' => 'physical', 'label' => 'Fediverse University', 'address_text' => '15 Fediverse Street' ),
				array( 'kind' => 'virtual', 'features' => array( 'VIDEO', 'AUDIO' ), 'label' => 'Google Meet', 'url' => 'https://meet.example.com/abc-defg' ),
				array( 'kind' => 'virtual', 'features' => array( 'FEED' ), 'label' => 'Live stream', 'url' => 'https://video.example.com/live' ),
				array( 'kind' => 'virtual', 'features' => array( 'VIDEO' ), 'access' => 'attendees', 'label' => 'Speakers room', 'url' => 'https://meet.example.com/private' ),
			),
		)
	);
	$ax_el_stored = axismundi_cal_event_locations( $ax_el_hybrid );
	ax_el_assert( $ax_el_results, 'an Event can hold several places at once', 4 === count( $ax_el_stored ) );
	ax_el_assert(
		$ax_el_results,
		'in the order they were given, since the first physical one is what the document names',
		'Fediverse University' === (string) $ax_el_stored[0]['label']
			&& 'Google Meet' === (string) $ax_el_stored[1]['label']
			&& 'Live stream' === (string) $ax_el_stored[2]['label']
	);

	/*
	 * Derived, not stored. A `hybrid` flag beside the list is a second answer to a question the list
	 * already answers, and the two would eventually disagree -- most likely when somebody removed the
	 * last virtual location and the flag stayed.
	 */
	ax_el_assert(
		$ax_el_results,
		'and whether it is hybrid follows from what is in the list rather than a flag beside it',
		'hybrid' === axismundi_cal_event_attendance_mode( $ax_el_hybrid )
	);
	$ax_el_room = $ax_el_make( $ax_el_posts, $ax_el_calendar, 'In a room', array( 'locations' => array( array( 'kind' => 'physical', 'label' => 'Room B' ) ) ) );
	$ax_el_call = $ax_el_make( $ax_el_posts, $ax_el_calendar, 'On a call', array( 'locations' => array( array( 'kind' => 'virtual', 'features' => array( 'VIDEO' ), 'label' => 'Jitsi', 'url' => 'https://meet.example.com/x' ) ) ) );
	ax_el_assert(
		$ax_el_results,
		'so a room is physical and a call is virtual without either saying so twice',
		'physical' === axismundi_cal_event_attendance_mode( $ax_el_room )
			&& 'virtual' === axismundi_cal_event_attendance_mode( $ax_el_call )
	);

	// -- What the writer refuses -----------------------------------------------------------------------

	ax_el_assert(
		$ax_el_results,
		'a virtual location without somewhere to go is refused, since that is all it is',
		is_wp_error( axismundi_cal_event_save( $ax_el_room, array( 'locations' => array( array( 'kind' => 'virtual', 'label' => 'Nowhere' ) ) ) ) )
	);
	ax_el_assert(
		$ax_el_results,
		'and a kind nothing defines is refused rather than stored',
		is_wp_error( axismundi_cal_event_save( $ax_el_room, array( 'locations' => array( array( 'kind' => 'somewhere', 'label' => 'x' ) ) ) ) )
	);
	ax_el_assert(
		$ax_el_results,
		'while an Event with no location at all is ordinary and stays writable',
		! is_wp_error( axismundi_cal_event_save( $ax_el_room, array( 'locations' => array() ) ) )
			&& array() === axismundi_cal_event_locations( $ax_el_room )
	);

	// -- The document, which is narrower ---------------------------------------------------------------

	$ax_el_body = (string) axismundi_cal_site_feed( $ax_el_calendar, 'Locations fixture', 'Asia/Seoul' )['body'];

	/*
	 * `LOCATION` is one TEXT property per VEVENT. Repeating it produces a document some clients reject
	 * and others take the last of, so several physical places are reduced to the first rather than
	 * emitted as several -- a lossy projection on purpose, where an accidental one would be a bug.
	 */
	$ax_el_two_rooms = $ax_el_make(
		$ax_el_posts,
		$ax_el_calendar,
		'Two rooms',
		array(
			'locations' => array(
				array( 'kind' => 'physical', 'label' => 'North hall' ),
				array( 'kind' => 'physical', 'label' => 'South hall' ),
			),
		)
	);
	$ax_el_body = (string) axismundi_cal_site_feed( $ax_el_calendar, 'Locations fixture', 'Asia/Seoul' )['body'];
	$ax_el_two_lines = array_values( array_filter(
		ax_el_lines( $ax_el_body ),
		static fn( string $line ) : bool => str_contains( $line, 'North hall' ) || str_contains( $line, 'South hall' )
	) );
	ax_el_assert(
		$ax_el_results,
		'a VEVENT names one place, because LOCATION is a single property and repeating it is malformed',
		1 === count( $ax_el_two_lines ) && str_starts_with( $ax_el_two_lines[0], 'LOCATION:' )
	);
	ax_el_assert( $ax_el_results, 'and it is the first one, which is the one somebody put first', str_contains( $ax_el_two_lines[0], 'North hall' ) );

	/*
	 * `CONFERENCE` is the opposite: it exists to carry remote-participation URIs and takes as many as
	 * there are. A meeting link belongs there rather than in `LOCATION`, which is text a client shows
	 * and cannot join.
	 */
	$ax_el_conf = ax_el_props( $ax_el_body, 'CONFERENCE' );
	ax_el_assert(
		$ax_el_results,
		'a meeting link is exported as CONFERENCE rather than as text somebody has to copy',
		1 === count( array_filter( $ax_el_conf, static fn( string $l ) : bool => str_contains( $l, 'meet.example.com/abc-defg' ) ) )
	);
	$ax_el_meet = array_values( array_filter( $ax_el_conf, static fn( string $l ) : bool => str_contains( $l, 'meet.example.com/abc-defg' ) ) );
	ax_el_assert(
		$ax_el_results,
		// `VALUE=URI` is what tells a client this is something to open rather than text to read. Which
		// features it carries is asserted below, where the ordering also matters.
		'declared as a URI a client can act on rather than as text',
		array() !== $ax_el_meet && str_contains( $ax_el_meet[0], 'VALUE=URI' )
	);
	/*
	 * A parameter value with a space in it has to be quoted. Unquoted, "Google Meet" ends the parameter
	 * at the space and the rest of the line is something no two parsers agree about -- which looks
	 * exactly like a working feed until a client refuses it.
	 */
	ax_el_assert(
		$ax_el_results,
		'and any label it carries is quoted, since a bare space would end the parameter',
		array() !== $ax_el_meet && str_contains( $ax_el_meet[0], 'LABEL="Google Meet"' )
	);
	/*
	 * A stream is a `CONFERENCE` too. RFC 7986 defines `FEED` for exactly this, so a broadcast and a
	 * meeting are one kind of thing offering different features -- splitting them into separate models
	 * would invent a distinction neither iCalendar nor FEP-8a8e makes.
	 */
	$ax_el_stream = array_values( array_filter( $ax_el_conf, static fn( string $l ) : bool => str_contains( $l, 'video.example.com/live' ) ) );
	ax_el_assert(
		$ax_el_results,
		'a stream is a CONFERENCE as well, offering FEED rather than being a kind of its own',
		array() !== $ax_el_stream && str_contains( $ax_el_stream[0], 'FEATURE=FEED' )
	);
	ax_el_assert(
		$ax_el_results,
		'and several features are listed together, since one link can offer more than one',
		array() !== $ax_el_meet && str_contains( $ax_el_meet[0], 'FEATURE=AUDIO,VIDEO' )
	);

	/*
	 * The leak this axis exists to prevent. A public Event with a private joining link is ordinary --
	 * the address is announced and the meeting URL is for the people coming -- and the open feed is
	 * handed to anybody who has the calendar. Published there, the link is public whatever the Event
	 * says about itself.
	 */
	ax_el_assert(
		$ax_el_results,
		'a link kept for attendees stays out of the public document, which everybody with the calendar reads',
		0 === count( array_filter( $ax_el_conf, static fn( string $l ) : bool => str_contains( $l, 'meet.example.com/private' ) ) )
	);
	ax_el_assert(
		$ax_el_results,
		'though it is still stored, since the people attending have to be told somewhere',
		1 === count( array_filter(
			axismundi_cal_event_locations( $ax_el_hybrid ),
			static fn( array $l ) : bool => 'attendees' === (string) $l['access'] && str_contains( (string) $l['url'], 'private' )
		) )
	);
	ax_el_assert(
		$ax_el_results,
		'and a feature nothing in RFC 7986 defines is refused rather than stored',
		is_wp_error( axismundi_cal_event_save( $ax_el_room, array( 'locations' => array( array( 'kind' => 'virtual', 'url' => 'https://x.example', 'features' => array( 'TELEPATHY' ) ) ) ) ) )
	);

	// -- The document names a place it may name -------------------------------------------------------

	/*
	 * Filtered before the choice, not after. An Event whose first venue is for attendees and whose
	 * second is announced has a public address, and picking the first and then dropping it leaves
	 * `LOCATION` empty -- the address published everywhere except the document people subscribe to.
	 */
	$ax_el_private_first = $ax_el_make(
		$ax_el_posts,
		$ax_el_calendar,
		'Private then public',
		array(
			'locations' => array(
				array( 'kind' => 'physical', 'label' => 'Green room', 'access' => 'attendees' ),
				array( 'kind' => 'physical', 'label' => 'Main auditorium' ),
			),
		)
	);
	$ax_el_after = ax_el_lines( (string) axismundi_cal_site_feed( $ax_el_calendar, 'Locations fixture', 'Asia/Seoul' )['body'] );
	ax_el_assert(
		$ax_el_results,
		'a document names the first place it may name rather than the first place there is',
		in_array( 'LOCATION:Main auditorium', $ax_el_after, true )
	);
	ax_el_assert(
		$ax_el_results,
		'and never the one kept back, which choosing first and filtering second would have printed',
		0 === count( array_filter( $ax_el_after, static fn( string $l ) : bool => str_contains( $l, 'Green room' ) ) )
	);

	// -- Attendees means nobody yet --------------------------------------------------------------------

	/*
	 * `attendees` has no members until there is a way to become one. Read as "any logged-in reader" it
	 * would publish a private joining link to every account on the site and call that restricted, so
	 * until Join and Invite exist it means the people who maintain the Event.
	 */
	$ax_el_reader  = (int) wp_insert_user( array( 'user_login' => 'ax_el_r_' . strtolower( wp_generate_password( 6, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
	$ax_el_users[] = $ax_el_reader;
	$ax_el_author  = get_current_user_id();
	wp_set_current_user( $ax_el_reader );
	$ax_el_seen = (array) axismundi_cal_rest_envelope( $ax_el_hybrid )['locations'];
	wp_set_current_user( $ax_el_author );
	ax_el_assert(
		$ax_el_results,
		'a logged-in reader is not an attendee, so the envelope withholds the link kept for them',
		0 === count( array_filter( $ax_el_seen, static fn( array $l ) : bool => str_contains( (string) $l['url'], 'private' ) ) )
	);
	ax_el_assert(
		$ax_el_results,
		'while somebody who maintains it still sees every one, or they could not edit what they set',
		1 === count( array_filter(
			(array) axismundi_cal_rest_envelope( $ax_el_hybrid )['locations'],
			static fn( array $l ) : bool => str_contains( (string) $l['url'], 'private' )
		) )
	);

	// -- A name is not a parser -----------------------------------------------------------------------

	/*
	 * The parameter is quoted, so semicolons and commas inside it are safe -- but a double quote has no
	 * escape in RFC 5545 and would close the value early, after which the rest reads as another
	 * parameter. This is the kind of thing that surfaces the day somebody names a link.
	 */
	$ax_el_awkward = $ax_el_make(
		$ax_el_posts,
		$ax_el_calendar,
		'Awkward label',
		array( 'locations' => array( array( 'kind' => 'virtual', 'features' => array( 'VIDEO' ), 'label' => AX_EL_AWKWARD, 'url' => 'https://meet.example.com/awkward' ) ) )
	);
	$ax_el_awkward_line = array_values( array_filter(
		ax_el_props( (string) axismundi_cal_site_feed( $ax_el_calendar, 'Locations fixture', 'Asia/Seoul' )['body'], 'CONFERENCE' ),
		static fn( string $l ) : bool => str_contains( $l, 'meet.example.com/awkward' )
	) );
	ax_el_assert(
		$ax_el_results,
		'a label carrying quotes, semicolons and commas still produces one parameter and one value',
		array() !== $ax_el_awkward_line
			&& str_contains( $ax_el_awkward_line[0], AX_EL_AWKWARD_EXPECTED )
			&& 1 === substr_count( $ax_el_awkward_line[0], 'LABEL=' )
	);
	ax_el_assert(
		$ax_el_results,
		'with the URI still the value rather than something the label ran into',
		array() !== $ax_el_awkward_line && str_ends_with( $ax_el_awkward_line[0], ':https://meet.example.com/awkward' )
	);

	// -- A Place is somebody else's object ------------------------------------------------------------

	/*
	 * The reference may dangle and the Event must not. A `Place` belongs to the geodata plugin, which
	 * can be deactivated or can delete one -- and an Event whose venue stopped rendering because of
	 * that would be broken by a plugin it does not depend on.
	 */
	$ax_el_referenced = $ax_el_make(
		$ax_el_posts,
		$ax_el_calendar,
		'Refers to a Place',
		array( 'locations' => array( array( 'kind' => 'physical', 'label' => 'Somewhere real', 'address_text' => '1 Example Street', 'place_id' => 999999 ) ) )
	);
	$ax_el_ref_row = axismundi_cal_event_locations( $ax_el_referenced )[0];
	ax_el_assert(
		$ax_el_results,
		'a Place reference nothing resolves leaves the address readable rather than the location empty',
		999999 === (int) $ax_el_ref_row['place_id']
			&& 'Somewhere real, 1 Example Street' === axismundi_cal_event_place_text( $ax_el_ref_row )
	);

	// -- One instance moving is a different question ---------------------------------------------------

	/*
	 * A series whose fourth meeting is in another room is not an Event with two locations. The
	 * per-occurrence override answers that, and it goes on working off the primary physical place --
	 * which is kept beside the schedule so the override has a baseline to differ from.
	 */
	/*
	 * Nothing is copied. `location_text` on a schedule row means "this instance differs", so an Event
	 * that has only a list leaves it empty -- writing the first entry there would make one fact true in
	 * two places, and a later edit to the list would leave the copy behind.
	 */
	ax_el_assert(
		$ax_el_results,
		'the list is not copied onto the schedule, which would be the same fact stored twice',
		'' === (string) axismundi_cal_schedule_for_event( $ax_el_two_rooms )['location_text']
	);
	ax_el_assert(
		$ax_el_results,
		'yet the document still names the place, because the reader resolves it rather than reading a copy',
		in_array( 'LOCATION:North hall', ax_el_lines( (string) axismundi_cal_site_feed( $ax_el_calendar, 'Locations fixture', 'Asia/Seoul' )['body'] ), true )
	);
	/*
	 * A changed venue is a changed Event to somebody holding it, and the schedule's own columns cannot
	 * see a table that is not theirs. Without this, moving a meeting would reach subscribers as an edit
	 * they are never told to look at.
	 */
	ax_el_assert(
		$ax_el_results,
		'and moving it raises the sequence, which is how a subscriber is told to look again',
		( static function () use ( $ax_el_two_rooms ) : bool {
			$before = (int) axismundi_cal_schedule_for_event( $ax_el_two_rooms )['sequence'];
			axismundi_cal_event_save( $ax_el_two_rooms, array( 'locations' => array( array( 'kind' => 'physical', 'label' => 'East wing' ) ) ) );
			return (int) axismundi_cal_schedule_for_event( $ax_el_two_rooms )['sequence'] > $before;
		} )()
	);

	// -- What an author can reach ----------------------------------------------------------------------

	$ax_el_read = (array) axismundi_cal_rest_envelope( $ax_el_hybrid )['locations'];
	ax_el_assert(
		$ax_el_results,
		'the list reads back through the envelope the editor uses, whole and in order',
		4 === count( $ax_el_read )
			&& 3 === count( array_filter( $ax_el_read, static fn( array $l ) : bool => 'virtual' === (string) $l['kind'] ) )
			&& 'Fediverse University' === (string) $ax_el_read[0]['label']
	);
	$ax_el_panel = (string) file_get_contents( dirname( __DIR__ ) . '/assets/editor/event-panel.js' );
	ax_el_assert(
		$ax_el_results,
		'and the editor can add and remove them, without which the table is unreachable',
		str_contains( $ax_el_panel, "key: 'locations'" ) && str_contains( $ax_el_panel, 'Add location' )
	);
	/*
	 * The old single field is gone from the panel rather than left beside the list. Two controls for
	 * one fact is how they come to disagree, and the one somebody edits is whichever they saw first.
	 */
	ax_el_assert(
		$ax_el_results,
		'while the single location field it replaces is not left beside it',
		! str_contains( $ax_el_panel, "key: 'locationText'" )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_el_posts as $ax_el_post ) {
		wp_delete_post( (int) $ax_el_post, true );
	}
	foreach ( $ax_el_calendars as $ax_el_cal ) {
		axismundi_cal_calendar_delete( (int) $ax_el_cal );
	}
	foreach ( $ax_el_users as $ax_el_user_id ) {
		wp_delete_user( (int) $ax_el_user_id );
	}
}

$ax_el_failures = count( array_filter( $ax_el_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_el_results ), $ax_el_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_el_failures > 0 ? 1 : 0 );
}
exit( $ax_el_failures > 0 ? 1 : 0 );
