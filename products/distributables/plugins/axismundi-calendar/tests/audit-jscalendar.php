<?php
/**
 * One Event said in JSCalendar (dev-only; dist-excluded).
 *
 * This is a read projection, so what is under test is whether the domain model can say what
 * JSCalendar names without losing anything -- which is also the question that decides, later, whether
 * the storage should move toward it. The interesting cases are the ones where iCalendar's habits and
 * JSCalendar's model disagree: a length instead of an end, an all-day date with no zone at all, and a
 * recurrence as a structure rather than a string.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_js_results = array();
$ax_js_users   = array();
$ax_js_posts   = array();

/** @param bool[] $results Results. */
function ax_js_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated Person Actor. */
function ax_js_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axjs' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	if ( $actor instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $actor->get_identity_id(), 'axjs' . strtolower( wp_generate_password( 8, false, false ) ) );
	}
	return $id;
}

/** One published Event. */
function ax_js_event( array &$posts, int $author, int $calendar_id, string $title, array $fields ) : int {
	$post_id = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_title' => $title, 'post_status' => 'draft', 'post_author' => $author )
	);
	$posts[] = $post_id;
	$saved   = axismundi_cal_event_save( $post_id, array_merge( array( 'calendar_id' => $calendar_id ), $fields ) );
	if ( is_wp_error( $saved ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "  (fixture refused: %s)\n", $saved->get_error_message() );
		return 0;
	}
	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;
	return $post_id;
}

