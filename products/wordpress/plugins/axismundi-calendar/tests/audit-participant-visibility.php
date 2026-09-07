<?php
/**
 * Who may see who is coming (dev-only; dist-excluded).
 *
 * An Event being public says a stranger may read the Event; it does not say they may read the guest
 * list. Conflating the two turns RSVP into a way of harvesting Actor URIs, so this is a second axis
 * with its own default -- and the default has to be the closed one, because the alternative is a new
 * public surface quietly publishing replies nobody chose to publish.
 *
 * The viewer is an argument at every level. An evaluator that reached for the session would put a
 * signed-in reader's guest list into a response that gets cached and served to everybody.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_pv_results = array();
$ax_pv_users   = array();
$ax_pv_posts   = array();

/** @param bool[] $results Results. */
function ax_pv_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated, published Person Actor. */
function ax_pv_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axpv' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axpv' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

/** Actor URIs visible to one viewer, in a stable order. */
function ax_pv_uris( int $post_id, ?string $viewer ) : array {
	$uris = array_map(
		static fn( array $row ) : string => (string) $row['actor_uri'],
		axismundi_cal_visible_participants( $post_id, $viewer )
	);
	sort( $uris );
	return $uris;
}

/** The same ordering, so an expectation is compared on membership rather than on row order. */
function ax_pv_expect( string ...$uris ) : array {
	sort( $uris );
	return $uris;
}

