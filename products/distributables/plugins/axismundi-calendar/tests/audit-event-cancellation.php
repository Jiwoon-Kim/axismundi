<?php
/**
 * Calling it off (dev-only; dist-excluded).
 *
 * The act that ends the Event rather than anybody's place in it, and the checks are mostly about what
 * survives. A cancellation is not a deletion and not a removal: everybody who said they were coming
 * still said it, the Event is still at the time it was going to be, and the record of both is what an
 * organizer, a guest, an audit and a dispute all read afterwards.
 *
 * The one thing that must move is the evening people kept free. It stays on their calendars, marked
 * off, because an Event that vanished would leave them holding an empty evening for a reason they
 * cannot see.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_cx_results = array();
$ax_cx_users   = array();
$ax_cx_posts   = array();

/** @param bool[] $results Results. */
function ax_cx_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated, published Person Actor. */
function ax_cx_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axcx' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axcx' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

/** One published Event, at a time the caller chooses. */
function ax_cx_event( array &$posts, int $author, int $calendar_id, string $when, array $extra = array() ) : int {
	$post_id = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_title' => 'Concert', 'post_status' => 'draft', 'post_author' => $author )
	);
	$posts[] = $post_id;
	$saved   = axismundi_cal_event_save(
		$post_id,
		array_merge(
			array(
				'calendar_id' => $calendar_id,
				'starts_at'   => gmdate( 'Y-m-d H:i:s', strtotime( $when ) ),
				'ends_at'     => gmdate( 'Y-m-d H:i:s', strtotime( $when . ' +2 hours' ) ),
				'timezone'    => 'Asia/Seoul',
				'join_mode'   => 'free',
			),
			$extra
		)
	);
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
	$ax_cx_host_user  = ax_cx_user( $ax_cx_users );
	$ax_cx_guest_user = ax_cx_user( $ax_cx_users );
	wp_set_current_user( $ax_cx_host_user );
	$ax_cx_host      = axismundi_actors_get_for_user( $ax_cx_host_user );
	$ax_cx_guest     = axismundi_actors_get_for_user( $ax_cx_guest_user );
	$ax_cx_cal       = (int) axismundi_cal_primary_calendar( (string) $ax_cx_host->get_uri() )['id'];
	$ax_cx_guest_cal = (int) axismundi_cal_primary_calendar( (string) $ax_cx_guest->get_uri() )['id'];
	axismundi_cal_acl_grant( $ax_cx_cal, '', 'reader', 'public' );

	$ax_cx_event = ax_cx_event( $ax_cx_posts, $ax_cx_host_user, $ax_cx_cal, '+40 days', array( 'maximum_attendee_capacity' => 50, 'participant_visibility' => 'public' ) );
	axismundi_cal_event_join( $ax_cx_event, (string) $ax_cx_guest->get_uri() );
	$ax_cx_sequence_before = (int) axismundi_cal_schedule_for_event( $ax_cx_event )['sequence'];
	ax_cx_assert(
		$ax_cx_results,
		'an event can be called off by whoever runs it',
		true === axismundi_cal_event_cancel( $ax_cx_event )
			&& axismundi_cal_event_is_cancelled( $ax_cx_event )
	);

	// -- nothing is erased -------------------------------------------------------------------------------

	/*
	 * The distinction the whole slice rests on. Cancelled is not "this never happened", it is "this was
	 * scheduled and then called off", and the second is a fact somebody may need years later.
	 */
	ax_cx_assert(
		$ax_cx_results,
		'the reply somebody gave survives it, because they did say they were coming',
		'accepted' === (string) axismundi_cal_event_participation( $ax_cx_event, (string) $ax_cx_guest->get_uri() )['state']
	);
	// Not `removed`, not `rejected`. Rewriting a reply would put the cancellation in the guest's mouth.
	ax_cx_assert(
		$ax_cx_results,
		'and is left as they gave it rather than rewritten into a refusal or a removal',
		1 === count( axismundi_cal_event_attendees( $ax_cx_event ) )
	);
	ax_cx_assert(
		$ax_cx_results,
		'the event itself stays, at the time it was going to be, saying it is off',
		'EventCancelled' === (string) axismundi_cal_event_get( $ax_cx_event )['event_status']
			&& $ax_cx_event === (int) get_post( $ax_cx_event )->ID
	);

	// -- the evening people kept free ---------------------------------------------------------------------

	/*
	 * The half that must not behave like a removal. Somebody who blocked out the evening needs to be
	 * told it is off; an Event that disappeared would leave them an empty evening and no reason.
	 */
	ax_cx_assert(
		$ax_cx_results,
		'it stays on the calendar of somebody who was coming, so they learn rather than lose an evening',
		in_array( $ax_cx_event, axismundi_cal_placed_event_ids( $ax_cx_guest_cal ), true )
	);
	// And it stays there whatever they have said about declined events, because they declined nothing.
	axismundi_cal_set_shows_declined_events( $ax_cx_guest_cal, (string) $ax_cx_guest->get_uri(), false );
	ax_cx_assert(
		$ax_cx_results,
		'and hiding declined events does not hide it, since being called off was not their answer',
		in_array( $ax_cx_event, axismundi_cal_placed_event_ids( $ax_cx_guest_cal ), true )
	);

	// -- the agenda window, which is about time and not about status ---------------------------------------

	/*
	 * A cancelled Event that has not happened yet is exactly what a calendar has to show. One whose
	 * evening has passed drops out of the default agenda the way anything past does -- because the
	 * window means current and upcoming, not because it was cancelled. Browsing that date still finds it.
	 */
	$ax_cx_now      = current_time( 'mysql', true );
	$ax_cx_schedule = axismundi_cal_schedule_for_event( $ax_cx_event );
	ax_cx_assert(
		$ax_cx_results,
		'a called-off event still to come is in the agenda, which is where somebody would look for it',
		axismundi_cal_schedule_within_agenda( $ax_cx_schedule, $ax_cx_now )
	);
	$ax_cx_past = ax_cx_event( $ax_cx_posts, $ax_cx_host_user, $ax_cx_cal, '-40 days' );
	axismundi_cal_event_cancel( $ax_cx_past );
	ax_cx_assert(
		$ax_cx_results,
		'and one whose evening has passed leaves the agenda the way anything past does',
		! axismundi_cal_schedule_within_agenda( axismundi_cal_schedule_for_event( $ax_cx_past ), $ax_cx_now )
			&& is_array( axismundi_cal_event_get( $ax_cx_past ) )
	);
	// No sweep, no expiry. The record is the point of keeping it.
	$ax_cx_back_then = array_filter(
		axismundi_cal_occurrences_in_range(
			gmdate( 'Y-m-d H:i:s', strtotime( '-41 days' ) ),
			gmdate( 'Y-m-d H:i:s', strtotime( '-39 days' ) ),
			50,
			$ax_cx_cal
		),
		static fn( array $occurrence ) : bool => $ax_cx_past === (int) $occurrence['post_id']
	);
	ax_cx_assert(
		$ax_cx_results,
		'though it is still there to be found on the date it was on',
		1 === count( $ax_cx_back_then )
	);

	// -- what a called-off event no longer takes -------------------------------------------------------------

	$ax_cx_late = axismundi_actors_get_for_user( ax_cx_user( $ax_cx_users ) );
	$ax_cx_join = axismundi_cal_event_join( $ax_cx_event, (string) $ax_cx_late->get_uri() );
	$ax_cx_ask  = axismundi_cal_event_invite( $ax_cx_event, (string) $ax_cx_late->get_uri() );
	ax_cx_assert(
		$ax_cx_results,
		'nobody joins it and nobody is asked to it, whatever its participation mode still says',
		is_wp_error( $ax_cx_join ) && 'ax_event_cancelled' === $ax_cx_join->get_error_code()
			&& is_wp_error( $ax_cx_ask ) && 'ax_event_cancelled' === $ax_cx_ask->get_error_code()
	);
	// The list is frozen rather than editable: it is what people said while it was on.
	$ax_cx_remove = axismundi_cal_event_remove_attendee( $ax_cx_event, (string) $ax_cx_guest->get_uri() );
	ax_cx_assert(
		$ax_cx_results,
		'and nobody is taken off a list that is now a record of what was said',
		is_wp_error( $ax_cx_remove ) && 'ax_event_cancelled' === $ax_cx_remove->get_error_code()
	);

	// -- the published documents -------------------------------------------------------------------------------

	$ax_cx_js = axismundi_cal_jscalendar_event( get_post( $ax_cx_event ), null );
	$ax_cx_as = axismundi_cal_event_transform( get_post( $ax_cx_event ) );
	ax_cx_assert(
		$ax_cx_results,
		'both documents say it is off rather than dropping it',
		'cancelled' === (string) $ax_cx_js['status'] && 'EventCancelled' === (string) $ax_cx_as['eventStatus']
	);
	/*
	 * Lifecycle before policy. This Event's guest list is public, and it still publishes none: who may
	 * see who is coming is a question about an Event that is going ahead.
	 */
	ax_cx_assert(
		$ax_cx_results,
		'and neither publishes a guest list, although the policy on this event says it is public',
		array( 'organizer' ) === array_keys( (array) $ax_cx_js['participants'] )
			&& ! isset( $ax_cx_as['attendees'] )
	);
	// The organizer stays: a cancellation is announced by somebody, and iTIP's CANCEL has to say who.
	ax_cx_assert(
		$ax_cx_results,
		'the organizer is still named, since somebody called it off',
		(string) $ax_cx_host->get_uri() === (string) $ax_cx_js['participants']['organizer']['calendarAddress']
	);
	ax_cx_assert(
		$ax_cx_results,
		'and no places are offered at something that is not happening',
		! isset( $ax_cx_as['remainingAttendeeCapacity'] ) && 'none' === (string) $ax_cx_as['joinMode']
	);
	// A subscriber holding the old entry is told, rather than left with something that looks fine.
	ax_cx_assert(
		$ax_cx_results,
		'and a subscribed calendar carries the cancellation instead of quietly dropping the entry',
		in_array( 'STATUS:CANCELLED', axismundi_cal_ics_vevent( $ax_cx_schedule, get_post( $ax_cx_event ) ), true )
	);

	// -- said once, in the organizer's name ---------------------------------------------------------------------

	/*
	 * `Update`, not `Delete`. A `Delete` would tell every peer to tombstone the Event, and the guest
	 * whose calendar it disappeared from would have no way to learn why.
	 */
	$ax_cx_acts = array_filter(
		axismundi_act_get_by_object( axismundi_cal_event_uri( $ax_cx_event ) ),
		static fn( Axismundi_Activity $activity ) : bool => 'Update' === $activity->get_type()
	);
	ax_cx_assert(
		$ax_cx_results,
		'the ledger says the host updated the event rather than deleting it',
		1 === count( $ax_cx_acts )
			&& (string) $ax_cx_host->get_uri() === array_values( $ax_cx_acts )[0]->get_actor_uri()
	);
	// Saving the Event again does not announce a second cancellation of the same Event.
	axismundi_cal_event_save( $ax_cx_event, array( 'event_status' => 'EventCancelled' ) );
	ax_cx_assert(
		$ax_cx_results,
		'and saying so twice is still one cancellation',
		1 === count(
			array_filter(
				axismundi_act_get_by_object( axismundi_cal_event_uri( $ax_cx_event ) ),
				static fn( Axismundi_Activity $activity ) : bool => 'Update' === $activity->get_type()
			)
		)
	);
	$ax_cx_again = axismundi_cal_event_cancel( $ax_cx_event );
	ax_cx_assert(
		$ax_cx_results,
		'and calling off something already called off is refused rather than repeated',
		is_wp_error( $ax_cx_again ) && 'ax_event_cancel_already' === $ax_cx_again->get_error_code()
	);
	/*
	 * A subscriber is entitled to treat an entry carrying no higher sequence as a stale copy of what
	 * they already hold. iTIP says so of `CANCEL` in as many words -- so a cancellation that did not
	 * advance it would be a message clients may ignore, which is the worst possible failure for this
	 * particular message.
	 */
	ax_cx_assert(
		$ax_cx_results,
		'and the cancellation is a newer version of the event, not a copy a client may ignore',
		(int) axismundi_cal_schedule_for_event( $ax_cx_event )['sequence'] > $ax_cx_sequence_before
	);

	// -- putting it back on ---------------------------------------------------------------------------------------

	$ax_cx_reinstated = axismundi_cal_event_reinstate( $ax_cx_event );
	ax_cx_assert(
		$ax_cx_results,
		'an event that was called off can be put back on',
		true === $ax_cx_reinstated && ! axismundi_cal_event_is_cancelled( $ax_cx_event )
	);
	/*
	 * The answers were never touched, so they are still there. Resetting everybody to `pending` would
	 * erase what people said in the name of asking them again -- and whether reinstating should re-ask
	 * is a question about notifications, not a reason to destroy answers.
	 */
	ax_cx_assert(
		$ax_cx_results,
		'and the people who had said they were coming still have',
		'accepted' === (string) axismundi_cal_event_participation( $ax_cx_event, (string) $ax_cx_guest->get_uri() )['state']
			&& 1 === count( axismundi_cal_event_attendees( $ax_cx_event ) )
	);
	ax_cx_assert(
		$ax_cx_results,
		'the guest list is published again, and the event takes replies again',
		1 === count( (array) axismundi_cal_event_transform( get_post( $ax_cx_event ) )['attendees']['items'] )
			&& 'pending' === axismundi_cal_event_invite( $ax_cx_event, (string) $ax_cx_late->get_uri() )
	);
	/*
	 * `Update` again rather than `Undo` of the cancellation. An `Undo` retracts a relationship its
	 * author established and says it never stood; the cancellation did stand, and people made other
	 * plans on the strength of it. What happened is that the Event changed a second time.
	 */
	$ax_cx_after = axismundi_act_get_by_object( axismundi_cal_event_uri( $ax_cx_event ) );
	ax_cx_assert(
		$ax_cx_results,
		'said as another change to the event rather than as undoing the cancellation',
		2 === count( array_filter( $ax_cx_after, static fn( Axismundi_Activity $a ) : bool => 'Update' === $a->get_type() ) )
			&& 0 === count( array_filter( $ax_cx_after, static fn( Axismundi_Activity $a ) : bool => 'Undo' === $a->get_type() ) )
	);
	// And a third act when it is called off again -- not the first cancellation handed back, which
	// would announce a version of the Event two changes have since replaced.
	axismundi_cal_event_cancel( $ax_cx_event );
	ax_cx_assert(
		$ax_cx_results,
		'and calling it off again is a third act, since two changes have happened since the first',
		3 === count(
			array_filter(
				axismundi_act_get_by_object( axismundi_cal_event_uri( $ax_cx_event ) ),
				static fn( Axismundi_Activity $a ) : bool => 'Update' === $a->get_type()
			)
		)
	);
	ax_cx_assert(
		$ax_cx_results,
		'but not back to being called off, which would be no change at all',
		'ax_event_reinstate_status' === axismundi_cal_event_reinstate( $ax_cx_event, 'EventCancelled' )->get_error_code()
	);
	// It comes back as what its organizer says it is, since nothing recorded what it used to be.
	ax_cx_assert(
		$ax_cx_results,
		'and it can come back as tentative rather than only as scheduled',
		true === axismundi_cal_event_reinstate( $ax_cx_event, 'EventTentative' )
			&& 'EventTentative' === (string) axismundi_cal_event_get( $ax_cx_event )['event_status']
	);
	$ax_cx_going = ax_cx_event( $ax_cx_posts, $ax_cx_host_user, $ax_cx_cal, '+60 days' );
	ax_cx_assert(
		$ax_cx_results,
		'while an event that is going ahead has nothing to put back on',
		'ax_event_reinstate_not_cancelled' === axismundi_cal_event_reinstate( $ax_cx_going )->get_error_code()
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_cx_posts ) as $ax_cx_post_id ) {
		if ( $ax_cx_post_id > 0 ) {
			wp_delete_post( (int) $ax_cx_post_id, true );
		}
	}
	foreach ( array_unique( $ax_cx_users ) as $ax_cx_user_id ) {
		wp_delete_user( (int) $ax_cx_user_id );
	}
}

$ax_cx_failures = count( array_filter( $ax_cx_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_cx_results ), $ax_cx_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_cx_failures > 0 ? 1 : 0 );
}
exit( $ax_cx_failures > 0 ? 1 : 0 );
