<?php
/**
 * The Schedule: the one writer for when an Event happens.
 *
 * `wp_ax_events` used to hold the times. It no longer does, and this is a cutover rather than an
 * adapter: two tables both accepting writes for the same fact is a drift generator, and the drift
 * only shows up as a calendar disagreeing with a federated Object months later. The time columns on
 * the old table are dead after conversion -- not read, not written -- and the envelope reader now
 * composes its answer from here instead.
 *
 * `ical_uid` is minted once and stored, never derived from the slug or permalink. A derived UID
 * turns a slug edit into a second event in every subscriber's calendar rather than an update to the
 * first.
 *
 * `sequence` moves only when something a calendar client would re-sync for changes. Bumping it on
 * every post save would make each typo fix an update storm across every subscription.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The fields whose change means subscribers must be told.
 *
 * Deliberately not "anything in the row": a description edit is a change to the Event, not to when
 * or where it happens, and iCalendar clients treat a SEQUENCE bump as grounds to re-prompt every
 * attendee.
 */
const AXISMUNDI_CAL_SEQUENCE_FIELDS = array( 'timezone', 'all_day', 'dtstart_local', 'dtend_local', 'rrule', 'location_place_id', 'location_text' );

/**
 * The schedule for one Event, or null.
 *
 * @param int $post_id Event post ID.
 * @return array<string,mixed>|null
 */
