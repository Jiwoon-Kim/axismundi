<?php
/**
 * The Event time contract, end to end (dev-only; dist-excluded).
 *
 * Five surfaces have to agree about when an Event happens: the schedule table, the REST envelope the
 * editor writes through, the range query, the grid grouping, and the iCalendar document. Each one is
 * tested elsewhere; nothing was testing that they say the same thing.
 *
 * The failure this catches is a field that exists in storage and cannot be reached from the editor.
 * That is not a crash and not a wrong answer -- it is a feature that appears finished, works when
 * imported, and cannot be produced by anybody using the software. `all_day` was exactly that: a
 * column, a writer, an ICS branch, a grid branch, and no way to set it.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_et_results   = array();
$ax_et_posts     = array();
$ax_et_calendars = array();
$ax_et_users     = array();

/** @param bool[] $results Results. */
function ax_et_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** The content lines of one document, unfolded. */
function ax_et_lines( string $body ) : array {
	return explode( "\r\n", str_replace( "\r\n ", '', $body ) );
}

/** Write an Event through the REST envelope, the way the editor does. */
function ax_et_write( int $post_id, array $envelope ) {
	$request = new WP_REST_Request( 'POST', '/wp/v2/' . AXISMUNDI_CAL_EVENT_POST_TYPE . '/' . $post_id );
	$request->set_param( 'meta', array() );
	$fields = axismundi_cal_rest_to_fields( $envelope );
	return axismundi_cal_event_save( $post_id, $fields );
}

