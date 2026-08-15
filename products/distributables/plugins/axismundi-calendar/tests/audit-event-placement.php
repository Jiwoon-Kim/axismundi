<?php
/**
 * Events that appear on a calendar they were not filed on (dev-only; dist-excluded).
 *
 * Being invited to something puts it on your calendar without moving it off anybody else's. The four
 * properties below are the ones that are easy to get wrong in the direction that loses information:
 * deduplicating a genuine double appearance, treating a refusal as a removal, or letting a personal
 * display setting reach past the participation it is about and hide a calendar somebody subscribed to.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_ep_results = array();
$ax_ep_users   = array();
$ax_ep_posts   = array();

/** @param bool[] $results Results. */
function ax_ep_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated Person Actor. */
function ax_ep_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axep' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	if ( $actor instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $actor->get_identity_id(), 'axep' . strtolower( wp_generate_password( 8, false, false ) ) );
	}
	return $id;
}

/**
 * One published Event on its author's own calendar, taking requests the host answers.
 *
 * `restricted` rather than `free` on purpose: a mode that accepts on arrival has no moment at which
 * somebody is told no, and the setting under test is about what happens to Events that were refused.
 */
function ax_ep_event( array &$posts, int $author, string $title ) : int {
	// Draft first, then published through the writer's own guard: an Event may not reach `publish`
	// without an envelope, and inserting one straight to it is silently downgraded.
	$post_id = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_title' => $title, 'post_status' => 'draft', 'post_author' => $author )
	);
	$posts[] = $post_id;
	axismundi_cal_event_save(
		$post_id,
		array( 'starts_at' => '2026-10-10 19:00:00', 'ends_at' => '2026-10-10 21:00:00', 'timezone' => 'Asia/Seoul', 'join_mode' => 'restricted', 'join_eligibility' => 'public' )
	);
	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;
	return $post_id;
}

/** Titles visible on one calendar in the fixture window. */
function ax_ep_titles( int $calendar_id ) : array {
	return array_map(
		static fn( array $o ) : string => (string) $o['title'],
		axismundi_cal_calendar_occurrences( $calendar_id, '2026-10-01 00:00:00', '2026-10-31 00:00:00' )
	);
}

