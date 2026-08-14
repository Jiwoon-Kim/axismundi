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
 * Record that when-or-where changed, without a field of its own to compare.
 *
 * `SEQUENCE` tells a subscriber that what they hold is out of date, and the schedule compares its own
 * columns to decide. An Event's locations live in their own table now, so a changed venue is invisible
 * to that comparison -- and a venue change is exactly the kind a client should re-prompt about.
 *
 * @param int $post_id Event post ID.
 * @return void
 */
function axismundi_cal_schedule_bump_sequence( int $post_id ) : void {
	global $wpdb;
	$schedule = axismundi_cal_schedule_for_event( $post_id );
	if ( ! is_array( $schedule ) ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update(
		axismundi_cal_schedules_table(),
		array( 'sequence' => (int) $schedule['sequence'] + 1, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'id' => (int) $schedule['id'] )
	);
}

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

	$calendar_id = array_key_exists( 'calendar_id', $fields )
		? (int) $fields['calendar_id']
		: (int) ( $existing['calendar_id'] ?? 0 );
	if ( $calendar_id <= 0 ) {
		/*
		 * No Calendar named, so the Event goes to the author's own -- created here if this is their
		 * first. Deliberately not a site-wide fallback Calendar: that one belongs to nobody, so
		 * nothing on it can be federated, shared or administered, and everyone's Events would be
		 * attributed to the same anonymous collection.
		 *
		 * A writer with no Actor is refused rather than given somewhere to put it. There is no
		 * correct answer to "whose Event is this?" in that case, and inventing one is what the
		 * unfiled Calendar did.
		 */
		$primary = axismundi_cal_ensure_primary_calendar( axismundi_cal_current_actor_uri() );
		if ( is_wp_error( $primary ) ) {
			return $primary;
		}
		$calendar_id = (int) $primary;
	}
	$calendar = axismundi_cal_calendar_get( $calendar_id );
	if ( ! is_array( $calendar ) || 'local' !== (string) $calendar['kind'] ) {
		return new WP_Error( 'ax_event_calendar', __( 'Choose a local calendar for this Event.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$calendar_timezone = axismundi_cal_calendar_timezone( $calendar );
	if ( '' === $calendar_timezone ) {
		return new WP_Error( 'ax_event_calendar_timezone', __( 'The selected calendar needs an IANA timezone before it can hold Events.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	/*
	 * Calendar time is the authoring default. A schedule keeps an explicit zone once written so an
	 * existing cross-region Event and a legacy import retain their actual instant; a new Event with
	 * no override inherits the selected Calendar's named place.
	 */
	$timezone = (string) ( $fields['timezone'] ?? ( $existing['timezone'] ?? $calendar_timezone ) );
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
		'calendar_id'       => $calendar_id,
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
	if ( ! is_array( $existing ) || (int) $existing['calendar_id'] !== $calendar_id ) {
		if ( is_array( $existing ) && (int) $existing['calendar_id'] > 0 ) {
			axismundi_cal_bump_revision( (int) $existing['calendar_id'] );
		}
		axismundi_cal_bump_revision( $calendar_id );
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
			'calendar_id'            => 0,
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

/**
 * Assign every legacy Schedule to exactly one Calendar before the membership table disappears.
 *
 * The oldest recorded membership wins. An Event that had none is placed in a single local
 * "Unfiled events" Calendar instead of being left with no owner; no Event is discarded and no
 * schedule timezone is rewritten during this structural migration.
 *
 * @return void
 */
function axismundi_cal_assign_orphan_schedules() : void {
	global $wpdb;
	$schedules = axismundi_cal_schedules_table();
	$legacy    = $wpdb->prefix . 'ax_cal_calendar_items';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema migration over this plugin's own table.
	$has_legacy = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $legacy ) );
	if ( $legacy !== $has_legacy ) {
		$unfiled_id = axismundi_cal_ensure_unfiled_calendar();
		if ( $unfiled_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- final migration fallback for schedules created after the legacy table disappeared.
			$wpdb->update( $schedules, array( 'calendar_id' => $unfiled_id ), array( 'calendar_id' => 0 ), array( '%d' ), array( '%d' ) );
		}
		return;
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema migration over this plugin's own tables.
	$orphans = (array) $wpdb->get_results( "SELECT id, event_post_id FROM {$schedules} WHERE calendar_id = 0", ARRAY_A );
	if ( empty( $orphans ) ) {
		return;
	}
	$unfiled_id = 0;
	foreach ( $orphans as $schedule ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- deterministic legacy membership lookup.
		$calendar_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT calendar_id FROM {$legacy} WHERE event_post_id = %d ORDER BY created_at ASC, id ASC LIMIT 1",
				(int) $schedule['event_post_id']
			)
		);
		if ( $calendar_id <= 0 ) {
			if ( $unfiled_id <= 0 ) {
				$unfiled_id = axismundi_cal_ensure_unfiled_calendar();
			}
			$calendar_id = $unfiled_id;
		}
		if ( $calendar_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema migration over this plugin's own table.
			$wpdb->update( $schedules, array( 'calendar_id' => $calendar_id ), array( 'id' => (int) $schedule['id'] ), array( '%d' ), array( '%d' ) );
		}
	}
}

/**
 * The one migration-only Calendar for legacy Events that had no membership at all.
 *
 * Only ever reached from `axismundi_cal_assign_orphan_schedules()`, and never from a writer. It has
 * no authority by design -- an upgrade cannot know whose those Events were -- which is exactly why
 * nothing on it federates and why the Calendars screen asks an administrator to assign one or delete
 * it. A new Event goes to its author's own Calendar; see `axismundi_cal_ensure_primary_calendar()`.
 *
 * @return int
 */
function axismundi_cal_ensure_unfiled_calendar() : int {
	global $wpdb;
	$calendars = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- stable migration lookup.
	$existing = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$calendars} WHERE slug = %s", 'unfiled-events' ) );
	if ( $existing > 0 ) {
		return $existing;
	}
	$now = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration creates one row in this plugin's own table.
	$wpdb->insert(
		$calendars,
		array(
			'slug'            => 'unfiled-events',
			'name'            => __( 'Unfiled events', 'axismundi-calendar' ),
			'description'     => __( 'Events preserved while Calendar ownership was introduced.', 'axismundi-calendar' ),
			'timezone'        => 'UTC',
			'kind'            => 'local',
			'visibility'      => 'public',
			'revision'        => 1,
			'owner_actor_uri' => '',
			'created_at'      => $now,
			'updated_at'      => $now,
		)
	);
	return (int) $wpdb->insert_id;
}
