<?php
/**
 * The dataset subscription surface (dev-only; dist-excluded).
 *
 * What this file exists to catch is the class of failure a subscriber cannot recover from. A `.ics`
 * is fetched and then kept: a client stores what it was given and re-reads it offline, so a holiday
 * that went out on the wrong day, under no name, or carrying somebody's address stays wrong in every
 * calendar that took it. The REST view has no equivalent exposure -- it is corrected on the next
 * request -- which is why the assertions here are about the document rather than about the query.
 *
 * The separation from the Event writer is asserted rather than trusted. It is a rule about code that
 * has not been written yet: the properties Events will want next are the participation ones, and the
 * only durable way to state "those must never reach this document" is a check that fails when they
 * do.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_di_results   = array();
$ax_di_calendars = array();
$ax_di_users     = array();

/** @param bool[] $results Results. */
function ax_di_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** The content lines of one document, unfolded, so a property can be looked for as it was written. */
function ax_di_lines( string $body ) : array {
	return explode( "\r\n", str_replace( "\r\n ", '', $body ) );
}

/** Whether any content line begins with one property name. */
function ax_di_has( string $body, string $property ) : bool {
	foreach ( ax_di_lines( $body ) as $line ) {
		if ( str_starts_with( $line, $property . ':' ) || str_starts_with( $line, $property . ';' ) ) {
			return true;
		}
	}
	return false;
}

/** Every value written for one property. */
function ax_di_values( string $body, string $property ) : array {
	$out = array();
	foreach ( ax_di_lines( $body ) as $line ) {
		if ( str_starts_with( $line, $property . ':' ) ) {
			$out[] = substr( $line, strlen( $property ) + 1 );
		}
	}
	return $out;
}

