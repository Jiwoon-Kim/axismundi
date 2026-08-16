<?php
/**
 * Who needs to be told, and what happened.
 *
 * This plugin does not have an inbox and must not grow one. A calendar notice, a mention, a follow
 * and a reply are the same kind of thing to the person receiving them -- "something you need to look
 * at" -- and a product that kept its own list would give them one badge per plugin to check.
 *
 * So what happens here is a declaration and nothing more. Each act says who it concerns and what it
 * was; `Axismundi Notifications` is what turns those into an inbox, and owns everything this
 * deliberately has no opinion about: unread state, bundling, badges, browser push, email, digests.
 * The Activity ledger and this plugin's own tables stay the record; a notification is derived, which
 * is exactly why it can carry as much personal state as somebody wants without touching either.
 *
 * The unit is the recipient **Actor**, never the WordPress user. An Organization is invited to things,
 * removed from things and told when they are called off, and an account that manages three Actors is
 * looking at three sets of notices -- which is the acting-Actor contract already in force everywhere
 * else. Whether a given signed-in user may see one of these is a question about who manages that
 * Actor, asked at display time by the plugin that displays them.
 *
 * Nobody is told about their own act. Notifying somebody that they did what they just did is noise
 * that teaches people to ignore the badge.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The lifecycle notices this plugin declares.
 *
 * Named for what happened rather than for how it should read. "The event was called off" is a fact;
 * whether that becomes a red badge, a line in a digest or nothing at all is a decision for the
 * inbox, and one this plugin would be guessing at.
 */
const AXISMUNDI_CAL_NOTICE_KINDS = array(
	// Something the organizer did to somebody.
	'event_invited',
	'event_invite_withdrawn',
	'event_removed',
	// Something the organizer did to the Event, which concerns everybody still holding it.
	'event_cancelled',
	'event_reinstated',
	// Somebody asking, and the answer they got.
	'event_join_requested',
	'event_joined',
	'event_join_answered',
	'event_join_withdrawn',
	// An invited guest answering, changing their answer, or taking it back.
	'event_invite_answered',
	'event_invite_answer_undone',
);

/**
 * What an Event looked like when something happened to it.
 *
 * Carried with the notice because a notification outlives the thing it is about: an Event that has
 * since been renamed, moved or deleted still produced a notice that has to read sensibly. The
 * snapshot is what it said at the time; anything wanting the current state has the URI.
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
 * Declare that somebody needs to know about something.
 *
 * @param string   $kind         One of the declared kinds.
 * @param int      $post_id      Event post ID.
 * @param string[] $recipients   Actor URIs to tell.
 * @param string   $actor_uri    The Actor whose act this was.
 * @param string   $activity_uri The Activity that recorded it, when there is one.
 * @return void
 */
function axismundi_cal_notify( string $kind, int $post_id, array $recipients, string $actor_uri, string $activity_uri = '' ) : void {
	if ( ! in_array( $kind, AXISMUNDI_CAL_NOTICE_KINDS, true ) ) {
		return;
	}
	$snapshot   = axismundi_cal_notice_snapshot( $post_id );
	$event_uri  = axismundi_cal_event_uri( $post_id );
	$occurred   = current_time( 'mysql', true );
	$actor_uri  = trim( $actor_uri );
	$delivered  = array();
	foreach ( $recipients as $recipient ) {
		$recipient = trim( (string) $recipient );
		// Nobody hears about their own act, and nobody hears about anything twice.
		if ( '' === $recipient || $recipient === $actor_uri || isset( $delivered[ $recipient ] ) ) {
			continue;
		}
		$delivered[ $recipient ] = true;
		/**
		 * Fires once per Actor who needs to know about something on a Calendar.
		 *
		 * The seam `Axismundi Notifications` listens on. Nothing is stored here: a listener that never
		 * arrives means the acts still happened, are still in the ledger, and are still visible on the
		 * screens that show them -- which is the right failure for a plugin that may not be installed.
		 *
		 * @param array<string,mixed> $notice Normalised notice.
		 */
		do_action(
			'axismundi_notify',
			array(
				'kind'                => $kind,
				'recipient_actor_uri' => $recipient,
				'actor_uri'           => $actor_uri,
				'object_uri'          => $event_uri,
				'source_activity_uri' => trim( $activity_uri ),
				'occurred_at'         => $occurred,
				'payload'             => $snapshot,
			)
		);
	}
}

/**
 * Everybody who still has this Event on their calendar.
 *
 * The audience for something that happens to the Event itself, and derived from the placement rule
 * rather than listed again: if it is on your calendar, a change to it is yours to hear about, and if
 * you were removed or you left then it is not. That is the same question `UNPLACED_STATES` already
 * answers, so there is one rule and not two that can disagree.
 *
 * Declining does not take anybody out of it. Somebody who said no still set that evening aside as
 * dealt with, and an Event they turned down being called off is exactly the kind of thing they would
 * otherwise find out by turning up.
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
