<?php
/**
 * Taking somebody off the list (dev-only; dist-excluded).
 *
 * The act this pins is the one that is easiest to spell wrongly. An organizer removing a guest is not
 * the guest declining, not the guest taking back a request, and not the Event being cancelled -- and
 * every one of those would be a lie told in somebody else's voice, published to their followers.
 *
 * So the checks are mostly about what the removal does *not* say: no refusal anybody did not make, no
 * `Undo` authored by the wrong party, no reply left behind on a list nobody is on.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_rm_results = array();
$ax_rm_users   = array();
$ax_rm_posts   = array();

/** @param bool[] $results Results. */
function ax_rm_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated, published Person Actor. */
function ax_rm_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axrm' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axrm' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

/** One published Event. */
function ax_rm_event( array &$posts, int $author, int $calendar_id, array $extra = array() ) : int {
	$post_id = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_title' => 'Rehearsal', 'post_status' => 'draft', 'post_author' => $author )
	);
	$posts[] = $post_id;
	$saved   = axismundi_cal_event_save(
		$post_id,
		array_merge(
			array(
				'calendar_id' => $calendar_id,
				'starts_at'   => gmdate( 'Y-m-d H:i:s', strtotime( '+30 days' ) ),
				'ends_at'     => gmdate( 'Y-m-d H:i:s', strtotime( '+30 days +2 hours' ) ),
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
	$ax_rm_host_user  = ax_rm_user( $ax_rm_users );
	$ax_rm_guest_user = ax_rm_user( $ax_rm_users );
	wp_set_current_user( $ax_rm_host_user );
	$ax_rm_host      = axismundi_actors_get_for_user( $ax_rm_host_user );
	$ax_rm_guest     = axismundi_actors_get_for_user( $ax_rm_guest_user );
	$ax_rm_cal       = (int) axismundi_cal_primary_calendar( (string) $ax_rm_host->get_uri() )['id'];
	$ax_rm_guest_cal = (int) axismundi_cal_primary_calendar( (string) $ax_rm_guest->get_uri() )['id'];
	// Joining asks whether the Event can be found at all, so the calendar has to be readable by
	// somebody other than its owner before anybody can ask to come to what is on it.
	axismundi_cal_acl_grant( $ax_rm_cal, '', 'reader', 'public' );

	$ax_rm_event = ax_rm_event( $ax_rm_posts, $ax_rm_host_user, $ax_rm_cal, array( 'maximum_attendee_capacity' => 3, 'participant_visibility' => 'public' ) );
	axismundi_cal_event_join( $ax_rm_event, (string) $ax_rm_guest->get_uri() );

	// -- the act ---------------------------------------------------------------------------------------

	ax_rm_assert(
		$ax_rm_results,
		'somebody who is coming can be taken off the list by the organizer',
		true === axismundi_cal_event_remove_attendee( $ax_rm_event, (string) $ax_rm_guest->get_uri() )
			&& 'removed' === (string) axismundi_cal_event_participation( $ax_rm_event, (string) $ax_rm_guest->get_uri() )['state']
			&& 0 === count( axismundi_cal_event_attendees( $ax_rm_event ) )
	);
	// The seat goes back. Otherwise removing somebody to make room would not make room.
	ax_rm_assert(
		$ax_rm_results,
		'and the place they held is free again',
		3 === (int) axismundi_cal_event_remaining_capacity( $ax_rm_event )
	);
	/*
	 * `Remove`, authored by the host. The verb matters more than it looks: an `Undo(Accept)` here would
	 * be the organizer retracting the guest's own words, and the ledger would read as though the guest
	 * had changed their mind about coming.
	 */
	$ax_rm_activity = axismundi_act_get( (string) axismundi_cal_event_participation( $ax_rm_event, (string) $ax_rm_guest->get_uri() )['current_response_activity_uri'] );
	ax_rm_assert(
		$ax_rm_results,
		'the ledger records the organizer removing them, not the guest changing their mind',
		$ax_rm_activity instanceof Axismundi_Activity
			&& 'Remove' === $ax_rm_activity->get_type()
			&& (string) $ax_rm_host->get_uri() === $ax_rm_activity->get_actor_uri()
			&& (string) $ax_rm_guest->get_uri() === (string) $ax_rm_activity->get_object_uri()
	);
	// The row stays. Deleting it would leave the organizer no record of an act they took, and the
	// guest no answer to "why did this disappear".
	ax_rm_assert(
		$ax_rm_results,
		'and the removal is kept rather than the person being erased from the record',
		is_array( axismundi_cal_event_participation( $ax_rm_event, (string) $ax_rm_guest->get_uri() ) )
	);

	// -- what it must not say --------------------------------------------------------------------------

	/*
	 * The whole point. JSCalendar has no status for having been taken off a list, and `declined` is the
	 * one it would be tempting to reach for -- which would publish a refusal the guest never made.
	 */
	$ax_rm_js = axismundi_cal_jscalendar_event( get_post( $ax_rm_event ), null );
	$ax_rm_as = axismundi_cal_event_transform( get_post( $ax_rm_event ) );
	ax_rm_assert(
		$ax_rm_results,
		'the removed guest is absent from the published participants rather than shown as having declined',
		! in_array(
			(string) $ax_rm_guest->get_uri(),
			array_map( static fn( array $p ) : string => (string) $p['calendarAddress'], array_values( (array) $ax_rm_js['participants'] ) ),
			true
		)
			&& ! in_array( (string) $ax_rm_guest->get_uri(), (array) $ax_rm_as['attendees']['items'], true )
	);
	// Even to the organizer, who has every right to see the row and still must not be shown a refusal.
	ax_rm_assert(
		$ax_rm_results,
		'and is absent for the organizer too, since the row is a removal and not an answer',
		! in_array(
			(string) $ax_rm_guest->get_uri(),
			array_map(
				static fn( array $p ) : string => (string) $p['calendarAddress'],
				array_values( (array) axismundi_cal_jscalendar_event( get_post( $ax_rm_event ), (string) $ax_rm_host->get_uri() )['participants'] )
			),
			true
		)
	);

	// -- the guest's calendar --------------------------------------------------------------------------

	ax_rm_assert(
		$ax_rm_results,
		'the event leaves the calendar of the person who was removed',
		! in_array( $ax_rm_event, axismundi_cal_placed_event_ids( $ax_rm_guest_cal ), true )
	);
	/*
	 * "Show declined events" is about your own answers -- it keeps what you turned down in view. A
	 * removal is not one of your answers, and turning that setting on must not put back an Event
	 * somebody took you off.
	 */
	axismundi_cal_set_shows_declined_events( $ax_rm_guest_cal, (string) $ax_rm_guest->get_uri(), true );
	ax_rm_assert(
		$ax_rm_results,
		'and showing declined events does not bring it back, because being removed was not declining',
		! in_array( $ax_rm_event, axismundi_cal_placed_event_ids( $ax_rm_guest_cal ), true )
	);

	// -- who may, and what is a different act ------------------------------------------------------------

	ax_rm_assert(
		$ax_rm_results,
		'somebody removed cannot put themselves back on an event that admits people on arrival',
		is_wp_error( axismundi_cal_event_join( $ax_rm_event, (string) $ax_rm_guest->get_uri() ) )
			&& 'removed' === (string) axismundi_cal_event_participation( $ax_rm_event, (string) $ax_rm_guest->get_uri() )['state']
	);
	// A request nobody has answered is rejected, and an invitation nobody has answered is withdrawn.
	// Removing either would answer a waiting person with a third word that means neither.
	$ax_rm_moderated = ax_rm_event( $ax_rm_posts, $ax_rm_host_user, $ax_rm_cal, array( 'join_mode' => 'restricted' ) );
	$ax_rm_asker     = axismundi_actors_get_for_user( ax_rm_user( $ax_rm_users ) );
	$ax_rm_invited   = axismundi_actors_get_for_user( ax_rm_user( $ax_rm_users ) );
	axismundi_cal_event_join( $ax_rm_moderated, (string) $ax_rm_asker->get_uri() );
	axismundi_cal_event_invite( $ax_rm_moderated, (string) $ax_rm_invited->get_uri() );
	$ax_rm_pending_join   = axismundi_cal_event_remove_attendee( $ax_rm_moderated, (string) $ax_rm_asker->get_uri() );
	$ax_rm_pending_invite = axismundi_cal_event_remove_attendee( $ax_rm_moderated, (string) $ax_rm_invited->get_uri() );
	ax_rm_assert(
		$ax_rm_results,
		'a waiting request and an unanswered invitation are each pointed at their own act instead',
		is_wp_error( $ax_rm_pending_join ) && 'ax_event_remove_pending_join' === $ax_rm_pending_join->get_error_code()
			&& is_wp_error( $ax_rm_pending_invite ) && 'ax_event_remove_pending_invite' === $ax_rm_pending_invite->get_error_code()
	);
	// The host's own attendance is their own `Join`, so undoing it is theirs as well.
	$ax_rm_self = axismundi_cal_event_remove_attendee( $ax_rm_event, (string) $ax_rm_host->get_uri() );
	ax_rm_assert(
		$ax_rm_results,
		'and the host is not removed from their own event',
		is_wp_error( $ax_rm_self ) && 'ax_event_remove_host' === $ax_rm_self->get_error_code()
	);
	// Somebody with no authority over the Event cannot end anybody's attendance.
	$ax_rm_other = ax_rm_user( $ax_rm_users );
	wp_set_current_user( $ax_rm_other );
	$ax_rm_denied = axismundi_cal_event_remove_attendee( $ax_rm_moderated, (string) $ax_rm_asker->get_uri() );
	ax_rm_assert(
		$ax_rm_results,
		'and a stranger cannot remove anybody',
		is_wp_error( $ax_rm_denied ) && 'ax_event_remove_denied' === $ax_rm_denied->get_error_code()
	);

	// -- the way back ------------------------------------------------------------------------------------

	/*
	 * An organizer who removed the wrong person needs a correction, and the person themselves is barred
	 * from asking -- so without this the mistake would be permanent for both of them.
	 */
	wp_set_current_user( $ax_rm_host_user );
	ax_rm_assert(
		$ax_rm_results,
		'the organizer can ask a removed person back, since the removal was their act to reverse',
		'pending' === axismundi_cal_event_invite( $ax_rm_event, (string) $ax_rm_guest->get_uri() )
	);
	/*
	 * And the invitation is answerable by the person who received it. The row began as their own
	 * request; the removal ended that, and what follows genuinely started from the other end -- left as
	 * a `join` it would be an invitation with nobody able to answer it.
	 */
	wp_set_current_user( $ax_rm_guest_user );
	ax_rm_assert(
		$ax_rm_results,
		'and they can answer it, rather than holding an invitation nobody is allowed to accept',
		'accepted' === axismundi_cal_event_respond_to_invite( $ax_rm_event, (string) $ax_rm_guest->get_uri(), 'accept' )
			&& 1 === count( axismundi_cal_event_attendees( $ax_rm_event ) )
			&& in_array( $ax_rm_event, axismundi_cal_placed_event_ids( $ax_rm_guest_cal ), true )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_rm_posts ) as $ax_rm_post_id ) {
		if ( $ax_rm_post_id > 0 ) {
			wp_delete_post( (int) $ax_rm_post_id, true );
		}
	}
	foreach ( array_unique( $ax_rm_users ) as $ax_rm_user_id ) {
		wp_delete_user( (int) $ax_rm_user_id );
	}
}

$ax_rm_failures = count( array_filter( $ax_rm_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_rm_results ), $ax_rm_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_rm_failures > 0 ? 1 : 0 );
}
exit( $ax_rm_failures > 0 ? 1 : 0 );
