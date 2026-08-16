<?php
/**
 * One Event as JSCalendar.
 *
 * The canonical target is **JSCalendar 2.0** (draft-ietf-calext-jscalendarbis), not RFC 8984. That is
 * a deliberate choice made while this is pre-release: 2.0 is where `endTimeZone` lives, and an Event
 * that ends in another zone is a fact the model now holds -- so pinning the wire to 1.0 would mean
 * either dropping it or lying about it, and both are worse than tracking a draft. Revision drift is
 * absorbed here, by an adapter with audits, which is what an adapter is for.
 *
 * A read projection, not a second home for the data. The domain model stores what JSCalendar names --
 * a civil start, a zone, a length, an arrival zone, a recurrence and its exceptions -- and this says
 * the same things in that vocabulary.
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

/** The registered media type, with the object type it carries. */
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
 * the clocks change is not 24 hours, and that difference is the whole reason a same-zone Event
 * carries a civil length instead of an elapsed one.
 *
 * @param DateInterval $interval Interval.
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
 * How long an Event really runs, for one that ends in another zone.
 *
 * The two civil values are read in their own zones and the difference between the instants is the
 * answer -- there is no civil length across a zone boundary to carry instead.
 *
 * @param array<string,mixed> $schedule Schedule row.
 * @return DateInterval
 */
