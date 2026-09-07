<?php
/**
 * What each Activity meant, and to whom.
 *
 * This plugin has no inbox and does not call one. `Axismundi Notifications` watches the Activity
 * ledger, and when it asks what a committed Activity meant, this answers for the ones Calendar
 * wrote. Everything after that -- storing, fanning out to managers, unread state, badges, transports
 * -- belongs there, and a calendar notice ends up in the same list as a mention and a follow because
 * that is how it looks to the person receiving it.
 *
 * The map, which is the whole of this file:
 *
 *   Invite                          → event_invited              the invited Actor
 *   Undo( Invite )                  → event_invite_withdrawn     the invited Actor
 *   Undo( Join )                    → event_join_withdrawn       the organizer
 *   Undo( Accept|Reject|Tentative ) → event_invite_answer_undone the organizer
 *   Join, still pending             → event_join_requested       the organizer
 *   Join, already accepted          → event_joined               the organizer
 *   Accept|Reject of a Join         → event_join_answered        the Actor who asked
 *   Accept|Reject|Tentative(Invite) → event_invite_answered      the organizer
 *   Remove                          → event_removed              the Actor removed
 *   Update, now cancelled           → event_cancelled            everybody still holding it
 *   Update, no longer cancelled     → event_reinstated           everybody still holding it
 *
 * Resolution happens after the transition and never at record time, which is why each command
 * flushes when its own work is done. A command that records two Activities -- taking an answer back
 * and giving another -- flushes between them, so the retraction is resolved against the answer it
 * retracts rather than against the one that replaced it.
 *
 * Nothing here decides whether somebody is told about their own act. That is a fact about a person,
 * and an Organization is acted for by whichever manager is at the keyboard; the suppression happens
 * where people are known. What this passes along is who performed it.
 *
 * And nothing here judges an Activity. These answers are computed from a ledger entry that is
 * already a fact -- signature, key, whether this instance was even a recipient, whether that Actor
 * may act on that Event, and whether the same Activity has been seen before are all settled before
 * it is recorded. That matters most for the case this reads as ready for: an `Invite` arriving from
 * another server and naming a local Event resolves here exactly like a local one, which is the point
 * -- but "it already resolves" is not "it is already safe", and adding a check here would put the
 * permission question in the one place that runs after the record was made.
 *
 * @see docs/AXISMUNDI-NOTIFICATIONS-ARCHITECTURE.md
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** The kinds Calendar produces, and the attention each asks for. */
const AXISMUNDI_CAL_NOTICE_KINDS = array(
	// Something the organizer did to somebody.
	'axismundi-calendar/event-invited'              => 'immediate',
	'axismundi-calendar/event-invite-withdrawn'     => 'immediate',
	'axismundi-calendar/event-removed'              => 'immediate',
	// Something that happened to the Event, which concerns everybody still holding it.
	'axismundi-calendar/event-cancelled'            => 'immediate',
	'axismundi-calendar/event-reinstated'           => 'bundled',
	// Somebody asking, and the answer they got.
	'axismundi-calendar/event-join-requested'       => 'immediate',
	'axismundi-calendar/event-joined'               => 'bundled',
	'axismundi-calendar/event-join-answered'        => 'immediate',
	'axismundi-calendar/event-join-withdrawn'       => 'bundled',
	// An invited guest answering, or taking their answer back.
	'axismundi-calendar/event-invite-answered'      => 'bundled',
	'axismundi-calendar/event-invite-answer-undone' => 'bundled',
);

/**
 * Tell Notifications what this plugin produces.
 *
 * @return void
 */
function axismundi_cal_register_notice_kinds() : void {
	if ( ! function_exists( 'axismundi_ntf_register_kind' ) ) {
		return;
	}
	foreach ( AXISMUNDI_CAL_NOTICE_KINDS as $kind => $urgency ) {
		axismundi_ntf_register_kind( $kind, array( 'category' => 'calendar', 'urgency' => $urgency ) );
	}
}
add_action( 'axismundi_notification_register_kinds', 'axismundi_cal_register_notice_kinds' );

/**
 * Say the work is done, so what was recorded can be resolved.
 *
 * Called by each command once its state transition is complete and before it returns. Doing it
 * here rather than leaving it to the end of the request is the contract: a fatal error, an `exit`
 * in a redirect handler or a killed request all end without that happening, and what survives is
 * the Activity with no notification beside it.
 *
 * @return void
 */
function axismundi_cal_notify_flush() : void {
	if ( function_exists( 'axismundi_notification_flush' ) ) {
		axismundi_notification_flush();
	}
}

/**
 * What an Event looked like when something happened to it.
 *
 * Carried with the notice because a notification outlives the thing it is about: an Event since
 * renamed, moved or deleted still produced a notice that has to read sensibly.
 *
 * @param int $post_id Event post ID.
 * @return array<string,mixed>
 */
