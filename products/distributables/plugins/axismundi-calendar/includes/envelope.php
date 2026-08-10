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
	return is_array( $row ) ? $row : null;
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
	 * Chosen, never inherited. Falling back to the site timezone is right for a single-venue
	 * site and wrong for a federated calendar, where the site's zone is nobody's in particular:
	 * it would stamp a confident offset on an event whose author never said where it happens,
	 * and `startTime` travels with that offset to every peer. An unanswered question is asked
	 * rather than guessed.
	 */
	$timezone = (string) ( $fields['timezone'] ?? ( $existing['timezone'] ?? '' ) );
	if ( '' === $timezone ) {
		return new WP_Error( 'ax_event_timezone', __( 'An Event needs a timezone. Choose the one the event happens in.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
		return new WP_Error( 'ax_event_timezone', __( 'The timezone must be an IANA identifier.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$starts = axismundi_cal_normalize_datetime( (string) ( $fields['starts_at'] ?? ( $existing['starts_at'] ?? '' ) ), $timezone );
	if ( is_wp_error( $starts ) ) {
		return $starts;
	}
	$ends = axismundi_cal_normalize_datetime( (string) ( $fields['ends_at'] ?? ( $existing['ends_at'] ?? '' ) ), $timezone );
	if ( is_wp_error( $ends ) ) {
		return $ends;
	}
	/*
	 * FEP-8a8e requires `endTime` to be later than `startTime`. Refused rather than repaired: an
	 * event that ends before it starts is a mistake only its author can resolve, and silently
	 * swapping or extending it would publish a time nobody chose.
	 */
	if ( strtotime( $ends['gmt'] ) <= strtotime( $starts['gmt'] ) ) {
		return new WP_Error( 'ax_event_range', __( 'An Event must end after it starts.', 'axismundi-calendar' ), array( 'status' => 400 ) );
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

	/*
	 * A rescheduled Event keeps the time it used to have, which is what tells a peer that already
	 * holds it that this is a move rather than a new Event.
	 */
	$previous = $existing['previous_starts_at_gmt'] ?? null;
	if ( is_array( $existing ) && $existing['starts_at_gmt'] !== $starts['gmt'] ) {
		$previous = (string) $existing['starts_at_gmt'];
	}

	$capacity = $fields['maximum_attendee_capacity'] ?? ( $existing['maximum_attendee_capacity'] ?? null );
	$capacity = ( null === $capacity || '' === $capacity ) ? null : max( 1, (int) $capacity );

	$now  = current_time( 'mysql', true );
	$data = array(
		'post_id'                    => $post_id,
		'starts_at'                  => $starts['local'],
		'starts_at_gmt'              => $starts['gmt'],
		'ends_at'                    => $ends['local'],
		'ends_at_gmt'                => $ends['gmt'],
		'timezone'                   => $timezone,
		'display_end_time'           => array_key_exists( 'display_end_time', $fields ) ? (int) (bool) $fields['display_end_time'] : (int) ( $existing['display_end_time'] ?? 1 ),
		'previous_starts_at_gmt'     => $previous,
		'event_status'               => $status,
		'join_mode'                  => $join_mode,
		'external_participation_url' => $external,
		'maximum_attendee_capacity'  => $capacity,
		'created_at'                 => (string) ( $existing['created_at'] ?? $now ),
		'updated_at'                 => $now,
	);
	// Column order above; `replace` takes positional formats, so a column added in the middle takes
	// the next specifier and writes the wrong type silently.
	$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' );
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