function axismundi_cal_elapsed_interval( array $schedule ) : DateInterval {
	try {
		$start = new DateTimeImmutable( (string) $schedule['dtstart_local'], new DateTimeZone( (string) $schedule['timezone'] ) );
		$end   = new DateTimeImmutable( (string) $schedule['dtend_local'], new DateTimeZone( (string) $schedule['end_timezone'] ) );
	} catch ( Exception $error ) {
		return new DateInterval( 'PT0S' );
	}
	$seconds = max( 0, $end->getTimestamp() - $start->getTimestamp() );
	// Stated in the units a person reads. `PT54000S` is the same fifteen hours and nobody recognises it.
	$spec = 'PT' . intdiv( $seconds, 3600 ) . 'H' . intdiv( $seconds % 3600, 60 ) . 'M' . ( $seconds % 60 ) . 'S';
	try {
		return new DateInterval( $spec );
	} catch ( Exception $error ) {
		return new DateInterval( 'PT0S' );
	}
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
 * A stored RSVP state as JSCalendar says it.
 *
 * `pending` is `needs-action` rather than absent: JSCalendar distinguishes somebody who was asked and
 * has not replied from somebody who is not on the list, and only the organizer and the person
 * themselves ever see that row anyway.
 *
 * Empty for the states that are not a standing relation at all. A withdrawn request and a removal are
 * both the end of one, and JSCalendar has no status for that -- writing either as `declined` would
 * publish a refusal nobody made, which is exactly the thing an RSVP document must never do. The
 * participant is left out instead, which is what "no longer on the list" looks like.
 *
 * @param string $state Stored participation state.
 * @return string
 */
function axismundi_cal_jscalendar_participation_status( string $state ) : string {
	switch ( $state ) {
		case 'accepted':
			return 'accepted';
		case 'tentative':
			return 'tentative';
		case 'rejected':
		case 'tentative_rejected':
			return 'declined';
		case 'pending':
			return 'needs-action';
		default:
			return '';
	}
}

/**
 * The people on an Event, as one viewer may see them.
 *
 * The organizer is always here -- the Event says who is running it, which is not a fact about who
 * replied. Everybody else arrives through `visible_participants()` and nothing decides anything a
 * second time: this surface must not grow its own opinion about who may be seen, or the answer will
 * eventually differ from the collection's for the same reader.
 *
 * @param int         $post_id Event post ID.
 * @param string|null $viewer  Viewing Actor URI, or null for an anonymous reader.
 * @return array<string,array<string,mixed>>
 */
function axismundi_cal_jscalendar_participants( int $post_id, ?string $viewer ) : array {
	$participants = array();
	$organizer    = axismundi_cal_event_acting_actor_uri( $post_id );
	if ( '' !== $organizer ) {
		/*
		 * The Actor it was published as, in the role JSCalendar has for it. Not a new `author` field: the
		 * standard has no such property, `Create(Event)` already carries provenance, and a second way to
		 * say the same thing is a second thing to keep in agreement.
		 */
		$participants['organizer'] = array(
			'@type'               => 'Participant',
			'calendarAddress'     => $organizer,
			'roles'               => array( 'owner' => true ),
			'participationStatus' => 'accepted',
			'expectReply'         => false,
		);
	}
	foreach ( axismundi_cal_visible_participants( $post_id, $viewer ) as $participation ) {
		$address = (string) $participation['actor_uri'];
		$status  = axismundi_cal_jscalendar_participation_status( (string) $participation['state'] );
		if ( '' === $address || '' === $status || $address === $organizer ) {
			continue;
		}
		// Keyed by a hash of the address rather than by position, so a participant keeps their key
		// across fetches even as other replies arrive and the ledger reorders.
		$participants[ substr( hash( 'sha256', $address ), 0, 16 ) ] = array(
			'@type'               => 'Participant',
			'calendarAddress'     => $address,
			'roles'               => array( 'attendee' => true ),
			'participationStatus' => $status,
		);
	}
	return $participants;
}

/**
 * One Event as a JSCalendar object.
 *
 * @param WP_Post     $post   Event post.
 * @param string|null $viewer Viewing Actor URI, or null for an anonymous reader. Required rather
 *                            than defaulted: a document that decided for itself who was reading it
 *                            would be cached and served to somebody else.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_cal_jscalendar_event( WP_Post $post, ?string $viewer ) {
	$schedule = axismundi_cal_schedule_for_event( (int) $post->ID );
	$envelope = axismundi_cal_event_get( (int) $post->ID );
	if ( ! is_array( $schedule ) || ! is_array( $envelope ) ) {
		return new WP_Error( 'ax_cal_jscalendar_missing', __( 'That Event has no schedule to describe.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	$timezone = (string) $schedule['timezone'];
	$all_day  = ! empty( $schedule['all_day'] );
	$end_zone = trim( (string) ( $schedule['end_timezone'] ?? '' ) );
	/*
	 * `duration` is what decides the end instant, so it has to be measured the way the Event actually
	 * runs. Within one zone that is the civil length -- 19:00-21:00 is two hours on the night the
	 * clocks change -- but an Event ending in another zone has no civil length: 10:00 in Seoul to 11:00
	 * in New York is one hour on the page and fifteen in the air, and publishing the first would put
	 * the landing before the take-off.
	 */
	$duration = axismundi_cal_schedule_duration( $schedule );

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
		if ( '' !== $end_zone ) {
			// JSCalendar 2.0 states the arrival zone beside the departure one, so the end can be shown on
			// the clock it happens on without the duration having to lie about how long it took.
			$event['endTimeZone'] = $end_zone;
		}
	}

	$content = (string) apply_filters( 'the_content', $post->post_content );
	if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
		$event['description']            = $content;
		$event['descriptionContentType'] = 'text/html';
	}

	// Already in this vocabulary: the stored structure is the JSCalendar rule, so nothing is converted.
	$rules = axismundi_cal_schedule_recurrence( $schedule );
	if ( array() !== $rules ) {
		$event['recurrenceRules'] = $rules;
	}
	/*
	 * Asked whether or not there is a rule. An added date needs no rule to exist -- JSCalendar states
	 * one as an override at a key the rule never produced -- and gating this on `recurrenceRules`
	 * meant an Event whose only recurrence was a hand-added date published none of it, while every
	 * local surface went on showing it.
	 */
	$overrides = axismundi_cal_jscalendar_overrides( (int) $schedule['id'], $timezone );
	if ( array() !== $overrides ) {
		$event['recurrenceOverrides'] = $overrides;
	}

	$places = axismundi_cal_jscalendar_locations( (int) $post->ID );
	foreach ( $places as $key => $value ) {
		if ( array() !== $value ) {
			$event[ $key ] = $value;
		}
	}

	$participants = axismundi_cal_jscalendar_participants( (int) $post->ID, $viewer );
	if ( array() !== $participants ) {
		$event['participants'] = $participants;
	}
	$event['links'] = array(
		'html' => array( '@type' => 'Link', 'href' => (string) get_permalink( $post ), 'contentType' => 'text/html' ),
	);
	return $event;
}

