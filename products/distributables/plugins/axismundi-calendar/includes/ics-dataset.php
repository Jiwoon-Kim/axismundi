<?php
/**
 * The subscription document for a maintained dataset.
 *
 * Deliberately not the Event writer. The two produce the same file format and describe nothing like
 * the same thing: an Event has an organizer, attendees, a permalink, a cancellation state and a
 * recurrence rule with authored exceptions, and a dataset entry has a date and a name. Sharing the
 * component writer would mean every property added for Events arrives here too, and the properties
 * Events will want next are the participation ones -- `ORGANIZER`, `ATTENDEE`, `mailto:` addresses.
 * A public holiday feed that names people is a privacy failure, and it would arrive silently, as a
 * side effect of unrelated work.
 *
 * The line primitives are shared, because escaping, folding and date formatting are RFC 5545 rather
 * than anything about Events, and a second implementation of the 75-octet fold is a second place for
 * a Korean holiday name to be cut mid-character.
 *
 * One feed per Calendar, which means one per language. 대한민국의 휴일 and Holidays in South Korea
 * are two subscriptions, and somebody who takes both gets both -- the same choice Google's holiday
 * feeds offer, and the reason the UID is qualified by the Calendar rather than by the day alone.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * How far ahead a dataset feed carries.
 *
 * Holidays are reviewed and published a year at a time, so a window that stopped at the end of the
 * current year would empty out every December for everyone who had already subscribed.
 */
const AXISMUNDI_CAL_DATASET_FEED_YEARS = 2;

/**
 * The stable iCalendar UID for one dataset entry.
 *
 * Two facts are wanted at once, and only one of them is the row. Re-importing a year rewrites rows
 * in place but must not hand every subscriber a calendar of duplicates, so the day is identified by
 * the occurrence it was linked to -- that is the thing that survives a re-import and a correction of
 * the name. A row nobody has linked yet is its own identity, which is honest: it is a day this site
 * has not related to anything.
 *
 * Qualified by the Calendar, and that is the whole reason the two halves are separate here. The
 * Korean and English editions of one holiday point at the same occurrence, so an occurrence-only UID
 * would make a client that holds both feeds collapse them into one entry and pick a language by
 * accident. Subscribing to both is a choice somebody made, and it should give them both.
 *
 * @param array<string,mixed> $item     System item row.
 * @param array<string,mixed> $calendar Calendar row.
 * @return string
 */
function axismundi_cal_dataset_ics_uid( array $item, array $calendar ) : string {
	$host       = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	$occurrence = (int) ( $item['holiday_occurrence_id'] ?? 0 );
	$local      = $occurrence > 0 ? 'day-' . $occurrence : 'entry-' . (int) $item['id'];
	return sprintf( 'ax-dataset-%s.%s@%s', $local, (string) $calendar['uuid'], '' !== $host ? $host : 'localhost' );
}

/**
 * One dataset entry as a `VEVENT`.
 *
 * The two temporal shapes are written as the two things RFC 5545 has for them, and nothing converts
 * between them. A whole day is `VALUE=DATE`, with no zone anywhere near it: a client that received a
 * holiday as a zoned midnight would show 광복절 on the 14th to every subscriber west of Greenwich.
 * A moment is a UTC date-time and stays one, because the day it falls on is the reader's client to
 * decide and this document is served to all of them alike.
 *
 * No `URL`. A dataset entry has no page of its own -- it is not a post, and pointing at the Calendar
 * page would give every one of a year's holidays the same link.
 *
 * @param array<string,mixed> $item     System item row.
 * @param array<string,mixed> $calendar Calendar row.
 * @return string[] Content lines.
 */
