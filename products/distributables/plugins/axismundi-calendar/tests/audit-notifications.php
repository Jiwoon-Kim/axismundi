<?php
/**
 * Who needs to be told (dev-only; dist-excluded).
 *
 * This plugin has no inbox and must not grow one -- a mention, a follow, a reply and a calendar
 * notice are the same kind of thing to the person receiving them, and one badge per plugin is how
 * people learn to ignore badges. What is pinned here is the declaration: each act says who it
 * concerns and what it was, and `Axismundi Notifications` turns those into an inbox.
 *
 * So the checks are about the addressing, which is the part only this plugin can know. The recipient
 * is an Actor and never a WordPress user; nobody is told about their own act; and something that
 * happens to the Event reaches everybody still holding it, which is the placement rule and not a
 * second list beside it.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_nt_results = array();
$ax_nt_users   = array();
$ax_nt_posts   = array();
// Collected through the globals array rather than a local: this file is executed inside a function
// by `wp eval-file`, so a plain assignment here is not the variable the listener would reach.
$GLOBALS['ax_nt_seen'] = array();

/** @param bool[] $results Results. */
function ax_nt_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated, published Person Actor. */
function ax_nt_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axnt' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), 'axnt' . strtolower( wp_generate_password( 8, false, false ) ) );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

