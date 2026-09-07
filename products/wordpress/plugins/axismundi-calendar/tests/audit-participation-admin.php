<?php
/**
 * The participation screen (dev-only; dist-excluded).
 *
 * Every section is a projection of participation state, so what this file checks is that the screen
 * shows nothing the model does not hold and offers nothing the model would refuse. `Requests` appears
 * only where requests are actually held, `Participants` counts the one state capacity counts, and
 * `Invitations` is absent rather than empty -- a tab announcing a feature that cannot be used reads
 * as broken to anybody who does not know it is unbuilt.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_pa_results   = array();
$ax_pa_posts     = array();
$ax_pa_calendars = array();
$ax_pa_users     = array();

/** @param bool[] $results Results. */
function ax_pa_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A user with an activated public Person Actor. */
function ax_pa_user( array &$users ) : array {
	$handle  = 'axpa' . strtolower( wp_generate_password( 8, false, false ) );
	$id      = (int) wp_insert_user( array( 'user_login' => $handle, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$users[] = $id;
	$uri     = '';
	if ( function_exists( 'axismundi_actors_ensure_for_user' ) ) {
		$actor = axismundi_actors_ensure_for_user( $id );
		if ( $actor instanceof Axismundi_Actor ) {
			axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
			axismundi_actors_register_handle( $actor->get_identity_id(), $handle );
			$uri = (string) $actor->get_uri();
		}
	}
	return array( 'user_id' => $id, 'actor_uri' => $uri );
}

/** The screen's markup for one Event, or for none, as one user. */
function ax_pa_render( int $user_id, int $post_id = 0 ) : string {
	wp_set_current_user( $user_id );
	if ( $post_id > 0 ) {
		$_GET['event'] = $post_id;
	} else {
		unset( $_GET['event'] );
	}
	ob_start();
	axismundi_cal_render_participation_page();
	return (string) ob_get_clean();
}

try {
	$ax_pa_host  = ax_pa_user( $ax_pa_users );
	$ax_pa_alice = ax_pa_user( $ax_pa_users );
	$ax_pa_bob   = ax_pa_user( $ax_pa_users );
	wp_set_current_user( $ax_pa_host['user_id'] );

	$ax_pa_suffix   = strtolower( wp_generate_password( 6, false, false ) );
	$ax_pa_calendar = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Screen fixture', 'slug' => 'ax-pa-' . $ax_pa_suffix, 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_pa_host['actor_uri'] )
	);
	$ax_pa_calendars[] = $ax_pa_calendar;
	axismundi_cal_acl_grant( $ax_pa_calendar, '', 'reader', 'public' );

	$ax_pa_make = static function ( array &$posts, int $calendar, string $title, array $fields ) : int {
		$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_title' => $title, 'post_author' => get_current_user_id() ) );
		$posts[] = $post_id;
		axismundi_cal_event_save(
			$post_id,
			array_merge( array( 'calendar_id' => $calendar, 'timezone' => 'Asia/Seoul', 'starts_at' => '2027-02-13 19:00:00', 'ends_at' => '2027-02-13 21:00:00' ), $fields )
		);
		$GLOBALS['axismundi_cal_rest_write'] = true;
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		$GLOBALS['axismundi_cal_rest_write'] = false;
		return $post_id;
	};

	$ax_pa_open = $ax_pa_make( $ax_pa_posts, $ax_pa_calendar, 'Open house', array( 'join_mode' => 'free' ) );
	$ax_pa_mod  = $ax_pa_make( $ax_pa_posts, $ax_pa_calendar, 'Moderated', array( 'join_mode' => 'restricted', 'maximum_attendee_capacity' => 2 ) );
	$ax_pa_shut = $ax_pa_make( $ax_pa_posts, $ax_pa_calendar, 'No participation', array( 'join_mode' => 'none' ) );

	axismundi_cal_event_join( $ax_pa_open, $ax_pa_alice['actor_uri'] );
	axismundi_cal_event_join( $ax_pa_mod, $ax_pa_alice['actor_uri'] );
	axismundi_cal_event_join( $ax_pa_mod, $ax_pa_bob['actor_uri'] );

	// -- the way in ------------------------------------------------------------------------------------

	ax_pa_assert(
		$ax_pa_results,
		'an event that takes replies offers a way into its participation, counting what is waiting',
		false !== strpos( (string) ( axismundi_cal_participation_row_action( array(), get_post( $ax_pa_mod ) )['ax_participation'] ?? '' ), 'Participation (2 waiting)' )
	);
	/*
	 * An Event taking no replies has no participation to show, and a link to an empty screen is a
	 * promise the Event has explicitly not made.
	 */
	ax_pa_assert(
		$ax_pa_results,
		'an event taking none offers no link at all',
		! isset( axismundi_cal_participation_row_action( array(), get_post( $ax_pa_shut ) )['ax_participation'] )
	);
	wp_set_current_user( $ax_pa_bob['user_id'] );
	ax_pa_assert(
		$ax_pa_results,
		'and somebody who does not run it is offered nothing, whatever the mode',
		! isset( axismundi_cal_participation_row_action( array(), get_post( $ax_pa_mod ) )['ax_participation'] )
	);

	// -- what the screen shows -------------------------------------------------------------------------

	$ax_pa_mod_html = ax_pa_render( $ax_pa_host['user_id'], $ax_pa_mod );
	ax_pa_assert(
		$ax_pa_results,
		'a moderated event shows the requests it is holding, with both answers offered',
		false !== strpos( $ax_pa_mod_html, 'Requests' )
			&& false !== strpos( $ax_pa_mod_html, 'ax_cal_answer_request' )
			&& false !== strpos( $ax_pa_mod_html, '>Accept<' )
			&& false !== strpos( $ax_pa_mod_html, '>Reject<' )
	);
	$ax_pa_open_html = ax_pa_render( $ax_pa_host['user_id'], $ax_pa_open );
	/*
	 * An Event admitting people on arrival has nothing waiting, so a section offering to approve them
	 * would be a decision that had already been made.
	 */
	ax_pa_assert(
		$ax_pa_results,
		'an event that admits people on arrival shows no approval section',
		false === strpos( $ax_pa_open_html, 'Requests' )
			&& false === strpos( $ax_pa_open_html, 'ax_cal_answer_request' )
	);
	/*
	 * Both are an `Accept` in the ledger and the state is the same. An organizer scanning a list wants
	 * to know which of these they personally let in, and on an open Event the answer is none of them.
	 */
	ax_pa_assert(
		$ax_pa_results,
		'an automatic acceptance says so, rather than crediting somebody with the decision',
		false !== strpos( $ax_pa_open_html, 'Accepted automatically' )
	);
	ax_pa_assert(
		$ax_pa_results,
		'while a decided one reads as a plain acceptance',
		'Accepted' === axismundi_cal_participation_state_label( array( 'state' => 'accepted' ), 'restricted' )
			&& 'Accepted automatically' === axismundi_cal_participation_state_label( array( 'state' => 'accepted' ), 'free' )
	);
	/*
	 * Absent rather than empty. A tab announcing a feature that cannot be used reads as broken to
	 * somebody who does not know it is unbuilt, and invites the question every time.
	 */
	ax_pa_assert(
		$ax_pa_results,
		'nothing anywhere announces invitations, which do not exist yet',
		false === stripos( $ax_pa_mod_html, 'Invitation' ) && false === stripos( $ax_pa_open_html, 'Invitation' )
	);

	// -- landing without an event ---------------------------------------------------------------------

	/*
	 * The menu item has to land somewhere. Registering the page with an empty menu title and removing
	 * it afterwards is the usual way to hide a page reached only by link, and it leaves the admin
	 * header reading a title out of the submenu that was just taken away -- so the page is named, and
	 * what it lands on is an index of the Events that have participation to manage.
	 */
	$ax_pa_index = ax_pa_render( $ax_pa_host['user_id'] );
	ax_pa_assert(
		$ax_pa_results,
		'opening the screen without an event lists the ones that take replies',
		false !== strpos( $ax_pa_index, 'Open house' ) && false !== strpos( $ax_pa_index, 'Moderated' )
	);
	/*
	 * An index listing Events somebody cannot open would be a list of doors that do not turn -- and
	 * one taking no replies has no participation to manage in the first place.
	 */
	ax_pa_assert(
		$ax_pa_results,
		'and leaves out the ones taking none',
		false === strpos( $ax_pa_index, 'No participation' )
	);
	ax_pa_assert(
		$ax_pa_results,
		'while somebody who runs nothing is shown nothing rather than a list they cannot use',
		false === strpos( ax_pa_render( $ax_pa_bob['user_id'] ), 'Moderated' )
	);

	// -- the counts agree with the model ---------------------------------------------------------------

	ax_pa_assert(
		$ax_pa_results,
		'a limit reads as places left rather than as a bare number',
		false !== strpos( $ax_pa_mod_html, '2 of 2 places left' )
	);
	axismundi_cal_event_respond_to_join( $ax_pa_mod, $ax_pa_alice['actor_uri'], 'accept' );
	$ax_pa_after = ax_pa_render( $ax_pa_host['user_id'], $ax_pa_mod );
	ax_pa_assert(
		$ax_pa_results,
		'and falls as somebody is admitted, the queue behind them having held no place',
		false !== strpos( $ax_pa_after, '1 of 2 places left' )
	);
	/*
	 * No limit is not a limit of nought. An organizer glancing at "0 left" on an Event that never had
	 * a capacity would believe it was full.
	 */
	ax_pa_assert(
		$ax_pa_results,
		'an event with no limit says so instead of reporting itself full',
		false !== strpos( $ax_pa_open_html, 'No limit' ) && false === strpos( $ax_pa_open_html, 'places left' )
	);
	ax_pa_assert(
		$ax_pa_results,
		'the accepted list holds only the people the capacity is counting',
		1 === substr_count( $ax_pa_after, 'Accepted<' )
			&& 1 === count( axismundi_cal_event_participations( $ax_pa_mod, array( 'accepted' ) ) )
	);
} finally {
	unset( $_GET['event'] );
	wp_set_current_user( 0 );
	foreach ( $ax_pa_posts as $ax_pa_post ) {
		wp_delete_post( (int) $ax_pa_post, true );
	}
	foreach ( $ax_pa_calendars as $ax_pa_cal ) {
		axismundi_cal_calendar_delete( (int) $ax_pa_cal );
	}
	foreach ( $ax_pa_users as $ax_pa_user_id ) {
		wp_delete_user( (int) $ax_pa_user_id );
	}
}

$ax_pa_failures = count( array_filter( $ax_pa_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_pa_results ), $ax_pa_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_pa_failures > 0 ? 1 : 0 );
}
exit( $ax_pa_failures > 0 ? 1 : 0 );
