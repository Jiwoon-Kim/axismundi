<?php
/**
 * Reading and writing the event envelope.
 *
 * One writer, as the rest of Axismundi does it, so the editor, REST and any importer land on the
 * same validation rather than three copies of it.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * How much of one Event is shown to somebody who may see its Calendar.
 *
 * No `public`. The Calendar is the outer gate and an Event cannot open it, so a value saying
 * otherwise would be a setting that does nothing in exactly the case somebody would rely on it.
 */
const AXISMUNDI_CAL_EVENT_VISIBILITIES = array( 'default', 'private' );

/**
 * The envelope for one event post, or null when it has none.
 *
 * @param int $post_id Event post ID.
 * @return array<string,mixed>|null
 */
function axismundi_cal_event_get( int $post_id ) : ?array {
	global $wpdb;
	if ( $post_id <= 0 || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_events_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- primary-key lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE post_id = %d", $post_id ), ARRAY_A );
	if ( ! is_array( $row ) ) {
		return null;
	}
	$schedule = axismundi_cal_schedule_for_event( $post_id );
	if ( ! is_array( $schedule ) ) {
		// An Event whose envelope predates conversion. Reported as having no times rather than
		// falling back to the dead columns, so a failed conversion is visible instead of being
		// papered over by stale values that no longer receive writes.
		return null;
	}
	return array_merge(
		$row,
		array(
			'calendar_id'            => (int) $schedule['calendar_id'],
			'timezone'               => (string) $schedule['timezone'],
			'starts_at'              => (string) $schedule['dtstart_local'],
			'ends_at'                => (string) $schedule['dtend_local'],
			'starts_at_gmt'          => (string) ( axismundi_cal_to_utc( (string) $schedule['dtstart_local'], (string) $schedule['timezone'] ) ?? '' ),
			'ends_at_gmt'            => (string) ( axismundi_cal_to_utc( (string) $schedule['dtend_local'], (string) $schedule['timezone'] ) ?? '' ),
			'display_end_time'       => (int) $schedule['display_end_time'],
			'previous_starts_at_gmt' => $schedule['previous_start_utc'],
			'all_day'                => (int) $schedule['all_day'],
			// Where it happens, as text. The `location_place_id` beside it in the schedule stays out of
			// the envelope until the geodata plugin owns a Place contract to validate one against.
			'location_text'          => (string) $schedule['location_text'],
			'rrule'                  => (string) $schedule['rrule'],
			'ical_uid'               => (string) $schedule['ical_uid'],
			'sequence'               => (int) $schedule['sequence'],
			'schedule_id'            => (int) $schedule['id'],
		)
	);
}

/**
 * Normalize one datetime field into its local and UTC pair.
 *
 * Both are kept because neither derives from the other without the zone, and the zone is a property
 * of the event rather than of the site.
 *
 * @param string $value    Local datetime, `Y-m-d H:i:s`.
 * @param string $timezone IANA timezone name.
 * @return array{local:string,gmt:string}|WP_Error
 */
function axismundi_cal_normalize_datetime( string $value, string $timezone ) {
	$value = trim( $value );
	if ( '' === $value ) {
		return new WP_Error( 'ax_event_datetime', __( 'A date and time is required.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	try {
		$zone  = new DateTimeZone( '' !== $timezone ? $timezone : 'UTC' );
		$local = new DateTimeImmutable( $value, $zone );
	} catch ( Exception $error ) {
		return new WP_Error( 'ax_event_datetime', __( 'The date, time or timezone could not be read.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	return array(
		'local' => $local->format( 'Y-m-d H:i:s' ),
		'gmt'   => $local->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
	);
}

/**
 * Write the envelope for one event post.
 *
 * @param int                 $post_id Event post ID.
 * @param array<string,mixed> $fields  Partial envelope.
 * @return true|WP_Error
 */
function axismundi_cal_event_save( int $post_id, array $fields ) {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_event_store', __( 'The event store is unavailable.', 'axismundi-calendar' ) );
	}
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || AXISMUNDI_CAL_EVENT_POST_TYPE !== $post->post_type ) {
		return new WP_Error( 'ax_event_post', __( 'An Event post is required.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$existing = axismundi_cal_event_get( $post_id );

	/*
	 * Time is written through the Schedule and nowhere else. The legacy time columns on this table
	 * are dead after conversion: two tables accepting writes for one fact drift, and the drift only
	 * surfaces later as a calendar disagreeing with the Object it federated.
	 */
	/*
	 * Settled before anything is written. An unfiled Event goes to the acting Actor's own Calendar, so
	 * the question "published by whom" has to be answered before the schedule picks one -- not after,
	 * when the Event would already be filed under whoever happened to be typing.
	 */
	$acting = axismundi_cal_resolve_event_acting_actor( $post_id, $fields, $existing );
	if ( is_wp_error( $acting ) ) {
		return $acting;
	}

	$schedule_fields = array();
	foreach ( array(
		'calendar_id'      => 'calendar_id',
		'starts_at'        => 'dtstart_local',
		'ends_at'          => 'dtend_local',
		'timezone'         => 'timezone',
		'display_end_time' => 'display_end_time',
		'all_day'          => 'all_day',
		'location_text'    => 'location_text',
		'rrule'            => 'rrule',
	) as $from => $to ) {
		if ( array_key_exists( $from, $fields ) ) {
			$schedule_fields[ $to ] = $fields[ $from ];
		}
	}
	/*
	 * Whose Calendar an unfiled Event lands in. Named here rather than left to the schedule's own
	 * fallback so that it follows the Actor being published as: somebody posting as an Organization
	 * files it on the Organization's calendar, not on their personal one.
	 */
	if ( empty( $schedule_fields['calendar_id'] ) && ! is_array( axismundi_cal_schedule_for_event( $post_id ) ) && $acting > 0 && function_exists( 'axismundi_actors_get_by_identity' ) ) {
		$acting_actor = axismundi_actors_get_by_identity( (int) $acting );
		if ( $acting_actor instanceof Axismundi_Actor ) {
			$primary = axismundi_cal_ensure_primary_calendar( (string) $acting_actor->get_uri() );
			if ( is_wp_error( $primary ) ) {
				return $primary;
			}
			$schedule_fields['calendar_id'] = (int) $primary;
		}
	}

	$schedule = axismundi_cal_schedule_save( $post_id, $schedule_fields );
	if ( is_wp_error( $schedule ) ) {
		return $schedule;
	}

	$status = (string) ( $fields['event_status'] ?? ( $existing['event_status'] ?? 'EventScheduled' ) );
	if ( ! in_array( $status, axismundi_cal_event_statuses(), true ) ) {
		return new WP_Error( 'ax_event_status', __( 'That event status is not one FEP-8a8e defines.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$join_mode = (string) ( $fields['join_mode'] ?? ( $existing['join_mode'] ?? 'none' ) );
	if ( ! in_array( $join_mode, axismundi_cal_event_join_modes(), true ) ) {
		return new WP_Error( 'ax_event_join_mode', __( 'That participation mode is not one FEP-8a8e defines.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	/*
	 * Kept whatever the mode is. `followers` on an Event currently admitting nobody is inert rather
	 * than wrong, and refusing to store it would silently reset the host's choice every time they
	 * closed an Event and reopened it -- losing a restriction on the way back in, which is the
	 * direction that matters.
	 */
	$eligibility = (string) ( $fields['join_eligibility'] ?? ( $existing['join_eligibility'] ?? 'public' ) );
	if ( ! in_array( $eligibility, AXISMUNDI_CAL_JOIN_ELIGIBILITIES, true ) ) {
		return new WP_Error( 'ax_event_join_eligibility', __( 'An event is open either to anyone or to the people following its host.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	/*
	 * `default` means "whatever the Calendar allows", which is the only honest default: an Event that
	 * declared itself public would be making a promise the two-axis rule refuses to keep.
	 */
	$visibility = (string) ( $fields['visibility'] ?? ( $existing['visibility'] ?? 'default' ) );
	if ( ! in_array( $visibility, AXISMUNDI_CAL_EVENT_VISIBILITIES, true ) ) {
		return new WP_Error( 'ax_event_visibility', __( 'An event is either shown as its calendar allows or kept private.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$transparency = strtoupper( (string) ( $fields['transparency'] ?? ( $existing['transparency'] ?? 'OPAQUE' ) ) );
	if ( ! in_array( $transparency, array( 'OPAQUE', 'TRANSPARENT' ), true ) ) {
		return new WP_Error( 'ax_event_transparency', __( 'An event either takes up the time or leaves it free.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	/*
	 * Participation is Actor to Actor, and the host is one end of it.
	 *
	 * `Join`, `Invite`, `Accept` and `Reject` all take an Actor as their subject, so an Event whose
	 * host has none cannot answer a request that arrives -- and turning participation on for one would
	 * be offering a handshake with nobody on the other side of it. Refused at the point the setting is
	 * made rather than when somebody tries to use it, so the host learns why while they can still fix
	 * it, instead of a guest discovering it on their behalf.
	 *
	 * This is also what keeps the replies ActivityPub-native from end to end. Admitting a host without
	 * one would mean a participant identity that is a local user id, and every screen reading the
	 * table would have to carry both models from its first line.
	 */
	if ( in_array( $join_mode, AXISMUNDI_CAL_JOINABLE_MODES, true ) && '' === axismundi_cal_event_owner_actor_uri( $post_id ) ) {
		return new WP_Error( 'ax_event_join_host_actor', __( 'Taking replies needs an actor profile to receive them, which this event\'s author does not have.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	/*
	 * A policy inviting replies needs somewhere those replies can come from. A private Event cannot be
	 * discovered or read by an ordinary Actor, so `free` or `restricted` on one is a promise with
	 * nothing behind it -- and it would read on screen as an event accepting people that nobody can
	 * find. Sending them elsewhere stays allowed, because that URL is the whole mechanism.
	 */
	if ( 'private' === $visibility && in_array( $join_mode, AXISMUNDI_CAL_JOINABLE_MODES, true ) ) {
		return new WP_Error( 'ax_event_join_private', __( 'A private event cannot take replies, because nobody outside can find it.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$external = esc_url_raw( (string) ( $fields['external_participation_url'] ?? ( $existing['external_participation_url'] ?? '' ) ) );
	if ( 'external' === $join_mode && '' === $external ) {
		return new WP_Error( 'ax_event_external_url', __( 'External participation needs the URL people are sent to.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$capacity = $fields['maximum_attendee_capacity'] ?? ( $existing['maximum_attendee_capacity'] ?? null );
	$capacity = ( null === $capacity || '' === $capacity ) ? null : max( 1, (int) $capacity );

	$now  = current_time( 'mysql', true );
	$data = array(
		'post_id'                    => $post_id,
		'event_status'               => $status,
		'visibility'                 => $visibility,
		'transparency'               => $transparency,
		'join_mode'                  => $join_mode,
		'join_eligibility'           => $eligibility,
		'external_participation_url' => $external,
		'maximum_attendee_capacity'  => $capacity,
		'acting_actor_identity_id'   => (int) $acting,
		'created_at'                 => (string) ( $existing['created_at'] ?? $now ),
		'updated_at'                 => $now,
	);
	$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' );
	if ( false === $wpdb->replace( axismundi_cal_events_table(), $data, $formats ) ) {
		return new WP_Error( 'ax_event_write', __( 'The event could not be saved.', 'axismundi-calendar' ) );
	}

	/*
	 * Where it happens, if the caller said. Written after the envelope so a rejected location cannot
	 * leave the row half-updated, and the primary physical place is copied onto the schedule from here
	 * -- the per-occurrence override needs a baseline to differ from, and one writer deriving it is why
	 * the two cannot disagree.
	 */
	if ( array_key_exists( 'locations', $fields ) ) {
		$located = axismundi_cal_event_locations_save( $post_id, (array) $fields['locations'] );
		if ( is_wp_error( $located ) ) {
			return $located;
		}
		/*
		 * Nothing is copied onto the schedule. `location_text` there is the per-occurrence override --
		 * "this week we are in room B" -- and writing the list's first entry into it would make the same
		 * fact true in two places, which is how a later edit to one leaves the other stale. Readers
		 * resolve it instead: the override if there is one, otherwise the first visible physical place.
		 *
		 * The list is part of when-and-where, so a changed venue is a changed Event to a subscriber.
		 */
		axismundi_cal_schedule_bump_sequence( $post_id );
	}

	axismundi_cal_event_refresh_projection( $post_id );
	return true;
}

/**
 * Tell Object Projections that this Event's listing facts may have changed.
 *
 * Through the single writer that owns the index rather than touching it here, so the three products
 * that feed it cannot drift into three different opinions of the same row.
 *
 * @param int $post_id Event post ID.
 * @return void
 */
function axismundi_cal_event_refresh_projection( int $post_id ) : void {
	if ( ! function_exists( 'axismundi_op_refresh_object_listing_projection' ) || ! function_exists( 'axismundi_op_post_object_uri' ) ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || AXISMUNDI_CAL_EVENT_POST_TYPE !== $post->post_type ) {
		return;
	}
	axismundi_op_refresh_object_listing_projection( axismundi_op_post_object_uri( $post ) );
}

/**
 * Keep the index in step with the post's own lifecycle.
 *
 * Publishing, unpublishing and trashing all change whether an Event is listable without touching
 * the envelope, so the envelope writer alone is not enough.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function axismundi_cal_event_sync_projection_on_status( int $post_id ) : void {
	axismundi_cal_event_refresh_projection( $post_id );
}
add_action( 'save_post_' . AXISMUNDI_CAL_EVENT_POST_TYPE, 'axismundi_cal_event_sync_projection_on_status', 30, 1 );
add_action( 'trashed_post', 'axismundi_cal_event_sync_projection_on_status', 10, 1 );
add_action( 'untrashed_post', 'axismundi_cal_event_sync_projection_on_status', 10, 1 );
