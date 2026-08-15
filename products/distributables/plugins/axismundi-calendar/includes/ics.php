<?php
/**
 * iCalendar serialization.
 *
 * A recurring Event is written as one `VEVENT` carrying its `RRULE`, plus one component per
 * authored exception -- not as an expanded list of occurrences. Expansion is the consumer's job and
 * every calendar client already does it, while a feed that expanded would be unbounded: an annual
 * birthday has no last occurrence, so "every occurrence" is not a finite document. This is the same
 * reason Calendar membership is a series rather than its instances.
 *
 * `ATTENDEE` and `ORGANIZER` are never written here. iTIP puts them in the payload because it
 * assumes point-to-point delivery to the invitees themselves; a subscription feed is a broadcast to
 * anyone holding the URL, so the same lines would publish the guest list and turn a public address
 * into an email-harvesting endpoint. They belong only on iTIP REQUEST and REPLY payloads.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** Line ending required by RFC 5545. A bare LF is rejected or silently mis-parsed by some clients. */
const AXISMUNDI_CAL_ICS_EOL = "\r\n";

/**
 * Escape a text value.
 *
 * Backslash first: escaping it after the others would escape the backslashes they just introduced.
 *
 * @param string $value Raw value.
 * @return string
 */
function axismundi_cal_ics_escape( string $value ) : string {
	$value = str_replace( '\\', '\\\\', $value );
	$value = str_replace( array( ';', ',' ), array( '\\;', '\\,' ), $value );
	return str_replace( array( "\r\n", "\r", "\n" ), '\\n', $value );
}

/**
 * Fold one content line to 75 octets.
 *
 * Octets rather than characters, because the limit in RFC 5545 is on octets and splitting a
 * multi-byte character across a fold produces a line no parser can reassemble -- which is how a
 * calendar with Korean or emoji in a title breaks while an English one looks fine.
 *
 * @param string $line Content line.
 * @return string
 */
function axismundi_cal_ics_fold( string $line ) : string {
	if ( strlen( $line ) <= 75 ) {
		return $line;
	}
	$out       = '';
	$current   = '';
	$length    = mb_strlen( $line, 'UTF-8' );
	$limit     = 75;
	for ( $i = 0; $i < $length; $i++ ) {
		$char = mb_substr( $line, $i, 1, 'UTF-8' );
		if ( strlen( $current ) + strlen( $char ) > $limit ) {
			$out    .= $current . AXISMUNDI_CAL_ICS_EOL . ' ';
			$current = '';
			// Continuation lines carry a leading space, which counts toward the octet budget.
			$limit   = 74;
		}
		$current .= $char;
	}
	return $out . $current;
}

/**
 * Join content lines into a document body.
 *
 * @param string[] $lines Content lines.
 * @return string
 */
function axismundi_cal_ics_join( array $lines ) : string {
	$folded = array_map( 'axismundi_cal_ics_fold', $lines );
	return implode( AXISMUNDI_CAL_ICS_EOL, $folded ) . AXISMUNDI_CAL_ICS_EOL;
}

/**
 * A UTC timestamp in iCalendar form.
 *
 * @param string $utc `Y-m-d H:i:s` UTC.
 * @return string
 */
function axismundi_cal_ics_utc( string $utc ) : string {
	$time = strtotime( $utc . ' UTC' );
	return false === $time ? gmdate( 'Ymd\THis\Z' ) : gmdate( 'Ymd\THis\Z', $time );
}

/**
 * A local wall time in iCalendar form.
 *
 * @param string $local   `Y-m-d H:i:s` local.
 * @param bool   $all_day Whether to write a date rather than a date-time.
 * @return string
 */
function axismundi_cal_ics_local( string $local, bool $all_day ) : string {
	$time = strtotime( $local );
	if ( false === $time ) {
		return '';
	}
	return $all_day ? gmdate( 'Ymd', $time ) : gmdate( 'Ymd\THis', $time );
}

/**
 * A bounded `VTIMEZONE` for one zone.
 *
 * Derived from the transitions PHP already ships with rather than assembled from a guessed offset:
 * a hand-written observance is wrong twice a year, and wrong in a way that only shows after the
 * clocks change. Bounded to the range in use, because a zone's full transition history runs to
 * hundreds of components that no client needs.
 *
 * @param string $tzid   IANA zone name.
 * @param int    $from   Range start, timestamp.
 * @param int    $to     Range end, timestamp.
 * @return string[] Content lines, or an empty array when the zone has none.
 */
