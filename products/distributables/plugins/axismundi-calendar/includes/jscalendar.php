<?php
/**
 * One Event as JSCalendar (RFC 8984).
 *
 * A read projection, not a second home for the data. The domain model already stores what JSCalendar
 * names -- a civil start, a zone, a wall-clock length, a recurrence and its exceptions -- and this
 * says the same things in that vocabulary. Writing it is also how we find out whether the model
 * really is JSCalendar-shaped, which is the point of doing it before deciding to migrate anything.
 *
 * Two contracts carry over unchanged and must not be quietly restated here:
 *
 * The organizer is the Event's acting Actor -- the identity it was published as. It is not the
 * calendar's authority, which owns the collection and answers for sharing; a writer adding an Event
 * to somebody else's calendar organizes their own event on it. There is deliberately no `author`
 * field: JSCalendar has no such property, provenance is already in the ActivityPub `Create`, and
 * inventing a non-standard one to say what `organizer` already says would leave two answers.
 *
 * The gate is `event_listable()`, the same predicate the feed, the grid and the collection ask. A
 * fifth public surface with its own opinion about what is public is how one of them ends up wrong.
 *
 * `duration` rather than an end time, which is the model's own semantics: an event that runs
 * 19:00-21:00 runs two hours on every occurrence, including the night the clocks change.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** The media type RFC 8984 registers, with the object type it carries. */
const AXISMUNDI_CAL_JSCALENDAR_MEDIA_TYPE = 'application/jscalendar+json; type=Event';

/**
 * FEP-8a8e event status as JSCalendar says it.
 *
 * JSCalendar has three; the wire vocabulary has six. The extra ones are all still scheduled events
 * with something to say about themselves, so they map to `confirmed` and keep their own word in the
 * ActivityStreams projection rather than being flattened into `tentative` here.
 *
 * @param string $status Stored status.
 * @return string
 */
function axismundi_cal_jscalendar_status( string $status ) : string {
	switch ( $status ) {
		case 'EventCancelled':
			return 'cancelled';
		case 'EventTentative':
			return 'tentative';
		default:
			return 'confirmed';
	}
}

/**
 * A wall-clock length as an ISO 8601 duration.
 *
 * Days are kept as days rather than folded into hours: `P1D` is one calendar day, which on the night
 * the clocks change is not 24 hours, and that difference is the whole reason the model carries a
 * civil length instead of an elapsed one.
 *
 * @param DateInterval $interval Civil interval.
 * @return string
 */
function axismundi_cal_jscalendar_duration( DateInterval $interval ) : string {
	$days = (int) $interval->days > 0 ? (int) $interval->days : (int) $interval->d;
	$time = '';
	foreach ( array( 'H' => (int) $interval->h, 'M' => (int) $interval->i, 'S' => (int) $interval->s ) as $unit => $value ) {
		if ( $value > 0 ) {
			$time .= $value . $unit;
		}
	}
	if ( 0 === $days && '' === $time ) {
		return 'PT0S';
	}
	return 'P' . ( $days > 0 ? $days . 'D' : '' ) . ( '' !== $time ? 'T' . $time : '' );
}

/**
 * A stored RRULE as a JSCalendar recurrence rule.
 *
 * Only the parts the writer accepts, because a rule this site cannot expand is one it must not
 * publish as though it could.
 *
 * @param string $rrule Stored RRULE.
 * @return array<string,mixed>|null
 */
