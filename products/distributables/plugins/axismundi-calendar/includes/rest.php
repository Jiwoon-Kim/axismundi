<?php
/**
 * The structured REST field the authoring panel reads and writes.
 *
 * One field, `axismundi_cal_envelope`, over the same single writer the importer and any future
 * caller use. The panel is an editing surface and nothing more: every rule that decides whether an
 * Event is well-formed -- the IANA timezone, `endTime` after `startTime`, the URL an `external`
 * join mode requires -- stays in `axismundi_cal_event_save()`, so a client cannot author its way around
 * one and a second authoring path cannot drift from the first.
 *
 * The envelope is a custom table rather than post meta, so this deliberately is not
 * `register_post_meta`: meta would give the editor a writer that bypasses the validating one.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The envelope as the panel sees it.
 *
 * A curated shape rather than the stored row. The GMT columns are derived from the local time and
 * the zone, and echoing them would invite a client to send them back as if they were authorable.
 * `previousStartsAtGmt` is readable because a rescheduled Event should be able to say so, but it is
 * written only by `save()` noticing the start moved.
 *
 * @param int $post_id Event post ID.
 * @return array<string,mixed>
 */
function axismundi_cal_rest_envelope( int $post_id ) : array {
	$envelope = axismundi_cal_event_get( $post_id );
	if ( ! is_array( $envelope ) ) {
		// An Event that has not been given its times yet. Reported as an empty envelope rather
		// than null so the panel has one shape to render in both states.
		return array(
			'calendarId'               => 0,
			'startsAt'                 => '',
			'endsAt'                   => '',
			'timezone'                 => '',
			'allDay'                   => false,
			'locations'                => array(),
			'visibility'               => 'default',
			'transparency'             => 'OPAQUE',
			'displayEndTime'           => true,
			'eventStatus'              => 'EventScheduled',
		'joinMode'                 => 'none',
			'externalParticipationUrl' => '',
			'maximumAttendeeCapacity'  => null,
			'remainingAttendeeCapacity' => null,
			'attendeeCount'            => 0,
			'previousStartsAtGmt'      => '',
			'rrule'                    => '',
			'recurring'                => false,
			'complete'                 => false,
		);
	}
	return array(
		'calendarId'               => (int) $envelope['calendar_id'],
		'startsAt'                 => (string) $envelope['starts_at'],
		'endsAt'                   => (string) $envelope['ends_at'],
		'timezone'                 => (string) $envelope['timezone'],
		'allDay'                   => (bool) $envelope['all_day'],
		/*
		 * Plain text, and only plain text. A `Place` is the geodata plugin's object with its own
		 * identity and its own validity rules; taking a bare id here would leave this plugin half-owning
		 * a model it cannot check, and would stop an Event being written until somebody had registered
		 * the venue. The `location_place_id` column stays unexposed until that contract exists.
		 */
		/*
		 * Only what this reader may be shown. This field travels with the post through the REST API, so
		 * anybody who can read a published Event can read it -- and a joining link kept for attendees
		 * would reach every logged-in account through the editor's own payload.
		 */
		'locations'                => axismundi_cal_event_visible_locations( $post_id ),
		/*
		 * Two axes. `visibility` is how much of this Event somebody who may see its Calendar is shown;
		 * whether they may see the Calendar at all is the Calendar's own setting, and the more
		 * restrictive of the two wins. `transparency` is a different question again -- not who may look,
		 * but whether looking should make them appear occupied.
		 */
		'visibility'               => (string) ( $envelope['visibility'] ?? 'default' ),
		'transparency'             => (string) ( $envelope['transparency'] ?? 'OPAQUE' ),
		'displayEndTime'           => (bool) $envelope['display_end_time'],
		'eventStatus'              => (string) $envelope['event_status'],
		'joinMode'                 => (string) $envelope['join_mode'],
		'externalParticipationUrl' => (string) $envelope['external_participation_url'],
		'maximumAttendeeCapacity'  => null === $envelope['maximum_attendee_capacity'] ? null : (int) $envelope['maximum_attendee_capacity'],
		/*
		 * Both derived, and neither writable. They are the accepted replies counted, so a panel field
		 * that could set them would be offering to edit an answer that is computed from somewhere else.
		 */
		'remainingAttendeeCapacity' => axismundi_cal_event_remaining_capacity( $post_id ),
		'attendeeCount'            => count( axismundi_cal_event_attendees( $post_id ) ),
		'previousStartsAtGmt'      => (string) ( $envelope['previous_starts_at_gmt'] ?? '' ),
		'rrule'                    => (string) ( $envelope['rrule'] ?? '' ),
		// Reported rather than left for the panel to infer from the rule, so the federation rule
		// and the notice the author reads cannot disagree about what counts as recurring.
		'recurring'                => '' !== trim( (string) ( $envelope['rrule'] ?? '' ) ),
		'complete'                 => true,
	);
}