function axismundi_cal_ics_vtimezone( string $tzid, int $from, int $to ) : array {
	try {
		$zone = new DateTimeZone( $tzid );
	} catch ( Exception $error ) {
		return array();
	}
	// One year of lead-in so the first observance describes the offset already in force when the
	// range opens, rather than leaving the first events without one.
	$transitions = $zone->getTransitions( $from - YEAR_IN_SECONDS, $to );
	if ( ! is_array( $transitions ) || count( $transitions ) < 1 ) {
		return array();
	}

	$lines    = array( 'BEGIN:VTIMEZONE', 'TZID:' . $tzid );
	$previous = null;
	foreach ( $transitions as $index => $transition ) {
		$offset = (int) $transition['offset'];
		if ( null === $previous ) {
			$previous = $offset;
			// The first entry describes the state at the start of the window, not a change, so it
			// is used only to establish the offset the next observance moves away from.
			if ( count( $transitions ) > 1 ) {
				continue;
			}
		}
		$component = ! empty( $transition['isdst'] ) ? 'DAYLIGHT' : 'STANDARD';
		$start     = (int) $transition['ts'];
		$lines[]   = 'BEGIN:' . $component;
		$lines[]   = 'DTSTART:' . gmdate( 'Ymd\THis', $start + $previous );
		$lines[]   = 'TZOFFSETFROM:' . axismundi_cal_ics_offset( $previous );
		$lines[]   = 'TZOFFSETTO:' . axismundi_cal_ics_offset( $offset );
		if ( ! empty( $transition['abbr'] ) ) {
			$lines[] = 'TZNAME:' . axismundi_cal_ics_escape( (string) $transition['abbr'] );
		}
		$lines[]  = 'END:' . $component;
		$previous = $offset;
		unset( $index );
	}
	$lines[] = 'END:VTIMEZONE';
	return count( $lines ) > 3 ? $lines : array();
}

/**
 * A UTC offset in `+hhmm` form.
 *
 * @param int $seconds Offset in seconds.
 * @return string
 */
function axismundi_cal_ics_offset( int $seconds ) : string {
	$sign    = $seconds < 0 ? '-' : '+';
	$seconds = abs( $seconds );
	return sprintf( '%s%02d%02d', $sign, intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ) );
}

/**
 * One Event as `VEVENT` components: the series, then its authored exceptions.
 *
 * @param array<string,mixed> $schedule Schedule row.
 * @param WP_Post             $post     Event post.
 * @return string[] Content lines.
 */