function axismundi_cal_jscalendar_recurrence_rule( string $rrule ) : ?array {
	$rrule = trim( $rrule );
	if ( '' === $rrule ) {
		return null;
	}
	$parts = axismundi_cal_rrule_parse( $rrule );
	if ( is_wp_error( $parts ) || empty( $parts['FREQ'] ) ) {
		return null;
	}
	/*
	 * Parsing only splits the string; it is `rrule_check()` that says whether this site can expand the
	 * result. Publishing a rule the expander refuses would hand a consumer a series we do not show
	 * ourselves -- and they would compute occurrences this calendar denies having.
	 */
	if ( is_wp_error( axismundi_cal_rrule_check( $parts, false ) ) ) {
		return null;
	}
	// The parser keeps a comma list as it was written. JSCalendar wants the members.
	foreach ( array( 'BYDAY', 'BYMONTHDAY', 'BYMONTH' ) as $list ) {
		if ( isset( $parts[ $list ] ) && ! is_array( $parts[ $list ] ) ) {
			$parts[ $list ] = array_filter( array_map( 'trim', explode( ',', (string) $parts[ $list ] ) ) );
		}
	}
	$rule = array(
		'@type'     => 'RecurrenceRule',
		'frequency' => strtolower( (string) $parts['FREQ'] ),
	);
	if ( isset( $parts['INTERVAL'] ) && (int) $parts['INTERVAL'] > 1 ) {
		$rule['interval'] = (int) $parts['INTERVAL'];
	}
	if ( isset( $parts['COUNT'] ) ) {
		$rule['count'] = (int) $parts['COUNT'];
	}
	if ( isset( $parts['UNTIL'] ) ) {
		// Local date-time, as JSCalendar states it: the zone is the Event's own and is not repeated here.
		$rule['until'] = str_replace( ' ', 'T', substr( (string) $parts['UNTIL'], 0, 19 ) );
	}
	if ( ! empty( $parts['BYDAY'] ) ) {
		$rule['byDay'] = array_values(
			array_map(
				static function ( $day ) : array {
					$day  = (string) $day;
					$name = strtolower( substr( $day, -2 ) );
					$nth  = (int) substr( $day, 0, -2 );
					return 0 !== $nth ? array( '@type' => 'NDay', 'day' => $name, 'nthOfPeriod' => $nth ) : array( '@type' => 'NDay', 'day' => $name );
				},
				(array) $parts['BYDAY']
			)
		);
	}
	foreach ( array( 'BYMONTHDAY' => 'byMonthDay', 'BYMONTH' => 'byMonth' ) as $from => $to ) {
		if ( ! empty( $parts[ $from ] ) ) {
			$rule[ $to ] = 'byMonth' === $to
				? array_map( 'strval', (array) $parts[ $from ] )
				: array_map( 'intval', (array) $parts[ $from ] );
		}
	}
	if ( ! empty( $parts['WKST'] ) ) {
		$rule['firstDayOfWeek'] = strtolower( (string) $parts['WKST'] );
	}
	return $rule;
}

/**
 * Instances that differ from the rule.
 *
 * A cancelled instance is `excluded`, which is what JSCalendar calls a date the series no longer
 * happens on. A moved or edited one carries the fields that differ; the recurrence id keys it, and
 * the id is the civil start the rule would have produced -- not where the instance ended up.
 *
 * @param int    $schedule_id Schedule id.
 * @param string $timezone    Event zone.
 * @return array<string,mixed>
 */
function axismundi_cal_jscalendar_overrides( int $schedule_id, string $timezone ) : array {
	$out = array();
	foreach ( axismundi_cal_overrides( $schedule_id ) as $recurrence_id => $override ) {
		/*
		 * Stored the way iCalendar writes a RECURRENCE-ID -- `20270201T090000`, or a bare date for an
		 * all-day series. JSCalendar keys an override by a local date-time, so the separators go back in
		 * and a date is read as its midnight.
		 */
		$compact = (string) $recurrence_id;
		$date    = substr( $compact, 0, 4 ) . '-' . substr( $compact, 4, 2 ) . '-' . substr( $compact, 6, 2 );
		$key     = 8 === strlen( $compact )
			? $date . 'T00:00:00'
			: $date . 'T' . substr( $compact, 9, 2 ) . ':' . substr( $compact, 11, 2 ) . ':' . substr( $compact, 13, 2 );
		if ( 'cancelled' === (string) ( $override['status'] ?? '' ) ) {
			$out[ $key ] = array( 'excluded' => true );
			continue;
		}
		$patch = array();
		if ( '' !== (string) ( $override['start_local'] ?? '' ) ) {
			$patch['start'] = str_replace( ' ', 'T', substr( (string) $override['start_local'], 0, 19 ) );
		}
		if ( '' !== (string) ( $override['location_text'] ?? '' ) ) {
			$patch['locations/override/name'] = (string) $override['location_text'];
		}
		$out[ $key ] = array() === $patch ? new stdClass() : $patch;
	}
	return $out;
}

