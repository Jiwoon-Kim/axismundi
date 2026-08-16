<?php
/**
 * Who may see who is coming.
 *
 * An Event being public says a stranger may read the Event. It does not say they may read the guest
 * list, and treating those as one question turns RSVP into a way of harvesting Actor URIs: fetch a
 * public Event, collect everybody who answered. So this is a second axis, and it is closed by
 * default -- `organizers`, which is the answer that discloses nothing somebody did not already know
 * about their own reply.
 *
 * The viewer is an argument and never a guess. These answers are read by surfaces with completely
 * different notions of "who is asking": an anonymous `.jscalendar` fetch has no viewer at all, a
 * REST call has the acting Actor of a signed-in user, and an iCalendar feed is a broadcast to
 * whoever holds the URL. An evaluator that reached for the session would put a signed-in reader's
 * guest list into a response that gets cached and served to everybody else.
 *
 *   organizers  the organizer sees everyone; each participant sees only their own row.
 *   attendees   the organizer sees everyone; accepted and tentative see each other;
 *               pending and rejected are visible to the organizer and to themselves.
 *   public      accepted and tentative are visible to anybody, including anonymous readers;
 *               pending and rejected remain organizer-and-self.
 *   private     the organizer and the participant themselves, and nobody else.
 *
 * `pending` and `rejected` never widen past organizer-and-self at any level. Somebody who declined
 * has not agreed to have the declining published, and somebody who has not answered has agreed to
 * nothing at all.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** The levels, from the most closed to the most open. */
const AXISMUNDI_CAL_PARTICIPANT_VISIBILITIES = array( 'private', 'organizers', 'attendees', 'public' );

/** States whose holder has agreed to be seen by the other guests. */
const AXISMUNDI_CAL_SHOWN_STATES = array( 'accepted', 'tentative' );

/**
 * The level one Event is set to.
 *
 * @param int $post_id Event post ID.
 * @return string
 */
function axismundi_cal_participant_visibility( int $post_id ) : string {
	$envelope = axismundi_cal_event_get( $post_id );
	$value    = is_array( $envelope ) ? (string) ( $envelope['participant_visibility'] ?? '' ) : '';
	// An unknown value reads as the closed answer rather than the open one: a column somebody edited by
	// hand, or a row from a future version, must not disclose more than the software understands.
	return in_array( $value, AXISMUNDI_CAL_PARTICIPANT_VISIBILITIES, true ) ? $value : 'organizers';
}

/**
 * Whether one viewer may see one participation row.
 *
 * @param int                      $post_id       Event post ID.
 * @param array<string,mixed>      $participation Participation row.
 * @param string|null              $viewer        Viewing Actor URI, or null for an anonymous reader.
 * @param array<string,mixed>|null $context       Optional precomputed context, to avoid re-querying per row.
 * @return bool
 */
function axismundi_cal_participation_visible_to( int $post_id, array $participation, ?string $viewer, ?array $context = null ) : bool {
	$level    = (string) ( $context['level'] ?? axismundi_cal_participant_visibility( $post_id ) );
	$organizer = (string) ( $context['organizer'] ?? axismundi_cal_event_owner_actor_uri( $post_id ) );
	$viewer   = null === $viewer ? null : trim( $viewer );
	$subject  = trim( (string) ( $participation['actor_uri'] ?? '' ) );
	$state    = (string) ( $participation['state'] ?? '' );

	// The two answers that hold at every level, and the reason nothing below has to repeat them.
	if ( null !== $viewer && '' !== $viewer ) {
		if ( $viewer === $organizer && '' !== $organizer ) {
			return true;
		}
		if ( $viewer === $subject ) {
			return true;
		}
	}
	if ( 'private' === $level || 'organizers' === $level ) {
		return false;
	}
	if ( ! in_array( $state, AXISMUNDI_CAL_SHOWN_STATES, true ) ) {
		// A refusal and an unanswered invitation are nobody else's business at any level.
		return false;
	}
	if ( 'public' === $level ) {
		return true;
	}
	// `attendees`: the people who said they are coming can see each other, and nobody else can.
	if ( null === $viewer || '' === $viewer ) {
		return false;
	}
	$own = axismundi_cal_event_participation( $post_id, $viewer );
	return is_array( $own ) && in_array( (string) $own['state'], AXISMUNDI_CAL_SHOWN_STATES, true );
}