try {
	/*
	 * No authority anywhere in this file, deliberately. `record_owner()` refuses to give a maintained
	 * calendar an Actor -- the site publishes these and nobody owns them -- so passing one here would
	 * fail every fixture and look like a permissions problem.
	 */
	$ax_di_suffix = (string) wp_rand( 1000, 9999 );
	$ax_di_ko     = (int) axismundi_cal_calendar_save(
		array(
			'name'            => '대한민국의 휴일',
			'slug'            => 'ax-di-ko-' . $ax_di_suffix,
			'timezone'        => 'Asia/Seoul',
			'kind'            => 'system',
			'system_provider' => 'holiday',
			'provider_config' => array( 'region' => 'KR', 'source_locale' => 'ko-KR' ),
		)
	);
	$ax_di_calendars[] = $ax_di_ko;
	$ax_di_en          = (int) axismundi_cal_calendar_save(
		array(
			'name'            => 'Holidays in South Korea',
			'slug'            => 'ax-di-en-' . $ax_di_suffix,
			'timezone'        => 'Asia/Seoul',
			'kind'            => 'system',
			'system_provider' => 'holiday',
			'provider_config' => array( 'region' => 'KR', 'source_locale' => 'en-US' ),
		)
	);
	$ax_di_calendars[] = $ax_di_en;

	/*
	 * The dataset the two editions are, which is what a holiday concept belongs to. Joined here rather
	 * than left implicit, because promoting an entry to a holiday needs somewhere to put it.
	 */
	$ax_di_catalog = (int) axismundi_cal_holiday_catalog_save(
		array( 'provider' => 'holiday', 'jurisdiction' => 'KR', 'scope' => 'public-holidays-and-observances', 'label' => 'ax-di catalog ' . $ax_di_suffix )
	);
	axismundi_cal_join_holiday_catalog( $ax_di_ko, $ax_di_catalog );
	axismundi_cal_join_holiday_catalog( $ax_di_en, $ax_di_catalog );

	$ax_di_year = (int) gmdate( 'Y' ) + 1;

	// -- The two temporal shapes, which are the two ways a date reaches a subscriber wrong ------------

	$ax_di_holiday = axismundi_cal_system_item_save(
		$ax_di_ko,
		array(
			'title'      => '광복절',
			'start_date' => $ax_di_year . '-08-15',
			'categories' => array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ),
			'status'     => 'published',
		)
	);
	$ax_di_phase = axismundi_cal_system_item_save(
		$ax_di_ko,
		array(
			'temporal_kind' => 'instant',
			'start_utc'     => $ax_di_year . '-08-28T00:30:00Z',
			'categories'    => array( 'ASTRONOMY', 'MOON-PHASE', 'FULL-MOON' ),
			'status'        => 'published',
		)
	);
	ax_di_assert(
		$ax_di_results,
		'the fixtures this file needs exist',
		$ax_di_ko > 0 && $ax_di_en > 0 && ! is_wp_error( $ax_di_holiday ) && ! is_wp_error( $ax_di_phase )
	);
	$ax_di_holiday = (int) $ax_di_holiday;
	$ax_di_phase   = (int) $ax_di_phase;

	$ax_di_feed = axismundi_cal_dataset_feed( (array) axismundi_cal_calendar_get( $ax_di_ko ) );
	$ax_di_body = is_array( $ax_di_feed ) ? (string) $ax_di_feed['body'] : '';
	ax_di_assert( $ax_di_results, 'a dataset produces a document', '' !== $ax_di_body && str_starts_with( $ax_di_body, 'BEGIN:VCALENDAR' ) );

	/*
	 * The failure this whole shape exists to prevent. A holiday written as a zoned midnight is the
	 * previous day for every subscriber west of the site, and the client keeps that reading.
	 */
	ax_di_assert(
		$ax_di_results,
		'a whole day is written as a DATE, with no time and no zone for a client to convert',
		in_array( 'DTSTART;VALUE=DATE:' . $ax_di_year . '0815', ax_di_lines( $ax_di_body ), true )
	);
	ax_di_assert(
		$ax_di_results,
		'and its end is the following day, which is what an exclusive DTEND means for a one-day entry',
		in_array( 'DTEND;VALUE=DATE:' . $ax_di_year . '0816', ax_di_lines( $ax_di_body ), true )
	);
	ax_di_assert(
		$ax_di_results,
		'a moment is written as a UTC date-time, leaving the day it falls on to the reader client',
		in_array( 'DTSTART:' . $ax_di_year . '0828T003000Z', ax_di_lines( $ax_di_body ), true )
	);
	ax_di_assert(
		$ax_di_results,
		'and is given no invented duration, since a phase does not last an hour',
		in_array( 'DTEND:' . $ax_di_year . '0828T003000Z', ax_di_lines( $ax_di_body ), true )
	);
	ax_di_assert(
		$ax_di_results,
		'no VTIMEZONE is written, because nothing in the document is a local time',
		! ax_di_has( $ax_di_body, 'BEGIN' ) || ! str_contains( $ax_di_body, 'BEGIN:VTIMEZONE' )
	);

	// -- Names, which is what step 3 and 4 were for --------------------------------------------------

	ax_di_assert(
		$ax_di_results,
		'an authored name is written as it was written',
		in_array( 'SUMMARY:광복절', ax_di_lines( $ax_di_body ), true )
	);
	/*
	 * The reason the public surface had to wait for the fallback. A VEVENT with no SUMMARY is kept by
	 * the subscriber as a nameless entry, and no later correction reaches it.
	 */
	$ax_di_summaries = ax_di_values( $ax_di_body, 'SUMMARY' );
	ax_di_assert(
		$ax_di_results,
		'and an entry named by its category still gets a SUMMARY, which is the one thing an .ics cannot be fixed for later',
		2 === count( $ax_di_summaries ) && ! in_array( '', array_map( 'trim', $ax_di_summaries ), true )
	);
	ax_di_assert(
		$ax_di_results,
		'the generated name being the phase, in the site language',
		in_array( 'SUMMARY:' . axismundi_cal_item_generated_name( array( 'MOON-PHASE', 'FULL-MOON' ) ), ax_di_lines( $ax_di_body ), true )
	);

	// -- Privacy, asserted about code that does not exist yet ----------------------------------------

	foreach ( array( 'ATTENDEE', 'ORGANIZER', 'CONTACT' ) as $ax_di_property ) {
		ax_di_assert(
			$ax_di_results,
			sprintf( 'a dataset document carries no %s, which is why it does not share the Event writer', $ax_di_property ),
			! ax_di_has( $ax_di_body, $ax_di_property )
		);
	}
	ax_di_assert( $ax_di_results, 'and no address of any kind reaches it', ! str_contains( $ax_di_body, 'mailto:' ) );

	// -- Categories, the half that survives a change of language -------------------------------------

	ax_di_assert(
		$ax_di_results,
		'the stable category keys are published, so a subscriber filter outlives a translation',
		in_array( 'CATEGORIES:HOLIDAY\,PUBLIC-HOLIDAY', ax_di_lines( $ax_di_body ), true )
	);
	ax_di_assert(
		$ax_di_results,
		'and a holiday does not make its subscribers look busy',
		count( array_filter( ax_di_values( $ax_di_body, 'TRANSP' ), static fn( string $v ) : bool => 'TRANSPARENT' !== $v ) ) === 0
	);

	// -- Review state --------------------------------------------------------------------------------

	$ax_di_draft = (int) axismundi_cal_system_item_save(
		$ax_di_ko,
		array( 'title' => '검토 전', 'start_date' => $ax_di_year . '-09-09', 'categories' => array( 'HOLIDAY' ) )
	);
	$ax_di_after = axismundi_cal_dataset_feed( (array) axismundi_cal_calendar_get( $ax_di_ko ) );
	ax_di_assert(
		$ax_di_results,
		'an unreviewed year is not published to subscribers, which is the point of reviewing it',
		$ax_di_draft > 0 && ! str_contains( (string) $ax_di_after['body'], '검토 전' )
	);

	// -- One feed per language -----------------------------------------------------------------------

	/*
	 * The consequence of publishing per Calendar rather than per catalog. Somebody who subscribes to
	 * both editions asked for both, and a UID shared between them would have their client silently
	 * merge the two and keep whichever language it read second.
	 */
	$ax_di_en_item = (int) axismundi_cal_system_item_save(
		$ax_di_en,
		array(
			'title'      => 'Liberation Day',
			'start_date' => $ax_di_year . '-08-15',
			'categories' => array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ),
			'status'     => 'published',
		)
	);
	$ax_di_concept = axismundi_cal_create_principal_holiday_from_item( $ax_di_holiday );
	$ax_di_linked  = ! is_wp_error( $ax_di_concept )
		? axismundi_cal_attach_item_to_holiday_concept( $ax_di_en_item, (int) $ax_di_concept, 'principal' )
		: $ax_di_concept;
	ax_di_assert( $ax_di_results, 'the two editions can be linked to one holiday', ! is_wp_error( $ax_di_linked ) );

	$ax_di_ko_uid = ax_di_values( (string) axismundi_cal_dataset_feed( (array) axismundi_cal_calendar_get( $ax_di_ko ) )['body'], 'UID' );
	$ax_di_en_uid = ax_di_values( (string) axismundi_cal_dataset_feed( (array) axismundi_cal_calendar_get( $ax_di_en ) )['body'], 'UID' );
	ax_di_assert(
		$ax_di_results,
		'and each edition still has its own UID, so subscribing to both gives both rather than one of them at random',
		array() !== $ax_di_en_uid && array() === array_intersect( $ax_di_ko_uid, $ax_di_en_uid )
	);

	/*
	 * The other half of the same rule. Re-reading a feed rewrites rows, and a UID that moved with the
	 * row would hand every subscriber a second copy of a day they already have.
	 */
	$ax_di_before_uid = $ax_di_ko_uid;
	axismundi_cal_system_item_save( $ax_di_ko, array( 'title' => '광복절 (수정)' ), $ax_di_holiday );
	$ax_di_after_uid = ax_di_values( (string) axismundi_cal_dataset_feed( (array) axismundi_cal_calendar_get( $ax_di_ko ) )['body'], 'UID' );
	ax_di_assert(
		$ax_di_results,
		'a corrected name does not change the UID, so a subscriber gets a correction rather than a duplicate',
		$ax_di_before_uid === $ax_di_after_uid
	);

	// -- Conditional GET -----------------------------------------------------------------------------

	$ax_di_one = (string) axismundi_cal_dataset_feed( (array) axismundi_cal_calendar_get( $ax_di_ko ) )['body'];
	$ax_di_two = (string) axismundi_cal_dataset_feed( (array) axismundi_cal_calendar_get( $ax_di_ko ) )['body'];
	ax_di_assert(
		$ax_di_results,
		'two builds of an unchanged dataset are byte-identical, without which the ETag would change on every poll',
		$ax_di_one === $ax_di_two
	);
} finally {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$ax_di_strays = (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . axismundi_cal_calendars_table() . " WHERE slug LIKE %s", 'ax-di-%' ) );
	foreach ( array_unique( array_merge( $ax_di_calendars, array_map( 'intval', $ax_di_strays ) ) ) as $ax_di_calendar ) {
		axismundi_cal_system_items_forget_calendar( (int) $ax_di_calendar );
		axismundi_cal_list_forget_calendar( (int) $ax_di_calendar );
		axismundi_cal_acl_forget_calendar( (int) $ax_di_calendar );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => (int) $ax_di_calendar ) );
	}
	foreach ( $ax_di_users as $ax_di_user_id ) {
		wp_delete_user( (int) $ax_di_user_id );
	}
}

$ax_di_failures = count( array_filter( $ax_di_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_di_results ), $ax_di_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_di_failures > 0 ? 1 : 0 );
}
exit( $ax_di_failures > 0 ? 1 : 0 );