/**
 * Where it happens, in the two shapes JSCalendar keeps apart.
 *
 * Only what was announced. A joining link kept for the people coming is not published to anybody who
 * fetches the Event, which is the same rule the ICS document follows.
 *
 * @param int $post_id Event post ID.
 * @return array{locations:array<string,mixed>,virtualLocations:array<string,mixed>}
 */
function axismundi_cal_jscalendar_locations( int $post_id ) : array {
	$physical = array();
	$virtual  = array();
	foreach ( axismundi_cal_event_locations( $post_id ) as $index => $location ) {
		if ( 'public' !== (string) ( $location['access'] ?? 'public' ) ) {
			continue;
		}
		$key   = 'l' . ( (int) $index + 1 );
		$label = (string) ( $location['label'] ?? '' );
		if ( 'virtual' === (string) ( $location['kind'] ?? 'physical' ) ) {
			$url = (string) ( $location['url'] ?? '' );
			if ( '' === $url ) {
				continue;
			}
			$virtual[ $key ] = array_filter(
				array( '@type' => 'VirtualLocation', 'name' => $label, 'uri' => $url ),
				static fn( $value ) : bool => '' !== $value
			);
			continue;
		}
		if ( '' === $label ) {
			continue;
		}
		$physical[ $key ] = array( '@type' => 'Location', 'name' => $label );
	}
	return array( 'locations' => $physical, 'virtualLocations' => $virtual );
}

/**
 * One Event as a JSCalendar object.
 *
 * @param WP_Post $post Event post.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_cal_jscalendar_event( WP_Post $post ) {
	$schedule = axismundi_cal_schedule_for_event( (int) $post->ID );
	$envelope = axismundi_cal_event_get( (int) $post->ID );
	if ( ! is_array( $schedule ) || ! is_array( $envelope ) ) {
		return new WP_Error( 'ax_cal_jscalendar_missing', __( 'That Event has no schedule to describe.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	$timezone = (string) $schedule['timezone'];
	$all_day  = ! empty( $schedule['all_day'] );
	$duration = axismundi_cal_civil_interval( (string) $schedule['dtstart_local'], (string) $schedule['dtend_local'] );

	$event = array(
		'@type'   => 'Event',
		'uid'     => (string) $schedule['ical_uid'],
		'updated' => get_post_modified_time( 'Y-m-d\TH:i:s\Z', true, $post ),
		'title'   => wp_strip_all_tags( get_the_title( $post ) ),
		'start'   => str_replace( ' ', 'T', substr( (string) $schedule['dtstart_local'], 0, 19 ) ),
		'duration' => axismundi_cal_jscalendar_duration( $duration ),
		'status'  => axismundi_cal_jscalendar_status( (string) $envelope['event_status'] ),
		'freeBusyStatus' => 'TRANSPARENT' === (string) $envelope['transparency'] ? 'free' : 'busy',
		'privacy' => 'public',
		'sequence' => (int) $schedule['sequence'],
	);
	/*
	 * An all-day Event has no zone, which is the whole of what makes it all-day: the same civil date
	 * everywhere, rather than a moment that lands on different dates for different readers.
	 */
	if ( $all_day ) {
		$event['showWithoutTime'] = true;
	} else {
		$event['timeZone'] = $timezone;
	}

	$content = (string) apply_filters( 'the_content', $post->post_content );
	if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
		$event['description']            = $content;
		$event['descriptionContentType'] = 'text/html';
	}

	$rule = axismundi_cal_jscalendar_recurrence_rule( (string) $schedule['rrule'] );
	if ( null !== $rule ) {
		$event['recurrenceRules'] = array( $rule );
		$overrides                = axismundi_cal_jscalendar_overrides( (int) $schedule['id'], $timezone );
		if ( array() !== $overrides ) {
			$event['recurrenceOverrides'] = $overrides;
		}
	}

	$places = axismundi_cal_jscalendar_locations( (int) $post->ID );
	foreach ( $places as $key => $value ) {
		if ( array() !== $value ) {
			$event[ $key ] = $value;
		}
	}

	/*
	 * The Actor it was published as, in the role JSCalendar has for it. Not a new `author` field: the
	 * standard has no such property, `Create(Event)` already carries provenance, and a second way to
	 * say the same thing is a second thing to keep in agreement.
	 */
	$organizer = axismundi_cal_event_acting_actor_uri( (int) $post->ID );
	if ( '' !== $organizer ) {
		$event['participants'] = array(
			'organizer' => array(
				'@type'               => 'Participant',
				'calendarAddress'     => $organizer,
				'roles'               => array( 'owner' => true ),
				'participationStatus' => 'accepted',
				'expectReply'         => false,
			),
		);
	}
	$event['links'] = array(
		'html' => array( '@type' => 'Link', 'href' => (string) get_permalink( $post ), 'contentType' => 'text/html' ),
	);
	return $event;
}