/**
 * The participation rows one viewer may see.
 *
 * @param int         $post_id Event post ID.
 * @param string|null $viewer  Viewing Actor URI, or null for an anonymous reader. Required: callers
 *                             state who is asking, because only they know.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_visible_participants( int $post_id, ?string $viewer ) : array {
	$context = array(
		'level'     => axismundi_cal_participant_visibility( $post_id ),
		'organizer' => axismundi_cal_event_owner_actor_uri( $post_id ),
	);
	$visible = array();
	foreach ( axismundi_cal_event_participations( $post_id ) as $participation ) {
		if ( axismundi_cal_participation_visible_to( $post_id, $participation, $viewer, $context ) ) {
			$visible[] = $participation;
		}
	}
	return $visible;
}

/**
 * The people coming, as one viewer may see them.
 *
 * The published collection is the accepted replies. Somebody answering tentatively is visible to the
 * other guests at the levels that show them -- they said they are leaning yes -- but they are not yet
 * one of the attendees, and a collection that counted them would answer "who is coming" with people
 * who have not said they are.
 *
 * @param int         $post_id Event post ID.
 * @param string|null $viewer  Viewing Actor URI, or null for an anonymous reader.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_visible_attendees( int $post_id, ?string $viewer ) : array {
	return array_values(
		array_filter(
			axismundi_cal_visible_participants( $post_id, $viewer ),
			static fn( array $row ) : bool => 'accepted' === (string) $row['state']
		)
	);
}

/**
 * How many people are coming, for a viewer who may not see who they are.
 *
 * A count discloses less than a list and answers the question most readers actually have. It is
 * still gated: at `private` and `organizers` a stranger learns nothing, because "eleven people are
 * coming to this private gathering" is itself something the organizer did not publish.
 *
 * @param int         $post_id Event post ID.
 * @param string|null $viewer  Viewing Actor URI, or null.
 * @return int|null Count, or null when the viewer may not be told.
 */
function axismundi_cal_visible_participant_count( int $post_id, ?string $viewer ) : ?int {
	$level     = axismundi_cal_participant_visibility( $post_id );
	$organizer = axismundi_cal_event_owner_actor_uri( $post_id );
	if ( null !== $viewer && '' !== trim( $viewer ) && trim( $viewer ) === $organizer ) {
		return count( axismundi_cal_event_participations( $post_id, array( 'accepted' ) ) );
	}
	if ( in_array( $level, array( 'private', 'organizers' ), true ) ) {
		return null;
	}
	if ( 'attendees' === $level ) {
		$own = null === $viewer ? null : axismundi_cal_event_participation( $post_id, trim( $viewer ) );
		if ( ! is_array( $own ) || ! in_array( (string) $own['state'], AXISMUNDI_CAL_SHOWN_STATES, true ) ) {
			return null;
		}
	}
	return count( axismundi_cal_event_participations( $post_id, array( 'accepted' ) ) );
}

/**
 * Give every Event written before this axis existed the closed answer.
 *
 * @return int Rows filled.
 */
function axismundi_cal_backfill_participant_visibility() : int {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return 0;
	}
	$table = axismundi_cal_events_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own table.
	return (int) $wpdb->query( "UPDATE {$table} SET participant_visibility = 'organizers' WHERE participant_visibility = '' OR participant_visibility NOT IN ( 'private', 'organizers', 'attendees', 'public' )" );
}