/** One published Event. */
function ax_nt_event( array &$posts, int $author, int $calendar_id, array $extra = array() ) : int {
	$post_id = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_title' => 'Reading', 'post_status' => 'draft', 'post_author' => $author )
	);
	$posts[] = $post_id;
	$saved   = axismundi_cal_event_save(
		$post_id,
		array_merge(
			array(
				'calendar_id' => $calendar_id,
				'starts_at'   => gmdate( 'Y-m-d H:i:s', strtotime( '+18 days' ) ),
				'ends_at'     => gmdate( 'Y-m-d H:i:s', strtotime( '+18 days +2 hours' ) ),
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

// Everything declared, in order, exactly as a listening plugin would receive it.
add_action(
	'axismundi_notify',
	static function ( array $notice ) : void {
		$GLOBALS['ax_nt_seen'][] = $notice;
	}
);

/** Recipients of the notices of one kind since a mark. */
function ax_nt_recipients( int $since, string $kind ) : array {
	$out = array();
	foreach ( array_slice( (array) $GLOBALS['ax_nt_seen'], $since ) as $notice ) {
		if ( $kind === (string) $notice['kind'] ) {
			$out[] = (string) $notice['recipient_actor_uri'];
		}
	}
	sort( $out );
	return $out;
}

/** Every kind declared since a mark. */
function ax_nt_kinds( int $since ) : array {
	return array_map(
		static fn( array $notice ) : string => (string) $notice['kind'],
		array_slice( (array) $GLOBALS['ax_nt_seen'], $since )
	);
}

try {
	$ax_nt_host_user  = ax_nt_user( $ax_nt_users );
	$ax_nt_guest_user = ax_nt_user( $ax_nt_users );
	wp_set_current_user( $ax_nt_host_user );
	$ax_nt_host  = axismundi_actors_get_for_user( $ax_nt_host_user );
	$ax_nt_guest = axismundi_actors_get_for_user( $ax_nt_guest_user );
	$ax_nt_cal   = (int) axismundi_cal_primary_calendar( (string) $ax_nt_host->get_uri() )['id'];
	axismundi_cal_acl_grant( $ax_nt_cal, '', 'reader', 'public' );

	// -- being asked, and answering ---------------------------------------------------------------------

	$ax_nt_event = ax_nt_event( $ax_nt_posts, $ax_nt_host_user, $ax_nt_cal, array( 'join_mode' => 'restricted' ) );
	$ax_nt_mark  = count( $GLOBALS['ax_nt_seen'] );
	axismundi_cal_event_invite( $ax_nt_event, (string) $ax_nt_guest->get_uri() );
	ax_nt_assert(
		$ax_nt_results,
		'an invitation is declared to the person invited, by Actor and not by account',
		array( (string) $ax_nt_guest->get_uri() ) === ax_nt_recipients( $ax_nt_mark, 'event_invited' )
	);
	/*
	 * The unit that makes an Organization possible. A notice addressed to a WordPress user could not
	 * be delivered to a Group, and an account managing three Actors is looking at three sets of news.
	 */
	$ax_nt_notice = end( $GLOBALS['ax_nt_seen'] );
	ax_nt_assert(
		$ax_nt_results,
		'carrying what it was about, so a notice still reads after the Event has moved on',
		(string) $ax_nt_host->get_uri() === (string) $ax_nt_notice['actor_uri']
			&& 'Reading' === (string) $ax_nt_notice['payload']['title']
			&& '' !== (string) $ax_nt_notice['source_activity_uri']
			&& '' !== (string) $ax_nt_notice['object_uri']
	);

	$ax_nt_mark = count( $GLOBALS['ax_nt_seen'] );
	wp_set_current_user( $ax_nt_guest_user );
	axismundi_cal_event_respond_to_invite( $ax_nt_event, (string) $ax_nt_guest->get_uri(), 'accept' );
	ax_nt_assert(
		$ax_nt_results,
		'and the answer goes back the other way, to the organizer who was waiting on it',
		array( (string) $ax_nt_host->get_uri() ) === ax_nt_recipients( $ax_nt_mark, 'event_invite_answered' )
	);
	// Taking an answer back is its own news: "they have not answered" is not "they said no".
	$ax_nt_mark = count( $GLOBALS['ax_nt_seen'] );
	axismundi_cal_event_undo_invite_response( $ax_nt_event, (string) $ax_nt_guest->get_uri() );
	ax_nt_assert(
		$ax_nt_results,
		'as is taking one back, which is a different thing from answering no',
		array( (string) $ax_nt_host->get_uri() ) === ax_nt_recipients( $ax_nt_mark, 'event_invite_answer_undone' )
	);

	// -- asking, and being answered ---------------------------------------------------------------------

	wp_set_current_user( $ax_nt_host_user );
	$ax_nt_open   = ax_nt_event( $ax_nt_posts, $ax_nt_host_user, $ax_nt_cal );
	$ax_nt_asker  = axismundi_actors_get_for_user( ax_nt_user( $ax_nt_users ) );
	$ax_nt_mark   = count( $GLOBALS['ax_nt_seen'] );
	axismundi_cal_event_join( $ax_nt_open, (string) $ax_nt_asker->get_uri() );
	// An Event that admits people on arrival produces an arrival, not a request to answer.
	ax_nt_assert(
		$ax_nt_results,
		'somebody arriving at an open event is news, and not a request the organizer must answer',
		array( (string) $ax_nt_host->get_uri() ) === ax_nt_recipients( $ax_nt_mark, 'event_joined' )
			&& array() === ax_nt_recipients( $ax_nt_mark, 'event_join_requested' )
	);
	$ax_nt_moderated = ax_nt_event( $ax_nt_posts, $ax_nt_host_user, $ax_nt_cal, array( 'join_mode' => 'restricted' ) );
	$ax_nt_mark      = count( $GLOBALS['ax_nt_seen'] );
	axismundi_cal_event_join( $ax_nt_moderated, (string) $ax_nt_asker->get_uri() );
	axismundi_cal_event_respond_to_join( $ax_nt_moderated, (string) $ax_nt_asker->get_uri(), 'accept' );
	ax_nt_assert(
		$ax_nt_results,
		'while a request reaches the organizer, and their decision reaches the person who asked',
		array( (string) $ax_nt_host->get_uri() ) === ax_nt_recipients( $ax_nt_mark, 'event_join_requested' )
			&& array( (string) $ax_nt_asker->get_uri() ) === ax_nt_recipients( $ax_nt_mark, 'event_join_answered' )
	);

	// -- nobody hears about their own act -----------------------------------------------------------------

	/*
	 * A host counting themselves in is the case that catches this. The recipient of a join notice is
	 * the organizer, and here the organizer and the joiner are the same Actor.
	 */
	$ax_nt_mark = count( $GLOBALS['ax_nt_seen'] );
	axismundi_cal_event_join( $ax_nt_open, (string) $ax_nt_host->get_uri() );
	ax_nt_assert(
		$ax_nt_results,
		'a host counting themselves in is not told that somebody joined',
		array() === ax_nt_kinds( $ax_nt_mark )
	);

	// -- what happens to the Event reaches everybody holding it ---------------------------------------------

	$ax_nt_going   = axismundi_actors_get_for_user( ax_nt_user( $ax_nt_users ) );
	$ax_nt_saidno  = axismundi_actors_get_for_user( ax_nt_user( $ax_nt_users ) );
	$ax_nt_left    = axismundi_actors_get_for_user( ax_nt_user( $ax_nt_users ) );
	$ax_nt_ousted  = axismundi_actors_get_for_user( ax_nt_user( $ax_nt_users ) );
	$ax_nt_party   = ax_nt_event( $ax_nt_posts, $ax_nt_host_user, $ax_nt_cal, array( 'join_mode' => 'restricted' ) );
	foreach ( array( $ax_nt_going, $ax_nt_saidno, $ax_nt_ousted ) as $ax_nt_person ) {
		axismundi_cal_event_invite( $ax_nt_party, (string) $ax_nt_person->get_uri() );
	}
	axismundi_cal_event_respond_to_invite( $ax_nt_party, (string) $ax_nt_going->get_uri(), 'accept' );
	axismundi_cal_event_respond_to_invite( $ax_nt_party, (string) $ax_nt_saidno->get_uri(), 'reject' );
	axismundi_cal_event_respond_to_invite( $ax_nt_party, (string) $ax_nt_ousted->get_uri(), 'accept' );
	axismundi_cal_event_remove_attendee( $ax_nt_party, (string) $ax_nt_ousted->get_uri() );
	// This one asked rather than being asked, because leaving is undoing your own request -- an
	// invitation is answered instead, and a fixture that invited them could not have them leave.
	axismundi_cal_event_join( $ax_nt_party, (string) $ax_nt_left->get_uri() );

	// The removal itself, told to the person it happened to -- the notice that matters most here,
	// because the Event leaves their calendar and silence would look like a deletion.
	ax_nt_assert(
		$ax_nt_results,
		'being taken off the list is told to the person it happened to',
		in_array( (string) $ax_nt_ousted->get_uri(), ax_nt_recipients( 0, 'event_removed' ), true )
	);

	axismundi_cal_event_withdraw_join( $ax_nt_party, (string) $ax_nt_left->get_uri() );
	$ax_nt_mark = count( $GLOBALS['ax_nt_seen'] );
	axismundi_cal_event_cancel( $ax_nt_party );
	$ax_nt_told = ax_nt_recipients( $ax_nt_mark, 'event_cancelled' );
	/*
	 * Somebody who declined is still told. They set that evening aside as dealt with, and an Event
	 * they turned down being called off is precisely the kind of thing they would otherwise discover
	 * by hearing about it from somebody else.
	 */
	ax_nt_assert(
		$ax_nt_results,
		'a cancellation reaches the people coming and the people who said no',
		in_array( (string) $ax_nt_going->get_uri(), $ax_nt_told, true )
			&& in_array( (string) $ax_nt_saidno->get_uri(), $ax_nt_told, true )
	);
	/*
	 * And nobody whose relationship to the Event is over. Being removed or having left is exactly the
	 * state that took it off their calendar, so news about it is no longer theirs -- the same rule,
	 * asked once.
	 */
	ax_nt_assert(
		$ax_nt_results,
		'and nobody who left or was taken off, their relationship to it having ended',
		! in_array( (string) $ax_nt_left->get_uri(), $ax_nt_told, true )
			&& ! in_array( (string) $ax_nt_ousted->get_uri(), $ax_nt_told, true )
	);
	ax_nt_assert(
		$ax_nt_results,
		'and not the organizer, who is the one who called it off',
		! in_array( (string) $ax_nt_host->get_uri(), $ax_nt_told, true )
	);
	// Putting it back on is news for the same people, and its own kind: they have to know which it is.
	$ax_nt_mark = count( $GLOBALS['ax_nt_seen'] );
	axismundi_cal_event_reinstate( $ax_nt_party );
	ax_nt_assert(
		$ax_nt_results,
		'putting it back on reaches the same people, said as its own thing',
		ax_nt_recipients( $ax_nt_mark, 'event_reinstated' ) === $ax_nt_told
	);

	// -- and nothing is kept here ---------------------------------------------------------------------------

	/*
	 * The boundary. A plugin that stored these would own read state, bundling and badges -- and would
	 * be a second inbox beside the one that is supposed to collect mentions, follows and replies too.
	 */
	ax_nt_assert(
		$ax_nt_results,
		'this plugin declares who needs to know and keeps no inbox of its own',
		! function_exists( 'axismundi_cal_notifications_table' )
			&& ! function_exists( 'axismundi_cal_unread_notices' )
	);
	// A site with nothing listening still works: the acts happened and the ledger holds them.
	ax_nt_assert(
		$ax_nt_results,
		'and an act still happens when nothing is listening for the notice',
		'pending' === axismundi_cal_event_invite( $ax_nt_moderated, (string) $ax_nt_going->get_uri() )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_nt_posts ) as $ax_nt_post_id ) {
		if ( $ax_nt_post_id > 0 ) {
			wp_delete_post( (int) $ax_nt_post_id, true );
		}
	}
	foreach ( array_unique( $ax_nt_users ) as $ax_nt_user_id ) {
		wp_delete_user( (int) $ax_nt_user_id );
	}
}

$ax_nt_failures = count( array_filter( $ax_nt_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_nt_results ), $ax_nt_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_nt_failures > 0 ? 1 : 0 );
}
exit( $ax_nt_failures > 0 ? 1 : 0 );