try {
	$ax_pv_host_user = ax_pv_user( $ax_pv_users );
	wp_set_current_user( $ax_pv_host_user );
	$ax_pv_host  = axismundi_actors_get_for_user( $ax_pv_host_user );
	$ax_pv_cal   = (int) axismundi_cal_primary_calendar( (string) $ax_pv_host->get_uri() )['id'];

	$ax_pv_post = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_title' => 'Gathering', 'post_status' => 'draft', 'post_author' => $ax_pv_host_user )
	);
	$ax_pv_posts[] = $ax_pv_post;
	axismundi_cal_event_save(
		$ax_pv_post,
		array(
			'calendar_id' => $ax_pv_cal,
			'starts_at'   => gmdate( 'Y-m-d H:i:s', strtotime( '+20 days' ) ),
			'ends_at'     => gmdate( 'Y-m-d H:i:s', strtotime( '+20 days +2 hours' ) ),
			'timezone'    => 'Asia/Seoul',
			'join_mode'   => 'restricted',
		)
	);
	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $ax_pv_post, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;

	// Four people in four states, which is what makes the levels distinguishable at all.
	$ax_pv_going    = axismundi_actors_get_for_user( ax_pv_user( $ax_pv_users ) );
	$ax_pv_maybe    = axismundi_actors_get_for_user( ax_pv_user( $ax_pv_users ) );
	$ax_pv_declined = axismundi_actors_get_for_user( ax_pv_user( $ax_pv_users ) );
	$ax_pv_waiting  = axismundi_actors_get_for_user( ax_pv_user( $ax_pv_users ) );
	$ax_pv_stranger = axismundi_actors_get_for_user( ax_pv_user( $ax_pv_users ) );
	foreach ( array( $ax_pv_going, $ax_pv_maybe, $ax_pv_declined, $ax_pv_waiting ) as $ax_pv_guest ) {
		axismundi_cal_event_invite( $ax_pv_post, (string) $ax_pv_guest->get_uri() );
	}
	axismundi_cal_event_respond_to_invite( $ax_pv_post, (string) $ax_pv_going->get_uri(), 'accept' );
	axismundi_cal_event_respond_to_invite( $ax_pv_post, (string) $ax_pv_maybe->get_uri(), 'tentative' );
	axismundi_cal_event_respond_to_invite( $ax_pv_post, (string) $ax_pv_declined->get_uri(), 'reject' );

	// -- the default is the closed answer ----------------------------------------------------------------

	/*
	 * Asked first, because a wrong default is the failure that ships silently: every Event written
	 * before this axis existed would start publishing its replies the day a public surface reads them.
	 */
	ax_pv_assert(
		$ax_pv_results,
		'an Event nobody configured keeps its guest list to the organizer',
		'organizers' === axismundi_cal_participant_visibility( $ax_pv_post )
	);
	ax_pv_assert(
		$ax_pv_results,
		'the organizer sees everyone, and an anonymous reader sees nobody',
		4 === count( axismundi_cal_visible_participants( $ax_pv_post, (string) $ax_pv_host->get_uri() ) )
			&& array() === axismundi_cal_visible_participants( $ax_pv_post, null )
	);
	// Each participant sees their own row: what they said is theirs to read back.
	ax_pv_assert(
		$ax_pv_results,
		'and each guest sees their own reply and nobody else\'s',
		array( (string) $ax_pv_going->get_uri() ) === ax_pv_uris( $ax_pv_post, (string) $ax_pv_going->get_uri() )
			&& array( (string) $ax_pv_declined->get_uri() ) === ax_pv_uris( $ax_pv_post, (string) $ax_pv_declined->get_uri() )
	);

	/*
	 * And an Event that predates the column has to be given that answer explicitly. The default only
	 * covers rows written after the migration; every Event already in the table was written when nobody
	 * was asked the question, and leaving those empty would mean the first public surface to read them
	 * published replies on the strength of an unset field.
	 */
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture: fabricate a pre-migration row.
	$wpdb->update( axismundi_cal_events_table(), array( 'participant_visibility' => '' ), array( 'post_id' => $ax_pv_post ), array( '%s' ), array( '%d' ) );
	ax_pv_assert(
		$ax_pv_results,
		'an Event written before the column existed reads closed even before the backfill runs',
		'organizers' === axismundi_cal_participant_visibility( $ax_pv_post )
			&& array() === ax_pv_uris( $ax_pv_post, null )
	);
	axismundi_cal_backfill_participant_visibility();
	ax_pv_assert(
		$ax_pv_results,
		'and the backfill writes that answer down rather than leaving it to be re-derived',
		'organizers' === (string) axismundi_cal_event_get( $ax_pv_post )['participant_visibility']
	);

	// -- attendees: the people coming can see each other ---------------------------------------------------

	axismundi_cal_event_save( $ax_pv_post, array( 'participant_visibility' => 'attendees' ) );
	ax_pv_assert(
		$ax_pv_results,
		'at attendees, the people coming see each other -- including the maybes, who said they are leaning yes',
		ax_pv_expect( (string) $ax_pv_going->get_uri(), (string) $ax_pv_maybe->get_uri() ) === ax_pv_uris( $ax_pv_post, (string) $ax_pv_maybe->get_uri() )
	);
	/*
	 * The half that must not widen. Somebody who declined has not agreed to have the declining
	 * published, and somebody who has not answered has agreed to nothing at all.
	 */
	ax_pv_assert(
		$ax_pv_results,
		'while a refusal and an unanswered invitation stay between the organizer and the person',
		! in_array( (string) $ax_pv_declined->get_uri(), ax_pv_uris( $ax_pv_post, (string) $ax_pv_going->get_uri() ), true )
			&& ! in_array( (string) $ax_pv_waiting->get_uri(), ax_pv_uris( $ax_pv_post, (string) $ax_pv_going->get_uri() ), true )
	);
	// Somebody who is not coming is not one of the attendees, whatever else they are.
	ax_pv_assert(
		$ax_pv_results,
		'and somebody with no place in it sees nothing at all',
		array() === ax_pv_uris( $ax_pv_post, (string) $ax_pv_stranger->get_uri() )
			&& array() === ax_pv_uris( $ax_pv_post, null )
	);

	// -- public: anonymous readers, and still only the two states -------------------------------------------

	axismundi_cal_event_save( $ax_pv_post, array( 'participant_visibility' => 'public' ) );
	ax_pv_assert(
		$ax_pv_results,
		'at public, anybody sees who is coming',
		ax_pv_expect( (string) $ax_pv_going->get_uri(), (string) $ax_pv_maybe->get_uri() ) === ax_pv_uris( $ax_pv_post, null )
	);
	ax_pv_assert(
		$ax_pv_results,
		'and public still does not publish a refusal or a pending invitation',
		! in_array( (string) $ax_pv_declined->get_uri(), ax_pv_uris( $ax_pv_post, null ), true )
			&& ! in_array( (string) $ax_pv_waiting->get_uri(), ax_pv_uris( $ax_pv_post, null ), true )
	);

	// -- private: the organizer and the person, and nobody else ---------------------------------------------

	axismundi_cal_event_save( $ax_pv_post, array( 'participant_visibility' => 'private' ) );
	ax_pv_assert(
		$ax_pv_results,
		'at private, even the people coming cannot see each other',
		array( (string) $ax_pv_going->get_uri() ) === ax_pv_uris( $ax_pv_post, (string) $ax_pv_going->get_uri() )
			&& 4 === count( axismundi_cal_visible_participants( $ax_pv_post, (string) $ax_pv_host->get_uri() ) )
	);

	// -- the count discloses less, and is gated too ----------------------------------------------------------

	/*
	 * "Eleven people are coming to this private gathering" is itself something the organizer did not
	 * publish, so the count is not a way around the level.
	 */
	ax_pv_assert(
		$ax_pv_results,
		'a stranger is told nothing about how many are coming to a closed Event',
		null === axismundi_cal_visible_participant_count( $ax_pv_post, null )
			&& 1 === axismundi_cal_visible_participant_count( $ax_pv_post, (string) $ax_pv_host->get_uri() )
	);
	axismundi_cal_event_save( $ax_pv_post, array( 'participant_visibility' => 'public' ) );
	ax_pv_assert(
		$ax_pv_results,
		'and is answered once the Event says the guest list is public',
		1 === axismundi_cal_visible_participant_count( $ax_pv_post, null )
	);

	// -- the two projections ask the evaluator rather than deciding again --------------------------------------

	/*
	 * Both documents are fetched by whoever holds the URL and cached by whatever sits in between, so
	 * both are built for a reader with no relation to the Event. The point of the checks is that they
	 * agree: two projections of one Event that each decided for themselves would eventually disagree,
	 * and the disagreement would be one of them publishing something the other withholds.
	 */
	axismundi_cal_event_save( $ax_pv_post, array( 'participant_visibility' => 'organizers' ) );
	$ax_pv_closed_js = axismundi_cal_jscalendar_event( get_post( $ax_pv_post ), null );
	$ax_pv_closed_as = axismundi_cal_event_transform( get_post( $ax_pv_post ) );
	ax_pv_assert(
		$ax_pv_results,
		'a closed guest list publishes the organizer and no guests at all',
		array( 'organizer' ) === array_keys( (array) $ax_pv_closed_js['participants'] )
			&& ! isset( $ax_pv_closed_as['attendees'] )
	);
	// Not an empty collection: `totalItems: 0` is the claim that nobody is coming, which is a different
	// statement from declining to say.
	ax_pv_assert(
		$ax_pv_results,
		'and says so by leaving the collection out rather than by publishing an empty one',
		! array_key_exists( 'attendees', $ax_pv_closed_as )
	);

	axismundi_cal_event_save( $ax_pv_post, array( 'participant_visibility' => 'public' ) );
	$ax_pv_open_js = axismundi_cal_jscalendar_event( get_post( $ax_pv_post ), null );
	$ax_pv_open_as = axismundi_cal_event_transform( get_post( $ax_pv_post ) );
	$ax_pv_open_js_addresses = array_map(
		static fn( array $p ) : string => (string) $p['calendarAddress'],
		array_values( (array) $ax_pv_open_js['participants'] )
	);
	ax_pv_assert(
		$ax_pv_results,
		'a published guest list names the people coming, and states what each of them said',
		in_array( (string) $ax_pv_going->get_uri(), $ax_pv_open_js_addresses, true )
			&& in_array( (string) $ax_pv_maybe->get_uri(), $ax_pv_open_js_addresses, true )
			&& 'tentative' === (string) array_values(
				array_filter(
					(array) $ax_pv_open_js['participants'],
					static fn( array $p ) : bool => (string) $ax_pv_maybe->get_uri() === (string) $p['calendarAddress']
				)
			)[0]['participationStatus']
	);
	ax_pv_assert(
		$ax_pv_results,
		'and neither document publishes a refusal or an unanswered invitation',
		! in_array( (string) $ax_pv_declined->get_uri(), $ax_pv_open_js_addresses, true )
			&& ! in_array( (string) $ax_pv_waiting->get_uri(), $ax_pv_open_js_addresses, true )
			&& ! in_array( (string) $ax_pv_declined->get_uri(), (array) $ax_pv_open_as['attendees']['items'], true )
	);
	/*
	 * `attendees` is the accepted replies. Somebody leaning yes is visible at the levels that show
	 * them and is still not one of the attendees, so the two documents differ here on purpose.
	 */
	ax_pv_assert(
		$ax_pv_results,
		'the collection is who is coming, so a maybe appears in the Event and not among the attendees',
		array( (string) $ax_pv_going->get_uri() ) === (array) $ax_pv_open_as['attendees']['items']
			&& 1 === (int) $ax_pv_open_as['attendees']['totalItems']
	);

	// -- capacity is not a way around it -----------------------------------------------------------------------

	/*
	 * A published maximum minus a published remainder is the accepted count exactly, and a peer
	 * watching the remainder drop would learn each acceptance as it happened.
	 */
	axismundi_cal_event_save( $ax_pv_post, array( 'maximum_attendee_capacity' => 10 ) );
	$ax_pv_open_capacity = axismundi_cal_event_transform( get_post( $ax_pv_post ) );
	axismundi_cal_event_save( $ax_pv_post, array( 'participant_visibility' => 'organizers' ) );
	$ax_pv_closed_capacity = axismundi_cal_event_transform( get_post( $ax_pv_post ) );
	ax_pv_assert(
		$ax_pv_results,
		'how many the Event holds is published either way, since its organizer stated it',
		10 === (int) $ax_pv_open_capacity['maximumAttendeeCapacity'] && 10 === (int) $ax_pv_closed_capacity['maximumAttendeeCapacity']
	);
	ax_pv_assert(
		$ax_pv_results,
		'but how many seats are left travels only where the count itself may',
		9 === (int) $ax_pv_open_capacity['remainingAttendeeCapacity']
			&& ! isset( $ax_pv_closed_capacity['remainingAttendeeCapacity'] )
	);
	axismundi_cal_event_save( $ax_pv_post, array( 'participant_visibility' => 'public' ) );

	// -- reachable from the editor's own envelope -------------------------------------------------------------

	/*
	 * The trap this suite exists for: a column, a writer and a gate with no way for anybody to set it.
	 */
	ax_pv_assert(
		$ax_pv_results,
		'the setting can be written and read back through the envelope the editor uses',
		'public' === (string) axismundi_cal_rest_envelope( $ax_pv_post )['participantVisibility']
			&& array( 'participant_visibility' => 'attendees' ) === axismundi_cal_rest_to_fields( array( 'participantVisibility' => 'attendees' ) )
	);
	// A value nothing understands is refused rather than stored as though it had been chosen.
	ax_pv_assert(
		$ax_pv_results,
		'and a level nothing defines is refused rather than silently opening or closing it',
		is_wp_error( axismundi_cal_event_save( $ax_pv_post, array( 'participant_visibility' => 'everyone' ) ) )
			&& 'public' === axismundi_cal_participant_visibility( $ax_pv_post )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_pv_posts ) as $ax_pv_post_id ) {
		wp_delete_post( (int) $ax_pv_post_id, true );
	}
	foreach ( array_unique( $ax_pv_users ) as $ax_pv_user_id ) {
		wp_delete_user( (int) $ax_pv_user_id );
	}
}

$ax_pv_failures = count( array_filter( $ax_pv_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_pv_results ), $ax_pv_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_pv_failures > 0 ? 1 : 0 );
}
exit( $ax_pv_failures > 0 ? 1 : 0 );
