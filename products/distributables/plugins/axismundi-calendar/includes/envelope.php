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
	$schedule_fields = array();
	foreach ( array(
		'calendar_id'      => 'calendar_id',
		'starts_at'        => 'dtstart_local',
		'ends_at'          => 'dtend_local',
		'timezone'         => 'timezone',
		'display_end_time' => 'display_end_time',
		'all_day'          => 'all_day',
		'rrule'            => 'rrule',
	) as $from => $to ) {
		if ( array_key_exists( $from, $fields ) ) {
			$schedule_fields[ $to ] = $fields[ $from ];
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
	$join_mode = (string) ( $fields['join_mode'] ?? ( $existing['join_mode'] ?? 'free' ) );
	if ( ! in_array( $join_mode, axismundi_cal_event_join_modes(), true ) ) {
		return new WP_Error( 'ax_event_join_mode', __( 'That participation mode is not one FEP-8a8e defines.', 'axismundi-calendar' ), array( 'status' => 400 ) );
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
		'join_mode'                  => $join_mode,
		'external_participation_url' => $external,
		'maximum_attendee_capacity'  => $capacity,
		'created_at'                 => (string) ( $existing['created_at'] ?? $now ),
		'updated_at'                 => $now,
	);
	$formats = array( '%d', '%s', '%s', '%s', '%d', '%s', '%s' );
	if ( false === $wpdb->replace( axismundi_cal_events_table(), $data, $formats ) ) {
		return new WP_Error( 'ax_event_write', __( 'The event could not be saved.', 'axismundi-calendar' ) );
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