/**
 * Whether an incoming envelope carries enough to be stored at all.
 *
 * The table cannot hold half an Event: both times and the zone are columns with no meaningful
 * empty value, and `save()` refuses anything less. A draft being filled in one field at a time
 * would therefore fail on every keystroke-triggered autosave before the author reached the last
 * field, so an incomplete envelope for an Event that has none yet is left unwritten instead of
 * rejected. Publishing is where incompleteness becomes an error, because that is where it starts
 * to matter -- see `axismundi_cal_guard_publish()`.
 *
 * @param array<string,mixed> $value Incoming envelope.
 * @return bool
 */
function axismundi_cal_rest_envelope_is_writable( array $value ) : bool {
	return '' !== trim( (string) ( $value['startsAt'] ?? '' ) )
		&& '' !== trim( (string) ( $value['endsAt'] ?? '' ) )
		&& (int) ( $value['calendarId'] ?? 0 ) > 0;
}

/**
 * Map the panel's shape onto the writer's field names.
 *
 * @param array<string,mixed> $value Incoming envelope.
 * @return array<string,mixed>
 */
function axismundi_cal_rest_to_fields( array $value ) : array {
	$map = array(
		'calendarId'               => 'calendar_id',
		'startsAt'                 => 'starts_at',
		'endsAt'                   => 'ends_at',
		'timezone'                 => 'timezone',
		'allDay'                   => 'all_day',
		'locations'                => 'locations',
		'visibility'               => 'visibility',
		'transparency'             => 'transparency',
		'displayEndTime'           => 'display_end_time',
		'eventStatus'              => 'event_status',
		'joinMode'                 => 'join_mode',
		'externalParticipationUrl' => 'external_participation_url',
		'maximumAttendeeCapacity'  => 'maximum_attendee_capacity',
		'rrule'                    => 'rrule',
	);
	$fields = array();
	foreach ( $map as $from => $to ) {
		if ( array_key_exists( $from, $value ) ) {
			$fields[ $to ] = $value[ $from ];
		}
	}
	return $fields;
}

/**
 * Register the envelope field on the Event REST resource.
 *
 * @return void
 */
function axismundi_cal_register_rest_field() : void {
	register_rest_field(
		AXISMUNDI_CAL_EVENT_POST_TYPE,
		'axismundi_cal_envelope',
		array(
			'get_callback'    => static function ( array $post ) : array {
				return axismundi_cal_rest_envelope( (int) $post['id'] );
			},
			'update_callback' => static function ( $value, WP_Post $post ) {
				// Dormant until the panel sends it, so any other write path is untouched.
				if ( ! is_array( $value ) ) {
					return true;
				}
				if ( ! current_user_can( 'edit_post', $post->ID ) ) {
					return new WP_Error( 'ax_event_forbidden', __( 'You cannot edit this Event.', 'axismundi-calendar' ), array( 'status' => 403 ) );
				}
				if ( isset( $value['calendarId'] ) && (int) $value['calendarId'] > 0 ) {
					$calendar = axismundi_cal_calendar_get( (int) $value['calendarId'] );
					if ( ! is_array( $calendar ) || ! axismundi_cal_can_manage_calendar( $calendar ) ) {
						return new WP_Error( 'ax_event_calendar_forbidden', __( 'You cannot add Events to that calendar.', 'axismundi-calendar' ), array( 'status' => 403 ) );
					}
				}
				if ( null === axismundi_cal_event_get( (int) $post->ID ) && ! axismundi_cal_rest_envelope_is_writable( $value ) ) {
					return true;
				}
				$result = axismundi_cal_event_save( (int) $post->ID, axismundi_cal_rest_to_fields( $value ) );
				return is_wp_error( $result ) ? $result : true;
			},
			'schema'          => array(
				'type'       => 'object',
				'context'    => array( 'edit' ),
					'properties' => array(
						'calendarId'               => array( 'type' => 'integer', 'minimum' => 1 ),
					'startsAt'                 => array( 'type' => 'string' ),
					'allDay'                   => array( 'type' => 'boolean' ),
					'locations'                => array( 'type' => 'array' ),
					'visibility'               => array( 'type' => 'string' ),
					'transparency'             => array( 'type' => 'string' ),
					'endsAt'                   => array( 'type' => 'string' ),
					'timezone'                 => array( 'type' => 'string' ),
					'displayEndTime'           => array( 'type' => 'boolean' ),
					'eventStatus'              => array( 'type' => 'string', 'enum' => axismundi_cal_event_statuses() ),
					'joinMode'                 => array( 'type' => 'string', 'enum' => axismundi_cal_event_join_modes() ),
					'externalParticipationUrl' => array( 'type' => 'string' ),
					'maximumAttendeeCapacity'  => array( 'type' => array( 'integer', 'null' ), 'minimum' => 1 ),
					'rrule'                    => array( 'type' => 'string' ),
					'recurring'                => array( 'type' => 'boolean', 'readonly' => true ),
					'previousStartsAtGmt'      => array( 'type' => 'string', 'readonly' => true ),
					'complete'                 => array( 'type' => 'boolean', 'readonly' => true ),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_cal_register_rest_field' );