try {
	$ax_js_user_id = ax_js_user( $ax_js_users );
	wp_set_current_user( $ax_js_user_id );
	$ax_js_actor = axismundi_actors_get_for_user( $ax_js_user_id );
	$ax_js_cal   = axismundi_cal_primary_calendar( (string) $ax_js_actor->get_uri() );
	$ax_js_id    = (int) $ax_js_cal['id'];
	axismundi_cal_acl_grant( $ax_js_id, '', 'reader', 'public' );

	// -- a length, not an end -------------------------------------------------------------------------

	$ax_js_timed = ax_js_event(
		$ax_js_posts,
		$ax_js_user_id,
		$ax_js_id,
		'Rehearsal',
		array( 'starts_at' => '2027-01-15 19:00:00', 'ends_at' => '2027-01-15 21:30:00', 'timezone' => 'Asia/Seoul' )
	);
	$ax_js_doc = axismundi_cal_jscalendar_event( get_post( $ax_js_timed ) );
	/*
	 * The model already meant start-plus-length; this says so out loud. A stored end would have to be
	 * reinterpreted by every reader, and the night the clocks change is where they would disagree.
	 */
	ax_js_assert(
		$ax_js_results,
		'a timed Event is a civil start, a zone and a length rather than two instants',
		is_array( $ax_js_doc )
			&& '2027-01-15T19:00:00' === (string) $ax_js_doc['start']
			&& 'PT2H30M' === (string) $ax_js_doc['duration']
			&& 'Asia/Seoul' === (string) $ax_js_doc['timeZone']
			&& ! isset( $ax_js_doc['showWithoutTime'] )
	);

	// -- an all-day date has no zone at all -------------------------------------------------------------

	/*
	 * The distinction the model keeps and JSCalendar states differently. All-day is not "midnight in
	 * some zone": it is the same civil date for every reader, so publishing a zone alongside it would
	 * invite a consumer to convert it and land on the day before.
	 */
	$ax_js_allday = ax_js_event(
		$ax_js_posts,
		$ax_js_user_id,
		$ax_js_id,
		'Holiday',
		array( 'starts_at' => '2027-01-20 00:00:00', 'ends_at' => '2027-01-21 00:00:00', 'timezone' => 'Asia/Seoul', 'all_day' => 1 )
	);
	$ax_js_all = 0 !== $ax_js_allday ? axismundi_cal_jscalendar_event( get_post( $ax_js_allday ) ) : null;
	ax_js_assert(
		$ax_js_results,
		'an all-day Event says so and carries no zone to be converted by',
		is_array( $ax_js_all )
			&& true === $ax_js_all['showWithoutTime']
			&& ! isset( $ax_js_all['timeZone'] )
			&& 'P1D' === (string) $ax_js_all['duration']
	);

	// -- a recurrence as a structure --------------------------------------------------------------------

	$ax_js_weekly = ax_js_event(
		$ax_js_posts,
		$ax_js_user_id,
		$ax_js_id,
		'Weekly standup',
		array( 'starts_at' => '2027-02-01 09:00:00', 'ends_at' => '2027-02-01 09:30:00', 'timezone' => 'Asia/Seoul', 'rrule' => 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,WE;COUNT=6' )
	);
	$ax_js_rec = axismundi_cal_jscalendar_event( get_post( $ax_js_weekly ) );
	ax_js_assert(
		$ax_js_results,
		'a recurrence is published as a structure rather than as the iCalendar string it is stored in',
		is_array( $ax_js_rec )
			&& isset( $ax_js_rec['recurrenceRules'][0] )
			&& 'weekly' === (string) $ax_js_rec['recurrenceRules'][0]['frequency']
			&& 2 === (int) $ax_js_rec['recurrenceRules'][0]['interval']
			&& 6 === (int) $ax_js_rec['recurrenceRules'][0]['count']
			&& array( array( '@type' => 'NDay', 'day' => 'mo' ), array( '@type' => 'NDay', 'day' => 'we' ) ) === $ax_js_rec['recurrenceRules'][0]['byDay']
	);
	// A rule this site cannot expand is one it must not publish as though it could.
	ax_js_assert(
		$ax_js_results,
		'and a rule nothing here understands is left out rather than passed through',
		null === axismundi_cal_jscalendar_recurrence_rule( 'FREQ=SECONDLY;BYSETPOS=2' )
	);

	// -- whose event it is ------------------------------------------------------------------------------

	/*
	 * The organizer is the Actor it was published as, which the previous slices made a stored fact.
	 * There is no `author`: JSCalendar has no such property, and a non-standard one would be a second
	 * answer to a question `organizer` already answers.
	 */
	ax_js_assert(
		$ax_js_results,
		'the organizer is the Actor the Event was published as, and no non-standard author is invented',
		(string) $ax_js_actor->get_uri() === (string) $ax_js_rec['participants']['organizer']['calendarAddress']
			&& true === $ax_js_rec['participants']['organizer']['roles']['owner']
			&& ! isset( $ax_js_rec['author'] )
			&& ! isset( $ax_js_rec['attributedTo'] )
	);

	// -- the same gate as every other public surface -----------------------------------------------------

	$ax_js_private = ax_js_event(
		$ax_js_posts,
		$ax_js_user_id,
		$ax_js_id,
		'Private matter',
		array( 'starts_at' => '2027-03-01 10:00:00', 'ends_at' => '2027-03-01 11:00:00', 'timezone' => 'Asia/Seoul', 'visibility' => 'private' )
	);
	ax_js_assert(
		$ax_js_results,
		'a private Event is withheld here by the same predicate the feed and the collection ask',
		! axismundi_cal_event_listable( get_post( $ax_js_private ) )
			&& axismundi_cal_event_listable( get_post( $ax_js_timed ) )
	);

	// -- what an exception looks like ---------------------------------------------------------------------

	/*
	 * A cancelled instance is a date the series no longer happens on, which JSCalendar states as an
	 * exclusion rather than as an instance with a cancelled status.
	 */
	$ax_js_schedule = axismundi_cal_schedule_for_event( $ax_js_weekly );
	$ax_js_now      = current_time( 'mysql', true );
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the rule may already have materialized this instance.
	$wpdb->delete( axismundi_cal_occurrences_table(), array( 'schedule_id' => (int) $ax_js_schedule['id'], 'recurrence_id' => '20270201T090000' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture standing in for the editor.
	$wpdb->insert(
		axismundi_cal_occurrences_table(),
		array(
			'schedule_id' => (int) $ax_js_schedule['id'], 'recurrence_id' => '20270201T090000',
			'start_utc' => '2027-02-01 00:00:00', 'end_utc' => '2027-02-01 00:30:00',
			'start_local' => '2027-02-01 09:00:00', 'end_local' => '2027-02-01 09:30:00',
			'status' => 'cancelled', 'origin' => 'override', 'location_place_id' => null,
			'location_text' => '', 'override_json' => '', 'created_at' => $ax_js_now, 'updated_at' => $ax_js_now,
		)
	);
	$ax_js_over = axismundi_cal_jscalendar_event( get_post( $ax_js_weekly ) );
	ax_js_assert(
		$ax_js_results,
		'a cancelled instance is published as a date the series is excluded from',
		isset( $ax_js_over['recurrenceOverrides']['2027-02-01T09:00:00'] )
			&& true === ( $ax_js_over['recurrenceOverrides']['2027-02-01T09:00:00']['excluded'] ?? false )
	);
	// -- a date the rule never produces ------------------------------------------------------------------

	/*
	 * An added date needs no rule to exist. JSCalendar states one as an override at a key no rule
	 * produced, and this used to be gated on there being a recurrence rule at all -- so an Event whose
	 * only extra date was hand-added published none of it while every local surface went on showing it.
	 */
	$ax_js_single = ax_js_event(
		$ax_js_posts,
		$ax_js_user_id,
		$ax_js_id,
		'One-off with an added date',
		array( 'starts_at' => '2027-07-07 09:00:00', 'ends_at' => '2027-07-07 10:00:00', 'timezone' => 'Asia/Seoul' )
	);
	$ax_js_single_schedule = axismundi_cal_schedule_for_event( $ax_js_single );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture standing in for the editor.
	$wpdb->insert(
		axismundi_cal_occurrences_table(),
		array(
			'schedule_id' => (int) $ax_js_single_schedule['id'], 'recurrence_id' => '20270710T090000',
			'start_utc' => '2027-07-10 00:00:00', 'end_utc' => '2027-07-10 01:00:00',
			'start_local' => '2027-07-10 09:00:00', 'end_local' => '2027-07-10 10:00:00',
			'status' => 'scheduled', 'origin' => 'rdate', 'location_place_id' => null,
			'location_text' => '', 'override_json' => '', 'created_at' => $ax_js_now, 'updated_at' => $ax_js_now,
		)
	);
	$ax_js_added = axismundi_cal_jscalendar_event( get_post( $ax_js_single ) );
	ax_js_assert(
		$ax_js_results,
		'a hand-added date is published even when the Event has no recurrence rule at all',
		! isset( $ax_js_added['recurrenceRules'] )
			&& isset( $ax_js_added['recurrenceOverrides']['2027-07-10T09:00:00'] )
	);

} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_js_posts ) as $ax_js_post_id ) {
		if ( $ax_js_post_id > 0 ) {
			wp_delete_post( (int) $ax_js_post_id, true );
		}
	}
	foreach ( array_unique( $ax_js_users ) as $ax_js_user_row ) {
		wp_delete_user( (int) $ax_js_user_row );
	}
}

$ax_js_failures = count( array_filter( $ax_js_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_js_results ), $ax_js_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_js_failures > 0 ? 1 : 0 );
}
exit( $ax_js_failures > 0 ? 1 : 0 );