function axismundi_cal_schedule_for_event( int $post_id ) : ?array {
	global $wpdb;
	if ( $post_id <= 0 || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_schedules_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE event_post_id = %d ORDER BY id ASC LIMIT 1", $post_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Mint the stable iCalendar UID for an Event.
 *
 * Built from the post id and the site host so it is stable for the lifetime of the Event and unique
 * across sites, and stored on first write so nothing later recomputes it differently.
 *
 * @param int $post_id Event post ID.
 * @return string
 */
function axismundi_cal_mint_ical_uid( int $post_id ) : string {
	$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	return sprintf( 'ax-event-%d@%s', $post_id, '' !== $host ? $host : 'localhost' );
}

/**
 * Write the schedule for one Event.
 *
 * @param int                 $post_id Event post ID.
 * @param array<string,mixed> $fields  Partial schedule.
 * @return int|WP_Error Schedule id.
 */
function axismundi_cal_schedule_save( int $post_id, array $fields ) {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_event_store', __( 'The event store is unavailable.', 'axismundi-calendar' ) );
	}
	$existing = axismundi_cal_schedule_for_event( $post_id );

	/*
	 * Chosen, never inherited. Falling back to the site timezone is right for a single-venue site
	 * and wrong for a federated calendar, where the site's zone is nobody's in particular: it would
	 * stamp a confident offset on an event whose author never said where it happens, and that offset
	 * travels to every peer.
	 */
	$timezone = (string) ( $fields['timezone'] ?? ( $existing['timezone'] ?? '' ) );
	if ( '' === $timezone ) {
		return new WP_Error( 'ax_event_timezone', __( 'An Event needs a timezone. Choose the one the event happens in.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
		return new WP_Error( 'ax_event_timezone', __( 'The timezone must be an IANA identifier.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$all_day = array_key_exists( 'all_day', $fields ) ? (int) (bool) $fields['all_day'] : (int) ( $existing['all_day'] ?? 0 );

	$start = axismundi_cal_normalize_datetime( (string) ( $fields['dtstart_local'] ?? ( $existing['dtstart_local'] ?? '' ) ), $timezone );
	if ( is_wp_error( $start ) ) {
		return $start;
	}
	$end = axismundi_cal_normalize_datetime( (string) ( $fields['dtend_local'] ?? ( $existing['dtend_local'] ?? '' ) ), $timezone );
	if ( is_wp_error( $end ) ) {
		return $end;
	}
	/*
	 * FEP-8a8e requires `endTime` to be later than `startTime`. Refused rather than repaired: an
	 * event that ends before it starts is a mistake only its author can resolve, and silently
	 * swapping or extending it would publish a time nobody chose.
	 */
	if ( strtotime( $end['gmt'] ) <= strtotime( $start['gmt'] ) ) {
		return new WP_Error( 'ax_event_range', __( 'An Event must end after it starts.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$rrule = trim( (string) ( $fields['rrule'] ?? ( $existing['rrule'] ?? '' ) ) );
	if ( '' !== $rrule ) {
		// Authored rules are validated and normalized here; an imported rule never reaches this
		// writer, because a subscribed feed is not ours to refuse.
		$validated = axismundi_cal_rrule_validate( $rrule );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}
		$rrule = $validated;
	}

	$next = array(
		'timezone'          => $timezone,
		'all_day'           => $all_day,
		'dtstart_local'     => $start['local'],
		'dtend_local'       => $end['local'],
		'rrule'             => $rrule,
		'location_place_id' => array_key_exists( 'location_place_id', $fields )
			? ( null === $fields['location_place_id'] ? null : (int) $fields['location_place_id'] )
			: ( isset( $existing['location_place_id'] ) && null !== $existing['location_place_id'] ? (int) $existing['location_place_id'] : null ),
		'location_text'     => (string) ( $fields['location_text'] ?? ( $existing['location_text'] ?? '' ) ),
	);

	$sequence = (int) ( $existing['sequence'] ?? 0 );
	$previous = $existing['previous_start_utc'] ?? null;
	if ( is_array( $existing ) ) {
		$changed = false;
		foreach ( AXISMUNDI_CAL_SEQUENCE_FIELDS as $field ) {
			// Compared as strings so that `null` and `''` and `0` do not read as three different
			// values for a location that was never set.
			if ( (string) ( $existing[ $field ] ?? '' ) !== (string) ( $next[ $field ] ?? '' ) ) {
				$changed = true;
				break;
			}
		}
		if ( $changed ) {
			++$sequence;
		}
		/*
		 * A rescheduled Event keeps the time it used to have, which is what tells a peer already
		 * holding it that this is a move rather than a new Event.
		 */
		if ( (string) $existing['dtstart_local'] !== $next['dtstart_local'] || (string) $existing['timezone'] !== $timezone ) {
			$previous = axismundi_cal_to_utc( (string) $existing['dtstart_local'], (string) $existing['timezone'] );
		}
	}

	$now  = current_time( 'mysql', true );
	$data = array_merge(
		$next,
		array(
			'event_post_id'          => $post_id,
			'ical_uid'               => (string) ( $existing['ical_uid'] ?? axismundi_cal_mint_ical_uid( $post_id ) ),
			'sequence'               => $sequence,
			'display_end_time'       => array_key_exists( 'display_end_time', $fields ) ? (int) (bool) $fields['display_end_time'] : (int) ( $existing['display_end_time'] ?? 1 ),
			'previous_start_utc'     => $previous,
			'materialized_until_utc' => $existing['materialized_until_utc'] ?? null,
			'created_at'             => (string) ( $existing['created_at'] ?? $now ),
			'updated_at'             => $now,
		)
	);

	$table = axismundi_cal_schedules_table();
	if ( is_array( $existing ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$written = $wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) );
		if ( false === $written ) {
			return new WP_Error( 'ax_event_write', __( 'The event could not be saved.', 'axismundi-calendar' ) );
		}
		$schedule_id = (int) $existing['id'];
	} else {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		if ( false === $wpdb->insert( $table, $data ) ) {
			return new WP_Error( 'ax_event_write', __( 'The event could not be saved.', 'axismundi-calendar' ) );
		}
		$schedule_id = (int) $wpdb->insert_id;
	}

	axismundi_cal_materialize( $schedule_id );
	return $schedule_id;
}

/**
 * Convert one local wall time in a zone to UTC.
 *
 * @param string $local    Local datetime.
 * @param string $timezone IANA zone.
 * @return string|null
 */
function axismundi_cal_to_utc( string $local, string $timezone ) : ?string {
	try {
		$zone = new DateTimeZone( '' !== $timezone ? $timezone : 'UTC' );
		return ( new DateTimeImmutable( $local, $zone ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	} catch ( Exception $error ) {
		return null;
	}
}

/**
 * Create the missing Schedule for every legacy Event envelope.
 *
 * The one-time conversion out of `wp_ax_events`. Idempotent by existence rather than by a flag, so
 * running it twice -- an upgrade that reruns, a half-finished migration -- cannot produce a second
 * schedule for one Event or overwrite a schedule that has since been edited.
 *
 * @return int Number of schedules created.
 */
function axismundi_cal_convert_legacy_envelopes() : int {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return 0;
	}
	$events    = axismundi_cal_events_table();
	$schedules = axismundi_cal_schedules_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time conversion over this plugin's own tables.
	$rows = (array) $wpdb->get_results(
		"SELECT e.* FROM {$events} e LEFT JOIN {$schedules} s ON s.event_post_id = e.post_id WHERE s.id IS NULL",
		ARRAY_A
	);

	$created = 0;
	foreach ( $rows as $row ) {
		$now  = current_time( 'mysql', true );
		$data = array(
			'event_post_id'          => (int) $row['post_id'],
			'timezone'               => (string) $row['timezone'],
			'all_day'                => 0,
			'dtstart_local'          => (string) $row['starts_at'],
			'dtend_local'            => (string) $row['ends_at'],
			// Every legacy Event is a single occurrence: recurrence could not be authored before
			// this table existed, so an empty rule is a fact rather than a default.
			'rrule'                  => '',
			'ical_uid'               => axismundi_cal_mint_ical_uid( (int) $row['post_id'] ),
			'sequence'               => 0,
			'display_end_time'       => (int) $row['display_end_time'],
			'previous_start_utc'     => $row['previous_starts_at_gmt'] ?? null,
			'materialized_until_utc' => null,
			'location_place_id'      => null,
			'location_text'          => '',
			'created_at'             => (string) ( $row['created_at'] ?? $now ),
			'updated_at'             => $now,
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		if ( false !== $wpdb->insert( $schedules, $data ) ) {
			++$created;
			axismundi_cal_materialize( (int) $wpdb->insert_id );
		}
	}
	return $created;
}