try {
	$ax_et_login = 'ax_et_' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_et_user  = (int) wp_insert_user( array( 'user_login' => $ax_et_login, 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
	$ax_et_users[] = $ax_et_user;
	wp_set_current_user( $ax_et_user );
	$ax_et_uri = '';
	if ( function_exists( 'axismundi_actors_ensure_for_user' ) ) {
		$ax_et_actor = axismundi_actors_ensure_for_user( $ax_et_user );
		if ( $ax_et_actor instanceof Axismundi_Actor ) {
			axismundi_actors_set_status( $ax_et_actor->get_identity_id(), 'public' );
			$ax_et_uri = (string) $ax_et_actor->get_uri();
		}
	}

	$ax_et_suffix   = strtolower( wp_generate_password( 6, false, false ) );
	$ax_et_calendar = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Event time fixture', 'slug' => 'ax-et-' . $ax_et_suffix, 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_et_uri )
	);
	$ax_et_calendars[] = $ax_et_calendar;
	axismundi_cal_acl_grant( $ax_et_calendar, '', 'reader', 'public' );

	$ax_et_make = static function ( array &$posts, string $title, array $envelope ) use ( $ax_et_calendar ) : int {
		$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_title' => $title ) );
		$posts[] = $post_id;
		ax_et_write( $post_id, array_merge( array( 'calendarId' => $ax_et_calendar ), $envelope ) );
		$GLOBALS['axismundi_cal_rest_write'] = true;
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		$GLOBALS['axismundi_cal_rest_write'] = false;
		return $post_id;
	};

	// -- 1. A whole day can be authored ----------------------------------------------------------------

	/*
	 * Through the REST envelope rather than the writer, because that is the seam that was missing: the
	 * column, the writer, the ICS branch and the grid branch all existed, and no editor could reach any
	 * of them. Asserting on `schedule_save()` would have passed the whole time.
	 */
	$ax_et_allday = $ax_et_make(
		$ax_et_posts,
		'Whole day',
		array( 'timezone' => 'Asia/Seoul', 'startsAt' => '2026-09-20 00:00:00', 'endsAt' => '2026-09-23 00:00:00', 'allDay' => true )
	);
	ax_et_assert(
		$ax_et_results,
		'an all-day Event can be written through the envelope the editor uses',
		1 === (int) axismundi_cal_schedule_for_event( $ax_et_allday )['all_day']
	);
	ax_et_assert(
		$ax_et_results,
		'and reads back as all-day, so the editor can show what it just saved',
		true === axismundi_cal_rest_envelope( $ax_et_allday )['allDay']
	);
	ax_et_assert(
		$ax_et_results,
		'while an ordinary Event is not all-day merely for having midnight times',
		false === axismundi_cal_rest_envelope(
			$ax_et_make( $ax_et_posts, 'Midnight but timed', array( 'timezone' => 'Asia/Seoul', 'startsAt' => '2026-09-01 00:00:00', 'endsAt' => '2026-09-02 00:00:00' ) )
		)['allDay']
	);

	// -- 2. A whole-day range keeps its exclusive end --------------------------------------------------

	/*
	 * `DTEND` is the day after the last one covered, which is the convention nobody guesses right. A
	 * three-day entry ending on the 23rd covers the 20th, 21st and 22nd -- and an off-by-one here shows
	 * up as a holiday lasting one day too long, which reads as data rather than as a bug.
	 */
	$ax_et_span = axismundi_cal_group_by_day(
		axismundi_cal_calendar_occurrences( $ax_et_calendar, '2026-09-01 00:00:00', '2026-10-01 00:00:00' ),
		new DateTimeZone( 'Asia/Seoul' )
	);
	$ax_et_on = static function ( array $days, string $date, string $title ) : bool {
		foreach ( $days[ $date ] ?? array() as $item ) {
			if ( $title === (string) $item['title'] ) {
				return true;
			}
		}
		return false;
	};
	ax_et_assert(
		$ax_et_results,
		'a whole-day range covers every day it runs over',
		$ax_et_on( $ax_et_span, '2026-09-20', 'Whole day' )
			&& $ax_et_on( $ax_et_span, '2026-09-21', 'Whole day' )
			&& $ax_et_on( $ax_et_span, '2026-09-22', 'Whole day' )
	);
	ax_et_assert(
		$ax_et_results,
		'and not the day its exclusive end names, which is the off-by-one this convention invites',
		! $ax_et_on( $ax_et_span, '2026-09-23', 'Whole day' )
	);

	/*
	 * Across a month boundary, because the grid asks month by month and a range that starts in one and
	 * ends in the next is the case a per-month query gets wrong.
	 */
	$ax_et_make( $ax_et_posts, 'Month crosser', array( 'timezone' => 'Asia/Seoul', 'startsAt' => '2026-10-30 00:00:00', 'endsAt' => '2026-11-03 00:00:00', 'allDay' => true ) );
	$ax_et_october = axismundi_cal_group_by_day( axismundi_cal_calendar_occurrences( $ax_et_calendar, '2026-10-01 00:00:00', '2026-11-01 00:00:00' ), new DateTimeZone( 'Asia/Seoul' ) );
	$ax_et_november = axismundi_cal_group_by_day( axismundi_cal_calendar_occurrences( $ax_et_calendar, '2026-11-01 00:00:00', '2026-12-01 00:00:00' ), new DateTimeZone( 'Asia/Seoul' ) );
	ax_et_assert(
		$ax_et_results,
		'a range crossing a month is on both months when each is asked for on its own',
		$ax_et_on( $ax_et_october, '2026-10-31', 'Month crosser' ) && $ax_et_on( $ax_et_november, '2026-11-01', 'Month crosser' )
	);
	ax_et_assert(
		$ax_et_results,
		'and a whole-day date is never shifted by the zone it is read in, which is what all-day means',
		( static function () use ( $ax_et_calendar, $ax_et_on ) : bool {
			$rows = axismundi_cal_calendar_occurrences( $ax_et_calendar, '2026-09-01 00:00:00', '2026-10-01 00:00:00' );
			foreach ( array( 'Pacific/Kiritimati', 'Pacific/Midway' ) as $zone ) {
				$days = axismundi_cal_group_by_day( $rows, new DateTimeZone( $zone ) );
				/*
				 * The negative is what makes this a test. A timed midnight-to-midnight Event covers the
				 * same three cells in Seoul, so asserting only that the 20th is present would pass on the
				 * very shape all-day exists to be distinguished from -- it is the day *before*, which a
				 * timed reading reaches in Midway and a civil date never does.
				 */
				if ( ! $ax_et_on( $days, '2026-09-20', 'Whole day' ) || $ax_et_on( $days, '2026-09-19', 'Whole day' ) ) {
					return false;
				}
			}
			return true;
		} )()
	);

	// -- 3. A timed range is placed in the reader's zone ------------------------------------------------

	/*
	 * The opposite rule to the one above, and the reason they are separate branches. A timed Event is an
	 * instant, so which day it falls on is the reader's to decide -- 19:00 Seoul on the 10th is still
	 * the 10th in Tokyo and the 9th in London.
	 */
	$ax_et_make( $ax_et_posts, 'Timed run', array( 'timezone' => 'Asia/Seoul', 'startsAt' => '2026-09-10 19:00:00', 'endsAt' => '2026-09-12 21:00:00' ) );
	$ax_et_rows  = axismundi_cal_calendar_occurrences( $ax_et_calendar, '2026-09-01 00:00:00', '2026-10-01 00:00:00' );
	$ax_et_seoul = axismundi_cal_group_by_day( $ax_et_rows, new DateTimeZone( 'Asia/Seoul' ) );
	ax_et_assert(
		$ax_et_results,
		'a timed range is on its first, middle and last day',
		$ax_et_on( $ax_et_seoul, '2026-09-10', 'Timed run' )
			&& $ax_et_on( $ax_et_seoul, '2026-09-11', 'Timed run' )
			&& $ax_et_on( $ax_et_seoul, '2026-09-12', 'Timed run' )
	);
	ax_et_assert(
		$ax_et_results,
		'and moves with the reader, unlike a whole-day one',
		$ax_et_on( axismundi_cal_group_by_day( $ax_et_rows, new DateTimeZone( 'Europe/London' ) ), '2026-09-10', 'Timed run' )
	);

	// -- 4. The document says the same thing -----------------------------------------------------------

	$ax_et_feed  = axismundi_cal_site_feed( $ax_et_calendar, 'Event time fixture', 'Asia/Seoul' );
	$ax_et_lines = ax_et_lines( (string) $ax_et_feed['body'] );

	/*
	 * A whole day is a DATE with no zone anywhere near it. Written as a zoned midnight it is the
	 * previous day for every subscriber west of the site, and the client keeps that reading.
	 */
	ax_et_assert(
		$ax_et_results,
		'a whole-day Event is exported as a DATE, with its exclusive end intact',
		in_array( 'DTSTART;VALUE=DATE:20260920', $ax_et_lines, true ) && in_array( 'DTEND;VALUE=DATE:20260923', $ax_et_lines, true )
	);
	ax_et_assert(
		$ax_et_results,
		'while a timed one carries the zone it happens in rather than being flattened to UTC',
		in_array( 'DTSTART;TZID=Asia/Seoul:20260910T190000', $ax_et_lines, true )
			&& in_array( 'DTEND;TZID=Asia/Seoul:20260912T210000', $ax_et_lines, true )
	);
	/*
	 * The zone has to be defined in the document, not merely named. A `TZID` pointing at nothing is a
	 * time some clients refuse and others guess at.
	 */
	ax_et_assert(
		$ax_et_results,
		'and the named zone is defined in the document, so a client has the offsets to apply',
		in_array( 'BEGIN:VTIMEZONE', $ax_et_lines, true ) && in_array( 'TZID:Asia/Seoul', $ax_et_lines, true )
	);

	$ax_et_weekly = $ax_et_make(
		$ax_et_posts,
		'Weekly standup',
		array( 'timezone' => 'Asia/Seoul', 'startsAt' => '2026-09-07 09:00:00', 'endsAt' => '2026-09-07 09:30:00', 'rrule' => 'FREQ=WEEKLY;BYDAY=MO' )
	);
	$ax_et_recurring_lines = ax_et_lines( (string) axismundi_cal_site_feed( $ax_et_calendar, 'Event time fixture', 'Asia/Seoul' )['body'] );
	ax_et_assert(
		$ax_et_results,
		'a recurring Event exports its rule rather than its expansion, which is what keeps the feed small',
		in_array( 'RRULE:FREQ=WEEKLY;BYDAY=MO', $ax_et_recurring_lines, true )
	);
	/*
	 * The rule is stated in local wall time, so it survives a daylight-saving change: a weekly 09:00 is
	 * 09:00 either side of one, which storing only the instant would not preserve.
	 */
	ax_et_assert(
		$ax_et_results,
		'and its UID is the one that was minted, not something derived from a title or a slug',
		in_array( 'UID:' . axismundi_cal_schedule_for_event( $ax_et_weekly )['ical_uid'], $ax_et_recurring_lines, true )
	);
	ax_et_assert(
		$ax_et_results,
		'so renaming the Event leaves the UID alone and a subscriber gets an edit rather than a second entry',
		( static function () use ( $ax_et_weekly, $ax_et_calendar ) : bool {
			$before = (string) axismundi_cal_schedule_for_event( $ax_et_weekly )['ical_uid'];
			wp_update_post( array( 'ID' => $ax_et_weekly, 'post_title' => 'Renamed standup', 'post_name' => 'renamed-standup' ) );
			return $before === (string) axismundi_cal_schedule_for_event( $ax_et_weekly )['ical_uid'];
		} )()
	);

	// -- 5. Where it happens ---------------------------------------------------------------------------

	/*
	 * Plain text only. A `Place` is the geodata plugin's object and its own contract; an Event that
	 * could not be written until somebody had registered the venue would be a calendar that refuses
	 * ordinary use.
	 */
	$ax_et_located = $ax_et_make(
		$ax_et_posts,
		'Somewhere in particular',
		array(
			'timezone' => 'Asia/Seoul',
			'startsAt' => '2026-09-25 14:00:00',
			'endsAt'   => '2026-09-25 16:00:00',
			// A list rather than a field: an Event can be in more than one place, and the audit for that
			// is its own file. What is checked here is that the time contract still reaches the document
			// alongside one.
			'locations' => array( array( 'kind' => 'physical', 'label' => '서울시 종로구', 'address_text' => '3층' ) ),
		)
	);
	ax_et_assert(
		$ax_et_results,
		// Stored in the list rather than on the schedule: `location_text` there means "this instance
		// differs", and a copy of the list would be the same fact in two places.
		'a location can be written through the envelope the editor uses',
		'서울시 종로구' === (string) axismundi_cal_event_locations( $ax_et_located )[0]['label']
			&& '' === (string) axismundi_cal_schedule_for_event( $ax_et_located )['location_text']
	);
	ax_et_assert(
		$ax_et_results,
		'and reads back, so the editor can show what it just saved',
		'서울시 종로구' === (string) axismundi_cal_rest_envelope( $ax_et_located )['locations'][0]['label']
	);
	/*
	 * Escaped on the way out, because a comma separates values in iCalendar. An address with one in it
	 * is the ordinary case and would otherwise arrive as two properties.
	 */
	ax_et_assert(
		$ax_et_results,
		'reaching the document as one LOCATION with its comma escaped rather than as two values',
		in_array( 'LOCATION:서울시 종로구\\, 3층', ax_et_lines( (string) axismundi_cal_site_feed( $ax_et_calendar, 'Event time fixture', 'Asia/Seoul' )['body'] ), true )
	);

	// -- And an author can reach them ------------------------------------------------------------------

	/*
	 * The REST layer being right is half of it. `all_day` had a column, a writer, an ICS branch and a
	 * grid branch for as long as it existed, and no control anywhere -- so every check above would have
	 * passed while nobody using the software could produce one.
	 *
	 * Asserted against the panel source, which is what the established checks for the workspace script
	 * do. It cannot prove the control renders; it does catch the control being removed, which is the
	 * way this particular gap opens.
	 */
	$ax_et_panel = (string) file_get_contents( dirname( __DIR__ ) . '/assets/editor/event-panel.js' );
	ax_et_assert(
		$ax_et_results,
		'the editor offers a whole-day toggle, without which the column is unreachable again',
		str_contains( $ax_et_panel, "key: 'allDay'" ) && str_contains( $ax_et_panel, 'envelope.allDay' )
	);
	ax_et_assert(
		$ax_et_results,
		'and a location list, which is the other half of the same gap',
		str_contains( $ax_et_panel, "key: 'locations'" ) && str_contains( $ax_et_panel, 'envelope.locations' )
	);
	/*
	 * A whole day has no time of day to enter, so the fields become dates. Keeping `datetime-local`
	 * would ask for a time that is then thrown away, and an author who set one would reasonably expect
	 * it to mean something.
	 */
	ax_et_assert(
		$ax_et_results,
		'whose date fields drop their times when the day is whole, rather than asking for one that is discarded',
		str_contains( $ax_et_panel, "envelope.allDay ? 'date' : 'datetime-local'" )
	);
	/*
	 * `location_place_id` stays out. A `Place` belongs to the geodata plugin, and taking a bare id here
	 * would leave this plugin half-owning a model it cannot validate -- and would block writing an Event
	 * until somebody had registered the venue.
	 */
	/*
	 * The zone is the Event's own choice, so the panel has to offer one. Reading it off the Calendar
	 * alone would make a Seoul calendar unable to hold a New York meeting -- which the writer has always
	 * allowed and nothing could express.
	 */
	ax_et_assert(
		$ax_et_results,
		'the editor offers a time zone of its own, since the calendar only suggests one',
		str_contains( $ax_et_panel, "key: 'timezone'" ) && str_contains( $ax_et_panel, 'config.timezones' )
	);
	ax_et_assert(
		$ax_et_results,
		'and is handed the list the writer will actually accept rather than asking for free text',
		in_array( 'Asia/Seoul', timezone_identifiers_list(), true )
			&& str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/includes/editor.php' ), "'timezones' => timezone_identifiers_list()" )
	);
	/*
	 * `WEEKDAYS` is a preset over a weekly rule, not a frequency -- iCalendar has no such `FREQ`. It is
	 * expanded before anything stores it, so the writer keeps one vocabulary.
	 */
	ax_et_assert(
		$ax_et_results,
		'every weekday is offered as a preset and expanded into the rule it stands for',
		str_contains( $ax_et_panel, "value: 'WEEKDAYS'" )
			&& str_contains( $ax_et_panel, "freq: 'WEEKLY', interval: 1, byday: WEEKDAY_SET.slice()" )
	);
	ax_et_assert(
		$ax_et_results,
		'and the rule it produces is one the writer accepts',
		! is_wp_error( axismundi_cal_event_save(
			$ax_et_make( $ax_et_posts, 'Weekdays', array( 'timezone' => 'Asia/Seoul', 'startsAt' => '2026-09-07 09:00:00', 'endsAt' => '2026-09-07 09:30:00', 'rrule' => 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR' ) ),
			array()
		) )
	);

	ax_et_assert(
		$ax_et_results,
		'while the Place reference stays closed until the plugin that owns Places has a contract for it',
		! str_contains( $ax_et_panel, 'locationPlaceId' )
			&& ! array_key_exists( 'locationPlaceId', axismundi_cal_rest_envelope( $ax_et_located ) )
	);

	// -- The three timezones are three ------------------------------------------------------------------

	/*
	 * Calendar time is the authoring default and nothing more. An Event keeps the zone it was written
	 * with, so a Seoul calendar can hold a New York meeting -- and changing what the Calendar suggests
	 * must not move an Event that already happened somewhere.
	 */
	$ax_et_abroad = $ax_et_make(
		$ax_et_posts,
		'New York meeting',
		array( 'timezone' => 'America/New_York', 'startsAt' => '2027-04-10 10:00:00', 'endsAt' => '2027-04-10 11:00:00' )
	);
	ax_et_assert(
		$ax_et_results,
		'an Event may sit in a different zone from the Calendar holding it',
		'America/New_York' === (string) axismundi_cal_schedule_for_event( $ax_et_abroad )['timezone']
	);
	$ax_et_before = (string) axismundi_cal_schedule_for_event( $ax_et_abroad )['dtstart_local'];
	axismundi_cal_calendar_save( array( 'timezone' => 'Europe/Berlin' ), $ax_et_calendar );
	ax_et_assert(
		$ax_et_results,
		'and changing the Calendar default afterwards does not move it, since that default is only a suggestion',
		'America/New_York' === (string) axismundi_cal_schedule_for_event( $ax_et_abroad )['timezone']
			&& $ax_et_before === (string) axismundi_cal_schedule_for_event( $ax_et_abroad )['dtstart_local']
	);

	// -- The night the clocks change --------------------------------------------------------------------

	/*
	 * The two answers a duration can have, and the model owes a different one to each. A series keeps
	 * its wall-clock length: 19:00-21:00 is two hours on the evening the clocks go back, not three. A
	 * single Event written across the transition keeps the end somebody wrote: 01:00-03:00 ends at
	 * 03:00, which happens to be three real hours later, and nothing may quietly move it to 04:00.
	 *
	 * The second used to fail. Measuring the gap between the two zoned instants gives the real elapsed
	 * time, and adding that back onto a zoned start applies the offset change a second time -- so the
	 * interval is measured between the civil values instead, and the zone places the result once.
	 */
	$ax_et_fallback = axismundi_cal_expand(
		array( 'id' => 0, 'timezone' => 'America/New_York', 'all_day' => 0, 'dtstart_local' => '2026-11-01 01:00:00', 'dtend_local' => '2026-11-01 03:00:00', 'rrule' => '' ),
		'2026-10-30 00:00:00',
		'2026-11-03 00:00:00'
	);
	ax_et_assert(
		$ax_et_results,
		'an Event written across a fall-back ends when it says it ends, not an hour later',
		1 === count( $ax_et_fallback )
			&& '2026-11-01 03:00:00' === (string) $ax_et_fallback[0]['end_local']
			&& '2026-11-01 08:00:00' === (string) $ax_et_fallback[0]['end_utc']
	);
	$ax_et_series = axismundi_cal_expand(
		array( 'id' => 0, 'timezone' => 'America/New_York', 'all_day' => 0, 'dtstart_local' => '2026-10-31 19:00:00', 'dtend_local' => '2026-10-31 21:00:00', 'rrule' => 'FREQ=DAILY;COUNT=3' ),
		'2026-10-30 00:00:00',
		'2026-11-05 00:00:00'
	);
	ax_et_assert(
		$ax_et_results,
		'and a series keeps its wall-clock length across the same night',
		3 === count( $ax_et_series )
			&& array( '2026-10-31 21:00:00', '2026-11-01 21:00:00', '2026-11-02 21:00:00' )
				=== array_map( static fn( array $o ) : string => (string) $o['end_local'], $ax_et_series )
	);

	/*
	 * The other direction, which breaks differently. Going forward the clock skips an hour, so the same
	 * civil range is shorter in real time -- and an occurrence can land on a time of day that does not
	 * exist at all.
	 */
	$ax_et_forward = axismundi_cal_expand(
		array( 'id' => 0, 'timezone' => 'America/New_York', 'all_day' => 0, 'dtstart_local' => '2026-03-08 01:00:00', 'dtend_local' => '2026-03-08 03:00:00', 'rrule' => '' ),
		'2026-03-06 00:00:00',
		'2026-03-10 00:00:00'
	);
	ax_et_assert(
		$ax_et_results,
		'an Event written across a spring-forward also ends when it says, and is simply shorter',
		1 === count( $ax_et_forward )
			&& '2026-03-08 03:00:00' === (string) $ax_et_forward[0]['end_local']
			&& '2026-03-08 07:00:00' === (string) $ax_et_forward[0]['end_utc']
	);
	/*
	 * A series at a time of day the transition deletes. The zone places that morning's occurrence at
	 * 03:30 and every later one goes back to 02:30 -- carrying the placement forward would turn a 02:30
	 * series into a 03:30 series permanently, which is the reverse-direction failure the fall-back
	 * cases could not have caught.
	 */
	$ax_et_ghost = axismundi_cal_expand(
		array( 'id' => 0, 'timezone' => 'America/New_York', 'all_day' => 0, 'dtstart_local' => '2026-03-06 02:30:00', 'dtend_local' => '2026-03-06 03:30:00', 'rrule' => 'FREQ=DAILY;COUNT=4' ),
		'2026-03-05 00:00:00',
		'2026-03-12 00:00:00'
	);
	ax_et_assert(
		$ax_et_results,
		'an occurrence on a clock time that does not exist moves that morning only, and the series returns',
		array( '2026-03-06 02:30:00', '2026-03-07 02:30:00', '2026-03-08 03:30:00', '2026-03-09 02:30:00' )
			=== array_map( static fn( array $o ) : string => (string) $o['start_local'], $ax_et_ghost )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_et_posts as $ax_et_post ) {
		wp_delete_post( (int) $ax_et_post, true );
	}
	foreach ( $ax_et_calendars as $ax_et_cal ) {
		axismundi_cal_calendar_delete( (int) $ax_et_cal );
	}
	foreach ( $ax_et_users as $ax_et_user_id ) {
		wp_delete_user( (int) $ax_et_user_id );
	}
}

$ax_et_failures = count( array_filter( $ax_et_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_et_results ), $ax_et_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_et_failures > 0 ? 1 : 0 );
}
exit( $ax_et_failures > 0 ? 1 : 0 );