function axismundi_cal_notice_snapshot( int $post_id ) : array {
	$schedule = axismundi_cal_schedule_for_event( $post_id );
	$envelope = axismundi_cal_event_get( $post_id );
	return array(
		'event_post_id' => $post_id,
		'title'         => wp_strip_all_tags( get_the_title( $post_id ) ),
		'url'           => (string) get_permalink( $post_id ),
		'starts_at'     => is_array( $schedule ) ? (string) $schedule['dtstart_local'] : '',
		'timezone'      => is_array( $schedule ) ? (string) $schedule['timezone'] : '',
		'all_day'       => is_array( $schedule ) ? (int) $schedule['all_day'] : 0,
		'event_status'  => is_array( $envelope ) ? (string) $envelope['event_status'] : '',
	);
}

/**
 * Everybody who still has this Event on their calendar.
 *
 * The audience for something that happens to the Event itself, derived from the placement rule
 * rather than listed again: if it is on your calendar, a change to it is yours to hear about, and if
 * you were removed or you left then it is not. Declining does not take anybody out of it -- somebody
 * who said no still set that evening aside as dealt with.
 *
 * @param int $post_id Event post ID.
 * @return string[] Actor URIs.
 */
function axismundi_cal_event_lifecycle_audience( int $post_id ) : array {
	$audience = array();
	foreach ( axismundi_cal_event_participations( $post_id ) as $participation ) {
		if ( in_array( (string) $participation['state'], AXISMUNDI_CAL_UNPLACED_STATES, true ) ) {
			continue;
		}
		$audience[] = (string) $participation['actor_uri'];
	}
	return $audience;
}

/**
 * The Event one Object URI names, or 0.
 *
 * @param string $uri Object URI.
 * @return int Event post ID.
 */
function axismundi_cal_event_post_id_from_uri( string $uri ) : int {
	$uri = trim( $uri );
	if ( '' === $uri ) {
		return 0;
	}
	// Object Projections owns this identity when it is installed, and answers for the URI form it
	// mints; the local resolver answers for the fallback form this plugin mints without it.
	$source = function_exists( 'axismundi_op_resolve_source_by_uri' )
		? apply_filters( 'axismundi_op_resolve_source_by_uri', null, $uri )
		: axismundi_cal_event_resolve_source_by_uri( null, $uri );
	return $source instanceof WP_Post && AXISMUNDI_CAL_EVENT_POST_TYPE === $source->post_type ? (int) $source->ID : 0;
}

/**
 * One intent, filled in from the Event it concerns.
 *
 * @param string   $kind       Kind.
 * @param int      $post_id    Event post ID.
 * @param string[] $recipients Actor URIs.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_notice_intents( string $kind, int $post_id, array $recipients ) : array {
	$intents  = array();
	$snapshot = axismundi_cal_notice_snapshot( $post_id );
	$seen     = array();
	foreach ( $recipients as $recipient ) {
		$recipient = trim( (string) $recipient );
		if ( '' === $recipient || isset( $seen[ $recipient ] ) ) {
			continue;
		}
		$seen[ $recipient ] = true;
		$intents[]          = array(
			'kind'                => $kind,
			'recipient_actor_uri' => $recipient,
			'object_uri'          => axismundi_cal_event_uri( $post_id ),
			'grouping_key'        => $kind . ':' . axismundi_cal_event_uri( $post_id ),
			// Who performed it, so the delivery stage can skip that person and nobody else. Zero when
			// nothing local did -- an Activity that arrived from another server, or a cron run.
			'initiating_local_user_id' => get_current_user_id(),
			'snapshot'            => $snapshot,
		);
	}
	return $intents;
}

/**
 * What the Activity being undone was, or null.
 *
 * @param Axismundi_Activity $activity The `Undo`.
 * @return Axismundi_Activity|null
 */
function axismundi_cal_undone_activity( Axismundi_Activity $activity ) : ?Axismundi_Activity {
	$object = (string) $activity->get_object_uri();
	if ( '' === $object || ! function_exists( 'axismundi_act_get' ) ) {
		return null;
	}
	$undone = axismundi_act_get( $object );
	return $undone instanceof Axismundi_Activity ? $undone : null;
}