function axismundi_cal_ics_vevent( array $schedule, WP_Post $post ) : array {
	$all_day  = ! empty( $schedule['all_day'] );
	$tzid     = (string) $schedule['timezone'];
	$uid      = (string) $schedule['ical_uid'];
	$stamp    = axismundi_cal_ics_utc( (string) $schedule['updated_at'] );
	$envelope = axismundi_cal_event_get( (int) $post->ID );

	$suffix = $all_day ? ';VALUE=DATE' : ( '' !== $tzid ? ';TZID=' . $tzid : '' );

	$lines   = array( 'BEGIN:VEVENT' );
	$lines[] = 'UID:' . axismundi_cal_ics_escape( $uid );
	$lines[] = 'DTSTAMP:' . $stamp;
	$lines[] = 'SEQUENCE:' . (int) $schedule['sequence'];
	$lines[] = 'LAST-MODIFIED:' . $stamp;
	$lines[] = 'SUMMARY:' . axismundi_cal_ics_escape( wp_strip_all_tags( get_the_title( $post ) ) );
	$lines[] = 'DTSTART' . $suffix . ':' . axismundi_cal_ics_local( (string) $schedule['dtstart_local'], $all_day );
	/*
	 * The end carries its own zone when it has one. iCalendar states DTSTART and DTEND independently,
	 * so an arrival in another zone is expressible here without any extension -- and writing it in the
	 * departure zone would move the landing time by the offset between them.
	 */
	$end_zone   = trim( (string) ( $schedule['end_timezone'] ?? '' ) );
	$end_suffix = $all_day || '' === $end_zone ? $suffix : ';TZID=' . $end_zone;
	$lines[] = 'DTEND' . $end_suffix . ':' . axismundi_cal_ics_local( (string) $schedule['dtend_local'], $all_day );
	$lines[] = 'URL:' . axismundi_cal_ics_escape( (string) get_permalink( $post ) );
	/*
	 * Whether holding this should make somebody look occupied. A calendar entry ordinarily does, so
	 * `OPAQUE` is the default and an open house says otherwise explicitly.
	 */
	$lines[] = 'TRANSP:' . ( 'TRANSPARENT' === strtoupper( (string) ( $envelope['transparency'] ?? '' ) ) ? 'TRANSPARENT' : 'OPAQUE' );

	/*
	 * The written excerpt, or a plain-text projection of the body. Stated here rather than taken from
	 * `get_the_excerpt()`, which runs filters and manufactures a summary of its own: what reached
	 * subscribers would then depend on which plugins were installed and on whether the body happened
	 * to contain a `<!--more-->`, and a subscription document is kept rather than re-read.
	 *
	 * `DESCRIPTION` is `TEXT` in RFC 5545, so markup does not belong in it whichever source it came
	 * from. The body's own images are page content and are not smuggled in here either; the lead image
	 * has its own property below.
	 */
	$description = trim( (string) $post->post_excerpt );
	if ( '' === $description ) {
		$description = trim( preg_replace( '/\s+/u', ' ', (string) wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) ) );
	}
	if ( '' !== $description ) {
		$lines[] = 'DESCRIPTION:' . axismundi_cal_ics_escape( $description );
	}

	/*
	 * The lead image, as RFC 7986 defines it. A client that does not know `IMAGE` ignores the line,
	 * which is the right outcome -- unlike a URL pasted into the description, which every client shows
	 * to everybody forever.
	 */
	$thumbnail = (string) wp_get_attachment_url( (int) get_post_thumbnail_id( $post ) );
	if ( '' !== $thumbnail ) {
		$lines[] = 'IMAGE;VALUE=URI:' . axismundi_cal_ics_escape( $thumbnail );
	}
	/*
	 * One place, because `LOCATION` is a single TEXT property. Several physical locations are reduced
	 * to the first rather than repeated: a VEVENT with two `LOCATION` lines is malformed, and clients
	 * split between rejecting it and silently keeping whichever came last.
	 *
	 * Read from the schedule, which the writer keeps as a copy of the first physical location -- so a
	 * per-occurrence override, which replaces exactly this line for one instance, goes on working.
	 */
	/*
	 * Resolved rather than stored: the per-occurrence override if this instance has one, otherwise the
	 * first physical place this document may name. Nothing copies the list onto the schedule, so the
	 * two cannot drift apart -- `location_text` on a schedule row means "this instance differs", and
	 * an empty one means it does not.
	 */
	$primary  = axismundi_cal_event_primary_place( (int) $post->ID );
	$location = trim( (string) $schedule['location_text'] );
	if ( '' === $location && is_array( $primary ) ) {
		$location = axismundi_cal_event_place_text( $primary );
	}
	if ( '' !== $location ) {
		$lines[] = 'LOCATION:' . axismundi_cal_ics_escape( $location );
	}

	/*
	 * The opposite shape: `CONFERENCE` exists to carry remote participation and takes as many URIs as
	 * there are. Only the ways of taking part go here -- a livestream is watched rather than joined,
	 * and publishing one as `CONFERENCE` would tell a client that opening it puts somebody in the room.
	 */
	foreach ( axismundi_cal_event_locations( (int) $post->ID ) as $virtual ) {
		if ( 'virtual' !== (string) $virtual['kind'] ) {
			continue;
		}
		/*
		 * Only what was meant to be announced. A public Event with a private joining link is ordinary --
		 * the address is for everybody and the meeting URL is for the people coming -- and this document
		 * is handed to anybody who has the calendar. An invited attendee's copy is a different document
		 * with a different writer, the way the dataset feed already is.
		 */
		if ( 'public' !== (string) $virtual['access'] ) {
			continue;
		}
		/*
		 * A parameter value carrying a space, a colon or a comma has to be quoted, which "Google Meet"
		 * does. Unquoted it ends the parameter at the space and the rest of the line becomes something
		 * no parser agrees about. Quotes cannot themselves appear inside, so they are dropped rather
		 * than escaped -- RFC 5545 gives them no escape.
		 */
		/*
		 * Quoted, and the quotes taken out of the value first. RFC 5545 gives DQUOTE no escape inside a
		 * quoted parameter, so a link somebody called `"Main"; Hall` cannot be represented with them --
		 * and left in, it closes the quote early and the semicolon then reads as the start of another
		 * parameter. Everything else a name might contain is safe once the whole value is quoted.
		 */
		$label    = trim( str_replace( '"', '', (string) $virtual['label'] ) );
		$features = trim( (string) $virtual['features'] );
		$lines[]  = 'CONFERENCE;VALUE=URI'
			. ( '' !== $features ? ';FEATURE=' . $features : '' )
			. ( '' !== $label ? ';LABEL="' . $label . '"' : '' )
			. ':' . axismundi_cal_ics_escape( (string) $virtual['url'] );
	}
	if ( '' !== trim( (string) $schedule['rrule'] ) ) {
		$lines[] = 'RRULE:' . (string) $schedule['rrule'];
	}
	if ( is_array( $envelope ) && 'EventCancelled' === (string) ( $envelope['event_status'] ?? '' ) ) {
		// The whole series is off. A cancelled Event stays in the feed rather than disappearing, so
		// a subscriber who already holds it is told rather than left with a stale entry.
		$lines[] = 'STATUS:CANCELLED';
	}

	// EXDATE from the cancelled instances, which is where cancellation actually lives; there is no
	// second column recording the same fact and free to disagree with it.
	$exdates = array();
	$rdates  = array();
	$changed = array();
	foreach ( axismundi_cal_overrides( (int) $schedule['id'] ) as $recurrence_id => $override ) {
		if ( 'cancelled' === (string) $override['status'] ) {
			$exdates[] = (string) $recurrence_id;
			continue;
		}
		if ( 'rdate' === (string) $override['origin'] ) {
			/*
			 * A date somebody added by hand, which no rule produces. It was expanded on every local
			 * surface and published on none: without this line a subscriber's calendar is missing an
			 * occurrence this one shows, and nothing anywhere says so.
			 */
			$rdates[] = (string) $recurrence_id;
			continue;
		}
		if ( 'override' === (string) $override['origin'] ) {
			$changed[ (string) $recurrence_id ] = $override;
		}
	}
	if ( ! empty( $exdates ) ) {
		sort( $exdates );
		$lines[] = 'EXDATE' . $suffix . ':' . implode( ',', $exdates );
	}
	if ( ! empty( $rdates ) ) {
		sort( $rdates );
		$lines[] = 'RDATE' . $suffix . ':' . implode( ',', $rdates );
	}
	$lines[] = 'END:VEVENT';

	// A moved or otherwise altered instance is its own component identified by RECURRENCE-ID, which
	// is the local identity of the instance it replaces -- not the time it moved to.
	foreach ( $changed as $recurrence_id => $override ) {
		$lines[] = 'BEGIN:VEVENT';
		$lines[] = 'UID:' . axismundi_cal_ics_escape( $uid );
		$lines[] = 'RECURRENCE-ID' . $suffix . ':' . $recurrence_id;
		$lines[] = 'DTSTAMP:' . $stamp;
		$lines[] = 'SEQUENCE:' . (int) $schedule['sequence'];
		$lines[] = 'SUMMARY:' . axismundi_cal_ics_escape( wp_strip_all_tags( get_the_title( $post ) ) );
		$lines[] = 'DTSTART' . $suffix . ':' . axismundi_cal_ics_local( (string) $override['start_local'], $all_day );
		$lines[] = 'DTEND' . $suffix . ':' . axismundi_cal_ics_local( (string) $override['end_local'], $all_day );
		$override_location = trim( (string) $override['location_text'] );
		if ( '' !== $override_location ) {
			$lines[] = 'LOCATION:' . axismundi_cal_ics_escape( $override_location );
		}
		$lines[] = 'END:VEVENT';
	}

	return $lines;
}