try {
	$ax_ep_host_user  = ax_ep_user( $ax_ep_users );
	$ax_ep_guest_user = ax_ep_user( $ax_ep_users );
	wp_set_current_user( $ax_ep_host_user );
	$ax_ep_host  = axismundi_actors_get_for_user( $ax_ep_host_user );
	$ax_ep_guest = axismundi_actors_get_for_user( $ax_ep_guest_user );
	$ax_ep_host_cal  = (int) axismundi_cal_primary_calendar( (string) $ax_ep_host->get_uri() )['id'];
	$ax_ep_guest_cal = (int) axismundi_cal_primary_calendar( (string) $ax_ep_guest->get_uri() )['id'];
	// The guest reads the host's calendar in one of the cases below, which needs the host to have published it.
	axismundi_cal_acl_grant( $ax_ep_host_cal, '', 'reader', 'public' );

	$ax_ep_party = ax_ep_event( $ax_ep_posts, $ax_ep_host_user, 'Housewarming' );

	// -- 1. an Event on your own calendar is on it once ------------------------------------------------

	/*
	 * The host joins their own Event. There is one Event on one calendar and two reasons for it to be
	 * there; a second appearance would be the model showing its own bookkeeping to the reader.
	 */
	axismundi_cal_event_join( $ax_ep_party, (string) $ax_ep_host->get_uri() );
	ax_ep_assert(
		$ax_ep_results,
		'joining an Event already on your calendar does not put it there twice',
		1 === count( array_filter( ax_ep_titles( $ax_ep_host_cal ), static fn( string $t ) : bool => 'Housewarming' === $t ) )
	);

	// -- 2. an invitation puts it on your calendar, before you answer ------------------------------------

	/*
	 * It appears while the answer is still outstanding, which is the whole point: something you have not
	 * replied to is exactly what you need to see on your calendar.
	 */
	ax_ep_assert(
		$ax_ep_results,
		'before being invited, a guest\'s calendar does not carry the Event',
		! in_array( 'Housewarming', ax_ep_titles( $ax_ep_guest_cal ), true )
	);
	axismundi_cal_event_join( $ax_ep_party, (string) $ax_ep_guest->get_uri() );
	$ax_ep_state = axismundi_cal_event_participation( $ax_ep_party, (string) $ax_ep_guest->get_uri() );
	ax_ep_assert(
		$ax_ep_results,
		'asking to come puts the Event on the guest\'s own calendar without moving it off the host\'s',
		in_array( 'Housewarming', ax_ep_titles( $ax_ep_guest_cal ), true )
			&& in_array( 'Housewarming', ax_ep_titles( $ax_ep_host_cal ), true )
			&& (int) $ax_ep_host_cal === (int) axismundi_cal_schedule_for_event( $ax_ep_party )['calendar_id']
	);
	ax_ep_assert(
		$ax_ep_results,
		'and the calendar can say why it is there rather than implying it was filed there',
		'filed' === axismundi_cal_event_placement_reason( $ax_ep_host_cal, $ax_ep_party )
			&& in_array( axismundi_cal_event_placement_reason( $ax_ep_guest_cal, $ax_ep_party ), array( 'joined', 'invited' ), true )
	);

	// -- 3. declining is an answer, not a removal ---------------------------------------------------------

	axismundi_cal_event_respond_to_join( $ax_ep_party, (string) $ax_ep_guest->get_uri(), 'reject' );
	ax_ep_assert(
		$ax_ep_results,
		'a declined Event stays on the calendar, because refusing is an answer to the host',
		in_array( 'Housewarming', ax_ep_titles( $ax_ep_guest_cal ), true )
	);
	axismundi_cal_set_shows_declined_events( $ax_ep_guest_cal, (string) $ax_ep_guest->get_uri(), false );
	ax_ep_assert(
		$ax_ep_results,
		'and turning declined Events off hides it without touching the answer that was given',
		! in_array( 'Housewarming', ax_ep_titles( $ax_ep_guest_cal ), true )
			&& is_array( axismundi_cal_event_participation( $ax_ep_party, (string) $ax_ep_guest->get_uri() ) )
	);

	// -- 4. hiding a refusal does not reach the calendar it was on ------------------------------------------

	/*
	 * The case Google shows: the guest also reads the host's calendar, so the Event is on their screen
	 * twice for two different reasons. Turning off declined invitations answers only the participation
	 * one -- it must not quietly remove an Event from a calendar somebody deliberately subscribed to.
	 */
	ax_ep_assert(
		$ax_ep_results,
		'the Event is still on the host\'s calendar, which the guest reads for an unrelated reason',
		in_array( 'Housewarming', ax_ep_titles( $ax_ep_host_cal ), true )
	);
	axismundi_cal_set_shows_declined_events( $ax_ep_guest_cal, (string) $ax_ep_guest->get_uri(), true );
	ax_ep_assert(
		$ax_ep_results,
		'turning them back on returns it, because nothing about the Event was changed',
		in_array( 'Housewarming', ax_ep_titles( $ax_ep_guest_cal ), true )
	);
	// One person's setting is theirs. The host never asked for anything to be hidden.
	ax_ep_assert(
		$ax_ep_results,
		'and the setting belongs to one Actor rather than to the calendar',
		axismundi_cal_shows_declined_events( $ax_ep_host_cal, (string) $ax_ep_host->get_uri() )
	);

	// -- a calendar that is nobody's own collects nothing -----------------------------------------------------

	/*
	 * Participation reaches the participant's own calendar and no other. An ordinary calendar they
	 * happen to own does not start collecting their invitations.
	 */
	$ax_ep_side = axismundi_cal_calendar_save(
		array( 'name' => 'Side calendar', 'slug' => 'axep-' . strtolower( wp_generate_password( 8, false, false ) ), 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => (string) $ax_ep_guest->get_uri() )
	);
	ax_ep_assert(
		$ax_ep_results,
		'another calendar the same Actor owns does not collect what they were invited to',
		! is_wp_error( $ax_ep_side ) && array() === axismundi_cal_placed_event_ids( (int) $ax_ep_side )
	);
	if ( ! is_wp_error( $ax_ep_side ) ) {
		axismundi_cal_calendar_delete( (int) $ax_ep_side );
	}
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_ep_posts ) as $ax_ep_post_id ) {
		wp_delete_post( (int) $ax_ep_post_id, true );
	}
	foreach ( array_unique( $ax_ep_users ) as $ax_ep_user_id ) {
		wp_delete_user( (int) $ax_ep_user_id );
	}
}

$ax_ep_failures = count( array_filter( $ax_ep_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ep_results ), $ax_ep_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ep_failures > 0 ? 1 : 0 );
}
exit( $ax_ep_failures > 0 ? 1 : 0 );
