<?php
/**
 * What each Activity meant, and to whom (dev-only; dist-excluded).
 *
 * This plugin has no inbox and does not call one. It answers, for the Activities it wrote, who needs
 * to know and what kind of thing happened -- and every answer is keyed to a committed Activity, so a
 * remote `Invite` resolves exactly like a local one and nothing here learns federation.
 *
 * The checks read the resolver directly rather than through Notifications, which may not be
 * installed. What is being pinned is the mapping and the addressing: those are the parts only this
 * plugin can know.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_nt_results = array();
$ax_nt_users   = array();
$ax_nt_posts   = array();

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

/** What the resolver says one Actor's most recent Activity of a type meant. */
function ax_nt_intents( string $actor_uri, string $type, ?string $object_uri = null ) : array {
	foreach ( axismundi_act_get_by_actor( $actor_uri, 50 ) as $activity ) {
		if ( $type !== $activity->get_type() ) {
			continue;
		}
		if ( null !== $object_uri && $object_uri !== (string) $activity->get_object_uri() ) {
			continue;
		}
		return (array) apply_filters( 'axismundi_notification_intents', array(), $activity );
	}
	return array();
}

/** Recipients named by a set of intents, of one kind. */
function ax_nt_recipients( array $intents, string $kind ) : array {
	$out = array();
	foreach ( $intents as $intent ) {
		if ( $kind === (string) $intent['kind'] ) {
			$out[] = (string) $intent['recipient_actor_uri'];
		}
	}
	sort( $out );
	return $out;
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
	$ax_nt_uri   = axismundi_cal_event_uri( $ax_nt_event );
	axismundi_cal_event_invite( $ax_nt_event, (string) $ax_nt_guest->get_uri() );
	$ax_nt_invited = ax_nt_intents( (string) $ax_nt_host->get_uri(), 'Invite', $ax_nt_uri );
	ax_nt_assert(
		$ax_nt_results,
		'an Invite means the invited Actor was asked, addressed by Actor and not by account',
		array( (string) $ax_nt_guest->get_uri() ) === ax_nt_recipients( $ax_nt_invited, 'axismundi-calendar/event-invited' )
	);
	/*
	 * The snapshot travels with it, because a notification outlives what it is about: an Event since
	 * renamed, moved or deleted still produced a notice that has to read sensibly.
	 */
	ax_nt_assert(
		$ax_nt_results,
		'carrying what it was about, and who performed it, for the stage that knows people',
		'Reading' === (string) $ax_nt_invited[0]['snapshot']['title']
			&& $ax_nt_uri === (string) $ax_nt_invited[0]['object_uri']
			&& $ax_nt_host_user === (int) $ax_nt_invited[0]['initiating_local_user_id']
	);

	wp_set_current_user( $ax_nt_guest_user );
	axismundi_cal_event_respond_to_invite( $ax_nt_event, (string) $ax_nt_guest->get_uri(), 'accept' );
	ax_nt_assert(
		$ax_nt_results,
		'and the answer to one goes back to the organizer who was waiting on it',
		array( (string) $ax_nt_host->get_uri() ) === ax_nt_recipients(
			ax_nt_intents( (string) $ax_nt_guest->get_uri(), 'Accept' ),
			'axismundi-calendar/event-invite-answered'
		)
	);
	// Taking an answer back is its own news: "they have not answered" is not "they said no".
	axismundi_cal_event_undo_invite_response( $ax_nt_event, (string) $ax_nt_guest->get_uri() );
	ax_nt_assert(
		$ax_nt_results,
		'as is taking one back, which the resolver tells apart by what was undone',
		array( (string) $ax_nt_host->get_uri() ) === ax_nt_recipients(
			ax_nt_intents( (string) $ax_nt_guest->get_uri(), 'Undo' ),
			'axismundi-calendar/event-invite-answer-undone'
		)
	);

	// -- asking, and being answered ---------------------------------------------------------------------

	wp_set_current_user( $ax_nt_host_user );
	$ax_nt_open  = ax_nt_event( $ax_nt_posts, $ax_nt_host_user, $ax_nt_cal );
	$ax_nt_asker = axismundi_actors_get_for_user( ax_nt_user( $ax_nt_users ) );
	axismundi_cal_event_join( $ax_nt_open, (string) $ax_nt_asker->get_uri() );
	/*
	 * One Activity, two different pieces of news, told apart by what the Event did with it -- which is
	 * why resolution waits for the transition. At record time the row does not exist yet.
	 */
	$ax_nt_arrival = ax_nt_intents( (string) $ax_nt_asker->get_uri(), 'Join', axismundi_cal_event_uri( $ax_nt_open ) );
	ax_nt_assert(
		$ax_nt_results,
		'somebody arriving at an open event is news, and not a request the organizer must answer',
		array( (string) $ax_nt_host->get_uri() ) === ax_nt_recipients( $ax_nt_arrival, 'axismundi-calendar/event-joined' )
			&& array() === ax_nt_recipients( $ax_nt_arrival, 'axismundi-calendar/event-join-requested' )
	);
	$ax_nt_moderated = ax_nt_event( $ax_nt_posts, $ax_nt_host_user, $ax_nt_cal, array( 'join_mode' => 'restricted' ) );
	axismundi_cal_event_join( $ax_nt_moderated, (string) $ax_nt_asker->get_uri() );
	ax_nt_assert(
		$ax_nt_results,
		'while a request on a moderated one is something the organizer has to answer',
		array( (string) $ax_nt_host->get_uri() ) === ax_nt_recipients(
			ax_nt_intents( (string) $ax_nt_asker->get_uri(), 'Join', axismundi_cal_event_uri( $ax_nt_moderated ) ),
			'axismundi-calendar/event-join-requested'
		)
	);
	axismundi_cal_event_respond_to_join( $ax_nt_moderated, (string) $ax_nt_asker->get_uri(), 'accept' );
	ax_nt_assert(
		$ax_nt_results,
		'and their decision belongs to the person who asked, not to the person who made it',
		array( (string) $ax_nt_asker->get_uri() ) === ax_nt_recipients(
			ax_nt_intents( (string) $ax_nt_host->get_uri(), 'Accept' ),
			'axismundi-calendar/event-join-answered'
		)
	);
	// Leaving is the organizer's business: a place opened up.
	axismundi_cal_event_withdraw_join( $ax_nt_open, (string) $ax_nt_asker->get_uri() );
	ax_nt_assert(
		$ax_nt_results,
		'somebody leaving is told to the organizer, an Undo of a Join being a different thing again',
		array( (string) $ax_nt_host->get_uri() ) === ax_nt_recipients(
			ax_nt_intents( (string) $ax_nt_asker->get_uri(), 'Undo' ),
			'axismundi-calendar/event-join-withdrawn'
		)
	);

	// -- what happens to the Event reaches everybody holding it ---------------------------------------------

	$ax_nt_going  = axismundi_actors_get_for_user( ax_nt_user( $ax_nt_users ) );
	$ax_nt_saidno = axismundi_actors_get_for_user( ax_nt_user( $ax_nt_users ) );
	$ax_nt_left   = axismundi_actors_get_for_user( ax_nt_user( $ax_nt_users ) );
	$ax_nt_ousted = axismundi_actors_get_for_user( ax_nt_user( $ax_nt_users ) );
	$ax_nt_party  = ax_nt_event( $ax_nt_posts, $ax_nt_host_user, $ax_nt_cal, array( 'join_mode' => 'restricted' ) );
	foreach ( array( $ax_nt_going, $ax_nt_saidno, $ax_nt_ousted ) as $ax_nt_person ) {
		axismundi_cal_event_invite( $ax_nt_party, (string) $ax_nt_person->get_uri() );
	}
	axismundi_cal_event_respond_to_invite( $ax_nt_party, (string) $ax_nt_going->get_uri(), 'accept' );
	axismundi_cal_event_respond_to_invite( $ax_nt_party, (string) $ax_nt_saidno->get_uri(), 'reject' );
	axismundi_cal_event_respond_to_invite( $ax_nt_party, (string) $ax_nt_ousted->get_uri(), 'accept' );
	axismundi_cal_event_remove_attendee( $ax_nt_party, (string) $ax_nt_ousted->get_uri() );
	// This one asked rather than being asked, because leaving is undoing your own request.
	axismundi_cal_event_join( $ax_nt_party, (string) $ax_nt_left->get_uri() );
	axismundi_cal_event_withdraw_join( $ax_nt_party, (string) $ax_nt_left->get_uri() );

	ax_nt_assert(
		$ax_nt_results,
		'being taken off the list is told to the person it happened to, the Remove naming them',
		array( (string) $ax_nt_ousted->get_uri() ) === ax_nt_recipients(
			ax_nt_intents( (string) $ax_nt_host->get_uri(), 'Remove' ),
			'axismundi-calendar/event-removed'
		)
	);

	axismundi_cal_event_cancel( $ax_nt_party );
	$ax_nt_told = ax_nt_recipients(
		ax_nt_intents( (string) $ax_nt_host->get_uri(), 'Update', axismundi_cal_event_uri( $ax_nt_party ) ),
		'axismundi-calendar/event-cancelled'
	);
	/*
	 * Somebody who declined is still told. They set that evening aside as dealt with, and an Event
	 * they turned down being called off is precisely what they would otherwise hear from somebody else.
	 */
	ax_nt_assert(
		$ax_nt_results,
		'a cancellation reaches the people coming and the people who said no',
		in_array( (string) $ax_nt_going->get_uri(), $ax_nt_told, true )
			&& in_array( (string) $ax_nt_saidno->get_uri(), $ax_nt_told, true )
	);
	/*
	 * And nobody whose relationship to it is over. Being removed or having left is exactly the state
	 * that took it off their calendar, so news about it is no longer theirs -- the same rule, asked
	 * once, rather than a second list that could disagree with the first.
	 */
	ax_nt_assert(
		$ax_nt_results,
		'and nobody who left or was taken off, their relationship to it having ended',
		! in_array( (string) $ax_nt_left->get_uri(), $ax_nt_told, true )
			&& ! in_array( (string) $ax_nt_ousted->get_uri(), $ax_nt_told, true )
	);

	// -- and this plugin keeps none of it ---------------------------------------------------------------------

	/*
	 * The boundary. Calendar answers what an Activity meant; where that ends up, who has read it and
	 * whether it becomes an email are questions for the plugin that owns inboxes -- and a calendar
	 * notice belongs in the same list as a mention because that is how it looks to the reader.
	 */
	ax_nt_assert(
		$ax_nt_results,
		'this plugin answers what an Activity meant and keeps no inbox of its own',
		! function_exists( 'axismundi_cal_notifications_table' )
			&& ! function_exists( 'axismundi_cal_unread_notices' )
			&& has_filter( 'axismundi_notification_intents', 'axismundi_cal_resolve_notification_intents' )
	);
	// Every kind it can answer with is registered, so nothing arrives in an inbox under a name no
	// settings screen can describe.
	ax_nt_assert(
		$ax_nt_results,
		'and every kind it can produce is one it declared',
		11 === count( AXISMUNDI_CAL_NOTICE_KINDS )
			&& array() === array_diff(
				array( 'axismundi-calendar/event-invited', 'axismundi-calendar/event-cancelled', 'axismundi-calendar/event-removed' ),
				array_keys( AXISMUNDI_CAL_NOTICE_KINDS )
			)
	);
	// An act still happens when nothing is listening, which is what makes the dependency one-way.
	ax_nt_assert(
		$ax_nt_results,
		'and an act still happens when nothing is listening for what it meant',
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