/**
 * Wrap components in a `VCALENDAR`.
 *
 * @param string[] $components Component lines.
 * @param string[] $tzids      Zones referenced by those components.
 * @param int      $from       Range start, timestamp.
 * @param int      $to         Range end, timestamp.
 * @param string   $name       Calendar display name.
 * @param string   $display_tz Zone the calendar is laid out in, or ''.
 * @return string
 */
function axismundi_cal_ics_document( array $components, array $tzids, int $from, int $to, string $name, string $display_tz = '' ) : string {
	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//Axismundi//Calendar ' . AXISMUNDI_CAL_VERSION . '//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'X-WR-CALNAME:' . axismundi_cal_ics_escape( $name ),
	);
	/*
	 * The calendar's own zone, which is what this property means: where the calendar belongs, not
	 * how any reader should be shown it. Each event still carries the zone it happens in on its own
	 * DTSTART, and a subscriber's client converts to whatever clock its user keeps.
	 */
	if ( '' !== $display_tz ) {
		$lines[] = 'X-WR-TIMEZONE:' . axismundi_cal_ics_escape( $display_tz );
	}
	foreach ( array_unique( array_filter( $tzids ) ) as $tzid ) {
		$lines = array_merge( $lines, axismundi_cal_ics_vtimezone( (string) $tzid, $from, $to ) );
	}
	$lines   = array_merge( $lines, $components );
	$lines[] = 'END:VCALENDAR';
	return axismundi_cal_ics_join( $lines );
}