/**
 * The JSCalendar version a request is asking for, or '' when it did not say.
 *
 * Read from the `version` parameter on an `application/jscalendar+json` Accept, which is how a
 * client says which vocabulary it can actually read. Saying nothing means "whatever you speak", and
 * that is the common case -- a browser, a curl, anything that has not thought about it.
 *
 * @return string
 */
function axismundi_cal_requested_jscalendar_version() : string {
	$accept = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ?? '' ) );
	if ( '' === $accept || false === stripos( $accept, 'application/jscalendar+json' ) ) {
		return '';
	}
	foreach ( explode( ',', $accept ) as $candidate ) {
		if ( false === stripos( $candidate, 'application/jscalendar+json' ) ) {
			continue;
		}
		if ( 1 === preg_match( '/version\s*=\s*"?([0-9.]+)"?/i', $candidate, $matches ) ) {
			return (string) $matches[1];
		}
	}
	return '';
}

/**
 * The 2.0-only properties one document uses.
 *
 * Named rather than counted, so a refusal can say which fact it could not express instead of leaving
 * somebody to guess what about their Event was too new.
 *
 * @param array<string,mixed> $event JSCalendar Event.
 * @return string[]
 */
function axismundi_cal_jscalendar_v2_properties( array $event ) : array {
	$found = array();
	foreach ( array( 'endTimeZone' ) as $property ) {
		if ( isset( $event[ $property ] ) ) {
			$found[] = $property;
		}
	}
	return $found;
}

/**
 * Refuse, rather than quietly hand back less than was asked about.
 *
 * A client that names `version=1.0` has said which vocabulary it can read, and an Event carrying
 * `endTimeZone` cannot be said in it. Dropping the property would not move the instant -- the
 * duration is the real elapsed time, so 1.0 arithmetic still lands on the right moment -- but the
 * arrival would be shown on the departure clock, and anything that read the document and wrote it
 * back would erase the arrival zone entirely. A 406 says that plainly; a downgraded document says
 * nothing at all.
 *
 * Events with nothing 2.0-only in them are served to a 1.0 client unchanged, because they are
 * already valid 1.0.
 *
 * @param array<string,mixed> $event   JSCalendar Event.
 * @param string              $version Requested version, or ''.
 * @return string[] The properties that cannot be expressed, empty when the request can be answered.
 */
function axismundi_cal_jscalendar_unrepresentable( array $event, string $version ) : array {
	if ( '' === $version || version_compare( $version, '2.0', '>=' ) ) {
		return array();
	}
	return axismundi_cal_jscalendar_v2_properties( $event );
}

/**
 * Say we could not answer in the vocabulary that was asked for.
 *
 * @param string[] $properties Properties that could not be expressed.
 * @return void
 */
function axismundi_cal_emit_jscalendar_version_refusal( array $properties ) : void {
	status_header( 406 );
	header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
	header( 'Vary: Accept', false );
	echo wp_json_encode( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response.
		array(
			'error'      => 'jscalendar_version',
			'available'  => '2.0',
			'properties' => array_values( $properties ),
		)
	);
	exit;
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
	/*
	 * Anonymous, by contract. This document is served to whoever fetches the URL and is cacheable by
	 * anything in between, so it is built for a reader with no relation to the Event -- a signed-in
	 * organizer looking at their own Event through this route sees what a stranger sees, and the
	 * surfaces that do know who is asking are the ones that answer for them.
	 */
	$event   = axismundi_cal_jscalendar_event( $post, null );
	$version = axismundi_cal_requested_jscalendar_version();
	if ( is_array( $event ) ) {
		$missing = axismundi_cal_jscalendar_unrepresentable( $event, $version );
		if ( array() !== $missing ) {
			axismundi_cal_emit_jscalendar_version_refusal( $missing );
		}
	}
	if ( is_wp_error( $event ) ) {
		status_header( 404 );
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		echo wp_json_encode( array( 'error' => $event->get_error_code() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response.
		exit;
	}
	status_header( 200 );
	// The version is stated rather than assumed, so a reader knows which vocabulary it just received.
	header( 'Content-Type: ' . AXISMUNDI_CAL_JSCALENDAR_MEDIA_TYPE . '; version=2.0; charset=' . get_option( 'blog_charset' ) );
	header( 'Vary: Accept', false );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Access-Control-Allow-Origin: *' );
	echo wp_json_encode( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response.
	exit;
}
add_action( 'template_redirect', 'axismundi_cal_serve_jscalendar', 4 );