/**
 * Answer for the Activities this plugin wrote.
 *
 * @param array<int,array<string,mixed>> $intents  Intents so far.
 * @param Axismundi_Activity             $activity Committed Activity.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_resolve_notification_intents( array $intents, Axismundi_Activity $activity ) : array {
	if ( ! axismundi_cal_ready() ) {
		return $intents;
	}
	$type   = $activity->get_type();
	$object = (string) $activity->get_object_uri();
	$target = (string) $activity->get_target_uri();

	// -- addressed to the Event itself ---------------------------------------------------------------

	if ( 'Invite' === $type ) {
		$post_id = axismundi_cal_event_post_id_from_uri( $object );
		return $post_id > 0 && '' !== $target
			? array_merge( $intents, axismundi_cal_notice_intents( 'axismundi-calendar/event-invited', $post_id, array( $target ) ) )
			: $intents;
	}
	if ( 'Join' === $type ) {
		$post_id = axismundi_cal_event_post_id_from_uri( $object );
		if ( $post_id <= 0 ) {
			return $intents;
		}
		/*
		 * Two different pieces of news from one Activity, told apart by what the Event did with it. A
		 * request is something the organizer has to answer; an arrival on an Event that admits people
		 * on sight is something they only need to know about.
		 */
		$participation = axismundi_cal_event_participation( $post_id, (string) $activity->get_actor_uri() );
		$kind          = is_array( $participation ) && 'accepted' === (string) $participation['state']
			? 'axismundi-calendar/event-joined'
			: 'axismundi-calendar/event-join-requested';
		return array_merge( $intents, axismundi_cal_notice_intents( $kind, $post_id, array( axismundi_cal_event_owner_actor_uri( $post_id ) ) ) );
	}
	if ( 'Remove' === $type ) {
		// `Remove` names the Event as the collection somebody was taken out of, and the Actor as what
		// was taken out -- so the object is a person here and the target is the Event.
		$post_id = axismundi_cal_event_post_id_from_uri( $target );
		return $post_id > 0 && '' !== $object
			? array_merge( $intents, axismundi_cal_notice_intents( 'axismundi-calendar/event-removed', $post_id, array( $object ) ) )
			: $intents;
	}
	if ( 'Update' === $type ) {
		$post_id = axismundi_cal_event_post_id_from_uri( $object );
		if ( $post_id <= 0 ) {
			return $intents;
		}
		$kind = axismundi_cal_event_is_cancelled( $post_id )
			? 'axismundi-calendar/event-cancelled'
			: 'axismundi-calendar/event-reinstated';
		return array_merge( $intents, axismundi_cal_notice_intents( $kind, $post_id, axismundi_cal_event_lifecycle_audience( $post_id ) ) );
	}

	// -- answers, which address the request or the invitation rather than the Event -------------------

	if ( in_array( $type, array( 'Accept', 'Reject', 'TentativeAccept' ), true ) ) {
		$answered = axismundi_cal_undone_activity( $activity );
		if ( ! $answered instanceof Axismundi_Activity ) {
			return $intents;
		}
		if ( 'Join' === $answered->get_type() ) {
			// The organizer answering a request. The news belongs to whoever asked.
			$post_id = axismundi_cal_event_post_id_from_uri( (string) $answered->get_object_uri() );
			return $post_id > 0
				? array_merge( $intents, axismundi_cal_notice_intents( 'axismundi-calendar/event-join-answered', $post_id, array( (string) $answered->get_actor_uri() ) ) )
				: $intents;
		}
		if ( 'Invite' === $answered->get_type() ) {
			// A guest answering an invitation. The organizer is the one waiting on it.
			$post_id = axismundi_cal_event_post_id_from_uri( (string) $answered->get_object_uri() );
			return $post_id > 0
				? array_merge( $intents, axismundi_cal_notice_intents( 'axismundi-calendar/event-invite-answered', $post_id, array( axismundi_cal_event_owner_actor_uri( $post_id ) ) ) )
				: $intents;
		}
		return $intents;
	}

	// -- taking something back -------------------------------------------------------------------------

	if ( 'Undo' === $type ) {
		$undone = axismundi_cal_undone_activity( $activity );
		if ( ! $undone instanceof Axismundi_Activity ) {
			return $intents;
		}
		if ( 'Invite' === $undone->get_type() ) {
			// The host taking an invitation back, which the invited Actor is the one to hear about.
			$post_id = axismundi_cal_event_post_id_from_uri( (string) $undone->get_object_uri() );
			return $post_id > 0 && '' !== (string) $undone->get_target_uri()
				? array_merge( $intents, axismundi_cal_notice_intents( 'axismundi-calendar/event-invite-withdrawn', $post_id, array( (string) $undone->get_target_uri() ) ) )
				: $intents;
		}
		if ( 'Join' === $undone->get_type() ) {
			// Somebody leaving, or taking a request back before it was answered.
			$post_id = axismundi_cal_event_post_id_from_uri( (string) $undone->get_object_uri() );
			return $post_id > 0
				? array_merge( $intents, axismundi_cal_notice_intents( 'axismundi-calendar/event-join-withdrawn', $post_id, array( axismundi_cal_event_owner_actor_uri( $post_id ) ) ) )
				: $intents;
		}
		if ( in_array( $undone->get_type(), array( 'Accept', 'Reject', 'TentativeAccept' ), true ) ) {
			/*
			 * A guest taking their answer back, which leaves the invitation unanswered. Different news
			 * from answering no, and the organizer needs the difference: one is a decision, the other is
			 * a decision withdrawn.
			 */
			$invite  = axismundi_act_get( (string) $undone->get_object_uri() );
			$post_id = $invite instanceof Axismundi_Activity ? axismundi_cal_event_post_id_from_uri( (string) $invite->get_object_uri() ) : 0;
			return $post_id > 0
				? array_merge( $intents, axismundi_cal_notice_intents( 'axismundi-calendar/event-invite-answer-undone', $post_id, array( axismundi_cal_event_owner_actor_uri( $post_id ) ) ) )
				: $intents;
		}
	}
	return $intents;
}
add_filter( 'axismundi_notification_intents', 'axismundi_cal_resolve_notification_intents', 10, 2 );