function axismundi_cal_dataset_ics_vevent( array $item, array $calendar ) : array {
	$stamp   = axismundi_cal_ics_utc( (string) ( $item['updated_at'] ?? gmdate( 'Y-m-d H:i:s' ) ) );
	$lines   = array( 'BEGIN:VEVENT' );
	$lines[] = 'UID:' . axismundi_cal_ics_escape( axismundi_cal_dataset_ics_uid( $item, $calendar ) );
	$lines[] = 'DTSTAMP:' . $stamp;
	$lines[] = 'LAST-MODIFIED:' . $stamp;
	$lines[] = 'SUMMARY:' . axismundi_cal_ics_escape( wp_strip_all_tags( axismundi_cal_item_display_name( $item ) ) );

	if ( AXISMUNDI_CAL_TEMPORAL_INSTANT === (string) ( $item['temporal_kind'] ?? AXISMUNDI_CAL_TEMPORAL_ALL_DAY ) ) {
		$start_utc = (string) $item['start_utc'];
		/*
		 * A point in time, given a zero-length span rather than an invented duration. A phase does not
		 * last an hour, and writing one would put a block on the reader's day that nothing claimed.
		 */
		$end_utc = '' !== (string) ( $item['end_utc'] ?? '' ) ? (string) $item['end_utc'] : $start_utc;
		$lines[] = 'DTSTART:' . axismundi_cal_ics_utc( $start_utc );
		$lines[] = 'DTEND:' . axismundi_cal_ics_utc( $end_utc );
	} else {
		// Both dates already exclusive at the end, which is what `VALUE=DATE` means and how the rows
		// are stored, so there is no off-by-one to apply here and none to get wrong.
		$lines[] = 'DTSTART;VALUE=DATE:' . axismundi_cal_ics_local( (string) $item['start_date'] . ' 00:00:00', true );
		$lines[] = 'DTEND;VALUE=DATE:' . axismundi_cal_ics_local( (string) $item['end_date'] . ' 00:00:00', true );
	}

	/*
	 * The stable half of the vocabulary, which is the half worth publishing. A subscriber filtering on
	 * `PUBLIC-HOLIDAY` can keep doing so after the site changes language, and that is exactly what
	 * Google's own holiday feed makes impossible by putting its classification in localized prose.
	 */
	$categories = axismundi_cal_normalize_categories( (string) ( $item['categories'] ?? '' ) );
	if ( array() !== $categories ) {
		$lines[] = 'CATEGORIES:' . axismundi_cal_ics_escape( implode( ',', $categories ) );
	}

	/*
	 * Transparent unless the row says otherwise. A holiday appearing on a subscriber's calendar should
	 * not make them look busy to their colleagues -- free/busy is about the person, and this document
	 * says nothing about any person.
	 */
	$lines[] = 'TRANSP:' . ( 'OPAQUE' === strtoupper( (string) ( $item['transparency'] ?? '' ) ) ? 'OPAQUE' : 'TRANSPARENT' );

	$description = trim( wp_strip_all_tags( (string) ( $item['description'] ?? '' ) ) );
	if ( '' !== $description ) {
		$lines[] = 'DESCRIPTION:' . axismundi_cal_ics_escape( $description );
	}
	$lines[] = 'END:VEVENT';
	return $lines;
}

/**
 * Build the subscription document for one maintained Calendar.
 *
 * Written in the site's language and in no other. A body that varied with `Accept-Language` would
 * make the `ETag` a lie: two clients would hold different documents under one validator, and a cache
 * in front of the site would serve whichever it saw first to everybody. The language a subscriber
 * wants is chosen by picking the feed, which is why there is one per Calendar.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return array{body:string,modified:int}|null
 */
function axismundi_cal_dataset_feed( array $calendar ) : ?array {
	$from = gmdate( 'Y-m-d', (int) strtotime( '-' . AXISMUNDI_CAL_FEED_PAST_MONTHS . ' months' ) );
	$to   = gmdate( 'Y-m-d', (int) strtotime( '+' . AXISMUNDI_CAL_DATASET_FEED_YEARS . ' years' ) );

	$items = axismundi_cal_system_items_in_range( (int) $calendar['id'], $from, $to );

	/*
	 * The site's language, pinned for the duration of the build rather than assumed. `determine_locale()`
	 * is filterable and a multilingual plugin is entitled to answer it from the request, which is the
	 * one thing this document cannot allow.
	 */
	$switched = switch_to_locale( get_locale() );

	$components = array();
	$modified   = 0;
	foreach ( $items as $item ) {
		$components = array_merge( $components, axismundi_cal_dataset_ics_vevent( $item, $calendar ) );
		$modified   = max( $modified, (int) strtotime( (string) $item['updated_at'] . ' UTC' ) );
	}
	$body = axismundi_cal_ics_document(
		$components,
		/*
		 * No VTIMEZONE, and nothing that would need one. Every component is either a floating date or a
		 * UTC instant, so a zone definition here would be an unused block that invites somebody to start
		 * writing local times against it.
		 */
		array(),
		(int) strtotime( $from ),
		(int) strtotime( $to ),
		axismundi_cal_calendar_display_name( $calendar )
	);

	if ( $switched ) {
		restore_previous_locale();
	}

	return array(
		'body' => $body,
		// A dataset with nothing in the window is still a calendar, and an empty one is the honest
		// answer. `Last-Modified` falls back to now so a client is not handed the epoch.
		'modified' => $modified > 0 ? $modified : time(),
	);
}