/** @return array<string,string> */
function axismundi_cal_jscalendar_rewrite_rules() : array {
	return array( '^event/([^/]+)\.jscalendar$' => 'index.php?ax_cal_jscalendar=$matches[1]' );
}

/** @return void */
function axismundi_cal_register_jscalendar_routes() : void {
	foreach ( axismundi_cal_jscalendar_rewrite_rules() as $regex => $query ) {
		add_rewrite_rule( $regex, $query, 'top' );
	}
}
add_action( 'init', 'axismundi_cal_register_jscalendar_routes', 7 );

/**
 * @param string[] $vars Query vars.
 * @return string[]
 */
function axismundi_cal_jscalendar_query_vars( array $vars ) : array {
	$vars[] = 'ax_cal_jscalendar';
	return $vars;
}
add_filter( 'query_vars', 'axismundi_cal_jscalendar_query_vars' );

/**
 * Serve one Event as JSCalendar.
 *
 * @return void
 */
function axismundi_cal_serve_jscalendar() : void {
	$slug = sanitize_title( (string) get_query_var( 'ax_cal_jscalendar' ) );
	if ( '' === $slug ) {
		return;
	}
	$post = get_page_by_path( $slug, OBJECT, AXISMUNDI_CAL_EVENT_POST_TYPE );
	/*
	 * The same answer a stranger gets from the feed and the collection. An Event withheld from those
	 * is withheld here, and a missing one and a private one are the same 404 -- the difference is
	 * exactly what a private Event would rather not disclose.
	 */
	if ( ! $post instanceof WP_Post || ! axismundi_cal_event_listable( $post ) ) {
		status_header( 404 );
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo wp_json_encode( array( 'error' => 'not_found' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response.
		exit;
	}
	$event = axismundi_cal_jscalendar_event( $post );
	if ( is_wp_error( $event ) ) {
		status_header( 404 );
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo wp_json_encode( array( 'error' => $event->get_error_code() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response.
		exit;
	}
	status_header( 200 );
	header( 'Content-Type: ' . AXISMUNDI_CAL_JSCALENDAR_MEDIA_TYPE . '; charset=' . get_option( 'blog_charset' ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Access-Control-Allow-Origin: *' );
	echo wp_json_encode( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response.
	exit;
}
add_action( 'template_redirect', 'axismundi_cal_serve_jscalendar', 4 );
